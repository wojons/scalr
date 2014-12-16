Scalr.regPage('Scalr.ui.farms.builder.tabs.proxy', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Proxy settings',
		itemId: 'proxy',
        tabData: null,

        layout: 'fit',

        cls: 'scalr-ui-farmbuilder-roleedit-tab',
        
        /*settings: {
            'nginx.proxies': undefined
        },*/

		isEnabled: function (record) {
			return record.get('behaviors').match("www");
		},

		showTab: function (record) {
			var me = this,
                settings = record.get('settings'),
                p = me.down('proxysettings');

            me.roles = [];
			moduleTabParams.farmRolesStore.each(function(r){
				if (r != record) {
                    var location = r.get('cloud_location');
					me.roles.push({id: r.get('farm_role_id'), name: r.get('alias') + (location ? ' (' + location + ')' : '')});
                }
			});

            p.setValue({
                'nginx.proxies': Ext.decode(settings['nginx.proxies']) || []
            });
		},

		hideTab: function (record) {
			var settings = record.get('settings');

            Ext.apply(settings, this.down('proxysettings').getValue());
            settings['nginx.proxies'] = Ext.encode(settings['nginx.proxies']);
			record.set('settings', settings);
		},

		items: [{
            xtype: 'proxysettings',
            proxyDefaults: moduleTabParams['nginx'],
            formConfig: {
                overflowY: 'auto',
                overflowX: 'hidden',
                hidden: true
            }
		}]
	});
});

Ext.define('Scalr.ui.ProxySettingsField', {
	extend: 'Ext.container.Container',
	alias: 'widget.proxysettings',

    mode: 'edit',

    initComponent: function(){
        this.store = Ext.create('Ext.data.Store', {
            fields: ['hostname', {name: 'port', type: 'int'}, 'backends', 'backend_ip_hash', 'backend_least_conn', 'server_template', 'ssl', 'ssl_certificate_id', 'ssl_port', {name: 'http', defaultValue: '0'}],
            proxy: 'object'
        });
        var config;
        if (this.mode === 'edit') {
            config = {
                layout: {
                    type: 'hbox',
                    align: 'stretch'
                },
                items: [
                    Ext.apply(this.getGridConfig(), {store: this.store})
                ,{
                    xtype: 'container',
                    layout: 'fit',
                    flex: 1,
                    items: this.getFormConfig()
                }]
            }
        } else {
            config = { items: Ext.apply(this.getFormConfig())};
        }
        Ext.apply(this, config);
        this.callParent(arguments);
    },

    onDestroy: function(){
        this.callParent(arguments);
        delete this.store;
    },

    getValue: function(){
        var list = [];
        this.down('#form').deselectRecord(this.mode === 'edit');
        (this.store.snapshot || this.store.data).each(function(record){
            var data = record.getData();
            delete data.id;
            list.push(data);
        });
        //some data conversion for scalarizr
        Ext.Array.each(list || [], function(proxy, pindex){
            var backends = [],
                templates = {};
            Ext.Array.each(proxy['backends'], function(backend, bindex) {
                var location = ((backend.location+'').indexOf('/') === 0 ? '' : '/') + backend.location;
                Ext.Array.each(backend.locations, function(item){
                    item.location = location;
                    backends.push(item);
                });
                if (templates[location] === undefined) {
                    templates[location] = {content: backend.template, location: location}
                }
            });
            proxy['backends'] = backends;
            proxy['templates'] = Ext.Object.getValues(templates);
            proxy['templates'].push({content: proxy['server_template'], server: true});
            
            delete proxy['server_template'];
        });
        if (this.mode === 'edit') {
            var selModel = this.down('grid').getSelectionModel();
            selModel.setLastFocused(null);
            selModel.deselectAll();
        }
        return {
            'nginx.proxies': list
        };
    },

    setValue: function(value){
        var data = [];
        //some data conversion for scalarizr
        Ext.Array.each(value['nginx.proxies'] || [], function(proxy, pindex){
            var templates = {};
            if (proxy['templates']) {
                Ext.Array.each(proxy['templates'], function(template){
                    if (template.location !== undefined) {
                        templates[template.location] = template.content;
                    } else if (template.server) {
                        proxy['server_template'] = template.content;
                    }
                });
            }
            if (proxy['backends'] && proxy['backends'].length) {
                var backends = {};
                Ext.Array.each(proxy['backends'], function(backend, bindex) {
                    backends[backend.location || ''] = backends[backend.location || ''] || [];
                    backends[backend.location || ''].push(backend);
                });
                proxy['backends'].length = 0;
                Ext.Object.each(backends, function(location, list){
                    proxy['backends'].push({
                        location: location ? (location.indexOf('/') === 0 ? location.substr(1) : location) : null,
                        template: templates[location] || '',
                        locations: list
                    });
                });
            }
        });

        this.store.loadData(value['nginx.proxies']);
        if (this.mode === 'add') {
            var form = this.down('#form');
            delete form._record;
            form.loadRecord(this.store.createModel({}));
        }
    },

    getGridConfig: function() {
        return Ext.apply({
            xtype: 'grid',
            itemId: 'grid',
            cls: 'x-panel-column-left x-grid-shadow',
            bodyStyle: 'box-shadow: none',
            multiSelect: true,
            enableColumnResize: false,
            maxWidth: 600,
            minWidth: 300,
            padding: '12 0 0',
            flex: .7,
            features: {
                ftype: 'addbutton',
                text: 'Add proxy',
                handler: function(view) {
                    var grid = view.up('grid'),
                        selModel = grid.getSelectionModel(),
                        form = grid.up('proxysettings').down('#form');
                    selModel.setLastFocused(null);
                    selModel.deselectAll();
                    form.loadRecord(grid.getStore().createModel({}));
                }
            },
            plugins: [{
                ptype: 'focusedrowpointer',
                thresholdOffset: 26,
            }],
            columns: [{
                text: 'Hostname',
                xtype: 'templatecolumn',
                sortable: true,
                tpl: '{hostname:htmlEncode}',
                flex: 1
            },{
                text: 'Port',
                xtype: 'templatecolumn',
                sortable: true,
                tpl: '{port:htmlEncode}',
                width: 80
            }, {
                xtype: 'templatecolumn',
                tpl: '<img style="cursor:pointer" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Delete proxy" src="'+Ext.BLANK_IMAGE_URL+'"/>',
                width: 42,
                sortable: false,
                dataIndex: 'id',
                align:'left'
            }],
            viewConfig: {
                overflowY: 'auto',
                overflowX: 'hidden'
            },
            listeners: {
                viewready: function() {
                    var me = this,
                        form = me.up('proxysettings').down('#form');
                    me.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                        if (oldFocused != newFocused) {
                            if (gridSelModel.lastFocused) {
                                if (gridSelModel.lastFocused != form.getRecord()) {
                                    form.loadRecord(gridSelModel.lastFocused);
                                }
                            } else {
                                form.deselectRecord(true);
                            }
                        }
                    });
                },
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('img.x-icon-action-delete')) {
                        var selModel = view.getSelectionModel();
                        if (record === selModel.getLastFocused()) {
                            selModel.deselectAll();
                            selModel.setLastFocused(null);
                        }
                        view.store.remove(record);
                        return false;
                    }
                }
            }
        }, this.gridConfig);
    },
    getFormConfig: function(){
        return Ext.apply({
            xtype: 'container',
            itemId: 'form',
            layout: 'anchor',
            defaults: {
                anchor: '100%'
            },
            getRecord: function(){
                return this._record;
            },
            loadRecord: function(record){
                var p = this.up('proxysettings'),
                    mode = p.mode,
                    defaults = p.proxyDefaults || {},
                    sslCertId = record.get('ssl_certificate_id');
                this.isLoading = true;
                this.saveRecord();
                this._record = record;
                if (sslCertId) {//preload cert list only if necessary
                    this.down('[name="proxy.ssl_certificate_id"]').store.load();
                }

                this.setFieldValues({
                    'proxy.hostname': record.get('hostname'),
                    'proxy.port': record.get('port') || '',
                    'proxy.backend_ip_hash': record.get('backend_ip_hash'),
                    'proxy.backend_least_conn': record.get('backend_least_conn'),
                    'proxy.server_template': record.get('server_template') || (defaults['server_section'] + '\n' + defaults['server_section_ssl']),
                    'proxy.ssl': record.get('ssl'),
                    'proxy.ssl_certificate_id': sslCertId,
                    'proxy.ssl_port': record.get('ssl_port') || 443,
                    'proxy.http': record.get('http') != 1
                });
                this.down('#backends').setValue(record.get('backends'));
                this.down('#options').setVisible(mode === 'edit' ? record.store !== undefined : true);
                if (mode === 'edit') {
                    this.show();
                    this.down('[name="proxy.hostname"]').focus();
                }
                this.checkIpHashVsBackup();
                this.isLoading = false;
            },
            saveRecord: function(removeEmpty){
                if (this._record !== undefined) {
                    var p = this.up('proxysettings'),
                        mode = p.mode,
                        defaults = p.proxyDefaults || {},
                        values = {
                            'hostname': this.down('[name="proxy.hostname"]').getValue(),
                            'port': this.down('[name="proxy.port"]').getValue(),
                            'backend_ip_hash': this.down('[name="proxy.backend_ip_hash"]').getValue() ? '1' : '0',
                            'backend_least_conn': this.down('[name="proxy.backend_least_conn"]').getValue() ? '1' : '0',
                            'server_template': this.down('[name="proxy.server_template"]').getValue() || (defaults['server_section'] + '\n' + defaults['server_section_ssl']),
                            'ssl': this.down('[name="proxy.ssl"]').getValue() ? '1' : '0'
                        },
                        sslCertId = this.down('[name="proxy.ssl_certificate_id"]').getValue();
                    if (values.ssl == '1' && sslCertId) {
                        Ext.apply(values, {
                            'ssl_certificate_id': sslCertId,
                            'ssl_port': this.down('[name="proxy.ssl_port"]').getValue(),
                            'http': this.down('[name="proxy.http"]').getValue() ? '0' : '1'
                        });
                    } else {
                        values.ssl = '0';
                    }
                    values.backends = this.down('#backends').getValue();
                    if (values.hostname && values.port) {
                        this._record.set(values);
                        if (mode === 'add') {
                            if (!this._record.store) {
                                p.store.add(this._record);
                            }

                        }
                    } else if (removeEmpty && this._record.store){
                        this._record.store.remove(this._record);
                    }

                }
            },
            updateRecord: function() {
                if (this.isLoading) return;
                var isValid = this.validateRecord();
                if (isValid === true) {
                    var hp = this.up('proxysettings'),
                        mode = hp.mode;
                    if (mode === 'edit') {
                        var grid = hp.down('#grid');
                        this._record.set({
                            hostname: this.down('[name="proxy.hostname"]').getValue(),
                            port: this.down('[name="proxy.port"]').getValue()
                        });
                        if (this._record.store === undefined) {
                            grid.getStore().add(this._record);
                            this.down('#backends').setValue();
                            this.down('#options').show();
                            grid.getSelectionModel().setLastFocused(this._record, true);
                        }
                    }
                } else if (Ext.isString(isValid)){
                    Scalr.message.Error(isValid);
                }

            },
            
            checkIpHashVsBackup: function(){
                var ipHashBtn = this.down('[name="proxy.backend_ip_hash"]'),
                    backupExists = false;
                this.down('#backends').items.each(function(){
                    this.down('#locations').items.each(function(){
                        var btn = this.down('[name="proxy.backup"]');
                        if (ipHashBtn.pressed) {
                            btn.toggle(false);
                        }
                        btn.setTooltip(ipHashBtn.pressed ? 'Backup is not available with IP hash enabled' : btn.initialConfig.tooltip);
                        btn.setDisabled(ipHashBtn.pressed);
                        backupExists = backupExists || btn.pressed;
                    });
                });
                ipHashBtn.setTooltip(!ipHashBtn.pressed && backupExists ? 'IP hash is not available with backup server' : ipHashBtn.initialConfig.tooltip);
                ipHashBtn.setDisabled(!ipHashBtn.pressed && backupExists);
            },

            validateRecord: function(){
                var portField = this.down('[name="proxy.port"]'),
                    hostnameField = this.down('[name="proxy.hostname"]'),
                    res = true;
                if (this.up('proxysettings').mode === 'edit') {
                    if (!hostnameField.validate() || !portField.validate()) {
                        res = false;
                    }
                } else {
                    if (Ext.String.trim(hostnameField.getValue()) || Ext.String.trim(portField.getValue()) || this.down('#backends').hasNonEmptyItems()) {
                        res = hostnameField.validate() || {comp: hostnameField};
                        if (res === true) {
                            res = portField.validate() || {comp: portField};
                            if (res === true) {
                                res = this.down('#backends').validate();
                            }
                        }
                    }
                }
                return res;
            },
            deselectRecord: function(hide){
                this.saveRecord();
                delete this._record;
                if (hide) {
                    this.hide();
                }
            },
            items: [{
                xtype: 'fieldset',
                title: 'Proxy settings',
                cls: 'x-fieldset-separator-none',
                layout: {
                    type: 'hbox',
                    align: 'middle'
                },
                items: [{
                    xtype: 'textfield',
                    name: 'proxy.hostname',
                    submitValue: false,
                    allowBlank: false,
                    emptyText: 'Hostname',
                    flex: 1,
                    maxWidth: 690,
                    listeners: {
                        change: {
                            fn: function(comp, value){
                                this.up('#form').updateRecord();
                            },
                            buffer: 300
                        }
                    }
                },{
                    xtype: 'label',
                    text: ' : ',
                    padding: '0 6'
                },{
                    xtype: 'textfield',
                    name: 'proxy.port',
                    submitValue: false,
                    emptyText: 'Port',
                    width: 60,
                    maskRe: new RegExp('[0123456789]', 'i'),
                    validator: function(value){
                        return value*1>0 || 'Value is invalid.';
                    },
                    listeners: {
                        change: {
                            fn: function(comp, value){
                                this.up('#form').updateRecord();
                            },
                            buffer: 300
                        }
                    }

                }]
            },{
                xtype: 'container',
                itemId: 'options',
                hidden: true,
                items: [{
                    xtype: 'container',
                    cls: 'x-container-fieldset x-grid-shadow',
                    padding: '0 32',
                    layout: 'anchor',
                    defaults: {
                        maxWidth: 760,
                        anchor: '100%'
                    },
                    items: [{
                        xtype: 'container',
                        layout: 'hbox',
                        cls: 'x-not-a-grid-header x-grid-header-ct',
                        items: [{
                            xtype: 'component',
                            html: '<div class="x-column-header-inner"><span class="x-column-header-text">URI</span></div>',
                            cls: 'x-column-header x-column-header-first',
                            flex: .46
                        },{
                            xtype: 'component',
                            html: '<div class="x-column-header-inner"><span class="x-column-header-text">Destination</span></div>',
                            cls: 'x-column-header',
                            minWidth: 260,
                            flex: 1
                        },{
                            xtype: 'component',
                            html: '<div class="x-column-header-inner"><span class="x-column-header-text">Flags</span></div>',
                            width: 76,
                            cls: 'x-column-header'
                        },{
                            xtype: 'component',
                            cls: 'x-column-header x-column-header-last',
                            width: 58
                        }]
                    },{
                        xtype: 'container',
                        cls: 'x-not-a-grid-body',
                        items: {
                            xtype: 'container',
                            cls: 'x-not-a-grid-view',
                            itemId: 'backends',
                            setValue: function(value){
                                var me = this;
                                value = value || [];
                                me.suspendLayouts();
                                me.removeAll();
                                if (value.length !== 0) {
                                    Ext.Array.each(value, function(item){
                                        me.addItem(item);
                                    });
                                } else {
                                    me.addItem();
                                }
                                me.resumeLayouts(true);
                            },

                            getValue: function(){
                                var backends = [];
                                this.items.each(function(item){
                                    var backend = {
                                        location: item.down('[name="proxy.location"]').getValue(),
                                        template: item.down('[name="proxy.template"]').getValue(),
                                        locations: []
                                    }, locationsCount = item.down('#locations').items.length;
                                    item.down('#locations').items.each(function(destination){
                                        var type = destination.down('[name="proxy.type"]').getValue(),
                                            location = {}, field, network;
                                        location[type] = destination.down('[name="proxy.' + type + '"]').getValue();
                                        location['port'] = destination.down('[name="proxy.port"]').getValue();
                                        if (type === 'farm_role_id') {
                                            network = destination.down('[name="proxy.network"]').getValue();
                                            if (network) {
                                                location['network'] = network;
                                            }
                                        }
                                        if (locationsCount > 1) {
                                            location['weight'] = destination.down('[name="proxy.weight"]').getValue();
                                        }
                                        if (location[type] && location['port']) {
                                            field = destination.down('[name="proxy.backup"]');
                                            if (field) {
                                                location['backup'] = field.getValue() ? '1' : '0';
                                            }
                                            field = destination.down('[name="proxy.down"]');
                                            if (field) {
                                                location['down'] = field.getValue() ? '1' : '0';
                                            }
                                            backend.locations.push(location);
                                        }
                                    });
                                    backends.push(backend);
                                });
                                return backends;
                            },

                            validate: function(silent){
                                var result = true,
                                    field;
                                this.items.each(function(item){
                                    item.down('#locations').items.each(function(destination){
                                        var type = destination.down('[name="proxy.type"]').getValue();
                                        field = destination.down('[name="proxy.' + type + '"]');
                                        result = field.validate() || {comp: field};
                                        if (result === true) {
                                            field = destination.down('[name="proxy.port"]');
                                            result = field[silent ? 'isValid' : 'validate']() || {comp: field};
                                        }
                                        return result === true;

                                    });
                                });
                                return result;
                            },

                            hasNonEmptyItems: function(){
                                var result = false;
                                this.items.each(function(item){
                                    if (item.down('[name="proxy.location"]').getValue()) {
                                        result = true;
                                    } else {
                                        item.down('#locations').items.each(function(destination){
                                            result = !!destination.down('[name="proxy.' + destination.down('[name="proxy.type"]').getValue() + '"]').getValue();
                                            return !result;

                                        });
                                    }
                                    return !result;
                                });
                                return result;
                            },

                            addItem: function(data) {
                                data = data || {};
                                this.add({
                                    xtype: 'proxysettingsbackend',
                                    roles: this.up('#proxy').roles,
                                    cls: 'x-item',
                                    values: data || {}

                                });
                                var form = this.up('#form');
                                if (form.floating) {
                                    form.center();
                                }
                            },

                            updateItemsCls: function(){
                                this.items.each(function(item, index){
                                    item[index % 2 ? 'addCls' : 'removeCls']('x-item-alt');
                                });
                            },

                            listeners: {
                                add: function() {
                                    this.updateItemsCls();
                                },
                                remove: function() {
                                    this.updateItemsCls();
                                }
                            }
                        }
                    },{
                        xtype: 'button',
                        cls: 'x-not-a-grid-button-add',
                        text: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-grid-add-item" />&nbsp;&nbsp;Add backend',
                        handler: function() {
                            this.up('#form').down('#backends').addItem();
                        }
                    }]
                },{
                    xtype: 'container',
                    cls: 'x-container-fieldset',
                    layout: 'hbox',
                    items: [{
                        xtype: 'checkbox',
                        name: 'proxy.ssl',
                        boxLabel: 'Enable SSL',
                        margin: '0 20 0 0',
                        submitValue: false,
                        listeners: {
                            change: function(comp, checked) {
                                comp.next().setVisible(checked);
                            }
                        }
                    },{
                        xtype: 'container',
                        itemId: 'proxy.ssloptions',
                        layout: 'hbox',
                        hidden: true,
                        flex: 1,
                        maxWidth: 660,
                        defaults: {
                            margin: '0 13 0 0'
                        },
                        items: [{
                            xtype: 'combo',
                            name: 'proxy.ssl_certificate_id',
                            flex: 1,
                            emptyText: 'Choose certificate',
                            valueField: 'id',
                            displayField: 'name',
                            allowBlank: false,

                            forceSelection: true,
                            queryCaching: false,
                            minChars: 0,
                            queryDelay: 10,
                            store: {
                                fields: [ 'id', 'name' ],
                                proxy: {
                                    type: 'cachedrequest',
                                    crscope: 'farmbuilder',
                                    url: '/services/ssl/certificates/xListCertificates',
                                    filterFields: ['name']
                                }
                            },
                            plugins: [{
                                ptype: 'comboaddnew',
                                url: '/services/ssl/certificates/create',
                                disabled: !Scalr.isAllowed('SERVICES_SSL')
                            }],
                            listConfig: {
                                width: 'auto',
                                style: 'white-space:nowrap'
                            },
                            listeners: {
                                addnew: function(item) {
                                    Scalr.CachedRequestManager.get('farmbuilder').setExpired({url: '/services/ssl/certificates/xListCertificates'});
                                }
                            }
                        },{
                            xtype: 'textfield',
                            name: 'proxy.ssl_port',
                            submitValue: false,
                            fieldLabel: 'HTTPS port',
                            labelWidth: 80,
                            width: 135
                        },{
                            xtype: 'buttonfield',
                            name: 'proxy.http',
                            cls: 'x-button-text-dark',
                            text: 'HTTP &rarr; HTTPS',
                            tooltip: 'Redirect HTTP to HTTPS',
                            inputValue: 1,
                            enableToggle: true,
                            submitValue: false,
                            margin: 0
                        }]
                    }]
                },{
                    xtype: 'fieldset',
                    title: 'Advanced settings',
                    cls: 'x-fieldset-separator-top-bottom',
                    collapsible: true,
                    collapsed: true,
                    layout: 'anchor',
                    items: [{
                        xtype: 'container',
                        layout: 'hbox',
                        items: [{
                            xtype: 'buttonfield',
                            name: 'proxy.backend_ip_hash',
                            margin: '0 12 0 0',
                            text: 'IP hash',
                            inputValue: 1,
                            width: 80,
                            enableToggle: true,
                            submitValue: false,
                            handler: function() {
                                this.up('#form').checkIpHashVsBackup();
                            }
                        },{
                            xtype: 'buttonfield',
                            name: 'proxy.backend_least_conn',
                            text: 'Least connections',
                            inputValue: 1,
                            width: 150,
                            enableToggle: true,
                            submitValue: false
                        }]
                    },{
                        xtype: 'textarea',
                        name: 'proxy.server_template',
                        fieldLabel: 'Server template',
                        labelAlign: 'top',
                        anchor: '100%',
                        height: 200,
                        maxWidth: 760
                    }]
                }]
            }]
        }, this.formConfig)
    }
});

Ext.define('Scalr.ui.ProxySettingsBackend', {
	extend: 'Ext.container.Container',
    alias: 'widget.proxysettingsbackend',
    layout: 'hbox',

    reminder: {
        fn: function(comp, value){
            if (value) {
                var cont = this.up('proxysettings'),
                    field = cont.down('[name="proxy.hostname"]'),
                    message;
                if (!field.getValue()) {
                    message = 'Don\'t forget to enter proxy hostname.';
                } else {
                    field = cont.down('[name="proxy.port"]');
                    if (!field.getValue()) {
                        message = 'Don\'t forget to enter proxy port.';
                    }
                }
                
                if (message) {
                    Scalr.message.InfoTip(message, field.getEl());
                }
            }
        },
        buffer: 300
    },
	initComponent : function() {
		var me = this;
        me.callParent();
        me.add([{
            xtype: 'hiddenfield',
            name: 'proxy.template',
            value: me.values['template'],
            submitValue: false
        },{
            xtype: 'textfield',
            name: 'proxy.location',
            fieldLabel: '/',
            labelSeparator: '',
            labelWidth: 6,
            submitValue: false,
            flex: .36,
            padding: '0 8 0 0',
            value: me.values['location'],
            listeners: {
                change: me.reminder
            }
        },{
            xtype: 'container',
            itemId: 'locations',
            layout: 'anchor',
            defaults: {
                anchor: '100%'
            },
            flex: 1,
            minWidth: 330,
            padding: '0 0 0 8',
            updateItems: function(){
                var me = this;
                me.items.each(function(item, index){
                    item.down('#add').setVisible(index === 0);
                    item.down('#delete').setVisible(index !== 0);
                    item.down('[name="proxy.weight"]').setVisible(me.items.length > 1);
                });
            },
            listeners: {
                add: function() {
                    this.updateItems();
                },
                remove: function() {
                    this.updateItems();
                }
            }
        },{
            xtype: 'button',
            ui: 'action',
            cls: 'x-btn-action-tmpl',
            tooltip: 'Edit template',
            margin: '0 0 0 12',
            handler: function() {
                var tmplField = this.up().down('[name="proxy.template"]');
                Scalr.Confirm({
                    form: {
                        xtype: 'container',
                        cls: 'x-container-fieldset',
                        layout: 'anchor',
                        items: [{
                            xtype: 'textarea',
                            name: 'template',
                            anchor: '100%',
                            height: 180,
                            value: tmplField.getValue()
                        }]
                    },
                    formWidth: 500,
                    ok: 'Save',
                    title: 'Edit template',
                    //formValidate: true,
                    closeOnSuccess: true,
                    success: function (formValues) {
                        tmplField.setValue(formValues['template']);
                        return true;
                    }
                });
            }
        },{
            xtype: 'button',
            itemId: 'delete',
            ui: 'action',
            cls: 'x-btn-action-delete',
            margin: '0 0 0 12',
            handler: function() {
                var item = this.up('proxysettingsbackend');
                item.ownerCt.remove(item);
            }
        }]);

        if (me.values.locations && me.values.locations.length > 0) {
            var ct = this.down('#locations');
            ct.suspendLayouts();
            Ext.Array.each(me.values.locations, function(backend){
                me.addDestination(backend);
            });
            ct.resumeLayouts(true);
        } else {
            me.addDestination();
        }

	},

    addDestination: function(data) {
        var ct = this.down('#locations'),
            item;
        item = ct.add({
            xtype: 'container',
            layout: 'anchor',
            margin: '0 0 10 0',
            items: [{
                xtype: 'container',
                layout: 'hbox',
                margin: '0 0 3 0',
                items:[{
                    xtype: 'buttongroupfield',
                    name: 'proxy.type',
                    value: 'host',
                    defaults: {
                        width: 45
                    },
                    items: [{
                        value: 'host',
                        text: 'Host'
                    },{
                        value: 'farm_role_id',
                        text: 'Role',
                        disabled: !this.roles.length,
                        tooltip: !this.roles.length ? 'No roles available' : ''
                    }],
                    margin: '0 5 0 3',
                    listeners: {
                        change: function(comp, value) {
                            var ct = comp.up('container');
                            ct.suspendLayouts();
                            ct.down('[name="proxy.host"]').setVisible(value === 'host');
                            ct.down('[name="proxy.farm_role_id"]').setVisible(value === 'farm_role_id');
                            ct.up().down('#network').setVisible(value === 'farm_role_id');
                            ct.resumeLayouts(true);
                        }
                    }
                },{
                    xtype: 'combo',
                    name: 'proxy.farm_role_id',
                    emptyText: 'Select role',
                    valueField: 'id',
                    displayField: 'name',
                    store: {
                        fields: [ 'id', 'name' ],
                        proxy: 'object',
                        data: this.roles
                    },
                    editable: false,
                    allowBlank: false,
                    queryMode: 'local',
                    hidden: true,
                    flex: 1,
                    listeners: {
                        change: this.reminder
                    }
                },{
                    xtype: 'textfield',
                    name: 'proxy.host',
                    emptyText: 'IP or hostname',
                    allowBlank: false,
                    flex: 1,
                    listeners: {
                        change: this.reminder
                    }
                },{
                    xtype: 'textfield',
                    name: 'proxy.port',
                    emptyText: 'port',
                    allowBlank: false,
                    fieldLabel: ':',
                    labelSeparator: '',
                    labelWidth: 4,
                    margin: '0 4',
                    width: 65,
                    value: 80
                },{
                    xtype: 'textfield',
                    name: 'proxy.weight',
                    emptyText: 'weight',
                    margin: '0 4',
                    maxWidth: 50,
                    hidden: true,
                    value: ''
                },{
                    xtype: 'button',
                    itemId: 'add',
                    ui: 'action',
                    cls: 'x-btn-action-add',
                    margin: '0 12 0 4',
                    handler: function() {
                        this.up('proxysettingsbackend').addDestination();
                    }
                },{
                    xtype: 'button',
                    itemId: 'delete',
                    ui: 'action',
                    cls: 'x-btn-action-remove',
                    margin: '0 12 0 4',
                    handler: function() {
                        item.ownerCt.remove(item);
                    }
                }]
            },{
                xtype: 'container',
                itemId: 'network',
                hidden: true,
                padding: '0 0 0 68',
                layout: {
                    type: 'hbox',
                    align: 'middle'
                },
                items: [{
                    xtype: 'label',
                    text: 'Use'
                },{
                    xtype: 'combo',
                    name: 'proxy.network',
                    editable: false,
                    width: 100,
                    margin: '0 6',
                    value: '',
                    store: [['','Auto'],['public','Public'],['private','Private']],
                    icons: {
                        szrversion: {tooltipData: {version: '2.11.6'}}
                    }
                },{
                    xtype: 'label',
                    text: 'upstream IPs'
                }]
            }]
        });
        item.down('container').add([{
            xtype: 'buttonfield',
            ui: 'flag',
            cls: 'x-btn-flag-backup',
            tooltip: 'Backup',
            margin: '0 0 0 8',
            name: 'proxy.backup',
            inputValue: 1,
            enableToggle: true,
            submitValue: false,
            handler: function() {
                this.up('#form').checkIpHashVsBackup();
            }
        },{
            xtype: 'buttonfield',
            ui: 'flag',
            cls: 'x-btn-flag-down',
            tooltip: 'Down',
            margin: '0 0 0 6',
            name: 'proxy.down',
            inputValue: 1,
            enableToggle: true,
            submitValue: false
        }])
        if (data) {
            item.setFieldValues({
                'proxy.type': data.farm_role_id !== undefined && item.down('[name="proxy.farm_role_id"]').findRecordByValue(data.farm_role_id) ? 'farm_role_id' : 'host',
                'proxy.farm_role_id': data.farm_role_id,
                'proxy.host': data.host,
                'proxy.port': data.port || 80,
                'proxy.network': data.network || '',
                'proxy.weight': data.weight || '',
                'proxy.backup': data.backup,
                'proxy.down': data.down
            });
        }
    }
});

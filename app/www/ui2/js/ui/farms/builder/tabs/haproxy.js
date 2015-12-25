Ext.define('Scalr.ui.FarmRoleEditorTab.Haproxy', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'HAProxy',
    itemId: 'haproxy',

    layout: 'fit',

    /*settings: {
        'haproxy.proxies': undefined
    },*/

    isEnabled: function (record) {
        return this.callParent(arguments) && record.get('behaviors').match("haproxy");
    },

    showTab: function (record) {
        var me = this,
            settings = record.get('settings', true),
            haproxySettings = Ext.decode(settings['haproxy.proxies']),
            errors = record.get('errors', true) || {},
            invalidIndex,
            hp = me.down('haproxysettings');

        if (Ext.isObject(errors) && Ext.isObject(errors['haproxy.proxies'])) {
            invalidIndex = errors['haproxy.proxies'].invalidIndex;
        }

        me.roles = [];
        this.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.each(function(r){
            if (r != record) {
                var location = r.get('cloud_location');
                me.roles.push({id: r.get('farm_role_id'), alias: r.get('alias'), name: r.get('alias') + (location ? ' (' + location + ')' : '')});
            }
        });

        //convert deprecated configurations farm_role_id->farm_role_alias
        Ext.each(haproxySettings || [], function(haproxy){
            Ext.each(haproxy['backends'] || [], function(backend){
                if (backend['farm_role_id']) {
                    var farmRoleAlias = null;
                    Ext.each(me.roles, function(role){
                        if (role.id == backend['farm_role_id']) {
                            farmRoleAlias = role.alias;
                            return false;
                        }
                    });
                    if (farmRoleAlias) {
                        backend['farm_role_alias'] = farmRoleAlias;
                        delete backend['farm_role_id'];
                    }
                }
            });
        });

        hp.setValue({
            'haproxy.proxies': haproxySettings,
            'haproxy.template' : settings['haproxy.template'] || ''
        }, invalidIndex);
    },

    hideTab: function (record) {
        var settings = record.get('settings'),
            haproxySettings = this.down('haproxysettings').getValue();

        settings['haproxy.proxies'] = Ext.encode(haproxySettings['haproxy.proxies']);
        settings['haproxy.template'] = haproxySettings['haproxy.template'];
        record.set('settings', settings);
    },

    __items: [{
        xtype: 'haproxysettings',
        formConfig: {
            overflowY: 'auto',
            overflowX: 'hidden',
            preserveScrollPosition: true,
            hidden: true
        }
    }]
});

Ext.define('Scalr.ui.HaproxySettingsField', {
	extend: 'Ext.container.Container',
	alias: 'widget.haproxysettings',

    mode: 'edit',

    initComponent: function(){
        this.store = Ext.create('Ext.data.Store', {
            fields: [{name: 'port', type: 'int'}, 'description', 'backends', 'healthcheck.interval', 'healthcheck.fallthreshold', 'healthcheck.risethreshold', 'template'],
            proxy: 'object'
        });
        var config;
        if (this.mode === 'edit') {
            config = {
                layout: {
                    type: 'hbox',
                    align: 'stretch'
                },
                items: [{
                    xtype: 'container',
                    autoScroll: true,
                    cls: 'x-panel-column-left x-panel-column-left-with-tabs',
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },
                    maxWidth: 600,
                    minWidth: 360,
                    flex: .7,
                    items: [
                        Ext.apply(this.getGridConfig(), {store: this.store}),
                    ,{
                        xtype: 'component',
                        cls: 'x-fieldset-subheader',
                        html: 'Config template',
                        margin: '24 12 0'
                    },{
                        xtype: 'textarea',
                        name: 'template',
                        height: 200,
                        margin: '12 12 0',
                        inputAttrTpl: 'wrap="off"'
                    }]
                },{
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

        this.down('#form').saveRecord();
        this.store.getUnfiltered().each(function(record){
            var data = record.getData();
            delete data.id;
            list.push(data);
        });
        var value = {
            'haproxy.proxies': list
        };

        if (this.mode === 'edit') {
            this.down('#grid').clearSelectedRecord();
            value['haproxy.template'] = this.down('[name="template"]').getValue();
        }
        return value;
    },

    setValue: function(value, selectedIndex){
        var me = this;
        me.store.loadData(value['haproxy.proxies'] || []);
        if (me.mode === 'add') {
            me.down('#form').loadRecord(me.store.createModel({}));
        } else {
            me.down('[name="template"]').setValue(value['haproxy.template']);
        }

        if (Ext.isNumeric(selectedIndex)) {
            var grid = me.down('#grid');
            cb = function(){
                grid.setSelectedRecord(me.store.getAt(selectedIndex));
                me.down('#form').isValid();
            };
            if (me.rendered) {
                cb();
            } else {
                me.on('afterrender', cb);
            }
        }

    },

    getGridConfig: function() {
        return Ext.apply({
            xtype: 'grid',
            itemId: 'grid',
            padding: '12 12 0',
            enableColumnResize: false,
            features: {
                ftype: 'addbutton',
                text: 'Add proxy',
                handler: function(view) {
                    var grid = view.up('grid');
                    grid.clearSelectedRecord();
                    grid.up('haproxysettings').down('#form').loadRecord(grid.getStore().createModel({}));
                }
            },
            plugins: [{
                ptype: 'focusedrowpointer',
                thresholdOffset: 26
            },{
                ptype: 'selectedrecord',
                getForm: function() {
                    return this.grid.up('haproxysettings').down('#form');
                }
            }],
            columns: [{
                text: 'Port',
                sortable: true,
                dataIndex: 'port',
                width: 80
            },{
                text: 'Description',
                sortable: true,
                dataIndex: 'description',
                flex: 1
            },{
                xtype: 'templatecolumn',
                tpl: '<img class="x-grid-icon x-grid-icon-delete" title="Delete proxy" src="'+Ext.BLANK_IMAGE_URL+'"/>',
                width: 42,
                sortable: false,
                dataIndex: 'id',
                align:'left'
            }],
            listeners: {
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('img.x-grid-icon-delete')) {
                        view.store.remove(record);
                        return false;
                    }
                }
            }
        }, this.gridConfig);
    },
    getFormConfig: function(){
        return Ext.apply({
            xtype: 'form',
            itemId: 'form',
            layout: 'anchor',
            defaults: {
                anchor: '100%'
            },
            listeners: {
                resetrecord: function(record) {
                    this.saveRecord(record);
                },
                loadrecord: function(record) {
                    var mode = this.up('haproxysettings').mode;
                    this.setFieldValues({
                        'haproxy.port': record.get('port') || '',
                        'haproxy.description': record.get('description'),
                        'haproxy.healthcheck.interval': !Ext.isEmpty(record.get('healthcheck.interval'), true) ? record.get('healthcheck.interval') : 30,
                        'haproxy.healthcheck.fallthreshold': !Ext.isEmpty(record.get('healthcheck.fallthreshold'), true) ? record.get('healthcheck.fallthreshold') : 5,
                        'haproxy.healthcheck.risethreshold': !Ext.isEmpty(record.get('healthcheck.risethreshold'), true) ? record.get('healthcheck.risethreshold') : 3,
                        'haproxy.template': record.get('template') || ''

                    });
                    this.down('#backends').setValue(record.get('backends'));
                    this.down('#options').setVisible(mode === 'edit' ? record.store !== undefined : true);
                    if (mode === 'edit') {
                        this.show();
                        if (!record.store) {
                            this.down('[name="haproxy.port"]').focus();
                        }
                    }
                }
            },
            saveRecord: function(record){
                record = record || this.getRecord();
                if (record) {
                    var hp = this.up('haproxysettings'),
                        mode = hp.mode,
                        values = {
                            'port': this.down('[name="haproxy.port"]').getValue(),
                            'description': this.down('[name="haproxy.description"]').getValue(),
                            'healthcheck.interval': this.down('[name="haproxy.healthcheck.interval"]').getValue(),
                            'healthcheck.fallthreshold': this.down('[name="haproxy.healthcheck.fallthreshold"]').getValue(),
                            'healthcheck.risethreshold': this.down('[name="haproxy.healthcheck.risethreshold"]').getValue(),
                            'template': this.down('[name="haproxy.template"]').getValue()
                        };

                    values.backends = this.down('#backends').getValue();
                    if (values.port) {
                        record.set(values);
                        if (mode === 'add' && !record.store) {
                            hp.store.add(record);
                        }
                    }
                }
            },
            updateRecord: function(name, value) {
                if (this.isRecordLoading) return;
                var isValid = this.validateRecord();
                if (isValid === true) {
                    var hp = this.up('haproxysettings'),
                        record = this.getRecord(),
                        mode = hp.mode;
                    if (mode === 'edit') {
                        var grid = hp.down('#grid');
                        record.set(name, value);
                        if (record.store === undefined) {
                            grid.getStore().add(record);
                            this.down('#backends').setValue();
                            this.down('#options').show();
                            grid.setSelectedRecord(record);
                        }
                    }
                } else if (Ext.isString(isValid)){
                    Scalr.message.Error(isValid);
                }

            },
            validateRecord: function(){
                var portField = this.down('[name="haproxy.port"]'),
                    res = true;
                if (this.up('haproxysettings').mode === 'edit') {
                    if (portField.validate()) {
                        var r = this.up('haproxysettings').store.query('port', portField.getValue(), false, false, true);
                        if (r.length > 0 && r.first() !== this.getRecord()) {
                            res = 'Port ' + portField.getValue() + ' is already used by another proxy.';
                        }
                    } else {
                        res = false;
                    }
                } else {
                    if (Ext.String.trim(portField.getValue()) || this.down('#backends').hasNonEmptyItems()) {
                        res = portField.validate() || {comp: portField};
                        if (res === true) {
                            res = this.down('#backends').validate();
                        }
                    }
                }
                return res;
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
                    name: 'haproxy.port',
                    fieldLabel: 'Port',
                    vtype: 'num',
                    validator: function(value){
                        return value*1>0 || 'Value is invalid.';
                    },
                    labelWidth: 35,
                    width: 95,
                    listeners: {
                        change: {
                            fn: function(comp, value){
                                this.up('#form').updateRecord('port', value);
                            },
                            buffer: 300
                        }
                    }
                },{
                    xtype: 'textfield',
                    name: 'haproxy.description',
                    emptyText: 'Description (optional)',
                    flex: 1,
                    margin: '0 0 0 10',
                    maxWidth: 660,
                    listeners: {
                        change: function(comp, value){
                            this.up('#form').updateRecord('description', value);
                        }
                    }
                }]
            },{
                xtype: 'container',
                itemId: 'options',
                hidden: true,
                items: [{
                    xtype: 'container',
                    cls: 'x-container-fieldset',
                    padding: '0 24',
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
                            html: '<div class="x-column-header-inner"><span class="x-column-header-text">Backends</span></div>',
                            cls: 'x-column-header x-column-header-first',
                            flex: 1
                        }]
                    },{
                        xtype: 'container',
                        cls: 'x-not-a-grid-body',
                        items: {
                            xtype: 'container',
                            itemId: 'backends',
                            cls: 'x-not-a-grid-view',
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
                                var value = [];
                                this.items.each(function(item){
                                    var data = {},
                                        network,
                                        type = item.down('[name="haproxy.type"]').getValue();
                                    data[type] = item.down('[name="haproxy.' + type + '"]').getValue();
                                    data['port'] = item.down('[name="haproxy.port"]').getValue();
                                    if (data[type] && data['port']) {
                                        data['backup'] = item.down('[name="haproxy.backup"]').getValue() ? '1' : '0';
                                        data['down'] = item.down('[name="haproxy.down"]').getValue() ? '1' : '0';
                                        // OLD WAY USE farm_role_id
                                        if (type === 'farm_role_id') {
                                            network = item.down('[name="haproxy.network"]').getValue();
                                            if (network) {
                                                data['network'] = network;
                                            }
                                        }

                                        // NEW WAY USE farm_role_alias
                                        if (type === 'farm_role_alias') {
                                            network = item.down('[name="haproxy.network"]').getValue();
                                            if (network) {
                                                data['network'] = network;
                                            }
                                        }

                                        value.push(data);
                                    }
                                });
                                return value;
                            },

                            validate: function(silent){
                                var result = true,
                                    field;
                                this.items.each(function(item){
                                    var type = item.down('[name="haproxy.type"]').getValue();
                                    field = item.down('[name="haproxy.' + type + '"]');
                                    result = field.validate() || {comp: field};
                                    if (result === true) {
                                        field = item.down('[name="haproxy.port"]');
                                        result = field[silent ? 'isValid' : 'validate']() || {comp: field};
                                    }
                                    return result === true;
                                });
                                return result;
                            },

                            hasNonEmptyItems: function(){
                                var result = false;
                                this.items.each(function(item){
                                    result = !!item.down('[name="haproxy.' + item.down('[name="haproxy.type"]').getValue() + '"]').getValue();
                                    return !result;
                                });
                                return result;
                            },

                            addItem: function(data) {
                                var item,
                                    roles = this.up('#haproxy').roles;
                                data = data || {};
                                item = this.add({
                                    xtype: 'container',
                                    layout: 'anchor',
                                    cls: 'x-item',
                                    //margin: '0 0 10 0',
                                    items: [{
                                        xtype: 'fieldcontainer',
                                        layout: 'hbox',
                                        items:[{
                                            xtype: 'buttongroupfield',
                                            name: 'haproxy.type',
                                            defaults: {
                                                width: 50,
                                                style: 'padding-left:0;padding-right:0'
                                            },
                                            items: [{
                                                value: 'host',
                                                text: 'Host'
                                            },{
                                                value: 'farm_role_alias',
                                                text: 'Role',
                                                disabled: !roles.length,
                                                tooltip: !roles.length ? 'No roles available' : ''
                                            }],
                                            margin: '0 10 0 3',
                                            listeners: {
                                                change: function(comp, value) {
                                                    var ct = comp.up('container');
                                                    ct.down('[name="haproxy.host"]').setVisible(value === 'host');
                                                    ct.down('[name="haproxy.farm_role_alias"]').setVisible(value === 'farm_role_alias');
                                                    ct.up().down('#network').setVisible(value === 'farm_role_alias');
                                                }
                                            }
                                        },{
                                            xtype: 'combo',
                                            name: 'haproxy.farm_role_alias',
                                            emptyText: 'Select role',
                                            store: {
                                                fields: [ 'id', 'alias', 'name' ],
                                                proxy: 'object',
                                                data: roles
                                            },
                                            valueField: 'alias',
                                            displayField: 'name',
                                            editable: false,
                                            allowBlank: false,
                                            queryMode: 'local',
                                            flex: 1,
                                            hidden: true
                                        },{
                                            xtype: 'textfield',
                                            name: 'haproxy.host',
                                            emptyText: 'IP or hostname',
                                            allowBlank: false,
                                            flex: 1
                                        },{
                                            xtype: 'textfield',
                                            name: 'haproxy.port',
                                            emptyText: 'port',
                                            vtype: 'num',
                                            allowBlank: false,
                                            fieldLabel: ':',
                                            labelSeparator: '',
                                            labelWidth: 4,
                                            margin: '0 4',
                                            width: 65
                                        },{
                                            xtype: 'buttonfield',
                                            iconCls: 'x-btn-icon-backup',
                                            tooltip: 'Backup',
                                            margin: '0 0 0 4',
                                            padding: 2,
                                            minWidth: 32,
                                            name: 'haproxy.backup',
                                            inputValue: 1,
                                            enableToggle: true,
                                            submitValue: false
                                        },{
                                            xtype: 'buttonfield',
                                            iconCls: 'x-btn-icon-down',
                                            tooltip: 'Down',
                                            margin: '0 0 0 4',
                                            padding: 2,
                                            minWidth: 32,
                                            name: 'haproxy.down',
                                            inputValue: 1,
                                            enableToggle: true,
                                            submitValue: false
                                        },{
                                            xtype: 'button',
                                            itemId: 'delete',
                                            ui: '',
                                             minWidth: 32,
                                            iconCls: 'x-grid-icon x-grid-icon-delete',
                                            margin: '4 0 0 4',
                                            handler: function() {
                                                var item = this.up().up();
                                                item.ownerCt.remove(item);
                                            }
                                        }]
                                    },{
                                        xtype: 'container',
                                        itemId: 'network',
                                        hidden: true,
                                        padding: '0 0 0 60',
                                        layout: {
                                            type: 'hbox',
                                            align: 'middle'
                                        },
                                        items: [{
                                            xtype: 'label',
                                            text: 'Use'
                                        },{
                                            xtype: 'combo',
                                            name: 'haproxy.network',
                                            editable: false,
                                            width: 100,
                                            margin: '0 6 0 28',
                                            value: '',
                                            store: [['','Auto'],['public','Public'],['private','Private']],
                                            /*plugins: {
                                                ptype: 'fieldicons',
                                                position: 'outer',
                                                icons: [{id: 'szrversion', tooltipData: {version: '2.11.6'}}]
                                            }*/
                                        },{
                                            xtype: 'label',
                                            text: 'upstream IPs'
                                        }]
                                    }]
                                });

                                item.setFieldValues({
                                    'haproxy.type': data.farm_role_alias !== undefined ? 'farm_role_alias' : 'host',
                                    'haproxy.farm_role_alias': data.farm_role_alias,
                                    'haproxy.host': data.host,
                                    'haproxy.port': data.port || this.up('#form').down('[name="haproxy.port"]').getValue() || 80,
                                    'haproxy.backup': data.backup,
                                    'haproxy.down': data.down,
                                    'haproxy.network': data.network || '',
                                });

                                var farmRoleAliasField = item.down('[name="haproxy.farm_role_alias"]');
                                if (data.farm_role_alias !== undefined && !farmRoleAliasField.findRecordByValue(data.farm_role_alias)) {
                                    farmRoleAliasField.markInvalid('Selected Farm role not exists');
                                }

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
                        text: 'Add backend',
                        handler: function() {
                            this.up('#form').down('#backends').addItem();
                        }
                    }]
                },{
                    xtype: 'fieldset',
                    title: 'Health check',
                    layout: 'hbox',
                    items: [{
                        xtype: 'numberfield',
                        name: 'haproxy.healthcheck.interval',
                        allowBlank: false,
                        fieldLabel: 'Interval ',
                        flex: 1,
                        labelWidth: 65,
                        minWidth: 100,
                        maxWidth: 120,
                        minValue: 5,
                        maxValue: 600,
                        vtype: 'num',
                        hideTrigger:true
                    },{
                        xtype: 'displayfield',
                        margin: '0 0 0 3',
                        value: 'sec'
                    },{
                        xtype: 'displayinfofield',
                        margin: '0 30 0 5',
                        info:   'The approximate interval (in seconds) between health checks of an individual instance.<br />The default is 30 seconds and a valid interval must be between 5 seconds and 600 seconds.' +
                                'Also, the interval value must be greater than the Timeout value'
                    },{
                        xtype: 'numberfield',
                        name: 'haproxy.healthcheck.fallthreshold',
                        allowBlank: false,
                        fieldLabel: 'Fall thld',
                        flex: 1,
                        labelWidth: 70,
                        minWidth: 100,
                        maxWidth: 150,
                        minValue: 2,
                        maxValue: 10,
                        vtype: 'num',
                        hideTrigger:true
                    },{
                        xtype: 'displayinfofield',
                        margin: '0 30 0 5',
                        info:   'The number of consecutive health probe failures that move the instance to the unhealthy state.<br />The default is 5 and a valid value lies between 2 and 10.'
                    },{
                        xtype: 'numberfield',
                        name: 'haproxy.healthcheck.risethreshold',
                        allowBlank: false,
                        fieldLabel: 'Rise thld',
                        flex: 1,
                        labelWidth: 70,
                        minWidth: 100,
                        maxWidth: 150,
                        minValue: 2,
                        maxValue: 10,
                        vtype: 'num',
                        hideTrigger:true
                    },{
                        xtype: 'displayinfofield',
                        margin: '0 0 0 5',
                        info:   'The number of consecutive health probe successes required before moving the instance to the Healthy state.<br />The default is 3 and a valid value lies between 2 and 10.'
                    }]
                },{
                    xtype: 'fieldset',
                    title: 'Advanced settings',
                    cls: 'x-fieldset-separator-top-bottom',
                    collapsible: true,
                    collapsed: true,
                    layout: 'anchor',
                    items: [{
                        xtype: 'textarea',
                        name: 'haproxy.template',
                        fieldLabel: 'Listen section template',
                        labelAlign: 'top',
                        anchor: '100%',
                        height: 200,
                        maxWidth: 760
                    }]
                }]
            }]
        }, this.formConfig);
    }
});
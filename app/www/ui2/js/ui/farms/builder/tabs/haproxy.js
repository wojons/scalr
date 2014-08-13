Scalr.regPage('Scalr.ui.farms.builder.tabs.haproxy', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'HAProxy',
		itemId: 'haproxy',

        layout: 'fit',

        cls: 'scalr-ui-farmbuilder-roleedit-tab',
        
        /*settings: {
            'haproxy.proxies': undefined
        },*/
        
		isEnabled: function (record) {
			return record.get('behaviors').match("haproxy");
		},

		showTab: function (record) {
			var me = this,
                settings = record.get('settings', true),
                hp = me.down('haproxysettings');

            me.roles = [];
			moduleTabParams.farmRolesStore.each(function(r){
				if (r != record) {
                    var location = r.get('cloud_location');
					me.roles.push({id: r.get('farm_role_id'), name: r.get('alias') + (location ? ' (' + location + ')' : '')});
                }
			});
            hp.setValue({
                'haproxy.proxies': Ext.decode(settings['haproxy.proxies'])
            });
		},

		hideTab: function (record) {
			var settings = record.get('settings');

            Ext.apply(settings, this.down('haproxysettings').getValue());
            settings['haproxy.proxies'] = Ext.encode(settings['haproxy.proxies']);
			record.set('settings', settings);
		},

		items: [{
            xtype: 'haproxysettings',
            formConfig: {
                overflowY: 'auto',
                overflowX: 'hidden',
                hidden: true
            }
		}]
	});
});

Ext.define('Scalr.ui.HaproxySettingsField', {
	extend: 'Ext.container.Container',
	alias: 'widget.haproxysettings',
    
    mode: 'edit',
    
    initComponent: function(){
        this.store = Ext.create('Ext.data.Store', {
            fields: [{name: 'port', type: 'int'}, 'description', 'backends', 'healthcheck.interval', 'healthcheck.fallthreshold', 'healthcheck.risethreshold'],
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
        if (this.mode === 'edit') {
            var selModel = this.down('grid').getSelectionModel();
            selModel.setLastFocused(null);
            selModel.deselectAll();
        }
        return {
            'haproxy.proxies': list
        };
    },

    setValue: function(value){
        this.store.loadData(value['haproxy.proxies'] || []);
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
            minWidth: 360,
            padding: '12 0 0',
            flex: .7,
            features: {
                ftype: 'addbutton',
                text: 'Add proxy',
                handler: function(view) {
                    var grid = view.up('grid'),
                        selModel = grid.getSelectionModel(),
                        form = grid.up('haproxysettings').down('#form');
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
                        form = me.up('haproxysettings').down('#form');
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
                var mode = this.up('haproxysettings').mode;
                this.isLoading = true;
                this.saveRecord();
                this._record = record;
                this.setFieldValues({
                    'haproxy.port': record.get('port') || '',
                    'haproxy.description': record.get('description'),
                    'haproxy.healthcheck.interval': record.get('healthcheck.interval') || 30,
                    'haproxy.healthcheck.fallthreshold': record.get('healthcheck.fallthreshold') || 5,
                    'haproxy.healthcheck.risethreshold': record.get('healthcheck.risethreshold') || 3

                });
                this.down('#backends').setValue(record.get('backends'));
                this.down('#options').setVisible(mode === 'edit' ? record.store !== undefined : true);
                if (mode === 'edit') {
                    this.show();
                    this.down('[name="haproxy.port"]').focus();
                }
                this.isLoading = false;
            },
            saveRecord: function(removeEmpty){
                if (this._record !== undefined) {
                    var hp = this.up('haproxysettings'),
                        mode = hp.mode,
                        values = {
                            'port': this.down('[name="haproxy.port"]').getValue(),
                            'description': this.down('[name="haproxy.description"]').getValue(),
                            'healthcheck.interval': this.down('[name="haproxy.healthcheck.interval"]').getValue(),
                            'healthcheck.fallthreshold': this.down('[name="haproxy.healthcheck.fallthreshold"]').getValue(),
                            'healthcheck.risethreshold': this.down('[name="haproxy.healthcheck.risethreshold"]').getValue()
                        };
                    values.backends = this.down('#backends').getValue();
                    if (values.port) {
                        this._record.set(values);
                        if (mode === 'add') {
                            if (!this._record.store) {
                                hp.store.add(this._record);
                            }
                            
                        }
                    } else if (removeEmpty && this._record.store){
                        this._record.store.remove(this._record);
                    }
                    
                }
            },
            updateRecord: function(name, value) {
                if (this.isLoading) return;
                var isValid = this.validateRecord();
                if (isValid === true) {
                    var hp = this.up('haproxysettings'),
                        mode = hp.mode;
                    if (mode === 'edit') {
                        var grid = hp.down('#grid');
                        this._record.set(name, value);
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
            validateRecord: function(){
                var portField = this.down('[name="haproxy.port"]'),
                    res = true;
                if (this.up('haproxysettings').mode === 'edit') {
                    if (portField.validate()) {
                        var r = this.up('haproxysettings').store.query('port', portField.getValue(), false, false, true);
                        if (r.length > 0 && r.first() !== this._record) {
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
                    name: 'haproxy.port',
                    fieldLabel: 'Port',
                    maskRe: new RegExp('[0123456789]', 'i'),
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
                            html: '<div class="x-column-header-inner"><span class="x-column-header-text">Backends</span></div>',
                            cls: 'x-column-header x-column-header-first',
                            flex: 1
                        },{
                            xtype: 'component',
                            html: '<div class="x-column-header-inner"><span class="x-column-header-text">Flags</span></div>',
                            width: 76,
                            cls: 'x-column-header'
                        },{
                            xtype: 'component',
                            cls: 'x-column-header x-column-header-last',
                            width: 36
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
                                        type = item.down('[name="haproxy.type"]').getValue();
                                    data[type] = item.down('[name="haproxy.' + type + '"]').getValue();
                                    data['port'] = item.down('[name="haproxy.port"]').getValue();
                                    if (data[type] && data['port']) {
                                        data['backup'] = item.down('[name="haproxy.backup"]').getValue() ? '1' : '0';
                                        data['down'] = item.down('[name="haproxy.down"]').getValue() ? '1' : '0';
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
                                    layout: 'hbox',
                                    cls: 'x-item',
                                    items:[{
                                        xtype: 'buttongroupfield',
                                        name: 'haproxy.type',
                                        defaults: {
                                            width: 50
                                        },
                                        items: [{
                                            value: 'host',
                                            text: 'Host'
                                        },{
                                            value: 'farm_role_id',
                                            text: 'Role',
                                            disabled: !roles.length,
                                            tooltip: !roles.length ? 'No roles available' : ''
                                        }],
                                        margin: '0 10 0 3',
                                        listeners: {
                                            change: function(comp, value) {
                                                var ct = comp.up('container');
                                                ct.down('[name="haproxy.host"]').setVisible(value === 'host');
                                                ct.down('[name="haproxy.farm_role_id"]').setVisible(value === 'farm_role_id');
                                            }
                                        }
                                    },{
                                        xtype: 'combo',
                                        name: 'haproxy.farm_role_id',
                                        emptyText: 'Select role',
                                        store: {
                                            fields: [ 'id', 'name' ],
                                            proxy: 'object',
                                            data: roles
                                        },
                                        valueField: 'id',
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
                                        maskRe: new RegExp('[0123456789]', 'i'),
                                        allowBlank: false,
                                        fieldLabel: ':',
                                        labelSeparator: '',
                                        labelWidth: 4,
                                        margin: '0 4',
                                        width: 80
                                    },{
                                        xtype: 'buttonfield',
                                        ui: 'flag',
                                        cls: 'x-btn-flag-backup',
                                        tooltip: 'Backup',
                                        margin: '0 0 0 18',
                                        name: 'haproxy.backup',
                                        inputValue: 1,
                                        enableToggle: true,
                                        submitValue: false
                                    },{
                                        xtype: 'buttonfield',
                                        ui: 'flag',
                                        cls: 'x-btn-flag-down',
                                        tooltip: 'Down',
                                        margin: '0 0 0 6',
                                        name: 'haproxy.down',
                                        inputValue: 1,
                                        enableToggle: true,
                                        submitValue: false
                                    },{
                                        xtype: 'button',
                                        itemId: 'delete',
                                        ui: 'action',
                                        cls: 'x-btn-action-delete',
                                        margin: '0 0 0 18',
                                        handler: function() {
                                            var item = this.up('container');
                                            item.ownerCt.remove(item);
                                        }
                                    }]
                                });

                                item.setFieldValues({
                                    'haproxy.type': data.farm_role_id !== undefined && item.down('[name="haproxy.farm_role_id"]').findRecordByValue(data.farm_role_id) ? 'farm_role_id' : 'host',
                                    'haproxy.farm_role_id': data.farm_role_id,
                                    'haproxy.host': data.host,
                                    'haproxy.port': data.port || this.up('#form').down('[name="haproxy.port"]').getValue() || 80,
                                    'haproxy.backup': data.backup,
                                    'haproxy.down': data.down
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
                    xtype: 'fieldset',
                    title: 'Health check',
                    cls: 'x-fieldset-separator-none',
                    layout: 'hbox',
                    items: [{
                        xtype: 'textfield',
                        name: 'haproxy.healthcheck.interval',
                        allowBlank: false,
                        fieldLabel: 'Interval ',
                        flex: 1,
                        labelWidth: 60,
                        minWidth: 100,
                        maxWidth: 120
                    },{
                        xtype: 'displayfield',
                        margin: '0 0 0 3',
                        value: 'sec'
                    },{
                        xtype: 'displayinfofield',
                        margin: '0 40 0 5',
                        info:   'The approximate interval (in seconds) between health checks of an individual instance.<br />The default is 30 seconds and a valid interval must be between 5 seconds and 600 seconds.' +
                                'Also, the interval value must be greater than the Timeout value'
                    },{
                        xtype: 'textfield',
                        name: 'haproxy.healthcheck.fallthreshold',
                        allowBlank: false,
                        fieldLabel: 'Fall threshold',
                        flex: 1,
                        labelWidth: 90,
                        minWidth: 110,
                        maxWidth: 150
                    },{
                        xtype: 'displayinfofield',
                        margin: '0 40 0 5',
                        info:   'The number of consecutive health probe failures that move the instance to the unhealthy state.<br />The default is 5 and a valid value lies between 2 and 10.'
                    },{
                        xtype: 'textfield',
                        name: 'haproxy.healthcheck.risethreshold',
                        allowBlank: false,
                        fieldLabel: 'Rise threshold',
                        flex: 1,
                        labelWidth: 90,
                        minWidth: 110,
                        maxWidth: 150
                    },{
                        xtype: 'displayinfofield',
                        margin: '0 0 0 5',
                        info:   'The number of consecutive health probe successes required before moving the instance to the Healthy state.<br />The default is 3 and a valid value lies between 2 and 10.'
                    }]
                }]
            }]
        }, this.formConfig)
    }
});
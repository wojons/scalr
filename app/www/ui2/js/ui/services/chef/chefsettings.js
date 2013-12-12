Ext.define('Scalr.ui.ChefSettingsField', {
	extend: 'Ext.container.Container',
	alias: 'widget.chefsettings',

    isValid: function(returnErrorField) {
        var field, result = true;
        if (this.down('[name="chef.bootstrap"]').getValue()) {
            if (this.down('#chefMode').getValue() === 'solo') {
                field = this.down('[name="chef.cookbook_url"]');
                result = field.validate();
                if (!result && returnErrorField) {
                    result = {comp:field};
                }
            } else {
                field = this.down('[name="chef.server_id"]');
                result = field.validate();
                if (!result && returnErrorField) {
                    result = {comp:field};
                }
                if (result) {
                    if (this.down('#confType').getValue() === 'role') {
                        field = this.down('[name="chef.role_name"]');
                        result = field.validate();
                        if (!result && returnErrorField) {
                            result = {comp:field};
                        }
                    }
                }
            }
        }
        return result;
    },

    clearInvalid: function() {
        var fields = this.query('[isFormField]');
        Ext.Array.each(fields, function(field){
            field.clearInvalid();
        });
    },

    setValue: function(value, callback) {
        var me = this,
            setValues = function(success) {
                me.reset();
                me.down('#chefMode').setValue(value['chef.cookbook_url'] ? 'solo' : 'server');
                me.down('[name="chef.cookbook_url"]').setValue(value['chef.cookbook_url']);
                me.down('[name="chef.cookbook_url_type"]').setValue(value['chef.cookbook_url_type'] || 'http');
                me.down('[name="chef.relative_path"]').setValue(value['chef.relative_path']);
                me.down('[name="chef.ssh_private_key"]').setValue(value['chef.ssh_private_key']);
                me.down('[name="chef.bootstrap"]').setValue(value['chef.bootstrap']);
                me.down('[name="chef.server_id"]').setValue(value['chef.server_id']);
                me.down('[name="chef.environment"]').setValue(value['chef.environment']);
                me.down('[name="chef.role_name"]').setValue(value['chef.role_name']);
                me.down('[name="chef.runlist"]').setValue(value['chef.runlist']);
                me.down('[name="chef.node_name_tpl"]').setValue(value['chef.node_name_tpl']);
                me.down('[name="chef.daemonize"]').setValue(value['chef.daemonize']);
                me.down('[name="chef.attributes"]').setValue(value['chef.attributes']);
                me.down('#confType').setValue(value['chef.role_name'] ? 'role' : 'runlist');
                if (callback !== undefined) {
                    callback(success);
                }
            };

        if (value['chef.server_id']) {//preload servers if server_id is set
            me.down('[name="chef.server_id"]').store.load(function(records, operation, success){
                setValues(success);
            });
        } else {
            setValues(true);
        }
    },

    getValue: function() {
        var value = {};
        value['chef.bootstrap'] = this.down('[name="chef.bootstrap"]').getValue() ? 1 : '';
        if (this.down('#chefMode').getValue() === 'solo') {
            value['chef.cookbook_url'] = this.down('[name="chef.cookbook_url"]').getValue();
            value['chef.runlist'] = this.down('[name="chef.runlist"]').getValue();
            value['chef.cookbook_url_type'] = this.down('[name="chef.cookbook_url_type"]').getValue();
            if (value['chef.cookbook_url_type'] === 'git') {
                value['chef.relative_path'] = this.down('[name="chef.relative_path"]').getValue();
                value['chef.ssh_private_key'] = this.down('[name="chef.ssh_private_key"]').getValue();
            }
        } else {
            value['chef.server_id'] = this.down('[name="chef.server_id"]').getValue();
            value['chef.environment'] = this.down('[name="chef.environment"]').getValue();
            if (this.down('#confType').getValue() === 'role') {
                value['chef.role_name'] = this.down('[name="chef.role_name"]').getValue();
            } else {
                value['chef.runlist'] = this.down('[name="chef.runlist"]').getValue();
            }
            value['chef.node_name_tpl'] = this.down('[name="chef.node_name_tpl"]').getValue();
            value['chef.daemonize'] = this.down('[name="chef.daemonize"]').getValue() ? 1 : 0;
        }
        value['chef.attributes'] = this.down('[name="chef.attributes"]').getValue();
        return value;
    },

    reset: function() {
        this.setFieldValues({
            'chefMode': null,
            'confType': null,
            'chef.bootstrap': 0,
            'chef.cookbook_url': '',
            'chef.cookbook_url_type': '',
            'chef.relative_path': '',
            'chef.ssh_private_key': '',
            'chef.runlist': '',
            'chef.server_id': '',
            'chef.environment': '',
            'chef.role_name': '',
            'chef.attributes': '',
            'chef.node_name_tpl': '',
            'chef.daemonize': 0
        });
    },

    items: [{
        xtype: 'fieldset',
        title: 'Use Chef to bootstrap this role',
        checkboxToggle: true,
        toggleOnTitleClick: true,
        collapsible: true,
        collapsed: true,
        checkboxName: 'chef.bootstrap',
        defaults: {
            anchor: '100%',
            maxWidth: 570
        },
        items: [{
            xtype: 'buttongroupfield',
            name: 'chefMode',
            itemId: 'chefMode',
            margin: '0 0 28 0',
            defaults: {
                width: 120,
                height: 32
            },
            value: 'server',
            items: [{
                text: 'Chef server',
                value: 'server'
            },{
                text: 'Chef solo',
                value: 'solo'
            }],
            listeners: {
                change: function(comp, value) {
                    var serverCt = comp.next('#server');
                    serverCt.setVisible(value === 'server');
                    comp.next('#solo').setVisible(value === 'solo');
                    if (value === 'server') {
                        var envValue = serverCt.down('[name="chef.environment"]').getValue();
                        comp.next('#attributes').setVisible(!!envValue);
                        comp.next('#nodeName').setVisible(!!envValue);
                        comp.next('[name="chef.daemonize"]').setVisible(!!envValue);
                        comp.next('#runlist').setVisible(!!envValue && serverCt.down('#confType').getValue() === 'runlist');
                        serverCt.down('#configuration').setVisible(!!envValue);
                    } else {
                        comp.next('#attributes').show();
                        comp.next('#runlist').show();
                        comp.next('#nodeName').hide();
                        comp.next('[name="chef.daemonize"]').hide();
                    }
                }
            }
        },{
            xtype: 'container',
            itemId: 'server',
            layout: 'anchor',
            defaults: {
                anchor: '100%',
                labelWidth: 120
            },
            items: [{
                xtype: 'combo',
                name: 'chef.server_id',
                fieldLabel: 'Chef server',
                valueField: 'id',
                displayField: 'url',
                editable: false,
                emptyText: 'Select server',
                value: '',
                allowBlank: false,
                queryCaching: false,
                clearDataBeforeQuery: true,
                store: {
                    fields: [ 'url', 'id' ],
                    proxy: {
                        type: 'cachedrequest',
                        url: '/services/chef/servers/xListServers/'
                    }
                },
                plugins: [{
                    ptype: 'comboaddnew',
                    pluginId: 'comboaddnew',
                    url: '/services/chef/servers/create',
                    disabled: !Scalr.isAllowed('SERVICES_CHEF')
                }],
                listeners: {
                    change: function(comp, value) {
                        var envField = comp.next();
                        envField.setDisabled(!value);
                        envField.store.proxy.params['servId'] = value;
                        envField.reset();
                        envField.setValue(value ? '_default' : '');
                    }
                }
            },{
                xtype: 'combo',
                name: 'chef.environment',
                fieldLabel: 'Chef environment',
                valueField: 'name',
                displayField: 'name',
                editable: false,
                value: '',
                disabled: true,
                queryCaching: false,
                clearDataBeforeQuery: true,
                store: {
                    fields: [ 'name' ],
                    proxy: {
                        type: 'cachedrequest',
                        url: '/services/chef/servers/xListEnvironments/'
                    }
                },
                listeners: {
                    change: function(comp, value) {
                        var ct = comp.up('chefsettings'),
                            rolesField = ct.down('[name="chef.role_name"]');
                        if (ct.down('#chefMode').getValue() === 'server') {
                            ct.down('#attributes').setVisible(!!value);
                            ct.down('#nodeName').setVisible(!!value);
                            ct.down('[name="chef.daemonize"]').setVisible(!!value);
                            ct.down('#runlist').setVisible(value && ct.down('#confType').getValue() == 'runlist');
                        }
                        ct.down('#configuration').setVisible(!!value);
                        rolesField.reset();
                        rolesField.store.proxy.params = {
                            servId: ct.down('[name="chef.server_id"]').getValue(),
                            chefEnv: value
                        };
                    }
                }
            },{
                xtype: 'container',
                itemId: 'configuration',
                layout: 'hbox',
                items: [{
                    xtype: 'buttongroupfield',
                    name: 'confType',
                    itemId: 'confType',
                    fieldLabel: 'Configuration',
                    labelWidth: 120,
                    width: 340,
                    defaults: {
                        width: 100
                    },
                    items: [{
                        text: 'Role',
                        value: 'role'
                    },{
                        text: 'Runlist',
                        value: 'runlist'
                    }],
                    listeners: {
                        change: function(comp, value) {
                            var ct = comp.up('chefsettings');
                            ct.down('[name="chef.role_name"]').setVisible(value === 'role');
                            if (ct.down('#chefMode').getValue() === 'server') {
                                ct.down('[name="chef.runlist"]').setVisible(ct.down('[name="chef.environment"]').getValue() && value === 'runlist');
                            }
                            ct.down('#configureRunlist').setVisible(value === 'runlist');
                        }
                    }
                },{
                    xtype: 'combo',
                    flex: 1,
                    name: 'chef.role_name',
                    valueField: 'name',
                    displayField: 'name',
                    editable: false,
                    allowBlank: false,
                    queryCaching: false,
                    clearDataBeforeQuery: true,
                    store: {
                        fields: [ 'name', 'chef_type' ],
                        proxy: {
                            type: 'cachedrequest',
                            url: '/services/chef/xListRoles'
                        }
                    }
                },{
                    xtype: 'button',
                    itemId: 'configureRunlist',
                    hidden: true,
                    flex: 1,
                    text: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-configure" />&nbsp;Configure runlist',
                    handler: function() {
                        var ct = this.up('chefsettings'),
                            chefServerId = ct.down('[name="chef.server_id"]').getValue(),
                            chefEnvironment = ct.down('[name="chef.environment"]').getValue(),
                            runlistField = ct.down('[name="chef.runlist"]'),
                            runlist = Ext.decode(runlistField.getValue() || '[]', true),
                            runlistGridData = [], runlistStore;
                        if (runlist === null) {
                            var msg = 'JSON is invalid';
                            runlistField.markInvalid(msg);
                            Scalr.message.InfoTip(msg, runlistField.inputEl, {anchor: 'bottom'});
                            return;
                        }
                        Ext.Array.each(runlist, function(item) {
                            var itemData = {}, name;
                            if (item.indexOf('role[') === 0) {
                                item = item.replace(/^role\[/i, '').replace(/\]$/i, '');
                                Ext.apply(itemData, {
                                    id: item,
                                    name: item,
                                    chef_type: true
                                });
                            } else if (item.indexOf('recipe[') === 0) {
                                item = item.replace(/^recipe\[/i, '').replace(/\]$/i, '');
                                item = item.split('::');
                                Ext.apply(itemData, {
                                    id: item.join(''),
                                    cookbook: item[0],
                                    name: item[1]
                                });
                            } else {
                                Ext.apply(itemData, {id: item, name: item});
                            }
                            runlistGridData.push(itemData);
                        });
                        runlistStore = Ext.create('store.store', {
                            fields: ['id', 'name', 'cookbook', 'chef_type'],
                            proxy: 'object',
                            data: runlistGridData
                        });
						Scalr.Confirm({
                            formWidth: 850,
							form: [{
                                xtype: 'fieldset',
                                title: 'Configure Chef runlist<span class="x-fieldset-header-description">Drag and drop roles and recipes from left to the right to configure runlist</span>',
                                cls: 'x-fieldset-separator-none',
                                height: 500,
                                layout: {
                                    type: 'hbox',
                                    align: 'stretch'
                                },
                                defaults: {
                                    flex: 1
                                },
                                items: [{
                                    xtype: 'container',
                                    cls: 'x-container-shadow',
                                    layout: 'accordion',
                                    items: [{
                                        xtype: 'grid',
                                        itemId: 'rolesGrid',
                                        title: 'Available roles',
                                        hideHeaders: true,
                                        selModel: {
                                            selType: 'rowmodel',
                                            mode: 'MULTI'
                                        },
                                        plugins: {
                                            ptype: 'gridstore',
                                            loadMask: true
                                        },
                                        viewConfig: {
                                            deferEmptyText: false,
                                            focusedItemCls: '',
                                            //emptyText: 'No items found',
                                            preserveScrollOnRefresh: true,
                                            plugins: {
                                                ptype: 'gridviewdragdrop',
                                                dragGroup: 'runList'
                                            }
                                        },
                                        store: {
                                            fields: [ 'name', 'chef_type', {name: 'id', convert: function(v, record){return record.data.name}}],
                                            sorters: [{
                                                property: 'name',
                                                transform: function(value){
                                                    return value.toLowerCase();
                                                }
                                            }],
                                            proxy: {
                                                type: 'cachedrequest',
                                                url: '/services/chef/xListRoles',
                                                params: {servId: chefServerId},
                                                processBox: false
                                            }
                                        },
                                        columns: [{
                                            flex: 1,
                                            dataIndex: 'name'
                                        }],
                                        listeners: {
                                            afterrender: function() {
                                                var store = this.store;
                                                store.load({callback: function(records, operation, success) {
                                                    if (success) {
                                                        (runlistStore.snapshot || runlistStore.data).each(function(rec){
                                                            var record = store.getById(rec.get('id'));
                                                            if (record) {
                                                                store.remove(record);
                                                            }
                                                        });
                                                    }
                                                }});
                                            }
                                        }
                                    },{
                                        xtype: 'grid',
                                        itemId: 'recipesGrid',
                                        title: 'Available recipes',
                                        hideHeaders: true,
                                        selModel: {
                                            selType: 'rowmodel',
                                            mode: 'MULTI'
                                        },
                                        plugins: {
                                            ptype: 'gridstore',
                                            loadMask: true
                                        },
                                        viewConfig: {
                                            deferEmptyText: false,
                                            focusedItemCls: '',
                                            //emptyText: 'No items found',
                                            preserveScrollOnRefresh: true,
                                            plugins: {
                                                ptype: 'gridviewdragdrop',
                                                dragGroup: 'runList'
                                            }
                                        },
                                        store: {
                                            fields: [ 'name', 'cookbook', {name: 'id', convert: function(v, record){return record.data.cookbook+record.data.name}}],
                                            sorters: [{
                                                property: 'cookbook',
                                                transform: function(value){
                                                    return value.toLowerCase();
                                                }
                                            },{
                                                property: 'name',
                                                transform: function(value){
                                                    return value.toLowerCase();
                                                }
                                            }],
                                            proxy: {
                                                type: 'cachedrequest',
                                                url: '/services/chef/xListAllRecipes',
                                                params: {servId: chefServerId, chefEnv: chefEnvironment},
                                                processBox: false
                                            }
                                        },
                                        columns: [{
                                            xtype: 'templatecolumn',
                                            flex: 1,
                                            tpl: '{cookbook}::{name}'
                                        }],
                                        listeners: {
                                            afterrender: function() {
                                                var store = this.store;
                                                store.load({callback: function(records, operation, success) {
                                                    if (success) {
                                                        (runlistStore.snapshot || runlistStore.data).each(function(rec){
                                                            var record = store.getById(rec.get('id'));
                                                            if (record) {
                                                                store.remove(record);
                                                            }
                                                        });
                                                    }
                                                }});
                                            }
                                        }
                                    }]
                                },{
                                    xtype: 'grid',
                                    itemId: 'runList',
                                    cls: 'x-container-shadow x-grid-no-selection',
                                    bodyStyle: 'background:#fff',
                                    margin: '2 2 2 12',
                                    store: runlistStore,
                                    viewConfig: {
                                        deferEmptyText: false,
                                        focusedItemCls: '',
                                        emptyText: '<span style="line-height:300px">Runlist is empty. Drag and drop roles and recipes here.</span>',
                                        plugins: {
                                            ptype: 'gridviewdragdrop',
                                            ddGroup: 'runList'
                                        }
                                    },
                                    columns: [{
                                        xtype: 'templatecolumn',
                                        tpl: '<tpl if="cookbook">recipe[{cookbook}::</tpl><tpl if="chef_type">role[</tpl>{name}<tpl if="cookbook">]</tpl><tpl if="chef_type">]</tpl>',
                                        flex: 1,
                                        text: 'Runlist',
                                        sortable: false,
                                        resizable: false,
                                        border: false,
                                        dataIndex: 'name'
                                    }, {
                                        xtype: 'templatecolumn',
                                        tpl: '<img style="cursor:pointer" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Remove from runlist" src="'+Ext.BLANK_IMAGE_URL+'"/>',
                                        text: '&nbsp;',
                                        width: 35,
                                        sortable: false,
                                        resizable: false,
                                        border: false,
                                        dataIndex: 'id',
                                        align:'center'
                                    }],
                                    listeners: {
                                        itemclick: function (view, record, item, index, e) {
                                            if (e.getTarget('img.x-icon-action-delete')) {
                                                var isRecipe= !!record.get('cookbook'),
                                                    store = this.up('fieldset').down(isRecipe ? '#recipesGrid' : '#rolesGrid').store;
                                                if (store.query('id', record.get('id')).length === 0) {
                                                    store.add(record);
                                                    store.sort();
                                                }

                                                view.store.remove(record);
                                            }
                                        }
                                    }
                                }]
							}],
							ok: 'Save',
							closeOnSuccess: true,
							scope: this,
							success: function (formValues, form) {
                                var runlist = [],
                                    store = form.down('#runList').store;
                                (store.snapshot || store.data).each(function (record) {
                                    if (record.get('cookbook')) {
                                        runlist.push('recipe[' + record.get('cookbook') + '::' + record.get('name') + ']');
                                    } else if (record.get('chef_type')) {
                                        runlist.push('role[' + record.get('name') + ']');
                                    } else {
                                        runlist.push(record.get('name'));
                                    }
                                });
                                runlistField.setValue(runlist.length > 0 ? Ext.encode(runlist) : '');
                                return true;
							}
						});
                    }
                }]
            }]
        },{
            xtype: 'container',
            itemId: 'solo',
            hidden: true,
            layout: 'anchor',
            defaults: {
                anchor: '100%',
                labelWidth: 120
            },
            items: [{
                xtype: 'buttongroupfield',
                name: 'chef.cookbook_url_type',
                fieldLabel: 'URL Type',
                defaults: {
                    width: 70
                },
                items: [{
                    text: 'Http',
                    value: 'http'
                },{
                    text: 'Git',
                    value: 'git'
                }],
                listeners: {
                    change: function(comp, value){
                        comp.next('[name="chef.ssh_private_key"]').setVisible(value === 'git');
                        comp.next('[name="chef.relative_path"]').setVisible(value === 'git');
                    }
                }
            },{
                xtype: 'textfield',
                name: 'chef.cookbook_url',
                fieldLabel: 'Cookbook URL',
                allowBlank: false
            },{
                xtype: 'textfield',
                name: 'chef.relative_path',
                fieldLabel: 'Relative path'
            },{
                xtype: 'textarea',
                name: 'chef.ssh_private_key',
                fieldLabel: 'Private key'
            }]
       },{
            xtype: 'container',
            itemId: 'nodeName',
            layout: 'hbox',
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Node name',
                labelWidth: 120,
                name: 'chef.node_name_tpl',
                emptyText: 'Leave blank to use server system hostname',
                submitEmptyText: false,
                flex: 1
            }, {
                xtype: 'displayinfofield',
                margin: '0 0 0 5',
                value: Scalr.strings['farmbuilder.available_variables.info'] + '<br /><b>For example:</b> %instance_index%.%farm_id%.example.com'
            }]
        },{
            xtype: 'checkbox',
            name: 'chef.daemonize',
            labelWidth: 120,
            fieldLabel: '&nbsp;',
            labelSeparator: '',
            hidden: !Scalr.flags['betaMode'],
            boxLabel: 'Daemonize chef client',
            listeners: {
                beforeshow: function() {
                    return !!Scalr.flags['betaMode'];
                }
            }
        },{
            xtype: 'textarea',
            itemId: 'runlist',
            name: 'chef.runlist',
            fieldLabel: 'Runlist',
            labelAlign: 'top',
            height: 220,
            emptyText: 'Paste your run list in JSON format'
        },{
            xtype: 'textarea',
            itemId: 'attributes',
            name: 'chef.attributes',
            fieldLabel: 'Attributes',
            labelAlign: 'top',
            height: 90,
            emptyText: 'Paste attributes in JSON format'
        }]
    }]

});
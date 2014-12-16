Ext.define('Scalr.ui.ChefSettingsField', {
	extend: 'Ext.container.Container',
	alias: 'widget.chefsettings',

    readOnly: false,
    disableDaemonize: false,

    isValid: function(returnErrorField) {
        var field, result = true;
        if (!this.readOnly && this.down('[name="chef.bootstrap"]').getValue()) {
            if (this.down('#chefMode').getValue() === 'solo') {
                result = this.down('#solo').isValid(returnErrorField);
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
            roleChefSettings = me.roleChefSettings || {},
            farmRoleChefSettings = me.farmRoleChefSettings || {},
            overrideAttributes,
            serverId = value['chef.server_id'],
            defaultEnv = '_default',
            limits = me.limits,
            setValues = function(success) {
                var field;
                me.reset();
                me.down('#chefMode').setValue(value['chef.cookbook_url'] ? 'solo' : 'server');
                me.down('#solo').setValues(value);
                me.down('[name="chef.bootstrap"]').setValue(value['chef.bootstrap']);
                me.down('[name="chef.server_id"]').setValue(serverId);
                me.down('[name="chef.environment"]').setValue(value['chef.environment'] || (serverId ? defaultEnv : null));
                me.down('[name="chef.role_name"]').setValue(value['chef.role_name']);
                me.down('[name="chef.runlist"]').setValue(value['chef.runlist']);
                me.down('[name="chef.node_name_tpl"]').setValue(value['chef.node_name_tpl']);
                field = me.down('[name="chef.daemonize"]');
                field.setValue(value['chef.daemonize']);
                field.setReadOnly(!!me.disableDaemonize || me.readOnly);

                field = me.down('[name="chef.attributes"]');
                if (me.readOnly) {
                    overrideAttributes = farmRoleChefSettings['chef.attributes'];
                    me.down('#overrideAttributes').setValue(!!overrideAttributes);
                    field.setValue(overrideAttributes ? farmRoleChefSettings['chef.attributes'] : roleChefSettings['chef.attributes']);
                    field.setReadOnly(!overrideAttributes);
                } else {
                    field.setValue(value['chef.attributes']);
                }
                me.down('#confType').setValue(value['chef.role_name'] ? 'role' : 'runlist');
                me.down('[name="chef.log_level"]').setValue(farmRoleChefSettings['chef.log_level'] || value['chef.log_level'] || 'auto');
                if (callback !== undefined) {
                    callback(success);
                }
            };
        if (!serverId && limits !== undefined) {
            Ext.Object.each(limits['servers'], function(id, server){
                if (server['default'] == 1) {
                    serverId = id;
                    if (server['environments'].length) {
                        defaultEnv = server['environments'][0];
                    }
                }
            });
        }
        if (serverId) {//preload servers if server_id is set
            me.down('[name="chef.server_id"]').store.load(function(records, operation, success){
                setValues(success);
            });
        } else {
            setValues(true);
        }
    },

    getValue: function() {
        var value = {},
            roleChefSettings = this.roleChefSettings || {},
            logLevel = this.down('[name="chef.log_level"]').getValue(),
            field;
        if (!this.readOnly) {
            value['chef.bootstrap'] = this.down('[name="chef.bootstrap"]').getValue() ? 1 : '';
            if (this.down('#chefMode').getValue() === 'solo') {
                value['chef.runlist'] = this.down('[name="chef.runlist"]').getValue();
                Ext.apply(value, this.down('#solo').getValues());
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
            value['chef.log_level'] = logLevel;
        } else {
            if (logLevel != roleChefSettings['chef.log_level']) {
                value['chef.log_level'] = logLevel;
            }
        }
        field = this.down('[name="chef.attributes"]');
        if (!field.readOnly) {
            value['chef.attributes'] = field.getValue();
        }
        return value;
    },

    reset: function() {
        this.setFieldValues({
            'chefMode': null,
            'confType': null,
            'chef.bootstrap': 0,
            'chef.runlist': '',
            'chef.server_id': '',
            'chef.environment': '',
            'chef.role_name': '',
            'chef.attributes': '',
            'chef.node_name_tpl': '',
            'chef.daemonize': 0,
            'chef.log_level': 'auto'
        });
        this.down('#solo').resetValues();
    },

    setReadOnly: function(readOnly) {
        var fields = this.query('[isFormField]');
        this.suspendLayouts();
        Ext.Array.each(fields, function(field){
            if (!Ext.Array.contains(['overrideAttributes', 'chef.log_level'], field.name)) {
                field.setReadOnly(!!readOnly, false);
            }
        });
        this.down('#configureRunlist').setDisabled(!!readOnly);
        this.down('#overrideAttributes').setVisible(!!readOnly);
        this.down('[name="chef.attributes"]').setFieldLabel(readOnly ? '' : 'Attributes');
        this.resumeLayouts(true);
        this.readOnly = !!readOnly;
    },

    items: [{
        xtype: 'fieldset',
        title: 'Use Chef to bootstrap this role',
        checkboxToggle: true,
        toggleOnTitleClick: true,
        collapsible: true,
        collapsed: true,
        checkboxName: 'chef.bootstrap',
        listeners: {
            beforecollapse: function() {
                if (this.up('chefsettings').readOnly) {
                    this.checkboxCmp.setValue(true);
                    return false;
                }
            }
        },
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
                        comp.next('#logLevel').setVisible(!!envValue);
                        comp.next('#runlist').setVisible(!!envValue && serverCt.down('#confType').getValue() === 'runlist');
                        serverCt.down('#configuration').setVisible(!!envValue);
                    } else {
                        comp.next('#attributes').show();
                        comp.next('#runlist').show();
                        comp.next('#logLevel').show();
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
                xtype: 'chefserveridcombo',
                name: 'chef.server_id',
                listeners: {
                    change: function(comp, value) {
                        var envField = comp.next(),
                            ct = comp.up('chefsettings'),
                            envValue = value ? '_default' : '',
                            envList = null;

                        if (value && ct.limits !== undefined && ct.limits['servers'][value]) {
                            var envs = ct.limits['servers'][value]['environments'] || [];
                            if (envs.length) {
                                envList = Ext.Array.map(envs, function(name){
                                    return {name: name};
                                });
                                envValue = '';
                            }
                        }
                        envField.store.proxy.data = envList;

                        envField.setDisabled(!value);
                        envField.store.proxy.params['servId'] = value;
                        envField.reset();
                        envField.setValue(envValue);
                    }
                }
            },{
                xtype: 'chefenvironmentcombo',
                name: 'chef.environment',
                listeners: {
                    change: function(comp, value) {
                        var ct = comp.up('chefsettings'),
                            rolesField = ct.down('[name="chef.role_name"]');
                        if (ct.down('#chefMode').getValue() === 'server') {
                            ct.down('#attributes').setVisible(!!value);
                            ct.down('#nodeName').setVisible(!!value);
                            ct.down('[name="chef.daemonize"]').setVisible(!!value);
                            ct.down('#logLevel').setVisible(!!value);
                            ct.down('#runlist').setVisible(value && ct.down('#confType').getValue() == 'runlist');
                        }
                        ct.down('#configuration').setVisible(!!value);
                        rolesField.reset();
                        rolesField.store.proxy.params = {
                            servId: ct.down('[name="chef.server_id"]').getValue()
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
                            runlistField = ct.down('[name="chef.runlist"]'),
                            runlist = Ext.decode(runlistField.getValue() || '[]', true);
                        if (runlist === null) {
                            var msg = 'JSON is invalid';
                            runlistField.markInvalid(msg);
                            Scalr.message.InfoTip(msg, runlistField.inputEl, {anchor: 'bottom'});
                            return;
                        }
                        Scalr.ui.ChefRunlistCosnstructor.show({
                            chefServerId: ct.down('[name="chef.server_id"]').getValue(),
                            chefEnvironment: ct.down('[name="chef.environment"]').getValue(),
                            runlist: runlist,
                            success: function (formValues, form) {
                                var runlist = this.getValues();
                                runlistField.setValue(runlist.length > 0 ? Ext.encode(runlist) : '');
                                return true;
                            }
                        });
                    }
                }]
            }]
        },{
            xtype: 'chefsolocontainer',
            itemId: 'solo',
            hidden: true,
            margin: 0
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
                icons: {
                    globalvars: true
                },
                iconsPosition: 'outer',
                flex: 1
            }]
        },{
            xtype: 'combo',
            store: ['auto', 'debug', 'info', 'warn', 'error', 'fatal'],
            editable: false,
            fieldLabel: 'Log level',
            name: 'chef.log_level',
            itemId: 'logLevel',
            labelWidth: 120
        },{
            xtype: 'checkbox',
            name: 'chef.daemonize',
            labelWidth: 120,
            fieldLabel: '&nbsp;',
            labelSeparator: '',
            boxLabel: 'Daemonize chef client',
            questionTooltip: 'Daemonize is not available in case of using Chef runlist in orchestration',
            icons: {
                question: true
            },
            listeners:{
                writeablechange: function(comp, readOnly) {
                    this.toggleIcon('question', readOnly && !this.up('chefsettings').readOnly);
                }
            }

        },{
            xtype: 'textarea',
            itemId: 'runlist',
            name: 'chef.runlist',
            fieldLabel: 'Runlist',
            labelAlign: 'top',
            height: 100,
            emptyText: 'Paste your run list in JSON format',
            icons: {
                globalvars: true
            }
        },{
            xtype: 'checkbox',
            name: 'overrideAttributes',
            itemId: 'overrideAttributes',
            hidden: true,
            boxLabel: 'Override attributes defined in the Role scope <img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qtip="Future attributes changes made on the Role will not affect this Farm Role" />',
            listeners: {
                change: function(comp, value) {
                    var attrField = comp.next('[name="chef.attributes"]'),
                        roleChefSettings = comp.up('chefsettings').roleChefSettings || {};
                    attrField.applyEmptyText(value ? '' : 'Paste attributes in JSON format');
                    attrField.setReadOnly(!value);
                    if (!value) {
                        attrField.setValue(roleChefSettings['chef.attributes'] || '');
                    }
                }
            }
        },{
            xtype: 'textarea',
            itemId: 'attributes',
            name: 'chef.attributes',
            fieldLabel: 'Attributes',
            icons: {
                globalvars: true
            },
            labelAlign: 'top',
            height: 220,
            margin: '6 0 0',
            emptyText: 'Paste attributes in JSON format'
        }]
    }]

});

Ext.define('Scalr.ui.ChefSoloSettings', {
	extend: 'Ext.container.Container',
	alias: 'widget.chefsolocontainer',

    layout: 'anchor',
    defaults: {
        anchor: '100%',
        labelWidth: 120
    },
    isValid: function(returnErrorField) {
        var field, result;
        field = this.down('[name="chef.cookbook_url"]');
        result = field.validate();
        if (!result && returnErrorField) {
            result = {comp:field};
        }
        return result;

    },
    setValues: function(values) {
        this.setFieldValues({
            'chef.cookbook_url': values['chef.cookbook_url'],
            'chef.cookbook_url_type': values['chef.cookbook_url_type'] || 'http',
            'chef.relative_path': values['chef.relative_path'],
            'chef.ssh_private_key': values['chef.ssh_private_key']
        });
    },
    getValues: function() {
        var values = {};
        values['chef.cookbook_url'] = this.down('[name="chef.cookbook_url"]').getValue();
        values['chef.cookbook_url_type'] = this.down('[name="chef.cookbook_url_type"]').getValue();
        if (values['chef.cookbook_url_type'] === 'git') {
            values['chef.relative_path'] = this.down('[name="chef.relative_path"]').getValue();
            values['chef.ssh_private_key'] = this.down('[name="chef.ssh_private_key"]').getValue();
        }
        return values;
    },
    resetValues: function() {
        this.setFieldValues({
            'chef.cookbook_url': '',
            'chef.cookbook_url_type': '',
            'chef.relative_path': '',
            'chef.ssh_private_key': '',
        });
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
        height: 50,
        fieldLabel: 'Private key'
    }]
})

Ext.define('Scalr.ui.ChefServerIdField', {
	extend: 'Ext.form.field.ComboBox',
	alias: 'widget.chefserveridcombo',
    
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
        fields: ['id', 'url', 'username', 'level' ],
        proxy: {
            type: 'cachedrequest',
            url: '/services/chef/servers/xListServers/'
        }
    },

    listConfig: {
        cls: 'x-boundlist-alt',
        tpl:
            '<tpl for=".">' +
                '<div class="x-boundlist-item" style="height: auto; width: auto; max-width: 900px;">' +
                    '<img src="'+Ext.BLANK_IMAGE_URL+'" class="scalr-scope-{level}" data-qtip="{level:capitalize} scope"/>&nbsp; {url}'+
                    '<div style="line-height: 16px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;margin:0 0 0 16px">' +
                        '<span style="font-style: italic;">Username: {username}</span>' +
                    '</div>' +
                '</div>' +
            '</tpl>'
    },

    onRender: function() {
        var me = this;

        me.callParent(arguments);

        me.inputImgEl = me.inputCell.createChild({
            tag: 'img',
            src: Ext.BLANK_IMAGE_URL,
            style: 'position:absolute;top:8px;left:6px;'
        });
        me.inputImgEl.setVisibilityMode(Ext.Element.DISPLAY);
        me.setInputImgElType(me.getValue());
    },

    setInputImgElType: function(newValue, oldValue) {
        var me = this,
            rec = me.findRecordByValue(newValue),
            oldRec = me.findRecordByValue(oldValue);
        if (oldRec) {
            this.inputImgEl.removeCls('scalr-scope-' + oldRec.get('level'));
        }
        if (rec) {
            this.inputImgEl.addCls('scalr-scope-' + rec.get('level'));
            this.inputImgEl.set({'data-qtip': Ext.String.capitalize(rec.get('level')) + ' scope'});
            this.inputImgEl.show();
            this.inputEl.setStyle('padding-left', '20px');
        } else {
            this.inputEl.setStyle('padding-left', '7px');
            this.inputImgEl.hide();
        }
    },

    onChange: function(newValue, oldValue) {
        var me = this;
        if (me.inputImgEl) {
            me.setInputImgElType(newValue, oldValue);
        }
        me.callParent(arguments);
    }

});

Ext.define('Scalr.ui.ChefEnvironmentField', {
	extend: 'Ext.form.field.ComboBox',
	alias: 'widget.chefenvironmentcombo',

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
            url: '/services/chef/xListEnvironments/'
        }
    }
});


Ext.define('Scalr.ui.ChefRunlistCosnstructor', {
    singleton: true,
    show: function(config) {
        var runlistGridData = [], runlistStore;
        
        Ext.Array.each(config.runlist, function(item) {
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
                items: [{
                    xtype: 'filterfield',
                    itemId: 'search',
                    emptyText: 'Filter',
                    hideFilterIcon: true,
                    filterFn: Ext.emptyFn,
                    listeners: {
                        change: {
                            fn: function(comp, value) {
                                var ct = this.up(),
                                    storeRoles = ct.down('#rolesGrid').store,
                                    storeRecipes = ct.down('#recipesGrid').store;
                                storeRoles.clearFilter(!!value);
                                storeRecipes.clearFilter(!!value);
                                if (value) {
                                    value = value.toLowerCase();
                                    storeRoles.filter([{filterFn: function(item){
                                        return item.get('name').toLowerCase().indexOf(value) !== -1;
                                    }}]);
                                    storeRecipes.filter([{filterFn: function(item){
                                        return item.get('id').toLowerCase().indexOf(value) !== -1;
                                    }}]);
                                }
                            },
                            buffer: 300
                        }
                    }
                },{
                    xtype: 'container',
                    height: 400,
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
                                emptyText: 'No roles found to match your search.',
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
                                    params: {servId: config.chefServerId},
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
                                emptyText: 'No recipes found to match your search.',
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
                                    params: {servId: config.chefServerId, chefEnv: config.chefEnvironment},
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
                }]
            }],
            ok: 'Save',
            closeOnSuccess: true,
            success: config.success,
            winConfig: {
                getValues: function() {
                    var runlist = [],
                        store = this.down('#runList').store;
                    (store.snapshot || store.data).each(function (record) {
                        if (record.get('cookbook')) {
                            runlist.push('recipe[' + record.get('cookbook') + '::' + record.get('name') + ']');
                        } else if (record.get('chef_type')) {
                            runlist.push('role[' + record.get('name') + ']');
                        } else {
                            runlist.push(record.get('name'));
                        }
                    });
                    return runlist;
                }
            }
        });
    }
});

Ext.define('Scalr.ui.ChefOrchestrationField', {
	extend: 'Ext.container.Container',
	alias: 'widget.cheforchestration',

    layout: 'anchor',
    defaults: {
        anchor: '100%',
        maxWidth: 640
    },

    noChefServerMessage: 'Bootstrap with Chef must be setup for the role in order to use this option.',

    initComponent: function() {
        this.callParent(arguments);
        this.chefSettings = this.chefSettings || {
            'chef.server_id': null,
            'chef.environment': null
        };

        if (this.disableItems) this.setItemsDisabled(true);
    },

    isValid: function(returnErrorField) {
        var field, result = true;
        
        if (!this.readOnly && !this.disabled) {
            if (this.down('#chefMode').getValue() === 'solo') {
                result = this.down('#solo').isValid(returnErrorField);
            } else {
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

    setValue: function(value) {
        value = value || {};
        var me = this,
            setValues = function() {
                var field,
                    chefServerBtn,
                    settingsCount = Ext.Object.getSize(value),
                    isNewRecord = settingsCount === 0,
                    isReconverge = (settingsCount === 3 && value['chef.runlist']==='' && value['chef.attributes']==='' && value['chef.role_name']===''),
                    isRoleChefServerSet = me.chefSettings['chef.bootstrap'] ==1 && me.chefSettings['chef.server_id'];
                me.reset();

                field = me.down('#chefMode');
                if (isReconverge || isNewRecord && !isRoleChefServerSet) {
                    field.setValue('reconverge');
                } else if (value['chef.cookbook_url']) {
                    field.setValue('solo');
                } else {
                    field.setValue('server');
                }
                chefServerBtn = field.items.getAt(0);
                chefServerBtn.setTooltip(!me.chefSettings['chef.server_id'] || me.chefSettings['chef.bootstrap'] != 1 ? me.noChefServerMessage : '');
                chefServerBtn.setDisabled(!me.chefSettings['chef.server_id'] || me.chefSettings['chef.bootstrap'] != 1);

                me.down('#solo').setValues(value);

                field = me.down('[name="chef.role_name"]');
                field.store.proxy.params = {servId: me.chefSettings['chef.server_id']};
                field.setValue(value['chef.role_name'] || me.chefSettings['chef.role_name']);
                
                me.down('[name="chef.runlist"]').setValue(value['chef.runlist'] || '');
                me.down('[name="chef.attributes"]').setValue(value['chef.attributes'] || me.chefSettings['chef.attributes']);
                me.down('#confType').setValue((value['chef.role_name'] || me.chefSettings['chef.role_name']) ? 'role' : 'runlist');
            };
        setValues();
    },

    getValue: function() {
        var value = {},
            chefMode = this.down('#chefMode').getValue();
        if (!this.readOnly) {
            if (chefMode === 'solo') {
                value['chef.runlist'] = this.down('[name="chef.runlist"]').getValue();
                Ext.apply(value, this.down('#solo').getValues());
                value['chef.attributes'] = this.down('[name="chef.attributes"]').getValue();
            } else if (chefMode === 'reconverge') {
                value['chef.role_name'] = '';
                value['chef.runlist'] = '';
                value['chef.attributes'] = '';
            } else {
                if (this.down('#confType').getValue() === 'role') {
                    value['chef.role_name'] = this.down('[name="chef.role_name"]').getValue();
                } else {
                    value['chef.runlist'] = this.down('[name="chef.runlist"]').getValue();
                }
                value['chef.attributes'] = this.down('[name="chef.attributes"]').getValue();
            }
        }
        return value;
    },
    
    reset: function() {
        this.setFieldValues({
            'chefMode': null,
            'confType': null,
            'chef.runlist': '',
            'chef.role_name': '',
            'chef.attributes': ''
        });
        this.down('#solo').resetValues();
        return this;
    },

    setReadOnly: function(readOnly) {
        var fields = this.query('[isFormField]');
        Ext.Array.each(fields, function(field){
            if (!Ext.Array.contains(['chef.attributes'], field.name)) {
                field.setReadOnly(!!readOnly, false);
            }
        });
        this.down('#configureRunlist').setDisabled(!!readOnly);
        this.readOnly = !!readOnly;
    },

    setItemsDisabled: function(disabled) {
        var fields = this.query('[isFormField]');
        Ext.Array.each(fields, function(field){
            field.setDisabled(!!disabled, false);
        });
    },


    items: [{
        xtype: 'buttongroupfield',
        name: 'chefMode',
        itemId: 'chefMode',
        margin: '0 0 28 0',
        defaults: {
            maxWidth: 200,
            flex: 1,
            height: 32
        },
        layout: 'hbox',
        items: [{
            text: 'Override runlist (Server)',
            value: 'server'
        },{
            text: 'Reconverge (Server)',
            value: 'reconverge'
        },{
            text: 'Chef solo',
            value: 'solo'
        }],
        listeners: {
            change: function(comp, value) {
                var ct = comp.up('cheforchestration'),
                    serverCt = comp.next('#server'),
                    soloCt = comp.next('#solo');
                ct.suspendLayouts();
                if (value) {
                    serverCt.setVisible(value === 'server');
                    soloCt.setVisible(value === 'solo');
                    if (value === 'server') {
                        ct.down('#attributes').show();
                        ct.down('#runlist').setVisible(ct.down('#confType').getValue() === 'runlist');
                        ct.down('#server').show();
                        ct.down('#reconverge').hide();
                    } else if (value === 'reconverge') {
                        ct.down('#reconverge').show();
                        ct.down('#attributes').hide();
                        ct.down('#runlist').hide();
                        ct.down('#server').hide();
                    } else {
                        comp.next('#attributes').show();
                        comp.next('#runlist').show();
                        ct.down('#reconverge').hide();
                    }
                }
                ct.resumeLayouts(true);
            }
        }
    },{
        xtype: 'displayfield',
        itemId: 'reconverge',
        cls: 'x-form-field-info',
        value: 'Reconverge will only execute on targets where Bootstrap with Chef was enabled'
    },{
        xtype: 'container',
        itemId: 'server',
        layout: 'anchor',
        defaults: {
            anchor: '100%',
            labelWidth: 120
        },
        items: [{
            xtype: 'container',
            itemId: 'configuration',
            layout: 'hbox',
            items: [{
                xtype: 'buttongroupfield',
                name: 'confType',
                itemId: 'confType',
                fieldLabel: 'Configuration',
                labelWidth: 120,
                width: 380,
                defaults: {
                    width: 120
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
                        var ct = comp.up('cheforchestration');
                        ct.down('[name="chef.role_name"]').setVisible(value === 'role');
                        if (ct.down('#chefMode').getValue() === 'server') {
                            ct.down('[name="chef.runlist"]').setVisible(value === 'runlist');
                        }
                        ct.down('#configureRunlist').setVisible(value === 'runlist').setDisabled(value !== 'runlist');
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
                    var ct = this.up('cheforchestration'),
                        runlistField = ct.down('[name="chef.runlist"]'),
                        runlist = Ext.decode(runlistField.getValue() || '[]', true);
                    if (runlist === null) {
                        var msg = 'JSON is invalid';
                        runlistField.markInvalid(msg);
                        Scalr.message.InfoTip(msg, runlistField.inputEl, {anchor: 'bottom'});
                        return;
                    }
                    Scalr.ui.ChefRunlistCosnstructor.show({
                        chefServerId: ct.chefSettings['chef.server_id'],
                        chefEnvironment: ct.chefSettings['chef.environment'],
                        runlist: runlist,
                        success: function (formValues, form) {
                            var runlist = this.getValues();
                            runlistField.setValue(runlist.length > 0 ? Ext.encode(runlist) : '');
                            return true;
                        }
                    });
                }
            }]
        }]
    },{
        xtype: 'chefsolocontainer',
        itemId: 'solo',
        hidden: true,
        margin: 0
    },{
        xtype: 'textarea',
        itemId: 'runlist',
        name: 'chef.runlist',
        fieldLabel: 'Runlist',
        labelAlign: 'top',
        height: 120,
        icons: {
            globalvars: true
        },
        emptyText: 'Paste your run list in JSON format'
    },{
        xtype: 'textarea',
        itemId: 'attributes',
        name: 'chef.attributes',
        fieldLabel: 'Attributes',
        icons: {
            globalvars: true
        },
        labelAlign: 'top',
        height: 120,
        emptyText: 'Paste attributes in JSON format'
    }]

});
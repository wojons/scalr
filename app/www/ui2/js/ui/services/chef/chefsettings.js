Ext.define('Scalr.ui.ChefSettingsField', {
    extend: 'Ext.container.Container',
    alias: 'widget.chefsettings',

    scrollable: 'y',
    readOnly: false,
    disableDaemonize: false,
    emptyAccountRoleEnvText: 'Will be defined in the Farm Role scope',

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
            limits = Scalr.getGovernance('general', 'general.chef'),
            field,
            setValues = function(success) {
                var field;
                me.reset();
                me.down('#chefMode').setValue(value['chef.cookbook_url'] ? 'solo' : 'server');
                me.down('#solo').setValues(value);
                me.down('[name="chef.bootstrap"]').setValue(value['chef.bootstrap']);
                me.down('[name="chef.server_id"]').setValue(serverId);
                field = me.down('[name="chef.environment"]');
                if (me.readOnly) {
                    if (roleChefSettings['chef.environment']) {
                        field.store.loadData([{name: 0, description: 'Role default (' + roleChefSettings['chef.environment'] + ')'}]);
                        field.setValue(farmRoleChefSettings['chef.environment'] || 0);
                        field.emptyText = 'Role default ('+roleChefSettings['chef.environment']+')';
                    } else {
                        if (farmRoleChefSettings['chef.environment']) {
                            field.setValue(farmRoleChefSettings['chef.environment']);
                        }
                        field.emptyText = ' ';
                    }
                } else {
                    if (Scalr.scope === 'account') {
                        field.store.loadData([{
                            name: 0, description: me.emptyAccountRoleEnvText
                        },{
                            name: defaultEnv
                        }]);
                        field.setValue(serverId ? (value['chef.environment'] || 0) : null);
                    } else {
                        field.setValue(value['chef.environment'] || (serverId ? defaultEnv : null));
                    }
                    field.emptyText = ' ';
                }
                field.applyEmptyText();
                me.down('[name="chef.role_name"]').setValue(value['chef.role_name']);
                me.down('[name="chef.runlist"]').setValue(value['chef.runlist']);
                me.down('[name="chef.node_name_tpl"]').setValue(value['chef.node_name_tpl']);
                me.down('[name="chef.ssl_verify_mode"]').setValue(value['chef.ssl_verify_mode'] || 'chef_auto');
                field = me.down('[name="chef.daemonize"]');
                field.setValue(value['chef.daemonize']);
                field.setReadOnly(!!me.disableDaemonize || me.readOnly);
                field.toggleIcon('warning', me.roleOsFamily === 'windows');

                field = me.down('[name="chef.attributes"]');
                if (me.readOnly) {
                    overrideAttributes = farmRoleChefSettings['chef.attributes'];
                    me.down('#overrideAttributes').setValue(!!overrideAttributes);
                    field.setValue(overrideAttributes ? farmRoleChefSettings['chef.attributes'] : roleChefSettings['chef.attributes']);
                    field.setReadOnly(!overrideAttributes);
                    if (me.mode === 'farmrole' && roleChefSettings['chef.allow_to_append_runlist'] == 1) {
                        me.down('[name="chef.runlist_append"]').setValue(farmRoleChefSettings['chef.runlist_append'] || '');
                    }
                } else {
                    field.setValue(value['chef.attributes']);
                    if (me.mode === 'role') {
                        me.down('[name="chef.allow_to_append_runlist"]').setValue(value['chef.allow_to_append_runlist']);
                    }
                }
                me.down('#confType').setValue(value['chef.role_name'] ? 'role' : 'runlist');
                me.down('[name="chef.log_level"]').setValue(farmRoleChefSettings['chef.log_level'] || value['chef.log_level'] || 'auto');
                if (value['chef.client_rb_template']) {
                    me.down('[name="chef.client_rb_template"]').setValue(value['chef.client_rb_template']);
                }
                if (value['chef.solo_rb_template']) {
                    me.down('[name="chef.solo_rb_template"]').setValue(value['chef.solo_rb_template']);
                }
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
        me.down('#invalidConfigurationWarning').hide();
        if (serverId) {//preload servers if server_id is set
            field = me.down('[name="chef.server_id"]');
            field.store.load(function(records, operation, success){
                if (me.readOnly && !farmRoleChefSettings['chef.environment'] && !field.findRecordByValue(serverId)) {
                    serverId = null;
                    field.emptyText = 'Invalid configuration';
                    field.applyEmptyText();
                    me.down('#invalidConfigurationWarning').show();
                }
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
                value['chef.solo_rb_template'] = this.down('[name="chef.solo_rb_template"]').getValue();
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
                value['chef.ssl_verify_mode'] = this.down('[name="chef.ssl_verify_mode"]').getValue();
                value['chef.daemonize'] = this.down('[name="chef.daemonize"]').getValue() ? 1 : 0;
                value['chef.client_rb_template'] = this.down('[name="chef.client_rb_template"]').getValue();
            }
            value['chef.log_level'] = logLevel;
            if (this.mode === 'role') {
                value['chef.allow_to_append_runlist'] = this.down('[name="chef.allow_to_append_runlist"]').getValue() ? 1 : 0;
            }
        } else {
            value['chef.environment'] = this.down('[name="chef.environment"]').getValue();
            if (logLevel != roleChefSettings['chef.log_level']) {
                value['chef.log_level'] = logLevel;
            }
           if (this.mode === 'farmrole' && roleChefSettings['chef.allow_to_append_runlist'] == 1) {
                value['chef.runlist_append'] = this.down('[name="chef.runlist_append"]').getValue();
            }
         }
        if (Ext.isEmpty(value['chef.environment']) || value['chef.environment'] == 0) {
            delete value['chef.environment'];
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
            'chef.allow_to_append_runlist': 0,
            'chef.server_id': '',
            'chef.environment': '',
            'chef.role_name': '',
            'chef.attributes': '',
            'chef.node_name_tpl': '',
            'chef.ssl_verify_mode': 'chef_auto',
            'chef.daemonize': 0,
            'chef.log_level': 'auto',
            'chef.client_rb_template': '',
            'chef.solo_rb_template': ''
        });
        this.down('#solo').resetValues();
    },

    setRoleChefSettings: function(roleChefSettings) {
        var me = this,
            fields = me.query('[isFormField]'),
            field;
        me.readOnly = !!roleChefSettings;
        me.roleChefSettings = roleChefSettings;
        me.suspendLayouts();
        Ext.Array.each(fields, function(field){
            if (!Ext.Array.contains(['overrideAttributes', 'chef.log_level', 'chef.environment', 'chef.runlist_append'], field.name)) {
                field.setReadOnly(me.readOnly, false);
            }
        });
        me.down('#configureRunlist').setDisabled(me.readOnly && roleChefSettings['chef.allow_to_append_runlist'] != 1);
        me.down('#overrideAttributes').setVisible(me.readOnly);
        field = me.down('[name="chef.attributes"]');
        field.setFieldLabel(me.readOnly ? '' : 'Attributes');
        me.resumeLayouts(true);
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
            collapse: function() {
                this.up('chefsettings').down('#advanced').hide();
            },
            expand: function() {
                if (this.down('#chefMode').getValue() === 'solo' || (this.down('[name="chef.environment"]').getValue() || this.up('chefsettings').readOnly)) {
                    this.up('chefsettings').down('#advanced').show();
                }
            },
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
                    var ct = comp.up('chefsettings'),
                        roleChefSettings = ct.roleChefSettings || {},
                        serverCt = ct.down('#server');
                    serverCt.setVisible(value === 'server');
                    ct.down('#advancedServer').setVisible(value === 'server');
                    ct.down('#solo').setVisible(value === 'solo');
                    ct.down('#advancedSolo').setVisible(value === 'solo');
                    if (value === 'server') {
                        var visible = serverCt.down('[name="chef.environment"]').getValue() || ct.readOnly || (Scalr.scope === 'account' && ct.down('[name="chef.server_id"]').getValue());
                        comp.next('#attributes').setVisible(visible);
                        comp.next('#nodeName').setVisible(visible);
                        comp.next('[name="chef.daemonize"]').setVisible(visible);
                        comp.next('[name="chef.runlist"]').setVisible(visible && serverCt.down('#confType').getValue() === 'runlist');
                        comp.next('[name="chef.runlist_append"]').setVisible(ct.mode === 'farmrole' && ct.readOnly && roleChefSettings['chef.allow_to_append_runlist'] == 1 && serverCt.down('#confType').getValue() === 'runlist');
                        ct.down('[name="chef.allow_to_append_runlist"]').setVisible(visible && serverCt.down('#confType').getValue() === 'runlist' && ct.mode === 'role');
                        serverCt.down('#configuration').setVisible(visible);
                        ct.down('#advanced').setVisible(visible && ct.down('[name="chef.bootstrap"]').getValue());
                    } else {
                        comp.next('#attributes').show();
                        comp.next('[name="chef.runlist"]').show();
                        comp.next('[name="chef.runlist_append"]').setVisible(ct.mode === 'farmrole' && ct.readOnly && roleChefSettings['chef.allow_to_append_runlist'] == 1);
                        ct.down('[name="chef.allow_to_append_runlist"]').setVisible(ct.mode === 'role');
                        comp.next('#nodeName').hide();
                        comp.next('[name="chef.daemonize"]').hide();
                        ct.down('#advanced').setVisible(ct.down('[name="chef.bootstrap"]').getValue());
                    }
                }
            }
        },{
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            itemId: 'invalidConfigurationWarning',
            value: 'Configuration is conflicting with Governance settings and cannot be used. Please ask your Scalr administrator to fix this.',
            hidden: true
        },{
            xtype: 'container',
            itemId: 'server',
            layout: 'anchor',
            defaults: {
                anchor: '100%',
                labelWidth: 135
            },
            items: [{
                xtype: 'chefserveridcombo',
                name: 'chef.server_id',
                listeners: {
                    change: function(comp, value) {
                        var envField = comp.next(),
                            chefSettingsField = comp.up('chefsettings'),
                            envValue = value ? '_default' : (chefSettingsField.readOnly ? 0 : null),
                            roleChefSettings = chefSettingsField.roleChefSettings || {},
                            roleDefaultEnv = {name: 0, description: 'Role default (' + roleChefSettings['chef.environment'] + ')'},
                            limits = Scalr.getGovernance('general', 'general.chef'),
                            rolesField = chefSettingsField.down('[name="chef.role_name"]'),
                            envList = null,
                            prependData = null;
                        if (Scalr.scope === 'account') {
                            if (value) {
                                prependData = [{
                                    name: 0, description: chefSettingsField.emptyAccountRoleEnvText
                                }];
                            }
                        } else {
                            if (value && limits !== undefined && limits['servers'][value]) {
                                var envs = limits['servers'][value]['environments'] || [];
                                if (envs.length) {
                                    envList = Ext.Array.map(envs, function(name){
                                        return {name: name};
                                    });
                                    envValue = envs.length === 1 ? envs[0] : null;
                                    if (chefSettingsField.readOnly && roleChefSettings['chef.environment']) {
                                        envValue = 0;
                                        envList.unshift(roleDefaultEnv);
                                    }
                                }
                            }
                            if (chefSettingsField.readOnly) {
                                if (roleChefSettings['chef.environment']) {
                                    prependData = [roleDefaultEnv];
                                } else if (!envValue) {
                                    envValue = null;
                                }
                            }
                        }
                        envField.store.proxy.prependData = prependData;
                        envField.store.proxy.data = envList;

                        envField.setDisabled(!value);
                        envField.store.proxy.params['servId'] = value;
                        envField.reset();
                        envField.setValue(envValue);

                        rolesField.reset();
                        rolesField.store.proxy.params = {
                            servId: value
                        };

                    }
                }
            },{
                xtype: 'chefenvironmentcombo',
                name: 'chef.environment',
                listeners: {
                    change: function(comp, value) {
                        var ct = comp.up('chefsettings'),
                            roleChefSettings = ct.roleChefSettings || {},
                            field,
                            visible = !!value || ct.readOnly || (Scalr.scope === 'account' && comp.prev('[name="chef.server_id"]').getValue());
                        if (ct.down('#chefMode').getValue() === 'server') {
                            ct.down('#attributes').setVisible(visible);
                            ct.down('#nodeName').setVisible(visible);
                            ct.down('[name="chef.daemonize"]').setVisible(visible);
                            ct.down('[name="chef.runlist"]').setVisible(visible && ct.down('#confType').getValue() == 'runlist');
                            ct.down('[name="chef.runlist_append"]').setVisible(ct.mode === 'farmrole' && ct.readOnly && roleChefSettings['chef.allow_to_append_runlist'] == 1 && ct.down('#confType').getValue() == 'runlist');
                            ct.down('[name="chef.allow_to_append_runlist"]').setVisible(visible && ct.down('#confType').getValue() == 'runlist' && ct.mode === 'role');
                            if (Scalr.scope === 'account') {
                                field = ct.down('#confType');
                                if (value == 0) field.setValue('runlist');
                                field.setReadOnly(value == 0);
                                ct.down('#configureRunlist').setDisabled(value == 0);
                            }
                            ct.down('#advanced').setVisible(visible && ct.down('[name="chef.bootstrap"]').getValue());
                        }
                        ct.down('#configuration').setVisible(visible);
                    }
                }
            },{
                xtype: 'fieldcontainer',
                itemId: 'configuration',
                layout: 'hbox',
                items: [{
                    xtype: 'buttongroupfield',
                    name: 'confType',
                    itemId: 'confType',
                    fieldLabel: 'Configuration',
                    labelWidth: 135,
                    width: 355,
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
                    plugins: {
                        ptype: 'fieldicons',
                        position: 'outer',
                        icons: [
                            {id: 'question', hidden: true, tooltip: 'Only manual Runlist configuration is supported in this mode'}
                        ]
                    },
                    listeners: {
                        writeablechange: function(comp, readOnly) {
                            if (Scalr.scope === 'account') {
                                this.toggleIcon('question', readOnly);
                            }
                        },
                        change: function(comp, value) {
                            var ct = comp.up('chefsettings'),
                                roleChefSettings = ct.roleChefSettings || {};
                            ct.down('[name="chef.role_name"]').setVisible(value === 'role');
                            if (ct.down('#chefMode').getValue() === 'server') {
                                var visible = (ct.down('[name="chef.environment"]').getValue() || ct.readOnly || (Scalr.scope === 'account' && ct.down('[name="chef.server_id"]').getValue())) && value === 'runlist';
                                ct.down('[name="chef.runlist"]').setVisible(visible);
                                ct.down('[name="chef.runlist_append"]').setVisible(ct.mode === 'farmrole' && ct.readOnly && roleChefSettings['chef.allow_to_append_runlist'] == 1 && value == 'runlist');
                                ct.down('[name="chef.allow_to_append_runlist"]').setVisible(visible && ct.mode === 'role');
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
                        },
                        sorters: {
                            property: 'name'
                        }
                    }
                },{
                    xtype: 'button',
                    itemId: 'configureRunlist',
                    hidden: true,
                    flex: 1,
                    iconCls: 'x-btn-icon-settings',
                    text: 'Configure runlist',
                    handler: function() {
                        var ct = this.up('chefsettings'),
                            roleChefSettings = ct.roleChefSettings || {},
                            runlistField,
                            envId,
                            runlist;
                        runlistField = ct.readOnly ? ct.down('[name="chef.runlist_append"]') : ct.down('[name="chef.runlist"]'),
                        runlist = Ext.decode(runlistField.getValue() || '[]', true);
                        if (runlist === null) {
                            var msg = 'JSON is invalid';
                            runlistField.focus();
                            runlistField.markInvalid(msg);
                            Scalr.message.InfoTip(msg, runlistField.inputEl, {anchor: 'bottom'});
                            return;
                        }
                        envId = ct.down('[name="chef.environment"]').getValue();
                        if (!envId) {
                            if (ct.readOnly && roleChefSettings['chef.environment']) {
                                envId = roleChefSettings['chef.environment'];
                            } else {
                                Scalr.message.InfoTip('Select Chef Environment first.', ct.down('[name="chef.environment"]').inputEl, {anchor: 'bottom'});
                            }
                        }
                        Scalr.ui.ChefRunlistCosnstructor.show({
                            chefServerId: ct.down('[name="chef.server_id"]').getValue(),
                            chefEnvironment: envId,
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
            xtype: 'textfield',
            fieldLabel: 'Node name',
            itemId: 'nodeName',
            labelWidth: 135,
            name: 'chef.node_name_tpl',
            emptyText: 'Leave blank to use server system hostname',
            submitEmptyText: false,
            plugins: {
                ptype: 'fieldicons',
                position: 'outer',
                icons: ['globalvars']
            }
        },{
            xtype: 'checkbox',
            name: 'chef.daemonize',
            labelWidth: 135,
            fieldLabel: '&nbsp;',
            labelSeparator: '',
            boxLabel: 'Daemonize chef client',
            plugins: {
                ptype: 'fieldicons',
                icons: [
                    {id: 'question', tooltip: 'Daemonize is not available in case of using Chef runlist in orchestration'},
                    {id: 'warning', tooltip: 'For this option to work, Chef Client <b>must</b> have been registered as a Windows service when it was installed.', hidden: true}
                ]
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
            plugins: {
                ptype: 'fieldicons',
                position: 'label',
                icons: ['globalvars']
            }
        },{
            xtype: 'textarea',
            name: 'chef.runlist_append',
            fieldLabel: 'Additional runlist',
            labelAlign: 'top',
            height: 100,
            hidden: true,
            emptyText: 'Paste your run list in JSON format',
            plugins: {
                ptype: 'fieldicons',
                position: 'label',
                icons: ['globalvars']
            }
        },{
            xtype: 'checkbox',
            name: 'chef.allow_to_append_runlist',
            boxLabel: 'Allow to append runlist on Farm Role level',
            margin: '-10 0 0 0',
            hidden: true
        },{
            xtype: 'checkbox',
            name: 'overrideAttributes',
            itemId: 'overrideAttributes',
            hidden: true,
            margin: '10 0 -10 0',
            boxLabel: 'Override attributes defined in the Role scope <img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qtip="Future attributes changes made on the Role will not affect this Farm Role" />',
            listeners: {
                change: function(comp, value) {
                    var attrField = comp.next('[name="chef.attributes"]'),
                        roleChefSettings = comp.up('chefsettings').roleChefSettings || {};
                    attrField.emptyText = value ? ' ' : 'Paste attributes in JSON format';
                    attrField.applyEmptyText();
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
            plugins: {
                ptype: 'fieldicons',
                position: 'label',
                icons: ['globalvars']
            },
            labelAlign: 'top',
            height: 220,
            margin: '6 0 0',
            emptyText: 'Paste attributes in JSON format'
        }]
    },{
        xtype: 'fieldset',
        collapsible: true,
        collapsed: true,
        title: 'Advanced settings',
        layout: 'fit',
        itemId: 'advanced',
        hidden: true,
        items: [{
            xtype: 'container',
            itemId: 'advancedServer',
            hidden: true,
            layout: 'anchor',
            defaults: {
                anchor: '100%',
                maxWidth: 570
            },
            items: [{
                xtype: 'buttongroupfield',
                fieldLabel: 'SSL verify mode',
                itemId: 'sslVerifyMode',
                labelWidth: 135,
                name: 'chef.ssl_verify_mode',
                defaults: {
                    width: 143
                },
                items: [{
                    text: 'Chef Default',
                    value: 'chef_auto'
                },{
                    text: 'Peer',
                    value: 'verify_peer'
                },{
                    text: 'None',
                    value: 'verify_none'
                }]
            },{
                xtype: 'combo',
                store: ['auto', 'debug', 'info', 'warn', 'error', 'fatal'],
                editable: false,
                fieldLabel: 'Log level',
                name: 'chef.log_level',
                itemId: 'logLevel',
                labelWidth: 135
            },{
                xtype: 'textarea',
                name: 'chef.client_rb_template',
                fieldLabel: 'client.rb template',
                labelAlign: 'top',
                height: 200
            }]
        },{
            xtype: 'container',
            itemId: 'advancedSolo',
            hidden: true,
            layout: 'anchor',
            defaults: {
                anchor: '100%',
                maxWidth: 570
            },
            items: [{
                xtype: 'textarea',
                name: 'chef.solo_rb_template',
                fieldLabel: 'solo.rb template',
                labelAlign: 'top',
                height: 200
            }]
        }]
    }]

});

Ext.define('Scalr.ui.ChefSoloSettings', {
    extend: 'Ext.container.Container',
    alias: 'widget.chefsolocontainer',

    layout: 'anchor',
    defaults: {
        anchor: '100%',
        labelWidth: 135
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
        fields: ['id', 'url', 'username', 'scope' ],
        proxy: {
            type: 'cachedrequest',
            url: '/services/chef/xListServers/'
        }
    },
    plugins: {
        ptype: 'fieldinnericonscope',
        tooltipScopeType: 'chefserver'
    },
    listConfig: {
        cls: 'x-boundlist-alt',
        tpl:
            '<tpl for=".">' +
                '<div class="x-boundlist-item" style="height: auto; width: auto; max-width: 900px;">' +
                    '{[this.getInnerIcon(values)]}&nbsp;&nbsp;{url}'+
                    '<div style="line-height: 16px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;margin:0 0 0 16px">' +
                        '<span style="font-style: italic;">Username: {username}</span>' +
                    '</div>' +
                '</div>' +
            '</tpl>'
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
    displayTpl:
        '<tpl for=".">' +
            '{[typeof values === "string" ? values : values.description||values.name]}' +
            '<tpl if="xindex < xcount">,</tpl>' +
        '</tpl>',
    listConfig: {
        getInnerTpl: function() {
            return '{[values.description||values.name]}';
        }
    },
    store: {
        model: Scalr.getModel({idProperty: 'name', fields: [ 'name', {name: 'description', convert: function(v, record){return record.data.description || record.data.name;}}]}),
        proxy: {
            type: 'cachedrequest',
            url: '/services/chef/xListEnvironments/'
        }
    }
});


Ext.define('Scalr.ui.ChefRunlistCosnstructor', {
    singleton: true,
    show: function(config) {
        var runlistGridData = [],
            runlistStore,
            store;

        Ext.Array.each(config.runlist, function(item) {
            var itemData = {}, id;
            if (item.indexOf('role[') === 0) {
                item = item.replace(/^role\[/i, '').replace(/\]$/i, '');
                Ext.apply(itemData, {
                    id: item,
                    type: 'role'
                });
            } else if (item.indexOf('recipe[') === 0) {
                item = item.replace(/^recipe\[/i, '').replace(/\]$/i, '');
                Ext.apply(itemData, {
                    id: item,
                    type: 'recipe'
                });
            } else {
                Ext.apply(itemData, {id: item});
            }
            runlistGridData.push(itemData);
        });

        runlistStore = Ext.create('store.store', {
            fields: ['id', 'type'],
            proxy: 'object',
            data: runlistGridData
        });

        store = Ext.create('store.store', {
            fields: [ 'id', 'type'],
            sorters: [{
                property: 'id'
            }],
            proxy: {
                type: 'cachedrequest',
                url: '/services/chef/xListRolesRecipes',
                params: {servId: config.chefServerId, chefEnv: config.chefEnvironment},
                processBox: false,
                filterFn: function(record){
                    var result = true;
                    return !runlistStore.getById(record.get('id'));
                }
            }
        });
        
        Scalr.Confirm({
            formWidth: 850,
            formLayout: 'fit',
            alignTop: true,
            winConfig: {
                autoScroll: false,
                layout: 'fit',
                height: '80%',
                getValues: function() {
                    var runlist = [],
                        store = this.down('#runList').store;
                    store.getUnfiltered().each(function(record) {
                        if (record.get('type') === 'recipe') {
                            runlist.push('recipe[' + record.get('id') + ']');
                        } else if (record.get('type') === 'role'){
                            runlist.push('role[' + record.get('id') + ']');
                        } else {
                            runlist.push(record.get('id'));
                        }
                    });
                    return runlist;
                }
            },
            form: [{
                xtype: 'container',
                layout: 'fit',
                items: {
                    xtype: 'fieldset',
                    title: 'Configure Chef runlist<span class="x-fieldset-header-description">Drag and drop roles and recipes from left to the right to configure runlist</span>',
                    cls: 'x-fieldset-separator-none',
                    layout: {
                        type: 'hbox',
                        align: 'stretch'
                    },
                    items: [{
                        xtype: 'grid',
                        itemId: 'availableList',
                        flex: 1,
                        selModel: {
                            selType: 'rowmodel',
                            mode: 'MULTI'
                        },
                        scrollable: 'y',
                        dockedItems: [{
                            xtype: 'toolbar',
                            dock: 'top',
                            ui: 'inline',
                            items: [{
                                xtype: 'filterfield',
                                itemId: 'search',
                                emptyText: 'Filter',
                                hideFilterIcon: true,
                                filterFn: Ext.emptyFn,
                                width: 160,
                                margin: '0 12 0 0',
                                store: store,
                                filterFields: ['id'],
                                listeners: {
                                    afterfilter: function() {
                                        var grid = this.up('grid');
                                        grid.store.removeAll();
                                        grid.store.load();
                                    }
                                }
                            },{
                                xtype: 'buttongroupfield',
                                itemId: 'typeFilter',
                                defaults: {
                                    width: 80
                                },
                                items: [{
                                    text: 'Roles',
                                    value: 'role'
                                },{
                                    text: 'Recipes',
                                    value: 'recipe'
                                }],
                                listeners: {
                                    change: function(comp, value) {
                                        store.removeFilter('typefilter');
                                        store.addFilter({
                                            id: 'typefilter',
                                            property: 'type',
                                            value: value
                                        });
                                        var grid = comp.up('grid');
                                        grid.columns[0].setText('Available ' + value + 's');
                                        grid.store.removeAll();
                                        grid.store.load();
                                    }
                                }
                            },{
                                xtype: 'tbfill'
                            },{
                                xtype: 'button',
                                cls: 'x-btn-flag',
                                iconCls: 'x-btn-icon-refresh',
                                tooltip: 'Refresh',
                                handler: function (me) {
                                    var ct = this.up('fieldset');
                                    this.up('grid').store.load({clearCache: true});
                                }
                            }]
                        }],
                        viewConfig: {
                            deferEmptyText: false,
                            focusedItemCls: '',
                            emptyText: 'Nothing found to match your search.',
                            preserveScrollOnRefresh: false,
                            loadingText: '',
                            plugins: {
                                ptype: 'gridviewdragdrop',
                                dragGroup: 'runList'
                            }
                        },
                        store: store,
                        columns: [{
                            flex: 1,
                            dataIndex: 'id',
                            sortable: false,
                            resizeable: false
                        }],
                        listeners: {
                            filterchange: function() {
                                this.view.refresh();
                            },
                            afterrender: function () {
                                var me = this,
                                    store = me.store;
                                store.load();
                                me.down('#typeFilter').setValue('role');
                            }
                        }
                    }, {
                        xtype: 'grid',
                        itemId: 'runList',
                        trackMouseOver: false,
                        bodyStyle: 'background:#fff',
                        margin: '45 0 0 12',
                        store: runlistStore,
                        flex: 1,
                        scrollable: 'y',
                        viewConfig: {
                            deferEmptyText: false,
                            focusedItemCls: '',
                            emptyText: 'Runlist is empty. Drag and drop roles and recipes here.',
                            plugins: {
                                ptype: 'gridviewdragdrop',
                                ddGroup: 'runList'
                            },
                            listeners: {
                                drop: function() {
                                    this.up('grid').prev().view.refresh();
                                }
                            }
                        },
                        columns: [{
                            xtype: 'templatecolumn',
                            tpl: '{type}[{id}]',
                            flex: 1,
                            text: 'Runlist',
                            sortable: false,
                            resizable: false,
                            border: false,
                            dataIndex: 'id'
                        }, {
                            xtype: 'templatecolumn',
                            tpl: '<img class="x-grid-icon x-grid-icon-delete" title="Remove from runlist" src="' + Ext.BLANK_IMAGE_URL + '"/>',
                            text: '&nbsp;',
                            width: 45,
                            sortable: false,
                            resizable: false,
                            border: false,
                            dataIndex: 'id',
                            align: 'center',
                            tdCls: 'x-grid-cell-nopadding',
                        }],
                        listeners: {
                            itemclick: function (view, record, item, index, e) {
                                if (e.getTarget('img.x-grid-icon-delete')) {
                                    var store = this.up('fieldset').down('#availableList').store,
                                        id = record.get('id');
                                    if (!store.getById(record.get('id'))) {
                                        store.add(record);
                                    }
                                    view.store.remove(record);
                                }
                            }
                        }
                    }]
                }
            }],
            ok: 'Save',
            closeOnSuccess: true,
            success: config.success
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
            maxWidth: 120,
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
            labelWidth: 135
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
                labelWidth: 135,
                width: 380,
                margin: '0 12 0 0',
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
                iconCls: 'x-btn-icon-settings',
                text: 'Configure runlist',
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
        plugins: {
            ptype: 'fieldicons',
            position: 'label',
            icons: ['globalvars']
        },
        emptyText: 'Paste your run list in JSON format'
    },{
        xtype: 'textarea',
        itemId: 'attributes',
        name: 'chef.attributes',
        fieldLabel: 'Attributes',
        plugins: {
            ptype: 'fieldicons',
            position: 'label',
            icons: ['globalvars']
        },
        labelAlign: 'top',
        height: 120,
        emptyText: 'Paste attributes in JSON format'
    }]

});

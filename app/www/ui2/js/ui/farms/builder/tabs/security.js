Ext.define('Scalr.ui.FarmRoleEditorTab.Security', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Security',
    itemId: 'security',
    layout: {
        type: 'hbox',
        align: 'stretch'
    },

    SETTING_SG_LIST: 'security_groups.list',

    settings: {
        'aws.security_groups.list': undefined
    },

    getTabTitle: function(record){
        var platform = record.get('platform'),
            isGovernanceEnabled = Scalr.getGovernance(platform, this.getSettingsPrefix(platform) + '.additional_security_groups') !==  undefined;
        this.tabButton.setTooltip(isGovernanceEnabled ? 'The account owner has enforced a specific policy on this setting.' : '');
        return '<span class="x-btn-inner-html-wrap">Security' + (isGovernanceEnabled ? '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-governance" style="vertical-align:baseline;position:relative;top:2px;" /></span>' : '');
    },

    getSettingsPrefix: function(platform) {
        var prefix = '';
        switch (platform) {
            case 'ec2':
                prefix = 'aws';
            break;
            case 'eucalyptus':
                prefix = 'euca';
            break;
            default:
                if (Scalr.isOpenstack(platform)) {
                    prefix = 'openstack';
                }
                if (Scalr.isCloudstack(platform)) {
                    prefix = 'cloudstack';
                }
            break;
        }
        return prefix;
    },

    //override default method
    getDefaultValues: function (record, roleDefaultSettings) {
        var values = {};
        if (roleDefaultSettings !== undefined && roleDefaultSettings[this.SETTING_SG_LIST]) {
            values[this.getSettingsPrefix(record.get('platform')) + '.' + this.SETTING_SG_LIST] = roleDefaultSettings[this.SETTING_SG_LIST];
        }
        return values;
    },

    isEnabled: function (record) {
        var platform = record.get('platform');
        return this.callParent(arguments) && record.get('securityGroupsEnabled') && (Ext.Array.contains(['ec2', 'eucalyptus'], platform) ||
               (Scalr.isOpenstack(platform) && Scalr.getPlatformConfigValue(platform, 'ext.securitygroups_enabled') == 1) ||
               Scalr.isCloudstack(platform));
    },

    splitGroupsList: function(list, record) {
        var p = record.get('platform');
        var uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
        var result = {
            ids: [],
            names: []
        };
        Ext.Array.each(list, function(item) {
            item = item.toString();
            if (Scalr.isOpenstack(p) || Scalr.isCloudstack(p)) {
                if (item == 'default' || item.indexOf('ip-pool') !== -1) {
                    result['names'].push(item);
                } else {
                    result['ids'].push(item);
                }
            } else {
                if (item.indexOf('sg-') !== 0 && !uuid.test(item)) {//item === 'scalr.ip-pool' || item.indexOf('scalr.farm-') === 0 || item.indexOf('scalr.role-') === 0 || item === 'default'
                    result['names'].push(item);
                } else {
                    result['ids'].push(item);
                }
            }
        });
        return result;
    },

    loadNotFoundGroups: function(list) {
        var grid = this.down('grid'),
            store = grid.getStore();
        if (this.limits === undefined || this.limits['allow_additional_sec_groups'] == 1) {
            Ext.Array.each(list['ids'], function(id) {
                if (!store.query('id', id, false, true, false).length) {
                    store.add(store.createModel({id: id}));
                }
            });
            Ext.Array.each(list['names'], function(name) {
                store.add(store.createModel({name: name}));
            });
        }
        if (this.limits !== undefined) {
            if (this.limits['value']) {
                Ext.Array.each(this.limits['value'].split(','), function(name) {
                    var res;
                    name = Ext.String.trim(name);
                    res = store.query(name.indexOf('sg-') !== 0 ? 'name' : 'id', name, false, false, true);
                    if (res.length === 0) {
                        var settings = {
                            addedByGovernance: true,
                            ignoreOnSave: true
                        };
                        settings[name.indexOf('sg-') !== 0 ? 'name' : 'id'] = name;
                        store.add(store.createModel(settings));
                    } else {
                        res.each(function(rec) {
                            rec.set('addedByGovernance', true);
                        });
                    }
                });
                //we have to refresh after setting addedByGovernance, to redraw selmodel checkboxes
                grid.getView().refresh();
            }
        }
    },

    beforeShowTab: function (record, handler) {
        var me = this,
            settings = record.get('settings', true),
            list,
            platform = record.get('platform'),
            splitList;

        //backward compatibility
        if (platform === 'ec2' && !settings['aws.' + me.SETTING_SG_LIST] && record.get('security_groups', true)) {
            settings['aws.' + me.SETTING_SG_LIST] = Ext.encode(record.get('security_groups', true));
        }

        list = Ext.decode(settings[me.getSettingsPrefix(platform) + '.' + me.SETTING_SG_LIST], true);

        splitList = me.splitGroupsList(list, record);

        me.limits = Scalr.getGovernance(platform, me.getSettingsPrefix(platform) + '.additional_security_groups');
        me.vpc = this.up('#farmDesigner').getVpcSettings();
        me.down('#add').setDisabled(me.limits !== undefined && me.limits['allow_additional_sec_groups'] != 1);

        if (me.limits === undefined || me.limits['allow_additional_sec_groups'] == 1) {
            if (splitList['ids'].length) {
                Scalr.Request({
                    url: '/security/groups/xListGroups',
                    params: {
                        filters: Ext.encode({
                            sgIds: splitList['ids'],
                            vpcId: me.vpc ? me.vpc.id : null
                        }),
                        platform: record.get('platform'),
                        cloudLocation: record.get('cloud_location'),
                    },
                    processBox: {
                        type: 'load',
                        msg: 'Loading ...'
                    },
                    success: function(data) {
                        me.down('grid').getStore().loadData(data.data);
                        handler();
                    },
                    failure: function (data, response, options) {
                        handler();
                    }
                });
            } else {
                handler();
            }
        } else {
            handler();
        }
    },

    showTab: function (record) {
        var settings = record.get('settings', true),
            list = Ext.decode(settings[this.getSettingsPrefix(record.get('platform')) + '.' + this.SETTING_SG_LIST], true);
        this.loadNotFoundGroups(this.splitGroupsList(list, record));
    },

    hideTab: function (record) {
        var settings,
            list = [],
            grid = this.down('grid'),
            store = grid.getStore();
        if (this.limits === undefined || this.limits['allow_additional_sec_groups'] == 1) {
            settings = record.get('settings');
            store.getUnfiltered().each(function (record) {
                var id = record.get('id');
                if (!record.get('ignoreOnSave')) {
                    list.push(id ? id : record.get('name'));
                }
            });
            settings[this.getSettingsPrefix(record.get('platform')) + '.' + this.SETTING_SG_LIST] = Ext.encode(list);
            record.set('settings', settings);
        }
        store.removeAll();
        this.down('#liveSearch').reset();
    },

    __items: [{
        xtype: 'grid',
        cls: 'x-panel-column-left x-panel-column-left-with-tabs',
        flex: 1,
        minWidth: 560,
        plugins: [{ptype: 'selectedrecord', disableSelection: false}, 'focusedrowpointer'],
        selModel: {
            selType: 'selectedmodel',
            getVisibility: function(record) {
                return !record.get('addedByGovernance');
            }
        },
        store: {
            model: Scalr.getModel({fields: ['id', 'name', 'description', 'addedByGovernance', 'ignoreOnSave']}),
            sorters: ['name']
        },
        listeners: {
            viewready: function() {
                var me = this;
                me.down('#liveSearch').store = me.store;
            },
            selectionchange: function(selModel, selected) {
                this.down('#delete').setDisabled(!selected.length);
            }
        },
        viewConfig: {
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No security groups were found to match your search.',
                emptyTextNoItems: 'Click on the button above to to add security groups to farm role'
            },
            listeners: {
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('.x-grid-row-checker')){
                        view.getSelectionModel().preventFocus = true;
                    }
                }
            },

            loadingText: 'Loading groups ...',
            deferEmptyText: false,
            overflowY: 'auto',
            overflowX: 'hidden'
        },

        columns: [{
            text: 'ID',
            width: 160,
            dataIndex: 'id',
            xtype: 'templatecolumn',
            tpl: '<tpl if="values.id">{id}<tpl else>-</tpl>'
        },{
            text: 'Security group',
            width: 200,
            dataIndex: 'name',
            xtype: 'templatecolumn',
            tpl: '<tpl if="values.name">{name}<tpl if="values.addedByGovernance">&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="Security group is forcefully added by account owner" class="x-icon-governance" /></tpl><tpl else>-</tpl>'

        },{
            text: 'Description',
            flex: 1,
            dataIndex: 'description',
            xtype: 'templatecolumn',
            tpl: '<tpl if="values.description">{description}<tpl else>-</tpl>'
        }],
        dockedItems: [{
            xtype: 'toolbar',
            ui: 'simple',
            dock: 'top',
            defaults: {
                margin: '0 0 0 10'
            },
            items: [{
                xtype: 'filterfield',
                itemId: 'liveSearch',
                margin: 0,
                filterFields: ['id', 'name']
            },{
                xtype: 'tbfill'
            },{
                itemId: 'add',
                text: 'Add security groups',
                cls: 'x-btn-green',
                tooltip: 'Add security groups to farm role',
                handler: function() {
                    var tab = this.up('#security'),
                        role = tab.currentRole,
                        store = tab.down('grid').store,
                        tabParams = this.up('#farmDesigner').moduleParams.tabParams,
                        excludeGroups = {names:[], ids:[]};
                    store.getUnfiltered().each(function (record) {
                        var id = record.get('id');
                        if (id) {
                            excludeGroups['ids'].push(id);
                        } else {
                            excludeGroups['names'].push(record.get('name'));
                        }
                    });
                    Scalr.Confirm({
                        formWidth: 950,
                        formLayout: 'fit',
                        alignTop: true,
                        winConfig: {
                            autoScroll: false,
                            layout: 'fit'
                        },
                        form: [{
                             xtype: 'sgmultiselect',
                             accountId: tabParams['accountId'],
                             remoteAddress: tabParams['remoteAddress'],
                             title: 'Add security groups to farm role',
                             limit: 10,
                             minHeight: 200,
                             excludeGroups: excludeGroups,
                             vpc: tab.vpc,
                             storeExtraParams: {
                                 platform: role.get('platform'),
                                 cloudLocation: role.get('cloud_location'),
                                 filters: Ext.encode({
                                     vpcId: tab.vpc ? tab.vpc.id : null
                                 })
                             }
                        }],
                        ok: 'Add',
                        disabled: true,
                        closeOnSuccess: true,
                        scope: this,
                        success: function (formValues, form) {
                            store.loadData(form.down('sgmultiselect').selection, true);
                            return true;
                        }
                    });
                }
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Remove security groups from farm role',
                handler: function() {
                    var grid = this.up('grid');
                    grid.getStore().remove(grid.getSelectionModel().getSelection());
                }
            }]
        }]
    },{
        xtype: 'container',
        flex: .6,
        layout: 'fit',
        items: {
            xtype: 'form',
            layout: 'fit',
            padding: 12,
            listeners: {
                loadrecord: function(record) {
                    var tab = this.up('#security'),
                        rulesGrid = tab.down('#rules'),
                        groupNotFound = tab.down('#groupNotFound');
                    rulesGrid.hide();
                    groupNotFound.hide();
                    rulesGrid.store.removeAll();
                    tab.down('#rulesTitle').setText((record.get('name') || record.get('id')) + ' rules (<a href="#">edit</a>)', false);
                    Scalr.Request({
                        url: '/security/groups/xGetGroupInfo',
                        hideErrorMessage: true,
                        params: {
                            platform: tab.currentRole.get('platform'),
                            cloudLocation: tab.currentRole.get('cloud_location'),
                            securityGroupId: record.get('id'),
                            securityGroupName: record.get('name'),
                            vpcId: tab.vpc ? tab.vpc.id : null
                        },
                        processBox: {
                            type: 'load',
                            msg: 'Loading ...'
                        },
                        success: function(data) {
                            if (data['id']) {
                                var allRules = [];
                                Ext.Array.each(['rules', 'sgRules'], function(field) {
                                    var rules = data[field];
                                    if (rules) {
                                        Ext.Object.each(rules, function(id, rule) {
                                            var data = Ext.clone(rule);
                                            data['id'] = id;
                                            if (field === 'rules') {
                                                data['sourceType'] = 'ip';
                                                data['sourceValue'] = data['cidrIp'];
                                                delete data['cidrIp'];
                                            } else {
                                                data['sourceType'] = 'sg';
                                                data['sourceValue'] = data['sg'];
                                                delete data['sg'];
                                            }
                                            allRules.push(data);
                                        });
                                    }
                                });
                                data['cloudLocation'] = tab.currentRole.get('cloud_location');
                                tab.down('#rulesTitle').securityGroupData = data;
                                rulesGrid.store.loadData(allRules);
                                rulesGrid.show();
                            } else {
                                if (!record.get('id')) {
                                    groupNotFound.down().setValue('The <b>' + record.get('name') + '</b> security group does not exist yet. It will be created when the first instance is launched.');
                                } else {
                                    groupNotFound.down().setValue('The <b>' + record.get('id') + '</b> security group does not exist.');
                                }
                                groupNotFound.show();
                            }
                        },
                        failure: function(data) {
                            if (data.errorMessage) {
                                if (!record.get('id') && data.errorMessage.match(/The security group (.+) does not exist$/i)) {
                                    groupNotFound.down().setValue('The <b>'+record.get('name')+'</b> security group does not exist yet. It will be created when the first instance is launched.');
                                    groupNotFound.show();
                                } else {
                                    Scalr.message.Error(data.errorMessage);
                                }
                            }
                        }
                    });
                }
            },
            items: [{
                xtype: 'container',
                itemId: 'groupNotFound',
                layout: 'anchor',
                hidden: true,
                items: {
                    xtype: 'displayfield',
                    anchor: '100%',
                    cls: 'x-form-field-info'
                }
            },{
                xtype: 'grid',
                itemId: 'rules',
                hidden: true,
                cls: 'x-grid-no-highlighting',
                store: {
                    fields: ['id', 'ipProtocol', 'fromPort', 'toPort', 'sourceType', 'sourceValue', 'comment'],
                    proxy: 'object'
                },
                viewConfig: {
                    focusedItemCls: 'no-focus',
                    emptyText: 'No rules defined'
                },
                columns: [{
                    text: 'Protocol',
                    dataIndex: 'ipProtocol'
                },{
                    text: 'Ports',
                    xtype: 'templatecolumn',
                    flex: .6,
                    dataIndex: 'fromPort',
                    tpl: '{fromPort} - {toPort}'
                },{
                    text: 'Source',
                    flex: 1,
                    dataIndex: 'sourceValue'
                }],
                dockedItems: [{
                    xtype: 'toolbar',
                    ui: 'inline',
                    dock: 'top',
                    items: [{
                        xtype: 'label',
                        itemId: 'rulesTitle',
                        cls: 'x-fieldset-subheader',
                        html: '&nbsp;',
                        margin: '0 0 8 0',
                        listeners: {
                            boxready: function() {
                                var me = this,
                                    tabParams = this.up('#farmDesigner').moduleParams.tabParams,
                                    inputEl = me.el;
                                inputEl.on('click', function(e) {
                                    var res = inputEl.query('a');
                                    if (res.length && e.within(res[0])) {
                                        Scalr.Confirm({
                                            formWidth: 950,
                                            formLayout: 'fit',
                                            alignTop: true,
                                            winConfig: {
                                                autoScroll: false,
                                                layout: 'fit'
                                            },
                                            form: [{
                                                 xtype: 'sgeditor',
                                                 vpcIdReadOnly: true,
                                                 accountId: tabParams['accountId'],
                                                 remoteAddress: tabParams['remoteAddress'],
                                                 listeners: {
                                                     afterrender: function() {
                                                         this.setValues(me.securityGroupData);
                                                     }
                                                 }
                                            }],
                                            ok: 'Save',
                                            closeOnSuccess: true,
                                            scope: me,
                                            success: function (formValues, form) {
                                                var win2 = form.up('#box'),
                                                    values = win2.down('sgeditor').getValues();
                                                if (values !== false) {
                                                    values['returnData'] = true;
                                                    Scalr.Request({
                                                        processBox: {
                                                            type: 'save'
                                                        },
                                                        url: '/security/groups/xSave',
                                                        params: values,
                                                        success: function (data) {
                                                            win2.destroy();
                                                            var grid = me.up('#security').down('grid'),
                                                                record = grid.getSelectedRecord();
                                                            grid.clearSelectedRecord();
                                                            if (record) grid.setSelectedRecord(record);
                                                        }
                                                    });
                                                }
                                            }
                                        });
                                    }
                                    e.preventDefault();
                                }, this);
                            }
                        }
                    }]
                }]
            }]
        }
    }]
});

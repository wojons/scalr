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

    sgUuid: /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
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
            default:
                if (Scalr.isOpenstack(platform)) {
                    prefix = 'openstack';
                } else if (Scalr.isCloudstack(platform)) {
                    prefix = 'cloudstack';
                } else {
                    prefix = platform;
                }
            break;
        }
        return prefix;
    },

    //override default method
    getDefaultValues: function (record, roleDefaultSettings) {
        var values = {},
            platform = record.get('platform');
        //do not add Scalr groups when sg governance enabled
        //find resetVpcId in plugins.js
        if (roleDefaultSettings !== undefined && Scalr.getGovernance(platform, this.getSettingsPrefix(platform) + '.additional_security_groups') === undefined) {
            if (roleDefaultSettings[this.SETTING_SG_LIST] && platform !== 'azure') {
                values[this.getSettingsPrefix(platform) + '.' + this.SETTING_SG_LIST] = roleDefaultSettings[this.SETTING_SG_LIST];
            }
        } else if (platform === 'ec2') {
            //we need this to avoid showing deprecated sg field under ec2 tab
            values[this.getSettingsPrefix(platform) + '.' + this.SETTING_SG_LIST] = '[]';
        }
        return values;
    },

    isEnabled: function (record) {
        var platform = record.get('platform');
        return this.callParent(arguments) && record.get('securityGroupsEnabled') && (Ext.Array.contains(['ec2', 'azure'], platform) ||
               (Scalr.isOpenstack(platform) && Scalr.getPlatformConfigValue(platform, 'ext.securitygroups_enabled') == 1) ||
               Scalr.isCloudstack(platform));
    },

    splitGroupsList: function(list, record) {
        var me = this,
            p = record.get('platform');
        var result = {
            ids: [],
            names: []
        };
        Ext.Array.each(list, function(item) {
            item = item.toString();
            if (p == 'ec2') {
                if (item.indexOf('sg-') !== 0) {
                    result['names'].push(item);
                } else {
                    result['ids'].push(item);
                }
            } else {
                if (!me.sgUuid.test(item) || item == 'default' || item.indexOf('ip-pool') !== -1) {
                    result['names'].push(item);
                } else {
                    result['ids'].push(item);
                }
            }
        });
        return result;
    },

    loadNotFoundGroups: function(list) {
        var me = this,
            grid = this.down('grid'),
            store = grid.getStore(),
            os = Scalr.utils.getOsById(this.currentRole.get('osId')) || {};
        if (this.limits === undefined || this.limits['allow_additional_sec_groups'] == 1) {
            Ext.Array.each(list['ids'], function(id) {
                if (!store.query('id', id, false, true, false).length) {
                    store.add(store.createModel({id: id}));
                }
            });
            Ext.Array.each(list['names'], function(name) {
                if (!store.query('name', name, false, true, false).length) {
                    store.add(store.createModel({name: name}));
                }
            });
        }
        if (this.limits !== undefined) {
            var governanceSGs = this.limits['value'];
            if (os.family === 'windows' && this.limits['windows']) {
                governanceSGs = this.limits['windows'];
            }
            if (governanceSGs) {
                Ext.Array.each(governanceSGs.split(','), function(name) {
                    var res,
                        name = Ext.String.trim(name),
                        isId = name.indexOf('sg-') === 0 || me.sgUuid.test(name);
                    res = store.query(!isId ? 'name' : 'id', name, false, false, true);
                    if (res.length === 0) {
                        var settings = {
                            addedByGovernance: true,
                            ignoreOnSave: true
                        };
                        settings[!isId ? 'name' : 'id'] = name;
                        store.add(store.createModel(settings));
                    } else {
                        res.each(function(rec) {
                            rec.set({
                                addedByGovernance: true,
                                ignoreOnSave: true
                            });
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
            farmRoleSecurityGroups,
            governanceSecurityGroups,
            securityGroups = [],
            os = Scalr.utils.getOsById(record.get('osId')) || {},
            platform = record.get('platform'),
            filters = {};
        
        //backward compatibility
        if (platform === 'ec2' && !settings['aws.' + me.SETTING_SG_LIST] && record.get('security_groups', true)) {
            settings['aws.' + me.SETTING_SG_LIST] = Ext.encode(record.get('security_groups', true));
        }

        me.limits = Scalr.getGovernance(platform, me.getSettingsPrefix(platform) + '.additional_security_groups');
        me.vpc = this.up('#farmDesigner').getVpcSettings();
        me.down('#add').setDisabled(me.limits !== undefined && me.limits['allow_additional_sec_groups'] != 1);
        
        if (me.limits !== undefined) {
            governanceSecurityGroups = (os.family === 'windows' && me.limits['windows'] ? me.limits['windows'] : me.limits['value']) || null;
            if (governanceSecurityGroups) {
                securityGroups.push.apply(securityGroups, governanceSecurityGroups.split(','));
            }
        }        
        
        if (me.limits === undefined || me.limits['allow_additional_sec_groups'] == 1) {
            farmRoleSecurityGroups = Ext.decode(settings[me.getSettingsPrefix(platform) + '.' + me.SETTING_SG_LIST], true);
            if (Ext.isArray(farmRoleSecurityGroups) && farmRoleSecurityGroups.length) {
                securityGroups.push.apply(securityGroups, farmRoleSecurityGroups);
            }
        }
        
        if (securityGroups.length) {
            securityGroups = me.splitGroupsList(securityGroups, record);
            filters['sgIds'] = securityGroups['ids'];
            filters['sgNames'] = securityGroups['names'];
            filters['vpcId'] = me.vpc ? me.vpc.id : null;
            
            if (platform === 'azure') {
                filters['resourceGroup'] = settings['azure.resource-group'];
            }
            Scalr.Request({
                url: '/farms/builder/xListSecurityGroups',
                params: {
                    filters: Ext.encode(filters),
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
    },

    showTab: function (record) {
        var settings = record.get('settings', true),
            list = Ext.decode(settings[this.getSettingsPrefix(record.get('platform')) + '.' + this.SETTING_SG_LIST], true);
        this.loadNotFoundGroups(this.splitGroupsList(list, record));
        this.down('grid').getView().refresh(); // if don't refresh, showTab (on activate tab) shows only first record (from 2 records)

        var rulesGridView = this.down('#rules').getView();
        rulesGridView.refresh();

        if (record.get('platform') === 'ec2' && this.vpc !== false) {
            rulesGridView.getFeature('groupingByType').enable();
        } else {
            rulesGridView.getFeature('groupingByType').disable();
        }
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
        grid.clearSelectedRecord();
        this.down('#liveSearch').reset();
        store.removeAll();
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
            model: Scalr.getModel({
                fields: [
                    {name: 'id', sortType: 'asUCText'}, 
                    {name: 'name', sortType: 'asUCText'}, 
                    {name: 'description', sortType: 'asUCText'}, 
                    'addedByGovernance', 
                    'ignoreOnSave'
                ]
            }),
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
                filterFields: ['id', 'name'],
                listeners: {
                    afterfilter: function(){
                        this.up('grid').clearSelectedRecord();
                    }
                }
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
                        osFamily = (Scalr.utils.getOsById(role.get('osId')) || {}).family,
                        excludeGroups = {names:[], ids:[]},
                        filters = {considerGovernance: true, existingGroupsOnly: true, osFamily: osFamily},
                        sgLimit = 0,
                        disableAddButton;
                    store.getUnfiltered().each(function (record) {
                        var id = record.get('id');
                        if (id) {
                            excludeGroups['ids'].push(id);
                        } else {
                            excludeGroups['names'].push(record.get('name'));
                        }
                    });

                    filters['vpcId'] = tab.vpc ? tab.vpc.id : null;

                    if (role.get('platform') === 'azure') {
                        filters['resourceGroup'] = role.get('settings', true)['azure.resource-group'];
                    }

                    if (role.get('platform') === 'azure') {
                        sgLimit = 1;
                    } else if (role.get('platform') === 'ec2') {
                        sgLimit = tabParams['scalr.aws.ec2.limits.security_groups_per_instance'];
                    }

                    disableAddButton = !Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage');
                    if (!disableAddButton && tab.limits !== undefined) {
                        disableAddButton = tab.limits['allow_additional_sec_groups'] == 0 || !Ext.isEmpty(tab.limits['additional_sec_groups_list']);
                    }

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
                             listGroupsUrl: '/farms/builder/xListSecurityGroups',
                             title: 'Add security groups to farm role',
                             limit: sgLimit,
                             minHeight: 200,
                             excludeGroups: excludeGroups,
                             vpc: tab.vpc,
                             resourceGroup: role.get('settings', true)['azure.resource-group'],
                             disableAddButton: disableAddButton,
                             storeExtraParams: {
                                 platform: role.get('platform'),
                                 cloudLocation: role.get('cloud_location'),
                                 filters: Ext.encode(filters)
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
                    tab.down('#rulesTitle').setText((record.get('name') || record.get('id')) + (Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage') ? ' (<a href="#">edit</a>)' : ''), false);
                    Scalr.Request({
                        url: '/security/groups/xGetGroupInfo',
                        hideErrorMessage: true,
                        params: {
                            platform: tab.currentRole.get('platform'),
                            cloudLocation: tab.currentRole.get('cloud_location'),
                            securityGroupId: record.get('id'),
                            securityGroupName: record.get('name'),
                            vpcId: tab.vpc ? tab.vpc.id : null,
                            resourceGroup: tab.currentRole.get('settings', true)['azure.resource-group']
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
                                if (tab.currentRole.get('platform') === 'azure') {
                                    data['resourceGroup'] = tab.currentRole.get('settings', true)['azure.resource-group'];
                                }
                                tab.down('#rulesTitle').securityGroupData = data;
                                rulesGrid.store.loadData(allRules);
                                rulesGrid.show();
                            } else {
                                if (!record.get('id')) {
                                    groupNotFound.down().setValue('The <b>' + record.get('name') + '</b> security group does not exist.');
                                } else {
                                    groupNotFound.down().setValue('The <b>' + record.get('id') + '</b> security group does not exist.');
                                }
                                groupNotFound.show();
                            }
                        },
                        failure: function(data) {
                            if (data.errorMessage) {
                                var errorMessage = data.errorMessage;
                                if (!record.get('id') && (data.errorMessage.match(/The security group (.+) does not exist$/i) ||
                                    data.errorMessage.match(/The Resource (.+) under resource group (.+) was not found.$/i))
                                ) {
                                    errorMessage = 'The <b>'+record.get('name')+'</b> security group does not exist.';
                                }
                                groupNotFound.down().setValue(errorMessage);
                                groupNotFound.show();
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
                    proxy: 'object',
                    groupField: 'type'
                },
                viewConfig: {
                    focusedItemCls: 'no-focus',
                    emptyText: 'No rules defined'
                },
                features: [{
                    ftype: 'grouping',
                    id: 'groupingByType',
                    hideGroupedHeader: true,
                    disabled: true,
                    groupHeaderTpl: [
                        '<span>',
                        '<tpl if="name === \'outbound\'">',
                            'Outbound',
                        '<tpl else>',
                            'Inbound',
                        '</tpl>',
                        ' ({rows.length})</span>'
                    ]
                }],
                columns: [{
                    text: 'Protocol',
                    dataIndex: 'ipProtocol',
                    xtype: 'templatecolumn',
                    tpl: '{[values.ipProtocol==\'*\'?\'ANY\':Ext.util.Format.uppercase(values.ipProtocol)]}'
                },{
                    text: 'Ports',
                    xtype: 'templatecolumn',
                    flex: .6,
                    dataIndex: 'fromPort',
                    tpl:
                    '<tpl if="ipProtocol==\'icmp\'">' +
                        '<tpl if="fromPort==-1&&fromPort==toPort">' +
                            'ANY' +
                        '<tpl else>' +
                            '{[values.fromPort==-1?\'ANY\':values.fromPort]}<tpl if="toPort"> - {[values.toPort==-1?\'ANY\':values.toPort]}</tpl>'+
                        '</tpl>' +
                    '<tpl else>' +
                        '<tpl if="!fromPort&&!toPort||fromPort==\'*\'&&toPort==\'*\'">' +
                            'ANY' +
                        '<tpl elseif="!toPort">' +
                            '{fromPort}' +
                        '<tpl else>' +
                            '{fromPort} - {toPort}' +
                        '</tpl>' +
                    '</tpl>'

                },{
                    text: 'Source',
                    flex: 1,
                    dataIndex: 'sourceValue',
                    xtype: 'templatecolumn',
                    tpl: '{[values.sourceValue==\'*\'?\'ANY\':Ext.util.Format.uppercase(values.sourceValue)]}'
                }],
                dockedItems: [{
                    xtype: 'toolbar',
                    ui: 'inline',
                    dock: 'top',
                    items: [{
                        xtype: 'label',
                        itemId: 'rulesTitle',
                        cls: 'x-fieldset-subheader x-fieldset-subheader-no-text-transform',
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
                                            hideOk: !Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage'),
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

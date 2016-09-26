Scalr.regPage('Scalr.ui.tools.aws.rds.sg.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'DBSecurityGroupDescription','DBSecurityGroupName'
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/aws/rds/sg/xList/'
        },
        remoteSort: true
    });
    var panel = Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'RDS Security groups',
            menuHref: '#/tools/aws/rds/sg',
            menuFavorite: true
        },
        stateId: 'grid-tools-aws-rds-sg-view',
        stateful: true,
        store: store,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],
        viewConfig: {
            emptyText: 'No security groups found',
            loadingText: 'Loading security groups ...'
        },
        disableSelection: true,
        columns: [
            { flex: 2, text: "Security group", dataIndex: 'DBSecurityGroupName', sortable: true },
            { flex: 2, text: "Description", dataIndex: 'DBSecurityGroupDescription', sortable: true },
            {
                xtype: 'optionscolumn',
                menu: [{
                    text: 'Edit',
                    iconCls: 'x-menu-icon-edit',
                    showAsQuickAction: true,
                    getVisibility: function(data) {
                        return Scalr.isAllowed('AWS_RDS', 'manage');
                    },
                    menuHandler: function(data) {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/rds/sg/edit?dbSgName=' + data['DBSecurityGroupName'] + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
                    }
                },{
                    text: 'Events log',
                    showAsQuickAction: true,
                    iconCls: 'x-menu-icon-logs',
                    getVisibility: function(data) {
                        return Scalr.isAllowed('LOGS_EVENT_LOGS');
                    },
                    menuHandler: function(data) {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/rds/logs?name=' + data['DBSecurityGroupName'] + '&type=db-security-group&cloudLocation=' + store.proxy.extraParams.cloudLocation);
                    }
                },{
                    text: 'Delete',
                    showAsQuickAction: true,
                    iconCls: 'x-menu-icon-delete',
                    getVisibility: function(data) {
                        return Scalr.isAllowed('AWS_RDS', 'manage');
                    },
                    menuHandler: function(data) {
                        Scalr.Request({
                            confirmBox: {
                                msg: 'Remove selected security group?',
                                type: 'delete'
                            },
                            processBox: {
                                msg: 'Removing security group ...',
                                type: 'delete'
                            },
                            scope: this,
                            url: '/tools/aws/rds/sg/xDelete',
                            params: {cloudLocation: panel.down('#cloudLocation').value, dbSgName: data['DBSecurityGroupName']},
                            success: function (data, response, options){
                                store.load();
                            }
                        });
                    }
                }]
            }
        ],
        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            beforeItems: [{
                text: 'Add group',
                cls: 'x-btn-green',
                hidden: !Scalr.isAllowed('AWS_RDS', 'manage'),
                handler: function() {
                    Scalr.Request({
                        confirmBox: {
                            title: 'Create new security group',
                            form: [{
                                xtype: 'fieldset',
                                cls: 'x-fieldset-separator-none',
                                defaults: {
                                    anchor: '100%'
                                },
                                fieldDefaults: {
                                    labelWidth: 120
                                },
                                items: [{
                                    xtype: 'combo',
                                    name: 'cloudLocation',
                                    plugins: {
                                        ptype: 'fieldinnericoncloud',
                                        platform: 'ec2'
                                    },
                                    store: {
                                        fields: [ 'id', 'name' ],
                                        data: moduleParams.locations,
                                        proxy: 'object'
                                    },
                                    editable: false,
                                    fieldLabel: 'Cloud Location',
                                    queryMode: 'local',
                                    displayField: 'name',
                                    valueField: 'id',
                                    value: panel.down('#cloudLocation').value
                                },{
                                    xtype: 'textfield',
                                    name: 'dbSecurityGroupName',
                                    fieldLabel: 'Name',
                                    allowBlank: false
                                },{
                                    xtype: 'textfield',
                                    name: 'dbSecurityGroupDescription',
                                    fieldLabel: 'Description',
                                    allowBlank: false
                                }]
                            }]
                        },
                        processBox: {
                            type: 'save'
                        },
                        scope: this,
                        url: '/tools/aws/rds/sg/xCreate/',
                        success: function (data, response, options){
                            if (options.params.cloudLocation == panel.down('#cloudLocation').value){
                                store.add({'DBSecurityGroupName': options.params.dbSecurityGroupName, 'dbSecurityGroupDescription': options.params.dbSecurityGroupDescription});
                            }
                            Scalr.event.fireEvent('redirect', '#/tools/aws/rds/sg/edit?dbSgName=' + options.params.dbSecurityGroupName + '&cloudLocation=' + options.params.cloudLocation);
                        }
                    });
                }
            }],
            items: [{
                xtype: 'filterfield',
                store: store
            }, ' ', {
                xtype: 'cloudlocationfield',
                platforms: ['ec2'],
                gridStore: store
            }]
        }]
    });
    return panel;
});

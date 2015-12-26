Scalr.regPage('Scalr.ui.tools.aws.rds.pg.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'Description','DBParameterGroupName'
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/aws/rds/pg/xList'
        },
        remoteSort: true
    });
    var panel = Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'RDS Parameter groups',
            menuHref: '#/tools/aws/rds/pg',
            menuFavorite: true
        },
        stateId: 'grid-tools-aws-rds-pg-view',
        stateful: true,
        store: store,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],
        viewConfig: {
            emptyText: 'No parameter groups found',
            loadingText: 'Loading parameter groups ...'
        },
        disableSelection: true,
        columns: [
            { flex: 2, text: "Parameter group", dataIndex: 'DBParameterGroupName', sortable: true },
            { flex: 2, text: "Description", dataIndex: 'Description', sortable: true },
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
                        Scalr.event.fireEvent('redirect', '#/tools/aws/rds/pg/edit?name=' + data['DBParameterGroupName'] + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
                    }
                },{
                    text: 'Events log',
                    iconCls: 'x-menu-icon-logs',
                    showAsQuickAction: true,
                    getVisibility: function(data) {
                        return Scalr.isAllowed('LOGS_EVENT_LOGS');
                    },
                    menuHandler: function(data) {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/rds/logs?name=' + data['DBParameterGroupName'] + '&type=db-instance&cloudLocation=' + store.proxy.extraParams.cloudLocation);
                    }
                },{
                    text: 'Delete',
                    iconCls: 'x-menu-icon-delete',
                    showAsQuickAction: true,
                    getVisibility: function(data) {
                        return Scalr.isAllowed('AWS_RDS', 'manage');
                    },
                    menuHandler: function(data) {
                        Scalr.Request({
                            confirmBox: {
                                msg: 'Remove selected parameter group?',
                                type: 'delete'
                            },
                            processBox: {
                                msg: 'Removing parameter group ...',
                                type: 'delete'
                            },
                            scope: this,
                            url: '/tools/aws/rds/pg/xDelete',
                            params: {cloudLocation: panel.down('#cloudLocation').value, name: data['DBParameterGroupName']},
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
                            title: 'Create new parameter group',
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
                                    value: panel.down('#cloudLocation').value,
                                    listeners: {
                                        beforerender: function (me) {
                                            me.fireEvent('change', me, me.getValue());
                                        },
                                        change: function (me, value) {
                                            Scalr.Request({
                                                processBox: {
                                                    type: 'load'
                                                },
                                                url: '/tools/aws/rds/pg/xGetDBFamilyList',
                                                params: {
                                                    cloudLocation: value
                                                },
                                                success: function (response) {
                                                    var engineFamilyField = me.next('[name=EngineFamily]');
                                                    var engineFamilyStore = engineFamilyField.getStore();

                                                    engineFamilyStore.loadData(
                                                        response['engineFamilyList']
                                                    );

                                                    engineFamilyField.setValue(
                                                        engineFamilyStore.first()
                                                    );
                                                }
                                            });
                                        }
                                    }
                                },{
                                    xtype: 'textfield',
                                    name: 'dbParameterGroupName',
                                    fieldLabel: 'Name',
                                    allowBlank: false
                                },{
                                    xtype: 'combo',
                                    name: 'EngineFamily',
                                    fieldLabel: 'Family',
                                    queryMode: 'local',
                                    editable: false,
                                    store: {
                                        reader: 'array',
                                        fields: [ 'family' ]
                                    },
                                    valueField: 'family',
                                    displayField: 'family'
                                },{
                                    xtype: 'textfield',
                                    name: 'Description',
                                    fieldLabel: 'Description',
                                    allowBlank: false
                                }]
                            }]
                        },
                        processBox: {
                            type: 'save'
                        },
                        scope: this,
                        url: '/tools/aws/rds/pg/xCreate',
                        success: function (data, response, options){
                            store.load();
                        }
                    });
                }
            }],
            items: [{
                xtype: 'cloudlocationfield',
                platforms: ['ec2'],
                gridStore: store
            }]
        }]
    });
    return panel;
});

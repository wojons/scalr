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
		title: 'Tools &raquo; Amazon Web Services &raquo; Amazon RDS &raquo; Manage parameter groups',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		plugins: {
			ptype: 'gridstore'
		},
		viewConfig: {
			emptyText: 'No parameter groups found',
			loadingText: 'Loading parameter groups ...'
		},
		columns: [
			{ flex: 2, text: "Name", dataIndex: 'DBParameterGroupName', sortable: true },
			{ flex: 2, text: "Description", dataIndex: 'Description', sortable: true },
			{
				xtype: 'optionscolumn2',
				menu: [{
					text: 'Edit',
					iconCls: 'x-menu-icon-edit',
					menuHandler: function(data) {
						Scalr.event.fireEvent('redirect', '#/tools/aws/rds/pg/edit?name=' + data['DBParameterGroupName'] + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
					}
				},{
					text: 'Events log',
					iconCls: 'x-menu-icon-logs',
					menuHandler: function(data) {
						Scalr.event.fireEvent('redirect', '#/tools/aws/rds/logs?name=' + data['DBParameterGroupName'] + '&type=db-instance&cloudLocation=' + store.proxy.extraParams.cloudLocation);
					}
				},{
					xtype: 'menuseparator'
				},{
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
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
                cls: 'x-btn-green-bg',
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
                                items: [{
                                    xtype: 'combo',
                                    name: 'cloudLocation',
                                    store: {
                                        fields: [ 'id', 'name' ],
                                        data: moduleParams.locations,
                                        proxy: 'object'
                                    },
                                    editable: false,
                                    fieldLabel: 'Location',
                                    queryMode: 'local',
                                    displayField: 'name',
                                    valueField: 'id',
                                    value: panel.down('#cloudLocation').value,
                                    listeners: {
                                        boxready: function (me) {
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
				xtype: 'fieldcloudlocation',
				itemId: 'cloudLocation',
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
				gridStore: store
			}]
		}]
	});
	return panel;
});

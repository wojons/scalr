Scalr.regPage('Scalr.ui.tools.aws.rds.pg.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'Description','DBParameterGroupName'
		],
		proxy: {
			type: 'scalr.paging',
			extraParams: loadParams,
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
		scalrReconfigureParams: {},
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
				xtype: 'optionscolumn',
				optionsMenu: [{
					text: 'Edit',
					iconCls: 'x-menu-icon-edit',
					menuHandler: function(item) {
						Scalr.event.fireEvent('redirect', '#/tools/aws/rds/pg/edit?name=' + item.record.get('DBParameterGroupName') + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
					}
				},{
					text: 'Events log',
					iconCls: 'x-menu-icon-logs',
					menuHandler: function(item) {
						Scalr.event.fireEvent('redirect', '#/tools/aws/rds/logs?name=' + item.record.get('DBParameterGroupName') + '&type=db-instance&cloudLocation=' + store.proxy.extraParams.cloudLocation);
					}
				},{
					xtype: 'menuseparator'
				},{
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
					menuHandler: function(item) {
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
							params: {cloudLocation: panel.down('#cloudLocation').value, name: item.record.get('DBParameterGroupName')},
							success: function (data, response, options){
								store.remove(item.record);
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
			afterItems: [{
				ui: 'paging',
				iconCls: 'x-tbar-add',
				handler: function() {
					Scalr.Request({
						confirmBox: {
							title: 'Create new parameter group',
							form: [{
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
								value: panel.down('#cloudLocation').value
							},{
								xtype: 'combo',
								name: 'Engine',
								store: [['mysql5.1','MySQL 5.1']],
								queryMode: 'local',
								value: 'mysql5.1',
								editable: false,
								fieldLabel: 'Engine'
							},{
								xtype: 'textfield',
								name: 'dbParameterGroupName',
								fieldLabel: 'Name',
								allowBlank: false
							},{
								xtype: 'textfield',
								name: 'Description',
								fieldLabel: 'Description',
								allowBlank: false
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
				gridStore: store,
				cloudLocation: loadParams['cloudLocation'] || ''
			}]
		}]
	});
	return panel;
});

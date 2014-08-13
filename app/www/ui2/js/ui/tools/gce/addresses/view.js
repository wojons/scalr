Scalr.regPage('Scalr.ui.tools.gce.addresses.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id','ip', 'description','status','createdAt','serverIndex','serverId',
			'instanceId','farmName','farmId','roleName','farmRoleId'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/gce/addresses/xListAddresses/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Tools &raquo; GCE &raquo; Static IPs',
		scalrOptions: {
			'reload': true,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-tools-gce-addresses-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'GCE Static IPs',
				href: '#/tools/gce/addresses'
			}
		}],

		viewConfig: {
			emptyText: 'No static ips found',
			loadingText: 'Loading static ips ...'
		},

		columns: [
			{ header: "Used By", flex: 1, dataIndex: 'farmName', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="farmId"><a href="#/farms/{farmId}/view" title="Farm {farmName}">{farmName}</a>' +
					'<tpl if="roleName">&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view"' +
						'title="Role {roleName}">{roleName}</a> #{serverIndex}' +
					'</tpl>' +
				'</tpl>' +
				'<tpl if="! farmId"><img src="/ui2/images/icons/false.png" /></tpl>'
			},
			{ header: "Name", width: 150, dataIndex: 'id', sortable: true },
			{ header: "IP", width: 140, dataIndex: 'ip', sortable: true },
			{ header: "Description", width: 200, dataIndex: 'description', sortable: true },
			{ header: "Date", width: 170, dataIndex: 'createdAt', sortable: true },
			{ header: "Status", width: 100, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				'{status}'
			},
			{ header: "Server", flex: 1, dataIndex: 'serverId', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="serverId"><a href="#/servers/{serverId}/view">{serverId}</a></tpl>' +
				'<tpl if="!serverId">{instanceId}</tpl>'
			},
			{
				xtype: 'optionscolumn2',
				menu: [{
					itemId: 'option.delete',
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
					request: {
						confirmBox: {
							type: 'delete',
							msg: 'Are you sure want to delete static IP "{ip}"?'
						},
						processBox: {
							type: 'delete',
							msg: 'Deleting static ip(s) ...'
						},
						url: '/tools/gce/addresses/xRemove/',
						dataHandler: function (data) {
							return { 
								addressId: Ext.encode([data['id']]),
								cloudLocation: store.proxy.extraParams.cloudLocation
							};
						},
						success: function () {
							store.load();
						}
					}
				}]
			}
		],

		multiSelect: true,
		selModel: {
			selType: 'selectedmodel'
		},
		listeners: {
			selectionchange: function(selModel, selections) {
				this.down('scalrpagingtoolbar').down('#delete').setDisabled(!selections.length);
			}
		},

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			afterItems: [{
				ui: 'paging',
				itemId: 'delete',
				iconCls: 'x-tbar-delete',
				tooltip: 'Select one or more IPs to delete them',
				disabled: true,
				handler: function() {
					var request = {
						confirmBox: {
							msg: 'Delete selected static ip(s): %s ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting static ip(s) ...',
							type: 'delete'
						},
						url: '/tools/gce/addresses/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('id'));
					}
					request.params = { addressId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
					Scalr.Request(request);
				}
			}],
			items: [{
                xtype: 'filterfield',
                store: store
            }, {
				xtype: 'fieldcloudlocation',
				itemId: 'cloudLocation',
                margin: '0 0 0 12',
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
				gridStore: store
			}]
		}]
	});
});

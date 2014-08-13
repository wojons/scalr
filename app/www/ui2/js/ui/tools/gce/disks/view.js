Scalr.regPage('Scalr.ui.tools.gce.disks.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id','description','status','size','snapshotId','createdAt'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/gce/disks/xListDisks/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Tools &raquo; GCE &raquo; Persistent disks',
		scalrOptions: {
			'reload': true,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-tools-gce-disks-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'GCE Persistent disks',
				href: '#/tools/gce/disks'
			}
		}],

		viewConfig: {
			emptyText: 'No PDs found',
			loadingText: 'Loading persistent disks ...'
		},

		columns: [
			{ header: "Used by", flex: 1, dataIndex: 'id', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="farmId">' +
					'<a href="#/farms/{farmId}/view" title="Farm {farmName}">{farmName}</a>' +
					'<tpl if="roleName">' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {roleName}">' +
						'{roleName}</a> #<a href="#/servers/{serverId}/view">{serverIndex}</a>' +
					'</tpl>' +
				'</tpl>' +
				'<tpl if="!farmId"><img src="/ui2/images/icons/false.png" /></tpl>'
			},
			{ header: "Name", width: 260, dataIndex: 'id', sortable: true },
			{ header: "Description", width: 400, dataIndex: 'description', sortable: true },
			{ header: "Date", width: 170, dataIndex: 'createdAt', sortable: true },
			{ header: "Size (GB)", width: 110, dataIndex: 'size', sortable: true },
			{ header: "Status", width: 180, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				'{status}'
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
							msg: 'Are you sure want to delete Disk "{diskId}"?'
						},
						processBox: {
							type: 'delete',
							msg: 'Deleting disk(s) ...'
						},
						url: '/tools/gce/disks/xRemove/',
						dataHandler: function (data) {
							return { 
								diskId: Ext.encode([data['id']]),
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
				tooltip: 'Select one or more volumes to delete them',
				disabled: true,
				handler: function() {
					var request = {
						confirmBox: {
							msg: 'Delete selected volume(s): %s ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting volume(s) ...',
							type: 'delete'
						},
						url: '/tools/gce/disks/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('id'));
					}
					request.params = { diskId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
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

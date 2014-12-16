Scalr.regPage('Scalr.ui.tools.openstack.snapshots.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'snapshotId', 'volumeId', 'status', 'createdAt', 'size', 'progress', 'name', 'description'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/openstack/snapshots/xListSnapshots/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Snapshots',
		scalrOptions: {
			'reload': true,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-tools-openstack-volumes-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Cloudstack Snapshots',
				href: '#/tools/openstack/snapshots?platform=' + loadParams['platform']
			}
		}],

		viewConfig: {
			emptyText: 'No snapshots found',
			loadingText: 'Loading snapshots ...'
		},

		columns: [
			{ header: "ID", width: 300, dataIndex: 'snapshotId', sortable: true },
			{ header: "Name", width: 150, dataIndex: 'name', sortable: true},
			{ header: "Description", width: 150, dataIndex: 'description', sortable: true, hidden: true},
			{ header: "Size", width: 150, dataIndex: 'size', sortable: true},
			{ header: "Volume ID", width: 300, dataIndex: 'volumeId', sortable: true },
			{ header: "Status", width: 150, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
                '{status} ({progress})'
            },
			{ header: "Created at", flex: 1, dataIndex: 'createdAt', sortable: true },
			{
				xtype: 'optionscolumn2',
				menu: [{
					itemId: 'option.create',
					text: 'Create new volume based on this snapshot',
					iconCls: 'x-menu-icon-create',
					menuHandler: function(data) {
						Scalr.event.fireEvent('redirect','#/tools/openstack/volumes/create?' +
							Ext.Object.toQueryString({
								'snapshotId': data['snapshotId'],
								'size': data['size'],
								'cloudLocation': store.proxy.extraParams.cloudLocation,
								'platform': loadParams['platform']
							})
						);
					}
				}, {
					itemId: 'option.delete',
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
					request: {
						confirmBox: {
							type: 'delete',
							msg: 'Are you sure want to delete Snapshot "{snapshotId}"?'
						},
						processBox: {
							type: 'delete',
							msg: 'Deleting volume(s) ...'
						},
						url: '/tools/openstack/snapshots/xRemove/',
						dataHandler: function (data) {
							return { 
								snapshotId: Ext.encode([data['snapshotId']]),
								cloudLocation: store.proxy.extraParams.cloudLocation,
								platform: loadParams['platform']
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
            ignoredLoadParams: ['platform'],
			store: store,
			dock: 'top',
			afterItems: [{
				ui: 'paging',
				itemId: 'delete',
				iconCls: 'x-tbar-delete',
				tooltip: 'Select one or more snapshots to delete them',
				disabled: true,
				handler: function() {
					var request = {
						confirmBox: {
							msg: 'Delete selected snapshot(s): %s ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting snapshot(s) ...',
							type: 'delete'
						},
						url: '/tools/openstack/snapshots/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('snapshotId'));
						request.confirmBox.objects.push(records[i].get('snapshotId'));
					}
					request.params = { platform: loadParams['platform'], snapshotId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
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

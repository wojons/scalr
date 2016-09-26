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
		scalrOptions: {
			reload: true,
			maximize: 'all',
            menuTitle: Scalr.utils.getPlatformName(loadParams['platform']) + ' Snapshots',
            //menuFavorite: true
		},
		store: store,
		stateId: 'grid-tools-openstack-volumes-view',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams',
            filterIgnoreParams: [ 'platform' ]
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
				xtype: 'optionscolumn',
                hidden: !Scalr.isAllowed('OPENSTACK_SNAPSHOTS', 'manage'),
				menu: [{
					text: 'Create new volume based on this snapshot',
					iconCls: 'x-menu-icon-create',
                    showAsQuickAction: true,
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
				}]
			}
		],

        selModel: Scalr.isAllowed('OPENSTACK_SNAPSHOTS', 'manage') ? 'selectedmodel' : null,
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
				itemId: 'delete',
				iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
				tooltip: 'Select one or more snapshots to delete them',
				disabled: true,
                hidden: !Scalr.isAllowed('OPENSTACK_SNAPSHOTS', 'manage'),
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
            }, ' ', {
                xtype: 'cloudlocationfield',
                platforms: [loadParams['platform']],
				gridStore: store
			}]
		}]
	});
});

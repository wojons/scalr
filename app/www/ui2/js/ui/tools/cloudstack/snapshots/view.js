Scalr.regPage('Scalr.ui.tools.cloudstack.snapshots.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'snapshotId', 'volumeId', 'state', 'createdAt', 'volumeType', 'intervalType', 'type'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/cloudstack/snapshots/xListSnapshots/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: true,
			maximize: 'all',
            menuTitle: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Snapshots',
            //menuFavorite: true
		},
		store: store,
		stateId: 'grid-tools-cloudstack-volumes-view',
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
			{ header: "ID", flex: 1, dataIndex: 'snapshotId', sortable: true },
			{ header: "Type", flex: 1, dataIndex: 'type', sortable: true},
			{ header: "Volume ID", flex: 1, dataIndex: 'volumeId', sortable: true },
			{ header: "Volume type", width: 180, dataIndex: 'volumeType', sortable: true },
			{ header: "Status", width: 180, dataIndex: 'state', sortable: true },
			{ header: "Created at", width: 240, dataIndex: 'createdAt', sortable: true }
		],

        selModel: Scalr.isAllowed('CLOUDSTACK_SNAPSHOTS', 'manage') ? 'selectedmodel' : null,
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
				tooltip: 'Select one or more snapshot(s) to delete them',
				disabled: true,
                hidden: !Scalr.isAllowed('CLOUDSTACK_SNAPSHOTS', 'manage'),
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
						url: '/tools/cloudstack/snapshots/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('snapshotId'));
						request.confirmBox.objects.push(records[i].get('snapshotId'));
					}
					request.params = { snapshotId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation, platform: store.proxy.extraParams.platform };
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

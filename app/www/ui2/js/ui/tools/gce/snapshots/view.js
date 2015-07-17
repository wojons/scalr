Scalr.regPage('Scalr.ui.tools.gce.snapshots.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id','description','status','size','details','createdAt'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/gce/snapshots/xListSnapshots/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: true,
			maximize: 'all',
            menuTitle: 'GCE Snapshots',
            menuHref: '#/tools/gce/snapshots',
            menuFavorite: true
        },
		store: store,
		stateId: 'grid-tools-gce-snapshots-view',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams',
            forbiddenFilters: ['platform']
        }],

		viewConfig: {
			emptyText: 'No snapshots found',
			loadingText: 'Loading snapshots ...'
		},

		columns: [
			{ header: "Snapshot", flex: 1, dataIndex: 'id', sortable: true },
			{ header: "Description", flex: 1, dataIndex: 'description', sortable: true },
			{ header: "Date", width: 175, dataIndex: 'createdAt', sortable: true },
			{ header: "Size (GB)", width: 110, dataIndex: 'size', sortable: true },
			{ header: "Status", width: 180, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				'{status}'
			}
		],

        selModel: 'selectedmodel',
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
				tooltip: 'Select one or more volumes to delete them',
				disabled: true,
				handler: function() {
					var me = this,
                        request = {
                            confirmBox: {
                                msg: 'Delete selected snapshot(s): %s ?',
                                type: 'delete'
                            },
                            processBox: {
                                msg: 'Deleting snapshot(s) ...',
                                type: 'delete'
                            },
                            url: '/tools/gce/snapshots/xRemove/',
                            success: function() {
                                me.up('grid').getSelectionModel().deselectAll();
                                store.load();
                            }
                        }, records = me.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('id'));
					}
					request.params = { snapshotId: Ext.encode(data)/*, cloudLocation: store.proxy.extraParams.cloudLocation */};
					Scalr.Request(request);
				}
			}],
			items: [{
                xtype: 'filterfield',
                store: store
            }/*, ' ', {
                xtype: 'cloudlocationfield',
                platforms: ['gce'],
                locations: moduleParams.locations,
				gridStore: store
			}*/]
		}]
	});
});

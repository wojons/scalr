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
		title: 'Tools &raquo; GCE &raquo; Snapshots',
		scalrOptions: {
			'reload': true,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-tools-gce-snapshots-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'GCE Snapshots',
				href: '#/tools/gce/snapshots'
			}
		}],

		viewConfig: {
			emptyText: 'No snapshots found',
			loadingText: 'Loading snapshots ...'
		},

		columns: [
			{ header: "Name", width: 400, dataIndex: 'id', sortable: true },
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
							msg: 'Are you sure want to delete snapshot "{snapshotId}"?'
						},
						processBox: {
							type: 'delete',
							msg: 'Deleting snapshots(s) ...'
						},
						url: '/tools/gce/snapshots/xRemove/',
						dataHandler: function (data) {
							return { 
								snapshotId: Ext.encode([data['id']])/*,
								cloudLocation: store.proxy.extraParams.cloudLocation*/
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
							msg: 'Delete selected snapshot(s): %s ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting snapshot(s) ...',
							type: 'delete'
						},
						url: '/tools/gce/snapshots/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

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
            }/*{
				xtype: 'fieldcloudlocation',
				itemId: 'cloudLocation',
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
				gridStore: store,
				cloudLocation: loadParams['cloudLocation'] || ''
			}*/]
		}]
	});
});

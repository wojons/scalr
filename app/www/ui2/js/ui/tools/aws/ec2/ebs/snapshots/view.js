Scalr.regPage('Scalr.ui.tools.aws.ec2.ebs.snapshots.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'snapshotId', 'volumeId', 'volumeSize', 'status', 'startTime', 'comment', 'progress', 'owner','volumeSize' ],
		proxy: {
			type: 'scalr.paging',
			extraParams: loadParams,
			url: '/tools/aws/ec2/ebs/snapshots/xListSnapshots/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Tools &raquo; Amazon Web Services &raquo; EC2 &raquo; EBS &raquo; Snapshots',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		scalrReconfigureParams: { volumeId: '', snapshotId: '' },
		store: store,
		stateId: 'grid-tools-aws-ec2-ebs-snapshots-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'EBS Snapshots',
				href: '#/tools/aws/ec2/ebs/snapshots'
			}
		}],

		viewConfig: {
			emptyText: "No snapshots found",
			loadingText: 'Loading snapshots ...'
		},

		columns: [
			{ header: "Snapshot ID", width: 150, dataIndex: 'snapshotId', sortable: true },
			{ header: "Owner", width: 150, dataIndex: 'owner', sortable: true },
			{ header: "Created on", width: 100, dataIndex: 'volumeId', sortable: true },
			{ header: "Size (GB)", width: 100, dataIndex: 'volumeSize', sortable: true },
			{ header: "Status", width: 120, dataIndex: 'status', sortable: true },
			{ header: "Local start time", width: 150, dataIndex: 'startTime', sortable: true },
			{ header: "Completed", width: 100, dataIndex: 'progress', sortable: false, align:'center', xtype: 'templatecolumn', tpl: '{progress}%' },
			{ header: "Comment", flex: 1, dataIndex: 'comment', sortable: true, xtype: 'templatecolumn', tpl: '<tpl if="comment">{comment}</tpl>' },
			{
				xtype: 'optionscolumn',
				optionsMenu: [{
					itemId: 'option.create',
					text: 'Create new volume based on this snapshot',
					iconCls: 'x-menu-icon-create',
					menuHandler: function(menuItem) {
						Scalr.event.fireEvent('redirect','#/tools/aws/ec2/ebs/volumes/create?' +
							Ext.Object.toQueryString({
								'snapshotId': menuItem.record.get('snapshotId'),
								'size': menuItem.record.get('volumeSize'),
								'cloudLocation': store.proxy.extraParams.cloudLocation
							})
						);
					}
				}, {
					xtype: 'menuseparator',
					itemId: 'option.Sep'
				}, {
					itemId: 'option.delete',
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
					request: {
						confirmBox: {
							type: 'delete',
							msg: 'Are you sure want to delete EBS snapshot "{snapshotId}"?'
						},
						processBox: {
							type: 'delete',
							msg: 'Deleting EBS snapshot ...'
						},
						url: '/tools/aws/ec2/ebs/snapshots/xRemove/',
						dataHandler: function (record) {
							return { snapshotId: Ext.encode([record.get('snapshotId')]), cloudLocation: store.proxy.extraParams.cloudLocation };
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
			selType: 'selectedmodel',
			selectedMenu: [{
				text: 'Delete',
				iconCls: 'x-menu-icon-delete',
				request: {
					confirmBox: {
						msg: 'Delete selected EBS snapshot(s): %s ?',
						type: 'delete'
					},
					processBox: {
						msg: 'Deleting selected EBS snapshot(s) ...',
						type: 'delete'
					},
					url: '/tools/aws/ec2/ebs/snapshots/xRemove/',
					dataHandler: function (records) {
						var data = [];
						this.confirmBox.objects = [];
						for (var i = 0, len = records.length; i < len; i++) {
							data.push(records[i].get('snapshotId'));
							this.confirmBox.objects.push(records[i].get('snapshotId'));
						}

						return { snapshotId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
					},
					success: function (data) {
						store.load();
					}
				}
			}]
		},

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			items: [{
				xtype: 'tbfilterfield',
				store: store
			}, ' ',{
				xtype: 'fieldcloudlocation',
				itemId: 'cloudLocation',
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
				gridStore: store,
				cloudLocation: loadParams['cloudLocation'] || ''
			}, ' ', {
				xtype: 'button',
				enableToggle: true,
				width: 220,
				text: 'Show public (Shared) snapshots',
				toggleHandler: function (field, checked) {
					store.proxy.extraParams.showPublicSnapshots = checked ? 'true' : 'false';
					store.loadPage(1);
				}
			}]
		}]
	});
});

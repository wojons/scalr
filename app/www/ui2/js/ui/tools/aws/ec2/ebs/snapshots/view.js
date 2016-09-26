Scalr.regPage('Scalr.ui.tools.aws.ec2.ebs.snapshots.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'snapshotId', 'volumeId', 'volumeSize', 'status', 'startTime', 'comment', 'progress', 'owner','volumeSize' ],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/aws/ec2/ebs/snapshots/xListSnapshots/'
		},
        sorters: [{
            property: 'snapshotId',
            direction: 'DESC'
        }],
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'EBS Snapshots',
            menuHref: '#/tools/aws/ec2/ebs/snapshots',
            menuFavorite: true
		},
		store: store,
		stateId: 'grid-tools-aws-ec2-ebs-snapshots-view',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],

		viewConfig: {
			emptyText: "No snapshots found",
			loadingText: 'Loading snapshots ...'
		},

		columns: [
			{ header: "Snapshot ID", width: 150, dataIndex: 'snapshotId', sortable: true },
			{ header: "Owner", width: 150, dataIndex: 'owner', sortable: true },
			{ header: "Created on", width: 120, dataIndex: 'volumeId', sortable: true },
			{ header: "Size (GB)", width: 110, dataIndex: 'volumeSize', sortable: true },
			{ header: "Status", width: 120, dataIndex: 'status', sortable: true },
			{ header: "Local start time", width: 180, dataIndex: 'startTime', sortable: true },
			{ header: "Completed", width: 100, dataIndex: 'progress', sortable: false, align:'center', xtype: 'templatecolumn', tpl: '{progress}%' },
			{ header: "Comment", flex: 1, dataIndex: 'comment', sortable: true, xtype: 'templatecolumn', tpl: '<tpl if="comment">{comment}</tpl>' },
			{
				xtype: 'optionscolumn',
				menu: [{
					text: 'Create new volume based on this snapshot',
					iconCls: 'x-menu-icon-create',
                    showAsQuickAction: true,
                    getVisibility: function(data) {
                        return Scalr.isAllowed('AWS_VOLUMES', 'manage');
                    },
					menuHandler: function(data) {
						Scalr.event.fireEvent('redirect','#/tools/aws/ec2/ebs/volumes/create?' +
							Ext.Object.toQueryString({
								'snapshotId': data['snapshotId'],
								'size': data['volumeSize'],
								'cloudLocation': store.proxy.extraParams.cloudLocation
							})
						);
					}
				}, {
					iconCls: 'x-menu-icon-fork',
					text: 'Copy to another EC2 region',
                    showAsQuickAction: true,
                    getVisibility: function(data) {
                        return Scalr.isAllowed('AWS_SNAPSHOTS', 'manage');
                    },
					request: {
						processBox: {
							type:'action'
						},
						url: '/tools/aws/ec2/ebs/snapshots/xGetMigrateDetails/',
						dataHandler: function (data) {
							return {
								'snapshotId': data['snapshotId'],
								'cloudLocation': store.proxy.extraParams.cloudLocation
							};
						},
						success: function (data) {
							Scalr.Request({
								confirmBox: {
									type: 'action',
									msg: 'Copying snapshots allows you to use them in additional regions',
									formWidth: 700,
									form: [{
										xtype: 'fieldset',
										title: 'Region copy',
										fieldDefaults: {
											labelWidth: 150
										},
										items: [{
											xtype: 'displayfield',
											width: 500,
											fieldLabel: 'Snpashot ID',
											value: data['snapshotId']
										},{
											xtype: 'displayfield',
											width: 500,
											fieldLabel: 'Source region',
											value: data['sourceRegion']
										}, {
											xtype: 'combo',
											fieldLabel: 'Destination region',
											plugins: {
							                    ptype: 'fieldinnericoncloud',
							                    platform: 'ec2'
							                },
											store: {
												fields: [ 'cloudLocation', 'name' ],
												proxy: 'object',
												data: data['availableDestinations']
											},
											autoSetValue: true,
											valueField: 'cloudLocation',
											displayField: 'name',
											editable: false,
											queryMode: 'local',
											name: 'destinationRegion',
											width: 500
										}]
									}]
								},
								processBox: {
									type: 'action'
								},
								url: '/tools/aws/ec2/ebs/snapshots/xMigrate',
								params: {snapshotId: data.snapshotId, sourceRegion: data.sourceRegion},
								success: function (data) {
                                    Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/ebs/snapshots/' + data.data.snapshotId + '/view?cloudLocation=' + data.data.cloudLocation);
								}
							});
						}
					}
				}]
			}
		],

		selModel: Scalr.isAllowed('AWS_SNAPSHOTS', 'manage') ? 'selectedmodel' : null,
		listeners: {
			selectionchange: function(selModel, selections) {
				var toolbar = this.down('scalrpagingtoolbar');
				toolbar.down('#delete').setDisabled(!selections.length);
			}
		},

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			afterItems: [{
				itemId: 'delete',
				disabled: true,
				iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
				tooltip: 'Delete',
                hidden: !Scalr.isAllowed('AWS_SNAPSHOTS', 'manage'),
				handler: function() {
					var request = {
						confirmBox: {
							type: 'delete',
							msg: 'Delete selected EBS snapshot(s): %s ?'
						},
						processBox: {
							msg: 'Deleting EBS snapshot(s) ...',
							type: 'delete'
						},
						url: '/tools/aws/ec2/ebs/snapshots/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('snapshotId'));
						request.confirmBox.objects.push(records[i].get('snapshotId'))
					}
					request.params = { snapshotId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
					Scalr.Request(request);
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}, ' ',{
                xtype: 'cloudlocationfield',
                platforms: ['ec2'],
				gridStore: store
			}, ' ', {
				xtype: 'buttonfield',
                name: 'showPublicSnapshots',
				enableToggle: true,
				width: 260,
				text: 'Show public (Shared) snapshots',
				toggleHandler: function (me) {
					store.applyProxyParams({
						showPublicSnapshots: me.getValue()
					});
				}
			}]
		}]
	});
});

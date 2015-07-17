Scalr.regPage('Scalr.ui.tools.aws.ec2.ebs.volumes.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{name: 'farmId', defaultValue: null}, 'farmRoleId', 'farmName', 'roleName', 'mysql_master_volume', {name: 'mountStatus', defaultValue: null}, 'serverIndex', 'serverId',
			'volumeId', 'size', 'snapshotId', 'availZone', 'status', 'attachmentStatus', 'device', 'instanceId', 'autoSnaps', {name: 'autoAttach', defaultValue: null}, 'type'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/aws/ec2/ebs/volumes/xListVolumes/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'EBS Volumes',
            menuHref: '#/tools/aws/ec2/ebs/volumes',
            menuFavorite: true
		},
		store: store,
		stateId: 'grid-tools-aws-ec2-ebs-volumes-view',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],

		viewConfig: {
			emptyText: 'No volumes found',
			loadingText: 'Loading volumes ...'
		},

		columns: [
			{ header: "Used by", flex: 1, dataIndex: 'id', sortable: true, xtype: 'templatecolumn',
				multiSort: function (st, direction) {
                    st.sort([{
                        property: 'farmId',
                        direction: direction
                    }, {
                        property: 'farmRoleId',
                        direction: direction
                    }, {
                        property: 'serverIndex',
                        direction: direction
                    }]);
                }, tpl:
				'<tpl if="farmId">' +
					'<a href="#/farms?farmId={farmId}" title="Farm {farmName}">{farmName}</a>' +
					'<tpl if="roleName">' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {roleName}">' +
						'{roleName}</a> #<a href="#/servers?serverId={serverId}">{serverIndex}</a>' +
					'</tpl>' +
				'</tpl>' +
				'<tpl if="!farmId">&mdash;</tpl>'
			},
			{ header: "Volume ID", width: 120, dataIndex: 'volumeId', sortable: true },
			{ header: "Size (GB)", width: 110, dataIndex: 'size', sortable: true },
			{ header: "Type", width: 100, dataIndex: 'type', sortable: true },
			{ header: "Snapshot ID", width: 35, dataIndex: 'snapshotId', sortable: true, hidden: true },
			{ header: "Placement", width: 110, dataIndex: 'availZone', sortable: true },
			{ header: "Status", width: 250, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				'{status}' +
				'<tpl if="attachmentStatus"> / {attachmentStatus}</tpl>' +
				'<tpl if="device"> ({device})</tpl>'
			},
			{ header: "Mount status", width: 110, dataIndex: 'mountStatus', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="mountStatus">{mountStatus}</tpl>' +
				'<tpl if="!mountStatus">&mdash;</tpl>'
			},
			{ header: "Instance ID", width: 120, dataIndex: 'instanceId', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="instanceId">{instanceId}</tpl>'
			},
			{ header: "Auto-snaps", width: 110, dataIndex: 'autoSnaps', sortable: false, align:'center', xtype: 'templatecolumn', tpl:
				'<tpl if="autoSnaps"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div></tpl>' +
				'<tpl if="!autoSnaps">&mdash;</tpl>'
			},
			{ header: "Auto-attach", width: 130, dataIndex: 'autoAttach', sortable: false, align:'center', xtype: 'templatecolumn', tpl:
				'<tpl if="autoAttach"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div></tpl>' +
				'<tpl if="!autoAttach">&mdash;</tpl>'
			}, {
				xtype: 'optionscolumn',
				getVisibility: function (record) {
					return record.get('status') !== 'deleting' && record.get('status') !== 'deleted'
				},
				menu: [{
					text: 'CloudWatch statistics',
					iconCls: 'x-menu-icon-statsload',
                    showAsQuickAction: true,
					menuHandler: function (data) {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/cloudwatch/view?objectId=' + data['volumeId'] + '&object=VolumeId&namespace=AWS/EBS&region=' + store.proxy.extraParams.cloudLocation);
					}
				},{
					iconCls: 'x-menu-icon-attach',
					text: 'Attach',
                    showAsQuickAction: true,
					menuHandler: function(data) {
						Scalr.event.fireEvent('redirect', "#/tools/aws/ec2/ebs/volumes/" + data['volumeId'] + "/attach?cloudLocation=" + store.proxy.extraParams.cloudLocation);
					},
                    getVisibility: function(data) {
                        return !data['mysqMasterVolume'] && !data['instanceId'];
                    }
				},{
					iconCls: 'x-menu-icon-detach',
					text: 'Detach',
                    showAsQuickAction: true,
                    getVisibility: function(data) {
                        return !data['mysqMasterVolume'] && data['instanceId'];
                    },
					request: {
						confirmBox: {
							type: 'action',
							//TODO: Add form: checkbox: forceDetach
							msg: 'Are you sure want to detach "{volumeId}" volume?'
						},
						processBox: {
							type: 'action',
							msg: 'Detaching EBS volume ...'
						},
						url: '/tools/aws/ec2/ebs/volumes/xDetach/',
						dataHandler: function (data) {
							return { volumeId: data['volumeId'], cloudLocation: store.proxy.extraParams.cloudLocation };
						},
						success: function (data) {
							store.load();
						}
					}
				},{
					xtype: 'menuseparator'
				},{
					text: 'Auto-snapshot settings',
					/*
					getVisibility: function() {
                        return Scalr.flags['showDeprecatedFeatures'];
                    },
                    */
					iconCls: 'x-menu-icon-autosnapshotsettings',
					menuHandler: function(data) {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/autoSnapshotSettings?type=ebs&objectId=' + data['volumeId'] + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
					}
				}, {
					xtype: 'menuseparator'
				}, {
					text: 'Create snapshot',
					iconCls: 'x-menu-icon-create',
					request: {
						confirmBox: {
							type: 'action',
							msg: 'Are you sure want to create snapshot for EBS volume "{volumeId}"?'
						},
						processBox: {
							type: 'action',
							msg: 'Creating EBS snapshot ...'
						},
						url: '/tools/aws/ec2/ebs/snapshots/xCreate/',
						dataHandler: function (data) {
							return { volumeId: data['volumeId'], cloudLocation: store.proxy.extraParams.cloudLocation };
						},
						success: function (data) {
                            Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/ebs/snapshots/' + data.data.snapshotId + '/view?cloudLocation=' + store.proxy.extraParams.cloudLocation);
						}
					}
				}, {
					text: 'Snapshots',
                    showAsQuickAction: true,
					iconCls: 'x-menu-icon-createserversnapshot',
					menuHandler: function(data) {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/ebs/snapshots?volumeId=' + data['volumeId'] + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
					}
				}, {
					xtype: 'menuseparator'
				}, {
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
					request: {
						confirmBox: {
							type: 'delete',
							msg: 'Are you sure want to delete EBS volume "{volumeId}"?'
						},
						processBox: {
							type: 'delete',
							msg: 'Deleting EBS volume ...'
						},
						url: '/tools/aws/ec2/ebs/volumes/xRemove/',
						dataHandler: function (data) {
							return { volumeId: Ext.encode([data['volumeId']]), cloudLocation: store.proxy.extraParams.cloudLocation };
						},
						success: function () {
							store.load();
						}
					}
				}]
			}
		],

		selModel: {
			selType: 'selectedmodel',
			getVisibility: function(record) {
				return record.get('status') == 'deleting' ? false : true;
			}
		},

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
			beforeItems: [{
                text: 'New volume',
                cls: 'x-btn-green',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/ebs/volumes/create');
				}
			}],
			afterItems: [{
				itemId: 'delete',
				disabled: true,
				iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
				tooltip: 'Delete',
				handler: function() {
					var request = {
						confirmBox: {
							type: 'delete',
							msg: 'Delete selected EBS volume(s): %s ?'
						},
						processBox: {
							type: 'delete'
						},
						url: '/tools/aws/ec2/ebs/volumes/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), volumes = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						volumes.push(records[i].get('volumeId'));
						request.confirmBox.objects.push(records[i].get('volumeId'))
					}
					request.params = { volumeId: Ext.encode(volumes), cloudLocation: store.proxy.extraParams.cloudLocation };
					Scalr.Request(request);
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}, ' ', {
                xtype: 'cloudlocationfield',
                platforms: ['ec2'],
				gridStore: store
			}]
		}]
	});
});

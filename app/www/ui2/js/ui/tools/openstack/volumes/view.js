Scalr.regPage('Scalr.ui.tools.openstack.volumes.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'farmId', 'farmRoleId', 'farmName', 'roleName', 'mysql_master_volume', 'mountStatus', 'serverIndex', 'serverId',
			'volumeId', 'size', 'type', 'availability_zone', 'status', 'attachmentStatus', 'device', 'instanceId', 'autoSnaps', 'autoAttach', 'attachmentId'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/openstack/volumes/xListVolumes/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Volumes',
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
				text: 'Openstack Volumes',
				href: '#/tools/openstack/volumes?platform=' + loadParams['platform']
			}
		}],

		viewConfig: {
			emptyText: 'No volumes found',
			loadingText: 'Loading volumes ...'
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
			{ header: "Volume ID", width: 260, dataIndex: 'volumeId', sortable: true },
			{ header: "Size (GB)", width: 110, dataIndex: 'size', sortable: true },
			{ header: "Type", width: 150, dataIndex: 'type', sortable: true},
			{ header: "Zone", width: 90, dataIndex: 'availability_zone', sortable: true },
			{ header: "Status", width: 180, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				'{status}' +
				'<tpl if="attachmentStatus"> / {attachmentStatus}</tpl>' +
				'<tpl if="device"> ({device})</tpl>'
			},
			{ header: "Instance ID", width: 260, dataIndex: 'instanceId', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="instanceId">{instanceId}</tpl>'
			}, {
				xtype: 'optionscolumn2',
				menu: [{
					itemId: 'option.createSnap',
					text: 'Create snapshot',
					iconCls: 'x-menu-icon-create',
					request: {
						confirmBox: {
							type: 'action',
							msg: 'Are you sure want to create snapshot for Cinder volume "{volumeId}"?'
						},
						processBox: {
							type: 'action',
							msg: 'Creating snapshot ...'
						},
						url: '/tools/openstack/snapshots/xCreate/',
						dataHandler: function (data) {
							return { 
								volumeId: data['volumeId'], 
								cloudLocation: store.proxy.extraParams.cloudLocation,
								platform: loadParams['platform']
							};
						},
						success: function (data) {
							document.location.href = '#/tools/openstack/snapshots/' + data.data.snapshotId + '/view?cloudLocation=' + store.proxy.extraParams.cloudLocation + '&platform=' + loadParams['platform'];
						}
					}
				}, {
					itemId: 'option.attach',
					iconCls: 'x-menu-icon-attach',
					text: 'Attach',
					menuHandler: function(data) {
						document.location.href = "#/tools/openstack/volumes/" + data['volumeId'] + "/attach?cloudLocation=" + store.proxy.extraParams.cloudLocation + '&platform=' + loadParams['platform'];
					},
                    getVisibility: function(data) {
                        return !data['instanceId'];
                    }
				}, {
					itemId: 'option.detach',
					iconCls: 'x-menu-icon-detach',
					text: 'Detach',
					request: {
						confirmBox: {
							type: 'action',
							//TODO: Add form: checkbox: forceDetach
							msg: 'Are you sure want to detach "{volumeId}" volume?'
						},
						processBox: {
							type: 'action',
							msg: 'Detaching cinder volume ...'
						},
						url: '/tools/openstack/volumes/xDetach/',
						dataHandler: function (data) {
							return { 
								serverId: data['instanceId'],
								attachmentId: data['attachmentId'],
								platform: store.proxy.extraParams.platform,
								cloudLocation: store.proxy.extraParams.cloudLocation 
							};
						},
						success: function (data) {
							store.load();
						}
					},
                    getVisibility: function(data) {
                        return data['instanceId'];
                    }
				}, {
					itemId: 'option.delete',
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
					request: {
						confirmBox: {
							type: 'delete',
							msg: 'Are you sure want to delete Volume "{volumeId}"?'
						},
						processBox: {
							type: 'delete',
							msg: 'Deleting volume(s) ...'
						},
						url: '/tools/openstack/volumes/xRemove/',
						dataHandler: function (data) {
							return { 
								volumeId: Ext.encode([data['volumeId']]),
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
						url: '/tools/openstack/volumes/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('volumeId'));
						request.confirmBox.objects.push(records[i].get('volumeId'));
					}
					request.params = { platform: loadParams['platform'], volumeId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
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

Scalr.regPage('Scalr.ui.tools.cloudstack.volumes.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'farmId', 'farmRoleId', 'farmName', 'roleName', 'mysql_master_volume', 'mountStatus', 'serverIndex', 'serverId',
			'volumeId', 'size', 'type', 'storage', 'status', 'attachmentStatus', 'device', 'instanceId', 'autoSnaps', 'autoAttach'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/cloudstack/volumes/xListVolumes/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: true,
			maximize: 'all',
            menuTitle: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Volumes',
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
			emptyText: 'No volumes found',
			loadingText: 'Loading volumes ...'
		},

		columns: [
			{ header: "Used by", flex: 1, dataIndex: 'farmId', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="farmId">' +
					'<a href="#/farms?farmId={farmId}" title="Farm {farmName}">{farmName}</a>' +
					'<tpl if="roleName">' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {roleName}">' +
						'{roleName}</a> #<a href="#/servers?serverId={serverId}">{serverIndex}</a>' +
					'</tpl>' +
				'</tpl>' +
				'<tpl if="!farmId">&mdash;</tpl>'
			},
			{ header: "Volume ID", width: 110, dataIndex: 'volumeId', sortable: true },
			{ header: "Size (GB)", width: 110, dataIndex: 'size', sortable: true },
			{ header: "Type", width: 150, dataIndex: 'type', sortable: true},
			{ header: "Storage", width: 120, dataIndex: 'storage', sortable: true },
			{ header: "Status", width: 180, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				'{status}' +
				'<tpl if="attachmentStatus"> / {attachmentStatus}</tpl>' +
				'<tpl if="device"> ({device})</tpl>'
			},
			{ header: "Mount status", width: 110, dataIndex: 'mountStatus', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="mountStatus">{mountStatus}</tpl>' +
				'<tpl if="!mountStatus">&mdash;</tpl>'
			},
			{ header: "Instance ID", width: 115, dataIndex: 'instanceId', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="instanceId">{instanceId}</tpl>'
			},
			{ header: "Auto-snaps", width: 110, dataIndex: 'autoSnaps', sortable: false, align:'center', xtype: 'templatecolumn', tpl:
				'<tpl if="autoSnaps"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div></tpl>' +
				'<tpl if="!autoSnaps">&mdash;</tpl>'
			},
			{ header: "Auto-attach", width: 130, dataIndex: 'autoAttach', sortable: false, align:'center', xtype: 'templatecolumn', tpl:
				'<tpl if="autoAttach"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div></tpl>' +
				'<tpl if="!autoAttach">&mdash;</tpl>'
			}
		],

        selModel: Scalr.isAllowed('CLOUDSTACK_VOLUMES', 'manage') ? 'selectedmodel' : null,
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
                tooltip: 'Select one or more volume(s) to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('CLOUDSTACK_VOLUMES', 'manage'),
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected volume(s): %s ?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Deleting volume(s) ...'
                        },
                        url: '/tools/cloudstack/volumes/xRemove/',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('volumeId'));
                        request.confirmBox.objects.push(records[i].get('volumeId'));
                    }
                    request.params = { volumeId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation, platform: store.proxy.extraParams.platform };
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

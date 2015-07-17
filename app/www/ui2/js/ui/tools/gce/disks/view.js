Scalr.regPage('Scalr.ui.tools.gce.disks.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id','description','status','size','snapshotId','createdAt',
            {name: 'farmId', defaultValue: null},
            {name: 'farmName', defaultValue: null},
            {name: 'roleName', defaultValue: null},
            {name: 'farmRoleId', defaultValue: null},
            {name: 'serverId', defaultValue: null},
            {name: 'serverIndex', defaultValue: null}
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/gce/disks/xListDisks/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: true,
			maximize: 'all',
            menuTitle: 'GCE Persistent disks',
            menuHref: '#/tools/gce/disks',
            menuFavorite: true
		},
		store: store,
		stateId: 'grid-tools-gce-disks-view',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams',
            forbiddenFilters: ['platform']
        }],

		viewConfig: {
			emptyText: 'No PDs found',
			loadingText: 'Loading persistent disks ...'
		},

		columns: [
			{ header: "Used by", flex: 1, dataIndex: 'id', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="farmId">' +
					'<a href="#/farms?farmId={farmId}" title="Farm {farmName}">{farmName}</a>' +
					'<tpl if="roleName">' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {roleName}">' +
						'{roleName}</a> #<a href="#/servers?serverId={serverId}">{serverIndex}</a>' +
					'</tpl>' +
				'</tpl>' +
				'<tpl if="!farmId">&mdash;</tpl>'
			},
			{ header: "Persistent disk", width: 260, dataIndex: 'id', sortable: true },
			{ header: "Description", width: 400, dataIndex: 'description', sortable: true },
			{ header: "Date", width: 170, dataIndex: 'createdAt', sortable: true },
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
					var request = {
						confirmBox: {
							msg: 'Delete selected volume(s): %s ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting volume(s) ...',
							type: 'delete'
						},
						url: '/tools/gce/disks/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('id'));
					}
					request.params = { diskId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
					Scalr.Request(request);
				}
			}],
			items: [{
                xtype: 'filterfield',
                store: store
            }, ' ', {
                xtype: 'cloudlocationfield',
                platforms: ['gce'],
                locations: moduleParams.locations,
				gridStore: store
			}]
		}]
	});
});

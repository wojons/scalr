Scalr.regPage('Scalr.ui.dm.applications.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id', 'name', 'source_id', 'source_url', 'used_on'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/dm/applications/xListApplications/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'Deployments Applications'
		},
		store: store,
		stateId: 'grid-dm-applications-view',
		stateful: true,
        plugins: [ 'gridstore', 'applyparams' ],

		viewConfig: {
			emptyText: 'No applications found',
			loadingText: 'Loading applications ...'
		},

		columns: [
			{ header: 'ID', width: 80, dataIndex: 'id', sortable: true },
			{ header: 'Name', flex: 1, dataIndex: 'name', sortable: true },
			{ header: 'Source', flex: 1, dataIndex: 'source_url', sortable: false, xtype: 'templatecolumn',
				tpl: '<a href="#/dm/sources/{source_id}/view">{source_url}</a>'
			},
			{ header: 'Status', width: 120, dataIndex: 'status', sortable: false, xtype: 'templatecolumn',
				tpl: '<tpl if="used_on != 0"><span style="color:green;">In use</span></tpl><tpl if="used_on == 0"><span style="color:gray;">Not used</span></tpl>'
			}, {
				xtype: 'optionscolumn',
				menu: [{
					text: 'Deploy',
					iconCls: 'x-menu-icon-launch',
                    showAsQuickAction: true,
					href: '#/dm/applications/{id}/deploy'
				}, {
					text: 'Edit',
					iconCls: 'x-menu-icon-edit',
                    showAsQuickAction: true,
					href: '#/dm/applications/{id}/edit'
				}, {
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
                    showAsQuickAction: true,
					request: {
						confirmBox: {
							msg: 'Are you sure want to remove deployment "{name}"?',
							type: 'delete'
						},
						processBox: {
							type: 'delete',
							msg: 'Removing demployment ...'
						},
						url: '/dm/applications/xRemoveApplications',
						dataHandler: function (data) {
							return {
								applicationId: data['id']
							};
						},
						success: function(data) {
							store.load();
						}
					}
				}]
			}
		],

		dockedItems: [{
            dock: 'top',
			xtype: 'displayfield',
			cls: 'x-form-field-warning x-form-field-warning-fit',
			value: Scalr.strings['deprecated_warning']
        },{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			beforeItems: [{
                text: 'Add application',
                cls: 'x-btn-green',
				handler: function() {
					Scalr.event.fireEvent('redirect','#/dm/applications/create');
				}
			}],
            items: [{
                xtype: 'filterfield',
                store: store
            }]
		}]
	});
});

Scalr.regPage('Scalr.ui.dnszones.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{ name: 'id', type: 'int' },
			{ name: 'client_id', type: 'int' },
			'zone_name', 'status', 'role_name', 'farm_roleid', 'dtlastmodified', 'farm_id', 'farm_name'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/dnszones/xListZones/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'DNS Zones',
            menuHref: '#/dnszones',
            menuFavorite: true
		},
		store: store,
		stateId: 'grid-dnszones-view',
		stateful: true,
        plugins: [ 'gridstore', 'applyparams' ],

		viewConfig: {
			emptyText: 'No DNS zones found',
			loadingText: 'Loading DNS zones ...'
		},

		columns: [
			{ text: "Domain name", flex: 2, dataIndex: 'zone_name', sortable: true, xtype: 'templatecolumn',
				tpl: '<a target="_blank" href="http://{zone_name}">{zone_name}</a>'
			},
			{ text: "Assigned to", flex: 1, dataIndex: 'role_name', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="farm_id &gt; 0"><a href="#/farms?farmId={farm_id}" title="Farm {farm_name}">{farm_name}</a>' +
					'<tpl if="farm_roleid &gt; 0">&nbsp;&rarr;&nbsp;<a href="#/farms/{farm_id}/roles/{farm_roleid}/view" ' +
					'title="Role {role_name}">{role_name}</a></tpl>' +
				'</tpl>' +
				'<tpl if="farm_id == 0">&mdash;</tpl>'
			},
			{ text: "Last modified", width: 200, dataIndex: 'dtlastmodified', sortable: true, xtype: 'templatecolumn',
				tpl: '<tpl if="dtlastmodified">{dtlastmodified}</tpl><tpl if="! dtlastmodified">Never</tpl>'
			},
			{ text: "Status", minWidth: 130, width: 130, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'dnszone'},
            {
				xtype: 'optionscolumn',
                hidden: !Scalr.isAllowed('DNS_ZONES', 'manage'),
				menu: [{
					text: 'Edit DNS Zone',
					iconCls: 'x-menu-icon-edit',
                    showAsQuickAction: true,
					href: '#/dnszones/{id}/edit'
				}, {
					text: 'Settings',
					iconCls: 'x-menu-icon-settings',
                    showAsQuickAction: true,
					href: '#/dnszones/{id}/settings'
				}],
				getVisibility: function (record) {
					return (record.get('status') != 'Pending delete' && record.get('status') != 'Pending create');
				}
			}
		],

		selModel:
            Scalr.isAllowed('DNS_ZONES', 'manage') ? {
                selType: 'selectedmodel',
                getVisibility: function (record) {
                    return (record.get('status') != 'Pending delete');
                }
            } : null,

		listeners: {
			selectionchange: function(selModel, selections) {
				this.down('scalrpagingtoolbar').down('#delete').setDisabled(!selections.length);
			}
		},

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			beforeItems: [{
                text: 'New DNS zone',
                cls: 'x-btn-green',
                hidden: !Scalr.isAllowed('DNS_ZONES', 'manage'),
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/dnszones/create');
				}
			}],
			afterItems: [{
				itemId: 'delete',
				iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
				tooltip: 'Select one or more zones to delete them',
				disabled: true,
                hidden: !Scalr.isAllowed('DNS_ZONES', 'manage'),
				handler: function() {
                    var grid = this.up('grid'),
                        request = {
						confirmBox: {
							msg: 'Remove selected zone(s): %s ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Removing dns zone(s) ...',
							type: 'delete'
						},
						url: '/dnszones/xRemoveZones',
						success: function() {
                            grid.getSelectionModel().deselectAll();
							store.load();
						}
					}, records = grid.getSelectionModel().getSelection(), zones = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						zones.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('zone_name'));
					}
					request.params = { zones: Ext.encode(zones) };
					Scalr.Request(request);
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}, ' ', {
				text: 'Default Records',
				tooltip: 'Manage Default DNS records',
                hidden: !Scalr.isAllowed('DNS_ZONES', 'manage'),
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/dnszones/defaultRecords');
				}
			}]
		}]
	});
});

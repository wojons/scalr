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
		title: 'DNS Zones &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-dnszones-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		},  {
			xtype: 'favoritetool',
			favorite: {
				text: 'DNS zones',
				href: '#/dnszones/view'
			}
		}],

		viewConfig: {
			emptyText: 'No DNS zones found',
			loadingText: 'Loading DNS zones ...'
		},

		columns: [
			{ text: "Domain name", flex: 2, dataIndex: 'zone_name', sortable: true, xtype: 'templatecolumn',
				tpl: '<a target="_blank" href="http://{zone_name}">{zone_name}</a>'
			},
			{ text: "Assigned to", flex: 1, dataIndex: 'role_name', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="farm_id &gt; 0"><a href="#/farms/{farm_id}/view" title="Farm {farm_name}">{farm_name}</a>' +
					'<tpl if="farm_roleid &gt; 0">&nbsp;&rarr;&nbsp;<a href="#/farms/{farm_id}/roles/{farm_roleid}/view" ' +
					'title="Role {role_name}">{role_name}</a></tpl>' +
				'</tpl>' +
				'<tpl if="farm_id == 0"><img src="/ui2/images/icons/false.png" /></tpl>'
			},
			{ text: "Last modified", width: 200, dataIndex: 'dtlastmodified', sortable: true, xtype: 'templatecolumn',
				tpl: '<tpl if="dtlastmodified">{dtlastmodified}</tpl><tpl if="! dtlastmodified">Never</tpl>'
			},
			{ text: "Status", minWidth: 130, width: 130, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'dnszone'},
            {
				xtype: 'optionscolumn2',
				menu: [{
					text: 'Edit DNS Zone',
					iconCls: 'x-menu-icon-edit',
					href: '#/dnszones/{id}/edit'
				}, {
					text: 'Settings',
					iconCls: 'x-menu-icon-settings',
					href: '#/dnszones/{id}/settings'
				}],
				getVisibility: function (record) {
					return (record.get('status') != 'Pending delete' && record.get('status') != 'Pending create');
				}
			}
		],

		multiSelect: true,
		selModel: {
			selType: 'selectedmodel',
			getVisibility: function (record) {
				return (record.get('status') != 'Pending delete');
			}
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
			beforeItems: [{
                text: 'Add DNS zone',
                cls: 'x-btn-green-bg',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/dnszones/create');
				}
			}],
			afterItems: [{
				ui: 'paging',
				itemId: 'delete',
				iconCls: 'x-tbar-delete',
				tooltip: 'Select one or more zones to delete them',
				disabled: true,
				handler: function() {
					var request = {
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
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), zones = [];

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
				width: 120,
				tooltip: 'Manage Default DNS records',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/dnszones/defaultRecords');
				}
			}]
		}]
	});
});

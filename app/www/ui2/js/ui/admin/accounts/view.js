Scalr.regPage('Scalr.ui.admin.accounts.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{name: 'id', type: 'int'}, 
			'name', 'dtadded', 'status', 'servers', 'users', 'envs', 'farms', 'limitEnvs', 'limitFarms', 'limitUsers', 'limitServers', 'ownerEmail', 'dnsZones', 'isTrial'
		],
		proxy: {
			type: 'scalr.paging',
			extraParams: loadParams,
			url: '/admin/accounts/xListAccounts'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Accounts &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		scalrReconfigureParams: { accountId: '' },
		store: store,
		stateId: 'grid-admin-accounts-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}],
		viewConfig: {
			emptyText: 'No accounts found',
			loadingText: 'Loading accounts ...'
		},

		columns: [
			{ header: "ID", width: 60, dataIndex: 'id', sortable: true },
			{ header: "Name", flex:1, dataIndex: 'name', sortable: true },
			{ header: "Owner email", flex: 1, dataIndex: 'ownerEmail', sortable: false },
			{ header: "Added", flex: 1, dataIndex: 'dtadded', sortable: true, xtype: 'templatecolumn',
				tpl: '{[values.dtadded ? values.dtadded : ""]}'
			},
			{ text: "Status", width: 100, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				new Ext.XTemplate('<span style="color: {[this.getClass(values.status)]}">{status} ({isTrial})</span>', {
					getClass: function (value) {
						if (value == 'Active')
							return "green";
						else if (value != 'Inactive')
							return "#666633";
						else
							return "red";
					}
				})
			},
			{ header: "Environments", width:  100, align:'center', dataIndex: 'envs', sortable: false, xtype: 'templatecolumn',
				tpl: '{envs}/{limitEnvs}'
			},
			{ header: "Users", width: 100, dataIndex: 'users', align:'center', sortable: false, xtype: 'templatecolumn',
				tpl: '{users}/{limitUsers}'
			},
			{ header: "Servers", width: 100, dataIndex: 'groups', align:'center', sortable: false, xtype: 'templatecolumn',
				tpl: '{servers}/{limitServers}'
			},
			{ header: "Farms", width: 100, dataIndex: 'farms', align:'center', sortable: false, xtype: 'templatecolumn',
				tpl: '{farms}/{limitFarms}'
			},
			{ header: "DNS Zones", width:  100, align:'center', dataIndex: 'dnsZones', sortable: false, xtype: 'templatecolumn',
				tpl: '{dnsZones}'
			},
			{
				xtype: 'optionscolumn',
				getOptionVisibility: function (item, record) {
					var data = record.data;

					return true;
				},

				optionsMenu: [{
					itemId: 'option.edit',
					iconCls: 'x-menu-icon-edit',
					text: 'Edit',
					href: "#/admin/accounts/{id}/edit"
				}, {
					itemId: 'option.login',
					iconCls: 'x-menu-icon-login',
					text: 'Login as owner',
					href: "/admin/accounts/{id}/loginAsOwner"
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
						type: 'delete',
						msg: 'Remove selected accounts(s): %s ?'
					},
					processBox: {
						type: 'delete',
						msg: 'Removing account(s)...'
					},
					url: '/admin/accounts/xRemove',
					dataHandler: function(records) {
						var accounts = [];
						this.confirmBox.objects = [];
						for (var i = 0, len = records.length; i < len; i++) {
							accounts.push(records[i].get('id'));
							this.confirmBox.objects.push(records[i].get('name'));
						}
						return { accounts: Ext.encode(accounts) };
					}
				}
			}]
		},

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			afterItems: [{
				ui: 'paging',
				iconCls: 'x-tbar-add',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/admin/accounts/create');
				}
			}],
			items: [{
				xtype: 'tbfilterfield',
				store: store
			}, ' ', {
				ui: 'paging',
				iconCls: 'x-tbar-info',
				tooltip: 'Filter: accountId or farm=farmId or owner=email or user=email or env=envId'
			}]
		}]
	});
});

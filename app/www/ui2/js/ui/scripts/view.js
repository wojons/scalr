Scalr.regPage('Scalr.ui.scripts.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{ name: 'id', type: 'int' },
			'name', 'description', 'origin',
			{ name: 'clientid', type: 'int' },
			'approval_state', 'dtupdated', 'client_email', 'version', 'client_name'
		],
		proxy: {
			type: 'scalr.paging',
			extraParams: loadParams,
			url: '/scripts/xListScripts/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Scripts &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		scalrReconfigureParams: {scriptId: '' },
		store: store,
		stateId: 'grid-scripts-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Scripts',
				href: '#/scripts/view'
			}
		}],

		viewConfig: {
			emptyText: 'No scripts defined',
			loadingText: 'Loading scripts ...',
			disableSelection: true
		},

		columns: [
			{ header: 'Author', flex: 1, dataIndex: 'id', sortable: false, xtype: 'templatecolumn', tpl: new Ext.XTemplate(
				'<tpl if="!this.isAdmin()">' +
					'<tpl if="clientid">' +
						'<tpl if="clientid == this.getClientId()">Me</tpl>' +
						'<tpl if="clientid != this.getClientId()">{client_name}</tpl>' +
					'</tpl>' +
					'<tpl if="!clientid">Scalr</tpl>' +
				'</tpl>' +
				'<tpl if="this.isAdmin()">' +
					'<tpl if="clientid">{client_name}</tpl>' +
					'<tpl if="!clientid">Scalr</tpl>' +
				'</tpl>', {
					getClientId: function() {
						return moduleParams['clientId']
					},
					isAdmin: function() {
						return moduleParams['isScalrAdmin']
					}
				})
			},
			{ header: 'Name', flex: 1, dataIndex: 'name', sortable: true },
			{ header: 'Description', flex: 2, dataIndex: 'description', sortable: true },
			{ header: 'Latest version', width: 100, dataIndex: 'version', sortable: false, align:'center' },
			{ header: 'Updated on', width: 160, dataIndex: 'dtupdated', sortable: true },
			{ header: 'Origin', width: 80, dataIndex: 'origin', sortable: false, align:'center', xtype: 'templatecolumn', tpl:
				'<tpl if="origin == &quot;Shared&quot;"><img src="/ui2/images/ui/scripts/default.png" height="16" title="Contributed by Scalr"></tpl>' +
				'<tpl if="origin == &quot;Custom&quot;"><img src="/ui2/images/ui/scripts/custom.png" height="16" title="Custom"></tpl>' +
				'<tpl if="origin != &quot;Shared&quot; && origin != &quot;Custom&quot;"><img src="/ui2/images/ui/scripts/contributed.png" height="16" title="Contributed by {client_name}"></tpl>'
			},
			{ header: 'Approved', width: 80, dataIndex: 'approval_state', sortable: false, align:'center', xtype: 'templatecolumn', tpl:
				'<tpl if="approval_state == &quot;Approved&quot; || !approval_state"><img src="/ui2/images/icons/true.png" title="Approved" /></tpl>' +
				'<tpl if="approval_state == &quot;Pending&quot;"><img src="/ui2/images/ui/scripts/pending.gif" title="Pending" /></tpl>' +
				'<tpl if="approval_state == &quot;Declined&quot;"><img src="/ui2/images/icons/false.png" title="Declined" /></tpl>'
			}, {
				xtype: 'optionscolumn',
				getOptionVisibility: function (item, record) {
					var data = record.data;
					
					if (item.itemId == 'option.view') {
						return true;
					} else if (item.itemId == 'option.fork') {
						return true;
					} else {
						if (item.itemId == 'option.execute' || item.itemId == 'option.execSep') {
							if (moduleParams['isScalrAdmin'])
								return false;
							else
								return true;
						}
						if ((data.clientid != 0 && data.clientid == moduleParams['clientId']) || moduleParams['isScalrAdmin'])
							return true;
						else
							return false;
					}
				},

				optionsMenu: [{
					itemId: 'option.view',
					iconCls: 'x-menu-icon-view',
					text: 'View',
					href: '#/scripts/{id}/view'
				}, {
					itemId: 'option.execute',
					iconCls: 'x-menu-icon-execute',
					text: 'Execute',
					href: '#/scripts/{id}/execute'
				}, {
					xtype: 'menuseparator',
					itemId: 'option.execSep'
				}, {
					itemId: 'option.fork',
					text: 'Fork',
					iconCls: 'x-menu-icon-fork',
					request: {
						processBox: {
							type: 'action'
						},
						dataHandler: function (record) {
							this.url = '/scripts/' + record.get('id') + '/xFork';
						},
						success: function () {
							store.load();
						}
					}
				}, {
					itemId: 'option.edit',
					iconCls: 'x-menu-icon-edit',
					text: 'Edit',
					href: '#/scripts/{id}/edit'
				}, {
					itemId: 'option.delete',
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
					request: {
						confirmBox: {
							msg: 'Remove script "{name}"?',
							type: 'delete'
						},
						processBox: {
							type: 'delete',
							msg: 'Removing script ...'
						},
						dataHandler: function (record) {
							this.url = '/scripts/' + record.get('id') + '/xRemove';
						},
						success: function () {
							store.load();
						}
					}
				}]
			}
		],

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			afterItems: [{
				ui: 'paging',
				iconCls: 'x-tbar-add',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/scripts/create');
				}
			}],
			items: [{
				xtype: 'tbfilterfield',
				store: store
			}, ' ', {
				xtype: 'combo',
				fieldLabel: 'Moderation phase',
				labelWidth: 110,
				width: 230,
				store: [ ['','All'], ['Approved','Approved'], ['Declined','Declined'], ['Pending','Pending'] ],
				editable: false,
				value: '',
				queryMode: 'local',
				itemId: 'approvalState',
				listeners: {
					change: function(field, value) {
						store.proxy.extraParams.approvalState = value;
						store.loadPage(1);
					}
				}
			}, ' ', {
				xtype: 'combo',
				fieldLabel: 'Origin',
				labelWidth: 40,
				store: [ ['','All'], ['Shared','Shared'], ['Custom','Custom'], ['User-contributed','User-contributed'] ],
				editable: false,
				value: '',
				queryMode: 'local',
				itemId: 'origin',
				iconCls: 'no-icon',
				listeners:{
					change: function(field, value) {
						store.proxy.extraParams.origin = value;
						store.loadPage(1);
					}
				}
			}]
		}]
	});
});

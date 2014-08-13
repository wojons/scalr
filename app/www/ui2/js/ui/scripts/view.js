Scalr.regPage('Scalr.ui.scripts.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{ name: 'id', type: 'int' }, { name: 'accountId', type: 'int' },
			'name', 'description', 'dtCreated', 'dtChanged', 'version', 'isSync', 'os'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/scripts/xList'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Scripts &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
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
			loadingText: 'Loading scripts ...'
		},

		columns: [
			{ header: 'ID', width: 60, dataIndex: 'id', sortable: true },
			{ header: 'Name', flex: 1, dataIndex: 'name', sortable: true },
			{ header: 'Description', flex: 2, dataIndex: 'description', sortable: true },
            { header: 'Execution mode', width: 150, dataIndex: 'isSync', sortable: true, xtype: 'statuscolumn', statustype: 'script'},
			{ header: 'Latest version', width: 100, dataIndex: 'version', sortable: false, align:'center' },
			{ header: 'OS', width: 60, sortable: false, align:'center', xtype: 'templatecolumn', tpl:
				'<tpl if="os == &quot;linux&quot;"><img src="/ui2/images/ui/scripts/linux.png" height="15" title="Linux"></tpl>' +
				'<tpl if="os == &quot;windows&quot;"><img src="/ui2/images/ui/scripts/windows.png" height="15" title="Windows"></tpl>'
            }, { header: 'Added on', width: 160, dataIndex: 'dtCreated', sortable: true },
            { header: 'Updated on', width: 160, dataIndex: 'dtChanged', sortable: true
            }, {
				xtype: 'optionscolumn2',
				menu: [{
					itemId: 'option.view',
					iconCls: 'x-menu-icon-view',
					text: 'View',
					href: '#/scripts/{id}/view'
				}, {
					itemId: 'option.execute',
					iconCls: 'x-menu-icon-execute',
					text: 'Execute',
					href: '#/scripts/{id}/execute',
                    getVisibility: function() {
                        return Scalr.user.type !== 'ScalrAdmin' && Scalr.isAllowed('ADMINISTRATION_SCRIPTS', 'execute');
                    }
				}, {
					xtype: 'menuseparator',
					itemId: 'option.execSep'
				}, {
					itemId: 'option.fork',
					text: 'Fork',
					iconCls: 'x-menu-icon-fork',
                    getVisibility: function() {
                        return Scalr.user.type == 'ScalrAdmin' || Scalr.isAllowed('ADMINISTRATION_SCRIPTS', 'fork');
                    },
					menuHandler: function(data) {
						Scalr.Request({
							confirmBox: {
								formValidate: true,
                                formSimple: true,
								form: [{
									xtype: 'textfield',
									name: 'name',
									labelWidth: 110,
									fieldLabel: 'New script name',
									value: 'Custom ' + data['name'],
									allowBlank: false
								}],
								type: 'action',
								msg: 'Are you sure want to fork script "' + data['name'] + '" ?'
							},
							processBox: {
								type: 'action'
							},
							url: '/scripts/xFork',
							params: {
								scriptId: data['id']
							},
                            success: function () {
								store.load();
							}
						});
					}
				}, {
					itemId: 'option.edit',
					iconCls: 'x-menu-icon-edit',
					text: 'Edit',
					href: '#/scripts/{id}/edit',
                    getVisibility: function(data) {
                        return (Scalr.user.type == 'ScalrAdmin') || (Scalr.isAllowed('ADMINISTRATION_SCRIPTS', 'manage') && data['accountId']);
                    }
				}]
			}
		],

		multiSelect: true,
		selModel: {
			selType: 'selectedmodel',
			getVisibility: function(record) {
				return (Scalr.user.type == 'ScalrAdmin') || !!record.get('accountId');
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
                text: 'Add script',
                cls: 'x-btn-green-bg',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/scripts/create');
				}
			}],
			afterItems: [{
				ui: 'paging',
				itemId: 'delete',
				iconCls: 'x-tbar-delete',
				tooltip: 'Select one or more scripts to delete them',
				disabled: true,
				handler: function() {
					var request = {
						confirmBox: {
							msg: 'Remove selected script(s): %s ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Removing selected scripts(s) ...',
							type: 'delete'
						},
						url: '/scripts/xRemove',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('name'));
					}
					request.params = { scriptId: Ext.encode(data) };
					Scalr.Request(request);
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}, ' ', {
				xtype: 'buttongroupfield',
				fieldLabel: 'Owner',
				labelWidth: 45,
				hidden: (Scalr.user.type == 'ScalrAdmin'),
				value: '',
                name: 'origin',
				items: [{
					xtype: 'button',
					text: 'All',
					value: '',
					width: 70
				}, {
					xtype: 'button',
					text: 'Scalr',
					width: 70,
					value: 'Shared'
				}, {
					xtype: 'button',
					text: 'Private',
					width: 70,
					value: 'Custom'
				}],
				listeners: {
					change: function (field, value) {
						store.proxy.extraParams.origin = value;
						store.loadPage(1);
					}
				}
			}]
		}]
	});
});

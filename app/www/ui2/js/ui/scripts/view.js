Scalr.regPage('Scalr.ui.scripts.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{ name: 'id', type: 'int' }, { name: 'accountId', type: 'int' },
			'name', 'description', 'dtCreated', 'dtChanged', 'version', 'isSync', 'os', 'envId'
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
			{
                header: '<img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qclass="x-tip-light" data-qtip="' +
                Ext.String.htmlEncode('<div>Scopes:</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr">&nbsp;&nbsp;Scalr</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-environment">&nbsp;&nbsp;Environment</div>') +
                '" />&nbsp;Name',
                flex: 1,
                dataIndex: 'name',
                sortable: true,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getScope(values)]}&nbsp;&nbsp;{name}',
                    {
                        getScope: function(data){
                            var scope = 'scalr';
                            if (data['envId']) {
                                scope = 'environment';
                            } else if (data['accountId']) {
                                scope = 'account';
                            }
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qtip="This Script is defined in the '+Ext.String.capitalize(scope)+' Scope"/>';
                        }
                    }
                )

            },
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
                xtype: 'cyclealt',
                name: 'origin',
                getItemIconCls: false,
                hidden: (Scalr.user.type == 'ScalrAdmin'),
                width: 110,
                cls: 'x-btn-compressed',
                changeHandler: function(comp, item) {
						store.proxy.extraParams.origin = item.value;
						store.loadPage(1);
                },
                getItemText: function(item) {
                    return item.value ? 'Scope: <img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" style="vertical-align: top; width: 14px; height: 14px;" title="' + item.text + '" />' : item.text;
                },
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: [{
                        text: 'All scopes',
                        value: null
                    },{
                        text: 'Scalr scope',
                        value: 'Shared',
                        iconCls: 'x-menu-item-icon-scope scalr-scope-scalr'
                    },{
                        text: 'Environment scope',
                        value: 'Custom',
                        iconCls: 'x-menu-item-icon-scope scalr-scope-env'
                    }]
                }
			}]
		}]
	});
});

Scalr.regPage('Scalr.ui.schedulertasks.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id', 'name', 'type', 'targetName', 'targetType', 'startTime',
			'endTime', 'lastStartTime', 'timezone', 'restartEvery','orderIndex', 'status', 'targetFarmId', 'targetFarmName', 'targetRoleId', 'targetRoleName', 'targetId'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/schedulertasks/xListTasks/'
		},
		remoteSort: true
	});
	return Ext.create('Ext.grid.Panel', {
		title: 'Scheduler tasks &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		scalrReconfigureParams: {},
		store: store,
		stateId: 'grid-schedulertasks-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Tasks scheduler',
				href: '#/schedulertasks/view'
			}
		}],

		viewConfig: {
			emptyText: 'No tasks found',
			loadingText: 'Loading tasks ...'
		},

		columns: [
			{ text: 'ID', width: 50, dataIndex: 'id', sortable: true },
			{ text: 'Task name', flex: 1, dataIndex: 'name', sortable: true },
			{ text: 'Task type', flex: 1, dataIndex: 'type', sortable: true },
			{ text: 'Target name', flex: 3, dataIndex: 'target', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="targetType == &quot;farm&quot;">Farm: <a href="#/farms/{targetId}/view" title="Farm {targetName}">{targetName}</a></tpl>' +
				'<tpl if="targetType == &quot;role&quot;">Farm: <a href="#/farms/{targetFarmId}/view" title="Farm {targetFarmName}">{targetFarmName}</a>' +
					'&nbsp;&rarr;&nbsp;Role: <a href="#/farms/{targetFarmId}/roles/{targetId}/view" title="Role {targetName}">{targetName}</a>' +
				'</tpl>' +
				'<tpl if="targetType == &quot;instance&quot;">Farm: <a href="#/farms/{targetFarmId}/view" title="Farm {targetFarmName}">{targetFarmName}</a>' +
					'&nbsp;&rarr;&nbsp;Role: <a href="#/farms/{targetFarmId}/roles/{targetRoleId}/view" title="Role {targetRoleName}">{targetRoleName}</a>' +
					'&nbsp;&rarr;&nbsp;Server: <a href="#/servers/view?farmId={targetFarmId}" title="Server {targetName}">{targetName}</a>' +
				'</tpl>'
			},
			{ text: 'Start date', width: 150, dataIndex: 'startTime', sortable: true },
			{ text: 'Restart every', width: 120, dataIndex: 'restartEvery', sortable: false, xtype: 'templatecolumn', tpl: new Ext.XTemplate(
				'<tpl if="restartEvery == 0">Never</tpl>' +
				'<tpl if="restartEvery != 0">{[this.convertTime(values.restartEvery)]}</tpl>', {
					convertTime: function (time) {
						if (time > 60) {
							var d1 = Math.ceil(time/60), d2 = Math.floor(time/60);
							if (d1 == d2) {
								time = time/60;
								if (time > 24) {
									d1 = Math.ceil(time/24), d2 = Math.floor(time/24);
									if (d1 == d2) {
										time = time/24;
										return time + " days";
									}
								} else {
									return time + " hours";
								}
								time = time * 60;
							}
						}
						return time + " minutes";
					}
				})
			},
			{ text: 'End date', width: 150, dataIndex: 'endTime', sortable: true },
			{ text: 'Last time executed', width: 150, dataIndex: 'lastStartTime', sortable: true },
			{ text: 'Timezone', width: 120, dataIndex: 'timezone', sortable: true },
			{ text: 'Priority', width: 60, dataIndex: 'order_index', sortable: true, hidden: true },
			{ text: 'Status', width: 100, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="status == &quot;Active&quot;"><span style="color: green;">{status}</span></tpl>' +
				'<tpl if="status == &quot;Suspended&quot;"><span style="color: blue;">{status}</span></tpl>' +
				'<tpl if="status == &quot;Finished&quot;"><span style="color: gray;">{status}</span></tpl>'
			}, {
				xtype: 'optionscolumn',
				getVisibility: function (record) {
					var reg =/Finished/i;
					return !reg.test(record.get('status'));
				},
				getOptionVisibility: function (item, record) {
					if (item.itemId == "option.activate" || item.itemId == "option.suspend" || item.itemId == "option.editSep") {
						var reg =/Finished/i
						if(reg.test(record.data.status))
							return false;
					}
					var reg =/Active/i
					if (item.itemId == "option.activate" && reg.test(record.get('status')))
						return false;

					var reg =/Suspended/i
					if (item.itemId == "option.suspend"  && reg.test(record.get('status')))
						return false;

					return true;
				},
				optionsMenu: [{
					itemId: 'option.activate',
					text: 'Activate',
					iconCls: 'x-menu-icon-activate',
					request: {
						processBox: {
							type: 'action'
						},
						url: '/schedulertasks/xActivate',
						dataHandler: function (record) {
							return { tasks: Ext.encode([record.get('id')]) };
						},
						success: function(data) {
							store.load();
						}
					}
				}, {
					itemId: 'option.suspend',
					text: 'Suspend',
					iconCls: 'x-menu-icon-suspend',
					request: {
						processBox: {
							type: 'action'
						},
						url: '/schedulertasks/xSuspend',
						dataHandler: function (record) {
							return { tasks: Ext.encode([record.get('id')]) };
						},
						success: function(data) {
							store.load();
						}
					}
				}, {
					xtype: 'menuseparator',
					itemId: 'option.editSep'
				}, {
					itemId: 'option.edit',
					iconCls: 'x-menu-icon-edit',
					text: 'Edit',
					href: '#/schedulertasks/{id}/edit'
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
						msg: 'Delete selected task(s): %s ?'
					},
					processBox: {
						type: 'delete'
					},
					url: '/schedulertasks/xDelete/',
					dataHandler: function (records) {
						var tasks = [];
						this.confirmBox.objects = [];
						for (var i = 0, len = records.length; i < len; i++) {
							tasks.push(records[i].get('id'));
							this.confirmBox.objects.push(records[i].get('name'))
						}

						return { tasks: Ext.encode(tasks) };
					}
				}
			}, {
				text: 'Activate',
				iconCls: 'x-menu-icon-activate',
				request: {
					confirmBox: {
						type: 'action',
						msg: 'Activate selected task(s)?',
						ok: 'Activate'
					},
					processBox: {
						type: 'action'
					},
					url: '/schedulertasks/xActivate/',
					dataHandler: function (records) {
						var tasks = [];
						for (var i = 0, len = records.length; i < len; i++) {
							tasks.push(records[i].get('id'));
						}

						return { tasks: Ext.encode(tasks) };
					}
				}
			}, {
				text: 'Suspend',
				iconCls: 'x-menu-icon-suspend',
				request: {
					confirmBox: {
						type: 'action',
						msg: 'Suspend selected task(s)?',
						ok: 'Suspend'
					},
					processBox: {
						type: 'action'
					},
					url: '/schedulertasks/xSuspend/',
					dataHandler: function (records) {
						var tasks = [];
						for (var i = 0, len = records.length; i < len; i++) {
							tasks.push(records[i].get('id'));
						}

						return { tasks: Ext.encode(tasks) };
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
					Scalr.event.fireEvent('redirect', '#/schedulertasks/create');
				}
			}],
			items: [{
					xtype: 'tbfilterfield',
					store: store
			}, ' ', {
				xtype: 'button',
				text: 'Transfer all tasks from old scheduler',
				hidden: !moduleParams['oldTasks'],
				handler: function () {
					Scalr.Request({
						confirmBox: {
							type: 'action',
							msg: 'Transfer all tasks to new scheduler ?'
						},
						processBox: {
							type: 'action'
						},
						url: '/schedulertasks/xTransfer',
						success: function () {
							Scalr.event.fireEvent('reload');
						}
					});
				}
			}]
		}]
	});
});

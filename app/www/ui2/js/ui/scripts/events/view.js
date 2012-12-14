Scalr.regPage('Scalr.ui.scripts.events.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'id', 'name','description' ],
		proxy: {
			type: 'scalr.paging',
			extraParams: loadParams,
			url: '/scripts/events/xListCustomEvents/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Scripts &raquo; Events &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		scalrReconfigureParams: { metricId: '' },
		store: store,
		stateId: 'grid-scripts-events-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Custom events',
				href: '#/scripts/events/view'
			}
		}],

		viewConfig: {
			emptyText: "No custom events",
			loadingText: 'Loading custom events ...'
		},

		columns: [
			{ header: "ID", width: 40, dataIndex: 'id', sortable: true },
			{ header: "Name", flex: 1, dataIndex: 'name', sortable:true },
			{ header: "Description", flex: 10, dataIndex: 'description', sortable: false },
			{
				xtype: 'optionscolumn',
				optionsMenu: [{
					text: 'Edit',
					href: "#/scripts/events/{id}/edit"
				}],
				getVisibility: function (record) {
					return (record.get('env_id') != 0);
				}
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
						msg: 'Remove selected event(s): %s ?',
						type: 'delete'
					},
					processBox: {
						msg: 'Removing selected event(s) ...',
						type: 'delete'
					},
					url: '/scripts/events/xRemove/',
					dataHandler: function (records) {
						var events = [];
						this.confirmBox.objects = [];
						for (var i = 0, len = records.length; i < len; i++) {
							events.push(records[i].get('id'));
							this.confirmBox.objects.push(records[i].get('name'))
						}

						return { events: Ext.encode(events) };
					}
				}
			}],
			getVisibility: function (record) {
				return (record.get('env_id') != 0);
			}
		},

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			afterItems: [{
				ui: 'paging',
				iconCls: 'x-tbar-add',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/scripts/events/create');
				}
			}]
		}]
	});
});

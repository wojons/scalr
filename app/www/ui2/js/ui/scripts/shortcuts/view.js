Scalr.regPage('Scalr.ui.scripts.shortcuts.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{name: 'id', type: 'int'},
			'farmid', 'farmname', 'farm_roleid', 'rolename', 'scriptname', 'event_name'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/scripts/shortcuts/xListShortcuts/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Scripts &raquo; Shortcuts &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		scalrReconfigureParams: { scriptId: '', eventName:'' },
		store: store,
		stateId: 'grid-scripts-shortcuts-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}],

		viewConfig: {
			emptyText: "No shortcuts defined",
			loadingText: 'Loading shortcuts ...'
		},

		columns: [
			{ header: "Target", flex: 1, dataIndex: 'id', sortable: false, xtype: 'templatecolumn', tpl:
				'<a href="#/farms/{farmid}/view">{farmname}</a>' +
				'<tpl if="farm_roleid &gt; 0">&rarr;<a href="#/farms/{farmid}/roles/{farm_roleid}/view">{rolename}</a></tpl>' +
				'&nbsp;&nbsp;&nbsp;'
			},
			{ header: "Script", flex: 2, dataIndex: 'scriptname', sortable: true }, {
				xtype: 'optionscolumn',
				optionsMenu: [{
					text: 'Edit',
					href: "#/scripts/execute?eventName={event_name}&isShortcut=1"
				}]
			}
		],

        multiSelect: true,
        selModel: {
            selType: 'selectedmodel'
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
            afterItems: [{
                ui: 'paging',
                itemId: 'delete',
                iconCls: 'x-tbar-delete',
                tooltip: 'Select one or more shortcut(s) to delete them',
                disabled: true,
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected shortcut(s): %s ?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Deleting shortcut(s) ...'
                        },
                        url: '/scripts/shortcuts/xRemove/',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('scriptname'));
                    }
                    request.params = { shortcuts: Ext.encode(data) };
                    Scalr.Request(request);
                }
            }]
        }]
	});
});

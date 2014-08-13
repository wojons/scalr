Scalr.regPage('Scalr.ui.scripts.shortcuts.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id', 'farmId', 'farmName', 'farmRoleId', 'farmRoleName', 'scriptName'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/scripts/shortcuts/xList'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Scripts &raquo; Shortcuts &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
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
			{ header: "Target", flex: 1, dataIndex: 'id', sortable: true, xtype: 'templatecolumn',
                doSort: function (state) {
                    var ds = this.up('tablepanel').store;
                    ds.sort([{
                        property: 'farmId',
                        direction: state
                    }, {
                        property: 'farmRoleId',
                        direction: state
                    }]);
                },
                tpl:
				'<a href="#/farms/{farmId}/view">{farmName}</a>' +
				'<tpl if="farmRoleId &gt; 0"> &rarr; <a href="#/farms/{farmId}/roles/{farmRoleId}/view">{farmRoleName}</a></tpl>' +
				'&nbsp;&nbsp;&nbsp;'
            },
			{ header: "Script", flex: 2, dataIndex: 'scriptId', sortable: true, xtype: 'templatecolumn', tpl: '{scriptName}' },
            {
				xtype: 'optionscolumn2',
				menu: [{
					text: 'Edit',
                    iconCls: 'x-menu-icon-edit',
					href: "#/scripts/execute?shortcutId={id}&edit=1"
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
            items: [{
                xtype: 'filterfield',
                store: store
            }],
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
                        request.confirmBox.objects.push(records[i].get('scriptName'));
                    }
                    request.params = { shortcutId: Ext.encode(data) };
                    Scalr.Request(request);
                }
            }]
        }]
	});
});

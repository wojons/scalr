Scalr.regPage('Scalr.ui.services.configurations.presets.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'id','env_id','client_id','name','role_behavior','dtadded','dtlastmodified' ],
		proxy: {
			type: 'scalr.paging',
			url: '/services/configurations/presets/xListPresets/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'Services Configurations Presets',
            menuHref: '#/services/configurations/presets',
            menuFavorite: true
		},
		store: store,
		stateId: 'grid-services-configurations-presets-view',
		stateful: true,
        plugins: [ 'gridstore', 'applyparams' ],

		viewConfig: {
			emptyText: "No presets found",
			loadingText: 'Loading presets ...'
		},

		columns:[
			{ header: "ID", width: 60, dataIndex: 'id', sortable:true },
			{ header: "Name", flex: 1, dataIndex: 'name', sortable:true },
			{ header: "Role automation", flex: 1, dataIndex: 'role_behavior', sortable: true },
			{ header: "Added at", flex: 1, dataIndex: 'dtadded', sortable: false },
			{ header: "Last time modified", flex: 1, dataIndex: 'dtlastmodified', sortable: false },
			{
				xtype: 'optionscolumn',
				menu: [{
					text: 'Edit',
					iconCls: 'x-menu-icon-edit',
					href: "#/services/configurations/presets/{id}/edit"
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
            dock: 'top',
			xtype: 'displayfield',
			cls: 'x-form-field-warning x-form-field-warning-fit',
			value: Scalr.strings['deprecated_warning']
        },{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            afterItems: [{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more configuration preset(s) to delete them',
                disabled: true,
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected configuration preset(s): %s ?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Deleting preset(s) ...'
                        },
                        url: '/services/configurations/presets/xRemove/',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('name'));
                    }
                    request.params = { presets: Ext.encode(data) };
                    Scalr.Request(request);
                }
            }],
            items: [{
                xtype: 'filterfield',
                store: store
            }]
        }]
	});
});

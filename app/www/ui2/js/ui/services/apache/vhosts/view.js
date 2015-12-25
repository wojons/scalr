Scalr.regPage('Scalr.ui.services.apache.vhosts.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'id','env_id','client_id','name','role_behavior','is_ssl_enabled','last_modified','farm_name','role_name', 'farm_id', 'farm_roleid'],
		proxy: {
			type: 'scalr.paging',
			url: '/services/apache/vhosts/xListVhosts/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'Apache Virtual Hosts',
            menuHref: '#/services/apache/vhosts',
            menuFavorite: true
        },
		store: store,
		stateId: 'grid-services-apache-vhosts-view',
		stateful: true,
        plugins: [ 'gridstore', 'applyparams' ],

		viewConfig: {
			emptyText: "No apache virtualhosts found",
			loadingText: 'Loading virtualhosts ...'
		},

		columns:[
			{ header: "ID", width: 60, dataIndex: 'id', sortable:true },
			{ header: "Virtualhost", flex: 5, dataIndex: 'name', sortable:true },
			{ header: "Farm & Role", flex: 5, dataIndex: 'farm_id', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="farm_name && role_name">'+
					'<a href="#/farms?farmId={farm_id}" title="Farm {farm_name}">{farm_name}</a>' +
					'&nbsp;&rarr;&nbsp;<a href="#/farms/{farm_id}/roles/{farm_roleid}/view" title="Role {role_name}">{role_name}</a> ' +
				'<tpl else>&mdash;</tpl>'
			},
			{ header: "Last time modified", width: 160, dataIndex: 'last_modified', sortable: true },
			{ header: "SSL", width: 60, dataIndex: 'is_ssl_enabled', sortable: true, align: 'center', xtype: 'templatecolumn', tpl:
				'<tpl if="is_ssl_enabled == 1"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div></tpl>' +
				'<tpl if="is_ssl_enabled == 0">&mdash;</tpl>'
			},
			{
				xtype: 'optionscolumn',
                hidden: !Scalr.isAllowed('SERVICES_APACHE', 'manage'),
				menu: [{
					text: 'Edit',
                    iconCls: 'x-menu-icon-edit',
                    showAsQuickAction: true,
					href: "#/services/apache/vhosts/{id}/edit"
				}]
			}
		],

        selModel: Scalr.isAllowed('SERVICES_APACHE', 'manage') ? 'selectedmodel' : null,
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
				itemId: 'delete',
				iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
				tooltip: 'Select one or more virtual hosts to delete them',
				disabled: true,
                hidden: !Scalr.isAllowed('SERVICES_APACHE', 'manage'),
				handler: function() {
					var me = this,
                        request = {
                            confirmBox: {
                                type: 'delete',
                                msg: 'Delete selected virtual host(s): %s ?'
                            },
                            processBox: {
                                type: 'delete',
                                msg: 'Deleting selected virtual host(s) ...'
                            },
                            url: '/services/apache/vhosts/xRemove/',
                            success: function() {
                                me.up('grid').getSelectionModel().deselectAll();
                                store.load();
                            }
                        }, records = me.up('grid').getSelectionModel().getSelection(), ids = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						ids.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('name'));
					}
					request.params = { vhosts: Ext.encode(ids) };
					Scalr.Request(request);
				}
			}],
			beforeItems: [{
                text: 'New virtualhost',
                cls: 'x-btn-green',
                hidden: !Scalr.isAllowed('SERVICES_APACHE', 'manage'),
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/services/apache/vhosts/create');
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}]
		}]
	});
});

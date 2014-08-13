Scalr.regPage('Scalr.ui.services.chef.servers.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id', 'username', 'url'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/services/chef/servers/xListServers/'
		},
		remoteSort: true
	});
	return Ext.create('Ext.grid.Panel', {
		title: 'Chef &raquo; Servers &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-chef-servers-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Chef Servers',
				href: '#/services/chef/servers/view'
			}
		}],

		viewConfig: {
			deferEmptyText: false,
			emptyText: 'No servers found',
			loadingText: 'Loading servers ...'
		},

		columns: [
            { text: "ID", width: 100, dataIndex: 'id', sortable: true },
			{ text: "URL", flex: 3, dataIndex: 'url', sortable: true },
			{ text: "User name", width: 220, dataIndex: 'username', sortable: true },
			{
                xtype: 'optionscolumn2',
				menu: [{
					text:'Edit', 
					iconCls: 'x-menu-icon-edit',
					menuHandler: function(data) {
						Scalr.event.fireEvent('redirect','#/services/chef/servers/edit?servId=' + data['id']);
					}
				},{
					xtype: 'menuseparator',
					itemId: 'option.attachSep'
				},{ 
					text:'Delete', 
					iconCls: 'x-menu-icon-delete',
                    request: {
                        confirmBox: {
                            msg: 'Remove selected chef server ?',
                            type: 'delete'
                        },
                        processBox: {
                            msg: 'Removing chef server ...',
                            type: 'delete'
                        },
                        url: 'services/chef/servers/xDeleteServer',
                        dataHandler: function (data) {
                            return { servId: data['id'] };
                        },
                        success: function (data, response, options){
                            store.load();
                        }
                    }
				}]
			}],
		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			beforeItems: [{
                text: 'Add Chef server',
                cls: 'x-btn-green-bg',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/services/chef/servers/create');
				}
			}]
		}]
	});
});
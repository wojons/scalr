Scalr.regPage('Scalr.ui.services.chef.runlists.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id', 'name', 'description', 'servId', 'chefEnv'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/services/chef/runlists/xListRunlists/'
		},
		remoteSort: true
	});
	return Ext.create('Ext.grid.Panel', {
		title: 'Chef &raquo; Runlists &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-chef-runlists-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Chef Runlists',
				href: '#/services/chef/runlists/view'
			}
		}],

		viewConfig: {
			deferEmptyText: false,
			emptyText: 'No runlists found',
			loadingText: 'Loading runlists ...'
		},

		columns: [
			{ text: "Name", width: 220, dataIndex: 'name', sortable: true },
			{ text: "Description", flex: 3, dataIndex: 'description', sortable: true },
			{
				text: 'Chef environment',
				flex: 2,
				dataIndex: 'chefEnv'
			},{ 
                xtype: 'optionscolumn2',
				menu: [{
					text: 'Edit', 
					iconCls: 'x-menu-icon-edit',
					menuHandler: function(data) {
						Scalr.event.fireEvent('redirect','#/services/chef/runlists/edit?runlistId=' + data['id']);
					}
				},{
					xtype: 'menuseparator',
					itemId: 'option.attachSep'
				},{ 
					text:'Source', 
					iconCls: 'x-menu-icon-info',
					menuHandler: function(data) {
						Scalr.event.fireEvent('redirect','#/services/chef/runlists/source?runlistId=' + data['id']);
					}
				},{
					xtype: 'menuseparator',
					itemId: 'option.attachSep'
				},{ 
					text: 'Delete', 
					iconCls: 'x-menu-icon-delete',
                    request: {
                        confirmBox: {
                            msg: 'Remove selected runlist ?',
                            type: 'delete'
                        },
                        processBox: {
                            msg: 'Removing runlist ...',
                            type: 'delete'
                        },
                        url: 'services/chef/runlists/xDeleteRunlist',
                        dataHandler: function (data) {
                            return { runlistId: data['id'], runlistName: data['name'], servId: data['servId'], chefEnv: data['chefEnv'] };
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
                text: 'Add runlist',
                cls: 'x-btn-green-bg',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/services/chef/runlists/create');
				}
			}]
		}]
	});
});
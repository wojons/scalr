Scalr.regPage('Scalr.ui.bundletasks.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{name: 'id', type: 'int'},{name: 'clientid', type: 'int'},
			'server_id','prototype_role_id','replace_type','status','platform','rolename','failure_reason','bundle_type','dtadded',
			'dtstarted','dtfinished','snapshot_id','platform_status','server_exists', 'os_family', 'os_version', 'os_name', 'created_by_email', 'role_id', 'duration'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/bundletasks/xListTasks/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Bundle tasks &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-bundletasks-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Bundle tasks',
				href: '#/bundletasks/view'
			}
		}],

		viewConfig: {
			emptyText: 'No bundle tasks found',
			loadingText: 'Loading bundle tasks ...',
            disableSelection: true
		},

		columns: [
			{ header: "ID", width: 70, dataIndex: 'id', sortable: true },
			{ header: "Server ID", width: 200, dataIndex: 'server_id', sortable: true, xtype: 'templatecolumn', tpl: new Ext.XTemplate(
				'<tpl if="server_exists"><a href="#/servers/{server_id}/dashboard" title="{server_id}">{[this.serverId(values.server_id)]}</a>',
				'<tpl else><img src="/ui2/images/icons/false.png" /></tpl>',
                {
					serverId: function(id) {
						var values = id.split('-');
						return values[0] + '-...-' + values[values.length - 1];
					}
                }
			)},
			{ header: "Role name", flex: 1, dataIndex: 'rolename', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="rolename && role_id && status==\'\success\'">' +
                    '<a href="#/roles/manager?roleId={role_id}">{rolename}</a>' +
                '<tpl else>' +
                    '{rolename}' +
                '</tpl>'
            },
            { header: "OS", flex: .7, dataIndex: 'os_family', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="os_family">' +
                    '<img style="margin:0 3px 0 0" class="x-icon-osfamily-small x-icon-osfamily-small-{os_family}" src="' + Ext.BLANK_IMAGE_URL + '"/> {[Scalr.utils.beautifyOsFamily(values.os_family)]} {os_version} {os_name:capitalize}' +
                '<tpl else>' +
                    '<img src="/ui2/images/icons/false.png" />' +
                '</tpl>'
            },
			{ header: "Added", width: 165, dataIndex: 'dtadded', sortable: true, hidden: true },
			{ header: "Started", width: 165, dataIndex: 'dtstarted', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="dtstarted">{dtstarted}</tpl>'
			},
			{ header: "Duration", width: 165, dataIndex: 'dtfinished', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="status==\'success\'">' +
                    '{duration}' +
                '<tpl elseif="status==\'failed\'">' +
                    '<img src="/ui2/images/icons/false.png" />' +
                '<tpl else>Ongoing</tpl>'
			},
            { header: "Created by", width: 165, dataIndex: 'created_by_email', sortable: true },
            { header: "Status", width: 140, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'bundletask'},
            {
				xtype: 'optionscolumn2',
				menu: [{
					text:'View log',
					iconCls: 'x-menu-icon-logs',
					href: '#/bundletasks/{id}/logs'
				}, {
					iconCls: 'x-menu-icon-cancel',
					text: 'Cancel',
                    getVisibility: function (data) {
                        return data['status'] !== 'success' && data['status'] !== 'failed';
                    },
					request: {
						confirmBox: {
							msg: 'Cancel selected bundle task?',
							type: 'action'
						},
						processBox: {
							type: 'action',
							msg: 'Canceling...'
						},
						url: '/bundletasks/xCancel/',
						dataHandler: function (data) {
							return { bundleTaskId: data['id'] };
						},
						success: function(data) {
							store.load();
						}
					}
				}]
			}
		],

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
            items: [{
                xtype: 'filterfield',
                store: store
            }]
		}]
	});
});

Scalr.regPage('Scalr.ui.bundletasks.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{name: 'id', type: 'int'},{name: 'clientid', type: 'int'},
			'server_id','prototype_role_id','replace_type','status','platform','rolename','failure_reason','bundle_type','dtadded',
			'dtstarted','dtfinished','snapshot_id','platform_status','server_exists', 'os_family', 'os_version', 'os_name', 'created_by_email', 'role_id', 'duration'
		],
		proxy: {
			type: 'scalr.paging',
			extraParams: loadParams,
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
		scalrReconfigureParams: { bundleTaskId: ''},
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
                    '<a href="#/roles/{role_id}/view">{rolename}</a>' +
                '<tpl else>' +
                    '{rolename}' +
                '</tpl>'
            },
			{ header: "Status", width: 140, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="status==\'failed\'">'+
                    '<span style="color:red;">{status:capitalize}</span> (<a href="#/bundletasks/{id}/failureDetails">Why?</a>)'+
                '<tpl elseif="status==\'success\'">' +
                    '<span style="color:green;">{status:capitalize}</span>' +
                '<tpl else>' +
    				'{status:capitalize}' +
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
            {
				xtype: 'optionscolumn',
				optionsMenu: [{
					text:'View log',
					iconCls: 'x-menu-icon-logs',
					href: '#/bundletasks/{id}/logs'
				}, {
					itemId: 'option.cancel',
					iconCls: 'x-menu-icon-cancel',
					text: 'Cancel',
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
						dataHandler: function (record) {
							return { bundleTaskId: record.get('id') };
						},
						success: function(data) {
							store.load();
						}
					}
				}],
				getOptionVisibility: function (item, record) {
					if (item.itemId == 'option.cancel') {
						if (record.get('status') != 'success' && record.get('status') != 'failed')
							return true;
						else
							return false;
					}

					return true;
				}
			}
		],

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top'
		}]
	});
});

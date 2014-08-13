Scalr.regPage('Scalr.ui.farms.roles.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{name: 'id', type: 'int'}, 'platform', 'location',
			'name', 'alias', 'min_count', 'max_count', 'min_LA', 'max_LA', 'running_servers', 'suspended_servers', 'non_running_servers' ,'domains',
			'image_id', 'farmid','shortcuts', 'role_id', 'scaling_algos', 'farm_status', 'location', 'allow_launch_instance', 'is_vpc'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/farms/roles/xListFarmRoles/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Farms &raquo; ' + moduleParams['farmName'] + ' &raquo; Roles',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-farms-roles-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}],

		viewConfig: {
			emptyText: 'No roles assigned to selected farm',
			loadingText: 'Loading roles ...'
		},

		columns: [
			{ header: "Cloud", width: 80, dataIndex: 'platform', sortable: true, align: 'center', xtype: 'templatecolumn', tpl:
                '<img class="x-icon-platform-small x-icon-platform-small-{platform}" title="{platform}" src="' + Ext.BLANK_IMAGE_URL + '"/>'
            },
			{ header: "Location", width: 100, dataIndex: 'location', sortable: false },
			{ header: "Name", flex: 1, dataIndex: 'alias', sortable: false},
			{ header: "Role", flex: 1, dataIndex: 'name', sortable: false, xtype: 'templatecolumn', tpl:
				'<a href="#/roles/manager?roleId={role_id}">{name}</a>'
			},
			{ header: "Min servers", width: 90, dataIndex: 'min_count', sortable: false, align:'center', xtype:'templatecolumn',  tpl:
				'<tpl if="min_count">{min_count}</tpl>' +
				'<tpl if="!min_count"><img src="/ui2/images/icons/false.png" /></tpl>'},
			{ header: "Max servers", width: 90, dataIndex: 'max_count', sortable: false, align:'center', xtype:'templatecolumn',  tpl:
				'<tpl if="max_count">{max_count}</tpl>' +
				'<tpl if="!max_count"><img src="/ui2/images/icons/false.png" /></tpl>'},
			{ header: "Enabled scaling algorithms", flex: 1, dataIndex: 'scaling_algos', sortable: false, align:'center' },
			{ header: "Servers", width: 100, dataIndex: 'servers', sortable: false, xtype: 'templatecolumn', 
				tpl: new Ext.XTemplate(
                    '<span data-anchor="right" data-qalign="r-l" data-qtip="{[this.getTooltipHtml(values)]}" data-qwidth="230">' +
                        '<span style="color:#28AE1E;">{running_servers}</span>' +
                        '/<span style="color:#329FE9;">{suspended_servers}</span>' +
                        '/<span style="color:#bbb;">{non_running_servers}</span>' +
                    '</span>'+
                    ' [<a href="#/servers/view?farmId={farmid}&farmRoleId={id}">View</a>]',
                    {
                        getTooltipHtml: function(values) {
                            return Ext.String.htmlEncode(
                                '<span style="color:#00CC00;">' + values.running_servers + '</span> &ndash; Initializing & Running servers<br/>' +
                                '<span style="color:#4DA6FF;">' + values.suspended_servers + '</span> &ndash; Suspended servers<br/>' +
                                '<span style="color:#bbb;">' + values.non_running_servers + '</span> &ndash; Terminated servers'
                            );
                        }
                    }
                )
			},
			{ header: "Domains", width: 100, dataIndex: 'domains', sortable: false, xtype: 'templatecolumn', tpl:
				'{domains} [<a href="#/dnszones/view?farmRoleId={id}">View</a>]'
			}, {
				xtype: 'optionscolumn2',
                menu: {
                    xtype: 'actionsmenu',
                    listeners: {
                        beforeshow: function() {
                            var me = this;
                            me.items.each(function (item) {
                                if (item.isshortcut) {
                                    me.remove(item);
                                }
                            });

                            if (me.data['farm_status'] == 1 && me.data['shortcuts'].length) {
                                me.add({
                                    xtype: 'menuseparator',
                                    isshortcut: true
                                });

                                Ext.Array.each(me.data['shortcuts'], function (shortcut) {
                                    if (typeof(shortcut) != 'function') {
                                        me.add({
                                            isshortcut: true,
                                            text: 'Execute ' + shortcut.name,
                                            href: '#/scripts/execute?shortcutId=' + shortcut.id
                                        });
                                    }
                                });
                            }
                        }
                    },
                    items: [{
                        itemId: 'option.ssh_key',
                        text: 'Download SSH private key',
                        iconCls: 'x-menu-icon-downloadprivatekey',
                        menuHandler: function (data) {
                            Scalr.utils.UserLoadFile('/sshkeys/downloadPrivate?farmId=' + loadParams['farmId'] + '&platform=' + data['platform'] + "&cloudLocation=" + data['location']);
                        }
                    }, {
                        itemId: 'option.cfg',
                        iconCls: 'x-menu-icon-configure',
                        text: 'Configure',
                        href: "#/farms/{farmid}/edit?farmRoleId={id}"
                    }, {
                        itemId: 'option.stat',
                        iconCls: 'x-menu-icon-statsusage',
                        text: 'View statistics',
                        href: "#/monitoring/view?farmRoleId={id}&farmId={farmid}"
                    }, {
                        itemId: 'option.info',
                        iconCls: 'x-menu-icon-info',
                        text: 'Extended role information',
                        href: "#/farms/" + loadParams['farmId'] + "/roles/{id}/extendedInfo"
                    }, {
                        xtype: 'menuseparator',
                        itemId: 'option.mainSep'
                    }, {
                        itemId: 'option.downgrade',
                        iconCls: 'x-menu-icon-downgrade',
                        text: 'Downgrade role to previous version',
                        href: "#/farms/" + loadParams['farmId'] + "/roles/{id}/downgrade"
                    }, {
                        xtype: 'menuseparator',
                        itemId: 'option.mainSep2'
                    }, {
                        itemId: 'option.exec',
                        iconCls: 'x-menu-icon-execute',
                        text: 'Execute script',
                        href: '#/scripts/execute?farmRoleId={id}'
                    }, {
                        xtype: 'menuseparator',
                        itemId: 'option.eSep'
                    }, {
                        itemId: 'option.launch',
                        iconCls: 'x-menu-icon-launch',
                        text: 'Launch new server',
                        getVisibility: function(data) {
                            return !!data['allow_launch_instance'];
                        },
                        request: {
                            processBox: {
                                type: 'launch'
                            },
                            dataHandler: function (data) {
                                this.url = '/farms/' + loadParams['farmId'] + '/roles/' + data['id'] + '/xLaunchNewServer';
                            },
                            success: function (data) {
                                store.load();
                            }
                        }
                    }]
                }
			}
		],

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
            ignoredLoadParams: ['farmId'],
			store: store,
			dock: 'top',
			items: [{
				xtype: 'filterfield',
				store: store
			}]
		}]
	});
});

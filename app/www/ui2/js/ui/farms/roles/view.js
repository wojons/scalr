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
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'Farms',
            menuSubTitle: moduleParams['farmName'] + ' &raquo; Roles',
            menuHref: '#/farms',
            menuParentStateId: 'grid-farms-view'
		},
		store: store,
		stateId: 'grid-farms-roles-view',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams',
            filterIgnoreParams: [ 'farmId' ],
            updateLinkOnStoreDataChanged: false
        }],
        disableSelection: true,

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
				'<a href="#/roles?roleId={role_id}">{name}</a>'
			},
			{ header: "Min servers", width: 90, dataIndex: 'min_count', sortable: false, align:'center', xtype:'templatecolumn',  tpl:
				'<tpl if="min_count">{min_count}</tpl>' +
				'<tpl if="!min_count">&mdash;</tpl>'},
			{ header: "Max servers", width: 90, dataIndex: 'max_count', sortable: false, align:'center', xtype:'templatecolumn',  tpl:
				'<tpl if="max_count">{max_count}</tpl>' +
				'<tpl if="!max_count">&mdash;</tpl>'},
			{ header: "Enabled scaling algorithms", flex: 1, dataIndex: 'scaling_algos', sortable: false, align:'center' },
			{ header: "Servers", width: 100, dataIndex: 'servers', sortable: false, align: 'center', xtype: 'templatecolumn',
				tpl: new Ext.XTemplate(
                    '<a href="#/servers?farmId={farmid}&farmRoleId={id}" class="x-grid-big-href"<span data-anchor="right" data-qalign="r-l" data-qtip="{[this.getTooltipHtml(values)]}" data-qwidth="270">' +
                        '<span style="color:#28AE1E;">{running_servers}</span>' +
                        '/<span style="color:#329FE9;">{suspended_servers}</span>' +
                        '/<span style="color:#bbb;">{non_running_servers}</span>' +
                    '</span></a>',
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
			{ header: "Domains", width: 90, dataIndex: 'domains', sortable: false, align: 'center', xtype: 'templatecolumn', tpl:
				'<a href=#/dnszones?farmRoleId={id}" class="x-grid-big-href">{domains}</a>'
			}, {
				xtype: 'optionscolumn',
                menu: {
                    xtype: 'actionsmenu',
                    listeners: {
                        beforeshow: function() {
                            var me = this,
                                shortcuts = me.data['shortcuts'] || [];
                            me.items.each(function (item) {
                                if (item.isshortcut) {
                                    me.remove(item);
                                }
                            });

                            if (me.data['farm_status'] == 1 && shortcuts.length) {
                                me.add({
                                    xtype: 'menuseparator',
                                    isshortcut: true
                                });

                                Ext.Array.each(shortcuts, function (shortcut) {
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
                        text: 'Download SSH private key',
                        iconCls: 'x-menu-icon-downloadprivatekey',
                        menuHandler: function (data) {
                            Scalr.utils.UserLoadFile('/sshkeys/downloadPrivate?farmId=' + loadParams['farmId'] + '&platform=' + data['platform'] + "&cloudLocation=" + data['location']);
                        }
                    }, {
                        iconCls: 'x-menu-icon-configure',
                        text: 'Configure',
                        showAsQuickAction: true,
                        href: "#/farms/designer?farmId={farmid}&farmRoleId={id}"
                    }, {
                        iconCls: 'x-menu-icon-statsusage',
                        text: 'View statistics',
                        href: "#/monitoring?farmRoleId={id}&farmId={farmid}",
                        getVisibility: function(data) {
                            return data['farm_status'] != 0 && Scalr.isAllowed('FARMS', 'statistics');
                        }
                    }, {
                        iconCls: 'x-menu-icon-information',
                        text: 'Extended role information',
                        showAsQuickAction: true,
                        href: "#/farms/" + loadParams['farmId'] + "/roles/{id}/extendedInfo"
                    }, {
                        xtype: 'menuseparator'
                    }, {
                        xtype: 'menuseparator'
                    }, {
                        iconCls: 'x-menu-icon-execute',
                        text: 'Execute script',
                        href: '#/scripts/execute?farmRoleId={id}'
                    }, {
                        iconCls: 'x-menu-icon-execute',
                        text: 'Fire event',
                        href: '#/scripts/events/fire?farmRoleId={id}',
                        getVisibility: function(data) {
                            return Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'fire');
                        }
                    }, {
                        xtype: 'menuseparator'
                    }, {
                        iconCls: 'x-menu-icon-launch',
                        text: 'Launch new server',
                        showAsQuickAction: true,
                        getVisibility: function(data) {
                            return !!data['allow_launch_instance'] && data['farm_status'] == '1';
                        },
                        request: {
                            confirmBox: {
                                type: 'launch',
                                msg: 'Are you sure want to launch new server ?'
                            },
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
			store: store,
			dock: 'top',
			items: [{
				xtype: 'filterfield',
				store: store
			}]
		}]
	});
});

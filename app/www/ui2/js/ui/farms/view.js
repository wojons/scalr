Scalr.regPage('Scalr.ui.farms.view', function (loadParams, moduleParams) {
    var filterFieldForm = [];

    if (moduleParams['leaseEnabled']) {
        filterFieldForm.push({
            xtype: 'textfield',
            fieldLabel: 'Show farms that will expire in N days',
            labelAlign: 'top',
            name: 'expirePeriod'
        });
    }
    if (Scalr['flags']['analyticsEnabled']) {
        filterFieldForm.push({
            xtype: 'textfield',
            fieldLabel: 'Project ID',
            labelAlign: 'top',
            name: 'projectId'
        });
    }

    filterFieldForm = filterFieldForm.length ? {items: filterFieldForm} : null;

	var store = Ext.create('store.store', {
		fields: [
			{name: 'id', type: 'int'},
			{name: 'clientid', type: 'int'},
			'name', 'status', 'dtadded', 'running_servers', 'suspended_servers', 'non_running_servers', 'roles', 'zones','client_email',
			'havemysqlrole','shortcuts', 'havepgrole', 'haveredisrole', 'haverabbitmqrole', 'havemongodbrole', 'havemysql2role', 'havemariadbrole', 
			'haveperconarole', 'lock', 'lock_comment', 'created_by_id', 'created_by_email', 'alerts', 'lease', 'leaseMessage'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/farms/xListFarms'
		},
		remoteSort: true
	});
	
	if (loadParams['demoFarm'] == 1) {
		Scalr.message.Success("Your first environment successfully configured and linked to your AWS account. Please use 'Options -> Launch' menu to launch your first demo LAMP farm.");
	}

	var grid = Ext.create('Ext.grid.Panel', {
		title: 'Farms &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-farms-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Farms',
				href: '#/farms/view'
			}
		}],

		viewConfig: {
			emptyText: 'No farms found',
			loadingText: 'Loading farms ...',
			disableSelection: true
		},

		columns: [
			{ text: "ID", width: 80, dataIndex: 'id', sortable: true },
			{ text: "Farm name", flex: 1, dataIndex: 'name', sortable: true, xtype: 'templatecolumn', tpl:
                '{name}<tpl if="lease && status == 1"> <a href="#/farms/{id}/extendedInfo">' +
                    '<tpl if="lease == &quot;Expire&quot;">' +
                        '<div class="" style="display: inline" data-anchor="left" data-qalign="l-r" data-qtip="{leaseMessage}" data-qwidth="340">' +
                    '</tpl>' +
                    '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-leased<tpl if="lease == &quot;Expire&quot;"> x-icon-leased-expire</tpl>">' +
                    '<tpl if="lease == &quot;Expire&quot;"></div></tpl>' +
                '</a></tpl>'
            },
			{ text: "Added", flex: 1, dataIndex: 'dtadded', sortable: true },
			{ text: "Owner", flex: 1, dataIndex: 'created_by_email', sortable: true },
			{ text: "Servers", width: 100, dataIndex: 'servers', sortable: false, xtype: 'templatecolumn',
				tpl: new Ext.XTemplate(
                    '<span data-anchor="right" data-qalign="r-l" data-qtip="{[this.getTooltipHtml(values)]}" data-qwidth="230">' +
                        '<span style="color:#28AE1E;">{running_servers}</span>' +
                        '/<span style="color:#329FE9;">{suspended_servers}</span>' +
                        '/<span style="color:#bbb;">{non_running_servers}</span>' +
                    '</span>'+
                    ' [<a href="#/servers/view?farmId={id}">View</a>]',
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
			{ text: "Roles", width: 70, dataIndex: 'roles', sortable: false, align:'center', xtype: 'templatecolumn',
				tpl: '<a href="#/farms/{id}/roles">{roles}</a>'
			},
			{ text: "DNS zones", width: 100, dataIndex: 'zones', sortable: false, align:'center', xtype: 'templatecolumn',
				tpl: '<a href="#/dnszones/view?farmId={id}">{zones}</a>'
			},
			{ text: "Status", width: 120, minWidth: 120, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'farm'},
            { text: "Alerts", width: 90, dataIndex: 'alerts', align:'center', sortable: false, xtype: 'templatecolumn',	tpl: 
                '<tpl if="status == 1">' +
					'<tpl if="alerts &gt; 0">' + 
                        '<span style="color:red;">{alerts}</span> [<a href="#/alerts/view?farmId={id}&status=failed">View</a>]' +
                    '<tpl else>' +
                        '<span style="color:green;">0</span>' +
                    '</tpl>'+
				'<tpl else>' + 
                    '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-minus" />' +
                '</tpl>'
			}, {
				text: 'Lock', width: 60, dataIndex: 'lock', fixed: true, resizable: false, sortable: false, tdCls: 'scalr-ui-farms-view-td-lock', xtype: 'templatecolumn', tpl:
					'<tpl if="lock"><div class="scalr-ui-farms-view-lock" title="{lock_comment}"></div><tpl else><div class="scalr-ui-farms-view-unlock" title="Lock farm"></div></tpl>'
			}, {
				xtype: 'optionscolumn2',
				menu: {
                    xtype: 'actionsmenu',
                    listeners: {
                        beforeshow: function () {
                            var me = this;
                            me.items.each(function (item) {
                                if (item.isshortcut) {
                                    me.remove(item);
                                }
                            });

                            if (me.data['shortcuts'].length) {
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
                        itemId: 'option.addToDash',
                        text: 'Add to dashboard',
                        iconCls: 'x-menu-icon-dashboard',
                        request: {
                            processBox: {
                                type: 'action',
                                msg: 'Adding new widget to dashboard ...'
                            },
                            url: '/dashboard/xUpdatePanel',
                            dataHandler: function (data) {
                                return {widget: Ext.encode({params: {farmId: data['id']}, name: 'dashboard.farm', url: '' })};
                            },
                            success: function (data, response, options) {
                                Scalr.event.fireEvent('update', '/dashboard', data.panel);
                                Scalr.storage.set('dashboard', Ext.Date.now());
                            }
                        }
                    }, {
                        xtype: 'menuseparator',
                        itemId: 'option.dashSep'
                    }, {
                        itemId: 'option.launchFarm',
                        text: 'Launch',
                        iconCls: 'x-menu-icon-launch',
                        getVisibility: function(data) {
                            return data.status == 0 && Scalr.isAllowed('FARMS', 'launch');
                        },
                        request: {
                            confirmBox: {
                                type: 'launch',
                                msg: 'Are you sure want to launch farm "{name}" ?'
                            },
                            processBox: {
                                type: 'launch',
                                msg: 'Launching farm ...'
                            },
                            url: '/farms/xLaunch/',
                            dataHandler: function (data) {
                                return { farmId: data['id'] };
                            },
                            success: function () {
                                store.load();
                            }
                        }
                    }, {
                        itemId: 'option.terminateFarm',
                        iconCls: 'x-menu-icon-terminate',
                        text: 'Terminate',
                        getVisibility: function(data) {
                            return data.status == 1 && Scalr.isAllowed('FARMS', 'terminate');
                        },
                        request: {
                            processBox: {
                                type:'action'
                            },
                            url: '/farms/xGetTerminationDetails/',
                            dataHandler: function (data) {
                                return { farmId: data['id'] };
                            },
                            success: function (data) {
                                Scalr.Request({
                                    confirmBox: {
                                        type: 'terminate',
                                        disabled: data.isMongoDbClusterRunning || false,
                                        msg: 'If you have made any modifications to your instances in <b>' + data['farmName'] + '</b> after launching, you may wish to save them as a machine image by creating a server snapshot.',
                                        multiline: true,
                                        formWidth: 740,
                                        form: [{
                                            hidden: !data.isMongoDbClusterRunning,
                                            xtype: 'displayfield',
                                            cls: 'x-form-field-warning',
                                            value: 'You currently have some Mongo instances in this farm. <br> Terminating it will result in <b>TOTAL DATA LOSS</b> (yeah, we\'re serious).<br/> Please <a href=\'#/services/mongodb/status?farmId='+data.farmId+'\'>shut down the mongo cluster</a>, then wait, then you\'ll be able to terminate the farm or just use force termination option (that will remove all mongodb data).'
                                        }, {
                                            hidden: !data.isMysqlRunning,
                                            xtype: 'displayfield',
                                            cls: 'x-form-field-warning',
                                            value: 'Server snapshot will not include database data. You can create db data bundle on database manager page.'
                                        }, {
                                            xtype: 'fieldset',
                                            title: 'Synchronization settings',
                                            hidden: true
                                        }, {
                                            xtype: 'fieldset',
                                            title: 'Termination options',
                                            defaults: {
                                                anchor: '100%'
                                            },
                                            items: [{
                                                xtype: 'checkbox',
                                                boxLabel: 'Forcefully terminate mongodb cluster (<b>ALL YOUR MONGODB DATA ON THIS FARM WILL BE REMOVED</b>)',
                                                name: 'forceMongoTerminate',
                                                hidden: !data.isMongoDbClusterRunning,
                                                listeners: {
                                                    change: function () {
                                                        if (this.checked)
                                                            this.up().up().up().down('#buttonOk').enable();
                                                        else
                                                            this.up().up().up().down('#buttonOk').disable();
                                                    }
                                                }
                                            }, {
                                                xtype: 'checkbox',
                                                boxLabel: 'Do not terminate a farm if synchronization fail on any role',
                                                name: 'unTermOnFail',
                                                hidden: true
                                            }, {
                                                xtype: 'checkbox',
                                                boxLabel: 'Delete DNS zone from nameservers. It will be recreated when the farm is launched.',
                                                name: 'deleteDNSZones'
                                            },/* {
                                                xtype: 'checkbox',
                                                boxLabel: 'Delete cloud objects (EBS, Elastic IPs, etc)',
                                                name: 'deleteCloudObjects'
                                            }, */{
                                                xtype: 'checkbox',
                                                checked: false,
                                                disabled: !!data.isRabbitMQ,
                                                boxLabel: 'Skip all shutdown routines (Do not process the BeforeHostTerminate event)',
                                                name: 'forceTerminate'
                                            },{
                                                xtype: 'displayfield',
                                                cls: 'x-form-field-info',
                                                margin: '12 0 0',
                                                hidden: !data.isRabbitMQ,
                                                value: '<b>Skiping all shutdown routines</b> is not allowed for farms with RabbitMQ role'
                                            }]
                                        }]
                                    },
                                    processBox: {
                                        type: 'terminate'
                                    },
                                    url: '/farms/xTerminate',
                                    params: {farmId: data.farmId},
                                    success: function () {
                                        store.load();
                                    }
                                });
                            }
                        }
                    }, {
                        xtype: 'menuseparator',
                        itemId: 'option.controlSep'
                    }, {
                        itemId: 'option.info',
                        iconCls: 'x-menu-icon-info',
                        text: 'Extended information',
                        href: "#/farms/{id}/extendedInfo"
                    }, {
                        itemId: 'option.usageStats',
                        text: 'Usage statistics',
                        iconCls: 'x-menu-icon-statsusage',
                        href: '#/statistics/serversusage?farmId={id}',
                        getVisibility: function() {
                            return Scalr.isAllowed('FARMS_STATISTICS');
                        }
                    }, {
                        itemId: 'option.loadStats',
                        iconCls: 'x-menu-icon-statsload',
                        text: 'Load statistics',
                        href: '#/monitoring/view?farmId={id}',
                        getVisibility: function(data) {
                            return data.status != 0;
                        }
                    }, {
                        xtype: 'menuseparator',
                        itemId: 'option.mysqlSep'
                    }, {
                        itemId: 'option.mysql',
                        iconCls: 'x-menu-icon-mysql',
                        text: 'MySQL status',
                        href: "#/dbmsr/status?farmId={id}&type=mysql",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havemysqlrole;
                        }
                    }, {
                        itemId: 'option.mysql2',
                        iconCls: 'x-menu-icon-mysql',
                        text: 'MySQL status',
                        href: "#/db/manager/dashboard?farmId={id}&type=mysql2",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havemysql2role;
                        }
                    }, {
                        itemId: 'option.percona',
                        iconCls: 'x-menu-icon-percona',
                        text: 'Percona server status',
                        href: "#/db/manager/dashboard?farmId={id}&type=percona",
                        getVisibility: function(data) {
                            return data.status != 0 && data.haveperconarole;
                        }
                    }, {
                        itemId: 'option.postgresql',
                        iconCls: 'x-menu-icon-postgresql',
                        text: 'PostgreSQL status',
                        href: "#/db/manager/dashboard?farmId={id}&type=postgresql",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havepgrole;
                        }
                    }, {
                        itemId: 'option.redis',
                        iconCls: 'x-menu-icon-redis',
                        text: 'Redis status',
                        href: "#/db/manager/dashboard?farmId={id}&type=redis",
                        getVisibility: function(data) {
                            return data.status != 0 && data.haveredisrole;
                        }
                    }, {
                        itemId: 'option.mariadb',
                        iconCls: 'x-menu-icon-mariadb',
                        text: 'MariaDB status',
                        href: "#/db/manager/dashboard?farmId={id}&type=mariadb",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havemariadbrole;
                        }
                    }, {
                        itemId: 'option.rabbitmq',
                        iconCls: 'x-menu-icon-rabbitmq',
                        text: 'RabbitMQ status',
                        href: "#/services/rabbitmq/status?farmId={id}",
                        getVisibility: function(data) {
                            return data.status != 0 && data.haverabbitmqrole;
                        }
                    }, {
                        itemId: 'option.mongodb',
                        iconCls: 'x-menu-icon-mongodb',
                        text: 'MongoDB status',
                        href: "#/services/mongodb/status?farmId={id}",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havemongodbrole;
                        }
                    }, {
                        itemId: 'option.script',
                        iconCls: 'x-menu-icon-execute',
                        text: 'Execute script',
                        href: '#/scripts/execute?farmId={id}',
                        getVisibility: function(data) {
                            return data.status != 0;
                        }
                    }, {
                        itemId: 'option.event',
                        iconCls: 'x-menu-icon-execute',
                        text: 'Fire event',
                        href: '#/scripts/events/fire?farmId={id}',
                        getVisibility: function(data) {
                            return data.status != 0 && Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'fire');
                        }
                    }, {
                        xtype: 'menuseparator',
                        itemId: 'option.logsSep'
                    }, {
                        itemId: 'option.ssh_key',
                        text: 'Download SSH private key',
                        iconCls: 'x-menu-icon-downloadprivatekey',
                        href: '#/sshkeys/view?farmId={id}'
                    }, {
                        itemId: 'option.alerts',
                        iconCls: 'x-menu-icon-alerts',
                        text: 'Alerts',
                        href: "#/alerts/view?farmId={id}",
                        getVisibility: function() {
                            return Scalr.isAllowed('FARMS_ALERTS');
                        }
                    },{
                        xtype: 'menuseparator',
                        itemId: 'option.editSep'
                    }, {
                        itemId: 'option.edit',
                        iconCls: 'x-menu-icon-configure',
                        text: 'Configure',
                        href: '#/farms/{id}/edit'
                    }, {
                        itemId: 'option.clone',
                        iconCls: 'x-menu-icon-clone',
                        text: 'Clone',
                        getVisibility: function() {
                            return Scalr.isAllowed('FARMS', 'clone');
                        },
                        request: {
                            confirmBox: {
                                type: 'clone',
                                msg: 'Are you sure want to clone farm "{name}" ?'
                            },
                            processBox: {
                                type: 'action',
                                msg: 'Cloning farm ...'
                            },
                            url: '/farms/xClone/',
                            dataHandler: function (data) {
                                return { farmId: data['id'] };
                            },
                            success: function () {
                                store.load();
                            }
                        }
                    }, {
                        itemId: 'option.delete',
                        iconCls: 'x-menu-icon-delete',
                        text: 'Delete',
                        request: {
                            confirmBox: {
                                type: 'delete',
                                msg: 'Are you sure want to remove farm "{name}" ?'
                            },
                            processBox: {
                                type: 'delete',
                                msg: 'Removing farm ...'
                            },
                            url: '/farms/xRemove/',
                            dataHandler: function (data) {
                                return { farmId: data['id'] };
                            },
                            success: function () {
                                store.load();
                            }
                        }
                    },{
                        xtype: 'menuseparator'
                    }, {
                        itemId: 'option.events',
                        text: 'Event Log',
                        iconCls: 'x-menu-icon-events',
                        href: '#/logs/events?farmId={id}'
                    }, {
                        itemId: 'option.logs',
                        iconCls: 'x-menu-icon-logs',
                        text: 'System Log',
                        href: "#/logs/system?farmId={id}"
                    }, {
                        itemId: 'option.scripting_logs',
                        iconCls: 'x-menu-icon-logs',
                        text: 'Scripting Log',
                        href: "#/logs/scripting?farmId={id}"
	    			}]
                }
			}
		],
		listeners: {
			itemclick: function(grid, record, item, index, e) {
				if (e.getTarget('div.scalr-ui-farms-view-lock')) {
					Scalr.Request({
						confirmBox: {
							type: 'action',
							msg: 'Are you sure want to unlock farm "' + record.get('name') + '" ?'
						},
						processBox: {
							type: 'action',
							msg: 'Unlocking farm ...'
						},
						url: '/farms/xUnlock/',
						params: {
							farmId: record.get('id')
						},
						success: function () {
							store.load();
						}
					});
				} else if (e.getTarget('div.scalr-ui-farms-view-unlock')) {
					var message = 'Only farm owner (' + record.get('created_by_email') + ') can unlock this farm';
					Scalr.Request({
						confirmBox: {
							type: 'action',
							msg: 'Are you sure you would like to lock farm "' + record.get('name') + '"? This will prevent the farm from being launched, terminated or removed, along with any configuration changes.',
							formWidth: 600,
							formValidate: true,
							form: [{
								xtype: 'fieldset',
                                cls: 'x-fieldset-separator-none',
								defaults: {
									anchor: '100%'
								},
								items: [{
									xtype: 'textarea',
									fieldLabel: 'Comment (required)',
									labelWidth: 65,
									name: 'comment',
									allowBlank: false
								}, {
									xtype: 'checkbox',
									checked: true,
									hidden: !record.get('created_by_email'),
									boxLabel: message,
									name: 'restrict'
								}]
							}]
						},
						processBox: {
							type: 'action',
							msg: 'Locking farm ...'
						},
						params: {
							farmId: record.get('id')
						},
						url: '/farms/xLock/',
						success: function () {
							store.load();
						}
					});

				}
			}
		},

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			beforeItems: [{
                text: 'Add farm',
                cls: 'x-btn-green-bg',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/farms/build');
				}
			}],
			items: [{
				xtype: 'filterfield',
                width: 300,
				store: store,
                form: filterFieldForm
            }, ' ', {
				xtype: 'button',
				text: 'Show only my farms',
				enableToggle: true,
                width: 170,
                pressed: Scalr.storage.get('grid-farms-view-show-only-my-farms'),
				toggleHandler: function (field, checked) {
					store.proxy.extraParams.showOnlyMy = checked ? '1' : '';
                    Scalr.storage.set('grid-farms-view-show-only-my-farms', checked);
					store.loadPage(1);
				},
                listeners: {
                    added: function() {
                        store.proxy.extraParams.showOnlyMy = this.pressed ? '1' : '';
                    }
                }
			}]
		}]
	});
	return grid;
});

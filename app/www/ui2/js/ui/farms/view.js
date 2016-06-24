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
            {name: 'accountId', type: 'int'},
            'name', 'status', 'added', 'runningServers', 'suspendedServers', 'nonRunningServers', 'rolesCnt', 'zonesCnt',
            'havemysqlrole','shortcuts', 'havepgrole', 'haveredisrole', 'haverabbitmqrole', 'havemongodbrole', 'havemysql2role', 'havemariadbrole',
            'haveperconarole', 'lock', 'lockComment', 'ownerId', 'ownerEmail', 'farmTeams', 'alertsCnt', { name: 'lease', defaultValue: false }, 'leaseMessage',
            'farmTeamIdPerm', 'farmOwnerIdPerm'
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
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Farms',
            menuHref: '#/farms',
            menuFavorite: true
        },
        store: store,
        stateId: 'grid-farms-view',
        stateful: true,
        plugins: [ 'gridstore', 'applyparams' ],

        disableSelection: true,
        viewConfig: {
            emptyText: 'No farms found',
            loadingText: 'Loading farms ...'
        },

        columns: [
            { text: "ID", width: 90, dataIndex: 'id', sortable: true },
            { text: "Farm", flex: 1, dataIndex: 'name', sortable: true, xtype: 'templatecolumn', tpl:
                '{name}' +
                '<tpl if="lease && status == 1">&nbsp;&nbsp;<a href="#/farms/{id}/extendedInfo">' +
                    '<div class="x-grid-icon x-grid-icon-leased<tpl if="lease == &quot;Expire&quot;">-expire</tpl>"' +
                    '<tpl if="lease == &quot;Expire&quot;">' +
                        ' data-anchor="left" data-qalign="l-r" data-qtip="{leaseMessage}" data-qwidth="340"' +
                    '</tpl>' +
                    '></div>' +
                '</a></tpl>' +
                '<tpl if="lock"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-locked" style="margin-left: 10px" data-qtip="{lockComment:htmlEncode}"></div></tpl>'
            },
            { text: "Added", flex: 1, dataIndex: 'dtadded', sortable: true, xtype: 'templatecolumn', tpl: '{added}' },
            { text: "Owner", flex: 1, dataIndex: 'ownerEmail', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="!ownerId"><div class="x-grid-icon x-grid-icon-warning x-grid-icon-simple" data-qtip="Owner for this farm has been removed."></div> </tpl>' +
                '{ownerEmail}'
            },
            { text: "Team", flex: 1, dataIndex: 'farmTeams', sortable: false },
            { text: "Servers", width: 90, dataIndex: 'servers', sortable: false, align: 'center', xtype: 'templatecolumn',
                tpl: new Ext.XTemplate(
                    '<a href="#/servers?farmId={id}" class="x-grid-big-href"><span data-anchor="right" data-qalign="r-l" data-qtip="{[this.getTooltipHtml(values)]}" data-qwidth="280">' +
                        '<span style="color:#28AE1E;">{runningServers}</span>' +
                        '/<span style="color:#329FE9;">{suspendedServers}</span>' +
                        '/<span style="color:#bbb;">{nonRunningServers}</span>' +
                    '</span></a>',
                    {
                        getTooltipHtml: function(values) {
                            return Ext.String.htmlEncode(
                                '<span style="color:#00CC00;">' + values.runningServers + '</span> &ndash; Initializing & Running servers<br/>' +
                                '<span style="color:#4DA6FF;">' + values.suspendedServers + '</span> &ndash; Suspended servers<br/>' +
                                '<span style="color:#bbb;">' + values.nonRunningServers + '</span> &ndash; Terminated servers'
                            );
                        }
                    }
                )
            },
            { text: "Roles", width: 70, dataIndex: 'rolesCnt', sortable: false, align:'center', xtype: 'templatecolumn',
                tpl: '<a href="#/farms/{id}/roles" class="x-grid-big-href">{rolesCnt}</a>'
            },
            { text: "DNS zones", width: 100, dataIndex: 'zonesCnt', sortable: false, align:'center', xtype: 'templatecolumn',
                hidden: !Scalr.flags['dnsGlobalEnabled'],
                hideable: Scalr.flags['dnsGlobalEnabled'],
                tpl:
                    '<tpl if="Scalr.isAllowed(\'DNS_ZONES\')">' +
                        '<a href="#/dnszones?farmId={id}" class="x-grid-big-href">{zonesCnt}</a>' +
                    '<tpl else>' +
                        '<span class="x-grid-big-href">{zonesCnt}</span>' +
                    '</tpl>'
            },
            { text: "Status", width: 120, minWidth: 120, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'farm'},
            { text: "Alerts", width: 90, dataIndex: 'alertsCnt', align:'center', sortable: false, xtype: 'templatecolumn', tpl:
                '<tpl if="status == 1">' +
                    '<tpl if="alertsCnt &gt; 0">' +
                        '<a href="#/alerts?farmId={id}&status=failed"><span class="x-grid-big-href" style="color:red;">{alertsCnt}</span></a>' +
                    '<tpl else>' +
                        '<div class="x-grid-icon x-grid-icon-ok x-grid-icon-simple"></div>' +
                    '</tpl>'+
                '<tpl else>' +
                    '&mdash;' +
                '</tpl>'
            }, {
                xtype: 'optionscolumn',
                menu: {
                    xtype: 'actionsmenu',
                    listeners: {
                        beforeshow: function () {
                            var me = this,
                                shortcuts = me.data['shortcuts'] || [];
                            me.items.each(function (item) {
                                if (item.isshortcut) {
                                    me.remove(item);
                                }
                            });

                            if (shortcuts.length) {
                                me.add({
                                    xtype: 'menuseparator',
                                    isshortcut: true
                                });

                                Ext.Array.each(shortcuts, function (shortcut) {
                                    if (typeof(shortcut) != 'function') {
                                        me.add({
                                            isshortcut: true,
                                            iconCls: 'x-menu-icon-execute',
                                            text: 'Execute ' + shortcut.name,
                                            href: '#/scripts/execute?shortcutId=' + shortcut.id
                                        });
                                    }
                                });
                            }
                        }
                    },
                    items: [{
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
                        xtype: 'menuseparator'
                    }, {
                        text: 'Launch',
                        iconCls: 'x-menu-icon-launch',
                        getVisibility: function(data) {
                            return data.status == 0 &&
                                   Scalr.isFarmAllowed('launch-terminate', data);
                        },
                        showAsQuickAction: 1,
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
                        iconCls: 'x-menu-icon-terminate',
                        text: 'Terminate',
                        getVisibility: function(data) {
                            return data.status == 1 &&
                                   Scalr.isFarmAllowed('launch-terminate', data);
                        },
                        showAsQuickAction: 2,
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
                        xtype: 'menuseparator'
                    }, {
                        iconCls: 'x-menu-icon-information',
                        text: 'Extended information',
                        href: "#/farms/{id}/extendedInfo",
                        showAsQuickAction: 6
                    }, {
                        text: 'Cost Analytics',
                        iconCls: 'x-menu-icon-analytics',
                        href: '#/analytics/farms?farmId={id}',
                        getVisibility: function() {
                            return Scalr.flags['analyticsEnabled'] && Scalr.isAllowed('ANALYTICS_ENVIRONMENT');
                        }
                    }, {
                        iconCls: 'x-menu-icon-statsload',
                        text: 'Load statistics',
                        href: '#/monitoring?farmId={id}',
                        getVisibility: function(data) {
                            return data.status != 0 &&
                                   Scalr.isFarmAllowed('statistics', data);
                        }
                    }, {
                        xtype: 'menuseparator'
                    }, {
                        iconCls: 'x-menu-icon-mysql',
                        text: 'MySQL status',
                        href: "#/dbmsr/status?farmId={id}&type=mysql",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havemysqlrole && Scalr.isAllowed('DB_DATABASE_STATUS');
                        }
                    }, {
                        iconCls: 'x-menu-icon-mysql',
                        text: 'MySQL status',
                        href: "#/db/manager/dashboard?farmId={id}&type=mysql2",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havemysql2role && Scalr.isAllowed('DB_DATABASE_STATUS');
                        }
                    }, {
                        iconCls: 'x-menu-icon-percona',
                        text: 'Percona server status',
                        href: "#/db/manager/dashboard?farmId={id}&type=percona",
                        getVisibility: function(data) {
                            return data.status != 0 && data.haveperconarole && Scalr.isAllowed('DB_DATABASE_STATUS');
                        }
                    }, {
                        iconCls: 'x-menu-icon-postgresql',
                        text: 'PostgreSQL status',
                        href: "#/db/manager/dashboard?farmId={id}&type=postgresql",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havepgrole && Scalr.isAllowed('DB_DATABASE_STATUS');
                        }
                    }, {
                        iconCls: 'x-menu-icon-redis',
                        text: 'Redis status',
                        href: "#/db/manager/dashboard?farmId={id}&type=redis",
                        getVisibility: function(data) {
                            return data.status != 0 && data.haveredisrole && Scalr.isAllowed('DB_DATABASE_STATUS');
                        }
                    }, {
                        iconCls: 'x-menu-icon-mariadb',
                        text: 'MariaDB status',
                        href: "#/db/manager/dashboard?farmId={id}&type=mariadb",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havemariadbrole && Scalr.isAllowed('DB_DATABASE_STATUS');
                        }
                    }, {
                        iconCls: 'x-menu-icon-rabbitmq',
                        text: 'RabbitMQ status',
                        href: "#/services/rabbitmq/status?farmId={id}",
                        getVisibility: function(data) {
                            return data.status != 0 && data.haverabbitmqrole && Scalr.isAllowed('SERVICES_RABBITMQ');
                        }
                    }, {
                        iconCls: 'x-menu-icon-mongodb',
                        text: 'MongoDB status',
                        href: "#/services/mongodb/status?farmId={id}",
                        getVisibility: function(data) {
                            return data.status != 0 && data.havemongodbrole;
                        }
                    }, {
                        iconCls: 'x-menu-icon-execute',
                        text: 'Execute script',
                        href: '#/scripts/execute?farmId={id}',
                        getVisibility: function(data) {
                            return data.status != 0 &&
                                   Scalr.isAllowed('SCRIPTS_ENVIRONMENT', 'execute') &&
                                   Scalr.isFarmAllowed('servers', data);
                        }
                    }, {
                        iconCls: 'x-menu-icon-fireevent',
                        text: 'Fire event',
                        href: '#/scripts/events/fire?farmId={id}',
                        getVisibility: function(data) {
                            return data.status != 0 &&
                                   Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'fire') &&
                                   Scalr.isFarmAllowed('servers', data);
                        }
                    }, {
                        xtype: 'menuseparator'
                    }, {
                        text: 'Download SSH private key',
                        iconCls: 'x-menu-icon-downloadprivatekey',
                        href: '#/sshkeys?farmId={id}',
                        getVisibility: function(data) {
                            return data.status != 0 &&
                                   Scalr.isAllowed('SECURITY_SSH_KEYS') &&
                                   Scalr.isFarmAllowed('servers', data);
                        }
                    }, {
                        iconCls: 'x-menu-icon-alerts',
                        text: 'Alerts',
                        href: "#/alerts?farmId={id}",
                        getVisibility: function(data) {
                            return Scalr.isFarmAllowed(undefined, data);
                        }
                    }, {
                        xtype: 'menuseparator'
                    }, {
                        iconCls: 'x-menu-icon-configure',
                        text: 'Configure',
                        showAsQuickAction: 3,
                        href: '#/farms/designer?farmId={id}',
                        getVisibility: function(data) {
                            return Scalr.isFarmAllowed('update', data);
                        }
                    }, {
                        iconCls: 'x-menu-icon-unlock',
                        text: 'Unlock farm',
                        showAsQuickAction: 4,
                        getVisibility: function(data) {
                            return !!data['lock'] &&
                                   Scalr.isFarmAllowed('update', data);
                        },
                        request: {
                            confirmBox: {
                                type: 'action',
                                msg: 'Are you sure want to unlock farm "{name}" ?'
                            },
                            processBox: {
                                type: 'action',
                                msg: 'Unlocking farm ...'
                            },
                            url: '/farms/xUnlock/',
                            dataHandler: function(data) {
                                return { farmId: data['id'] }
                            },
                            success: function () {
                                store.load();
                            }
                        }
                    }, {
                        iconCls: 'x-menu-icon-lock',
                        text: 'Lock farm',
                        showAsQuickAction: 5,
                        getVisibility: function(data) {
                            return !data['lock'] &&
                                   Scalr.isFarmAllowed('update', data);
                        },
                        menuHandler: function(data) {
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    msg: 'Are you sure you would like to lock farm "' + data['name'] + '"? This will prevent the farm from being launched, terminated or removed, along with any configuration changes.',
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
                                            emptyText: 'Comment (required)',
                                            name: 'comment',
                                            allowBlank: false
                                        }, {
                                            xtype: 'radiogroup',
                                            columns: 1,
                                            items: [{
                                                boxLabel: 'Anyone with access can unlock this Farm',
                                                inputValue: '',
                                                name: 'restrict'
                                            }, {
                                                boxLabel: 'Only the Farm Owner "' + data['ownerEmail'] + '" can unlock this Farm',
                                                inputValue: 'owner',
                                                checked: data['ownerEmail'] && !data['farmTeams'],
                                                name: 'restrict'
                                            }, {
                                                boxLabel: 'Only members of the Farm’s Teams "' + data['farmTeams'] + '" can unlock this Farm',
                                                inputValue: 'team',
                                                checked: !!data['farmTeams'],
                                                hidden: !data['farmTeams'],
                                                name: 'restrict'
                                            }]
                                        }]
                                    }]
                                },
                                processBox: {
                                    type: 'action',
                                    msg: 'Locking farm ...'
                                },
                                params: {
                                    farmId: data['id']
                                },
                                url: '/farms/xLock/',
                                success: function () {
                                    store.load();
                                }
                            });
                        }
                    }, {
                        iconCls: 'x-menu-icon-clone',
                        text: 'Clone',
                        getVisibility: function(data) {
                            return Scalr.isFarmAllowed('clone', data);
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
                        iconCls: 'x-menu-icon-delete',
                        text: 'Delete',
                        getVisibility: function(data) {
                            return Scalr.isFarmAllowed('delete', data);
                        },
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
                    }, {
                        xtype: 'menuseparator'
                    }, {
                        text: 'Event Log',
                        iconCls: 'x-menu-icon-events',
                        href: '#/logs/events?farmId={id}',
                        getVisibility: function(data) {
                            return Scalr.isAllowed('LOGS_EVENT_LOGS');
                        }
                    }, {
                        iconCls: 'x-menu-icon-logs',
                        text: 'System Log',
                        href: "#/logs/system?farmId={id}",
                        getVisibility: function(data) {
                            return Scalr.isAllowed('LOGS_SYSTEM_LOGS');
                        }
                    }, {
                        iconCls: 'x-menu-icon-logs',
                        text: 'Orchestration Log',
                        href: "#/logs/orchestration?farmId={id}",
                        getVisibility: function(data) {
                            return Scalr.isAllowed('LOGS_ORCHESTRATION_LOGS');
                        }
                    }]
                }
            }
        ],

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            beforeItems: [{
                text: 'New farm',
                cls: 'x-btn-green',
                hidden: !Scalr.isAllowed('OWN_FARMS', 'create'),
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/farms/designer');
                }
            }],
            items: [{
                xtype: 'filterfield',
                width: 300,
                store: store,
                form: filterFieldForm
            }, ' ', {
                xtype: 'cyclealt',
                name: 'owner',
                getItemIconCls: false,
                width: 200,
                cls: 'x-btn-compressed x-btn-font-size-default',
                style: 'font-size: 12px',
                changeHandler: function (comp, item) {
                    store.applyProxyParams({
                        owner: item.value
                    });
                },
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: [{
                        text: 'All Farms',
                        value: null
                    },{
                        text: 'Farms I own',
                        value: 'me'
                    },{
                        text: 'Farms my Teams own',
                        value: 'team'
                    }]
                }
            }]
        }]
    });

    return grid;
});

Scalr.regPage('Scalr.ui.servers.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'cloud_location', 'flavor', 'instance_type_name', 'cloud_server_id', 'excluded_from_dns', 'server_id', 'remote_ip', 'role_alias',
            'local_ip', 'status', 'platform', 'farm_name', 'role_name', 'index', 'role_id', 'farm_id', 'farm_roleid',
            'uptime', 'ismaster', 'os_family', { name: 'has_eip', defaultValue: false }, 'is_szr', 'cluster_position', 'agent_version', 'agent_update_needed', 'agent_update_manual',
            'isInitFailed', 'la_server', 'launch_error', 'alerts', 'cluster_role', 'is_locked', 'hostname', 'termination_error', 'isScalarized', 'scalingEnabled', 'suspendEc2Locked', 'suspendHidden'
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/servers/xListServers/'
        },
        remoteSort: true
    });

    var laStore = Ext.create('store.store', {
        fields: [ 'server_id', 'la', 'time' ],
        proxy: 'object'
    });
    
    var serverMenu = Ext.widget({
        xtype: 'servermenu',
        moduleParams: moduleParams,
        loadParams: loadParams,
        listeners: {
            actioncomplete: function() {
                store.load();
            }
        }
    });

    var updateHandler = {
        id: 0,
        timeout: 15000,
        running: false,
        schedule: function (run) {
            this.running = Ext.isDefined(run) ? run : this.running;

            clearTimeout(this.id);
            if (this.running)
                this.id = Ext.Function.defer(this.start, this.timeout, this);
        },
        start: function () {
            var list = [];

            if (! this.running)
                return;

            store.each(function (r) {
                if (r.get('status') != 'Terminated')
                    list.push(r.get('server_id'));
            });

            if (! list.length) {
                this.schedule();
                return;
            }

            Scalr.Request({
                url: '/servers/xListServersUpdate/',
                params: {
                    servers: Ext.encode(list)
                },
                headers: {
                    'Scalr-Autoload-Request': 1
                },
                success: function (data) {
                    for (var serverId in data.servers) {
                        var r = store.findRecord('server_id', serverId);
                        if (r) {
                            r.set(data.servers[serverId]);
                            r.commit();
                        }
                    }
                    this.schedule();
                },
                failure: function () {
                    this.schedule();
                },
                scope :this
            });
        }
    };
    store.on('load', function() {
        // reset to start
        this.schedule(true);
    }, updateHandler);

    var laHandler = {
        id: 0,
        timeout: 60000,
        running: false,
        cache: {},
        updateEvent: function () {
            laStore.removeAll();
            this.schedule(this.running, true);
        },
        schedule: function (run, start) {
            this.running = Ext.isDefined(run) ? run : this.running;
            start = start == true ? 0 : this.timeout;

            clearTimeout(this.id);
            if (this.running)
                this.id = Ext.Function.defer(this.start, start, this);
        },
        start: function () {
            var dt = new Date();

            if (! this.running)
                return;

            for (var i = 0; i < store.getCount(); i++) {
                var r = store.getAt(i);

                if (r.get('status') == 'Running' && r.get('isScalarized') == 1) {
                    var la = laStore.findRecord('server_id', r.get('server_id'));

                    if (!la || (la.get('time') > dt)) {
                        r.set('la_server', '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-colored-status-running white" />');
                        r.commit();
                        if (! la)
                            la = laStore.add({ server_id: r.get('server_id') })[0];

                        Scalr.Request({
                            url: '/servers/xServerGetLa/',
                            params: { serverId: r.get('server_id') },
                            headers: {
                                'Scalr-Autoload-Request': 1
                            },
                            success: function (data) {
                                r.set({
                                    'la_server': data.la
                                });
                                r.commit();
                                la.set({
                                    'time': Ext.Date.add(new Date(), Date.MINUTE, 3),
                                    'la': data.la
                                });
                                la.commit();
                                Ext.Function.defer(this.start, 50, this);
                            },
                            failure: function (data) {
                                r.set({
                                    'la_server': '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon-warning" title="' + (data && data.errorMessage || 'Cannot proceed request') + '">'
                                });
                                r.commit();
                                la.set({
                                    'time': Ext.Date.add(new Date(), Date.MINUTE, 3),
                                    'la': data && data.la
                                });
                                la.commit();
                                Ext.Function.defer(this.start, 50, this);
                            },
                            scope: this
                        });
                        return false;
                    } else {
                        r.set('la_server', la.get('la'));
                        r.commit();
                    }
                } else {
                    r.set('la_server', r.get('isScalarized') == 1 ? '-' : 'N/A');
                    r.commit();
                }
            }
            this.schedule();
        }
    };
    store.on('load', laHandler.updateEvent, laHandler);

    return Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Servers',
            menuHref: '#/servers',
            menuFavorite: true
        },
        store: store,
        stateId: 'grid-servers-view',
        stateful: true,
        plugins: [ 'gridstore', 'applyparams' ],

        viewConfig: {
            emptyText: 'No servers found',
            loadingText: 'Loading servers ...',
            getRowClass: function (record, rowIndex, rowParams) {
                //TODO: replace == 9999 with > 0 when ready
                return (record.get('isScalarized') == 1 && !record.get('is_szr') && record.get('status') == 'Running') || (record.get('alerts') > 0) ? 'x-grid-row-color-red' : '';
            },
            listeners: {
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('a.updateAgent')) {
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            url: e.getTarget('a.updateAgent').href
                        })
                        e.preventDefault();
                    } else if (e.getTarget('img.lock')) {
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            confirmBox: {
                                type: 'action',
                                msg: 'Reset disableAPITermination flag for server "' + record.get('server_id') + '"?'
                            },
                            url: '/servers/xLock',
                            params: {
                                serverId: record.get('server_id')
                            },
                            success: function() {
                                store.load();
                            }
                        })
                    }
                }
            }
        },

        columns: [
            { header: "Cloud", width: 85, dataIndex: 'platform', sortable: true, align: 'left', xtype: 'templatecolumn', tpl:
                '<img class="x-icon-platform-small x-icon-platform-small-{platform}" style="margin-bottom: 2px" title="{platform}" src="' + Ext.BLANK_IMAGE_URL + '"/>'
            },
            { header: "Farm & role", flex: 2, dataIndex: 'farm_name', sortable: true, xtype: 'templatecolumn',
                multiSort: function(store, direction) {
                    store.sort([{
                        property: 'farm_name',
                        direction: direction
                    }, {
                        property: 'role_alias',
                        direction: direction
                    }, {
                        property: 'index',
                        direction: direction
                    }]);
                }, tpl:
                '<tpl if="farm_id">' +
                    '<a href="#/farms?farmId={farm_id}" title="Farm {farm_name:htmlEncode}">{farm_name}</a>' +
                    '<tpl if="role_alias">' +
                        '&nbsp;&rarr;&nbsp;<a href="#/farms/{farm_id}/roles/{farm_roleid}/view" title="Role {role_alias}">{role_alias}</a> ' +
                    '</tpl>' +
                    '<tpl if="role_name && !role_alias">' +
                        '&nbsp;&rarr;&nbsp;<a href="#/farms/{farm_id}/roles/{farm_roleid}/view" title="Role {role_name}">{role_name}</a> ' +
                    '</tpl>' +
                    '<tpl if="!role_name && !role_alias">' +
                        '&nbsp;&rarr;&nbsp;*removed role*&nbsp;' +
                    '</tpl>' +
                    '#<a href="#/servers?serverId={server_id}">{index}</a>'+
                '</tpl>' +
                '<tpl if="cluster_role"> ({cluster_role})</tpl>' +
                '<tpl if="cluster_position"> ({cluster_position})</tpl>' +
                '<tpl if="! farm_id">&mdash;</tpl>'
            },
            { header: "Server ID", flex: 1, dataIndex: 'server_id', sortable: false, xtype: 'templatecolumn', tpl: new Ext.XTemplate(
                '<tpl if="isScalarized == 1 && !is_szr && status == \'Running\'">' +
                    '<a href="http://blog.scalr.com/post/53324376570/ami-scripts" target="_blank"><div class="x-grid-icon x-grid-icon-error x-grid-icon-simple"  title="This server using old (deprecated) scalr agent. Please click here for more informating about how to upgrade it."></div></a>&nbsp;' +
                '</tpl>' +
                '<tpl if="alerts &gt; 0">' +
                    '<a href="#/alerts?serverId={server_id}"><div class="x-grid-icon x-grid-icon-error x-grid-icon-simple" title="{alerts} alert(s). Click for more information."></div></a>&nbsp;' +
                '</tpl>' +
                '<a href="#/servers/{server_id}/dashboard">{[this.serverId(values.server_id)]}</a>'
                ,{
                    serverId: function(id) {
                        var values = id.split('-');
                        return values[0] + '-...-' + values[values.length - 1];
                    }
                })
            },
            { header: "Hostname", flex: 1, dataIndex: 'hostname', sortable: false, hidden: true, xtype: 'templatecolumn',
                tpl: '<tpl if="hostname">{hostname}<tpl else>&mdash;</tpl>'
            },
            { header: "Cloud Server ID", width: 100, dataIndex: 'cloud_server_id', sortable: false, hidden: true, xtype:'templatecolumn',  tpl:
                '<tpl if="cloud_server_id">{cloud_server_id}' +
                '<tpl else>&mdash;</tpl>'
            },
            { header: "Cloud Location", width: 100, dataIndex: 'cloud_location', sortable: false, hidden: true },
            { header: "Status", minWidth: 160, width: 160, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'server', qtipConfig: {width: 320}},
            { header: 'Type', width: 100, dataIndex: 'flavor', sortable: false, hidden: true, xtype: 'templatecolumn', tpl: '{[values.instance_type_name||values.flavor]}' },
            { header: "Public IP", width: 120, dataIndex: 'remote_ip_int', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="remote_ip">' +
                '<tpl if="has_eip"><span style="color:green;">{remote_ip} <img title="Elastic IP" src="/ui2/images/icons/elastic_ip.png" /></span></tpl><tpl if="!has_eip">{remote_ip}</tpl>' +
                '</tpl>'
            },
            { header: "Private IP", width: 120, dataIndex: 'local_ip_int', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="local_ip">{local_ip}</tpl>'
            },
            { header: "Uptime", width: 200, dataIndex: 'uptime', sortable: true },
            { header: "DNS", width: 48, dataIndex: 'excluded_from_dns', sortable: false, xtype: 'templatecolumn', align: 'center', 
                hidden: !Scalr.flags['dnsGlobalEnabled'],
                hideable: Scalr.flags['dnsGlobalEnabled'],
                tpl: '<tpl if="excluded_from_dns">&mdash;<tpl else><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div></tpl>'
            },
            { header: "LA", width: 60, dataIndex: 'la_server', itemId: 'la', sortable: false, hidden: true, align: 'center',
                listeners: {
                    hide: function () {
                        laHandler.schedule(false);
                    },
                    show: function () {
                        // grid column is hidden and may doesn't exist, let give it time. May be bug in 4.2
                        setTimeout(function () {
                            laHandler.schedule(true, true);
                        }, 100);
                    }
                }
            },
            { header: "Agent", width: 80, dataIndex: 'agent_version', sortable: false, xtype: 'templatecolumn',  align: 'center', tpl:
                '<tpl if="isScalarized == 1 && (status == &quot;Running&quot; || status == &quot;Initializing&quot;)">' +
                    '<tpl if="agent_update_needed">' +
                        '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-warning" data-qtip="Your Scalr Agent version <b>{agent_version}</b> is too old" style="cursor:default" />' +
                    '<tpl elseif="agent_update_manual">' +
                        '<a href="http://blog.scalr.com/post/53324376570/ami-scripts" target="_blank"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-error" data-qwidth="420" data-qtip="This server using old (deprecated) Scalr Agent <b>{agent_version}</b>. Please click for more informating about how to upgrade it."></a> ' +
                    '<tpl else>'+
                        '{agent_version}' +
                    '</tpl>'+
                '<tpl else>' +
                    '&mdash;' +
                '</tpl>'
            }, {
                xtype: 'optionscolumn',
                fillEmptyIcons: true,
                listeners: {
                    boxready: {
                        fn: function() {
                            var me = this,
                                grid = this.up('grid');
                            if (grid) {
                                grid.on('applyparams', function(params) {
                                    if (me.menu) {
                                        me.menu.loadParams = params;
                                    }
                                });
                            }
                        },
                        single: true
                    }
                },
                menu: serverMenu
            }
        ],

        selModel: {
            selType: 'selectedmodel',
            getVisibility: function(record) {
                var data = record.getData()
                return Ext.Array.contains(['Running', 'Initializing', 'Suspended'], data['status']) &&
                       Scalr.isFarmAllowed('servers', data);
            }
        },

        listeners: {
            activate: function () {
                updateHandler.schedule(true);
                laHandler.schedule(! this.headerCt.down('#la').isHidden(), true);
            },
            deactivate: function () {
                updateHandler.schedule(false);
                laHandler.schedule(false);
            },
            selectionchange: function(selModel, selection) {
                var toolbar = this.down('scalrpagingtoolbar'),
                    hasSelection = selection.length > 0,
                    buttons = {
                        reboot: {
                            status: hasSelection
                        },
                        terminate: {
                            status: hasSelection
                        },
                        suspend: {
                            status: hasSelection
                        },
                        resume: {
                            status: hasSelection
                        }
                    };
                if (hasSelection) {
                    Ext.each(selection, function(record) {
                        var data = record.getData();
                        Ext.Object.each(buttons, function(id, button){
                            var status = serverMenu['is' + Ext.String.capitalize(id) + 'Available'](data);
                            if (id === 'suspend') {
                                status = status && !data['suspendEc2Locked'];
                            }
                            if (button.status) {
                                button.status = status;
                            }
                            if (!status) {
                                button['ids'] = button['ids'] || [];
                                button['ids'].push(data['server_id']);
                            }
                        })
                    });
                }
                Ext.Object.each(buttons, function(id, button){
                    var btn = toolbar.down('#' + id);
                    btn.setDisabled(!button.status);
                    if (!button.status || !hasSelection) {
                        btn.setTooltip((!hasSelection ? 'Select servers to ' : 'Some of selected server(s) are not allowed to ') + id);
                    } else {
                        btn.setTooltip(Ext.String.capitalize(id) + ' selected servers');
                    }
                });
            }
        },

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            afterItems: [{
                itemId: 'suspend',
                iconCls: 'x-btn-icon-down',
                tooltip: 'Select servers to suspend',
                disabled: true,
                handler: function() {
                    var records = this.up('grid').getSelectionModel().getSelection(),
                        servers = [];

                    for (var i = 0, len = records.length; i < len; i++) {
                        servers.push(records[i].get('server_id'));
                    }

                    Scalr.cache['Scalr.ui.servers.suspend'](servers, function() {
                        store.load();
                    });
                }
            }, {
                itemId: 'resume',
                iconCls: 'x-btn-icon-launch',
                tooltip: 'Select servers to resume',
                disabled: true,
                handler: function() {
                    var records = this.up('grid').getSelectionModel().getSelection(),
                        servers = [];

                    for (var i = 0, len = records.length; i < len; i++) {
                        servers.push(records[i].get('server_id'));
                    }

                    Scalr.cache['Scalr.ui.servers.resume'](servers, function() {
                        store.load();
                    });
                }
            }, {
                itemId: 'reboot',
                iconCls: 'x-btn-icon-reboot',
                tooltip: 'Select servers to reboot',
                disabled: true,
                handler: function() {
                    var records = this.up('grid').getSelectionModel().getSelection(),
                        servers = [];

                    for (var i = 0, len = records.length; i < len; i++) {
                        servers.push(records[i].get('server_id'));
                    }

                    Scalr.cache['Scalr.ui.servers.reboot'](servers, 'soft', false, function() {
                        store.load();
                    });
                }
            }, {
                itemId: 'terminate',
                iconCls: 'x-btn-icon-terminate',
                cls: 'x-btn-red',
                tooltip: 'Select servers to terminate',
                disabled: true,
                handler: function() {
                    var forcefulDisabledCount = 0,
                        forcefulDisabled,
                        scalingEnabledCount = 0,
                        platform,
                        records = this.up('grid').getSelectionModel().getSelection(),
                        servers = [];

                    for (var i = 0, len = records.length; i < len; i++) {
                        platform = records[i].get('platform');
                        servers.push(records[i].get('server_id'));
                        if (Scalr.isOpenstack(platform, true) || platform === 'cloudstack') {
                            forcefulDisabledCount++;
                        }
                        if (records[i].get('scalingEnabled') == 1) {
                            scalingEnabledCount++;
                        }
                    }

                    forcefulDisabled = records.length === forcefulDisabledCount ? true : (forcefulDisabledCount>0 ? 'partial' : false);

                    Scalr.cache['Scalr.ui.servers.terminate'](servers, forcefulDisabled, scalingEnabledCount > 0, function() {
                        store.load();
                    });
                }
            }],
            items: [{
                xtype: 'filterfield',
                width: 350,
                form: {
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Cloud server id',
                        labelAlign: 'top',
                        name: 'cloudServerId'
                    }, {
                        xtype: 'textfield',
                        fieldLabel: 'Cloud server location',
                        labelAlign: 'top',
                        name: 'cloudServerLocation'
                    }, {
                        xtype: 'textfield',
                        fieldLabel: 'Hostname',
                        labelAlign: 'top',
                        name: 'hostname'
                    }, {
                        xtype: 'textfield',
                        fieldLabel: 'ImageID',
                        labelAlign: 'top',
                        name: 'imageId'
                    }, {
                        xtype: 'serversfilteruptimefield',
                        fieldLabel: 'Launch time',
                        labelAlign: 'top',
                        name: 'uptime'
                    }]
                },
                store: store
            }, ' ', {
                xtype: 'buttonfield',
                name: 'showTerminated',
                enableToggle: true,
                width: 200,
                text: 'Show terminated servers',
                toggleHandler: function (me) {
                    store.applyProxyParams({
                        showTerminated: me.getValue()
                    });
                }
            }]
        }]
    });
});

Ext.define('Scalr.ui.ServersFilterUptime', {
    extend: 'Ext.form.FieldContainer',

    alias: 'widget.serversfilteruptimefield',

    mixins: {
        labelable: 'Ext.form.Labelable',
        fieldAncestor: 'Ext.form.FieldAncestor',
        field: 'Ext.form.field.Field'
    },

    layout: 'hbox',

    initComponent: function() {
        var me = this;

        me.callParent(arguments);
        me.initField();
    },

    getValue: function() {
        var b = parseInt(this.down('#b').getValue());
        return b > 0 ? (this.down('#a').getValue() + this.down('#b').getValue() + this.down('#c').getValue()) : '';
    },
    setValue: function(value) {
        var found = (value || '').match(/^(m|l)([0-9]+)(d|h)$/);
        if (found && (parseInt(found[2]) > 0)) {
            this.down('#a').setValue(found[1]);
            this.down('#b').setValue(found[2]);
            this.down('#c').setValue(found[3]);
        } else {
            this.down('#a').reset();
            this.down('#b').reset();
            this.down('#c').reset();
        }
    },
    items: [{
        xtype: 'cyclealt',
        itemId: 'a',
        submitValue: false,
        getItemIconCls: false,
        width: 120,
        cls: 'x-btn-compressed',
        menu: {
            cls: 'x-menu-light x-menu-cycle-button-filter x-menu-light-no-icon',
            minWidth: 120,
            items: [{
                text: 'More than',
                value: 'm'
            }, {
                text: 'Less than',
                value: 'l'
            }]
        }
    }, {
        xtype: 'numberfield',
        minValue: 1,
        flex: 1,
        itemId: 'b',
        submitValue: false,
        margin: '0 10 0 10'
    }, {
        xtype: 'cyclealt',
        itemId: 'c',
        submitValue: false,
        getItemIconCls: false,
        width: 90,
        cls: 'x-btn-compressed',
        menu: {
            cls: 'x-menu-light x-menu-cycle-button-filter x-menu-light-no-icon',
            minWidth: 90,
            items: [{
                text: 'Days',
                value: 'd'
            }, {
                text: 'Hours',
                value: 'h'
            }]
        }
    }]
});

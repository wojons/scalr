Scalr.regPage('Scalr.ui.servers.dashboard', function (loadParams, moduleParams) {
    var showInstanceHealth = moduleParams['general']['status'] === 'Running';
    
	var panel = Ext.create('Ext.form.Panel', {
		scalrOptions: {
			'modal': true
		},
		width: 1140,
		title: 'Server status',
        layout: 'auto',
        overflowX: 'hidden',
        bodyStyle: 'overflow-x:hidden!important',
        items: [{
            xtype: 'button',
            itemId: 'serverOptions',
            width: 100,
            text: 'Actions',
            style: 'position:absolute;right:32px;top:21px;z-index:2',
            menuAlign: 'tr-br',
            menu: {
                xtype: 'servermenu',
                hideOptionInfo: true,
                listeners: {
                    beforeshow: {
                        fn: function() {
                            this.doAutoRender();
                            this.setData(moduleParams['general']);
                        },
                        single: true
                    },
                    actioncomplete: function() {
                        Scalr.event.fireEvent('refresh');
                    }
                }
            }
        },{
            xtype: 'fieldset',
            itemId: 'general',
            cls: 'x-fieldset-separator-none',
            title: 'General info',
            items: [{
                xtype: 'container',
                layout: {
                    type: 'hbox',
                    align: 'stretch'
                },
                margin: 0,
                defaults: {
                    xtype: 'container',
                    layout: 'anchor',
                    flex: 1,
                    defaults: {
                        xtype: 'displayfield',
                        labelWidth: 130
                    }
                },
                items: [{
                    items: [{
                        name: 'server_id',
                        fieldLabel: 'Server ID'
                    },{
                        name: 'role',
                        fieldLabel: 'Role name',
                        renderer: function(value, comp) {
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-platform-small x-icon-platform-small-' + value.platform + '"/>&nbsp;&nbsp;' + value.name;
                        }
                    },{
                        name: 'os',
                        fieldLabel: 'Operating system',
                        renderer: function(value, comp) {
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-osfamily-small x-icon-osfamily-small-' + value.family + '"/>&nbsp;&nbsp;' + value.title;
                        }
                    },{
                        name: 'behaviors',
                        fieldLabel: 'Automation',
                        renderer: function(value, comp) {
                            var html = [];
                            Ext.Array.each(value, function(behavior) {
                                html.push(Scalr.utils.beautifyBehavior(behavior));
                            });
                            return html.join(', ');
                        }
                    },{
                        name: 'addedDate',
                        fieldLabel: 'Added on'
                    }]
                },{
                    padding: '0 0 0 32',
                    items: [{
                        name: 'status',
                        fieldLabel: 'Status',
                        renderer: function(value) {
                            return '<span style="font-weight:bold;' + (value === 'Running' ? 'color:#008000' : '') + '">' + value + '</span>'
                        }
                    },{
                        name: 'remote_ip',
                        fieldLabel: 'Remote IP'
                    },{
                        name: 'local_ip',
                        fieldLabel: 'Local IP'
                    },{
                        name: 'cloud_location',
                        fieldLabel: 'Location'
                    },{
                        name: 'instType',
                        fieldLabel: 'Instance type'
                    }]
                }]
            },{
                xtype: 'displayfield',
                name: 'securityGroups',
                anchor: '100%',
                fieldLabel: 'Security groups',
                cls: 'x-display-field-highlight',
                labelWidth: 130
            }]
        },{
            xtype: 'container',
            itemId: 'storageAndScalarizr',
            cls: 'x-fieldset-separator-top',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            defaults: {
                flex: 1
            },
            items: [{
                xtype: 'fieldset',
                title: 'Storage',
                itemId: 'storage',
                cls: 'x-fieldset-separator-none',
                loadStorages: function() {
                    var me = this;
                    Scalr.Request({
                        url: '/servers/xGetStorageDetails/',
                        params: {serverId: loadParams['serverId']},
                        success: function(data){
                            if (!me.isDestroyed && data.data) {
                                var items = [];
                                Ext.Object.each(data.data, function(key, value){
                                    items[key==='/'?'unshift':'push']({
                                        xtype: 'progressfield',
                                        fieldLabel: key,
                                        labelWidth: 200,
                                        anchor: '100%',
                                        labelSeparator: '',
                                        units: 'Gb',
                                        emptyText: 'Loading...',
                                        fieldCls: 'x-form-progress-field',
                                        value: {
                                            total: Ext.util.Format.round(value.total*1/1024/1024, 1),
                                            value: Ext.util.Format.round((value.total*1 - value.free)/1024/1024, 1)
                                        }
                                    });
                                });
                                me.add(items);
                            }
                        }
                    });
                }
            },{
                xtype: 'fieldset',
                title: 'Scalr agent',
                itemId: 'scalarizr',
                cls: 'x-fieldset-separator-left',
                hidden: true,
                items: [{
                    xtype: 'container',
                    itemId: 'status',
                    hidden: true,
                    anchor: '100%',
                    defaults: {
                        xtype: 'displayfield',
                        labelWidth: 130
                    },
                    items: [{
                        name: 'status',
                        fieldLabel: 'Scalarizr status',
                        renderer: function(value) {
                            return '<span style="font-weight:bold;color:' + (value === 'running' ? '#008000' : 'red') + '">' + Ext.String.capitalize(value) + '</span>'
                        }
                    },{
                        name: 'version',
                        fieldLabel: 'Scalarizr version'
                    },{
                        name: 'repository',
                        fieldLabel: 'Repository'
                    },{
                        name: 'lastUpdate',
                        fieldLabel: 'Last update',
                        renderer: function(value) {
                            var html = value.date ? (value.date + '&nbsp;&nbsp;&nbsp;') : '';
                            if (value.error) {
                                html += '<span style="font-weight:bold;color:#C00000">Failed';
                                html += ' <img data-qtip="'+Ext.String.htmlEncode(value.error)+'" src="/ui2/images/icons/question.png" style="cursor: help; height: 16px;position:relative;top:2px">';
                                html += '</span>';
                            } else if(!value.date) {
                                html += '<span style="font-weight:bold;">Never</span>';
                            } else {
                                html += '<span style="font-weight:bold;color:#008000;">Success</span>';
                            }
                            return html;
                        }
                    },{
                        name: 'nextUpdate',
                        fieldLabel: 'Next update'
                    },{
                        xtype: 'container',
                        layout: 'hbox',
                        defaults: {
                            width: 190,
                            height: 32
                        },
                        margin: '12 0 0 0',
                        items: [{
                            xtype: 'button',
                            text: 'Update scalarizr now',
                            disabled: (moduleParams['scalarizr'] === undefined || moduleParams['scalarizr']['version'] == moduleParams['scalarizr']['candidate']),
                            handler: function() {
                                Scalr.Request({
                                    confirmBox: {
                                        type: 'action',
                                        msg: 'Are you sure want to update scalarizr right now?'
                                    },
                                    processBox: {
                                        type: 'action',
                                        msg: 'Updating scalarizr ...'
                                    },
                                    url: '/servers/xSzrUpdate/',
                                    params: {serverId: loadParams['serverId']},
                                    success: function(){
                                        Scalr.event.fireEvent('refresh');
                                    }
                                });
                            }
                        },{
                            xtype: 'button',
                            text: 'Restart scalarizr',
                            margin:'0 0 0 20',
                            handler: function() {
                                Scalr.Request({
                                    confirmBox: {
                                        type: 'action',
                                        msg: 'Are you sure want to restart scalarizr right now?'
                                    },
                                    processBox: {
                                        type: 'action',
                                        msg: 'Restarting scalarizr ...'
                                    },
                                    url: '/servers/xSzrRestart/',
                                    params: {serverId: loadParams['serverId']},
                                    success: function(){
                                        Scalr.event.fireEvent('refresh');
                                    }
                                });
                            }
                        }]
                    }]
                },{
                    xtype: 'button',
                    itemId: 'upgradeUpdClientBtn',
                    hidden: true,
                    text: 'Upgrade scalarizr upd-client',
                    height: 32,
                    flex: 1,
                    handler: function() {
                        Scalr.Request({
                            processBox: {
                                type: 'action',
                                msg: 'Updating scalarizr upd-client to the latest version ...'
                            },
                            url: '/servers/xUpdateUpdateClient/',
                            params: {serverId: loadParams['serverId']},
                            success: function(){
                                //Scalr.event.fireEvent('refresh');
                            }
                        });
                    }
                },{
                    xtype: 'displayfield',
                    hidden: true,
                    name: 'statusNotAvailable'
                }]
            }]
        },{
            xtype: 'fieldset',
            itemId: 'instanceHealth',
            title: 'Instance health',
            cls: 'x-fieldset-separator-top',
            collapsible: true,
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            margin: 0,
            defaults: {
                xtype: 'container',
                layout: 'anchor',
                flex: 1,
                defaults: {
                    xtype: 'displayfield',
                    labelWidth: 130,
                    anchor: '100%'
                }
            },
            items: [{
                xtype: 'displayfield',
                hidden: true,
                itemId: 'healthNotAvailable',
                value: 'Health metrics available only for Running instances'
            },{
                margin: '0 32 0 0',
                items: [{
                    xtype: 'progressfield',
                    fieldLabel: 'Memory usage',
                    name: 'server_metrics_memory',
                    units: 'Gb',
                    emptyText: 'Loading...',
                    fieldCls: 'x-form-progress-field'
                },{
                    xtype: 'progressfield',
                    fieldLabel: 'CPU load',
                    name: 'server_metrics_cpu',
                    emptyText: 'Loading...',
                    fieldCls: 'x-form-progress-field'
                },{
                    xtype: 'displayfield',
                    fieldLabel: 'Load averages',
                    name: 'server_load_average'
                }]
            },{
                margin: '0 0 0 32',
                items: [{
                    xtype: 'container',
                    layout: 'column',
                    defaults: {
                        width: 220
                    },
                    items: [{
                        xtype: 'label',
                        text: 'Memory:'
                    },{
                        xtype: 'label',
                        text: 'CPU:'
                    }]
                },{
                    xtype: 'container',
                    layout: 'column',
                    defaults: {
                        margin: '12 20 10 0'
                    },
                    items: [{
                        xtype: 'chartpreview',
                        itemId: 'memoryChart'
                    },{
                        xtype: 'chartpreview',
                        itemId: 'cpuChart'
                    }]
                },{
                    xtype: 'container',
                    layout: 'column',
                    defaults: {
                        width: 220
                    },
                    items: [{
                        xtype: 'label',
                        text: 'Load averages:'
                    },{
                        xtype: 'label',
                        text: 'Network:'
                    }]
                },{
                    xtype: 'container',
                    layout: 'column',
                    defaults: {
                        margin: '12 20 0 0'
                    },
                    items: [{
                        xtype: 'chartpreview',
                        itemId: 'laChart'
                    },{
                        xtype: 'chartpreview',
                        itemId: 'netChart'
                    }]
                }]
            }]
        },{
            xtype: 'fieldset',
            itemId: 'alerts',
            collapsible: true,
            collapsed: true,
            title: 'Alerts',
            cls: 'x-fieldset-separator-top',
            listeners: {
                afterrender: function() {
                    this.down('grid').getStore().load();
                }
            },
            items: [{
                xtype: 'grid',
                cls: 'x-grid-shadow',
                store: {
                    fields: [ 'id', 'server_id', 'farm_id', 'farm_name', 'farm_roleid',
                        'role_name', 'server_index', 'status', 'metric', 'details', 'dtoccured',
                        'dtsolved', 'dtlastcheck', 'server_exists'
                    ],
                    proxy: {
                        type: 'scalr.paging',
                        extraParams: {
                            status: 'failed',
                            serverId: loadParams['serverId']
                        },
                        url: '/alerts/xListAlerts/'
                    },
                    remoteSort: true,
                    listeners: {
                        load: function(store, records) {
                            if (!panel.isDestroyed) {
                                panel.down('#alerts').setTitle('Alerts (' + records.length + ')');
                            }
                        }
                    }
                },
                plugins: {
                    ptype: 'gridstore'
                },

                viewConfig: {
                    deferEmptyText: false,
                    emptyText: "No active alerts found",
                    loadingText: 'Loading alerts ...'
                },

                columns: [
                    { header: "Check", flex: 1, dataIndex: 'metric', sortable: true },
                    { header: "Occured", width: 160, dataIndex: 'dtoccured', sortable: true },
                    { header: "Last check", width: 160, dataIndex: 'dtlastcheck', sortable: true, xtype: 'templatecolumn', tpl:
                        '<tpl if="dtlastcheck">{dtlastcheck}<tpl else><img src="/ui2/images/icons/false.png" /></tpl>'
                    },
                    { header: "Details", flex: 1, dataIndex: 'details', sortable: true }
                ]
            }]
        },{
            xtype: 'fieldset',
            itemId: 'cloudProperties',
            collapsible: true,
            collapsed: true,
            title: 'Cloud specific properties',
            cls: 'x-fieldset-separator-top'
        },{
            xtype: 'fieldset',
            itemId: 'internalProperties',
            collapsible: true,
            collapsed: true,
            title: 'Scalr internal properties',
            cls: 'x-fieldset-separator-top'
        }],
		tools: [{
			type: 'refresh',
			handler: function () {
				Scalr.event.fireEvent('refresh');
			}
		}, {
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}],

		loadChartsData: function() {
			var me = this,
                farmId = moduleParams['general']['farm_id'],
                farmRoleId = moduleParams['general']['farm_role_id'],
                serverInfo = {index: moduleParams['general']['index'], title: moduleParams['general']['server_id']},
                counter = 0;
            var callback = function() {
                if (++counter === 4) {
                    me.lcdDelayed = Ext.Function.defer(me.loadChartsData, 60000, me);
                }
            };
            me.down('#memoryChart').loadStatistics(farmId, 'MEMSNMP', 'daily', farmRoleId, serverInfo, callback);
            me.down('#cpuChart').loadStatistics(farmId, 'CPUSNMP', 'daily', farmRoleId, serverInfo, callback);
            me.down('#laChart').loadStatistics(farmId, 'LASNMP', 'daily', farmRoleId, serverInfo, callback);
            me.down('#netChart').loadStatistics(farmId, 'NETSNMP', 'daily', farmRoleId, serverInfo, callback);
		},
        
		loadGeneralMetrics: function() {
			var me = this,
				serverId = moduleParams['general']['server_id'],
				scrollTop = me.body.getScroll().top;
			if (serverId) {
				me.getForm().setValues({
					server_load_average: null,
					server_metrics_memory: null,
					server_metrics_cpu: null
				});
				me.body.scrollTo('top', scrollTop);
				Scalr.Request({
					url: '/servers/xGetHealthDetails',
					params: {
						serverId: serverId
					},
					success: function (res) {
                        if (me.isDestroyed) return;
                        me.suspendLayouts();
						if (
                            serverId == moduleParams['general']['server_id'] &&
                            res.data['memory'] && res.data['cpu']
                        ) {
							me.getForm().setValues({
								server_load_average: res.data['la'],
								server_metrics_memory: {
									total: res.data['memory']['total']*1,
									value: Ext.util.Format.round(res.data['memory']['total'] - res.data['memory']['free'], 2)
								},
								server_metrics_cpu: (100 - res.data['cpu']['idle'])/100
							});
						} else {
                            me.getForm().setValues({
                                server_load_average: 'not available',
                                server_metrics_memory: 'not available',
                                server_metrics_cpu: 'not available'
                            });
                        }
                        me.resumeLayouts(false);
                        me.lgmDelayed = Ext.Function.defer(me.loadGeneralMetrics, 30000, me);
					},
                    failure: function() {
                        if (me.isDestroyed) return;
                        me.suspendLayouts();
                        me.getForm().setValues({
                            server_load_average: 'not available',
                            server_metrics_memory: 'not available',
                            server_metrics_cpu: 'not available'
                        });
                        me.resumeLayouts(false);
                    }
				});
			}
		},

        listeners: {
            afterrender: function() {
                if (showInstanceHealth) {
                    this.loadGeneralMetrics();
                    this.loadChartsData();
                }
            },
            beforedestroy: function() {
                if (this.lgmDelayed) {
                    clearTimeout(this.lgmDelayed);
                }
                if (this.lcdDelayed) {
                    clearTimeout(this.lcdDelayed);
                }
            }
        }
    });
    panel.down('#general').setFieldValues(moduleParams['general']);
    var scalarizrCt = panel.down('#scalarizr');
    if (moduleParams['scalarizr'] !== undefined) {
        scalarizrCt.show();
        switch (moduleParams['scalarizr']['status']) {
            case 'upgradeUpdClient':
                scalarizrCt.down('#upgradeUpdClientBtn').show();
            break;
            case 'statusNotAvailable':
                scalarizrCt.down('[name="statusNotAvailable"]').show().setValue(moduleParams['scalarizr']['error']);
            break;
            default:
                scalarizrCt.down('#status').show();
                scalarizrCt.setFieldValues(moduleParams['scalarizr']);
            break;
        }
        
    }

    var storageCt = panel.down('#storage');
    if (/*moduleParams['storage']*/ moduleParams['scalarizr'] !== undefined) {
        storageCt.show();
        panel.down('#storage').loadStorages();
    }

    if (moduleParams['scalarizr'] === undefined/* && moduleParams['storage'] === undefined*/) {
        panel.down('#storageAndScalarizr').hide();
    }

    if (!showInstanceHealth) {
        panel.down('#instanceHealth').items.each(function() {
            this.setVisible(this.itemId === 'healthNotAvailable');
        });

    }
    var cloudPropertiesCt = panel.down('#cloudProperties'),
        securityGroupsField = panel.down('[name="securityGroups"]');
    if (moduleParams['cloudProperties'] !== undefined) {
        var items = [];
        Ext.Object.each(moduleParams['cloudProperties'], function(key, value){
            items.push({
                xtype: 'displayfield',
                fieldLabel: key,
                labelWidth: 160,
                value: value || '-'
            });
        });
        cloudPropertiesCt.add(items);
        if (moduleParams['cloudProperties']['Security groups']) {
            securityGroupsField.setValue(moduleParams['cloudProperties']['Security groups']);
        } else {
            securityGroupsField.hide();
        }
    } else {
        cloudPropertiesCt.hide();
        securityGroupsField.hide();
    }

    var internalPropertiesCt = panel.down('#internalProperties');
    if (moduleParams['internalProperties'] !== undefined) {
        var items = [];
        Ext.Object.each(moduleParams['internalProperties'], function(key, value){
            items.push({
                xtype: 'displayfield',
                fieldLabel: key,
                labelWidth: 160,
                value: value || '-'
            });
        });
        internalPropertiesCt.add(items);
    } else {
        internalPropertiesCt.hide();
    }

    if (moduleParams['updateAmiToScalarizr'] !== undefined) {
        panel.add({
            xtype: 'fieldset',
            title: 'Upgrade from ami-scripts to scalarizr',
            items: {
                xtype: 'textarea',
                readOnly: true,
                anchor: '100%',
                value: moduleParams['updateAmiToScalarizr']
            }
        });
    }

	return panel;
});
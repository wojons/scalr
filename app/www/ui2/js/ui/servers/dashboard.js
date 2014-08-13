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
                        name: 'hostname',
                        fieldLabel: 'Hostname'
                    },{
                        name: 'farm_name',
                        fieldLabel: 'Farm',
                        renderer: function (value) {
                            return '<a href="#/farms/view?farmId=' + moduleParams.general['farm_id'] + '">' + value + '</a>';
                        }
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
                        width: 280,
                        renderer: function(value) {
                            return Scalr.ui.ColoredStatus.getHtml({
                                type: 'server',
                                status: moduleParams['general']['status'],
                                data: moduleParams['general']
                            });
                        }
                    },{
                        name: 'remote_ip',
                        fieldLabel: 'Public IP'
                    },{
                        name: 'local_ip',
                        fieldLabel: 'Private IP'
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
                                    items[ key === '/' ? 'unshift' : 'push' ]({
                                        xtype: 'progressfield',
                                        fieldLabel: key + (value && value.fstype ? ' (' + value.fstype + ')' : ''),
                                        labelWidth: 200,
                                        anchor: '100%',
                                        labelSeparator: '',
                                        units: 'Gb',
                                        emptyText: 'Loading...',
                                        fieldCls: 'x-form-progress-field',
                                        value: value ? {
                                            total: Ext.util.Format.round(value.total*1/1024/1024, 1),
                                            value: Ext.util.Format.round((value.total*1 - value.free)/1024/1024, 1)
                                        } : 'Unknown size'
                                    });
                                });
                                me.add(items);
                            }
                        }
                    });
                }
            },{
                xtype: 'toolfieldset',
                title: 'Scalr agent',
                itemId: 'scalarizr',
                cls: 'x-fieldset-separator-left',
                hidden: true,
                tools: [{
                    type: 'refresh',
                    tooltip: 'Refresh scalr agent status',
                    style: 'margin-left:12px',
                    handler: function () {
                        this.refreshStatus();
                    }
                }],
                abortRequest: function() {
                    if (this.request) {
                        Ext.Ajax.abort(this.request);
                        delete this.request;
                    }
                },
                requestTimeout: 30, //seconds
                refreshStatus: function() {
                    var me = this;
                    fn = function() {
                        me.mask('Refreshing agent status...');
                        me.request = Scalr.Request({
                            url: '/servers/xGetServerRealStatus/',
                            params: {serverId: loadParams['serverId'], timeout: me.requestTimeout},
                            success: function(data){
                                me.unmask();
                                me.showStatus(data['scalarizr']);
                                if (Ext.isString(me.error) && me.error.indexOf('Unable to perform request to update client: Timeout was reached') !== -1) {
                                    me.requestTimeout += 15;
                                }
                                delete me.request;
                            }
                        });
                    };
                    if (me.rendered) {
                        fn();
                    } else {
                        me.on('boxready', fn, me, {single: true});
                    }
                },
                showStatus: function(data) {
                    switch (data['status']) {
                        case 'upgradeUpdClient':
                            this.down('#status').hide();
                            this.down('[name="statusNotAvailable"]').hide();
                            this.down('#upgradeUpdClientBtn').show();
                        break;
                        case 'statusNotAvailable':
                            this.down('#upgradeUpdClientBtn').hide();
                            this.down('#status').hide();
                            this.down('[name="statusNotAvailable"]').show().setValue(data['error']);
                        break;
                        case 'statusNoCache':
                            this.down('#upgradeUpdClientBtn').hide();
                            this.down('#status').hide();
                            this.down('[name="statusNotAvailable"]').show().setValue('&nbsp;');
                        break;
                        break;
                        default:
                            this.down('#upgradeUpdClientBtn').hide();
                            this.down('[name="statusNotAvailable"]').hide();
                            this.down('#status').show();
                            this.setFieldValues(data);
                            this.down('#btnUpdateScalarizr').setDisabled(data === undefined || data['version'] == data['candidate']);
                        break;
                    }
                },
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
                                html += ' <img data-qtip="'+Ext.String.htmlEncode(value.error)+'" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-question" style="cursor: help; height: 16px;position:relative;top:2px">';
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
                            itemId: 'btnUpdateScalarizr',
                            text: 'Update scalarizr now',
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
                    xtype: 'chartpreview',
                    itemId: 'chartPreview'
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
            var me = this;

            var hostUrl = moduleParams['general']['monitoring_host_url'];
            var farmId = moduleParams['general']['farm_id'];
            var farmRoleId = moduleParams['general']['farm_roleid'];
            var index = moduleParams['general']['index'];
            var farmHash = moduleParams['general']['farm_hash'];
            var metrics = 'mem,cpu,la,net';
            var period = 'daily';
            var params = {farmId: farmId, farmRoleId: farmRoleId, index: index, hash: farmHash, period: period, metrics: metrics};
            var chartPreview = me.down('#chartPreview');

            var callback = function() {
                me.lcdDelayed = Ext.Function.defer(me.loadChartsData, 60000, me);
            };

            chartPreview.loadStatistics(hostUrl, params, callback);
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
                this.down('#scalarizr').abortRequest();
            }
        }
    });
    var generalCt = panel.down('#general');
    generalCt.setFieldValues(moduleParams['general']);
    generalCt.down('[name="hostname"]').setVisible(!!moduleParams['general']['hostname']);
    
    var scalarizrCt = panel.down('#scalarizr');
    if (moduleParams['scalarizr'] !== undefined) {
        scalarizrCt.show();
        scalarizrCt.showStatus(moduleParams['scalarizr']);//show cached status
        scalarizrCt.refreshStatus();//load real status
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
                labelWidth: 220,
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
                labelWidth: 220,
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
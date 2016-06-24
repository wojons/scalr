Scalr.regPage('Scalr.ui.servers.dashboard', function (loadParams, moduleParams) {

    var generalServerInfo = moduleParams.general;
    var platform = generalServerInfo.platform;
    var cloudLocation = generalServerInfo['cloud_location'];
    var serverStatus = generalServerInfo.status;
    var showInstanceHealth = serverStatus === 'Running';

    var changeInstanceType = function (farmRoleInstanceType, instanceType, imageType, imageArchitecture, vpcId) {
        imageType = Ext.isString(imageType) ? imageType : '';
        imageArchitecture = Ext.isString(imageArchitecture) ? imageArchitecture : '';

        Scalr.utils.loadInstanceTypes(platform, cloudLocation, function (instancesTypes) {
            var limites = Scalr.getGovernance('ec2', 'instance_type');
            var isPolicyEnabled = !Ext.isEmpty(limites);

            var instanceTypeRestrictions = {
                ebs: imageType.indexOf('ebs') !== -1,
                hvm: imageType.indexOf('hvm') !== -1,
                x64: imageArchitecture !== 'i386',
                vpc: !Ext.isEmpty(vpcId)
            };

            Scalr.Confirm({
                formWidth: 400,
                ok: 'Change',
                form: [{
                    xtype: 'fieldset',
                    title: 'Change Instance type',
                    fieldDefaults: {
                        anchor: '100%',
                        labelWidth: 120
                    },
                    items: [{
                        xtype: 'hiddenfield',
                        name: 'serverId',
                        value: loadParams.serverId
                    }, {
                        xtype: 'displayfield',
                        fieldLabel: 'Farm Role configuration',
                        value: farmRoleInstanceType
                    }, {
                        xtype: 'displayfield',
                        fieldLabel: 'Current type',
                        value: instanceType
                    }, {
                        xtype: 'instancetypefield',
                        fieldLabel: 'New type',
                        name: 'instanceType',
                        value: !isPolicyEnabled ? instanceType : limites.default,
                        iconsPosition: 'outer',
                        store: {
                            proxy: 'object',
                            fields: [ 'id', 'name', 'note', 'ram', 'type', 'vcpus', 'disk', 'ebsencryption', 'ebsoptimized', 'placementgroups', 'instancestore', {name: 'disabled', defaultValue: false}, 'disabledReason', 'restrictions' ],
                            data: instancesTypes,
                            filters: [{
                                id: 'governancePolicyFilter',
                                filterFn: function (record) {
                                    return !isPolicyEnabled
                                        ? true
                                        : Ext.Array.contains(limites.value, record.get('id'));
                                }
                            }, {
                                id: 'awsRestrictionsFilter',
                                filterFn: function (record) {
                                    var allowed = true,
                                        restrictions = record.get('restrictions'),
                                        encryptionRequired = (Scalr.getGovernance('ec2', 'aws.storage') || {})['require_encryption'];
                                    allowed = !encryptionRequired || record.get('ebsencryption')
                                    
                                    if (allowed && !Ext.isEmpty(restrictions)) {
                                        Ext.Object.each(instanceTypeRestrictions, function (type, isAllowed) {
                                            if (Ext.isDefined(restrictions[type]) && restrictions[type] !== isAllowed) {
                                                allowed = false;
                                                return false;
                                            }
                                        });
                                    }

                                    return allowed;
                                }
                            }],
                            sorters: {
                                property: 'disabled'
                            }
                        },
                        disableChangeButton: function (disabled) {
                            var me = this;

                            me.up('#box').down('#buttonOk')
                                .setDisabled(disabled);

                            return me;
                        },
                        listeners: {
                            afterrender: function (field) {
                                var value = field.getValue();

                                field
                                    .disableChangeButton(
                                        Ext.isEmpty(value) || (value === instanceType)
                                    )
                                    .toggleIcon('governance', isPolicyEnabled);
                            },
                            change: function (field, value) {
                                field.disableChangeButton(value === instanceType);
                            }
                        }
                    }]
                }],
                success: function (values, form) {
                    Scalr.Request({
                        processBox: {
                            type: 'save'
                        },
                        url: '/servers/xChangeInstanceType',
                        params: values,
                        success: function () {
                            panel.down('[name=instType]')
                                .setValue(values.instanceType);
                        }
                    });
                }
            });
        });
    };

    var panel = Ext.create('Ext.form.Panel', {
        scalrOptions: {
            'modal': true
        },
        width: 1140,
        title: 'Server status',
        layout: 'auto',
        overflowX: 'hidden',
        bodyStyle: 'overflow-x:hidden!important',
        preserveScrollPosition: true,
        items: [{
            xtype: 'button',
            itemId: 'serverOptions',
            width: 120,
            text: 'Actions',
            style: 'position:absolute;right:32px;top:21px;z-index:2',
            menuAlign: 'tr-br',
            listeners: {
                added: {
                    fn: function() {
                        this.menu.doAutoRender();
                        this.menu.setData(moduleParams['general']);
                        if (!this.menu.visibleItemsCount) {
                            this.hide();
                        }
                    },
                    single: true
                }
            },
            menu: {
                xtype: 'servermenu',
                hideOptionInfo: true,
                moduleParams: moduleParams,
                listeners: {
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
                    flex: 1
                },
                items: [{
                    defaults: {
                        xtype: 'displayfield',
                        labelWidth: 145
                    },
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
                            return '<a href="#/farms?farmId=' + moduleParams.general['farm_id'] + '">' + value + '</a>';
                        }
                    },{
                        name: 'role',
                        fieldLabel: 'Role name',
                        renderer: function(value, comp) {
                            var name = value.name;

                            if (name === 'unknown') {
                                return '';
                            }

                            if (value.id && Scalr.isAllowed('ROLES_ENVIRONMENT')) {
                                name = '<a href="#/roles?roleId=' + value.id + '">' + name + '</a>';
                            }
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-platform-small x-icon-platform-small-' + value.platform + '"/>&nbsp;&nbsp;' + name;
                        }
                    },{
                        name: 'os',
                        fieldLabel: 'Operating system',
                        renderer: function(value, comp) {
                            return value ? '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-osfamily-small x-icon-osfamily-small-' + value.family + '"/>&nbsp;&nbsp;' + value.name : 'unknown';
                        }
                    },{
                        name: 'behaviors',
                        fieldLabel: 'Built-in automation',
                        renderer: function(value, comp) {
                            var html = [];
                            if (moduleParams['general']['isScalarized'] == 1) {
                                html.push('<img style="float:left;margin:0 8px 8px 0" class="x-icon-scalr-small" src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="Scalarizr (Scalr agent)" />');
                            }
                            Ext.Array.each(value, function (value) {
                                if (!Ext.isEmpty(value) && value !== 'base') {
                                    html.push('<img style="float:left;margin:0 8px 8px 0" class="x-icon-role-small x-icon-role-small-' + value + '" src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Ext.htmlEncode(Scalr.utils.beautifyBehavior(value, true)) + '" />');
                                }
                            });
                            return html.length > 0 ? html.join(' ') : '&mdash;';
                        }
                    },{
                        name: 'addedDate',
                        fieldLabel: 'Added on'
                    }]
                },{
                    defaults: {
                        xtype: 'displayfield',
                        labelWidth: 160
                    },
                    padding: '0 0 0 32',
                    items: [{
                        name: 'status',
                        fieldLabel: 'Status',
                        width: 300,
                        renderer: function(value) {
                            return Scalr.ui.ColoredStatus.getHtml({
                                type: 'server',
                                status: moduleParams['general']['status'],
                                data: moduleParams['general'],
                                qtipConfig: {width: 300}
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
                        xtype: 'fieldcontainer',
                        fieldLabel: 'Instance type',
                        layout: 'hbox',
                        items: [{
                            xtype: 'displayfield',
                            name: 'instType',
                        }, {
                            xtype: 'button',
                            text: 'Change',
                            margin: '0 0 0 15',
                            hidden: !(Scalr.isAllowed('FARMS', 'servers') ||
                                    moduleParams['general']['farmTeamIdPerm'] && Scalr.isAllowed('TEAM_FARMS', 'servers') ||
                                    moduleParams['general']['farmOwnerIdPerm'] && Scalr.isAllowed('OWN_FARMS', 'servers')),
                            disabled: platform !== 'ec2' || serverStatus !== 'Suspended',
                            tooltip: function (platform, serverStatus) {
                                if (platform !== 'ec2') {
                                    return Scalr.utils.getPlatformName(platform)
                                        + ' doesn\'t support this operation.';
                                }

                                if (serverStatus !== 'Suspended') {
                                    return 'Instance type change available only for suspended servers.';
                                }

                                return '';
                            }(platform, serverStatus),
                            handler: function (button) {
                                changeInstanceType(
                                    generalServerInfo.farmRoleInstanceType,
                                    button.prev('[name=instType]').getValue(),
                                    generalServerInfo.imageType,
                                    generalServerInfo.imageArchitecture,
                                    moduleParams.internalProperties['ec2.vpc-id']
                                );
                            }
                        }]
                    },{
                        xtype: 'fieldcontainer',
                        fieldLabel: 'Enhanced networking',
                        layout: 'hbox',
                        hidden: platform !== 'ec2',
                        items: [{
                            xtype: 'displayfield',
                            name: 'enhancedNetworking',
                            value: '&mdash;'
                        }, {
                            xtype: 'button',
                            text: 'Enable',
                            margin: '0 0 0 15',
                            itemId: 'enableEnhancedNetworking',
                            hidden: true,
                            disabled: serverStatus !== 'Suspended',
                            tooltip: serverStatus !== 'Suspended' ? 'Available only on suspended servers.' : '',
                            handler: function (button) {
                                Scalr.Request({
                                    confirmBox: {
                                        type: 'action',
                                        msg: 'Are you sure want to enable Enhanced Networking?'
                                    },
                                    processBox: {
                                        type: 'action',
                                        progressBar: true
                                    },
                                    url: '/servers/xEnableEnhancedNetworking/',
                                    params: {serverId: loadParams['serverId']},
                                    success: function () {
                                        button.hide();
                                        button.prev('[name="enhancedNetworking"]').setValue('Enabled');
                                    }
                                });
                            }
                        }]
                    }, {
                        name: 'imageId',
                        fieldLabel: 'Cloud Image ID',
                        plugins: {
                            ptype: 'fieldicons',
                            position: 'outer',
                            icons: [{id: 'warning', hidden: true, tooltip: "This Server was launched using an Image that is no longer used in its Role's configuration."}]
                        },
                        renderer: function(value) {
                            var imageHash = moduleParams['general']['imageHash'];

                            return Scalr.isAllowed('IMAGES_ENVIRONMENT') && !Ext.isEmpty(imageHash)
                                ? '<a href="#/images?hash=' + imageHash + '">' + value + '</a>'
                                : value;
                        },
                        listeners: {
                            afterrender: function() {
                                if (moduleParams['general']['imageIdDifferent'])
                                    this.toggleIcon('warning', true);
                            }
                        }
                    }]
                }]
            },{
                xtype: 'displayfield',
                name: 'securityGroups',
                anchor: '100%',
                fieldLabel: 'Security groups',
                cls: 'x-display-field-highlight',
                labelWidth: 145
            },{
                xtype: 'displayfield',
                name: 'blockStorage',
                anchor: '100%',
                fieldLabel: 'Block storage',
                cls: 'x-display-field-highlight',
                labelWidth: 145
            }]
        },{
            xtype: 'container',
            itemId: 'notScalarizedWarning',
            cls: 'x-fieldset-separator-top',
            hidden: true,
            items: {
                xtype: 'displayfield',
                margin: 0,
                width: '100%',
                itemId: 'healthNotAvailable',
                value: '<b>Storage</b>, <b>Scalr Agent</b> and <b>Instance health</b> information available only on servers with scalr agent installed',
                cls: 'x-form-field-warning x-form-field-warning-fit'
            }
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

                    if (!Ext.isEmpty(me.getEl())) {
                        me.mask('');
                    } else {
                        me.on('boxready', function () {
                            me.mask('');
                        });
                    }

                    var onSuccess = function (data) {
                        if (!me.isDestroyed) {
                            me.unmask();

                            if (data.status === 'available' && !Ext.isEmpty(data.data)) {
                                var items = [];
                                Ext.Object.each(data.data, function(key, value) {
                                    items[key === '/' ? 'unshift' : 'push']({
                                        xtype: 'progressfield',
                                        fieldLabel: key + (value && value.fstype ? ' (' + value.fstype + ')' : ''),
                                        labelWidth: 200,
                                        labelStyle: 'word-wrap: break-word',
                                        anchor: '100%',
                                        labelSeparator: '',
                                        units: 'Gb',
                                        emptyText: 'Loading...',
                                        fieldCls: 'x-form-progress-field',
                                        value: value ? {
                                            total: Ext.util.Format.round(value.total * 1 / 1024 / 1024, 1),
                                            value: Ext.util.Format.round((value.total * 1 - value.free) / 1024 / 1024, 1)
                                        } : 'Unknown size'
                                    });
                                });
                                me.add(items);
                            } else if (data.status === 'notAvailable' && !Ext.isEmpty(data.error)) {
                                me.add({
                                    xtype: 'displayfield',
                                    value: data.error,
                                    renderer: function (value) {
                                        return '<span style="color:red">' + value + '</span>';
                                    }
                                });
                            }
                        }
                    };

                    var onFailure = function () {
                        if (!me.isDestroyed) {
                            me.unmask();
                            me.add({
                                xtype: 'displayfield',
                                value: 'Unable to load data from server.',
                                renderer: function (value) {
                                    return '<span style="color:red">' + value + '</span>';
                                }
                            });
                        }
                    };

                    Ext.create('Scalr.ScalarizrRequest')
                        .on({
                            success: onSuccess,
                            failure: onFailure
                        })
                        .request({
                            url: '/servers/xGetStorageDetails/',
                            params: {
                                serverId: loadParams.serverId
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
                        this.refreshStatus(1);
                    }
                }],
                abortRequest: function() {
                    if (this.request) {
                        Ext.Ajax.abort(this.request);
                        delete this.request;
                    }
                },
                requestTimeout: 30, //seconds
                refreshStatus: function(requestLimit) {
                    var me = this;
                    var fn = function() {
                        me.mask('');

                        var onSuccess = function (data) {
                            if (!me.isDestroyed) {
                                me.unmask();
                                me.showStatus(data['scalarizr']);
                                if (Ext.isString(me.error) && me.error.indexOf('Unable to perform request to update client: Timeout was reached') !== -1) {
                                    me.requestTimeout += 15;
                                }
                            }
                            delete me.request;
                        };

                        var onFailure = function () {
                            if (!me.isDestroyed) {
                                me.unmask();
                                me.down('[name="statusNotAvailable"]')
                                    .show()
                                    .setValue(
                                        '<span style="color:red">Unable to load data from server.</span>'
                                    );
                            }
                        };

                        Ext.create('Scalr.ScalarizrRequest', { requestLimit: !Ext.isDefined(requestLimit) ? 3 : requestLimit })
                            .on({
                                request: function (request) {
                                    me.request = request;
                                },
                                success: onSuccess,
                                failure: onFailure
                            })
                            .request({
                                url: '/servers/xGetServerRealStatus/',
                                params: {
                                    serverId: loadParams['serverId'],
                                    timeout: me.requestTimeout
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
                        case 'statusNotAvailable':
                            this.down('#status').hide();
                            this.down('[name="statusNotAvailable"]').show().setValue(data['error']);
                        break;
                        case 'statusNoCache':
                            this.down('#status').hide();
                            this.down('[name="statusNotAvailable"]').show().setValue('&nbsp;');
                        break;
                        break;
                        default:
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
                            return '<b style="color:' + (value === 'running' ? '#008000' : 'red') + '">' + Ext.String.capitalize(value) + '</b>'
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
                                html += '<b style="color:#C00000">Failed';
                                html += ' &nbsp;<img data-qtip="'+Ext.String.htmlEncode(value.error)+'" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-error" style="position:relative;top:-2px">';
                                html += '</b>';
                            } else if(!value.date) {
                                html += '<b>Never</b>';
                            } else {
                                html += '<b style="color:#008000;">Success</b>';
                            }
                            return html;
                        }
                    },{
                        name: 'nextUpdate',
                        fieldLabel: 'Next update',
                        renderer: function(value) {
                            var html;
                            if (!value) {
                                html = 'Scalarizr is up to date';
                            } else {
                                html = 'Update to <b>' + value['candidate'] + '</b>';
                                html += value['scheduledOn'] ? ' scheduled on <b>' + value['scheduledOn'] + '</b>' : ' is not yet scheduled';
                            }
                            return html;
                        }
                    },{
                        xtype: 'fieldcontainer',
                        layout: 'hbox',
                        defaults: {
                            width: 190,
                            height: 32
                        },
                        hidden: !(Scalr.isAllowed('FARMS', 'servers') ||
                                moduleParams['general']['farmTeamIdPerm'] && Scalr.isAllowed('TEAM_FARMS', 'servers') ||
                                moduleParams['general']['farmOwnerIdPerm'] && Scalr.isAllowed('OWN_FARMS', 'servers')),
                        items: [{
                            xtype: 'button',
                            itemId: 'btnUpdateScalarizr',
                            text: 'Update scalarizr now',
                            handler: function() {
                                Scalr.Request({
                                    timeout: 105000,
                                    confirmBox: {
                                        type: 'action',
                                        msg: 'Are you sure want to update scalarizr right now?'
                                    },
                                    processBox: {
                                        type: 'action',
                                        msg: 'Updating scalarizr ...',
                                        text: 'Scalarizr update can take up to 2 minutes',
                                        progressBar: true
                                    },
                                    url: '/servers/xSzrUpdate/',
                                    params: {serverId: loadParams['serverId']},
                                    success: function () {
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
                                    success: function () {
                                        Scalr.event.fireEvent('refresh');
                                    }
                                });
                            }
                        }]
                    }]
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
                    labelWidth: 145,
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
                        url: '/alerts/xList'
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
                        '<tpl if="dtlastcheck">{dtlastcheck}<tpl else>&mdash;</tpl>'
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
                chartPreview = me.down('#chartPreview');

            if (!(Scalr.isAllowed('FARMS', 'statistics') ||
                moduleParams['general']['farmTeamIdPerm'] && Scalr.isAllowed('TEAM_FARMS', 'statistics') ||
                moduleParams['general']['farmOwnerIdPerm'] && Scalr.isAllowed('OWN_FARMS', 'statistics')
            )) {
                chartPreview.hide();
                return;
            }

            var hostUrl = moduleParams['general']['monitoring_host_url'];
            var farmId = moduleParams['general']['farm_id'];
            var farmRoleId = moduleParams['general']['farm_roleid'];
            var index = moduleParams['general']['index'];
            var farmHash = moduleParams['general']['farm_hash'];
            var metrics = 'mem,cpu,la,net';
            var period = 'daily';
            var params = {farmId: farmId, farmRoleId: farmRoleId, index: index, hash: farmHash, period: period, metrics: metrics};

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
                    headers: {
                        'Scalr-Autoload-Request': 1
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
        loadEnhancedNetworkingStatus: function() {
            var me = this,
                serverId = moduleParams['general']['server_id'];
            if (platform === 'ec2' && serverId) {
                Scalr.Request({
                    url: '/servers/xGetEnhancedNetworkingStatus',
                    params: {
                        serverId: serverId
                    },
                    success: function (data) {
                        var enhancedNetworking;
                        if (me.isDestroyed) return;
                        if (data.isAvailable) {
                            if (data.isEnabled) {
                                enhancedNetworking = 'Enabled';
                            } else {
                                enhancedNetworking = 'Disabled';
                                if (Scalr.isAllowed('FARMS', 'servers') ||
                                    moduleParams['general']['farmTeamIdPerm'] && Scalr.isAllowed('TEAM_FARMS', 'servers') ||
                                    moduleParams['general']['farmOwnerIdPerm'] && Scalr.isAllowed('OWN_FARMS', 'servers'))
                                {
                                    me.down('#enableEnhancedNetworking').show();
                                }
                            }
                            me.down('[name="enhancedNetworking"]').setValue(enhancedNetworking);
                        }
                    },
                    failure: function() {
                        if (me.isDestroyed) return;
                    }
                });
            }
        },

        listeners: {
            afterrender: function() {
                if (showInstanceHealth && moduleParams['general']['isScalarized'] == 1) {
                    this.loadGeneralMetrics();
                    this.loadChartsData();
                }
                this.loadEnhancedNetworkingStatus();
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
    if (moduleParams['general']['isScalarized'] == 1) {

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
    } else {
        panel.down('#storageAndScalarizr').hide();
        panel.down('#instanceHealth').hide();
        panel.down('#notScalarizedWarning').show();
    }
    var cloudPropertiesCt = panel.down('#cloudProperties'),
        securityGroupsField = panel.down('[name="securityGroups"]'),
        blockStorageField = panel.down('[name="blockStorage"]');
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
            securityGroupsField.setValue(Scalr.isAllowed('SECURITY_GROUPS') ? moduleParams['cloudProperties']['Security groups'] : Ext.util.Format.stripTags(moduleParams['cloudProperties']['Security groups']));
        } else {
            securityGroupsField.hide();
        }

        if (moduleParams['cloudProperties']['Block storage']) {
            blockStorageField.setValue(Scalr.isAllowed('AWS_VOLUMES') ? moduleParams['cloudProperties']['Block storage'] : Ext.util.Format.stripTags(moduleParams['cloudProperties']['Block storage']));
        } else {
            blockStorageField.hide();
        }
    } else {
        cloudPropertiesCt.hide();
        securityGroupsField.hide();
        blockStorageField.hide();
    }

    var internalPropertiesCt = panel.down('#internalProperties');
    if (moduleParams['internalProperties'] !== undefined) {
        var items = [];
        Ext.Object.each(moduleParams['internalProperties'], function(key, value){
            if (moduleParams['general']['isScalarized'] == 0 && key && key.indexOf('scalarizr') === 0) {
                return true;
            }
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

Ext.define('Scalr.ScalarizrRequest', {
    extend: 'Scalr.RepeatingRequest',

    doNotHideErrorMessages: true,

    onSuccess: function (responseData) {
        var me = this;

        me.requestCount++;

        var isScalarizrAvailable = !Ext.isEmpty(responseData.scalarizr)
            ? responseData.scalarizr.status !== 'statusNotAvailable'
            : responseData.status !== 'notAvailable';

        if (isScalarizrAvailable || me.requestCount >= me.requestLimit) {
            me.fireEvent('success', responseData);
            me.destroy();
            return me;
        }

        me.doStep();

        Ext.Function.defer(
            me.doRequest,
            me.getTimeout(),
            me
        );

        return me;
    },

    onFailure: function (responseData) {
        var me =  this;

        me.fireEvent('failure', responseData);
        me.destroy();

        return me;
    }
});

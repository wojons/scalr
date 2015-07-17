Scalr.regPage('Scalr.ui.tools.aws.rds.instances.view', function (loadParams, moduleParams) {

    var store = Ext.create('Scalr.ui.ContinuousStore', {

        fields: [
            'engine',
            'status',
            'hostname',
            'port',
            'name',
            'username',
            'type',
            'storage',
            'dtadded',
             'avail_zone',
             'isReplica',
             'engineVersion',
             'multiAz'
        ],

        proxy: {
            type: 'ajax',
            url: '/tools/aws/rds/instances/xListInstances/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        }
    });

    var grid = Ext.create('Ext.grid.Panel', {
        flex: 1,
        cls: 'x-panel-column-left',
        scrollable: true,

        store: store,

        plugins: [ 'applyparams', 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }, {
            ptype: 'continuousrenderer'
        }],

        viewConfig: {
            emptyText: 'No DB Instances defined'
        },

        selModel: 'selectedmodel',

        areDbInstancesAvailable: function (dbInstances) {
            return Ext.Array.every(dbInstances, function (dbInstance) {
                return dbInstance.get('status') === 'available';
            });
        },

        listeners: {
            selectionchange: function (selModel, selections) {
                var me = this;

                var toolbar = me.down('toolbar');
                var hasSelected = selections.length !== 0;

                toolbar.down('#terminate').setDisabled(!hasSelected);
                toolbar.down('#reboot').setDisabled(!hasSelected
                    || !me.areDbInstancesAvailable(selections)
                );
            }
        },

        collectSelectedDbInstancesIds: function () {
            var me = this;

            return Ext.Array.map(
                me.getSelectionModel().getSelection(),

                function (record) {
                    return record.get('name');
                }
            );
        },

        terminateDbInstance: function (dbInstanceId) {
            var me = this;

            var isOperationMultiple = Ext.isArray(dbInstanceId);

            Scalr.Request({
                confirmBox: {
                    type: 'terminate',
                    msg: !isOperationMultiple
                        ? 'Terminate server <b>' + dbInstanceId + '</b> ?<p>' +
                            '<i> This action will completely remove this server from AWS.<br>' +
                            'All data that has not been snapshotted will be lost.</i>'
                        : 'Terminate selected server(s): %s ?<p>' +
                            '<i> This action will completely remove this servers from AWS.<br>' +
                            'All data that has not been snapshotted will be lost.</i>',
                    objects: isOperationMultiple ? dbInstanceId : null
                },
                processBox: {
                    type: 'terminate',
                    msg: 'Sending terminate command...'
                },
                url: '/tools/aws/rds/instances/xTerminate/',
                params: {
                    cloudLocation: me.down('#cloudLocation').getValue(),
                    dbInstancesIds: Ext.encode(
                        !isOperationMultiple ? [dbInstanceId] : dbInstanceId
                    )
                },
                success: function (response) {
                    store.load();
                    grid.setSelection();
                }
            });

            return me;
        },

        terminateSelectedDbInstance: function () {
            var me = this;

            me.terminateDbInstance(
                me.getSelectedRecord().get('name')
            );

            return me;
        },

        terminateSelectedDbInstances: function () {
            var me = this;

            me.terminateDbInstance(
                me.collectSelectedDbInstancesIds()
            );

            return me;
        },

        rebootDbInstance: function (dbInstanceId) {
            var me = this;

            var isOperationMultiple = Ext.isArray(dbInstanceId);

            Scalr.Request({
                confirmBox: {
                    type: 'reboot',
                    msg: !isOperationMultiple
                        ? 'Reboot server <b>' + dbInstanceId + '</b> ?'
                        : 'Reboot selected server(s): %s ?',
                    objects: isOperationMultiple ? dbInstanceId : null
                },
                processBox: {
                    type: 'reboot',
                    msg: 'Sending reboot command...'
                },
                url: '/tools/aws/rds/instances/xReboot/',
                params: {
                    cloudLocation: me.down('#cloudLocation').getValue(),
                    dbInstancesIds: Ext.encode(
                        !isOperationMultiple ? [dbInstanceId] : dbInstanceId
                    )
                },
                success: function (response) {
                    store.load();
                    grid.setSelection();
                }
            });

            return me;
        },

        rebootSelectedDbInstance: function () {
            var me = this;

            me.rebootDbInstance(
                me.getSelectedRecord().get('name')
            );

            return me;
        },

        rebootSelectedDbInstances: function () {
            var me = this;

            me.rebootDbInstance(
                me.collectSelectedDbInstancesIds()
            );

            return me;
        },

        modifySelectedDbInstance: function () {
            var me = this;

            Scalr.event.fireEvent('redirect',
                '#/tools/aws/rds/instances/' + me.getSelectedRecord().get('name') +
                '/edit?cloudLocation=' + me.down('#cloudLocation').getValue()
            );

            return me;
        },

        columns: [{
            text: "DB Instance",
            flex: 1,
            dataIndex: 'name',
            sortable: true
        }, {
            text: 'Used on',
            flex: 0.9,
            dataIndex: 'farmId',
            sortable: false,
            renderer: function (value, meta, record) {
                var farmName = record.get('farmName');

                if (!Ext.isEmpty(farmName)) {
                    return '<a href="#/farms?farmId=' + value + '">' + farmName + '</a>';
                }

                return '&mdash;';
            }
        }, {
            text: "Engine",
            xtype: 'templatecolumn',
            flex: 0.7,
            minWidth: 160,
            tpl: '{[this.beautifyEngine(values.engine, values.engineVersion)]}',
            sortable: false
        }, {
            text: "Placement",
            width: 120,
            dataIndex: 'avail_zone',
            sortable: false
        }, {
            header: "Status",
            minWidth: 160,
            width: 130,
            dataIndex: 'status',
            sortable: true,
            xtype: 'statuscolumn',
            statustype: 'rdsdbinstance'
        }, {
            xtype: 'optionscolumn',
            menu: [{
                iconCls: 'x-menu-icon-edit',
                text: 'Modify',
                showAsQuickAction: true,
                getVisibility: function(data) {
                    return data['status'] === 'available';
                },
                menuHandler: function(data) {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/instances/' + data['name'] + '/edit?cloudLocation=' + store.proxy.extraParams.cloudLocation);
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'Create snapshot',
                iconCls: 'x-menu-icon-createserversnapshot',
                getVisibility: function(data) {
                    return data['status'] === 'available';
                },
                request: {
                    processBox: {
                        type: 'action'
                    },
                    url: '/tools/aws/rds/snapshots/xCreateSnapshot/',
                    dataHandler: function(data) {
                        return {
                            dbinstance: data['name'],
                            cloudLocation: store.proxy.extraParams.cloudLocation
                        }
                    },
                    success: function() {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/rds/snapshots?dbinstance=' + this.params.dbinstance + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
                    }
                }
            }, {
                text: 'Manage snapshots',
                iconCls: 'x-menu-icon-configure',
                menuHandler: function(data) {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/snapshots?dbinstance=' + data['name'] + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'Create read replica',
                iconCls: 'x-menu-icon-create',
                getVisibility: function(data) {
                    var engine = data['engine'];

                    return !data['isReplica'] && (engine === 'mysql' || engine === 'postgres') && data['status'] === 'available';
                },
                menuHandler: function(data) {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/instances/' + data['name'] + '/createReadReplica?cloudLocation=' + store.proxy.extraParams.cloudLocation);
                }
            }, {
                text: 'Promote read replica',
                iconCls: 'x-menu-icon-promotereplica',
                getVisibility: function(data) {
                    return data['isReplica'] && data['status'] === 'available';
                },
                menuHandler: function(data) {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/instances/' + data['name'] + '/promoteReadReplica?cloudLocation=' + store.proxy.extraParams.cloudLocation);
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'CloudWatch statistics',
                iconCls: 'x-menu-icon-statsload',
                menuHandler: function(data) {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/cloudwatch?objectId=' + data['name'] + '&object=DBInstanceIdentifier&namespace=AWS/RDS&region=' + store.proxy.extraParams.cloudLocation);
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'Events log',
                iconCls: 'x-menu-icon-logs',
                menuHandler: function(data) {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/logs?name=' + data['name'] + '&type=db-instance&cloudLocation=' + store.proxy.extraParams.cloudLocation);
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'Reboot',
                iconCls: 'x-menu-icon-reboot',
                showAsQuickAction: true,
                getVisibility: function(data) {
                    return data.status === 'available';
                },
                menuHandler: function(data) {
                    grid.rebootDbInstance(data.name);
                }
            }, {
                text: 'Terminate',
                iconCls: 'x-menu-icon-terminate',
                showAsQuickAction: true,
                getVisibility: function(data) {
                    return data.status !== 'deleting';
                },
                menuHandler: function(data) {
                    grid.terminateDbInstance(data.name);
                }
            }]
        }],

        dockedItems: [{
            xtype: 'toolbar',
            store: store,
            dock: 'top',
            ui: 'simple',

            defaults: {
                margin: '0 0 0 12'
            },

            items: [{
                xtype: 'filterfield',
                store: store,
                margin: 0,
                flex: 1,
                minWidth: 100,
                maxWidth: 200
            }, {
                xtype: 'cloudlocationfield',
                platforms: [ 'ec2' ],
                gridStore: store
            }, {
                xtype: 'tbfill'
            }, {
                text: 'New DB Instance',
                cls: 'x-btn-green',
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/instances/create');
                }
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.clearAndLoad();
                }
            }, {
                itemId: 'reboot',
                iconCls: 'x-btn-icon-reboot',
                tooltip: 'Select one or more DB Instances to reboot them',
                disabled: true,
                handler: function () {
                    grid.rebootSelectedDbInstances();
                }
            }, {
                itemId: 'terminate',
                iconCls: 'x-btn-icon-terminate',
                cls: 'x-btn-red',
                tooltip: 'Select one or more DB Instances to terminate them',
                disabled: true,
                handler: function () {
                    grid.terminateSelectedDbInstances();
                }
            }]
        }]
    });

    var form = Ext.create('Ext.form.Panel', {
        hidden: true,
        autoScroll: true,

        fieldDefaults: {
            anchor: '100%'
        },

        isOracle: function (engine) {
            return engine.substring(0, 6) === 'oracle';
        },

        getDbInstanceData: function (dbInstanceName, cloudLocation, callback, scope) {
            var me = this;

            me.hide();

            Scalr.Request({
                processBox: {
                    type: 'load'
                },
                url: '/tools/aws/rds/instances/xGetDbInstanceData',
                params: {
                    dbInstanceName: dbInstanceName,
                    cloudLocation: cloudLocation
                },
                success: function (response) {
                    var dbInstanceData = response.instance;

                    if (!Ext.isEmpty(dbInstanceData)) {
                        callback.call(scope, dbInstanceData);
                    }

                    me.show();
                },
                failure: function () {
                    me.show();
                }
            });

            return me;
        },

        hideIopsField: function (hidden) {
            var me = this;

            me.down('[name=Iops]')
                .setVisible(!hidden);

            return me;
        },

        hideSourceInstanceField: function (hidden) {
            var me = this;

            me.down('[name=ReadReplicaSourceDBInstanceIdentifier]')
                .setVisible(!hidden);

            return me;
        },

        hideCharacterSetNameField: function (hidden) {
            var me = this;

            me.down('[name=CharacterSetName]')
                .setVisible(!hidden);

            return me;
        },

        setSecurityGroupsSettings: function (isVpcDefined) {
            var me = this;

            var securityGroupsField = me.down('[name=securityGroups]');
            securityGroupsField.isVpcDefined = isVpcDefined;
            securityGroupsField.cloudLocation = grid.down('#cloudLocation').getValue();

            return me;
        },

        applyDbInstanceData: function (data) {
            var me = this;

            var vpcSecurityGroups = data['VpcSecurityGroups'];
            var isVpcDefined = !Ext.isEmpty(vpcSecurityGroups);

            data.securityGroups = isVpcDefined
                ? vpcSecurityGroups
                : data['DBSecurityGroups'];

            me
                .hideIopsField(Ext.isEmpty(data['Iops']))
                .hideSourceInstanceField(!data.isReplica)
                .hideCharacterSetNameField(
                    !me.isOracle(data['Engine'])
                )
                .setSecurityGroupsSettings(isVpcDefined)
                .getForm().setValues(data);

            me.getRecord().set(data);

            return me;
        },

        setHeader: function (value) {
            var me = this;

            me.down('#main').setTitle(value);

            return me;
        },

        disableModifyButton: function (dbInstanceStatus) {
            var me = this;

            me.down('#modify')
                .setDisabled(dbInstanceStatus !== 'available');

            return me;
        },

        disableTerminateButton: function (dbInstanceStatus) {
            var me = this;

            me.down('#terminate')
                .setDisabled(dbInstanceStatus === 'deleting');

            return me;
        },

        listeners: {
            beforeloadrecord: function (record) {
                var me = this;

                if (!Ext.isEmpty(record.get('Address'))) {
                    me
                        .hideIopsField(Ext.isEmpty(record.get('Iops')))
                        .hideSourceInstanceField(!record.get('isReplica'))
                        .hideCharacterSetNameField(
                            !me.isOracle(record.get('Engine'))
                        )
                        .setSecurityGroupsSettings(
                            !Ext.isEmpty(record.get('VpcSecurityGroups'))
                        );
                }
            },
            afterloadrecord: function (record) {
                var me = this;

                var dbInstanceName = record.get('name');

                if (Ext.isEmpty(record.get('Address'))) {
                    me.getDbInstanceData(
                        dbInstanceName,
                        grid.down('#cloudLocation').getValue(),
                        me.applyDbInstanceData,
                        me
                    );
                }

                var dbInstanceStatus = record.get('status');

                me
                    .setHeader(dbInstanceName)
                    .disableModifyButton(dbInstanceStatus)
                    .disableTerminateButton(dbInstanceStatus);
            }
        },

        defaults: {
            xtype: 'fieldset',
            defaults: {
                xtype: 'displayfield',
                labelWidth: 200,
                width: '100%'
            }
        },

        items: [{
            itemId: 'main',
            headerCls: 'x-fieldset-separator-bottom',
            items: [{
                name: 'farmId',
                fieldLabel: 'Used on',
                renderer: function (farmId) {
                    var record = form.getRecord();

                    if (!Ext.isEmpty(record)) {
                        var farmName = record.get('farmName');

                        if (!Ext.isEmpty(farmName)) {
                            return '<a href="#/farms?farmId=' + farmId + '">' + farmName + '</a>';
                        }
                    }

                    return '&mdash;';
                }
            }, {
                name: 'Address',
                fieldLabel: 'DNS Name'
            }, {
                name: 'InstanceCreateTime',
                fieldLabel: 'Created at'
            }, {
                name: 'isReplica',
                fieldLabel: 'Read Replica',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? 'No' : 'Yes';
                }
            }, {
                name: 'ReadReplicaSourceDBInstanceIdentifier',
                fieldLabel: 'Source Instance'
            }, {
                name: 'DBInstanceClass',
                fieldLabel: 'Type'
            }, {
                name: 'MultiAZ',
                fieldLabel: 'Multi-AZ Deployment'
            }, {
                name: 'AvailabilityZone',
                fieldLabel: 'Availability Zone'
            }, {
                name: 'securityGroups',
                fieldLabel: 'Security Groups',
                separator: ', ',
                isVpcDefined: false,
                getDbSecurityGroupsRenderedValue: function (securityGroupsNames) {
                    var me = this;

                    var cloudLocation = me.cloudLocation;
                    var values = [];

                    Ext.Array.each(securityGroupsNames, function (securityGroupName) {
                        values.push(
                            '<a href="#/tools/aws/rds/sg/edit?' + Ext.Object.toQueryString({
                                dbSgName: securityGroupName,
                                cloudLocation: cloudLocation
                            }) + '">' + securityGroupName + '</a>'
                        );
                    });

                    return values.join(me.separator);
                },
                getVpcSecurityGroupsRenderedValue: function (securityGroups) {
                    var me = this;

                    var cloudLocation = me.cloudLocation;
                    var values = [];

                    Ext.Array.each(securityGroups, function (securityGroup) {
                        values.push(
                            '<a href="#/security/groups/' + securityGroup.vpcSecurityGroupId + '/edit?' + Ext.Object.toQueryString({
                                platform: 'ec2',
                                cloudLocation: cloudLocation
                            }) + '">' + securityGroup.vpcSecurityGroupName + '</a>'
                        );
                    });

                    return values.join(me.separator);
                },
                renderer: function (value) {
                    var me = this;

                    if (!Ext.isEmpty(value)) {
                        return me.isVpcDefined
                            ?  me.getVpcSecurityGroupsRenderedValue(value)
                            :  me.getDbSecurityGroupsRenderedValue(value);
                    }

                    return '';
                }
            }]
        }, {
            title: 'Storage',
            items: [{
                name: 'StorageType',
                fieldLabel: 'Storage type',
                types: {
                    standard: 'Magnetic',
                    gp2: 'General Purpose (SSD)',
                    io1: 'Provisioned IOPS (SSD)'
                },
                renderer: function (value) {
                    return this.types[value];
                }
            }, {
                name: 'Iops',
                fieldLabel: 'IOPS'
            }, {
                name: 'AllocatedStorage',
                fieldLabel: 'Allocated Storage'
            }]
        }, {
            title: 'Database Engine',
            items: [{
                name: 'Engine',
                fieldLabel: 'Engine',
                renderer: function (engineName) {
                    var fullEngineName = Scalr.utils.beautifyEngineName(engineName);

                    if (engineName.indexOf('-') !== -1) {
                        engineName = engineName.substring(0, engineName.indexOf('-'));
                    }

                    return '<img class="x-icon-engine-small x-icon-engine-small-' +
                        engineName + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;' +
                        fullEngineName;
                }
            }, {
                name: 'EngineVersion',
                fieldLabel: 'Version'
            }, {
                name: 'LicenseModel',
                fieldLabel: 'Licensing Model'
            }, {
                name: 'DBParameterGroup',
                fieldLabel: 'Parameter Group'
            }, {
                name: 'OptionGroupName',
                fieldLabel: 'Option Group'
            }, {
                name: 'CharacterSetName',
                fieldLabel: 'Character Set Name'
            }, {
                name: 'Port',
                fieldLabel: 'Port'
            }]
        }, {
            title: 'Database',
            items: [{
                name: 'MasterUsername',
                fieldLabel: 'Master Username'
            }, {
                name: 'DBName',
                fieldLabel: 'Database Name'
            }]
        }, {
            title: 'Maintenance Windows and Backups',
            items: [{
                name: 'AutoMinorVersionUpgrade',
                fieldLabel: 'Auto Minor Version Upgrade',
                renderer: function (value) {
                    return value ? 'Enabled' : 'Disabled';
                }
            }, {
                name: 'PreferredMaintenanceWindow',
                fieldLabel: 'Preferred Maintenance Window'
            }, {
                name: 'PreferredBackupWindow',
                fieldLabel: 'Preferred Backup Window'
            }, {
                name: 'BackupRetentionPeriod',
                fieldLabel: 'Backup Retention Period'
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            maxWidth: 700,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [{
                xtype: 'button',
                itemId: 'modify',
                text: 'Modify',
                handler: function () {
                    grid.modifySelectedDbInstance();
                }
            }, {
                xtype: 'button',
                itemId: 'terminate',
                cls: 'x-btn-red',
                text: 'Terminate',
                handler: function () {
                    grid.terminateSelectedDbInstance();
                }
            }]
        }]
    });

    return Ext.create('Ext.panel.Panel', {
        stateful: true,
        stateId: 'grid-scaling-metrics-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'RDS DB Instances',
            menuHref: '#/tools/aws/rds/instances',
            menuFavorite: true
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: .6,
            maxWidth: 700,
            minWidth: 500,
            layout: 'fit',
            items: [ form ]
        }]
    });
});

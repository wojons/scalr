Scalr.regPage('Scalr.ui.tools.aws.rds.instances.view', function (loadParams, moduleParams) {

    var dbInstancesStore = Ext.create('Scalr.ui.ContinuousStore', {

        fields: [{
            name: 'Address',
            type: 'string'
        }, {
            name: 'AllocatedStorage',
            type: 'auto'
        }, {
            name: 'AutoMinorVersionUpgrade',
            type: 'boolean'
        }, {
            name: 'AvailabilityZone',
            type: 'string'
        }, {
            name: 'BackupRetentionPeriod',
            type: 'number'
        }, {
            name: 'CharacterSetName',
            type: 'string'
        }, {
            name: 'DBClusterIdentifier',
            type: 'string'
        }, {
            name: 'DBInstanceClass',
            type: 'string'
        }, {
            name: 'DBInstanceIdentifier',
            type: 'string'
        }, {
            name: 'DBInstanceStatus',
            type: 'string'
        }, {
            name: 'DBName',
            type: 'string'
        }, {
            name: 'DBParameterGroup',
            type: 'string'
        }, {
            name: 'DBSecurityGroups',
            type: 'auto'
        }, {
            name: 'DBSubnetGroupName',
            type: 'string'
        }, {
            name: 'Engine',
            type: 'string'
        }, {
            name: 'EngineVersion',
            type: 'string'
        }, {
            name: 'InstanceCreateTime',
            type: 'string'
        }, {
            name: 'Iops',
            type: 'number'
        }, {
            name: 'KmsKeyId',
            type: 'string'
        }, {
            name: 'LicenseModel',
            type: 'string'
        }, {
            name: 'MasterUsername',
            type: 'string'
        }, {
            name: 'MultiAZ',
            type: 'string'
        }, {
            name: 'OptionGroupName',
            type: 'string'
        }, {
            name: 'Port',
            type: 'number'
        }, {
            name: 'PreferredBackupWindow',
            type: 'string'
        }, {
            name: 'PreferredMaintenanceWindow',
            type: 'string'
        }, {
            name: 'PubliclyAccessible',
            type: 'boolean'
        }, {
            name: 'ReadReplicaSourceDBInstanceIdentifier',
            type: 'string'
        }, {
            name: 'StorageType',
            type: 'string'
        }, {
            name: 'VpcId',
            type: 'string'
        }, {
            name: 'VpcSecurityGroups',
            type: 'auto'
        }, {
            name: 'isReplica',
            type: 'boolean'
        }, {
            name: 'farmId',
            type: 'string'
        }, {
            name: 'farmName',
            type: 'string'
        }],

        proxy: {
            type: 'ajax',
            url: '/tools/aws/rds/instances/xListInstances',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },

        setDbInstanceStatus: function (dbInstanceIdentifier, dbInstanceStatus) {
            var me = this;

            var record = me.findRecord('DBInstanceIdentifier', dbInstanceIdentifier);

            if (!Ext.isEmpty(record)) {
                record.set('DBInstanceStatus', dbInstanceStatus);
            }

            return record;
        },

        getLaunchedDbInstancesStatuses: function () {
            var me = this;

            var dbInstances = {};

            me.each(function (record) {
                var dbInstanceStatus = record.get('DBInstanceStatus');
                if (dbInstanceStatus !== 'deleted') {
                    dbInstances[record.get('DBInstanceIdentifier')] = dbInstanceStatus;
                }
            });

            return dbInstances;
        },

        updateDbInstances: function () {
            var me = this;

            Scalr.Request({
                url: '/tools/aws/rds/instances/xGetDbInstancesStatus',
                hideErrorMessage: true,
                params: {
                    cloudLocation: dbInstancesGrid.getCloudLocation(),
                    dbInstances: Ext.encode(
                        me.getLaunchedDbInstancesStatuses()
                    )
                },
                success: function (response) {
                    var dbInstances = response.dbInstances;

                    if (!Ext.isEmpty(dbInstances)) {
                        Ext.Object.each(dbInstances, function (dbInstanceIdentifier, dbInstanceData) {
                            var record = me.findRecord('DBInstanceIdentifier', dbInstanceIdentifier);
                            var isRecordExist = !Ext.isEmpty(record);

                            if (isRecordExist && dbInstanceData !== 'deleted') {
                                record.set(dbInstanceData);
                            } else if (isRecordExist) {
                                record.set('DBInstanceStatus', 'deleted');
                            }
                        });

                        me.fireEvent('updaterecords', Ext.Object.getKeys(dbInstances));
                    }

                    return true;
                }
            });

            return me;
        }
    });

    var dbInstancesGrid = Ext.create('Ext.grid.Panel', {
        flex: 1,
        cls: 'x-panel-column-left',
        scrollable: true,

        store: dbInstancesStore,

        plugins: [ 'continuousrenderer', 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }, {
            ptype: 'applyparams',
            loadStoreOnReturn: false
        }],

        viewConfig: {
            emptyText: 'No DB Instances defined.'
        },

        selModel:
            Scalr.isAllowed('AWS_RDS', 'manage') ? {
                selType: 'selectedmodel',
                getVisibility: function (record) {
                    var dbInstanceStatus = record.get('DBInstanceStatus');
                    return dbInstanceStatus !== 'deleting' && dbInstanceStatus !== 'deleted';
                }
            } : null,

        getCloudLocation: function () {
            return this.down('#cloudLocation').getValue();
        },

        areDbInstancesAvailable: function (dbInstances) {
            return Ext.Array.every(dbInstances, function (dbInstance) {
                return dbInstance.get('DBInstanceStatus') === 'available';
            });
        },

        updatePage: function (type, action, dbInstanceData, cloudLocation) {
            var me = this;

            if (type === '/tools/aws/rds/instances' && cloudLocation === me.getCloudLocation()) {
                /** temp fix; todo: improve Scalr.ui.RepeatableTask */
                panel.restartTask = false;
                /** end */

                var store = me.getStore();
                var record;

                if (action === 'launch') {
                    record = store.add(dbInstanceData)[0];
                } else if (action === 'modify') {
                    me.clearSelectedRecord();

                    record = store.findRecord(
                        'DBInstanceIdentifier',
                        dbInstanceData['DBInstanceIdentifier']
                    );
                }

                me.setSelectedRecord(record);

                me.updateDbInstancesTask.start(true);
            }

            return me;
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
            },

            afterrender: function (grid) {
                grid.updateDbInstancesTask = Ext.create('Scalr.ui.RepeatableTask', {
                    scope: dbInstancesStore,
                    handleRequest: true,
                    interval: 60000,
                    run: dbInstancesStore.updateDbInstances,
                    subscribers: {
                        start: 'load',
                        restart: 'refresh',
                        stop: ['beforeload', {
                            event: 'deactivate',
                            scope: panel
                        }],
                        destroy: {
                            event: 'beforedestroy',
                            scope: panel
                        }
                    },
                    stopIf: function (store, records) {
                        return Ext.isEmpty(records);
                    }
                });

                dbInstancesStore.on('updaterecords', grid.maybeReloadSelectedRecord, grid);

                Scalr.event.on('update', grid.updatePage, grid);
            },

            destroy: function (grid) {
                Scalr.event.un('update', grid.updatePage, grid);
            }
        },

        maybeReloadSelectedRecord: function (dbInstanceIdentifiers) {
            dbInstanceIdentifiers = Ext.isArray(dbInstanceIdentifiers)
                ? dbInstanceIdentifiers
                : [dbInstanceIdentifiers];

            var me = this;

            var selectedRecord = me.getSelectedRecord();

            var isSelectedInstanceBeingDeleted = !Ext.isEmpty(selectedRecord)
                && Ext.Array.contains(
                    dbInstanceIdentifiers,
                    selectedRecord.get('DBInstanceIdentifier')
                );

            if (isSelectedInstanceBeingDeleted) {
                me.clearSelectedRecord();
                me.setSelectedRecord(selectedRecord);
            }

            return isSelectedInstanceBeingDeleted;
        },

        collectSelectedDbInstancesIds: function (collectClustersIds) {
            var me = this;

            return Ext.Array.map(
                me.getSelectionModel().getSelection(),

                function (record) {
                    return !collectClustersIds
                        ? record.get('DBInstanceIdentifier')
                        : {
                            dbInstanceIdentifier: record.get('DBInstanceIdentifier'),
                            dbClusterIdentifier: record.get('Engine') === 'aurora'
                                ? record.get('DBClusterIdentifier')
                                : null
                        };
                }
            );
        },

        updateProcessedRecords: function (dbInstancesIdentifiers, dbInstancesStatus) {
            var me = this;

            if (Ext.isEmpty(dbInstancesIdentifiers)) {
                return me;
            }

            var store = me.getStore();
            var selectionModel = me.getSelectionModel();

            Ext.Array.each(dbInstancesIdentifiers, function(dbInstanceIdentifier) {
                selectionModel.deselect(
                    store.setDbInstanceStatus(
                        dbInstanceIdentifier,
                        dbInstancesStatus
                    )
                );
            });

            me.maybeReloadSelectedRecord(dbInstancesIdentifiers);

            return me;
        },

        terminateDbInstance: function (dbInstancesData) {
            var me = this;

            var isOperationMultiple = Ext.isArray(dbInstancesData);

            var dbInstanceIdentifier = !isOperationMultiple
                ? dbInstancesData.dbInstanceIdentifier
                : Ext.Array.map(dbInstancesData, function (instanceData) {
                    return instanceData.dbInstanceIdentifier;
                });

            Scalr.Request({
                confirmBox: {
                    type: 'terminate',
                    msg: !isOperationMultiple
                        ? 'Terminate server <b>' + dbInstanceIdentifier + '</b> ?<p>' +
                            '<i> This action will completely remove this server from AWS.<br>' +
                            'All data that has not been snapshotted will be lost.</i>'
                        : 'Terminate selected server(s): %s ?<p>' +
                            '<i> This action will completely remove this servers from AWS.<br>' +
                            'All data that has not been snapshotted will be lost.</i>',
                    objects: isOperationMultiple ? dbInstanceIdentifier : null
                },
                processBox: {
                    type: 'terminate',
                    msg: 'Sending terminate command...'
                },
                url: '/tools/aws/rds/instances/xTerminate',
                params: {
                    cloudLocation: me.getCloudLocation(),
                    dbInstancesIds: Ext.encode(
                        !isOperationMultiple ? [dbInstancesData] : dbInstancesData
                    )
                },
                success: function (response) {
                    me.updateProcessedRecords(response.processed, 'deleting');
                },
                failure: function (response) {
                    me.updateProcessedRecords(response.processed, 'deleting');
                }
            });

            return me;
        },

        terminateSelectedDbInstance: function () {
            var me = this;

            var record = me.getSelectedRecord();

            me.terminateDbInstance({
                dbInstanceIdentifier: record.get('DBInstanceIdentifier'),
                dbClusterIdentifier: record.get('Engine') === 'aurora'
                    ? record.get('DBClusterIdentifier')
                    : null
            });

            return me;
        },

        terminateSelectedDbInstances: function () {
            var me = this;

            me.terminateDbInstance(
                me.collectSelectedDbInstancesIds(true)
            );

            return me;
        },

        rebootDbInstance: function (dbInstanceIdentifier) {
            var me = this;

            var isOperationMultiple = Ext.isArray(dbInstanceIdentifier);

            Scalr.Request({
                confirmBox: {
                    type: 'reboot',
                    msg: !isOperationMultiple
                        ? 'Reboot server <b>' + dbInstanceIdentifier + '</b> ?'
                        : 'Reboot selected server(s): %s ?',
                    objects: isOperationMultiple ? dbInstanceIdentifier : null
                },
                processBox: {
                    type: 'reboot',
                    msg: 'Sending reboot command...'
                },
                url: '/tools/aws/rds/instances/xReboot',
                params: {
                    cloudLocation: me.getCloudLocation(),
                    dbInstancesIds: Ext.encode(
                        !isOperationMultiple ? [dbInstanceIdentifier] : dbInstanceIdentifier
                    )
                },
                success: function (response) {
                    me.updateProcessedRecords(response.processed, 'rebooting');
                },
                failure: function (response) {
                    me.updateProcessedRecords(response.processed, 'rebooting');
                }
            });

            return me;
        },

        rebootSelectedDbInstance: function () {
            var me = this;

            me.rebootDbInstance(
                me.getSelectedRecord().get('DBInstanceIdentifier')
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

        modifyDbInstance: function (dbInstanceIdentifier, vpcId) {
            var me = this;

            var requestParams = {
                cloudLocation: me.getCloudLocation()
            };

            if (!Ext.isEmpty(vpcId)) {
                requestParams.vpcId = vpcId;
            }

            Scalr.event.fireEvent('redirect',
                '#/tools/aws/rds/instances/' + dbInstanceIdentifier
                + '/edit?'
                + Ext.Object.toQueryString(requestParams)
            );

            return me;
        },

        modifySelectedDbInstance: function () {
            var me = this;

            var selectedRecord = me.getSelectedRecord();

            me.modifyDbInstance(
                selectedRecord.get('DBInstanceIdentifier'),
                selectedRecord.get('VpcId')
            );

            return me;
        },

        columns: [{
            text: 'DB Instance',
            flex: 1,
            dataIndex: 'DBInstanceIdentifier',
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
            text: 'Engine',
            xtype: 'templatecolumn',
            flex: 0.7,
            minWidth: 160,
            tpl: '{[this.beautifyEngine(values.Engine, values.EngineVersion)]}',
            sortable: false
        }, {
            text: 'Placement',
            xtype: 'templatecolumn',
            width: 120,
            sortable: false,
            tpl: [
                '<tpl if="!Ext.isEmpty(AvailabilityZone)">',
                    '{AvailabilityZone}',
                '<tpl else>',
                    '&mdash;',
                '</tpl>'
            ]
        }, {
            header: 'Status',
            minWidth: 160,
            width: 130,
            dataIndex: 'DBInstanceStatus',
            sortable: true,
            xtype: 'statuscolumn',
            statustype: 'rdsdbinstance'
        }, {
            xtype: 'optionscolumn',
            menu: [{
                iconCls: 'x-menu-icon-edit',
                text: 'Modify',
                showAsQuickAction: 1,
                getVisibility: function (data) {
                    return data['DBInstanceStatus'] === 'available' && Scalr.isAllowed('AWS_RDS', 'manage');
                },
                menuHandler: function (data) {
                    dbInstancesGrid.modifyDbInstance(
                        data['DBInstanceIdentifier'],
                        data['VpcId']
                    );
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'Create snapshot',
                iconCls: 'x-menu-icon-createserversnapshot',
                getVisibility: function (data) {
                    return Scalr.isAllowed('AWS_RDS', 'manage')
                        && data['Engine'] !== 'aurora'
                        && data['DBInstanceStatus'] === 'available';
                },
                request: {
                    processBox: {
                        type: 'action'
                    },
                    url: '/tools/aws/rds/snapshots/xCreateSnapshot',
                    dataHandler: function (data) {
                        return {
                            cloudLocation: dbInstancesGrid.getCloudLocation(),
                            dbInstanceId: data['DBInstanceIdentifier']
                        };
                    },
                    success: function () {
                        Scalr.event.fireEvent('redirect',
                            '#/tools/aws/rds/snapshots?' + Ext.Object.toQueryString(this.params)
                        );
                    }
                }
            }, {
                text: 'Manage snapshots',
                iconCls: 'x-menu-icon-configure',
                showAsQuickAction: 4,
                getVisibility: function (data) {
                    return Scalr.isAllowed('AWS_RDS', 'manage') && data['Engine'] !== 'aurora';
                },
                menuHandler: function (data) {
                    Scalr.event.fireEvent('redirect',
                        '#/tools/aws/rds/snapshots?' + Ext.Object.toQueryString({
                            dbInstanceId: data['DBInstanceIdentifier'],
                            cloudLocation: dbInstancesGrid.getCloudLocation()
                        })
                    );
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'Create read replica',
                iconCls: 'x-menu-icon-create',
                getVisibility: function (data) {
                    var engine = data['Engine'];

                    return !data['isReplica']
                        && Ext.Array.contains(['aurora', 'mysql', 'postgres', 'mariadb'], engine)
                        && data['DBInstanceStatus'] === 'available' && Scalr.isAllowed('AWS_RDS', 'manage');
                },
                menuHandler: function (data) {
                    Scalr.event.fireEvent('redirect',
                        '#/tools/aws/rds/instances/' + data['DBInstanceIdentifier']
                        + '/createReadReplica?cloudLocation=' + dbInstancesGrid.getCloudLocation()
                    );
                }
            }, {
                text: 'Promote read replica',
                iconCls: 'x-menu-icon-promotereplica',
                getVisibility: function (data) {
                    return data['isReplica'] && data['DBInstanceStatus'] === 'available' && Scalr.isAllowed('AWS_RDS', 'manage');
                },
                menuHandler: function (data) {
                    Scalr.event.fireEvent('redirect',
                        '#/tools/aws/rds/instances/' + data['DBInstanceIdentifier']
                        + '/promoteReadReplica?cloudLocation=' + dbInstancesGrid.getCloudLocation()
                    );
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'CloudWatch statistics',
                iconCls: 'x-menu-icon-statsload',
                showAsQuickAction: 5,
                getVisibility: function(data) {
                    return Scalr.isAllowed('AWS_CLOUDWATCH');
                },
                menuHandler: function (data) {
                    Scalr.event.fireEvent('redirect',
                        '#/tools/aws/ec2/cloudwatch?' + Ext.Object.toQueryString({
                            objectId: data['DBInstanceIdentifier'],
                            object: 'DBInstanceIdentifier',
                            namespace: 'AWS/RDS',
                            region: dbInstancesGrid.getCloudLocation()
                        })
                    );
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'Events log',
                iconCls: 'x-menu-icon-logs',
                showAsQuickAction: 6,
                getVisibility: function(data) {
                    return Scalr.isAllowed('LOGS_EVENT_LOGS');
                },
                menuHandler: function (data) {
                    Scalr.event.fireEvent('redirect',
                        '#/tools/aws/rds/logs?' + Ext.Object.toQueryString({
                            name: data['DBInstanceIdentifier'],
                            type: 'db-instance',
                            cloudLocation: dbInstancesGrid.getCloudLocation()
                        })
                    );
                }
            }, {
                xtype: 'menuseparator'
            }, {
                text: 'Reboot',
                iconCls: 'x-menu-icon-reboot',
                showAsQuickAction: 2,
                getVisibility: function (data) {
                    return data['DBInstanceStatus'] === 'available' && Scalr.isAllowed('AWS_RDS', 'manage');
                },
                menuHandler: function (data) {
                    dbInstancesGrid.rebootDbInstance(
                        data['DBInstanceIdentifier']
                    );
                }
            }, {
                text: 'Terminate',
                iconCls: 'x-menu-icon-terminate',
                showAsQuickAction: 3,
                getVisibility: function (data) {
                    var dbInstanceStatus = data['DBInstanceStatus'];
                    return dbInstanceStatus !== 'deleting' && dbInstanceStatus !== 'deleted' && Scalr.isAllowed('AWS_RDS', 'manage');
                },
                menuHandler: function (data) {
                    dbInstancesGrid.terminateDbInstance({
                        dbInstanceIdentifier: data['DBInstanceIdentifier'],
                        dbClusterIdentifier: data['Engine'] === 'aurora'
                            ? data['DBClusterIdentifier']
                            : null
                    });
                }
            }]
        }],

        dockedItems: [{
            xtype: 'toolbar',
            store: dbInstancesStore,
            dock: 'top',
            ui: 'simple',

            defaults: {
                margin: '0 0 0 12'
            },

            items: [{
                xtype: 'filterfield',
                store: dbInstancesStore,
                margin: 0,
                flex: 1,
                minWidth: 100,
                maxWidth: 200
            }, {
                xtype: 'cloudlocationfield',
                platforms: [ 'ec2' ],
                gridStore: dbInstancesStore
            }, {
                xtype: 'tbfill'
            }, {
                text: 'New DB Instance',
                cls: 'x-btn-green',
                hidden: !Scalr.isAllowed('AWS_RDS', 'manage'),
                handler: function () {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/instances/create');
                }
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    dbInstancesStore.clearAndLoad();
                }
            }, {
                itemId: 'reboot',
                iconCls: 'x-btn-icon-reboot',
                tooltip: 'Select one or more DB Instances to reboot them',
                disabled: true,
                hidden: !Scalr.isAllowed('AWS_RDS', 'manage'),
                handler: function () {
                    dbInstancesGrid.rebootSelectedDbInstances();
                }
            }, {
                itemId: 'terminate',
                iconCls: 'x-btn-icon-terminate',
                cls: 'x-btn-red',
                tooltip: 'Select one or more DB Instances to terminate them',
                disabled: true,
                hidden: !Scalr.isAllowed('AWS_RDS', 'manage'),
                handler: function () {
                    dbInstancesGrid.terminateSelectedDbInstances();
                }
            }]
        }]
    });

    var dbInstanceForm = Ext.create('Ext.form.Panel', {
        hidden: true,
        autoScroll: true,

        fieldDefaults: {
            anchor: '100%'
        },

        isOracle: function (engine) {
            return engine.substring(0, 6) === 'oracle';
        },

        isAurora: function (engine) {
            return engine === 'aurora';
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
            securityGroupsField.cloudLocation = dbInstancesGrid.getCloudLocation();

            return me;
        },

        hideStorageFieldSet: function (hidden) {
            var me = this;

            me.down('#storage').setVisible(!hidden);

            return me;
        },

        hideKmsKeyField: function (hidden) {
            var me = this;

            me.down('[name=KmsKeyId]').setVisible(!hidden);

            return me;
        },

        hideClusterIdField: function (hidden) {
            var me = this;

            me.down('[name=DBClusterIdentifier]').setVisible(!hidden);

            return me;
        },

        setHeader: function (value) {
            var me = this;

            me.down('#main').setTitle(value);

            return me;
        },

        disableTerminateButton: function (dbInstanceStatus) {
            var me = this;

            me.down('#terminate')
                .setDisabled(
                    dbInstanceStatus === 'deleting'
                    || dbInstanceStatus === 'deleted'
                );

            return me;
        },

        disableModifyButton: function (dbInstanceStatus) {
            var me = this;

            me.down('#modify')
                .setDisabled(dbInstanceStatus !== 'available');

            return me;
        },

        setFarmName: function (farmId, farmName) {
            var me = this;

            if (!Ext.isEmpty(farmId)) {
                me.down('#usedOn')
                    .setValue(
                        '<a href="#/farms?farmId=' + farmId + '">' + farmName + '</a>'
                    );
            }

            return me;
        },

        listeners: {
            beforeloadrecord: function (record) {
                var me = this;

                var engine = record.get('Engine');
                var isAurora = me.isAurora(engine);

                me
                    .hideIopsField(
                        record.get('Iops') === 0
                    )
                    .hideSourceInstanceField(
                        !record.get('isReplica')
                    )
                    .hideCharacterSetNameField(
                        !me.isOracle(engine)
                    )
                    .hideStorageFieldSet(isAurora)
                    .hideClusterIdField(!isAurora)
                    .hideKmsKeyField(
                        Ext.isEmpty(record.get('KmsKeyId'))
                    )
                    .setSecurityGroupsSettings(
                        !Ext.isEmpty(record.get('VpcId'))
                    );

                return true;
            },
            afterloadrecord: function (record) {
                var me = this;

                var dbInstanceIdentifier = record.get('DBInstanceIdentifier');
                var dbInstanceStatus = record.get('DBInstanceStatus');
                var isAurora = me.isAurora(record.get('Engine'));

                me
                    .setHeader(dbInstanceIdentifier)
                    .setFarmName(
                        record.get('farmId'),
                        record.get('farmName')
                    )
                    .disableModifyButton(dbInstanceStatus)
                    .disableTerminateButton(dbInstanceStatus);

                var securityGroupsField = me.down('[name=securityGroups]');

                securityGroupsField.setValue(
                    !securityGroupsField.isVpcDefined
                        ? record.get('DBSecurityGroups')
                        : record.get('VpcSecurityGroups')
                );

                return true;
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
                itemId: 'usedOn',
                fieldLabel: 'Used on',
                value: '&mdash;'
            }, {
                name: 'DBClusterIdentifier',
                fieldLabel: 'DB Cluster Identifier',
                renderer: function (value) {
                    return Ext.isEmpty(value)
                        ? '&mdash;'
                        : '<a href="#/tools/aws/rds/clusters/view?' + Ext.Object.toQueryString({
                            platform: 'ec2',
                            cloudLocation: dbInstancesGrid.getCloudLocation(),
                            dBClusterIdentifier: value
                        }) + '">' + value + '</a>';
                }
            }, {
                name: 'Address',
                fieldLabel: 'DNS Name',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }, {
                name: 'InstanceCreateTime',
                fieldLabel: 'Created at'
            }, {
                name: 'isReplica',
                fieldLabel: 'Read Replica',
                renderer: function (value) {
                    return !value
                        ? '&mdash;'
                        : '<div class="x-grid-icon x-grid-icon-ok" style="cursor: default;"></div>';
                }
            }, {
                name: 'ReadReplicaSourceDBInstanceIdentifier',
                fieldLabel: 'Source Instance',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }, {
                name: 'DBInstanceClass',
                fieldLabel: 'Type'
            }, {
                name: 'MultiAZ',
                fieldLabel: 'Multi-AZ Deployment',
                renderer: function (value) {
                    // fixme: string to boolean
                    return value !== 'Enabled'
                        ? '<span data-qtip="Disabled">&mdash;</span>'
                        : '<div class="x-grid-icon x-grid-icon-ok" data-qtip="Enabled" style="cursor: default;"></div>';
                }
            }, {
                name: 'AvailabilityZone',
                fieldLabel: 'Availability Zone',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
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
            itemId: 'storage',
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
                fieldLabel: 'Allocated Storage',
                renderer: function (value) {
                    if (!Ext.isEmpty(value)) {
                        // temp fix: wait for pending values refactoring
                        return value.indexOf('New value') !== -1
                            ? value
                            : value + ' GB';
                    }

                    return '&mdash;';
                }
            }, {
                name: 'KmsKeyId',
                fieldLabel: 'KMS key'
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
                fieldLabel: 'Port',
                renderer: function (value) {
                    return value !== 0 ? value : '&mdash;';
                }
            }]
        }, {
            title: 'Database',
            items: [{
                name: 'MasterUsername',
                fieldLabel: 'Master Username'
            }, {
                name: 'DBName',
                fieldLabel: 'Database Name',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }]
        }, {
            title: 'Maintenance Windows and Backups',
            items: [{
                name: 'AutoMinorVersionUpgrade',
                fieldLabel: 'Auto Minor Version Upgrade',
                renderer: function (value) {
                    return !value
                        ? '<span data-qtip="Disabled">&mdash;</span>'
                        : '<div class="x-grid-icon x-grid-icon-ok" data-qtip="Enabled" style="cursor: default;"></div>';
                }
            }, {
                name: 'PreferredMaintenanceWindow',
                fieldLabel: 'Preferred Maintenance Window'
            }, {
                name: 'PreferredBackupWindow',
                fieldLabel: 'Preferred Backup Window'
            }, {
                name: 'BackupRetentionPeriod',
                fieldLabel: 'Backup Retention Period',
                renderer: function (value) {
                    if (Ext.isEmpty(value)) {
                        return '&mdash;';
                    }
                    return value + (value === 1 ? 'day' : 'days');
                }
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            hidden: !Scalr.isAllowed('AWS_RDS', 'manage'),
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            maxWidth: 700,
            defaults: {
                flex: 1,
                maxWidth: 160
            },
            items: [{
                xtype: 'button',
                itemId: 'modify',
                text: 'Modify',
                handler: function () {
                    dbInstancesGrid.modifySelectedDbInstance();
                }
            }, {
                xtype: 'button',
                itemId: 'terminate',
                cls: 'x-btn-red',
                text: 'Terminate',
                handler: function () {
                    dbInstancesGrid.terminateSelectedDbInstance();
                }
            }]
        }]
    });

    var panel = Ext.create('Ext.panel.Panel', {
        stateful: true,
        stateId: 'grid-tools-aws-rds-instances-view',

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

        items: [ dbInstancesGrid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: 0.6,
            maxWidth: 700,
            minWidth: 500,
            layout: 'fit',
            items: [ dbInstanceForm ]
        }],

        /** temp fix; todo: improve Scalr.ui.RepeatableTask */
        restartTask: false,

        listeners: {
            activate: function () {
                var me = this;

                var updateDbInstancesTask = dbInstancesGrid.updateDbInstancesTask;

                if (me.restartTask) {
                    if (updateDbInstancesTask.isStopped() && dbInstancesStore.getCount() > 0) {
                        updateDbInstancesTask.start(true);
                    }
                    return true;
                }

                me.restartTask = true;
            }
        }
        /** end */
    });

    return panel;
});
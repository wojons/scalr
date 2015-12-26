Scalr.regPage('Scalr.ui.tools.aws.rds.clusters.view', function (loadParams, moduleParams) {

    var store = Ext.create('Scalr.ui.ContinuousStore', {

        fields: [
            'allocatedStorage',
            'availabilityZones',
            'backupRetentionPeriod',
            'characterSetName',
            'dBClusterIdentifier',
            'dBClusterMembers',
            'dBClusterParameterGroup',
            'dBSubnetGroup',
            'databaseName',
            'endpoint',
            'engine',
            'engineVersion',
            'masterUsername',
            'membersCount',
            'port',
            'preferredBackupWindow',
            'preferredMaintenanceWindow',
            'status',
            'vpcSecurityGroups',
            'vpcId'
        ],

        proxy: {
            type: 'ajax',
            url: '/tools/aws/rds/clusters/xList/',
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
            emptyText: 'No DB Clusters defined.'
        },

        selModel: Scalr.isAllowed('AWS_RDS', 'manage') ? 'selectedmodel' : null,

        listeners: {
            selectionchange: function (selModel, selections) {
                this.down('toolbar').down('#delete')
                    .setDisabled(!selections.length);
            }
        },

        getCloudLocation: function () {
            return this.down('#cloudLocation').getValue();
        },

        collectSelectedDbClustersIds: function () {
            var me = this;

            return Ext.Array.map(
                me.getSelectionModel().getSelection(),

                function (record) {
                    return record.get('dBClusterIdentifier');
                }
            );
        },

        modifySelectedDbCluster: function () {
            var me = this;

            var selectedRecord = me.getSelectedRecord();

            Scalr.event.fireEvent('redirect',
                '#/tools/aws/rds/clusters/' + selectedRecord.get('dBClusterIdentifier') +
                '/edit?' + Ext.Object.toQueryString({
                    cloudLocation: me.getCloudLocation(),
                    vpcId: selectedRecord.get('vpcId')
                })
            );

            return me;
        },

        deleteDbCluster: function (dbClusterIdentifier) {
            var me = this;

            var isOperationMultiple = Ext.isArray(dbClusterIdentifier);

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: !isOperationMultiple
                        ? 'Delete DB Cluster <b>' + dbClusterIdentifier + '</b> ?<p>' +
                            '<i> This action will completely remove this DB Cluster from AWS.</i>'
                        : 'Delete selected DB Cluster(s): %s ?<p>' +
                            '<i> This action will completely remove this DB Cluster from AWS.</i>',
                    objects: isOperationMultiple ? dbClusterIdentifier : null
                },
                processBox: {
                    type: 'delete',
                    msg: 'Sending delete command...'
                },
                url: '/tools/aws/rds/clusters/xDelete/',
                params: {
                    cloudLocation: me.getCloudLocation(),
                    dBClusterIdentifiers: Ext.encode(
                        !isOperationMultiple ? [ dbClusterIdentifier ] : dbClusterIdentifier
                    )
                },
                success: function (response) {
                    store.load();
                    grid.setSelection();
                }
            });

            return me;
        },

        deleteSelectedDbCluster: function () {
            var me = this;

            var record = me.getSelectedRecord();

            me.deleteDbCluster(
                record.get('dBClusterIdentifier')
            );

            return me;
        },

        deleteSelectedDbClusters: function () {
            var me = this;

            me.deleteDbCluster(
                me.collectSelectedDbClustersIds()
            );

            return me;
        },

        columns: [{
            text: 'DB Cluster',
            flex: 1,
            dataIndex: 'dBClusterIdentifier',
            sortable: true
        }, {
            text: 'Engine',
            xtype: 'templatecolumn',
            flex: 0.7,
            minWidth: 160,
            tpl: '{[this.beautifyEngine(values.engine, values.engineVersion)]}',
            sortable: false
        }, {
            text: 'Members',
            xtype: 'templatecolumn',
            dataIndex: 'membersCount',
            width: 110,
            align:'center',
            tpl: [
                '<a href="#/tools/aws/rds/instances?platform=ec2&cloudLocation={[this.getCloudLocation()]}&dBClusterIdentifier={dBClusterIdentifier}" class="x-grid-big-href">{membersCount}</a>', {
                    getCloudLocation: function () {
                        return grid.getCloudLocation();
                    }
                }
            ],
            sortable: true
        }, {
            header: 'Status',
            minWidth: 160,
            width: 130,
            dataIndex: 'status',
            sortable: true,
            xtype: 'statuscolumn',
            statustype: 'rdsdbcluster'
        }, {
            xtype: 'optionscolumn',
            hidden:  !Scalr.isAllowed('AWS_RDS', 'manage'),
            menu: [{
                text: 'Modify',
                iconCls: 'x-menu-icon-edit',
                showAsQuickAction: true,
                getVisibility: function (data) {
                    return data.status === 'available';
                },
                menuHandler: function (data) {
                    Scalr.event.fireEvent('redirect',
                        '#/tools/aws/rds/clusters/' + data.dBClusterIdentifier
                        + '/edit?' + Ext.Object.toQueryString({
                            cloudLocation: grid.getCloudLocation(),
                            vpcId: data.vpcId
                        })
                    );
                }
            }, {
                text: 'Create snapshot',
                iconCls: 'x-menu-icon-createserversnapshot',
                showAsQuickAction: true,
                getVisibility: function (data) {
                    return data.status === 'available';
                },
                request: {
                    processBox: {
                        type: 'action'
                    },
                    url: '/tools/aws/rds/snapshots/xCreateSnapshot',
                    dataHandler: function (data) {
                        return {
                            cloudLocation: grid.getCloudLocation(),
                            dbClusterId: data.dBClusterIdentifier
                        };
                    },
                    success: function () {
                        console.log(this.params);
                        Scalr.event.fireEvent('redirect',
                            '#/tools/aws/rds/snapshots?' + Ext.Object.toQueryString(this.params)
                        );
                    }
                }
            }, {
                text: 'Manage snapshots',
                iconCls: 'x-menu-icon-configure',
                showAsQuickAction: true,
                menuHandler: function (data) {
                    Scalr.event.fireEvent('redirect',
                        '#/tools/aws/rds/snapshots?' + Ext.Object.toQueryString({
                            cloudLocation: grid.getCloudLocation(),
                            dbClusterId: data.dBClusterIdentifier
                        })
                    );
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
            }, /*{
                text: 'New DB Cluster',
                cls: 'x-btn-green',
                hidden: !Scalr.isAllowed('AWS_RDS', 'manage'),
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/clusters/create');
                }
            },*/ {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.clearAndLoad();
                }
            }, {
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more DB Clusters to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('AWS_RDS', 'manage'),
                handler: function () {
                    grid.deleteSelectedDbClusters();
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

        defaults: {
            xtype: 'fieldset',
            defaults: {
                xtype: 'displayfield',
                labelWidth: 200,
                width: '100%'
            }
        },

        setHeader: function (value) {
            var me = this;

            me.down('#main').setTitle(value);

            return me;
        },

        disableDeleteButton: function (dbClusterStatus) {
            var me = this;

            me.down('#delete')
                .setDisabled(dbClusterStatus === 'deleting');

            return me;
        },

        disableModifyButton: function (dbClusterStatus) {
            var me = this;

            me.down('#modify')
                .setDisabled(dbClusterStatus !== 'available');

            return me;
        },

        listeners: {
            afterloadrecord: function (record) {
                var me = this;

                var status = record.get('status');

                me
                    .setHeader(record.get('dBClusterIdentifier'))
                    .disableDeleteButton(status)
                    .disableModifyButton(status);
            },
        },

        items: [{
            itemId: 'main',
            headerCls: 'x-fieldset-separator-bottom',
            items: [{
                name: 'dBClusterMembers',
                fieldLabel: 'DB Cluster Members',
                separator: ', ',
                getMembersRenderedValue: function (members) {
                    var me = this;

                    var cloudLocation = grid.getCloudLocation();
                    var values = [];

                    Ext.Array.each(members, function (instanceId) {
                        values.push(
                            '<a href="#/tools/aws/rds/instances/view?' + Ext.Object.toQueryString({
                                cloudLocation: cloudLocation,
                                dBInstanceIdentifier: instanceId
                            }) + '">' + instanceId + '</a>'
                        );
                    });

                    return values.join(me.separator);
                },
                renderer: function (value) {
                    var me = this;

                    if (!Ext.isEmpty(value)) {
                        return me.getMembersRenderedValue(value);
                    }

                    return '—';
                }
            }, {
                name: 'availabilityZones',
                fieldLabel: 'Availability Zones',
                renderer: function (value) {
                    if (Ext.isArray(value)) {
                        return value.join(', ');
                    }

                    return '&mdash;';
                }
            }, {
                name: 'vpcSecurityGroups',
                fieldLabel: 'Security Groups',
                separator: ', ',
                getVpcSecurityGroupsRenderedValue: function (securityGroups) {
                    var me = this;

                    var cloudLocation = grid.getCloudLocation();
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
                        return me.getVpcSecurityGroupsRenderedValue(value);
                    }

                    return '—';
                }
            }]
        }, {
            title: 'Database Engine',
            items: [{
                name: 'engine',
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
                name: 'engineVersion',
                fieldLabel: 'Version'
            }, {
                name: 'dBClusterParameterGroup',
                fieldLabel: 'Parameter Group'
            }, {
                name: 'port',
                fieldLabel: 'Port'
            }]
        }, {
            title: 'Database',
            items: [{
                name: 'masterUsername',
                fieldLabel: 'Master Username'
            }, {
                name: 'databaseName',
                fieldLabel: 'Database Name',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '—';
                },
            }]
        }, {
            title: 'Maintenance Windows and Backups',
            items: [{
                name: 'preferredMaintenanceWindow',
                fieldLabel: 'Preferred Maintenance Window'
            }, {
                name: 'preferredBackupWindow',
                fieldLabel: 'Preferred Backup Window'
            }, {
                name: 'backupRetentionPeriod',
                fieldLabel: 'Backup Retention Period',
                renderer: function (value) {
                    if (Ext.isEmpty(value)) {
                        return '&mdash;';
                    }
                    return value + (parseInt(value) === 1 ? 'day' : 'days');
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
                    grid.modifySelectedDbCluster();
                }
            }, {
                xtype: 'button',
                itemId: 'delete',
                cls: 'x-btn-red',
                text: 'Delete',
                handler: function () {
                    grid.deleteSelectedDbCluster();
                }
            }]
        }]
    });

    return Ext.create('Ext.panel.Panel', {
        stateful: true,
        stateId: 'grid-tools-aws-rds-clusters-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'RDS DB Clusters',
            menuHref: '#/tools/aws/rds/clusters',
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

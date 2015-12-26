Scalr.regPage('Scalr.ui.tools.aws.rds.snapshots', function (loadParams, moduleParams) {

    var snapshotsStore = Ext.create('store.store', {
        fields: [
            'id',
            'name',
            'storage',
            'idtcreated',
            'avail_zone',
            'engine',
            'status',
            'port',
            'dtcreated',
            'dBClusterIdentifier'
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/aws/rds/snapshots/xListSnapshots'
        },
        remoteSort: true
    });

    var snapshotsGrid = Ext.create('Ext.grid.Panel', {

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'RDS DB Snapshots',
            menuHref: '#/tools/aws/rds/snapshots',
            menuFavorite: true
        },

        store: snapshotsStore,

        stateful: true,
        stateId: 'grid-tools-aws-rds-snapshots-view',

        plugins: [ 'gridstore', 'applyparams' ],

        viewConfig: {
            emptyText: 'No DB Snapshots found.',
            loadingText: 'Loading DB Snapshots...'
        },

        selModel:
            Scalr.isAllowed('AWS_RDS', 'manage') ?
            {
                selType: 'selectedmodel',
                getVisibility: function (record) {
                    return record.get('type') !== 'automated';
                }
            } : null,

        listeners: {
            selectionchange: function (selModel, selections) {
                this.down('scalrpagingtoolbar')
                    .down('#delete')
                        .setDisabled(Ext.isEmpty(selections));
            }
        },

        getCloudLocation: function () {
            return this.down('#cloudLocation').getValue();
        },

        deleteSelectedDbSnapshots: function () {
            var me = this;

            var selectionModel = me.getSelectionModel();
            var selectedRecords = selectionModel.getSelection();

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: 'Delete selected DB Snapshot(s): %s ?',
                    objects: Ext.Array.map(selectedRecords, function (record) {
                        return record.get('name');
                    })
                },
                processBox: {
                    type: 'delete',
                    msg: 'Deleting DB Snapshot(s) ...'
                },
                url: '/tools/aws/rds/snapshots/xDeleteSnapshots',
                params: {
                    cloudLocation: me.getCloudLocation(),
                    snapshots: Ext.encode(
                        Ext.Array.map(selectedRecords, function (record) {
                            return {
                                name: record.get('id'),
                                engine: record.get('engine')
                            };
                        })
                    )
                },
                success: function () {
                    snapshotsStore.load();
                    selectionModel.deselectAll();
                },
                failure: function () {
                    snapshotsStore.load();
                    selectionModel.deselectAll();
                }
            });

            return me;
        },

        columns: [{
            header: "DB Snapshot",
            flex: 1,
            dataIndex: 'name',
            sortable: true
        }, {
            header: "Port",
            xtype: 'templatecolumn',
            width: 70,
            sortable: false,
            tpl: [
                '<tpl if="!Ext.isEmpty(port)">',
                    '{port}',
                '<tpl else>',
                    '&mdash;',
                '</tpl>'
            ]
        }, {
            header: "Status",
            minWidth: 160,
            width: 130,
            dataIndex: 'status',
            sortable: true,
            xtype: 'statuscolumn',
            statustype: 'rdsdbcluster'
        }, {
            text: 'Engine',
            xtype: 'templatecolumn',
            dataIndex: 'engine',
            flex: 0.7,
            minWidth: 160,
            tpl: '{[this.beautifyEngine(values.engine)]}'
        }, {
            header: "Storage",
            width: 75,
            dataIndex: 'storage',
            sortable: false
        }, {
            header: "Created at",
            width: 170,
            dataIndex: 'dtcreated',
            sortable: true
        }, {
            header: "Instance created at",
            width: 170,
            dataIndex: 'idtcreated',
            sortable: false
        }, {
            xtype: 'optionscolumn',
            width: 120,
            menu: [{
                text: 'Restore',
                iconCls: 'x-menu-icon-restoredbinstance',
                showAsQuickAction: true,
                getVisibility: function (data) {
                    return data.status === 'available';
                },
                menuHandler: function (data) {
                    Scalr.event.fireEvent(
                        'redirect',
                        '#/tools/aws/rds/' + (data.engine !== 'aurora' ? 'instances' : 'clusters')
                        + '/restore?' + Ext.Object.toQueryString({
                            snapshot: data.name,
                            cloudLocation: snapshotsGrid.getCloudLocation()
                        })
                    );
                }
            }]
        }],

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: snapshotsStore,
            dock: 'top',
            beforeItems: [{
                text: 'New DB Instance',
                hidden: !Scalr.isAllowed('AWS_RDS', 'manage'),
                cls: 'x-btn-green',
                handler: function () {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/instances/create');
                }
            }],
            afterItems: [{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more DB Snapshot(s) to delete them.',
                disabled: true,
                hidden: !Scalr.isAllowed('AWS_RDS', 'manage'),
                handler: function () {
                    snapshotsGrid.deleteSelectedDbSnapshots();
                }
            }],

            items: [{
                xtype: 'filterfield',
                store: snapshotsStore,
                margin: 0,
                flex: 1,
                minWidth: 100,
                maxWidth: 250
            }, {
                xtype: 'cloudlocationfield',
                platforms: ['ec2'],
                gridStore: snapshotsStore,
                margin: '0 0 0 15'
            }, {
                xtype: 'cyclealt',
                fieldLabel: 'Snapshots',
                name: 'type',
                cls: 'x-btn-compressed',
                getItemIconCls: false,
                width: 200,
                margin: '0 0 0 15',
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: [{
                        text: 'All Snapshots',
                        value: null
                    },{
                        text: 'Manual Snapshots',
                        value: 'manual'
                    },{
                        text: 'Automated Snapshots',
                        value: 'automated'
                    }]
                },
                changeHandler: function (field, item) {
                    snapshotsStore.applyProxyParams({
                        type: item.value
                    });
                },
            }]
        }]
    });

    return snapshotsGrid;
});

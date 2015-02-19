Scalr.regPage('Scalr.ui.tools.aws.rds.instances.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [ "engine", "status", "hostname", "port", "name", "username", "type", "storage", "dtadded", "avail_zone", "isReplica", "engineVersion", 'multiAz' ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/aws/rds/instances/xListInstances/'
        },
        remoteSort: true
    });
    return Ext.create('Ext.grid.Panel', {
        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instances',
        scalrOptions: {
            'reload': false,
            'maximize': 'all'
        },
        store: store,
        stateId: 'grid-tools-aws-rds-instances-view',
        stateful: true,
        plugins: {
            ptype: 'gridstore'
        },
        tools: [{
            xtype: 'gridcolumnstool'
        }, {
            xtype: 'favoritetool',
            favorite: {
                text: 'RDS Instances',
                href: '#/tools/aws/rds/instances'
            }
        }],
        viewConfig: {
            emptyText: 'No db instances found',
            loadingText: 'Loading db instances ...'
        },

        columns: [
            { text: "Name", flex: 1, dataIndex: 'name', sortable: true },
            //{ text: "Hostname", flex: 1, dataIndex: 'hostname', sortable: false },
            { text: "Engine", width: 100, dataIndex: 'engine', sortable: false },
            { text: "Engine version", width: 130, dataIndex: 'engineVersion', sortable: false },
            //{ text: "Port", width: 70, dataIndex: 'port', sortable: false },
            { header: "Status", minWidth: 160, width: 130, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'rdsdbinstance' },
            //{ text: "Username", width: 130, dataIndex: 'username', sortable: false },
            { text: "Placement", width: 120, dataIndex: 'avail_zone', sortable: false },
            { text: "Type", width: 120, dataIndex: 'type', sortable: true },
            { text: "Storage", width: 75, dataIndex: 'storage', sortable: false },
            { header: "Multi-AZ", width: 75, dataIndex: 'multiAz', sortable: false, xtype: 'templatecolumn', align: 'center', tpl:
                '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-<tpl if="!multiAz">minus<tpl else>ok</tpl>"/>'
            },
            { header: "Read replica", width: 100, dataIndex: 'isReplica', sortable: false, xtype: 'templatecolumn', align: 'center', tpl:
            '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-<tpl if="!isReplica">minus<tpl else>ok</tpl>"/>'
            },
            { text: "Created at", width: 160, dataIndex: 'dtadded', sortable: true },
            {
                xtype: 'optionscolumn2',
                menu: [{
                    text: 'Details',
                    iconCls: 'x-menu-icon-info',
                    menuHandler: function (data) {
                        document.location.href = '#/tools/aws/rds/instances/' + data['name'] + '/details?cloudLocation=' + store.proxy.extraParams.cloudLocation;
                    }
                }, {
                    iconCls: 'x-menu-icon-edit',
                    text: 'Modify',
                    getVisibility: function (data) {
                        return  data['status'] === 'available';
                    },
                    menuHandler: function (data) {
                        document.location.href = '#/tools/aws/rds/instances/' + data['name'] + '/edit?cloudLocation=' + store.proxy.extraParams.cloudLocation;
                    }
                }, {
                    xtype: 'menuseparator'
                }, {
                    text: 'Create snapshot',
                    iconCls: 'x-menu-icon-createserversnapshot',
                    getVisibility: function (data) {
                        return  data['status'] === 'available';
                    },
                    request: {
                        processBox: {
                            type: 'action'
                        },
                        url: '/tools/aws/rds/snapshots/xCreateSnapshot/',
                        dataHandler: function (data) {
                            return {
                                dbinstance: data['name'],
                                cloudLocation: store.proxy.extraParams.cloudLocation
                            }
                        },
                        success: function () {
                            document.location.href = '#/tools/aws/rds/snapshots?dbinstance=' + this.params.dbinstance + '&cloudLocation=' + store.proxy.extraParams.cloudLocation;
                        }
                    }
                }, {
                    text: 'Manage snapshots',
                    iconCls: 'x-menu-icon-configure',
                    menuHandler: function (data) {
                        document.location.href = '#/tools/aws/rds/snapshots?dbinstance=' + data['name'] + '&cloudLocation=' + store.proxy.extraParams.cloudLocation;
                    }
                }, {
                    xtype: 'menuseparator'
                }, {
                    text: 'Create read replica',
                    iconCls: 'x-menu-icon-clone',
                    getVisibility: function (data) {
                        var engine = data['engine'];

                        return !data['isReplica'] && (engine === 'mysql'
                            || engine === 'postgres') && data['status'] === 'available';
                    },
                    menuHandler: function (data) {
                        document.location.href = '#/tools/aws/rds/instances/' + data['name'] + '/createReadReplica?cloudLocation=' + store.proxy.extraParams.cloudLocation;
                    }
                }, {
                    text: 'Promote read replica',
                    iconCls: 'x-menu-icon-clone',
                    getVisibility: function (data) {
                        return data['isReplica'] && data['status'] === 'available';
                    },
                    menuHandler: function (data) {
                        document.location.href = '#/tools/aws/rds/instances/' + data['name'] + '/promoteReadReplica?cloudLocation=' + store.proxy.extraParams.cloudLocation;
                    }
                }, {
                    xtype: 'menuseparator'
                }, {
                    text: 'CloudWatch statistics',
                    iconCls: 'x-menu-icon-statsload',
                    menuHandler: function (data) {
                        document.location.href = '#/tools/aws/ec2/cloudwatch/view?objectId=' + data['name'] + '&object=DBInstanceIdentifier&namespace=AWS/RDS&region=' + store.proxy.extraParams.cloudLocation;
                    }
                }, {
                    xtype: 'menuseparator'
                }, {
                    text: 'Events log',
                    iconCls: 'x-menu-icon-logs',
                    menuHandler: function (data) {
                        document.location.href = '#/tools/aws/rds/logs?name=' + data['name'] + '&type=db-instance&cloudLocation=' + store.proxy.extraParams.cloudLocation;
                    }
                }, {
                    xtype: 'menuseparator'
                }, {
                    text: 'Reboot',
                    getVisibility: function (data) {
                        return  data['status'] === 'available';
                    },
                    iconCls: 'x-menu-icon-reboot',
                    request: {
                        confirmBox: {
                            msg: 'Reboot server "{name}"?',
                            type: 'reboot'
                        },
                        processBox: {
                            type: 'reboot',
                            msg: 'Sending reboot command ...'
                        },
                        url: '/tools/aws/rds/instances/xReboot/',
                        dataHandler: function (data) {
                            return {
                                instanceId: data['name'],
                                cloudLocation: store.proxy.extraParams.cloudLocation
                            };
                        },
                        success: function(data) {
                            store.load();
                        }
                    }
                }, {
                    text: 'Terminate',
                    iconCls: 'x-menu-icon-terminate',
                    request: {
                        confirmBox: {
                            msg: 'Terminate server <b>{name}</b>?<p>' +
                            '<i> This action will completely remove this server from AWS.<br>' +
                            'All data that has not been snapshotted will be lost.</i>',
                            type: 'terminate'
                        },
                        processBox: {
                            type: 'terminate',
                            msg: 'Sending terminate command ...'
                        },
                        url: '/tools/aws/rds/instances/xTerminate/',
                        dataHandler: function (data) {
                            return {
                                instanceId: data['name'],
                                cloudLocation: store.proxy.extraParams.cloudLocation
                            };
                        },
                        success: function(data) {
                            store.load();
                        }
                    }
                }]
            }
        ],

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            beforeItems: [{
                text: 'Add instance',
                cls: 'x-btn-green-bg',
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/instances/create');
                }
            }],
            items: [{
                xtype: 'fieldcloudlocation',
                itemId: 'cloudLocation',
                store: {
                    fields: [ 'id', 'name' ],
                    data: moduleParams.locations,
                    proxy: 'object'
                },
                gridStore: store
            }]
        }]
    });
});

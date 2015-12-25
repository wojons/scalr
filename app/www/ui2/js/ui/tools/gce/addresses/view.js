Scalr.regPage('Scalr.ui.tools.gce.addresses.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'id','ip', 'description','status','createdAt',
            {name: 'serverId', defaultValue: null},
            {name: 'serverIndex', defaultValue: null},
            {name: 'instanceId', defaultValue: null},
            {name: 'farmId', defaultValue: null},
            {name: 'farmName', defaultValue: null},
            {name: 'roleName', defaultValue: null},
            {name: 'farmRoleId', defaultValue: null},

        ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/gce/addresses/xList/'
        },
        remoteSort: true,
        sorters: {
            property: 'id',
            direction: 'DESC'
        }
    });

    return Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: true,
            maximize: 'all',
            menuTitle: 'GCE Static IPs',
            menuHref: '#/tools/gce/addresses',
            menuFavorite: true
        },
        store: store,
        stateId: 'grid-tools-gce-addresses-view',
        stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams',
            forbiddenFilters: ['platform']
        }],

        viewConfig: {
            emptyText: 'No static IPs found',
            loadingText: 'Loading static IPs ...'
        },

        columns: [
            { header: "Used By", flex: 1, dataIndex: 'farmName', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="farmId"><a href="#/farms?farmId={farmId}" title="Farm {farmName}">{farmName}</a>' +
                    '<tpl if="roleName">&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view"' +
                        'title="Role {roleName}">{roleName}</a> #{serverIndex}' +
                    '</tpl>' +
                '</tpl>' +
                '<tpl if="! farmId">&mdash;</tpl>'
            },
            { header: "Name", width: 150, dataIndex: 'id', sortable: true },
            { header: "IP", width: 140, dataIndex: 'ip', sortable: true },
            { header: "Description", width: 200, dataIndex: 'description', sortable: true },
            { header: "Date", width: 175, dataIndex: 'createdAt', sortable: true },
            { header: "Status", width: 100, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
                '{status}'
            },
            { header: "Server", flex: 1, dataIndex: 'serverId', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="serverId"><a href="#/servers?serverId={serverId}">{serverId}</a></tpl>' +
                '<tpl if="!serverId">{instanceId}</tpl>'
            }
        ],

        selModel: Scalr.isAllowed('GCE_STATIC_IPS', 'manage') ? 'selectedmodel' : null,
        listeners: {
            selectionchange: function(selModel, selections) {
                this.down('scalrpagingtoolbar').down('#delete').setDisabled(!selections.length);
            }
        },

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            afterItems: [{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more IPs to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('GCE_STATIC_IPS', 'manage'),
                handler: function() {
                    var request = {
                        confirmBox: {
                            msg: 'Delete selected static ip(s): %s ?',
                            type: 'delete'
                        },
                        processBox: {
                            msg: 'Deleting static ip(s) ...',
                            type: 'delete'
                        },
                        url: '/tools/gce/addresses/xRemove/',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('id'));
                    }
                    request.params = { addressId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
                    Scalr.Request(request);
                }
            }],
            items: [{
                xtype: 'filterfield',
                store: store,
                form: {
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Address ID',
                        labelAlign: 'top',
                        name: 'addressId'
                    }]
                }
            }, ' ', {
                xtype: 'cloudlocationfield',
                platforms: ['gce'],
                locations: moduleParams.locations,
                gridStore: store
            }]
        }]
    });
});

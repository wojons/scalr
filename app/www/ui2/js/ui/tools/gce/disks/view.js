Scalr.regPage('Scalr.ui.tools.gce.disks.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'id','description','status','size','snapshotId','createdAt',
            {name: 'farmId', defaultValue: null},
            {name: 'farmName', defaultValue: null},
            {name: 'roleName', defaultValue: null},
            {name: 'farmRoleId', defaultValue: null},
            {name: 'serverId', defaultValue: null},
            {name: 'serverIndex', defaultValue: null}
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/gce/disks/xList/'
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
            menuTitle: 'GCE Persistent disks',
            menuHref: '#/tools/gce/disks',
            menuFavorite: true
        },
        store: store,
        stateId: 'grid-tools-gce-disks-view',
        stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams',
            forbiddenFilters: ['platform']
        }],

        viewConfig: {
            emptyText: 'No PDs found',
            loadingText: 'Loading persistent disks ...'
        },

        columns: [
            { header: "Used by", flex: 1, sortable: false, xtype: 'templatecolumn', tpl: [
                '<tpl if="farmId">',
                    '<a href="#/farms?farmId={farmId}" title="Farm {farmName}">{farmName}</a>',
                    '<tpl if="farmRoleName">',
                        '&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {farmRoleName}">',
                        '{farmRoleName}</a> #<a href="#/servers?serverId={serverId}">{serverIndex}</a>',
                    '</tpl>',
                '<tpl elseif="cloudServerId">',
                    '<span style="color: gray">{cloudServerId}</span>',
                '<tpl else>',
                    '&mdash;',
                '</tpl>'
            ]},
            { header: "Persistent disk", flex: 1, dataIndex: 'id', sortable: true },
            { header: "Description", flex: 1, dataIndex: 'description', sortable: true },
            { header: "Date", width: 170, dataIndex: 'createdAt', sortable: true },
            { header: "Size (GB)", width: 110, dataIndex: 'size', sortable: true },
            { header: "Status", width: 180, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
                '{status}'
            }
        ],

        selModel: Scalr.isAllowed('GCE_PERSISTENT_DISKS', 'manage') ? 'selectedmodel' : null,
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
                tooltip: 'Select one or more volumes to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('GCE_PERSISTENT_DISKS', 'manage'),
                handler: function() {
                    var request = {
                        confirmBox: {
                            msg: 'Delete selected volume(s): %s ?',
                            type: 'delete'
                        },
                        processBox: {
                            msg: 'Deleting volume(s) ...',
                            type: 'delete'
                        },
                        url: '/tools/gce/disks/xRemove/',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('id'));
                    }
                    request.params = { diskId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
                    Scalr.Request(request);
                }
            }],
            items: [{
                xtype: 'filterfield',
                store: store,
                form: {
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Disk ID',
                        labelAlign: 'top',
                        name: 'diskId'
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

Scalr.regPage('Scalr.ui.security.groups.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'name', 'description', 'id', 'vpcId'
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/security/groups/xListGroups/'
        },
        remoteSort: true
    });

    return Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: true,
            maximize: 'all',
            menuTitle: Scalr.utils.getPlatformName(loadParams['platform']) + ' Security groups',
            menuFavorite: true,
            menuHref: '#/security/groups?platform=' + loadParams['platform'],
            menuParentStateId: 'grid-security-groups-view-' + loadParams['platform']
        },
        store: store,
        stateId: 'grid-security-groups-view',
        stateful: true,
        plugins: [ 'gridstore', 'applyparams' ],

        viewConfig: {
            emptyText: "No security groups found",
            loadingText: 'Loading security groups ...'
        },

        columns: [
            { header: "ID", width: 180, dataIndex: 'id', sortable: true },
            { header: "Security group", flex: 1, dataIndex: 'name', sortable: true },
            { header: "Description", flex: 2, dataIndex: 'description', sortable: true },
            { header: "VPC ID", width: 180, dataIndex: 'vpcId', sortable: true },
            {
                xtype: 'optionscolumn',
                menu: [{
                    iconCls: 'x-menu-icon-' + (Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage') ? 'edit' : 'details'),
                    text: Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage') ? 'Edit' : 'View',
                    showAsQuickAction: true,
                    menuHandler: function(data) {
                        Scalr.event.fireEvent('redirect', '#/security/groups/' + data['id'] + '/edit?platform=' + loadParams['platform'] + (Scalr.isCloudstack(loadParams['platform']) ? '' : '&cloudLocation=' + store.proxy.extraParams.cloudLocation));
                    }
                }]
            }
        ],

        selModel: Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage') ? 'selectedmodel' : null,
        listeners: {
            selectionchange: function(selModel, selections) {
                this.down('scalrpagingtoolbar').down('#delete').setDisabled(!selections.length);
            }
        },

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            beforeItems: [{
                text: 'New group',
                cls: 'x-btn-green',
                hidden: !Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage'),
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/security/groups/create?platform=' + loadParams['platform'] + (Scalr.isCloudstack(loadParams['platform']) ? '' : '&cloudLocation=' + store.proxy.extraParams.cloudLocation));
                }
            }],
            afterItems: [{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more security group(s) to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage'),
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected security group(s): %s ?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Deleting group(s) ...'
                        },
                        url: '/security/groups/xRemove',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('name'));
                    }
                    request.params = { groups: Ext.encode(data), platform:loadParams['platform'], cloudLocation: store.proxy.extraParams.cloudLocation };
                    Scalr.Request(request);
                }
            }],
            items: [{
                xtype: 'filterfield',
                store: store
            }, ' ', {
                xtype: 'cloudlocationfield',
                platforms: [loadParams['platform']],
                gridStore: store,
                hidden: Scalr.isCloudstack(loadParams['platform'])
            }]
        }]
    });
});

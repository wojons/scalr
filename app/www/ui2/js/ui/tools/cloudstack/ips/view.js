Scalr.regPage('Scalr.ui.tools.cloudstack.ips.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'farmId', 'farmRoleId', 'farmName', 'roleName', 'serverIndex', 'serverId',
            'ipId', 'ip', 'purpose', 'networkName', 'state', 'dtAllocated', 'instanceId'
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/cloudstack/ips/xListIps'
        },
        remoteSort: true
    });

    return Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: true,
            maximize: 'all',
            menuTitle: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Public IPs',
            //menuFavorite: true
        },
        store: store,
        stateId: 'grid-tools-cloudstack-ips-view',
        stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams',
            filterIgnoreParams: [ 'platform' ]
        }],

        viewConfig: {
            emptyText: 'No public IPs found',
            loadingText: 'Loading public IPs ...'
        },

        columns: [
            { header: "Used by", flex: 1, dataIndex: 'instanceId', sortable: false, xtype: 'templatecolumn', tpl:
                '<tpl if="farmId">' +
                    '<a href="#/farms?farmId={farmId}" title="Farm {farmName}">{farmName}</a>' +
                    '<tpl if="roleName">' +
                        '&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {roleName}">' +
                        '{roleName}</a> #<a href="#/servers?serverId={serverId}">{serverIndex}</a>' +
                    '</tpl>' +
                '</tpl>' +
				'<tpl if="instanceId && ! farmId">' +
				    'Non scalr server: {instanceId}' +
				'</tpl>' +
                '<tpl if="!farmId && !instanceId">&mdash;</tpl>'
            },
			{ header: "IP Address", flex: .5, dataIndex: 'ip', sortable: true },
            { header: "Network", flex: .5, dataIndex: 'networkName', sortable: true },
            { header: "Purpose", width: 150, dataIndex: 'purpose', sortable: true},
            { header: "State", width: 120, dataIndex: 'state', sortable: true }
        ],

        selModel: Scalr.isAllowed('CLOUDSTACK_PUBLIC_IPS', 'manage') ? 'selectedmodel' : null,
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
                tooltip: 'Select one or more IP(s) to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('CLOUDSTACK_PUBLIC_IPS', 'manage'),
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected IP(s): %s ?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Deleting IP(s) ...'
                        },
                        url: '/tools/cloudstack/ips/xRemove/',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('ipId'));
                        request.confirmBox.objects.push(records[i].get('ipId'));
                    }
                    request.params = { ipId: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
                    Scalr.Request(request);
                }
            }],
            items: [{
                xtype: 'filterfield',
                store: store
            }, ' ', {
                xtype: 'cloudlocationfield',
                platforms: [loadParams['platform']],
				gridStore: store
            }]
        }]
    });
});

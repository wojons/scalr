Scalr.regPage('Scalr.ui.tools.cloudstack.ips.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'farmId', 'farmRoleId', 'farmName', 'roleName', 'serverIndex', 'serverId',
            'ipId', 'ip', 'purpose', 'networkName', 'state', 'dtAllocated', 'instanceId'
        ],
        proxy: {
            type: 'scalr.paging',
            extraParams: loadParams,
            url: '/tools/cloudstack/ips/xListIps'
        },
        remoteSort: true
    });

    return Ext.create('Ext.grid.Panel', {
        title: 'Tools &raquo; Cloudstack &raquo; Public IPs',
        scalrOptions: {
            'reload': false,
            'maximize': 'all'
        },
        scalrReconfigureParams: { volumeId: '' },
        store: store,
        stateId: 'grid-tools-cloudstack-ips-view',
        stateful: true,
        plugins: {
            ptype: 'gridstore'
        },
        tools: [{
            xtype: 'gridcolumnstool'
        }, {
            xtype: 'favoritetool',
            favorite: {
                text: 'Cloudstack Public IPs',
                href: '#/tools/cloudstack/ips'
            }
        }],

        viewConfig: {
            emptyText: 'No public IPs found',
            loadingText: 'Loading public IPs ...'
        },

        columns: [
            { header: "Used by", flex: 1, dataIndex: 'id', sortable: false, xtype: 'templatecolumn', tpl:
                '<tpl if="farmId">' +
                    '<a href="#/farms/{farmId}/view" title="Farm {farmName}">{farmName}</a>' +
                    '<tpl if="roleName">' +
                        '&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {roleName}">' +
                        '{roleName}</a> #<a href="#/servers/{serverId}/view">{serverIndex}</a>' +
                    '</tpl>' +
                '</tpl>' +
				'<tpl if="instanceId && ! farmId">' +
				    'Non scalr server: {instanceId}' +
				'</tpl>' +
                '<tpl if="!farmId && !instanceId"><img src="/ui2/images/icons/false.png" /></tpl>'
            },
			{ header: "IP Address", width: 120, dataIndex: 'ip', sortable: true },
            { header: "Network", width: 150, dataIndex: 'networkName', sortable: true },
            { header: "Purpose", width: 150, dataIndex: 'purpose', sortable: true},
            { header: "State", width: 120, dataIndex: 'state', sortable: true },
            {
                xtype: 'optionscolumn',
                getOptionVisibility: function (item, record) {
                    return true;
                },

                optionsMenu: [{
                    itemId: 'option.delete',
                    text: 'Delete',
                    iconCls: 'x-menu-icon-delete',
                    request: {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Are you sure want to delete Public IP "{ip}"?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Deleting IP(s) ...'
                        },
                        url: '/tools/cloudstack/ips/xRemove/',
                        dataHandler: function (record) {
                            return { ipId: Ext.encode([record.get('ipId')]), cloudLocation: store.proxy.extraParams.cloudLocation };
                        },
                        success: function () {
                            store.load();
                        }
                    }
                }]
            }
        ],

        multiSelect: true,
        selModel: {
            selType: 'selectedmodel'
        },

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
                ui: 'paging',
                itemId: 'delete',
                iconCls: 'x-tbar-delete',
                tooltip: 'Select one or more IP(s) to delete them',
                disabled: true,
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
                xtype: 'fieldcloudlocation',
                itemId: 'cloudLocation',
                store: {
                    fields: [ 'id', 'name' ],
                    data: moduleParams.locations,
                    proxy: 'object'
                },
                gridStore: store,
                cloudLocation: loadParams['cloudLocation'] || ''
            }]
        }]
    });
});

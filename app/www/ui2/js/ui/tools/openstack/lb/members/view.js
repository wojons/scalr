Scalr.regPage('Scalr.ui.tools.openstack.lb.members.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: ['status', 'weight', 'admin_state_up', 'tenant_id', 'pool_name', 'pool_id', 'address', 'protocol_port', 'id'],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/openstack/lb/members/xList'
        },
        remoteSort: true
    });

    var panel = Ext.create('Ext.grid.Panel', {

        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Load balancers &raquo; Members',
        itemId: 'lbMembersGrid',

        scalrOptions: {
            //'reload': false,
            'maximize': 'all'
        },

        store: store,

        stateId: 'grid-tools-openstack-lb-members-view',
        stateful: true,

        plugins: {
            ptype: 'gridstore'
        },
        tools: [{
            xtype: 'gridcolumnstool'
        }, {
            xtype: 'favoritetool',
            favorite: {
                text: 'LB Members',
                href: '#/tools/openstack/lb/members'
            }
        }],

        viewConfig: {
            emptyText: 'No members found',
            loadingText: 'Loading members ...'
        },

        columns: [
            { header: "ID", flex: 1, dataIndex: 'id', sortable: true },
            { header: "Pool", xtype: 'templatecolumn', flex: 1, sortable: true,
                tpl: new Ext.XTemplate(
                    '<a href="#/tools/openstack/lb/pools/info?{[this.getParams()]}&poolId={pool_id}">{pool_name}</a>', {
                        getParams: function() {
                            var platform = 'platform=' + store.proxy.extraParams.platform,
                                cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation;
                            return platform + cloudLocation;
                        }
                    })
            },
            { header: "IP address", flex: 1, dataIndex: 'address', sortable: true },
            { header: "Protocol port", flex: 0.5, dataIndex: 'protocol_port', sortable: true }
        ],

        multiSelect: true,
        selModel: {
            selType: 'selectedmodel'
        },

        listeners: {
            selectionchange: function(selModel, selections) {
                var toolbar = this.down('scalrpagingtoolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
            }
        },

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            ignoredLoadParams: ['platform'],
            store: store,
            dock: 'top',
            beforeItems: [{
                text: 'Add member',
                cls: 'x-btn-green-bg',
                handler: function() {
                    var platform = 'platform=' + store.proxy.extraParams.platform,
                        cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation,
                        params = platform + cloudLocation;

                    Scalr.event.fireEvent('redirect',
                        '#/tools/openstack/lb/members/create?' + params
                    );
                }
            }],
            afterItems: [{
                ui: 'paging',
                itemId: 'delete',
                disabled: true,
                iconCls: 'x-tbar-delete',
                tooltip: 'Delete',
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected member(s): %s ?'
                        },
                        processBox: {
                            type: 'delete'
                        },
                        params: loadParams,
                        url: '/tools/openstack/lb/members/xRemove/',
                        success: function() {
                            store.load();
                        },
                        failure: function() {
                            store.load();
                        }
                    };

                    var records = panel.getSelectionModel().getSelection(),
                        members = [];
                    request.confirmBox.objects = [];

                    for (var i = 0, recordsNumber = records.length; i < recordsNumber; i++) {
                        members.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('id'))
                    }

                    request.params.memberId = Ext.encode(members);
                    Scalr.Request(request);
                }
            }],

            items: [{
                xtype: 'filterfield',
                store: store
            }, ' ', {
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

    return panel;
});

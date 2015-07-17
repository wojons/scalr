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

        itemId: 'lbMembersGrid',

        scalrOptions: {
            //'reload': false,
            maximize: 'all',
            menuTitle: Scalr.utils.getPlatformName(loadParams['platform']) + ' LB Members',
            //menuFavorite: true
        },

        store: store,

        stateId: 'grid-tools-openstack-lb-members-view',
        stateful: true,

        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams',
            filterIgnoreParams: [ 'platform' ]
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

        selModel: 'selectedmodel',
        listeners: {
            selectionchange: function(selModel, selections) {
                var toolbar = this.down('scalrpagingtoolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
            }
        },

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            beforeItems: [{
                text: 'New member',
                cls: 'x-btn-green',
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
                itemId: 'delete',
                disabled: true,
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
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
                xtype: 'cloudlocationfield',
                platforms: [loadParams['platform']],
				gridStore: store
            }]
        }]
    });

    return panel;
});

Scalr.regPage('Scalr.ui.tools.openstack.contrail.networks.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [ 'fq_name', 'uuid', 'network_policy_refs' ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/openstack/contrail/networks/xList'
        },
        remoteSort: true
    });

    var panel = Ext.create('Ext.grid.Panel', {
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Networking &raquo; Networks',

        scalrOptions: {
            //'reload': false,
            'maximize': 'all'
        },
        store: store,

        stateId: 'grid-tools-openstack-contrail-networks-view',
        stateful: true,

        plugins: {
            ptype: 'gridstore'
        },
        tools: [{
            xtype: 'gridcolumnstool'
        }, {
            xtype: 'favoritetool',
            favorite: {
                text: 'Contrail policies',
                href: '#/tools/openstack/contrail/networks'
            }
        }],

        viewConfig: {
            emptyText: 'No networks found',
            loadingText: 'Loading networks ...'
        },

        columns: [
            { header: "Name", flex: 1, dataIndex: 'fq_name', sortable: false, xtype: 'templatecolumn', tpl: '{[values.fq_name[2]]}' },
            { header: "Attached policies", flex: 2, sortable: false, xtype: 'templatecolumn', tpl:
                '<tpl if="network_policy_refs">' +
                    '<tpl for="network_policy_refs">' +
                        '{[values.to[2]]}' +
                        '<tpl if="xindex < xcount">, </tpl>' +
                    '</tpl>' +
                '<tpl else><img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-minus" /></tpl>'
            },
            { xtype: 'optionscolumn2',
                menu: [{
                    text: 'Edit network',
                    iconCls: 'x-menu-icon-edit',
                    menuHandler: function(data) {
                        var platform = 'platform=' + store.proxy.extraParams.platform,
                            cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation,
                            networkId = '&networkId=' + data['uuid'],
                            params = platform + cloudLocation + networkId;

                        Scalr.event.fireEvent('redirect',
                            '#/tools/openstack/contrail/networks/edit?' + params
                        );
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
                text: 'Add network',
                cls: 'x-btn-green-bg',
                handler: function() {
                    var platform = 'platform=' + store.proxy.extraParams.platform,
                        cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation,
                        params = platform + cloudLocation;

                    Scalr.event.fireEvent('redirect',
                        '#/tools/openstack/contrail/networks/create?' + params
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
                            msg: 'Delete selected network(s): %s ?'
                        },
                        processBox: {
                            type: 'delete'
                        },
                        params: loadParams,
                        url: '/tools/openstack/contrail/networks/xRemove/',
                        success: function() {
                            store.load();
                        },
                        failure: function() {
                            store.load();
                        }
                    };

                    var records = panel.getSelectionModel().getSelection(),
                        networks = [];
                    request.confirmBox.objects = [];

                    for (var i = 0, recordsNumber = records.length; i < recordsNumber; i++) {
                        networks.push(records[i].get('uuid'));
                        request.confirmBox.objects.push(records[i].get('fq_name')[2])
                    }

                    request.params.networkId = Ext.encode(networks);
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

Scalr.regPage('Scalr.ui.tools.openstack.contrail.policies.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [ 'fq_name', 'uuid', 'network_policy_entries' ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/openstack/contrail/policies/xList'
        },
        remoteSort: true
    });

    var panel = Ext.create('Ext.grid.Panel', {
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Networking &raquo; Policies',

        scalrOptions: {
            //'reload': false,
            'maximize': 'all'
        },
        store: store,

        stateId: 'grid-tools-openstack-contrail-policies-view',
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
                href: '#/tools/openstack/contrail/policies'
            }
        }],

        viewConfig: {
            emptyText: 'No policies found',
            loadingText: 'Loading policies ...'
        },

        columns: [
            { header: "Name", width: 250, dataIndex: 'fq_name', sortable: false, xtype: 'templatecolumn', tpl: '{[values.fq_name[2]]}' },
            { header: "Rules", flex: 2, sortable: false, xtype: 'templatecolumn', tpl:
                '<tpl for="values.network_policy_entries.policy_rule">' +
                    '{action_list.simple_action} protocol {protocol} network {[values.src_addresses[0].virtual_network]} port ' +
                    '<tpl for="src_ports">' +
                        '<tpl if="start_port != -1 && xindex == 1">[ </tpl>' +
                        '<tpl if="start_port == -1 && end_port == -1">' +
                            'any' +
                        '<tpl elseif="start_port != end_port">' +
                            '{start_port}-{end_port}' +
                        '<tpl else>' +
                            '{start_port}' +
                        '</tpl>' +
                        '<tpl if="xcount &gt; xindex">, </tpl>' +
                        '<tpl if="start_port != -1 && xcount == xindex"> ]</tpl>' +
                    '</tpl>' +
                    ' {direction} network {[values.dst_addresses[0].virtual_network]} port ' +
                    '<tpl for="dst_ports">' +
                        '<tpl if="start_port != -1 && xindex == 1">[ </tpl>' +
                        '<tpl if="start_port == -1 && end_port == -1">' +
                            'any' +
                        '<tpl elseif="start_port != end_port">' +
                            '{start_port}-{end_port}' +
                        '<tpl else>' +
                            '{start_port}' +
                        '</tpl>' +
                        '<tpl if="xcount &gt; xindex">, </tpl>' +
                        '<tpl if="start_port != -1 && xcount == xindex"> ]</tpl>' +
                    '</tpl>' +
                    '<br>' +
                '</tpl>'
            },
            { xtype: 'optionscolumn2',
                menu: [{
                    text: 'Edit',
                    iconCls: 'x-menu-icon-edit',
                    menuHandler: function(data) {
                        var platform = 'platform=' + store.proxy.extraParams.platform,
                            cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation,
                            policyId = '&policyId=' + data['uuid'],
                            params = platform + cloudLocation + policyId;

                        Scalr.event.fireEvent('redirect',
                            '#/tools/openstack/contrail/policies/edit?' + params
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
                text: 'Add policy',
                cls: 'x-btn-green-bg',
                handler: function() {
                    var platform = 'platform=' + store.proxy.extraParams.platform,
                        cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation,
                        params = platform + cloudLocation;

                    Scalr.event.fireEvent('redirect',
                        '#/tools/openstack/contrail/policies/create?' + params
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
                            msg: 'Delete selected policy(s): %s ?'
                        },
                        processBox: {
                            type: 'delete'
                        },
                        params: loadParams,
                        url: '/tools/openstack/contrail/policies/xRemove/',
                        success: function() {
                            store.load();
                        },
                        failure: function() {
                            store.load();
                        }
                    };

                    var records = panel.getSelectionModel().getSelection(),
                        policies = [];
                    request.confirmBox.objects = [];

                    for (var i = 0, recordsNumber = records.length; i < recordsNumber; i++) {
                        policies.push(records[i].get('uuid'));
                        request.confirmBox.objects.push(records[i].get('fq_name')[2])
                    }

                    request.params.policyId = Ext.encode(policies);
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

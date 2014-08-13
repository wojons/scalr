Scalr.regPage('Scalr.ui.tools.openstack.contrail.dns.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [ 'href', 'fq_name', 'uuid', 'virtual_DNS_data' ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/openstack/contrail/dns/xList'
        },
        remoteSort: true
    });

    var panel = Ext.create('Ext.grid.Panel', {
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Networking &raquo; Virtual DNS',

        scalrOptions: {
            //'reload': false,
            'maximize': 'all'
        },

        store: store,

        stateId: 'grid-tools-openstack-contrail-dns-view',
        stateful: true,

        plugins: {
            ptype: 'gridstore'
        },
        tools: [{
            xtype: 'gridcolumnstool'
        }, {
            xtype: 'favoritetool',
            favorite: {
                text: 'Contrail Virtual DNS',
                href: '#/tools/openstack/contrail/dns'
            }
        }],

        viewConfig: {
            emptyText: 'No DNS records found',
            loadingText: 'Loading DNS records ...'
        },

        columns: [
            { header: "Server name", flex: 1, dataIndex: 'fq_name', sortable: false, xtype: 'templatecolumn', tpl: '{[values.fq_name[1]]}' },
            { header: "Domain name", flex: 1, sortable: false, xtype: 'templatecolumn', tpl: '{[values.virtual_DNS_data.domain_name]}' },
            { header: "Forwarders", flex: 1, sortable: false, xtype: 'templatecolumn', tpl: '{[values.virtual_DNS_data.next_virtual_DNS]}' },
            { header: "Record order", flex: 1, sortable: false, xtype: 'templatecolumn', tpl: '{[values.virtual_DNS_data.record_order]}' },
            { xtype: 'optionscolumn2',
                menu: [{
                    text: 'Edit',
                    iconCls: 'x-menu-icon-edit',
                    menuHandler: function(data) {
                        var platform = 'platform=' + store.proxy.extraParams.platform,
                            cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation,
                            dnsId = '&dnsId=' + data['uuid'],
                            params = platform + cloudLocation + dnsId;

                        Scalr.event.fireEvent('redirect',
                            '#/tools/openstack/contrail/dns/edit?' + params
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
                text: 'Add virtual DNS',
                cls: 'x-btn-green-bg',
                handler: function() {
                    var platform = 'platform=' + store.proxy.extraParams.platform,
                        cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation,
                        params = platform + cloudLocation;

                    Scalr.event.fireEvent('redirect',
                        '#/tools/openstack/contrail/dns/create?' + params
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
                            msg: 'Delete selected DNS(s): %s ?'
                        },
                        processBox: {
                            type: 'delete'
                        },
                        params: loadParams,
                        url: '/tools/openstack/contrail/dns/xRemove/',
                        success: function() {
                            store.load();
                        },
                        failure: function() {
                            store.load();
                        }
                    };

                    var records = panel.getSelectionModel().getSelection(),
                        dns = [];
                    request.confirmBox.objects = [];

                    for (var i = 0, recordsNumber = records.length; i < recordsNumber; i++) {
                        dns.push(records[i].get('uuid'));
                        request.confirmBox.objects.push(records[i].get('uuid'))
                    }

                    request.params.dnsId = Ext.encode(dns);
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

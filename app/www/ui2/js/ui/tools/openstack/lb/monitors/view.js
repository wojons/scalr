Scalr.regPage('Scalr.ui.tools.openstack.lb.monitors.view', function (loadParams, moduleParams) {

    var store = Ext.create('store.store', {
        fields: ['status', 'admin_state_up', 'tenant_id', 'id', 'delay', 'max_retries', 'timeout', 'type'],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/openstack/lb/monitors/xList'
        },
        remoteSort: true
    });

    var panel = Ext.create('Ext.grid.Panel', {

        itemId: 'lbMonitorsGrid',

        scalrOptions: {
            //'reload': false,
            maximize: 'all',
            menuTitle: Scalr.utils.getPlatformName(loadParams['platform']) + ' LB Monitors',
            //menuFavorite: true
        },

        store: store,

        stateId: 'grid-tools-openstack-lb-monitors-view',
        stateful: true,

        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams',
            filterIgnoreParams: [ 'platform' ]
        }],

        viewConfig: {
            emptyText: 'No monitors found',
            loadingText: 'Loading monitors ...'
        },

        columns: [
            { header: "ID", xtype: 'templatecolumn', flex: 1, sortable: true,
                tpl: new Ext.XTemplate(
                    '<a href="#/tools/openstack/lb/monitors/info?{[this.getParams()]}&monitorId={id}">{id}</a>', {
                        getParams: function() {
                            var platform = 'platform=' + store.proxy.extraParams.platform,
                                cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation;
                            return platform + cloudLocation;
                        }
                    })
            },
            { header: "Monitor type", flex: 1, dataIndex: 'type', sortable: true },
            { header: "Delay", width: 100, dataIndex: 'delay' },
            { header: "Timeout", width: 100, dataIndex: 'timeout' },
            { header: "Max retries", width: 125, dataIndex: 'max_retries' },
            { xtype: 'optionscolumn',
                menu: [{
                    text: 'View',
                    iconCls: 'x-menu-icon-view',
                    showAsQuickAction: true,
                    menuHandler: function(data) {
                        var platform = 'platform=' + store.proxy.extraParams.platform,
                            cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation,
                            monitorId = '&monitorId=' + data['id'],
                            params = platform + cloudLocation + monitorId;

                        Scalr.event.fireEvent('redirect',
                            '#/tools/openstack/lb/monitors/info?' + params
                        );
                    }
                }]
            }
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
                text: 'New monitor',
                cls: 'x-btn-green',
                handler: function() {
                    var platform = 'platform=' + store.proxy.extraParams.platform,
                        cloudLocation = '&cloudLocation=' + store.proxy.extraParams.cloudLocation,
                        params = platform + cloudLocation;

                    Scalr.event.fireEvent('redirect',
                        '#/tools/openstack/lb/monitors/create?' + params
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
                            msg: 'Delete selected monitor(s): %s ?'
                        },
                        processBox: {
                            type: 'delete'
                        },
                        params: loadParams,
                        url: '/tools/openstack/lb/monitors/xRemove/',
                        success: function() {
                            store.load();
                        },
                        failure: function() {
                            store.load();
                        }
                    };

                    var records = panel.getSelectionModel().getSelection(),
                        monitors = [];
                    request.confirmBox.objects = [];

                    for (var i = 0, recordsNumber = records.length; i < recordsNumber; i++) {
                        monitors.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('id'))
                    }

                    request.params.monitorId = Ext.encode(monitors);
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

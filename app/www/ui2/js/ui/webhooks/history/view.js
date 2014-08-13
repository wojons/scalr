Scalr.regPage('Scalr.ui.webhooks.history.view', function (loadParams, moduleParams) {

	var store = Ext.create('store.store', {
		fields: ['historyId', 'url', 'created', 'farmId', 'eventId', 'eventType', 'status', 'responseCode', 'payload', 'webhookName', 'webhookId', 'endpointId', 'errorMsg'],
		proxy: {
			type: 'ajax',
			url: '/webhooks/history/xGetList/',
            reader: {
                type: 'json',
                root: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
		},
        sorters: {
            property: 'created',
            direction: 'DESC'
        },
        leadingBufferZone: 0,
        trailingBufferZone: 0,
        pageSize: 100,
        buffered: true,
		remoteSort: true,
        purgePageCount: 0,
        listeners: {
            beforeload: function() {
                var selModel = grid.getSelectionModel();
                selModel.deselectAll();
                selModel.setLastFocused(null);
            },
            prefetch: function(store, records) {
                if (records) {
                    //console.log(this.getCount() + records.length + ' of ' + this.getTotalCount());
                }
            }
        },
        updateParamsAndLoad: function(params, reset) {
            if (reset) {
                this.proxy.extraParams = {};
            }
            var proxyParams = this.proxy.extraParams;
            Ext.Object.each(params, function(name, value) {
                if (value === undefined) {
                    delete proxyParams[name];
                } else {
                    proxyParams[name] = value;
                }
            });
            this.removeAll();
            this.load();
        },
        isFilteredByEventId: function() {
            return !!this.proxy.extraParams.eventId;
        }
	});

    var resetFilterFields = function() {
        grid.down('#filterfield').setValue(null).clearButton.hide();
    };

    var reconfigurePage = function(params) {
        params = params || {};
        cb = function(reconfigure){
            if (params['eventId']) {
                resetFilterFields();
                grid.down('#filterfield').setValue('(eventId:' + params['eventId'] + ')').clearButton.show();
                store.updateParamsAndLoad({eventId: params['eventId']}, true);
            } else if (store.isFilteredByEventId() || !reconfigure) {
                resetFilterFields();
                store.updateParamsAndLoad({eventId: undefined}, true);
            }
        };
        if (grid.view.viewReady) {
            cb(true);
        } else {
            grid.view.on('viewready', function(){cb();}, grid.view, {single: true});
        }
    };


    var grid = Ext.create('Ext.grid.Panel', {
        xtype: 'grid',
        itemId: 'history',
        flex: 1,
        cls: 'x-grid-shadow x-grid-shadow-buffered x-panel-column-left',
        store: store,
        padding: '0 0 12 0',
        plugins: [
            'focusedrowpointer',

            {
                ptype: 'bufferedrenderer',
                scrollToLoadBuffer: 100,
                synchronousRender: false
            }
        ],
        forceFit: true,
        viewConfig: {
            emptyText: 'No history items found',
            deferEmptyText: false,
            loadMask: false
        },

        columns: [
            { header: 'Datetime', width: 156, dataIndex: 'created', sortable: false},
            { header: 'Endpoint', flex: 1, dataIndex: 'url', sortable: false, xtype: 'templatecolumn', tpl: '<a href="#/webhooks/endpoints?endpointId={endpointId}">{url}</a>'},
            { header: 'Webhook', flex: .6, dataIndex: 'webhookName', sortable: false, xtype: 'templatecolumn', tpl: '<a href="#/webhooks/configs?webhookId={webhookId}">{webhookName}</a>'},
            { header: 'Event', flex: .8, dataIndex: 'eventType', sortable: false, xtype: 'templatecolumn', tpl: '<a href="#/farms/{farmId}/events?eventId={eventId}">{eventType}</a>'},
            { header: 'Status', maxWidth: 100, dataIndex: 'status', sortable: false, xtype: 'statuscolumn', statustype: 'webhookhistory', resizable: false},
            { header: 'Response code', width: 140, dataIndex: 'responseCode', sortable: false}
        ],

        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                itemId: 'filterfield',
                store: store,
                flex: 1,
                minWidth: 100,
                maxWidth: 380,
                margin: 0,
                separatedParams: ['eventId']
            },{
                xtype: 'tbfill',
                flex: .01
            },{
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                tooltip: 'Refresh',
                handler: function() {
                    store.updateParamsAndLoad();
                }
            }]
        }]
    });


	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
		fieldDefaults: {
			anchor: '100%'
		},
		layout: 'fit',
        overflowX: 'hidden',
        overflowY: 'auto',
		listeners: {
            afterrender: function() {
                var me = this;
                grid.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                    if (newFocused) {
                        if (me.getRecord() !== newFocused) {
                            me.loadInfo(newFocused);
                        }
                    } else {
                        me.setVisible(false);
                        me.getForm().reset(true);
                    }
                });
            },
            beforedestroy: function() {
                this.abortCurrentRequest();
            }
		},
        abortCurrentRequest: function() {
            if (this.currentRequest) {
                Ext.Ajax.abort(this.currentRequest);
                delete this.currentRequest;
            }
        },
        loadInfo: function(record) {
            var me = this;
            me.abortCurrentRequest();
            me.up().mask('Loading...');
            me.hide();
            me.getForm()._record = record;
            if (!record.get('payload')) {
                me.currentRequest = Scalr.Request({
                    url: '/webhooks/history/xGetInfo',
                    params: {historyId: record.get('historyId')},
                    success: function (data) {
                        delete me.currentRequest;
                        if (data['info']['historyId'] == me.getRecord().get('historyId')) {
                            me.getRecord().set(data['info']);
                            me.showInfo();
                        }
                    }
                });
            } else {
                me.showInfo();
            }
        },
        showInfo: function() {
            this.down('#payload').setValue(this.getRecord().get('payload'));
            this.up().unmask();
            this.show();
        },
		items: [{
			xtype: 'fieldset',
            itemId: 'formtitle',
            cls: 'x-fieldset-separator-none',
            defaults: {
                labelWidth: 90
            },
            layout: 'fit',
			items: [{
                 xtype: 'textarea',
                 itemId: 'payload',
                 readOnly: true,
                 fieldLabel: 'Payload',
                 labelAlign: 'top'
            }]
        }]
	});
	
	var panel = Ext.create('Ext.panel.Panel', {
		cls: 'scalr-ui-panel-webhooks-ebndpoints',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			title: 'History',
			reload: false,
			maximize: 'all',
			leftMenu: {
				menuId: 'webhooks',
				itemId: 'history'
			}
		},
        listeners: {
            applyparams: reconfigurePage
        },
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            cls: 'x-transparent-mask',
            flex: .6,
            maxWidth: 640,
            minWidth: 400,
            layout: 'fit',
            items: [
                form
            ]
        }]
	});
	return panel;
});
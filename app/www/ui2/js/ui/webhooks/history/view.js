Scalr.regPage('Scalr.ui.webhooks.history.view', function (loadParams, moduleParams) {
    var scalrOptions;
    if (moduleParams['levelMap'][moduleParams['level']] == 'account') {
		scalrOptions = {
			title: 'Account management &raquo; Webhooks &raquo; History',
			maximize: 'all',
			leftMenu: {
				menuId: 'settings',
				itemId: 'webhooks',
                showPageTitle: true
			}
		};
    } else {
		scalrOptions = {
			title: Ext.String.capitalize(moduleParams['levelMap'][moduleParams['level']]) + ' webhooks &raquo; History',
			maximize: 'all',
			leftMenu: {
				menuId: 'webhooks',
				itemId: 'history',
                showPageTitle: true
			}
		};
    }

	var store = Ext.create('store.store', {
		fields: ['historyId', 'url', 'created', 'farmId', 'serverId', 'eventId', 'eventType', 'status', 'responseCode', 'payload', 'webhookName', 'webhookId', 'endpointId', 'errorMsg', 'handleAttempts'],
		proxy: {
			type: 'ajax',
			url: '/webhooks/history/xGetList/',
            reader: {
                type: 'json',
                root: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            },
            extraParams: {level: moduleParams['level']}
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
                this.proxy.extraParams = {level: moduleParams['level']};
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
            { header: 'Event date and time', width: 160, dataIndex: 'created', sortable: false},
            { header: 'Webhook', flex: .6, dataIndex: 'webhookName', sortable: false, xtype: 'templatecolumn', tpl: '<a href="#/webhooks/configs?level='+moduleParams['levelMap'][moduleParams['level']]+'&webhookId={webhookId}">{webhookName}</a>'},
            { header: 'Event', flex: .6, dataIndex: 'eventType', sortable: false, xtype: 'templatecolumn', tpl: '<a href="#/logs/events?eventId={eventId}">{eventType}</a>'},
            { header: 'Attempts', width: 85, dataIndex: 'handleAttempts', sortable: false},
            { header: 'Status', maxWidth: 90, dataIndex: 'status', sortable: false, xtype: 'statuscolumn', statustype: 'webhookhistory', resizable: false},
            {
                header: 'Last response code',
                flex: .9,
                maxWidth: 150,
                dataIndex: 'responseCode',
                sortable: false,
                xtype: 'templatecolumn',
                tpl:
                    '<tpl if="handleAttempts&gt;0">' +
                        '<tpl if="status!=1">' +
                            '<img style="float:right" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-error" data-qtip="Attempt #{handleAttempts} failed. Cause: <b>{[values.errorMsg?Ext.util.Format.htmlEncode(Ext.util.Format.htmlEncode(values.errorMsg)):\'unknown\']}</b>" />' +
                        '</tpl>' +
                        '{[values.responseCode||\'None\']} ' +
                    '</tpl>'
            }
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
            }, moduleParams['level'] == 2 ? {
                xtype: 'webhooksaccountmenu',
                dock: 'top',
                flex: 2,
                value: 'history'
            } : {
                xtype: 'tbfill',
                flex: .1
            },{
                xtype: 'tbfill',
                flex: .1
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
                    params: {historyId: record.get('historyId'), level: moduleParams['level']},
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
            var record = this.getRecord();
            this.down('#payload').setValue(record.get('payload'));
            this.down('#eventId').setValue('<a href="#/logs/events?eventId='+record.get('eventId')+'">'+record.get('eventId')+'</a>');
            this.down('#serverId').setValue(record.get('serverId') ? '<a href="#/servers/view?serverId='+record.get('serverId')+'">'+record.get('serverId')+'</a>' : '-');
            this.down('#payload').setValue(record.get('payload'));
            this.down('#endpoint').setValue('<a href="#/webhooks/endpoints?level='+moduleParams['levelMap'][moduleParams['level']]+'&endpointId='+record.get('endpointId')+'">'+record.get('url')+'</a>');
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
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
			items: [{
                xtype: 'displayfield',
                itemId: 'endpoint',
                fieldLabel: 'Endpoint'
            },{
                xtype: 'displayfield',
                itemId: 'eventId',
                fieldLabel: 'Event ID'
            },{
                xtype: 'displayfield',
                itemId: 'serverId',
                fieldLabel: 'Server ID'
            },{
                xtype: 'textarea',
                itemId: 'payload',
                readOnly: true,
                flex: 1,
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
		scalrOptions: scalrOptions,
        listeners: {
            applyparams: reconfigurePage
        },
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            cls: 'x-transparent-mask',
            flex: .5,
            maxWidth: 640,
            minWidth: 300,
            layout: 'fit',
            items: [
                form
            ]
        }]
	});

	return panel;
});
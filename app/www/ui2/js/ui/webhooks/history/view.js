Scalr.regPage('Scalr.ui.webhooks.history.view', function (loadParams, moduleParams) {

	var store = Ext.create('Scalr.ui.ContinuousStore', {
		fields: ['historyId', 'url', 'created', 'farmId', 'serverId', 'eventId', 'eventType', 'status', 'responseCode', 'payload', 'webhookName', 'webhookId', 'endpointId', 'errorMsg', 'handleAttempts'],
		proxy: {
			type: 'ajax',
			url: '/webhooks/history/xGetList/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
		},
        sorters: {
            property: 'created',
            direction: 'DESC'
        },
        listeners: {
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
            this.load();
        },
        isFilteredByEventId: function() {
            return !!this.proxy.extraParams.eventId;
        }
	});

    var grid = Ext.create('Ext.grid.Panel', {
        xtype: 'grid',
        itemId: 'history',
        flex: 1,
        cls: 'x-panel-column-left',
        store: store,
        plugins: [{
            ptype: 'applyparams'
        },{
            ptype: 'focusedrowpointer'
        },{
            ptype: 'selectedrecord',
            getForm: function() {
                return form;
            }
        },{
            ptype: 'continuousrenderer'
        }],
        viewConfig: {
            emptyText: 'No history items found',
            deferEmptyText: false
        },

        columns: [
            { header: 'Event date and time', width: 175, dataIndex: 'created', sortable: false},
            { header: 'Webhook', flex: .6, dataIndex: 'webhookName', sortable: false, xtype: 'templatecolumn', tpl: '<a href="#/'+(Scalr.scope=='account'?'account/':'')+'webhooks/configs?webhookId={webhookId}">{webhookName}</a>'},
            { header: 'Event', flex: .6, dataIndex: 'eventType', sortable: false, xtype: 'templatecolumn', tpl: '<a href="#/logs/events?eventId={eventId}">{eventType}</a>'},
            { header: 'Attempts', width: 100, dataIndex: 'handleAttempts', sortable: false},
            { header: 'Status', maxWidth: 90, dataIndex: 'status', sortable: false, xtype: 'statuscolumn', statustype: 'webhookhistory', resizable: false},
            {
                header: 'Last response code',
                flex: .9,
                maxWidth: 166,
                dataIndex: 'responseCode',
                sortable: false,
                xtype: 'templatecolumn',
                tpl:
                    '<tpl if="handleAttempts&gt;0">' +
                        '<tpl if="status!=1">' +
                            '<div style="float:right"  class="x-grid-icon x-grid-icon-error x-grid-icon-simple" data-qtip="Attempt #{handleAttempts} failed. Cause: <b>{[values.errorMsg?Ext.util.Format.htmlEncode(Ext.util.Format.htmlEncode(values.errorMsg)):\'unknown\']}</b>"></div>' +
                        '</tpl>' +
                        '{[values.responseCode||\'None\']} ' +
                    '</tpl>'
            }
        ],

        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
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
            }, {
                xtype: 'tbfill',
                flex: .1
            },{
                xtype: 'tbfill',
                flex: .1
            },{
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function() {
                    store.clearAndLoad();
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
            beforedestroy: function() {
                this.abortCurrentRequest();
            },
            afterloadrecord: function(record) {
                var me = this;
                me.abortCurrentRequest();
                if (!record.get('payload')) {
                    me.up().mask('');
                    me.hide();
                    me.currentRequest = Scalr.Request({
                        url: '/webhooks/history/xGetInfo',
                        params: {historyId: record.get('historyId')},
                        success: function (data) {
                            delete me.currentRequest;
                            if (data['info']['historyId'] == record.get('historyId')) {
                                record.set(data['info']);
                                me.afterLoadRecord(record);
                            }
                        }
                    });
                } else {
                    me.afterLoadRecord(record);
                }

            }
		},
        afterLoadRecord: function(record) {
            var frm = this.getForm();
            frm.findField('payload').setValue(record.get('payload'));
            frm.findField('endpoint').setValue({endpointId: record.get('endpointId'), url: record.get('url')})
            this.up().unmask();
            this.show();
        },
        abortCurrentRequest: function() {
            if (this.currentRequest) {
                Ext.Ajax.abort(this.currentRequest);
                delete this.currentRequest;
            }
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
                name: 'endpoint',
                fieldLabel: 'Endpoint',
                renderer: function(value, comp) {
                    return Ext.isObject(value) ? '<a href="#/'+(Scalr.scope=='account'?'account/':'')+'webhooks/endpoints?endpointId='+value.endpointId+'">'+value.url+'</a>' : '&ndash';
                }
            },{
                xtype: 'displayfield',
                name: 'eventId',
                fieldLabel: 'Event ID',
                renderer: function(value, comp) {
                    return '<a href="#/logs/events?eventId='+value+'">'+value+'</a>';
                }
            },{
                xtype: 'displayfield',
                name: 'serverId',
                fieldLabel: 'Server ID',
                renderer: function(value, comp) {
                    return value ? '<a href="#/servers?serverId='+value+'">'+value+'</a>' : '&ndash;';
                }
            },{
                xtype: 'textarea',
                name: 'payload',
                readOnly: true,
                flex: 1,
                fieldLabel: 'Payload',
                labelAlign: 'top'
            }]
        }]
	});

	var panel = Ext.create('Ext.panel.Panel', {
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			maximize: 'all',
            menuTitle: 'Webhooks',
            menuSubTitle: 'History',
            menuHref: '#'+Scalr.utils.getUrlPrefix()+'/webhooks/endpoints',
            menuFavorite: Ext.Array.contains(['account', 'environment'], Scalr.scope),
            menuParentStateId: 'grid-webhooks-configs',
			leftMenu: {
				menuId: 'webhooks',
				itemId: 'history'
			}
		},
        items: [grid, {
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

Scalr.regPage('Scalr.ui.logs.api', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'id','transaction_id','dtadded','action','ipaddress','request' ],
		proxy: {
			type: 'scalr.paging',
			url: '/logs/xListApiLogs/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Logs &raquo; API Log',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-logs-api-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'API Log',
				href: '#/logs/api'
			}
		}],

		viewConfig: {
			emptyText: 'Nothing found',
			loadingText: 'Loading...'
		},

		columns: [
			{ header: 'Transaction ID', flex: 1, dataIndex: 'transaction_id', sortable: false },
			{ header: 'Time', flex: 1, dataIndex: 'dtadded', sortable: true },
			{ header: 'Action', flex: 1, dataIndex: 'action', sortable: true },
			{ header: 'IP address', flex: 1, dataIndex: 'ipaddress', sortable: true },
			{
				xtype: 'optionscolumn2',
				menu: [
					{ text:'Details', href: "#/logs/apiLogEntryDetails?transactionId={transaction_id}" }
				]
			}
		],

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			items: [{
				xtype: 'filterfield',
				store: store,
                width: 300,
                form: {
                    items: [{
                        xtype: 'datefield',
                        fieldLabel: 'By date',
                        labelAlign: 'top',
                        name: 'byDate',
                        format: 'Y-m-d',
                        maxValue: new Date(),
                        listeners: {
                            change: function (field, value) {
                                this.next().down('[name="fromTime"]')[ value ? 'enable' : 'disable' ]();
                                this.next().down('[name="toTime"]')[ value ? 'enable' : 'disable' ]();
                            }
                        }
                    }, {
                        xtype: 'fieldcontainer',
                        layout: 'hbox',
                        fieldLabel: 'Period of time',
                        labelAlign: 'top',
                        items: [{
                            xtype: 'timefield',
                            flex: 1,
                            name: 'fromTime',
                            format: 'H:i',
                            disabled: true,
                            listeners: {
                                change: function(field, value) {
                                    this.next().setMinValue(value);
                                }
                            }
                        }, {
                            xtype: 'timefield',
                            flex: 1,
                            margin: '0 0 0 10',
                            name: 'toTime',
                            format: 'H:i',
                            disabled: true
                        }]
                    }]
                }
			}]
		}]
	});
});

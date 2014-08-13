Scalr.regPage('Scalr.ui.logs.system', function (loadParams, moduleParams) {
	Ext.applyIf(moduleParams['params'], loadParams);
	var store = Ext.create('store.store', {
		fields: [ 'serverid','message','severity','time','source','farmid','servername','farm_name', 's_severity', 'cnt' ],
		proxy: {
			type: 'scalr.paging',
			extraParams: moduleParams['params'],
			url: '/logs/xListLogs/'
		},
		remoteSort: true
	});

	var filterSeverity = function (combo, checked) {
		store.proxy.extraParams['severity[' + combo.severityLevel + ']'] = checked ? 1 : 0;
		store.load();
	};

	var panel = Ext.create('Ext.grid.Panel', {
		title: 'Logs &raquo; System',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-logs-system-view',
		stateful: true,
		plugins: [{
			ptype: 'gridstore',
            highlightNew: true
		}, {
			ptype: 'rowexpander',
			rowBodyTpl: [
				'<p><b>Caller:</b> <a href="#/servers/{servername}/view">{servername}</a>/{source}</p>',
				'<p><b>Message:</b> {message}</p>'
			]
		}],

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'System log',
				href: '#/logs/system'
			}
		}],

		viewConfig: {
			emptyText: 'No logs found',
			loadingText: 'Loading logs ...',
			disableSelection: true,
			getRowClass: function (record) {
                if (record.get('severity') > 3) {
                    return 'x-grid-row-red';
                }
			}
		},

		columns: [
			{ header: '', width: 40, dataIndex: 'severity', sortable: false, resizable: false, hideable: false, align:'center', xtype: 'templatecolumn', tpl:
				'<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-severity x-icon-severity-{severity}" />'
			},
			{ header: 'Time', width: 156, dataIndex: 'time', sortable: true },
			{ header: 'Farm', width: 120, dataIndex: 'farm_name', itemId: 'farm_name', sortable: false, xtype: 'templatecolumn', tpl:
				'<a href="#/farms/{farmid}/view">{farm_name}</a>'
			},
			{ header: 'Caller', flex: 1, dataIndex: 'source', sortable: false, xtype: 'templatecolumn', tpl:
				'<a href="#/servers/{servername}/view">{servername}</a>/{source}'
			},
			{ header: 'Message', flex: 2, dataIndex: 'message', sortable: false, xtype: 'templatecolumn', tpl:
				'{[values.message.replace(/<br.*?>/g, "")]}' },
            { header: 'Count', width: 80, dataIndex: 'cnt', sortable: false, align: 'center' }
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
			}, ' ', {
				xtype: 'combo',
				fieldLabel: 'Farm',
				labelWidth: 34,
				width: 250,
				matchFieldWidth: false,
				listConfig: {
					minWidth: 150
				},
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams['farms'],
					proxy: 'object'
				},
				editable: false,
				queryMode: 'local',
				itemId: 'farmId',
				value: loadParams['farmId'] || 0,
				valueField: 'id',
				displayField: 'name',
				listeners: {
					select: function () {
						if (this.getValue() != 0)
							panel.headerCt.items.getAt(3).hide();
						else
							panel.headerCt.items.getAt(3).show();

						panel.store.proxy.extraParams['farmId'] = this.getValue();
						panel.store.loadPage(1);
					}
				}
            }, ' ', {
				text: 'Severity',
				width: 100,
				menu: {
					items: [{
						text: 'Fatal error',
						checked: true,
						severityLevel: 5,
						listeners: {
							checkchange: filterSeverity
						}
					}, {
						text: 'Error',
						checked: true,
						severityLevel: 4,
						listeners: {
							checkchange: filterSeverity
						}
					}, {
						text: 'Warning',
						checked: true,
						severityLevel: 3,
						listeners: {
							checkchange: filterSeverity
						}
					}, {
						text: 'Information',
						checked: true,
						severityLevel: 2,
						listeners: {
							checkchange: filterSeverity
						}
					}, {
						text: 'Debug',
						checked: false,
						severityLevel: 1,
						listeners: {
							checkchange: filterSeverity
						}
					}]
				}
			}, ' ', {
				text: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-download" />&nbsp;Download Log',
				width: 160,
				handler: function () {
					var params = Scalr.utils.CloneObject(store.proxy.extraParams);
					params['action'] = 'download';
					Scalr.utils.UserLoadFile('/logs/xListLogs?' + Ext.urlEncode(params));
				}
			}]
		}]
	});

	return panel;
});

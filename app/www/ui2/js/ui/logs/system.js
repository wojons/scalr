Scalr.regPage('Scalr.ui.logs.system', function (loadParams, moduleParams) {

	var store = Ext.create('store.store', {
		fields: [ 'serverid','message','severity','time','source','farmid','servername','farm_name', 's_severity', 'cnt' ],
		proxy: {
			type: 'scalr.paging',
			url: '/logs/xListLogs/'
		},
		remoteSort: true,
        sorters: {
            property: 'time',
            direction: 'DESC'
        }
    });

	var panel = Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
            menuTitle: 'System Log',
            menuHref: '#/logs/system',
            menuFavorite: true,
			maximize: 'all'
		},
		store: store,
		stateId: 'grid-logs-system-view',
		stateful: true,
		plugins: [{
			ptype: 'gridstore',
            highlightNew: true
		}, {
            ptype: 'applyparams',
            hiddenParams: ['severity']
        }, {
			ptype: 'rowexpander',
			rowBodyTpl: [
				'<p><b>Caller:</b> <a href="#/servers?serverId={servername}">{servername}</a>/{source}</p>',
				'<p><b>Message:</b> {message}</p>'
			]
        }],

        disableSelection: true,
		viewConfig: {
			emptyText: 'Nothing found',
			loadingText: 'Loading...',
			getRowClass: function (record) {
                if (record.get('severity') > 3) {
                    return 'x-grid-row-color-red';
                }
			}
		},

		columns: [
			{ header: 'Type', width: 50, dataIndex: 'severity', sortable: false, resizable: false, hideable: false, align:'center', xtype: 'templatecolumn', tpl: new Ext.XTemplate(
                '<div class="x-grid-icon x-grid-icon-simple x-grid-icon-{[this.getName(values.severity)]}"></div>', {
                    getName: function(severity) {
                        return { 1: 'bug', 2: 'info', 3: 'warning', 4: 'error', 5: 'fatalerror'}[severity];
                    }
                })
			},
			{ header: 'Time', width: 180, dataIndex: 'time', sortable: true },
			{ header: 'Farm', flex: 1, dataIndex: 'farm_name', name: 'farmName', itemId: 'farm_name', sortable: false, xtype: 'templatecolumn', tpl:
				'<a href="#/farms?farmId={farmid}">{farm_name}</a>'
			},
			{ header: 'Caller', flex: 1, dataIndex: 'source', sortable: false, xtype: 'templatecolumn', tpl:
				'<a href="#/servers?serverId={servername}">{servername}</a>/{source}'
			},
			{ header: 'Message', flex: 3, dataIndex: 'message', sortable: false, xtype: 'templatecolumn', tpl:
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
				name: 'farmId',
				value: 0,
				valueField: 'id',
				displayField: 'name',
				listeners: {
					select: function (me) {
						var value = me.getValue();

						panel.down('[name=farmName]').setVisible(value === 0);

						store.applyProxyParams({
							farmId: value
						});
					}
				}
            }, ' ', {
                xtype: 'buttonfield',
				text: 'Severity',
                name: 'severity',
                arrowCls: 'split',
                getValue: function () {
                    var me = this;

                    var value = [];

                    Ext.Array.each(me.getMenu().query(), function (menuItem) {
                        if (menuItem.checked) {
                            value.push(menuItem.severityLevel);
                        }
                    });

                    return value.join(',');
                },
                menu: {
                    defaults: {
                        checked: true,
                        listeners: {
                            checkchange: function (menuItem) {
                                store.applyProxyParams({
                                    'severity': menuItem.up('buttonfield').getValue()
                                });
                            }
                        }
                    },
					items: [{
						text: 'Fatal error',
						severityLevel: 5
					}, {
						text: 'Error',
						severityLevel: 4
					}, {
						text: 'Warning',
						severityLevel: 3
					}, {
						text: 'Information',
						severityLevel: 2
					}, {
						text: 'Debug',
						checked: false,
						severityLevel: 1
					}]
				}
			}, ' ', {
                cls: 'x-btn-with-icon-and-text',
                iconCls: 'x-btn-icon-download',
                text: 'Download log',
                width: 165,
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

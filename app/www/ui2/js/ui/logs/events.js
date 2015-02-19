Scalr.regPage('Scalr.ui.logs.events', function (loadParams, moduleParams) {
    var firedByTpl =
        '<tpl if="event_farm_id">' +
            '<a href="#/farms/{event_farm_id}/view" title="Farm {event_farm_name}">{event_farm_name}</a>' +
            '<tpl if="event_role_name">' +
                '&nbsp;&rarr;&nbsp;<a href="#/farms/{event_farm_id}/roles/{event_farm_roleid}/view" title="Role {event_role_name}">{event_role_name}</a> ' +
            '</tpl>' +
            '<tpl if="!event_role_name">' +
                '&nbsp;&rarr;&nbsp;*removed role*&nbsp;' +
            '</tpl>' +
            '#<a href="#/servers/{event_server_id}/view">{event_server_index}</a>'+
        '<tpl else>{event_server_id}</tpl>';

	var store = Ext.create('store.store', {
		fields: [
			'id','dtadded', 'type', 'message', 'event_id', 'scripts',
			
			'event_server_id', 
			'event_farm_name', 
			'event_farm_id', 
			'event_role_id', 
			'event_farm_roleid',
			'event_role_name',
			'event_server_index',
            'webhooks'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/logs/xListEventLogs'
		},
		remoteSort: true
	});

	var panel = Ext.create('Ext.grid.Panel', {
		title: 'Logs &raquo; Event Log',
		scalrOptions: {
            reload: false,
			maximize: 'all'
		},
		store: store,
		stateId: 'grid-farms-events-view',
		stateful: true,
		plugins: [{
			ptype: 'gridstore',
            highlightNew: true
        },{
			ptype: 'rowexpander',
            pluginId: 'rowexpander',
			rowBodyTpl: [
				'<tpl if="message">' +
                    '<table style="line-height:22px">'+
                    '<tr><td><b>Details:</b></td><td>{message}</td></tr>' +
                    '</table>'+
                '<tpl else>' +
                    '<p>No extended info available</p>' +
                '</tpl>'
			]
		}],
		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Event Log',
				href: '#/logs/events'
			}
		}],

		viewConfig: {
			emptyText: 'Nothing found',
			loadingText: 'Loading...',
        	disableSelection: true,
			getRowClass: function (record, rowIndex, rowParams) {
                var errorsCount = record.get('scripts')['failed'] + record.get('webhooks')['failed'];
                if (errorsCount > 0) {
                    return 'x-grid-row-red';
                }
			}
        },

		columns: [
			{ header: "Date", width: 160, dataIndex: 'dtadded', sortable: false },
			{ header: "Event", width: 200, dataIndex: 'type', sortable: false },
			{ header: 'Fired by', flex: 1, dataIndex: 'event_server_id', sortable: false, xtype: 'templatecolumn', tpl: firedByTpl},
			{ header: "Details", flex: 3, dataIndex: 'message', sortable: false },
			{ header: "Orchestration rules", width: 150, dataIndex: 'scripts', sortable: false, xtype: 'templatecolumn',
				tpl: new Ext.XTemplate(
                    '<tpl if="scripts.total &gt; 0">'+
                        '<span data-anchor="right" data-qalign="r-l" data-qtip="{[this.getTooltipHtml(values)]}" data-qwidth="270">' +
                            '<span style="color:#28AE1E;">{[values.scripts.complete]}</span>' +
                            '/<span style="color:#bbb;">{[values.scripts.pending]}</span>' +
                            '/<span style="color:orange;">{[values.scripts.timeout]}</span>' +
                            '/<span style="color:red;">{[values.scripts.failed]}</span>' +
                        '</span>'+
                        '&nbsp;[<a href="#/logs/scripting?eventId={event_id}">View</a>]'+
                    '<tpl else>'+
                        '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-minus" />'+
                    '</tpl>',
                    {
                        getTooltipHtml: function(values) {
                            return Ext.String.htmlEncode(
                                '<b>Orchestration rules execution results</b><br/>' +
                                '<table>'+
                                '<tr><td>Completed:&nbsp;</td><td><span style="color:#00CC00;">' + values.scripts.complete + '</span></td></tr>' +
                                '<tr><td>Pending:&nbsp;</td><td><span style="color:#bbb;">' + values.scripts.pending + '</span></td></tr>' +
                                '<tr><td>Timed out:&nbsp;</td><td><span style="color:orange;">' + values.scripts.timeout + '</span></td></tr>' +
                                '<tr><td>Failed:&nbsp;</td><td><span style="color:red;">' + values.scripts.failed + '</span></td></tr>'+
                                '</table>'
                            );
                        }
                    }
                )
			},
			{ header: "Webhooks", width: 150, dataIndex: 'webhooks', sortable: false, xtype: 'templatecolumn',
				tpl: new Ext.XTemplate(
                    '<tpl if="webhooks.total &gt; 0">'+
                        '<span data-anchor="right" data-qalign="r-l" data-qtip="{[this.getTooltipHtml(values)]}" data-qwidth="160">' +
                            '<span style="color:#28AE1E;">{[values.webhooks.complete]}</span>' +
                            '/<span style="color:#bbb;">{[values.webhooks.pending]}</span>' +
                            '/<span style="color:red;">{[values.webhooks.failed]}</span>' +
                        '</span>'+
                        '&nbsp;[<a href="#/webhooks/history?eventId={event_id}">View</a>]'+
                    '<tpl else>'+
                        '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-minus" />'+
                    '</tpl>',
                    {
                        getTooltipHtml: function(values) {
                            return Ext.String.htmlEncode(
                                '<b>Webhooks statuses</b><br/>' +
                                '<table>'+
                                '<tr><td>Completed:&nbsp;</td><td><span style="color:#00CC00;">' + values.webhooks.complete + '</span></td></tr>' +
                                '<tr><td>Pending:&nbsp;</td><td><span style="color:#bbb;">' + values.webhooks.pending + '</span></td></tr>' +
                                '<tr><td>Failed:&nbsp;</td><td><span style="color:red;">' + values.webhooks.failed + '</span></td></tr>'+
                                '</table>'
                            );
                        }
                    }
                )
			},
		],

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
            //ignoredLoadParams: ['farmId', 'eventServerId', 'eventId'],
			store: store,
			dock: 'top',
			items: [{
				xtype: 'filterfield',
				store: store
			}, ' ', {
				xtype: 'combo',
				fieldLabel: 'Farm',
				labelWidth: 34,
				width: 250,
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
				iconCls: 'no-icon',
				listeners: {
					change: function () {
						panel.store.proxy.extraParams['farmId'] = this.getValue();
						panel.store.loadPage(1);
					}
				}
            }/*, ' ', {
				xtype: 'button',
				text: 'Configure event notifications',
				handler: function () {
					document.location.href = '#/farms/events/configure?farmId=' + store.proxy.extraParams.farmId;
				}
			}*/]
		}]
	});

    return panel;
});

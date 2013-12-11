Scalr.regPage('Scalr.ui.logs.scripting', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'id', 'event', 'dtadded', 'message', 'script_name', 'exec_time', 'exec_exitcode', 'event_id',
			'target_server_id', 
			'target_farm_name', 
			'target_farm_id', 
			'target_role_id', 
			'target_farm_roleid',			 
			'target_server_index',
			'target_role_name',
			 
			'event_server_id', 
			'event_farm_name', 
			'event_farm_id', 
			'event_role_id', 
			'event_farm_roleid',
			'event_role_name',
			'event_server_index',
            'execution_id'
		],
		proxy: {
			type: 'scalr.paging',
			extraParams: loadParams,
			url: '/logs/xListScriptingLogs/'
		},
		remoteSort: true
	});

	var panel = Ext.create('Ext.grid.Panel', {
		title: 'Logs &raquo; Scripting',
		scalrOptions: {
			reload: false,
			maximize: 'all'
		},
		scalrReconfigureParams: { eventId: '', serverId: '' },
		store: store,
		stateId: 'grid-scripting-view',
		stateful: true,
		plugins: [{
			ptype: 'gridstore',
            highlightNew: true
		}, {
			ptype: 'rowexpander',
            pluginId: 'rowexpander',
			rowBodyTpl: [
				'<tpl if="message"><p><b>Message:</b> {message}</p><tpl else><p>Loading...</p></tpl>'
			]
		}],

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Scripting Log',
				href: '#/logs/scripting'
			}
		}],
		viewConfig: {
			emptyText: 'No logs found',
			loadingText: 'Loading logs ...',
			disableSelection: true,
			getRowClass: function (record, rowIndex, rowParams) {
                if (record.get('exec_exitcode') != '0') {
                    return 'x-grid-row-red';
                }
			},
            listeners: {
                beforerefresh: function(){//since we load message dynamically, we must to collapse all expanded rows before refresh
                    var key,
                        recordsExpanded = this.up().getPlugin('rowexpander').recordsExpanded;
                    if (recordsExpanded) {
                        for (key in recordsExpanded) {
                            if (recordsExpanded.hasOwnProperty(key)) {
                                delete recordsExpanded[key];
                            }
                        }
                    }
                },
                expandbody: function(rowNode, record, expandRow, eOpts){
                    if(true || record.get('execution_id')) {
                        if (!record.get('message')) {
                            Scalr.Request({
                                url: '/logs/getScriptingLog/',
                                params: {
                                    executionId: record.get('execution_id')
                                },
                                success: function (data) {
                                    var node = Ext.fly(rowNode).down('.x-grid-rowbody');
                                    if (node) {
                                        node.setHTML('<p><b>Message:</b> ' + data.message + '</p>');
                                        record.set('message', data.message);
                                    }
                                },
                                scope: this
                            });
                        }
                    }
                }
            }
		},

		columns: [
			{ header: 'Date', width: 160, dataIndex: 'dtadded', sortable: true },
			{ header: 'Event', width: 200, dataIndex: 'event', sortable: false, xtype: 'templatecolumn', tpl: 
				'<tpl if="!event_id || !event_farm_id">'+
				'{event}'+
				'<tpl else><a href="#/farms/{event_farm_id}/events?eventId={event_id}">{event}</a></tpl>'
			},
			{ header: 'Fired by', flex: 1, dataIndex: 'event_server_id', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="event_farm_id">' +
					'<a href="#/farms/{event_farm_id}/view" title="Farm {event_farm_name}">{event_farm_name}</a>' +
					'<tpl if="event_role_name">' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{event_farm_id}/roles/{event_farm_roleid}/view" title="Role {event_role_name}">{event_role_name}</a> ' +
					'</tpl>' +
					'<tpl if="!event_role_name">' +
						'&nbsp;&rarr;&nbsp;*removed role*&nbsp;' +
					'</tpl>' +
					'#<a href="#/servers/{event_server_id}/view">{event_server_index}</a>'+
				'<tpl else><img src="/ui2/images/icons/false.png"></tpl>'
			},
			{ header: 'Executed on', flex: 2, dataIndex: 'server_id', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="target_farm_id">' +
					'<a href="#/farms/{target_farm_id}/view" title="Farm {target_farm_name}">{target_farm_name}</a>' +
					'<tpl if="target_role_name">' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{target_farm_id}/roles/{target_farm_roleid}/view" title="Role {target_role_name}">{target_role_name}</a> ' +
					'</tpl>' +
					'<tpl if="!target_role_name">' +
						'&nbsp;&rarr;&nbsp;*removed role*&nbsp;' +
					'</tpl>' +
					'#<a href="#/servers/{target_server_id}/view">{target_server_index}</a>'+
				'</tpl>'
			},
			{ header: 'Script name', width: 200, dataIndex: 'script_name', sortable: false },
			{ header: 'Execution time', width: 130, dataIndex: 'exec_time', sortable: false, xtype: 'templatecolumn', tpl: '{exec_time} s'},
			{ header: 'Exit code', width: 100, dataIndex: 'exec_exitcode', sortable: false }
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
                        xtype: 'textfield',
                        fieldLabel: 'ServerID',
                        labelAlign: 'top',
                        name: 'serverId'
                    }, {
                        xtype: 'textfield',
                        fieldLabel: 'EventID',
                        labelAlign: 'top',
                        name: 'eventId'
                    }, {
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
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams['farms'],
					proxy: 'object'
				},
				editable: false,
				queryMode: 'local',
				itemId: 'farmId',
				value: loadParams['farmId'] || '0',
				valueField: 'id',
				displayField: 'name',
				iconCls: 'no-icon',
				listeners: {
					change: function () {
						panel.store.proxy.extraParams['farmId'] = this.getValue();
						panel.store.loadPage(1);
					}
				}
			}]
		}]
	});

	return panel;
});

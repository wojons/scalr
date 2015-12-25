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
			url: '/logs/xListScriptingLogs/'
		},
        sorters: {
            property: 'id',
            direction: 'DESC'
        },
        remoteSort: true
	});

	var panel = Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'Scripting Log',
            menuHref: '#/logs/scripting',
            menuFavorite: true
		},
		store: store,
		stateId: 'grid-logs-scripting-view',
		stateful: true,
		plugins: [{
			ptype: 'gridstore',
            highlightNew: true
		}, {
            ptype: 'applyparams'
        }, {
			ptype: 'rowexpander',
            pluginId: 'rowexpander',
			rowBodyTpl: [
				'<tpl if="message">' +
                    '<p><b>Message:</b><br/><br/>{[values.message.replace(\'STDERR:\',\'<b>STDERR:</b>\').replace(\'STDOUT:\',\'<b>STDOUT:</b>\')]}</p>' +
                '<tpl elseif="execution_id">' +
                    '<p>Loading...</p>' +
                '<tpl else>' +
                    '<p>No extended info available</p>' +
                '</tpl>'
			]
		}],

        disableSelection: true,
		viewConfig: {
			emptyText: 'Nothing found',
			loadingText: 'Loading...',
			getRowClass: function (record) {
                var exitCode = record.get('exec_exitcode');
                if (exitCode == '130') {
                    return 'x-grid-row-color-orange';
                } else if (exitCode != '0') {
                    return 'x-grid-row-color-red';
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
                    if(record.get('execution_id')) {
                        if (!record.get('message')) {
                            Scalr.Request({
                                hideErrorMessage: true,
                                url: '/logs/getScriptingLog/',
                                params: {
                                    executionId: record.get('execution_id')
                                },
                                success: function (data) {
                                    if (!Ext.isEmpty(data) && !Ext.isEmpty(data.message)) {
                                        var node = Ext.fly(rowNode).down('.x-grid-rowbody');
                                        if (node) {
                                            node.setHtml('<p><b>Message:</b><br/><br/>' + (data.message+'').replace('STDERR:','<b>STDERR:</b>').replace('STDOUT:','<b>STDOUT:</b>') + '</p>');
                                            record.set('message', data.message);
                                        }
                                    }
                                },
                                failure: function(data) {
                                    if (!Ext.isEmpty(data) && !Ext.isEmpty(data.errorMessage)) {
                                        var node = Ext.fly(rowNode).down('.x-grid-rowbody');
                                        if (node) {
                                            node.setHtml('<p>' + (data.errorMessage || '') + '</p>');
                                        }
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
			{ header: 'Date', width: 175, dataIndex: 'dtadded', sortable: true },
			{ header: 'Event', width: 200, dataIndex: 'event', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="!event_id || !event_farm_id">'+
				'{event}'+
				'<tpl else><a href="#/logs/events?eventId={event_id}">{event}</a></tpl>'
			},
			{ header: 'Fired by', flex: 1, dataIndex: 'event_server_id', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="event_farm_id">' +
					'<tpl if="event_role_name">' +
						'<a href="#/farms?farmId={event_farm_id}" title="Farm {event_farm_name}">{event_farm_name}</a>' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{event_farm_id}/roles/{event_farm_roleid}/view" title="Role {event_role_name}">{event_role_name}</a> ' +
						'&nbsp;#<a href="#/servers?serverId={event_server_id}">{event_server_index}</a>'+
					'</tpl>' +
					'<tpl if="!event_role_name">' +
						'{event_server_id}' +
					'</tpl>' +
				'<tpl else>{event_server_id}</tpl>'
			},
			{ header: 'Executed on', flex: 2, dataIndex: 'server_id', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="target_farm_id">' +
					'<tpl if="target_role_name">' +
						'<a href="#/farms?farmId={target_farm_id}" title="Farm {target_farm_name}">{target_farm_name}</a>' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{target_farm_id}/roles/{target_farm_roleid}/view" title="Role {target_role_name}">{target_role_name}</a> ' +
						'&nbsp;#<a href="#/servers?serverId={target_server_id}">{target_server_index}</a>'+
					'</tpl>' +
					'<tpl if="!target_role_name">' +
						'{target_server_id}' +
					'</tpl>' +
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
            defaults: {
                margin: '0 0 0 15'
            },
			items: [{
				xtype: 'filterfield',
				store: store,
                width: 300,
                margin: 0,
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
                        xtype: 'textfield',
                        fieldLabel: 'EventServerID',
                        labelAlign: 'top',
                        name: 'eventServerId'
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
                    }, {
                        xtype: 'combo',
                        store: {
                            fields: [ 'id', 'name' ],
                            data: moduleParams['scripts'],
                            proxy: 'object'
                        },
                        valueField: 'id',
                        displayField: 'name',
                        name: 'scriptId',
                        editable: false,
                        forceSelection: true,
                        fieldLabel: 'Script',
                        labelAlign: 'top'
                    }, {
                        xtype: 'combo',
                        store: moduleParams['events'],
                        name: 'event',
                        editable: false,
                        forceSelection: true,
                        fieldLabel: 'Event',
                        labelAlign: 'top'
                    }, {
                        xtype: 'combo',
                        store: {
                            fields: [ 'id', 'name' ],
                            data: moduleParams['tasks'],
                            proxy: 'object'
                        },
                        valueField: 'id',
                        displayField: 'name',
                        name: 'schedulerId',
                        editable: false,
                        forceSelection: true,
                        fieldLabel: 'Scheduler task',
                        labelAlign: 'top',
                        listeners: {
                            change: function (field, value) {
                                if (value) {
                                    this.prev().reset();
                                    this.prev().disable();
                                } else {
                                    this.prev().enable();
                                }
                            }
                        }
                    }]
                }
			}, {
				xtype: 'combo',
				fieldLabel: 'Farm',
                name: 'farmId',
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
				value: '0',
				valueField: 'id',
				displayField: 'name',
				iconCls: 'no-icon',
				listeners: {
					change: function () {
						panel.store.proxy.extraParams['farmId'] = this.getValue();
						panel.store.loadPage(1);
					}
				}
			}, {
                xtype: 'cyclealt',
                name: 'status',
                cls: 'x-btn-compressed',
                fieldLabel: 'Result',
                labelWidth: 45,
                getItemIconCls: false,
                width: 200,
                changeHandler: function (comp, item) {
                    store.applyProxyParams({
                        status: item.value
                    });
                },
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: [{
                        text: 'All logs',
                        value: ''
                    },{
                        text: 'Success logs',
                        value: 'success'
                    },{
                        text: 'Failure logs',
                        value: 'failure'
                    }]
                }
            }]
		}]
	});

	return panel;
});

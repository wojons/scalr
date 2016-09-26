Scalr.regPage('Scalr.ui.logs.orchestration', function (loadParams, moduleParams) {

    var store = Ext.create('Scalr.ui.ContinuousStore', {
        fields: [
            'id',
            'type',
            'event',
            'added',
            'message',
            'scriptName',
            'execTime',
            'execExitCode',
            'eventId',
            'targetServerId',
            'targetFarmName',
            'targetFarmId',
            'targetRoleId',
            'targetFarmRoleId',
            'targetServerIndex',
            'targetRoleName',
            'eventServerId',
            'eventFarmName',
            'eventFarmId',
            'eventRoleId',
            'eventFarmRoleId',
            'eventRoleName',
            'eventServerIndex',
            'executionId'
        ],
        proxy: {
            type: 'ajax',
            url: '/logs/xListOrchestrationLogs',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },
        sorters: {
            property: 'id',
            direction: 'DESC'
        },
        remoteSort: true
    });

    var panel = Ext.create('Ext.grid.Panel', {
        cls: 'x-panel-column-left',

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Orchestration Log',
            menuHref: '#/logs/orchestration',
            menuFavorite: true
        },

        store: store,

        stateId: 'grid-logs-orchestration-view',
        stateful: true,

        plugins: ['applyparams', {
            ptype: 'continuousrenderer',
            highlightNew: true
        }, {
            ptype: 'rowexpander',
            pluginId: 'rowexpander',
            rowBodyTpl: [
                '<tpl if="message">',
                    '<p><b>Message:</b><br/><br/>{[values.message.replace(\'STDERR:\',\'<b>STDERR:</b>\').replace(\'STDOUT:\',\'<b>STDOUT:</b>\')]}</p>',
                '<tpl elseif="executionId">',
                    '<p>Loading...</p>',
                '<tpl else>',
                    '<p>No extended info available.</p>',
                '</tpl>'
            ]
        }],

        disableSelection: true,

        viewConfig: {
            emptyText: 'Orchestration log is empty.',
            getRowClass: function (record) {
                var exitCode = record.get('execExitCode');

                if (exitCode === 130) {
                    return 'x-grid-row-color-orange';
                }

                return exitCode !== 0 ? 'x-grid-row-color-red' : null;
            },
            listeners: {
                beforerefresh: function () {//since we load message dynamically, we must to collapse all expanded rows before refresh
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
                expandbody: function (rowNode, record, expandRow, eOpts) {
                    if (record.get('executionId') && !record.get('message')) {
                        Scalr.Request({
                            hideErrorMessage: true,
                            url: '/logs/getOrchestrationLog/',
                            params: {
                                executionId: record.get('executionId')
                            },
                            success: function (data) {
                                if (!Ext.isEmpty(data) && !Ext.isEmpty(data.message)) {
                                    var node = Ext.fly(rowNode).down('.x-grid-rowbody');
                                    if (node) {
                                        node.setHtml('<p><b>Message:</b><br/><br/>' + (data.message + '').replace('STDERR:', '<b>STDERR:</b>').replace('STDOUT:', '<b>STDOUT:</b>') + '</p>');
                                        record.set('message', data.message);
                                    }
                                }
                            },
                            failure: function (data) {
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
        },

        columns: [{
            header: 'Date',
            width: 175,
            dataIndex: 'added',
            sortable: true
        }, {
            header: 'Event type',
            flex: 0.5,
            minWidth: 200,
            dataIndex: 'event',
            sortable: false,
            xtype: 'templatecolumn',
            tpl: [
                '<img class="x-grid-icon x-grid-icon-{type}" src="{[Ext.BLANK_IMAGE_URL]}" style="cursor: default; margin-right: 6px;" />',
                '<tpl if="Ext.isEmpty(values.eventId) || Ext.isEmpty(values.eventFarmId)">',
                    '{event}',
                '<tpl else>',
                    '<a href="#/logs/events?eventId={eventId}">{event}</a>',
                '</tpl>'
            ]
        }, {
            header: 'Fired by',
            flex: 1,
            dataIndex: 'eventServerId',
            sortable: false,
            xtype: 'templatecolumn',
            tpl: [
                '<tpl if="eventFarmId">',
                    '<tpl if="eventRoleName">',
                        '<a href="#/farms?farmId={eventFarmId}" title="Farm {eventFarmName}">{eventFarmName}</a>',
                        '&nbsp;&rarr;&nbsp;<a href="#/farms/{eventFarmId}/roles/{eventFarmRoleId}/view" title="Role {eventRoleName}">{eventRoleName}</a> ',
                        '&nbsp;#<a href="#/servers?serverId={eventServerId}">{eventServerIndex}</a>',
                    '</tpl>',
                    '<tpl if="!eventRoleName">',
                        '{eventServerId}',
                    '</tpl>',
                '<tpl else>',
                    '{eventServerId}',
                '</tpl>'
            ]
        }, {
            header: 'Executed on',
            flex: 1,
            dataIndex: 'serverId',
            sortable: false,
            xtype: 'templatecolumn',
            tpl: [
                '<tpl if="targetFarmId">',
                    '<tpl if="!Ext.isEmpty(values.targetRoleName)">',
                        '<a href="#/farms?farmId={targetFarmId}" title="Farm {targetFarmName}">{targetFarmName}</a>',
                        '&nbsp;&rarr;&nbsp;<a href="#/farms/{targetFarmId}/roles/{targetFarmRoleId}/view" title="Role {targetRoleName}">{targetRoleName}</a> ',
                        '&nbsp;#<a href="#/servers?serverId={targetServerId}">{targetServerIndex}</a>',
                    '</tpl>',
                    '<tpl if="Ext.isEmpty(values.targetRoleName)">',
                        '{targetServerId}',
                    '</tpl>',
                '</tpl>'
            ]
        }, {
            header: 'Script name',
            flex: 0.5,
            minWidth: 200,
            dataIndex: 'scriptName',
            sortable: false
        }, {
            header: 'Execution time',
            width: 130,
            dataIndex: 'execTime',
            sortable: false,
            xtype: 'templatecolumn',
            tpl: [
                '{execTime} sec',
                '<tpl if="values.execExitCode!==0">',
                    '<img src="{[Ext.BLANK_IMAGE_URL]}" data-qtip="EXIT CODE: {execExitCode}" class="x-grid-icon x-grid-icon-error" style="margin-left: 6px; cursor: default;" />',
                '</tpl>'
            ]
        }],

        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 10'
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
                margin: '0 0 0 15',
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
                    change: function (field, value) {
                        store.applyProxyParams({
                            farmId: value
                        });
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
            }, {
                xtype: 'tbfill',
                flex: 1
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.clearAndLoad();
                }
            }]
        }]
    });

    return panel;
});

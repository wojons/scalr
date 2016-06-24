Scalr.regPage('Scalr.ui.bundletasks.view', function (loadParams, moduleParams) {

    var store = Ext.create('Scalr.ui.ContinuousStore', {
        fields: [{
                name: 'id',
                type: 'int'
            }, {
                name: 'clientid',
                type: 'int'
            },
            'server_id', 'prototype_role_id', 'replace_type',
            'status', 'platform', 'rolename', 'failure_reason',
            'bundle_type', 'dtadded', 'dtstarted', 'dtfinished',
            'snapshot_id', 'platform_status', 'server_exists',
            'os_family', 'os_version', 'os_name',
            'created_by_email', 'role_id', 'duration'
        ],

        proxy: {
            type: 'ajax',
            url: '/bundletasks/xListTasks/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },
        sorters: [{
            property: 'id',
            direction: 'DESC'
        }]
    });

    var logPanel = Scalr.getPage('Scalr.ui.bundletasks.view.logs');

    var grid = Ext.create('Ext.grid.Panel', {

        cls: 'x-panel-column-left',
        flex: 1,

        store: store,

        plugins: [ 'applyparams', 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }, {
            ptype: 'continuousrenderer'
        }],

        viewConfig: {
            emptyText: 'No bundle tasks found'
        },

        columns: [
            { header: "Server ID", width: 200, dataIndex: 'server_id', sortable: false, xtype: 'templatecolumn', tpl: new Ext.XTemplate(
                '<tpl if="server_exists == 1"><a href="#/servers/{server_id}/dashboard" data-qtip="{server_id}">{[this.serverId(values.server_id)]}</a>',
                '<tpl else>&mdash;</tpl>',
                {
                    serverId: function(id) {
                        var values = id.split('-');
                        return values[0] + '-...-' + values[values.length - 1];
                    }
                }
            )},
            { header: "Name", flex: 1, dataIndex: 'rolename', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="rolename && role_id && status==\'success\'">' +
                    '<a href="#/roles?roleId={role_id}">{rolename}</a>' +
                '<tpl else>' +
                    '{rolename}' +
                '</tpl>'
            }, {
                text: 'Location',
                minWidth: 110,
                flex: 0.7,
                dataIndex: 'platform',
                sortable: false,
                renderer: function (value, meta, record) {
                    var platform = record.get('platform'), location = record.get('cloud_location');
                    return '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" data-qtip="' + Scalr.utils.getPlatformName(platform) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;<span style="line-height: 18px;">' + (location ? location : 'All locations') + '</span>';
                },
                multiSort: function (st, direction) {
                    st.sort([{
                        property: 'platform',
                        direction: direction
                    }, {
                        property: 'cloudLocation',
                        direction: direction
                    }]);
                }
            },
            { header: "OS", flex: 0.7, dataIndex: 'os_family', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="os_family">' +
                    '<img style="margin:0 3px 0 0" data-qtip="{[Scalr.utils.beautifyOsFamily(values.os_family)]} {os_version}" class="x-icon-osfamily-small x-icon-osfamily-small-{os_family}" src="' + Ext.BLANK_IMAGE_URL + '"/> {[Scalr.utils.beautifyOsFamily(values.os_family)]} {os_version}' +
                '<tpl else>' +
                    '&mdash;' +
                '</tpl>'
            },
            { header: "Started", width: 165, dataIndex: 'dtstarted', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="dtstarted">{dtstarted}<tpl else>&mdash;</tpl>'
            },
            { header: "Status", width: 140, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'bundletask' },
            {
                xtype: 'optionscolumn',
                menu: [{
                    iconCls: 'x-menu-icon-cancel',
                    text: 'Cancel',
                    showAsQuickAction: true,
                    getVisibility: function (data) {
                        return !Ext.Array.contains(
                            [ 'success', 'failed', 'cancelled' ],
                            data.status
                        );
                    },
                    request: {
                        confirmBox: {
                            msg: 'Cancel selected bundle task?',
                            type: 'action'
                        },
                        processBox: {
                            type: 'action',
                            msg: 'Canceling...'
                        },
                        url: '/bundletasks/xCancel/',
                        dataHandler: function (data) {
                            return {
                                bundleTaskId: data.id
                            };
                        },
                        success: function (data) {
                            store.clearAndLoad();
                        }
                    }
                }]
            }
        ],

        dockedItems: [{
            xtype: 'toolbar',
            store: store,
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                store: store,
                margin: 0
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

    var form = Ext.create('Ext.form.Panel', {

        hidden: true,
        autoScroll: true,

        layout: {
            type: 'vbox',
            align: 'stretch'
        },

        plugins: [{
            ptype: 'formrepeatabletask',
            pluginId: 'updateShortLog',
            args: [ 'id', 'status' ],
            stopIf: function (record) {
                return Ext.isEmpty(record) || Ext.Array.contains(
                    [ 'success', 'failed', 'cancelled' ],
                    record.get('status')
                );
            }
        }],

        fieldDefaults: {
            anchor: '100%'
        },

        shortLogRecordsCount: 10,

        shortLogRefreshTime: 15000,

        isBundleTaskLoaded: false,

        setHeader: function (headerText) {
            var me = this;

            me.down('fieldset')
                .setTitle(headerText);

            return me;
        },

        viewLog: function () {
            var me = this;

            logPanel.bundleTaskId = me.getRecord().get('id');

            Scalr.utils.Window(logPanel);

            return me;
        },

        clearShortLog: function () {
            var me = this;

            me.down('#log').getStore().removeAll();

            return me;
        },

        updateShortLog: function (bundleTaskId, status) {
            var me = this;

            var shortLogGrid = me.down('#log');
            shortLogGrid.setLoading('');

            var request = Scalr.Request({
                url: '/bundletasks/xListLogs/',
                headers: {
                    'Scalr-Autoload-Request': 1
                },
                params: {
                    bundleTaskId: bundleTaskId,
                    limit: me.shortLogRecordsCount,
                    taskInfo: me.isBundleTaskLoaded,
                    status: status,
                    sort: Ext.encode([{
                        property :"id",
                        direction:"DESC"
                    }])
                },
                success: function (response) {
                    var logData = response.data;

                    if (!Ext.isEmpty(logData)) {
                        me.applyShortLog(logData);
                    }

                    shortLogGrid.setLoading(false);

                    var taskData = response.task;

                    if (!Ext.isEmpty(taskData)) {
                        var record = me.getForm().getRecord();

                        if (!Ext.isEmpty(record)) {
                            record.set(taskData);
                            me.getPlugin('updateShortLog').restart(record);
                        }
                    }
                },
                failure: function () {
                    shortLogGrid.setLoading(false);
                }
            });

            me.isBundleTaskLoaded = true;

            return request;
        },

        applyShortLog: function (logData) {
            var me = this;

            me.down('#log').getStore()
                .loadData(logData);

            return me;
        },

        setDuration: function (duration, status) {
            var me = this;

            var values = {
                success: duration,
                failed: '&mdash;'
            };

            me.down('[name=duration]').setValue(
                Ext.isDefined(values[status]) ? values[status] : 'Ongoing'
            );

            return me;
        },

        setFailureReason: function (message) {
            var me = this;

            var isVisible = !Ext.isEmpty(message);

            me.down('#failureReason')
                .setVisible(isVisible)
                .setValue(message);

            me.down('#failureReasonLabel')
                .setVisible(isVisible);

            return me;
        },

        listeners: {
            afterloadrecord: function (record) {
                var me = this;

                me.isBundleTaskLoaded = false;

                var status = record.get('status');

                me
                    .clearShortLog()
                    .updateShortLog(
                        record.get('id'),
                        status
                    );

                me
                    .setFailureReason(
                        record.get('failure_reason')
                    )
                    .setDuration(
                        record.get('duration'),
                        status
                    );
            }
        },

        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            fieldDefaults: {
                anchor: '100%'
            },
            defaults: {
                xtype: 'displayfield',
                labelWidth: 140
            },
            items: [{
                name: 'id',
                fieldLabel: 'ID'
            }, {
                name: 'rolename',
                fieldLabel: 'Name',
                renderer: function (value) {
                    var record = form.getRecord();

                    if (!Ext.isEmpty(record)) {
                        var roleId = record.get('role_id');
                        var status = record.get('status');

                        if (!Ext.isEmpty(value) && !Ext.isEmpty(roleId) && status === 'success') {
                            return '<a href="#/roles?roleId=' + roleId + '">' + value + '</a>';
                        }
                    }

                    return value;
                }
            }, {
                name: 'cloud_location',
                fieldLabel: 'Location',
                renderer: function (value) {
                    var record = form.getRecord();
                    var cloudLocation = !Ext.isEmpty(value) ? value : 'All locations';

                    if (!Ext.isEmpty(record)) {
                        var platform = record.get('platform');
                        var platformName = Scalr.utils.getPlatformName(platform);

                        return '<img class="x-icon-platform-small x-icon-platform-small-' + platform +
                            '" data-qtip="' + platformName + '" src="' + Ext.BLANK_IMAGE_URL +
                            '"/> ' + cloudLocation;
                    }

                    return cloudLocation;
                }
            }, {
                name: 'os_family',
                fieldLabel: 'Operating system',
                renderer: function (value) {
                    var record = form.getRecord();

                    if (!Ext.isEmpty(record) && !Ext.isEmpty(value)) {
                        var osVersion = record.get('os_version');

                        return '<img style="margin:0 3px 0 0" class="x-icon-osfamily-small x-icon-osfamily-small-' +
                            value + '" src="' + Ext.BLANK_IMAGE_URL + '"/> ' + Scalr.utils.beautifyOsFamily(value) +
                            ' ' + (!Ext.isEmpty(osVersion) ? osVersion : '');
                    }

                    return '&mdash;';
                }
            }, {
                name: 'dtstarted',
                fieldLabel: 'Started',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }, {
                name: 'duration',
                fieldLabel: 'Duration',
                tpl: [
                    '<tpl if="values.status == success">',
                        '<span>values.duration</span>',
                    '<tpl elseif="values.status == failed">',
                        '<span>&mdash;</span>',
                    '<tpl else>',
                        '<span>Ongoing</span>',
                    '</tpl>'
                ]
            }, {
                name: 'created_by_email',
                fieldLabel: 'Created by',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }, {
                fieldLabel: 'Failure reason',
                itemId: 'failureReasonLabel'
            }]
        }, {
            xtype: 'container',
            items: [{
                xtype: 'component',
                itemId: 'failureReason',
                tpl: [
                    '<div class="message-wrapper" style="background:#FEFADE;max-height:80px;margin-top:20px;padding:12px;overflow:hidden;">',
                        '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-fail" style="float:left;margin-right:8px;cursor:default" />',
                        '<div class="message" style="position:relative;overflow:hidden;color:red;word-wrap:break-word;">',
                            '{message:htmlEncode}<br/>',
                            '<div class="expander" style="display:none;background:-moz-linear-gradient(top, rgba(254,250,222,0) 0%,rgba(254,250,222,1) 60%,rgba(254,250,222,1) 100%);background:-webkit-linear-gradient(top, rgba(254,250,222,0) 0%,rgba(254,250,222,1) 60%,rgba(254,250,222,1) 100%);position:absolute;bottom:0;width:100%;padding:32px 0 0;height:50px;cursor:pointer;text-align:center;">',
                                '<img src="' + Ext.BLANK_IMAGE_URL + '" class="showmore x-icon-show-more-red" />',
                            '</div>',
                        '</div>',
                    '</div>'
                ],
                activateExpander: function () {
                    var me = this,
                        messageEl = me.el.down('.message'),
                        messageWrapperEl;
                    if (messageEl) {
                        messageWrapperEl = me.el.down('.message-wrapper');
                        if (messageEl.getHeight() > 100) {
                            messageEl.setStyle('height', messageWrapperEl.getHeight() + 'px');
                            messageEl.down('.expander').show();
                        }
                        messageWrapperEl.setStyle('max-height', null);
                        me.el.on('click', function(e) {
                            if (Ext.fly(e.getTarget()).hasCls('showmore')) {
                                me.el.down('.message').setStyle('height', 'auto');
                                me.el.down('.expander').hide();
                                me.updateLayout();
                            }
                            e.stopEvent();

                        });
                    }
                },
                setValue: function (value) {
                    var me = this;

                    me.update({
                        message: value
                    });

                    me.activateExpander();

                    return me;
                }
            }]
        }, {
            xtype: 'fieldset',
            flex: 1,
            minHeight: 300,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'fieldcontainer',
                fieldLabel: 'Log',
                layout: 'hbox',
                items: [{
                    xtype: 'tbfill',
                    flex: 1
                }, {
                    xtype: 'button',
                    text: 'View full log',
                    tooltip: 'View full log for selected bundle task',
                    handler: function () {
                        form.viewLog();
                    }
                }]

            }, {
                xtype: 'grid',
                itemId: 'log',
                trackMouseOver: false,
                disableSelection: true,
                hideHeaders: true,
                margin: '6 0 0 0',
                flex: 1,
                plugins: [{
                    ptype: 'gridstore',
                    highlightNew: true
                }],
                store: {
                    fields: [{
                            name: 'id',
                            type: 'int'
                        },
                        'dtadded',
                        'message'
                    ],
                    proxy: 'object'
                },
                viewConfig: {
                    emptyText: 'Log is empty.',
                    deferEmptyText: false,
                    getRowClass: function (record, rowIndex) {
                        var messageTimestamp = Ext.Date.format(
                            new Date(record.get('dtadded')), 'time'
                        );

                        var isNew = Ext.Date.format(new Date(), 'time') - Ext.Date.format(
                                new Date(record.get('dtadded')), 'time'
                            ) < form.shortLogRefreshTime;

                        return !isNew ? '' : 'x-grid-row-color-new';
                    }
                },
                columns: [{
                    header: 'Date / Message',
                    xtype: 'templatecolumn',
                    flex: 1,
                    sortable: false,
                    tpl: [
                        '<div style="max-height:60px;padding:5px 0;">',
                            '<div style="font:11px OpenSansBold;text-transform:uppercase;color:#333;margin-bottom:6px;">{dtadded}</div>',
                            '<div style="font:14px OpenSansRegular,arial,sans-serif;color:#224164;overflow:hidden;white-space:nowrap;text-overflow: ellipsis;">{message}</div>',
                        '</div>'
                    ]
                }],
            }]
        }]
    });

    return Ext.create('Ext.panel.Panel', {

        stateId: 'grid-bundletasks-view',
        stateful: true,

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Bundle Tasks',
            menuHref: '#/bundletasks',
            menuFavorite: true
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: .4,
            maxWidth: 550,
            minWidth: 300,
            layout: 'fit',
            items: [ form ]
        }]
    });
});

Scalr.regPage('Scalr.ui.bundletasks.view.logs', function () {
    var logStore = Ext.create('Scalr.ui.ContinuousStore', {
        fields: [{
            name: 'id',
            type: 'int'
        },
            'dtadded',
            'message'
        ],

        proxy: {
            type: 'ajax',
            url: '/bundletasks/xListLogs/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },
        sorters: [{
            property: 'id',
            direction: 'DESC'
        }]
    });

    return {
        title: 'Bundle Task Log',
        layout: 'fit',
        height: '80%',
        /*
         maxHeight will be overwritten,
         so to prevent jerky scrolling we set "height" in percentage
         */
        width: '80%',
        scrollable: false,

        items: [{
            xtype: 'grid',
            store: logStore,
            margin: '12 0',
            scrollable: true,

            plugins: [{
                ptype: 'continuousrenderer'
            }, {
                ptype: 'rowexpander',
                rowBodyTpl: [
                    '<p><b>Message:</b> <span>{message}</span></p>'
                ]
            }],

            viewConfig: {
                emptyText: 'Log is empty'
            },

            columns: [{
                header: "Date",
                width: 180,
                dataIndex: 'dtadded',
                sortable: true
            }, {
                header: "Message",
                flex: 1,
                dataIndex: 'message',
                sortable: false
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'Close',
                maxWidth: 140,
                handler: function (button) {
                    button.up('panel').close();
                }
            }]
        }],

        listeners: {
            boxready: function (panel) {
                logStore
                    .applyProxyParams({
                        bundleTaskId: panel.bundleTaskId
                    })
                    .on('load', function () {
                        panel.updateLayout();
                        panel.down('grid').getView().refresh();
                    },
                    panel, {
                        single: true
                    }
                );
            }
        }
    };
});

Ext.define('Scalr.ui.WebhooksGrid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.webhooksgrid',

    flex: 1,

    initComponent: function() {
        var me = this;
        me.selModel =
            !me.readOnly ? {
                selType: 'selectedmodel',
                getVisibility: function(record) {
                    return this.view ? this.view.up().scope == record.get('scope') : true;
                }
            } : null;

        me.typeTitle = me.type === 'config' ? 'webhook' : me.type;
        me.viewConfig = {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No ' + me.typeTitle + 's were found to match your search.',
                emptyTextNoItems: 'You have no ' + me.typeTitle + 's created yet.'
            },
            loadingText: 'Loading ' + me.typeTitle + 's ...',
            deferEmptyText: false
        };
        me.dockedItems = [];
        me.dockedItems.push({
            xtype: 'toolbar',
            ui: 'simple',
            dock: 'top',
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                itemId: 'liveSearch',
                margin: 0,
                minWidth: 60,
                maxWidth: 200,
                flex: 1,
                filterFields: me.filterFields,
                handler: null,
                store: me.store
            },{
                xtype: 'cyclealt',
                name: 'scope',
                getItemIconCls: false,
                hidden: Scalr.user.type === 'ScalrAdmin',
                width: 130,
                changeHandler: function (field, menuItem) {
                    me.store.applyProxyParams({
                        scope: menuItem.value
                    });
                },
                getItemText: function (item) {
                    return item.value
                        ? 'Scope: &nbsp;<img src="'
                            + Ext.BLANK_IMAGE_URL
                            + '" class="' + item.iconCls
                            + '" title="' + item.text + '" />'
                        : item.text;
                },
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: [{
                        text: 'All scopes',
                        value: null
                    }, {
                        text: 'Scalr scope',
                        value: 'scalr',
                        iconCls: 'scalr-scope-scalr'
                    }, {
                        text: 'Account scope',
                        value: 'account',
                        iconCls: 'scalr-scope-account'
                    }, {
                        text: 'Environment scope',
                        value: 'environment',
                        iconCls: 'scalr-scope-environment',
                        hidden: Scalr.scope !== 'environment',
                        disabled: Scalr.scope !== 'environment'
                    }]
                }
            },{
                xtype: 'tbfill',
                flex: .1,
                margin: 0
            },{
                xtype: 'tbfill',
                flex: .1,
                margin: 0
            },{
                itemId: 'add',
                text: 'New ' + me.typeTitle,
                cls: 'x-btn-green',
                enableToggle: true,
                hidden: me.readOnly,
                toggleHandler: function (button, pressed) {
                    me.fireEvent('btnnewclick', pressed);
                }
            },{
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function() {
                    me.fireEvent('btnrefreshclick');
                }
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Delete ' + me.typeTitle,
                hidden: me.readOnly,
                handler: function () {
                    var action = this.getItemId(),
                        actionMessages = {
                            'delete': ['Delete selected ' + me.typeTitle + '(s)', 'Deleting selected ' + me.typeTitle + '(s) ...']
                        },
                        selModel = me.getSelectionModel(),
                        ids = [],
                        request = {};

                    for (var i=0, records = selModel.getSelection(), len=records.length; i<len; i++) {
                        ids.push(records[i].get((me.type === 'config' ? 'webhook' : me.type) + 'Id'));
                    }

                    request = {
                        confirmBox: {
                            msg: actionMessages[action][0],
                            type: action
                        },
                        processBox: {
                            msg: actionMessages[action][1],
                            type: action
                        },
                        params: {action: action},
                        success: function (data) {
                            if (data.processed && data.processed.length) {
                                switch (action) {
                                    case 'delete':
                                        var recordsToDelete = [];
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            recordsToDelete.push(me.store.getById(data.processed[i]));
                                            selModel.deselect(recordsToDelete[i]);
                                        }
                                        me.store.remove(recordsToDelete);
                                        break;
                                }
                            }
                        }
                    };
                    request.url = '/webhooks/' + me.type + 's/xGroupActionHandler';
                    request.params[me.typeTitle + 'Ids'] = Ext.encode(ids);

                    Scalr.Request(request);
                }
            }]
        });

        me.on('selectionchange',  function(selModel, selected) {
            this.down('#delete').setDisabled(!selected.length);
        });

        me.plugins = ['focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true
        }];

        me.callParent(arguments);
    }
});
Scalr.regPage('Scalr.ui.scripts.events.view', function (loadParams, moduleParams) {
    var scalrOptions;
    if (moduleParams['scope'] == 'account') {
        scalrOptions = {
            title: 'Account management &raquo; Custom events',
            maximize: 'all',
            leftMenu: {
                menuId: 'settings',
                itemId: 'events',
                showPageTitle: true
            }
        };
    } else {
        scalrOptions = {
            maximize: 'all'
        };
    }

    var store = Ext.create('store.store', {
        fields: [
            'id',
            'name',
            'description',
            'used',
            'status',
            'scope'
        ],
        data: moduleParams['events'],
        proxy: {
            type: 'ajax',
            url: '/scripts/events/xList/',
            extraParams: {
                scope: moduleParams['scope']
            },
            reader: {
                type: 'json',
                root: 'data',
                successProperty: 'success'
            }
        },
        sorters: [{
            property: 'name'
        }]
    });

    var grid = Ext.create('Ext.grid.Panel', {
        cls: 'x-grid-shadow x-panel-column-left',
        store: store,
        flex: 1,
        selModel: {
            selType: 'selectedmodel',
            getVisibility: function(record) {
                return moduleParams['scope'] == record.get('scope') && !record.get('used');
            }
        },
        plugins: ['focusedrowpointer'],
        columns: [{
            header: '<img style="cursor: help" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-info" data-qclass="x-tip-light" data-qtip="' +
            Ext.String.htmlEncode('<div>Scopes:</div>' +
            '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr">&nbsp;&nbsp;Scalr</div>' +
            '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-account">&nbsp;&nbsp;Account</div>' +
            '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-environment">&nbsp;&nbsp;Environment</div>') +
            '" />&nbsp;Name',
            flex: 1,
            dataIndex: 'name',
            resizable: false,
            sortable: true,
            xtype: 'templatecolumn',
            tpl: new Ext.XTemplate('{[this.getScope(values.scope)]}&nbsp;&nbsp;{name}',
                {
                    getScope: function (scope) {
                        return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-' + scope + '" data-qtip="' + Ext.String.capitalize(scope) + ' scope"/>';
                    }
                }
            )
        }, {
            header: 'Status',
            minWidth: 90,
            width: 90,
            dataIndex: 'status',
            sortable: true,
            resizable: false,
            xtype: 'statuscolumn',
            statustype: 'customevent'
        }, {
            header: 'Actions',
            width: 90,
            minWidth: 90,
            hidden: Scalr.user.type == 'ScalrAdmin',
            sortable: false,
            align: 'center', xtype: 'templatecolumn', tpl:
            '<a title="Fire event" href="#/scripts/events/fire?eventName={name}"><img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-server-action x-icon-server-action-execute" /></a>'
        }],
        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No custom events found.',
                emptyTextNoItems: 'You have no custom events added yet.'
            },
            loadingText: 'Loading custom events ...',
            deferEmptyText: false,
            listeners: {
                refresh: function(view){
                    view.getSelectionModel().setLastFocused(null);
                    view.getSelectionModel().deselectAll();
                }
            }
        },
        listeners: {
            selectionchange: function(selModel, selected) {
                this.down('#delete').setDisabled(!selected.length);
            }
        },
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            defaults: {
                margin: '0 0 0 12',
                handler: function() {
                    var action = this.getItemId(),
                        actionMessages = {
                            'delete': ['Delete selected custom event(s)', 'Deleting custom event(s) ...']
                        },
                        selModel = grid.getSelectionModel(),
                        ids = [],
                        request = {};

                    for (var i=0, records = selModel.getSelection(), len=records.length; i<len; i++) {
                        ids.push(records[i].get('id'));
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
                        params: {action: action, scope: moduleParams['scope']},
                        success: function (data) {
                            if (data.processed && data.processed.length) {
                                switch (action) {
                                    case 'delete':
                                        var recordsToDelete = [];
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            recordsToDelete.push(grid.store.getById(data.processed[i]));
                                            selModel.deselect(recordsToDelete[i]);
                                        }
                                        grid.store.remove(recordsToDelete);
                                        break;
                                }
                            }
                            selModel.refreshLastFocused();
                        }
                    };
                    request.url = '/scripts/events/xGroupActionHandler';
                    request.params['ids'] = Ext.encode(ids);

                    Scalr.Request(request);
                }
            },
            items: [{
                xtype: 'filterfield',
                itemId: 'liveSearch',
                margin: 0,
                minWidth: 60,
                maxWidth: 200,
                flex: 1,
                filterFields: ['name'],
                handler: null,
                store: store
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
                text: 'New custom event',
                cls: 'x-btn-green-bg',
                handler: function() {
                    grid.getSelectionModel().setLastFocused(null);
                    form.loadRecord(grid.store.createModel({scope: moduleParams['scope']}));
                }
            },{
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                tooltip: 'Refresh',
                handler: function() {
                    store.load();
                }
            },{
                itemId: 'delete',
                ui: 'paging',
                iconCls: 'x-tbar-delete',
                disabled: true,
                tooltip: 'Delete custom event '
            }]
        }]
    });

    var form = 	Ext.create('Ext.form.Panel', {
        hidden: true,
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        listeners: {
            hide: function() {
                grid.down('#add').setDisabled(false);
            },
            afterrender: function() {
                var me = this;
                grid.getSelectionModel().on('focuschange', function(gridSelModel){
                    if (gridSelModel.lastFocused) {
                        me.loadRecord(gridSelModel.lastFocused);
                    } else {
                        me.hide();
                    }
                });
            },
            beforeloadrecord: function(record) {
                var frm = this.getForm(),
                    isNewRecord = !record.get('id');

                frm.reset(true);

                if (moduleParams['scope'] == record.get('scope')) {
                    this.getDockedComponent('buttons').show();
                    frm.findField('name').setReadOnly(!isNewRecord);
                    frm.findField('description').setReadOnly(false);
                    this.down('#formtitle').setTitle(isNewRecord ? 'New custom event' : 'Edit custom event');
                } else {
                    this.getDockedComponent('buttons').hide();
                    frm.findField('name').setReadOnly(true);
                    frm.findField('description').setReadOnly(true);
                    this.down('#formtitle').setTitle('View custom event');
                }
                this.down('#delete').setDisabled(!!record.get('used'));

                var c = this.query('component[cls~=hideoncreate], #delete');
                for (var i=0, len=c.length; i<len; i++) {
                    c[i].setVisible(!isNewRecord);
                }
                grid.down('#add').setDisabled(isNewRecord);
            },
            loadrecord: function() {
                if (!this.isVisible()) {
                    this.show();
                }
            }
        },
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 70,
            allowBlank: false,
            validateOnChange: false
        },
        items: [{
            xtype: 'fieldset',
            itemId: 'formtitle',
            title: '&nbsp;',
            items: [{
                xtype: 'textfield',
                name: 'name',
                allowBlank: false,
                regex: /^[A-Za-z0-9]+$/,
                regexText: 'Name should contain only alphanumeric characters',
                fieldLabel: 'Name'
            }, {
                xtype: 'textarea',
                name: 'description',
                allowBlank: true,
                fieldLabel: 'Description'
            },{
                xtype: 'displayfield',
                cls: 'hideoncreate',
                name: 'status',
                renderer: function(value) {
                    var record = this.up('form').getForm().getRecord(),
                        used,
                        text;
                    if (record) {
                        used = record.get('used');
                        if (used) {
                            text = ['This <b>Custom Event</b> is currently used by '];
                            if (used['rolesCount'] > 0) {
                                text.push(used['rolesCount']+'&nbsp;Role(s)');
                            }
                            if (used['farmRolesCount'] > 0) {
                                text.push((text.length > 1 ? ' and ' : '') + used['farmRolesCount'] + '&nbsp;FarmRole(s)');
                            }
                            if (used['webhooksCount'] > 0) {
                                text.push((text.length > 1 ? ' and ' : '') + used['webhooksCount'] + '&nbsp;Webhook(s)');
                            }
                            text = text.join('');
                        } else {
                            text = 'This <b>Custom Event</b> is currently not used by any <b>Role</b>, <b>Farm Role</b> or <b>Webhook</b>.';
                        }

                    }
                    return text;
                }
            }]
        }],
        dockedItems: [{
            xtype: 'container',
            itemId: 'buttons',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            maxWidth: 1100,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [{
                itemId: 'save',
                xtype: 'button',
                text: 'Save',
                handler: function() {
                    var frm = form.getForm(),
                        params,
                        name = frm.findField('name').getValue(),
                        record = frm.getRecord();
                    if (frm.isValid()) {
                        params = {
                            id: record.get('id'),
                            scope: moduleParams['scope']
                        };

                        var fn = function(replace) {
                            Scalr.Request({
                                processBox: {
                                    type: 'save'
                                },
                                confirmBox: replace ? {
                                    type: 'action',
                                    formWidth: 650,
                                    msg: Scalr.user.type == 'ScalrAdmin' ?
                                        'A "' + name + '" Custom Event already exists in one or multiple Accounts. Creating "' +name +
                                        '" as an Scalr-Scope Custom Event will transparently replace those Account-Scope and Environment-Scope Custom Events.<br><br>' +
                                        'Orchestration Rules, Webhooks, and other integrations will continue to function, with no additional changes required.'
                                        :
                                        'A "' + name + '" Custom Event already exists in one or multiple Environments that are part of this Account. Creating "' +name +
                                        '" as an Account-Scope Custom Event will transparently replace those Environment-Scope Custom Events.<br><br>' +
                                        'Orchestration Rules, Webhooks, and other integrations will continue to function, with no additional changes required.'
                                } : null,
                                url: '/scripts/events/xSave',
                                form: frm,
                                params: params,
                                success: function (data) {
                                    var isNewRecord = !record.get('id');
                                    grid.getSelectionModel().setLastFocused(null);
                                    form.setVisible(false);
                                    if (isNewRecord) {
                                        record = store.add(data.event)[0];
                                        grid.getSelectionModel().select(record);
                                    } else {
                                        record.set(data.event);
                                        form.loadRecord(record);
                                    }
                                    if (isNewRecord) {
                                        grid.getSelectionModel().select(record);
                                    } else {
                                        grid.getSelectionModel().setLastFocused(record);
                                    }
                                    Scalr.CachedRequestManager.get().setExpired({url: '/scripts/events/xList'});
                                },
                                failure: function(data) {
                                    if (data && data['replaceEvent']) {
                                        params['replaceEvent'] = true;
                                        fn(true);
                                    }
                                }
                            });
                        };

                        fn();
                    }
                }
            }, {
                itemId: 'cancel',
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    grid.getSelectionModel().setLastFocused(null);
                    form.setVisible(false);
                }
            }, {
                itemId: 'delete',
                xtype: 'button',
                cls: 'x-btn-default-small-red',
                text: 'Delete',
                handler: function() {
                    var record = form.getForm().getRecord();
                    Scalr.Request({
                        confirmBox: {
                            msg: 'Delete custom event?',
                            type: 'delete'
                        },
                        processBox: {
                            msg: 'Deleting...',
                            type: 'delete'
                        },
                        scope: this,
                        url: '/scripts/events/xGroupActionHandler',
                        params: { ids: Ext.encode([record.get('id')]), action: 'delete' },
                        success: function (data) {
                            record.store.remove(record);
                        }
                    });
                }
            }]
        }]
    });

    var panel = Ext.create('Ext.panel.Panel', {
        cls: 'scalr-ui-panel-webhooks-ebndpoints',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        scalrOptions: scalrOptions,
        title: moduleParams['scope'] == 'account' ? '' : Ext.String.capitalize(moduleParams['scope']) + ' custom events',
        items: [
            grid
            ,{
                xtype: 'container',
                itemId: 'rightcol',
                flex: .8,
                maxWidth: 900,
                minWidth: 640,
                layout: 'fit',
                items: form
            }]
    });

    return panel;

});

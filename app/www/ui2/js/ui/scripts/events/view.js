Scalr.regPage('Scalr.ui.scripts.events.view', function (loadParams, moduleParams) {
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
            reader: {
                type: 'json',
                rootProperty: 'data',
                successProperty: 'success'
            }
        },
        sorters: [{
            property: 'name'
        }],
        listeners: {
            beforeload: function () {
                grid.down('#add').toggle(false, true);
            },
            filterchange: function () {
                grid.down('#add').toggle(false, true);
            }
        }
    });

	var reconfigurePage = function(params) {
        if (params.eventId) {
            cb = function() {
                if (params.eventId === 'new') {
                    panel.down('#add').toggle(true);
                } else {
                    panel.down('#liveSearch').reset();
                    var record = store.getById(params.eventId);
                    if (record) {
                        grid.setSelectedRecord(record);
                    }
                }
            };
            if (grid.view.viewReady) {
                cb();
            } else {
                grid.view.on('viewready', cb, grid.view, {single: true});
            }
        }
    };

    var grid = Ext.create('Ext.grid.Panel', {
        cls: 'x-panel-column-left',
        store: store,
        flex: 1,
        selModel:
            Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'manage') ?
            {
                selType: 'selectedmodel',
                getVisibility: function(record) {
                    return moduleParams['scope'] == record.get('scope') && !record.get('used');
                }
            } : null,
        plugins: [ 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true
        }],
        columns: [{
            text: 'Event',
            flex: 1,
            dataIndex: 'name',
            resizable: false,
            sortable: true,
            xtype: 'templatecolumn',
            tpl: new Ext.XTemplate('{[this.getScope(values.scope)]}&nbsp;&nbsp;{name}',
                {
                    getScope: function(scope){
                        return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('event') + '"/>';
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
            hidden: moduleParams['scope'] != 'environment' || !Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'fire'),
            sortable: false,
            align: 'center', xtype: 'templatecolumn', tpl:
            '<a data-qtip="Fire event" href="#/scripts/events/fire?eventName={name}"><img src="' + Ext.BLANK_IMAGE_URL + '" class="x-grid-icon x-grid-icon-execute" /></a>'
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
            deferEmptyText: false
        },
        listeners: {
            selectionchange: function(selModel, selected) {
                this.down('#delete').setDisabled(!selected.length);
            }
        },
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
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
                filterFields: ['name'],
                handler: null,
                store: store
            },{
                xtype: 'cyclealt',
                name: 'scope',
                getItemIconCls: false,
                hidden: Scalr.user.type === 'ScalrAdmin',
                width: 130,
                changeHandler: function (me, menuItem) {
                    store.applyProxyParams({
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
                text: 'New custom event',
                cls: 'x-btn-green',
                enableToggle: true,
                hidden: !Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'manage'),
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();
                        form.loadRecord(grid.store.createModel({scope: moduleParams['scope']}));
                        form.down('[name=name]').focus();

                        return;
                    }

                    form.hide();
                }
            },{
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function() {
                    store.load();
                }
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Delete custom event ',
                hidden: !Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'manage'),
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
                        }
                    };
                    request.url = '/scripts/events/xGroupActionHandler';
                    request.params['ids'] = Ext.encode(ids);

                    Scalr.Request(request);
                }
            }]
        }]
    });

    var form = 	Ext.create('Ext.form.Panel', {
        hidden: true,

        layout: {
            type: 'vbox',
            align: 'stretch'
        },

        disableButtons: function (disabled, scope, isEventUsed) {
            var me = this;

            var tooltip = !disabled
                ? ''
                : Scalr.utils.getForbiddenActionTip('event', scope);

            Ext.Array.each(
                me.getDockedComponent('buttons').query('#save, #delete'),
                function (button) {
                    button.
                        setTooltip(tooltip).
                        setDisabled(
                            button.getItemId() !== 'delete'
                                ? disabled
                                : disabled || isEventUsed
                    );
                }
            );

            return me;
        },

        toggleScopeInfo: function(record) {
            var me = this,
                scopeInfoField = me.down('#scopeInfo');
            if (Scalr.scope != record.get('scope')) {
                scopeInfoField.setValue(Scalr.utils.getScopeInfo('custom event', record.get('scope'), record.get('id')));
                scopeInfoField.show();
            } else {
                scopeInfoField.hide();
            }
            return me;
        },

        listeners: {
            beforeloadrecord: function(record) {
                var frm = this.getForm(),
                    isNewRecord = !record.store,
                    scope = record.get('scope'),
                    isEventUsed = !!record.get('used');

                this.down('#save').setText(isNewRecord ? 'Create' : 'Save');

                if (moduleParams['scope'] === scope) {
                    this.disableButtons(false, scope, isEventUsed);
                    frm.findField('name').setReadOnly(!isNewRecord || !Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'manage'));
                    frm.findField('description').setReadOnly(!Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'manage'));
                    this.down('#formtitle').setTitle((Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'manage') ? (isNewRecord ? 'New' : 'Edit') : 'View') + ' custom event');
                } else {
                    this.disableButtons(true, scope, isEventUsed);
                    frm.findField('name').setReadOnly(true);
                    frm.findField('description').setReadOnly(true);
                    this.down('#formtitle').setTitle((Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'manage') ? 'Edit' : 'View') + ' custom event');
                }

                var c = this.query('component[cls~=hideoncreate], #delete');
                for (var i=0, len=c.length; i<len; i++) {
                    c[i].setVisible(!isNewRecord);
                }

                grid.down('#add').toggle(isNewRecord, true);
                this.toggleScopeInfo(record);
            }
        },
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 90,
            allowBlank: false
        },
        items: [{
            xtype: 'displayfield',
            itemId: 'scopeInfo',
            cls: 'x-form-field-info x-form-field-info-fit',
            anchor: '100%',
            hidden: true
        },{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
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
                        text = '';
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
                            if (used['accountScriptsCount'] > 0) {
                                text.push((text.length > 1 ? ' and ' : '') + used['accountScriptsCount'] + '&nbsp;Orchestration(s)');
                            }
                            text = text.join('');
                        } else {
                            text = 'This <b>Custom Event</b> is currently not used by any <b>Role</b>, <b>Farm Role</b>, <b>Webhook</b> or <b>Orchestration</b>.';
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
            hidden: !Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'manage'),
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
                            id: record.store ? record.get('id') : null
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
                                    if (!record.store) {
                                        record = store.add(data.event)[0];
                                    } else {
                                        record.set(data.event);
                                    }
                                    grid.clearSelectedRecord();
                                    grid.setSelectedRecord(record);
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
                    grid.clearSelectedRecord();
                    grid.down('#add').toggle(false, true);
                }
            }, {
                itemId: 'delete',
                xtype: 'button',
                cls: 'x-btn-red',
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
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        scalrOptions: {
            maximize: 'all',
            menuTitle: 'Custom events',
            menuHref: '#' + (Scalr.scope == 'environment' ? '/scripts/events' : Scalr.utils.getUrlPrefix() + '/events'),
            menuFavorite: Ext.Array.contains(['account', 'environment'], Scalr.scope),
        },
        stateId: 'grid-scripts-events-view',
        listeners: {
            applyparams: reconfigurePage
        },
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

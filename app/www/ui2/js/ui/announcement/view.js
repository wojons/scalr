Scalr.regPage('Scalr.ui.announcement.view', function (loadParams, moduleParams) {

    var store = Ext.create('Scalr.ui.ContinuousStore', {
        autoLoad: true,
        fields: ['id', 'accountId', 'added', 'title', 'msg', 'user'],
        sorters: [{
            property: 'added',
            direction: 'DESC'
        }],
        proxy: {
            type: 'ajax',
            url: '/announcements/xList',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },

        listeners: {
            beforeload: function () {
                grid.down('#add').toggle(false, true);
            },
            load: function(store, records, success) {
                if (success && store.getCount() === 1) {
                    grid.setSelectedRecord(records[0]);
                }
            },
            filterchange: function () {
                grid.down('#add').toggle(false, true);
            }
        },
        removeByIds: function(ids) {
            this.remove(Ext.Array.map(ids, function(id) {
                return this.getById(id);
            }, this));

            !this.getCount() && grid.getView().refresh();

            return this;
        }
    });

    var grid = Ext.create('Ext.grid.Panel', {
        cls: 'x-panel-column-left',
        flex: 1,
        scrollable: true,

        store: store,

        plugins: ['focusedrowpointer', 'continuousrenderer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }],
        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            emptyText: 'No announcements found',
            loadingText: 'Loading announcements ...',
            deferEmptyText: false
        },
        selModel: {
            selType: 'selectedmodel',
            getVisibility: function (record) {
                return Scalr.scope === 'scalr' ||
                    record.get('accountId') == Scalr.user.clientId;
            }
        },

        listeners: {
            selectionchange: function(selModel, selection) {
                this.down('toolbar #delete').setDisabled(!selection.length);
            }
        },

        deleteRecords: function (ids, names) {
            if (Ext.typeOf(ids) !== 'array') {
                ids = [ids];
                names = [names];
            } else if (ids.length === 1 && Ext.typeOf(names) !== 'array') {
                names = [names];
            }
            var multiKill = ids.length > 1;

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    winConfig: {width: ' ', minWidth: 400, maxWidth: 600},
                    msg: multiKill ?
                        'Delete selected announcements:<br>%s ?' :
                        'Delete announcement<br><b>' + names[0] + '</b> ?',
                    objects: multiKill ? names : null
                },
                processBox: {
                    type: 'delete',
                    msg: 'Deleting announcement' + (multiKill ? 's' : '') + ' ...'
                },
                url: '/announcements/xRemove',
                params: {
                    ids: Ext.JSON.encode(ids)
                },
                success: function (data) {
                    var ids = data['processed'];

                    !Ext.isEmpty(ids) && store.removeByIds(ids);
                }
            });
        },
        deleteSelected: function () {
            var ids = [], names = [];

            Ext.Array.each(this.getSelectionModel().getSelection(),
                function (record) {
                    ids.push(record.get('id'));
                    names.push(Scalr.utils.announcementHelper.stripTags(record.get('title')));
                }
            );
            this.deleteRecords(ids, names);

            return this;
        },

        columns: [
            {text: 'ID', dataIndex: 'id', width: 60, sortable: false},
            {
                text: 'Title',
                dataIndex: 'title',
                xtype: 'templatecolumn',
                tpl: ['{title:htmlEncode}'],
                flex: 1,
                sortable: true
            },{
                text: 'Date Created',
                dataIndex: 'added',
                width: 160,
                sortable: true
            },{
                text: 'User',
                dataIndex: 'user',
                xtype: 'templatecolumn',
                tpl: [
                    '<tpl for="user">',
                    '<tpl if="name">{name}</tpl>',
                    '<tpl if="email"> &lt;{email}&gt;</tpl>',
                    '</tpl>'
                ],
                flex: .8,
                sortable: false
            }
        ],
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12'
            },

            items: [{
                xtype: 'filterfield',
                store: store,
                margin: 0
            },{
                xtype: 'tbfill'
            },{
                text: 'New announcement',
                itemId: 'add',
                cls: 'x-btn-green',
                enableToggle: true,
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();
                        form.loadRecord(new Ext.data.Model({id: null}));
                        return;
                    }
                    form.hide();
                }
            },{
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.clearAndLoad();
                }
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Select one or more announcements to delete them',
                handler: function () {
                    grid.deleteSelected();
                }
            }]
        }]
    });

    var form = Ext.create('Ext.form.Panel', {
        hidden: true,
        autoScroll: true,

        listeners: {
            loadrecord: function (record) {
                var isEdit = record.store ? true : false,
                    isReadOnly = isEdit && Scalr.scope !== 'scalr' && !record.get('accountId'),
                    buttons = this.down('#buttons');

                if (isEdit) {
                    grid.down('#add').toggle(false, true);
                }
                buttons.down('#delete').setVisible(isEdit);
                buttons.down('#save').setText(isEdit ? 'Save' : 'Create');
                this.query('[name=title], [name=msg], #buttons button').forEach(function (cmp) {
                    cmp.setDisabled(isReadOnly);
                });

                this.clearInvalid()
                    .setHeader(isEdit ? 'Edit Announcement' : 'New Announcement')
                    .toggleScopeInfo(isReadOnly)
                    .show();
            }
        },
        clearInvalid: function () {
            Ext.each(this.down('[isFormField=true]'), function(cmp){
                cmp.clearInvalid();
            });

            return this;
        },
        setHeader: function (header) {
            this.down('fieldset').setTitle(header);

            return this;
        },
        toggleScopeInfo: function (readOnly) {
            var scopeInfoField = this.down('#scopeInfo');
            if (readOnly) {
                scopeInfoField.show();
            } else {
                scopeInfoField.hide();
            }

            return this;
        },

        items: [{
                xtype: 'displayfield',
                itemId: 'scopeInfo',
                cls: 'x-form-field-info x-form-field-info-fit',
                anchor: '100%',
                value: Scalr.utils.getScopeInfo('announcement', 'scalr'),
                hidden: true
            },{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            title: 'Edit announcement',
            labelWidth: 120,
            defaults: {
                labelAlign: 'top',
                width: '100%'
            },
            items: [{
                xtype: 'textfield',
                name: 'title',
                fieldLabel: 'Title',
                allowBlank: false,
                maxLength: 100
            },{
                xtype: 'textareafield',
                name: 'msg',
                fieldLabel: 'Message',
                height: 320,
                overflowY: 'auto',
                allowBlank: false,
                plugins: [{
                    ptype: 'fieldicons',
                    position: 'label',
                    icons: {
                        id: 'info',
                        tooltip: Scalr.strings['announcement.editor.info']
                    }
                }]
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
                xtype: 'button',
                flex: 1,
                maxWidth: 140
            },
            items: [{
                itemId: 'save',
                text: 'Save',
                handler: function() {
                    if (form.isValid()) {
                        var record = form.getRecord(),
                            util = Scalr.utils.announcement;

                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/announcements/xSave',
                            form: form.getForm(),
                            params: {id: record.store ? record.get('id') : null},
                            success: function (data) {
                                if (!record.store) {
                                    record = store.add(data.announcement)[0];
                                } else {
                                    record.set(data.announcement);
                                }
                                grid.clearSelectedRecord();
                                grid.setSelectedRecord(record);
                                if (!util.suspended) {
                                    util.load();
                                }
                            }
                        });
                    }
                }
            }, {
                itemId: 'cancel',
                text: 'Cancel',
                handler: function() {
                    grid.clearSelectedRecord();
                    grid.down('#add').toggle(false, true);
                }
            }, {
                itemId: 'delete',
                cls: 'x-btn-red',
                text: 'Delete',
                handler: function() {
                    var record = form.getRecord();
                    grid.deleteRecords(record.get('id'), record.get('title'));
                }
            }]
        }]
    });

    var isScalrScope = Scalr.scope === 'scalr',
        menuHref = isScalrScope ? '#/admin/announcements' : '#/account/announcements';

    return Ext.create('Ext.panel.Panel', {
        stateful: true,
        stateId: 'grid-announcement-messages-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Announcements',
            menuHref: menuHref,
            menuFavorite: !isScalrScope
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: .8,
            maxWidth: 800,
            minWidth: 400,
            layout: 'fit',
            items: [ form ]
        }]
    });
});

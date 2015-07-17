Scalr.regPage('Scalr.ui.core.api2', function () {

	var store = Ext.create('store.store', {
        model: Scalr.getModel({
            idProperty: 'keyId',
            fields: ['keyId', 'secretKey', 'active', {name: 'created', type: 'date'}, {name: 'lastUsed', type: 'date'}, 'createdHr', 'lastUsedHr']
        }),
        proxy: {
            type: 'ajax',
            url: '/core/xListApiKeys',
            reader: {
                type: 'json',
                rootProperty: 'data',
                successProperty: 'success'
            }
        },

        removeByApiKeyId: function (ids) {
            var me = this;

            me.remove(Ext.Array.map(
                ids, function (id) {
                    return me.getById(id);
                }
            ));

            if (me.getCount() === 0) {
                grid.getView().refresh();
            }

            return me;
        }
	});

	var grid = Ext.create('Ext.grid.Panel', {

        cls: 'x-panel-column-left',
        flex: 1,
        scrollable: true,

		store: store,

        plugins: [ 'applyparams', 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }],

        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No API keys found.',
                emptyTextNoItems: 'You have no API keys added yet.'
            },
            loadingText: 'Loading API keys ...',
            deferEmptyText: false
        },

        selModel: 'selectedmodel',

        listeners: {
            selectionchange: function(selModel, selected) {
                this.down('#delete').setDisabled(!selected.length);
                this.down('#activate').setDisabled(!selected.length);
                this.down('#deactivate').setDisabled(!selected.length);
            }
        },

		columns:[{
            text: 'Key ID',
            dataIndex: 'keyId',
            width: 230,
            sortable: true,
            resizable: false
        },{
            text: 'Description',
            sortable: true,
            dataIndex: 'name',
            flex: 1
        },{
            text: 'Created',
            dataIndex: 'created',
            width: 180,
            resizable: false,
            xtype: 'templatecolumn',
            tpl: '{createdHr}'
        },{
            text: 'Last used',
            dataIndex: 'lastUsed',
            width: 180,
            resizable: false,
            xtype: 'templatecolumn',
            tpl: '{lastUsedHr}'
        },{
            text: 'Status',
            dataIndex: 'active',
            xtype: 'statuscolumn',
            statustype: 'apikey',
            qtipConfig: {width: 200},
            width: 100,
            minWidth: 100,
            sortable: true,
            resizable: false
        }],

		dockedItems: [{
			xtype: 'toolbar',
			dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12',
                handler: function() {
                    var action = this.getItemId(),
                        grid = this.up('grid'),
                        store = grid.getStore(),
                        actionMessages = {
                            'delete': ['Delete selected API key(s): %s ?', 'Deleting selected API key(s) ...'],
                            activate: ['Activate selected API key(s): %s ?', 'Activating selected API key(s) ...'],
                            deactivate: ['Deactivate selected API key(s): %s ?', 'Deactivating selected API key(s) ...']
                        },
                        selModel = grid.getSelectionModel(),
                        ids = [],
                        request = {};
                    for (var i=0, records = selModel.getSelection(), len=records.length; i<len; i++) {
                        ids.push(records[i].get('keyId'));
                    }

                    request = {
                        confirmBox: {
                            msg: actionMessages[action][0],
                            type: action,
                            objects: ids
                        },
                        processBox: {
                            msg: actionMessages[action][1],
                            type: action
                        },
                        params: {ids: ids, action: action},
                        success: function (data) {
                            if (data.processed && data.processed.length) {
                                switch (action) {
                                    case 'activate':
                                    case 'deactivate':
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            var record = store.getById(data.processed[i]);
                                            record.set('active', action == 'deactivate'? 0 : 1);
                                            selModel.deselect(record);
                                        }
                                    break;
                                    case 'delete':
                                        var recordsToDelete = [];
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            recordsToDelete.push(store.getById(data.processed[i]));
                                            selModel.deselect(recordsToDelete[i]);
                                        }
                                        store.remove(recordsToDelete);
                                        grid.view.refresh();
                                    break;
                                }
                            }
                        }
                    };
                    request.url = '/core/xApiKeysActionHandler';
                    request.params.keyIds = Ext.encode(ids);

                    Scalr.Request(request);
                }
            },
			items: [{
				xtype: 'filterfield',
                itemId: 'filterfield',
				store: store,
                filterFields: ['name', 'keyId'],
                margin: 0,
                listeners: {
                    afterfilter: function () {
                        grid.getView().refresh();
                    }
                },
                handler: null
            }, {
                xtype: 'tbfill'
            },{
                text: 'Generate new API key',
                cls: 'x-btn-green',
                handler: function() {
                    var me = this;
                    Scalr.Request({
                        url: '/core/xGenerateApiKey',
                        processBox: {
                            msg: 'Generating API Key',
                            type: 'action'
                        },
                        success: function (data) {
                            if (data['key']) {
                                var grid = me.up('grid'),
                                    record = grid.getStore().add(data['key']);
                                grid.down('#filterfield').reset();
                                grid.getView().focusRow(record[0]);

                                Scalr.utils.Window({
                                    title: 'Secret Key',
                                    xtype: 'form',
                                    width: 460,
                                    items: [{
                                        xtype: 'fieldset',
                                        cls: 'x-fieldset-separator-none',
                                        items: [{
                                            xtype: 'textfield',
                                            anchor: '100%',
                                            readOnly: true,
                                            value: record[0].get('secretKey'),
                                            listeners: {
                                                afterrender: function() {
                                                    this.focus();
                                                    this.inputEl.dom.select();
                                                }
                                            }
                                        }]
                                    }],
                                    dockedItems: [{
                                        xtype: 'container',
                                        dock: 'bottom',
                                        layout: {
                                            type: 'hbox',
                                            pack: 'center'
                                        },
                                        items: [{
                                            xtype: 'button',
                                            text: 'Close',
                                            margin: '0 0 18 0',
                                            handler: function() {
                                                this.up('form').close();
                                            }
                                        }]
                                    }]
                                });
                                record[0].set('secretKey', '******');
                            }
                        }
                    });
                }
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.load();
                }
            },{
                itemId: 'activate',
                iconCls: 'x-btn-icon-activate',
                disabled: true,
                tooltip: 'Activate'
            },{
                itemId: 'deactivate',
                iconCls: 'x-btn-icon-suspend',
                disabled: true,
                tooltip: 'Deactivate'
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Delete'
            }]
		}]
	});

    var form = Ext.create('Ext.form.Panel', {
        hidden: true,
        trackResetOnLoad: true,
        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-text-transform',
            headerCls: 'x-fieldset-separator-bottom',
            itemId: 'main',
            defaults: {
                labelWidth: 90,
                anchor: '100%'
            },
            items: [{
                xtype: 'displayfield',
                name: 'keyId',
                fieldLabel: 'Key ID'
            },{
                xtype: 'textfield',
                fieldLabel: 'Description',
                selectOnFocus: true,
                name: 'name',
                regex: /^[a-z0-9 _\-]*$/i,
                regexText: 'Name should contain only letters, numbers, spaces and dashes',
                listeners: {
                    focus: function() {
                        this.up('form').down('#save').setDisabled(this.readOnly);
                    },
                    blur: function() {
                        this.up('form').down('#save').setDisabled(!this.isDirty());
                    }
                }
            }, {
                xtype: 'displayfield',
                name: 'createdHr',
                fieldLabel: 'Created'
            }, {
                xtype: 'displayfield',
                name: 'lastUsedHr',
                fieldLabel: 'Last used'
            }]
        }],

        listeners: {
            loadrecord: function() {
                this.down('#save').disable();
            }
        },
        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults: {
                flex: 1,
                maxWidth: 120
            },
            items: [{
                itemId: 'save',
                xtype: 'button',
                text: 'Save',
                disabled: true,
                handler: function () {
                    var me = this,
                        record = me.up('form').getForm().getRecord(),
                        name = me.up('form').down('[name="name"]').getValue();

                    if (this.up('form').down('[name="name"]').isValid())
                        Scalr.Request({
                            processBox: {
                                action: 'save'
                            },
                            url: '/core/xSaveApiKeyName',
                            params: {
                                keyId: record.get('keyId'),
                                name: name
                            },
                            success: function(data) {
                                record.set('name', data.name);
                                me.disable();
                            }
                        });
                }
            }, {
                itemId: 'delete',
                xtype: 'button',
                text: 'Delete',
                cls: 'x-btn-red',
                handler: function() {
                    var record = this.up('form').getForm().getRecord();

                    Scalr.Request({
                        confirmBox: {
                            msg: 'Delete API Key?',
                            type: 'delete',
                            formWidth: 440
                        },
                        params: {
                            action: 'delete',
                            keyIds: Ext.encode([record.get('keyId')])
                        },
                        processBox: {
                            msg: 'Deleting API Key ...',
                            type: 'delete'
                        },
                        url: '/core/xApiKeysActionHandler',
                        success: function (data) {
                            if (data.processed && data.processed.length) {
                                var recordsToDelete = [];
                                for (var i=0,len=data.processed.length; i<len; i++) {
                                    recordsToDelete.push(store.getById(data.processed[i]));
                                    grid.getSelectionModel().deselect(recordsToDelete[i]);
                                }
                                store.remove(recordsToDelete);
                                grid.view.refresh();
                            }
                        }
                    });
                }
            },{
				itemId: 'cancel',
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
                    grid.clearSelectedRecord();
				}
            }]
        }]

    });

    return Ext.create('Ext.panel.Panel', {

        stateful: true,
        stateId: 'grid-api-keys',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'API Keys'
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: .4,
            maxWidth: 900,
            minWidth: 400,
            layout: 'fit',
            items: [ form ]
        }]
    });
});

Scalr.regPage('Scalr.ui.scripts.events.fire', function (loadParams, moduleParams) {
    var form = Ext.create('Ext.form.Panel', {
        width: 900,
        title: 'Fire event',
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },

        items: [{
            xtype: 'fieldset',
            title: 'Event',
            items: [{
                xtype: 'combobox',
                emptyText: 'Select an event',
				store: {
					fields: [ 'name', 'description'],
					data: moduleParams['events'],
					proxy: 'object'
				},
                displayField: 'name',
                queryMode: 'local',
                valueField: 'name',
                editable: true,
                anyMatch: true,
                selectOnFocus: true,
                forceSelection: true,
                autoSearch: false,
                allowBlank: false,
                readOnly: !!moduleParams['eventName'],
                name: 'eventName',
                listeners: {
                    afterrender: function(comp) {
                        comp.inputEl.on('click', function(){
                            if (!comp.readOnly) {
                                comp.expand();
                            }
                        });
                    },
                    change: function(comp, value) {
                        var rec = comp.findRecordByValue(value);
                        comp.next().update(rec ? rec.get('description') || 'No description for this event' : '&nbsp;');
                    }
                }
            },{
                xtype: 'component',
                itemId: 'eventDescription',
                style: 'color:#666;font-style:italic',
                html: '&nbsp;',
                margin: '12 0 0'
            }]
        },{
            xtype: 'farmroles',
            title: 'Event context',
            itemId: 'executionTarget',
            params: moduleParams['farmWidget']
        },{
            xtype: 'fieldset',
            title: 'Scripting parameters',
            cls: 'x-fieldset-separator-none',
            items: {
                xtype: 'grid',
                cls: 'x-grid-shadow x-grid-no-highlighting',
                store: {
                    fields: ['name', 'value'],
                    proxy: 'object'
                },
                features: {
                    ftype: 'addbutton',
                    text: 'Add new param',
                    handler: function(view) {
                        view.up().store.add({});
                    }
                },
                plugins: [{
                    ptype: 'cellediting',
                    pluginId: 'cellediting',
                    clicksToEdit: 1
                }],
                listeners: {
                    viewready: function() {
                        this.store.add({});
                    },
                    itemclick: function (view, record, item, index, e) {
                        var selModel = view.getSelectionModel();
                        if (e.getTarget('img.x-icon-action-delete')) {
                            if (record === selModel.getLastFocused()) {
                                selModel.deselectAll();
                                selModel.setLastFocused(null);
                            }
                            view.store.remove(record);
                            if (!view.store.getCount()) {
                                view.up().store.add({});
                            }
                            return false;
                        }
                    }
                },
                columns: [{
                    header: 'Name',
                    sortable: false,
                    resizable: false,
                    dataIndex: 'name',
                    flex: 1,
                    editor: {
                        xtype: 'textfield',
                        editable: false,
                        margin: '0 12 0 13',
                        fixWidth: -25,
                        maxLength: 127,
                        allowBlank: false
                    },
                    renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                        var column = grid.panel.columns[colIndex],
                           valueEncoded = Ext.String.htmlEncode(value);
                        return  '<div class="x-form-text" style="background:#fff;padding:2px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;cursor:text">'+
                                    valueEncoded +
                                '</div>';
                    }
                },{
                    header: 'Value',
                    sortable: false,
                    resizable: false,
                    dataIndex: 'value',
                    flex: 2,
                    editor: {
                        xtype: 'textfield',
                        editable: false,
                        margin: '0 12 0 13',
                        fixWidth: -25,
                        maxLength: 255
                    },
                    renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                        var column = grid.panel.columns[colIndex],
                            valueEncoded = Ext.String.htmlEncode(value);
                        return  '<div class="x-form-text" style="background:#fff;padding:2px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;cursor:text"  data-qtip="'+valueEncoded+'">'+
                                    valueEncoded +
                                '</div>';
                    }
                },{
                    renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                        return '<img style="cursor:pointer;margin-top:6px;" width="15" height="15" class="x-icon-action x-icon-action-delete" data-qtip="Delete param" src="'+Ext.BLANK_IMAGE_URL+'"/>';
                    },
                    width: 42,
                    sortable: false,
                    align:'left'

                }],
                getValue: function(){
                    var me = this,
                        result  = {};
                    (me.store.snapshot || me.store.data).each(function(record){
                        var name = record.get('name'),
                            value = record.get('value');
                        if (name) {
                            result[name] = value;
                        }
                    });
                    return result;
                }
            }
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
                text: 'Fire event',
                handler: function () {
                    Scalr.message.Flush(true);
                    if (form.getForm().isValid())
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            url: '/scripts/events/xFire/',
                            params: Ext.apply({
                                eventParams: Ext.encode(form.down('grid').getValue())
                            },loadParams),
                            form: form.getForm(),
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]
    });

    if (moduleParams)
        form.getForm().setValues(moduleParams);

    form.getForm().clearInvalid();
    return form;
});
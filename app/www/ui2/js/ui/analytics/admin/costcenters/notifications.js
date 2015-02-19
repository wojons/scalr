Scalr.regPage('Scalr.ui.analytics.admin.costcenters.notifications', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			modal: true
		},
		//title: 'Cost center notifications',
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 80
		},
		width: 960,
        bodyCls: 'x-container-fieldset',
        bodyStyle: 'padding-top:0',
        layout: 'fit',
        items: [{
            xtype: 'grid',
            itemId: 'notifications',
            selModel: {
                selType: 'selectedmodel'
            },
            cls: 'x-grid-shadow x-grid-no-highlighting x-costanalytics-notifications-grid',
            store: {
                fields: ['uuid', 'subjectType', 'subjectId', 'notificationType', 'threshold', {name: 'recipientType', defaultValue: 1}, 'emails', 'status'],
                proxy: 'object'
            },
            features: {
                ftype: 'addbutton',
                text: 'Add new alert',
                handler: function(view) {
                    var grid = view.up();
                    grid.store.add({subjectType: 1, subjectId: loadParams['ccId'], status: 1});
                }
            },
            plugins: [
                Ext.create('Ext.grid.plugin.CellEditing', {
                    pluginId: 'cellediting',
                    clicksToEdit: 1,
                    listeners: {
                        beforeedit: function(editor, o) {
                            if (o.column.isEditable) {
                                return o.column.isEditable(o.record);
                            }
                        }
                    }
                })
            ],
            listeners: {
                selectionchange: function(selModel, selected) {
                    this.down('#delete').setDisabled(!selected.length);
                    this.down('#enable').setDisabled(!selected.length);
                    this.down('#disable').setDisabled(!selected.length);
                }
            },
            setValues: function(id, data) {
                this.store.load({data: data});
            },
            getValues: function() {
                var grid = this,
                    store = grid.store,
                    items = [],
                    error,
                    cellEditing = grid.getPlugin('cellediting');
                (store.snapshot || store.data).each(function(record){
                    var colIndex;
                    if (!record.get('notificationType')) {
                        colIndex = 0;
                    } else if (!record.get('threshold')) {
                        colIndex = 1;
                    } else if (record.get('recipientType') == 2 && !record.get('emails')) {
                        colIndex = 3;
                    }
                    if (colIndex !== undefined) {
                        cellEditing.startEdit(record, colIndex);
                        Ext.Function.defer(cellEditing.context.column.field.validate, 2, cellEditing.context.column.field);
                        error = true;
                        return false;
                    } else {
                        items.push(record.getData());
                    }
                });
                return !error ? {items: items} : false;
            },
            columns: [{
                header: 'Alert type',
                dataIndex: 'notificationType',
                width: 200,
                //tdCls: 'x-form-trigger-wrap',
                resizable: false,
                editor: {
                    xtype: 'combo',
                    store: {
                        fields: ['id', 'name', 'description'],
                        data: [{
                            id: 1, name: 'Usage', description: 'Receive an email when a certain percentage of your budget has been consumed'
                        },{
                            id: 2, name: 'Projected overspend', description: 'Receive an email when Scalr expects budget overspend of a certain percentage'
                        }]
                    },
                    emptyText: 'Select alert type',
                    editable: false,
                    margin: '0 12 0 13',
                    allowBlank: false,
                    valueField: 'id',
                    displayField: 'name',
                    fixWidth: -25,
                    listConfig: {
                        cls: 'x-boundlist-alt',
                        tpl:
                            '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                '<div style="font-weight: bold">{name}</div>' +
                                '<div style="line-height: 26px;white-space:nowrap;">{description}</div>' +
                            '</div></tpl>'
                    }
                },
                renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                    var column = grid.panel.columns[colIndex],
                        editor = column.getEditor(record),
                        rec = editor.findRecordByValue(value);
                    return '<table class="x-form-trigger-wrap" style="table-layout: fixed; width: 100%;border-collapse:collapse" cellpadding="0"><tr><td class="x-form-trigger-input-cell" style="width: 100%;"><div style="box-shadow:none;padding:2px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;cursor:text" >'+(value ? (rec ? rec.get(editor.displayField) : value) : '<span style="color:#999">Select alert type</span>')+'</div></td><td valign="top" class="x-trigger-cell"><div class="x-trigger-index-0 x-form-trigger x-form-arrow-trigger x-form-trigger-first"></div></td></tr></table>';
                }
            },{
                header: 'Threshold <img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qtip="% of quarterly budget"/>',//
                dataIndex: 'threshold',
                maxWidth: 120,
                flex: 1,
                resizable: false,
                editor: {
                    xtype: 'textfield',
                    editable: false,
                    margin: '0 12 0 13',
                    fixWidth: -25,
                    allowBlank: false,
                    //validateOnChange: false,
                    maskRe: new RegExp('[0123456789]', 'i')
                },
                renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                    var column = grid.panel.columns[colIndex],
                        editor = column.getEditor(record);
                    return  '<div class="x-form-text" style="background:#fff;padding:2px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;cursor:text" >'+
                                (value || '<span style="color:#999">' + (editor.emptyText || '') + '</span>') +
                            '</div>';
                }
            },{
                header: 'Recipient',
                xtype: 'buttongroupcolumn',
                dataIndex: 'recipientType',
                width: 230,
                resizable: false,
                buttons: [{
                    text: 'CC lead',
                    value: '1',
                    width: 105
                },{
                    text: 'Email',
                    value: '2',
                    width: 105
                }]
            },{
                sortable: false,
                resizable: false,
                dataIndex: 'emails',
                flex: 1,
                editor: {
                    xtype: 'textfield',
                    emptyText: 'Add one or more emails',
                    editable: false,
                    margin: '0 12 0 13',
                    fixWidth: -25,
                    //validateOnChange: false,
                    allowBlank: false,
                    validator: function(value) {
                        if (value) {
                            var ar = value.split(','), i, errors = [];
                            for (i = 0; i < ar.length; i++) {
                                if (! Ext.form.field.VTypes.email(ar[i]))
                                    errors.push(ar[i]);
                            }

                            if (errors.length)
                                return 'You\'ve entered not valid emails: ' + errors.join(', ');
                        }
                        return true;
                    }
                },
                isEditable: function(record) {
                    return record.get('recipientType') == 2;
                },
                renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                    var column = grid.panel.columns[colIndex],
                        editor = column.getEditor(record);
                    return  record.get('recipientType') == 1 ? '' :
                            '<div class="x-form-text" style="background:#fff;padding:2px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;cursor:text"  data-qtip="'+Ext.String.htmlEncode((value || '').replace(',', '<br/>'))+'">'+
                                (value || '<span style="color:#999">' + editor.emptyText + '</span>') +
                            '</div>';
                }
            },{
                xtype: 'statuscolumn',
                header: 'Status',
                statustype: 'costanalyticsnotification',
                resizable: false,
                maxWidth: 90
            }],
            dockedItems: [{
                xtype: 'toolbar',
                dock: 'top',
                style: 'box-shadow: none;padding-left:0',
                //margin: '12 0 0 0',
                defaults: {
                    margin: '0 0 0 12',
                    handler: function(comp) {
                        var grid = comp.up('grid'),
                            selection = grid.getSelectionModel().getSelection();
                        if (comp.itemId === 'delete') {
                            grid.store.remove(selection);
                        } else {
                            Ext.each(selection, function(record){
                                record.set('status', comp.itemId === 'enable');
                            });
                        }
                    }
                },
                items: [{
                    xtype: 'component',
                    html: 'Schedule email alerts based on budget usage and overspend',
                    margin: 0
                },{
                    xtype: 'tbfill',
                    margin: 0
                },{
                    itemId: 'enable',
                    ui: 'paging',
                    iconCls: 'x-tbar-activate',
                    disabled: true,
                    tooltip: 'Enable selected items'
                },{
                    itemId: 'disable',
                    ui: 'paging',
                    iconCls: 'x-tbar-suspend',
                    disabled: true,
                    tooltip: 'Disable selected items'
                },{
                    itemId: 'delete',
                    ui: 'paging',
                    iconCls: 'x-tbar-delete',
                    disabled: true,
                    tooltip: 'Delete selected items'
                }]
            }]
        },{
            xtype: 'grid',
            itemId: 'reports',
            hidden: true,
            cls: 'x-grid-shadow x-grid-no-highlighting x-costanalytics-notifications-grid',
            selModel: {
                selType: 'selectedmodel'
            },
            store: {
                fields: ['uuid', 'subjectType', 'subjectId', {name: 'period', defaultValue: 3}, 'emails', 'status'],
                proxy: 'object'
            },
            features: {
                ftype: 'addbutton',
                text: 'Add new report',
                handler: function(view) {
                    var grid = view.up();
                    grid.store.add({subjectType: 1, subjectId: loadParams['ccId'], status: 1});
                }
            },
            plugins: [
                Ext.create('Ext.grid.plugin.CellEditing', {
                    pluginId: 'cellediting',
                    clicksToEdit: 1,
                    listeners: {
                        beforeedit: function(editor, o) {
                            if (o.column.isEditable) {
                                return o.column.isEditable(o.record);
                            }
                        }
                    }
                })
            ],
             setValues: function(id, data) {
                this.store.load({data: data});
            },
            getValues: function() {
                var grid = this,
                    store = grid.store,
                    items = [],
                    error,
                    cellEditing = grid.getPlugin('cellediting');
                (store.snapshot || store.data).each(function(record){
                    var colIndex;
                    if (!record.get('emails')) {
                        colIndex = 1;
                    }
                    if (colIndex !== undefined) {
                        cellEditing.startEdit(record, colIndex);
                        Ext.Function.defer(cellEditing.context.column.field.validate, 2, cellEditing.context.column.field);
                        error = true;
                        return false;
                    } else {
                        items.push(record.getData());
                    }
                });
                return !error ? {items: items} : false;
            },
            listeners: {
                selectionchange: function(selModel, selected) {
                    this.down('#delete').setDisabled(!selected.length);
                    this.down('#enable').setDisabled(!selected.length);
                    this.down('#disable').setDisabled(!selected.length);
                }
            },
            columns: [{
                header: 'Frequency',
                dataIndex: 'period',
                width: 130,
                tdCls: 'x-form-trigger-wrap',
                editor: {
                    xtype: 'combo',
                    store: [[1, 'Daily'],[2, 'Weekly'],[3, 'Monthly'],[4, 'Quarterly']],
                    value: 1,
                    fixWidth: -25,
                    editable: false,
                    margin: '0 12 0 13'
                },
                renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                    var column = grid.panel.columns[colIndex],
                        editor = column.getEditor(record),
                        rec = editor.findRecordByValue(value);
                    return '<table class="x-form-trigger-wrap" style="table-layout: fixed; width: 100%;border-collapse:collapse" cellpadding="0"><tr><td class="x-form-trigger-input-cell" style="width: 100%;"><div style="box-shadow:none;padding:2px 12px 3px 13px" >'+(value ? (rec ? rec.get(editor.displayField) : value) : '<span style="color:#999"></span>')+'</div></td><td valign="top" class="x-trigger-cell"><div class="x-trigger-index-0 x-form-trigger x-form-arrow-trigger x-form-trigger-first"></div></td></tr></table>';
                }
            },{
                header: 'Recipient',
                sortable: false,
                resizable: false,
                dataIndex: 'emails',
                flex: 1,
                editor: {
                    xtype: 'textfield',
                    emptyText: 'Add one or more emails',
                    editable: false,
                    margin: '0 12 0 13',
                    fixWidth: -25,
                    //validateOnChange: false,
                    allowBlank: false,
                    validator: function(value) {
                        if (value) {
                            var ar = value.split(','), i, errors = [];
                            for (i = 0; i < ar.length; i++) {
                                if (! Ext.form.field.VTypes.email(ar[i]))
                                    errors.push(ar[i]);
                            }

                            if (errors.length)
                                return 'You\'ve entered not valid emails: ' + errors.join(', ');
                        }
                        return true;
                    }
                },
                renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                    var column = grid.panel.columns[colIndex],
                        editor = column.getEditor(record);
                    return  record.get('recipientType') == 1 ? '' :
                            '<div class="x-form-text" style="background:#fff;padding:2px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;cursor:text"  data-qtip="'+Ext.String.htmlEncode((value || '').replace(',', '<br/>'))+'">'+
                                (value || '<span style="color:#999">' + editor.emptyText + '</span>') +
                            '</div>';
                }
            },{
                xtype: 'statuscolumn',
                header: 'Status',
                statustype: 'costanalyticsnotification',
                resizable: false,
                maxWidth: 90
            }],
            dockedItems: [{
                xtype: 'toolbar',
                dock: 'top',
                style: 'box-shadow: none',
                defaults: {
                    margin: '0 0 0 12',
                    handler: function(comp) {
                        var grid = comp.up('grid'),
                            selection = grid.getSelectionModel().getSelection();
                        if (comp.itemId === 'delete') {
                            grid.store.remove(selection);
                        } else {
                            Ext.each(selection, function(record){
                                record.set('status', comp.itemId === 'enable');
                            });
                        }
                    }
                },
                items: [{
                    xtype: 'tbfill',
                    margin: 0
                },{
                    itemId: 'enable',
                    ui: 'paging',
                    iconCls: 'x-tbar-activate',
                    disabled: true,
                    tooltip: 'Enable selected items'
                },{
                    itemId: 'disable',
                    ui: 'paging',
                    iconCls: 'x-tbar-suspend',
                    disabled: true,
                    tooltip: 'Disable selected items'
                },{
                    itemId: 'delete',
                    ui: 'paging',
                    iconCls: 'x-tbar-delete',
                    disabled: true,
                    tooltip: 'Delete selected items'
                }]
            }]
        }],
		dockedItems: [{
            xtype: 'container',
            cls: 'x-container-fieldset',
            style: 'padding-bottom:0',
            dock: 'top',
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                html: 'Cost center notifications',

            },{
                xtype: 'buttongroupfield',
                value: 'notifications',
                itemId: 'notificationType',
                defaults: {
                    width: 150
                },
                items: [{
                    text: 'Budget alerts',
                    value: 'notifications'
                },{
                    text: 'Periodic reports',
                    value: 'reports'
                }],
                listeners: {
                    change: function(comp, value) {
                        if (value === 'notifications') {
                            form.down('#notifications').show().view.findFeature('addbutton').updateButtonPosition();
                            form.down('#reports').hide();
                        } else {
                            form.down('#notifications').hide();
                            form.down('#reports').show().view.findFeature('addbutton').updateButtonPosition();
                        }
                    }
                }
            }]
        },{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
                    var types = ['notifications', 'reports'],
                        notifications = {
                            notifications: form.down('#notifications').getValues(),
                            reports: form.down('#reports').getValues()
                        };
                    Ext.each(types, function(type){
                        notifications[type] = form.down('#' + type).getValues();
                        if (!notifications[type]) {
                            form.down('#notificationType').setValue(type);
                            notifications = false;
                            return false;
                        }
                    });

                    if (!notifications) return;

                    Scalr.Request({
                        processBox: {
                            type: 'save'
                        },
                        url: '/analytics/costcenters/xSaveNotifications/',
                        params: {
                            notifications: Ext.encode(notifications)
                        },
                        success: function (data) {
                            Ext.each(types, function(type){
                                form.down('#' + type).store.load({data: data[type]});
                            });
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
    form.down('#notifications').store.load({data: moduleParams['notifications']});
    form.down('#reports').store.load({data: moduleParams['reports']});
	return form;
});

Scalr.regPage('Scalr.ui.analytics.admin.notifications.view', function (loadParams, moduleParams) {
    var maxFormWidth = 1120;

	var panel = Ext.create('Ext.panel.Panel', {
        cls: 'x-costanalytics',
		layout: 'fit',
		scalrOptions: {
			title: 'Notifications',
			reload: true,
			maximize: 'all',
            leftMenu: {
                menuId: 'analytics',
                itemId: 'notifications'
            }
		},
        editOption: function(id, data){
            var formsCt = this.down('#forms'),
                id, item;
            this.currentId = id;
            id = id.split('.');
            item = formsCt.down('#' + id[0]);
            formsCt.suspendLayouts();
            item.setValues(id, data);
            formsCt.layout.setActiveItem(item);
            formsCt.resumeLayouts(true);
            this.show();
        },
        saveOption: function(){
            var me = this,
                newData,
                notifications = {},
                grid = this.down('#forms').layout.getActiveItem();
            newData = grid.getValues();
            if (!newData) return;
            
            notifications[me.currentId] = newData;

            Scalr.Request({
                processBox: {
                    type: 'save'
                },
                url: '/analytics/notifications/xSave/',
                params: {
                    notifications: Ext.encode(notifications)
                },
                success: function (data) {
                    moduleParams[me.currentId] = data[me.currentId];
                    grid.store.load({data: data[me.currentId]});
                }
            });
        },
		items: [{
            xtype: 'container',
            itemId: 'forms',
            flex: 1,
            layout: 'card',
            items: [{
                xtype: 'grid',
                itemId: 'notifications',
                selModel: {
                    selType: 'selectedmodel'
                },
                flex: 1,
                maxWidth: maxFormWidth,
                cls: 'x-grid-shadow x-grid-no-highlighting x-container-fieldset x-costanalytics-notifications-grid',
                store: {
                    fields: ['uuid', 'subjectType', {name: 'subjectId', convert: function(v, record){return v || ''}}, 'notificationType', 'threshold', {name: 'recipientType', defaultValue: 1}, 'emails', 'status'],
                    proxy: 'object'
                },
                features: {
                    ftype: 'addbutton',
                    text: 'Add new alert',
                    handler: function(view) {
                        var grid = view.up();
                        grid.store.add({subjectType: grid.up('panel').currentId === 'notifications.ccs' ? 1 : 2, status: 1});
                    }
                },
                plugins: [
                    Ext.create('Ext.grid.plugin.CellEditing', {
                        pluginId: 'cellediting',
                        clicksToEdit: 1,
                        listeners: {
                            beforeedit: function(editor, o) {
                                if (o.column.dataIndex === 'subjectId') {
                                    var emptyText,
                                        columnEditor = o.column.getEditor(),
                                        subjectType = o.record.get('subjectType');
                                    if (subjectType == 1) {
                                        emptyText = 'All cost centers';
                                    } else if (subjectType == 2) {
                                        emptyText = 'All projects';
                                    }
                                    columnEditor.store.load({data: {'': emptyText}});
                                    columnEditor.store.load({data: moduleParams[subjectType == 1 ? 'ccs' : 'projects'], addRecords: true});
                                    columnEditor.emptyText = emptyText;
                                    columnEditor.applyEmptyText();
                                }
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
                    this.columns[3].buttons[0].text = id[1] == 'ccs' ? 'CC lead' : 'Project lead';
                    this.store.load({data: data});
                },
                getValues: function() {
                    var grid = this,
                        store = grid.store,
                        items = [],
                        error,
                        record,
                        singleEmptyRecord = false,
                        cellEditing = grid.getPlugin('cellediting');
                    if (store.getCount() == 1) {
                        record = store.first();
                        if (!record.get('uuid') && !record.get('notificationType') && !record.get('threshold')) {
                            singleEmptyRecord = true;
                        }
                    }

                    if (!singleEmptyRecord) {
                        (store.snapshot || store.data).each(function(record){
                            var colIndex;
                            if (!record.get('notificationType')) {
                                colIndex = 0;
                            } else if (!record.get('threshold')) {
                                colIndex = 1;
                            } else if (record.get('recipientType') == 2 && !record.get('emails')) {
                                colIndex = 4;
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
                    }
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
                    header: 'Subject',
                    dataIndex: 'subjectId',
                    width: 160,
                    tdCls: 'x-form-trigger-wrap',
                    editor: {
                        xtype: 'combo',
                        store: {
                            fields: ['id', 'name'],
                            proxy: 'object',
                            sorters: [{
                                sorterFn: function(rec1, rec2) {
                                    if (!rec1.data.id) {
                                        return -1;
                                    } else if (!rec2.data.id) {
                                        return 1;
                                    } else {
                                        return 0;
                                    }
                                }
                            },{
                                property: 'name'
                            }]
                        },
                        valueField: 'id',
                        displayField: 'name',
                        //allowBlank: false,
                        editable: false,
                        //forceSelection: true,
                        fixWidth: -25,
                        margin: '0 12 0 13',
                        queryMode: 'local',
                        listConfig: {
                            cls: 'x-boundlist-alt',
                            tpl:
                                '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                    '<div style="white-space:nowrap;">{name}</div>' +
                                '</div></tpl>'
                        }
                    },
                    isEditable: function(record) {
                        return record.get('subjectType')>0;
                    },
                    renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                        var column = grid.panel.columns[colIndex],
                            editor = column.getEditor(record),
                            subjectType = record.get('subjectType'),
                            emptyText,
                            data,
                            rec;
                        data = moduleParams[subjectType == 1 ? 'ccs' : 'projects'];
                        if (subjectType == 1) {
                            emptyText = 'All cost centers';
                        } else {
                            emptyText = 'All projects';
                        }
                        return '<table class="x-form-trigger-wrap" style="table-layout: fixed; width: 100%;border-collapse:collapse" cellpadding="0"><tr><td class="x-form-trigger-input-cell" style="width: 100%;"><div style="box-shadow:none;padding:2px 0 3px 13px;overflow:hidden;text-overflow:ellipsis" >'+(value ? (data[value]|| value) : '<span style="color:#999">' + emptyText + '</span>')+'</div></td><td valign="top" class="x-trigger-cell"><div class="x-trigger-index-0 x-form-trigger x-form-arrow-trigger x-form-trigger-first"></div></td></tr></table>';
                    }
                },{
                    header: 'Recipient',
                    xtype: 'buttongroupcolumn',
                    dataIndex: 'recipientType',
                    width: 230,
                    resizable: false,
                    buttons: [{
                        text: 'Project lead',
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
            },{
                xtype: 'grid',
                itemId: 'reports',
                flex: 1,
                maxWidth: maxFormWidth,
                cls: 'x-grid-shadow x-grid-no-highlighting x-container-fieldset x-costanalytics-notifications-grid',
                selModel: {
                    selType: 'selectedmodel'
                },
                store: {
                    fields: ['uuid', {name: 'subjectType', convert: function(v, record){return v || -1}}, {name: 'subjectId', convert: function(v, record){return v || ''}}, {name: 'period', defaultValue: 3}, 'emails', 'status'],
                    proxy: 'object'
                },
                features: {
                    ftype: 'addbutton',
                    text: 'Add new report',
                    handler: function(view) {
                        var grid = view.up();
                        grid.store.add({status: 1});
                    }
                },
                plugins: [
                    Ext.create('Ext.grid.plugin.CellEditing', {
                        pluginId: 'cellediting',
                        clicksToEdit: 1,
                        listeners: {
                            beforeedit: function(editor, o) {
                                if (o.column.dataIndex === 'subjectId') {
                                    var emptyText,
                                        columnEditor = o.column.getEditor(),
                                        subjectType = o.record.get('subjectType');
                                    if (subjectType == 1) {
                                        emptyText = 'All cost centers';
                                    } else if (subjectType == 2) {
                                        emptyText = 'All projects';
                                    }
                                    columnEditor.store.load({data: {'': emptyText}});
                                    columnEditor.store.load({data: moduleParams[subjectType == 1 ? 'ccs' : 'projects'], addRecords: true});
                                    columnEditor.emptyText = emptyText;
                                    columnEditor.applyEmptyText();
                                }
                                if (o.column.isEditable) {
                                    return o.column.isEditable(o.record);
                                }
                            },
                            validateedit: function(editor, o) {
                                if (o.column.dataIndex === 'subjectType') {
                                    if (o.value != o.record.get('subjectType')) {
                                        o.record.data['subjectId'] = null;
                                    }
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
                        record,
                        singleEmptyRecord = false,
                        cellEditing = grid.getPlugin('cellediting');
                    if (store.getCount() == 1) {
                        record = store.first();
                        if (!record.get('uuid') && !record.get('subjectType')) {
                            singleEmptyRecord = true;
                        }
                    }

                    if (!singleEmptyRecord) {
                        (store.snapshot || store.data).each(function(record){
                            var colIndex;
                            if (!record.get('subjectType')) {
                                colIndex = 0;
                            } else if (!record.get('emails')) {
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
                    }
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
                    header: 'Report type',
                    dataIndex: 'subjectType',
                    width: 200,
                    tdCls: 'x-form-trigger-wrap',
                    editor: {
                        xtype: 'combo',
                        store: {
                            fields: ['id', 'name', 'description'],
                            data: [{
                                id: -1, name: 'Total summary', description: 'Report summarizes spend for ALL cost centers'
                            },{
                                id: 1, name: 'Cost center', description: 'Report summarizes the spend/budget for individual cost centers'
                            },{
                                id: 2, name: 'Project', description: 'Report summarizes the spend/budget for individual projects'
                            }]
                        },
                        emptyText: 'Please select report',
                        allowBlank: false,
                        editable: false,
                        fixWidth: -25,
                        margin: '0 12 0 13',
                        valueField: 'id',
                        displayField: 'name',
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
                        return '<table class="x-form-trigger-wrap" style="table-layout: fixed; width: 100%;border-collapse:collapse" cellpadding="0"><tr><td class="x-form-trigger-input-cell" style="width: 100%;"><div style="box-shadow:none;padding:2px 12px 3px 13px" >'+(value ? (rec ? rec.get(editor.displayField) : value) : '<span style="color:#999">Please select report</span>')+'</div></td><td valign="top" class="x-trigger-cell"><div class="x-trigger-index-0 x-form-trigger x-form-arrow-trigger x-form-trigger-first"></div></td></tr></table>';
                    }
                },{
                    header: 'Subject',
                    dataIndex: 'subjectId',
                    width: 200,
                    tdCls: 'x-form-trigger-wrap',
                    editor: {
                        xtype: 'combo',
                        store: {
                            fields: ['id', 'name'],
                            proxy: 'object',
                            sorters: [{
                                sorterFn: function(rec1, rec2) {
                                    if (!rec1.data.id) {
                                        return -1;
                                    } else if (!rec2.data.id) {
                                        return 1;
                                    } else {
                                        return 0;
                                    }
                                }
                            },{
                                property: 'name'
                            }]
                        },
                        valueField: 'id',
                        displayField: 'name',
                        //allowBlank: false,
                        editable: false,
                        //forceSelection: true,
                        fixWidth: -25,
                        margin: '0 12 0 13',
                        queryMode: 'local',
                        listConfig: {
                            cls: 'x-boundlist-alt',
                            tpl:
                                '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                    '<div style="white-space:nowrap;">{name}</div>' +
                                '</div></tpl>'
                        }
                    },
                    isEditable: function(record) {
                        return record.get('subjectType')>0;
                    },
                    renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                        var column = grid.panel.columns[colIndex],
                            editor = column.getEditor(record),
                            subjectType = record.get('subjectType'),
                            emptyText,
                            data,
                            rec;
                        if (subjectType > 0) {
                            data = moduleParams[subjectType == 1 ? 'ccs' : 'projects'];
                            if (subjectType == 1) {
                                emptyText = 'All cost centers';
                            } else {
                                emptyText = 'All projects';
                            }
                            return '<table class="x-form-trigger-wrap" style="table-layout: fixed; width: 100%;border-collapse:collapse" cellpadding="0"><tr><td class="x-form-trigger-input-cell" style="width: 100%;"><div style="box-shadow:none;padding:2px 0 3px 13px;overflow:hidden;text-overflow:ellipsis" >'+(value ? (data[value]|| value) : '<span style="color:#999">' + emptyText + '</span>')+'</div></td><td valign="top" class="x-trigger-cell"><div class="x-trigger-index-0 x-form-trigger x-form-arrow-trigger x-form-trigger-first"></div></td></tr></table>';
                        } else {
                            return '<span style="color:#999;line-height:24px">All cost centers summary</span>';
                        }
                    }
                },{
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
            }]
        }],
        listeners: {
            selectnotificationtype: function(type) {
                panel.editOption(type, moduleParams[type]);
            },
            afterrender: function (me) {
                panel.getDockedComponent('tabs').items.first().toggle(true);
            }
        },
        dockedItems:[{
            xtype: 'container',
            itemId: 'tabs',
            dock: 'left',
            width: 200,
            overflowY: 'auto',
            overflowX: 'hidden',
            padding: '12 0',
            weight: 2,
            style: 'background-color: #DFE4EA;',
            defaults: {
                xtype: 'button',
                ui: 'tab',
                cls: 'x-btn-tab-small-light',
                toggleGroup: 'analytics-notifications-tabs',
                textAlign: 'left',
                allowDepress: false,
                disableMouseDownPressed: true,
                pressed: false,
                toggleHandler: function (me, state) {
                    if (state) {
                        panel.fireEvent('selectnotificationtype', me.value);
                    }
                }
            },
            items: [{
                text: 'Cost center budget alerts',
                value: 'notifications.ccs'
            },{
                text: 'Project budget alerts',
                value: 'notifications.projects'
            },{
                text: 'Periodic reports',
                value: 'reports'
            }]
        },{
            xtype: 'container',
            itemId: 'toolbar',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            //maxWidth: maxFormWidth,
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                itemId: 'save',
                text: 'Save',
                handler: function() {
                    panel.saveOption();
                }
            }, {
                xtype: 'button',
                itemId: 'cancel',
                text: 'Cancel',
                margin: '0 0 0 24',
                disabled: true,
                handler: function() {
                    //panel.down('#options').getSelectionModel().deselectAll();
                }
            }]
        }]

	});

	return panel;
});

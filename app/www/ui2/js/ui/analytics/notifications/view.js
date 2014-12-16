Scalr.regPage('Scalr.ui.analytics.notifications.view', function (loadParams, moduleParams) {
    var maxFormWidth = 1020;
    var store = Ext.create('store.store', {
        fields: ['id', 'name', 'description', 'enabled', 'items', 'type'],
        proxy: 'object',
        data: [{
            id: 'notifications.ccs.enabled',
            name: 'Cost center budget alerts',
            type: 'notifications',
            description: 'Schedule email alerts for EVERY cost center.',
            enabled: moduleParams['notifications.ccs']['enabled'],
            items: moduleParams['notifications.ccs']['items']
        },{
            id: 'notifications.projects.enabled',
            name: 'Project budget alerts',
            type: 'notifications',
            description: 'Schedule email alerts for EVERY project.',
            enabled: moduleParams['notifications.projects']['enabled'],
            items: moduleParams['notifications.projects']['items']
        },{
            id: 'reports.enabled',
            name: 'Periodic reports',
            type: 'reports',
            description: 'Periodic reports description',
            enabled: moduleParams['reports']['enabled'],
            items: moduleParams['reports']['items']
        }]
    });
    
	var panel = Ext.create('Ext.panel.Panel', {
        cls: 'x-costanalytics',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			title: 'Notifications',
			reload: true,
			maximize: 'all',
            leftMenu: {
                menuId: 'analytics',
                itemId: 'notifications'
            }
		},
		items: [{
            xtype: 'grid',
            flex: 1,
            maxWidth: 360,
            itemId: 'options',
            padding: '12 0',
            cls: 'x-grid-shadow x-panel-column-left x-grid-with-formfields x-grid-no-selection',
            plugins: {
                ptype: 'focusedrowpointer',
                thresholdOffset: 30,
                addOffset: 4
            },
            store: store,
            columns: [{
                header: 'Notifications',
                dataIndex: 'name',
                flex: 1,
                sortable: false,
                resizable: false,
                renderer: function(value, meta){
                    return '<span style="font-weight:bold">' + value + '</span>';
                }
            },{
                xtype: 'statuscolumn',
                statustype: 'notification',
                header: 'Status',
                sortable: false,
                resizable: false,
                width: 100,
                minWidth: 100,
                align: 'center',
                padding: 2,
                qtipConfig: {
                    width: 310
                }
            }],
            listeners: {
                selectionchange: function(comp, selected){
                    if (selected.length > 0) {
                        panel.down('#rightcol').editOption(selected[0].getData());
                    } else {
                        panel.down('#rightcol').hide();
                    }
                }
            },
            saveOption: function(enabled){
                var newData,
                    rightcol = panel.down('#rightcol'),
                    record = this.getSelectionModel().getSelection()[0],
                    notifications = {};
                enabled = enabled !== undefined ? enabled : record.get('enabled');
                newData = rightcol.getOptionData(enabled);
                if (!newData) return;
                newData['enabled'] = enabled;
                record.set(newData);
                
                notifications[record.get('id')] = {
                    enabled: newData['enabled'],
                    items: newData['items']
                };

                rightcol.setToolbar(enabled);
                
                Scalr.Request({
                    processBox: {
                        type: 'save'
                    },
                    url: '/analytics/notifications/xSave/',
                    params: {
                        notifications: Ext.encode(notifications)
                    },
                    success: function () {}
                });
            }
		},{
            xtype: 'panel',
            itemId: 'rightcol',
            flex: 1,
            hidden: true,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            setTitle: function(header, subheader){
                this.getComponent('title').update('<div class="x-fieldset-header-text" style="float:none">'+header + '</div>' + (subheader? '<div class="x-fieldset-header-description">' + subheader + '</div>' : ''));
            },
            setToolbar: function(enabled){
                var toolbar = this.getDockedComponent('toolbar');
                toolbar.down('#save').setVisible(enabled == 1).enable();
                toolbar.down('#disable').setVisible(enabled == 1).enable();
                toolbar.down('#enable').setVisible(enabled != 1).enable();
            },
            disableButtons: function() {
                var toolbar = this.getDockedComponent('toolbar');
                toolbar.down('#save').disable();
                toolbar.down('#disable').disable();
                toolbar.down('#enable').disable();
            },
            editOption: function(option){
                var formsCt = this.down('#forms'), 
                    item = formsCt.down('#' + option['type']);
                this.setTitle(option['name'], option['description']);
                this.setToolbar(option['enabled']);
                formsCt.suspendLayouts();
                item.setValues(option);
                formsCt.layout.setActiveItem(item);
                formsCt.resumeLayouts(true);
                this.show();
            },
            getOptionData: function(enabled) {
                return this.down('#forms').layout.getActiveItem().getValues(enabled);
            },
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-header x-fieldset-separator-bottom',
                itemId: 'title'
            },{
                xtype: 'container',
                itemId: 'forms',
                flex: 1,
                layout: 'card',
                items: [{
                    xtype: 'grid',
                    itemId: 'notifications',
                    flex: 1,
                    maxWidth: maxFormWidth,
                    cls: 'x-grid-shadow x-grid-no-highlighting x-container-fieldset',
                    store: {
                        fields: ['uuid', 'notificationType', 'threshold', {name: 'recipientType', defaultValue: 1}, 'emails'],
                        proxy: 'object'
                    },
                    features: {
                        ftype: 'addbutton',
                        text: 'Add new alert',
                        handler: function(view) {
                            view.up().store.add({});
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
                        itemclick: function (view, record, item, index, e) {
                            if (e.getTarget('img.x-icon-action-delete')) {
                                var selModel = view.getSelectionModel();
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
                    setValues: function(data) {
                        this.columns[2].buttons[0].text = data['id'] == 'notifications.ccs.enabled' ? 'CC lead' : 'Project lead';
                        this.store.load({data: data['items']});
                        if (!this.store.getCount()) {
                            this.store.add({});
                        }
                    },
                    getValues: function(enabled) {
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
                        
                        if (singleEmptyRecord) {
                            if (enabled == 1) {
                                error = true;
                                cellEditing.startEdit(record, 0);
                            }
                        } else {
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
                                    cellEditing.context.column.field.validate();
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
                        width: 220,
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
                        header: '% of quarterly budget',
                        dataIndex: 'threshold',
                        maxWidth: 180,
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
                        renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                            return '<img style="cursor:pointer;margin-top:6px" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Delete alert" src="'+Ext.BLANK_IMAGE_URL+'"/>';
                        },
                        width: 42,
                        sortable: false,
                        dataIndex: 'uuid',
                        align:'left'
                    }]
                },{
                    xtype: 'grid',
                    itemId: 'reports',
                    flex: 1,
                    maxWidth: maxFormWidth,
                    cls: 'x-grid-shadow x-grid-no-highlighting x-container-fieldset',
                    store: {
                        fields: ['uuid', {name: 'subjectType', defaultValue: -1, convert: function(v, record){return v || -1}}, {name: 'subjectId', convert: function(v, record){return v || ''}}, {name: 'period', defaultValue: 3}, 'emails'],
                        proxy: 'object'
                    },
                    features: {
                        ftype: 'addbutton',
                        text: 'Add new report',
                        handler: function(view) {
                            view.up().store.add({});
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
                     setValues: function(data) {
                        this.store.load({data: data['items']});
                        if (!this.store.getCount()) {
                            this.store.add({});
                        }
                    },
                    getValues: function(enabled) {
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

                        if (singleEmptyRecord) {
                            if (enabled == 1) {
                                error = true;
                                cellEditing.startEdit(record, 0);
                            }
                        } else {
                            (store.snapshot || store.data).each(function(record){
                                var colIndex;
                                if (!record.get('subjectType')) {
                                    colIndex = 0;
                                } else if (!record.get('emails')) {
                                    colIndex = 3;
                                }
                                if (colIndex !== undefined) {
                                    cellEditing.startEdit(record, colIndex);
                                    cellEditing.context.column.field.validate();
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
                        itemclick: function (view, record, item, index, e) {
                            if (e.getTarget('img.x-icon-action-delete')) {
                                var selModel = view.getSelectionModel();
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
                        renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                            return '<img style="cursor:pointer;margin-top:6px" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Delete alert" src="'+Ext.BLANK_IMAGE_URL+'"/>';
                        },
                        width: 42,
                        sortable: false,
                        dataIndex: 'uuid',
                        align:'left'
                    }]
                }]
            }],
            dockedItems:[{
                xtype: 'container',
                itemId: 'toolbar',
                dock: 'bottom',
                cls: 'x-docked-buttons',
                maxWidth: maxFormWidth,
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                items: [{
                    xtype: 'button',
                    itemId: 'save',
                    text: 'Save',
                    handler: function() {
                        panel.down('#options').saveOption();
                    }
                }, {
                    xtype: 'button',
                    itemId: 'enable',
                    text: 'Save & Enable',
                    handler: function() {
                        panel.down('#options').saveOption(1);
                    }
                }, {
                    xtype: 'button',
                    itemId: 'disable',
                    text: 'Disable',
                    handler: function() {
                        panel.down('#options').saveOption(0);
                    }
                }, {
                    xtype: 'button',
                    itemId: 'cancel',
                    text: 'Cancel',
                    margin: '0 0 0 24',
                    handler: function() {
                        panel.down('#options').getSelectionModel().deselectAll();
                    }
                }]
            }]

        }]
	});

	return panel;
});

Scalr.regPage('Scalr.ui.admin.analytics.notifications.view', function (loadParams, moduleParams) {
    var maxFormWidth = 1120;

    var panel = Ext.create('Ext.panel.Panel', {
        cls: 'x-panel-column-left x-panel-column-left-with-tabs x-costanalytics',
        layout: 'fit',
        scalrOptions: {
            reload: true,
            maximize: 'all',
            menuTitle: 'Cost analytics',
            menuSubTitle: 'Notifications',
            menuHref: '#/admin/analytics/dashboard',
            menuParentStateId: 'panel-admin-analytics',
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
                url: '/admin/analytics/notifications/xSave/',
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
                selModel: 'selectedmodel',
                flex: 1,
                maxWidth: maxFormWidth,
                cls: 'x-grid-no-highlighting x-container-fieldset x-costanalytics-notifications-grid x-grid-with-formfields',
                store: {
                    fields: [{name: 'uuid', defaultValue: ''}, 'subjectType', {name: 'subjectId', convert: function(v, record){return v || ''}}, 'notificationType', 'threshold', {name: 'recipientType', defaultValue: 1}, 'emails', 'status'],
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
                        error;
                    store.getUnfiltered().each(function(record){
                        var colIndex;
                        if (!record.get('notificationType')) {
                            colIndex = 0;
                        } else if (!record.get('threshold')) {
                            colIndex = 1;
                        } else if (record.get('recipientType') == 2) {
                            var widget = grid.columns[4].getWidget(record);//email column
                            if (widget && !widget.validate()) {
                                widget.focus();
                                error = true;
                                return false;
                            }
                        }
                        if (colIndex !== undefined) {
                            var widget = grid.columns[colIndex].getWidget(record);
                            widget.validate();
                            widget.focus();
                            error = true;
                            return false;
                        } else {
                            var data = record.getData();
                            delete data.id;
                            items.push(data);
                        }
                    });
                    return !error ? {items: items} : false;
                },
                columns: [{
                    text: 'Alert type',
                    xtype: 'widgetcolumn',
                    dataIndex: 'notificationType',
                    width: 210,
                    widget: {
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
                        allowBlank: false,
                        editable: false,
                        valueField: 'id',
                        displayField: 'name',
                        listConfig: {
                            cls: 'x-boundlist-alt',
                            tpl:
                                '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                    '<b>{name}</b>' +
                                    '<div style="line-height: 26px;white-space:nowrap;">{description}</div>' +
                                '</div></tpl>'
                        },
                        listeners: {
                            change: function(comp, value){
                                var record = comp.getWidgetRecord();
                                if (record) {
                                    record.set('notificationType', value);
                                }
                            }
                        }
                    }
                },{
                    text: 'Threshold <img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qtip="% of quarterly budget"/>',//
                    dataIndex: 'threshold',
                    maxWidth: 120,
                    flex: 1,
                    resizable: false,
                    xtype: 'widgetcolumn',
                    widget: {
                        xtype: 'textfield',
                        allowBlank: false,
                        maskRe: new RegExp('[0123456789]', 'i'),
                        listeners: {
                            change: function(comp, value){
                                var record = comp.getWidgetRecord();
                                if (record) {
                                    record.set('threshold', value);
                                }
                            }
                        }
                    }
                },{
                    text: 'Subject',
                    xtype: 'widgetcolumn',
                    dataIndex: 'subjectId',
                    width: 210,
                    onWidgetAttach: function(column, widget, record) {
                        widget.setSubjectType(record.get('subjectType'));
                    },
                    widget: {
                        xtype: 'combo',
                        store: {
                            model: Scalr.getModel({fields: ['id', 'name']}),
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
                        editable: false,
                        queryMode: 'local',
                        listConfig: {
                            cls: 'x-boundlist-alt',
                            tpl:
                                '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                    '<div style="white-space:nowrap;">{name}</div>' +
                                '</div></tpl>'
                        },
                        setSubjectType: function(subjectType) {
                            var emptyText;
                            emptyText = subjectType == 1 ? 'All cost centers' : 'All projects';
                            this.store.load({data: {'': emptyText}});
                            this.store.load({data: moduleParams[subjectType == 1 ? 'ccs' : 'projects'], addRecords: true});
                            this.setReadOnly(false);
                            this.emptyText = emptyText;
                            this.applyEmptyText();

                        },
                        listeners: {
                            change: function(comp, value){
                                var record = comp.getWidgetRecord();
                                if (record) {
                                    record.set('subjectId', value);
                                }
                            }
                        }
                    }
                },{
                    text: 'Recipient',
                    xtype: 'widgetcolumn',
                    dataIndex: 'recipientType',
                    width: 140,
                    resizable: false,
                    align: 'center',
                    widget: {
                        xtype: 'buttongroupfield',
                        defaults: {
                            flex: 1
                        },
                        items: [{
                            text: 'Lead',
                            value: '1'
                        },{
                            text: 'Email',
                            value: '2'
                        }],
                        listeners: {
                            change: function(comp, value){
                                var record = comp.getWidgetRecord();
                                if (record) {
                                    record.set('recipientType', value);
                                    comp.getWidgetColumn().up('grid').columns[4].getWidget(record).setRecipientType(value);
                                }
                            }
                        }
                    }
                },{
                    sortable: false,
                    resizable: false,
                    dataIndex: 'emails',
                    flex: 1,
                    xtype: 'widgetcolumn',
                    onWidgetAttach: function(column, widget, record) {
                        widget.setRecipientType(record.get('recipientType'));
                    },
                    widget: {
                        xtype: 'textfield',
                        emptyText: 'Add one or more emails',
                        allowBlank: false,
                        style: 'width:100%',
                        setRecipientType: function(recipientType) {
                            this.setVisible(recipientType != 1);
                        },
                        validator: function(value) {
                            if (value) {
                                var ar = value.split(','), i, errors = [];
                                ar = Ext.Array.map(ar, Ext.String.trim);
                                for (i = 0; i < ar.length; i++) {
                                    if (! Ext.form.field.VTypes.email(ar[i]))
                                        errors.push(ar[i]);
                                }

                                if (errors.length)
                                    return 'You\'ve entered not valid emails: ' + errors.join(', ');
                            }
                            return true;
                        },
                        listeners: {
                            change: function(comp, value){
                                var record = comp.getWidgetRecord();
                                if (record) {
                                    record.set('emails', value);
                                }
                            }
                        }
                    }
                },{
                    xtype: 'statuscolumn',
                    text: 'Status',
                    statustype: 'costanalyticsnotification',
                    resizable: false,
                    width: 90,
                    minWidth: 90
                }],
                dockedItems: [{
                    xtype: 'toolbar',
                    dock: 'top',
                    ui: 'inline',
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
                        iconCls: 'x-btn-icon-activate',
                        disabled: true,
                        tooltip: 'Enable selected items'
                    },{
                        itemId: 'disable',
                        iconCls: 'x-btn-icon-suspend',
                        disabled: true,
                        tooltip: 'Disable selected items'
                    },{
                        itemId: 'delete',
                        iconCls: 'x-btn-icon-delete',
                        cls: 'x-btn-red',
                        disabled: true,
                        tooltip: 'Delete selected items'
                    }]
                }]
            },{
                xtype: 'grid',
                itemId: 'reports',
                flex: 1,
                maxWidth: maxFormWidth,
                trackMouseOver: false,
                cls: 'x-grid-no-highlighting x-container-fieldset x-costanalytics-notifications-grid x-grid-with-formfields',
                selModel: {
                    selType: 'selectedmodel'
                },
                store: {
                    fields: [{name: 'uuid', defaultValue: ''}, {name: 'subjectType', convert: function(v, record){return v || -1}}, {name: 'subjectId', convert: function(v, record){return v || ''}}, {name: 'period', defaultValue: 3}, 'emails', 'status'],
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
                setValues: function(id, data) {
                    this.store.load({data: data});
                },
                getValues: function() {
                    var grid = this,
                        store = grid.store,
                        items = [],
                        error;
                    store.getUnfiltered().each(function(record){
                        var colIndex;
                        if (!record.get('subjectType')) {
                            colIndex = 0;
                        } else {
                            var widget = grid.columns[3].getWidget(record);//email column
                            if (widget && !widget.validate()) {
                                widget.focus();
                                error = true;
                                return false;
                            }
                        }
                        if (colIndex !== undefined) {
                            var widget = grid.columns[colIndex].getWidget(record);
                            widget.validate();
                            widget.focus();
                            error = true;
                            return false;
                        } else {
                            var data = record.getData();
                            delete data.id;
                            items.push(data);
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
                    text: 'Report type',
                    xtype: 'widgetcolumn',
                    dataIndex: 'subjectType',
                    width: 200,
                    widget: {
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
                        valueField: 'id',
                        displayField: 'name',
                        listConfig: {
                            cls: 'x-boundlist-alt',
                            tpl:
                                '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                    '<b>{name}</b>' +
                                    '<div style="line-height: 26px;white-space:nowrap;">{description}</div>' +
                                '</div></tpl>'
                        },
                        listeners: {
                            change: function(comp, value){
                                var record = comp.getWidgetRecord();
                                if (record) {
                                    record.set('subjectType', value);
                                    comp.getWidgetColumn().up('grid').columns[1].getWidget(record).setSubjectType(value, true);
                                }
                            }
                        }
                    }
                },{
                    text: 'Subject',
                    xtype: 'widgetcolumn',
                    dataIndex: 'subjectId',
                    width: 210,
                    onWidgetAttach: function(column, widget, record) {
                        widget.setSubjectType(record.get('subjectType'));
                    },
                    widget: {
                        xtype: 'combo',
                        store: {
                            model: Scalr.getModel({fields: ['id', 'name']}),
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
                        editable: false,
                        queryMode: 'local',
                        listConfig: {
                            cls: 'x-boundlist-alt',
                            tpl:
                                '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                    '<div style="white-space:nowrap;">{name}</div>' +
                                '</div></tpl>'
                        },
                        setSubjectType: function(subjectType, reset) {
                            var emptyText;
                            if (reset) this.reset();
                            if (subjectType == -1) {
                                this.store.removeAll();
                                emptyText = 'All cost centers summary';
                                this.setReadOnly(true);
                            } else {
                                emptyText = subjectType == 1 ? 'All cost centers' : 'All projects';
                                this.store.load({data: {'': emptyText}});
                                this.store.load({data: moduleParams[subjectType == 1 ? 'ccs' : 'projects'], addRecords: true});
                                this.setReadOnly(false);
                            }
                            this.emptyText = emptyText;
                            this.applyEmptyText();

                        },
                        listeners: {
                            change: function(comp, value){
                                var record = comp.getWidgetRecord();
                                if (record) {
                                    record.set('subjectId', value);
                                }
                            }
                        }
                    }
                },{
                    text: 'Frequency',
                    dataIndex: 'period',
                    xtype: 'widgetcolumn',
                    width: 130,
                    widget: {
                        xtype: 'combo',
                        store: [[1, 'Daily'],[2, 'Weekly'],[3, 'Monthly'],[4, 'Quarterly']],
                        value: 1,
                        editable: false,
                        listeners: {
                            change: function(comp, value){
                                var record = comp.getWidgetRecord();
                                if (record) {
                                    record.set('period', value);
                                }
                            }
                        }
                    }
                },{
                    text: 'Recipient',
                    dataIndex: 'emails',
                    xtype: 'widgetcolumn',
                    flex: 1,
                    widget: {
                        xtype: 'textfield',
                        emptyText: 'Add one or more emails',
                        allowBlank: false,
                        validator: function(value) {
                            if (value) {
                                var ar = value.split(','), i, errors = [];
                                ar = Ext.Array.map(ar, Ext.String.trim);
                                for (i = 0; i < ar.length; i++) {
                                    if (! Ext.form.field.VTypes.email(ar[i]))
                                        errors.push(ar[i]);
                                }

                                if (errors.length)
                                    return 'You\'ve entered not valid emails: ' + errors.join(', ');
                            }
                            return true;
                        },
                        listeners: {
                            change: function(comp, value){
                                var record = comp.getWidgetRecord();
                                if (record) {
                                    record.set('emails', value);
                                }
                            }
                        }
                    }
                },{
                    xtype: 'statuscolumn',
                    text: 'Status',
                    statustype: 'costanalyticsnotification',
                    resizable: false,
                    width: 90,
                    minWidth: 90
                }],
                dockedItems: [{
                    xtype: 'toolbar',
                    dock: 'top',
                    ui: 'inline',
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
                        iconCls: 'x-btn-icon-activate',
                        disabled: true,
                        tooltip: 'Enable selected items'
                    },{
                        itemId: 'disable',
                        iconCls: 'x-btn-icon-suspend',
                        disabled: true,
                        tooltip: 'Disable selected items'
                    },{
                        itemId: 'delete',
                        iconCls: 'x-btn-icon-delete',
                        cls: 'x-btn-red',
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
            width: 210,
            overflowY: 'auto',
            overflowX: 'hidden',
            weight: 2,
            cls: 'x-docked-tabs x-docked-tabs-light',
            defaults: {
                xtype: 'button',
                ui: 'tab',
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

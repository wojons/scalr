Scalr.regPage('Scalr.ui.admin.analytics.projects.notifications', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			modal: true
		},
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 80
		},
		width: 960,
        bodyCls: 'x-container-fieldset',
        layout: 'fit',
        items: [{
            xtype: 'grid',
            itemId: 'notifications',
            selModel: 'selectedmodel',
            cls: 'x-grid-shadow x-grid-no-highlighting x-costanalytics-notifications-grid x-grid-with-formfields',
            store: {
                fields: [{name: 'uuid', defaultValue: ''}, 'subjectType', 'subjectId', 'notificationType', 'threshold', {name: 'recipientType', defaultValue: 1}, 'emails', 'status'],
                proxy: 'object'
            },
            features: {
                ftype: 'addbutton',
                text: 'Add new alert',
                handler: function(view) {
                    var grid = view.up();
                    grid.store.add({subjectType: 2, subjectId: loadParams['projectId'], status: 1});
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
                    } else if (record.get('recipientType') == 2 && !record.get('emails')) {
                        colIndex = 3;
                    }
                    if (colIndex !== undefined) {
                        var widget = grid.columns[colIndex].getWidget(record);
                        widget.validate();
                        widget.focus();
                        return false;
                    } else {
                        items.push(record.getData());
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
                                comp.getWidgetColumn().up('grid').columns[3].getWidget(record).setRecipientType(value);
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
                    xtype: 'component',
                    html: 'Schedule email alerts based on budget usage and overspend',
                    margin: 0
                },{
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
            hidden: true,
            cls: 'x-grid-shadow x-grid-no-highlighting x-costanalytics-notifications-grid x-grid-with-formfields',
            selModel: 'selectedmodel',
            store: {
                fields: [{name: 'uuid', defaultValue: ''}, 'subjectType', 'subjectId', {name: 'period', defaultValue: 3}, 'emails', 'status'],
                proxy: 'object'
            },
            features: {
                ftype: 'addbutton',
                text: 'Add new report',
                handler: function(view) {
                    var grid = view.up();
                    grid.store.add({subjectType: 2, subjectId: loadParams['projectId'], status: 1});
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
                    if (!record.get('emails')) {
                        colIndex = 1;
                    }
                    if (colIndex !== undefined) {
                        var widget = grid.columns[colIndex].getWidget(record);
                        widget.validate();
                        widget.focus();
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
                minWidth: 90,
                width: 90
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
        }],
		dockedItems: [{
            xtype: 'container',
            cls: 'x-container-fieldset',
            style: 'padding-bottom:0;background:#f1f5fa',
            dock: 'top',
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                html: 'Project notifications'

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
                        url: '/admin/analytics/projects/xSaveNotifications/',
                        params: {
                            projectId: loadParams['projectId'],
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

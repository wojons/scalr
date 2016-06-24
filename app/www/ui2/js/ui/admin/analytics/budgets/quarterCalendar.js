Scalr.regPage('Scalr.ui.admin.analytics.budgets.quarterCalendar', function (loadParams, moduleParams) {
    var curDate = new Date(),
        curYear = curDate.getFullYear();

    var form = Ext.create('Ext.form.Panel', {
        width: 500,
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true,
            closeOnEsc: moduleParams['quartersConfirmed'] == 1
        },
        items: [{
            xtype: 'fieldset',
            itemId: 'quarterCal',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            title: 'Fiscal calendar',
            defaults: {
                xtype: 'fieldcontainer',
                labelWidth: 25,
                layout: 'hbox',
                defaults: {
                    flex: 1
                }
            },
            items: [{
                xtype: 'displayfield',
                anchor: '100%',
                hidden: moduleParams['quartersConfirmed'] == 1,
                cls: 'x-form-field-warning',
                value: 'Fiscal calendar must be set <b>before</b> defining budgets!'
            },{
                fieldLabel: 'Q1',
                items: [{
                    xtype: 'datefield',
                    name: 'quarters[]',
                    itemId: 'q1',
                    //vtype: 'daterange',
                    daterangeCtId: 'quarterCal',
                    endDateField: 'q2',
                    format: 'M j',
                    submitFormat: 'm-d',
                    editable: false,
                    listeners: {
                        change: function(comp, value) {
                            form.down('#q42').setValue(Ext.Date.add(value, Ext.Date.DAY, -1), 'M j');
                        }
                    }
                },{
                    xtype: 'component',
                    style: 'text-align:center',
                    html: '&ndash;',
                    maxWidth: 24
                },{
                    xtype: 'datefield',
                    //vtype: 'daterange',
                    daterangeCtId: 'quarterCal',
                    startDateField: 'q1',
                    itemId: 'q12',
                    format: 'M j',
                    readOnly: true,
                    submitValue: false
                }]
            },{
                fieldLabel: 'Q2',
                items: [{
                    xtype: 'datefield',
                    name: 'quarters[]',
                    itemId: 'q2',
                    //vtype: 'daterange',
                    daterangeCtId: 'quarterCal',
                    endDateField: 'q3',
                    format: 'M j',
                    submitFormat: 'm-d',
                    //maxValue: new Date(),
                    editable: false,
                    listeners: {
                        change: function(comp, value) {
                            form.down('#q12').setValue(Ext.Date.add(value, Ext.Date.DAY, -1), 'M j');
                        }
                    }
                },{
                    xtype: 'component',
                    style: 'text-align:center',
                    html: '&ndash;',
                    maxWidth: 24
                }, {
                    xtype: 'datefield',
                    //vtype: 'daterange',
                    daterangeCtId: 'quarterCal',
                    startDateField: 'q2',
                    itemId: 'q22',
                    format: 'M j',
                    readOnly: true,
                    submitValue: false
                }]
            },{
                fieldLabel: 'Q3',
                items: [{
                    xtype: 'datefield',
                    name: 'quarters[]',
                    itemId: 'q3',
                    //vtype: 'daterange',
                    daterangeCtId: 'quarterCal',
                    endDateField: 'q4',
                    format: 'M j',
                    submitFormat: 'm-d',
                    //maxValue: new Date(),
                    editable: false,
                    listeners: {
                        change: function(comp, value) {
                            form.down('#q22').setValue(Ext.Date.add(value, Ext.Date.DAY, -1), 'M j');
                        }
                    }
                },{
                    xtype: 'component',
                    style: 'text-align:center',
                    html: '&ndash;',
                    maxWidth: 24
                }, {
                    xtype: 'datefield',
                    //vtype: 'daterange',
                    daterangeCtId: 'quarterCal',
                    startDateField: 'q3',
                    itemId: 'q32',
                    format: 'M j',
                    readOnly: true,
                    submitValue: false
                }]
            },{
                fieldLabel: 'Q4',
                items: [{
                    xtype: 'datefield',
                    name: 'quarters[]',
                    itemId: 'q4',
                    //vtype: 'daterange',
                    //endDateField: 'customEndDate',
                    format: 'M j',
                    submitFormat: 'm-d',
                    //maxValue: new Date(),
                    editable: false,
                    listeners: {
                        change: function(comp, value) {
                            form.down('#q32').setValue(Ext.Date.add(value, Ext.Date.DAY, -1), 'M j');
                        }
                    }
                },{
                    xtype: 'component',
                    style: 'text-align:center',
                    html: '&ndash;',
                    maxWidth: 24
                }, {
                    xtype: 'datefield',
                    //vtype: 'daterange',
                    daterangeCtId: 'quarterCal',
                    startDateField: 'q4',
                    itemId: 'q42',
                    format: 'M j',
                    readOnly: true,
                    submitValue: false
                }]
            }]
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
                text: 'Save',
                handler: function() {
                    var frm = form.getForm();
                    if (frm.isValid())
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: frm,
                            url: '/admin/analytics/budgets/xSaveQuarterCalendar',
                            success: function (data) {
                                Scalr.utils.Quarters.days = data['quarters'];
                                Scalr.event.fireEvent('redirect', '#/admin/analytics/budgets/');
                            }
                        });
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                disabled: moduleParams['quartersConfirmed'] != 1,
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]

    });

    Ext.each(moduleParams['quarters'], function(item, i){
        form.down('#q'+(i+1)).setValue(curYear+'-'+item);
    });
    return form;
});

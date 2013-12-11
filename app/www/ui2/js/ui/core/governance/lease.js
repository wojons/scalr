Ext.define('Scalr.ui.CoreGovernanceLease', {
    extend: 'Ext.container.Container',
    alias: 'widget.governancelease',

    setValues: function(data){
        var me = this,
            limits = data.settings.limits || {};

        if (limits['notifications'] == undefined)
            limits['notifications'] = undefined; // add default one

        this.setFieldValues(limits);
        Scalr.CachedRequestManager.get('governance').load(
            {
                url: '/core/governance/lease/xRequests'
            },
            function(data, status){
                data = data || [];
                me.down('#nonStandardRequests').store.loadData(data);
            }
        );

    },
    getValues: function() {
        return this.isValidFields() ? this.getFieldValues(true) : null;
    },
    items: [{
        xtype: 'fieldset',
        items: [{
            xtype: 'fieldcontainer',
            layout: 'hbox',
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Running farm lifetime',
                labelWidth: 140,
                width: 195,
                name: 'defaultLifePeriod',
                allowBlank: false,
                value: 30
            }, {
                xtype: 'displayfield',
                value: 'days',
                submitValue: false,
                margin: '0 0 0 5'
            }, {
                xtype: 'displayinfofield',
                value: 'Limit on how long a farm can be run before automatic termination',
                submitValue: false,
                margin: '0 0 0 5'
            }]
        }]
    }, {
        xtype: 'hidden',
        name: 'notifications',
        setValue: function(value) {
            var ct = this.next('fieldset');
            // on first call fieldset hasn't existed yet
            if (ct) {
                ct.removeAll();
                value = value || [{
                    to: 'owner',
                    period: 5
                }];
                for (var i = 0; i < value.length; i++)
                    ct.addNotification(value[i]);
            }
        },
        getValue: function() {
            var ct = this.next('fieldset'), values = [];
            ct.items.each(function(c) {
                var em = c.child('[name="emails"]');
                values.push({
                    key: c.child('[name="key"]').getValue(),
                    to: c.child('[name="to"]').getValue(),
                    emails: !em.readOnly ? c.child('[name="emails"]').getValue() : '',
                    period: c.child('[name="period"]').getValue()
                });
            });

            return values;
        }
    }, {
        xtype: 'fieldset',
        title: 'Notifications about farm\'s termination',
        collapsible: true,
        collapsed: true,
        addNotification: function(notif) {
            notif = notif || {};
            this.add({
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'hidden',
                    name: 'key',
                    submitValue: false,
                    value: notif['key'] || Scalr.utils.getRandomString(8)
                }, {
                    xtype: 'displayfield',
                    value: 'Send notification to'
                }, {
                    xtype: 'buttongroupfield',
                    name: 'to',
                    value: notif['to'] || 'owner',
                    submitValue: false,
                    margin: '0 0 0 8',
                    items: [{
                        text: 'Farm owner',
                        value: 'owner',
                        width: 100
                    }, {
                        text: 'Email',
                        value: 'email',
                        width: 100
                    }],
                    listeners: {
                        change: function(field, value) {
                            this.next('textfield')[ (value == 'owner') ? 'hide' : 'show']();
                        }
                    }
                }, {
                    xtype: 'textfield',
                    flex: 1,
                    maxWidth: 300,
                    name: 'emails',
                    emptyText: 'Enter one or more emails (comma separated)',
                    value: notif['emails'],
                    submitValue: false,
                    hidden: notif['to'] != 'email',
                    margin: '0 0 0 8'
                }, {
                    xtype: 'displayfield',
                    value: 'prior',
                    margin: '0 0 0 8'
                }, {
                    xtype: 'textfield',
                    name: 'period',
                    value: notif['period'] || 1,
                    submitValue: false,
                    width: 50,
                    margin: '0 0 0 8'
                }, {
                    xtype: 'displayfield',
                    value: 'days',
                    width: 30,
                    margin: '0 0 0 8'
                }, {
                    xtype: 'button',
                    ui: 'action',
                    itemId: 'delete',
                    margin: '0 0 0 8',
                    cls: 'x-btn-action-delete',
                    disabled: true,
                    handler: function() {
                        var ct = this.up('container');
                        ct.up('fieldset').remove(ct);
                    }
                }]
            })
        },

        plugins: {
            ptype: 'addfield',
            targetEl: '.x-fieldset-body span',
            width: '608px',
            cls: 'scalr-ui-addfield-light',
            handler: function() {
                this.addNotification();
            }
        },

        listeners: {
            add: function(cont, comp) {
                var cnt = this.items.getCount();
                if (cnt > 1)
                    comp.down('#delete').enable();

                if (cnt == 2)
                    this.items.getAt(0).down('#delete').enable();
            },
            remove: function() {
                if (this.items.getCount() == 1)
                    this.items.getAt(0).down('#delete').disable();
            }
        }


    }, {
        xtype: 'fieldset',
        title: 'Extension settings',
        items: [{
            xtype: 'buttongroupfield',
            labelWidth: 240,
            fieldLabel: 'Lease extension',
            name: 'leaseExtension',
            value: 'allow',
            items: [{
                text: 'Allow',
                value: 'allow',
                width: 80
            }, {
                text: 'Disallow',
                value: 'disallow',
                width: 80
            }],
            listeners: {
                change: function(c, value) {
                    var dis = value == 'disallow', fieldset = this.up('fieldset');
                    fieldset.down('[name="leaseExtensionStandardNumber"]').setDisabled(dis);
                    fieldset.down('[name="leaseExtensionStandardPeriod"]').setDisabled(dis);
                    fieldset.down('[name="leaseExtensionNonStandard"]').setDisabled(dis);
                }
            }
        }, {
            xtype: 'fieldcontainer',
            layout: 'hbox',
            fieldLabel: 'Maximum number of extensions allowed',
            labelWidth: 240,
            items: [{
                xtype: 'textfield',
                width: 50,
                name: 'leaseExtensionStandardNumber',
                allowBlank: false,
                value: 12
            }, {
                xtype: 'displayinfofield',
                value: 'Limit on the number of times a farm can have its lifetime extended',
                margin: '0 0 0 5'
            }]
        }, {
            xtype: 'fieldcontainer',
            layout: 'hbox',
            labelWidth: 240,
            fieldLabel: 'Standard extension term length',
            items: [{
                xtype: 'textfield',
                width: 50,
                name: 'leaseExtensionStandardPeriod',
                allowBlank: false,
                value: 30
            }, {
                xtype: 'displayfield',
                value: 'days',
                margin: '0 0 0 5'
            }]
        }, {
            xtype: 'checkbox',
            boxLabel: 'Allow non-standard extensions',
            name: 'leaseExtensionNonStandard',
            checked: true,
            listeners: {
                change: function (field, value) {
                    if (value) {
                        this.up('fieldset').next().show();
                        this.next().show();
                    } else {
                        this.up('fieldset').next().hide();
                        this.next().hide();
                    }
                },
                enable: function () {
                    if (this.getValue()) {
                        this.up('fieldset').next().show();
                        this.next().show();
                    }
                },
                disable: function () {
                    this.up('fieldset').next().hide();
                    this.next().hide();
                }
            }
        }, {
            xtype: 'textarea',
            fieldLabel: 'Notify the following users (comma separated, email addresses) about non-standard extension requests',
            labelAlign: 'top',
            height: 100,
            width: 600,
            name: 'leaseExtensionNonStandardNotifyEmails',
            validateOnChange: false,
            validateOnBlur: true,
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
        }]
    }, {
        xtype: 'fieldset',
        title: 'Non-standard extension requests',
        items: {
            xtype: 'grid',
            itemId: 'nonStandardRequests',
            cls: 'x-grid-shadow',

            store: {
                fields: [ 'id', 'name', 'request_days', 'request_comment', 'terminate_date' ]
            },

            viewConfig: {
                emptyText: 'No requests',
                deferInitialRefresh: false
            },

            columns: [
                { text: 'Farm name', flex: 1, dataIndex: 'name', sortable: true },
                { text: 'Expiry date', flex: 1, dataIndex: 'terminate_date', sortable: true, xtype: 'templatecolumn', tpl: new Ext.XTemplate(
                    '{[this.getDate(values)]}', {
                        getDate: function(values) {
                            var dt = new Date(values['terminate_date']);
                            return Ext.Date.format(dt, "M j, Y");
                        }
                    }
                )},
                { text: 'Days requested', flex: 1, dataIndex: 'request_days', sortable: true, xtype: 'templatecolumn', tpl:
                    '<tpl if="request_days == 0">No expiration<tpl else>{request_days}</tpl>'
                },
                { text: 'Comment', flex: 3, dataIndex: 'request_comment', sortable: true }
            ],

            multiSelect: true,
            selModel: {
                selType: 'selectedmodel'
            },

            listeners: {
                selectionchange: function(selModel, selections) {
                    this.down('toolbar').down('#approve').setDisabled(!selections.length);
                    this.down('toolbar').down('#decline').setDisabled(!selections.length);
                }
            },

            dockedItems: [{
                xtype: 'toolbar',
                ui: 'simple',
                items: [{
                    xtype: 'button',
                    text: 'Show history of requests',
                    handler: function() {
                        Scalr.event.fireEvent('redirect', '#/core/governance/lease/history');
                    }
                }, '->', {
                    ui: 'paging',
                    itemId: 'approve',
                    iconCls: 'x-tbar-approve',
                    margin: '0 9 0 0',
                    disabled: true,
                    handler: function() {
                        var request = {
                            confirmBox: {
                                type: 'action',
                                msg: 'Approve selected requests for farm(s): %s ?',
                                formSimple: true,
                                formWidth: 450,
                                form: [{
                                    xtype: 'textarea',
                                    name: 'comment',
                                    fieldLabel: 'Comment',
                                    labelAlign: 'top',
                                    height: 100
                                }]
                            },
                            processBox: {
                                type: 'approve',
                                msg: 'Approving requests ...'
                            },
                            url: '/core/governance/lease/xRequestResult',
                            success: function() {
                                var grid = me.up('grid'), selected = grid.getSelectionModel().getSelection();
                                for (var i = 0; i < selected.length; i++) {
                                    grid.store.remove(selected[i]);
                                }
                            }
                        }, records = this.up('grid').getSelectionModel().getSelection(), requests = [], me = this;

                        request.confirmBox.objects = [];
                        for (var i = 0, len = records.length; i < len; i++) {
                            requests.push(records[i].get('id'));
                            request.confirmBox.objects.push(records[i].get('name'));
                        }
                        request.params = { requests: Ext.encode(requests), decision: 'approve' };
                        Scalr.Request(request);
                    }
                }, {
                    ui: 'paging',
                    itemId: 'decline',
                    iconCls: 'x-tbar-decline',
                    disabled: true,
                    handler: function() {
                        var request = {
                            confirmBox: {
                                type: 'decline',
                                msg: 'Decline selected requests for farm(s): %s ?',
                                formSimple: true,
                                formWidth: 450,
                                form: [{
                                    xtype: 'textarea',
                                    name: 'comment',
                                    fieldLabel: 'Comment',
                                    labelAlign: 'top',
                                    height: 100
                                }]
                            },
                            processBox: {
                                type: 'action',
                                msg: 'Decline requests ...'
                            },
                            url: '/core/governance/lease/xRequestResult',
                            success: function() {
                                var grid = me.up('grid'), selected = grid.getSelectionModel().getSelection();
                                for (var i = 0; i < selected.length; i++) {
                                    grid.store.remove(selected[i]);
                                }
                            }
                        }, records = this.up('grid').getSelectionModel().getSelection(), requests = [], me = this;

                        request.confirmBox.objects = [];
                        for (var i = 0, len = records.length; i < len; i++) {
                            requests.push(records[i].get('id'));
                            request.confirmBox.objects.push(records[i].get('name'));
                        }
                        request.params = { requests: Ext.encode(requests), decision: 'decline' };
                        Scalr.Request(request);
                    }
                }]
            }]

        }
    }]
});

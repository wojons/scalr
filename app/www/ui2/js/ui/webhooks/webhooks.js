Ext.define('Scalr.ui.WebhooksGrid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.webhooksgrid',

    flex: 1,
    multiSelect: true,
    selModel: {
        selType: 'selectedmodel',
        getVisibility: function(record) {
            return this.view.up().level == record.get('level');
        }
    },

    initComponent: function() {
        var me = this;
        me.typeTitle = me.type === 'config' ? 'webhook' : me.type;
        me.viewConfig = {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No ' + me.typeTitle + 's were found to match your search.',
                emptyTextNoItems: 'You have no ' + me.typeTitle + 's created yet.'
            },
            loadingText: 'Loading ' + me.typeTitle + 's ...',
            deferEmptyText: false,
            listeners: {
                refresh: function(view){
                    view.getSelectionModel().setLastFocused(null);
                    view.getSelectionModel().deselectAll();
                }
            }
        };
        me.dockedItems = [];
        me.dockedItems.push({
            xtype: 'toolbar',
            dock: 'top',
            defaults: {
                margin: '0 0 0 12',
                handler: function() {
                    var action = this.getItemId(),
                        actionMessages = {
                            'delete': ['Delete selected ' + me.typeTitle + '(s)', 'Deleting selected ' + me.typeTitle + '(s) ...']
                        },
                        selModel = me.getSelectionModel(),
                        ids = [],
                        request = {};
                
                    for (var i=0, records = selModel.getSelection(), len=records.length; i<len; i++) {
                        ids.push(records[i].get('id'));
                    }

                    request = {
                        confirmBox: {
                            msg: actionMessages[action][0],
                            type: action
                        },
                        processBox: {
                            msg: actionMessages[action][1],
                            type: action
                        },
                        params: {action: action, level: me.level},
                        success: function (data) {
                            if (data.processed && data.processed.length) {
                                switch (action) {
                                    case 'delete':
                                        var recordsToDelete = [];
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            recordsToDelete.push(me.store.getById(data.processed[i]));
                                            selModel.deselect(recordsToDelete[i]);
                                        }
                                        me.store.remove(recordsToDelete);
                                    break;
                                }
                            }
                            selModel.refreshLastFocused();
                        }
                    };
                    request.url = '/webhooks/' + me.type + 's/xGroupActionHandler';
                    request.params[me.typeTitle + 'Ids'] = Ext.encode(ids);

                    Scalr.Request(request);
                }
            },
            items: [{
                xtype: 'filterfield',
                itemId: 'liveSearch',
                margin: 0,
                minWidth: 60,
                maxWidth: 200,
                flex: 1,
                filterFields: me.filterFields,
                handler: null,
                store: me.store
            }, me.level == 2 ? {
                xtype: 'webhooksaccountmenu',
                dock: 'top',
                flex: 2,
                value: me.type
            } : {
                xtype: 'tbfill',
                flex: .1,
                margin: 0
            },{
                xtype: 'tbfill',
                flex: .1,
                margin: 0
            },{
                itemId: 'add',
                text: 'New ' + me.typeTitle,
                cls: 'x-btn-green-bg',
                handler: function() {
                    me.getSelectionModel().setLastFocused(null);
                    me.fireEvent('btnnewclick', me.store.createModel({}));
                }
            },{
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                tooltip: 'Refresh',
                handler: function() {
                    me.fireEvent('btnrefreshclick');
                }
            },{
                itemId: 'delete',
                ui: 'paging',
                iconCls: 'x-tbar-delete',
                disabled: true,
                tooltip: 'Delete ' + me.typeTitle
            }]
        });

        me.on('selectionchange',  function(selModel, selected) {
            this.down('#delete').setDisabled(!selected.length);
        });
        me.plugins = ['focusedrowpointer'];

        me.callParent(arguments);
    }
});

Ext.define('Scalr.ui.WebhooksEndpointForm', {
    extend: 'Ext.form.Panel',
    alias: 'widget.webhooksendpointform',

    hidden: true,
    fieldDefaults: {
        anchor: '100%'
    },
    layout: 'auto',
    overflowX: 'hidden',
    overflowY: 'auto',
    trackResetOnLoad: true,

    initComponent: function() {
        var me = this;
        me.on('beforeloadrecord', function(record){
            var frm = this.getForm(),
                isNewRecord = !record.get('endpointId'),
                isEndpointValid = record.get('isValid') == 1;

            frm.reset(true);
            frm.findField('url').validateOnChange = false;
            this.down('#formtitle').setTitle(isNewRecord ? 'New endpoint' : 'Edit endpoint');

            if (!isNewRecord && isEndpointValid) {
                var usedBy = [];
                Ext.Object.each(record.get('webhooks'), function(webhookId, webhookName){
                    usedBy.push('<a href="#/webhooks/configs?level='+me.levelName+'&webhookId='+webhookId+'">'+webhookName+'</a>');
                });
                this.down('#usedBy').setValue(usedBy.length ? usedBy.join(', ') : '-');
            }
            this.down('#validation').setVisible(!isEndpointValid && !isNewRecord);
            this.down('#usedBy').setVisible(isEndpointValid && !isNewRecord);
            this.down('#securityKey').setVisible(isEndpointValid && !isNewRecord);

            this.down('#delete').setVisible(!isNewRecord);
        });

		me.items = {
			xtype: 'fieldset',
            itemId: 'formtitle',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            title: '&nbsp;',
            defaults: {
                labelWidth: 90
            },
			items: [{
                xtype: 'hidden',
                name: 'endpointId'
            },{
				xtype: 'textfield',
				name: 'url',
				fieldLabel: 'URL',
				allowBlank: false,
                //vtype:'url',
                validateOnChange: false,
                emptyText: 'ex. http://example.com/endpoint'
            },{
				xtype: 'textarea',
				name: 'securityKey',
                itemId: 'securityKey',
				fieldLabel: 'Signing key',
                readOnly: true,
                submitValue: false,
                height: 48
            },{
                xtype: 'displayfield',
                itemId: 'usedBy',
                fieldLabel: 'Used by'
            },{
                xtype: 'fieldset',
                title: 'Endpoint validation',
                itemId: 'validation',
                layout: 'anchor',
                cls: 'x-fieldset-separator-none',
                margin: '24 0 0 0',
                defaults: {
                    anchor: '100%'
                },
                style: 'background:#fefde0;border-radius:3px;color:#333;font:13px/20px arial,helvetica,tahoma,sans-serif;',
                items: [{
                    xtype: 'component',
                    html: 'For security reasons and to ensure quality of service, Scalr requires that you confirm ownership of an Endpoint before it can be used in a Webhook. Note that Endpoints only have to be validated once.' +
                          '<p>To validate your endpoint, perform one of the following, then click "Validate":' +
                          '<ul style="padding: 0 0 0 24px"><li>Configure the web service handling Webhook messages for this endpoint so that it returns the validation code found below when Scalr performs an HTTP GET request.</li>'+
                          '<li>Configure the web service handling Webhook messages for this endpoint so that it adds a "X-Validation-Token" HTTP header to its responses. The header value should be the validation code found below.</li></ul></p>'
                },{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'textfield',
                        name: 'validationToken',
                        readOnly: true,
                        fieldLabel: 'Validation token',
                        labelWidth: 110,
                        flex: 1
                    },{
                        xtype: 'button',
                        itemId: 'validateBtn',
                        text: 'Validate',
                        width: 110,
                        margin: '0 0 0 12',
                        handler: function() {
                            var frm = me.getForm(),
                                record = frm.getRecord();
                            if (frm.isValid()) {
                                var cb = function (data) {
                                    record.set(data.endpoint);
                                    me.loadRecord(record);
                                    me.fireEvent('validateendpoint', record);
                                };
                                Scalr.Request({
                                    processBox: {
                                        type: 'action'
                                    },
                                    params: {level: me.level},
                                    url: '/webhooks/endpoints/xValidate',
                                    form: frm,
                                    success: cb,
                                    failure: cb
                                });
                            }
                        }
                    }]
                }]
            }]
        };

		me.dockedItems = [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
            maxWidth: 1100,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
			items: [{
				itemId: 'save',
				xtype: 'button',
				text: 'Save',
				handler: function() {
					var frm = me.getForm(),
                        record = frm.getRecord();
					if (frm.isValid()) {
                        var r = {
							processBox: {
								type: 'save'
							},
							url: '/webhooks/endpoints/xSave',
							form: frm,
                            params: {level: me.level},
							success: function (data) {
                                me.fireEvent('saveendpoint', record, data);
							},
                            failure: function() {
                                frm.findField('url').validateOnChange = true;
                            }
                        };
                        //temporarily disable url validation per Igor`s request(see also Scalr_UI_Controller_Webhooks_Endpoints->xSaveAction)
                        /*if (record.get('isValid') == 1 && frm.findField('url').isDirty()) {
                            r.confirmBox = {
                                type: 'action',
                                msg: 'Endpoint must be revalidated after changing the url.<br/>Are you sure you want to save changes?',
                                ok: 'Save'
                            };
                        }*/
                        Scalr.Request(r);
					}
				}
			}, {
				itemId: 'cancel',
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
                    me.fireEvent('cancelendpoint');
				}
			}, {
				itemId: 'delete',
				xtype: 'button',
				cls: 'x-btn-default-small-red',
				text: 'Delete',
				handler: function() {
					var record = me.getForm().getRecord();
					Scalr.Request({
						confirmBox: {
							msg: 'Delete endpoint?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting...',
							type: 'delete'
						},
						scope: this,
						url: '/webhooks/endpoints/xRemove',
						params: {endpointId: record.get('endpointId'), level: me.level},
						success: function (data) {
							record.store.remove(record);
                            me.setVisible(false);
						}
					});
				}
			}]
		}];


        me.callParent(arguments);
    }
});

Ext.define('Scalr.ui.WebhooksAccountMenu', {
	extend: 'Scalr.ui.FormFieldButtonGroup',
	alias: 'widget.webhooksaccountmenu',

    listeners: {
        beforetoggle: function(){return false;}
    },
    layout: {
        type: 'hbox',
        //pack: 'center'
    },
    maxWidth: 380,
    items: [{
        value: 'endpoint',
        text: 'Endpoints',
        flex: 1,
        //margin: '12 0 0',
        hrefTarget: '_self',
        href: '#/webhooks/endpoints?level=account'
    },{
        value: 'config',
        text: 'Webhooks',
        flex: 1,
        //margin: '12 0 0',
        hrefTarget: '_self',
        href: '#/webhooks/configs?level=account'
    },{
        value: 'history',
        text: 'History',
        flex: 1,
        //margin: '12 0 0',
        hrefTarget: '_self',
        href: '#/webhooks/history?level=account'
    }]
});
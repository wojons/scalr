Scalr.regPage('Scalr.ui.webhooks.endpoints.view', function (loadParams, moduleParams) {
	var storeEndpoints = Scalr.data.get('webhooks.endpoints'),
		storeWebhooks = Scalr.data.get('webhooks.configs');
		
	var store = Ext.create('Scalr.ui.ChildStore', {
		parentStore: storeEndpoints,
		filterOnLoad: true,
		sortOnLoad: true,
		sorters: [{
			property: 'url',
			transform: function(value){
				return value.toLowerCase();
			}
		}]
	});
	
	var reconfigurePage = function(params) {
        if (params.endpointId) {
            cb = function() {
                var selModel = grid.getSelectionModel();
                selModel.deselectAll();
                if (params.endpointId === 'new') {
                    panel.down('#add').handler();
                } else {
                    panel.down('#liveSearch').reset();
                    var record = store.getById(params.endpointId);
                    if (record) {
                        selModel.select(record);
                    }
                }
            };
            if (grid.view.viewReady) {
                cb();
            } else {
                grid.view.on('viewready', cb, grid.view, {single: true});
            }
        }
    };
	
    var grid = Ext.create('Ext.grid.Panel', {
        cls: 'x-grid-shadow x-panel-column-left',
        flex: 1,
        multiSelect: true,
        selType: 'selectedmodel',
        store: store,
        plugins: ['focusedrowpointer'],
        listeners: {
            selectionchange: function(selModel, selected) {
                this.down('#delete').setDisabled(!selected.length);
            }
        },
        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No endpoints were found to match your search.',
                emptyTextNoItems: 'You have no endpoints created yet.'
            },
            loadingText: 'Loading endpoints ...',
            deferEmptyText: false,
            listeners: {
                refresh: function(view){
                    view.getSelectionModel().setLastFocused(null);
                }
            }
        },

        columns: [
            {text: 'URL', flex: 1, dataIndex: 'url', sortable: true},
            {text: 'Status', minWidth: 120, dataIndex: 'isValid', sortable: true, xtype: 'statuscolumn', statustype: 'webhookendpoint', qtipConfig: {width: 300}}
        ],
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            defaults: {
                margin: '0 0 0 12',
                handler: function() {
                    var action = this.getItemId(),
                        actionMessages = {
                            'delete': ['Delete selected endpoint(s)', 'Deleting selected endpoint(s) ...']
                        },
                        selModel = grid.getSelectionModel(),
                        ids = [],
                        urls = [],
                        request = {};
                    for (var i=0, records = selModel.getSelection(), len=records.length; i<len; i++) {
                        ids.push(records[i].get('endpointId'));
                        urls.push(records[i].get('url'));
                    }

                    request = {
                        confirmBox: {
                            msg: actionMessages[action][0],
                            type: action,
                            //objects: urls
                        },
                        processBox: {
                            msg: actionMessages[action][1],
                            type: action
                        },
                        params: {endpointIds: ids, action: action},
                        success: function (data) {
                            if (data.processed && data.processed.length) {
                                switch (action) {
                                    case 'delete':
                                        var recordsToDelete = [];
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            recordsToDelete.push(store.getById(data.processed[i]));
                                            selModel.deselect(recordsToDelete[i]);
                                        }
                                        store.remove(recordsToDelete);
                                    break;
                                }
                            }
                            selModel.refreshLastFocused();
                        }
                    };
                    request.url = '/webhooks/endpoints/xGroupActionHandler';
                    request.params.endpointIds = Ext.encode(ids);

                    Scalr.Request(request);
                }
            },
            items: [{
                xtype: 'filterfield',
                itemId: 'liveSearch',
                margin: 0,
                minWidth: 120,
                maxWidth: 200,
                flex: 1,
                filterFields: ['url'],
                handler: null,
                store: store
            },{
                xtype: 'tbfill'
            },{
                itemId: 'add',
                text: 'New endpoint',
                cls: 'x-btn-green-bg',
                handler: function() {
                    grid.getSelectionModel().setLastFocused(null);
                    form.loadRecord(store.createModel({}));
                }
            },{
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                tooltip: 'Refresh',
                handler: function() {
                    Scalr.data.reload('webhooks.*');
                }
            },{
                itemId: 'delete',
                ui: 'paging',
                iconCls: 'x-tbar-delete',
                disabled: true,
                tooltip: 'Delete endpoint'
            }]
        }]
    });

	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
		fieldDefaults: {
			anchor: '100%'
		},
		layout: 'auto',
        overflowX: 'hidden',
        overflowY: 'auto',
        trackResetOnLoad: true,
		listeners: {
			hide: function() {
    			grid.down('#add').setDisabled(false);
			},
            afterrender: function() {
                var me = this;
                grid.getSelectionModel().on('focuschange', function(gridSelModel){
                    if (gridSelModel.lastFocused) {
                        me.loadRecord(gridSelModel.lastFocused);
                    } else {
                        me.setVisible(false);
                    }
                });
            },
			beforeloadrecord: function(record) {
				var frm = this.getForm(),
					isNewRecord = !record.get('endpointId'),
                    isEndpointValid = record.get('isValid') == 1;

				frm.reset(true);
                frm.findField('url').validateOnChange = false;
                this.down('#formtitle').setTitle(isNewRecord ? 'New endpoint' : 'Edit endpoint');

                if (!isNewRecord && isEndpointValid) {
                    var endpointId = record.get('endpointId'),
                        usedBy = [];
                    storeWebhooks.each(function(webhook){
                        if (Ext.Array.contains(webhook.get('endpoints'), endpointId)) {
                            usedBy.push('<a href="#/webhooks/configs?webhookId='+webhook.get('webhookId')+'">'+webhook.get('name')+'</a>');
                        }
                    });
                    this.down('#usedBy').setValue(usedBy.length ? usedBy.join(', ') : '-');
                }
                this.down('#validation').setVisible(!isEndpointValid && !isNewRecord);
                this.down('#usedBy').setVisible(isEndpointValid && !isNewRecord);
                this.down('#securityKey').setVisible(isEndpointValid && !isNewRecord);
                
                this.down('#delete').setVisible(!isNewRecord);
                grid.down('#add').setDisabled(isNewRecord);
			},
			loadrecord: function(record) {
				if (!this.isVisible()) {
					this.setVisible(true);
				}
			}
		},
		items: [{
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
                            var frm = this.up('form').getForm(),
                                record = frm.getRecord();
                            if (frm.isValid()) {
                                var cb = function (data) {
                                    grid.getSelectionModel().setLastFocused(null);
                                    record.set(data.endpoint);
                                    form.loadRecord(record);
                                    grid.getSelectionModel().setLastFocused(record);
                                };
                                Scalr.Request({
                                    processBox: {
                                        type: 'action'
                                    },
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
        }],
		dockedItems: [{
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
					var frm = form.getForm(),
                        record = frm.getRecord();
					if (frm.isValid()) {
                        var r = {
							processBox: {
								type: 'save'
							},
							url: '/webhooks/endpoints/xSave',
							form: frm,
							success: function (data) {
                                var isNewRecord = !record.get('endpointId');
                                grid.getSelectionModel().setLastFocused(null);
                                form.setVisible(false);
								if (isNewRecord) {
									record = store.add(data.endpoint)[0];
									grid.getSelectionModel().select(record);
								} else {
									record.set(data.endpoint);
									form.loadRecord(record);
								}
                                if (isNewRecord) {
                                    grid.getSelectionModel().select(record);
                                } else {
                                    grid.getSelectionModel().setLastFocused(record);
                                }

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
                    grid.getSelectionModel().setLastFocused(null);
                    form.setVisible(false);
				}
			}, {
				itemId: 'delete',
				xtype: 'button',
				cls: 'x-btn-default-small-red',
				text: 'Delete',
				handler: function() {
					var record = form.getForm().getRecord();
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
						params: {endpointId: record.get('endpointId')},
						success: function (data) {
							record.store.remove(record);
                            form.setVisible(false);
						}
					});
				}
			}]
		}]
	});
	
	var panel = Ext.create('Ext.panel.Panel', {
		cls: 'scalr-ui-panel-webhooks-ebndpoints',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			title: 'Endpoints',
			reload: false,
			maximize: 'all',
			leftMenu: {
				menuId: 'webhooks',
				itemId: 'endpoints'
			}
		},
        listeners: {
            applyparams: reconfigurePage
        },
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            flex: 1,
            maxWidth: 640,
            minWidth: 400,
            layout: 'fit',
            items: [
                form
            ]
        }]
	});
	return panel;
});
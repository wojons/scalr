Scalr.regPage('Scalr.ui.webhooks.configs.view', function (loadParams, moduleParams) {
	var storeWebhooks = Scalr.data.get('webhooks.configs');
		
	var store = Ext.create('Scalr.ui.ChildStore', {
		parentStore: storeWebhooks,
		filterOnLoad: true,
		sortOnLoad: true,
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}]
	});

	var storeEndpoints = Ext.create('Scalr.ui.ChildStore', {
		parentStore: Scalr.data.get('webhooks.endpoints')
	});

	var reconfigurePage = function(params) {
        if (params.webhookId) {
            cb = function() {
                var selModel = grid.getSelectionModel();
                selModel.deselectAll();
                if (params.webhookId === 'new') {
                    panel.down('#add').handler();
                } else {
                    panel.down('#liveSearch').reset();
                    var record = store.getById(params.webhookId);
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
                emptyText: 'No webhooks were found to match your search.',
                emptyTextNoItems: 'You have no webhooks created yet.'
            },
            loadingText: 'Loading webhooks ...',
            deferEmptyText: false,
            listeners: {
                refresh: function(view){
                    view.getSelectionModel().setLastFocused(null);
                }
            }
        },

        columns: [
            {text: 'Name', flex: 1, dataIndex: 'name', sortable: true}
        ],
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            defaults: {
                margin: '0 0 0 12',
                handler: function() {
                    var action = this.getItemId(),
                        actionMessages = {
                            'delete': ['Delete selected webhook(s)', 'Deleting selected webhook(s) ...']
                        },
                        selModel = grid.getSelectionModel(),
                        ids = [],
                        urls = [],
                        request = {};
                    for (var i=0, records = selModel.getSelection(), len=records.length; i<len; i++) {
                        ids.push(records[i].get('webhookId'));
                        urls.push(records[i].get('name'));
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
                        params: {webhookIds: ids, action: action},
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
                    request.url = '/webhooks/configs/xGroupActionHandler';
                    request.params.webhookIds = Ext.encode(ids);

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
                filterFields: ['name'],
                handler: null,
                store: store
            },{
                xtype: 'tbfill'
            },{
                itemId: 'add',
                text: 'New webhook',
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
                tooltip: 'Delete webhook(s)'
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
                form.down('#events').store.load({data: moduleParams['events']});
                form.down('#farms').store.load({data: moduleParams['farms']});
            },
			beforeloadrecord: function(record) {
				var frm = this.getForm(),
					isNewRecord = !record.get('webhookId');

				frm.reset(true);
                this.down('#formtitle').setTitle(isNewRecord ? 'New webhook' : 'Edit webhook');
				var c = this.query('component[cls~=hideoncreate], #delete');
				for (var i=0, len=c.length; i<len; i++) {
					c[i].setVisible(!isNewRecord);
				}
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
            cls: 'x-fieldset-separator-none',
            title: '&nbsp;',
            defaults: {
                labelWidth: 80
            },
			items: [{
                xtype: 'hidden',
                name: 'webhookId'
            },{
				xtype: 'textfield',
				name: 'name',
				fieldLabel: 'Name',
				allowBlank: false,
                validateOnChange: false
            },{
                xtype: 'comboboxselect',
                itemId: 'endpoints',
                name: 'endpoints',
                store: storeEndpoints,
                flex: 1,
                valueField: 'id',
                displayField: 'url',
                fieldLabel: 'Endpoints',
                queryMode: 'local',
                columnWidth: 1,
                allowBlank: false,
                emptyText: 'Please select endpoints',
                listeners: {
                    beforeselect: function(field, record) {
                        return record.get('isValid') == 1;
                    }
                },
                listConfig: {
                    tpl: '<tpl for="."><div class="x-boundlist-item">{[values.isValid!=1?\'<span style="color:#999">\'+values.url+\' (inactive)</span>\':values.url]}</div></tpl>'
                }
            },{
                xtype: 'comboboxselect',
                store: {
                    fields: [
                        'id',
                        {
                            name: 'title',
                            convert: function(v, record){
                                return record.get('id')=='*' ? 'All Events' : record.get('id');
                            }
                        },
                        'name'
                    ],
                    proxy: 'object'
                },
                fieldLabel: 'Events',
                valueField: 'id',
                displayField: 'title',
                queryMode: 'local',
                allowBlank: false,
                validateOnChange: false,
                itemId: 'events',
                name: 'events',
                emptyText: 'Please select events',
                listConfig: {
                    cls: 'x-boundlist-role-scripting-events',
                    style: 'white-space:nowrap',
                    getInnerTpl: function(displayField) {
                        return '<tpl if=\'id == \"*\"\'>All Events<tpl else>{id} <span style="color:#999">({name})</span></tpl>';
                    }
                }
            },{
                xtype: 'comboboxselect',
                store: {
                    fields: [
                        {name: 'id', type: 'int'},
                        'name'
                    ],
                    proxy: 'object'
                },
                fieldLabel: 'Farms',
                valueField: 'id',
                displayField: 'name',
                queryMode: 'local',
                validateOnChange: false,
                itemId: 'farms',
                name: 'farms',
                emptyText: 'All farms'
            },{
                xtype: 'checkbox',
                name: 'skipPrivateGv',
                boxLabel: 'Do not expose private GlobalVariables in webhook payload'
			}, {
				xtype: 'textarea',
				name: 'postData',
				fieldLabel: 'User data',
                labelAlign: 'top',
                icons: {
                    globalvars: true
                },
                iconsPosition: 'outer',
                validateOnChange: false,
                height: 120
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
                        webhook = frm.getValues(),
                        record = frm.getRecord();
					if (frm.isValid()) {
                        webhook.endpoints = Ext.encode(webhook.endpoints);
                        webhook.events = Ext.encode(webhook.events);
                        webhook.farms = Ext.encode(webhook.farms);
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/webhooks/configs/xSave',
                            params: webhook,
							success: function (data) {
                                var isNewRecord = !record.get('webhookId');
                                grid.getSelectionModel().setLastFocused(null);
                                form.setVisible(false);
								if (isNewRecord) {
									record = store.add(data.webhook)[0];
									grid.getSelectionModel().select(record);
								} else {
									record.set(data.webhook);
									form.loadRecord(record);
								}
                                if (isNewRecord) {
                                    grid.getSelectionModel().select(record);
                                } else {
                                    grid.getSelectionModel().setLastFocused(record);
                                }

							}
						});
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
							msg: 'Delete webhook?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting...',
							type: 'delete'
						},
						scope: this,
						url: '/webhooks/configs/xRemove',
						params: {webhookId: record.get('webhookId')},
						success: function (data) {
							record.store.remove(record);
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
			title: 'Webhooks',
			reload: false,
			maximize: 'all',
			leftMenu: {
				menuId: 'webhooks',
				itemId: 'configs'
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
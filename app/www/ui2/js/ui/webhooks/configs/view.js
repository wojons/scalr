Scalr.regPage('Scalr.ui.webhooks.configs.view', function (loadParams, moduleParams) {
    var isWebhooksReadOnly = false;
    if (Scalr.scope === 'environment') {
        isWebhooksReadOnly = !Scalr.isAllowed('WEBHOOKS_ENVIRONMENT', 'manage');
    } else if (Scalr.scope === 'account') {
        isWebhooksReadOnly = !Scalr.isAllowed('WEBHOOKS_ACCOUNT', 'manage');
    }

    var store = Ext.create('store.store', {
        model: Ext.define(null, {
            extend: 'Ext.data.Model',
            idProperty: 'webhookId',
            fields: [
                {name: 'webhookId', type: 'string'},
                {name: 'scope', defaultValue: moduleParams['scope']},
                'name',
                'postData',
                'skipPrivateGv',
                'endpoints',
                'events',
                'farms',
                {name: 'timeout', type: 'int', defaultValue: 3},
                {name: 'attempts', type: 'int', defaultValue: 3}
            ]
        }),
        data: moduleParams['configs'],
		proxy: {
			type: 'ajax',
			url: '/webhooks/configs/xList/',
            reader: {
                type: 'json',
                rootProperty: 'configs',
                successProperty: 'success'
            }
		},
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}]
	});

	var reconfigurePage = function(params) {
        if (params.webhookId) {
            cb = function() {
                if (params.webhookId === 'new') {
                    panel.down('#add').handler();
                } else {
                    panel.down('#liveSearch').reset();
                    var record = store.getById(params.webhookId);
                    if (record) {
                        grid.setSelectedRecord(record);
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

    var grid = Ext.create('Scalr.ui.WebhooksGrid', {
        cls: 'x-panel-column-left',
        store: store,
        type: 'config',
        filterFields: ['name'],
        readOnly: isWebhooksReadOnly,
        columns: [
            {
                text: 'Webhook',
                flex: 1,
                dataIndex: 'name',
                resizable: false,
                sortable: true,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getScope(values.scope)]}&nbsp;&nbsp;{name}',
                    {
                        getScope: function(scope){
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('webhook') + '"/>';
                        }
                    }
                )
            }
        ],
        scope: moduleParams['scope'],
        listeners: {
            btnnewclick: function (pressed) {
                grid.clearSelectedRecord();

                if (pressed) {
                    form.loadRecord(store.createModel({webhookId: 0}));
                }
            },
            btnrefreshclick: function() {
                store.load();
            }
        }
    });

	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
		fieldDefaults: {
			anchor: '100%'
		},
		layout: 'auto',
        overflowX: 'hidden',
        overflowY: 'auto',
        toggleScopeInfo: function(record) {
            var me = this,
                scopeInfoField = me.down('#scopeInfo');
            if (Scalr.scope != record.get('scope')) {
                scopeInfoField.setValue(Scalr.utils.getScopeInfo('webhook', record.get('scope'), record.get('webhookId')));
                scopeInfoField.show();
            } else {
                scopeInfoField.hide();
            }
            return me;
        },
		listeners: {
            show: function (form) {
                form.down('field[xtype!=hidden]').focus();
            },
			hide: function () {
                grid.down('#add').toggle(false, true);
			},
			beforeloadrecord: function(record) {
				var isNewRecord = !record.store;

                this.down('#formtitle').setTitle(isNewRecord ? 'New webhook' : 'Edit webhook');
				var c = this.query('component[cls~=hideoncreate], #delete');
				for (var i=0, len=c.length; i<len; i++) {
					c[i].setVisible(!isNewRecord);
				}
                grid.down('#add').toggle(isNewRecord, true);

                var readOnly = moduleParams['scope'] != record.get('scope') || isWebhooksReadOnly;
                Ext.each(this.query('[isFormField]'), function(field){
                    field.setReadOnly(readOnly);
                });
                if (moduleParams['scope'] === record.get('scope')) {
                    this.down('#delete').setDisabled(false);
                    this.down('#save').setDisabled(false);
                    this.down('[name="postData"]').show();
                } else {
                    this.down('#delete').setDisabled(true);
                    this.down('#save').setDisabled(true);
                    this.down('[name="postData"]').hide();
                }

                this.toggleScopeInfo(record);

            },
            afterrender: function() {
                form.down('#events').store.load({data: moduleParams['events']});
                form.down('#farms').store.load({data: moduleParams['farms']});
            }
		},
		items: [{
            xtype: 'displayfield',
            itemId: 'scopeInfo',
            cls: 'x-form-field-info x-form-field-info-fit',
            width: '100%',
            hidden: true
        },{
			xtype: 'fieldset',
            itemId: 'formtitle',
            cls: 'x-fieldset-separator-none',
            title: '&nbsp;',
            defaults: {
                labelWidth: 160
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
                xtype: 'tagfield',
                itemId: 'endpoints',
                name: 'endpoints',
                store: {
                    fields: ['id', 'url', 'isValid', 'scope'],
                    data: moduleParams['endpoints'],
                    proxy: 'object'
                },
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
                labelTpl: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-{scope}" data-qtip="{scope:capitalize} scope"/>&nbsp;{url}',
                listConfig: {
                    tpl:
                        '<tpl for=".">' +
                            '<div class="x-boundlist-item">' +
                                '<div style="white-space:nowrap">' +
                                    '&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-{scope}" data-qtip="{scope:capitalize} scope"/>&nbsp; '+
                                    '{[values.isValid!=1?\'<span style="color:#999">\'+values.url+\' (inactive)</span>\':values.url]}' +
                                '</div>' +
                            '</div>' +
                        '</tpl>'
                },
                submitValue: false
            },{
                xtype: 'tagfield',
                store: {
                    fields: [
                        'name',
                        'description',
                        'scope',
                        {
                            name: 'id',
                            mapping: 'name'
                        }
                    ],
                    proxy: 'object'
                },
                fieldLabel: 'Events',
                valueField: 'id',
                displayField: 'id',
                queryMode: 'local',
                allowBlank: false,
                itemId: 'events',
                name: 'events',
                emptyText: 'Please select events',
                matchFieldWidth: true,
                labelTpl: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-{scope}" data-qtip="{scope:capitalize} scope"/>&nbsp;{name}',
                listConfig: Scalr.configs.eventsListConfig,
                submitValue: false
            },{
                xtype: 'tagfield',
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
                itemId: 'farms',
                name: 'farms',
                emptyText: 'All farms',
                submitValue: false
            },{
                xtype: 'combo',
                fieldLabel: 'Timeout (sec)',
                store: [1,2,3,4,5,6,7,8,9,10],
                editable: false,
                name: 'timeout',
                maxWidth: 225,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip:
                            '<b>Timeout</b><br/>'+
                            'When delivering a Webhook Notification, Scalr will expect to start<br/>' +
                            'receiving your Endpoint&#39;s response within this duration. If your<br/>' +
                            'Endpoint fails to respond in time, Scalr may mark the request as<br/>' +
                            'failed or retry it at a later time, depending on your configuration<br/>' +
                            'below (maximum delivery attempts).'
                    }
                }]
            },{
                xtype: 'combo',
                fieldLabel: 'Max. delivery attempts',
                store: [1,2,3,4,5],
                editable: false,
                name: 'attempts',
                maxWidth: 225,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip:
                            '<b>Maximum delivery attempts</b><br/>'+
                            'If delivery of a Webhook Notification fails, Scalr will retry the<br/>' +
                            'request until it succeeds, or until the maximum number of delivery<br/>' +
                            'attempts configured here has been exceeded. <br/>' +
                            'Visit the <a href="https://scalr-wiki.atlassian.net/wiki/x/AgDO" target="_blank">Scalr Wiki</a> for information regarding what Scalr considers<br/>'+
                            'to be delivery failures and the retry schedule.'
                    }
                }]
            },{
                xtype: 'checkbox',
                name: 'skipPrivateGv',
                boxLabel: 'Do not expose private GlobalVariables in webhook payload'
			}, {
				xtype: 'textarea',
				name: 'postData',
				fieldLabel: 'User data',
                labelAlign: 'top',
                plugins: {
                    ptype: 'fieldicons',
                    position: 'label',
                    icons: ['globalvars']
                },
                validateOnChange: false,
                height: 120
            }]
		}],
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
            hidden: isWebhooksReadOnly,
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
                        params,
                        record = frm.getRecord();
					if (frm.isValid()) {
                        params = {
                            endpoints: Ext.encode(frm.findField('endpoints').getValue()),
                            events: Ext.encode(frm.findField('events').getValue()),
                            farms: Ext.encode(frm.findField('farms').getValue())
                        };
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/webhooks/configs/xSave',
                            form: frm,
                            params: params,
							success: function (data) {
								if (!record.store) {
									record = store.add(data.webhook)[0];
								} else {
									record.set(data.webhook);
								}
                                grid.clearSelectedRecord();
                                grid.setSelectedRecord(record);

							}
						});
					}
				}
			}, {
				itemId: 'cancel',
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
                    grid.clearSelectedRecord();
				}
			}, {
				itemId: 'delete',
				xtype: 'button',
				cls: 'x-btn-red',
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
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
            maximize: 'all',
            menuTitle: 'Webhooks',
            menuHref: '#'+Scalr.utils.getUrlPrefix()+'/webhooks/endpoints',
            menuFavorite: Ext.Array.contains(['account', 'environment'], Scalr.scope),
            leftMenu: {
                menuId: 'webhooks',
                itemId: 'configs'
            }
        },
        stateId: 'grid-webhooks-configs',
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
            items: form
        }]
	});

	return panel;
});

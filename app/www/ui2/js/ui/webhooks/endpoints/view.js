Scalr.regPage('Scalr.ui.webhooks.endpoints.view', function (loadParams, moduleParams) {
    var isWebhooksReadOnly = false;
    if (Scalr.scope === 'environment') {
        isWebhooksReadOnly = !Scalr.isAllowed('WEBHOOKS_ENVIRONMENT', 'manage');
    } else if (Scalr.scope === 'account') {
        isWebhooksReadOnly = !Scalr.isAllowed('WEBHOOKS_ACCOUNT', 'manage');
    }
	var store = Ext.create('store.store', {
        model: Ext.define(null, {
            extend: 'Ext.data.Model',
            idProperty: 'endpointId',
            fields: [
                {name: 'endpointId', type: 'string'},
                {name: 'scope', defaultValue: moduleParams['scope']},
                'url',
                'isValid',
                'validationToken',
                'securityKey',
                'webhooks'
            ]
        }),
        data: moduleParams['endpoints'],
		proxy: {
			type: 'ajax',
			url: '/webhooks/endpoints/xList/',
            reader: {
                type: 'json',
                rootProperty: 'endpoints',
                successProperty: 'success'
            }
		},
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
                if (params.endpointId === 'new') {
                    panel.down('#add').handler();
                } else {
                    panel.down('#liveSearch').reset();
                    var record = store.getById(params.endpointId);
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
        type: 'endpoint',
        filterFields: ['url'],
        readOnly: isWebhooksReadOnly,
        columns: [
            {
                text: 'URL',
                flex: 1,
                sortable: true,
                resizable: false,
                dataIndex: 'url',
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getScope(values.scope)]}&nbsp;&nbsp;{url}',
                    {
                        getScope: function(scope){
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('webhookendpoint') + '"/>';
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
                    form.loadRecord(store.createModel({endpointId: 0}));
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
                scopeInfoField.setValue(Scalr.utils.getScopeInfo('endpoint', record.get('scope'), record.get('endpointId')));
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
                var isNewRecord = !record.store,
                    isEndpointValid = record.get('isValid') == 1,
                    frm = this.getForm();

                this.down('#formtitle').setTitle(isNewRecord ? 'New endpoint' : 'Edit endpoint');

                if (!isNewRecord && isEndpointValid) {
                    var usedBy = Ext.Array.map(record.get('webhooks') || [], function(webhookConfig){
                        return '<a href="#'+Scalr.utils.getUrlPrefix(webhookConfig.scope, webhookConfig.envId)+'/webhooks/configs?webhookId='+webhookConfig.webhookId+'">'+webhookConfig.name+'</a>';
                    });
                    this.down('#usedBy').setValue(usedBy.length ? usedBy.join(', ') : '-');
                }
                this.down('#usedBy').setVisible(isEndpointValid && !isNewRecord);
                this.down('#securityKey').setVisible(isEndpointValid && !isNewRecord);

                this.down('#delete').setVisible(!isNewRecord);
                grid.down('#add').toggle(isNewRecord, true);

                if (moduleParams['scope'] === record.get('scope')) {
                    //this.disableButtons(false, record.get('scope'), isEventUsed);
                    frm.findField('url').setReadOnly(isWebhooksReadOnly);
                    this.down('#delete').setDisabled(false);
                    this.down('#save').setDisabled(false);
                } else {
                    //this.disableButtons(true, scope, isEventUsed);
                    frm.findField('url').setReadOnly(true);
                    frm.findField('securityKey').hide;
                    this.down('#usedBy').hide();
                    this.down('#delete').setDisabled(true);
                    this.down('#save').setDisabled(true);
                }

                this.toggleScopeInfo(record);

			},
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
                        record = frm.getRecord();
					if (frm.isValid()) {
                        Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/webhooks/endpoints/xSave',
							form: frm,
							success: function (data) {
                                if (!record.store) {
                                    record = store.add(data.endpoint)[0];
                                } else {
                                    record.set(data.endpoint);
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
            menuSubTitle: 'Endpoints',
            menuHref: '#'+Scalr.utils.getUrlPrefix()+'/webhooks/endpoints',
            menuParentStateId: 'grid-webhooks-configs',
            menuFavorite: Ext.Array.contains(['account', 'environment'], Scalr.scope),
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
            items: form
        }]
	});
	return panel;
});

Scalr.regPage('Scalr.ui.core.api', function (loadParams, moduleParams) {
	var params = moduleParams;
	
	return Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'API settings',
		fieldDefaults: {
			labelWidth: 110
		},
        layout: 'auto',
		items: [{
			xtype: 'fieldset',
			title: 'Enable API access for environment &laquo;' + params['envName'] + '&raquo;',
			checkboxToggle:  true,
            toggleOnTitleClick: true,
            collapsible: true,
			collapsed: !params['api.enabled'],
			checkboxName: 'api.enabled',
            cls: 'x-fieldset-separator-none',
			inputValue: 1,
            defaults:{
                anchor: '100%'
            },
			items: [{
                xtype: 'displayfield',
                fieldLabel: 'API Endpoint',
                value: params['api.endpoint']
            },{
				xtype: 'fieldcontainer',
				layout: 'hbox',
				fieldLabel: 'API Key ID',
				items: [{
					xtype: 'textfield',
					flex: 1,
					name: 'api.access_key',
					readOnly: true,
					value: params['api.access_key']
				}, {
					xtype: 'button',
					margin: '0 0 0 8',
					text: 'Regenerate',
                    width: 110,
					hidden: !params['api.enabled'],
					handler: function () {
						Scalr.Request({
							confirmBox: {
								type: 'action',
								msg: 'Are you sure want to regenerate API keys ? This action will immediately replace your current keys.'
							},
							processBox: {
								type: 'action'
							},
							url: '/core/xRegenerateApiKeys',
							scope: this,
							success: function (data) {
								this.up('form').getForm().setValues({
									'api.access_key': data.keys.id,
									'api.secret_key': data.keys.key
								});
							}
						});
					}
				}]
			}, {
				xtype: 'textarea',
				name: 'api.secret_key',
				fieldLabel: 'API Access Key',
				readOnly: true,
				height: 100,
				value: params['api.secret_key']
			}, {
				xtype:'textarea',
				fieldLabel: 'API access whitelist (by IP address). Example: 67.45.3.7, 67.46.*.*, 91.*.*.*',
				labelAlign: 'top',
				name:'api.ip.whitelist',
				grow: true,
				growMax: 200,
				value: params['api.ip.whitelist']
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
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						url: '/core/xSaveApiSettings/',
						form: this.up('form').getForm(),
						success: function () {
							Scalr.event.fireEvent('close');
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
});

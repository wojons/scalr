Scalr.regPage('Scalr.ui.environments.platform.cloudstack', function (loadParams, moduleParams) {
	var params = moduleParams['params'];

	var isEnabledProp = moduleParams['platform'] + '.is_enabled';
	
	var form = Ext.create('Ext.form.Panel', {
		bodyCls: 'x-panel-body-frame',
		scalrOptions: {
			'modal': true
		},
		width: 600,
		title: 'Environments &raquo; ' + moduleParams.env.name + '&raquo; ' + moduleParams['platformName'],
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 120
		},

		items: [{
			xtype: 'checkbox',
			name: isEnabledProp,
			checked: params[isEnabledProp],
			hideLabel: true,
			boxLabel: 'I want to use '+moduleParams['platformName'],
			listeners: {
				'change': function () {
					if (this.getValue()) {
						form.down('[name="api_key"]').show();
						form.down('[name="api_url"]').show();
						form.down('[name="secret_key"]').show();
					} else {
						form.down('[name="api_key"]').hide();
						form.down('[name="api_url"]').hide();
						form.down('[name="secret_key"]').hide();
					}
				}
			}
		}, {
			xtype: 'textfield',
			fieldLabel: 'API key',
			name: 'api_key',
			value: params['api_key'],
			hidden: !params[isEnabledProp]
		}, {
			xtype: 'textfield',
			fieldLabel: 'Secret key',
			name: 'secret_key',
			value: params['secret_key'],
			hidden: !params[isEnabledProp]
		}, {
			xtype: 'textfield',
			fieldLabel: 'API URL',
			name: 'api_url',
			value: params['api_url'],
			hidden: !params[isEnabledProp]
		}],

		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-bottom-frame',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					if (form.getForm().isValid()) {
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							form: form.getForm(),
							params: { platform: moduleParams['platform']},
							url: '/environments/' + moduleParams.env.id + '/platform/xSaveCloudstack',
							success: function (data) {
								var flag = Scalr.flags.needEnvConfig && data.enabled;
								Scalr.event.fireEvent('update', '/environments/' + moduleParams.env.id + '/edit', moduleParams['platform'], data.enabled);
								if (! flag)
									Scalr.event.fireEvent('close');
							}
						});
					}
				}
			}, {
				xtype: 'button',
				margin: '0 0 0 5',
				hidden: Scalr.flags.needEnvConfig,
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}, {
				xtype: 'button',
				hidden: !Scalr.flags.needEnvConfig,
				margin: '0 0 0 5',
				text: "I'm not using "+moduleParams['platformName']+", let me configure another cloud",
				handler: function () {
					Scalr.event.fireEvent('redirect', '#/environments/' + moduleParams.env.id + '/edit', true);
				}
			}, {
				xtype: 'button',
				hidden: !Scalr.flags.needEnvConfig,
				margin: '0 0 0 5',
				text: 'Do this later',
				handler: function () {
					sessionStorage.setItem('needEnvConfigLater', true);
					Scalr.event.fireEvent('unlock');
					Scalr.event.fireEvent('redirect', '#/dashboard');
				}
			}]
		}]
	});

	if (moduleParams['platform'] == 'idcf') {
		var apiUrl = form.down('[name="api_url"]')
		apiUrl.setValue('https://api.noahcloud.jp/portal/client/api');
		//apiUrl.setReadOnly(true);
	}

	return form;
});

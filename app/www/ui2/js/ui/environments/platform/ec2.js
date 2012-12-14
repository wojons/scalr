Scalr.regPage('Scalr.ui.environments.platform.ec2', function (loadParams, moduleParams) {
	var params = moduleParams['params'];

	var form = Ext.create('Ext.form.Panel', {
		bodyCls: 'x-panel-body-frame',
		scalrOptions: {
			'modal': true
		},
		width: 600,
		title: 'Environments &raquo; ' + moduleParams.env.name + '&raquo; Amazon EC2',
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 130
		},

		items: [{
			xtype: 'displayfield',
			fieldCls: 'x-form-field-info',
			hidden: !Scalr.flags.needEnvConfig,
			value: 'Thanks for signing up to Scalr!<br><br>' +
				'The next step after signing up is to share your EC2 keys with us, or keys from any other infrastructure cloud. We use these keys to make the API calls to the cloud, on your behalf. These keys are stored encrypted on a secured, firewalled server.<br><br>' +
				'You can <a href="http://wiki.scalr.net/Tutorials/Create_an_AWS_account" target="_blank" style="font-weight: bold">get these keys by following this video</a>'
		}, {
			xtype: 'displayfield',
			fieldCls: 'x-form-field-info',
			hidden: Scalr.flags.needEnvConfig,
			value: '<a href="http://wiki.scalr.net/Tutorials/Create_an_AWS_account" target="_blank" style="font-weight: bold">Tutorial: How to obtain all this information.</a>'
		}, {
			xtype: 'checkbox',
			name: 'ec2.is_enabled',
			checked: params['ec2.is_enabled'],
			hideLabel: true,
			boxLabel: 'I want use Amazon EC2',
			listeners: {
				'change': function () {
					if (this.getValue()) {
						form.down('[name="ec2.account_id"]').show();
						form.down('[name="ec2.access_key"]').show();
						form.down('[name="ec2.secret_key"]').show();
						form.down('[name="ec2.certificate"]').show();
						form.down('[name="ec2.private_key"]').show();
					} else {
						form.down('[name="ec2.account_id"]').hide();
						form.down('[name="ec2.access_key"]').hide();
						form.down('[name="ec2.secret_key"]').hide();
						form.down('[name="ec2.certificate"]').hide();
						form.down('[name="ec2.private_key"]').hide();
					}
				}
			}
		}, {
			xtype: 'textfield',
			fieldLabel: 'Account Number',
			width: 320,
			name: 'ec2.account_id',
			value: params['ec2.account_id'],
			hidden: !params['ec2.is_enabled'],
			listeners: {
				'blur': function () {
					this.setValue(this.getValue().replace(/-/g, ''));
				}
			}
		}, {
			xtype: 'textfield',
			fieldLabel: 'Access Key',
			width: 320,
			name: 'ec2.access_key',
			value: params['ec2.access_key'],
			hidden: !params['ec2.is_enabled']
		}, {
			xtype: 'textfield',
			fieldLabel: 'Secret Key',
			width: 320,
			name: 'ec2.secret_key',
			value: params['ec2.secret_key'],
			hidden: !params['ec2.is_enabled']
		}, {
			xtype: 'filefield',
			fieldLabel: 'X.509 Certificate file',
			name: 'ec2.certificate',
			value: params['ec2.certificate'],
			hidden: !params['ec2.is_enabled']
		}, {
			xtype: 'filefield',
			fieldLabel: 'X.509 Private Key file',
			name: 'ec2.private_key',
			value: params['ec2.private_key'],
			hidden: !params['ec2.is_enabled']
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
							url: '/environments/' + moduleParams.env.id + '/platform/xSaveEc2',
							params: {beta: loadParams['beta']},
							success: function (data) {
								
								Scalr.event.fireEvent('unlock');
								
								if (data.demoFarm) {
									Scalr.event.fireEvent('redirect', '#/farms/view?demoFarm=1', true);
								} else {
									var flag = Scalr.flags.needEnvConfig && data.enabled;
									Scalr.event.fireEvent('update', '/environments/' + moduleParams.env.id + '/edit', 'ec2', data.enabled);
									
									if (! flag)
										Scalr.event.fireEvent('close');
								}
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
				text: "I'm not using AWS EC2, let me configure another cloud",
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

	return form;
});

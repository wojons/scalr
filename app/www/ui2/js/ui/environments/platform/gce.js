Scalr.regPage('Scalr.ui.environments.platform.gce', function (loadParams, moduleParams) {
	var params = moduleParams['params'];

	var form = Ext.create('Ext.form.Panel', {
		bodyPadding: 5,
		bodyCls: 'scalr-ui-frame',
		scalrOptions: {
			'modal': true
		},
		width: 600,
		title: 'Environments &raquo; ' + moduleParams.env.name + '&raquo; Google Compute Engine',
		fieldDefaults: {
			anchor: '100%',
			msgTarget: 'side',
			labelWidth: 120
		},

		items: [{
			xtype: 'checkbox',
			name: 'gce.is_enabled',
			checked: params['gce.is_enabled'],
			hideLabel: true,
			boxLabel: 'I want use Google Compute Engine',
			listeners: {
				'change': function () {
					if (this.getValue()) {
						form.down('[name="gce.client_id"]').show();
						form.down('[name="gce.service_account_name"]').show();
						form.down('[name="gce.project_id"]').show();
						form.down('[name="gce.key"]').show();
					} else {
						form.down('[name="gce.client_id"]').hide();
						form.down('[name="gce.service_account_name"]').hide();
						form.down('[name="gce.project_id"]').hide();
						form.down('[name="gce.key"]').hide();
					}
				}
			}
		}, {
			xtype: 'textfield',
			fieldLabel: 'Client ID',
			width: 320,
			name: 'gce.client_id',
			value: params['gce.client_id'],
			hidden: !params['gce.is_enabled']
		}, {
			xtype: 'textfield',
			fieldLabel: 'Email (Service account name)',
			width: 320,
			name: 'gce.service_account_name',
			value: params['gce.service_account_name'],
			hidden: !params['gce.is_enabled']
		}, {
			xtype: 'textfield',
			fieldLabel: 'Project ID',
			width: 320,
			name: 'gce.project_id',
			value: params['gce.project_id'],
			hidden: !params['gce.is_enabled']
		}, {
			xtype: 'filefield',
			fieldLabel: 'Private key',
			name: 'gce.key',
			value: params['gce.key'],
			hidden: !params['gce.is_enabled']
		}],

		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'scalr-ui-docked-bottombar',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				width: 80,
				handler: function() {
					if (form.getForm().isValid()) {
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							form: form.getForm(),
							url: '/environments/' + moduleParams.env.id + '/platform/xSaveGce',
							params: {beta: loadParams['beta']},
							success: function (data) {
								
								Scalr.event.fireEvent('unlock');
								
								if (data.demoFarm) {
									Scalr.event.fireEvent('redirect', '#/farms/view', true);
								} else {
									var flag = Scalr.flags.needEnvConfig && data.enabled;
									Scalr.event.fireEvent('update', '/environments/' + moduleParams.env.id + '/edit', 'gce', data.enabled);
									
									if (! flag)
										Scalr.event.fireEvent('close');
								}
							}
						});
					}
				}
			}, {
				xtype: 'button',
				width: 80,
				margin: '0 0 0 5',
				hidden: Scalr.flags.needEnvConfig,
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}, {
				xtype: 'button',
				width: 280,
				hidden: !Scalr.flags.needEnvConfig,
				margin: '0 0 0 5',
				text: "I'm not using GCE, let me configure another cloud",
				handler: function () {
					Scalr.event.fireEvent('redirect', '#/environments/' + moduleParams.env.id + '/edit', true);
				}
			}, {
				xtype: 'button',
				width: 80,
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

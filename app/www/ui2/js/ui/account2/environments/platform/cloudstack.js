Scalr.regPage('Scalr.ui.account2.environments.platform.cloudstack', function (loadParams, moduleParams) {
	var params = moduleParams['params'],
        cloudInfo = params['_info'] || {};

	var isEnabledProp = moduleParams['platform'] + '.is_enabled';
	
	var sendForm = function(disablePlatform) {
		var frm = form.getForm(),
			r = {
				processBox: {
					type: 'save'
				},
				form: form.getForm(),
				params: { platform: moduleParams['platform']},
				url: '/account/environments/' + moduleParams.env.id + '/platform/xSaveCloudstack',
				success: function (data) {
					var flag = Scalr.flags.needEnvConfig && data.enabled;
					Scalr.event.fireEvent('update', '/account/environments/edit', moduleParams.env.id, moduleParams['platform'], data.enabled);
					if (! flag)
						Scalr.event.fireEvent('close');
				}
			};
		if (disablePlatform) {
			frm.findField(isEnabledProp).setValue(null);
			Ext.apply(r, {
				confirmBox: {
					msg: 'Delete this cloud?',
					type: 'delete',
					ok: 'Delete'
				},
				processBox: {
					msg: 'Deleting...'
				}
			});
		} else {
			frm.findField(isEnabledProp).setValue('on');
			if (!frm.isValid())return;
		}
		
		Scalr.Request(r);
		
	}
	
	var form = Ext.create('Ext.form.Panel', {
		scalrOptions: {
			'modal': true
		},
        bodyCls: 'x-container-fieldset',
		width: 700,
		title: 'Environments &raquo; ' + moduleParams.env.name + '&raquo; ' + moduleParams['platformName'],
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 120
		},

		items: [{
			xtype: 'hidden',
			name: isEnabledProp,
			value: 'on'
		}, {
			xtype: 'textfield',
			fieldLabel: 'API key',
			name: 'api_key',
			value: params['api_key']
		}, {
			xtype: 'textfield',
			fieldLabel: 'Secret key',
			name: 'secret_key',
			value: params['secret_key']
		}, {
			xtype: 'textfield',
			fieldLabel: 'API URL',
			name: 'api_url',
			value: params['api_url']
		},{
            xtype: 'fieldset',
            hidden: Ext.Object.getSize(cloudInfo) === 0,
            style: 'background:#e6e8ec;border-radius:4px;',
            cls: 'x-fieldset-separator-none',
            margin: '24 0 0 0',
            items: [{
                xtype: 'label',
                html: '<b>Cloud details:</b>',
                style: 'display:block',
                margin: '0 0 12 0'
            },{
                xtype: 'displayfield',
                fieldLabel: 'Version',
                value: cloudInfo['cloudstackversion']
            },{
                xtype: 'displayfield',
                fieldLabel: 'Security groups',
                value: '<img width="16" height="16" src="/ui2/images/icons/' + (cloudInfo['securitygroupsenabled'] ? 'true.png' : 'delete_icon_16x16.png') + '" />'
            },{
                xtype: 'displayfield',
                fieldLabel: 'Load balancer',
                value: '<img width="16" height="16" src="/ui2/images/icons/' + (cloudInfo['supportELB'] ? 'true.png' : 'delete_icon_16x16.png') + '" />'
            }]
        }],

		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
            defaults:{
                flex: 1,
                maxWidth: 150
            },
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					sendForm();
				}
			}, {
				xtype: 'button',
				hidden: !!Scalr.flags.needEnvConfig,
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			},{
				xtype: 'button',
				cls: 'x-btn-default-small-red',
				hidden: !params[isEnabledProp] || !!Scalr.flags.needEnvConfig,
				text: 'Delete',
				handler: function() {
					sendForm(true);
				}
			}, {
				xtype: 'button',
				hidden: !Scalr.flags.needEnvConfig,
				text: "I'm not using "+moduleParams['platformName']+", let me configure another cloud",
                flex: 3.4,
                maxWidth: 600,
				handler: function () {
					Scalr.event.fireEvent('redirect', '#/account/environments/?envId=' + moduleParams.env.id , true);
				}
			}, {
				xtype: 'button',
				hidden: !Scalr.flags.needEnvConfig,
				text: 'Do this later',
				handler: function () {
					sessionStorage.setItem('needEnvConfigLater', true);
					Scalr.event.fireEvent('unlock');
					Scalr.event.fireEvent('redirect', '#/dashboard');
				}
			}]
		}]
	});

	if (moduleParams['platform'] == 'idcf' && !params['api_url']) {
		var apiUrl = form.down('[name="api_url"]')
		apiUrl.setValue('https://apis.i.noahcloud.jp/portal/client/api');
		//apiUrl.setReadOnly(true);
	}

	return form;
});

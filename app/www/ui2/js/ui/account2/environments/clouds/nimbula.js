Scalr.regPage('Scalr.ui.account2.environments.clouds.nimbula', function (loadParams, moduleParams) {
	var params = moduleParams['params'];

	var form = Ext.create('Ext.form.Panel', {
        bodyCls: 'x-container-fieldset',
        autoScroll: true,
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 120
		},

		items: [{
			xtype: 'hidden',
			name: 'nimbula.is_enabled',
			value: 'on'
		}, {
			xtype: 'textfield',
			fieldLabel: 'Username',
			name: 'nimbula.username',
			value: params['nimbula.username']
		}, {
			xtype: 'textfield',
			fieldLabel: 'Password',
			name: 'nimbula.password',
			value: params['nimbula.password']
		}, {
			xtype: 'textfield',
			fieldLabel: 'API URL',
			name: 'nimbula.api_url',
			value: params['nimbula.api_url']
		}]
	});

	return form;
});

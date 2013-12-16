Scalr.regPage('Scalr.ui.account2.environments.clouds.openstack', function (loadParams, moduleParams) {
    var keyStoneUrlField,
        keyStoneUrls,
        params = moduleParams['params'],
        isEnabledProp = loadParams['platform'] + '.is_enabled';

    switch (loadParams['platform']) {
        case 'ecs':
            keyStoneUrls = ['https://api.entercloudsuite.com:5000/v2.0', 'https://staging-api.entercloudsuite.com:5000/v2.0'];
            keyStoneUrlField = {
                xtype: 'combo',
                fieldLabel: 'Keystone URL',
                name: 'keystone_url',
                store: keyStoneUrls,
                editable: false,
                value: params['keystone_url'] || keyStoneUrls[0]
            };
        break;
        default:
            keyStoneUrlField = {
                xtype: 'textfield',
                fieldLabel: 'Keystone URL',
                name: 'keystone_url',
                value: params['keystone_url']
            };
        break;
    }

	var form = Ext.create('Ext.form.Panel', {
        bodyCls: 'x-container-fieldset',
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 120
		},

		items: [{
			xtype: 'hidden',
			name: isEnabledProp,
			value: 'on'
		}, keyStoneUrlField, {
			xtype: 'textfield',
			fieldLabel: 'Username',
			name: 'username',
			value: params['username'],
			hidden: false
		}, {
			xtype: 'textfield',
			fieldLabel: 'Password',
			name: 'password',
			value: params['password'],
			hidden: true
		}, {
			xtype: 'textfield',
			fieldLabel: 'API key',
			name: 'api_key',
			value: params['api_key'],
			hidden: true
		}, {
			xtype: 'textfield',
			fieldLabel: 'Tenant name',
			name: 'tenant_name',
			value: params['tenant_name'],
			hidden: true
		}]
	});

	if (loadParams['platform'] == 'rackspacengus') {
		var apiUrl = form.down('[name="keystone_url"]')
		apiUrl.setValue('https://identity.api.rackspacecloud.com/v2.0');
		apiUrl.setReadOnly(true);
		
		form.down('[name="api_key"]').show();
		
		form.down('[name="password"]').hide();
		form.down('[name="tenant_name"]').hide();
	}
	else if (loadParams['platform'] == 'rackspacenguk') {
		var apiUrl = form.down('[name="keystone_url"]')
		apiUrl.setValue('https://lon.identity.api.rackspacecloud.com/v2.0');
		apiUrl.setReadOnly(true);
		
		form.down('[name="api_key"]').show();
		
		form.down('[name="password"]').hide();
		form.down('[name="tenant_name"]').hide();
	} else {
		form.down('[name="api_key"]').hide();
		
		form.down('[name="password"]').show();
		form.down('[name="tenant_name"]').show();
	}

	return form;
});

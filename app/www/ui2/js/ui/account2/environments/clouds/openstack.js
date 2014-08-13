Scalr.regPage('Scalr.ui.account2.environments.clouds.openstack', function (loadParams, moduleParams) {
    var keyStoneUrlField,
        keyStoneUrls,
        params = moduleParams['params'],
        isEnabledProp = loadParams['platform'] + '.is_enabled',
        cloudFeatures = params['features'] || {},
        cloudFeaturesTabs = [];

    switch (loadParams['platform']) {
        case 'ecs':
            keyStoneUrls = ['https://api-legacy.entercloudsuite.com:5000/v2.0', 'https://api.entercloudsuite.com:5000/v2.0', 'https://catalog.entercloudsuite.com:443/v2.0', 'https://staging-api.entercloudsuite.com:5000/v2.0'];
            keyStoneUrlField = {
                xtype: 'combo',
                fieldLabel: 'Keystone URL',
                name: 'keystone_url',
                store: keyStoneUrls,
                editable: false,
                value: params['keystone_url'] || keyStoneUrls[1]
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

    if (Ext.Object.getSize(cloudFeatures) !== 0) {
        Ext.Object.each(cloudFeatures, function(key, value) {
            var items = [];
            Ext.Object.each(value, function(feature, featureValue) {
                items.push({
                    xtype: 'displayfield',
                    fieldLabel: feature,
                    value: Ext.isBoolean(featureValue) ? '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-' + (featureValue ? 'ok' : 'fail') + '" />' : featureValue
                });
            });
            cloudFeaturesTabs.push({
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                tabConfig: {
                    title: key
                },
				defaults: {
					anchor: '100%',
					labelWidth: 210
				},
                items: items
            });
        });
    }


	var form = Ext.create('Ext.form.Panel', {
        bodyCls: 'x-container-fieldset',
        autoScroll: true,
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
		}, {
            xtype: 'checkbox',
            name: 'ssl_verifypeer',
            width: 260,
            checked: params['ssl_verifypeer'],
            hidden: !(loadParams['platform'] == 'openstack' || loadParams['platform'] == 'ecs' || loadParams['platform'] == 'nebula'),
            boxLabel: 'Enable SSL certificate verification for Keystone endpoints'
        }, {
            xtype: 'tabpanel',
            itemId: 'tabs',
            cls: 'x-tabs-dark',
            margin: '24 0 0 0',
            hidden: Ext.Object.getSize(cloudFeatures) === 0,
            items: cloudFeaturesTabs
        }]
	});

	if (loadParams['platform'] === 'rackspacengus') {
		var apiUrl = form.down('[name="keystone_url"]')
		apiUrl.setValue('https://identity.api.rackspacecloud.com/v2.0');
		apiUrl.setReadOnly(true);
		
		form.down('[name="api_key"]').show();
		
		form.down('[name="password"]').hide();
		form.down('[name="tenant_name"]').hide();
	} else if (loadParams['platform'] === 'rackspacenguk') {
		var apiUrl = form.down('[name="keystone_url"]')
		apiUrl.setValue('https://lon.identity.api.rackspacecloud.com/v2.0');
		apiUrl.setReadOnly(true);
		
		form.down('[name="api_key"]').show();
		
		form.down('[name="password"]').hide();
		form.down('[name="tenant_name"]').hide();
	} else {
        if (loadParams['platform'] === 'ecs') {
            form.down('[name="keystone_url"]').setVisible(!!Scalr.flags['betaMode']);
        }

        form.down('[name="api_key"]').hide();
		
		form.down('[name="password"]').show();
		form.down('[name="tenant_name"]').show();
	}

	return form;
});

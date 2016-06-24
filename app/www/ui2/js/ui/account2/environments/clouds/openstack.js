Scalr.regPage('Scalr.ui.account2.environments.clouds.openstack', function (loadParams, moduleParams) {
    var params = moduleParams['params'],
        isEnabledProp = loadParams['platform'] + '.is_enabled',
        cloudFeatures = params['features'] || {},
        cloudFeaturesTabs = [],
        hideSslVerifyPeer = loadParams['platform'] == 'rackspacengus' || loadParams['platform'] == 'rackspacenguk';

    if (Ext.Object.getSize(cloudFeatures) !== 0) {
        Ext.Object.each(cloudFeatures, function(key, value) {
            var items = [];
            if (Ext.isString(value)) {
                items.push({
                    xtype: 'displayfield',
                    cls: 'x-form-field-warning',
                    margin: '0 0 20 0',
                    value: value
                });
            } else {
                Ext.Object.each(value, function(feature, featureValue) {
                    items.push({
                        xtype: 'displayfield',
                        fieldLabel: feature,
                        margin: 0,
                        value: Ext.isBoolean(featureValue) ? (featureValue ? '<div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div>' : '&nbsp;&ndash;') : featureValue
                    });
                });
            }
            cloudFeaturesTabs.push({
                xtype: 'container',
                cls: 'x-container-fieldset',
                tabConfig: {
                    title: key
                },
				defaults: {
					anchor: '100%',
					labelWidth: 240
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
		},{
            xtype: 'textfield',
            fieldLabel: 'Keystone URL',
            name: 'keystone_url',
            value: params['keystone_url'],
            validator: function(value) {
                return /^https?:\/\//.test(value) || 'Keystone URL must begin with http:// or https://';
            },
            onKeystoneUrlChange: function() {
                var isV3 = (this.getValue() || '').indexOf('v3') !== -1;
                this.next('[name="domain_name"]').setVisible(isV3).setDisabled(!isV3);
            },
            enableKeyEvents: true,
            listeners: {
                afterrender: {
                    fn: 'onKeystoneUrlChange',
                    single: true
                },
                change: 'onKeystoneUrlChange'
            }
        },{
			xtype: 'textfield',
			fieldLabel: 'Domain name',
			name: 'domain_name',
			value: params['domain_name'],
			hidden: true,
            disabled: true
        },{
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
			hidden: true,
            selectOnFocus: true
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
			hidden: true,
            selectOnFocus: true
		}, {
            xtype: 'checkbox',
            name: 'ssl_verifypeer',
            width: 260,
            checked: Ext.isEmpty(params) && !hideSslVerifyPeer || params['ssl_verifypeer'] == 1,
            hidden: hideSslVerifyPeer,
            boxLabel: 'Enable SSL certificate verification for Keystone endpoints'
        }, {
            xtype: 'tabpanel',
            itemId: 'tabs',
            cls: 'x-tabs-dark',
            margin: '24 0 0 0',
            hidden: Ext.Object.getSize(cloudFeatures) === 0,
            items: cloudFeaturesTabs
        },{
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            margin: '0 0 20 0',
            hidden: !params['info'] || !params['info']['exception'],
            value: params['info'] && params['info']['exception'] ? params['info']['exception'] : ''
            
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
        form.down('[name="api_key"]').hide();

		form.down('[name="password"]').show();
		form.down('[name="tenant_name"]').show();
	}

	return form;
});

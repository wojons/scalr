Scalr.regPage('Scalr.ui.account2.environments.clouds.cloudstack', function (loadParams, moduleParams) {
	var params = moduleParams['params'],
        cloudInfo = params['_info'] || {};

	var isEnabledProp = loadParams['platform'] + '.is_enabled';

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
		}, {
			xtype: 'textfield',
			fieldLabel: 'API URL',
			name: 'api_url',
			value: params['api_url'],
            validator: function(value) {
                return /^https?:\/\//.test(value) || 'API URL must begin with http:// or https://';
            }
		}, {
			xtype: 'textfield',
			fieldLabel: 'API key',
			name: 'api_key',
			value: params['api_key'],
            allowBlank: false
		}, {
			xtype: 'textfield',
			fieldLabel: 'Secret key',
			name: 'secret_key',
			value: params['secret_key'],
            allowBlank: false,
            selectOnFocus: true
		},{
            xtype: 'fieldset',
            hidden: Ext.Object.getSize(cloudInfo) === 0,
            style: 'background:#e1ebf4;border-radius:0;',
            cls: 'x-fieldset-separator-none',
            title: 'Cloud details',
            margin: '24 0 0 0',
            defaults: {
                margin: 0
            },
            items: [{
                xtype: 'displayfield',
                fieldLabel: 'Version',
                value: cloudInfo['cloudstackversion']
            },{
                xtype: 'displayfield',
                fieldLabel: 'Security groups',
                value: cloudInfo['securitygroupsenabled'] ? '<div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div>' : '&nbsp;&ndash;'
            },{
                xtype: 'displayfield',
                fieldLabel: 'Load balancer',
                value: cloudInfo['supportELB'] ? '<div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div>' : '&nbsp;&ndash;'
            }]
        }]
	});

	if (loadParams['platform'] == 'idcf' && !params['api_url']) {
		var apiUrl = form.down('[name="api_url"]');
		apiUrl.setValue('https://apis.i.noahcloud.jp/portal/client/api');
		//apiUrl.setReadOnly(true);
	}

	return form;
});

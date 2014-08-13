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
			value: params['api_url']
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
                value: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-' + (cloudInfo['securitygroupsenabled'] ? 'ok' : 'fail') + '" />'
            },{
                xtype: 'displayfield',
                fieldLabel: 'Load balancer',
                value: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-' + (cloudInfo['supportELB'] ? 'ok' : 'fail') + '" />'
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

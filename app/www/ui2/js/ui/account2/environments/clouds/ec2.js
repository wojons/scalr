Scalr.regPage('Scalr.ui.account2.environments.clouds.ec2', function (loadParams, moduleParams) {
	var params = moduleParams['params'];

	return Ext.create('Ext.form.Panel', {
        bodyCls: 'x-container-fieldset',
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 130
		},

		items: [{
			xtype: 'displayfield',
			cls: 'x-form-field-info',
			value: '<a href="http://wiki.scalr.net/Tutorials/Create_an_AWS_account" target="_blank" style="font-weight: bold">Getting started with Scalr and EC2 tutorial</a>'
		}, {
			xtype: 'hidden',
			name: 'ec2.is_enabled',
			value: 'on'
		}, {
			xtype: 'textfield',
			fieldLabel: 'Account Number',
			width: 320,
			name: 'ec2.account_id',
			value: params['ec2.account_id'],
			listeners: {
				blur: function () {
					this.setValue(this.getValue().replace(/-/g, ''));
				}
			}
		}, {
			xtype: 'textfield',
			fieldLabel: 'Access Key ID',
			width: 320,
			name: 'ec2.access_key',
			value: params['ec2.access_key']
		}, {
			xtype: 'textfield',
			fieldLabel: 'Secret Access Key',
			width: 320,
			name: 'ec2.secret_key',
			value: params['ec2.secret_key']
		}, {
			xtype: 'filefield',
			fieldLabel: 'X.509 Certificate file',
			name: 'ec2.certificate',
            hidden: Ext.isEmpty(params['ec2.certificate']),
			value: params['ec2.certificate']
		}, {
			xtype: 'filefield',
			fieldLabel: 'X.509 Private Key file',
			name: 'ec2.private_key',
            hidden: Ext.isEmpty(params['ec2.private_key']),
			value: params['ec2.private_key']
		}]
	});
});

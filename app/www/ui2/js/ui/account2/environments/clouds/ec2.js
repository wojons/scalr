Scalr.regPage('Scalr.ui.account2.environments.clouds.ec2', function (loadParams, moduleParams) {
	var params = moduleParams['params'];

	return Ext.create('Ext.form.Panel', {
        bodyCls: 'x-container-fieldset',
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 140
		},
        autoScroll: true,
		items: [{
			xtype: 'hidden',
			name: 'ec2.is_enabled',
			value: 'on'
		}, {
			xtype: 'displayfield',
			fieldLabel: 'Account Number',
			name: 'ec2.account_id',
			value: params['ec2.account_id'],
            hidden: true,
			listeners: {
				afterrender: function () {
                    if (this.getValue())
                        this.show();
				}
			}
        }, {
			xtype: 'displayfield',
			fieldLabel: 'IAM User ARN',
			value: params['arn'],
            hidden: true,
			listeners: {
				afterrender: function () {
                    if (this.getValue())
                        this.show();
				}
			}
        }, {
			xtype: 'textfield',
			fieldLabel: 'Access Key ID',
			name: 'ec2.access_key',
			value: params['ec2.access_key']
		}, {
			xtype: 'textfield',
			fieldLabel: 'Secret Access Key',
			name: 'ec2.secret_key',
			value: params['ec2.secret_key']
		}, {
            xtype: 'buttongroupfield',
            fieldLabel: 'Account type',
            name: 'ec2.account_type',
            value: params['ec2.account_type'] || 'regular',
            defaults: {
                width: 120
            },
            items: [{
                text: 'Regular',
                value: 'regular'
            },{
                text: 'GovCloud',
                value: 'gov-cloud'
            },{
                text: 'AWS China',
                value: 'cn-cloud'
            }]
		},{
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

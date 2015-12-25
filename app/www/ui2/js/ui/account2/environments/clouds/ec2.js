Scalr.regPage('Scalr.ui.account2.environments.clouds.ec2', function (loadParams, moduleParams) {
	var params = moduleParams['params'];

	return Ext.create('Ext.form.Panel', {
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 150
		},
        autoScroll: true,
        items: [{
            xtype: 'fieldset',
            //cls: 'x-container-fieldset',
            cls: 'x-fieldset-separator-none',
            items: [{
                xtype: 'hidden',
                name: 'ec2.is_enabled',
                value: 'on'
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Account Number',
                name: 'account_id',
                value: params['account_id'],
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
                name: 'access_key',
                value: params['access_key']
            }, {
                xtype: 'textfield',
                fieldLabel: 'Secret Access Key',
                name: 'secret_key',
                value: params['secret_key'],
                selectOnFocus: true
            }, {
                xtype: 'buttongroupfield',
                fieldLabel: 'Account type',
                name: 'account_type',
                value: params['account_type'] || 'regular',
                defaults: {
                    width: 120
                },
                listeners: {
                    change: function(comp, value) {
                        var field = comp.up('form').down('[name="detailed_billing.region"]'),
                            region;
                        field.reset();
                        field.store.load({data: params['cloudLocations'][value]});
                        region = field.findRecordByValue('us-east-1');
                        if (!region) {
                            region = field.store.first();
                        }
                        field.setValue(region);
                    }
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
                name: 'certificate',
                hidden: Ext.isEmpty(params['certificate']),
                value: params['certificate']
            }, {
                xtype: 'filefield',
                fieldLabel: 'X.509 Private Key file',
                name: 'private_key',
                hidden: Ext.isEmpty(params['private_key']),
                value: params['private_key']
            }]
		}, {
			xtype: 'fieldset',
			title: 'Enable detailed billing',
            cls: 'x-fieldset-separator-none',
            checkboxToggle: true,
            checkboxName: 'detailed_billing.enabled',
            collapsible: true,
			collapsed: params['detailed_billing.enabled'] != 1,
            toggleOnTitleClick: true,
            hidden: !Scalr.flags['analyticsEnabled'],
            items: [{
                xtype: 'combo',
                fieldLabel: 'Billing bucket region',
                name: 'detailed_billing.region',
                valueField: 'name',
                displayField: 'name',
                value: params['detailed_billing.region'] || (!params['account_type'] || params['account_type'] === 'regular' ? 'us-east-1' : params['cloudLocations'][params['account_type']][0]),
                store: {
                    fields: ['id', 'name'],
                    proxy: 'object',
                    data: params['cloudLocations'][params['account_type'] || 'regular']
                },
                editable: false,
            },{
                xtype: 'textfield',
                fieldLabel: 'Billing bucket name',
                name: 'detailed_billing.bucket',
                value: params['detailed_billing.bucket'],
                listeners: {
                    change: function (field) {
                        field.next('[name=detailed_billing.payer_account]')
                            .validate();
                    }
                }
            }, {
                xtype: 'textfield',
                fieldLabel: 'Payer Account',
                name: 'detailed_billing.payer_account',
                value: params['detailed_billing.payer_account'],
                validator: function (value) {
                    var me = this;

                    var hasValue = !Ext.isEmpty(value);
                    var bucketName = me.prev('[name=detailed_billing.bucket]').getValue();

                    if (hasValue && Ext.isEmpty(bucketName)) {
                        return 'Billing Bucket Name is required for this field';
                    } else if (hasValue && !/^\d+$/.test(value)) {
                        return 'This field can contain only numbers';
                    }

                    return true;
                }
            }]
        }]
	});
});

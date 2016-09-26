Scalr.regPage('Scalr.ui.tools.aws.vpc.create', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
        title: 'Create VPC',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modalWindow: true
		},
		width: 560,
        defaults: {
            labelWidth: 135
        },
        bodyCls: 'x-container-fieldset',
		items: [{
            xtype: 'displayfield',
            name: 'cloudLocation',
            submitValue: true,
            fieldLabel: 'VPC region'
        },{
            xtype: 'textfield',
            name: 'name',
            fieldLabel: 'Name'
        },{
            xtype: 'textfield',
            name: 'cidr_block',
            fieldLabel: 'Cidr block'
        },{
            xtype: 'buttongroupfield',
            name: 'tenancy',
            fieldLabel: 'Instance tenancy',
            defaults: {
                width: 110
            },
            items: [{
                text: 'Default',
                value: 'default'
            },{
                text: 'Dedicated',
                value: 'dedicated'
            }]
        }],

		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Create',
				handler: function() {
					if (form.getForm().isValid()) {
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							params: form.getValues(),
							form: form.getForm(),
							url: '/tools/aws/vpc/xCreate',
							success: function (data) {
								if (data['vpc']) {
									Scalr.event.fireEvent('update', '/tools/aws/vpc/create', data['vpc']);
								}
								form.close();
							}
						});
					}
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					form.close();
				}
			}]
		}]
	});

	form.getForm().setValues({
        cloudLocation: loadParams['cloudLocation'],
		cidr_block: '10.0.0.0/16',
		tenancy: 'default'
	});

	return form;
});

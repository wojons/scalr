Scalr.regPage('Scalr.ui.tools.aws.vpc.createNetworkInterface', function (loadParams, moduleParams) {
    var subnet = moduleParams['subnet'];
	var form = Scalr.utils.Window({
        xtype: 'form',
		scalrOptions: {
			modalWindow: true
		},
		width: 600,
        defaults: {
            labelWidth: 120
        },
        bodyCls: 'x-container-fieldset x-fieldset-no-bottom-padding',
		items: [{
            xtype: 'component',
            cls: 'x-fieldset-subheader',
            html: 'Create network interface'
        },{
            xtype: 'displayfield',
            cls: 'x-form-field-info',
            value: 'Elastic IP will be automatically created and associated with this Network Interface'
        },{
            xtype: 'displayfield',
            fieldLabel: 'Subnet',
            labelWidth: 70,
            value: subnet ? subnet['subnetId'] + ' (' + subnet['cidrBlock'] + ') ' + subnet['availabilityZone'] : 'Subnet is not selected'
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
                    Scalr.Request({
                        processBox: {
                            type: 'save'
                        },
                        params: {
                            cloudLocation: loadParams['cloudLocation'],
                            vpcId: loadParams['vpcId'],
                            subnetId: subnet['subnetId']
                        },
                        url: '/tools/aws/vpc/xCreateNetworkInterface',
                        success: function (data) {
                            if (data['ni']) {
                                Scalr.event.fireEvent('update', '/tools/aws/vpc/createNetworkInterface', data['ni']);
                            }
                            form.close();
                        }
                    });
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

	return form;
});

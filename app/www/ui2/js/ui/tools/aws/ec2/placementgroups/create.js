Scalr.regPage('Scalr.ui.tools.aws.ec2.placementgroups.create', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
		title: 'Create Placement Group',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modalWindow: true
		},
		width: 500,
        defaults: {
            labelWidth: 70
        },
        bodyCls: 'x-container-fieldset x-fieldset-no-bottom-padding',
		items: [{
            xtype: 'displayfield',
            name: 'cloudLocation',
            fieldLabel: 'Location',
            submitValue: true,
            value: loadParams['cloudLocation']
        },{
            xtype: 'textfield',
            name: 'groupName',
            fieldLabel: 'Name',
            allowBlank: false
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
							url: '/tools/aws/ec2/xCreatePlacementGroup',
							success: function (data) {
								if (data['group']) {
									Scalr.event.fireEvent('update', '/tools/aws/ec2/createPlacementGroup', data['group']);
								}
								Scalr.event.fireEvent('close');
							}
						});
					}
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});

	return form;
});

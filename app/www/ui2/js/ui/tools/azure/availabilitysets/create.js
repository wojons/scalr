Scalr.regPage('Scalr.ui.tools.azure.availabilitysets.create', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
		title: 'Create Availability Set',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modalWindow: true
		},
		width: 460,
        defaults: {
            labelWidth: 130
        },
        bodyCls: 'x-container-fieldset x-fieldset-no-bottom-padding',
        defaultFocus: '[name="name"]',
		items: [{
            xtype: 'textfield',
            name: 'cloudLocation',
            fieldLabel: 'Location',
            readOnly: true,
            hideInputOnReadOnly: true,
            value: loadParams['cloudLocation']
        },{
            xtype: 'textfield',
            name: 'resourceGroup',
            fieldLabel: 'Resource Group',
            readOnly: true,
            hideInputOnReadOnly: true,
            value: loadParams['resourceGroup']
        },{
            xtype: 'textfield',
            name: 'name',
            allowBlank: false,
            fieldLabel: 'Name'
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
							form: form.getForm(),
							url: '/tools/azure/availabilitySets/xCreate',
							success: function (data) {
								if (data['availabilitySet']) {
									Scalr.event.fireEvent('update', '/tools/azure/availabilitySets/create', data['availabilitySet']);
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

	return form;
});

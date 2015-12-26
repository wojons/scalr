Scalr.regPage('Scalr.ui.tools.azure.resourcegroups.create', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
		title: 'Create Resource Group',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modalWindow: true
		},
		width: 460,
        defaults: {
            labelWidth: 90
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
							url: '/tools/azure/resourceGroups/xCreate',
							success: function (data) {
								if (data['resourceGroup']) {
									Scalr.event.fireEvent('update', '/tools/azure/resourceGroups/create', data['resourceGroup']);
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

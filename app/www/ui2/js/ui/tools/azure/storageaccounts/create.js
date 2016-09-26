Scalr.regPage('Scalr.ui.tools.azure.storageaccounts.create', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
		title: 'Create Storage Account',
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
            xtype: 'combo',
            name: 'accountType',
            editable: false,
            fieldLabel: 'Storage Account',
            store: ['Standard_LRS', 'Standard_ZRS', 'Standard_GRS', 'Standard_RAGRS', 'Premium_LRS'],
            value: 'Standard_LRS'
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
							url: '/tools/azure/storageAccounts/xCreate',
							success: function (data) {
								if (data['storageAccount']) {
									Scalr.event.fireEvent('update', '/tools/azure/storageAccounts/create', data['storageAccount']);
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

Scalr.regPage('Scalr.ui.tools.azure.virtualnetworks.create', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
		title: 'Create Virtual Network',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modalWindow: true
		},
		width: 640,
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
        },{
            xtype: 'textfield',
            fieldLabel: 'Address space',
            name: 'addressPrefix',
            allowBlank: false,
            emptyText: 'ex. 192.168.0.0/16',
            tagRegexText: 'Invlid IP range',
            tagRegex: /^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+\/[0-9]+$/
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
							url: '/tools/azure/virtualnetworks/xCreate',
							success: function (data) {
								if (data['virtualNetwork']) {
									Scalr.event.fireEvent('update', '/tools/azure/virtualNetworks/create', data['virtualNetwork']);
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

Scalr.regPage('Scalr.ui.tools.azure.virtualnetworks.createSubnet', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
		title: 'Create Virtual Network Subnet',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modalWindow: true
		},
		width: 640,
        bodyStyle: 'padding-right: 42px',
        defaults: {
            labelWidth: 130
        },
        scrollable: false,
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
            name: 'virtualNetwork',
            fieldLabel: 'Virtual Network',
            readOnly: true,
            hideInputOnReadOnly: true,
            value: loadParams['virtualNetwork']
        },{
            xtype: 'textfield',
            name: 'name',
            allowBlank: false,
            fieldLabel: 'Name'
        },{
            xtype: 'textfield',
            fieldLabel: 'Address range',
            name: 'addressPrefix',
            allowBlank: false,
            plugins: {
                ptype: 'fieldicons',
                align: 'right',
                position: 'outer',
                icons: [{id: 'info', tooltip: 'The address range in CIDR notation. It must be contained by the address space of the virtual network and by one of the standard private address spaces: 10.0.0.0/8, 172.160.0.0/12, or 192.168.0.0/16.'}]
            }
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
							url: '/tools/azure/virtualnetworks/xCreateSubnet',
							success: function (data) {
								if (data['subnet']) {
									Scalr.event.fireEvent('update', '/tools/azure/virtualNetworks/createSubnet', data['subnet']);
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

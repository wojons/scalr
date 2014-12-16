Scalr.regPage('Scalr.ui.account2.variables.view', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		scalrOptions: {
            title: 'Account management &raquo; Global variables',
			maximize: 'all',
			leftMenu: {
				menuId: 'settings',
				itemId: 'variables',
                showPageTitle: true
			}
		},
		fieldDefaults: {
			labelWidth: 110
		},
        cls: 'x-panel-column-left',
        layout: 'fit',
        autoScroll: true,
		items: {
            xtype: 'variablefield',
            name: 'variables',
            currentScope: 'account',
            value: moduleParams.variables
        },
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons-mini',
            weight: 10,
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					if (this.up('form').getForm().isValid())
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/account/variables/xSaveVariables/',
							form: this.up('form').getForm(),
							success: function () {
								Scalr.event.fireEvent('refresh');
							}
						});
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
});

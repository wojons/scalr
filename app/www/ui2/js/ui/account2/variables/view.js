Scalr.regPage('Scalr.ui.account2.variables.view', function (loadParams, moduleParams) {
    var pageTitle = 'Account global variables';
	return Ext.create('Ext.form.Panel', {
		scalrOptions: {
			title: pageTitle,
			maximize: 'all',
			leftMenu: {
				menuId: 'settings',
				itemId: 'variables'
			}
		},
		fieldDefaults: {
			labelWidth: 110
		},
        cls: 'x-panel-column-left',
        layout: 'auto',
        autoScroll: true,
		items: [{
			xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            title: pageTitle,
			items: [{
				xtype: 'variablefield',
				name: 'variables',
				currentScope: 'account',
				value: moduleParams.variables,
                maxWidth: 1100
			}]
		}],

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

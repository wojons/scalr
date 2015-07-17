Scalr.regPage('Scalr.ui.account2.variables.view', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		scalrOptions: {
			maximize: 'all',
            menuTitle: 'Global variables',
            menuHref: '#/account/variables',
            menuFavorite: true
		},
		fieldDefaults: {
			labelWidth: 110
		},
        cls: 'x-panel-column-left x-panel-column-left-with-tabs',
        stateId: 'grid-account-variables',
        layout: 'fit',
        autoScroll: true,
		items: {
            xtype: 'variablefield',
            cls: 'x-panel-column-left',
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

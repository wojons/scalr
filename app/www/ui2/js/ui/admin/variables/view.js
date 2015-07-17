Scalr.regPage('Scalr.ui.admin.variables.view', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		fieldDefaults: {
			labelWidth: 110
		},
        scalrOptions: {
            maximize: 'all',
            menuTitle: 'Global variables',
            menuHref: '#/admin/variables',
            menuFavorite: true
        },
        stateId: 'grid-admin-variables-view',
        layout: 'fit',
		items: {
            xtype: 'variablefield',
            cls: 'x-panel-column-left',
            name: 'variables',
            currentScope: 'scalr',
            value: moduleParams.variables
        },
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
            cls: 'x-docked-buttons-mini',
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
							url: '/admin/variables/xSaveVariables/',
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

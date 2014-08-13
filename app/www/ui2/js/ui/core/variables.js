Scalr.regPage('Scalr.ui.core.variables', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		width: 1300,
		title: 'Environment global variables',
		fieldDefaults: {
			labelWidth: 110
		},
        layout: 'auto',
		items: [{
			xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
			items: [{
				xtype: 'variablefield',
				name: 'variables',
				currentScope: 'env',
				value: moduleParams.variables
			}]
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
				text: 'Save',
				handler: function() {
					if (this.up('form').getForm().isValid())
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/core/xSaveVariables/',
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

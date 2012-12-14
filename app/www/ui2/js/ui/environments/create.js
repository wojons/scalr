Scalr.regPage('Scalr.ui.environments.create', function (loadParams, moduleParams) {

	var form = Ext.create('Ext.form.Panel', {
		bodyCls: 'x-panel-body-frame',
		width: 700,
		title: 'Environments &raquo; Create',
		fieldDefaults: {
			anchor: '100%'
		},

		items: [{
			xtype: 'fieldset',
			title: 'General information',
			labelWidth: 120,
			items:[{
				xtype: 'textfield',
				name: 'name',
				fieldLabel: 'Name',
				value: moduleParams['name']
			}]
		}],

		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-bottom-frame',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function () {
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						url: '/environments/xCreate',
						form: form.getForm(),
						success: function (data) {
							Scalr.event.fireEvent('update', '/environments/create', data.env);
							Scalr.event.fireEvent('close');
						}
					});
				}
			}, {
				xtype: 'button',
				margin: '0 0 0 5',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});

	return form;
});

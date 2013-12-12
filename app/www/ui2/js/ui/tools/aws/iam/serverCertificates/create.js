Scalr.regPage('Scalr.ui.tools.aws.iam.serverCertificates.create', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		width: 900,
		title: 'Server Certificates &raquo; Add',
		fieldDefaults: {
			anchor: '100%'
		},

		items: [{
			xtype: 'fieldset',
			title: 'General information',
			labelWidth: 140,
			items: [{
				xtype: 'textfield',
				name: 'name',
				fieldLabel: 'Name'
			},{
				xtype: 'textfield',
				name: 'certificate',
				fieldLabel: 'Certificate',
				inputType: 'file'
			}, {
				xtype: 'textfield',
				name: 'privateKey',
				fieldLabel: 'Private key',
				inputType: 'file'
			}, {
				xtype: 'textfield',
				name: 'certificateChain',
				fieldLabel: 'Certificate chain',
				inputType: 'file'
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
				text: 'Upload',
				handler: function() {
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						form: this.up('form').getForm(),
						url: '/tools/aws/iam/serverCertificates/xSave',
						success: function () {
							Scalr.event.fireEvent('redirect', '#/tools/aws/iam/serverCertificates/view');
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

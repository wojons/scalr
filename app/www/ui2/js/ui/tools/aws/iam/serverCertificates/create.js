Scalr.regPage('Scalr.ui.tools.aws.iam.serverCertificates.create', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'Server Certificates &raquo; Add',
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 110
		},
		items: [{
			xtype: 'fieldset',
			title: 'General information',
			items: [{
				xtype: 'textfield',
				name: 'name',
				fieldLabel: 'Name',
                allowBlank: false
			},{
				xtype: 'filefield',
				name: 'certificate',
				fieldLabel: 'Certificate',
                allowBlank: false
			}, {
				xtype: 'filefield',
				name: 'privateKey',
				fieldLabel: 'Private key',
                allowBlank: false
			}, {
				xtype: 'filefield',
				name: 'certificateChain',
				fieldLabel: 'Certificate chain'
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
                    if (this.up('form').getForm().isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: this.up('form').getForm(),
                            url: '/tools/aws/iam/serverCertificates/xSave',
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
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

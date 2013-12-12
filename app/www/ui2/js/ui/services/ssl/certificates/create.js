Scalr.regPage('Scalr.ui.services.ssl.certificates.create', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		title: moduleParams.cert ? 'Services &raquo; Ssl &raquo; Certificates &raquo; Edit' : 'Services &raquo; Ssl &raquo; Certificates &raquo; Create',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modal: true
		},
		width: 600,

		items: [{
			xtype: 'fieldset',
			items: [{
                xtype: 'textfield',
                fieldLabel: 'Name',
                name: 'name',
                allowBlank: false
            }, {
                xtype: 'hidden',
                name: 'id'
            },{
				xtype: 'filefield',
				name: 'sslCert',
				fieldLabel: 'Certificate'
			}, {
				xtype: 'filefield',
				name: 'sslPkey',
				fieldLabel: 'Private key'
			}, {
				xtype: 'filefield',
				name: 'sslCabundle',
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
				text: 'Save',
				handler: function() {
					if (form.getForm().isValid())
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							form: form.getForm(),
							url: '/services/ssl/certificates/xSave/',
							success: function (data) {
								if (data.cert) {
									Scalr.event.fireEvent('update', '/services/ssl/certificates/create', data.cert);
								}
								Scalr.event.fireEvent('close');
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

	if (moduleParams.cert)
		form.getForm().setValues(moduleParams.cert);

	return form;
});

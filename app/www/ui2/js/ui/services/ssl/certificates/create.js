Scalr.regPage('Scalr.ui.services.ssl.certificates.create', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		title: 'SSL certificates &raquo; ' + (moduleParams.cert ? 'Edit' : 'Create'),
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modal: true
		},
		width: 600,
        fieldDefaults: {
            labelWidth: 140
        },
		items: [{
			xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
			items: [{
                xtype: 'textfield',
                fieldLabel: 'Name',
                name: 'name',
                allowBlank: false,
                width: 489
            }, {
                xtype: 'hidden',
                name: 'id'
            }, {
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'filefield',
                    name: 'certificate',
                    flex: 1,
                    fieldLabel: 'Certificate',
                    listeners: {
                        afterrender: function() {
                            if (! this.getValue())
                                this.next().disable();
                        }
                    }
                }, {
                    xtype: 'button',
                    ui: 'action',
                    margin: '0 0 0 4',
                    cls: 'x-btn-action-delete',
                    tooltip: 'Click here to delete previously uploaded certificate',
                    handler: function() {
                        this.prev().setValue();
                        this.next().setValue(1);
                        this.disable();
                    }
                }, {
                    xtype: 'hidden',
                    name: 'certificateClear'
                }]
            }, {
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'filefield',
                    name: 'caBundle',
                    flex: 1,
                    fieldLabel: 'Certificate chain',
                    listeners: {
                        afterrender: function() {
                            if (! this.getValue())
                                this.next().disable();
                        }
                    }
                }, {
                    xtype: 'button',
                    ui: 'action',
                    margin: '0 0 0 4',
                    cls: 'x-btn-action-delete',
                    tooltip: 'Click here to delete previously uploaded certificate chain',
                    handler: function() {
                        this.prev().setValue();
                        this.next().setValue(1);
                        this.disable();
                    }
                }, {
                    xtype: 'hidden',
                    name: 'caBundleClear'
                }]
			}, {
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'filefield',
                    name: 'privateKey',
                    flex: 1,
                    fieldLabel: 'Private key',
                    listeners: {
                        afterrender: function() {
                            if (! this.getValue())
                                this.next().disable();
                        }
                    }
                }, {
                    xtype: 'button',
                    ui: 'action',
                    margin: '0 0 0 4',
                    cls: 'x-btn-action-delete',
                    tooltip: 'Click here to delete previously uploaded private key',
                    handler: function() {
                        this.prev().setValue();
                        this.next().setValue(1);
                        this.disable();
                    }
                }, {
                    xtype: 'hidden',
                    name: 'privateKeyClear'
                }]
			}, {
                xtype: 'textfield',
                inputType:'password',
                width: 489,
                fieldLabel: 'Private key password',
                name: 'privateKeyPassword'
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

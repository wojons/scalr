Scalr.regPage('Scalr.ui.core.security', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'Security',
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 130
		},
        layout: 'auto',
		items: [{
			xtype: 'fieldset',
            title: 'Change password',
            hidden: Scalr.flags['authMode'] == 'ldap',
			items: [{
				xtype: 'textfield',
				inputType:'password',
				name: 'password',
				allowBlank: false,
				fieldLabel: 'New password',
				value: '******'
			},{
				xtype: 'textfield',
				inputType:'password',
				name: 'cpassword',
				allowBlank: false,
				fieldLabel: 'Confirm password',
				value: '******'
			}]
		}, {
			xtype: 'fieldset',
			hidden: !moduleParams['security2fa'],
			title: 'Two-factor authentication based on <a href="http://code.google.com/p/google-authenticator/" target="_blank">google authenticator</a> (TOTP)',
			items: [{
				xtype: 'buttongroupfield',
				name: 'security2faGgl',
				listeners: {
					beforetoggle: function(field, value) {
						if (value == '1') {
							Scalr.utils.Window({
								xtype: 'form',
								title: 'Enable two-factor authentication',
								width: 400,
                                layout: 'auto',
								items: [{
									xtype: 'fieldset',
                                    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
									defaults: {
										labelWidth: 50
									},
									items: [{
										xtype: 'textfield',
										readOnly: true,
										name: 'qr',
										value: moduleParams['security2faCode'],
										fieldLabel: 'Key',
                                        anchor: '100%'
									}, {
                                        xtype: 'qrpanel',
                                        textToEncode: 'otpauth://totp/' + encodeURIComponent(document.location.host) + ':' + encodeURIComponent(Scalr.user['userName'])  + '?secret=' + moduleParams['security2faCode'],
                                        margin: '0 0 6 55'
									}, {
										xtype: 'textfield',
										name: 'code',
										fieldLabel: 'Code',
										allowBlank: false,
                                        anchor: '100%'
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
										text: 'Enable',
										handler: function() {
											var fm = this.up('#box').getForm();

											if (fm.isValid())
												Scalr.Request({
													processBox: {
														type: 'action'
													},
													form: fm,
													url: '/core/xSettingsEnable2FaGgl/',
													success: function (data) {
														form.down('[name="security2faGgl"]').setValue('1');
														this.up('#box').close();

                                                        Scalr.utils.Window({
                                                            xtype: 'form',
                                                            width: 500,
                                                            title: 'Please save this reset code!',
                                                            titleAlign: 'center',
                                                            items: {
                                                                xtype: 'fieldset',
                                                                cls: 'x-fieldset-separator-none',
                                                                items: [{
                                                                    xtype: 'component',
                                                                    style: 'font-size: 13px',
                                                                    html:
                                                                        '<span style="text-align: center; font-size: 20px; display: block">' + data.resetCode + '</span><br>' +
                                                                            'This code will allow you to reset two-factor authentication if you lose or change your secondary device. ' +
                                                                            'You MUST SAVE THIS CODE!<br><br>' +
                                                                            'Scalr will NOT recreate this code, and you may be locked out without it.<br><br>'
                                                                }, {
                                                                    xtype: 'checkbox',
                                                                    boxLabel: 'I confirm that I have saved this code.',
                                                                    listeners: {
                                                                        change: function(field, value) {
                                                                            this.next().setDisabled(!value);
                                                                        }
                                                                    }
                                                                }, {
                                                                    xtype: 'button',
                                                                    text: 'Continue',
                                                                    height: 36,
                                                                    disabled: true,
                                                                    width: 150,
                                                                    margin: '0 0 0 136',
                                                                    handler: function() {
                                                                        this.up('form').close();
                                                                    }
                                                                }]
                                                            }
                                                        });
                                                    },
													scope: this
												});
										}
									}, {
										xtype: 'button',
										text: 'Cancel',
										handler: function() {
											this.up('#box').close();
										}
									}]
								}]
							});
						} else {
                            Scalr.utils.Window({
                                xtype: 'form',
                                title: 'Disable two-factor authentication',
                                width: 400,
                                layout: 'auto',
                                items: [{
                                    xtype: 'fieldset',
                                    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                                    items: [{
                                        xtype: 'textfield',
                                        fieldLabel: 'Code',
                                        labelWidth: 60,
                                        anchor: '100%',
                                        allowBlank: false,
                                        name: 'code'
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
                                        text: 'Disable',
                                        handler: function() {
                                            var fm = this.up('#box').getForm();

                                            if (fm.isValid())
                                                Scalr.Request({
                                                    processBox: {
                                                        type: 'action'
                                                    },
                                                    form: fm,
                                                    url: '/core/xSettingsDisable2FaGgl/',
                                                    success: function (data) {
                                                        form.down('[name="security2faGgl"]').setValue('');
                                                        this.up('#box').close();
                                                    },
                                                    scope: this
                                                });
                                        }
                                    }, {
                                        xtype: 'button',
                                        text: 'Cancel',
                                        handler: function() {
                                            this.up('#box').close();
                                        }
                                    }]
                                }]
                            });
						}

						return false;
					}
				},
                defaults: {
                    width: 95
                },
				items: [{
					text: 'Disabled',
					value: ''
				}, {
					text: 'Enabled',
					value: '1'
				}]
			}]
		}, {
			xtype: 'fieldset',
			title: 'IP access whitelist',
            cls: 'x-fieldset-separator-none',
			items: [{
				xtype: 'displayfield',
				value: 'Example: 67.45.3.7, 67.46.*, 91.*'
			}, {
				xtype:'textarea',
				hideLabel: true,
				name: 'securityIpWhitelist',
				grow: true,
				growMax: 200,
				emptyText: 'Leave blank to disable',
				anchor: '100%'
			}]
		}],

		dockedItems: [{
			xtype: 'container',
			cls: 'x-docked-buttons',
			dock: 'bottom',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						url: '/core/xSecuritySave/',
						form: this.up('form').getForm(),
						success: function () {
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

	form.getForm().setValues(moduleParams);
	return form;
});

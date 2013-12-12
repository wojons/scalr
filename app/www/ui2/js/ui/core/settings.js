Scalr.regPage('Scalr.ui.core.settings', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		width: 800,
		title: 'Settings',
        fieldDefaults: {
            labelWidth: 70
        },
		items: [{
			xtype: 'container',
            cls: 'x-fieldset-separator-bottom',
			layout: 'hbox',
			items: [{
				xtype: 'fieldset',
				flex: 1,
                cls: 'x-fieldset-separator-none',
				title: 'Profile information',
				items: [{
					xtype: 'displayfield',
					name: 'user_email',
					fieldLabel: 'Email',
					readOnly: true
				},{
					xtype: 'textfield',
					name: 'user_fullname',
					fieldLabel: 'Full name',
					anchor: '100%'
                }]
			},{
				xtype: 'fieldset',
				flex: 1,
                title: 'Avatar settings',
                cls: 'x-fieldset-separator-left',
				items: [{
                    xtype: 'image',
                    style: 'position:absolute;right:32px;top:16px;border-radius:4px',
                    width: 46,
                    height: 46,
                    src: Scalr.utils.getGravatarUrl(moduleParams['gravatar_hash'], 'large')
                }, {
                    xtype: 'displayfield',
                    value: '<a href="http://gravatar.com/" target="blank">Change your avatar at Gravatar.com</a>'
                }, {
                    xtype: 'textfield',
					name: 'gravatar_email',
					fieldLabel: 'Gravatar email',
					vtype: 'email',
                    labelWidth: 95,
					anchor: '100%'
				}]
			}]
		}, {
			xtype: 'fieldset',
            title: 'RSS feed',
			items: [{
				xtype: 'displayfield',
				cls: 'x-form-field-info',
				value: 'Each farm has an events and notifications page. You can get these events outside of Scalr on an RSS reader with the below credentials.'
			}, {
				xtype: 'textfield',
				name: 'rss_login',
				width: 336,
				fieldLabel: 'Login'
			}, {
				xtype: 'fieldcontainer',
				fieldLabel: 'Password',
				layout: 'hbox',
				items: [{
					xtype: 'textfield',
					name: 'rss_pass',
					width: 261,
					hideLabel: true
				}, {
					xtype: 'button',
					text: 'Generate',
                    width: 90,
					margin: '0 0 0 8',
					handler: function() {
						function getRandomNum() {
							var rndNum = Math.random();
							rndNum = parseInt(rndNum * 1000);
							rndNum = (rndNum % 94) + 33;
							return rndNum;
						};

						function checkPunc(num) {
							if ((num >=33) && (num <=47)) { return true; }
							if ((num >=58) && (num <=64)) { return true; }
							if ((num >=91) && (num <=96)) { return true; }
							if ((num >=123) && (num <=126)) { return true; }
							return false;
						};

						var length=16;
						var sPassword = "";

						for (var i=0; i < length; i++) {
							var numI = getRandomNum();
							while (checkPunc(numI)) { numI = getRandomNum(); }
							sPassword = sPassword + String.fromCharCode(numI);
						}

						this.prev().setValue(sPassword);
					}
				}]
			}]
		}, {
			xtype: 'fieldset',
			title: 'User interface',
			items: [{
				xtype: 'combo',
				fieldLabel: 'Timezone',
				store: moduleParams['timezones_list'],
				allowBlank: false,
				forceSelection: true,
				editable: true,
				name: 'timezone',
				queryMode: 'local',
				width: 336,
				anyMatch: true
			}]
		}, {
			xtype: 'container',
			layout: 'hbox',
            cls: 'x-fieldset-separator-bottom',
			items: [{
				xtype: 'fieldset',
				title: 'Dashboard',
                cls: 'x-fieldset-separator-none',
				flex: 1,
				items: [{
					xtype: 'buttongroupfield',
					fieldLabel: 'Columns',
					name: 'dashboard_columns',
					value: moduleParams['dashboard_columns'],
					items: [{
						text: '1',
						value: '1',
						width: 50
					}, {
						text: '2',
						value: '2',
						width: 50
					}, {
						text: '3',
						value: '3',
						width: 50
					}, {
						text: '4',
						value: '4',
						width: 50
					}, {
						text: '5',
						value: '5',
						width: 50
					}]
				}]
			}, {
				xtype: 'fieldset',
				title: 'Default table length',
				flex: 1,
                cls: 'x-fieldset-separator-left',
                items: [{
                    xtype: 'buttongroupfield',
                    fieldLabel: 'Items per page',
                    labelWidth: 95,
                    value: Ext.state.Manager.get('grid-ui-page-size', 'auto'),
                    items: [{
                        text: 'Auto',
                        value: 'auto',
                        width: 45
                    }, {
                        text: '10',
                        value: 10,
                        width: 45
                    }, {
                        text: '25',
                        value: 25,
                        width: 45
                    }, {
                        text: '50',
                        value: 50,
                        width: 45
                    }, {
                        text: '100',
                        value: 100,
                        width: 45
                    }],
                    submitValue: false,
                    listeners: {
                        change: function(component, newValue) {
                            Ext.state.Manager.set('grid-ui-page-size', newValue);
                        }
                    }
				}]
			}]
		}],

		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
            style: 'padding-top: 50px',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				itemId: 'buttonSubmit',
				handler: function() {
					if (form.getForm().isValid())
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/core/xSaveSettings/',
							form: this.up('form').getForm(),
							scope: this,
							success: function (data, response, options) {
								if (this.up('form').down('[name="dashboard_columns"]') != moduleParams['dashboard_columns']) {
									Scalr.event.fireEvent('update', '/dashboard', data.panel);
								}
								Scalr.event.fireEvent('update', '/account/user/gravatar', data['gravatarHash'] ||'');
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

Ext.getHead().createChild('<style type="text/css">#recaptcha_widget a:hover { border-radius: 3px; background-color: #FCFCFD !important; }</style>');

Scalr.regPage('Scalr.ui.guest.login', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		width: 500,
		style: 'margin-top: 50px',
		bodyCls: 'x-panel-body-frame',
		title: 'Please login',
		scalrOptions: {
			reload: false
		},
		layout: 'anchor',
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 70
		},
		items: {
			xtype: 'fieldset',
			style: 'padding: 30px 0px 10px',
			items: [{
				xtype: 'textfield',
				name: 'scalrLogin',
				fieldLabel: 'Email',
				allowBlank: false,
				inputId: 'textfield-user-login-inputEl',

				fieldSubTpl: '',
				listeners: {
					afterrender: function () {
						this.bodyEl.appendChild('textfield-user-login-inputEl');
					}
				}
			}, {
				xtype: 'textfield',
				inputType: 'password',
				name: 'scalrPass',
				fieldLabel: 'Password',
				allowBlank: false,
				inputId: 'textfield-user-password-inputEl',

				fieldSubTpl: '',
				listeners: {
					afterrender: function () {
						this.bodyEl.appendChild('textfield-user-password-inputEl');
					}
				}
			}, {
				xtype: 'checkbox',
				name: 'scalrKeepSession',
				inputType: 'checkbox',
				checked: true,
				boxLabel: 'Remember me'
			}, {
				xtype: 'component',
				height: 67,
				hidden: true,
				html:
					'<div id="recaptcha_widget" style="margin-left: 75px;">' +
						'<div style="float: left; border-radius: 3px; width: 300px; overflow: hidden;">' +
							'<div id="recaptcha_image"></div>' +
							'<input type="text" id="recaptcha_response_field" style="display: none" />' +
						'</div>' +
						'<div style="float: left; margin-left: 4px; width: 24px; height: 100%;">' +
							'<a href="javascript:Recaptcha.reload()" style="background: url(/ui2/images/ui/guest/login/captcha.png) no-repeat 0px 0px; width: 24px; height: 23px; display: block; margin-top: 3px;" title="Get another CAPTCHA"></a>' +
							'<a href="javascript:Recaptcha.showhelp()" style="background: url(/ui2/images/ui/guest/login/captcha.png) no-repeat 0px -23px; width: 24px; height: 23px; display: block; margin-top: 7px;" title="Help"></a>' +
						'</div>' +
					'</div>',
				listeners: {
					afterrender: function() {
						/*
						 from <script type="text/javascript" src="https://www.google.com/recaptcha/api/challenge?k=6Le9Us4SAAAAAOlqx9rkyJq0g3UBZtpoETmqUsmY"></script>
						 */
						RecaptchaState = {
							site : '6Le9Us4SAAAAAOlqx9rkyJq0g3UBZtpoETmqUsmY',
							rtl : false,
							challenge : '03AHJ_VusTaUJHZh24wZZKe2S3g-9WflEqNOdit2UDU-c38Gmx6_8I1A5Cq3xOExZD-KiobqaS25ox-i4lHMhSBTRVpdb6LWwCzJTUYDKt9zLl3HoC4HGWKc6XhUxNWIBeMkYkZDpZEmQUIH-XS8Dho3VTQYBa33RvEg',
							is_incorrect : false,
							programming_error : '',
							error_message : '',
							server : 'https://www.google.com/recaptcha/api/',
							lang : 'ru',
							timeout : 1800
						};
						RecaptchaOptions = {
							theme: 'custom',
							custom_theme_widget: 'recaptcha_widget'
						};
						Ext.Loader.injectScriptElement(RecaptchaState.server + 'js/recaptcha.js', Ext.emptyFn);
					}
				}
			}, {
				xtype: 'textfield',
				name: 'scalrCaptcha',
				allowBlank: false,
				fieldLabel: 'Captcha',
				hidden: true,
				disabled: true,
				listeners: {
					show: function() {
						this.prev().show();
					},
					hide: function() {
						this.prev().hide();
					}
				}
			}],
			listeners: {
				render: function() {
					this.el.createChild({ tag: 'form' }).appendChild(this.body);
				}
			}
		},

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
				text: 'Login',
				itemId: 'buttonSubmit',
				handler: function () {
					if (this.up('form').getForm().isValid()) {
						var values = this.up('form').getForm().getValues();

						if (Ext.isChrome) {
							// fake save password feature
							var iframe = document.getElementById('hiddenChromeLoginForm');
							var iframedoc = iframe.contentWindow ? iframe.contentWindow.document : iframe.contentDocument;
							if (iframedoc) {
								iframedoc.getElementById('scalrLogin').value = values['scalrLogin'];
								iframedoc.getElementById('scalrPass').value = values['scalrPass'];
								iframedoc.getElementById('loginForm').submit();
							}
						}

						Scalr.Request({
							processBox: {
								type: 'action'
							},
							scope: this,
							form: this.up('form').getForm(),
							url: '/guest/xLogin',
							success: function (data) {
								if (data['tfa']) {
									Scalr.event.fireEvent('redirect', data['tfa'], true, this.up('form').getForm().getValues());
								} else {
									Scalr.event.fireEvent('unlock');
									if (Ext.isChrome) {
										history.back();
									}

									if (Scalr.user.userId && (data.userId == Scalr.user.userId)) {
										Scalr.state.userNeedLogin = false;
										Scalr.event.fireEvent('close');
									} else {
										Scalr.application.updateContext(function() {
											Scalr.event.fireEvent('close', true);
										});
									}
								}
							},
							failure: function (data) {
								if (data && data['loginattempts'] && data['loginattempts'] > 2) {
									this.up('form').down('[name="scalrCaptcha"]').show().enable().reset();

									if (Ext.isObject(Recaptcha))
										Recaptcha.reload();
								}
								else
									this.up('form').down('[name="scalrCaptcha"]').hide().disable();
							}
						});
					}
				}
			}, {
				xtype: 'button',
				text: 'Forgot password?',
				margin: '0 0 0 10',
				handler: function () {
					Scalr.event.fireEvent('redirect', '#/guest/recoverPassword' , true, {
						email: this.up('form').down('[name=scalrLogin]').getValue()
					});
				}
			}]
		}],
		listeners: {
			boxready: function () {
				if (Ext.get('body-login-container'))
					Ext.get('body-login-container').remove();
			},
			activate: function () {
				if (Scalr.user.userId && !Scalr.state.userNeedLogin) {
					Scalr.event.fireEvent('close', true);
				} else {
					Scalr.event.fireEvent('lock', true);
					this.down('[name="scalrLogin"]').focus();
				}
			}
		}
	});
});

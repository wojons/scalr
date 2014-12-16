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
				editable: false,
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
		}, {
			xtype: 'fieldset',
			title: 'SSH Launcher settings',
            hidden: !Scalr.isAllowed('FARMS_SERVERS', 'ssh-console'),
            collapsed: true,
            collapsible: true,
            defaults: {
                anchor: '100%',
                labelWidth: 120,
                emptyText: 'Use default'
            },
            items: [{
                xtype: 'textfield',
                name: 'ssh.console.username',
                fieldLabel: 'User name',
                emptyText: 'root (scalr on GCE)'
            },{
                xtype: 'textfield',
                name: 'ssh.console.port',
                fieldLabel: 'Port',
                emptyText: '22'
            },{
                xtype: 'buttongroupfield',
                hidden: !Scalr.isAllowed('SECURITY_SSH_KEYS'),
                name: 'ssh.console.disable_key_auth',
                fieldLabel: 'SSH Key Auth',
                value: '0',
                defaults: {
                    width: 90
                },
                items: [{
                    text: 'Disabled',
                    value: '1'
                },{
                    text: 'Enabled',
                    value: '0'
                }]
            },{
                xtype: 'container',
                hidden: !Scalr.isAllowed('SECURITY_SSH_KEYS'),
                layout: 'hbox',
                items: [{
                    xtype: 'textfield',
                    name: 'ssh.console.key_name',
                    labelWidth: 120,
                    flex: 1,
                    fieldLabel: 'SSH key name',
                    emptyText: 'FARM-{SCALR_FARM_ID}-{SCALR_CLOUD_LOCATION}-' + moduleParams['scalr.id']
                },{
                    xtype: 'displayinfofield',
                    margin: '0 0 0 5',
                    width: 16,
                    info: Ext.String.htmlEncode('Scalr will automatically provide the SSH keys it generates to use with your hosts to the SSH Launcher Applet. '+
                          'If you\'re using Scalr keys, we suggest keeping this default unchanged. <br/>However, if you\'d like to use a custom SSH Key '+
                          '(perhaps because you have configured SSH Key Governance), then you can simply add the key in ~/.ssh/scalr-ssh-keys. ' +
                          'Scalr will not override it. <br/>View <a href="http:/scalr-wiki.atlassian.net" target="_blank">this Wiki page for important information</a>.')
                }]
            },{
                xtype: 'buttongroupfield',
                name: 'ssh.console.enable_agent_forwarding',
                fieldLabel: 'Agent Forwarding',
                value: '0',
                defaults: {
                    width: 90
                },
                items: [{
                    text: 'Disabled',
                    value: '0'
                },{
                    text: 'Enabled',
                    value: '1'
                }]
            },{
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'combobox',
                    labelWidth: 120,
                    flex: 1,
                    store: [
                        ['', 'Auto detect'],
                        ['com.scalr.ssh.provider.mac.', 'Mac AppleScript + OpenSSH'],
                        ['com.scalr.ssh.provider.linux.LinuxGnomeTerminalSSHProvider', 'Linux + Gnome Terminal + OpenSSH'],
                        ['com.scalr.ssh.provider.linux.LinuxXTermSSHProvider', 'Linux + XTerm + OpenSSH'],
                        ['com.scalr.ssh.provider.mac.MacAppleScriptSSHProvider', 'Mac OS + AppleScript + OpenSSH'],
                        ['com.scalr.ssh.provider.mac.MacNativeSSHProvider', 'Mac OS + Terminal Configuration + OpenSSH'],
                        ['com.scalr.ssh.provider.mac.MacSSHProvider', 'Mac OS + Terminal bash Script + OpenSSH'],
                        ['com.scalr.ssh.provider.windows.WindowsPuTTYProvider', 'Windows + PuTTY'],
                        ['com.scalr.ssh.provider.windows.WindowsOpenSSHProvider', 'Windows + OpenSSH']
                    ],
                    emptyText: 'Auto detect',
                    name: 'ssh.console.preferred_provider',
                    editable: false,
                    fieldLabel: 'Preferred provider'
                },{
                    xtype: 'displayinfofield',
                    margin: '0 0 0 5',
                    width: 16,
                    info: Ext.String.htmlEncode('The applet automatically tries all providers available for your '+
                                                'platform, you should not have to override this parameter. Only change this ' +
                                                'parameter if you understand precisely what you are doing.')
                }]
            },{
                xtype: 'combobox',
                store: ['ALL', 'SEVERE', 'WARNING', 'INFO', 'CONFIG', 'FINE', 'FINER', 'FINEST', 'OFF'],
                emptyText: 'CONFIG',
                name: 'ssh.console.log_level',
                editable: false,
                fieldLabel: 'Log level'
            },{
                xtype: 'component',
                style: 'color:#999',
                margin: '12 0 0',
                html: '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-globalvars" style="vertical-align:top" />&nbsp;&nbsp;All text fields in SSH applet settings support Global Variable Interpolation'
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

    moduleParams['ssh.console.disable_key_auth'] = moduleParams['ssh.console.disable_key_auth'] || '0';
    moduleParams['ssh.console.enable_agent_forwarding'] = moduleParams['ssh.console.enable_agent_forwarding'] || '0';
	form.getForm().setValues(moduleParams);
	return form;
});

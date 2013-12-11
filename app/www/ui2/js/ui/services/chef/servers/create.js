Scalr.regPage('Scalr.ui.services.chef.servers.create', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		width: 660,
		scalrOptions: {
			modal: true
		},
		items: [{
            xtype: 'fieldset',
            title: loadParams['servId'] ? 'Edit Chef server' : 'New Chef server',
            items: {
                xtype: 'textfield',
                name: 'url',
                fieldLabel: 'URL',
                labelWidth: 70,
                anchor: '100%',
                allowBlank: false
            }
        },{
			xtype: 'fieldset',
			title: 'Client authorization',
			defaults: {
				labelWidth: 70,
				anchor: '100%',
				allowBlank: false
			},
			items: [{
				xtype: 'textfield',
				name: 'userName',
				fieldLabel: 'Username'
			},{
				xtype: 'textarea',
				height: 80,
				name: 'authKey',
				fieldLabel: 'Key'

			}]
		},{
			xtype: 'fieldset',
			title: 'Client validator authorization',
            cls: 'x-fieldset-separator-none',
			defaults: {
				labelWidth: 70,
				anchor: '100%',
				allowBlank: false
			},
			items: [{
				xtype: 'textfield',
				name: 'userVName',
				fieldLabel: 'Username'
			},{
				xtype: 'textarea',
				height: 80,
				name: 'authVKey',
				fieldLabel: 'Key'

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
				text: loadParams['servId'] ? 'Save' : 'Add',
				formBind: true,
				handler: function() {
					Scalr.Request({
						processBox: {
							type: 'action'
						},
						scope: this,
						form: form.getForm(),
						url: '/services/chef/servers/xSaveServer',
						params: {servId: loadParams['servId'] ? loadParams['servId'] : 0},
						success: function (data) {
                            if (data['server']) {
                                Scalr.CachedRequestManager.get().setExpired({
                                    url: '/services/chef/servers/xListServers/'
                                });
                                Scalr.event.fireEvent('update', '/services/chef/servers/create', data['server']);
                            }
							Scalr.event.fireEvent('close');
						}
					});
				}
			},{
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});
	if(loadParams['servId'])
		form.getForm().setValues(moduleParams['servParams']);
	return form;
});
Scalr.regPage('Scalr.ui.admin.users.create', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		width: 700,
		title: (moduleParams['user']) ? 'Admin &raquo; Users &raquo; Edit' : 'Admin &raquo; Users &raquo; Create',
		fieldDefaults: {
			anchor: '100%'
		},

		items: [{
			xtype: 'fieldset',
			title: 'General information',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
			items: [{
				xtype: 'textfield',
				name: 'email',
				fieldLabel: 'Email',
				allowBlank: false
			}, {
				xtype: 'textfield',
				name: 'password',
				inputType: 'password',
				fieldLabel: 'Password',
				value: moduleParams['user'] ? '******': '',
				allowBlank: false
			}, {
                xtype: 'buttongroupfield',
                name: 'status',
                value: 'Active',
                fieldLabel: 'Status',
                items: [{
                    text: 'Active',
                    value: 'Active',
                    width: 130
                }, {
                    text: 'Inactive',
                    value: 'Inactive',
                    width: 130
                }]
			}, {
                xtype: 'buttongroupfield',
                name: 'type',
                value: 'ScalrAdmin',
                fieldLabel: 'Type',
                items: [{
                    text: 'Global admin',
                    value: 'ScalrAdmin',
                    width: 130
                }, {
                    text: 'Financial admin',
                    value: 'FinAdmin',
                    width: 130
                }],
                listeners: {
                    change: function(comp, value) {
                        comp.prev('[name="email"]').vtype = value === 'FinAdmin' ? 'email' : null;
                    }
                }
            }, {
				xtype: 'textfield',
				name: 'fullname',
				fieldLabel: 'Full name'
			}, {
				xtype: 'textarea',
				name: 'comments',
				fieldLabel: 'Comments',
				grow: true,
				growMax: 400
			}, {
				xtype: 'hidden',
				name: 'id'
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
				text: moduleParams['user'] ? 'Save' : 'Create',
				handler: function () {
					if (form.getForm().isValid())
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/admin/users/xSave',
							form: form.getForm(),
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
	
	if (moduleParams['user']) {
		form.getForm().setValues(moduleParams['user']);
		if (moduleParams['user']['email'] == 'admin') {
			form.down('[name="email"]').setReadOnly(true);
			form.down('[name="status"]').setReadOnly(true);
            form.down('[name="type"]').setReadOnly(true);
			form.down('[name="fullname"]').setReadOnly(true);
			form.down('[name="comments"]').setReadOnly(true);
		}
	}

	return form;
});

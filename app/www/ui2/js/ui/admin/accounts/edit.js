Scalr.regPage('Scalr.ui.admin.accounts.edit', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'Admin &raquo; Accounts &raquo; ' + (moduleParams['account']['id'] ? ('Edit &raquo; ' + moduleParams['account']['name']) : 'Create'),
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 130
		},
        preserveScrollPosition: true,
		items: [{
			xtype: 'fieldset',
			title: 'General information',
			items: [{
				xtype: 'textfield',
				name: 'name',
				fieldLabel: 'Name'
			}, {
				xtype: 'textarea',
				name: 'comments',
				fieldLabel: 'Comments'
			}]
		}, {
			xtype: 'fieldset',
			title: Scalr.flags['authMode'] == 'ldap' ? 'LDAP information' : 'Owner information',
            hidden: Scalr.flags['authMode'] == 'scalr' && !!moduleParams['account']['id'],
			items: [{
				xtype: 'textfield',
				name: 'ownerEmail',
				fieldLabel: Scalr.flags['authMode'] == 'ldap' ? 'LDAP login' : 'Email'
			}, {
				xtype: 'textfield',
				inputType: 'password',
				name: 'ownerPassword',
                hidden: Scalr.flags['authMode'] == 'ldap',
                disabled: Scalr.flags['authMode'] == 'ldap',
				fieldLabel: 'Password'
			}]
		}, {
			xtype: 'fieldset',
			title: 'Limits',
			hidden: !moduleParams['account']['id'] || Scalr.flags['authMode'] == 'ldap',
			items: [{
				xtype: 'textfield',
				name: 'limitEnv',
				fieldLabel: 'Environments'
			}, {
				xtype: 'textfield',
				name: 'limitUsers',
				fieldLabel: 'Users'
			}, {
				xtype: 'textfield',
				name: 'limitFarms',
				fieldLabel: 'Farms'
			}, {
				xtype: 'textfield',
				name: 'limitServers',
				fieldLabel: 'Servers'
			}]
		},{
            xtype: 'container',
            cls: 'x-container-fieldset',
            layout: 'anchor',
            hidden: !Scalr.flags['analyticsEnabled'] || (Scalr.flags['hostedScalr'] && !moduleParams['account']['id']),
            items: [{
                xtype: 'tagfield',
                name: 'ccs',
                store: {
                    fields: [ 'ccId', 'name' ],
                    proxy: 'object',
                    data: moduleParams['ccs']
                },
                flex: 1,
                valueField: 'ccId',
                displayField: 'name',
                fieldLabel: 'Cost centers',
                queryMode: 'local',
                columnWidth: 1,
                disabled: !Scalr.flags['analyticsEnabled'] || Scalr.flags['hostedScalr'],
                submitValue: false,
                allowBlank: false
            },{
                xtype: 'displayfield',
                cls: 'x-form-field-warning',
                itemId: 'removedCcsInfo',
                hidden: true
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
                    var form = this.up('form'),
                        params;
                    if (form.getForm().isValid()) {
                        params = {
                            id: moduleParams['account']['id']
                        };
                        if (Scalr.flags['analyticsEnabled'] && !Scalr.flags['hostedScalr']) {
                            params['ccs'] = Ext.encode(form.down('[name="ccs"]').getValue());
                        }
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/admin/accounts/xSave',
                            form: form.getForm(),
                            params: params,
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
	
	form.getForm().setValues(moduleParams['account']);
    form.getForm().clearInvalid();

    if (Scalr.flags['analyticsEnabled']) {
        form.down('[name="ccs"]').on('change', function(field) {
            var origValue = moduleParams['account']['ccs'] || [],
                value = field.getValue() || [],
                removedCcs = [];
            if (moduleParams['account']['id']) {
                Ext.each(origValue, function(ccId){
                    if (!Ext.Array.contains(value, ccId)) {
                        Ext.each(moduleParams['ccs'], function(cc){
                            if (cc.ccId === ccId) {
                                removedCcs.push(cc);
                                return false;
                            }
                        });
                    }
                });
                if(removedCcs.length) {
                    var warning = ['Removing Cost Center from the whitelist prevents new Environments from being associated with it, but existing Environments are not affected. Removed Cost Centers:'];
                    Ext.each(removedCcs, function(cc){
                        var envs = [];
                        if (cc.envs && cc.envs.length) {
                            Ext.each(cc.envs, function(env){
                                envs.push(env.name + ' (id:' + env.id + ')');
                            });
                        }
                        warning.push(cc.name + (envs.length ? ' - ' + envs.join(', ') : ''));

                    });
                    form.down('#removedCcsInfo').setValue(warning.join('<br/>')).show();
                } else {
                    form.down('#removedCcsInfo').hide();
                }
            }
        });
    }
	return form;
});

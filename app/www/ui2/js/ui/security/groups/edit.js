Scalr.regPage('Scalr.ui.security.groups.edit', function (loadParams, moduleParams) {
	var form = Ext.create('Scalr.ui.SecurityGroupEditor', {
		width: 900,
		scalrOptions: {
			modal:true
		},
        accountId: moduleParams['accountId'],
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
                    var values = this.up('sgeditor').getValues();
                    if (values !== false) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/security/groups/xSave',
                            params: values,
                            success: function (data) {
                                if (!!moduleParams['securityGroupId']) {
                                    Scalr.event.fireEvent('refresh');
                                } else {
                                    Scalr.event.fireEvent('close');
                                }
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
    form.setValues(moduleParams);

	return form;
});

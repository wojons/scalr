Scalr.regPage('Scalr.ui.tools.aws.rds.pg.edit', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel',{
		title: 'Tools &raquo; Amazon Web Services &raquo; Amazon RDS &raquo; Parameter groups &raquo; ' + loadParams['name'] + ' &raquo; Edit',
		width: 900,
		items: [{
			xtype: 'fieldset',
			title: 'General',
			itemId: 'general',
			defaults: {
				labelWidth: 250,
				xtype: 'displayfield'
			},
			items: [{
				fieldLabel: 'Parameter Group Name',
				name: 'DBParameterGroupName',
				value: moduleParams.group['dBParameterGroupName']
			},
			{
				fieldLabel: 'Parameter Group Family',
				name: 'Engine',
				value: moduleParams.group['dBParameterGroupFamily']
			},
			{
				fieldLabel: 'Description',
				name: 'Description',
				value: moduleParams.group['description']
			}]
		},{
			xtype: 'fieldset',
			title: 'System parameters',
			itemId: 'system',
			items: moduleParams.params['system']
		},{
			xtype: 'fieldset',
			title: 'Engine default parameters',
			itemId: 'engineDefault',
			items: moduleParams.params['engineDefault']
		},{
			xtype: 'fieldset',
			title: 'User parameters',
			itemId: 'user',
			items: moduleParams.params['user'],
            defaults: {
                defaults: {
                    allowBlank: false
                }
            }
		}],
        getFirstInvalidField: function () {
            return this.down('field{isValid()===false}');
        },
        scrollToField: function (field) {
            field.inputEl.scrollIntoView(this.body.el, false, false);
        },
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
                    if (form.isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: form.getForm(),
                            url: '/tools/aws/rds/pg/xSave',
                            params: loadParams,
                            success: function (data) {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    } else {
                        var invalidField = form.getFirstInvalidField();

                        if (!Ext.isEmpty(invalidField)) {
                            form.scrollToField(invalidField);
                            invalidField.focus();
                        }
                    }
				}
			},{
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			},{
				xtype: 'button',
				text: 'Reset to defaults',
				handler: function() {
					Scalr.Request({
						confirmBox: {
							msg: 'Are you sure you want to reset all parameters?',
							type: 'action'
						},
						processBox: {
							type: 'action'
						},
						url: '/tools/aws/rds/pg/xReset',
						params: loadParams,
						success: function (data) {
							document.location.reload();
						}
					});
				}
			}]
		}]
	});
	return form;
});

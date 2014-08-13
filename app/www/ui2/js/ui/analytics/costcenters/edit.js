Scalr.regPage('Scalr.ui.analytics.costcenters.edit', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		scalrOptions: {
			modal: true
		},
		title: moduleParams.cc.ccId ? 'Edit cost center' : 'New cost center',
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 80
		},
		width: 600,
        items: {
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            items: [{
                xtype: 'hidden',
                name: 'ccId'
            },{
                xtype: 'container',
                layout: 'hbox',
                defaults: {
                    flex: 1
                },
                items: [{
                    xtype: 'textfield',
                    name: 'name',
                    fieldLabel: 'Name',
                    allowBlank: false
                },{
                    xtype: 'textfield',
                    name: 'billingCode',
                    fieldLabel: 'Billing code',
                    margin: '0 0 0 32',
                    allowBlank: false
                }]
            },{
                xtype: 'displayfield',
                itemId: 'created',
                hidden: true,
                fieldLabel: 'Created on',
                margin: '8 0 0 0'
            },{
                xtype: 'textfield',
                name: 'leadEmail',
                allowBlank: false,
                vtype: 'email',
                fieldLabel: 'Lead email',
                margin: '8 0 0 0'
            },{
                xtype: 'textarea',
                name: 'description',
                fieldLabel: 'Description',
                margin: '8 0 0 0'
            },{
                xtype: 'displayfield',
                name: 'environments',
                fieldLabel: 'Tracked environments',
                labelWidth: 140,
                hidden: true
            }]
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
                    var frm = form.getForm();
					if (frm.isValid())
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							form: frm,
							url: '/analytics/costcenters/xSave/',
							success: function (data) {
                                Scalr.event.fireEvent('update', '/analytics/costcenters/edit', data.cc);
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
			},{
                xtype: 'button',
                cls: 'x-btn-default-small-red',
                hidden: !moduleParams.cc.ccId || moduleParams.cc.archived,
                text: moduleParams.cc.removable ? 'Delete' : 'Archive',
                disabled: !moduleParams.cc.removable && moduleParams.cc.warning,
                tooltip: !moduleParams.cc.removable && moduleParams.cc.warning ? moduleParams.cc.warning : '',
                handler: function() {
                    Scalr.Request({
                        confirmBox: 
                            moduleParams.cc.removable ?
                            {
                                msg: 'Delete cost center <b>' + moduleParams.cc.name + '</b> ?',
                                type: 'delete'
                            }:
                            {
                                msg: 'Archive cost center <b>' + moduleParams.cc.name + '</b> ?',
                                type: 'archive',
                                ok: 'Archive'
                            },
                        processBox: {
                            msg: 'Processing...'
                        },
                        scope: this,
                        url: '/analytics/costcenters/xRemove',
                        params: {ccId: moduleParams.cc.ccId},
                        success: function (data) {
                            Scalr.event.fireEvent('update', '/analytics/costcenters/remove', {ccId: moduleParams.cc.ccId, removable: data.removable});
                            Scalr.event.fireEvent('close');
                        }
                    });
                }
            }]
		}]
	});

	form.getForm().setValues(moduleParams.cc);
    if (moduleParams.cc.ccId) {
        var createdField = form.down('#created'),
            created = Scalr.utils.Quarters.getDate(moduleParams.cc.created, true);
        if (!isNaN(created.getTime())) {
            created = Ext.Date.format(created, 'M j, Y');
            if (moduleParams.cc.createdByEmail) {
                created += ' by ' + moduleParams.cc.createdByEmail;
            }
        } else {
            created = '&ndash;';
        }
        createdField.show().setValue(created);
    }
    
	return form;
});

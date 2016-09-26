Scalr.regPage('Scalr.ui.admin.analytics.costcenters.edit', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
		scalrOptions: {
			modalWindow: true
		},
		title: moduleParams.cc.ccId ? 'Edit cost center' : 'New cost center',
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 90
		},
		width: 740,
        items: {
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            items: [{
                xtype: 'textfield',
                readOnly: true,
                fieldLabel: 'ID',
                hidden: !moduleParams.cc.ccId,
                hideInputOnReadOnly: true,
                name: 'ccId'
            },{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                defaults: {
                    flex: 1
                },
                items: [{
                    xtype: 'textfield',
                    name: 'name',
                    fieldLabel: 'Name',
                    allowBlank: false,
                    readOnly: !!moduleParams.cc.archived
                },{
                    xtype: 'textfield',
                    name: 'billingCode',
                    fieldLabel: 'Billing code',
                    margin: '0 0 0 32',
                    allowBlank: false,
                    readOnly: !!moduleParams.cc.archived
                }]
            },{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                items: [{
                    xtype: 'buttongroupfield',
                    name: 'locked',
                    fieldLabel: 'Status',
                    readOnly: !!moduleParams.cc.projectId || !!moduleParams.cc.archived,
                    value: 0,
                    //margin: '8 0 6 0',
                    defaults: {
                        width: 108
                    },
                    width: 316,
                    items: [{
                        text: 'Locked',
                        value: 1
                    },{
                        text: 'Unlocked',
                        value: 0
                    }],
                    listeners: {
                        change: function(comp, value) {
                            comp.next().setValue(value ? 'Only the Financial Admin can add new Projects' : 'IT users of this Cost Center can add new Projects');
                        }
                    }
                },{
                    xtype: 'displayfield',
                    itemId: 'lockedInfo',
                    value: 'IT users of this Cost Center can add new Projects',
                    renderer: function(value) {
                        return '<img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" /> &nbsp;' + value
                    }
                }]
            },{
                xtype: 'displayfield',
                itemId: 'created',
                hidden: true,
                fieldLabel: 'Created on'
            },{
                xtype: 'textfield',
                name: 'leadEmail',
                allowBlank: false,
                vtype: 'email',
                fieldLabel: 'Lead email',
                margin: '8 0 0 0',
                readOnly: !!moduleParams.cc.archived
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
							url: '/admin/analytics/costcenters/xSave/',
							success: function (data) {
                                Scalr.event.fireEvent('update', '/admin/analytics/costcenters/edit', data.cc);
                                form.close();
                                //Scalr.event.fireEvent('redirect', '#/admin/analytics/costcenters', false, {ccId: data.cc.ccId});
							}
						});
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					form.close();
				}
			},{
                xtype: 'button',
                cls: 'x-btn-red',
                hidden: !moduleParams.cc.ccId || !!moduleParams.cc.archived,
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
                        url: '/admin/analytics/costcenters/xRemove',
                        params: {ccId: moduleParams.cc.ccId},
                        success: function (data) {
                            Scalr.event.fireEvent('update', '/admin/analytics/costcenters/remove', {ccId: moduleParams.cc.ccId, removable: data.removable});
                            form.close();
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

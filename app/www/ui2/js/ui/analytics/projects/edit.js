Scalr.regPage('Scalr.ui.analytics.projects.edit', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		scalrOptions: {
			modal: true
		},
		title: moduleParams.project.projectId ? 'Edit project' : 'New project',
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
                name: 'projectId'
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
                    labelWidth: 120,
                    allowBlank: false
                },{
                    xtype: 'textfield',
                    name: 'billingCode',
                    fieldLabel: 'Billing code',
                    margin: '0 0 0 32',
                    allowBlank: false
                }]
            },{
                xtype: 'combo',
                name: 'ccId',
                fieldLabel: 'Parent cost center',
                labelWidth: 120,
                editable: false,
                queryMode: 'local',
                displayField: 'name',
                valueField: 'ccId',
                allowBlank: false,
                readOnly: !!moduleParams.project.projectId,
                store: {
                    fields: ['ccId', 'name'],
                    data: moduleParams['ccs'],
                    sorters: [{
                        property: 'name',
                        transform: function(value){
                            return value.toLowerCase();
                        }
                    }]
                }
            },{
                xtype: 'displayfield',
                itemId: 'created',
                labelWidth: 120,
                hidden: true,
                fieldLabel: 'Created on'
            },{
                xtype: 'textfield',
                name: 'leadEmail',
                allowBlank: false,
                vtype: 'email',
                labelWidth: 120,
                fieldLabel: 'Lead email'
            },{
                xtype: 'container',
                layout: 'anchor',
                hidden: Scalr.user.type == 'FinAdmin' || Scalr.user.type == 'ScalrAdmin',
                items: [{
                    xtype: 'buttongroupfield',
                    name: 'shared',
                    fieldLabel: 'Share mode',
                    labelWidth: 120,
                    maxWidth: 535,
                    anchor: '100%',
                    value: 1,
                    layout: 'hbox',
                    defaults: {
                        flex: 1
                    },
                    items: [{
                        text: 'User',
                        value: 0,
                        tooltip: 'This Project can only be used by you'
                    },{
                        text: 'Environment',
                        value: 3,
                        tooltip: 'This Project can only be used by Farms in this Environment'
                    },{
                        text: 'Account',
                        value: 2,
                        tooltip: 'This Project can be used by Farms within this Cost Center in only this Scalr Account'
                    },{
                        text: 'Shared',
                        value: 1,
                        tooltip: 'This Project can be used by Farms within this Cost Center in other Scalr accounts'
                    }]
                },{
                    xtype: 'component'
                }]
            },{
                xtype: 'textarea',
                name: 'description',
                labelWidth: 120,
                fieldLabel: 'Description'
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
							url: '/analytics/projects/xSave/',
							success: function (data) {
                                Scalr.event.fireEvent('update', '/analytics/projects/' + (moduleParams.project.projectId ? 'edit' : 'add'), data.project);
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
                hidden: !moduleParams.project.projectId || moduleParams.project.archived,
                text: moduleParams.project.removable ? 'Delete' : 'Archive',
                disabled: !moduleParams.project.removable && moduleParams.project.warning,
                tooltip: !moduleParams.project.removable && moduleParams.project.warning ? moduleParams.project.warning : '',
                handler: function() {
                    Scalr.Request({
                        confirmBox: 
                            moduleParams.project.removable ?
                            {
                                msg: 'Delete project <b>' + moduleParams.project.name + '</b> ?',
                                type: 'delete'
                            }:
                            {
                                msg: 'Archive project <b>' + moduleParams.project.name + '</b> ?',
                                type: 'archive',
                                ok: 'Archive'
                            },
                        processBox: {
                            msg: 'Processing...'
                        },
                        scope: this,
                        url: '/analytics/projects/xRemove',
                        params: {projectId: moduleParams.project.projectId},
                        success: function (data) {
                            Scalr.event.fireEvent('update', '/analytics/projects/remove', {ccId: moduleParams.project.projectId, removable: data.removable});
                            Scalr.event.fireEvent('close');
                        }
                    });
                }
			}]
		}]
	});

	var frm = form.getForm(),
        ccIdField = frm.findField('ccId');
    frm.setValues(moduleParams.project);
    if (!ccIdField.getValue()) {
        if (ccIdField.store.getCount() == 1) {
            ccIdField.setValue(ccIdField.store.first());
            ccIdField.setReadOnly(true);
        } else if (loadParams['ccId']){
            ccIdField.setValue(loadParams['ccId']);
        }

    }

    if (moduleParams.project.projectId) {
        var createdField = form.down('#created'),
            created = Scalr.utils.Quarters.getDate(moduleParams.project.created, true);
        if (!isNaN(created.getTime())) {
            created = Ext.Date.format(created, 'M j, Y');
            if (moduleParams.project.createdByEmail) {
                created += ' by ' + moduleParams.project.createdByEmail;
            }
        } else {
            created = '&ndash;';
        }
        createdField.show().setValue(created);
    }

	return form;
});

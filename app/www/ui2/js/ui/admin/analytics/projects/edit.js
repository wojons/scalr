Scalr.regPage('Scalr.ui.admin.analytics.projects.edit', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
        layout: 'auto',
        plugins: {
            ptype: 'localcachedrequest',
            crscope: 'editproject'
        },
		scalrOptions: {
			modalWindow: true
		},
		title: moduleParams.project.projectId ? 'Edit project' : 'New project',
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 140
		},
		width: 600,
        items: {
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            items: [{
                xtype: 'textfield',
                readOnly: true,
                fieldLabel: 'ID',
                hidden: !moduleParams.project.projectId,
                hideInputOnReadOnly: true,
                name: 'projectId'
            },{
                xtype: 'textfield',
                name: 'name',
                fieldLabel: 'Name',
                allowBlank: false,
                readOnly: !!moduleParams.project.archived
            },{
                xtype: 'combo',
                name: 'ccId',
                fieldLabel: 'Parent cost center',
                editable: false,
                queryMode: 'local',
                displayField: 'name',
                valueField: 'ccId',
                allowBlank: false,
                readOnly: !!moduleParams.project.projectId || !!moduleParams.project.archived,
                store: {
                    fields: ['ccId', 'name'],
                    data: Ext.Object.getValues(moduleParams['ccs']),
                    sorters: [{
                        property: 'name',
                        transform: function(value){
                            return value.toLowerCase();
                        }
                    }]
                },
                listeners: {
                    change: function(comp, value){
                        var field = form.down('[name="billingCode"]');
                        if (form.down('#useParentBillingCode').getValue() && moduleParams['ccs'][value]) {
                            field.setValue(moduleParams['ccs'][value]['billingCode']);
                        }
                        if (Scalr.utils.isAdmin()) {
                            form.down('projectsharemode').setCostCenter(value);
                        }
                    }
                }
            },{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                defaults: {
                    flex: 1
                },
                items: [{
                    xtype: 'textfield',
                    name: 'billingCode',
                    fieldLabel: 'Billing code',
                    margin: '0 16 0 0',
                    allowBlank: false,
                    readOnly: !!moduleParams.project.archived
                },{
                    xtype: 'checkbox',
                    boxLabel: 'Use parent Cost center billing code',
                    itemId: 'useParentBillingCode',
                    submitValue: false,
                    readOnly: !!moduleParams.project.archived,
                    listeners: {
                        change: function(comp, value) {
                            var field = form.down('[name="billingCode"]'),
                                ccId = form.down('[name="ccId"]').getValue();
                            field.setReadOnly(value);
                            if (value && moduleParams['ccs'][ccId]) field.setValue(moduleParams['ccs'][ccId]['billingCode']);
                        }
                    }
                }]
            },{
                xtype: 'displayfield',
                itemId: 'created',
                hidden: true,
                fieldLabel: 'Created on'
            },{
                xtype: 'projectsharemode',
                hidden: !moduleParams.project.projectId || !Scalr.utils.isAdmin()
            },{
                xtype: 'textfield',
                name: 'leadEmail',
                allowBlank: false,
                vtype: 'email',
                fieldLabel: 'Lead email',
                readOnly: !!moduleParams.project.archived
            },{
                xtype: 'textarea',
                name: 'description',
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
                    var frm = form.getForm(),
                        projectName = frm.findField('name').getValue(), costCenterName, accountName, field, record;
                    field = frm.findField('ccId');
                    record = field.findRecordByValue(field.getValue());
                    costCenterName = record ? record.get(field.displayField) : '';

                    field = frm.findField('accountId');
                    if (field) {
                        record = field.findRecordByValue(field.getValue());
                        accountName = record ? record.get(field.displayField) : '';
                    }
                    var urlPrefix;
                    if (Scalr.scope === 'scalr') {
                        urlPrefix = '/admin';
                    } else {
                        urlPrefix = '/account';
                    }
                    saveProject = function(checkAccountAccessToCc, grantAccountAccessToCc) {
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							form: frm,
                            params: {
                                checkAccountAccessToCc: checkAccountAccessToCc,
                                grantAccountAccessToCc: grantAccountAccessToCc
                            },
							url: urlPrefix + '/analytics/projects/xSave/',
							success: function(data) {
                                Scalr.event.fireEvent('update', '/analytics/projects/' + (moduleParams.project.projectId ? 'edit' : 'add'), data.project);
								form.close();
							},
                            failure: function(data) {
                                if (data['ccIsNotAllowedToAccount']) {
                                    Scalr.utils.Window({
                                        title: 'Warning',
                                        layout: 'anchor',
                                        width: 600,
                                        bodyCls: 'x-container-fieldset',
                                        defaults: {
                                            anchor: '100%'
                                        },
                                        items: [{
                                            xtype: 'displayfield',
                                            cls: 'x-form-field-warning',
                                            value: 'You are trying to save <b>'+projectName+'</b> as a Project under Cost Center <b>'+costCenterName+'</b>, restricted to Account <b>'+accountName+'</b>. '+
                                                   'However, <b>'+accountName+'</b> does not have access to <b>'+costCenterName+'</b>. Are you sure you want to proceed? <br/>Project <b>'+projectName+'</b> will not ' +
                                                   'be usable unless an administrator grants <b>'+accountName+'</b> access to <b>'+costCenterName+'</b>.'
                                        },{
                                            xtype: 'checkbox',
                                            name: 'grant',
                                            boxLabel: 'Grant <b>'+accountName+'</b> access to <b>'+costCenterName+'</b>'
                                        }],
                                        dockedItems: [{
                                            xtype: 'container',
                                            cls: 'x-docked-buttons',
                                            dock: 'bottom',
                                            layout: {
                                                type: 'hbox',
                                                pack: 'center'
                                            },
                                            items: [{
                                                xtype: 'button',
                                                text: 'Save',
                                                handler: function() {
                                                    var grantAccountAccessToCc = this.up().up().down('[name="grant"]').getValue();
                                                    this.up('#box').close();
                                                    saveProject(false, grantAccountAccessToCc);
                                                }
                                            }, {
                                                xtype: 'button',
                                                text: 'Cancel',
                                                margin: '0 0 0 10',
                                                handler: function() {
                                                    this.up('#box').close();
                                                }
                                            }]
                                        }]
                                    });

                                }
                            }
						});
                    }
					if (frm.isValid()) saveProject(true);
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
                hidden: !moduleParams.project.projectId || !!moduleParams.project.archived || !Scalr.isAllowed('ANALYTICS_PROJECTS_ACCOUNT', 'delete'),
                text: moduleParams.project.removable ? 'Delete' : 'Archive',
                disabled: !moduleParams.project.removable && moduleParams.project.warning,
                tooltip: !moduleParams.project.removable && moduleParams.project.warning ? moduleParams.project.warning : '',
                handler: function() {
                    var urlPrefix;
                    if (Scalr.scope === 'scalr') {
                        urlPrefix = '/admin';
                    } else {
                        urlPrefix = '/account';
                    }
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
                        url: urlPrefix + '/analytics/projects/xRemove',
                        params: {projectId: moduleParams.project.projectId},
                        success: function (data) {
                            Scalr.event.fireEvent('update', '/analytics/projects/remove', {projectId: moduleParams.project.projectId, removable: data.removable});
                            form.close();
                        }
                    });
                }
			}]
		}]
	});

	var frm = form.getForm(),
        ccIdField = frm.findField('ccId');
    delete moduleParams.project.shared;
    frm.setValues(moduleParams.project);
    if (Scalr.utils.isAdmin()) {
        form.down('projectsharemode').project = moduleParams.project;
        form.down('projectsharemode').setValue(moduleParams['sharedWidget']);
    }
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

        if (moduleParams['ccs'][moduleParams.project.ccId]) {
            form.down('#useParentBillingCode').setValue(moduleParams['ccs'][moduleParams.project.ccId]['billingCode']==moduleParams.project.billingCode);
        }
    }

	return form;
});

Ext.define('Scalr.ui.ProjectShareMode', {
    extend: 'Ext.container.Container',
    alias: 'widget.projectsharemode',
    layout: 'anchor',
    defaults: {
        anchor: '100%'
    },
    setCostCenter: function(ccId) {
        this.ccId = ccId;
        this.show();
    },
    setValue: function(value) {
        var accountField = this.down('[name="accountId"]'),
            sharedField = this.down('[name="shared"]');

        accountField.setReadOnly(value.farmsCount > 0);
        sharedField.setValue(value['shared'] || 1);
        if (this.project.projectId && value['shared'] == 1) {
            sharedField.items.first().disable().setTooltip('Switching from Global to Account scope is not allowed');
        }

        if (value['accountId'] && value['accounts']){
            accountField.store.getProxy().data = value['accounts'];
            accountField.store.load();
            accountField.setValue(value['accountId']+'' || '');
        }
    },

    items: [{
        xtype: 'buttongroupfield',
        name: 'shared',
        fieldLabel: 'Scope',
        anchor: '100%',
        layout: 'hbox',
        defaults: {
            flex: 1
        },
        items: [{
            text: 'Account',
            value: 2,
            tooltip: 'This Project can be used by Farms within this Cost Center in only selected Scalr Account'
        },{
            text: 'Global',
            value: 1,
            tooltip: 'This Project can be used by Farms within this Cost Center in other Scalr accounts'
        }],
        listeners: {
            beforetoggle: function(btn, value) {
                var me = this;
                if (me.up('projectsharemode').project.projectId) {
                    if (value == 1) {
                        Scalr.Confirm({
                            msg: 'After saving changes you will not be able to switch Scope back, are you sure you want to continue?',
                            type: 'action',
                            ok: 'Continue',
                            success: function() {
                                me.setValue(1);
                            }
                        });
                        return false;
                    }
                }
            },
            change: function(comp, value) {
                var ct = comp.up('projectsharemode'),
                    accountField = ct.down('[name="accountId"]');
                if (value == 1) {
                    accountField.hide().disable();
                } else {
                    accountField.show().enable();
                }
            }
        }
    },{
        xtype: 'combo',
        name: 'accountId',
        store: {
            fields: [ 'id', 'name', {name: 'title', convert: function(v, record){return record.data.name + ' (id: ' + record.data.id + ')'}} ],
            sorters: [{
                property: 'name',
                transform: function(value){
                    return value.toLowerCase();
                }
            }],
            proxy: {
                type: 'cachedrequest',
                crscope: 'editproject',
                url: '/admin/analytics/projects/xGetProjectWidgetAccounts',
                root: 'accounts',
                filterFields: ['title']
            }
        },
        valueField: 'id',
        fieldLabel: 'Account',
        labelWidth: 70,
        displayField: 'title',
        margin: '0 0 6 145',
        hidden: true,
        disabled: true,

        forceSelection: true,
        selectOnFocus: true,

        restoreValueOnBlur: true,
        queryCaching: false,
        minChars: 0,
        queryDelay: 10,
        autoSearch: false,
        allowBlank: false
    }]
});

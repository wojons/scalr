Scalr.regPage('Scalr.ui.servers.createsnapshot', function  (loadParams, moduleParams) {
    var replacementOptions = [],
        roleScopes = [],
        imageScopes = [],
        isManageableRole,
        scope = moduleParams['roleScope'];

    if (moduleParams['isReplaceFarmRolePermission']) {
        replacementOptions.push(['farm', 'ONLY on the current Farm "' + moduleParams['farmName'] + '"'], ['all', 'on ALL managed Farms']);
    }

    if (Scalr.isAllowed('IMAGES_ENVIRONMENT', 'manage')) {
        imageScopes.push({ id: 'environment', name: 'Environment' });
    }

    if (Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage')) {
        roleScopes.push({ id: 'environment', name: 'Environment' });
    }

    if (Scalr.isAllowed('IMAGES_ACCOUNT', 'manage')) {
        imageScopes.push({ id: 'account', name: 'Account'});
        if (scope == 'scalr') {
            scope = 'environment';
        }

        if (Scalr.isAllowed('ROLES_ACCOUNT', 'manage')) {
            roleScopes.push({ id: 'account', name: 'Account'});
        }
    } else {
        scope = 'environment';
    }

    isManageableRole = moduleParams['roleScope'] == 'environment' && Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage') ||
        moduleParams['roleScope'] == 'account' && Scalr.isAllowed('ROLES_ACCOUNT', 'manage');

    var form = Ext.create('Ext.form.Panel', {
        scalrOptions: {
            'modal': true
        },
        width: 1100,
        title: 'Create server snapshot',
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 80
        },

        items: [{
            xtype: 'displayfield',
            cls: 'x-form-field-warning x-form-field-warning-fit',
            value: 'You are snapshotting a Windows Server. Before proceeding, make sure that you have sysprepped the instance' +
                (moduleParams['platform'] == 'gce' ? 'by running gcesysprep' : '') + '. ' +
                '<br/>Note that the instance <b>will be ' + (moduleParams['platform'] == 'gce' ? 'terminated' : 'rebooted') +
                '</b> in order to complete the server snapshotting process. Do not proceed if this is a problem for you.',
            hidden: !moduleParams['windowsWarning']
        }, {
            xtype: 'displayfield',
            cls: 'x-form-field-warning x-form-field-warning-fit',
            value: 'You are about to synchronize DB instance. The bundle will not include DB data. ' + "<a href='" +
                (moduleParams['databaseMysqlWarning'] ? '#/db/dashboard?farmId=' + moduleParams['farmId'] + '&type=mysql' :
                    '#/db/manager/dashboard?farmId=' + moduleParams['farmId'] + '&type=' + moduleParams['databaseWarning']) +
                "'>Click here if you wish to bundle and save DB data</a>.",
            hidden: !(moduleParams['databaseMysqlWarning'] || moduleParams['databaseWarning'])
        }, {
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            cls: 'x-fieldset-separator-bottom',
            items: [{
                xtype: 'container',
                cls: 'x-container-fieldset',
                width: 450,
                items: [{
                    xtype: 'displayfield',
                    value: moduleParams['serverId'],
                    fieldLabel: 'Server ID'
                }, {
                    xtype: 'displayfield',
                    value: '<a href="#/farms?farmId=' + moduleParams['farmId'] + '">' + moduleParams['farmName'] + '</a> (ID: ' + moduleParams['farmId'] + ')',
                    fieldLabel: 'Farm'
                },{
                    xtype: 'displayfield',
                    value: '<div style="float:left;"><img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Scalr.utils.getScopeLegend('role', '')+ '" class="scalr-scope-' + moduleParams['roleScope'] + '" style="margin:0 6px 0 0"/>' + '<a href="#/roles?roleId=' + moduleParams['roleId'] + '">' + moduleParams['roleName'] + '</a>' + ' #' + moduleParams['serverIndex'],
                    fieldLabel: 'Role name'
                }]
            }, {
                xtype: 'container',
                cls: 'x-container-fieldset x-fieldset-separator-left',
                flex: 1,
                defaults: {
                    labelWidth: 120
                },
                items: [{
                    xtype: 'displayfield',
                    fieldLabel: 'Location',
                    value:
                        '<img class="x-icon-platform-small x-icon-platform-small-' + moduleParams['platform'] +
                        '" data-qtip="' + Scalr.utils.getPlatformName(moduleParams['platform']) + '" src="' + Ext.BLANK_IMAGE_URL +
                        '"/> ' + moduleParams['cloudLocation']
                }, {
                    xtype: 'displayfield',
                    value: moduleParams['serverImageId'],
                    fieldLabel: 'Cloud Image ID',
                    plugins: {
                        ptype: 'fieldicons',
                        position: 'outer',
                        icons: [{id: 'question', tooltip: "This Server was launched using an Image that is no longer used in its Role's configuration."}]
                    },
                    listeners: {
                        afterrender: function() {
                            if (moduleParams['imageId'] != moduleParams['serverImageId'])
                                this.toggleIcon('question', true);
                        }
                    }
                }]
            }]
        }, {
            xtype: 'fieldset',
            title: 'Create new Image',
            cls: 'x-fieldset-bottom-padding',
            itemId: 'image',
            items: [{
                xtype: 'container',
                layout: {
                    type: 'hbox'
                },
                items: [{
                    xtype: 'container',
                    cls: 'x-container-fieldset-lr',
                    width: 450,
                    layout: 'anchor',
                    items: [{
                        xtype: 'textfield',
                        name: 'name',
                        allowBlank: false,
                        vtype: 'rolename',
                        value: moduleParams['roleName'],
                        fieldLabel: 'Name'
                    }, {
                        xtype: 'combobox',
                        fieldLabel: 'Scope',
                        plugins: {
                            ptype: 'fieldinnericonscope',
                            field: 'id',
                            tooltipScopeType: 'role'
                        },
                        editable: false,
                        name: 'scope',
                        value: scope,
                        store: {
                            fields: ['id', 'name'],
                            data: imageScopes
                        },
                        valueField: 'id',
                        displayField: 'name',
                        listeners: {
                            change: function(field, value) {
                                var el = this.up('#image').down('[name="replaceImage"]');
                                if (!isManageableRole || value != moduleParams['roleScope'] || !form.down('#role').collapsed) {
                                    el.disable().setValue();
                                } else {
                                    el.enable();
                                }
                            }
                        }
                    }]
                }, {
                    xtype: 'container',
                    flex: 1,
                    cls: 'x-container-fieldset-lr',
                    layout: 'anchor',
                    items: [{
                        xtype: 'checkbox',
                        name: 'replaceImage',
                        disabled: !isManageableRole,
                        boxLabel: 'Replace Image "' + moduleParams['imageName'] + ' [' + moduleParams['imageId'] + ']' +
                            '" on Role "' + moduleParams['roleName'] + '" with the newly created Image',
                        listeners: {
                            change: function(field, value) {
                                this.up().up().next()[value ? 'show' : 'hide']();
                            }
                        }
                    }]
                }]
            }, {
                xtype: 'displayfield',
                cls: 'x-form-field-warning x-container-fieldset-lr',
                anchor: '100%',
                hidden: true,
                value: 'This will affect all future Servers of "' + moduleParams['roleName'] + '" Role launched in "' +
                (moduleParams['cloudLocation'] ? moduleParams['cloudLocation'] : 'all locations') + '". Running Servers will not be affected.'
            }]
        }, {
            xtype: 'fieldset',
            checkboxToggle: true,
            checkboxName: 'createRole',
            collapsed: true,
            title: 'Also create new Role',
            cls: 'x-fieldset-bottom-padding',
            itemId: 'role',
            items: [{
                xtype: 'container',
                layout: {
                    type: 'hbox'
                },
                items: [{
                    xtype: 'container',
                    cls: 'x-container-fieldset',
                    style: 'padding-top: 0px',
                    width: 450,
                    layout: 'anchor',
                    items: [{
                        xtype: 'textfield',
                        name: 'name',
                        fieldLabel: 'Name',
                        disabled: true,
                        vtype: 'rolename',
                        allowBlank: false,
                        maxWidth: 450,
                        listeners: {
                            blur: function () {
                                if (this.isValid()) {
                                    form.down('#image').down('[name="name"]').setValue(this.getValue() + '-' + Ext.Date.format(new Date(), 'YmdHi'));
                                }
                            }
                        }
                    }, {
                        xtype: 'textarea',
                        name: 'roleDescription',
                        height: 64,
                        fieldLabel: 'Description'
                    }, {
                        xtype: 'combobox',
                        fieldLabel: 'Scope',
                        plugins: {
                            ptype: 'fieldinnericonscope',
                            field: 'id',
                            tooltipScopeType: 'role'
                        },
                        editable: false,
                        name: 'scope',
                        value: scope,
                        disabled: true,
                        store: {
                            fields: ['id', 'name'],
                            data: roleScopes
                        },
                        valueField: 'id',
                        displayField: 'name',
                        listeners: {
                            change: function(field, value) {
                                form.down('#image').down('[name="scope"]').setValue(value);
                            }
                        }
                    }]
                }, {
                    xtype: 'container',
                    flex: 1,
                    cls: 'x-container-fieldset',
                    style: 'padding-top: 0px',
                    layout: 'anchor',
                    items: [{
                        xtype: 'checkbox',
                        submitValue: false,
                        itemId: 'replaceRole',
                        disabled: replacementOptions.length == 0,
                        boxLabel: 'Replace Role "' + moduleParams['roleName'] + '" with the newly created Role',
                        listeners: {
                            change: function (field, value) {
                                this.next().setDisabled(!value);
                                this.up().up().next()[value ? 'show' : 'hide']();
                            }
                        }
                    }, {
                        xtype: 'combo',
                        store: replacementOptions,
                        queryMode: 'local',
                        allowBlank: false,
                        disabled: true,
                        editable: false,
                        name: 'replaceRole',
                        //margin: '0 0 0 12'
                    }]
                }]
            }, {
                xtype: 'displayfield',
                cls: 'x-form-field-warning x-container-fieldset-lr',
                anchor: '100%',
                hidden: true,
                value: 'Running Servers will not be affected'
            }],
            listeners: {
                boxready: function() {
                    if (!(Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage') || Scalr.isAllowed('ROLES_ACCOUNT', 'manage'))) {
                        this.checkboxCmp.disable();
                    }
                },
                expand: function() {
                    var ct = this.prev('#image'), scope;
                    this.up('form').down('#create').setText('Create Image and Role');

                    ct.down('[name="name"]').disable();
                    scope = ct.down('[name="scope"]').disable().getValue();
                    ct.down('[name="replaceImage"]').disable().setValue();

                    if (!Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage') && scope == 'environment') {
                        scope = 'account';
                    } else if (!Scalr.isAllowed('ROLES_ACCOUNT', 'manage')) {
                        scope = 'environment';
                    }

                    this.down('[name="name"]').enable();
                    this.down('[name="scope"]').enable().setValue(scope);
                    ct.down('[name="scope"]').setValue(scope);
                },
                collapse: function() {
                    var ct = this.prev('#image'), scopeEl = ct.down('[name="scope"]'), value = scopeEl.getValue();
                    this.up('form').down('#create').setText('Create Image');

                    this.down('[name="name"]').disable();
                    this.down('#replaceRole').setValue();
                    this.down('[name="replaceRole"]').disable().setValue();
                    this.down('[name="scope"]').disable();

                    ct.down('[name="name"]').enable();
                    ct.down('[name="scope"]').enable().setValue().setValue(value);
                }
            }
        }, {
            xtype: 'fieldset',
            title: 'Role options',
            itemId: 'roleOptions',
            hidden: true,
            items: [{
                xtype: 'textfield',
                name: 'roleName',
                value: moduleParams['roleName'],
                fieldLabel: 'Role name'
            }, {
                xtype: 'textarea',
                fieldLabel: 'Description',
                name: 'roleDescription',
                height: 100
            }]
        }, {
            xtype: 'fieldset',
            title: 'Root EBS options',
            hidden: !moduleParams['isVolumeSizeSupported'],
            defaults: {
                anchor: '100%',
                maxWidth: 450
            },
            items: [{
                xtype: 'fieldcontainer',
                fieldLabel: 'Size (GB)',
                layout: 'hbox',
                items: [{
                    xtype: 'textfield',
                    name: 'rootVolumeSize',
                    width: 100,
                    vtype: 'ebssize',
                    getEbsType: function() {
                        return this.up('form').down('[name="rootVolumeType"]').getValue();
                    },
                    getEbsIops: function() {
                        return this.up('form').down('[name="rootVolumeIops"]').getValue();
                    }
                }, {
                    padding: '0 0 0 5',
                    xtype: 'displayfield',
                    value: ' (Leave blank for default value)'
                }]
            }, {
                xtype: 'fieldcontainer',
                layout: 'hbox',
                hidden: !moduleParams['isVolumeTypeSupported'],
                disabled: !moduleParams['isVolumeTypeSupported'],
                fieldLabel: 'EBS type',
                items: [{
                    xtype: 'combo',
                    store: Ext.Array.merge([['', '']], Scalr.utils.getEbsTypes()),
                    valueField: 'id',
                    displayField: 'name',
                    editable: false,
                    queryMode: 'local',
                    value: '',
                    name: 'rootVolumeType',
                    flex: 1,
                    listeners: {
                        change: function (comp, value) {
                            var form = comp.up('form'),
                                iopsField = form.down('[name="rootVolumeIops"]');
                            if (value == 'io1') {
                                iopsField.show().enable().focus(false, 100);
                                var value = iopsField.getValue();
                                iopsField.setValue(value || 100);
                            } else {
                                iopsField.hide().disable();
                                form.down('[name="rootVolumeSize"]').isValid();
                            }
                        }
                    }
                }, {
                    xtype: 'textfield',
                    name: 'rootVolumeIops',
                    hidden: true,
                    disabled: true,
                    margin: '0 0 0 5',
                    vtype: 'iops',
                    allowBlank: false,
                    flex: 1,
                    maxWidth: 60
                }]
            }]
        }, {
            xtype: 'hidden',
            name: 'serverId',
            value: moduleParams['serverId']
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
                itemId: 'create',
                text: 'Create Image',
                minWidth: 150,
                handler: function () {
                    var frm = this.up('form').getForm();
                    if (frm.isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            form: frm,
                            url: '/servers/xServerCreateSnapshot/',
                            success: function (data) {
                                if (Scalr.isAllowed('IMAGES_ENVIRONMENT', 'bundletasks')) {
                                    Scalr.event.fireEvent('redirect', '#/bundletasks?id=' + data.bundleTaskId);
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
                minWidth: 150,
                handler: function () {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]
    });

    return form;
});

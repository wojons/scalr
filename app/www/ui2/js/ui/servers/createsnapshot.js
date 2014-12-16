Scalr.regPage('Scalr.ui.servers.createsnapshot', function  (loadParams, moduleParams) {
    return Ext.create('Ext.form.Panel', {
        scalrOptions: {
            'modal': true
        },
        width: 850,
        title: 'Create server snapshot',
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 80
        },

        items: [{
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            value: moduleParams['showWarningMessage'] || '',
            hidden: !moduleParams['showWarningMessage']
        }, {
            xtype: 'container',
            layout: 'hbox',
            cls: 'x-fieldset-separator-bottom',
            items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                flex: 1,
                items: [{
                    xtype: 'displayfield',
                    value: moduleParams['serverId'],
                    fieldLabel: 'Server ID'
                }, {
                    xtype: 'displayfield',
                    value: moduleParams['farmId'],
                    fieldLabel: 'Farm ID'
                }, {
                    xtype: 'displayfield',
                    value: moduleParams['farmName'],
                    fieldLabel: 'Farm name'
                }]
            }, {
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-left',
                margin: '0 0 0 12',
                flex: 1,
                defaults: {
                    labelWidth: 100
                },
                items: [{
                    xtype: 'displayfield',
                    value: moduleParams['roleName'],
                    fieldLabel: 'Role name'
                }, {
                    xtype: 'displayfield',
                    value: moduleParams['cloudLocation'],
                    fieldLabel: 'Cloud Location'
                }, {
                    xtype: 'displayfield',
                    value: moduleParams['imageId'],
                    fieldLabel: 'Image ID',
                    icons: {
                        question: true
                    },
                    iconsPosition: 'outer',
                    questionTooltip: "This Server was launched using an Image that is no longer used in its Role's configuration.",
                    listeners: {
                        afterrender: function() {
                            if (moduleParams['imageId'] != moduleParams['roleImageId'])
                                this.toggleIcon('question', true);
                        }
                    }
                }]
            }]
        }, {
            xtype: 'fieldset',
            checkboxToggle: true,
            title: 'Create new Image',
            items: [{
                xtype: 'textfield',
                maxWidth: 450,
                name: 'name',
                itemId: 'name',
                allowBlank: false,
                vtype: 'rolename',
                value: moduleParams['roleName'],
                fieldLabel: 'Name'
            }, {
                xtype: 'checkbox',
                name: 'replaceImage',
                disabled: moduleParams['isSharedRole'],
                boxLabel: 'Replace Image "' +
                ((moduleParams['roleImageId'] != moduleParams['imageName']) ? (moduleParams['imageName'] + ' [' + moduleParams['roleImageId'] + ']') : moduleParams['roleImageId']) +
                    '" on Role "' + moduleParams['roleName'] + '" with the newly created Image',
                listeners: {
                    change: function(field, value) {
                        this.next()[value ? 'show' : 'hide']();
                    }
                }
            }, {
                xtype: 'displayfield',
                cls: 'x-form-field-warning',
                anchor: '100%',
                hidden: true,
                value: 'This will affect all future Servers of "' + moduleParams['roleName'] + '" Role launched in "' +
                (moduleParams['cloudLocation'] ? moduleParams['cloudLocation'] : 'all locations') + '". Running Servers will not be affected.'
            }],
            listeners: {
                boxready: function() {
                    this.checkboxCmp.disable();
                }
            }
        }, {
            xtype: 'fieldset',
            checkboxToggle: true,
            checkboxName: 'createRole',
            collapsed: true,
            title: 'Also create new Role',
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
                        this.up('fieldset').prev().down('#name').setValue(this.getValue() + '-' + Ext.Date.format(new Date(), 'YmdHi'));
                    }
                }
            }, {
                xtype: 'textarea',
                name: 'description',
                maxWidth: 450,
                height: 64,
                fieldLabel: 'Description'
            }, {
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'checkbox',
                    submitValue: false,
                    itemId: 'replaceRole',
                    boxLabel: 'Replace Role "' + moduleParams['roleName'] + '" with the newly created Role:',
                    listeners: {
                        change: function (field, value) {
                            this.next().setDisabled(!value);
                            this.up().next()[value ? 'show' : 'hide']();
                        }
                    }
                }, {
                    xtype: 'combo',
                    store: [['farm', 'ONLY on the current Farm "' + moduleParams['farmName'] + '"'], ['all', 'on ALL Farms']],
                    queryMode: 'local',
                    allowBlank: false,
                    disabled: true,
                    flex: 1,
                    minWidth: 200,
                    editable: false,
                    name: 'replaceRole',
                    margin: '0 0 0 12'
                }]
            }, {
                xtype: 'displayfield',
                cls: 'x-form-field-warning',
                anchor: '100%',
                hidden: true,
                value: 'Running Servers will not be affected'
            }],
            listeners: {
                expand: function() {
                    this.up('form').down('#create').setText('Create Image and Role');
                    this.down('[name="name"]').enable();

                    var field = this.prev().down('#name');
                    field.disable();
                    field.name = 'imageName';

                    this.prev().down('[name="replaceImage"]').disable().setValue();
                },
                collapse: function() {
                    this.up('form').down('#create').setText('Create Image');
                    this.down('[name="name"]').disable();
                    this.down('#replaceRole').setValue();

                    var field = this.prev().down('#name');
                    field.enable();
                    field.name = 'name';

                    if (! moduleParams['isSharedRole'])
                        this.prev().down('[name="replaceImage"]').enable();
                }
            }
        }, {
            xtype: 'fieldset',
            title: 'Role options',
            itemId: 'roleOptions',
            hidden: true,
            //cls: 'x-fieldset-separator-none',
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
                    validator: function (value) {
                        var form = this.up('form');
                        var minValue = 1;
                        if (value) {
                            if (form.down('[name="rootVolumeType"]').getValue() === 'io1') {
                                minValue = Scalr.utils.getMinStorageSizeByIops(form.down('[name="rootVolumeIops"]').getValue());
                            }
                            if (value * 1 > Scalr.constants.ebsMaxStorageSize) {
                                return 'Maximum value is ' + Scalr.constants.ebsMaxStorageSize + '.';
                            } else if (value * 1 < minValue) {
                                return 'Minimum value is ' + minValue + '.';
                            }
                        }
                        return true;
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
                    store: Ext.Array.merge([['', '']], Scalr.constants.ebsTypes),
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
                width: 200,
                handler: function () {
                    var frm = this.up('form').getForm();
                    if (frm.isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            form: frm,
                            url: '/servers/xServerCreateSnapshot/',
                            success: function () {
                                Scalr.event.fireEvent('redirect', '#/bundletasks/view');
                            }
                        });
                    }
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                width: 200,
                handler: function () {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]
    });
});

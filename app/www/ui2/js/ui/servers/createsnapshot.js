Scalr.regPage('Scalr.ui.servers.createsnapshot', function (loadParams, moduleParams) {
    var iopsMin = 100, iopsMax = 400, maxEbsStorageSize = 1000;
	return Ext.create('Ext.form.Panel', {
		scalrOptions: {
			'modal': true
		},
		width: 900,
		title: 'Snapshot server to create a new role',
		fieldDefaults: {
			anchor: '100%'
		},

		items: [{
			xtype: 'displayfield',
			cls: 'x-form-field-warning',
			value: moduleParams['showWarningMessage'] || '',
			hidden: ! moduleParams['showWarningMessage']
		}, {
			xtype: 'fieldset',
			title: 'Server details',
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
			}, {
				xtype: 'displayfield',
				value: moduleParams['roleName'],
				fieldLabel: 'Role name'
			}]
		}, {
			xtype: 'fieldset',
			title: 'Role replacement scope',
            //hidden: Scalr.flags['betaMode'],
			items: [{
				xtype: 'radiogroup',
				columns: 1,
				hideLabel: true,
				listeners: {
					change: function (field, value) {
						if (value['replaceType'] != 'no_replace')
							this.next().show();
						else
							this.next().hide();
					}
				},
				items: [{
					name: 'replaceType',
					boxLabel: moduleParams['replaceNoReplace'],
                    checked: true,
					inputValue: 'no_replace'
				}, {
					name: 'replaceType',
					boxLabel: moduleParams['replaceFarmReplace'],
					inputValue: 'replace_farm'
				}, {
					name: 'replaceType',
					boxLabel: moduleParams['replaceAll'],
					inputValue: 'replace_all'
				}]
			}, {
				xtype: 'checkbox',
				name: 'noServersReplace',
				checked: moduleParams['dbSlave'],
				readOnly: moduleParams['dbSlave'],
                hidden: true,
				boxLabel: 'Do not replace servers that are already running. Only NEW servers will be launched with the created role.'
			}]
        }, {
            xtype: 'fieldset',
            //title: 'Replacement options',
            hidden: !Scalr.flags['betaMode'],
            items: [{
                xtype: 'hidden',
                name: 'useNewReplacementOption',
                value: true
            }, {
                xtype: 'buttongroupfield',
                name: 'replace2',
                value: 'role',
                items: [{
                    text: 'Create new Role',
                    value: 'role',
                    width: 200
                }, {
                    text: 'Create new Image',
                    value: 'image',
                    width: 200
                }],
                listeners: {
                    change: function(field, value) {
                        this.next('#replaceRole')[value == 'role' ? 'show' : 'hide']();
                        this.next('#replaceImage')[value == 'image' ? 'show' : 'hide']();
                        this.next('#replaceImageWarning')[value == 'image' && this.next('#replaceImage').getValue() ? 'show' : 'hide']();
                    }
                }
            }, {
                xtype: 'checkbox',
                boxLabel: 'Replace ' + moduleParams['roleName'] + ' with the new Role',
                itemId: 'replaceRole',
                listeners: {
                    change: function (field, value) {
                        this.up().next()

                        if (value['replaceType'] != 'no_replace')
                            this.next().show();
                        else
                            this.next().hide();
                    }
                }
            }, {
                xtype: 'checkbox',
                boxLabel: 'Replace ' + moduleParams['imageId'] + ' on ' + moduleParams['roleName'] + ' with it',
                itemId: 'replaceImage',
                hidden: true,
                listeners: {
                    change: function (field, value) {
                        this.next()[value ? 'show' : 'hide']();
                    }
                }
            }, {
                xtype: 'displayfield',
                itemId: 'replaceImageWarning',
                hidden: true,
                cls: 'x-form-field-warning',
                value: 'Warning: this will affect all future instances of %Role Name% launched in %Image Location%'
            }, {
                xtype: 'checkbox',
                name: 'noServersReplace2',
                checked: moduleParams['dbSlave'],
                readOnly: moduleParams['dbSlave'],
                hidden: true,
                boxLabel: 'Do not replace servers that are already running. Only NEW servers will be launched with the created role.'
            }]
        }, {
			xtype: 'fieldset',
			title: 'Role options',
            cls: 'x-fieldset-separator-none',
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
        }, Scalr.flags['betaMode'] ? {
            xtype:'fieldset',
            title: 'Root EBS options',
            cls: 'x-fieldset-separator-top',
            hidden: !(moduleParams['platform'] == 'ec2' && moduleParams['isVolumeSizeSupported'] == 1),
            defaults: {
                anchor: '100%',
                maxWidth: 480
            },
            items: [{
                xtype: 'fieldcontainer',
                fieldLabel: 'Size (GB)',
                layout: 'hbox',
                items: [{
                    xtype: 'textfield',
                    name: 'rootVolumeSize',
                    width: 100,
                    validator: function(value) {
                        var form = this.up('form');
                        var minValue = 1;
                        if (value) {
                            if (form.down('[name="rootVolumeType"]').getValue() === 'io1') {
                                minValue = Math.ceil(form.down('[name="rootVolumeIops"]').getValue()*1/10);
                            }
                            if (value*1 > maxEbsStorageSize) {
                                return 'Maximum value is ' + maxEbsStorageSize + '.';
                            } else if (value*1 < minValue) {
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
                fieldLabel: 'EBS type',
                items: [{
                    xtype: 'combo',
                    store: [['standard', 'Standard EBS (Magnetic)'],['gp2', 'General Purpose (SSD)'], ['io1', 'Provisioned IOPS (' + iopsMin + ' - ' + iopsMax + '): ']],
                    valueField: 'id',
                    displayField: 'name',
                    editable: false,
                    queryMode: 'local',
                    value: 'standard',
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
                    maskRe: new RegExp('[0123456789]', 'i'),
                    validator: function(value){
                        if (value*1 > iopsMax) {
                            return 'Maximum value is ' + iopsMax + '.';
                        } else if (value*1 < iopsMin) {
                            return 'Minimum value is ' + iopsMin + '.';
                        }
                        return true;
                    },
                    flex: 1,
                    maxWidth: 60
                }]
            }]
        } : {
            xtype: 'fieldcontainer',
            fieldLabel: 'Root EBS size',
            layout: 'hbox',
            margin: '0 0 0 32',
            items: [{
                xtype: 'textfield',
                name: 'rootVolumeSize',
                width: 100
            }, {
                padding: '0 0 0 5',
                xtype: 'displayfield',
                value: 'GB (Leave blank for default value)'
            }],
            hidden: !(moduleParams['platform'] == 'ec2' && moduleParams['isVolumeSizeSupported'] == 1)
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
				text: 'Create role',
				handler: function() {
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
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});
});

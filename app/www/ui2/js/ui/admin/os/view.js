Scalr.regPage('Scalr.ui.admin.os.view', function (loadParams, moduleParams) {
    var osFamilies = {};
    var osFilterItems = [{
        text: 'All Families',
        value: null,
        iconCls: 'x-icon-osfamily-small'
    }];

    var uniqFamilies = {};
    Ext.each(moduleParams['os'], function(os){
        if (!uniqFamilies[os.family]) {
            osFilterItems.push({
                text: Scalr.utils.beautifyOsFamily(os.family),
                value: os.family,
                iconCls: 'x-icon-osfamily-small x-icon-osfamily-small-' + os.family
            });
            uniqFamilies[os.family] = 1;
        }
    });

    var store = Ext.create('store.store', {
        fields: [
            'id',
            'name',
            'family',
            'generation',
            'version',
            {name: 'status', defaultValue: 'active'},
            {name: 'isSystem', defaultValue: 0},
            'used'
        ],
        data: moduleParams['os'],
		proxy: {
			type: 'ajax',
			url: '/admin/os/xList/',
            reader: {
                type: 'json',
                rootProperty: 'os',
                successProperty: 'success'
            }
		},
		sorters: [{
			property: 'name'
		}]
	});

    var grid = Ext.create('Ext.grid.Panel', {
        cls: 'x-panel-column-left',
        store: store,
        flex: 1,
        selModel: 'selectedmodel',
        plugins: [
            'focusedrowpointer',
            {ptype: 'selectedrecord', disableSelection: false, clearOnRefresh: true}
        ],
        columns: [{
            header: 'ID',
            dataIndex: 'id',
            width: 150
        },{
            header: 'OS',
            dataIndex: 'name',
            xtype: 'templatecolumn',
            tpl: '<img class="x-icon-osfamily-small x-icon-osfamily-small-{family}" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;{name}',
            flex: 1
        },{
            header: 'Family',
            dataIndex: 'family',
            width: 100
        },{
            header: 'Generation',
            dataIndex: 'generation',
            width: 120
        },{
            header: 'Version',
            dataIndex: 'version',
            width: 90
        }, {
            header: 'Usage',
            minWidth: 90,
            width: 90,
            dataIndex: 'used',
            sortable: true,
            resizable: false,
            xtype: 'statuscolumn',
            statustype: 'osusage'
        },{
            header: 'System',
            dataIndex: 'isSystem',
            xtype: 'templatecolumn',
            sortable: false,
            align: 'center',
            tpl: '<tpl if="isSystem!=1">&mdash;<tpl else><img src="' + Ext.BLANK_IMAGE_URL + '" class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"/></tpl>',
            width: 70
        },{ text: 'Status', width: 80, minWidth: 80, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'os', qtipConfig: {width: 280}
        }],
        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            emptyText: 'No operating systems found.',
            loadingText: 'Loading operating systems ...',
            deferEmptyText: false
        },
        listeners: {
            selectionchange: function(selModel, selected) {
                var isSystemOsSelected = false,
                    btn;
                Ext.each(selected, function(record){
                    isSystemOsSelected = record.get('isSystem') == 1;
                    return !isSystemOsSelected;
                });
                btn = this.down('#delete');
                btn.setDisabled(!selected.length || isSystemOsSelected);
                this.down('#activate').setDisabled(!selected.length);
                this.down('#deactivate').setDisabled(!selected.length);
            }
        },
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12',
                handler: function() {
                    var action = this.getItemId(),
                        actionMessages = {
                            'delete': ['Delete selected operating system(s)', 'Deleting operating system(s) ...'],
                            'activate': ['Activate selected operating system(s)', 'Activating operating system(s) ...'],
                            'deactivate': ['Deactivate selected operating system(s)', 'Deactivate operating system(s) ...']
                        },
                        selModel = grid.getSelectionModel(),
                        ids = [],
                        request = {};

                    for (var i=0, records = selModel.getSelection(), len=records.length; i<len; i++) {
                        ids.push(records[i].get('id'));
                    }

                    request = {
                        confirmBox: {
                            msg: actionMessages[action][0],
                            type: action
                        },
                        processBox: {
                            msg: actionMessages[action][1],
                            type: action
                        },
                        params: {action: action},
                        success: function (data) {
                            if (data.processed && data.processed.length) {
                                switch (action) {
                                    case 'delete':
                                        var recordsToDelete = [];
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            recordsToDelete.push(grid.store.getById(data.processed[i]));
                                            selModel.deselect(recordsToDelete[i]);
                                        }
                                        grid.store.remove(recordsToDelete);
                                    break;
                                    case 'deactivate':
                                    case 'activate':
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            var record = grid.store.getById(data.processed[i]);
                                            if (record) {
                                                record.set('status', action === 'activate' ? 'active' : 'inactive');
                                                selModel.deselect(record);
                                            }
                                        }
                                    break;
                                }
                            }
                        }
                    };
                    request.url = '/admin/os/xGroupActionHandler';
                    request.params['ids'] = Ext.encode(ids);

                    Scalr.Request(request);
                }
            },
            items: [{
                xtype: 'filterfield',
                itemId: 'liveSearch',
                margin: 0,
                minWidth: 60,
                maxWidth: 200,
                flex: 1,
                filterFields: ['id', 'name', 'version', 'generation'],
                store: store,
                handler: null
            },{
                xtype: 'cyclealt',
                itemId: 'family',
                name: 'family',
                width: 150,
                getItemIconCls: false,
                hidden: osFilterItems.length === 2,
                changeHandler: function (comp, item) {
                    if (item.value) {
                        store.addFilter({id: 'family', property: 'family', value: item.value});
                    } else {
                        store.removeFilter('family');
                    }
                },
                getItemText: function(item) {
                    return item.value ? 'OS: &nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '"/>' : item.text;
                },
                menu: {
                     cls: 'x-menu-light x-menu-cycle-button-filter',
                     minWidth: 200,
                     items: osFilterItems
                 }
            },{
                xtype: 'cyclealt',
                itemId: 'isSystem',
                name: 'isSystem',
                width: 140,
                getItemIconCls: false,
                changeHandler: function (comp, item) {
                    if (Ext.isNumeric(item.value)) {
                        store.addFilter({id: 'isSystem', property: 'isSystem', value: item.value});
                    } else {
                        store.removeFilter('isSystem');
                    }
                },
                menu: {
                     cls: 'x-menu-light x-menu-cycle-button-filter',
                     minWidth: 140,
                     items: [{
                         text: 'All OS',
                         value: ''
                     },{
                         text: 'System OS',
                         value: 1
                     },{
                         text: 'Custom OS',
                         value: 0
                     }]
                 }
            },{
                xtype: 'tbfill',
                flex: .1,
                margin: 0
            },{
                itemId: 'add',
                text: 'New OS',
                cls: 'x-btn-green',
                enableToggle: true,
                handler: Ext.emptyFn,
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();
                        form.loadRecord(store.createModel({id: 0}));
                        form.down('[name=id]').focus();

                        return;
                    }

                    form.hide();
                }
            },{
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function() {
                    store.load();
                }
            },{
                itemId: 'activate',
                iconCls: 'x-btn-icon-activate',
                disabled: true,
                tooltip: 'Activate operating systems'
            },{
                itemId: 'deactivate',
                iconCls: 'x-btn-icon-suspend',
                disabled: true,
                tooltip: 'Deactivate operating systems'
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Delete operating systems'
            }]
        }]
    });

	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
		layout: {
            type: 'vbox',
            align: 'stretch'
        },
		listeners: {
			hide: function() {
                grid.down('#add').toggle(false, true);
			},
			beforeloadrecord: function(record) {
				var frm = this.getForm(),
					isNewRecord = !record.store,
                    isSystemRecord = record.get('isSystem') == 1;

                osFamilies = {};
                store.getUnfiltered().each(function(record){
                    var data = record.getData();
                    if (data['family'] && data['isSystem'] == 1) {
                        osFamilies[data['family']] = osFamilies[data['family']] || {generations: {}, family: data['family']};
                        if (data['generation']) {
                            osFamilies[data['family']].generations[data['generation']] = osFamilies[data['family']].generations[data['generation']] || {versions: []};
                            if (data['version']) Ext.Array.include(osFamilies[data['family']].generations[data['generation']].versions, data['version']);
                        }

                    }
                });

                osFamilies[frm.findField('family').customId] = {family: ''};
                frm.findField('family').store.load({data: osFamilies});


                frm.getFields().each(function(field){
                    if (field.name !== 'status') {
                        field.setReadOnly(isSystemRecord);
                    }
                });
                this.down('fieldset').setTitle((isNewRecord ? 'New' : 'Edit') + ' operating system');
				var c = this.query('component[cls~=hideoncreate], #delete');
				for (var i=0, len=c.length; i<len; i++) {
					c[i].setVisible(!isNewRecord);
				}
                grid.down('#add').toggle(isNewRecord, true);
                this.down('#delete').setDisabled(isSystemRecord).setTooltip(isSystemRecord ? 'This Operating System can\'t be removed' : '');
            },
            afterloadrecord: function(record) {
                var frm = this.getForm(),
                    field,
                    family = osFamilies[record.get('family')],
                    generation = family && family.generations[record.get('generation')];
                if (!record.get('id')) {
                    frm.findField('id').setValue('').clearInvalid();
                } else {
                    field = frm.findField('family');
                    if (!family) {
                        field.setValue(field.customId);
                        field.next('[name="customFamily"]').show().enable().setValue(record.get('family'));
                    } else {
                        field.next('[name="customFamily"]').hide().disable();
                    }

                    field = frm.findField('generation');
                    if (!generation) {
                        field.setValue(field.customId);
                        field.next('[name="customGeneration"]').show().enable().setValue(record.get('generation'));
                    } else {
                        field.next('[name="customGeneration"]').hide().disable();
                    }

                    field = frm.findField('version');
                    if (!generation || !Ext.Array.contains(generation.versions, record.get('version'))) {
                        if (record.get('isSystem') == 1) {
                            field.store.removeAll();
                            field.reset();
                            field.next('[name="customVersion"]').hide().disable();
                        } else {
                            field.setValue(field.customId);
                            field.next('[name="customVersion"]').show().enable().setValue(record.get('version'));
                        }
                    } else {
                        field.next('[name="customVersion"]').hide().disable();
                    }
                }
            }
		},
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 90,
            allowBlank: false
		},
		items: [{
            xtype: 'fieldset',
            itemId: 'formtitle',
            cls: 'x-fieldset-separator-none',
            items: [{
                xtype: 'textfield',
                allowBlank: false,
                fieldLabel: 'ID',
                hideInputOnReadOnly: true,
                name: 'id',
                validator: function(value) {
                    if ((value||'').match(/[^a-z0-9\-]/)) {
                        return 'ID must contain only lower case letters, numbers and hyphens';
                    }
                    return true;
                }
            },{
                xtype: 'textfield',
                name: 'name',
                allowBlank: false,
                fieldLabel: 'Name',
                hideInputOnReadOnly: true
            },{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                fieldLabel: 'Family',
                items: [{
                    xtype: 'combo',
                    name: 'family',
                    valueField: 'id',
                    displayField: 'id',
                    editable: false,
                    allowBlank: false,
                    flex: 1,
                    hideInputOnReadOnly: true,
                    customId: 'Custom family',
                    plugins: {
                        ptype: 'fieldinnericon',
                        field: 'family',
                        iconClsPrefix: 'x-icon-osfamily-small x-icon-osfamily-small-'
                    },
                    store: {
                        fields: ['id', 'generations', 'family'],
                        proxy: {
                            type: 'object',
                            reader: {
                                idFieldFromIndex: true
                            }
                        }
                    },
                    listeners: {
                        change: function(comp, value) {
                            var frm = this.up('form').getForm(),
                                record,
                                generationField = frm.findField('generation'),
                                generations = {},
                                customFamilyField = comp.next('[name="customFamily"]'),
                                isCustomFamily = value === comp.customId;
                            if (value) {
                                customFamilyField.setDisabled(!isCustomFamily).setVisible(isCustomFamily);
                                if (isCustomFamily && comp.hasFocus) {
                                    customFamilyField.focus(true);
                                } else if (record = comp.findRecordByValue(value)) {
                                    generations = Ext.clone(record.get('generations')) || generations;
                                }
                                generations[generationField.customId] = {};
                                generationField.store.load({data: generations});
                                generationField.up().show();
                                if (generationField.getValue()!==generationField.customId) generationField.reset();
                            } else {
                                customFamilyField.hide();
                                generationField.up().hide();
                            }

                        }
                    },
                    getSubmitData: function() {
                        var value = this.getValue();
                        return {
                            family: value == this.customId ? this.next('[name="customFamily"]').getValue() : value
                        };
                    }
                },{
                    xtype: 'textfield',
                    name: 'customFamily',
                    allowBlank: false,
                    fieldLabel: ':',
                    labelWidth: 6,
                    margin: '0 0 0 6',
                    submitValue: false,
                    flex: 1,
                    hidden: true,
                    disabled: true
                }]
            },{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                fieldLabel: 'Generation',
                hidden: true,
                items: [{
                    xtype: 'combo',
                    name: 'generation',
                    valueField: 'id',
                    displayField: 'id',
                    editable: false,
                    allowBlank: false,
                    flex: 1,
                    autoSetSingleValue: true,
                    hideInputOnReadOnly: true,
                    customId: 'Custom generation',
                    store: {
                        fields: ['id', 'versions'],
                        proxy: {
                            type: 'object',
                            reader: {
                                idFieldFromIndex: true
                            }
                        }
                    },
                    listeners: {
                        change: function(comp, value) {
                            var frm = this.up('form').getForm(),
                                record,
                                versionField = frm.findField('version'),
                                versions = [],
                                customGenerationField = comp.next('[name="customGeneration"]'),
                                isCustomGeneration = value === comp.customId;
                            if (value) {
                                customGenerationField.setDisabled(!isCustomGeneration).setVisible(isCustomGeneration);
                                if (isCustomGeneration && comp.hasFocus) {
                                    customGenerationField.focus(true);
                                } else if (record = comp.findRecordByValue(value)) {
                                    versions = Ext.clone(record.get('versions')) || versions;
                                }
                                versions.push(versionField.customId);
                                versionField.store.load({data: Ext.Array.map(versions, function(value){return {id: value};})});
                                versionField.up().show();
                                if (versionField.getValue()!==versionField.customId) versionField.reset();
                            } else {
                                versionField.up().hide();
                            }
                        }
                    },
                    getSubmitData: function() {
                        var value = this.getValue();
                        return {
                            generation: value == this.customId ? this.next('[name="customGeneration"]').getValue() : value
                        };
                    }
                },{
                    xtype: 'textfield',
                    name: 'customGeneration',
                    allowBlank: false,
                    fieldLabel: ':',
                    labelWidth: 6,
                    margin: '0 0 0 6',
                    submitValue: false,
                    flex: 1,
                    hidden: true,
                    disabled: true
                }]
            },{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                fieldLabel: 'Version',
                hidden: true,
                items: [{
                    xtype: 'combo',
                    name: 'version',
                    valueField: 'id',
                    displayField: 'id',
                    editable: false,
                    allowBlank: true,
                    flex: 1,
                    autoSetSingleValue: true,
                    hideInputOnReadOnly: true,
                    customId: 'Custom version',
                    store: {
                        fields: ['id'],
                        proxy: 'object'
                    },
                    listeners: {
                        change: function(comp, value) {
                            var customVersionField = comp.next('[name="customVersion"]'),
                                isCustomVersion = value === comp.customId;
                            if (value) {
                                customVersionField.setDisabled(!isCustomVersion).setVisible(isCustomVersion);
                                if (isCustomVersion && comp.hasFocus) customVersionField.focus(true);
                            }
                        }
                    },
                    getSubmitData: function() {
                        var value = this.getValue();
                        return {
                            version: value == this.customId ? this.next('[name="customVersion"]').getValue() : value
                        };
                    }
                },{
                    xtype: 'textfield',
                    name: 'customVersion',
                    fieldLabel: ':',
                    labelWidth: 6,
                    margin: '0 0 0 6',
                    allowBlank: true,
                    submitValue: false,
                    flex: 1,
                    hidden: true,
                    disabled: true
                }]
            },{
                xtype: 'buttongroupfield',
                name: 'status',
                fieldLabel: 'Status',
                defaults: {
                    width: 120
                },
                items: [{
                    text: 'Active',
                    value: 'active'
                },{
                    text: 'Inactive',
                    value: 'inactive'
                }]
            },{
                xtype: 'displayfield',
                cls: 'hideoncreate',
                fieldLabel: 'Usage',
                name: 'used',
                renderer: function(value) {
                    var text;
                    if (value) {
                        text = [];
                        if (value['rolesCount'] > 0) {
                            text.push('<b>'+ value['rolesCount']+'</b>&nbsp;Role(s)');
                        }
                        if (value['imagesCount'] > 0) {
                            text.push((value['rolesCount'] > 0 ? ' and ' : '') + '<b>'+ value['imagesCount']+'</b>&nbsp;Image(s)');
                        }
                        text.push(' use this Operating System');
                        text = text.join('');
                    } else {
                        text = '&mdash;';
                    }
                    return text;
                }
            }]
		}],
		dockedItems: [{
			xtype: 'container',
            itemId: 'buttons',
			dock: 'bottom',
			cls: 'x-docked-buttons',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
            maxWidth: 1100,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
			items: [{
				itemId: 'save',
				xtype: 'button',
				text: 'Save',
				handler: function() {
					var frm = form.getForm(),
                        record = frm.getRecord();
					if (frm.isValid()) {
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/admin/os/xSave',
                            form: frm,
							success: function (data) {
								if (!record.store) {
									record = store.add(data.os)[0];
								} else {
									record.set(data.os);
								}
                                grid.clearSelectedRecord();
                                grid.setSelectedRecord(record);
							}
						});
					}
				}
			}, {
				itemId: 'cancel',
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
                    grid.clearSelectedRecord();
				}
			}, {
				itemId: 'delete',
				xtype: 'button',
				cls: 'x-btn-red',
				text: 'Delete',
				handler: function() {
					var record = form.getForm().getRecord();
					Scalr.Request({
						confirmBox: {
							msg: 'Delete operating system?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting...',
							type: 'delete'
						},
						scope: this,
						url: '/admin/os/xRemove',
						params: {id: record.get('id')},
						success: function (data) {
							record.store.remove(record);
						}
					});
				}
			}]
		}]
	});


	var panel = Ext.create('Ext.panel.Panel', {
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			maximize: 'all',
            menuTitle: 'Operating Systems',
            menuHref: '#/admin/os',
		},
        stateId: 'grid-admin-os-view',
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            flex: .4,
            maxWidth: 640,
            minWidth: 400,
            layout: 'fit',
            items: form
        }]
	});

	return panel;

});
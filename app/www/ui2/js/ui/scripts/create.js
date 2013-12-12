Scalr.regPage('Scalr.ui.scripts.create', function (loadParams, moduleParams) {
	var saveHandler = function (curRevFlag, executeFlag) {
		var params = {};
		if (moduleParams['script']) {
			params = { saveCurrentRevision: curRevFlag ? 1 : 0 };
		}

		if (form.getForm().isValid())
			Scalr.Request({
				processBox: {
					type: 'save'
				},
				url: '/scripts/xSave/',
				params: params,
				form: form.getForm(),
				success: function () {
					if (moduleParams['script']) {
						if (executeFlag)
							Scalr.event.fireEvent('redirect', '#/scripts/' + moduleParams['script']['id'] + '/execute');
						else
							Scalr.event.fireEvent('close');
					} else
						Scalr.event.fireEvent('redirect', '#/scripts/view');
				}
			});
	};

	var form = Ext.create('Ext.form.Panel', {
		width: 900,
		title: (moduleParams['script']) ? 'Scripts &raquo; Edit' : 'Scripts &raquo; Create',
		fieldDefaults: {
			anchor: '100%'
		},

		tools: [{
			type: 'maximize',
			handler: function () {
				Scalr.event.fireEvent('maximize');
			}
		}],
        layout: 'auto',
		items: [{
			xtype: 'fieldset',
			title: 'General information',
			items: [{
				xtype: 'container',
				layout: 'hbox',
				maxWidth: 836,
				items: [{
					xtype: 'textfield',
					name: 'name',
					fieldLabel: 'Name',
					labelWidth: 150,
					allowBlank: false,
					flex: 1
				}, {
					xtype: 'combo',
					fieldLabel: 'Version',
					name: 'version',
					labelWidth: 50,
					margin: '0 0 0 12',
					width: 110,
                    hidden: !moduleParams['script'],
					store: moduleParams['versions'],
					editable: false,
					value: moduleParams['script'] ? parseInt(moduleParams['script']['version']) : 1,
					queryMode: 'local',
					listeners: {
						change: function (field, value) {
							if (this.rendered && moduleParams['script']) {
								Scalr.Request({
									url: '/scripts/xGetScriptContent',
									params: { version: value, scriptId: moduleParams['script']['id'] },
									processBox: {
										type: 'load',
										msg: 'Loading script contents ...'
									},
									scope: this,
									success: function (data) {
										this.up('form').down('[name="script"]').codeMirror.setValue(data['script']);
									}
								});
							}
						}
					}
				}, {
                    xtype: 'button',
                    ui: 'action',
                    itemId: 'delete',
                    hidden: !moduleParams['script'] || moduleParams['versions'].length < 2,
                    margin: '0 0 0 8',
                    cls: 'x-btn-action-delete',
                    tooltip: 'Remove selected version',
                    handler: function() {
                        Scalr.Request({
                            confirmBox: {
                                type: 'delete',
                                msg: 'Are you sure want to delete selected version? This cannot be undone.'
                            },
                            processBox: {
                                type: 'delete'
                            },
                            url: '/scripts/xRemoveRevision',
                            params: {
                                id: moduleParams['script']['id'],
                                rev: this.prev().getValue()
                            },
                            success: function() {
                                Scalr.event.fireEvent('refresh');
                            }
                        });
                    }
                }]
			}, {
				xtype: 'textfield',
				name: 'description',
				labelWidth: 150,
				maxWidth: 836,
				fieldLabel: 'Description'
			},{
                xtype: 'buttongroupfield',
                fieldLabel: 'Default execution mode',
                editable: false,
                name: 'isSync',
                labelWidth: 150,
                defaults: {
                    width: 110
                },
                value: 1,
                items: [{
                    text: 'Blocking',
                    value: 1
                },{
                    text: 'Non-blocking',
                    value: 0
                }]
            }]
		}, {
			xtype: 'fieldset',
			collapsible: true,
			collapsed: true,
			title: 'Variables',
			items: [{
				xtype: 'displayfield',
				cls: 'x-form-field-info',
				value: 'Built in variables: <br>' + moduleParams['variables'] + '<br /><br /> You may use own variables as %variable%. Variable values can be set for each role in farm settings.'
			}]
		}, {
			xtype: 'fieldset',
			title: 'Script',
			labelWidth: 130,
            cls: 'x-fieldset-separator-none',
			items: [{
				xtype: 'displayfield',
				cls: 'x-form-field-warning',
				value: 'First line must contain shebang (#!/path/to/interpreter)'
			}, {
				xtype: 'codemirror',
				minHeight: 300,
				name: 'script',
				hideLabel: true,
				addResizeable: true,
				value: moduleParams['scriptContents']
			}]
		}, {
			xtype: 'hidden',
			name: 'id'
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
				xtype: 'splitbutton',
				text: 'Save',
				hidden: !moduleParams['script'],
				handler: function () {
					saveHandler(false);
				},
				menu: [{
					text: 'Save changes as new version (' + (parseInt(moduleParams['latestVersion']) + 1) + ')',
					hidden: !moduleParams['script'],
					handler: function () {
						saveHandler(false);
					}
				}, {
					text: 'Save changes as new version (' + (parseInt(moduleParams['latestVersion']) + 1) + ') and execute script',
					hidden: !moduleParams['script'],
					handler: function () {
						saveHandler(false, true);
					}
				}, {
					xtype: 'menuseparator'
				}, {
					text: 'Save changes in current version',
					hidden: !moduleParams['script'],
					handler: function () {
						saveHandler(true);
					}
				}, {
					text: 'Save changes in current version and execute script',
					hidden: !moduleParams['script'],
					handler: function () {
						saveHandler(true, true);
					}
				}, {
					text: 'Create new script',
					hidden: !!moduleParams['script'],
					handler: function () {
						saveHandler(true);
					}
				}]
			}, {
				xtype: 'button',
				text: 'Create',
				hidden: !!moduleParams['script'],
				handler: function () {
					saveHandler(true);
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

	if (moduleParams['script'])
		form.getForm().setValues(moduleParams['script']);

	return form;
});

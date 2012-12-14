Scalr.regPage('Scalr.ui.scripts.create', function (loadParams, moduleParams) {
	var saveHandler = function (curRevFlag, executeFlag) {
		var params = {};
		if (moduleParams['scriptId']) {
			if (curRevFlag)
				params = { saveCurrentRevision: 1, scriptId: moduleParams['scriptId'] };
			else
				params = { saveCurrentRevision: 0, scriptId: moduleParams['scriptId'] };
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
					if (moduleParams['scriptId']) {
						if (executeFlag)
							Scalr.event.fireEvent('redirect', '#/scripts/' + moduleParams['scriptId'] + '/execute');
						else
							Scalr.event.fireEvent('close');
					} else
						Scalr.event.fireEvent('redirect', '#/scripts/view');
				}
			});
	};

	var form = Ext.create('Ext.form.Panel', {
		bodyCls: 'x-panel-body-frame',
		width: 900,
		title: (moduleParams['scriptId']) ? 'Scripts &raquo; Edit' : 'Scripts &raquo; Create',
		fieldDefaults: {
			anchor: '100%'
		},

		tools: [{
			type: 'maximize',
			handler: function () {
				Scalr.event.fireEvent('maximize');
			}
		}],

		items: [{
			xtype: 'fieldset',
			title: 'General information',
			labelWidth: 130,
			items: [{
				xtype: 'textfield',
				name: 'scriptName',
				fieldLabel: 'Script name',
				allowBlank: false,
				value: moduleParams['scriptName']
			}, {
				xtype: 'textfield',
				name: 'scriptDescription',
				fieldLabel: 'Description',
				value: moduleParams['description']
			}, {
				xtype: 'combo',
				fieldLabel: 'Version',
				name: 'scriptVersion',
				store: moduleParams['versions'],
				editable: false,
				value: parseInt(moduleParams['version']),
				queryMode: 'local',
				listeners: {
					change: function (field, value) {
						if (moduleParams['scriptId']) {
							Scalr.Request({
								url: '/scripts/' + moduleParams['scriptId'] + '/xGetScriptContent',
								params: { version: value },
								processBox: {
									type: 'load',
									msg: 'Loading script contents ...'
								},
								scope: this,
								success: function (data) {
									this.up('form').down('[name="scriptContents"]').codeMirror.setValue(data['scriptContents']);
								}
							});
						}
					}
				}
			}]
		}, {
			xtype: 'fieldset',
			title: 'Script',
			labelWidth: 130,
			items: [{
				xtype: 'displayfield',
				fieldCls: 'x-form-field-info',
				value: 'Built in variables:<br />' + moduleParams['variables'] + '<br /><br /> You may use own variables as %variable%. Variable values can be set for each role in farm settings.'
			}, {
				xtype: 'displayfield',
				fieldCls: 'x-form-field-warning',
				value: 'First line must contain shebang (#!/path/to/interpreter)'
			}, {
				xtype: 'codemirror',
				minHeight: 200,
				name: 'scriptContents',
				hideLabel: true,
				addResizeable: true,
				value: moduleParams['scriptContents']
			}]
		}],

		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-bottom-frame',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'splitbutton',
				text: 'Save',
				hidden: moduleParams['scriptId'] ? false : true,
				handler: function () {
					saveHandler(false);
				},
				menu: [{
					text: 'Save changes as new version (' + (parseInt(moduleParams['latestVersion']) + 1) + ')',
					hidden: moduleParams['scriptId'] ? false : true,
					handler: function () {
						saveHandler(false);
					}
				}, {
					text: 'Save changes as new version (' + (parseInt(moduleParams['latestVersion']) + 1) + ') and execute script',
					hidden: moduleParams['scriptId'] ? false : true,
					handler: function () {
						saveHandler(false, true);
					}
				}, {
					xtype: 'menuseparator'
				}, {
					text: 'Save changes in current version',
					hidden: moduleParams['scriptId'] ? false : true,
					handler: function () {
						saveHandler(true);
					}
				}, {
					text: 'Save changes in current version and execute script',
					hidden: moduleParams['scriptId'] ? false : true,
					handler: function () {
						saveHandler(true, true);
					}
				}, {
					text: 'Create new script',
					hidden: moduleParams['scriptId'] ? true : false,
					handler: function () {
						saveHandler(true);
					}
				}]
			}, {
				xtype: 'button',
				text: 'Create',
				hidden: moduleParams['scriptId'] ? true : false,
				handler: function () {
					saveHandler(true);
				}
			}, {
				xtype: 'button',
				margin: '0 0 0 5',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});

	return form;
});

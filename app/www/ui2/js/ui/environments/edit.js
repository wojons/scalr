Scalr.regPage('Scalr.ui.environments.edit', function (loadParams, moduleParams) {
	var environment = moduleParams['environment'], params = environment['params'];

	var form = Ext.create('Ext.form.Panel', {
		bodyCls: 'x-panel-body-frame',
		width: 720,
		title: 'Environments &raquo; Edit &raquo; ' + environment.name,
		fieldDefaults: {
			anchor: '100%'
		},

		items: [{
			xtype: 'fieldset',
			hidden: !!Scalr.flags.needEnvConfig,
			labelWidth: 100,
			title: 'Date & Time settings',
			items: [{
				xtype: 'combo',
				fieldLabel: 'Timezone',
				store: moduleParams['timezones'],
				allowBlank: false,
				editable: true,
				name: 'timezone',
				value: params['timezone'],
				queryMode: 'local'
			}]
		}, {
			xtype: 'fieldset',
			title: 'Platforms',
			itemId: 'platforms'
		}]
	});

	if (! Scalr.flags.needEnvConfig)
		form.addDocked({
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-bottom-frame',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					if (form.getForm().isValid()) {
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							form: form.getForm(),
							url: '/environments/xSave/',
							params: {
								envId: environment.id
							},
							success: function () {
								Scalr.event.fireEvent('close');
							}
						});
					}
				}
			}, {
				xtype: 'button',
				margin: '0 0 0 5',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		});

	for (var i in moduleParams['platforms']) {
		var disabled = environment['enabledPlatforms'].indexOf(i) == -1;
		form.down('#platforms').add({
			xtype: 'custombutton',
			width: 109,
			height: 112,
			cls: 'scalr-ui-environment-edit-btn',
			childEls: [ 'icon' ],
			renderTpl:
				'<div class="{prefix}-btn-custom" id="{id}-btnEl">' +
					'<div id="{id}-icon" class="{prefix}-btn-icon"><img src="{icon}"></div>' +
					'<div class="{prefix}-btn-name">{name}</div>' +
				'</div>',
			renderData: {
				prefix: 'scalr-ui-environment-edit',
				name: moduleParams['platforms'][i],
				icon: '/ui2/images/icons/platform/' + i + (disabled ? '_disabled' : '') + '_89x64.png'
			},
			platform: i,
			handler: function () {
				Scalr.event.fireEvent('redirect', '#/environments/' + environment.id + '/platform/' + this.platform, true);
			}
		});
	};

	Scalr.event.on('update', function (type, platform, enabled) {
		if (type == '/environments/' + environment.id + '/edit') {
			var b = form.down('#platforms').down('[platform="' + platform + '"]');
			if (b) {
				b.icon.update('<img src="' + '/ui2/images/icons/platform/' + platform + (enabled ? '' : '_disabled') + '_89x64.png' + '" />');
			}
		}
	}, form);

	return form;
});

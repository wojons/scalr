Scalr.regPage('Scalr.ui.farms.builder.tabs.devel', function () {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Development options',
        itemId: 'devel',
        
        settings: {
            'user-data.scm_branch': 'master',
            'user-data.szr_version': ''
        },
        
		isEnabled: function (record) {
			return Scalr.flags['betaMode'];
		},

		showTab: function (record) {
			var settings = record.get('settings');

			this.down('[name="user-data.scm_branch"]').setValue(settings['user-data.scm_branch'] || 'master');
			this.down('[name="user-data.szr_version"]').setValue(settings['user-data.szr_version'] || '');
		},

		hideTab: function (record) {
			var settings = record.get('settings');
			settings['user-data.scm_branch'] = this.down('[name="user-data.scm_branch"]').getValue();
			settings['user-data.szr_version'] = this.down('[name="user-data.szr_version"]').getValue();
			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            defaults: {
                maxWidth: 600,
				anchor: '100%',
				labelWidth: 110
            },
			items: [{
				xtype: 'textfield',
				fieldLabel: 'SCM Branch',
				name: 'user-data.scm_branch'
			}, {
				xtype: 'textfield',
				fieldLabel: 'Scalarizr version',
				name: 'user-data.szr_version'
			}]
		}]
	});
});

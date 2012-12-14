Scalr.regPage('Scalr.ui.farms.builder.tabs.devel', function () {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Development options',
		cache: {},

		isEnabled: function (record) {
			
			var pageParameters = Ext.urlDecode(window.location.search.substring(1));
			return (pageParameters['devel'] == 1);
		},

		getDefaultValues: function (record) {
			return {
				'user-data.scm_branch': 'trunk',
				'user-data.szr_version': ''
			};
		},

		showTab: function (record) {
			var settings = record.get('settings');

			this.down('[name="user-data.scm_branch"]').setValue(settings['user-data.scm_branch'] || 'trunk');
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
			items: [{
				xtype: 'textfield',
				anchor: '100%',
				labelWidth: 200,
				fieldLabel: 'SCM Branch',
				name: 'user-data.scm_branch'
			}, {
				xtype: 'textfield',
				anchor: '100%',
				labelWidth: 200,
				fieldLabel: 'Scalarizr version',
				name: 'user-data.szr_version'
			}]
		}]
	});
});

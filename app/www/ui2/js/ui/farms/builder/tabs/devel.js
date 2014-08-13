Scalr.regPage('Scalr.ui.farms.builder.tabs.devel', function () {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Development',
        itemId: 'devel',
        
        settings: {
            'user-data.scm_branch': '',
            'user-data.szr_version': '',
            'base.custom_user_data': '',
            'openstack.boot_from_volume': 0
        },
        
		isEnabled: function (record) {
			return Scalr.flags['betaMode'];
		},

		showTab: function (record) {
			var settings = record.get('settings');

			this.down('[name="user-data.scm_branch"]').setValue(settings['user-data.scm_branch'] || 'master');
			this.down('[name="user-data.szr_version"]').setValue(settings['user-data.szr_version'] || '');
			this.down('[name="base.custom_user_data"]').setValue(settings['base.custom_user_data'] || '');
			this.down('[name="openstack.boot_from_volume"]').setValue(settings['openstack.boot_from_volume'] || 0);

            this.down('[name="user-data.enabled"]')[settings['user-data.scm_branch'] || settings['user-data.szr_version'] ? 'expand' : 'collapse']();
		},

		hideTab: function (record) {
			var settings = record.get('settings'),
                userDataEnabled = !this.down('[name="user-data.enabled"]').collapsed;
        
			settings['user-data.scm_branch'] = userDataEnabled ? this.down('[name="user-data.scm_branch"]').getValue() : '';
			settings['user-data.szr_version'] = userDataEnabled ? this.down('[name="user-data.szr_version"]').getValue() : '';
			
			settings['base.custom_user_data'] = this.down('[name="base.custom_user_data"]').getValue();
			settings['openstack.boot_from_volume'] = this.down('[name="openstack.boot_from_volume"]').getValue();
			
			
			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
            title: ' Scalarizr branch & version',
            name: 'user-data.enabled',
            checkboxToggle: true,
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
		},{
			xtype: 'checkbox',
			name: 'openstack.boot_from_volume',
			boxLabel: 'Use cinder volume as root device'
        }, {
			xtype: 'fieldset',
            itemId: 'additionaltags',
            title: 'Custom user-data',
            collapsible: true,
            collapsed: true,
            toggleOnTitleClick: true,
			items: [{
				xtype: 'textarea',
				width: 600,
	            height: 200,
				name: 'base.custom_user_data',
				icons: {
	                globalvars: true
	            }
			}]
		}]
	});
});

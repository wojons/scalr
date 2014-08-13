Scalr.regPage('Scalr.ui.farms.builder.tabs.gce', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'GCE settings',
        itemId: 'gce',
        layout: 'anchor',
        
        settings: {
            'gce.on-host-maintenance': undefined
        },
        
		isEnabled: function (record) {
			return record.get('platform') == 'gce';
		},

		showTab: function (record) {
			var settings = record.get('settings', true);
			this.down('[name="gce.on-host-maintenance"]').setValue(settings['gce.on-host-maintenance'] || 'TERMINATE');
		},

		hideTab: function (record) {
			var settings = record.get('settings');

			settings['gce.on-host-maintenance'] = this.down('[name="gce.on-host-maintenance"]').getValue();
			
			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
            layout: 'anchor',
            defaults: {
                anchor: '100%',
                maxWidth: 600,
                labelWidth: 140
            },
			items: [{
				xtype: 'combo',
				store: [['TERMINATE', 'TERMINATE'], ['MIGRATE', 'MIGRATE']],
				valueField: 'name',
				displayField: 'description',
				fieldLabel: 'Maintenance behavior',
				editable: false,
				queryMode: 'local',
				name: 'gce.on-host-maintenance'
			}]
		}]
	});
});

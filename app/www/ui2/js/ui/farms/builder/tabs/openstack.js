Scalr.regPage('Scalr.ui.farms.builder.tabs.openstack', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Placement and type',
		cache: {},

		isEnabled: function (record) {
			return record.get('platform') == 'openstack';
		},

		getDefaultValues: function (record) {
			return {
				'openstack.flavor-id': 1
			};
		},

		beforeShowTab: function (record, handler) {
			var cloudLocation = record.get('cloud_location');

			if (this.cacheExist(['flavorsOpenstack', cloudLocation]))
				handler();
			else
				Scalr.Request({
					processBox: {
						type: 'action'
					},
					url: '/platforms/openstack/xGetFlavors',
					params: {
						cloudLocation: cloudLocation
					},
					scope: this,
					success: function (response) {
						this.cacheSet(response.data, ['flavorsOpenstack', cloudLocation]);
						handler();
					},
					failure: function () {
						this.deactivateTab();
					}
				});
		},

		showTab: function (record) {
			var settings = record.get('settings');

			this.down('[name="openstack.flavor-id"]').store.load({ data: this.cacheGet(['flavorsOpenstack', record.get('cloud_location')]) });
			this.down('[name="openstack.flavor-id"]').setValue(parseInt(settings['openstack.flavor-id']) || 1);
		},

		hideTab: function (record) {
			var settings = record.get('settings');

			settings['openstack.flavor-id'] = this.down('[name="openstack.flavor-id"]').getValue();

			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
			items: [{
				xtype: 'combo',
				store: {
					fields: [ 'id', 'name' ],
					proxy: 'object'
				},
				valueField: 'id',
				displayField: 'name',
				fieldLabel: 'Flavor',
				editable: false,
				queryMode: 'local',
				name: 'openstack.flavor-id',
				width: 300
			}]
		}]
	});
});

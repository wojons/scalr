Scalr.regPage('Scalr.ui.farms.builder.tabs.gce', function (moduleTabParams) {
	
	var gceSettings = new Array();
	
	var form = Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'GoogleCE options',
		cache: {},

		isEnabled: function (record) {
			return record.get('platform') == 'gce';
		},

		getDefaultValues: function (record) {
			return {
				'gce.machine-type': 'n1-standard-1',
				'gce.network': 'default'
			};
		},

		beforeShowTab: function (record, handler) {
			Scalr.Request({
				processBox: {
					type: 'action'
				},
				url: '/platforms/gce/xGetOptions',
				scope: this,
				success: function (response) {
					gceSettings = response.data;
					handler();
				},
				failure: function () {
					this.deactivateTab();
				}
			});
		},

		showTab: function (record) {
			var settings = record.get('settings');

			this.down('[name="gce.machine-type"]').store.load({ data: gceSettings['types'] });
			this.down('[name="gce.machine-type"]').setValue(settings['gce.machine-type'] || 'n1-standard-1');
			
			this.down('[name="gce.network"]').store.load({ data: gceSettings['networks'] });
			this.down('[name="gce.network"]').setValue(settings['gce.network'] || 'default');
			
			
			var cLoc = this.down('[name="gce.cloud-location"]');
			cLoc.store.load({ data: gceSettings['zones'] });
			try {
				var defVal = cLoc.store.getAt(0).get('name');
			} catch (e){}
			
			this.down('[name="gce.cloud-location"]').setValue(settings['gce.cloud-location'] || defVal);
		},

		hideTab: function (record) {
			var settings = record.get('settings');

			settings['gce.machine-type'] = this.down('[name="gce.machine-type"]').getValue();
			settings['gce.network'] = this.down('[name="gce.network"]').getValue();
			settings['gce.cloud-location'] = this.down('[name="gce.cloud-location"]').getValue();

			record.set('cloud_location', settings['gce.cloud-location']);
			
			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
			items: [{
				xtype: 'combo',
				store: {
					fields: [ 'name', 'description' ],
					proxy: 'object'
				},
				valueField: 'name',
				displayField: 'description',
				fieldLabel: 'Cloud location',
				editable: false,
				queryMode: 'local',
				name: 'gce.cloud-location',
				width: 750,
				listeners: {
					change: function(field, value) {
						this.up('[tab="tab"]').currentRole.set('cloud_location', value);
					}
				}
			}, {
				xtype: 'combo',
				store: {
					fields: [ 'name', 'description' ],
					proxy: 'object'
				},
				valueField: 'name',
				displayField: 'description',
				fieldLabel: 'Machine type',
				editable: false,
				queryMode: 'local',
				name: 'gce.machine-type',
				width: 750
			}, {
				xtype: 'combo',
				store: {
					fields: [ 'name', 'description' ],
					proxy: 'object'
				},
				valueField: 'name',
				displayField: 'description',
				fieldLabel: 'Network',
				editable: false,
				queryMode: 'local',
				name: 'gce.network',
				width: 750
			}]
		}]
	});
	
	return form;
});

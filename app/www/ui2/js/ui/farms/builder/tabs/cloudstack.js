Scalr.regPage('Scalr.ui.farms.builder.tabs.cloudstack', function () {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Cloudstack settings',
		cache: {},

		isEnabled: function (record) {
			return record.get('platform') == 'cloudstack' || record.get('platform') == 'idcf' || record.get('platform') == 'ucloud';
		},

		getDefaultValues: function (record) {
			return {
				'cloudstack.service_offering_id': '',
				'cloudstack.network_id': '',
				'cloudstack.disk_offering_id': ''
			};
		},

		beforeShowTab: function (record, handler) {
			var cloudLocation = record.get('cloud_location');

			if (this.cacheExist('serviceOfferings'))
				handler();
			else
				Scalr.Request({
					processBox: {
						type: 'action'
					},
					url: '/platforms/cloudstack/xGetOfferingsList/',
					scope: this,
					params: {
						cloudLocation: cloudLocation,
						platform: record.get('platform')
					},
					success: function (response) {
						this.cacheSet(response.data['serviceOfferings'], 'cloudstack.serviceOfferings');
						this.cacheSet(response.data['networks'], 'cloudstack.networks');
						this.cacheSet(response.data['diskOfferings'], 'cloudstack.diskOfferings');
						this.cacheSet(response.data['ipAddresses'], 'cloudstack.ipAddresses');
						handler();
					},
					failure: function () {
						this.deactivateTab();
					}
				});
		},


		showTab: function (record) {
			var settings = record.get('settings');

			var sOid = this.down('[name="cloudstack.service_offering_id"]');
			sOid.store.load({ data: this.cacheGet('cloudstack.serviceOfferings') });
			try {
				var defVal = sOid.store.getAt(0).get('id');
			} catch (e) {}			
			sOid.setValue(settings['cloudstack.service_offering_id'] || defVal);
			
			
			var nId = this.down('[name="cloudstack.network_id"]');
			nId.store.load({ data: this.cacheGet('cloudstack.networks') });
			if (nId.store.getCount() == 0) {
				nId.hide();
			} else {
				nId.show();
				try {
					var defVal = nId.store.getAt(0).get('id');
				} catch (e) {}
				nId.setValue(settings['cloudstack.network_id'] || defVal);
			}
			
			var dId = this.down('[name="cloudstack.disk_offering_id"]');
			dId.store.load({ data: this.cacheGet('cloudstack.diskOfferings') });
			if (dId.store.getCount() == 0) {
				dId.hide();
			} else {
				dId.show();
				try {
					var defVal = dId.store.getAt(0).get('id');
				} catch (e) {}
				dId.setValue(settings['cloudstack.disk_offering_id'] || defVal);
			}
			
			var ipId = this.down('[name="cloudstack.shared_ip.id"]');
			ipId.store.load({ data: this.cacheGet('cloudstack.ipAddresses') });
			if (ipId.store.getCount() == 0) {
				ipId.hide();
			} else {
				ipId.show();
				try {
					var defVal = ipId.store.getAt(0).get('id');
				} catch (e) {}
				ipId.setValue(settings['cloudstack.shared_ip.id'] || defVal);
			}
		},

		hideTab: function (record) {
			var settings = record.get('settings');
			settings['cloudstack.service_offering_id'] = this.down('[name="cloudstack.service_offering_id"]').getValue();
			settings['cloudstack.disk_offering_id'] = this.down('[name="cloudstack.disk_offering_id"]').getValue();
			settings['cloudstack.network_id'] = this.down('[name="cloudstack.network_id"]').getValue();
			
			settings['cloudstack.shared_ip.id'] = this.down('[name="cloudstack.shared_ip.id"]').getValue();
			if (settings['cloudstack.shared_ip.id']) {
				var r = this.down('[name="cloudstack.shared_ip.id"]').findRecordByValue(settings['cloudstack.shared_ip.id']);
				settings['cloudstack.shared_ip.address'] = r.get('name');
			}
			
			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
			defaults: {
				labelWidth: 150,
				width: 500
			},
			items: [{
				xtype: 'combo',
				store: {
					fields: [ 'id', 'name' ],
					proxy: 'object'
				},
				matchFieldWidth: false,
				listConfig: {
					width: 'auto',
					minWidth: 350
				},
				valueField: 'id',
				displayField: 'name',
				fieldLabel: 'Service offering',
				editable: false,
				queryMode: 'local',
				name: 'cloudstack.service_offering_id'
			}, {
				xtype: 'combo',
				store: {
					fields: [ 'id', 'name' ],
					proxy: 'object'
				},
				matchFieldWidth: false,
				listConfig: {
					width: 'auto',
					minWidth: 350
				},
				valueField: 'id',
				displayField: 'name',
				fieldLabel: 'Disk offering',
				editable: false,
				queryMode: 'local',
				name: 'cloudstack.disk_offering_id'
			}, {
				xtype: 'combo',
				store: {
					fields: [ 'id', 'name' ],
					proxy: 'object'
				},
				matchFieldWidth: false,
				listConfig: {
					width: 'auto',
					minWidth: 350
				},
				valueField: 'id',
				displayField: 'name',
				fieldLabel: 'Network',
				editable: false,
				queryMode: 'local',
				name: 'cloudstack.network_id'
			}, {
				xtype: 'combo',
				store: {
					fields: [ 'id', 'name' ],
					proxy: 'object'
				},
				matchFieldWidth: false,
				listConfig: {
					width: 'auto',
					minWidth: 350
				},
				valueField: 'id',
				displayField: 'name',
				fieldLabel: 'Shared IP',
				editable: false,
				queryMode: 'local',
				name: 'cloudstack.shared_ip.id'
			}]
		}]
	});
});

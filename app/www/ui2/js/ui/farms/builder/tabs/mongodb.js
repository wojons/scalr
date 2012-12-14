Scalr.regPage('Scalr.ui.farms.builder.tabs.mongodb', function () {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'MongoDB settings',
		itemId: 'mongodb',
		cache: {},
		isEnabled: function (record) {
			return record.get('behaviors').match('mongodb');
		},

		getDefaultValues: function (record) {
			if (record.get('platform') == 'ec2') 
				var default_storage_engine = 'ebs';
			else if (record.get('platform') == 'rackspace') 
				var default_storage_engine = 'eph';
			else if (record.get('platform') == 'cloudstack') 
				var default_storage_engine = 'csvol';
			
			
			return {
				'mongodb.data_storage.engine': default_storage_engine,
				'mongodb.data_storage.ebs.size': 10
			};
		},

		showTab: function (record) {
			var settings = record.get('settings');
			
			if (record.get('platform') == 'ec2') {
				this.down('[name="mongodb.data_storage.engine"]').store.load({
					data: [{name:'ebs', description:'EBS Volume'}, {name:'raid.ebs', description:'RAID array'}]
				});
			}
			else if (record.get('platform') == 'rackspace') {
				this.down('[name="mongodb.data_storage.engine"]').store.load({
					data: ['eph']
				});
			}
			else if (record.get('platform') == 'cloudstack') {
				this.down('[name="mongodb.data_storage.engine"]').store.load({
					data: ['csvol']
				});
			}
			
			if (settings['mongodb.data_storage.engine'] == 'ebs' || settings['mongodb.data_storage.engine'] == 'csvol') {
				this.down('[name="mongodb.data_storage.ebs.size"]').setValue(settings['mongodb.data_storage.ebs.size']);
			}

			if (record.get('new')) {
				this.down('[name="mongodb.data_storage.raid.level"]').enable()
				this.down('[name="mongodb.data_storage.raid.volumes_count"]').enable();
				this.down('[name="mongodb.data_storage.raid.volume_size"]').enable();
				
				this.down('[name="mongodb.data_storage.engine"]').enable();
				this.down('[name="mongodb.data_storage.ebs.size"]').enable();
			}
			else {
				this.down('[name="mongodb.data_storage.raid.level"]').disable()
				this.down('[name="mongodb.data_storage.raid.volumes_count"]').disable();
				this.down('[name="mongodb.data_storage.raid.volume_size"]').disable();
				
				this.down('[name="mongodb.data_storage.engine"]').disable();
				this.down('[name="mongodb.data_storage.ebs.size"]').disable();
			}
			
			this.down('[name="mongodb.data_storage.engine"]').setValue(settings['mongodb.data_storage.engine']);
			
			var raidType = this.down('[name="mongodb.data_storage.raid.level"]');
			raidType.store.load({
				data: [
					{name:'0', description:'RAID 0 (block-level striping without parity or mirroring)'},
					{name:'1', description:'RAID 1 (mirroring without parity or striping)'},
					{name:'5', description:'RAID 5 (block-level striping with distributed parity)'},
					{name:'10', description:'RAID 10 (mirrored sets in a striped set)'}
				]
			});
			raidType.setValue(settings['mongodb.data_storage.raid.level'] || '10');
			
			this.down('[name="mongodb.data_storage.raid.volumes_count"]').setValue(settings['mongodb.data_storage.raid.volumes_count'] || 4);
			this.down('[name="mongodb.data_storage.raid.volume_size"]').setValue(settings['mongodb.data_storage.raid.volume_size'] || 100);
		},

		hideTab: function (record) {
			var settings = record.get('settings');

			settings['mongodb.data_storage.engine'] = this.down('[name="mongodb.data_storage.engine"]').getValue();
			
			if (settings['mongodb.data_storage.engine'] == 'ebs' || settings['mongodb.data_storage.engine'] == 'csvol') {
				if (record.get('new')) 
					settings['mongodb.data_storage.ebs.size'] = this.down('[name="mongodb.data_storage.ebs.size"]').getValue();
			}
			else {
				delete settings['mongodb.data_storage.ebs.size'];
			}

			if (settings['mongodb.data_storage.engine'] == 'raid.ebs') {				
				settings['mongodb.data_storage.raid.level'] = this.down('[name="mongodb.data_storage.raid.level"]').getValue();
				settings['mongodb.data_storage.raid.volume_size'] = this.down('[name="mongodb.data_storage.raid.volume_size"]').getValue();
				settings['mongodb.data_storage.raid.volumes_count'] = this.down('[name="mongodb.data_storage.raid.volumes_count"]').getValue();
			}
			
			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
			title: 'MongoDB data storage settings',
			items: [{
				xtype: 'combo',
				name: 'mongodb.data_storage.engine',
				fieldLabel: 'Storage engine',
				editable: false,
				store: {
					fields: [ 'description', 'name' ],
					proxy: 'object'
				},
				valueField: 'name',
				displayField: 'description',
				width: 400,
				labelWidth: 160,
				queryMode: 'local',
				listeners: {
					change: function(){
						this.up('#mongodb').down('[name="ebs_settings"]').hide();
						this.up('#mongodb').down('[name="raid_settings"]').hide();
						
						if (this.getValue() == 'ebs' || this.getValue() == 'csvol') {
							this.up('#mongodb').down('[name="ebs_settings"]').show();
						}
						
						if (this.getValue() == 'raid.ebs') {
							this.up('#mongodb').down('[name="raid_settings"]').show();
						}
					}
				}
			}]
		}, {
			xtype: 'fieldset',
			name: 'ebs_settings',
			title: 'Block Storage settings',
			hidden: true,
			items: [{
				xtype: 'textfield',
				fieldLabel: 'Storage size (10-1000 GB)',
				labelWidth: 160,
				width: 200,
				name: 'mongodb.data_storage.ebs.size',
				validator: function(value) {
					if (parseInt(value) < 10 || parseInt(value) > 1000)
						return "EBS size should be from 10 GB to 1000 GB";
					
					return true;
				}
			}]
		}, {
			xtype:'fieldset',
			name: 'raid_settings',
			title: 'RAID storage settings',
			hidden: true,
			items: [{ 
				xtype: 'combo',
				name: 'mongodb.data_storage.raid.level',
				fieldLabel: 'RAID level',
				editable: false,
				store: {
					fields: [ 'name', 'description' ],
					proxy: 'object'
				},
				valueField: 'name',
				displayField: 'description',
				width: 500,
				value: '',
				labelWidth: 160,
				queryMode: 'local',
				listeners:{
					change:function() {
						try {
							var data = [];
							if (this.getValue() == '0') {
								data = {'2':'2', '3':'3', '4':'4', '5':'5', '6':'6', '7':'7', '8':'8'};
							} else if (this.getValue() == '1') {
								data = {'2':'2'};
							} else if (this.getValue() == '5') {
								data = {'3':'3', '4':'4', '5':'5', '6':'6', '7':'7', '8':'8'};
							} else if (this.getValue() == '10') {
								data = {'4':'4', '6':'6', '8':'8'};
							}
							
							var obj = this.up('#mongodb').down('[name="mongodb.data_storage.raid.volumes_count"]');
							obj.store.load({data: data});
							var val = obj.store.getAt(0).get('id');
							obj.setValue(val);
						} catch (e) {}
					}
				}
			}, {
				xtype: 'combo',
				name: 'mongodb.data_storage.raid.volumes_count',
				fieldLabel: 'Number of volumes',
				editable: false,
				store: {
					fields: [ 'id', 'name'],
					proxy: 'object'
				},
				valueField: 'id',
				displayField: 'name',
				width: 500,
				labelWidth: 160,
				queryMode: 'local'
			}, {
				xtype: 'textfield',
				fieldLabel: 'Each volume size',
				labelWidth: 160,
				width: 200,
				value: '10',
				name: 'mongodb.data_storage.raid.volume_size'
			}/*, {
				xtype: 'fieldcontainer',
				layout:'hbox',
				hideLabel: true,
				items:[ {
						xtype:"displayfield",
						hideLabel: true,
						value:"Available space on the raid: "
					}, {
						xtype:"displayfield",
						hideLabel: true,
						style:{fontWeight:'bold'},
						value:"",
						margin: '0 0 0 3'
					}, {
						xtype:"displayfield",
						hideLabel: true,
						value:" GB",
						margin: '0 0 0 3'
					}
				]
			}*/]
		}]
	});
});

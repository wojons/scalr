Scalr.regPage('Scalr.ui.farms.builder.tabs.dbmsr', function (moduleTabParams) {
	
	var pageParameters = Ext.urlDecode(window.location.search.substring(1));
	
	//TODO: Move to JSON
	var ephemeralDevicesMap = new Array();
	ephemeralDevicesMap['m1.small'] =  {'ephemeral0':{'size': 150}}
	ephemeralDevicesMap['m1.medium'] = {'ephemeral0':{'size': 400}}
	ephemeralDevicesMap['m1.large'] =  {'ephemeral0':{'size': 420}, 'ephemeral1':{'size': 420}};
	ephemeralDevicesMap['m1.xlarge'] = {'ephemeral0':{'size': 420}, 'ephemeral1':{'size': 420}, 'ephemeral2':{'size': 420}, 'ephemeral3':{'size': 420}};
	ephemeralDevicesMap['c1.medium'] = {'ephemeral0':{'size': 340}}
	ephemeralDevicesMap['c1.xlarge'] = {'ephemeral0':{'size': 420}, 'ephemeral1':{'size': 420}, 'ephemeral2':{'size': 420}, 'ephemeral3':{'size': 420}};
	ephemeralDevicesMap['m2.xlarge'] = {'ephemeral0':{'size': 410}};
	ephemeralDevicesMap['m2.2xlarge'] = {'ephemeral0':{'size': 840}};
	ephemeralDevicesMap['m2.4xlarge'] =  {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}};
	ephemeralDevicesMap['hi1.4xlarge'] =  {'ephemeral0':{'size': 1000}, 'ephemeral1':{'size': 1000}};
	ephemeralDevicesMap['cc1.4xlarge'] =  {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}};
	ephemeralDevicesMap['cc2.8xlarge'] =  {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}, 'ephemeral2':{'size': 840}, 'ephemeral3':{'size': 840}};
	ephemeralDevicesMap['cg1.4xlarge'] =  {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}};
	
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Database settings',
		itemId: 'dbmsr',
		cache: {},

		isEnabled: function (record) {
			return ((record.get('behaviors').match('percona') || record.get('behaviors').match('postgresql') || record.get('behaviors').match('mysql2') || record.get('behaviors').match('redis')) &&
				(
					record.get('platform') == 'ec2' ||
					record.get('platform') == 'rackspace' ||
					record.get('platform') == 'cloudstack' ||
					record.get('platform') == 'gce' ||
					record.get('platform') == 'idcf' ||
					record.get('platform') == 'ucloud' 
				)
			);
		},

		getDefaultValues: function (record) {
			if (record.get('platform') == 'ec2')
				var default_storage_engine = 'ebs';
			else if (record.get('platform') == 'rackspace' || record.get('platform') == 'gce')
				var default_storage_engine = 'eph';
			else if (record.get('platform') == 'cloudstack' || record.get('platform') == 'idcf' || record.get('platform') == 'ucloud')
				var default_storage_engine = 'csvol';

			return {
				'db.msr.data_bundle.enabled': 1,
				'db.msr.data_bundle.every': 24,
				'db.msr.data_bundle.timeframe.start_hh': '05',
				'db.msr.data_bundle.timeframe.start_mm': '00',
				'db.msr.data_bundle.timeframe.end_hh': '09',
				'db.msr.data_bundle.timeframe.end_mm': '00',
				
				'db.msr.data_storage.engine': default_storage_engine,
				'db.msr.data_storage.ebs.size': 10,
				'db.msr.data_storage.ebs.snaps.enable_rotation' : 1,
				'db.msr.data_storage.ebs.snaps.rotate' : 5,
				
				'db.msr.data_backup.enabled': 1,
				'db.msr.data_backup.every' : 48,
				'db.msr.data_backup.timeframe.start_hh': '05',
				'db.msr.data_backup.timeframe.start_mm': '00',
				'db.msr.data_backup.timeframe.end_hh': '09',
				'db.msr.data_backup.timeframe.end_mm': '00'
			};
		},
		
		showTab: function (record) {
			
			var settings = record.get('settings');
			
			if (record.get('platform') == 'ec2') {
				
				var storages = [{name:'ebs', description:'Single EBS Volume'}, {name:'raid.ebs', description:'RAID array on EBS volumes'}]
				
				/*
				if (record.get('behaviors').match('percona') || record.get('behaviors').match('mysql2')) {
					
					if (settings['db.msr.data_storage.engine'] == 'eph')
						storages[storages.length] = {name:'eph', description:'Single ephemeral device'};

					if (Ext.isDefined(ephemeralDevicesMap[settings['aws.instance_type']]))
						storages[storages.length] = {name:'lvm', description:'LVM on ephemeral devices'};
						
				} else {
					storages[storages.length] = {name:'eph', description:'Single ephemeral device'};
				}
				*/
				
				if (settings['db.msr.data_storage.engine'] == 'lvm')
					storages[storages.length] = {name:'lvm', description:'LVM on ephemeral devices'};
				
				storages[storages.length] = {name:'eph', description:'Single ephemeral device'};
				
				
				this.down('[name="db.msr.data_storage.engine"]').store.load({
					data: storages
				});
				
			} else if (record.get('platform') == 'rackspace' || record.get('platform') == 'gce') {
				this.down('[name="db.msr.data_storage.engine"]').store.load({
					data: [{name:'eph', description:'Ephemeral device'}]
				});
			} else if (record.get('platform') == 'cloudstack' || record.get('platform') == 'idcf' || record.get('platform') == 'ucloud') {
				this.down('[name="db.msr.data_storage.engine"]').store.load({
					data: [{name:'csvol', description:'CloudStack Block Volume'}]
				});
				
				this.down('[name="db.msr.data_backup.enabled"]').collapse();
				this.down('[name="db.msr.data_backup.enabled"]').hide();
			}
			
			// Fily systems:
			var fsystems = new Array();
			fsystems[0] = {'fs':'ext3', 'description':'Ext3'};
			if ((record.get('image_os_family') == 'centos' && record.get('arch') == 'x86_64') ||
				(record.get('image_os_family') == 'ubuntu' && (record.get('image_os_version') == '10.04' || record.get('image_os_version') == '12.04'))
			) {
				if (moduleTabParams['featureMFS']) {
					fsystems[1] = {'fs':'ext4', 'description':'Ext4'};
					fsystems[2] = {'fs':'xfs', 'description':'XFS'};
				} else {
					fsystems[1] = {'fs':'ext4', 'description':'Ext4 (Not available for your pricing plan)'};
					fsystems[2] = {'fs':'xfs', 'description':'XFS (Not available for your pricing plan)'};
				}
			}
			
			this.down('[name="db.msr.data_storage.fstype"]').store.load({data: fsystems});
			this.down('[name="db.msr.data_storage.fstype"]').setValue(settings['db.msr.data_storage.fstype'] || 'ext3');
			
			// Ephemeral devices
			
			var availableDisks = new Array();
			availableDisks[0] = {'device':'', 'description':''};
			
			if (record.get('platform') == 'rackspace')
				availableDisks[availableDisks.length] = {'device':'/dev/loop0', 'description':'Loop device (75% from /)'};
			else if (record.get('platform') == 'gce') {
				availableDisks[availableDisks.length] = {'device':'ephemeral-disk-0', 'description':'Loop device (80% of ephemeral-disk-0)'};
			}
			else if (record.get('platform') == 'ec2') {
				
				var devices = [];
				devices['/dev/sda2'] ={'m1.small':1, 'c1.medium':1};
				devices['/dev/sdb'] = {'m1.medium':1, 'm1.large':1, 'm1.xlarge':1, 'c1.xlarge':1, 'cc1.4xlarge':1, 'cc2.8xlarge':1, 'm2.xlarge':1, 'm2.2xlarge':1, 'm2.4xlarge':1};
				devices['/dev/sdc'] = {               'm1.large':1, 'm1.xlarge':1, 'c1.xlarge':1, 'cc1.4xlarge':1, 'cc2.8xlarge':1};
				devices['/dev/sdd'] = {						 		'm1.xlarge':1, 'c1.xlarge':1, 			   	   'cc2.8xlarge':1 };
				devices['/dev/sde'] = {						 		'm1.xlarge':1, 'c1.xlarge':1, 			       'cc2.8xlarge':1 };
				
				devices['/dev/sdf'] = {'hi1.4xlarge':1 };
				devices['/dev/sdg'] = {'hi1.4xlarge':1 };
				
				for (var deviceName in devices) {
					if (devices[deviceName][settings['aws.instance_type']] == 1) {
						availableDisks[availableDisks.length] = {'device':deviceName, 'description':'LVM on '+deviceName+' (80% available for data)'};
					}
				}
			}

			if (settings['db.msr.data_storage.engine'] == 'lvm')
				this.down('[name="lvm_settings"]').show();
			else
				this.down('[name="lvm_settings"]').hide();

			// prepare ephemeral devices checkbox's
			if (Ext.isDefined(ephemeralDevicesMap[settings['aws.instance_type']])) {
				var cont = this.down('[name="lvm_settings"]'), devices = ephemeralDevicesMap[settings['aws.instance_type']], size = 0,
					volumes = Ext.decode(settings['db.msr.storage.lvm.volumes']), def = Ext.Object.getSize(volumes) ? false : true;
				cont.suspendLayouts();
				cont.removeAll();

				for (var d in devices) {
					cont.add({
						xtype: 'checkbox',
						name: d,
						boxLabel: d + ' (' + devices[d]['size'] + 'Gb)',
						ephSize: devices[d]['size'],
						checked: def || Ext.isDefined(volumes[d]),
						handler: function() {
							var c = this.up('fieldset'), s = 0;
							Ext.each(c.query('checkbox'), function() {
								if (this.getValue())
									s += parseInt(this.ephSize);
							});

							c.down('displayfield').setValue(s + 'Gb');
						}
					});
					size += parseInt(devices[d]['size']);
				}

				cont.add({
					xtype: 'displayfield',
					fieldLabel: 'Total size',
					labelWidth: 80,
					value: size + 'Gb'
				});

				cont.resumeLayouts(true);
				if (! record.get('new'))
					cont.disable();
			}
			
			this.down('[name="db.msr.data_storage.eph.disk"]').store.load({data: availableDisks});
			
			//TODO: Select first device
			
			if (record.get('behaviors').match('redis')) {
				this.down('[name="db.msr.redis.persistence_type"]').store.load({
					data: [
						{name:'aof', description:'Append Only File'},
						{name:'snapshotting', description:'Snapshotting'}
					]
				});
				
				this.down('[name="db.msr.redis.persistence_type"]').setValue(settings['db.msr.redis.persistence_type'] || 'snapshotting');
				this.down('[name="db.msr.redis.use_password"]').setValue(settings['db.msr.redis.use_password'] || 1);
				this.down('[name="db.msr.redis.num_processes"]').setValue(settings['db.msr.redis.num_processes'] || 1);
				
				this.down('[name="redis_settings"]').show();
			} else {
				this.down('[name="redis_settings"]').hide();
			}
			
			var raidType = this.down('[name="db.msr.data_storage.raid.level"]');
			raidType.store.load({
				data: [
					{name:'0', description:'RAID 0 (block-level striping without parity or mirroring)'},
					{name:'1', description:'RAID 1 (mirroring without parity or striping)'},
					{name:'5', description:'RAID 5 (block-level striping with distributed parity)'},
					{name:'10', description:'RAID 10 (mirrored sets in a striped set)'}
				]
			});
			raidType.setValue(settings['db.msr.data_storage.raid.level'] || '10');
			
			this.down('[name="db.msr.data_storage.raid.ebs.type"]').setValue(settings['db.msr.data_storage.raid.ebs.type'] || 'standard');
			this.down('[name="db.msr.data_storage.raid.ebs.iops"]').setValue(settings['db.msr.data_storage.raid.ebs.iops'] || 50);
			this.down('[name="db.msr.data_storage.raid.volumes_count"]').setValue(settings['db.msr.data_storage.raid.volumes_count'] || 4);
			this.down('[name="db.msr.data_storage.raid.volume_size"]').setValue(settings['db.msr.data_storage.raid.volume_size'] || 10);

			if (settings['db.msr.data_bundle.enabled'] == 1)
				this.down('[name="db.msr.data_bundle.enabled"]').expand();
			else
				this.down('[name="db.msr.data_bundle.enabled"]').collapse();

			this.down('[name="db.msr.data_bundle.every"]').setValue(settings['db.msr.data_bundle.every']);
			
			this.down('[name="db.msr.data_bundle.use_slave"]').setValue(settings['db.msr.data_bundle.use_slave'] || 0);
			
			this.down('[name="db.msr.data_bundle.timeframe.start_hh"]').setValue(settings['db.msr.data_bundle.timeframe.start_hh']);
			this.down('[name="db.msr.data_bundle.timeframe.start_mm"]').setValue(settings['db.msr.data_bundle.timeframe.start_mm']);
			this.down('[name="db.msr.data_bundle.timeframe.end_hh"]').setValue(settings['db.msr.data_bundle.timeframe.end_hh']);
			this.down('[name="db.msr.data_bundle.timeframe.end_mm"]').setValue(settings['db.msr.data_bundle.timeframe.end_mm']);

			if (settings['db.msr.data_backup.enabled'] == 1)
				this.down('[name="db.msr.data_backup.enabled"]').expand();
			else
				this.down('[name="db.msr.data_backup.enabled"]').collapse();

			this.down('[name="db.msr.data_backup.every"]').setValue(settings['db.msr.data_backup.every']);
			this.down('[name="db.msr.data_backup.timeframe.start_hh"]').setValue(settings['db.msr.data_backup.timeframe.start_hh']);
			this.down('[name="db.msr.data_backup.timeframe.start_mm"]').setValue(settings['db.msr.data_backup.timeframe.start_mm']);
			this.down('[name="db.msr.data_backup.timeframe.end_hh"]').setValue(settings['db.msr.data_backup.timeframe.end_hh']);
			this.down('[name="db.msr.data_backup.timeframe.end_mm"]').setValue(settings['db.msr.data_backup.timeframe.end_mm']);

			//if (settings['db.msr.data_storage.engine'] == 'eph') {
			var ephDisk = this.down('[name="db.msr.data_storage.eph.disk"]');
			var defVal = ephDisk.store.getAt(1) ? ephDisk.store.getAt(1).get('device') : ephDisk.store.getAt(0).get('device');
			ephDisk.setValue(settings['db.msr.data_storage.eph.disk'] || defVal);
			//}
			
			if (settings['db.msr.data_storage.engine'] == 'ebs' || settings['db.msr.data_storage.engine'] == 'csvol') {
				if (settings['db.msr.data_storage.ebs.snaps.enable_rotation'] == 1) {
					this.down('[name="db.msr.data_storage.ebs.snaps.enable_rotation"]').setValue(true);
					this.down('[name="db.msr.data_storage.ebs.snaps.rotate"]').enable();
				} else {
					this.down('[name="db.msr.data_storage.ebs.snaps.enable_rotation"]').setValue(false);
					this.down('[name="db.msr.data_storage.ebs.snaps.rotate"]').disable();
				}
				this.down('[name="db.msr.data_storage.ebs.snaps.rotate"]').setValue(settings['db.msr.data_storage.ebs.snaps.rotate']);
				this.down('[name="db.msr.data_storage.ebs.size"]').setValue(settings['db.msr.data_storage.ebs.size']);
				this.down('[name="db.msr.data_storage.ebs.type"]').setValue(settings['db.msr.data_storage.ebs.type'] || 'standard');
				this.down('[name="db.msr.data_storage.ebs.iops"]').setValue(settings['db.msr.data_storage.ebs.iops'] || 50);
				
				if (settings['db.msr.data_storage.engine'] == 'csvol') {
					this.down('[name="db.msr.data_storage.ebs.type"]').hide();
					this.down('[name="db.msr.data_storage.ebs.iops"]').hide();
				} else {
					this.down('[name="db.msr.data_storage.ebs.type"]').show();
					
					if (this.down('[name="db.msr.data_storage.ebs.type"]').getValue() == 'io1')
						this.down('[name="db.msr.data_storage.ebs.iops"]').show();
					else
						this.down('[name="db.msr.data_storage.ebs.iops"]').hide();
				}
					
			}

			this.down('[name="db.msr.data_storage.engine"]').setValue(settings['db.msr.data_storage.engine']);
			
			if (!record.get('new')) {
				
				//RAID Settings
				this.down('[name="db.msr.data_storage.raid.level"]').disable()
				this.down('[name="db.msr.data_storage.raid.volumes_count"]').disable();
				this.down('[name="db.msr.data_storage.raid.volume_size"]').disable();
				
				// Engine & EBS Settings
				this.down('[name="db.msr.data_storage.engine"]').disable();
				this.down('[name="db.msr.data_storage.ebs.size"]').disable();
				this.down('[name="db.msr.data_storage.ebs.type"]').disable();
				this.down('[name="db.msr.data_storage.ebs.iops"]').disable();
				
				this.down('[name="db.msr.data_storage.fstype"]').disable();
			} else {
				//RAID Settings
				this.down('[name="db.msr.data_storage.raid.level"]').enable()
				this.down('[name="db.msr.data_storage.raid.volumes_count"]').enable();
				this.down('[name="db.msr.data_storage.raid.volume_size"]').enable();
				
				// Engine & EBS Settings
				this.down('[name="db.msr.data_storage.engine"]').enable();
				this.down('[name="db.msr.data_storage.ebs.size"]').enable();
				this.down('[name="db.msr.data_storage.ebs.type"]').enable();
				this.down('[name="db.msr.data_storage.ebs.iops"]').enable();
				this.down('[name="db.msr.data_storage.fstype"]').enable();
			}
		},

		hideTab: function (record) {
			var settings = record.get('settings');

			if (record.get('behaviors').match('redis')) {
				settings['db.msr.redis.persistence_type'] = this.down('[name="db.msr.redis.persistence_type"]').getValue();
				settings['db.msr.redis.use_password'] = this.down('[name="db.msr.redis.use_password"]').getValue();
				settings['db.msr.redis.num_processes'] = this.down('[name="db.msr.redis.num_processes"]').getValue();
			}

			if (! this.down('[name="db.msr.data_bundle.enabled"]').collapsed) {
				settings['db.msr.data_bundle.enabled'] = 1;
				settings['db.msr.data_bundle.every'] = this.down('[name="db.msr.data_bundle.every"]').getValue();
				settings['db.msr.data_bundle.timeframe.start_hh'] = this.down('[name="db.msr.data_bundle.timeframe.start_hh"]').getValue();
				settings['db.msr.data_bundle.timeframe.start_mm'] = this.down('[name="db.msr.data_bundle.timeframe.start_mm"]').getValue();
				settings['db.msr.data_bundle.timeframe.end_hh'] = this.down('[name="db.msr.data_bundle.timeframe.end_hh"]').getValue();
				settings['db.msr.data_bundle.timeframe.end_mm'] = this.down('[name="db.msr.data_bundle.timeframe.end_mm"]').getValue();
				
				settings['db.msr.data_bundle.use_slave'] = this.down('[name="db.msr.data_bundle.use_slave"]').getValue();
			} else {
				settings['db.msr.data_bundle.enabled'] = 0;
				delete settings['db.msr.data_bundle.every'];
				delete settings['db.msr.data_bundle.timeframe.start_hh'];
				delete settings['db.msr.data_bundle.timeframe.start_mm'];
				delete settings['db.msr.data_bundle.timeframe.end_hh'];
				delete settings['db.msr.data_bundle.timeframe.end_mm'];
			}

			if (! this.down('[name="db.msr.data_backup.enabled"]').collapsed) {
				settings['db.msr.data_backup.enabled'] = 1;
				settings['db.msr.data_backup.every'] = this.down('[name="db.msr.data_backup.every"]').getValue();
				settings['db.msr.data_backup.timeframe.start_hh'] = this.down('[name="db.msr.data_backup.timeframe.start_hh"]').getValue();
				settings['db.msr.data_backup.timeframe.start_mm'] = this.down('[name="db.msr.data_backup.timeframe.start_mm"]').getValue();
				settings['db.msr.data_backup.timeframe.end_hh'] = this.down('[name="db.msr.data_backup.timeframe.end_hh"]').getValue();
				settings['db.msr.data_backup.timeframe.end_mm'] = this.down('[name="db.msr.data_backup.timeframe.end_mm"]').getValue();
			} else {
				settings['db.msr.data_backup.enabled'] = 0;
				delete settings['db.msr.data_backup.every'];
				delete settings['db.msr.data_backup.timeframe.start_hh'];
				delete settings['db.msr.data_backup.timeframe.start_mm'];
				delete settings['db.msr.data_backup.timeframe.end_hh'];
				delete settings['db.msr.data_backup.timeframe.end_mm'];
			}

			if (record.get('new')) {
				settings['db.msr.data_storage.engine'] = this.down('[name="db.msr.data_storage.engine"]').getValue();
				settings['db.msr.data_storage.fstype'] = this.down('[name="db.msr.data_storage.fstype"]').getValue();
			}
			
			if (settings['db.msr.data_storage.engine'] == 'ebs' || settings['db.msr.data_storage.engine'] == 'csvol') {
				if (record.get('new')) {
					settings['db.msr.data_storage.ebs.size'] = this.down('[name="db.msr.data_storage.ebs.size"]').getValue();
					settings['db.msr.data_storage.ebs.type'] = this.down('[name="db.msr.data_storage.ebs.type"]').getValue();
					settings['db.msr.data_storage.ebs.iops'] = this.down('[name="db.msr.data_storage.ebs.iops"]').getValue();
				}

				if (this.down('[name="db.msr.data_storage.ebs.snaps.enable_rotation"]').getValue()) {
					settings['db.msr.data_storage.ebs.snaps.enable_rotation'] = 1;
					settings['db.msr.data_storage.ebs.snaps.rotate'] = this.down('[name="db.msr.data_storage.ebs.snaps.rotate"]').getValue();
				} else {
					settings['db.msr.data_storage.ebs.snaps.enable_rotation'] = 0;
					delete settings['db.msr.data_storage.ebs.snaps.rotate'];
				}
			} else {
				delete settings['db.msr.data_storage.ebs.size'];
				delete settings['db.msr.data_storage.ebs.snaps.enable_rotation'];
				delete settings['db.msr.data_storage.ebs.snaps.rotate'];
			}

			if (settings['db.msr.data_storage.engine'] == 'eph') {
				settings['db.msr.data_storage.eph.disk'] = this.down('[name="db.msr.data_storage.eph.disk"]').getValue();
			}

			if (settings['db.msr.data_storage.engine'] == 'lvm') {
				if (record.get('new')) {
					var volumes = {};
					Ext.each(this.down('[name="lvm_settings"]').query('checkbox'), function() {
						if (this.getValue()) {
							volumes[this.getName()] = this.ephSize;
						}
					});
					settings['db.msr.storage.lvm.volumes'] = Ext.encode(volumes);
				}
			}

			if (settings['db.msr.data_storage.engine'] == 'raid.ebs') {
				settings['db.msr.data_storage.raid.level'] = this.down('[name="db.msr.data_storage.raid.level"]').getValue();
				settings['db.msr.data_storage.raid.volume_size'] = this.down('[name="db.msr.data_storage.raid.volume_size"]').getValue();
				settings['db.msr.data_storage.raid.volumes_count'] = this.down('[name="db.msr.data_storage.raid.volumes_count"]').getValue();
				
				settings['db.msr.data_storage.raid.ebs.type'] = this.down('[name="db.msr.data_storage.raid.ebs.type"]').getValue();
				settings['db.msr.data_storage.raid.ebs.iops'] = this.down('[name="db.msr.data_storage.raid.ebs.iops"]').getValue();
			}

			record.set('settings', settings);
		},

		items: [{
				xtype: 'fieldset',
				name: 'redis_settings',
				hidden: true,
				title: 'Redis settings',
				items: [{ 
					xtype: 'combo',
					name: 'db.msr.redis.persistence_type',
					fieldLabel: 'Persistence type',
					editable: false,
					store: {
						fields: [ 'name', 'description' ],
						proxy: 'object'
					},
					valueField: 'name',
					displayField: 'description',
					width: 400,
					labelWidth: 160,
					queryMode: 'local'
				}, {
					xtype: 'combo',
					hidden: !Scalr.flags['betaMode'],
					fieldLabel: 'Number of processes',
					store: [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16],
					valueField: 'id',
					displayField: 'name',
					editable: false,
					queryMode: 'local',
					value: 1,
					name: 'db.msr.redis.num_processes',
					labelWidth: 160,
					width: 400
				}, {
					xtype: 'checkbox',
					hideLabel: true,
					name: 'db.msr.redis.use_password',
					boxLabel: 'Use password authentification'
				}]
			}, {
				xtype: 'fieldset',
				title: 'Storage settings',
				items: [{ 
					xtype: 'combo',
					name: 'db.msr.data_storage.engine',
					fieldLabel: 'Storage engine',
					editable: false,
					store: {
						fields: [ 'description', 'name' ],
						proxy: 'object'
					},
					valueField: 'name',
					displayField: 'description',
					width: 500,
					labelWidth: 160,
					queryMode: 'local',
					listeners:{
						change: function(){
							var upDbmsr = this.up('#dbmsr');
							upDbmsr.down('[name="ebs_settings"]').hide();
							upDbmsr.down('[name="raid_settings"]').hide();
							upDbmsr.down('[name="raid_settings_not_available"]').hide();
							upDbmsr.down('[name="eph_settings"]').hide();
							upDbmsr.down('[name="lvm_settings"]').hide();
							
							if (this.getValue() == 'ebs' || this.getValue() == 'csvol') {
								upDbmsr.down('[name="ebs_settings"]').show();
							} else if (this.getValue() == 'lvm') {
								upDbmsr.down('[name="lvm_settings"]').show();
							} else if (this.getValue() == 'raid.ebs') {
								
								if (moduleTabParams['featureRAID']) {
									upDbmsr.down('[name="raid_settings"]').show();
								} else {
									upDbmsr.down('[name="raid_settings_not_available"]').show();
								}
								
							} else if (this.getValue() == 'eph') {
								upDbmsr.down('[name="eph_settings"]').show();
								
							}
						}
					}
				}, { 
					xtype: 'combo',
					name: 'db.msr.data_storage.fstype',
					fieldLabel: 'Filesystem',
					editable: false,
					store: {
						fields: [ 'fs', 'description' ],
						proxy: 'object'
					},
					valueField: 'fs',
					displayField: 'description',
					width: 500,
					labelWidth: 160,
					queryMode: 'local'
				}]
			}, {
				xtype:'fieldset',
				name: 'eph_settings',
				title: 'Ephemeral Storage settings',
				hidden: true,
				items: [{ 
					xtype: 'combo',
					name: 'db.msr.data_storage.eph.disk',
					fieldLabel: 'Disk device',
					editable: false,
					store: {
						fields: [ 'device', 'description' ],
						proxy: 'object'
					},
					valueField: 'device',
					displayField: 'description',
					width: 500,
					labelWidth: 160,
					queryMode: 'local',
					listeners:{
						change:function(){
							//TODO:
						}
					}
				}]
			}, {
				xtype:'fieldset',
				name: 'lvm_settings',
				title: 'LVM Storage settings',
				hidden: true,
				items: [{
					xtype: 'textfield',
					hideLabel:true,
					value: 'LVM device on 2 SSD ephemeral drives (Total size: 2TB)',
					width: 400
				}]
			}, {
				xtype:'fieldset',
				name: 'ebs_settings',
				title: 'Block Storage settings',
				hidden: true,
				items: [{
					xtype: 'fieldcontainer',
					layout: 'hbox',
					fieldLabel: 'EBS type',
					width: 600,
					labelWidth:180,
					items: [{
						xtype: 'combo',
						store: [['standart', 'Standart'],['io1', 'Provisioned IOPS (1-1000): ']],
						valueField: 'id',
						displayField: 'name',
						editable: false,
						queryMode: 'local',
						value: 'standard',
						name: 'db.msr.data_storage.ebs.type',
						width: 200,
						listeners: {
							change: function (field, value) {
								var c = this.up().down('[name="db.msr.data_storage.ebs.iops"]');
								if (value == 'io1')
									c.show();
								else
									c.hide();
							}
						}
					}, {
						xtype: 'textfield',
						itemId: 'db.msr.data_storage.ebs.iops',
						name: 'db.msr.data_storage.ebs.iops',
						hideLabel: true,
						hidden: true,
						margin: '0 0 0 2',
						width: 40,
						value: '50'
					}]
				}, {
					xtype: 'textfield',
					fieldLabel: 'Storage size (max. 1000 GB)',
					labelWidth: 180,
					width: 300,
					name: 'db.msr.data_storage.ebs.size'
				}, {
					xtype: 'fieldcontainer',
					layout: 'hbox',
					name: 'ebs_rotation_settings',
					items: [{
						xtype: 'checkbox',
						hideLabel: true,
						name: 'db.msr.data_storage.ebs.snaps.enable_rotation',
						boxLabel: 'Snapshots are rotated',
						handler: function (checkbox, checked) {
							if (checked)
								this.next('[name="db.msr.data_storage.ebs.snaps.rotate"]').enable();
							else
								this.next('[name="db.msr.data_storage.ebs.snaps.rotate"]').disable();
						}
					}, {
						xtype: 'textfield',
						hideLabel: true,
						name: 'db.msr.data_storage.ebs.snaps.rotate',
						width: 40,
						margin: '0 0 0 3'
					}, {
						xtype: 'displayfield',
						value: 'times before being removed.',
						margin: '0 0 0 3'
					}]
				}]
			}, {
				xtype:'fieldset',
				name: 'raid_settings_not_available',
				title: 'RAID Storage settings',
				hidden: true,
				items: [{
					xtype: 'displayfield',
					fieldCls: 'x-form-field-warning',
					value: 'RAID arrays are not available for your pricing plan. <a href="#/billing">Please upgrade your account to be able to use this feature.</a>',
				}]
			}, {
				xtype:'fieldset',
				name: 'raid_settings',
				title: 'RAID storage settings',
				hidden: true,
				items: [{ 
					xtype: 'combo',
					name: 'db.msr.data_storage.raid.level',
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
								
								var obj = this.up('#dbmsr').down('[name="db.msr.data_storage.raid.volumes_count"]');
								obj.store.load({data: data});
								var val = obj.store.getAt(0).get('id');
								obj.setValue(val);
							} catch (e) {}
						}
					}
				}, {
					xtype: 'combo',
					name: 'db.msr.data_storage.raid.volumes_count',
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
					xtype: 'fieldcontainer',
					layout: 'hbox',
					fieldLabel: 'EBS type',
					width: 600,
					labelWidth:150,
					items: [{
						xtype: 'combo',
						store: [['standart', 'Standart'],['io1', 'Provisioned IOPS (1-1000): ']],
						valueField: 'id',
						displayField: 'name',
						editable: false,
						queryMode: 'local',
						name: 'db.msr.data_storage.raid.ebs.type',
						value: 'standard',
						width: 200,
						listeners: {
							change: function (field, value) {
								var c = this.up().down('[name="db.msr.data_storage.raid.ebs.iops"]');
								if (value == 'io1')
									c.show();
								else
									c.hide();
							}
						}
					}, {
						xtype: 'textfield',
						itemId: 'db.msr.data_storage.raid.ebs.iops',
						name: 'db.msr.data_storage.raid.ebs.iops',
						hideLabel: true,
						hidden: true,
						margin: '0 0 0 2',
						width: 40,
						value: '50'
					}]
				}, {
					xtype: 'textfield',
					fieldLabel: 'Each volume size',
					labelWidth: 160,
					width: 200,
					value: '10',
					name: 'db.msr.data_storage.raid.volume_size'
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
			}, {
			xtype: 'fieldset',
			checkboxToggle:  true,
			name: 'db.msr.data_bundle.enabled',
			title: 'Bundle and save data snapshot',
			defaults: {
				labelWidth: 150
			},
			items: [{
				xtype: 'fieldcontainer',
				layout: 'hbox',
				hideLabel: true,
				items: [{
					xtype: 'displayfield',
					value: 'Perform full data bundle every'
				}, {
					xtype: 'textfield',
					width: 40,
					margin: '0 0 0 3',
					name: 'db.msr.data_bundle.every'
				}, {
					xtype: 'displayfield',
					margin: '0 0 0 3',
					value: 'hours'
				}, {
					xtype: 'displayinfofield',
					margin: '0 0 0 5',
					info:   'DB snapshots contain a hotcopy of database data directory, file that holds binary log position and debian.cnf' +
							'<br>' +
							'When farm starts:<br>' +
							'1. Database master dowloads and extracts a snapshot from storage depends on cloud platfrom<br>' +
							'2. When data is loaded and master starts, slaves download and extract a snapshot as well<br>' +
							'3. Slaves are syncing with master for some time'
				}]
			}, {
				xtype: 'fieldcontainer',
				layout: 'hbox',
				fieldLabel: 'Preferred bundle window',
				items: [{
					xtype: 'textfield',
					name: 'db.msr.data_bundle.timeframe.start_hh',
					width: 40
				}, {
					xtype: 'displayfield',
					value: ':',
					margin: '0 0 0 3'
				}, {
					xtype: 'textfield',
					name: 'db.msr.data_bundle.timeframe.start_mm',
					width: 40,
					margin: '0 0 0 3'
				}, {
					xtype: 'displayfield',
					value: '-',
					margin: '0 0 0 3'
				}, {
					xtype: 'textfield',
					name: 'db.msr.data_bundle.timeframe.end_hh',
					width: 40,
					margin: '0 0 0 3'
				}, {
					xtype: 'displayfield',
					value: ':',
					margin: '0 0 0 3'
				},{
					xtype: 'textfield',
					name: 'db.msr.data_bundle.timeframe.end_mm',
					width: 40,
					margin: '0 0 0 3'
				}, {
					xtype: 'displayfield',
					value: 'Format: hh24:mi - hh24:mi',
					bodyStyle: 'font-style: italic',
					margin: '0 0 0 3'
				}]
			}, {
				xtype: 'checkbox',
				hideLabel: true,
				hidden: !Scalr.flags['betaMode'],
				name: 'db.msr.data_bundle.use_slave',
				boxLabel: 'Use SLAVE server for data bundle'
			}]
		}, {
			xtype: 'fieldset',
			checkboxToggle:  true,
			name: 'db.msr.data_backup.enabled',
			title: 'Backup data (gziped database dump)',
			defaults: {
				labelWidth: 150
			},
			items: [{
				xtype: 'fieldcontainer',
				layout: 'hbox',
				hideLabel: true,
				items: [{
					xtype: 'displayfield',
					value: 'Perform backup every'
				}, {
					xtype: 'textfield',
					width: 40,
					margin: '0 0 0 3',
					name: 'db.msr.data_backup.every'
				}, {
					xtype: 'displayfield',
					margin: '0 0 0 3',
					value: 'hours'
				}]
			}, {
				xtype: 'fieldcontainer',
				layout: 'hbox',
				fieldLabel: 'Preferred backup window',
				items: [{
					xtype: 'textfield',
					name: 'db.msr.data_backup.timeframe.start_hh',
					width: 40
				}, {
					xtype: 'displayfield',
					value: ':',
					margin: '0 0 0 3'
				}, {
					xtype: 'textfield',
					name: 'db.msr.data_backup.timeframe.start_mm',
					width: 40,
					margin:'0 0 0 3'
				}, {
					xtype: 'displayfield',
					value: '-',
					margin: '0 0 0 3'
				}, {
					xtype: 'textfield',
					name: 'db.msr.data_backup.timeframe.end_hh',
					width: 40,
					margin: '0 0 0 3'
				}, {
					xtype: 'displayfield',
					value: ':',
					margin: '0 0 0 3'
				},{
					xtype: 'textfield',
					name: 'db.msr.data_backup.timeframe.end_mm',
					width: 40,
					margin: '0 0 0 3'
				}, {
					xtype: 'displayfield',
					value: 'Format: hh24:mi - hh24:mi',
					bodyStyle: 'font-style: italic',
					margin: '0 0 0 3'
				}]
			}]
		}]
	});
});

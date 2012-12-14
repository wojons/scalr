Scalr.regPage('Scalr.ui.farms.builder.tabs.placement', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Placement and type',
		itemId: 'aws_placement',
		cache: {},
		isEnabled: function (record) {
			return record.get('platform') == 'ec2';
		},

		getDefaultValues: function (record) {
			return {
				'aws.availability_zone': '',
				'aws.instance_type': record.get('arch') == 'i386' ? 'm1.small' : 'm1.large'
			};
		},

		beforeShowTab: function (record, handler) {
			var cloudLocation = record.get('cloud_location');

			if (this.cacheExist(['availabilityZonesEC2', cloudLocation]))
				handler();
			else
				Scalr.Request({
					processBox: {
						type: 'action'
					},
					url: '/platforms/ec2/xGetAvailZones',
					params: {
						cloudLocation: cloudLocation,
						roleId: record.get('role_id'),
						platform: record.get('platform')
					},
					scope: this,
					success: function (response) {
						this.cacheSet(response.data, ['availabilityZonesEC2', cloudLocation]);
						handler();
					},
					failure: function () {
						this.deactivateTab();
					}
				});
		},

		showTab: function (record) {
			var settings = record.get('settings');

			var tagsString = (record.get('tags')) ? record.get('tags').join(" ") : "";
			var typeArray = new Array();
			var typeValue = '';

			if (record.get('arch') == 'i386') {
				if ((tagsString.indexOf('ec2.ebs') != -1 || settings['aws.instance_type'] == 't1.micro') && !record.get('behaviors').match('cf_cloud_controller'))
					typeArray = ['t1.micro', 'm1.small', 'c1.medium'];
				else
					typeArray = ['m1.small', 'c1.medium'];

				typeValue = (settings['aws.instance_type'] || 'm1.small');
			} else {
				
				typeValue = (settings['aws.instance_type'] || 'm1.large');
				
				if (tagsString.indexOf('ec2.ebs') != -1 || settings['aws.instance_type'] == 't1.micro') {
					if (tagsString.indexOf('ec2.hvm') != -1 && record.get('os') != '2008Server') {
						typeArray = ['cc1.4xlarge', 'cc2.8xlarge', 'cg1.4xlarge', 'hi1.4xlarge'];
						
						if (settings['aws.instance_type'] != 'm1.large')
							typeValue = (settings['aws.instance_type'] || 'cc1.4xlarge');
						else
							typeValue = 'cc1.4xlarge';
						
					} else {
						if (record.get('behaviors').match('cf_cloud_controller'))
							typeArray = ['m1.small', 'c1.medium', 'm1.medium', 'm1.large', 'm1.xlarge', 'c1.xlarge', 'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge', 'm3.xlarge', 'm3.2xlarge', 'hi1.4xlarge'];
						else
							typeArray = ['t1.micro', 'm1.small', 'c1.medium', 'm1.medium', 'm1.large', 'm1.xlarge', 'c1.xlarge', 'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge', 'm3.xlarge', 'm3.2xlarge', 'hi1.4xlarge'];
						typeValue = (settings['aws.instance_type'] || 'm1.small');
					}
				} else {
					typeArray = ['m1.large', 'm1.xlarge', 'c1.xlarge', 'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge'];
					typeValue = (settings['aws.instance_type'] || 'm1.large');
				}
			}

			this.down('[name="aws.instance_type"]').store.load({ data: typeArray });
			this.down('[name="aws.instance_type"]').setValue(typeValue);

			
			if (tagsString.indexOf('ec2.ebs') != -1) {
				if (typeValue == 'm1.large' || typeValue == 'm1.xlarge' || typeValue == 'm2.4xlarge' || typeValue == 'm3.xlarge' || typeValue == 'm3.2xlarge') {
					this.down('[name="aws.ebs_type"]').show();
					
					if (settings['aws.ebs_optimized'] == 1) {
						this.down('[name="aws.ebs_optimized"]').setValue(true);
					} else {
						this.down('[name="aws.ebs_optimized"]').setValue(false);
					}
					
				} else
					this.down('[name="aws.ebs_type"]').hide();
			} else
				this.down('[name="aws.ebs_type"]').hide();
			
			
			var comp = this.down('#aws_availability_zone_loc'), data = this.cacheGet(['availabilityZonesEC2', record.get('cloud_location')]);

			comp.removeAll();
			for (var i = 0; i < data.length; i++)
				comp.add({
					boxLabel: data[i].name,
					name: data[i].id
				});

			var d = [{ id: 'x-scalr-diff', name: 'Distribute equally' }, { id: '', name: 'AWS-chosen' }, { id: 'x-scalr-custom', name: 'Selected by me' }];
			for (var i = 0; i < data.length; i++)
				d.push(data[i]);

			this.down('[name="aws.availability_zone"]').store.load({ data: d });

			var zone = settings['aws.availability_zone'] || '';
			if (zone.match(/x-scalr-custom/)) {
				var loc = zone.replace('x-scalr-custom=', '').split(':');
				this.down('#aws_availability_zone_loc').items.each(function () {
					for (var i = 0; i < loc.length; i++) {
						if (this.name == loc[i])
							this.setValue(true);
					}
				});

				this.down('#aws_availability_zone_loc').show();
				zone = 'x-scalr-custom';
			}

			this.down('[name="aws.availability_zone"]').setValue(zone);

			if (
				record.get('behaviors').match('mysql') &&
				settings['mysql.data_storage_engine'] == 'ebs' &&
				settings['mysql.master_ebs_volume_id'] != '' &&
				settings['mysql.master_ebs_volume_id'] != undefined &&
				record.get('generation') != 2 &&
				this.down('[name="aws.availability_zone"]').getValue() != '' &&
				this.down('[name="aws.availability_zone"]').getValue() != 'x-scalr-diff'
			) {
				this.down('[name="aws.availability_zone"]').disable();
				this.down('#aws_availability_zone_warn').show();
			} else {
				this.down('[name="aws.availability_zone"]').enable();
				this.down('#aws_availability_zone_warn').hide();
			}
		},

		hideTab: function (record) {
			var settings = record.get('settings');

			settings['aws.instance_type'] = this.down('[name="aws.instance_type"]').getValue();

			if (this.down('[name="aws.availability_zone"]').getValue() == 'x-scalr-custom') {
				var loc = [];
				this.down('#aws_availability_zone_loc').items.each(function () {
					if (this.getValue())
						loc[loc.length] = this.name;
				});

				// TODO: replace hack
				if (loc.length == 0)
					Scalr.message.Error('Availability zone for role "' + record.get('name') + '" should be selected');
				else
					Scalr.message.Flush();

				settings['aws.availability_zone'] = 'x-scalr-custom=' + loc.join(':');
			} else
				settings['aws.availability_zone'] = this.down('[name="aws.availability_zone"]').getValue();

			
			//aws.root_ebs_type
			//aws.root_ebs_iops
			if (settings['aws.instance_type'] == 'm1.large' || settings['aws.instance_type'] == 'm1.xlarge' || settings['aws.instance_type'] == 'm2.4xlarge') {
				/*
				settings['aws.root_ebs_type'] = this.down('[name="aws.root_ebs_type"]').getValue();
				if (settings['aws.root_ebs_type'] == 'io1') {
					settings['aws.root_ebs_iops'] = this.down('[name="aws.root_ebs_iops"]').getValue();
				}
				*/
				settings['aws.ebs_optimized'] = this.down('[name="aws.ebs_optimized"]').getValue() ? 1 : 0;
			} else
				settings['aws.ebs_optimized'] = 0;
			
			//TODO: dbmsr.js
			if (record.get('platform') == 'ec2') {
				
				var devices = [];
				devices['/dev/sda2'] ={'m1.small':1, 'c1.medium':1};
				devices['/dev/sdb'] = {'m1.medium':1, 'm1.large':1, 'm1.xlarge':1, 'c1.xlarge':1, 'cc1.4xlarge':1, 'cc2.8xlarge':1, 'm2.xlarge':1, 'm2.2xlarge':1, 'm2.4xlarge':1};
				devices['/dev/sdc'] = {               'm1.large':1, 'm1.xlarge':1, 'c1.xlarge':1, 'cc1.4xlarge':1, 'cc2.8xlarge':1};
				devices['/dev/sdd'] = {						 		'm1.xlarge':1, 'c1.xlarge':1, 			   	   'cc2.8xlarge':1 };
				devices['/dev/sde'] = {						 		'm1.xlarge':1, 'c1.xlarge':1, 			       'cc2.8xlarge':1 };
				
				devices['/dev/sdf'] = {'hi1.4xlarge':1 };
				devices['/dev/sdg'] = {'hi1.4xlarge':1 };
				
				var fistDevice = "";
				var availableDisks = [];
				
				for (var deviceName in devices) {
					if (devices[deviceName][settings['aws.instance_type']] == 1) {
						availableDisks[availableDisks.length] = {'device':deviceName, 'description':'LVM on '+deviceName+' (80% available for data)'};
						
						if (fistDevice == "")
							fistDevice = deviceName;
						
						if (settings['db.msr.data_storage.eph.disk'] == deviceName) {
							fistDevice = deviceName;
						}
					}
				}
			}
			settings['db.msr.data_storage.eph.disk'] = fistDevice;
			
			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
			items: [{
				xtype: 'fieldcontainer',
				layout: 'hbox',
				fieldLabel: 'Availability zone',
				labelWidth: 100,
				items: [{
					xtype: 'combo',
					store: {
						fields: [ 'id', 'name', 'state' ],
						proxy: 'object'
					},
					valueField: 'id',
					displayField: 'name',
					editable: false,
					queryMode: 'local',
					name: 'aws.availability_zone',
					width: 300,
					listeners: {
						change: function (field, value) {
							var c = this.next('#aws_availability_zone_loc');
							if (value == 'x-scalr-custom')
								c.show();
							else
								c.hide();
						}
					}, listConfig : {
				        tpl : '<tpl for="."><div class="x-boundlist-item">{name} <tpl if="state == &quot;available&quot;">(<span style="color:green;">UP</span>)<tpl elseif="state">(<span style="color:red;">DOWN</span>: {state})</tpl></div></tpl>'
				    }
				}, {
					xtype: 'displayinfofield',
					itemId: 'aws_availability_zone_warn',
					hidden: true,
					margin: '0 0 0 5',
					info: ('If you want to change placement, you need to remove Master EBS volume first on <a href="#/dbmsr/status?farmid=%FARMID%&type=mysql">MySQL status page</a>.').replace('%FARMID%', moduleTabParams.farmId)
				}, {
					itemId: 'aws_availability_zone_loc',
					xtype: 'checkboxgroup',
					flex: 1,
					columns: [ 100, 100, 100, 100, 100, 100, 100 ],
					margin: '0 0 0 8',
					defaults: {
						margin: 0
					},
					hidden: true
				}]
			}, {
				xtype: 'combo',
				store: {
					fields: [ 'id', 'name' ],
					proxy: 'object'
				},
				valueField: 'name',
				displayField: 'name',
				fieldLabel: 'Instance type',
				editable: false,
				queryMode: 'local',
				name: 'aws.instance_type',
				width: 405,
				labelWidth: 100,
				listeners: {
					change: function () {						
						var value = this.getValue();
						
						if (value == 'm1.large' || value == 'm1.xlarge' || value == 'm2.4xlarge' || value == 'm3.xlarge' || value == 'm3.2xlarge')
							this.up('#aws_placement').down('[name="aws.ebs_type"]').show();
						else
							this.up('#aws_placement').down('[name="aws.ebs_type"]').hide();
					}
				}
			}, {
				xtype: 'fieldcontainer',
				layout: 'hbox',
				name: 'aws.ebs_type',
				hidden: true,
				hideLabel: true,
				items: [{
					xtype: 'checkbox',
					name: 'aws.ebs_optimized',
					boxLabel: 'Launch instances as an <a traget="_blank" href="http://aws.typepad.com/aws/2012/08/fast-forward-provisioned-iops-ebs.html">EBS-Optimized</a>'
				}]
			}
				
			/*{
				xtype: 'fieldcontainer',
				layout: 'hbox',
				name: 'aws.ebs_type',
				hidden: true,
				fieldLabel: 'Root volume (EBS) type',
				width: 600,
				labelWidth:150,
				items: [{
					xtype: 'combo',
					store: [['standart', 'Standart'],['io1', 'Provisioned IOPS (1-1000): ']],
					valueField: 'id',
					displayField: 'name',
					editable: false,
					queryMode: 'local',
					name: 'aws.root_ebs_type',
					width: 200,
					listeners: {
						change: function (field, value) {
							var c = this.up().down('[name="aws.root_ebs_iops"]');
							if (value == 'io1')
								c.show();
							else
								c.hide();
						}
					}
				}, {
					xtype: 'textfield',
					itemId: 'aws.root_ebs_iops',
					name: 'aws.root_ebs_iops',
					hideLabel: true,
					hidden: true,
					margin: '0 0 0 5',
					width: 150,
					value: '500'
				}]
			}*/]
		}]
	});
});

Scalr.regPage('Scalr.ui.farms.builder.tabs.storage', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Storage',
		itemId: 'storage',
		cache: {},

		isEnabled: function (record) {
			return Scalr.flags['betaMode'];
		},
		
		/*beforeShowTab: function (record, handler) {
			var chefId = record.get('settings')['chef.runlist_id'];
			this.down('[name="enableChef"]').setValue(!Ext.isEmpty(chefId));
			
			var gridPanel = this.down('#runList');
			gridPanel.getSelectionModel().deselectAll();
			this.down('#runList').store.load(function (records, operations, success){
				for (i = 0; i<records.length; i++) {
					if (records[i].get('id') == chefId) {
						gridPanel.getSelectionModel().select(records[i].index);
						records[i].set('sel', 1);
					}
				}
			});
			Scalr.event.on('update', getListRecord, this);
		},*/
		
		showTab: function (record) {
			var settings = this.down('#settings'), data = [];

			this.down('#configuration').store.loadData([]);


			settings.down('[name="purpose"]').store.loadData([{
				name: 'db', description: 'Database'
			}, {
				name: 'additional', description: 'Additional'
			}]);

			// Storage engine
			if (record.get('platform') == 'ec2') {
				data = [{
					name:'ebs', description:'EBS Volume'
				}, {
					name:'eph', description:'Ephemeral device'
				}, {
					name:'lvm', description:'LVM'
				}, {
					name:'raid.ebs', description:'RAID array'
				}];
			} else if (record.get('platform') == 'rackspace') {
				data = [{
					name:'eph', description:'Ephemeral device'
				}];
			} else if (record.get('platform') == 'cloudstack' || record.get('platform') == 'idcf' || record.get('platform') == 'ucloud') {
				data = [{
					name:'csvol', description:'CloudStack Block Volume'
				}];
			}
			settings.down('[name="type"]').store.loadData(data);

			// Filesystem
			var data = [{ fs: 'ext3', description: 'Ext3' }];
			if ((record.get('image_os_family') == 'centos' && record.get('arch') == 'x86_64') ||
				(record.get('image_os_family') == 'ubuntu' && (record.get('image_os_version') == '10.04' || record.get('image_os_version') == '12.04'))
				) {
				if (moduleTabParams['featureMFS']) {
					data.push({ fs: 'ext4', description: 'Ext4'});
					data.push({ fs: 'xfs', description: 'XFS'});
				} else {
					data.push({ fs: 'ext4', description: 'Ext4 (Not available for your pricing plan)'});
					data.push({ fs: 'xfs', description: 'XFS (Not available for your pricing plan)'});
				}
			}
			settings.down('[name="fs"]').store.loadData(data);

			this.down('#ebs_settings').hide();


		},
		
		hideTab: function (record) {
			/*var settings = record.get('settings');
			settings['chef.runlist_id'] = '';
			settings['chef.attributes'] = '';
			if (this.down('[name="enableChef"]').getValue()) {
				this.down('#runList').store.each(function(item){
					if (item.get('sel')) {
						settings['chef.runlist_id'] = item.get('id');
						var data = {};
						Ext.each(Ext.decode(item.get('attributes')), function(attribItem){
							data[attribItem.name] = attribItem.value;
						});
						settings['chef.attributes'] = Ext.encode(data);
					}
				});
			}
			this.down('#attrib').down('#optionsView').store.removeAll();
			Scalr.event.un('update', getListRecord, this);*/
			/*if (this.down('[name="enableChef"]').getValue() && this.down('#runList').getSelectionModel().getSelection()[0]) {
				var data = {};
				Ext.each(Ext.decode(this.down('#runList').getSelectionModel().getSelection()[0].get('attributes')), function(item){
					data[item.name] = item.value;
				}); 
				settings['chef.runlist_id'] = this.down('#runList').getSelectionModel().getSelection()[0].get('id');
				settings['chef.attributes'] = Ext.encode(data);
			} else {}

			record.set('settings', settings);*/
		},

		layout: 'fit',
		items: [{
			xtype: 'container',
			padding: '0 0 12 0',
			layout: {
				type: 'hbox',
				align: 'stretch'
			},
			items: [{
				xtype: 'container',
				layout: {
					type: 'vbox',
					align: 'stretch'
				},
				flex: 1,
				items: [{
					xtype: 'gridpanel',
					flex: 1,
					itemId: 'configuration',

					title: 'Storage configuration',

					viewConfig: {
						disableSelection: true,
						emptyText: 'No storages'
					},
					store: {
						proxy: 'object',
						fields: [ 'purpose', 'type', 'fs', 'settings' ]
					},
					columns: [
						{ header: 'Purpose', width: 100, sortable: true, dataIndex: 'purpose' },
						{ header: 'Type', flex: 1, sortable: true, dataIndex: 'type' },
						{ header: 'FS', flex: 1, sortable: true, dataIndex: 'fs' },
						{ header: 'Description', flex: 3, sortable: false, dataIndex: 'settings', xtype: 'templatecolumn', tpl:
							''
						}
					],
					dockedItems: [{
						xtype: 'toolbar',
						dock: 'top',
						items: [{
							ui: 'paging',
							iconCls: 'x-tbar-add',
							handler: function() {
								this.up('#storage').down('#editor').applyRecord(null, this.up('#storage').currentRole);
							}
						}]
					}]
				}, {
					xtype: 'gridpanel',
					flex: 1,

					title: 'Storage usage',

					viewConfig: {
						disableSelection: true,
						emptyText: 'No storage'
					},
					store: {
						proxy: 'object',
						fields: [ 'elasticIp', 'instanceId', 'serverId', 'serverIndex', 'remoteIp', 'warningInstanceIdDoesntMatch' ]
					},
					columns: [
						{ header: 'Server Index', flex: 1, sortable: true, dataIndex: 'serverIndex' }
					]
				}]
			}, {
				xtype: 'container',
				width: 500,
				autoScroll: true,
				margin: '0 0 0 12',
				itemId: 'editor',
				applyRecord: function(record, farmRoleRecord) {
					var settings = this.down('#settings');
					settings.show();




					if (record) {

					}
				},
				saveRecord: function() {

				},
				items: [{
					xtype: 'fieldset',
					title: 'Settings',
					itemId: 'settings',
					layout: 'anchor',
					hidden: true,
					defaults: {
						anchor: '100%',
						fieldLabel: 100
					},
					items: [{
						xtype: 'combo',
						name: 'purpose',
						fieldLabel: 'Purpose',
						editable: false,
						store: {
							fields: [ 'name', 'description' ],
							proxy: 'object'
						},
						valueField: 'name',
						displayField: 'description',
						queryMode: 'local',
						listeners: {
							change: function(field, value) {

							}
						}
					}, {
						xtype: 'combo',
						name: 'type',
						fieldLabel: 'Storage engine',
						editable: false,
						store: {
							fields: [ 'description', 'name' ],
							proxy: 'object'
						},
						valueField: 'name',
						displayField: 'description',
						queryMode: 'local',
						listeners: {
							change: function(field, value) {
								var editor = this.up('#editor');

								editor.down('#ebs_settings').hide();

								if (value == 'ebs') {
									editor.down('#ebs_settings').show();
								}


								/*var upDbmsr = this.up('#dbmsr');
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

								}*/
							}
						}
					}, {
						xtype: 'combo',
						name: 'fs',
						fieldLabel: 'Filesystem',
						editable: false,
						store: {
							fields: [ 'fs', 'description' ],
							proxy: 'object'
						},
						valueField: 'fs',
						displayField: 'description',
						queryMode: 'local'
					}]
				}, {
					xtype:'fieldset',
					itemId: 'ebs_settings',
					title: 'EBS settings',
					layout: 'anchor',
					defaults: {
						labelWidth: 100,
						anchor: '100%'
					},
					getValue: function() {
						var data = {};
						data['ebs.size'] = this.down('[name="ebs.size"]').getValue();

					},
					setValue: function() {

						// iops value: '50'


					},
					items: [{
						xtype: 'fieldcontainer',
						layout: 'hbox',
						fieldLabel: 'Storage size',
						items: [{
							xtype: 'textfield',
							name: 'ebs.size',
							allowBlank: false,
							flex: 1,
							validator: function(value) {
								if (parseInt(value) > 1000)
									return 'Size should be less than 1000 GB';
								else
									return true;
							}
						}, {
							xtype: 'displayinfofield',
							margin: '0 0 0 5',
							value: 'Max size - 1000 GB'
						}]
					}, {
						xtype: 'fieldcontainer',
						layout: 'hbox',
						fieldLabel: 'EBS type',
						items: [{
							xtype: 'combo',
							store: [['standart', 'Standart'],['io1', 'Provisioned IOPS (1-1000): ']],
							valueField: 'id',
							displayField: 'name',
							editable: false,
							queryMode: 'local',
							value: 'standard',
							name: 'ebs.type',
							flex: 1,
							listeners: {
								change: function (field, value) {
									var c = this.next('[name="ebs.iops"]');
									if (value == 'io1')
										c.show();
									else
										c.hide();
								}
							}
						}, {
							xtype: 'textfield',
							name: 'ebs.iops',
							hideLabel: true,
							hidden: true,
							margin: '0 0 0 5',
							width: 40
						}]
					}, {
						xtype: 'fieldcontainer',
						layout: 'hbox',
						name: 'ebs_rotation_settings',
						items: [{
							xtype: 'checkbox',
							hideLabel: true,
							name: 'ebs.snaps.enable_rotation',
							boxLabel: 'Snapshots are rotated',
							handler: function (checkbox, checked) {
								if (checked)
									this.next('[name="ebs.snaps.rotate"]').enable();
								else
									this.next('[name="ebs.snaps.rotate"]').disable();
							}
						}, {
							xtype: 'textfield',
							hideLabel: true,
							name: 'ebs.snaps.rotate',
							width: 40,
							margin: '0 0 0 5'
						}, {
							xtype: 'displayfield',
							value: 'times before being removed.',
							margin: '0 0 0 5'
						}]
					}]
				}]
			}]

		}]
	});
});
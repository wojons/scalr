Scalr.regPage('Scalr.ui.farms.builder.tabs.ebs2', function (moduleTabParams) {
	var form = Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'EBS 2',
		cache: {},

		isEnabled: function (record) {
			
			var pageParameters = Ext.urlDecode(window.location.search.substring(1));
			
			return record.get('platform') == 'ec2' && pageParameters["beta"] == 1;
		},

		getDefaultValues: function (record) {
			return {
				'aws.use_ebs': 0
			};
		},

		beforeShowTab: function (record, handler) {
			var cloudLocation = record.get('cloud_location');
			
				Scalr.Request({
					processBox: {
						type: 'action'
					},
					url: '/platforms/ec2/xGetFarmRoleEBSSettings',
					params: {
						cloudLocation: cloudLocation,
						farmRoleId: record.get('farm_role_id'),
						farmId: moduleTabParams.farmId,
					},
					scope: this,
					success: function (response) {
						this.cacheSet(response.data, ['snapshotsEC2', cloudLocation]);
						handler();
					},
					failure: function () {
						this.deactivateTab();
					}
				});
		},

		showTab: function (record) {
			var settings = record.get('settings');

		
			var data = [{'index':1, 'size': 10, 'snapshot': 'snap-12312321', 're_use': 1, 'mountpoint' : '/mnt/storage'},
			            {'index':2, 'size': 30, 'snapshot': '', 're_use': 1, 'mountpoint' : '/mnt/storage'},
			            {'index':3, 'size': 10, 'snapshot': 'snap-12312321', 're_use': 0, 'mountpoint' : ''}
			];
			
			this.down('#ebsVolumes').store.load({ data: data });
			
			var data = [
			            {'server':'#4 (not running)', 'v1': 'vol-1231231', 'v2': 'vol-1231231', 'v3': 'vol-1231231', 'v4': 'vol-1231231', 'v5': 'vol-1231231', 'v6': 'vol-1231231', 'v7': 'vol-1231231', 'v8': 'vol-1231231'}
			           
			];
			
			this.down('#ebsMapping').store.load({ data: data });
		},

		hideTab: function (record) {
			var settings = record.get('settings');

			this.down('#ebsVolumes').store.each(function (rec) {
				settings['aws.' + i++] = [ rec.get('protocol'), rec.get('lb_port'), rec.get('instance_port'), rec.get('ssl_certificate') ].join("#");
			});

			//record.set('settings', settings);
		},

		items: [{
			xtype: 'grid',
			anchor: '100%',
			title: 'EBS Volumes Configuration',
			itemId: 'ebsVolumes',
			deferEmptyText: false,
			store: {
				proxy: 'object',
				fields: [ 'index', 'size', 'snapshot' , 're_use', 'mountpoint']
			},
			forceFit: true,
			plugins: {
				ptype: 'gridstore',
			},

			viewConfig: {
				emptyText: 'EBS volumes not configured'
			},

			columns: [
				{ header: 'Index', flex: 150, sortable: true, dataIndex: 'index' },
				{ header: 'Size (GB)', flex: 280, sortable: true, dataIndex: 'size' },
				{ header: 'Type', flex: 280, sortable: true, dataIndex: 'type', tpl:
					'{type}<tpl if="type == \"io1\"">: {iops}</tpl>' 
				},
				{ header: 'Snapshot', flex: 180, sortable: true, dataIndex: 'snapshot' },
				{ header: 'Re-use', flex: 200, sortable: true, dataIndex: 're_use', tpl:
					'<tpl if="re_use"><img src="/ui2/images/icons/true.png" /></tpl><tpl if="!re_use"><img src="/ui2/images/icons/false.png" /></tpl>'
				},
				{ header: 'Mount to', flex: 200, sortable: true, dataIndex: 'mountpoint', tpl:
					'<tpl if="mountpoint">{mountpoint}</tpl><tpl if="!mountpoint"><img src="/ui2/images/icons/false.png" /></tpl>'
				},
				{ header: '&nbsp;', width: 20, sortable: false, dataIndex: 'id', align:'center', xtype: 'templatecolumn',
					tpl: '<img class="delete" src="/ui2/images/icons/delete_icon_16x16.png">', clickHandler: function (comp, store, record) {
						store.remove(record);
					}
				}
			],

			listeners: {
				itemclick: function (view, record, item, index, e) {
					if (e.getTarget('img.delete'))
						view.store.remove(record);
				}
			}, dockedItems: [{
				xtype: 'toolbar',
				dock: 'top',
				layout: {
					type: 'hbox',
					align: 'left',
					pack: 'start'
				},
				items: [{
					ui: 'paging',
					iconCls: 'x-tbar-add',
					handler: function() {
						Scalr.Confirm({
							form: [{
								xtype: 'fieldcontainer',
								layout: 'hbox',
								hideLabel: true,
								items: [{
									xtype: 'displayfield',
									value: 'Size'
								}, {
									xtype: 'textfield',
									name: 'ebs_size',
									margin:'0 0 0 3',
									width: 40
								}, {
									xtype: 'displayfield',
									margin:'0 0 0 3',
									value: 'GB'
								}, {
									xtype: 'fieldcontainer',
									layout: 'hbox',
									margin:'0 0 0 13',
									name: 'ebs_settings',
									//hidden: (pageParameters['beta'] != 1),
									hideLabel: true,
									//fieldLabel: 'Type',
									width: 460,
									labelWidth: 32,
									items: [{
										xtype: 'combo',
										store: {
											fields: [ 'id', 'name' ],
											proxy: 'object',
											data: [{id: 'standart', name: 'Standart'}, {id: 'io1', name: 'Provisioned IOPS (1-1000): '}]
										},
										valueField: 'id',
										displayField: 'name',
										editable: false,
										queryMode: 'local',
										name: 'ebs_type',
										value: 'standard',
										width: 200,
										listeners: {
											change: function (field, value) {
												var c = this.up().down('[name="ebs_iops"]');
												if (value == 'io1')
													c.show();
												else
													c.hide();
											}
										}
									}, {
										xtype: 'textfield',
										itemId: 'ebs_iops',
										name: 'ebs_iops',
										hideLabel: true,
										hidden: true,
										margin: '0 0 0 2',
										width: 40,
										value: '500'
									}]
								}]
							}, {
								xtype: 'combo',
								name: 'ebs_snapid',
								emptyText: 'No snapshot selected',
								fieldLabel: 'Snapshot',
								labelWidth: 80,
								editable: true,
								forceSelection: false,
								width: 500,
								typeAhead: true,
								allowBlank: true,
								selectOnFocus: true,
								valueField: 'snapid',
								displayField: 'snapid',
								queryMode: 'local',
								displayTpl: '<tpl for="."><tpl if="snapid">{snapid} (Created: {createdat}, Size: {size}GB)</tpl></tpl>',
								listConfig: {
									getInnerTpl: function() {
										return '<tpl for="."><tpl if="snapid">{snapid} (Created: {createdat}, Size: {size}GB)</tpl></tpl>';
									}
								},
								store: {
									fields: [ 'snapid', 'createdat', 'size' ],
									proxy: 'object'
								}
							}, {
								xtype: 'fieldcontainer',
								layout: 'hbox',
								hideLabel: true,
								items: [{
									xtype: 'checkbox',
									boxLabel: 'Mount volume to',
									name: 'ebs_mount',
									handler: function (field, checked) {
										if (checked)
											this.next('[name="ebs_mountpoint"]').enable();
										else
											this.next('[name="ebs_mountpoint"]').disable();
									}
								}, {
									xtype: 'textfield',
									margin: '0 0 0 3',
									name: 'ebs_mountpoint'
								}, {
									xtype: 'displayfield',
									margin: '0 0 0 3',
									value: 'mount point.'
								}]
							}, {
								xtype: 'checkbox',
								boxLabel: 'Do not re-use this volume',
								name: 're_use',
								checked: true
							}],
							ok: 'Setup',
							title: 'EBS volume settings',
							formValidate: true,
							closeOnSuccess: true,
							scope: this,
							success: function (formValues) {
								/*
								 var view = this.up('#listeners'), store = view.store;

								 if (store.findBy(function (record) {
								 if (
								 record.get('protocol') == formValues.protocol &&
								 record.get('lb_port') == formValues.lb_port &&
								 record.get('instance_port') == formValues.instance_port
								 ) {
								 Scalr.message.Error('Such listener already exists');
								 return true;
								 }
								 }) == -1) {
								 store.add(formValues);
								 return true;
								 } else {
								 return false;
								 }
								 */
							}
						});
					}
				}]
			}]
		}, {
			xtype: 'grid',
			anchor: '100%',
			title: 'Current EBS volumes mapping',
			itemId: 'ebsMapping',
			deferEmptyText: false,
			store: {
				proxy: 'object',
				fields: [ 'server', 'v1', 'v2' , 'v3', 'v4', 'v5', 'v6', 'v7', 'v8']
			},
			forceFit: true,
			plugins: {
				ptype: 'gridstore',
			},

			viewConfig: {
				emptyText: 'No volumes were created for this role'
			},

			columns: [
				{ header: 'Server', flex: 800, sortable: true, dataIndex: 'server' },
				{ header: 'Volume #1', flex: 200, sortable: true, dataIndex: 'v1' },
				{ header: 'Volume #2', flex: 200, sortable: true, dataIndex: 'v2' },
				{ header: 'Volume #3', flex: 200, sortable: true, dataIndex: 'v3' },
				{ header: 'Volume #4', flex: 200, sortable: true, dataIndex: 'v4' },
				{ header: 'Volume #5', flex: 200, sortable: true, dataIndex: 'v5' },
				{ header: 'Volume #6', flex: 200, sortable: true, dataIndex: 'v6' },
				{ header: 'Volume #7', flex: 200, sortable: true, dataIndex: 'v7' },
				{ header: 'Volume #8', flex: 200, sortable: true, dataIndex: 'v8' }
			],

			listeners: {
				itemclick: function (view, record, item, index, e) {
					if (e.getTarget('img.delete'))
						view.store.remove(record);
				}
			}
		}]
	});
	
	return form;
});

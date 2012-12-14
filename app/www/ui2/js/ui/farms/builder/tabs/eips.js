Scalr.regPage('Scalr.ui.farms.builder.tabs.eips', function () {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Elastic IPs',
		cache: {},

		isEnabled: function (record) {
			return record.get('platform') == 'ec2';
		},

		getDefaultValues: function (record) {
			return {
				'aws.use_elastic_ips': 0,
				'aws.elastic_ips.map': ''
			};
		},

		beforeShowTab: function (record, handler) {
			var cloudLocation = record.get('cloud_location'), farmRoleId = record.get('farm_role_id');

			if (this.cacheExist(['eips', farmRoleId]))
				handler();
			else
				Scalr.Request({
					processBox: {
						type: 'action'
					},
					url: '/platforms/ec2/xGetFarmRoleElasicIps',
					params: {
						cloudLocation: cloudLocation,
						farmRoleId: farmRoleId
					},
					scope: this,
					success: function (response) {
						response.data['ips'].unshift({
							ipAddress: '0.0.0.0'
						});
						this.cacheSet(response.data, ['eips', farmRoleId]);
						handler();
					},
					failure: function () {
						this.deactivateTab();
					}
				});
		},

		showTab: function (record) {
			var settings = record.get('settings'),
				farmRoleId = record.get('farm_role_id'),
				ct = this.down('[name="aws.use_elastic_ips"]'),
				data = this.cacheGet(['eips', farmRoleId]);

			if (data.map.length > settings['scaling.max_instances']) {
				data.map.splice(settings['scaling.max_instances'], data.map.length - settings['scaling.max_instances']);
			} else if (data.map.length < settings['scaling.max_instances']) {
				for (var i = data.map.length; i < settings['scaling.max_instances']; i++)
					data.map.push({ serverIndex: i + 1 });
			}

			this.down('[name="aws.elastic_ips.map"]').store.load({ data: data.map });
			this.down('[name="aws.elastic_ips.map"]')['ipAddressEditorIps'] = data['ips'];
			this.down('[name="aws.elastic_ips.warning"]').hide();

			if (settings['aws.use_elastic_ips'] == 1) {
				ct.expand();
			} else {
				this.down('[name="aws.use_elastic_ips"]').collapse();
			}
		},

		hideTab: function (record) {
			var settings = record.get('settings'),
				farmRoleId = record.get('farm_role_id'),
				ct = this.down('[name="aws.use_elastic_ips"]'),
				data = this.cacheGet(['eips', farmRoleId]);

			Ext.each(this.down('[name="aws.elastic_ips.map"]').store.getModifiedRecords(), function(record) {
				data.map[record.index] = record.data;
			});
			this.cacheSet(data, ['eips', farmRoleId]);

			if (! this.down('[name="aws.use_elastic_ips"]').collapsed) {
				settings['aws.use_elastic_ips'] = 1;
				settings['aws.elastic_ips.map'] = '';
				this.down('[name="aws.elastic_ips.map"]').store.each(function(record) {
					settings['aws.elastic_ips.map'] += record.get('serverIndex') + '=' + record.get('elasticIp') + ';';
				});
			} else {
				settings['aws.use_elastic_ips'] = 0;
			}

			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
			title: 'Assign one ElasticIP per instance',
			name: 'aws.use_elastic_ips',
			checkboxToggle: true,
			collapsed: true,
			items: [{
				xtype: 'displayfield',
				fieldCls: 'x-form-field-info',
				anchor: '100%',
				value:   'Enable to have Scalr automatically assign an ElasticIP to each instance of this role ' +
					'(this requires a few minutes during which the instance is unreachable from the public internet) ' +
					'after HostInit but before HostUp. If out of allocated IPs, Scalr will request more, but never remove any.'
			}, {
				xtype: 'displayfield',
				fieldCls: 'x-form-field-warning',
				anchor: '100%',
				name: 'aws.elastic_ips.warning',
				hidden: true,
				value: ''
			}, {
				xtype: 'grid',
				name: 'aws.elastic_ips.map',
				plugins: [{
					ptype: 'cellediting',
					clicksToEdit: 1,
					listeners: {
						beforeedit: function(comp, e) {
							var editor = this.getEditor(e.record, e.column);
							for (var i = 0, len = e.grid['ipAddressEditorIps'].length; i < len; i++) {
								e.grid['ipAddressEditorIps'][i]['fieldInstanceId'] = e.record.get('instanceId') && (e.grid['ipAddressEditorIps'][i]['instanceId'] == e.record.get('instanceId'));
							}
							editor.field.store.load({ data: e.grid['ipAddressEditorIps'] });
						},
						edit: function(comp, e) {
							if (e.value == null) {
								e.record.set('elasticIp', '');
							}

							if (e.record.get('elasticIp')) {
								var editor = this.getEditor(e.record, e.column);
								var r = editor.field.store.findRecord('ipAddress', e.record.get('elasticIp'));
								if (r && r.get('instanceId') && r.get('instanceId') != e.record.get('instanceId') && r.get('ipAddress') != e.record.get('remoteIp'))
									e.grid.up('[tab="tab"]').down('[name="aws.elastic_ips.warning"]').setValue(
										'IP address \'' + e.record.get('elasticIp') + '\' is already in use, and will be re-associated with selected server. IP address on old server will revert to dynamic IP.'
									).show();
								else
									e.grid.up('[tab="tab"]').down('[name="aws.elastic_ips.warning"]').hide();
							}
						}
					}
				}],
				viewConfig: {
					disableSelection: true
				},
				store: {
					proxy: 'object',
					fields: [ 'elasticIp', 'instanceId', 'serverId', 'serverIndex', 'remoteIp', 'warningInstanceIdDoesntMatch' ]
				},
				columns: [
					{ header: 'Server Index', width: 120, sortable: true, dataIndex: 'serverIndex' },
					{ header: 'Server ID', width: 360, sortable: true, dataIndex: 'serverId', xtype: 'templatecolumn', tpl:
						'<tpl if="serverId"><a href="#/servers/{serverId}/extendedInfo">{serverId}</a> <tpl if="instanceId">({instanceId})</tpl><tpl else>Not running</tpl>'
					}, {
						header: 'Elastic IP', width: 250, sortable: true, dataIndex: 'elasticIp', editable: true, tdCls: 'x-grid-cell-editable',
						renderer: function(value, metadata, record) {
							metadata.tdAttr = 'title="Click here to change"';
							metadata.style = 'line-height: 16px; padding-top: 4px; padding-bottom: 2px';

							if (value == '0.0.0.0')
								value = 'Allocate new';
							else if (!value)
								value = 'Not allocated yet';

							value = '<span style="float: left">' + value + '</span>';

							if (record.get('warningInstanceIdDoesntMatch'))
								value += '<div style="margin-left: 5px; float: left; height: 15px; width: 16px; background-image: url(/ui2/images/icons/warning_icon_16x16.png)" title="This IP address is out of sync and associated with another instance on EC2">&nbsp;</div>'

							return value;
						},
						editor: {
							xtype: 'combobox',
							forceSelection: true,
							editable: false,
							displayField: 'ipAddress',
							valueField: 'ipAddress',
							matchFieldWidth: false,
							store: {
								proxy: 'object',
								fields: ['ipAddress', 'instanceId', 'farmName' , 'roleName', 'serverIndex', 'fieldInstanceId']
							},
							displayTpl: '<tpl for="."><tpl if="values.ipAddress == \'0.0.0.0\'">Allocate new<tpl else>{[values.ipAddress]}</tpl></tpl>',
							listConfig: {
								minWidth: 250,
								cls: 'x-boundlist-alt',
								tpl: '<tpl for="."><div class="x-boundlist-item" style="font: bold 13px arial; height: auto; padding: 5px;">' +
										'<tpl if="ipAddress == \'0.0.0.0\'"><span>Allocate new</span>' +
										'<tpl elseif="ipAddress != \'\'">' +
											'<tpl if="!fieldInstanceId">' +
												'<tpl if="farmName"><span style="color: #F90000">{ipAddress}</span>' +
												'<tpl else><span style="color: #138913">{ipAddress}</span> (free)</tpl>' +
											'<tpl else><span>{ipAddress}</span></tpl>' +
										'<tpl else>Not allocated yet</tpl>' +
										'<tpl if="ipAddress && farmName"><br /><span style="font-weight: normal">used by: {farmName} &rarr; {roleName} # {serverIndex}</span></tpl>' +
									'</div></tpl>'
							}
						}
					}
				]
			}]
		}]
	});
});

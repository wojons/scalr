Scalr.regPage('Scalr.ui.farms.builder.tabs.cloudstack', function (moduleParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Cloudstack settings',
        itemId: 'cloudstack',
        
        settings: {
            'cloudstack.network_id': '',
            'cloudstack.shared_ip.id': undefined,
            'cloudstack.shared_ip.address': undefined,
            'cloudstack.use_static_nat': 0,
            'cloudstack.static_nat.map': ''
        },
        
        tabData: null,
        
        getTitle: function(record){
            return moduleParams.platforms[record.get('platform')].name + ' settings';
        },
        
		isEnabled: function (record) {
			return  Ext.Array.contains(['cloudstack', 'idcf', 'ucloud'], record.get('platform'));
		},

        beforeShowTab: function(record, handler) {
            Scalr.CachedRequestManager.get('farmbuilder').load(
                {
                    url: '/platforms/cloudstack/xGetOfferingsList/',
                    params: {
                        cloudLocation: record.get('cloud_location'),
                        platform: record.get('platform'),
						farmRoleId: record.get('new') ? '' : record.get('farm_role_id')
                    }
                },
                function(data, status){
                    this.tabData = data;
                    status ? handler() : this.deactivateTab();
                },
                this
            );
        },

		showTab: function (record) {
            var me = this,
                settings = record.get('settings'),
                eipsData = this.tabData['eips'],
                eipsFieldset = this.down('[name="cloudstack.use_static_nat"]'),
                field, defaultValue;
        
            Ext.Object.each({
                'cloudstack.network_id': 'networks',
                'cloudstack.shared_ip.id': 'ipAddresses'
            }, function(fieldName, dataFieldName){
                var storeData,
                    cloudLocation = record.get('cloud_location'),
                    limits = me.up('#farmbuilder').getLimits(record.get('platform') + fieldName.replace('cloudstack', ''));
                if (limits && limits.value && limits.value[cloudLocation] && limits.value[cloudLocation].length > 0) {
                    storeData = [];
                    Ext.Array.each(me.tabData ? me.tabData[dataFieldName] : [], function(item) {
                        if (Ext.Array.contains(limits.value[cloudLocation], item.id)) {
                            storeData.push(item);
                        }
                    });
                } else {
                    storeData = me.tabData ? me.tabData[dataFieldName] : [];
                }

                defaultValue = null
                field = me.down('[name="' + fieldName + '"]');
                field.store.load({ data: storeData });
                if (field.store.getCount() == 0) {
                    field.hide();
                } else {
                    defaultValue = field.store.getAt(0).get('id');
                    field.show();
                }
                field.setValue(!Ext.isEmpty(settings[fieldName]) ? settings[fieldName] : defaultValue);
                field[limits?'addCls':'removeCls']('x-field-governance');
            });
            
            //static nat
            if (eipsData) {
                var maxInstances = settings['scaling.enabled'] == 1 ? settings['scaling.max_instances'] : record.get('running_servers');
                if (eipsData.map.length > maxInstances) {
                    var removeIndex = maxInstances;
                    for (var i=eipsData.map.length-1; i>=0; i--) {
                        removeIndex = i + 1;
                        if (eipsData.map[i]['serverId'] || removeIndex == maxInstances) {
                            break;
                        }
                    }
                    eipsData.map.splice(removeIndex, eipsData.map.length - removeIndex);
                } else if (eipsData.map.length < maxInstances) {
                    for (var i = eipsData.map.length; i < maxInstances; i++)
                        eipsData.map.push({ serverIndex: i + 1 });
                }

                field = this.down('[name="cloudstack.static_nat.map"]');
                field.store.load({ data: eipsData.map });
                field['ipAddressEditorIps'] = Ext.Array.merge(eipsData['ips'], [{ipAddress: '0.0.0.0'}]);
                this.down('[name="cloudstack.static_nat.warning"]').hide();

                eipsFieldset[settings['cloudstack.use_static_nat'] == 1 ? 'expand' : 'collapse']();
                eipsFieldset.show();
            } else {
                eipsFieldset.hide();
            }
        },

		hideTab: function (record) {
			var me = this,
                settings = record.get('settings'),
                eipsFieldset = this.down('[name="cloudstack.use_static_nat"]');
			settings['cloudstack.network_id'] = this.down('[name="cloudstack.network_id"]').getValue();
			
			settings['cloudstack.shared_ip.id'] = this.down('[name="cloudstack.shared_ip.id"]').getValue();
			if (settings['cloudstack.shared_ip.id']) {
				var r = this.down('[name="cloudstack.shared_ip.id"]').findRecordByValue(settings['cloudstack.shared_ip.id']);
				settings['cloudstack.shared_ip.address'] = r.get('name');
			} else {
				settings['cloudstack.shared_ip.address'] = "";
			}
			
            //static nat
			Ext.each(this.down('[name="cloudstack.static_nat.map"]').store.getModifiedRecords(), function(record) {
				me.tabData['eips'].map[record.index] = record.data;
			});

            if (eipsFieldset.isVisible()) {
                if (!eipsFieldset.collapsed) {
                    settings['cloudstack.use_static_nat'] = 1;
                    settings['cloudstack.static_nat.map'] = '';
                    this.down('[name="cloudstack.static_nat.map"]').store.each(function(record) {
                        settings['cloudstack.static_nat.map'] += record.get('serverIndex') + '=' + record.get('elasticIp') + ';';
                    });
                } else {
                    settings['cloudstack.use_static_nat'] = 0;
                }
            }
            
			record.set('settings', settings);
		},

		items: [{
			xtype: 'fieldset',
            layout: 'anchor',
			defaults: {
				labelWidth: 150,
				maxWidth: 650,
                anchor: '100%'
			},
			items: [{
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
                governance: true,
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
		}, {
			xtype: 'fieldset',
			title: 'Static NAT',
			name: 'cloudstack.use_static_nat',
			checkboxToggle: true,
            toggleOnTitleClick: true,
            collapsible: true,
			collapsed: true,
			defaults: {
                maxWidth: 1020
            },
			items: [{
				xtype: 'displayfield',
				cls: 'x-form-field-info',
                hidden: true,
				value:   'Enable to have Scalr automatically assign an ElasticIP to each instance of this role ' +
					'(this requires a few minutes during which the instance is unreachable from the public internet) ' +
					'after HostInit but before HostUp. If out of allocated IPs, Scalr will request more, but never remove any.'
			}, {
				xtype: 'displayfield',
				cls: 'x-form-field-warning',
				anchor: '100%',
				name: 'cloudstack.static_nat.warning',
				hidden: true,
				value: ''
			}, {
				xtype: 'grid',
                cls: 'x-grid-shadow',
				name: 'cloudstack.static_nat.map',
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
									e.grid.up('[tab="tab"]').down('[name="cloudstack.static_nat.warning"]').setValue(
										'IP address \'' + e.record.get('elasticIp') + '\' is already in use, and will be re-associated with selected server. IP address on old server will revert to dynamic IP.'
									).show();
								else
									e.grid.up('[tab="tab"]').down('[name="cloudstack.static_nat.warning"]').hide();
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
					{ header: 'Server ID', flex: 1, sortable: true, dataIndex: 'serverId', xtype: 'templatecolumn', tpl:
						'<tpl if="serverId"><a href="#/servers/{serverId}/dashboard">{serverId}</a> <tpl if="instanceId">({instanceId})</tpl><tpl else>Not running</tpl>'
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
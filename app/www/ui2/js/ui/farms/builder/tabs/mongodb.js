Scalr.regPage('Scalr.ui.farms.builder.tabs.mongodb', function (moduleParams) {
    var iopsMin = 100, 
        iopsMax = 4000, 
        integerRe = new RegExp('[0123456789]', 'i'), 
        maxEbsStorageSize = 1000;
        
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'MongoDB settings',
		itemId: 'mongodb',
        tabData: null,
        
        settings: {
            'mongodb.data_storage.engine': function(record){return record.getMongoDefaultStorageEngine()},
            'mongodb.data_storage.ebs.size': 10,
            'mongodb.data_storage.ebs.type': undefined,
            'mongodb.data_storage.ebs.iops': undefined,
            'mongodb.mms.api_key': undefined,
            'mongodb.mms.secret_key': undefined,
            'mongodb.data_storage.raid.level': undefined,
            'mongodb.data_storage.raid.volume_size': undefined,
            'mongodb.data_storage.raid.volumes_count': undefined,
            'mongodb.ssl.enabled': undefined,
            'mongodb.ssl.cert_id': undefined
        },
        
		isEnabled: function (record) {
			return record.get('behaviors').match('mongodb');
		},
        
		showTab: function (record) {
			var settings = record.get('settings', true),
                platform = record.get('platform', true),
                storages = [];
			
			if (platform === 'ec2') {
                storages.push({name:'ebs', description:'EBS Volume'});
                storages.push({name:'raid.ebs', description:'RAID array'});
			} else if (platform === 'rackspace') {
                storages.push({name:'eph', description:'Ephemeral device'});
			} else if (platform === 'cloudstack' || platform == 'idcf') {
				storages.push({name:'csvol', description:'Cloudstack Block Device'});
			} else if (platform === 'gce') {
                storages.push({name:'gce_persistent', description:'GCE persistent disk'});
			} else if (Scalr.isOpenstack(platform)) {
				storages.push({name:'cinder', description:'Cinder Block Device'});
			}
            
            this.down('[name="mongodb.data_storage.engine"]').store.load({data: storages});
			
			if (settings['mongodb.data_storage.engine'] == 'ebs' || 
				settings['mongodb.data_storage.engine'] == 'csvol' || 
				settings['mongodb.data_storage.engine'] == 'cinder' || 
				settings['mongodb.data_storage.engine'] == 'gce_persistent') {
				this.down('[name="mongodb.data_storage.ebs.size"]').setValue(settings['mongodb.data_storage.ebs.size']);
			}
			
			if (settings['mongodb.data_storage.engine'] == 'ebs') {
				this.down('[name="mongodb.data_storage.ebs.type"]').setValue(settings['mongodb.data_storage.ebs.type'] || 'standard');
                this.down('[name="mongodb.data_storage.ebs.iops"]').setValue(settings['mongodb.data_storage.ebs.iops'] || 50);
				
				if (this.down('[name="mongodb.data_storage.ebs.type"]').getValue() == 'io1')
                    this.down('[name="mongodb.data_storage.ebs.iops"]').show();
                else
                    this.down('[name="mongodb.data_storage.ebs.iops"]').hide();
			}

            var notANewRecord = !record.get('new');
            this.down('[name="mongodb.data_storage.raid.level"]').setDisabled(notANewRecord)
            this.down('[name="mongodb.data_storage.raid.volumes_count"]').setDisabled(notANewRecord);
            this.down('[name="mongodb.data_storage.raid.volume_size"]').setDisabled(notANewRecord);

            this.down('[name="mongodb.data_storage.engine"]').setDisabled(notANewRecord);
            this.down('[name="mongodb.data_storage.ebs.size"]').setDisabled(notANewRecord);
            this.down('[name="mongodb.data_storage.ebs.iops"]').setDisabled(notANewRecord);
            this.down('[name="mongodb.data_storage.ebs.type"]').setDisabled(notANewRecord);
            
			this.down('[name="mongodb.data_storage.engine"]').setValue(settings['mongodb.data_storage.engine']);
			
			this.down('[name="mongodb.mms.api_key"]').setValue(settings['mongodb.mms.api_key']);
			this.down('[name="mongodb.mms.secret_key"]').setValue(settings['mongodb.mms.secret_key']);
			
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
            
            //ssl
            var sslFieldset = this.down('[name="mongodb.ssl.enabled"]'),
                sslCertIdField = this.down('[name="mongodb.ssl.cert_id"]');
            sslFieldset[settings['mongodb.ssl.enabled'] == 1 ? 'expand' : 'collapse']();
            if (settings['mongodb.ssl.cert_id']) {
                sslCertIdField.store.load();
            }
            sslCertIdField.setValue(settings['mongodb.ssl.cert_id']);
            
            if (sslFieldset.rendered) {
                sslFieldset.show();
                sslFieldset[(moduleParams.farm.status || 0) > 0 ? 'mask' : 'unmask']();
            } else {
                sslFieldset.setVisible((moduleParams.farm.status || 0) == 0);
            }
            this.down('#mongo_ssl_warning').setVisible((moduleParams.farm.status || 0) > 0);
		},

		hideTab: function (record) {
			var settings = record.get('settings');

			settings['mongodb.data_storage.engine'] = this.down('[name="mongodb.data_storage.engine"]').getValue();
			
			if (settings['mongodb.data_storage.engine'] == 'ebs' || settings['mongodb.data_storage.engine'] == 'csvol' || settings['mongodb.data_storage.engine'] == 'cinder' || settings['mongodb.data_storage.engine'] == 'gce_persistent') {
				if (record.get('new')) {
				    settings['mongodb.data_storage.ebs.size'] = this.down('[name="mongodb.data_storage.ebs.size"]').getValue();
				    
					if (settings['mongodb.data_storage.engine'] == 'ebs') {
						settings['mongodb.data_storage.ebs.type'] = this.down('[name="mongodb.data_storage.ebs.type"]').getValue();
						settings['mongodb.data_storage.ebs.iops'] = this.down('[name="mongodb.data_storage.ebs.iops"]').getValue();
					}
				}
			}
			else {
				delete settings['mongodb.data_storage.ebs.size'];
			}

            settings['mongodb.mms.api_key'] = this.down('[name="mongodb.mms.api_key"]').getValue();
			settings['mongodb.mms.secret_key'] = this.down('[name="mongodb.mms.secret_key"]').getValue();

			if (settings['mongodb.data_storage.engine'] == 'raid.ebs') {				
				settings['mongodb.data_storage.raid.level'] = this.down('[name="mongodb.data_storage.raid.level"]').getValue();
				settings['mongodb.data_storage.raid.volume_size'] = this.down('[name="mongodb.data_storage.raid.volume_size"]').getValue();
				settings['mongodb.data_storage.raid.volumes_count'] = this.down('[name="mongodb.data_storage.raid.volumes_count"]').getValue();
			}
			
            
            var sslEnabled = !this.down('[name="mongodb.ssl.enabled"]').collapsed, 
                sslCertId = this.down('[name="mongodb.ssl.cert_id"]').getValue();
            if (sslEnabled && sslCertId) {
                settings['mongodb.ssl.enabled'] = 1;
                settings['mongodb.ssl.cert_id'] = sslCertId;
            } else {
                settings['mongodb.ssl.enabled'] = 0;
                settings['mongodb.ssl.cert_id'] = '';
            }
            
			record.set('settings', settings);
		},

		items: [{
			xtype: 'displayfield',
            itemId: 'mongo_ssl_warning',
            hidden: true,
            anchor: '100%',
			cls: 'x-form-field-info',
			value: 'SSL settings can be changed on TERMINATED farm only.'
        },{
			xtype: 'fieldset',
			title: 'Mongo over SSL',
			name: 'mongodb.ssl.enabled',
            toggleOnTitleClick: true,
			checkboxToggle: true,
			collapsed: true,
            collapsible: true,
            layout: 'anchor',
			items: [{
                xtype: 'combo',
                name: 'mongodb.ssl.cert_id',
                fieldLabel: 'SSL certificate',
                labelWidth: 160,
                width: 500,
                emptyText: 'Choose certificate',
                valueField: 'id',
                displayField: 'name',
                allowBlank: false,

                forceSelection: true,
                queryCaching: false,
                minChars: 0,
                queryDelay: 10,
                store: {
                    fields: [ 'id', 'name' ],
                    proxy: {
                        type: 'cachedrequest',
                        crscope: 'farmbuilder',
                        url: '/services/ssl/certificates/xListCertificates',
                        filterFields: ['name']
                    }
                },
                plugins: [{
                    ptype: 'comboaddnew',
                    url: '/services/ssl/certificates/create',
                    disabled: !Scalr.isAllowed('SERVICES_SSL')
                }],
                listeners: {
                    addnew: function(item) {
                        Scalr.CachedRequestManager.get('farmbuilder').setExpired({url: '/services/ssl/certificates/xListCertificates'});
                    }
                }
            }]
        },{
            xtype: 'fieldset',
            title: 'mms.10gen.com integration',
            hidden: !Scalr.flags['betaMode'],
            items: [{
                xtype: 'textfield',
                fieldLabel: 'API key',
                labelWidth: 160,
                width: 500,
                name: 'mongodb.mms.api_key'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Secret key',
                labelWidth: 160,
                width: 500,
                name: 'mongodb.mms.secret_key'
            }]
        }, {
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
                        var ct = this.up('#mongodb'),
                            engine = this.getValue();
						ct.down('#ebs_settings').hide();
						ct.down('#raid_settings').hide();
						
						if (Ext.Array.contains(['ebs', 'csvol', 'cinder', 'gce_persistent'], engine)) {
							ct.down('#ebs_settings').show();
                            ct.down('#ebs_type').setVisible(engine !== 'gce_persistent');
						} else if (engine == 'raid.ebs') {
							ct.down('#raid_settings').show();
						}
					}
				}
			},{
                xtype: 'container',
                itemId: 'ebs_settings',
                hidden: true,
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    itemId: 'ebs_type',
                    width: 600,
                    margin: '0 0 6',
                    items: [{
                        xtype: 'combo',
                        store: [['standard', 'Standard EBS (Magnetic)'],['gp2', 'General Purpose (SSD)'],['io1', 'Provisioned IOPS (' + iopsMin + ' - ' + iopsMax + '): ']],
                        fieldLabel: 'EBS type',
                        labelWidth:160,
                        valueField: 'id',
                        displayField: 'name',
                        editable: false,
                        queryMode: 'local',
                        value: 'standard',
                        name: 'mongodb.data_storage.ebs.type',
                        width: 400,
                        listeners: {
                            change: function (comp, value) {
                                var tab = comp.up('#mongodb'),
                                    iopsField = comp.next();
                                iopsField.setVisible(value === 'io1');
                                if (tab.currentRole.get('new')) {
                                    if (value === 'io1') {
                                        iopsField.reset();
                                        iopsField.setValue(100);
                                    } else {
                                        tab.down('[name="mongodb.data_storage.ebs.size"]').isValid();
                                    }
                                }
                            }
                        }
                    }, {
                        xtype: 'textfield',
                        itemId: 'mongodb.data_storage.ebs.iops',
                        name: 'mongodb.data_storage.ebs.iops',
                        hidden: true,
                        margin: '0 0 0 6',
                        width: 60,
                        maskRe: integerRe,
                        validator: function(value){
                            if (value*1 > iopsMax) {
                                return 'Maximum value is ' + iopsMax + '.';
                            } else if (value*1 < iopsMin) {
                                return 'Minimum value is ' + iopsMin + '.';
                            }
                            return true;
                        },
                        allowBlank: false,
                        listeners: {
                            change: function(comp, value){
                                var tab = comp.up('#mongodb'),
                                    sizeField = tab.down('[name="mongodb.data_storage.ebs.size"]');
                                if (tab.currentRole.get('new')) {
                                    if (comp.isValid() && comp.prev().getValue() === 'io1') {
                                        var minSize = Math.ceil(value*1/10);
                                        if (sizeField.getValue()*1 < minSize) {
                                            sizeField.setValue(minSize);
                                        }
                                    }
                                }
                            }
                        }
                    }]
                }, {
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Storage size',
                        labelWidth: 160,
                        width: 220,
                        name: 'mongodb.data_storage.ebs.size',
                        maskRe: integerRe,
                        value: 10,
                        validator: function(value){
                            var minValue = 10,
                                tab = this.up('#mongodb');
                            if (tab.down('[name="mongodb.data_storage.ebs.type"]').getValue() === 'io1') {
                                minValue = Math.ceil(tab.down('[name="mongodb.data_storage.ebs.iops"]').getValue()*1/10);
                            }
                            if (value*1 > maxEbsStorageSize) {
                                return 'Maximum value is ' + maxEbsStorageSize + '.';
                            } else if (value*1 < minValue) {
                                return 'Minimum value is ' + minValue + '.';
                            }
                            return true;
                        }
                    },{
                        xtype: 'label',
                        text: 'GB',
                        margin: '0 0 0 6'
                    }]
                }]
            }, {
                xtype: 'container',
                itemId: 'raid_settings',
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
                    width: 220,
                    value: '10',
                    maskRe: integerRe,
                    allowBlank: false,
                    name: 'mongodb.data_storage.raid.volume_size'
                }]
            }]
		}]
	});
});

Scalr.regPage('Scalr.ui.tools.aws.ec2.ebs.volumes.create', function (loadParams, moduleParams) {
    var mustAttachVolume = Scalr.utils.getAclResourceMode('AWS_VOLUMES') == 'MANAGED_FARMS';
	loadParams['size'] = loadParams['size'] || 1;
	return Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'Tools &raquo; Amazon Web Services &raquo; EBS &raquo; Volumes &raquo; Create',
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 150
		},

		items: [{
			xtype: 'fieldset',
			title: 'Placement',
			items: [{
				fieldLabel: 'Cloud location',
				xtype: 'combo',
				allowBlank: false,
				editable: false,
				plugins: {
                	ptype: 'fieldinnericoncloud',
                	platform: 'ec2'
                },
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
				displayField: 'name',
				valueField: 'id',
				queryMode: 'local',
				name: 'cloudLocation',
				width: 200,
				listeners: {
					change: function (comp, value){
                        var kmsKeys = ((Scalr.getGovernance('ec2', 'aws.kms_keys') || {})[value] || {})['keys'],
                            field;
                        field = this.next('[name="availabilityZone"]');
						field.store.proxy.extraParams.cloudLocation = value;
						field.setValue();
						field.store.load();

                        field = this.up('form').down('[name="kmsKeyId"]');
                        field.store.proxy.params.cloudLocation = value;
                        field.store.proxy.data = kmsKeys;
                        field.toggleIcon('governance', kmsKeys !== undefined);
                        field.reset();

					},
					render: function () {
						this.setValue(loadParams['cloudLocation'] || this.store.getAt(0).get('id'));
					}
				}
			}, {
				fieldLabel:'Availability zone',
				xtype: 'combo',
				store: {
					fields: [ 'id', 'name' ],
					proxy: {
						type: 'ajax',
						url: '/platforms/ec2/xGetAvailZones',
						reader: {
							type: 'json',
							rootProperty: 'data'
						}
					}
				},
				valueField: 'id',
				displayField: 'name',
				editable: false,
				name: 'availabilityZone',
                allowBlank: false,
				width: 200,
                listeners: {
                    change: function(comp, value) {
                        var serverIdField = comp.up('form').down('[name="serverId"]');
                        serverIdField.reset();
                        if (value) {
                            serverIdField.store.proxy.params = {availabilityZone: value};
                        }
                    }
                }
			}]
		}, {
			xtype: 'fieldset',
			title: 'Volume information',
			items: [{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                items: [{
                    xtype: 'combo',
                    store: Scalr.constants.ebsTypes,
                    fieldLabel: 'Type',
                    valueField: 'id',
                    displayField: 'name',
                    editable: false,
                    queryMode: 'local',
                    name: 'type',
                    width: 400,
                    value: 'standard',
                    listeners: {
                        change: function (comp, value) {
                            var form = comp.up('form'),
                                iopsField = form.down('[name="iops"]');
                            iopsField.setVisible(value === 'io1').setDisabled(value !== 'io1');
                            if (value === 'io1') {
                                iopsField.reset();
                                iopsField.setValue(100);
                            } else {
                                form.down('[name="size"]').isValid();
                            }
                        }
                    }
                }, {
                    xtype: 'textfield',
                    name: 'iops',
                    vtype: 'iops',
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    margin: '0 0 0 6',
                    width: 60,
                    value: 100,
                    listeners: {
                        change: function(comp, value){
                            var form = comp.up('form'),
                                sizeField = form.down('[name="size"]');
                            if (comp.isValid() && comp.prev().getValue() === 'io1') {
                                var minSize = Scalr.utils.getMinStorageSizeByIops(value);
                                if (sizeField.getValue()*1 < minSize) {
                                    sizeField.setValue(minSize);
                                }
                            }
                        }
                    }

                }]
            },{
				xtype:'fieldcontainer',
				fieldLabel: 'Size',
				layout: 'hbox',
				items:[{
					xtype: 'textfield',
					name: 'size',
					value: loadParams['size'],
                    vtype: 'ebssize',
                    getEbsType: function() {
                        return this.up('form').down('[name="type"]').getValue();
                    },
                    getEbsIops: function() {
                        return this.up('form').down('[name="iops"]').getValue();
                    },
					validator: function (value) {
						if (loadParams['snapshotId'] && value < loadParams['size'])
							return "Volume size should be equal or greater than snapshot size (" + loadParams['size'] + 'GB)';
						else
							return true;
					},
					width: 100
				}, {
					xtype: 'displayfield',
					value: 'GB',
					padding: '0 0 0 5'
				}]
			}, {
				xtype: 'textfield',
				fieldLabel: 'Snapshot',
				readOnly: true,
				hidden: !(loadParams['snapshotId']),
				name: 'snapshotId',
				value: loadParams['snapshotId'] || ''
			},{
                xtype: 'checkbox',
                name: 'encrypted',
                hidden: !!loadParams['snapshotId'],
                disabled: !!loadParams['snapshotId'],
                boxLabel: 'Enable EBS encryption',
                value: '0',
                listeners: {
                    change: function(comp, value) {
                        comp.next('[name="kmsKeyId"]').setVisible(value == 1).reset();
                    }
                }
            },{
                xtype: 'combo',
                name: 'kmsKeyId',
                fieldLabel: 'KMS key',
                valueField: 'id',
                displayField: 'displayField',
                emptyText: 'Default key (aws/ebs)',
                anchor: '100%',
                matchFieldWidth: true,
                hidden: true,
                disabled: !!loadParams['snapshotId'],

                queryCaching: false,
                minChars: 0,
                queryDelay: 10,
                autoSearch: false,
                editable: false,
                plugins: {
                    ptype: 'fieldicons',
                    position: 'outer',
                    icons: [
                        {id: 'governance'}
                    ]
                },
                store: {
                    fields: [ 'id', 'alias', {name: 'displayField', convert: function(v, record){return record.data.alias ? record.data.alias.replace('alias/', ''):''}} ],
                    proxy: {
                        type: 'cachedrequest',
                        url: '/platforms/ec2/xGetKmsKeysList',
                        root: 'keys',
                        filterFn: function(record) {
                            return !Ext.Array.contains(['alias/aws/rds', 'alias/aws/redshift', 'alias/aws/s3'], record.get('alias'))
                        },
                        params: {}
                    },
                    sorters: {
                        property: 'alias',
                        transform: function(value){
                            return value ? value.toLowerCase() : value;
                        }
                    }
                }
            }]
		},{
			xtype: 'fieldset',
			title: 'Attach volume' + (mustAttachVolume ? '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="Your permissions do not allow you to create EBS volume without attaching it to the Server." class="x-icon-question" />' : ''),
            checkboxToggle: true,
            collapsible: true,
			collapsed: true,
            toggleOnTitleClick: true,
            listeners: {
                boxready: function() {
                    this.checkboxCmp.submitValue = false;
                    if (mustAttachVolume) this.expand();
                    this.checkboxCmp.setDisabled(mustAttachVolume);
                },
                beforecollapse: function() {
                    return !this.checkboxCmp.disabled;
                },
                expand: function() {
                    this.items.each(function(item){
                        if (item.name !== 'mountPoint') item.enable();
                    });
                },
                collapse: function() {
                    this.items.each(function(item){
                        if (item.name !== 'mountPoint') item.disable();
                    });
                }
            },
            defaults: {
                disabled: true
            },
			items: [{
				xtype: 'combo',
				fieldLabel: 'Server',
				name: 'serverId',
				displayField: 'name',
				valueField: 'id',

                queryCaching: false,
                minChars: 0,
                queryDelay: 10,
                autoSearch: false,
                allowBlank: false,
                restoreValueOnBlur: true,
                selectSingleRecordOnPartialMatch: true,
                cls: 'x-boundlist-alt',
                tpl:
                    '<tpl for=".">' +
                        '<div class="x-boundlist-item" style="height: auto; width: auto">' +
                            '<div class="x-semibold">{name}</div>' +
                            '<tpl if="farmName && farmRoleName">' +
                                '<div <div style="line-height: 28px;">{farmName} &rarr; {farmRoleName}</div>' +
                            '</tpl>' +
                        '</div>' +
                    '</tpl>',
                store: {
                    fields: [ 'id', 'name', 'farmName', 'farmRoleName'],
                    proxy: {
                        type: 'cachedrequest',
                        url: '/tools/aws/ec2/ebs/volumes/xGetServersList',
                        root: 'servers',
                        filterFields: ['name', 'farmName', 'farmRoleName'],
                        ttl: 1,
                        reader: {
                            type: 'object'
                        },
                        params: {}
                    }
                },
                updateEmptyText: function(type){
                    var zone;
                    if (!type) {
                        zone = this.store.proxy.params.availabilityZone;
                    }
                    this.emptyText =  type ? 'Please select server' : 'No running servers found in ' + zone;
                    this.applyEmptyText();
                },
                listeners: {
                    afterrender: function(){
                        var me = this;
                        me.updateEmptyText(true);
                        me.store.on('load', function(store, records, result){
                            me.updateEmptyText(records && records.length > 0);
                        });
                    },
                    change: function(comp, value) {
                        if (!value) comp.updateEmptyText(true);
                    },
                    beforequery: function() {
                        var availZoneField = this.up('form').down('[name="availabilityZone"]');
                        if (!availZoneField.getValue()) {
                            this.collapse();
                            availZoneField.isValid();
                            Scalr.message.InfoTip('Select Availability Zone  first.', availZoneField.inputEl, {anchor: 'bottom'});
                            return false;
                        }
                    }
                }
			}, {
                xtype: 'checkbox',
                name: 'attachOnBoot',
                boxLabel: 'Always attach this volume to selected server',
                listeners: {
                    change: function(comp, status) {
                        comp.next().setVisible(!!status).setDisabled(!status);
                    }
                }
            },{
				xtype: 'fieldcontainer',
				layout: 'hbox',
                hidden: true,
                disabled: true,
				items: [{
					xtype: 'checkbox',
					name: 'mount',
					inputValue: 1,
                    boxLabel: 'Automatically mount this volume after attach to ',
                    listeners: {
                        change: function(comp, status) {
                            comp.next().setDisabled(!status);
                        }
                    }
				}, {
					xtype: 'textfield',
					name: 'mountPoint',
                    disabled: true,
					margin: '0 0 0 3',
					value: '/mnt/storage'
				}]
            }]
        }],

		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Create',
				handler: function() {
                    var form = this.up('form');
                    if (form.isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: form.getForm(),
                            scope: this,
                            url: '/tools/aws/ec2/ebs/volumes/xCreate',
                            success: function (data) {
                                Scalr.event.fireEvent('redirect',
                                    '#/tools/aws/ec2/ebs/volumes/' + data.data.volumeId + '/view?cloudLocation=' +
                                    this.up('form').down('[name="cloudLocation"]').getValue()
                                );
                            }
                        });
                    }
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});
});

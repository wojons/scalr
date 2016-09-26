Scalr.regPage('Scalr.ui.farms.builder.addrole.dbmsr', function () {
    return {
        xtype: 'container',
        itemId: 'dbmsr',
        isExtraSettings: true,
        hidden: true,
        suspendUpdateEvent: 0,

        layout: 'anchor',

        isVisibleForRole: function(record) {
            return record.isDbMsr();
        },

        onSettingsUpdate: function(record, name, value) {
            var me = this,
                platform = record.get('platform');
            me.suspendUpdateEvent++;
            if (name === 'instance_type' && Ext.Array.contains(['ec2', 'gce'], platform)) {
                me.refreshStorageEngine(record);
                me.refreshStorageDisks(record);
                me.refreshDisksCheckboxes(record, 'lvm_settings');
                if (platform === 'ec2') {
                    me.refreshEbsEncrypted(record, value);
                }
            }
            this.suspendUpdateEvent--;
        },

        refreshEbsEncrypted: function(record, instType) {
            var me = this;
            record.loadInstanceTypeInfo(function(instanceTypeInfo){
                var field = me.down('[name="db.msr.data_storage.ebs.encrypted"]'),
                    readOnly = false,
                    value = false,
                    ebsEncryptionSupported = instanceTypeInfo ? instanceTypeInfo.ebsencryption || false : true;
                if (field) {
                    readOnly = !ebsEncryptionSupported;
                    value = !ebsEncryptionSupported ? false : field.getValue();
                    if (ebsEncryptionSupported && (Scalr.getGovernance('ec2', 'aws.storage') || {})['require_encryption']) {
                        readOnly = true;
                        value = true;
                        field.toggleIcon('governance', true);
                    } else {
                        field.toggleIcon('governance', false);
                    }
                    field.encryptionSupported = ebsEncryptionSupported;
                    field.setValue(value);
                    field.setReadOnly(readOnly);
                }
            }, instType);
        },

        refreshStorageEngine: function(record) {
            var field = this.down('[name="db.msr.data_storage.engine"]'),
                currentValue = field.getValue();
			field.store.load({
				data: record.getAvailableStorages()
			});

            if (!field.findRecordByValue(currentValue)) {
                var currentValue = record.getDefaultStorageEngine();
                if (!field.findRecordByValue(currentValue)) {
                    currentValue = field.store.first();
                }
            }
            field.setValue(currentValue);

        },

        refreshStorageDisks: function(record) {
            var field =  this.down('[name="db.msr.data_storage.eph.disk"]'),
                cont = this.down('#eph_checkboxes');
            if (record.isMultiEphemeralDevicesEnabled()) {
                field.hide();
                cont.show();
                this.refreshDisksCheckboxes(record, 'eph_checkboxes');
            } else {
                field.show();
                cont.hide();
                field.store.load({data: record.getAvailableStorageDisks()});
                field.setValue(field.store.getAt(field.store.getCount() > 1 ? 1 : 0).get('device'));
            }
        },

        getDisksCheckboxesValues: function(itemId) {
            var volumes = {};
            Ext.each(this.down('#' + itemId).query('checkbox'), function() {
                if (this.getValue()) {
                    volumes[this.getName()] = this.ephSize;
                }
            });
            return Ext.Object.getSize(volumes) > 0 ? Ext.encode(volumes) : null;
        },

        refreshDisksCheckboxes: function(record, itemId) {
            var ephemeralDevicesMap = record.getEphemeralDevicesMap() || {};

            var platform = record.get('platform'),
                settings = record.get('settings'),
                cont = this.down('#' + itemId),
                instanceType;

			if (Ext.Array.contains(['ec2', 'gce'], platform)) {
				instanceType = settings['instance_type'];
			}

            cont.suspendLayouts();
            cont.removeAll();
			if (instanceType !== undefined && ephemeralDevicesMap[instanceType] !== undefined) {
				var devices = ephemeralDevicesMap[instanceType],
                    size = 0;

				for (var d in devices) {
					cont.add({
						xtype: 'checkbox',
						name: d,
						boxLabel: d + ' (' + devices[d]['size'] + 'Gb)',
						ephSize: devices[d]['size'],
						checked: false
					});
					size += parseInt(devices[d]['size']);
				}
			} else {
                cont.add({
					xtype: 'displayfield',
					value: 'LVM device'
                });
            }
            cont.resumeLayouts(true);
        },

        setRole: function(record) {
            var field,
                platform = record.get('platform'),
                instType,
                result = true;

            //fs
            field = this.down('[name="db.msr.data_storage.fstype"]');
            field.suspendLayouts();
            field.removeAll();
            field.add(record.getAvailableStorageFs());
			field.setValue('ext3');
            field.resumeLayouts(true);

			//storage engine
            this.refreshStorageEngine(record);

            //disks
            this.refreshStorageDisks(record);

            //raid
			field = this.down('[name="db.msr.data_storage.raid.level"]');
			field.store.load({
				data: record.getAvailableStorageRaids()
			});
			field.setValue('10');

            //lvm
            this.refreshDisksCheckboxes(record, 'lvm_settings');

			if (Ext.Array.contains(record.get('behaviors', true), 'redis')) {
				this.down('[name="db.msr.redis.persistence_type"]').setValue('snapshotting');
				this.down('[name="db.msr.redis.use_password"]').setValue('1');
				this.down('[name="db.msr.redis.num_processes"]').setValue(1);

				this.down('#redis_settings').show();
			} else {
				this.down('#redis_settings').hide();
			}

            this.down('[name="db.msr.redis.num_processes"]').setValue(1);
            this.down('[name="db.msr.data_storage.ebs.iops"]').setValue(100);
            this.down('[name="db.msr.data_storage.ebs.size"]').setValue(10);
            this.down('[name="db.msr.data_storage.ebs.encrypted"]').setValue(false);
            this.down('[name="db.msr.data_storage.cinder.size"]').setValue(100);
            this.down('[name="db.msr.data_storage.gced.size"]').setValue(100);
            this.down('[name="db.msr.data_storage.gced.type"]').setValue('pd-standard');

            this.down('#disallowWarning').hide();
            this.down('#dataStorageSettings').show();
            if (record.getAvailableStorages().length === 0) {
                if (Scalr.isOpenstack(platform)) {
                    var warningFieldset = this.down('#disallowWarning');
                    warningFieldset.show();
                    warningFieldset.down().setValue('Cinder and Swift are not available on your ' + Scalr.utils.getPlatformName(platform) + ' cloud. At least one of these is required to use database roles.');
                    this.down('#dataStorageSettings').hide();
                    result = false;
                }
            }
            if (platform === 'ec2') {
                if (instType = this.up('form').getForm().findField('instance_type').getValue()) {
                    this.refreshEbsEncrypted(record, instType);
                }
            }
            return result;
        },

        isValid: function(record) {
            var storageEngine = this.down('[name="db.msr.data_storage.engine"]').getValue(),
                res = true,
                field;
            if (Ext.Array.contains(['ebs', 'csvol'], storageEngine)) {
                field = this.down('[name="db.msr.data_storage.ebs.size"]');
                res = field.validate() || {comp: field};
                if (res === true && this.down('[name="db.msr.data_storage.ebs.type"]').getValue() === 'io1') {
                    field = this.down('[name="db.msr.data_storage.ebs.iops"]');
                    res = field.validate() || {comp: field};
                }
            } else if (storageEngine === 'lvm' && record.get('platform') === 'ec2') {
                var lvm = this.down('#lvm_settings').query('checkbox');
                if (!this.getDisksCheckboxesValues('lvm_settings') && lvm.length > 0) {
                    res = {comp: lvm[0], message: 'Please select ephemeral device'};
                }
            } else if (storageEngine === 'cinder') {
                field = this.down('[name="db.msr.data_storage.cinder.size"]');
                res = field.validate() || {comp: field};
            } else if (storageEngine === 'raid.ebs') {
                field = this.down('[name="db.msr.data_storage.raid.volume_size"]');
                res = field.validate() || {comp: field};
                if (res === true && storageEngine === 'raid.ebs' && this.down('[name="db.msr.data_storage.raid.ebs.type"]').getValue() === 'io1') {
                    field = this.down('[name="db.msr.data_storage.raid.ebs.iops"]');
                    res = field.validate() || {comp: field};
                }
            }
            return res;
        },

        getSettings: function(record) {
            var settings = {
                    'db.msr.data_storage.engine': this.down('[name="db.msr.data_storage.engine"]').getValue(),
                    'db.msr.data_storage.fstype': this.down('[name="db.msr.data_storage.fstype"]').getValue()
                };

			if (settings['db.msr.data_storage.engine'] === 'eph') {
                if (record.isMultiEphemeralDevicesEnabled()) {
                    settings['db.msr.data_storage.eph.disks'] = this.getDisksCheckboxesValues('eph_checkboxes');
                } else {
                    settings['db.msr.data_storage.eph.disk'] = this.down('[name="db.msr.data_storage.eph.disk"]').getValue();
                }
            } else if (settings['db.msr.data_storage.engine'] === 'lvm') {
                settings['db.msr.storage.lvm.volumes'] = this.getDisksCheckboxesValues('lvm_settings');
            } else if (settings['db.msr.data_storage.engine'] === 'raid.ebs') {
				settings['db.msr.data_storage.raid.level'] = this.down('[name="db.msr.data_storage.raid.level"]').getValue();
				settings['db.msr.data_storage.raid.volume_size'] = this.down('[name="db.msr.data_storage.raid.volume_size"]').getValue();
				settings['db.msr.data_storage.raid.volumes_count'] = this.down('[name="db.msr.data_storage.raid.volumes_count"]').getValue();

                if (settings['db.msr.data_storage.engine'] === 'raid.ebs') {
                    settings['db.msr.data_storage.raid.ebs.type'] = this.down('[name="db.msr.data_storage.raid.ebs.type"]').getValue();
                    settings['db.msr.data_storage.raid.ebs.iops'] = this.down('[name="db.msr.data_storage.raid.ebs.iops"]').getValue();
                }
			} else if (settings['db.msr.data_storage.engine'] == 'cinder') {
				settings['db.msr.data_storage.cinder.size'] = this.down('[name="db.msr.data_storage.cinder.size"]').getValue();
			} else if (settings['db.msr.data_storage.engine'] == 'gce_persistent') {
				settings['db.msr.data_storage.gced.size'] = this.down('[name="db.msr.data_storage.gced.size"]').getValue();
				settings['db.msr.data_storage.gced.type'] = this.down('[name="db.msr.data_storage.gced.type"]').getValue();
			} else if (Ext.Array.contains(['ebs', 'csvol'], settings['db.msr.data_storage.engine'])) {
                settings['db.msr.data_storage.ebs.size'] = this.down('[name="db.msr.data_storage.ebs.size"]').getValue();
                settings['db.msr.data_storage.ebs.type'] = this.down('[name="db.msr.data_storage.ebs.type"]').getValue();
                settings['db.msr.data_storage.ebs.iops'] = this.down('[name="db.msr.data_storage.ebs.iops"]').getValue();
                settings['db.msr.data_storage.ebs.encrypted'] = this.down('[name="db.msr.data_storage.ebs.encrypted"]').getValue() ? 1 : 0;
            }

			if (Ext.Array.contains(record.get('behaviors', true), 'redis')) {
				settings['db.msr.redis.persistence_type'] = this.down('[name="db.msr.redis.persistence_type"]').getValue();
				settings['db.msr.redis.use_password'] = this.down('[name="db.msr.redis.use_password"]').getValue();
				settings['db.msr.redis.num_processes'] = this.down('[name="db.msr.redis.num_processes"]').getValue();
			}


            return settings;
        },

        items: [{
            xtype: 'fieldset',
            itemId: 'redis_settings',
            title: 'Redis settings',
            hidden: true,
            layout: 'anchor',
            items: [{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                maxWidth: 760,
                items: [{
                    xtype: 'combo',
                    name: 'db.msr.redis.persistence_type',
                    fieldLabel: 'Persistence type',
                    editable: false,
                    store: Scalr.constants.redisPersistenceTypes,
                    valueField: 'id',
                    displayField: 'name',
                    value: 'snapshotting',
                    flex: 1,
                    labelWidth: 125,
                    queryMode: 'local',
                    margin: '0 32 0 0'
                }, {
                    xtype: 'buttongroupfield',
                    name: 'db.msr.redis.use_password',
                    fieldLabel: 'Password auth',
                    labelWidth: 110,
                    flex: 1,
                    defaults: {
                        width: 50
                    },
                    items: [{
                        text: 'On',
                        value: '1'
                    },{
                        text: 'Off',
                        value: '0'
                    }]
                }]
            }, {
                xtype: 'sliderfield',
                name: 'db.msr.redis.num_processes',
                fieldLabel: 'Num. of processes',
                minValue: 1,
                maxValue: 16,
                increment: 1,
                labelWidth: 128,
                anchor: '50%',
                maxWidth: 380,
                margin: '0 16 20 0',
                useTips: false,
                showValue: true
            }]
        },{
            xtype: 'fieldset',
            itemId: 'disallowWarning',
            items: {
                xtype: 'displayfield',
                cls: 'x-form-field-warning',
                anchor: '100%'
            }
        },{
            xtype: 'fieldset',
            itemId: 'dataStorageSettings',
            title: 'Data storage settings',
            items: [{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                maxWidth: 760,
                items: [{
                    xtype: 'combo',
                    name: 'db.msr.data_storage.engine',
                    flex: 1,
                    margin: '0 32 0 0',
                    fieldLabel: 'Storage engine',
                    labelWidth: 120,
                    allowChangeable: false,
                    editable: false,
                    queryMode: 'local',
                    store: {
                        fields: [ 'description', 'name' ],
                        proxy: 'object'
                    },
                    valueField: 'name',
                    displayField: 'description',
                    listeners:{
                        change: function(comp, value){
                            var tab = this.up('#dbmsr'),
                                moduleTabParams = tab.up('#farmDesigner').moduleParams['tabParams'],
                                isRaid = value === 'raid.ebs',
                                field, xfsBtn;
                            tab.suspendLayouts();
                            tab.down('#eph_settings').setVisible(value === 'eph');
                            tab.down('#lvm_settings').setVisible(value === 'lvm');

                            tab.down('#ebs_settings').setVisible(value === 'ebs' || value === 'csvol');
                            tab.down('[name="db.msr.data_storage.ebs.encrypted"]').setVisible(value === 'ebs');
                            tab.down('#ebs_settings_type').setVisible(value === 'ebs');

                            tab.down('#cinder_settings').setVisible(value === 'cinder');
                            tab.down('#gced_settings').setVisible(value === 'gce_persistent');
                            tab.down('#raid_settings').setVisible(isRaid);
                            if (isRaid) {
                                var raidEbsTypeField = tab.down('[name="db.msr.data_storage.raid.ebs.type"]');
                                raidEbsTypeField.setVisible(value === 'raid.ebs');
                                tab.down('[name="db.msr.data_storage.raid.ebs.iops"]').setVisible(value === 'raid.ebs' && raidEbsTypeField.getValue() === 'io');
                            }

                            field = tab.down('[name="db.msr.data_storage.fstype"]');
                            xfsBtn = field.down('[value="xfs"]');
                            if (xfsBtn && !xfsBtn.unavailable) {
                                if (isRaid && field.getValue() === 'xfs') {
                                    field.setValue('ext3');
                                }
                                xfsBtn.setDisabled(isRaid);
                            }

                            tab.resumeLayouts(true);
                            if (value && tab.suspendUpdateEvent === 0) {
                                tab.up('form').updateRecordSettings(comp.name, value);
                            }
                        }
                    }
                },{
                    xtype: 'buttongroupfield',
                    name: 'db.msr.data_storage.fstype',
                    flex: 1,
                    minWidth: 200,
                    fieldLabel: 'Filesystem',
                    labelWidth: 80,
                    layout: 'hbox',
                    defaults: {
                        minWidth: 50,
                        maxWidth: 70,
                        flex: 1
                    }
                }]
            },{
                xtype: 'container',
                maxWidth: 760,
                layout: 'anchor',
                defaults: {
                   anchor: '100%'
                },
                items: [{
                    xtype: 'container',
                    itemId: 'eph_settings',
                    cls: 'inner-container',
                    layout: 'anchor',
                    hidden: true,
                    items: [{
                        xtype: 'combo',
                        name: 'db.msr.data_storage.eph.disk',
                        allowChangeable: false,
                        fieldLabel: 'Disk device',
                        editable: false,
                        store: {
                            fields: [ 'device', 'description' ],
                            proxy: 'object'
                        },
                        valueField: 'device',
                        displayField: 'description',
                        anchor: '100%',
                        labelWidth: 100,
                        queryMode: 'local'
                    },{
                        xtype: 'container',
                        itemId: 'eph_checkboxes',
                        hidden: true
                    }]
                },{
                    xtype: 'container',
                    itemId: 'lvm_settings',
                    cls: 'inner-container',
                    hidden: true,
                    items: [{
                        xtype: 'displayfield',
                        hideLabel: true,
                        value: 'LVM device',
                        width: 400
                    }]
                },{
                    xtype: 'container',
                    itemId: 'ebs_settings',
                    cls: 'inner-container',
                    hidden: true,
                    layout: 'anchor',
                    items: [{
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox'
                        },
                        anchor: '100%',
                        items: [{
                            xtype: 'container',
                            itemId: 'ebs_settings_type',
                            layout: 'hbox',
                            flex: 1,
                            //maxWidth: 362,
                            margin: '0 20 0 0',
                            items: [{
                                xtype: 'combo',
                                store: Scalr.utils.getEbsTypes(),
                                allowChangeable: false,
                                fieldLabel: 'EBS type',
                                labelWidth: 102,
                                valueField: 'id',
                                displayField: 'name',
                                editable: false,
                                queryMode: 'local',
                                value: 'standard',
                                name: 'db.msr.data_storage.ebs.type',
                                flex: 1,
                                maxWidth: 380,
                                listeners: {
                                    change: function (comp, value) {
                                        var iopsField = comp.next();
                                        iopsField.setVisible(value === 'io1');
                                        if (value === 'io1') {
                                            iopsField.reset();
                                            iopsField.setValue(100);
                                        } else {
                                            comp.up('container').next().isValid();
                                        }
                                    }
                                }
                            },{
                                xtype: 'textfield',
                                //itemId: 'db.msr.data_storage.ebs.iops',
                                name: 'db.msr.data_storage.ebs.iops',
                                allowChangeable: false,
                                vtype: 'iops',
                                allowBlank: false,
                                hideLabel: true,
                                hidden: true,
                                margin: '0 0 0 2',
                                width: 50,
                                listeners: {
                                    change: function(comp, value){
                                        var sizeField = comp.up('container').next();
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
                            xtype: 'textfield',
                            name: 'db.msr.data_storage.ebs.size',
                            allowChangeable: false,
                            fieldLabel: 'Storage size',
                            width: 155,
                            vtype: 'ebssize',
                            getEbsType: function() {
                                return this.up('container').down('[name="db.msr.data_storage.ebs.type"]').getValue();
                            },
                            getEbsIops: function() {
                                return this.up('container').down('[name="db.msr.data_storage.ebs.iops"]').getValue();
                            },
                            labelWidth: 90,
                            allowBlank: false,
                            margin: '0 6 0 0'
                        },{
                            xtype: 'label',
                            cls: 'x-label-grey',
                            text: 'GB',
                            margin: '6 0 0 0'
                        }]
                    },{
                        xtype: 'checkbox',
                        name: 'db.msr.data_storage.ebs.encrypted',
                        boxLabel: 'Enable EBS encryption',
                        defaults: {
                            width: 90
                        },
                        value: '0',
                        hidden: true,
                        plugins: {
                            ptype: 'fieldicons',
                            icons: [
                                {id: 'question', tooltip: 'EBS encryption is not supported by selected instance type'},
                                'governance'
                            ]
                        },
                        listeners: {
                            writeablechange: function(comp, readOnly) {
                                this.toggleIcon('question', readOnly && !comp.encryptionSupported);
                            }
                        }

                    }]
                },{
                    xtype: 'container',
                    itemId: 'cinder_settings',
                    cls: 'inner-container',
                    hidden: true,
                    layout: 'anchor',
                    items: [{
                        xtype: 'textfield',
                        name: 'db.msr.data_storage.cinder.size',
                        allowChangeable: false,
                        fieldLabel: 'Disk size',
                        vtype: 'num',
                        allowBlank: false,
                        labelWidth: 100,
                        anchor: '100%',
                        maxWidth: 200,
                        value: 100
                    }]
                },{
                    xtype: 'container',
                    itemId: 'gced_settings',
                    cls: 'inner-container',
                    hidden: true,
                    layout: 'anchor',
                    items: [{
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'textfield',
                            name: 'db.msr.data_storage.gced.size',
                            allowChangeable: false,
                            fieldLabel: 'Disk size',
                            vtype: 'num',
                            allowBlank: false,
                            width: 215,
                            value: 100
                        },{
                            xtype: 'label',
                            text: 'GB',
                            margin: '0 0 0 6'
                        }]
                    }, {
                        xtype: 'combo',
                        store: Scalr.constants.gceDiskTypes,
                        width: 215,
                        valueField: 'id',
                        displayField: 'name',
                        allowChangeable: false,
                        fieldLabel: 'Disk type',
                        editable: false,
                        queryMode: 'local',
                        value: 'pd-standard',
                        name: 'db.msr.data_storage.gced.type'
                    }]
                },{
                    xtype: 'container',
                    itemId: 'raid_settings',
                    cls: 'inner-container',
                    hidden: true,
                    layout: 'anchor',
                    items: [{
                        xtype: 'container',
                        layout: 'hbox',
                        items: [{
                            xtype: 'combo',
                            name: 'db.msr.data_storage.raid.level',
                            allowChangeable: false,
                            fieldLabel: 'RAID level',
                            editable: false,
                            store: {
                                fields: [ 'name', 'description' ],
                                proxy: 'object'
                            },
                            valueField: 'name',
                            displayField: 'description',
                            flex: 1,
                            margin: '0 20 0 0',
                            value: '',
                            labelWidth: 80,
                            queryMode: 'local',
                            listeners:{
                                change:function(comp, value) {
                                    var data = [];
                                    if (value == '0') {
                                        data = {'2':'2', '3':'3', '4':'4', '5':'5', '6':'6', '7':'7', '8':'8'};
                                    } else if (value == '1') {
                                        data = {'2':'2'};
                                    } else if (value == '5') {
                                        data = {'3':'3', '4':'4', '5':'5', '6':'6', '7':'7', '8':'8'};
                                    } else if (value == '10') {
                                        data = {'4':'4', '6':'6', '8':'8'};
                                    }

                                    var field = comp.next(),
                                        val;
                                    field.store.load({data: data});
                                    if (field.store.getCount()) {
                                        val = field.store.getAt(0).get('id')
                                    }
                                    field.setValue(val);
                                }
                            }
                        },{
                            xtype: 'combo',
                            name: 'db.msr.data_storage.raid.volumes_count',
                            allowChangeable: false,
                            fieldLabel: 'Number of volumes',
                            editable: false,
                            store: {
                                fields: [ 'id', 'name'],
                                proxy: 'object'
                            },
                            valueField: 'id',
                            displayField: 'name',
                            flex: 1,
                            labelWidth: 135,
                            maxWidth: 210,
                            queryMode: 'local'
                        }]
                    },{
                        xtype: 'container',
                        layout: 'hbox',
                        margin: '12 0',
                        items: [{
                            xtype: 'container',
                            flex: 1,
                            layout: 'hbox',
                            items: [{
                                xtype: 'combo',
                                store: Scalr.utils.getEbsTypes(),
                                allowChangeable: false,
                                fieldLabel: 'EBS type',
                                labelWidth: 80,
                                valueField: 'id',
                                displayField: 'name',
                                editable: false,
                                queryMode: 'local',
                                name: 'db.msr.data_storage.raid.ebs.type',
                                value: 'standard',
                                flex: 1,
                                listeners: {
                                    change: function (comp, value) {
                                        var iopsField = comp.next();
                                        iopsField.setVisible(value === 'io1');
                                        if (value === 'io1') {
                                            iopsField.reset();
                                            iopsField.setValue(100);
                                        } else {
                                            comp.up('container').next().isValid();
                                        }
                                    }
                                }
                            }, {
                                xtype: 'textfield',
                                //itemId: 'db.msr.data_storage.raid.ebs.iops',
                                allowChangeable: false,
                                name: 'db.msr.data_storage.raid.ebs.iops',
                                vtype: 'iops',
                                allowBlank: false,
                                hidden: true,
                                margin: '0 0 0 2',
                                width: 50,
                                listeners: {
                                    change: function(comp, value){
                                        var sizeField = comp.up('container').next();
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
                            xtype: 'textfield',
                            name: 'db.msr.data_storage.raid.volume_size',
                            allowChangeable: false,
                            fieldLabel: 'Each volume size',
                            allowBlank: false,
                            labelWidth: 135,
                            flex: 1,
                            maxWidth: 186,
                            margin: '0 6 0 20',
                            value: 10,
                            vtype: 'ebssize',
                            getEbsType: function() {
                                return this.up('container').down('[name="db.msr.data_storage.raid.ebs.type"]').getValue();
                            },
                            getEbsIops: function() {
                                return this.up('container').down('[name="db.msr.data_storage.raid.ebs.iops"]').getValue();
                            }
                        },{
                            xtype: 'label',
                            cls: 'x-label-grey',
                            text: 'GB',
                            margin: '4 0 0 0'
                        }]
                    }]
                }]
            }]
        }]
    }
});

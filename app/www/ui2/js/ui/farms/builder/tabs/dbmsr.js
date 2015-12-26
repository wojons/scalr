Ext.define('Scalr.ui.FarmRoleEditorTab.Dbmsr', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Database',
    itemId: 'dbmsr',

    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'db.msr.redis.persistence_type': undefined,
        'db.msr.redis.use_password': undefined,
        'db.msr.redis.num_processes': undefined,
        'db.msr.data_bundle.enabled': 1,
        'db.msr.data_bundle.every': 24,
        'db.msr.data_bundle.timeframe.start_hh': '05',
        'db.msr.data_bundle.timeframe.start_mm': '00',
        'db.msr.data_bundle.timeframe.end_hh': '09',
        'db.msr.data_bundle.timeframe.end_mm': '00',
        'db.msr.data_bundle.use_slave': undefined,
        'db.msr.no_data_bundle_on_promote': undefined,
        'db.msr.data_bundle.compression': undefined,
        'db.msr.data_backup.enabled': function(record) {
            var platform = record.get('platform');
            return Scalr.isCloudstack(platform) || Scalr.isOpenstack(platform) && Scalr.getPlatformConfigValue(platform, 'ext.swift_enabled') != 1 ? 0 : 1;
        },
        'db.msr.data_backup.every': 48,
        'db.msr.data_backup.server_type': 'master-if-no-slaves',
        'db.msr.data_backup.timeframe.start_hh': '05',
        'db.msr.data_backup.timeframe.start_mm': '00',
        'db.msr.data_backup.timeframe.end_hh': '09',
        'db.msr.data_backup.timeframe.end_mm': '00',
        'db.msr.data_storage.engine': function(record) {return record.getDefaultStorageEngine()},
        'db.msr.storage.recreate_if_missing': 0,
        'db.msr.data_storage.fstype': undefined,
        'db.msr.data_storage.ebs.size': 10,
        'db.msr.data_storage.ebs.encrypted': undefined,
        'db.msr.data_storage.ebs.type': undefined,
        'db.msr.data_storage.ebs.iops': undefined,
        'db.msr.data_storage.ebs.snaps.enable_rotation': 1,
        'db.msr.data_storage.ebs.snaps.rotate': 5,
        'db.msr.data_storage.eph.disk': undefined,
        'db.msr.data_storage.eph.disks': undefined,
        'db.msr.storage.lvm.volumes': undefined,
        'db.msr.data_storage.raid.level': undefined,
        'db.msr.data_storage.raid.volume_size': undefined,
        'db.msr.data_storage.raid.volumes_count': undefined,
        'db.msr.data_storage.raid.ebs.type': undefined,
        'db.msr.data_storage.raid.ebs.iops': undefined,
        'db.msr.data_storage.cinder.size': 100,
        'db.msr.data_storage.gced.size': 1,
        'db.msr.data_storage.gced.type': 'pd-standard'
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && record.isDbMsr();
    },

   onRoleUpdate: function(record, name, value, oldValue) {
        if (!this.isEnabled(record)) return;

        var me = this,
            fullname = name.join('.'),
            settings = record.get('settings');
        if (fullname === 'settings.aws.instance_type' || fullname === 'settings.gce.machine-type') {
            var devices = record.getAvailableStorageDisks(),
                fistDevice = '',
                field, currentValue;

            Ext.Array.each(devices, function(disk){
                if (fistDevice === ''){
                    fistDevice = disk.device;
                }
                if (settings['db.msr.data_storage.eph.disk'] == disk.device) {
                    fistDevice = disk.device;
                }
            });

            if (me.isVisible()) {
                if (record.get('new')) {
                    field = me.down('[name="db.msr.data_storage.engine"]');
                    currentValue = field.getValue();
                    field.store.load({
                        data: record.getAvailableStorages()
                    });

                    field.setValue(field.findRecordByValue(currentValue) ? currentValue : record.getDefaultStorageEngine());
                }

                if (record.isMultiEphemeralDevicesEnabled()) {
                    me.refreshDisksCheckboxes(record, 'eph_checkboxes', 'db.msr.data_storage.eph.disks');
                } else {
                    field = me.down('[name="db.msr.data_storage.eph.disk"]');
                    field.store.load({data: devices});
                    field.setValue(fistDevice);
                }
                me.refreshDisksCheckboxes(record, 'lvm_settings', 'db.msr.storage.lvm.volumes');

                if (fullname === 'settings.aws.instance_type' && record.get('new')) {
                    record.loadInstanceTypeInfo(function(instanceTypeInfo){
                        var field = me.down('[name="db.msr.data_storage.ebs.encrypted"]'),
                            ebsEncryptionSupported = instanceTypeInfo ? instanceTypeInfo.ebsencryption || false : true;
                        if (field) {
                            field.setReadOnly(!ebsEncryptionSupported);
                        }
                    });

                }
            }

            if (settings['db.msr.data_storage.engine'] === 'eph') {
                if (!record.isMultiEphemeralDevicesEnabled()) {
                    settings['db.msr.data_storage.eph.disk'] = fistDevice;
                }
                record.set('settings', settings);
            }
        }

    },

    refreshDisksCheckboxes: function(record, itemId, optionName) {
        var cont = this.down('#' + itemId);
        cont.suspendLayouts();
        cont.removeAll();
        var ephemeralDevicesMap = record.getEphemeralDevicesMap();

        var platform = record.get('platform'),
            settings = record.get('settings', true),
            instanceType;

        if (platform === 'gce') {
            instanceType = settings['gce.machine-type'];
        } else if (platform === 'ec2') {
            instanceType = settings['aws.instance_type'];
        }

        if (instanceType !== undefined && ephemeralDevicesMap[instanceType] !== undefined) {
            var devices = ephemeralDevicesMap[instanceType], size = 0,
                volumes = Ext.decode(settings[optionName]);

            for (var d in devices) {
                cont.add({
                    xtype: 'checkbox',
                    name: d,
                    boxLabel: d + ' (' + devices[d]['size'] + 'Gb)',
                    ephSize: devices[d]['size'],
                    checked: volumes && Ext.isDefined(volumes[d])
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
        cont.setDisabled(!record.get('new'))
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

    changeEbsStorageSettings: function() {
        var me = this,
            settings = me.currentRole.get('settings', true),
            growConfigExt,
            growConfig = {},
            growConfigMapping = {
                'db.msr.data_storage.ebs.type': 'volumeType',
                'db.msr.data_storage.ebs.size': 'size',
                'db.msr.data_storage.ebs.iops': 'iops',
            },
            currentEbsStorageSettings = {
                'db.msr.data_storage.ebs.type': me.down('[name="db.msr.data_storage.ebs.type"]').getValue(),
                'db.msr.data_storage.ebs.size': me.down('[name="db.msr.data_storage.ebs.size"]').getValue(),
            };
        if (currentEbsStorageSettings['db.msr.data_storage.ebs.type'] == 'io1') {
            currentEbsStorageSettings['db.msr.data_storage.ebs.iops'] = me.down('[name="db.msr.data_storage.ebs.iops"]').getValue();
        }

        if (settings['db.msr.storage.grow_config']) {
            growConfigExt = Ext.decode(settings['db.msr.storage.grow_config'], true);
            Ext.Object.each(growConfigMapping, function(nameInt, nameExt){
                if (growConfigExt[nameExt] !== undefined) {
                    growConfig[nameInt] = growConfigExt[nameExt];
                }
            });
        }
        Scalr.Confirm({
            form: {
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                title: 'Change storage configuration',
                items: [{
                    xtype: 'fieldcontainer',
                    layout: 'hbox',
                    items: [{
                        xtype: 'combo',
                        store: Scalr.constants.ebsTypes,
                        fieldLabel: 'EBS type',
                        valueField: 'id',
                        displayField: 'name',
                        editable: false,
                        queryMode: 'local',
                        name: 'db.msr.data_storage.ebs.type',
                        width: 360,
                        value: growConfig['db.msr.data_storage.ebs.type'] || currentEbsStorageSettings['db.msr.data_storage.ebs.type'],
                        listeners: {
                            change: function (comp, value) {
                                var form = comp.up('form'),
                                    iopsField = form.down('[name="db.msr.data_storage.ebs.iops"]');
                                iopsField.setVisible(value === 'io1').setDisabled(value !== 'io1');
                                if (value === 'io1') {
                                    iopsField.reset();
                                    iopsField.setValue(100);
                                } else {
                                    form.down('[name="db.msr.data_storage.ebs.size"]').isValid();
                                }
                            }
                        }
                    }, {
                        xtype: 'textfield',
                        //itemId: 'db.msr.data_storage.ebs.iops',
                        name: 'db.msr.data_storage.ebs.iops',
                        vtype: 'iops',
                        allowBlank: false,
                        hidden: (growConfig['db.msr.data_storage.ebs.type'] || currentEbsStorageSettings['db.msr.data_storage.ebs.type']) != 'io1',
                        disabled: (growConfig['db.msr.data_storage.ebs.type'] || currentEbsStorageSettings['db.msr.data_storage.ebs.type']) != 'io1',
                        margin: '0 0 0 6',
                        width: 50,
                        value: growConfig['db.msr.data_storage.ebs.iops'] || currentEbsStorageSettings['db.msr.data_storage.ebs.iops'],
                        listeners: {
                            change: function(comp, value){
                                var form = comp.up('form'),
                                    sizeField = form.down('[name="db.msr.data_storage.ebs.size"]');
                                if (comp.isValid() && comp.prev().getValue() === 'io1') {
                                    var minSize = Scalr.utils.getMinStorageSizeByIops(value);
                                    if (sizeField.getValue()*1 < minSize) {
                                        sizeField.setValue(minSize);
                                    }
                                }
                            }
                        }

                    }]
                }, {
                    xtype: 'fieldcontainer',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    items: [{
                        xtype: 'textfield',
                        name: 'db.msr.data_storage.ebs.size',
                        fieldLabel: 'Storage size',
                        width: 185 + 55,
                        value: growConfig['db.msr.data_storage.ebs.size'] || currentEbsStorageSettings['db.msr.data_storage.ebs.size'],
                        vtype: 'ebssize',
                        getEbsType: function() {
                            return this.up('form').down('[name="db.msr.data_storage.ebs.type"]').getValue();
                        },
                        getEbsIops: function() {
                            return this.up('form').down('[name="db.msr.data_storage.ebs.iops"]').getValue();
                        },
                        validator: function(value){
                            if (value*1 < currentEbsStorageSettings['db.msr.data_storage.ebs.size']*1) {
                                return 'New storage size must be bigger that previous one.';
                            }
                            return true;
                        }
                    },{
                        xtype: 'label',
                        text: 'GB',
                        margin: '0 0 0 6'
                    }]
                }]
            },
            formWidth: 520,
            ok: 'Save',
            closeOnSuccess: true,
            success: function (formValues, form) {
                if (form.isValid()) {
                    var growConfig = {};
                    Ext.Object.each(growConfigMapping, function(nameInt, nameExt){
                        if (formValues[nameInt] && currentEbsStorageSettings[nameInt] != formValues[nameInt]) {
                            growConfig[nameExt] = formValues[nameInt];
                        }
                    });
                    if (Ext.Object.getSize(growConfig) > 0) {
                        settings['db.msr.storage.grow_config'] = Ext.encode(growConfig);
                    } else {
                        delete settings['db.msr.storage.grow_config'];
                    }
                    me.down('#changeEbsStorageSettingsInfo').setValue(growConfig);
                    return true;
                }

            }
        });
    },

    beforeShowTab: function (record, handler) {
        var me = this,
            platform = record.get('platform');

        if (platform === 'ec2' && record.get('new')) {
            record.loadInstanceTypeInfo(function(instanceTypeInfo){
                var field = me.down('[name="db.msr.data_storage.ebs.encrypted"]'),
                    ebsEncryptionSupported = instanceTypeInfo ? instanceTypeInfo.ebsencryption || false : true;
                field.setReadOnly(!ebsEncryptionSupported);
                handler();
            });
        } else {
            handler();
        }
    },

    showTab: function (record) {
        var settings = record.get('settings'),
            platform = record.get('platform'),
            notANewRecord = !record.get('new'),
            tabParams = this.up('#farmDesigner').moduleParams.tabParams,
            field, value;

        this.isLoading = true;

        this.down('[name="db.msr.data_bundle.use_slave"]').setVisible(record.isMySql());

        if (Scalr.isCloudstack(platform) || Scalr.isOpenstack(platform) && Scalr.getPlatformConfigValue(platform, 'ext.swift_enabled') != 1) {
            this.down('[name="db.msr.data_backup.enabled"]').hide().collapse();
        } else {
            this.down('[name="db.msr.data_backup.enabled"]').show();
        }

        this.down('[name="db.msr.data_storage.engine"]').store.load({data: record.getAvailableStorages()});

        // File systems
        field = this.down('[name="db.msr.data_storage.fstype"]');

        field.removeAll();
        field.add(record.getAvailableStorageFs());
        field.setValue(settings['db.msr.data_storage.fstype'] || 'ext3');
        if (notANewRecord) {
            field.setReadOnly(notANewRecord);
        }

        // Ephemeral devices
        this.down('[name="lvm_settings"]').setVisible(settings['db.msr.data_storage.engine'] === 'lvm');
        this.down('[name="db.msr.data_bundle.compression"]').setVisible(settings['db.msr.data_storage.engine'] === 'lvm');
        this.refreshDisksCheckboxes(record, 'lvm_settings', 'db.msr.storage.lvm.volumes');

        //redis
        if (record.get('behaviors').match('redis')) {
            this.down('[name="db.msr.redis.persistence_type"]').setValue(settings['db.msr.redis.persistence_type'] || 'snapshotting');
            this.down('[name="db.msr.redis.use_password"]').setValue(settings['db.msr.redis.use_password'] || 1);

            field = this.down('[name="db.msr.redis.num_processes"]');
            field.setValue(settings['db.msr.redis.num_processes'] || 1);
            field.setReadOnly(tabParams.farm.status > 0 && !record.get('new'))

            this.down('[name="redis_settings"]').show();
        } else {
            this.down('[name="redis_settings"]').hide();
        }

        //eph
        field = this.down('[name="db.msr.data_storage.eph.disk"]');
        if (record.isMultiEphemeralDevicesEnabled()) {
            field.hide();
            this.down('#eph_checkboxes').show();
            this.refreshDisksCheckboxes(record, 'eph_checkboxes', 'db.msr.data_storage.eph.disks');
        } else {
            field.show();
            this.down('#eph_checkboxes').hide();
            field.store.load({data: record.getAvailableStorageDisks()});
            field.setValue(settings['db.msr.data_storage.eph.disk'] || (field.store.getAt(field.store.getCount() > 1 ? 1 : 0).get('device')));
        }
        //raid
        field = this.down('[name="db.msr.data_storage.raid.level"]');
        field.store.load({data: record.getAvailableStorageRaids()});
        field.setValue(settings['db.msr.data_storage.raid.level'] || '10');

        this.down('[name="db.msr.data_storage.raid.ebs.type"]').setValue(settings['db.msr.data_storage.raid.ebs.type'] || 'standard');
        this.down('[name="db.msr.data_storage.raid.ebs.iops"]').setValue(settings['db.msr.data_storage.raid.ebs.iops'] || 50);
        this.down('[name="db.msr.data_storage.raid.volumes_count"]').setValue(settings['db.msr.data_storage.raid.volumes_count'] || 4);
        this.down('[name="db.msr.data_storage.raid.volume_size"]').setValue(settings['db.msr.data_storage.raid.volume_size'] || 10);

        this.down('[name="db.msr.data_storage.cinder.size"]').setValue(settings['db.msr.data_storage.cinder.size'] || 1);

        this.down('[name="db.msr.data_storage.gced.size"]').setValue(settings['db.msr.data_storage.gced.size'] || '');
        this.down('[name="db.msr.data_storage.gced.type"]').setValue(settings['db.msr.data_storage.gced.type'] || 'pd-standard');

        //data bundle
        this.down('[name="db.msr.data_bundle.enabled"]')[settings['db.msr.data_bundle.enabled'] == 1 ? 'expand' : 'collapse']();
        this.down('[name="db.msr.data_bundle.every"]').setValue(settings['db.msr.data_bundle.every']);
        this.down('[name="db.msr.data_bundle.use_slave"]').setValue(settings['db.msr.data_bundle.use_slave'] || 0);
        this.down('[name="db.msr.no_data_bundle_on_promote"]').setValue(settings['db.msr.no_data_bundle_on_promote'] || 0);
        this.down('[name="db.msr.data_bundle.compression"]').setValue(settings['db.msr.data_bundle.compression'] || '');
        this.down('[name="db.msr.data_bundle.timeframe.start_hh"]').setValue(settings['db.msr.data_bundle.timeframe.start_hh']);
        this.down('[name="db.msr.data_bundle.timeframe.start_mm"]').setValue(settings['db.msr.data_bundle.timeframe.start_mm']);
        this.down('[name="db.msr.data_bundle.timeframe.end_hh"]').setValue(settings['db.msr.data_bundle.timeframe.end_hh']);
        this.down('[name="db.msr.data_bundle.timeframe.end_mm"]').setValue(settings['db.msr.data_bundle.timeframe.end_mm']);

        //data backup
        this.down('[name="db.msr.data_backup.enabled"]')[settings['db.msr.data_backup.enabled'] == 1 ? 'expand' : 'collapse']();

        if (!Ext.Array.contains(['cloudstack', 'idcf'], platform)) {
            this.down('[name="db.msr.data_backup.every"]').setValue(settings['db.msr.data_backup.every']);
            this.down('[name="db.msr.data_backup.server_type"]').setValue(settings['db.msr.data_backup.server_type'] || 'master-if-no-slaves');
            this.down('[name="db.msr.data_backup.timeframe.start_hh"]').setValue(settings['db.msr.data_backup.timeframe.start_hh']);
            this.down('[name="db.msr.data_backup.timeframe.start_mm"]').setValue(settings['db.msr.data_backup.timeframe.start_mm']);
            this.down('[name="db.msr.data_backup.timeframe.end_hh"]').setValue(settings['db.msr.data_backup.timeframe.end_hh']);
            this.down('[name="db.msr.data_backup.timeframe.end_mm"]').setValue(settings['db.msr.data_backup.timeframe.end_mm']);
        }

        if (Ext.Array.contains(['ebs', 'csvol'], settings['db.msr.data_storage.engine'])) {
            this.down('[name="db.msr.data_storage.ebs.snaps.enable_rotation"]').setValue(settings['db.msr.data_storage.ebs.snaps.enable_rotation'] == 1);

            field = this.down('[name="db.msr.data_storage.ebs.snaps.rotate"]');
            field.setDisabled(settings['db.msr.data_storage.ebs.snaps.enable_rotation'] != 1);
            field.setValue(settings['db.msr.data_storage.ebs.snaps.rotate']);

            //recreate_if_missing
            field = this.down('[name="db.msr.storage.recreate_if_missing"]');
            field.setValue(settings['db.msr.storage.recreate_if_missing']);

            this.down('[name="db.msr.data_storage.ebs.size"]').setValue(settings['db.msr.data_storage.ebs.size']);
            this.down('[name="db.msr.data_storage.ebs.type"]').setValue(settings['db.msr.data_storage.ebs.type'] || 'standard');
            this.down('[name="db.msr.data_storage.ebs.iops"]').setValue(settings['db.msr.data_storage.ebs.iops'] || 50);

            if (settings['db.msr.data_storage.engine'] == 'csvol') {
                this.down('[name="db.msr.data_storage.ebs.type"]').hide();
                this.down('[name="db.msr.data_storage.ebs.iops"]').hide();
            } else {
                this.down('[name="db.msr.data_storage.ebs.type"]').show();
                this.down('[name="db.msr.data_storage.ebs.iops"]').setVisible(this.down('[name="db.msr.data_storage.ebs.type"]').getValue() == 'io1');
            }


            field = this.down('[name="db.msr.data_storage.ebs.encrypted"]');
            if (settings['db.msr.data_storage.engine'] === 'ebs') {
                field.setValue(settings['db.msr.data_storage.ebs.encrypted'] == 1);
            }
            field.setVisible(settings['db.msr.data_storage.engine'] === 'ebs');

            //growth
            field = this.down('#changeEbsStorageSettingsBtn');
            field.setVisible(notANewRecord && settings['db.msr.data_storage.engine'] === 'ebs');
            field.setDisabled((tabParams.farm.status || 0) > 0);
            field.setTooltip((tabParams.farm.status || 0) > 0 ? 'For running farm please use Database manager page in farm actions menu.' : '');
            this.down('#changeEbsStorageSettingsInfo').setValue(Ext.decode(settings['db.msr.storage.grow_config'], true));


        }

        this.down('[name="db.msr.data_storage.engine"]').setValue(settings['db.msr.data_storage.engine']);

        //RAID Settings
        this.down('[name="db.msr.data_storage.raid.level"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.raid.volumes_count"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.raid.volume_size"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.raid.ebs.type"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.raid.ebs.iops"]').setDisabled(notANewRecord);

        // Engine & EBS Settings
        this.down('[name="db.msr.data_storage.engine"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.ebs.size"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.ebs.encrypted"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.ebs.type"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.ebs.iops"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.fstype"]').setDisabled(notANewRecord);

        // Cinder settings
        this.down('[name="db.msr.data_storage.cinder.size"]').setDisabled(notANewRecord);

        //GCE Disk settings
        this.down('[name="db.msr.data_storage.gced.size"]').setDisabled(notANewRecord);
        this.down('[name="db.msr.data_storage.gced.type"]').setDisabled(notANewRecord);

        this.isLoading = false;
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
            settings['db.msr.no_data_bundle_on_promote'] = this.down('[name="db.msr.no_data_bundle_on_promote"]').getValue();
            settings['db.msr.data_bundle.compression'] = this.down('[name="db.msr.data_bundle.compression"]').getValue();
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
            settings['db.msr.data_backup.server_type'] = this.down('[name="db.msr.data_backup.server_type"]').getValue();
            settings['db.msr.data_backup.timeframe.start_hh'] = this.down('[name="db.msr.data_backup.timeframe.start_hh"]').getValue();
            settings['db.msr.data_backup.timeframe.start_mm'] = this.down('[name="db.msr.data_backup.timeframe.start_mm"]').getValue();
            settings['db.msr.data_backup.timeframe.end_hh'] = this.down('[name="db.msr.data_backup.timeframe.end_hh"]').getValue();
            settings['db.msr.data_backup.timeframe.end_mm'] = this.down('[name="db.msr.data_backup.timeframe.end_mm"]').getValue();
        } else {
            settings['db.msr.data_backup.enabled'] = 0;
            delete settings['db.msr.data_backup.every'];
            delete settings['db.msr.data_backup.server_type'];
            delete settings['db.msr.data_backup.timeframe.start_hh'];
            delete settings['db.msr.data_backup.timeframe.start_mm'];
            delete settings['db.msr.data_backup.timeframe.end_hh'];
            delete settings['db.msr.data_backup.timeframe.end_mm'];
        }

        if (record.get('new')) {
            settings['db.msr.data_storage.engine'] = this.down('[name="db.msr.data_storage.engine"]').getValue();
            settings['db.msr.data_storage.fstype'] = this.down('[name="db.msr.data_storage.fstype"]').getValue();
        }

        if (settings['db.msr.data_storage.engine'] === 'ebs' || settings['db.msr.data_storage.engine'] === 'csvol') {
            if (record.get('new')) {
                settings['db.msr.data_storage.ebs.size'] = this.down('[name="db.msr.data_storage.ebs.size"]').getValue();
                settings['db.msr.data_storage.ebs.type'] = this.down('[name="db.msr.data_storage.ebs.type"]').getValue();
                settings['db.msr.data_storage.ebs.iops'] = this.down('[name="db.msr.data_storage.ebs.iops"]').getValue();
                settings['db.msr.data_storage.ebs.encrypted'] = this.down('[name="db.msr.data_storage.ebs.encrypted"]').getValue() ? 1 : 0;
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
            delete settings['db.msr.data_storage.ebs.encrypted'];
            delete settings['db.msr.data_storage.ebs.snaps.enable_rotation'];
            delete settings['db.msr.data_storage.ebs.snaps.rotate'];
        }

        settings['db.msr.storage.recreate_if_missing'] = this.down('[name="db.msr.storage.recreate_if_missing"]').getValue();

        if (settings['db.msr.data_storage.engine'] === 'eph') {
            if (record.isMultiEphemeralDevicesEnabled()) {
                settings['db.msr.data_storage.eph.disks'] = this.getDisksCheckboxesValues('eph_checkboxes');
            } else {
                settings['db.msr.data_storage.eph.disk'] = this.down('[name="db.msr.data_storage.eph.disk"]').getValue();
            }
        }

        if (settings['db.msr.data_storage.engine'] === 'lvm') {
            //Remove this settings because if instance type was changed we need to update this setting.
            // Update it manually still not allowed, need consider to allow to change this setting.
            //if (record.get('new')) {
                settings['db.msr.storage.lvm.volumes'] = this.getDisksCheckboxesValues('lvm_settings');
            //}
        }

        if (settings['db.msr.data_storage.engine'] === 'raid.ebs') {
            settings['db.msr.data_storage.raid.level'] = this.down('[name="db.msr.data_storage.raid.level"]').getValue();
            settings['db.msr.data_storage.raid.volume_size'] = this.down('[name="db.msr.data_storage.raid.volume_size"]').getValue();
            settings['db.msr.data_storage.raid.volumes_count'] = this.down('[name="db.msr.data_storage.raid.volumes_count"]').getValue();
            if (settings['db.msr.data_storage.engine'] === 'raid.ebs') {
                settings['db.msr.data_storage.raid.ebs.type'] = this.down('[name="db.msr.data_storage.raid.ebs.type"]').getValue();
                settings['db.msr.data_storage.raid.ebs.iops'] = this.down('[name="db.msr.data_storage.raid.ebs.iops"]').getValue();
            }
        }

        if (settings['db.msr.data_storage.engine'] === 'cinder') {
            settings['db.msr.data_storage.cinder.size'] = this.down('[name="db.msr.data_storage.cinder.size"]').getValue();
        }

        if (settings['db.msr.data_storage.engine'] === 'gce_persistent') {
            settings['db.msr.data_storage.gced.size'] = this.down('[name="db.msr.data_storage.gced.size"]').getValue();
            settings['db.msr.data_storage.gced.type'] = this.down('[name="db.msr.data_storage.gced.type"]').getValue();
        }

        record.set('settings', settings);
    },

    __items: [{
            xtype: 'fieldset',
            name: 'redis_settings',
            hidden: true,
            title: 'Redis settings',
            items: [{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                items: [{
                    xtype: 'combo',
                    name: 'db.msr.redis.persistence_type',
                    fieldLabel: 'Persistence type',
                    editable: false,
                    store: Scalr.constants.redisPersistenceTypes,
                    valueField: 'id',
                    displayField: 'name',
                    width: 410,
                    margin: '0 32 0 0',
                    labelWidth: 185,
                    queryMode: 'local'
                }, {
                    xtype: 'buttongroupfield',
                    name: 'db.msr.redis.use_password',
                    fieldLabel: 'Password auth',
                    labelWidth: 110,
                    defaults: {
                        width: 65
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
                fieldLabel: 'Number of processes',
                minValue: 1,
                maxValue: 16,
                increment: 1,
                labelWidth: 185,
                width: 410,
                margin: '0 0 24 0',
                useTips: false,
                showValue: true
            }]
        }, {
            xtype: 'fieldset',
            title: 'Storage settings',
            cls: 'x-fieldset-no-bottom-padding',
            items: [{
                xtype: 'fieldcontainer',
                layout: 'hbox',
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
                    width: 410,
                    labelWidth: 185,
                    margin: '0 32 0 0',
                    queryMode: 'local',
                    listeners:{
                        change: function(comp, value){
                            var tab = this.up('#dbmsr'),
                                isRaid = value === 'raid.ebs',
                                field, xfsBtn;
                            tab.suspendLayouts();
                            tab.down('[name="ebs_settings"]').setVisible(value === 'ebs' || value === 'csvol');
                            tab.down('[name="eph_settings"]').setVisible(value === 'eph');
                            tab.down('[name="lvm_settings"]').setVisible(value === 'lvm');
                            tab.down('[name="db.msr.data_bundle.compression"]').setVisible(value === 'lvm');
                            tab.down('[name="cinder_settings"]').setVisible(value === 'cinder');
                            tab.down('[name="gced_settings"]').setVisible(value === 'gce_persistent');
                            if (isRaid) {
                                tab.down('[name="raid_settings"]').show();
                                tab.down('#raid_ebs_type').setVisible(value === 'raid.ebs');
                            } else {
                                tab.down('[name="raid_settings"]').hide();
                            }

                            var record = tab.currentRole,
                                settings = record.get('settings');
                            if (record.get('new')) {
                                field = tab.down('[name="db.msr.data_storage.fstype"]');
                                xfsBtn = field.down('[value="xfs"]');
                                if (xfsBtn && !xfsBtn.unavailable) {
                                    if (isRaid && field.getValue() === 'xfs') {
                                        field.setValue('ext3');
                                    }
                                    xfsBtn.setDisabled(isRaid);
                                }
                            }

                            if (!tab.isLoading) {
                                settings[comp.name] = value;
                                record.set('settings', settings);
                            }
                            tab.resumeLayouts(true);
                        }
                    }
                }, {
                    xtype: 'buttongroupfield',
                    name: 'db.msr.data_storage.fstype',
                    fieldLabel: 'Filesystem',
                    labelWidth: 80,
                    defaults: {
                        width: 65,
                        tooltipType: 'title'
                    }
                }]
            },{
                xtype: 'checkbox',
                hideLabel: true,
                name: 'db.msr.storage.recreate_if_missing',
                boxLabel: 'Re-create storage if one or more volumes missing'
            }]
        }, {
            xtype:'fieldset',
            name: 'eph_settings',
            title: 'Ephemeral storage settings',
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
                width: 410,
                labelWidth: 185,
                queryMode: 'local',
                listeners:{
                    change:function(){
                        //TODO:
                    }
                }
            },{
                xtype: 'container',
                itemId: 'eph_checkboxes',
                margin: 0,
                hidden: true
            }]
        }, {
            xtype:'fieldset',
            name: 'lvm_settings',
            itemId: 'lvm_settings',
            title: 'LVM storage settings',
            hidden: true,
            items: [{
                xtype: 'displayfield',
                hideLabel:true,
                value: 'LVM device',
                width: 410
            }]
        }, {
            xtype:'fieldset',
            name: 'ebs_settings',
            title: 'Block storage settings',
            hidden: true,
            items: [{
                xtype: 'displayfield',
                maxWidth: 780,
                cls: 'x-form-field-warning',
                itemId: 'changeEbsStorageSettingsInfo',
                hidden: true,
                anchor: '100%',
                renderer: function(value, field) {
                    if (!value || Ext.Object.getSize(value) === 0) {
                        field.hide();
                    } else {
                        field.show();
                        var changes = [];
                        if (value['volumeType']) {
                            var ebsType = Ext.Array.findBy(Scalr.constants.ebsTypes, function(item){
                                if (item[0] == value['volumeType']) {
                                    return true;
                                }
                            });

                            changes.push(ebsType ? ebsType[1].replace('(' + Scalr.constants.iopsMin + ' - ' + Scalr.constants.iopsMax + '):', '') : value['volumeType']);
                        }
                        if (value['iops']) {
                            changes.push(value['iops']);
                        }
                        if (value['size']) {
                            changes.push(value['size']+'Gb');
                        }
                        return 'Your storage configuration change (to <span class="x-semibold">' + changes.join(' ') + '</span> volume) will be applied and used when your DB master initializes. <a href="#">Cancel</a>';
                    }
                },
                listeners: {
                    afterrender: function() {
                        var me = this,
                            inputEl = me.el;
                        inputEl.on('click', function(e) {
                            var res = inputEl.query('a');
                            if (res.length && e.within(res[0])) {
                                var settings = me.up('#dbmsr').currentRole.get('settings', true);
                                delete settings['db.msr.storage.grow_config'];
                                me.setValue('');

                            }
                            e.preventDefault();
                        });
                    }
                }
            },{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                width: 600,
                items: [{
                    xtype: 'combo',
                    store: Scalr.constants.ebsTypes,
                    fieldLabel: 'EBS type',
                    labelWidth: 185,
                    valueField: 'id',
                    displayField: 'name',
                    editable: false,
                    queryMode: 'local',
                    value: 'standard',
                    name: 'db.msr.data_storage.ebs.type',
                    width: 410,
                    listeners: {
                        change: function (comp, value) {
                            var tab = comp.up('#dbmsr'),
                                iopsField = tab.down('[name="db.msr.data_storage.ebs.iops"]');
                            iopsField.setVisible(value === 'io1');
                            if (tab.currentRole.get('new')) {
                                if (value === 'io1') {
                                    iopsField.reset();
                                    iopsField.setValue(100);
                                } else {
                                    tab.down('[name="db.msr.data_storage.ebs.size"]').isValid();
                                }
                            }
                        }
                    }
                }, {
                    xtype: 'textfield',
                    //itemId: 'db.msr.data_storage.ebs.iops',
                    name: 'db.msr.data_storage.ebs.iops',
                    vtype: 'iops',
                    allowBlank: false,
                    hidden: true,
                    margin: '0 0 0 6',
                    width: 50,
                    listeners: {
                        change: function(comp, value){
                            var tab = comp.up('#dbmsr'),
                                sizeField = tab.down('[name="db.msr.data_storage.ebs.size"]');
                            if (tab.currentRole.get('new')) {
                                if (comp.isValid() && comp.prev().getValue() === 'io1') {
                                    var minSize = Scalr.utils.getMinStorageSizeByIops(value);
                                    if (sizeField.getValue()*1 < minSize) {
                                        sizeField.setValue(minSize);
                                    }
                                }
                            }
                        }
                    }

                }]
            }, {
                xtype: 'fieldcontainer',
                layout: {
                    type: 'hbox',
                    align: 'middle'
                },
                items: [{
                    xtype: 'textfield',
                    name: 'db.msr.data_storage.ebs.size',
                    fieldLabel: 'Storage size',
                    labelWidth: 185,
                    width: 185 + 65,
                    value: '10',
                    vtype: 'ebssize',
                    getEbsType: function() {
                        return this.up('#dbmsr').down('[name="db.msr.data_storage.ebs.type"]').getValue();
                    },
                    getEbsIops: function() {
                        return this.up('#dbmsr').down('[name="db.msr.data_storage.ebs.iops"]').getValue();
                    }
                },{
                    xtype: 'label',
                    text: 'GB',
                    margin: '0 0 0 6'
                }]
            }, {
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
                        {id: 'szrversion', tooltipData: {version: '2.9.25'}}
                    ]
                },
                listeners: {
                    writeablechange: function(comp, readOnly) {
                        this.toggleIcon('question', readOnly && comp.up('#dbmsr').currentRole.get('new'));
                    }
                }
            }, {
                xtype: 'fieldcontainer',
                layout: {
                    type: 'hbox',
                    align: 'middle'
                },
                name: 'ebs_rotation_settings',
                items: [{
                    xtype: 'checkbox',
                    hideLabel: true,
                    name: 'db.msr.data_storage.ebs.snaps.enable_rotation',
                    boxLabel: 'Snapshots are rotated',
                    width: 185 + 5,
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
                    width: 50,
                    margin: '0 6 0 0',
                    vtype: 'num'
                }, {
                    xtype: 'label',
                    text: 'times before being removed.'
                }]
            },{
                xtype: 'button',
                itemId: 'changeEbsStorageSettingsBtn',
                text: 'Change storage configuration',
                margin: '6 0 0 0',
                width: 240,
                hidden: true,
                handler: function(){
                    this.up('#dbmsr').changeEbsStorageSettings();
                }
            }]
        }, {
            xtype:'fieldset',
            name: 'cinder_settings',
            title: 'Cinder Storage settings',
            hidden: true,
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Disk size',
                labelWidth: 185,
                width: 185 + 55,
                name: 'db.msr.data_storage.cinder.size',
                value: 100
            },{
                xtype: 'label',
                text: 'GB',
                margin: '0 0 0 6'
            }]
        }, {
            xtype:'fieldset',
            name: 'gced_settings',
            title: 'GCE persistent disk settings',
            hidden: true,
            items: [{
                xtype: 'fieldcontainer',
                layout: {
                    type: 'hbox',
                    align: 'middle'
                },
                items: [{
	                xtype: 'textfield',
                    fieldLabel: 'Size',
	                width: 225,
	                name: 'db.msr.data_storage.gced.size',
	                value: 100,
                    vtype: 'num'
	            },{
	                xtype: 'label',
	                text: 'GB',
	                margin: '0 0 0 6'
	            }]
		      }, {
                  xtype: 'combo',
                  store: Scalr.constants.gceDiskTypes,
                  width: 225,
                  valueField: 'id',
                  displayField: 'name',
                  fieldLabel: 'Type',
                  editable: false,
                  queryMode: 'local',
                  value: 'pd-standard',
                  name: 'db.msr.data_storage.gced.type',
                  plugins: {
                      ptype: 'fieldicons',
                      position: 'outer',
                      icons: [
                          {id: 'szrversion', tooltipData: {version: '3.5.19'}}
                      ]
                  }
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
                width: 410 ,
                value: '',
                labelWidth: 185,
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
                width: 185 + 55,
                labelWidth: 185,
                queryMode: 'local'
            }, {
                xtype: 'fieldcontainer',
                layout: 'hbox',
                itemId: 'raid_ebs_type',
                width: 600,
                items: [{
                    xtype: 'combo',
                    store: Scalr.constants.ebsTypes,
                    fieldLabel: 'EBS type',
                    valueField: 'id',
                    displayField: 'name',
                    editable: false,
                    queryMode: 'local',
                    name: 'db.msr.data_storage.raid.ebs.type',
                    value: 'standard',
                    width: 410,
                    labelWidth: 185,
                    listeners: {
                        change: function (comp, value) {
                            var tab = comp.up('#dbmsr'),
                                iopsField = tab.down('[name="db.msr.data_storage.raid.ebs.iops"]');
                            iopsField.setVisible(value === 'io1');
                            if (tab.currentRole.get('new')) {
                                if (value === 'io1') {
                                    iopsField.reset();
                                    iopsField.setValue(100);
                                } else {
                                    tab.down('[name="db.msr.data_storage.raid.volume_size"]').isValid();
                                }
                            }
                        }
                    }
                }, {
                    xtype: 'textfield',
                    //itemId: 'db.msr.data_storage.raid.ebs.iops',
                    name: 'db.msr.data_storage.raid.ebs.iops',
                    vtype: 'iops',
                    allowBlank: false,
                    hidden: true,
                    margin: '0 0 0 6',
                    width: 50,
                    listeners: {
                        change: function(comp, value){
                            var tab = comp.up('#dbmsr'),
                                sizeField = tab.down('[name="db.msr.data_storage.raid.volume_size"]');
                            if (tab.currentRole.get('new')) {
                                if (comp.isValid() && comp.prev().getValue() === 'io1') {
                                    var minSize = Scalr.utils.getMinStorageSizeByIops(value);
                                    if (sizeField.getValue()*1 < minSize) {
                                        sizeField.setValue(minSize);
                                    }
                                }
                            }
                        }
                    }
                }]
            }, {
                xtype: 'fieldcontainer',
                layout: {
                    type: 'hbox',
                    align: 'middle'
                },
                items: [{
                    xtype: 'textfield',
                    fieldLabel: 'Each volume size',
                    labelWidth: 185,
                    width: 185 + 55,
                    value: '10',
                    name: 'db.msr.data_storage.raid.volume_size',
                    vtype: 'ebssize',
                    getEbsType: function() {
                        return this.up('#dbmsr').down('[name="db.msr.data_storage.raid.ebs.type"]').getValue();
                    },
                    getEbsIops: function() {
                        return this.up('#dbmsr').down('[name="db.msr.data_storage.raid.ebs.iops"]').getValue();
                    }
                },{
                    xtype: 'label',
                    text: 'GB',
                    margin: '0 0 0 6'
                }]
            }]
        }, {
        xtype: 'fieldset',
        checkboxToggle:  true,
        name: 'db.msr.data_bundle.enabled',
        title: 'Bundle and save data snapshot',
        defaults: {
            labelWidth: 185
        },
        items: [{
            xtype: 'fieldcontainer',
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            hideLabel: true,
            items: [{
                xtype: 'label',
                text: 'Perform data bundle every',
                width: 185 + 5
            }, {
                xtype: 'textfield',
                width: 50,
                name: 'db.msr.data_bundle.every',
                vtype: 'num'
            }, {
                xtype: 'label',
                margin: '0 6',
                text: 'hours'
            }, {
                xtype: 'displayinfofield',
                info:   'DB snapshots contain a hotcopy of the database data directory, a file that holds binary log position and debian.cnf' +
                        '<br>' +
                        'When your farm starts:<br>' +
                        '1. The database master downloads and extracts a snapshot from storage depending on the cloud platform<br>' +
                        '2. When this data is loaded and the master starts, all slaves download and extract a snapshot from storage as well<br>' +
                        '3. Slaves will then sync with the master for some time'
            }]
        }, {
            xtype: 'fieldcontainer',
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            items: [{
                xtype: 'label',
                text: 'Preferred bundle window',
                width: 185 + 5
            }, {
                xtype: 'textfield',
                name: 'db.msr.data_bundle.timeframe.start_hh',
                width: 50,
                vtype: 'num'
            }, {
                xtype: 'label',
                text: ':',
                margin: '0 4'
            }, {
                xtype: 'textfield',
                name: 'db.msr.data_bundle.timeframe.start_mm',
                width: 50,
                vtype: 'num'
            }, {
                xtype: 'label',
                html: '&ndash;',
                margin: '0 6'
            }, {
                xtype: 'textfield',
                name: 'db.msr.data_bundle.timeframe.end_hh',
                width: 50,
                vtype: 'num'
            }, {
                xtype: 'label',
                text: ':',
                margin: '0 4'
            },{
                xtype: 'textfield',
                name: 'db.msr.data_bundle.timeframe.end_mm',
                width: 50,
                vtype: 'num'
            }, {
                xtype: 'displayinfofield',
                margin: '0 0 0 6',
                info: '<span class="x-semibold">Format</span><br/>24 hour<br/>hh : mm'
            }]
        }, {
            xtype: 'combo',
            fieldLabel: 'Compression',
            store: [['', 'No compression (Recommended on small instances)'], ['gzip', 'gzip (Recommended on large instances)']],
            valueField: 'id',
            displayField: 'name',
            editable: false,
            queryMode: 'local',
            value: 'gzip',
            name: 'db.msr.data_bundle.compression',
            labelWidth: 185,
            width: 410
        }, {
            xtype: 'checkbox',
            hideLabel: true,
            name: 'db.msr.data_bundle.use_slave',
            boxLabel: 'Use SLAVE server for data bundle'
        }, {
            xtype: 'checkbox',
            hideLabel: true,
            name: 'db.msr.no_data_bundle_on_promote',
            boxLabel: 'Do not create data bundle during slave to master promotion process'
        }]
    }, {
        xtype: 'fieldset',
        checkboxToggle:  true,
        name: 'db.msr.data_backup.enabled',
        title: 'Backup data (gziped database dump)',
        defaults: {
            labelWidth: 185
        },
        items: [{
            xtype: 'fieldcontainer',
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            hideLabel: true,
            items: [{
                xtype: 'label',
                text: 'Perform backup every',
                width: 185 + 5
            }, {
                xtype: 'textfield',
                width: 50,
                margin: '0 6 0 0',
                name: 'db.msr.data_backup.every',
                vtype: 'num'
            }, {
                xtype: 'label',
                text: 'hours'
            }]
        }, {
            xtype: 'fieldcontainer',
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            items: [{
                xtype: 'label',
                text: 'Preferred backup window',
                width: 185 + 5
            }, {
                xtype: 'textfield',
                name: 'db.msr.data_backup.timeframe.start_hh',
                width: 50,
                vtype: 'num'
            }, {
                xtype: 'label',
                text: ':',
                margin: '0 4'
            }, {
                xtype: 'textfield',
                name: 'db.msr.data_backup.timeframe.start_mm',
                width: 50,
                vtype: 'num'
            }, {
                xtype: 'label',
                html: '&ndash;',
                margin: '0 6'
            }, {
                xtype: 'textfield',
                name: 'db.msr.data_backup.timeframe.end_hh',
                width: 50,
                vtype: 'num'
            }, {
                xtype: 'label',
                text: ':',
                margin: '0 4'
            },{
                xtype: 'textfield',
                name: 'db.msr.data_backup.timeframe.end_mm',
                width: 50,
                vtype: 'num'
            }, {
                xtype: 'displayinfofield',
                margin: '0 0 0 6',
                info: '<span class="x-semibold">Format</span><br/>24 hour<br/>hh : mm'
            }]
        }, {
            xtype: 'combo',
            fieldLabel: 'Perform data backup on',
            store: [['slave', 'SLAVE only'], ['master', 'MASTER only'], ['master-if-no-slaves', 'MASTER if NO slaves']],
            valueField: 'id',
            displayField: 'name',
            editable: false,
            queryMode: 'local',
            name: 'db.msr.data_backup.server_type',
            labelWidth: 185,
            width: 185 + 245
        }]
    }]
});

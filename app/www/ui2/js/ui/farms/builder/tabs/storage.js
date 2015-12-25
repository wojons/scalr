Ext.define('Scalr.ui.FarmRoleEditorTab.Storage', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Storage',
    itemId: 'storage',
    layout: {
        type: 'hbox',
        align: 'stretch'
    },

    settings: {
        storages: undefined
    },

    tabData: null,

    isEnabled: function (record) {
        return this.callParent(arguments) && record.get('platform') != 'azure';
    },

    showWarningRemovedEphemeralDevices: function(devices) {
        var result = [], name;
        Ext.each(devices, function(item) {
            if (item['fs']) {
                name = item['settings']['ec2_ephemeral.name'];
                name = item['mount'] ? name + ' with mountpoint ' + item['mountPoint'] : name;
                result.push(name)
            }
        });

        if (result.length) {
            Ext.defer(function() {
                Scalr.message.Warning('Number of ephemeral devices were decreased. Following devices will be unavailable: ' + result.join(', '));
            }, 200);
        }
    },

    onRoleUpdate: function(record, name, value, oldValue) {
        if (!this.isEnabled(record)) return;
        var me = this,
            storages,
            configs,
            newConfigs,
            removedDevices,
            ephemeralDevices,
            ephemeralDevicesNumber;
        if (name.join('.') === 'settings.aws.instance_type') {

            record.loadInstanceTypeInfo(function(instanceTypeInfo){
                var field,
                    ebsEncryptionSupported = instanceTypeInfo ? instanceTypeInfo.ebsencryption || false : true,
                    osFamily = Scalr.utils.getOsById(record.get('osId'), 'family');
                ephemeralDevices = instanceTypeInfo ? instanceTypeInfo.instancestore : null;
                if (me.isVisible()) {
                    me.tabData['ephemeralDevices'] = ephemeralDevices;
                    me.down('#configuration').clearSelectedRecord();
                    me.refreshEc2EphemeralDevices();
                    field = me.down('[name="type"]');
                    if (field) field.store.loadData(me.getAvailableStorageTypes(record));

                    field = me.down('[name="ebs.encrypted"]');
                    if (field) {
                        field.encryptionSupported = ebsEncryptionSupported;
                        field.setReadOnly(!ebsEncryptionSupported);
                    }
                } else {
                    storages = record.get('storages') || {};
                    configs = storages['configs'] || []
                    newConfigs = [];
                    ephemeralDevicesNumber = (ephemeralDevices || {})['number'] || 0;
                    removedDevices = [];
                    Ext.Array.each(configs, function(storage){
                        if (storage['type'] === 'ec2_ephemeral') {
                            if (storage['settings']['ec2_ephemeral.name'].replace('ephemeral', '')*1 >= ephemeralDevicesNumber) {
                                removedDevices.push(storage);
                                return true;
                            } else {
                                storage['settings']['ec2_ephemeral.size'] = ephemeralDevices['size'];
                            }
                        }
                        newConfigs.push(storage);
                    });
                    for (var i=0;i<ephemeralDevicesNumber;i++) {
                        if (Ext.Array.every(newConfigs, function(storage){return !(storage['type'] === 'ec2_ephemeral' && storage['settings']['ec2_ephemeral.name'] === 'ephemeral' + i); })) {
                            newConfigs.push(Ext.merge({
                                type: 'ec2_ephemeral',
                                reUse: 0,
                                settings: {
                                    'ec2_ephemeral.name': 'ephemeral' + i,
                                    'ec2_ephemeral.size': ephemeralDevices['size']
                                }
                            }, i == 0 && osFamily !== 'windows' ? {
                                mount: true,
                                mountPoint: '/mnt',
                                fs: 'ext3'
                            } : {}));
                        }
                    }
                    storages['configs'] = newConfigs;
                    record.set('storages', storages);
                    if (removedDevices.length) {
                        me.showWarningRemovedEphemeralDevices(removedDevices);
                    }
                }
            });
        }
    },

    getAvailableStorageTypes: function(roleRecord, storageRecord) {
        var platform, os, result;
        roleRecord = roleRecord || this.currentRole;
        platform = roleRecord.get('platform');
        os = Scalr.utils.getOsById(roleRecord.get('osId')) || {};
        result = [];
        if (platform === 'ec2') {
            if (!storageRecord || storageRecord.get('type') !== 'ec2_ephemeral') {
                result.push({name: 'ebs', description: 'EBS volume'});
                if (os.family !== 'windows') {
                    result.push({name: 'raid.ebs', description: 'RAID array (on EBS)'});
                }
            }
            if (this.tabData['ephemeralDevices'] && this.getAvailableEc2EphemeralDevices(storageRecord).length) {
                result.push({name: 'ec2_ephemeral', description: 'Ephemeral device'});
            }
        } else if (Scalr.isCloudstack(platform)) {
            result.push(
                {name: 'csvol', description: 'CS volume'},
                {name: 'raid.csvol', description: 'RAID array (on CS volumes)'}
            );
        } else if (platform === 'gce') {
            if (!storageRecord || storageRecord.get('type') !== 'gce_ephemeral') {
                result.push({name: 'gce_persistent', description: 'Persistent disk'});
            }
            if ((!storageRecord || !storageRecord.get('type') || storageRecord.get('type') === 'gce_ephemeral') && this.getAvailableGceEphemeralDevices(storageRecord).length) {
                result.push({name: 'gce_ephemeral', description: 'Local SSD disk (ephemeral)'});
            }
        } else if (Scalr.isOpenstack(platform)) {
            result.push(
                {name: 'cinder', description: 'Persistent disk'},
                {name: 'raid.cinder', description: 'RAID array (on Persistent disks)'}
            );
        }

        return result;
    },

    getAvailableEc2EphemeralDevices: function(storageRecord) {
        var ephemeralDevices = [],
            storages = this.down('#configuration').store.getUnfiltered(),
            editor = this.down('#editor');
        storageRecord = storageRecord || editor.getRecord();
        for (var i=0;i<this.tabData['ephemeralDevices']['number'];i++) {
            var bypass = false;
            storages.each(function(rec){
                if (storageRecord !== rec && rec.get('type') === 'ec2_ephemeral' && rec.get('settings')['ec2_ephemeral.name'] === 'ephemeral' + i) {
                    bypass = true;
                    return false;
                }
            });
            if (!bypass) {
                ephemeralDevices.push({name: 'ephemeral' + i, description: 'ephemeral' + i, size: this.tabData['ephemeralDevices']['size']});
            }
        }

        return ephemeralDevices;
    },

    getAvailableGceEphemeralDevices: function(storageRecord) {
        var ephemeralDevices = [],
            storages = this.down('#configuration').store.getUnfiltered(),
            editor = this.down('#editor');
        storageRecord = storageRecord || editor.getRecord();
        for (var i=0;i<4;i++) {
            var bypass = false;
            storages.each(function(rec){
                if (storageRecord !== rec && rec.get('type') === 'gce_ephemeral' && rec.get('settings')['gce_ephemeral.name'] === 'google-local-ssd-' + i) {
                    bypass = true;
                    return false;
                }
            });
            if (!bypass) {
                ephemeralDevices.push({name: 'google-local-ssd-' + i, description: 'google-local-ssd-' + i});
            }
        }

        return ephemeralDevices;
    },

    refreshEc2EphemeralDevices: function() {
        var me = this,
            osFamily = Scalr.utils.getOsById(me.currentRole.get('osId'), 'family'),
            store = me.down('#configuration').store,
            ephemeralDevicesNumber = (me.tabData['ephemeralDevices'] || {})['number'] || 0,
            recordsToRemove = [],
            recordsToAdd = [];
        store.getUnfiltered().each(function(record){
            if (record.get('type') === 'ec2_ephemeral') {
                if ((record.get('settings')['ec2_ephemeral.name'].replace('ephemeral', '')*1 >= ephemeralDevicesNumber)) {
                    recordsToRemove.push(record);
                } else {
                    var settings = record.get('settings');
                    settings['ec2_ephemeral.size'] = me.tabData['ephemeralDevices']['size'];
                    record.set(settings);
                }
            }
        });
        if (recordsToRemove.length) {
            me.showWarningRemovedEphemeralDevices(Ext.Array.map(recordsToRemove, function(item) { return item.data; }));
            store.remove(recordsToRemove);
        }
        for (var i=0;i<ephemeralDevicesNumber;i++) {
            if (!store.getUnfiltered().findBy(function(storage){return storage.get('type') === 'ec2_ephemeral' && storage.get('settings')['ec2_ephemeral.name'] === 'ephemeral' + i; })) {
                recordsToAdd.push(Ext.merge({
                    type: 'ec2_ephemeral',
                    reUse: 0,
                    settings: {
                        'ec2_ephemeral.name': 'ephemeral' + i,
                        'ec2_ephemeral.size': me.tabData['ephemeralDevices']['size']
                    }
                }, i == 0 && osFamily !== 'windows' ? {
                    mount: true,
                    mountPoint: '/mnt',
                    fs: 'ext3'
                } : {}));
            }
        }
        if (recordsToAdd.length) {
            store.loadData(recordsToAdd, true);
        }
    },

    beforeShowTab: function (record, handler) {
        var me = this,
            platform = record.get('platform'),
            cloudLocation = record.get('cloud_location');
        me.tabData = {};
        Scalr.cachedRequest.load(
            {
                url: '/platforms/xGetRootDeviceInfo/',
                params: {
                    cloudLocation: cloudLocation,
                    platform: platform,
                    roleId: record.get('role_id')
                }
            },
            function(data, status){
                if (status) {
                    me.tabData['rootDeviceConfig'] = data;
                    if (Scalr.isCloudstack(platform)) {
                        Scalr.cachedRequest.load(
                            {
                                url: '/platforms/cloudstack/xGetOfferingsList/',
                                params: {
                                    cloudLocation: cloudLocation,
                                    platform: platform,
                                    farmRoleId: record.get('new') ? '' : record.get('farm_role_id')
                                }
                            },
                            function(data, status){
                                Ext.applyIf(me.tabData, data);
                                status ? handler() : me.deactivateTab();
                            },
                            me
                        );
                    } else if (Scalr.isOpenstack(platform)) {
                        Scalr.cachedRequest.load(
                            {
                                url: '/platforms/openstack/xGetOpenstackResources',
                                params: {
                                    cloudLocation: cloudLocation,
                                    platform: platform
                                }
                            },
                            function(data, status){
                                Ext.applyIf(me.tabData, data);
                                status ? handler() : me.deactivateTab();
                            },
                            me
                        );
                    } else if (platform === 'ec2') {
                        record.loadInstanceTypeInfo(function(instanceTypeInfo){
                            var field = me.down('[name="ebs.encrypted"]'),
                                ebsEncryptionSupported = instanceTypeInfo ? instanceTypeInfo.ebsencryption || false : true,
                                kmsKeys = ((Scalr.getGovernance('ec2', 'aws.kms_keys') || {})[cloudLocation] || {})['keys'];
                            //ephemeraldevices
                            me.tabData['ephemeralDevices'] = instanceTypeInfo ? instanceTypeInfo.instancestore : null;
                            //ebsencryption
                            field.encryptionSupported = ebsEncryptionSupported;
                            field.setReadOnly(!ebsEncryptionSupported);

                            field = me.down('[name="ebs.kms_key_id"]');
                            field.toggleIcon('governance', kmsKeys !== undefined)
                            if (kmsKeys === undefined) {
                                Scalr.cachedRequest.load(
                                    {
                                        url: '/platforms/ec2/xGetKmsKeysList',
                                        params: {
                                            cloudLocation: cloudLocation
                                        }
                                    },
                                    function(data, status){
                                        field.store.load({data: data['keys']});
                                        status ? handler() : me.deactivateTab();
                                    },
                                    me
                                );
                            } else {
                                field.store.load({data: kmsKeys});
                                status ? handler() : me.deactivateTab();
                            }
                        });
                    } else {
                        handler();
                    }
                } else {
                    me.deactivateTab();
                }
            },
            me,
            1//ttl
        );
    },

    showTab: function (record) {
        var me = this,
            settings = record.get('settings', true),
            platform = record.get('platform'),
            storages = record.get('storages', true) || {},
            storagesUsage = storages['devices'] || [],
            os = Scalr.utils.getOsById(record.get('osId')) || {},
            rootStorage,
            storagesToLoad,
            field,
            errors = record.get('errors', true) || {},
            invalidIndex,
            volumeTypes;

        if (Ext.isObject(errors) && Ext.isObject(errors['storages'])) {
            invalidIndex = errors['storages'].invalidIndex;
        }

        field = me.down('#configuration');
        if (me.tabData['rootDeviceConfig']) {
            if (settings['base.root_device_config']) {
                rootStorage = Ext.decode(settings['base.root_device_config']);
            } else {
                rootStorage = Ext.applyIf({
                    mount: true,
                    rebuild: true,
                    reUse: false,
                    isRootDevice: true
                }, me.tabData['rootDeviceConfig']);
            }
        }
        storagesToLoad = Ext.Array.merge(storages['configs'] || [], rootStorage ? [rootStorage] : []);
        Ext.Array.each(storagesToLoad, function(storage){
            if (Ext.isArray(storagesUsage[storage['id']])) {
                storage['usage'] = storagesUsage[storage['id']];
            }
        });
        field.store.loadData(storagesToLoad);
        if (platform === 'ec2') {
            me.refreshEc2EphemeralDevices();
        }
        var grouping = field.getView().findFeature('grouping');
        if (grouping.groupCache['Ephemeral storage']) {
            grouping.collapse('Ephemeral storage');
        }

        if (Scalr.isCloudstack(platform)) {
            me.down('[name="csvol.disk_offering_id"]').store.load({data: me.tabData['diskOfferings'] || []});
            me.down('[name="csvol.snapshot_id"]').store.getProxy().params = {
                platform: record.get('platform'),
                cloudLocation: record.get('cloud_location')
            };
        } else if (Scalr.isOpenstack(platform)) {
            field = me.down('[name="cinder.volume_type"]');
            volumeTypes = me.tabData['volume_types'] || [];
            field.setVisible(volumeTypes.length > 0).setDisabled(!volumeTypes.length);
            field.store.load({data: volumeTypes});
        }

        // Storage filesystem
        var data = [];

        if (os.family !== 'windows') {
            data.push({ fs: 'ext3', description: 'Ext3' });
            if ((os.family == 'centos' && record.get('image', true)['architecture'] == 'x86_64') ||
                (os.family == 'ubuntu' && Ext.Array.contains(['10.04', '12.04', '14.04'], os.generation))
                ) {
                data.push({ fs: 'ext4', description: 'Ext4'});
                data.push({ fs: 'xfs', description: 'XFS'});
            }
        } else if (platform === 'ec2') {
            data.push({ fs: 'ntfs', description: 'NTFS'});
        }

        field = me.down('[name="fs"]');
        field.reset();
        field.store.loadData(data);
        field.fsIsAvailableForPlatformAndOsFamily = os.family !== 'windows' || platform === 'ec2';
        field.setDisabled(!field.fsIsAvailableForPlatformAndOsFamily);
        field.setVisible(field.fsIsAvailableForPlatformAndOsFamily);

        me.down('#editor').hide();

        if (Ext.isNumeric(invalidIndex)) {
            var grid = me.down('#configuration');
            cb = function(){
                grid.setSelectedRecord(grid.store.getAt(invalidIndex + (me.tabData['rootDeviceConfig'] ? 1 : 0)));
                me.down('#editor').isValid();
            };
            if (me.rendered) {
                cb();
            } else {
                me.on('afterrender', cb);
            }
        }

    },

    hideTab: function (record) {
        var grid = this.down('#configuration');

        grid.clearSelectedRecord();

        var c = record.get('storages') || {},
            s = this.getStorages();
        c['configs'] = s['storages'];
        record.set('storages', c);

        if (s.root) {
            var settings = record.get('settings');
            settings['base.root_device_config'] = Ext.encode(s.root);
            record.set('settings', settings);
        }
        grid.store.removeAll();
    },

    getStorages: function() {
        var storages = [],
            root,
            grid = this.down('#configuration');
        grid.store.getUnfiltered().each(function(record) {
            var data = record.getData();
            delete data['extInternalId'];
            delete data['usage'];
            if (!record.get('readOnly')) {
                if (record.get('isRootDevice')) {
                    root = data;
                } else {
                    storages.push(data);
                }
            }
        });
        return {storages: storages, root: root};
    },

    getAvailableWindowsDisks: function(current) {
        var a = 65,
            disks = {},
            store = this.down('#configuration').store;
        for (var i = 3; i<26; i++) {
            var letter = String.fromCharCode(a + i);
            if (letter === current || !store.getUnfiltered().findBy(function(storage){return storage.get('mountPoint') == letter; })) {
                disks[letter] = letter;
            }
        }
        return disks;
    },

    showStorageUsageInfo: function(data) {
        Scalr.utils.Window({
            width: 800,
            title: 'Storage usage',
            layout: 'fit',
            scrollable: false,

            items: [{
                xtype: 'grid',
                padding: '0 12 12',
                disableSelection: true,
                trackMouseOver: false,
                viewConfig: {
                    deferEmptyText: false,
                    emptyText: 'Selected storage is not in use.'
                },
                store: {
                    proxy: 'object',
                    fields: [ 'serverIndex', 'serverId', 'serverInstanceId', 'farmRoleId', 'storageId', 'storageConfigId', 'placement' ],
                    data: data
                },
                columns: [
                    { header: 'Server Index', width: 125, sortable: true, dataIndex: 'serverIndex' },
                    { header: 'Server Id', flex: 1, sortable: true, dataIndex: 'serverId', xtype: 'templatecolumn', tpl:
                        '<tpl if="serverId"><a href="#/servers/{serverId}/dashboard">{serverId}</a> <tpl if="serverInstanceId">({serverInstanceId})</tpl><tpl else>Not running</tpl>'
                    },
                    { header: 'Storage Id', width: 130, sortable: true, dataIndex: 'storageId' },
                    { header: 'Placement', width: 110, sortable: true, dataIndex: 'placement' },
                    { width: 46, sortable: false, dataIndex: 'config', xtype: 'templatecolumn', tpl:
                        '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-info" data-qtip="View config" />'
                    }, {
                        xtype: 'templatecolumn', width: 42, sortable: false, resizable: false, dataIndex: 'id', align: 'center', tpl: [
                            '<tpl if="serverId==\'\'">',
                                '<img class="delete-volume x-grid-icon x-grid-icon-delete" data-qtip="Delete volume" src="'+Ext.BLANK_IMAGE_URL+'" />',
                            '<tpl else>',
                                '<img class="x-grid-icon x-grid-icon-simple x-grid-icon-delete" data-qtip="You can delete volume only if server is not running" src="'+Ext.BLANK_IMAGE_URL+'" />',
                            '</tpl>'
                        ],
                        hidden: !Scalr.flags['betaMode']
                    }
                ],

                listeners: {
                    itemclick: function (view, record, item, index, e) {
                        if (e.getTarget('img.x-grid-icon-info')) {
                            Scalr.Request({
                                processBox: {
                                    type: 'action',
                                    msg: 'Loading config ...'
                                },
                                url: '/farms/builder/xGetStorageConfig',
                                params: {
                                    farmRoleId: record.get('farmRoleId'),
                                    configId: record.get('storageConfigId'),
                                    serverIndex: record.get('serverIndex')
                                },
                                success: function(data) {
                                    Scalr.utils.Window({
                                        xtype: 'form',
                                        title: 'Storage config',
                                        width: 800,
                                        layout: 'fit',
                                        bodyCls: 'x-container-fieldset',
                                        items: [{
                                            xtype: 'codemirror',
                                            readOnly: true,
                                            value: JSON.stringify(data.config, null, "\t"),
                                            mode: 'application/json',
                                            margin: '0 0 12 0'
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
                                                text: 'Close',
                                                handler: function() {
                                                    this.up('#box').close();
                                                }
                                            }]
                                        }]
                                    });
                                }
                            });
                            e.preventDefault();

                        } else if (e.getTarget('img.delete-volume')) {
                            Scalr.Request({
                                confirmBox: {
                                    type: 'delete',
                                    msg: 'Are you sure want to remove volume ?'
                                },
                                processBox: {
                                    type: 'delete',
                                    msg: 'Loading volume ...'
                                },
                                url: '/farms/builder/xRemoveStorageVolume',
                                params: {
                                    farmRoleId: record.get('farmRoleId'),
                                    storageId: record.get('storageId')
                                },
                                success: function() {

                                }
                            });
                            e.preventDefault();
                        }
                    }
                }
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
                    text: 'Close',
                    maxWidth: 140,
                    handler: function (button) {
                        button.up('panel').close();
                    }
                }]
            }]
        });
    },
    __items: [{
        xtype: 'grid',
        itemId: 'configuration',
        maxWidth: 900,
        minWidth: 460,
        flex: 1,
        cls: 'x-panel-column-left x-panel-column-left-with-tabs',
        padding: '18 0 0',
        features: [{
            ftype: 'grouping',
            restoreGroupsState: true,
            groupHeaderTpl: '{name} ({[values.children.length]})'
        },{
            ftype: 'addbutton',
            text: 'Add storage',
            handler: function() {
                var currentRole = this.grid.up('#storage').currentRole,
                    form = this.grid.up('#storage').down('#editor'),
                    osFamily = Scalr.utils.getOsById(currentRole.get('osId'), 'family');
                    storageDefaults = {
                        reUse: Scalr.getDefaultValue('STORAGE_RE_USE')
                    };

                if (osFamily === 'windows') {
                    if (currentRole.get('platform') === 'ec2') {
                        storageDefaults['type'] = 'ebs';
                        storageDefaults['fs'] = 'ntfs';
                    } else if (Scalr.isOpenstack(currentRole.get('platform'))) {
                        storageDefaults['type'] = 'cinder';
                    } else if (Scalr.isCloudstack(currentRole.get('platform'))) {
                        storageDefaults['type'] = 'csvol';
                    } else if (currentRole.get('platform') === 'gce') {
                        storageDefaults['type'] = 'gce_persistent';
                    }
                }

                this.grid.clearSelectedRecord();
                form.loadRecord(this.grid.store.createModel(storageDefaults));
                if (!form.hasInvalidField()) {
                    form.onLiveUpdate();//add record to store if form is valid
                }
            }
        }],
        plugins: [{
            ptype: 'selectedrecord',
            getForm: function() {
                return this.grid.up('#storage').down('form');
            }
        },{
            ptype: 'focusedrowpointer',
            thresholdOffset: 26
        }],
        viewConfig: {
            getRowClass: function(record) {
                if (record.get('status') === 'Pending delete')
                    return 'x-grid-row-strikeout';
            }
        },
        store: {
            model: Scalr.getModel({
                fields: [
                    'id', 'type', 'fs', 'settings', 'mount', 'mountPoint', 'label', 'reUse',
                    {name: 'status', defaultValue: ''},
                    'rebuild',
                    {name: 'isRootDevice', defaultValue: false},
                    {name: 'readOnly', defaultValue: false},
                    {name: 'category', depends: ['type'], convert: function(v, record) {
                        return Ext.Array.contains(['ec2_ephemeral', 'gce_ephemeral'], record.data.type) ? 'Ephemeral storage' : ' Persistent storage';
                    }},
                    {name: 'usage', defaultValue: null}
                ]
            }),
            groupField: 'category',
            sorters: [{
                property: 'isRootDevice',
                direction: 'DESC'
            },{
                sorterFn: function(rec1, rec2){
                    if (rec1.data.type === 'ec2_ephemeral' && rec2.data.type === 'ec2_ephemeral') {
                        var name1 = rec1.data.settings['ec2_ephemeral.name'] || '',
                            name2 = rec2.data.settings['ec2_ephemeral.name'] || '';
                        return  name1.replace('ephemeral', '')*1 > name2.replace('ephemeral', '')*1  ? 1 : -1;
                    } else if (rec1.data.type === 'gce_ephemeral' && rec2.data.type === 'gce_ephemeral') {
                        var name1 = rec1.data.settings['gce_ephemeral.name'] || '',
                            name2 = rec2.data.settings['gce_ephemeral.name'] || '';
                        return  name1.replace('google-local-ssd-', '')*1 > name2.replace('google-local-ssd-', '')*1  ? 1 : -1;
                    } else {
                        return 0;
                    }
                }
            }]
        },
        columns: [
            { header: 'Mount point', flex: 1.7, sortable: false, dataIndex: 'mountPoint', xtype: 'templatecolumn', tpl:
                '<tpl if="values.mount && values.mountPoint">' +
                    '<tpl if="values.fs==\'ntfs\'">'+
                        '<tpl if="label">'+
                            '{label} ({mountPoint}:)' +
                        '<tpl else>'+
                            '{mountPoint}:' +
                        '</tpl>'+
                    '<tpl else>'+
                        '{mountPoint}' +
                    '</tpl>'+
                '<tpl else>&mdash;</tpl>'
            },
            { header: 'Type', flex: 2, sortable: false, dataIndex: 'type', xtype: 'templatecolumn', tpl:
                new Ext.XTemplate('{[this.name(values)]}', {
                    name: function(values) {
                        var type = values.type,
                            res,
                            l = {
                                'ebs': 'EBS volume',
                                'csvol': 'CS volume',
                                'cinder': 'Persistent disk',
                                'gce_persistent': 'Persistent disk',
                                'raid.ebs': 'RAID array (on EBS)',
                                'raid.csvol': 'RAID array (on CS volumes)',
                                'raid.cinder': 'RAID array (on PDs)',
                                'instance-store': 'Ephemeral device'
                            };

                            if (type === 'ec2_ephemeral') {
                                res = values['settings']['ec2_ephemeral.name'];
                            } else if (type === 'gce_ephemeral') {
                                res = values['settings']['gce_ephemeral.name'];
                            } else {
                                res = type ? (l[type] || type) + (type === 'ebs' && values['settings']['ebs.snapshot'] ? ' ('+ values['settings']['ebs.snapshot'] + ')' : ''): '&mdash;';
                            }

                        return res;
                    }
                })
            },
            { header: 'Size', width: 100, sortable: false, xtype: 'templatecolumn', tpl:
                new Ext.XTemplate(
                    '{[this.getSize(values)]}', {
                    getSize: function(v) {
                        var s;
                        if (Ext.Array.contains(['raid.ebs', 'ebs'], v.type)) {
                            s = v['settings']['ebs.size'];
                        } else if (v.type === 'ec2_ephemeral') {
                            s = v['settings']['ec2_ephemeral.size'];
                        } else if (v.type === 'gce_ephemeral') {
                            s = v['settings']['gce_ephemeral.size'];
                        } else if (Ext.Array.contains(['raid.csvol', 'csvol'], v.type)) {
                            s = v['settings']['csvol.size'];
                        } else if (Ext.Array.contains(['raid.cinder', 'cinder'], v.type)) {
                            s = v['settings']['cinder.size'];
                        } else if (v.type === 'gce_persistent') {
                            s = v['settings']['gce_persistent.size'];
                        }

                        return s ? Ext.String.htmlEncode(s)+ ' GB' : '&mdash;';
                    }
                })
            },
            { header: 'Re-use', width: 68, xtype: 'templatecolumn', sortable: false, align: 'center', tpl:
                '<tpl if="reUse"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"><tpl else>&mdash;</tpl>'
            },
            {
                xtype: 'templatecolumn',
                tpl:
                    '<img src="'+Ext.BLANK_IMAGE_URL+'" style="width:20px;height:20px;margin-right:9px" <tpl if="usage && usage.length">class="x-grid-icon x-grid-icon-information" title="Storage usage"</tpl>/>'+
                    '<tpl if="status!=\'Pending delete\'&&!isRootDevice&&type!=\'ec2_ephemeral\'">' +
                        '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-delete" title="Delete storage" />'+
                    '</tpl>',
                width: 68,
                sortable: false,
                resizable: false,
                dataIndex: 'id',
                align:'left'
        }],

        listeners: {
            itemclick: function (view, record, item, index, e) {
                if (e.getTarget('img.x-grid-icon-delete')) {
                    if (!record.get('id')) {
                        view.getStore().remove(record);
                    } else {
                        record.set('status', 'Pending delete');
                        view.up().clearSelectedRecord();
                    }
                    return false;
                } else if (e.getTarget('img.x-grid-icon-information')) {
                    view.up('#storage').showStorageUsageInfo(record.get('usage'));
                    return false;
                }
            }
        }

    },{
        xtype: 'container',
        layout: 'fit',
        flex: .7,
        margin: 0,
        items: [{
            xtype: 'container',
            cls: 'x-container-fieldset',
            hidden: true,
            items: {
                xtype: 'displayfield',
                width: '100%',
                cls: 'x-form-field-info',
                value: 'Root device configuration is not available for this Farm Role (the root device may not be a volume)'
            }
        },{
            xtype: 'form',
            itemId: 'editor',
            margin: 0,
            overflowY: 'auto',
            isRecordLoading: 0,
            listeners: {
                render: function() {
                    var me = this,
                        form = me.getForm();
                    form.getFields().each(function(){
                        this.on('change', me.onLiveUpdate, me);
                    });
                },
                beforeloadrecord: function(record) {
                    var readOnly = record.get('readOnly');
                    //storage engine
                    this.down('[name="type"]').store.loadData(this.up('#storage').getAvailableStorageTypes(null, record));

                    this.prev().setVisible(readOnly);
                    return (record.get('status') === 'Pending create' || record.get('status') == '') && !readOnly;
                },
                loadrecord: function(record) {
                    var form = this.getForm(),
                        field;
                    form.setValues(record.get('settings'));
                    form.clearInvalid();
                    this.toggleReadOnly(record);
                    if (form.findField('fs').getValue() === 'ntfs') {
                        field = form.findField('mountPointNtfs');
                        field.store.load({data: this.up('#storage').getAvailableWindowsDisks(record.get('mountPoint'))});
                        field.setValue(record.get('mountPoint'));
                    }
                    if (!this.isVisible()) {
                        this.setVisible(true);
                    }
                },
                updaterecord: function(record) {
                    this.updateSettings();
                }
            },
            toggleReadOnly: function(record) {
                var isRootDevice = record.get('isRootDevice'),
                    fsCount,
                    field;
                field = this.down('[name="type"]');
                field.setReadOnly(isRootDevice || field.store.getCount()===1 && field.getValue());

                field = this.down('[name="fs"]');
                fsCount = field.store.getCount();
                if (field.fsIsAvailableForPlatformAndOsFamily) {
                    field.setDisabled(isRootDevice || !record.get('mount'));
                    field.setVisible(!isRootDevice);
                }
                if (!isRootDevice && fsCount === 1) {
                    field.setValue(field.store.first());
                }
                field.setReadOnly(fsCount === 1 && field.getValue());

                this.down('#mountCt').setVisible(field.fsIsAvailableForPlatformAndOsFamily || isRootDevice);

                this.down('[name="mount"]').setReadOnly(isRootDevice);
                this.down('[name="mountPoint"]').setReadOnly(isRootDevice);
                if (isRootDevice) {
                    this.down('[name="reUse"]').hide();
                    this.down('[name="rebuild"]').hide();
                }
                this.down('[name="ebs.snapshot"]').setReadOnly(isRootDevice);
                if (isRootDevice) {
                    this.down('[name="ebs.encrypted"]').hide();
                }
            },
            isSettingsField: function(name) {
                return name.indexOf('ebs') === 0 || name.indexOf('raid') === 0 || name.indexOf('csvol') === 0 || name.indexOf('cinder') === 0 || name.indexOf('gce_persistent') === 0 || name.indexOf('ec2_ephemeral') === 0 || name.indexOf('gce_ephemeral') === 0;
            },
            updateSettings: function() {
                var me = this,
                    form = me.getForm(),
                    settings = {};
                Ext.Object.each(form.getFieldValues(), function(name, value){
                    if (me.isSettingsField(name)) {
                        settings[name] = value;
                    } else if (name === 'mountPointNtfs') {
                        form.getRecord().set('mountPoint', value);
                    }
                });
                form.getRecord().set('settings', settings);
            },
            onLiveUpdate: function(field, value, oldValue) {
                if (this.isRecordLoading > 0) return;
                var me = this,
                    record = me.getRecord();

                if (me.isValid()) {
                    me.updateRecord();
                    var conf = me.up('#storage').down('#configuration');
                    if (!record.store) {
                        var groupingFeature = conf.view.findFeature('grouping');
                        groupingFeature.disable();//bugfix v5.0.1: updating group field fix
                        conf.store.add(record);
                        groupingFeature.enable();
                        if (groupingFeature.groupCache[record.get('category')]) {
                            groupingFeature.expand(record.get('category'));
                        }
                        conf.setSelectedRecord(record);
                        if (field && field.xtype === 'textfield') {//firefox resets cursor position, we have to fix this
                            field.selectText(100,100);
                        }
                    }
                }
            },

            items: [{
                xtype: 'fieldset',
                title: 'Storage configuration',
                itemId: 'settings',
                defaults: {
                    labelWidth: 116,
                    anchor: '100%',
                    maxWidth: 480
                },
                items: [{
                    xtype: 'combo',
                    name: 'type',
                    fieldLabel: 'Storage engine',
                    hideInputOnReadOnly: true,
                    editable: false,
                    store: {
                        fields: [ 'description', 'name', 'isEphemeral', 'size' ],
                        proxy: 'object'
                    },
                    valueField: 'name',
                    displayField: 'description',
                    queryMode: 'local',
                    allowBlank: false,
                    emptyText: 'Please select storage engine',
                    listeners: {
                        change: function(comp, value) {
                            var editor = comp.up('#editor'),
                                tab = editor.up('#storage'),
                                ebs = editor.down('#ebs_settings'),
                                ebsSnapshots = editor.down('[name="ebs.snapshot"]'),
                                ebsEncrypted = editor.down('[name="ebs.encrypted"]'),
                                raid = editor.down('#raid_settings'),
                                csvol = editor.down('#csvol_settings'),
                                cinder = editor.down('#cinder_settings'),
                                gce = editor.down('#gce_settings'),
                                platform = tab.currentRole.get('platform'),
                                field;

                            ebs.setVisible(value == 'ebs' || value == 'raid.ebs');
                            ebs.setDisabled(value != 'ebs' && value != 'raid.ebs');
                            ebsSnapshots.setVisible(value == 'ebs' && platform == 'ec2');
                            ebsEncrypted.setVisible(value == 'ebs' && platform == 'ec2').setValue(false);
                            editor.down('[name="ebs.type"]').setReadOnly(platform !== 'ec2', false);

                            raid.setVisible(value == 'raid.ebs' || value == 'raid.csvol' || value == 'raid.cinder');
                            raid.setDisabled(value != 'raid.ebs' && value != 'raid.csvol' && value != 'raid.cinder');

                            csvol.setVisible(value == 'csvol' || value == 'raid.csvol');
                            csvol.setDisabled(value != 'csvol' && value != 'raid.csvol');

                            cinder.setVisible(value == 'cinder' || value == 'raid.cinder');
                            cinder.setDisabled(value != 'cinder' && value != 'raid.cinder');

                            gce.setVisible(value == 'gce_persistent');
                            gce.setDisabled(value != 'gce_persistent');

                            if (value == 'raid.ebs' || value == 'raid.csvol' || value == 'raid.cinder') {
                                // set default values for raid configuration
                                raid.down('[name="raid.level"]').setValue('10');
                                editor.down('[name="fs"]').setValue('ext3');
                            }

                            if ( value === 'csvol' || value === 'raid.csvol') {
                                field = editor.down('[name="csvol.disk_offering_id"]');
                                if (!field.getValue()) {
                                    field.setValue(field.store.first());
                                }
                            } else if (value == 'cinder' || value == 'raid.cinder') {
                                field = editor.down('[name="cinder.volume_type"]');
                                if (!field.getValue()) {
                                    field.setValue(field.store.first());
                                }
                            }

                            if (value === 'ec2_ephemeral') {
                                editor.down('#ec2_ephemeral_settings').show().enable();
                                editor.down('[name="reUse"]').hide().setValue(false);
                                editor.down('[name="rebuild"]').hide();
                                field = editor.down('#ec2_ephemeral_settings').down('[name="ec2_ephemeral.name"]');
                                field.store.loadData(tab.getAvailableEc2EphemeralDevices());
                                field.setValue(field.store.first());
                                field.setReadOnly(field.store.getCount()<2);
                            } else {
                                editor.down('#ec2_ephemeral_settings').hide().disable();
                            }

                            if (value === 'gce_ephemeral') {
                                editor.down('#gce_ephemeral_settings').show().enable();
                                editor.down('[name="reUse"]').hide().setValue(false);
                                editor.down('[name="rebuild"]').hide();
                                field = editor.down('#gce_ephemeral_settings').down('[name="gce_ephemeral.name"]');
                                field.store.loadData(tab.getAvailableGceEphemeralDevices());
                                field.setValue(field.store.first());
                                field.setReadOnly(field.store.getCount()<2);
                                editor.down('#gce_ephemeral_settings').down('[name="gce_ephemeral.size"]').setValue(375);
                            } else {
                                editor.down('#gce_ephemeral_settings').hide().disable();
                            }

                            if (value !== 'ec2_ephemeral' && value !== 'gce_ephemeral') {
                                editor.down('[name="reUse"]').show();
                                editor.down('[name="rebuild"]').show();
                            }

                        }
                    }
                }, {
                    xtype: 'checkbox',
                    name: 'reUse',
                    boxLabel: 'Reuse this storage if the instance is replaced',
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        icons: [{
                            id: 'info',
                            tooltip: 'If an instance is terminated, reattach this same volume to the replacement server'
                        }]
                    }]
                }, {
                    xtype: 'checkbox',
                    name: 'rebuild',
                    boxLabel: 'Recreate missing volumes',
                    plugins: {
                        ptype: 'fieldicons',
                        align: 'right',
                        icons: [{id: 'info', tooltip: 'If Scalr detects any missing volumes, regenerate this storage from scratch (based on configuration)'}]
                    }
                }]
            }, {
                xtype: 'fieldset',
                itemId: 'mountCt',
                title: 'Automatically mount device',
                checkboxName: 'mount',
                checkboxToggle: true,
                collapsible: true,
                collapsed: true,
                listeners: {
                    beforeexpand: function() {
                        if (this.checkboxCmp.readOnly && !this.up('form').isRecordLoading) return false;
                        this.onChange(true);
                    },
                    beforecollapse: function() {
                        if (this.checkboxCmp.readOnly && !this.up('form').isRecordLoading) return false;
                        this.onChange(false);
                    }
                },
                onChange: function(checked) {
                    var me = this,
                        form = me.up('form'),
                        fsField = me.down('[name="fs"]'),
                        mountPointField = me.down('#mountPoint'),
                        mountPointNtfsField = me.down('#mountPointNtfs'),
                        labelField = me.down('[name="label"]'),
                        isNtfs = fsField.getValue() === 'ntfs';
                    if (checked) {
                        if (fsField.fsIsAvailableForPlatformAndOsFamily) {
                            fsField.enable();
                        }
                        if (isNtfs) {
                            mountPointField.hide().disable();
                            mountPointNtfsField.show().enable();
                            if (!form.isRecordLoading && !mountPointNtfsField.getValue()) {
                                mountPointNtfsField.setValue(mountPointNtfsField.store.first());
                            }
                            labelField.show().enable();
                        } else {
                            mountPointField.enable().show();
                            mountPointNtfsField.hide().disable();
                            labelField.hide().disable();
                        }
                    } else {
                        mountPointField.disable();
                        mountPointNtfsField.disable();
                        fsField.disable();
                        labelField.disable();
                    }
                },
                defaults: {
                    labelWidth: 116,
                    anchor: '100%',
                    maxWidth: 480
                },
                items: [{
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
                    queryMode: 'local',
                    emptyText: 'Please select filesystem',
                    hideInputOnReadOnly: true,
                    allowBlank: false,
                    listeners: {
                        beforeselect: function(comp, record) {
                            var typeField = this.up('form').down('[name="type"]'),
                                type = typeField.getValue();
                            if (!Scalr.flags['betaMode'] && Ext.isString(type) && type.indexOf('raid') !== -1 && record.get('fs') === 'xfs') {
                                Scalr.message.InfoTip('Xfs is not available on raid.', this.inputEl, {anchor: 'bottom'});
                                return false;
                            }
                        }
                    }
                },{
                    xtype: 'fieldcontainer',
                    itemId: 'mountPointCt',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    defaults: {
                        labelWidth: 116
                    },
                    items: [{
                        xtype: 'textfield',
                        disabled: true,
                        allowBlank: false,
                        fieldLabel: 'Mount point',
                        flex: 1,
                        name: 'mountPoint',
                        itemId: 'mountPoint',
                        plugins: [{
                            ptype: 'fieldicons',
                            position: 'outer',
                            icons: [{
                                id: 'warning', hidden: true, tooltip: 'Re-mounting system mountpoint can cause unpredictable issues with an instance. Please consider mounting your device to another mountpoint.'
                            }]
                        }],
                        listeners: {
                            change: function(comp, value) {
                                this.toggleIcon('warning', Ext.Array.contains(['/var', '/usr', '/bin', '/boot', '/etc', '/home', '/lib', '/opt', '/sys',  '/sbin'], value));
                            }
                        }
                    },{
                        xtype: 'combo',
                        width: 180,
                        name: 'mountPointNtfs',
                        valueField: 'id',
                        displayField: 'name',
                        allowBlank: false,
                        fieldLabel: 'Mount point',
                        store: {
                            fields: ['id', 'name'],
                            proxy: 'object'
                        },
                        editable: false,
                        disabled: true,
                        hidden: true,
                        itemId: 'mountPointNtfs'
                    }]
                },{
                    xtype: 'textfield',
                    fieldLabel: 'Disk label',
                    disabled: true,
                    hidden: true,
                    validator: function(value) {
                        return /\t/.test(value) ? 'Tab character is not allowed here' : true;
                    },
                    name: 'label'
                }]
            }, {
                xtype:'fieldset',
                itemId: 'raid_settings',
                title: 'RAID settings',
                hidden: true,
                maskOnDisable: true,
                defaults: {
                    labelWidth: 116,
                    anchor: '100%',
                    maxWidth: 480
                },
                items: [{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    anchor: '100%',
                    items: [{
                        xtype: 'combo',
                        name: 'raid.level',
                        hideLabel: true,
                        editable: false,
                        store: {
                            fields: [ 'name', 'description' ],
                            proxy: 'object',
                            data: [
                                { name: '0', description: 'RAID 0 (block-level striping without parity or mirroring)' },
                                { name: '1', description: 'RAID 1 (mirroring without parity or striping)' },
                                { name: '5', description: 'RAID 5 (block-level striping with distributed parity)' },
                                { name: '10', description: 'RAID 10 (mirrored sets in a striped set)' }
                            ]
                        },
                        valueField: 'name',
                        displayField: 'description',
                        value: '0',
                        queryMode: 'local',
                        flex: 1,
                        allowBlank: false,
                        listeners: {
                            change: function() {
                                var data = [], field = this.next('[name="raid.volumes_count"]');

                                if (this.getValue() == '0') {
                                    data = [{ id: 2, name: 2 }, { id: 3, name: 3 }, { id: 4, name: 4 }, { id: 5, name: 5 },
                                        { id: 6, name: 6 }, { id: 7, name: 7 }, { id: 8, name: 8 }];
                                } else if (this.getValue() == '1') {
                                    data = [{ id: 2, name: 2 }];
                                } else if (this.getValue() == '5') {
                                    data = [{ id: 3, name: 3 }, { id: 4, name: 4 }, { id: 5, name: 5 },
                                        { id: 6, name: 6 }, { id: 7, name: 7 }, { id: 8, name: 8 }];
                                } else if (this.getValue() == '10') {
                                    data = [{ id: 4, name: 4 }, { id: 6, name: 6 }, { id: 8, name: 8 }];
                                } else {
                                    field.reset();
                                    field.disable();
                                    return;
                                }

                                field.store.loadData(data);
                                field.enable();
                                if (! field.getValue())
                                    field.setValue(field.store.first().get('id'));
                            }
                        }
                    }, {
                        xtype: 'label',
                        text: 'on',
                        margin: '0 0 0 5'
                    }, {
                        xtype: 'combo',
                        name: 'raid.volumes_count',
                        disabled: true,
                        editable: false,
                        width: 50,
                        store: {
                            fields: [ 'id', 'name'],
                            proxy: 'object'
                        },
                        valueField: 'id',
                        displayField: 'name',
                        queryMode: 'local',
                        margin: '0 0 0 5'
                    }, {
                        xtype: 'label',
                        text: 'volumes',
                        margin: '0 0 0 5'
                    }]
                }]
            }, {
                xtype:'fieldset',
                itemId: 'ebs_settings',
                title: 'Volume settings',
                hidden: true,
                maskOnDisable: true,
                defaults: {
                    labelWidth: 116,
                    anchor: '100%',
                    maxWidth: 480
                },
                items: [{
                    xtype: 'textfield',
                    name: 'ebs.size',
                    fieldLabel: 'Size (GB)',
                    allowBlank: false,
                    maxWidth: 195,
                    vtype: 'ebssize',
                    getEbsType: function() {
                        return this.up('#editor').down('[name="ebs.type"]').getValue();
                    },
                    getEbsIops: function() {
                        return this.up('#editor').down('[name="ebs.iops"]').getValue();
                    },
                    validator: function(value) {
                        var form = this.up('#editor'),
                            field = form.down('[name="ebs.snapshot"]');
                        if (! field.isDisabled() && field.getValue()) {
                            if (field.snapshotData && (value*1 < field.snapshotData.size*1))
                                return 'Value must be bigger or equal to snapshot size: ' + field.snapshotData.size + 'GB';
                        }
                        return true;
                    }
                }, {
                    xtype: 'fieldcontainer',
                    layout: 'hbox',
                    fieldLabel: 'EBS type',
                    items: [{
                        xtype: 'combo',
                        store: Scalr.constants.ebsTypes,
                        valueField: 'id',
                        displayField: 'name',
                        editable: false,
                        queryMode: 'local',
                        value: 'standard',
                        name: 'ebs.type',
                        flex: 1,
                        listeners: {
                            change: function (comp, value) {
                                var form = comp.up('form'),
                                    iopsField = form.down('[name="ebs.iops"]');
                                if (value == 'io1') {
                                    iopsField.show().enable().focus(false, 100);
                                    var value = iopsField.getValue();
                                    iopsField.setValue(value || 100);
                                } else {
                                    iopsField.hide().disable();
                                    form.down('[name="ebs.size"]').isValid();
                                }
                            }
                        }
                    }, {
                        xtype: 'textfield',
                        name: 'ebs.iops',
                        hidden: true,
                        disabled: true,
                        margin: '0 0 0 5',
                        vtype: 'iops',
                        allowBlank: false,
                        flex: 1,
                        maxWidth: 60
                    }]
                }, {
                    xtype: 'textfield',
                    fieldLabel: 'Snapshot',
                    name: 'ebs.snapshot',
                    emptyText: 'Create an empty volume',
                    anchor: '100%',
                    cls: 'x-form-filterfield',
                    snapshotData: null,
                    editable: false,
                    fieldStyle: 'cursor: pointer',
                    triggers: {
                        reset: {
                            hideOnReadOnly: false,
                            hidden: true,
                            extraCls: 'x-form-filterfield-trigger-cancel-button',
                            handler: function() {
                                this.reset();
                                delete this.snapshotData;
                            }
                        },
                        lookup: {
                            extraCls: 'x-form-filterfield-trigger-search-button',
                            handler: function() {
                                this.showPopup();
                            }
                        }
                    },
                    listeners: {
                        afterrender: function() {
                            var me = this;
                            this.inputEl.on('mouseup', function(){
                                me.showPopup();
                            });
                        },
                        writeablechange: function(comp) {
                            this.getTrigger('reset').setVisible(!!this.getValue());
                        },
                        change: function(comp, value) {
                            var encryptionField;
                            encryptionField = comp.next('[name="ebs.encrypted"]');
                            this.getTrigger('reset').setVisible(!!value);
                            if (comp.snapshotData && comp.snapshotData.snapshotId != value) {
                                delete this.snapshotData;
                            }
                            if (encryptionField.encryptionSupported) {
                                if (comp.snapshotData) {
                                    encryptionField.setValue(comp.snapshotData.encrypted ? 1 : 0);
                                    encryptionField.setReadOnly(true);
                                } else {
                                    encryptionField.setReadOnly(!!value);
                                }
                            }
                        }
                    },
                    showPopup: function() {
                        if (!this.readOnly && !this.disabled) {
                            Scalr.Confirm({
                                formWidth: 1050,
                                formLayout: 'fit',
                                alignTop: true,
                                winConfig: {
                                    height: '80%',
                                    autoScroll: false,
                                    layout: 'fit'
                                },
                                form: [{
                                    xtype: 'snapshotselect',
                                    title: 'Select Snapshot',
                                    titleAlignCenter: true,
                                    minHeight: 200,
                                    encryptionSupported: this.next('[name="ebs.encrypted"]').encryptionSupported,
                                    storeExtraParams: {
                                        cloudLocation: this.up('#storage').currentRole.get('cloud_location')
                                    }
                                }],
                                ok: 'Select',
                                disabled: true,
                                closeOnSuccess: true,
                                scope: this,
                                success: function (formValues, form) {
                                    this.snapshotData = form.down('snapshotselect').selection.getData();
                                    this.setValue(this.snapshotData.snapshotId);
                                    return true;
                                }
                            });
                        }
                    }
                }, {
                    xtype: 'checkbox',
                    name: 'ebs.encrypted',
                    boxLabel: 'Enable EBS encryption',
                    encryptionSupported: false,
                    defaults: {
                        width: 90
                    },
                    value: '0',
                    plugins: {
                        ptype: 'fieldicons',
                        icons: ['question', {id: 'szrversion', tooltipData: {version: '2.9.25'}}]
                    },
                    listeners: {
                        writeablechange: function(comp, readOnly) {
                            this.toggleIcon('question', readOnly);
                            if (readOnly) {
                                this.updateIconTooltip('question', this.encryptionSupported ? 'EBS encryption is set according to snapshot settings' : 'EBS encryption is not supported by selected instance type');
                            }
                            comp.next('[name="ebs.kms_key_id"]').setVisible(!readOnly && comp.getValue() == 1).reset();
                        },
                        change: function(comp, value) {
                            comp.next('[name="ebs.kms_key_id"]').setVisible(value == 1).reset();
                        }
                    }
                },{
                    xtype: 'combo',
                    name: 'ebs.kms_key_id',
                    fieldLabel: 'KMS key',
                    valueField: 'id',
                    displayField: 'displayField',
                    emptyText: 'Default key (aws/ebs)',
                    anchor: '100%',
                    matchFieldWidth: true,
                    hidden: true,

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
                        proxy: 'object',
                        filters: [
                            function(record) {
                                return !Ext.Array.contains(['alias/aws/rds', 'alias/aws/redshift', 'alias/aws/s3'], record.get('alias'));
                            }
                        ],
                        sorters: {
                            property: 'alias',
                            transform: function(value){
                                return value ? value.toLowerCase() : value;
                            }
                        }
                    }
                }]
            }, {
                xtype:'fieldset',
                itemId: 'csvol_settings',
                title: 'Volume settings',
                maskOnDisable: true,
                hidden: true,
                defaults: {
                    labelWidth: 100,
                    width: 300
                },
                items: [{
                    xtype: 'combo',
                    anchor: '100%',
                    maxWidth: 480,
                    name: 'csvol.disk_offering_id',
                    fieldLabel: 'Disk offering',
                    editable: false,
                    store: {
                        fields: [ 'id', 'name' , 'type', {name: 'size', type: 'int'}, 'custom_size', {name: 'fullname', convert: function(v, record){return record.data.name + (record.data.type ? ' (' + record.data.type + ')' : '');}}],
                        proxy: 'object',
                        sortOnFilter: true,
                        sortOnLoad: true,
                        sorters: [{
                            property: 'size',
                            direction: 'ASC'
                        }]
                    },
                    queryMode: 'local',
                    valueField: 'id',
                    displayField: 'fullname',
                    allowBlank: false,
                    labelWidth: 116,
                    listeners: {
                        change: function(field, value){
                            var record = field.findRecordByValue(value),
                                csvol = field.up('#csvol_settings'),
                                sizeField = csvol.down('[name="csvol.size"]'),
                                typeField = csvol.down('[name="csvol.disk_offering_type"]'),
                                customSize = record ? record.get('custom_size') : false;
                            sizeField.setValue(record ? record.get('size') || 1 : null);
                            sizeField.setReadOnly(!customSize);
                            typeField.setValue(customSize ? 'custom' : 'fixed');
                            if (record && record.get('type') === 'local') {
                                this.up('#editor').down('[name="reUse"]').setValue(0).setReadOnly(true);
                            } else {
                                this.up('#editor').down('[name="reUse"]').setReadOnly(false);
                            }
                        }
                    }
                },{
                    xtype: 'fieldcontainer',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Size',
                        labelWidth: 116,
                        maxWidth: 160,
                        name: 'csvol.size',
                        allowBlank: false
                    },{
                        xtype: 'label',
                        text: 'GB',
                        margin: '0 0 0 6'
                    }]
                },{
                    xtype: 'hidden',
                    name: 'csvol.disk_offering_type'
                }, {
                    xtype: 'combo',
                    fieldLabel: 'Snapshot',
                    name: 'csvol.snapshot_id',
                    emptyText: 'Create an empty volume',
                    valueField: 'snapshotId',
                    displayField: 'snapshotId',
                    labelWidth: 116,
                    anchor: '100%',
                    maxWidth: 480,
                    matchFieldWidth: true,

                    restoreValueOnBlur: true,
                    queryCaching: false,
                    minChars: 0,
                    queryDelay: 10,
                    store: {
                        fields: [ 'snapshotId', 'createdAt', 'volumeType', 'volumeId' ],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'farmDesigner',
                            url: '/tools/cloudstack/snapshots/xGetSnapshots',
                            filterFields: ['snapshotId', 'volumeId'],//fliterFn
                            prependData: [{}]//Create an empty volume
                        }
                    },
                    listConfig: {
                        cls: 'x-boundlist-alt',
                        tpl:
                            '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                '<tpl if="snapshotId">' +
                                '<div class="x-semibold">{snapshotId} ({volumeType})</div>' +
                                '<div>created <span class="x-semibold">{createdAt}</span> on <span class="x-semibold">{volumeId}</span></div>' +
                                '<tpl else><div style="line-height: 26px;">Create an empty volume</div></tpl>' +
                                '</div></tpl>'
                    },
                    listeners: {
                        blur: function(comp) {
                            if (comp.lastQuery && !comp.disabled && !comp.readOnly && !comp.findRecordByValue(comp.getValue())) {
                                comp.reset();
                            }
                        }
                    }
                }]
            }, {
                xtype:'fieldset',
                itemId: 'cinder_settings',
                title: 'Volume settings',
                hidden: true,
                maskOnDisable: true,
                defaults: {
                    labelWidth: 100,
                    width: 300
                },
                items: [{
                    xtype: 'combo',
                    anchor: '100%',
                    maxWidth: 300,
                    name: 'cinder.volume_type',
                    fieldLabel: 'Type',
                    editable: false,
                    store: {
                        fields: [ 'id', 'name'],
                        proxy: 'object',
                        sortOnFilter: true,
                        sortOnLoad: true,
                        sorters: [{
                            property: 'name',
                            direction: 'ASC'
                        }]
                    },
                    queryMode: 'local',
                    valueField: 'id',
                    displayField: 'name',
                    allowBlank: false,
                    labelWidth: 50
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Size',
                        labelWidth: 50,
                        maxWidth: 100,
                        name: 'cinder.size',
                        allowBlank: false
                    },{
                        xtype: 'label',
                        text: 'GB',
                        margin: '0 0 0 6'
                    }]
                }]
            }, {
                xtype:'fieldset',
                itemId: 'gce_settings',
                title: 'Volume settings',
                hidden: true,
                maskOnDisable: true,
                items: [{
                    xtype: 'fieldcontainer',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Size',
                        labelWidth: 116,
                        name: 'gce_persistent.size',
                        width: 225,
                        allowBlank: false
                    },{
                        xtype: 'label',
                        text: 'GB',
                        margin: '0 0 0 6'
                    }]
                }, {
                    xtype: 'combo',
                    store: Scalr.constants.gceDiskTypes,
                    width: 225,
                    labelWidth: 116,
                    valueField: 'id',
                    displayField: 'name',
                    fieldLabel: 'Type',
                    editable: false,
                    queryMode: 'local',
                    value: 'pd-standard',
                    name: 'gce_persistent.type',
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
                itemId: 'ec2_ephemeral_settings',
                title: 'Volume settings',
                hidden: true,
                maskOnDisable: true,
                items: [{
                    xtype: 'combo',
                    name: 'ec2_ephemeral.name',
                    maxWidth: 300,
                    labelWidth: 116,
                    anchor: '100%',
                    fieldLabel: 'Name',
                    hideInputOnReadOnly: true,
                    editable: false,
                    store: {
                        fields: [ 'description', 'name', 'size'],
                        proxy: 'object'
                    },
                    valueField: 'name',
                    displayField: 'description',
                    queryMode: 'local',
                    allowBlank: false,
                    listeners: {
                        change: function(comp, value) {
                            var record = comp.findRecordByValue(value);
                            if (record) {
                                this.up('#ec2_ephemeral_settings').down('[name="ec2_ephemeral.size"]').setValue(record.get('size'));
                            }
                        }
                    }
                },{
                    xtype: 'fieldcontainer',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Size',
                        labelWidth: 116,
                        name: 'ec2_ephemeral.size',
                        readOnly: true,
                        width: 200,
                        allowBlank: false
                    },{
                        xtype: 'label',
                        text: 'GB',
                        margin: '0 0 0 6'
                    }]
                }]
            }, {
                xtype:'fieldset',
                itemId: 'gce_ephemeral_settings',
                title: 'Volume settings',
                hidden: true,
                maskOnDisable: true,
                items: [{
                    xtype: 'combo',
                    name: 'gce_ephemeral.name',
                    maxWidth: 300,
                    labelWidth: 116,
                    anchor: '100%',
                    fieldLabel: 'Name',
                    hideInputOnReadOnly: true,
                    editable: false,
                    store: {
                        fields: [ 'description', 'name'],
                        proxy: 'object'
                    },
                    valueField: 'name',
                    displayField: 'description',
                    queryMode: 'local',
                    allowBlank: false
                },{
                    xtype: 'fieldcontainer',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Size',
                        labelWidth: 116,
                        name: 'gce_ephemeral.size',
                        width: 200,
                        readOnly: true,
                        allowBlank: false
                    },{
                        xtype: 'label',
                        text: 'GB',
                        margin: '0 0 0 6'
                    }]
                }]
            }]
        }]
    }]
});

Ext.define('Scalr.ui.SnapshotSelect', {
	extend: 'Ext.form.FieldSet',
	alias: 'widget.snapshotselect',

    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
    layout: 'fit',

    initComponent: function() {
        var me = this;
        me.callParent(arguments);
        var store = Ext.create('Scalr.ui.ContinuousStore', {
            model: Ext.define(null, {
                extend: 'Ext.data.Model',
                idProperty: 'snapshotId',
                fields: [ 'snapshotId', 'createdDate', 'volumeSize', 'volumeId', 'description', 'encrypted' ]
            }),

            proxy: {
                type: 'ajax',
                url: '/platforms/ec2/xGetSnapshots',
                extraParams: {
                    cloudLocation: me.storeExtraParams.cloudLocation
                },
                reader: {
                    type: 'json',
                    rootProperty: 'data',
                    totalProperty: 'total',
                    successProperty: 'success'
                }
            },
            listeners: {
                load: function(store) {
                    store.proxy.extraParams.nextToken = store.proxy.reader.rawData.nextToken;
                    if (store.proxy.extraParams.nextToken) {
                        store.totalCount = store.getCount() + 1;
                    }
                }
            }
        });

        me.add([{
            xtype: 'grid',
            store: store,
            plugins: [{
                ptype: 'continuousrenderer'
            }],
            viewConfig: {
                emptyText: 'No snapshots found',
                //deferEmptyText: false,
                /*focusedItemCls: 'no-focus',
                emptyText: 'No security groups found',
                loadingText: 'Loading security groups ...',*/
                listeners: {
                    viewready: function() {
                        store.applyProxyParams();
                    }
                }
            },

            columns: [
                { header: "Snapshot ID", width: 140, dataIndex: 'snapshotId', sortable: false },
                { header: "Created", width: 180, dataIndex: 'createdDate', sortable: false },
                { header: "Volume ID", width: 120, dataIndex: 'volumeId', sortable: false },
                { header: "Size (GB)", width: 80, dataIndex: 'volumeSize', sortable: false },
                { header: "Comment", flex: 1, dataIndex: 'description', sortable: false, xtype: 'templatecolumn', tpl: '<tpl if="description"><span data-qtip="{description:htmlEncode}">{description}</span></tpl>' },
                { header: "Encrypted", width: 90, dataIndex: 'encrypted', resizeable: false, sortable: false, align:'center', xtype: 'templatecolumn', tpl:
                    '<tpl if="encrypted">'+
                        '<div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div>'+
                    '<tpl else>&mdash;</tpl>'}

            ],

            listeners: {
                selectionchange: function(selModel, selections) {
                    if (selections.length) {
                        me.selection = selections[0];
                    } else {
                        delete me.selection;
                    }

                    me.updateButtonState(me.selection);
                }
            },

            dockedItems: [{
                xtype: 'toolbar',
                dock: 'top',
                ui: 'inline',
                items: [{
                    xtype: 'filterfield',
                    store: store,
                    listeners: {
                        beforefilter: function() {
                            delete this.getStore().proxy.extraParams.nextToken;
                        }
                    }
                }]
            }]
        }]);
    },
    updateButtonState: function(selection) {
        var btnOk = this.up('#box').down('#buttonOk');
        if (selection && selection.get('encrypted') && !this.encryptionSupported) {
            btnOk.setDisabled(true).setTooltip('Encrypted EBS storage is not supported by current instance type.');
        } else {
            btnOk.setDisabled(!selection).setTooltip('');
        }
    }
});

Scalr.ui.getFarmRoleModel = function() {
    return Ext.define(null,{
        extend: 'Ext.data.Model',
        fields: [
            'id',
            { name: 'new', type: 'boolean' },
            'role_id',
            'platform',
            'generation',
            'osId',
            'farm_role_id',
            'cloud_location',
            'image',
            'name',
            'alias',
            'group',
            'cat_id',
            'behaviors',
            {name: 'launch_index', type: 'int'},
            'is_bundle_running',
            'settings',
            'scaling',
            'scripting',
            'scripting_params',
            'storages',
            'config_presets',
            'variables',
            {name: 'securityGroupsEnabled', defaultValue: true},
            {name: 'running_servers', defaultValue: 0},
            {name: 'suspended_servers', defaultValue: 0},
            'security_groups',
            {name: 'hourly_rate', defaultValue: null}
        ],

        constructor: function() {
            var me = this;
            me.callParent(arguments);
        },

        get: function(field, raw) {
            var value = this.callParent([field]);
            return raw === true || !value || Ext.isPrimitive(value) ? value : Ext.clone(value);
        },

        watchList: {
            launch_index: true,
            settings: ['scaling.enabled', 'scaling.min_instances', 'scaling.max_instances', 'aws.instance_type', 'db.msr.data_storage.engine', 'gce.machine-type'],
            scaling: true
        },

        set: function (fieldName, newValue) {
            var me = this,
                data = me.data,
                single = (typeof fieldName == 'string'),
                name, values, currentValue, value,
                events = [];

            if (me.store) {
                if (single) {
                    values = me._singleProp;
                    values[fieldName] = newValue;
                } else {
                    values = fieldName;
                }

                for (name in values) {
                    if (values.hasOwnProperty(name)) {
                        value = values[name];
                        currentValue = data[name];
                        if (me.isEqual(currentValue, value)) {
                            continue;
                        }
                        if (me.watchList[name]) {
                            if (me.watchList[name] === true) {
                                events.push({name: [name], value: value, oldValue: currentValue});
                            } else {
                                for (var i=0, len=me.watchList[name].length; i<len; i++) {
                                    var name1 = me.watchList[name][i],
                                        currentValue1 = currentValue && currentValue[name1] ? currentValue[name1] : undefined,
                                        value1 = value && value[name1] ? value[name1] : undefined;
                                    if (currentValue1 != value1) {
                                        events.push({name: [name, name1], value: value1, oldValue: currentValue1});
                                    }
                                }
                            }
                        }
                    }
                }

                if (single) {
                    delete values[fieldName];
                }
            }

            me.callParent(arguments);

            Ext.Array.each(events, function(event){
                me.store.fireEvent('roleupdate', me, event.name, event.value, event.oldValue);
            });
        },

        getInstanceType: function(availableInstanceTypes, limits, vpcSettings) {
            var me = this,
                settings = me.get('settings', true),
                image = me.get('image', true),
                typeString = image['type'] || '',
                restrictions = {
                    ec2: {
                        ebs: typeString.indexOf('ebs') !== -1,
                        hvm: typeString.indexOf('hvm') !== -1,
                        x64: image['architecture'] !== 'i386',
                        vpc: vpcSettings !== false
                    }
                },
                platform = me.get('platform'),
                instanceType = settings[this.getInstanceTypeParamName()],
                defaultInstanceType,
                defaultInstanceTypeAllowed,
                firstAllowedInstanceType,
                allowedInstanceTypeCount = 0,
                instanceTypes = [];

            if (platform === 'ec2') {
                defaultInstanceType = 'm1.small';
            } else if (platform === 'gce') {
                defaultInstanceType = 'n1-standard-1';
            }

            isInstanceTypeAllowed = function(instanceType) {
                var allowed = true,
                    compatibleWith = [],
                    notCompatibleWith = [];
                if (instanceType.restrictions) {
                    if (restrictions[platform]) {
                        Ext.Object.each(restrictions[platform], function(name, value) {
                            if (instanceType.restrictions[name] !== undefined && instanceType.restrictions[name] !== value) {
                                if (instanceType.restrictions[name] === true) {
                                    compatibleWith.push(name);
                                } else {
                                    notCompatibleWith.push(name);
                                }
                            }
                        });
                    }
                }
                if (compatibleWith.length) {
                    allowed = 'Compatible with ' + compatibleWith.join(' and ') + ' roles only';
                } else if (notCompatibleWith.length) {
                    allowed = 'Not compatible with ' + notCompatibleWith.join(' and ') + ' roles';
                }
                return allowed;
            }

            Ext.each(availableInstanceTypes, function(item) {
                var allowed,
                    clonedItem = Ext.clone(item);
                if (instanceType !== item.id && limits && limits['value'] !== undefined && !Ext.Array.contains(limits['value'], item.id)) {
                    return;
                }

                instanceTypes.push(clonedItem);
                allowed = isInstanceTypeAllowed(item);
                if (allowed === true) {
                    firstAllowedInstanceType = firstAllowedInstanceType || item.id;
                    allowedInstanceTypeCount++;
                    if (defaultInstanceType == item.id) {
                        defaultInstanceTypeAllowed = true;
                    }
                    if (limits && !instanceType && limits['default'] == item.id ) {
                        instanceType = limits['default'];
                    }
                } else {
                    clonedItem.disabled = true;
                    clonedItem.disabledReason = allowed;
                }
            });

            if (!instanceType) {
                if (defaultInstanceType && defaultInstanceTypeAllowed) {
                    instanceType = defaultInstanceType;
                } else {
                    instanceType = firstAllowedInstanceType;
                }
            }

            return {
                value: instanceType,
                list: instanceTypes,
                allowedInstanceTypeCount: allowedInstanceTypeCount
            };
        },

        getGceCloudLocation: function() {
            var cloudLocation = this.get('settings', true)['gce.cloud-location'] || '';
            if (cloudLocation.match(/x-scalr-custom/)) {
                cloudLocation = cloudLocation.replace('x-scalr-custom=', '').split(':');
            } else {
                if (Ext.isEmpty(cloudLocation)) {
                    cloudLocation = [];
                }
                cloudLocation = [cloudLocation];
            }
            return cloudLocation;
        },

        setGceCloudLocation: function(value) {
            var settings = this.get('settings');
            if (value.length === 1) {
                settings['gce.cloud-location'] = value[0];
            } else if (value.length > 1) {
                settings['gce.cloud-location'] = 'x-scalr-custom=' + value.join(':');
            } else {
                settings['gce.cloud-location'] = '';
            }
            this.set('settings', settings);
        },

        getInstanceTypeParamName: function(platform) {
            var name;
            platform = platform || this.get('platform');
            switch (platform) {
                case 'ec2':
                    name = 'aws.instance_type';
                break;
                case 'eucalyptus':
                    name = 'euca.instance_type';
                break;
                case 'gce':
                    name = 'gce.machine-type';
                break;
                case 'rackspace':
                    name = 'rs.flavor-id';
                break;
                default:
                    if (Scalr.isOpenstack(platform)) {
                        name = 'openstack.flavor-id';
                    } else if (Scalr.isCloudstack(platform)) {
                        name = 'cloudstack.service_offering_id';
                    }
                break;
            }
            return name;
        },

        getDefaultStorageEngine: function() {
            var engine = '',
                platform = this.get('platform', true);

            if (platform === 'ec2' || platform === 'eucalyptus') {
                engine = 'ebs';
            } else if (platform === 'rackspace') {
                engine = 'eph';
            } else if (Scalr.isOpenstack(platform)) {
                engine = this.isMySql() ? 'lvm' : 'eph';
            } else if (platform === 'gce') {
                engine = 'gce_persistent';
            } else if (platform == 'cloudstack' || platform == 'idcf') {
                engine = 'csvol';
            }
            return engine;
        },

        getMongoDefaultStorageEngine: function() {
            var engine,
                platform = this.get('platform', true);

            if (platform === 'ec2') {
                engine = 'ebs';
            } else if (platform === 'rackspace') {
                engine = 'eph';
            } else if (platform === 'gce') {
                engine = 'gce_persistent';
            } else if (platform === 'cloudstack' || platform === 'idcf') {
                engine = 'csvol';
            } else if (Scalr.isOpenstack(platform)) {
                engine = 'cinder';
            }
            return engine;
        },

        getOldMySqlDefaultStorageEngine: function() {
            var engine,
                platform = this.get('platform', true);

            if (platform === 'ec2') {
                engine = 'ebs';
            } else if (platform === 'rackspace') {
                engine = 'eph';
            } else if (platform === 'cloudstack' || platform === 'idcf') {
                engine = 'csvol';
            }

            return engine;
        },

        getRabbitMQDefaultStorageEngine: function() {
            var engine,
                platform = this.get('platform', true);

            if (platform === 'ec2') {
                engine = 'ebs';
            } else if (Scalr.isOpenstack(platform)) {
                engine = 'cinder';
            } else if (Scalr.isCloudstack(platform)) {
                engine = 'csvol';
            } else if (platform === 'gce') {
                engine = 'gce_persistent';
            }

            return engine;
        },

        isDbMsr: function(includeDeprecated) {
            var behaviors = this.get('behaviors', true),
                db = ['mysql2', 'percona', 'redis', 'postgresql', 'mariadb'];

            if (includeDeprecated === true) {
                db.push('mysql');
            }
            behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
            return Ext.Array.some(behaviors, function(rb){
                return Ext.Array.contains(db, rb);
            });
        },

        isMySql: function() {
            var behaviors = this.get('behaviors', true),
                db = ['mysql2', 'percona', 'mariadb'];

            behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
            return Ext.Array.some(behaviors, function(rb){
                return Ext.Array.contains(db, rb);
            });
        },

        isVpcRouter: function() {
            var behaviors = this.get('behaviors', true);

            behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
            return Ext.Array.contains(behaviors, 'router');
        },

        ephemeralDevicesMap: {
            ec2: {
                'm1.small': {'ephemeral0':{'size': 150}},
                'm1.medium': {'ephemeral0':{'size': 400}},
                'm1.large': {'ephemeral0':{'size': 420}, 'ephemeral1':{'size': 420}},
                'm1.xlarge': {'ephemeral0':{'size': 420}, 'ephemeral1':{'size': 420}, 'ephemeral2':{'size': 420}, 'ephemeral3':{'size': 420}},
                'c1.medium': {'ephemeral0':{'size': 340}},
                'c1.xlarge': {'ephemeral0':{'size': 420}, 'ephemeral1':{'size': 420}, 'ephemeral2':{'size': 420}, 'ephemeral3':{'size': 420}},
                'm2.xlarge': {'ephemeral0':{'size': 410}},
                'm2.2xlarge': {'ephemeral0':{'size': 840}},
                'm2.4xlarge': {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}},
                'hi1.4xlarge': {'ephemeral0':{'size': 1000}, 'ephemeral1':{'size': 1000}},
                'cc1.4xlarge': {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}},
                'cr1.8xlarge': {'ephemeral0':{'size': 120}, 'ephemeral1':{'size': 120}},
                'cc2.8xlarge': {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}, 'ephemeral2':{'size': 840}, 'ephemeral3':{'size': 840}},
                'cg1.4xlarge': {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}},
                'hs1.8xlarge': {'ephemeral0':{'size': 12000}, 'ephemeral1':{'size': 12000}, 'ephemeral2':{'size': 12000}, 'ephemeral3':{'size': 12000}}
            },
            gce: {
                'n1-highcpu-2-d': {'google-ephemeral-disk-0':{'size': 870}},
                'n1-highcpu-4-d': {'google-ephemeral-disk-0':{'size': 1770}},
                'n1-highcpu-8-d': {'google-ephemeral-disk-0':{'size': 1770}, 'google-ephemeral-disk-1':{'size': 1770}},
                'n1-highmem-2-d': {'google-ephemeral-disk-0':{'size': 870}},
                'n1-highmem-4-d': {'google-ephemeral-disk-0':{'size': 1770}},
                'n1-highmem-8-d': {'google-ephemeral-disk-0':{'size': 1770}, 'google-ephemeral-disk-1':{'size': 1770}},
                'n1-standard-1-d': {'google-ephemeral-disk-0':{'size': 420}},
                'n1-standard-2-d': {'google-ephemeral-disk-0':{'size': 870}},
                'n1-standard-4-d': {'google-ephemeral-disk-0':{'size': 1770}},
                'n1-standard-8-d': {'google-ephemeral-disk-0':{'size': 1770}, 'google-ephemeral-disk-1':{'size': 1770}}
            }
        },

        getEphemeralDevicesMap: function() {
            return this.ephemeralDevicesMap[this.get('platform')];

        },

        getAvailableStorages: function() {
            var platform = this.get('platform'),
                ephemeralDevicesMap = this.getEphemeralDevicesMap(),
                settings = this.get('settings', true),
                storages = [];

            if (platform === 'ec2') {
                storages.push({name:'ebs', description:'Single EBS Volume'});
                storages.push({name:'raid.ebs', description:'RAID array on EBS volumes'});

                if (this.isMySql()) {
                    if (Ext.isDefined(ephemeralDevicesMap[settings['aws.instance_type']])) {
                        storages.push({name:'lvm', description:'LVM on ephemeral devices'});
                    }
                    if (settings['db.msr.data_storage.engine'] == 'eph' || Scalr.flags['betaMode']) {
                        storages.push({name:'eph', description:'Ephemeral device'});
                    }
                } else {
                    storages.push({name:'eph', description:'Ephemeral device'});
                }
            } else if (platform === 'eucalyptus') {
                storages.push({name:'ebs', description:'Single EBS Volume'});
                storages.push({name:'raid.ebs', description:'RAID array on EBS volumes'});
            } else if (platform === 'rackspace') {
                storages.push({name:'eph', description:'Ephemeral device'});
            } else if (platform === 'gce') {
                storages.push({name:'gce_persistent', description:'GCE Persistent disk'});
            } else if (Scalr.isOpenstack(platform)) {
                if (Scalr.getPlatformConfigValue(platform, 'ext.cinder_enabled') == 1) {
                    storages.push({name:'cinder', description:'Cinder volume'});
                }
                if (Scalr.getPlatformConfigValue(platform, 'ext.swift_enabled') == 1) {
                    if (this.isMySql()) {
                        storages.push({name:'lvm', description:'LVM on loop device (75% from /)'});
                    } else {
                        storages.push({name:'eph', description:'Ephemeral device'});
                    }
                }
            } else if (Scalr.isCloudstack(platform)) {
                storages.push({name:'csvol', description:'CloudStack Block Volume'});
            }
            return storages;
        },

        getAvailableStorageFs: function() {
            var list,
                os = Scalr.utils.getOsById(this.get('osId')) || {},
                arch = this.get('image', true)['architecture'],
                extraFs = (os.family === 'centos' && arch === 'x86_64') ||
                          (os.family === 'ubuntu' && (os.generation == '10.04' || os.generation == '12.04' || os.generation == '14.04')),
                disabledText = extraFs ? '' : 'Not available in the selected OS';
            list = [
                {value: 'ext3', text: 'Ext3'},
                {value: 'ext4', text: 'Ext4', unavailable: !extraFs, disabled: !extraFs, tooltip: disabledText},
                {value: 'xfs', text: 'XFS', unavailable: !extraFs, disabled: !extraFs, tooltip: disabledText}
            ];
            return list;
        },

        storageDisks: {
            ec2: {
                '/dev/sda2': {'m1.small':1, 'c1.medium':1},
                '/dev/sdb': {'m1.medium':1, 'm1.large':1, 'm1.xlarge':1, 'c1.xlarge':1, 'cc1.4xlarge':1, 'cc2.8xlarge':1, 'cr1.8xlarge':1, 'm2.xlarge':1, 'm2.2xlarge':1, 'm2.4xlarge':1},
                '/dev/sdc': {               'm1.large':1, 'm1.xlarge':1, 'c1.xlarge':1, 'cc1.4xlarge':1, 'cc2.8xlarge':1, 'cr1.8xlarge':1},
                '/dev/sdd': {						 	  'm1.xlarge':1, 'c1.xlarge':1, 			   	 'cc2.8xlarge':1 },
                '/dev/sde': {						 	  'm1.xlarge':1, 'c1.xlarge':1, 			     'cc2.8xlarge':1 },

                '/dev/sdf': {'hi1.4xlarge':1 },
                '/dev/sdg': {'hi1.4xlarge':1 }
            }
        },
        getAvailableStorageDisks: function() {
            var platform = this.get('platform'),
                settings = this.get('settings', true),
                disks = [];

            disks.push({'device':'', 'description':''});
            if (platform === 'ec2') {
                Ext.Object.each(this.storageDisks['ec2'], function(key, value){
                    if (value[settings['aws.instance_type']] === 1) {
                        disks.push({'device': key, 'description':'LVM on ' + key + ' (80% available for data)'});
                    }
                });
            } else if (Scalr.isOpenstack(platform) || platform === 'rackspace') {
                disks.push({'device':'/dev/loop0', 'description':'Loop device (75% from /)'});
            } else if (platform === 'gce') {
                disks.push({'device':'ephemeral-disk-0', 'description':'Loop device (80% of ephemeral-disk-0)'});
            }
            return disks;
        },

        getAvailableStorageRaids: function() {
            return [
                {name:'0', description:'RAID 0 (block-level striping without parity or mirroring)'},
                {name:'1', description:'RAID 1 (mirroring without parity or striping)'},
                {name:'5', description:'RAID 5 (block-level striping with distributed parity)'},
                {name:'10', description:'RAID 10 (mirrored sets in a striped set)'}
            ];
        },

        isMultiEphemeralDevicesEnabled: function(){
            var res = false,
                settings = this.get('settings', true);
            if (this.get('platform') === 'ec2' && !settings['db.msr.data_storage.eph.disk']) {
                var behaviors = this.get('behaviors', true);
                behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
                res = Ext.Array.contains(behaviors, 'postgresql');
            }
            return res;
        },

        hasBehavior: function(behavior) {
            var behaviors = this.get('behaviors', true);

            behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
            return Ext.Array.contains(behaviors, behavior);
        },

        loadRoleChefSettings: function(cb) {
            var record = this,
                chefSettings = {};
            Scalr.CachedRequestManager.get('farmDesigner').load(
                {
                    url: '/farms/builder/xGetRoleChefSettings/',
                    params: {
                        roleId: record.get('role_id')
                    }
                },
                function(data, status) {
                    var roleChefEnabled = Ext.isObject(data['chef']) && data['chef']['chef.bootstrap'] == 1,
                        farmRoleChefSettings = {};
                    if (status) {
                        Ext.Object.each(record.get('settings', true) || {}, function(key, value){
                            if (key.indexOf('chef.') === 0) {
                                farmRoleChefSettings[key] = value;
                            }
                        });

                        if (roleChefEnabled) {
                            Ext.apply(chefSettings, data['chef']);
                            if (farmRoleChefSettings['chef.attributes']) {
                                chefSettings['chef.attributes'] = farmRoleChefSettings['chef.attributes'];
                            }
                        } else {
                            Ext.apply(chefSettings, farmRoleChefSettings);
                        }
                    }
                    cb({
                        chefSettings: chefSettings,
                        roleChefSettings: roleChefEnabled ? data['chef'] : null,
                        farmRoleChefSettings: farmRoleChefSettings
                    }, status);
                }
            );

        },

        loadEBSEncryptionSupport: function(cb, instType) {
            var platform = this.get('platform'),
                cloudLocation = this.get('cloud_location'),
                encryption = false;
            instType = instType || this.get('settings', true)['aws.instance_type'];
            Scalr.loadInstanceTypes(platform, cloudLocation, function(data, status){
                Ext.each(data, function(i){
                    if (i.id === instType) {
                        encryption = i.ebsencryption;
                        return false;
                    }
                });
                cb(encryption);
            });

        },

        loadEBSOptimizedSupport: function(cb, instType) {
            var platform = this.get('platform'),
                cloudLocation = this.get('cloud_location'),
                typeString = this.get('image', true)['type'] || '',
                ebsOptSupported = false;

            if (typeString.indexOf('ebs') !== -1) {
                instType = instType || this.get('settings', true)['aws.instance_type'];
                Scalr.loadInstanceTypes(platform, cloudLocation, function(data, status){
                    Ext.each(data, function(i){
                        if (i.id === instType) {
                            ebsOptSupported = i.ebsoptimized || ebsOptSupported;
                            return false;
                        }
                    });
                    cb(ebsOptSupported);
                });
            } else {
                cb(false);
            }
        },

        loadPlacementGroupsSupport: function(cb, instType) {
            var platform = this.get('platform'),
                cloudLocation = this.get('cloud_location'),
                pgSupported = false;

            instType = instType || this.get('settings', true)['aws.instance_type'];
            Scalr.loadInstanceTypes(platform, cloudLocation, function(data, status){
                Ext.each(data, function(i){
                    if (i.id === instType) {
                        pgSupported = i.placementgroups || pgSupported;
                        return false;
                    }
                });
                cb(pgSupported);
            });
        },

        loadHourlyRate: function(instanceType, cb) {
            var me = this,
                settings = me.get('settings', true);
            Scalr.CachedRequestManager.get('farmDesigner').load({
                url: '/farms/builder/xGetInstanceTypeHourlyRate',
                params: {
                    platform: me.get('platform'),
                    cloudLocation: me.get('cloud_location'),
                    instanceType: instanceType || settings[me.getInstanceTypeParamName()],
                    osFamily: Scalr.utils.getOsById(me.get('osId'), 'family')
                }
            }, function(data, status){
                    if (status) {
                        me.set('hourly_rate', data['hourly_rate']);
                        cb(data['hourly_rate']);
                    }
                }
            );
        }
    });
};

Ext.define('Scalr.ui.FarmDesignerFarmRoles', {
    extend: 'Ext.view.View',
    alias: 'widget.farmrolesview',
    preserveScrollOnRefresh: true,
    cls: 'x-dataview-farmroles',
    itemCls: 'x-dataview-tab',
    selectedItemCls : 'x-dataview-tab-selected',
    overItemCls : 'x-dataview-tab-over',
    itemSelector: '.x-dataview-tab',
    autoScroll: true,
    plugins: [{
        ptype: 'flyingbutton',
        pluginId: 'flyingbutton',
        handler: function(){
            this.fireEvent('roleslibrary');
        }
    },{
        ptype: 'viewdragdrop',
        pluginId: 'viewdragdrop',
        offsetY: 50
    }],
    deferInitialRefresh: false,
    allowDeselect: true,
    tpl  : new Ext.XTemplate(
        '<div><tpl for=".">',
            '<div class="x-dataview-tab<tpl if="errors"> x-item-invalid</tpl>" {[values.errors?\'data-qtip="\'+this.getErrors(values.errors):\'title="\'+values.alias]}">',
                '<div class="x-item-color-corner x-color-{[Scalr.utils.getColorById(values.farm_role_id)]}"></div>',
                '<div class="x-item-inner">',
                    '<div class="name"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-role-small x-icon-role-small-{[Scalr.utils.getRoleCls(values)]}"/>&nbsp; {[this.fitMaxLength(values.alias, 16)]}</div>',
                    '<div class="launchindex" title="Launch index">{[values.launch_index+1]}</div>',
                    '<div class="platform-location">',
                        '<span class="platform">{platform}</span> ',
                        '<span class="location" title="{[values.settings[\'gce.region\']||values.cloud_location]}">({[values.settings[\'gce.region\']||values.cloud_location]})</span>',
                    '</div>',
                    '<div class="delete-role" title="Delete role from farm"></div>',
                '</div>',
            '</div>',
        '</tpl></div>'
    ,{
        getErrors: function(errors) {
            var html = [];
            Ext.Object.each(errors, function(key, value){
                if (key === 'variables') {
                    Ext.Object.each(value, function(key, value){
                        Ext.Object.each(value, function(key, value){
                            html.push(value);
                        });
                    });
                } else {
                    html.push(Ext.isObject(value) ? value.message : value);
                }
            });
            return Ext.String.htmlEncode('Errors: <ul class="x-tip-errors-list"><li>' + html.join('</li><li>') + '</li></ul>');
        }
    }),
    deferEmptyText: false,
    trackOver: true,

    onUpdate: function () {
        this.refresh();
    },
    toggleRolesLibraryBtn: function(pressed) {
        this.getPlugin('flyingbutton').button.setPressed(pressed)
    },
    toggleLaunchOrder: function(value) {
        this.getPlugin('viewdragdrop')[value!=1?'disable':'enable']();
        this[value==1?'addCls':'removeCls']('x-dataview-farmroles-sortable');
        if (value == 1) {
            this.store.resetLaunchIndexes();
        }

    },
    listeners: {
        itemremove: function() {
            var farmDesigner = this.up('#farmDesigner');
            farmDesigner.down('farmcostmetering').refresh();
        },
        itemadd: function(record, index, node) {
            this.scrollBy(0, 9000);
        },
        drop: function(node, data, record, position) {
            if (data.records[0]) {
                var newLaunchIndex = record.get('launch_index') + (position=='after' ? 1 : 0),
                    scrollTop = this.el.getScroll().top;
                this.suspendLayouts();
                data.records[0].store.updateLaunchIndex(data.records[0], newLaunchIndex);
                this.resumeLayouts(true);
                this.el.scrollTo('top', scrollTop);
            }
        },
        beforecontainerclick: function(){
            //prevent deselect on container click
            return false;
        },
        beforeitemclick: function (view, record, item, index, e) {
            if (e.getTarget('.delete-role', 10, true)) {
                var msg = 'Delete role "' + record.get('alias') + '" from farm?';
                if (record.get('is_bundle_running') == true) {
                    Scalr.message.Error('This role is locked by server snapshot creation process. Please wait till snapshot will be created.');
                    return false;
                }
                if (record.isVpcRouter()) {
                    msg = 'This VPC Router Farm Role may be used by other farms/roles. Are you '+
                          'sure you want to remove it?<br/> Farm Roles that using this router may not '+
                          'longer be able to communicate with Scalr.';
                }
                Scalr.Confirm({
                    type: 'delete',
                    msg: msg,
                    success: function () {
                        view.store.remove(record);
                        view.refresh();
                    }
                });
                return false;
            }
        },
        beforeselect: function(view, record) {
            if (record.get('is_bundle_running') == true) {
                Scalr.message.Error('This role is locked by server snapshot creation process. Please wait till snapshot will be created.');
                return false;
            }
        }
     }
});

Ext.define('Scalr.ui.FarmRolesFlyingButton', {
	extend: 'Ext.AbstractPlugin',
	alias: 'plugin.flyingbutton',
	handler: Ext.emptyFn,

	init: function(client) {
		var me = this,
            ct = client.up();
        me.client = client;
		ct.on({
			afterrender: function() {
				me.button = Ext.widget({
                    xtype: 'button',
                    itemId: 'rolesLibraryBtn',
                    ui: 'tab',
                    allowDepress: false,
                    disableMouseDownPressed: true,
                    hrefTarget: null,
                    toggleGroup: 'farmdesigner-farm-settings',
                    text: 'Add farm role',
                    textAlign: 'center',
                    cls: 'x-btn-farmroles-add',
                    //floating: true,
                    renderTo: ct.body.el,
                    handler: function(comp, state) {
                        if (state) {
                            me.handler.apply(me.client);
                        }
                    }
                });
            }
        });
        client.on({
			resize: {
                fn:me.updatePosition,
                scope: me
            },
			itemremove: {
                fn: me.updatePosition,
                scope: me
            },
			itemadd: {
                fn: me.updatePosition,
                scope: me
            },
			refresh: {
                fn: me.updatePosition,
                scope: me
            }
		})
	},

	updatePosition: function() {
        var buttonTop = '';
        if (this.button) {
            var el = this.client.el.down('div');
            buttonTop = (this.client.el.getHeight() < el.getHeight() ? this.client.el.getHeight() : el.getHeight())+'px';

            if (buttonTop !== this.buttonTop) {
                this.button.setStyle('top', buttonTop);
                this.buttonTop = buttonTop;
            }
		}
	}

});

/*roles drag and drop*/
Ext.define('Scalr.ui.FarmRolesDragZone', {
    extend: 'Ext.view.DragZone',
    onInitDrag: function(x, y) {
        var me = this,
            data = me.dragData,
            view = data.view,
            selectionModel = view.getSelectionModel(),
            record = view.getRecord(data.item);
        // Update the selection to match what would have been selected if the user had
        // done a full click on the target node rather than starting a drag from it
        /* Changed */
        /*if (!selectionModel.isSelected(record)) {
            selectionModel.selectWithEvent(record, me.DDMInstance.mousedownEvent);
        }*/
        data.records = [record];//selectionModel.getSelection();
        /* End */
        Ext.fly(me.ddel).setHtml(me.getDragText());
        me.proxy.update(me.ddel);
        me.onStartDrag(x, y);
        return true;
    },
});

Ext.define('Scalr.ui.FarmRolesDropZone', {
    extend: 'Ext.view.DropZone',

	handleNodeDrop : Ext.emptyFn

});

Ext.define('Scalr.ui.FarmRolesDragDrop', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.viewdragdrop',

    uses: [
        'Ext.view.ViewDragZone',
        'Ext.view.ViewDropZone'
    ],

    dragText : 'move Farm Role to the new launch position',
    ddGroup : "ViewDD",
    enableDrop: true,

    enableDrag: true,
	offsetY: 0,
	handleNodeDrop: Ext.emptyFn,

    init : function(view) {
        view.on('render', this.onViewRender, this, {single: true});
    },

    destroy: function() {
        Ext.destroy(this.dragZone, this.dropZone);
    },

    enable: function() {
        var me = this;
        if (me.dragZone) {
            me.dragZone.unlock();
        }
        if (me.dropZone) {
            me.dropZone.unlock();
        }
        me.callParent();
    },

    disable: function() {
        var me = this;
        if (me.dragZone) {
            me.dragZone.lock();
        }
        if (me.dropZone) {
            me.dropZone.lock();
        }
        me.callParent();
    },

    onViewRender : function(view) {
        var me = this;

        if (me.enableDrag) {
            me.dragZone = new Scalr.ui.FarmRolesDragZone({
                view: view,
                ddGroup: me.dragGroup || me.ddGroup,
                dragText: me.dragText
            });
        }

        if (me.enableDrop) {
            me.dropZone = new Scalr.ui.FarmRolesDropZone({
                view: view,
                ddGroup: me.dropGroup || me.ddGroup,
				offsetY: me.offsetY
            });
        }
    }
});

Ext.define('Scalr.ui.FormInstanceTypeField', {
	extend: 'Ext.form.field.ComboBox',
	alias: 'widget.instancetypefield',

    editable: true,
    hideInputOnReadOnly: true,
    queryMode: 'local',
    fieldLabel: 'Instance type',
    anyMatch: true,
    autoSearch: false,
    selectOnFocus: true,
    restoreValueOnBlur: true,
    store: {
        fields: [ 'id', 'name', 'note', 'ram', 'type', 'vcpus', 'disk', 'ebsencryption', 'ebsoptimized', 'placementgroups', {name: 'disabled', defaultValue: false}, 'disabledReason' ],
        proxy: 'object',
        sorters: {
            property: 'disabled'
        }
    },
    valueField: 'id',
    displayField: 'name',
    listConfig: {
        emptyText: 'No instance type matching query',
        emptyTextTpl: new Ext.XTemplate(
            '<div style="margin:8px 8px 0">' +
                '<span class="x-semibold">No instance type matching query</span>' +
                '<div style="line-height:24px">Instance types unavailable in <i>{cloudLocation}</i><tpl if="limits"> or restricted by Governance</tpl> are not listed</div>' +
            '</div>'
        ),
        cls: 'x-boundlist-alt',
        tpl:
            '<tpl for="."><div class="x-boundlist-item" style="white-space:nowrap;height: auto; width: auto;<tpl if="disabled">color:#999</tpl>">' +
                '<div><span class="x-semibold">{name}</span> &nbsp;<tpl if="disabled"><span style="font-size:12px;font-style:italic">({[values.disabledReason||\'Not compatible with the selected image\']})</span></tpl></div>' +
                '<div style="line-height: 26px;white-space:nowrap;">{[this.instanceTypeInfo(values)]}</div>' +
            '</div></tpl>'
    },
    updateListEmptyText: function(data) {
        var picker = this.getPicker();
        if (picker) {
            picker.emptyText = picker.emptyTextTpl.apply(data);
        }
    },
    initComponent: function() {
        this.plugins = {
            ptype: 'fieldicons',
            position: this.iconsPosition || 'inner',
            icons: [{id: 'governance', tooltip: 'The account owner has limited which instance types can be used in this Environment'}]
        };
        this.callParent(arguments);
        this.on('beforeselect', function(comp, record){
            if (record.get('disabled')) {
                return false;
            }
        }, this, {priority: 1});
    }
});

Ext.define('Ext.form.field.ComboBoxRadio', {
    extend:'Ext.form.field.ComboBox',
    alias: 'widget.comboradio',
	editable: false,
	queryMode: 'local',
	autoSelect: false,
    autoSearch: false,

    defaultListConfig: {},

    createPicker: function() {
        var me = this,
            picker,
            pickerCfg = Ext.apply({
                xtype: 'comboradiolist',
                pickerField: me,
                floating: true,
                hidden: true,
                store: me.store,
                displayField: me.displayField,
                preserveScrollOnRefresh: true,
                valueField: me.valueField
            }, me.listConfig, me.defaultListConfig);

        picker = me.picker = Ext.widget(pickerCfg);

        me.mon(picker, {
            selectionchange: me.onListSelectionChange,
            refresh: me.onListRefresh,
            scope: me
        });

        return picker;
    },

    onListRefresh: function() {
        if (!this.expanding) {
            this.alignPicker();
        }
        this.syncSelection();
    },

    onListSelectionChange: function(value) {
        var me = this;
        if (!me.ignoreSelection && (me.isExpanded || me.processSelectionChange)) {
	        me.setValue(value, false);
	        me.fireEvent('select', me, value);
	    }
    },

    findRecordByValue: function(value) {
        return this.findRecord(this.valueField, Ext.isObject(value)  ? value[this.valueField] : value);
    },

    setValue: function(value, doSelect) {
        var me = this,
            valueNotFoundText = me.valueNotFoundText,
            store = me.getStore(),
            inputEl = me.inputEl,
            matchedRecords = [],
            displayTplData = [],
            processedValue = [],
            autoLoadOnValue = me.autoLoadOnValue,
            pendingLoad = store.hasPendingLoad(),
            unloaded = autoLoadOnValue && store.loadCount === 0 && !pendingLoad,
            i, len, record, dataObj;

        if (value != null && (pendingLoad || unloaded || store.isEmptyStore)) {
            // Called while the Store is loading or we don't have the real store bound yet.
            // Ensure it is processed by the onLoad/bindStore.
            me.value = value;
            /*me.setHiddenValue(me.value);
            if (unloaded && store.getProxy().isRemote) {
                store.load();
            }*/
            return me;
        }

        // This method processes multi-values, so ensure value is an array.
        value = Ext.Array.from(value);

        // Loop through values, matching each from the Store, and collecting matched records
        for (i = 0, len = value.length; i < len; i++) {
            record = value[i];
            if (!record || !record.isModel) {
                record = me.findRecordByValue(record);
            }
            if (record) {
            	var processedValueTmp, tplData;
                matchedRecords.push(record);
                if (Ext.isObject(value[i])) {
		            processedValueTmp = {};
	                processedValueTmp[me.valueField] = record.get(me.valueField);
	                if (value[i].items) {
                		processedValueTmp['items'] = value[i].items;
                        tplData = value[i].items.join(', ');
                	}
                	//todo check items
                } else {
                	processedValueTmp = record.get(me.valueField);
                    tplData = record.get(me.displayField);
                }
                processedValue.push(processedValueTmp);
                displayTplData.push(tplData);
            } else if (Ext.isDefined(valueNotFoundText)) {
                displayTplData.push(valueNotFoundText);
            }
        }

        // Set the value of this field. If we are multiselecting, then that is an array.
        me.setHiddenValue(processedValue);
        me.value = me.multiSelect ? processedValue : processedValue[0];
        if (!Ext.isDefined(me.value)) {
            me.value = undefined;
        }
        me.displayTplData = displayTplData; //store for getDisplayValue method
        me.lastSelection = me.valueModels = matchedRecords;

        if (inputEl && me.emptyText && !Ext.isEmpty(value)) {
            inputEl.removeCls(me.emptyCls);
        }

        // Calculate raw value from the collection of Model data
        me.setRawValue(me.getDisplayValue());
        me.checkChange();

        if (doSelect !== false) {
            me.syncSelection();
        }
        me.applyEmptyText();

        return me;
    },

    getValue: function() {
        // If the user has not changed the raw field value since a value was selected from the list,
        // then return the structured value from the selection. If the raw field value is different
        // than what would be displayed due to selection, return that raw value.
        var me = this,
            picker = me.picker,
            rawValue = me.getRawValue(), //current value of text field
            value = me.value; //stored value from last selection or setValue() call

        if (me.getDisplayValue() !== rawValue) {
            value = rawValue;
            me.value = me.displayTplData = me.valueModels = undefined;
            if (picker) {
                me.ignoreSelection++;
                picker.items.each(function(item){
                	item.setChecked(false);
                });
                me.ignoreSelection--;
            }
        }

        // Return null if value is undefined/null, not falsy.
        me.value = value == null ? null : value;
        return me.value;
    },

    isEqual: function(v1, v2) {
        var fromArray = Ext.Array.from,
            i, j, len;

        v1 = fromArray(v1);
        v2 = fromArray(v2);
        len = v1.length;

        if (len !== v2.length) {
            return false;
        }

        for(i = 0; i < len; i++) {
        	if (Ext.isObject(v2[i])) {
        		if (v2[i][this.valueField] !== v1[i][this.valueField]) {
        			return false;
        		} else if (v2[i].items && v1[i].items && v2[i].items.length === v1[i].items.length){
        			for(j = 0; j < v2[i].items.length; j++) {
        				if (v2[i].items[j] != v2[i].items[j]) {
        					return false;
        				}
        			}
        		} else if (v2[i].items || v1[i].items){
        			return false;
        		}
        	} else if (v2[i] !== v1[i]) {
                return false;
            }
        }

        return true;
    },

   syncSelection: function() {
        var me = this,
            picker = me.getPicker();

        if (picker) {
        	var value, items, values = Ext.Array.from(me.value);
            me.ignoreSelection++;
            picker.items.each(function(item){
            	var checked = false;
            	for (var i=0, len=values.length; i<len; i++) {
            		value = null;
            		items = [];
            		if (Ext.isObject(values[i])) {
            			value = values[i][me.valueField];
            			items = values[i].items || [];
            		} else {
            			value = values[i];
            		}
	           		if (value == item.value) {
	           			checked = true;
            		} else if (items.length) {
            			for (var j=0, len1=items.length; j<len1; j++) {
		 	           		if (items[j] == item.value) {
			           			checked = true;
			           			break;
			           		}
		            	}
            		}
            		if (checked) {
            			break;
            		}
            	}
            	item.setChecked(checked);
            });
            me.ignoreSelection--;
        }
    }
});

Ext.define('Ext.view.ComboRadioList', {
    extend: 'Ext.menu.Menu',
    alias: 'widget.comboradiolist',
    mixins: {
        storeholer: 'Ext.util.StoreHolder'
    },
    baseCls: 'x-comboradiolist',
    initComponent: function() {
        var me = this;

        me.store = Ext.data.StoreManager.lookup(me.store || 'ext-empty-store');
        me.bindStore(me.store, true);

        me.callParent();
        me.onDataRefresh();
    },

    getNavigationModel: Ext.emptyFn,

    onShow: function(){
    	this.callParent();
    	this.fireEvent('refresh');
    },

    onItemClick: function(item) {
        var me = this,
            checked = item.checked;
    	if (checked || item.parentValue !== undefined) {
    		var params;

	    	if (item.hasItems || item.parentValue !== undefined) {
	    		params = {};
	    		params[me.valueField] = item.record.get(me.valueField);
	    		params.items = [];
	    		var items = me.query('[parentValue='+params[me.valueField]+']');
	    		for (var i=0, len=items.length; i<len; i++) {
	    			if (items[i].checked) {
	    				params.items.push(items[i].value);
	    			}
	    		}
                if (item.parentValue !== undefined) {
                    me.down('[value="'+item.parentValue+'"]').setChecked(true);
                }
	    	} else {
	    		params = item.record.get(me.valueField);
	    	}
    		me.fireEvent('selectionchange', params);
    	}

		if (checked && item.group !== undefined && !item.hasItems) {
			me.pickerField.collapse();
		}
    },

    bindStore : function(store, initial) {
        var me = this;
        me.mixins.storeholer.bindStore.apply(me, arguments);
    },

    onDataRefresh: function() {
        var me = this,
            clickHandler = Ext.bind(me.onItemClick, me);
        me.removeAll();
        me.store.getUnfiltered().each(function(record) {
        	var value = record.get(me.valueField),
        		text = record.get(me.displayField),
        		items = record.get('items');
        	me.add({
                xtype: 'menucheckitem',
                group: me.getId(),
                hasItems: items ? true : false,
                text: text,
                value: value,
                hideOnClick: false,
                record: record,
                handler: clickHandler
            });
            if (items) {
            	for (var i=0, len=items.length; i<len; i++) {
		        	me.add({
                        xtype: 'menucheckitem',
                        cls: 'x-subitem' + (items[i].disabled ? ' x-menu-item-disabled-forced' : ''),
		                parentValue: value,
                        tooltip: items[i].disabled ? 'Zone is offline for maintenance' : null,
                        tooltipType: 'title',
		                text: items[i][me.displayField],
		                value: items[i].id,
		                disabled: items[i].disabled || false,
		                record: record,
                        handler: clickHandler
		            });
            	}
            }
        });
        me.fireEvent('refresh');
    },

    getStoreListeners: function() {
        var me = this;
        return {
            refresh: me.onDataRefresh,
            add: me.onDataRefresh,
            remove: me.onDataRefresh,
            update: me.onDataRefresh,
            clear: me.onDataRefresh
        };
    },

    onDestroy: function() {
        this.bindStore(null);
        this.callParent();
    }
});

Ext.define('Scalr.ui.FarmSettings', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.farmsettings',
    layout: 'card',
    dockedItems: [{
        xtype: 'container',
        itemId: 'tabs',
        dock: 'left',
        cls: 'x-docked-tabs x-docked-tabs-light',
        width: 190,
        defaults: {
            xtype: 'button',
            ui: 'tab',
            textAlign: 'left',
            allowDepress: false,
            disableMouseDownPressed: true,
            toggleGroup: 'farmsettings-tabs',
            toggleHandler: function(comp, state) {
                if (state) {
                    this.up('farmsettings').layout.setActiveItem(this.value)
                } else {
                    this.removeCls('x-btn-tab-invalid');
                }
            }
        },
        items: [{
            text: 'General',
            value: 'general',
            pressed: true
        },{
            text: 'Global variables',
            value: 'variables'
        },{
            text: 'Advanced',
            value: 'advanced'
        }]

    }],
    defaults: {
        cls: 'x-panel-column-left x-panel-column-left-with-tabs'
    },
    items: [{
        xtype: 'container',
        itemId: 'general',
        items: [{
            xtype: 'container',
            cls: 'x-fieldset-separator-bottom',
            itemId: 'base',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'fieldset',
                title: 'Farm settings',
                cls: 'x-fieldset-separator-none',
                layout: 'anchor',
                flex: 1,
                maxWidth: 600,
                defaults: {
                    anchor: '100%',
                    labelWidth: 125
                },
                items: [{
                    xtype: 'textfield',
                    name: 'name',
                    itemId: 'farmName',
                    fieldLabel: 'Name'
                }, {
                    xtype: 'combo',
                    name: 'timezone',
                    itemId: 'timezone',
                    fieldLabel: 'Timezone',
                    store: {
                        fields: ['id', 'name'],
                        proxy: 'object',
                        sorters: {
                            property: 'name',
                            direction: 'ASC'
                        }
                    },
                    valueField: 'id',
                    displayField: 'name',
                    allowBlank: false,
                    anchor: '100%',
                    forceSelection: true,
                    editable: false,
                    queryMode: 'local',
                    anyMatch: true
                }, {
                    xtype: 'fieldcontainer',
                    layout: 'hbox',
                    hidden: true,
                    itemId: 'farmOwnerCt',
                    fieldLabel: 'Owner',
                    items: [{
                        xtype: 'combo',
                        flex: 1,
                        itemId: 'farmOwner',
                        name: 'owner',
                        editable: false,
                        queryMode: 'local',
                        store: {
                            fields: ['id', 'email'],
                            proxy: 'object'
                        },
                        valueField: 'id',
                        displayField: 'email',
                        showWarning: true,
                        setTooltip: function (text) {
                            if (this.rendered) {
                                this.inputEl.set({'data-qtip': Ext.String.htmlEncode(text)});
                            } else {
                                this.on('afterrender', function () {
                                    this.inputEl.set({'data-qtip': Ext.String.htmlEncode(text)});
                                }, this, {single: true});
                            }
                        },
                        listeners: {
                            select: function(field) {
                                if (field.ownerId && field.showWarning) {
                                    Scalr.message.Warning('Be careful when changing a Farm\'s Owner, you may lose access.');
                                    field.showWarning = false;
                                }
                            }
                        }
                    }, {
                        xtype: 'button',
                        margin: '0 0 0 6',
                        itemId: 'historyBtn',
                        width: 80,
                        text: 'History',
                        handler: function () {
                            Scalr.Request({
                                processBox: {
                                    action: 'load'
                                },
                                url: '/farms/xGetOwnerHistory',
                                params: {
                                    farmId: this.up('#farmDesigner').moduleParams.farmId
                                },
                                success: function (data) {
                                    Scalr.utils.Window({
                                        xtype: 'grid',
                                        title: 'History',
                                        margin: 16,
                                        width: 600,
                                        store: {
                                            fields: ['newId', 'newEmail', 'changedById', 'changedByEmail', 'dt'],
                                            data: data.history,
                                            reader: 'object'
                                        },
                                        viewConfig: {
                                            emptyText: 'No changes have been made',
                                            deferEmptyText: false
                                        },
                                        columns: [
                                            {header: 'New Owner', flex: 1, dataIndex: 'newEmail', sortable: true},
                                            {
                                                header: 'Was set by',
                                                flex: 1,
                                                dataIndex: 'changedByEmail',
                                                sortable: true
                                            },
                                            {header: 'On', flex: 1, dataIndex: 'dt', sortable: true}
                                        ],
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
                                                handler: function () {
                                                    this.up('grid').close();
                                                }
                                            }]
                                        }]
                                    });
                                }
                            })
                        }
                    }]
                }, {
                    xtype: 'fieldcontainer',
                    layout: 'hbox',
                    itemId: 'teamOwnerCt',
                    fieldLabel: 'Team',
                    items: [{
                        xtype: 'combo',
                        flex: 1,
                        itemId: 'teamOwner',
                        name: 'teamOwner',
                        editable: false,
                        queryMode: 'local',
                        store: {
                            fields: ['id', 'name'],
                            proxy: 'object'
                        },
                        valueField: 'id',
                        displayField: 'name',
                        showWarning: true,
                        setTooltip: function (text) {
                            if (this.rendered) {
                                this.inputEl.set({'data-qtip': Ext.String.htmlEncode(text)});
                            } else {
                                this.on('afterrender', function () {
                                    this.inputEl.set({'data-qtip': Ext.String.htmlEncode(text)});
                                }, this, {single: true});
                            }
                        },
                        listeners: {
                            select: function(field) {
                                if (field.teamId && field.showWarning) {
                                    Scalr.message.Warning('Be careful when changing a Farm\'s Team, you may lose access.');
                                    field.showWarning = false;
                                }
                            }
                        }
                    }]
                }, {
                    xtype: 'buttongroupfield',
                    name: 'rolesLaunchOrder',
                    itemId: 'launchorder',
                    fieldLabel: 'Launch Order',
                    layout: 'hbox',
                    plugins: {
                        ptype: 'fieldicons',
                        align: 'left',
                        position: 'outer',
                        icons: ['info']
                    },
                    listeners: {
                        change: function(comp, value) {
                            this.updateIconTooltip('info', value == 0 ? 'Simultaneous: Launch all roles at the same time' : 'Sequential: Use drag and drop to adjust the launching order of roles');
                            this.up('farmsettings').fireEvent('launchordertoggle', value);
                        }
                    },
                    defaults: {
                        flex: 1,
                        maxWidth: 130
                    },
                    items: [{
                        text: 'Simultaneous',
                        value: '0'
                    }, {
                        text: 'Sequential',
                        value: '1'
                    }]
                },{
                    xtype: 'textarea',
                    name: 'description',
                    itemId: 'farmDescription',
                    fieldLabel: 'Description',
                    grow: true,
                    growMin: 70
                }]
            },{
                xtype: 'farmcostmetering',
                itemId: 'costMetering',
                title: 'Cost metering',
                flex: 1.1,
                hidden: true
            }]
        },{
            xtype: 'container',
            itemId: 'awsvpc',
            layout: 'anchor',
            items: [{
                xtype: 'displayfield',
                itemId: 'vpcinfo',
                cls: 'x-form-field-info x-form-field-info-fit',
                anchor: '100%',
                hidden: true,
                value: 'Amazon VPC settings can be changed on TERMINATED farm only.'
            },{
                xtype: 'fieldset',
                itemId: 'vpc',
                title: '&nbsp;',
                baseTitle: 'Launch this farm inside Amazon VPC',
                checkboxToggle: true,
                collapsed: true,
                collapsible: true,
                checkboxName: 'vpc_enabled',
                layout: 'anchor',
                markInvalid: function(error) {
                    Scalr.message.ErrorTip(error, this.down('[name="vpc_enabled"]').getEl(), {anchor: 'bottom'});
                },
                resetVpcSettings: function() {
                    this.toggleCheckboxDisabled(false);
                    this.forcedCollapse = true;
                    this.collapse();
                    this.forcedCollapse = false;
                    this.down('#vpcRegion').reset();
                },
                toggleCheckboxDisabled: function(disable) {
                    this.checkboxCmp.setDisabled(disable);
                },
                listeners: {
                    beforecollapse: function() {
                        var me = this;
                        if (me.checkboxCmp.disabled) {
                            return false;
                        } else if (!me.forcedCollapse && me.down('[name="vpc_id"]').getValue()) {
                            me.maybeResetVpcId(function(){
                                me.forcedCollapse = true;
                                me.collapse();
                                me.forcedCollapse = false;
                            });
                            return false;
                        }
                    },
                    beforeexpand: function() {
                        return !this.checkboxCmp.disabled;
                    }
                },
                maybeResetVpcId: function(okCb) {
                    var me = this,
                        moduleParams = me.up('#farmDesigner').moduleParams;

                    if (moduleParams.tabParams.farmRolesStore.getUnfiltered().length) {
                        Scalr.utils.Confirm({
                            form: {
                                xtype: 'container',
                                items: {
                                    xtype: 'component',
                                    style: 'text-align: center',
                                    margin: '36 32 0',
                                    html: '<span class="x-fieldset-subheader1">All VPC-related settings of all roles will be reset<br/>(including <b>Security groups</b> and <b>VPC subnets</b>).<br/>Are you sure you want to continue?</span>'
                                }
                            },
                            ok: 'Continue',
                            success: function() {
                                me.resetVpcId();
                                if (Ext.isFunction(okCb)) {
                                    okCb();
                                }
                            }
                        });
                    } else {
                        me.resetVpcId();
                        if (Ext.isFunction(okCb)) {
                            okCb();
                        }
                    }
                },
                resetVpcId: function() {
                    var moduleParams = this.up('#farmDesigner').moduleParams;
                    this.down('[name="vpc_id"]').reset();
                    moduleParams.tabParams.farmRolesStore.getUnfiltered().each(function (rec) {
                        if (rec.get('platform') === 'ec2') {
                            var settings = rec.get('settings', true);
                            delete settings['aws.vpc_subnet_id'];
                            delete settings['router.vpc.networkInterfaceId'];
                            delete settings['router.scalr.farm_role_id'];
                            settings['aws.security_groups.list'] = moduleParams['roleDefaultSettings']['security_groups.list'];
                        }
                    });
                },
                defaults: {
                    anchor: '50%',
                    maxWidth: 540,
                    labelWidth: 120
                },
                items: [{
                    xtype: 'combo',
                    itemId: 'vpcRegion',
                    name: 'vpc_region',
                    emptyText: 'Please select a VPC region',
                    editable: false,
                    fieldLabel: 'Cloud location',
                    plugins: {
                        ptype: 'fieldinnericoncloud',
                        platform: 'ec2'
                    },
                    store: {
                        fields: [ 'id', 'name' ],
                        proxy: 'object'
                    },
                    queryMode: 'local',
                    valueField: 'id',
                    displayField: 'name',
                    listeners: {
                        beforeselect: function(field, record) {
                            var me = this,
                                fieldset;
                            if (field.getPicker().isVisible() && !me.forcedSelect && me.next('[name="vpc_id"]').getValue()) {
                                fieldset = me.up('#vpc');
                                fieldset.maybeResetVpcId(function(){
                                    me.forcedSelect = true;
                                    me.setValue(record.get('id'));
                                    me.forcedSelect = false;
                                });
                                return false;
                            }
                        },
                        change: function(field, value) {
                            var vpcIdField = field.next(),
                                vpcIdFieldProxy = vpcIdField.store.getProxy(),
                                vpcLimits = Scalr.getGovernance('ec2', 'aws.vpc'),
                                disableAddNew = false;

                            vpcIdField.reset();
                            vpcIdField.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + value;
                            vpcIdFieldProxy.params = {cloudLocation: value};
                            delete vpcIdFieldProxy.filterFn;

                            if (vpcLimits && vpcLimits['regions'] && vpcLimits['regions'][value]) {
                                if (vpcLimits['regions'][value]['ids'] && vpcLimits['regions'][value]['ids'].length > 0) {
                                    vpcIdFieldProxy.filterFn = function(record) {
                                        return Ext.Array.contains(vpcLimits['regions'][value]['ids'], record.get('id'));
                                    };
                                    vpcIdField.setValue(vpcLimits['regions'][value]['ids'][0]);
                                    disableAddNew = true;
                                }
                            }
                            vpcIdField.getPlugin('comboaddnew').setDisabled(disableAddNew);
                        }
                    }
                }, {
                    xtype: 'combo',
                    flex: 1,
                    name: 'vpc_id',
                    emptyText: 'Please select a VPC ID',
                    editable: false,
                    fieldLabel: 'VPC ID',

                    queryCaching: false,
                    clearDataBeforeQuery: true,
                    store: {
                        fields: [ 'id', 'name' ],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'farmDesigner',
                            url: '/platforms/ec2/xGetVpcList',
                            root: 'vpc'
                        }
                    },
                    valueField: 'id',
                    displayField: 'name',
                    plugins: [{
                        ptype: 'comboaddnew',
                        pluginId: 'comboaddnew',
                        url: '/tools/aws/vpc/create'
                    }],
                    listeners: {
                        addnew: function(item) {
                            Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                                url: '/platforms/ec2/xGetVpcList',
                                params: this.store.proxy.params
                            });
                        },
                        beforequery: function() {
                            var vpcRegionField = this.prev('combo');
                            if (!vpcRegionField.getValue()) {
                                this.collapse();
                                Scalr.message.InfoTip('Select VPC region first.', vpcRegionField.inputEl, {anchor: 'bottom'});
                                return false;
                            }
                        },
                        beforeselect: function(field, record) {
                            var me = this,
                                fieldset;
                            if (field.getPicker().isVisible() && !me.forcedSelect && me.getValue()) {
                                fieldset = me.up('#vpc');
                                fieldset.maybeResetVpcId(function(){
                                    me.forcedSelect = true;
                                    me.setValue(record.get('id'));
                                    me.forcedSelect = false;
                                });
                                return false;
                            }
                        }
                    }
                }]
            }]
        }]
    },{
        xtype: 'variablefield',
        name: 'variables',
        itemId: 'variables',
        currentScope: 'farm',
        encodeParams: false,
        removeTopSeparator: true,
        cls: 'x-panel-column-left x-panel-column-left-with-tabs',
        //fixme extjs5
        /*restoreContainerScrollState: function () {
            var me = this;

            var container = me.up('#farm');
            container.el.scrollTo('top', container.scrollTop || 0);

            return me;
        },*/
        updateFarmRoleVariable: function (variable, farmCurrent, scopes, farmDefault, farmLocked) {
            variable.scopes = scopes;
            variable.category = farmCurrent.category;

            if (!farmDefault) {
                if (farmCurrent.flagFinal == 1 || farmCurrent.flagRequired ||
                    farmCurrent.flagHidden == 1 || farmCurrent.format || farmCurrent.validator) {
                    if (farmCurrent.flagFinal == 1) {
                        variable.current = '';
                    }
                    variable.locked = farmCurrent;
                } else {
                    variable.locked = '';
                }

                if (farmCurrent.flagHidden == 1 && farmCurrent.value) {
                    farmCurrent.value = '******';
                }

                variable.default = farmCurrent;
            } else {
                variable.default = (farmCurrent && farmCurrent.value) ? farmCurrent : farmDefault;
                variable.locked = farmLocked;

                if (farmLocked.flagHidden == 1) {
                    variable.default.value = '******';
                }
            }

            if (variable.current && variable.current.value) {
                variable.scopes.push('farmrole');
            }
        },
        listeners: {
            load: function (me, data) {
                if (data.length) {
                    var farmVariables = [];

                    Ext.Array.each(data, function (variable) {
                        if (!variable['default']) {
                            var current = Ext.clone(variable.current);
                            var locked = '';

                            if (current.flagFinal == 1 || current.flagRequired ||
                                current.flagHidden == 1 || current.format || current.validator) {
                                if (current.flagHidden == 1) {
                                    current.value = '******';
                                }
                                locked = current;
                            }

                            farmVariables.push({
                                name: variable.name,
                                'default': current,
                                locked: locked,
                                scopes: Ext.Array.clone(variable.scopes),
                                category: variable.category
                            });
                        }
                    });

                    me.farmVariables = farmVariables;
                }
            },
            boxready: function (me) {
                var container = me.up('#farm');
                //fixme extjs5
                /*container.el.on('scroll', function () {
                    container.scrollTop = container.el.getScroll().top;
                }, me);*/
            },
            select: function (me) {
                //fixme extjs5
                //me.restoreContainerScrollState();
            },
            datachanged: function (me, record) {
                //fixme extjs5
                //me.restoreContainerScrollState();

                var name = record.get('name');
                var current = Ext.clone(record.get('current'));
                var def = record.get('default');
                var locked = Ext.clone(record.get('locked'));
                var scopes = Ext.Array.clone(record.get('scopes'));

                if (!def) {
                    Ext.Array.each(me.farmVariables, function (variable) {
                        if (variable.name === name) {
                            me.updateFarmRoleVariable(variable, current, scopes);
                            return false;
                        }
                    });
                }

                this.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.getUnfiltered().each(function (role) {
                    var variables = role.get('variables', true) || [];

                    Ext.Array.each(variables, function (variable) {
                        if (variable.name === name) {
                            me.updateFarmRoleVariable(variable, current, scopes, def, locked);
                        }
                    });

                    role.set('variables', variables);
                });
            },
            addvariable: function (me, record) {
                var name = record.get('name');
                var current = Ext.clone(record.get('current'));
                var scopes = Ext.Array.clone(record.get('scopes'));
                var newVariable = {
                    name: name,
                    category: record.get('category'),
                    'default': current,
                    scopes: scopes
                };

                if (!Ext.isDefined(me.farmVariables)) {
                    me.farmVariables = [];
                }

                me.farmVariables.push(newVariable);

                this.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.getUnfiltered().each(function (role) {
                    var variables = role.get('variables', true) || [];
                    var isVariableExist = false;

                    Ext.Array.each(variables, function (variable) {
                        if (variable.name === name) {
                            isVariableExist = true;
                        }
                    });

                    if (!isVariableExist) {
                        variables.push(newVariable);
                    }

                    role.set('variables', variables);
                });
            },
            removevariable: function (me, record) {
                //fixme extjs5
                //me.restoreContainerScrollState();

                var name = record.get('name');

                Ext.Array.each(me.farmVariables, function (variable, index, farmVariables) {
                    if (variable.name === name) {
                        farmVariables.splice(index, 1);

                        return false;
                    }
                });

                this.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.getUnfiltered().each(function (role) {
                    var variables = role.get('variables', true) || [];
                    var result = [];

                    Ext.Array.each(variables, function (variable) {
                        var current = variable.current;

                        if (variable.name !== name || (current && current.value)) {
                            if (current) {
                                variable.locked = '';
                                variable.default = '';
                                variable.scopes.shift();
                            }

                            result.push(variable);
                        }
                    });

                    role.set('variables', result);
                });
            }
        }
    },{
        xtype: 'container',
        itemId: 'advanced',
        items: [{
            xtype: 'fieldset',
            title: 'Scalr agent update settings',
            cls: 'x-fieldset-separator-none',
            itemId: 'szrUpdateSettings',
            defaults: {
                labelWidth: 140,
                width: 360
            },
            items: [{
                xtype: 'combo',
                itemId: 'updRepo',
                editable: false,
                name: 'szr.upd.repository',
                fieldLabel: 'Repository',
                valueField: 'name',
                displayField: 'name',
                store: {
                    fields: ['name'],
                    proxy: 'object'
                }
            },{
                xtype: 'fieldcontainer',
                itemId: 'updSchedule',
                fieldLabel: 'Schedule (UTC time)',
                layout: 'hbox',
                plugins: {
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: [{
                        id: 'info',
                        tooltip:
                            '*&nbsp;&nbsp;&nbsp;*&nbsp;&nbsp;&nbsp;*<br>' +
                            '─&nbsp;&nbsp;&nbsp;─&nbsp;&nbsp;&nbsp;─<br>' +
                            '│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>' +
                            '│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>' +
                            '│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;└───── day of week (0 - 6) (0 is Sunday)<br>' +
                            '│&nbsp;&nbsp;&nbsp;└─────── day of month (1 - 31)<br>' +
                            '└───────── hour (0 - 23)<br>'
                    }]
                },
                defaults: {
                    xtype: 'textfield',
                    flex: 1,
                    margin: '0 6 0 0'
                },
                items: [{
                    name: 'hh',
                    validator: function(value) {
                        return /^(\*|(1{0,1}\d|2[0-3])(-(1{0,1}\d|2[0-3])){0,1}(,(1{0,1}\d|2[0-3])(-(1{0,1}\d|2[0-3])){0,1})*)(\/(1{0,1}\d|2[0-3])){0,1}$/.test(value) || 'Invalid format';
                    }
                },{
                    name: 'dd',
                    validator: function(value) {
                        return /^(\*|([12]{0,1}\d|3[01])(-([12]{0,1}\d|3[01])){0,1}(,([12]{0,1}\d|3[01])(-([12]{0,1}\d|3[01])){0,1})*)(\/([12]{0,1}\d|3[01])){0,1}$/.test(value) || 'Invalid format';
                    }
                },{
                    name: 'dw',
                    margin: 0,
                    validator: function(value) {
                        if (value !== '*' && this.prev('[name="dd"]').getValue() !== '*') {
                            return '"Day of month" and "Day of week" cannot be set at the same time';
                        } else {
                            return /^(\*|[0-6](-([0-6])){0,1}(,([0-6])(-([0-6])){0,1})*)(\/([0-6])){0,1}$/.test(value) || 'Invalid format';
                        }
                    }
                }]
            }]
        }]
    }],
    listeners: {
        boxready: function() {
            var tabs = this.getDockedComponent('tabs');
            this.items.each(function(item){
                item.on('activate', function(){
                    var btn = tabs.down('[value="'+this.itemId+'"]');
                    if (!btn.pressed) {
                        btn.setPressed();
                    }
                });
            });
        },
        activate: function() {
            this.down('farmcostmetering').refresh();
        },
        setfarm: function(newFarmSettings) {
            var farmDesigner = this.up('#farmDesigner'),
                moduleParams = farmDesigner.moduleParams,
                vpcFieldset,
                disallowVpcToggle = false,
                defaultVpcEnabled = false,
                preloadVpcIdList = false,
                ct, field, schedule,
                farmSettings = moduleParams.farm ? Ext.clone(moduleParams.farm.farm) : {isNew: true};

            this.clearErrors();
            if (farmSettings.isNew && newFarmSettings) {
                Ext.apply(farmSettings, newFarmSettings);
            }
            Ext.applyIf(farmSettings, {
                timezone: moduleParams['timezone_default'],
                rolesLaunchOrder: '0',
                variables: moduleParams.farmVariables,
                status: 0
            });

            this.layout.setActiveItem('general');
            this.getComponent('general').suspendLayouts();

            //base
            ct = this.down('#base');

            field = ct.down('#costMetering');
            if (Scalr.flags['analyticsEnabled']) {
                field.setValue(moduleParams.analytics);
            }
            field.setVisible(Scalr.flags['analyticsEnabled']);

            ct.down('#timezone').store.load({data: Ext.Array.toValueMap(moduleParams.timezones_list)});

            field = ct.down('#farmOwner');
            field.store.load({data: moduleParams.usersList});
            field.setDisabled(!farmSettings.ownerEditable || farmSettings.isNew);
            field.setReadOnly(!farmSettings.ownerEditable || farmSettings.isNew);
            field.ownerId = farmSettings.owner;
            field.showWarning = true;
            //field.setTooltip(!farmSettings.ownerEditable ? 'Only account owner or farm owner can change this field' : '');

            field = ct.down('#teamOwner');
            field.store.load({data: moduleParams.teamsList});
            field.teamId = farmSettings.teamOwner;
            field.showWarning = true;
            field.setDisabled(!farmSettings.teamOwnerEditable && !farmSettings.isNew);
            field.setReadOnly(!farmSettings.teamOwnerEditable && !farmSettings.isNew);

            ct.down('#historyBtn').setVisible(!!farmSettings.ownerEditable);
            ct.down('#farmOwnerCt').setVisible(!!moduleParams.farm);

            ct.resetFieldValues();
            ct.setFieldValues(farmSettings);

            //vpc
            ct = this.down('#awsvpc');
            ct.setVisible(!!moduleParams['farmVpcEc2Enabled']);
            vpcFieldset = ct.down('#vpc');
            vpcFieldset.resetVpcSettings();
            if (moduleParams['farmVpcEc2Enabled']) {
                ct.down('#vpcRegion').store.load({data: moduleParams['farmVpcEc2Locations']});
                var vpcLimits = Scalr.getGovernance('ec2', 'aws.vpc'),
                    vpcRegionField = ct.down('[name="vpc_region"]');
                vpcRegionField.store.clearFilter();
                if (vpcLimits) {
                    if (vpcLimits['regions']) {
                        var defaultRegion;
                        vpcRegionField.store.filter({filterFn: function(region){
                            var r = vpcLimits['regions'][region.get('id')];
                            if (r !== undefined && r['default'] == 1) {
                                defaultRegion = region.get('id');
                            }
                            return r !== undefined;
                        }});
                        if (!farmSettings.vpc || !farmSettings.vpc.region) {
                            vpcRegionField.setValue(defaultRegion || vpcRegionField.store.first());
                        }
                        preloadVpcIdList = true;
                    }
                    disallowVpcToggle = defaultVpcEnabled = vpcLimits['value'] == 1;
                }
                vpcFieldset.setTitle(vpcFieldset.baseTitle + (vpcLimits?'&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Ext.String.htmlEncode(Scalr.strings['farmbuilder.vpc.enforced']) + '" class="x-icon-governance" />':''));

                if (farmSettings.vpc && farmSettings.vpc.id) {
                    farmSettings.vpc_enabled = true;
                    farmSettings.vpc_region = farmSettings.vpc.region;
                    farmSettings.vpc_id = farmSettings.vpc.id;
                    if (disallowVpcToggle && !defaultVpcEnabled) {
                        disallowVpcToggle = false;
                    }
                    preloadVpcIdList = true;
                } else {
                    if (farmSettings.isNew) {
                        farmSettings.vpc_enabled = defaultVpcEnabled;
                    } else if (disallowVpcToggle && defaultVpcEnabled) {
                        disallowVpcToggle = false;
                    }
                }

                if (farmSettings.status != 0) {
                    disallowVpcToggle = true;
                }
                Ext.Array.each(vpcFieldset.query('[isFormField]'), function(field){
                    if(field.name!=='vpc_enabled') field.setDisabled(farmSettings.status != 0);
                });
                ct.down('#vpcinfo').setVisible(farmSettings.status != 0);

                ct.setFieldValues(farmSettings);
                vpcFieldset.toggleCheckboxDisabled(disallowVpcToggle);

                if (preloadVpcIdList) {
                    ct.down('[name="vpc_id"]').store.load();
                }
            }
            this.getComponent('general').resumeLayouts(true);

            //global variables
            this.getComponent('variables').setValue(farmSettings.variables);

            //advanced
            ct = this.down('#advanced');
            ct.resetFieldValues();
            field = ct.down('#updRepo');
            field.store.load({data: moduleParams.tabParams['scalr.scalarizr_update.repos']});
            field.emptyText = 'System default' + (moduleParams.tabParams['scalr.scalarizr_update.default_repo'] ? ' (' + moduleParams.tabParams['scalr.scalarizr_update.default_repo'] + ')' : '');
            field.applyEmptyText();
            field.setValue(farmSettings['szr.upd.repository'] || '');

            schedule = (farmSettings['szr.upd.schedule'] || '').split(' ');
            ct.down('#updSchedule').setFieldValues({
                hh: schedule.length === 3 ? schedule[0] : '*',
                dd: schedule.length === 3 ? schedule[1] : '*',
                dw: schedule.length === 3 ? schedule[2] : '*',
            });
        }
    },
    getValues: function() {
        var tab,
            values = {},
            tabValues,
            farmDesigner = this.up('#farmDesigner'),
            moduleParams = farmDesigner.moduleParams;
        //general
        tab = this.getComponent('general');
        Ext.apply(values, tab.down('#base').getFieldValues(true));

        //vpc
        var vpcEnabledField = tab.down('[name="vpc_enabled"]'),
            vpcRegionField = tab.down('[name="vpc_region"]'),
            vpcIdField = tab.down('[name="vpc_id"]');
        if (vpcEnabledField.getValue()) {
            var vpcLimits = Scalr.getGovernance('ec2', 'aws.vpc');
            if (vpcLimits) {
                if (vpcLimits['value'] == 1 && (!vpcRegionField.getValue() || !vpcIdField.getValue())) {
                    this.layout.setActiveItem('general');
                    if (!vpcRegionField.getValue()) {
                        Scalr.message.InfoTip('VPC region is required.', vpcRegionField.inputEl);
                    } else if (!vpcIdField.getValue()) {
                        Scalr.message.InfoTip('VPC ID is required.', vpcIdField.inputEl);
                    }
                    return false;
                }
            }
            values['vpc_region'] = vpcRegionField.getValue();
            values['vpc_id'] = vpcIdField.getValue();
        }

        //variables
        values['variables'] = this.getComponent('variables').getValue();

        //advanced
        tab = this.getComponent('advanced');
        if (!tab.isValidFields()) {
            this.layout.setActiveItem('advanced');
            return false;
        }
        tabValues = tab.getFieldValues(true);

        if (moduleParams.tabParams.farm['status'] == 1 &&
            moduleParams.tabParams.farm['szr.upd.repository'] === 'latest' &&
            tabValues['szr.upd.repository'] === 'stable') {
            tab.down('#updRepo').markInvalid('Switching from \'latest\' repository to \'stable\' is not supported for running farms');
            this.layout.setActiveItem('advanced');
            return false;
        }

        values['szr.upd.repository'] = tabValues['szr.upd.repository'];
        values['szr.upd.schedule'] = ([
            tabValues['hh'] || '*',
            tabValues['dd'] || '*',
            tabValues['dw'] || '*'
        ]).join(' ');

        return values;
    },
    setErrors: function(errors) {
        var me = this,
            tabs = this.getDockedComponent('tabs');
        Ext.Object.each(errors, function(name, error){
            var field = me.down('[name="' + name + '"]');
            field = field || me.down('#' + name);
            if (field && field.markInvalid) {
                field.markInvalid(error);
            }
        });
        me.clearErrors();
        if (errors['variables'] !== undefined) {
            tabs.down('[value="variables"]').addCls('x-btn-tab-invalid');
        }

        if (Ext.Object.getSize(errors) === 1 && errors['variables'] !== undefined) {
            this.layout.setActiveItem('variables');
        } else {
            this.layout.setActiveItem('general');
            tabs.down('[value="general"]').addCls('x-btn-tab-invalid');
        }
    },

    clearErrors: function(errors) {
        var me = this,
            tabs = me.getDockedComponent('tabs');
        tabs.items.each(function(tab){
            tab.removeCls('x-btn-tab-invalid');
        });
    }
});

Ext.define('Scalr.ui.FarmRoleEditor', {
	extend: 'Ext.container.Container',
	alias: 'widget.farmroleeditor',

	layout: {
		type: 'vbox',
        align: 'stretch'
	},
	currentRole: null,
    activeFarmRoleTab: null,
    cls: 'x-farmrole-editor',
    items: [{
        xtype: 'panel',
        flex: 1,
        layout: 'card',
        itemId: 'tabspanel',
        style: 'background:#cdddeb',
        dockedItems: [{
            xtype: 'container',
            itemId: 'tabs',
            dock: 'left',
            cls: 'x-docked-tabs x-docked-tabs-light',
            width: 200,
            padding: 0,
            autoScroll: true
        }],
        onAdd: function (cmp) {
            cmp.tabButton = this.getDockedComponent('tabs').add({
                xtype: 'button',
                ui: 'tab',
                textAlign: 'left',
                text: cmp.isDeprecated ? cmp.tabTitle + '<span class="superscript">deprecated</span>' : cmp.tabTitle,
                toggleGroup: 'farmroleeditor-tabs',
                allowDepress: false,
                tabCmp: cmp,
                cls: cmp.isDeprecated ? 'x-btn-tab-deprecated' : '',
                handler: function (b) {
                    this.layout.setActiveItem(b.tabCmp);
                },
				disableMouseDownPressed: true,
                scope: this
            });
        }
    }],

    initComponent: function() {
        this.activeFarmRoleTab = {};
        this.callParent(arguments);
        this.on({
            beforeactivate: function () {
                var me = this,
                    activate = {
                        flag: me.activeFarmRoleTab[me.currentRole.get('farm_role_id')] || true,
                        tab: null
                    };
                this.suspendLayouts();
                for (var i=0, len=this.tabs.length; i<len; i++) {
                    activate = this.tabs[i].setCurrentRole( me.currentRole, activate);
                }
                if (activate.tab) {
                    activate.tab.ownerCt.layout.setActiveItem(activate.tab);
                    activate.tab.tabButton.toggle(true);
                }
                this.resumeLayouts(true);
            },

            deactivate: function () {
                var tabsPanel = this.getComponent('tabspanel');
                if (tabsPanel.layout.activeItem) {
                    tabsPanel.layout.activeItem.hide();
                    tabsPanel.layout.activeItem.fireEvent('deactivate', tabsPanel.layout.activeItem);
                    tabsPanel.layout.activeItem = null;
                }
            },
            tabactivate: function(tab) {
                this.activeFarmRoleTab[tab.currentRole.get('farm_role_id')] = tab.itemId;
            }
        });
	},

    initTabs: function() {
        this.activeFarmRoleTab = {};
        if (this.tabs) {
            Ext.each(this.tabs, function(tab) {
                tab.onFarmLoad();
            });
            return;
        } else {
            var tabsPanel = this.getComponent('tabspanel'),
                moduleParams = this.up('#farmDesigner').moduleParams;
            this.tabs = [];

            this.tabs.push(this.insert(0, Ext.create('Scalr.ui.FarmRoleEditorTab.Main')));
            for (var i = 0; i < moduleParams.tabs.length; i++) {
                var tabId = moduleParams.tabs[i];
                var tab = tabsPanel.add(Ext.create('Scalr.ui.FarmRoleEditorTab.' + Ext.String.capitalize(tabId)));
                this.tabs.push(tab);
                tab.onFarmLoad();
            }
        }
    },

    addFarmRoleDefaults: function (record) {
		var settings = record.get('settings'),
            roleDefaultSettings = this.up('#farmDesigner').moduleParams.roleDefaultSettings || {};

		this.down('#tabspanel').items.each(function(item) {
			if (item.isEnabled(record)) {
				Ext.applyIf(settings, item.getDefaultValues(record, roleDefaultSettings));
            }
		});
        Ext.applyIf(settings, this.down('#maintab').getDefaultValues(record, roleDefaultSettings));

		record.set('settings', settings);
	},

	setCurrentRole: function (record) {
		this.currentRole = record;
	},

    setActiveTab: function(id) {
        var ct = this.getComponent('tabspanel'),
            tab = ct.getComponent(id);
        if (tab) {
            ct.getDockedComponent('tabs').items.each(function(item) {
                if (item.tabCmp === tab) {
                    item.toggle(true);
                    ct.layout.setActiveItem(tab);
                    return false;
                }
            });
        }
    },

});

Ext.define('Scalr.ui.FarmRoleEditorTab', {
	extend: 'Ext.container.Container',
	tabTitle: '',
	autoScroll: true,
    layout: 'anchor',

	currentRole: null,

    tab: 'tab',//deprecated?

    initComponent: function() {
        this.addCls('x-farmrole-editor-tab');
        this.callParent(arguments);
        this.on({
            activate: function () {
                var me = this,
                    handler = function(){
                        me.activateTab();
                        me.showTab(me.currentRole);
                        me.highlightErrors();
                    };
                if (me.__items) {
                    me.add(me.__items);
                    me.__items = null;
                }
                if (me.itemId !== 'maintab') {
                    me.deactivateTab(true);
                }
                me.beforeShowTab(this.currentRole, handler);
                me.up('#farmRoleEditor').fireEvent('tabactivate', me);
            },
            deactivate: function () {
                var me = this;
                if (!me.deactivated) {
                    me.hideTab(me.currentRole);
                }
                me.clearErrors();
                me.tabButton.removeCls('x-btn-tab-invalid');
                me.up('#farmRoleEditor').fireEvent('tabdeactivate', me);
            },
            added: {
                fn: function() {
                    if (this.onRoleUpdate !== undefined) {
                        this.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.on('roleupdate', this.onRoleUpdate, this);
                    }
                },
                scope: this,
                single: true
            }
        });

    },

	setCurrentRole: function (record, activate) {
        var enabled = this.isEnabled(record),
            hasError;
		this.currentRole = record;
        this.tabButton.setVisible(enabled);
        if (enabled) {
            if (this.getTabTitle !== undefined) {
                this.tabButton.setText(this.getTabTitle(record));
            }
            hasError = this.hasError();
            if (activate.flag === true || this.itemId === activate.flag || hasError && activate.flag !== 'error' ) {
                activate.tab = this;
                activate.flag = hasError ? 'error' : false;
            }
            this.tabButton[hasError ? 'addCls' : 'removeCls']('x-btn-tab-invalid');
        }
        return activate;
	},

    hasError: function(){
        var me = this,
            errors = me.currentRole.get('errors', true),
            hasError = false;
        if (errors) {
            var tabSettings = me.getSettingsList();
            if (tabSettings !== undefined) {
                Ext.Object.each(errors, function(name, error){
                    if (name in tabSettings) {
                        hasError = true;
                        return false;
                    }
                });
            }
        }
        return hasError;
    },

	highlightErrors: function () {
        var me = this,
            errors;
        if (me.currentRole) {
            errors = me.currentRole.get('errors', true);
            if (errors) {
                var tabSettings = me.getSettingsList(),
                    counter = 0;
                if (tabSettings !== undefined) {
                    Ext.Object.each(errors, function(name, error){
                        if (name in tabSettings) {
                            var field = me.down('[name="' + name + '"]');
                            if (field && field.markInvalid) {
                                field.markInvalid(error);
                            } else if (!counter) {
                                Scalr.message.Flush(true);
                                if (Ext.isString(error)) {
                                    Scalr.message.Error(error);
                                }
                                counter++;
                            }
                        }
                    });
                }
            }
        }
    },

	clearErrors: function () {
        var errors;
        if (this.currentRole) {
            errors = this.currentRole.get('errors', true);
            if (errors) {
                var tabSettings = this.getSettingsList();
                if (tabSettings !== undefined) {
                    Ext.Object.each(errors, function(name, error){
                        if (name in tabSettings) {
                            delete errors[name];
                        }
                    });
                }
                if (Ext.Object.getSize(errors) === 0) {
                    this.currentRole.set('errors', null);
                }
            }
        }
    },

	beforeShowTab: function (record, handler) {
		this.el.unmask();
		handler();
	},

	showTab: Ext.emptyFn,

	hideTab: Ext.emptyFn,

    deactivateTab: function (noMessage) {
        this.deactivated = true;
		this.el.mask(noMessage ? null : 'Unable to load data from server.', 'x-mask-error');
	},

    activateTab: function () {
        this.deactivated = false;
        if (this.el) {//fixme extjs5
            this.el.unmask();
        }
	},

	isEnabled: function (record) {
        if (this.itemId === 'maintab') {
            return true;
        } else if (record.isVpcRouter()) {
            return Ext.Array.contains(['vpcrouter', 'devel', 'network'], this.itemId);
        } else {
            return this.itemId !== 'vpcrouter';
        }
	},

	getDefaultValues: function (record, roleDefaultSettings) {
        var me = this,
            moduleParams = this.up('#farmDesigner').moduleParams,
            values = {};
        if (me.settings !== undefined) {
            Ext.Object.each(me.settings, function(name, defaultValue){
                if (roleDefaultSettings !== undefined && roleDefaultSettings[name] !== undefined) {
                    values[name] = roleDefaultSettings[name];
                } else if (defaultValue !== undefined) {
                    values[name] = Ext.isFunction(defaultValue) ? defaultValue.call(me, record, moduleParams) : defaultValue;
                }
            });
        }
		return values;
	},

    getSettingsList: function() {
        return this.settings;
	},

    suspendOnRoleUpdate: 0,

    //defining this method in tab will allow to react to a role settings update
    //onRoleUpdate: function(record, name, value, oldValue) {},

    onFarmLoad: Ext.emptyFn
});

Ext.define('Scalr.ui.FarmRoleEditorTab.Main', {
	extend: 'Scalr.ui.FarmRoleEditorTab',

    itemId: 'maintab',

    layout: {
        type: 'hbox',
        align: 'stretch'
    },
    isLoading: false,
    cls: 'x-farmrole-editor-maintab x-panel-column-left',

    minified: false,
    stateful: true,
    stateId: 'farms-builder-roleedit-maintab',
    stateEvents: ['minify', 'maximize'],
    autoScroll: false,
    cache: null,

    listeners: {
        boxready: function() {
            var me = this;
            me.add({
                xtype: 'button',
                ui: '',
                cls: 'x-btn-collapse',
                enableToggle: true,
                disableMouseDownPressed: true,
                margin: '22 6 0 0',
                padding: 0,
                pressed: this.minified,
                toggleHandler: function(btn) {
                    me.toggleMinified();
                }
            });
        },
        staterestore: function() {
            this.setMinified(this.minified);
        }
    },

    getState: function() {
        return {
            minified: this.minified
        };
    },

    toggleMinified: function() {
        this.minified ? this.maximize() : this.minify();
    },

    minify: function(){
        this.setMinified(true);
        this.fireEvent('minify');
    },

    maximize: function() {
        this.setMinified(false);
        this.fireEvent('maximize');
    },

    setMinified: function(minified) {
        this.suspendLayouts();
        Ext.Array.each(this.query('[hideOnMinify],[showLabelOnMinify]'), function(item){
            if (item.hideOnMinify) {
                item.setVisible(!minified && !item.forceHidden);
            }
            if (item.showLabelOnMinify) {
                item.setWidth(minified ? 147 : 42);
                item.setFieldLabel(minified ? item._fieldLabel : '');
            }
        });
        this.resumeLayouts(true);
        this.setHeight(minified ? 66 : null);
        this.minified = minified;
    },

    setCurrentRole: function (record, activate) {
        this.currentRole = record;
        this.fireEvent('activate', this, this);
        return activate;
    },

    getDefaultValues: function (record) {
        switch (record.get('platform')) {
            case 'ec2':
                return {
                    'aws.availability_zone': '',
                    'aws.instance_type': record.get('image', true)['architecture'] == 'i386' ? 'm1.small' : 'm1.large'
                };
            break;
            case 'rackspace':
                return {
                    'rs.flavor-id': 1
                };
            break;
            case 'cloudstack':
            case 'idcf':
                return {
                    'cloudstack.service_offering_id': ''
                };
            break;
            case 'gce':
                return {
                    'gce.machine-type': ''
                };
            break;
            default:
                return {};
            break;
        }
    },

    beforeShowTab: function (record, handler) {
        var me = this,
            platform = record.get('platform'),
            cloudLocation = record.get('cloud_location');

        callback = function(data, status) {
            if (me.callPlatformHandler('beforeShowTab', [record, handler]) === false) {
                handler();
            }
        };

        if (platform === 'gce') {
            cloudLocation = record.getGceCloudLocation();
            cloudLocation = cloudLocation.length > 0 ? cloudLocation[0] : '';
        }

        Scalr.loadInstanceTypes(platform, cloudLocation, Ext.bind(me.setupInstanceTypeField, me, [record, callback], true));
    },

    setupInstanceTypeField: function(data, status, record, callback) {
        var me = this,
            field = me.down('[name="instanceType"]'),
            limits = Scalr.getGovernance(record.get('platform'), record.getInstanceTypeParamName()),
            instanceType = record.getInstanceType(data, limits, me.up('#farmDesigner').getVpcSettings());

        field.setDisabled(!status);
        field.store.load({ data: instanceType['list'] || [] });
        me.isLoading = true;
        field.setValue(instanceType['value']);
        field.resetOriginalValue();
        me.isLoading = false;
        field.setReadOnly(instanceType.list.length === 0 || (instanceType.list.length === 1 && instanceType.list[0].id === instanceType.value));
        field.toggleIcon('governance', !!limits);
        field.updateListEmptyText({cloudLocation:record.get('cloud_location'), limits: !!limits});

        if(callback) callback();
    },

    onRoleUpdate: function(record, name, value, oldValue) {
        if (this.suspendOnRoleUpdate > 0) {
            return;
        }

        var fullname = name.join('.'),
            comp;
        if (fullname === 'settings.scaling.min_instances') {
            comp = this.down('[name="min_instances"]');
        } else if (fullname === 'settings.scaling.max_instances') {
            comp = this.down('[name="max_instances"]');
        } else if (fullname === 'settings.scaling.enabled') {
            this.down('#costmetering').onScalingDisabled(value != 1);
        }

        if (comp) {
            comp.suspendEvents(false);
            comp.setValue(value);
            comp.resumeEvents();
            if (Scalr.flags['analyticsEnabled']) {
                this.down('#costmetering').updateRates();
            }
        }
    },

    showTab: function (record) {
        var me = this,
            settings = record.get('settings', true),
            platform = record.get('platform'),
            arch = record.get('image', true)['architecture'],
            roleBehaviors = record.get('behaviors').split(','),
            behaviors = [],
            tabParams = this.up('#farmDesigner').moduleParams.tabParams;
        me.isLoading = true;

        Ext.Array.each(roleBehaviors, function(b) {
           behaviors.push(tabParams['behaviors'][b] || b);
        });
        behaviors = behaviors.join(', ');

        me.setFieldValues({
            alias: record.get('alias'),
            min_instances: settings['scaling.min_instances'] || 1,
            max_instances: settings['scaling.max_instances'] || 1,
            running_servers: {
                running_servers: record.get('running_servers'),
                suspended_servers: record.get('suspended_servers'),
                'base.consider_suspended': settings['base.consider_suspended'] || 'running'
            }
        });
        if (Scalr.flags['analyticsEnabled']) {
            if (Ext.isNumeric(record.get('hourly_rate')) || Ext.Array.contains(me.up('#farmDesigner')['moduleParams']['analytics']['unsupportedClouds'], record.get('platform'))) {
                me.down('#costmetering').updateRates();
            } else {
                record.loadHourlyRate(null, function() {me.down('#costmetering').updateRates();});
            }
        }
        me.down('#roleName').update({platform: platform, name: record.get('name')});
        me.down('#replaceRole').setVisible(!record.get('new') && record.get('osId') && !tabParams.lock);
        me.down('#osName').update({osId: record.get('osId'), arch: !Ext.isEmpty(arch) ? ' (' + (arch == 'i386' ? '32' : '64') + 'bit)' : ''});
        me.down('#cloud_location').selectLocation(platform, platform === 'gce' ? settings['gce.region'] : record.get('cloud_location'));

        me.suspendLayouts();
        me.down('#column2').items.each(function(comp) {
            comp.setVisible(comp.checkPlatform !== undefined ? comp.checkPlatform(platform) : Ext.Array.contains(comp.platform, platform));
        });
        me.resumeLayouts(true);

        var scalingTab = me.up('farmroleeditor').down('#scaling'),
            topCostTab = me.down('#costmetering'),
            isVpcRouter = Ext.Array.contains(roleBehaviors, 'router');
        if (scalingTab && scalingTab.isEnabled(record) && settings['scaling.enabled'] == 1 || isVpcRouter) {
            topCostTab.onScalingDisabled(false);
            if (isVpcRouter) {
                topCostTab.down('[name="max_instances"]').setReadOnly(true);
                topCostTab.down('[name="min_instances"]').setReadOnly(true);
            } else {
                var readonly = scalingTab.isTabReadonly(record),
                    isCfRole = (Ext.Array.contains(roleBehaviors, 'cf_cloud_controller') || Ext.Array.contains(roleBehaviors, 'cf_health_manager'));
                topCostTab.down('[name="max_instances"]').setReadOnly(readonly);
                topCostTab.down('[name="min_instances"]').setReadOnly(readonly && (isCfRole || !record.get('new')));
            }
        } else {
            topCostTab.onScalingDisabled(true);
        }

        me.callPlatformHandler('showTab', arguments);

        me.isLoading = false;
    },

    onParamChange: function (name, value, text) {
        var record = this.currentRole;
        if (record && !this.isLoading) {
            this.suspendOnRoleUpdate++;
            switch (name) {
                case 'min_instances':
                case 'max_instances':
                    var settings = record.get('settings');
                    settings['scaling.' + name] = value;
                    record.set('settings', settings);
                break;
                case 'instanceType':
                    var settings = record.get('settings');
                    settings[record.getInstanceTypeParamName()] = value;
                    settings['info.instance_type_name'] = text;
                    record.set('settings', settings);
                break;
                case 'alias':
                    record.set('alias', value);
                    this.up('farmroleeditor').fireEvent('rolealiaschange', value);
                break;
                default:
                    this.callPlatformHandler('saveParam', [name, value]);
                break;
            }
            this.suspendOnRoleUpdate--;
        }
    },

    callPlatformHandler: function(method, args) {
        var handler = this.currentRole.get('platform');
        if (Scalr.isOpenstack(handler)) {
            handler = 'openstack';
        } else if (Scalr.isCloudstack(handler)) {
            handler = 'cloudstack';
        }
        if (this[handler] && this[handler][method]) {
            this[handler][method].apply(this, args);
        } else {
            return false;
        }
    },

    ec2: {
        beforeShowTab: function(record, handler) {
            this.cache = null;
            if (this.up('#farmDesigner').getVpcSettings() !== false || Ext.Array.contains(record.get('behaviors').split(','), 'router')) {
                this.down('[name="aws.availability_zone"]').hide();
                this.down('[name="aws.cloud_location"]').setValue(record.get('cloud_location')).show();
                handler();
            } else {
                Scalr.cachedRequest.load(
                    {
                        url: '/platforms/ec2/xGetAvailZones',
                        params: {cloudLocation: record.get('cloud_location')}
                    },
                    function(data, status, cacheId){
                        this.cache = data;
                        this.down('[name="aws.availability_zone"]').show().setDisabled(!status);
                        this.down('[name="aws.cloud_location"]').hide();
                        handler();
                    },
                    this
                );
            }
        },
        showTab: function(record) {
            var settings = record.get('settings', true),
                field;

            //availability zone
            field = this.down('[name="aws.availability_zone"]');
            var zones = Ext.Array.map(this.cache || [], function(item){ item.disabled = item.state != 'available'; return item;}),
                data = [{
                    zoneId: 'x-scalr-diff',
                    name: 'Distribute equally'
                },{
                    zoneId: '',
                    name: 'AWS-chosen'
                },{
                    zoneId: 'x-scalr-custom',
                    name: 'Selected by me',
                    items: zones
                }],
                zone = settings['aws.availability_zone'] || '',
                disableAvailZone =  record.get('behaviors').match('mysql') && settings['mysql.data_storage_engine'] == 'ebs' &&
                                    settings['mysql.master_ebs_volume_id'] != '' && settings['mysql.master_ebs_volume_id'] != undefined &&
                                    record.get('generation') != 2 && this.down('[name="aws.availability_zone"]').getValue() != '' &&
                                    this.down('[name="aws.availability_zone"]').getValue() != 'x-scalr-diff';

            field.store.loadData(data);
            if (zone.match(/x-scalr-custom/)) {
                zone = {zoneId: 'x-scalr-custom', items: zone.replace('x-scalr-custom=', '').split(':')};
            } else if (!Ext.isEmpty(zone) && zone !== 'x-scalr-diff' && zone != 'x-scalr-custom') {
                zone = {zoneId: 'x-scalr-custom', items: [zone]};
            }

            field.setValue(zone);
            if (!field.disabled) {
                field.setDisabled(disableAvailZone);
            }
            this.down('#aws_availability_zone_warn').setVisible(disableAvailZone && field.isVisible());
        },
        saveParam: function(name, value) {
            var record = this.currentRole,
                settings = record.get('settings');
            switch (name) {
                case 'aws.availability_zone':
                    if (Ext.isObject(value)) {
                        if (value.items) {
                            if (value.items.length === 1) {
                                settings[name] = value.items[0];
                            } else if (value.items.length > 1) {
                                settings[name] = value.zoneId + '=' + value.items.join(':');
                            }
                        }
                    } else {
                        settings[name] = value;
                    }
                break;
            }
            record.set('settings', settings);

        }
    },

    eucalyptus: {
        beforeShowTab: function(record, handler) {
            this.cache = null;
            if (this.up('#farmDesigner').getVpcSettings() !== false || Ext.Array.contains(record.get('behaviors').split(','), 'router')) {
                this.down('[name="euca.availability_zone"]').hide();
                this.down('[name="euca.cloud_location"]').setValue(record.get('cloud_location')).show();
                handler();
            } else {
                Scalr.cachedRequest.load(
                    {
                        url: '/platforms/eucalyptus/xGetAvailZones',
                        params: {cloudLocation: record.get('cloud_location')}
                    },
                    function(data, status, cacheId){
                        this.cache = data;
                        this.down('[name="euca.availability_zone"]').show().setDisabled(!status);
                        this.down('[name="euca.cloud_location"]').hide();
                        handler();
                    },
                    this
                );
            }
        },
        showTab: function(record) {
            var settings = record.get('settings', true),
                field;

            //availability zone
            field = this.down('[name="euca.availability_zone"]');
            var zones = Ext.Array.map(this.cache || [], function(item){ item.disabled = item.state != 'available'; return item;}),
                data = [{
                    zoneId: 'x-scalr-diff',
                    name: 'Distribute equally'
                },{
                    zoneId: '',
                    name: 'Euca-chosen'
                },{
                    zoneId: 'x-scalr-custom',
                    name: 'Selected by me',
                    items: zones
                }],
                zone = settings['euca.availability_zone'] || '',
                disableAvailZone =  record.get('behaviors').match('mysql') && settings['mysql.data_storage_engine'] == 'ebs' &&
                                    settings['mysql.master_ebs_volume_id'] != '' && settings['mysql.master_ebs_volume_id'] != undefined &&
                                    record.get('generation') != 2 && this.down('[name="aws.availability_zone"]').getValue() != '' &&
                                    this.down('[name="euca.availability_zone"]').getValue() != 'x-scalr-diff';

            field.store.loadData(data);
            if (zone.match(/x-scalr-custom/)) {
                zone = {zoneId: 'x-scalr-custom', items: zone.replace('x-scalr-custom=', '').split(':')};
            } else if (!Ext.isEmpty(zone) && zone !== 'x-scalr-diff' && zone != 'x-scalr-custom') {
                zone = {zoneId: 'x-scalr-custom', items: [zone]};
            }

            field.setValue(zone);
            if (!field.disabled) {
                field.setDisabled(disableAvailZone);
            }
            this.down('#euca_availability_zone_warn').setVisible(disableAvailZone && field.isVisible());
        },
        saveParam: function(name, value) {
            var record = this.currentRole,
                settings = record.get('settings');
            switch (name) {
                case 'euca.availability_zone':
                    if (Ext.isObject(value)) {
                        if (value.items) {
                            if (value.items.length === 1) {
                                settings[name] = value.items[0];
                            } else if (value.items.length > 1) {
                                settings[name] = value.zoneId + '=' + value.items.join(':');
                            }
                        }
                    } else {
                        settings[name] = value;
                    }
                break;
            }
            record.set('settings', settings);

        }
    },

    rackspace: {
        showTab: function(record) {
            this.down('[name="rs.cloud_location"]').setValue(record.get('cloud_location'));
        },
        saveParam: function(name, value) {
            var record = this.currentRole,
                settings = record.get('settings');
            settings[name] = value;
            record.set('settings', settings);
        }
    },

    openstack: {
        beforeShowTab: function(record, handler) {
            this.cache = null;
            Scalr.cachedRequest.load(
                {
                    url: '/platforms/openstack/xGetOpenstackResources',
                    params: {
                        cloudLocation: record.get('cloud_location'),
                        platform: record.get('platform')
                    }
                },
                function(data, status){
                    this.cache = data;
                    handler();
                },
                this
            );
        },
        showTab: function(record) {
            var settings = record.get('settings', true),
                availabilityZones = this.cache && this.cache['availabilityZones'] ? this.cache['availabilityZones'] : null,
                field;

            field = this.down('[name="openstack.availability_zone"]');
            if (availabilityZones) {
                this.down('[name="openstack.cloud_location"]').hide();

                //availability zone
                var zones = Ext.Array.map(availabilityZones || [], function(item){ item.disabled = item.state != 'available'; return item;}),
                    data = [{
                        zoneId: 'x-scalr-diff',
                        name: 'Distribute equally'
                    },{
                        zoneId: '',
                        name: 'Cloud-chosen'
                    },{
                        zoneId: 'x-scalr-custom',
                        name: 'Selected by me',
                        items: zones
                    }],
                    zone = settings['openstack.availability_zone'] || '';

                field.store.loadData(data);
                if (zone.match(/x-scalr-custom/)) {
                    zone = {zoneId: 'x-scalr-custom', items: zone.replace('x-scalr-custom=', '').split(':')};
                } else if (!Ext.isEmpty(zone) && zone !== 'x-scalr-diff' && zone != 'x-scalr-custom') {
                    zone = {zoneId: 'x-scalr-custom', items: [zone]};
                }

                field.show().setValue(zone);
            } else {
                field.hide();
                this.down('[name="openstack.cloud_location"]').show().setValue(record.get('cloud_location'));
            }
        },

        saveParam: function(name, value) {
            var record = this.currentRole,
                settings = record.get('settings');
            switch (name) {
                case 'openstack.availability_zone':
                    if (Ext.isObject(value)) {
                        if (value.items) {
                            if (value.items.length === 1) {
                                settings[name] = value.items[0];
                            } else if (value.items.length > 1) {
                                settings[name] = value.zoneId + '=' + value.items.join(':');
                            }
                        }
                    } else {
                        settings[name] = value;
                    }
                break;
            }
            record.set('settings', settings);

        }
    },

    cloudstack: {
        beforeShowTab: function(record, handler) {
            Scalr.CachedRequestManager.get('farmDesigner').load(
                {
                    url: '/platforms/cloudstack/xGetOfferingsList/',
                    params: {
                        cloudLocation: record.get('cloud_location'),
                        platform: record.get('platform'),
                        farmRoleId: record.get('new') ? '' : record.get('farm_role_id')
                    }
                },
                function(data, status){
                    if (data && data['features']) {//farmroleeditor
                        record.set('securityGroupsEnabled', !!data['features']['securityGroupsEnabled']);
                        this.up('farmroleeditor').down('#tabspanel').down('#security').tabButton.setVisible(!!data['features']['securityGroupsEnabled']);
                    }
                    handler();
                },
                this
            );
        },
        showTab: function(record) {
            this.down('[name="cloudstack.cloud_location"]').setValue(record.get('cloud_location'));
        },
        saveParam: function(name, value) {
            var record = this.currentRole,
                settings = record.get('settings');
            settings[name] = value;
            record.set('settings', settings);
        }
    },

    gce: {
        beforeShowTab: function(record, handler) {
            this.cache = null;
            Scalr.cachedRequest.load(
                {
                    url: '/platforms/gce/xGetOptions',
                    params: {}
                },
                function(data, status){
                    this.cache = data;
                    this.down('[name="gce.cloud-location"]').setDisabled(!status);
                    handler();
                },
                this
            );
       },
        showTab: function(record) {
            var data = this.cache || {},
                field, zones = [],
                settings = record.get('settings', true);

            field = this.down('[name="gce.cloud-location"]');
            if (settings['gce.region']) {
                Ext.each(data['zones'], function(zone){
                    if (zone['name'].indexOf(settings['gce.region']) === 0) {
                        zones.push(zone);
                    }
                });
            } else {
                zones = data['zones'] || [];
            }
            field.store.loadData(zones);
            field.setValue(record.getGceCloudLocation());
        },
        saveParam: function(name, value) {
            var record = this.currentRole;
            if (name === 'gce.cloud-location') {
                record.setGceCloudLocation(value);
                Scalr.loadInstanceTypes(record.get('platform'), record.getGceCloudLocation()[0], Ext.bind(this.setupInstanceTypeField, this, [record], true));
            }
        }
    },
    defaults: {
        padding: '18 16'
    },
    items: [{
        xtype: 'container',
        flex: 1.4,
        minWidth: 320,
        maxWidth: 440,
        layout: 'anchor',
        cls: 'x-fieldset-separator-right',
        defaults: {
            labelWidth: 110,
            anchor: '100%'
        },
        items: [{
            xtype: 'textfield',
            name: 'alias',
            fieldLabel: 'Alias',
            hideOnMinify: true,
            margin: '0 0 6',
            validateOnChange: false,
            vtype: 'rolename',
            validator: function(value){
                var error = false;
                if (this.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.countBy('alias', Ext.String.trim(value), this.up('#maintab').currentRole) > 0) {
                    error = 'Alias must be unique within the farm';
                }
                return error || true;
            },
            listeners: {
                focus: function() {
                    this.currentValue = this.getValue();
                },
                blur: function() {
                    if (this.validate() && this.currentValue != this.getValue()) {
                        this.up('#farmDesigner').onFarmRoleAliasChanged(this.currentValue, this.getValue());
                    }
                },
                change: function(comp, value) {
                    if (comp.validate()) {
                        comp.up('#maintab').onParamChange(comp.name, Ext.String.trim(value));
                    }
                }
            }
        },{
            xtype: 'container',
            hideOnMinify: true,
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            height: 30,
            items: [{
                xtype: 'label',
                text: 'Role name',
                width: 115,
                cls: 'x-form-item-label-default'
            },{
                xtype: 'component',
                cls: 'x-overflow-ellipsis',
                itemId: 'roleName',
                flex: 1,
                tpl: '<span title="{name}"><img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-platform-small x-icon-platform-small-{platform}"/>&nbsp;&nbsp;{name}</span>'
            },{
                xtype: 'button',
                itemId: 'replaceRole',
                hidden: true,
                minWidth: 36,
                height: 26,
                iconCls: 'x-icon-replace',
                tooltip: 'Replace role',
                padding: 0,
                handler: function() {
                    Scalr.event.fireEvent('modal','#/farms/roles/replaceRole?farmId=' + this.up('#farmDesigner').moduleParams.tabParams['farmId'] + '&farmRoleId=' + this.up('#maintab').currentRole.get('farm_role_id'));
                }
            }]
        },{
            xtype: 'container',
            hideOnMinify: true,
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            height: 30,
            margin: '0 0 6',
            items: [{
                xtype: 'label',
                text: 'OS',
                width: 115,
                cls: 'x-form-item-label-default'
            },{
                xtype: 'component',
                cls: 'x-overflow-ellipsis',
                itemId: 'osName',
                tpl: '{[this.getOsById(values.osId, values.arch)]}',
                flex: 1
            }]
        },{
            xtype: 'instancetypefield',
            name: 'instanceType',
            margin: '0 0 6 0',
            labelWidth: 110,
            listeners: {
                beforeselect: function(comp, record) {

                    var currentRole, storages, storageTab, allowChange = true, dbmsrTab, ec2Tab;
                    currentRole = comp.up('#maintab').currentRole;

                    if (currentRole.get('platform') === 'ec2') {
                        if (!record.get('ebsoptimized')) {
                            var tabsPanel = this.up('farmroleeditor').down('#tabspanel');

                            ec2Tab = tabsPanel.getComponent('ec2');
                            if (ec2Tab && ec2Tab.isVisible()) {
                                allowChange = ec2Tab.down('[name="aws.ebs_optimized"]').getValue() ? false : true;
                            } else {
                                allowChange = currentRole.get('settings', true)['aws.ebs_optimized'] != 1;
                            }
                            if (!allowChange) Scalr.message.InfoTip('Please remove EBS-Optimized flag under EC2 tab before changing instance type to '+record.get('name') + '.', comp.inputEl, {anchor: 'bottom'});
                        }

                        if (allowChange && !record.get('ebsencryption')) {
                            var tabsPanel = this.up('farmroleeditor').down('#tabspanel');

                            dbmsrTab = tabsPanel.getComponent('dbmsr');
                            if (dbmsrTab && dbmsrTab.isVisible()) {
                                allowChange = dbmsrTab.down('[name="db.msr.data_storage.ebs.encrypted"]').getValue() ? false : true;
                            } else {
                                allowChange = currentRole.get('settings', true)['db.msr.data_storage.ebs.encrypted'] != 1;
                            }
                            if (!allowChange) Scalr.message.InfoTip('Instance type '+record.get('name') + ' doesn\'t support EBS encrypted storage', comp.inputEl, {anchor: 'bottom'});
                            //check storages
                            if (allowChange) {
                                storageTab = tabsPanel.getComponent('storage');
                                if (storageTab && storageTab.isVisible()) {
                                    storages = storageTab.getStorages().storages;
                                } else {
                                    storages = (currentRole.get('storages', true)||{})['configs'];
                                }
                                if (storages) {
                                    Ext.each(storages, function(storage){
                                        if (storage.settings && storage.settings['ebs.encrypted'] == 1) {
                                            allowChange = false;
                                            return false;
                                        }
                                    });
                                    if (!allowChange) Scalr.message.InfoTip('Please remove EBS encrypted storages before changing instance type to '+record.get('name') + '.', comp.inputEl, {anchor: 'bottom'});
                                }
                            }
                        }
                    }
                    if (allowChange && Scalr.flags['analyticsEnabled']) {
                        if (!Ext.Array.contains(this.up('#farmDesigner')['moduleParams']['analytics']['unsupportedClouds'], currentRole.get('platform'))) {
                            currentRole.loadHourlyRate(record.get('id'), function(){comp.up('#maintab').down('#costmetering').updateRates()});
                        } else {
                            comp.up('#maintab').down('#costmetering').updateRates();
                        }
                    }
                    return allowChange;
                },
                change: function(comp, value) {
                    var record = this.findRecordByValue(value),
                        tab = comp.up('#maintab');
                    if (record) {
                        tab.onParamChange(comp.name, value, record.get('name'));
                        tab.down('#instanceTypeDetails').update(record.getData());
                    } else {
                        tab.down('#instanceTypeDetails').update('&ndash;');
                    }
                }
            }
        },{
            xtype: 'container',
            hideOnMinify: true,
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            items: [{
                xtype: 'label',
                text: 'Configuration',
                width: 115,
                cls: 'x-form-item-label-default'
            },{
                xtype: 'component',
                cls: 'x-overflow-ellipsis',
                itemId: 'instanceTypeDetails',
                flex: 1,
                html: '&nbsp;',
                tpl: '{[this.instanceTypeInfo(values)]}'
            }]
        }]
    },{
        xtype: 'container',
        flex: 1,
        maxWidth: 320,
        minWidth: 260,
        cls: 'x-fieldset-separator-right',
        layout: 'anchor',
        defaults: {
            anchor: '100%'
        },
        items: [{
            xtype: 'cloudlocationmap',
            mode: 'single',
            itemId: 'cloud_location',
            margin: '12 0 28 0',
            hideOnMinify: true,
            listeners: {
                selectlocation: function(location, state){
                    var tab = this.up('#maintab'),
                        record = tab.currentRole,
                        settings = record.get('settings', true);
                    //gce location beta
                    if (!settings['gce.region']) {
                        if (record.get('platform') === 'gce') {
                            var field = tab.down('[name="gce.cloud-location"]'),
                                value;
                            if (field) {
                                value = Ext.clone(field.getValue());
                                if (state) {
                                    if (!Ext.Array.contains(value, location)) {
                                        value.push(location);
                                    }
                                } else {
                                    if (value.length === 1) {
                                        Scalr.message.InfoTip('At least one zone must be selected!', field.inputEl);
                                    } else {
                                        Ext.Array.remove(value, location);
                                    }
                                }
                                field.setValue(value);
                            }
                        }
                    }
                }
            }
        },{
            xtype: 'container',
            itemId: 'column2',
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            defaults: {
                hidden: true
            },
            items: [{
                xtype: 'container',
                platform: ['ec2'],
                hidden: false,
                layout: {
                    type: 'hbox',
                    align: 'stretch'
                },
                items:[{
                    xtype: 'comboradio',
                    fieldLabel: 'Avail zone',
                    flex: 1,
                    name: 'aws.availability_zone',
                    valueField: 'zoneId',
                    displayField: 'name',
                    listConfig: {
                        cls: 'x-menu-light'
                    },
                    store: {
                        fields: [ 'zoneId', 'name', 'state', 'disabled', 'items' ],
                        proxy: 'object'
                    },
                    margin: 0,
                    labelWidth: 80,
                    listeners: {
                        collapse: function() {
                            var value = this.getValue();
                            if (Ext.isObject(value) && value.items.length === 0) {
                                this.setValue('');
                            }
                        },
                        change: function(comp, value) {
                            comp.up('#maintab').onParamChange(comp.name, value);
                        }
                    }
                },{
                    xtype: 'displayinfofield',
                    itemId: 'aws_availability_zone_warn',
                    hidden: true,
                    margin: '0 0 0 10',
                    info: 'If you want to change placement, you need to remove Master EBS volume first.'
                },{
                    xtype: 'displayfield',
                    fieldLabel: 'Cloud location',
                    labelWidth: 110,
                    name: 'aws.cloud_location',
                    hidden: true
                }]
            },{
                xtype: 'container',
                platform: ['eucalyptus'],
                layout: {
                    type: 'hbox',
                    align: 'stretch'
                },
                items:[{
                    xtype: 'comboradio',
                    fieldLabel: 'Avail zone',
                    flex: 1,
                    name: 'euca.availability_zone',
                    valueField: 'zoneId',
                    displayField: 'name',
                    listConfig: {
                        cls: 'x-menu-light'
                    },
                    store: {
                        fields: [ 'zoneId', 'name', 'state', 'disabled', 'items' ],
                        proxy: 'object'
                    },
                    margin: 0,
                    labelWidth: 70,
                    listeners: {
                        collapse: function() {
                            var value = this.getValue();
                            if (Ext.isObject(value) && value.items.length === 0) {
                                this.setValue('');
                            }
                        },
                        change: function(comp, value) {
                            comp.up('#maintab').onParamChange(comp.name, value);
                        }
                    }
                },{
                    xtype: 'displayinfofield',
                    itemId: 'euca_availability_zone_warn',
                    hidden: true,
                    margin: '0 0 0 10',
                    info: 'If you want to change placement, you need to remove Master EBS volume first.'
                },{
                    xtype: 'displayfield',
                    fieldLabel: 'Cloud location',
                    labelWidth: 110,
                    name: 'euca.cloud_location',
                    hidden: true
                }]
            },{
                xtype: 'displayfield',
                platform: ['rackspace'],
                fieldLabel: 'Cloud location',
                labelWidth: 110,
                name: 'rs.cloud_location'
            },{
                xtype: 'container',
                checkPlatform: function(platform) {
                    return Scalr.isOpenstack(platform);
                },
                layout: 'fit',
                items: [{
                    xtype: 'displayfield',
                    fieldLabel: 'Cloud location',
                    labelWidth: 110,
                    name: 'openstack.cloud_location'
                },{
                    xtype: 'comboradio',
                    fieldLabel: 'Avail zone',
                    flex: 1,
                    name: 'openstack.availability_zone',
                    valueField: 'zoneId',
                    displayField: 'name',
                    listConfig: {
                        cls: 'x-menu-light'
                    },
                    store: {
                        fields: [ 'zoneId', 'name', 'state', 'disabled', 'items' ],
                        proxy: 'object'
                    },
                    margin: 0,
                    labelWidth: 80,
                    listeners: {
                        collapse: function() {
                            var value = this.getValue();
                            if (Ext.isObject(value) && value.items.length === 0) {
                                this.setValue('');
                            }
                        },
                        change: function(comp, value) {
                            comp.up('#maintab').onParamChange(comp.name, value);
                        }
                    }
                }]
            },{
                xtype: 'displayfield',
                platform: ['cloudstack', 'idcf'],
                fieldLabel: 'Cloud location',
                labelWidth: 110,
                margin: 0,
                name: 'cloudstack.cloud_location'
            },{
                xtype: 'combobox',
                fieldLabel: Scalr.flags['betaMode'] ? 'Avail zone' : 'Cloud location',
                platform: ['gce'],
                flex: 1,
                multiSelect: true,
                name: 'gce.cloud-location',
                valueField: 'name',
                displayField: 'description',
                listConfig: {
                    cls: 'x-boundlist-with-icon',
                    tpl : '<tpl for=".">'+
                            '<tpl if="state != &quot;UP&quot;">'+
                                '<div class="x-boundlist-item x-boundlist-item-disabled" title="Zone is offline for maintenance"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{description}&nbsp;<span class="warning"></span></div>'+
                            '<tpl else>'+
                                '<div class="x-boundlist-item"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{description}</div>'+
                            '</tpl>'+
                          '</tpl>'
                },
                store: {
                    fields: [ 'name', {name: 'description', convert: function(v, record){return record.data.description || record.data.name;}}, 'state' ],
                    proxy: 'object',
                    sorters: ['name']
                },
                editable: false,
                queryMode: 'local',
                margin: 0,
                labelWidth: 110,
                listeners: {
                    beforeselect: function(comp, record, index) {
                        if (comp.isExpanded) {
                            return record.get('state') === 'UP';
                        }
                    },
                    beforedeselect: function(comp, record, index) {
                        if (comp.isExpanded) {
                            if (comp.getValue().length < 2) {
                                Scalr.message.InfoTip('At least one zone must be selected!', comp.inputEl, {anchor: 'bottom'});
                                return false;
                            } else {
                                return true;
                            }
                        }
                    },
                    change: function(comp, value) {
                        var tab = comp.up('#maintab'), locations = [],
                            record = tab.currentRole,
                            settings = record.get('settings', true);

                        tab.onParamChange(comp.name, value);
                        tab.currentRole.set('cloud_location', value.length === 1 ? value[0] : 'x-scalr-custom');
                        //gce location beta
                        if (!settings['gce.region']) {
                            comp.store.data.each(function(record){locations.push(record.get('name'))});
                            tab.down('#cloud_location').selectLocation(tab.currentRole.get('platform'), value, locations);
                        }
                    }
                }
            }]
       }]
    },{
        xtype: 'farmrolecostmetering',
        itemId: 'costmetering',
        layout: 'anchor',
        flex: 1.8,
        minWidth: 320
    }]
});


Ext.define('Scalr.ui.FormVpcNetworkInterfaceField', {
	extend: 'Ext.form.field.ComboBox',
	alias: 'widget.vpcnetworkinterfacefield',

    fieldLabel: 'Network interface',
    editable: false,

    queryCaching: false,
    clearDataBeforeQuery: true,

    plugins: [{
        ptype: 'comboaddnew',
        pluginId: 'comboaddnew',
        url: '/tools/aws/vpc/createNetworkInterface'
    }],

    store: {
        fields: ['id', 'publicIp', {name: 'description', convert: function(v, record){
            return record.data.id + (record.data.publicIp ? ' (' + record.data.publicIp + ')' : '');
        }} ],
        proxy: {
            type: 'cachedrequest',
            url: '/tools/aws/vpc/xListNetworkInterfaces'
        }
    },
    valueField: 'id',
    displayField: 'description',
    updateEmptyText: function(type){
        this.emptyText =  type ? 'Please select network interface' : 'No Elastic Network Interfaces available, click (+) to create one';
        this.applyEmptyText();
    },
    listeners: {
        boxready: function() {
            var me = this;
            me.store.on('load', function(store, data){
                me.updateEmptyText(data.length > 0);
            });
        },
        addnew: function(item) {
            Scalr.CachedRequestManager.get().setExpired({
                url: '/tools/aws/vpc/xListNetworkInterfaces',
                params: this.store.proxy.params
            });
        },
        beforequery: function() {
            var subnetIdField = this.prev('[name="aws.vpc_subnet_id"]'),
                value = subnetIdField.getValue();
            if (!value || !value.length) {
                this.collapse();
                Scalr.message.InfoTip('Select subnet first.', subnetIdField.bodyEl, {anchor: 'bottom'});
                return false;
            }
        }
    }
});

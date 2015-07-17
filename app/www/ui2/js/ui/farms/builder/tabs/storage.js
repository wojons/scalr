Ext.define('Scalr.ui.FarmRoleEditorTab.Storage', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Storage',
    itemId: 'storage',
    layout: {
        type: 'hbox',
        align: 'stretch'
    },

    tabData: null,

    onRoleUpdate: function(record, name, value, oldValue) {
        if (!this.isEnabled(record) || !this.isVisible()) return;
        var me = this;
        if (name.join('.') === 'settings.aws.instance_type') {
            record.loadEBSEncryptionSupport(function(encryptionSupported){
                var field = me.down('[name="ebs.encrypted"]');
                if (field) {
                    field.encryptionSupported = encryptionSupported;
                    field.setReadOnly(!encryptionSupported);
                }
            });
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
                        record.loadEBSEncryptionSupport(function(encryptionSupported){
                            var field = me.down('[name="ebs.encrypted"]'),
                                kmsKeys = ((Scalr.getGovernance('ec2', 'aws.kms_keys') || {})[cloudLocation] || {})['keys'];
                            field.encryptionSupported = encryptionSupported;
                            field.setReadOnly(!encryptionSupported);

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
        var settingsCt = this.down('#settings'),
            settings = record.get('settings', true),
            platform = record.get('platform'),
            storages = record.get('storages', true) || {},
            os = Scalr.utils.getOsById(record.get('osId')) || {},
            rootStorage,
            field,
            volumeTypes,
            data = [];

        field = this.down('#configuration');
        if (this.tabData['rootDeviceConfig']) {
            if (settings['base.root_device_config']) {
                rootStorage = Ext.decode(settings['base.root_device_config']);
            } else {
                rootStorage = Ext.applyIf({
                    mount: true,
                    rebuild: true,
                    reUse: false,
                    isRootDevice: true
                }, this.tabData['rootDeviceConfig']);
            }
        }
        field.store.loadData(Ext.Array.merge(storages['configs'] || [], rootStorage ? [rootStorage] : []));
        field.devices = storages['devices'] || [];

        this.down('[name="ebs.snapshot"]').store.getProxy().params = {cloudLocation: record.get('cloud_location')};

        // Storage engine
        if (platform == 'ec2' || platform == 'eucalyptus') {
            data = [{
                name: 'ebs', description: 'EBS volume'
            }, {
                name: 'raid.ebs', description: 'RAID array (on EBS)'
            }];
        } else if (Scalr.isCloudstack(platform)) {
            data = [{
                name: 'csvol', description: 'CS volume'
            }, {
                name: 'raid.csvol', description: 'RAID array (on CS volumes)'
            }];
            this.down('[name="csvol.disk_offering_id"]').store.load({data: this.tabData['diskOfferings'] || []});
            this.down('[name="csvol.snapshot_id"]').store.getProxy().params = {
                platform: record.get('platform'),
                cloudLocation: record.get('cloud_location')
            };
        } else if (platform === 'gce') {
            data = [{
                name: 'gce_persistent', description: 'Persistent disk'
            }];
        } else if (Scalr.isOpenstack(platform)) {
            data = [{
                name: 'cinder', description: 'Persistent disk'
            }, {
                name: 'raid.cinder', description: 'RAID array (on Persistent disks)'
            }];
            field = this.down('[name="cinder.volume_type"]');
            volumeTypes = this.tabData['volume_types'] || [];
            field.setVisible(volumeTypes.length > 0).setDisabled(!volumeTypes.length);
            field.store.load({data: volumeTypes});
        }

        //storage engine
        field = settingsCt.down('[name="type"]');
        field.store.loadData(data);
        field.setReadOnly(os.family === 'windows', false);

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
        }
        field = settingsCt.down('[name="fs"]');
        field.store.loadData(data);
        field.setDisabled(os.family === 'windows');
        field.setVisible(os.family !== 'windows');

        this.down('#editor').hide();
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
            record.set('settings', settings)
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

    __items: [{
        xtype: 'container',
        maxWidth: 900,
        minWidth: 460,
        flex: 1,
        cls: 'x-panel-column-left x-panel-column-left-with-tabs',
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        items: [{
            xtype: 'component',
            cls: 'x-fieldset-subheader',
            margin: 12,
            html: 'Storage'
        },{
            xtype: 'grid',
            itemId: 'configuration',
            multiSelect: true,
            padding: '0 12 12',
            features: {
                ftype: 'addbutton',
                text: 'Add storage',
                handler: function() {
                    var currentRole = this.grid.up('#storage').currentRole,
                        form = this.grid.up('#storage').down('#editor'),
                        osFamily = Scalr.utils.getOsById(currentRole.get('osId'), 'family'),
                        storageDefaults = { reUse: 1};

                    if (osFamily === 'windows') {
                        if (currentRole.get('platform') === 'ec2' || currentRole.get('platform') === 'eucalyptus')
                            storageDefaults['type'] = 'ebs';
                        else if (Scalr.isOpenstack(currentRole.get('platform')))
                            storageDefaults['type'] = 'cinder';
                        else if (Scalr.isCloudstack(currentRole.get('platform')))
                            storageDefaults['type'] = 'csvol';
                        else if (currentRole.get('platform') === 'gce')
                            storageDefaults['type'] = 'gce_persistent';
                    }


                    this.grid.clearSelectedRecord();
                    form.loadRecord(this.grid.store.createModel(storageDefaults));
                    if (!form.hasInvalidField()) {
                        form.onLiveUpdate();//add record to store if form is valid
                    }
                }
            },
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
                model: Scalr.getModel({fields: [ 'id', 'type', 'fs', 'settings', 'mount', 'mountPoint', 'reUse', {name: 'status', defaultValue: ''}, 'rebuild', {name: 'isRootDevice', defaultValue: false}, {name: 'readOnly', defaultValue: false} ]}),
                sorters: [{
                    property: 'isRootDevice',
                    direction: 'DESC'
                }]
            },
            columns: [
                { header: 'Mount point', flex: 2, sortable: false, dataIndex: 'mountPoint', xtype: 'templatecolumn', tpl:
                    '<tpl if="mountPoint">{mountPoint}<tpl else>&mdash;</tpl>'
                },
                { header: 'Type', flex: 2, sortable: false, dataIndex: 'type', xtype: 'templatecolumn', tpl:
                    new Ext.XTemplate('{[this.name(values.type)]}', {
                        name: function(type) {
                            var l = {
                                'ebs': 'EBS volume',
                                'csvol': 'CS volume',
                                'cinder': 'Persistent disk',
                                'gce_persistent': 'Persistent disk',
                                'raid.ebs': 'RAID array (on EBS)',
                                'raid.csvol': 'RAID array (on CS volumes)',
                                'raid.cinder': 'RAID array (on PDs)',
                                'instance-store': 'Ephemeral device'
                            };

                            return type ? (l[type] || type) : '&mdash;'
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
                            } else if (v.type === 'instance-store') {
                                s = v['settings']['size'];
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
                { header: 'Re-use', width: 60, xtype: 'templatecolumn', sortable: false, align: 'center', tpl:
                    '<tpl if="reUse"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"><tpl else>&mdash;</tpl>'
                },
                {
                    xtype: 'templatecolumn',
                    tpl: '<tpl if="status!=\'Pending delete\'&&!isRootDevice"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-delete" title="Delete storage" /></tpl>',
                    width: 42,
                    sortable: false,
                    resizable: false,
                    dataIndex: 'id',
                    align:'left'
            }],

            listeners: {
                viewready: function() {
                    var me = this;
                    me.on('selectedrecordchange', function(record) {
                        var dev = me.next();
                        dev.hide();
                        dev.store.removeAll();
                        if (record && !record.get('isRootDevice')) {
                            var id = record.get('id');
                            if (Ext.isArray(me.devices[id])) {
                                dev.store.add(me.devices[id])
                                dev.show();
                            }
                        }
                    });
                },
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('img.x-grid-icon-delete')) {
                        if (!record.get('id')) {
                            view.getStore().remove(record);
                        } else {
                            record.set('status', 'Pending delete');
                            view.up().clearSelectedRecord();
                        }
                        return false;
                    }
                }
            }
        },{
            xtype: 'grid',
            padding: '0 12 12',
            flex: 1,
            itemId: 'devices',
            hidden: true,
            disableSelection: true,
            trackMouseOver: false,
            viewConfig: {
                deferEmptyText: false,
                emptyText: 'Selected storage is not in use.'
            },
            store: {
                proxy: 'object',
                fields: [ 'serverIndex', 'serverId', 'serverInstanceId', 'farmRoleId', 'storageId', 'storageConfigId', 'placement' ]
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
            },
            dockedItems: [{
                xtype: 'toolbar',
                ui: 'inline',
                dock: 'top',
                padding: 0,
                items: [{
                    xtype: 'label',
                    cls: 'x-fieldset-subheader',
                    html: 'Storage usage'
                }]
            }]

        }]
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
                    this.prev().setVisible(readOnly);
                    return (record.get('status') === 'Pending create' || record.get('status') == '') && !readOnly;
                },
                loadrecord: function(record) {
                    var form = this.getForm();
                    form.setValues(record.get('settings'));
                    form.clearInvalid();
                    this.toggleReadOnly(record);
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
                    osFamily = Scalr.utils.getOsById(this.up('#storage').currentRole.get('osId'), 'family'),
                    field;
                if (osFamily !== 'windows') {
                    this.down('[name="type"]').setReadOnly(isRootDevice);
                    field = this.down('[name="fs"]');
                    field.setDisabled(isRootDevice);
                    field.setVisible(!isRootDevice);
                }
                this.down('#mountCt').setVisible(osFamily !== 'windows' || isRootDevice);

                this.down('[name="mount"]').setReadOnly(isRootDevice);
                this.down('[name="mountPoint"]').setReadOnly(isRootDevice);
                this.down('[name="reUse"]').setVisible(!isRootDevice);
                this.down('[name="rebuild"]').setVisible(!isRootDevice);
                this.down('[name="ebs.snapshot"]').setReadOnly(isRootDevice);
                if (isRootDevice) {
                    this.down('[name="ebs.encrypted"]').hide();
                }
            },
            isSettingsField: function(name) {
                return name.indexOf('ebs') === 0 || name.indexOf('raid') === 0 || name.indexOf('csvol') === 0 || name.indexOf('cinder') === 0 || name.indexOf('gce_persistent') === 0;
            },
            updateSettings: function() {
                var me = this,
                    form = me.getForm(),
                    settings = {};
                Ext.Object.each(form.getFieldValues(), function(name, value){
                    if (me.isSettingsField(name)) {
                        settings[name] = value;
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
                        conf.store.add(record);
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
                    labelWidth: 110,
                    anchor: '100%',
                    maxWidth: 480
                },
                items: [{
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
                    allowBlank: false,
                    emptyText: 'Please select storage engine',
                    listeners: {
                        change: function(field, value) {
                            var editor = this.up('#editor'),
                                ebs = editor.down('#ebs_settings'),
                                ebsSnapshots = editor.down('[name="ebs.snapshot"]'),
                                ebsEncrypted = editor.down('[name="ebs.encrypted"]'),
                                raid = editor.down('#raid_settings'),
                                csvol = editor.down('#csvol_settings'),
                                cinder = editor.down('#cinder_settings'),
                                gce = editor.down('#gce_settings'),
                                platform = editor.up('#storage').currentRole.get('platform'),
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
                        }
                    }
                },{
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
                    allowBlank: false,
                    listeners: {
                        beforeselect: function(comp, record) {
                            var typeField = this.prev('[name="type"]'),
                                type = typeField.getValue();
                            if (!Scalr.flags['betaMode'] && Ext.isString(type) && type.indexOf('raid') !== -1 && record.get('fs') === 'xfs') {
                                Scalr.message.InfoTip('Xfs is not available on raid.', this.inputEl, {anchor: 'bottom'});
                                return false;
                            }
                        }
                    }
                }, {
                    xtype: 'fieldcontainer',
                    itemId: 'mountCt',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    items: [{
                        xtype: 'checkbox',
                        boxLabel: 'Automatically mount device to',
                        name: 'mount',
                        inputValue: 1,
                        handler: function (field, checked) {
                            if (checked)
                                this.next('[name="mountPoint"]').enable();
                            else
                                this.next('[name="mountPoint"]').setValue('').disable();
                        }
                    }, {
                        xtype: 'textfield',
                        margin: '0 0 0 5',
                        disabled: true,
                        validator: function(value) {
                            var valid = true;
                            if (this.prev().getValue() && Ext.isEmpty(value)) {
                                valid = 'Field is required';
                            }
                            return valid;
                        },
                        flex: 1,
                        name: 'mountPoint'
                    }]
                }, {
                    xtype: 'checkbox',
                    name: 'reUse',
                    boxLabel: 'Reuse this storage if the instance is replaced',
                    plugins: {
                        ptype: 'fieldicons',
                        align: 'right',
                        icons: [{id: 'info', tooltip: 'If an instance is terminated, reattach this same volume to the replacement server'}]
                    }
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
                xtype:'fieldset',
                itemId: 'raid_settings',
                title: 'RAID settings',
                hidden: true,
                maskOnDisable: true,
                defaults: {
                    labelWidth: 110,
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
                    labelWidth: 110,
                    anchor: '100%',
                    maxWidth: 480
                },
                items: [{
                    xtype: 'textfield',
                    name: 'ebs.size',
                    fieldLabel: 'Size (GB)',
                    allowBlank: false,
                    maxWidth: 170,
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
                            var record = field.findRecord(field.valueField, field.getValue());
                            if (record && (parseInt(value) < record.get('size')))
                                return 'Value must be bigger than snapshot size: ' + record.get('size') + 'GB';
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
                    xtype: 'combo',
                    fieldLabel: 'Snapshot',
                    name: 'ebs.snapshot',
                    emptyText: 'Create an empty volume',
                    valueField: 'snapshotId',
                    displayField: 'snapshotId',
                    anchor: '100%',
                    matchFieldWidth: true,

                    queryCaching: false,
                    minChars: 0,
                    queryDelay: 10,
                    autoSearch: false,
                    ebsEncryptionMessage: 'Encrypted EBS storages are not supported by current instance type.',
                    validator: function(value) {
                        var record;
                        record = this.findRecordByValue(value);
                        if (record && record.get('encrypted') && !this.next('[name="ebs.encrypted"]').encryptionSupported) {
                            return this.ebsEncryptionMessage;
                        }
                        return true;
                    },
                    store: {
                        fields: [ 'snapshotId', 'createdDate', 'size', 'volumeId', 'description', 'encrypted' ],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'farmDesigner',
                            url: '/platforms/ec2/xGetSnapshots',
                            filterFields: ['snapshotId', 'volumeId', 'description'],//fliterFn
                            prependData: [{}]//Create an empty volume
                        }
                    },
                    listConfig: {
                        cls: 'x-boundlist-alt',
                        tpl:
                            '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                '<tpl if="snapshotId">' +
                                    '<div><span class="x-semibold">{snapshotId} ({size}GB)</span> {[values.encrypted?\'<i>encrypted</i>\':\'\']}</div>' +
                                    '<div>created <span class="x-semibold">{createdDate}</span> on <span class="x-semibold">{volumeId}</span></div>' +
                                    '<div style="font-style: italic; font-size: 11px;">{description}</div>' +
                                '<tpl else><div style="line-height: 26px;">Create an empty volume</div></tpl>' +
                            '</div></tpl>'
                    },
                    listeners: {
                        blur: function(comp) {
                            if (comp.lastQuery && !comp.disabled && !comp.readOnly && !comp.findRecordByValue(comp.getValue())) {
                                comp.reset();
                            }
                        },
                        beforeselect: function(comp, record) {
                            if (record.get('encrypted')) {
                                if (!comp.next('[name="ebs.encrypted"]').encryptionSupported) {
                                    Scalr.message.InfoTip(this.ebsEncryptionMessage, comp.inputEl, {anchor: 'bottom'});
                                    return false;
                                }
                            }
                        },
                        change: function(comp, value){
                            var encryptionField, record, encrypted;
                            encryptionField = comp.next('[name="ebs.encrypted"]');
                            if (encryptionField.encryptionSupported) {
                                record = comp.findRecordByValue(value);
                                if (record && record.get('snapshotId')) {
                                    encryptionField.setValue(record.get('encrypted') ? 1 : 0);
                                    encryptionField.setReadOnly(true);
                                } else {
                                    encryptionField.setReadOnly(false);
                                }
                            }
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
                                this.updateIconTooltip('question', this.encryptionSupported ? 'EBS encryption is set according to snapshot settings' : 'EBS encryption is not supported by selected instance type')
                            }
                        },
                        change: function(comp, value) {
                            comp.next('[name="ebs.kms_key_id"]').setVisible(value == 1).setValue('');
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
                    labelWidth: 110,
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
                        labelWidth: 110,
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
                    labelWidth: 110,
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
                        labelWidth: 110,
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
                    labelWidth: 110,
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
            }]
        }]
    }]
});
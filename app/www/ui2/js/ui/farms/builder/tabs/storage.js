Scalr.regPage('Scalr.ui.farms.builder.tabs.storage', function (moduleTabParams) {
    var iopsMin = 100, 
        iopsMax = 4000, 
        integerRe = new RegExp('[0123456789]', 'i'), 
        maxEbsStorageSize = 1000;
        
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Storage',
		itemId: 'storage',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        
        tabData: null,
        
        cls: 'scalr-ui-farmbuilder-roleedit-tab',

        isEnabled: function (record) {
			return true;
		},

        onRoleUpdate: function(record, name, value, oldValue) {
            if (!this.isActive(record)) return;
            var me = this;
            if (Scalr.flags['betaMode'] && name.join('.') === 'settings.aws.instance_type') {
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
                        me.tabData = data;
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
                        me.tabData = data;
                        status ? handler() : me.deactivateTab();
                    },
                    me
                );
            } else if (platform === 'ec2' && Scalr.flags['betaMode']) {
                record.loadEBSEncryptionSupport(function(encryptionSupported){
                    var field = me.down('[name="ebs.encrypted"]');
                    field.encryptionSupported = encryptionSupported;
                    field.setReadOnly(!encryptionSupported);
                    handler();
                });
            } else {
                handler();
            }
        },
        
		showTab: function (record) {
			var settings = this.down('#settings'),
                platform = record.get('platform'),
                storages = record.get('storages', true),
                osFamily = record.get('os_family'),
                field,
                volumeTypes,
                data = [];

            field = this.down('#configuration');
			field.store.loadData(storages['configs'] || []);
			field.devices = storages['devices'] || [];

			this.down('[name="ebs.snapshot"]').store.getProxy().params = {cloudLocation: record.get('cloud_location')};

			// Storage engine
			if (platform == 'ec2' || platform == 'eucalyptus') {
				data = [{
					name: 'ebs', description: 'Single EBS volume'
				}, {
					name: 'raid.ebs', description: 'RAID array (on EBS)'
				}];
			} else if (Scalr.isCloudstack(platform)) {
				data = [{
					name: 'csvol', description: 'Single CS volume'
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
            field = settings.down('[name="type"]');
			field.store.loadData(data);
            field.setReadOnly(record.get('os_family') === 'windows', false);

			// Storage filesystem
			var data = [];

            if (osFamily !== 'windows') {
                data.push({ fs: 'ext3', description: 'Ext3' });
                if ((osFamily == 'centos' && record.get('arch') == 'x86_64') ||
                    (osFamily == 'ubuntu' && Ext.Array.contains(['10.04', '12.04', '14.04'], record.get('os_generation')))
                    ) {
                    if (moduleTabParams['featureMFS']) {
                        data.push({ fs: 'ext4', description: 'Ext4'});
                        data.push({ fs: 'xfs', description: 'XFS'});
                    } else {
                        data.push({ fs: 'ext4', description: 'Ext4 (Not available for your pricing plan)'});
                        data.push({ fs: 'xfs', description: 'XFS (Not available for your pricing plan)'});
                    }
                }
            }
            field = settings.down('[name="fs"]');
			field.store.loadData(data);
            field.setDisabled(osFamily === 'windows');
            field.setVisible(osFamily !== 'windows');

            this.down('#mountCt').setVisible(osFamily !== 'windows');
            
			this.down('#editor').hide();
		},
		
		hideTab: function (record) {
			var grid = this.down('#configuration'),
                selModel = grid.getSelectionModel();

            selModel.setLastFocused(null);
            selModel.deselectAll();

			var c = record.get('storages') || {};
			c['configs'] = this.getStorages();
			record.set('storages', c);
		},

        getStorages: function() {
            var storages = [],
                grid = this.down('#configuration');
			grid.store.each(function(record) {
				storages.push(record.getData());
			});
            return storages;
        },
		
		items: [{
            xtype: 'container',
            maxWidth: 900,
            minWidth: 460,
            flex: 1,
            cls: 'x-panel-column-left',
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
                cls: 'x-grid-shadow',
                multiSelect: true,
                padding: '0 9 9',
                features: {
                    ftype: 'addbutton',
                    text: 'Add storage',
                    handler: function(view) {
                        var currentRole = view.up('#storage').currentRole,
                            selModel = view.getSelectionModel(),
                            storageDefaults = { reUse: 1 };

                            if (currentRole.get('os_family') === 'windows') {
								if (currentRole.get('platform') === 'ec2' || currentRole.get('platform') === 'eucalyptus')
                                    storageDefaults['type'] = 'ebs';
						        else if (Scalr.isOpenstack(currentRole.get('platform')))
								    storageDefaults['type'] = 'cinder';
                            }
                            
														
                            selModel.setLastFocused(null);
                            selModel.deselectAll();
                            view.up('#storage').down('#editor').loadRecord(view.store.createModel(storageDefaults), currentRole);
                    }
                },
                plugins: [{
                    ptype: 'focusedrowpointer',
                    thresholdOffset: 26,
                }],
                viewConfig: {
                    getRowClass: function(record) {
                        if (record.get('status') === 'Pending delete')
                            return 'x-grid-row-strikeout';
                    }
                },
                store: {
                    proxy: 'object',
                    fields: [ 'id', 'type', 'fs', {name: 'settings', defaultValue: {fs: 'ext3'}}, 'mount', 'mountPoint', 'reUse', 'status', 'rebuild' ]
                },
                columns: [
                    { header: 'Type', flex: 2, sortable: true, dataIndex: 'type', xtype: 'templatecolumn', tpl:
                        new Ext.XTemplate('{[this.name(values.type)]}', {
                            name: function(type) {
                                var l = {
                                    'ebs': 'Single EBS volume',
                                    'csvol': 'Single CS volume',
									'cinder': 'Single persistent disk',
									'gce_persistent': 'Persistent disk',
                                    'raid.ebs': 'RAID array (on EBS)',
                                    'raid.csvol': 'RAID array (on CS volumes)',
									'raid.cinder': 'RAID array (on PDs)'
                                };

                                return l[type] || type;
                            }
                        })
                    },
                    { header: 'FS', flex: 1, sortable: true, dataIndex: 'fs', xtype: 'templatecolumn', tpl:
                        new Ext.XTemplate('{[this.name(values.fs)]}', {
                            name: function(type) {
                                var l = {
                                    'ext3': 'Ext3',
									'ext4': 'Ext4',
									'xfs': 'XFS'
                                };

                                return l[type] || type;
                            }
                        })
                    },
                    { header: 'Re-use', width: 60, xtype: 'templatecolumn', sortable: false, align: 'center', tpl:
                        '<tpl if="reUse"><img src="/ui2/images/icons/true.png"><tpl else><img src="/ui2/images/icons/false.png"></tpl>'
                    },
                    { header: 'Mount point', flex: 2, sortable: true, dataIndex: 'mountPoint', xtype: 'templatecolumn', tpl:
                        '<tpl if="mountPoint">{mountPoint}<tpl else><img src="/ui2/images/icons/false.png"></tpl>'
                    },
                    { header: 'Description', flex: 3, sortable: false, dataIndex: 'type', xtype: 'templatecolumn', tpl:
                        new Ext.XTemplate(
                            '{[this.getDescription(values)]}', {
                            getDescription: function(v) {
                                var result = [],
                                    s;
                                if (Ext.Array.contains(['raid.ebs', 'raid.csvol', 'raid.cinder'], v.type)) {
                                    result.push('RAID ' + v['settings']['raid.level'] + ' on ' + v['settings']['raid.volumes_count'] + ' x');
                                }

                                if (Ext.Array.contains(['raid.ebs', 'ebs'], v.type)) {
                                    s = v['settings']['ebs.size'] + 'GB EBS volume';
                                    if (v['settings']['ebs.type'] == 'io1') {
                                        s += ' (' + v['settings']['ebs.iops'] + ' iops)';
                                    }
                                    
                                    if (v['settings']['ebs.type'] == 'gp2') {
                                        s += ' (SSD)';
                                    }
                                    if (v['settings']['ebs.encrypted'] == '1') {
                                        s += ' encrypted';
                                    }
                                } else if (Ext.Array.contains(['raid.csvol', 'csvol'], v.type)) {
                                    s = v['settings']['csvol.size'] + 'GB CS volume';
                                } else if (Ext.Array.contains(['raid.cinder', 'cinder'], v.type)) {
                                    s = v['settings']['cinder.size'] + 'GB Persistent disk';
                                } else if (v.type === 'gce_persistent') {
                                    s = v['settings']['gce_persistent.size'] + 'GB Persistent disk';
                                }

                                result.push(s);
                                return Ext.String.htmlEncode(result.join(' '));
                            }
                        })
                    },
                    {
                        xtype: 'templatecolumn',
                        tpl: '<tpl if="status!=\'Pending delete\'"><img style="cursor:pointer" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Delete scaling metric" src="'+Ext.BLANK_IMAGE_URL+'"/></tpl>',
                        width: 42,
                        sortable: false,
                        resizable: false,
                        dataIndex: 'id',
                        align:'left'
                }],

                listeners: {
                    viewready: function() {
                        var me = this;
                        me.getSelectionModel().on('focuschange', function(gridSelModel) {
                            var dev = me.next();
                            dev.hide();
                            //todo: 4.2 check
                            if (dev.store.getCount() > 0) {
                                dev.store.removeAll();
                            }
                            if (gridSelModel.lastFocused) {
                                var id = gridSelModel.lastFocused.get('id');
                                if (me.devices[id]) {
                                    for (var i in me.devices[id])
                                        dev.store.add(me.devices[id][i]);
                                    dev.show();
                                }
                            }
                        });
                    },
                    itemclick: function (view, record, item, index, e) {
                        if (e.getTarget('img.x-icon-action-delete')) {
                            var selModel = view.getSelectionModel();
                            if (record === selModel.getLastFocused()) {
                                selModel.deselectAll();
                                selModel.setLastFocused(null);
                            }
                            if (!record.get('id')) {
                                view.getStore().remove(record);
                            } else {
                                record.set('status', 'Pending delete');
                            }
                            return false;
                        }
                    }
                }
            },{
                xtype: 'grid',
                flex: 1,
                padding: 9,
                itemId: 'devices',
                cls: 'x-grid-shadow x-grid-no-selection',
                margin: '24 0 0 0',
                hidden: true,
                viewConfig: {
                    disableSelection: true,
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
                    { header: 'Config', width: 80, sortable: false, dataIndex: 'config', xtype: 'templatecolumn', tpl:
                        '<a href="#" class="view">View</a>'
                    }, {
                        xtype: 'templatecolumn', width: 42, sortable: false, resizable: false, dataIndex: 'id', align: 'center', tpl: [
                            '<tpl if="serverId==\'\'">',
                                '<img style="cursor:pointer" width="15" height="15" class="x-icon-action x-icon-action-delete" ',
                                'data-qtip="Delete volume" src="'+Ext.BLANK_IMAGE_URL+'" />',
                            '<tpl else>',
                                '<img width="15" height="15" class="x-icon-action x-icon-action-delete-disabled" ',
                                'data-qtip="You can delete volume only if server is not running" src="'+Ext.BLANK_IMAGE_URL+'" />',
                            '</tpl>'
                        ],
                        hidden: !Scalr.flags['betaMode']
                    }
                ],

                listeners: {
                    itemclick: function (view, record, item, index, e) {
                        if (e.getTarget('a.view')) {
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

                        } else if (e.getTarget('img.x-icon-action-delete')) {
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
                    ui: 'simple',
                    dock: 'top',
                    items: [{
                        xtype: 'label',
                        cls: 'x-fieldset-subheader',
                        html: 'Storage usage',
                        margin: 0
                    }]
                }]
                
            }]
        },{
            xtype: 'container',
            layout: 'fit',
            flex: .7,
            margin: 0,
            items: {
                xtype: 'form',
                itemId: 'editor',
                margin: 0,
                overflowY: 'auto',
                suspendLiveUpdate: 0,
                listeners: {
                    render: function() {
                        var me = this,
                            grid = me.up('#storage').down('#configuration'),
                            form = me.getForm();
                        grid.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused) {
                            if (oldFocused != newFocused) {
                                if (newFocused && (newFocused.get('status') == 'Pending create' || newFocused.get('status') == '')) {
                                    if (newFocused !== form.getRecord()) {
                                        me.loadRecord(newFocused);
                                    }
                                } else {
                                    me.deselectRecord();
                                }
                            }
                        });
                        form.getFields().each(function(){
                            this.on('change', me.onLiveUpdate, me)
                        });
                    },
                    beforeloadrecord: function() {
                        var form = this.getForm();
                        this.suspendLiveUpdate++;
                        form.reset(true);
                    },
                    loadrecord: function(record) {
                        var form = this.getForm();
                        form.setValues(record.get('settings'));
                        form.clearInvalid();
                        if (!this.isVisible()) {
                            this.setVisible(true);
                        }
                        this.suspendLiveUpdate--;
                    },
                    updaterecord: function(record) {
                        this.updateSettings();
                    }
                },
                deselectRecord: function() {
                    var form = this.getForm();
                    this.setVisible(false);
                    this.suspendLiveUpdate++;
                    form.reset(true);
                    this.suspendLiveUpdate--;

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
                    if (this.suspendLiveUpdate > 0) return;
                    var me = this,
                        form = me.getForm(),
                        record = form.getRecord();
                    
                    if (form.isValid()) {
                        form.updateRecord();
                        var conf = me.up('#storage').down('#configuration');
                        if (!record.store) {
                            conf.store.add(record);
                            conf.getSelectionModel().setLastFocused(record, true);
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
                                
                                ebs[ value == 'ebs' || value == 'raid.ebs' ? 'show' : 'hide' ]();
                                ebs[ value == 'ebs' || value == 'raid.ebs' ? 'enable' : 'disable' ]();
                                ebsSnapshots[ value == 'ebs' && platform == 'ec2' ? 'show' : 'hide' ]();
                                ebsEncrypted[ value == 'ebs' && platform == 'ec2' && Scalr.flags['betaMode'] ? 'show' : 'hide' ]();
                                editor.down('[name="ebs.type"]').setReadOnly(platform !== 'ec2', false);
                                
								raid[ value == 'raid.ebs' || value == 'raid.csvol' || value == 'raid.cinder' ? 'show' : 'hide' ]();
                                raid[ value == 'raid.ebs' || value == 'raid.csvol' || value == 'raid.cinder' ? 'enable' : 'disable' ]();
                                
								csvol[ value == 'csvol' || value == 'raid.csvol' ? 'show' : 'hide' ]();
                                csvol[ value == 'csvol' || value == 'raid.csvol' ? 'enable' : 'disable' ]();
								
								cinder[ value == 'cinder' || value == 'raid.cinder' ? 'show' : 'hide' ]();
                                cinder[ value == 'cinder' || value == 'raid.cinder' ? 'enable' : 'disable' ]();
								
								gce[ value == 'gce_persistent' ? 'show' : 'hide' ]();
                                gce[ value == 'gce_persistent' ? 'enable' : 'disable' ]();

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
                                if (!Scalr.flags['betaMode'] && type.indexOf('raid') !== -1 && record.get('fs') === 'xfs') {
                                    Scalr.message.InfoTip('Xfs is not available on raid.', this.inputEl, {anchor: 'bottom'});
                                    return false;
                                }
                            }
                        }
                    }, {
                        xtype: 'container',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'checkbox',
                            name: 'reUse',
                            boxLabel: 'Reuse this storage if the instance is replaced'
                        }, {
                            xtype: 'displayinfofield',
                            value: "If an instance is terminated, reattach this same volume to the replacement server",
                            margin: '0 0 0 5'
                        }]
                    }, {
                        xtype: 'container',
                        layout: 'hbox',
                        items: [{
                            xtype: 'checkbox',
                            name: 'rebuild',
                            boxLabel: 'Regenerate this storage if missing volumes are detected in it'
                        }, {
                            xtype: 'displayinfofield',
                            value: "If Scalr detects any missing volumes, regenerate this storage from scratch (based on configuration)",
                            margin: '0 0 0 5'
                        }]
                    }, {
                        xtype: 'container',
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
                    }]
                }, {
                    xtype:'fieldset',
                    itemId: 'raid_settings',
                    title: 'RAID settings',
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
                        validator: function(value) {
                            var form = this.up('#editor'),
                                field = form.down('[name="ebs.snapshot"]');
                            if (! field.isDisabled() && field.getValue()) {
                                var record = field.findRecord(field.valueField, field.getValue());
                                if (record && (parseInt(value) < record.get('size')))
                                    return 'Value must be bigger than snapshot size: ' + record.get('size') + 'GB';
                            }
                            
                            var minValue = 1;
                            if (form.down('[name="ebs.type"]').getValue() === 'io1') {
                                minValue = Math.ceil(form.down('[name="ebs.iops"]').getValue()*1/10);
                            }
                            if (value*1 > maxEbsStorageSize) {
                                return 'Maximum value is ' + maxEbsStorageSize + '.';
                            } else if (value*1 < minValue) {
                                return 'Minimum value is ' + minValue + '.';
                            }
                            
                            return true;
                        }
                    }, {
                        xtype: 'fieldcontainer',
                        layout: 'hbox',
                        fieldLabel: 'EBS type',
                        items: [{
                            xtype: 'combo',
                            store: [['standard', 'Standard EBS (Magnetic)'],['gp2', 'General Purpose (SSD)'], ['io1', 'Provisioned IOPS (' + iopsMin + ' - ' + iopsMax + '): ']],
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
                            maskRe: integerRe,
                            validator: function(value){
                                if (value*1 > iopsMax) {
                                    return 'Maximum value is ' + iopsMax + '.';
                                } else if (value*1 < iopsMin) {
                                    return 'Minimum value is ' + iopsMin + '.';
                                }
                                return true;
                            },
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

                        restoreValueOnBlur: true,
                        queryCaching: false,
                        minChars: 0,
                        queryDelay: 10,
                        autoSearch: false,
                        ebsEncryptionMessage: 'Encrypted EBS storages are not supported by current instance type.',
                        validator: function(value) {
                            var record;
                            if (Scalr.flags['betaMode']) {
                                record = this.findRecordByValue(value);
                                if (record && record.get('encrypted') && !this.next('[name="ebs.encrypted"]').encryptionSupported) {
                                    return this.ebsEncryptionMessage;
                                }
                            }
                            return true;
                        },
                        store: {
                            fields: [ 'snapshotId', 'createdDate', 'size', 'volumeId', 'description', 'encrypted' ],
                            proxy: {
                                type: 'cachedrequest',
                                crscope: 'farmbuilder',
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
                                        '<div><span style="font-weight: bold">{snapshotId} ({size}GB)</span> {[values.encrypted?\'<i>encrypted</i>\':\'\']}</div>' +
                                        '<div>created <span style="font-weight: bold">{createdDate}</span> on <span style="font-weight: bold">{volumeId}</span></div>' +
                                        '<div style="font-style: italic; font-size: 11px;">{description}</div>' +
                                    '<tpl else><div style="line-height: 26px;">Create an empty volume</div></tpl>' +
                                '</div></tpl>'
                        },
                        listeners: {
                            beforeselect: function(comp, record) {
                                if (Scalr.flags['betaMode'] && record.get('encrypted')) {
                                    if (!comp.next('[name="ebs.encrypted"]').encryptionSupported) {
                                        Scalr.message.InfoTip(this.ebsEncryptionMessage, comp.inputEl, {anchor: 'bottom'});
                                        return false;
                                    }
                                }
                            },
                            change: function(comp, value){
                                var encryptionField, record, encrypted;
                                if (Scalr.flags['betaMode']) {
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
                        }
                    }, {
                        xtype: 'checkbox',
                        name: 'ebs.encrypted',
                        boxLabel: 'Enable EBS encryption',
                        encryptionSupported: false,
                        hidden: !Scalr.flags['betaMode'],
                        defaults: {
                            width: 90
                        },
                        value: '0',
                        icons: {
                            question: true
                        },
                        listeners: {
                            writeablechange: function(comp, readOnly) {
                                this.toggleIcon('question', readOnly);
                                if (readOnly) {
                                    this.updateIconTooltip('question', this.encryptionSupported ? 'EBS encryption is set according to snapshot settings' : 'EBS encryption is not supported by selected instance type')
                                }
                            }
                        }
                    }]
                }, {
                    xtype:'fieldset',
                    itemId: 'csvol_settings',
                    title: 'Volume settings',
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
                        xtype: 'container',
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
                                crscope: 'farmbuilder',
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
                                    '<div style="font-weight: bold">{snapshotId} ({volumeType})</div>' +
                                    '<div>created <span style="font-weight: bold">{createdAt}</span> on <span style="font-weight: bold">{volumeId}</span></div>' +
                                    '<tpl else><div style="line-height: 26px;">Create an empty volume</div></tpl>' +
                                    '</div></tpl>'
                        }
                    }]
                }, {
                    xtype:'fieldset',
                    itemId: 'cinder_settings',
                    title: 'Volume settings',
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
                    defaults: {
                        labelWidth: 100,
                        width: 300
                    },
                    items: [{
                        xtype: 'container',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'textfield',
                            fieldLabel: 'Size',
                            labelWidth: 50,
                            maxWidth: 110,
                            name: 'gce_persistent.size',
                            allowBlank: false
                        },{
                            xtype: 'label',
                            text: 'GB',
                            margin: '0 0 0 6'
                        }]
                    }]
                }]
            }
        }]
	});
});
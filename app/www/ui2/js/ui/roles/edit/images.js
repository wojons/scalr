Ext.define('Scalr.ui.RoleDesignerTabImages', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditimages',
	layout: {
		type: 'hbox',
		align: 'stretch'
	},
    items: [{
        xtype: 'grid',
        flex: 1,
		maxWidth: 800,
        minWidth: 440,
        cls: 'x-grid-shadow x-panel-column-left',
        store: {
            fields: [ 'platform', 'location', 'image_id', 'architecture', 'errors', 'isEmpty'],
            proxy: 'object'
        },
        plugins: [{
            ptype: 'focusedrowpointer',
            thresholdOffset: 26
        }],

        viewConfig: {
            getRowClass: function(record) {
                return record.get('errors') ? 'x-grid-row-red' : '';
            },
            deferEmptyText: false,
            emptyText: 'No images found',
            listeners: {
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('img.x-icon-action-delete')) {
                        var selModel = view.getSelectionModel();
                        if (record === selModel.getLastFocused()) {
                            selModel.deselectAll();
                            selModel.setLastFocused(null);
                        }
                        view.store.remove(record);
                        Ext.Array.include(view.up('roleeditimages').removedImages, record.get('image_id'));
                        return false;
                    }
                }
            }
        },
        listeners: {
            viewready: function() {
                var me = this;
                me.form = me.up('roleeditimages').down('form');
                me.down('#imagesLiveSearch').store = me.store;
                if (! Scalr.flags['betaMode'])
                    me.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                        if (newFocused) {
                            if (newFocused !== me.form.getRecord()) {
                                me.form.loadRecord(newFocused);
                            }
                        } else {
                            me.form.deselectRecord();
                        }
                        if (oldFocused && oldFocused !== newFocused && oldFocused.get('isEmpty')) {
                            me.store.remove(oldFocused);
                        }
                    });
            }
        },
        columns: [
			{ header: "Cloud location", flex: 1, dataIndex: 'platform', sortable: true, renderer:
                function(value, meta, record) {
                    var platform = record.get('platform'),
                        location = record.get('location'),
                        res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" title="' + Scalr.utils.getPlatformName(platform) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                    if (platform === 'gce' || platform === 'ecs') {
                        res += 'All regions';
                    } else if (location) {
                        if (Scalr.platforms[platform] && Scalr.platforms[platform]['locations'] && Scalr.platforms[platform]['locations'][location]) {
                            res += Scalr.platforms[platform]['locations'][location];
                        } else {
                            res += location;
                        }
                    }
                    return res;
                }
            },
            { header: "Image ID", flex: 1.1, dataIndex: 'image_id', sortable: true },
            //{ header: "Architecture", align: 'center', width: 120, dataIndex: 'architecture', sortable: true, xtype: 'templatecolumn', tpl: '<tpl if="!isEmpty"><tpl if="architecture==\'i386\'">32 bit<tpl elseif="architecture==\'x86_64\'">64 bit<tpl else>-</tpl></tpl>' },
            {
                xtype: 'templatecolumn',
                tpl: '<img style="cursor:pointer" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Delete image" src="'+Ext.BLANK_IMAGE_URL+'"/>',
                width: 42,
                sortable: false,
                dataIndex: 'id',
                align:'left'
            }
        ],
		dockedItems: [{
			xtype: 'toolbar',
            ui: 'simple',
			dock: 'top',
			defaults: {
				margin: '0 0 0 10'
			},
            style: 'padding-right:0',
			items: [{
				xtype: 'filterfield',
				itemId: 'imagesLiveSearch',
				margin: 0,
                width: 180,
				filterFields: ['image_id', 'location', 'platform'],
				listeners: {
					afterfilter: function(){
                        this.up('grid').getSelectionModel().setLastFocused(null);
					}
				}
			},{
				xtype: 'tbfill'
            },{
				itemId: 'add',
                text: 'Add image',
                cls: 'x-btn-green-bg',
                hidden: Scalr.flags['betaMode'],
				handler: function() {
					var grid = this.up('grid'),
                        selModel = grid.getSelectionModel(),
                        rec;
                    selModel.deselectAll();
                    rec = grid.getStore().add({architecture: 'x86_64', isEmpty: true})[0];
					selModel.setLastFocused(rec);
				}
			}, {
                itemId: 'add2',
                text: 'Add image',
                cls: 'x-btn-green-bg',
                hidden: !Scalr.flags['betaMode'],
                handler: function() {
                    var grid = this.up('grid'),
                        selModel = grid.getSelectionModel(),
                        used = [];

                    selModel.deselectAll();

                    grid.getStore().each(function(rec) {
                        var platform = rec.get('platform'), location = rec.get('location');

                        if (! (platform in used))
                            used[platform] = [];

                        used[platform].push(location);
                    });

                    var callback = function(images, used) {
                        //clouds filter
                        var platformFilterItems = [{
                            text: 'All clouds',
                            value: null,
                            iconCls: 'x-icon-osfamily-small'
                        }];

                        Ext.Object.each(Scalr.platforms, function(key, value) {
                            if (value.enabled) {
                                platformFilterItems.push({
                                    text: Scalr.utils.getPlatformName(key),
                                    value: key,
                                    iconCls: 'x-icon-platform-small x-icon-platform-small-' + key
                                });
                            }
                        });

                        var imagesStore = Ext.create('store.store', {
                            fields: ['id', 'platform', 'cloudLocation', 'architecture', 'source', 'createdByEmail'],
                            proxy: 'object',
                            data: images
                        });

                        var isAllowedImage = function(record) {
                            if (record.get('platform') in used) {
                                if (used[record.get('platform')].indexOf(record.get('cloudLocation')) != -1) {
                                    return false;
                                }
                            }

                            return true;
                        };

                        Scalr.utils.Window({
                            xtype: 'panel',
                            title: 'Add images',
                            width: 1000,
                            alignTop: true,
                            layout: 'fit',
                            items: [{
                                xtype: 'grid',
                                store: imagesStore,
                                cls: 'x-grid-shadow',
                                margin: '0 12',
                                viewConfig: {
                                    emptyText: 'No available images found for ' + Scalr.utils.beautifyOsFamily(grid.imagesCacheParams.osFamily) + ' ' + grid.imagesCacheParams.osVersion,
                                    deferEmptyText: false
                                },
                                multiSelect: true,
                                selModel: {
                                    selType: 'selectedmodel',
                                    injectCheckbox: 'first',
                                    getVisibility: function(record) {
                                        return isAllowedImage(record) && this.isAllowedToAdd(record);
                                    },
                                    isAllowedToAdd: function(record) {
                                        var selection = this.getSelection(), i, flag = true;
                                        for (i = 0; i < selection.length; i++) {
                                            if (selection[i].get('platform') == record.get('platform') &&
                                                selection[i].get('cloudLocation') == record.get('cloudLocation') &&
                                                selection[i].get('id') != record.get('id')
                                            )
                                                return false;
                                        }

                                        return true;
                                    }
                                },
                                columns: [
                                    { header: 'Image ID', dataIndex: 'id', flex: 1 },
                                    { header: "Cloud location", flex: 1, dataIndex: 'platform', sortable: true, renderer:
                                        function(value, meta, record) {
                                            var platform = record.get('platform'),
                                                location = record.get('cloudLocation'),
                                                res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" title="' + Scalr.utils.getPlatformName(platform) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                                            if (platform === 'gce' || platform === 'ecs') {
                                                res += 'All regions';
                                            } else if (location) {
                                                if (Scalr.platforms[platform] && Scalr.platforms[platform]['locations'] && Scalr.platforms[platform]['locations'][location]) {
                                                    res += Scalr.platforms[platform]['locations'][location];
                                                } else {
                                                    res += location;
                                                }
                                            }
                                            return res;
                                        }
                                    },
                                    { header: 'Architecture', dataIndex: 'architecture', flex: 1 },
                                    { header: 'Source', dataIndex: 'source', flex: 1 },
                                    { header: 'Created by', dataIndex: 'createdByEmail', flex: 1 }
                                ],
                                listeners: {
                                    selectionchange: function(sm, selected) {
                                        this.getView().refresh();
                                        this.getStore().filter();
                                        this.up('panel').down('#add')[selected.length ? 'enable' : 'disable']();
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
                                    text: 'Add',
                                    itemId: 'add',
                                    disabled: true,
                                    handler: function() {
                                        var sel = this.up('panel').down('grid').getSelectionModel().getSelection(), i;
                                        for (i = 0; i < sel.length; i++)
                                            grid.getStore().add({
                                                platform: sel[i].get('platform'),
                                                location: sel[i].get('cloudLocation'),
                                                image_id: sel[i].get('id'),
                                                architecture: sel[i].get('architecture')
                                            });
                                        this.up('#box').close();
                                    }
                                }, {
                                    xtype: 'button',
                                    text: 'Cancel',
                                    handler: function() {
                                        this.up('#box').close();
                                    }
                                }]
                            }, {
                                xtype: 'toolbar',
                                dock: 'top',
                                defaults: {
                                    margin: '0 0 0 12'
                                },
                                items: [{
                                    xtype: 'filterfield',
                                    itemId: 'filterfield',
                                    store: imagesStore,
                                    width: 160,
                                    filterFields: ['id']
                                }, {
                                    xtype: 'cyclealt',
                                    itemId: 'platform',
                                    getItemIconCls: false,
                                    width: 100,
                                    hidden: platformFilterItems.length === 2,
                                    cls: 'x-btn-compressed',
                                    changeHandler: function(comp, item) {
                                        comp.next('#location').setPlatform(item.value);

                                        imagesStore.addFilter({
                                            id: 'platform',
                                            data: { platform: item.value, cloudLocation: undefined },
                                            filterFn: function(record) {
                                                return (record.get('platform') == this.data.platform) || !this.data.platform;
                                            }
                                        });
                                    },
                                    getItemText: function(item) {
                                        return item.value ? 'Cloud: <img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '" />' : item.text;
                                    },
                                    menu: {
                                        cls: 'x-menu-light x-menu-cycle-button-filter',
                                        minWidth: 200,
                                        items: platformFilterItems
                                    }
                                }, {
                                    xtype: 'combo',
                                    itemId: 'location',
                                    matchFieldWidth: false,
                                    width: 180,
                                    editable: false,
                                    store: {
                                        fields: [ 'id', 'name' ],
                                        proxy: 'object'
                                    },
                                    displayField: 'name',
                                    emptyText: 'All locations',
                                    valueField: 'id',
                                    value: '',
                                    queryMode: 'local',
                                    platform: '',
                                    locationsLoaded: false,
                                    listeners: {
                                        change: function(comp, value) {
                                            imagesStore.addFilter({
                                                id: 'platform',
                                                data: { platform: this.platform, cloudLocation: value },
                                                filterFn: function(record) {
                                                    return !this.data.cloudLocation || (record.get('platform') == this.data.platform) && (record.get('cloudLocation') == this.data.cloudLocation);
                                                }
                                            });
                                        },
                                        beforequery: function() {
                                            var me = this;
                                            me.collapse();
                                            Scalr.loadCloudLocations(me.platform, function(data){
                                                var locations = {'': 'All locations'};
                                                Ext.Object.each(data, function(platform, loc){
                                                    Ext.apply(locations, loc);
                                                });
                                                me.store.load({data: locations});
                                                me.locationsLoaded = true;
                                                me.expand();
                                            });
                                            return false;
                                        },
                                        afterrender: {
                                            fn: function() {
                                                this.setPlatform();
                                            },
                                            single: true
                                        }
                                    },
                                    setPlatform: function(platform) {
                                        this.platform = platform;
                                        this.locationsLoaded = false;
                                        this.store.removeAll();
                                        this.suspendEvents(false);
                                        this.reset();
                                        this.resumeEvents();
                                    }
                                }, {
                                    xtype: 'button',
                                    text: 'Show only available images',
                                    enableToggle: true,
                                    pressed: true,
                                    setFilter: function(enabled) {
                                        var grid = this.up('panel').down('grid');
                                        if (enabled) {
                                            grid.getStore().addFilter({
                                                id: 'showonlyavailable',
                                                filterFn: function(record) {
                                                    return isAllowedImage(record) && grid.getSelectionModel().isAllowedToAdd(record);
                                                }
                                            });
                                        } else {
                                            grid.getStore().removeFilter('showonlyavailable');
                                        }
                                    },
                                    toggleHandler: function() {
                                        if (this.pressed)
                                            this.setFilter(true);
                                        else
                                            this.setFilter(false);
                                    },
                                    listeners: {
                                        afterrender: function() {
                                            this.setFilter(true);
                                        }
                                    }
                                }]
                            }]
                        });
                    };

                    if (grid.imagesCache) {
                        callback(grid.imagesCache, used);
                    } else {
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            url: '/images/xGetRoleImages',
                            params: grid.imagesCacheParams,
                            success: function(data) {
                                grid.imagesCache = data.images;
                                callback(grid.imagesCache, used);
                            }
                        });
                    }
                }
            }]
		}]
    },{
        xtype: 'container',
        layout: 'fit',
        flex: .5,
        margin: 0,
        minWidth: 350,
        items: {
            xtype: 'form',
            hidden: true,
            autoScroll: true,
            items: [{
                xtype: 'fieldset',
                title: 'Image details',
                cls: 'x-fieldset-separator-none',
                defaults: {
                    anchor: '100%',
                    maxWidth: 500
                },
                items: [{
                    xtype: 'combo',
                    fieldLabel: 'Cloud',
                    emptyText: 'Please select cloud',
                    store: {
                        fields: ['id', 'name'],
                        proxy: 'object'
                    },
                    valueField: 'id',
                    displayField: 'name',
                    allowBlank: false,
                    editable: false,
                    name: 'platform',
                    queryMode: 'local',
                    listeners: {
                        change: function (comp, value) {
                            var panel = this.up('form');
                            panel.loadLocations(value);
                            if (value) {
                                panel.getForm().findField('architecture').show();
                                panel.getForm().findField('image_id').show();
                            }
                        },
                        beforeselect: function(comp, record) {
                            if (record.get('id') === 'gce') {
                                var store = this.up('form').grid.store;
                                if (store.query('platform', 'gce').length) {
                                    Scalr.message.InfoTip('Only one Google CE image can be added to the role.', comp.inputEl, {anchor: 'bottom'});
                                    return false;
                                }
                            } else if (record.get('id') === 'ecs') {
                                var store = this.up('form').grid.store;
                                if (store.query('platform', 'ecs').length) {
                                    Scalr.message.InfoTip('Only one ECS image can be added to the role.', comp.inputEl, {anchor: 'bottom'});
                                    return false;
                                }
                            }
                        }
                    }
                }, {
                    xtype: 'combo',
                    fieldLabel: 'Location',
                    emptyText: 'Please select location',
                    store: {
                        fields: ['id', 'name'],
                        proxy: 'object'
                    },
                    valueField: 'id',
                    displayField: 'name',
                    hidden: true,
                    disabled: true,
                    allowBlank: false,
                    editable: false,
                    name: 'location',
                    queryMode: 'local',
                    matchFieldWidth: false,
                    listeners: {
                        beforequery: function() {
                            var me = this;
                            me.collapse();
                            Scalr.loadCloudLocations(me.platform, function(data){
                                var locations = {};
                                Ext.Object.each(data, function(platform, loc){
                                    Ext.apply(locations, loc);
                                });
                                me.store.load({data: locations});
                                me.locationsLoaded = true;
                                me.expand();
                            });
                            return false;
                        },
                        beforeselect: function(comp, record) {
                            var frm = this.up('form'),
                                store = frm.grid.store,
                                imgRecord = frm.getForm().getRecord(),
                                platform = imgRecord.get('platform'),
                                location = record.get('id');
                            if (store.queryBy(function(rec){
                                if (imgRecord !== rec && platform == rec.get('platform') && location == rec.get('location')) {
                                    return true;
                                }
                            }).length) {
                                Scalr.message.InfoTip('Image on this cloud location already exists.', comp.inputEl, {anchor: 'bottom'});
                                return false;
                            }
                        }
                    }
                }, {
                    xtype: 'textfield',
                    fieldLabel: 'Location',
                    hidden: true,
                    disabled: true,
                    allowBlank: false,
                    name: 'location_text'
				}, {
					xtype: 'buttongroupfield',
					fieldLabel: 'Architecture',
                    defaults: {
                        width: 80
                    },
                    items: [{
                        text: '32 bit',
                        value: 'i386'
                    },{
                        text: '64 bit',
                        value: 'x86_64'
                    }],
					value: 'x86_64',
					name: 'architecture',
					queryMode: 'local'
				}, {
					xtype: 'textfield',
					fieldLabel: 'Image ID',
					allowBlank: false,
					name: 'image_id'
                }]
            }],
            listeners: {
                afterrender: function() {
                    var me = this,
                        onFieldChange = function(comp, value){
                            me.updateRecord(comp.name, value);
                        };
                    me.grid = me.up('roleeditimages').down('grid');

                    me.getForm().getFields().each(function(field){
                        field.on('change', onFieldChange, field);
                    });
                },
                beforeloadrecord: function(record) {
                    var form = this.getForm(),
                        isNewRecord = record.get('isEmpty');
                    this.isLoading = true;
                    form.reset(true);
                    form.findField('platform').setDisabled(!isNewRecord);
                    form.getFields().each(function(field){
                        if (field.name !== 'platform') {
                            field.setVisible(!isNewRecord, true);
                        }
                    });
                },

                loadrecord: function(record) {
                    var form = this.getForm();

                    if (record.get('isEmpty')) {
                        form.clearInvalid();
                    } else {
                        form.isValid();
                    }
                    
                    if (!this.isVisible()) {
                        this.setVisible(true);
                        this.ownerCt.updateLayout();//recalculate form dimensions after container size was changed, while form was hidden
                    }

                    if (this.showLocationAsText(record.get('platform'))) {
                        form.findField('location_text').setValue(record.get('location'));
                    }
                    this.isLoading = false;
                }
            },
            deselectRecord: function() {
                var form = this.getForm();
                this.setVisible(false);
                this.isLoading = true;
                form.reset(true);
                this.isLoading = false;
            },
            showLocationAsText: function (platform) {
                return Scalr.user['type'] === 'ScalrAdmin' && (Scalr.isCloudstack(platform) || Scalr.isOpenstack(platform));
            },
            loadLocations: function(platform) {
                var locationComboField = this.down('[name="location"]'),
                    locationTextField = this.down('[name="location_text"]'),
                    locations, record = this.getForm().getRecord();
                locationComboField.setDisabled(true).hide().reset();
                locationTextField.setDisabled(true).hide().reset();
                if (!platform) return;
                
                if (platform !== 'gce' && platform !== 'ecs') {
                    if (this.showLocationAsText(platform)) {
                        if (!record.get('location')) {
                            locationTextField.setDisabled(false);
                        }
                        locationTextField.show();
                    } else {
                        locations = (Scalr.platforms[platform] || {})['locations'];
                        if (!record.get('location')) {
                            locationComboField.setDisabled(false);
                        }
                        locationComboField.platform = platform;
                        locationComboField.locationsLoaded = false;
                        locationComboField.getStore().removeAll();
                        locationComboField.show();
                    }
                }
            },
            
            updateRecord: function(fieldName, fieldValue) {
                var record = this.getRecord(),
                    values = {};

                if (this.isLoading || !record) {
                    return;
                }

                if (fieldName === 'location_text') {
                    fieldName = 'location';
                }
                values[fieldName] = fieldValue;
                if (record.get('isEmpty')) {
                    values['isEmpty'] = false;
                }
                values['errors'] = this.getForm().hasInvalidField();
                record.set(values);
            }

        }
    }],

    isValid: function() {
        var store = this.down('grid').getStore(),
            form = this.down('form'),
            errorsCount = 0;

        if (form.isVisible() && !form.getForm().isValid()) {
            errorsCount++;
        } else {
            (store.snapshot || store.data).each(function(record){
                if (record.get('errors')) {
                    errorsCount++;
                    return false;
                }
            });
        }
        return errorsCount === 0;
    },

    addImage: function() {
        this.down(Scalr.flags['betaMode'] ? '#add2' : '#add').handler();
    },

    initComponent: function() {
        this.callParent(arguments);
        this.removedImages = [];
        this.addListener({
            showtab: {
                fn: function(params){
                    var role = params['role'] || {},
                        grid = this.down('grid'),
                        platforms = {};
                    grid.getStore().load({data: role['images']});
                    Ext.Object.each(Scalr.platforms, function(key, value){
                        if (value.enabled) {
                            platforms[key] = Scalr.utils.getPlatformName(key);
                        }
                    });
                    this.down('[name="platform"]').getStore().load({data: platforms});

                    grid.imagesCacheParams = {
                        osFamily: params['role']['osFamily'],
                        osVersion: params['role']['osVersion']
                    };
                },
                single: true
            },
            hidetab: function(params) {
                var store = this.down('grid').getStore(),
                     selModel = this.down('grid').getSelectionModel(),
                     lastFocused = selModel.getLastFocused();
                if (lastFocused && lastFocused.get('isEmpty')) {
                    selModel.deselectAll();
                    selModel.setLastFocused(null);
                }
                params['role']['images'].length = 0;
                (store.snapshot || store.data).each(function(record){
                    params['role']['images'].push(record.getData());
                });
            }
        });
    },
    getSubmitValues: function() {
        var store = this.down('grid').getStore(),
            images = [];
        (store.snapshot || store.data).each(function(record){
            images.push(record.getData());
        });

        return {images: images, removedImages: this.removedImages};
    }
});

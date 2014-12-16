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
        minWidth: 440,
        cls: 'x-grid-shadow x-panel-column-left',
        store: {
            fields: [ 'platform', 'cloudLocation', 'imageId', 'extended'],
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
                        view.up('roleeditimages').removedImages.push({
                            platform: record.get('platform'),
                            cloudLocation: record.get('cloudLocation')
                        });
                        return false;
                    }
                }
            }
        },
        listeners: {
            viewready: function() {
                var me = this;
                me.form = me.up('roleeditimages').down('#formAdd');
                me.down('#imagesLiveSearch').store = me.store;
                me.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                    if (newFocused) {
                        if (newFocused !== me.form.getRecord()) {
                            me.form.loadRecord(newFocused);
                        }
                    } else {
                        me.form.deselectRecord();
                    }
                });
            }
        },
        columns: [
			{ header: "Cloud location", flex: 1, dataIndex: 'platform', sortable: true, renderer:
                function(value, meta, record) {
                    var platform = record.get('platform'),
                        cloudLocation = record.get('cloudLocation'),
                        res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" title="' + Scalr.utils.getPlatformName(platform) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                    if (platform === 'gce' || platform === 'ecs') {
                        res += 'All regions';
                    } else if (cloudLocation) {
                        if (Scalr.platforms[platform] && Scalr.platforms[platform]['locations'] && Scalr.platforms[platform]['locations'][cloudLocation]) {
                            res += Scalr.platforms[platform]['locations'][cloudLocation];
                        } else {
                            res += cloudLocation;
                        }
                    }
                    return res;
                }
            }, {
                header: "Image ID", flex: 1.1, dataIndex: 'imageId', sortable: true
            }, {
                header: '<img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qclass="x-tip-light" data-qtip="' +
                Ext.String.htmlEncode('<div>Scopes:</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr">&nbsp;&nbsp;Scalr</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-environment">&nbsp;&nbsp;Environment</div>') +
                '" />&nbsp;Name',
                dataIndex: 'name',
                sortable: true,
                flex: 1.8,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getLevel(values.extended.envId)]}&nbsp;&nbsp;{extended.name}',
                    {
                        getLevel: function(envId){
                            var scope = envId ? 'environment' : 'scalr';
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qtip="This image is defined at '+Ext.String.capitalize(scope)+' level"/>';
                        }
                    }
                )
            }, {
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
				filterFields: ['imageId', 'cloudLocation', 'platform'],
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
                handler: function() {
                    var grid = this.up('grid'),
                        selModel = grid.getSelectionModel(),
                        used = {};

                    selModel.deselectAll();

                    grid.getStore().each(function(rec) {
                        var platform = rec.get('platform'), cloudLocation = rec.get('cloudLocation');

                        if (! (platform in used))
                            used[platform] = [];

                        used[platform].push(cloudLocation);
                    });

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
                        fields: ['id', 'platform', 'cloudLocation', 'name', 'os', 'osFamily', 'osGeneration', 'osVersion', 'size', 'architecture', 'source', 'type', 'createdByEmail', 'status', 'used', 'dtAdded', 'bundleTaskId', 'envId'],
                        proxy: {
                            type: 'scalr.paging',
                            url: '/images/xList/',
                            extraParams: {
                                os: Ext.encode(grid.imagesCacheParams),
                                hideLocation: Ext.encode(used),
                                hideNotActive: true
                            }
                        },
                        autoLoad: true,
                        pageSize: 15,
                        remoteSort: true
                    });

                    Scalr.utils.Window({
                        xtype: 'panel',
                        title: 'Add images<br><span style="font-size: 11px">Showing only Images matching Role properties: ' +
                            Scalr.utils.beautifyOsFamily(grid.imagesCacheParams.osFamily) + ' ' + grid.imagesCacheParams.osVersion+ '</span>',
                        width: 1280,
                        alignTop: true,
                        layout: 'fit',
                        // grid doesn't have style in Scalr.utils.Window, use temporary parent panel
                        items: [{
                            xtype: 'grid',
                            store: imagesStore,
                            cls: 'x-grid-shadow',
                            margin: '0 12',
                            viewConfig: {
                                emptyText: 'No images found'
                            },
                            columns: [
                                {
                                    header: '<img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qclass="x-tip-light" data-qtip="' +
                                    Ext.String.htmlEncode('<div>Scopes:</div>' +
                                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr">&nbsp;&nbsp;Scalr</div>' +
                                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-environment">&nbsp;&nbsp;Environment</div>') +
                                    '" />&nbsp;Name',
                                    dataIndex: 'name',
                                    sortable: true,
                                    flex: 2,
                                    xtype: 'templatecolumn',
                                    tpl: new Ext.XTemplate('{[this.getScope(values.envId)]}&nbsp;&nbsp;{name}',
                                        {
                                            getScope: function(envId){
                                                var scope = envId ? 'environment' : 'scalr';
                                                return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qtip="This Image is defined in the '+Ext.String.capitalize(scope)+' Scope"/>';
                                            }
                                        }
                                    )
                                }, {
                                    header: 'Image ID', dataIndex: 'id', flex: 1
                                }, {
                                    header: "Cloud location", width: 200, dataIndex: 'platform', sortable: true, renderer:
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
                                { header: 'Architecture', dataIndex: 'architecture', width: 120 },
                                { header: 'Type', dataIndex: 'type', width: 120 },
                                { header: 'Source', dataIndex: 'source', width: 120 },
                                { header: 'Created by', dataIndex: 'createdByEmail', width: 160 }
                            ],
                            listeners: {
                                selectionchange: function (selModel, selections) {
                                    this.down('#add').setDisabled(!selections.length);
                                },

                                itemdblclick: function(grid, record) {
                                    this.down('#add').handler();
                                }
                            },
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
                                        var sel = this.up('grid').getSelectionModel().getSelection();
                                        grid.getStore().add({
                                            platform: sel[0].get('platform'),
                                            cloudLocation: sel[0].get('cloudLocation'),
                                            imageId: sel[0].get('id'),
                                            extended: sel[0].getData()
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
                                xtype: 'scalrpagingtoolbar',
                                itemId: 'paging',
                                style: 'box-shadow:none;padding-left:0;padding-right:0',
                                store: imagesStore,
                                dock: 'top',
                                calculatePageSize: false,
                                beforeItems: [],
                                defaults: {
                                    margin: '0 0 0 12'
                                },
                                items: [{
                                    xtype: 'filterfield',
                                    itemId: 'filterfield',
                                    store: imagesStore,
                                    width: 280,
                                    margin: 0,
                                    form: {
                                        items: [{
                                            xtype: 'textfield',
                                            name: 'id',
                                            fieldLabel: 'Image ID',
                                            labelAlign: 'top'
                                        }]
                                    }
                                }, {
                                    xtype: 'cyclealt',
                                    itemId: 'platform',
                                    getItemIconCls: false,
                                    width: 100,
                                    hidden: platformFilterItems.length === 2,
                                    cls: 'x-btn-compressed',
                                    changeHandler: function(comp, item) {
                                        comp.next('#location').setPlatform(item.value);
                                        imagesStore.proxy.extraParams.platform = item.value;
                                        delete imagesStore.proxy.extraParams.cloudLocation;
                                        imagesStore.load();
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
                                    width: 220,
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
                                            imagesStore.proxy.extraParams.cloudLocation = value;
                                            imagesStore.load();
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
                                        this.store.load();
                                        this.suspendEvents(false);
                                        this.reset();
                                        this.resumeEvents();
                                    }
                                }, {
                                    xtype: 'cyclealt',
                                    itemId: 'scope',
                                    getItemIconCls: false,
                                    flex: 1,
                                    minWidth: 100,
                                    maxWidth: 110,
                                    cls: 'x-btn-compressed',
                                    changeHandler: function(comp, item) {
                                        imagesStore.proxy.extraParams.scope = item.value;
                                        imagesStore.load();
                                    },
                                    getItemText: function(item) {
                                        return item.value ? 'Scope: <img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" style="vertical-align: top; width: 14px; height: 14px;" title="' + item.text + '" />' : item.text;
                                    },
                                    menu: {
                                        cls: 'x-menu-light x-menu-cycle-button-filter',
                                        minWidth: 200,
                                        items: [{
                                            text: 'All scopes',
                                            value: null
                                        },{
                                            text: 'Scalr scope',
                                            value: 'scalr',
                                            iconCls: 'x-menu-item-icon-scope scalr-scope-scalr'
                                        },{
                                            text: 'Environment scope',
                                            value: 'env',
                                            iconCls: 'x-menu-item-icon-scope scalr-scope-env'
                                        }]
                                    }
                                }]
                            }]
                        }]
                    });
                }
            }]
		}]
    },{
        xtype: 'container',
        layout: 'fit',
        flex: .5,
        margin: 0,
        minWidth: 350,
        maxWidth: 600,
        items: [{
            xtype: 'form',
            hidden: true,
            itemId: 'formAdd',
            items: [{
                xtype: 'fieldset',
                title: 'Image information',
                cls: 'x-fieldset-separator-none',
                defaults: {
                    anchor: '100%',
                    labelWidth: 120
                },
                items: [{
                    xtype: 'displayfield',
                    name: 'name',
                    fieldLabel: 'Name'
                }, {
                    xtype: 'displayfield',
                    name: 'platform',
                    fieldLabel: 'Platform'
                }, {
                    xtype: 'displayfield',
                    name: 'cloudLocation',
                    fieldLabel: 'Cloud Location',
                    renderer: function(value) {
                        return value ? value : 'All locations';
                    }
                }, {
                    xtype: 'displayfield',
                    name: 'id',
                    fieldLabel: 'Image ID'
                }, {
                    xtype: 'displayfield',
                    name: 'architecture',
                    fieldLabel: 'Architecture'
                }, {
                    xtype: 'displayfield',
                    name: 'os',
                    fieldLabel: 'Operating system',
                    renderer: function(value) {
                        return value ? value : 'Unknown';
                    }
                }, {
                    xtype: 'displayfield',
                    name: 'software',
                    fieldLabel: 'Software',
                    renderer: function(value) {
                        return value ? value : 'Unknown';
                    }
                }, {
                    xtype: 'displayfield',
                    name: 'size',
                    fieldLabel: 'Size',
                    renderer: function(value) {
                        return value ? (value + ' Gb') : 'Unknown';
                    }
                }, {
                    xtype: 'displayfield',
                    name: 'type',
                    fieldLabel: 'Type',
                    renderer: function(value) {
                        return value ? value : 'Unknown';
                    }
                },{
                    xtype: 'displayfield',
                    name: 'source',
                    fieldLabel: 'Source'
                }, {
                    xtype: 'displayfield',
                    name: 'createdByEmail',
                    fieldLabel: 'Created by',
                    renderer: function(value) {
                        return value ? value : 'Unknown';
                    }
                }, {
                    xtype: 'displayfield',
                    name: 'dtAdded',
                    fieldLabel: 'Created on',
                    renderer: function(value) {
                        return value ? value : 'Unknown';
                    }
                }]
            }],
            deselectRecord: function() {
                var form = this.getForm();
                form.reset(true);
                this.setVisible(false);
            },
            loadRecord: function(record) {
                var ext = Ext.clone(record.get('extended'));

                ext = ext || {};
                ext['name'] = '<a href="#/images/view?platform=' + ext['platform'] + '&cloudLocation=' + ext['cloudLocation'] + '&id=' + ext['id'] + '">' + ext['name'] + '</a>';
                ext['source'] = ext['source'] == 'BundleTask' ? '<a href="#/bundletasks/view?id=' + ext['bundleTaskId'] + '">BundleTask</a>' : ext['source'];
                this.getForm().findField('type')[record.get('platform') == 'ec2' ? 'show' : 'hide']();
                this.setVisible(true);
                this.setFieldValues(ext);
            }
        }]
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
        this.down('#add').handler();
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
                },
                single: true
            },
            hidetab: function(params) {
                var store = this.down('grid').getStore(),
                     selModel = this.down('grid').getSelectionModel(),
                     lastFocused = selModel.getLastFocused();
                params['role']['images'].length = 0;
                (store.snapshot || store.data).each(function(record){
                    params['role']['images'].push({
                        platform: record.get('platform'),
                        cloudLocation: record.get('cloudLocation'),
                        imageId: record.get('imageId'),
                        name: record.get('extended')['name']
                    });
                });
            }
        });
        this.addListener({
            showtab: {
                fn: function(params){
                    var role = params['role'] || {};

                    this.down('grid').imagesCacheParams = {
                        osFamily: role['osFamily'],
                        osVersion: role['osVersion'],
                        osGeneration: role['osGeneration']
                    };
                }
            }
        });
    },
    getSubmitValues: function() {
        var store = this.down('grid').getStore(),
            images = {}, result = [];

        Ext.each(this.removedImages, function(item) {
            images[item['platform']] = images[item['platform']] || [];
            images[item['platform']][item['cloudLocation']] = '';
        });

        (store.snapshot || store.data).each(function(record) {
            images[record.get('platform')] = images[record.get('platform')] || [];
            images[record.get('platform')][record.get('cloudLocation')] = record.get('imageId');
        });

        for (var platform in images) {
            for (var cloudLocation in images[platform]) {
                result.push({
                    platform: platform,
                    cloudLocation: cloudLocation,
                    imageId: images[platform][cloudLocation]
                });
            }
        }

        return { images: result };
    }
});

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
        cls: 'x-panel-column-left',
        store: {
            fields: [
                'platform', 'cloudLocation', 'imageId', 'extended', 'name',
                {
                    name: 'ordering',
                    convert: function(v, record){
                        return record.data.platform + record.data.cloudLocation;
                    }
                }
            ],
            proxy: 'object'
        },
        selModel: {
            selType: 'selectedmodel'
        },
        plugins: [{
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true
        }, {
            ptype: 'focusedrowpointer',
            thresholdOffset: 26
        }],

        viewConfig: {
            getRowClass: function(record) {
                return record.get('errors') ? 'x-grid-row-color-red' : '';
            },
            deferEmptyText: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No images found',
                emptyTextNoItems: 'You have no images added yet.'
            }
        },
        listeners: {
            viewready: function() {
                var me = this;
                me.down('#imagesLiveSearch').store = me.store;
            },
            selectionchange: function(selModel, selected) {
                this.down('#delete').setDisabled(!selected.length);
                this.down('#replace').setDisabled(selected.length != 1);
            }
        },
        columns: [
            { text: "Location", flex: 1, dataIndex: 'ordering', sortable: true, renderer:
                function(value, meta, record) {
                    var platform = record.get('platform'),
                        cloudLocation = record.get('cloudLocation'),
                        res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" title="' + Scalr.utils.getPlatformName(platform) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                    if (platform === 'gce' || platform === 'azure') {
                        res += 'All locations';
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
                text: "Image ID", flex: 1.1, dataIndex: 'imageId', sortable: true
            }, {
                text: 'Image',
                dataIndex: 'name',
                sortable: true,
                flex: 1.8,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getLevel(values.extended.scope)]}&nbsp;&nbsp;{extended.name}',
                    {
                        getLevel: function(scope){
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('image') + '"/>';
                        }
                    }
                )
            }
        ],
        dockedItems: [{
            xtype: 'toolbar',
            ui: 'simple',
            dock: 'top',
            items: [{
                xtype: 'filterfield',
                itemId: 'imagesLiveSearch',
                margin: 0,
                width: 180,
                filterFields: ['imageId', 'cloudLocation', 'platform']
            },{
                xtype: 'tbfill'
            }, {
                itemId: 'add',
                text: 'Add image',
                cls: 'x-btn-green',
                addImageHandler: function(replaceImageRecord) {
                    var grid = this.up('grid'),
                        os = Scalr.utils.getOsById(grid.imagesCacheParams.osId) || {},
                        existingLocations = {};

                    grid.getStore().getUnfiltered().each(function (rec) {
                        var platform = rec.get('platform'), cloudLocation = rec.get('cloudLocation');

                        if (!(platform in existingLocations))
                            existingLocations[platform] = {};

                        existingLocations[platform][cloudLocation] = rec;
                    });

                    //clouds filter
                    var platforms = [];
                    Ext.Object.each(Scalr.platforms, function (key, value) {
                        if (value.enabled) {
                            platforms.push(key);
                        }
                    });

                    var imagesStore = Ext.create('Scalr.ui.ContinuousStore', {
                        fields: ['id', 'platform', 'cloudLocation', 'name', 'osId', 'size', 'architecture', 'source', 'type', 'createdByEmail', 'status', 'used', 'dtAdded', 'bundleTaskId', 'envId', 'scope'],
                        proxy: {
                            type: 'ajax',
                            url: '/images/xList/',
                            extraParams: {
                                osId: grid.imagesCacheParams.osId,
                                isScalarized: grid.imagesCacheParams.isScalarized,
                                hideNotActive: true,
                                useHashAsFilter: true,
                            },
                            reader: {
                                type: 'json',
                                rootProperty: 'data',
                                totalProperty: 'total',
                                successProperty: 'success'
                            }
                        },
                        autoLoad: true
                    });

                    Scalr.utils.Window({
                        xtype: 'panel',
                        title: 'Add images' +
                            '<span style="font-size: 13px; font-family: OpenSansRegular, arial; text-transform: none; line-height: 22px;">' +
                                ' (Only <a href="https://scalr-wiki.atlassian.net/wiki/x/d4BM" target="_blank">Images registered with Scalr</a> are listed)' +
                            '</span>',
                        width: '80%',
                        alignTop: true,
                        layout: 'fit',
                        // grid doesn't have style in Scalr.utils.Window, use temporary parent panel
                        items: [{
                            xtype: 'grid',
                            store: imagesStore,
                            margin: '0 12',
                            scrollable: true,
                            plugins: [{
                                ptype: 'continuousrenderer'
                            }],

                            viewConfig: {
                                emptyText: 'No images found'
                            },
                            columns: [
                                {
                                    text: 'Name',
                                    dataIndex: 'name',
                                    sortable: true,
                                    flex: 2,
                                    xtype: 'templatecolumn',
                                    tpl: new Ext.XTemplate('{[this.getScope(values.scope)]}&nbsp;&nbsp;{name}',
                                        {
                                            getScope: function (scope) {
                                                return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-' + scope + '" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('image') + '"/>';
                                            }
                                        }
                                    )
                                }, {
                                    text: 'Image ID', dataIndex: 'id', flex: 1
                                }, {
                                    text: "Location",
                                    width: 200,
                                    dataIndex: 'platform',
                                    sortable: true,
                                    renderer: function (value, meta, record) {
                                        var platform = record.get('platform'),
                                            location = record.get('cloudLocation'),
                                            res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" data-qtip="' + Scalr.utils.getPlatformName(platform) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                                        if (platform === 'gce' || platform === 'azure') {
                                            res += 'All locations';
                                        } else if (location) {
                                            if (Scalr.platforms[platform] && Scalr.platforms[platform]['locations'] && Scalr.platforms[platform]['locations'][location]) {
                                                res += Scalr.platforms[platform]['locations'][location];
                                            } else {
                                                res += location;
                                            }
                                        }
                                        return res;
                                    },
                                    multiSort: function (st, direction) {
                                        st.sort([{
                                            property: 'platform',
                                            direction: direction
                                        }, {
                                            property: 'cloudLocation',
                                            direction: direction
                                        }]);
                                    }
                                },
                                {text: 'Architecture', dataIndex: 'architecture', width: 120},
                                {text: 'Type', dataIndex: 'type', width: 120},
                                {text: 'Source', dataIndex: 'source', width: 120},
                                {text: 'Created by', dataIndex: 'createdByEmail', width: 160}
                            ],
                            listeners: {
                                selectionchange: function (selModel, selections) {
                                    this.down('#add').setDisabled(!selections.length);
                                },

                                itemdblclick: function (grid, record) {
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
                                    text: replaceImageRecord ? 'Replace' : 'Add',
                                    itemId: 'add',
                                    disabled: true,
                                    handler: function () {
                                        var me = this,
                                            record = me.up('grid').getSelectionModel().getSelection()[0],
                                            platform = record.get('platform'),
                                            cloudLocation = record.get('cloudLocation'),
                                            imageId = record.get('id');
                                        addImage = function (imageToRemove) {
                                            var store = grid.getStore();
                                            if (imageToRemove) {
                                                store.remove(imageToRemove);
                                            }
                                            store.add({
                                                platform: platform,
                                                cloudLocation: cloudLocation,
                                                name: record.get('name'),
                                                imageId: imageId,
                                                extended: record.getData()
                                            });
                                            me.up('#box').close();
                                        };
                                        if (existingLocations[platform] !== undefined && existingLocations[platform][cloudLocation] !== undefined) {
                                            var existingImageId = existingLocations[platform][cloudLocation].get('imageId');
                                            if (imageId != existingImageId) {
                                                Scalr.Confirm({
                                                    type: 'action',
                                                    msg: 'Are you sure want to replace ' + Scalr.utils.getPlatformName(platform) + ' image <b style="white-space:nowrap">' + existingImageId + '</b> ' +
                                                    (!Ext.isEmpty(cloudLocation) ? 'in region <b style="white-space:nowrap">' + cloudLocation + '</b> ' : '') + 'with a new image <b style="white-space:nowrap"s>' + imageId + '</b>?',
                                                    ok: 'Replace',
                                                    formWidth: 600,
                                                    success: function (formValues, form) {
                                                        addImage(existingLocations[platform][cloudLocation]);
                                                    }
                                                });
                                            } else {
                                                me.up('#box').close();
                                            }
                                            return;

                                        }
                                        addImage();
                                    }
                                }, {
                                    xtype: 'button',
                                    text: 'Cancel',
                                    handler: function () {
                                        this.up('#box').close();
                                    }
                                }]
                            }, {
                                xtype: 'toolbar',
                                itemId: 'paging',
                                style: 'box-shadow:none;padding-left:0;padding-right:0',
                                dock: 'top',
                                defaults: {
                                    margin: '0 0 12 12'
                                },
                                items: [{
                                    xtype: 'filterfield',
                                    itemId: 'filterfield',
                                    store: imagesStore,
                                    flex: 1,
                                    maxWidth: 200,
                                    margin: 0,
                                    form: {
                                        items: [{
                                            xtype: 'textfield',
                                            name: 'id',
                                            fieldLabel: 'Cloud Image ID',
                                            labelAlign: 'top'
                                        }]
                                    }
                                }, {
                                    xtype: 'cloudlocationfield',
                                    cls: 'x-btn-compressed',
                                    platforms: platforms,
                                    value: replaceImageRecord ? { platform: replaceImageRecord.get('platform'), cloudLocation: replaceImageRecord.get('cloudLocation') } : null,
                                    disabled: !!replaceImageRecord,
                                    listeners: {
                                        change: function (me, value) {
                                            imagesStore.applyProxyParams(value);
                                        }
                                    }
                                }, {
                                    xtype: 'cyclealt',
                                    itemId: 'scope',
                                    getItemIconCls: false,
                                    flex: 1,
                                    minWidth: 100,
                                    maxWidth: 110,
                                    hidden: Scalr.scope == 'scalr',
                                    disabled: Scalr.scope == 'scalr',
                                    cls: 'x-btn-compressed',
                                    changeHandler: function (comp, item) {
                                        imagesStore.proxy.extraParams.scope = item.value;
                                        imagesStore.load();
                                    },
                                    getItemText: function (item) {
                                        return item.value ? 'Scope: &nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '" />' : item.text;
                                    },
                                    menu: {
                                        cls: 'x-menu-light x-menu-cycle-button-filter',
                                        minWidth: 200,
                                        items: [{
                                            text: 'All scopes',
                                            value: null
                                        }, {
                                            text: 'Scalr scope',
                                            value: 'scalr',
                                            iconCls: 'x-menu-item-icon-scope scalr-scope-scalr'
                                        }, {
                                            text: 'Account scope',
                                            value: 'account',
                                            iconCls: 'x-menu-item-icon-scope scalr-scope-account'
                                        }, {
                                            text: 'Environment scope',
                                            value: 'environment',
                                            iconCls: 'x-menu-item-icon-scope scalr-scope-environment',
                                            hidden: Scalr.scope !== 'environment',
                                            disabled: Scalr.scope !== 'environment'
                                        }]
                                    }
                                }, {
                                    xtype: 'displayfield',
                                    cls: 'x-form-field-filter',
                                    fieldStyle: 'padding: 6px 0 4px 0', // set smaller padding because icon's height expands component height
                                    value: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-osfamily-small x-icon-osfamily-small-' + os.family + '"/>&nbsp;' + Scalr.utils.beautifyOsFamily(os.family) + ' ' + (os.version || os.generation || os.id)
                                }, {
                                    xtype: 'displayfield',
                                    cls: 'x-form-field-filter',
                                    hidden: grid.imagesCacheParams.isScalarized != 1,
                                    value: '<span data-qtip="Show Images with Scalarizr (or Сloud-init) only">Images with <span class="x-semibold">Scalarizr</span> (or <span class="x-semibold">Сloud-init</span>)</span>'
                                }]
                            }]
                        }]
                    });
                },

                handler: function () {
                    this.up('grid').getSelectionModel().deselectAll();
                    this.addImageHandler();
                }
            }, {
                itemId: 'replace',
                iconCls: 'x-btn-icon-replace',
                disabled: true,
                margin: '0 0 0 12',
                tooltip: 'Replace selected image',
                handler: function() {
                    var grid = this.up('grid'),
                        selection = grid.getSelectionModel().getSelection();

                    grid.down('#add').addImageHandler(selection[0]);
                }
            }, {
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                margin: '0 0 0 12',
                tooltip: 'Delete selected images',
                handler: function() {
                    var grid = this.up('grid'),
                        selection = grid.getSelectionModel().getSelection();
                    Ext.each(selection, function(record){
                        grid.up('roleeditimages').removedImages.push({
                            platform: record.get('platform'),
                            cloudLocation: record.get('cloudLocation')
                        });
                    });
                    grid.getStore().remove(selection);
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
            listeners: {
                loadrecord: function(record) {
                    var frm = this.getForm(),
                        ext = record.get('extended') || {};
                    this.setFieldValues(ext);
                    frm.findField('name').setValue(ext['hash'] ? ('<a href="#' + Scalr.utils.getUrlPrefix() + '/images?hash=' + ext['hash'] + '">' + ext['name'] + '</a>') : (ext['name'] || 'Unknown'));
                    frm.findField('source').setValue(ext['source'] == 'BundleTask' ? '<a href="#/bundletasks?id=' + ext['bundleTaskId'] + '">BundleTask</a>' : ext['source']);
                    frm.findField('type')[record.get('platform') == 'ec2' ? 'show' : 'hide']();
                }
            },
            items: [{
                xtype: 'fieldset',
                title: 'Image information',
                cls: 'x-fieldset-separator-none',
                defaults: {
                    anchor: '100%',
                    labelWidth: 130
                },
                items: [{
                    xtype: 'displayfield',
                    name: 'name',
                    fieldLabel: 'Name'
                }, {
                    xtype: 'displayfield',
                    name: 'cloudLocation',
                    fieldLabel: 'Location',
                    renderer: function (value) {
                        var me = this;

                        var record = me.up('form').getRecord();
                        var cloudLocation = !Ext.isEmpty(value) ? value : 'All locations';

                        if (!Ext.isEmpty(record)) {
                            var platform = record.get('platform');
                            var platformName = Scalr.utils.getPlatformName(platform);

                            return '<img class="x-icon-platform-small x-icon-platform-small-' + platform +
                                '" data-qtip="' + platformName + '" src="' + Ext.BLANK_IMAGE_URL +
                                '"/> ' + cloudLocation;
                        }

                        return cloudLocation;
                    }
                }, {
                    xtype: 'displayfield',
                    name: 'id',
                    fieldLabel: 'Cloud Image ID'
                }, {
                    xtype: 'displayfield',
                    name: 'architecture',
                    fieldLabel: 'Architecture'
                }, {
                    xtype: 'displayfield',
                    name: 'osId',
                    fieldLabel: 'Operating system',
                    renderer: function(value) {
                        return Scalr.utils.getOsById(value, 'name') || value;
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
                    name: 'isScalarized',
                    fieldLabel: 'Scalarizr',
                    renderer: function(value) {
                        var record = this.up('form').getForm().getRecord();
                        return value == 1 ? 'Installed ('+(record.get('agentVersion') || 'Unknown')+')' : 'Not installed';
                    }
                }, {
                    xtype: 'displayfield',
                    name: 'hasCloudInit',
                    fieldLabel: 'Cloud-init',
                    renderer: function(value) {
                        return value == 1 ? 'Installed' : 'Not installed';
                    }
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
            }]
        }]
    }],

    isValid: function() {
        var store = this.down('grid').getStore(),
            form = this.down('form'),
            errorsCount = 0;

        if (form.isVisible() && !form.getForm().isValid()) {
            errorsCount++;
        } else {
            store.getUnfiltered().each(function(record){
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
                        grid = this.down('grid');
                    grid.getStore().load({data: role['images']});
                },
                single: true
            },
            hidetab: function(params) {
                var store = this.down('grid').getStore();
                params['role']['images'].length = 0;
                store.getUnfiltered().each(function(record){
                    params['role']['images'].push({
                        platform: record.get('platform'),
                        cloudLocation: record.get('cloudLocation'),
                        imageId: record.get('imageId'),
                        name: record.get('extended')['name'],
                        hash: record.get('extended')['hash'],
                        isScalarized: record.get('extended')['isScalarized'],
                        hasCloudInit: record.get('extended')['hasCloudInit']
                    });
                });
            }
        });
        this.addListener({
            showtab: {
                fn: function(params){
                    var grid = this.down('grid'),
                        role = params['role'] || {};
                    grid.imagesCacheParams = {
                        osId: role['osId'],
                        isScalarized: role['isScalarized']
                    };
                    grid.down('#add').setVisible(Scalr.utils.isAdmin() || Scalr.isAllowed('IMAGES_' + Scalr.scope.toUpperCase()));
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

        store.getUnfiltered().each(function(record) {
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

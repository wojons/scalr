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
            },
            listeners: {
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('img.x-grid-icon-delete')) {
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
                me.down('#imagesLiveSearch').store = me.store;
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
            }, {
                xtype: 'templatecolumn',
                tpl: '<img class="x-grid-icon x-grid-icon-delete" title="Delete image" src="'+Ext.BLANK_IMAGE_URL+'"/>',
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
            items: [{
                xtype: 'filterfield',
                itemId: 'imagesLiveSearch',
                margin: 0,
                width: 180,
                filterFields: ['imageId', 'cloudLocation', 'platform']
            },{
                xtype: 'tbfill'
            },{
                itemId: 'add',
                text: 'Add image',
                cls: 'x-btn-green',
                handler: function() {
                    var grid = this.up('grid'),
                        selModel = grid.getSelectionModel(),
                        used = {};

                    selModel.deselectAll();

                    grid.getStore().getUnfiltered().each(function(rec) {
                        var platform = rec.get('platform'), cloudLocation = rec.get('cloudLocation');

                        if (! (platform in used))
                            used[platform] = [];

                        used[platform].push(cloudLocation);
                    });

                    //clouds filter
                    var platforms = [];
                    Ext.Object.each(Scalr.platforms, function(key, value) {
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
                                hideLocation: Ext.encode(used),
                                hideNotActive: true
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
                        title: 'Add images<div style="font-size: 12px; font-family: OpenSansRegular, arial; text-transform: none; line-height: 22px;">Showing only Images matching Role OS: ' + Scalr.utils.getOsById(grid.imagesCacheParams.osId, 'name') + '.' +
                            ' Only <a href="https://scalr-wiki.atlassian.net/wiki/x/d4BM" target="_blank">Images registered with Scalr</a> are listed.' +
                            '</div>',
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
                                            getScope: function(scope){
                                                return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('image') + '"/>';
                                            }
                                        }
                                    )
                                }, {
                                    text: 'Image ID', dataIndex: 'id', flex: 1
                                }, {
                                    text: "Location", width: 200, dataIndex: 'platform', sortable: true, renderer:
                                    function(value, meta, record) {
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
                                { text: 'Architecture', dataIndex: 'architecture', width: 120 },
                                { text: 'Type', dataIndex: 'type', width: 120 },
                                { text: 'Source', dataIndex: 'source', width: 120 },
                                { text: 'Created by', dataIndex: 'createdByEmail', width: 160 }
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
                                            name: sel[0].get('name'),
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
                                xtype: 'toolbar',
                                itemId: 'paging',
                                style: 'box-shadow:none;padding-left:0;padding-right:0',
                                dock: 'top',
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
                                            fieldLabel: 'Cloud Image ID',
                                            labelAlign: 'top'
                                        }]
                                    }
                                }, {
                                    xtype: 'cloudlocationfield',
                                    cls: 'x-btn-compressed',
                                    platforms: platforms,
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
                                    changeHandler: function(comp, item) {
                                        imagesStore.proxy.extraParams.scope = item.value;
                                        imagesStore.load();
                                    },
                                    getItemText: function(item) {
                                        return item.value ? 'Scope: &nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '" />' : item.text;
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
                                            text: 'Account scope',
                                            value: 'account',
                                            iconCls: 'x-menu-item-icon-scope scalr-scope-account'
                                        },{
                                            text: 'Environment scope',
                                            value: 'environment',
                                            iconCls: 'x-menu-item-icon-scope scalr-scope-environment',
                                            hidden: Scalr.scope !== 'environment',
                                            disabled: Scalr.scope !== 'environment'
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
                        hash: record.get('extended')['hash']
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
                    	osId: role['osId']
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

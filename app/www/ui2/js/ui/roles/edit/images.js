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
                    if (platform === 'gce') {
                        res += 'All regions';
                    }
                    if (location) {
                        if (this.platforms[platform] && this.platforms[platform]['locations'][location]) {
                            res += this.platforms[platform]['locations'][location];
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
				handler: function() {
					var grid = this.up('grid'),
                        selModel = grid.getSelectionModel(),
                        rec;
                    selModel.deselectAll();
                    rec = grid.getStore().add({architecture: 'x86_64', isEmpty: true})[0];
					selModel.setLastFocused(rec);
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
                    matchFieldWidth: false
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
                    platforms = this.up('roleeditimages').platforms,
                    locations, record = this.getForm().getRecord();
                locationComboField.setDisabled(true).hide().reset();
                locationTextField.setDisabled(true).hide().reset();
                if (!platform) return;
                
                if (platform !== 'gce') {
                    if (this.showLocationAsText(platform)) {
                        if (!record.get('location')) {
                            locationTextField.setDisabled(false);
                        }
                        locationTextField.show();
                    } else {
                        locations = (platforms[platform] || {})['locations'];
                        if (!record.get('location')) {
                            locationComboField.setDisabled(false);
                        }
                        locationComboField.show();
                        locationComboField.getStore().load({ data: locations});
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
            errorsCount = 0;
        (store.snapshot || store.data).each(function(record){
            if (record.get('errors')) {
                errorsCount++;
                return false;
            }
        });
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
                    grid.platforms = params.platforms;
                    Ext.Object.each(params.platforms, function(key, value){
                        platforms[key] = Scalr.utils.getPlatformName(key);
                    });
                    this.down('[name="platform"]').getStore().load({data: platforms});
                    this.platforms = params.platforms;
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

        return {images: Ext.encode(images), removedImages: Ext.encode(this.removedImages)};
    }
});

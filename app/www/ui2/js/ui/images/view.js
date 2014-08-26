Scalr.regPage('Scalr.ui.images.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
        fields: ['id', 'platform', 'cloudLocation', 'osName', 'osFamily', 'architecture', 'source', 'createdByEmail', 'status', 'dtAdded'],
		proxy: {
			type: 'scalr.paging',
			url: '/images/xList'
		},
		remoteSort: true
	});

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

    return Ext.create('Ext.grid.Panel', {
		title: 'Images &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-images-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Images',
				href: '#/images/view'
			}
		}],

		viewConfig: {
			emptyText: 'No images defined',
			loadingText: 'Loading images ...'
		},

		columns: [
            { header: '&nbsp;', width: 50, dataIndex: 'platform', sortable: true, renderer:
                function(value, meta, record) {
                    return '<img class="x-icon-platform-small x-icon-platform-small-' + record.get('platform') + '" title="' + Scalr.utils.getPlatformName(record.get('platform')) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                }
            },
            { header: "Cloud location", flex: 1, dataIndex: 'cloudLocation', sortable: true },
            { header: 'Image ID', dataIndex: 'id', flex: 1 },
            { header: 'OS', flex: 1, dataIndex: 'osName', sortable: false, xtype: 'templatecolumn', tpl: '<img style="margin:0 3px"  class="x-icon-osfamily-small x-icon-osfamily-small-{osFamily}" src="' + Ext.BLANK_IMAGE_URL + '"/> {osName}' },
            { header: 'Architecture', dataIndex: 'architecture', flex: 1 },
            { header: 'Source', dataIndex: 'source', flex: 1 },
            { header: 'Created by', dataIndex: 'createdByEmail', flex: 1, sortable: false },
            { header: 'Created at', dataIndex: 'dtAdded', flex: 1, sortable: true },
            { header: "Status", maxWidth: 100, dataIndex: 'status', sortable: false, xtype: 'statuscolumn', statustype: 'image', resizable: false },
            {
				xtype: 'optionscolumn2',
                hidden: true,
				menu: [{
					//itemId: 'option.view',
					//iconCls: 'x-menu-icon-view',
					text: 'Show roles',
					//href: '#/scripts/{id}/view',
                    getVisibility: function(record) {
                        return record.get('status') == 'Not used';
                    },
                    menuHandler: function(data) {


                    }
				}]
			}
		],

		multiSelect: true,
		selModel: {
			selType: 'selectedmodel',
			getVisibility: function(record) {
				return record.get('status') == 'Not used';
			}
		},

		listeners: {
			selectionchange: function(selModel, selections) {
				var toolbar = this.down('scalrpagingtoolbar');
				toolbar.down('#delete').setDisabled(!selections.length);
			}
		},

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			beforeItems: [{
                text: 'Add script',
                hidden: true,
                cls: 'x-btn-green-bg',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/scripts/create');
				}
			}],

			afterItems: [{
				ui: 'paging',
				itemId: 'delete',
				iconCls: 'x-tbar-delete',
				tooltip: 'Select one or more images to delete them',
				disabled: true,
				handler: function() {
					var request = {
						confirmBox: {
							msg: 'Remove selected image(s): %s ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Removing selected image(s) ...',
							type: 'delete'
						},
						url: '/images/xRemove',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push({
                            id: records[i].get('id'),
                            platform: records[i].get('platform'),
                            cloudLocation: records[i].get('cloudLocation')
                        });
						request.confirmBox.objects.push(records[i].get('id'));
					}
					request.params = { images: Ext.encode(data) };
					Scalr.Request(request);
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}, {
                xtype: 'cyclealt',
                margin: '0 0 0 12',
                itemId: 'platform',
                getItemIconCls: false,
                width: 100,
                hidden: platformFilterItems.length === 2,
                cls: 'x-btn-compressed',
                changeHandler: function(comp, item) {
                    comp.next('#location').setPlatform(item.value);

                    store.proxy.extraParams.platform = item.value;
                    store.proxy.extraParams.cloudLocation = '';
                    store.loadPage(1);
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
                margin: '0 0 0 12',
                itemId: 'location',
                matchFieldWidth: false,
                width: 250,
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
                        store.proxy.extraParams.platform = this.platform;
                        store.proxy.extraParams.cloudLocation = value;
                        store.loadPage(1);
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
            }]
		}]
	});
});

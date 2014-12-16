Scalr.regPage('Scalr.ui.images.view', function (loadParams, moduleParams) {
    var platformFilterItems = [{
        text: 'All clouds',
        value: null,
        iconCls: 'x-icon-osfamily-small'
    }];

    Ext.Object.each(Scalr.platforms, function(key, value) {
        if (value.enabled || (moduleParams['platforms'].indexOf(key) != -1)) {
            platformFilterItems.push({
                text: Scalr.utils.getPlatformName(key),
                value: key,
                iconCls: 'x-icon-platform-small x-icon-platform-small-' + key
            });
        }
    });

    var deleteConfirmationForm = {
        xtype: 'fieldset',
        title: 'Removal parameters',
        hidden: Scalr.user.type == 'ScalrAdmin',
        items: [{
            xtype: 'checkbox',
            boxLabel: 'Remove image from cloud',
            inputValue: 1,
            checked: false,
            name: 'removeFromCloud',
            listeners: {
                change: function(field, value) {
                    this.next()[value ? 'show' : 'hide']();
                    this.updateLayout();
                }
            }
        }, {
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            value: 'The cloud image deletion process is asynchronous; if an error occurs, it will be reported here.',
            hidden: true
        }]
    };

    //os filter
    var osFilterItems = [{
        text: 'All OS',
        value: null,
        iconCls: 'x-icon-osfamily-small'
    }];
    Ext.Array.each(moduleParams['os'], function(value){
        osFilterItems.push({
            text: Scalr.utils.beautifyOsFamily(value),
            value: value,
            iconCls: 'x-icon-osfamily-small x-icon-osfamily-small-' + value
        });
    });

    var imagesStore = Ext.create('store.store', {
        fields: ['id', 'platform', 'cloudLocation', 'name', 'os', 'osFamily', 'osGeneration', 'osVersion', 'size',
            'architecture', 'source', 'type', 'createdByEmail', 'status', 'statusError', 'used', 'status', 'dtAdded', 'bundleTaskId',
            'envId', 'software'
        ],
        proxy: {
            type: 'ajax',
            url: '/images/xList/',
            reader: {
                type: 'json',
                root: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },
        leadingBufferZone: 0,
        trailingBufferZone: 0,
        pageSize: 100,
        buffered: true,
        remoteSort: true,
        purgePageCount: 0,
        listeners: {
            beforeload: function() {
                var selModel = grid.getSelectionModel();
                selModel.deselectAll();
                selModel.setLastFocused(null);
            },
            prefetch: function(store, records) {
                if (records) {
                    //console.log(this.getCount() + records.length + ' of ' + this.getTotalCount());
                }
            },
            load: function(store, records, successful) {
                if (successful && records.length == 1) {
                    grid.getSelectionModel().select(records[0]);
                }
            }
        },
        updateParamsAndLoad: function(params, reset) {
            if (reset) {
                this.proxy.extraParams = {};
            }
            var proxyParams = this.proxy.extraParams;
            Ext.Object.each(params, function(name, value) {
                if (value === undefined) {
                    delete proxyParams[name];
                } else {
                    proxyParams[name] = value;
                }
            });
            this.removeAll();
            this.load();
        },
        isFilteredByRoleId: function() {
            return !!this.proxy.extraParams.roleId;
        }
    });

    var grid = Ext.create('Ext.grid.Panel', {
        xtype: 'grid',
        itemId: 'roles',
        flex: 1.2,
        cls: 'x-grid-shadow x-grid-shadow-buffered x-panel-column-left',
        store: imagesStore,
        padding: '0 0 12 0',
        plugins: [
            'focusedrowpointer',

            {
                ptype: 'bufferedrenderer',
                scrollToLoadBuffer: 100,
                synchronousRender: false
            }
        ],
        forceFit: true,
        viewConfig: {
            emptyText: 'No images found',
            deferEmptyText: false,
            loadMask: false
        },

        columns: [{
                header: '<img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qclass="x-tip-light" data-qtip="' +
                Ext.String.htmlEncode('<div>Scopes:</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr">&nbsp;&nbsp;Scalr</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-environment">&nbsp;&nbsp;Environment</div>') +
                '" />&nbsp;Name',
                dataIndex: 'name',
                sortable: true,
                flex: 1,
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
                header: 'Location',
                minWidth: 110,
                flex: 1,
                dataIndex: 'platform',
                sortable: true,
                renderer: function (value, meta, record) {
                    var platform = record.get('platform'), location = record.get('cloudLocation');
                    return '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" title="' + Scalr.utils.getPlatformName(platform) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;<span style="line-height: 18px;">' + (location ? location : 'All locations') + '</span>';
                },
                doSort: function (state) {
                    var ds = this.up('tablepanel').store;
                    ds.sort([{
                        property: 'platform',
                        direction: state
                    }, {
                        property: 'cloudLocation',
                        direction: state
                    }]);
                }
            },
            { header: 'OS', flex: 1, maxWidth: 170, dataIndex: 'os', sortable: true, xtype: 'templatecolumn', tpl: '<img style="margin:0 3px"  class="x-icon-osfamily-small x-icon-osfamily-small-{osFamily}" src="' + Ext.BLANK_IMAGE_URL + '"/> {os}' },
            { header: 'Created by', dataIndex: 'createdByEmail', flex: 0.5, maxWidth: 150, sortable: false },
            { header: 'Created on', dataIndex: 'dtAdded', flex: 0.5, maxWidth: 170, sortable: true },
            { header: "Status", maxWidth: 100, dataIndex: 'status', sortable: false, xtype: 'statuscolumn', statustype: 'image', resizable: false },

        ],

        multiSelect: true,
        selModel: {
            selType: 'selectedmodel',
            pruneRemoved: true,
            getVisibility: function(record) {
                return  !record.get('used') &&
                        record.get('status') != 'delete' &&
                        (Scalr.user.type == 'ScalrAdmin' || record.get('envId') && Scalr.isAllowed('FARMS_IMAGES', 'manage'));
            }
        },

        listeners: {
            selectionchange: function(selModel, selections) {
                this.down('toolbar').down('#delete').setDisabled(!selections.length);
            }
        },
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            defaults: {
                margin: '0 0 0 12'
            },
            enableParamsCapture: true,
            store: imagesStore,
            items: [{
                xtype: 'filterfield',
                itemId: 'filterfield',
                store: imagesStore,
                flex: 2.5,
                minWidth: 200,
                maxWidth: 350,
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
                margin: '0 0 0 12',
                itemId: 'platform',
                name: 'platform',
                getItemIconCls: false,
                width: 110,
                hidden: platformFilterItems.length === 2,
                cls: 'x-btn-compressed',
                changeHandler: function(comp, item) {
                    comp.next('#location').setPlatform(item.value);
                    imagesStore.updateParamsAndLoad({platform: item.value, cloudLocation: undefined});
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
                name: 'cloudLocation',
                matchFieldWidth: false,
                flex: 2,
                minWidth: 90,
                maxWidth: 220,
                editable: false,
                store: {
                    fields: ['id', 'name'],
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
                    change: function (comp, value) {
                        imagesStore.updateParamsAndLoad({cloudLocation: value});
                    },
                    beforequery: function () {
                        var me = this;
                        me.collapse();
                        Scalr.loadCloudLocations(me.platform, function (data) {
                            var locations = {'': 'All locations'};
                            Ext.Object.each(data, function (platform, loc) {
                                Ext.apply(locations, loc);
                            });
                            me.store.load({data: locations});
                            me.locationsLoaded = true;
                            me.expand();
                        });
                        return false;
                    },
                    afterrender: {
                        fn: function () {
                            this.setPlatform();
                        },
                        single: true
                    }
                },
                setPlatform: function (platform) {
                    this.platform = platform;
                    this.locationsLoaded = false;
                    this.store.removeAll();
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
                hidden: Scalr.user.type == 'ScalrAdmin',
                cls: 'x-btn-compressed',
                changeHandler: function(comp, item) {
                    imagesStore.updateParamsAndLoad({scope: item.value});
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
            }, {
                xtype: 'cyclealt',
                itemId: 'os',
                flex: 1,
                minWidth: 80,
                maxWidth: 110,
                getItemIconCls: false,
                cls: 'x-btn-compressed',
                hidden: osFilterItems.length === 2,
                changeHandler: function(comp, item) {
                    imagesStore.updateParamsAndLoad({osFamily: item.value});
                },
                getItemText: function(item) {
                    return item.value ? 'OS: <img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '"/>' : item.text;
                },
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: osFilterItems
                }
            }, {
                xtype: 'tbfill',
                flex: .01
            }, {
                text: 'Add image',
                disabled: !Scalr.isAllowed('FARMS_IMAGES', 'create') && Scalr.user.type != 'ScalrAdmin',
                cls: 'x-btn-green-bg',
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/images/create');
                }
            }, {
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                tooltip: 'Refresh',
                handler: function() {
                    imagesStore.updateParamsAndLoad();
                }
            }, {
                ui: 'paging',
                itemId: 'delete',
                iconCls: 'x-tbar-delete',
                tooltip: 'Select one or more images to delete them',
                disabled: true,
                handler: function() {
                    var request = {
                        confirmBox: {
                            msg: 'Remove selected image(s): %s ?',
                            type: 'delete',
                            formWidth: 440,
                            form: deleteConfirmationForm
                        },
                        processBox: {
                            msg: 'Removing selected image(s) ...',
                            type: 'delete'
                        },
                        url: '/images/xRemove',
                        success: function() {
                            imagesStore.updateParamsAndLoad();
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
            }]
        }]
    });

    var form = Ext.create('Ext.form.Panel', {
        hidden: true,
        autoScroll: true,
        trackResetOnLoad: true,
        listeners: {
            afterrender: function() {
                var me = this;
                grid.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                    if (newFocused) {
                        if (me.getRecord() !== newFocused) {
                            me.loadRecord(newFocused);
                        }
                    } else {
                        me.setVisible(false);
                        me.getForm().reset(true);
                    }
                });
            },
            beforeloadrecord: function(record) {
                var frm = this.getForm();

                frm.reset(true);

                this.down('#main').setTitle(record.get('name'));
                this.down('#delete').setDisabled(!(
                    !record.get('used') &&
                    record.get('status') != 'delete' &&
                    (Scalr.user.type == 'ScalrAdmin' || record.get('envId') && Scalr.isAllowed('FARMS_IMAGES', 'manage'))
                ));
                this.down('#copy').setDisabled(
                    record.get('status') == 'delete' ||
                    record.get('platform') != 'ec2' ||
                    record.get('envId') == null ||
                    Scalr.user.type == 'ScalrAdmin' ||
                    !Scalr.isAllowed('FARMS_IMAGES', 'manage')
                );
                this.down('#addTo').setDisabled(record.get('status') != 'active');
                this.down('#edit').disable();
                frm.findField('name').setReadOnly(
                    !record.get('envId') &&
                    Scalr.user.type != 'ScalrAdmin' &&
                    !Scalr.isAllowed('FARMS_IMAGES', 'manage')
                );
                frm.findField('status')[record.get('status') != 'active' ? 'show' : 'hide']();
                frm.findField('used')[record.get('status') == 'active' ? 'show' : 'hide']();
                frm.findField('statusError')[record.get('status') == 'failed' ? 'show' : 'hide']();
                frm.findField('type')[record.get('platform') == 'ec2' ? 'show' : 'hide']();
            },
            loadrecord: function() {
                if (!this.isVisible()) {
                    this.show();
                }
            }
        },
        items: [{
            xtype: 'fieldset',
            itemId: 'main',
            style: 'padding-bottom: 8px'
        }, {
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            flex: 1,
            defaults: {
                labelWidth: 120
            },
            items: [{
                xtype: 'container',
                layout: 'hbox',
                margin: '0 0 6 0',
                items: [{
                    xtype: 'displayfield',
                    labelWidth: 120,
                    flex: 1,
                    name: 'platform',
                    fieldLabel: 'Platform'
                }, {
                    itemId: 'addTo',
                    xtype: 'splitbutton',
                    //height: 26,
                    width: 90,
                    text: 'Use',
                    cls: 'x-btn-default-small-green',
                    handler: function() {
                        this.maybeShowMenu();
                    },
                    menu: [{
                        text: 'in new Role',
                        handler: function() {
                            // redirect
                            Scalr.event.fireEvent('redirect', '#/roles/edit', false, {
                                image: this.up('form').getForm().getRecord().getData()
                            });
                        }
                    }, {
                        text: 'in existing Role',
                        handler: function () {
                            var data = this.up('form').getForm().getRecord().getData();
                            Scalr.Confirm({
                                formWidth: 950,
                                alignTop: true,
                                winConfig: {
                                    autoScroll: false
                                },
                                form: [{
                                    xtype: 'roleselect',
                                    image: data
                                }],
                                ok: 'Add',
                                disabled: true,
                                success: function (field, button) {
                                    Scalr.event.fireEvent('redirect', '#/roles/' + button.roleId + '/edit', false, {
                                        image: data
                                    });
                                }
                            });

                        }
                    }]
                }]
            }, {
                xtype: 'displayfield',
                name: 'cloudLocation',
                fieldLabel: 'Cloud Location',
                renderer: function (value) {
                    return value ? value : 'All locations';
                }
            }, {
                xtype: 'displayfield',
                name: 'id',
                fieldLabel: 'Image ID'
            }, {
                xtype: 'fieldcontainer',
                fieldLabel: 'Name',
                layout: 'hbox',
                items: [{
                    xtype: 'textfield',
                    vtype: 'rolename',
                    name: 'name',
                    flex: 1,
                    listeners: {
                        focus: function() {
                            this.up('form').down('#edit').setDisabled(this.readOnly);
                        },
                        blur: function() {
                            this.up('form').down('#edit').setDisabled(!this.isDirty());
                        }
                    }
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    value: 'Updating this Name in Scalr will not update the name of this image in your cloud'
                }]

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
            },{
                xtype: 'displayfield',
                name: 'software',
                fieldLabel: 'Software',
                renderer: function(value) {
                    return value ? value : 'Unknown';
                }
            },{
                xtype: 'displayfield',
                name: 'size',
                fieldLabel: 'Size',
                renderer: function (value) {
                    return value ? (value + ' Gb') : 'Unknown';
                }
            },{
                xtype: 'displayfield',
                name: 'type',
                fieldLabel: 'Type',
                renderer: function(value) {
                    return value ? value : 'Unknown';
                }
            },{
                xtype: 'displayfield',
                name: 'source',
                fieldLabel: 'Source',
                renderer: function(value) {
                    var record = this.up('form').getForm().getRecord();
                    if (value == 'BundleTask' && record) {
                        return '<a href="#/bundletasks/view?id=' + record.get('bundleTaskId') + '">BundleTask</a>';
                    } else {
                        return value;
                    }
                }
            },{
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
            }, {
                xtype: 'displayfield',
                name: 'used',
                renderer: function (value) {
                    var record = this.up('form').getForm().getRecord(),
                        used,
                        text;
                    if (record) {
                        used = record.get('used');
                        if (used) {
                            text = ['This <b>Image</b> is currently used by '];
                            if (used['rolesCount'] > 0) {
                                text.push('<a href="#/roles/manager?imageId=' + record.get('id') + '">' + (used['roleName'] ? used['roleName'] : (used['rolesCount'] + '&nbsp;Role(s)')) + '</a>');
                            }
                            if (used['serversCount'] > 0) {
                                text.push((used['rolesCount'] > 0 ? ' and ' : '') + '<a href="#/servers/view?imageId=' + record.get('id') + '">' + used['serversCount'] + '&nbsp;Server(s)</a>');
                            }
                            text = text.join('');
                        } else {
                            text = 'This <b>Image</b> is currently not used by any <b>Role</b> and <b>Server</b>.';
                        }

                    }
                    return text;
                }
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Status',
                hidden: true,
                name: 'status',
                renderer: function(value) {
                    if (value == 'delete')
                        return 'Deleting';
                    else if (value == 'failed')
                        return 'Failed';
                    else
                        return value;
                }
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Error',
                hidden: true,
                name: 'statusError',
                renderer: function (value) {
                    return Ext.htmlEncode(value);
                }
            }]
        }],
        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults: {
                flex: 1,
                maxWidth: 120
            },
            items: [{
                itemId: 'copy',
                xtype: 'button',
                text: 'Copy',
                tooltip: 'Copy to another EC2 region',
                handler: function() {
                    var record = this.up('form').getForm().getRecord();
                    Scalr.Request({
                        params: {
                            cloudLocation: record.get('cloudLocation'),
                            id: record.get('id')
                        },
                        processBox: {
                            msg: 'Copying image ...',
                            type: 'action'
                        },
                        url: '/images/xGetEc2MigrateDetails/',
                        success: function(data) {
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    msg: 'Use a image in a different EC2 region by copying it',
                                    formWidth: 600,
                                    form: [{
                                        xtype: 'fieldset',
                                        title: 'Copy image across regions',
                                        defaults: {
                                            anchor: '100%',
                                            labelWidth: 120
                                        },
                                        items: [{
                                            xtype: 'displayfield',
                                            fieldLabel: 'Image name',
                                            value: data['name']
                                        },{
                                            xtype: 'displayfield',
                                            fieldLabel: 'Source region',
                                            value: data['cloudLocation']
                                        }, {
                                            xtype: 'combo',
                                            fieldLabel: 'Destination region',
                                            store: {
                                                fields: [ 'cloudLocation', 'name' ],
                                                proxy: 'object',
                                                data: data['availableDestinations']
                                            },
                                            autoSetValue: true,
                                            valueField: 'cloudLocation',
                                            displayField: 'name',
                                            editable: false,
                                            queryMode: 'local',
                                            name: 'destinationRegion'
                                        }]
                                    }]
                                },
                                processBox: {
                                    type: 'action'
                                },
                                url: '/images/xEc2Migrate',
                                params: {
                                    id: record.get('id'),
                                    cloudLocation: record.get('cloudLocation')
                                },
                                success: function (data) {
                                    grid.down('filterfield').setValue('(id:' + data['id'] + ')').storeHandler();

                                }
                            });
                        }
                    });
                }
            }, {
                itemId: 'edit',
                xtype: 'button',
                text: 'Save',
                disabled: true,
                handler: function () {
                    var record = this.up('form').getForm().getRecord(), name = this.up('form').down('[name="name"]').getValue(), me = this;
                    Scalr.Request({
                        processBox: {
                            action: 'save'
                        },
                        url: '/images/xUpdateName',
                        params: {
                            id: record.get('id'),
                            platform: record.get('platform'),
                            cloudLocation: record.get('cloudLocation'),
                            name: name
                        },
                        success: function() {
                            record.set('name', name);
                            me.disable();
                        }
                    });
                }
            }, {
                itemId: 'delete',
                xtype: 'button',
                text: 'Delete',
                cls: 'x-btn-default-small-red',
                handler: function() {
                    var record = this.up('form').getForm().getRecord();

                    Scalr.Request({
                        confirmBox: {
                            msg: 'Delete "' + record.get('id') + '" image?',
                            type: 'delete',
                            formWidth: 440,
                            form: deleteConfirmationForm
                        },
                        params: {
                            images: Ext.encode([{
                                id: record.get('id'),
                                platform: record.get('platform'),
                                cloudLocation: record.get('cloudLocation')
                            }])
                        },
                        processBox: {
                            msg: 'Deleting image ...',
                            type: 'delete'
                        },
                        url: '/images/xRemove',
                        success: function() {
                            imagesStore.updateParamsAndLoad();
                        }
                    });
                }
            }]
        }]
    });

    var panel = Ext.create('Ext.panel.Panel', {
        title: 'Images &raquo; Manager',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        scalrOptions: {
            reload: false,
            maximize: 'all'
        },
        tools: [{
            xtype: 'favoritetool',
            favorite: {
                text: 'Images',
                href: '#/images/view'
            }
        }],
        items: [
            grid,
            {
                xtype: 'container',
                itemId: 'rightcol',
                flex: .4,
                minWidth: 400,
                maxWidth: 600,
                layout: 'fit',
                cls: 'x-transparent-mask',
                items: form
        }],
        listeners: {
            afterrender: function() {
                // temp fix: applyFilters should be called before updateParamsAndLoad
                grid.on('applyparams', function() {
                    imagesStore.updateParamsAndLoad();
                });
            }
        }
    });

    grid.relayEvents(panel, ['applyparams']);

    return panel;
});

Ext.define('Scalr.ui.ImagesViewRoleSelect', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.roleselect',

    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
    title: '',
    image: {},

    initComponent: function() {
        var me = this;

        me.title = 'Select role to add an image';
        if (me.image.osFamily && me.image.osVersion) {
            me.title = me.title + '<br><span style="font-size: 11px">Showing only Roles matching Image properties: ' + Scalr.utils.beautifyOsFamily(me.image.osFamily) + ' ' + me.image.osVersion+ '</span>';
        }

        me.callParent(arguments);

        var store = Ext.create('store.store', {
            fields: [
                {name: 'id', type: 'int'},
                'name', 'os', 'osName', 'osFamily', 'platforms', 'status', 'canAddImage'
            ],
            autoLoad: true,
            proxy: {
                type: 'scalr.paging',
                url: '/roles/xListRoles/',
                extraParams: {
                    addImage: Ext.encode(me.image),
                    scope: Scalr.user.type == 'ScalrAdmin' ? 'scalr' : 'env'
                }
            },
            pageSize: 15,
            remoteSort: true
        });

        me.add([{
            xtype: 'grid',
            cls: 'x-grid-shadow',
            store: store,

            plugins: {
                ptype: 'gridstore'
            },

            viewConfig: {
                focusedItemCls: 'no-focus',
                emptyText: "No roles found",
                deferEmptyText: false,
                loadingText: 'Loading roles ...'
            },

            columns: [
                { header: "", width: 38, dataIndex: 'canAddImage', sortable: false, xtype: 'templatecolumn', align: 'center', tpl:
                    '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-<tpl if="canAddImage">ok<tpl else>fail</tpl>"' +
                        'data-qtip="<tpl if="canAddImage">You can add this image<tpl else>You can\'t add this Image to this Role because it already has an Image configured for this Cloud Platform and Location.</tpl>"/>'
                },
                { text: "ID", width: 80, dataIndex: 'id'},
                { text: "Role name", flex: 1, dataIndex: 'name'},
                { text: "Clouds", flex: .5, minWidth: 110, dataIndex: 'platforms', sortable: false, xtype: 'templatecolumn', tpl:
                    '<tpl for="platforms">'+
                        '<img style="margin:0 3px"  class="x-icon-platform-small x-icon-platform-small-{.}" title="{[Scalr.utils.getPlatformName(values)]}" src="' + Ext.BLANK_IMAGE_URL + '"/>'+
                    '</tpl>'
                },
                { text: 'OS', flex: .7, minWidth: 160, dataIndex: 'os', align: 'left', sortable: true, hidden: !!(me.image.osFamily && me.image.osVersion), xtype: 'templatecolumn', tpl: '<img style="margin:0 3px"  class="x-icon-osfamily-small x-icon-osfamily-small-{osFamily}" src="' + Ext.BLANK_IMAGE_URL + '"/> {os}' },
                { text: "Status", width: 120, minWidth: 120, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'role' }
            ],

            dockedItems: [{
                xtype: 'scalrpagingtoolbar',
                itemId: 'paging',
                style: 'box-shadow:none;padding-left:0;padding-right:0',
                store: store,
                dock: 'top',
                calculatePageSize: false,
                beforeItems: [],
                items: [{
                    xtype: 'filterfield',
                    store: store
                }]
            }],

            listeners: {
                beforeselect: function(grid, record) {
                    if (! record.get('canAddImage')) {
                        me.enableAddButton();
                        return false;
                    }
                },

                selectionchange: function (selModel, selections) {
                    if (selections.length) {
                        var roleId = selections[0].get('id');

                        me.enableAddButton(roleId);
                        return;
                    }
                    me.enableAddButton();
                },

                itemdblclick: function(grid, record) {
                    if (record.get('canAddImage')) {
                        me.enableAddButton(record.get('id'));
                        me.up('#box').down('#buttonOk').handler();
                    }
                }
            }
        }]);
    },

    onBoxReady: function() {
        this.callParent();
        this.titleCmp.el.applyStyles('text-align: center; float: center');
    },

    enableAddButton: function (roleId) {
        var me = this;
        var button = me.up('#box').down('#buttonOk');

        button.setDisabled(!roleId);
        button.roleId = roleId;
    }
});
Scalr.regPage('Scalr.ui.roles.manager', function (loadParams, moduleParams) {
    var isScalrAdmin = Scalr.user.type === 'ScalrAdmin';
    var deleteConfirmationForm = {
        xtype: 'fieldset',
        cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
        hidden: isScalrAdmin,
        items: [{
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            anchor: '100%',
            value: 'Removing this Role will not remove the Images associated with it (neither in Scalr nor in your Cloud)'
        }]
    };

    //category tabs
    var categories = [{text: 'All categories', catId: 0}];
    categories.push.apply(categories, Ext.Array.map(moduleParams['categories'], function(item) {
        return {
            text: item.name,
            catId: item.id
        };
    }));

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

	var rolesStore = Ext.create('store.store', {
		fields: [
			{name: 'id', type: 'int'},
			{name: 'client_id', type: 'int'},
			'name', 'origin', 'behaviors', 'os', 'osFamily', 'platforms','used_servers','status',
            'images', 'description', 'usedBy'
		],
		proxy: {
			type: 'ajax',
			url: '/roles/xListRoles/',
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

    var resetFilterFields = function() {
        panel.getDockedComponent('tabs').resetTabs();
        grid.down('#filterfield').setValue(null).clearButton.hide();
        grid.down('#location').setPlatform();
        grid.down('#origin').setValue(null, true);
        grid.down('#platform').setValue(null, true);
        grid.down('#os').setValue(null, true);
    };

    var reconfigurePage = function(params) {
        params = params || {};
        cb = function(reconfigure){
            if (params['roleId']) {
                resetFilterFields();
                grid.down('#filterfield').setValue('(roleId:' + params['roleId'] + ')').clearButton.show();
                rolesStore.updateParamsAndLoad({roleId: params['roleId']}, true);
                rolesStore.on('prefetch', function(store, records) {
                        if (records.length) {
                            grid.getSelectionModel().setLastFocused(records[0]);
                        }
                    },
                    rolesStore,
                    {single: true}
                );
            } else if (params['chefServerId']) {
                resetFilterFields();
                grid.down('#filterfield').setValue('(chefServerId:' + params['chefServerId'] + ')').clearButton.show();
                rolesStore.updateParamsAndLoad({chefServerId: params['chefServerId']}, true);
            } else if (params['imageId']) {
                resetFilterFields();
                grid.down('#platform').setValue(params['platform'], true);
                grid.down('#location').suspendEvents();
                grid.down('#location').setValue(params['cloudLocation']);
                grid.down('#location').resumeEvents();
                grid.down('#filterfield').setValue('(imageId:' + params['imageId'] + ')').clearButton.show();
                rolesStore.updateParamsAndLoad({
                    platform: params['platform'],
                    cloudLocation: params['cloudLocation'],
                    imageId: params['imageId']
                }, true);
                rolesStore.on('prefetch', function(store, records) {
                        if (records.length) {
                            grid.getSelectionModel().setLastFocused(records[0]);
                        }
                    },
                    rolesStore,
                    {single: true}
                );

            } else if (rolesStore.isFilteredByRoleId() || !reconfigure) {
                resetFilterFields();
                rolesStore.updateParamsAndLoad({roleId: undefined}, true);
            }
        };
        if (grid.view.viewReady) {
            cb(true);
        } else {
            grid.view.on('viewready', function(){cb();}, grid.view, {single: true});
        }

    };

    var grid = Ext.create('Ext.grid.Panel', {
        xtype: 'grid',
        itemId: 'roles',
        flex: 1.2,
        cls: 'x-grid-shadow x-grid-shadow-buffered x-panel-column-left',
        store: rolesStore,
        padding: '0 0 12 0',
        maxWidth: 1000,
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
            emptyText: 'No roles found',
            deferEmptyText: false,
            loadMask: false
        },

        columns: [
            {
                header: '<img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qclass="x-tip-light" data-qtip="' +
                Ext.String.htmlEncode('<div>Scopes:</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr">&nbsp;&nbsp;Scalr</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-environment">&nbsp;&nbsp;Environment</div>') +
                '" />&nbsp;Role',
                dataIndex: 'name',
                sortable: true,
                flex: 2,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getScope(values.origin)]}&nbsp;&nbsp;{name}',
                    {
                        getScope: function(origin){
                            var scope = origin == 'SHARED' ? 'scalr' : 'environment';
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qtip="This Role is defined in the '+Ext.String.capitalize(scope)+' Scope"/>';
                        }
                    }
                )
            },
            { header: "Clouds", flex: .5, minWidth: 110, dataIndex: 'platforms', sortable: false, xtype: 'templatecolumn', tpl:
                '<tpl for="platforms">'+
                    '<img style="margin:0 3px"  class="x-icon-platform-small x-icon-platform-small-{.}" title="{[Scalr.utils.getPlatformName(values)]}" src="' + Ext.BLANK_IMAGE_URL + '"/>'+
                '</tpl>'
            },
            { header: 'OS', flex: .7, minWidth: 160, dataIndex: 'os', sortable: true, xtype: 'templatecolumn', tpl: '<img style="margin:0 3px"  class="x-icon-osfamily-small x-icon-osfamily-small-{osFamily}" src="' + Ext.BLANK_IMAGE_URL + '"/> {os}' },
            { header: "Status", maxWidth: 100, dataIndex: 'status', sortable: false, xtype: 'statuscolumn', statustype: 'role', resizable: false}
        ],

        multiSelect: true,
        selModel: {
            selType: 'selectedmodel',
            pruneRemoved: true,
            getVisibility: function(record) {
                return isScalrAdmin || record.get('origin') === 'CUSTOM';
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
            items: [{
                xtype: 'filterfield',
                itemId: 'filterfield',
                store: rolesStore,
                flex: 1,
                minWidth: 100,
                maxWidth: 200,
                margin: 0,
                form: {
                    items: [{
                        xtype: 'textfield',
                        name: 'imageId',
                        fieldLabel: 'Image ID',
                        labelAlign: 'top'
                    }]
                },
                separatedParams: ['roleId', 'chefServerId']
            },{
                xtype: 'cyclealt',
                itemId: 'platform',
                getItemIconCls: false,
                flex: 1,
                minWidth: 90,
                maxWidth: 110,
                hidden: platformFilterItems.length === 1,
                cls: 'x-btn-compressed',
                changeHandler: function(comp, item) {
                    comp.next('#location').setPlatform(item.value);
                    rolesStore.updateParamsAndLoad({platform: item.value, cloudLocation: undefined});
                },
                getItemText: function(item) {
                    return item.value ? 'Cloud: <img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '" />' : item.text;
                },
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: platformFilterItems
                }
            },{
                xtype: 'combo',
                itemId: 'location',
                matchFieldWidth: false,
                flex: 2,
                minWidth: 60,
                maxWidth: 120,
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
                        rolesStore.updateParamsAndLoad({cloudLocation: value});
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
            },{
                // TODO: replace scope within origin
                xtype: 'cyclealt',
                itemId: 'origin',
                getItemIconCls: false,
                flex: 1,
                minWidth: 100,
                maxWidth: 110,
                cls: 'x-btn-compressed',
                changeHandler: function(comp, item) {
                    rolesStore.updateParamsAndLoad({scope: item.value});
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
                    rolesStore.updateParamsAndLoad({osFamily: item.value});
                 },
                 getItemText: function(item) {
                     return item.value ? 'OS: <img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '"/>' : item.text;
                 },
                 menu: {
                     cls: 'x-menu-light x-menu-cycle-button-filter',
                     minWidth: 200,
                     items: osFilterItems
                 }
            },{
                xtype: 'tbfill',
                flex: .01
            },{
                text: 'Add role',
                margin: 0,
                cls: 'x-btn-green-bg',
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/roles/' + (isScalrAdmin ? 'edit' : 'create'));
                }
            },{
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                tooltip: 'Refresh',
                handler: function() {
                    rolesStore.updateParamsAndLoad();
                }
            },{
                itemId: 'delete',
                ui: 'paging',
                iconCls: 'x-tbar-delete',
                tooltip: 'Delete selected roles',
                disabled: true,
                handler: function() {
                    var request = {
                        confirmBox: {
                            msg: 'Remove selected role(s): %s ?',
                            type: 'delete',
                            formWidth: 440,
                            form: deleteConfirmationForm
                        },
                        processBox: {
                            msg: 'Removing selected role(s) ...',
                                type: 'delete'
                        },
                        url: '/roles/xRemove',
                        success: function() {
                            rolesStore.updateParamsAndLoad();
                        }
                    }, records = grid.getSelectionModel().getSelection(), roles = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        roles.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('name'));
                    }
                    request.params = { roles: Ext.encode(roles) };
                    Scalr.Request(request);
                }
            }]
        }]
    });

    var form = Ext.create('Ext.form.Panel', {
        hidden: true,
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        autoScroll: true,
        teamsGridCollapsed: false,
        listeners: {
            afterrender: function() {
                var me = this;
                grid.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                    if (newFocused) {
                        if (me.getRecord() !== newFocused) {
                            me.loadRoleInfo(newFocused);
                        }
                    } else {
                        me.setVisible(false);
                        me.getForm().reset(true);
                    }
                });
            },
            beforedestroy: function() {
                this.abortCurrentRequest();
            }
        },
        abortCurrentRequest: function() {
            if (this.currentRequest) {
                Ext.Ajax.abort(this.currentRequest);
                delete this.currentRequest;
            }
        },
        loadRoleInfo: function(record) {
            var me = this;
            me.abortCurrentRequest();
            me.up().mask('Loading...');
            me.hide();
            me.getForm()._record = record;
            if (!record.get('images')) {
                me.currentRequest = Scalr.Request({
                    url: '/roles/xGetInfo',
                    params: {roleId: record.get('id')},
                    success: function (data) {
                        delete me.currentRequest;
                        if (data['role']['id'] == me.getRecord().get('id')) {
                            me.getRecord().set(data['role']);
                            me.showRoleInfo();
                        }
                    }
                });
            } else {
                me.showRoleInfo();
            }
        },
        showRoleInfo: function() {
            var behaviors = [],
                images = [],
                usedBy = [],
                platformsAdded = {},
                record = this.getRecord();

            Ext.Array.each(record.get('images'), function(value){
                images.push(value);
                platformsAdded[value['platform']] = true;
            });

            if (!isScalrAdmin) {
                Ext.Object.each(Scalr.platforms, function(key, value) {
                    if (platformsAdded[key] === undefined && value.enabled) {
                        images.push({platform: key});
                    }
                });
            }
            
            Ext.Array.each(record.get('behaviors'), function(value){
                if (value !== 'base') {
                    behaviors.push('<span style="white-space:nowrap" data-qtip="' + Ext.htmlEncode(Scalr.utils.beautifyBehavior(value, true)) + '"><img class="x-icon-role-small x-icon-role-small-' + value + '" src="' + Ext.BLANK_IMAGE_URL + '" />&nbsp;' + Scalr.utils.beautifyBehavior(value) + '</span>');
                }
            });
            if (record.get('usedBy')) {
                Ext.Array.each(record.get('usedBy')['farms'], function(value) {
                    usedBy.push('<a href="#/farms/' + value['id'] + '/view">' + value['name'] + '</a>');
                });
            }
            this.down('#main').setTitle(record.get('name'), record.get('description') || '<i>description is empty</i>');
            this.down('[name="id"]').setValue(record.get('id'));
            this.down('[name="os"]').setValue('<img src="'+Ext.BLANK_IMAGE_URL+'" title="" class="x-icon-osfamily-small x-icon-osfamily-small-'+record.get('osFamily')+'" />&nbsp;' + record.get('os'));
            this.down('[name="behaviors"]').setValue(behaviors.length > 0 ? behaviors.join(',&nbsp;&nbsp; ') : '-');
            this.down('[name="usedBy"]').setValue(usedBy.length > 0 ? usedBy.join(', ') : '-').setFieldLabel(record.get('usedBy') ? ('Used by ' + record.get('usedBy')['cnt'] + ' farms') : 'Used by');
            this.down('#images').store.load({data: images});
            this.down('#addToFarm').setDisabled(record.get('images').length == 0);
            this.down('#edit').setDisabled(record.get('origin') !== 'CUSTOM' && !isScalrAdmin).setHref('#/roles/' + record.get('id') + '/edit');
            this.down('#clone').setDisabled(!Scalr.isAllowed('FARMS_ROLES', 'clone'));
            this.down('#delete').setDisabled(!isScalrAdmin && record.get('origin') !== 'CUSTOM');
            this.up().unmask();
            this.show();
        },
            items: [{
                xtype: 'fieldset',
                itemId: 'main',
                style: 'padding-bottom: 8px'
            },{
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
                        name: 'id',
                        fieldLabel: 'Role ID'
                    },{
                        itemId: 'addToFarm',
                        xtype: 'button',
                        height: 26,
                        text: 'Add to farm',
                        cls: 'x-btn-default-small-green',
                        hidden: isScalrAdmin,
                        handler: function() {
                            var me = this;

                            Scalr.Confirm({
                                formWidth: 950,
                                alignTop: true,
                                winConfig: {
                                    autoScroll: false
                                },
                                form: [{
                                    xtype: 'farmselect'
                                }],
                                ok: 'Add',
                                disabled: true,
                                success: function(field, button) {
                                    var record = me.up('form').getRecord();
                                    var farmId = button.farmId;
                                    var link = '#/farms/' + farmId + '/edit?roleId=' + record.get('id');

                                    Scalr.event.fireEvent('redirect', link);
                                }
                            });
                        }
                    }]
                },{
                    xtype: 'displayfield',
                    name: 'os',
                    fieldLabel: 'Operating system'
                },{
                    xtype: 'displayfield',
                    name: 'behaviors',
                    fieldLabel: 'Built-in automation'
                },{
                    xtype: 'displayfield',
                    fieldLabel: 'Used by',
                    name: 'usedBy',
                    margin: '0 0 11 0'
                },{
                    xtype: 'label',
                    text: 'Images:'
                },{
                    xtype: 'grid',
                    itemId: 'images',
                    cls: 'x-grid-shadow x-grid-no-highlighting',
                    margin: '12 0 0 0',
                    flex: 1,
                    //platforms: moduleParams['platforms'],
                    store: {
                        fields: [ 'platform', 'cloudLocation', 'imageId', 'extended'],
                        proxy: 'object'
                    },
                    viewConfig: {
                        emptyText: 'No images found',
                        deferEmptyText: false,
                        getRowClass: function (record) {
                            return record.get('imageId') ? '' : 'x-grid-row-disabled';
                        }
                    },
                    columns: [
                        { header: "Cloud Location", flex: 0.6, dataIndex: 'platform', sortable: true, renderer:
                            function(value, meta, record) {
                                var platform = record.get('platform'),
                                    cloudLocation = record.get('cloudLocation'),
                                    res = '';

                                res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                                if (record.get('imageId')) {
                                    if (platform === 'gce' || platform === 'ecs') {
                                        res += 'All regions';
                                    } else if (location) {
                                        if (Scalr.platforms[platform] && Scalr.platforms[platform]['locations'] && Scalr.platforms[platform]['locations'][cloudLocation]) {
                                            res += Scalr.platforms[platform]['locations'][cloudLocation];
                                        } else {
                                            res += cloudLocation;
                                        }
                                    }
                                } else {
                                    res += Scalr.utils.getPlatformName(platform) + '&nbsp;';
                                }
                                return res;
                            }
                        },
                        { header: "Image", flex: 1, dataIndex: 'cloudLocation', sortable: true, renderer:
                            function(value, meta, record) {
                                var res = '', extended = record.get('extended');
                                if (record.get('imageId')) {
                                    var qtip = [];
                                    if (extended['size'])
                                        qtip.push('Size: ' + extended['size'] + 'Gb');
                                    if (extended['software'])
                                        qtip.push('Software: ' + extended['software']);
                                    if (extended['type'] && (record.get('platform') == 'ec2'))
                                        qtip.push('Type: ' + extended['type']);
                                    if (qtip.length)
                                        qtip = ' data-qtip="' + Ext.htmlEncode(qtip.join('<br>')) + '"';
                                    else
                                        qtip = '';

                                    res = '<a href="#/images/view?platform=' + record.get('platform') + '&cloudLocation=' +
                                        record.get('cloudLocation') + '&id=' + record.get('imageId') + '"' + qtip + '>' + extended['name'] + '</a>';
                                } else {
                                    res = '<i>No image has been added for this cloud</i>';
                                }

                                return res;
                            }
                        }
                    ]
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
            defaults:{
                flex: 1,
                maxWidth: 150
            },
            items: [{
                itemId: 'edit',
                xtype: 'button',
                text: 'Edit',
                href: '#',
                hrefTarget: '_self'
            },{
                itemId: 'clone',
                xtype: 'button',
                text: 'Clone',
                handler: function() {
                    var record = this.up('form').getRecord();
                    Scalr.Request({
                        confirmBox: {
                            type: 'action',
                            formValidate: true,
                            form: {
                                xtype: 'fieldset',
                                cls: 'x-fieldset-separator-none',
                                items: [{
                                    xtype: 'textfield',
                                    fieldLabel: 'New role name',
                                    editable: false,
                                    queryMode: 'local',
                                    value: '',
                                    vtype: 'rolename',
                                    allowBlank: false,
                                    name: 'newRoleName',
                                    labelWidth: 100,
                                    anchor: '100%'
                                }]
                            },
                            msg: 'Clone "' + record.get('name') + '" role?'
                        },
                        processBox: {
                            type: 'action',
                            msg: 'Cloning role ...'
                        },
                        url: '/roles/xClone/',
                        params: { roleId: record.get('id') },
                        success: function (data) {
                            Scalr.message.Success("Role successfully cloned");
                            Scalr.event.fireEvent('redirect', '#/roles/manager?roleId=' + data.newRoleId);
                        }
                    });
                }
            },{
                itemId: 'delete',
                xtype: 'button',
                text: 'Delete',
                cls: 'x-btn-default-small-red',
                handler: function() {
                    var record = this.up('form').getRecord();
                    Scalr.Request({
                        confirmBox: {
                            msg: 'Delete "' + record.get('name') + '" role?',
                            type: 'delete',
                            formWidth: 440,
                            form: deleteConfirmationForm
                        },
                        params: {
                            roles: Ext.encode([record.get('id')])
                        },
                        processBox: {
                            msg: 'Deleting role ...',
                            type: 'delete'
                        },
                        url: '/roles/xRemove',
                        success: function() {
                            rolesStore.updateParamsAndLoad();
                        }
                    });
                }
            }]
        }]
    });

    var panel = Ext.create('Ext.panel.Panel', {
        title: 'Roles &raquo; Manager',
        cls: 'scalr-ui-roles-manager',
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
                text: 'Roles',
                href: '#/roles/manager'
            }
        }],
        listeners: {
            applyparams: reconfigurePage
        },
        items: [
            grid,
        {
            xtype: 'container',
            itemId: 'rightcol',
            flex: .6,
            layout: 'fit',
            cls: 'x-transparent-mask',
            items: form
        }],
        dockedItems: [{
            xtype: 'container',
            itemId: 'tabs',
            weight: 1,
            dock: 'left',
            cls: 'x-docked-tabs',
            width: 160,
            autoScroll: true,
            defaults: {
                xtype: 'button',
                ui: 'tab',
                textAlign: 'left',
                allowDepress: false,
                disableMouseDownPressed: true,
                toggleGroup: 'rolesmanager-tabs',
                toggleHandler: function(btn, pressed) {
                    if (pressed) {
                        rolesStore.updateParamsAndLoad({catId: btn.catId});
                    }
                }
            },
            resetTabs: function() {
                this.items.each(function(){
                    if (this.pressed) {
                        this.toggle(false, true);
                    }
                });
                this.items.first().toggle(true, true);
            },
            items: categories
        }]
    });

	Scalr.event.on('update', function (type, role) {
		if (type == '/roles/edit') {
            if (form) {
                var record = form.getRecord();
                if (record && record.get('id') == role['id']) {
                    record.set(role);
                    form.loadRoleInfo(record);
                }
            }
		}
	}, form);

    return panel;
});


Ext.define('Scalr.ui.RolesManagerFarmSelect', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.farmselect',

    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
    title: 'Select farm to add a role',

    initComponent: function() {
        var me = this;

        me.callParent(arguments);

        var store = Ext.create('store.store', {
            fields: [
                {name: 'id', type: 'int'},
                'name', 'created_by_email', 'roles', 'status'
            ],
            autoLoad: true,
            proxy: {
                type: 'scalr.paging',
                url: '/farms/xListFarms/'
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
                emptyText: "No farms found",
                deferEmptyText: false,
                loadingText: 'Loading farms ...'
            },

            columns: [
                {header: "ID", width: 80, dataIndex: 'id'},
                {header: "Farm name", flex: 1, dataIndex: 'name'},
                { text: "Owner", flex: 1, dataIndex: 'created_by_email', sortable: true },
                { text: "Roles", width: 70, dataIndex: 'roles', sortable: false, align:'center', xtype: 'templatecolumn',
                    tpl: '<a href="#/farms/{id}/roles">{roles}</a>'
                },
                { text: "Status", width: 120, minWidth: 120, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'farm'}
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
                selectionchange: function (selModel, selections) {
                    if (selections.length) {
                        var farmId = selections[0].get('id');

                        me.enableAddButton(farmId);
                        return;
                    }
                    me.enableAddButton();
                }
            }
        }]);
    },

    enableAddButton: function (farmId) {
        var me = this;
        var button = me.up('#box').down('#buttonOk');

        button.setDisabled(!farmId);
        button.farmId = farmId;
    }
});
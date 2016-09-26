Scalr.regPage('Scalr.ui.roles.manager', function (loadParams, moduleParams) {
    var isScalrAdmin = Scalr.user.type === 'ScalrAdmin',
        currentCatId = 0;
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

    getCategoriesItems = function(categories) {
        var items = [{text: 'All categories', catId: 0, pressed: currentCatId == 0}];
        Ext.Object.each(categories, function(id, cat) {
            items.push({
                text: cat.name,
                catId: cat.id,
                pressed: cat.id == currentCatId
            });
        });
        return items;
    }

    //clouds filter
    var platforms = [];
    Ext.Object.each(Scalr.platforms, function(key, value) {
        if (value.enabled) {
            platforms.push(key);
        }
    });

    //os filter
    var osFilterItems = [{
        text: 'All OS',
        value: null,
        iconCls: 'x-icon-osfamily-small'
    }];
    Ext.each(Scalr.utils.getOsFamilyList(true), function(family){
        osFilterItems.push({
            text: family.name,
            value: family.id,
            iconCls: 'x-icon-osfamily-small x-icon-osfamily-small-' + family.id
        });
    });

    var rolesStore = Ext.create('Scalr.ui.ContinuousStore', {
        fields: [
            {name: 'id', type: 'int'},
            'name', 'scope', 'behaviors', 'osId', 'dtAdded', 'dtLastUsed', 'platforms','status',
            'images', 'description', 'usedBy', 'isQuickStart', 'isDeprecated', 'isScalarized', 'accountId', 'envId', 'scope', 'environments'
        ],
        proxy: {
            type: 'ajax',
            url: '/roles/xListRoles/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },
        listeners: {
            beforeload: function() {
                var selModel = grid.getSelectionModel();
                selModel.deselectAll();
            }
        },
        isFilteredByRoleId: function() {
            return !!this.proxy.extraParams.roleId;
        }
    });

    var grid = Ext.create('Ext.grid.Panel', {
        xtype: 'grid',
        itemId: 'roles',
        cls: 'x-panel-column-left',
        store: rolesStore,
        flex: 1,
        maxWidth: 1000,

        plugins: [ 'focusedrowpointer', {
            ptype: 'applyparams',
            loadStoreOnReturn: false,
            hiddenParams: [ 'catId' ]
        }, {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            getForm: function() {
                return form;
            }
        }, {
            ptype: 'continuousrenderer'
        }],

        viewConfig: {
            emptyText: 'No roles found',
            //deferEmptyText: false,
            loadMask: false
        },

        columns: [
            {
                text: 'Role',
                dataIndex: 'name',
                sortable: true,
                flex: 2,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getScope(values)]}&nbsp;&nbsp;<span {[this.getNameStyle(values)]}>{name}</span>',
                    {
                        getScope: function(values) {
                            var scope = values.scope == 'account' && values.environments.length ? 'account-locked' : values.scope;
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('role') + '"/>';
                        },
                        getNameStyle: function(values) {
                            var message = values.scope == 'account' && values.environments.length ?
                                "Available on the following environments: <br>" + values.environments.map(function(value) { return '<b>' + value + '</b>'; }).join('<br>') :
                                '';

                            if (values.isQuickStart == 1) {
                                return 'style="color: green;' + (values.isDeprecated == 1 ? 'text-decoration: line-through;' : '') + '" data-qtip="This Role is being featured as a QuickStart Role.' + (message ? '<br><br>' + message : '') + '"';
                            } else if (values.isDeprecated == 1) {
                                return 'style="text-decoration: line-through;" data-qtip="This Role is being deprecated, and cannot be added to any Farm.' + (message ? '<br><br>' + message : '') + '"';
                            } else if (message) {
                                return 'data-qtip="' + message + '"';
                            }
                        }
                    }
                )
            },
            { text: "Clouds", width: 80, dataIndex: 'platforms', sortable: false, tdCls: 'x-grid-cell-nopadding', xtype: 'templatecolumn', tpl: new Ext.XTemplate(
                '<tpl if="platforms.length &gt; 2"><div data-qtip="{[this.getPlatforms(values.platforms)]}" data-qclass="x-tip-light" style="line-height: 21px"></tpl>' +

                '<tpl for="platforms">' +
                    '<tpl if="xindex &lt; 3"><img style="margin:0 3px 0"  class="x-icon-platform-small x-icon-platform-small-{.}" title="{[Scalr.utils.getPlatformName(values)]}" src="' + Ext.BLANK_IMAGE_URL + '"/></tpl>' +
                '</tpl>' +
                '<tpl if="platforms.length &gt; 2"><span style="font-size: 12px; color: #333;">+{platforms.length - 2}</span></div></tpl>',
                {
                    getPlatforms: function(platforms) {
                        return Ext.htmlEncode(Ext.Array.map(platforms || [], function(p) {
                            return '<img style="margin:0 3px 0"  class="x-icon-platform-small x-icon-platform-small-' + p + '" title="' + Scalr.utils.getPlatformName(p) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>';
                        }).join(''));
                    }
                })
            },{
                text: 'OS',
                flex: .7,
                minWidth: 160,
                dataIndex: 'os_id',
                sortable: true,
                xtype: 'templatecolumn',
                tpl: '{[this.getOsById(values.osId)]}'
            },{ header: "Status", width: 100, minWidth: 100, dataIndex: 'status', sortable: false, xtype: 'statuscolumn', statustype: 'role', resizable: false}
        ],

        selModel: {
            selType: 'selectedmodel',
            pruneRemoved: true,
            getVisibility: function(record) {
                return record.get('status') !== 'In use' && (
                       isScalrAdmin ||
                       record.get('accountId') && !record.get('envId') && Scalr.scope == 'account' && Scalr.isAllowed('ROLES_ACCOUNT', 'manage') ||
                       record.get('envId') && Scalr.scope == 'environment' && Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage')
                );
            }
        },

        listeners: {
            selectionchange: function(selModel, selections) {
                this.down('toolbar').down('#delete').setDisabled(!selections.length);
            }
        },
        dockedItems: [{
            xtype: 'toolbar',
            ui: 'simple',
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
                maxWidth: 300,
                margin: 0,
                form: {
                    items: [{
                        xtype: 'textfield',
                        name: 'imageId',
                        fieldLabel: 'Cloud Image ID',
                        labelAlign: 'top'
                    }, {
                        xtype: 'checkbox',
                        name: 'isQuickStart',
                        boxLabel: 'Quick Start Roles',
                        inputValue: true,
                        uncheckedValue: false
                    }, {
                        xtype: 'checkbox',
                        name: 'isDeprecated',
                        boxLabel: 'Deprecated Roles',
                        inputValue: true,
                        uncheckedValue: false
                    }, {
                        xtype: 'combobox',
                        name: 'status',
                        store: [['', ''], ['inUse', 'In use'], ['notUsed', 'Not used']],
                        editable: false,
                        labelAlign: 'top',
                        fieldLabel: 'Status'
                    }]
                },
                separatedParams: ['roleId', 'chefServerId']
            },{
                xtype: 'hiddenfield',
                name: 'catId',
                listeners: {
                    change: function (field, value) {
                        panel.
                            getDockedComponent('tabs').
                            setTab(value);
                    }
                }
            },{
                xtype: 'cloudlocationfield',
                cls: 'x-btn-compressed',
                platforms: platforms,
                forceAllLocations: true,
                listeners: {
                    change: function (me, value) {
                        rolesStore.applyProxyParams(value);
                    }
                }
            },{
                xtype: 'cyclealt',
                name: 'scope',
                getItemIconCls: false,
                flex: 1,
                minWidth: 100,
                maxWidth: 110,
                hidden: Scalr.scope == 'scalr',
                disabled: Scalr.scope == 'scalr',
                cls: 'x-btn-compressed',
                changeHandler: function (comp, item) {
                    rolesStore.applyProxyParams({
                        scope: item.value
                    });
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

            }, {
                 xtype: 'cyclealt',
                 itemId: 'osFamily',
                 name: 'osFamily',
                 flex: 1,
                 minWidth: 80,
                 maxWidth: 110,
                 getItemIconCls: false,
                 cls: 'x-btn-compressed',
                 hidden: osFilterItems.length === 2,
                 changeHandler: function (comp, item) {
                     rolesStore.applyProxyParams({
                         osFamily: item.value
                     });
                 },
                 getItemText: function(item) {
                     return item.value ? 'OS: &nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '"/>' : item.text;
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
                text: 'New role',
                margin: 0,
                cls: 'x-btn-green',
                disabled: Scalr.scope == 'account' && !Scalr.isAllowed('ROLES_ACCOUNT', 'manage') ||
                    Scalr.scope == 'environment' && !Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage'),
                handler: function() {
                    if (Scalr.scope !== 'environment') {
                        Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + '/roles/edit');
                        return;
                    }

                    var allowedActions = Ext.Array.filter(['manage', 'import', 'build'], function (action) {
                        return action !== 'manage' ? Scalr.isAllowed('IMAGES_ENVIRONMENT', action) : true;
                    });

                    if (allowedActions.length > 1) {
                        Scalr.event.fireEvent('redirect', '#/roles/create');
                        return;
                    }

                    Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + '/roles/' + {
                        'manage': 'edit',
                        'import': 'import',
                        'build': 'builder'
                    }[allowedActions[0]]);
                }
            },{
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    rolesStore.applyProxyParams();
                }
            },{
                itemId: 'delete',
                cls: 'x-btn-red',
                iconCls: 'x-btn-icon-delete',
                tooltip: 'Delete selected roles',
                disabled: true,
                handler: function() {
                    var request = {
                        confirmBox: {
                            msg: 'Remove selected role(s): %s ?',
                            type: 'delete',
                            formWidth: 480,
                            form: deleteConfirmationForm
                        },
                        processBox: {
                            msg: 'Removing selected role(s) ...',
                                type: 'delete'
                        },
                        url: '/roles/xRemove',
                        success: function (response) {
                            rolesStore.remove(Ext.Array.map(
                                response.processed, function (id) {
                                    return rolesStore.getById(id);
                                }
                            ));
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
        listeners: {
            beforedestroy: function() {
                this.abortCurrentRequest();
            },
            afterloadrecord: function(record) {
                var me = this;
                me.abortCurrentRequest();
                if (!record.get('images')) {
                    me.up().mask('');
                    me.hide();
                    me.currentRequest = Scalr.Request({
                        url: '/roles/xGetInfo',
                        params: {roleId: record.get('id')},
                        success: function (data) {
                            delete me.currentRequest;
                            if (data['role']['id'] == record.get('id')) {
                                record.set(data['role']);
                                me.afterLoadRecord(record);
                            }
                        }
                    });
                } else {
                    me.afterLoadRecord(record);
                }

            }

        },
        afterLoadRecord: function(record) {
            var frm = this.getForm(),
                images = [],
                platformsAdded = {},
                record = this.getRecord(),
                flags = [],
                os = Scalr.utils.getOsById(record.get('osId')),
                osDeprecated = os && os.status != 'active';

            if (record.get('isQuickStart') == 1) {
                flags.push('<span style="display: inline-block; border-radius: 2px; line-height: 10px; text-transform: uppercase; background-color: white; color: green; font-size: 10px; padding: 4px; font-family: OpenSansBold"' +
                'data-qtip="This Role is being featured as a QuickStart Role.">Quick Start</span>');
            }

            if (record.get('isDeprecated') == 1) {
                flags.push('<span style="display: inline-block; border-radius: 0; line-height: 10px; text-transform: uppercase; background-color: white; color: red; font-size: 10px; padding: 4px; font-family: OpenSansBold"' +
                'data-qtip="This Role is being deprecated, and cannot be added to any Farm.">Deprecated</span>');
            }

            if (osDeprecated) {
                flags.push('<span style="display: inline-block; border-radius: 0; line-height: 10px; text-transform: uppercase; background-color: white; color: red; font-size: 10px; padding: 4px; font-family: OpenSansBold"' +
                'data-qtip="' + os.name + ' is being deprecated and was disabled. It cannot be used in any Image, nor can any Role containing an Image of this OS be added to a Farm.">OS Deprecated</span>');
            }

            this.down('#main').setTitle(record.get('name'), (record.get('description') || '<i>description is empty</i>') + (flags.length ? '<div style="margin-top: 6px;">' + flags.join('&nbsp;') + '</div>' : ''));

            //images
            Ext.Array.each(record.get('images'), function(value){
                images.push(value);
                platformsAdded[value['platform']] = true;
            });

            this.down('#addToFarm').setDisabled(images.length == 0 || record.get('isDeprecated') == 1 || osDeprecated);
            this.down('#promoteRole').setDisabled(record.get('scope') !== 'environment');

            //add empty platforms
            if (!isScalrAdmin) {
                Ext.Object.each(Scalr.platforms, function(key, value) {
                    if (platformsAdded[key] === undefined && value.enabled) {
                        images.push({platform: key});
                    }
                });
            }
            this.down('#images').store.load({data: images});

            frm.findField('usedBy').setVisible(Scalr.scope === 'environment').setValue(record.get('usedBy')).setFieldLabel(record.get('usedBy') ? ('Used by ' + record.get('usedBy')['cnt'] + ' farms') : 'Used by');
            frm.findField('dtLastUsed').setVisible(record.get('scope') !== 'scalr' || Scalr.user.type === 'ScalrAdmin');
            frm.findField('environments').setVisible(record.get('environments').length);

            this.down('#edit').setDisabled(!(
                Scalr.scope == 'scalr' ||
                Scalr.scope == 'account' && record.get('scope') == 'account' && Scalr.isAllowed('ROLES_ACCOUNT', 'manage') ||
                Scalr.scope == 'environment' && record.get('scope') == 'environment' && Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage')
            )).setHref('#' + Scalr.utils.getUrlPrefix() + '/roles/' + record.get('id') + '/edit');

            this.down('#clone').setDisabled(
                Scalr.scope === 'environment' && !Scalr.isAllowed('ROLES_ENVIRONMENT', 'clone') ||
                Scalr.scope === 'account' && !Scalr.isAllowed('ROLES_ACCOUNT', 'clone')
            );

            this.down('#delete').setDisabled(
                record.get('status') == 'In use' || !(
                    Scalr.scope == 'scalr' ||
                    Scalr.scope == 'account' && record.get('scope') == 'account' && Scalr.isAllowed('ROLES_ACCOUNT', 'manage') ||
                    Scalr.scope == 'environment' && record.get('scope') == 'environment' && Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage')
                )
            );

            this.up().unmask();
            this.show();
        },

        abortCurrentRequest: function() {
            if (this.currentRequest) {
                Ext.Ajax.abort(this.currentRequest);
                delete this.currentRequest;
            }
        },
        items: [{
            xtype: 'fieldset',
            itemId: 'main',
            cls: 'x-fieldset-separator-none x-fieldset-no-text-transform',
            headerCls: 'x-fieldset-separator-bottom',
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            flex: 1,
            defaults: {
                labelWidth: 150
            },
            items: [{
                xtype: 'container',
                style: 'z-index:2;',
                items: [{
                    itemId: 'promoteRole',
                    xtype: 'button',
                    iconCls: 'x-btn-icon-replace',
                    tooltip: 'Promoto role to account scope',
                    //hidden: !(Scalr.isAllowed('ROLES_ACCOUNT', 'manage') && Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage')),
                    hidden: true,
                    style: 'position:absolute;right:118px',
                    handler: function() {
                        var record = this.up('form').getForm().getRecord();
                        Scalr.Request({
                            confirmBox: {
                                type: 'action',
                                msg: 'Are you sure want to move role to account scope? This cannot be undone. ' +
                                'Also we will move all images associated with role to account scope.'
                            },
                            processBox: {
                                type: 'action'
                            },
                            params: {
                                id: record.get('id')
                            },
                            url: '/roles/xPromote',
                            success: function(data) {
                                if (data.role) {
                                    record.set(data.role);
                                }
                            }
                        });
                    }
                }, {
                    itemId: 'addToFarm',
                    xtype: 'button',
                    text: 'Add to farm',
                    cls: 'x-btn-green',
                    hidden: Scalr.scope !== 'environment' || !(Scalr.isAllowed('FARMS', 'update') || Scalr.isAllowed('OWN_FARMS', 'update') || Scalr.isAllowed('TEAM_FARMS', 'update')),
                    style: 'position:absolute;right:0',
                    maxWidth: 120,
                    handler: function() {
                        var me = this;

                        Scalr.Confirm({
                            formWidth: 950,
                            alignTop: true,
                            winConfig: {
                                layout: 'fit',
                                scrollable: false
                            },
                            form: [{
                                xtype: 'farmselect'
                            }],
                            formLayout: 'fit',
                            ok: 'Add',
                            disabled: true,
                            success: function(field, button) {
                                Scalr.event.fireEvent('redirect', '#/farms/designer?farmId=' + button.farmId, false, {roleId: me.up('form').getRecord().get('id')});
                            }
                        });
                    }
                }]
            },{
                xtype: 'displayfield',
                name: 'id',
                fieldLabel: 'Role ID'
            },{
                xtype: 'displayfield',
                name: 'osId',
                fieldLabel: 'Operating system',
                renderer: function(value) {
                    var os = Scalr.utils.getOsById(value);
                    return os ? '<img src="'+Ext.BLANK_IMAGE_URL+'" title="" class="x-icon-osfamily-small x-icon-osfamily-small-'+os.family+'" />&nbsp;' + os.name : value;
                }
            }, {
                xtype: 'displayfield',
                name: 'behaviors',
                fieldLabel: 'Built-in automation',
                renderer: function (value) {
                    var html = [],
                        record = this.up('form').getRecord();
                    if (record && record.get('isScalarized') == 1) {
                        html.push('<img style="float:left;margin:0 8px 8px 0" class="x-icon-scalr-small" src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="Scalarizr (Scalr agent)" />');
                        Ext.Array.each(value, function (value) {
                            if (!Ext.isEmpty(value) && value !== 'base') {
                                html.push('<img style="float:left;margin:0 8px 8px 0" class="x-icon-role-small x-icon-role-small-' + value + '" src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Ext.htmlEncode(Scalr.utils.beautifyBehavior(value, true)) + '" />');
                            }
                        });
                    }
                    return html.length > 0 ? html.join(' ') : '&ndash;';
                }
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Used by',
                name: 'usedBy',
                renderer: function(value) {
                    var html = [];
                    if (value) {
                        Ext.Array.each(value['farms'], function(value) {
                            html.push('<a href="#/farms?farmId=' + value['id'] + '">' + value['name'] + '</a>');
                        });
                    }
                    return html.length > 0 ? html.join(', ') : '&ndash;';
                }
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Created on',
                name: 'dtAdded',
                renderer: function(value) {
                    return value || 'Unknown';
                }
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Last used on',
                name: 'dtLastUsed',
                renderer: function (value) {
                    return value || 'Unknown';
                }
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Permissions',
                name: 'environments',
                renderer: function(value) {
                    return "Available on " + value.length + " environment" + (value.length > 1 ? 's' : '');
                }
            }, {
                xtype: 'label',
                text: 'Images',
                cls: 'x-form-item-label-default'
            }, {
                xtype: 'grid',
                itemId: 'images',
                trackMouseOver: false,
                disableSelection: true,
                margin: '6 0 0 0',
                flex: 1,
                store: {
                    fields: [
                        'platform', 'cloudLocation', 'imageId', 'extended',
                        {
                            name: 'ordering',
                            convert: function(v, record){
                                return record.data.imageId ? record.data.platform + record.data.cloudLocation : null;
                            }
                        }
                    ],
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
                    { header: "Location", flex: 0.6, dataIndex: 'ordering', sortable: true, renderer:
                        function(value, meta, record) {
                            var platform = record.get('platform'),
                                platformName = Scalr.utils.getPlatformName(platform),
                                cloudLocation = record.get('cloudLocation'),
                                res = '';

                            res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" data-qtip="' + platformName + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                            if (record.get('imageId')) {
                                if (platform === 'gce' || platform === 'azure') {
                                    res += 'All locations';
                                } else if (location) {
                                    if (Scalr.platforms[platform] && Scalr.platforms[platform]['locations'] && Scalr.platforms[platform]['locations'][cloudLocation]) {
                                        res += Scalr.platforms[platform]['locations'][cloudLocation];
                                    } else {
                                        res += cloudLocation;
                                    }
                                }
                            } else {
                                res += platformName + '&nbsp;';
                            }
                            return res;
                        }
                    },
                    { header: "Image", flex: 1, dataIndex: 'imageId', sortable: false, renderer:
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

                                res = extended['hash'] ? ('<a href="#' + Scalr.utils.getUrlPrefix() + '/images?hash='+extended['hash']+'"' + qtip + '>' + extended['name'] + '</a>') : '<span style="color: red">Image not found</span>';
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
                            formWidth: 500,
                            form: {
                                xtype: 'fieldset',
                                cls: 'x-fieldset-separator-none',
                                items: [{
                                    xtype: 'textfield',
                                    fieldLabel: 'New role name',
                                    value: '',
                                    vtype: 'rolename',
                                    allowBlank: false,
                                    name: 'newRoleName',
                                    labelWidth: 120,
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
                            record = rolesStore.add(data.role)[0];
                            grid.view.focusRow(record);
                        }
                    });
                }
            },{
                itemId: 'delete',
                xtype: 'button',
                text: 'Delete',
                cls: 'x-btn-red',
                handler: function() {
                    var record = this.up('form').getRecord();
                    Scalr.Request({
                        confirmBox: {
                            msg: 'Delete "' + record.get('name') + '" role?',
                            type: 'delete',
                            formWidth: 480,
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
                        success: function(request) {
                            if (request.processed.length > 0) {
                                rolesStore.remove(record);
                            }
                        }
                    });
                }
            }]
        }]
    });

    var panel = Ext.create('Ext.panel.Panel', {
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        stateId: 'grid-roles-manager',
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuHref: '#' + Scalr.utils.getUrlPrefix() +  '/roles',
            menuTitle: 'Roles',
            menuFavorite: true
        },
        cls: 'scalr-ui-roles-manager',
        resetOn: 'roleId',
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            flex: .5,
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
            width: 170,
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
                        rolesStore.applyProxyParams({catId: btn.catId});
                        currentCatId = btn.catId;
                    }
                }
            },
            setTab: function (catId) {
                this.down('[catId=' + catId + ']').toggle(true);
                return this;
            },
            items: getCategoriesItems(moduleParams['categories'])
        }]
    });

    Scalr.event.on('update', function (type, role, categories) {
        if (type == '/roles/edit') {
            var record = rolesStore.getById(role.id);
            if (Ext.isEmpty(record)) {
                record = rolesStore.add(role)[0];
            } else {
                record.set(role);
                grid.clearSelectedRecord();
            }
            if (Ext.isObject(categories)) {
                var tabs = panel.down('#tabs');
                if (!categories[currentCatId]) {
                    currentCatId = 0;
                }
                tabs.suspendLayouts();
                tabs.removeAll();
                tabs.add(getCategoriesItems(categories));
                tabs.resumeLayouts(true);
            }
            Ext.defer(function(){grid.view.focusRow(record)}, 100);
        }
    }, form);

    return panel;
});


Ext.define('Scalr.ui.RolesManagerFarmSelect', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.farmselect',

    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
    title: 'Select farm to add a role',
    titleAlignCenter: true,
    layout: 'fit',

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
                url: '/farms/xListFarms/',
                extraParams: {
                    manageable: true
                }
            },
            pageSize: 15,
            remoteSort: true
        });

        me.add([{
            xtype: 'grid',
            store: store,
            autoScroll: true,

            plugins: {
                ptype: 'gridstore'
            },

            viewConfig: {
                emptyText: 'No farms found',
                deferEmptyText: false,
                loadingText: 'Loading farms ...'
            },

            columns: [
                {header: "ID", width: 80, dataIndex: 'id'},
                {header: "Farm name", flex: 1, dataIndex: 'name'},
                { text: "Owner", flex: 1, dataIndex: 'created_by_email', sortable: true },
                { text: "Roles", width: 70, dataIndex: 'roles', sortable: false, align:'center' },
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

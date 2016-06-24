Scalr.regPage('Scalr.ui.images.view', function (loadParams, moduleParams) {
    var countPlatformInRecords = function (platform, records) {
        var recordsWithPlatform = records.filter(function (record) {
            if (record.get('platform') === platform) {
                return record;
            }
        });

        return recordsWithPlatform.length;
    };

    var platforms = [];

    Ext.Object.each(Scalr.platforms, function(key, value) {
        if (value.enabled || (moduleParams['platforms'].indexOf(key) != -1)) {
            platforms.push(key);
        }
    });

    var deleteConfirmationForm = {
        xtype: 'fieldset',
        title: 'Removal parameters',
        hidden: Scalr.scope !== 'environment',
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
            anchor: '100%',
            value: 'The cloud image deletion process is asynchronous.<br/> If an error occurs, it will be reported here.',
            hidden: true
        }]
    };

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

    var imagesStore = Ext.create('Scalr.ui.ContinuousStore', {
        model: Ext.define(null, {
            extend: 'Ext.data.Model',
            idProperty: 'hash',
            fields: [
                'hash', 'id', 'platform', 'cloudLocation', 'name', 'osId', 'size',
                'architecture', 'source', 'type', 'createdByEmail', 'status', 'statusError', 'used', 'status', 'dtAdded', 'dtLastUsed', 'bundleTaskId',
                'accountId', 'envId', 'software', 'scope'
            ]
        }),
        sorters: [{
            property: 'dtAdded',
            direction: 'DESC'
        }],
       proxy: {
            type: 'ajax',
            url: '/images/xList/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },
        listeners: {
            load: function(store, records, successful) {
                if (successful && store.getCount() === 1) {
                    grid.setSelectedRecord(records[0]);
                }
            }
        }
    });

    var grid = Ext.create('Ext.grid.Panel', {
        xtype: 'grid',
        itemId: 'roles',
        flex: 1,
        cls: 'x-panel-column-left',
        store: imagesStore,
        plugins: [{
            ptype: 'applyparams',
            loadStoreOnReturn: false,
        },
            'focusedrowpointer',
        {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            getForm: function() {
                return form;
            }
        },{
            ptype: 'continuousrenderer'
        }],
        viewConfig: {
            emptyText: 'No images found'
        },

        columns: [{
                text: 'Image',
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
                text: 'Location',
                minWidth: 110,
                flex: 0.9,
                dataIndex: 'platform',
                sortable: true,
                renderer: function (value, meta, record) {
                    var platform = record.get('platform'), location = record.get('cloudLocation');
                    return '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" data-qtip="' + Scalr.utils.getPlatformName(platform) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;<span style="line-height: 18px;">' + (location ? location : 'All locations') + '</span>';
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
            {
                text: 'OS',
                flex: .8,
                dataIndex: 'osId',
                sortable: true,
                xtype: 'templatecolumn',
                tpl: '{[this.getOsById(values.osId)]}'
            },
            //{ header: 'Created by', dataIndex: 'createdByEmail', flex: 0.5, maxWidth: 150, sortable: false },
            { header: 'Created on', dataIndex: 'dtAdded', flex: 0.8, maxWidth: 170, sortable: true },
            { header: 'Status', width: 100, minWidth: 100, dataIndex: 'status', sortable: false, xtype: 'statuscolumn', statustype: 'image', resizable: false },

        ],

        selModel: {
            selType: 'selectedmodel',
            pruneRemoved: true,
            getVisibility: function(record) {
                return  !record.get('used') &&
                        record.get('status') != 'pending_delete' &&
                        (
                            Scalr.user.type == 'ScalrAdmin' ||
                            record.get('accountId') && !record.get('envId') && Scalr.scope == 'account' && Scalr.isAllowed('IMAGES_ACCOUNT', 'manage') ||
                            record.get('envId') && Scalr.scope == 'environment' && Scalr.isAllowed('IMAGES_ENVIRONMENT', 'manage')
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
            dock: 'top',
            ui: 'simple',
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
                        fieldLabel: 'Cloud Image ID',
                        labelAlign: 'top'
                    },{
                        xtype: 'textfield',
                        name: 'hash',
                        fieldLabel: 'Image ID',
                        labelAlign: 'top'
                    }]
                }
            }, {
                xtype: 'cloudlocationfield',
                cls: 'x-btn-compressed',
                platforms: platforms,
                forceAllLocations: true,
                listeners: {
                    change: function (me, value) {
                        imagesStore.applyProxyParams(value);
                    }
                }
            }, {
                xtype: 'cyclealt',
                itemId: 'scope',
                name: 'scope',
                getItemIconCls: false,
                flex: 1,
                minWidth: 100,
                maxWidth: 110,
                hidden: Scalr.user.type == 'ScalrAdmin',
                cls: 'x-btn-compressed',
                changeHandler: function (comp, item) {
                    imagesStore.applyProxyParams({
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
                    imagesStore.applyProxyParams({
                        osFamily: item.value
                    });
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
                text: 'New image',
                disabled: !(Scalr.user.type === 'ScalrAdmin'
                    || (Scalr.scope === 'account' && Scalr.isAllowed('IMAGES_ACCOUNT', 'manage'))
                    || (Scalr.scope === 'environment' && (Scalr.isAllowed('IMAGES_ENVIRONMENT', 'manage') || Scalr.isAllowed('IMAGES_ENVIRONMENT', 'import') || Scalr.isAllowed('IMAGES_ENVIRONMENT', 'build')))
                ),
                cls: 'x-btn-green',
                handler: function() {
                    if (Scalr.scope !== 'environment') {
                        Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + '/images/register');
                        return;
                    }

                    var allowedActions = Ext.Array.filter(['manage', 'import', 'build'], function (action) {
                        return Scalr.isAllowed('IMAGES_ENVIRONMENT', action);
                    });

                    if (allowedActions.length > 1) {
                        Scalr.event.fireEvent('redirect', '#/images/create');
                        return;
                    }

                    Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + {
                        'manage': '/images/register',
                        'import': '/roles/import?image',
                        'build': '/roles/builder?image'
                    }[allowedActions[0]]);
                }
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    imagesStore.applyProxyParams();
                }
            }, {
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more images to delete them',
                disabled: true,
                handler: function() {
                    var grid = this.up('grid'),
                        record = form.getRecord(),
                        records = grid.getSelectionModel().getSelection(),
                        amountOfAzure = countPlatformInRecords('azure', records),
                        isAzure = amountOfAzure > 0,
                        isAllAzure = amountOfAzure === records.length,
                        delForm = Ext.clone(deleteConfirmationForm),
                        data = [],
                        formWidth = 460;

                    if (isAzure && !isAllAzure) {
                        delForm.items[1].value = 'Remove image from cloud is not supported for Azure platform.<br/>' + delForm.items[1].value;
                        formWidth = 530;
                    }

                    var request = {
                        confirmBox: {
                            msg: 'Remove selected image(s): %s&nbsp;?',
                            type: 'delete',
                            formWidth: formWidth,
                            form: isAllAzure ? undefined : delForm
                        },
                        processBox: {
                            msg: 'Removing selected image(s) ...',
                            type: 'delete'
                        },
                        url: '/images/xRemove',
                        success: function (response) {
                            imagesStore.remove(Ext.Array.map(
                                response.processed, function (id) {
                                    return imagesStore.getById(id);
                                }
                            ));
                            Ext.each(response.pending, function(hash){
                                var image = imagesStore.getById(hash);
                                if (image) {
                                    image.set('status', 'pending_delete');
                                    image.commit(); // commit is needed to disable checkbox in selection

                                    if (record) {
                                        if (image.getId() === record.getId()) {
                                            form.loadRecord(image);
                                        }
                                    }
                                }
                            });
                            grid.getSelectionModel().deselectAll();
                        }
                    };

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('hash'));
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
        listeners: {
            beforeloadrecord: function (record) {
                this.down('[name=used]').imageId = record.get('id');
            },
            loadrecord: function(record) {
                var frm = this.getForm();

                this.down('#main').setTitle(record.get('name'));
                this.down('#delete').setDisabled(!(
                    !record.get('used') &&
                    record.get('status') != 'pending_delete' &&
                    (
                        Scalr.user.type == 'ScalrAdmin' ||
                        record.get('accountId') && !record.get('envId') && Scalr.scope == 'account' && Scalr.isAllowed('IMAGES_ACCOUNT', 'manage') ||
                        record.get('envId') && Scalr.scope == 'environment' && Scalr.isAllowed('IMAGES_ENVIRONMENT', 'manage')
                    )
                ));
                this.down('#copy').setDisabled(
                    record.get('status') == 'pending_delete' ||
                    record.get('platform') != 'ec2' ||
                    record.get('envId') == null ||
                    Scalr.user.type == 'ScalrAdmin' ||
                    !Scalr.isAllowed('IMAGES_ENVIRONMENT', 'manage')
                );
                var os = Scalr.utils.getOsById(record.get('osId')) || {};
                this.down('#addTo').setDisabled(record.get('status') != 'active' || os.status != 'active');
                this.down('#edit').disable();

                var nameReadOnly = record.get('status') == 'pending_delete' || record.get('scope') != Scalr.scope;
                if (Scalr.scope === 'environment') {
                    nameReadOnly = nameReadOnly ||  !record.get('envId') || !Scalr.isAllowed('IMAGES_ENVIRONMENT', 'manage');
                } else if (Scalr.scope === 'account') {
                    nameReadOnly = nameReadOnly || !record.get('accountId') || !Scalr.isAllowed('IMAGES_ACCOUNT', 'manage');
                }
                frm.findField('name').setReadOnly(nameReadOnly);

                frm.findField('status')[record.get('status') != 'active' ? 'show' : 'hide']();
                frm.findField('used')[record.get('status') == 'active' ? 'show' : 'hide']();
                frm.findField('statusError')[record.get('status') == 'failed' ? 'show' : 'hide']();
                frm.findField('type')[record.get('platform') == 'ec2' ? 'show' : 'hide']();
                frm.findField('dtLastUsed')[
                    Scalr.user.type == 'ScalrAdmin' && !record.get('accountId') || record.get('accountId') ?
                        'show' : 'hide']();
            }
        },
        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-text-transform',
            headerCls: 'x-fieldset-separator-bottom',
            itemId: 'main',
            defaults: {
                labelWidth: 140,
                anchor: '100%'
            },
            items: [{
                xtype: 'container',
                layout: 'hbox',
                margin: '0 0 6 0',
                items: [{
                    xtype: 'displayfield',
                    name: 'cloudLocation',
                    fieldLabel: 'Location',
                    labelWidth: 140,
                    flex: 1,
                    renderer: function (value) {
                        var record = form.getRecord();
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
                    itemId: 'addTo',
                    xtype: 'splitbutton',
                    width: 80,
                    text: 'Use',
                    cls: 'x-btn-green',
                    handler: function() {
                        this.maybeShowMenu();
                    },
                    menu: {
                        xtype: 'menu',
                        cls: 'x-topmenu-farms',
                        items: [{
                            text: 'in new Role',
                            handler: function() {
                                // redirect
                                Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + '/roles/edit', false, {
                                    image: this.up('form').getForm().getRecord().getData()
                                });
                            }
                        }, {
                            text: 'in existing Role',
                            handler: function () {
                                var data = this.up('form').getForm().getRecord().getData();
                                Scalr.Confirm({
                                    formWidth: 950,
                                    formLayout: 'fit',
                                    alignTop: true,
                                    winConfig: {
                                        autoScroll: false,
                                        layout: 'fit'
                                    },
                                    form: [{
                                        xtype: 'roleselect',
                                        image: data
                                    }],
                                    ok: 'Add',
                                    disabled: true,
                                    success: function (field, button) {
                                        Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + '/roles/' + button.roleId + '/edit', false, {
                                            image: data
                                        });
                                    }
                                });

                            }
                        }]
                    }
                }]
            }, {
                xtype: 'displayfield',
                name: 'id',
                fieldLabel: 'Cloud Image ID'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Name',
                vtype: 'rolename',
                name: 'name',
                allowBlank: false,
                flex: 1,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: {
                        id: 'info',
                        tooltip: 'Updating this Name in Scalr will not update the name of this image in your cloud'
                    }
                }],
                listeners: {
                    focus: function() {
                        this.up('form').down('#edit').setDisabled(this.readOnly);
                    },
                    blur: function() {
                        this.up('form').down('#edit').setDisabled(!this.isDirty());
                    }
                }
            }, {
                xtype: 'displayfield',
                name: 'architecture',
                fieldLabel: 'Architecture'
            }, {
                xtype: 'displayfield',
                name: 'osId',
                fieldLabel: 'Operating system',
                renderer: function(value) {
                    var os = Scalr.utils.getOsById(value);
                    return os ? '<img src="'+Ext.BLANK_IMAGE_URL+'" title="" class="x-icon-osfamily-small x-icon-osfamily-small-'+os.family+'" />&nbsp;' + os.name : value;
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
                        return '<a href="#/bundletasks?id=' + record.get('bundleTaskId') + '">BundleTask</a>';
                    } else {
                        return value;
                    }
                }
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
                name: 'dtLastUsed',
                fieldLabel: 'Last used on',
                renderer: function(value) {
                    return value ? value : 'Unknown';
                }
            }, {
                xtype: 'displayfield',
                name: 'used',
                fieldLabel: 'Image usage',
                listeners: {
                    beforerender: function (field) {
                        var currentScope = Scalr.scope;
                        var isEnvironment = currentScope === 'environment';

                        field.renderingParams = {
                            scopeIconMask: '<div style="float:left;"><img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="{0}" class="scalr-scope-{1}" style="margin:0 6px 0 0"/>',
                            maskWithoutLink: '<span style="margin:0 8px 0 0">{2}</span></div>',
                            maskWithLink: '<a href="#' + Scalr.utils.getUrlPrefix() + '/{3}?imageId={4}" style="margin:0 12px 0 0">{2}</a></div>',
                            isRolesLinked: currentScope === 'scalr'
                                || (isEnvironment && Scalr.isAllowed('ROLES_ENVIRONMENT'))
                                || (currentScope === 'account' && Scalr.isAllowed('ROLES_ACCOUNT')),
                            isServersLinked: isEnvironment && (Scalr.isAllowed('FARMS', 'servers')
                                || Scalr.isAllowed('TEAM_FARMS', 'servers')
                                || Scalr.isAllowed('OWN_FARMS', 'servers')
                            ),
                            currentScope: currentScope
                        };
                    }
                },
                renderer: function (values) {
                    var me = this;

                    if (Ext.isObject(values)) {
                        var params = me.renderingParams;
                        var stringValue = '';

                        Ext.Object.each(values, function (key, value, values) {
                            if (key === 'roleName' || value === 0) {
                                return;
                            }

                            var entityName = key !== 'serversEnvironment' ? 'Role' : 'Server';
                            var isRole = entityName === 'Role';
                            var scopeName = key.substring(isRole ? 5 : 7);
                            var scope = scopeName.toLowerCase();
                            var isLinkAvailable = scope === params.currentScope
                                && (isRole ? params.isRolesLinked : params.isServersLinked);
                            var mask = params.scopeIconMask + (!isLinkAvailable ? params.maskWithoutLink : params.maskWithLink);
                            var text = isRole && isLinkAvailable && value === 1
                                ? values.roleName
                                : value + ' ' + entityName + (value !== 1 ? 's' : '');

                            stringValue += Ext.String.format(mask, scopeName, scope, text, isRole ? 'roles' : 'servers', me.imageId);
                        });

                        return stringValue;
                    }

                    return '&mdash;';
                }
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Status',
                hidden: true,
                name: 'status',
                renderer: function(value) {
                    if (value == 'pending_delete')
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
                                        cls: 'x-fieldset-separator-none',
                                        defaults: {
                                            anchor: '100%',
                                            labelWidth: 140
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
                                    Scalr.event.fireEvent('redirect', '#/images?hash=' + data.hash);
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
                    if (this.up('form').down('[name="name"]').isValid())
                        Scalr.Request({
                            processBox: {
                                action: 'save'
                            },
                            url: '/images/xUpdateName',
                            params: {
                                hash: record.get('hash'),
                                name: name
                            },
                            success: function(data) {
                                record.set('name', data.name);
                                record.commit();
                                me.disable();
                            }
                        });
                }
            }, {
                itemId: 'delete',
                xtype: 'button',
                text: 'Delete',
                cls: 'x-btn-red',
                handler: function() {
                    var record = form.getRecord();

                    Scalr.Request({
                        confirmBox: {
                            msg: 'Delete "' + record.get('id') + '" image?',
                            type: 'delete',
                            formWidth: 460,
                            form: record.get('platform') === 'azure' ? undefined : deleteConfirmationForm
                        },
                        params: {
                            images: Ext.encode([record.get('hash')])
                        },
                        processBox: {
                            msg: 'Deleting image ...',
                            type: 'delete'
                        },
                        url: '/images/xRemove',
                        success: function (response) {
                            imagesStore.remove(Ext.Array.map(
                                response.processed, function (hash) {
                                    return imagesStore.getById(hash);
                                }
                            ));
                            Ext.each(response.pending, function(hash){
                                var image = imagesStore.getById(hash);
                                if (image) {
                                    image.set('status', 'pending_delete');
                                    image.commit(); // commit is needed to disable checkbox in selection

                                    if (image.getId() === record.getId()) {
                                        form.loadRecord(image);
                                    }
                                }
                            });
                            grid.getSelectionModel().deselectAll();
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
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Images',
            menuHref: '#' + Scalr.utils.getUrlPrefix() + '/images',
            menuFavorite: true
        },
        stateId: 'grid-images-view',
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            flex: .4,
            minWidth: 400,
            maxWidth: 600,
            layout: 'fit',
            cls: 'x-transparent-mask',
            items: form
        }]
    });

    Scalr.event.on('update', function (type, image) {
        if (type == '/images/create') {
            var record = imagesStore.getById(image.hash);
            if (Ext.isEmpty(record)) {
                record = imagesStore.add(image)[0];
            } else {
                record.set(image);
                grid.clearSelectedRecord();
            }
            Ext.defer(function(){grid.view.focusRow(record)}, 100);
        }
    }, panel);


    return panel;
});

Ext.define('Scalr.ui.ImagesViewRoleSelect', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.roleselect',

    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
    title: '',
    image: {},
    titleAlignCenter: true,
    layout: 'fit',

    initComponent: function() {
        var me = this,
            os = Scalr.utils.getOsById(me.image.osId) || {};

        me.title = 'Select role to add an image';

        me.callParent(arguments);

        var store = Ext.create('Scalr.ui.ContinuousStore', {
            fields: [
                {name: 'id', type: 'int'},
                'name', 'osId', 'platforms', 'status', 'canAddImage'
            ],
            autoLoad: true,
            proxy: {
                type: 'ajax',
                url: '/roles/xListRoles/',
                extraParams: {
                    addImage: Ext.encode(me.image),
                    scope: Scalr.scope
                },
                reader: {
                    type: 'json',
                    rootProperty: 'data',
                    totalProperty: 'total',
                    successProperty: 'success'
                }
            }
        });

        me.add([{
            xtype: 'grid',
            store: store,

            plugins: [{
                ptype: 'continuousrenderer'
            }],

            viewConfig: {
                focusedItemCls: 'no-focus',
                emptyText: "No roles found",
                deferEmptyText: false,
                loadingText: 'Loading roles ...',
                getRowClass: function (record, index, rowParams) {
                    var cls = [];
                    if (!record.get('canAddImage')) {
                        cls.push('x-grid-row-disabled');
                    }
                    return cls.join(',');
                }
            },

            columns: [
                { header: "", width: 38, dataIndex: 'canAddImage', sortable: false, hideable: false, xtype: 'templatecolumn', align: 'center', tpl:
                    '<div class="x-grid-icon x-grid-icon-simple x-grid-icon-<tpl if="canAddImage">ok<tpl else>fail</tpl>"' +
                        'data-qtip="<tpl if="canAddImage">You can add this image<tpl else>You can\'t add this Image to this Role because it already has an Image configured for this Cloud Platform and Location.</tpl>"</div>'
                },
                { text: "ID", width: 80, dataIndex: 'id'},
                { text: "Role name", flex: 1, dataIndex: 'name', xtype: 'templatecolumn', tpl: new Ext.XTemplate('<span {[this.getNameStyle(values)]}>{name}</span>', {
                    getNameStyle: function(values) {
                        if (values.isQuickStart == 1) {
                            return 'style="color: green;' + (values.isDeprecated == 1 ? 'text-decoration: line-through;' : '') + '" data-qtip="This Role is being featured as a QuickStart Role."';
                        } else if (values.isDeprecated == 1) {
                            return 'style="text-decoration: line-through;" data-qtip="This Role is being deprecated, and cannot be added to any Farm."';
                        }
                    }
                })},
                { text: "Clouds", flex: .5, minWidth: 110, dataIndex: 'platforms', sortable: false, xtype: 'templatecolumn', tpl:
                    '<tpl for="platforms">'+
                        '<img style="margin:0 3px 0"  class="x-icon-platform-small x-icon-platform-small-{.}" title="{[Scalr.utils.getPlatformName(values)]}" src="' + Ext.BLANK_IMAGE_URL + '"/>'+
                    '</tpl>'
                },
                {
                    text: 'OS',
                    flex: .7,
                    minWidth: 160,
                    dataIndex: 'osId',
                    align: 'left',
                    sortable: true,
                    hidden: !!me.image.osId,
                    xtype: 'templatecolumn',
                    tpl: '{[this.getOsById(values.osId)]}'
                },
                { text: "Status", width: 120, minWidth: 120, dataIndex: 'status', sortable: false, xtype: 'statuscolumn', statustype: 'role' }
            ],

            dockedItems: [{
                xtype: 'toolbar',
                itemId: 'paging',
                style: 'box-shadow:none;padding-left:0;padding-right:0',
                dock: 'top',
                defaults: {
                    margin: '0 0 0 12'
                },
                items: [{
                    xtype: 'filterfield',
                    margin: 0,
                    store: store
                }, {
                    xtype: 'button',
                    cls: 'x-btn-compressed',
                    pressed: true,
                    hidden: !os.id,
                    style: 'padding-left:6px;padding-right:8px',
                    text: '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-osfamily-small x-icon-osfamily-small-'+os.family+'"/>&nbsp;' + Scalr.utils.beautifyOsFamily(os.family) + ' ' + (os.version || os.generation || os.id),
                    disabled: true
                },{
                    xtype: 'button',
                    cls: 'x-btn-compressed',
                    pressed: true,
                    style: 'padding-left:6px;padding-right:8px',
                    hidden: me.image.isScalarized != 0 || me.image.hasCloudInit != 0,
                    text:  'No Scalarizr',
                    tooltip: 'Show Roles with no Scalarizr',
                    disabled: true
                }]
            }],

            listeners: {
                beforeselect: function(view, record) {
                    return record.get('canAddImage');
                },
                selectionchange: function (selModel, selections) {
                    if (selections.length) {
                        me.enableAddButton(selections[0].get('canAddImage') ? selections[0].get('id') : null);
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

    enableAddButton: function (roleId) {
        var me = this;
        var button = me.up('#box').down('#buttonOk');

        button.setDisabled(!roleId);
        button.roleId = roleId;
    }
});

Scalr.regPage('Scalr.ui.images.register', function (loadParams, moduleParams) {
    var isScopeEnvironment = Scalr.scope === 'environment';

    var platformsTabs = [];

    Ext.Object.each(Scalr.platforms, function (platformId, platformInfo) {
        if (platformInfo.enabled) {
            platformsTabs.push({
                text: Scalr.utils.getPlatformName(platformId, true),
                iconCls: 'x-icon-platform-large x-icon-platform-large-' + platformId,
                value: platformId
            });
        }
    });

    if (!platformsTabs.length) {
        Scalr.message.Error('Please <a href="#/account/environments?envId=' + Scalr.user.envId + '">configure cloud credentials</a> before adding images');
        return;
    }

    var panel = Ext.create('Ext.panel.Panel', {
        scalrOptions: {
            maximize: 'all',
            menuTitle: 'Images',
            menuHref: '#' + Scalr.utils.getUrlPrefix() + '/images',
            menuParentStateId: 'grid-images-view'
        },

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        items: [{
            xtype: 'container',
            itemId: 'leftColumn',
            cls: 'x-panel-column-left',
            autoScroll: true,
            width: 534,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                title: 'Select image',
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                items: [{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    margin: '10 0 10 0',
                    items: {
                        xtype: 'cloudlocationmap',
                        itemId: 'locationsMap',
                        platforms: Scalr.platforms,
                        size: 'large',
                        listeners: {
                            selectlocation: function (cloudLocation) {
                                panel.setCloudLocation(cloudLocation);
                            }
                        }
                    }
                }, {
                    xtype: 'combo',
                    fieldLabel: 'Cloud Location',
                    labelWidth: 115,
                    margin: '0 0 6 0',
                    itemId: 'cloudLocation',
                    editable: false,
                    valueField: 'id',
                    displayField: 'name',
                    queryMode: 'local',
                    store: {
                        fields: ['id', 'name']
                    },
                    plugins: [{
                        ptype: 'fieldinnericoncloud'
                    }, {
                        ptype: 'fieldicons',
                        icons: ['governance']
                    }],
                    listeners: {
                        change: function (field, value) {
                            panel.setLocationOnMap(value);
                            panel.validateCheckButton();
                        }
                    }
                }, {
                    xtype: 'textfield',
                    fieldLabel: 'Cloud Location',
                    labelWidth: 115,
                    itemId: 'cloudLocationText',
                    allowBlank: false,
                    hidden: true,
                    listeners: {
                        change: function (field, value) {
                            panel.validateCheckButton();
                        }
                    }
                }, {
                    xtype: 'textfield',
                    fieldLabel: 'Cloud Image ID',
                    itemId: 'cloudImageId',
                    labelWidth: 115,
                    allowBlank: false,
                    listeners: {
                        change: function (field, value) {
                            panel.validateCheckButton();
                        },
                        specialkey: function (field, e) {
                            if (e.getKey() === e.ENTER) {
                                panel.checkImage();
                            }
                        }
                    }
                }]
            }, {
                xtype: 'container',
                itemId: 'buttons',
                cls: 'x-docked-buttons',
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                defaults: {
                    xtype: 'button',
                    width: 140
                },
                items: [{
                    text: 'Next',
                    itemId: 'check',
                    disabled: true,
                    handler: function () {
                        panel.checkImage();
                    }
                }, {
                    text: 'Cancel',
                    handler: function () {
                        Scalr.event.fireEvent('close');
                    }
                }]
            }]
        }, {
            xtype: 'form',
            itemId: 'rightColumn',
            layout: 'anchor',
            autoScroll: true,
            hidden: true,
            flex: 1,
            fieldDefaults: {
                anchor: '100%',
                labelWidth: 140,
                maxWidth: 470
            },
            hideEc2Fields: function (isEc2, isType, isIops) {
                var me = this;

                var rootDeviceType = me.down('#rootDeviceType');
                rootDeviceType.setVisible(isEc2);

                Ext.Array.each(rootDeviceType.query(), function (field) {
                    field.setDisabled(Scalr.scope === 'environment');
                });

                me.down('[name=ec2VolumeType]')
                    .setVisible(isEc2 && isType);

                me.down('[name=ec2VolumeIops]')
                    .setVisible(isEc2 && isType && isIops);

                return me;
            },
            loadImageData: function (platform, imageData) {
                var me = this;

                me.getForm()
                    .setValues(imageData);

                var isEc2 = platform === 'ec2';

                me
                    .hideEc2Fields(isEc2, !Ext.isEmpty(imageData.ec2VolumeType), !Ext.isEmpty(imageData.ec2VolumeIops))
                    .show()
                    .down('[name=name]')
                        .focus();

                return me;
            },
            items: [{
                xtype: 'fieldset',
                title: 'Image details',
                items: [{
                    xtype: 'textfield',
                    fieldLabel: 'Name',
                    name: 'name',
                    allowBlank: false,
                    vtype: 'rolename',
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        position: 'outer',
                        icons: {
                            id: 'info',
                            tooltip: 'This name will only be used in Scalr, ' +
                            'and this Image\'s name in your Cloud will not be changed.'
                        }
                    }]
                }, {
                    xtype: 'displayfield',
                    fieldLabel: 'Description',
                    name: 'description',
                    submitValue: false,
                    renderer: function (value) {
                        return !Ext.isEmpty(value)
                            ? '<i>' + Ext.String.htmlEncode(value) + '</i>'
                            : '&mdash;';
                    }
                }, {
                    xtype: 'fieldcontainer',
                    itemId: 'os',
                    layout: 'hbox',
                    fieldLabel: 'OS',
                    items: [{
                        xtype: 'combo',
                        name: 'osFamily',
                        flex: 1,
                        displayField: 'name',
                        valueField: 'id',
                        editable: false,
                        allowBlank: false,
                        emptyText: 'Family',
                        submitValue: false,
                        plugins: {
                            ptype: 'fieldinnericon',
                            field: 'id',
                            iconClsPrefix: 'x-icon-osfamily-small x-icon-osfamily-small-'
                        },
                        store: {
                            fields: ['id', 'name'],
                            proxy: 'object',
                            data: Scalr.utils.getOsFamilyList()
                        },
                        listeners: {
                            change: function (comp, value) {
                                var osIdField = comp.next();
                                osIdField.store.load({data: value ? Scalr.utils.getOsList(value) : []});
                                osIdField.reset();
                            }
                        }
                    }, {
                        xtype: 'combo',
                        name: 'osId',
                        displayField: 'title',
                        valueField: 'id',
                        editable: false,
                        allowBlank: false,
                        flex: 0.6,
                        autoSetSingleValue: true,
                        emptyText: 'Version',
                        store: {
                            proxy: 'object',
                            fields: ['id', {
                                name: 'title',
                                convert: function (value, record) {
                                    return record.get('version')
                                        || record.get('generation')
                                        || record.getId();
                                }
                            }]
                        },
                        margin: '0 0 0 12',
                        listeners: {
                            beforequery: function () {
                                var osFamilyField = this.prev();
                                if (!osFamilyField.getValue()) {
                                    Scalr.message.InfoTip('Select OS family first.', osFamilyField.inputEl, {anchor: 'bottom'});
                                }
                            }
                        }
                    }]
                }, {
                    xtype: 'buttongroupfield',
                    fieldLabel: 'Architecture',
                    name: 'architecture',
                    disabled: isScopeEnvironment,
                    defaults: {
                        width: 130
                    },
                    items: [{
                        text: '32 bit',
                        value: 'i386'
                    }, {
                        text: '64 bit',
                        value: 'x86_64'
                    }],
                    value: 'x86_64'
                }, {
                    xtype: 'fieldcontainer',
                    fieldLabel: 'Root device type',
                    itemId: 'rootDeviceType',
                    cls: 'hideoncustomimage',
                    layout: 'hbox',
                    items: [{
                        xtype: 'buttongroupfield',
                        name: 'ec2Type',
                        disabled: isScopeEnvironment,
                        flex: 1,
                        value: 'ebs',
                        listeners: {
                            change: function (buttonGroupField, value) {
                                panel.disableHvmButton(
                                    value !== 'ebs'
                                );
                            }
                        },
                        defaults: {
                            width: 130
                        },
                        items: [{
                            text: 'EBS',
                            value: 'ebs'
                        }, {
                            text: 'Instance store',
                            value: 'instance-store'
                        }]
                    }, {
                        xtype: 'buttonfield',
                        text: 'HVM',
                        disabled: isScopeEnvironment,
                        itemId: 'hvm',
                        name: 'ec2Hvm',
                        enableToggle: true
                    }]
                }, {
                    xtype: 'displayfield',
                    fieldLabel: 'Volume type',
                    name: 'ec2VolumeType',
                    submitValue: false,
                    renderer: function (value) {
                        var values = {
                            'standard': 'Standard EBS (Magnetic)',
                            'gp2': 'General Purpose (SSD)',
                            'io1': 'Provisioned IOPS'
                        };

                        return !Ext.isEmpty(value) ? values[value] : '';
                    }
                }, {
                    xtype: 'displayfield',
                    fieldLabel: 'Volume Iops',
                    name: 'ec2VolumeIops',
                    submitValue: false

                }, isScopeEnvironment ? {
                    xtype: 'displayfield',
                    fieldLabel: 'Size',
                    name: 'size',
                    renderer: function (value) {
                        return !Ext.isEmpty(value)
                            ? value + ' Gb'
                            : '&mdash;';
                    }
                } : {
                    xtype: 'fieldcontainer',
                    layout: 'hbox',
                    items: [{
                        xtype: 'numberfield',
                        fieldLabel: 'Size',
                        name: 'size',
                        minValue: 1,
                        width: 255
                    }, {
                        xtype: 'label',
                        text: 'Gb',
                        cls: 'x-form-item-label-default',
                        margin: '0 0 0 6'
                    }]
                }]
            }, {
                xtype: 'fieldset',
                items: [{
                    xtype: 'buttongroupfield',
                    fieldLabel: 'Scalarizr installed',
                    submitValue: false,
                    defaults: {
                        width: 130
                    },
                    items: [{
                        text: 'Yes',
                        value: true
                    },{
                        text: 'No',
                        value: false
                    }],
                    value: false,
                    listeners: {
                        change: function (buttonGroupField, value) {
                            panel.disableRegisterButton(!value);
                        }
                    }
                }]
            }, {
                xtype: 'fieldset',
                title: 'Installed Software',
                items: [{
                    xtype: 'container',
                    margin: '16 0 10 -10',
                    itemId: 'software',
                    defaults: {
                        xtype: 'button',
                        ui: 'simple',
                        disableMouseDownPressed: true,
                        enableToggle: true,
                        cls: 'x-btn-simple-large',
                        iconAlign: 'top',
                        margin: '0 0 10 10',
                        tooltip: ''
                    },
                    items: [{
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-apache',
                        text: 'Apache',
                        behavior: 'apache'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-nginx',
                        text: 'Nginx',
                        behavior: 'nginx'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-haproxy',
                        text: 'Haproxy',
                        behavior: 'haproxy'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-mysql',
                        text: 'Mysql',
                        behavior: 'mysql'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-percona',
                        text: 'Percona',
                        behavior: 'percona'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-redis',
                        text: 'Redis',
                        behavior: 'redis'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-postgresql',
                        text: 'Postgresql',
                        behavior: 'postgresql'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-mongodb',
                        text: 'Mongodb',
                        behavior: 'mongodb'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-chef',
                        text: 'Chef',
                        behavior: 'chef'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-mariadb',
                        text: 'Mariadb',
                        behavior: 'mariadb'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-tomcat',
                        text: 'Tomcat',
                        behavior: 'tomcat'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-memcached',
                        text: 'Memcached',
                        behavior: 'memcached'
                    }, {
                        iconCls: 'x-icon-behavior-large x-icon-behavior-large-rabbitmq',
                        text: 'Rabbitmq',
                        behavior: 'rabbitmq'
                    }]
                }]
            }],

            dockedItems: [{
                xtype: 'container',
                itemId: 'buttons',
                cls: 'x-docked-buttons',
                dock: 'bottom',
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                defaults: {
                    xtype: 'button',
                    width: 140
                },
                items: [{
                    text: 'Register',
                    itemId: 'register',
                    disabled: true,
                    tooltip: 'The Scalr agent must be installed on this Image to continue.'
                        + ' If it isn\'t, please install it and try again.',
                    handler: function () {
                        panel.registerImage();
                    }
                }, {
                    text: 'Cancel',
                    handler: function () {
                        panel.discardImage();
                    }
                }]
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            itemId: 'platforms',
            dock: 'left',
            cls: 'x-docked-tabs',
            width: 110 + Ext.getScrollbarSize().width,
            overflowY: 'auto',
            defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'top',
                disableMouseDownPressed: true,
                toggleGroup: 'rolebuilder-tabs',
                cls: 'x-btn-tab-no-text-transform',
                toggleHandler: function (comp, state) {
                    if (state) {
                        panel.fireEvent('selectplatform', this.value);
                    }
                }
            },
            items: platformsTabs
        }],

        applyCloudLocations: function (platform, cloudLocations, editable) {
            var me = this;

            var locationsStoreData = [];
            var locationsIds = [];

            Ext.Object.each(cloudLocations, function (id, name) {
                locationsStoreData.push({
                    id: id,
                    name: name
                });

                locationsIds.push(id);
            });

            var locationsField = me.getCloudLocationField();
            locationsField.getPlugin('fieldinnericoncloud')
                .setPlatform(platform);

            var locationsStore = locationsField.getStore();
            locationsStore.removeAll();

            var cloudLocation = '';

            if (editable) {
                locationsField.hide();
                me.getCloudLocationTextField().show().setValue(cloudLocation);

            } else {
                locationsField.show();
                me.getCloudLocationTextField().hide();

                var isGce = platform === 'gce';

                if (isGce || platform === 'azure') {
                    locationsStore.loadData([{
                        id: 'all',
                        name: (isGce ? 'GCE' : 'Azure')
                        + ' images are automatically available in all regions.'
                    }]);
                    locationsField.setValue('all');
                    locationsField.disable();

                    cloudLocation = 'all';
                } else {
                    locationsStore.loadData(locationsStoreData);

                    var firstLocationsRecord = locationsStore.first();

                    cloudLocation = !Ext.isEmpty(firstLocationsRecord)
                        ? firstLocationsRecord.getId()
                        : '';

                    locationsField
                        .enable()
                        .setValue(cloudLocation);
                }
            }

            me.getCloudLocationsMap()
                .selectLocation(platform, cloudLocation, locationsIds, 'world');

            return me;
        },

        loadCloudLocations: function (platform, callback) {
            var me = this;

            if (Scalr.scope !== 'environment' && (Scalr.isOpenstack(platform) || Scalr.isCloudstack(platform) || platform == 'rackspace')) {
                me.applyCloudLocations(platform, [], true);
            } else {
                Scalr.loadCloudLocations(platform, function (cloudLocations) {
                    me.applyCloudLocations(platform, cloudLocations);
                });
            }

            return me;
        },

        getPlatform: function () {
            return this.platform;
        },

        getLeftColumn: function () {
            return this.down('#leftColumn');
        },

        getRightColumn: function () {
            return this.down('#rightColumn');
        },

        getCloudLocationField: function () {
            return this.getLeftColumn()
                .down('#cloudLocation');
        },

        getCloudLocationTextField: function () {
            return this.getLeftColumn()
                .down('#cloudLocationText');
        },

        getCloudLocation: function () {
            return this.getCloudLocationField().getValue();
        },

        setCloudLocation: function (cloudLocation) {
            var me = this;

            me.getCloudLocationField()
                .setValue(cloudLocation);

            return me;
        },

        getCloudLocationsMap: function () {
            return this.getLeftColumn()
                .down('#locationsMap');
        },

        setLocationOnMap: function (cloudLocation) {
            var me = this;

            me.getCloudLocationsMap()
                .setLocation(cloudLocation);

            return me;
        },

        getCloudImageIdField: function () {
            return this.getLeftColumn()
                .down('#cloudImageId');
        },

        getCloudImageId: function () {
            return this.getCloudImageIdField().getValue();
        },

        getLeftColumnsButtons: function () {
            return this.getLeftColumn()
                .down('#buttons');
        },

        validateCheckButton: function() {
            var imageId = this.getCloudImageId(),
                location = !this.getCloudLocationField().isVisible() ? this.getCloudLocationTextField().getValue() : this.getCloudLocation();

            this.disableCheckButton(
                Ext.isEmpty(imageId) || !imageId.trim() ||
                Ext.isEmpty(location) || !location.trim()
            );
        },

        disableCheckButton: function (disabled) {
            var me = this;

            me.getLeftColumnsButtons()
                .down('#check')
                    .setDisabled(disabled);

            return me;
        },

        disableLeftColumnsButtons: function (disabled) {
            var me = this;

            Ext.Array.each(
                me.getLeftColumnsButtons().query('button'),
                function (button) {
                    button.setDisabled(disabled);
                }
            );

            return me;
        },

        disableRegisterButton: function (disabled) {
            var me = this;

            me.getRightColumn()
                .down('#register')
                    .setDisabled(disabled)
                    .setTooltip(!disabled
                        ? ''
                        : 'The Scalr agent must be installed on this Image to continue. If it isn\'t, please install it and try again.'
                    );

            return me;
        },

        disableHvmButton: function (disabled) {
            var me = this;

            me.getRightColumn()
                .down('#rootDeviceType')
                    .down('#hvm')
                        .setDisabled(disabled)
                        .toggle(false);

            return me;
        },

        getEnabledSoftwareList: function () {
            var me = this;

            return Ext.Array.map(
                me.getRightColumn().down('#software').query('[pressed=true]'),
                function (button) {
                    return button.behavior;
                }
            );
        },

        checkImage: function () {
            var me = this;

            if (Scalr.scope == 'environment') {
                Scalr.Request({
                    processBox: {
                        type: 'action'
                    },
                    params: {
                        platform: me.getPlatform(),
                        cloudLocation: me.getCloudLocationField().isVisible() ? me.getCloudLocation() : me.getCloudLocationTextField().getValue(),
                        imageId: me.getCloudImageId()
                    },
                    url: '/images/xCheck',
                    success: function (response) {
                        me.acceptImage(response.data);
                    }
                });
            } else {
                me.acceptImage({});
            }

            return me;
        },

        acceptImage: function (imageData) {
            var me = this;

            me.getCloudLocationField()
                .setReadOnly(true);

            me.getCloudImageIdField()
                .setReadOnly(true);

            me.getCloudLocationTextField()
                .setReadOnly(true);

            me.disableLeftColumnsButtons(true);

            me.getRightColumn()
                .loadImageData(me.getPlatform(), imageData);

            return me;
        },

        discardImage: function () {
            var me = this;

            me.getRightColumn()
                .hide()
                .reset();

            me.disableLeftColumnsButtons(false);

            var cloudLocationTextField = me.getCloudLocationTextField(), textIsVisible = cloudLocationTextField.isVisible();

            me.getCloudLocationField().setReadOnly(false);
            cloudLocationTextField.setReadOnly(false);
            cloudLocationTextField.reset();
            if (textIsVisible) {
                cloudLocationTextField.focus();
            }

            me.disableCheckButton(true);

            var cloudImageIdField = me.getCloudImageIdField();
            cloudImageIdField.setReadOnly(false);
            cloudImageIdField.reset();
            if (!textIsVisible) {
                cloudImageIdField.focus();
            }

            return me;
        },

        registerImage: function () {
            var me = this;

            var form = me.getRightColumn();

            if (form.isValid()) {
                Scalr.Request({
                    processBox: {
                        type: 'action'
                    },
                    form: form.getForm(),
                    params: {
                        platform: me.getPlatform(),
                        cloudLocation: me.getCloudLocationField().isVisible() ? me.getCloudLocation() : me.getCloudLocationTextField().getValue(),
                        imageId: me.getCloudImageId(),
                        software: Ext.encode(me.getEnabledSoftwareList())
                    },
                    url: '/images/xSave',
                    success: function (response) {
                        Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + '/images?hash=' + response.hash);
                    }
                });
            }

            return me;
        },

        listeners: {
            selectplatform: function (platform) {
                var me = this;

                me.platform = platform;

                me.loadCloudLocations(platform);

                me.discardImage();

                return true;
            },
            boxready: function (panel) {
                panel.down('#platforms')
                    .child()
                        .toggle();
            }
        }
    });

    return panel;
});

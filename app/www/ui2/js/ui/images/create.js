Scalr.regPage('Scalr.ui.images.create', function (loadParams, moduleParams) {
    var platforms = {};
    Ext.Object.each(Scalr.platforms, function(key, value){
        if (value.enabled) {
            platforms[key] = Scalr.utils.getPlatformName(key);
        }
    });

	return Ext.create('Ext.form.Panel', {
        title: 'Choose a image creation method',
        width: 500,
        layout: 'anchor',

        scalrOptions: {
            modal: true
        },

        tools: [{
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent('close');
            }
        }],

        items: [{
            xtype: 'container',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults: {
                xtype: 'button',
                ui: 'simple',
                cls: 'x-btn-simple-large',
                margin: '20 0 20 20',
                iconAlign: 'top'
            },
            items: [{
                xtype: 'button',
                text: "Register\n" +
                    "existing image",
                itemId: 'newImage',
                enableToggle: true,
                iconCls: 'x-icon-behavior-large x-icon-behavior-large-mixed',
                tooltip: 'Register existing Image manually.',
                listeners: {
                    boxready: function() {
                        this.btnInnerEl.applyStyles('white-space: pre; padding: 0px;');

                        if ('register' in loadParams)
                            this.toggle(true);
                    },
                    toggle: function(btn, state) {
                        this.up().next('#add')[ state ? 'show' : 'hide']();
                    }
                }
            }, {
                xtype: 'button',
                text: "Image from\n" +
                    "non-Scalr server",
                href: '#/roles/import?image',
                hrefTarget: '_self',
                disabled: Scalr.user.type == 'ScalrAdmin',
                iconCls: 'x-icon-behavior-large x-icon-behavior-large-wizard',
                tooltip: 'Snapshot an existing Server that is not currently managed by Scalr, and use the snapshot as an Image.',
                listeners: {
                    boxready: function() {
                        this.btnInnerEl.applyStyles('white-space: pre; padding: 0px;');
                    }
                }
            }, {
                xtype: 'button',
                text: 'Image builder',
                href: '#/roles/builder?image',
                hrefTarget: '_self',
                disabled: Scalr.user.type == 'ScalrAdmin',
                iconCls: 'x-icon-behavior-large x-icon-behavior-large-rolebuilder',
                tooltip: 'Use the Role Builder wizard to bundle supported software into an Image.'
            }]
        }, {
            xtype: 'container',
            margin: 20,
            layout: 'anchor',
            itemId: 'add',
            hidden: true,
            defaults: {
                anchor: '100%',
                labelWidth: 110
            },
            showLocationAsText: function (platform) {
                return Scalr.user['type'] === 'ScalrAdmin' && (Scalr.isCloudstack(platform) || Scalr.isOpenstack(platform));
            },
            loadLocations: function(platform) {
                var locationComboField = this.down('[name="cloudLocation"]'),
                    locationTextField = this.down('[name="cloudLocationText"]');
                locationComboField.setDisabled(true).hide().reset();
                locationTextField.setDisabled(true).hide().reset();
                if (!platform) return;

                this.down('#buttons').show();

                if (platform !== 'gce' && platform !== 'ecs') {
                    if (this.showLocationAsText(platform)) {
                        locationTextField.setDisabled(false).show();
                    } else {
                        locationComboField.platform = platform;
                        locationComboField.locationsLoaded = false;
                        locationComboField.getStore().removeAll();
                        locationComboField.setDisabled(false).show();
                    }
                }
            },

            items: [{
                xtype: 'combo',
                fieldLabel: 'Cloud',
                emptyText: 'Please select cloud',
                store: {
                    fields: ['id', 'name'],
                    proxy: 'object',
                    data: platforms
                },
                valueField: 'id',
                displayField: 'name',
                allowBlank: false,
                editable: false,
                name: 'platform',
                queryMode: 'local',
                listeners: {
                    change: function (comp, value) {
                        var ct = this.up();
                        ct.loadLocations(value);
                        ct.down('[name="imageId"]')[value ? 'show' : 'hide']();
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
                name: 'cloudLocation',
                queryMode: 'local',
                matchFieldWidth: false,
                listeners: {
                    beforequery: function() {
                        var me = this;
                        me.collapse();
                        Scalr.loadCloudLocations(me.platform, function(data){
                            me.store.load({data: data});
                            me.locationsLoaded = true;
                            me.expand();
                        });
                        return false;
                    }
                }
            }, {
                xtype: 'textfield',
                fieldLabel: 'Location',
                hidden: true,
                disabled: true,
                allowBlank: false,
                name: 'cloudLocationText'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Cloud Image ID',
                hidden: true,
                allowBlank: false,
                name: 'imageId',
                listeners: {
                    specialkey: function(field, e) {
                        if (e.getKey() == e.ENTER) {
                            this.up().down('#action').handler();
                        }
                    }
                }
            }, {
                xtype: Scalr.user.type == 'ScalrAdmin' ? 'textfield' : 'displayfield',
                fieldLabel: 'Size',
                hidden: true,
                disabled: true,
                name: 'size'
            },
                Scalr.user.type == 'ScalrAdmin' ?
                {
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
                    hidden: true,
                    disabled: true
                } : {
                    xtype: 'displayfield',
                    name: 'architecture',
                    fieldLabel: 'Architecture',
                    hidden: true,
                    disabled: true
                }
            , {
                xtype: 'textfield',
                fieldLabel: 'Name',
                vtype: 'rolename',
                minLength: 3,
                allowBlank: false,
                disabled: true,
                flex: 1,
                name: 'name',
                hidden: true,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'left',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: 'This name will only be used in Scalr, ' +
                        'and this Image\'s name in your Cloud will not be changed.'
                    }
                }]
            }, {
                xtype: 'fieldcontainer',
                itemId: 'os',
                layout: 'hbox',
                disabled: true,
                hidden: true,
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
                        change: function(comp, value) {
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
                    flex: .6,
                    autoSetSingleValue: true,
                    emptyText: 'Version',
                    store: {
                        fields: ['id', {name: 'title', convert: function(v, record){return record.data.version || record.data.generation || record.data.id}}],
                        proxy: 'object'
                    },
                    margin: '0 0 0 12',
                    listeners: {
                        beforequery: function() {
                            var osFamilyField = this.prev();
                            if (!osFamilyField.getValue()) {
                                Scalr.message.InfoTip('Select OS family first.', osFamilyField.inputEl, {anchor: 'bottom'});
                            }
                        }
                    }
                }]
            }, {
                xtype: 'fieldset',
                title: 'Installed software',
                collapsible: true,
                collapsed: true,
                hidden: true,
                disabled: true,
                itemId: 'software',
                submitValue: false,
                cls: 'x-fieldset-separator-none',
                listeners: {
                    boxready: function() {
                        this.legend.el.applyStyles('padding-left: 0px; padding-right: 0px');
                        this.body.el.applyStyles('padding-left: 0px; padding-right: 0px');
                    }
                },
                items: [{
                    xtype: 'checkboxgroup',
                    columns: 3,
                    items: [{
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'apache',
                        boxLabel: 'Apache'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'nginx',
                        boxLabel: 'Nginx'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'haproxy',
                        boxLabel: 'Haproxy'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'mysql',
                        boxLabel: 'Mysql'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'percona',
                        boxLabel: 'Percona'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'redis',
                        boxLabel: 'Redis'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'postgresql',
                        boxLabel: 'Postgresql'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'mongodb',
                        boxLabel: 'Mongodb'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'chef',
                        boxLabel: 'Chef'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'mariadb',
                        boxLabel: 'Mariadb'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'tomcat',
                        boxLabel: 'Tomcat'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'memcached',
                        boxLabel: 'Memcached'
                    }, {
                        xtype: 'checkbox',
                        name: 'software[]',
                        inputValue: 'rabbitmq',
                        boxLabel: 'Rabbitmq'
                    }]
                }]
            }, {
                xtype: 'fieldcontainer',
                fieldLabel: 'Type',
                layout: 'hbox',
                itemId: 'ec2Type',
                hidden: true,
                disabled: true,
                items: [{
                    xtype: 'buttongroupfield',
                    name: 'ec2Type',
                    defaults: {
                        width: 140
                    },
                    items: [{
                        text: 'EBS',
                        value: 'ebs'
                    }, {
                        text: 'Instance-store',
                        value: 'instance-store'
                    }]
                }, {
                    xtype: 'checkbox',
                    boxLabel: 'HVM',
                    name: 'ec2Hvm',
                    width: 60,
                    margin: '0 0 0 12'
                }]
            }, {
                xtype: 'container',
                layout: 'hbox',
                itemId: 'buttons',
                hidden: true,
                items: [{
                    xtype: 'button',
                    flex: 1,
                    height: 32,
                    itemId: 'action',
                    text: Scalr.user.type == 'ScalrAdmin' ? 'Next' : 'Check',
                    handler: function() {
                        var form = this.up('form'),
                            me = this,
                            platform = form.down('[name="platform"]'),
                            cloudLocation = form.down('[name="cloudLocation"]'),
                            cloudLocationText = form.down('[name="cloudLocationText"]'),
                            imageId = form.down('[name="imageId"]');

                        if (form.getForm().isValid()) {
                            var values = form.getForm().getValues();
                            if (values['cloudLocationText']) {
                                values['cloudLocation'] = values['cloudLocationText'];
                            }

                            if (platform.readOnly) {
                                Scalr.Request({
                                    processBox: {
                                        type: 'action'
                                    },
                                    confirmBox: {
                                        type: 'action',
                                        ok: 'Continue',
                                        msg: 'Is Scalarizr installed on this Image? If yes, continue. If not, cancel, and choose an Image with Scalarizr installed on it.'
                                    },
                                    params: values,
                                    url: '/images/xSave',
                                    success: function(data) {
                                        Scalr.event.fireEvent('redirect', '#/images?hash=' + data.hash);
                                    }
                                });

                            } else {
                                var sfn = function(data) {
                                    data = data ? data['data'] : {};
                                    form.down('[name="platform"]').setReadOnly(true);
                                    form.down('[name="cloudLocation"]').setReadOnly(true);
                                    form.down('[name="imageId"]').setReadOnly(true);
                                    form.down('#os').show().enable();
                                    form.down('#software').show().enable();
                                    var arch = form.down('[name="architecture"]');
                                    arch.show().enable();
                                    if (data['architecture']) {
                                        arch.setValue(data['architecture']).setReadOnly(true);
                                    }

                                    form.down('[name="size"]').show().enable();
                                    if (Scalr.user.type != 'ScalrAdmin') {
                                        form.down('[name="size"]').setValue(data['size'] ? data['size'] + ' Gb' : '-');
                                    } else {
                                        if (values['platform'] == 'ec2') {
                                            form.down('#ec2Type').show().enable();
                                        }
                                    }

                                    form.down('[name="name"]').
                                        show().
                                        enable().
                                        setValue(data['name']);
                                    me.setText('Register');
                                };

                                if (Scalr.user.type == 'ScalrAdmin') {
                                    sfn();
                                } else {
                                    Scalr.Request({
                                        processBox: {
                                            type: 'action'
                                        },
                                        params: values,
                                        url: '/images/xCheck',
                                        success: sfn
                                    });
                                }
                            }
                        }
                    }
                }, {
                    xtype: 'button',
                    flex: 1,
                    height: 32,
                    margin: '0 0 0 12',
                    text: 'Cancel',
                    handler: function() {
                        var form = this.up('form');
                        form.getForm().reset();

                        form.down('#newImage').toggle(false);
                        form.down('[name="platform"]').setReadOnly(false);
                        form.down('[name="cloudLocation"]').setReadOnly(false);
                        form.down('[name="imageId"]').setReadOnly(false);
                        form.down('#os').hide().disable();
                        form.down('#software').hide().disable();
                        form.down('[name="name"]').hide().disable();
                        form.down('[name="size"]').hide().disable();
                        form.down('[name="architecture"]').hide().disable();
                        form.down('#ec2Type').hide().disable();

                        this.prev().setText('Check');
                        this.up('#buttons').hide();
                    }
                }]
            }]
        }]

    });
});

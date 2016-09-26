Scalr.regPage('Scalr.ui.roles.builder', function (loadParams, moduleParams) {
    var platforms = moduleParams.platforms || {},
        vpcLimits = Scalr.getGovernance('ec2', 'aws.vpc'),
        rootDeviceTypeFilterEnabled = true,
        chefFieldsetEnabled = true,
        advancedFieldsetEnabled = Scalr.flags['betaMode'],
        result = {},
        behaviors,
        buttons = {
            platforms: [],
            behaviors: [],
            addons: []
        };

    behaviors = [
        {name: 'mysql2', disable: {behavior: ['postgresql', 'redis', 'percona', 'mariadb'], os:[{family: 'centos', version: /^7/i}]}},
        {name: 'mariadb', disable: {behavior: ['postgresql', 'redis', 'percona','mysql2']}},
        {name: 'postgresql', disable: {platform: ['gce'], behavior: ['redis', 'percona', 'mysql2', 'mariadb']}},
        {name: 'percona', disable: {behavior: ['postgresql', 'redis', 'mysql2', 'mariadb']}},
        {name: 'app', disable: {behavior:['www', 'tomcat']}},
        {name: 'tomcat', disable: {behavior:['app'], os:['oel', {family: 'ubuntu', version: ['10.04']}]}},
        {name: 'haproxy', disable: {behavior:['www']}},
        {name: 'www', disable: {behavior:['app', 'haproxy']}},
        {name: 'memcached'},
        {name: 'redis', disable: {behavior: ['postgresql', 'percona', 'mysql2', 'mariadb']}},
        {name: 'rabbitmq', disable: {os: ['rhel', 'oel', 'redhat']}},
        {name: 'chef', addon: true, button: {pressed: true, toggle: Ext.emptyFn}}
    ];

    //behaviors buttons
    for (var i=0, len=behaviors.length; i<len; i++) {
        var button = {
            iconCls: 'x-icon-behavior-large x-icon-behavior-large-' + behaviors[i].name,
            text: Scalr.utils.beautifyBehavior(behaviors[i].name),
            behavior: behaviors[i].name
        };
        if (behaviors[i].button) {
            Ext.apply(button, behaviors[i].button);
        }
        buttons[behaviors[i].addon ? 'addons' : 'behaviors'].push(button);
    }

    //platform buttons
    for (var i in platforms) {
        buttons['platforms'].push({
            text: Scalr.utils.getPlatformName(i, true),
            iconCls: 'x-icon-platform-large x-icon-platform-large-' + i,
            value: i
        });
    }

    var toggleBehaviors = function(enable) {
        panel.down('#settings-behaviors').items.each(function(){
            this[enable?'enable':'disable']();
            this.setTooltip(enable?'':'Please select operating system.');
        });
        panel.down('#settings-addons').items.each(function(){
            if (this.xtype === 'button') {
                this[enable?'enable':'disable']();
                this.setTooltip(enable?'':'Please select operating system.');
            }
        });
    };

    var onSelectBehavior = function() {
        if (this.pressed) {
            result.behaviors.push(this.behavior);
        } else {
            Ext.Array.remove(result.behaviors, this.behavior);
        }
        panel.refreshBehaviors();
        refreshSoftwareWarning();
    };

    var onSelectAddon = function() {
        if (this.pressed) {
            result.addons.push(this.behavior);
        } else {
            Ext.Array.remove(result.addons, this.behavior);
        }
        if (this.behavior == 'chef') {
            panel.down('#chefsettings')[this.pressed?'show':'hide']();
        }
        refreshSoftwareWarning();
    };

    var refreshSoftwareWarning = function() {
        panel.down('#softwarewarning').setVisible(result.behaviors.length > 0 || result.addons.length > 1);
    };

    var panel = Ext.create('Ext.panel.Panel', {
        scalrOptions: {
            maximize: 'all',
            menuTitle: 'Roles',
            menuHref: '#/roles',
            menuParentStateId: 'grid-roles-manager'
        },
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        plugins: {
            ptype: 'localcachedrequest',
            crscope: 'rolebuilder'
        },

        items:[{
            xtype: 'container',
            cls: 'x-panel-column-left',
            autoScroll: true,
            width: 534,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'fieldset',
                itemId: 'leftcol',
                cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                title: 'Location and operating system',
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                items: [{
                    xtype: 'label',
                    text: 'Cloud Location',
                    cls: 'x-form-item-label-default'
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    margin: '0 0 10 0',
                    items: {
                        xtype: 'cloudlocationmap',
                        itemId: 'locationmap',
                        platforms: Scalr.platforms,
                        size: 'large',
                        listeners: {
                            beforeselectlocation: function() {
                                return !panel.serverId;
                            },
                            selectlocation: function(location){
                                panel.down('#cloudLocation').setValue(location);
                            }
                        }
                    }
                },{
                    xtype: 'combo',
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
                        change: function(comp, value) {
                            if (value) {
                                panel.fireEvent('selectlocation', value);
                                var locations = [];
                                this.store.data.each(function(rec){
                                    locations.push(rec.get('id'));
                                });
                                panel.down('#locationmap').selectLocation(result.platform, result.cloudLocation, locations, 'world');
                                if (result.platform === 'ec2' && loadParams['vpc'] == 1) {
                                    var vpcIdField = comp.next('#vpcId'),
                                        vpcIdFieldProxy = vpcIdField.store.getProxy(),
                                        disableAddNew = false;

                                    vpcIdField.reset();
                                    vpcIdField.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + value;
                                    vpcIdFieldProxy.params = {cloudLocation: value};
                                    delete vpcIdFieldProxy.filterFn;
                                    if (vpcLimits && vpcLimits['regions'] && vpcLimits['regions'][value]) {
                                        if (vpcLimits['regions'][value]['ids'] && vpcLimits['regions'][value]['ids'].length > 0) {
                                            vpcIdFieldProxy.filterFn = function(record) {
                                                return Ext.Array.contains(vpcLimits['regions'][value]['ids'], record.get('id'));
                                            };
                                            //vpcIdField.setValue(vpcLimits['regions'][value]['ids'][0]);
                                            disableAddNew = true;
                                        }
                                    }
                                    vpcIdField.getPlugin('comboaddnew').setDisabled(disableAddNew);
                                }
                            }
                        }
                    }
                },{
                    xtype: 'combo',
                    flex: 1,
                    name: 'vpcId',
                    itemId: 'vpcId',
                    emptyText: vpcLimits && vpcLimits['value'] == 1 ? 'Select VPC ID' : 'Do not use VPC',
                    allowBlank: !vpcLimits || vpcLimits['value'] != 1,
                    editable: false,

                    queryCaching: false,
                    clearDataBeforeQuery: true,
                    store: {
                        fields: ['id', 'name' ],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'rolebuilder',
                            url: '/platforms/ec2/xGetVpcList',
                            root: 'vpc',
                            prependData: !vpcLimits || vpcLimits['value'] != 1 ? [{id: 0, name: 'Do not use VPC'}] : null
                        }
                    },
                    valueField: 'id',
                    displayField: 'name',
                    plugins: [{
                        ptype: 'fieldicons',
                        icons: ['governance']
                    },{
                        ptype: 'comboaddnew',
                        pluginId: 'comboaddnew',
                        url: '/tools/aws/vpc/create',
                        applyNewValue: true
                    }],
                    hidden: loadParams['vpc'] != 1,
                    listeners: {
                        addnew: function(item) {
                            Scalr.CachedRequestManager.get('rolebuilder').setExpired({
                                url: '/platforms/ec2/xGetVpcList',
                                params: this.store.proxy.params
                            });
                        },
                        change: function(field, value) {
                            if (loadParams['vpc'] == 1) {
                                var vpcSubnetField = field.next('#vpcSubnetId');
                                vpcSubnetField.reset();
                                vpcSubnetField.setVisible(!!value);
                                vpcSubnetField.store.proxy.params = {
                                    cloudLocation: result.cloudLocation,
                                    vpcId: value,
                                    extended: 1
                                };
                                vpcSubnetField.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + result.cloudLocation + '&vpcId=' + value;
                                vpcSubnetField.getPlugin('comboaddnew').setDisabled(vpcLimits && vpcLimits['ids'] && Ext.isArray(vpcLimits['ids'][value]));

                            }
                        }
                    }
                },{
                    xtype: 'vpcsubnetfield',
                    name: 'vpcSubnetId',
                    itemId: 'vpcSubnetId',
                    emptyText: 'Select VPC subnet',
                    allowBlank: false,
                    hidden: true,
                    maxCount: 1
                },{
                    xtype: 'buttongroupfield',
                    itemId: 'architecture',
                    fieldLabel: 'Architecture',
                    cls: 'hideoncustomimage',
                    margin: '10 0 0 0',
                    labelWidth: 120,
                    listeners: {
                        change: function(comp, value) {
                            panel.fireEvent('selectarchitecture', value);
                        }
                    },
                    defaults: {
                        width: 140
                    },
                    items: [{
                        text: '64 bit',
                        value: 'x86_64'
                    },{
                        text: '32 bit',
                        value: 'i386'
                    }]
                },{
                    xtype: 'container',
                    itemId: 'rootdevicetypewrap',
                    cls: 'hideoncustomimage',
                    hidden: true,
                    layout: 'hbox',
                    margin: '10 0 4 0',
                    items: [{
                        xtype: 'buttongroupfield',
                        itemId: 'root_device_type',
                        fieldLabel: 'Root device type',
                        labelWidth: 120,
                        width: 412,
                        listeners: {
                            change: function(comp, value) {
                                panel.fireEvent('selectrootdevicetype', value);
                            }
                        },
                        defaults: {
                            width: 140
                        },
                        items: [{
                            text: 'EBS',
                            value: 'ebs'
                        },{
                            text: 'Instance store',
                            value: 'instance-store'
                        }]
                    },{
                        xtype: 'button',
                        itemId: 'hvm',
                        enableToggle: true,
                        text: 'HVM',
                        toggleHandler: function(){
                            panel.fireEvent('selecthvm', this.pressed);
                        }
                    }]
                },{
                    xtype: 'label',
                    text: 'Image ID:',
                    margin: '12 0 16 0',
                    cls: 'showoncustomimage'
                },{
                    xtype: 'textfield',
                    itemId: 'imageId',
                    cls: 'showoncustomimage',
                    allowBlank: false
                },{
                    xtype: 'label',
                    text: 'Operating system',
                    margin: '10 0 0 0',
                    cls: 'x-form-item-label-default hideoncustomimage'
                }]
            },{
                xtype: 'container',
                itemId: 'images',
                cls: 'hideoncustomimage',
                margin: '0 0 0 18'
            }]
        }, {
            xtype: 'form',
            itemId: 'rightcol',
            layout: 'anchor',
            autoScroll: true,
            flex: 1,
            items: [{
                xtype: 'fieldset',
                title: 'General info and software',
                defaults: {
                    anchor: '100%'
                },
                items: [{
                    xtype: 'textfield',
                    fieldLabel: 'Name',
                    maxWidth: 536,
                    itemId: 'rolename',
                    submitValue: false,
                    allowBlank: false,
                    vtype: 'rolename'
                },{
                    xtype: 'checkbox',
                    boxLabel: 'Only create an Image, do not create a Role using that Image',
                    readOnly: !Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage'),
                    checked: !Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage'),
                    itemId: 'roleimage'
                },{
                    xtype: 'label',
                    text: 'Software',
                    cls: 'x-form-item-label-default'
                },{
                    xtype: 'container',
                    margin: '16 0 10 -10',
                    itemId: 'settings-behaviors',
                    defaults: {
                        xtype: 'button',
                        ui: 'simple',
                        disabled: true,
                        disableMouseDownPressed: true,
                        enableToggle: true,
                        cls: 'x-btn-simple-large',
                        iconAlign: 'top',
                        margin: '0 0 10 10',
                        tooltip: '',
                        listeners: {
                            toggle: onSelectBehavior
                        }
                    },
                    items: buttons['behaviors']
                },{
                    xtype: 'label',
                    text: 'Addons',
                    cls: 'x-form-item-label-default'
                }, {
                    xtype: 'container',
                    margin: '10 0 10 -10',
                    itemId: 'settings-addons',
                    cls: 'x-rolebuilder-addons',
                    defaults: {
                        xtype: 'button',
                        ui: 'simple',
                        enableToggle: true,
                        cls: 'x-btn-simple-large',
                        iconAlign: 'top',
                        margin: '-10 10 0 0',
                        listeners: {
                            toggle: onSelectAddon
                        }
                    },
                    items: Ext.Array.merge(buttons['addons'], [{xtype: 'component', html: 'Chef client will be included in this image', margin: '18 0 0', cls: '', style: 'vertical-align:top;display:inline-block;'}])
                },{
                    xtype: 'displayfield',
                    itemId: 'softwarewarning',
                    hidden: true,
                    cls: 'x-form-field-info',
                    value: 'Software version is taken from the <b>official OS repositories</b>. Consult your distribution\'s package manager for exact version details.'
                }]
            },{
                xtype: 'fieldset',
                title: 'Install additional software using Chef',
                itemId: 'chefsettings',
                hidden: !chefFieldsetEnabled,
                toggleOnTitleClick: true,
                collapsible: true,
                collapsed: true,
                layout: 'anchor',
                defaults: {
                    maxWidth: 516,
                    anchor: '100%',
                    labelWidth: 140
                },
                listeners: {
                    expand: function(){
                        panel.down('#rightcol').body.scrollTo('top', 3000);
                    }
                },
                items: [{
                    xtype: 'chefserveridcombo',
                    name: 'chef.server',

                    listeners: {
                        change: function(field, value) {
                            if (value) {
                                var f = this.next('[name="chef.environment"]');
                                f.setReadOnly(false, false);

                                // hack, to preselect _default, when none being set
                                if (! f.environmentValue)
                                    f.setValue('_default');

                                f.store.proxy.params['servId'] = value;

                                var f1 = this.next('[name="chef.role"]');
                                f1.setReadOnly(false, false);
                                f1.store.proxy.params['servId'] = value;
                            }
                        }
                    }
                }, {
                    xtype: 'combo',
                    name: 'chef.environment',
                    fieldLabel: 'Chef environment',
                    valueField: 'name',
                    displayField: 'name',
                    editable: false,
                    readOnly: true,
                    value: '',

                    queryCaching: false,
                    clearDataBeforeQuery: true,
                    store: {
                        fields: [ 'name' ],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'rolebuilder',
                            url: '/services/chef/xListEnvironments/'
                        }
                    },
                    listeners: {
                        change: function(field, value) {
                            if (value) {
                                var f = this.next('[name="chef.role"]');
                                f.store.proxy.params['chefEnv'] = value;
                                f.store.proxy.params['servId'] = this.store.proxy.params['servId'];

                                if (f.getValue()) {
                                    f.setValue('');
                                    f.store.load();
                                }
                            }
                        }
                    }
                }, {
                    xtype: 'combo',
                    name: 'chef.role',
                    fieldLabel: 'Chef role',
                    valueField: 'name',
                    displayField: 'name',
                    editable: false,
                    readOnly: true,
                    value: '',

                    queryCaching: false,
                    clearDataBeforeQuery: true,
                    store: {
                        fields: [ 'name', 'chef_type' ],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'rolebuilder',
                            url: '/services/chef/xListRoles'
                        }
                    }
                },{
                    xtype: 'textarea',
                    fieldLabel: 'Attributes (json)',
                    name: 'chef.attributes',
                    itemId: 'chefattributes',
                    hidden: true,
                    grow: true,
                    growMax: 300,
                    anchor: '100%',
                    maxWidth: null,
                    validateOnChange: false,
                    validator: function(value) {
                        return !Ext.isEmpty(value) && Ext.typeOf(Ext.decode(value, true)) != 'object' ? 'Invalid json' : true;
                    }
                }]
            },{
                xtype: 'fieldset',
                title: 'Advanced options',
                collapsible: true,
                collapsed: true,
                toggleOnTitleClick: true,
                hidden: !advancedFieldsetEnabled,
                layout: 'anchor',
                defaults: {
                    maxWidth: 516,
                    anchor: '100%',
                    labelWidth: 140
                },
                listeners: {
                    expand: function(){
                        panel.down('#rightcol').body.scrollTo('top', 3000);
                    }
                },
                items: [{
                    xtype: 'displayfield',
                    cls: 'x-form-field-warning',
                    value: 'Please use advanced options on your own risk!',
                    margin: '8 0 12 0'
                },{
                    xtype: 'textfield',
                    name: 'advanced.availzone',
                    itemId: 'availzone',
                    fieldLabel: 'Availability zone'
                },{
                    xtype: 'textfield',
                    name: 'advanced.servertype',
                    itemId: 'servertype',
                    fieldLabel: 'Server type'
                },{
                    xtype: 'textfield',
                    name: 'advanced.scalrbranch',
                    itemId: 'scalrbranch',
                    fieldLabel: 'Scalarizr branch'
                },{
                    xtype: 'textfield',
                    name: 'advanced.region',
                    itemId: 'region',
                    fieldLabel: 'Region'
                },{
                    xtype: 'textfield',
                    name: 'advanced.overrideImageId',
                    itemId: 'overrideImageId',
                    hidden: true,
                    fieldLabel: 'Image ID'
                },{
                    xtype: 'checkbox',
                    name: 'advanced.dontterminatefailed',
                    itemId: 'dontterminatefailed',
                    boxLabel: 'Do not terminate if build failed'
                }]
            }],
            dockedItems:[{
                xtype: 'container',
                dock: 'bottom',
                cls: 'x-docked-buttons',
                style: 'z-index:101;background:transparent',//show toolbar above the mask to make "Cancel" button available
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                items: [{
                    xtype: 'button',
                    itemId: 'save',
                    text: 'Create',
                    disabled: true,
                    handler: function() {
                        var platformImages = panel.getPlatformImages(result.platform);
                        if (chefFieldsetEnabled) {
                            if (!panel.down('#chefattributes').isValid()) {
                                panel.down('#chefsettings').expand();
                                return;
                            }
                        }
                        if (!platformImages.length) {
                            if (!panel.down('#imageId').isValid()) {
                                panel.down('#imageId').focus();
                                return;
                            }
                        }

                        if (loadParams['vpc'] == 1 && result.platform === 'ec2') {
                            if (!panel.down('#vpcId').isValid()) {
                                return;
                            }
                            if (panel.down('#vpcId').getValue() && !panel.down('#vpcSubnetId').isValid()) {
                                return;
                            }
                        }

                        if (panel.down('#rolename').isValid()) {
                            var r = Scalr.utils.CloneObject(result);
                            r.advanced = {};
                            r.chef = {};

                            //role name
                            r['name'] = panel.down('#rolename').getValue();
                            r['createImage'] = panel.down('#roleimage').getValue();

                            //collect behaviors
                            if (! r.behaviors.length) {
                                Ext.Array.include(r.behaviors, 'base');
                            }
                            r['behaviors'] = Ext.encode(Ext.Array.merge(r.behaviors, r.addons));
                            delete r.addons;

                            //imageId
                            if (platformImages.length) {
                                r['imageId'] = panel.getImageId();
                            } else {
                                r['imageId'] = panel.down('#imageId').getValue();
                                delete r['architecture'];
                            }

                            //collect fieldsets options
                            var extraOptions = panel.down('#rightcol').getValues();
                            if (advancedFieldsetEnabled) {//collect advanced options
                                Ext.Object.each(extraOptions, function(key, value){
                                    if (key.match(/^advanced\./ig)) {
                                        r.advanced[key.replace('advanced.', '')] = value;
                                    }
                                });
                                //override imageId
                                if (platformImages.length && !Ext.isEmpty(r.advanced['overrideImageId'])) {
                                    r['imageId'] = r.advanced['overrideImageId'];
                                }
                                delete r.advanced['overrideImageId'];
                            }
                            if (chefFieldsetEnabled) {//collect chef options
                                Ext.Object.each(extraOptions, function(key, value){
                                    if (key.match(/^chef./ig)) {
                                        r.chef[key] = value;
                                    }
                                });
                            }

                            if (loadParams['devScalarizrBranch']) {
                                r['devScalarizrBranch'] = loadParams['devScalarizrBranch'];
                            }

                            //backward compatibility
                            r['mysqlServerType'] = 'mysql';
                            r['location'] = r['cloudLocation'];

                            r.advanced = Ext.encode(r.advanced);
                            r.chef = Ext.encode(r.chef);

                            if (loadParams['vpc'] == 1 && r.platform === 'ec2' && panel.down('#vpcId').getValue()) {
                                r.vpcId = panel.down('#vpcId').getValue();
                                r.vpcSubnetId = panel.down('#vpcSubnetId').getValue();
                            }
                            Scalr.Request({
                                processBox: {
                                    type: 'action'
                                },
                                url: '/roles/xBuild',
                                params: r,
                                success: function (data) {
                                    Scalr.event.fireEvent('redirect', '#/roles/builder?serverId=' + data.serverId + (loadParams['devel'] ? '&devel=1' : ''));
                                }
                            });
                        } else {
                            panel.down('#rightcol').body.scrollTo('top', 0);
                            panel.down('#rolename').focus();
                        }
                    }
                }, {
                    xtype: 'button',
                    itemId: 'cancel',
                    text: 'Cancel',
                    handler: function() {
                        Scalr.event.fireEvent('close');
                    }
                }]
            }]
        },{
            xtype: 'container',
            itemId: 'progresscol',
            layout: 'anchor',
            autoScroll: true,
            hidden: true,
            flex: 1,
            listeners: {
                show: {
                    fn: function() {
                        this.down('#progress').resetProgress();
                        this.down('#log').setBundleTaskId(this.bundleTaskId);
                    },
                    single: true
                }
            },
            items: [{
                xtype: 'fieldset',
                title: moduleParams['server'] ? ((moduleParams['server']['object'] == 'role' ? 'Role' : 'Image') + ' creation progress') : '',
                cls: 'x-fieldset-separator-none',
                itemId: 'progress',
                layout: {
                    type: 'hbox',
                    align: 'stretch'
                },
                defaults: {
                    xtype: 'component',
                    flex: 1,
                    cls: 'scalr-ui-progress-step x-semibold',
                    maxWidth: 300
                },
                resetProgress: function() {
                    this.items.each(function(item, index){
                        if (index === 0) {
                            item.addCls('inprogress');
                            item.removeCls('complete');
                        } else {
                            item.removeCls('inprogress complete');
                        }
                    });
                },
                updateProgress: function(status, state) {
                    var data = {
                        'failed': -1,
                        'pending': 0,
                        'starting-server': 0,
                        'preparing-environment': 1,
                        'installing-software': 1,
                        'creating-role': 3,
                        'success': 5
                    };
                    if (data[status]) {
                        this.items.each(function(item, index){
                            if (index < data[status]) {
                                item.removeCls('inprogress');
                                item.addCls('complete');
                            } else if (index === data[status]) {
                                item.addCls(state || 'inprogress');
                            } else {
                                item.removeCls('inprogress complete');
                            }
                        });
                    }
                },
                items: [{
                    xtype: 'component',
                    html: 'Starting server'
                },{
                    xtype: 'component',
                    html: 'Creating image'
                },{
                    xtype: 'component',
                    html: 'Setting automation'
                },{
                    xtype: 'component',
                    html: 'Building ' + (moduleParams['server'] ? moduleParams['server']['object'] : '')
                },{
                    xtype: 'component',
                    html: 'Complete!'
                }]
            },{
                xtype: 'fieldset',
                title: moduleParams['server'] ? ((moduleParams['server']['object'] == 'role' ? 'Role' : 'Image') + ' creation log') : '',
                cls: 'x-fieldset-separator-top-bottom',
                itemId: 'log',
                collapsible: true,
                setBundleTaskId: function(bundleTaskId) {
                    var rightcol = this.up('#progresscol');
                    this.bundleTaskId = bundleTaskId;
                    rightcol.down('#progress').updateProgress('in-progress');
                    this.down('#fullLog').update('<a href="#">View full log</a>', [], function() {
                        this.el.down('a').on('click', function(e) {
                            var logPanel = Scalr.getPage('Scalr.ui.bundletasks.view.logs');
                            logPanel.bundleTaskId = bundleTaskId;
                            Scalr.utils.Window(logPanel);
                            e.preventDefault();
                        });
                    });
                    this.loadBundleTaskData();

                },
                loadBundleTaskData: function() {
                    var me = this;
                    me.stopAutoUpdate();
                    me.request = Scalr.Request({
                        params: {
                            bundleTaskId: this.bundleTaskId
                        },
                        url: '/roles/import/xGetBundleTaskData/',
                        success: function (data) {
                            if (!me.isDestroyed) {
                                var progresscol = me.up('#progresscol');
                                if (data['status'] === 'failed') {
                                    progresscol.down('#progress').updateProgress(data['status']);
                                    panel.onBundleTaskFailed(data['failureReason']);
                                } else {
                                    me.down('grid').store.load({data: data['logs']});
                                    progresscol.down('#progress').updateProgress(data['status']);
                                    if (data['status'] !== 'success') {
                                        me.autoUpdateTask = setTimeout(function(){
                                            me.loadBundleTaskData();
                                        }, 5000);
                                    } else {
                                        panel.onBundleTaskSuccess(data);
                                    }
                                }
                            }
                        }
                    });

                },
                autoUpdateTask: null,
                stopAutoUpdate: function(){
                    if (this.request) {
                        Ext.Ajax.abort(this.request);
                    }
                    if (this.autoUpdateTask) {
                        clearTimeout(this.autoUpdateTask);
                        this.autoUpdateTask = null;
                    }
                },
                listeners: {
                    destroy: function() {
                        this.stopAutoUpdate();
                    },
                    hide: function() {
                        this.stopAutoUpdate();
                    }
                },
                items: [{
                    xtype: 'grid',
                    plugins: [{
                        ptype: 'gridstore'
                    }, {
                        ptype: 'rowexpander',
                        rowBodyTpl: [
                            '<p><b>Message:</b> {message}</p>'
                        ]
                    }],
                    store: {
                        fields: [
                            {name: 'id', type: 'int'},
                            'dtadded','message'
                        ],
                        proxy: 'object'
                    },
                    viewConfig: {
                        emptyText: 'Log is empty',
                        focusedItemCls: 'x-noselection',
                        selectedItemCls: 'x-noselection',
                        getRowClass: function(record, rowIndex) {
                            return rowIndex === 0 ? 'x-grid-row-color-new' : '';
                        }
                    },
                    columns: [
                        { header: "Date", width: 165, dataIndex: 'dtadded', sortable: false },
                        { header: "Message", flex: 1, dataIndex: 'message', sortable: false }
                    ]
                },{
                    xtype: 'component',
                    itemId: 'fullLog',
                    margin: '6 0 0'
                }]
            },{
                xtype: 'component',
                itemId: 'success',
                hidden: true,
                cls: 'x-fieldset-subheader x-fieldset-subheader-no-text-transform',
                style: 'text-align:center',
                margin: '48 0 16'
            },{
                xtype: 'container',
                itemId: 'failed',
                hidden: true,
                margin: '32 0 16',
                items: [{
                    xtype: 'component',
                    cls: 'x-fieldset-subheader',
                    style: 'text-align:center',
                    margin: '0 0 16',
                    html: 'Role creation failed'
                },{
                    xtype: 'component',
                    style: 'text-align:center',
                    itemId: 'failureReason'
                }]
            },{
                xtype: 'container',
                itemId: 'buttons',
                items: [{
                    xtype: 'container',
                    itemId: 'commonButtons',
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        itemId: 'cancel',
                        text: 'Cancel',
                        handler: function() {
                            Scalr.utils.Confirm({
                                form: {
                                    xtype: 'component',
                                    style: 'text-align: center',
                                    margin: '36 0 0',
                                    html: '<span class="x-fieldset-subheader x-fieldset-subheader-no-text-transform">Are you sure want to cancel ' + moduleParams['server']['object'] + ' creation?</span>'
                                },
                                success: function() {
                                    Scalr.Request({
                                        processBox: {
                                            type: 'action'
                                        },
                                        url: '/servers/xServerCancelOperation/',
                                        params: { serverId: panel.serverId },
                                        success: function (data) {

                                        }
                                    });
                                    Scalr.event.fireEvent('redirect', '#/roles');
                                }
                            });
                        }
                    }]
                },{
                    xtype: 'container',
                    itemId: 'successButtons',
                    hidden: true,
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'View ' + (moduleParams['server'] ? moduleParams['server']['object'] : ''),
                        handler: function() {
                            if (moduleParams['server']['object'] == 'role')
                                Scalr.event.fireEvent('redirect', '#/roles?roleId=' + panel.roleId);
                            else
                                Scalr.event.fireEvent('redirect', '#/images?hash=' + panel.imageHash);
                        }
                    },{
                        xtype: 'button',
                        text: 'Build farm',
                        hidden: moduleParams['server'] ? moduleParams['server']['object'] == 'image' : false,
                        handler: function() {
                            Scalr.event.fireEvent('redirect', '#/farms/designer?roleId=' + panel.roleId);
                        }
                    }]
                },{
                    xtype: 'container',
                    itemId: 'failedButtons',
                    hidden: true,
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Try again',
                        handler: function() {
                            var params = {};
                            if (loadParams['devel']) params['devel'] = 1;
                            if ('image' in loadParams) params['image'] = '';
                            Scalr.event.fireEvent('redirect', '#/roles/builder?' + Ext.Object.toQueryString(params));
                        }
                    }]
                }]
            }]
        }],
        dockedItems: [{
            xtype: 'container',
            itemId: 'platforms',
            dock: 'left',
            cls: 'x-docked-tabs',
            width: 110 + Ext.getScrollbarSize().width,
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
            items: buttons['platforms']
        }],

        suspendState: 0,
        platformsState: {},
        filters: {},
        imagesCache: {},

        getPlatformImages: function(platformId) {
            var me = this,
                platform = platforms[platformId];
            if (!me.imagesCache[platformId]) {
                me.imagesCache[platformId] = [];
                //select images with supported osId
                for (var i=0, len=platform.images.length; i<len; i++) {
                    if (Scalr.utils.getOsById(platform.images[i].os_id)) {
                        me.imagesCache[platformId].push(platform.images[i]);
                    }
                }
            }
            return me.imagesCache[platformId];
        },

        onSelectPlatform: function(platformId) {
            var platformLocations = Scalr.platforms[platformId] ? Scalr.platforms[platformId].locations : null,
                images = this.getPlatformImages(platformId),
                compImages = panel.down('#images'),
                added = {},
                locations = {},
                compLocationData = [],
                archs = {};


            panel.toggleRightColumn(images.length == 0);
            if (result) {
                this.platformsState[result.platform] = Ext.apply({}, result);
            }

            if (this.platformsState[platformId]) {
                this.platformsState[platformId].behaviors = result.behaviors;
                this.platformsState[platformId].addons = result.addons;
                this.platformsState[platformId].hvm = 0;
                result = Ext.apply({}, this.platformsState[platformId]);
            } else {
                result = {
                    platform: platformId,
                    architecture: 'x86_64',
                    behaviors: result.behaviors || [],
                    addons: result.addons || ['chef']
                };
            }

            this.filters = ['architecture'];
            if (result.platform == 'ec2' && rootDeviceTypeFilterEnabled) {
                this.filters.push('root_device_type');
            }


            panel.suspendLayouts();
            compImages.removeAll();
            if (images.length) {
                for (var i=0, len=images.length; i<len; i++) {
                    var image = images[i],
                        osId = image.os_id,
                        os = Scalr.utils.getOsById(osId);
                    if (os) {
                        if (!added[osId]) {
                            compImages.add({
                                xtype: 'button',
                                ui: 'simple',
                                cls: 'x-btn-simple-large',
                                iconAlign: 'top',
                                iconCls: 'x-icon-osfamily-large x-icon-osfamily-large-' + os.family,
                                allowDepress: false,
                                disableMouseDownPressed: true,
                                toggleGroup: 'scalr-ui-roles-builder-image',
                                text: '<span class="small">'+os.name+'</span>',
                                osId: osId,
                                margin: '0 0 10 10',
                                toggleHandler: function () {
                                    panel.fireEvent('selectname', this.osId, this.pressed);
                                }
                            });

                            added[osId] = true;
                        }

                        if (platformLocations && platformLocations[image.cloud_location]) {
                            locations[image.cloud_location] = Scalr.platforms[platformId].locations[image.cloud_location];
                        } else {
                            locations[image.cloud_location] = image.cloud_location;
                        }
                        archs[image.architecture] = true;
                    }
                }
            } else {
                locations = platformLocations ? Ext.clone(platformLocations) : {};
            }

            var c = panel.down('#leftcol').query('component[cls~=hideoncustomimage]');
            for (var i=0, len=c.length; i<len; i++) {
                c[i].setVisible(!!images.length);
            }

            var c = panel.down('#leftcol').query('component[cls~=showoncustomimage]');
            for (var i=0, len=c.length; i<len; i++) {
                c[i].setVisible(!images.length);
            }

            //location combobox setup
            var compLocation = panel.down('#cloudLocation');
            compLocation.store.clearFilter();
            compLocation.reset();

            compLocation.toggleIcon('governance', false);
            compLocation.getPlugin('fieldinnericoncloud').setPlatform(platformId);

            if (result.platform == 'gce') {
                compLocation.store.loadData([{id: 'all', name: 'GCE roles are automatically available in all regions.'}]);
                compLocation.setValue('all');
                compLocation.setDisabled(true);
            } else {
                Ext.Object.each(locations, function(k, v){
                    compLocationData.push({id: k, name: v});
                });
                compLocation.store.loadData(compLocationData);
                compLocation.setDisabled(false);
                compLocation.store.sort('name', 'desc');
                if (loadParams['vpc'] == 1 && platformId == 'ec2' && vpcLimits && vpcLimits['regions']) {
                    var defaultRegion;
                    compLocation.store.filter({filterFn: function(region){
                        var r = vpcLimits['regions'][region.get('id')];
                        if (r !== undefined && r['default'] == 1) {
                            defaultRegion = region.get('id');
                        }
                        return r !== undefined;
                    }});
                    compLocation.setValue(defaultRegion || compLocation.store.first());
                    compLocation.toggleIcon('governance', true);
                    this.down('#vpcId').toggleIcon('governance', true);
                    this.down('#vpcSubnetId').toggleIcon('governance', true);
                } else {
                    compLocation.setValue(result.cloudLocation ? result.cloudLocation : (result.platform == 'ec2' && locations['us-east-1'] ? 'us-east-1' : compLocationData[0].id));
                }
            }

            if (loadParams['vpc'] == 1) {
                this.down('#vpcId').setVisible(platformId == 'ec2');
                this.down('#vpcSubnetId').setVisible(platformId == 'ec2' && this.down('#vpcId').getValue());
            }


            panel.down('#rootdevicetypewrap')[result.platform == 'ec2' && rootDeviceTypeFilterEnabled ? 'show' : 'hide']();
            //panel.down('#architecture')[Ext.Object.getSize(archs) > 1 ? 'show' : 'hide']();
            panel.resumeLayouts(true);


        },

        initFilters: function() {
            var me = this;
            for (var i=0, len=this.filters.length; i<len; i++) {
                var comp = panel.down('#'+this.filters[i]),
                    isItemFound = false;
                comp.items.each(function(item, index){
                    item.enable();
                    if (result[me.filters[i]] && result[me.filters[i]] == item.value) {
                        comp.setValue(item.value);
                        isItemFound = true;
                        return false;
                    }
                });
                if (!isItemFound) {
                    comp.items.each(function(item, index){
                        if (!item.disabled) {
                            comp.setValue(item.value);
                            return false;
                        }
                    });
                }
            }
        },

        getImageId: function() {
            var me = this,
                imageId,
                images = this.getPlatformImages(result.platform),
                hvm = panel.down('#hvm').pressed ? 1 : 0;

            for (var j=0, len=images.length; j<len; j++) {
                var image = images[j],
                    match = result.cloudLocation == 'all' || image.cloud_location == result.cloudLocation;
                if (match) {
                    for (var i=0, len1=me.filters.length; i<len1; i++) {
                        match = result[me.filters[i]] ? image[me.filters[i]] == result[me.filters[i]] : true;
                        if (match && result.platform == 'ec2' && me.filters[i] == 'root_device_type' && result[me.filters[i]] == 'ebs') {//hvm
                            match = hvm == (image.hvm || 0);
                        }
                        if (!match) break;
                    }
                }
                if (match && result.osId == image.os_id) {
                    imageId = image.image_id;
                    break;
                }
            }

            return imageId;

        },

        findImageById: function(platform, imageId) {
            var images = this.getPlatformImages(platform),
                image;
            Ext.Array.each(images, function(item){
                if (item['image_id'] == imageId) {
                    image = item;
                    return false;
                }
            });

            return image;
        },

        getFiltersValues: function() {
            var me = this,
                images = this.getPlatformImages(result.platform),
                state = {images: {}, filters: {}},
                hvm = panel.down('#hvm').pressed ? 1 : 0;

            for (var j=0, len=images.length; j<len; j++) {
                var image = images[j],
                    matchHvm = true,
                    match = result.cloudLocation == 'all' || image.cloud_location == result.cloudLocation;
                if (match) {
                    for (var i=0, len1=me.filters.length; i<len1; i++) {
                        match = result[me.filters[i]] ? image[me.filters[i]] == result[me.filters[i]] : true;

                        if (match && result.platform == 'ec2' && me.filters[i] == 'root_device_type' && result[me.filters[i]] == 'ebs') {//hvm
                            matchHvm = hvm == (image.hvm || 0);
                        }

                        if (!match || !matchHvm) break;
                    }
                }
                if (match ) {
                    if (matchHvm) {
                        state.images[image.os_id] = true;
                    }
                    if (result.platform == 'ec2' && result.root_device_type == 'ebs' && image.hvm) {
                        state.hvm = 1;
                        state.hvmOnly = Ext.isDefined(state.hvmOnly) ? state.hvmOnly : 1;
                    } else {
                        state.hvmOnly = 0;
                    }
                }
            }
            for (var i=0, len=me.filters.length; i<len; i++) {
                for (var k=0, len1=images.length; k<len1; k++) {
                    var image = images[k],
                        match = result.cloudLocation == 'all' || image.cloud_location == result.cloudLocation;
                    if (match) {
                        for (var j=0, len2=me.filters.length; j<len2; j++) {
                            match = result[me.filters[j]] == result[me.filters[i]] || (result[me.filters[j]] ? image[me.filters[j]] == result[me.filters[j]] : true);
                            if (!match) break;
                        }
                    }
                    if (match) {
                        state.filters[me.filters[i]] = state.filters[me.filters[i]] || {};
                        state.filters[me.filters[i]][image[me.filters[i]]] = true;
                    }
                }
            }
            return state;
        },

        updateFiltersState: function() {
            var me = this,
                state = this.getFiltersValues(),
                compImages = panel.down('#images'),
                hvmButton = panel.down('#hvm');
            
            if (state.hvm && !state.hvmOnly) {
                hvmButton.enable();
            } else {
                if (hvmButton.pressed != (state.hvmOnly ? true : false)) {
                    hvmButton.toggle(state.hvmOnly ? true : false);
                    hvmButton.disable();
                    return;
                } else {
                    hvmButton.disable();
                }
            }

            compImages.items.each(function() {
                if (state.images[this.osId]) {
                    this.enable();
                } else {
                    if (this.pressed) {
                        this.toggle(false);
                    }
                    this.disable();
                }
            });

            for (var i=0, len=me.filters.length; i<len; i++) {
                if (state.filters[me.filters[i]]) {
                    var comp = panel.down('#'+me.filters[i]);
                    comp.setReadOnly(false);
                    comp.items.each(function(){
                        if (!state.filters[me.filters[i]][this.value]) {
                            if (this.pressed) {
                                this.toggle(false);
                                var that = this;
                                comp.items.each(function(){
                                    if (this !== that) {
                                        this.toggle(true);
                                    }
                                });
                            }
                            comp.setReadOnly(true);
                        }
                    });
                }
            }
        },

        refreshBehaviors: function() {
            var params = {platform: result.platform, behavior: result.behaviors},
                image = panel.down('#images').down('[pressed=true]'),
                os = Scalr.utils.getOsById(image.osId);
            if (os) {
                params.os = {
                    family: os.family,
                    version: os.version
                }
            }
            for (var i=0, len=behaviors.length; i<len; i++) {
                var item = behaviors[i],
                    enabled = true,
                    disableInfo;
                if (item.disable) {
                    Ext.Object.each(params, function(key, value){
                        if (item.disable[key]) {
                            if (key == 'os') {
                                for (var j=0, len1=item.disable[key].length; j<len1; j++) {
                                    if (Ext.isString(item.disable[key][j])) {
                                        if (value.family == item.disable[key][j]) {
                                            enabled = false;
                                            break;
                                        }
                                    } else {
                                        if (value.family == item.disable[key][j].family) {
                                            if (Ext.isArray(item.disable[key][j].version)) {
                                                if (Ext.Array.contains(item.disable[key][j].version, value.version)) {
                                                    enabled = false;
                                                    break;
                                                }
                                            } else if (Ext.isString(value.version) && value.version.match(item.disable[key][j].version)){
                                                enabled = false;
                                                break;
                                            }
                                        }
                                    }
                                }
                            } else {
                                enabled = Ext.isArray(value) ? !Ext.Array.intersect(item.disable[key], value).length : !Ext.Array.contains(item.disable[key], value);
                            }
                        }
                        disableInfo = {
                            reason: key,
                            value: item.disable[key] || null
                        };
                        return enabled;
                    });
                }
                var btn = panel.down('[behavior="'+item.name+'"]');
                if (enabled) {
                    btn.enable();
                    btn.setTooltip('');
                }  else {
                    btn.toggle(false).disable();
                    var message = '';
                    if (disableInfo.reason == 'os') {
                        message = '<b>' + Scalr.utils.beautifyBehavior(item.name) + '</b> cannot be used together with <b style="white-space:nowrap">' + Scalr.utils.beautifyOsFamily(Scalr.utils.getOsById(result.osId, 'family')) + ' ' + Scalr.utils.getOsById(result.osId, 'version') + '</b>.';
                    } else if (disableInfo.reason == 'platform') {
                        message = '<b>' + Scalr.utils.beautifyBehavior(item.name) + '</b> is not available on <b style="white-space:nowrap">' + platforms[result.platform].name + '</b>.';
                    } else if (disableInfo.reason == 'behavior') {
                        message = '<b>' + Scalr.utils.beautifyBehavior(item.name) + '</b> cannot be used together with <b style="white-space:nowrap">' + (Ext.Array.map(Ext.Array.intersect(result.behaviors, disableInfo.value), Scalr.utils.beautifyBehavior)).join(', ') + '</b>.';
                        //disableInfo.reason + (disableInfo.reason == 'behavior' ? ': ' + disableInfo.value.join(', ') : '')
                    }
                    btn.setTooltip(message);
                }
            }
        },
        toggleRightColumn: function(enable) {
            toggleBehaviors(true);
            panel.down('#save').setDisabled(!enable);
            var rightcol = panel.down('#rightcol'), mask;
            rightcol[enable ? 'unmask' : 'mask']();
            mask = rightcol.getEl().child('.x-mask');
            if (mask) {
                mask.set({title: enable ? '' : 'Please select operating system.'});
                mask.setStyle({
                    background: '#ffffff',
                    opacity: .6
                });
            }

        },

        loadServer: function(server, image) {
            image = image || {};
            panel.suspendLayouts();
            if (server['platform'] !== 'gce') {
                panel.down('#cloudLocation').setValue(image['cloud_location']).disable();
            }
            panel.down('#architecture').setValue(image['architecture']).disable();
            panel.down('#root_device_type').setValue(image['root_device_type']).disable();
            panel.down('#hvm').toggle(image['hvm']==1).disable();
            panel.down('#images').items.each(function(item){
                if (item.osId == image.os_id) {
                    item.toggle(true);
                }
                item.disable();
            });
            panel.down('#imageId').setValue(server['imageId']).disable();

            panel.getDockedComponent('platforms').items.each(function(){
                this.disable();
            });

            panel.down('#rightcol').hide();
            panel.down('#progresscol').bundleTaskId = server.bundleTaskId;
            panel.down('#progresscol').show();
            panel.resumeLayouts(true);
        },

        onBundleTaskSuccess: function(role) {
            var progresscol = this.getComponent('progresscol'),
                success = progresscol.down('#success');
            progresscol.down('#successButtons').show();
            progresscol.down('#commonButtons').hide();
            progresscol.down('#log').hide();
            success.update('New ' + moduleParams['server']['object'] + ' &laquo;' + (moduleParams['server']['object'] == 'role' ? role['roleName'] : role['imageId']) + '&raquo; has successfully been created');
            success.show();
            this.roleId = role['roleId'];
            this.platform = role['platform'];
            this.imageId = role['imageId'];
            this.imageHash = role['imageHash'];
        },
        onBundleTaskFailed: function(failureReason) {
            var progresscol = this.getComponent('progresscol'),
                failed = progresscol.down('#failed');
            progresscol.down('#failedButtons').show();
            progresscol.down('#commonButtons').hide();
            progresscol.down('#log').hide();
            failed.down('#failureReason').update('Error: '+failureReason);
            failed.show();
        },

        listeners: {
            afterrender: function () {
                var items = panel.down('#platforms').items,
                    defaultPlatform = loadParams['platform'] || 'ec2',
                    defaultItem, server = moduleParams['server'], image;

                if (server) {
                    panel.serverId = server['serverId'];
                    image = this.findImageById(server['platform'], server['imageId']);
                    defaultPlatform = server['platform'];
                }
                items.each(function(){
                    if (this.value == defaultPlatform) {
                        defaultItem = this;
                        return false;
                    }
                });
                if (server) {
                    this.on('afterselectplatform', function(){
                        this.loadServer(server, image);
                    }, this, {single: true});
                }

                if ('image' in loadParams) {
                    this.down('#roleimage').setValue(true);
                }

                defaultItem = defaultItem || items.first();
                defaultItem.toggle(true);
            },
            selectplatform: function(platform) {
                var me = this;
                callback = function() {
                    me.suspendState++;
                    me.onSelectPlatform(platform);
                    me.initFilters();
                    me.suspendState--;
                    me.updateFiltersState();
                    me.down('#availzone')[platform=='ec2'?'show':'hide']();
                    me.down('#region')[platform=='gce'?'show':'hide']();
                    me.down('#overrideImageId')[me.getPlatformImages(platform).length && Scalr.flags['betaMode']?'show':'hide']();
                    me.fireEvent('afterselectplatform');
                };
                if (platform=='gce') {
                    callback();
                } else {
                    Scalr.loadCloudLocations(platform, callback);
                }
            },
            selectlocation: function(value) {
                result.cloudLocation = value;
                if (!this.suspendState) {
                    panel.updateFiltersState();
                }
            },
            selectarchitecture: function(value) {
                result.architecture = value;
                if (!this.suspendState) {
                    panel.updateFiltersState();
                }
            },
            selectrootdevicetype: function(value) {
                result.root_device_type = value;
                if (value=='ebs') {
                    panel.down('#hvm').enable();
                } else {
                    panel.down('#hvm').toggle(false);
                    panel.down('#hvm').disable();
                }
                if (!this.suspendState) {
                    panel.updateFiltersState();
                }
            },
            selecthvm: function(value) {
                result.hvm = value ? 1 : 0;
                if (!this.suspendState) {
                    panel.updateFiltersState();
                }
            },
            selectname: function(osId, select) {
                result.osId = select ? osId : null;
                panel.toggleRightColumn(select);
                if (select) {
                    panel.refreshBehaviors();
                }
            }

        }
    });
    return panel;
});

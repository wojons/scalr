Scalr.regPage('Scalr.ui.core.governance.edit', function (loadParams, moduleParams) {
    var instanceTypeDescription = 'Limit the types of instances that can be configured in Farm Designer.';
    var configOptions = {
        openstack: [{
            name: 'openstack.flavor-id',
            title: 'Instance type',
            type: 'instancetype',
            defaults: {value: []},
            subheader: instanceTypeDescription
        },{
            name: 'openstack.networks',
            title: 'Networks',
            type: 'networks',
            defaults: {value: {}},
            subheader: 'Limit the networks that instances can be launched in.'
        },{
            name: 'openstack.additional_security_groups',
            title: 'Security groups',
            type: 'securityGroups',
            emptyText: 'ex. xxx,yyy',
            defaults: {
                value: '',
                allow_additional_sec_groups: 0
            },
            subheader: 'Set security groups list that will be applied to all instances.',
            warning: 'Please ensure that the security groups that you list already exist within your cloud setup. Scalr WILL NOT create these groups and instances will fail to launch otherwise.'
        }],
        cloudstack: [{
            name: 'cloudstack.service_offering_id',
            title: 'Instance type',
            type: 'instancetype',
            defaults: {value: []},
            subheader: instanceTypeDescription
        },{
            name: 'cloudstack.network_id',
            title: 'Network',
            type: 'networks',
            defaults: {value: {}},
            subheader: 'Limit the networks that instances can be launched in.'
        },{
            name: 'cloudstack.additional_security_groups',
            title: 'Security groups',
            type: 'securityGroups',
            emptyText: 'ex. xxx,yyy',
            defaults: {
                value: '',
                allow_additional_sec_groups: 0
            },
            subheader: 'Set security groups list that will be applied to all instances.',
            warning: 'Please ensure that the security groups that you list already exist within your cloud setup. Scalr WILL NOT create these groups and instances will fail to launch otherwise.'
        }]
    };

    var config = {
        general: {
            title: 'Scalr',
            options: [{
                name: 'general.lease',
                type: 'lease',
                title: 'Lease management',
                subheader: 'Automatically terminate farms after a predefined period of time, with optional extensions and exemptions.'
            },{
                name: 'general.hostname_format',
                title: 'Server hostname format',
                type: 'text',
                emptyText: 'Leave blank to use cloud generated hostname',
                defaults: {
                    value: ''
                },
                subheader: 'Define a hostname format that will be used for all servers across this environment.',
                icons: {
                    globalvars: true
                }
            }]
        },
        ec2: {
            title: Scalr.utils.getPlatformName('ec2', true),
            options: [{
                name: 'aws.vpc',
                title: 'VPC',
                type: 'vpc',
                defaults: {
                    value: 0,
                    regions: {'us-east-1': {'default': 1}, ids: []}, ids: {}
                },
                subheader: 'Limit locations, internet access and subnets for Virtual Private Clouds.'
            },{
                name: 'aws.instance_type',
                title: 'Instance type',
                type: 'instancetype',
                defaults: {
                    value: ['m1.small'],
                    'default': 'm1.small'
                },
                subheader: instanceTypeDescription
            },{
                name: 'aws.additional_security_groups',
                title: 'Security groups',
                type: 'securityGroups',
                emptyText: 'ex. xxx,yyy',
                defaults: {
                    value: '',
                    allow_additional_sec_groups: 0
                },
                subheader: 'Set security groups list that will be applied to all instances.',
                warning: 'Please ensure that the security groups that you list already exist within your EC2 setup. Scalr WILL NOT create these groups and instances will fail to launch otherwise.'
            },{
                name: 'aws.ssh_key_pair',
                title: 'SSH key pair',
                type: 'textarea',
                defaults: {
                    value: ''
                },
                subheader: 'Set a common SSH key pair for all instances.',
                warning: 'Make sure this key pair already exists within your EC2 setup. Scalr WILL NOT create this pair and instances will fail to launch otherwise.'
            },{
                name: 'aws.iam',
                title: 'IAM',
                type: 'awsIAM',
                defaults: {
                    value: '',
                    iam_instance_profile_arn: ''
                },
                subheader: 'Limit which IAM instance profiles can be applied to instances.',
                warning: 'List the names of all IAM instance profiles you want to ALLOW. Users will be limited to only these profiles in Farm Designer.<br/>' +
                         '<b>Choose profiles that ALREADY EXIST in your EC2 account. Scalr will NOT CREATE any new profiles.</b>',
                emptyText: 'ex. Name1,Name2,...'
            }]
        }
    };

    if (Scalr.flags['betaMode']) {
        config['ec2']['options'].push({
            name: 'aws.tags',
            title: 'Tags',
            type: 'tags',
            alwaysEnabled: true,
            defaultTags: {
                'scalr-env-id': {value: '{SCALR_ENV_ID}', readOnly: true},
                'scalr-owner': {value: '{SCALR_FARM_OWNER_EMAIL}', readOnly: true},
                'scalr-farm-id': {value: '{SCALR_FARM_ID}', readOnly: true},
                'scalr-farm-role-id': {value: '{SCALR_FARM_ROLE_ID}', readOnly: true},
                'scalr-server-id': {value: '{SCALR_SERVER_ID}', readOnly: true},
                'Name': {value: '{SCALR_FARM_NAME} -> {SCALR_FARM_ROLE_ALIAS} #{SCALR_SERVER_INDEX}'}
            },
            defaults: {
                value: {}
            },
            subheader: 'Define tags that should be automatically assigned to every resource',
            warning: 'Global Variable Interpolation is supported for tags values <img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-globalvars" style="vertical-align:top;position:relative;top:2px" />'
        });
    }
    Ext.Object.each(moduleParams['platforms'], function(key, value){
        if (Scalr.isOpenstack(key, true)) {
            config[key] = {
                title: Scalr.utils.getPlatformName(key, true),
                options: Ext.clone(configOptions['openstack'])
            };
        } else if (Scalr.isCloudstack(key)) {
            config[key] = {
                title: Scalr.utils.getPlatformName(key, true),
                options: Ext.clone(configOptions['cloudstack'])
            }
        }
    });
    
    var platformsTabs = Ext.Array.clean(Ext.Array.map(Ext.Object.getKeys(config), function(platform, index, platforms){
        if (moduleParams['platforms'][platform] !== undefined) {
            return {
                iconCls: 'x-icon-platform-large x-icon-platform-large-' + platform,
                text: config[platform].title,
                value: platform
            }
        }
    }));

    platformsTabs.unshift({
        text: 'Scalr',
        value: 'general',
        style: 'margin-bottom: 10px',
        iconCls: 'scalr-ui-core-governance-icon-general'
    });

    var governanceSettings = Ext.isObject(moduleParams['values']) ? moduleParams['values'] : {};
    var maxFormWidth = 820;

	var panel = Ext.create('Ext.panel.Panel', {
        title: 'Governance policies for \'' + Scalr.user['envName'] + '\' environment',
		scalrOptions: {
			maximize: 'all'
		},
        plugins: {
            ptype: 'localcachedrequest',
            crscope: 'governance'
        },
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		items:[{
            xtype: 'grid',
            flex: 1,
            maxWidth: 500,
            itemId: 'options',
            padding: '12 0',
            cls: 'x-grid-shadow x-panel-column-left x-grid-with-formfields x-grid-no-selection',
            plugins: {
                ptype: 'focusedrowpointer',
                thresholdOffset: 30,
                addOffset: 4
            },
            store:{
                fields: ['platform', 'config', 'settings'],
                proxy: 'object'
            },
            columns: [{
                header: 'Policy',
                dataIndex: 'config',
                flex: 1,
                sortable: false,
                resizable: false,
                renderer: function(value, meta){
                    return '<span style="font-weight:bold">' + value.title + '</span>';
                }
            },{
                xtype: 'statuscolumn',
                statustype: 'policy',
                header: 'Status',
                sortable: false,
                resizable: false,
                width: 100,
                minWidth: 100,
                align: 'center',
                padding: 2,
                qtipConfig: {
                    width: 310
                }
            }],
            listeners: {
                selectionchange: function(comp, selected){
                    if (selected.length > 0) {
                        panel.down('#rightcol').editOption(selected[0].getData());
                    } else {
                        panel.down('#rightcol').hide();
                    }
                }
            },
            saveOption: function(enabled){
                var value, limits,
                    rightcol = panel.down('#rightcol'),
                    record = this.getSelectionModel().getSelection()[0];
                value = this.getSelectionModel().getSelection()[0].getData();
                limits = rightcol.getOptionValue(value.config);
                if (! limits)
                    return;
                value.settings.limits = limits;
                value.settings.enabled = enabled !== undefined ? enabled : value.settings.enabled;
                governanceSettings[value.platform][value.config.name] = value.settings;
                record.set('settings', Ext.clone(value.settings));
                Scalr.Request({
                    processBox: {
                        type: 'save'
                    },
                    url: '/core/governance/xSave/',
                    params: {
                        category: value.platform,
                        name: value.config.name,
                        value: Ext.encode(value.settings)
                    },
                    success: function () {}
                });
            }
        },{
            xtype: 'panel',
            itemId: 'rightcol',
            flex: 1,
            hidden: true,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            setTitle: function(header, subheader){
                this.getComponent('title').update('<div class="x-fieldset-header-text" style="float:none">'+header + '</div>' + (subheader? '<div class="x-fieldset-header-description">' + subheader + '</div>' : ''));
            },
            setToolbar: function(option){
                var toolbar = this.getDockedComponent('toolbar');
                toolbar.down('#save').setVisible(option.settings.enabled == 1 || option.config.alwaysEnabled).enable();
                toolbar.down('#disable').setVisible(option.settings.enabled == 1 && !option.config.alwaysEnabled).enable();
                toolbar.down('#enforce').setVisible(option.settings.enabled != 1 && !option.config.alwaysEnabled).enable();
            },
            disableButtons: function() {
                var toolbar = this.getDockedComponent('toolbar');
                toolbar.down('#save').disable();
                toolbar.down('#disable').disable();
                toolbar.down('#enforce').disable();
            },
            editOption: function(option){
                var container = this.down('#policySettings'),
                    warning = container.getComponent('warning');

                this.suspendLayouts();
                this.currentItem = container.getComponent(option.config.type);
                this.setTitle(option.config.title, option.config.subheader);
                this.setToolbar(option);
                this.currentItem.setValues(option);
                container.items.each(function(){
                    if (this.tab !== undefined) {
                        this.setVisible(this.itemId === option.config.type);
                    }
                });

                this.show();
                
                this.resumeLayouts(true);

                if (option.config.warning) {
                    warning.show();
                    warning.setValue(option.config.warning);
                } else {
                    warning.hide();
                }
            },
            getOptionValue: function(config) {
                return this.currentItem.getValues(config);
            },
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-header',
                itemId: 'title'
            },{
                xtype: 'container',
                itemId: 'policySettings',
                cls: 'x-fieldset-separator-top',
                flex: 1,
                defaults: {
                    margin: '12 32 0'
                },
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                items: [{
                    xtype: 'displayfield',
                    itemId: 'warning',
                    hidden: true,
                    cls: 'x-form-field-info',
                    maxWidth: maxFormWidth,
                    margin: '18 32 0'
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'text',
                    layout: 'anchor',
                    maxWidth: maxFormWidth,
                    defaults: {
                        anchor: '100%'
                    },
                    setValues: function(data){
                        var limits = data.settings.limits,
                            field = this.down('[name="value"]');
                        field.emptyText = data.config.emptyText || ' ';
                        field.hideIcons();
                        if (data.config.icons) {
                            Ext.Object.each(data.config.icons, function(icon){
                                field.toggleIcon(icon, true);
                            });
                        }
                        field.applyEmptyText();
                        field.setValue(limits.value);
                    },
                    getValues: function(){
                        return this.getFieldValues();
                    },
                    items: [{
                        xtype: 'textfield',
                        name: 'value',
                        icons: {
                            globalvars: true
                        }
                    }]
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'textarea',
                    layout: 'anchor',
                    maxWidth: maxFormWidth,
                    defaults: {
                        anchor: '100%'
                    },
                    setValues: function(data){
                        var limits = data.settings.limits,
                            field = this.down('[name="value"]');
                        field.emptyText = data.config.emptyText;
                        field.applyEmptyText();
                        field.setValue(limits.value);
                    },
                    getValues: function(){
                        return this.getFieldValues();
                    },
                    items: [{
                        xtype: 'textarea',
                        name: 'value',
                        height: 26,
                        resizable: {
                            handles: 's',
                            heightIncrement: 26,
                            pinned: true
                        },

                        mode: null,
                        setMode: function(mode, resize) {
                            if (mode !== this.mode) {
                                if (mode == 'multi') {
                                    this.mode = 'multi';
                                    this.inputEl.setStyle('overflow', 'auto');
                                    if (resize) {
                                        this.setHeight(78);
                                    }
                                } else {
                                    this.mode = 'single';
                                    this.inputEl.setStyle('overflow', 'hidden');
                                }
                            }
                        },
                        listeners: {
                            resize: function(comp, width, height) {
                                comp.el.parent().setSize(comp.el.getSize());//fix resize wrapper for flex element
                                comp.setMode(comp.inputEl.getHeight() > 26 ? 'multi' : 'single', false);
                            }
                        }
                    }]
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'securityGroups',
                    layout: 'anchor',
                    maxWidth: maxFormWidth,
                    defaults: {
                        anchor: '100%'
                    },
                    setValues: function(data){
                        var limits = data.settings.limits,
                            field = this.down('[name="value"]');
                        field.emptyText = data.config.emptyText || ' ';
                        field.applyEmptyText();
                        this.setFieldValues(limits);
                    },
                    getValues: function(){
                        var result = this.isValidFields() ? this.getFieldValues() : null;
                        if (result) {
                            result['allow_additional_sec_groups'] = result['allow_additional_sec_groups'] ? 1 : 0;
                        }
                        return result;
                    },
                    items: [{
                        xtype: 'textarea',
                        name: 'value',
                        height: 26,
                        resizable: {
                            handles: 's',
                            heightIncrement: 26,
                            pinned: true
                        },
                        maskRe: /[\w+=,.@-]/i,
                        regex: /^[\w+=,.@-]*$/,
                        regexText: 'SG names must be alphanumeric, including the following common characters: plus (+), equal (=), comma (,), period (.), at (@), and dash (-).',

                        mode: null,
                        setMode: function(mode, resize) {
                            if (mode !== this.mode) {
                                if (mode == 'multi') {
                                    this.mode = 'multi';
                                    this.inputEl.setStyle('overflow', 'auto');
                                    if (resize) {
                                        this.setHeight(78);
                                    }
                                } else {
                                    this.mode = 'single';
                                    this.inputEl.setStyle('overflow', 'hidden');
                                }
                            }
                        },
                        listeners: {
                            resize: function(comp, width, height) {
                                comp.el.parent().setSize(comp.el.getSize());//fix resize wrapper for flex element
                                comp.setMode(comp.inputEl.getHeight() > 26 ? 'multi' : 'single', false);
                            }
                        }
                    },{
                        xtype: 'checkbox',
                        name: 'allow_additional_sec_groups',
                        inputValue: 1,
                        boxLabel: '&nbsp;Allow user to specify additional security groups'
                    }]
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'awsIAM',
                    layout: 'anchor',
                    maxWidth: maxFormWidth,
                    defaults: {
                        anchor: '100%'
                    },
                    setValues: function(data){
                        var limits = data.settings.limits,
                            field = this.down('[name="iam_instance_profile_arn"]');
                        field.emptyText = data.config.emptyText || ' ';
                        field.applyEmptyText();

                        this.setFieldValues(limits);
                    },
                    getValues: function(){
                        return this.isValidFields() ? this.getFieldValues(true) : null;
                    },
                    items: [{
                        xtype: 'textfield',
                        name: 'iam_instance_profile_arn',
                        fieldLabel: 'IAM profile name(s)',
                        labelWidth: 130,
                        maskRe: /[\w+=,.@-]/i,
                        regex: /^[\w+=,.@-]*$/,
                        regexText: 'Instance profile names must be alphanumeric, including the following common characters: plus (+), equal (=), comma (,), period (.), at (@), and dash (-).'
                    }]
                },{
                    xtype: 'notagridview',
                    hidden: true,
                    tab: true,
                    itemId: 'instancetype',
                    flex: 1,
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var me = this,
                            limits = data.settings.limits;
                        callback = function(data, status) {
                            if (data && data.length) {
                                me.addItems(Ext.Array.map(data, function(item){
                                    return {
                                        itemData: {
                                            settings: {
                                                name: (new Ext.XTemplate('<b>{name}</b> ({[this.instanceTypeInfo(values)]})').apply(item)),
                                                id: item.id,
                                                'default': limits['default'] === item.id,
                                                enabled: Ext.Array.contains(limits.value, item.id) ?  1 : 0
                                            }
                                        }
                                    };
                                }), false);
                            } else {
                                me.showEmptyText('Unable to load instance types');
                                panel.down('#rightcol').disableButtons();
                                me.removeItems();
                            }
                        };
                        Scalr.loadInstanceTypes(data.platform, '', callback);
                    },
                    getValues: function(){
                        var limits = {value:[], 'default': null};
                        this.getItems().each(function(item){
                            if (item.down('[name="enabled"]').getValue() === 1) {
                                limits.value.push(item.itemData.settings.id);
                            }
                            if (item.down('[name="default"]').getValue()) {
                                limits['default'] = item.itemData.settings.id;
                            }
                        });
                        return limits;
                    },
                    columns: [{
                        name: 'default',
                        title: 'Default',
                        header: {
                            width: 84
                        },
                        defaultValue: 0,
                        control: {
                            xtype: 'radio',
                            width: 84,
                            listeners: {
                                change: function(comp, value){
                                    if (value) {
                                        comp.ownerCt.down('[name="enabled"]').setValue(1);
                                    }
                                }
                            }
                        }
                    },{
                        name: 'enabled',
                        title: 'Available instance types',
                        header: {
                            flex: 1
                        },
                        defaultValue: 0,
                        control: {
                            xtype: 'buttongroupfield',
                            defaults: {
                                width: 40
                            },
                            margin: '0 18 0 0',
                            items: [{
                                text: 'On',
                                value: 1
                            },{
                                text: 'Off',
                                value: 0
                            }],
                            listeners: {
                                beforetoggle: function(comp, value){
                                    if (value === 0 && comp.ownerCt.ownerCt.down('[name="default"]').getValue()) {
                                        Scalr.message.Warning('Default item can\'t be disabled.');
                                        return false;
                                    }
                                }
                            }
                        }
                    },{
                        name: 'name',
                        header: false,
                        control: {
                            xtype: 'displayfield',
                            fieldStyle: 'color:#000'
                        }
                    }]
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'vpc',
                    flex: 1,
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var limits = data.settings.limits,
                            regionsField = this.down('#regions'),
                            valueField = this.down('[name="value"]'),
                            vpsidsField = this.down('#vpcids'),
                            vpcIdsValues = limits['ids'] || {},
                            vpcIdsList = [];

                        this.suspendLayouts();

                        valueField.reset();
                        valueField.setValue(limits['value'] ? true : false);

                        regionsField.addItems(Ext.Array.map(Ext.Object.getKeys(moduleParams['platforms']['ec2']), function(region){
                            var regionValue = limits['regions'][region] || {},
                                ids = regionValue['ids'] || [];
                            Ext.Array.each(ids, function(id){
                                var item = {
                                    region: region,
                                    name: id,
                                    type: ''
                                };
                                if (Ext.isString(vpcIdsValues[id])) {
                                    item['internet'] = vpcIdsValues[id];
                                    item['type'] = 'internet';
                                } else if (Ext.isArray(vpcIdsValues[id])) {
                                    item['ids'] = vpcIdsValues[id];
                                    item['type'] = 'ids';
                                }
                                vpcIdsList.push({itemData: {settings: item}});
                            });
                            return {
                                itemData: {
                                    settings: {
                                        'default': regionValue['default'] || 0,
                                        ids: ids,
                                        name: region,
                                        enabled: limits['regions'][region] !== undefined ?  1 : 0
                                    }
                                }
                            };
                        }), false);

                        if (vpcIdsList.length > 0) {
                            vpsidsField.addItems(vpcIdsList, false);
                            vpsidsField.show();
                        } else {
                            vpsidsField.removeItems();
                            vpsidsField.hide();
                        }
                        this.toggleControls(limits['value'] ? true : false);

                        this.resumeLayouts(true);
                    },
                    getValues: function(){
                        var regionsField = this.down('#regions'),
                            vpsidsField = this.down('#vpcids'),
                            value = {value: this.down('[name="value"]').getValue() ? 1 : 0, regions:{}, ids: {}};
                        regionsField.getItems().each(function(item){
                            if (item.down('[name="enabled"]').getValue() === 1) {
                                value.regions[item.itemData.settings.name] = {
                                    'default': item.down('[name="default"]').getValue() ? 1 : 0,
                                    ids: item.down('[name="ids"]').getValue()
                                };
                            }
                        });
                        vpsidsField.getItems().each(function(item){
                            var type = item.down('[name="type"]').getValue();
                            if (type !== '') {
                                value.ids[item.itemData.settings.name] = item.down('[name="' + type + '"]').getValue();
                            }
                        });
                        return value;
                    },
                    toggleControls: function(enabled) {
                        this.down('#regions').setDisabled(!enabled);
                        this.down('#vpcids').setDisabled(!enabled);
                    },
                    items: [{
                        xtype: 'buttonfield',
                        name: 'value',
                        margin: '0 0 12 3',
                        maxWidth: 300,
                        enableToggle: true,
                        text: 'Require ALL farms to launch in a VPC',
                        listeners: {
                            toggle: function(comp, pressed){
                                comp.up('#vpc').toggleControls(pressed);
                            }
                        }
                    },{
                        xtype: 'container',
                        flex: 1,
                        itemId: 'settings',
                        layout: {
                            type: 'vbox',
                            align: 'stretch'
                        },
                        items: [{
                            xtype: 'notagridview',
                            itemId: 'regions',
                            flex: 1.8,
                            maxHeight: 370,
                            columns: [{
                                name: 'default',
                                title: 'Default',
                                header: {
                                    width: 84
                                },
                                defaultValue: 0,
                                control: {
                                    xtype: 'radio',
                                    width: 64,
                                    margin: '0 0 0 20',
                                    listeners: {
                                        change: function(comp, value){
                                            if (value) {
                                                comp.ownerCt.down('[name="enabled"]').setValue(1);
                                            }
                                        }
                                    }
                                }
                            },{
                                name: 'name',
                                title: 'Region',
                                width: 134,
                                header: {
                                    width: 134
                                }
                            },{
                                name: 'enabled',
                                title: 'Allowed',
                                header: {
                                    width: 110
                                },
                                defaultValue: 0,
                                extendInitialConfig: function(config, itemData){
                                    config.fieldLabel = itemData.settings.name;
                                },
                                control: {
                                    xtype: 'buttongroupfield',
                                    labelWidth: 134,
                                    labelSeparator: '',
                                    width: 244,
                                    defaults: {
                                        width: 40
                                    },
                                    items: [{
                                        text: 'On',
                                        value: 1
                                    },{
                                        text: 'Off',
                                        value: 0
                                    }],
                                    listeners: {
                                        change: function(comp, value){
                                            var fieldIds = comp.ownerCt.down('[name="ids"]');
                                            fieldIds.setVisible(value === 1);
                                            fieldIds.setValue(null);
                                        },
                                        beforetoggle: function(comp, value){
                                            if (value === 0 && comp.ownerCt.ownerCt.down('[name="default"]').getValue()) {
                                                Scalr.message.Warning('Default item can\'t be disabled.');
                                                return false;
                                            }
                                        }
                                    }
                                }
                            },{
                                name: 'ids',
                                title: 'Allowed VPCs',
                                header: {
                                    flex: 1
                                },
                                extendInitialConfig: function(config, itemData){
                                    config.hidden = itemData.settings.enabled !== 1;
                                    config.store.data = Ext.Array.map(itemData.settings.ids, function(id){return {id: id, name: id}});
                                    config.store.proxy.params = {cloudLocation: itemData.settings.name};
                                },
                                control: {
                                    xtype: 'comboboxselect',
                                    displayField: 'name',
                                    valueField: 'id',
                                    emptyText: 'No limits',
                                    columnWidth: 1,
                                    flex: 1,
                                    //margin: '0 50 0 0',

                                    queryCaching: false,
                                    store: {
                                        fields: ['id', 'name'],
                                        proxy: {
                                            type: 'cachedrequest',
                                            crscope: 'governance',
                                            url: '/platforms/ec2/xGetVpcList',
                                            root: 'vpc'
                                        }
                                    },
                                    listeners: {
                                        change: function(comp) {
                                            var c = comp.up('#vpc');
                                            if (c) {
                                                c.down('#vpcids').updateItems(comp.ownerCt.itemData.settings.name, comp.getValue());
                                            }
                                        }
                                    }
                                }
                            }]
                        },{
                            xtype: 'notagridview',
                            itemId: 'vpcids',
                            title: 'Internet access & subnets restrictions',
                            hidden: true,
                            flex:1,
                            minHeight: 160,
                            margin: '18 0',
                            //headerls: 'x-fieldset-separator-top',
                            updateItems: function(region, list) {
                                var me = this, left = [];
                                me.getItems().each(function(item){
                                    if (item.itemData.settings.region === region) {
                                        if (!Ext.Array.contains(list, item.itemData.settings.name)) {
                                            me.removeItem(item);
                                        } else {
                                            left.push(item.itemData.settings.name);
                                        }
                                    }
                                });
                                me.addItems(Ext.Array.map(list, function(vpcid){
                                    return !Ext.Array.contains(left, vpcid) ? {
                                        itemData: {
                                            settings: {region: region, ids: [], name: vpcid}
                                        }
                                    } : undefined;
                                }));
                                me.setVisible(me.getItems().length > 0);
                            },
                            columns: [{
                                name: 'name',
                                title: 'VPC ID',
                                header: {
                                    width: 134
                                }
                            },{
                                name: 'type',
                                title: 'Restrict',
                                header: {
                                    flex: 1
                                },
                                defaultValue: '',
                                extendInitialConfig: function(config, itemData){
                                    config.fieldLabel = itemData.settings.name;
                                },
                                control: {
                                    xtype: 'combo',
                                    labelWidth: 134,
                                    labelSeparator: '',
                                    width: 300,
                                    editable: false,
                                    margin: '0 20 0 0',
                                    store: [
                                        ['', 'No limits'],
                                        ['internet', 'Type'],
                                        ['ids', 'To specific subnet(s)']
                                    ],
                                    name: 'type',
                                    listeners: {
                                        change: function(comp, value){
                                            comp.ownerCt.suspendLayouts();
                                            comp.ownerCt.down('[name="ids"]').setVisible(value === 'ids');
                                            comp.ownerCt.down('[name="internet"]').setVisible(value === 'internet');
                                            comp.ownerCt.resumeLayouts(true);
                                        }
                                    }
                                }
                            },{
                                name: 'internet',
                                header: false,
                                extendInitialConfig: function(config, itemData){
                                    config.hidden = itemData.settings.type !== 'internet';
                                    config.value = itemData.settings.internet || 'outbound-only';
                                },
                                control: {
                                    xtype: 'buttongroupfield',
                                    name: 'internet',
                                    defaults: {
                                        width: 120
                                    },
                                    items: [{
                                        text: 'Private',
                                        value: 'outbound-only'
                                    },{
                                        text: 'Public',
                                        value: 'full'
                                    }]
                                }
                            },{
                                name: 'ids',
                                header: false,
                                extendInitialConfig: function(config, itemData){
                                    config.hidden = itemData.settings.type !== 'ids';
                                    config.store.data = Ext.Array.map(itemData.settings.ids || [], function(id){return {id: id, description: id}});
                                    config.store.proxy.params = {
                                        cloudLocation: itemData.settings.region,
                                        vpcId: itemData.settings.name,
                                        extended: 1
                                    };
                                },
                                control: {
                                    xtype: 'comboboxselect',
                                    name: 'ids',
                                    displayField: 'description',
                                    valueField: 'id',
                                    columnWidth: 1,
                                    flex: 1,
                                    margin: '0 10 0 0',
                                    clearDataBeforeQuery: true,
                                    queryCaching: false,
                                    store: {
                                        fields:['id', 'name', 'description', 'ips_left', 'type', 'availability_zone', 'cidr'],
                                        proxy: {
                                            type: 'cachedrequest',
                                            crscope: 'governance',
                                            url: '/tools/aws/vpc/xListSubnets'
                                        }
                                    },
                                    listConfig: {
                                        style: 'white-space:nowrap',
                                        cls: 'x-boundlist-alt',
                                        tpl: '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto;line-height:20px">' +
                                                '<div><span style="font-weight: bold">{[values.name || \'<i>No name</i>\' ]} - {id}</span> <span style="font-style: italic;font-size:90%">(Type: <b>{type:capitalize}</b>)</span></div>' +
                                                '<div>{cidr} in {availability_zone} [IPs left: {ips_left}]</div>' +
                                            '</div></tpl>'
                                    }
                                }
                            }]
                        }]
                    }]
                },{
                    xtype: 'notagridview',
                    itemId: 'networks',
                    hidden: true,
                    tab: true,
                    flex: 1,
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var me = this,
                            limits = data.settings.limits,
                            cloudFamily = data.platform;
                        if (Scalr.isCloudstack(data.platform)) {
                            cloudFamily = 'cloudstack';
                        } else if (Scalr.isOpenstack(data.platform)) {
                            cloudFamily = 'openstack';
                        }

                        me.removeItems();
                        Scalr.CachedRequestManager.get('governance').load(
                            {
                                url: '/platforms/'+cloudFamily+'/xGetNetworks/',
                                params: {
                                    platform: data.platform
                                }
                            },
                            function(data, status){
                                var items = [];
                                data = data || {};
                                if (!Ext.Object.getSize(data)) {
                                    me.removeItems();
                                    me.showEmptyText(status ? 'Networking governance is not available for your private cloud' : 'Unable to load networks list');
                                    panel.down('#rightcol').disableButtons();
                                } else {
                                    Ext.Object.each(data, function(region, networks){
                                        items.push({
                                            itemData: {
                                                networks: networks,
                                                settings: {
                                                    name: region,
                                                    ids: limits.value[region] || []
                                                }
                                            }
                                        });
                                    });
                                    me.addItems(items);
                                }
                            }
                        );
                    },
                    getValues: function(){
                        var limits = {value: {}};
                        this.getItems().each(function(item){
                            limits.value[item.itemData.settings.name] = item.down('[name="ids"]').getValue();
                        });
                        return limits;
                    },
                    columns: [{
                        name: 'name',
                        title: 'Region',
                        width: 134,
                        header: {
                            width: 134
                        },
                        control: {
                            xtype: 'displayfield',
                            fieldStyle: 'font-weight:bold;color:#000',
                            width: 134
                        }
                    },{
                        name: 'ids',
                        title: 'Allowed Networks',
                        header: {
                            flex: 1
                        },
                        extendInitialConfig: function(config, itemData){
                            config.store.data = itemData.networks;
                        },
                        control: {
                            xtype: 'comboboxselect',
                            displayField: 'name',
                            valueField: 'id',
                            emptyText: 'No limits',
                            columnWidth: 1,
                            queryMode: 'local',
                            store: {
                                fields: ['id', 'name'],
                                proxy: 'object'
                            },
                            flex: 1,
                            margin: '0 10 0 0'
                        }
                    }]
                },{
                    xtype: 'governancelease',
                    itemId: 'lease',
                    hidden: true,
                    tab: true,
                    flex: 1,
                    margin: 0,
                    autoScroll: true
                },{
                    xtype: 'container',
                    flex: 1,
                    hidden: true,
                    tab: true,
                    itemId: 'tags',
                    layout: 'fit',
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var limits = data.settings.limits,
                            grid = this.down('grid'),
                            savedTags = limits.value || {},
                            savedTagsCount = Ext.Object.getSize(savedTags),
                            tags = [];
                        Ext.Object.each(data.config.defaultTags, function(tagName, tagData){
                            if (tagData.readOnly && savedTags[tagName] === undefined || !tagData.readOnly && savedTagsCount === 0) {
                                tags.push({
                                    name: tagName,
                                    value: tagData.value,
                                    readOnly: tagData.readOnly
                                });
                            }
                        });
                        Ext.Object.each(savedTags, function(name, value){
                            tags.push({
                                name: name,
                                value: value
                            });
                        });
                        grid.store.loadData(tags);
                    },
                    getValues: function(config){
                        var valid = true,
                            grid = this.down('grid'),
                            cellEditing = grid.getPlugin('cellediting'),
                            result = {};
                        (grid.store.snapshot || grid.store.data).each(function(record){
                            var name = record.get('name'),
                                value = record.get('value');
                            if (!name) {
                                cellEditing.startEdit(record, 0);
                                cellEditing.context.column.field.validate();
                                valid = false;
                                return false;
                            } else if (!config.defaultTags[name] || config.defaultTags[name].value != value) {
                                result[name] = value;
                            }
                        });
                        return valid ? {value: result} : null;
                    },
                    items: [{
                        xtype: 'grid',
                        flex: 1,
                        cls: 'x-grid-shadow x-grid-no-highlighting',
                        store: {
                            fields: ['name', 'value', 'readOnly'],
                            proxy: 'object'
                        },
                        features: {
                            ftype: 'addbutton',
                            text: 'Add new tag',
                            maxCount: 10,
                            handler: function(view) {
                                view.up().store.add({});
                            }
                        },
                        plugins: [
                            Ext.create('Ext.grid.plugin.CellEditing', {
                                pluginId: 'cellediting',
                                clicksToEdit: 1,
                                listeners: {
                                    beforeedit: function(editor, o) {
                                        if (o.column.isEditable) {
                                            return o.column.isEditable(o.record);
                                        }
                                    }
                                }
                            })
                        ],
                        listeners: {
                            viewready: function() {
                                var view = this.view;
                                cb = function() {
                                    view.findFeature('addbutton').setDisabled((view.store.snapshot || view.store.data).length >= 10, 'Tags limit of 10 reached');
                                }
                                this.store.on({
                                    add: cb,
                                    remove: cb
                                });
                            },
                            itemclick: function (view, record, item, index, e) {
                                if (e.getTarget('img.x-icon-action-delete')) {
                                    var selModel = view.getSelectionModel();
                                    if (record === selModel.getLastFocused()) {
                                        selModel.deselectAll();
                                        selModel.setLastFocused(null);
                                    }
                                    view.store.remove(record);
                                    if (!view.store.getCount()) {
                                        view.up().store.add({});
                                    }
                                    return false;
                                }
                            }
                        },
                        columns: [{
                            header: 'Name',
                            sortable: false,
                            resizable: false,
                            dataIndex: 'name',
                            flex: 1,
                            editor: {
                                xtype: 'textfield',
                                editable: false,
                                margin: '0 12 0 13',
                                fixWidth: -25,
                                maxLength: 127,
                                //validateOnChange: false,
                                allowBlank: false
                            },
                            isEditable: function(record) {
                                return !record.get('readOnly');
                            },
                            renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                                var column = grid.panel.columns[colIndex],
                                   valueEncoded = Ext.String.htmlEncode(value);
                                return  '<div class="x-form-text" style="background:#fff;padding:2px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;cursor:text"  data-qtip="'+valueEncoded+'">'+
                                            (record.get('readOnly') ? '<span style="color:#999">' + valueEncoded + '</span>' : valueEncoded) +
                                        '</div>';
                            }
                        },{
                            header: 'Value',
                            sortable: false,
                            resizable: false,
                            dataIndex: 'value',
                            flex: 2,
                            editor: {
                                xtype: 'textfield',
                                editable: false,
                                margin: '0 12 0 13',
                                fixWidth: -25,
                                maxLength: 255,
                                //validateOnChange: false,
                                allowBlank: false
                            },
                            isEditable: function(record) {
                                return !record.get('readOnly');
                            },
                            renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                                var column = grid.panel.columns[colIndex],
                                    valueEncoded = Ext.String.htmlEncode(value);
                                return  '<div class="x-form-text" style="background:#fff;padding:2px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;cursor:text"  data-qtip="'+valueEncoded+'">'+
                                            (record.get('readOnly') ? '<span style="color:#999">' + valueEncoded + '</span>' : valueEncoded) +
                                        '</div>';
                            }
                        },{
                            renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                                var result = '<img style="cursor:pointer;margin-top:6px;';
                                if (record.get('readOnly')) {
                                    result += 'cursor:default;opacity:.4" width="16" height="16" class="x-icon-server-action x-icon-lock" title="This tag is used by Scalr and cannot be changed or removed"';
                                } else {
                                    result += '" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Delete tag"';
                                }
                                result += ' src="'+Ext.BLANK_IMAGE_URL+'"/>';
                                return result;
                            },
                            width: 42,
                            sortable: false,
                            align:'left'

                        }]
                    }]
                }]
            }],
            dockedItems:[{
                xtype: 'container',
                itemId: 'toolbar',
                dock: 'bottom',
                cls: 'x-docked-buttons',
                maxWidth: maxFormWidth,
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                items: [{
                    xtype: 'button',
                    itemId: 'save',
                    text: 'Save policy',
                    handler: function() {
                        panel.down('#options').saveOption();
                    }
                }, {
                    xtype: 'button',
                    itemId: 'enforce',
                    text: 'Save & Enforce',
                    handler: function() {
                        panel.down('#options').saveOption(1);
                        this.up('#rightcol').setToolbar(1);
                    }
                }, {
                    xtype: 'button',
                    itemId: 'disable',
                    text: 'Disable policy',
                    handler: function() {
                        panel.down('#options').saveOption(0);
                        this.up('#rightcol').setToolbar(0);
                    }
                }, {
                    xtype: 'button',
                    itemId: 'cancel',
                    text: 'Cancel',
                    margin: '0 0 0 24',
                    handler: function() {
                        panel.down('#options').getSelectionModel().deselectAll();
                    }
                }]
            }]
        }],
		dockedItems: [{
            xtype: 'container',
            itemId: 'tabs',
            dock: 'left',
            cls: 'x-docked-tabs',
            width: 112 + Ext.getScrollbarSize().width,
            overflowY: 'auto',
            defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'above',
                disableMouseDownPressed: true,
                toggleGroup: 'governance-tabs',
                toggleHandler: function(field, state) {
                    if (state) {
                        panel.fireEvent('selectplatform', this.value);
                    } else {
                        panel.fireEvent('deselectplatform', this.value);
                    }
                }
            },
            items: platformsTabs
        }],
		listeners: {
			boxready: function () {
				this.getDockedComponent('tabs').items.first().toggle(true);
			},
			selectplatform: function(platform) {
                panel.getComponent('options').store.loadData(Ext.Array.map(config[platform]['options'], function(optionConfig){
                    governanceSettings[platform] = governanceSettings[platform] || {};
                    if (governanceSettings[platform][optionConfig.name] === undefined) {
                        governanceSettings[platform][optionConfig.name] = {limits: optionConfig.defaults};
                        if (optionConfig.alwaysEnabled) {
                            governanceSettings[platform][optionConfig.name]['enabled'] = 1;
                        }
                    }
                    return {
                        platform: platform,
                        config: optionConfig,
                        settings: governanceSettings[platform][optionConfig.name]
                    }
                }));
			}
		}
	});
	return panel;
});
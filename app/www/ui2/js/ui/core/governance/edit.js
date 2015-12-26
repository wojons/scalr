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
            defaults: {
                value: '',
                allow_additional_sec_groups: 0
            },
            subheader: 'Set security groups list that will be applied to all instances.',
            warning: 'Please ensure that the security groups that you list already exist within your cloud setup. Scalr WILL NOT create these groups and instances will fail to launch otherwise.'
        },{
            name: 'openstack.tags',
            title: 'Metadata',
            type: 'tags',
            defaults: {
                value: {}
            },
            tagsLimit: 0,
            subheader: 'Define metadata name-value pairs that should be automatically assigned to every instance. Enforcing this policy will prevent users from adding additional metadata.',
            warning: 'Global Variable Interpolation is supported for metadata values <img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-globalvars" style="vertical-align:top;position:relative;top:2px" />'+
                     '<br/><i>Scalr reserves some <a href="https://scalr-wiki.atlassian.net/wiki/x/MwAeAQ" target="_blank">metadata name-value pairs</a> to configure the Scalarizr agent.</i>'
        },{
            name: 'openstack.instance_name_format',
            title: 'Instance name',
            type: 'text',
            emptyText: '{SCALR_SERVER_ID}',
            defaults: {
                value: ''
            },
            subheader: 'Define a instance name format that will be used for all instances.',
            icons: ['globalvars']
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
                icons: ['globalvars']
            },{
                name: 'general.chef',
                title: 'Chef',
                type: 'chef',
                defaults: {
                    value: '',
                    servers: {}
                },
                subheader: 'Limit chef servers and environments.'
            }]
        },
        ec2: {
            title: '<span class="small">Amazon<br/>Web Services</span>',
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
                title: 'Instance types',
                group: 'EC2',
                type: 'instancetype',
                defaults: {
                    value: ['m1.small'],
                    'default': 'm1.small'
                },
                subheader: instanceTypeDescription
            },{
                name: 'aws.ssh_key_pair',
                title: 'SSH key pairs',
                group: 'EC2',
                type: 'text',
                defaults: {
                    value: ''
                },
                subheader: 'Set a common SSH key pair for all instances.',
                warning: 'Make sure this key pair already exists within your EC2 setup. Scalr WILL NOT create this pair and instances will fail to launch otherwise.'
            },{
                name: 'aws.instance_name_format',
                title: 'Instance name',
                group: 'EC2',
                type: 'text',
                defaults: {
                    value: ''
                },
                subheader: 'Define a instance name format that will be used for all instances.',
                emptyText: '{SCALR_FARM_NAME} -> {SCALR_FARM_ROLE_ALIAS} #{SCALR_INSTANCE_INDEX}',
                icons: ['globalvars']
            },{
                name: 'aws.additional_security_groups',
                title: 'Security groups',
                type: 'securityGroups',
                maxCount: moduleParams['scalr.aws.ec2.limits.security_groups_per_instance'],
                separateWindowsSgs: true,
                extendedAdditionalSgs: true,
                group: 'EC2',
                defaults: {
                    value: '',
                    allow_additional_sec_groups: 0
                },
                subheader: 'Set security groups list that will be applied to all instances.',
                warning: 'Please ensure that the security groups that you list already exist within your EC2 setup. Scalr WILL NOT create these groups and instances will fail to launch otherwise.'
            },{
                name: 'aws.elb_additional_security_groups',
                title: 'Security groups',
                type: 'securityGroups',
                maxCount: moduleParams['scalr.aws.ec2.limits.security_groups_per_instance'],
                extendedAdditionalSgs: true,
                group: 'ELB',
                defaults: {
                    value: '',
                    allow_additional_sec_groups: 0
                },
                subheader: 'Set security groups list that will be applied to all ELBs.',
                warning: 'Please ensure that the security groups that you list already exist within your EC2 setup. Scalr WILL NOT create these groups and instances will fail to launch otherwise.'
            },{
                name: 'aws.iam',
                title: 'IAM profiles',
                type: 'awsIAM',
                defaults: {
                    value: '',
                    iam_instance_profile_arn: ''
                },
                subheader: 'Limit which IAM instance profiles can be applied to instances.',
                warning: 'List the names of all IAM instance profiles you want to ALLOW. Users will be limited to only these profiles in Farm Designer.<br/>' +
                         '<span class="x-semibold">Choose profiles that ALREADY EXIST in your EC2 account. Scalr will NOT CREATE any new profiles.</span>',
                emptyText: 'ex. Name1,Name2,...'
            },{
                name: 'aws.tags',
                title: 'Tagging',
                group: 'EC2',
                type: 'tags',
                defaults: {
                    value: {},
                    allow_additional_tags: 0
                },
                tagsLimit: 10,
                subheader: 'Define tags that should be automatically assigned to every EC2 instance and EBS volume. Enforcing this policy will prevent users from adding additional tags.',
                warning: 'Global Variable Interpolation is supported for tag values <img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-globalvars" style="vertical-align:top;position:relative;top:2px" />'
            },{
                name: 'aws.rds',
                title: 'Farm association',
                group: 'RDS',
                type: 'awsRDS',
                defaults: {
                    value: '',
                    db_instance_requires_farm_association: 0
                },
                subheader: 'RDS-related restriction.'
            },{
                name: 'aws.rds_additional_security_groups',
                title: 'Security groups',
                type: 'securityGroups',
                maxCount: 5,
                extendedAdditionalSgs: true,
                group: 'RDS',
                defaults: {
                    value: '',
                    allow_additional_sec_groups: 0
                },
                subheader: 'Set security groups list that will be applied to all RDS instances.',
                warning: 'Please ensure that the security groups that you list already exist within your EC2 setup. Scalr WILL NOT create these groups and instances will fail to launch otherwise.'
            },{
                name: 'aws.kms_keys',
                title: 'KMS keys',
                type: 'encryptionkeys',
                defaults: {
                    value: []
                },
                subheader: 'Limit the Encryption Keys that can be used within Scalr.'
            }]
        },
        azure: {
            title: Scalr.utils.getPlatformName('azure'),
            options: [{
                name: 'azure.cloud_location',
                title: 'Regions',
                type: 'list',
                url: '/platforms/azure/xGetResourceGroups',
                root: 'cloudLocations',
                defaults: {value: ['eastus'], 'default': 'eastus'},
                subheader: 'Limit regions that instances can be launch in.'
            },{
                name: 'azure.resource-group',
                title: 'Resource groups',
                type: 'list',
                url: '/platforms/azure/xGetResourceGroups',
                root: 'resourceGroups',
                defaults: {value: []},
                subheader: 'Limit resource groups'
            },{
                name: 'azure.vm-size',
                title: 'Instance types',
                type: 'instancetypebyregion',
                defaults: {value: {}},
                subheader: instanceTypeDescription
            },{
                name: 'azure.tags',
                title: 'Tagging',
                type: 'tags',
                defaults: {
                    value: {}
                },
                tagsLimit: 0,
                subheader: 'Define tags that should be automatically assigned to every Azure VM. Enforcing this policy will prevent users from adding additional tags.',
                warning: 'Global Variable Interpolation is supported for tag values <img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-globalvars" style="vertical-align:top;position:relative;top:2px" />'
            }]
        }
    };

    Ext.Object.each(moduleParams['platforms'], function(key, value){
        if (Scalr.isOpenstack(key, false)) {
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
        iconCls: 'scalr-ui-core-governance-icon-general'
    });

    var governanceSettings = Ext.isObject(moduleParams['values']) ? moduleParams['values'] : {};
    var maxFormWidth = 820;

	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			maximize: 'all',
            menuTitle: 'Governance',
            menuHref: '#/core/governance',
            menuFavorite: true
		},
        stateId: 'panel-core-governance-edit',
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
            maxWidth: 400,
            itemId: 'options',
            padding: '12 0',
            cls: 'x-panel-column-left x-grid-with-bigger-rows',
            viewConfig: {
                selectedRecordFocusCls: ''
            },
            features: [{
                id:'grouping',
                ftype:'grouping',
                collapsible: false,
                groupHeaderTpl: Ext.create('Ext.XTemplate',
                    '{children:this.getGroupName}',
                    {
                        getGroupName: function(children) {
                            if (children.length > 0) {
                                var name = children[0].get('group'),
                                    enabledCnt = 0;
                                Ext.each(children, function(child){
                                    enabledCnt += child.get('settings')['enabled'] == 1 ? 1 : 0;
                                });
                                return name + '<span style="font-weight:normal;font-family:OpenSansRegular"> ('+enabledCnt+' of '+children.length+' policies enforced)</span>';
                            }
                        }
                    }
                )
            }],
            plugins: {
                ptype: 'focusedrowpointer',
                thresholdOffset: 20,
                addOffset: 8
            },
            store:{
                fields: ['platform', 'config', 'settings', {name: 'group', defaultValue: ' General'}],
                proxy: 'object',
                groupField: 'group'
            },
            columns: [{
                header: 'Policy',
                dataIndex: 'config',
                flex: 1,
                sortable: false,
                resizable: false,
                renderer: function(value, meta){
                    return '<span class="x-semibold">' + value.title + '</span>';
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
                var me = this, value, limits,
                    rightcol = panel.down('#rightcol'),
                    record = me.getSelectionModel().getSelection()[0];
                value = me.getSelectionModel().getSelection()[0].getData();
                limits = rightcol.getOptionValue(value.config, enabled);
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
                    success: function (data) {
                        if (enabled !== undefined) {
                            rightcol.setToolbar(enabled);
                            rightcol.currentItem.fireEvent('statuschanged', enabled);
                        }
                        Scalr.governance = data.governance || {};//upadate governance in user context
                        Scalr.event.fireEvent('update', '/core/governance', value.platform, value.config.name);
                    }
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
            setToolbar: function(enabled, alwaysEnabled){
                var toolbar = this.getDockedComponent('toolbar');
                toolbar.down('#save').setVisible(enabled == 1 || alwaysEnabled).enable();
                toolbar.down('#disable').setVisible(enabled == 1 && !alwaysEnabled).enable();
                toolbar.down('#enforce').setVisible(enabled != 1 && !alwaysEnabled).enable();
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
                this.setToolbar(option.settings.enabled, option.config.alwaysEnabled);
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
            getOptionValue: function(config, enabled) {
                return this.currentItem.getValues(config, enabled);
            },
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-header-default',
                itemId: 'title'
            },{
                xtype: 'container',
                itemId: 'policySettings',
                cls: 'x-fieldset-separator-top',
                flex: 1,
                defaults: {
                    margin: '12 24 0'
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
                    margin: '18 24 0'
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
                            Ext.each(data.config.icons, function(icon){
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
                        plugins: {
                            ptype: 'fieldicons',
                            icons: [{id: 'globalvars'}]
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
                    flex: 1,
                    itemId: 'list',
                    layout: 'fit',
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var limits = data.settings.limits,
                            config = data.config,
                            value = limits.value || [],
                            defaultValue = limits.default || null,
                            listField = this.down('#listGrid');
                        Scalr.CachedRequestManager.get('governance').load(
                            {
                                url: config.url
                            },
                            function(data, status){
                                data = data || {};
                                if (!Ext.Object.getSize(data)) {
                                    panel.down('#rightcol').disableButtons();
                                    listField.store.removeAll();
                                } else {
                                    var listToLoad = [];
                                    if (Ext.isArray(data[config.root])) {
                                        Ext.each(data[config.root], function(item){
                                            listToLoad.push({
                                                id: item.id,
                                                'default': item.id === defaultValue,
                                                name: item.name,
                                                enabled: Ext.Array.contains(value, item.id) ? 1 : 0
                                            });
                                        });
                                    } else {
                                        Ext.Object.each(data[config.root], function(id, name){
                                            listToLoad.push({
                                                id: id,
                                                'default': defaultValue ? id === defaultValue : defaultValue = id,
                                                name: name,
                                                enabled: Ext.Array.contains(value, id) ? 1 : 0
                                            });
                                        });
                                    }
                                    listField.store.load({data: listToLoad});
                                }
                            }
                        );

                    },
                    getValues: function(){
                        var listField = this.down('#listGrid'),
                            value = [], defaultValue;
                        listField.store.getUnfiltered().each(function(record){
                            if (record.get('enabled')) {
                                value.push(record.get('id'));
                            }
                            if (record.get('default')) {
                                defaultValue = record.get('id');
                            }
                        });
                        return {
                            value: value,
                            default: defaultValue
                        };
                    },
                    items: [{
                        xtype: 'grid',
                        cls: 'x-grid-with-formfields',
                        itemId: 'listGrid',
                        store: {
                            fields: ['id', 'name', {name: 'default', type: 'boolean'}, 'enabled'],
                            proxy: 'object',
                            sorters: {
                                property: 'name'
                            }
                        },
                        trackMouseOver: false,
                        disableSelection: true,
                        columns: [{
                            text: 'Default',
                            sortable: false,
                            resizable: false,
                            width: 76,
                            dataIndex: 'default',
                            xtype: 'widgetcolumn',
                            align: 'center',
                            widget: {
                                xtype: 'radio',
                                name: 'default',
                                listeners: {
                                    change: function(comp, value){
                                        var record = comp.getWidgetRecord();
                                        if (record) {
                                            record.set('default', value);
                                            if (value) {
                                                record.set('enabled', 1)
                                            }
                                        }
                                    }
                                }
                            }
                        },{
                            text: 'Allowed',
                            sortable: false,
                            resizable: false,
                            dataIndex: 'enabled',
                            width: 110,
                            xtype: 'widgetcolumn',
                            align: 'center',
                            widget: {
                                xtype: 'buttongroupfield',
                                margin: '0 0 0 -6',
                                defaults: {
                                    width: 45,
                                    style: 'padding-left:0;padding-right:0'
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
                                        var record = comp.getWidgetRecord();
                                        if (record) {
                                            record.set('enabled', value);
                                            if (!value) {
                                                record.set('default', false);
                                            }
                                        }
                                    },
                                    beforetoggle: function(btn, value){
                                        var record = this.getWidgetRecord();
                                        if (value === 0 && record.get('default')) {
                                            Scalr.message.InfoTip('Default region can\'t be disabled.', btn.el, {anchor: 'bottom'});
                                            return false;
                                        }
                                    }
                                }
                            }
                        },{
                            text: 'Name',
                            sortable: false,
                            resizable: false,
                            flex: 1,
                            dataIndex: 'name'
                        }]
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
                    applyFieldSettings: function(settings) {
                        this.settings = settings;
                        this.down('[name="separateWindowsSgs"]').setVisible(settings.separateWindowsSgs);
                    },
                    setValues: function(data){
                        var limits = data.settings.limits || {};
                        
                        this.resetFieldValues();
                        this.applyFieldSettings(data.config);
                        
                        var values = {
                            allow_additional_sec_groups: limits['allow_additional_sec_groups'],
                            value: limits['value'] || ''
                        };

                        if (this.settings.separateWindowsSgs) {
                            values['windows'] = limits['windows'] || '';
                            values['separateWindowsSgs'] = limits['windows'] ? true : false
                        }
                        
                        if (this.settings.extendedAdditionalSgs) {
                            values['additional_sec_groups_list'] = limits['additional_sec_groups_list'] || '';
                            values['extendedAdditionalSgs'] = limits['additional_sec_groups_list'] ? 'list' : 'any';
                        }
                        
                        this.settings.skipMaxCountCheck = true;
                        this.setFieldValues(values);
                        this.settings.skipMaxCountCheck = false;
                    },
                    getValues: function(config, enabled){
                        var values,
                            field,
                            result = {};

                        values = this.getFieldValues();
                        if (enabled !== 0 && !values['allow_additional_sec_groups'] && Ext.isEmpty(values['value'])) {
                            field = this.down('[name="value"]');
                            field.markInvalid('This field is required');
                            field.focus();
                            return null;
                        }
                        
                        if (Ext.isArray(values['value'])) {
                            result['value'] = values['value'].join(',');
                        }
                        
                        result['allow_additional_sec_groups'] = values['allow_additional_sec_groups'] ? 1 : 0;
                        if (this.settings.separateWindowsSgs) {
                            if (values['separateWindowsSgs']) {
                                if (!Ext.isEmpty(values['windows'])) {
                                    result['windows'] = values['windows'].join(',');
                                } else if (enabled !== 0) {
                                    field = this.down('[name="windows"]');
                                    field.markInvalid('This field is required');
                                    field.focus();
                                    return null;
                                }
                            }
                        }

                        if (this.settings.extendedAdditionalSgs) {
                            if (values['allow_additional_sec_groups'] && values['extendedAdditionalSgs'] === 'list') {
                                if (!Ext.isEmpty(values['additional_sec_groups_list'])) {
                                    result['additional_sec_groups_list'] = values['additional_sec_groups_list'].join(',');
                                } else if (enabled !== 0) {
                                    field = this.down('[name="additional_sec_groups_list"]');
                                    field.validate();
                                    field.focus();
                                    return null;
                                }
                            }

                        }
                        return result;
                    },
                    items: [{
                        xtype: 'checkbox',
                        name: 'separateWindowsSgs',
                        boxLabel: 'Use a different set of Security Groups for <b>Windows</b> and <b>Linux</b> instances',
                        listeners: {
                            change: function(comp, value) {
                                var ct = this.up('#securityGroups');
                                ct.down('[name="windows"]').setVisible(!!value);
                                ct.down('[name="value"]').toggleIcon('linux', value);
                            }
                        }
                    },{
                        xtype: 'taglistfield',
                        name: 'value',
                        tagRegexText: 'SG names must be alphanumeric, including the following common characters: plus (+), equal (=), comma (,), period (.), at (@), dash (-) and space.',
                        tagRegex: /^\**[\w+=,.@\-][\w+=,.@\- *]*$/,
                        plugins: [{
                            ptype: 'fieldicons',
                            position: 'inner',
                            icons: [{
                                id: 'linux',
                                hidden: true,
                                iconCls: 'osfamily-small x-icon-osfamily-small-oel',
                                tooltip: 'Security groups that will be applied to all Linux instances'
                            }]
                        }],
                        listeners: {
                            beforeselect: function(comp, record) {
                                var settings = comp.up('#securityGroups').settings;
                                if (!settings.skipMaxCountCheck && settings.maxCount && comp.getValue().length >= settings.maxCount) {
                                    Scalr.message.InfoTip('Limit of ' + settings.maxCount + ' Security Groups per instance has been reached.', comp.inputEl, {anchor: 'bottom'});
                                    return false;
                                }
                            }
                        }
                    },{
                        xtype: 'taglistfield',
                        name: 'windows',
                        hidden: true,
                        tagRegexText: 'SG names must be alphanumeric, including the following common characters: plus (+), equal (=), comma (,), period (.), at (@), dash (-) and space.',
                        tagRegex: /^\**[\w+=,.@\-][\w+=,.@\- *]*$/,
                        plugins: [{
                            ptype: 'fieldicons',
                            position: 'inner',
                            icons: [{
                                id: 'windows',
                                iconCls: 'osfamily-small x-icon-osfamily-small-windows',
                                tooltip: 'Security groups that will be applied to all Windows instances'
                            }]
                        }],
                        listeners: {
                            beforeselect: function(comp, record) {
                                var settings = comp.up('#securityGroups').settings;
                                if (!settings.skipMaxCountCheck && settings.maxCount && comp.getValue().length >= settings.maxCount) {
                                    Scalr.message.InfoTip('Limit of ' + settings.maxCount + ' Security Groups per instance has been reached.', comp.inputEl, {anchor: 'bottom'});
                                    return false;
                                }
                            }
                        }
                    },{
                        xtype: 'checkbox',
                        name: 'allow_additional_sec_groups',
                        inputValue: 1,
                        margin: '12 0 6 0',
                        boxLabel: 'Allow user to specify additional security groups',
                        listeners: {
                            change: function(comp, value) {
                                this.next().setVisible(comp.up('#securityGroups').settings.extendedAdditionalSgs && !!value);
                            }
                        }
                    },{
                        xtype: 'container',
                        layout: 'hbox',
                        hidden: true,
                        itemId: 'extendedAdditionalSgsCt',
                        items: [{
                            xtype: 'buttongroupfield',
                            name: 'extendedAdditionalSgs',
                            defaults: {
                                width: 100
                            },
                            value: 'any',
                            items: [{
                                value: 'any',
                                text: 'Any'
                            },{
                                value: 'list',
                                text: 'From list'
                            }],
                            listeners: {
                                change: function(comp, value) {
                                    comp.next().setVisible(value === 'list');
                                }
                            }
                        },{
                            xtype: 'taglistfield',
                            name: 'additional_sec_groups_list',
                            flex: 1,
                            margin: '0 0 0 10',
                            hidden: true,
                            allowBlank: false,
                            tagRegexText: 'SG names must be alphanumeric, including the following common characters: plus (+), equal (=), comma (,), period (.), at (@), dash (-) and space.',
                            tagRegex: /^\**[\w+=,.@\-][\w+=,.@\- *]*$/,
                        }]
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
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'awsRDS',
                    layout: 'anchor',
                    maxWidth: maxFormWidth,
                    defaults: {
                        anchor: '100%'
                    },
                    setValues: function(data){
                        var limits = data.settings.limits;
                        this.setFieldValues(limits);
                    },
                    getValues: function(){
                        var result = this.isValidFields() ? this.getFieldValues() : null;
                        if (result) {
                            result['db_instance_requires_farm_association'] = result['db_instance_requires_farm_association'] ? 1 : 0;
                        }
                        return result;
                    },
                    items: [{
                        xtype: 'checkbox',
                        name: 'db_instance_requires_farm_association',
                        boxLabel: 'Require Farm association for new database instances'
                    }]
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'instancetype',
                    flex: 1,
                    layout: 'fit',
                    maxWidth: 1000,
                    setValues: function(data){
                        var me = this,
                            limits = data.settings.limits,
                            grid = me.down('instancetypesgrid');
                        callback = function(data, status) {
                            if (data && data.length) {
                                grid.store.load({
                                    data: Ext.Array.map(data, function(item){
                                        return {
                                            name: (new Ext.XTemplate('<span class="x-semibold">{name}</span> ({[this.instanceTypeInfo(values)]})').apply(item)),
                                            id: item.id,
                                            'default': limits['default'] === item.id,
                                            enabled: Ext.Array.contains(limits.value, item.id) ?  1 : 0
                                        };
                                    })
                                });
                            } else {
                                panel.down('#rightcol').disableButtons();
                            }
                        };
                        grid.store.removeAll();
                        Scalr.loadInstanceTypes(data.platform, '', callback);
                    },
                    getValues: function(){
                        var limits = {value:[], 'default': null};
                        this.down('instancetypesgrid').store.getUnfiltered().each(function(record){
                            var id = record.get('id');
                            if (record.get('enabled')) {
                                limits.value.push(id);
                            }
                            if (record.get('default')) {
                                limits.default = id;
                            }
                        });
                        return limits;
                    },
                    items: [{
                        xtype: 'instancetypesgrid'
                    }]
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'instancetypebyregion',
                    flex: 1,
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },
                    maxWidth: 1000,
                    setValues: function(data){
                        var limits = data.settings.limits,
                            config = data.config,
                            value = limits.value || {},
                            grid = this.down('#instancetypesgrid'),
                            regionsField = this.down('#region');
                        regionsField.reset();
                        this.policy = Ext.clone(data);
                        Scalr.CachedRequestManager.get('governance').load(
                            {
                                url: '/platforms/azure/xGetResourceGroups'
                            },
                            function(data, status){
                                data = data || {};
                                if (!Ext.Object.getSize(data)) {
                                    panel.down('#rightcol').disableButtons();
                                } else {
                                    var cloudLocationGrovernance = Scalr.getGovernance('azure', 'azure.cloud_location'),
                                        cloudLocations = [];
                                    Ext.Object.each(data.cloudLocations, function(id, name){
                                        if (cloudLocationGrovernance === undefined || Ext.Array.contains(cloudLocationGrovernance.value, id)) {
                                            cloudLocations.push({
                                                id: id,
                                                name: name,
                                                limit: value[id] ? value[id].value.length : null
                                            });
                                        }
                                    });
                                    regionsField.store.load({data: cloudLocations});
                                }
                            }
                        );
                    },
                    getValues: function(){
                        var region = this.down('#region').getValue();
                        if (region) {
                            this.updateRegionLimits(region);
                        }
                        return this.policy.settings.limits;
                    },
                    updateRegionLimits: function(region) {
                        var regionLimits,
                            grid = this.down('instancetypesgrid');
                        if (region) {
                            if (grid.isVisible()) {
                                regionLimits = {value: []};
                                grid.store.getUnfiltered().each(function(record) {
                                    if (record.get('enabled')) {
                                        regionLimits.value.push(record.get('id'));
                                        if (record.get('default')) {
                                            regionLimits['default'] = record.get('id');
                                        }
                                    }
                                });
                                this.policy.settings.limits.value[region] = regionLimits;
                            } else {
                                delete this.policy.settings.limits.value[region];
                            }
                        }
                    },
                    items: [{
                        xtype: 'container',
                        layout: 'hbox',
                        items: [{
                            xtype: 'combo',
                            itemId: 'region',
                            valueField: 'id',
                            displayField: 'name',
                            editable: false,
                            maxWidth: 400,
                            flex: 1,
                            emptyText: 'Please select region',
                            store: {
                                fields: ['id', 'name', 'limit'],
                                proxy: 'object',
                                sorters: {
                                    property: 'name'
                                }
                            },
                            listConfig: {
                               tpl : '<tpl for=".">'+
                                          '<div class="x-boundlist-item"><span class="x-semibold">{name}</span> <span style="font-style:italic">&nbsp;({[values.limit ? values.limit + \' instance type(s) allowed\' : \'No limits\']})</span></div>'+
                                      '</tpl>'
                            },
                            name: 'type',
                            listeners: {
                                change: function(comp, value, oldValue){
                                    var tab = comp.up('#instancetypebyregion'),
                                        enabledField = comp.next('#enabled');
                                    if (oldValue) {
                                        tab.updateRegionLimits(oldValue);
                                    }
                                    enabledField.setValue(0);
                                    if (value) {
                                        enabledField.show().setValue(tab.policy.settings.limits.value[value] ? 1 : 0);
                                    } else {
                                        enabledField.hide();
                                    }
                                }
                            }
                        },{
                            xtype: 'buttongroupfield',
                            itemId: 'enabled',
                            margin: '0 0 12 12',
                            defaults: {
                                width: 140
                            },
                            items: [{
                                text: 'No limits',
                                value: 0
                            },{
                                text: 'Configure limits',
                                value: 1
                            }],
                            hidden: true,
                            listeners: {
                                change: function(comp, value) {
                                    var grid = comp.up().next('instancetypesgrid'),
                                        policy = comp.up('#instancetypebyregion').policy,
                                        regionLimits,
                                        region = comp.prev('#region').getValue();
                                    if (value) {
                                        regionLimits = policy.settings.limits.value[region] || {};
                                        callback = function(data, status) {
                                            if (data && data.length) {
                                                grid.store.load({
                                                    data: Ext.Array.map(data, function(item){
                                                        return {
                                                            name: (new Ext.XTemplate('<span class="x-semibold">{name}</span> ({[this.instanceTypeInfo(values)]})').apply(item)),
                                                            id: item.id,
                                                            'default': regionLimits['default'] === item.id,
                                                            enabled: Ext.Array.contains(regionLimits.value || [], item.id) ?  1 : 0
                                                        };
                                                    })
                                                });
                                                grid.show();
                                            } else {
                                                panel.down('#rightcol').disableButtons();
                                            }
                                        };
                                        grid.store.removeAll();
                                        Scalr.loadInstanceTypes(policy.platform, region, callback);
                                        grid.onRecordUpdate(grid.store);
                                    } else {
                                        grid.hide();
                                        grid.store.removeAll();
                                        grid.onRecordUpdate(grid.store);
                                    }
                                }
                            }
                        }]
                    },{
                        xtype: 'instancetypesgrid',
                        flex: 1,
                        hidden: true,
                        onRecordUpdate: function(store) {
                            var regionField = this.up('#instancetypebyregion').down('#region'),
                                regionRecord = regionField.findRecordByValue(regionField.getValue()),
                                limit = 0;
                            if (regionRecord) {
                                store.getUnfiltered().each(function(record){
                                    if (record.get('enabled')) {
                                        limit ++;
                                    }
                                });
                                regionRecord.set('limit', limit);
                            }
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

                        regionsField.store.load({
                            data: Ext.Array.map(Ext.Object.getKeys(moduleParams['platforms']['ec2']), function(region){
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
                                    vpcIdsList.push(item);
                                });
                                return {
                                    'default': regionValue['default'] || 0,
                                    ids: ids,
                                    name: region,
                                    enabled: limits['regions'][region] !== undefined ?  1 : 0
                                };
                            })
                        });

                        vpsidsField.store.load({data: vpcIdsList});
                        vpsidsField.setVisible(vpcIdsList.length > 0);

                        this.resumeLayouts(true);
                    },
                    getValues: function(){
                        var regionsField = this.down('#regions'),
                            vpsidsField = this.down('#vpcids'),
                            value = {value: this.down('[name="value"]').getValue() ? 1 : 0, regions:{}, ids: {}};
                        regionsField.store.getUnfiltered().each(function(record){
                            if (record.get('enabled')) {
                                value.regions[record.get('name')] = {
                                    'default': record.get('default') ? 1 : 0,
                                    ids: record.get('ids')
                                };
                            }
                        });
                        vpsidsField.store.getUnfiltered().each(function(record){
                            var type = record.get('type');
                            if (type !== '') {
                                value.ids[record.get('name')] = record.get(type);
                            }
                        });
                        return value;
                    },
                    items: [{
                        xtype: 'checkbox',
                        name: 'value',
                        //margin: '0 0 12 3',
                        boxLabel: 'Require all resources to be launched in a VPC'
                    },{
                        xtype: 'container',
                        flex: 1,
                        itemId: 'settings',
                        layout: {
                            type: 'vbox',
                            align: 'stretch'
                        },
                        items: [{
                            xtype: 'grid',
                            cls: 'x-grid-with-formfields',
                            itemId: 'regions',
                            flex: 1.6,
                            maxHeight: 300,
                            store: {
                                fields: ['name', {name: 'default', type: 'boolean'}, 'ids', 'enabled'],
                                proxy: 'object'
                            },
                            trackMouseOver: false,
                            disableSelection: true,
                            columns: [{
                                text: 'Default',
                                sortable: false,
                                resizable: false,
                                width: 76,
                                dataIndex: 'default',
                                xtype: 'widgetcolumn',
                                align: 'center',
                                widget: {
                                    xtype: 'radio',
                                    name: 'default',
                                    listeners: {
                                        change: function(comp, value){
                                            var record = comp.getWidgetRecord();
                                            if (record) {
                                                record.set('default', value);
                                                if (value) {
                                                    record.set('enabled', 1)
                                                }
                                            }
                                        }
                                    }
                                }
                            },{
                                text: 'Region',
                                sortable: false,
                                resizable: false,
                                width: 135,
                                dataIndex: 'name'
                            },{
                                text: 'Allowed',
                                sortable: false,
                                resizable: false,
                                dataIndex: 'enabled',
                                width: 110,
                                xtype: 'widgetcolumn',
                                align: 'center',
                                widget: {
                                    xtype: 'buttongroupfield',
                                    margin: '0 0 0 -6',
                                    defaults: {
                                        width: 45,
                                        style: 'padding-left:0;padding-right:0'
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
                                            var record = comp.getWidgetRecord();
                                            if (record) {
                                                record.set('enabled', value);
                                                if (!value) {
                                                    record.set('default', false);
                                                }
                                            }
                                        },
                                        beforetoggle: function(btn, value){
                                            var record = this.getWidgetRecord();
                                            if (value === 0 && record.get('default')) {
                                                Scalr.message.InfoTip('Default region can\'t be disabled.', btn.el, {anchor: 'bottom'});
                                                return false;
                                            }
                                        }
                                    }
                                }
                            },{
                                text: 'Allowed VPCs',
                                sortable: false,
                                resizable: false,
                                //dataIndex: 'ids', //avoid value auto binding, will do everyting manually onWidgetAttach
                                width: 110,
                                xtype: 'widgetcolumn',
                                flex: 1,
                                onWidgetAttach: function(column, widget, record) {
                                    if (!widget.onRecordUpdate) {
                                        var ids = record.get('ids');
                                        widget.store.proxy.params = {cloudLocation: record.get('name')};
                                        widget.store.loadData(Ext.Array.map(ids, function(id){return {id: id, name: id}}));
                                        widget.suspendEvents(false);
                                        widget.setValue(ids);
                                        widget.resumeEvents();
                                        widget.setVisible(record.get('enabled') == 1);
                                        widget.onRecordUpdate = function(store, rec, operation, modifiedFieldNames){
                                            if (rec === record && Ext.Array.contains(modifiedFieldNames, 'enabled')) {
                                                var enabled = rec.get('enabled'),
                                                    view = column.getView();
                                                if (!enabled) widget.setValue(null);
                                                widget.setVisible(enabled == 1);
                                                column.onViewRefresh(view, view.getViewRange());//update widget width to fit column
                                            }
                                        };
                                        record.store.on('update', widget.onRecordUpdate);
                                    }
                                },
                                widget: {
                                    xtype: 'tagfield',
                                    displayField: 'name',
                                    valueField: 'id',
                                    emptyText: 'No limits',
                                    queryCaching: false,
                                    minChars: 0,
                                    queryDelay: 10,
                                    forceSelection: false,
                                    store: {
                                        fields: ['id', 'name'],
                                        proxy: {
                                            type: 'cachedrequest',
                                            crscope: 'governance',
                                            url: '/platforms/ec2/xGetVpcList',
                                            root: 'vpc',
                                            filterFields: ['id', 'name'],
                                        }
                                    },
                                    listeners: {
                                        change: function(comp, value) {
                                            var record = comp.getWidgetRecord();
                                            if (record) {
                                                record.set('ids', value);
                                                comp.up('#vpc').down('#vpcids').updateRecords(record.get('name'), value);
                                            }
                                        }
                                    }
                                }
                            }]

                        },{
                            xtype: 'grid',
                            cls: 'x-grid-with-formfields',
                            itemId: 'vpcids',
                            hidden: true,
                            flex: 1,
                            store: {
                                fields: ['name', {name: 'type', defaultValue: ''}, 'ids', 'region', {name: 'internet', defaultValue: 'full'}],
                                proxy: 'object'
                            },
                            trackMouseOver: false,
                            disableSelection: true,
                            dockedItems: {
                                dock: 'top',
                                xtype: 'component',
                                cls: 'x-fieldset-subheader',
                                html: 'Internet access & subnets restrictions',
                                margin: '32 0 12'
                            },
                            updateRecords: function(region, list) {
                                var me = this, left = [], data = [];
                                me.store.getUnfiltered().each(function(record){
                                    if (record.get('region') === region) {
                                        var name = record.get('name');
                                        if (!Ext.Array.contains(list, name)) {
                                            me.store.remove(record);
                                        } else {
                                            left.push(name);
                                        }
                                    }
                                });
                                Ext.Array.each(list, function(vpcid){
                                    if (!Ext.Array.contains(left, vpcid)) {
                                        data.push({region: region, ids: [], name: vpcid});
                                    }
                                });
                                me.store.load({data: data, addRecords: true});
                                me.setVisible(me.store.getUnfiltered().length > 0);
                            },
                            columns: [{
                                text: 'VPC ID',
                                width: 134,
                                dataIndex: 'name'
                            },{
                                text: 'Restrict',
                                xtype: 'widgetcolumn',
                                width: 210,
                                dataIndex: 'type',
                                widget: {
                                    xtype: 'combo',
                                    editable: false,
                                    store: [
                                        ['', 'No limits'],
                                        ['internet', 'Type'],
                                        ['ids', 'To specific subnet(s)']
                                    ],
                                    name: 'type',
                                    listeners: {
                                        change: function(comp, value){
                                            var record = comp.getWidgetRecord();
                                            if (record) {
                                                record.set('type', value);
                                            }
                                        }
                                    }
                                }
                            },{
                                xtype: 'widgetcolumn',
                                flex: 1,
                                listeners: {
                                    render: function() {
                                        var column = this;
                                        column.up('grid').store.on('update', function(store, rec, operation, modifiedFieldNames){
                                            if (Ext.Array.contains(modifiedFieldNames, 'type')) {
                                                var type = rec.get('type'),
                                                    view = column.getView(),
                                                    widget = column.getWidget(rec);
                                                widget.down('#ids').setVisible(type === 'ids');
                                                widget.down('#internet').setVisible(type === 'internet');
                                                if (view && view.viewReady) {
                                                    column.onViewRefresh(view, view.getViewRange());//update widget width to fit column
                                                }
                                            }
                                        });
                                    }
                                },
                                onWidgetAttach: function(column, widget, record) {
                                    var idsField,
                                        internetField,
                                        ids = record.get('ids'),
                                        type = record.get('type');
                                    idsField = widget.down('#ids');
                                    internetField = widget.down('#internet');
                                    idsField.store.proxy.params = {
                                        cloudLocation: record.get('region'),
                                        vpcId: record.get('name'),
                                        extended: 1
                                    };
                                    if (type === 'ids') {
                                        idsField.store.loadData(Ext.Array.map(ids || [], function(id){return {id: id, description: id}}));
                                        idsField.setValue(ids);
                                    } else if (type === 'internet') {
                                        internetField.setValue(record.get('internet'));
                                    }
                                    idsField.setVisible(type === 'ids');
                                    internetField.setVisible(type === 'internet');
                                },
                                widget: {
                                    xtype: 'container',
                                    layout: 'fit',
                                    items: [{
                                        xtype: 'buttongroupfield',
                                        itemId: 'internet',
                                        hidden: true,
                                        defaults: {
                                            width: 120
                                        },
                                        items: [{
                                            text: 'Private',
                                            value: 'outbound-only'
                                        },{
                                            text: 'Public',
                                            value: 'full'
                                        }],
                                        listeners: {
                                            change: function(comp, value){
                                                var record = this.up().getWidgetRecord();
                                                if (record) {
                                                    record.set('internet', value);
                                                }
                                            }
                                        }
                                    },{
                                        xtype: 'vpcsubnetfield',
                                        itemId: 'ids',
                                        hidden: true,
                                        ignoreGovernance: true,
                                        listeners: {
                                            change: function(comp, value){
                                                var record = comp.up().getWidgetRecord();
                                                if (record) {
                                                    record.set('ids', value);
                                                }
                                            }
                                        }
                                    }]
                                }
                            }]

                        }]
                    }]
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'encryptionkeys',
                    flex: 1,
                    layout: 'fit',
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var limits = data.settings.limits,
                            regionsField = this.down('#regions');

                        this.suspendLayouts();
                        regionsField.store.load({
                            data: Ext.Array.map(Ext.Object.getKeys(moduleParams['platforms']['ec2']), function(region){
                                var regionValue = limits[region] || {};
                                return {
                                    keys: regionValue['keys'] || [],
                                    name: region
                                };
                            })
                        });

                        this.resumeLayouts(true);
                    },
                    getValues: function(){
                        var regionsField = this.down('#regions'),
                            value = {};
                        regionsField.store.getUnfiltered().each(function(record){
                            var keys = record.get('keys');
                            if (Ext.isArray(keys) && keys.length) {
                                value[record.get('name')] = {
                                    keys: keys
                                };
                            }
                        });
                        return value;
                    },
                    items: [{
                        xtype: 'grid',
                        cls: 'x-grid-with-formfields',
                        itemId: 'regions',
                        store: {
                            fields: ['name', 'keys'],
                            proxy: 'object'
                        },
                        trackMouseOver: false,
                        disableSelection: true,
                        columns: [{
                            text: 'Region',
                            sortable: false,
                            resizable: false,
                            width: 135,
                            dataIndex: 'name'
                        },{
                            text: 'Allowed Keys',
                            sortable: false,
                            resizable: false,
                            //dataIndex: 'keys', //avoid value auto binding, will do everyting manually onWidgetAttach
                            xtype: 'widgetcolumn',
                            flex: 1,
                            onWidgetAttach: function(column, widget, record) {
                                if (!widget.onRecordUpdate) {
                                    var keys = record.get('keys');
                                    widget.store.proxy.params = {cloudLocation: record.get('name')};
                                    widget.store.loadData(Ext.Array.map(keys, function(key){return {id: key.id, alias: key.alias}}));
                                    widget.suspendEvents(false);
                                    widget.setValue(Ext.Array.map(keys, function(key){return key.id;}));
                                    widget.resumeEvents();
                                    widget.onRecordUpdate = function(store, rec, operation, modifiedFieldNames){
                                        if (rec === record) {
                                            var view = column.getView();
                                            column.onViewRefresh(view, view.getViewRange());//update widget width to fit column
                                        }
                                    };
                                    record.store.on('update', widget.onRecordUpdate);
                                }
                            },
                            widget: {
                                xtype: 'tagfield',
                                displayField: 'displayField',
                                valueField: 'id',
                                emptyText: 'No limits',
                                queryCaching: false,
                                minChars: 0,
                                queryDelay: 10,
                                forceSelection: false,
                                store: {
                                    fields: ['id', 'alias', {name: 'displayField', convert: function(v, record){return record.data.alias ? record.data.alias.replace('alias/', ''):''}}],
                                    proxy: {
                                        type: 'cachedrequest',
                                        crscope: 'governance',
                                        url: '/platforms/ec2/xGetKmsKeysList',
                                        root: 'keys',
                                        filterFields: ['alias']
                                    },
                                    sorters: {
                                        property: 'alias',
                                        transform: function(value){
                                            return value ? value.toLowerCase() : value;
                                        }
                                    }
                                },
                                listeners: {
                                    change: function(comp, value) {
                                        var record = comp.getWidgetRecord(),
                                            keys = [];
                                        if (record) {
                                            Ext.each(value || [], function(id){
                                                var rec = comp.store.getById(id);
                                                if (rec) {
                                                    keys.push({
                                                        id: rec.get('id'),
                                                        alias: rec.get('alias')
                                                    });
                                                }
                                            });
                                            record.set('keys', keys);
                                        }
                                    }
                                }
                            }
                        }]
                    }]
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'networks',
                    flex: 1,
                    layout: 'fit',
                    maxWidth: 1000,
                    setValues: function(data){
                        var me = this,
                            limits = data.settings.limits,
                            cloudFamily = data.platform,
                            grid = me.down('grid');
                        if (Scalr.isCloudstack(data.platform)) {
                            cloudFamily = 'cloudstack';
                        } else if (Scalr.isOpenstack(data.platform)) {
                            cloudFamily = 'openstack';
                        }

                        grid.store.removeAll();
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
                                    panel.down('#rightcol').disableButtons();
                                } else {
                                    Ext.Object.each(data, function(region, networks){
                                        items.push({
                                            networks: networks,
                                            name: region,
                                            ids: limits.value[region] || []
                                        });
                                    });
                                   grid.store.load({data: items});
                                }
                            }
                        );
                    },
                    getValues: function(){
                        var limits = {value: {}};
                        this.down('grid').store.getUnfiltered().each(function(record){
                            limits.value[record.get('name')] = record.get('ids');
                        });
                        return limits;
                    },
                    items: {
                        xtype: 'grid',
                        cls: 'x-grid-with-formfields',
                        store: {
                            fields: ['ids', 'name', 'networks'],
                            proxy: 'object'
                        },
                        trackMouseOver: false,
                        disableSelection: true,
                        viewConfig: {
                            emptyText: 'Networks list is empty',
                            deferEmptyText: false
                        },
                        columns: [{
                            text: 'Region',
                            width: 134,
                            sortable: false,
                            resizable: false,
                            dataIndex: 'name'
                        },{
                            xtype: 'widgetcolumn',
                            text: 'Allowed Networks',
                            flex: 1,
                            //dataIndex: 'ids', //avoid value auto binding, will do everyting manually onWidgetAttach
                            onWidgetAttach: function(column, widget, record) {
                                widget.store.loadData(record.get('networks'));
                                widget.setValue(record.get('ids'));
                            },
                            sortable: false,
                            resizable: false,
                            widget: {
                                xtype: 'tagfield',
                                displayField: 'name',
                                valueField: 'id',
                                emptyText: 'No limits',
                                queryMode: 'local',
                                forceSelection: false,
                                store: {
                                    fields: ['id', 'name'],
                                    proxy: 'object'
                                },
                                listeners: {
                                    change: function(comp, value) {
                                        var record = comp.getWidgetRecord();
                                        if (record) {
                                            record.set('ids', value);
                                        }
                                    }
                                }
                            }
                        }]
                    }
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
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var field,
                            isOpenstack = Scalr.isOpenstack(data.platform);
                        field = this.down('ec2tagsfield');
                        field.setTagsLimit(data.config.tagsLimit);
                        field.setCloud(isOpenstack ? 'openstack' : data.platform);
                        field.setValue(data.settings.limits.value);

                        field = this.down('[name="allow_additional_tags"]');
                        field.setValue(data.settings.limits['allow_additional_tags']);
                        field.setBoxLabel('Allow user to specify additional ' + (isOpenstack ? 'name-value pairs' : 'tags'));
                    },
                    getValues: function(config){
                        var grid = this.down('ec2tagsfield'),
                            result = {};
                        if (!grid.isValid()) return null;

                        result['value'] = grid.getValue();
                        result['allow_additional_tags'] = this.down('[name="allow_additional_tags"]').getValue() ? 1 : 0;
                        return result;
                    },
                    items: [{
                        xtype: 'checkbox',
                        name: 'allow_additional_tags',
                        inputValue: 1,
                        boxLabel: '&nbsp;'
                    },{
                        xtype: 'ec2tagsfield',
                        flex: 1
                    }]
                },{
                    xtype: 'container',
                    hidden: true,
                    tab: true,
                    itemId: 'chef',
                    flex: 1,
                    layout: 'fit',
                    maxWidth: 1000,
                    setValues: function(data){
                        var limits = data.settings.limits;

                        this.down('grid').store.load({
                            data: Ext.Array.map(moduleParams['chef']['servers'], function(server){
                                var serverId = server['id']+'',
                                    serverValue = limits['servers'][serverId] || {},
                                    ids = serverValue['environments'] || [];
                                return {
                                    id: serverId,
                                    'default': serverValue['default'] || 0,
                                    ids: ids,
                                    url: server['url'],
                                    scope: server['scope'],
                                    enabled: limits['servers'][serverId] !== undefined ?  1 : 0
                                };
                            })
                        });
                    },
                    getValues: function(){
                        var value = {servers: {}};
                        this.down('grid').store.getUnfiltered().each(function(record){
                            if (record.get('enabled')) {
                                value.servers[record.get('id')] = {
                                    'default': record.get('default') ? 1 : 0,
                                    environments: record.get('ids')
                                };
                            }
                        });
                        Scalr.CachedRequestManager.get().setExpired({url: '/services/chef/servers/xListServers/'});
                        return value;
                    },
                    items: [{
                        xtype: 'grid',
                        cls: 'x-grid-with-formfields',
                        store: {
                            fields: ['id', {name: 'default', type: 'boolean'}, 'ids', 'url', 'scope', 'enabled'],
                            proxy: 'object'
                        },
                        trackMouseOver: false,
                        disableSelection: true,
                        columns: [{
                            text: 'Default',
                            sortable: false,
                            resizable: false,
                            width: 76,
                            dataIndex: 'default',
                            xtype: 'widgetcolumn',
                            align: 'center',
                            widget: {
                                xtype: 'radio',
                                name: 'default',
                                listeners: {
                                    change: function(comp, value){
                                        var record = comp.getWidgetRecord();
                                        if (record) {
                                            record.set('default', value);
                                            if (value) {
                                                record.set('enabled', 1)
                                            }
                                        }
                                    }
                                }
                            }
                        },{
                            text: 'Chef server',
                            sortable: false,
                            resizable: false,
                            flex: 1.1,
                            dataIndex: 'url',
                            xtype: 'templatecolumn',
                            tpl: '<img data-qtip="{scope:capitalize} scope" src="'+Ext.BLANK_IMAGE_URL+'" class="scalr-scope-{scope}" />&nbsp;&nbsp;{url}'
                        },{
                            text: 'Allowed',
                            sortable: false,
                            resizable: false,
                            dataIndex: 'enabled',
                            width: 110,
                            xtype: 'widgetcolumn',
                            align: 'center',
                            widget: {
                                xtype: 'buttongroupfield',
                                margin: '0 0 0 -6',
                                defaults: {
                                    width: 45,
                                    style: 'padding-left:0;padding-right:0'
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
                                        var record = comp.getWidgetRecord();
                                        if (record) {
                                            record.set('enabled', value);
                                            if (!value) {
                                                record.set('default', false);
                                            }
                                        }
                                    }
                                }
                            }
                        },{
                            text: 'Allowed environments',
                            sortable: false,
                            resizable: false,
                            //dataIndex: 'ids', //avoid value auto binding, will do everyting manually onWidgetAttach
                            width: 110,
                            xtype: 'widgetcolumn',
                            flex: 1,
                            onWidgetAttach: function(column, widget, record) {
                                if (!widget.onRecordUpdate) {
                                    var ids = record.get('ids');
                                    //widget.store.loadData(Ext.Array.map(ids, function(id){return {id: id}}));
                                    widget.store.proxy.params = {servId: record.get('id')};
                                    widget.setValue(ids);
                                    widget.setVisible(record.get('enabled') == 1);
                                    widget.onRecordUpdate = function(store, rec, operation, modifiedFieldNames){
                                        if (rec === record && Ext.Array.contains(modifiedFieldNames, 'enabled')) {
                                            var enabled = rec.get('enabled'),
                                                view = column.getView();
                                            if (!enabled) widget.setValue(null);
                                            widget.setVisible(enabled == 1);
                                            column.onViewRefresh(view, view.getViewRange());//update widget width to fit column
                                        }
                                    };
                                    record.store.on('update', widget.onRecordUpdate);
                                }
                            },
                            widget: {
                                xtype: 'tagfield',
                                displayField: 'id',
                                valueField: 'id',
                                emptyText: 'No limits',
                                queryCaching: false,
                                minChars: 0,
                                queryDelay: 10,
                                forceSelection: false,
                                store: {
                                    fields: [{name: 'id', mapping: 'name'}],
                                    proxy: {
                                        type: 'cachedrequest',
                                        crscope: 'governance',
                                        url: '/services/chef/xListEnvironments/',
                                        filterFields: ['id']
                                    }
                                },
                                listeners: {
                                    change: function(comp, value) {
                                        var record = comp.getWidgetRecord();
                                        if (record) {
                                            record.set('ids', value);
                                        }
                                    }
                                }
                            }
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
                    }
                }, {
                    xtype: 'button',
                    itemId: 'disable',
                    text: 'Disable policy',
                    handler: function() {
                        panel.down('#options').saveOption(0);
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
            width: 110 + Ext.getScrollbarSize().width,
            overflowY: 'auto',
            defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'top',
                disableMouseDownPressed: true,
                toggleGroup: 'governance-tabs',
                cls: 'x-btn-tab-no-text-transform',
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
                var grid = panel.getComponent('options');
                grid.getSelectionModel().deselectAll();
                grid.view.getFeature('grouping')[platform !== 'ec2' ? 'disable' : 'enable']();
                grid.store.loadData(Ext.Array.map(config[platform]['options'], function(optionConfig){
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
                        group: optionConfig.group,
                        settings: governanceSettings[platform][optionConfig.name]
                    }
                }));
			}
		}
	});
	return panel;
});

Ext.define('Scalr.ui.InstanceTypesGrid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.instancetypesgrid',
    cls: 'x-grid-with-formfields',
    store: {
        fields: ['id', {name: 'default', type: 'boolean'}, 'name', 'enabled'],
        proxy: 'object'
    },
    trackMouseOver: false,
    disableSelection: true,
    viewConfig: {
        emptyText: 'Instance types list is empty',
        deferEmptyText: false
    },
    listeners: {
        afterrender: function() {
            this.store.on('update', this.onRecordUpdate || Ext.emptyFn, this);
        }
    },
    columns: [{
        text: 'Default',
        sortable: false,
        resizable: false,
        width: 76,
        dataIndex: 'default',
        xtype: 'widgetcolumn',
        align: 'center',
        widget: {
            xtype: 'radio',
            name: 'default',
            listeners: {
                change: function(comp, value){
                    var record = comp.getWidgetRecord();
                    if (record) {
                        record.set('default', value);
                        if (value) {
                            record.set('enabled', 1)
                        }
                    }
                }
            }
        }
    },{
        resizable: false,
        sortable: false,
        dataIndex: 'enabled',
        width: 110,
        xtype: 'widgetcolumn',
        align: 'center',
        widget: {
            xtype: 'buttongroupfield',
            margin: '0 0 0 -6',
            defaults: {
                width: 45,
                style: 'padding-left:0;padding-right:0'
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
                    var record = comp.getWidgetRecord();
                    if (record) {
                        record.set('enabled', value);
                        if (!value) {
                            record.set('default', false);
                        }
                    }
                }
            }
        }
    },{
        dataIndex: 'name',
        flex: 1,
        resizable: false,
        sortable: false,
        text: 'Available instance types'
    }]
});
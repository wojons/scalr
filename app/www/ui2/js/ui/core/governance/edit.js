Scalr.regPage('Scalr.ui.core.governance.edit', function (loadParams, moduleParams) {
    var config = {
        general: {
            title: 'Scalr',
            options: [{
                name: 'general.lease',
                type: 'lease',
                title: 'Lease management'
            },{
                name: 'general.hostname_format',
                title: 'Server hostname format',
                type: 'text',
                emptyText: 'Leave blank to use cloud generated hostname',
                defaults: {
                    value: ''
                },
                subheader: '',
                warning: Scalr.strings['farmbuilder.hostname_format.info']
            }]
        },
        ec2: {
            title: 'Amazon EC2',
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
                type: 'list',
                values: [
                    't1.micro', 
                    'm1.small', 'm1.medium', 'm1.large', 'm1.xlarge',
                    'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge', 
                    'm3.xlarge', 'm3.2xlarge',
                    'c1.medium', 'c1.xlarge',
                    'c3.large', 'c3.xlarge', 'c3.2xlarge', 'c3.4xlarge', 'c3.8xlarge',
                    'i2.large', 'i2.xlarge', 'i2.2xlarge', 'i2.4xlarge', 'i2.8xlarge',
                    'g2.2xlarge',
                    'cc1.4xlarge', 'cc2.8xlarge', 'cg1.4xlarge', 'hi1.4xlarge', 'cr1.8xlarge'
                ],
                defaults: {
                    value: ['m1.small'],
                    'default': 'm1.small'
                },
                subheader: 'Limit the types of instances that can be configured in Farm Designer.'
            },{
                name: 'aws.additional_security_groups',
                title: 'Security groups',
                type: 'awsSecurityGroups',
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
                type: 'text',
                defaults: {
                    value: ''
                },
                subheader: 'Set a common SSH key pair for all instances.',
                warning: 'Make sure this key pair already exists within your EC2 setup. Scalr WILL NOT create this pair and instances will fail to launch otherwise.'
            }]
        },
        idcf: {
            title: 'IDC Frontier',
            options: [{
                name: 'idcf.service_offering_id',
                title: 'Service offering',
                type: 'csofferings',
                defaults: {value: []},
                subheader: 'Limit service offerings.'
            },{
                name: 'idcf.network_id',
                title: 'Network',
                type: 'csnetworks',
                defaults: {value: {}},
                subheader: 'Limit networks.'
            }]
        },
        cloudstack: {
            title: 'Cloudstack',
            options: [{
                name: 'cloudstack.service_offering_id',
                title: 'Service offering',
                type: 'csofferings',
                defaults: {value: []},
                subheader: 'Limit service offerings.'
            },{
                name: 'cloudstack.network_id',
                title: 'Network',
                type: 'csnetworks',
                defaults: {value: {}},
                subheader: 'Limit networks.'
            }]
        }

    };
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
                xtype: 'buttongroupcolumn',
                header: 'Enforce',
                sortable: false,
                resizable: false,
                width: 120,
                align: 'center',
                buttons: [{
                    text: 'Yes',
                    value: '1',
                    width: 50
                },{
                    text: 'No',
                    value: '0',
                    width: 50
                }],
                toggleHandler: function(view, record, value) {
                    var settings = Ext.clone(record.get('settings')),
                        selModel = view.getSelectionModel();
                    settings['enabled'] = value;
                    record.set('settings', settings);
                    selModel.deselectAll();
                    selModel.select(record);
                    view.up().saveOption(record);
                },
                getValue: function(record){
                    return record.get('settings')['enabled'] || '0';
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
            saveOption: function(record){
                var value, limits;
                if (!record) {
                    value = this.getSelectionModel().getSelection()[0].getData();
                    limits = panel.down('#rightcol').getOptionValue();
                    if (! limits)
                        return;
                    value.settings.limits = limits;
                } else {
                    value = record.getData();
                }
                governanceSettings[value.config.name] = value.settings;
                Scalr.Request({
                    processBox: {
                        type: 'save'
                    },
                    url: '/core/governance/xSave/',
                    params: {
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
            layout: 'fit',
            toggleMode: function(readonly) {
                this[readonly ? 'mask' : 'unmask']();
                var mask = this.getEl().child('.x-mask');
                if (mask) {
                    mask.setStyle({
                        background: '#ffffff',
                        opacity: .6
                    });
                }
                this.getDockedComponent('toolbar').down('#save').setDisabled(readonly);
            },
            editOption: function(option){
                var container = this.child(),
                    warning = container.getComponent('warning');

                this.suspendLayouts();
                this.currentItem = container.getComponent(option.config.type);
                container.setTitle(option.config.title, option.config.subheader);
                this.currentItem.setValues(option);
                container.items.each(function(){
                    if (this.tab !== undefined) {
                        this.setVisible(this.itemId === option.config.type);
                    }
                });

                this.show();
                
                this.toggleMode(option.settings.enabled != 1);
                this.resumeLayouts(true);

                if (option.config.warning) {
                    warning.show();
                    warning.setValue(option.config.warning);
                } else {
                    warning.hide();
                }
            },
            getOptionValue: function() {
                return this.currentItem.getValues();
            },
            items: {
                xtype: 'container',
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                defaults: {
                    margin: '12 32 0'
                },
                setTitle: function(header, subheader){
                    this.getComponent('title').update('<div class="x-fieldset-header-text" style="float:none">'+header + '</div>' + (subheader? '<div class="x-fieldset-header-description">' + subheader + '</div>' : ''));
                },
                items: [{
                    xtype: 'component',
                    cls: 'x-fieldset-header',
                    itemId: 'title',
                    margin: 0
                },{
                    xtype: 'displayfield',
                    itemId: 'warning',
                    hidden: true,
                    cls: 'x-form-field-info',
                    maxWidth: maxFormWidth
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
                    itemId: 'awsSecurityGroups',
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
                        this.setFieldValues(limits);
                    },
                    getValues: function(){
                        var result = this.getFieldValues();
                        result['allow_additional_sec_groups'] = result['allow_additional_sec_groups'] ? 1 : 0;
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
                    xtype: 'notagridview',
                    hidden: true,
                    tab: true,
                    itemId: 'list',
                    flex: 1,
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var limits = data.settings.limits;
                        this.addItems(Ext.Array.map(data.config.values, function(option){
                            return {
                                itemData: {
                                    settings: {
                                        name: option,
                                        'default': limits['default'] === option,
                                        enabled: Ext.Array.contains(limits.value, option) ?  1 : 0
                                    }
                                }
                            };
                        }), false);
                    },
                    getValues: function(){
                        var limits = {value:[], 'default': null};
                        this.getItems().each(function(item){
                            if (item.down('[name="enabled"]').getValue() === 1) {
                                limits.value.push(item.itemData.settings.name);
                            }
                            if (item.down('[name="default"]').getValue()) {
                                limits['default'] = item.itemData.settings.name;
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
                        title: 'Allowed',
                        header: {
                            flex: 1
                        },
                        defaultValue: 0,
                        extendInitialConfig: function(config, itemData){
                            config.fieldLabel = itemData.settings.name;
                        },
                        control: {
                            xtype: 'buttongroupfield',
                            labelWidth: 134,
                            labelSeparator: '',
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
                                beforetoggle: function(comp, value){
                                    if (value === 0 && comp.ownerCt.ownerCt.down('[name="default"]').getValue()) {
                                        Scalr.message.Warning('Default item can\'t be disabled.');
                                        return false;
                                    }
                                }
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
                                        ['internet', 'Internet access'],
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
                                        text: 'Outbound-only',
                                        value: 'outbound-only'
                                    },{
                                        text: 'Full',
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
                                        vpcId: itemData.settings.name
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

                                    queryCaching: false,
                                    store: {
                                        fields: ['id', 'description', 'internet', 'availability_zone', 'ips_left'],
                                        proxy: {
                                            type: 'cachedrequest',
                                            crscope: 'governance',
                                            url: '/platforms/ec2/xGetSubnetsList'
                                        }
                                    },
                                    listConfig: {
                                        style: 'white-space:nowrap',
                                        cls: 'x-boundlist-alt',
                                        tpl:
                                            '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto;line-height:20px">' +
                                                '<div><span style="font-weight: bold">{id}</span> <span style="font-style: italic;font-size:90%">(Internet access: <b>{[values.internet || \'unknown\']}</b>)</span></div>' +
                                                '<div>{sidr} in {availability_zone} [IPs left: {ips_left}]</div>' +
                                            '</div></tpl>'
                                    }
                                }
                            }]
                        }]
                    }]
                },{
                    xtype: 'notagridview',
                    itemId: 'csofferings',
                    hidden: true,
                    tab: true,
                    flex: 1,
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var me = this,
                            limits = data.settings.limits;
                        Scalr.CachedRequestManager.get('governance').load(
                            {
                                url: '/platforms/cloudstack/xGetServiceOfferings/',
                                params: {
                                    platform: data.platform
                                }
                            },
                            function(data, status){
                                data = data || [];
                                me.addItems(Ext.Array.map(data, function(item){
                                    return {
                                        itemData: {
                                            settings: {
                                                id: item.id,
                                                name: item.name,
                                                enabled: Ext.Array.contains(limits.value, item.id)
                                            }
                                        }
                                    };
                                }), false);
                            }
                        );
                    },
                    getValues: function(){
                        var limits = {value: []};
                        this.getItems().each(function(item){
                            if (item.down('[name="enabled"]').getValue() === 1) {
                                limits.value.push(item.itemData.settings.id);
                            }
                        });
                        return limits;
                    },
                    columns: [{
                        name: 'enabled',
                        title: 'Allowed',
                        header: {
                            width: 104
                        },
                        defaultValue: 0,
                        control: {
                            xtype: 'buttongroupfield',
                            width: 104,
                            defaults: {
                                width: 40
                            },
                            items: [{
                                text: 'On',
                                value: 1
                            },{
                                text: 'Off',
                                value: 0
                            }]
                        }
                    },{
                        name: 'name',
                        title: 'Available service offerings',
                        header: {
                            flex: 1
                        },
                        control: {
                            xtype: 'displayfield',
                            fieldStyle: 'font-weight:bold;color:#000'
                        }
                    }]
                },{
                    xtype: 'notagridview',
                    itemId: 'csnetworks',
                    hidden: true,
                    tab: true,
                    flex: 1,
                    maxWidth: maxFormWidth,
                    setValues: function(data){
                        var me = this,
                            limits = data.settings.limits;
                        me.removeItems();
                        Scalr.CachedRequestManager.get('governance').load(
                            {
                                url: '/platforms/cloudstack/xGetNetworks/',
                                params: {
                                    platform: data.platform
                                }
                            },
                            function(data, status){
                                var items = [];
                                data = data || {};
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
                }]
            },
            dockedItems:[{
                xtype: 'container',
                itemId: 'toolbar',
                dock: 'bottom',
                cls: 'x-docked-buttons',
                style: 'z-index:101;background:transparent;',
                maxWidth: maxFormWidth,
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                items: [{
                    xtype: 'button',
                    itemId: 'save',
                    text: 'Save',
                    handler: function() {
                        panel.down('#options').saveOption();
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
            width: 112,
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
                    if (governanceSettings[optionConfig.name] === undefined) {
                        governanceSettings[optionConfig.name] = {limits: optionConfig.defaults};
                    }
                    return {
                        platform: platform,
                        config: optionConfig,
                        settings: governanceSettings[optionConfig.name]
                    }
                }));
			}
		}
	});
	return panel;
});

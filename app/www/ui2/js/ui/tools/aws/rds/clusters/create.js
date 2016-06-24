Scalr.regPage('Scalr.ui.tools.aws.rds.clusters.create', function (loadParams, moduleParams) {

    var vpcPolicy = function (params) {

        var policy = {
            enabled: !Ext.isEmpty(params)
        };

        if (policy.enabled) {
            Ext.apply(policy, {
                launchWithVpcOnly: !!params.value,
                regions: Ext.Object.getKeys(params.regions),
                vpcs: params.regions,
                subnets: params.ids
            });
        }

        return policy;

    }( Scalr.getGovernance('ec2', 'aws.vpc') );


    var securityGroupsPolicy = function (params) {

        var policy = {
            enabled: !Ext.isEmpty(params)
        };

        if (policy.enabled) {

            policy.defaultGroups = params.value ? params.value.split(',') : [];
            policy.defaultGroupsList = '<b>' + policy.defaultGroups.join('</b>, <b>') + '</b>';
            policy.additionalGroupsList = params['additional_sec_groups_list'] ? params['additional_sec_groups_list'].split(',') : [];
            Ext.apply(policy, {
                allowAddingGroups: !!params['allow_additional_sec_groups'],
                enabledPolicyMessage: 'A Security Group Policy is active in this Environment' +
                    (!Ext.isEmpty(policy.defaultGroups) ? ', and requires that you attach the following Security Groups to your DB instance: ' +
                    policy.defaultGroupsList : '') + '.',
                requiredGroupsMessage: 'A Security Group Policy is active in this Environment' +
                    (!Ext.isEmpty(policy.defaultGroups) ? ', and restricts you to the following Security Groups: ' +
                    policy.defaultGroupsList : '') + '.'
            });
        }

        return policy;

    }( Scalr.getGovernance('ec2', 'aws.rds_additional_security_groups') );


    var form = Ext.create('Ext.form.Panel', {
        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Clusters &raquo; Create',
        width: 700,
        preserveScrollPosition: true,

        getFirstInvalidField: function () {
            return this.down('field{isValid()===false}');
        },

        scrollToField: function (field) {
            field.inputEl.scrollIntoView(this.body.el, false, false);
        },

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            maxWidth: 700,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [{
                xtype: 'button',
                text: 'Create',
                itemId: 'create',
                disabled: true,
                handler: function () {
                    var values = form.getValues(),
                        field;
                    if (values['VpcId'] && (!values['VpcSecurityGroups'] || values['VpcSecurityGroups'] == '[]')) {
                        field = form.getForm().findField('VpcSecurityGroupIds');
                        field.markInvalid('This field is required.');
                        field.focus();
                        return;
                    }
                    
                    form.down('[name=PreferredMaintenanceWindow]').setValue(form.down('#FirstDay').value + ':' + form.down('#fhour').value + ':' + form.down('#fminute').value + '-' + form.down('#LastDay').value + ':' + form.down('#lhour').value + ':' + form.down('#lminute').value);
                    form.down('[name=PreferredBackupWindow]').setValue(form.down('#bfhour').value + ':' + form.down('#bfminute').value + '-' + form.down('#blhour').value + ':' + form.down('#blminute').value);
                    
                    if (!form.getForm().isValid()) {
                        var invalidField = form.getFirstInvalidField();

                        if (!Ext.isEmpty(invalidField)) {
                            form.scrollToField(invalidField);
                            invalidField.focus();
                        }

                        return;
                    }

                    Scalr.Request({
                        processBox: {
                            type: 'save',
                            msg: 'Launching...'
                        },
                        url: '/tools/aws/rds/clusters/xSave',
                        form: form.getForm(),
                        success: function() {
                            Scalr.event.fireEvent('close');
                        }
                    });
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function () {
                  Scalr.event.fireEvent('close');
                }
            }]
        }],

        items: [{
            xtype: 'fieldset',
            title: 'Location and VPC Settings' + (
                vpcPolicy.enabled
                    ? '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="'
                        + Ext.String.htmlEncode(Scalr.strings.rdsDbInstanceVpcEnforced) + '" class="x-icon-governance" />'
                    : ''
            ),
            name: 'locationSettings',

            defaults: {
                xtype: 'combo',
                labelWidth: 140,
                editable: false,
                width: 610,
                padding: 5
            },

            items: [{
                fieldLabel: 'Cloud Location',
                emptyText: 'Select location',
                name: 'cloudLocation',
                plugins: {
                    ptype: 'fieldinnericoncloud',
                    platform: 'ec2'
                },
                store: {
                    fields: [ 'id', 'name' ],
                    data: moduleParams.locations,
                    proxy: 'object',
                    filters: [{
                        id: 'governancePolicyFilter',
                        filterFn: function (record) {
                            return vpcPolicy.enabled && vpcPolicy.launchWithVpcOnly
                                ? Ext.Array.contains(vpcPolicy.regions, record.get('id'))
                                : true;
                        }
                    }]
                },
                queryMode: 'local',
                displayField: 'name',
                valueField: 'id',
                listeners: {
                    change: function (field, value) {
                        form.down('#create').enable();

                        Ext.Array.each(
                            form.query('fieldset[name!=locationSettings]'), function (fieldset) {
                                fieldset.show().enable();
                            }
                        );

                        /*if (vpcPolicy.enabled && !vpcPolicy.launchWithVpcOnly) {
                            applyParameters(value);
                            return;
                        }*/

                        var vpcField = field.next();
                        vpcField
                            .enable()
                            .show()
                            .reset();

                        var vpcStore = vpcField.getStore();
                        vpcStore.removeAll();

                        if (!vpcPolicy.enabled || !Ext.isEmpty(vpcPolicy.vpcs[value])) {
                            Scalr.Request({
                                processBox: {
                                    type: 'load'
                                },
                                url: '/platforms/ec2/xGetVpcList',
                                params: {
                                    cloudLocation: value,
                                    serviceName: 'rds'
                                },
                                success: function (data) {
                                    var vpc = data.vpc;
                                    var defaultVpc = data.default;

                                    if (!Ext.isEmpty(defaultVpc)) {
                                        if (vpcPolicy.enabled) {
                                            var allowedVpcs = vpcPolicy.vpcs[value].ids;

                                            if (!Ext.isEmpty(allowedVpcs) && !Ext.Array.contains(allowedVpcs, defaultVpc)) {
                                                defaultVpc = null;
                                            }
                                        }
                                    }

                                    vpcStore.loadData(vpc);
                                    vpcField.setValue(defaultVpc || vpcStore.first());
                                }
                            });
                        } else {
                            vpcField.disable();
                        }

                        applyParameters(value);
                    }
                }
            }, {
                name: 'VpcId',
                fieldLabel: 'VPC',
                emptyText: 'No VPC selected. Launch DB instance outside VPC',
                queryMode: 'local',
                hidden: true,
                allowBlank: false,
                store: {
                    fields: [ 'id', 'name', 'defaultSecurityGroupId', 'defaultSecurityGroupName' ],
                    filters: [{
                        id: 'governancePolicyFilter',
                        filterFn: function (record) {
                            if (!vpcPolicy.enabled) {
                                return true;
                            }

                            var allowedVpcs = vpcPolicy.vpcs[
                                form.down('[name=cloudLocation]').getValue()
                            ].ids;

                            var vpcId = record.get('id');

                            return !Ext.isEmpty(allowedVpcs)
                                ? (!vpcPolicy.launchWithVpcOnly ? vpcId === 0 : false) || Ext.Array.contains(allowedVpcs, vpcId)
                                : (vpcPolicy.launchWithVpcOnly ? vpcId !== 0 : true);
                        }
                    }]
                },
                valueField: 'id',
                displayField: 'name',
                listeners: {
                    change: function (field, value) {
                        var cloudLocation = form.down('[name=cloudLocation]').getValue();

                        var subnetGroupField = field.next();
                        subnetGroupField.setVisible(value).reset();
                        subnetGroupField.allowBlank = !value;
                        subnetGroupField.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString({
                            cloudLocation: cloudLocation,
                            vpcId: value
                        });

                        var subnetGroupStore = subnetGroupField.getStore();
                        subnetGroupStore.removeAll();
                        subnetGroupStore.getProxy().params = {
                            cloudLocation: cloudLocation,
                            vpcId: value,
                            extended: 1
                        };

                        subnetGroupStore.load();

                        var DBSecurityGroups = form.down('[name=DBSecurityGroups]');
                        DBSecurityGroups.setVisible(!value);
                        DBSecurityGroups.submitValue = !value;

                        var VpcSecurityGroupIds = form.down('[name=VpcSecurityGroupIds]');
                        VpcSecurityGroupIds.setVisible(value);

                        form.down('[name=VpcSecurityGroups]').submitValue = !!value;


                        var vpcRecord = field.getStore().getById(value);

                        if (vpcRecord) {

                            var defaultSecurityGroups = vpcRecord.get('defaultSecurityGroups');

                            if (!Ext.isEmpty(defaultSecurityGroups)) {
                                VpcSecurityGroupIds.setSecurityGroups(defaultSecurityGroups);
                                /*VpcSecurityGroupIds.
                                    setSecurityGroupsIds(
                                    Ext.Array.map(defaultSecurityGroups, function (group) {
                                        return group.securityGroupId;
                                    })
                                ).
                                    setValue(
                                    Ext.Array.map(defaultSecurityGroups, function (group) {
                                        return group.securityGroupName;
                                    })
                                );*/
                            }
                        }
                    }
                }
            }, {
                name: 'DBSubnetGroupName',
                fieldLabel: 'Subnet Group',
                emptyText: 'Select Subnet Group',
                hidden: true,
                queryMode: 'local',
                //queryCaching: false,
                //clearDataBeforeQuery: true,
                store: {
                    fields: [ 'dBSubnetGroupName', 'dBSubnetGroupDescription', 'subnets' ],
                    proxy: {
                        type: 'cachedrequest',
                        crscope: 'rdsInstances',
                        url: '/tools/aws/rds/instances/xGetSubnetGroup',
                        root: 'subnetGroups',
                        filterFields: ['dBSubnetGroupName']
                    },
                    listeners: {
                        load: function (store) {
                            form.down('[name=DBSubnetGroupName]').
                                setValue(store.first()).
                                validate();
                        }
                    },
                    filters: [{
                        id: 'governancePolicyFilter',
                        filterFn: function (record) {
                            if (!vpcPolicy.enabled) {
                                return true;
                            }

                            var subnetsPolicy = vpcPolicy.subnets[
                                form.down('[name=VpcId]').getValue()
                            ];

                            if (Ext.isEmpty(subnetsPolicy)) {
                                return true;
                            }

                            return !Ext.Array.some(record.get('subnets'), function (subnet) {

                                var policy = subnetsPolicy;

                                if (Ext.isArray(policy)) {
                                    return !Ext.Array.contains(policy, subnet.subnetIdentifier);
                                }

                                policy = policy === 'full' ? 'public' : 'private';

                                return policy !== subnet.type;
                            });
                        }
                    }]
                },
                valueField: 'dBSubnetGroupName',
                displayField: 'dBSubnetGroupName',
                plugins: [{
                    ptype: 'comboaddnew',
                    pluginId: 'comboaddnew',
                    url: '/tools/aws/rds/instances/createSubnetGroup',
                    applyNewValue: true
                }],
                listeners: {
                    change: function (me, value) {
                        var availabilityZoneField = form.down('[name=AvailabilityZone]');
                        availabilityZoneField.reset();

                        var availabilityZoneStore = availabilityZoneField.getStore();
                        //availabilityZoneStore.removeFilter('bySubnetGroup');
                        availabilityZoneStore.clearFilter();

                        availabilityZoneField.setValue(
                            availabilityZoneStore.first()
                        );

                        if (value) {
                            var store = me.getStore();

                            var subnetGroupRecord = store.getAt(
                                store.find('dBSubnetGroupName', value)
                            );

                            if (subnetGroupRecord) {
                                var availabilityZoneNames = [];

                                Ext.Array.each(subnetGroupRecord.get('subnets'), function (subnet) {
                                    availabilityZoneNames.push(
                                        subnet['subnetAvailabilityZone'].name
                                    );
                                });

                                availabilityZoneStore.addFilter({
                                    filterId: 'bySubnetGroup',
                                    filterFn: function (record) {
                                        var name = record.get('name');
                                        return name === '' || availabilityZoneNames.indexOf(name) !== -1;
                                    }
                                });
                            }
                        }
                    },
                    addnew: function () {
                        Scalr.CachedRequestManager.get('rdsInstances').setExpired({
                            url: '/tools/aws/rds/instances/xGetSubnetGroup',
                            params: this.store.proxy.params
                        });
                    }
                }
            }, {
                xtype: 'hiddenfield',
                name: 'SubnetIds',
                submitValue: vpcPolicy.enabled,
                getSubmitValue: function () {
                    var me = this;

                    var vpcField = form.down('[name=VpcId]');

                    if (!Ext.isEmpty(vpcField.getValue())) {
                        var subnetGroupField = me.prev();

                        var record = subnetGroupField.findRecord(
                            'dBSubnetGroupName',
                            subnetGroupField.getValue()
                        );

                        if (Ext.isObject(record) && record.isModel) {
                            var subnets = record.get('subnets');

                            if (Ext.isArray(subnets)) {
                                return Ext.encode(
                                    Ext.Array.map(record.get('subnets'), function (subnet) {
                                        return subnet.subnetIdentifier;
                                    })
                                );
                            }
                        }
                    }

                    return null;
                }
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database Cluster',
            name: 'instanceSettings',
            hidden: true,
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
                xtype: 'textfield',
                name: 'DBClusterIdentifier',
                fieldLabel: 'DB Cluster Identifier',
                emptyText: 'Unique name for this Database Cluster',
                allowBlank: false,
                regex: /^[a-zA-Z].*$/,
                invalidText: 'Identifier must start with a letter',
                minLength: 1,
                maxLength: 63
            }, {// todo: multiple availability zones
                xtype: 'combo',
                name: 'AvailabilityZone',
                fieldLabel: 'Availability Zone',
                store: {
                    model: Scalr.getModel({
                        fields: [ 'id', 'name' ]
                    }),
                    proxy: 'object'
                },
                queryMode: 'local',
                editable: false,
                emptyText: 'No preference',
                valueField: 'id',
                displayField: 'name'
            }, {// todo: VPC security groups only
                xtype: 'fieldcontainer',
                fieldLabel: 'Security Groups',
                layout: 'hbox',
                width: 595,
                /*
                plugins: [{
                    ptype: 'fieldicons',
                    icons: [{
                        id: 'governance',
                        tooltip: securityGroupsPolicy.enabled
                            ? securityGroupsPolicy.enabledPolicyMessage
                            : '',
                        test: 'test text'
                    }],
                    position: 'outer',
                    align: 'right'
                }],
                */
                setIconVisible: function (visible) {
                    var me = this;

                    me.setFieldLabel('Security Groups' + (!visible
                        ? ''
                        : '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL +
                            '" class="x-icon-governance" style="margin-top:-4px" data-qtip="' +
                            securityGroupsPolicy.enabledPolicyMessage +
                            '" />'
                    ));

                    /*me.getPlugin('fieldicons').
                        toggleIcon('governance', visible);*/

                    return me;
                },
                setButtonDisabled: function (disabled) {
                    var me = this;

                    me.down('button').
                        setTooltip(disabled ? securityGroupsPolicy.requiredGroupsMessage : '').
                        setDisabled(disabled);

                    return me;
                },
                enablePolicy: function () {
                    var me = this;

                    var isPolicyEnabled = securityGroupsPolicy.enabled;

                    me.
                        setIconVisible(isPolicyEnabled);
                        /*
                        setButtonDisabled(
                            isPolicyEnabled && !securityGroupsPolicy.allowAddingGroups
                        );
                        */

                    return isPolicyEnabled;
                },
                disablePolicy: function () {
                    var me = this;

                    me.
                        setIconVisible(false);
                        //setButtonDisabled(false);

                    return me;
                },
                listeners: {
                    boxready: function (container) {
                        container.down('[name=VpcSecurityGroupIds]').
                            on({
                                show: container.enablePolicy,
                                hide: container.disablePolicy,
                                scope: container
                            });
                    }
                },
                fieldDefaults: {
                    width: 295
                    //maxHeight: 50
                },
                items: [{
                    xtype: 'hiddenfield',
                    name: 'VpcSecurityGroups',
                    submitValue: false,
                    getSubmitValue: function () {
                        var me = this;

                        var vpcSecurityGroupsField = me.next();
                        var securityGroupIds = vpcSecurityGroupsField.
                            getSecurityGroupsIds();
                        var securityGroupNames = vpcSecurityGroupsField.
                            getSecurityGroupsNames();

                        return Ext.encode(
                            Ext.Array.map(securityGroupIds, function (id, index) {
                                return {
                                    id: id,
                                    name: securityGroupNames[index]
                                };
                            })
                        );
                    }
                }, {
                    xtype: 'taglistfield',
                    name: 'VpcSecurityGroupIds',
                    //cls: 'x-tagfield-force-item-hover',
                    hidden: true,
                    submitValue: false,
                    readOnly: true,
                    scrollable: true,
                    defaultGroups: securityGroupsPolicy.enabled
                        ? securityGroupsPolicy.defaultGroups
                        : [],
                    listeners: {
                        afterrender: {
                            fn: function (field) {
                                field.getEl().on('click', function (event, target) {

                                    target = Ext.get(target);

                                    if (target.hasCls('scalr-ui-rds-tagfield-sg-name')) {

                                        var link =
                                            '#/security/groups/' + target.getAttribute('data-id') + '/edit?' +

                                            Ext.Object.toQueryString({
                                                platform: 'ec2',
                                                cloudLocation: form.down('[name=cloudLocation]').getValue()
                                            });

                                        Scalr.event.fireEvent('redirect', link);
                                    }
                                });
                            },

                            single: true
                        }
                    },
                    setSecurityGroupsIds: function (ids) {
                        var me = this;

                        me.securityGroupIds = ids;

                        return me;
                    },
                    setSecurityGroupsNames: function (names) {
                        var me = this;

                        me.securityGroupNames = names;

                        return me;
                    },
                    getSecurityGroupsIds: function () {
                        return this.securityGroupIds || [];
                    },
                    getSecurityGroupsNames: function () {
                        return this.securityGroupNames || [];
                    },
                    getDefaultGroups: function () {
                        return this.defaultGroups;
                    },
                    getSecurityGroups: function () {
                        var me = this;

                        var securityGroupIds = me.getSecurityGroupsIds();
                        var securityGroupNames = me.getSecurityGroupsNames();

                        return Ext.Array.map(securityGroupIds, function (id, index) {
                            return {
                                id: id,
                                name: securityGroupNames[index]
                            };
                        });
                    },
                    setSecurityGroups: function (groups) {
                        var me = this;

                        var ids = Ext.Array.map(groups, function (group) {
                            if (group.isModel) {
                                return group.get('securityGroupId');
                            }

                            return Ext.isDefined(group.securityGroupId)
                                ? group.securityGroupId
                                : group.id;
                        });

                        var names = Ext.Array.map(groups, function (group) {
                            if (group.isModel) {
                                return group.get('name');
                            }

                            return Ext.isDefined(group.securityGroupName)
                                    ? group.securityGroupName
                                    : group.name;
                        });

                        me.
                            setSecurityGroupsIds(ids).
                            setSecurityGroupsNames(names).
                            setValue(
                                Ext.Array.map(ids, function(id, index) {
                                    var name = names[index] || '';

                                    if (!Ext.isEmpty(id)) {
                                        return '<span data-id=\'' + id +
                                            '\' class=\'scalr-ui-rds-tagfield-sg-name\' style=\'cursor:pointer\'>' +
                                            name + '</span>';
                                    }

                                    var warningTooltip = 'A Security Group Policy is active in this Environment, ';

                                    if (name.indexOf('*') !== -1) {
                                        warningTooltip += 'and requires that you attach Security Group matching to pattern <b>' + name + '</b> to your DB instance.<br/>' +
                                        'But there is NO or MORE THAN ONE Security group matching to pattern found.';
                                    } else {
                                        warningTooltip += 'and requires that you attach <b>' + name + '</b> Security Group to your DB instance.\n' +
                                        'But <b>' + name + '</b> does not exist in current VPC.';
                                    }

                                    return '<div data-qtip=\'' + warningTooltip + '\'' + ' >' +
                                        '<img src=\'' + Ext.BLANK_IMAGE_URL +
                                        '\' class=\'x-icon-warning\' style=\'vertical-align:middle;margin-right:6px\' />' +
                                        name + '</div>';


                                })
                            );

                        return me;
                    }
                }, {
                    xtype: 'taglistfield',
                    name: 'DBSecurityGroups',
                    cls: 'x-tagfield-force-item-hover',
                    readOnly: true,
                    scrollable: true,
                    listeners: {
                        afterrender: {
                            fn: function (field) {
                                field.getEl().on('click', function (event, target) {

                                    target = Ext.get(target);

                                    if (target.hasCls('x-tagfield-item-text')) {

                                        var link = '#/tools/aws/rds/sg/edit?' +

                                            Ext.Object.toQueryString({
                                                dbSgName: target.getHtml(),
                                                cloudLocation: form.down('[name=cloudLocation]').getValue()
                                            });

                                        Scalr.event.fireEvent('redirect', link);
                                    }
                                });
                            },

                            single: true
                        }
                    },
                    getSubmitValue: function () {
                        return Ext.encode(
                            this.getValue()
                        );
                    }
                }, {
                    xtype: 'button',
                    text: 'Change',
                    width: 80,
                    margin: '0 0 0 12',
                    handler: function () {
                        var vpcId = form.down('[name=VpcId]').getValue();
                        var isVpcDefined = !!vpcId;

                        var field = !isVpcDefined
                            ? form.down('[name=DBSecurityGroups]')
                            : form.down('[name=VpcSecurityGroupIds]');

                        var excludeGroups = isVpcDefined && securityGroupsPolicy.enabled
                            ? field.getDefaultGroups()
                            : [];

                        editSecurityGroups(
                            form.down('[name=cloudLocation]').getValue(),
                            vpcId,
                            !isVpcDefined ? field.getValue() : field.getSecurityGroups(),
                            excludeGroups
                        );
                    }
                }]
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database Engine',
            name: 'engineSettings',
            hidden: true,
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
                xtype: 'combo',
                name: 'Engine',
                fieldLabel: 'Engine',
                store: [
                    [ 'aurora', 'Amazon Aurora' ]
                ],
                queryMode: 'local',
                editable: false,
                listeners: {
                    change: function (me, value, oldValue) {
                        Scalr.Request({
                            processBox: {
                                type: 'load'
                            },
                            url: '/tools/aws/rds/instances/xGetEngineVersions',
                            params: {
                                cloudLocation: form.down('[name=cloudLocation]').getValue(),
                                engine: value
                            },
                            success: function (response) {
                                var engineVersionField = me.next();
                                engineVersionField.reset();

                                var engineVersionStore = engineVersionField.getStore();
                                engineVersionStore.removeAll();

                                var engineVersions = response['engineVersions'];

                                if (engineVersions) {
                                    engineVersionStore.loadData(engineVersions);
                                    engineVersionField.setValue(
                                        engineVersionStore.last()
                                    );
                                }
                            }
                        });

                        /*form.down('[name=CharacterSetName]')
                            .setVisible(false)
                            .setDisabled(true)
                            .reset();*/
                    }
                }
            }, {
                xtype: 'combo',
                name: 'EngineVersion',
                fieldLabel: 'Version',
                editable: false,
                queryMode: 'local',
                store: {
                    reader: 'array',
                    fields: [ 'version' ]
                },
                valueField: 'version',
                displayField: 'version',
                listeners: {
                    change: function (me, value) {
                        if (!Ext.isEmpty(value)) {
                            var cloudLocation = form.down('[name=cloudLocation]').getValue();
                            var engine = form.down('[name=Engine]').getValue();

                            Scalr.Request({
                                processBox: {
                                    type: 'load'
                                },
                                url: '/tools/aws/rds/instances/xGetOptionGroups',
                                params: {
                                    cloudLocation: cloudLocation,
                                    engine: engine,
                                    engineVersion: value
                                },
                                success: function (response) {
                                    var optionGroupField = form.down('[name=OptionGroupName]');
                                    optionGroupField.reset();
                                    optionGroupField.enable();

                                    var optionGroupStore = optionGroupField.getStore();
                                    optionGroupStore.removeAll();
                                    optionGroupStore.loadData(response['optionGroups']);

                                    var optionGroup = response['defaultOptionGroupName']
                                        || optionGroupStore.first();

                                    if (optionGroup) {
                                        optionGroupField.setValue(optionGroup);
                                        return;
                                    }

                                    optionGroupField.disable();
                                }
                            });

                            Scalr.Request({
                                processBox: {
                                    type: 'load'
                                },
                                url: '/tools/aws/rds/instances/xGetParameterGroup',
                                params: {
                                    cloudLocation: cloudLocation,
                                    engine: engine,
                                    engineVersion: value
                                },
                                success: function (response) {
                                    var parameterGroupField = form.down('[name=DBClusterParameterGroupName]');
                                    parameterGroupField.reset();
                                    parameterGroupField.enable();

                                    var parameterGroupStore = parameterGroupField.getStore();
                                    parameterGroupStore.removeAll();
                                    parameterGroupStore.loadData(response['groups']);

                                    var parameterGroup = response['default']
                                        || parameterGroupStore.first();

                                    if (parameterGroup) {
                                        parameterGroupField.setValue(parameterGroup);
                                        return;
                                    }

                                    parameterGroupField.disable();
                                }
                            });
                        }
                    }
                }
            }, {
                xtype: 'combo',
                name: 'DBClusterParameterGroupName',
                fieldLabel: 'Parameter Group',
                store: {
                    fields: [ 'dBParameterGroupName' ],
                    proxy: 'object'
                },
                queryMode: 'local',
                valueField: 'dBParameterGroupName',
                displayField: 'dBParameterGroupName',
                editable: false
            }, {
                xtype: 'combo',
                name: 'OptionGroupName',
                fieldLabel: 'Option Group',
                store: {
                    fields: [ 'optionGroupName' ],
                    proxy: 'object'
                },
                queryMode: 'local',
                valueField: 'optionGroupName',
                displayField: 'optionGroupName',
                editable: false
            }, {
                xtype: 'combo',
                name: 'CharacterSetName',
                fieldLabel: 'Character Set Name',
                store: [
                    'AL32UTF8',
                    'JA16EUC',
                    'JA16EUCTILDE',
                    'JA16SJIS',
                    'JA16SJISTILDE',
                    'KO16MSWIN949',
                    'TH8TISASCII',
                    'VN8MSWIN1258',
                    'ZHS16GBK',
                    'ZHT16HKSCS',
                    'ZHT16MSWIN950',
                    'ZHT32EUC',
                    'BLT8ISO8859P13',
                    'BLT8MSWIN1257',
                    'CL8ISO8859P5',
                    'CL8MSWIN1251',
                    'EE8ISO8859P2',
                    'EL8ISO8859P7',
                    'EL8MSWIN1253',
                    'EE8MSWIN1250',
                    'NE8ISO8859P10',
                    'NEE8ISO8859P4',
                    'WE8ISO8859P15',
                    'WE8MSWIN1252',
                    'AR8ISO8859P6',
                    'AR8MSWIN1256',
                    'IW8ISO8859P8',
                    'IW8MSWIN1255',
                    'TR8MSWIN1254',
                    'WE8ISO8859P9',
                    'US7ASCII',
                    'UTF8',
                    'WE8ISO8859P1'
                ],
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip:
                            'For Oracle DB Instances only.<br>' +
                            'The character set being used by the database.<br>' +
                            'Default is <b>AL32UTF8</b>.'
                    }
                }],
                value: 'AL32UTF8',
                queryMode: 'local',
                editable: false,
                hidden: true
            }, {
                xtype: 'numberfield',
                name: 'Port',
                fieldLabel: 'Port',
                value: 3306,
                minValue: 1150,
                maxValue: 65535,
                allowBlank: false
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database',
            name: 'databaseSettings',
            hidden: true,
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
                xtype: 'textfield',
                name: 'MasterUsername',
                fieldLabel: 'Master Username',
                allowBlank: false
            }, {
                xtype: 'textfield',
                name: 'MasterUserPassword',
                fieldLabel: 'Master Password',
                allowBlank: false,
                regex: /^[a-z0-9!#$%&'()*+,.:;<=>?\[\]\\^_`{|}~-]*$/i,
                invalidText: 'Master Password can be any printable ASCII character except "/", """, or "@"',
                minLength: 8,
                minLengthText: 'Master Password must be a minimum of 8 characters'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Initial Database Name',
                flex: 1,
                name: 'DatabaseName',
                regex: /^[a-z][a-z0-9]*$/i,
                invalidText: 'Initial Database Name must begin with a letter and contain only alphanumeric characters',
                maxLength: 64,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: 'If you leave this empty, no initial database will be created'
                    }
                }]
            }]
        }, {
            xtype: 'fieldset',
            title: 'Maintenance Windows and Backups',
            name: 'maintenanceWindowSettings',
            hidden: true,
            items: [{
                xtype: 'hiddenfield',
                name: 'PreferredMaintenanceWindow'
            }, {
                xtype: 'hiddenfield',
                name: 'PreferredBackupWindow'
            }, {
                xtype: 'fieldcontainer',
                layout: {
                    type: 'hbox'
                },
                defaults: {
                    margin: '0 3 0 0'
                },
                items: [{
                    labelWidth: 200,
                    width: 275,
                    xtype: 'combo',
                    itemId: 'FirstDay',
                    fieldLabel: 'Preferred Maintenance Window',
                    queryMode: 'local',
                    editable: false,
                    store: [
                        ['sun','Sun'], ['mon','Mon'],
                        ['tue','Tue'],['wed','Wed'],
                        ['thu','Thur'],['fri','Fri'],
                        ['sat','Sat']
                    ],
                    value: 'mon'
                },{
                    xtype: 'displayfield',
                    value: ' : '
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'fhour',
                    value: '05'
                },{
                    xtype: 'displayfield',
                    value: ' : '
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'fminute',
                    value: '00'
                },{
                    xtype: 'displayfield',
                    value: ' - '
                },{
                    width: 70,
                    xtype: 'combo',
                    itemId: 'LastDay',
                    queryMode: 'local',
                    editable: false,
                    store: [
                        ['sun','Sun'], ['mon','Mon'],
                        ['tue','Tue'],['wed','Wed'],
                        ['thu','Thur'],['fri','Fri'],
                        ['sat','Sat']
                    ],
                    value: 'mon'
                },{
                    xtype: 'displayfield',
                    value: ' : '
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'lhour',
                    value: '09'
                },{
                    xtype: 'displayfield',
                    value: ' : '
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'lminute',
                    value: '00'
                },{
                    xtype: 'displayfield',
                    value: 'UTC',
                    margin: '0 0 0 3',
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        position: 'outer',
                        icons: {
                            id: 'info',
                            tooltip: 'Format: hh24:mi - hh24:mi'
                        }
                    }]
                }]
            }, {
                xtype: 'fieldcontainer',
                layout: {
                    type: 'hbox'
                },
                items: [{
                    labelWidth: 200,
                    width: 240,
                    xtype: 'textfield',
                    itemId: 'bfhour',
                    fieldLabel: 'Preferred Backup Window',
                    value: '10'
                },{
                    xtype: 'displayfield',
                    value: ' : ',
                    margin: '0 0 0 3'
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'bfminute',
                    value: '00',
                    margin: '0 0 0 3'
                },{
                    xtype: 'displayfield',
                    value: ' - ',
                    margin: '0 0 0 3'
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'blhour',
                    value: '12',
                    margin: '0 0 0 3'
                },{
                    xtype: 'displayfield',
                    value: ' : ',
                    margin: '0 0 0 3'
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'blminute',
                    value: '00',
                    margin: '0 0 0 3'
                },{
                    xtype: 'displayfield',
                    value: 'UTC',
                    margin: '0 0 0 6',
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        position: 'outer',
                        icons: {
                            id: 'info',
                            tooltip: 'Format: hh24:mi - hh24:mi'
                        }
                    }]
                }]
            }, {
                xtype: 'fieldcontainer',
                layout: 'hbox',
                items: [{
                    labelWidth: 200,
                    width: 280,
                    xtype: 'numberfield',
                    name: 'BackupRetentionPeriod',
                    fieldLabel: 'Backup Retention Period',
                    value: 1,
                    minValue: 0,
                    maxValue: 35
                }, {
                    xtype: 'displayfield',
                    margin: '0 0 0 9',
                    value: 'days'
                }]
            }]
        }]
    });

    var applySecurityGroups = function (groups) {
        var securityGroupNames = [];

        Ext.Array.each(groups, function (securityGroup) {
            securityGroupNames.push(
                securityGroup['dBSecurityGroupName']
            );
        });

        var field = form.down('[name=DBSecurityGroups]');
        field.reset();
        field.setValue(
            securityGroupNames.join(', ')
        );

        return true;
    };

    var applyAvailabilityZone = function (zones) {
        zones = !Ext.isEmpty(zones) ? zones : [];

        zones.unshift({
            id: '',
            name: 'No preference'
        });

        var field = form.down('[name=AvailabilityZone]');
        field.reset();

        var store = field.getStore();
        store.clearFilter();
        store.loadData(zones);

        field.setValue(store.first());

        return true;
    };

    var applyParameters = function (cloudLocation) {
        Scalr.Request({
            processBox: {
                type: 'action'
            },
            url: '/tools/aws/rds/instances/xGetParameters/',
            params: {
                cloudLocation: cloudLocation
            },
            scope: this,
            success: function (response) {
                applySecurityGroups(response['sgroups']);
                applyAvailabilityZone(response['zones']);

                form.down('[name=Engine]').setValue('aurora');

                form.getForm().isValid();
            },
            failure: function() {
                form.disable();
            }
        });
    };

    var updateSecurityGroups = function (securityGroups, isVpcDefined) {
        if (isVpcDefined) {
            form.down('[name=VpcSecurityGroupIds]').
                setSecurityGroups(securityGroups);
            return true;
        }

        form.down('[name=DBSecurityGroups]').setValue(
            Ext.Array.map(securityGroups, function (group) {
                return group.get('name');
            })
        );

        return true;
    };

    /*var updateSecurityGroups = function (selectedGroups, isVpcDefined) {
        var securityGroupField = !isVpcDefined
            ? form.down('[name=DBSecurityGroups]')
            : form.down('[name=VpcSecurityGroupIds]');

        var selectedGroupNames = [];
        var selectedGroupIds = [];

        Ext.Array.each(selectedGroups, function (record) {
            selectedGroupNames.push(
                record.get('name')
            );

            if (isVpcDefined) {
                selectedGroupIds.push(
                    record.get('id')
                );
            }
        });

        if (isVpcDefined) {
            securityGroupField.setSecurityGroupsIds(selectedGroupIds);
        }

        securityGroupField.setValue(
            selectedGroupNames.join(',')
        );

        return true;
    };*/

    var editSecurityGroups = function (cloudLocation, vpcId, selected, excludeGroups) {
        var isVpcDefined = !!vpcId;

        var filter = isVpcDefined
            ? Ext.encode({
                vpcId: vpcId,
                considerGovernance: securityGroupsPolicy.enabled,
                serviceName: 'rds'
              })
            : null;

        Scalr.Confirm({
            formWidth: 950,
            alignTop: true,
            winConfig: {
                autoScroll: false
            },
            form: [{
                xtype: 'rdssgmultiselect',
                title: 'Add Security Groups to DB Instance',
                listGroupsUrl: '/tools/aws/rds/xListSecurityGroups',
                limit: isVpcDefined ? 5 : 25,
                minHeight: 200,
                selection: selected,
                defaultVpcGroups: excludeGroups,
                governanceWarning: isVpcDefined && securityGroupsPolicy.enabled && !securityGroupsPolicy.allowAddingGroups
                    ? securityGroupsPolicy.requiredGroupsMessage
                    : null,
                disableAddButton: securityGroupsPolicy.enabled && (!securityGroupsPolicy.allowAddingGroups || !Ext.isEmpty(securityGroupsPolicy.additionalGroupsList)),
                storeExtraParams: {
                    platform: !isVpcDefined ? 'rds' : 'ec2',
                    cloudLocation: cloudLocation,
                    filters: filter
                },
                accountId: moduleParams.accountId,
                remoteAddress: moduleParams.remoteAddress,
                vpc: {
                    id: vpcId,
                    region: cloudLocation
                }
            }],
            disabled: true,
            closeOnSuccess: true,
            scope: this,
            success: function (formValues, securityGroupForm) {
                updateSecurityGroups(
                    securityGroupForm.down('rdssgmultiselect').selection,
                    isVpcDefined
                );

                return true;
            }
        });
    };

    var rdsGovernance = Scalr.getGovernance('ec2', 'aws.rds') || {};
    if (rdsGovernance['db_instance_requires_farm_association'] == 1) {
        var fieldsetAssociateFarmId = form.down('#associateFarmId');
        fieldsetAssociateFarmId.expand();
        form.getForm().findField('farmId').enable();
        fieldsetAssociateFarmId.checkboxCmp.setDisabled(true);
    }


    return form;
});

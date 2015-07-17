Scalr.regPage('Scalr.ui.tools.aws.rds.instances.create', function (loadParams, moduleParams) {

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

            policy.defaultGroups = params.value.split(',');
            policy.defaultGroupsList = '<b>' + policy.defaultGroups.join('</b>, <b>') + '</b>';

            Ext.apply(policy, {
                allowAddingGroups: !!params['allow_additional_sec_groups'],
                enabledPolicyMessage: 'A Security Group Policy is active in this Environment, ' +
                    'and requires that you attach the following Security Groups to your DB instance: ' +
                    policy.defaultGroupsList + '.',
                requiredGroupsMessage: 'A Security Group Policy is active in this Environment, ' +
                    'and restricts you to the following Security Groups: ' +
                    policy.defaultGroupsList + '.'
            });
        }

        return policy;

    }( Scalr.getGovernance('ec2', 'aws.additional_security_groups') );


    var form = Ext.create('Ext.form.Panel', {
        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instances &raquo; Launch',
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
                text: 'Launch',
                itemId: 'launch',
                disabled: true,
                handler: function () {
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
                        url: '/tools/aws/rds/instances/xLaunchInstance',
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
                labelWidth: 130,
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
                            return vpcPolicy.enabled
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
                        form.down('#launch').enable();
                        Ext.Array.each(
                            form.query('fieldset[name!=locationSettings]'), function (fieldset) {
                                fieldset.show().enable();
                            }
                        );

                        if (vpcPolicy.enabled && !vpcPolicy.launchWithVpcOnly) {
                            applyParameters(value);
                            return;
                        }

                        var vpcField = field.next();
                        vpcField.show().reset();

                        var vpcStore = vpcField.getStore();
                        vpcStore.removeAll();

                        Scalr.Request({
                            processBox: {
                                type: 'load'
                            },
                            url: '/platforms/ec2/xGetVpcList',
                            params: {
                                cloudLocation: value
                            },
                            success: function (data) {
                                var vpc = data.vpc;
                                var defaultVpc = data.default;

                                if (!Ext.isEmpty(defaultVpc)) {
                                    if (vpcPolicy.enabled) {
                                        var allowedVpcs = vpcPolicy.vpcs[
                                            form.down('[name=cloudLocation]').getValue()
                                        ].ids;

                                        if (!Ext.isEmpty(allowedVpcs) && !Ext.Array.contains(allowedVpcs, defaultVpc)) {
                                            defaultVpc = null;
                                        }
                                    }
                                }

                                if (Ext.isEmpty(defaultVpc)) {
                                    vpc.unshift({
                                        id: 0,
                                        name: ''
                                    });
                                }

                                vpcStore.loadData(vpc);
                                vpcField.setValue(defaultVpc || vpcStore.first());
                            }
                        });

                        applyParameters(value);
                    }
                }
            }, {
                name: 'VpcId',
                fieldLabel: 'VPC',
                emptyText: 'No VPC selected. Launch DB instance outside VPC',
                queryMode: 'local',
                hidden: true,
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

                            return !Ext.isEmpty(allowedVpcs)
                                ? Ext.Array.contains(allowedVpcs, record.get('id'))
                                : true;
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
                        availabilityZoneStore.removeFilter('bySubnetGroup');

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
            title: 'Database Instance and Storage',
            name: 'instanceSettings',
            hidden: true,
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
                xtype: 'textfield',
                name: 'DBInstanceIdentifier',
                fieldLabel: 'DB Instance Identifier',
                emptyText: 'Unique name for this Database Instance',
                allowBlank: false,
                regex: /^[a-zA-Z].*$/,
                invalidText: 'Identifier must start with a letter',
                minLength: 1,
                maxLength: 63
            }, /*{
                xtype: 'combo',
                name: 'DBInstanceClass',
                fieldLabel: 'Type',
                store: {
                    fields: [ 'dBInstanceClass' ],
                    proxy: {
                        type: 'cachedrequest',
                        crscope: 'rdsInstances',
                        url: '/tools/aws/rds/instances/xGetInstanceTypes',
                        root: 'instanceTypes'
                    },
                    listeners: {
                        load: function (store) {
                            form.down('[name=DBInstanceClass]').
                                setValue(store.first()).
                                validate();
                        }
                    }
                },
                valueField: 'dBInstanceClass',
                displayField: 'dBInstanceClass',
                allowBlank: false,
                editable: false
            },*/{
                xtype: 'combo',
                name: 'DBInstanceClass',
                fieldLabel: 'Type',
                labelWidth: 200,
                width: 500,
                store: [
                    'db.t1.micro', 'db.m1.small', 'db.m1.medium',
                    'db.m1.large', 'db.m1.xlarge', 'db.m2.2xlarge ',
                    'db.m2.4xlarge' , 'db.m3.medium ', 'db.m3.large',
                    'db.m3.xlarge', 'db.m3.2xlarge', 'db.r3.large',
                    'db.r3.xlarge', 'db.r3.2xlarge', 'db.r3.4xlarge',
                    'db.r3.8xlarge', 'db.t2.micro', 'db.t2.small',
                    'db.t2.medium'
                ],
                queryMode: 'local',
                allowBlank: false,
                value: 'db.m1.small',
                editable: false,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'warning',
                        tooltip:
                            'Instance type availability varies across AWS regions. ' +
                            'Consult the AWS Documentation on ' +
                            '<a target="_blank" href="http://aws.amazon.com/rds/pricing/">current</a>' +
                            ' and ' +
                            '<a target="_blank" href="http://aws.amazon.com/rds/previous-generation/">legacy</a>' +
                            ' instance types for more information.'
                    }
                }]
            }, {
                xtype: 'container',
                layout: 'hbox',
                width: 500,
                items: [{
                    xtype: 'checkboxfield',
                    fieldLabel: 'Multi-AZ Deployment',
                    name: 'MultiAZ',
                    labelWidth: 200,
                    inputValue: true,
                    uncheckedValue: false,
                    listeners: {
                        change: function (field, value) {
                            form.down('[name=AvailabilityZone]').
                                setDisabled(!!value).
                                reset();

                            /*
                            var engine = form.down('[name=Engine]').getValue();

                            if (engine === 'sqlserver-ee' || engine === 'sqlserver-se') {
                                Scalr.Request({
                                    processBox: {
                                        type: 'load'
                                    },
                                    url: '/tools/aws/rds/instances/xGetOptionGroups',
                                    params: {
                                        cloudLocation: form.down('[name=cloudLocation]').getValue(),
                                        engine: engine,
                                        engineVersion: form.down('[name=EngineVersion]').getValue(),
                                        multiAz: value
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
                            }
                            */
                        }
                    }
                }, {
                    xtype: 'displayfield',
                    name: 'mirroring',
                    margin: '0 0 0 6',
                    flex: 1,
                    hidden: true,
                    value: '(Mirroring)'
                }]
            }, {
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
            }, {
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
                            '" class="x-icon-governance" data-qtip="' +
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
                                    var name = names[index];

                                    if (!Ext.isEmpty(id)) {
                                        return '<span data-id=\'' + id +
                                            '\' class=\'scalr-ui-rds-tagfield-sg-name\' style=\'cursor:pointer\'>' +
                                            name + '</span>';
                                    }

                                    var warningTooltip = 'A Security Group Policy is active in this Environment,\n' +
                                        'and requires that you attach <b>' + name + '</b> Security Group to your DB instance.\n' +
                                        'But <b>' + name + '</b> does not exist in current VPC.';

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
            }, {
                xtype: 'combo',
                name: 'StorageType',
                fieldLabel: 'Storage type',
                editable: false,
                queryMode: 'local',
                store: {
                    fields: [ 'type', 'name' ],
                    data: [
                        { type: 'standard', name: 'Magnetic' },
                        { type: 'gp2', name: 'General Purpose (SSD)' },
                        { type: 'io1', name: 'Provisioned IOPS (SSD)' }
                    ]
                },
                value: 'gp2',
                valueField: 'type',
                displayField: 'name',
                listeners: {
                    change: function (me, value) {
                        var iopsField = me.next();
                        var isIops = value === 'io1';

                        iopsField.setValue(1000);
                        iopsField.setVisible(isIops);
                        iopsField.submitValue = isIops;
                        iopsField.allowBlank = !isIops;
                    }
                }
            }, {
                xtype: 'numberfield',
                name: 'Iops',
                fieldLabel: 'IOPS',
                submitValue: false,
                hidden: true,
                value: 1000,
                minValue: 1000,
                maxValue: 30000,
                step: 1000,
                validator: function (value) {
                    return value % 1000
                        ? 'IOPS value must be an increment of 1000'
                        : true;
                }
            }, {
                xtype: 'container',
                layout: 'hbox',
                width: 530,
                items: [{
                    xtype: 'numberfield',
                    name: 'AllocatedStorage',
                    fieldLabel: 'Allocated Storage',
                    labelWidth: 200,
                    width: 500,
                    allowBlank: false,
                    minValue: 5,
                    maxValue: 3072,
                    value: 5,
                    minText: 'The minimum storage size for this engine is {0} GB',
                    maxText: 'The maximum storage size for this engine is {0} GB'
                }, {
                    xtype: 'displayfield',
                    margin: '0 0 0 6',
                    value: 'GB'
                }]
            }]
        }, {
            xtype: 'fieldset',
            title: 'Associate this database instance with a Farm' +
                   ((Scalr.getGovernance('ec2', 'aws.rds') || {})['db_instance_requires_farm_association'] == 1 ? '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="The account owner has enforced a specific policy on this setting" class="x-icon-governance" />' : ''),
            itemId: 'associateFarmId',
            collapsible: true,
            collapsed: true,
            checkboxToggle: true,
            hidden: true,
            defaults: {
                labelWidth: 200,
                width: 500
            },
            listeners: {
                beforecollapse: function() {
                    return !this.checkboxCmp.disabled;
                },
                beforeexpand: function() {
                    return !this.checkboxCmp.disabled;
                },
                expand: function() {
                    this.down('[name="farmId"]').enable();
                },
                collapse: function() {
                    this.down('[name="farmId"]').disable();
                }
            },
            items: [{
				xtype: 'combo',
				fieldLabel: 'Farm',
				matchFieldWidth: false,
				listConfig: {
					minWidth: 150
				},
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams['farms'],
					proxy: 'object'
				},
                disabled: true,
                anyMatch: true,
                autoSearch: false,
                selectOnFocus: true,
                restoreValueOnBlur: true,
				queryMode: 'local',
				name: 'farmId',
				valueField: 'id',
				displayField: 'name',
                allowBlank: false,
                plugins: [{
                    ptype: 'fieldicons',
                    position: 'outer',
                    align: 'right',
                    icons: [{id: 'question', hidden: false, tooltip: 'Only Farms you\'re allowed to manage are available'}]
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
                    ['mysql', 'MySQL'],
                    ['oracle-se1', 'Oracle SE One'],
                    ['oracle-se', 'Oracle SE'],
                    ['oracle-ee', 'Oracle EE'],
                    ['sqlserver-ee', 'Microsoft SQL Server EE'],
                    ['sqlserver-se', 'Microsoft SQL Server SE'],
                    ['sqlserver-ex', 'Microsoft SQL Server EX'],
                    ['sqlserver-web', 'Microsoft SQL Server WEB'],
                    ['postgres', 'PostgreSQL']
                ],
                storageValues: {
                    'mysql': { minValue: 5, maxValue: 3072 },
                    'oracle-se1': { minValue: 10, maxValue: 3072 },
                    'oracle-se': { minValue: 10, maxValue: 3072 },
                    'oracle-ee': { minValue: 10, maxValue: 3072 },
                    'sqlserver-ee': { minValue: 200, maxValue: 1024 },
                    'sqlserver-se': { minValue: 200, maxValue: 1024 },
                    'sqlserver-ex': { minValue: 20, maxValue: 1024 },
                    'sqlserver-web': { minValue: 20, maxValue: 1024 },
                    'postgres': { minValue: 5, maxValue: 3072 }
                },
                licenseModels: {
                    'mysql': [{ licenseModel: 'general-public-license' }],
                    'oracle-se1': [
                        { licenseModel: 'license-included' },
                        { licenseModel: 'bring-your-own-license' }
                    ],
                    'oracle-se': [{ licenseModel: 'bring-your-own-license' }],
                    'oracle-ee': [{ licenseModel: 'bring-your-own-license' }],
                    'sqlserver-ee': [{ licenseModel: 'bring-your-own-license' }],
                    'sqlserver-se': [
                        { licenseModel: 'license-included' },
                        { licenseModel: 'bring-your-own-license' }
                    ],
                    'sqlserver-ex': [{ licenseModel: 'license-included' }],
                    'sqlserver-web': [{ licenseModel: 'license-included' }],
                    'postgres': [{ licenseModel: 'postgresql-license' }]
                },
                portValues: {
                    'mysql': 3306,
                    'oracle-se1': 1521,
                    'oracle-se': 1521,
                    'oracle-ee': 1521,
                    'sqlserver-ee': 1433,
                    'sqlserver-se': 1433,
                    'sqlserver-ex': 1433,
                    'sqlserver-web': 1433,
                    'postgres': 5432
                },
                queryMode: 'local',
                editable: false,
                isOracle: function (engine) {
                    return engine.substring(0, 6) === 'oracle';
                },
                listeners: {
                    change: function (me, value) {
                        form.down('[name=Port]').setValue(
                            me.portValues[value]
                        );

                        setMultiAzStatus(value);

                        deprecateMultiAz(
                            form.down('[name=cloudLocation]').getValue(),
                            value
                        );

                        var licenseModelField = form.down('[name=LicenseModel]');
                        var licenseModelStore = licenseModelField.getStore();
                        licenseModelStore.loadData(me.licenseModels[value]);
                        licenseModelField.setValue(licenseModelStore.first());

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

                                var allocatedStorageField = form.down('[name=AllocatedStorage]');
                                Ext.apply(allocatedStorageField, me.storageValues[value]);
                                allocatedStorageField.validate();
                            }
                        });

                        var isOracle = me.isOracle(value);

                        form.down('[name=CharacterSetName]')
                            .setVisible(isOracle)
                            .setDisabled(!isOracle)
                            .reset();
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
                        if (value) {
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
                                    engineVersion: value,
                                    multiAz: form.down('[name=MultiAZ]').getValue()
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
                                    var parameterGroupField = form.down('[name=DBParameterGroup]');
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

                            /*
                            var instanceTypesField = form.down('[name=DBInstanceClass]');
                            var instanceTypesStore = instanceTypesField.getStore();
                            instanceTypesStore.removeAll();
                            instanceTypesStore.getProxy().params = {
                                cloudLocation: cloudLocation,
                                engine: engine,
                                engineVersion: value
                            };

                            instanceTypesStore.load();
                            */
                        }
                    }
                }
            }, {
                xtype: 'combo',
                name: 'LicenseModel',
                fieldLabel: 'Licensing Model',
                editable: false,
                queryMode: 'local',
                store: {
                    fields: [ 'licenseModel' ],
                    data: [
                        { licenseModel: 'license-included' },
                        { licenseModel: 'bring-your-own-license' },
                        { licenseModel: 'general-public-license' },
                        { licenseModel: 'postgresql-license' }
                    ]
                },
                valueField: 'licenseModel',
                displayField: 'licenseModel',
                value: 'general-public-license'
            }, {
                xtype: 'combo',
                name: 'DBParameterGroup',
                fieldLabel: 'Parameter Group',
                store: {
                    fields: ['dBParameterGroupName'],
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
                allowBlank: false
            }, {
                xtype: 'textfield',
                fieldLabel: 'Initial Database Name',
                flex: 1,
                name: 'DBName',
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
                labelWidth: 200,
                xtype: 'checkboxfield',
                fieldLabel: 'Auto Minor Version Upgrade',
                name: 'AutoMinorVersionUpgrade',
                inputValue: true,
                uncheckedValue: false,
                value: false
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

    var deprecateMultiAz = function (cloudLocation, engine) {
        var isRegionDeprecated = cloudLocation !== 'eu-west-1'
            && cloudLocation !== 'us-east-1'
            && cloudLocation !== 'us-west-2';

        var isMirroringRequired = engine === 'sqlserver-ee'
            || engine === 'sqlserver-se';

        var multiAzField = form.down('[name=MultiAZ]');

        if (isMirroringRequired) {
            multiAzField.setValue(false);
        }

        multiAzField.setDisabled(
            isMirroringRequired
        );

        return true;
    };

    var setMultiAzStatus = function (engine) {
        form.down('[name=mirroring]').setVisible(
            engine === 'sqlserver-ee'
            || engine === 'sqlserver-se'
        );

        var isMultiAzDeprecated = engine === 'sqlserver-web'
            || engine === 'sqlserver-ex';
        var multiAzField = form.down('[name=MultiAZ]');

        multiAzField.setDisabled(isMultiAzDeprecated);

        if (isMultiAzDeprecated) {
            multiAzField.setValue(false);
        }

        return true;
    };

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

                form.down('[name=Engine]').setValue('mysql');

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
                considerGovernance: securityGroupsPolicy.enabled
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
                title: 'Add security groups to farm role',
                limit: 10,
                minHeight: 200,
                selection: selected,
                defaultVpcGroups: excludeGroups,
                governanceWarning: isVpcDefined && securityGroupsPolicy.enabled && !securityGroupsPolicy.allowAddingGroups
                    ? securityGroupsPolicy.requiredGroupsMessage
                    : null,
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

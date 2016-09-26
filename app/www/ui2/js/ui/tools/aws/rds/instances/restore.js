Scalr.regPage('Scalr.ui.tools.aws.rds.instances.restore', function (loadParams, moduleParams) {

    var cloudLocation = loadParams.cloudLocation;

    var snapshot = moduleParams['snapshot'];

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

    var isCurrentRegionAllowed = !(vpcPolicy.enabled
        && vpcPolicy.launchWithVpcOnly
        && !Ext.Array.contains(vpcPolicy.regions, cloudLocation)
    );

    var form = Ext.create('Ext.form.Panel', {
        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instances &raquo; Restore',
        width: 700,

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
                text: 'Restore',
                handler: function() {
                    if (form.getForm().isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save',
                                msg: 'Restoring ...'
                            },
                            params: {
                                cloudLocation: loadParams['cloudLocation']
                            },
                            url: '/tools/aws/rds/instances/xRestoreInstance',
                            form: form.getForm(),
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
                }
            },{
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
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
            items: [{
                padding: 5,
                labelWidth: 130,
                xtype: 'combo',
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
                editable: false,
                width: 610,
                queryMode: 'local',
                displayField: 'name',
                valueField: 'id',
                readOnly: true,
                listeners: {
                    change: function (field, value) {

                        if (!isCurrentRegionAllowed/* || (vpcPolicy.enabled && !vpcPolicy.launchWithVpcOnly)*/) {
                            return;
                        }

                        var vpcField = field.next();

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

                                    if (!defaultVpc) {
                                        vpc.unshift({
                                            id: 0,
                                            name: ''
                                        });
                                    }

                                    var vpcStore = vpcField.getStore();

                                    vpcStore.loadData(vpc);

                                    vpcField.setValue(
                                        snapshot['VpcId']
                                    );
                                }
                            });
                            return;
                        }

                        vpcField.disable();
                    }
                }
            }, {
                xtype: 'combo',
                labelWidth: 130,
                name: 'VpcId',
                padding: 5,
                fieldLabel: 'VPC',
                editable: false,
                emptyText: 'No VPC selected. Launch DB instance outside VPC',
                queryMode: 'local',
                width: 610,
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
                        if (!isCurrentRegionAllowed) {
                            return;
                        }

                        var cloudLocation = loadParams['cloudLocation'];

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
                    }
                }
            }, {
                padding: 5,
                labelWidth: 130,
                xtype: 'combo',
                name: 'DBSubnetGroupName',
                fieldLabel: 'Subnet Group',
                emptyText: 'Select Subnet Group',
                editable: false,
                width: 610,
                queryMode: 'local',
                //queryCaching: false,
                //clearDataBeforeQuery: true,
                hidden: true,
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
                        if (!isCurrentRegionAllowed) {
                            return;
                        }

                        var availabilityZoneField = form.down('[name=AvailabilityZone]');
                        availabilityZoneField.reset();

                        var availabilityZoneStore = availabilityZoneField.getStore();
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
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
                xtype: 'displayfield',
                name: 'DBSnapshotIdentifier',
                fieldLabel: 'DB Snapshot Identifier',
                value: loadParams.snapshot,
                submitValue: true
            }, {
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
                store: Scalr.constants.rdsInstancesTypes,
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
                xtype: 'checkboxfield',
                fieldLabel: 'Multi-AZ Deployment',
                name: 'MultiAZ',
                inputValue: true,
                uncheckedValue: false,
                listeners: {
                    change: function (field, value) {
                        form.down('[name=AvailabilityZone]').setDisabled(value
                                ? true
                                : false
                        );
                    }
                }
            }, {
                itemId: 'AvailabilityZone',
                xtype: 'combo',
                name: 'AvailabilityZone',
                fieldLabel: 'Availability Zone',
                emptyText: 'No preference',
                store: {
                    model: Scalr.getModel({
                        fields: [ 'id', 'name' ]
                    }),
                    proxy: 'object',
                    data: Ext.Array.merge(
                        [ { id: '', name: 'No preference' } ],
                        moduleParams.zones
                    )
                },
                queryMode: 'local',
                editable: false,
                valueField: 'id',
                displayField: 'name',
                disabled: snapshot['multiAZ']
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
            },{
                xtype: 'numberfield',
                name: 'Iops',
                fieldLabel: 'IOPS',
                submitValue: snapshot['iops'] ? true : false,
                hidden: snapshot['iops'] ? false : true,
                value: 1000,
                minValue: 1000,
                maxValue: 30000,
                step: 1000,
                validator: function (value) {
                    return value % 1000
                        ? 'IOPS value must be an increment of 1000'
                        : true;
                }
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database Engine',
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
                xtype: 'combo',
                name: 'Engine',
                fieldLabel: 'Engine',
                store: {
                    fields: [ 'id', 'name' ],
                    data: [
                        { id: 'mysql', name: 'MySQL' },
                        { id: 'oracle-se1', name: 'Oracle SE One' },
                        { id: 'oracle-se', name: 'Oracle SE' },
                        { id: 'oracle-ee', name: 'Oracle EE' },
                        { id: 'sqlserver-ee', name: 'Microsoft SQL Server EE' },
                        { id: 'sqlserver-se', name: 'Microsoft SQL Server SE' },
                        { id: 'sqlserver-ex', name: 'Microsoft SQL Server EX' },
                        { id: 'sqlserver-web', name: 'Microsoft SQL Server WEB' },
                        { id: 'postgres', name: 'PostgreSQL' },
                        { id: 'mariadb', name: 'MariaDB' }
                    ],
                    filters: [{
                        filterId: 'bySnapshotEngine',
                        filterFn: function (record) {
                            return snapshot['Engine'].substring(0, 3)
                                === record.get('id').substring(0, 3);
                        }
                    }]
                },
                valueField: 'id',
                displayField: 'name',
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
                    'postgres': [{ licenseModel: 'postgresql-license' }],
                    'mariadb': [{ licenseModel: 'general-public-license' }]
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
                    'postgres': 5432,
                    'mariadb': 3306
                },
                queryMode: 'local',
                editable: false,
                listeners: {
                    change: function (me, value) {
                        form.down('[name=Port]').setValue(
                            me.portValues[value]
                        );

                        var licenseModelField = form.down('[name=LicenseModel]');
                        var licenseModelStore = licenseModelField.getStore();
                        licenseModelStore.loadData(me.licenseModels[value]);
                        licenseModelField.setValue(licenseModelStore.first());

                        if (!isCurrentRegionAllowed) {
                            return;
                        }

                        if (value) {
                            Scalr.Request({
                                processBox: {
                                    type: 'load'
                                },
                                url: '/tools/aws/rds/instances/xGetOptionGroups',
                                params: {
                                    cloudLocation: loadParams.cloudLocation,
                                    engine: value,
                                    engineVersion: snapshot['EngineVersion']
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
                value: snapshot['licenseModel'] || 'general-public-license'
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
                xtype: 'numberfield',
                name: 'Port',
                fieldLabel: 'Port',
                minValue: 1150,
                maxValue: 65535,
                allowBlank: false
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database',
            itemId: 'databaseSettings',
            hidden: snapshot['Engine'] === 'mysql',
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Initial Database Name',
                flex: 1,
                name: 'DBName',
                submitValue: snapshot['Engine'] !== 'mysql',
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
            title: 'Maintenance',
            name: 'maintenanceSettings',
            items: [{
                labelWidth: 200,
                xtype: 'checkboxfield',
                fieldLabel: 'Auto Minor Version Upgrade',
                name: 'AutoMinorVersionUpgrade',
                inputValue: true,
                uncheckedValue: false,
                submitValue: true
            }]
        }]
    });

    form.getForm().setValues(snapshot);

    form.down('[name=cloudLocation]').
        setValue(cloudLocation);

    form.down('[name=StorageType]').getStore().addFilter({
        filterFn: function (record) {
            return record.get('type') !== 'io1' || parseInt(snapshot['AllocatedStorage']) >= 100;
        }
    });

    if (snapshot['Engine'] === 'mariadb') {
        var dBInstanceClassField = form.down('[name=DBInstanceClass]');
        var dBInstanceClassStore = dBInstanceClassField.getStore();

        dBInstanceClassStore.filterBy(function (record) {
            var instanceType = record.get('field1');

            return Ext.Array.some([ 't2', 'm3', 'r3' ], function (type) {
                return instanceType.indexOf('.' + type) !== -1;
            });
        });

        dBInstanceClassField.setValue(
            dBInstanceClassStore.first()
        );

        form.down('[name=VpcId]').allowBlank = false;

        form.down('#databaseSettings')
            .disable()
            .hide();
    }

    /*
    var instanceTypesField = form.down('[name=DBInstanceClass]');
    var instanceTypesStore = instanceTypesField.getStore();
    instanceTypesStore.removeAll();
    instanceTypesStore.getProxy().params = {
        cloudLocation: loadParams.cloudLocation,
        engine: snapshot['Engine'],
        engineVersion: snapshot['EngineVersion']
    };

    instanceTypesStore.load();
    */

    form.getForm().isValid();

    if (!isCurrentRegionAllowed) {

        form.disable();

        Scalr.message.Warning(
            'You can\'t restore a DB instance from this snapshot,'
            + ' because the VPC Policy active in your Environment doesn\'t allow you to launch new DB instances in <b>'
            + moduleParams.locations[cloudLocation] + '</b>.'
        );
    }

    return form;
});

Scalr.regPage('Scalr.ui.tools.aws.rds.instances.create', function (loadParams, moduleParams) {

    var form = Ext.create('Ext.form.Panel', {
        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instances &raquo; Launch',
        width: 700,

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'Launch',
                handler: function () {
                    form.down('[name=PreferredMaintenanceWindow]').setValue(form.down('#FirstDay').value + ':' + form.down('#fhour').value + ':' + form.down('#fminute').value + '-' + form.down('#LastDay').value + ':' + form.down('#lhour').value + ':' + form.down('#lminute').value);
                    form.down('[name=PreferredBackupWindow]').setValue(form.down('#bfhour').value + ':' + form.down('#bfminute').value + '-' + form.down('#blhour').value + ':' + form.down('#blminute').value);

                    if (form.getForm().isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save',
                                msg: 'Launching ...'
                            },
                            url: '/tools/aws/rds/instances/xLaunchInstance',
                            form: form.getForm(),
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
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
            title: 'Location and VPC Settings',
            name: 'locationSettings',

            defaults: {
                xtype: 'combo',
                editable: false,
                width: 610,
                padding: 5
            },

            items: [{
                fieldLabel: 'Location',
                emptyText: 'Select location',
                name: 'cloudLocation',
                store: {
                    fields: [ 'id', 'name' ],
                    data: moduleParams.locations,
                    proxy: 'object'
                },
                queryMode: 'local',
                displayField: 'name',
                valueField: 'id',
                listeners: {
                    change: function (field, value) {
                        Ext.Array.each(
                            form.query('fieldset[name!=locationSettings]'), function (fieldset) {
                                fieldset.show().enable();
                            }
                        );

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

                                if (!defaultVpc) {
                                    vpc.unshift({
                                        id: null,
                                        name: ''
                                    });
                                }

                                vpcStore.loadData(vpc);
                                vpcField.setValue(data.default || vpcStore.first());
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
                    fields: [ 'id', 'name', 'defaultSecurityGroupId', 'defaultSecurityGroupName' ]
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
                        VpcSecurityGroupIds.submitValue = !!value;

                        var vpcRecord = field.getStore().getById(value);

                        if (vpcRecord) {
                            VpcSecurityGroupIds.
                                setSecurityGroupsIds(
                                [ vpcRecord.get('defaultSecurityGroupId') ]
                            ).
                                setValue(
                                vpcRecord.get('defaultSecurityGroupName') || null
                            );
                        }
                    }
                }
            }, {
                name: 'DBSubnetGroupName',
                fieldLabel: 'Subnet Group',
                emptyText: 'Select Subnet Group',
                hidden: true,
                queryCaching: false,
                clearDataBeforeQuery: true,
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
                    }
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
                                        return availabilityZoneNames.indexOf(record.get('name')) !== -1;
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
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database Instance and Storage',
            name: 'instanceSettings',
            hidden: true,
            defaults: {
                labelWidth: 200,
                width: 455
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
                xtype: 'container',
                layout: 'hbox',
                width: '100%',
                items: [{
                    xtype: 'combo',
                    name: 'DBInstanceClass',
                    fieldLabel: 'Type',
                    labelWidth: 200,
                    width: 455,
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
                    editable: false
                }, {
                    xtype: 'displayfield',
                    flex: 1,
                    margin: '0 0 0 6',
                    value:
                        '<img class="tipHelp" src="/ui2/images/icons/warning_icon_16x16.png" data-qtip=\''
                        + 'Instance type availability varies across AWS regions. ' +
                        'Consult the AWS Documentation on ' +
                        '<a target="_blank" href="http://aws.amazon.com/rds/pricing/">current</a>' +
                        ' and ' +
                        '<a target="_blank" href="http://aws.amazon.com/rds/previous-generation/">legacy</a>' +
                        ' instance types for more information.'
                        + '\' style="cursor: help; height: 16px;">'
                }]
            }, {
                xtype: 'container',
                layout: 'hbox',
                width: 455,
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
                    fields: ['id', 'name'],
                    proxy: 'object'
                },
                queryMode: 'local',
                editable: false,
                emptyText: 'No preference',
                valueField: 'id',
                displayField: 'name'
            }, {
                xtype: 'container',
                layout: 'hbox',
                width: '100%',
                items: [{
                    xtype: 'displayfield',
                    fieldLabel: 'Security Groups',
                    labelWidth: 200,
                    maxWidth: 550,
                    name: 'VpcSecurityGroupIds',
                    submitValue: false,
                    hidden: true,
                    listeners: {
                        change: function (me, newValue, oldValue) {
                            if (newValue && !oldValue) {
                                me.setFieldStyle('margin-right: 12px');
                                me.updateLayout();
                            }

                            if (!newValue && oldValue) {
                                me.setFieldStyle('margin-right: 0');
                                me.updateLayout();
                            }
                        }
                    },
                    renderer: function (value) {
                        var me = this;

                        var securityGroupIds = me.getSecurityGroupsIds();

                        if (value && securityGroupIds.length) {
                            var separator = ', ';
                            var cloudLocation = form.down('[name=cloudLocation]').getValue();
                            var securityGroupNames = value.split(separator);
                            var renderedValues = [];

                            Ext.Array.each(securityGroupNames, function (name, i) {
                                renderedValues.push(
                                    '<a href="#/security/groups/'
                                    + securityGroupIds[i]
                                    + '/edit?' + Ext.Object.toQueryString({
                                        platform: 'ec2',
                                        cloudLocation: cloudLocation
                                    }) + '">' + name + '</a>'
                                );
                            });

                            value = renderedValues.join(separator);
                        }

                        return value;
                    },
                    setSecurityGroupsIds: function (value) {
                        var me = this;

                        me.securityGroupIds = value;

                        return me;
                    },
                    getSecurityGroupsIds: function () {
                        return this.securityGroupIds || [];
                    },
                    getSubmitValue: function () {
                        var me = this;

                        return Ext.encode(
                            me.getSecurityGroupsIds()
                        );
                    }
                }, {
                    xtype: 'displayfield',
                    fieldLabel: 'Security Groups',
                    labelWidth: 200,
                    maxWidth: 550,
                    name: 'DBSecurityGroups',
                    submitValue: true,
                    listeners: {
                        change: function (me, newValue, oldValue) {
                            if (newValue && !oldValue) {
                                me.setFieldStyle('margin-right: 12px');
                                me.updateLayout();
                            }

                            if (!newValue && oldValue) {
                                me.setFieldStyle('margin-right: 0');
                                me.updateLayout();
                            }
                        }
                    },
                    renderer: function (value) {
                        if (value) {
                            var separator = ', ';
                            var cloudLocation = form.down('[name=cloudLocation]').getValue();
                            var securityGroupNames = value.split(separator);
                            var renderedValues = [];

                            Ext.Array.each(securityGroupNames, function (name) {
                                renderedValues.push(
                                    '<a href="#/tools/aws/rds/sg/edit?' + Ext.Object.toQueryString({
                                        dbSgName: name,
                                        cloudLocation: cloudLocation
                                    }) + '">' + name + '</a>'
                                );
                            });

                            value = renderedValues.join(separator);
                        }

                        return value;
                    },
                    getSubmitValue: function () {
                        var me = this;

                        return Ext.encode(me.getRawValue().split(', '));
                    }
                }, {
                    xtype: 'button',
                    text: 'Change',
                    width: 80,
                    handler: function () {
                        var vpcId = form.down('[name=VpcId]').getValue();

                        var vpcIdFilter = vpcId ? Ext.encode({
                            vpcId: vpcId
                        }) : null;

                        var securityGroupField = !vpcIdFilter
                            ? form.down('[name=DBSecurityGroups]')
                            : form.down('[name=VpcSecurityGroupIds]');

                        editSecurityGroups(
                            form.down('[name=cloudLocation]').getValue(),
                            vpcId,
                            vpcIdFilter,
                            securityGroupField.getValue().split(', ')
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
                width: 485,
                items: [{
                    xtype: 'numberfield',
                    name: 'AllocatedStorage',
                    fieldLabel: 'Allocated Storage',
                    labelWidth: 200,
                    width: 455,
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
            title: 'Database Engine',
            name: 'engineSettings',
            hidden: true,
            defaults: {
                labelWidth: 200,
                width: 455
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
                width: 455
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
                xtype: 'fieldcontainer',
                fieldLabel: 'Initial Database Name',
                labelWidth: 200,
                width: 481,
                layout: 'hbox',
                items: [{
                    xtype: 'textfield',
                    flex: 1,
                    name: 'DBName'
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: 'If you leave this empty, no initial database will be created'
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
                xtype: 'container',
                layout: {
                    type: 'hbox'
                },
                defaults: {
                    margin: '0 3 0 0'
                },
                items: [{
                    labelWidth: 200,
                    width: 270,
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
                    margin: '0 0 0 3'
                },{
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    info: 'Format: hh24:mi - hh24:mi'
                }]
            }, {
                xtype: 'container',
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
                    margin: '0 0 0 6'
                },{
                    xtype: 'displayinfofield',
                    info: 'Format: hh24:mi - hh24:mi',
                    margin: '0 0 0 6'
                }]
            }, {
                xtype: 'container',
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
        var field = form.down('[name=AvailabilityZone]');
        field.reset();

        var store = field.getStore();
        store.clearFilter();
        store.loadData(zones);

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

    var updateSecurityGroups = function (selectedGroups, runOnVpc) {
        var securityGroupField = !runOnVpc
            ? form.down('[name=DBSecurityGroups]')
            : form.down('[name=VpcSecurityGroupIds]');

        var selectedGroupNames = [];
        var selectedGroupIds = [];

        Ext.Array.each(selectedGroups, function (record) {
            selectedGroupNames.push(
                record.get('name')
            );

            if (runOnVpc) {
                selectedGroupIds.push(
                    record.get('id')
                );
            }
        });

        if (runOnVpc) {
            securityGroupField.setSecurityGroupsIds(selectedGroupIds);
        }

        securityGroupField.setValue(
            selectedGroupNames.join(', ')
        );

        return true;
    };

    var editSecurityGroups = function (cloudLocation, vpcId, vpcIdFilter, selected) {
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
                storeExtraParams: {
                    platform: !vpcIdFilter ? 'rds' : 'ec2',
                    cloudLocation: cloudLocation,
                    filters: Ext.encode(vpcIdFilter)
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
                    !!vpcIdFilter
                );

                return true;
            }
        });
    };

    return form;
});
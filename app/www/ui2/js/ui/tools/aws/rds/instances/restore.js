Scalr.regPage('Scalr.ui.tools.aws.rds.instances.restore', function (loadParams, moduleParams) {

    var snapshot = moduleParams['snapshot'];

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
            title: 'Location and VPC Settings',
            items: [{
                padding: 5,
                xtype: 'combo',
                fieldLabel: 'Location',
                emptyText: 'Select location',
                name: 'cloudLocation',
                store: {
                    fields: [ 'id', 'name' ],
                    data: moduleParams.locations,
                    proxy: 'object'
                },
                editable: false,
                width: 610,
                queryMode: 'local',
                displayField: 'name',
                valueField: 'id',
                readOnly: true,
                listeners: {
                    change: function (field, value) {
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

                                var vpcField = field.next();
                                var vpcStore = vpcField.getStore();

                                vpcStore.loadData(vpc);

                                vpcField.setValue(
                                    snapshot['VpcId']
                                );
                            }
                        });
                    }
                }
            }, {
                xtype: 'combo',
                name: 'VpcId',
                padding: 5,
                fieldLabel: 'VPC',
                editable: false,
                emptyText: 'No VPC selected. Launch DB instance outside VPC',
                queryMode: 'local',
                width: 610,
                store: {
                    fields: [ 'id', 'name', 'defaultSecurityGroupId', 'defaultSecurityGroupName' ]
                },
                valueField: 'id',
                displayField: 'name',
                listeners: {
                    change: function (field, value) {
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
                xtype: 'combo',
                name: 'DBSubnetGroupName',
                fieldLabel: 'Subnet Group',
                emptyText: 'Select Subnet Group',
                editable: false,
                width: 610,
                queryCaching: false,
                clearDataBeforeQuery: true,
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
                        availabilityZoneStore.clearFilter();

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
            defaults: {
                labelWidth: 200,
                width: 455
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
                    fields: ['id', 'name'],
                    proxy: 'object',
                    data: moduleParams.zones
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
                width: 455
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
                        { id: 'postgres', name: 'PostgreSQL' }
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

                        var licenseModelField = form.down('[name=LicenseModel]');
                        var licenseModelStore = licenseModelField.getStore();
                        licenseModelStore.loadData(me.licenseModels[value]);
                        licenseModelField.setValue(licenseModelStore.first());

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
            name: 'databaseSettings',
            hidden: snapshot['Engine'] === 'mysql',
            items: [{
                fieldLabel: 'Initial Database Name',
                xtype: 'fieldcontainer',
                labelWidth: 200,
                width: 481,
                layout: 'hbox',
                items: [{
                    xtype: 'textfield',
                    flex: 1,
                    name: 'DBName',
                    submitValue: snapshot['Engine'] !== 'mysql'
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: 'If you leave this empty, no initial database will be created'
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
        setValue(loadParams.cloudLocation);

    form.down('[name=StorageType]').getStore().addFilter({
        filterFn: function (record) {
            return record.get('type') !== 'io1' || parseInt(snapshot['AllocatedStorage']) >= 100;
        }
    });

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

    return form;
});
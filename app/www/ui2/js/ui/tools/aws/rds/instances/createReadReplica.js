Scalr.regPage('Scalr.ui.tools.aws.rds.instances.createReadReplica', function (loadParams, moduleParams) {

    var instance = moduleParams['instance'];

    var form = Ext.create('Ext.form.Panel', {
        width: 520,
        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; Create Read Replica',

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
                text: 'Create',
                handler: function() {
                    if (form.getForm().isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save',
                                msg: 'Creating ...'
                            },
                            url: '/tools/aws/rds/instances/xSaveReadReplica',
                            params: {
                                cloudLocation: loadParams.cloudLocation
                            },
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
            hidden: true,
            title: 'Location and VPC Settings',
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
                    proxy: 'object'
                },
                editable: false,
                width: 710,
                queryMode: 'local',
                displayField: 'name',
                valueField: 'id',
                listeners: {
                    change: function (field, value) {
                        /*
                        form.down('[name=instanceSettings]').show().enable();
                        form.down('[name=databaseSettings]').show().enable();
                        form.down('[name=maintenanceSettings]').show().enable();

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
                        */
                    }
                }
            }, {
                submitValue: false,
                xtype: 'combo',
                name: 'VpcId',
                labelWidth: 130,
                padding: 5,
                fieldLabel: 'VPC',
                editable: false,
                emptyText: 'No VPC selected. Launch DB instance outside VPC',
                queryMode: 'local',
                hidden: true,
                width: 710,
                store: {
                    fields: [ 'id', 'name', 'defaultSecurityGroupId' ]
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
                        subnetGroupField.validate();

                        var subnetGroupStore = subnetGroupField.getStore();
                        subnetGroupStore.removeAll();
                        subnetGroupStore.getProxy().params = {
                            cloudLocation: cloudLocation,
                            vpcId: value,
                            extended: 1
                        };
                    }
                }
            }, {
                submitValue: false,
                labelWidth: 130,
                padding: 5,
                xtype: 'combo',
                name: 'DBSubnetGroupName',
                fieldLabel: 'Subnet Group',
                emptyText: 'Select Subnet Group',
                editable: false,
                hidden: true,
                width: 710,
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
            //hidden: true,
            defaults: {
                labelWidth: 200,
                width: 455
            },
            items: [{
                xtype: 'displayfield',
                name: 'SourceDBInstanceIdentifier',
                fieldLabel: 'Source Instance',
                value: loadParams['instanceId'],
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
            }, {
                xtype: 'combo',
                name: 'DBInstanceClass',
                fieldLabel: 'Type',
                store: [
                    'db.t1.micro', 'db.m1.small', 'db.m1.medium',
                    'db.m1.large', 'db.m1.xlarge', 'db.m2.2xlarge ',
                    'db.m2.4xlarge' , 'db.m3.medium ', 'db.m3.large',
                    'db.m3.xlarge', 'db.m3.2xlarge', 'db.r3.large',
                    'db.r3.xlarge', 'db.r3.2xlarge', 'db.r3.4xlarge',
                    'db.r3.8xlarge', 'db.t2.micro', 'db.t2.small',
                    'db.t2.medium'
                ],
                value: 'db.m1.small',
                queryMode: 'local',
                allowBlank: false,
                editable: false
            }, {
                xtype: 'combo',
                name: 'AvailabilityZone',
                fieldLabel: 'Availability Zone',
                emptyText: 'No preference',
                store: {
                    fields: ['id', 'name'],
                    proxy: 'object'
                },
                queryMode: 'local',
                editable: false,
                valueField: 'id',
                displayField: 'name'
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
                labelWidth: 200,
                width: 455,
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
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database',
            name: 'databaseSettings',
            //hidden: true,
            items: [{
                submitValue: false,
                labelWidth: 200,
                width: 455,
                xtype: 'combo',
                name: 'OptionGroupName',
                fieldLabel: 'Option Group',
                store: {
                    fields: [ 'optionGroupName' ],
                    proxy: 'object',
                    data: instance['OptionGroupMembership']
                },
                queryMode: 'local',
                valueField: 'optionGroupName',
                displayField: 'optionGroupName',
                editable: false
            }, {
                labelWidth: 200,
                width: 455,
                xtype: 'numberfield',
                name: 'Port',
                fieldLabel: 'Port',
                minValue: 1150,
                maxValue: 65535,
                allowBlank: false
            }]
        }, {
            xtype: 'fieldset',
            title: 'Maintenance',
            name: 'maintenanceSettings',
            //hidden: true,
            items: [{
                labelWidth: 200,
                xtype: 'checkboxfield',
                fieldLabel: 'Auto Minor Version Upgrade',
                name: 'AutoMinorVersionUpgrade',
                inputValue: true,
                uncheckedValue: false,
                value: false,
                submitValue: true
            }]
        }]
    });

    var applyAvailabilityZone = function (zones) {
        var field = form.down('[name=AvailabilityZone]');

        var store = field.getStore();
        store.clearFilter();
        store.loadData(zones);

        field.setValue(
            instance['AvailabilityZone']
        );

        return true;
    };

    var getAvailabilityZone = function (cloudLocation) {
        Scalr.Request({
            processBox: {
                type: 'action'
            },
            url: '/tools/aws/rds/instances/xGetAvailabilityZones/',
            params: {
                cloudLocation: cloudLocation
            },
            scope: this,
            success: function (response) {
                applyAvailabilityZone(response['zones']);
            },
            failure: function() {
                form.disable();
            }
        });
    };

    form.getForm().setValues(moduleParams.instance);

    form.down('[name=cloudLocation]').
        setValue(loadParams.cloudLocation).
        disable();

    form.down('[name=DBInstanceIdentifier]').validate();

    form.down('[name=StorageType]').getStore().addFilter({
        filterFn: function (record) {
            return record.get('type') !== 'io1' || parseInt(instance['AllocatedStorage']) >= 100;
        }
    });

    getAvailabilityZone(
        loadParams.cloudLocation
    );

    return form;
});

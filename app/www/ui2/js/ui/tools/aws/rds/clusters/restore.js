Scalr.regPage('Scalr.ui.tools.aws.rds.clusters.restore', function (loadParams, moduleParams) {

    var cloudLocation = loadParams.cloudLocation;
    var snapshot = moduleParams.snapshot;

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
        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Clusters &raquo; Restore',
        width: 650,

        listeners: {
            validitychange: function (baseForm, isValid) {
                form.down('#restore')
                    .setDisabled(!isValid);
            }
        },

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            maxWidth: 650,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [{
                xtype: 'button',
                itemId: 'restore',
                text: 'Restore',
                disabled: true,
                handler: function() {
                    if (form.getForm().isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save',
                                msg: 'Restoring ...'
                            },
                            params: {
                                cloudLocation: loadParams.cloudLocation
                            },
                            url: '/tools/aws/rds/clusters/xRestoreCluster',
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
            defaults: {
                labelWidth: 170,
                anchor: '100%'
            },
            items: [{
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
                name: 'VpcId',
                fieldLabel: 'VPC',
                editable: false,
                emptyText: 'No VPC selected. Launch DB Cluster outside VPC',
                allowBlank: false,
                queryMode: 'local',
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
                xtype: 'combo',
                name: 'DBSubnetGroupName',
                fieldLabel: 'Subnet Group',
                emptyText: 'Select Subnet Group',
                editable: false,
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
            }, {
                xtype: 'fieldcontainer',
                fieldLabel: 'Publicly Accessible',
                width: 215,
                anchor: null,
                items: [{
                    xtype: 'checkboxfield',
                    name: 'PublicAccessible',
                    inputValue: true,
                    uncheckedValue: false
                }],
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: {
                        id: 'info',
                        tooltip: 'Select Yes if you want EC2 instances and devices outside of the VPC hosting the DB instance to connect to the DB instance. '
                            + 'If you select No, Amazon RDS will not assign a public IP address to the DB instance, '
                            + 'and no EC2 instance or devices outside of the VPC will be able to connect. If you select Yes, '
                            + 'you must also select one or more VPC security groups that specify which EC2 instances '
                            + 'and devices can connect to the DB instance.'
                    }
                }]
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database Cluster',
            defaults: {
                labelWidth: 170,
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
                name: 'DBClusterIdentifier',
                fieldLabel: 'DB Cluster Identifier',
                emptyText: 'Unique name for this Database Cluster',
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
                    'db.r3.large',
                    'db.r3.xlarge',
                    'db.r3.2xlarge',
                    'db.r3.4xlarge',
                    'db.r3.8xlarge'
                ],
                queryMode: 'local',
                allowBlank: false,
                value: 'db.r3.large',
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
                displayField: 'name'
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database Engine',
            defaults: {
                labelWidth: 170,
                width: 500
            },
            items: [{
                xtype: 'combo',
                name: 'Engine',
                fieldLabel: 'Engine',
                store: {
                    fields: [ 'id', 'name' ],
                    data: [{
                        id: 'aurora',
                        name: 'Amazon Aurora'
                    }]
                },
                valueField: 'id',
                displayField: 'name',
                queryMode: 'local',
                editable: false
            }, {
                xtype: 'numberfield',
                name: 'Port',
                itemId: 'port',
                fieldLabel: 'Port',
                minValue: 1150,
                maxValue: 65535,
                value: 3306,
                allowBlank: false
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database',
            itemId: 'databaseSettings',
            defaults: {
                labelWidth: 170,
                width: 500
            },
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Initial Database Name',
                flex: 1,
                name: 'DBName',
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

    form.down('#port').setValue(3306);

    if (!isCurrentRegionAllowed) {
        form.disable();

        Scalr.message.Warning(
            'You can\'t restore a DB Cluster from this snapshot,'
            + ' because the VPC Policy active in your Environment doesn\'t allow you to launch new DB Clusters in <b>'
            + moduleParams.locations[cloudLocation] + '</b>.'
        );

        return form;
    }

    form.isValid();

    return form;
});

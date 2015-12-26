Scalr.regPage('Scalr.ui.tools.aws.rds.instances.edit', function (loadParams, moduleParams) {

    var cloudLocation = loadParams.cloudLocation;
    var instance = moduleParams.instance;
    var isAurora = instance['Engine'] === 'aurora';
    var requestsCount = 0;

    var securityGroupsPolicy = function (params, isAurora) {

        var policy = {
            enabled: !Ext.isEmpty(params) && !isAurora
        };

        if (policy.enabled) {

            policy.defaultGroups = params.value ? params.value.split(',') : [];

            policy.defaultGroupsList = '<b>' + policy.defaultGroups.join('</b>, <b>') + '</b>';

            policy.allowAddingGroups = !!params['allow_additional_sec_groups'];
            policy.additionalGroupsList = params['additional_sec_groups_list'] ? params['additional_sec_groups_list'].split(',') : [];
            policy.message =
                'A Security Group Policy is active in this Environment' +
                (!Ext.isEmpty(policy.defaultGroups) ? ', and requires that you attach the following Security Groups to your DB instance: ' + policy.defaultGroupsList : '') + '.' +
                (!policy.allowAddingGroups ? '\nYou are not allowed to attach additional Security Groups.' : '');

            policy.tip =
                '<br>Your current Security Group configuration is not compliant with this Policy. To comply, ' +
                'you can edit your Security Groups manually, or select ' +
                '"Modify and automatically comply with Security Groups Policy" when saving your changes. ' +
                'If you don\'t, your Security Groups will remain unchanged.';
        }

        return policy;

    }( Scalr.getGovernance('ec2', 'aws.rds_additional_security_groups'), isAurora );

    var form = Ext.create('Ext.form.Panel', {

        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instances &raquo; Modify',
        width: 700,
        preserveScrollPosition: true,

        getFirstInvalidField: function () {
            return this.down('field{isValid()===false}');
        },

        scrollToField: function (field) {
            field.inputEl.scrollIntoView(this.body.el, false, false);
        },

        showPolicyInfo: function (visible) {
            var me = this;

            me.down('#securityGroupsPolicyInfo').setVisible(visible);

            return me;
        },

        extendModifyButton: function (extended) {
            var me = this;

            me.down('#modify').setVisible(!extended);
            me.down('#extendedModify').setVisible(extended);

            return me;
        },

        prepareMaintenanceParams: function () {
            var me = this;

            me.down('[name=PreferredMaintenanceWindow]').setValue(form.down('#FirstDay').value + ':' + form.down('#fhour').value + ':' + form.down('#fminute').value + '-' + form.down('#LastDay').value + ':' + form.down('#lhour').value + ':' + form.down('#lminute').value);
            me.down('[name=PreferredBackupWindow]').setValue(form.down('#bfhour').value + ':' + form.down('#bfminute').value + '-' + form.down('#blhour').value + ':' + form.down('#blminute').value);

            return me;
        },

        modifyDbInstance: function (params) {
            var me = this,
                baseForm = me.getForm();

            if (!isAurora) {
                me.prepareMaintenanceParams();
            }

            var securityGroups = me.down('[name=VpcSecurityGroups]');

            if (!Ext.isEmpty(params) && !Ext.isEmpty(params.VpcSecurityGroups)) {
                securityGroups.disable();
            }

            if (!form.getForm().isValid()) {
                var invalidField = form.getFirstInvalidField();

                if (!Ext.isEmpty(invalidField)) {
                    form.scrollToField(invalidField);
                    invalidField.focus();
                }

                if (!isAurora) {
                    securityGroups.enable();
                }

                return false;
            }

            var vpcId = instance['VpcId'];
            
            if (!Ext.isEmpty(vpcId)) {
                params['VpcId'] = vpcId;
            }

            Scalr.Request({
                processBox: {
                    type: 'save',
                    msg: 'Modifying ...'
                },
                url: '/tools/aws/rds/instances/xModifyInstance',
                form: baseForm,
                params: params,
                success: function (responseData) {
                    Scalr.event.fireEvent('update', '/tools/aws/rds/instances', 'modify', responseData.instance, responseData.cloudLocation);
                    Scalr.event.fireEvent('close');
                },
                failure: function() {
                    if (!isAurora) {
                        securityGroups.enable();
                    }
                }
            });

            return true;
        },

        acceptSecurityGroupsPolicy: function () {
            var me = this;

            var baseForm = me.getForm();

            if (me.getForm().isValid()) {
                Scalr.Request({
                    processBox: {
                        type: 'save',
                        msg: 'Modifying ...'
                    },
                    url: '/platforms/ec2/xGetDefaultVpcSegurityGroups',
                    params: {
                        cloudLocation: cloudLocation,
                        vpcId: instance['VpcId'],
                        serviceName: 'rds'
                    },
                    success: function (response) {
                        var securityGrops = Ext.Array.map(response.data, function (securityGroup) {
                            return {
                                id: securityGroup.securityGroupId,
                                name: securityGroup.securityGroupName
                            };
                        });

                        me.modifyDbInstance({
                            VpcSecurityGroups: Ext.encode(securityGrops)
                        });
                    }
                });

                return true;
            }
        },

        filterInstancesTypes: function (engine) {
            var me = this;

            var instanceClassField = me.down('[name=DBInstanceClass]');
            var instanceClassStore = instanceClassField.getStore();
            instanceClassStore.clearFilter();

            var allowedTypes = {
                aurora: [ 'r3' ],
                mariadb: [ 't2', 'm3', 'r3' ]
            };

            var engineAllowedTypes = allowedTypes[engine];
            var hasRestrictions = Ext.isDefined(engineAllowedTypes);

            if (hasRestrictions) {
                instanceClassStore.filterBy(function (record) {
                    var instanceType = record.get('field1');

                    return Ext.Array.some(engineAllowedTypes, function (type) {
                        return instanceType.indexOf('.' + type) !== -1;
                    });
                });
            }

            instanceClassField.setValue(!hasRestrictions
                ? 'db.m1.small'
                : instanceClassStore.first()
            );

            return me;
        },

        selectAuroraEngine: function (isSelected) {
            var me = this;

            var engineVersionField = me.down('[name=EngineVersion]');
            var engineVersion = engineVersionField.getValue();

            if (isSelected && !Ext.isEmpty(engineVersion)) {
                engineVersionField.setRawValue('compatible with MySQL ' + engineVersion);
            }

            engineVersionField.setDisabled(isSelected);


            me.down('[name=StorageType]')
                .setValue(!isSelected ? 'gp2' : 'grover')
                .setVisible(!isSelected);


            me.down('[name=AllocatedStorage]')
                .setDisabled(isSelected)
                .setVisible(!isSelected);

            me.down('[name=MultiAZ]')
                .setDisabled(isSelected)
                .setVisible(!isSelected);

            return me;
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
                itemId: 'modify',
                text: 'Modify',
                handler: function () {
                    form.modifyDbInstance({
                        ignoreGovernance: true
                    });
                }
            }, {
                xtype: 'splitbutton',
                itemId: 'extendedModify',
                text: 'Modify',
                hidden: true,
                handler: function () {
                    form.modifyDbInstance({});
                },
                menu: [{
                    text: 'Modify and automatically comply with Security Groups Policy',
                    iconCls: 'x-btn-icon-governance',
                    handler: function () {
                        form.acceptSecurityGroupsPolicy();
                    }
                }, {
                    xtype: 'menuseparator'
                }, {
                    text: 'Modify and keep Security Groups as-is',
                    iconCls: 'x-btn-icon-governance-ignore',
                    handler: function () {
                        form.modifyDbInstance({
                            ignoreGovernance: true
                        });
                    }
                }]
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function () {
                    Scalr.event.fireEvent('close');
                }
            }]
        }],

        items: [{
            xtype: 'displayfield',
            itemId: 'securityGroupsPolicyInfo',
            cls: 'x-form-field-governance x-form-field-governance-fit',
            anchor: '100%',
            hidden: true,
            value: securityGroupsPolicy.enabled
                ? securityGroupsPolicy.message + securityGroupsPolicy.tip
                : ''
        }, {
            xtype: 'fieldset',
            title: 'Location and VPC Settings',
            name: 'locationSettings',
            hidden: true,

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
                    proxy: 'object'
                },
                queryMode: 'local',
                displayField: 'name',
                valueField: 'id',
                readOnly: true
            }, {
                name: 'VpcId',
                fieldLabel: 'VPC',
                emptyText: 'No VPC selected. Launch DB instance outside VPC',
                queryMode: 'local',
                readOnly: true,
                hidden: !instance['VpcId'],
                store: {
                    fields: [ 'id', 'name', 'defaultSecurityGroupId', 'defaultSecurityGroupName' ]
                },
                valueField: 'id',
                displayField: 'name',
                submitValue: false
            }, {
                name: 'DBSubnetGroupName',
                fieldLabel: 'Subnet Group',
                emptyText: 'Select Subnet Group',
                readOnly: true,
                hidden: !instance['DBSubnetGroupName'],
                queryCaching: false,
                clearDataBeforeQuery: true,
                store: {
                    fields: [ 'dBSubnetGroupName', 'dBSubnetGroupDescription', 'subnets' ]
                },
                valueField: 'dBSubnetGroupName',
                displayField: 'dBSubnetGroupName',
                submitValue: false
            }]
        }, {
            xtype: 'fieldset',
            title: !isAurora ? 'Database Instance and Storage' : 'Database Instance',
            name: 'instanceSettings',
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
                maxLength: 63,
                readOnly: true
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
                    uncheckedValue: false
                    /*listeners: {
                        change: function (field, value) {
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
                        }
                    }*/
                }, {
                    xtype: 'displayfield',
                    name: 'mirroring',
                    margin: '0 0 0 6',
                    flex: 1,
                    hidden: true,
                    value: '(Mirroring)'
                }]
            }, {
                xtype: 'textfield',
                name: 'AvailabilityZone',
                fieldLabel: 'Availability Zone',
                editable: false,
                readOnly: true,
                emptyText: 'No preference'
            }, {
                xtype: 'fieldcontainer',
                itemId: 'securityGroups',
                fieldLabel: 'Security Groups',
                layout: 'hbox',
                width: 595,
                hidden: isAurora,
                disabled: isAurora,
                /*
                plugins: [{
                    ptype: 'fieldicons',
                    icons: [{
                        id: 'governance',
                        tooltip: securityGroupsPolicy.enabled
                            ? securityGroupsPolicy.message
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
                            securityGroupsPolicy.message +
                            '" />'
                    ));

                    /*me.getPlugin('fieldicons').
                        toggleIcon('governance', visible);*/

                    return me;
                },
                setButtonDisabled: function (disabled) {
                    var me = this;

                    me.down('button').
                        setTooltip(disabled ? securityGroupsPolicy.message : '');
                        //setDisabled(disabled);

                    return me;
                },
                enablePolicy: function () {
                    var me = this;

                    var isPolicyEnabled = securityGroupsPolicy.enabled;

                    me.
                        setIconVisible(isPolicyEnabled);
                        /*setButtonDisabled(
                            isPolicyEnabled && !securityGroupsPolicy.allowAddingGroups
                        );*/

                    return isPolicyEnabled;
                },
                disablePolicy: function () {
                    var me = this;

                    me.
                        setIconVisible(false).
                        setButtonDisabled(false);

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
                    validator: function (values) {
                        if (!securityGroupsPolicy.enabled) {
                            return true;
                        }

                        if (Ext.isEmpty(values)) {
                            return false;
                        }

                        values = Ext.Array.map(values, function (value) {
                            var dataName = 'data-name=\'';
                            var substring = value.substring(value.indexOf(dataName) + dataName.length);

                            return substring.substring(0, substring.indexOf('\''));
                        });

                        var defaultGroups = securityGroupsPolicy.defaultGroups;
                        var securityGroupsContainer = form.down('#securityGroups');

                        securityGroupsContainer.setIconVisible(true);

                        form.
                            showPolicyInfo(false).
                            extendModifyButton(false);

                        if (!securityGroupsPolicy.allowAddingGroups) {
                            var isGroupsEquals = Ext.Array.equals(
                                Ext.Array.sort(defaultGroups),
                                Ext.Array.sort(values)
                            );

                            if (!isGroupsEquals) {
                                form.
                                    showPolicyInfo(true).
                                    extendModifyButton(true);
                            }
                        } else {
                            var isSecurityGroupMissing = Ext.Array.some(defaultGroups, function (group) {
                                return !Ext.Array.contains(values, group);
                            });

                            if (isSecurityGroupMissing) {
                                form.
                                    showPolicyInfo(true).
                                    extendModifyButton(true);
                            }
                        }

                        return true;

                        /*return !Ext.Array.some(defaultGroups, function (group) {
                            return !Ext.Array.contains(values, group);
                        });*/
                    },
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

                            return Ext.isDefined(group.vpcSecurityGroupId)
                                ? group.vpcSecurityGroupId
                                : group.id;
                        });

                        var names = Ext.Array.map(groups, function (group) {
                            if (group.isModel) {
                                return group.get('name');
                            }

                            return Ext.isDefined(group.vpcSecurityGroupName)
                                    ? group.vpcSecurityGroupName
                                    : group.name;
                        });

                        me.
                            setSecurityGroupsIds(ids).
                            setSecurityGroupsNames(names).
                            setValue(
                                Ext.Array.map(ids, function(id, index) {
                                    var name = names[index];

                                    if (!Ext.isEmpty(id)) {
                                        return '<span data-id=\'' + id + '\' data-name=\'' + name +
                                            '\' class=\'scalr-ui-rds-tagfield-sg-name\' style=\'cursor:pointer\'>' +
                                            name + '</span>';
                                    }

                                    var warningTooltip = 'A Security Group Policy is active in this Environment,\n' +
                                        'and requires that you attach <b>' + name + '</b> Security Group to your DB instance.\n' +
                                        'But <b>' + name + '</b> does not exist in current VPC.';

                                    return '<div data-name=\'' + name + '\' data-qtip=\'' + warningTooltip + '\'' + ' >' +
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
                        var dBSubnetGroup = instance['VpcSecurityGroups'];
                        var vpcId = dBSubnetGroup ? instance['VpcId'] : null;
                        var isVpcDefined = !!vpcId;

                        var field = !isVpcDefined
                            ? form.down('[name=DBSecurityGroups]')
                            : form.down('[name=VpcSecurityGroupIds]');

                        var excludeGroups = isVpcDefined && securityGroupsPolicy.enabled
                            ? field.getDefaultGroups()
                            : [];

                        editSecurityGroups(
                            cloudLocation,
                            vpcId,
                            !isVpcDefined ? field.getValue() : field.getSecurityGroups(),
                            excludeGroups
                        );
                    }
                }]
            }, {
                xtype: 'numberfield',
                name: 'AllocatedStorage',
                fieldLabel: 'Allocated Storage',
                allowBlank: false,
                minValue: 5,
                maxValue: 3072
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
                submitValue: false,
                hidden: true,
                minValue: 1000,
                maxValue: 30000,
                step: 1000,
                validator: function (value) {
                    return value % 1000
                        ? 'IOPS value must be an increment of 1000'
                        : true;
                }
            }]
        },{
            xtype: 'displayfield',
            itemId: 'noFarmIdInfo',
            hidden: true,
            anchor: '100%',
            cls: 'x-form-field-info',
            value: 'This database instance is not associated with a Farm.'
        },{
            xtype: 'fieldset',
            title: 'Associate this database instance with a Farm',
            itemId: 'associateFarmId',
            collapsible: true,
            collapsed: true,
            checkboxToggle: true,
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
                }
            },
            items: [{
				xtype: 'textfield',
				fieldLabel: 'Farm',
                disabled: true,
				name: 'farmName',
                listeners: {
                    change: function(comp, value) {
                        if (value) {
                            this.up().expand();
                        }
                    }
                }
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database Engine',
            name: 'engineSettings',
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
                    ['postgres', 'PostgreSQL'],
                    ['aurora', 'Amazon Aurora'],
                    ['mariadb', 'MariaDB']
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
                    'postgres': { minValue: 5, maxValue: 3072 },
                    'aurora': { minValue: 100, maxValue: 3072 },
                    'mariadb': { minValue: 5, maxValue: 6144 }
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
                    'postgres': [{ licenseModel: 'postgresql-license' }],
                    'aurora': [{ licenseModel: 'general-public-license' }],
                    'mariadb': [{ licenseModel: 'general-public-license' }]
                },
                queryMode: 'local',
                editable: false,
                readOnly: true,
                submitValue: false,
                isAurora: function (engine) {
                    return engine === 'aurora';
                },
                listeners: {
                    change: function (me, value) {
                        setMultiAzStatus(value);

                        deprecateMultiAz(
                            form.down('[name=cloudLocation]').getValue(),
                            value
                        );

                        form.filterInstancesTypes(value);

                        Scalr.Request({
                            processBox: {
                                type: 'load'
                            },
                            url: '/tools/aws/rds/instances/xGetEngineVersions',
                            params: {
                                cloudLocation: cloudLocation,
                                engine: value
                            },
                            success: function (response) {
                                var engineVersionField = me.next();

                                var engineVersionStore = engineVersionField.getStore();
                                engineVersionStore.removeAll();

                                var engineVersions = response['engineVersions'];

                                if (engineVersions) {
                                    engineVersionStore.loadData(engineVersions);
                                    engineVersionField.setValue(
                                            instance['EngineVersion']
                                    );
                                }

                                var allocatedStorageField = form.down('[name=AllocatedStorage]');
                                Ext.apply(allocatedStorageField, me.storageValues[value]);

                                if (me.isAurora(value)) {
                                    form.selectAuroraEngine(true);
                                }
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
                                    requestsCount++;

                                    var optionGroupField = form.down('[name=OptionGroupName]');
                                    optionGroupField.reset();

                                    var optionGroupStore = optionGroupField.getStore();
                                    optionGroupStore.removeAll();
                                    optionGroupStore.loadData(response['optionGroups']);

                                    optionGroupField.setValue(requestsCount < 2
                                        ? instance['OptionGroupName']
                                        : response['defaultOptionGroupName']
                                    );
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
                                    requestsCount++;

                                    var parameterGroupField = form.down('[name=DBParameterGroup]');

                                    var parameterGroupStore = parameterGroupField.getStore();
                                    parameterGroupStore.removeAll();
                                    parameterGroupStore.loadData(response['groups']);

                                    parameterGroupField.setValue(requestsCount < 2
                                        ? instance['DBParameterGroup']
                                        : response['default']
                                    );
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
                xtype: 'checkboxfield',
                fieldLabel: 'Allow Major Version Upgrade',
                name: 'AllowMajorVersionUpgrade',
                inputValue: true,
                uncheckedValue: false,
                value: true,
                hidden: true,
                submitValue: true
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
                value: 'general-public-license',
                readOnly: true,
                hidden: isAurora,
                disabled: isAurora
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
                editable: false,
                hidden: isAurora,
                disabled: isAurora
            }, {
                xtype: 'textfield',
                name: 'Port',
                fieldLabel: 'Port',
                allowBlank: false,
                readOnly: true,
                submitValue: false
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database',
            name: 'databaseSettings',
            hidden: isAurora,
            disabled: isAurora,
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
                xtype: 'textfield',
                name: 'MasterUsername',
                fieldLabel: 'Master Username',
                allowBlank: false,
                readOnly: true,
                submitValue: false
            }, {
                xtype: 'textfield',
                name: 'MasterUserPassword',
                fieldLabel: 'Master Password',
                emptyText: '******',
                regex: /^[a-z0-9!#$%&'()*+,.:;<=>?\[\]\\^_`{|}~-]*$/i,
                invalidText: 'Master Password can be any printable ASCII character except "/", """, or "@"',
                minLength: 8,
                minLengthText: 'Master Password must be a minimum of 8 characters'
            }]
        }, {
            xtype: 'fieldset',
            title: !isAurora ? 'Maintenance Windows and Backups' : '',
            name: 'maintenanceWindowSettings',
            fieldDefaults: {
                submitValue: false
            },
            items: [{
                xtype: 'hiddenfield',
                name: 'PreferredMaintenanceWindow',
                submitValue: true,
                disabled: isAurora
            }, {
                xtype: 'hiddenfield',
                name: 'PreferredBackupWindow',
                submitValue: true,
                disabled: isAurora
            }, {
                labelWidth: 200,
                xtype: 'checkboxfield',
                fieldLabel: 'Apply Immediately',
                name: 'ApplyImmediately',
                inputValue: true,
                uncheckedValue: false,
                submitValue: true
            }, {
                labelWidth: 200,
                xtype: 'checkboxfield',
                fieldLabel: 'Auto Minor Version Upgrade',
                name: 'AutoMinorVersionUpgrade',
                inputValue: true,
                uncheckedValue: false,
                submitValue: true
            }, {
                xtype: 'fieldcontainer',
                layout: {
                    type: 'hbox'
                },
                defaults: {
                    margin: '0 3 0 0'
                },
                hidden: isAurora,
                disabled: isAurora,
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
                    value: instance['PreferredMaintenanceWindow'].substr(0, 3)
                },{
                    xtype: 'displayfield',
                    value: ' : '
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'fhour',
                    value: instance['PreferredMaintenanceWindow'].substr(4, 2)
                },{
                    xtype: 'displayfield',
                    value: ' : '
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'fminute',
                    value: instance['PreferredMaintenanceWindow'].substr(7, 2)
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
                    value: instance['PreferredMaintenanceWindow'].substr(10, 3)
                },{
                    xtype: 'displayfield',
                    value: ' : '
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'lhour',
                    value: instance['PreferredMaintenanceWindow'].substr(14, 2)
                },{
                    xtype: 'displayfield',
                    value: ' : '
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'lminute',
                    value: instance['PreferredMaintenanceWindow'].substr(17, 2)
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
                defaults: {
                    isFormFields: false
                },
                hidden: isAurora,
                disabled: isAurora,
                items: [{
                    labelWidth: 200,
                    width: 240,
                    xtype: 'textfield',
                    itemId: 'bfhour',
                    fieldLabel: 'Preferred Backup Window',
                    value: instance['PreferredBackupWindow'].substr(0, 2)
                },{
                    xtype: 'displayfield',
                    value: ' : ',
                    margin: '0 0 0 3'
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'bfminute',
                    value: instance['PreferredBackupWindow'].substr(3, 2),
                    margin: '0 0 0 3'
                },{
                    xtype: 'displayfield',
                    value: ' - ',
                    margin: '0 0 0 3'
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'blhour',
                    value: instance['PreferredBackupWindow'].substr(6, 2),
                    margin: '0 0 0 3'
                },{
                    xtype: 'displayfield',
                    value: ' : ',
                    margin: '0 0 0 3'
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'blminute',
                    value: instance['PreferredBackupWindow'].substr(9, 2),
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
                hidden: isAurora,
                disabled: isAurora,
                items: [{
                    labelWidth: 200,
                    width: 280,
                    xtype: 'numberfield',
                    name: 'BackupRetentionPeriod',
                    fieldLabel: 'Backup Retention Period',
                    value: 1,
                    minValue: 0,
                    maxValue: 35,
                    submitValue: true
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

        form.down('[name=MultiAZ]').
            setDisabled(isMultiAzDeprecated);

        if (isMultiAzDeprecated) {
            multiAzField.setValue(false);
        }

        return true;
    };

    var updateSecurityGroups = function (selectedGroups, isVpcDefined) {
        if (isVpcDefined) {
            form.down('[name=VpcSecurityGroupIds]').
                setSecurityGroups(selectedGroups);
            return true;
        }

        form.down('[name=DBSecurityGroups]').setValue(
            Ext.Array.map(selectedGroups, function (group) {
                return group.isModel
                    ? group.get('name')
                    : group.name;
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
                record.isModel
                    ? record.get('name')
                    : record['vpcSecurityGroupName']
            );

            if (isVpcDefined) {
                selectedGroupIds.push(
                    record.isModel
                        ? record.get('id')
                        : record['vpcSecurityGroupId']
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
                limit: isVpcDefined ? 5 : 25,
                minHeight: 200,
                selection: selected,
                defaultVpcGroups: excludeGroups,
                governanceWarning: isVpcDefined && securityGroupsPolicy.enabled && !securityGroupsPolicy.allowAddingGroups
                    ? securityGroupsPolicy.message
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

    form.getForm().setValues(instance);

    form.down('[name=cloudLocation]').
        setValue(cloudLocation);

    var fieldsetAssociateFarmId = form.down('#associateFarmId');
    if (instance['farmName']) {
        fieldsetAssociateFarmId.expand();
        fieldsetAssociateFarmId.checkboxCmp.setDisabled(true);
    } else {
        fieldsetAssociateFarmId.hide();
        form.down('#noFarmIdInfo').show();
    }

    if (instance['VpcId']) {
        updateSecurityGroups(
            instance['VpcSecurityGroups'],
            true
        );

        var DBSecurityGroups = form.down('[name=DBSecurityGroups]');
        DBSecurityGroups.hide();
        DBSecurityGroups.submitValue = false;

        form.down('[name=VpcSecurityGroupIds]').show();

        form.down('#securityGroups').enablePolicy();

        form.down('[name=VpcSecurityGroups]').submitValue = true;

        return form;
    }

    form.down('[name=DBSecurityGroups]')
        .on('afterrender', function (field) {
            field.reset();
            field.setValue(
                instance['DBSecurityGroups'].join(', ')
            );
        });

    return form;

});

Ext.define('Scalr.rds.instance.form.SecurityGroupsFieldContainer', {
    extend: 'Ext.form.FieldContainer',

    alias: 'widget.rdssecuritygroups',

    layout: 'hbox',

    config: {
        cloudLocation: null,
        vpcId: null,
        accountId: null,
        remoteAddress: null
    },

    initComponent: function () {
        var me = this;

        me.callParent();

        me.down('button').setHandler(me.buttonHandler, me);

        me.applyPolicy(Scalr.getGovernance('ec2', 'aws.rds_additional_security_groups'));

        return true;
    },

    initEvents: function () {
        var me = this;

        me.down('#vpcSecurityGroupIds').on({
            show: me.enablePolicy,
            hide: me.disablePolicy,
            scope: me
        });

        //Scalr.event.on('update', me.updateGovernanceHandler, me);

        return me.callParent();
    },

    onBoxReady: function () {
        var me = this;

        var cloudLocation = me.cloudLocation;
        var policy = me.policy;
        var vpcSecurityGroupsField = me.down('#vpcSecurityGroupIds');

        vpcSecurityGroupsField.defaultGroups = policy.enabled ? policy.defaultGroups : [];

        vpcSecurityGroupsField.getEl().on('click', function (event, target) {
            target = Ext.get(target);

            if (target.hasCls('scalr-ui-rds-tagfield-sg-name')) {
                var link = '#/security/groups/' + target.getAttribute('data-id') + '/edit?' + Ext.Object.toQueryString({
                    platform: 'ec2',
                    cloudLocation: cloudLocation
                });

                Scalr.event.fireEvent('redirect', link);
            }
        });

        var dbSecurityGroupsField = me.down('#dbSecurityGroups');

        dbSecurityGroupsField.getEl().on('click', function (event, target) {
            target = Ext.get(target);

            if (target.hasCls('x-tagfield-item-text')) {
                var link = '#/tools/aws/rds/sg/edit?' + Ext.Object.toQueryString({
                    dbSgName: target.getHtml(),
                    cloudLocation: cloudLocation
                });

                Scalr.event.fireEvent('redirect', link);
            }
        });

        return me.callParent(arguments);
    },

    buttonHandler: function () {
        var me = this;

        var vpcId = me.vpcId;
        var isVpcDefined = !!vpcId;
        var field = me.down(!isVpcDefined ? '#dbSecurityGroups' : '#vpcSecurityGroupIds');
        var excludeGroups = isVpcDefined && me.policy.enabled ? field.getDefaultGroups() : [];

        me.editSecurityGroups(
            me.cloudLocation,
            vpcId,
            !isVpcDefined ? field.getValue() : field.getSecurityGroups(),
            excludeGroups
        );
    },

    onFormVpcChange: function (field, vpcId) {
        var me = this;

        me.vpcId = vpcId;

        var isVpcFieldEmpty = Ext.isEmpty(vpcId) || vpcId === 0;

        var dbSecurityGroupsField = me.down('#dbSecurityGroups');
        dbSecurityGroupsField.setVisible(isVpcFieldEmpty);
        dbSecurityGroupsField.submitValue = isVpcFieldEmpty;

        var vpcSecurityGroupIds = me.down('#vpcSecurityGroupIds');
        vpcSecurityGroupIds.setVisible(!isVpcFieldEmpty);

        me.down('#vpcSecurityGroups').submitValue = !isVpcFieldEmpty;

        var record = field.getStore().getById(vpcId);

        if (!Ext.isEmpty(record)) {
            var defaultSecurityGroups = !me.isModify ? record.get('defaultSecurityGroups') : me.vpcSecurityGroups;

            if (!Ext.isEmpty(defaultSecurityGroups)) {
                vpcSecurityGroupIds.setSecurityGroups(defaultSecurityGroups);
            }
        }

        return true;
    },

    applyPolicy: function (policyConfig) {
        var me = this;

        var policy = {
            enabled: !Ext.isEmpty(policyConfig)
        };

        if (policy.enabled) {
            policy.defaultGroups = policyConfig.value ? policyConfig.value.split(',') : [];
            policy.defaultGroupsList = '<b>' + policy.defaultGroups.join('</b>, <b>') + '</b>';
            policy.additionalGroupsList = policyConfig['additional_sec_groups_list'] ? policyConfig['additional_sec_groups_list'].split(',') : [];

            Ext.apply(policy, {
                allowAddingGroups: !!policyConfig['allow_additional_sec_groups'],

                enabledPolicyMessage: 'A Security Group Policy is active in this Environment' +
                    (!Ext.isEmpty(policy.defaultGroups) ? ', and requires that you attach the following Security Groups to your DB instance: ' + policy.defaultGroupsList : '') + '.' +
                    (!policy.allowAddingGroups ? '\nYou are not allowed to attach additional Security Groups.' : ''),

                requiredGroupsMessage: 'A Security Group Policy is active in this Environment' +
                    (!Ext.isEmpty(policy.defaultGroups) ? ', and restricts you to the following Security Groups: ' +
                    policy.defaultGroupsList : '') + '.',

                tip: '<br>Your current Security Group configuration is not compliant with this Policy. To comply, ' +
                    'you can edit your Security Groups manually, or select ' +
                    '"Modify and automatically comply with Security Groups Policy" when saving your changes. ' +
                    'If you don\'t, your Security Groups will remain unchanged.'
            });
        }

        me.policy = policy;

        return policy.enabled;
    },

    updateGovernanceHandler: function (type) {
        if (type === '/core/governance') {
            this.applyPolicy(Scalr.getGovernance('ec2', 'aws.rds_additional_security_groups'));

            return true;
        }

        return false;
    },

    setIconVisible: function (visible) {
        var me = this;

        me.setFieldLabel('Security Groups' + (!visible ? '' : '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL +
            '" class="x-icon-governance" style="margin-top:-4px" data-qtip="' +
            me.policy.enabledPolicyMessage +
            '" />'
        ));

        return me;
    },

    setButtonDisabled: function (disabled) {
        var me = this;

        me.down('button')
            .setTooltip(disabled ? me.policy.requiredGroupsMessage : '')
            .setDisabled(disabled);

        return me;
    },

    enablePolicy: function () {
        var me = this;

        var isPolicyEnabled = me.policy.enabled;

        me.setIconVisible(isPolicyEnabled);

        return isPolicyEnabled;
    },

    disablePolicy: function () {
        var me = this;

        me.setIconVisible(false);

        return me;
    },

    updateSecurityGroups: function (securityGroups, isVpcDefined) {
        var me = this;

        if (isVpcDefined) {
            me.down('#vpcSecurityGroupIds').setSecurityGroups(securityGroups);

            return me;
        }

        me.down('#dbSecurityGroups').setValue(
            Ext.Array.map(securityGroups, function (securityGroup) {
                return securityGroup.isModel ? securityGroup.get('name') : securityGroup.name;
            })
        );

        return me;
    },

    editSecurityGroups: function (cloudLocation, vpcId, selected, excludeGroups) {
        var me = this;

        var isVpcDefined = !!vpcId;
        var policy = me.policy;
        var filter = !isVpcDefined ? null : Ext.encode({
            vpcId: vpcId,
            considerGovernance: policy.enabled,
            serviceName: 'rds'
        });

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
                governanceWarning: isVpcDefined && policy.enabled && !policy.allowAddingGroups
                    ? policy.requiredGroupsMessage
                    : null,
                disableAddButton: policy.enabled && (!policy.allowAddingGroups || !Ext.isEmpty(policy.additionalGroupsList)),
                storeExtraParams: {
                    platform: !isVpcDefined ? 'rds' : 'ec2',
                    cloudLocation: cloudLocation,
                    filters: filter
                },
                accountId: me.accountId,
                remoteAddress: me.remoteAddress,
                vpc: {
                    id: vpcId,
                    region: cloudLocation
                }
            }],
            disabled: true,
            closeOnSuccess: true,
            scope: this,
            success: function (formValues, securityGroupForm) {
                me.updateSecurityGroups(
                    securityGroupForm.down('rdssgmultiselect').selection,
                    isVpcDefined
                );

                return true;
            }
        });
    },

    items: [{
        itemId: 'vpcSecurityGroups',
        name: 'VpcSecurityGroups',
        xtype: 'hiddenfield',
        submitValue: false,
        getSubmitValue: function () {
            var me = this;

            var vpcSecurityGroupsField = me.next();
            var securityGroupIds = vpcSecurityGroupsField.getSecurityGroupsIds();
            var securityGroupNames = vpcSecurityGroupsField.getSecurityGroupsNames();

            return Ext.encode(
                Ext.Array.map(securityGroupIds, function(id, index) {
                    return {
                        id: id,
                        name: securityGroupNames[index]
                    };
                })
            );
        }
    }, {
        itemId: 'vpcSecurityGroupIds',
        name: 'VpcSecurityGroupIds',
        xtype: 'taglistfield',
        cls: 'x-tagfield-force-item-hover',
        hidden: true,
        submitValue: false,
        readOnly: true,
        scrollable: true,
        validator: function (values) {
            var me = this;

            var container = me.up('rdssecuritygroups');
            var policy = container.policy;

            if (!container.isModify || !policy.enabled) {
                return true;
            }

            if (Ext.isEmpty(values)) {
                return false;
            }

            container.setIconVisible(true);

            values = Ext.Array.map(values, function (value) {
                var attributeNameString = 'data-name=\'';
                var substring = value.substring(value.indexOf(attributeNameString) + attributeNameString.length);

                return substring.substring(0, substring.indexOf('\''));
            });

            var defaultGroups = policy.defaultGroups;

            container.fireEvent('policyvalidationchange', true);

            if (!policy.allowAddingGroups) {
                var isGroupsEquals = Ext.Array.equals(Ext.Array.sort(defaultGroups), Ext.Array.sort(values));

                if (!isGroupsEquals) {
                    container.fireEvent('policyvalidationchange', false, policy.enabledPolicyMessage + policy.tip);
                }
            } else {
                var isGroupMissing = Ext.Array.some(defaultGroups, function (group) {
                    return !Ext.Array.contains(values, group);
                });

                if (isGroupMissing) {
                    container.fireEvent('policyvalidationchange', false, policy.enabledPolicyMessage + policy.tip);
                }
            }

            return true;
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

                return group.vpcSecurityGroupId || group.securityGroupId || group.id;
            });

            var names = Ext.Array.map(groups, function(group) {
                if (group.isModel) {
                    return group.get('name');
                }

                return group.vpcSecurityGroupName || group.securityGroupName || group.name;
            });

            me
                .setSecurityGroupsIds(ids)
                .setSecurityGroupsNames(names)
                .setValue(
                    Ext.Array.map(ids, function (id, index) {
                        var name = names[index] || '';

                        if (!Ext.isEmpty(id)) {
                            return '<span data-name=\'' + name + '\' data-id=\'' + id +
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

                        return '<div data-name=\'' + name + '\' data-qtip=\'' + warningTooltip + '\'' + ' >' +
                            '<img src=\'' + Ext.BLANK_IMAGE_URL +
                            '\' class=\'x-icon-warning\' style=\'vertical-align:middle;margin-right:6px\' />' +
                            name + '</div>';
                    })
            );

            return me;
        }
    }, {
        itemId: 'dbSecurityGroups',
        name: 'DBSecurityGroups',
        xtype: 'taglistfield',
        cls: 'x-tagfield-force-item-hover',
        readOnly: true,
        scrollable: true,
        getSubmitValue: function () {
            return Ext.encode(this.getValue());
        }
    }, {
        xtype: 'button',
        text: 'Change',
        width: 80,
        margin: '0 0 0 12'
    }]
});


Ext.define('Scalr.rds.instance.form.Panel', {
    extend: 'Ext.form.Panel',

    alias: 'widget.rdsinstanceform',

    setTitle: function (title) {
        var me = this;

        title = 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instances &raquo; ' + title;

        return me.callParent(arguments);
    },

    initComponent: function () {
        var me = this;

        me.callParent();

        me.down('#cloudLocation').getStore().loadRawData(me.locations);

        me.down('#farmId').getStore().loadData(me.farms);

        var isModify = me.isModify = !Ext.isEmpty(me.instanceData);
        var actionName = isModify ? 'Modify' : 'Launch';

        me.setTitle(actionName);

        var securityGroupsContainer = me.down('#securityGroupsContainer');
        securityGroupsContainer.isModify = isModify;
        securityGroupsContainer.accountId = me.accountId;
        securityGroupsContainer.remoteAddress = me.remoteAddress;
        securityGroupsContainer.vpcSecurityGroups = isModify ? me.instanceData.VpcSecurityGroups : null;

        if (isModify) {
            me.down('#launch').hide();
            me.down('#modify').show();

            me.down('#applyImmediately').enable().show();
        }

        me.initGovernance();

        return true;
    },

    initEvents: function () {
        var me = this;

        var vpcField = me.down('#vpc');
        vpcField.on('change', me.onVpcChange, me);
        vpcField.getStore().on('load', me.onVpcStoreLoad, me);

        var securityGroupsContainer = me.down('#securityGroupsContainer');
        vpcField.on('change', securityGroupsContainer.onFormVpcChange, securityGroupsContainer);

        var subnetGroupField = me.down('#subnetGroup');
        subnetGroupField.on('change', me.onSubnetGroupChange, me);
        subnetGroupField.getStore().on('load', me.onSubnetGroupStoreLoad, me);

        var engineVersionField = me.down('#engineVersion');
        engineVersionField.on('change', me.onEngineVersionChange, me);
        engineVersionField.getStore().on('load', me.onEngineVersionStoreLoad, me);

        me.down('#cloudLocation').on('change', me.onCloudLocationChange, me);
        me.down('#multiAz').on('change', me.onMultiAzChange, me);
        me.down('#storageType').on('change', me.onStorageTypeChange, me);
        me.down('#engine').on('change', me.onEngineChange, me);
        me.down('#optionGroup').getStore().on('load', me.onOptionGroupStoreLoad, me);
        me.down('#parameterGroup').getStore().on('load', me.onParameterGroupStoreLoad, me);

        securityGroupsContainer.on('policyvalidationchange', me.hideSecurityGroupsPolicyWarning, me);

        if (!me.isModify) {
            me.down('#instanceType').on('change', me.onInstanceTypeChange, me);
            me.down('#storageEncrypted').on('change', me.onStorageEncryptedChange, me);
            me.down('#kmsKey').on('change', me.onKmsKeyChange, me);
        }

        //Scalr.event.on('update', me.updateGovernanceHandler, me);

        return me.callParent();
    },

    onBoxReady: function () {
        var me = this;

        if (me.isModify) {
            me.initInstanceData(me.instanceData);
        }

        return me.callParent();
    },

    initInstanceData: function (data) {
        var me = this;

        me.getForm().setValues(data);

        me.down('#cloudLocation').setValue(me.cloudLocation);

        var isAurora = data.Engine === 'aurora';

        var hiddenItems = {
            'locationSettings': true,
            'availabilityZone': true,
            'storageEncryptedContainer': true,
            'licenseModel': true,
            'port': true,
            'dbName': true,
            'masterUsername': true,
            'securityGroupsContainer': isAurora,
            'optionGroup': isAurora,
            'databaseSettings': isAurora,
            'backupRetentionPeriodContainer': isAurora,
            'backupWindowContainer': isAurora,
            'maintenanceWindowContainer': isAurora
        };

        Ext.Object.each(hiddenItems, function (id, hidden) {
            me.down('#' + id).setVisible(!hidden);
        });

        var readOnlyItems = ['instanceId', 'engine', 'farmId'];

        Ext.Array.each(readOnlyItems, function (id) {
            var component = me.down('#' + id);
            component.setReadOnly(true);

            if (id === 'farmId') {
                component.getPlugin('fieldicons').hideIcons();
            }
        });

        me.down('#allowMajorVersionUpgrade').enable();

        var masterUserPasswordField = me.down('#masterUserPassword');
        masterUserPasswordField.allowBlank = true;
        masterUserPasswordField.emptyText = '******';
        masterUserPasswordField.applyEmptyText();

        var associateFarmIdFieldSet = me.down('#associateFarmId');

        if (!Ext.isEmpty(data.farmName)) {
            associateFarmIdFieldSet.expand();
            associateFarmIdFieldSet.checkboxCmp.setDisabled(true);
        } else {
            associateFarmIdFieldSet.hide();
            me.down('#noFarmIdInfo').show();
        }

        return true;
    },

    initGovernance: function () {
        var me = this;

        var governance = me.governance = {};

        if (!me.isModify) {
            me.applyVpcPolicy(Scalr.getGovernance('ec2', 'aws.vpc'));

            var rdsGovernance = Scalr.getGovernance('ec2', 'aws.rds');

            if (!Ext.isEmpty(rdsGovernance) && rdsGovernance.db_instance_requires_farm_association === 1) {
                var associateFarmIdFieldSet = me.down('#associateFarmId');

                associateFarmIdFieldSet.expand();
                associateFarmIdFieldSet.down('#farmId').enable();
                associateFarmIdFieldSet.checkboxCmp.setDisabled(true);
            }

            var storagePolicy = Scalr.getGovernance('ec2', 'aws.rds_storage');
            var requireEncryption = !Ext.isEmpty(storagePolicy) ? storagePolicy.require_encryption : false;
            var storageEncryptedField = me.down('#storageEncrypted');

            if (requireEncryption) {
                storageEncryptedField.toggleIcon('governance', true);
                storageEncryptedField.setReadOnly(true);
                storageEncryptedField.setValue(true);

                me.down('#kmsKey').enable().show();
            } else {
                storageEncryptedField.disable();
            }
        } else {
            governance.vpc = {
                enabled: false
            };
        }

        return me.governance;
    },

    getPolicy: function (policyName) {
        return this.governance[policyName];
    },

    applyVpcPolicy: function (policyConfig) {
        var me = this;

        var policy = me.governance.vpc = {
            enabled: !Ext.isEmpty(policyConfig)
        };

        if (policy.enabled) {
            Ext.apply(policy, {
                launchWithVpcOnly: !!policyConfig.value,
                regions: Ext.Object.getKeys(policyConfig.regions),
                vpcs: policyConfig.regions,
                subnets: policyConfig.ids
            });

            me.enableLocationFilters(policy);

            var iconHtml = '<img src="' + Ext.BLANK_IMAGE_URL + '" style="margin-left: 6px;" data-qtip="' +
                Ext.String.htmlEncode(Scalr.strings.rdsDbInstanceVpcEnforced) + '" class="x-icon-governance" />';

            me.down('#locationSettings').setTitle('Location and VPC Settings' + iconHtml);

            me.down('#subnetIds').enable();
        }

        return policy.enabled;
    },

    hideSecurityGroupsPolicyWarning: function (hidden, warningText) {
        var me = this;

        me.down('#modify').setVisible(hidden);
        me.down('#extendedModify').setVisible(!hidden);

        me.down('#securityGroupsPolicyWarning').setValue(warningText).setVisible(!hidden);

        return true;
    },

    enableLocationFilters: function (policy) {
        var me = this;

        var cloudLocationField = me.down('#cloudLocation');

        cloudLocationField.getStore().addFilter({
            id: 'governancePolicyFilter',
            filterFn: function (record) {
                return policy.launchWithVpcOnly ? Ext.Array.contains(policy.regions, record.get('id')) : true;
            }
        });

        var vpcField = me.down('#vpc');

        vpcField.getStore().addFilter({
            id: 'governancePolicyFilter',
            filterFn: function (record) {
                var allowedVpcs = policy.vpcs[cloudLocationField.getValue()].ids;
                var vpcId = record.get('id');

                return !Ext.isEmpty(allowedVpcs)
                    ? (!policy.launchWithVpcOnly ? vpcId === 0 : false) || Ext.Array.contains(allowedVpcs, vpcId)
                    : (policy.launchWithVpcOnly ? vpcId !== 0 : true);
            }
        });

        me.down('#subnetGroup').getStore().addFilter({
            id: 'governancePolicyFilter',
            filterFn: function (record) {
                var subnetsPolicy = policy.subnets[vpcField.getValue()];

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
        });
    },

    updateGovernanceHandler: function (type) {
        if (type === '/core/governance') {
            this.applyVpcPolicy(Scalr.getGovernance('ec2', 'aws.vpc'));

            return true;
        }

        return false;
    },

    onCloudLocationChange: function (field, cloudLocation) {
        var me = this;

        me.cloudLocation = me.down('#securityGroupsContainer').cloudLocation = cloudLocation;

        me.down('#launch').enable();

        Ext.Array.each(me.query('fieldset'), function (fieldset) {
            fieldset.show().enable();
        });

        me.filterEngines(cloudLocation).applyKmsKeysData(cloudLocation);

        var vpcField = field.next();
        vpcField.enable().show().reset();

        var vpcStore = vpcField.getStore();
        vpcStore.removeAll();

        vpcStore.getProxy().params = {
            cloudLocation: cloudLocation,
            serviceName: 'rds'
        };

        var vpcPolicy = me.getPolicy('vpc');

        if (!vpcPolicy.enabled || !Ext.isEmpty(vpcPolicy.vpcs[cloudLocation])) {
            vpcStore.load();
        }

        me.applyCloudLocationParameters(cloudLocation);

        return true;
    },

    onVpcStoreLoad: function (store, records, successful) {
        var me = this;

        if (successful && !Ext.isEmpty(records)) {
            var defaultValue;

            if (me.isModify) {
                defaultValue = me.instanceData.VpcId;
            } else {
                defaultValue = store.getProxy().getReader().defaultVpc;

                var vpcPolicy = me.getPolicy('vpc');

                if (!Ext.isEmpty(defaultValue) && vpcPolicy.enabled) {
                    var allowedVpcs = vpcPolicy.vpcs[value].ids;

                    defaultValue = Ext.isArray(allowedVpcs) && !Ext.Array.contains(allowedVpcs, defaultValue) ? null : defaultValue;
                }

                if (Ext.isEmpty(defaultValue)) {
                    defaultValue = vpcPolicy.enabled && vpcPolicy.launchWithVpcOnly ? store.first() : store.insert(0, {
                        id: 0,
                        name: ''
                    });
                }
            }

            me.down('#vpc').setValue(defaultValue);

            return true;
        }

        return false;
    },

    onVpcChange: function (field, vpcId) {
        var me = this;

        me.vpcId = vpcId;

        var isVpcFieldEmpty = Ext.isEmpty(vpcId) || vpcId === 0;

        me.down('#publiclyAccessible').setDisabled(isVpcFieldEmpty).setVisible(!isVpcFieldEmpty);

        var subnetGroupField = field.next();
        subnetGroupField.setVisible(!isVpcFieldEmpty).setDisabled(isVpcFieldEmpty).reset();
        subnetGroupField.allowBlank = isVpcFieldEmpty;

        var cloudLocation = me.cloudLocation;

        subnetGroupField.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString({
            cloudLocation: cloudLocation,
            vpcId: vpcId
        });

        var subnetGroupStore = subnetGroupField.getStore();
        subnetGroupStore.removeAll();

        subnetGroupStore.getProxy().params = {
            cloudLocation: cloudLocation,
            vpcId: vpcId,
            extended: 1
        };

        subnetGroupStore.load();

        return true;
    },

    onSubnetGroupStoreLoad: function (store, records, successful) {
        var me = this;

        if (successful && !Ext.isEmpty(records)) {
            me.down('#subnetGroup').setValue(records[0]).validate();
            return true;
        }

        return false;
    },

    onSubnetGroupChange: function (field, subnetGroup) {
        var me = this;

        var availabilityZoneField = me.down('#availabilityZone');
        availabilityZoneField.reset();

        var availabilityZoneStore = availabilityZoneField.getStore();
        availabilityZoneStore.clearFilter();

        availabilityZoneField.setValue(availabilityZoneStore.first());

        if (!Ext.isEmpty(subnetGroup)) {
            var subnetGroupStore = field.getStore();

            var subnetGroupRecord = subnetGroupStore.getAt(
                subnetGroupStore.find('dBSubnetGroupName', subnetGroup)
            );

            if (subnetGroupRecord) {
                var availabilityZoneNames = [];

                Ext.Array.each(subnetGroupRecord.get('subnets'), function (subnet) {
                    availabilityZoneNames.push(subnet.subnetAvailabilityZone.name);
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

        return true;
    },

    onInstanceTypeChange: function (field, instanceType) {
        var me = this;

        var encriptingDisabled = !me.isEncryptingAvailable(instanceType);

        var storageEncryptedField = me.down('#storageEncrypted');
        storageEncryptedField.setDisabled(encriptingDisabled);

        if (encriptingDisabled) {
            storageEncryptedField.setValue(false);
        }

        return true;
    },

    onMultiAzChange: function (field, multiAz) {
        var me = this;

        me.multiAz = multiAz;

        var engine = me.engine;

        if (!me.isModify && engine !== 'aurora') {
            me.down('#availabilityZone').setDisabled(multiAz).reset();
        }

        var optionGroupStore = me.down('#optionGroup').getStore();

        optionGroupStore.getProxy().params = {
            cloudLocation: me.cloudLocation,
            engine: engine,
            engineVersion: me.engineVersion,
            multiAz: multiAz
        };

        optionGroupStore.load();

        return true;
    },

    onStorageTypeChange: function (field, storageType) {
        var me = this;

        var iopsField = field.next();
        var isIops = storageType === 'io1';

        iopsField.setValue(1000);
        iopsField.setVisible(isIops);
        iopsField.submitValue = isIops;
        iopsField.allowBlank = !isIops;

        return true;
    },

    onStorageEncryptedChange: function (field, storageEncrypted) {
        var me = this;

        var KmsKeyField = me.down('#kmsKey');
        KmsKeyField.reset();
        KmsKeyField.setDisabled(!storageEncrypted).setVisible(storageEncrypted);

        return true;
    },

    onKmsKeyChange: function (field, kmsKey) {
        var selectArn = kmsKey === 0;

        var arnField = field.next('#arn');
        arnField.reset();
        arnField.setDisabled(!selectArn).setVisible(selectArn);

        return true;
    },

    onEngineChange: function (field, engine, previousEngine) {
        var me = this;

        me.engine = engine;
        me.previousEngine = previousEngine;

        me.updateMultiAz(engine).filterInstancesTypes(engine);

        var isAurora = field.isAurora(engine);

        if (!me.isModify) {
            var record = field.getStore().findRecord('id', engine);

            me.down('#port').setValue(record.get('defaultPort'));

            var licenseModelField = me.down('#licenseModel');
            var licenseModelStore = licenseModelField.getStore();

            licenseModelStore.loadRawData(record.get('licenseModels'));
            licenseModelField.setValue(licenseModelStore.first());

            me.setVpcRequired(isAurora || engine === 'mariadb');

            var isOracle = field.isOracle(engine);

            me.down('#characterSetName')
                .setVisible(isOracle)
                .setDisabled(!isOracle)
                .reset();
        }

        var engineVersionField = me.down('#engineVersion');
        engineVersionField.reset();

        var engineVersionStore = engineVersionField.getStore();

        engineVersionStore.getProxy().params = {
            cloudLocation: me.cloudLocation,
            engine: engine
        };

        engineVersionStore.load();

        return true;
    },

    onEngineVersionChange: function (field, engineVersion) {
        var me = this;

        me.engineVersion = engineVersion;

        if (!Ext.isEmpty(engineVersion)) {
            var cloudLocation = me.cloudLocation;
            var engine = me.engine;
            var optionGroupStore = me.down('#optionGroup').getStore();

            optionGroupStore.getProxy().params = {
                cloudLocation: cloudLocation,
                engine: engine,
                engineVersion: engineVersion,
                multiAz: me.down('#multiAz').getValue()
            };

            optionGroupStore.load();

            var parameterGroupStore = me.down('#parameterGroup').getStore();

            parameterGroupStore.getProxy().params = {
                cloudLocation: cloudLocation,
                engine: engine,
                engineVersion: engineVersion
            };

            parameterGroupStore.load();
        }

        return true;
    },

    onEngineVersionStoreLoad: function (store, records, successful) {
        var me = this;

        var isModify = me.isModify;

        if (successful && !Ext.isEmpty(records)) {
            me.down('#engineVersion').setValue(
                !isModify ? store.last() : me.instanceData.EngineVersion
            );
        }

        var engine = !isModify ? me.engine : me.instanceData.Engine;
        var allocatedStorageField = me.down('#allocatedStorage');
        var storageLimits = me.down('#engine').getStore().findRecord('id', engine).get('storageLimits');

        Ext.apply(allocatedStorageField, storageLimits);

        var isAurora = engine === 'aurora';

        if (!isModify) {
            allocatedStorageField.validate();

            if (isAurora || me.previousEngine === 'aurora') {
                me.selectAuroraEngine(isAurora);
            }

            return true;
        }

        if (isAurora) {
            me.selectAuroraEngine(true);
        }

        return true;
    },

    onOptionGroupStoreLoad: function (store, records, successful) {
        var me = this;

        var optionGroupField = me.down('#optionGroup');
        optionGroupField.reset();

        var proxy = store.getProxy();
        var defaultOptionGroupName = proxy.getReader().defaultOptionGroupName;

        if (!me.isModify) {
            optionGroupField.enable();

            var optionGroup = !Ext.isEmpty(defaultOptionGroupName) ? defaultOptionGroupName : store.first();

            if (!Ext.isEmpty(optionGroup)) {
                optionGroupField.setValue(optionGroup);

                return true;
            }

            optionGroupField.disable();

            return false;
        }

        var proxyParams = proxy.params;
        var instanceData = me.instanceData;
        var preserveOptionGroup = proxyParams.engineVersion === instanceData.EngineVersion && proxyParams.multiAz === instanceData.MultiAZ;

        optionGroupField.setValue(preserveOptionGroup ? instanceData.OptionGroupName : defaultOptionGroupName);

        return true;
    },

    onParameterGroupStoreLoad: function (store, records, successful) {
        var me = this;

        var parameterGroupField = me.down('#parameterGroup');
        var proxy = store.getProxy();
        var defaultParameterGroupName = proxy.getReader().defaultParameterGroupName;

        if (!me.isModify) {
            parameterGroupField.reset();
            parameterGroupField.enable();

            var parameterGroup = !Ext.isEmpty(defaultParameterGroupName) ? defaultParameterGroupName : store.first();

            if (!Ext.isEmpty(parameterGroup)) {
                parameterGroupField.setValue(parameterGroup);

                return true;
            }

            parameterGroupField.disable();

            return false;
        }

        var instanceData = me.instanceData;

        parameterGroupField.setValue(
            proxy.params.engineVersion === instanceData.EngineVersion ? instanceData.DBParameterGroup : defaultParameterGroupName
        );

        return true;
    },

    filterEngines: function (cloudLocation) {
        var me = this;

        var enginesStore = me.down('#engine').getStore();
        enginesStore.clearFilter();

        enginesStore.addFilter({
            id: 'byCloudLocation',
            filterFn: function (record) {
                if (record.get('field1') === 'aurora') {
                    // regions where Amazon Aurora is currently available
                    return Ext.Array.contains(['us-east-1', 'us-west-2', 'eu-west-1'], cloudLocation);
                }

                return true;
            }
        });

        return me;
    },

    applyKmsKeysData: function (cloudLocation) {
        var me = this;

        var allowedKmsKeys = ((Scalr.getGovernance('ec2', 'aws.kms_keys') || {})[cloudLocation] || {})['keys'];
        var isAllowedKeysDefined = Ext.isArray(allowedKmsKeys);

        if (isAllowedKeysDefined) {
            allowedKmsKeys = Ext.Array.clone(allowedKmsKeys);
            allowedKmsKeys.unshift({
                id: 0,
                displayField: 'Enter a key ARN'
            });
        }

        var kmsKeysField = me.down('#kmsKey');
        var kmsKeysProxy = kmsKeysField.getStore().getProxy();

        kmsKeysProxy.params.cloudLocation = cloudLocation;
        kmsKeysProxy.data = allowedKmsKeys;

        kmsKeysField.toggleIcon('governance', isAllowedKeysDefined);
        kmsKeysField.reset();

        return me;
    },

    isEncryptingAvailable: function (instanceType) {
        return instanceType === 'db.t2.large' || Ext.Array.some(['.m3', '.m4', '.r3', '.cr1'], function (type) {
            return instanceType.indexOf(type) !== -1;
        });
    },

    updateMultiAz: function (engine) {
        var me = this;

        me.down('#mirroring').setVisible(
            engine === 'sqlserver-ee' || engine === 'sqlserver-se'
        );

        var isMultiAzForbided = engine === 'sqlserver-web' || engine === 'sqlserver-ex';

        var multiAzField = me.down('#multiAz');
        multiAzField.setVisible(!isMultiAzForbided).setDisabled(isMultiAzForbided);

        if (isMultiAzForbided) {
            multiAzField.setValue(false);
        }

        return me;
    },

    filterInstancesTypes: function (engine) {
        var me = this;

        var instanceClassField = me.down('#instanceType');
        var instanceClassStore = instanceClassField.getStore();

        instanceClassStore.clearFilter();

        var allowedTypes = {
            aurora: ['r3'],
            mariadb: ['t2', 'm3', 'r3']
        };

        var allowedByEngine = allowedTypes[engine];
        var hasRestrictions = Ext.isDefined(allowedByEngine);
        var encryptionRequired = (Scalr.getGovernance('ec2', 'aws.rds_storage') || {})['require_encryption'];
        var isModify = me.isModify;
        var filteringRequired = isModify ? hasRestrictions : (hasRestrictions || encryptionRequired);

        if (filteringRequired) {
            instanceClassStore.filterBy(function (record) {
                var instanceType = record.get('field1');

                if (!isModify) {
                    var allowed = !encryptionRequired || me.isEncryptingAvailable(instanceType);

                    if (allowed && allowedByEngine) {
                        allowed = Ext.Array.some(allowedByEngine, function (type) {
                            return instanceType.indexOf('.' + type) !== -1;
                        });
                    }

                    return allowed;
                }

                return Ext.Array.some(allowedByEngine, function(type) {
                    return instanceType.indexOf('.' + type) !== -1;
                });
            });
        }

        if (!isModify) {
            instanceClassField.setValue(!hasRestrictions && !encryptionRequired ? 'db.m1.small' : instanceClassStore.first());
            return me;
        }

        instanceClassField.setValue(me.instanceData['DBInstanceClass']);

        return me;
    },

    setVpcRequired: function (isRequired) {
        var me = this;

        var vpcField = me.down('#vpc');
        vpcField.allowBlank = !isRequired;
        vpcField.validate();

        return me;
    },

    selectAuroraEngine: function (selected) {
        var me = this;

        var isModify = me.isModify;
        var engineVersionField = me.down('#engineVersion');
        var engineVersion = !isModify ? engineVersionField.getValue() : me.instanceData.EngineVersion;

        if (selected && !Ext.isEmpty(engineVersion)) {
            engineVersionField.setRawValue('compatible with MySQL ' + engineVersion);
        }

        engineVersionField.setDisabled(selected);

        me.down('#storageType').setValue(!selected ? 'gp2' : 'grover').setVisible(!selected);

        me.down('#allocatedStorageContainer').setDisabled(selected).setVisible(!selected);
        me.down('#allocatedStorage').setDisabled(selected);

        me.down('#multiAzContainer').setDisabled(selected).setVisible(!selected);

        if (!isModify) {
            me.down('#backupRetentionPeriod').setValue(!selected ? 1 : 0)
                .up('fieldcontainer').setVisible(!selected);
        }

        return me;
    },

    applySecurityGroups: function (securityGroups) {
        var me = this;

        var securityGroupNames = [];

        Ext.Array.each(securityGroups, function (securityGroup) {
            securityGroupNames.push(securityGroup.dBSecurityGroupName);
        });

        var securityGroupsField = me.down('#dbSecurityGroups');
        securityGroupsField.reset();
        securityGroupsField.setValue(!me.isModify ? securityGroupNames.join(', ') : me.instanceData.DBSecurityGroups);

        return me;
    },

    applyAvailabilityZone: function (availabilityZones) {
        var me = this;

        availabilityZones = !Ext.isEmpty(availabilityZones) ? availabilityZones : [];

        availabilityZones.unshift({
            id: '',
            name: 'No preference'
        });

        var availabilityZoneField = me.down('#availabilityZone');
        availabilityZoneField.reset();

        var availabilityZoneStore = availabilityZoneField.getStore();
        availabilityZoneStore.clearFilter();
        availabilityZoneStore.loadData(availabilityZones);

        availabilityZoneField.setValue(!me.isModify ? availabilityZoneStore.first() : me.instanceData.AvailabilityZone);

        return me;
    },

    applyCloudLocationParameters: function (cloudLocation) {
        var me = this;

        Scalr.Request({
            processBox: {
                type: 'action'
            },
            url: '/tools/aws/rds/instances/xGetParameters',
            params: {
                cloudLocation: cloudLocation
            },
            scope: this,
            success: function (response) {
                me.applySecurityGroups(response.sgroups).applyAvailabilityZone(response.zones)
                    .down('#engine').setValue(!me.isModify ? 'mysql' : me.instanceData.Engine);

                me.getForm().isValid();
            },
            failure: function () {
                me.disable();
            }
        });

        return me;
    },

    launchInstance: function () {
        var me = this;

        var values = me.getValues();
        var vpcSecurityGroups = values.VpcSecurityGroups;
        var form = me.getForm();

        var vpcId = values.VpcId;

        if (!Ext.isEmpty(vpcId) && vpcId !== 0  && (Ext.isEmpty(vpcSecurityGroups) || vpcSecurityGroups === '[]')) {
            var vpcSecurityGroupsField = form.findField('VpcSecurityGroupIds');
            vpcSecurityGroupsField.markInvalid('This field is required.');
            vpcSecurityGroupsField.focus();

            return false;
        }

        if (!form.isValid()) {
            var invalidField = me.getFirstInvalidField();

            if (!Ext.isEmpty(invalidField)) {
                me.scrollToField(invalidField);
                invalidField.focus();
            }

            return false;
        }

        me.down('#maintenanceSettings').prepareData();

        Scalr.Request({
            processBox: {
                type: 'save',
                msg: 'Launching...'
            },
            url: '/tools/aws/rds/instances/xLaunchInstance',
            form: form,
            success: function (response) {
                Scalr.event.fireEvent('update', '/tools/aws/rds/instances', 'launch', response.instance, response.cloudLocation);
                Scalr.event.fireEvent('close');
            }
        });

        return true;
    },

    modifyInstance: function (params) {
        params = Ext.isObject(params) ? params : {};

        var me = this;

        var instance = me.instanceData;
        var isAurora = instance.Engine === 'aurora';

        if (!isAurora) {
            me.down('#maintenanceSettings').prepareData();
        }

        var securityGroups = me.down('#vpcSecurityGroups');

        if (!Ext.isEmpty(params) && !Ext.isEmpty(params.VpcSecurityGroups)) {
            securityGroups.disable();
        }

        var form = me.getForm();

        if (!form.isValid()) {
            var invalidField = me.getFirstInvalidField();

            if (!Ext.isEmpty(invalidField)) {
                me.scrollToField(invalidField);
                invalidField.focus();
            }

            if (!isAurora) {
                securityGroups.enable();
            }

            return false;
        }

        var vpcId = instance.VpcId;

        if (!Ext.isEmpty(vpcId)) {
            params.VpcId = vpcId;
        }

        Scalr.Request({
            processBox: {
                type: 'save',
                msg: 'Modifying...'
            },
            url: '/tools/aws/rds/instances/xModifyInstance',
            form: form,
            params: params,
            success: function (response) {
                Scalr.event.fireEvent('update', '/tools/aws/rds/instances', 'modify', response.instance, response.cloudLocation);
                Scalr.event.fireEvent('close');
            },
            failure: function () {
                if (!isAurora) {
                    securityGroups.enable();
                }
            }
        });

        return true;
    },

    acceptSecurityGroupsPolicy: function () {
        var me = this;

        if (me.getForm().isValid()) {
            Scalr.Request({
                processBox: {
                    type: 'save',
                    msg: 'Modifying...'
                },
                url: '/platforms/ec2/xGetDefaultVpcSegurityGroups',
                params: {
                    cloudLocation: me.cloudLocation,
                    vpcId: me.instanceData.VpcId,
                    serviceName: 'rds'
                },
                success: function (response) {
                    var securityGrops = Ext.Array.map(response.data, function(securityGroup) {
                        return {
                            id: securityGroup.securityGroupId,
                            name: securityGroup.securityGroupName
                        };
                    });

                    me.modifyInstance({
                        VpcSecurityGroups: Ext.encode(securityGrops)
                    });
                }
            });

            return true;
        }
    },

    getFirstInvalidField: function () {
        return this.down('field{isValid()===false}');
    },

    scrollToField: function (field) {
        return field.inputEl.scrollIntoView(this.body.el, false, false);
    },

    config: {
        width: 700,
        preserveScrollPosition: true,

        locations: {},
        cloudLocation: null,

        defaults: {
            xtype: 'fieldset',
            defaults: {
                labelWidth: 200,
                width: 500
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
            maxWidth: 700,
            defaults: {
                xtype: 'button',
                flex: 1,
                maxWidth: 140
            },
            items: [{
                itemId: 'launch',
                text: 'Launch',
                disabled: true,
                handler: function (button) {
                    button.up('rdsinstanceform').launchInstance();
                }
            }, {
                itemId: 'modify',
                text: 'Modify',
                hidden: true,
                handler: function (button) {
                    button.up('rdsinstanceform').modifyInstance({
                        ignoreGovernance: true
                    });
                }
            }, {
                itemId: 'extendedModify',
                xtype: 'splitbutton',
                text: 'Modify',
                hidden: true,
                handler: function (button) {
                    button.up('rdsinstanceform').modifyInstance();
                },
                menu: [{
                    text: 'Modify and automatically comply with Security Groups Policy',
                    iconCls: 'x-btn-icon-governance',
                    handler: function (button) {
                        button.up('rdsinstanceform').acceptSecurityGroupsPolicy();
                    }
                }, {
                    xtype: 'menuseparator'
                }, {
                    text: 'Modify and keep Security Groups as-is',
                    iconCls: 'x-btn-icon-governance-ignore',
                    handler: function (button) {
                        button.up('rdsinstanceform').modifyInstance({
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
        }]
    },

    items: [{
        itemId: 'securityGroupsPolicyWarning',
        xtype: 'displayfield',
        cls: 'x-form-field-governance x-form-field-governance-fit',
        anchor: '100%',
        hidden: true
    }, {
        itemId: 'locationSettings',
        title: 'Location and VPC Settings',
        defaults: {
            xtype: 'combo',
            labelWidth: 140,
            editable: false,
            width: 610
        },
        items: [{
            itemId: 'cloudLocation',
            name: 'cloudLocation',
            fieldLabel: 'Cloud Location',
            emptyText: 'Select location',
            queryMode: 'local',
            displayField: 'name',
            valueField: 'id',
            plugins: {
                ptype: 'fieldinnericoncloud',
                platform: 'ec2'
            },
            store: {
                fields: ['id', 'name'],
                proxy: 'object'
            }
        }, {
            itemId: 'vpc',
            name: 'VpcId',
            fieldLabel: 'VPC',
            emptyText: 'No VPC selected. Launch DB instance outside VPC',
            queryMode: 'local',
            hidden: true,
            valueField: 'id',
            displayField: 'name',
            store: {
                fields: [
                    'id',
                    'name',
                    'defaultSecurityGroupId',
                    'defaultSecurityGroupName'
                ],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'rdsInstances',
                    url: '/platforms/ec2/xGetVpcList',
                    reader: {
                        type: 'json',
                        rootProperty: 'data',
                        successProperty: 'success',
                        transform: function (data) {
                            this.defaultVpc = data.default;
                            return data.vpc;
                        }
                    }
                }
            }
        }, {
            itemId: 'subnetGroup',
            name: 'DBSubnetGroupName',
            fieldLabel: 'Subnet Group',
            emptyText: 'Select Subnet Group',
            hidden: true,
            disabled: true,
            queryMode: 'local',
            valueField: 'dBSubnetGroupName',
            displayField: 'dBSubnetGroupName',
            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/aws/rds/instances/createSubnetGroup',
                applyNewValue: true
            }],
            store: {
                fields: [
                    'dBSubnetGroupName',
                    'dBSubnetGroupDescription',
                    'subnets'
                ],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'rdsInstances',
                    url: '/tools/aws/rds/instances/xGetSubnetGroup',
                    root: 'subnetGroups',
                    filterFields: ['dBSubnetGroupName']
                }
            }
        }, {
            itemId: 'subnetIds',
            name: 'SubnetIds',
            xtype: 'hiddenfield',
            disabled: true,
            getSubmitValue: function () {
                var me = this;

                var vpcId = me.prev('#vpc').getValue();

                if (!Ext.isEmpty(vpcId) && vpcId !== 0) {
                    var subnetGroupField = me.prev();
                    var record = subnetGroupField.findRecord('dBSubnetGroupName', subnetGroupField.getValue());

                    if (Ext.isObject(record) && record.isModel) {
                        var subnets = record.get('subnets');

                        return !Ext.isArray(subnets) ? null : Ext.encode(
                            Ext.Array.map(subnets, function (subnet) {
                                return subnet.subnetIdentifier;
                            })
                        );
                    }
                }

                return null;
            }
        }, {
            itemId: 'publiclyAccessible',
            xtype: 'fieldcontainer',
            fieldLabel: 'Publicly Accessible',
            hidden: true,
            width: 185,
            height: 30,
            plugins: [{
                ptype: 'fieldicons',
                align: 'right',
                icons: {
                    id: 'info',
                    tooltip: 'Select <b>Yes</b> if you want EC2 instances and devices outside of the VPC hosting the DB instance to connect to the DB instance. ' + 'If you select <b>No</b>, Amazon RDS will not assign a public IP address to the DB instance, ' + 'and no EC2 instance or devices outside of the VPC will be able to connect. <br/>If you select <b>Yes</b>, ' + 'you must also select one or more VPC security groups that specify which EC2 instances ' + 'and devices can connect to the DB instance.'
                }
            }],
            items: [{
                name: 'PubliclyAccessible',
                xtype: 'checkboxfield',
                inputValue: true,
                uncheckedValue: false
            }]
        }]
    }, {
        title: 'Database Instance and Storage',
        hidden: true,
        items: [{
            itemId: 'instanceId',
            name: 'DBInstanceIdentifier',
            xtype: 'textfield',
            fieldLabel: 'DB Instance Identifier',
            emptyText: 'Unique name for this Database Instance',
            allowBlank: false,
            regex: /^[a-zA-Z].*$/,
            invalidText: 'Identifier must start with a letter',
            minLength: 1,
            maxLength: 63,
            hideInputOnReadOnly: true
        }, {
            itemId: 'instanceType',
            name: 'DBInstanceClass',
            xtype: 'combo',
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
                    tooltip: 'Instance type availability varies across AWS regions. ' +
                        'Consult the AWS Documentation on ' +
                        '<a target="_blank" href="http://aws.amazon.com/rds/pricing/">current</a>' +
                        ' and ' +
                        '<a target="_blank" href="http://aws.amazon.com/rds/previous-generation/">legacy</a>' +
                        ' instance types for more information.'
                }
            }]
        }, {
            itemId: 'multiAzContainer',
            xtype: 'fieldcontainer',
            layout: 'hbox',
            items: [{
                itemId: 'multiAz',
                name: 'MultiAZ',
                xtype: 'checkboxfield',
                fieldLabel: 'Multi-AZ Deployment',
                labelWidth: 200,
                inputValue: true,
                uncheckedValue: false
            }, {
                itemId: 'mirroring',
                xtype: 'displayfield',
                margin: '0 0 0 6',
                flex: 1,
                hidden: true,
                value: '(Mirroring)'
            }],
            plugins: [{
                ptype: 'fieldicons',
                align: 'right',
                icons: {
                    id: 'info',
                    hidden: true,
                    tooltip: 'Determine if you want to create Aurora Replicas in other Availability Zones for failover support. ' + 'For more information about multiple Availability Zones, see ' + '<a target="_blank" href="http://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/Concepts.RegionsAndAvailabilityZones.html">Regions and Availability Zones</a>.'
                }
            }]
        }, {
            itemId: 'availabilityZone',
            name: 'AvailabilityZone',
            xtype: 'combo',
            fieldLabel: 'Availability Zone',
            queryMode: 'local',
            editable: false,
            emptyText: 'No preference',
            valueField: 'id',
            displayField: 'name',
            store: {
                model: Scalr.getModel({
                    fields: ['id', 'name']
                }),
                proxy: 'object'
            }
        }, {
            itemId: 'securityGroupsContainer',
            xtype: 'rdssecuritygroups',
            fieldLabel: 'Security Groups',
            width: 595,
            fieldDefaults: {
                width: 295
            }
        }, {
            itemId: 'storageType',
            name: 'StorageType',
            xtype: 'combo',
            fieldLabel: 'Storage type',
            editable: false,
            queryMode: 'local',
            value: 'gp2',
            valueField: 'type',
            displayField: 'name',
            store: {
                fields: ['type', 'name'],
                data: [{
                    type: 'standard',
                    name: 'Magnetic'
                }, {
                    type: 'gp2',
                    name: 'General Purpose (SSD)'
                }, {
                    type: 'io1',
                    name: 'Provisioned IOPS (SSD)'
                }]
            }
        }, {
            itemId: 'iops',
            name: 'Iops',
            xtype: 'numberfield',
            fieldLabel: 'IOPS',
            submitValue: false,
            hidden: true,
            value: 1000,
            minValue: 1000,
            maxValue: 30000,
            step: 1000,
            validator: function (value) {
                return value % 1000 ? 'IOPS value must be an increment of 1000' : true;
            }
        }, {
            itemId: 'allocatedStorageContainer',
            xtype: 'fieldcontainer',
            layout: 'hbox',
            width: 530,
            items: [{
                itemId: 'allocatedStorage',
                name: 'AllocatedStorage',
                xtype: 'numberfield',
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
        }, {
            itemId: 'storageEncryptedContainer',
            xtype: 'fieldcontainer',
            fieldLabel: 'Enable Encryption',
            items: [{
                itemId: 'storageEncrypted',
                name: 'StorageEncrypted',
                xtype: 'checkboxfield',
                boxLabel: ' ',
                inputValue: true,
                uncheckedValue: false,
                width: 220,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: ['governance', {
                        id: 'info',
                        tooltip: '<b>Amazon RDS encryption is available for all storage types and the following DB instance classes:</b> ' + 'db.m4.large, db.m4.xlarge, db.m4.2xlarge, db.m4.4xlarge, db.m4.10xlarge, db.m3.medium, db.m3.large, db.m3.xlarge, db.m3.2xlarge, db.r3.large, db.r3.xlarge, db.r3.2xlarge, db.r3.4xlarge, db.r3.8xlarge, db.cr1.8xlarge, db.t2.large.'
                    }]
                }]
            }]
        }, {
            itemId: 'kmsKey',
            name: 'KmsKeyId',
            xtype: 'combo',
            fieldLabel: 'KMS key',
            valueField: 'id',
            displayField: 'displayField',
            emptyText: 'Default key (aws/rds)',
            matchFieldWidth: true,
            hidden: true,
            disabled: true,
            queryCaching: false,
            minChars: 0,
            queryDelay: 10,
            autoSearch: false,
            editable: false,
            plugins: {
                ptype: 'fieldicons',
                position: 'outer',
                align: 'right',
                icons: [{
                    id: 'governance'
                }]
            },
            store: {
                fields: ['id', 'alias', {
                    name: 'displayField',
                    convert: function (value, record) {
                        if (!Ext.isEmpty(value)) {
                            return value;
                        }

                        var alias = record.get('alias');

                        return !Ext.isEmpty(alias) ? alias.replace('alias/', '') : '';
                    }
                }],
                proxy: {
                    type: 'cachedrequest',
                    url: '/platforms/ec2/xGetKmsKeysList',
                    root: 'keys',
                    filterFn: function(record) {
                        return !Ext.Array.contains(
                            ['alias/aws/ebs', 'alias/aws/redshift', 'alias/aws/s3'],
                            record.get('alias')
                        );
                    },
                    prependData: [{
                        id: 0,
                        displayField: 'Enter a key ARN'
                    }],
                    params: {}
                },
                sorters: {
                    property: 'alias',
                    transform: function (value) {
                        return !!value ? value.toLowerCase() : value;
                    }
                }
            },
            getSubmitValue: function () {
                var me = this;

                var value = me.getValue();

                return value !== 0 ? value : me.next('#arn').getValue();
            }
        }, {
            itemId: 'arn',
            xtype: 'textfield',
            fieldLabel: 'ARN',
            isFormField: false,
            allowBlank: false,
            disabled: true,
            hidden: true,
            width: '100%',
            plugins: [{
                ptype: 'fieldicons',
                align: 'right',
                icons: {
                    id: 'info',
                    tooltip: Ext.String.htmlEncode('e.g.:arn:aws:kms:<region>:<accountID>:key/<key-id>')
                }
            }]
        }]
    }, {
        itemId: 'noFarmIdInfo',
        xtype: 'displayfield',
        hidden: true,
        anchor: '100%',
        cls: 'x-form-field-info',
        value: 'This database instance is not associated with a Farm.'
    }, {
        itemId: 'associateFarmId',
        xtype: 'fieldset',
        title: 'Associate this database instance with a Farm' +
            ((Scalr.getGovernance('ec2', 'aws.rds') || {})['db_instance_requires_farm_association'] == 1
                ? '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="The account owner has enforced a specific policy on this setting" class="x-icon-governance" />'
                : ''
            ),
        collapsible: true,
        collapsed: true,
        checkboxToggle: true,
        hidden: true,
        defaults: {
            labelWidth: 200,
            width: 500
        },
        listeners: {
            beforecollapse: function (fieldSet) {
                return !fieldSet.checkboxCmp.disabled;
            },
            beforeexpand: function (fieldSet) {
                return !fieldSet.checkboxCmp.disabled;
            },
            expand: function (fieldSet) {
                fieldSet.down('#farmId').enable();
            },
            collapse: function (fieldSet) {
                fieldSet.down('#farmId').disable();
            }
        },
        items: [{
            itemId: 'farmId',
            name: 'farmId',
            xtype: 'combo',
            fieldLabel: 'Farm',
            matchFieldWidth: false,
            disabled: true,
            anyMatch: true,
            autoSearch: false,
            selectOnFocus: true,
            restoreValueOnBlur: true,
            queryMode: 'local',
            valueField: 'id',
            displayField: 'name',
            allowBlank: false,
            hideInputOnReadOnly: true,
            listConfig: {
                minWidth: 150
            },
            store: {
                fields: ['id', 'name'],
                proxy: 'object'
            },
            plugins: [{
                ptype: 'fieldicons',
                position: 'outer',
                align: 'right',
                icons: [{
                    id: 'question',
                    hidden: false,
                    tooltip: 'Only Farms you\'re allowed to manage are available'
                }]
            }]
        }]
    }, {
        xtype: 'fieldset',
        title: 'Database Engine',
        hidden: true,
        defaults: {
            labelWidth: 200,
            width: 500
        },
        items: [{
            itemId: 'engine',
            name: 'Engine',
            xtype: 'combo',
            fieldLabel: 'Engine',
            hideInputOnReadOnly: true,
            queryMode: 'local',
            valueField: 'id',
            displayField: 'name',
            editable: false,
            isOracle: function (engine) {
                return engine.substring(0, 6) === 'oracle';
            },
            isAurora: function (engine) {
                return engine === 'aurora';
            },
            plugins: ['fieldinnericonrds'],
            store: {
                proxy: 'object',
                fields: [
                    'id',
                    'name',
                    'storageLimits',
                    'licenseModels',
                    'defaultPort'
                ],
                data: [{
                    id: 'mysql',
                    name: 'MySQL',
                    defaultPort: 3306,
                    licenseModels: ['general-public-license'],
                    storageLimits: {
                        minValue: 5,
                        maxValue: 3072
                    }
                }, {
                    id: 'oracle-se1',
                    name: 'Oracle SE One',
                    defaultPort: 1521,
                    licenseModels: ['license-included', 'bring-your-own-license'],
                    storageLimits: {
                        minValue: 10,
                        maxValue: 3072
                    }
                }, {
                    id: 'oracle-se',
                    name: 'Oracle SE',
                    defaultPort: 1521,
                    licenseModels: ['bring-your-own-license'],
                    storageLimits: {
                        minValue: 10,
                        maxValue: 3072
                    }
                }, {
                    id: 'oracle-ee',
                    name: 'Oracle EE',
                    defaultPort: 1521,
                    licenseModels: ['bring-your-own-license'],
                    storageLimits: {
                        minValue: 10,
                        maxValue: 3072
                    }
                }, {
                    id: 'sqlserver-ee',
                    name: 'Microsoft SQL Server EE',
                    defaultPort: 1433,
                    licenseModels: ['bring-your-own-license'],
                    storageLimits: {
                        minValue: 200,
                        maxValue: 1024
                    }
                }, {
                    id: 'sqlserver-se',
                    name: 'Microsoft SQL Server SE',
                    defaultPort: 1433,
                    licenseModels: ['license-included', 'bring-your-own-license'],
                    storageLimits: {
                        minValue: 200,
                        maxValue: 1024
                    }
                }, {
                    id: 'sqlserver-ex',
                    name: 'Microsoft SQL Server EX',
                    defaultPort: 1433,
                    licenseModels: ['license-included'],
                    storageLimits: {
                        minValue: 20,
                        maxValue: 1024
                    }
                }, {
                    id: 'sqlserver-web',
                    name: 'Microsoft SQL Server WEB',
                    defaultPort: 1433,
                    licenseModels: ['license-included'],
                    storageLimits: {
                        minValue: 20,
                        maxValue: 1024
                    }
                }, {
                    id: 'postgres',
                    name: 'PostgreSQL',
                    defaultPort: 5432,
                    licenseModels: ['postgresql-license'],
                    storageLimits: {
                        minValue: 5,
                        maxValue: 3072
                    }
                }, {
                    id: 'aurora',
                    name: 'Amazon Aurora',
                    defaultPort: 3306,
                    licenseModels: ['general-public-license'],
                    storageLimits: {
                        minValue: 100,
                        maxValue: 3072
                    }
                }, {
                    id: 'mariadb',
                    name: 'MariaDB',
                    defaultPort: 3306,
                    licenseModels: ['general-public-license'],
                    storageLimits: {
                        minValue: 5,
                        maxValue: 6144
                    }
                }]
            }
        }, {
            itemId: 'engineVersion',
            name: 'EngineVersion',
            xtype: 'combo',
            fieldLabel: 'Version',
            editable: false,
            queryMode: 'local',
            valueField: 'version',
            displayField: 'version',
            hideInputOnReadOnly: true,
            store: {
                fields: ['version'],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'rdsInstances',
                    url: '/tools/aws/rds/instances/xGetEngineVersions',
                    reader: {
                        type: 'array',
                        rootProperty: 'engineVersions',
                        successProperty: 'success'
                    }
                }
            }
        }, {
            itemId: 'allowMajorVersionUpgrade',
            name: 'AllowMajorVersionUpgrade',
            xtype: 'checkboxfield',
            fieldLabel: 'Allow Major Version Upgrade',
            inputValue: true,
            uncheckedValue: false,
            value: true,
            hidden: true,
            disabled: true
        }, {
            itemId: 'licenseModel',
            name: 'LicenseModel',
            xtype: 'combo',
            fieldLabel: 'Licensing Model',
            editable: false,
            queryMode: 'local',
            valueField: 'licenseModel',
            displayField: 'licenseModel',
            value: 'general-public-license',
            store: {
                fields: ['licenseModel'],
                data: [
                    'license-included',
                    'bring-your-own-license',
                    'general-public-license',
                    'postgresql-license'
                ],
                proxy: {
                    type: 'object',
                    reader: {
                        type: 'array',
                        transform: function (data) {
                            return Ext.Array.map(data, function (item) {
                                return [item];
                            });
                        }
                    }
                }
            }
        }, {
            itemId: 'parameterGroup',
            name: 'DBParameterGroup',
            xtype: 'combo',
            fieldLabel: 'Parameter Group',
            queryMode: 'local',
            valueField: 'dBParameterGroupName',
            displayField: 'dBParameterGroupName',
            editable: false,
            store: {
                fields: ['dBParameterGroupName'],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'rdsInstances',
                    url: '/tools/aws/rds/instances/xGetParameterGroup',
                    reader: {
                        type: 'json',
                        rootProperty: 'groups',
                        successProperty: 'success',
                        transform: function (data) {
                            this.defaultParameterGroupName = data.default;
                            return data.groups;
                        }
                    }
                }
            }
        }, {
            itemId: 'optionGroup',
            name: 'OptionGroupName',
            xtype: 'combo',
            fieldLabel: 'Option Group',
            queryMode: 'local',
            valueField: 'optionGroupName',
            displayField: 'optionGroupName',
            editable: false,
            store: {
                fields: ['optionGroupName'],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'rdsInstances',
                    url: '/tools/aws/rds/instances/xGetOptionGroups',
                    reader: {
                        type: 'json',
                        rootProperty: 'optionGroups',
                        successProperty: 'success',
                        transform: function (data) {
                            this.defaultOptionGroupName = data.defaultOptionGroupName;
                            return data.optionGroups;
                        }
                    }
                }
            }
        }, {
            itemId: 'characterSetName',
            name: 'CharacterSetName',
            xtype: 'combo',
            fieldLabel: 'Character Set Name',
            value: 'AL32UTF8',
            queryMode: 'local',
            editable: false,
            hidden: true,
            plugins: [{
                ptype: 'fieldicons',
                align: 'right',
                position: 'outer',
                icons: {
                    id: 'info',
                    tooltip: 'For Oracle DB Instances only.<br>' +
                        'The character set being used by the database.<br>' +
                        'Default is <b>AL32UTF8</b>.'
                }
            }],
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
            ]
        }, {
            itemId: 'port',
            name: 'Port',
            xtype: 'numberfield',
            fieldLabel: 'Port',
            value: 3306,
            minValue: 1150,
            maxValue: 65535,
            allowBlank: false
        }]
    }, {
        itemId: 'databaseSettings',
        xtype: 'fieldset',
        title: 'Database',
        hidden: true,
        defaults: {
            labelWidth: 200,
            width: 500
        },
        items: [{
            itemId: 'masterUsername',
            name: 'MasterUsername',
            xtype: 'textfield',
            fieldLabel: 'Master Username',
            allowBlank: false
        }, {
            itemId: 'masterUserPassword',
            name: 'MasterUserPassword',
            xtype: 'textfield',
            fieldLabel: 'Master Password',
            allowBlank: false,
            regex: /^[a-z0-9!#$%&'()*+,.:;<=>?\[\]\\^_`{|}~-]*$/i,
            invalidText: 'Master Password can be any printable ASCII character except "/", """, or "@"',
            minLength: 8,
            minLengthText: 'Master Password must be a minimum of 8 characters'
        }, {
            itemId: 'dbName',
            name: 'DBName',
            xtype: 'textfield',
            fieldLabel: 'Initial Database Name',
            flex: 1,
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
        itemId: 'maintenanceSettings',
        title: 'Maintenance Windows and Backups',
        hidden: true,
        fieldDefaults: {
            submitValue: false
        },
        prepareData: function () {
            var me = this;

            var maintenanceContainer = me.down('#maintenanceWindowContainer');
            var maintenanceFirstDay = maintenanceContainer.down('#maintenanceFirstDay').getValue();
            var maintenanceFirstHour = maintenanceContainer.down('#maintenanceFirstHour').getValue();
            var maintenanceFirstMinute = maintenanceContainer.down('#maintenanceFirstMinute').getValue();
            var maintenanceLastDay = maintenanceContainer.down('#maintenanceLastDay').getValue();
            var maintenanceLastHour = maintenanceContainer.down('#maintenanceLastHour').getValue();
            var maintenanceLastMinute = maintenanceContainer.down('#maintenanceLastMinute').getValue();

            me.down('#preferredMaintenanceWindow').setValue(
                maintenanceFirstDay + ':' + maintenanceFirstHour + ':' + maintenanceFirstMinute
                + '-' + maintenanceLastDay + ':' + maintenanceLastHour + ':' + maintenanceLastMinute
            );

            var backupContainer = me.down('#backupWindowContainer');
            var backupFirstHour = backupContainer.down('#backupFirstHour').getValue();
            var backupFirstMinute = backupContainer.down('#backupFirstMinute').getValue();
            var backupLastHour = backupContainer.down('#backupLastHour').getValue();
            var backupLastMinute = backupContainer.down('#backupLastMinute').getValue();

            me.down('#preferredBackupWindow').setValue(
                backupFirstHour + ':' + backupFirstMinute + '-' + backupLastHour + ':' + backupLastMinute
            );

            return true;
        },
        items: [{
            itemId: 'applyImmediately',
            name: 'ApplyImmediately',
            xtype: 'checkboxfield',
            labelWidth: 200,
            fieldLabel: 'Apply Immediately',
            inputValue: true,
            uncheckedValue: false,
            disabled: true,
            hidden: true,
            submitValue: true
        }, {
            xtype: 'hiddenfield',
            itemId: 'preferredMaintenanceWindow',
            name: 'PreferredMaintenanceWindow',
            submitValue: true
        }, {
            xtype: 'hiddenfield',
            itemId: 'preferredBackupWindow',
            name: 'PreferredBackupWindow',
            submitValue: true
        }, {
            labelWidth: 200,
            xtype: 'checkboxfield',
            fieldLabel: 'Auto Minor Version Upgrade',
            name: 'AutoMinorVersionUpgrade',
            inputValue: true,
            uncheckedValue: false,
            value: false,
            submitValue: true
        }, {
            xtype: 'fieldcontainer',
            itemId: 'maintenanceWindowContainer',
            width: '100%',
            layout: {
                type: 'hbox'
            },
            defaults: {
                margin: '0 3 0 0'
            },
            items: [{
                itemId: 'maintenanceFirstDay',
                labelWidth: 200,
                width: 275,
                xtype: 'combo',
                fieldLabel: 'Preferred Maintenance Window',
                queryMode: 'local',
                editable: false,
                store: [
                    ['sun', 'Sun'],
                    ['mon', 'Mon'],
                    ['tue', 'Tue'],
                    ['wed', 'Wed'],
                    ['thu', 'Thur'],
                    ['fri', 'Fri'],
                    ['sat', 'Sat']
                ],
                value: 'mon'
            }, {
                xtype: 'displayfield',
                value: ' : '
            }, {
                itemId: 'maintenanceFirstHour',
                width: 35,
                xtype: 'textfield',
                value: '05'
            }, {
                xtype: 'displayfield',
                value: ' : '
            }, {
                itemId: 'maintenanceFirstMinute',
                width: 35,
                xtype: 'textfield',
                value: '00'
            }, {
                xtype: 'displayfield',
                value: ' - '
            }, {
                itemId: 'maintenanceLastDay',
                width: 70,
                xtype: 'combo',
                queryMode: 'local',
                editable: false,
                store: [
                    ['sun', 'Sun'],
                    ['mon', 'Mon'],
                    ['tue', 'Tue'],
                    ['wed', 'Wed'],
                    ['thu', 'Thur'],
                    ['fri', 'Fri'],
                    ['sat', 'Sat']
                ],
                value: 'mon'
            }, {
                xtype: 'displayfield',
                value: ' : '
            }, {
                itemId: 'maintenanceLastHour',
                width: 35,
                xtype: 'textfield',
                value: '09'
            }, {
                xtype: 'displayfield',
                value: ' : '
            }, {
                itemId: 'maintenanceLastMinute',
                width: 35,
                xtype: 'textfield',
                value: '00'
            }, {
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
            itemId: 'backupWindowContainer',
            layout: {
                type: 'hbox'
            },
            items: [{
                itemId: 'backupFirstHour',
                labelWidth: 200,
                width: 240,
                xtype: 'textfield',
                fieldLabel: 'Preferred Backup Window',
                value: '10'
            }, {
                xtype: 'displayfield',
                value: ' : ',
                margin: '0 0 0 3'
            }, {
                itemId: 'backupFirstMinute',
                width: 35,
                xtype: 'textfield',
                value: '00',
                margin: '0 0 0 3'
            }, {
                xtype: 'displayfield',
                value: ' - ',
                margin: '0 0 0 3'
            }, {
                itemId: 'backupLastHour',
                width: 35,
                xtype: 'textfield',
                value: '12',
                margin: '0 0 0 3'
            }, {
                xtype: 'displayfield',
                value: ' : ',
                margin: '0 0 0 3'
            }, {
                itemId: 'backupLastMinute',
                width: 35,
                xtype: 'textfield',
                value: '00',
                margin: '0 0 0 3'
            }, {
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
            itemId: 'backupRetentionPeriodContainer',
            xtype: 'fieldcontainer',
            layout: 'hbox',
            items: [{
                itemId: 'backupRetentionPeriod',
                name: 'BackupRetentionPeriod',
                xtype: 'numberfield',
                fieldLabel: 'Backup Retention Period',
                labelWidth: 200,
                width: 280,
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

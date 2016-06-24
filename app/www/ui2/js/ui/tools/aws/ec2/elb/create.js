Scalr.regPage('Scalr.ui.tools.aws.ec2.elb.create', function (loadParams, moduleParams) {

    return Scalr.utils.Window({
        xtype: 'elbform',
        isModal: true,

        title: 'AWS &raquo; Elastic Load Balancer &raquo; Create',
        width: 600,

        cloudLocation: loadParams.cloudLocation,

        scalrOptions: {
            modalWindow: true
        },

        onCreate: function (form, response) {
            var loadBalancer = response.elb;

            if (!Ext.isEmpty(loadBalancer)) {
                Scalr.event.fireEvent('update', '/tools/aws/ec2/elb/create', loadBalancer);
            }

            form.close();
        },

        onCancel: function (form) {
            form.close();
        },

        listeners: {
            boxready: function (form) {
                form
                    .setReadOnly(false)
                    .applyCloudLocation(form.cloudLocation, true);

                form.setGovernanceDisabled(false);

                var vpcId = loadParams.vpcId;
                var placementField = form.down('[name=vpcId]');

                if (!Ext.isEmpty(vpcId)) {
                    // form is not masked during the store's loading
                    form.setLoading('');
                    placementField.getStore().load(function () {
                        form.setLoading(false);
                    });
                    placementField.setValue(vpcId);
                }

                placementField.setReadOnly(true);
            }
        }
    });
});

Ext.define('Scalr.ui.ElasticLoadBalancerForm', {
    extend: 'Ext.form.Panel',

    alias: 'widget.elbform',

    autoScroll: true,

    readOnly: false,

    isModal: false,

    cloudLocation: '',

    accountId: '',

    remoteAddress: '',

    onCreate: Ext.emptyFn,

    onDelete: Ext.emptyFn,

    onCancel: Ext.emptyFn,

    defaults: {
        xtype: 'fieldset',
        collapsible: true
    },

    fieldDefaults: {
        anchor: '100%',
        labelWidth: 160
    },

    getCloudLocation: function () {
        return this.cloudLocation;
    },

    hideCreateButton: function (hidden) {
        var me = this;

        me.down('#create').setVisible(!hidden);

        return me;
    },

    hideSaveButton: function (hidden) {
        var me = this;

        me.down('#save').setVisible(!hidden);

        return me;
    },

    hideDeleteButton: function (hidden) {
        var me = this;

        me.down('#delete').setVisible(!hidden);

        return me;
    },

    setFormLoading: function (loading) {
        var me = this;

        me.setLoading(!loading ? false : '');

        return me;
    },

    setReadOnly: function (readOnly) {
        var me = this;

        me.readOnly = readOnly;

        Ext.Array.each(me.query('fieldset'), function (fieldSet) {
            fieldSet.setReadOnly(readOnly);
        });

        me
            .hideCreateButton(readOnly)
            .hideSaveButton(!readOnly)
            .hideDeleteButton(!readOnly);

        return me;
    },

    clearListenersStore: function () {
        var me = this;

        me.down('#listeners').getStore().removeAll();

        return me;
    },

    applyListeners: function (listeners) {
        var me = this;

        var listenersStore = me.down('#listeners').getStore();
        listenersStore.removeAll();

        listenersStore.add(Ext.Array.map(listeners, function (item) {
            item.listener.policyNames = item.policyNames;
            return item.listener;
        }));

        listenersStore.commitChanges();

        return me;
    },

    applyStickinessPolicies: function (policies) {
        var me = this;

        var policiesStore = me.down('#stickinessPolicies').getStore();
        policiesStore.removeAll();
        policiesStore.loadData(policies);
        policiesStore.commitChanges();

        return me;
    },

    applyInstances: function (instances) {
        var me = this;

        var instancesFieldset = me.down('#instances');

        var instancesStore = instancesFieldset.down('#instancesGrid').getStore();
        instancesStore.removeAll();

        var instancesCountText = '0/0';

        if (!Ext.isEmpty(instances)) {
            instancesStore.loadData(instances);

            var inServiceInstancesCount = 0;

            Ext.Array.each(instances, function (instance) {
                if (instance.status === 'InService') {
                    inServiceInstancesCount++;
                }
            });

            instancesCountText = inServiceInstancesCount + '/' + (instances.length - inServiceInstancesCount);
        }

        instancesFieldset.setTitle('Instances (' + instancesCountText + ')');

        return me;
    },

    showDeregisterInstanceConfirm: function (instanceData, elbName) {
        var me = this;

        var instanceId = instanceData.instanceId;
        var state = instanceData.state;
        var description = instanceData.description;

        Scalr.Confirm({
            formWidth: 600,
            alignTop: true,
            winConfig: {
                autoScroll: false
            },
            scalrOptions: {
                modalWindow: true
            },
            type: 'delete',
            msg: 'Are you sure you want to remove Instance <b>' + instanceId +
                '</b> with status <i>' + state +
                '</i> from Load Balancer <b>' + elbName + '</b> ?',
            ok: 'Remove',
            closeOnSuccess: true,
            scope: this,
            success: function (formValues) {
                Scalr.Request({
                    processBox: {
                        type: 'delete',
                        msg: 'Removing instance...'
                    },
                    url: '/tools/aws/ec2/elb/xDeregisterInstance',
                    params: {
                        cloudLocation: me.getCloudLocation(),
                        elbName: elbName,
                        awsInstanceId: instanceId
                    },
                    success: function (response) {
                        var instanceId = response.instanceId;
                        var record = me.getForm().getRecord();
                        var instances = record.get('instances');
                        var removedInstance = Ext.Array.findBy(instances, function (instance) {
                            return instance.instanceId === instanceId;
                        });

                        record.set('instances', Ext.Array.remove(instances, removedInstance));

                        me.applyInstances(instances, elbName);

                        return true;
                    }
                });

                return true;
            }
        });

        return me;
    },

    deregisterInstance: function (awsInstanceId) {
        var me = this;

        var elbName = me.getForm().getRecord().get('name');

        Scalr.Request({
            processBox: {
                type: 'load'
            },
            url: '/tools/aws/ec2/elb/xGetInstanceHealth',
            params: {
                cloudLocation: me.getCloudLocation(),
                elbName: elbName,
                awsInstanceId: awsInstanceId
            },
            success: function (response) {
                if (!Ext.isEmpty(response.instanceId)) {
                    me.showDeregisterInstanceConfirm(response, elbName);
                }
            }
        });

        return me;
    },

    applyLoadBalancerData: function (data) {
        var me = this;

        var form = me.getForm();

        var values = Ext.merge({},
            data.healthCheck, {
            zones: data.availabilityZones,
            subnets: data.subnets,
            vpcId: data.vpcId
        });

        form.setValues(values);

        var placementField = me.down('[name=vpcId]');
        var placementStore = placementField.getStore();
        placementStore.load();

        var listeners = data.listenerDescriptions;
        var policies = data.policies;
        var instances = data.instances;
        var securityGroups = data.securityGroups;

        me
            .applyInstances(instances, data.loadBalancerName)
            .applyListeners(listeners)
            .applyStickinessPolicies(data.policies)
            .toggleStickinessPolicies()
            .setSecurityGroups(securityGroups)
            .setGovernanceDisabled(true);

        form.getRecord().set(Ext.apply(values, {
            listeners: listeners,
            policies: policies,
            instances: instances,
            securityGroups: securityGroups
        }));


        var vpcRecord = placementStore.findRecord('id', data.vpcId);

        if (!Ext.isEmpty(vpcRecord)) {
            placementField.setValue(vpcRecord);
        } else {
            placementField.setRawValue(data.vpcId);
            placementField.fireEvent('change', placementField, data.vpcId);
        }

        return me;
    },

    getLoadBalancerData: function (cloudLocation, elbName, callback) {
        var me = this;

        Scalr.Request({
            processBox: {
                type: 'load'
            },
            url: '/tools/aws/ec2/elb/xGetDetails',
            params: {
                cloudLocation: cloudLocation,
                elbName: elbName
            },
            success: function (response) {
                var loadBalancerData = response.elb;

                if (!Ext.isEmpty(loadBalancerData) && Ext.isFunction(callback)) {
                    callback.call(me, loadBalancerData);
                }
            }
        });

        return me;
    },

    applySecurityGroupsPolicy: function () {
        var me = this;

        var securityGroupsPolicy = me.refreshSecurityGroupsPolicy();

        me.down('#securityGroupsContainer').setGovernanceLabel();

        return me;
    },

    refreshSecurityGroupsPolicy: function () {
        var me = this;

        me.securityGroupsPolicy = function (params) {

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
                        (!Ext.isEmpty(policy.defaultGroups) ? ', and requires that you attach the following Security Groups to your ELB: ' + policy.defaultGroupsList : '') +
                        '.',
                    requiredGroupsMessage: 'A Security Group Policy is active in this Environment' +
                        (!Ext.isEmpty(policy.defaultGroups) ? ', and restricts you to the following Security Groups: ' + policy.defaultGroupsList : '') +
                        '.'
                });
            }

            return policy;

        }( Scalr.getGovernance('ec2', 'aws.elb_additional_security_groups') );

        return me.securityGroupsPolicy;
    },

    hideVpcPolicyIcon: function (hidden) {
        var me = this;

        me.down('fieldset').setGovernanceTitle(null, hidden);

        return me;
    },

    applyVpcPolicy: function (cloudLocation) {
        var me = this;

        var vpcPolicy = function (params) {

            var policy = {
                enabled: !Ext.isEmpty(params)
            };

            if (policy.enabled) {
                Ext.apply(policy, {
                    configured: true,
                    launchWithVpcOnly: !!params.value,
                    regions: Ext.Object.getKeys(params.regions),
                    vpcs: params.regions,
                    subnets: params.ids
                });
            }

            return policy;

        }( Scalr.getGovernance('ec2', 'aws.vpc') );

        var isPolicyEnabled = vpcPolicy.enabled;

        me.vpcPolicy = vpcPolicy;

        me.hideVpcPolicyIcon(!isPolicyEnabled);

        me.down('[name=vpcId]').allowBlank = !(isPolicyEnabled && vpcPolicy.launchWithVpcOnly);

        if (isPolicyEnabled) {
            var isVpcAllowed = !Ext.isEmpty(vpcPolicy.vpcs[cloudLocation]);

            if (!isVpcAllowed && vpcPolicy.launchWithVpcOnly) {
                return false;
            }
        }

        return true;
    },

    getVpcPolicy: function () {
        return this.vpcPolicy;
    },

    disableVpcPolicy: function (disabled) {
        var me = this;

        var policy = me.getVpcPolicy();
        var isEnabled = !policy.configured ? false : !disabled;

        policy.enabled = isEnabled;
        me.hideVpcPolicyIcon(!isEnabled);

        var placementField = me.down('[name=vpcId]');
        placementField.emptyText = policy.launchWithVpcOnly && !me.readOnly ? ' ' : 'EC2';
        placementField.applyEmptyText();
        return me;
    },

    isVpcPolicyEnabled: function () {
        return this.getVpcPolicy().enabled;
    },

    getSecurityGroupsPolicy: function () {
        return this.securityGroupsPolicy;
    },

    disableSecurityGroupsPolicy: function (disabled) {
        var me = this;

        me.getSecurityGroupsPolicy().enabled = !disabled;

        if (!disabled) {
            me.applySecurityGroupsPolicy();
        } else {
            me.down('#securityGroupsContainer').setGovernanceLabel();
        }

        return me;
    },

    isSecurityGroupsPolicyEnabled: function () {
        return this.getSecurityGroupsPolicy().enabled;
    },

    setGovernanceDisabled: function (disabled) {
        var me = this;

        me
            .disableVpcPolicy(disabled)
            .disableSecurityGroupsPolicy(disabled);

        return me;
    },

    isGovernanceEnabled: function () {
        var me = this;

        return me.isVpcPolicyEnabled() && me.isSecurityGroupsPolicyEnabled();
    },

    applyCloudLocation: function (cloudLocation, preventLoading) {
        preventLoading = true;
        //preventLoading = preventLoading || false;

        var me = this;

        me.cloudLocation = cloudLocation;

        var zonesStore = me.down('#zones').getStore();
        zonesStore.getProxy().params = {
            cloudLocation: cloudLocation
        };

        var placementField = me.down('[name=vpcId]');

        var placementStore = placementField.getStore();
        placementStore.getProxy().params = {
            cloudLocation: cloudLocation,
            serviceName: 'elb'
        };

        if (!preventLoading) {
            zonesStore.load();
            placementStore.load();
        }

        placementField.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString({
            cloudLocation: cloudLocation
        });

        return me.applyVpcPolicy(cloudLocation);
    },

    updateGovernanceHandler: function () {
        var me = this;

        me.applyVpcPolicy(me.cloudLocation);
        me.applySecurityGroupsPolicy();

        Scalr.CachedRequestManager.get().setExpiredByMask(
            me.down('[name=vpcId]').getStore().getProxy().url
        );

        return me;
    },

    setSecurityGroups: function (groups) {
        var me = this;

        var ids = Ext.Array.map(groups, function(group) {
            if (group.isModel) {
                return group.get('securityGroupId');
            }

            return Ext.isDefined(group.securityGroupId) ? group.securityGroupId : group.id;
        });

        var names = Ext.Array.map(groups, function(group) {
            if (group.isModel) {
                return group.get('name');
            }

            return Ext.isDefined(group.securityGroupName) ? group.securityGroupName : group.name;
        });

        me.down('#securityGroups').
            setSecurityGroupsIds(ids).
            setSecurityGroupsNames(names).
            setValue(
                Ext.Array.map(ids, function (id, index) {
                    var name = names[index] || '';

                    if (!Ext.isEmpty(id)) {
                        return '<span data-id=\'' + id +
                            '\' class=\'scalr-ui-rds-tagfield-sg-name\' style=\'cursor:pointer\'>' +
                            name + '</span>';
                    }

                    var warningTooltip = 'A Security Group Policy is active in this Environment, ';

                    if (name.indexOf('*') !== -1) {
                        warningTooltip += 'and requires that you attach Security Group matching to pattern <b>' + name + '</b> to your ELB.<br/>' +
                        'But there is NO or MORE THAN ONE Security group matching to pattern found.';
                    } else {
                        warningTooltip += 'and requires that you attach <b>' + name + '</b> Security Group to your ELB.\n' +
                        'But <b>' + name + '</b> does not exist in current VPC.';
                    }

                    return '<div data-qtip=\'' + warningTooltip + '\'' + ' >' +
                        '<img src=\'' + Ext.BLANK_IMAGE_URL +
                        '\' class=\'x-icon-warning\' style=\'vertical-align:middle;margin-right:6px\' />' +
                        name + '</div>';
                })
            );

        return me;
    },

    changeSecurityGroups: function () {
        var me = this;

        var cloudLocation = me.getCloudLocation();
        var vpcId = me.down('[name=vpcId]').getValue();
        var selectedSecurityGroups = me.down('#securityGroups').getSecurityGroups();
        var securityGroupsPolicy = me.refreshSecurityGroupsPolicy();

        Scalr.Confirm({
            formWidth: 950,
            alignTop: true,
            winConfig: {
                autoScroll: false
            },
            scalrOptions: {
                modalWindow: true
            },
            form: [{
                xtype: 'rdssgmultiselect',
                title: 'Add security groups to Elastic Load Balancer',
                listGroupsUrl: '/tools/aws/ec2/elb/xListSecurityGroups',
                limit: 5, //VPC ELB sg limit
                allowBlank: true,
                minHeight: 200,
                selection: selectedSecurityGroups,
                defaultVpcGroups: securityGroupsPolicy.defaultGroups,
                governanceWarning: securityGroupsPolicy.enabled && !securityGroupsPolicy.allowAddingGroups
                    ? securityGroupsPolicy.requiredGroupsMessage
                    : null,
                disableAddButton: securityGroupsPolicy.enabled && (!securityGroupsPolicy.allowAddingGroups || !Ext.isEmpty(securityGroupsPolicy.additionalGroupsList)),
                storeExtraParams: {
                    platform: 'ec2',
                    cloudLocation: cloudLocation,
                    filters: Ext.encode({
                        vpcId: vpcId,
                        considerGovernance: securityGroupsPolicy.enabled,
                        serviceName: 'elb'
                    })
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
                me.setSecurityGroups(
                    securityGroupForm.down('sgmultiselect').selection
                );

                return true;
            }
        });

        return me;
    },

    hasLoadBalancerListeners: function () {
        return this.down('#listeners').getStore().getCount() !== 0;
    },

    showEmptyListenersMessage: function () {
        Scalr.message.Error('Load Balancer must have at least one listener.');
    },

    createLoadBalancer: function () {
        var me = this;

        if (me.getForm().isValid()) {
            var listeners = [];

            me.down('#listeners').store.each(function(rec) {
                listeners.push([rec.get('protocol'), rec.get('loadBalancerPort'), rec.get('instancePort'), rec.get('sslCertificate')].join("#"));
            });

            if (listeners.length === 0) {
                me.showEmptyListenersMessage();
                return me;
            }

            var healthcheck = {
                target: me.down("[name='target']").getValue(),
                healthyThreshold: me.down("[name='healthythreshold']").getValue(),
                interval: me.down("[name='interval']").getValue(),
                timeout: me.down("[name='timeout']").getValue(),
                unhealthyThreshold: me.down("[name='unhealthythreshold']").getValue()
            };

            Scalr.Request({
                processBox: {
                    type: 'save'
                },
                params: {
                    listeners: Ext.encode(listeners),
                    healthcheck: Ext.encode(healthcheck),
                    cloudLocation: me.cloudLocation
                },
                form: me.getForm(),
                url: '/tools/aws/ec2/elb/xCreate',
                success: function (response) {
                    me.fireEvent('create', me, response);
                }
            });
        }

        return me;
    },

    getRecordData: function (listeners) {
        return Ext.Array.map(listeners, function (record) {
            return record.getData();
        });
    },

    getListenersSubmitData: function () {
        var me = this;

        var listenersStore = me.down('#listeners').getStore();

        var addedListeners = listenersStore.getNewRecords();

        var modifiedListeners = Ext.Array.difference(
            listenersStore.getModifiedRecords(),
            addedListeners
        );

        return {
            create: me.getRecordData(addedListeners),
            update: me.getRecordData(modifiedListeners),
            remove: me.getRecordData(listenersStore.getRemovedRecords())
        };
    },

    getPoliciesSubmitData: function () {
        var me = this;

        var policiesStore = me.down('#stickinessPolicies').getStore();

        return {
            create: me.getRecordData(policiesStore.getNewRecords()),
            remove: me.getRecordData(policiesStore.getRemovedRecords())
        };
    },

    saveLoadBalancer: function () {
        var me = this;

        if (!me.hasLoadBalancerListeners()) {
            me.showEmptyListenersMessage();
            return me;
        }

        Scalr.Request({
            processBox: {
                type: 'save'
            },
            params: {
                cloudLocation: me.getCloudLocation(),
                elbName: me.getForm().getRecord().get('name'),
                listeners: Ext.encode(me.getListenersSubmitData()),
                policies: Ext.encode(me.getPoliciesSubmitData())
            },
            url: '/tools/aws/ec2/elb/xSave',
            success: function (response) {
                var record = me.getForm().getRecord();
                var listeners = response.listenerDescriptions;

                if (!Ext.isEmpty(listeners)) {
                    me.applyListeners(listeners);
                    record.set('listeners', listeners);
                }

                var policies = response.policies;

                if (!Ext.isEmpty(policies)) {
                    me.applyStickinessPolicies(policies);
                    record.set('policies', policies);
                }
            }
        });

        return me;
    },

    toggleStickinessPolicies: function () {
        var me = this;

        var isVisible = false;

        me.down('#listeners').getStore().each(function (record) {
            var protocol = record.get('protocol');
            if (protocol === 'HTTP' || protocol === 'HTTPS') {
                isVisible = true;
                return false;
            }
        });

        me.down('#policiesFieldSet').setVisible(isVisible);

        return me;
    },

    addNewListener: function () {
        var me = this;

        var listenersStore = me.down('#listeners').getStore();
        var busyPorts = listenersStore.collect('loadBalancerPort');

        Scalr.Confirm({
            form: [{
                xtype: 'container',
                cls: 'x-container-fieldset',
                layout: 'anchor',
                defaults: {
                    anchor: '100%',
                    labelWidth: 140
                },
                items: [{
                    xtype: 'combo',
                    name: 'protocol',
                    fieldLabel: 'Protocol',
                    editable: false,
                    store: [ 'TCP', 'HTTP', 'SSL', 'HTTPS' ],
                    queryMode: 'local',
                    allowBlank: false,
                    listeners: {
                        change: function (field, value) {
                            var isProtocolSecure = value === 'SSL' || value === 'HTTPS';

                            field.next('[name=sslCertificate]')
                                .setDisabled(!isProtocolSecure)
                                .setVisible(isProtocolSecure);
                        }
                    }
                }, {
                    xtype: 'numberfield',
                    name: 'loadBalancerPort',
                    fieldLabel: 'Load balancer port',
                    allowBlank: false,
                    minValue: 1,
                    minText: 'Valid Load Balancer ports are one (1) through 65535',
                    maxValue: 65535,
                    maxText: 'Valid Load Balancer ports are one (1) through 65535',
                    validator: function (value) {
                        value = parseInt(value);

                        if (Ext.Array.contains(busyPorts, value)) {
                            return 'This Load Balancer Port is already in use';
                        }

                        return true;
                    }
                }, {
                    xtype: 'numberfield',
                    name: 'instancePort',
                    fieldLabel: 'Instance port',
                    allowBlank: false,
                    minValue: 1,
                    minText: 'Valid instance ports are one (1) through 65535',
                    maxValue: 65535,
                    maxText: 'Valid instance ports are one (1) through 65535'
                }, {
                    xtype: 'combo',
                    name: 'sslCertificate',
                    fieldLabel: 'SSL Certificate',
                    hidden: true,
                    disabled: true,
                    editable: false,
                    allowBlank: false,
                    store: {
                        autoLoad: true,
                        fields: [ 'name', 'path', 'arn', 'id', 'upload_date' ],
                        proxy: {
                            type: 'ajax',
                            reader: {
                                type: 'json',
                                rootProperty: 'data'
                            },
                            url: '/tools/aws/iam/servercertificates/xListCertificates'
                        }
                    },
                    valueField: 'arn',
                    displayField: 'name'
                }]
            }],
            ok: 'Add',
            title: 'Add new listener',
            formValidate: true,
            closeOnSuccess: true,
            scope: this,
            success: function (formValues) {
                if (listenersStore.findBy(function (record) {
                    if (
                        record.get('protocol') == formValues.protocol &&
                        record.get('loadBalancerPort') == formValues.loadBalancerPort &&
                        record.get('instancePort') == formValues.instancePort
                    ) {
                        Scalr.message.Error('Such listener already exists');
                        return true;
                    }
                }) == -1) {
                    listenersStore.add(formValues);
                    if (me.readOnly) {
                        me.toggleStickinessPolicies();
                    }
                    return true;
                } else {
                    return false;
                }
            }
        });

        return me;
    },

    addNewPolicy: function () {
        var me = this;

        var stickinessPoliciesStore = me.down('#stickinessPolicies').getStore();
        var existingNames = stickinessPoliciesStore.collect('policyName');

        Scalr.Confirm({
            title: 'Create Stickiness Policies',
            formSimple: true,
            form: [{
                xtype: 'hiddenfield',
                name: 'cloudLocation',
                value: me.getCloudLocation()
            }, {
                xtype: 'combo',
                itemId: 'polis',
                name: 'policyType',
                editable: false,
                fieldLabel: 'Cookie Type',
                queryMode: 'local',
                store: [
                    ['AppCookie', 'App cookie'],
                    ['LbCookie', 'Lb cookie']
                ],
                value: 'AppCookie',
                listeners: {
                    change: function(field, value) {
                        var nextContainer = this.next('container');
                        if (value == "LbCookie") {
                            nextContainer.down('[name="cookieSettings"]').labelEl.update("Exp. period:");
                            nextContainer.down('[name="Sec"]').show();
                        } else {
                            nextContainer.down('[name="cookieSettings"]').labelEl.update("Cookie Name:");
                            nextContainer.down('[name="Sec"]').hide();
                        }
                    }
                }
            }, {
                xtype: 'textfield',
                name: 'policyName',
                fieldLabel: 'Name',
                allowBlank: false,
                validator: function (value) {
                    return !Ext.Array.contains(existingNames, value)
                        ? true
                        : 'Such Stickiness Policy already exists';
                }
            }, {
                xtype: 'container',
                layout: {
                    type: 'hbox'
                },
                items: [{
                    xtype: 'textfield',
                    name: 'cookieSettings',
                    fieldLabel: 'Cookie Name',
                    allowBlank: false,
                    labelWidth: 100,
                    width: 365
                }, {
                    margin: '0 0 0 2',
                    xtype: 'displayfield',
                    name: 'Sec',
                    value: 'sec',
                    hidden: true
                }]

            }],
            formValidate: true,
            success: function (formValues) {
                stickinessPoliciesStore.add({
                    policyType: formValues.policyType,
                    policyName: formValues.policyName,
                    cookieSettings: formValues.cookieSettings
                });
            }

        });

        return me;
    },

    removePolicy: function (recordId, policyName) {
        var me = this;

        var policiesStore = me.down('#stickinessPolicies').getStore();

        policiesStore.remove(
            policiesStore.getById(recordId)
        );

        var listenerRecord = me.down('#listeners').getStore()
            .findRecord('policyNames', policyName);

        if (!Ext.isEmpty(listenerRecord)) {
            listenerRecord.set('policyNames', '');
        }

        return me;
    },

    initEvents: function () {
        var me = this;

        me.on({
            create: me.onCreate,
            deleteloadbalancer: me.onDelete,
            cancel: me.onCancel
        });
    },

    initComponent: function () {
        var me = this;

        me.callParent();

        me.vpcPolicy = {
            enabled: false
        };

        me.securityGroupsPolicy = {
            enabled: false
        };

        me.add([{
            title: 'New Load Balancer',
            itemId: 'details',
            collapsible: false,

            defaults: {
                xtype: 'displayfield'
            },

            setGovernanceTitle: function (prefix, hideIcon) {
                hideIcon = hideIcon || !me.isVpcPolicyEnabled();

                prefix = !Ext.isEmpty(prefix) ? prefix : (!me.readOnly ? 'New' : 'Edit');

                this.setTitle(
                    prefix + ' Load Balancer' + (!hideIcon
                        ? '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' +
                            Ext.String.htmlEncode(Scalr.strings.awsLoadBalancerVpcEnforced) +
                            '" class="x-icon-governance" />'
                        : ''
                    )
                );

                return this;
            },

            setReadOnly: function (readOnly) {
                var me = this;

                me.setGovernanceTitle(!readOnly ? 'New' : 'Edit');

                Ext.Array.each(me.query('field[itemId!=securityGroups]'), function (field) {
                    field.setReadOnly(readOnly);

                    if (field.getXType() === 'displayfield') {
                        field.setVisible(readOnly);
                    }
                });

                me.down('[name=vpcId]').setFieldLabel(
                    !readOnly ? 'Create LB Inside' : 'Placement'
                );

                me.down('[name="scheme"]').hide();

                me.down('[name="crossLoadBalancing"]').setVisible(!readOnly);

                me.down('#securityGroupsContainer').setVisible(
                    !!me.down('[name=vpcId]').getValue()
                );

                me.down('#changeSecurityGroups').setVisible(!readOnly);

                return me;
            },

            items: [{
                xtype: 'textfield',
                fieldLabel: 'Name',
                name: 'name',
                regex: /^[-a-zA-Z0-9]+$/,
                regexText: 'Load Balancer names must only contain alphanumeric characters or dashes.'
            }, {
                xtype: 'combo',
                fieldLabel: 'Create LB Inside',
                name: 'vpcId',
                emptyText: 'EC2',
                editable: false,
                hideInputOnReadOnly: true,
                queryCaching: false,
                clearDataBeforeQuery: true,
                valueField: 'id',
                displayField: 'name',
                pickerAlign: 'tr-br',
                plugins: [{
                    ptype: 'comboaddnew',
                    pluginId: 'comboaddnew',
                    url: '/tools/aws/vpc/create'
                }],
                store: {
                    fields: [ 'id', 'name', 'defaultSecurityGroups' ],
                    proxy: {
                        type: 'cachedrequest',
                        url: '/platforms/ec2/xGetVpcList',
                        root: 'vpc',
                        prependData: [{
                            id: 0,
                            name: 'EC2'
                        }]
                    }
                },
                listConfig: {
                    cls: 'x-boundlist-nowrap'
                },
                listeners: {
                    afterrender: function (field) {
                        field.getStore().addFilter({
                            id: 'governancePolicyFilter',
                            filterFn: function (record) {
                                var vpcPolicy = me.getVpcPolicy();

                                if (!vpcPolicy.enabled) {
                                    return true;
                                }

                                if (record.get('name') === 'EC2') {
                                    return !vpcPolicy.launchWithVpcOnly;
                                }

                                var currentRegion = vpcPolicy.vpcs[me.getCloudLocation()];

                                if (Ext.isEmpty(currentRegion)) {
                                    return false;
                                }

                                var allowedVpcs = currentRegion.ids;

                                return !Ext.isEmpty(allowedVpcs)
                                    ? Ext.Array.contains(allowedVpcs, record.get('id'))
                                    : true;
                            }
                        });
                    },
                    addnew: function (item) {
                        var me = this;

                        var proxy = me.getStore().getProxy();

                        Scalr.CachedRequestManager.get().setExpired({
                            url: proxy.url,
                            params: proxy.params
                        });
                    },
                    change: function (field, value) {
                        var securityGroupsContainer = me.down('#securityGroupsContainer');
                        securityGroupsContainer.setVisible(!!value);

                        securityGroupsContainer.down('#securityGroups').setDisabled(!value);

                        var subnetsField = me.down('#subnets');

                        if (!!value) {
                            me.down('#zones').hide().disable();

                            if (!me.readOnly) {
                                var vpcRecord = field.getStore().findRecord('id', value);

                                if (!Ext.isEmpty(vpcRecord)) {
                                    me.setSecurityGroups(
                                        vpcRecord.get('defaultSecurityGroups') || []
                                    );
                                }

                                me.down('[name="scheme"]').show().enable();
                            }

                            var vpcLimits = Scalr.getGovernance('ec2', 'aws.vpc');

                            subnetsField.reset();
                            subnetsField.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + me.cloudLocation + '&vpcId=' + value;
                            subnetsField.getPlugin('comboaddnew').setDisabled(vpcLimits && vpcLimits['ids'] && Ext.isArray(vpcLimits['ids'][value]));
                            Ext.apply(subnetsField.store.getProxy(), {
                                params: {
                                    cloudLocation: me.cloudLocation,
                                    vpcId: value,
                                    extended: 1
                                },
                                filterFn: vpcLimits && vpcLimits['ids'] && vpcLimits['ids'][value] ? subnetsField.subnetsFilterFn : null,
                                filterFnScope: subnetsField
                            });

                            subnetsField.requireSameSubnetType = false;
                            subnetsField.show().enable();
                            subnetsField.clearInvalid();
                            subnetsField.allowBlank = false;

                            var record = me.getForm().getRecord();
                            if (!Ext.isEmpty(record)) {
                                subnetsField.getStore().load();
                                subnetsField.setValue(record.get('subnets'));
                            }
                        } else {
                            me.down('#zones').show().enable();
                            me.down('[name="scheme"]').hide().disable();
                            subnetsField.hide().disable();
                            subnetsField.allowBlank = true;
                            subnetsField.requireSameSubnetType = true;
                        }
                    }
                },
                setCloudLocation: function (cloudLocation) {
                    var proxy = this.store.proxy,
                        disableAddNewPlugin = false,
                        vpcLimits = Scalr.getGovernance('ec2', 'aws.vpc');
                    this.reset();
                    this.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + cloudLocation;
                    proxy.params = {
                        serviceName: 'elb',
                        cloudLocation: cloudLocation
                    };
                    delete proxy.data;
                    this.setReadOnly(false, false);
                    if (Ext.isObject(vpcLimits)) {
                        this.toggleIcon('governance', true);
                        this.allowBlank = vpcLimits['value'] == 0;
                        if (vpcLimits['regions'] && vpcLimits['regions'][cloudLocation]) {
                            if (vpcLimits['regions'][cloudLocation]['ids'] && vpcLimits['regions'][cloudLocation]['ids'].length > 0) {
                                var vpcList = Ext.Array.map(vpcLimits['regions'][cloudLocation]['ids'], function(vpcId) {
                                    return {
                                        id: vpcId,
                                        name: vpcId
                                    };
                                });
                                if (vpcLimits['value'] == 0) {
                                    vpcList.unshift({
                                        id: 0,
                                        name: 'EC2'
                                    });
                                }
                                proxy.data = vpcList;
                                this.store.load();
                                disableAddNewPlugin = true;
                                if (vpcLimits['value'] == 1) {
                                    this.setValue(this.store.first());
                                }
                            }
                        }
                    }
                    this.getPlugin('comboaddnew').setDisabled(disableAddNewPlugin);
                }

            }, {
                fieldLabel: 'DNS name',
                name: 'dnsName'
            }, {
                fieldLabel: 'Created At',
                name: 'dtcreated',
                render: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }, {
                xtype: 'vpcsubnetfield',
                itemId: 'subnets',
                name: 'subnets',
                fieldLabel: 'Subnets',
                hidden: true,
                disabled: true,
                requireSameSubnetType: true,
                getSubmitValue: function () {
                    return Ext.encode(this.getValue());
                },
                listeners: {
                    afterrender: function (field) {
                        field.getStore().addFilter({
                            id: 'governancePolicyFilter',
                            filterFn: function (record) {
                                var vpcPolicy = me.getVpcPolicy();

                                if (!vpcPolicy.enabled) {
                                    return true;
                                }

                                var subnetsPolicy = vpcPolicy.subnets[
                                    me.down('[name=vpcId]').getValue()
                                ];

                                if (Ext.isEmpty(subnetsPolicy)) {
                                    return true;
                                }

                                var policy = subnetsPolicy;

                                if (Ext.isArray(policy)) {
                                    return Ext.Array.contains(policy, record.get('id'));
                                }

                                policy = policy === 'full' ? 'public' : 'private';

                                return policy === record.get('type');
                            }
                        });
                    }
                }
            }, {
                xtype: 'tagfield',
                fieldLabel: 'Availability Zones',
                itemId: 'zones',
                name: 'zones',
                valueField: 'id',
                displayField: 'name',
                allowBlank: false,
                queryCaching: false,
                clearDataBeforeQuery: true,
                store: {
                    fields: ['id', 'name', 'state'],
                    proxy: {
                        type: 'cachedrequest',
                        url: '/platforms/ec2/xGetAvailZones'
                    }
                },
                listeners: {
                    beforeselect: function(comp, record, index) {
                        if (comp.isExpanded) {
                            var result = true;
                            if (record.get('state') !== 'available') {
                                result = false;
                            }
                            return result;
                        }
                    }
                },
                getSubmitValue: function () {
                    return Ext.encode(this.getValue());
                }
            }, {
                xtype: 'fieldcontainer',
                itemId: 'securityGroupsContainer',
                fieldLabel: 'Security groups',
                layout: {
                    type: 'hbox'
                },
                setGovernanceLabel: function () {
                    var field = this;
                    var securityGroupsPolicy = me.getSecurityGroupsPolicy();

                    field.setFieldLabel('Security Groups' + (!securityGroupsPolicy.enabled ? '' : '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL +
                        '" class="x-icon-governance" style="margin-top:-4px" data-qtip="' +
                        securityGroupsPolicy.enabledPolicyMessage +
                        '" />'
                    ));

                    return field;
                },
                items: [{
                    xtype: 'taglistfield',
                    name: 'securityGroups',
                    itemId: 'securityGroups',
                    allowBlank: false,
                    disabled: true,
                    flex: 1,
                    readOnly: true,
                    scrollable: true,
                    defaultGroups: [],
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
                    setDefaultGroups: function (securityGroups) {
                        this.defaultGroups = securityGroups;
                    },
                    getDefaultGroups: function () {
                        return this.defaultGroups;
                    },
                    getSubmitValue: function () {
                        return Ext.encode(this.getSecurityGroups());
                    },
                    listeners: {
                        afterrender: {
                            fn: function (field) {
                                field.getEl().on('click', function (event, target) {
                                    target = Ext.get(target);

                                    if (target.hasCls('scalr-ui-rds-tagfield-sg-name')) {

                                        Scalr.Request({
                                            processBox: {
                                                type: 'load'
                                            },
                                            url: '/security/groups/xGetGroupInfo',
                                            params: {
                                                platform: 'ec2',
                                                cloudLocation: me.getCloudLocation(),
                                                securityGroupId: target.getAttribute('data-id')
                                            },
                                            success: function (response) {
                                                Scalr.Confirm({
                                                    formWidth: 950,
                                                    formLayout: 'fit',
                                                    alignTop: true,
                                                    winConfig: {
                                                        autoScroll: false,
                                                        layout: 'fit'
                                                    },
                                                    form: [{
                                                        xtype: 'sgeditor',
                                                        vpcIdReadOnly: true,
                                                        accountId: me.accountId,
                                                        remoteAddress: me.remoteAddress,
                                                        listeners: {
                                                            afterrender: function () {
                                                                this.setValues(response);
                                                            }
                                                        }
                                                    }],
                                                    ok: 'Save',
                                                    closeOnSuccess: true,
                                                    scope: me,
                                                    success: function (formValues, form) {
                                                        var win2 = form.up('#box'),
                                                            values = win2.down('sgeditor').getValues();
                                                        if (values !== false) {
                                                            values['returnData'] = true;
                                                            Scalr.Request({
                                                                processBox: {
                                                                    type: 'save'
                                                                },
                                                                url: '/security/groups/xSave',
                                                                params: values,
                                                                success: function (data) {
                                                                    win2.destroy();
                                                                }
                                                            });
                                                        }
                                                    }
                                                });
                                            }
                                        });
                                    }
                                });
                            },

                            single: true
                        }
                    }
                }, {
                    xtype: 'button',
                    itemId: 'changeSecurityGroups',
                    text: 'Change',
                    width: 80,
                    margin: '0 0 0 12',
                    handler: function () {
                        me.changeSecurityGroups();
                    }
                }]
            }, {
                xtype: 'checkbox',
                name: 'scheme',
                fieldLabel: 'Create an internal Load Balancer',
                labelWidth: 245,
                inputValue: 'internal',
                hidden: true,
                disabled: true
            }, {
                xtype: 'checkbox',
                name: 'crossLoadBalancing',
                fieldLabel: 'Enable Cross-Zone Load Balancing',
                labelWidth: 245,
                inputValue: true,
                uncheckedValue: false,
                value: false,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: {
                        id: 'info',
                        tooltip: 'Cross-Zone Load Balancing distributes traffic ' +
                            'evenly across all your back-end instances in all Availability Zones.'
                    }
                }]
            }]
        }, {
            title: 'instances',
            itemId: 'instances',
            setReadOnly: function (readOnly) {
                var me = this;

                me.setVisible(readOnly).setDisabled(!readOnly);

                return me;
            },
            items: [{
                xtype: 'fieldcontainer',
                items: [{
                    xtype: 'grid',
                    itemId: 'instancesGrid',
                    disableSelection: true,
                    margin: '12 0 0 0',
                    maxHeight: 500,
                    store: {
                        proxy: 'object',
                        fields: [ 'instanceId', 'availabilityZone', 'status' ]
                    },
                    viewConfig: {
                        emptyText: 'There are no instances registered to this Load Balancer.',
                        deferEmptyText: false
                    },
                    columns: [{
                        header: 'Instance ID',
                        dataIndex: 'instanceId',
                        xtype: 'templatecolumn',
                        tpl: '<a href="#/servers?cloudServerId={instanceId}">{instanceId}</a>',
                        flex: 1,
                        sortable: false
                    }, {
                        header: 'Availability Zone',
                        dataIndex: 'availabilityZone',
                        flex: 0.7,
                        sortable: false
                    }, {
                        xtype: 'statuscolumn',
                        header: 'Status',
                        statustype: 'instancehealth',
                        dataIndex: 'status',
                        minWidth: 140,
                        sortable: true
                    }, {
                        xtype: 'optionscolumn',
                        hidden: !Scalr.isAllowed('AWS_ELB', 'manage'),
                        menu: [{
                            iconCls: 'x-menu-icon-delete',
                            text: 'Remove from Load Balancer',
                            showAsQuickAction: true,
                            menuHandler: function (data) {
                                me.deregisterInstance(data.instanceId);
                            }
                        }]
                    }]
                }]
            }]
        }, {
            title: 'Healthcheck',
            itemId: 'healthcheck',
            defaults: {
                xtype: 'numberfield',
                maxWidth: 320
            },
            setReadOnly: function (readOnly) {
                var me = this;

                if (!readOnly) {
                    me.expand();
                }

                Ext.Array.each(me.query('field'), function (field) {
                    field.setReadOnly(readOnly);
                });

                return me;
            },
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Target',
                maxWidth: '100%',
                name: 'target',
                allowBlank: false,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: {
                        id: 'info',
                        tooltip:
                            'The instance being checked. The protocol is either TCP or HTTP. The range of valid ports is one (1) through 65535.<br />' +
                            'Notes: TCP is the default, specified as a TCP: port pair, for example "TCP:5000".' +
                            'In this case a healthcheck simply attempts to open a TCP connection to the instance on the specified port.' +
                            'Failure to connect within the configured timeout is considered unhealthy.<br />' +
                            'For HTTP, the situation is different. HTTP is specified as a "HTTP:port/PathToPing" grouping, for example "HTTP:80/weather/us/wa/seattle". In this case, a HTTP GET request is issued to the instance on the given port and path. Any answer other than "200 OK" within the timeout period is considered unhealthy.<br />' +
                            'The total length of the HTTP ping target needs to be 1024 16-bit Unicode characters or less.'
                    }
                }]
            }, {
                fieldLabel: 'Timeout',
                name: 'timeout',
                value: 5,
                minValue: 2,
                maxValue: 60,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: {
                        id: 'info',
                        tooltip: 'Amount of time (in seconds) during which no response means ' +
                            'a failed health probe. <br />The default is 5 seconds and a valid ' +
                            'value must be between 2 seconds and 60 seconds. ' +
                            'Also, the timeout value must be less than the Interval value.'
                    }
                }]
            }, {
                fieldLabel: 'Interval',
                name: 'interval',
                value: 30,
                minValue: 5,
                maxValue: 600,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: {
                        id: 'info',
                        tooltip: 'The approximate interval (in seconds) between health checks of ' +
                            'an individual instance.<br />The default is 30 seconds and a valid interval ' +
                            'must be between 5 seconds and 600 seconds. ' +
                            'Also, the interval value must be greater than the Timeout value'
                    }
                }]
            }, {
                fieldLabel: 'Unhealthy Threshold',
                name: 'unhealthythreshold',
                value: 5,
                minValue: 2,
                maxValue: 10,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: {
                        id: 'info',
                        tooltip:
                            'The number of consecutive health probe failures that move the instance to ' +
                            'the unhealthy state.<br />The default is 5 and a valid value lies between 2 and 10.'
                    }
                }]
            }, {
                fieldLabel: 'Healthy Threshold',
                name: 'healthythreshold',
                value: 3,
                minValue: 2,
                maxValue: 10,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: {
                        id: 'info',
                        tooltip:
                            'The number of consecutive health probe successes required ' +
                            'before moving the instance to the Healthy state.<br />The default is ' +
                            '3 and a valid value lies between 2 and 10.'
                    }
                }]
            }]
        }, {
            title: 'Listeners',

            setReadOnly: function (readOnly) {
                var me = this;

                if (!readOnly) {
                    me.expand();
                }

                return me;
            },

            items: [{
                xtype: 'grid',
                itemId: 'listeners',
                cls: 'x-grid-no-hilighting',
                disableSelection: true,
                store: {
                    proxy: 'object',
                    fields: [
                        'protocol',
                        'sslCertificate', {
                            name: 'loadBalancerPort',
                            type: 'int'
                        }, {
                            name: 'instancePort',
                            type: 'int'
                        }, {
                            name: 'ports',
                            convert: function (value, record) {
                                return record.get('loadBalancerPort') + ' / ' + record.get('instancePort');
                            }
                        }
                    ]
                },
                plugins: {
                    ptype: 'gridstore'
                },

                viewConfig: {
                    emptyText: 'No listeners defined',
                    deferEmptyText: false
                },

                columns: [{
                    header: 'Protocol',
                    width: 105,
                    sortable: true,
                    dataIndex: 'protocol'
                }, {
                    header: 'LB / Instance ports',
                    width: 150,
                    sortable: false,
                    dataIndex: 'ports'
                }, /*{
                    header: 'LB port',
                    flex: 1,
                    sortable: false,
                    dataIndex: 'loadBalancerPort'
                }, {
                    header: 'Instance port',
                    flex: 1,
                    sortable: false,
                    dataIndex: 'instancePort'
                },*/ {
                    xtype: 'templatecolumn',
                    header: 'SSL certificate',
                    flex: 1,
                    sortable: false,
                    dataIndex: 'sslCertificate',
                    tpl: '<tpl if="sslCertificate">{sslCertificate}<tpl else>&mdash;</tpl>'
                }, {
                    text: 'Stickiness Policy',
                    flex: 1,
                    sortable: false,
                    dataIndex: 'policyNames',
                    renderer: function (value, meta, record) {
                        return !Ext.isEmpty(value) ? value : '&mdash;';
                    }
                }, {
                    xtype: 'optionscolumn',
                    hidden: !Scalr.isAllowed('AWS_ELB', 'manage'),
                    menu: [{
                        iconCls: 'x-menu-icon-edit',
                        text: 'Settings',
                        showAsQuickAction: true,
                        getVisibility: function (data) {
                            var protocol = data.protocol;
                            return me.readOnly && (protocol !== 'TCP' && protocol !== 'SSL');
                        },
                        menuHandler: function (data) {
                            Scalr.Confirm({
                                title: 'Create new parameter group',
                                formSimple: true,
                                formWidth: 500,
                                form: [{
                                    xtype: 'combo',
                                    name: 'policyName',
                                    store: {
                                        proxy: {
                                            type: 'ajax',
                                            reader: 'json',
                                            writer: 'json'
                                        },
                                        fields: [ 'policyName', 'description' ],
                                        data: [{
                                            policyName: '',
                                            description: 'Do not use session stickiness on this ELB port'
                                        }]
                                    },
                                    editable: false,
                                    fieldLabel: 'Location',
                                    queryMode: 'local',
                                    valueField: 'policyName',
                                    displayField: 'description',
                                    value: data.policyNames || ''
                                }],
                                scope: this,
                                success: function (formValues) {
                                    var listenerStore = me.down('#listeners').getStore();
                                    var rowIndex = listenerStore.find('loadBalancerPort', data.loadBalancerPort);

                                    listenerStore.getAt(rowIndex).set('policyNames', formValues.policyName || '');
                                },
                                listeners: {
                                    boxready: function(form) {
                                        var store = form.down('combo').getStore();

                                        Ext.each(me.down('#stickinessPolicies').getStore().getRange(), function(record) {
                                            store.add({
                                                policyName: record.get('policyName'),
                                                description: record.get('policyName')
                                            });
                                        });
                                    }
                                },

                            });
                        }
                    },{
                        iconCls: 'x-menu-icon-delete',
                        text: 'Delete',
                        showAsQuickAction: true,
                        menuHandler: function (data) {
                            var listenersStore = me.down('#listeners').getStore();

                            listenersStore.remove(
                                listenersStore.getById(data.id)
                            );

                            if (me.readOnly) {
                                me.toggleStickinessPolicies();
                            }
                        }
                    }]
                }],

                dockedItems: [{
                    xtype: 'toolbar',
                    dock: 'top',
                    ui: 'simple',
                    padding: '0 0 15',
                    hidden: !Scalr.isAllowed('AWS_ELB', 'manage'),
                    items: [{
                        xtype: 'tbfill'
                    }, {
                        text: 'Add listener',
                        cls: 'x-btn-green',
                        handler: function() {
                            me.addNewListener();
                        }
                    }]
                }]
            }]
        }, {
            title: 'Stickiness Policies',
            itemId: 'policiesFieldSet',

            setReadOnly: function (readOnly) {
                var fieldSet = this;

                //fieldSet.setDisabled(!readOnly);

                if (!readOnly) {
                    fieldSet.hide();
                    return fieldSet;
                }

                me.toggleStickinessPolicies();

                return fieldSet;
            },

            items: [{
                xtype: 'grid',
                itemId: 'stickinessPolicies',
                plugins: {
                    ptype: 'gridstore'
                },
                viewConfig: {
                    deferEmptyText: false,
                    emptyText: 'No Stickiness Policies found'
                },
                disableSelection: true,
                columns: [{
                    text: 'Type',
                    width: 120,
                    dataIndex: 'policyType'
                }, {
                    flex: 1,
                    text: 'Name',
                    dataIndex: 'policyName'
                }, {
                    flex: 1,
                    text: 'Cookie name / Exp. period',
                    sortable: false,
                    dataIndex: 'cookieSettings'
                }, {
                    xtype: 'optionscolumn',
                    hidden: !Scalr.isAllowed('AWS_ELB', 'manage'),
                    menu: [{
                        iconCls: 'x-menu-icon-delete',
                        text: 'Delete',
                        showAsQuickAction: true,
                        menuHandler: function (data) {
                            me.removePolicy(data.id, data.policyName);
                        }
                    }]
                }],
                store: {
                    fields: [
                        { name: 'policyType' },
                        { name: 'policyName' },
                        { name: 'cookieSettings' }
                    ]
                },
                dockedItems: [{
                    xtype: 'toolbar',
                    dock: 'top',
                    ui: 'simple',
                    padding: '0 0 15',
                    hidden: !Scalr.isAllowed('AWS_ELB', 'manage'),
                    items: [/*{
                        xtype: 'component',
                        html: '<div style="padding: 0 0 0 32px; margin-bottom: 0" class="x-fieldset-subheader">' + '<span>Stickiness policies</span>' + '<img style="margin-left: 6px" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-info" data-qclickable="1" data-qtip=\'' + '' + '\' />' + '</div>'
                    }, */{
                        xtype: 'tbfill'
                    }, {
                        text: 'Add policy',
                        cls: 'x-btn-green',
                        handler: function () {
                            me.addNewPolicy();
                        }
                    }]
                }]
            }]
        }]);

        me.addDocked([{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            hidden: !Scalr.isAllowed('AWS_ELB', 'manage'),
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            maxWidth: 1100,
            defaults: {
                xtype: 'button',
                flex: 1,
                maxWidth: 140
            },
            items: [{
                text: 'Save',
                itemId: 'save',
                hidden: me.isModal,
                handler: function () {
                    me.saveLoadBalancer();
                }
            }, {
                text: 'Create',
                itemId: 'create',
                hidden: me.isModal,
                handler: function () {
                    me.createLoadBalancer();
                }
            }, {
                text: 'Cancel',
                handler: function () {
                    me.fireEvent('cancel', me);
                }
            }, {
                itemId: 'delete',
                cls: 'x-btn-red',
                text: 'Delete',
                hidden: me.isModal,
                handler: function () {
                    me.fireEvent('deleteloadbalancer', me);
                }
            },]
        }]);
    }
});

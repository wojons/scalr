Scalr.regPage('Scalr.ui.tools.aws.rds.clusters.edit', function (loadParams, moduleParams) {

    var cloudLocation = loadParams.cloudLocation;
    var instance = moduleParams.cluster;
    var requestsCount = 0;

    var securityGroupsPolicy = function (params) {

        var policy = {
            enabled: !Ext.isEmpty(params)
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

    }( Scalr.getGovernance('ec2', 'aws.rds_additional_security_groups') );

    var form = Ext.create('Ext.form.Panel', {

        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instances &raquo; Modify DB CLuster',
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

        modifyDbCluster: function (params) {
            var me = this,
                baseForm = me.getForm();

            me.prepareMaintenanceParams();

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

                securityGroups.enable();

                return false;
            }

            params = Ext.isObject(params) ? params : {};
            params.cloudLocation = cloudLocation;

            Scalr.Request({
                processBox: {
                    type: 'save',
                    msg: 'Modifying ...'
                },
                url: '/tools/aws/rds/clusters/xModify',
                form: baseForm,
                params: params,
                success: function() {
                    Scalr.event.fireEvent('close');
                },
                failure: function() {
                    securityGroups.enable();
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

                        me.modifyDbCluster({
                            VpcSecurityGroups: Ext.encode(securityGrops)
                        });
                    }
                });

                return true;
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
                flex: 1,
                maxWidth: 140
            },
            items: [{
                xtype: 'button',
                itemId: 'modify',
                text: 'Modify',
                handler: function () {
                    form.modifyDbCluster({
                        ignoreGovernance: true
                    });
                }
            }, {
                xtype: 'splitbutton',
                itemId: 'extendedModify',
                text: 'Modify',
                hidden: true,
                handler: function () {
                    form.modifyDbCluster();
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
                        form.modifyDbCluster({
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
            title: 'Database Cluster',
            name: 'instanceSettings',
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
                xtype: 'textfield',
                name: 'DBClusterIdentifier',
                fieldLabel: 'DB Cluster Identifier',
                readOnly: true
            }, {
                xtype: 'fieldcontainer',
                itemId: 'securityGroups',
                fieldLabel: 'Security Groups',
                layout: 'hbox',
                width: 595,
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
                                                cloudLocation: cloudLocation
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
                    },
                    getSubmitValue: function () {
                        var me = this;

                        var securityGroupIds = me.getSecurityGroupsIds();
                        var securityGroupNames = me.getSecurityGroupsNames();

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
                    name: 'DBSecurityGroups',
                    cls: 'x-tagfield-force-item-hover',
                    readOnly: true,
                    scrollable: true,
                    hidden: true,
                    disabled: true,
                    listeners: {
                        afterrender: {
                            fn: function (field) {
                                field.getEl().on('click', function (event, target) {

                                    target = Ext.get(target);

                                    if (target.hasCls('x-tagfield-item-text')) {

                                        var link = '#/tools/aws/rds/sg/edit?' +

                                            Ext.Object.toQueryString({
                                                dbSgName: target.getHtml(),
                                                cloudLocation: cloudLocation
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
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database Engine',
            name: 'engineSettings',
            hidden: true,
            disabled: true,
            defaults: {
                labelWidth: 200,
                width: 500
            },
            items: [{
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
            }]
        }, {
            xtype: 'fieldset',
            title: 'Database',
            name: 'databaseSettings',
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
            title: 'Maintenance Windows and Backups',
            name: 'maintenanceWindowSettings',
            items: [{
                xtype: 'hiddenfield',
                name: 'PreferredMaintenanceWindow'
            }, {
                xtype: 'hiddenfield',
                name: 'PreferredBackupWindow'
            }, {
                labelWidth: 200,
                xtype: 'checkboxfield',
                fieldLabel: 'Apply Immediately',
                name: 'ApplyImmediately',
                inputValue: true,
                uncheckedValue: false
            }, {
                xtype: 'fieldcontainer',
                layout: {
                    type: 'hbox'
                },
                defaults: {
                    margin: '0 3 0 0',
                    isFormField: false
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
                    isFormField: false
                },
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
                hidden: true,
                disabled: true,
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

        form.down('[name=MultiAZ]').
            setDisabled(isMultiAzDeprecated);

        if (isMultiAzDeprecated) {
            multiAzField.setValue(false);
        }

        return true;
    };

    var applySecurityGroups = function (groups) {
        var securityGroupNames = [];

        Ext.Array.each(groups, function (securityGroup) {
            securityGroupNames.push(
                securityGroup
            );
        });

        var field = form.down('[name=DBSecurityGroups]');
        field.reset();
        field.setValue(
            securityGroupNames.join(', ')
        );

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

    updateSecurityGroups(
        instance['VpcSecurityGroups'],
        true
    );

    form.down('#securityGroups').enablePolicy();

    return form;

});

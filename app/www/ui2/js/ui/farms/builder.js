Scalr.regPage('Scalr.ui.farms.builder', function (loadParams, moduleParams) {
    var farmLocked = moduleParams['farm'] && moduleParams['farm']['lock'];

	var reconfigurePage = function(params) {
        var record;

        if (loadParams['roleId']) {
            farmbuilder.down('#farmroles').fireEvent('addrole', loadParams['roleId']*1);
        }

        if (params['farmRoleId']) {
            record = farmRolesStore.findRecord('farm_role_id', params['farmRoleId']);
            if (record) {
                farmbuilder.down('#farmroles').select(record);
            }
        }
	};
    var getNewFarmRoleVariables = function(roleVars) {
        var farmVars = farmbuilder.down('#variables').getValue() || [];
        roleVars = roleVars || []

        for (var i = 0; i < farmVars.length; i++) {
            if (farmVars[i]['scope'] == 'farm') {
                var flag = true;
                for (var j = 0, len = roleVars.length; j < len; j++) {
                    if (roleVars[j]['name'] == farmVars[i]['name']) {
                        flag = false;

                        if (roleVars[j]['scope'] != 'role') {
                            // var has value on farm level, and doesn't have value on role level, add
                            roleVars[j]['defaultScope'] = farmVars[i]['scope'];
                            roleVars[j]['defaultValue'] = farmVars[i]['value'];
                            roleVars[j]['scope'] = farmVars[i]['scope'];
                            break;
                        }
                    }
                }

                if (flag) {
                    // don't have such value, add it
                    farmVars[i]['defaultScope'] = farmVars[i]['scope'];
                    farmVars[i]['defaultValue'] = farmVars[i]['value'];
                    farmVars[i]['value'] = '';

                    roleVars.push(farmVars[i]);
                }
            }
        }

        return roleVars;
    };

    var farmRolesSorters = [{
			property: 'launch_index',
			direction: 'ASC'
		}],
        farmRolesStore = Ext.create('store.store', {
            model: 'Scalr.ui.FarmRoleModel',
            proxy: 'object',
            data: moduleParams.farm ? moduleParams.farm.roles : [],
            sortOnLoad: true,
            sortOnFilter: true,
            sorters: farmRolesSorters,
            listeners: {
                remove: function() {
                    this.resetLaunchIndexes();
                }
            },
            countBy: function(field, value, excludeRecord){
                var list = this.query(field, value, false, false, true),
                    count = list.length;
                if (excludeRecord !== undefined && list.indexOf(excludeRecord) !== -1) {
                    count--;
                }
                return count;
            },
            getNextLaunchIndex: function() {
                var index = -1;
                (this.snapshot || this.data).each(function() {
                    var i = this.get('launch_index');
                    index = i > index ? i : index;
                });
                return ++index;
            },
            resetLaunchIndexes: function() {
                var data = this.queryBy(function(){return true;}),//me.store.snapshot || me.store.data
                    index = 0;
                data.sort(farmRolesSorters);
                data.each(function() {
                    this.set('launch_index', index++);
                });
                this.sort(farmRolesSorters);
            },

            updateLaunchIndex: function(record, launchIndex) {
                var currentLaunchIndex = record.get('launch_index');
                (this.snapshot || this.data).each(function() {
                    var recLaunchIndex = this.get('launch_index');
                    if (recLaunchIndex >= launchIndex) {
                        this.set('launch_index', recLaunchIndex + 1);
                    }
                });
                record.set('launch_index', launchIndex);

                (this.snapshot || this.data).each(function() {
                    var recLaunchIndex = this.get('launch_index');
                    if (recLaunchIndex > currentLaunchIndex) {
                        this.set('launch_index', recLaunchIndex - 1);
                    }
                });
                this.sort(farmRolesSorters);
            }

        });

    var addRoleHandler = function (role) {
        var behaviors = role.get('behaviors'),
            alias = role.get('alias'),
            msg;

        if (!Ext.form.field.VTypes.rolename(alias)) {
            Scalr.message.Error(Ext.form.field.VTypes.rolenameText);
            return false;
        } else if (farmRolesStore.countBy('alias', alias) > 0) {
            Scalr.message.Error('Alias must be unique within the farm');
            return false;
        }

        if ((Ext.Array.contains(behaviors, 'mysql') || Ext.Array.contains(behaviors, 'mysql2') || Ext.Array.contains(behaviors, 'percona')) && !Ext.Array.contains(behaviors, 'mysqlproxy')) {
            if (
                farmRolesStore.queryBy(function(record) {
                    if ((record.get('behaviors').match('mysql') || record.get('behaviors').match('mysql2') || record.get('behaviors').match('percona')) && !record.get('behaviors').match('mysqlproxy'))
                        return true;
                }).length > 0
            ) {
                Scalr.message.Error('Only one MySQL / Percona role can be added to farm');
                return false;
            }
        }

        var exhistingBehavior;
        Ext.Array.each(['postgresql', 'redis', 'mongodb', 'rabbitmq', 'mariadb'], function(behavior){
            if (Ext.Array.contains(behaviors, behavior)) {
                if (
                    farmRolesStore.queryBy(function(record) {
                        if (record.get('behaviors').match(behavior))
                            return true;
                    }).length > 0
                ) {
                    exhistingBehavior = behavior;
                    return false;
                }
            }
        });
        if (exhistingBehavior) {
            Scalr.message.Error('Only one ' + Scalr.utils.beautifyBehavior(exhistingBehavior) + ' role can be added to farm');
            return false;
        }

        role.set({
            'farm_role_id': 'virtual_' + (new Date()).getTime(),
            'new': true,
            'launch_index': farmRolesStore.getNextLaunchIndex(),
            'behaviors': behaviors.join(','),
            'variables': getNewFarmRoleVariables(role.get('variables'))
        });

        farmbuilder.down('#edit').addRoleDefaultValues(role);

        farmRolesStore.add(role);
        msg = 'Role "' + role.get('alias') + '" added';
        if (Scalr.flags['analyticsEnabled'] && !Ext.Array.contains(moduleParams['analytics']['unsupportedClouds'], role.get('platform'))) {
            role.loadHourlyRate(null, function(){Scalr.message.Success(msg);});
        } else {
            Scalr.message.Success(msg);
        }
    };

    var saveErrorHandler = function(errors) {
        var fbcard = farmbuilder.getComponent('fbcard'),
            activeRole,
            farmErrors = [],
            rolesErrors = [],
            errorTipEl,
            errorTipText = 'Some errors occured while saving the farm';

        (farmRolesStore.snapshot || farmRolesStore.data).each(function (rec) {
            rec.set('errors', undefined);
        });

        errors = errors || {};
        if (errors.roles !== undefined) {
            Ext.Object.each(errors.roles, function(farmRoleId, errors){
                var role = farmRolesStore.query('farm_role_id', farmRoleId).first();
                if (role) {
                    role.set('errors', errors);
                    rolesErrors.push('role <b>' + (role.get('alias') || role.get('name')) + '</b>: ' + Ext.Object.getSize(errors) + ' error(s)');
                    if (activeRole === undefined) {
                        activeRole = role;
                    }
                }
            });
        }
        if (errors.farm !== undefined) {
            var farmPanel = fbcard.getComponent('farm');
            Ext.Object.each(errors.farm, function(name, error){
                var field = farmPanel.down('[name="' + name + '"]');
                field = field || farmPanel.down('#' + name);
                if (field && field.markInvalid) {
                    field.markInvalid(error);
                }
                farmErrors.push(error);
            });

            fbcard.layout.setActiveItem('farm');
            errorTipEl = farmPanel.el;
        } else if (activeRole !== undefined) {
            var farmRolesPanel = farmbuilder.getComponent('farmroles');
            farmRolesPanel.select(activeRole);
            errorTipEl = farmRolesPanel.el;
        }

        if (farmErrors.length || rolesErrors.length) {
            errorTipText += ':<ul class="x-tip-errors-list"><li>' + Ext.Array.merge(farmErrors, rolesErrors).join('</li><li>') + '</li></ul>'
            Scalr.message.ErrorTip(errorTipText, errorTipEl);

        }
    };

    var saveHandler = function (farm) {
        var p = {}, farmOwnerCmp;
        farm = farm || {};

        farmbuilder.down('#farmroles').deselectAll();
        farmbuilder.down('#farmroles').clearFilter();
        farmbuilder.getComponent('fbcard').layout.setActiveItem('farm');

        farm['farmId'] = moduleParams['farmId'];
        farmOwnerCmp = farmbuilder.down('#farmOwner');

        p['name'] = farmbuilder.down('#farmName').getValue();
        p['description'] = farmbuilder.down('#farmDescription').getValue();
        p['owner'] = farmOwnerCmp && !farmOwnerCmp.readOnly ? farmOwnerCmp.getValue() : null;
        p['timezone'] = farmbuilder.down('#timezone').getValue();
        p['rolesLaunchOrder'] = farmbuilder.down('#launchorder').getValue();
        p['variables'] = farmbuilder.down('#variables').getValue();
        p['projectId'] = farmbuilder.down('#costMetering').getProjectId();

        //vpc
        var vpcEnabledField = farmbuilder.down('[name="vpc_enabled"]'),
            vpcRegionField = farmbuilder.down('[name="vpc_region"]'),
            vpcIdField = farmbuilder.down('[name="vpc_id"]');
        if (vpcEnabledField.getValue()) {
            var vpcLimits = farmbuilder.getLimits('ec2', 'aws.vpc');
            if (vpcLimits) {
                if (vpcLimits['value'] == 1 && (!vpcRegionField.getValue() || !vpcIdField.getValue())) {
                    farmbuilder.getComponent('fbcard').layout.setActiveItem('farm');
                    if (!vpcRegionField.getValue()) {
                        Scalr.message.InfoTip('VPC region is required.', vpcRegionField.getEl());
                    } else if (!vpcIdField.getValue()) {
                        Scalr.message.InfoTip('VPC ID is required.', vpcIdField.getEl());
                    }
                    return;
                }
            }
            p['vpc_region'] = vpcRegionField.getValue();
            p['vpc_id'] = vpcIdField.getValue();
        }

        farm['farm'] = Ext.encode(p);

        p = [];
        (farmRolesStore.snapshot || farmRolesStore.data).each(function (rec) {
            var sets = {};

            sets = {
                alias: rec.get('alias'),
                role_id: rec.get('role_id'),
                farm_role_id: rec.get('farm_role_id'),
                launch_index: rec.get('launch_index'),
                platform: rec.get('platform'),
                cloud_location: rec.get('cloud_location'),
                settings: rec.get('settings', true),
                scaling: rec.get('scaling'),
                scripting: rec.get('scripting'),
                scripting_params: rec.get('scripting_params'),
                config_presets: rec.get('config_presets'),
                storages: rec.get('storages'),
                variables: rec.get('variables')
            };

            if (Ext.isObject(rec.get('params'))) {
                sets['params'] = rec.get('params');
            }

            p[p.length] = sets;
        });

        farm['roles'] = Ext.encode(p);
        farm['v2'] = 1;
        farm['changed'] = moduleParams['farm'] ? moduleParams['farm']['changed'] : '';
        Scalr.Request({
            processBox: {
                msg: 'Saving farm ...'
            },
            url: '/farms/builder/xBuild',
            params: farm,
            success: function(data) {
                if (data.isNewFarm)
                    Scalr.event.fireEvent('redirect', '#/farms/view?farmId=' + data.farmId);
                else
                    Scalr.event.fireEvent('close');
            },
            failure: function(data) {
                if (data['errors']) {
                    saveErrorHandler(data['errors']);
                }

                if (data['changedFailure']) {
                    Scalr.utils.Window({
                        title: 'Warning',
                        layout: 'fit',
                        width: 560,
                        bodyCls: 'x-container-fieldset',
                        items: [{
                            xtype: 'displayfield',
                            cls: 'x-form-field-warning',
                            value: data['changedFailure'],
                            margin: '0 0 10 0'
                        }],
                        dockedItems: [{
                            xtype: 'container',
                            cls: 'x-docked-buttons',
                            dock: 'bottom',
                            layout: {
                                type: 'hbox',
                                pack: 'center'
                            },
                            items: [{
                                xtype: 'button',
                                text: 'Override',
                                handler: function() {
                                    this.up('#box').close();
                                    moduleParams['farm']['changed'] = ''; // TODO: do better via flag
                                    saveHandler();
                                }
                            }, {
                                xtype: 'button',
                                text: 'Refresh page',
                                margin: '0 0 0 10',
                                handler: function() {
                                    this.up('#box').close();
                                    Scalr.event.fireEvent('refresh');
                                }
                            }, {
                                xtype: 'button',
                                text: 'Continue edit',
                                margin: '0 0 0 10',
                                handler: function() {
                                    this.up('#box').close();
                                }
                            }]
                        }]
                    });
                }
            }
        });
    }

    var farmbuilder = Ext.create('Ext.container.Container', {
        baseTitle: 'Farms &raquo; ' + (moduleParams.farm ? moduleParams.farm.farm.name : 'Builder'),
        updateTitle: function(text) {
            this.up('panel').setTitle(this.baseTitle + (text ? ' &raquo; ' + text : ''));
        },
		layout: {
			type: 'hbox',
			align : 'stretch',
			pack  : 'start'
		},
        itemId: 'farmbuilder',
        items: [{
            xtype: 'farmselroles',
            itemId: 'farmroles',
            store:  farmRolesStore,
            listeners: {
                farmsettings: function(state) {
                    this.deselectAll();
                    if (state) {
                        farmbuilder.down('#fbcard').layout.setActiveItem('farm');
                    } else {
                        farmbuilder.down('#fbcard').layout.setActiveItem('blank');
                    }
                },
                selectionchange: function(c, selections) {
                    var c = farmbuilder.down('#fbcard');
                    if (selections[0]) {
                        c.layout.setActiveItem('blank');
                        farmbuilder.down('#edit').setCurrentRole(selections[0]);
                        c.layout.setActiveItem('edit');
                    } else {
                        c.layout.setActiveItem('farm');
                    }
                },
                addrole: function (roleId) {
                    this.deselectAll();
                    var card = farmbuilder.down('#fbcard');
                    if (!card.getComponent('add')) {
                        card.add({
                            xtype: 'roleslibrary',
                            moduleParams: moduleParams,
                            hidden: true,
                            autoRender: false,
                            itemId: 'add',
                            roleId: roleId,
                            listeners: {
                                activate: function() {
                                    farmbuilder.updateTitle('Add new role');
                                    farmbuilder.getComponent('farmroles').down('dataview').getPlugin('flyingbutton').setDisabled(true);
                                    if (this.down('form').isVisible()) {
                                        farmbuilder.up().getDockedComponent('buttons').getComponent('save').lockButton();
                                    }
                                },
                                deactivate: function() {
                                    farmbuilder.getComponent('farmroles').down('dataview').getPlugin('flyingbutton').setDisabled(false);
                                    farmbuilder.up().getDockedComponent('buttons').getComponent('save').unlockButton();
                                },
                                addrole: addRoleHandler,
                                showform: function() {
                                    farmbuilder.up().getDockedComponent('buttons').getComponent('save').lockButton();
                                },
                                hideform: function() {
                                    farmbuilder.up().getDockedComponent('buttons').getComponent('save').unlockButton();
                                },
                                beforesetalias: function(name, res){
                                    var count = farmRolesStore.countBy('name', name);
                                    res.alias = name + (count > 0 ? '-' + count : '');
                                }
                            }
                        });
                    }
                    card.layout.setActiveItem('add');
                }
            }
        }, {
            xtype: 'container',
            itemId: 'fbcard',
            layout: 'card',
            flex: 1,
            activeItem: 'farm',
            items: [{
                itemId: 'farm',
                xtype: 'container',
                autoScroll: true,
                layout: 'anchor',
                cls: 'x-panel-column-left',
                getVpcSettings: function() {
                    var result = false,
                        vpcRegion = this.down('[name="vpc_region"]').getValue(),
                        vpcId = this.down('[name="vpc_id"]').getValue();
                    if (moduleParams['farmVpcEc2Enabled'] && this.down('[name="vpc_enabled"]').getValue() && vpcRegion && vpcId){
                        result = {
                            region: vpcRegion,
                            id: vpcId
                        }
                    }
                    return result;
                },
                defaults: {
                    anchor: '100%'
                },
                items: [{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'stretch'
                    },
                    cls: 'x-fieldset-separator-bottom',
                    items: [{
                        xtype: 'fieldset',
                        cls: 'x-fieldset-separator-right',
                        title: 'General info',
                        flex: 1,
                        maxWidth: 600,
                        defaults: {
                            anchor: '100%',
                            labelWidth: 125
                        },
                        items: [{
                            xtype: 'textfield',
                            name: 'name',
                            itemId: 'farmName',
                            fieldLabel: 'Name'
                        }, {
                            xtype: 'combo',
                            name: 'timezone',
                            itemId: 'timezone',
                            store: moduleParams['timezones_list'],
                            fieldLabel: 'Timezone',
                            allowBlank: false,
                            anchor: '100%',
                            forceSelection: true,
                            editable: false,
                            queryMode: 'local',
                            anyMatch: true
                        }, moduleParams.farm ? {
                            xtype: 'fieldcontainer',
                            layout: 'hbox',
                            fieldLabel: 'Owner',
                            items: [{
                                xtype: 'combo',
                                flex: 1,
                                itemId: 'farmOwner',
                                name: 'owner',
                                editable: false,
                                queryMode: 'local',
                                store: {
                                    fields: ['id', 'email'],
                                    data: moduleParams['usersList'],
                                    proxy: 'object'
                                },
                                valueField: 'id',
                                displayField: 'email',
                                tooltipText: !moduleParams['farm']['farm']['ownerEditable'] ? 'Only account owner or farm owner can change this field' : null,
                                readOnly: !moduleParams['farm']['farm']['ownerEditable']
                            }, {
                                xtype: 'button',
                                margin: '0 0 0 6',
                                width: 80,
                                hidden: !moduleParams['farm']['farm']['ownerEditable'],
                                text: 'History',
                                handler: function() {
                                    Scalr.Request({
                                        processBox: {
                                            action: 'load'
                                        },
                                        url: '/farms/xGetOwnerHistory',
                                        params: {
                                            farmId: moduleParams['farmId']
                                        },
                                        success: function(data) {
                                            Scalr.utils.Window({
                                                xtype: 'grid',
                                                title: 'History',
                                                margin: 16,
                                                width: 600,
                                                //padding: 16,
                                                store: {
                                                    fields: ['newId', 'newEmail', 'changedById', 'changedByEmail', 'dt'],
                                                    data: data.history,
                                                    reader: 'object'
                                                },
                                                viewConfig: {
                                                    emptyText: 'No changes have been made',
                                                    deferEmptyText: false
                                                },
                                                columns: [
                                                    { header: 'New Owner', flex: 1, dataIndex: 'newEmail', sortable: true },
                                                    { header: 'Was set by', flex: 1, dataIndex: 'changedByEmail', sortable: true },
                                                    { header: 'On', flex: 1, dataIndex: 'dt', sortable: true }
                                                ],
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
                                                        text: 'Close',
                                                        width: 150,
                                                        handler: function() {
                                                            this.up('grid').close();
                                                        }
                                                    }]
                                                }]
                                            });
                                        }
                                    })
                                }
                            }]
                        } : null, {
                            xtype: 'fieldcontainer',
                            fieldLabel: 'Roles launch order',
                            layout: 'hbox',
                            items: [{
                                xtype: 'buttongroupfield',
                                name: 'rolesLaunchOrder',
                                itemId: 'launchorder',
                                columns: 1,
                                listeners: {
                                    change: function(comp, value) {
                                        farmbuilder.down('#farmroles').toggleLaunchOrder(value == 1);
                                        if (value == 1) {
                                            farmRolesStore.resetLaunchIndexes();
                                        }
                                    }
                                },
                                defaults: {
                                    width: 130
                                },
                                items: [{
                                    text: 'Simultaneous',
                                    value: '0'
                                }, {
                                    text: 'Sequential',
                                    value: '1'
                                }]

                            },{
                                xtype: 'displayinfofield',
                                margin: '0 0 0 5',
                                info:   '<b>Simultaneous</b>: Launch all roles at the same time<br/>' +
                                        '<b>Sequential</b>: Use drag and drop to adjust the launching order of roles'
                            }]
                        },{
                            xtype: 'textarea',
                            name: 'description',
                            itemId: 'farmDescription',
                            fieldLabel: 'Description',
                            grow: true,
                            growMin: 70
                        }]
                    },{
                        xtype: 'farmcostmetering',
                        itemId: 'costMetering',
                        title: 'Cost metering',
                        flex: 1,
                        farmRolesStore: farmRolesStore,
                        analyticsData: moduleParams['analytics'],
                        hidden: !Scalr.flags['analyticsEnabled'],
                    }]
                },{
                    xtype: 'displayfield',
                    itemId: 'vpcinfo',
                    cls: 'x-form-field-info',
                    maxWidth: 740,
                    margin: '18 32 8',
                    hidden: true,
                    value: 'Amazon VPC settings can be changed on TERMINATED farm only.'
                },{
                    xtype: 'fieldset',
                    itemId: 'vpc',
                    title: '&nbsp;',
                    baseTitle: 'Launch this farm inside Amazon VPC',
                    checkboxToggle: true,
                    collapsed: true,
                    collapsible: true,
                    hidden: !moduleParams['farmVpcEc2Enabled'],
                    checkboxName: 'vpc_enabled',
                    layout: 'hbox',
                    markInvalid: function(error) {
                        Scalr.message.ErrorTip(error, this.down('[name="vpc_enabled"]').getEl(), {anchor: 'bottom'});
                    },
                    disableToggle: function(disable) {
                        this.checkboxCmp.setDisabled(disable);
                    },
                    listeners: {
                        beforecollapse: function() {
                            var me = this;
                            if (me.checkboxCmp.disabled) {
                                return false;
                            } else if (!me.forcedCollapse && me.down('[name="vpc_id"]').getValue()) {
                                me.maybeResetVpcId(function(){
                                    me.forcedCollapse = true;
                                    me.collapse();
                                    me.forcedCollapse = false;
                                });
                                return false;
                            }
                        },
                        beforeexpand: function() {
                            return !this.checkboxCmp.disabled;
                        }
                    },
                    maybeResetVpcId: function(okCb) {
                        var me = this;
                        Scalr.utils.Confirm({
                            form: {
                                xtype: 'container',
                                items: {
                                    xtype: 'component',
                                    style: 'text-align: center',
                                    margin: '36 32 0',
                                    html: '<span class="x-fieldset-subheader1">All VPC-related settings of all roles will be reset<br/>(including <b>Security groups</b> and <b>VPC subnets</b>).<br/>Are you sure you want to continue?</span>'
                                }
                            },
                            ok: 'Continue',
                            success: function() {
                                me.resetVpcId();
                                if (Ext.isFunction(okCb)) {
                                    okCb();
                                }
                            }
                        });
                    },
                    resetVpcId: function() {
                        this.down('[name="vpc_id"]').reset();
                        (farmRolesStore.snapshot || farmRolesStore.data).each(function (rec) {
                            if (rec.get('platform') === 'ec2') {
                                var settings = rec.get('settings', true);
                                delete settings['aws.vpc_subnet_id'];
                                delete settings['router.vpc.networkInterfaceId'];
                                delete settings['router.scalr.farm_role_id'];
                                settings['aws.security_groups.list'] = moduleParams['roleDefaultSettings']['security_groups.list'];
                            }
                        });
                    },
                    items: [{
                        xtype: 'combo',
                        width: 300,
                        name: 'vpc_region',
                        emptyText: 'Please select a VPC region',
                        editable: false,
                        store: {
                            fields: [ 'id', 'name' ],
                            data: moduleParams['farmVpcEc2Locations'] || [],
                            proxy: 'object'
                        },
                        queryMode: 'local',
                        valueField: 'id',
                        displayField: 'name',
                        listeners: {
                            beforeselect: function(field, record) {
                                var me = this,
                                    fieldset;
                                if (!me.forcedSelect && me.next('[name="vpc_id"]').getValue()) {
                                    fieldset = me.up('#vpc');
                                    fieldset.maybeResetVpcId(function(){
                                        me.forcedSelect = true;
                                        me.setValue(record.get('id'));
                                        me.forcedSelect = false;
                                    });
                                    return false;
                                }
                            },
                            change: function(field, value) {
                                var vpcIdField = field.next(),
                                    vpcIdFieldProxy = vpcIdField.store.getProxy(),
                                    vpcLimits = farmbuilder.getLimits('ec2', 'aws.vpc'),
                                    disableAddNew = false;

                                vpcIdField.reset();
                                vpcIdField.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + value;
                                vpcIdFieldProxy.params = {cloudLocation: value};
                                delete vpcIdFieldProxy.filterFn;

                                if (vpcLimits && vpcLimits['regions'] && vpcLimits['regions'][value]) {
                                    if (vpcLimits['regions'][value]['ids'] && vpcLimits['regions'][value]['ids'].length > 0) {
                                        vpcIdFieldProxy.filterFn = function(record) {
                                            return Ext.Array.contains(vpcLimits['regions'][value]['ids'], record.get('id'));
                                        };
                                        vpcIdField.setValue(vpcLimits['regions'][value]['ids'][0]);
                                        disableAddNew = true;
                                    }
                                }
                                vpcIdField.getPlugin('comboaddnew').setDisabled(disableAddNew);
                            }
                        }
                    }, {
                        xtype: 'combo',
                        flex: 1,
                        maxWidth: 430,
                        name: 'vpc_id',
                        emptyText: 'Please select a VPC ID',
                        margin: '0 0 0 12',
                        editable: false,

                        queryCaching: false,
                        clearDataBeforeQuery: true,
                        store: {
                            fields: [ 'id', 'name' ],
                            proxy: {
                                type: 'cachedrequest',
                                crscope: 'farmbuilder',
                                url: '/platforms/ec2/xGetVpcList',
                                root: 'vpc'
                            }
                        },
                        valueField: 'id',
                        displayField: 'name',
                        plugins: [{
                            ptype: 'comboaddnew',
                            pluginId: 'comboaddnew',
                            url: '/tools/aws/vpc/create',
                            applyNewValue: false
                        }],
                        listeners: {
                            addnew: function(item) {
                                Scalr.CachedRequestManager.get('farmbuilder').setExpired({
                                    url: '/platforms/ec2/xGetVpcList',
                                    params: {
                                        cloudLocation: this.prev('combo').getValue()
                                    }
                                });
                            },
                            beforequery: function() {
                                var vpcRegionField = this.prev('combo');
                                if (!vpcRegionField.getValue()) {
                                    this.collapse();
                                    Scalr.message.InfoTip('Select VPC region first.', vpcRegionField.getEl());
                                    return false;
                                }
                            },
                            beforeselect: function(field, record) {
                                var me = this,
                                    fieldset;
                                if (!me.forcedSelect && me.getValue()) {
                                    fieldset = me.up('#vpc');
                                    fieldset.maybeResetVpcId(function(){
                                        me.forcedSelect = true;
                                        me.setValue(record.get('id'));
                                        me.forcedSelect = false;
                                    });
                                    return false;
                                }
                            }
                        }
                    }]
                }, {
                    xtype: 'variablefield',
                    name: 'variables',
                    itemId: 'variables',
                    title: 'Farm global variables',
                    margin: '0 0 0 18',
                    maxHeight: 400,
                    currentScope: 'farm',
                    encodeParams: false,
                    removeTopSeparator: true,
                    restoreContainerScrollState: function () {
                        var me = this;

                        var container = me.up('#farm');
                        container.el.scrollTo('top', container.scrollTop || 0);

                        return me;
                    },
                    updateFarmRoleVariable: function (variable, farmCurrent, scopes, farmDefault, farmLocked) {
                        variable.scopes = scopes;

                        if (!farmDefault) {
                            if (farmCurrent.flagFinal == 1 || farmCurrent.flagRequired ||
                                farmCurrent.flagHidden == 1 || farmCurrent.format || farmCurrent.validator) {
                                if (farmCurrent.flagFinal == 1) {
                                    variable.current = '';
                                }
                                variable.locked = farmCurrent;
                            } else {
                                variable.locked = '';
                            }

                            if (farmCurrent.flagHidden == 1 && farmCurrent.value) {
                                farmCurrent.value = '******';
                            }

                            variable.default = farmCurrent;
                        } else {
                            variable.default = (farmCurrent && farmCurrent.value) ? farmCurrent : farmDefault;
                            variable.locked = farmLocked;

                            if (farmLocked.flagHidden == 1) {
                                variable.default.value = '******';
                            }
                        }

                        if (variable.current && variable.current.value) {
                            variable.scopes.push('farmrole');
                        }
                    },
                    listeners: {
                        load: function (me, data) {
                            if (data.length) {
                                var farmVariables = [];

                                Ext.Array.each(data, function (variable) {
                                    if (!variable['default']) {
                                        var current = Ext.clone(variable.current);
                                        var locked = '';

                                        if (current.flagFinal == 1 || current.flagRequired ||
                                            current.flagHidden == 1 || current.format || current.validator) {
                                            if (current.flagHidden == 1) {
                                                current.value = '******';
                                            }
                                            locked = current;
                                        }

                                        farmVariables.push({
                                            name: variable.name,
                                            'default': current,
                                            locked: locked,
                                            scopes: Ext.Array.clone(variable.scopes)
                                        });
                                    }
                                });

                                me.farmVariables = farmVariables;
                            }
                        },
                        boxready: function (me) {
                            var container = me.up('#farm');
                            container.el.on('scroll', function () {
                                container.scrollTop = container.el.getScroll().top;
                            }, me);
                        },
                        select: function (me) {
                            me.restoreContainerScrollState();
                        },
                        datachanged: function (me, record) {
                            me.restoreContainerScrollState();

                            var name = record.get('name');
                            var current = Ext.clone(record.get('current'));
                            var def = record.get('default');
                            var locked = Ext.clone(record.get('locked'));
                            var scopes = Ext.Array.clone(record.get('scopes'));

                            if (!def) {
                                Ext.Array.each(me.farmVariables, function (variable) {
                                    if (variable.name === name) {
                                        me.updateFarmRoleVariable(variable, current, scopes);
                                        return false;
                                    }
                                });
                            }

                            (farmRolesStore.snapshot || farmRolesStore.data).each(function (role) {
                                var variables = role.get('variables', true) || [];

                                Ext.Array.each(variables, function (variable) {
                                    if (variable.name === name) {
                                        me.updateFarmRoleVariable(variable, current, scopes, def, locked);
                                    }
                                });

                                role.set('variables', variables);
                            });
                        },
                        addvariable: function (me, record) {
                            var name = record.get('name');
                            var current = Ext.clone(record.get('current'));
                            var scopes = Ext.Array.clone(record.get('scopes'));
                            var newVariable = {
                                name: name,
                                'default': current,
                                scopes: scopes
                            };

                            me.farmVariables.push(newVariable);

                            (farmRolesStore.snapshot || farmRolesStore.data).each(function (role) {
                                var variables = role.get('variables', true) || [];
                                var isVariableExist = false;

                                Ext.Array.each(variables, function (variable) {
                                    if (variable.name === name) {
                                        isVariableExist = true;
                                    }
                                });

                                if (!isVariableExist) {
                                    variables.push(newVariable);
                                }

                                role.set('variables', variables);
                            });
                        },
                        removevariable: function (me, record) {
                            me.restoreContainerScrollState();

                            var name = record.get('name');

                            Ext.Array.each(me.farmVariables, function (variable, index, farmVariables) {
                                if (variable.name === name) {
                                    farmVariables.splice(index, 1);

                                    return false;
                                }
                            });

                            (farmRolesStore.snapshot || farmRolesStore.data).each(function (role) {
                                var variables = role.get('variables', true) || [];
                                var result = [];

                                Ext.Array.each(variables, function (variable) {
                                    var current = variable.current;

                                    if (variable.name !== name || (current && current.value)) {
                                        if (current) {
                                            variable.locked = '';
                                            variable.default = '';
                                            variable.scopes.shift();
                                        }

                                        result.push(variable);
                                    }
                                });

                                role.set('variables', result);
                            });
                        }
                    }
                }],
                listeners: {
                    boxready: function() {
                        var form = farmbuilder.down('#farm'),
                            farm = moduleParams.farm ? Ext.clone(moduleParams.farm.farm) : {isNew: true},
                            vpcFieldset = form.down('#vpc'),
                            disallowVpcToggle = false,
                            defaultVpcEnabled = false,
                            preloadVpcIdList = false;
                        Ext.apply(farm, {
                            timezone: farm.timezone || moduleParams['timezone_default'],
                            rolesLaunchOrder: farm.rolesLaunchOrder || '0',
                            variables: farm.variables || moduleParams.farmVariables,
                            status: farm.status || 0
                        });

                        if (moduleParams['farmVpcEc2Enabled']) {
                            var vpcLimits = farmbuilder.getLimits('ec2', 'aws.vpc');
                            if (vpcLimits) {
                                if (vpcLimits['regions']) {
                                    var vpcRegionField = form.down('[name="vpc_region"]'),
                                        defaultRegion;
                                    vpcRegionField.store.filter({filterFn: function(region){
                                        var r = vpcLimits['regions'][region.get('id')];
                                        if (r !== undefined && r['default'] == 1) {
                                            defaultRegion = region.get('id');
                                        }
                                        return r !== undefined;
                                    }});
                                    if (!farm.vpc || !farm.vpc.region) {
                                        vpcRegionField.setValue(defaultRegion || vpcRegionField.store.first());
                                    }
                                    preloadVpcIdList = true;
                                }
                                disallowVpcToggle = true;
                                defaultVpcEnabled = vpcLimits['value'] == 1;
                            }
                            vpcFieldset.setTitle(vpcFieldset.baseTitle + (vpcLimits?'&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Ext.String.htmlEncode(Scalr.strings['farmbuilder.vpc.enforced']) + '" class="x-icon-governance" />':''));

                            if (farm.vpc && farm.vpc.id) {
                                farm.vpc_enabled = true;
                                farm.vpc_region = farm.vpc.region;
                                farm.vpc_id = farm.vpc.id;
                                if (disallowVpcToggle && !defaultVpcEnabled) {
                                    disallowVpcToggle = false;
                                }
                                preloadVpcIdList = true;
                            } else {
                                if (farm.isNew) {
                                    farm.vpc_enabled = defaultVpcEnabled;
                                } else if (disallowVpcToggle && defaultVpcEnabled) {
                                    disallowVpcToggle = false;
                                }
                            }

                            if (farm.status != 0) {
                                disallowVpcToggle = true;
                                Ext.Array.each(vpcFieldset.query('[isFormField]'), function(field){
                                    if(field.name!=='vpc_enabled') field.disable();
                                });
                                form.down('#vpcinfo').show();
                            }
                        }
                        form.setFieldValues(farm);
                        vpcFieldset.disableToggle(disallowVpcToggle);
                        if (preloadVpcIdList) {
                            form.down('[name="vpc_id"]').store.load();
                        }
                        form.on('activate', function(){reconfigurePage(loadParams)}, farmbuilder, {single:true});
                    },
                    activate: function() {
                        farmbuilder.down('#farmroles').toggleFarmButton(true);
                        farmbuilder.updateTitle();
                        if (Scalr.flags['analyticsEnabled']) {
                            farmbuilder.down('#costMetering').refresh();
                        }
                    },
                    deactivate: function() {
                        farmbuilder.down('#farmroles').toggleFarmButton(false);
                    }
                }
            }, {
                xtype: 'container',
                itemId: 'blank',
                cls: 'x-panel-column-left',
                listeners: {
                    activate: function() {
                        farmbuilder.updateTitle();
                    }
                }
            }, {
                xtype: 'farmroleedit',
                itemId: 'edit',
                moduleParams: moduleParams,
                farmRolesStore: farmRolesStore,
                listeners: {
                    activate: function() {
                        farmbuilder.updateTitle(this.currentRole.get('alias'));
                    },
                    rolealiaschange: function() {
                        farmbuilder.updateTitle(this.currentRole.get('alias'));
                    },
                    tabactivate: function(tab) {
                        if (tab.itemId === 'scripting') {
                            farmbuilder.down('#farmroles').down('dataview').addCls('scalr-ui-show-color-corners');
                        }
                    },
                    tabdeactivate: function(tab) {
                        if (tab.itemId === 'scripting') {
                            farmbuilder.down('#farmroles').down('dataview').removeCls('scalr-ui-show-color-corners')
                        }
                    }
                }
            }]
        }]
    });

    if (farmLocked) {
        Scalr.message.Warning(moduleParams['farm']['lock'] + ' You won\'t be able to save any changes.');
    }

    Ext.apply(moduleParams['tabParams'], {
        farmRolesStore: farmRolesStore,
        behaviors: moduleParams['behaviors'] || {},
        metrics: moduleParams['metrics'] || {},
        farm: moduleParams['farm'] ? moduleParams['farm'].farm : {}
    });


    farmbuilder.getLimits = function (category, name){
        var limits = moduleParams.governance || {};
        limits = limits[category] || {};
        return name !== undefined ? limits[name] : limits;
    }

    farmbuilder.down('#edit').createTabs();

	Scalr.event.on('update', function (type, data) {
		if (type == '/farms/roles/replaceRole' && data['farmId'] == loadParams['farmId']) {
            var record = farmRolesStore.findRecord('farm_role_id', data['farmRoleId'], false, false, true),
                farmRoles;
            if (record) {
                farmRoles = farmbuilder.down('#farmroles');
                farmRoles.deselectAll();
                record.set(data.role);
                farmRoles.select(record);
            }
        }
    }, farmbuilder);

    return Ext.create('Ext.panel.Panel', {
        scalrOptions: {
            'maximize': 'all',
            'title': 'Farms &raquo; ' + (moduleParams.farm ? moduleParams.farm.farm.name : 'Builder')
        },
        plugins: {
            ptype: 'localcachedrequest',
            crscope: 'farmbuilder'
        },
		layout: 'fit',
        tools: [{
            xtype: 'favoritetool',
            hidden: !!moduleParams.farm,
            favorite: {
                text: 'Create new farm',
                href: '#/farms/build'
            }
        }],
        items: farmbuilder,
        dockedItems: [{
            xtype: 'container',
            itemId: 'buttons',
            dock: 'bottom',
            cls: 'x-docked-buttons-mini',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                itemId: 'save',
                text: 'Save farm',
                disabled: farmLocked,
                lockButton: function() {
                    if (!farmLocked) {
                        this.setTooltip({
                            text: 'Add selected role to the farm or cancel role adding before saving farm.',
                            align: 'bc-tc'
                        }).setDisabled(true);
                    }
                },
                unlockButton: function() {
                    if (!farmLocked) {
                        this.setTooltip('').setDisabled(false);
                    }
                },
                handler: function() {
                    saveHandler();
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]
    });
});


Ext.define('Scalr.ui.FarmRoleModel', {
    extend: 'Ext.data.Model',
    fields: [
        'id',
        { name: 'new', type: 'boolean' },
        'role_id',
        'platform',
        'generation',
        'os',
        'os_family',
        'os_generation',
        'os_version',
        'farm_role_id',
        'cloud_location',
        'image',
        'name',
        'alias',
        'group',
        'cat_id',
        'behaviors',
        {name: 'launch_index', type: 'int'},
        'is_bundle_running',
        'settings',
        'scaling',
        'scripting',
        'scripting_params',
        'storages',
        'config_presets',
        'variables',
        'running_servers',
        'suspended_servers',
        'security_groups',
        'hourly_rate'
    ],

    constructor: function() {
        var me = this;
        me.callParent(arguments);
    },

    get: function(field, raw) {
        var value = this.callParent([field]);
        return raw === true || !value || Ext.isPrimitive(value) ? value : Ext.clone(value);
    },

    watchList: {
        launch_index: true,
        settings: ['scaling.enabled', 'scaling.min_instances', 'scaling.max_instances', 'aws.instance_type', 'db.msr.data_storage.engine', 'gce.machine-type'],
        scaling: true
    },

    set: function (fieldName, newValue) {
        var me = this,
            data = me[me.persistenceProperty],
            single = (typeof fieldName == 'string'),
            name, values, currentValue, value,
            events = [];

        if (me.store) {
            if (single) {
                values = me._singleProp;
                values[fieldName] = newValue;
            } else {
                values = fieldName;
            }

            for (name in values) {
                if (values.hasOwnProperty(name)) {
                    value = values[name];
                    currentValue = data[name];
                    if (me.isEqual(currentValue, value)) {
                        continue;
                    }
                    if (me.watchList[name]) {
                        if (me.watchList[name] === true) {
                            events.push({name: [name], value: value, oldValue: currentValue});
                        } else {
                            for (var i=0, len=me.watchList[name].length; i<len; i++) {
                                var name1 = me.watchList[name][i],
                                    currentValue1 = currentValue && currentValue[name1] ? currentValue[name1] : undefined,
                                    value1 = value && value[name1] ? value[name1] : undefined;
                                if (currentValue1 != value1) {
                                    events.push({name: [name, name1], value: value1, oldValue: currentValue1});
                                }
                            }
                        }
                    }
                }
            }

            if (single) {
                delete values[fieldName];
            }
        }

        me.callParent(arguments);

        Ext.Array.each(events, function(event){
            me.store.fireEvent('roleupdate', me, event.name, event.value, event.oldValue);
        });
    },

    getInstanceType: function(availableInstanceTypes, limits) {
        var me = this,
            settings = me.get('settings', true),
            typeString = me.get('image', true)['type'] || '',
            isEbs = typeString.indexOf('ebs') !== -1,
            isHvm = typeString.indexOf('hvm') !== -1,
            behaviors = me.get('behaviors', true),
            platform = me.get('platform'),
            allowedTypes,
            instanceType = settings[this.getInstanceTypeParamName()],
            defaultInstanceType,
            defaultInstanceTypeAllowed,
            firstAllowedInstanceType,
            allowedInstanceTypeCount = 0,
            instanceTypes = [];

        behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
        if (platform === 'ec2') {
            if (me.get('image', true)['architecture'] === 'i386') {
                if ((isEbs || instanceType == 't1.micro') && !Ext.Array.contains(behaviors, 'cf_cloud_controller')) {
                    allowedTypes = ['t1.micro', 'm1.small', 'm1.medium', 'c1.medium'];
                } else {
                    allowedTypes = ['m1.small', 'm1.medium', 'c1.medium'];
                }
                defaultInstanceType = 'm1.small';
            } else {
                defaultInstanceType = 'm1.small';

                if (isEbs || instanceType == 't1.micro') {
                    if (isHvm && me.get('os') != '2008Server' && me.get('os') != '2008ServerR2' && me.get('os_family') != 'windows') {
                        allowedTypes = [
                            't2.micro', 't2.small', 't2.medium',
                            'cc1.4xlarge', 'cc2.8xlarge', 'cg1.4xlarge', 'hi1.4xlarge',
                            'cr1.8xlarge', 'g2.2xlarge',
                            'i2.xlarge', 'i2.2xlarge', 'i2.4xlarge', 'i2.8xlarge',
                            'm3.medium', 'm3.large', 'm3.xlarge', 'm3.2xlarge',
                            'c3.large', 'c3.xlarge', 'c3.2xlarge', 'c3.4xlarge', 'c3.8xlarge',
                            'c4.large', 'c4.xlarge', 'c4.2xlarge', 'c4.4xlarge', 'c4.8xlarge',
                            'r3.large', 'r3.xlarge', 'r3.2xlarge', 'r3.4xlarge', 'r3.8xlarge'
                        ];
                        defaultInstanceType = 'c3.large';

                    } else {
                        allowedTypes = [
                            't1.micro',
                            'm1.small', 'm1.medium', 'm1.large', 'm1.xlarge',
                            'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge',
                            'm3.medium', 'm3.large', 'm3.xlarge', 'm3.2xlarge',
                            'c1.medium', 'c1.xlarge',
                            'c3.large', 'c3.xlarge', 'c3.2xlarge', 'c3.4xlarge', 'c3.8xlarge',
                            //'c4.large', 'c4.xlarge', 'c4.2xlarge', 'c4.4xlarge', 'c4.8xlarge',
                            //'r3.large', 'r3.xlarge', 'r3.2xlarge', 'r3.4xlarge', 'r3.8xlarge',
                            'i2.xlarge', 'i2.2xlarge', 'i2.4xlarge', 'i2.8xlarge',
                            'g2.2xlarge',
                            'cc1.4xlarge', 'cc2.8xlarge', 'cg1.4xlarge', 'hi1.4xlarge', 'cr1.8xlarge', 'hs1.8xlarge'
                        ];
                        defaultInstanceType = 'm1.small';
                    }
                } else {
                    if (isHvm) {
                        allowedTypes = [
                            't2.micro', 't2.small', 't2.medium',
                            'm1.small', 'm1.medium', 'm1.large', 'm1.xlarge',
                            'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge',
                            'm3.medium', 'm3.large', 'm3.xlarge', 'm3.2xlarge',
                            'c1.medium', 'c1.xlarge',
                            'c3.large', 'c3.xlarge', 'c3.2xlarge', 'c3.4xlarge', 'c3.8xlarge',
                            'c4.large', 'c4.xlarge', 'c4.2xlarge', 'c4.4xlarge', 'c4.8xlarge',
                            'r3.large', 'r3.xlarge', 'r3.2xlarge', 'r3.4xlarge', 'r3.8xlarge',
                            'hi1.4xlarge', 'hs1.8xlarge'
                        ];
                    } else {
                        allowedTypes = [
                            'm1.small', 'm1.medium', 'm1.large', 'm1.xlarge',
                            'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge',
                            'm3.medium', 'm3.large', 'm3.xlarge', 'm3.2xlarge',
                            'c1.medium', 'c1.xlarge',
                            'c3.large', 'c3.xlarge', 'c3.2xlarge', 'c3.4xlarge', 'c3.8xlarge',
                            //'c4.large', 'c4.xlarge', 'c4.2xlarge', 'c4.4xlarge', 'c4.8xlarge',
                            'hi1.4xlarge', 'hs1.8xlarge'
                        ];
                    }
                    defaultInstanceType = 'm1.small';
                }
            }
        } else if (platform === 'eucalyptus') {
            allowedTypes = [
                't1.micro',
                'm1.small', 'm1.medium', 'm1.large', 'm1.xlarge',
                'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge',
                'm3.xlarge', 'm3.2xlarge',
                'c1.medium', 'c1.xlarge',
                'hi1.4xlarge', 'hs1.8xlarge', 'cr1.8xlarge'
            ];
            defaultInstanceType = 'm1.small';

        } else if (platform === 'gce') {
            defaultInstanceType = 'n1-standard-1';
        }

        Ext.each(availableInstanceTypes, function(item) {
            var allowed = true, allowedByGovernance = true, clonedItem = Ext.clone(item);
            if (instanceType !== item.id) {
                if (allowedTypes !== undefined && !Ext.Array.contains(allowedTypes, item.id)) {
                    allowed = false;
                }
                if (limits && limits['value'] !== undefined && !Ext.Array.contains(limits['value'], item.id)) {
                    allowed = false;
                    allowedByGovernance = false;
                }
            }
            if (allowedByGovernance) {
                instanceTypes.push(clonedItem);
            }
            if (allowed) {
                firstAllowedInstanceType = firstAllowedInstanceType || item.id;
                allowedInstanceTypeCount++;
                if (defaultInstanceType == item.id) {
                    defaultInstanceTypeAllowed = true;
                }
                if (limits && !instanceType && limits['default'] == item.id ) {
                    instanceType = limits['default'];
                }
            } else {
                clonedItem.disabled = true;
            }
        });

        if (!instanceType) {
            if (defaultInstanceType && defaultInstanceTypeAllowed) {
                instanceType = defaultInstanceType;
            } else {
                instanceType = firstAllowedInstanceType;
            }
        }

        return {
            value: instanceType,
            list: instanceTypes,
            allowedInstanceTypeCount: allowedInstanceTypeCount
        };
    },

    getGceCloudLocation: function() {
        var cloudLocation = this.get('settings', true)['gce.cloud-location'] || '';
        if (cloudLocation.match(/x-scalr-custom/)) {
            cloudLocation = cloudLocation.replace('x-scalr-custom=', '').split(':');
        } else {
            if (Ext.isEmpty(cloudLocation)) {
                cloudLocation = [];
            }
            cloudLocation = [cloudLocation];
        }
        return cloudLocation;
    },

    setGceCloudLocation: function(value) {
        var settings = this.get('settings');
        if (value.length === 1) {
            settings['gce.cloud-location'] = value[0];
        } else if (value.length > 1) {
            settings['gce.cloud-location'] = 'x-scalr-custom=' + value.join(':');
        } else {
            settings['gce.cloud-location'] = '';
        }
        this.set('settings', settings);
    },

    isEc2EbsOptimizedFlagVisible: function(instType) {
        var me = this,
            result = false,
            typeString = me.get('image', true)['type'] || '';
        if (instType === undefined) {
            instType = me.get('settings', true)['aws.instance_type'];
        }
        if (typeString.indexOf('ebs') !== -1) {
            result = Ext.Array.contains([
            'c1.xlarge', 'c3.xlarge', 'c3.2xlarge', 'c3.4xlarge',
            'r3.large', 'r3.xlarge', 'r3.2xlarge', 'r3.4xlarge', 'r3.8xlarge',
            'g2.2xlarge', 'i2.xlarge', 'i2.2xlarge', 'i2.4xlarge',
            'm1.large', 'm1.xlarge', 'm2.4xlarge', 'm2.4xlarge', 'm3.xlarge','m3.2xlarge'], instType);
        }
        return result;
    },

    isEc2ClusterPlacementGroupVisible: function(instType) {
        var me = this,
            result = false;
        if (instType === undefined) {
            instType = me.get('settings', true)['aws.instance_type'];
        }
        result = Ext.Array.contains([
            'c3.large', 'c3.xlarge', 'c3.2xlarge', 'c3.4xlarge', 'c3.8xlarge','cc2.8xlarge',
            'cg1.4xlarge', 'g2.2xlarge','cr1.8xlarge', 'r3.large', 'r3.xlarge', 'r3.2xlarge',
            'r3.4xlarge', 'r3.8xlarge','hi1.4xlarge', 'hs1.8xlarge', 'i2.xlarge', 'i2.2xlarge',
            'i2.4xlarge', 'i2.8xlarge'], instType);
        return result;
    },

    getInstanceTypeParamName: function() {
        var platform = this.get('platform'),
            name;
        switch (platform) {
            case 'ec2':
                name = 'aws.instance_type';
            break;
            case 'eucalyptus':
                name = 'euca.instance_type';
            break;
            case 'gce':
                name = 'gce.machine-type';
            break;
            case 'rackspace':
                name = 'rs.flavor-id';
            break;
            default:
                if (Scalr.isOpenstack(platform)) {
                    name = 'openstack.flavor-id';
                } else if (Scalr.isCloudstack(platform)) {
                    name = 'cloudstack.service_offering_id';
                }
            break;
        }
        return name;
    },

    getDefaultStorageEngine: function() {
        var engine = '',
            platform = this.get('platform', true);

        if (platform === 'ec2' || platform === 'eucalyptus') {
            engine = 'ebs';
        } else if (platform === 'rackspace') {
            engine = 'eph';
        } else if (Scalr.isOpenstack(platform)) {
            engine = this.isMySql() ? 'lvm' : 'eph';
        } else if (platform === 'gce') {
            engine = 'gce_persistent';
        } else if (platform == 'cloudstack' || platform == 'idcf') {
            engine = 'csvol';
        }
        return engine;
    },

    getMongoDefaultStorageEngine: function() {
        var engine,
            platform = this.get('platform', true);

        if (platform === 'ec2') {
            engine = 'ebs';
        } else if (platform === 'rackspace') {
            engine = 'eph';
        } else if (platform === 'gce') {
            engine = 'gce_persistent';
        } else if (platform === 'cloudstack' || platform === 'idcf') {
            engine = 'csvol';
        } else if (Scalr.isOpenstack(platform)) {
            engine = 'cinder';
        }
        return engine;
    },

    getOldMySqlDefaultStorageEngine: function() {
        var engine,
            platform = this.get('platform', true);

        if (platform === 'ec2') {
            engine = 'ebs';
        } else if (platform === 'rackspace') {
            engine = 'eph';
        } else if (platform === 'cloudstack' || platform === 'idcf') {
            engine = 'csvol';
        }

        return engine;
    },

    getRabbitMQDefaultStorageEngine: function() {
        var engine,
            platform = this.get('platform', true);

        if (platform === 'ec2') {
            engine = 'ebs';
        } else if (Scalr.isOpenstack(platform)) {
            engine = 'cinder';
        } else if (Scalr.isCloudstack(platform)) {
            engine = 'csvol';
        } else if (platform === 'gce') {
            engine = 'gce_persistent';
        }

        return engine;
    },

    isDbMsr: function(includeDeprecated) {
        var behaviors = this.get('behaviors', true),
            db = ['mysql2', 'percona', 'redis', 'postgresql', 'mariadb'];

        if (includeDeprecated === true) {
            db.push('mysql');
        }
        behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
        return Ext.Array.some(behaviors, function(rb){
            return Ext.Array.contains(db, rb);
        });
    },

    isMySql: function() {
        var behaviors = this.get('behaviors', true),
            db = ['mysql2', 'percona', 'mariadb'];

        behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
        return Ext.Array.some(behaviors, function(rb){
            return Ext.Array.contains(db, rb);
        });
    },

    isVpcRouter: function() {
        var behaviors = this.get('behaviors', true);

        behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
        return Ext.Array.contains(behaviors, 'router');
    },

    ephemeralDevicesMap: {
        ec2: {
            'm1.small': {'ephemeral0':{'size': 150}},
            'm1.medium': {'ephemeral0':{'size': 400}},
            'm1.large': {'ephemeral0':{'size': 420}, 'ephemeral1':{'size': 420}},
            'm1.xlarge': {'ephemeral0':{'size': 420}, 'ephemeral1':{'size': 420}, 'ephemeral2':{'size': 420}, 'ephemeral3':{'size': 420}},
            'c1.medium': {'ephemeral0':{'size': 340}},
            'c1.xlarge': {'ephemeral0':{'size': 420}, 'ephemeral1':{'size': 420}, 'ephemeral2':{'size': 420}, 'ephemeral3':{'size': 420}},
            'm2.xlarge': {'ephemeral0':{'size': 410}},
            'm2.2xlarge': {'ephemeral0':{'size': 840}},
            'm2.4xlarge': {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}},
            'hi1.4xlarge': {'ephemeral0':{'size': 1000}, 'ephemeral1':{'size': 1000}},
            'cc1.4xlarge': {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}},
            'cr1.8xlarge': {'ephemeral0':{'size': 120}, 'ephemeral1':{'size': 120}},
            'cc2.8xlarge': {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}, 'ephemeral2':{'size': 840}, 'ephemeral3':{'size': 840}},
            'cg1.4xlarge': {'ephemeral0':{'size': 840}, 'ephemeral1':{'size': 840}},
            'hs1.8xlarge': {'ephemeral0':{'size': 12000}, 'ephemeral1':{'size': 12000}, 'ephemeral2':{'size': 12000}, 'ephemeral3':{'size': 12000}}
        },
        gce: {
            'n1-highcpu-2-d': {'google-ephemeral-disk-0':{'size': 870}},
            'n1-highcpu-4-d': {'google-ephemeral-disk-0':{'size': 1770}},
            'n1-highcpu-8-d': {'google-ephemeral-disk-0':{'size': 1770}, 'google-ephemeral-disk-1':{'size': 1770}},
            'n1-highmem-2-d': {'google-ephemeral-disk-0':{'size': 870}},
            'n1-highmem-4-d': {'google-ephemeral-disk-0':{'size': 1770}},
            'n1-highmem-8-d': {'google-ephemeral-disk-0':{'size': 1770}, 'google-ephemeral-disk-1':{'size': 1770}},
            'n1-standard-1-d': {'google-ephemeral-disk-0':{'size': 420}},
            'n1-standard-2-d': {'google-ephemeral-disk-0':{'size': 870}},
            'n1-standard-4-d': {'google-ephemeral-disk-0':{'size': 1770}},
            'n1-standard-8-d': {'google-ephemeral-disk-0':{'size': 1770}, 'google-ephemeral-disk-1':{'size': 1770}}
        }
    },

    getEphemeralDevicesMap: function() {
        return this.ephemeralDevicesMap[this.get('platform')];

    },

    getAvailableStorages: function() {
        var platform = this.get('platform'),
            ephemeralDevicesMap = this.getEphemeralDevicesMap(),
            settings = this.get('settings', true),
            storages = [];

        if (platform === 'ec2') {
            storages.push({name:'ebs', description:'Single EBS Volume'});
            storages.push({name:'raid.ebs', description:'RAID array on EBS volumes'});

            if (this.isMySql()) {
                if (Ext.isDefined(ephemeralDevicesMap[settings['aws.instance_type']])) {
                    storages.push({name:'lvm', description:'LVM on ephemeral devices'});
                }
                if (settings['db.msr.data_storage.engine'] == 'eph' || Scalr.flags['betaMode']) {
                    storages.push({name:'eph', description:'Ephemeral device'});
                }
            } else {
                storages.push({name:'eph', description:'Ephemeral device'});
            }
        } else if (platform === 'eucalyptus') {
            storages.push({name:'ebs', description:'Single EBS Volume'});
            storages.push({name:'raid.ebs', description:'RAID array on EBS volumes'});
        } else if (platform === 'rackspace') {
            storages.push({name:'eph', description:'Ephemeral device'});
        } else if (platform === 'gce') {
            storages.push({name:'gce_persistent', description:'GCE Persistent disk'});
        } else if (Scalr.isOpenstack(platform)) {
            if (Scalr.getPlatformConfigValue(platform, 'ext.cinder_enabled') == 1) {
                storages.push({name:'cinder', description:'Cinder volume'});
            }
            if (Scalr.getPlatformConfigValue(platform, 'ext.swift_enabled') == 1) {
                if (this.isMySql()) {
                    storages.push({name:'lvm', description:'LVM on loop device (75% from /)'});
                } else {
                    storages.push({name:'eph', description:'Ephemeral device'});
                }
            }
        } else if (Scalr.isCloudstack(platform)) {
            storages.push({name:'csvol', description:'CloudStack Block Volume'});
        }
        return storages;
    },

    getAvailableStorageFs: function(featureMFS) {
        var list,
            osFamily = this.get('os_family'),
            arch = this.get('image', true)['architecture'],
            osVersion = this.get('os_generation'),
            extraFs = (osFamily === 'centos' && arch === 'x86_64') ||
                      (osFamily === 'ubuntu' && (osVersion == '10.04' || osVersion == '12.04' || osVersion == '14.04')),
            disabledText = extraFs && !featureMFS ? 'Not available for your pricing plan' : '';
        list = [
            {value: 'ext3', text: 'Ext3'},
            {value: 'ext4', text: 'Ext4', unavailable: !extraFs || !featureMFS, disabled: !extraFs || !featureMFS, tooltip: disabledText},
            {value: 'xfs', text: 'XFS', unavailable: !extraFs || !featureMFS, disabled: !extraFs || !featureMFS, tooltip: disabledText}
        ];
        return list;
    },

    storageDisks: {
        ec2: {
            '/dev/sda2': {'m1.small':1, 'c1.medium':1},
            '/dev/sdb': {'m1.medium':1, 'm1.large':1, 'm1.xlarge':1, 'c1.xlarge':1, 'cc1.4xlarge':1, 'cc2.8xlarge':1, 'cr1.8xlarge':1, 'm2.xlarge':1, 'm2.2xlarge':1, 'm2.4xlarge':1},
            '/dev/sdc': {               'm1.large':1, 'm1.xlarge':1, 'c1.xlarge':1, 'cc1.4xlarge':1, 'cc2.8xlarge':1, 'cr1.8xlarge':1},
            '/dev/sdd': {						 	  'm1.xlarge':1, 'c1.xlarge':1, 			   	 'cc2.8xlarge':1 },
            '/dev/sde': {						 	  'm1.xlarge':1, 'c1.xlarge':1, 			     'cc2.8xlarge':1 },

            '/dev/sdf': {'hi1.4xlarge':1 },
            '/dev/sdg': {'hi1.4xlarge':1 }
        }
    },
    getAvailableStorageDisks: function() {
        var platform = this.get('platform'),
            settings = this.get('settings', true),
            disks = [];

        disks.push({'device':'', 'description':''});
        if (platform === 'ec2') {
            Ext.Object.each(this.storageDisks['ec2'], function(key, value){
                if (value[settings['aws.instance_type']] === 1) {
                    disks.push({'device': key, 'description':'LVM on ' + key + ' (80% available for data)'});
                }
            });
        } else if (Scalr.isOpenstack(platform) || platform === 'rackspace') {
            disks.push({'device':'/dev/loop0', 'description':'Loop device (75% from /)'});
        } else if (platform === 'gce') {
            disks.push({'device':'ephemeral-disk-0', 'description':'Loop device (80% of ephemeral-disk-0)'});
        }
        return disks;
    },

    getAvailableStorageRaids: function() {
        return [
            {name:'0', description:'RAID 0 (block-level striping without parity or mirroring)'},
            {name:'1', description:'RAID 1 (mirroring without parity or striping)'},
            {name:'5', description:'RAID 5 (block-level striping with distributed parity)'},
            {name:'10', description:'RAID 10 (mirrored sets in a striped set)'}
        ];
    },

    isMultiEphemeralDevicesEnabled: function(){
        var res = false,
            settings = this.get('settings', true);
        if (this.get('platform') === 'ec2' && !settings['db.msr.data_storage.eph.disk']) {
            var behaviors = this.get('behaviors', true);
            behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
            res = Ext.Array.contains(behaviors, 'postgresql');
        }
        return res;
    },

    hasBehavior: function(behavior) {
        var behaviors = this.get('behaviors', true);

        behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');
        return Ext.Array.contains(behaviors, behavior);
    },

    loadRoleChefSettings: function(cb) {
        var record = this,
            chefSettings = {};
        Scalr.CachedRequestManager.get('farmbuilder').load(
            {
                url: '/farms/builder/xGetRoleChefSettings/',
                params: {
                    roleId: record.get('role_id')
                }
            },
            function(data, status) {
                var roleChefEnabled = Ext.isObject(data['chef']) && data['chef']['chef.bootstrap'] == 1,
                    farmRoleChefSettings = {};
                if (status) {
                    Ext.Object.each(record.get('settings', true) || {}, function(key, value){
                        if (key.indexOf('chef.') === 0) {
                            farmRoleChefSettings[key] = value;
                        }
                    });
                    
                    if (roleChefEnabled) {
                        Ext.apply(chefSettings, data['chef']);
                        if (farmRoleChefSettings['chef.attributes']) {
                            chefSettings['chef.attributes'] = farmRoleChefSettings['chef.attributes'];
                        }
                    } else {
                        Ext.apply(chefSettings, farmRoleChefSettings);
                    }
                }
                cb({
                    chefSettings: chefSettings,
                    roleChefSettings: roleChefEnabled ? data['chef'] : null,
                    farmRoleChefSettings: farmRoleChefSettings
                }, status);
            }
        );

    },

    loadEBSEncryptionSupport: function(cb, instType) {
        var platform = this.get('platform'),
            cloudLocation = this.get('cloud_location'),
            encryption = false;
        instType = instType || this.get('settings', true)['aws.instance_type']
        Scalr.loadInstanceTypes(platform, cloudLocation, function(data, status){
            Ext.each(data, function(i){
                if (i.id === instType) {
                    encryption = i.ebsencryption;
                    return false;
                }
            });
            cb(encryption);
        });

    },
    
    loadHourlyRate: function(instanceType, cb) {
        var me = this,
            settings = me.get('settings', true);
        Scalr.CachedRequestManager.get('farmbuilder').load({
            url: '/farms/builder/xGetInstanceTypeHourlyRate',
            params: {
                platform: me.get('platform'),
                cloudLocation: me.get('cloud_location'),
                instanceType: instanceType || settings[me.getInstanceTypeParamName()],
                osFamily: me.get('os_family')
            }
        }, function(data, status){
                if (status) {
                    me.set('hourly_rate', data['hourly_rate']);
                    cb(data['hourly_rate']);
                }
            }
        );
    }
});

Ext.define('Scalr.ui.FormInstanceTypeField', {
	extend: 'Ext.form.field.ComboBox',
	alias: 'widget.instancetypefield',

    editable: true,
    hideInputOnReadOnly: true,
    queryMode: 'local',
    fieldLabel: 'Instance type',
    anyMatch: true,
    autoSearch: false,
    selectOnFocus: true,
    icons: {
        governance: true
    },
    governanceTooltip: 'The account owner has limited which instance types can be used in this Environment',
    store: {
        fields: [ 'id', 'name', 'note', 'ram', 'type', 'vcpus', 'disk', 'ebsencryption', 'disabled' ],
        proxy: 'object',
        sorters: {
            property: 'disabled'
        }
    },
    valueField: 'id',
    displayField: 'name',
    listConfig: {
        emptyText: 'No instance type matching query',
        emptyTextTpl: new Ext.XTemplate(
            '<div style="margin:8px 8px 0">' +
                '<b>No instance type matching query</b>' +
                '<div style="line-height:24px">Instance types unavailable in <i>{cloudLocation}</i><tpl if="limits"> or restricted by Governance</tpl> are not listed</div>' +
            '</div>'
        ),
        cls: 'x-boundlist-alt',
        tpl:
            '<tpl for="."><div class="x-boundlist-item" style="white-space:nowrap;height: auto; width: auto;<tpl if="disabled">color:#999</tpl>">' +
                '<div><b>{name}</b> &nbsp;<tpl if="disabled"><span style="font-size:12px;font-style:italic">(Not compatible with the selected image)</span></tpl></div>' +
                '<div style="line-height: 26px;white-space:nowrap;">{[this.instanceTypeInfo(values)]}</div>' +
            '</div></tpl>'
    },
    updateListEmptyText: function(data) {
        var picker = this.getPicker();
        if (picker) {
            picker.emptyText = picker.emptyTextTpl.apply(data);
        }
    },
    initComponent: function() {
        this.callParent(arguments);
        this.on('specialkey', function(comp, e){
            if(!comp.readOnly && e.getKey() === e.ESC){
                comp.reset();
            }
        });
        this.on('blur', function(comp){
            if (!comp.readOnly && !comp.findRecordByValue(comp.getValue())) {
                comp.reset();
            }
        });
        this.on('afterrender', function(comp){
            comp.inputEl.on('click', function(){
                comp.expand();
            });
        });
        this.on('change', function(comp){
            if (comp.findRecordByValue(comp.getValue())) {
                comp.resetOriginalValue();
            }
        });
        this.on('beforeselect', function(comp, record){
            if (record.get('disabled')) {
                return false;
            }
        });
    }
});

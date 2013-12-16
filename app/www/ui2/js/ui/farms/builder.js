Scalr.regPage('Scalr.ui.farms.builder', function (loadParams, moduleParams) {
	var reconfigurePage = function(params) {
        var record;
        if (params['roleId']) {
            record = farmRolesStore.findRecord('role_id', params['roleId']);
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
            isMultiRoleAllowed = function(behaviors){
                behaviors = behaviors || [];
                var allowed = behaviors.length > 0,
                    disallowedBehaviors = ['mysql', 'mysql2', 'percona', 'redis', 'postgresql', 'mariadb', 'mongodb'];
                Ext.Array.each(behaviors, function(behavior){
                    if (Ext.Array.contains(disallowedBehaviors, behavior)) {
                        return allowed = false;
                    }
                });
                return allowed;
            };

        if (!(/^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/).test(alias)) {
            Scalr.message.Error('Alias should start and end with letter or number and contain only letters, numbers and dashes');
            return false;
        } else if (farmRolesStore.countBy('alias', alias) > 0) {
            Scalr.message.Error('Alias must be unique within the farm');
            return false;
        }

        if (!isMultiRoleAllowed(behaviors)) {
            if (
                farmRolesStore.queryBy(function(record) {
                    if (
                        record.get('platform') == role.get('platform') &&
                        record.get('role_id') == role.get('role_id') &&
                        (record.get('cloud_location') == role.get('cloud_location') || record.get('platform') === 'gce')
                    )
                        return true;
                }).length > 0
            ) {
                Scalr.message.Error('Role "' + role.get('name') + '" already added');
                return false;
            }

            // check before adding
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

            if (Ext.Array.contains(behaviors, 'postgresql')) {
                if (
                    farmRolesStore.queryBy(function(record) {
                        if (record.get('behaviors').match('postgresql'))
                            return true;
                    }).length > 0
                ) {
                    Scalr.message.Error('Only one PostgreSQL role can be added to farm');
                    return false;
                }
            }

            if (Ext.Array.contains(behaviors, 'redis')) {
                if (
                    farmRolesStore.queryBy(function(record) {
                        if (record.get('behaviors').match('redis'))
                            return true;
                    }).length > 0
                ) {
                    Scalr.message.Error('Only one Redis role can be added to farm');
                    return false;
                }
            }

            if (Ext.Array.contains(behaviors, 'mongodb')) {
                if (
                    farmRolesStore.queryBy(function(record) {
                        if (record.get('behaviors').match('mongodb'))
                            return true;
                    }).length > 0
                ) {
                    Scalr.message.Error('Only one MongoDB role can be added to farm');
                    return false;
                }
            }

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
        Scalr.message.Success('Role "' + role.get('alias') + '" added');

    };

    var saveErrorHandler = function(errors) {
        var fbcard = farmbuilder.getComponent('fbcard'),
            activeRole;
        (farmRolesStore.snapshot || farmRolesStore.data).each(function (rec) {
            rec.set('errors', undefined);
        });
        errors = errors || {};
        if (errors.roles !== undefined) {
            Ext.Object.each(errors.roles, function(farmRoleId, errors){
                var role = farmRolesStore.query('farm_role_id', farmRoleId).first();
                if (role) {
                    role.set('errors', errors);
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
                if (field && field.markInvalid) {
                    field.markInvalid(error);
                }
            });
        }

        if (errors.farm === undefined && activeRole !== undefined) {
            //var editPanel = fbcard.getComponent('edit');
            farmbuilder.getComponent('farmroles').select(activeRole);
        } else {
            fbcard.layout.setActiveItem('farm');
        }
    };

    var saveHandler = function (farm) {
        var p = {};
        farm = farm || {};

        farmbuilder.down('#farmroles').deselectAll();
        farmbuilder.down('#farmroles').clearFilter();
        farmbuilder.getComponent('fbcard').layout.setActiveItem('farm');

        farm['farmId'] = moduleParams['farmId'];

        p['name'] = farmbuilder.down('#farmName').getValue();
        p['description'] = farmbuilder.down('#farmDescription').getValue();
        p['timezone'] = farmbuilder.down('#timezone').getValue();
        p['rolesLaunchOrder'] = farmbuilder.down('#launchorder').getValue();
        p['variables'] = farmbuilder.down('#variables').getValue();

        //vpc
        var vpcEnabledField = farmbuilder.down('[name="vpc_enabled"]'),
            vpcRegionField = farmbuilder.down('[name="vpc_region"]'),
            vpcIdField = farmbuilder.down('[name="vpc_id"]');
        if (vpcEnabledField.getValue()) {
            var vpcLimits = farmbuilder.getLimits('aws.vpc');
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
                Scalr.event.fireEvent('redirect', '#/farms/' + data.farmId + '/view');
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
                addrole: function () {
                    this.deselectAll();
                    var card = farmbuilder.down('#fbcard');
                    if (!card.getComponent('add')) {
                        card.add({
                            xtype: 'roleslibrary',
                            moduleParams: moduleParams,
                            hidden: true,
                            autoRender: false,
                            itemId: 'add',
                            listeners: {
                                activate: function() {
                                    farmbuilder.updateTitle('Add new role');
                                    farmbuilder.getComponent('farmroles').down('dataview').getPlugin('flyingbutton').setDisabled(true);
                                },
                                deactivate: function() {
                                    farmbuilder.getComponent('farmroles').down('dataview').getPlugin('flyingbutton').setDisabled(false);
                                },
                                addrole: addRoleHandler,
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
                        maxWidth: 700,
                        defaults: {
                            anchor: '100%',
                            labelWidth: 80
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
                            editable: true,
                            queryMode: 'local',
                            anyMatch: true
                        }, {
                            xtype: 'textarea',
                            name: 'description',
                            itemId: 'farmDescription',
                            fieldLabel: 'Description',
                            grow: true,
                            growMin: 70
                        }]
                    },{
                        xtype: 'fieldset',
                        title: 'Roles launch order',
                        cls: 'x-fieldset-separator-none',
                        width: 470,
                        defaults: {
                            anchor: '100%',
                            labelWidth: 80
                        },
                        items: [{
                            xtype: 'buttongroupfield',
                            name: 'rolesLaunchOrder',
                            itemId: 'launchorder',
                            columns: 1,
                            listeners: {
                                change: function(comp, value) {
                                    farmbuilder.down('#launchorderinfo').setValue(value == 1?'Use drag and drop to adjust the launching order of roles.':'Launch all roles at the same time.');
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
                            xtype: 'displayfield',
                            itemId: 'launchorderinfo',
                            cls: 'x-form-field-info',
                            value: 'Launch all roles at the same time.'
                        }]
                    }]
                },{
                    xtype: 'displayfield',
                    itemId: 'vpcinfo',
                    cls: 'x-form-field-info',
                    maxWidth: 740,
                    margin: '18 32 8',
                    hidden: true,
                    value: 'VPC settings can be changed on TERMINATED farm only.'
                },{
                    xtype: 'fieldset',
                    itemId: 'vpc',
                    title: '&nbsp;',
                    baseTitle: 'Launch this farm inside VPC',
                    toggleOnTitleClick: true,
                    checkboxToggle: true,
                    collapsed: true,
                    collapsible: true,
                    hidden: !moduleParams['farmVpcEc2Enabled'],
                    checkboxName: 'vpc_enabled',
                    layout: 'hbox',
                    disableToggle: function(disable) {
                        this.toggleDisabled = disable;
                        this.checkboxCmp.setDisabled(disable);
                    },
                    listeners: {
                        beforecollapse: function() {
                            if (this.toggleDisabled) {
                                this.checkboxCmp.setValue(true);
                                return false;
                            }
                        },
                        beforeexpand: function() {
                            if (this.toggleDisabled) {
                                this.checkboxCmp.setValue(false);
                                return false;
                            }
                        }
                    },
                    items: [{
                        xtype: 'combo',
                        width: 300,
                        name: 'vpc_region',
                        emptyText: 'Please, select VPC region',
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
                            change: function(field, value) {
                                var vpcIdField = field.next(),
                                    vpcIdFieldProxy = vpcIdField.store.getProxy(),
                                    vpcLimits = farmbuilder.getLimits('aws.vpc'),
                                    disableAddNew = false;

                                vpcIdField.reset();
                                vpcIdField.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + value;
                                vpcIdFieldProxy.params = {cloudLocation: value};
                                delete vpcIdFieldProxy.data;

                                if (vpcLimits && vpcLimits['regions'] && vpcLimits['regions'][value]) {
                                    if (vpcLimits['regions'][value]['ids'] && vpcLimits['regions'][value]['ids'].length > 0) {
                                        var vpcList = Ext.Array.map(vpcLimits['regions'][value]['ids'], function(vpcId){
                                            return {id: vpcId, name: vpcId};
                                        });
                                        vpcIdFieldProxy.data = vpcList;
                                        vpcIdField.store.load();
                                        vpcIdField.setValue(vpcIdField.store.first());
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
                        emptyText: 'Please, select VPC ID',
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
                            url: '/tools/aws/vpc/create'
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
                            }
                        }
                    }]
                }, {
                    xtype: 'fieldset',
                    title: 'Global variables',
                    cls: 'x-fieldset-separator-none',
                    hidden: false,
                    items: [{
                        xtype: 'variablefield',
                        name: 'variables',
                        itemId: 'variables',
                        maxWidth: 1200,
                        currentScope: 'farm',
                        addFieldCls: 'scalr-ui-addfield-light',
                        encodeParams: false,
                        listeners: {
                            addvar: function(item) {
                                (farmRolesStore.snapshot || farmRolesStore.data).each(function() {
                                    var variables = this.get('variables', true) || [], flag = true;
                                    for (var i = 0; i < variables.length; i++) {
                                        if (variables[i]['name'] == item['name']) {
                                            flag = false;
                                        }
                                    }
                                    if (flag) {
                                        item['defaultValue'] = item['value'];
                                        item['defaultScope'] = item['scope'];
                                        item['value'] = '';
                                        variables.push(item);
                                    }

                                    this.set('variables', variables);
                                });
                            },
                            editvar: function(item) {
                                (farmRolesStore.snapshot || farmRolesStore.data).each(function() {
                                    var variables = this.get('variables', true) || [];

                                    for (var i = 0; i < variables.length; i++) {
                                        if (variables[i]['name'] == item['name']) {
                                            if (item['flagFinal']) {
                                                variables[i]['flagFinalGlobal'] = item['flagFinal'];
                                                variables[i]['flagFinal'] = item['flagFinal'];
                                                variables[i]['scope'] = item['scope'];
                                                variables[i]['value'] = '';
                                                variables[i]['defaultScope'] = item['scope'];
                                                variables[i]['defaultValue'] = item['value'];
                                            } else {
                                                variables[i]['flagFinalGlobal'] = '';
                                                variables[i]['flagFinal'] = '';
                                            }

                                            if (variables[i]['defaultScope'] == item['scope']) {
                                                variables[i]['defaultValue'] = item['value'];

                                            } else if ((variables[i]['defaultScope'] == item['defaultScope']) && item.value) {
                                                // we change item's scope from env to farm, add as defaultScope
                                                if (variables[i]['scope'] == variables[i]['defaultScope']) {
                                                    variables[i]['scope'] = item['scope'];
                                                }

                                                variables[i]['defaultScope'] = item['scope'];
                                                variables[i]['defaultValue'] = item['value'];

                                            } else if (variables[i]['defaultScope'] == 'farm' && !item.value) {
                                                variables[i]['defaultScope'] = item['defaultScope'];
                                                variables[i]['defaultValue'] = item['defaultValue'];
                                                if (variables[i]['scope'] == 'farm') {
                                                    variables[i]['scope'] = item['scope'];
                                                }
                                            }
                                        }
                                    }

                                    this.set('variables', variables);
                                });
                            },
                            removevar: function(item) {
                                (farmRolesStore.snapshot || farmRolesStore.data).each(function() {
                                    var variables = this.get('variables', true) || [], result = [];

                                    for (var i = 0; i < variables.length; i++) {
                                        if (! (variables[i]['name'] == item['name'] && variables[i]['scope'] == item['scope'])) {
                                            result.push(variables[i]);
                                        }
                                    }

                                    this.set('variables', result);
                                });
                            }
                        }
                    }]
                }],
                listeners: {
                    boxready: function() {
                        var form = farmbuilder.down('#farm'),
                            farm = moduleParams.farm ? Ext.clone(moduleParams.farm.farm) : {isNew: true},
                            vpcFieldset = form.down('#vpc'),
                            disallowVpcToggle = false,
                            defaultVpcEnabled = false;
                        Ext.apply(farm, {
                            timezone: farm.timezone || moduleParams['timezone_default'],
                            rolesLaunchOrder: farm.rolesLaunchOrder || '0',
                            variables: farm.variables || moduleParams.farmVariables,
                            status: farm.status || 0
                        });

                        if (moduleParams['farmVpcEc2Enabled']) {
                            var vpcLimits = farmbuilder.getLimits('aws.vpc');
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
                                    field.disable();
                                });
                                form.down('#vpcinfo').show();
                            }
                        }
                        form.setFieldValues(farm);
                        vpcFieldset.disableToggle(disallowVpcToggle);
                        reconfigurePage(loadParams);
                    },
                    activate: function() {
                        farmbuilder.down('#farmroles').toggleFarmButton(true);
                        farmbuilder.updateTitle();
                    },
                    deactivate: function() {
                        // automatically add variable when tab hided
                        var el = this.down('#variables').down('variablevaluefield:last');
                        if (el.down('[name="newValue"]').getValue() == 'true' && el.down('[name="name"]').getValue())
                            el.getPlugin('addfield').run();

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

    if (moduleParams['farm'] && moduleParams['farm']['lock'])
        Scalr.message.Warning(moduleParams['farm']['lock'] + ' You won\'t be able to save any changes.');

    Ext.apply(moduleParams['tabParams'], {
        farmRolesStore: farmRolesStore,
        behaviors: moduleParams['behaviors'] || {},
        platforms: moduleParams['platforms'] || {},
        metrics: moduleParams['metrics'] || {},
        farm: moduleParams['farm'] ? moduleParams['farm'].farm : {}
    });


    farmbuilder.getLimits = function (name){
        var limits = moduleParams.governance || {};
        return name !== undefined ? limits[name] : limits;
    }

    farmbuilder.down('#edit').createTabs();

	Scalr.event.on('update', function (type, data) {
		if (type == '/farms/roles/replaceRole' && data['farmId'] == loadParams['farmId']) {
            var record = farmRolesStore.findRecord('farm_role_id', data['farmRoleId']),
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
            dock: 'bottom',
            cls: 'x-docked-buttons-mini',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'Save',
                disabled: moduleParams['farm'] ? !!moduleParams['farm']['lock'] : false,
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
        'arch',
        'image_id',
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
        'tags',
        'variables',
        'running_servers',
        'security_groups'
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

    getEucaInstanceType: function(limits) {
        var me = this,
            settings = me.get('settings', true),
            tagsString = (me.get('tags', true) || []).join(' '),
            behaviors = me.get('behaviors', true),
            result = {
                list: [],
                value: ''
            };

        behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');

        result.list = [
            't1.micro', 
            'm1.small', 'm1.medium', 'm1.large', 'm1.xlarge', 
            'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge', 
            'm3.xlarge', 'm3.2xlarge', 
            'c1.medium', 'c1.xlarge',
            'hi1.4xlarge', 'hs1.8xlarge', 'cr1.8xlarge'
        ];
        result.value = (settings['euca.instance_type'] || 'm1.small');

        if (limits) {
            if (limits['value'] !== undefined) {
                result.list = Ext.Array.intersect(limits['value'], result.list);
            }
            if (!settings['euca.instance_type']) {
                if (limits['default'] !== undefined && Ext.Array.contains(result.list, limits['default'])) {
                    result.value = limits['default'];
                } else {
                    result.value = result.list.length > 0 ? result.list[0] : null;
                }
            }
        }
        return result;
    },
    
    getEc2InstanceType: function(limits) {
        var me = this,
            settings = me.get('settings', true),
            tagsString = (me.get('tags', true) || []).join(' '),
            behaviors = me.get('behaviors', true),
            result = {
                list: [],
                value: ''
            };

        behaviors = Ext.isArray(behaviors) ? behaviors : behaviors.split(',');

        if (me.get('arch') === 'i386') {
            if ((tagsString.indexOf('ec2.ebs') != -1 || settings['aws.instance_type'] == 't1.micro') && !Ext.Array.contains(behaviors, 'cf_cloud_controller')) {
                result.list = ['t1.micro', 'm1.small', 'm1.medium', 'c1.medium'];
            } else {
                result.list = ['m1.small', 'm1.medium', 'c1.medium'];
            }
            result.value = settings['aws.instance_type'] || 'm1.small';
        } else {
            result.value = settings['aws.instance_type'] || 'm1.small';

            if (tagsString.indexOf('ec2.ebs') != -1 || settings['aws.instance_type'] == 't1.micro') {
                if (tagsString.indexOf('ec2.hvm') != -1 && me.get('os') != '2008Server' && me.get('os') != '2008ServerR2' && me.get('os_family') != 'windows') {
                    result.list = ['cc1.4xlarge', 'cc2.8xlarge', 'cg1.4xlarge', 'hi1.4xlarge', 'cr1.8xlarge', 'g2.2xlarge'];
                    if (settings['aws.instance_type'] != 'm1.large') {
                        result.value = settings['aws.instance_type'] || 'cc1.4xlarge';
                    } else {
                        result.value = 'cc1.4xlarge';
                    }
                } else {
                    result.list = [
						't1.micro', 
						'm1.small', 'm1.medium', 'm1.large', 'm1.xlarge',
						'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge', 
						'm3.xlarge', 'm3.2xlarge',
						'c1.medium', 'c1.xlarge',
						'c3.large', 'c3.xlarge', 'c3.2xlarge', 'c3.4xlarge', 'c3.8xlarge',
						'i2.large', 'i2.xlarge', 'i2.2xlarge', 'i2.4xlarge', 'i2.8xlarge',
						'g2.2xlarge',
						'cc1.4xlarge', 'cc2.8xlarge', 'cg1.4xlarge', 'hi1.4xlarge', 'cr1.8xlarge'
                    ];
                    result.value = (settings['aws.instance_type'] || 'm1.small');
                }
            } else {
                result.list = ['m1.large', 'm1.xlarge', 'c1.xlarge', 'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge'];
                result.value = settings['aws.instance_type'] || 'm1.large';
            }
        }

        if (limits) {
            if (limits['value'] !== undefined) {
                result.list = Ext.Array.intersect(limits['value'], result.list);
            }
            if (!settings['aws.instance_type']) {
                if (limits['default'] !== undefined && Ext.Array.contains(result.list, limits['default'])) {
                    result.value = limits['default'];
                } else {
                    result.value = result.list.length > 0 ? result.list[0] : null;
                }
            }
        }
        return result;
    },

    isEc2EbsOptimizedFlagVisible: function(instType) {
        var me = this,
            result = false,
            tagsString = (me.get('tags', true) || []).join(' ');
        if (instType === undefined) {
            instType = me.getEc2InstanceType()['value'];
        }
        if (tagsString.indexOf('ec2.ebs') !== -1) {
            result = Ext.Array.contains(['m1.large', 'm1.xlarge', 'm2.4xlarge', 'm3.xlarge','m3.2xlarge'], instType);
        }
        return result;
    },

    getDefaultStorageEngine: function() {
        var engine = '',
            platform = this.get('platform', true);

        if (platform === 'ec2') {
            engine = 'ebs';
        } else if (platform === 'rackspace') {
            engine = 'eph';
        } else if (Scalr.isOpenstack(platform)) {
            engine = this.isMySql() ? 'lvm' : 'eph';
        } else if (platform === 'gce') {
            engine = 'gce_persistent';
        } else if (platform == 'cloudstack' || platform == 'idcf' || platform == 'ucloud') {
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
        } else if (platform === 'cloudstack' || platform === 'idcf' || platform === 'ucloud') {
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
            //remove gce eph
            /*if (this.isMySql()) {
                storages = [{name:'lvm', description:'LVM on ephemeral devices'}];
            } else {
                storages.push({name:'eph', description:'Ephemeral device'});
            }*/

            storages.push({name:'gce_persistent', description:'GCE Persistent disk'});
            if (Scalr.flags['betaMode']) {
                storages.push({name:'raid.gce_persistent', description:'RAID array on GCE Persistent disk'});
            }
        } else if (Scalr.isOpenstack(platform)) {
            storages.push({name:'cinder', description:'Cinder volume'});

            if (this.isMySql()) {
                storages.push({name:'lvm', description:'LVM on loop device (75% from /)'});
            } else {
                storages.push({name:'eph', description:'Ephemeral device'});
            }


        } else if (Ext.Array.contains(['cloudstack', 'idcf', 'ucloud'], platform)) {
            storages.push({name:'csvol', description:'CloudStack Block Volume'});
        }
        return storages;
    },

    getAvailableStorageFs: function(featureMFS) {
        var list,
            osFamily = this.get('os_family'),
            arch = this.get('arch'),
            osVersion = this.get('os_generation'),
            extraFs = (osFamily === 'centos' && arch === 'x86_64') ||
                      (osFamily === 'ubuntu' && (osVersion == '10.04' || osVersion == '12.04')),
            disabledText = extraFs && !featureMFS ? 'Not available for your pricing plan' : '';
        list = [
            {value: 'ext3', text: 'Ext3'},
            {value: 'ext4', text: 'Ext4', disabled: !extraFs || !featureMFS, tooltip: disabledText},
            {value: 'xfs', text: 'XFS', disabled: !extraFs || !featureMFS, tooltip: disabledText}
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
});
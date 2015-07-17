Scalr.regPage('Scalr.ui.farms.builder', function (loadParams, moduleParams) {
    var farmRolesSorters = [{
			property: 'launch_index',
			direction: 'ASC'
		}],
        farmRolesStore = Ext.create('store.store', {
            model: Scalr.ui.getFarmRoleModel(),
            proxy: 'object',
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
                this.getUnfiltered().each(function(record) {
                    var i = record.get('launch_index');
                    index = i > index ? i : index;
                });
                return ++index;
            },
            resetLaunchIndexes: function() {
                var data = this.queryBy(function(){return true;}),
                    index = 0;
                data.sort(farmRolesSorters);
                data.each(function(record) {
                    record.set('launch_index', index++);
                });
                //this.sort(farmRolesSorters);
            },

            updateLaunchIndex: function(record, launchIndex) {
                var currentLaunchIndex = record.get('launch_index');
                this.suspendEvents(true);
                this.getUnfiltered().each(function(rec) {
                    var recLaunchIndex = rec.get('launch_index');
                    if (recLaunchIndex >= launchIndex) {
                        rec.set('launch_index', recLaunchIndex + 1);
                    }
                });
                record.set('launch_index', launchIndex);

                this.getUnfiltered().each(function(rec) {
                    var recLaunchIndex = rec.get('launch_index');
                    if (recLaunchIndex > currentLaunchIndex) {
                        rec.set('launch_index', recLaunchIndex - 1);
                    }
                });
                //this.sort(farmRolesSorters);
                this.resumeEvents();
            }

        });

    var farmDesigner = Ext.create('Ext.panel.Panel', {
        //title: 'Farm Designer',
        updateTitle: function(farmName, roleName) {
            if (farmName) {
                var btn = this.getComponent('farmRoles').down('#farmSettingsBtn');
                btn.setText(farmName);
                btn[farmName ? 'removeCls' : 'addCls']('x-btn-new-farm');
                btn.setTooltip(farmName.length > 10 ? farmName : '');
            }
            //this.setTitle((this.moduleParams.farm ? this.moduleParams.farm.farm.name : 'Farm Designer &raquo; ') + (text ? ' &raquo; ' + text : ''));
        },
        scalrOptions: {
            menuTitle: 'Farms',
            menuSubTitle: 'Farm Designer',
            menuParentStateId: 'grid-farms-view',
            menuHref: '#/farms',
            maximize: 'all',
            reload: false,
            menuFavorite: true
            //beforeClose: function() {
            //    return false;
            //}
        },
        itemId: 'farmDesigner',
        plugins: {
            ptype: 'localcachedrequest',
            crscope: 'farmDesigner'
        },
		layout: {
			type: 'hbox',
			align : 'stretch',
			pack  : 'start'
		},
        listeners: {
            applyparams: 'loadFarm'
        },
        items: [{
            xtype: 'panel',
            itemId: 'farmRoles',
            cls: 'x-docked-tabs x-docked-tabs-farm-settings',
            width: 190 + Ext.getScrollbarSize().width,
            layout: 'fit',
            items: [{
                xtype: 'farmrolesview',
                store:  farmRolesStore,
                listeners: {
                    selectionchange: function(c, selections) {
                        var ct = this.up();
                        if (selections[0]) {
                            this.toggleRolesLibraryBtn(false);
                            ct.down('#farmSettingsBtn').setPressed(false);
                            farmDesigner.showFarmRoleEditor(selections[0]);
                        } else {
                            farmDesigner.showFarmSettings();
                        }
                    },
                    roleslibrary: function(c) {
                        farmDesigner.showRolesLibrary();
                    }
                }
            }],
            dockedItems: [{
                dock: 'top',
                xtype: 'button',
                itemId: 'farmSettingsBtn',
                ui: 'tab',
                allowDepress: false,
                disableMouseDownPressed: true,
                hrefTarget: null,
                toggleGroup: 'farmdesigner-farm-settings',
                iconCls: 'x-btn-icon-settings',
                text: 'Farm settings',
                textAlign: 'left',
                cls: 'x-btn-farm-settings',
                handler: function(comp, state) {
                    if (state) {
                        this.up().down('farmrolesview').getSelectionModel().deselectAll();
                        farmDesigner.showFarmSettings();
                    }
                }
            }, {
                dock: 'top',
                xtype: 'filterfield',
                store: farmRolesStore,
                emptyText: 'Filter farm roles',
                filterFields: ['alias', 'platform', 'cloud_location'],
                margin: 12,
                listeners: {
                    afterfilter: function() {
                        //me.down('dataview').getStore().sort();//if launch order was updated on filtered store we must to re-apply sorting
                    }
                }

            }]
        },{
            xtype: 'container',
            itemId: 'farmDesignerCard',
            layout: 'card',
            flex: 1,
            items: [{
                xtype: 'component',
                itemId: 'blank'
            },{
                xtype: 'farmsettings',
                itemId: 'farmSettings',
                listeners: {
                    launchordertoggle: function(value) {
                        farmDesigner.down('farmrolesview').toggleLaunchOrder(value);
                    }
                }
            },{
                xtype: 'farmroleeditor',
                itemId: 'farmRoleEditor',
                listeners: {
                    activate: function() {
                        farmDesigner.updateTitle(null, this.currentRole.get('alias'));
                    },
                    rolealiaschange: function() {
                        farmDesigner.updateTitle(null, this.currentRole.get('alias'));
                    },
                    tabactivate: function(tab) {
                        if (tab.itemId === 'scripting') {
                            farmDesigner.down('farmrolesview').addCls('x-show-color-corners');
                        }
                    },
                    tabdeactivate: function(tab) {
                        if (tab.itemId === 'scripting') {
                            farmDesigner.down('farmrolesview').removeCls('x-show-color-corners')
                        }
                    }
                }

            }]
        }],
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
                xtype: 'splitbutton',
                itemId: 'save',
                text: 'Save farm',
                launch: false,
                menu: {
                    xtype: 'menu',
                    cls: 'x-topmenu-farms',
                    items: [{
                        text: 'Save farm',
                        handler: function () {
                            farmDesigner.save();
                        }
                    }, {
                        text: 'Save & launch',
                        itemId: 'launch',
                        handler: function () {
                            farmDesigner.save(true);
                        }
                    }]
                },
                lockButton: function() {
                    if (!this.isFarmLocked) {
                        this.setTooltip({
                            text: 'Add selected role to the farm or cancel role adding before saving farm.',
                            align: 'bc-tc'
                        }).setDisabled(true);
                    }
                },
                unlockButton: function() {
                    if (!this.isFarmLocked) {
                        this.setTooltip('').setDisabled(false);
                    }
                },
                handler: function() {
                    if (!this.isFarmLocked) {
                        farmDesigner.save(this.launch);
                    }
                },
                setLaunch: function(status) {
                    if (status === 'disabled' || !farmDesigner.moduleParams['farmLaunchPermission']) {
                        this.setText('Save farm');
                        this.menu.down('#launch').disable();
                    } else {
                        this.menu.down('#launch').enable();
                        this.setText(status === 'default' ? 'Save & launch' : 'Save farm');
                        this.launch = status === 'default';
                    }
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }],
        loadFarm: function(params) {
            var me = this;
            Scalr.CachedRequestManager.clear('farmDesigner');
            farmRolesStore.removeAll();
            me.updateTitle('Farm settings');
            me.getComponent('farmDesignerCard').layout.setActiveItem('blank');
            params['scalrPageHash'] = moduleParams['scalrPageHash'];
            params['scalrPageUiHash'] = Scalr.state['pageUiHash'];
            Scalr.Request({
                url: '/farms/xGetFarm',
                params: params,
                processBox: {
                    type: 'load',
                    msg: 'Loading farm...'
                },
                success: function(data) {
                    if (data['scalrPageHashMismatch']) {
                        farmDesigner.close();
                        Scalr.event.fireEvent('refresh');
                        return;
                    }

                    if (data['scalrPageUiHashMismatch']) {
                        Scalr.event.fireEvent('reload');
                        return;
                    }

                    me.setFarm(params, data);
                }
            });
        },
        setFarm: function(loadParams, moduleParams) {
            var me = this,
                isNewFarm = !moduleParams['farm'],
                farmSettings = isNewFarm ? {} : moduleParams['farm'],
                isFarmLocked = farmSettings['lock'] || false,
                record;
            me.farmId = loadParams.farmId;
            me.moduleParams = moduleParams;
            me.updateTitle(isNewFarm ? 'New farm' : farmSettings.farm.name);
            me.getComponent('farmDesignerCard').getComponent('farmSettings').fireEvent('setfarm', loadParams['farm']);
            if (farmSettings['lock']) {
                Scalr.message.Warning(farmSettings['lock'] + ' <br/>You won\'t be able to save any changes.');
            }
            Ext.apply(moduleParams['tabParams'], {
                farmRolesStore: farmRolesStore,
                behaviors: moduleParams['behaviors'] || {},
                metrics: moduleParams['metrics'] || {},
                lock: farmSettings['lock'],
                farm: moduleParams['farm'] ? moduleParams['farm'].farm : {}
            });

            me.getComponent('farmDesignerCard').getComponent('farmRoleEditor').initTabs();
            //disable save button if farm is locked
            var btnSave = me.getDockedComponent('buttons').getComponent('save');
            btnSave.setDisabled(isFarmLocked);
            btnSave.isFarmLocked = isFarmLocked;
            btnSave.setLaunch(isNewFarm ? 'default' : (farmSettings.farm.status>0 ? 'disabled' : 'visible'));

            me.getComponent('farmRoles').down('filterfield').resetFilter();
            farmRolesStore.loadData(farmSettings.roles || []);

            if (loadParams['roleId']) {
                me.down('farmrolesview').toggleRolesLibraryBtn(true);
                me.showRolesLibrary(loadParams['roleId']*1);
            } else if (loadParams['farmRoleId']) {
                record = farmRolesStore.findRecord('farm_role_id', loadParams['farmRoleId']);
                if (record) {
                    me.down('farmrolesview').select(record, false, true);
                    me.getComponent('farmRoles').down('#farmSettingsBtn').setPressed(false);
                    me.showFarmRoleEditor(record);
                }
            } else {
                me.showFarmSettings();
            }

        },
        onFarmRoleAliasChanged: function(oldAlias, newAlias) {
            var farmRoleEditor = this.down('#farmRoleEditor').down('#tabspanel'),
                tab = farmRoleEditor.layout.getActiveItem();

            farmRolesStore.getUnfiltered().each(function(record){
                var scripting = record.get('scripting', true);
                if (Ext.isArray(scripting)) {
                    Ext.each(scripting, function(script, index){
                        var farmroles = [];
                        if (script.target === 'farmroles' && Ext.isArray(script.target_farmroles)) {
                            Ext.each(script.target_farmroles, function(alias){
                                if (alias === oldAlias) {
                                    farmroles.push(newAlias);
                                } else {
                                    farmroles.push(alias);
                                }
                            });
                            scripting[index].target_farmroles = farmroles;
                        }
                    })
                    record.set('scripting', scripting);
                }

                var settings = record.get('settings', true),
                    nginxSettings = Ext.decode(settings['nginx.proxies']),
                    haproxySettings = Ext.decode(settings['haproxy.proxies']);
                if (Ext.isArray(nginxSettings)) {
                    Ext.each(nginxSettings, function(nginx){
                        Ext.each(nginx['backends'] || [], function(backend){
                            if (backend['farm_role_alias'] === oldAlias) {
                                backend['farm_role_alias'] = newAlias;
                            }
                        });
                    });
                    settings['nginx.proxies'] = Ext.encode(nginxSettings);
                }
                if (Ext.isArray(haproxySettings)) {
                    Ext.each(haproxySettings, function(haproxy){
                        Ext.each(haproxy['backends'] || [], function(backend){
                            if (backend['farm_role_alias'] === oldAlias) {
                                backend['farm_role_alias'] = newAlias;
                            }
                        });
                    });
                    settings['haproxy.proxies'] = Ext.encode(haproxySettings);
                }

                record.set('settings', settings);

            });

            if (tab.itemId === 'scripting') tab.fireEvent('activate');
        },
        showRolesLibrary: function(roleId) {
            var me = this,
                card = me.getComponent('farmDesignerCard'),
                rolesLibrary = card.getComponent('rolesLibrary');
            me.down('farmrolesview').getSelectionModel().deselectAll(true);
            if (!rolesLibrary) {
                rolesLibrary = card.add({
                    xtype: 'roleslibrary',
                    hidden: true,
                    //autoRender: false,
                    itemId: 'rolesLibrary',
                    roleId: roleId,
                    listeners: {
                        activate: function() {
                            farmDesigner.updateTitle(null, 'Add new role');
                            if (this.down('form').isVisible()) {
                                me.getDockedComponent('buttons').getComponent('save').lockButton();
                            }
                        },
                        deactivate: function() {
                            me.getDockedComponent('buttons').getComponent('save').unlockButton();
                        },
                        addrole: function(role) {
                            return me.addFarmRole(role);
                        },
                        showform: function() {
                            me.getDockedComponent('buttons').getComponent('save').lockButton();
                        },
                        hideform: function() {
                            me.getDockedComponent('buttons').getComponent('save').unlockButton();
                        },
                        beforesetalias: function(name, res){
                            var count = farmRolesStore.countBy('name', name);
                            res.alias = name + (count > 0 ? '-' + count : '');
                        }
                    }
                });
            } else {
                rolesLibrary.roleId = roleId;
            }
            card.layout.setActiveItem(rolesLibrary);
        },
        showFarmRoleEditor: function(record) {
            var me = this,
                card = me.getComponent('farmDesignerCard'),
                farmRoleEditor = card.getComponent('farmRoleEditor');
            card.layout.setActiveItem('blank');
            farmRoleEditor.setCurrentRole(record);
            card.layout.setActiveItem(farmRoleEditor);
        },
        showFarmSettings: function() {
            var me = this,
                card = me.getComponent('farmDesignerCard');
            me.down('farmrolesview').getSelectionModel().deselectAll();
            me.getComponent('farmRoles').down('#farmSettingsBtn').setPressed(true);
            card.layout.setActiveItem('farmSettings');
        },
        addFarmRole: function(role) {
            var me = this,
                behaviors = role.get('behaviors'),
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
                'variables': me.getNewFarmRoleVariables(role.get('variables'))
            });

            me.down('#farmRoleEditor').addFarmRoleDefaults(role);

            farmRolesStore.add(role);
            msg = 'Role "' + role.get('alias') + '" added';
            if (Scalr.flags['analyticsEnabled'] && !Ext.Array.contains(me.moduleParams['analytics']['unsupportedClouds'], role.get('platform'))) {
                role.loadHourlyRate(null, function(){Scalr.message.Success(msg);});
            } else {
                Scalr.message.Success(msg);
            }
        },
        getFarmVariables: function() {
            return this.getComponent('farmDesignerCard').getComponent('farmSettings').down('#variables').farmVariables || [];
        },
        getNewFarmRoleVariables: function(roleVars) {
            var farmVars = this.getComponent('farmDesignerCard').getComponent('farmSettings').down('#variables').getValue() || [];
            roleVars = roleVars || [];

            for (var i = 0; i < farmVars.length; i++) {
                if (farmVars[i]['current']) {
                    var flag = true;
                    for (var j = 0, len = roleVars.length; j < len; j++) {
                        if (roleVars[j]['name'] == farmVars[i]['name']) {
                            flag = false;

                            if (roleVars[j]['default'] && !roleVars[j]['current']) {
                                // var has value on farm level, and doesn't have value on role level, add
                                roleVars[j]['default'] = Ext.clone(farmVars[i]['current']);
                                roleVars[j]['scopes'].push('farm');
                                break;
                            }
                        }
                    }

                    if (flag) {
                        // don't have such value, add it
                        farmVars[i]['default'] = Ext.clone(farmVars[i]['current']);
                        farmVars[i]['current'] = undefined;
                        roleVars.push(farmVars[i]);
                    }
                }
            }

            return roleVars;
        },
        getVpcSettings: function() {
            var result = false,
                farmSettings = this.getComponent('farmDesignerCard').getComponent('farmSettings'),
                vpcRegion = farmSettings.down('[name="vpc_region"]').getValue(),
                vpcId = farmSettings.down('[name="vpc_id"]').getValue();
            if (this.moduleParams['farmVpcEc2Enabled'] && farmSettings.down('[name="vpc_enabled"]').getValue() && vpcRegion && vpcId){
                result = {
                    region: vpcRegion,
                    id: vpcId
                };
            }
            return result;
        },
        getSzrUpdateSettings: function() {
            var ct = this.getComponent('farmDesignerCard').getComponent('farmSettings').getComponent('advanced').down('#szrUpdateSettings'),
                values,
                result = {'szr.upd.repository': '', 'szr.upd.schedule' : '* * *'};
            if (ct.isValidFields()) {
                values = ct.getFieldValues(true);
                result = {
                    'szr.upd.repository': values['szr.upd.repository'],
                    'szr.upd.schedule' : ([
                        values['hh'] || '*',
                        values['dd'] || '*',
                        values['dw'] || '*'
                    ]).join(' ')
                };
            }
            return result;
        },
        save: function (launch) {
            var me = this,
                farmSettings,
                farmRoles = [],
                card = me.getComponent('farmDesignerCard'),
                farmSettings = card.getComponent('farmSettings');

            me.down('farmrolesview').getSelectionModel().deselectAll();
            me.getComponent('farmRoles').down('filterfield').resetFilter();
            card.layout.setActiveItem(farmSettings);

            farmSettings = farmSettings.getValues();
            if (!farmSettings) return;

            farmRolesStore.getUnfiltered().each(function (rec) {
                var sets = {}, params;
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

                params = rec.get('params', true);
                if (Ext.isObject(params)) {
                    sets['params'] = params;
                }

                farmRoles.push(sets);
            });

            Scalr.Request({
                processBox: {
                    msg: 'Saving farm ...'
                },
                url: '/farms/builder/xBuild',
                params: {
                    farm: Ext.encode(farmSettings),
                    roles: Ext.encode(farmRoles),
                    v2: 1,
                    changed: me.moduleParams['farm'] ? me.moduleParams['farm']['changed'] : '',
                    farmId: me.moduleParams['farmId'],
                    launch: !!launch
                },
                success: function(data) {
                    if (data.isNewFarm)
                        Scalr.event.fireEvent('redirect', '#/farms?farmId=' + data.farmId);
                    else
                        Scalr.event.fireEvent('close');
                },
                failure: function(data) {
                    if (data['errors']) {
                        me.saveErrorHandler(data['errors']);
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
                                        me.moduleParams['farm']['changed'] = ''; // TODO: do better via flag
                                        farmDesigner.save();
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
        },
        saveErrorHandler: function(errors) {
            var me = this,
                card = me.getComponent('farmDesignerCard'),
                farmSettings = card.getComponent('farmSettings'),
                activeRole,
                farmErrors = [],
                rolesErrors = [],
                errorTipEl = farmDesigner.getDockedComponent('buttons').down('#save').el,
                errorTipText = 'Some errors occured while saving the farm';

            farmRolesStore.getUnfiltered().each(function (rec) {
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
                farmSettings.setErrors(errors.farm);
                Ext.Object.each(errors.farm, function(name, error){
                    if (name === 'variables') {
                        Ext.Object.each(error, function(key, value){
                            Ext.Object.each(value, function(key, value){
                                farmErrors.push(value);
                            });
                        });
                    } else {
                        farmErrors.push(error);
                    }
                });
            } else {
                farmSettings.clearErrors();
                if (activeRole !== undefined) {
                    var farmRoles = me.down('farmrolesview');
                    farmRoles.select(activeRole);
                    //errorTipEl = farmRoles.up().el;
                }
            }

            if (farmErrors.length || rolesErrors.length) {
                errorTipText += ':<ul class="x-tip-errors-list"><li>' + Ext.Array.merge(farmErrors, rolesErrors).join('</li><li>') + '</li></ul>'
                Scalr.message.ErrorTip(errorTipText, errorTipEl);

            }
        }
    });

	Scalr.event.on('update', function (type, data) {
		if (type == '/farms/roles/replaceRole' && data['farmId'] == farmDesigner['farmId']) {
            var record = farmRolesStore.findRecord('farm_role_id', data['farmRoleId'], false, false, true),
                farmRoles;
            if (record) {
                farmRoles = farmDesigner.down('farmrolesview');
                farmRoles.getSelectionModel().deselectAll()
                record.set(data.role);
                farmRoles.select(record);
            }
        }
    }, farmDesigner);

    return farmDesigner;
});

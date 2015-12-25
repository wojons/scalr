Ext.define('Scalr.ui.FarmRoleEditorTab.Scripting', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Orchestration',

    itemId: 'scripting',
    layout: 'fit',

    tabData: null,

    getDefaultValues: function (record) {
        record.set('scripting', []);
        return {};
    },

    beforeShowTab: function (record, handler) {
        var me = this;
        me.tabData = {};
        Scalr.CachedRequestManager.get('farmDesigner').load(
            {
                url: '/account/orchestration/xGetList',
                params: {}
            },
            function(data, status){
                me.tabData['accountScripts'] = data;
                if (status) {
                    Scalr.CachedRequestManager.get('farmDesigner').load(
                        {
                            url: '/farms/builder/xGetScripts',
                            params: {
                                cloudLocation: record.get('cloud_location'),
                                roleId: record.get('role_id')
                            }
                        },
                        function(data, status){
                            if (status) {
                                Ext.apply(me.tabData, data);
                                record.loadRoleChefSettings(function(data, status){
                                    if (status) {
                                        me.tabData['chefSettings'] = data['chefSettings'];
                                        handler();
                                    } else {
                                        me.deactivateTab();
                                    }
                                });
                            } else {
                                me.deactivateTab();
                            }
                        },
                        me,
                        0
                    );
                } else {
                    me.deactivateTab();
                }
            },
            me,
            0
        );
    },

    showTab: function (record) {
        var scripts = record.get('scripting'),
            accountScripts = this.tabData['accountScripts'] || {},
            roleScripts = this.tabData['roleScripts'] || {},
            chefSettings = this.tabData['chefSettings'] || {},
            roleParams = record.get('scripting_params'),
            roleOsFamily = Scalr.utils.getOsById(record.get('osId'), 'family'),
            params = {};

        if (Ext.isArray(roleParams)) {
            for (var i = 0; i < roleParams.length; i++) {
                params[roleParams[i]['hash']] = roleParams[i]['params'];
            }
        }

        addSystemScript = function(scripts, script, system) {
            var addScript = true;
            if (system === 'account' && roleOsFamily && script['script_type'] === 'scalr') {
                addScript = script['os'] == roleOsFamily || script['os'] == 'linux' && roleOsFamily != 'windows';
            }
            if (addScript) {
                scripts.push({
                    role_script_id: script['role_script_id'],
                    event: script['event_name'],
                    isSync: script['isSync'],
                    order_index: script['order_index'],
                    params: params[script['hash']] || script['params'],
                    script: script['script_name'],
                    script_id: script['script_id'],
                    target: script['target'],
                    timeout: script['timeout'],
                    version: script['version'],
                    system: system,
                    hash: script['hash'],
                    script_path: script['script_path'],
                    script_type: script['script_type'],
                    os: script['os']
                });
            }
        };

        for (var i in roleScripts) {
            addSystemScript(scripts, roleScripts[i], 'role');
        }
        for (var i in accountScripts) {
            addSystemScript(scripts, accountScripts[i], 'account');
        }


        var rolescripting = this.down('#rolescripting');
        rolescripting.chefSettings = chefSettings;
        rolescripting.setCurrentRoleOptions({
            farmRoleAlias: record.get('alias'),
            osFamily: roleOsFamily,
            chefAvailable: record.hasBehavior('chef')
        });

        //load farm roles
        var farmRoles = [],
            farmRolesStore = record.store;
        farmRolesStore.getUnfiltered().each(function(item){
            farmRoles.push({
                farm_role_id: item.get('farm_role_id'),
                platform: item.get('platform'),
                cloud_location: item.get('cloud_location'),
                role_id: item.get('role_id'),
                name: item.get('name'),
                alias: item.get('alias'),
                current: item === record
            });
        });
        rolescripting.loadRoles(farmRoles);

        //load scripst, events and behaviors
        rolescripting.loadScripts(this.tabData['scripts'] || []);
        rolescripting.loadEvents(this.tabData['events'] || {});
        rolescripting.loadBehaviors(this.up('#farmDesigner').moduleParams.tabParams['behaviors']);
        rolescripting.abortIfBeforeHostUpFails = record.get('settings', true)['base.abort_init_on_script_fail'] == 1;

        //convert deprecated configurations farm_role_id->farm_role_alias
        Ext.each(scripts, function(script){
            if (script.target === 'roles') {
                var targetFarmRoles = [];
                Ext.each(script.target_roles || [], function(farmRoleId){
                    var farmRoleAlias = null;
                    Ext.each(farmRoles, function(role){
                        if (role.farm_role_id == farmRoleId) {
                            farmRoleAlias = role.alias;
                            return false;
                        }
                    });
                    if (farmRoleAlias) {
                        targetFarmRoles.push(farmRoleAlias);
                    }
                });
                script.target = 'farmroles';
                script.target_farmroles = targetFarmRoles;
                delete script.target_roles;
            }
        });
        //load role scripts
        rolescripting.loadRoleScripts(scripts);
    },

    hideTab: function (record) {
        var scripts = this.down('#rolescripting').getRoleScripts(),
            scripting = [],
            scripting_params = [];

        scripts.each(function(item) {
            var system = item.get('system');
            if (!system) {
                var script = Ext.clone(item.data);
                delete script.id;
                scripting.push(script);
            } else if (system === 'role') {
                scripting_params.push({
                    role_script_id: item.get('role_script_id'),
                    params: item.get('params'),
                    hash: item.get('hash')
                });
            }
        });

        record.set('scripting', scripting);
        record.set('scripting_params', scripting_params);
        this.down('#rolescripting').clearRoleScripts();
    },

    __items: {
        xtype: 'scriptfield',
        itemId: 'rolescripting',
        addGridCls: 'x-panel-column-left-with-tabs'
    }
});

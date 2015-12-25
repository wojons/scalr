Ext.define('Scalr.ui.RoleDesignerTabScripting', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditscripting',
    layout: 'fit',
    cls: 'scalr-ui-role-edit-tab-scripting',
    items: [{
        xtype: 'scriptfield',
        itemId: 'rolescripting',
        mode: 'role'
    }],
    initComponent: function(){
        this.callParent(arguments);
        this.addListener({
            showtab: {
                fn: function(params){
                    var rolescripting = this.down('#rolescripting');
                    rolescripting.loadScripts(params['scriptData']['scripts'] || []);
                    rolescripting.loadEvents(params['scriptData']['events'] || {});
                },
                single: true
            },
            hidetab: function(params) {
                var scripts = this.down('#rolescripting').getRoleScripts(),
                    scripting = [];

                scripts.each(function(item) {
                    var script = item.getData();
                    if (!script['system']) {
                        script['event_name'] = script['event'];
                        script['script_name'] = script['script'];
                        scripting.push(script);
                    }
                });

                params['role']['scripts'] = scripting;
                this.down('#rolescripting').down('grid').clearSelectedRecord();
            }
        });
        this.addListener({
            showtab: {
                fn: function(params){
                    var rolescripting = this.down('#rolescripting'),
                        roleOsFamily = Scalr.utils.getOsById(params['role']['osId'], 'family'),
                        scripts = [];
                    rolescripting.chefSettings = params['role']['chef'] || {};
                    rolescripting.setCurrentRoleOptions({
                        osFamily: roleOsFamily,
                        chefAvailable: Ext.Array.contains(params['role']['behaviors'], 'chef')
                    });
                    rolescripting.roleOs = roleOsFamily;

                    if (params['role']['scripts'].length) {
                        scripts.push.apply(scripts, params['role']['scripts']);
                    }
                    if (params['accountScripts'].length) {
                        Ext.each(params['accountScripts'], function(script){
                            var addScript = true;
                            if (script['script_type'] === 'scalr') {
                                addScript = script['os'] == roleOsFamily || script['os'] == 'linux' && roleOsFamily != 'windows';
                            }
                            if (addScript) {
                                scripts.push(script);
                            }
                        });
                    }
                    rolescripting.loadRoleScripts(scripts);
                }
            }
        });
    },
    getSubmitValues: function() {
        var scripts = this.down('#rolescripting').getRoleScripts(),
            scripting = [];

        scripts.each(function(item) {
            var script = item.getData();
            if (!script['system']) {
                script['event_name'] = script['event'];
                scripting.push(script);
            }
        });

        return {scripts: scripting};
    }

});

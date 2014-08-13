Ext.define('Scalr.ui.RoleDesignerTabScripting', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditscripting',
    layout: 'fit',
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
                    rolescripting.loadRoleScripts(params['role']['scripts']);
                },
                single: true
            },
            hidetab: function(params) {
                var scripts = this.down('#rolescripting').getRoleScripts(),
                    scripting = [];

                scripts.each(function(item) {
                    scripting.push(item.getData());
                });

                params['role']['scripts'] = scripting;
            }
        });
        this.addListener({
            showtab: {
                fn: function(params){
                    var rolescripting = this.down('#rolescripting');
                    rolescripting.chefSettings = params['role']['chef'] || {};
                    rolescripting.setCurrentRoleOptions({
                        osFamily: params['role']['osFamily'],
                        chefAvailable: Ext.Array.contains(params['role']['behaviors'], 'chef')
                    });
                    rolescripting.roleOs = params['role']['osFamily'];
                }
            }
        });
    },
    getSubmitValues: function() {
        var scripts = this.down('#rolescripting').getRoleScripts(),
            scripting = [];

        scripts.each(function(item) {
            var script = item.getData();
            script['event_name'] = script['event'];
            scripting.push(script);
        });

        return {scripts: scripting};
    }

});

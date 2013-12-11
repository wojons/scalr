Ext.define('Scalr.ui.RoleDesignerTabScripting', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditscripting',
    layout: 'fit',
    items: [{
        xtype: 'scriptfield2',
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
                    scripting.push(item.data);
                });

                params['role']['scripts'] = scripting;
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

        return {scripts: Ext.encode(scripting)};
    }

});

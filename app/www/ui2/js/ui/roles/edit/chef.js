Ext.define('Scalr.ui.RoleDesignerTabChef', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditchef',
    cls: 'x-panel-column-left',
    items: [{
        xtype: 'chefsettings'
    }],
    autoScroll: true,
    initComponent: function(){
        this.callParent(arguments);
        this.addListener({
            showtab: {
                fn: function(params){
                    var field = this.down('chefsettings'),
                        governance = params['governance'] || {};
                    field.limits = governance['general.chef'] || undefined;
                    field.disableDaemonize = false;
                    Ext.each(params['role']['scripts'] || [], function(script){
                        if (script['script_type'] === 'chef' && script['params'] && !script['params']['chef.cookbook_url']) {
                            field.disableDaemonize = true;
                            return false;
                        }
                    });
                    field.setValue(params['role']['chef'] || {});
                }
            },
            hidetab: function(params) {
                params['role']['chef'] = this.down('chefsettings').getValue();
            }

        });
    },
    getSubmitValues: function() {
        return {chef: this.down('chefsettings').getValue()};
    },
    isValid: function() {
        return this.down('chefsettings').isValid();
    }
});
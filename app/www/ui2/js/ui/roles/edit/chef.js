Ext.define('Scalr.ui.RoleDesignerTabChef', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditchef',
    cls: 'x-panel-column-left x-panel-column-left-with-tabs',
    items: [{
        xtype: 'chefsettings'
    }],
    autoScroll: true,
    initComponent: function(){
        this.callParent(arguments);
        this.addListener({
            showtab: {
                fn: function(params){
                    var field = this.down('chefsettings');
                    field.disableDaemonize = false;
                    field.roleOsFamily = Scalr.utils.getOsById(params['role']['osId'], 'family');
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
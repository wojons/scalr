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
                    this.down('chefsettings').setValue(params['role']['chef'] || {});
                },
                single: true
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
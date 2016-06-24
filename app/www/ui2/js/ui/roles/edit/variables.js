Ext.define('Scalr.ui.RoleDesignerTabVariables', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditvariables',
    autoScroll: true,
    layout: 'fit',
    items: [{
        xtype: 'variablefield',
        name: 'variables',
        cls: 'x-panel-column-left',
        currentScope: 'role',
        encodeParams: false
    }],
    initComponent: function(){
        this.callParent(arguments);
        this.addListener({
            showtab: {
                fn: function(params){
                    this.down('variablefield').setValue(params['role']['variables']);
                },
                single: true
            }
        });
    },
    getSubmitValues: function() {
        return {variables: this.down('variablefield').getValue()};
    },
    isValid: function() {
        return true;
    }

});

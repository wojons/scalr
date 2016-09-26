Ext.define('Scalr.ui.FarmRoleEditorTab.Variables', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Global variables',
    itemId: 'variables',
    labelWidth: 200,
    layout: 'fit',

    settings: {
        variables: undefined
    },

    showTab: function (record) {
        this.down('variablefield').setValue(record.get('variables', true));
    },

    hideTab: function (record) {
        var me = this;

        var variableField = me.down('variablefield');
        variableField.showForm(false);

        record.set('variables', variableField.getValue(true));
    },

    __items: {
        xtype: 'variablefield',
        name: 'variables',
        currentScope: 'farmrole',
        encodeParams: false,
        cls: 'x-panel-column-left x-panel-column-left-with-tabs'
    }
});

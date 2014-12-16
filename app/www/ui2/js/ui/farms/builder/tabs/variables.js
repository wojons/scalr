Scalr.regPage('Scalr.ui.farms.builder.tabs.variables', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Global variables',
        itemId: 'variables',
		labelWidth: 200,
        layout: 'fit',

		isEnabled: function (record) {
			return true;
		},

        settings: {
            variables: undefined
        },

		showTab: function (record) {
			this.down('variablefield').setValue(record.get('variables', true));
		},

		hideTab: function (record) {
            var me = this;

            me.down('variablefield').showExtendedForm(false);

			record.set('variables', this.down('variablefield').getValue(true));
		},

		items: {
            xtype: 'variablefield',
            name: 'variables',
            currentScope: 'farmrole',
            encodeParams: false,
            maxWidth: 1200
        }
	});
});

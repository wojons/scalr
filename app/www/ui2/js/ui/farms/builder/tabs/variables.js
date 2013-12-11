Scalr.regPage('Scalr.ui.farms.builder.tabs.variables', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Global variables',
        itemId: 'variables',
		labelWidth: 200,
        
        layout: 'anchor',
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
			record.set('variables', this.down('variablefield').getValue());
		},

		items: [{
			xtype: 'fieldset',
			autoScroll: true,
            cls: 'x-fieldset-separator-none',
			items: [{
				xtype: 'variablefield',
				name: 'variables',
				currentScope: 'farmrole',
                encodeParams: false,
				maxWidth: 1200
			}]
		}]
	});
});

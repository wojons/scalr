Scalr.regPage('Scalr.ui.farms.builder.addrole.rackspace', function () {
    return {
        xtype: 'container',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',

        instanceTypeFieldName: 'rs.flavor-id',
        
        layout: 'hbox',
        defaults: {
            maxWidth: 762
        },

        isVisibleForRole: function(record) {
            return record.get('platform') === 'rackspace';
        },

        isValid: function() {
            var res = true,
                field;
            field = this.down('[name="rs.flavor-id"]');
            res = field.validate() || {comp: field, message: 'Instance type is required'};
            return res;
        },

        getSettings: function() {
            return {
                'rs.flavor-id': this.down('[name="rs.flavor-id"]').getValue()
            };
        },

        items: [{
            xtype: 'instancetypefield',
            name: 'rs.flavor-id',
            labelWidth: 90,
            flex: 1,
            allowBlank: false
        }]
    }
});

Scalr.regPage('Scalr.ui.farms.builder.addrole.rackspace', function () {
    return {
        xtype: 'container',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',
        
        layout: 'hbox',

        isVisibleForRole: function(record) {
            return record.get('platform') === 'rackspace';
        },

        onSelectImage: function(record) {
            if (this.isVisibleForRole(record)) {
                this.setRole(record);
                this.show();
            } else {
                this.hide();
            }
        },

        setRole: function(record) {
            Scalr.cachedRequest.load(
                {
                    url: '/platforms/rackspace/xGetFlavors',
                    params: {cloudLocation: record.get('cloud_location')}
                },
                function(data, status){
                    var flavorIdField = this.down('[name="rs.flavor-id"]');
                    if (status) {
                        flavorIdField.store.load({ data: data || []});
                    }
                    flavorIdField.setValue(1);
                    flavorIdField.setDisabled(!status);
                },
                this
            );
        },

        isValid: function() {
            return true;
        },

        getSettings: function() {
            return {
                'rs.flavor-id': this.down('[name="rs.flavor-id"]').getValue()
            };
        },

        items: [{
            xtype: 'combo',
            name: 'rs.flavor-id',
            maxWidth: 385,
            flex: 1,
            fieldLabel: 'Flavor',
            labelWidth: 50,
            submitValue: false,
            editable: false,
            queryMode: 'local',
            store: {
                fields: [ 'id', 'name' ],
                proxy: 'object'
            },
            valueField: 'id',
            displayField: 'name'
        }]
    }
});

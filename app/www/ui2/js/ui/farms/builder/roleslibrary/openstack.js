Scalr.regPage('Scalr.ui.farms.builder.addrole.openstack', function () {
    return {
        xtype: 'container',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',
        
        layout: 'fit',
        defaults: {
            maxWidth: 762
        },

        isVisibleForRole: function(record) {
            var platform = record.get('platform');
            return Scalr.isOpenstack(platform);
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
                    url: '/platforms/openstack/xGetOpenstackResources',
                    params: {
                        cloudLocation: record.get('cloud_location'), 
                        platform: record.get('platform')
                    }
                },
                function(data, status){
                    var flavorIdField = this.down('[name="openstack.flavor-id"]'),
                        ipPoolsField = this.down('[name="openstack.ip-pool"]'),
                        value = '', 
                        ipPools = data ? data['ipPools'] : null;
                    if (status) {
                        flavorIdField.store.load({ data:  data['flavors'] || []});
                        if (flavorIdField.store.getCount() > 0) {
                            value = flavorIdField.store.getAt(0).get('id');
                        }
                    }
                    flavorIdField.setValue(value);
                    flavorIdField.setDisabled(!status);
                    
                    ipPoolsField.reset();
                    ipPoolsField.store.load({data: ipPools || []});
                    ipPoolsField.setVisible(!!ipPools);
                },
                this
            );
        },

        isValid: function() {
            return true;
        },

        getSettings: function() {
            return {
                'openstack.flavor-id': this.down('[name="openstack.flavor-id"]').getValue(),
                'openstack.ip-pool': this.down('[name="openstack.ip-pool"]').getValue()
            };
        },

        items: [{
            xtype: 'container',
            layout: 'hbox',
            items: [{
                xtype: 'combo',
                name: 'openstack.flavor-id',
                flex: 1,
                maxWidth: 385,
                fieldLabel: 'Flavor',
                labelWidth: 50,
                editable: false,
                queryMode: 'local',
                store: {
                    fields: [ 'id', 'name' ],
                    proxy: 'object'
                },
                valueField: 'id',
                displayField: 'name'
            },{
                xtype: 'combo',
                name: 'openstack.ip-pool',
                flex: 1,
                fieldLabel: 'Floating IPs pool',
                labelWidth: 110,
                editable: false,
                hidden: true,
                margin: '0 0 0 64',
                queryMode: 'local',
                store: {
                    fields: [ 'id', 'name' ],
                    proxy: 'object'
                },
                valueField: 'id',
                displayField: 'name'
            }]
        }]
    }
});

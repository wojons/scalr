Scalr.regPage('Scalr.ui.farms.builder.addrole.openstack', function () {
    return {
        xtype: 'container',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',

        layout: 'fit',
        defaults: {
            maxWidth: 760
        },

        isVisibleForRole: function(record) {
            var platform = record.get('platform');
            return Scalr.isOpenstack(platform);
        },

        setRole: function(record) {
            this.currentRole = record;
            Scalr.cachedRequest.load(
                {
                    url: '/platforms/openstack/xGetOpenstackResources',
                    params: {
                        cloudLocation: record.get('cloud_location'), 
                        platform: record.get('platform')
                    }
                },
                function(data, status){
                    var ipPoolsField = this.down('[name="openstack.ip-pool"]'),
                        ipPools = data ? data['ipPools'] : null;
                    
                    ipPoolsField.reset();
                    ipPoolsField.store.load({data: ipPools || []});
                    ipPoolsField.setVisible(Scalr.getPlatformConfigValue(record.get('platform'), 'ext.floating_ips_enabled') == 1 && !!ipPools);
                },
                this
            );
        },

        isValid: function() {
            return true;
        },

        getSettings: function() {
            var settings = {};
            if (Scalr.getPlatformConfigValue( this.currentRole.get('platform'), 'ext.floating_ips_enabled') == 1) {
                settings['openstack.ip-pool'] = this.down('[name="openstack.ip-pool"]').getValue();
            }
            return settings;
        },

        items: [{
            xtype: 'combo',
            name: 'openstack.ip-pool',
            flex: 1,
            fieldLabel: 'Floating IPs pool',
            labelWidth: 120,
            editable: false,
            hidden: true,
            queryMode: 'local',
            store: {
                model: Scalr.getModel({fields: [ 'id', 'name' ]}),
                proxy: 'object'
            },
            valueField: 'id',
            displayField: 'name'
        }]
    };
});

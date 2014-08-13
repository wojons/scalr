Scalr.regPage('Scalr.ui.farms.builder.addrole.openstack', function () {
    return {
        xtype: 'container',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',

        instanceTypeFieldName: 'openstack.flavor-id',
         
        layout: 'fit',
        defaults: {
            maxWidth: 762
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
            var res = true,
                field;
            field = this.down('[name="openstack.flavor-id"]');
            res = field.validate() || {comp: field, message: 'Instance type is required'};
            return res;
        },

        getSettings: function() {
            var settings = {
                'openstack.flavor-id': this.down('[name="openstack.flavor-id"]').getValue()
            };
            if (Scalr.getPlatformConfigValue( this.currentRole.get('platform'), 'ext.floating_ips_enabled') == 1) {
                settings['openstack.ip-pool'] = this.down('[name="openstack.ip-pool"]').getValue();
            }
            return settings;
        },

        items: [{
            xtype: 'container',
            layout: 'hbox',
            items: [{
                xtype: 'instancetypefield',
                name: 'openstack.flavor-id',
                labelWidth: 90,
                flex: 1,
                allowBlank: false
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

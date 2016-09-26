Scalr.regPage('Scalr.ui.farms.builder.addrole.gce', function () {
    return {
        xtype: 'container',
        itemId: 'gce',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',

        layout: 'anchor',
        defaults: {
            anchor: '100%',
            maxWidth: 760,
            labelWidth: 120
        },

        isVisibleForRole: function(record) {
            return record.get('platform') === 'gce';
        },

        setRole: function(record) {
            var platform = record.get('platform'),
                cloudLocation = record.get('cloud_location');
            this.currentRole = record;
            Scalr.cachedRequest.load(
                {
                    url: '/platforms/gce/xGetOptions',
                    params: {}
                },
                function(data, status){
                    var networks = data && data.networks ? data.networks : [],
                        field = this.down('[name="gce.network"]');
                    field.reset();
                    field.store.load({data: networks});
                    field.setValue('default');
                },
                this
            );
        },

        isValid: function() {
            var res = true,
                field = this.down('[name="gce.subnet"]');
            if (field.isVisible()) {
                res = field.validate() || {comp: field, message: 'Subnet is required'};
            }
            return res;
        },

        getSettings: function() {
            var field = this.down('[name="gce.subnet"]'),
                settings = {
                    'gce.network': this.down('[name="gce.network"]').getValue()
                };
            if (field.isVisible()) {
                settings['gce.subnet'] = field.getValue();
            }
            return settings;
        },

        items: [{
            xtype: 'combo',
            store: {
                fields: [ 'name', 'description' ],
                proxy: 'object'
            },
            valueField: 'name',
            displayField: 'description',
            fieldLabel: 'Network',
            editable: false,
            queryMode: 'local',
            name: 'gce.network',
            allowBlank: false,
            listeners: {
                change: function(comp, value) {
                    comp.next('[name="gce.subnet"]').loadSubnets(comp.up('#gce').currentRole.get('cloud_location'), value);
                }
            }
        }, {
            xtype: 'gcesubnetfield'
        }]
    };
});

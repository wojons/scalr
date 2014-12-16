Scalr.regPage('Scalr.ui.farms.builder.addrole.gce', function () {
    return {
        xtype: 'container',
        itemId: 'gce',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',

        instanceTypeFieldName: 'gce.machine-type',

        layout: {
            type: 'hbox'
        },
        defaults: {
            maxWidth: 348
        },

        isVisibleForRole: function(record) {
            return record.get('platform') === 'gce';
        },

        onSettingsUpdate: function(record, name, value) {
        },

        setRole: function(record) {
            this.currentRole = record;
            Scalr.cachedRequest.load(
                {
                    url: '/platforms/gce/xGetOptions',
                    params: {}
                },
                function(data, status){
                    var locationField = this.down('[name="gce.cloud-location"]'),
                        zones = [], defaultZone;

                    Ext.each(data['zones'], function(zone){
                        if (zone['name'].indexOf(record.get('cloud_location')) === 0) {
                            zones.push(zone);
                        }
                    });
                    locationField.store.loadData(zones);
                    locationField.reset();
                    defaultZone = locationField.store.first();
                    locationField.setValue(defaultZone && defaultZone.get('state') === 'UP' ? defaultZone : '');
                    locationField.setDisabled(!status);
                },
                this
            );
        },

        isValid: function() {
            var res = true,
                field;
            field = this.down('[name="gce.machine-type"]');
            res = field.validate() || {comp: field, message: 'Instance type is required'};
            if (res === true) {
                field = this.down('[name="gce.cloud-location"]');
                res = field.validate() || {comp: field, message: 'Zone is required'};
            }
            return res;
        },

        getSettings: function() {
            var location = this.down('[name="gce.cloud-location"]').getValue(),
                region = '';

            if (location.length === 1) {
                location = location[0];
                region = location;
            } else if (location.length > 1) {
                location = 'x-scalr-custom=' + location.join(':');
                region = 'x-scalr-custom';
            } else {
                location = '';
            }
            return {
                'gce.machine-type': this.down('[name="gce.machine-type"]').getValue(),
                'gce.cloud-location': location,
                'gce.region': region
            };
        },

        items: [{
            xtype: 'instancetypefield',
            name: 'gce.machine-type',
            labelWidth: 90,
            flex: 1,
            allowBlank: false
        },{
            xtype: 'combobox',
            fieldLabel: 'Avail zone',
            flex: 1,
            multiSelect: true,
            name: 'gce.cloud-location',
            valueField: 'name',
            displayField: 'description',
            allowBlank: false,
            listConfig: {
                cls: 'x-boundlist-with-icon',
                tpl : '<tpl for=".">'+
                        '<tpl if="state != &quot;UP&quot;">'+
                            '<div class="x-boundlist-item x-boundlist-item-disabled" title="Zone is offline for maintenance"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{description}&nbsp;<span class="warning"></span></div>'+
                        '<tpl else>'+
                            '<div class="x-boundlist-item"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{description}</div>'+
                        '</tpl>'+
                      '</tpl>'
            },
            store: {
                fields: [ 'name', {name: 'description', convert: function(v, record){return record.data.description || record.data.name;}}, 'state' ],
                proxy: 'object',
                sorters: ['name']
            },
            editable: false,
            queryMode: 'local',
            margin: '0 0 0 64',
            labelWidth: 70,
            listeners: {
                beforeselect: function(comp, record, index) {
                    if (comp.isExpanded) {
                        var result = true;
                        if (record.get('state') !== 'UP') {
                            result = false;
                        }
                        return result;
                    }
                },
                beforedeselect: function(comp, record, index) {
                    if (comp.isExpanded) {
                        var result = true;
                        if (comp.getValue().length < 2) {
                            Scalr.message.InfoTip('At least one zone must be selected!', comp.inputEl, {anchor: 'bottom'});
                            result = false;
                        }
                        return result;
                    }
                },
                change: function(comp, value) {
                    if (value && value.length) {
                        var container = comp.up('#gce'),
                            panel = container.up('form'),
                            f = panel.getForm().findField('cloud_location'),
                            locations = [],
                            record = container.currentRole;
                        Scalr.loadInstanceTypes(record.get('platform'), value[0], Ext.bind(panel.setupInstanceTypeField, panel, [container, record], true));
                    }
                }
            }
        }]
    };
});

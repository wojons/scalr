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
                    var locationField = this.down('[name="gce.cloud-location"]');
                        
                    locationField.store.loadData(data['zones'] || []);
                    locationField.reset();
                    locationField.setValue(record.getGceCloudLocation());
                    
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
            return res;
        },

        getSettings: function() {
            var location = this.down('[name="gce.cloud-location"]').getValue();
                if (location.length === 1) {
                    location = location[0];
                } else if (location.length > 1) {
                    location = 'x-scalr-custom=' + location.join(':');
                } else {
                    location = '';
                }
            return {
                'gce.cloud-location': location,
                'gce.machine-type': this.down('[name="gce.machine-type"]').getValue()
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
            fieldLabel: 'Location',
            flex: 1,
            multiSelect: true,
            name: 'gce.cloud-location',
            valueField: 'name',
            displayField: 'description',
            listConfig: {
                cls: 'x-boundlist-checkboxes',
                tpl : '<tpl for=".">'+
                        '<tpl if="state != &quot;UP&quot;">'+
                            '<div class="x-boundlist-item x-boundlist-item-disabled" title="Zone is offline for maintenance"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{description}&nbsp;<span class="warning"></span></div>'+
                        '<tpl else>'+
                            '<div class="x-boundlist-item"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{description}</div>'+
                        '</tpl>'+
                      '</tpl>'
            },
            store: {
                fields: [ 'name', 'description', 'state' ],
                proxy: 'object'
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
                            Scalr.message.InfoTip('At least one cloud location must be selected!', comp.inputEl, {anchor: 'bottom'});
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
                        f.suspendEvents(false);
                        f.setValue(value.length === 1 ? value[0] : 'x-scalr-custom');
                        f.resumeEvents();
                        comp.store.data.each(function(){locations.push(this.get('name'))});
                        panel.down('#locationmap').selectLocation(panel.state.platform, value, locations, 'world');
                        
                        Scalr.loadInstanceTypes(record.get('platform'), value[0], Ext.bind(panel.setupInstanceTypeField, panel, [container, record], true));
                    }
                }
            }
        }]
    };
});

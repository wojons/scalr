Scalr.regPage('Scalr.ui.farms.builder.addrole.gce', function () {
    return {
        xtype: 'container',
        itemId: 'gce',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',
        
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        defaults: {
            maxWidth: 348
        },

        isVisibleForRole: function(record) {
            return record.get('platform') === 'gce';
        },

        onSelectImage: function(record) {
            if (this.isVisibleForRole(record)) {
                this.setRole(record);
                this.show();
            } else {
                this.hide();
            }
        },

        onSettingsUpdate: function(record, name, value) {
            if (name === 'db.msr.data_storage.engine') {
                this.refreshInstanceType();
            }
        },

        refreshInstanceType: function(value) {
            var me = this,
                cloudLocation = me.down('[name="gce.cloud-location"]').getValue();
            if (cloudLocation.length > 0) {
                Scalr.cachedRequest.load(
                    {
                        url: '/platforms/gce/xGetMachineTypes',
                        params: {
                            cloudLocation: cloudLocation[0]
                        }
                    },
                    function(data, status) {
                        var mtypeField = me.down('[name="gce.machine-type"]');
                        if (status) {
                            var settings = me.currentRole.get('settings', true),
                                mtypeFieldValue;

                            if (value === undefined) {
                                value = mtypeField.getValue();
                            }

                            mtypeField.store.load({ data: data['types'] || [] });
                            mtypeFieldValue = 'n1-standard-1';

                           if (!mtypeField.findRecordByValue(value)) {
                               value = null;
                           }
                           
                            mtypeField.reset();
                            mtypeField.setValue(value || mtypeFieldValue);
                        }
                        mtypeField.setDisabled(!status);
                    }
                );
            }
            
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
                        mtypeField = this.down('[name="gce.machine-type"]'),
                        locationFieldValue = '';
                        
                    mtypeField.setDisabled(!status);
                    if (status) {
                        locationField.store.loadData(data['zones'] || []);
                        if (locationField.store.getCount() > 0) {
                            locationFieldValue = locationField.store.getAt(3);
                            locationFieldValue = locationFieldValue ? locationFieldValue.get('name') : '';
                        }
                    }
                    locationField.reset();
                    locationField.setValue(locationFieldValue);
                    
                    locationField.setDisabled(!status);
                },
                this
            );
        },

        isValid: function() {
            return true;
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
            margin: '0 64 0 0',
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
                    if (value) {
                        var panel = comp.up('form'),
                            f = panel.getForm().findField('cloud_location'),
                            locations = [];
                        f.suspendEvents(false);
                        f.setValue(value.length === 1 ? value[0] : 'x-scalr-custom');
                        f.resumeEvents();
                        comp.store.data.each(function(){locations.push(this.get('name'))});
                        panel.down('#locationmap').selectLocation(panel.state.platform, value, locations, 'world');
                        
                        this.up('#gce').refreshInstanceType();
                    }
                }
            }
        },{
            xtype: 'combo',
            store: {
                fields: [ 'name', 'description' ],
                proxy: 'object'
            },
            flex: 1,
            valueField: 'name',
            displayField: 'name',
            fieldLabel: 'Machine type',
            labelWidth: 90,
            editable: false,
            queryMode: 'local',
            name: 'gce.machine-type',
            listConfig: {
                width: 'auto',
                minWidth: 180,
                style: 'white-space:nowrap',
                getInnerTpl: function(displayField) {
                    return '{description}';
                }
            },
            listeners: {
                change: function(comp, value){
                    if (value) {
                        this.up('form').updateRecordSettings(comp.name, value);
                    }
                }
            }
        }]
    }
});

Scalr.regPage('Scalr.ui.farms.builder.addrole.euca', function () {
    return {
        xtype: 'container',
        isExtraSettings: true,
        hidden: true,
        
        cls: 'x-container-fieldset x-fieldset-separator-bottom',

        instanceTypeFieldName: 'euca.instance_type',
        
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        
        isVisibleForRole: function(record) {
            return record.get('platform') === 'eucalyptus';
        },

        setRole: function(record) {
            var formPanel = this.up('form');

            if (formPanel.up('roleslibrary').vpc === false) {
                Scalr.cachedRequest.load(
                    {
                        url: '/platforms/eucalyptus/xGetAvailZones',
                        params: {cloudLocation: record.get('cloud_location')}
                    },
                    function(data, status){
                        var availZoneField = this.down('[name="euca.availability_zone"]'),
                            items = [{id: '', name: 'Euca-chosen'}];

                        if (status) {
                            items = [{ 
                                id: 'x-scalr-diff', 
                                name: 'Distribute equally' 
                            },{ 
                                id: '', 
                                name: 'Euca-chosen' 
                            },{ 
                                id: 'x-scalr-custom', 
                                name: 'Selected by me',
                                items: Ext.Array.map(data || [], function(item){ item.disabled = item.state != 'available'; return item;})
                            }];
                        }
                        availZoneField.store.loadData(items);
                        availZoneField.setValue('');
                        availZoneField.show().setDisabled(!status);
                    },
                    this
                );
            } else {
                this.down('[name="euca.availability_zone"]').hide();
            }

        },

        isValid: function() {
            var res = true, field = this.down('[name="euca.instance_type"]');
            res = field.validate() || {comp: field, message: 'Instance type is required'};
            return res;
        },

        getSettings: function() {
            var formPanel = this.up('form'),
                settings = {},
                value;

            if (formPanel.up('roleslibrary').vpc === false) {
                value = this.down('[name="euca.availability_zone"]').getValue();
                if (Ext.isObject(value)) {
                    if (value.items) {
                        if (value.items.length === 1) {
                            value = value.items[0];
                        } else if (value.items.length > 1) {
                            value = value.id + '=' + value.items.join(':');
                        }
                    }
                }
                settings['euca.availability_zone'] = value;
            }
            settings['euca.instance_type'] = this.down('[name="euca.instance_type"]').getValue();
            return settings;
        },

        items: [{
            xtype: 'instancetypefield',
            name: 'euca.instance_type',
            labelWidth: 90,
            
            flex: 1,
            submitValue: false,
            allowBlank: false,
            listeners: {
                change: function(comp, value){
                    var record = this.findRecordByValue(value);
                    if (record) {
                        this.up('form').updateRecordSettings(comp.name, value);
                    }
                }
            }
        },{
            xtype: 'comboradio',
            fieldLabel: 'Avail zone',
            flex: 1,
            submitValue: false,
            name: 'euca.availability_zone',
            valueField: 'id',
            displayField: 'name',
            listConfig: {
                cls: 'x-menu-light'
            },
            store: {
                fields: [ 'id', 'name', 'state', 'disabled', 'items' ],
                proxy: 'object'
            },
            margin: '0 0 0 64',
            labelWidth: 70,
            listeners: {
                collapse: function() {
                    var value = this.getValue();
                    if (Ext.isObject(value) && value.items.length === 0) {
                        this.setValue('');
                    }
                }
            }
        }]
    };
});

Scalr.regPage('Scalr.ui.farms.builder.addrole.ec2', function () {
    return {
        xtype: 'container',
        isExtraSettings: true,
        hidden: true,
        
        cls: 'x-container-fieldset x-fieldset-separator-bottom',

        instanceTypeFieldName: 'aws.instance_type',

        layout: 'fit',
        defaults: {
            maxWidth: 762
        },
        
        isVisibleForRole: function(record) {
            return record.get('platform') === 'ec2';
        },

        setRole: function(record) {
            var formPanel = this.up('form');

            if (formPanel.up('roleslibrary').vpc === false) {
                Scalr.cachedRequest.load(
                    {
                        url: '/platforms/ec2/xGetAvailZones',
                        params: {cloudLocation: record.get('cloud_location')}
                    },
                    function(data, status){
                        var availZoneField = this.down('[name="aws.availability_zone"]'),
                            items = [{id: '', name: 'AWS-chosen'}];

                        if (status) {
                            items = [{ 
                                id: 'x-scalr-diff', 
                                name: 'Distribute equally' 
                            },{ 
                                id: '', 
                                name: 'AWS-chosen' 
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
                this.down('[name="aws.availability_zone"]').hide();
            }

        },

        isValid: function() {
            var res = true, field = this.down('[name="aws.instance_type"]');
            res = field.validate() || {comp: field, message: 'Instance type is required'};
            return res;
        },

        getSettings: function() {
            var formPanel = this.up('form'),
                settings = {},
                value;

            if (formPanel.up('roleslibrary').vpc === false) {
                value = this.down('[name="aws.availability_zone"]').getValue();
                if (Ext.isObject(value)) {
                    if (value.items) {
                        if (value.items.length === 1) {
                            value = value.items[0];
                        } else if (value.items.length > 1) {
                            value = value.id + '=' + value.items.join(':');
                        }
                    }
                }
                settings['aws.availability_zone'] = value;
            }
            settings['aws.instance_type'] = this.down('[name="aws.instance_type"]').getValue();
            return settings;
        },

        items: {
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'instancetypefield',
                name: 'aws.instance_type',
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
                name: 'aws.availability_zone',
                valueField: 'id',
                displayField: 'name',
                margin: '0 0 0 64',
                listConfig: {
                    cls: 'x-menu-light'
                },
                store: {
                    fields: [ 'id', 'name', 'state', 'disabled', 'items' ],
                    proxy: 'object'
                },
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
        }
    };
});

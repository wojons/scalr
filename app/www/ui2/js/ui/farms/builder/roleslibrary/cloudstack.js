Scalr.regPage('Scalr.ui.farms.builder.addrole.cloudstack', function () {
    return {
        xtype: 'container',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',

        instanceTypeFieldName: 'cloudstack.service_offering_id',

        layout: 'anchor',
        defaults: {
            anchor: '100%',
            maxWidth: 762,
            labelWidth: 110
        },
        
        isVisibleForRole: function(record) {
            return Ext.Array.contains(['cloudstack', 'idcf', 'ucloud'], record.get('platform'));
        },

        setRole: function(record) {
            var platform = record.get('platform'),
                cloudLocation = record.get('cloud_location');
            Scalr.CachedRequestManager.get('farmbuilder').load(
                {
                    url: '/platforms/cloudstack/xGetOfferingsList/',
                    params: {
                        cloudLocation: cloudLocation,
                        platform: platform,
                        farmRoleId: ''
                    }
                },
                function(data, status){
                    var me = this,
                        field,
                        defaultValue = null,
                        limits,
                        fb = this.up('#farmbuilder');
                    data = data || {};
                    this.down('[name="cloudstack.static_nat.map"]').hide().reset();
                    Ext.Object.each({
                        'cloudstack.network_id': 'networks',
                        'cloudstack.shared_ip.id': 'ipAddresses'
                    }, function(fieldName, dataFieldName){
                        var storeData;
                        limits = fb.getLimits(platform, fieldName);
                        if (limits && limits.value && limits.value[cloudLocation] && limits.value[cloudLocation].length > 0) {
                            storeData = [];
                            Ext.Array.each(data ? data[dataFieldName] : [], function(item) {
                                if (Ext.Array.contains(limits.value[cloudLocation], item.id)) {
                                    storeData.push(item);
                                }
                            });
                        } else {
                            storeData = data ? data[dataFieldName] : [];
                        }

                        defaultValue = null;
                        field = me.down('[name="' + fieldName + '"]');
                        field.store.load({ data: storeData });
                        if (field.store.getCount() == 0) {
                            field.hide();
                        } else {
                            defaultValue = field.store.getAt(0).get('id');
                            field.show();
                        }
                        field.setValue(defaultValue);
                        field.toggleIcon('governance', !!limits);
                    });
                },
                this
            );
        },

        isValid: function() {
            var res = true,
                field;
            field = this.down('[name="cloudstack.service_offering_id"]');
            res = field.validate() || {comp: field, message: 'Instance type is required'};
            if (res === true && this.down('[name="cloudstack.network_id"]').getValue() == 'SCALR_MANUAL') {
                field = this.down('[name="cloudstack.static_nat.map"]');
                res = field.validate() || {comp: field, message: 'Static IPs is required'};
            }
            return res;
        },

        getSettings: function() {
            var sharedIpIdField = this.down('[name="cloudstack.shared_ip.id"]'),
                settings = {
                'cloudstack.service_offering_id': this.down('[name="cloudstack.service_offering_id"]').getValue(),
                'cloudstack.network_id': this.down('[name="cloudstack.network_id"]').getValue()
            };
            if (settings['cloudstack.network_id'] == 'SCALR_MANUAL') {
                settings['cloudstack.static_nat.map'] = this.down('[name="cloudstack.static_nat.map"]').getValue();
                settings['cloudstack.use_static_nat'] = 1;
            } else {
                settings['cloudstack.shared_ip.id'] = sharedIpIdField.getValue();

                if (settings['cloudstack.shared_ip.id']) {
                    var r = sharedIpIdField.findRecordByValue(settings['cloudstack.shared_ip.id']);
                    settings['cloudstack.shared_ip.address'] = r ? r.get('name') : '';
                } else {
                    settings['cloudstack.shared_ip.address'] = '';
                }
            }
            return settings;
        },

        items: [{
            xtype: 'instancetypefield',
            name: 'cloudstack.service_offering_id',
            allowBlank: false,
            iconsPosition: 'outer'
        },{
            xtype: 'combo',
            name: 'cloudstack.network_id',
            flex: 1,
            matchFieldWidth: false,
            fieldLabel: 'Network',
            icons: {
                governance: true
            },
            iconsPosition: 'outer',
            editable: false,
            listConfig: {
                width: 'auto',
                minWidth: 350
            },
            queryMode: 'local',
            store: {
                fields: [ 'id', 'name' ],
                proxy: 'object'
            },
            valueField: 'id',
            displayField: 'name',
            listeners: {
                change: function(comp, value) {
                    comp.next('[name="cloudstack.static_nat.map"]').setVisible(value == 'SCALR_MANUAL');
                    comp.next('[name="cloudstack.shared_ip.id"]').setVisible(value != 'SCALR_MANUAL');
                }
            }
        }, {
            xtype: 'textfield',
            name: 'cloudstack.static_nat.map',
            fieldLabel: 'Static IPs',
            emptyText: 'ex. 1=192.168.0.1;2=192.168.0.2',
            hidden: true,
            allowBlank: false
        },{
            xtype: 'combo',
            name: 'cloudstack.shared_ip.id',
            flex: 1,
            fieldLabel: 'Shared IP',
            editable: false,
            matchFieldWidth: false,
            listConfig: {
                width: 'auto',
                minWidth: 350
            },
            queryMode: 'local',
            store: {
                fields: [ 'id', 'name' ],
                proxy: 'object'
            },
            valueField: 'id',
            displayField: 'name'
        }]
    };
});

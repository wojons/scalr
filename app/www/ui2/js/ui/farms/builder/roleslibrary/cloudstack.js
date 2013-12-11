Scalr.regPage('Scalr.ui.farms.builder.addrole.cloudstack', function () {
    return {
        xtype: 'container',
        isExtraSettings: true,
        hidden: true,

        cls: 'x-container-fieldset x-fieldset-separator-bottom',

        layout: 'anchor',
        defaults: {
            maxWidth: 760
        },
        
        isVisibleForRole: function(record) {
            return Ext.Array.contains(['cloudstack', 'idcf', 'ucloud'], record.get('platform'));
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
                        offerings,
                        fb = this.up('#farmbuilder');
                    data = data || {};
                    field = this.down('[name="cloudstack.service_offering_id"]');

                    limits = fb.getLimits(platform + '.service_offering_id');
                    if (limits && limits.value) {
                        offerings = [];
                        Ext.Array.each(data ? data['serviceOfferings'] : [], function(offering) {
                            if (Ext.Array.contains(limits.value, offering.id)) {
                                offerings.push(offering);
                            }
                        });
                    } else {
                        offerings = data ? data['serviceOfferings'] : [];
                    }

                    field.store.load({ data: offerings});
                    if (field.store.getCount() > 0) {
                        defaultValue = field.store.getAt(0).get('id');
                    }
                    field.setValue(defaultValue);
                    field.setDisabled(!status);
                    field[limits?'addCls':'removeCls']('x-field-governance');

                    Ext.Object.each({
                        'cloudstack.network_id': 'networks',
                        'cloudstack.shared_ip.id': 'ipAddresses'
                    }, function(fieldName, dataFieldName){
                        var storeData;
                        limits = fb.getLimits(platform + fieldName.replace('cloudstack', ''));
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
                        field[limits?'addCls':'removeCls']('x-field-governance');
                    });
                },
                this
            );
        },

        isValid: function() {
            var res = true,
                field;
            field = this.down('[name="cloudstack.service_offering_id"]');
            res = field.validate() || {comp: field, message: 'Service offering is required'};
            return res;
        },

        getSettings: function() {
            var sharedIpIdField = this.down('[name="cloudstack.shared_ip.id"]'),
                settings = {
                'cloudstack.service_offering_id': this.down('[name="cloudstack.service_offering_id"]').getValue(),
                'cloudstack.network_id': this.down('[name="cloudstack.network_id"]').getValue(),
                'cloudstack.shared_ip.id': sharedIpIdField.getValue()
            };

            if (settings['cloudstack.shared_ip.id']) {
                var r = sharedIpIdField.findRecordByValue(settings['cloudstack.shared_ip.id']);
                settings['cloudstack.shared_ip.address'] = r ? r.get('name') : '';
            } else {
                settings['cloudstack.shared_ip.address'] = '';
            }

            return settings;
        },

        items: [{
            xtype: 'container',
            layout: 'hbox',
            margin: '0 0 12 0',
            items: [{
                xtype: 'combo',
                name: 'cloudstack.service_offering_id',
                flex: 1,
                fieldLabel: 'Service offering',
                governance: true,
                labelWidth: 100,
                labelStyle: 'white-space:nowrap',
                editable: false,
                allowBlank: false,
                matchFieldWidth: false,
                listConfig: {
                    style: 'white-space:nowrap'
                },
                queryMode: 'local',
                store: {
                    fields: [ 'id', 'name' ],
                    proxy: 'object'
                },
                valueField: 'id',
                displayField: 'name'
            }]
        },{
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'combo',
                name: 'cloudstack.network_id',
                flex: 1,
                matchFieldWidth: false,
                fieldLabel: 'Network',
                governance: true,
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
                displayField: 'name'
            }, {
                xtype: 'combo',
                name: 'cloudstack.shared_ip.id',
                flex: 1,
                margin: '0 0 0 64',
                fieldLabel: 'Shared IP',
                labelWidth: 70,
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
        }]
    }
});

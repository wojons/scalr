Scalr.regPage('Scalr.ui.farms.builder.addrole.ec2', function () {
    return {
        xtype: 'container',
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
            return record.get('platform') === 'ec2';
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
            var formPanel = this.up('form'),
                role = formPanel.getCurrentRole(),
                instTypeLimits = formPanel.up('#farmbuilder').getLimits('aws.instance_type'),
                instType,
                field,
                tags;

            if (formPanel.mode === 'shared') {//record.getEc2InstanceType uses some tags, so we must to define them
                tags = record.get('tags') || [];
                if (role.hvm == 1) {
                    Ext.Array.include(tags, 'ec2.hvm');
                }
                if (role.ebs == 1) {
                    Ext.Array.include(tags, 'ec2.ebs');
                }
                record.set('tags', tags);
            }

            instType = record.getEc2InstanceType(instTypeLimits);
            field = this.down('[name="aws.instance_type"]');
            field.reset();
            field.store.load({data: instType.list});
            field.setValue(instType.value);
            field.setReadOnly(instType.list.length < 2, false);
            field[instTypeLimits?'addCls':'removeCls']('x-field-governance');

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

        items: [{
            xtype: 'comboradio',
            fieldLabel: 'Avail zone',
            flex: 1,
            submitValue: false,
            name: 'aws.availability_zone',
            valueField: 'id',
            displayField: 'name',
            listConfig: {
                cls: 'x-menu-light'
            },
            store: {
                fields: [ 'id', 'name', 'state', 'disabled', 'items' ],
                proxy: 'object'
            },
            margin: '0 64 0 0',
            labelWidth: 70,
            listeners: {
                collapse: function() {
                    var value = this.getValue();
                    if (Ext.isObject(value) && value.items.length === 0) {
                        this.setValue('');
                    }
                }
            }
        },{
            xtype: 'combo',
            flex: 1,
            submitValue: false,
            editable: false,
            hideInputOnReadOnly: true,
            labelWidth: 90,
            queryMode: 'local',
            name: 'aws.instance_type',
            fieldLabel: 'Instance type',
            governance: true,
            store: {
                fields: [ 'id', 'name' ],
                proxy: 'object'
            },
            valueField: 'name',
            emptyText: 'No suitable instance types',
            displayField: 'name',
            allowBlank: false,
            anyMatch: true,
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

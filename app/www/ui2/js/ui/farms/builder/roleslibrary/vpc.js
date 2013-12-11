Scalr.regPage('Scalr.ui.farms.builder.addrole.vpc', function () {
    return {
        xtype: 'fieldset',
        itemId: 'vpc',
        isExtraSettings: true,
        hidden: true,

        baseTitle: 'VPC-related settings',
        title: '',
        layout: 'anchor',
        defaults: {
            anchor: '100%',
            labelWidth: 110,
            maxWidth: 760
        },
        
        isVisibleForRole: function(record) {
            return record.get('platform') === 'ec2' && 
                   this.up('roleslibrary').vpc !== false &&
                   !Ext.Array.contains(record.get('behaviors'), 'router');
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
            var field,
                vpc = this.up('roleslibrary').vpc,
                limits = this.up('#farmbuilder').getLimits('aws.vpc'),
                defaultType = 'new',
                forceInternetAccess,
                disableAddSubnet = false;
                
            if (limits && limits['ids']) {
                var fieldLimits = limits['ids'][vpc.id];
                if (Ext.isArray(fieldLimits)) {
                    defaultType = 'existing';
                    disableAddSubnet = true;
                } else if (Ext.isString(fieldLimits)) {
                    forceInternetAccess = fieldLimits;
                }
            }
            
            field = this.down('[name="aws.vpc_internet_access"]');
            field.down('[value="full"]').setDisabled(forceInternetAccess && forceInternetAccess !== 'full');
            field.down('[value="outbound-only"]').setDisabled(forceInternetAccess && forceInternetAccess !== 'outbound-only');
            field.setValue(forceInternetAccess || 'outbound-only');

            field = this.down('[name="aws.vpc_routing_table_id"]');
            field.store.getProxy().params = {cloudLocation: vpc.region, vpcId: vpc.id}
            field.reset();
            
            field = this.down('[name="vpcSubnetType"]');
            field.setValue(defaultType);
            field.down('[value="new"]').setDisabled(disableAddSubnet);

            this.down('[name="aws.vpc_avail_zone"]').store.getProxy().params = {cloudLocation: vpc.region};

            field = this.down('[name="aws.vpc_subnet_id"]');
            Ext.apply(field.store.getProxy(), {
                params: {cloudLocation: vpc.region, vpcId: vpc.id},
                filterFn: limits && limits['ids'] && limits['ids'][vpc.id] ? this.subnetsFilterFn : null,
                filterFnScope: this
            });

            this.setTitle(this.baseTitle + (limits?'&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" title="VPC settings is limited by account owner" class="x-icon-governance" />':''));
        },

        isValid: function() {
            var res = true,
                fields = this.query('[isFormField]');
                
            for (var i = 0, len = fields.length; i < len; i++) {
                res = fields[i].validate() || {comp:fields[i]};
                if (res !== true) {
                    break;
                }
            }
            return res;
        },

        getSettings: function() {
            var settings = {
                    'aws.vpc_internet_access': null,
                    'aws.vpc_subnet_id': null,
                    'aws.vpc_avail_zone': null
                };

            if (this.down('[name="vpcSubnetType"]').getValue() === 'new') {
                settings['aws.vpc_avail_zone'] = this.down('[name="aws.vpc_avail_zone"]').getValue();
                settings['aws.vpc_internet_access'] = this.down('[name="aws.vpc_internet_access"]').getValue();
                settings['aws.vpc_routing_table_id'] = this.down('[name="aws.vpc_routing_table_id"]').getValue();
                if (settings['aws.vpc_internet_access'] === 'full') {
                    settings['aws.use_elastic_ips'] = 1;
                }
            } else {
                var subnetIdField = this.down('[name="aws.vpc_subnet_id"]'),
                    subnet = subnetIdField.findRecordByValue(subnetIdField.getValue());
                settings['aws.vpc_subnet_id'] = subnetIdField.getValue();
                if (subnet && subnet.get('internet') === 'full') {
                    settings['aws.use_elastic_ips'] = 1;
                }
            }
            return settings;
        },

        subnetsFilterFn: function(record) {
            var res = false,
                limits = this.up('#farmbuilder').getLimits('aws.vpc'),
                vpc = this.up('roleslibrary').vpc,
                fieldLimits = limits['ids'][vpc.id],
                filterType = Ext.isArray(fieldLimits) ? 'subnets' : 'iaccess',
                internet = record.get('internet');
            if (
                filterType === 'subnets' && Ext.Array.contains(fieldLimits, record.get('id')) ||
                filterType === 'iaccess' && (internet === undefined || internet === fieldLimits)
            ) {
                res = true;
            }
            return res;
        },
        
        items: [{
            xtype: 'buttongroupfield',
            name: 'vpcSubnetType',
            fieldLabel: 'Placement',
            labelWidth: 90,
            submitValue: false,
            defaults: {
                width: 123
            },
            items: [{
                text: 'New subnet',
                value: 'new'
            },{
                text: 'Existing subnet',
                value: 'existing'
            }],
            listeners: {
                change: function(comp, value) {
                    var c = comp.next(),
                        isNew = value === 'new',
                        subnetIdField =  c.down('[name="aws.vpc_subnet_id"]'),
                        subnet = subnetIdField.findRecordByValue(subnetIdField.getValue());
                        
                    c.suspendLayouts();
                    c.down('[name="aws.vpc_avail_zone"]').setVisible(isNew).setDisabled(!isNew);
                    c.down('[name="aws.vpc_internet_access"]').setVisible(isNew).setDisabled(!isNew);
                    c.down('[name="aws.vpc_routing_table_id"]').setVisible(isNew).setDisabled(!isNew);
                    
                    subnetIdField.setVisible(!isNew).setDisabled(isNew);
                    if (!isNew && subnet) {
                        c.down('#vpc_internet_access').setValue(Ext.String.capitalize(subnet.get('internet') || 'unknown')).show();
                    } else {
                        c.down('#vpc_internet_access').hide();
                    }
                    
                    c.resumeLayouts(true);
                }
            }
        },{
            xtype: 'container',
            cls: 'inner-container',
            items: [{
                xtype: 'combo',
                name: 'aws.vpc_avail_zone',
                fieldLabel: 'Avail zone',
                submitValue: false,
                width: 325,
                valueField: 'id',
                displayField: 'name',
                allowBlank: false,
                
                editable: false,
                queryCaching: false,
                minChars: 0,
                queryDelay: 10,
                store: {
                    fields: [ 'id', 'name' ],
                    proxy: {
                        type: 'cachedrequest',
                        url: '/platforms/ec2/xGetAvailZones'
                    }
                }
            },{
                xtype: 'combo',
                name: 'aws.vpc_routing_table_id',
                fieldLabel: 'Routing table',
                hidden: true,
                width: 325,
                valueField: 'id',
                displayField: 'name',
                emptyText: 'Scalr default',

                editable: false,
                queryCaching: false,
                minChars: 0,
                queryDelay: 10,
                store: {
                    fields: [ 'id', 'name' ],
                    proxy: {
                        type: 'cachedrequest',
                        crscope: 'farmbuilder',
                        url: '/platforms/ec2/xGetRoutingTableList',
                        root: 'tables',
                        prependData: [{id: '', name: 'Scalr default'}]
                    }
                }
            },{
                xtype: 'buttongroupfield',
                name: 'aws.vpc_internet_access',
                submitValue: false,
                fieldLabel: 'Internet access',
                width: 325,
                defaults:{
                    width: 109
                },
                items: [{
                    text: 'Full',
                    value: 'full'
                },{
                    text: 'Outbound-only',
                    value: 'outbound-only'
                }]
            },{
                xtype: 'combo',
                name: 'aws.vpc_subnet_id',
                fieldLabel: 'Subnet',
                submitValue: false,
                width: 425,
                valueField: 'id',
                displayField: 'description',
                allowBlank: false,
                
                editable: false,
                queryCaching: false,
                minChars: 0,
                queryDelay: 10,
                store: {
                    fields: ['id' , 'description', 'availability_zone', 'internet', 'ips_left', 'sidr', 'name'],
                    proxy: {
                        type: 'cachedrequest',
                        url: '/platforms/ec2/xGetSubnetsList'
                    }
                },
                listConfig: {
                    style: 'white-space:nowrap',
                    cls: 'x-boundlist-alt',
                    tpl:
                        '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto;line-height:20px">' +
                            '<div><span style="font-weight: bold">{name} - {id}</span> <span style="font-style: italic;font-size:90%">(Internet access: <b>{[values.internet || \'unknown\']}</b>)</span></div>' +
                            '<div>{sidr} in {availability_zone} [IPs left: {ips_left}]</div>' +
                        '</div></tpl>'
                },
                listeners: {
                    change: function(comp, value) {
                        var tab = this.up('#vpc'),
                            rec = comp.findRecordByValue(value);
                        if (rec) {
                            tab.down('#vpc_internet_access').setValue(Ext.String.capitalize(rec.get('internet') || 'unknown')).show();
                        } else {
                            tab.down('#vpc_internet_access').hide();
                        }
                    }
                }
            },{
                xtype: 'displayfield',
                itemId: 'vpc_internet_access',
                fieldLabel: 'Internet access',
                hidden: true
            }]
        }]
    }
});

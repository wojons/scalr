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
            labelWidth: 115,
            maxWidth: 760
        },
        
        isVisibleForRole: function(record) {
            return record.get('platform') === 'ec2' && this.up('roleslibrary').vpc !== false;
        },

        setRole: function(record) {
            var field,
                vpc = this.up('roleslibrary').vpc,
                limits = this.up('#farmbuilder').getLimits('ec2', 'aws.vpc'),
                moduleTabParams = this.up('roleslibrary').moduleParams['tabParams'];
            this.isVpcRouter = Ext.Array.contains(record.get('behaviors'), 'router');
            this.instancesConnectionPolicy = moduleTabParams['scalr.instances_connection_policy'];
            field = this.down('[name="aws.vpc_subnet_id"]');
            field.maxCount = this.isVpcRouter ? 1 : 0;
            field.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + vpc.region + '&vpcId=' + vpc.id;
            field.getPlugin('comboaddnew').setDisabled(limits && limits['ids'] && Ext.isArray(limits['ids'][vpc.id]));
            Ext.apply(field.store.getProxy(), {
                params: {cloudLocation: vpc.region, vpcId: vpc.id, extended: 1},
                filterFn: this.subnetsFilterFn,
                filterFnScope: this
            });
            field.reset();

            field = this.down('[name="router.scalr.farm_role_id"]');
            Ext.apply(field.store.getProxy(), {params: {vpcId: vpc.id}});
            field.reset();

            field = this.down('[name="router.vpc.networkInterfaceId"]');
            field.store.proxy.params = {
                cloudLocation: vpc.region,
                vpcId: vpc.id
            };
            field.reset();
            field.setVisible(this.isVpcRouter);

            this.setTitle(this.baseTitle + (limits?'&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Ext.String.htmlEncode(Scalr.strings['farmbuilder.vpc.enforced']) + '" class="x-icon-governance" />':''));
        },

        isValid: function() {
            var res = true,
                fields = this.query('[isFormField]');
                
            for (var i = 0, len = fields.length; i < len; i++) {
                if (fields[i].isVisible()) {
                    res = fields[i].validate() || {comp:fields[i]};
                    if (res !== true) {
                        break;
                    }
                }
            }
            return res;
        },

        getSettings: function() {
            var settings, value, field;
            value = this.down('[name="aws.vpc_subnet_id"]').getValue();
            settings = {
                'aws.vpc_subnet_id': Ext.encode(value)
            };
            if (this.isVpcRouter) {
                settings['router.vpc.networkInterfaceId'] = this.down('[name="router.vpc.networkInterfaceId"]').getValue();
            } else if (this.instancesConnectionPolicy === 'public') {
                var field = this.down('[name="router.scalr.farm_role_id"]');
                if (field.isVisible()) {
                    settings['router.scalr.farm_role_id'] = field.getValue();
                }
            }
            return settings;
        },

        subnetsFilterFn: function(record) {
            var res = false,
                limits = this.up('#farmbuilder').getLimits('ec2', 'aws.vpc'),
                vpc = this.up('roleslibrary').vpc,
                fieldLimits, filterType;

            var type = record.get('type');
            if (type === 'private' && this.isVpcRouter) {
                res = false;
            } else if (limits && limits['ids'] && limits['ids'][vpc.id]) {
                fieldLimits = limits['ids'][vpc.id];
                filterType = Ext.isArray(fieldLimits) ? 'subnets' : 'iaccess';
                if (filterType === 'subnets' && Ext.Array.contains(fieldLimits, record.get('id'))) {
                    res = true;
                } else if (filterType === 'iaccess') {
                    res = type === 'private' && fieldLimits === 'outbound-only' || type === 'public' && fieldLimits === 'full';
                }
            } else {
                res = true;
            } 
            return res;
        },
        
        items: [{
            xtype: 'comboboxselect',
            name: 'aws.vpc_subnet_id',
            fieldLabel: 'Subnet',
            displayField: 'description',
            valueField: 'id',
            //emptyText: '',
            flex: 1,
            queryCaching: false,
            clearDataBeforeQuery: true,
            allowBlank: false,
            minChars: 0,
            queryDelay: 10,
            store: {
                fields: ['id', 'name', 'description', 'ips_left', 'type', 'availability_zone', 'cidr'],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'farmbuilder',
                    url: '/tools/aws/vpc/xListSubnets',
                    filterFields: ['description']
                }
            },
            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/aws/vpc/createSubnet',
                applyNewValue: false
            }],
            listeners: {
                addnew: function(item) {
                    Scalr.CachedRequestManager.get('farmbuilder').setExpired({
                        url: '/tools/aws/vpc/xListSubnets',
                        params: this.store.proxy.params
                    });
                },
                change: function(comp, value) {
                    var form = this.up('#vpc'),
                        field,
                        networkType,
                        subnets = comp.getValue();

                    Ext.Array.each(subnets, function(subnet) {
                        var record = comp.findRecordByValue(subnet);
                        if (record) {
                            networkType = record.get('type');
                        }
                        return !networkType;
                    });
                    form.down('#warning').setVisible(subnets.length > 1);

                    form.down('#networkType').setValue(networkType ? Ext.String.capitalize(networkType) : '')
                    form.down('#networkTypeCt').setVisible(!!networkType);
                    form.down('#networkTypeInfo').setInfo(networkType === 'public' ? Scalr.strings['vpc.public_subnet.info'] : Scalr.strings['vpc.private_subnet.info']);

                    field = form.down('[name="router.scalr.farm_role_id"]');
                    if (form.instancesConnectionPolicy === 'public') {
                        field.setVisible(networkType === 'private');
                        field.allowBlank = networkType !== 'private';
                    }

                    var interfaceIdField = field.next('[name="router.vpc.networkInterfaceId"]');
                    if (interfaceIdField.isVisible()) {
                        interfaceIdField.reset();
                        interfaceIdField.updateEmptyText(true);
                        if (subnets.length > 0) {
                            interfaceIdField.store.proxy.params['subnetId'] = subnets[0];
                            interfaceIdField.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(interfaceIdField.store.proxy.params);
                        }
                    }


                },
                beforeselect: function(comp, record) {
                    var subnets = this.getValue(),
                        rec;
                    if (subnets.length > 0) {
                        if (this.maxCount && subnets.length >= this.maxCount) {
                            Scalr.message.InfoTip('Single subnet is required for VPC Router', comp.bodyEl, {anchor: 'bottom'});
                            return false;
                        }
                        
                        rec = comp.findRecordByValue(subnets[0]);
                        if (rec && rec.get('type') == record.get('type')) {
                            return true;
                        } else {
                            Scalr.message.InfoTip('Only subnets of the <b>same type</b> can be selected.', comp.bodyEl, {anchor: 'bottom'});
                            return false;
                        }
                    }
                    return true;
                }
            },
            listConfig: {
                style: 'white-space:nowrap',
                cls: 'x-boundlist-alt',
                tpl:
                    '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto;line-height:20px">' +
                        '<div><span style="font-weight: bold">{[values.name || \'<i>No name</i>\' ]} - {id}</span> <span style="font-style: italic;font-size:90%">(Type: <b>{type:capitalize}</b>)</span></div>' +
                        '<div>{cidr} in {availability_zone} [IPs left: {ips_left}]</div>' +
                    '</div></tpl>'
            }
        },{
            xtype: 'container',
            layout: 'hbox',
            itemId: 'networkTypeCt',
            hidden: true,
            items: [{
                xtype: 'displayfield',
                itemId: 'networkType',
                fieldLabel: 'Network type',
                labelWidth: 115
            },{
                xtype: 'displayinfofield',
                itemId: 'networkTypeInfo',
                margin: '0 0 0 5',
                info:   ''
            }]
        },{
            xtype: 'combo',
            flex: 1,
            name: 'router.scalr.farm_role_id',
            fieldLabel: 'Scalr VPC Router',
            editable: false,
            hidden: true,

            queryCaching: false,
            clearDataBeforeQuery: true,
            store: {
                fields: ['farm_role_id', 'nid', 'ip', 'farm_name', 'role_name', {name: 'description', convert: function(v, record){
                    return record.data.farm_name + ' -> ' + record.data.role_name + ' -> ' + record.data.ip + ' (' + record.data.nid + ')';
                }} ],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'farmbuilder',
                    url: '/tools/aws/vpc/xListScalrRouters'
                }
            },
            valueField: 'farm_role_id',
            displayField: 'description',
            updateEmptyText: function(type){
                this.emptyText =  type ? 'Please select a VPC Router' : 'No VPC Router found in this VPC';
                this.applyEmptyText();
            },
            listeners: {
                afterrender: function(){
                    var me = this;
                    me.updateEmptyText(true);
                    me.store.on('load', function(store, records, result){
                        me.updateEmptyText(records && records.length > 0);
                    });
                }
            }
        },{
            xtype: 'combo',
            flex: 1,
            name: 'router.vpc.networkInterfaceId',
            fieldLabel: 'Network interface',
            editable: false,
            hidden: true,

            queryCaching: false,
            clearDataBeforeQuery: true,
            forceSelection: false,
            allowBlank: false,

            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/aws/vpc/createNetworkInterface'
            }],

            store: {
                fields: ['id', 'publicIp', {name: 'description', convert: function(v, record){
                    return record.data.id + (record.data.publicIp ? ' (' + record.data.publicIp + ')' : '');
                }} ],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'farmbuilder',
                    url: '/tools/aws/vpc/xListNetworkInterfaces'
                }
            },
            valueField: 'id',
            displayField: 'description',
            updateEmptyText: function(type){
                this.emptyText =  type ? 'Please select network interface' : 'No Elastic Network Interfaces available, click (+) to create one';
                this.applyEmptyText();
            },
            listeners: {
                boxready: function() {
                    var me = this;
                    me.store.on('load', function(store, data){
                        me.updateEmptyText(data.length > 0);
                    });
                },
                addnew: function(item) {
                    Scalr.CachedRequestManager.get('farmbuilder').setExpired({
                        url: '/tools/aws/vpc/xListNetworkInterfaces',
                        params: this.store.proxy.params
                    });
                },
                beforequery: function() {
                    var subnetIdField = this.prev('[name="aws.vpc_subnet_id"]'),
                        value = subnetIdField.getValue();
                    if (!value || !value.length) {
                        this.collapse();
                        Scalr.message.InfoTip('Select subnet first.', subnetIdField.getEl());
                        return false;
                    }
                },
            }
        },{
            xtype: 'displayfield',
            itemId: 'warning',
            margin: 0,
            flex: 1,
            hidden: true,
            cls: 'x-form-field-info',
            value: 'If more than one subnet is selected Scalr will evenly distribute instances across each subnet.'

        }]
    }
});

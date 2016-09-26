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
            labelWidth: 120,
            maxWidth: 760
        },

        isVisibleForRole: function(record) {
            return record.get('platform') === 'ec2' && this.up('roleslibrary').vpc !== false;
        },

        setRole: function(record) {
            var field,
                roleslibrary = this.up('roleslibrary'),
                defaultSettings = roleslibrary.defaultRoleSettings || {},
                vpc = roleslibrary.vpc,
                limits = Scalr.getGovernance('ec2', 'aws.vpc'),
                moduleTabParams = this.up('#farmDesigner').moduleParams['tabParams'];
            this.isVpcRouter = Ext.Array.contains(record.get('behaviors'), 'router');
            this.instancesConnectionPolicy = moduleTabParams['scalr.instances_connection_policy'];

            field = this.down('[name="aws.vpc_subnet_id"]');
            field.isVpcRouter = this.isVpcRouter;
            field.maxCount = this.isVpcRouter ? 1 : 0;
            field.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + vpc.region + '&vpcId=' + vpc.id;
            field.getPlugin('comboaddnew').setDisabled(limits && limits['ids'] && Ext.isArray(limits['ids'][vpc.id]));
            field.store.getProxy().params = {
                cloudLocation: vpc.region,
                vpcId: vpc.id,
                extended: 1
            };
            field.reset();
            if (defaultSettings['roleId'] == record.get('role_id') &&
                defaultSettings['settings'] &&
                defaultSettings['settings']['settings'] &&
                defaultSettings['settings']['settings']['aws.vpc_subnet_id']
            ) {
                field.store.on('load', function() {
                    this.setValue([defaultSettings['settings']['settings']['aws.vpc_subnet_id']]);
                }, field, { single: true });
                field.store.load();
            }

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

            this.down('#routerWarning').setVisible(this.isVpcRouter);

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

        items: [{
            xtype: 'displayfield',
            itemId: 'routerWarning',
            hidden: true,
            anchor: '100%',
            cls: 'x-form-field-info',
            value: 'VPC Routers must be deployed in a public VPC subnet.'
        },{
            xtype: 'vpcsubnetfield',
            name: 'aws.vpc_subnet_id',
            fieldLabel: 'Subnet',
            flex: 1,
            allowBlank: false,
            requireSameSubnetType: true,
            listeners: {
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

                    var networkTypeField = form.down('#networkType');
                    networkTypeField.setValue(networkType ? Ext.String.capitalize(networkType) : '')
                    networkTypeField.setVisible(!!networkType);
                    networkTypeField.updateIconTooltip('info', networkType === 'public' ? Scalr.strings['vpc.public_subnet.info'] : Scalr.strings['vpc.private_subnet.info']);

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


                }
            }
        },{
            xtype: 'displayfield',
            itemId: 'networkType',
            fieldLabel: 'Network type',
            hidden: true,
            anchor: null,
            plugins: [{
                ptype: 'fieldicons',
                align: 'right',
                position: 'outer',
                icons: {
                    id: 'info',
                    tooltip: ''
                }
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
                    crscope: 'farmDesigner',
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
            xtype: 'vpcnetworkinterfacefield',
            name: 'router.vpc.networkInterfaceId',
            flex: 1,
            hidden: true,
            allowBlank: false
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

Scalr.regPage('Scalr.ui.farms.builder.addrole.azure', function () {
    return {
        xtype: 'container',
        itemId: 'azure',
        isExtraSettings: true,
        hidden: true,

        defaults: {
            defaults: {
                anchor: '100%',
                labelWidth: 120,
                maxWidth: 760
            }
        },
        layout: 'anchor',

        isVisibleForRole: function(record) {
            return record.get('platform') === 'azure';
        },

        setRole: function(record) {
            var field,
                resourceGroupGrovernance = Scalr.getGovernance('azure', 'azure.resource-group');
            this.currentRole = record;

            field = this.down('[name="azure.resource-group"]');
            //field.toggleIcon('governance', !!resourceGroupGrovernance);
            if (resourceGroupGrovernance !== undefined) {
                field.store.proxy.data = Ext.Array.map(resourceGroupGrovernance.value, function(v){
                    return {
                        id: v,
                        name: v
                    };
                });
                field.getPlugin('comboaddnew').setDisabled(true);
                if (resourceGroupGrovernance['default']) {
                    field.store.load();
                    field.setValue(resourceGroupGrovernance['default']);
                }
            } else {
                delete field.store.proxy.data;
                field.getPlugin('comboaddnew').setDisabled(false);
                field.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + record.get('cloud_location');
            }

            field = this.down('[name="azure.storage-account"]');
            field.reset();
            field.store.proxy.params['cloudLocation'] = record.get('cloud_location');
            field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(field.store.proxy.params);
            field.updateEmptyText(true);

            field = this.down('[name="azure.availability-set"]');
            field.reset();
            field.store.proxy.params['cloudLocation'] = record.get('cloud_location');
            field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(field.store.proxy.params);
            field.updateEmptyText(true);

            field = this.down('[name="azure.virtual-network"]');
            field.reset();
            field.store.proxy.params['cloudLocation'] = record.get('cloud_location');
            field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(field.store.proxy.params);
            field.updateEmptyText(true);

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
            return {
                'azure.resource-group': this.down('[name="azure.resource-group"]').getValue(),
                'azure.storage-account': this.down('[name="azure.storage-account"]').getValue(),
                'azure.availability-set': this.down('[name="azure.availability-set"]').getValue(),
                'azure.virtual-network': this.down('[name="azure.virtual-network"]').getValue(),
                'azure.subnet': this.down('[name="azure.subnet"]').getValue(),
                'azure.use_public_ips': this.down('[name="azure.use_public_ips"]').getValue() ? 1 : 0
            };
        },

        items: [{
            xtype: 'fieldset',
            title: 'Azure',
            items: [{
                xtype: 'combo',
                name: 'azure.resource-group',
                fieldLabel: 'Resource group',
                editable: false,
                allowBlank: false,
                forceSelection: false,
                store: {
                    fields: ['id', 'name'],
                    sorters: {
                        property: 'name'
                    },
                    proxy: {
                        type: 'cachedrequest',
                        crscope: 'farmDesigner',
                        url: '/platforms/azure/xGetResourceGroups',
                        params: {},
                        root: 'resourceGroups'
                    }
                },
                valueField: 'id',
                displayField: 'name',
                //queryCaching: false,
                plugins: [{
                    ptype: 'comboaddnew',
                    pluginId: 'comboaddnew',
                    url: '/tools/azure/resourceGroups/create'
                /*},{
                    ptype: 'fieldicons',
                    position: 'outer',
                    icons: ['governance']*/
                }],
                listeners: {
                    addnew: function(item) {
                        Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                            url: '/platforms/azure/xGetResourceGroups'
                        });
                    },
                    afterrender: function(){
                        var me = this;
                        me.store.on('load', function(store, records, result){
                            me.emptyText = records && records.length > 0 ? ' ' : 'No existing Resource Groups found';
                            me.applyEmptyText();
                        });
                    },
                    change: function(comp, value) {
                        var tab = comp.up('#azure');
                        if (value) {
                            var field,
                                params = {
                                    resourceGroup: value,
                                    cloudLocation: comp.up('#azure').currentRole.get('cloud_location')
                                };
                            field = comp.next('[name="azure.storage-account"]');
                            field.reset();
                            field.store.proxy.params = params;
                            field.show();
                            field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(params);
                            field.updateEmptyText(true);

                            field = comp.next('[name="azure.availability-set"]');
                            field.reset();
                            field.store.proxy.params = params;
                            field.show();
                            field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(params);
                            field.updateEmptyText(true);

                            field = tab.down('[name="azure.virtual-network"]');
                            field.reset();
                            field.store.proxy.params = params;
                            tab.down('#network').show();
                            field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(params);
                            field.updateEmptyText(true);
                        } else {
                            comp.next('[name="azure.storage-account"]').hide();
                            comp.next('[name="azure.availability-set"]').hide();
                            tab.down('#network').hide();
                        }
                    }
                }
            },{
                xtype: 'combo',
                name: 'azure.storage-account',
                fieldLabel: 'Storage Account',
                editable: false,
                allowBlank: false,
                hidden: true,
                queryCaching: false,
                clearDataBeforeQuery: true,
                store: {
                    fields: ['id', 'name'],
                    sorters: {
                        property: 'name'
                    },
                    proxy: {
                        type: 'cachedrequest',
                        crscope: 'farmDesigner',
                        url: '/platforms/azure/xGetOptions',
                        params: {},
                        root: 'storageAccounts'
                    }
                },
                valueField: 'id',
                displayField: 'name',
                plugins: [{
                    ptype: 'comboaddnew',
                    pluginId: 'comboaddnew',
                    url: '/tools/azure/storageAccounts/create'
                }],
                updateEmptyText: function(isEmpty){
                    this.emptyText =  isEmpty ? ' ' : 'No existing Storage Accounts found';
                    this.applyEmptyText();
                },
                listeners: {
                    addnew: function(item) {
                        Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                            url: '/platforms/azure/xGetOptions',
                            params: this.store.proxy.params
                        });
                    },
                    afterrender: function(){
                        var me = this;
                        me.store.on('load', function(store, records, result){
                            me.updateEmptyText(records && records.length > 0);
                        });
                    }
                }
            },{
                xtype: 'combo',
                name: 'azure.availability-set',
                fieldLabel: 'Availability Set',
                editable: false,
                allowBlank: false,
                hidden: true,
                queryCaching: false,
                clearDataBeforeQuery: true,
                store: {
                    fields: ['id', 'name'],
                    sorters: {
                        property: 'name'
                    },
                    proxy: {
                        type: 'cachedrequest',
                        crscope: 'farmDesigner',
                        url: '/platforms/azure/xGetOptions',
                        params: {},
                        root: 'availabilitySets'
                    }
                },
                valueField: 'id',
                displayField: 'name',
                plugins: [{
                    ptype: 'comboaddnew',
                    pluginId: 'comboaddnew',
                    url: '/tools/azure/availabilitySets/create'
                }],
                updateEmptyText: function(isEmpty){
                    this.emptyText =  isEmpty ? ' ' : 'No existing Availabilty Sets found';
                    this.applyEmptyText();
                },
                listeners: {
                    addnew: function(item) {
                        Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                            url: '/platforms/azure/xGetOptions',
                            params: this.store.proxy.params
                        });
                    },
                    afterrender: function(){
                        var me = this;
                        me.store.on('load', function(store, records, result){
                            me.updateEmptyText(records && records.length > 0);
                        });
                    }
                }
            }]
        },{
            xtype: 'fieldset',
            title: 'Network',
            itemId: 'network',
            hidden: true,
            items: [{
                xtype: 'combo',
                name: 'azure.virtual-network',
                fieldLabel: 'Virtual network',
                editable: false,
                allowBlank: false,
                queryCaching: false,
                clearDataBeforeQuery: true,
                store: {
                    fields: ['id', 'name'],
                    proxy: {
                        type: 'cachedrequest',
                        crscope: 'farmDesigner',
                        url: '/platforms/azure/xGetOptions',
                        params: {},
                        root: 'virtualNetworks'
                    }
                },
                valueField: 'id',
                displayField: 'name',
                plugins: [{
                    ptype: 'comboaddnew',
                    pluginId: 'comboaddnew',
                    url: '/tools/azure/virtualNetworks/create'
                }],
                updateEmptyText: function(isEmpty){
                    this.emptyText =  isEmpty ? ' ' : 'No existing Virtual Networks found';
                    this.applyEmptyText();
                },
                listeners: {
                    addnew: function(item) {
                        Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                            url: '/platforms/azure/xGetOptions',
                            params: this.store.proxy.params
                        });
                    },
                    afterrender: function(){
                        var me = this;
                        me.store.on('load', function(store, records, result){
                            me.updateEmptyText(records && records.length > 0);
                        });
                    },
                    change: function(comp, value){
                        var rec = comp.findRecordByValue(value),
                            field = comp.next('[name="azure.subnet"]');
                        field.reset();
                        if (value && rec) {
                            field.show();
                            field.store.load({data: rec.get('subnets')});
                            field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(comp.store.proxy.params) + '&virtualNetwork=' + value;
                        } else {
                            field.hide();
                        }
                    }
                }
            },{
                xtype: 'combo',
                name: 'azure.subnet',
                fieldLabel: 'Subnet',
                editable: false,
                queryMode: 'local',
                allowBlank: false,
                store: {
                    model: Scalr.getModel({fields: [ 'id', 'name' ]}),
                    proxy: 'object'
                },
                valueField: 'id',
                displayField: 'name',
                plugins: [{
                    ptype: 'comboaddnew',
                    pluginId: 'comboaddnew',
                    url: '/tools/azure/virtualNetworks/createSubnet',
                    params: {}
                }],
                hidden: true,
                updateEmptyText: function(isEmpty){
                    this.emptyText =  isEmpty ? ' ' : 'No existing Subnets found';
                    this.applyEmptyText();
                },
                listeners: {
                    addnew: function(item) {
                        Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                            url: '/platforms/azure/xGetOptions',
                            params: this.prev('[name="azure.virtual-network"]').store.proxy.params
                        });
                    },
                    afterrender: function(){
                        var me = this;
                        me.store.on('load', function(store, records, result){
                            me.updateEmptyText(records && records.length > 0);
                        });
                    },
                    change: function(comp, value) {
                        comp.next('[name="azure.use_public_ips"]').setVisible(!!value);
                    }
                }
            },{
                xtype: 'checkbox',
                name: 'azure.use_public_ips',
                hidden: true,
                boxLabel: 'Attach dynamic public IP to every instance of this Farm Role',
            }]
        }]
    };
});

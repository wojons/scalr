Scalr.regPage('Scalr.ui.tools.openstack.lb.pools.vipinfo', function (loadParams, moduleParams) {

    var panel = Ext.create('Ext.form.Panel', {
        width: 460,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Load balancers &raquo; Pools &raquo; VIP details',
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },
        tools: [{
                type: 'close',
                handler: function () {
                    Scalr.event.fireEvent('close');
                }
            }],

        items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                title: 'Virtual IP details',
                defaults: {
                    labelWidth: 150,
                    value: '-'
                },
                items: [{
                        fieldLabel: 'ID',
                        xtype: 'displayfield',
                        name: 'id'
                    }, {
                        fieldLabel: 'Tenant ID',
                        xtype: 'displayfield',
                        name: 'tenant_id'
                    }, {
                        fieldLabel: 'Name',
                        xtype: 'displayfield',
                        name: 'name'
                    }, {
                        fieldLabel: 'Description',
                        xtype: 'displayfield',
                        name: 'description'
                    }, {
                        fieldLabel: 'Subnet ID',
                        xtype: 'displayfield',
                        name: 'subnet_id'
                    }, {
                        fieldLabel: 'Address',
                        xtype: 'displayfield',
                        name: 'address'
                    }, {
                        fieldLabel: 'Protocol port',
                        xtype: 'displayfield',
                        name: 'protocol_port'
                    }, {
                        fieldLabel: 'Protocol',
                        xtype: 'displayfield',
                        name: 'protocol'
                    }, {
                        fieldLabel: 'Pool ID',
                        xtype: 'displayfield',
                        name: 'pool_id'
                    }, {
                        fieldLabel: 'Session persistence',
                        xtype: 'displayfield',
                        name: 'session_persistence_type'
                    }, {
                        fieldLabel: 'Cookie name',
                        xtype: 'displayfield',
                        name: 'session_persistence_cookie_name'
                    }, {
                        fieldLabel: 'Connection limit',
                        xtype: 'displayfield',
                        name: 'connection_limit'
                    }, {
                        fieldLabel: 'Admin state up',
                        xtype: 'displayfield',
                        name: 'admin_state_up'
                    }, {
                        fieldLabel: 'Status',
                        xtype: 'displayfield',
                        name: 'status'
                }]
        }]
    });

    panel.getForm().setValues(moduleParams['vip']);
    return panel;
});

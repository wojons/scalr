Scalr.regPage('Scalr.ui.tools.openstack.lb.pools.addvip', function (loadParams, moduleParams) {

    var panel = Ext.create('Ext.form.Panel', {
        width: 500,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Load balancers &raquo; Pools &raquo; Add VIP',
        scalrOptions: {
            modal: true
        },

        isCookieFieldNeed: function(sessionPersistenceValue) {
            if (!sessionPersistenceValue) {
                var typeField = panel.down('#sessionPersistence');
                sessionPersistenceValue = typeField.getValue();
            }
            return sessionPersistenceValue === 'APP_COOKIE';
        },

        hideFields: function(sessionPersistenceValue) {
            var visible = panel.isCookieFieldNeed(sessionPersistenceValue),
                cookieNameField = panel.down('#cookieName');

            cookieNameField.setVisible(visible);
            panel.updateLayout();
        },

        items: [{
            xtype: 'fieldset',
            title: 'Specify VIP',
            defaults: {
                labelWidth: 150,
                anchor: '100%'
            },
            items: [{
                fieldLabel:'Name',
                xtype: 'textfield',
                name: 'name'
            }, {
                fieldLabel:'Description',
                xtype: 'textfield',
                emptyText: 'Additional information here...',
                name: 'description'
            }, {
                fieldLabel: 'IP address',
                xtype: 'textfield',
                emptyText: 'Specify a free IP address from ' + moduleParams['subnet']['cidr'],
                name: 'address'
            }, {
                fieldLabel:'Protocol port',
                xtype: 'textfield',
                allowBlank: false,
                validator: function (value) {
                    return value >= 0 && value <= 65535 ? true : 'Protocol port should have a value from 0 to 65535';
                },
                name: 'protocol_port'
            }, {
                fieldLabel:'Protocol',
                xtype: 'textfield',
                readOnly: true,
                value: moduleParams['protocol'],
                name: 'protocol'
            }, {
                fieldLabel:'Session persistence',
                itemId: 'sessionPersistence',
                xtype: 'combo',
                editable: false,
                store: ['APP_COOKIE', 'HTTP_COOKIE', 'SOURCE_IP'],
                emptyText: 'Set session persistence',
                name: 'session_persistence',
                listeners: {
                    change: function(combo, value) {
                        panel.hideFields(value);
                    }
                }
            }, {
                fieldLabel:'Cookie name',
                itemId: 'cookieName',
                xtype: 'textfield',
                name: 'cookie_name',
                hidden: true
            }, {
                fieldLabel:'Connection limit',
                xtype: 'textfield',
                name: 'connection_limit'
            }, {
                fieldLabel:'Admin state',
                xtype: 'checkboxfield',
                inputValue: true,
                uncheckedValue: false,
                value: true,
                name: 'admin_state_up'
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'Create',
                handler: function() {
                    var form = panel.getForm();
                    if (form.isValid()) {
                        loadParams['subnet_id'] = moduleParams['subnet']['id'];
                        loadParams['pool_id'] = loadParams['poolId'];
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: form,
                            scope: this,
                            params: loadParams,
                            url: '/tools/openstack/lb/pools/xAddVip',
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]
    });

    return panel;
});
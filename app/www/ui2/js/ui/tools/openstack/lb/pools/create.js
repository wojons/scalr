Scalr.regPage('Scalr.ui.tools.openstack.lb.pools.create', function (loadParams, moduleParams) {

    var panel = Ext.create('Ext.form.Panel', {
        width: 500,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Load balancers &raquo; Pools &raquo; ' + (moduleParams['pool'] ? 'Edit pool' : 'Create pool'),
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },

        items: [{
            xtype: 'fieldset',
            title: 'Pool details',
            defaults: {
                labelWidth: 150
            },
            items: [{
                fieldLabel:'Name',
                xtype: 'textfield',
                allowBlank: false,
                name: 'name'
            }, {
                fieldLabel:'Description',
                xtype: 'textfield',
                emptyText: 'Additional information here...',
                name: 'description'
            }, {
                fieldLabel: 'Subnet',
                xtype: 'combo',
                editable: false,
                store: {
                    fields: [ 'cidr', 'id' ],
                    data: moduleParams['subnets'],
                    proxy: 'object'
                },
                displayField: 'cidr',
                valueField: 'id',
                emptyText: 'Select a subnet',
                allowBlank: false,
                readOnly: !!moduleParams['pool'],
                name: 'subnet_id'
            }, {
                fieldLabel:'Protocol',
                xtype: 'combo',
                editable: false,
                store: ['HTTP', 'HTTPS'],
                emptyText: 'Select a protocol',
                allowBlank: false,
                readOnly: !!moduleParams['pool'],
                name: 'protocol'
            }, {
                fieldLabel:'Load balancing method',
                xtype: 'combo',
                editable: false,
                store: ['ROUND_ROBIN', 'LEAST_CONNECTIONS', 'SOURCE_IP'],
                emptyText: 'Select a protocol',
                allowBlank: false,
                name: 'lb_method'
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
                text: 'Save',
                handler: function() {
                    var form = panel.getForm();
                    if (form.isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: form,
                            scope: this,
                            params: loadParams,
                            url: '/tools/openstack/lb/pools/xSave',
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

    panel.getForm().setValues(moduleParams['pool']);
    return panel;
});

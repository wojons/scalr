Scalr.regPage('Scalr.ui.tools.openstack.lb.monitors.info', function (loadParams, moduleParams) {

    var panel = Ext.create('Ext.form.Panel', {
        width: 460,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Load balancers &raquo; Monitors &raquo; Monitor details',
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },
        tools: [{
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent( 'close' );
            }
        }],

        items: [
            {
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                title: 'Monitor details',
                defaults: {
                    labelWidth: 155,
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
                    fieldLabel: 'Type',
                    xtype: 'displayfield',
                    name: 'type'
                }, {
                    fieldLabel: 'Delay',
                    xtype: 'displayfield',
                    name: 'delay'
                }, {
                    fieldLabel: 'Timeout',
                    xtype: 'displayfield',
                    name: 'timeout'
                }, {
                    fieldLabel: 'Max retries',
                    xtype: 'displayfield',
                    name: 'max_retries'
                }, {
                    fieldLabel: 'HTTP method',
                    xtype: 'displayfield',
                    name: 'http_method'
                }, {
                    fieldLabel: 'URL path',
                    xtype: 'displayfield',
                    name: 'url_path'
                }, {
                    fieldLabel: 'Expected codes',
                    xtype: 'displayfield',
                    name: 'expected_codes'
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

    panel.getForm().setValues(moduleParams['monitor']);
    return panel;
});
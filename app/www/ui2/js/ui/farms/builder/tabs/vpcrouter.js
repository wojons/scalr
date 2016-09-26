Ext.define('Scalr.ui.FarmRoleEditorTab.Vpcrouter', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'VPC router settings',
    itemId: 'vpcrouter',
    layout: 'anchor',

    cls: 'x-panel-column-left-with-tabs',

    isEnabled: function (record) {
        return this.callParent(arguments) && record.get('platform') === 'ec2';
    },

    beforeShowTab: function (record, handler) {
        handler();
    },

    showTab: function (record) {
        var settings = record.get('settings', true);
        this.setFieldValues({
            'router.vpc.networkInterfaceId': settings['router.vpc.networkInterfaceId'] || '-',
            'router.vpc.ip': settings['router.vpc.ip'] || '-',
            'router.vpc.ipAllocationId': settings['router.vpc.ipAllocationId'] || '-'
        });
    },

    hideTab: function (record) {
        //var settings = record.get('settings');
        //record.set('settings', settings);
    },

    __items: [{
        xtype: 'fieldset',
        cls: 'x-fieldset-separator-none',
        defaults: {
            labelWidth: 210,
            maxWidth: 500
        },
        items: [{
            xtype: 'displayfield',
            name: 'router.vpc.networkInterfaceId',
            fieldLabel: 'Elastic Network Interface ID',
            value: ''
        }, {
            xtype: 'displayfield',
            name: 'router.vpc.ip',
            fieldLabel: 'Proxy IP address',
            value: ''
        }, {
            xtype: 'displayfield',
            name: 'router.vpc.ipAllocationId',
            fieldLabel: 'IP Allocation ID',
            value: ''
        }]
    }]
});

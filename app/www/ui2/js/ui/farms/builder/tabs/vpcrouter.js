Scalr.regPage('Scalr.ui.farms.builder.tabs.vpcrouter', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'VPC router settings',
        itemId: 'vpcrouter',
        layout: 'anchor',
        
		isEnabled: function (record) {
			return record.get('platform') === 'ec2';
		},

		beforeShowTab: function (record, handler) {
            handler();
		},

		showTab: function (record) {
			var settings = record.get('settings', true);
			
			this.down('[name="router.vpc.networkInterfaceId"]').setValue(settings['router.vpc.networkInterfaceId'] || '-');
			this.down('[name="router.vpc.ip"]').setValue(settings['router.vpc.ip'] || '-');
			this.down('[name="router.vpc.ipAllocationId"]').setValue(settings['router.vpc.ipAllocationId'] || '-');
		},

		hideTab: function (record) {
			//var settings = record.get('settings');

			//record.set('settings', settings);
		},

		items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            defaults: {
                labelWidth: 180,
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
});

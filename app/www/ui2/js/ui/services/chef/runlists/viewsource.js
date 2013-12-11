Scalr.regPage('Scalr.ui.services.chef.runlists.viewsource', function (loadParams, moduleParams) {
	return Ext.create('Ext.panel.Panel', {
		title: 'Chef &raquo; RunList &raquo; Source',
		width: 400,
		height: 300,
		scalrOptions: {
			'modal': true
		},
        layout: 'fit',
        bodyPadding: 12,
		items: [{
			xtype: 'textareafield',
			value: moduleParams['runlist'],
			height: '98%',
			readOnly: true
		}],
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}]
	})
});
	
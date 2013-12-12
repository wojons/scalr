Scalr.regPage('Scalr.ui.bundletasks.failuredetails', function (loadParams, moduleParams) {
	return Ext.create('Ext.panel.Panel', {
		title: 'Bundle task information',
		scalrOptions: {
			'modal': true
		},
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}],
		items: [{
			xtype: 'fieldset',
			title: 'General information',
			labelWidth: 130,
            cls: 'x-fieldset-separator-none',
			items: [{
				xtype: 'displayfield',
				name: 'email',
				fieldLabel: 'Failure reason',
				readOnly: true,
				value: '<span style="color:red;">' + moduleParams['failureReason'] + '</span>'
			}]
		}],
		width: 800
	});
});

Scalr.regPage('Scalr.ui.servers.consoleoutput', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		scalrOptions: {
			'maximize': 'all',
			'reload': true
		},
		title: 'Server "' + moduleParams['name'] + '" console output',
		html: moduleParams['content'],
        bodyCls: 'x-container-fieldset',
		autoScroll: true,
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}]
	});
});

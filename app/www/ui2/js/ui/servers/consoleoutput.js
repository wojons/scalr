Scalr.regPage('Scalr.ui.servers.consoleoutput', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		scalrOptions: {
			maximize: 'all',
			reload: true,
            menuTitle: 'Servers',
            menuHref: '#/servers',
            parentStateId: 'grid-servers-view'
		},
        autoScroll: true,
        items: [{
            xtype: 'fieldset',
            title: 'Server "' + moduleParams['name'] + '" console output',
            html: moduleParams['content']
        }]
	});
});

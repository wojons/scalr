Scalr.regPage('Scalr.ui.servers.consoleoutput', function (loadParams, moduleParams) {
    return Ext.create('Ext.panel.Panel', {
        title: 'Server "' + moduleParams['name'] + '" console output',
        preserveScrollPosition: true,
        scalrOptions: {
            modal: true
        },
        layout: 'fit',
        bodyCls: 'x-container-fieldset',
        width: 1140,
        tools: [{
            type: 'refresh',
            handler: function () {
                Scalr.event.fireEvent('refresh');
            }
        }, {
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent('close');
            }
        }],
        items: [{
            xtype: 'component',
            style: 'word-wrap: break-word',
            html: moduleParams['content']
        }]
    });
});

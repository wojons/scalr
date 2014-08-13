Scalr.regPage('Scalr.ui.operations.details', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		title: moduleParams['name'] + ' progress',
		scalrOptions: {
			'modal': true
		},
		width: 800,
		items:[{
			xtype: 'fieldset',
			title: 'General information',
			labelWidth: 130,
			items: [{
				xtype: 'displayfield',
				fieldLabel: 'Server ID',
				value: (moduleParams['serverId']) ? moduleParams['serverId'] : "*Server was terminated*" 
			}, {
				xtype: 'displayfield',
				fieldLabel: 'Operation status',
				value: moduleParams['status'] == 'ok' ? "Completed" : Ext.String.capitalize(moduleParams['status'])
			}, {
				xtype: 'displayfield',
				fieldLabel: 'Date',
				value: moduleParams['date']
			}, {
				xtype: 'displayfield',
				fieldLabel: 'Error',
				hidden: !(moduleParams['message']),
				value: moduleParams['message'],
                fieldStyle: 'max-width:620px;word-wrap:break-word;'
			}, {
                xtype: 'container',
                layout: 'hbox',
                defaults: {
                    width: 190,
                    height: 32
                },
                margin: '12 0 0 100',
                items: [{
	                xtype: 'button',
	                text: 'Download debug log',
	                hidden: !(moduleParams['message'] && moduleParams['serverStatus'] != 'Pending launch'),
	                handler: function() {
	                	Scalr.utils.UserLoadFile('/servers/downloadScalarizrDebugLog?serverId=' + moduleParams['serverId']);
	                }
                }]
			}]
		}, {
			xtype: 'fieldset',
			title: 'Details',
            cls: 'x-fieldset-separator-none',
			html: moduleParams['content']
		}],
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
		}]
	});
});

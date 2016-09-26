Scalr.regPage('Scalr.ui.core.about', function (loadParams, moduleParams) {
	var params = moduleParams;

	return Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'About Scalr',
        scalrOptions: {
            modal: true
        },
		fieldDefaults: {
			labelWidth: 130,
            anchor: '100%'
		},
        layout: 'auto',
		items: [{
			xtype: 'fieldset',
			items: [{
                xtype: 'displayfield',
                fieldLabel: 'Installation ID',
                hidden: (!params['id']),
                value: (params['id']) ? params['id'] : ' - '
            },{
                xtype: 'displayfield',
                fieldLabel: 'Version',
                value: (params['edition']) ? params['version'] + " (" + params['edition'] + ")" : params['version']
            },{
                xtype: 'displayfield',
                fieldLabel: 'Revision',
                hidden: (!params['gitRevision']),
                value: (params['gitRevision']) ? params['gitRevision'] + " (" + params['gitDate'] + ")" : " - "
            },{
                xtype: 'displayfield',
                fieldLabel: 'Full Revision Hash',
                hidden: (!params['gitFullHash']),
                value: (params['gitFullHash']) ? params['gitFullHash'] : " - "
            },{
                xtype: 'displayfield',
                fieldLabel: 'Current branch',
                hidden: !params['branch'],
                value: params['branch']
            }]
		}],
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}]
	});
});

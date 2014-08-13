Scalr.regPage('Scalr.ui.account2.environments.clouds.gce', function (loadParams, moduleParams) {
	var params = moduleParams['params'];
	
	return Ext.create('Ext.form.Panel', {
        bodyCls: 'x-container-fieldset',
        autoScroll: true,
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 190
		},

		items: [{
            xtype: 'component',
            cls: 'x-fieldset-subheader',
            html: 'OAuth Service Account'
        },{
			xtype: 'hidden',
			name: 'gce.is_enabled',
			value: 'on'
		}, {
			xtype: 'textfield',
			fieldLabel: 'Client ID',
			width: 320,
			name: 'gce.client_id',
			value: params['gce.client_id']
		}, {
			xtype: 'textfield',
			fieldLabel: 'Email (Service account name)',
			width: 320,
			name: 'gce.service_account_name',
			value: params['gce.service_account_name']
		}, {
			xtype: 'textfield',
			fieldLabel: 'Project ID',
			width: 320,
			name: 'gce.project_id',
			value: params['gce.project_id']
		}, {
			xtype: 'filefield',
			fieldLabel: 'Private key',
			name: 'gce.key',
			value: params['gce.key']
		}]
	});
});

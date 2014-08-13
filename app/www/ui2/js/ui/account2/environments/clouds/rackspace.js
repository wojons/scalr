Scalr.regPage('Scalr.ui.account2.environments.clouds.rackspace', function (loadParams, moduleParams) {
	var params = moduleParams['params'];
	
	var form = Ext.create('Ext.form.Panel', {
        //bodyCls: 'x-container-fieldset',
        autoScroll: true,
		fieldDefaults: {
			anchor: '100%'
		},
        onSavePlatform: function(disablePlatform) {
            var frm = this.getForm();
            if (disablePlatform) {
                frm.findField('rackspace.is_enabled.rs-ORD1').setValue(null);
                frm.findField('rackspace.is_enabled.rs-LONx').setValue(null);
            } else {
                var locations = ['rs-ORD1', 'rs-LONx'];
                for (var i=0, len=locations.length; i<len; i++) {
                    var locationDisabled = Ext.isEmpty(Ext.String.trim(frm.findField('rackspace.username.'+locations[i]).getValue()))
                        && Ext.isEmpty(Ext.String.trim(frm.findField('rackspace.api_key.'+locations[i]).getValue()));
                    frm.findField('rackspace.is_enabled.'+locations[i]).setValue(locationDisabled?null:'on');
                    frm.findField('rackspace.username.'+locations[i]).allowBlank = locationDisabled?true:false;
                    frm.findField('rackspace.api_key.'+locations[i]).allowBlank = locationDisabled?true:false;
                }
            }
        },
		items: [{
			xtype: 'fieldset',
			title: 'Rackspace US cloud location',
			items: [{
				xtype: 'hidden',
				name: 'rackspace.is_enabled.rs-ORD1',
				value: params['rs-ORD1']
			}, {
				xtype: 'textfield',
				fieldLabel: 'Username',
				name: 'rackspace.username.rs-ORD1',
				value: (params['rs-ORD1']) ? params['rs-ORD1']['rackspace.username'] : ''
			}, {
				xtype: 'textfield',
				fieldLabel: 'API Key',
				name: 'rackspace.api_key.rs-ORD1',
				value: (params['rs-ORD1']) ? params['rs-ORD1']['rackspace.api_key'] : ''
			}, {
				xtype: 'checkbox',
				name: 'rackspace.is_managed.rs-ORD1',
				checked: (params['rs-ORD1'] && params['rs-ORD1']['rackspace.is_managed'] == 1) ? true : false,
				hideLabel: true,
				boxLabel: 'Check this checkbox if your account is managed'
			}]
		}, {
			xtype: 'fieldset',
			title: 'Rackspace UK cloud location',
			items: [{
				xtype: 'hidden',
				name: 'rackspace.is_enabled.rs-LONx',
				value: params['rs-LONx']
			}, {
				xtype: 'textfield',
				fieldLabel: 'Username',
				name: 'rackspace.username.rs-LONx',
				value: (params['rs-LONx']) ? params['rs-LONx']['rackspace.username'] : ''
			}, {
				xtype: 'textfield',
				fieldLabel: 'API Key',
				name: 'rackspace.api_key.rs-LONx',
				value: (params['rs-LONx']) ? params['rs-LONx']['rackspace.api_key'] : ''
			}, {
				xtype: 'checkbox',
				name: 'rackspace.is_managed.rs-LONx',
				checked: (params['rs-LONx'] && params['rs-LONx']['rackspace.is_managed']) ? true : false,
				hideLabel: true,
				boxLabel: 'Check this checkbox if your account is managed'
			}]
		}]
	});

	return form;
});

Scalr.regPage('Scalr.ui.account2.environments.clouds.gce', function (loadParams, moduleParams) {
	var params = moduleParams['params'];
	
	var form = Ext.create('Ext.form.Panel', {
        bodyCls: 'x-container-fieldset',
        fieldDefaults: {
			anchor: '100%',
            labelWidth: 110
        },
        autoScroll: true,
        items: [{
            xtype: 'component',
            cls: 'x-fieldset-subheader',
            html: 'OAuth Service Account'
        },{
			xtype: 'hidden',
			name: 'gce.is_enabled',
			value: 'on'
        },{
            xtype: 'buttongroupfield',
            margin: '0 0 12',
            layout: 'hbox',
            name: 'mode',
            submitValue: false,
            defaults: {
                flex: 1
            },
            items: [{
                text: 'Configure manually',
                value: 'manual'
            },{
                text: 'Upload JSON key',
                value: 'jsonkey'
            }],
            listeners: {
                change: function(comp, value) {
                    var form = comp.up('form'), ct, fields;
                    form.suspendLayouts();
                    Ext.each(['manual', 'jsonkey'], function(v){
                        ct = form.down('#' + v);
                        ct.setVisible(value === v);
                        Ext.each(ct.query('[isFormField]'), function(field){
                            field.setDisabled(value !== v);
                        });
                    });
                    form.resumeLayouts(true);
                }
            }
		},{
			xtype: 'textfield',
			fieldLabel: 'Project ID',
			name: 'gce.project_id',
			value: params['gce.project_id']
        },{
            xtype: 'container',
            layout: 'anchor',
            itemId: 'manual',
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Client ID',
                name: 'gce.client_id',
                value: params['gce.client_id']
            },{
                xtype: 'textfield',
                fieldLabel: 'Email (Service account name)',
                name: 'gce.service_account_name',
                value: params['gce.service_account_name']
            },{
                xtype: 'filefield',
                fieldLabel: 'Private key',
                name: 'gce.key',
                value: params['gce.key'],
                listeners: {
                    //Bug: file button will not be disabled when filefield is hidden initially
                    afterrender: function(){
                        this.setDisabled(this.disabled);
                    }
                }
            }]
        },{
            xtype: 'container',
            layout: 'anchor',
            itemId: 'jsonkey',
            items: [{
                xtype: 'filefield',
                fieldLabel: 'JSON key',
                name: 'gce.json_key',
                value: params['gce.json_key'],
                listeners: {
                    //Bug: file button will not be disabled when filefield is hidden initially
                    afterrender: function(){
                        this.setDisabled(this.disabled);
                    }
                }
            }]
        }]
	});

    form.getForm().findField('mode').setValue(params['gce.json_key'] ? 'jsonkey' : 'manual');
    return form;
});

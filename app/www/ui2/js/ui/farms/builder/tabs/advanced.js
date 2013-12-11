Scalr.regPage('Scalr.ui.farms.builder.tabs.advanced', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Advanced',
        itemId: 'advanced',
        layout: 'anchor',
        
        settings: {
            'base.hostname_format': undefined,
            'base.keep_scripting_logs_time': 3600,
            'system.timeouts.reboot': 360,
            'system.timeouts.launch': 2400,
            'dns.create_records': 0,
            'dns.int_record_alias': function(record) {return 'int-' + record.get('alias')},
            'dns.ext_record_alias': function(record) {return 'ext-' + record.get('alias')}
        },

        isEnabled: function (record) {
			return record.get('platform') != 'rds';
		},

		showTab: function (record) {
			var settings = record.get('settings', true);

            this.down('[name="base.hostname_format"]').setValueWithGovernance(settings['base.hostname_format'], this.up('#farmbuilder').getLimits('general.hostname_format'));

            this.setFieldValues({
                'base.keep_scripting_logs_time': Math.round(settings['base.keep_scripting_logs_time']/3600) || 1,
                
                'system.timeouts.reboot': settings['system.timeouts.reboot'] || 360,
                'system.timeouts.launch': settings['system.timeouts.launch'] || 2400,
                
                'dns.create_records': settings['dns.create_records'] == 1,
                'dns.int_record_alias': settings['dns.int_record_alias'] || ('int-' + record.get('alias')),
                'dns.ext_record_alias': settings['dns.ext_record_alias'] || ('ext-' + record.get('alias'))
            });
		},

		hideTab: function (record) {
			var settings = record.get('settings'),
                hostnameFormatField = this.down('[name="base.hostname_format"]');

            if (!hostnameFormatField.readOnly) {
                settings['base.hostname_format'] = hostnameFormatField.getValue();
            }
            settings['base.keep_scripting_logs_time'] = this.down('[name="base.keep_scripting_logs_time"]').getValue()*3600;
            
			settings['system.timeouts.reboot'] = this.down('[name="system.timeouts.reboot"]').getValue();
			settings['system.timeouts.launch'] = this.down('[name="system.timeouts.launch"]').getValue();

            settings['dns.create_records'] = this.down('[name="dns.create_records"]').getValue() ? 1 : 0;
            if (settings['dns.create_records'] == 1) {
                settings['dns.int_record_alias'] = this.down('[name="dns.int_record_alias"]').getValue() || ('int-' + record.get('alias'));
                settings['dns.ext_record_alias'] = this.down('[name="dns.ext_record_alias"]').getValue() || ('ext-' + record.get('alias'));
            } else {
                delete settings['dns.int_record_alias'];
                delete settings['dns.ext_record_alias'];
            }
            
			record.set('settings', settings);
		},
        defaults: {
            defaults: {
                maxWidth: 800,
                anchor: '100%'
            }
        },
		items: [{
            xtype: 'fieldset',
            title: 'General',
            items: [{
                xtype: 'fieldcontainer',
				labelWidth: 180,
                fieldLabel: 'Server hostname format',
                layout: 'hbox',
                items: [{
                    xtype: 'textfield',
                    name: 'base.hostname_format',
					emptyText: 'Leave blank to use cloud generated hostname',
                    flex: 1,
                    governance: true
                },{
                    xtype: 'displayinfofield',
                    margin: '0 0 0 5',
                    value: Scalr.strings['farmbuilder.hostname_format.info']
                }]
		  }]
        }, {
			xtype: 'fieldset',
            title: 'Scripting',
			items: [{
				xtype: 'container',
				layout: {
                    type: 'hbox',
                    align: 'middle'
                },
				items: [{
					xtype: 'label',
					text: "Rotate scripting logs every"
				}, {
					xtype: 'numberfield',
					name: 'base.keep_scripting_logs_time',
					margin: '0 10',
                    width: 70,
                    minValue: 1,
                    allowDecimals: false,
                    listeners: {
                        blur: function(){
                            if (!this.getValue()) {
                                this.setValue(1);
                            }
                        }
                    }
				}, {
					xtype: 'label',
					text: 'hour(s).'
				}]
            }]
		}, {
			xtype: 'fieldset',
            title: 'Timeouts',
			items: [{
				xtype: 'container',
				layout: {
                    type: 'hbox',
                    align: 'middle'
                },
				items: [{
					xtype: 'label',
					text: "Terminate instance if it will not send 'rebootFinish' event after reboot in"
				}, {
					xtype: 'textfield',
					name: 'system.timeouts.reboot',
					margin: '0 5',
                    width: 70
				}, {
					xtype: 'label',
					text: 'seconds.'
				}]
			}, {
				xtype: 'container',
				layout: {
                    type: 'hbox',
                    align: 'middle'
                },
				items: [{
					xtype: 'label',
					text: "Terminate instance if it will not send 'hostUp' or 'hostInit' event after launch in"
				}, {
					xtype: 'textfield',
					name: 'system.timeouts.launch',
					margin: '0 5',
                    width: 70
				}, {
					xtype: 'label',
					text: 'seconds.'
				}]
			}]
        },{
			xtype: 'fieldset',
			title: 'Create system int-* and ext-* dns records',
			checkboxToggle: true,
            toggleOnTitleClick: true,
            collapsible: true,
			collapsed: true,
			checkboxName: 'dns.create_records',
			items: [{
				xtype: 'displayfield',
				cls: 'x-form-field-warning',
				value: 'Will affect only new records. Old ones WILL REMAIN the same.'
			}, {
                xtype: 'textfield',
                name: 'dns.int_record_alias',
                fieldLabel: 'Format for private IP A-records',
                labelWidth: 200,
                allowBlank: false
			}, {
                xtype: 'textfield',
                name: 'dns.ext_record_alias',
                fieldLabel: 'Format for public IP A-records',
                labelWidth: 200,
                allowBlank: false
			}]
		}]
	});
});

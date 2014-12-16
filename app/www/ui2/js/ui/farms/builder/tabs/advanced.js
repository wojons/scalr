Scalr.regPage('Scalr.ui.farms.builder.tabs.advanced', function (moduleTabParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'Advanced',
        itemId: 'advanced',
        layout: 'anchor',
        
        settings: {
            'base.hostname_format': undefined,
            'base.api_port': 8010,
            'base.messaging_port': 8013,
            'base.keep_scripting_logs_time': 3600,
            'system.timeouts.reboot': 360,
            'system.timeouts.launch': 2400,
            'dns.create_records': 0,
            'dns.int_record_alias': function(record) {return 'int-' + record.get('alias')},
            'dns.ext_record_alias': function(record) {return 'ext-' + record.get('alias')},
            'base.upd.repository': '',
            'base.upd.schedule': function(record) {return moduleTabParams['farm']['updSchedule'] || '* * *'},
            'base.abort_init_on_script_fail': 0,
            'base.disable_firewall_management': 0
        },

        isEnabled: function (record) {
			return record.get('platform') != 'rds';
		},

		showTab: function (record) {
			var settings = record.get('settings', true),
                fieldLimit = this.up('#farmbuilder').getLimits('general', 'general.hostname_format'),
                schedule = (settings['base.upd.schedule'] || moduleTabParams['farm']['updSchedule'] || '').split(' ');

            this.down('[name="base.hostname_format"]').setValueWithGovernance(settings['base.hostname_format'], fieldLimit !== undefined ? fieldLimit.value : undefined);
            
            this.setFieldValues({
                'base.keep_scripting_logs_time': Math.round(settings['base.keep_scripting_logs_time']/3600) || 1,
                
                'system.timeouts.reboot': settings['system.timeouts.reboot'] || 360,
                'system.timeouts.launch': settings['system.timeouts.launch'] || 2400,
                'base.api_port': settings['base.api_port'] || 8010,
                'base.messaging_port': settings['base.messaging_port'] || 8013,
                
                'dns.create_records': settings['dns.create_records'] == 1,
                'dns.int_record_alias': settings['dns.int_record_alias'] || ('int-' + record.get('alias')),
                'dns.ext_record_alias': settings['dns.ext_record_alias'] || ('ext-' + record.get('alias')),

                'base.upd.repository': settings['base.upd.repository'] || '',
                'base.upd.schedule.h': schedule.length === 3 ? schedule[0] : '*',
                'base.upd.schedule.d': schedule.length === 3 ? schedule[1] : '*',
                'base.upd.schedule.w': schedule.length === 3 ? schedule[2] : '*',
                'base.abort_init_on_script_fail': settings['base.abort_init_on_script_fail'] == 1,
                'base.disable_firewall_management': settings['base.disable_firewall_management'] == 1
            });
		},

		hideTab: function (record) {
			var settings = record.get('settings'),
                hostnameFormatField = this.down('[name="base.hostname_format"]');

            if (!hostnameFormatField.readOnly) {
                settings['base.hostname_format'] = hostnameFormatField.getValue();
            }
            settings['base.keep_scripting_logs_time'] = this.down('[name="base.keep_scripting_logs_time"]').getValue()*3600;
   			settings['base.abort_init_on_script_fail'] = this.down('[name="base.abort_init_on_script_fail"]').getValue() ? 1 : 0;
            settings['base.disable_firewall_management'] = this.down('[name="base.disable_firewall_management"]').getValue() ? 1 : 0;

			settings['system.timeouts.reboot'] = this.down('[name="system.timeouts.reboot"]').getValue();
			settings['system.timeouts.launch'] = this.down('[name="system.timeouts.launch"]').getValue();

			settings['base.api_port'] = this.down('[name="base.api_port"]').getValue();
			settings['base.messaging_port'] = this.down('[name="base.messaging_port"]').getValue();
			
            settings['dns.create_records'] = this.down('[name="dns.create_records"]').getValue() ? 1 : 0;
            if (settings['dns.create_records'] == 1) {
                settings['dns.int_record_alias'] = this.down('[name="dns.int_record_alias"]').getValue() || ('int-' + record.get('alias'));
                settings['dns.ext_record_alias'] = this.down('[name="dns.ext_record_alias"]').getValue() || ('ext-' + record.get('alias'));
            } else {
                delete settings['dns.int_record_alias'];
                delete settings['dns.ext_record_alias'];
            }

            settings['base.upd.repository'] = this.down('[name="base.upd.repository"]').getValue();
            settings['base.upd.schedule'] = ([
                this.down('[name="base.upd.schedule.h"]').getValue() || '*', 
                this.down('[name="base.upd.schedule.d"]').getValue() || '*',
                this.down('[name="base.upd.schedule.w"]').getValue() || '*'
            ]).join(' ');
            
			record.set('settings', settings);
		},
        defaults: {
            defaults: {
                maxWidth: 680,
                anchor: '100%'
            }
        },
		items: [{
            xtype: 'fieldset',
            title: 'General',
            items: [{
                xtype: 'textfield',
                name: 'base.hostname_format',
                fieldLabel: 'Server hostname format',
                labelWidth: 190,
                emptyText: 'Leave blank to use cloud generated hostname',
                flex: 1,
                icons: {
                    governance: true,
                    globalvars: true
                },
                iconsPosition: 'outer',
                governanceTitle: 'format of server hostnames'
            },{
                xtype: 'textfield',
                labelWidth: 190,
                maxWidth: 275,
                fieldLabel: 'Scalarizr API port',
                name: 'base.api_port'
            },{
                xtype: 'textfield',
                labelWidth: 190,
                maxWidth: 275,
                fieldLabel: 'Scalarizr control port',
                name: 'base.messaging_port'
            },{
                xtype: 'checkbox',
                name: 'base.disable_firewall_management',
                boxLabel: 'Disable automated management of iptables',
                icons: {
                    warning: {
                        tooltip: Ext.String.htmlEncode('Feature only available in Scalarizr starting from 2.11.27 <br/>If you disable automated management of local firewall rules, you\'ll need to ensure that your local firewall is properly configured to allow Scalr traffic. <a href="https://scalr-wiki.atlassian.net/wiki/x/CYA0" target="_blank">View the required configuration here</a>.')
                    }
                }
            }]
        },{
			xtype: 'fieldset',
            title: 'Scripting',
			items: [{
				xtype: 'container',
				layout: {
                    type: 'hbox',
                    align: 'middle'
                },
				items: [{
					xtype: 'numberfield',
					name: 'base.keep_scripting_logs_time',
					fieldLabel: 'Rotate scripting logs every',
                    labelWidth: 190,
					margin: '0 10 0 0',
                    width: 275,
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
            },{
                xtype: 'checkbox',
                name: 'base.abort_init_on_script_fail',
                boxLabel: 'Abort Server initialization when a Blocking BeforeHostUp Script fails (non-zero exit code)',
                icons: {
                    szrversion: {tooltipData: {version: '2.11.15'}}
                }
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
            hidden: !moduleTabParams['scalr.dns.global.enabled'],
			items: [{
				xtype: 'displayfield',
				cls: 'x-form-field-warning',
				value: 'Will affect only new records. Old ones WILL REMAIN the same.'
			}, {
                xtype: 'textfield',
                name: 'dns.int_record_alias',
                fieldLabel: 'Private IP A-records format',
                icons: {
                    globalvars: true
                },
                iconsPosition: 'outer',
                labelWidth: 190,
                allowBlank: false
			}, {
                xtype: 'textfield',
                name: 'dns.ext_record_alias',
                fieldLabel: 'Public IP A-records format',
                icons: {
                    globalvars: true
                },
                iconsPosition: 'outer',
                labelWidth: 190,
                allowBlank: false
			}]
		},{
			xtype: 'fieldset',
            title: 'Override Scalarizr Agent update settings',
            defaults: {
                labelWidth: 140
            },
			items: [{
                xtype: 'combo',
                editable: false,
                name: 'base.upd.repository',
                fieldLabel: 'Repository',
                queryMode: 'local',
                maxWidth: 262,
                store: Ext.Array.merge([['', 'Use farm settings']],Ext.Array.map(moduleTabParams['scalr.scalarizr_update.repos']||[], function(item){return [item, item]})),
                emptyText: 'Use farm settings'
            },{
                xtype: 'fieldcontainer',
                fieldLabel: 'Schedule (UTC time)',
                layout: 'hbox',
                scheduleValidator: Ext.Function.createBuffered(function() {
                    var dd = this.down('[name="base.upd.schedule.d"]'),
                        dw = this.down('[name="base.upd.schedule.w"]');
                    if (dd.getValue() !== '*' && dw.getValue() !== '*') {
                        dw.markInvalid('"Day of month" and "Day of week" cannot be set at the same time');
                    } else {
                        dw.clearInvalid();
                        if (!(/^(\*|([12]{0,1}\d|3[01])(-([12]{0,1}\d|3[01])){0,1}(,([12]{0,1}\d|3[01])(-([12]{0,1}\d|3[01])){0,1})*)(\/([12]{0,1}\d|3[01])){0,1}$/).test(dd.getValue())) {
                            dd.markInvalid('Invalid format');
                        }
                        if (!(/^(\*|[0-6](-([0-6])){0,1}(,([0-6])(-([0-6])){0,1})*)(\/([0-6])){0,1}$/).test(dw.getValue())) {
                            dw.markInvalid('Invalid format');
                        }
                    }

                }, 200),
                items: [{
                    xtype: 'textfield',
                    hideLabel: true,
                    width: 50,
                    margin: '0 3 0 0',
                    name: 'base.upd.schedule.h',
                    validator: function(value) {
                        return (/^(\*|(1{0,1}\d|2[0-3])(-(1{0,1}\d|2[0-3])){0,1}(,(1{0,1}\d|2[0-3])(-(1{0,1}\d|2[0-3])){0,1})*)(\/(1{0,1}\d|2[0-3])){0,1}$/).test(value) || 'Invalid format';
                    }
                },{
                    xtype: 'textfield',
                    hideLabel: true,
                    width: 50,
                    margin: '0 3 0 0',
                    name: 'base.upd.schedule.d',
                    listeners: {
                        change: function() {
                            this.up().scheduleValidator();
                        }
                    }
                },{
                    xtype: 'textfield',
                    hideLabel: true,
                    width: 50,
                    name: 'base.upd.schedule.w',
                    margin: '0 3 0 0',
                    listeners: {
                        change: function() {
                            this.up().scheduleValidator();
                        }
                    }
                },{
                    xtype: 'displayinfofield',
                    info:
                    '*&nbsp;&nbsp;&nbsp;*&nbsp;&nbsp;&nbsp;*<br>' +
                    '─&nbsp;&nbsp;&nbsp;─&nbsp;&nbsp;&nbsp;─<br>' +
                    '│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>' +
                    '│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>' +
                    '│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;└───── day of week (0 - 6) (0 is Sunday)<br>' +
                    '│&nbsp;&nbsp;&nbsp;└─────── day of month (1 - 31)<br>' +
                    '└───────── hour (0 - 23)<br>'
                }]
            }]
        }]
	});
});

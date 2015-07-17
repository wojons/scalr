Ext.define('Scalr.ui.FarmRoleEditorTab.Advanced', {
    extend: 'Scalr.ui.FarmRoleEditorTab',

    tabTitle: 'Advanced',
    itemId: 'advanced',
    layout: 'anchor',

    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'base.hostname_format': undefined,
        'base.api_port': 8010,
        'base.messaging_port': 8013,
        'base.keep_scripting_logs_time': 3600,
        'system.timeouts.reboot': 360,
        'system.timeouts.launch': 2400,
        'dns.create_records': 0,
        'dns.int_record_alias': function(record, moduleParams) {return 'int-' + record.get('alias')},
        'dns.ext_record_alias': function(record, moduleParams) {return 'ext-' + record.get('alias')},
        'base.upd.repository': '',
        'base.upd.schedule': '',
        'base.abort_init_on_script_fail': 0,
        'base.reboot_after_hostinit_phase': 0,
        'base.disable_firewall_management': 0
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && record.get('platform') != 'rds';
    },

    showTab: function (record) {
        var settings = record.get('settings', true),
            farmDesigner = this.up('#farmDesigner'),
            fieldLimit = Scalr.getGovernance('general', 'general.hostname_format'),
            moduleParams = farmDesigner.moduleParams,
            field,
            farmSchedule = farmDesigner.getSzrUpdateSettings()['szr.upd.schedule'].split(' '),
            schedule = (settings['base.upd.schedule'] || '').split(' ');

        this.down('[name="base.hostname_format"]').setValueWithGovernance(settings['base.hostname_format'], fieldLimit !== undefined ? fieldLimit.value : undefined);

        this.down('[name="base.upd.repository"]').store.loadData(Ext.Array.merge([['', 'Use farm settings']],Ext.Array.map(moduleParams.tabParams['scalr.scalarizr_update.repos']||[], function(item){return [item, item]}))),
        this.down('#systemDnsRecords').setVisible(!!moduleParams.tabParams['scalr.dns.global.enabled']);

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
            'base.upd.schedule.h': schedule.length === 3 ? schedule[0] : '',
            'base.upd.schedule.d': schedule.length === 3 ? schedule[1] : '',
            'base.upd.schedule.w': schedule.length === 3 ? schedule[2] : '',
            'base.abort_init_on_script_fail': settings['base.abort_init_on_script_fail'] == 1,
            'base.reboot_after_hostinit_phase' : settings['base.reboot_after_hostinit_phase'] == 1,
            'base.disable_firewall_management': settings['base.disable_firewall_management'] == 1
        });

        field = this.down('[name="base.upd.schedule.h"]');
        field.emptyText = farmSchedule[0];
        field.applyEmptyText();
        field = this.down('[name="base.upd.schedule.d"]');
        field.emptyText = farmSchedule[1];
        field.applyEmptyText();
        field = this.down('[name="base.upd.schedule.w"]');
        field.emptyText = farmSchedule[2];
        field.applyEmptyText();

    },

    hideTab: function (record) {
        var settings = record.get('settings'),
            farmDesigner = this.up('#farmDesigner'),
            farmSchedule = farmDesigner.getSzrUpdateSettings()['szr.upd.schedule'].split(' '),
            hostnameFormatField = this.down('[name="base.hostname_format"]');

        if (!hostnameFormatField.readOnly) {
            settings['base.hostname_format'] = hostnameFormatField.getValue();
        }
        settings['base.keep_scripting_logs_time'] = this.down('[name="base.keep_scripting_logs_time"]').getValue()*3600;
        settings['base.abort_init_on_script_fail'] = this.down('[name="base.abort_init_on_script_fail"]').getValue() ? 1 : 0;
        settings['base.reboot_after_hostinit_phase'] = this.down('[name="base.reboot_after_hostinit_phase"]').getValue() ? 1 : 0;
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
        var h = this.down('[name="base.upd.schedule.h"]').getValue(),
            d = this.down('[name="base.upd.schedule.d"]').getValue(),
            w = this.down('[name="base.upd.schedule.w"]').getValue();
        if (h || d || w) {
            settings['base.upd.schedule'] = ([
                h || farmSchedule[0],
                d || farmSchedule[1],
                w || farmSchedule[2]

            ]).join(' ');
        } else {
            delete settings['base.upd.schedule'];
        }

        record.set('settings', settings);
    },
    defaults: {
        defaults: {
            maxWidth: 680,
            anchor: '100%',
            labelWidth: 220
        }
    },
    __items: [{
        xtype: 'fieldset',
        title: 'General',
        items: [{
            xtype: 'textfield',
            name: 'base.hostname_format',
            fieldLabel: 'Server hostname format',
            emptyText: 'Leave blank to use cloud generated hostname',
            flex: 1,
            plugins: {
                ptype: 'fieldicons',
                position: 'outer',
                icons: [
                    {id: 'governance', tooltipData: {fieldLabel: 'format of server hostnames'}},
                    'globalvars'
                ]
            }
        },{
            xtype: 'textfield',
            maxWidth: 300,
            fieldLabel: 'Scalarizr API port',
            name: 'base.api_port'
        },{
            xtype: 'textfield',
            maxWidth: 300,
            fieldLabel: 'Scalarizr control port',
            name: 'base.messaging_port'
        },{
            xtype: 'checkbox',
            name: 'base.disable_firewall_management',
            boxLabel: 'Disable automated management of iptables'
        }]
    },{
        xtype: 'fieldset',
        title: 'Scripting',
        items: [{
            xtype: 'fieldcontainer',
            fieldLabel: 'Rotate scripting logs every',
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            items: [{
                xtype: 'numberfield',
                name: 'base.keep_scripting_logs_time',
                margin: '0 10 0 0',
                width: 75,
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
            boxLabel: 'Abort Server initialization when a Blocking BeforeHostUp Script fails (non-zero exit code)'
        },{
            xtype: 'checkbox',
            name: 'base.reboot_after_hostinit_phase',
            boxLabel: 'Reboot after HostInit Scripts have executed',
            plugins: {
                ptype: 'fieldicons',
                icons: [
                    {id: 'szrversion', tooltipData: {version: '3.5.12'}}
                ]
            }
        }]
    }, {
        xtype: 'fieldset',
        title: 'Timeouts',
        items: [{
            xtype: 'fieldcontainer',
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
            xtype: 'fieldcontainer',
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
        itemId: 'systemDnsRecords',
        title: 'Create system int-* and ext-* dns records',
        checkboxToggle: true,
        toggleOnTitleClick: true,
        collapsible: true,
        collapsed: true,
        checkboxName: 'dns.create_records',
        hidden: true,
        items: [{
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            value: 'Will affect only new records. Old ones WILL REMAIN the same.'
        }, {
            xtype: 'textfield',
            name: 'dns.int_record_alias',
            fieldLabel: 'Private IP A-records format',
            plugins: {
                ptype: 'fieldicons',
                position: 'outer',
                icons: ['globalvars']
            },
            allowBlank: false
        }, {
            xtype: 'textfield',
            name: 'dns.ext_record_alias',
            fieldLabel: 'Public IP A-records format',
            plugins: {
                ptype: 'fieldicons',
                position: 'outer',
                icons: ['globalvars']
            },
            allowBlank: false
        }]
    },{
        xtype: 'fieldset',
        title: 'Override Scalarizr Agent update settings',
        defaults: {
            labelWidth: 160
        },
        items: [{
            xtype: 'combo',
            editable: false,
            name: 'base.upd.repository',
            fieldLabel: 'Repository',
            queryMode: 'local',
            width: 351,
            emptyText: 'Use farm settings',
            valueField: 'value',
            displayField: 'text',
            store: Ext.create('Ext.data.ArrayStore', {fields: ['value', 'text']})
        },{
            xtype: 'fieldcontainer',
            fieldLabel: 'Schedule (UTC time)',
            layout: 'hbox',
            scheduleValidator: Ext.Function.createBuffered(function() {
                var dd = this.down('[name="base.upd.schedule.d"]'),
                    dw = this.down('[name="base.upd.schedule.w"]'),
                    ddValue = dd.getValue(), dwValue = dw.getValue();
                if (ddValue && dwValue && ddValue !== '*' && dwValue !== '*') {
                    dw.markInvalid('"Day of month" and "Day of week" cannot be set at the same time');
                } else {
                    dw.clearInvalid();
                    if (ddValue && !(/^(\*|([12]{0,1}\d|3[01])(-([12]{0,1}\d|3[01])){0,1}(,([12]{0,1}\d|3[01])(-([12]{0,1}\d|3[01])){0,1})*)(\/([12]{0,1}\d|3[01])){0,1}$/).test(dd.getValue())) {
                        dd.markInvalid('Invalid format');
                    }
                    if (dwValue && !(/^(\*|[0-6](-([0-6])){0,1}(,([0-6])(-([0-6])){0,1})*)(\/([0-6])){0,1}$/).test(dw.getValue())) {
                        dw.markInvalid('Invalid format');
                    }
                }

            }, 200),
            items: [{
                xtype: 'textfield',
                width: 60,
                margin: '0 3 0 0',
                name: 'base.upd.schedule.h',
                validator: function(value) {
                    return !value || (/^(\*|(1{0,1}\d|2[0-3])(-(1{0,1}\d|2[0-3])){0,1}(,(1{0,1}\d|2[0-3])(-(1{0,1}\d|2[0-3])){0,1})*)(\/(1{0,1}\d|2[0-3])){0,1}$/).test(value) || 'Invalid format';
                }
            },{
                xtype: 'textfield',
                width: 60,
                margin: '0 3 0 0',
                name: 'base.upd.schedule.d',
                listeners: {
                    change: function(field, value) {
                        if (value) {
                            this.up().scheduleValidator();
                        }
                    }
                }
            },{
                xtype: 'textfield',
                width: 60,
                name: 'base.upd.schedule.w',
                margin: '0 3 0 0',
                listeners: {
                    change: function(field, value) {
                        if (value) {
                            this.up().scheduleValidator();
                        }
                    }
                },
                plugins: {
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: [{
                        id: 'info',
                        tooltip:
                            '*&nbsp;&nbsp;&nbsp;*&nbsp;&nbsp;&nbsp;*<br>' +
                            '─&nbsp;&nbsp;&nbsp;─&nbsp;&nbsp;&nbsp;─<br>' +
                            '│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>' +
                            '│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>' +
                            '│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;└───── day of week (0 - 6) (0 is Sunday)<br>' +
                            '│&nbsp;&nbsp;&nbsp;└─────── day of month (1 - 31)<br>' +
                            '└───────── hour (0 - 23)<br>'
                    }]
                }
            }]
        }]
    }]
});

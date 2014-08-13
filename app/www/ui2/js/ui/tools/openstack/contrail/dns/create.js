Scalr.regPage('Scalr.ui.tools.openstack.contrail.dns.create', function (loadParams, moduleParams) {
    var panel = Ext.create('Ext.form.Panel', {
        width: 600,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Networking &raquo; Virtual DNS &raquo; ' + (moduleParams['dns'] ? 'Edit' : 'Create') + ' server',
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },

        items: [{
            xtype: 'fieldset',
            defaults: {
                labelWidth: 150
            },
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Server name',
                name: 'fq_name',
                regex: /^[a-zA-Z0-9]+$/,
                readOnly: !!moduleParams['dns'],
                allowBlank: false
            }, {
                xtype: 'textfield',
                fieldLabel: 'Domain name',
                name: 'domain_name',
                readOnly: !!moduleParams['dns'],
                allowBlank: false
            }, {
                xtype: 'combo',
                store: moduleParams['listDns'],
                fieldLabel: 'DNS forwarder',
                name: 'next_virtual_dns',
                emptyText: 'Enter forwarder IP or select a DNS server'
            }, {
                xtype: 'buttongroupfield',
                layout: 'hbox',
                defaults: {
                    flex: 1
                },
                items: [{
                    text: 'Random',
                    value: 'random'
                }, {
                    text: 'Fixed',
                    value: 'fixed'
                }, {
                    text: 'Round-Robin',
                    value: 'round-robin'
                }],
                name: 'record_order',
                value: 'random',
                fieldLabel: 'Record resolution order',
                editable: false,
                allowBlank: false
            }, {
                xtype: 'textfield',
                name: 'default_ttl_seconds',
                fieldLabel: 'Time to live',
                emptyText: '86400',
                vtype: 'num'
            }, {
                xtype: 'comboboxselect',
                store: {
                    fields: [ 'name', 'fq_name', 'uuid' ],
                    data: moduleParams['ipams']
                },
                valueField: 'uuid',
                displayField: 'name',
                fieldLabel: 'Associate IPAMs',
                name: 'network_ipams'
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'Save',
                handler: function() {
                    var form = panel.getForm();
                    if (form.isValid()) {
                        var request = {}, values = form.getValues(), params = {}, i;
                        var networkIpams = panel.down('[name="network_ipams"]');

                        request['fq_name'] = Ext.Array.clone(moduleParams['fqBaseName']);
                        request['fq_name'].push(values['fq_name']);
                        request['parent_type'] = 'domain';
                        request['virtual_DNS_data'] = {
                            default_ttl_seconds: values['default_ttl_seconds'] ? parseInt(values['default_ttl_seconds']) : 86400,
                            domain_name: values['domain_name'],
                            next_virtual_DNS: values['next_virtual_dns'],
                            record_order: values['record_order'],
                            dynamic_records_from_client: true
                        };
                        request['network_ipam_back_refs'] = [];

                        for (i = 0; i < values['network_ipams'].length; i++) {
                            var rec = networkIpams.findRecordByValue(values['network_ipams'][i]);
                            request['network_ipam_back_refs'].push({
                                to: rec.get('fq_name'),
                                uuid: rec.get('uuid')
                            });
                        }

                        Ext.apply(params, loadParams);
                        params['request'] = Ext.encode(request);

                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            scope: this,
                            params: params,
                            url: '/tools/openstack/contrail/dns/xSave',
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]
    });

    if (moduleParams['dns']) {
        var dns = moduleParams['dns'], values = {
            fq_name: dns['fq_name'][1],
            default_ttl_seconds: dns['virtual_DNS_data']['default_ttl_seconds'],
            domain_name: dns['virtual_DNS_data']['domain_name'],
            next_virtual_dns: dns['virtual_DNS_data']['next_virtual_DNS'],
            record_order: dns['virtual_DNS_data']['record_order'],
            network_ipams: []
        };

        if (dns['network_ipam_back_refs']) {
            for (var i = 0; i < dns['network_ipam_back_refs'].length; i++) {
                values['network_ipams'].push(dns['network_ipam_back_refs'][i]['uuid']);
            }
        }

        panel.getForm().setValues(values);
    }

    return panel;
});

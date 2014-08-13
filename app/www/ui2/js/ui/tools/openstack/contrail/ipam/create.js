Scalr.regPage('Scalr.ui.tools.openstack.contrail.ipam.create', function (loadParams, moduleParams) {
    var panel = Ext.create('Ext.form.Panel', {
        width: 600,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Networking &raquo; IPAM &raquo; ' + (moduleParams['ipam'] ? 'Edit' : 'Create'),
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },
        layout: 'auto',

        items: [{
            xtype: 'fieldset',
            defaults: {
                labelWidth: 150
            },
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Name',
                name: 'fq_name',
                allowBlank: false,
                readOnly: !! moduleParams['ipam']
            }, {
                xtype: 'buttongroupfield',
                fieldLabel: 'DNS method',
                layout: 'hbox',
                value: 'default',
                name: 'dns_method',
                defaults: {
                    flex: 1
                },
                items: [{
                    text: 'Default',
                    value: 'default'
                }, {
                    text: 'Virtual DNS',
                    value: 'virtual-dns'
                }, {
                    text: 'Tenant',
                    value: 'tenant'
                }, {
                    text: 'None',
                    value: 'none'
                }],
                listeners: {
                    change: function(field, value) {
                        var tenant_server_ip = this.next('[name="tenant_server_ip"]'), dns_name = this.next('[name="dns_uuid"]');

                        if (value == 'default' || value == 'none') {
                            tenant_server_ip.hide().disable();
                            dns_name.hide().disable();
                        } else if (value == 'virtual-dns') {
                            dns_name.show().enable();
                            tenant_server_ip.hide().disable();
                        } else {
                            dns_name.hide().disable();
                            tenant_server_ip.show().enable();
                        }
                    }
                }
            }, {
                xtype: 'combo',
                store: {
                    fields: [ 'uuid', 'name', 'fq_name' ],
                    data: moduleParams['dns']
                },
                valueField: 'uuid',
                displayField: 'name',
                allowBlank: false,
                hidden: true,
                disabled: true,
                fieldLabel: 'Virtual DNS',
                editable: false,
                name: 'dns_uuid'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Tenant DNS server IP',
                name: 'tenant_server_ip',
                hidden: true,
                disabled: true,
                allowBlank: false
            }, {
                xtype: 'textfield',
                fieldLabel: 'NTP server IP',
                name: 'ntp_server_ip'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Domain name',
                name: 'domain_name'
            }, {
                xtype: 'grid',
                itemId: 'view',
                cls: 'x-grid-shadow',
                maxHeight: 500,
                selModel: {
                    selType: 'selectedmodel'
                },
                store: {
                    proxy: 'object',
                    fields: [ 'to', 'attr' ]
                },
                plugins: {
                    ptype: 'gridstore'
                },
                listeners: {
                    selectionchange: function(selModel, selected) {
                        this.down('#delete').setDisabled(!selected.length);
                    }
                },
                viewConfig: {
                    focusedItemCls: 'no-focus',
                    emptyText: 'No IP blocks defined',
                    deferEmptyText: false
                },
                columns: [
                    { header: 'Network', flex: 1, sortable: false, xtype: 'templatecolumn', tpl:
                        '{[values.to[2]]}'
                    },
                    { header: 'IP Block', flex: 1, sortable: false, xtype: 'templatecolumn', tpl:
                        '{[values.attr.ipam_subnets[0].subnet.ip_prefix]}/{[values.attr.ipam_subnets[0].subnet.ip_prefix_len]}'
                    },
                    { header: 'Gateway', flex: 1, sortable: false, xtype: 'templatecolumn', tpl:
                        '{[values.attr.ipam_subnets[0].default_gateway]}'
                    }
                ],
                dockedItems: [{
                    xtype: 'toolbar',
                    ui: 'simple',
                    dock: 'top',
                    defaults: {
                        margin: '0 0 0 10'
                    },
                    items: [{
                        xtype: 'tbfill'
                    },{
                        itemId: 'add',
                        text: 'Associate',
                        cls: 'x-btn-green-bg',
                        tooltip: 'Associate IP Blocks to IPAM',
                        handler: function() {
                            var store = this.up('#view').store;
                            Scalr.Confirm({
                                form: [{
                                    xtype: 'fieldset',
                                    title: 'New IP Block',
                                    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                                    defaults: {
                                        anchor: '100%',
                                        labelWidth: 120
                                    },
                                    items: [{
                                        xtype: 'combo',
                                        store: {
                                            fields: [ 'name', 'uuid', 'fq_name' ],
                                            data: moduleParams['networks']
                                        },
                                        name: 'network',
                                        valueField: 'uuid',
                                        displayField: 'name',
                                        fieldLabel: 'Network',
                                        editable: false,
                                        allowBlank: false
                                    }, {
                                        xtype: 'textfield',
                                        name: 'ip',
                                        fieldLabel: 'IP Block',
                                        allowBlank: false,
                                        emptyText: 'IP address in xxx.xxx.xxx.xxx/xx format'
                                    }, {
                                        xtype: 'textfield',
                                        name: 'gateway',
                                        fieldLabel: 'Gateway',
                                        allowBlank: false
                                    }]
                                }],
                                formWidth: 500,
                                ok: 'Add',
                                formValidate: true,
                                closeOnSuccess: true,
                                success: function (formValues) {
                                    var network = this.down('[name="network"]'), rec = network.findRecordByValue(formValues['network']), object = {}, ip = formValues['ip'].split('/');

                                    object['to'] = rec.get('fq_name');
                                    object['uuid'] = rec.get('uuid');
                                    object['attr'] = {
                                        ipam_subnets: [{
                                            default_gateway: formValues['gateway'],
                                            subnet: {
                                                ip_prefix: ip[0],
                                                ip_prefix_len: parseInt(ip[1])
                                            }
                                        }]
                                    };

                                    store.add(object);
                                    return true;
                                }
                            });
                        }
                    },{
                        itemId: 'delete',
                        iconCls: 'x-tbar-delete',
                        ui: 'paging',
                        disabled: true,
                        tooltip: 'Delete selected rules',
                        handler: function() {
                            var grid = this.up('grid'),
                                selModel = grid.getSelectionModel(),
                                selection = selModel.getSelection();
                            selModel.setLastFocused(null);
                            grid.getStore().remove(selection);
                        }
                    }]
                }]
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
                        var request = {}, values = form.getValues(), params = {}, i, dhcp = [], blockStore = panel.down('#view').store;

                        request['fq_name'] = Ext.Array.clone(moduleParams['fqBaseName']);
                        request['fq_name'].push(values['fq_name']);
                        request['parent_type'] = 'project';
                        request['network_ipam_mgmt'] = {};
                        request['virtual_DNS_refs'] = [];

                        if (values['ntp_server_ip']) {
                            dhcp.push({
                                dhcp_option_name: 4,
                                dhcp_option_value: values['ntp_server_ip']
                            });
                        }

                        if (values['domain_name']) {
                            dhcp.push({
                                dhcp_option_name: 15,
                                dhcp_option_value: values['domain_name']
                            });
                        }

                        if (dhcp.length) {
                            request['network_ipam_mgmt']['dhcp_option_list'] = {
                                dhcp_option: dhcp
                            };
                        }

                        switch (values['dns_method']) {
                            case 'none':
                                request['network_ipam_mgmt']['ipam_dns_method'] = 'none';
                                break;

                            case 'virtual-dns':
                                var rec = panel.down('[name="dns_uuid"]').findRecordByValue(values['dns_uuid']);

                                request['network_ipam_mgmt']['ipam_dns_method'] = 'virtual-dns-server';
                                request['network_ipam_mgmt']['ipam_dns_server'] = {
                                    virtual_dns_server_name: rec.get('fq_name').join(':')
                                };
                                request['virtual_DNS_refs'].push({
                                    uuid: rec.get('uuid'),
                                    to: rec.get('fq_name')

                                });
                                break;

                            case 'tenant':
                                request['network_ipam_mgmt']['ipam_dns_method'] = 'tenant-dns-server';
                                request['network_ipam_mgmt']['ipam_dns_server'] = {
                                    tenant_dns_server_address: {
                                        ip_address: [ values['tenant_server_ip'] ]
                                    }
                                };
                                break;

                            default:
                                request['network_ipam_mgmt']['ipam_dns_method'] = 'default-dns-server';
                                break;
                        }

                        if (blockStore.count()) {
                            request['virtual_network_refs'] = [];
                            blockStore.each(function (rec) {
                                request['virtual_network_refs'].push(rec.getData());
                            });
                        }

                        // WHY ? I don't know, but it requires =(
                        request['uuid'] = loadParams['ipamId'];

                        Ext.apply(params, loadParams);
                        params['request'] = Ext.encode(request);

                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            scope: this,
                            params: params,
                            url: '/tools/openstack/contrail/ipam/xSave',
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

    if (moduleParams['ipam']) {
        var ipam = moduleParams['ipam'], values = {};

        values['fq_name'] = ipam['fq_name'][2];
        switch (ipam['network_ipam_mgmt']['ipam_dns_method']) {
            case 'default-dns-server':
                values['dns_method'] = 'default';
                break;

            case 'none':
                values['dns_method'] = 'none';
                break;

            case 'virtual-dns-server':
                values['dns_method'] = 'virtual-dns';
                values['dns_uuid'] = ipam['virtual_DNS_refs'][0]['uuid'];
                break;

            case 'tenant-dns-server':
                values['dns_method'] = 'tenant';
                values['tenant_server_ip'] = ipam['network_ipam_mgmt']['ipam_dns_server']['tenant_dns_server_address']['ip_address'];
                break;
        }

        if ('dhcp_option_list' in ipam['network_ipam_mgmt']) {
            var dhcp = ipam['network_ipam_mgmt']['dhcp_option_list']['dhcp_option'], i;
            for (i = 0; i < dhcp.length; i++) {
                if (dhcp[i].dhcp_option_name == 4)
                    values['ntp_server_ip'] = dhcp[i].dhcp_option_value;
                else if (dhcp[i].dhcp_option_name == 15)
                    values['domain_name'] = dhcp[i].dhcp_option_value;
            }
        }

        panel.getForm().setValues(values);

        if (ipam['virtual_network_back_refs'])
            panel.down('#view').store.loadData(ipam['virtual_network_back_refs']);
    }

    return panel;
});

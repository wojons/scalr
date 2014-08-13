Scalr.regPage('Scalr.ui.tools.openstack.contrail.policies.create', function (loadParams, moduleParams) {
    var panel = Ext.create('Ext.form.Panel', {
        width: 1200,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Networking &raquo; Policy &raquo; ' + (moduleParams['policy'] ? ' Edit' : 'Create'),
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },

        items: [{
            xtype: 'fieldset',
            defaults: {
                labelWidth: 60
            },
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Name',
                name: 'fq_name',
                maxWidth: 300,
                allowBlank: false,
                readOnly: !!moduleParams['policy']
            },{
                xtype: 'grid',
                itemId: 'view',
                cls: 'x-grid-shadow',
                maxHeight: 500,
                selModel: {
                    selType: 'selectedmodel'
                },
                store: {
                    proxy: 'object',
                    fields: [ 'action_list', 'application', 'direction', 'dst_addresses', 'dst_ports', 'protocol', 'rule_sequence', 'src_addresses', 'src_ports' ]
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
                    emptyText: 'No policies defined',
                    deferEmptyText: false
                },
                columns: [
                    { header: 'Action', width: 80, sortable: false, xtype: 'templatecolumn', tpl:
                        '{[values.action_list.simple_action]}'
                    },
                    { header: 'Protocol', width: 80, sortable: false, dataIndex: 'protocol' },
                    { header: 'Source network', flex: 1, sortable: false, xtype: 'templatecolumn', tpl:
                        '{[values.src_addresses[0].virtual_network]}'
                    },
                    { header: 'Source ports', width: 150, sortable: false, xtype: 'templatecolumn', tpl:
                        '<tpl for="src_ports">' +
                            '<tpl if="start_port == -1 && end_port == -1">' +
                                'any' +
                            '<tpl elseif="start_port != end_port">' +
                                '{start_port}-{end_port}' +
                            '<tpl else>' +
                                '{start_port}' +
                            '</tpl>' +
                            '<tpl if="xcount &gt; xindex">, </tpl>' +
                        '</tpl>'
                    },
                    { header: 'Direction', width: 100, sortable: false, dataIndex: 'direction' },
                    { header: 'Destination network', flex: 1, sortable: false, xtype: 'templatecolumn', tpl:
                        '{[values.dst_addresses[0].virtual_network]}'},
                    { header: 'Destination ports', width: 150, sortable: false, xtype: 'templatecolumn', tpl:
                        '<tpl for="dst_ports">' +
                            '<tpl if="start_port == -1 && end_port == -1">' +
                            'any' +
                            '<tpl elseif="start_port != end_port">' +
                            '{start_port}-{end_port}' +
                            '<tpl else>' +
                            '{start_port}' +
                            '</tpl>' +
                            '<tpl if="xcount &gt; xindex">, </tpl>' +
                            '</tpl>'
                    },
                    //{ header: 'Apply service', flex: 1, sortable: true, dataIndex: 'comment' },
                    //{ header: 'Mirror to', flex: 1, sortable: true, dataIndex: 'comment' }
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
                        text: 'Add rule',
                        cls: 'x-btn-green-bg',
                        tooltip: 'New policy',
                        handler: function() {
                            var store = this.up('#view').store;
                            Scalr.Confirm({
                                form: [{
                                    xtype: 'fieldset',
                                    title: 'New policy',
                                    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                                    defaults: {
                                        anchor: '100%',
                                        labelWidth: 120
                                    },
                                    items: [{
                                        xtype: 'combo',
                                        store: [ ['pass', 'PASS'], ['deny', 'DENY'] ],
                                        name: 'simple_action',
                                        fieldLabel: 'Action',
                                        value: 'pass',
                                        editable: false
                                    }, {
                                        xtype: 'combo',
                                        store: [ ['any', 'ANY'], ['tcp', 'TCP'], ['udp', 'UDP'], ['icmp', 'ICMP']],
                                        name: 'protocol',
                                        fieldLabel: 'Protocol',
                                        value: 'any',
                                        editable: false
                                    }, {
                                        xtype: 'combo',
                                        store: moduleParams['networks'],
                                        name: 'src_network',
                                        fieldLabel: 'Source network',
                                        value: 'any',
                                        editable: false
                                    }, {
                                        xtype: 'textfield',
                                        name: 'src_ports',
                                        emptyText: 'ANY',
                                        fieldLabel: 'Source ports'
                                    }, {
                                        xtype: 'combo',
                                        store: [ '<>', '>' ],
                                        name: 'direction',
                                        editable: false,
                                        fieldLabel: 'Direction',
                                        value: '<>'
                                    }, {
                                        xtype: 'combo',
                                        store: moduleParams['networks'],
                                        name: 'dst_network',
                                        fieldLabel: 'Destination network',
                                        value: 'any',
                                        editable: false
                                    }, {
                                        xtype: 'textfield',
                                        name: 'dst_ports',
                                        emptyText: 'ANY',
                                        fieldLabel: 'Destination ports'
                                    }/*, {
                                        xtype: 'checkbox',
                                        boxLabel: 'Apply service',
                                        name: 'apply_service' + combo
                                    }, {
                                        xtype: 'checkbox',
                                        boxLabel: 'Mirror to',
                                        name: 'mirror_to' + combo
                                    }*/
                                    ]
                                }],
                                formWidth: 600,
                                ok: 'Add',
                                formValidate: true,
                                closeOnSuccess: true,
                                scope: this,
                                success: function (formValues) {
                                    var convertPorts = function(str) {
                                        var lines = str.split(','), i, result = [];
                                        for (i = 0; i < lines.length; i++) {
                                            var ports = lines[i].split('-');
                                            if (ports.length == 2) {
                                                result.push({
                                                    start_port: parseInt(ports[0]),
                                                    end_port: parseInt(ports[1])
                                                });
                                            } else {
                                                result.push({
                                                    start_port: parseInt(ports[0]),
                                                    end_port: parseInt(ports[0])
                                                });
                                            }
                                        }

                                        return result;
                                    };

                                    var object = {
                                        action_list: {
                                            simple_action: formValues['simple_action']
                                        },
                                        direction: formValues['direction'],
                                        dst_addresses: [{
                                            virtual_network: formValues['dst_network']
                                        }],
                                        protocol: formValues['protocol'],
                                        src_addresses: [{
                                            virtual_network: formValues['src_network']
                                        }],
                                        src_ports: [{
                                            start_port: -1,
                                            end_port: -1
                                        }],
                                        dst_ports: [{
                                            start_port: -1,
                                            end_port: -1
                                        }],
                                        rule_sequence: {
                                            major: -1,
                                            minor: -1
                                        }
                                    };

                                    if (formValues['src_ports']) {
                                        object['src_ports'] = convertPorts(formValues['src_ports']);
                                    }

                                    if (formValues['dst_ports']) {
                                        object['dst_ports'] = convertPorts(formValues['dst_ports']);
                                    }

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
                        var request = {}, values = form.getValues(), params = {}, policyStore = panel.down('#view').store;

                        request['fq_name'] = Ext.Array.clone(moduleParams['fqBaseName']);
                        request['fq_name'].push(values['fq_name']);
                        request['parent_type'] = 'project';
                        request['network_ipam_mgmt'] = {};
                        request['virtual_DNS_refs'] = [];
                        request['network_policy_entries'] = {
                            policy_rule: []
                        };

                        policyStore.each(function (rec) {
                            request['network_policy_entries']['policy_rule'].push(rec.getData());
                        });

                        Ext.apply(params, loadParams);
                        params['request'] = Ext.encode(request);

                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            scope: this,
                            params: params,
                            url: '/tools/openstack/contrail/policies/xSave',
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

    if (moduleParams['policy']) {
        var policy = moduleParams['policy'], values = {}, policyStore = panel.down('#view').store;

        values['fq_name'] = policy['fq_name'][2];
        if (policy['network_policy_entries']) {
            policyStore.loadData(policy['network_policy_entries']['policy_rule']);
        }

        panel.getForm().setValues(values);
    }

    return panel;
});

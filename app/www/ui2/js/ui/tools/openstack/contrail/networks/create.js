Scalr.regPage('Scalr.ui.tools.openstack.contrail.networks.create', function (loadParams, moduleParams) {
    var panel = Ext.create('Ext.form.Panel', {
        width: 700,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Networking &raquo; Networks &raquo; ' + (moduleParams['network'] ? 'Edit' : 'Create'),
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },

        items: [{
            xtype: 'fieldset',
            defaults: {
                labelWidth: 120
            },
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Name',
                name: 'fq_name',
                readOnly: !!moduleParams['network'],
                allowBlank: false
            }, {
                xtype: 'comboboxselect',
                store: {
                    fields: [ 'name', 'fq_name' ],
                    data: moduleParams['policies']
                },
                valueField: 'name',
                displayField: 'name',
                fieldLabel: 'Network policy(s)',
                name: 'network_policies'
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
                    { header: 'IPAM', flex: 1, sortable: false, xtype: 'templatecolumn', tpl:
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
                        tooltip: 'Associate IP Blocks to networks',
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
                                            data: moduleParams['ipams']
                                        },
                                        name: 'ipam',
                                        valueField: 'uuid',
                                        displayField: 'name',
                                        fieldLabel: 'IPAM',
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
                                    var network = this.down('[name="ipam"]'), rec = network.findRecordByValue(formValues['ipam']), object = {}, ip = formValues['ip'].split('/');

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
        }, {
            xtype: 'fieldset',
            defaults: {
                labelWidth: 120
            },
            title: 'Advanced options',
            items: [{
                xtype: 'combo',
                fieldLabel: 'Forwarding mode',
                store: [ ['l2_l3', 'L2 and L3'], ['l2', 'L2']],
                editable: false,
                value: 'l2_l3',
                maxWidth: 250,
                name: 'forwarding_mode'
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
                    var form = panel.getForm(), values = form.getValues(), request = {}, params = {}, i, blockStore = panel.down('#view').store;
                    var networkPolicies = panel.down('[name="network_policies"]');

                    request['fq_name'] = Ext.Array.clone(moduleParams['fqBaseName']);
                    request['fq_name'].push(values['fq_name']);
                    request['parent_type'] = 'project';

                    request['network_policy_refs'] = [];
                    for (i = 0; i < values['network_policies'].length; i++) {
                        request['network_policy_refs'].push({
                            attr: {
                                sequence: {
                                    major: 0,
                                    minor: 0
                                },
                                timer: null
                            },
                            to: networkPolicies.findRecordByValue(values['network_policies'][i]).get('fq_name')
                        });
                    }

                    request['network_ipam_refs'] = [];
                    if (blockStore.count()) {
                        blockStore.each(function (rec) {
                            request['network_ipam_refs'].push(rec.getData());
                        });
                    }

                    // advanced
                    request['virtual_network_properties'] = {
                        forwarding_mode: values['forwarding_mode']
                    };

                    Ext.apply(params, loadParams);
                    params['request'] = Ext.encode(request);

                    if (form.isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            scope: this,
                            params: params,
                            url: '/tools/openstack/contrail/networks/xSave',
                            success: function () {
                                Scalr.event.fireEvent('close');
                                //Scalr.event.fireEvent('refresh');
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

    if (moduleParams['network']) {
        var values = {}, network = moduleParams['network'], i;
        values['fq_name'] = network['fq_name'][2];
        values['network_policies'] = [];
        if (network['network_policy_refs']) {
            for (i = 0; i < network['network_policy_refs'].length; i++) {
                values['network_policies'].push(network['network_policy_refs'][i]['to'][2]);
            }
        }

        if (network['network_ipam_refs']) {
            panel.down('#view').store.loadData(network['network_ipam_refs']);
        }

        // advanced
        values['forwarding_mode'] = network['virtual_network_properties']['forwarding_mode'];


        panel.getForm().setValues(values);
    }

    return panel;
});

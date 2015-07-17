Scalr.regPage('Scalr.ui.tools.openstack.lb.monitors.create', function (loadParams, moduleParams) {

    var panel = Ext.create('Ext.form.Panel', {
        width: 500,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Load balancers &raquo; Monitors &raquo; Create monitor',
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },

        isTypeHttpOrHttps: function(typeFieldValue) {
            if (!typeFieldValue) {
            var typeField = panel.down('#type-of-monitoring');
            typeFieldValue = typeField.getValue();
            }
            return typeFieldValue === 'HTTP' || typeFieldValue === 'HTTPS';
        },

        hideFields: function(typeFieldValue) {
            var visible = panel.isTypeHttpOrHttps(typeFieldValue),
                httpMethodField = panel.down('#http-method'),
                urlField = panel.down('#url-field');

            httpMethodField.setVisible(visible);
            urlField.setVisible(visible);
            panel.down('#expected-codes-field').setVisible(visible);
            panel.updateLayout();
        },

        items: [{
            xtype: 'fieldset',
            title: 'Monitor details',
            defaults: {
                labelWidth: 150
            },
            items: [{
                fieldLabel:'Pool',
                xtype: 'combo',
                emptyText: 'Select a pool',
                editable: false,
                allowBlank: false,
                store: {
                    fields: ['id', 'name'],
                    data: moduleParams['pools'],
                    proxy: 'object'
                },
                displayField: 'name',
                valueField: 'id',
                name: 'pool_id'
            }, {
                fieldLabel:'Type',
                itemId: 'type-of-monitoring',
                xtype: 'combo',
                editable: false,
                store: ['PING', 'TCP', 'HTTP', 'HTTPS'],
                emptyText: 'Select type',
                allowBlank: false,
                name: 'type',
                listeners: {
                    change: function(combo, value) {
                        panel.hideFields(value);
                    }
                }
            }, {
                fieldLabel:'Delay',
                xtype: 'textfield',
                allowBlank: false,
                validator: function (value) {
                    return value >=0 ? true : 'Delay should have a non-negative value';
                },
                name: 'delay'
            }, {
                fieldLabel:'Timeout',
                xtype: 'textfield',
                allowBlank: false,
                validator: function (value) {
                    return value >=0 ? true : 'Timeout should have a non-negative value';
                },
                name: 'timeout'
            }, {
                fieldLabel:'Max retries (1~10)',
                xtype: 'textfield',
                allowBlank: false,
                validator: function (value) {
                    return value >=1 && value <= 10 ? true : 'Max number of retries should have a value from 1 to 10';
                },
                name: 'max_retries'
            }, {
                fieldLabel:'HTTP method',
                itemId: 'http-method',
                xtype: 'combo',
                editable: false,
                store: ['GET'],
                value: 'GET',
                validator: function (value) {
                    if (panel.isTypeHttpOrHttps()) {
                        return value ? true : 'HTTP method required if type is HTTP or HTTPS';
                    } else {
                        return true;
                    }
                },
                name: 'http_method'
            }, {
                fieldLabel:'URL',
                itemId: 'url-field',
                xtype: 'textfield',
                value: '/',
                validator: function (value) {
                    if (panel.isTypeHttpOrHttps()) {
                        if (!value) {
                            return 'URL required if type is HTTP or HTTPS';
                        } else if (value && value.indexOf('/') !== 0) {
                            return 'Path must be a string beginning with a / (forward slash)';
                        } else {
                            return true;
                        }
                    } else {
                        return true;
                    }
                },
                name: 'url_path'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Expected HTTP status codes',
                itemId: 'expected-codes-field',
                width: 260,
                value: 200,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: 'The list of HTTP status codes expected in response from the member to ' +
                        'declare it healthy.<br/>Expected codes can be represented as a single value (e.g. 200), ' +
                        'list (e.g. 200, 202) or range (e.g. 200-204).'
                    }
                }],
                validator: function (value) {
                    if (panel.isTypeHttpOrHttps()) {
                        return value ? true : 'URL required if type is HTTP or HTTPS';
                    } else {
                        return true;
                    }
                },
                name: 'expected_codes'
            }, {
                fieldLabel:'Admin state',
                xtype: 'checkboxfield',
                inputValue: true,
                uncheckedValue: false,
                value: true,
                name: 'admin_state_up'
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
                text: 'Create',
                handler: function() {
                    var form = panel.getForm();
                    if (form.isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: panel.getForm(),
                            scope: this,
                            params: loadParams,
                            url: '/tools/openstack/lb/monitors/xSave',
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
        }],

        listeners: {
            afterrender: function() {
                this.hideFields();
            }
        }
    });

    return panel;
});

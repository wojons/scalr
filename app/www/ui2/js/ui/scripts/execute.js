Scalr.regPage('Scalr.ui.scripts.execute', function (loadParams, moduleParams) {
    var form = Ext.create('Ext.form.Panel', {
        width: 900,
        title: loadParams['edit'] ? 'Edit shortcut' : 'Execute script',
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 120
        },

        items: [{
            xtype: 'farmroles',
            title: 'Execution target',
            itemId: 'executionTarget',
            params: moduleParams['farmWidget'],
            listeners: {
                boxready: function() {
                    var el = form.down('#additional');
                    if (!!loadParams['edit']) {
                        this.down('[name="farmId"]').setReadOnly(true);
                        this.down('[name="farmRoleId"]').setReadOnly(true);
                    }

                    this.down('[name="farmId"]').on('change', function(field, value) {
                        el[value != 0 ? 'show' : 'hide']();
                    });

                    this.down('[name="serverId"]').on('change', function(field, value) {
                        el[value != 0 ? 'hide' : 'show']();
                    });
                    this.down('[name="serverId"]').on('hide', function() {
                        el.show();
                    });
                }
            }
        }, {
            xtype: 'fieldset',
            items: [{
                xtype: 'tabpanel',
                itemId: 'tabs',
                cls: 'x-tabs-dark',
                margin: '0 0 18 0',
                defaults: {
                    listeners: {
                        activate: function(tab) {
                            var scriptParamsWrapper = form.down('#scriptParams');
                            scriptParamsWrapper.setVisible(tab.itemId === 'scalrscript' ? !!scriptParamsWrapper.scriptHasParams : false);

                            form.down('[name="scriptId"]').setDisabled(tab.itemId != 'scalrscript');
                            form.down('[name="scriptPath"]').setDisabled(tab.itemId != 'localscript');
                        }
                    }
                },
                items: [{
                    xtype: 'container',
                    itemId: 'scalrscript',
                    tabConfig: {
                        title: 'Scalr script'
                    },
                    items: [{
                        xtype: 'container',
                        cls: 'x-container-fieldset',
                        layout: {
                            type: 'hbox',
                            align: 'stretch'
                        },
                        items: [{
                            xtype: 'scriptselectfield',
                            name: 'scriptId',
                            emptyText: 'Select a script',
                            store: {
                                fields: [ 'id', 'name', 'description', 'os', 'isSync', 'timeout', 'versions', 'accountId', 'scope', 'createdByEmail' ],
                                data: moduleParams.scripts,
                                proxy: 'object'
                            },
                            allowBlank: false,
                            flex: 1,
                            labelWidth: 60,
                            listeners: {
                                change: function (field) {
                                    var record = field.findRecordByValue(field.getValue());
                                    var scriptVersionField = form.down('[name="scriptVersion"]');

                                    scriptVersionField.setValue('');

                                    if (record) {
                                        scriptVersionField.store.loadData(record.get('versions'));
                                        scriptVersionField.store.insert(0, { version: -1, versionName: 'Latest', variables: scriptVersionField.store.last().get('variables') });

                                        if (!moduleParams.shortcutId) {
                                            scriptVersionField.setValue(scriptVersionField.store.first().get('version'));

                                            form.down('[name="scriptTimeout"]').setValue(record.get('timeout'));
                                            form.down('[name="scriptIsSync"]').setValue(record.get('isSync'));
                                        }
                                    }
                                }
                            }
                        }, {
                            xtype: 'combo',
                            store: {
                                fields: ['version', 'versionName', 'variables' ],
                                proxy: 'object'
                            },
                            valueField: 'version',
                            displayField: 'versionName',
                            editable: false,
                            queryMode: 'local',
                            width: 140,
                            labelWidth: 60,
                            margin: '0 0 0 24',
                            name: 'scriptVersion',
                            fieldLabel: 'Version',
                            listeners: {
                                change: function (field, value) {
                                    var fieldset = form.down('#scriptParams');
                                    if (! value) {
                                        fieldset.removeAll();
                                        fieldset.hide();
                                        field.scriptHasParams = false;
                                        return;
                                    }
                                    var r = field.findRecordByValue(value);
                                    if (!r)
                                        return;

                                    var variables = r.get('variables');

                                    fieldset.removeAll();
                                    if (Ext.isObject(variables)) {
                                        for (var i in variables) {
                                            fieldset.add({
                                                xtype: 'textfield',
                                                fieldLabel: variables[i],
                                                name: 'scriptParams[' + i + ']',
                                                value: moduleParams['scriptParams'] ? moduleParams['scriptParams'][i] : ''
                                            });
                                        }
                                        fieldset.show();
                                        fieldset.scriptHasParams = true;
                                    } else {
                                        fieldset.hide();
                                        fieldset.scriptHasParams = false;
                                    }
                                }
                            }
                        }]
                    }]
                }, {
                    xtype: 'container',
                    itemId: 'localscript',
                    cls: 'x-container-fieldset',
                    layout: 'anchor',
                    defaults: {
                        anchor: '100%'
                    },
                    tabConfig: {
                        title: 'Local script'
                    },
                    items: [{
                        xtype: 'textfield',
                        name: 'scriptPath',
                        disabled: true,
                        fieldLabel: 'Path',
                        plugins: {
                            ptype: 'fieldicons',
                            position: 'outer',
                            icons: ['globalvars']
                        },
                        allowBlank: false,
                        emptyText: '/path/to/the/script',
                        labelWidth: 60,
                        margin: 0
                    }]
                }]
            }, {
                xtype: 'buttongroupfield',
                fieldLabel: 'Execution mode',
                name: 'scriptIsSync',
                defaults: {
                    width: 130
                },
                items: [{
                    text: 'Blocking',
                    value: 1
                },{
                    text: 'Non-blocking',
                    value: 0
                }]
            }, {
                xtype: 'textfield',
                fieldLabel: 'Timeout',
                name: 'scriptTimeout',
                maxWidth: 170,
                vtype: 'num',
                validator: function (value) {
                    return value > 0 ? true : 'Timeout should be greater than 0';
                },
                allowBlank: false
            }]
        }, {
            xtype: 'fieldset',
            title: 'Script options',
            itemId: 'scriptParams',
            labelWidth: 100,
            hidden: true,
            fieldDefaults: {
                anchor: '100%'
            }
        }, {
            xtype: 'fieldset',
            title: 'Additional settings',
            itemId: 'additional',
            labelWidth: 100,
            items: [{
                xtype: 'checkbox',
                boxLabel: 'Add a shortcut in Options menu for roles.',
                name: 'shortcutId',
                inputValue: moduleParams['shortcutId'] || -1,
                checked: !!moduleParams['shortcutId'],
                readOnly: !!loadParams['edit'],
                plugins: {
                    ptype: 'fieldicons',
                    icons: [{id: 'info', tooltip: 'It will allow me to execute this script with the above parameters with a single click. <a href="https://scalr-wiki.atlassian.net/wiki/x/qQIb" target="_blank">Read more</a>'}]
                }
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
                hidden: !loadParams['edit'],
                handler: function () {
                    if (form.getForm().isValid())
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            url: '/scripts/xExecute/',
                            params: {
                                editShortcut: 1
                            },
                            form: form.getForm(),
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                }
            }, {
                xtype: 'splitbutton',
                text: 'Execute',
                hidden: !!loadParams['edit'],
                handler: function () {
                    Scalr.message.Flush(true);
                    if (form.getForm().isValid())
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            url: '/scripts/xExecute/',
                            form: form.getForm(),
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                },
                menu: [{
                    text: 'Execute script and stay on this page',
                    handler: function () {
                        if (form.getForm().isValid())
                            Scalr.Request({
                                processBox: {
                                    type: 'action'
                                },
                                url: '/scripts/xExecute/',
                                form: form.getForm(),
                                success: function () {

                                }
                            });
                    }
                }],
                listeners: {
                    menushow: function () {
                        Scalr.message.Flush(true);
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

    if (moduleParams) {
        if (moduleParams['scriptPath'])
            form.down('#tabs').setActiveTab('localscript');
        if (moduleParams.scriptId == 0) {
            delete moduleParams.scriptId;
        }
        form.getForm().setValues(moduleParams);
    }

    return form;
});
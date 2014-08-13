Scalr.regPage('Scalr.ui.scripts.create', function (loadParams, moduleParams) {
    var saveHandler = function (createNewVersion, executeFlag) {
        var params = {
            version: createNewVersion ? 0 : form.down('[name="version"]').getValue()
        };

        if (form.getForm().isValid())
            Scalr.Request({
                processBox: {
                    type: 'save'
                },
                url: '/scripts/xSave/',
                params: params,
                form: form.getForm(),
                success: function () {
                    if (moduleParams['script']) {
                        if (executeFlag)
                            Scalr.event.fireEvent('redirect', '#/scripts/' + moduleParams['script']['id'] + '/execute');
                        else
                            Scalr.event.fireEvent('close');
                    } else
                        Scalr.event.fireEvent('redirect', '#/scripts/view');
                }
            });
    };
    var form = Ext.create('Ext.form.Panel', {
        width: 900,
        title: (moduleParams['script']) ? 'Scripts &raquo; Edit' : 'Scripts &raquo; Create',
        fieldDefaults: {
            anchor: '100%'
        },

        tools: [{
            type: 'maximize',
            handler: function () {
                Scalr.event.fireEvent('maximize');
            }
        }],
        layout: 'auto',
        items: [{
            xtype: 'fieldset',
            title: 'General information',
            items: [{
                xtype: 'container',
                layout: 'hbox',
                maxWidth: 836,
                items: [{
                    xtype: 'textfield',
                    name: 'name',
                    fieldLabel: 'Name',
                    labelWidth: 150,
                    allowBlank: false,
                    flex: 1
                }, {
                    xtype: 'combo',
                    fieldLabel: 'Version',
                    name: 'version',
                    labelWidth: 50,
                    margin: '0 0 0 12',
                    width: 110,
                    hidden: !moduleParams['script'],
                    store: moduleParams['versions'],
                    editable: false,
                    value: 1,
                    submitValue: false,
                    queryMode: 'local',
                    listeners: {
                        change: function (field, value) {
                            if (this.rendered && moduleParams['script']) {
                                Scalr.Request({
                                    url: '/scripts/xGetContent',
                                    params: { version: value, scriptId: moduleParams['script']['id'] },
                                    processBox: {
                                        type: 'load',
                                        msg: 'Loading script contents ...'
                                    },
                                    scope: this,
                                    success: function (data) {
                                        this.up('form').down('[name="content"]').codeMirror.setValue(data['content']);
                                    }
                                });
                            }
                        }
                    }
                }, {
                    xtype: 'button',
                    ui: 'action',
                    itemId: 'delete',
                    hidden: !moduleParams['script'] || moduleParams['versions'].length < 2,
                    margin: '0 0 0 8',
                    cls: 'x-btn-action-delete',
                    tooltip: 'Remove selected version',
                    handler: function() {
                        Scalr.Request({
                            confirmBox: {
                                type: 'delete',
                                msg: 'Are you sure want to delete selected version? This cannot be undone.'
                            },
                            processBox: {
                                type: 'delete'
                            },
                            url: '/scripts/' + moduleParams['script']['id'] + '/xRemoveVersion',
                            params: {
                                version: this.prev().getValue()
                            },
                            success: function() {
                                Scalr.event.fireEvent('refresh');
                            }
                        });
                    }
                }]
            }, {
                xtype: 'textfield',
                name: 'description',
                labelWidth: 150,
                maxWidth: 836,
                fieldLabel: 'Description'
            }, {
                xtype: 'container',
                layout: 'hbox',
                maxWidth: 836,
                items: [{
                    xtype: 'buttongroupfield',
                    fieldLabel: 'Default execution mode',
                    editable: false,
                    name: 'isSync',
                    labelWidth: 150,
                    flex: 1,
                    defaults: {
                        width: 110
                    },
                    value: 1,
                    items: [{
                        text: 'Blocking',
                        value: 1
                    },{
                        text: 'Non-blocking',
                        value: 0
                    }],
                    setDefaultTimeout: function(value) {
                        var t = this.next('[name="timeout"]');
                        t.emptyText = moduleParams['timeouts'][value == 1 ? 'sync' : 'async'];
                        t.applyEmptyText();
                    },
                    listeners: {
                        afterlayout: function() {
                            this.setDefaultTimeout(this.getValue());
                        },
                        change: function(field, value) {
                            this.setDefaultTimeout(value);
                        }
                    }
                }, {
                    xtype: 'textfield',
                    fieldLabel: 'Default timeout',
                    flex: 1,
                    maxWidth: 160,
                    name: 'timeout'
                }, {
                    xtype: 'combo',
                    flex: 1,
                    maxWidth: 260,
                    hidden: Scalr.user.type == 'ScalrAdmin',
                    store: {
                        fields: [ { name: 'id', type: 'int' }, 'name' ],
                        data: moduleParams['environments']
                    },
                    displayField: 'name',
                    valueField: 'id',
                    name: 'envId',
                    value: 0,
                    editable: false,
                    fieldLabel: 'Environment',
                    margin: '0 0 0 12',
                    labelWidth: 80
                }]
            }, {
                xtype: 'tagbox',
                fieldLabel: 'Tags',
                saveTagsOn: 'submit',
                labelWidth: 150,
                maxWidth: 836,
                name: 'tags',
                flex: 1
            }]
        }, {
            xtype: 'fieldset',
            collapsible: true,
            collapsed: true,
            title: 'Variables',
            items: [{
                xtype: 'displayfield',
                cls: 'x-form-field-info',
                value: 'You can access <a href="https://scalr-wiki.atlassian.net/wiki/x/hiIb" target="_blank">Global Variables</a> as Environment Variables in your Scripts. Visit <a href="https://scalr-wiki.atlassian.net/wiki/x/7R4b" target="_blank">the documentation</a> for details and examples. </br>'+
                    'In addition to the Global Variables that you manually define, Scalr provides <a href="https://scalr-wiki.atlassian.net/wiki/x/MYBM" target="_blank">System Global Variables</a>, which include information regarding the Server the Script is currently executing on.</br>'+
                    'If the Script is used in an Orchestration Rule, System Global Variables will also include information regarding the Server that fired the event that triggered this Orchestration Rule.</br>'+
                    'You can also use <a href="https://scalr-wiki.atlassian.net/wiki/x/9YAs" target="_blank">Scripting Parameters</a> in your Scripts to make them configurable in the Farm Designer.</br>'
            }]
        }, {
            xtype: 'fieldset',
            labelWidth: 130,
            cls: 'x-fieldset-separator-none',
            items: [{
                xtype: 'container',
                layout: 'hbox',
                margin: '0 0 12 0',
                items: [{
                    xtype: 'component',
                    cls: 'x-fieldset-header-text',
                    html: 'Script'
                }, {
                    xtype: 'component',
                    flex: 1
                }, {
                    xtype: 'button',
                    text: 'Upload',
                    width: 80,
                    margin: '0 0 0 12',
                    hidden: !Scalr.flags['betaMode'],
                    enableToggle: true,
                    toggleHandler: function (el, state) {
                        form.down('[name="content"]')[state ? 'hide' : 'show']();
                        form.down('#upload')[!state ? 'hide' : 'show']();
                        form.down('[name="uploadType"]').setDisabled(!state);
                    }
                }]
            }, {
                xtype: 'displayfield',
                cls: 'x-form-field-warning',
                value: 'First line must contain shebang (#!/path/to/interpreter)'
            }, {
                xtype: 'codemirror',
                minHeight: 300,
                name: 'content',
                hideLabel: true,
                addResizeable: true
            }, {
                xtype: 'container',
                layout: 'hbox',
                itemId: 'upload',
                hidden: true,
                items: [{
                    xtype: 'combo',
                    fieldLabel: 'Upload script',
                    store: [ 'URL', 'File' ],
                    value: 'URL',
                    editable: false,
                    disabled: true,
                    name: 'uploadType',
                    width: 200,
                    listeners: {
                        change: function (field, value) {
                            this.next('[name="uploadUrl"]')[ value == 'URL' ? 'show' : 'hide']();
                            this.next('[name="uploadFile"]')[ value == 'File' ? 'show' : 'hide']();
                        }
                    }
                }, {
                    xtype: 'textfield',
                    flex: 1,
                    margin: '0 0 0 12',
                    name: 'uploadUrl',
                    emptyText: 'http://domain.com/file'
                }, {
                    xtype: 'filefield',
                    margin: '0 0 0 12',
                    name: 'uploadFile',
                    hidden: true,
                    flex: 1
                }]
            }]
        }, {
            xtype: 'hidden',
            name: 'id'
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
                xtype: 'splitbutton',
                text: 'Save',
                hidden: !moduleParams['script'],
                handler: function () {
                    saveHandler(true);
                },
                menu: [{
                    text: 'Save changes as new version (' + (moduleParams['script'] ? (parseInt(moduleParams['script']['version']) + 1) : '') + ')',
                    hidden: !moduleParams['script'],
                    handler: function () {
                        saveHandler(true);
                    }
                }, {
                    text: 'Save changes as new version (' + (moduleParams['script'] ? (parseInt(moduleParams['script']['version']) + 1) : '') + ') and execute script',
                    hidden: !moduleParams['script'],
                    handler: function () {
                        saveHandler(true, true);
                    }
                }, {
                    xtype: 'menuseparator'
                }, {
                    text: 'Save changes in current version',
                    hidden: !moduleParams['script'],
                    handler: function () {
                        saveHandler(false);
                    }
                }, {
                    text: 'Save changes in current version and execute script',
                    hidden: !moduleParams['script'],
                    handler: function () {
                        saveHandler(false, true);
                    }
                }]
            }, {
                xtype: 'button',
                text: 'Create',
                hidden: !!moduleParams['script'],
                handler: function () {
                    saveHandler(true);
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

    if (moduleParams['script'])
        form.getForm().setValues(moduleParams['script']);

    return form;
});

Scalr.regPage('Scalr.ui.scripts.view', function (loadParams, moduleParams) {

    var globalVariablesInfo =

        'You can access <a href="https://scalr-wiki.atlassian.net/wiki/x/hiIb" '
        + 'target="_blank">Global Variables</a> as Environment Variables in your Scripts. '
        + 'Visit <a href="https://scalr-wiki.atlassian.net/wiki/x/7R4b" target="_blank">the documentation</a> '
        + 'for details and examples.</br>In addition to the Global Variables that you manually define, '
        + 'Scalr provides <a href="https://scalr-wiki.atlassian.net/wiki/x/MYBM" target="_blank">'
        + 'System Global Variables</a>, which include information regarding the Server the Script is '
        + 'currently executing on.</br>If the Script is used in an Orchestration Rule, '
        + 'System Global Variables will also include information regarding the Server that fired the event '
        + 'that triggered this Orchestration Rule.';

    var editor = {

        layout: 'fit',
        height: '80%',
        width: '80%',
        scrollable: false,

        codeMirrorConfig: {
            scriptContent: '',
            readOnly: false
        },

        listeners: {
            beforeclose: function (panel) {
                form.applyScriptContent(
                    panel.down('codemirror').getValue()
                );
            }
        },

        items: [{
            xtype: 'codemirror',
            hideLabel: true,
            validator: function (value) {
                return value.substring(0, 2) !== '#!'
                    ? 'First line must contain shebang (#!/path/to/interpreter)'
                    : true;
            },
            listeners: {
                boxready: function (field) {
                    var config = field.up().codeMirrorConfig;

                    field.codeMirror.setValue(
                        config.scriptContent
                    );

                    field.setReadOnly(config.readOnly);
                }
            }
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
                text: 'Close',
                maxWidth: 140,
                handler: function (button) {
                    button.up('panel').close();
                }
            }]
        }]
    };

	var store = Ext.create('Scalr.ui.ContinuousStore', {

        fields: [
			{ name: 'id', type: 'int' },
            { name: 'accountId', type: 'int' },
			'name', 'description', 'version', 'isSync', 'os', 'envId'
		],

        proxy: {
            type: 'ajax',
            url: '/scripts/xList',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },

        removeByScriptId: function (ids) {
            store.remove(Ext.Array.map(
                ids, function (id) {
                    return store.getById(id);
                }
            ));
        }
	});

	var grid =  Ext.create('Ext.grid.Panel', {

        cls: 'x-panel-column-left',
        flex: .8,
        minWidth: 660,
        scrollable: true,
		store: store,

        plugins: [ 'applyparams', 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }, {
            ptype: 'continuousrenderer'
        }],

		viewConfig: {
			emptyText: 'No scripts found'
		},

        selModel: {
            selType: 'selectedmodel',
            getVisibility: function (record) {
                return Scalr.scope === record.get('scope');
            }
        },

        listeners: {
            selectionchange: function(selModel, selections) {
                var toolbar = this.down('toolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
            }
        },

        applyScript: function (script) {
            var me = this;

            var record = me.getSelectedRecord();
            var store = me.getStore();

            if (Ext.isEmpty(record) || record.getId() != script.id) {
                record = store.add(script)[0];
            } else {
                record.set(script);
                me.clearSelectedRecord();
            }

            me.view.focusRow(record);

            return me;
        },

        deleteScript: function (id, name) {

            var isDeleteMultiple = Ext.typeOf(id) === 'array';

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Delete script <b>' + name + '</b> ?'
                        : 'Delete selected script(s): %s ?',
                    objects: isDeleteMultiple ? name : null
                },
                processBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Deleting <b>' + name + '</b> ...'
                        : 'Deleting selected script(s) ...'
                },
                url: '/scripts/xRemove',
                params: {
                    scriptId: Ext.encode(
                        !isDeleteMultiple ? [id] : id
                    )
                },
                success: function (response) {
                    var deletedScriptsIds = response.processed;

                    if (!Ext.isEmpty(deletedScriptsIds)) {
                        store.removeByScriptId(deletedScriptsIds);
                    }

                }
            });
        },

        deleteSelectedScript: function () {
            var me = this;

            var record = me.getSelectedRecord();

            me.deleteScript(
                record.get('id'),
                record.get('name')
            );

            return me;
        },

        deleteSelectedScripts: function () {
            var me = this;

            var ids = [];
            var names = [];

            Ext.Array.each(
                me.getSelectionModel().getSelection(),

                function (record) {
                    ids.push(record.get('id'));
                    names.push(record.get('name'));
                }
            );

            me.deleteScript(ids, names);

            return me;
        },

		columns: [
			{ header: 'ID', width: 80, dataIndex: 'id', sortable: true },
			{
                text: 'Script',
                flex: 1,
                dataIndex: 'name',
                sortable: true,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getScope(values.scope)]}&nbsp;&nbsp;{name}',
                    {
                        getScope: function(scope){
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('script') + '"/>';
                        }
                    }
                )
            },
            //{ header: 'Execution mode', width: 150, dataIndex: 'isSync', sortable: false, resizable: false, xtype: 'statuscolumn', statustype: 'script'},
			{ header: 'Version', width: 80, dataIndex: 'version', sortable: false, resizable: false, align:'center' },
			{ header: 'OS', width: 60, sortable: false, align:'center', xtype: 'templatecolumn', tpl:
				'<tpl if="os == &quot;linux&quot;"><img src="/ui2/images/ui/scripts/linux.png" height="15" title="Linux"></tpl>' +
				'<tpl if="os == &quot;windows&quot;"><img src="/ui2/images/ui/scripts/windows.png" height="15" title="Windows"></tpl>'
            }, {
				xtype: 'optionscolumn',
                hidden: !(Scalr.scope === 'environment' && Scalr.isAllowed('ADMINISTRATION_SCRIPTS', 'execute')),
				menu: [{
					iconCls: 'x-menu-icon-execute',
					text: 'Execute',
                    showAsQuickAction: true,
					href: '#/scripts/{id}/execute'
				}]
			}
		],

		dockedItems: [{
			xtype: 'toolbar',
			store: store,
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12'
            },
			items: [{
				xtype: 'filterfield',
				store: store,
                margin: 0
			}, ' ', {
                xtype: 'cyclealt',
                name: 'scope',
                getItemIconCls: false,
                hidden: Scalr.user.type === 'ScalrAdmin',
                width: 130,
                margin: 0,
                changeHandler: function (me, menuItem) {
                    store.applyProxyParams({
                        scope: menuItem.value
                    });
                },
                getItemText: function (item) {
                    return item.value
                        ? 'Scope: &nbsp;<img src="'
                            + Ext.BLANK_IMAGE_URL
                            + '" class="' + item.iconCls
                            + '" title="' + item.text + '" />'
                        : item.text;
                },
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: [{
                        text: 'All scopes',
                        value: null
                    }, {
                        text: 'Scalr scope',
                        value: 'scalr',
                        iconCls: 'scalr-scope-scalr'
                    }, {
                        text: 'Account scope',
                        value: 'account',
                        iconCls: 'scalr-scope-account'
                    }, {
                        text: 'Environment scope',
                        value: 'environment',
                        iconCls: 'scalr-scope-environment',
                        hidden: Scalr.scope !== 'environment'
                    }]
                }
			}, {
                xtype: 'tbfill'
            }, {
                text: 'New script',
                itemId: 'add',
                cls: 'x-btn-green',
                enableToggle: true,
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();

                        form.down('#details').setTitle('New script');

                        form.
                            toggleScopeInfo(false).
                            setFormReadOnly(false).
                            hideForkButton(true).
                            hideDeleteButton(true).
                            setDeleteTooltip('').
                            hideCreateButton(false).
                            hideSaveButton(true).
                            hideScriptVersion(true, true).
                            show();

                        return;
                    }

                    form.hide();
                }
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.clearAndLoad();
                    grid.down('#add').toggle(false, true);
                }
            }, {
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more scripts to delete them',
                disabled: true,
                handler: function() {
                    grid.deleteSelectedScripts();
                }
            }]
		}]
	});

    var form = Ext.create('Ext.form.Panel', {

        hidden: true,

        autoScroll: true,

        scope: Scalr.scope,

        allowFork: Scalr.user.type === 'ScalrAdmin'
            || Scalr.isAllowed('ADMINISTRATION_SCRIPTS', 'fork'),

        isScriptEditable: function (scriptScope) {
            return scriptScope === this.scope;
        },

        maximizeEditor: function () {
            var me = this;

            var codeMirror = me.down('[name=content]');

            Ext.apply(editor.codeMirrorConfig, {
                scriptContent: codeMirror.getValue(),
                readOnly: codeMirror.readOnly
            });

            Scalr.utils.Window(editor);

            return me;
        },

        hideForkButton: function (hidden, disabled) {
            var me = this;

            me.down('#fork').
                setVisible(!hidden).
                setDisabled(disabled);

            return me;
        },

        hideDeleteButton: function (hidden, disabled) {
            var me = this;

            me.down('#delete').
                setVisible(!hidden).
                setDisabled(disabled);

            return me;
        },

        setDeleteTooltip: function (tooltip) {
            var me = this;

            me.down('#delete').setTooltip(tooltip);

            return me;
        },

        hideCreateButton: function (hidden) {
            var me = this;

            me.down('#create').
                setVisible(!hidden);

            return me;
        },

        hideSaveButton: function (hidden) {
            var me = this;

            me.down('#save').
                setVisible(!hidden);

            return me;
        },

        disableSaveButton: function (disabled, scope) {
            var me = this;

            me.down('#save').
                setTooltip(disabled
                    ? Scalr.utils.getForbiddenActionTip('script', scope)
                    : '').
                setDisabled(disabled);

            return me;
        },

        hideScriptVersion: function (hidden, hideButton) {
            var me = this;

            me.down('[name=version]').
                setVisible(!hidden);

            if (hideButton) {
                me.down('#removeVersion').hide();
            }

            return me;
        },

        getScriptData: function (scriptId, readOnly) {
            var me = this;

            Scalr.Request({
                processBox: {
                    type: 'load'
                },
                url: '/scripts/xGet',
                params: {
                    scriptId: scriptId
                },
                success: function (response) {
                    var scriptData = response.script;

                    if (scriptData) {
                        me.applyScriptData(scriptData, readOnly);
                    }
                }
            });

            return me;
        },

        applyScriptVersions: function (versions, latestVersion, readOnly) {
            var me = this;

            me.down('[name=version]').
                getStore()
                    .loadData(versions);

            me.down('#removeVersion').
                show().
                setDisabled(!!readOnly || versions.length < 2);

            me.down('#save').
                setLatestVersion(latestVersion);

            return me;
        },

        applyScriptContent: function (content) {
            var me = this;

            me.down('[name=content]').
                codeMirror.setValue(content);

            return me;
        },

        applyScriptTags: function (tags) {
            var me = this;

            me.down('[name=tags]').setValue(
                tags.split(',')
            );

            return me;
        },

        applyScriptData: function (data, readOnly) {
            var me = this;

            me.
                applyScriptVersions(data.versions, data.version, readOnly).
                applyScriptTags(data.tags).
                getRecord().
                    set(data);

            return me;
        },

        saveScript: function (version) {
            var me = this;

            var baseForm = me.getForm();

            if (baseForm.isValid()) {
                var request = function (checkScriptParameters) {
                    Scalr.Request({
                        processBox: {
                            type: 'save'
                        },
                        url: '/scripts/xSave',
                        form: baseForm,
                        hideErrorMessage: true,
                        params: {
                            version: Ext.isDefined(version)
                                ? version : null,
                            envId: Scalr.user.envId,
                            checkScriptParameters: checkScriptParameters
                        },
                        success: function (response) {

                            var script = response.script;

                            if (!Ext.isEmpty(script)) {
                                grid.applyScript(script);
                            }
                        },
                        failure: function (response) {
                            if (response.showScriptParametersConfirmation) {
                                Scalr.Confirm({
                                    msg: 'It looks like you might be using Script Parameters in this Script. If that is the case, enable Script Parameter Interpolation.',
                                    type: 'action',
                                    formWidth: 460,
                                    ok: 'Ignore & Save',
                                    closeOnSuccess: false,
                                    success: function () {
                                        request(false);
                                    }
                                });
                            } else if (response.errorMessage) {
                                Scalr.message.Error(response.errorMessage);
                            }
                        }

                    });
                };
                request(true);
            }

            return me;
        },

        deleteScriptVersion: function () {
            var me = this;

            var record = me.getRecord();
            var version = me.down('[name=version]').getValue();

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: 'Are you sure want to delete selected version? This cannot be undone.'
                },
                processBox: {
                    type: 'delete'
                },
                url: '/scripts/' + record.get('id') + '/xRemoveVersion',
                params: {
                    version: version
                },
                success: function (response) {
                    var versions = response.script.versions;
                    var latestVersion = response.script.version;

                    record.set({
                        version: latestVersion,
                        versions: versions
                    });

                    me.loadRecord(me.getRecord());
                }
            });
        },

        forkScript: function () {
            var me = this;

            var record = me.getRecord();
            var name = record.get('name');

            Scalr.Request({
                confirmBox: {
                    formValidate: true,
                    formSimple: true,
                    form: [{
                        xtype: 'textfield',
                        name: 'name',
                        fieldLabel: 'New script name',
                        labelAlign: 'top',
                        labelWidth: 110,
                        allowBlank: false,
                        value: 'Custom ' + name
                    }],
                    type: 'action',
                    msg: 'Are you sure want to fork script "' + name + '" ?'
                },
                processBox: {
                    type: 'action'
                },
                url: '/scripts/xFork',
                params: {
                    scriptId: record.get('id')
                },
                success: function (response) {

                    var script = response.script;

                    if (!Ext.isEmpty(script)) {
                        grid.applyScript(script);
                    }
                },
            });

            return me;
        },

        toggleScopeInfo: function(record) {
            var me = this,
                scopeInfoField = me.down('#scopeInfo');
            if (record && !me.isScriptEditable(record.get('scope'))) {
                scopeInfoField.setValue(Scalr.utils.getScopeInfo('script', record.get('scope'), record.get('id')));
                scopeInfoField.show();
            } else {
                scopeInfoField.hide();
            }
            return me;
        },

        // temporary solution
        setFormReadOnly: function (readOnly) {
            var me = this;

            me.down('[name=name]').setReadOnly(readOnly);
            me.down('[name=description]').setReadOnly(readOnly);
            me.down('[name=tags]').setReadOnly(readOnly);
            me.down('[name=timeout]').setReadOnly(readOnly);

            Ext.Array.each(
                me.down('[name=isSync]').query(), function (button) {
                    button.setDisabled(readOnly);
                }
            );

            Ext.Array.each(
                me.down('#scriptSourceTabs').query(), function (button) {
                    button.setDisabled(readOnly);
                }
            );

            me.down('[name=content]').setReadOnly(readOnly);
            me.down('[name=allowScriptParameters]').setReadOnly(readOnly);

            return me;
        },

        setFormEditable: function (scope) {
            var me = this;

            var readOnly = !me.isScriptEditable(scope);

            me.
                hideForkButton(false, !me.allowFork).
                hideDeleteButton(false, readOnly).
                setDeleteTooltip(readOnly ? Scalr.utils.getForbiddenActionTip('script', scope) : '').
                hideCreateButton(true).
                disableSaveButton(readOnly, scope).
                hideSaveButton(false).
                hideScriptVersion(false).
                setFormReadOnly(readOnly);

            return me;
        },

        listeners: {
            show: function (form) {
                form.down('field[xtype!=hidden]').focus();
            },
            afterloadrecord: function (record) {
                var me = this;

                var scope = record.get('scope');
                var readOnly = !me.isScriptEditable(scope);
                var versions = record.get('versions');

                if (!Ext.isDefined(versions)) {
                    me.getScriptData(record.get('id'), readOnly);
                } else {
                    me.
                        applyScriptVersions(versions, record.get('version'), readOnly).
                        applyScriptTags(record.get('tags'));
                }

                me.setFormEditable(scope);

                grid.down('#add').toggle(false, true);
                form.down('#details').setTitle('Edit script');
                form.toggleScopeInfo(record);
            }
        },

        items: [{
            xtype: 'displayfield',
            itemId: 'scopeInfo',
            cls: 'x-form-field-info x-form-field-info-fit',
            anchor: '100%',
            hidden: true
        },{
            xtype: 'fieldset',
            itemId: 'details',
            title: 'Edit script',
            collapsible: true,
            stateful: true,
            stateId: 'fieldset-scripts-details',
            fieldDefaults: {
                anchor: '100%'
            },
            items: [{
                xtype: 'hidden',
                name: 'id'
            }, {
                xtype: 'textfield',
                name: 'name',
                fieldLabel: 'Name',
                allowBlank: false
            }, {
                xtype: 'textfield',
                name: 'description',
                fieldLabel: 'Description'
            }, {
                xtype: 'scalrtagfield',
                name: 'tags',
                fieldLabel: 'Tags',
                saveTagsOn: 'submit'
            }]
        }, {
            xtype: 'fieldset',
            collapsible: true,
            collapsed: true,
            title: 'Script execution options',
            defaults: {
                labelWidth: 130
            },
            items: [{
                xtype: 'buttongroupfield',
                fieldLabel: 'Execution mode',
                editable: false,
                name: 'isSync',
                value: 1,
                defaults: {
                    width: 130
                },
                items: [{
                    text: 'Blocking',
                    value: 1
                },{
                    text: 'Non-blocking',
                    value: 0
                }],
                setDefaultTimeout: function (value) {
                    var me = this;

                    var timeoutField = me.next('[name=timeout]');

                    timeoutField.emptyText = moduleParams['timeouts'][
                        value ? 'sync' : 'async'
                    ];

                    timeoutField.applyEmptyText();
                },
                listeners: {
                    afterlayout: function (buttonGroup) {
                        buttonGroup.setDefaultTimeout(
                            buttonGroup.getValue()
                        );
                    },
                    change: function (buttonGroup, value) {
                        buttonGroup.setDefaultTimeout(value);
                    }
                }
            }, {
                xtype: 'numberfield',
                name: 'timeout',
                fieldLabel: 'Timeout',
                width: 266,
                minValue: 0,
                step: 10
            }]
        }, {
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',

            defaults: {
                anchor: '100%'
            },

            hideFields: function (visibleFieldName) {
                var me = this;

                var isSourceRemote = visibleFieldName !== 'content';

                me.down('#maximizeEditor').
                    setDisabled(isSourceRemote);

                Ext.Array.each(
                    me.down('#scriptSource').query(),
                    function (field) {
                        var fieldName = field.getName();

                        if (fieldName === 'uploadType') {
                            var uploadType = isSourceRemote
                                ? (visibleFieldName === 'uploadFile' ? 'File' : 'URL' )
                                : '';

                            field.
                                setValue(uploadType).
                                setDisabled(!isSourceRemote);

                            return;
                        }

                        var isVisible = fieldName === visibleFieldName;

                        field.
                            setVisible(isVisible).
                            setDisabled(!isVisible);
                    }
                );

                return me;
            },

            items: [{
                xtype: 'fieldcontainer',
                name: 'toolbar',
                layout: 'hbox',
                margin: '0 0 12 0',
                items: [{
                    xtype: 'component',
                    html:
                        '<div style="padding: 0 0 0 32px; margin-bottom: 0" class="x-fieldset-subheader">'
                            + '<span>Script content</span>'
                            + '<img style="margin-left: 6px" src="'
                            + Ext.BLANK_IMAGE_URL
                            + '" class="x-icon-info" data-qclickable="1" data-qtip=\''
                            + globalVariablesInfo
                            + '\' />'
                        + '</div>'
                }, {
                    xtype: 'tbfill'
                }, {
                    xtype: 'combo',
                    name: 'version',
                    fieldLabel: 'Version',
                    labelWidth: 60,
                    editable: false,
                    queryMode: 'local',
                    submitValue: false,
                    store: {
                        proxy: 'object',
                        fields: [ 'version', 'content' ],
                        listeners: {
                            datachanged: function (store) {
                                var versionCombo = form.down('[name=version]');
                                var record = store.findRecord(
                                    'version', versionCombo.getValue()
                                );

                                if (!Ext.isEmpty(record)) {
                                    form.applyScriptContent(
                                        record.get('content')
                                    );

                                    versionCombo.select(record);
                                }
                            }
                        }
                    },
                    displayField: 'version',
                    valueField: 'version',
                    width: 120,
                    listeners: {
                        change: function (combo, value) {
                            if (!Ext.isEmpty(value)) {
                                form.down('#save').
                                    setCurrentVersion(value);
                            }
                        },
                        select: function (combo, record) {
                            form.applyScriptContent(
                                record.get('content')
                            );
                        }
                    }
                }, {
                    xtype: 'button',
                    itemId: 'removeVersion',
                    cls: 'x-btn-red',
                    iconCls: 'x-btn-icon-delete',
                    margin: '0 0 0 12',
                    tooltip: 'Delete selected version',
                    handler: function () {
                        form.deleteScriptVersion();
                    }
                }, {
                    xtype: 'tbfill'
                }, {
                    xtype: 'buttongroupfield',
                    itemId: 'scriptSourceTabs',
                    editable: false,
                    submitValue: false,
                    value: 'content',
                    items: [{
                        iconCls: 'x-btn-icon-edit',
                        tooltip: 'Script editor',
                        value: 'content'
                    }, {
                        iconCls: 'x-btn-icon-web',
                        tooltip: 'Attach script from web',
                        value: 'uploadUrl'
                    }, {
                        iconCls: 'x-btn-icon-upload',
                        tooltip: 'Upload script',
                        value: 'uploadFile'
                    }],
                    listeners: {
                        change: function (buttonGroup, state) {
                            buttonGroup.up('fieldset').hideFields(state);
                        }
                    }
                }, {
                    xtype: 'button',
                    itemId: 'maximizeEditor',
                    iconCls: 'x-btn-icon-maximize',
                    margin: '0 0 0 12',
                    tooltip: 'Maximize editor',
                    handler: function () {
                        form.maximizeEditor();
                    }
                }]
            }, {
                xtype: 'checkbox',
                name: 'allowScriptParameters',
                boxLabel: 'Enable Script Parameter Interpolation',
                plugins: {
                    ptype: 'fieldicons',
                    icons: [{id: 'info', tooltip: 'Visit the documentation on <a target="_blank" href="https://scalr-wiki.atlassian.net/wiki/x/QBUb">Script Parameters</a> for more details'}]
                }
            }, {
                xtype: 'fieldcontainer',
                itemId: 'scriptSource',
                defaults: {
                    width: '100%'
                },
                items: [{
                    xtype: 'hiddenfield',
                    name: 'uploadType',
                    value: ''
                }, {
                    xtype: 'textfield',
                    name: 'uploadUrl',
                    emptyText: 'http://domain.com/file',
                    hidden: true
                }, {
                    xtype: 'filefield',
                    name: 'uploadFile',
                    emptyText: 'Select script to upload',
                    hidden: true
                }, {
                    xtype: 'codemirror',
                    name: 'content',
                    minHeight: 300,
                    hideLabel: true,
                    validator: function (value) {
                        return value.substring(0, 2) !== '#!'
                            ? 'First line must contain shebang (#!/path/to/interpreter)'
                            : true;
                    }
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
            maxWidth: 1100,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [{
                xtype: 'button',
                itemId: 'create',
                text: 'Create',
                handler: function () {
                    form.saveScript();
                }
            }, {
                xtype: 'splitbutton',
                itemId: 'save',
                text: 'Save',
                margin: 0,
                listeners: {
                    menushow: function (button, menu) {
                        var saveAsNewButton = menu.down('#saveAsNew');

                        saveAsNewButton.setDisabled(
                            form.down('[name=content]').getValue()
                            === form.down('[name=version]').
                                    getStore().
                                        findRecord('version', saveAsNewButton.version).
                                            get('content')

                        );
                    }
                },
                setCurrentVersion: function (version) {
                    var me = this;

                    me.getMenu().down('#saveAsCurrent').
                        version = version;

                    return me;
                },
                setLatestVersion: function (version) {
                    var me = this;

                    me.getMenu().down('#saveAsNew').
                        updateText(version).
                        version = version;

                    return me;
                },
                handler: function () {
                    form.saveScript();
                },
                menu: [{
                    itemId: 'saveAsNew',
                    text: 'Save changes as new version',
                    updateText: function (version) {
                        var me = this;

                        me.setText(
                            'Save changes as new version ('
                            + ++version
                            + ')'
                        );

                        return me;
                    },
                    handler: function () {
                        form.saveScript();
                    }
                }, {
                    xtype: 'menuseparator'
                }, {
                    itemId: 'saveAsCurrent',
                    text: 'Save changes in current version',
                    handler: function (button) {
                        form.saveScript(button.version);
                    }
                }]
            }, {
                xtype: 'button',
                itemId: 'fork',
                text: 'Fork',
                handler: function () {
                    form.forkScript();
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function () {
                    grid.clearSelectedRecord();
                    grid.down('#add').toggle(false, true);
                }
            }, {
                xtype: 'button',
                itemId: 'delete',
                cls: 'x-btn-red',
                text: 'Delete',
                handler: function () {
                    grid.deleteSelectedScript();
                }
            }]
        }]
    });

    return Ext.create('Ext.panel.Panel', {

        stateful: true,
        stateId: 'grid-scripts-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Scripts',
            menuHref: '#' + Scalr.utils.getUrlPrefix() +  '/scripts',
            menuFavorite: true
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: 1,
            minWidth: 600,
            layout: 'fit',
            items: [ form ]
        }]
    });
});

/*
Ext.define('Scalr.ui.ScriptManager', {

    singleton: true,

    forkAction: '/scripts/xFork',

    removeAction: '/scripts/xRemove',

    _getForkConfirm: function (name) {
        return {
            formValidate: true,
            formSimple: true,
            type: 'action',
            msg: 'Are you sure want to fork script "'
                + name + '" ?',
            form: {
                xtype: 'textfield',
                name: 'name',
                fieldLabel: 'New script name',
                labelAlign: 'top',
                labelWidth: 110,
                allowBlank: false,
                value: 'Custom ' + name
            }
        };
    },

    _getRemoveConfirm: function (name) {
        return {
            type: 'delete',
            msg: 'Delete script <b>' + name + '</b> ?'
        };
    },

    fork: function (id, name, callback) {
        var me = this;

        Scalr.Request({
            url: me.forkAction,
            confirmBox: me._getForkConfirm(
                Ext.isString(name) ? name : ''
            ),
            processBox: {
                type: 'action'
            },
            params: {
                scriptId: Ext.isNumber(id)
                    ? id
                    : parseInt(id)
            },
            success: function () {
                if (Ext.isFunction(callback)) {
                    callback();
                }
            }
        });

        return me;
    },

    remove: function (id, name, callback) {
        var me = this;

        Scalr.Request({
            url: me.removeAction,
            confirmBox: me._getRemoveConfirm(
                Ext.isString(name) ? name : ''
            ),
            processBox: {
                type: 'delete'
            },
            params: {
                scriptId: Ext.encode([id])
            },
            success: function () {
                if (Ext.isFunction(callback)) {
                    callback();
                }
            }
        });

        return me;
    }
});
*/

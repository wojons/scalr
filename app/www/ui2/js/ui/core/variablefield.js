Ext.define('Scalr.ui.VariableField', {
    extend: 'Ext.container.Container',

    mixins: {
        field: 'Ext.form.field.Field'
    },

    alias: 'widget.variablefield',

    cls: 'scalr-ui-variablefield',

    currentScope: 'env',

    encodeParams: true,

    layout: {
        type: 'hbox',
        align: 'stretch'
    },

    scopes: {
        scalr: 'Scalr',
        account: 'Account',
        env: 'Environment',
        role: 'Role',
        farm: 'Farm',
        farmrole: 'Farm Role'
    },

    preserveScrollOnRefresh: true,

    dirty: false,

    initComponent : function() {
        var me = this;

        me.callParent();

        me.initField();

        me.addEvents('beforeselect', 'select', 'datachanged', 'addvariable', 'removevariable', 'load');

        me.on({
            beforeselect: me.beforeSelect,
            select: me.onSelect,
            datachanged: me.onDataChanged,
            removevariable: me.onVariableRemove,
            scope: me
        });

        me.setTitle(me.title);

        if (me.removeTopSeparator) {
            Ext.apply(me.down('[name=title]'), {
                style: 'box-shadow: none'
            });
        }
    },

    beforeSelect: function (me) {
        me.saveScrollState();
    },

    onSelect: function (me) {
        me.restoreScrollState();
    },

    onDataChanged: function (me) {
        me.restoreScrollState();

        if (!me.isDirty()) {
            me.markDirty();
        }
    },

    onVariableRemove: function (me) {
        me.restoreScrollState();
    },

    saveScrollState: function () {
        var me = this;

        if (me.preserveScrollOnRefresh) {
            me.down('grid').getView().saveScrollState();
        }

        return me;
    },

    restoreScrollState: function () {
        var me = this;

        if (me.preserveScrollOnRefresh) {
            me.down('grid').getView().restoreScrollState();
        }

        return me;
    },

    isValid: function () {
        var me = this;

        var grid = me.down('grid');
        var isValid = true;

        Ext.Array.each(grid.getStore().data.items, function (record) {
            if (record.get('validationErrors').length) {
                isValid = false;

                var selectionModel = grid.getSelectionModel();
                selectionModel.deselectAll();
                selectionModel.select(record);

                return false;
            }
        });

        return isValid;
    },

    isDirty: function () {
        var me = this;

        return me.dirty;
    },

    markDirty: function () {
        var me = this;

        me.dirty = true;

        return me;
    },

    setTitle: function (title) {
        var me = this;

        if (title) {
            me.down('[name=title]').setText(title);
        }

        return me;
    },

    getValue: function (includeLockedVariables) {
        var me = this;

        var store = me.down('grid').getStore();
        var records = store.snapshot || store.data;
        var variables = [];

        Ext.Array.each(records.items, function (record) {
            var locked = record.get('locked');

            if (includeLockedVariables || !(locked && parseInt(locked.flagFinal))) {
                variables.push({
                    name: record.get('name'),
                    'default': record.get('default'),
                    locked: locked,
                    current: record.get('current'),
                    flagDelete: record.get('flagDelete'),
                    scopes: record.get('scopes')
                });
            }
        });

        return me.encodeParams ? Ext.encode(variables) : variables;
    },

    setValue: function (value) {
        var me = this;

        value = (me.encodeParams ? Ext.decode(value, true) : value) || [];

        me.down('grid').getStore().loadData(value);

        me.markRequiredVariables();

        me.fireEvent('load', me, value);

        return me;
    },

    markInvalid: function (errors) {
        var me = this;

        var variableNames = Ext.Object.getKeys(errors);
        var grid = me.down('grid');
        var store = grid.getStore();
        var firstRecord = null;

        Ext.Array.each(variableNames, function (name) {
            var record = store.findRecord('name', name);
            record.set('serverErrors', Ext.Object.getValues(errors[name]));

            firstRecord = firstRecord || record;
        });

        me.fireEvent('beforeselect', me);

        var selectionModel = grid.getSelectionModel();
        selectionModel.deselectAll();
        selectionModel.select(firstRecord);

        return me;
    },

    addVariable: function () {
        var me = this;

        var grid = me.down('grid');
        var view = grid.getView();
        var store = grid.getStore();
        var extendedForm = me.down('[name=extendedForm]');
        var newVariable = me.newVariable;
        var isNewVariableExist = !(!newVariable ||
            (newVariable.get('name') && newVariable.get('validationErrors').indexOf('name') === -1));

        var record = isNewVariableExist ? me.newVariable : store.add({
            current: {
                scope: me.currentScope
            },
            scopes: [ me.currentScope ],
            validationErrors: []
        })[0];

        me.fireEvent('beforeselect', me);

        grid.getSelectionModel().select(record, false, true);

        view.focusRow(record);
        view.scrollBy(0, grid.getHeight());
        view.saveScrollState();

        extendedForm.setValue(record);

        me.newVariable = record;

        return me;
    },

    removeVariable: function (record) {
        var me = this;

        var store = record.store;
        var extendedForm = me.down('[name=extendedForm]');

        if (!record.get('name')) {
            store.remove(record);
            me.newVariable = null;
        } else {
            record.set('flagDelete', 1);
            record.commit();

            store.filter();
        }

        extendedForm.variable = null;
        extendedForm.setFormVisible(false);

        me.fireEvent('removevariable', me, record);
    },

    getNames: function () {
        var me = this;

        var names = [];

        Ext.Array.each(me.down('grid').getStore().data.items, function (record) {
            if (!record.get('flagDelete') && record.get('validationErrors').indexOf('name') === -1) {
                names.push(record.get('name'));
            }
        });

        return names;
    },

    getScopeName: function (scopeId) {
        var me = this;

        return me.scopes[scopeId] || null;
    },

    showExtendedForm: function (isVisible) {
        var me = this;

        var extendedForm = me.down('[name=extendedForm]');

        if (!isVisible) {
            extendedForm.variable = null;
        }

        extendedForm.setFormVisible(isVisible);

        return me;
    },

    isVariableRequired: function (record) {
        var me = this;

        return !record.get('current').value &&
            !record.get('default').value &&
            record.get('locked').flagRequired === me.currentScope;
    },

    markRequiredVariables: function () {
        var me = this;

        var store = me.down('grid').getStore();

        Ext.Array.each(store.data.items, function (record) {
            if (me.isVariableRequired(record)) {
                record.set('validationErrors', ['value']);
            }
        });

        return me;
    },

    items: [{
        xtype: 'container',
        flex: 1,
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        items: [{
            name: 'title',
            cls: 'x-panel-column-left',
            padding: '1 0 0 12',
            setText: function (text) {
                var me = this;

                me.update('<div style="padding-top: 9px" class="x-fieldset-header-text">' + text + '</div>');

                return me;
            }
        }, {
            xtype: 'grid',
            cls: 'x-grid-shadow x-panel-column-left scalr-ui-variablefield-grid',
            style: 'box-shadow: none',
            padding: '1 0 12 0',
            flex: 1,

            viewConfig: {
                emptyText: 'No variables'
            },

            store: {
                fields: [ 'name', 'newValue', 'value', 'current', 'default', 'locked', 'flagDelete', 'scopes', 'validationErrors', 'serverErrors' ],
                reader: 'object',
                filters: [{
                    id: 'deletedVariableFilter',
                    filterFn: function (record) {
                        return !record.get('flagDelete');
                    }
                }],
                listeners: {
                    update: function (me, record, operation, modifiedFieldNames) {
                        if (modifiedFieldNames && modifiedFieldNames[0] === 'flagDelete') {
                            // todo: variable names event based updating
                        }
                    }
                }
            },

            plugins: {
                ptype: 'focusedrowpointer',
                addOffset: 3
            },

            listeners: {
                select: function (me, record) {
                    var grid = me.view.panel;

                    var variableField = grid.up('variablefield');
                    variableField.fireEvent('beforeselect', variableField);

                    grid.up('variablefield').down('[name=extendedForm]').setValue(record);
                },
                itemkeydown: function (me, record, item, index, e) {
                    var variableField = me.up('variablefield');

                    if (e.getKey() === e.TAB && !variableField.down('[name=newName]').isVisible()) {
                        variableField.down('[name=flagHidden]').focus();
                    }
                }
            },

            getScopeMarkerHtml: function (scope) {
                var me = this;

                return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-' + scope +
                    '" data-qtip="' + me.up('variablefield').getScopeName(scope) + '" />';
            },

            getEmptyNameHtml: function (scope) {
                var me = this;

                return me.getScopeMarkerHtml(scope) +
                    '<span style="font-style: italic; color: #999; padding-left: 10px">no name</span>';
            },

            getLockedNameHtml: function (name, scope) {
                var me = this;

                return '<div data-qtip="' + me.up('variablefield').getScopeName(scope) +
                    '"class="scalr-ui-variablefield-variable-locked-scope-' +
                    scope + '"></div><span style="color: #999; padding-left: 2px">' + name + '</span>';
            },

            getInvalidNameHtml: function (name, scope) {
                var me = this;

                return me.getScopeMarkerHtml(scope)
                    + '<span style="color: #f04a46; padding-left: 10px">' + name + '</span>';
            },

            getNameHtml: function (name, scope, isLocked, isValid) {
                var me = this;

                if (!name) {
                    return me.getEmptyNameHtml(scope);
                }

                if (isLocked) {
                    return me.getLockedNameHtml(name, scope);
                }

                if (!isValid) {
                    return me.getInvalidNameHtml(name, scope);
                }

                return me.getScopeMarkerHtml(scope) + '<span style="padding-left: 10px">' + name + '</span';
            },

            getValueColor: function (value) {
                return !value ? '#999' : '#000';
            },

            getValueLengthHtml: function (length) {
                return '<div style="float: right; color: #999; padding-left: 6px">(' + length + ' chars)</div>';
            },

            getPureValueHtml: function (value, color) {
                return '<span style="font-family: monospace; color: ' +
                    color + '">' + value + '</span>';
            },

            getLongValueHtml: function (valueHtml, valueLengthHtml) {
                return valueLengthHtml +
                    '<div class="scalr-ui-variablefield-grid-variable-value-long">' + valueHtml + '</div>';
            },

            getValueHtml: function (value, color) {
                var me = this;

                var valueLength = value.length;
                var valueLengthHtml = me.getValueLengthHtml(valueLength);
                var linebreakIndex = value.indexOf('\n');
                var valueHtml = me.getPureValueHtml(value, color);

                if (linebreakIndex > 6) {
                    valueHtml = me.getPureValueHtml(value.substring(0, linebreakIndex), color);

                    return me.getLongValueHtml(valueHtml, valueLengthHtml);
                }

                if (valueLength > 60) {
                    return me.getLongValueHtml(valueHtml, valueLengthHtml);
                }

                return valueHtml;
            },

            getFlagFinalClass: function (flagFinal) {
                return 'scalr-ui-variablefield-grid-flag-final-' + (!flagFinal ? 'off' : 'on');
            },

            getFlagRequiredClass: function (flagRequired) {
                return !flagRequired ?
                    '' : 'scalr-ui-variablefield-grid-flag-required-scope-' + flagRequired;
            },

            getFlagRequiredTip: function (flagRequired) {
                var me = this;

                return flagRequired ? '<img src="' + Ext.BLANK_IMAGE_URL +
                    '" class="scalr-scope-' + flagRequired + '" /><span style="margin-left: 6px">' +
                    me.up('variablefield').getScopeName(flagRequired) + '</span>' :
                    '<span style="font-style: italic">not required</span>';
            },

            getFlagHiddenClass: function (flagHidden) {
                return 'scalr-ui-variablefield-grid-flag-hidden-' + (!flagHidden ? 'off' : 'on');
            },

            getFlagFinalHtml: function (flagFinal) {
                var me = this;

                return '<div class="' + me.getFlagFinalClass(flagFinal) + '" data-qtip="Locked variable"></div>';
            },

            getFlagRequiredHtml: function (flagRequired) {
                var me = this;

                return '<div class="scalr-ui-variablefield-grid-flag-required ' +
                    me.getFlagRequiredClass(flagRequired) +
                    '" data-qtip=\'<div style="float: left; margin-right: 6px">Required in:</div>' +
                    me.getFlagRequiredTip(flagRequired) + '\'></div>';
            },

            getFlagHiddenHtml: function (flagHidden) {
                var me = this;

                return '<div class="' + me.getFlagHiddenClass(flagHidden) + '" data-qtip="Hidden variable"></div>';
            },

            getFlagsHtml: function (flagFinal, flagRequired, flagHidden) {
                var me = this;

                return '<div>' +
                    me.getFlagFinalHtml(flagFinal) +
                    me.getFlagRequiredHtml(flagRequired) +
                    me.getFlagHiddenHtml(flagHidden) +
                    '</div>';
            },

            dockedItems: [{
                xtype: 'toolbar',
                style: 'box-shadow: none; padding: 12px 0',
                dock: 'top',
                layout: 'hbox',
                items: [{
                    xtype: 'filterfield',
                    width: 175,
                    handler: function (me, value) {
                        var store = me.up().ownerCt.getStore();

                        if (value) {
                            store.addFilter({
                                id: 'searchFilter',
                                anyMatch: true,
                                property: 'name',
                                value: value
                            });

                            return;
                        }

                        store.removeFilter('searchFilter');
                    }
                }, {
                    xtype: 'button',
                    margin: '0 0 0 12',
                    width: 170,
                    enableToggle: true,
                    text: 'Show locked variables',
                    applyFilter: function (status) {
                        var me = this;

                        var store = me.up().ownerCt.getStore();
                        var variableField = me.up('variablefield');

                        if (status) {
                            store.removeFilter('finalVariableFilter');

                            variableField.fireEvent('select', variableField);

                            return;
                        }

                        store.addFilter({
                            id: 'finalVariableFilter',
                            filterFn: function(record) {
                                return !(record.get('default') && record.get('locked').flagFinal == 1);
                            }
                        });

                        variableField.fireEvent('select', variableField);
                    },
                    listeners: {
                        afterrender: function (me) {
                            me.applyFilter(false);
                        },
                        toggle: function (me, status) {
                            me.applyFilter(status);
                        }
                    }
                }, {
                    xtype: 'tbfill',
                    flex: 1
                }, {
                    xtype: 'button',
                    text: 'Add variable',
                    cls: 'x-btn-green-bg',
                    style: 'padding: 5px 9px',
                    width: 104,
                    handler: function (me) {
                        me.up('variablefield').addVariable();
                    }
                }]
            }],

            columns: [{
                header:
                    '<img src="' + Ext.BLANK_IMAGE_URL +
                    '" style="float: left; cursor: help" class="x-icon-severity x-icon-severity-2" data-qtip=\'' +
                    '<div>Scopes:</div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr" />' +
                    '<span style="padding-left: 6px">Scalr</span></div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-account" />' +
                    '<span style="padding-left: 6px">Account</span></div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-env" />' +
                    '<span style="padding-left: 6px">Environment</span></div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-role" />' +
                    '<span style="padding-left: 6px">Role</span></div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-farm" />' +
                    '<span style="padding-left: 6px">Farm</span></div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-farmrole" />' +
                    '<span style="padding-left: 6px">Farm Role</span></div>'
                    + '\' /><div style="float: left; padding-left: 6px">Name</div>',
                name: 'name',
                dataIndex: 'name',
                flex: 0.35,
                renderer: function (value, meta, record) {
                    var me = this;

                    var current = record.get('current');
                    var def = record.get('default');
                    var locked = record.get('locked');
                    var scope = current && (current.value || !def) ? current.scope : def.scope;

                    return me.getNameHtml(
                        record.get('name'),
                        scope,
                        parseInt(locked.flagFinal) === 1,
                        !(record.get('serverErrors') || record.get('validationErrors').length)
                    );
                }
            }, {
                header: 'Value',
                flex: 0.65,
                sortable: false,
                renderer: function (value, meta, record) {
                    var me = this;

                    var current = record.get('current');
                    var def = record.get('default');
                    var locked = record.get('locked');

                    var currentValue = current.value;
                    var variableValue = currentValue || def.value || '';
                    var valueColor = me.getValueColor(currentValue);

                    return me.getValueHtml(variableValue, valueColor);
                }
            }, {
                header: 'Flags',
                width: 103,
                sortable: false,
                align: 'center',
                renderer: function (value, meta, record) {
                    var me = this;

                    var current = record.get('current');
                    var locked = record.get('locked');

                    var flagFinal = parseInt(current.flagFinal) === 1 || parseInt(locked.flagFinal) === 1;
                    var flagRequired = current.flagRequired || locked.flagRequired;
                    var flagHidden = parseInt(current.flagHidden) === 1 || parseInt(locked.flagHidden) === 1;

                    return me.getFlagsHtml(flagFinal, flagRequired, flagHidden);
                }
            }]
        }]
    }, {
        xtype: 'container',
        name: 'extendedForm',
        style: 'background-color: #f0f1f4',
        width: 370,
        layout: 'vbox',

        getScopesData: function (scopes) {
            var me = this;

            var variableField = me.up('variablefield');
            var scopesData = [];

            Ext.Array.each(scopes, function (scope) {
                scopesData.push({
                    id: scope,
                    name: variableField.getScopeName(scope)
                });
            });

            return scopesData;
        },

        setVariableName: function (name, lastDefinitionsScope, scopes) {
            var me = this;

            var nameField = me.down('[name=name]');
            nameField.setVisible(name);

            var newNameField = me.down('[name=newName]');
            newNameField.setVisible(!name);

            var variableField = me.up('variablefield');

            if (name) {

                /*
                var declarationsScope = scopes.shift();
                var definitionsScopes = me.getScopesData(scopes);
                var declarationsScopeName = variableField.getScopeName(declarationsScope);

                nameField.update({
                    name: name,
                    lastDefinitionsScope: lastDefinitionsScope,
                    lastDefinitionsScopeName: variableField.getScopeName(lastDefinitionsScope),
                    declarationsScope: declarationsScope,
                    declarationsScopeName: declarationsScopeName,
                    definitionsScopes: definitionsScopes.length ? definitionsScopes : [{
                        id: declarationsScope,
                        name: declarationsScopeName
                    }]
                });
                */

                var declarationsScope = scopes[0];

                nameField.update({
                    name: name,
                    lastDefinitionsScope: lastDefinitionsScope,
                    lastDefinitionsScopeName: variableField.getScopeName(lastDefinitionsScope),
                    declarationsScope: declarationsScope,
                    declarationsScopeName: variableField.getScopeName(declarationsScope),
                    definitionsScopes: me.getScopesData(scopes)
                });

                return me;
            }

            newNameField.variableNames = variableField.getNames();
            newNameField.setValue().focus();

            return me;
        },

        setVariableFlags: function (record) {
            var me = this;

            var current = record.get('current');
            var def = record.get('default');
            var locked = record.get('locked');
            var disabled = !Ext.Object.isEmpty(def) || !Ext.Object.isEmpty(locked);
            var flagRequired = current['flagRequired'] || locked['flagRequired'];
            var flagFinal = current['flagFinal'] == 1 || locked['flagFinal'] == 1;
            var flagHidden = current['flagHidden'] == 1 || locked['flagHidden'] == 1;

            me.down('[name=flags]').setValue({
                disabled: disabled,
                flagRequired: flagRequired,
                flagFinal: flagFinal,
                flagHidden: flagHidden
            });

            return me;
        },

        setVariableValue: function (locked, currentValue, defaultValue, validator) {
            var me = this;

            var field = me.down('[name=value]');
            field.setDisabled(locked);

            field.setValue();
            field.emptyText = defaultValue || ' ';
            field.applyEmptyText();

            if (currentValue && !locked) {
                field.setValue(currentValue);
            }

            var isRegexValid = me.down('[name=validator]').validator(validator);

            if (validator && isRegexValid && typeof isRegexValid !== 'string') {
                field.regex = new RegExp(validator);
                field.validate();

                return me;
            }

            field.regex = '';
            field.validate();

            return me;
        },

        setVariableRequiredScope: function (flagRequired, scope, readOnly) {
            var me = this;

            var currentScope = me.currentScope;
            var field = me.down('[name=requiredScope]');

            var store = field.getStore();
            store.clearFilter();

            if (!readOnly) {
                var record = store.findRecord('id', scope);
                var index = store.indexOf(record);

                store.filterBy(function (record) {
                    var isScopeBelow = store.indexOf(record) > index;

                    if (currentScope === 'role') {
                        return isScopeBelow && record.get('id') !== 'farm';
                    }

                    return isScopeBelow;
                });
            }

            field.
                show().
                setValue(flagRequired || 'farmrole').
                setDisabled(readOnly);

            if (!flagRequired) {
                field.hide();
            }

            return me;
        },

        setVariableFormat: function (format, readOnly) {
            var me = this;

            me.down('[name=format]').
                setValue(format).
                setDisabled(readOnly).
                validate();

            return me;
        },

        setVariableValidator: function (validator, readOnly) {
            var me = this;

            me.down('[name=validator]').
                setValue(validator).
                setDisabled(readOnly).
                validate();

            return me;
        },

        getErrorText: function (errors) {
            var text = [];

            Ext.Array.each(errors, function (error) {
                text.push('<div>' + error + ' </div>');
            });

            return text.join('');
        },

        setHeaderText: function (errors) {
            var me = this;

            var subHeader = errors ? ('<div style="color: #f04a46">' + me.getErrorText(errors) + '</div>') : '';

            me.down('fieldset').
                setTitle('Variable details', subHeader);

            return me;
        },

        setDeletable: function (isDeletable) {
            var me = this;

            me.down('[name=delete]').setDisabled(isDeletable);

            return me;
        },

        setFormVisible: function (isVisible) {
            var me = this;

            me.down('fieldset').setVisible(isVisible);
            me.down('[name=delete]').setVisible(isVisible);

            return me;
        },

        setDeleteTooltip: function (readOnly, name, declarationsScope) {
            var me = this;

            me.down('[name=delete]').setTooltip(
                !readOnly ? '' : '<div style="float: left"><span style="font-style: italic">' +
                name + '</span> is declared in: </div>' +
                '<img style="margin: 0 6px" src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-' +
                declarationsScope + '" />' + me.up('variablefield').getScopeName(declarationsScope) +
                ';<div style="text-align: center">delete it there first</div>'
            );

            return me;
        },

        setValue: function (record) {
            var me = this;

            if (!me.variable) {
                me.setFormVisible(true);
            }

            me.variable = record;

            var name = record.get('name');
            var current = record.get('current');
            var def = record.get('default');
            var locked = record.get('locked');
            var readOnly = (!Ext.Object.isEmpty(def) || !Ext.Object.isEmpty(locked));
            var scopes = Ext.Array.clone(record.get('scopes'));
            var scope = current.value ? current.scope : (def.scope || locked.scope || current.scope);
            var validator = readOnly ? locked.validator : current.validator;

            me.
                setDeletable(readOnly).
                setDeleteTooltip(readOnly, name, scopes[0]).
                setHeaderText(record.get('serverErrors')).
                setVariableName(name, scope, scopes).
                setVariableRequiredScope(readOnly ? locked.flagRequired : current.flagRequired, scope, readOnly).
                setVariableFlags(record).
                setVariableValue(parseInt(locked.flagFinal), current.value, def.value, validator).
                setVariableFormat(readOnly ? locked.format : current.format, readOnly).
                setVariableValidator(validator, readOnly);

            var variableField = me.up('variablefield');

            if (!name) {
                me.setVariableName();
                variableField.fireEvent('select', variableField);
                return me;
            }

            if (record.get('validationErrors').indexOf('name') !== -1) {
                me.setVariableName();
                me.down('[name=newName]').setValue(name);
            }

            variableField.fireEvent('select', variableField);

            return me;
        },

        setValidationError: function (field, isValid) {
            var me = this;

            var record = me.variable;
            var errors = record.get('validationErrors') || [];
            var fieldNameIndex = errors.indexOf(field);

            if (isValid && fieldNameIndex !== -1) {
                errors.splice(fieldNameIndex, 1);
            } else if (!isValid && fieldNameIndex === -1) {
                errors.push(field);
            }

            record.set('validationErrors', errors);

            return me;
        },

        applyName: function (name, isValid, preventFocusOnValue) {
            var me = this;

            var record = me.variable;
            var current = record.get('current');

            current.name = name;

            me.setValidationError('name', isValid);

            record.set('current', current);
            record.set('name', name);
            record.commit();

            var variableField = me.up('variablefield');

            if (isValid && name) {
                me.setValue(record);

                variableField.fireEvent('addvariable', variableField, record);

                if (preventFocusOnValue) {
                    me.down('[name=newName]').preventFocusOnValue = false;
                    return;
                }

                me.down('[name=value]').focus(true);
                return;
            }

            variableField.fireEvent('select', variableField);

            return me;
        },

        applyFlagFinal: function (flag) {
            var me = this;

            var record = me.variable;
            var current = record.get('current');
            var readOnly = !Ext.Object.isEmpty(record.get('default')) ||
                !Ext.Object.isEmpty(record.get('locked'));

            if (!readOnly && parseInt(current.flagFinal) !== flag) {
                current.flagFinal = flag;

                record.set('current', current);
                record.commit();

                var variableField = me.up('variablefield');
                variableField.fireEvent('datachanged', variableField, record);
            }

            return me;
        },

        applyFlagRequired: function (state) {
            var me = this;

            var requiredScopeField = me.down('[name=requiredScope]');
            var flag = state ? requiredScopeField.getValue() : '';
            var record = me.variable;
            var current = record.get('current');
            var readOnly = !Ext.Object.isEmpty(record.get('default')) ||
                !Ext.Object.isEmpty(record.get('locked'));

            if (!readOnly && current.flagRequired !== flag) {
                current.flagRequired = flag;

                record.set('current', current);
                record.commit();

                requiredScopeField.setVisible(state);

                var variableField = me.up('variablefield');
                variableField.fireEvent('datachanged', variableField, record);

                return me;
            }

            requiredScopeField.setVisible(state);

            return me;
        },

        applyFlagHidden: function (flag) {
            var me = this;

            var record = me.variable;
            var current = record.get('current');
            var readOnly = !Ext.Object.isEmpty(record.get('default')) ||
                !Ext.Object.isEmpty(record.get('locked'));

            if (!readOnly && parseInt(current.flagHidden) !== flag) {
                current.flagHidden = flag;

                record.set('current', current);
                record.commit();

                var variableField = me.up('variablefield');
                variableField.fireEvent('datachanged', variableField, record);
            }

            return me;
        },

        applyValue: function (value, oldValue, isValid) {
            var me = this;

            var record = me.variable;

            if (record && value !== oldValue) {
                var current = record.get('current');
                var scopes = record.get('scopes');

                if (!Ext.isObject(current)) {
                    current = {
                        name: record.get('name'),
                        scope: me.currentScope
                    };
                }

                current.value = value;

                var currentScopeIndex = scopes.indexOf(current.scope);

                if (!value && currentScopeIndex !== 0) {
                    scopes.splice(currentScopeIndex, 1);
                } else if (currentScopeIndex === -1) {
                    scopes.push(current.scope);
                }

                me.setValidationError('value', isValid);

                record.set('current', current);
                record.set('scopes', scopes);
                record.commit();

                var nameField = me.down('[name=name]');

                if (nameField.isVisible()) {
                    var def = record.get('default');
                    var scope = value || !def ? current.scope : def.scope;

                    me.setVariableName(record.get('name'), scope, Ext.Array.clone(scopes));
                }

                var variableField = me.up('variablefield');
                variableField.fireEvent('datachanged', variableField, record);
            }

            return me;
        },

        applyRequiredScope: function (requiredScope) {
            var me = this;

            var record = me.variable;

            if (record) {
                var current = record.get('current');

                if (current.flagRequired) {
                    current.flagRequired = requiredScope;

                    record.set('current', current);
                    record.commit();

                    var variableField = me.up('variablefield');
                    variableField.fireEvent('datachanged', variableField, record);
                }

                me.down('[name=flagRequired]').inputValue = requiredScope;
            }

            return me;
        },

        applyFormat: function (format, isValid) {
            var me = this;

            var record = me.variable;
            var current = record.get('current');

            current.format = format;

            me.setValidationError('format', isValid);

            record.set('current', current);
            record.commit();

            var variableField = me.up('variablefield');
            variableField.fireEvent('datachanged', variableField, record);

            return me;
        },

        applyValidator: function (validator, isValid) {
            var me = this;

            var record = me.variable;
            var current = record.get('current');
            var validationErrors = record.get('validationErrors');

            current.validator = validator;

            me.setValidationError('validator', isValid);

            record.set('current', current);
            record.commit();

            var valueField = me.down('[name=value]');
            valueField.regex = isValid ? new RegExp(validator) : '';
            valueField.validate();

            var variableField = me.up('variablefield');
            variableField.fireEvent('datachanged', variableField, record);

            return me;
        },

        listeners: {
            afterrender: function (me) {
                me.currentScope = me.up('variablefield').currentScope;
            }
        },

        items: [{
            xtype: 'fieldset',
            title: 'Variable details',
            cls: 'x-fieldset-separator-none',
            flex: 1,
            width: '100%',
            autoScroll: true,
            hidden: true,
            hideMode: 'visibility',
            layout: 'vbox',
            defaults: {
                width: 300
            },

            items: [{
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'component',
                    name: 'name',
                    isFormField: false,
                    tpl: [
                        '<div style="margin-top: 6px">',

                            '<div style="float: left" data-qtip=\'',

                                '<div style="border-bottom: 1px solid #fff">',
                                    '<div style="float: left; width: 65px">Declared in:</div>',
                                    '<img src="' + Ext.BLANK_IMAGE_URL + '" style="margin: 0 6px" class="scalr-scope-{declarationsScope}" />',
                                    '<span>{declarationsScopeName}</span>',
                                '</div>',

                                '<div style="margin-top: 6px">',
                                    '<div style="float: left; width: 65px">',
                                        '<span>Defined in:</span>',
                                    '</div>',
                                    '<div style="float: left">',
                                        '<tpl for="definitionsScopes">',
                                            '<div>',
                                                '<img src="' + Ext.BLANK_IMAGE_URL + '" style="margin: 0 6px" class="scalr-scope-{id}" />',
                                                '<span>{name}</span>',
                                            '</div>',
                                        '</tpl>',
                                    '</div>',
                                '</div>',

                            '\'>',

                                '<tpl if="declarationsScope !== lastDefinitionsScope">',
                                    '<img src="' + Ext.BLANK_IMAGE_URL + '" style="margin: 0 -6px 4px 0" class="scalr-scope-{declarationsScope}" />',
                                    '<img src="' + Ext.BLANK_IMAGE_URL + '" style="box-shadow: -1px -1px 0 0 #f0f1f4" class="scalr-scope-{lastDefinitionsScope}" />',
                                '<tpl else>',
                                    '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-{declarationsScope}" />',
                                '</tpl>',

                            '</div>',

                            '<div class="scalr-ui-variablefield-extendedform-variable-name">{name}</div>',

                        '</div>'
                    ]
                }, {
                    xtype: 'textfield',
                    name: 'newName',
                    width: 202,
                    emptyText: 'Name',
                    hidden: true,
                    allowChangeable: false,
                    allowChangeableMsg: 'Variable name, cannot be changed',
                    minLength: 2,
                    minLengthText: 'Variable names must be a minimum of 2 characters',
                    maxLength: 50,
                    maxLengthText: 'Variable names can be a maximum of 50 character',
                    isFormField: false,
                    validateOnChange: true,

                    validator: function (value) {
                        var me = this;

                        if (!value) {
                            return true;
                        }

                        if (!/^\w+$/.test(value)) {
                            return 'Variable names can only contain letters, numbers and underscores _';
                        }

                        if (!/^[A-Za-z]/.test(value[0])) {
                            return 'Variable names must start with a letter';
                        }

                        if (me.variableNames.indexOf(value) !== -1) {
                            return 'This variable name is already in use';
                        }

                        return true;
                    },

                    listeners: {
                        blur: function (me) {
                            me.up('[name=extendedForm]').
                                applyName(me.getValue(), me.isValid(), me.preventFocusOnValue);
                        },
                        specialkey: function (me, e) {
                            var value = me.getValue();

                            if (e.getKey() === e.ENTER && value && me.isValid()) {
                                me.preventFocusOnValue = true;
                                me.blur();
                            }
                        }
                    }
                }, {
                    xtype: 'tbfill',
                    flex: .01
                }, {
                    xtype: 'container',
                    name: 'flags',
                    layout: 'hbox',

                    setValue: function (value) {
                        var me = this;

                        // todo: comb this

                        var disabled = value.disabled;
                        var flags = me.query('buttonfield');

                        if (!disabled) {
                            Ext.Array.each(flags, function (field) {
                                field.enable();
                            });
                        }

                        Ext.Array.each(flags, function (field) {
                            field.setFlag(value[field.name]);
                        });

                        if (disabled) {
                            Ext.Array.each(flags, function (field) {
                                field.disable();
                            });
                        }

                        if (me.up('[name=extendedForm]').currentScope === 'farmrole') {
                            Ext.Array.each(flags, function (field) {
                                if (field.name === 'flagFinal') {
                                    return;
                                }
                                field.disable();
                            });
                        }
                    },

                    items: [{
                        xtype: 'buttonfield',
                        ui: 'flag',
                        cls: 'x-btn-flag-final',
                        name: 'flagFinal',
                        tooltip: 'Cannot be changed at a lower scope',
                        inputValue: 1,
                        enableToggle: true,
                        isFormField: false,
                        setFlag: function (value) {
                            var me = this;

                            me.setValue(value);
                            me.next().setDisabled(value);

                            return me;
                        },
                        toggleHandler: function (me, state) {
                            var extendedForm = me.up('[name=extendedForm]');
                            extendedForm.applyFlagFinal(me.getValue() || 0);

                            var currentScope = extendedForm.variable.get('current').scope;
                            me.next().setDisabled(currentScope !== 'farmrole' ? state : true);
                        }
                    }, {
                        xtype: 'buttonfield',
                        ui: 'flag',
                        cls: 'x-btn-flag-required',
                        margin: '0 0 0 6',
                        name: 'flagRequired',
                        tooltip: 'Shall be set at a lower scope',
                        enableToggle: true,
                        isFormField: false,
                        setFlag: function (value) {
                            var me = this;

                            me.setValue(value);
                            me.prev().setDisabled(value);

                            return me;
                        },
                        toggleHandler: function (me, state) {
                            me.up('[name=extendedForm]').applyFlagRequired(state);
                            me.prev().setDisabled(state);
                        }
                    }, {
                        xtype: 'buttonfield',
                        ui: 'flag',
                        cls: 'x-btn-flag-hide',
                        margin: '0 0 0 6',
                        name: 'flagHidden',
                        tooltip: 'Do not store this variable on instances, and mask value from view at a lower scope',
                        inputValue: 1,
                        enableToggle: true,
                        isFormField: false,
                        setFlag: function (value) {
                            var me = this;

                            me.setValue(value);

                            return me;
                        },
                        toggleHandler: function (me) {
                            me.up('[name=extendedForm]').applyFlagHidden(me.getValue() || 0);
                        }
                    }]
                }]
            }, {
                xtype: 'textarea',
                name: 'value',
                margin: '6 0 0 0',
                flex: 1,
                minHeight: 100,
                isFormField: false,
                fieldStyle: {
                    fontFamily: 'monospace'
                },
                disabled: true,
                validator: function (value) {
                    var me = this;

                    var extendedForm = me.up('[name=extendedForm]');
                    var record = extendedForm.variable;
                    var defaultValue = record.get('default').value;
                    var flagRequired = record.get('locked').flagRequired;

                    if (!value && !defaultValue && flagRequired === extendedForm.currentScope) {
                        return record.get('name') + ' is required variable';
                    }

                    return true;
                },
                listeners: {
                    focus: function (me) {
                        me.oldValue = me.getValue();
                    },
                    blur: function (me) {
                        me.up('[name=extendedForm]').applyValue(me.getValue(), me.oldValue, me.isValid());
                    }
                }
            }, {
                xtype: 'combo',
                name: 'requiredScope',
                margin: '6 0 0 0',
                fieldLabel: 'Required scope',
                store: {
                    fields: [ 'id', 'name' ],
                    data: [
                        { id: 'scalr', name: 'Scalr' },
                        { id: 'account', name: 'Account' },
                        { id: 'env', name: 'Environment' },
                        { id: 'role', name: 'Role' },
                        { id: 'farm', name: 'Farm' },
                        { id: 'farmrole', name: 'Farm Role' }
                    ]
                },
                fieldSubTpl: [
                    '<div class="{hiddenDataCls}" role="presentation"></div>',
                        '<div id="{id}" type="{type}" ',
                            '<tpl if="size">size="{size}" </tpl>',
                            '<tpl if="tabIdx">tabIndex="{tabIdx}" </tpl>',
                        'class="{fieldCls} {typeCls}" style="box-shadow: none" autocomplete="off"></div>',
                        '<div id="{cmpId}-triggerWrap" class="{triggerWrapCls}" role="presentation">',
                            '{triggerEl}',
                        '<div class="{clearCls}" role="presentation"></div>',
                    '</div>',
                    {
                        compiled: true,
                        disableFormats: true
                    }
                ],
                displayTpl: [
                    '<tpl for=".">',
                        '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-{id}" />',
                        '<span style="padding-left: 6px">{name}</span>',
                    '</tpl>'
                ],
                listConfig: {
                    getInnerTpl: function () {
                        return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-{id}" /><span style="padding-left: 6px">{name}</span>';
                    }
                },
                setRawValue: function (value) {
                    var me = this;

                    value = Ext.value(me.transformRawValue(value), '');
                    me.rawValue = value;

                    if (me.inputEl) {
                        me.inputEl.dom.value = value;
                        me.inputEl.dom.innerHTML = value;
                    }

                    return value;
                },
                valueField: 'id',
                displayField: 'name',
                queryMode: 'local',
                triggerAction: 'last',
                lastQuery: '',
                editable: false,
                isFormField: false,
                disabled: true,
                listeners: {
                    change: function (me, value) {
                        me.up('[name=extendedForm]').applyRequiredScope(value);
                    }
                }
            }, {
                xtype: 'textfield',
                name: 'format',
                margin: '6 0 0 0',
                fieldLabel: 'Format',
                isFormField: false,
                disabled: true,
                validator: function (value) {
                    var test = value.match(/\%/g);

                    if (!value || (test && test.length === 1)) {
                        return true;
                    }

                    return 'Format isn\'t valid';
                },
                listeners: {
                    blur: function (me) {
                        me.up('[name=extendedForm]').applyFormat(me.getValue(), me.isValid());
                    }
                }
            }, {
                xtype: 'textfield',
                name: 'validator',
                margin: '6 0 0 0',
                fieldLabel: 'Validation pattern',
                isFormField: false,
                disabled: true,
                validator: function (value) {
                    if (value) {
                        try {
                            var r = new RegExp(value);
                            r.test('test');
                            return true;
                        } catch (e) {
                            return e.message;
                        }
                    }

                    return true;
                },
                listeners: {
                    blur: function (me) {
                        me.up('[name=extendedForm]').applyValidator(me.getValue(), me.isValid());
                    }
                }
            }]
        }, {
            xtype: 'container',
            style: 'border-bottom: 1px solid #dfe4ea',
            width: '100%',
            items: [{
                xtype: 'button',
                name: 'delete',
                text: 'Delete',
                cls: 'x-btn-default-small-red -bottom',
                margin: '12 0 24 110',
                height: 32,
                width: 150,
                hidden: true,
                handler: function (me) {
                    me.up('variablefield').removeVariable(me.up('[name=extendedForm]').variable);
                }
            }]
        }]
    }]
});

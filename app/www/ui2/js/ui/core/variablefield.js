Ext.define('Scalr.ui.VariableField', {
    extend: 'Ext.container.Container',

    mixins: {
        field: 'Ext.form.field.Field'
    },

    alias: 'widget.variablefield',

    cls: 'x-form-variablefield',

    preserveScrollOnRefresh: true,

    currentScope: 'environment',

    scalrDefaultsCategoryName: 'SCALR_UI_DEFAULTS',

    encodeParams: true,

    dirty: false,

    readOnly: false,

    layout: {
        type: 'hbox',
        align: 'stretch'
    },

    scopes: {
        scalr: 'Scalr',
        account: 'Account',
        environment: 'Environment',
        role: 'Role',
        farm: 'Farm',
        farmrole: 'Farm Role'
    },

    scopesList: [
        'scalr',
        'account',
        'environment',
        'role',
        'farm',
        'farmrole'
    ],

    // This data should be synchronized with Scalr_Scripting_GlobalVariables
    scalrUiDefaults: [{
        name: 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
        description: 'Reuse block storage device if an instance is replaced.',
        validator: '^[01]$'
    }, {
        name: 'SCALR_UI_DEFAULT_REBOOT_AFTER_HOST_INIT',
        description: 'Reboot after HostInit Scripts have executed.',
        validator: '^[01]$'
    }, {
        name: 'SCALR_UI_DEFAULT_AUTO_SCALING',
        description: 'Auto-scaling is disabled (0) or enabled (1).',
        validator: '^[01]$'
    }, {
        name: 'SCALR_UI_DEFAULT_AWS_INSTANCE_INITIATED_SHUTDOWN_BEHAVIOR',
        description: 'AWS EC2 instance initiated shutdown behavior is suspend ("stop") or terminate ("terminate").',
        validator: '^(stop|terminate)$'
    }],

    initCategories: function () {
        var me = this;

        me.storedCategories = me.getUsedCategories();
        me.storedCategories.push(me.scalrDefaultsCategoryName);

        var categoriesStore = me.getCategoriesStore();
        categoriesStore.removeAll();
        categoriesStore.add(
            Ext.Array.map(me.storedCategories, function (category) {
                return [ category ];
            })
        );

        return me;
    },

    initRequiredScopes: function () {
        var me = this;

        me.availableRequiredScopes = Ext.Array.slice(
            me.scopesList,
            me.scopesList.indexOf(me.getScope()) + 1
        );

        me.getForm().down('[name=requiredScope]').getMenu().items.each(function (menuItem) {
            if (!Ext.Array.contains(me.availableRequiredScopes, menuItem.value)) {
                menuItem
                    .disable()
                    .hide();
            }
        });

        return me;
    },

    initScalrUiDefaults: function () {
        var me = this;

        var categoryName = me.scalrDefaultsCategoryName;
        var emptyVariableData = me.getEmptyVariableData();
        var scalrUiDefaults = me.scalrUiDefaults;

        var scalrVariables = me.scalrVariables = Ext.Array.map(scalrUiDefaults, function (variable) {
            return Ext.Object.merge(Ext.clone(emptyVariableData), {
                name: variable.name,
                category: categoryName,
                current: Ext.apply(variable, {
                    category: categoryName
                })
            });
        });

        if (!Ext.isEmpty(scalrVariables)) {
            me.getForm().down('variablenamefield').getStore().loadData(scalrVariables);
        }

        return me;
    },

    initComponent : function () {
        var me = this;

        me.callParent();

        me.initField();

        me.setTitle(me.title);

        me.grid = me.down('grid');
        me.grid.variableField = me;
        me.store = me.grid.getStore();
        me.form = me.down('[name=extendedForm]');
        me.form.variableField = me;
        me.form.currentScope = me.currentScope;

        me.form.down('variablenamefield').isScalrDefaultsEditable = me.isScalrDefaultsEditable =
            Ext.Array.contains(['scalr', 'account', 'environment'], me.currentScope);

        if (me.getScope() === 'scalr') {
            me.getGridPanel().down('#showLocked')
                .disable()
                .setTooltip('All variables are visible on the Scalr scope');
        }

        if (me.removeTopSeparator) {
            Ext.apply(me.down('[name=title]'), {
                style: 'box-shadow: none'
            });
        }

        if (!Ext.Array.contains(['scalr', 'account', 'environment'], me.getScope())) {
            me.grid.down('#refresh').hide();
        }

        me
            .initRequiredScopes()
            .initScalrUiDefaults()
            .setReadOnly(me.readOnly, true);

        if (Ext.isEmpty(me.validationErrors)) {
            me.validationErrors = {
                variables: {},
                newVariable: {}
            };
        }
    },

    initEvents: function () {
        var me = this;

        me.callParent();

        var grid = me.getGridPanel();

        me.on({
            beforeselect: me.beforeSelect,
            select: me.onSelect,
            datachanged: me.onDataChanged,
            removevariable: me.onVariableRemove,
            addvariable: me.onVariableAdd,
            addcategory: me.addStoredCategory,
            removecategory: me.removeStoredCategory,
            scope: me
        });

        me.getStore()
            .on('update', grid.refreshView, grid);

        grid.getView().on({
            groupcollapse: me.onCategoryCollapse,
            scope: me
        });

        me.getForm().on({
            beforecategorychanged: me.beforeCategoryChanged,
            categorychanged: me.onCategoryChanged,
            scope: me
        });
    },

    beforeSelect: function (me) {
        if (me.preserveScrollOnRefresh) {
            me.saveScrollState();
        }

        return true;
    },

    onSelect: function (me) {
        if (me.preserveScrollOnRefresh) {
            me.restoreScrollState();
        }

        return true;
    },

    onDataChanged: function (me) {
        if (me.preserveScrollOnRefresh) {
            me.restoreScrollState();
        }

        if (!me.isDirty()) {
            me.markDirty();
        }

        return true;
    },

    onVariableRemove: function (me, variable, category) {
        if (me.preserveScrollOnRefresh) {
            me.restoreScrollState();
        }

        //me.getGridPanel().resumeLayouts(true);
        me.getGridPanel().enableGrouping();

        me.tryRemoveCategory(category);

        return true;
    },

    onVariableAdd: function (me, record) {
        me.getGridPanel()
            .down('#add').toggle(false, true);

        var validationErrors = me.validationErrors;
        validationErrors.variables[record.get('name')] = validationErrors.newVariable;
        validationErrors.newVariable = {};

        return true;
    },

    tryRemoveCategory: function (category) {
        var me = this;

        if (!Ext.Array.contains(me.getUsedCategories(), category)) {
            var categoriesStore = me.getCategoriesStore();
            var record = categoriesStore.findRecord(
                'category', category, 0, false, false, true
            );

            if (Ext.isEmpty(record)) {
                categoriesStore.on('datachanged', function (store) {
                    me.tryRemoveCategory(category);
                }, me, {
                    single: true
                });
                return false;
            }

            categoriesStore.remove(
                categoriesStore.findRecord('category', category)
            );

            me.fireEvent('removecategory', me, category);

            return true;
        }

        return false;
    },

    addCategory: function (category) {
        var me = this;

        me.getCategoriesStore().add(
            [ [ category ] ]
        );

        me.fireEvent('addcategory', me, category);

        return true;
    },

    beforeCategoryChanged: function (newCategory) {
        var me = this;

        var hasCategory = Ext.Array.contains(
            me.getStoredCategories(),
            newCategory
        );

        if (hasCategory || newCategory === '') {
            me.getGridPanel().expandGroup(newCategory);
        }

        return true;
    },

    onCategoryChanged: function (newCategory, oldCategory) {
        var me = this;

        //me.getGridPanel().refreshView();

        if (!Ext.isEmpty(oldCategory)) {
            me.tryRemoveCategory(oldCategory);
        }

        var hasCategory = Ext.isEmpty(newCategory) || Ext.Array.equals(
            me.getUsedCategories(true).sort(),
            me.getStoredCategories(true).sort()
        );

        if (!hasCategory) {
            me.addCategory(newCategory);
        }

        return true;
    },

    onCategoryCollapse: function (gridView, node, group) {
        var me = this;

        var grid = me.getGridPanel();
        var selectedRecord= grid.getSelection()[0];

        if (!Ext.isEmpty(selectedRecord) && selectedRecord.get('category') === group) {
            grid.getSelectionModel().deselect(selectedRecord);
            grid.refreshView();
            grid.down('#add').toggle(false, true);
            me.getForm().setFormVisible(false);

        }

        return true;
    },

    getGridPanel: function () {
        var me = this;

        return me.grid || me.down('grid');
    },

    getStore: function () {
        var me = this;

        return me.store || me.down('grid').getStore();
    },

    getCategoriesStore: function () {
        var me = this;

        return me.getForm().down('#category').getStore();
    },

    getForm: function () {
        var me = this;

        return me.form || me.down('[name=extendedForm]');
    },

    getScope: function () {
        var me = this;

        return me.currentScope;
    },

    getUsedCategories: function (lowerCase) {
        var me = this;

        var categories = [];

        me.getStore().getGroups().eachKey(function (key) {
            if (!Ext.isEmpty(key)) {
                categories.push(!lowerCase ? key : key.toLowerCase());
            }
        });

        return Ext.Array.unique(categories);
    },

    addStoredCategory: function (variableField, category) {
        var me = this;

        me.storedCategories.push(category);

        return me;
    },

    removeStoredCategory: function (variableField, category) {
        var me = this;

        Ext.Array.remove(me.storedCategories, category);

        return me;
    },

    getStoredCategories: function (lowerCase) {
        var me = this;

        return !lowerCase
            ? me.storedCategories
            : Ext.Array.map(me.storedCategories, function (category) {
                return category.toLowerCase();
            });
    },

    saveScrollState: function () {
        var me = this;

        me.getGridPanel().getView().saveScrollState();

        return me;
    },

    restoreScrollState: function () {
        var me = this;

        me.getGridPanel().getView().restoreScrollState();

        return me;
    },

    selectRecord: function (record) {
        var me = this;

        var grid = me.getGridPanel();
        grid.expandGroup(record.get('category'));

        var selectionModel = grid.getSelectionModel();
        selectionModel.deselectAll();
        selectionModel.select(record);

        me.getForm().markFieldsInvalid(record);

        return me;
    },

    isValid: function () {
        var me = this;

        var isValid = true;

        Ext.Array.each(me.getStore().data.items, function (record) {
            var validationErrors = record.get('validationErrors') || [];
            if (validationErrors.length) {
                me.selectRecord(record);
                isValid = false;

                return false;
            }
        });

        return isValid;
    },

    validate: function () {
        var me = this;

        me
            .resetFilter()
            .maybeRemoveNewVariable();

        var isValid = me.isValid();

        if (isValid !== me.wasValid) {
            me.wasValid = isValid;
            me.fireEvent('validitychange', me, isValid);
        }

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

    setReadOnly: function (readOnly, changeVisibility) {
        changeVisibility = Ext.isDefined(changeVisibility) ? changeVisibility : false;

        var me = this;

        me.readOnly = readOnly;

        me.getForm().getDockedComponent('deleteButtonContainer').setVisible(!readOnly);

        var addButton = me.getGridPanel().down('#add');

        if (!changeVisibility) {
            addButton.setDisabled(readOnly);
            return me;
        }

        addButton.setVisible(!readOnly);

        return me;
    },

    showForm: function (isVisible) {
        var me = this;

        var form = me.getForm();
        form.setFormVisible(isVisible);

        if (!isVisible) {
            form.variable = null;
        }

        return me;
    },

    getVariableData: function (record) {
        return {
            name: record.get('name'),
            'default': record.get('default'),
            locked: record.get('locked'),
            current: record.get('current'),
            flagDelete: record.get('flagDelete'),
            scopes: record.get('scopes'),
            category: record.get('category')
        };
    },

    getValue: function (includeLockedVariables, doEncode) {
        var me = this;

        var store = me.getStore();
        var records = store.getUnfiltered();
        var variables = [];

        Ext.Array.each(records.items, function (record) {
            var locked = record.get('locked');

            if (includeLockedVariables || !(locked && parseInt(locked.flagFinal))) {
                variables.push(me.getVariableData(record));
            }
        });

        var encodeParams = Ext.isDefined(doEncode) ? doEncode : me.encodeParams;

        return encodeParams ? Ext.encode(variables) : variables;
    },

    setValue: function (value) {
        var me = this;

        value = (me.encodeParams ? Ext.decode(value, true) : value) || [];

        var store = me.getStore();
        store.clearData(true, store.getUnfiltered());
        store.loadData(value);

        me.newVariable = null;

        me
            .showForm(false)
            .markRequiredVariables()
            .initCategories();

        me.getGridPanel()
            .refreshGrouping()
            .down('#add').toggle(false, true);

        me.fireEvent('load', me, value);

        return me;
    },

    markInvalid: function (errors) {
        var me = this;

        me.resetFilter();

        me.validationErrors.variables = errors;

        var variableNames = Ext.Object.getKeys(errors);
        var store = me.getStore();
        var firstRecord = null;

        Ext.Array.each(variableNames, function (name) {
            var variableErrors = errors[name];
            var record = store.findRecord('name', name);

            record.set('validationErrors', Ext.Object.getKeys(variableErrors));

            Ext.Object.each(variableErrors, function (field, errors) {
                me.addValidationError(name, field, errors);
            });

            firstRecord = firstRecord || record;
        });

        me.fireEvent('beforeselect', me);

        me.selectRecord(firstRecord);

        return me;
    },

    getEmptyVariableData: function () {
        var me = this;

        var scope = me.getScope();

        return {
            current: {
                scope: scope
            },
            category: '',
            scopes: [ scope ],
            validationErrors: []
        };
    },

    isNewVariableExist: function () {
        var me = this;

        var newVariable = me.newVariable;

        return !(!newVariable || (newVariable.get('name') &&
            newVariable.get('validationErrors').indexOf('name') === -1));
    },

    addVariable: function () {
        var me = this;

        var record = me.isNewVariableExist()
            ? me.newVariable
            : me.getStore()
                .add(me.getEmptyVariableData())[0];

        me.fireEvent('beforeselect', me);

        record.commit();

        me.getGridPanel()
            .focusOn(record)
            .setSelection(record);

        me.getForm().setValue(record);

        me.newVariable = record;

        return me;
    },

    isVariableEmpty: function (record) {
        var me = this;

        var isEmpty = true;

        Ext.Object.each(record.get('current'), function (fieldName, value) {
            if (fieldName !== 'scope' && !Ext.isEmpty(value) && value !== 0) {
                isEmpty = false;
                return false;
            }
        });

        return isEmpty;
    },

    maybeRemoveNewVariable: function () {
        var me = this;

        var record = me.newVariable;

        if (!Ext.isEmpty(record) && me.isVariableEmpty(record)) {
            me.getStore().remove(record);
            me.newVariable = null;
            me.validationErrors.newVariable = {};
            return me;
        }

        return me;
    },

    removeFromStore: function (record) {
        var me = this;

        var store = me.getStore();
        store.suspendEvents();

        if (!record.get('name') || record === me.newVariable) {
            store.remove(record);
            me.newVariable = null;
        } else {
            record.set('flagDelete', 1);
            record.commit();

            store.filter();
        }

        store.resumeEvents();

        return me;
    },

    removeVariable: function (record) {
        var me = this;

        var category = record.get('category');

        var grid = me.getGridPanel();
        grid.disableGrouping();
        //grid.suspendLayouts();

        me.removeFromStore(record);

        me.showForm(false);

        me.fireEvent('removevariable', me, record, category);

        return me;
    },

    getNames: function () {
        var me = this;

        var names = [];

        Ext.Array.each(me.getStore().getUnfiltered().items, function (record) {
            if (!record.get('flagDelete') && (record.get('validationErrors') || []).indexOf('name') === -1) {
                var name = record.get('name');
                if (Ext.isString(name)) {
                    names.push(name.toLowerCase());
                }
            }
        });

        return names;
    },

    getScopeName: function (scopeId) {
        var me = this;

        return me.scopes[scopeId] || null;
    },

    getScopeData: function (scope) {
        var me = this;

        return {
            id: scope,
            name: me.getScopeName(scope)
        };
    },

    isVariableRequired: function (record) {
        var me = this;

        var current = record.get('current');
        var currentValue = current ? current.value : null;
        var def = record.get('default');
        var defaultValue = def ? def.value : null;
        var locked = record.get('locked');

        return !currentValue && !defaultValue
            && locked && locked.flagRequired === me.getScope();
    },

    markRequiredVariables: function () {
        var me = this;

        var store = me.getStore();

        Ext.Array.each(store.data.items, function (record) {
            if (me.isVariableRequired(record)) {
                record.set('validationErrors', [ 'value' ]);

                var variableName = record.get('name');

                me.addValidationError(
                    variableName,
                    'value',
                    [ variableName + ' is required variable.' ]
                );
            }
        });

        return me;
    },

    showLockedVariables: function (isVisible) {
        var me = this;

        var store = me.getStore();

        if (isVisible) {
            store.removeFilter('finalVariableFilter');
            me.fireEvent('select', me);

            return me;
        }

        store.addFilter(store.finalVariableFilter);
        me.fireEvent('select', me);

        return me;
    },

    isCreationButtonPressed: function () {
        return this.getGridPanel().down('#add').pressed;
    },

    isVariableExists: function (name) {
        return Ext.Array.contains(this.getNames(), name);
    },

    getValidationErrors: function (variableName) {
        var me = this;

        if (Ext.isEmpty(me.validationErrors)) {
            me.validationErrors = {
                variables: {},
                newVariable: {}
            };
        }

        if (Ext.isEmpty(variableName) || !me.isVariableExists(variableName)) {
            return me.validationErrors.newVariable;
        }

        var variablesErrors = me.validationErrors.variables;
        var errors = variablesErrors[variableName];

        if (!Ext.isObject(errors)) {
            variablesErrors[variableName] = {};
            return variablesErrors[variableName];
        }

        return errors;
    },

    addValidationError: function (variableName, fieldName, errorText) {
        var me = this;

        me.getValidationErrors(variableName)[fieldName] = errorText;

        return me;
    },

    removeValidationError: function (variableName, fieldName) {
        var me = this;

        delete me.getValidationErrors(variableName)[fieldName];

        return me;
    },

    beautifyFieldName: function (fieldName) {
        var fieldNames = {
            name: 'Name',
            category: 'Category',
            value: 'Value',
            format: 'Format',
            validator: 'Validation pattern'
        };

        return fieldNames[fieldName];
    },

    beforeTooltipShow: function (rowNode) {
        var me = this;

        var gridView = me.getGridPanel().getView();
        var record = gridView.getRecord(rowNode);

        if (Ext.isEmpty(record)) {
            return false;
        }

        var validationErrors = record.get('validationErrors');

        if (Ext.isEmpty(validationErrors)) {
            return false;
        }

        var variableName = ((record.get('validationErrors') || []).indexOf('name') === -1)
            ? record.get('name')
            : null;
        var validationErrorsTexts = me.getValidationErrors(variableName);
        var invalidText = '';
        var errorsCount = 0;

        Ext.Array.each(validationErrors, function (field, index) {
            var fieldErrors = Ext.Array.map(validationErrorsTexts[field] || [], function (text, index) {
                return ++index + '. ' + text + '<br />';
            });
            var fieldErrorsCount = fieldErrors.length;

            errorsCount = errorsCount + fieldErrorsCount;

            invalidText = invalidText + (index !== 0 ? '<p>' : '')
                + '<b>' + me.beautifyFieldName(field) + '</b><br />'
                + fieldErrors.join('')
                + (index !== 0 ? '</p>' : '');
        });

        return {
            title: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-error"> '
                + errorsCount + ' validation error' + (errorsCount > 1 ? 's' : '') + ' in the '
                + (variableName !== null ? ('"' + variableName + '"') : 'new') + ' variable:',
            msg: invalidText
        };

    },

    resetFilter: function () {
        var me = this;

        var filterField = me.getGridPanel().down('filterfield');

        if (!Ext.isEmpty(filterField.getValue())) {
            filterField.reset();
        }

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
            padding: '1 0 0 12',
            setText: function (text) {
                var me = this;

                me.update('<div style="padding-top: 9px" class="x-fieldset-header-text">' + text + '</div>');

                return me;
            }
        }, {
            xtype: 'grid',
            cls: 'x-form-variablefield-grid',
            style: 'box-shadow: none',
            padding: '0 12 12 12',
            flex: 1,

            viewConfig: {
                preserveScrollOnRefresh: true,
                markDirty: false,
                plugins: {
                    ptype: 'dynemptytext',
                    emptyText: 'No variables found.',
                    emptyTextNoItems: 'You have no variables added yet.'
                },
                loadingText: 'Loading variables ...',
                deferEmptyText: false,
                getRowClass: function (record) {
                    return !Ext.isEmpty(record.get('validationErrors'))
                        ? 'x-grid-row-color-red'
                        : '';
                }
            },

            features: [{
                ftype: 'grouping',
                id: 'categoriesGrouping',
                hideGroupedHeader: true,
                groupHeaderTpl: [
                    '<tpl if="name">',
                        '{[Ext.String.capitalize(values.name.toLowerCase())]}',
                    '<tpl else>',
                        'Uncategorized',
                    '</tpl>',
                    ' ({rows.length})'
                ]
            }],

            plugins: [{
                ptype: 'focusedrowpointer',
                pluginId: 'focusedrowpointer'
            }, {
                ptype: 'rowtooltip',
                pluginId: 'rowtooltip',
                cls: 'x-tip-form-invalid',
                anchor: 'top',
                minWidth: 330,
                beforeShow: function (tooltip) {
                    var invalidText = tooltip.owner.getVariableField()
                        .beforeTooltipShow(tooltip.triggerElement);

                    if (Ext.isObject(invalidText)) {
                        tooltip.setTitle(invalidText.title);
                        tooltip.update(invalidText.msg);
                        return true;
                    }

                    return false;
                }
            }],

            store: {
                fields: [
                    'name',
                    'newValue',
                    'value',
                    'current',
                    'default',
                    'locked',
                    'flagDelete',
                    'scopes',
                    'validationErrors', {
                        name: 'category',
                        convert: function (value) {
                            return Ext.isString(value)
                                ? Ext.String.capitalize(value.toLowerCase())
                                : '';
                        }
                }],
                reader: 'object',
                groupField: 'category',
                filters: [{
                    id: 'deletedVariableFilter',
                    filterFn: function (record) {
                        return !record.get('flagDelete');
                    }
                }],
                finalVariableFilter: {
                    id: 'finalVariableFilter',
                    filterFn: function (record) {
                        var locked = record.get('locked') || {};
                        return !(record.get('default') && parseInt(locked.flagFinal) === 1);
                    }
                },
                createSearchFilter: function (value) {
                    return {
                        id: 'searchFilter',
                        anyMatch: true,
                        property: 'name',
                        value: value
                    };
                },
                search: function (text) {
                    var me = this;

                    if (text) {
                        me.addFilter(me.createSearchFilter(text));
                        return me;
                    }

                    me.removeFilter('searchFilter');
                    return me;
                }
                /**
                todo: variable names event based updating
                listeners: {
                    update: function (me, record, operation, modifiedFieldNames) {
                        if (modifiedFieldNames && modifiedFieldNames[0] === 'flagDelete') {
                        }
                    }
                }
                */
            },

            listeners: {
                select: function (me, record) {
                    var grid = me.view.panel;

                    var variableField = grid.variableField;
                    variableField.fireEvent('beforeselect', variableField);

                    var variableName = record.get('name');
                    var isVariableNew = variableField.isNewVariableExist()
                        && variableField.newVariable.get('name') === variableName;

                    grid.down('#add').toggle(Ext.isEmpty(variableName) || isVariableNew, true);

                    variableField.getForm()
                        .setFormVisible(true)
                        .setValue(record);
                },

                deselect: function (row, record) {
                    var variableField = row.view.panel.getVariableField();
                    var newVariable = variableField.newVariable;

                    if (record === newVariable) {
                        variableField.maybeRemoveNewVariable();
                        return;
                    }
                }

                /*itemkeydown: function (me, record, item, index, e) {
                    var form = me.panel.variableField.getForm();

                    if (e.getKey() === e.TAB && !form.down('[name=newName]').isVisible()) {
                        form.down('[name=flagHidden]').focus();
                    }
                }*/
            },

            getVariableField: function () {
                return this.variableField;
            },

            getGrouping: function () {
                return this.getView().getFeature('categoriesGrouping');
            },

            enableGrouping: function () {
                var me = this;

                me.getGrouping().enable();

                return me;
            },

            disableGrouping: function () {
                var me = this;

                me.getGrouping().disable();

                return me;
            },

            refreshGrouping: function () {
                var me = this;

                me.suspendLayouts();

                var feature = me.getGrouping();
                feature.disable();
                feature.enable();

                me.resumeLayouts(true);

                return me;
            },

            // temp solution
            refreshView: function () {
                var me = this;

                var store = me.getStore();

                store.addFilter({
                    id: 'refreshGroups',
                    filterFn: function () {
                        return true;
                    }
                });

                store.removeFilter('refreshGroups');

                return me;
            },

            expandGroup: function (groupName) {
                var me = this;

                me.getGrouping().expand(groupName);

                return me;
            },

            expandAllGroups: function () {
                var me = this;

                me.getGrouping().expandAll();

                return me;
            },

            getScopeMarkerHtml: function (scope) {
                var me = this;
                me.currentScope = me.currentScope || me.getVariableField().currentScope;

                return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-' + scope +
                    '" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('variable', false, me.currentScope) + '" />';
            },

            getEmptyNameHtml: function (scope) {
                var me = this;

                return me.getScopeMarkerHtml(scope) +
                    '<span style="font-style: italic; color: #999; padding-left: 10px">no name</span>';
            },

            getLockedNameHtml: function (name, scope) {
                var me = this;

                return '<div data-qtip="' + me.getVariableField().getScopeName(scope) +
                    '"class="x-form-variablefield-variable-locked-scope-' +
                    scope + '"></div><span style="color: #999; padding-left: 6px">' + name + '</span>';
            },

            startsWithScalrUiDefault: function (string) {
                return string.toLowerCase().substring(0, 16) === 'scalr_ui_default';
            },

            getNameHtml: function (name, scope, isLocked) {
                var me = this;

                if (Ext.isEmpty(name)) {
                    return me.getEmptyNameHtml(scope);
                }

                name = me.startsWithScalrUiDefault(name) ? name.substring(17) : name;

                if (isLocked) {
                    return me.getLockedNameHtml(name, scope);
                }

                return me.getScopeMarkerHtml(scope) + '<span style="padding-left: 10px">' + name + '</span>';
            },

            getValueColor: function (value) {
                return !value ? '#999' : '#224164';
            },

            getValueLengthHtml: function (length) {
                return '<div style="float: right; color: #999; padding-left: 6px">(' + length + ' chars)</div>';
            },

            getPureValueHtml: function (value, color) {
                return '<span style="font-family: DroidSansMono; color: ' +
                    color + '">' + value + '</span>';
            },

            getLongValueHtml: function (valueHtml, valueLengthHtml) {
                return valueLengthHtml +
                    '<div class="x-form-variablefield-grid-variable-value-long">' + valueHtml + '</div>';
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
                return 'x-form-variablefield-grid-flag-final-' + (!flagFinal ? 'off' : 'on');
            },

            getFlagRequiredClass: function (flagRequired) {
                return !flagRequired ?
                    '' : 'x-form-variablefield-grid-flag-required-scope-' + flagRequired;
            },

            getFlagRequiredTip: function (flagRequired) {
                var me = this;

                return flagRequired ? '<img src="' + Ext.BLANK_IMAGE_URL +
                    '" class="scalr-scope-' + flagRequired + '" /><span style="margin-left: 6px">' +
                    me.getVariableField().getScopeName(flagRequired) + '</span>' :
                    '<span style="font-style: italic">not required</span>';
            },

            getFlagHiddenClass: function (flagHidden) {
                return 'x-form-variablefield-grid-flag-hidden-' + (!flagHidden ? 'off' : 'on');
            },

            getFlagFinalHtml: function (flagFinal) {
                var me = this;

                return '<div class="' + me.getFlagFinalClass(flagFinal) + '" data-qtip="Locked variable"></div>';
            },

            getFlagRequiredHtml: function (flagRequired) {
                var me = this;

                return '<div class="x-form-variablefield-grid-flag-required ' +
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

                return '<div class="x-form-variablefield-grid-cell-flags">' +
                    me.getFlagHiddenHtml(flagHidden) +
                    me.getFlagFinalHtml(flagFinal) +
                    me.getFlagRequiredHtml(flagRequired) +
                    '</div>';
            },

            focusOn: function (record) {
                var me =  this;

                var view = me.getView();
                view.focusRow(record);
                view.scrollBy(0, me.getHeight(), false);
                view.saveScrollState();

                return me;
            },

            dockedItems: [{
                xtype: 'toolbar',
                dock: 'top',
                ui: 'simple',
                padding: '12 0',
                defaults: {
                    flex: 1
                },
                items: [{
                    xtype: 'filterfield',
                    maxWidth: 200,
                    handler: function (field, value) {
                        var variableField = field.up('variablefield');
                        var grid = variableField.getGridPanel();

                        grid.getSelectionModel().deselectAll();
                        variableField.getForm().setFormVisible(false);
                        grid.down('#add').toggle(false, true);

                        grid
                            .expandAllGroups()
                            .getStore().search(value);
                    }
                }, {
                    xtype: 'button',
                    itemId: 'showLocked',
                    margin: '0 0 0 12',
                    maxWidth: 190,
                    enableToggle: true,
                    text: 'Show locked variables',
                    isLockedVariable: function (record) {
                        var locked = record.get('locked') || {};
                        return record.get('default') && parseInt(locked.flagFinal) === 1;
                    },
                    listeners: {
                        afterrender: function (button) {
                            button.up('variablefield').showLockedVariables(false);
                        },
                        toggle: function (button, pressed) {
                            var variableField = button.up('variablefield');
                            variableField.showLockedVariables(pressed);

                            if (!pressed) {
                                var grid = variableField.getGridPanel();
                                var selectedRecord = grid.getSelectionModel().getSelection()[0];

                                if (!Ext.isEmpty(selectedRecord) && button.isLockedVariable(selectedRecord)) {
                                    grid.setSelection();
                                    grid.updateLayout();
                                    variableField.getForm().setFormVisible(false);
                                }
                            }
                        }
                    }
                }, {
                    xtype: 'tbfill',
                    flex: 0.1
                }, {
                    itemId: 'refresh',
                    iconCls: 'x-btn-icon-refresh',
                    maxWidth: 42,
                    tooltip: 'Refresh',
                    handler: function () {
                        Scalr.event.fireEvent('refresh');
                    }
                }, {
                    xtype: 'button',
                    itemId: 'add',
                    text: 'New variable',
                    cls: 'x-btn-green',
                    margin: '0 0 0 12',
                    maxWidth: 115,
                    enableToggle: true,
                    toggleHandler: function (button, state) {
                        var variableField = button.up('variablefield');
                        var grid = variableField.getGridPanel();
                        var form = variableField.getForm();

                        variableField.resetFilter();

                        if (state) {
                            if (variableField.isNewVariableExist()) {
                                grid.expandGroup(
                                    variableField.newVariable.get('category')
                                );
                            } else if (grid.getGrouping().getGroup('').isCollapsed) {
                                grid.expandGroup('');
                            }

                            form.expandFieldSets();
                            variableField.addVariable();
                        } else {
                            grid.setSelection();
                            grid.updateLayout();
                        }

                        form.setFormVisible(state);
                    }
                }]
            }],

            columns: [{
                text: 'Variable',
                name: 'name',
                dataIndex: 'name',
                flex: 0.35,
                minWidth: 100,
                renderer: function (value, meta, record) {
                    var me = this;

                    var current = record.get('current') || {};
                    var def = record.get('default') || {};
                    var locked = record.get('locked') || {};
                    var validationErrors = record.get('validationErrors') || [];
                    var description = locked.description || current.description;

                    return (description ? '<img src="'+Ext.BLANK_IMAGE_URL+'" style="float:right;cursor:default" class="x-grid-icon x-grid-icon-info" data-qtip="'+Ext.String.htmlEncode(Ext.String.htmlEncode(description))+'"/>' + '<span style="cursor:default" data-qtip="'+Ext.String.htmlEncode(Ext.String.htmlEncode(description))+'">' : '') + me.getNameHtml(
                        Ext.String.htmlEncode(record.get('name')),
                        current && (current.value || Ext.Object.isEmpty(def)) ? current.scope : def.scope,
                        parseInt(locked.flagFinal) === 1
                    ) + (description ? '</span>' : '');
                }
            }, {
                text: 'Value',
                flex: 0.65,
                sortable: false,
                renderer: function (value, meta, record) {
                    var me = this;

                    var current = record.get('current') || {};
                    var def = record.get('default') || {};
                    var locked = record.get('locked') || {};
                    var currentValue = current.value;

                    return me.getValueHtml(
                        Ext.String.htmlEncode(currentValue || def.value || ''),
                        me.getValueColor(currentValue)
                    );
                }
            }, {
                text: 'Flags',
                width: 120,
                sortable: false,
                align: 'center',
                renderer: function (value, meta, record) {
                    var me = this;

                    var current = record.get('current') || {};
                    var locked = record.get('locked') || {};

                    return me.getFlagsHtml(
                        parseInt(current.flagFinal) === 1 || parseInt(locked.flagFinal) === 1,
                        current.flagRequired || locked.flagRequired,
                        parseInt(current.flagHidden) === 1 || parseInt(locked.flagHidden) === 1
                    );
                }
            }]
        }]
    }, {
        xtype: 'form',
        name: 'extendedForm',
        style: 'background-color: #f1f5fa',
        width: 400,
        scrollable: true,

        layout: {
            type: 'vbox',
            align: 'stretch'
        },

        getVariableField: function () {
            return this.variableField;
        },

        expandFieldSets: function () {
            var me = this;

            Ext.Array.each(
                me.query('#editVariable, #valueAndFlags'),
                function (fieldSet) {
                    fieldSet.expand();
                }
            );

            return me;
        },

        getScopesData: function (scopes) {
            var me = this;

            var variableField = me.variableField;
            var scopesData = [];

            Ext.Array.each(scopes, function (scope) {
                scopesData.push(variableField.getScopeData(scope));
            });

            return scopesData;
        },

        getNameData: function (name, lastDefinitionsScope, scopes) {
            var me = this;

            var variableField = me.variableField;
            var declarationsScope = scopes[0];

            return {
                name: name,
                lastDefinitionsScope: lastDefinitionsScope,
                lastDefinitionsScopeName: variableField.getScopeName(lastDefinitionsScope),
                declarationsScope: declarationsScope,
                declarationsScopeName: variableField.getScopeName(declarationsScope),
                definitionsScopes: me.getScopesData(scopes)
            };
        },

        setVariableName: function (name, lastDefinitionsScope, scopes) {
            var me = this;

            var nameField = me.down('[name=name]');
            nameField.setVisible(name);

            var newNameField = me.down('[name=newName]');
            newNameField.setVisible(!name);

            if (!Ext.isEmpty(name)) {
                nameField.setValue(me.getNameData(name, lastDefinitionsScope, scopes));
                return me;
            }

            newNameField.variableNames = me.variableField.getNames();
            newNameField
                .setValue()
                .focus()
                .clearInvalid();

            return me;
        },

        setVariableFlags: function (record, disabled) {
            var me = this;

            var current = record.get('current') || {};
            var def = record.get('default') || {};
            var locked = record.get('locked') || {};

            me.down('[name=flags]').setValue({
                disabled: disabled,
                flagRequired: current.flagRequired || locked.flagRequired,
                flagFinal: parseInt(current.flagFinal) === 1 || parseInt(locked.flagFinal) === 1,
                flagHidden: parseInt(current.flagHidden) === 1 || parseInt(locked.flagHidden) === 1
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

            //field.applyValidator(validator);

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

        setVariableDescription: function (description, readOnly) {
            var me = this;

            me.down('[name=description]').
                setValue(description).
                setDisabled(readOnly).
                validate();

            return me;
        },

        setVariableCategory: function (category, readOnly) {
            var me = this;

            var categoryField = me.down('#category');

            categoryField.
                setValue(category).
                setDisabled(readOnly).
                validate();

            /*if (categoryField.editable) {
                categoryField.setHideTrigger(
                    categoryField.getStore().getCount() === 0
                );
            }

            categoryField.updateLayout();*/

            return me;
        },

        setHeaderText: function (isVariableNew) {
            var me = this;

            me.down('fieldset').setTitle(
                !isVariableNew ? 'Edit Variable' : 'New Variable'
            );

            return me;
        },

        setDeletable: function (isDeletable) {
            var me = this;

            me.down('#delete').setDisabled(isDeletable);

            return me;
        },

        setFormVisible: function (isVisible) {
            var me = this;

            Ext.Array.each(me.query('fieldset'), function (fieldSet) {
                fieldSet.setVisible(isVisible);
            });

            me.down('#delete').setVisible(isVisible);

            return me;
        },

        setDeleteTooltip: function (readOnly, name, declarationsScope) {
            var me = this;

            me.down('#delete').setTooltip(
                !readOnly ? '' : '<div style="float: left"><span style="font-style: italic">' +
                name + '</span> is declared in: </div>' +
                '<img style="margin: 0 6px" src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-' +
                declarationsScope + '" />' + me.variableField.getScopeName(declarationsScope) +
                ';<div style="text-align: center">delete it there first</div>'
            );

            return me;
        },

        toggleScalrVariableMode: function (enabled, isScalrDefaultsEditable) {
            var me = this;

            me.down('#category').setReadOnly(enabled);
            me.down('[name=description]').setReadOnly(enabled);

            if (enabled) {
                me.down('[name=format]').disable();
                me.down('[name=validator]').disable();

                var flags = me.down('[name=flags]');
                flags.down('[name=flagHidden]').disable();
                flags.down('[name=flagRequired]').disable();

                if (!isScalrDefaultsEditable) {
                    me.down('[name=value]').disable();
                }
            }

            return me;
        },

        setValue: function (record) {
            var me = this;

            me.isLoading = true;

            if (!me.variable) {
                me.setFormVisible(true);
            }

            me.variable = record;

            var variableField = me.getVariableField();
            var name = Ext.String.htmlEncode(record.get('name'));
            var current = record.get('current') || {};
            var def = record.get('default') || {};
            var locked = record.get('locked') || {};
            var readOnly = !variableField.readOnly
                ? (!Ext.Object.isEmpty(def) || !Ext.Object.isEmpty(locked))
                : true;
            var scopes = Ext.Array.clone(record.get('scopes'));
            var scope = current.value ? current.scope : (def.scope || locked.scope || current.scope);
            var validator = readOnly ? locked.validator : current.validator;
            var description = (readOnly ? locked.description : current.description) || '';
            var validationErrors = record.get('validationErrors') || [];
            var isVariableNew = me.variableField.isCreationButtonPressed();
            var category = (readOnly ? locked.category : current.category) || '';

            me.
                setDeletable(readOnly).
                setDeleteTooltip(readOnly, name, scopes[0]).
                setHeaderText(isVariableNew).
                setVariableName(name, scope, scopes).
                setVariableFlags(record, readOnly).
                setVariableValue(
                    !variableField.readOnly ? parseInt(locked.flagFinal) : true,
                    current.value,
                    def.value,
                    validator
                ).
                setVariableFormat(readOnly ? locked.format : current.format, readOnly).
                setVariableValidator(validator, readOnly).
                setVariableDescription(description, readOnly).
                setVariableCategory(category, readOnly);

            if (Ext.isEmpty(name)) {
                me.setVariableName();
                me.toggleScalrVariableMode(false);
                variableField.fireEvent('select', variableField);
                me.isLoading = false;
                return me;
            }

            var newNameField = me.down('variablenamefield');

            if (validationErrors.indexOf('name') !== -1) {
                me.setVariableName();
                newNameField.setValue(name);
                me.toggleScalrVariableMode(false);
            } else {
                me.toggleScalrVariableMode(newNameField.isScalrDefault(name), variableField.isScalrDefaultsEditable);
            }

            //me.markFieldsInvalid(record);

            variableField.fireEvent('select', variableField);

            me.isLoading = false;

            return me;
        },

        setValidationError: function (field, isValid) {
            var me = this;

            var record = me.variable;
            var name = field !== 'name' ? record.get('name') : null;
            var errors = record.get('validationErrors') || [];
            var fieldNameIndex = errors.indexOf(field);
            var variableField = me.getVariableField();

            if (isValid === true) {
                variableField.removeValidationError(name, field);
                if (fieldNameIndex !== -1) {
                    errors.splice(fieldNameIndex, 1);
                }
            } else if (isValid !== true) {
                variableField.addValidationError(name, field, isValid);
                if (fieldNameIndex === -1) {
                    errors.push(field);
                }
            }

            record.set('validationErrors', Ext.Array.clone(errors));

            return me;
        },

        applyNewVariable: function (name, isValid, preventFocusOnValue) {
            var me = this;

            var record = me.variable;
            var newValueField = me.down('variablenamefield');
            var variableField = me.getVariableField();

            if (newValueField.isScalrDefault(name) && isValid === true) {
                record.set(newValueField.getVariableData(name));
                me.applyCategory(variableField.scalrDefaultsCategoryName, true);
            } else {
                var current = record.get('current');
                current.name = name;
                record.set('current', current);
                record.set('name', name);
            }

            me.setValidationError('name', isValid);

            record.commit();

            if (isValid === true && name) {
                me.setValue(record);

                variableField.fireEvent('addvariable', variableField, record);

                if (preventFocusOnValue) {
                    newValueField.preventFocusOnValue = false;
                    return me;
                }

                me.down('[name=value]').focus(true);
                return me;
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

                var variableField = me.getVariableField();
                variableField.fireEvent('datachanged', variableField, record);
            }

            return me;
        },

        applyFlagRequired: function (isFlagSets) {
            var me = this;

            var record = me.variable;
            var readOnly = !Ext.Object.isEmpty(record.get('default'))
                || !Ext.Object.isEmpty(record.get('locked'));

            var value = function (record, isFlagSets) {
                var flag = '';

                if (isFlagSets) {
                    var locked = record.get('locked');

                    flag = !readOnly
                        ? record.get('current').flagRequired
                        : (!Ext.Object.isEmpty(locked) ? locked.flagRequired : '');

                    flag = !Ext.isEmpty(flag) ? flag : 'farmrole';
                }

                return flag;
            }(record, isFlagSets);

            me.down('[name=requiredScope]')
                .setValue(value)
                .setDisabled(readOnly || Ext.isEmpty(value));

            if (!readOnly && !me.isLoading) {
                var current = record.get('current');
                current.flagRequired = value;

                record.set('current', current);
                record.commit();

                var variableField = me.getVariableField();
                variableField.fireEvent('datachanged', variableField, record);
            }

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

                var variableField = me.getVariableField();
                variableField.fireEvent('datachanged', variableField, record);
            }

            return me;
        },

        applyDescription: function (description) {
            var me = this;

            var record = me.variable;
            var current = record.get('current');
            var readOnly = !Ext.Object.isEmpty(record.get('default')) ||
                !Ext.Object.isEmpty(record.get('locked'));

            if (!readOnly && current.description !== description) {
                current.description = description;

                record.set('current', current);
                record.commit();

                var variableField = me.getVariableField();
                variableField.fireEvent('datachanged', variableField, record);
            }

            return me;
        },

        applyCategory: function (category, isValid) {
            var me = this;

            var record = me.variable;

            if (!Ext.isEmpty(record)) {
                var locked = record.get('locked');
                var readOnly = !Ext.Object.isEmpty(record.get('default')) ||
                    !Ext.Object.isEmpty(locked);
                var current = record.get('current');
                var oldCategory = (readOnly ? locked.category : current.category) || '';

                if (!readOnly && oldCategory !== category) {
                    current.category = category;

                    category = isValid === true ? category : '';

                    me.fireEvent('beforecategorychanged', category);

                    me.setValidationError('category', isValid);

                    record.set('category', category);
                    record.set('current', current);
                    record.commit();

                    me.fireEvent('categorychanged', category, oldCategory);

                    var variableField = me.getVariableField();
                    variableField.fireEvent('datachanged', variableField, record);
                }

                /*var categoryField = me.down('#category');

                if (categoryField.editable) {
                    categoryField.setHideTrigger(
                        categoryField.getStore().getCount() === 0
                    );
                }*/
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

                var variableField = me.getVariableField();
                variableField.fireEvent('datachanged', variableField, record);
            }

            return me;
        },

        applyRequiredScope: function (requiredScope) {
            var me = this;

            var record = me.variable;

            if (record) {
                var current = record.get('current') || {};

                if (current.flagRequired && !me.isLoading) {
                    current.flagRequired = requiredScope;

                    record.set('current', current);
                    record.commit();

                    var variableField = me.getVariableField();
                    variableField.fireEvent('datachanged', variableField, record);
                }

                me.down('[name=flagRequired]').inputValue = requiredScope;
            }

            return me;
        },

        applyFormat: function (format, isValid) {
            var me = this;

            var record = me.variable;
            var current = record.get('current') || {};

            current.format = format;

            me.setValidationError('format', isValid);

            record.set('current', current);
            record.commit();

            var variableField = me.getVariableField();
            variableField.fireEvent('datachanged', variableField, record);

            return me;
        },

        applyValidator: function (validator, parsedValidator, isValid) {
            var me = this;

            var record = me.variable;
            var current = record.get('current') || {};
            var validationErrors = record.get('validationErrors') || [];

            current.validator = validator;

            me.setValidationError('validator', isValid);

            record.set('current', current);
            record.commit();

            /*var valueField = me.down('[name=value]');
            valueField.regex = isValid === true
                ? new RegExp(parsedValidator.regex, parsedValidator.modifiers)
                : '';
            valueField.validate();*/

            var variableField = me.getVariableField();
            variableField.fireEvent('datachanged', variableField, record);

            return me;
        },

        markFieldInvalid: function (fieldName, errors) {
            var me = this;

            var field = me.down('[name=' + fieldName + ']');

            if (!Ext.isEmpty(field)) {
                field.markInvalid(errors);
            }

            return me;
        },

        markFieldsInvalid: function (record) {
            var me = this;

            var validationErrors = record.get('validationErrors');

            if (!Ext.isEmpty(validationErrors)) {
                var variableField = me.getVariableField();

                Ext.Array.each(validationErrors, function (fieldName) {
                    me.markFieldInvalid(
                        fieldName,
                        variableField
                            .getValidationErrors(record.get('name'))[fieldName]
                    );
                });
            }

            return me;
        },

        startsWithScalr: function (variableName) {
            variableName = Ext.isString(variableName) ? variableName : '';
            return variableName.toUpperCase().substring(0, 5) === 'SCALR';
        },

        isScalrDefault: function (variableName) {
            var me = this;

            variableName = !Ext.isDefined(variableName) ? me.variable.get('name') : variableName;

            return me.down('variablenamefield').isScalrDefault(variableName);
        },

        defaults: {
            xtype: 'fieldset',
            width: '100%',
            collapsible: true
        },

        items: [{
            title: 'Edit Variable',
            itemId: 'editVariable',
            height: 220,
            defaults: {
                width: '100%',
                labelWidth: 85
            },
            items: [{
                xtype: 'displayfield',
                fieldLabel: 'Name',
                name: 'name',
                isFormField: false,
                renderer: function (values) {
                    return !Ext.isEmpty(values) ? this.variableNameTpl.apply(values) : '';
                },
                markInvalid: Ext.emptyFn,
                listeners: {
                    beforerender: function (field) {
                        field.variableNameTpl = new Ext.XTemplate(
                            '<div>',
                                '<div style="float: left" data-qtip=\'',

                                    '<div style="border-bottom: 1px solid #fff; padding-bottom: 12px;">',
                                        '<div style="float: left; width: 85px;">Declared in:</div>',
                                        '<img src="' + Ext.BLANK_IMAGE_URL + '" style="margin: 0 6px" class="scalr-scope-{declarationsScope}" />',
                                        '<span>{declarationsScopeName}</span>',
                                    '</div>',

                                    '<div style="margin-top: 12px">',
                                        '<div style="float: left; width: 85px">',
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
                                        '<img src="' + Ext.BLANK_IMAGE_URL + '" style="margin: 0 -9px 10px 0" class="scalr-scope-{declarationsScope}" />',
                                        '<img src="' + Ext.BLANK_IMAGE_URL + '" style="box-shadow: -1px -1px 0 0 #f0f1f4" class="scalr-scope-{lastDefinitionsScope}" />',
                                    '<tpl else>',
                                        '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-{declarationsScope}" />',
                                    '</tpl>',

                                '</div>',

                                '<div class="x-form-variablefield-extendedform-variable-name" style="padding-top: 2px" data-qtip="{name}">{name}</div>',
                            '</div>'
                        );
                    }
                }
            }, {
                xtype: 'variablenamefield',
                name: 'newName',
                fieldLabel: 'Name',
                hidden: true,
                isBrowserFirefox: Ext.browser.is('firefox'),
                listeners: {
                    focus: function (me) {
                        if (me.isBrowserFirefox) {
                            me.resumeEvent('blur');
                        }
                        me.oldValue = me.getValue();
                    },
                    blur: function (me) {
                        if (me.isBrowserFirefox) {
                            me.suspendEvent('blur');
                        }

                        var value = me.getValue();

                        me.up('form').applyNewVariable(
                            value,
                            !me.isValid() ? me.getErrors(value) : true,
                            me.preventFocusOnValue
                        );
                    },
                    specialkey: function (me, e) {
                        var value = me.getValue();

                        if (e.getKey() === e.ENTER && value && me.isValid() && !me.isExpanded) {
                            me.preventFocusOnValue = true;
                            me.blur();
                        }
                    },
                    select: function (me, record) {
                        me.preventFocusOnValue = true;
                        me.blur();
                    }
                }
            }, {
                xtype: 'combo',
                fieldLabel: 'Category',
                itemId: 'category',
                name: 'category',
                store: {
                    type: 'array',
                    fields: [{
                        name: 'category',
                        convert: function (value) {
                            return Ext.String.capitalize(value.toLowerCase());
                        }
                    }],
                    filters: [{
                        id: 'excludeScalrUiDefaults',
                        filterFn: function (record) {
                            return record.get('category') !== 'Scalr_ui_defaults';
                        }
                    }],
                },
                valueField: 'category',
                displayField: 'category',
                queryMode: 'local',
                emptyText: 'Uncategorized',
                triggerAction: 'last',
                lastQuery: '',
                isFormField: false,
                disabled: true,
                hideInputOnReadOnly: true,
                /*vtype: 'rolename',
                vtypeText: 'Category should start and end with letter or number and contain only letters, numbers and dashes.',
                minLength: 2,
                minLengthText: 'Category must be a minimum of 2 characters.',
                maxLength: 32,
                maxLengthText: 'Category must be a maximum of 32 characters.',*/
                displayTpl: [
                    '<tpl for=".">',
                        '<tpl if="values.category === \'Scalr_ui_defaults\'">',
                            'SCALR_UI_DEFAULTS',
                        '<tpl else>',
                            '{[Ext.String.capitalize(values.category.toLowerCase())]}',
                        '</tpl>',
                    '</tpl>'
                ],
                listConfig: {
                    deferEmptyText: false,
                    emptyText: '<div style="margin:8px;font-style:italic;color:#999">You have no categories created yet</div>'
                },
                validator: function (value) {
                    var me = this;

                    if (Ext.isEmpty(value)) {
                        return true;
                    }

                    if (!/^[A-Za-z0-9]+[A-Za-z0-9-_]*[A-Za-z0-9]+$/i.test(value) || value.length > 31) {
                        return 'Category should contain only letters, numbers, dashes and underscores, start and end with letter and be from 2 to 32 chars long.';
                    }

                    if (/^SCALR_.*/i.test(value) && !me.up('form').isScalrDefault()) {
                        return "'SCALR_' prefix is reserved and cannot be used for user GVs.";
                    }

                    return true;
                },
                listeners: {
                    clearvalue: function (me) {
                        me.clearInvalid();
                    },
                    select: function (me, record) {
                        var value = me.getValue();
                        me.up('form').applyCategory(
                            value,
                            !me.isValid() ? me.getErrors(value) : true
                        );
                    },
                    blur: function (me) {
                        if (!me.readOnly) {
                            var value = me.getRawValue();
                            var record = me.getStore().findRecord('category', value, 0, false, false, true);

                            value = !Ext.isEmpty(record)
                                ? record.get('category')
                                : Ext.String.capitalize(value.toLowerCase());

                            me.setRawValue(value);

                            me.up('form').applyCategory(
                                value,
                                !me.isValid() ? me.getErrors(value) : true
                            );
                        }
                    }
                }
            }, {
                xtype: 'textarea',
                name: 'description',
                fieldLabel: 'Description',
                height: 60,
                isFormField: false,
                hideInputOnReadOnly: true,
                listeners: {
                    blur: function (me) {
                        me.up('form').applyDescription(me.getValue());
                    }
                }
            }]
        }, {
            title: 'Value and Flags',
            itemId: 'valueAndFlags',
            flex: 1,
            minHeight: 220,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                items: [{
                    xtype: 'container',
                    name: 'flags',
                    layout: 'hbox',

                    defaults: {
                        xtype: 'buttonfield',
                        cls: 'x-btn-flag',
                        style: 'min-width: 36px',
                        margin: '0 0 0 8',
                        enableToggle: true,
                        isFormField: false
                    },

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

                        if (me.up('form').currentScope === 'farmrole') {
                            me.down('[name=flagRequired]').disable();
                        }
                    },

                    items: [{
                        iconCls: 'x-btn-icon-flag-hidden',
                        name: 'flagHidden',
                        tooltip: 'Do not store this variable on instances, and mask value from view at a lower scope.',
                        inputValue: 1,
                        margin: 0,
                        setFlag: function (value) {
                            var me = this;

                            me.setValue(value);

                            return me;
                        },
                        toggleHandler: function (me) {
                            me.up('form').applyFlagHidden(me.getValue() || 0);
                        }
                    }, {
                        iconCls: 'x-btn-icon-flag-final',
                        name: 'flagFinal',
                        tooltip: 'Cannot be changed at a lower scope.',
                        inputValue: 1,
                        setFlag: function (value) {
                            var me = this;

                            me.setValue(value);
                            me.next().setDisabled(value);

                            return me;
                        },
                        toggleHandler: function (me, state) {
                            var form = me.up('form');
                            form.applyFlagFinal(me.getValue() || 0);

                            var record = form.variable;
                            var variableCurrent = record.get('current');
                            var currentScope = variableCurrent ? variableCurrent.scope : null;

                            me.next().setDisabled(currentScope !== 'farmrole' && !form.startsWithScalr(record.get('name')) ? state : true);
                        }
                    }, {
                        iconCls: 'x-btn-icon-flag-required',
                        name: 'flagRequired',
                        tooltip: 'Shall be set at a lower scope.',
                        setFlag: function (value) {
                            var me = this;

                            me.setValue(value);
                            me.toggle(!Ext.isEmpty(value));
                            me.prev().setDisabled(value);

                            return me;
                        },
                        toggleHandler: function (me, state) {
                            me.up('form').applyFlagRequired(state);
                            me.prev().setDisabled(state);
                        }
                    }]
                }, {
                    xtype: 'cyclealt',
                    name: 'requiredScope',
                    isFormField: false,
                    getItemIconCls: false,
                    flex: 1,
                    margin: '0 0 0 15',
                    disabled: true,
                    changeHandler: function (me, menuItem) {
                        me.up('form').applyRequiredScope(menuItem.value);
                    },
                    getItemText: function (item) {
                        return item.value
                            ? 'Required scope: &nbsp;<img src="'
                                + Ext.BLANK_IMAGE_URL
                                + '" class="' + item.iconCls
                                + '" title="' + item.text + '" />'
                            : item.text;
                    },
                    menu: {
                        cls: 'x-menu-light x-menu-cycle-button-filter',
                        minWidth: 200,
                        items: [{
                            text: 'Not required',
                            value: ''
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
                            iconCls: 'scalr-scope-environment'
                        }, {
                            text: 'Role scope',
                            value: 'role',
                            iconCls: 'scalr-scope-role'
                        }, {
                            text: 'Farm scope',
                            value: 'farm',
                            iconCls: 'scalr-scope-farm'
                        }, {
                            text: 'Farm Role scope',
                            value: 'farmrole',
                            iconCls: 'scalr-scope-farmrole'
                        }]
                    }
                }]
            }, {
                xtype: 'textarea',
                name: 'value',
                flex: 1,
                width: '100%',
                isFormField: false,
                fieldStyle: {
                    fontFamily: 'DroidSansMono'
                },
                disabled: true,
                regexText: 'Value isn\'t valid because of validation pattern.',
                isBrowserFirefox: Ext.browser.is('firefox'),
                validator: function (value) {
                    var me = this;

                    var extendedForm = me.up('form');
                    var record = extendedForm.variable;

                    if (Ext.isEmpty(record)) {
                        return true;
                    }

                    var defaultValue = (record.get('default') || {}).value;
                    var locked = record.get('locked') || {};
                    var flagRequired = locked.flagRequired;

                    if (!value && !defaultValue && flagRequired === extendedForm.currentScope) {
                        return record.get('name') + ' is required variable.';
                    }

                    return true;
                },
                /*applyValidator: function (validator) {
                    var me = this;

                    var validatorField = me.up('form').down('[name=validator]');
                    var isRegexValid = validatorField.validateRegex(validator);

                    if (!Ext.isEmpty(validator) && isRegexValid === true) {
                        var parsedValidator = validatorField.parseValue(validator, true);
                        me.regex = new RegExp(parsedValidator.regex, parsedValidator.modifiers);
                    } else {
                        me.regex = '';
                    }

                    me.validate();

                    return me;
                },*/
                listeners: {
                    focus: function (me) {
                        if (me.isBrowserFirefox) {
                            me.resumeEvent('blur');
                        }
                        me.oldValue = me.getValue();
                    },
                    blur: function (me) {
                        if (me.isBrowserFirefox) {
                            me.suspendEvent('blur');
                        }

                        var value = me.getValue();

                        if (Scalr.flags.betaMode) {
                            // let's check for NULL byte
                            for (var i = 0; i < value.length; i++) {
                                if (value.charCodeAt(i) == 0) {
                                    console.log('detected NULL byte at ' + i + ' position of string: ' + value);
                                }
                            }
                        }

                        me.up('form').applyValue(
                            value,
                            me.oldValue,
                            !me.isValid() ? me.getErrors(value) : true
                        );
                    }
                }
            }]
        }, {
            title: 'Format and Validation Pattern',
            height: 155,
            defaults: {
                labelWidth: 85,
                width: '100%',
                disabled: true
            },
            items: [{
                xtype: 'textfield',
                name: 'format',
                fieldLabel: 'Format',
                style: 'padding: 0 6px 0 0',
                isFormField: false,
                isBrowserFirefox: Ext.browser.is('firefox'),
                validator: function (value) {
                    var test = value.match(/\%/g);

                    if (!value || (test && test.length === 1)) {
                        return true;
                    }

                    return 'Format isn\'t valid.';
                },
                listeners: {
                    focus: function (me) {
                        if (me.isBrowserFirefox) {
                            me.resumeEvent('blur');
                        }
                    },
                    blur: function (me) {
                        if (me.isBrowserFirefox) {
                            me.suspendEvent('blur');
                        }

                        var value = me.getValue();

                        me.up('form').applyFormat(
                            value,
                            !me.isValid() ? me.getErrors(value) : true
                        );
                    }
                }
            }, {
                xtype: 'validatorfield',
                name: 'validator',
                fieldLabel: 'Validation pattern',
                padding: '0 6 0 0',
                isBrowserFirefox: Ext.browser.is('firefox'),
                validator: function (value) {
                    if (value) {
                        try {
                            var regexp = new RegExp(value);
                            regexp.test('test');
                            return true;
                        } catch (e) {
                            return e.message;
                        }
                    }

                    return true;
                },
                listeners: {
                    focus: function (field) {
                        if (field.isBrowserFirefox) {
                            field.resumeEvent('blur');
                        }
                    },
                    change: function (field, value, parsedValue) {
                        if (field.isBrowserFirefox) {
                            field.suspendEvent('blur');
                        }

                        field.up('form')
                            .applyValidator(
                                value,
                                parsedValue,
                                !field.isValid() ? field.getErrors(value) : true
                            )
                            .setValidationError('value', true)
                            .down('[name=value]').clearInvalid();
                    }
                }
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            itemId: 'deleteButtonContainer',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            maxWidth: 400,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [{
                xtype: 'button',
                itemId: 'delete',
                text: 'Delete',
                cls: 'x-btn-red',
                handler: function (button) {
                    var variableField = button.up('variablefield');

                    variableField
                        .removeVariable(
                            button.up('form').variable
                        )
                        .down('#add')
                            .toggle(false, true);
                }
            }]
        }]
    }]
});

Ext.define('Scalr.ui.variablefield.NameField', {
    extend: 'Ext.form.field.ComboBox',
    alias: 'widget.variablenamefield',

    isFormField: false,
    displayField: 'name',
    valueField: 'name',
    queryMode: 'local',
    autoSearch: false,
    pickerAlign: 'tr-br',
    hideTrigger: true,
    allowChangeable: false,
    allowChangeableMsg: 'Variable name, cannot be changed.',
    allowBlank: false,
    validateOnChange: true,
    /*minLength: 2,
    minLengthText: 'Variable names must be a minimum of 2 characters.',
    maxLength: 50,
    maxLengthText: 'Variable names can be a maximum of 50 character.',*/

    store: {
        proxy: 'object',
        fields: [ 'name', 'description' ]
    },

    listConfig: {
        cls: 'x-form-variablefield-namefield-boundlist',
        maxWidth: 400,
        tpl: [
            '<tpl for=".">',
                '<div class="x-boundlist-item">',
                    '<div class="x-variable-name"><span class="x-semibold">{name}</span></div>',
                    '<div class="x-variable-description">{current.description}</div>',
                '</div>',
            '</tpl>'
        ]
    },

    startsWithScalr: function (string) {
        string = Ext.isString(string) ? string : '';
        return string.toLowerCase().substring(0, 5) === 'scalr';
    },

    isScalrDefault: function (variableName) {
        return !Ext.isEmpty(this.getStore().findRecord('name', variableName, 0, false, true, true));
    },

    getVariableData: function (variableName) {
        var me = this;

        var record = me.getStore().findRecord('name', variableName);
        return !Ext.isEmpty(record) ? record.getData() : {};
    },

    expand: function () {
        var me = this;

        var value = me.getValue();

        if (Ext.isEmpty(value) || !me.startsWithScalr(value) || !me.isScalrDefaultsEditable) {
            return;
        }

        me.callParent();
    },

    validator: function (value) {
        var me = this;

        if (Ext.isEmpty(value)) {
            return true;
        }

        if (!/^[A-Za-z]{1,1}[A-Za-z0-9_]{1,127}$/.test(value)) {
            return 'Name should contain only letters, numbers and underscores, start with letter and be from 2 to 128 chars long.';
        }

        /*if (!/^\w+$/.test(value)) {
            return 'Variable names can only contain letters, numbers and underscores _';
        }

        if (!/^[A-Za-z]/.test(value[0])) {
            return 'Variable names must start with a letter.';
        }*/

        if (/^SCALR_.*/i.test(value) && !Ext.Array.contains(me.getStore().collect('name'), value)) {
            return "'SCALR_' prefix is reserved and cannot be used for user GVs.";
        }

        if (me.variableNames.indexOf(value.toLowerCase()) !== -1) {
            return 'This variable name is already in use.';
        }

        return true;
    }

    /*beforeQuery: function (queryPlan) {
        var me = this;

        var value = queryPlan.query;
        if (Ext.isEmpty(value) || value.substring(0, 5) !== 'SCALR') {
            queryPlan.cancel = true;
            me.validate();
        }

        return me.callParent([arguments]);
    },*/
});

Ext.define('Scalr.ui.ValidatorField', {
    extend: 'Ext.form.FieldContainer',

    alias: 'widget.validatorfield',

    mixins: {
        field: 'Ext.form.field.Field'
    },

    cls: 'x-form-validatorfield',

    layout: 'hbox',

    getRegexField: function () {
        return this.down('#regex');
    },

    getModifiersField: function () {
        return this.down('#modifiers');
    },

    getRegex: function () {
        return this.getRegexField().getValue();
    },

    getModifiers: function () {
        return this.getModifiersField().getValue();
    },

    toggleIconsCls: function (cls, state) {
        var me = this;

        Ext.Array.each(
            me.query('#beforeSlash, #afterSlash'),
            function (iconCmp) {
                var hasCls = iconCmp.hasCls(cls);

                if (state && !hasCls) {
                    iconCmp.addCls(cls);
                } else if (!state && hasCls) {
                    iconCmp.removeCls(cls);
                }

                return true;
            }
        );

        return me;
    },

    enable: function () {
        var me = this;

        me.disabled = false;

        me.getRegexField().enable();
        me.getModifiersField().enable();
        me.toggleIconsCls('disabled', false);

        return me;
    },

    disable: function () {
        var me = this;

        me.disabled = true;

        me.getRegexField().disable();
        me.getModifiersField().disable();
        me.toggleIconsCls('disabled', true);

        return me;
    },

    markInvalid: function (errors) {
        var me = this;

        var regexField = me.getRegexField();
        regexField.markInvalid(errors);

        regexField.on('blur', function () {
            me.toggleIconsCls('invalid', false);
        }, me, {
            single: true
        });

        me.toggleIconsCls('invalid', true);
    },

    clearInvalid: function () {
        var me = this;

        me.callParent();

        me.getRegexField().clearInvalid();
        me.getModifiersField().clearInvalid();

        me.toggleIconsCls('invalid', false);
    },

    isValid: function () {
        var me = this;

        return me.disabled || (me.getRegexField().isValid() && me.getModifiersField().isValid());
    },

    getErrors: function () {
        var me = this;

        return Ext.Array.merge(
            me.getRegexField().getErrors(),
            me.getModifiersField().getErrors()
        );
    },

    parseValue: function (value, excludeUnusedModifiers) {
        var me = this;

        var parsedValue = {
            regex: value,
            modifiers: ''
        };

        if (!Ext.isEmpty(value) && value.charAt(0) === '/') {
            var lastRegexCharIndex = value.lastIndexOf('/');

            if (lastRegexCharIndex !== 0) {
                parsedValue.regex = value.substring(1, lastRegexCharIndex);

                var modifiers = value.substring(lastRegexCharIndex + 1);
                parsedValue.modifiers = excludeUnusedModifiers === true
                    ? me.excludeUnusedModifiers(modifiers)
                    : modifiers;

            }
        }

        return parsedValue;
    },

    setValue: function (value) {
        value = Ext.isString(value) ? value : '';

        var me = this;

        me.toggleIconsCls('invalid', false);

        var parsedValue = me.parseValue(value);

        me.getRegexField().setValue(parsedValue.regex);
        me.getModifiersField().setValue(parsedValue.modifiers);

        return me;
    },

    excludeUnusedModifiers: function (modifiers) {
        modifiers = Ext.isString(modifiers) ? modifiers.split('') : modifiers;

        return Ext.Array.difference(modifiers, ['x', 'X', 's', 'u', 'U', 'A', 'J']).join('');
    },

    getValue: function () {
        var me = this;

        var regex = me.getRegex();

        return !Ext.isEmpty(regex)
            ? '/' + regex + '/' + me.getModifiers()
            : '';
    },

    getParsedValue: function () {
        var me = this;

        return {
            regex: me.getRegex(),
            modifiers: me.excludeUnusedModifiers(
                me.getModifiers()
            )
        };
    },

    validateRegex: function (regex) {
        var me = this;

        var regexField = me.getRegexField();

        regex = !Ext.isEmpty(regex) ? regex : regexField.getValue();

        return regexField.validator(regex);
    },

    initComponent: function () {
        var me = this;

        var beforeSlash = {
            xtype: 'component',
            itemId: 'beforeSlash',
            cls: 'x-form-regexfield-trigger-slash x-form-regexfield-trigger-slash-before'
        };

        var afterSlash = {
            xtype: 'component',
            itemId: 'afterSlash',
            cls: 'x-form-regexfield-trigger-slash x-form-regexfield-trigger-slash-after'
        };

        var regexField = {
            xtype: 'textfield',
            itemId: 'regex',
            cls: 'x-form-regexfield',
            flex: 1,
            emptyText: 'Regular expression',
            isFormField: false
        };

        var modifiersField = {
            xtype: 'textfield',
            itemId: 'modifiers',
            width: 75,
            margin: '0 0 0 12',
            isFormField: false,
            emptyText: 'Modifiers',
            regex: /^[imsxADSUXu]+$/,
            regexText: 'Possible modifiers are: i, m, s, x, A, D, S, U, X, u.'
        };

        me.items = [ beforeSlash, regexField, afterSlash, modifiersField ];

        me.plugins = [{
            ptype: 'fieldicons',
            align: 'right',
            position: 'outer',
            icons: {
                id: 'info',
                tooltip: 'Validation pattern field and variable’s value will be validated only on the server side.'
            }
        }];

        me.callParent();
    },

    initEvents: function () {
        var me = this;

        me.callParent();

        me.getRegexField().on({
            validitychange: function (field, isValid) {
                me.toggleIconsCls('invalid', !isValid);
            },
            change: function () {
                me.toggleIconsCls('invalid', false);
            },
            blur: function () {
                me.toggleIconsCls('invalid', false);
                me.fireEvent('change', me, me.getValue(), me.getParsedValue());
            },
            scope: me
        });

        me.getModifiersField().on('blur', function () {
            me.fireEvent('change', me, me.getValue(), me.getParsedValue());
        }, me);
    }
});

Ext.define('Scalr.ui.VariableField', {
    extend: 'Ext.container.Container',

    mixins: {
        field: 'Ext.form.field.Field'
    },

    alias: 'widget.variablefield',

    cls: 'x-form-variablefield',

    preserveScrollOnRefresh: true,

    currentScope: 'env',

    encodeParams: true,

    dirty: false,

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

    categoriesCreatingScopes: [
        'scalr',
        'account',
        'env',
        'environment'
    ],

    allowCategoriesCreating: true,

    initCategories: function () {
        var me = this;

        me.storedCategories = me.getUsedCategories();

        var categoriesStore = me.getCategoriesStore();
        categoriesStore.removeAll();
        categoriesStore.add(
            Ext.Array.map(me.storedCategories, function (category) {
                return [ category ];
            })
        );

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
            addvariable: function (variableField) {
                variableField.down('#add').toggle(false, true);
            },
            addcategory: me.addStoredCategory,
            removecategory: me.removeStoredCategory,
            scope: me
        });

        me.getStore().on({
            update: grid.refreshView,
            scope: grid
        });

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

        var variableNames = Ext.Object.getKeys(errors);
        var store = me.getStore();
        var firstRecord = null;

        Ext.Array.each(variableNames, function (name) {
            var record = store.findRecord('name', name);
            record.set('serverErrors', Ext.Object.getValues(errors[name]));

            firstRecord = firstRecord || record;
        });

        me.fireEvent('beforeselect', me);

        me.selectRecord(firstRecord);

        return me;
    },

    createNewVariable: function () {
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
            : me.getStore().add(me.createNewVariable())[0];

        me.fireEvent('beforeselect', me);

        var grid = me.getGridPanel();
        record.commit();
        grid.setSelection(record);

        me.getForm().setValue(record);

        me.newVariable = record;

        return me;
    },

    removeFromStore: function (record) {
        var me = this;

        var store = me.getStore();
        store.suspendEvents();

        if (!record.get('name')) {
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

        Ext.Array.each(me.getStore().data.items, function (record) {
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
                record.set('validationErrors', new Array('value'));
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
                deferEmptyText: false
            },

            features: [{
                ftype: 'grouping',
                id: 'categoriesGrouping',
                hideGroupedHeader: true,
                groupHeaderTpl: [
                    '<tpl if="name">',
                        '<b>{[Ext.String.capitalize(values.name.toLowerCase())]}</b>',
                    '<tpl else>',
                        '<b>Uncategorized</b>',
                    '</tpl>',
                    ' ({rows.length})'
                ]
            }],

            plugins: {
                ptype: 'focusedrowpointer',
                pluginId: 'focusedrowpointer'
            },

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
                    'validationErrors',
                    'serverErrors', {
                        name: 'category',
                        convert: function (value) {
                            return Ext.String.capitalize(value.toLowerCase());
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

                    /**
                    todo: relayEvents from grid to variablefield
                    */
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
                /*itemkeydown: function (me, record, item, index, e) {
                    var form = me.panel.variableField.getForm();

                    if (e.getKey() === e.TAB && !form.down('[name=newName]').isVisible()) {
                        form.down('[name=flagHidden]').focus();
                    }
                }*/
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

                return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-' + scope +
                    '" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('variable') + '" />';
            },

            getEmptyNameHtml: function (scope) {
                var me = this;

                return me.getScopeMarkerHtml(scope) +
                    '<span style="font-style: italic; color: #999; padding-left: 10px">no name</span>';
            },

            getLockedNameHtml: function (name, scope) {
                var me = this;

                return '<div data-qtip="' + me.up('variablefield').getScopeName(scope) +
                    '"class="x-form-variablefield-variable-locked-scope-' +
                    scope + '"></div><span style="color: #999; padding-left: 6px">' + name + '</span>';
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

                return me.getScopeMarkerHtml(scope) + '<span style="padding-left: 10px">' + name + '</span>';
            },

            getValueColor: function (value) {
                return !value ? '#999' : '#000';
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
                    me.up('variablefield').getScopeName(flagRequired) + '</span>' :
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

                return '<div>' +
                    me.getFlagFinalHtml(flagFinal) +
                    me.getFlagRequiredHtml(flagRequired) +
                    me.getFlagHiddenHtml(flagHidden) +
                    '</div>';
            },

            focusOn: function (record) {
                var me =  this;

                var view = me.getView();
                view.focusRow(record);
                view.scrollBy(0, me.getHeight());
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
                    handler: function (me, value) {
                        me.up('grid')
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
                    listeners: {
                        afterrender: function (me) {
                            me.up('variablefield').showLockedVariables(false);
                        },
                        toggle: function (me, status) {
                            me.up('variablefield').showLockedVariables(status);
                        }
                    }
                }, {
                    xtype: 'tbfill',
                    flex: 0.1
                }, {
                    xtype: 'button',
                    itemId: 'add',
                    text: 'New variable',
                    cls: 'x-btn-green',
                    maxWidth: 115,
                    enableToggle: true,
                    toggleHandler: function (button, state) {
                        var variableField = button.up('variablefield');
                        var grid = variableField.getGridPanel();

                        if (state) {
                            if (variableField.isNewVariableExist()) {
                                grid.expandGroup(
                                    variableField.newVariable.get('category')
                                );
                            } else if (grid.getGrouping().getGroup('').isCollapsed) {
                                grid.expandGroup('');
                            }
                            variableField.addVariable();
                        } else {
                            grid.setSelection();
                            grid.updateLayout();
                        }

                        variableField.getForm().setFormVisible(state);
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
                        parseInt(locked.flagFinal) === 1,
                        !(record.get('serverErrors') || validationErrors.length)
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
                width: 112,
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
        xtype: 'container',
        name: 'extendedForm',
        style: 'background-color: #f1f5fa',
        width: 400,
        layout: 'vbox',

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

            if (name) {
                nameField.update(me.getNameData(name, lastDefinitionsScope, scopes));

                return me;
            }

            newNameField.variableNames = me.variableField.getNames();
            newNameField.setValue().focus();

            return me;
        },

        setVariableFlags: function (record) {
            var me = this;

            var current = record.get('current') || {};
            var def = record.get('default') || {};
            var locked = record.get('locked') || {};

            me.down('[name=flags]').setValue({
                disabled: !Ext.Object.isEmpty(def) || !Ext.Object.isEmpty(locked),
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

            field.applyValidator(validator);

            return me;
        },

        setVariableRequiredScope: function (flagRequired, scope, readOnly) {
            var me = this;

            var field = me.down('[name=requiredScope]');

            field.
                filterStore(!readOnly, scope, me.currentScope).
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

        getErrorText: function (errors) {
            var text = [];

            Ext.Array.each(errors, function (error) {
                text.push('<div>' + error + ' </div>');
            });

            return text.join('');
        },

        setHeaderText: function (errors, isVariableNew) {
            var me = this;

            var subHeader = errors ? ('<div style="color: #f04a46">' + me.getErrorText(errors) + '</div>') : '';

            me.down('fieldset').setTitle(
                !isVariableNew ? 'Edit Variable' : 'New Variable',
                subHeader
            );

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
                declarationsScope + '" />' + me.variableField.getScopeName(declarationsScope) +
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
            var current = record.get('current') || {};
            var def = record.get('default') || {};
            var locked = record.get('locked') || {};
            var readOnly = (!Ext.Object.isEmpty(def) || !Ext.Object.isEmpty(locked));
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
                setHeaderText(record.get('serverErrors'), isVariableNew).
                setVariableName(name, scope, scopes).
                setVariableRequiredScope(readOnly ? locked.flagRequired : current.flagRequired, scope, readOnly).
                setVariableFlags(record).
                setVariableValue(parseInt(locked.flagFinal), current.value, def.value, validator).
                setVariableFormat(readOnly ? locked.format : current.format, readOnly).
                setVariableValidator(validator, readOnly).
                setVariableDescription(description, readOnly).
                setVariableCategory(category, readOnly);

            var variableField = me.up('variablefield');

            if (!name) {
                me.setVariableName();
                variableField.fireEvent('select', variableField);
                return me;
            }

            if (validationErrors.indexOf('name') !== -1) {
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

                var variableField = me.up('variablefield');
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

                    category = isValid ? category : '';

                    me.fireEvent('beforecategorychanged', category);

                    me.setValidationError('category', isValid);

                    record.set('category', category);
                    record.set('current', current);
                    record.commit();

                    me.fireEvent('categorychanged', category, oldCategory);

                    var variableField = me.up('variablefield');
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

                var variableField = me.up('variablefield');
                variableField.fireEvent('datachanged', variableField, record);
            }

            return me;
        },

        applyRequiredScope: function (requiredScope) {
            var me = this;

            var record = me.variable;

            if (record) {
                var current = record.get('current') || {};

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
            var current = record.get('current') || {};

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
            var current = record.get('current') || {};
            var validationErrors = record.get('validationErrors') || [];

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

        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            flex: 1,
            width: '100%',
            autoScroll: true,
            hidden: true,
            hideMode: 'visibility',
            layout: 'vbox',
            defaults: {
                width: '100%'
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
                                    '<div style="float: left; width: 85px">Declared in:</div>',
                                    '<img src="' + Ext.BLANK_IMAGE_URL + '" style="margin: 0 6px" class="scalr-scope-{declarationsScope}" />',
                                    '<span>{declarationsScopeName}</span>',
                                '</div>',

                                '<div style="margin-top: 6px">',
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
                                    '<img src="' + Ext.BLANK_IMAGE_URL + '" style="margin: 0 -7px 8px 0" class="scalr-scope-{declarationsScope}" />',
                                    '<img src="' + Ext.BLANK_IMAGE_URL + '" style="box-shadow: -1px -1px 0 0 #f0f1f4" class="scalr-scope-{lastDefinitionsScope}" />',
                                '<tpl else>',
                                    '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-{declarationsScope}" />',
                                '</tpl>',

                            '</div>',

                            '<div class="x-form-variablefield-extendedform-variable-name">{name}</div>',

                        '</div>'
                    ]
                }, {
                    xtype: 'textfield',
                    name: 'newName',
                    width: 190,
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

                        if (me.variableNames.indexOf(value.toLowerCase()) !== -1) {
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
                    flex: 0.01
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
                            me.down('[name=flagRequired]').disable();
                        }
                    },

                    items: [{
                        xtype: 'buttonfield',
                        cls: 'x-btn-flag',
                        iconCls: 'x-btn-icon-flag-final',
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

                            var variableCurrent = extendedForm.variable.get('current');
                            var currentScope = variableCurrent ? variableCurrent.scope : null;

                            me.next().setDisabled(currentScope !== 'farmrole' ? state : true);
                        }
                    }, {
                        xtype: 'buttonfield',
                        cls: 'x-btn-flag',
                        iconCls: 'x-btn-icon-flag-required',
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
                        cls: 'x-btn-flag',
                        iconCls: 'x-btn-icon-flag-hidden',
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
                    fontFamily: 'DroidSansMono'
                },
                disabled: true,
                validator: function (value) {
                    var me = this;

                    var extendedForm = me.up('[name=extendedForm]');
                    var record = extendedForm.variable;
                    var defaultValue = (record.get('default') || {}).value;
                    var locked = record.get('locked') || {};
                    var flagRequired = locked.flagRequired;

                    if (!value && !defaultValue && flagRequired === extendedForm.currentScope) {
                        return record.get('name') + ' is required variable';
                    }

                    return true;
                },
                applyValidator: function (validator) {
                    var me = this;

                    var isRegexValid = me.next('[name=validator]').validator(validator);

                    me.regex = (validator && isRegexValid && typeof isRegexValid !== 'string') ?
                        new RegExp(validator) : '';

                    me.validate();

                    return me;
                },
                listeners: {
                    focus: function (me) {
                        me.oldValue = me.getValue();
                    },
                    blur: function (me) {
                        if (Scalr.flags.betaMode) {
                            var value = me.getValue();
                            // let's check for NULL byte
                            for (var i = 0; i < value.length; i++) {
                                if (value.charCodeAt(i) == 0) {
                                    console.log('detected NULL byte at ' + i + ' position of string: ' + value);
                                }
                            }
                        }

                        me.up('[name=extendedForm]').applyValue(me.getValue(), me.oldValue, me.isValid());
                    }
                }
            }, {
                xtype: 'combo',
                itemId: 'category',
                margin: '6 0 0 0',
                fieldLabel: 'Category',
                store: {
                    type: 'array',
                    fields: [{
                        name: 'category',
                        convert: function (value) {
                            return Ext.String.capitalize(value.toLowerCase());
                        }
                    }]
                },
                valueField: 'category',
                displayField: 'category',
                queryMode: 'local',
                emptyText: 'Uncategorized',
                triggerAction: 'last',
                lastQuery: '',
                isFormField: false,
                disabled: true,
                vtype: 'rolename',
                vtypeText: 'Category should start and end with letter or number and contain only letters, numbers and dashes.',
                minLength: 2,
                minLengthText: 'Category must be a minimum of 2 characters.',
                maxLength: 32,
                maxLengthText: 'Category must be a maximum of 32 characters.',
                displayTpl: [
                    '<tpl for=".">',
                        '{[Ext.String.capitalize(values.category.toLowerCase())]}',
                    '</tpl>'
                ],
                listConfig: {
                    deferEmptyText: false,
                    emptyText: '<div style="margin:8px;font-style:italic;color:#999">You have no categories created yet</div>'
                },
                listeners: {
                    clearvalue: function (me) {
                        me.clearInvalid();
                        //me.up('[name=extendedForm]').applyCategory('', true);
                    },
                    select: function (me, record) {
                        me.up('[name=extendedForm]').applyCategory(
                            me.getValue(),
                            me.isValid()
                        );
                    },
                    blur: function (me) {
                        var value = me.getRawValue();
                        var record = me.getStore().findRecord('category', value, 0, false, false, true);

                        value = !Ext.isEmpty(record)
                            ? record.get('category')
                            : Ext.String.capitalize(value.toLowerCase());

                        me.setRawValue(value);

                        me.up('[name=extendedForm]').applyCategory(value, me.isValid());
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
                /*
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
                */
                listConfig: {
                    getInnerTpl: function () {
                        return '<img src="' + Ext.BLANK_IMAGE_URL +
                            '" class="scalr-scope-{id}" /><span style="padding-left: 6px; height: 26px">{name}</span>';
                    }
                },
                setRawValue: function (value) {
                    var me = this;

                    value = me.transformRawValue(value) || '';
                    me.rawValue = value;

                    if (me.inputEl) {
                        me.inputEl.dom.value = value;
                        me.inputEl.dom.innerHTML = value;
                    }

                    return value;
                },
                filterStore: function (doFilter, scope, currentScope) {
                    var me = this;

                    var store = me.getStore();
                    store.clearFilter();

                    if (doFilter) {
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

                    return me;
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
                    blur: function (me) {
                        me.up('[name=extendedForm]').applyValidator(me.getValue(), me.isValid());
                    }
                }
            },{
                xtype: 'textarea',
                name: 'description',
                margin: '6 0 0 0',
                fieldLabel: 'Description',
                height: 60,
                isFormField: false,
                listeners: {
                    blur: function (me) {
                        me.up('[name=extendedForm]').applyDescription(me.getValue());
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
                cls: 'x-btn-red',
                margin: '12 0 24 110',
                height: 32,
                width: 150,
                hidden: true,
                handler: function (me) {
                    var variableField = me.up('variablefield');

                    variableField
                        .removeVariable(
                            me.up('[name=extendedForm]').variable
                        )
                        .down('#add').toggle(false, true);
                }
            }]
        }]
    }]
});

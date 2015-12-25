// ready for extjs 5
Ext.define('Scalr.ui.FilterFieldPicker', {
    extend: 'Ext.form.Panel',
    alias: 'trigger.filterfieldpicker',

    cls: 'x-form-filterfield-picker-form',

    floating: true,
    focusable: true,
    tabIndex: 0,
    hidden: true,
    shadow: false,

    fieldDefaults: {
        anchor: '100%'
    },

    initEvents: function () {
        var me = this;

        me.callParent();

        me.oldValues = {};

        var filterField = me.pickerField;
        var form = me.getForm();

        form.getFields().each(function (field) {
            field.on('specialkey', function (field, e) {
                var key = e.getKey();

                if (key === e.ESC) {
                    form.reset();
                    form.setValues(me.oldValues);
                    filterField.collapse();
                }

                if (key === e.ENTER) {
                    filterField.collapse();
                    filterField.doForceChange();
                }
            });
        });
    }
});

// ready for extjs 5
Ext.define('Scalr.ui.FormFilterField', {
    extend: 'Ext.form.field.Picker',
    alias: 'widget.filterfield',

    isFilterField: true,

    cls: 'x-form-filterfield',

    filterId: 'filterfield',

    width: 250,

    hideTrigger: false,

    enableKeyEvents: true,

    filterFields: [],

    remoteSort: false,

    checkChangeBuffer: 300,

    forceSearchButonVisibility: false,

    config: {
        triggers: {
            cancelButton: {
                cls: 'x-form-filterfield-trigger-cancel-button',
                hidden: true,
                handler: 'resetFilter',
                toggleVisibility: function () {
                    var me = this;

                    me.setVisible(!me.isVisible());

                    return me;
                },
                scope: 'this'
            },
            picker: {
                cls: 'x-form-filterfield-trigger-picker-button',
                weight: 1,
                handler: 'onTriggerClick',
                scope: 'this'
            },
            searchButton: {
                cls: 'x-form-filterfield-trigger-search-button',
                weight: 2,
                handler: 'doForceChange',
                scope: 'this'
            }
        }
    },

    hasStore: function () {
        return Ext.isDefined(this.store);
    },

    getStore: function () {
        return this.store;
    },

    getRemoteSort: function () {
        return this.getStore().remoteSort;
    },

    getRemoteFilter: function () {
        return this.getStore().remoteFilter;
    },

    getFilterFields: function () {
        return this.filterFields;
    },

    hasForm: function () {
        return Ext.isDefined(this.form);
    },

    getForm: function () {
        return this.form;
    },

    createForm: function () {
        var me = this;

        return new Scalr.ui.FilterFieldPicker(Ext.apply(me.getForm(), {
            pickerField: me
        }));
    },

    hasMenu: function () {
        return Ext.isDefined(this.menu);
    },

    getMenu: function () {
        return this.menu;
    },

    createMenu: function () {
        var me = this;

        return Ext.widget(me.getMenu(), {
            keepVisibleEls: []
        }).
            on('hide', function () {
                me.collapse();
            });
    },

    hasHandler: function () {
        return Ext.isFunction(this.handler);
    },

    applyTriggers: function (triggers) {
        var me = this;

        var picker = triggers.picker;
        picker.hidden = !me.hasForm() && !me.hasMenu();

        var searchButton = triggers.searchButton;
        searchButton.hidden = !(me.hasStore() && (me.getRemoteSort() || me.getRemoteFilter()))
            && !me.forceSearchButonVisibility;

        return me.callParent([triggers]);
    },

    initComponent: function () {
        var me = this;

        me.callParent();

        if (me.hasStore()) {
            me.remoteSort = me.remoteSort || me.getRemoteSort() || me.getRemoteFilter();

            if (me.remoteSort) {
                me.addCls('x-form-filterfield-remote');
            }
        } else if (me.forceSearchButonVisibility) {
            me.addCls('x-form-filterfield-remote');
        }

        if (!Ext.isDefined(me.emptyText)) {
            me.emptyText = me.remoteSort ? 'Search' : 'Filter';
        }
    },

    initEvents: function () {
        var me = this;

        me.callParent();

        me.on('change', function (me, value, oldValue) {

            if (!me.hasHandler()) {
                me.applyFilter(value);
            } else {
                me.handler(me, value, oldValue);
            }

            if (me.isValueInverted(value, oldValue)) {
                me
                    .toggleFocusCls()
                    .getTrigger('cancelButton')
                        .toggleVisibility();

                me.updateLayout();
            }

        }, me);

        me.on('specialkey', function (me, e) {
            var key = e.getKey();

            if (key === e.ESC) {
                me.resetFilter();
                e.stopEvent();
            }

            if (key === e.ENTER && (me.remoteSort || me.forceSearchButonVisibility)) {
                me.doForceChange();
            }
        });
    },

    onTriggerClick: function () {
        var me = this;

        if (me.hasForm() || me.hasMenu()) {
            me.callParent();
        }
    },

    // Fixed bug with FilterField's resetting (until the checkChangeBuffer's time has elapsed)
    onFieldMutation: function (e) {
        // When using propertychange, we want to skip out on various values, since they won't cause
        // the underlying value to change.
        var me = this,
            task = me.checkChangeTask;
        if (!(e.type == 'propertychange' && me.ignoreChangeRe.test(e.browserEvent.propertyName))) {
            if (!task) {
                me.checkChangeTask = task = new Ext.util.DelayedTask(me.doCheckChangeTask,me);
            }
            if (!me.bindNotifyListener) {
                // We continually create/destroy the listener as needed (see doCheckChangeTask) because we're listening
                // to a global event, so we don't want the event to be triggered unless absolutely necessary. In this case,
                // we only need to fix the value when we have a pending change to check.
                me.bindNotifyListener = Ext.on('beforebindnotify', me.onBeforeNotify, me, {
                    destroyable: true
                });
            }
            /** Changed */
            if (me.remoteSort) {
                me.rawValue = me.inputEl.getValue();
            }
            /** End */
            task.delay(me.checkChangeBuffer);
        }
    },

    checkChange: function () {
        var me = this;

        if (!me.remoteSort) {
            me.callParent();
        }

        return me;
    },

    doForceChange: function () {
        var me = this;

        if (!me.isDestroyed) {
            var picker = me.getTrigger('picker');

            if (!Ext.isEmpty(picker) && picker.isVisible()) {
                me.collapse();
            }

            var newValue = me.getValue();
            var oldValue = me.lastValue;

            me.lastValue = newValue;

            me.fireEvent('change', me, newValue, oldValue);
            me.onChange(newValue, oldValue);
        }

        return me;
    },

    createPicker: function () {
        var me = this;

        return me.hasForm() ? me.createForm() : me.createMenu();
    },

    onExpand: function () {
        var me = this;

        if (me.hasForm()) {
            var picker = me.getPicker();
            var form = picker.getForm();
            var parsedValues = me.getParsedValue();

            picker.oldValues = parsedValues;
            form.reset();
            form.setValues(parsedValues);
        }
    },

    onCollapse: function () {
        var me = this;

        if (me.hasForm()) {
            me.setValue(me.compileValue(
                Ext.apply(
                    me.getParsedValue(),
                    me.getPicker().getForm().getValues()
                )
            ));
        }
    },

    toggleFocusCls: function () {
        var me = this;

        var className = 'x-form-filterfield-has-value';

        if (!me.hasCls(className)) {
            me.addCls(className);
            return me;
        }

        me.removeCls(className);
        return me;
    },

    isValueInverted: function (value, oldValue) {
        var isValueEmpty = Ext.isEmpty(value);
        var isOldValueEmpty = Ext.isEmpty(oldValue);

        return (!isValueEmpty && isOldValueEmpty)
            || (isValueEmpty && !isOldValueEmpty);
    },

    parseValue: function (array) {
        var params = {};

        Ext.Array.each(array, function (string) {
            var separator = string.indexOf(':');
            var key = string.substring(0, separator).trim();

            params[key] = string.substring(separator + 1).trim();
        });

        return params;
    },

    compileValue: function (object) {
        var string = object.query || '';

        Ext.Object.each(object, function (key, value) {
            if (value && key !== 'query') {
                string = string + ' (' + key + ':' + value + ')';
            }
        });

        return string.trim();
    },

    formatQuery: function (string) {
        return string.trim().replace(/\s+/g, ' ');
    },

    getParsedValue: function () {
        var me = this;

        var value = me.getValue();
        var usefulParams = [];
        var query = value;
        var regex = /\(([^)]+)\)/g;
        var found;

        while (found = regex.exec(value)) {
            usefulParams.push(found[1]);
            query = query.replace(found[0], '');
        }

        return Ext.Object.merge(
            me.parseValue(usefulParams), {
                query: me.formatQuery(query)
            }
        );
    },

    resetFilter: function () {
        var me = this;

        me.reset();
        me.doForceChange();

        return me;
    },

    isRecordMatched: function (record) {
        var me = this;

        var value = me.getValue().toLowerCase();

        return me.getFilterFields().some(function (field) {
            var fieldValue = !Ext.isFunction(field)
                ? record.get(field)
                : field(record);

            return !Ext.isEmpty(fieldValue)
                && fieldValue.toLowerCase().indexOf(value) !== -1;
        });
    },

    clearTreeFilter: function () {
        var me = this;

        var store = me.getStore();
        store.removeFilter(me.filterId);

        store.each(function (record) {
            record.set('isVisible', false);
        });

        return store;
    },

    isNodeVisible: function (record) {
        var me = this;

        var value = me.getValue();
        var isNodeVisible = !record.get('isVisible')
            ? me.getFilterField().isRecordMatched(record, value)
            : true;
        var parentNode = record.parentNode;

        if (isNodeVisible && !Ext.isEmpty(parentNode) && !parentNode.get('isVisible')) {
            parentNode.set('isVisible', isNodeVisible);
        }

        return isNodeVisible || record.get('isVisible');
    },

    filterTreeLocally: function (value) {
        var me = this;

        me.clearTreeFilter().
            addFilter({
                id: me.filterId,
                value: value,
                filterFn: me.isNodeVisible,
                getFilterField: function () {
                    return me;
                }
            });

        return me;
    },

    filterGridLocally: function (value) {
        var me = this;

        var store = me.getStore();
        store.removeFilter(me.filterId);

        store.addFilter({
            id: me.filterId,
            value: value,
            filterFn: me.isRecordMatched,
            scope: me
        });

        return me;
    },

    doLocalSort: function (value) {
        var me = this;

        if (me.getStore().isTreeStore) {
            me.filterTreeLocally(value);
            return me;
        }

        me.filterGridLocally(value);
        return me;
    },

    doRemoteSort: function () {
        var me = this;

        var parsedValue = me.getParsedValue();

        me.getStore().
            clearProxyParams(me.appliedParamKeys).
            applyProxyParams(parsedValue);

        me.appliedParamKeys = Ext.Object.getKeys(parsedValue);

        return me;
    },

    applyFilter: function (value) {
        var me = this;

        if (me.hasStore()) {
            me.fireEvent('beforefilter', me);

            if (me.getRemoteSort() || me.getRemoteFilter()) {
                me.doRemoteSort();
            } else {
                me.doLocalSort(value);
            }

            me.fireEvent('afterfilter', me);
        }

        return me;
    }
});

Ext.define('Scalr.ui.FormFieldButtonGroup', {
    extend: 'Ext.form.FieldContainer',
    alias: 'widget.buttongroupfield',

    mixins: {
        field: 'Ext.form.field.Field'
    },
    baseCls: 'x-form-buttongroupfield',
    allowBlank: false,
    readOnly: false,
    labelSeparator: '',
    defaultBindProperty: 'value',
    maskOnDisable: false,

    initComponent: function() {
        var me = this, defaults;
        defaults = {
            xtype: 'button',
            enableToggle: true,
            //toggleGroup: me.getInputId(),
            allowDepress: me.allowBlank,
            scope: me,
            disabled: me.readOnly,
            doToggle: function(){
                /* Changed */
                if (this.enableToggle && this.allowDepress !== false || !this.pressed && this.ownerCt.fireEvent('beforetoggle', this, this.value) !== false) {
                    this.toggle();
                }
                /* End */
            },
            toggleHandler: function(button, state){
                button.ownerCt.setValue(state ? button.value : null);
            },
            onMouseDown: function(e) {
                var me = this;
                if (!me.disabled && e.button === 0) {
                    Ext.button.Manager.onButtonMousedown(me, e);
                }
            }
        };
        me.defaults = me.initialConfig.defaults ? Ext.clone(me.initialConfig.defaults) : {};
        Ext.applyIf(me.defaults, defaults);

        me.callParent();
        me.addCls(me.baseCls);
        me.initField();
        if (!me.name) {
            me.name = me.getInputId();
        }
    },

    getValue: function() {
        var me = this,
            val = me.getRawValue();
        me.value = val;
        return val;
    },

    setValue: function(value) {
        var me = this;
        me.setRawValue(value);
        return me.mixins.field.setValue.call(me, value);
    },

    getRawValue: function() {
        var me = this, v, b;
        me.items.each(function(){
            if (this.pressed === true) {
                b = this;
            }
        });

        if (b) {
            v = b.value;
            me.rawValue = v;
        } else {
            v = me.rawValue;
        }
        return v;
    },

    setRawValue: function(value) {
        var me = this;
        me.rawValue = value;
        me.items.each(function(){
            if (me.rendered) {
                this.toggle(this.value == value, this.value != value);
            } else {
                this.pressed = this.value == value;
            }
        });
        return value;
    },

    getInputId: function() {
        return this.inputId || (this.inputId = this.id + '-inputEl');
    },

    setReadOnly: function(readOnly) {
        var me = this;
        readOnly = !!readOnly;
        me.readOnly = readOnly;
        me.items.each(function(btn){
            btn.setDisabled(readOnly);
        });
        me.fireEvent('writeablechange', me, readOnly);
    },

    //fix extjs 5.1.0 children masking bug
    onEnable: function() {
        if (this.rendered) {
            this.items.each(function(item){
                if (item.rendered) {
                    item.unmask();
                }
            });
        }
        this.callParent(arguments);
    }

});

Ext.define('Scalr.ui.FormButtonField', {
    alias: 'widget.buttonfield',
    extend: 'Ext.button.Button',

    mixins: {
        field: 'Ext.form.field.Field'
    },
    inputValue: true,

    initComponent: function() {
        var me = this;
        me.callParent();
        me.initField();
    },

    getValue: function() {
        return this.pressed ? this.inputValue : '';
    },

    setValue: function(value) {
        this.toggle(value == this.inputValue);
        return this;
    }
});

Ext.define('Scalr.ui.FormCheckButtonField', {
    alias: 'widget.checkbuttonfield',
    extend: 'Scalr.ui.FormButtonField',

    setValue: function (value) {
        var me = this;

        if (value) {
            value = value.split('-');

            Ext.Array.each(me.menu.items.items, function (field, index) {
                field.checked = parseInt(value[index]);
            });
        }

        return me;
    }
});

Ext.define('Scalr.ui.FormFieldInfoTooltip', {
    extend: 'Ext.form.DisplayField',
    alias: 'widget.displayinfofield',
    initComponent: function () {
        // should use value for message
        var info = this.value || this.info;
        this.value = '<img class="x-icon-info" src="'+Ext.BLANK_IMAGE_URL+'" data-qtip=\'' + info + '\'/>';

        this.callParent(arguments);
    },

    setInfo: function (text) {
        var me = this;

        me.setValue('<img class="x-icon-info" src="'+Ext.BLANK_IMAGE_URL+'" data-qtip=\'' + text + '\' />');
    }
});

Ext.define('Scalr.ui.FormFieldFarmRoles', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.farmroles',

    layout: 'column',

    initComponent: function() {
        this.callParent(arguments);
        this.params = this.params || {};
        this.params.options = this.params.options || [];

        var farmField = this.down('[name="farmId"]'), farmRoleField = this.down('[name="farmRoleId"]'), serverField = this.down('[name="serverId"]');
        farmField.store.loadData(this.params['dataFarms'] || []);
        farmField.setValue(this.params['farmId'] || '');

        if (this.params.options.indexOf('requiredFarm') != -1)
            farmField.allowBlank = false;

        if (this.params.options.indexOf('requiredFarmRole') != -1)
            farmRoleField.allowBlank = false;

        if (this.params.options.indexOf('requiredServer') != -1)
            serverField.allowBlank = false;

        delete this.params['farmId'];
        delete this.params['farmRoleId'];
        delete this.params['serverId'];
        this.fixWidth();
    },

    fixWidth: function() {
        var farmField = this.down('[name="farmId"]'), farmRoleField = this.down('[name="farmRoleId"]'), serverField = this.down('[name="serverId"]');

        if (this.params.options.indexOf('disabledServer') != -1) {
            farmField.columnWidth = 0.5;
            farmRoleField.columnWidth = 0.5;
        } else if (this.params.options.indexOf('disabledFarmRole') != -1) {
            farmField.columnWidth = 1;
        } else {
            farmField.columnWidth = 1/3;
            farmRoleField.columnWidth = 1/3;
            serverField.columnWidth = 1/3;
        }
    },

    items: [{
        xtype: 'combo',
        hideLabel: true,
        name: 'farmId',
        store: {
            model: Scalr.getModel({fields: [ 'id', 'name' ]})
        },
        valueField: 'id',
        displayField: 'name',
        emptyText: 'Select a farm',
        editable: false,
        queryMode: 'local',
        listeners: {
            change: function () {
                var me = this, fieldset = this.up('fieldset');

                if (fieldset.params.options.indexOf('disabledFarmRole') != -1)
                    return;

                if (fieldset.params.options.indexOf('disabledServer') == -1)
                    fieldset.down('[name="serverId"]').hide();

                if (!this.getValue() || this.getValue() == '0') {
                    fieldset.down('[name="farmRoleId"]').hide();
                    return;
                }

                var successHandler = function(data) {
                    var field = fieldset.down('[name="farmRoleId"]');
                    if (Ext.isEmpty(field)) {
                        return;
                    }
                    field.show();
                    if (data['dataFarmRoles']) {
                        field.emptyText = 'Select a role';
                        field.reset();
                        field.store.loadData(data['dataFarmRoles']);

                        if (fieldset.params['farmRoleId']) {
                            field.setValue(fieldset.params['farmRoleId']);
                            delete fieldset.params['farmRoleId'];
                        } else {
                            if (fieldset.params.options.indexOf('addAll') != -1) {
                                field.setValue('0');
                            } else {
                                if (field.store.getCount() == 1)
                                    field.setValue(field.store.first()); // preselect single element
                                else
                                    field.setValue('');
                            }
                        }

                        field.enable();
                        field.clearInvalid();
                    } else {
                        field.store.removeAll();
                        field.emptyText = 'No roles';
                        field.reset();
                        field.disable();
                        if (field.allowBlank == false)
                            field.markInvalid('This field is required');
                    }
                };

                if (fieldset.params['dataFarmRoles']) {
                    successHandler(fieldset.params);
                    delete fieldset.params['dataFarmRoles'];
                } else
                    Scalr.Request({
                        url: '/farms/xGetFarmWidgetRoles/',
                        params: {farmId: me.getValue(), options: fieldset.params['options'].join(',')},
                        processBox: {
                            type: 'load',
                            msg: 'Loading farm roles ...'
                        },
                        success: successHandler
                    });
            }
        }
    }, {
        xtype: 'combo',
        hideLabel: true,
        hidden: true,
        name: 'farmRoleId',
        store: {
            model: Scalr.getModel({fields: [ 'id', 'name', 'platform', 'role_id' ]})
        },
        valueField: 'id',
        displayField: 'name',
        emptyText: 'Select a role',
        margin: '0 0 0 5',
        editable: false,
        queryMode: 'local',
        listeners: {
            change: function () {
                var me = this, fieldset = this.up('fieldset');

                if (fieldset.params.options.indexOf('disabledServer') != -1) {
                    fieldset.down('[name="serverId"]').hide();
                    return;
                }

                if (! me.getValue() || me.getValue() == '0') {
                    fieldset.down('[name="serverId"]').hide();
                    return;
                }

                var successHandler = function (data) {
                    var field = fieldset.down('[name="serverId"]');
                    field.show();
                    if (data['dataServers']) {
                        field.emptyText = 'Select a server';
                        field.reset();
                        field.store.load({data: data['dataServers']});

                        if (fieldset.params['serverId']) {
                            field.setValue(fieldset.params['serverId']);
                            delete fieldset.params['serverId'];
                        } else {
                            field.setValue(0);
                        }

                        field.enable();
                    } else {
                        field.emptyText = 'No running servers';
                        field.reset();
                        field.disable();
                    }
                };

                if (fieldset.params['dataServers']) {
                    successHandler(fieldset.params);
                    delete fieldset.params['dataServers'];
                } else
                    Scalr.Request({
                        url: '/farms/xGetFarmWidgetServers',
                        params: {farmRoleId: me.getValue(), options: fieldset.params['options'].join(',')},
                        processBox: {
                            type: 'load',
                            msg: 'Loading servers ...'
                        },
                        success: successHandler
                    });
            }
        }
    }, {
        xtype: 'combo',
        hideLabel: true,
        hidden: true,
        name: 'serverId',
        store: {
            model: Scalr.getModel({fields: [ 'id', 'name' ]})
        },
        valueField: 'id',
        displayField: 'name',
        margin: '0 0 0 5',
        editable: true,
        selectOnFocus: true,
        forceSelection: true,
        autoSearch: false,
        queryMode: 'local'
    }],

    optionChange: function(action, key) {
        var index = this.params.options.indexOf(key);

        if (action == 'remove' && index != -1 || action == 'add' && index == -1) {
            if (action == 'remove') {
                this.params.options.splice(index, index);
            } else {
                this.params.options.push(key);
            }

            switch(key) {
                case 'disabledFarmRole':
                    if (action == 'add') {
                        this.down('[name="farmRoleId"]').hide();
                        this.down('[name="serverId"]').hide();
                    } else {
                        this.down('[name="farmId"]').fireEvent('change');
                    }
                    break;

                case 'disabledServer':
                    if (action == 'add') {
                        this.down('[name="serverId"]').hide();
                    } else {
                        this.down('[name="farmRoleId"]').fireEvent('change');
                    }
                    break;
            }
        }

        this.fixWidth();
        this.updateLayout();

        return this;
    },

    syncItems: function () {
        /*if (this.enableFarmRoleId && this.down('[name="farmId"]').getValue()) {
         this.down('[name="farmId"]').fireEvent('change');
         } else
         this.down('[name="farmRoleId"]').hide();

         if (! this.enableServerId)
         this.down('[name="serverId"]').hide();*/
    }
});

Ext.define('Scalr.ui.FormFieldProgress', {
    extend: 'Ext.form.field.Display',
    alias: 'widget.progressfield',

    fieldSubTpl: [
        '<div id="{id}"',
        '<tpl if="fieldStyle"> style="{fieldStyle}"</tpl>',
        ' class="{fieldCls}"><div class="x-form-progress-bar"></div><span class="x-form-progress-text">{value}</span></div>',
        {
            compiled: true,
            disableFormats: true
        }
    ],

    fieldCls: Ext.baseCSSPrefix + 'form-progress-field',

    progressTextCls: 'x-form-progress-text',
    pendingCls: 'x-form-progress-pending',
    failedCls: 'x-form-progress-failed',
    progressBarCls: 'x-form-progress-bar',
    warningPercentage: 60,
    alertPercentage: 80,
    warningCls: 'x-form-progress-bar-warning',
    alertCls: 'x-form-progress-bar-alert',

    valueField: 'value',
    emptyText: '',
    units: '%',

    checkValue: false,
    checkValueFn: Ext.emptyFn,
    invalidValueText: '',

    setRawValue: function(value) {
        var me = this;

        var preventValueRendering = me.checkValue && !me.checkValueFn(value);

        if (preventValueRendering) {
            value = me.invalidValueText;
        }

        me.rawValue = Ext.isObject(value) ? Ext.clone(value) : value;

        if (me.rendered) {
            me.doRenderProgressBar();
        } else {
            me.deferredRender = true;
        }
        return value;
    },

    getProgressBarPercentage: function() {
        var value = this.getRawValue(),
            size = 0;
        if (Ext.isNumeric(value)) {
            size = value*100;
        } else if (Ext.isObject(value)) {
            size = Math.round(value[this.valueField]*100/value.total);
        }
        return size;
    },

    getDisplayValue: function() {
        var value = this.getRawValue(),
            display;
        if (Ext.isObject(value)) {
            if (this.units == '%') {
                display = Math.round(value[this.valueField]*100/value.total);
            } else {
                display = value[this.valueField] + ' of ' + value.total;
            }
        } else if (Ext.isNumeric(value)) {
            display = Math.round(value*100);
        }
        if (display !== undefined) {
            display += ' ' + this.units;
        } else if (value){
            display = value;
        }

        return display !== undefined ? display : this.emptyText;
    },

    setText: function(text) {
        var me = this;
        if (me.rendered) {
            me.inputEl.down('.'+me.progressTextCls).dom.innerHTML = text;
        }
    },

    valueToRaw: function(value) {
        return value;
    },

    doRenderProgressBar: function() {
        var me = this,
            percentage;
        percentage = this.getProgressBarPercentage()*1;
        var progressbar = me.inputEl.down('.'+me.progressBarCls);
        //progressbar.stopAnimation();
        progressbar.setWidth(0).removeCls(me.warningCls + ' ' + me.alertCls);

        if (percentage > me.alertPercentage) {
            progressbar.addCls(me.alertCls);
        } else if (percentage > me.warningPercentage) {
            progressbar.addCls(me.warningCls);
        }
        progressbar.setStyle('width', percentage+ '%');
        //disable animation due to unpredictable chrome tab crashing since v30
        /*progressbar.animate({
            duration: 500,
            from: {
                width: 0
            },
            to: {
                width: percentage+ '%'
            }
        });*/
        me.inputEl.down('.'+me.progressTextCls).dom.innerHTML = me.getDisplayValue();
    },

    setPending: function (isPending) {
        var me = this;

        var pendingText = 'Pending';

        if (Ext.isString(isPending)) {
            pendingText = isPending;
            isPending = true;
        }

        var inputEl = me.inputEl;
        var pendingCls = me.pendingCls;

        if (isPending) {
            inputEl.addCls(pendingCls);
            me.setRawValue('');
            me.setText(
                '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-field-pending" />' +
                pendingText
            );
            return me;
        }

        inputEl.removeCls(pendingCls);

        return me;
    },

    setFailed: function (isFailed) {
        var me = this;

        var inputEl = me.inputEl;
        var failedCls = me.failedCls;
        var failedText = 'Pending';

        if (Ext.isString(isFailed)) {
            failedText = isFailed;
            isFailed = true;
        }

        if (isFailed) {
            inputEl.addCls(failedCls);
            me.setRawValue('');
            me.setText(
                '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-field-failed" />' + failedText
            );
            return me;
        }

        inputEl.removeCls(failedCls);

        return me;
    },

    onRender: function() {
        this.callParent(arguments);
        if (this.deferredRender) {
            this.doRenderProgressBar();
            this.deferredRender = false;
        }
    }
});

Ext.define('Scalr.ui.CloudLocationMap', {
    extend: 'Ext.Component',
    alias: 'widget.cloudlocationmap',
    baseCls: 'scalr-ui-cloudlocationmap',

    settings: {
        common: {
            size: {
                width: 210,
                height: 100
            },
            cls: 'common'
        },
        large: {
            size: {
                width: 320,
                height: 140
            },
            cls: 'large'
        }
    },
    size: 'common',

    mode: 'multi',
    platforms: {},
    regions: {
        us: 0,
        sa: 3,
        eu: 2,
        ap: 1,
        unknown: 4,
        all: 5,
        jp: 6
    },
    autoSelect: false,
    locations: {
        ec2: {
            'ap-northeast-1': {region: 'ap', x: {common: 125, large: 183}, y: {common:27, large: 40}},
            'ap-southeast-1': {region: 'ap', x: {common: 95, large: 142}, y: {common:58, large: 81}},
            'ap-southeast-2': {region: 'ap', x: {common: 133, large: 193}, y: {common:88, large: 118}},
            'eu-west-1': {region: 'eu', x: {common: 75, large: 112}, y: {common:14, large: 18}},
            'eu-central-1': {region: 'eu', x: {common: 88, large: 112}, y: {common:16, large: 18}},
            'sa-east-1': {region: 'sa', x: {common: 114, large: 170}, y: {common:51, large: 71}},
            'us-east-1': {region: 'us', x: {common: 145, large: 212}, y: {common:53, large: 74}},
            'us-west-1': {region: 'us', x: {common: 43, large: 78}, y: {common:50, large: 66}},
            'us-west-2': {region: 'us', x: {common: 35, large: 72}, y: {common:26, large: 40}}
        },
        ec2_world: {//all locations on a single map
            'ap-northeast-1': {region: 'all', x: {common: 182, large: 274}, y: {common:32, large: 46}},
            'ap-southeast-1': {region: 'all', x: {common: 156, large: 244}, y: {common:54, large: 78}},
            'ap-southeast-2': {region: 'all', x: {common: 186, large: 286}, y: {common:76, large: 110}},
            'eu-west-1': {region: 'all', x: {common: 88, large: 140}, y: {common:24, large: 32}},
            'eu-central-1': {region: 'all', x: {common: 100, large: 156}, y: {common:26, large: 36}},
            'sa-east-1': {region: 'all', x: {common: 68, large: 104}, y: {common:70, large: 100}},
            'us-east-1': {region: 'all', x: {common: 48, large: 78}, y: {common:34, large: 48}},
            'us-west-1': {region: 'all', x: {common: 28, large: 42}, y: {common:40, large: 50}},
            'us-west-2': {region: 'all', x: {common: 22, large: 40}, y: {common:30, large: 40}}
        },
        rackspace: {
            'rs-LONx': {region: 'eu', x: {common: 75, large: 120}, y: {common:14, large: 18}},
            'rs-ORD1': {region: 'us', x: {common: 120, large: 180}, y: {common:32, large: 44}}
        },
        rackspace_world: {
            'rs-LONx': {region: 'all', x: {common: 88, large: 144}, y: {common:24, large: 30}},
            'rs-ORD1': {region: 'all', x: {common: 40, large: 70}, y: {common:28, large: 45}}
        },
        rackspacengus: {
            'DFW': {region: 'us', x: {common: 100, large: 154}, y: {common:60, large: 78}},
            'ORD': {region: 'us', x: {common: 120, large: 180}, y: {common:32, large: 44}},
            'IAD': {region: 'us', x: {common: 145, large: 180}, y: {common:44, large: 44}},
            'SYD': {region: 'ap', x: {common: 135, large: 180}, y: {common:88, large: 44}}
        },
        rackspacengus_world: {
            'DFW': {region: 'all', x: {common: 40, large: 63}, y: {common:37, large: 52}},
            'ORD': {region: 'all', x: {common: 45, large: 70}, y: {common:30, large: 45}},
            'IAD': {region: 'all', x: {common: 53, large: 78}, y: {common:34, large: 47}},
            'SYD': {region: 'all', x: {common: 186, large: 285}, y: {common:76, large: 112}}
        },
        rackspacenguk: {
            'LON': {region: 'eu', x: {common: 75, large: 120}, y: {common:14, large: 18}}
        },
        rackspacenguk_world: {
            'LON': {region: 'all', x: {common: 88, large: 144}, y: {common:24, large: 34}}
        },
        idcf: {
            'jp-east-t1v': {region: 'jp', x: {common: 114, large: 168}, y: {common:66, large: 88}},//Tokyo
            'jp-east-f2v': {region: 'jp', x: {common: 116, large: 176}, y: {common:46, large: 68}}//Shirakawa
        },
        idcf_world: {
            'jp-east-t1v': {region: 'all', x: {common: 182, large: 274}, y: {common:32, large: 46}},
            'jp-east-f2v': {region: 'all', x: {common: 182, large: 278}, y: {common:32, large: 40}}
        },
        gce: {
            //zones
            'us-central1-a': {region: 'all', x: {common: 30, large: 46}, y: {common:32, large: 48}},
            'us-central1-b': {region: 'all', x: {common: 36, large: 56}, y: {common:30, large: 46}},
            'us-central2-a': {region: 'all', x: {common: 42, large: 66}, y: {common:34, large: 48}},
            'europe-west1-a': {region: 'all', x: {common: 95, large: 150}, y: {common:28, large: 38}},
            'europe-west1-b': {region: 'all', x: {common: 100, large: 160}, y: {common:26, large: 36}},
            'asia-east1-b': {region: 'all', x: {common: 166, large: 254}, y: {common:46, large: 64}},
            'asia-east1-a': {region: 'all', x: {common: 160, large: 246}, y: {common:54, large: 80}},
            //gce location beta
            'us-central1': {region: 'all', x: {common: 36, large: 56}, y: {common:30, large: 46}},
            'us-central2': {region: 'all', x: {common: 42, large: 66}, y: {common:34, large: 48}},
            'europe-west1': {region: 'all', x: {common: 95, large: 150}, y: {common:28, large: 38}},
            'asia-east1': {region: 'all', x: {common: 166, large: 254}, y: {common:46, large: 64}},
            'us-west1': {region: 'all', x: {common: 30, large: 46}, y: {common:32, large: 48}}
        },
        openstack: {
            'ItalyMilano1': {region: 'eu', x: {common: 96, large: 148}, y: {common:26, large: 36}},
            'it-mil1': {region: 'eu', x: {common: 96, large: 148}, y: {common:26, large: 36}},
            'de-fra1': {region: 'eu', x: {common: 90, large: 140}, y: {common:14, large: 24}},
            'nl-ams1': {region: 'eu', x: {common: 80, large: 134}, y: {common:16, large: 26}}
        },
        openstack_world: {
            'ItalyMilano1': {region: 'eu', x: {common: 96, large: 148}, y: {common:26, large: 36}},
            'it-mil1': {region: 'eu', x: {common: 96, large: 148}, y: {common:26, large: 36}},
            'de-fra1': {region: 'eu', x: {common: 90, large: 140}, y: {common:14, large: 24}},
            'nl-ams1': {region: 'eu', x: {common: 80, large: 134}, y: {common:16, large: 26}}
        }
    },
    renderTpl: [
        '<div id="{id}-mapEl" data-ref="mapEl" class="map map-{mapCls}" style="{mapStyle}"><div id="{id}-titleEl" data-ref="titleEl" class="title x-semibold"></div></div>'
    ],
    childEls: ['mapEl', 'titleEl'],

    constructor: function(config) {
        this.callParent(arguments);
        this.locations.rds = this.locations.ec2;
        this.settings[this.size].style = 'width:' + this.settings[this.size].size.width + 'px;';
        this.settings[this.size].style += 'height:' + this.settings[this.size].size.height + 'px;';
        this.mapSize = this.settings[this.size].size;
        this.renderData.mapStyle = this.settings[this.size].style;
        this.renderData.mapCls = this.settings[this.size].cls;
    },

    selectLocation: function(platform, selectedLocations, allLocations, map){
        var me = this,
            locationFound = false,
            platformMap = platform !== 'idcf' && (me.locations[platform + '_' + map] !== undefined) ? platform + '_' + map : platform;
        fn = function() {
            me.suspendLayouts();
            allLocations = allLocations || [];
            me.reset();
            if (selectedLocations === 'all') {
                me.mapEl.setStyle('background-position', me.getRegionPosition('all'));
                locationFound = true;
                if (platform === 'gce') {
                    Ext.Object.each(me.locations[platformMap], function(key, value) {
                        me.addLocation(platform, key, value, true, true);
                    });
                }
            } else if (me.locations[platformMap]) {
                selectedLocations = Ext.isArray(selectedLocations) ? selectedLocations : [selectedLocations];
                var selectedLocation = me.locations[platformMap][selectedLocations[0]];
                if (selectedLocation) {
                    me.mapEl.setStyle('background-position', me.getRegionPosition(selectedLocation.region));
                    if (selectedLocation.region != 'unknown') {
                        locationFound = true;
                        Ext.Object.each(me.locations[platformMap], function(key, value) {
                            var selected = Ext.Array.contains(selectedLocations, key);
                            if (selected || Ext.Array.contains(allLocations, key)) {
                                me.addLocation(platform, key, value, selected);
                            }
                        });
                    }
                }
            }
            if (!locationFound) {
                me.mapEl.setStyle('background-position', me.getRegionPosition('unknown'));
            }
            me.resumeLayouts(true);
        };

        if (me.rendered) {
            fn();
        } else {
            me.on('afterrender', fn, me, {single: true});
        }
    },

    addLocation: function(platform, name, data, selected, silent) {
        var me = this,
            title = name,
            platformInfo = me.platforms[platform] || {};
        if (platform !== 'gce' && platformInfo.locations && platformInfo.locations[name]) {
            title = platformInfo.locations[name];
        }
        var el = Ext.DomHelper.append(me.mapEl.dom, '<div data-location="'+Ext.util.Format.htmlEncode(name)+'" style="top:'+data.y[me.size]+'px;left:'+data.x[me.size]+'px" class="location'+(selected ? ' selected' : '')+'" title="'+Ext.util.Format.htmlEncode(title)+'"></div>', true)
        if (!silent) {
            el.on('click', function(){
                var isSelected = this.hasCls('selected');
                if (me.fireEvent('beforeselectlocation', this.getAttribute('data-location')) === false) {
                    return;
                }
                if (!isSelected && me.autoSelect) {
                    var loc = me.mapEl.query('.location');
                    for (var i=0, len=loc.length; i<len; i++) {
                        Ext.fly(loc[i]).removeCls('selected');
                    }
                    this.addCls('selected');
                }
                me.fireEvent('selectlocation', this.getAttribute('data-location'), !isSelected);
            });
        }
        if (me.mode == 'single' && platform === 'ec2' && selected) {
            me.titleEl.setHtml(title);
            me.fixTitlePosition();
        }
    },

    addLocations: function(locations) {
        var me = this;
        me.mapEl.setStyle('background-position', this.getRegionPosition('all'));
        Ext.Array.each(locations, function(item){
            if (item.location && me.locations[item.platform + '_world'] && me.locations[item.platform + '_world'][item.location]) {
                me.addLocation(item.platform, item.location, me.locations[item.platform + '_world'][item.location], false, false);
            }
        });
    },

    fixTitlePosition: function() {
        var loc = this.mapEl.query('.location');
        if (loc[0]) {
            var el = Ext.fly(loc[0]);
            //we are trying to avoid overlapping between title and location div
            if (el.getTop(true) > this.mapSize.height/2) {
                this.titleEl.setTop(el.getTop(true)-35);
            } else {
                this.titleEl.setTop(el.getTop(true)+20);
            }
        }
    },

    reset: function() {
        if (!this.rendered) return;
        var loc = this.mapEl.query('.location');
        for (var i=0, len=loc.length; i<len; i++) {
            Ext.removeNode(loc[i]);
        }
        this.titleEl.setHtml('');
    },

    getRegionPosition: function(region) {
        return '0 -' + (this.regions[region]*this.mapSize.height) + 'px';
    },

    setLocation: function(location) {
        var locations = this.mapEl.query('.location');
        Ext.Array.each(locations, function(loc){
            var el = Ext.fly(loc);
            el[el.getAttribute('data-location') == location ? 'addCls' : 'removeCls']('selected');
        });
    }

});

Ext.define('Scalr.ui.FormTextCodeMirror', {
    extend: 'Ext.form.field.Base',
    alias: 'widget.codemirror',

    readOnly: false,
    addResizeable: false,

    fieldSubTpl: '<div id="{id}"></div>',
    enterIsSpecial: false,
    mode: '', // set to prevent mode recognition

    setMode: function (cm) {
        if (this.mode) {
            cm.setOption('mode', this.mode);
            return;
        }

        // #! ... /bin/(language)
        var value = cm.getValue(), mode = /^#!.*\/bin\/(.*)$/.exec(Ext.String.trim(value.split("\n")[0]));
        mode = mode && mode.length == 2 ? mode[1] : '';

        switch (mode) {
            case 'python':
                cm.setOption('mode', 'python');
                break;

            case 'bash': case 'sh':
            cm.setOption('mode', 'shell');
            break;

            case 'php':
                cm.setOption('mode', 'php');
                break;

            default:
                cm.setOption('mode', 'text/plain');
                break;
        }
    },

    afterRender: function () {
        this.callParent(arguments);
        this.codeMirror = new CodeMirror(this.inputEl, {
            value: this.getRawValue(),
            readOnly: this.readOnly
        });

        this.codeMirror.on('change', Ext.Function.bind(function (editor, changes) {
            if (changes.from.line == 0)
                this.setMode(editor);

            var value = editor.getValue();
            this.setRawValue(value);
            this.mixins.field.setValue.call(this, value);

            /*var el = Ext.fly(this.codeMirror.getWrapperElement()).down('.CodeMirror-lines').child('div');
             console.log(el.getHeight());
             this.setHeight(el.getHeight() + 14); // padding
             //this.setSize();
             this.updateLayout();

             //console.log(editor.get)*/
        }, this));

        this.setMode(this.codeMirror);

        //this.codeMirror.setSize('100%', '100%');

        this.on('resize', function (comp, width, height) {
            //debugger;
            Ext.fly(this.codeMirror.getWrapperElement()).setSize(width, height);
            this.codeMirror.refresh();
        });

        if (this.addResizeable) {
            Ext.fly(this.codeMirror.getWrapperElement()).addCls('codemirror-resizeable');
            new Ext.Resizable(this.codeMirror.getWrapperElement(), {
                minHeight:this.minHeight,
                handles: 's',
                pinned: true,
                listeners: {
                    resizedrag: function(){
                        this.target.up('.x-panel-body').dom.scrollTop = 99999;
                    }
                }
            });
        }
    },

    getRawValue: function () {
        var me = this,
            v = (me.codeMirror ? me.codeMirror.getValue() : (me.rawValue || ''));
        me.rawValue = v;
        return v;
    },

    setRawValue: function (value) {
        var me = this;
        value = me.transformRawValue(value) || '';
        me.rawValue = value;

        return value;
    },

    setValue: function(value) {
        var me = this;
        me.setRawValue(me.valueToRaw(value));

        if (me.codeMirror)
            me.codeMirror.setValue(value);

        return me.mixins.field.setValue.call(me, value);
    },

    setReadOnly: function (readOnly) {
        var me = this;

        me.codeMirror.setOption('readOnly', readOnly);

        me.callParent(arguments);
    },

    getErrors: function (value) {
        var me = this;

        var errors = [];

        if (Ext.isDefined(value)) {
            errors = me.callParent([value]);
            var validator = me.validator;

            if (Ext.isFunction(validator)) {
                var message = validator.call(me, value);

                if (message !== true) {
                    errors.push(message);
                }
            }
        }

        return errors;
    }
});

Ext.define('Scalr.ui.CycleButtonAlt', {
    alias: 'widget.cyclealt',

    extend: 'Ext.button.Split',

    mixins: {
        field: 'Ext.form.field.Field'
    },

    showText: true,
    getItemIconCls: false,
    deferChangeEvent: true,
    suspendChangeEvent: 0,
    multiselect: false,
    selectedItemsSeparator: '&nbsp;',

    getButtonText: function(item) {
        var me = this,
            text = '';

        if (item && me.showText === true) {
            if (me.prependText) {
                text += me.prependText;
            }
            text += Ext.isDefined(me.getItemText) ? me.getItemText(item) : item.text;
            return text;
        }
        return me.text;
    },

    toggleItem: function(item, checked, suppressEvent) {
        var me = this,
            text = [];
        if (!Ext.isObject(item)) {
            item = me.menu.getComponent(item);
        }
        if (item) {
            if (me.multiselect) {
                Ext.Array[checked ? 'include' : 'remove'](me.activeItems, item);
                Ext.Array.each(me.activeItems, function(item){
                    text.push(me.getButtonText(item));
                });
            } else {
                me.activeItems.length = 0;
                me.activeItems.push(item);
                text.push(me.getButtonText(item));
            }
            me.suspendChangeEvent++;

            if (me.activeItems.length === 0 && me.noneSelectedText) {
                text = me.noneSelectedText;
            } else if (me.allSelectedText && me.activeItems.length === me.menu.items.length) {
                text = me.allSelectedText;
            } else if (me.selectedTpl && me.activeItems.length !== me.menu.items.length) {
                text = Ext.String.format(me.selectedTpl, me.activeItems.length, me.menu.items.length);
            } else {
                text = text.join(me.selectedItemsSeparator);
            }

            if (me.multiselect) {
                text = '<span class="x-btn-split-multiselect">' + text + '</span>';
            }
            if (!me.rendered) {
                me.text = text;
            } else {
                me.setText(text);
            }

            if (item.checked != checked) {
                item.setChecked(checked, false);
            }
            me.suspendChangeEvent--;

            if (!suppressEvent && me.suspendChangeEvent === 0) {
                me.fireEvent('change', me, item, me.multiselect ?  me.activeItems : me.activeItems[0]);
            }
        }

    },

    getActiveItem: function() {
        var me = this;
        return me.multiselect ?  me.activeItems : me.activeItems[0];
    },

    initComponent: function() {
        var me      = this,
            checked = 0,
            items,
            i, iLen, item;
        me.activeItems = [];
        if (me.changeHandler) {
            me.on('change', me.changeHandler, me.scope || me);
            delete me.changeHandler;
        }

        items = (me.menu.items || []).concat(me.items || []);
        me.menu = Ext.applyIf({
            //cls: Ext.baseCSSPrefix + 'cycle-menu',
            items: []
        }, me.menu);

        iLen = items.length;

        // Convert all items to CheckItems
        for (i = 0; i < iLen; i++) {
            item = items[i];

            item = Ext.applyIf({
                group        : me.multiselect ? null : me.id,
                itemIndex    : i,
                checkHandler : me.checkHandler,
                scope        : me,
                checked      : item.checked || false
            }, item);

            me.menu.items.push(item);

            if (item.checked) {
                checked = i;
            }
        }

        me.itemCount = me.menu.items.length;
        me.callParent(arguments);
        me.initField();
        if (!me.multiselect) {
            me.on('click', me.toggleSelected, me);
        }
        me.toggleItem(checked, true, me.deferChangeEvent);

        // If configured with a fixed width, the cycling will center a different child item's text each click. Prevent this.
        if (me.width && me.showText) {
            me.addCls(Ext.baseCSSPrefix + 'cycle-fixed-width');
        }
    },

    checkHandler: function(item, pressed) {
        if (this.multiselect || pressed) {
            this.toggleItem(item, pressed);
        }
    },

    toggleSelected: function() {
        var me = this,
            m = me.menu,
            checkItem;

        checkItem = me.activeItems[0].next(':not([disabled])');

        if (Ext.isEmpty(checkItem)) {
            var firstItem = me.getMenu().items.first();
            checkItem = !firstItem.isDisabled() ? firstItem : firstItem.next(':not([disabled])');
        }

        if (!Ext.isEmpty(checkItem)) {
            checkItem.setChecked(true);
        }
    },

    getValue: function() {
        var me = this,
            value;
        if (me.multiselect) {
            value = [];
            Ext.Array.each(me.activeItems, function(item){
                if (item.value) {
                    value.push(value);
                }
            });
        } else if (me.activeItems[0]) {
            value = me.activeItems[0].value;
        }
       return value;
    },

    setValue: function(value, suppressEvent) {
        var me = this,
            val = Ext.isArray(value) ? value : [value];
        me.menu.items.each(function(item){
            if (Ext.Array.contains(val, item.value)) {
                me.toggleItem(item, true, suppressEvent);
            }
        });

        return me;
    },

    add: function(item){
        var me = this;

        return me.menu.add(
            Ext.isArray(item) ?
            Ext.Array.map(item, function(item){
                return Ext.applyIf({
                    group        : me.multiselect ? null : me.id,
                    itemIndex    : ++me.itemCount,
                    checkHandler : me.checkHandler,
                    scope        : me,
                    checked      : item.checked || false
                }, item);
            }) :
            Ext.applyIf({
                group        : me.multiselect ? null : me.id,
                itemIndex    : ++me.itemCount,
                checkHandler : me.checkHandler,
                scope        : me,
                checked      : item.checked || false
            }, item)
        );
    },

    removeAll: function() {
        var me = this;
        me.activeItems.length = 0;
        me.menu.removeAll();
    }
});

/* todo: fix colorfield css */
Ext.define('Ext.form.field.Color', {
    extend: 'Ext.form.field.Picker',
    alias: 'widget.colorfield',

    cls: 'x-field-colorpicker',
    editable: false,
    colors: ['333333', 'DF2200', 'AA00AA', '1A4D99', '3D690C', '006666', '6F8A02', '0C82C0', 'CA4B00', '671F92'],
    emptyColor: 'eeeeee',

    createPicker: function() {
        var me = this;

        return new Ext.picker.Color({
            cls: 'x-field-colorpicker-menu',
            pickerField: me,
            floating: true,
            hidden: true,
            height: 'auto',
            colors: me.colors,
            listeners: {
                scope: me,
                select: me.onSelect
            }
        });
    },

    onSelect: function(m, d) {
        var me = this;
        me.setValue(d);
        me.fireEvent('select', me, d);
        me.collapse();
    },

    onExpand: function() {
        var value = this.getValue();
        if (value) {
        this.picker.select(value, true);
        }
    },

    onFocusLeave: Ext.emptyFn,

    setRawValue: function(value) {
        var me = this;
        me.callParent(arguments);
        me.setRawColor(value);
    },

    setRawColor: function(value) {
        var me = this,
            color = me.emptyColor;
        if (!Ext.isEmpty(value)) {
            color = value;
        }
        if (me.inputEl) {
            me.inputEl.setStyle({'background': '#' + color});
        }
    },

    afterRender: function(){
        var me = this;
        me.callParent(arguments);
        me.setRawColor(me.value);
    }

});

Ext.define('Scalr.ui.VpcSubnetField', {
    extend: 'Ext.form.field.Tag',
    alias: 'widget.vpcsubnetfield',

    displayField: 'description',
    valueField: 'id',

    forceSelection: false,
    queryCaching: false,
    clearDataBeforeQuery: true,
    editable: true,
    minChars: 0,
    queryDelay: 10,

    requireSameSubnetType: false,
    maxCount: 0,

    iconAlign: 'left',
    iconPosition: 'inner',

    store: {
        fields: ['id', 'name', 'description', 'ips_left', 'type', 'availability_zone', 'cidr'],
        proxy: {
            type: 'cachedrequest',
            url: '/tools/aws/vpc/xListSubnets',
            filterFields: ['name', 'description']
        }
    },
    listConfig: {
        style: 'white-space:nowrap',
        cls: 'x-boundlist-alt',
        tpl:
            '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto;line-height:20px">' +
                '<div><b>{[values.name || \'<i>No name</i>\' ]} - {id}</b> <span style="font-style: italic;font-size:90%">(Type: <b>{type:capitalize}</b>)</span></div>' +
                '<div>{cidr} in {availability_zone} [IPs left: {ips_left}]</div>' +
            '</div></tpl>'
    },

    initComponent: function() {
        var me = this;
        me.plugins = me.plugins || [];
        me.plugins.push({
            ptype: 'comboaddnew',
            pluginId: 'comboaddnew',
            url: '/tools/aws/vpc/createSubnet',
            disabled: true
        }, {
            ptype: 'fieldicons',
            icons: ['governance'],
            align: me.iconAlign,
            position: me.iconPosition
        });

        me.on('addnew', function(item) {
            Scalr.CachedRequestManager.get().setExpired({
                url: '/tools/aws/vpc/xListSubnets',
                params: me.store.proxy.params
            });
        });

        me.on('beforeselect', function(comp, record) {
            var subnets = this.getValue(),
                rec;
            if (subnets.length > 0) {
                if (this.maxCount && subnets.length >= this.maxCount) {
                    Scalr.message.InfoTip('Single subnet is required', comp.bodyEl, {anchor: 'bottom'});
                    return false;
                }
                if (this.requireSameSubnetType) {
                    rec = comp.findRecordByValue(subnets[0]);
                    if (rec && rec.get('type') == record.get('type')) {
                        return true;
                    } else {
                        Scalr.message.InfoTip('Only subnets of the <b>same type</b> can be selected.', comp.bodyEl, {anchor: 'bottom'});
                        return false;
                    }
                }
            }
            return true;
        });

        me.callParent(arguments);

        Ext.apply(me.getStore().getProxy(), {
            filterFn: function(record) {
                var res = false,
                    limits = this.ignoreGovernance ? undefined : Scalr.getGovernance('ec2', 'aws.vpc'),
                    vpcId = this.store.proxy.params.vpcId,
                    fieldLimits, filterType;

                var type = record.get('type');
                if (type === 'private' && this.isVpcRouter) {
                    res = false;
                } else if (limits && limits['ids'] && limits['ids'][vpcId]) {
                    fieldLimits = limits['ids'][vpcId];
                    filterType = Ext.isArray(fieldLimits) ? 'subnets' : 'iaccess';
                    if (filterType === 'subnets' && Ext.Array.contains(fieldLimits, record.get('id'))) {
                        res = true;
                    } else if (filterType === 'iaccess') {
                        res = type === 'private' && fieldLimits === 'outbound-only' || type === 'public' && fieldLimits === 'full';
                    }
                } else {
                    res = true;
                }
                return res;
            },
            filterFnScope: me
        });
    }
});

Ext.define('Scalr.ui.FormScriptField', {
    extend: 'Ext.form.field.ComboBox',
    alias: 'widget.scriptselectfield',

    fieldLabel: 'Script',
    emptyText: 'Select a script',
    editable: true,
    anyMatch: true,
    autoSearch: false,
    restoreValueOnBlur: true,
    selectOnFocus: true,
    expandOnClick: true,
    queryMode: 'local',

    valueField: 'id',
    displayField: 'name',

    plugins: {
        ptype: 'fieldinnericonscope',
        tooltipScopeType: 'script'
    },
    listConfig: {
        cls: 'x-boundlist-alt',
        tpl:
            '<tpl for=".">' +
                '<div class="x-boundlist-item" style="height: auto; width: auto; max-width: 900px;">' +
                    '<div>{[this.getInnerIcon(values)]}&nbsp;&nbsp;<b>{name}</b>' +
                        '<tpl if="createdByEmail"><span style="color: #666; font-size: 11px;">&nbsp;(created by {createdByEmail})</span></tpl>' +
                        '<img src="/ui2/images/ui/scripts/{os}.png" style="float: right; opacity: 0.6">' +
                    '</div>' +
                    '<tpl if="description">' +
                        '<div style="line-height: 16px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px">' +
                            '<span style="font-style: italic;">{description}</span>' +
                        '</div>' +
                    '</tpl>' +
                '</div>' +
            '</tpl>'
    },

    onRender: function() {
        var me = this;

        me.callParent(arguments);

        me.inputImgEl = me.inputCell.createChild({
            tag: 'img',
            style: 'position: absolute; top: 8px; right: 26px; opacity: 0.8',
            width: 15,
            height: 15
        });

        me.inputImgEl.setVisibilityMode(Ext.Element.DISPLAY);
        me.setInputImgElType(me.getValue());
    },

    setInputImgElType: function(value, oldValue) {
        var me = this,
            rec = me.findRecordByValue(value);
        if (rec) {
            this.inputImgEl.dom.src = '/ui2/images/ui/scripts/' + rec.get('os') + '.png';
            this.inputImgEl.show();
        } else {
            this.inputImgEl.hide();
        }
    },

    onChange: function(newValue, oldValue) {
        var me = this;
        if (me.inputImgEl) {
            me.setInputImgElType(newValue, oldValue);
        }

        me.callParent(arguments);
    }
});

Ext.define('Scalr.ui.NameValueListField', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.namevaluelistfield',
    cls: 'x-grid-with-formfields',
    trackMouseOver: false,
    disableSelection: true,

    store: {
        fields: [{name: 'name', defaultValue: ''}, {name: 'value', defaultValue: ''}],
        proxy: 'object'
    },
    features: {
        ftype: 'addbutton',
        text: 'Add',
        handler: function(view) {
            var grid = view.up(),
                record = grid.store.add({})[0],
                field = grid.columns[0].getWidget(record);
            view.scrollBy(0, 9000);
            field.focus();
            field.clearInvalid();
        }
    },

    listeners: {
        itemclick: function (view, record, item, index, e) {
            if (e.getTarget('img.x-grid-icon-delete')) {
                view.store.remove(record);
                /*if (!view.store.getCount()) {
                    view.up().store.add({});
                }*/
                return false;
            }
        }
    },

    initComponent: function() {
        if (this.itemName) {
            this.on('viewready', function() {
                this.view.findFeature('addbutton').setText('Add ' + this.itemName);
            }, this, {single: true});
        }
        this.callParent(arguments);
    },

    setReadOnly: function(readOnly) {
        this.readOnly = !!readOnly;
        this.view.refresh();
        this.view.findFeature('addbutton').setDisabled(readOnly);
    },

    setValue: function(value){
        var me = this,
            data = [];
        value = value || {};
        Ext.Object.each(value, function(name, value){
            data.push({
                name: name,
                value: value
            });
        });
        me.store.loadData(data);
    },

    getValue: function(){
        var me = this,
            result  = {};
        me.store.getUnfiltered().each(function(record){
            var name = record.get('name'),
                value = record.get('value');
            if (name) {
                result[name] = value;
            }
        });
        return result;
    },

    isRowReadOnly: function(record) {
        return this.readOnly;
    },

    isNameValid: function(name, record) {
        return true;
    },

    deleteColumnRenderer: function(record) {
        var result = '<img style="cursor:pointer;margin-top:6px;';
        if (!this.readOnly) {
            result += '" class="x-grid-icon x-grid-icon-delete" data-qtip="Delete"';
        }
        return result += ' src="'+Ext.BLANK_IMAGE_URL+'"/>';
    },

    columns: [{
        header: 'Name',
        sortable: false,
        resizable: false,
        dataIndex: 'name',
        flex: .8,
        xtype: 'widgetcolumn',
        onWidgetAttach: function(column, widget, record) {
            widget.setReadOnly(widget.up('grid').isRowReadOnly(record));
            if (record.get('name')) {
                widget.isValid();
            }
        },
        widget: {
            xtype: 'textfield',
            allowBlank: false,
            listeners: {
                change: function(comp, value){
                    var record = comp.getWidgetRecord();
                    if (record) {
                        record.set('name', value);
                    }
                }
            },
            validator: function(value) {
                var column = this.getWidgetColumn(),
                    record = this.getWidgetRecord();
                if (column && record) {
                    return column.ownerCt.grid.isNameValid(value, record);
                } else {
                    return true;
                }
            }
        }
    },{
        header: 'Value',
        sortable: false,
        resizable: false,
        dataIndex: 'value',
        flex: 2,
        xtype: 'widgetcolumn',
        onWidgetAttach: function(column, widget, record) {
            widget.setReadOnly(widget.up('grid').isRowReadOnly(record));
        },
        widget: {
            xtype: 'textfield',
            listeners: {
                change: function(comp, value){
                    var record = comp.getWidgetRecord();
                    if (record) {
                        record.set('value', value);
                    }
                    cb = function() {
                        comp.inputEl.set({'data-qtip': comp.readOnly ? Ext.String.htmlEncode(value) : ''});
                    }
                    if (comp.inputEl) {
                        cb();
                    } else {
                        comp.on('afterrender', cb, comp, {single: true})
                    }
                }
            }
        }
    },{
        renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
            return grid.up().deleteColumnRenderer(record);
        },
        width: 42,
        sortable: false,
        align:'left'

    }],

});


Ext.define('Scalr.ui.Ec2TagsField', {
    extend: 'Scalr.ui.NameValueListField',
    alias: 'widget.ec2tagsfield',

    allowNameTag: false,
    tagsLimit: 10,
    cloud: 'ec2',

    initComponent: function() {
        this.reservedNames = [];
        this.systemTags = {
            'scalr-meta': 'v1:{SCALR_ENV_ID}:{SCALR_FARM_ID}:{SCALR_FARM_ROLE_ID}:{SCALR_SERVER_ID}'
        };
        this.on('viewready', function() {
            this.setCloud(this.cloud);
        }, this, {single: true});
        this.callParent(arguments);
    },

    setCloud: function(cloud) {
        this.cloud = cloud;
        if (cloud === 'ec2' || cloud === 'azure') {
            this.itemName = 'tag';
            this.subjectName = 'Tagging';
            this.reservedNames = ['scalr-meta', 'Name'];
        } else if (cloud === 'openstack') {
            this.itemName = 'name-value pair';
            this.subjectName = 'Metadata';
            this.reservedNames = ['scalr-meta', 'farmid',  'role',  'httpproto',  'region',  'hash',  'realrolename', 'szr_key',  'serverid',  'p2p_producer_endpoint',  'queryenv_url', 'behaviors',  'farm_roleid',  'roleid',  'env_id',  'platform', 'server_index',  'cloud_server_id',  'cloud_location_zone',  'owner_email'];
        }
        this.governanceTooltip = 'This Environment\'s '+this.subjectName+' Policy does not allow you to add custom '+this.itemName+'s.',
        this.governanceTooltip2 = 'This Environment\'s '+this.subjectName+' Policy does not allow you to edit this '+this.itemName+'.',
        this.view.findFeature('addbutton').setText('Add ' + this.itemName);
    },
    setSubjectName: function(subjectName) {
        this.subjectName = subjectName;
    },
    store: {
        fields: [{name: 'name', defaultValue: ''}, {name: 'value', defaultValue: ''}, {name: 'system', defaultValue: false}, {name: 'ignoreOnSave', defaultValue: false}, {name: 'addedByGovernance', defaultValue: false}],
        proxy: 'object'
    },
    listeners: {
        viewready: function() {
            this.store.on({
                refresh: this.applyTagsLimit,
                add: this.applyTagsLimit,
                remove: this.applyTagsLimit,
                scope: this
            });
            this.applyTagsLimit();
        }
    },
    isRowReadOnly: function(record) {
        return this.callParent(arguments) || record.get('system') || record.get('addedByGovernance');
    },
    isNameValid: function(name, record) {
        name = Ext.String.trim(name);
        return !Ext.Array.contains(this.reservedNames, name) || !!record.get('system') || (name === 'Name' ? 'Use the separate Instance Name Pattern option.' : 'Reserved name');
    },
    deleteColumnRenderer: function(record) {
        var result = '<img style="cursor:pointer;margin-top:6px;';
        if (record.get('system')) {
            result += 'cursor:default;opacity:.4" class="x-grid-icon x-grid-icon-lock" data-qwidth="440" data-qtip="System '+this.itemName+'s cannot be modified or removed, and will be added to instances and volumes regardless of whether you enforce a '+this.subjectName+' policy"';
        } else {
            if (this.readOnly || record.get('addedByGovernance')) {
                result += '" class="x-icon-governance" data-qtip="' + this.governanceTooltip2 + '"';
            } else {
                result += '" class="x-grid-icon x-grid-icon-delete" data-qtip="Delete"';
            }
        }
        result += ' src="'+Ext.BLANK_IMAGE_URL+'"/>';
        return result;
    },
    setValue: function(value, limits){
        var me = this,
            data = [];
        value = value || {};
        limits = limits || {};
        Ext.Object.each(me.systemTags, function(tagName, tagValue){
            data.push({
                name: tagName,
                value: tagValue,
                system: true
            });
        });
        Ext.Object.each(limits, function(tagName, tagValue){
            data.push({
                name: tagName,
                value: tagValue,
                addedByGovernance: true,
                ignoreOnSave: value[tagName] !== undefined ? false : true
            });
        });
        Ext.Object.each(value, function(tagName, tagValue){
            if (me.systemTags[tagName] === undefined && limits[tagName] === undefined) {
                data.push({
                    name: tagName,
                    value: tagValue
                });
            }
        });
        me.store.loadData(data);
    },

    isValid: function(){
        var me = this,
            isValid = true;
        me.store.getUnfiltered().each(function(record){
            var name = Ext.String.trim(record.get('name'));
            if (!name || Ext.Array.contains(me.reservedNames, name) && !record.get('system')) {
                var widget = me.columns[0].getWidget(record);
                isValid = widget.validate();
                widget.focus();
                return false;
            }
        });
        return isValid;
    },

    getValue: function(){
        var me = this,
            result  = {};
        me.store.getUnfiltered().each(function(record){
            var name = record.get('name'),
                value = record.get('value');
            if (name && !me.systemTags[name] && !record.get('ignoreOnSave')) {
                result[name] = value;
            }
        });
        return result;
    },

    setTagsLimit: function(tagsLimit) {
        this.tagsLimit = tagsLimit;
        this.applyTagsLimit();
    },

    applyTagsLimit: function() {
        var me = this,
            view = me.view,
            tooltip,
            tagsLimit;
        if (me.tagsLimit) {
            tagsLimit = me.allowNameTag ? me.tagsLimit : me.tagsLimit - 1
            tooltip = me.readOnly ? me.governanceTooltip : 'Tag limit of ' + tagsLimit + ' reached' + (!me.allowNameTag ? ' (1 tag reserved for Name)' : '');
            view.findFeature('addbutton').setDisabled((view.store.snapshot || view.store.data).length >= tagsLimit || me.readOnly, tooltip);
        } else {
            view.findFeature('addbutton').setDisabled(me.readOnly, me.readOnly ? me.governanceTooltip : '');
        }

    }
});

Ext.define('Scalr.ui.FieldProgressBar', {
    extend: 'Scalr.ui.FormFieldProgress',
    alias: 'widget.fieldprogressbar',

    warningPercentage: 0,
    alertPercentage: 0,
    warningCls: 'x-form-progress-bar',
    alertCls: 'x-form-progress-bar',

    emptyText: 'Loading...',
    units: '%',

    minStep: 1,
    maxStep: 5,

    // max loading time in seconds
    loadingTime: 100,

    value: 0.001,

    getRandomInt: function (min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    },

    doStep: function () {
        var me = this;

        var step = me.getRandomInt(me.minStep, me.maxStep);

        if (me.getRandomInt(0, 1)) {
            step = step / 10;
        }

        var newValue = me.getValue() + step / me.loadingTime;

        if (newValue >= 1) {
            me.setValue(1);

            return me;
        }

        me.setValue(newValue);

        new Ext.util.DelayedTask(function () {
            if (me.rendered) {
                me.doStep();
            }
        }).delay(step * 1000);

        return me;
    },

    onRender: function () {
        var me = this;

        me.callParent(arguments);

        new Ext.util.DelayedTask(function () {
            if (me.rendered) {
                me.doStep();
            }
        }).delay(1000);
    },

    beforeDestroy: function () {
        var me = this;
        me.setValue(1);
    }
});

Ext.define('Scalr.ui.view.TagBoundListKeyNav', {
    extend: 'Ext.view.BoundListKeyNav',
    alias: 'view.navigation.tagboundlist',

    onKeyEnter: function(e) {
        var me = this,
            field = me.view.pickerField,
            inputEl = field.inputEl,
            rawValue = inputEl.dom.value,
            preventKeyUpEvent = field.preventKeyUpEvent;

        rawValue = Ext.Array.clean(rawValue.split(field.delimiterRegexp));
        inputEl.dom.value = '';
        field.setValue(field.valueStore.getRange().concat(rawValue));
        field.collapse();
        inputEl.focus();
    },

    onKeyTab: function(e) {
        var selModel = this.view.getSelectionModel(),
            field = this.view.pickerField,
            count = selModel.getCount();

        this.selectHighlighted(e);

        // Handle the case where the highlighted item is already selected
        // In this case, the change event won't fire, so just collapse
        if (!field.multiSelect && count === selModel.getCount()) {
            field.collapse();
        }
    },

    selectHighlighted: function(e) {
        var boundList = this.view,
            selModel = boundList.getSelectionModel(),
            highlightedRec;

        highlightedRec = boundList.getNavigationModel().getRecord();
        if (highlightedRec) {

            // Select if not already selected.
            // If already selected, selecting with no CTRL flag will deselect the record.
            if (e.getKey() === e.TAB || !selModel.isSelected(highlightedRec)) {
                selModel.selectWithEvent(highlightedRec, e);
            }
        }
    }
});

Ext.define('Scalr.ui.form.field.TagList', {
    extend: 'Ext.form.field.Tag',
    alias: 'widget.taglistfield',

    createNewOnEnter: true,

    anyMatch: true,

    hideTrigger: true,

    //createNewOnBlur: true,

    queryMode: 'local',

    displayField: 'tag',

    valueField: 'tag',

    delimiter: ',',

    suspendCollapse: false,

    cls: 'scalr-ui-form-field-tag',

    lastQuery: '',

    initComponent: function () {
        var me = this;

        me.store = me.store || Ext.create('Ext.data.Store', {
            proxy: 'object',
            fields: ['tag']
        });

        me.plugins = me.plugins || [];
        me.plugins.push({
            ptype: 'addnewtag',
            pluginId: 'addnewtag'
        });

        me.callParent(arguments);
    },

    initEvents: function () {
        var me = this;

        me.on('change', function (me, value, oldValue) {
            var removedTag = Ext.Array.difference(
                oldValue, value
            )[0];

            if (removedTag) {
                var record = me.valueStore.findRecord('tag', removedTag);

                if (record) {
                    me.getPicker().refresh();
                }
            }
        });

        me.on('beforequery', function (queryPlan) {
            me.enteredValue = queryPlan.query;
        });

        me.callParent(arguments);
    },

    onFieldMutation: function(e) {
        var me = this,
            key = e.getKey(),
            isDelete = key === e.BACKSPACE || key === e.DELETE,
            rawValue = me.inputEl.dom.value,
            len = rawValue.length;

        // Do not process two events for the same mutation.
        // For example an input event followed by the keyup that caused it.
        // We must process delete keyups.
        // Also, do not process TAB event which fires on arrival.
        if (!me.readOnly && (rawValue !== me.lastMutatedValue || isDelete) && key !== e.TAB) {
            me.lastMutatedValue = rawValue;
            me.lastKey = key;

            /** Changed **/
            //if (len && (e.type !== 'keyup' || (!e.isSpecialKey() || isDelete))) {
            if (e.type !== 'keyup' || (!e.isSpecialKey() || isDelete)) {
            /** Changed **/
                me.doQueryTask.delay(me.queryDelay);
            } else {
                // We have *erased* back to empty if key is a delete, or it is a non-key event (cut/copy)
                if (!len && (!key || isDelete)) {
                    // Essentially a silent setValue.
                    // Clear our value, and the tplData used to construct a mathing raw value.
                    if (!me.multiSelect) {
                        me.value = null;
                        me.displayTplData = undefined;
                    }
                    // Just erased back to empty. Hide the dropdown.
                    me.collapse();

                    // There may have been a local filter if we were querying locally.
                    // Clear the query filter and suppress the consequences (we do not want a list refresh).
                    if (me.queryFilter) {
                        // Must set changingFilters flag for this.checkValueOnChange.
                        // the suppressEvents flag does not affect the filterchange event
                        me.changingFilters = true;
                        me.store.removeFilter(me.queryFilter, true);
                        me.changingFilters = false;
                    }
                }
                me.callParent([e]);
            }
        }
    },

    doLocalQuery: function(queryPlan) {
        var me = this,
            queryString = queryPlan.query,
            store = me.getStore(),
            filter = me.queryFilter;

        me.queryFilter = null;

        // Must set changingFilters flag for this.checkValueOnChange.
        // the suppressEvents flag does not affect the filterchange event
        me.changingFilters = true;
        if (filter) {
            store.removeFilter(filter, true);
        }

        // Querying by a string...
        if (queryString) {
            filter = me.queryFilter = new Ext.util.Filter({
                id: me.id + '-filter',
                anyMatch: me.anyMatch,
                caseSensitive: me.caseSensitive,
                root: 'data',
                property: me.displayField,
                value: me.enableRegEx ? new RegExp(queryString) : queryString
            });
            /** Changed **/
            //store.addFilter(filter, true);
            store.addFilter(filter);
            /** Changed **/
        }
        me.changingFilters = false;

        // Expand after adjusting the filter if there are records or if emptyText is configured.
        if (me.store.getCount() || me.getPicker().emptyText) {
            // The filter changing was done with events suppressed, so
            // refresh the picker DOM while hidden and it will layout on show.
            me.getPicker().refresh();
            me.expand();
        } else {
            /** Changed **/
            //me.collapse();
            if (!me.suspendCollapse) {
                me.collapse();
            }
            /** Changed **/
        }

        me.afterQuery(queryPlan);
    },

    applyRawValue: function () {
        var me = this;

        var inputEl = me.inputEl;
        var rawValue = inputEl.dom.value;

        inputEl.dom.value = '';

        rawValue = Ext.Array.clean(
            rawValue.split(me.delimiterRegexp)
        );

        me.setValue(
            me.valueStore.
                getRange().concat(rawValue)
        );

        inputEl.focus();

        me.collapse();

        return me;
    },

    onKeyUp: function(e, t) {
        var me = this,
            inputEl = me.inputEl,
            rawValue = inputEl.dom.value,
            preventKeyUpEvent = me.preventKeyUpEvent;

        if (me.preventKeyUpEvent) {
            e.stopEvent();
            if (preventKeyUpEvent === true || e.getKey() === preventKeyUpEvent) {
                delete me.preventKeyUpEvent;
            }
            return;
        }

        if (me.multiSelect && me.delimiterRegexp && me.delimiterRegexp.test(rawValue) ||
            (me.createNewOnEnter && e.getKey() === e.ENTER)) {
            /** Changed **/
            me.applyRawValue();
            /** Changed **/
        }

        me.callParent([e,t]);
    },

    setValue: function (value, add, skipLoad) {
        var me = this;

        if (!Ext.isEmpty(value)) {

            value = typeof value === 'string'
                ? value.split(me.delimiter)
                : value;

            var lastValue = value[value.length - 1];

            if (!lastValue || lastValue.isModel) {
                return me;
            }

            if (!me.isTagValid(lastValue)) {
                value.pop();
                if (me.rendered && me.tagRegexText) {
                    Scalr.message.InfoTip(me.tagRegexText, me.el, {anchor: 'bottom'});
                }
            }
        }

        return me.callParent([value, add, skipLoad]);
    },

    isTagValid: function (value) {
        if (!value || !this.tagRegex) {
            return true;
        }

        var values = value.split(',');
        var newValue = values[values.length - 1];

        return this.tagRegex.exec(newValue);
    },

    getSubmitValue: function () {
        var me = this;
        return me.getValue().join(',');
    },

    // temp fix
    reset: function () {
        var me = this;

        me.setValue();
        me.inputEl.dom.value = '';

        me.callParent();
    }
});

Ext.define('Scalr.ui.form.field.Tag', {
    extend: 'Scalr.ui.form.field.TagList',
    alias: 'widget.scalrtagfield',

    hideTrigger: false,

    tagRegex: /^[a-zA-Z0-9-]{3,10}$/,
    tagRegexText: 'Tag name can only contain letters and numbers, and must be between 3 and 10 characters long.',

    initComponent: function () {
        var me = this;

        me.callParent(arguments);

        me.tags = Scalr.tags;
        me.cachedTags = Ext.clone(me.tags);

        me.saveTagsOn = me.saveTagsOn || 'submit';
        me.store.load({data: me.loadTags()})

    },

    beforeRender: function () {
        var me = this;

        me.initTagsSaving();

        me.callParent();
    },

    loadTags: function () {
        var me = this;
        var tags = me.tags;
        var tagsModel = [];

        Ext.each(tags, function (tag) {
            tagsModel.push({
                tag: tag
            });
        });

        return tagsModel;
    },

    addTagToStore: function (tag) {
        var me = this;
        var tagExists = me.isTagExist(tag, me.cachedTags);
        var store = me.getStore();

        if (tag && !tagExists) {
            store.add({tag: tag});
            me.cachedTags.push(tag);
        }
    },

    isTagExist: function (enteredValue, tags) {
        var me = this;

        tags = tags || me.tags;

        return tags.some(function (tag) {
            return tag === enteredValue;
        });
    },

    saveTags: function () {
        var me = this;
        var enteredTags = me.getRawValue().split(',');
        var existingTags = me.tags;

        Ext.each(existingTags, function (tag, i, tags) {
            if (!tag) {
                tags.splice(i, 1);
            }
        });

        Ext.each(enteredTags, function (tag) {
            if (tag && !me.isTagExist(tag)) {
                existingTags.push(tag);
            }
        });
    },

    initTagsSaving: function () {
        var me = this;
        var saveEvents = me.tagsSavingEvents;
        var event = me.saveTagsOn;

        if (saveEvents.hasOwnProperty(event)) {
            saveEvents[event](me);
        }
    },

    // this is valid values ​​for saveTagsOn flag
    tagsSavingEvents: {
        submit: function (tagBox) {
            var form = tagBox.up('form');

            form.on('actioncomplete', function () {
                tagBox.saveTags();
            });
        }
    },

    setValue: function (value, add, skipLoad) {
        var me = this;

        if (!Ext.isEmpty(value)) {

            value = typeof value === 'string'
                ? value.split(me.delimiter)
                : value;

            var lastValue = value[value.length - 1];

            if (!lastValue || lastValue.isModel) {
                return me;
                //return me.callParent([null, add, skipLoad]);
            }

            if (me.isTagValid(lastValue)) {
                me.addTagToStore(lastValue);
            } else {
                value.pop();
                Scalr.message.InfoTip(me.tagRegexText, me.el);
            }
        }

        return me.callParent([value, add, skipLoad]);
    }


});

Ext.define('Scalr.ui.CloudLocationField', {
    extend: 'Ext.form.FieldContainer',
    alias: 'widget.cloudlocationfield',

    mode: 'clouds',
    allCloudsText: '&nbsp;All locations',
    localLocationParamName: 'grid-ui-default-cloud-location',
    config: {
        platforms: []
    },

    initComponent: function() {
        var me = this;
        me.callParent(arguments);
        me.on('change', function(field, value){
            if (this.gridStore) {
                this.gridStore.applyProxyParams(value);
            }
        });
        me.add([{
            xtype: 'hiddenfield',
            itemId: 'platform',
            name: 'platform',
            allowBlank: false,
            listeners: {
                change: function(field, value) {
                    var field = this.up('cloudlocationfield');
                    this.next('#cloudLocation').setValue(field.initialCloudLocation || '');
                    delete field.initialCloudLocation;
                    field.updateButtonText();
                    field.fireEvent('change', field, {platform: value});
                }
            }
        },{
            xtype: 'hiddenfield',
            itemId: 'cloudLocation',
            name: 'cloudLocation',
            allowBlank: false,
            listeners: {
                change: function(field, value) {
                    var field = this.up('cloudlocationfield');
                    field.updateButtonText();
                    if (field.mode === 'locations' && value) {
                        Ext.state.Manager.set(me.localLocationParamName, value);
                    }
                    field.fireEvent('change', field, {
                        platform: this.prev('#platform').getValue(),
                        cloudLocation: value
                    });
                }
            }
        },{
            xtype: 'button',
            text: me.allCloudsText,
            menu: {
                cls: 'x-menu-light x-menu-cycle-button-filter',
                enableKeyNav: false,
                items: [{
                    text: me.allCloudsText,
                    value: ''
                }],
                defaults: {
                    handler: function(item) {
                        me.onCloudLocationSelect.apply(me, me.mode === 'clouds' ? [item.value] : [me.down('#platform').getValue(), item.value]);
                    },
                    listeners: {
                        afterrender: function() {
                            if (me.mode === 'clouds') {
                                this.el.on({
                                    mouseover: function(){
                                        if (this.value) me.onPlatformMouseOver(this.value);
                                    },
                                    mouseleave: function(){
                                        if (this.value) me.onPlatformMouseLeave(this.value);
                                    },
                                    scope: this
                                });
                            }
                        }
                    }
                },
                listeners: {
                    activate: function() {
                        var value = me.down(me.mode === 'clouds' ? '#platform' : '#cloudLocation').getValue() || '';
                        this.items.each(function(item){
                            item.removeCls('x-menu-item-checked');
                        });
                        var item = value ? this.down('[value="'+value+'"]') : this.items.first();
                        if (item) item.addCls('x-menu-item-checked');
                    }
                }
            }
        }]);
    },

    updateButtonText: function() {
        var platform = this.down('#platform').getValue(),
            cloudLocation = this.down('#cloudLocation').getValue(),
            text;
        text = platform ? '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-'+platform+'" style="position:relative;top:-2px" />' : this.allCloudsText;
        if (platform && cloudLocation) {
            text += '&nbsp;&nbsp;' + cloudLocation;
        } else if (platform) {
            text += '&nbsp;&nbsp;' + Scalr.utils.getPlatformName(platform);
        }
        this.down('button').setText(text);
    },
    updatePlatforms: function(newPlatforms, oldPlatforms) {
        if (this.rendered) {
            this.onUpdatePlatforms();
        } else {
            this.on('boxready', this.onUpdatePlatforms, this, {single: true});
        }
    },

    onUpdatePlatforms: function() {
        var me = this,
            platforms = this.getPlatforms(),
            menu = me.down('button').menu,
            menuItems = [];
        if (platforms.length !== 1) {
            me.mode = 'clouds';
            menuItems.push({
                text: me.allCloudsText,
                value: ''
            });
            menu.removeAll();
            Ext.each(platforms, function(platform){
                menuItems.push({
                    style: 'padding-right:30px',
                    text: '&nbsp;' + Scalr.utils.getPlatformName(platform) + '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-tool-img x-tool-expand-small x-menuitem-expander" />',
                    value: platform,
                    iconCls: 'x-icon-platform-small x-icon-platform-small-' + platform
                });
            });
            menu.add(menuItems);
        } else {
            me.mode = 'locations';
            if (me.forceAllLocations) {
                menuItems.push({
                    text: me.allCloudsText,
                    value: ''
                });
            }
            menu.addCls('x-menu-light-no-icon');
            me.loadingLocations = true;
            cb = function(locations) {
                if (locations) {
                    Ext.Object.each(locations, function(key, value){
                        menuItems.push({
                            text: value,
                            value: key
                        });
                    });
                    me.initialCloudLocation = '';
                    if (!me.forceAllLocations) {
                        var location = Ext.state.Manager.get(me.localLocationParamName);
                        if (locations[location] !== undefined) {
                            me.initialCloudLocation = location;
                        } else {
                            me.initialCloudLocation = menuItems.length ? menuItems[0].value : '';
                        }
                    }
                    me.down('#platform').setValue(platforms[0]);
                    //me.down('#cloudLocation').setValue();
                }

                menu.removeAll();
                menu.add(menuItems);

                if (me.loadingLocationsCallback) {
                    me.loadingLocationsCallback();
                    delete me.loadingLocationsCallback;
                }
                me.loadingLocations = false;

            }
            if (me.locations) {
                cb(me.locations);
            } else {
                Scalr.loadCloudLocations(platforms[0], cb);
            }
        }
    },

    onCloudLocationSelect: function(platform, cloudLocation) {
        var field = this.down('#platform');
        field.suspendEvents(false);
        field.setValue(platform);
        field.resumeEvents();

        field = this.down('#cloudLocation');
        field.suspendEvents(false);
        field.setValue(' ');
        field.resumeEvents();
        field.setValue(cloudLocation || '');

        this.down('button').menu.hide();
    },

    loadCloudLocationsBuffered: function(platform) {
        var me = this;
        clearTimeout(me.loadLocationTimer);
        me.loadLocationTimer = Ext.defer(function(){
            var parentMenuItem = me.down('button').menu.down('[value="'+platform+'"]');
            if (!parentMenuItem.menu) {
                parentMenuItem.setMenu({
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    enableKeyNav: false,
                    loading: true,
                    items: {
                        text: '&nbsp;Loading locations',
                        iconCls: 'x-icon-loading-list',
                        hideOnClick: false
                    },
                }, true);
            }
            parentMenuItem.doExpandMenu();

            Scalr.loadCloudLocations(platform, function(locations) {me.onLoadCloudLocations(platform, locations);}, false);
        }, Scalr.platforms[platform] && Scalr.platforms[platform].locations ? 0 : 100);
    },

    onPlatformMouseOver: function(platform) {
        var me = this;
        me.currentPlatform = platform;
        me.loadCloudLocationsBuffered(platform);
    },

    onShowLocations: function(platform, cloudLocations) {
        var me = this,
            parentMenuItem = me.down('button').menu.down('[value="'+platform+'"]'),
            menuItems = [];
        if (!parentMenuItem.menu || parentMenuItem.menu.loading || !parentMenuItem.menu.isVisible()) {
            menuItems.push({
                text: 'All locations',
                value: ''
            });
            if (cloudLocations) {
                Ext.Object.each(cloudLocations, function(key, value){
                    menuItems.push({
                        text: value,
                        value: key
                    });
                });
            }
            parentMenuItem.setMenu({
                cls: 'x-menu-light x-menu-cycle-button-filter',
                enableKeyNav: false,
                items: menuItems,
                defaults: {
                    handler: function(item) {
                        me.onCloudLocationSelect(platform, item.value);
                    }
                },
                listeners: {
                    activate: function() {
                        var cloudLocation = me.down('#cloudLocation').getValue() || '';
                        this.items.each(function(item){
                            item.removeCls('x-menu-item-checked');
                        });
                        if (parentMenuItem .value == me.down('#platform').getValue()) {
                            var item = cloudLocation ? this.down('[value="'+cloudLocation+'"]') : this.items.first();
                            if (item) item.addCls('x-menu-item-checked');
                        }
                    }
                }
            }, true);
            parentMenuItem.doExpandMenu();
        }
    },

    onPlatformMouseLeave: function() {
        if (this.currentPlatform) {
            this.down('button').menu.down('[value="'+this.currentPlatform+'"]').el.down('.x-menuitem-expander').removeCls('x-icon-loading-list');
            delete this.currentPlatform;
            clearTimeout(this.loadLocationTimer);
        }
    },

    onLoadCloudLocations: function(platform, cloudLocations) {
        if (this.currentPlatform === platform) {
            this.down('button').menu.down('[value="'+this.currentPlatform+'"]').el.down('.x-menuitem-expander').removeCls('x-icon-loading-list');
            this.onShowLocations(platform, cloudLocations);
        }
    },

    beforeApplyParams: function(callback) {
        if (this.loadingLocations) {
            this.loadingLocationsCallback = callback;
        } else {
            callback();
        }
    }
});

Ext.define('Scalr.ui.ReCaptchaField', {
    extend:'Ext.form.field.Base',
    alias: 'widget.recaptchafield',

    fieldSubTpl: [
        '<div id="{id}" role="{role}" {inputAttrTpl}',
        '<tpl if="fieldStyle"> style="{fieldStyle}"</tpl>',
        ' class="{fieldCls} {fieldCls}-{ui}"><span style="font-style: italic">Loading reCaptcha</span></div>',
        {
            compiled: true,
            disableFormats: true
        }
    ],

    focusable: false,
    readOnly: true,
    height: 78,
    minWidth: 304,

    initEvents: function() {
        var me = this;

        me.callParent();

        if (Scalr.flags['recaptchaPublicKey']) {
            if (this.isVisible()) {
                this.renderRecaptcha();
            } else {
                this.on('show', this.renderRecaptcha, this, {
                    single: true
                });
            }
        } else {
            this.hide();
            this.disable();
        }
    },

    callbackRecaptcha: function() {
        this.validate();
    },

    renderRecaptcha: function() {
        if ('grecaptcha' in window) {
            this.inputEl.update('');
            this.recaptchaEl = grecaptcha.render(this.inputEl.dom, {
                'sitekey': Scalr.flags['recaptchaPublicKey'],
                'callback': this.callbackRecaptcha.bind(this)
            });
        } else {
            this.loadRecaptcha();
        }
    },

    beforeDestroy: function() {
        if (Ext.isDefined(this.recaptchaEl)) {
            grecaptcha.reset(this.recaptchaEl);

            // google recaptcha issue: if captcha was checked, after remove -> error
            var plsContainers = document.body.getElementsByClassName('pls-container');
            for(var i = 0; i < plsContainers.length; i++){
                var parent = plsContainers[i].parentNode;
                while (parent.firstChild) {
                    parent.removeChild(parent.firstChild);
                }
            }
        }
    },

    loadRecaptcha: function() {
        reCaptchaLoadedCallback = (function() {
            this.renderRecaptcha();
            delete reCaptchaLoadedCallback;
        }).bind(this);
        Ext.Loader.loadScalrScript('https://www.google.com/recaptcha/api.js?onload=reCaptchaLoadedCallback&render=explicit');
    },

    isDirty: function() {
        return false;
    },

    isValid: function() {
        return Ext.isDefined(this.recaptchaEl) ? !!grecaptcha.getResponse(this.recaptchaEl) : (this.isDisabled() ? true : false);
    },

    getRawValue: function() {
        return Ext.isDefined(this.recaptchaEl) ? grecaptcha.getResponse(this.recaptchaEl) : '';
    },

    reset: function() {
        if (Ext.isDefined(this.recaptchaEl)) {
            grecaptcha.reset(this.recaptchaEl);
        }
    },

    setRawValue: function(value) {

    }
});

Ext.define('Scalr.ui.FormInstanceTypeField', {
    extend: 'Ext.form.field.ComboBox',
    alias: 'widget.instancetypefield',

    editable: true,
    hideInputOnReadOnly: true,
    queryMode: 'local',
    fieldLabel: 'Instance type',
    anyMatch: true,
    autoSearch: false,
    selectOnFocus: true,
    restoreValueOnBlur: true,
    markInvalidInstaceType: false,
    nl2brErrors: true,
    store: {
        fields: [ 'id', 'name', 'note', 'ram', 'type', 'vcpus', 'disk', 'ebsencryption', 'ebsoptimized', 'placementgroups', 'instancestore', {name: 'disabled', defaultValue: false}, 'disabledReason' ],
        proxy: 'object',
        sorters: {
            property: 'disabled'
        }
    },
    valueField: 'id',
    displayField: 'name',
    listConfig: {
        emptyText: 'No instance type matching query',
        emptyTextTpl: new Ext.XTemplate(
            '<div style="margin:8px 8px 0">' +
                '<span class="x-semibold">No instance type matching query</span>' +
                '<div style="line-height:24px">Instance types unavailable in <i>{cloudLocation}</i><tpl if="limits"> or restricted by Governance</tpl> are not listed</div>' +
            '</div>'
        ),
        cls: 'x-boundlist-alt',
        tpl:
            '<tpl for="."><div class="x-boundlist-item" style="white-space:nowrap;height: auto; width: auto;<tpl if="disabled">color:#999</tpl>">' +
                '<div><span class="x-semibold">{name}</span> &nbsp;<tpl if="disabled"><span style="font-size:12px;font-style:italic">({[values.disabledReason||\'Not compatible with the selected image\']})</span></tpl></div>' +
                '<div style="line-height: 26px;white-space:nowrap;">{[this.instanceTypeInfo(values)]}</div>' +
            '</div></tpl>'
    },
    updateListEmptyText: function(data) {
        var picker = this.getPicker();
        if (picker) {
            picker.emptyText = picker.emptyTextTpl.apply(data);
        }
    },
    initComponent: function() {
        this.plugins = {
            ptype: 'fieldicons',
            position: this.iconsPosition || 'inner',
            icons: [{id: 'governance', tooltip: 'The account owner has limited which instance types can be used in this Environment'}]
        };
        this.callParent(arguments);
        this.on('beforeselect', function(comp, record){
            if (record.get('disabled') && comp.getPicker().isVisible()) {
                return false;
            }
        }, this, {priority: 1});

        if (this.markInvalidInstaceType) {
            this.validateOnChange = false;
            this.on('change', function(comp, value){
                comp.refreshInvalidState();
            });
        }
    },
    refreshInvalidState: function(value) {
        if (!this.markInvalidInstaceType) {
            return;
        }
        var record = this.findRecordByValue(value !== undefined ? value : this.getValue());
        if (record && record.get('disabled')) {
            this.markInvalid('Instance type ' + record.get('name') + ' is not compatible with selected Role.\n Please choose another one.');
        } else {
            this.clearInvalid();
        }
    }
});

Ext.define('Scalr.ui.FormFieldPassword', {
    extend: 'Ext.form.field.Text',
    alias: 'widget.passwordfield',

    inputType: 'password',
    placeholder: '******',
    initialValue: null,

    isPasswordEmpty: function(password) {
        return Ext.isEmpty(password) || password === false;
    },

    getSubmitData: function() {
        if (!this.isPasswordEmpty(this.initialValue) && this.getValue() == this.placeholder ||
            this.isPasswordEmpty(this.initialValue) && this.getValue() == '') {
            return null;
        } else {
            return this.callParent(arguments);
        }
    },

    getModelData: function() {
        var data = {};
        data[this.getName()] = this.getValue() != '' ? true : false;
        return data;
    },

    setValue: function(value) {
        this.initialValue = value;
        if (!this.isPasswordEmpty(value)) {
            value = this.placeholder;
        } else {
            value = '';
        }
        this.callParent(arguments);
    }

});

Ext.define('Scalr.form.field.Password', {
    extend: 'Scalr.ui.FormFieldPassword',
    alias: 'widget.scalrpasswordfield',

    /*config: {
        triggers: {
            state: {
                cls: Ext.baseCSSPrefix + 'passwordfield-state'
            }
        }
    },*/

    // TODO: remove or replace x-passwordfield style and corresponding image sprite
    //cls: Ext.baseCSSPrefix + 'passwordfield',

    defaultSets: {
        'lowercase': 'abcdefghjkmnpqrstuvwxyz',
        'uppercase': 'ABCDEFGHJKMNPQRSTUVWXYZ',
        'digit': '1234567890',
        'special symbols': '!@#$%&*?'
    },

    strengthLevelNames: [ 'invalid', 'weak', 'fair', 'good', 'strong' ],

    minPasswordLengthAdmin: false,
    minPasswordLengthText: 'The minimum password length is {0} characters.',

    //regex: /^[ A-Za-z0-9!@#$%&*?]*$/,
    //regexText: 'Password should contain only upper- and lower-case letters, numbers and special symbols: !@#$%&*?',

    getErrors: function(value) {
        value = arguments.length ? (value == null ? '' : value) : this.processRawValue(this.getRawValue());

        var me = this,
            errors = me.callParent(arguments),
            groups = [],
            passLen = me.minPasswordLengthAdmin ? 15 : 8;

        if (Ext.isEmpty(value) || value == '******') {
            //me.setStrengthLevelCls();
        } else {
            Ext.Object.each(me.defaultSets, function (key, set) {
                var verified = Ext.Array.some(set.split(''), function (symbol) {
                    return value.indexOf(symbol) !== -1;
                });

                if (!verified) {
                    groups.push(key);
                }
            });

            if (value.length < passLen) {
                errors.push(Ext.String.format(me.minPasswordLengthText, passLen));
            }

            if (groups.length) {
                errors.push("Password doesn't contain any characters from the following group(s): " + groups.join(", "));
            }

            //me.setStrengthLevelCls(me.strengthLevelNames[4 - groups.length]);
        }

        return errors;
    },

    setStrengthLevelCls: function (levelName) {
        var me = this;

        if (me.rendered) {
            var stateTriggerEl = me.getTrigger('state').getEl();
            var stateClsPrefix = Ext.baseCSSPrefix + 'password-';

            Ext.Array.each(me.strengthLevelNames, function (name) {
                if (name === levelName) {
                    stateTriggerEl.addCls(stateClsPrefix + name);
                } else {
                    stateTriggerEl.removeCls(stateClsPrefix + name);
                }
            });
        }

        return me;
    }
});

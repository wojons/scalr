Ext.define('Scalr.ui.VariableField', {
    extend: 'Ext.container.Container',
    mixins: {
        field: 'Ext.form.field.Field'
    },
    alias: 'widget.variablefield',

    currentScope: 'env',
    addFieldCls: '',
    encodeParams: true,

    initComponent : function() {
        var me = this;
        me.callParent();
        me.initField();

        me.addEvents('addvar', 'editvar', 'removevar');
    },

    markInvalid: function(errors) {
        var i, ct = this.down('#ct'), fields = this.query('variablevaluefield'), link = {}, name, j, f;

        for (i = 0; i < fields.length; i++) {
            name = fields[i].down('[name="name"]').getValue();
            if (name)
                link[name] = fields[i];
        }

        for (i in errors) {
            if (link[i]) {
                for (j in errors[i]) {
                    f = link[i].down('[name="' + j + '"]');
                    if (f)
                        f.markInvalid(errors[i][j]);
                }
            }
        }
    },

    isValid: function() {
        var items = this.down('#ct').query('variablevaluefield'), length = items.length, i, valid = true;
        for (i = 0; i < length; i++) {
            valid = valid && items[i].down('[name="value"]').isValid();
        }

        return valid;
    },

    onResize: function() {
        var lbWidth = 0, ctWidth, curWidth, width, ct = this.down('#ct'), el;
        ct.items.each(function(c) {
            var w = c.child('[name="name"]').computedWidth;
            if (w > lbWidth)
                lbWidth = w;
        });

        el = ct.child('[hidden=false]') || ct.items.getAt(0);

        curWidth = el.child('[name="name"]').getWidth() + el.child('[name="newName"]').getWidth();
        ctWidth = el.child('[name="newName"]').next().getWidth();

        if (lbWidth < 150) {
            width = 150;
        } else {
            width = Math.ceil((ctWidth + curWidth) / 2); // 50/50
            if (width > lbWidth)
                width = lbWidth;
            else if (width == 0)
                width = 150;
        }

        ct.suspendLayouts();
        ct.items.each(function(c) {
            c.child('[name="name"]').setWidth(width);
            c.child('[name="newName"]').setWidth(width);
        });

        this.down('#labelContainer').child().setWidth(width);
        this.down('#showLockedVars').setWidth(curWidth + ctWidth + 5);
        ct.resumeLayouts();
        this.updateLayout();
    },

    getValue: function() {
        var fields = this.query('variablevaluefield'), variables = [];
        for (var i = 0; i < fields.length; i++) {
            var values = fields[i].getFieldValues();
            if (values)
                variables.push(values);
        }

        return this.encodeParams ? Ext.encode(variables) : variables;
    },

    setValue: function(value) {
        value = (this.encodeParams ? Ext.decode(value, true) : value) || [];
        var ct = this.down('#ct'), me = this, currentScope = this.currentScope, i, f, names = [], isLockedVars = false;

        this.down('#showLockedVars').resetLockedVars();
        ct.suspendLayouts();
        ct.removeAll();

        this.suspendEvent('addvar', 'editvar', 'removevar');

        // for flagRequiredScope
        var allowedScopes = [], allowedAllScopes = [], sc = { 'scalr': 'Scalr', 'account': 'Account', 'env': 'Environment', 'role': 'Role', 'farm': 'Farm', 'farmrole': 'FarmRole' }, sca = Ext.Object.getKeys(sc);
        sca = sca.slice(sca.indexOf(currentScope) + 1 + (currentScope == 'role' ? 1 : 0)); // if currentScope == role, exclude farm
        for (i = 0; i < sca.length; i++) {
            allowedScopes.push([ sca[i], sc[sca[i]]]);
        }
        for (i in sc) {
            allowedAllScopes.push([ i, sc[i] ]);
        }

        for (i = 0; i < value.length; i++) {
            f = ct.add({
                currentScope: currentScope,
                xtype: 'variablevaluefield',
                originalValues: value[i]
            });
            var current = { name: value[i]['name'] };

            if (value[i]['current']) {
                if (value[i]['current']['flagRequired'])
                    current['flagRequiredScope'] = value[i]['current']['flagRequired']; // it should be set at first, because of flagRequired field
                else
                    current['flagRequiredScope'] = 'farmrole';

                Ext.apply(current, value[i]['current']);
                f['scope'] = value[i]['current']['scope'];
            }

            if (value[i]['default']) {
                f['scope'] = f['scope'] || value[i]['default']['scope'];
                f['defaultScope'] = value[i]['default']['scope'];
                // set up-level value
                f.down('[name="value"]').emptyText = value[i]['default']['value'];
            } else {
                f['defaultScope'] = currentScope;
            }

            names.push(current['name']);
            current['newValue'] = false;

            f.down('[name="flagRequiredScope"]').store.loadData((value[i]['locked'] || value[i]['default']) ? allowedAllScopes : allowedScopes);
            f.setFieldValues(current);

            if (value[i]['locked'] || value[i]['default']) {
                // variable has upper value, block flags
                var locked = value[i]['locked'] || {};
                if (locked['flagRequired'])
                    f.down('[name="flagRequiredScope"]').setValue(locked['flagRequired']).setReadOnly(true);

                f.down('[name="flagRequired"]').setValue(locked['flagRequired']).disable();
                f.down('[name="flagFinal"]').setValue(locked['flagFinal']).disable();
                f.down('[name="flagHidden"]').setValue(locked['flagHidden']).disable();

                if (locked['flagFinal'] == 1) {
                    f.down('[name="value"]').setReadOnly(true);
                    f.down('[name="value"]').reset(); // fix issue, when variable was redefined on upper level and lock interface
                    f.down('#reset').disable();
                    f.down('#delete').disable();
                    f.lockedVar = true;
                    isLockedVars = true;
                    f.hide();
                }

                f.down('[name="validator"]').setValue(locked['validator']).setReadOnly(true);
                f.down('[name="validator"]').emptyText = '';
                f.down('[name="format"]').setValue(locked['format']).setReadOnly(true);
                f.down('[name="format"]').emptyText = '';

                if (locked['flagRequired'] || locked['format'] || locked['validator']) {
                    f.down('#configure').toggle(true);
                }

                var valueField = f.down('[name="value"]');
                if (locked['flagRequired'] == currentScope && !valueField.emptyText) {
                    valueField.allowBlank = false;
                    valueField.isValid();
                }

                f.down('#configure').disable();
            }

            if (value[i]['current'] && (value[i]['current']['flagRequired'] || value[i]['current']['format'] || value[i]['current']['validator'])) {
                f.down('#configure').toggle(true);
            }

            // not to remove variables from higher scopes
            if (value[i]['default'])
                f.down('#delete').disable();

            if (value[i]['flagDelete'] == 1)
                f.hide();

            if (currentScope == 'farmrole') {
                // or required and hidden for last scope (farmrole)
                f.down('[name="flagRequired"]').disable();
                f.down('[name="flagHidden"]').disable();
            }

            if (f.down('[name="validator"]').getValue()) {
                try {
                    f.down('[name="value"]').regex = new RegExp(f.down('[name="validator"]').getValue());
                    f.down('[name="value"]').isValid();
                } catch (e) {}
            }
        }

        if (value.length < 10)
            this.down('filterfield').hide();
        else
            this.down('filterfield').show();

        this.resumeEvent('addvar', 'editvar', 'removevar');
        if (isLockedVars)
            this.down('#showLockedVars').show();

        var handler = function() {
            // check, if last new variable was filled
            var items = ct.items.items, names = [], added = null;
            for (var i = 0; i < items.length; i++) {
                if (items[i].xtype == 'variablevaluefield') {
                    if (items[i].down('[name="newValue"]').getValue() == 'true') {
                        added = items[i];
                        var field = added.down('[name="newName"]');
                        if (field.isValid()) {
                            if (! field.getValue()) {
                                field.markInvalid('Name is required');
                                return;
                            }

                            items[i].down('[name="newValue"]').setValue(false);
                        } else {
                            return;
                        }
                        added.originalValues = {
                            name: added.down('[name="name"]').getValue()
                        };
                    }
                    names.push(items[i].down('[name="name"]').getValue());
                }
            }

            this.getPlugin('addfield').hide();
            ct.suspendLayouts();
            var f = ct.add({
                xtype: 'variablevaluefield',
                currentScope: currentScope,
                defaultScope: currentScope,
                scope: currentScope,
                plugins: {
                    ptype: 'addfield',
                    cls: me.addFieldCls,
                    handler: handler
                }
            });
            f.down('[name="flagRequiredScope"]').store.loadData(allowedScopes);
            f.setFieldValues({
                flagRequiredScope: 'farmrole',
                newValue: true
            });
            f.down('[name="newName"]').validatorNames = names;
            if (currentScope == 'farmrole') {
                f.down('[name="flagRequired"]').disable();
                f.down('[name="flagHidden"]').disable();
            }
            ct.resumeLayouts(true);

            if (added)
                me.fireEvent('addvar', added.getFieldValues());
        };

        f = ct.add({
            xtype: 'variablevaluefield',
            currentScope: currentScope,
            defaultScope: currentScope,
            scope: currentScope,
            plugins: {
                ptype: 'addfield',
                cls: me.addFieldCls,
                handler: handler
            }
        });
        f.down('[name="flagRequiredScope"]').store.loadData(allowedScopes);
        f.setFieldValues({
            newValue: true,
            flagRequiredScope: 'farmrole'
        });
        f.down('[name="newName"]').validatorNames = names;
        if (currentScope == 'farmrole') {
            f.down('[name="flagRequired"]').disable();
            f.down('[name="flagHidden"]').disable();
        }

        ct.resumeLayouts(true);
    },

    items: [{
        xtype: 'filterfield',
        width: 176,
        handler: function(field, value) {
            this.up('variablefield').down('#ct').items.each(function() {
                var f = this.child('[name="name"]');
                if (f.isVisible() && value && (f.getValue().indexOf(value) != -1))
                    f.addCls('scalr-ui-variablefield-mark');
                else
                    f.removeCls('scalr-ui-variablefield-mark');
            });
        }
    }, {
        xtype: 'container',
        itemId: 'labelContainer',
        layout: 'hbox',
        margin: '0 0 8 0',
        items: [{
            xtype: 'label',
            cls: 'x-panel-header-text-default',
            text: 'Name',
            margin: '0 0 0 6',
            width: 150
        },{
            xtype: 'label',
            text: 'Value',
            cls: 'x-panel-header-text-default',
            flex: 1
        }]
    }, {
        xtype: 'displayfield',
        fieldCls: 'x-form-display-field x-panel-header-text-default',
        value: 'Show locked variables',
        itemId: 'showLockedVars',
        hidden: true,
        margin: '0 0 8 0',
        resetLockedVars: function() {
            if (this.rendered) {
                this.setValue('Show locked variables');
                this.imgEl.replaceCls('x-tool-expand', 'x-tool-collapse');
            }
        },
        listeners: {
            render: function() {
                var me = this, value = me.getValue();

                this.el.applyStyles('height: 26px; padding: 0 5px; background-color: #FBFBFC; border-radius: 3px; overflow: hidden; cursor: pointer');
                this.imgEl = this.bodyEl.insertFirst({
                    tag: 'div',
                    cls: 'x-tool-img x-tool-collapse',
                    style: 'left: 5px; top: 5px; position: relative; float: left'
                });
                this.inputEl.applyStyles('margin-left: 24px; padding-top: 0px');

                this.el.on('click', function() {
                    var ct = this.up('variablefield'), els = ct.query('[lockedVar=true]'), flag = this.imgEl.hasCls('x-tool-collapse'), i;
                    ct.suspendLayouts();
                    for (i = 0; i < els.length; i++)
                        els[i][ flag ? 'show' : 'hide']();
                    ct.resumeLayouts(true);
                    if (flag) {
                        this.setValue('Hide locked variables');
                        this.imgEl.replaceCls('x-tool-collapse', 'x-tool-expand');
                    } else {
                        this.setValue('Show locked variables');
                        this.imgEl.replaceCls('x-tool-expand', 'x-tool-collapse');
                    }
                }, this);
            }
        }
    }, {
        xtype: 'container',
        layout: {
            type: 'hbox',
            align: 'top'
        },
        items: [{
            xtype: 'container',
            itemId: 'ct',
            layout: 'anchor',
            flex: 1,
            listeners: {
                afterlayout: function() {
                    var c = this.down('variablevaluefield:last'), width;
                    if (c) {
                        width = 150 + 6 + c.down('[name="value"]').getWidth();
                        if (width != this.currentFieldWidth) {
                            this.currentFieldWidth = width;
                            this.up().prev().setWidth(width);
                        }
                    }
                }
            }
        }, {
            xtype: 'container',
            margin: '0 0 0 8',
            layout: {
                type: 'vbox',
                align: 'right'
            },
            items: [{
                xtype: 'fieldset',
                width: 150,
                collapsible: true,
                collapsed: true,
                cls: 'x-fieldset-separator-none x-fieldset-light',
                title: 'Usage',
                listeners: {
                    expand: function () {
                        this.setWidth(320);
                    },
                    collapse: function () {
                        this.setWidth(150);
                    }
                },
                items: [{
                    xtype: 'displayfield',
                    value: '<span style="font-weight: bold">Scope:</span> highest to lowest'
                }, {
                    xtype: 'displayfield',
                    value: '<div class="scalr-ui-variablefield-scope-env icon"></div> Environment'
                }, {
                    xtype: 'displayfield',
                    value: '<div class="scalr-ui-variablefield-scope-role icon"></div> Role'
                }, {
                    xtype: 'displayfield',
                    value: '<div class="scalr-ui-variablefield-scope-farm icon"></div> Farm'
                }, {
                    xtype: 'displayfield',
                    value: '<div class="scalr-ui-variablefield-scope-farmrole icon"></div> FarmRole'
                }, {
                    xtype: 'component',
                    cls: 'x-fieldset-delimiter'
                }, {
                    xtype: 'displayfield',
                    value: '<span style="font-weight: bold">Types:</span>'
                }, {
                    xtype: 'displayfield',
                    cls: 'scalr-ui-variablefield-flag-required',
                    value: '<div class="x-btn-inner"></div> Shall be set at a lower scope'
                }, {
                    xtype: 'displayfield',
                    cls: 'scalr-ui-variablefield-flag-final',
                    value: '<div class="x-btn-inner"></div> Cannot be changed at a lower scope'
                }, {
                    xtype: 'component',
                    cls: 'x-fieldset-delimiter'
                }, {
                    xtype: 'displayfield',
                    value: '<span style="font-weight: bold">Description:</span>'
                }, {
                    xtype: 'displayfield',
                    value: 'You can access these variables:<br />'+
                        '&bull; As OS environment variables when executing a script<br />'+
                        '&bull; Via CLI command: <i>szradm -q list-global-variables</i><br />'+
                        '&bull; Via the GlobalVariablesList(ServerID) API call<br />'
                }]
            }]
        }]
    }]
});

Ext.define('Scalr.ui.VariableValueField', {
    extend: 'Ext.form.FieldContainer',
    alias: 'widget.variablevaluefield',
    layout: 'hbox',
    hideLabel: true,
    plugins: [],
    cls: 'scalr-ui-variablefield-btn-autohide',
    margin: 0,

    listeners: {
        afterlayout: function() {
            var pl = this.getPlugin('addfield'), width = this.down('[name="newName"]').getWidth() + 6 + this.down('[name="value"]').getWidth();
            if (pl && pl.isVisible()) {
                pl.setWidth(width);
            }
        }
    },

    getFieldValues: function () {
        if (this.down('[name="name"]').getValue()) {
            var values = { current: null };
            if (this.down('[name="name"]').getScope() == this.currentScope) {
                values['current'] = {
                    name: this.down('[name="name"]').getValue(),
                    scope: this.down('[name="name"]').getScope(),
                    value: this.down('[name="value"]').getValue(),
                    flagFinal: this.down('[name="flagFinal"]').isDisabled() ? '' : this.down('[name="flagFinal"]').getValue(),
                    flagRequired: this.down('[name="flagRequired"]').isDisabled() ? '' : this.down('[name="flagRequired"]').getValue(),
                    flagHidden: this.down('[name="flagHidden"]').isDisabled() ? '' : this.down('[name="flagHidden"]').getValue(),
                    format: this.down('[name="format"]').readOnly ? '' : this.down('[name="format"]').getValue(),
                    validator: this.down('[name="validator"]').readOnly ? '' : this.down('[name="validator"]').getValue()
                };
            }

            Ext.applyIf(values, this.originalValues);
            values['flagDelete'] = this.down('[name="flagDelete"]').getValue();
            return values;
        } else {
            return null;
        }
    },

    items: [{
        xtype: 'hidden',
        name: 'newValue',
        isFormField: false,
        listeners: {
            change: function(field, value) {
                var me = this.up('variablevaluefield'), flagNew = value == 'true';
                me.suspendLayouts();
                me.down('[name="newName"]').setVisible(flagNew);
                me.down('[name="newName"]').setDisabled(!flagNew);
                me.down('[name="name"]').setVisible(!flagNew);
                me.down('#delete').setDisabled(flagNew);
                me.down('#configure').setDisabled(flagNew);
                me.down('#reset').setDisabled(flagNew);
                me.resumeLayouts(true);
            }
        }
    }, {
        xtype: 'displayfield',
        name: 'name',
        fieldCls: 'x-form-display-field x-form-display-field-as-label',
        width: 150,
        isFormField: false,
        updateScope: function(value) {
            this.scopeEl.dom.className = '';
            this.scopeEl.addCls('scalr-ui-variablefield-scope-' + value);

            var names = {
                scalr: 'Scalr',
                account: 'Account',
                env: 'Environment',
                role: 'Role',
                farm: 'Farm',
                farmrole: 'FarmRole'
            };
            this.scopeEl.set({ title: names[value] || value });
            this.scope = value;
        },
        getScope: function() {
            return this.scope;
        },
        listeners: {
            render: function() {
                var me = this, value = me.getValue();

                this.el.applyStyles('height: 26px; padding: 0 5px; background-color: #FBFBFC; border-radius: 3px; overflow: hidden');
                this.scopeEl = this.bodyEl.insertFirst({
                    tag: 'div',
                    style: 'left: 5px; top: 8px; height: 10px; width: 10px; position: absolute'
                });
                this.inputEl.applyStyles('margin-left: 24px; padding-top: 0px');
                // try to calculate real width
                this.inputEl.applyStyles('display: inline');
                this.computedWidth = this.inputEl.getWidth() + 24 + 10; // margin-left + padding on parent table
                this.inputEl.applyStyles('display: ');

                this.inputEl.set({ title: (this.getWidth() >= this.computedWidth) ? '' : this.getValue() });
                this.updateScope(this.up('variablevaluefield').scope);
            },
            resize: function(c, width, height) {
                if (this.rendered)
                    this.inputEl.set({ title: (width >= this.computedWidth) ? '' : this.getValue() });
            },
            change: function(el, value) {
                if (this.rendered)
                    this.inputEl.set({ title: value.length > 15 ? value : '' });
            }
        }
    }, {
        xtype: 'textfield',
        name: 'newName',
        fieldCls: 'x-form-field',
        isFormField: false,
        allowChangeable: false,
        allowChangeableMsg: 'Variable name, cannot be changed',
        width: 150,
        validatorNames: [],
        validator: function(value) {
            if (! value)
                return true;
            if (/^[A-Za-z]{1,1}[A-Za-z0-9_]{1,49}$/.test(value)) {
                if (this.validatorNames.indexOf(value) == -1)
                    return true;
                else
                    return 'Such name already defined';
            } else
                return 'Name should contain only alpha and numbers. Length should be from 2 chars to 50.';
        },
        listeners: {
            blur: function() {
                this.prev().setValue(this.getValue());
            },
            specialkey: function(field, e){
                if (e.getKey() == e.ENTER) {
                    this.up('variablevaluefield').getPlugin('addfield').run();
                }
            }
        }
    }, {
        xtype: 'container',
        flex: 1,
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        margin: '0 0 0 6',
        items: [{
            xtype: 'textarea',
            name: 'value',
            isFormField: false,
            enableKeyEvents: true,
            minHeight: 26,
            height: 26,
            flex: 1,
            //regexText: ''
            resizable: {
                handles: 's',
                heightIncrement: 26,
                pinned: true
            },

            mode: null,

            setMode: function(mode, resize) {
                if (mode !== this.mode) {
                    if (mode == 'multi') {
                        this.mode = 'multi';
                        this.toggleWrap('off');
                        this.inputEl.setStyle('overflow', 'auto');
                        if (resize) {
                            this.setHeight(26 * 3);
                        }
                    } else {
                        this.mode = 'single';
                        this.inputEl.setStyle('overflow', 'hidden');
                        this.toggleWrap(null);
                    }
                }
            },

            toggleWrap: function(wrap) {
                if (!wrap) {
                    wrap = this.inputEl.getAttribute('wrap') == 'off' ? null : 'off';
                }
                this.inputEl.set({wrap: wrap});
            },

            listeners: {
                keyup: function(comp, e) {
                    if (e.getKey() === e.ENTER) {
                        this.setMode('multi', true);
                    }
                },

                //todo
                focus: function() {
                    var c = this.up('container'), cmp = this.up('variablevaluefield');
                    if (cmp.down('[name="newValue"]').getValue() == 'true') {
                        cmp.getPlugin('addfield').run();
                    }

                    if (! this.readOnly)
                        c.prev('[name="name"]').updateScope(c.up('variablevaluefield').currentScope);
                },

                blur: function() {
                    var c = this.up('container');
                    if (! this.readOnly) {
                        if (this.isDirty()) {
                            c.prev('[name="name"]').updateScope(c.up('variablevaluefield').currentScope);
                        } else {
                            c.prev('[name="name"]').updateScope(c.up('variablevaluefield').defaultScope);
                        }

                        this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
                    }
                },

                resize: function(comp, width, height) {
                    comp.el.parent().setSize(comp.el.getSize());//fix resize wrapper for flex element
                    comp.setMode(comp.inputEl.getHeight() > 26 ? 'multi' : 'single', false);
                },

                boxready: function(comp) {
                    this.setMode(this.getValue().match(/\n/g) ? 'multi' : 'single', true);
                }
            }
        }, {
            xtype: 'container',
            itemId: 'configureItems',
            layout: 'hbox',
            hidden: true,
            margin: '0 0 8 0',
            items: [{
                xtype: 'textfield',
                emptyText: 'Format',
                tooltipText: 'Format',
                name: 'format',
                flex: 1,
                isFormField: false,
                listeners: {
                    blur: function() {
                        this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
                    }
                }
            }, {
                xtype: 'textfield',
                emptyText: 'Validation pattern',
                tooltipText: 'Validation pattern',
                margin: '0 0 0 5',
                name: 'validator',
                isFormField: false,
                flex: 1,
                validator: function(value) {
                    if (value) {
                        try {
                            var r = new RegExp(value);
                            r.test('test');
                            return true;
                        } catch (e) {
                            return e.message;
                        }
                    } else
                        return true;
                },
                listeners: {
                    blur: function() {
                        if (this.isValid()) {
                            var c = this.up('variablevaluefield').down('[name="value"]'), value = this.getValue();
                            if (value)
                                c.regex = new RegExp(value);
                            else
                                delete c.regex;

                            c.validate();
                            this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
                        }
                    }
                }
            }, {
                xtype: 'combo',
                store: {
                    reader: 'json',
                    fields: [ 'id', 'name' ],
                    data: [[ 'farmrole', 'FarmRole' ]]
                },
                valueField: 'id',
                displayField: 'name',
                queryMode: 'local',
                margin: '0 0 0 5',
                name: 'flagRequiredScope',
                value: 'farmrole',
                editable: false,
                width: 120,
                hidden: true,
                isFormField: false,
                tooltipText: 'Required scope',
                listeners: {
                    change: function(field, value) {
                        this.up('variablevaluefield').down('[name="flagRequired"]').inputValue = value;
                        this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
                    }
                }
            }]
        }]
    }, {
        xtype: 'buttonfield',
        ui: 'flag',
        cls: 'x-btn-flag-final',
        margin: '0 0 0 6',
        name: 'flagFinal',
        tooltip: 'Cannot be changed at a lower scope',
        inputValue: 1,
        enableToggle: true,
        isFormField: false,
        toggleHandler: function(el, state) {
            if (this.up('variablevaluefield').currentScope != 'farmrole') {
                this.next('[name="flagRequired"]')[state ? 'disable' : 'enable']();
            }

            this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
        },
        markInvalid: function(error) {
            if (this.rendered)
                Scalr.message.ErrorTip(error, this.el);
            else
                this.on('afterrender', function() {
                    Scalr.message.ErrorTip(error, this.el);
                }, this, {
                    single: true,
                    delay: 200
                });
        }
    }, {
        xtype: 'buttonfield',
        ui: 'flag',
        cls: 'x-btn-flag-required',
        margin: '0 0 0 6',
        name: 'flagRequired',
        tooltip: 'Shall be set at a lower scope',
        inputValue: 'farmrole',
        enableToggle: true,
        isFormField: false,
        toggleHandler: function(el, state) {
            this.prev('[name="flagFinal"]')[state ? 'disable' : 'enable']();
            this.up('variablevaluefield').down('[name="flagRequiredScope"]')[state ? 'show' : 'hide']();

            if (state)
                this.up('variablevaluefield').down('#configure').toggle(true);

            this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
        },
        markInvalid: function(error) {
            if (this.rendered)
                Scalr.message.ErrorTip(error, this.el);
            else
                this.on('afterrender', function() {
                    Scalr.message.ErrorTip(error, this.el);
                }, this, {
                    single: true,
                    delay: 200
                });
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
        toggleHandler: function(el, state) {
            this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
        }
    }, {
        xtype: 'button',
        ui: 'action',
        itemId: 'reset',
        margin: '0 0 0 8',
        cls: 'x-btn-action-reset',
        handler: function() {
            var c = this.up('variablevaluefield').down('[name="value"]');
            c.reset();
            c.fireEvent('blur');
            this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
        }
    }, {
        xtype: 'button',
        ui: 'action',
        itemId: 'configure',
        margin: '0 0 0 8',
        enableToggle: true,
        cls: 'x-btn-action-configure',
        toggleHandler: function(el, state) {
            var c = this.up('variablevaluefield').down('#configureItems');
            c[state ? 'show' : 'hide' ]();
        }
    }, {
        xtype: 'button',
        ui: 'action',
        itemId: 'delete',
        margin: '0 0 0 8',
        cls: 'x-btn-action-delete',
        handler: function() {
            this.up('variablevaluefield').hide();
            this.next().setValue(1);
            this.up('variablefield').fireEvent('removevar', this.up('variablevaluefield').getFieldValues());
        }
    }, {
        xtype: 'hidden',
        name: 'flagDelete',
        isFormField: false,
        value: ''
    }]
});


Ext.define('Scalr.ui.VariableField2', {
    extend: 'Ext.container.Container',

    mixins: {
        field: 'Ext.form.field.Field'
    },
    alias: document.location.search.indexOf('newgv') !== -1 ? 'widget.variablefield' : 'widget.variablefield2',

    currentScope: 'env',
    addFieldCls: '', // TODO: remove
    encodeParams: true,

    initComponent : function() {
        var me = this;
        me.callParent();
        me.initField();

        me.addEvents('addvar', 'editvar', 'removevar');
    },

    markInvalid: function(errors) {
        var i, ct = this.down('#ct'), fields = this.query('variablevaluefield'), link = {}, name, j, f;

        for (i = 0; i < fields.length; i++) {
            name = fields[i].down('[name="name"]').getValue();
            if (name)
                link[name] = fields[i];
        }

        for (i in errors) {
            if (link[i]) {
                for (j in errors[i]) {
                    f = link[i].down('[name="' + j + '"]');
                    if (f)
                        f.markInvalid(errors[i][j]);
                }
            }
        }
    },

    isValid: function() {
        return true;

        var items = this.down('#ct').query('variablevaluefield'), length = items.length, i, valid = true;
        for (i = 0; i < length; i++) {
            valid = valid && items[i].down('[name="value"]').isValid();
        }

        return valid;
    },

    getValue: function() {
        var store = this.down('grid').getStore(), variables = [];

        store.each(function(record) {
            variables.push({
                name: record.get('name'),
                current: record.get('current'),
                default: record.get('default'),
                locked: record.get('locked'),
                flagDelete: record.get('flagDelete')
            });
        });

        return this.encodeParams ? Ext.encode(variables) : variables;
    },

    setValue: function(value) {
        value = (this.encodeParams ? Ext.decode(value, true) : value) || [];
        var grid = this.down('grid'), me = this, currentScope = this.currentScope, i, f, names = [], isLockedVars = false;

        grid.store.loadData(value);

        /*
        this.down('#showLockedVars').resetLockedVars();
        ct.suspendLayouts();
        ct.removeAll();

        this.suspendEvent('addvar', 'editvar', 'removevar');
        */

        // for flagRequiredScope
        var allowedScopes = [], allowedAllScopes = [], sc = { 'scalr': 'Scalr', 'account': 'Account', 'env': 'Environment', 'role': 'Role', 'farm': 'Farm', 'farmrole': 'FarmRole' }, sca = Ext.Object.getKeys(sc);
        sca = sca.slice(sca.indexOf(currentScope) + 1 + (currentScope == 'role' ? 1 : 0)); // if currentScope == role, exclude farm
        for (i = 0; i < sca.length; i++) {
            allowedScopes.push([ sca[i], sc[sca[i]]]);
        }
        for (i in sc) {
            allowedAllScopes.push([ i, sc[i] ]);
        }

        for (i = 0; i < value.length; i++) {
            var current = { name: value[i]['name'] };
        }

        return;

        for (i = 0; i < value.length; i++) {
            f = ct.add({
                currentScope: currentScope,
                xtype: 'variablevaluefield',
                originalValues: value[i]
            });
            var current = { name: value[i]['name'] };

            if (value[i]['current']) {
                if (value[i]['current']['flagRequired'])
                    current['flagRequiredScope'] = value[i]['current']['flagRequired']; // it should be set at first, because of flagRequired field
                else
                    current['flagRequiredScope'] = 'farmrole';

                Ext.apply(current, value[i]['current']);
                f['scope'] = value[i]['current']['scope'];
            }

            if (value[i]['default']) {
                f['scope'] = f['scope'] || value[i]['default']['scope'];
                f['defaultScope'] = value[i]['default']['scope'];
                // set up-level value
                f.down('[name="value"]').emptyText = value[i]['default']['value'];
            } else {
                f['defaultScope'] = currentScope;
            }

            names.push(current['name']);
            current['newValue'] = false;

            f.down('[name="flagRequiredScope"]').store.loadData((value[i]['locked'] || value[i]['default']) ? allowedAllScopes : allowedScopes);
            f.setFieldValues(current);

            if (value[i]['locked'] || value[i]['default']) {
                // variable has upper value, block flags
                var locked = value[i]['locked'] || {};
                if (locked['flagRequired'])
                    f.down('[name="flagRequiredScope"]').setValue(locked['flagRequired']).setReadOnly(true);

                f.down('[name="flagRequired"]').setValue(locked['flagRequired']).disable();
                f.down('[name="flagFinal"]').setValue(locked['flagFinal']).disable();
                f.down('[name="flagHidden"]').setValue(locked['flagHidden']).disable();

                if (locked['flagFinal'] == 1) {
                    f.down('[name="value"]').setReadOnly(true);
                    f.down('[name="value"]').reset(); // fix issue, when variable was redefined on upper level and lock interface
                    f.down('#reset').disable();
                    f.down('#delete').disable();
                    f.lockedVar = true;
                    isLockedVars = true;
                    f.hide();
                }

                f.down('[name="validator"]').setValue(locked['validator']).setReadOnly(true);
                f.down('[name="validator"]').emptyText = '';
                f.down('[name="format"]').setValue(locked['format']).setReadOnly(true);
                f.down('[name="format"]').emptyText = '';

                if (locked['flagRequired'] || locked['format'] || locked['validator']) {
                    f.down('#configure').toggle(true);
                }

                var valueField = f.down('[name="value"]');
                if (locked['flagRequired'] == currentScope && !valueField.emptyText) {
                    valueField.allowBlank = false;
                    valueField.isValid();
                }

            }

            // not to remove variables from higher scopes
            if (value[i]['default'])
                f.down('#delete').disable();

            if (value[i]['flagDelete'] == 1)
                f.hide();

            if (currentScope == 'farmrole') {
                // or required and hidden for last scope (farmrole)
                f.down('[name="flagRequired"]').disable();
                f.down('[name="flagHidden"]').disable();
            }

            if (f.down('[name="validator"]').getValue()) {
                try {
                    f.down('[name="value"]').regex = new RegExp(f.down('[name="validator"]').getValue());
                    f.down('[name="value"]').isValid();
                } catch (e) {}
            }
        }

        var handler = function() {
            // check, if last new variable was filled
            var items = ct.items.items, names = [], added = null;
            for (var i = 0; i < items.length; i++) {
                if (items[i].xtype == 'variablevaluefield') {
                    if (items[i].down('[name="newValue"]').getValue() == 'true') {
                        added = items[i];
                        var field = added.down('[name="newName"]');
                        if (field.isValid()) {
                            if (! field.getValue()) {
                                field.markInvalid('Name is required');
                                return;
                            }

                            items[i].down('[name="newValue"]').setValue(false);
                        } else {
                            return;
                        }
                        added.originalValues = {
                            name: added.down('[name="name"]').getValue()
                        };
                    }
                    names.push(items[i].down('[name="name"]').getValue());
                }
            }

            this.getPlugin('addfield').hide();
            ct.suspendLayouts();
            var f = ct.add({
                xtype: 'variablevaluefield',
                currentScope: currentScope,
                defaultScope: currentScope,
                scope: currentScope,
                plugins: {
                    ptype: 'addfield',
                    cls: me.addFieldCls,
                    handler: handler
                }
            });
            f.down('[name="flagRequiredScope"]').store.loadData(allowedScopes);
            f.setFieldValues({
                flagRequiredScope: 'farmrole',
                newValue: true
            });
            f.down('[name="newName"]').validatorNames = names;
            if (currentScope == 'farmrole') {
                f.down('[name="flagRequired"]').disable();
                f.down('[name="flagHidden"]').disable();
            }
            ct.resumeLayouts(true);

            if (added)
                me.fireEvent('addvar', added.getFieldValues());
        };

        f = ct.add({
            xtype: 'variablevaluefield',
            currentScope: currentScope,
            defaultScope: currentScope,
            scope: currentScope,
            plugins: {
                ptype: 'addfield',
                cls: me.addFieldCls,
                handler: handler
            }
        });
        f.down('[name="flagRequiredScope"]').store.loadData(allowedScopes);
        f.setFieldValues({
            newValue: true,
            flagRequiredScope: 'farmrole'
        });
        f.down('[name="newName"]').validatorNames = names;
        if (currentScope == 'farmrole') {
            f.down('[name="flagRequired"]').disable();
            f.down('[name="flagHidden"]').disable();
        }

        ct.resumeLayouts(true);
    },

    items: [{
        xtype: 'container',
        layout: 'hbox',
        margin: '0 0 12 0',
        items: [{
            xtype: 'filterfield',
            width: 200,
            handler: function(field, value) {
                var store = this.up().next().child('grid').getStore();

                if (value) {
                    store.addFilter({
                        id: 'searchFilter',
                        anyMatch: true,
                        property: 'name',
                        value: value
                    });
                } else {
                    store.removeFilter('searchFilter');
                }
            }
        }, {
            xtype: 'button',
            margin: '0 0 0 12',
            width: 200,
            enableToggle: true,
            text: 'Show locked variables',
            applyFilter: function(status) {
                var store = this.up().next().child('grid').getStore();
                if (status) {
                    store.removeFilter('finalVariableFilter');
                } else {
                    store.addFilter({
                        id: 'finalVariableFilter',
                        filterFn: function(record) {
                            return !(record.get('default') && record.get('default').flagFinal == 1);
                        }
                    });
                }
            },
            listeners: {
                afterrender: function() {
                    this.applyFilter(false);
                },
                toggle: function(button, status) {
                    this.applyFilter(status);
                }
            }
        }]
    }, {
        xtype: 'container',
        layout: {
            type: 'hbox'
        },
        items: [{
            xtype: 'grid',
            flex: 1,
            cls: 'x-grid-shadow x-grid-no-highlighting x-grid-with-formfields scalr-ui-variablefield2-grid',
            store: {
                fields: [ 'name', 'newValue', 'value', 'current', 'default', 'locked', 'flagDelete' ],
                reader: 'object',
                filters: [{
                    id: 'deletedVariableFilter',
                    property: 'flagDelete',
                    value: ''
                }]
            },
            features: {
                ftype: 'addbutton',
                text: 'Add variable',
                handler: function(view) {
                    var grid = view.up('grid'), r;

                    r = grid.getStore().add({
                        newValue: true,
                        current: { scope: grid.up('variablefield').currentScope }
                    });

                    setTimeout(function() {
                        grid.getPlugin('cellediting').startEdit(r[0], 0);
                    }, 50);
                }
            },
            plugins: [
                Ext.create('Ext.grid.plugin.CellEditing', {
                    pluginId: 'cellediting',
                    clicksToEdit: 1,
                    listeners: {
                        beforeedit: function(plugin, o) {
                            var current = o.record.get('current'), def = o.record.get('default'), locked = o.record.get('locked');

                            if (o.field == 'value') {
                                if (locked['flagFinal'] == 1) {
                                    return false;
                                }

                                var ed = o.column.getEditor(o.record);
                                ed.emptyText = def['value'] || ' ';
                                ed.applyEmptyText();

                                if (current['value']) {
                                    o.record.set('value', current['value']);
                                }
                            }


                            if (o.column.isEditable) {
                                return o.column.isEditable(o.record);
                            }

                            return true;
                        },
                        canceledit: function(editor, o) {
                            if (o.field == 'name') {
                                o.grid.getStore().remove(o.record);
                            }
                        },
                        edit: function(editor, o) {
                            if (o.field == 'name') {
                                o.record.set('newValue', null);
                            }
                            if (o.field == 'value') {
                                var current = o.record.get('current');

                                if (!Ext.isObject(current)) {
                                    current = {};
                                }

                                current['value'] = o.value;
                                current['scope'] = editor.grid.up('variablefield').currentScope;
                                o.record.set('current', current);
                                o.record.commit();

                                var name = o.record.get('name');
                                var extendedProperties = editor.grid.next();

                                if (extendedProperties.isVisible() && name === extendedProperties.down('[name=name]').getValue()) {
                                    extendedProperties.fireEvent('updateform', extendedProperties, o.record);
                                }
                            }
                        }
                    }
                })
            ],
            columns: [{
                header: 'Name',
                dataIndex: 'name',
                width: 150,
                renderer: function (value, meta, record) {
                    var current = record.get('current'),
                        def = record.get('default'),
                        locked = record.get('locked');

                    var scope = current && (current.value || !def) ? current.scope : def.scope;

                    return '<div class="scalr-ui-variablefield2-scopemarker scalr-ui-variablefield2-scopemarker-' +
                        scope + '"></div><span>' + record.get('name') + '</span>';
                },
                editor: {
                    xtype: 'textfield',
                    editable: false,
                    margin: '0 12 0 12',
                    fixWidth: -25,
                    allowBlank: false,
                    allowChangeable: false,
                    allowChangeableMsg: 'Variable name, cannot be changed',

                    validator: function(value) {
                        if (! value)
                            return false;

                        if (/^[A-Za-z]{1,1}[A-Za-z0-9_]{1,49}$/.test(value)) {
                            if (this.validatorNames.indexOf(value) == -1) {
                                return true;
                            } else {
                                return 'Such name already defined';
                            }
                        } else {
                            return 'Name should contain only alpha and numbers. Length should be from 2 chars to 50.';
                        }
                    },
                    listeners: {
                        focus: function() {
                            this.validatorNames = this.up('grid').getStore().collect('name', false, true);
                        }
                    }
                },
                isEditable: function(record) {
                    return !!record.get('newValue');
                }


            }, {
                header: 'Value',
                dataIndex: 'value',
                flex: 1,
                editor: {
                    xtype: 'textfield',
                    margin: '0 12 0 13',
                    fixWidth: -25
                },
                renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                    var current = record.get('current'), def = record.get('default'), locked = record.get('locked');

                    if (locked['flagFinal'] == 1) {
                        return '<span style="color:#999; padding:3px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;">' + def['value'] + '</span>';
                    }

                    return  '<div class="x-form-text" style="background:#fff;padding:3px 12px 3px 13px;text-overflow: ellipsis;overflow:hidden;cursor:text" >'+
                        (current['value'] || '<span style="color:#999">' + (def['value'] || '') + '</span>') +
                        '</div>';
                }

            }, {
                header: 'Flags',
                width: 103,
                sortable: false,
                align: 'center',
                renderer: function(value, meta, record) {
                    var current = record.get('current'),
                        def = record.get('default'),
                        locked = record.get('locked'),
                        output = '<div>',
                        disabled = !Ext.Object.isEmpty(def) || !Ext.Object.isEmpty(locked),
                        flagRequired = current['flagRequired'] || locked['flagRequired'],
                        flagFinal = current['flagFinal'] == 1 || locked['flagFinal'] == 1,
                        flagHidden = current['flagHidden'] == 1 || locked['flagHidden'] == 1;

                    output += '<div class="scalr-ui-variablefield2-flag-final' + (!flagRequired ? (disabled ? ' disabled' : '') + (flagFinal ? ' pressed' : '') : ' disabled') + '" title="Final variable"></div>';
                    output += '<div class="scalr-ui-variablefield2-flag-required' + (!flagFinal ? (disabled ? ' disabled' : '') + (flagRequired ? (' pressed scope-' + flagRequired) : '') : ' disabled') + '" title="Required variable"></div>';
                    output += '<div class="scalr-ui-variablefield2-flag-hidden' + (disabled ? ' disabled' : '') + (flagHidden ? ' pressed' : '') + '" title="Hidden variable"></div>';

                    return output + '</div>';
                }
            }, {
                header: '&nbsp;',
                width: 70,
                sortable: false,
                renderer: function(value, meta, record) {
                    var current = record.get('current'),
                        def = record.get('default'),
                        locked = record.get('locked'),
                        selected = record.get('selected'),
                        output = '<div class="action' + (selected ? 'selected' : '') + '">',
                        disabled = !Ext.Object.isEmpty(def) || !Ext.Object.isEmpty(locked);

                    output += '<div class="scalr-ui-variablefield2-action-ext' + (selected ? ' pressed' : '') + '" title="Extended properties"></div>';
                    output += '<div class="scalr-ui-variablefield2-action-delete' + (disabled ? ' disabled' : '') + '" title="Delete variable" style="margin-left: 12px"></div>';

                    return output + '</div>';
                }
            }],

            extendProperties: function (record) {
                var me = this;

                var extendedProperties = me.next();
                extendedProperties.fireEvent('updateform', extendedProperties, record);

                return me;
            },

            listeners: {
                itemclick: function (view, record, item, index, e) {
                    var me = this,
                        current = record.get('current'),
                        def = record.get('default'),
                        locked = record.get('locked'),
                        disabled = !Ext.Object.isEmpty(def) || !Ext.Object.isEmpty(locked);

                    var flagFinal = e.getTarget('div.scalr-ui-variablefield2-flag-final');

                    if (flagFinal && !disabled && !Ext.get(flagFinal).hasCls('disabled')) {
                        current['flagFinal'] = current['flagFinal'] == '1' ? '' : '1';
                        record.set('current', current);
                        record.commit();

                        me.update();
                        me.extendProperties(record);
                    }

                    if (e.getTarget('div.scalr-ui-variablefield2-flag-hidden') && !disabled) {
                        current['flagHidden'] = current['flagHidden'] == '1' ? '' : '1';
                        record.set('current', current);
                        record.commit();

                        me.extendProperties(record);
                    }

                    var flagRequired = e.getTarget('div.scalr-ui-variablefield2-flag-required');

                    if (flagRequired && !disabled && !Ext.get(flagRequired).hasCls('disabled')) {
                        current['flagRequired'] = current['flagRequired'] ? '' : 'farmrole';
                        record.set('current', current);
                        record.commit();

                        me.update();
                        me.extendProperties(record);
                    }

                    if (e.getTarget('div.scalr-ui-variablefield2-action-ext')) {
                        var selectedRecord = me.getStore().findRecord('selected', true);

                        if (selectedRecord && selectedRecord !== record) {
                            selectedRecord.set('selected', false);
                        }

                        record.set('selected', !record.get('selected'));

                        me.extendProperties(record);
                    }

                    if (e.getTarget('div.scalr-ui-variablefield2-action-delete') && !disabled) {
                        record.set('flagDelete', 1);
                        record.commit();
                    }
                }
            }

        }, {
            xtype: 'container',
            name: 'extendedProperties',
            layout: 'anchor',
            hidden: true,
            hideMode: 'visibility',
            width: 300,
            defaults: {
                anchor: '100%'
            },
            items: [{
                xtype: 'displayfield',
                name: 'name',
                isFormField: false
            }, {
                xtype: 'textarea',
                name: 'value',
                isFormField: false,
                disabled: true,
                listeners: {
                    blur: function (me) {
                        var value = me.getValue();
                        var extendedProperties = me.up();
                        var record = extendedProperties.variable;
                        var current = record.get('current');

                        if (!Ext.isObject(current)) {
                            current = {};
                        }

                        current['value'] = value;
                        current['scope'] = me.up('variablefield').currentScope;

                        record.set('current', current);
                        record.commit();

                        extendedProperties.fireEvent('updateform', extendedProperties, record);
                    }
                }
            }, {
                xtype: 'combo',
                name: 'scope',
                fieldLabel: 'Required scope',
                store: {
                    fields: [ 'id', 'name' ],
                    data: [
                        { id: 'scalr', name: 'Scalr' },
                        { id: 'account', name: 'Account' },
                        { id: 'env', name: 'Environment' },
                        { id: 'role', name: 'Role' },
                        { id: 'farm', name: 'Farm' },
                        { id: 'farmrole', name: 'FarmRole' }
                    ]
                },
                valueField: 'id',
                displayField: 'name',
                queryMode: 'local',
                triggerAction: 'last',
                lastQuery: '',
                editable: false,
                isFormField: false
            }, {
                xtype: 'textfield',
                name: 'format',
                fieldLabel: 'Format',
                isFormField: false
            }, {
                xtype: 'textfield',
                name: 'validator',
                fieldLabel: 'Validation pattern',
                isFormField: false,
                validator: function(value) {
                    if (value) {
                        try {
                            var r = new RegExp(value);
                            r.test('test');
                            return true;
                        } catch (e) {
                            return e.message;
                        }
                    } else
                        return true;
                },
                listeners: {
                    change: function (me, value) {
                        if (me.isValid()) {
                            me.up().down('[name=value]').regex = new RegExp(value);
                        }
                    }
                }
            }],

            setName: function (name, readOnly) {
                var me = this;

                me.down('[name=name]').
                    setDisabled(readOnly).
                    setValue(name);

                return me;
            },

            setValue: function (disabled, currentValue, defaultValue) {
                var me = this;

                var field = me.down('[name=value]');
                field.setDisabled(disabled);

                field.setValue();
                field.emptyText = defaultValue || ' ';
                field.applyEmptyText();

                if (currentValue) {
                    field.setValue(currentValue);

                    return me;
                }



                return me;
            },

            setRequiredScope: function (visible, scope, readOnly) {
                var me = this;

                var currentScope = me.currentScope;
                var field = me.down('[name=scope]');

                if (visible) {
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

                    field.show().setDisabled(readOnly).
                        setValue(readOnly ? scope : 'farmrole');

                    return me;
                }

                field.hide();
                return me;
            },

            setFormat: function (format, readOnly) {
                var me = this;

                me.down('[name=format]').
                    setValue(format).
                    setDisabled(readOnly);

                return me;
            },

            setValidator: function (validator, readOnly) {
                var me = this;

                me.down('[name=validator]').
                    setValue(validator).
                    setDisabled(readOnly);

                return me;
            },

            setVariable: function (record) {
                var me = this;

                me.variable = record;

                var def = record.get('default');
                var current = record.get('current');
                var locked = record.get('locked');
                var readOnly = !Ext.Object.isEmpty(def) || !Ext.Object.isEmpty(locked);

                me.
                    setName(record.get('name'), readOnly).
                    setValue(readOnly ? parseInt(locked.flagFinal) : parseInt(current.flagFinal), current.value, def.value).
                    setRequiredScope(readOnly ? locked.flagRequired : current.flagRequired,
                        locked.scope || (readOnly && !current.value ? def.scope : current.scope), readOnly).
                    setFormat(readOnly ? locked.format : current.format, readOnly).
                    setValidator(readOnly ? locked.validator : current.validator, readOnly);

                return me;
            },

            listeners: {
                afterrender: function (me) {
                    me.currentScope = me.prev().up('variablefield').currentScope;
                },

                updateform: function (me, record) {
                    if (record.get('selected')) {
                        me.setVariable(record).show();
                        return;
                    }

                    me.hide();
                }
            }
        }]
    }]
});

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

	getValue: function() {
		var fields = this.query('variablevaluefield'), variables = [];
		for (var i = 0; i < fields.length; i++) {
			var values = fields[i].getFieldValues();
			Ext.applyIf(values, fields[i].originalValues || {});
			if (values['newValue'] == 'true') {
				if (! values['name'])
					continue;
			}
			delete values['newValue'];
			delete values['newName'];
			if (values['name'])
				variables.push(values);
		}

		return this.encodeParams ? Ext.encode(variables) : variables;
	},

	setValue: function(value) {
		value = (this.encodeParams ? Ext.decode(value, true) : value) || [];
		var ct = this.down('#ct'), me = this, currentScope = this.currentScope, i, f, names = [], isLockedVars = false;

		ct.suspendLayouts();
		ct.removeAll();

		if (value.length < 10)
			this.down('filterfield').hide();
		else
			this.down('filterfield').show();

        this.suspendEvent('addvar', 'editvar', 'removevar');

		for (i = 0; i < value.length; i++) {
			f = ct.add({
				currentScope: currentScope,
				xtype: 'variablevaluefield',
				originalValues: value[i]
			});
            names.push(value[i]['name']);
			value[i]['newValue'] = false;
			value[i]['defaultScope'] = value[i]['defaultScope'] || currentScope;
			f.setFieldValues(value[i]);
			f.down('[name="value"]').emptyText = value[i]['defaultValue'] || '';

			if (value[i]['flagRequiredGlobal'] == 1) {
				if (!f.down('[name="value"]').emptyText && value[i]['scope'] == 'farmrole')
					f.down('[name="value"]').allowBlank = false;
				f.down('[name="value"]').isValid();
				f.down('[name="flagRequired"]').setValue(1);
				f.down('[name="flagRequired"]').disable();
			}

			if (value[i]['flagFinalGlobal'] == 1) {
				f.down('[name="value"]').setReadOnly(true);
				f.down('[name="value"]').setDisabled(true);
				f.down('[name="flagFinal"]').disable();
				f.down('[name="flagFinal"]').setValue(1);
				f.down('[name="flagRequired"]').disable();
                f.down('[name="flagHidden"]').disable();

				if (value[i]['scope'] != currentScope) {
                    f.down('#delete').disable();
                    f.down('#configure').disable();
                    f.down('#reset').disable();
                    f.hide();
                    f.lockedVar = true;
                    isLockedVars = true;
                }
			}

            if (value[i]['flagHiddenGlobal'] == 1) {
                f.down('[name="flagHidden"]').disable();
                f.down('[name="flagHidden"]').setValue(1);
            }

            // not to change required and final for high-scope variables
			if (value[i]['defaultScope'] != currentScope) {
				f.down('[name="flagFinal"]').disable();
				f.down('[name="flagRequired"]').disable();
			} else if (currentScope == 'farmrole') {
                // or required for last scope (farmrole)
                f.down('[name="flagRequired"]').disable();
            }

			// not to remove variables from higher scopes
			if (value[i]['defaultScope'] != currentScope)
				f.down('#delete').disable();

            if (value[i]['flagDelete'] == 1)
				f.hide();

            if (value[i]['validator'] || value[i]['format'])
                f.down('#configure').toggle(true).disable();

            if (value[i]['lockConfigure']) {
                f.down('[name="validator"]').setReadOnly(true);
                f.down('[name="format"]').setReadOnly(true);
            }

            if (value[i]['validator'])
                f.down('[name="value"]').regex = new RegExp(value[i]['validator']);
		}

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
					}
					names.push(items[i].down('[name="name"]').getValue());
				}
			}

			this.getPlugin('addfield').hide();
			ct.suspendLayouts();
			var f = ct.add({
				xtype: 'variablevaluefield',
				currentScope: currentScope,
				plugins: {
					ptype: 'addfield',
                    cls: me.addFieldCls,
					handler: handler
				}
			});
			f.setFieldValues({
				defaultScope: currentScope,
				scope: currentScope,
				newValue: true
			});
			f.down('[name="newName"]').validatorNames = names;
			if (currentScope == 'farmrole') {
				f.down('[name="flagRequired"]').disable();
			}
			ct.resumeLayouts(true);

            if (added)
                me.fireEvent('addvar', added.getFieldValues());
		};

		f = ct.add({
			xtype: 'variablevaluefield',
			currentScope: currentScope,
			plugins: {
				ptype: 'addfield',
                cls: me.addFieldCls,
				handler: handler
			}
		});
		f.setFieldValues({
			defaultScope: currentScope,
			scope: currentScope,
			newValue: true
		});
        f.down('[name="newName"]').validatorNames = names;
		if (currentScope == 'farmrole') {
			f.down('[name="flagFinal"]').disable();
			f.down('[name="flagRequired"]').disable();
		}

        ct.resumeLayouts(true);
	},

	items: [{
        xtype: 'filterfield',
        width: 176,
        handler: function(field, value) {
            this.up('variablefield').down('#ct').items.each(function() {
                var f = this.child('[name="name"]');
                if (f.isVisible() && f.getValue().indexOf(value) == -1)
                    f.addCls('x-form-display-field-mark');
                else
                    f.removeCls('x-form-display-field-mark');
            });
        }
    }, {
        xtype: 'container',
        layout: 'hbox',
        margin: '0 0 8 0',
        items: [{
            xtype: 'label',
            cls: 'x-panel-header-text-default',
            text: 'Name',
            width: 156
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
            var pl = this.getPlugin('addfield'), width = 150 + 6 + this.down('[name="value"]').getWidth();
            if (pl && pl.isVisible()) {
                pl.setWidth(width);
            }
        }
    },

    getFieldValues: function () {
        var values = this.callParent(arguments);
        values['defaultValue'] = this.down('[name="value"]').emptyText;
        return values;
    },

    items: [{
		xtype: 'hidden',
		name: 'newValue',
		submitValue: false,
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
		xtype: 'hidden',
		name: 'scope',
        submitValue: false
	}, {
		xtype: 'hidden',
		name: 'defaultScope',
		submitValue: false
	}, {
		xtype: 'displayfield',
		name: 'name',
		fieldCls: 'x-form-display-field x-form-display-field-as-label',
		width: 150,
        updateScope: function(field, value, prev) {
            this.scopeEl.removeCls('scalr-ui-variablefield-scope-' + prev);
            this.scopeEl.addCls('scalr-ui-variablefield-scope-' + value);

            var names = {
                env: 'Environment',
                role: 'Role',
                farm: 'Farm',
                farmrole: 'FarmRole'
            };
            this.scopeEl.set({ title: names[value] || value });
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
                if (value.length > 15)
                    this.inputEl.set({ title: value });

                var scope = this.prev('[name="scope"]');
                scope.on('change', this.updateScope, this);
                this.updateScope(scope, scope.getValue(), '');
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
		submitValue: false,
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
			submitValue: false,
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

				focus: function() {
					var c = this.up('container'), cmp = this.up('variablevaluefield');
                    if (cmp.down('[name="newValue"]').getValue() == 'true') {
                        cmp.getPlugin('addfield').run();
                    }

					c.prev('[name="scope"]').setValue(c.up('variablevaluefield').currentScope);
				},
				
				blur: function() {
					var c = this.up('container');
					if (this.isDirty()) {
						c.prev('[name="scope"]').setValue(c.up('variablevaluefield').currentScope);
					} else {
						c.prev('[name="scope"]').setValue(c.prev('[name="defaultScope"]').getValue());
					}

                    this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
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
                name: 'format',
                flex: 1,
                submitValue: false
            }, {
                xtype: 'textfield',
                emptyText: 'Validation pattern',
                margin: '0 0 0 5',
                name: 'validator',
                submitValue: false,
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
                        }
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
		submitValue: false,
        toggleHandler: function(el, state) {
            var c = this.next('[name="flagRequired"]');
            if (state && !c.pressed) {
                c.disable();
                c.manualDisabled = true;
            } else if (c.manualDisabled) {
                c.enable();
                c.manualDisabled = false;
            }
            this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
        }
	}, {
		xtype: 'buttonfield',
        ui: 'flag',
		cls: 'x-btn-flag-required',
		margin: '0 0 0 6',
		name: 'flagRequired',
		tooltip: 'Shall be set at a lower scope',
		inputValue: 1,
		enableToggle: true,
		submitValue: false,
        toggleHandler: function(el, state) {
            var c = this.prev('[name="flagFinal"]');
            if (state && !c.pressed) {
                c.disable();
                c.manualDisabled = true;
            } else if (c.manualDisabled) {
                c.enable();
                c.manualDisabled = false;
            }
            this.up('variablefield').fireEvent('editvar', this.up('variablevaluefield').getFieldValues());
        }
    }, {
        xtype: 'buttonfield',
        ui: 'flag',
        cls: 'x-btn-flag-hide',
        margin: '0 0 0 6',
        name: 'flagHidden',
        tooltip: 'Mask value from view at a lower scope',
        inputValue: 1,
        enableToggle: true,
        submitValue: false
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
		submitValue: false,
		value: ''
	}]
});

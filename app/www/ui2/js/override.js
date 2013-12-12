Ext.override(Ext.view.Table, {
    enableTextSelection: true,
    markDirty: false
});

Ext.override(Ext.grid.View, {
    stripeRows: false
});

Ext.override(Ext.form.action.Action, {
    submitEmptyText: false
});

Ext.override(Ext.tip.Tip, {
    shadow: false
});

Ext.override(Ext.panel.Tool, {
    width: 21,
    height: 16
});

Ext.override(Ext.tip.ToolTip, {
    clickable: false,
    onRender: function() {
        var me = this;
        me.callParent(arguments);
        if (me.clickable) {
            me.mon(me.el, 'mouseover', function () {
                me.clearTimer('hide');
                me.clearTimer('dismiss');
            }, me);
            me.mon(me.el, 'mouseout', function () {
                me.clearTimer('show');
                if (me.autoHide !== false) {
                    me.delayHide();
                }
            }, me);
        }
    }
});

// show file name in File Field (4.2)
Ext.override(Ext.form.field.File, {
	onRender: function() {
		var me = this;

		me.callParent(arguments);
		me.anchor = '-30';
		me.browseButtonWrap.addCls('x-field-browsebutton');
	},

	buttonText: '',
	setValue: function(value) {
		Ext.form.field.File.superclass.setValue.call(this, value);
	}
});

// (4.2)
Ext.override(Ext.form.field.Base, {
	allowChangeable: true,
	allowChangeableMsg: 'You can set this value only once',
	validateOnBlur: false,
    governance: false,

	initComponent: function() {
        if (this.governance) {
            this.beforeSubTpl = '<span class="x-icon-governance" data-qtip="' + this.getGovernanceMessage() + '"></span>' + (this.beforeSubTpl || '');
        }

		this.callParent(arguments);

        // submit form on enter on any fields in form
        this.on('specialkey', function(field, e) {
			if (e.getKey() == e.ENTER) {
				var form = field.up('form');
				if (form) {
					var button = form.down('#buttonSubmit');
					if (button) {
						button.handler();
					}
				}
			}
		});

		if (! this.allowChangeable) {
			this.cls += ' x-form-notchangable-field';
		}
	},

	afterRender: function() {
		this.callParent(arguments);

		if (! this.allowChangeable) {
			this.changeableTip = Ext.create('Ext.tip.ToolTip', {
				target: this.inputEl,
				html: this.allowChangeableMsg
			});
		}
	},

	markInvalid: function() {
		this.callParent(arguments);
		if (! this.allowChangeable)
			this.changeableTip.setDisabled(true);
	},

	clearInvalid: function() {
		this.callParent(arguments);
		if (! this.allowChangeable)
			this.changeableTip.setDisabled(false);
	},

    setValueWithGovernance: function(value, limit) {
        var governanceEnabled = limit !== undefined;
        this.setValue(governanceEnabled ? limit.value : value);
        this.setReadOnly(governanceEnabled);
        this[governanceEnabled ? 'addCls' : 'removeCls']('x-field-governance');
    },

    getGovernanceMessage: function(raw) {
        var message = 'The account owner has enforced a specific policy on ';
        if (this.governanceTitle) {
            message += 'the <b>' + this.governanceTitle + '</b>.';
        } else if (this.fieldLabel) {
            message += 'the <b>' + this.fieldLabel.toLowerCase() + '</b> setting.';
        } else {
            message += 'this setting.';
        }
        return raw ? message : Ext.String.htmlEncode(message);
    }
});

Ext.override(Ext.form.field.Checkbox, {
	setReadOnly: function(readOnly) {
		var me = this,
			inputEl = me.inputEl;
		if (inputEl) {
			// Set the button to disabled when readonly
			inputEl.dom.disabled = readOnly || me.disabled;
		}
		me[readOnly ? 'addCls' : 'removeCls'](me.readOnlyCls);
		me.readOnly = readOnly;
	}
});

Ext.override(Ext.form.field.Trigger, {
	updateEditState: function() {
		var me = this;

		me.callOverridden();
		me[me.readOnly ? 'addCls' : 'removeCls'](me.readOnlyCls);
	}
});

// TextArea should always show emptyText via value, not placeholder (scrolling and multi-line will work)
Ext.override(Ext.form.field.TextArea, {
	getSubTplData: function() {
		var me = this,
			fieldStyle = me.getFieldStyle(),
			ret = me.callParent();

		if (me.grow) {
			if (me.preventScrollbars) {
				ret.fieldStyle = (fieldStyle||'') + ';overflow:hidden;height:' + me.growMin + 'px';
			}
		}

		Ext.applyIf(ret, {
			cols: me.cols,
			rows: me.rows
		});

		/** Changed, get from Ext.form.field.Text:getSubTplData */
		var value = me.getRawValue();
		var isEmpty = me.emptyText && value.length < 1, placeholder = '';
		if (isEmpty) {
			value = me.emptyText;
			me.valueContainsPlaceholder = true;
		}

		Ext.apply(ret, {
			placeholder : placeholder,
			value       : value,
			fieldCls    : me.fieldCls + ((isEmpty && (placeholder || value)) ? ' ' + me.emptyCls : '') + (me.allowBlank ? '' :  ' ' + me.requiredCls)
		});
		/** End */

		return ret;
	},

	applyEmptyText: function() {
		var me = this,
			emptyText = me.emptyText,
			isEmpty;

		if (me.rendered && emptyText) {
			isEmpty = me.getRawValue().length < 1 && !me.hasFocus;

			/** Changed */
			if (isEmpty) {
				me.setRawValue(emptyText);
				me.valueContainsPlaceholder = true;
			}
			/** End */

			//all browsers need this because of a styling issue with chrome + placeholders.
			//the text isnt vertically aligned when empty (and using the placeholder)
			if (isEmpty) {
				me.inputEl.addCls(me.emptyCls);
			}

			me.autoSize();
		}
	},

	onFocus: function() {
		this.callParent(arguments);

		// move to beforeFocus
		var me = this,
			inputEl = me.inputEl,
			emptyText = me.emptyText,
			isEmpty;

		//debugger;
		/** Changed */
		if ((emptyText && (!Ext.supports.Placeholder || me.xtype == 'textarea')) && (inputEl.dom.value === me.emptyText && me.valueContainsPlaceholder)) {
			me.setRawValue('');
			isEmpty = true;
			inputEl.removeCls(me.emptyCls);
			me.valueContainsPlaceholder = false;
		} else if (Ext.supports.Placeholder && me.xtype != 'textarea') {
			me.inputEl.removeCls(me.emptyCls);
		}
		if (me.selectOnFocus || isEmpty) {
			inputEl.dom.select();
		}
		/** End */
	}
});

Ext.override(Ext.slider.Single, {
    showValue: false,
	onRender: function() {
		var me = this;
		me.callParent(arguments);
        
        Ext.DomHelper.append(this.thumbs[0].el, '<div class="x-slider-thumb-inner"></div>', true);
        if (me.showValue) {
            this.sliderValue = Ext.DomHelper.append(this.thumbs[0].el, '<div class="x-slider-value">'+this.getValue()+'</div>', true);
            this.on('change', function(comp, value){
                if (this.sliderValue !== undefined) {
                    this.sliderValue.setHTML(value);
                }
            });
        }
	}
});


Ext.override(Ext.form.Panel, {
	initComponent: function() {
		this.callParent(arguments);
		this.relayEvents(this.form, ['beforeloadrecord', 'loadrecord', 'updaterecord' ]);
	},
	loadRecord: function(record) {
		var arg = [];

		for (var i = 0; i < arguments.length; i++)
			arg.push(arguments[i]);
		this.suspendLayouts();
		
		if (this.fireEvent.apply(this, ['beforeloadrecord'].concat(arg)) === false) {
			this.resumeLayouts(true);
			return false;
		}
		var ret = this.getForm().loadRecord(record);
		
		this.fireEvent.apply(this, ['loadrecord'].concat(arg));
		this.resumeLayouts(true);
		
		return ret;
	}
});

// (4.2)
Ext.override(Ext.form.Basic, {
	constructor: function() {
		this.callParent(arguments);
		this.addEvents('beforeloadrecord', 'loadrecord', 'updaterecord');
	},
	initialize: function () {
		this.callParent(arguments);

		//scroll to error fields
		this.on('actionfailed', function (basicForm) {
			basicForm.getFields().each(function (field) {
				if (field.isFieldLabelable && field.getActiveError()) {
					field.el.scrollIntoView(basicForm.owner.body);
					return false;
				}
			});
		});
	},

	updateRecord: function(record) {
		record = record || this._record;
		var ret = this.callParent(arguments);
		this.fireEvent('updaterecord', record);
		return ret;
	}
});

// (4.2) save our additional parameter
Ext.override(Ext.panel.Table, {
	getState: function() {
		var state = this.callParent(arguments);
		state = this.addPropertyToState(state, 'autoRefresh', this.autoRefresh);
		return state;
	}
});

// (4.2)
Ext.override(Ext.view.AbstractView, {
	loadingText: 'Loading data...'
	//disableSelection: true,
	// TODO: apply and check errors (role/edit for example, selected plugin for grid
});

// (4.2)
Ext.override(Ext.view.BoundList, {
	afterRender: function() {
		this.callParent(arguments);

		if (this.minWidth)
			this.el.applyStyles('min-width: ' + this.minWidth + 'px');
	}
})

// (4.2)
Ext.override(Ext.form.field.Text, {
    readOnlyCls: 'x-form-readonly',
    hideInputOnReadOnly: false,
	initComponent: function() {
		var me = this;
		me.callParent(arguments);
        if (me.hideInputOnReadOnly) {
            me.readOnlyCls += ' x-input-readonly';
        }
	}
});

Ext.override(Ext.form.field.ComboBox, {
	matchFieldWidth: false,
	autoSetValue: false,
    clearDataBeforeQuery: false,
    restoreValueOnBlur: false,

	initComponent: function() {
		var me = this;
		me.callParent(arguments);
		if (!me.value && me.autoSetValue && me.store.getCount() > 0) {
			me.setValue(me.store.first().get(me.valueField));
		}
	},

	alignPicker: function() {
		var me = this,
			picker = me.getPicker();

		if (me.isExpanded) {
			if (! me.matchFieldWidth) {
				// set minWidth
				picker.el.applyStyles('min-width: ' + me.bodyEl.getWidth() + 'px');
			}
		}
		this.callParent(arguments);
	},

    doRemoteQuery: function(queryPlan) {
        if (this.queryMode == 'remote' && this.clearDataBeforeQuery) {
            this.store.removeAll();
        }
        this.callParent(arguments);
    },
    
    onLoad: function(store, records, successful) {
        this.callParent(arguments);
        if (!successful && this.queryMode == 'remote') {
            this.collapse();
        }
    },

    /*
     * Combobox set editable=true when setting reaOnly=false, sometimes we need to set these options separately.
     * Using setEditable(false) after setReadOnly(false) doesn't work for unknown reason
     **/
    setReadOnly: function(readOnly, editable) {
        this.callParent(arguments);
        if (editable !== undefined && this.inputEl) {
            this.inputEl.dom.readOnly = !editable;
        }
    },

    assertValue: function() {
        var forceSelection = this.forceSelection;
        if (this.restoreValueOnBlur) {
            forceSelection = true;
        }
        this.callParent(arguments);
        this.forceSelection = forceSelection;
    },

	defaultListConfig: {
        loadMask: false,
		shadow: false // disable shadow in combobox
	},
	shadow: false
});

Ext.override(Ext.form.field.Picker, {
	pickerOffset: [0, 1]
});

Ext.override(Ext.picker.Date, {
	shadow: false
});

Ext.override(Ext.picker.Month, {
	shadow: false,
	initComponent: function() {
		this.callParent(arguments);

		// buttons have extra padding, low it
		if (this.showButtons) {
			this.okBtn.padding = 3;
			this.cancelBtn.padding = 3;
		}
	}
});

Ext.override(Ext.container.Container, {
	setFieldValues: function(values) {
		for (var i in values) {
			var f = this.down('[name="' + i + '"]');
			if (f)
				f.setValue(values[i]);
		}
	},

	getFieldValues: function(noSubmitValue) {
		var fields = this.query('[isFormField]'), values = {};
        noSubmitValue = noSubmitValue || false; // not include submitValue: false

		for (var i = 0, len = fields.length; i < len; i++) {
            if (noSubmitValue && fields[i].submitValue == false)
                continue;
			values[fields[i].getName()] = fields[i].getValue();
		}

		return values;
	},

    isValidFields: function() {
        var fields = this.query('[isFormField]'), isValid = true;
        for (var i = 0, len = fields.length; i < len; i++) {
            isValid = isValid && fields[i].isValid();
        }

        return isValid;
    }
});

// override to save scope, WTF? field doesn't forward =((
Ext.override(Ext.grid.feature.AbstractSummary, {
	getSummary: function(store, type, field, group){
		if (type) {
			if (Ext.isFunction(type)) {
				return store.aggregate(type, null, group, [field]);
			}

			switch (type) {
				case 'count':
					return store.count(group);
				case 'min':
					return store.min(field, group);
				case 'max':
					return store.max(field, group);
				case 'sum':
					return store.sum(field, group);
				case 'average':
					return store.average(field, group);
				default:
					return group ? {} : '';

			}
		}
	}
});

Ext.override(Ext.grid.column.Column, {
	// hide control menu
	menuDisabled: true,

    // extjs doesn't save column parameter, use dataIndex as stateId by default
    getStateId: function () {
        return this.dataIndex || this.stateId || this.headerId;
    },

	// mark sortable columns
	beforeRender: function() {
		this.callParent();
		if (this.sortable)
			this.addCls('x-column-header-sortable');
	}
});

Ext.override(Ext.grid.Panel, {
	enableColumnMove: false
});

Ext.override(Ext.grid.header.Container, {
    applyColumnsState: function(columns) {
        if (!columns || !columns.length) {
            return;
        }

        var me     = this,
            items  = me.items.items,
            count  = items.length,
            i      = 0,
            length = columns.length,
            c, col, columnState, index;

        for (c = 0; c < length; c++) {
            columnState = columns[c];

            for (index = count; index--; ) {
                col = items[index];
                if (col.getStateId && col.getStateId() == columnState.id) {
                    // If a column in the new grid matches up with a saved state...
                    // Ensure that the column is restored to the state order.
                    // i is incremented upon every column match, so all persistent
                    // columns are ordered before any new columns.
                    /*Changed*/
                    //since we don't use columnmove - we don't need code below.(This code places all newly added columns to the very last position)
                    /*if (i !== index) {
                        me.moveHeader(index, i);
                    }*/
                    /*End*/
                    if (col.applyColumnState) {
                        col.applyColumnState(columnState);
                    }
                    ++i;
                    break;
                }
            }
        }
    }
});

// fieldset's title is not legend (simple div)
Ext.override(Ext.form.FieldSet, {
	createLegendCt: function() {
		var me = this,
			items = [],
			legend = {
				xtype: 'container',
				baseCls: me.baseCls + '-header',
                cls: me.headerCls,
				id: me.id + '-legend',
				//autoEl: 'legend',
				items: items,
				ownerCt: me,
				ownerLayout: me.componentLayout
			};

		// Checkbox
		if (me.checkboxToggle) {
			items.push(me.createCheckboxCmp());
		} else if (me.collapsible) {
			// Toggle button
			items.push(me.createToggleCmp());
		}

        if (me.collapsible && me.toggleOnTitleClick && !me.checkboxToggle) {
            legend.listeners = {
                click : {
                    element: 'el',
                    scope : me,
                    fn : function(e, el){
                        if(!Ext.fly(el).hasCls(me.baseCls + '-header-text')) {
                            me.toggle(arguments);
                        }
                    }
                }
            };
        }
        
		// Title
		items.push(me.createTitleCmp());

		return legend;
	},
    
	createToggleCmp: function() {
		var me = this;
		me.addCls('x-fieldset-with-toggle')
		me.toggleCmp = Ext.widget({
			xtype: 'tool',
			type: me.collapsed ? 'collapse' : 'expand',
			handler: me.toggle,
			id: me.id + '-legendToggle',
			scope: me
		});
		return me.toggleCmp;
	},
	setExpanded: function() {
		this.callParent(arguments);

		if (this.toggleCmp) {
			if (this.collapsed)
				this.toggleCmp.setType('collapse');
			else
				this.toggleCmp.setType('expand');
		}
	},
    setTitle: function(title, description) {
        this.callParent([title + (description ? '<span class="x-fieldset-header-description">' + description + '</span>' : '')]);
    }
    
});

Ext.override(Ext.menu.Menu, {
	childMenuOffset: [1, 0],
	menuOffset: [0, 1],
	shadow: false,
	showBy: function(cmp, pos, off) {
		var me = this;

		if (cmp.isMenuItem)
			off = this.childMenuOffset; // menu is showed from menu item
		else if (me.isMenu)
			off = this.menuOffset;

		if (me.floating && cmp) {
			me.show();

			// Align to Component or Element using setPagePosition because normal show
			// methods are container-relative, and we must align to the requested element
			// or Component:
			me.setPagePosition(me.el.getAlignToXY(cmp.el || cmp, pos || me.defaultAlign, off));
			me.setVerticalPosition();
		}
		return me;
	},
	afterLayout: function() {
		this.callParent(arguments);

		var first = null, last = null;

		this.items.each(function (item) {
			item.removeCls('x-menu-item-first');
			item.removeCls('x-menu-item-last');

			if (!first && !item.isHidden())
				first = item;

			if (!item.isHidden())
				last = item;
		});

		if (first)
			first.addCls('x-menu-item-first');

		if (last)
			last.addCls('x-menu-item-last');
	}
});

Ext.override(Ext.grid.plugin.CellEditing, {
	getEditor: function() {
		var editor = this.callParent(arguments);

		if (editor.field.getXType() == 'combobox') {
			editor.field.on('focus', function() {
				this.expand();
			});

			editor.field.on('collapse', function() {
				editor.completeEdit();
			});
		}

		return editor;
	}
});

Ext.override(Ext.data.Model, {
	hasStore: function() {
		return this.stores.length ? true : false;
	}
});

Ext.Error.handle = function(err) {
	var err = new Ext.Error(err);

	Scalr.utils.PostError({
		message: 't1 ' + err.toString(),
		url: document.location.href
	});

	return true;
};

/*temporarily disabled due to 17278-unable-to-add-rule-to-security-group*/
/*Ext.override(Ext.grid.plugin.HeaderResizer, {
	resizeColumnsToFitPanelWidth: function(currentColumn) {
		var headerCt = this.headerCt,
			grid = headerCt.ownerCt || null;

		if (!grid) return;
		
		var columnsWidth = headerCt.getFullWidth(),
			panelWidth = grid.body.getWidth();
			
		if (panelWidth > columnsWidth) {
			var columns = [];
			Ext.Array.each(this.headerCt.getVisibleGridColumns(), function(col){
				if (col.initialConfig.flex && col != currentColumn) {
					columns.push(col);
				}
			});
			if (columns.length) {
				var scrollWidth = grid.getView().el.dom.scrollHeight == grid.getView().el.getHeight() ? 0 : Ext.getScrollbarSize().width,
					deltaWidth = Math.floor((panelWidth - columnsWidth - scrollWidth)/columns.length);
				grid.suspendLayouts();
				for(var i=0, len=columns.length; i<len; i++) {
					var flex = columns[i].flex || null;
					columns[i].setWidth(columns[i].getWidth() + deltaWidth);
					if (flex) {
						columns[i].flex = flex;
						delete columns[i].width;
					}
				}
				grid.resumeLayouts(true);
			}
		}
	},
	
	afterHeaderRender: function() {
		this.callParent(arguments);
	  
		var me = this;
		this.headerCt.ownerCt.on('resize', me.resizeColumnsToFitPanelWidth, me);
		this.headerCt.ownerCt.store.on('refresh', me.resizeColumnsToFitPanelWidth, me);
		
		this.headerCt.ownerCt.on('beforedestroy', function(){
			me.headerCt.ownerCt.un('resize', me.resizeColumnsToFitPanelWidth, me);
			me.headerCt.ownerCt.store.un('refresh', me.resizeColumnsToFitPanelWidth, me);
		}, me);
	},
  
	onEnd: function(e){
		this.callParent(arguments);
		this.resizeColumnsToFitPanelWidth(this.dragHd);
	}
});*/

// (4.2)
Ext.apply(Ext.Loader, {
	loadScripts: function(sc, handler) {
		var scope = {
			queue: Scalr.utils.CloneObject(sc),
			handler: handler
		}, me = this;

		for (var i = 0; i < sc.length; i++) {
			(function(script){
				me.injectScriptElement(script, function() {
					var ind = this.queue.indexOf(script);
					this.queue.splice(ind, 1);
					if (! this.queue.length)
						this.handler();
				}, Ext.emptyFn, scope);
			})(sc[i]);
		}
	}
});

/* (4.2)*/
Ext.override(Ext.button.Button, {
    onMouseDown: function(e) {
        var me = this;

        if (Ext.isIE) {
            // In IE the use of unselectable on the button's elements causes the element
            // to not receive focus, even when it is directly clicked.
            me.getFocusEl().focus();
        }

        if (!me.disabled && e.button === 0) {
            Ext.button.Manager.onButtonMousedown(me, e);
            /*Changed*/
            if (!me.disableMouseDownPressed) {
                me.addClsWithUI(me.pressedCls);
            }
            /*End*/
        }
    }
});

// (4.2)
Ext.override(Ext.button.Split, {
    initComponent: function() {
        this.callParent(arguments);
        this.addCls('x-btn-default-small-split');
    }
});

// (4.2)
Ext.define(null, {
    override: 'Ext.grid.plugin.RowExpander',

    addExpander: function() {
        this.callParent(arguments);
        //they override selectionmodel checkbox position to 1 for unknown reason, we need to fix it
        this.grid.getSelectionModel().injectCheckbox = 'last';
    }
});

// (4.2)
Ext.define(null, {
    override: 'Ext.form.field.Date',
    triggerWidth: 29
});

// (4.2)
Ext.define(null, {
    override: 'Ext.picker.Date',
    disableAnim: true
});

// (4.2)
Ext.define(null, {
    override: 'Ext.picker.Month',
    onRender: function() {
        this.callParent(arguments);
        this.buttonsEl.addCls('x-form-buttongroupfield')
    }
});

// (4.2)
Ext.define(null, {
    override: 'Ext.form.action.Submit',
    buildForm: function() {
        var result = this.callParent(arguments);
        Ext.fly(result.formEl).createChild({
            tag: 'input',
            type: 'hidden',
            name: 'X-Requested-Token',
            value: Scalr.flags.specialToken
        });
        return result;
    }
});

// (4.2)
Ext.define(null, {
    override: 'Ext.grid.plugin.RowExpander',
    getHeaderConfig: function() {
        var config = this.callParent(arguments);
        config['width'] = 38;

        return config;
    },

    // transfer all row classes to parent wrap tr
    addCollapsedCls: {
        before: function(values, out) {
            var me = this.rowExpander;

            if (!me.recordsExpanded[values.record.internalId]) {
                values.itemClasses.push(me.rowCollapsedCls);
            }

            values.itemClasses = Ext.Array.merge(values.itemClasses, values.rowClasses);
        },
        priority: 500
    }
});

Ext.define(null, {
    override: 'Ext.layout.component.Dock',

    beginLayoutCycle: function(ownerContext) {
        var me = this,
            docked = ownerContext.dockedItems,
            len = docked.length,
            owner = me.owner,
            frameBody = owner.frameBody,
            lastHeightModel = me.lastHeightModel,
            i, item, dock;

        /* CHANGED */
        Ext.layout.component.Dock.superclass.beginLayoutCycle.apply(this, arguments);
        /* END */

        if (me.owner.manageHeight) {
            // Reset in case manageHeight gets turned on during lifecycle.
            // See below for why display could be set to non-default value.
            if (me.lastBodyDisplay) {
                owner.body.dom.style.display = me.lastBodyDisplay = '';
            }
        } else {
            // When manageHeight is false, the body stretches the outer el by using wide margins to force it to
            // accommodate the docked items. When overflow is visible (when panel is resizable and has embedded handles),
            // the body must be inline-block so as not to collapse its margins
            if (me.lastBodyDisplay !== 'inline-block') {
                owner.body.dom.style.display = me.lastBodyDisplay = 'inline-block';
            }

            if (lastHeightModel && lastHeightModel.shrinkWrap &&
                !ownerContext.heightModel.shrinkWrap) {
                owner.body.dom.style.marginBottom = '';
            }
        }

        if (ownerContext.widthModel.auto) {
            if (ownerContext.widthModel.shrinkWrap) {
                owner.el.setWidth(null);
            }
            owner.body.setWidth(null);
            if (frameBody) {
                frameBody.setWidth(null);
            }
        }
        if (ownerContext.heightModel.auto) {
            /* CHANGED */
            // TODO: check in 4.2.3
            //owner.body.setHeight(null); Scalr: quote this line
            /* END */
            //owner.el.setHeight(null); Disable this for now
            if (frameBody) {
                frameBody.setHeight(null);
            }
        }

        // Each time we begin (2nd+ would be due to invalidate) we need to publish the
        // known contentWidth/Height if we are collapsed:
        if (ownerContext.collapsedVert) {
            ownerContext.setContentHeight(0);
        } else if (ownerContext.collapsedHorz) {
            ownerContext.setContentWidth(0);
        }

        // dock: 'right' items, when a panel gets narrower get "squished". Moving them to
        // left:0px avoids this!
        for (i = 0; i < len; i++) {
            item = docked[i].target;
            dock = item.dock;

            if (dock == 'right') {
                item.setLocalX(0);
            } else if (dock != 'left') {
                continue;
            }

            // TODO - clear width/height?
        }
    }
});

Ext.define(null, {
    override: 'Ext.grid.RowEditor',

    //disable animation due to unpredictable chrome tab crashing since v30
    reposition: function(animateConfig, fromScrollHandler) {
        var me = this,
            context = me.context,
            row = context && Ext.get(context.row),
            yOffset = 0,
            rowTop,
            localY,
            deltaY,
            afterPosition;

        if (row && Ext.isElement(row.dom)) {

            deltaY = me.syncButtonPosition(me.getScrollDelta());

            if (!me.editingPlugin.grid.rowLines) {
                yOffset = -parseInt(row.first().getStyle('border-bottom-width'), 10);
            }
            rowTop = me.calculateLocalRowTop(row);
            localY = me.calculateEditorTop(rowTop) + yOffset;

            if (!fromScrollHandler) {
                afterPosition = function() {
                    if (deltaY) {
                        me.scrollingViewEl.scrollBy(0, deltaY, /*Changed*/false/*End*/);
                    }
                    me.focusContextCell();
                }
            }

            me.syncEditorClip();console.log(animateConfig)
            /*Changed*/
            /*if (animateConfig) {
                me.animate(Ext.applyIf({
                    to: {
                        top: localY
                    },
                    duration: animateConfig.duration || 125,
                    callback: afterPosition
                }, animateConfig));
            } else {*/
                me.setLocalY(localY);
                if (afterPosition) {
                    afterPosition();
                }
            //}
            /*End*/
        }
    },

});

//disable animation due to unpredictable chrome tab crashing since v30
Ext.define(null, {
    override: 'Ext.layout.container.Accordion',
    animate: false
});

Ext.define(null, {
    override: 'Ext.tip.QuickTip',
    maxWidth: 600
});
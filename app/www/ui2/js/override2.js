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
Ext.override(Ext.form.field.ComboBox, {
    clearDataBeforeQuery: false,
    restoreValueOnBlur: false,
    enableKeyEvents: true,
    autoSearch: true,

    doRemoteQuery: function(queryPlan) {
        if (this.queryMode == 'remote' && this.clearDataBeforeQuery) {
            this.store.removeAll();
        }
        this.callParent(arguments);
    },

    doLocalQuery: function(queryPlan) {
        var me = this,
            queryString = queryPlan.query;


        if (!me.queryFilter) {

            me.queryFilter = new Ext.util.Filter({
                id: me.id + '-query-filter',
                anyMatch: me.anyMatch,
                caseSensitive: me.caseSensitive,
                root: 'data',
                property: me.displayField
            });
            me.store.addFilter(me.queryFilter, false);
        }


        if (queryString || !queryPlan.forceAll) {
            me.queryFilter.disabled = false;
            me.queryFilter.setValue(me.enableRegEx ? new RegExp(queryString) : queryString);
        }


        else {
            me.queryFilter.disabled = true;
        }


        me.store.filter();

        if (me.store.getCount()/** Changed */ || (me.listConfig && me.listConfig.emptyText) /** End */) { //show boundlist empty text if nothing found
            me.expand();
        } else {
            me.collapse();
        }

        me.afterQuery(queryPlan);
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
        /** Changed */
        var forceSelection = this.forceSelection;
        if (this.restoreValueOnBlur) {
            forceSelection = true;
        }
        /** End */

        var me = this,
            value = me.getRawValue(),
            rec, currentValue;

        if (me.forceSelection) {
            if (me.multiSelect) {


                if (value !== me.getDisplayValue()) {
                    me.setValue(me.lastSelection);
                }
            } else {
                /** Changed */
                if (me.selectSingleRecordOnPartialMatch) {
                    rec = me.store.query(me.displayField, value, true);
                    if (rec.length === 1) {
                        rec = rec.first();
                    } else {
                        delete rec;
                    }
                } else {
                    rec = me.findRecordByDisplay(value);
                }
                /** End */
                if (rec) {
                    currentValue = me.value;


                    if (!me.findRecordByValue(currentValue)) {
                        me.select(rec, true);
                    }
                } else {
                    me.setValue(me.lastSelection);
                }
            }
        }
        me.collapse();


        /** Changed */
        this.forceSelection = forceSelection;
        /** End */
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


// 4.2.0
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
    },
    onHeaderResize: function(header, w, suppressFocus) {
        var me = this,
            view = me.view,
            gridSection = me.ownerCt;

        // Do not react to header sizing during initial Panel layout when there is no view content to size.
        if (view && view.body.dom) {
            me.tempLock();
            if (gridSection) {
                gridSection.onHeaderResize(me, header, w);
            }
        }
        /* changed */
        // exclude from statesave when layout just calculate column's width
        if (this.scalrFlagResizeColumn)
            me.fireEvent('columnresize', this, header, w);
        delete this.scalrFlagResizeColumn;
        /* end */
    }
});

Ext.define(null, {
    override: 'Ext.grid.plugin.HeaderResizer',
    doResize: function() {
        // mark when user resizes header in UI
        this.headerCt.scalrFlagResizeColumn = true;
        this.callParent(arguments);
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
        if (this.checkboxCmp) this.checkboxCmp.suspendEvents(false);//fixes collapse, expand, beforecollapse, beforeexpand events double call 4.2.2
		this.callParent(arguments);
        if (this.checkboxCmp) this.checkboxCmp.resumeEvents();

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

            me.syncEditorClip();
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
    override: 'Ext.AbstractComponent',
    getState: function() {
        var me = this,
            state = null,
            sizeModel = me.getSizeModel();

        /* Changed */
        // doesn't save dimensions
        /* End */

        return state;
    }
});

Ext.define(null, {
    override: 'Ext.grid.CellEditor',

    realign: function(autoSize) {
        this.callParent(arguments);

        if (autoSize === true && this.field.fixWidth) {
            this.field.setWidth(this.field.getWidth() + this.field.fixWidth);
        }
    }
});
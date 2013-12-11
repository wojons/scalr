/*
 * Data plugins
 */
Ext.define('Scalr.ui.DataReaderJson', {
	extend: 'Ext.data.reader.Json',
	alias : 'reader.scalr.json',

	type: 'json',
	root: 'data',
	totalProperty: 'total',
	successProperty: 'success'
});

Ext.define('Scalr.ui.DataProxyAjax', {
	extend: 'Ext.data.proxy.Ajax',
	alias: 'proxy.scalr.paging',

	reader: 'scalr.json'
});

Ext.define('Scalr.ui.StoreReaderObject', {
	extend: 'Ext.data.reader.Json',
	alias: 'reader.object',

	readRecords: function (data) {
		var me = this, result = [];

		for (var i in data) {
			if (Ext.isString(data[i]))
				result[result.length] = {id: i, name: data[i]}; // format id => name
			else
				result[result.length] = data[i];
		}

		return me.callParent([result]);
	}
});

Ext.define('Scalr.ui.StoreProxyObject', {
	extend: 'Ext.data.proxy.Memory',
	alias: 'proxy.object',

	reader: 'object',

	/**
	* Reads data from the configured {@link #data} object. Uses the Proxy's {@link #reader}, if present
	* @param {Ext.data.Operation} operation The read Operation
	* @param {Function} callback The callback to call when reading has completed
	* @param {Object} scope The scope to call the callback function in
	*/
	read: function(operation, callback, scope) {
		var me     = this,
			reader = me.getReader();

		////
		if (Ext.isDefined(operation.data))
			me.data = operation.data;
		////

		var result = reader.read(me.data);

		Ext.apply(operation, {
			resultSet: result
		});

		operation.setCompleted();
		operation.setSuccessful();
		Ext.callback(callback, scope || me, [operation]);
	}
});

/*
 * Form plugins
 */
Ext.define('Scalr.ui.ComboAddNewPlugin', {
	extend: 'Ext.AbstractPlugin',
	alias: 'plugin.comboaddnew',
	url: '',
	postUrl: '',
    disabled: false,
    
	init: function(comp) {
		var me = this;

		// to preserve offset for add button
		Ext.override(comp, {
			alignPicker: function() {
                this.callParent();

                var me = this,
                    picker = me.getPicker(),
                    heightAbove = me.getPosition()[1] - Ext.getBody().getScroll().top,
                    heightBelow = Ext.Element.getViewHeight() - heightAbove - me.getHeight(),
                    space = Math.max(heightAbove, heightBelow);

				if (picker.getHeight() > space - (5 + 24)) {
					picker.setHeight(space - (5 + 24)); // have some leeway so we aren't flush against
				}
			}
		});

		comp.on('render', function() {
			var picker = this.getPicker();
			picker.addCls('x-boundlist-hideemptygrid');
			picker.on('render', function() {
                if (!me.disabled) {
                    this.el.addCls('x-boundlist-with-addnew');
                }
				me.el = this.el.createChild({
					tag: 'div',
					cls: 'x-boundlist-addnew',
                    title: 'Add new'
				}).on('click', function() {
					comp.collapse();
					me.handler();
				}).setVisible(!me.disabled);
			});
		});

		Scalr.event.on('update', function(type, element) {
			if (type == me.url) {
				this.store.add(element);
				this.setValue(element[this.valueField]);
                this.fireEvent('addnew', element);
			}
		}, comp);
	},
    
    setDisabled: function(disabled) {
        if (this.el !== undefined) {
            var parent = this.el.parent();
            if (parent) {
                parent[disabled ? 'removeCls' : 'addCls']('x-boundlist-with-addnew');
            }
            this.el.setVisible(!disabled);
        }
        this.disabled = disabled;
    },
    
	handler: function() {
		Scalr.event.fireEvent('redirect', '#' + this.url + this.postUrl);
	}
});

/*
 * Grid plugins
 */
Ext.define('Scalr.ui.GridStorePlugin', {
	extend: 'Ext.AbstractPlugin',
	alias: 'plugin.gridstore',
	loadMask: false,
    highlightNew: false,

	init: function (client) {
        var me = this;
		client.getView().loadMask = this.loadMask;
		client.store.proxy.view = client.getView(); // :(

        if (me.highlightNew) {
            var view = client.getView(), prevFn = view.getRowClass;

            view.getRowClass = function(record) {
                var cls = prevFn ? (prevFn.apply(view, arguments) || '') : '';
                if (record.get('gridHighlightItem'))
                    cls = cls + ' x-grid-row-new';

                return cls;
            };
        }

		client.store.on({
			scope: client,
			beforeload: function (store) {
                if (me.highlightNew && store.gridHightlightNew) {
                    store.gridHightlight = store.data.clone();
                    delete store.gridHightlightNew;
                }

				if (this.getView().rendered)
					this.getView().clearViewEl();
				if (! this.getView().loadMask)
					this.processBox = Scalr.utils.CreateProcessBox({
						type: 'action',
						msg: client.getView().loadingText
					});
			},
			load: function (store) {
                if (store.gridHightlight) {
                    store.each(function(record) {
                        if (! store.gridHightlight.getByKey(record.getId()))
                            record.set('gridHighlightItem', 1);
                    });

                    delete store.gridHightlight;
                }

				if (! this.getView().loadMask)
					this.processBox.destroy();
			}
		});

		client.store.proxy.on({
			exception: function (proxy, response, operation, options) {
                if (client.store.gridHightlight)
                    delete client.store.gridHightlight;

				var message = 'Unable to load data';
				try {
					var result = Ext.decode(response.responseText, true);
					if (result && result.success === false && result.errorMessage)
						message += ' (' + result.errorMessage + ')';
					else
						throw 'Report';
				} catch (e) {
					if (response.status == 200 && Ext.isFunction(response.getAllResponseHeaders) && response.getAllResponseHeaders() && response.responseText) {
						var report = [ "Ext.JSON.decode(): You're trying to decode an invalid JSON String" ];
						report.push(Scalr.utils.VarDump(response.request.headers));
						report.push(Scalr.utils.VarDump(response.request.options));
						report.push(Scalr.utils.VarDump(response.request.options.params));
						report.push(Scalr.utils.VarDump(response.getAllResponseHeaders()));
						report.push(response.status);
						report.push(response.responseText);

						report = report.join("\n\n");

						Scalr.utils.PostError({
							message: 't2 ' + report,
							url: document.location.href
						});
					}
				}
				
				message += '. <a href="#">Refresh</a>';

				proxy.view.update('<div class="x-grid-error">' + message + '</div>');
				proxy.view.el.down('a').on('click', function (e) {
					e.preventDefault();
					client.store.load();
				});
                proxy.view.refreshSize();
			}
		});
	}
});

Ext.define('Scalr.ui.GridSelectionModel', {
	alias: 'selection.selectedmodel',
	extend: 'Ext.selection.CheckboxModel',

	injectCheckbox: 'last',
	highlightArrow: false,
	checkOnly: true,

	bindComponent: function () {
		this.callParent(arguments);
        this.view.on('viewready', function(){
            this.view.on('refresh', function() {
                this.toggleUiHeader(false);
            }, this);
        }, this);
        
        this.on('beforeselect', function(comp, record) {
            return comp.getVisibility(record);
        });
	},

	getHeaderConfig: function() {
		var c = this.callParent();
		c.width = 38;
		c.minWidth = c.width;
		return c;
	},

	// required in all cases
	getVisibility: function (record) {
		return true;
	},

	renderer: function(value, metaData, record, rowIndex, colIndex, store, view) {
		metaData.tdCls = Ext.baseCSSPrefix + 'grid-cell-special';
		metaData.style = 'margin-left: 5px';

		if (this.getVisibility(record))
			return '<div class="' + Ext.baseCSSPrefix + 'grid-row-checker">&#160;</div>';
	},

    updateHeaderState: function() {
        var me = this, selections = [];
        Ext.each(me.store.getRange(), function (record) {
            if (this.getVisibility(record))
                selections.push(record);
        }, this);

        var hdSelectStatus = this.selected.getCount() === selections.length && selections.length > 0;
        this.toggleUiHeader(hdSelectStatus);
    },

	// keyNav
	onKeyEnd: function(e) {
		var me = this,
			last = me.store.getAt(me.store.getCount() - 1);

		if (last) {
			me.setLastFocused(last);
		}
	},

	onKeyHome: function(e) {
		var me = this,
			first = me.store.getAt(0);

		if (first) {
			me.setLastFocused(first);
		}
	},

	onKeyPageUp: function(e) {
		var me = this,
			rowsVisible = me.getRowsVisible(),
			selIdx,
			prevIdx,
			prevRecord;

		if (rowsVisible) {
			selIdx = e.recordIndex;
			prevIdx = selIdx - rowsVisible;
			if (prevIdx < 0) {
				prevIdx = 0;
			}
			prevRecord = me.store.getAt(prevIdx);
			me.setLastFocused(prevRecord);
		}
	},

	onKeyPageDown: function(e) {
		var me = this,
			rowsVisible = me.getRowsVisible(),
			selIdx,
			nextIdx,
			nextRecord;

		if (rowsVisible) {
			selIdx = e.recordIndex;
			nextIdx = selIdx + rowsVisible;
			if (nextIdx >= me.store.getCount()) {
				nextIdx = me.store.getCount() - 1;
			}
			nextRecord = me.store.getAt(nextIdx);
			me.setLastFocused(nextRecord);
		}
	},

	onKeySpace: function(e) {
		var me = this,
			record = me.lastFocused;

		if (record) {
			if (me.isSelected(record)) {
				me.doDeselect(record, false);
                me.setLastFocused(record);
			} else if(me.getVisibility(record)) {
				me.doSelect(record, true);
			}
		}
	},

	onKeyUp: function(e) {
		var me = this,
			idx  = me.store.indexOf(me.lastFocused),
			record;

		if (idx > 0) {
			// needs to be the filtered count as thats what
			// will be visible.
			record = me.store.getAt(idx - 1);
			me.setLastFocused(record);
		}
	},

	onKeyDown: function(e) {
		var me = this,
			idx  = me.store.indexOf(me.lastFocused),
			record;

		// needs to be the filtered count as thats what
		// will be visible.
		if (idx + 1 < me.store.getCount()) {
			record = me.store.getAt(idx + 1);
			me.setLastFocused(record);
		}
	},
	
    onRowClick: function(view, record, item, index, e) {
        //view.el.focus(); todo: what is this?
        var me = this,
            checker = e.getTarget(me.checkSelector),
            mode;

        if (!me.allowRightMouseSelection(e)) {
            return;
        }

        // checkOnly set, but we didn't click on a checker.
        if (me.checkOnly && !checker) {
            me.setLastFocused(record);
            e.lastSelected = record;
           return;
        }
        if (checker) {
			e.preventDefault();//prevent text selection
            mode = me.getSelectionMode();
            // dont change the mode if its single otherwise
            // we would get multiple selection
            if (mode !== 'SINGLE' && !e.shiftKey) {
                me.setSelectionMode('SIMPLE');
            }
            me.selectWithEvent(record, e);
            me.setSelectionMode(mode);
            
            if (!me.preventFocus) {
                me.setLastFocused(record);
            }
        } else {
            me.selectWithEvent(record, e);
        }
    },
	
	refreshLastFocused: function(suppressFocus) {
		var record = this.getLastFocused();
		this.setLastFocused(null, suppressFocus);
		if (record) {
			this.setLastFocused(record, suppressFocus);
		}
	},
	
    selectWithEvent: function(record, e, keepExisting) {
        var me = this;
        if (me.selectionMode === 'MULTI') {
            if (e.ctrlKey && me.isSelected(record)) {
                me.doDeselect(record, false);
            } else if (e.shiftKey && me.lastFocused) {
                if (!me.isSelected(record)) {
                    me.selectRange(me.lastFocused, record, true);
                } else {
                    me.deselectRange(me.lastFocused, record);
                }
            } else if (e.ctrlKey) {
                me.doSelect(record, true, false);
            } else if (me.isSelected(record) && !e.shiftKey && !e.ctrlKey && me.selected.getCount() > 1) {
                me.doSelect(record, keepExisting, false);
            } else {
                me.doSelect(record, false);
            }
        } else {
            me.callParent(arguments);
        }
    }	
	
});

/**
 * Base class from Ext.ux.TabReorderer.
 */
Ext.define('Ext.ux.BoxReorderer', {
    mixins: {
        observable: 'Ext.util.Observable'
    },

    /**
     * @cfg {String} itemSelector
     * A {@link Ext.DomQuery DomQuery} selector which identifies the encapsulating elements of child
     * Components which participate in reordering.
     */
    itemSelector: '.x-box-item',

    /**
     * @cfg {Mixed} animate
     * If truthy, child reordering is animated so that moved boxes slide smoothly into position.
     * If this option is numeric, it is used as the animation duration in milliseconds.
     */
    animate: 100,

    constructor: function() {
        this.addEvents(
            /**
             * @event StartDrag
             * Fires when dragging of a child Component begins.
             * @param {Ext.ux.BoxReorderer} this
             * @param {Ext.container.Container} container The owning Container
             * @param {Ext.Component} dragCmp The Component being dragged
             * @param {Number} idx The start index of the Component being dragged.
             */
             'StartDrag',
            /**
             * @event Drag
             * Fires during dragging of a child Component.
             * @param {Ext.ux.BoxReorderer} this
             * @param {Ext.container.Container} container The owning Container
             * @param {Ext.Component} dragCmp The Component being dragged
             * @param {Number} startIdx The index position from which the Component was initially dragged.
             * @param {Number} idx The current closest index to which the Component would drop.
             */
             'Drag',
            /**
             * @event ChangeIndex
             * Fires when dragging of a child Component causes its drop index to change.
             * @param {Ext.ux.BoxReorderer} this
             * @param {Ext.container.Container} container The owning Container
             * @param {Ext.Component} dragCmp The Component being dragged
             * @param {Number} startIdx The index position from which the Component was initially dragged.
             * @param {Number} idx The current closest index to which the Component would drop.
             */
             'ChangeIndex',
            /**
             * @event Drop
             * Fires when a child Component is dropped at a new index position.
             * @param {Ext.ux.BoxReorderer} this
             * @param {Ext.container.Container} container The owning Container
             * @param {Ext.Component} dragCmp The Component being dropped
             * @param {Number} startIdx The index position from which the Component was initially dragged.
             * @param {Number} idx The index at which the Component is being dropped.
             */
             'Drop'
        );
        this.mixins.observable.constructor.apply(this, arguments);
    },

    init: function(container) {
        var me = this;

        me.container = container;

        // Set our animatePolicy to animate the start position (ie x for HBox, y for VBox)
        me.animatePolicy = {};
        me.animatePolicy[container.getLayout().names.x] = true;



        // Initialize the DD on first layout, when the innerCt has been created.
        me.container.on({
            scope: me,
            boxready: me.afterFirstLayout,
            beforedestroy: me.onContainerDestroy
        });
    },

    /**
     * @private Clear up on Container destroy
     */
    onContainerDestroy: function() {
        var dd = this.dd;
        if (dd) {
            dd.unreg();
            this.dd = null;
        }
    },

    afterFirstLayout: function() {
        var me = this,
            layout = me.container.getLayout(),
            names = layout.names,
            dd;

        // Create a DD instance. Poke the handlers in.
        // TODO: Ext5's DD classes should apply config to themselves.
        // TODO: Ext5's DD classes should not use init internally because it collides with use as a plugin
        // TODO: Ext5's DD classes should be Observable.
        // TODO: When all the above are trus, this plugin should extend the DD class.
        dd = me.dd = Ext.create('Ext.dd.DD', layout.innerCt, me.container.id + '-reorderer');
        Ext.apply(dd, {
            animate: me.animate,
            reorderer: me,
            container: me.container,
            getDragCmp: this.getDragCmp,
            clickValidator: Ext.Function.createInterceptor(dd.clickValidator, me.clickValidator, me, false),
            onMouseDown: me.onMouseDown,
            startDrag: me.startDrag,
            onDrag: me.onDrag,
            endDrag: me.endDrag,
            getNewIndex: me.getNewIndex,
            doSwap: me.doSwap,
            findReorderable: me.findReorderable
        });

        // Decide which dimension we are measuring, and which measurement metric defines
        // the *start* of the box depending upon orientation.
        dd.dim = names.width;
        dd.startAttr = names.beforeX;
        dd.endAttr = names.afterX;
    },

    getDragCmp: function(e) {
        return this.container.getChildByElement(e.getTarget(this.itemSelector, 10));
    },

    // check if the clicked component is reorderable
    clickValidator: function(e) {
        var cmp = this.getDragCmp(e);

        // If cmp is null, this expression MUST be coerced to boolean so that createInterceptor is able to test it against false
        return !!(cmp && cmp.reorderable !== false);
    },

    onMouseDown: function(e) {
        var me = this,
            container = me.container,
            containerBox,
            cmpEl,
            cmpBox;

        // Ascertain which child Component is being mousedowned
        me.dragCmp = me.getDragCmp(e);
        if (me.dragCmp) {
            cmpEl = me.dragCmp.getEl();
            me.startIndex = me.curIndex = container.items.indexOf(me.dragCmp);

            // Start position of dragged Component
            cmpBox = cmpEl.getBox();

            // Last tracked start position
            me.lastPos = cmpBox[this.startAttr];

            // Calculate constraints depending upon orientation
            // Calculate offset from mouse to dragEl position
            containerBox = container.el.getBox();
            if (me.dim === 'width') {
                me.minX = containerBox.left;
                me.maxX = containerBox.right - cmpBox.width;
                me.minY = me.maxY = cmpBox.top;
                me.deltaX = e.getPageX() - cmpBox.left;
            } else {
                me.minY = containerBox.top;
                me.maxY = containerBox.bottom - cmpBox.height;
                me.minX = me.maxX = cmpBox.left;
                me.deltaY = e.getPageY() - cmpBox.top;
            }
            me.constrainY = me.constrainX = true;
        }
    },

    startDrag: function() {
        var me = this,
            dragCmp = me.dragCmp;

        if (dragCmp) {
            // For the entire duration of dragging the *Element*, defeat any positioning and animation of the dragged *Component*
            dragCmp.setPosition = Ext.emptyFn;
            dragCmp.animate = false;

            // Animate the BoxLayout just for the duration of the drag operation.
            if (me.animate) {
                me.container.getLayout().animatePolicy = me.reorderer.animatePolicy;
            }
            // We drag the Component element
            me.dragElId = dragCmp.getEl().id;
            me.reorderer.fireEvent('StartDrag', me, me.container, dragCmp, me.curIndex);
            // Suspend events, and set the disabled flag so that the mousedown and mouseup events
            // that are going to take place do not cause any other UI interaction.
            dragCmp.suspendEvents();
            dragCmp.disabled = true;
            dragCmp.el.setStyle('zIndex', 100);
        } else {
            me.dragElId = null;
        }
    },

    /**
     * @private
     * Find next or previous reorderable component index.
     * @param {Number} newIndex The initial drop index.
     * @return {Number} The index of the reorderable component.
     */
    findReorderable: function(newIndex) {
        var me = this,
            items = me.container.items,
            newItem;

        if (items.getAt(newIndex).reorderable === false) {
            newItem = items.getAt(newIndex);
            if (newIndex > me.startIndex) {
                 while(newItem && newItem.reorderable === false) {
                    newIndex++;
                    newItem = items.getAt(newIndex);
                }
            } else {
                while(newItem && newItem.reorderable === false) {
                    newIndex--;
                    newItem = items.getAt(newIndex);
                }
            }
        }

        newIndex = Math.min(Math.max(newIndex, 0), items.getCount() - 1);

        if (items.getAt(newIndex).reorderable === false) {
            return -1;
        }
        return newIndex;
    },

    /**
     * @private
     * Swap 2 components.
     * @param {Number} newIndex The initial drop index.
     */
    doSwap: function(newIndex) {
        var me = this,
            items = me.container.items,
            container = me.container,
            wasRoot = me.container._isLayoutRoot,
            orig, dest, tmpIndex, temp;

        newIndex = me.findReorderable(newIndex);

        if (newIndex === -1) {
            return;
        }

        me.reorderer.fireEvent('ChangeIndex', me, container, me.dragCmp, me.startIndex, newIndex);
        orig = items.getAt(me.curIndex);
        dest = items.getAt(newIndex);
        items.remove(orig);
        tmpIndex = Math.min(Math.max(newIndex, 0), items.getCount() - 1);
        items.insert(tmpIndex, orig);
        items.remove(dest);
        items.insert(me.curIndex, dest);

        // Make the Box Container the topmost layout participant during the layout.
        container._isLayoutRoot = true;
        container.updateLayout();
        container._isLayoutRoot = wasRoot;
        me.curIndex = newIndex;
    },

    onDrag: function(e) {
        var me = this,
            newIndex;

        newIndex = me.getNewIndex(e.getPoint());
        if ((newIndex !== undefined)) {
            me.reorderer.fireEvent('Drag', me, me.container, me.dragCmp, me.startIndex, me.curIndex);
            me.doSwap(newIndex);
        }

    },

    endDrag: function(e) {
        if (e) {
            e.stopEvent();
        }
        var me = this,
            layout = me.container.getLayout(),
            temp;

        if (me.dragCmp) {
            delete me.dragElId;

            // Reinstate the Component's positioning method after mouseup, and allow the layout system to animate it.
            delete me.dragCmp.setPosition;
            me.dragCmp.animate = true;

            // Ensure the lastBox is correct for the animation system to restore to when it creates the "from" animation frame
            me.dragCmp.lastBox[layout.names.x] = me.dragCmp.getPosition(true)[layout.names.widthIndex];

            // Make the Box Container the topmost layout participant during the layout.
            me.container._isLayoutRoot = true;
            me.container.updateLayout();
            me.container._isLayoutRoot = undefined;

            // Attempt to hook into the afteranimate event of the drag Component to call the cleanup
            temp = Ext.fx.Manager.getFxQueue(me.dragCmp.el.id)[0];
            if (temp) {
                temp.on({
                    afteranimate: me.reorderer.afterBoxReflow,
                    scope: me
                });
            }
            // If not animated, clean up after the mouseup has happened so that we don't click the thing being dragged
            else {
                Ext.Function.defer(me.reorderer.afterBoxReflow, 1, me);
            }

            if (me.animate) {
                delete layout.animatePolicy;
            }
            me.reorderer.fireEvent('drop', me, me.container, me.dragCmp, me.startIndex, me.curIndex);
        }
    },

    /**
     * @private
     * Called after the boxes have been reflowed after the drop.
     * Re-enabled the dragged Component.
     */
    afterBoxReflow: function() {
        var me = this;
        me.dragCmp.el.setStyle('zIndex', '');
        me.dragCmp.disabled = false;
        me.dragCmp.resumeEvents();
    },

    /**
     * @private
     * Calculate drop index based upon the dragEl's position.
     */
    getNewIndex: function(pointerPos) {
        var me = this,
            dragEl = me.getDragEl(),
            dragBox = Ext.fly(dragEl).getBox(),
            targetEl,
            targetBox,
            targetMidpoint,
            i = 0,
            it = me.container.items.items,
            ln = it.length,
            lastPos = me.lastPos;

        me.lastPos = dragBox[me.startAttr];

        for (; i < ln; i++) {
            targetEl = it[i].getEl();

            // Only look for a drop point if this found item is an item according to our selector
            if (targetEl.is(me.reorderer.itemSelector)) {
                targetBox = targetEl.getBox();
                targetMidpoint = targetBox[me.startAttr] + (targetBox[me.dim] >> 1);
                if (i < me.curIndex) {
                    if ((dragBox[me.startAttr] < lastPos) && (dragBox[me.startAttr] < (targetMidpoint - 5))) {
                        return i;
                    }
                } else if (i > me.curIndex) {
                    if ((dragBox[me.startAttr] > lastPos) && (dragBox[me.endAttr] > (targetMidpoint + 5))) {
                        return i;
                    }
                }
            }
        }
    }
});

Ext.define('Scalr.ui.CustomButton', {
	alias: 'widget.custombutton',
	extend: 'Ext.Component',

	hidden: false,
	disabled: false,
	pressed: false,
	enableToggle: false,
	maskOnDisable: false,

	childEls: [ 'btnEl' ],

	overCls: 'x-btn-custom-over',
	pressedCls: 'x-btn-custom-pressed',
	disabledCls: 'x-btn-custom-disabled',
	tooltipType: 'qtip',
	
	initComponent: function() {
		var me = this;
		me.callParent(arguments);

		me.addEvents('click', 'toggle');

		if (Ext.isString(me.toggleGroup)) {
			me.enableToggle = true;
		}

		me.renderData['disabled'] = me.disabled;
	},

	onRender: function () {
		var me = this;

		me.callParent(arguments);

		me.mon(me.btnEl, {
			click: me.onClick,
			scope: me
		});

		if (me.pressed)
			me.addCls(me.pressedCls);

		Ext.ButtonToggleManager.register(me);
		
        if (me.tooltip) {
            me.setTooltip(me.tooltip, true);
        }
		
	},

	onDestroy: function() {
		var me = this;
		if (me.rendered) {
			Ext.ButtonToggleManager.unregister(me);
			me.clearTip();
		}
		me.callParent();
	},

	toggle: function(state, suppressEvent) {
		var me = this;
		state = state === undefined ? !me.pressed : !!state;
		if (state !== me.pressed) {
			if (me.rendered) {
				me[state ? 'addCls': 'removeCls'](me.pressedCls);
			}
			me.pressed = state;
			if (!suppressEvent) {
				me.fireEvent('toggle', me, state);
				Ext.callback(me.toggleHandler, me.scope || me, [me, state]);
			}
		}
		return me;
	},

	onClick: function(e) {
		var me = this;
		if (! me.disabled) {
			me.doToggle();
			me.fireHandler(e);
		}
	},

	fireHandler: function(e){
		var me = this,
		handler = me.handler;

		me.fireEvent('click', me, e);
		if (handler) {
			handler.call(me.scope || me, me, e);
		}
	},

	doToggle: function(){
		var me = this;
		if (me.enableToggle && (me.allowDepress !== false || !me.pressed)) {
			me.toggle();
		}
	},
	
	setTooltip: function(tooltip, initial) {
		var me = this;

		if (me.rendered) {
			if (!initial) {
				me.clearTip();
			}
			if (Ext.isObject(tooltip)) {
				Ext.tip.QuickTipManager.register(Ext.apply({
					target: me.btnEl.id
				},
				tooltip));
				me.tooltip = tooltip;
			} else {
				me.btnEl.dom.setAttribute(me.getTipAttr(), tooltip);
			}
		} else {
			me.tooltip = tooltip;
		}
		return me;
	},
	
	getTipAttr: function(){
		return this.tooltipType == 'qtip' ? 'data-qtip' : 'title';
	},
	
    clearTip: function() {
        if (Ext.isObject(this.tooltip)) {
            Ext.tip.QuickTipManager.unregister(this.btnEl);
        }
    }
});

Ext.define('Scalr.ui.GridPanelTool', {
	extend: 'Ext.panel.Tool',
	alias: 'widget.gridcolumnstool',

	initComponent: function () {
		this.type = 'settings';
		this.callParent();
	},

	gridSettingsForm: function () {
		var columnsFieldset = new Ext.form.FieldSet({
			title: 'Grid columns to show'
		});
		var checkboxGroup = columnsFieldset.add({
			xtype: 'checkboxgroup',
				columns: 2,
				vertical: true
		});
		var grid = this.up('panel'),
			columns = grid.columns;

		for(var i in columns) {
			if(columns[i].hideable) {
				checkboxGroup.add({
					xtype: 'checkbox',
					boxLabel: columns[i].text,
					checked: !columns[i].hidden,
					name: columns[i].text,
					inputValue: 1
				});
			}
		}

		var settingsFieldset = new Ext.form.FieldSet({
            layout: 'hbox',
			items: [{
				xtype: 'button',
                flex: 1,
				text: '<span style="font-weight:normal">Reset columns width</span>',
				handler: function(){
					grid.suspendLayouts();
					for (var i=0, len=columns.length; i<len; i++) {
						if (columns[i].initialConfig.flex) {
							columns[i].flex = columns[i].initialConfig.flex;
							if (columns[i].width) {
								delete columns[i].width;
							}
						} else if (columns[i].initialConfig.width) {
							columns[i].width = columns[i].initialConfig.width;
						}
					}
					grid.resumeLayouts(true);
				}
			}, {
                xtype: 'buttonfield',
                flex: 1,
                margin: '0 0 0 10',
                text: 'Autorefresh',
                name: 'autoRefresh',
                enableToggle: true,
                value: !!this.up('panel').down('scalrpagingtoolbar').autoRefresh
            }]
		});
		return [columnsFieldset, settingsFieldset];
	},

	handler: function () {
		var me = this,
            grid = me.up('panel'),
			columns = grid.columns;
		Scalr.Confirm({
			form: me.gridSettingsForm(),
			success: function (data) {
				for (var i in columns) {
					if(data[columns[i].text])
						columns[i].show();
					if(!data[columns[i].text] && columns[i].hideable)
						columns[i].hide();
				}
				grid.fireEvent('resize');

				if (data['autoRefresh'])
					this.up('panel').down('scalrpagingtoolbar').checkRefreshHandler({'autoRefresh': 60 }, true);
				else
					this.up('panel').down('scalrpagingtoolbar').checkRefreshHandler({'autoRefresh': 0}, true);
			},
			scope: this
		});
	}
});

Ext.define('Scalr.ui.PanelTool', {
	extend: 'Ext.panel.Tool',
	alias: 'widget.favoritetool',

	/** Example:
	 *
	 favorite: {
	    text: 'Farms',
	    href: '#/farms/view'
	 }
	 */
	favorite: {},

	initComponent: function () {
		this.type = 'favorite';
		this.favorite.hrefTarget = '_self';
		var favorites = Scalr.storage.get('system-favorites');

		Ext.each(favorites, function (item) {
			if (item.href == this.favorite['href']) {
				this.type = 'favorite-checked';
				return false;
			}
		}, this);

		this.callParent();
	},

	handler: function () {
		var favorites = Scalr.storage.get('system-favorites') || [], enabled = this.type == 'favorite-checked', href = this.favorite.href, menu = Scalr.application.getDockedComponent('top');

		if (enabled) {
			var index = menu.items.findIndex('href', this.favorite.href);
			menu.remove(menu.items.getAt(index));

			Ext.Array.each(favorites, function(item) {
				if (item.href == href) {
					Ext.Array.remove(favorites, item);
					return false;
				}
			});
			this.setType('favorite');
		} else {
			var index = menu.items.findIndex('xtype', 'tbfill'), fav = Scalr.utils.CloneObject(this.favorite);
			Ext.apply(fav, {
				hrefTarget: '_self',
				reorderable: true,
				cls: 'x-btn-favorite',
				overCls: 'btn-favorite-over',
				pressedCls: 'btn-favorite-pressed'
			});
			menu.insert(index, fav);
			favorites.push(this.favorite);
			this.setType('favorite-checked');
		}

		Scalr.storage.set('system-favorites', favorites);
	}
});

// add link for topmenu (4.2)
Ext.define('Scalr.ui.MenuItemTop', {
	extend: 'Ext.menu.Item',
	alias: 'widget.menuitemtop',
    cls: 'x-menu-item-addlink',
    addLinkHrefDisabled: false,

	onRender: function () {
		var me = this;
		me.callParent();

        if (me.addLinkHrefDisabled)
            return;

        me.itemEl.createChild({
            tag: 'a',
            cls: 'addlink',
            href: me.addLinkHref
        }, me.arrowEl);
	}
});

// DEPRECATED, remove with old farm builder
Ext.define('Scalr.ui.FormComboButton', {
	extend: 'Ext.container.Container',
	alias: 'widget.combobutton',

	cls: 'x-form-combobutton',
	handler: Ext.emptyFn,
	privateHandler: function (btn) {
		this.handler(btn.value, btn);
	},

	initComponent: function () {
		var me = this, groupName = this.getId() + '-button-group';

		for (var i = 0; i < me.items.length; i++) {
			Ext.apply(me.items[i], {
				enableToggle: true,
				toggleGroup: groupName,
				allowDepress: false,
				handler: me.privateHandler,
				scope: me
			});
		}

		me.callParent();
	},

	afterRender: function () {
		this.callParent(arguments);

		this.items.first().addCls('x-btn-default-small-combo-first');
		this.items.last().addCls('x-btn-default-small-combo-last');
	},

	getValue: function () {
		var b = Ext.ButtonToggleManager.getPressed(this.getId() + '-button-group');
		if (b)
			return b.value;
	}
});

Ext.define('Scalr.ui.AddFieldPlugin', {
	extend: 'Ext.AbstractPlugin',
	alias: 'plugin.addfield',
	pluginId: 'addfield',
    width: '100%',
    targetEl: 'tbody',
    cls: '',

	init: function (client) {
		var me = this;
        me.client = client;
		client.on('afterrender', function() {
			me.panelContainer = Ext.DomHelper.insertAfter(client.el.down(me.targetEl), {style: {height: '26px'}}, true);
			var addmask = Ext.DomHelper.append(me.panelContainer,
				'<div style="position: absolute; width: ' + me.width + '; height: 26px;'+(me.padding ? 'padding:' + me.padding + ';' : '')+'">' +
					'<div class="scalr-ui-addfield ' + me.cls + '"></div>' +
					'</div>'
				, true);
			addmask.down('div.scalr-ui-addfield').on('click', me.handler, client);
		}, client);
	},
    run: function() {
        this.handler.call(this.client);
    },
    isVisible: function() {
        return this.panelContainer && this.panelContainer.isVisible();
    },
    setWidth: function(width) {
        this.width = width;
        if (this.panelContainer)
            this.panelContainer.down('div').setWidth(width);
    },
	hide: function() {
		if (this.panelContainer) {
            this.panelContainer.remove();
            this.panelContainer = null;
        }
	}
});

Ext.define('Scalr.ui.FormFieldPassword', {
    extend: 'Ext.form.field.Text',
	alias: 'widget.passwordfield',
	
	inputType:'password',
	allowBlank: false,
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

Ext.define('Scalr.ui.LeftMenu', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.leftmenu',
	
    disabled: false,
	client:null,
	menu: null,
	menuVisible: false,
	
	currentMenuId: null,
	currentItemId: null,
	itemIdPrefix: 'leftmenu-',
	
	itemIconClsPrefix: 'x-icon-leftmenu-',

	getMenus: function(menuId){
		var menus = [];
		switch (menuId) {
			case 'account':
                menus.push({
                    itemId:'environments',
                    href: '#/account/environments',
                    text: 'Environments'
                });
                
                menus.push({
                    itemId:'teams',
                    href: '#/account/teams',
                    text: 'Teams'
                });

                if (Scalr.utils.canManageAcl()) {
                    menus.push({
                        itemId:'users',
                        href: '#/account/users',
                        text: 'Users'
                    });

                    menus.push({
                        itemId:'roles',
                        href: '#/account/roles',
                        text: 'ACL'
                    });
                }
			    break;
		}
		return menus;
	},
	
	init: function(client) {
		var me = this;
		me.client = client;
	},
	
	create: function() {
        this.menu = Ext.create('Ext.container.Container', {
			hidden: true,
			dock: 'left',
            cls: 'x-docked-tabs',
            width: 112,
			defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'above',
                disableMouseDownPressed: true,
                toggleGroup: 'leftmenu',
                hrefTarget: null
			}
		});
		this.client.addDocked(this.menu);
	},
	
	set: function(options) {
		var me = this;
		if (options.menuId !== this.currentMenuId) {
			this.menu.removeAll();
            var iconClsPrefix = me.itemIconClsPrefix + options.menuId;
			this.menu.add(Ext.Array.map(this.getMenus(options.menuId), function(item){
                item.iconCls = iconClsPrefix + ' ' + iconClsPrefix + '-' + item.itemId;
				item.itemId = me.itemIdPrefix + item.itemId;
				return item;
			}));
			this.currentMenuId = options.menuId;
			this.currentItemId = null;
		}
		if (options.itemId !== this.currentItemId) {
			this.menu.getComponent(me.itemIdPrefix + options.itemId).toggle(true);
			this.currentItemId = options.itemId;
		}
	},
	
	show: function(options) {
		if (this.menu === null) {
			this.create();
		}
		this.set(options);
		this.menuVisible = true;
		this.menu.show();
	},
	
	hide: function() {
		this.menuVisible = false;
		this.menu.hide();
	}
	
});

Ext.define('Scalr.ui.GridField', {
    extend: 'Ext.grid.Panel',
    mixins: {
        //labelable: 'Ext.form.Labelable',
        field: 'Ext.form.field.Field'
    },
    alias: 'widget.gridfield',
	
	multiSelect: true,
	selModel: {
		selType: 'selectedmodel',
		injectCheckbox: 'first'
	},
	fieldReady: false,

	allowBlank: true,

	initComponent : function() {
        var me = this;
 		me.callParent();
		this.initField();
        if (!me.name) {
            me.name = me.getInputId();
        }
		
		this.on('viewready', function(){
			this.fieldReady = true;
			this.setRawValue(this.value);
		});
		this.on('selectionchange', function(selModel, selected){
			this.checkChange();
		});
		this.getStore().on('refresh', function(){
			me.setRawValue(me.value);
		});
	},
  
	setValue: function(value) {
		this.setRawValue(value);
		return this.mixins.field.setValue.call(this, value);
	},

	setRawValue: function(value) {
		if (this.fieldReady) {
			var store = this.getStore(),
				records = [];

			value = value || [];
			for (var i=0, len=value.length; i<len; i++) {
				var record = store.getById(value[i]);
				if (record) {
					records.push(record);
				}
			}
			this.getSelectionModel().select(records, false, true);
		}
	},

    getInputId: function() {
        return this.inputId || (this.inputId = this.id + '-inputEl');
    },
	
    getRawValue: function() {
		var ids = [];
		this.getSelectionModel().selected.each(function(record){
			ids.push(record.get('id'));
		});
		return ids;
    },

	getValue: function() {
		return this.getRawValue();
	},

    getActiveError : function() {
        return this.activeError || '';
    },
	
    getSubmitData: function() {
        var me = this,
            data = null;
        if (!me.disabled && me.submitValue) {
            data = {};
            data[me.getName()] = Ext.encode(me.getValue());
        }
        return data;
    }
	
});

Ext.define('Scalr.ui.ChildStore', {
	extend: 'Ext.data.Store',
	parentStore: null,
    suspendSelfUpdate: 0,
    suspendParentUpdate: 0,
	constructor: function(){
		var me = this;
		if (arguments[0].parentStore) {
			arguments[0].model = arguments[0].parentStore.getProxy().getModel().modelName;
		}
		me.callParent(arguments);
		if (me.parentStore) {
			this.loadRecords(this.parentStore.getRange());
			
			this.parentStore.on({
				refresh: function(){
                    if (me.suspendSelfUpdate === 0) {
                        me.suspendParentUpdate++;
                        me.loadRecords(me.parentStore.getRange());
                        me.suspendParentUpdate--;
                    }
				},
				remove: function(store, record){
					if (me.suspendSelfUpdate === 0) {
						me.suspendParentUpdate++;
                        me.remove(record);
                        me.suspendParentUpdate--;
					}
				},
				add: function(store, records, index){
					if (me.suspendSelfUpdate === 0) {
						me.suspendParentUpdate++;
						me.insert(index, records);
						me.suspendParentUpdate--;
					}
				}
			});
			
			this.on({
				remove: function(store, record){
					if (me.suspendParentUpdate === 0) {
                        me.suspendSelfUpdate++;
                        me.parentStore.remove(record);
                        me.suspendSelfUpdate--;
                    }
				},
				add: function(store, records, index){
					if (me.suspendParentUpdate === 0) {
						me.suspendSelfUpdate++;
						me.parentStore.insert(index, records);
						me.suspendSelfUpdate--;
					}
				}
			});
		}
	}
});	

Ext.define('Scalr.ui.FormPicker', {
	extend:'Ext.form.field.Picker',
	alias: 'widget.formpicker',
	
	onBoxReady: function() {
		this.callParent(arguments);
		this.on({
			expand: function() {
				//this.parseSearchString();
			}
		});
	},
	
	createPicker: function() {
		var me = this,
			formDefaults = {
				style: 'background:#F0F1F4;border-radius:4px;box-shadow: 0 1px 3px #7B8BA1;margin-top:1px',
				fieldDefaults: {
					anchor: '100%'
				},
				focusOnToFront: false,
				padding: 12,
				pickerField: me,
				floating: true,
				hidden: true,
				ownerCt: this.ownerCt
			};
			
		if (!this.form.dockedItems) {
			this.form.dockedItems = {
				xtype: 'container',
				layout: {
					type: 'hbox',
					pack: 'center'
				},
				dock: 'bottom',
				items: [{
					xtype: 'button',
					text: 'Search',
					handler: function() {me.focus();
						me.collapse();
						//me.fireEvent('search');
					}
				}]
			}
		}
		if (this.form.items) {
			this.form.items.unshift({
				xtype: 'textfield',
				name: 'keywords',
				fieldLabel: 'Has words',
				labelAlign: 'top'
			});
		}
		var form = Ext.create('Ext.form.Panel', Ext.apply(formDefaults, this.form));
		form.getForm().getFields().each(function(){
			if (this.xtype == 'combo') {
				this.on('expand', function(){
					this.picker.el.on('mousedown', function(e){
						me.keepVisible = true;
					});
				}, this, {single: true})
			}
		})
		return form;
	},
	
	collapseIf: function(e) {
		var me = this;
		if (!me.keepVisible && !me.isDestroyed && !e.within(me.bodyEl, false, true) && !e.within(me.picker.el, false, true) && !me.isEventWithinPickerLoadMask(e)) {
			me.collapse();
		}
		me.keepVisible = false;
	}
	
});

Ext.define('Scalr.ui.data.View.DynEmptyText', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.dynemptytext',
	
    disabled: false,
	client:null,
	
	onAddItemClick: Ext.emptyFn,
	itemsTotal: null,
	emptyText: 'No items were found to match your search.<p>Try modifying your search criteria or <a class="add-link" href="#">adding a new item</a></p>',
	emptyTextNoItems: null,
    showArrow: true,
    arrowCls: 'x-grid-empty-arrow',
    forceRefresh: false,
	
	init: function(client) {
		var me = this;
		me.client = client;
		client.on({
			containerclick: {
				fn: me.onContainerClick,
				scope: me
			},
			afterrender: function() {
				client.store.on({
					refresh: {
						fn: me.updateEmptyText,
						scope: me
					},
					add: {
						fn: me.updateEmptyText,
						scope: me
					},
					remove: {
						fn: me.updateEmptyText,
						scope: me
					}
				});
				me.updateEmptyText();
			},
			beforedestroy: function() {
				this.un('containerclick', me.onContainerClick, me);
				this.store.un('refresh', me.updateEmptyText, me);
				this.store.un('add', me.updateEmptyText, me);
				this.store.un('remove', me.updateEmptyText, me);
			}
		});
	},
	
	onContainerClick: function(comp, e){
		var el = comp.el.query('a.add-link');
		if (el.length) {
            for (var i=0, len=el.length; i<len; i++) {
                if (e.within(el[i])) {
                    this.onAddItemClick(el[i]);
                    break;
                }
            }
			e.preventDefault();
		}
	},
	
	updateEmptyText: function() {
		var client = this.client,
			itemsTotal = client.store.snapshot ?  client.store.snapshot.length : client.store.data.length;

		if (this.forceRefresh || itemsTotal !== this.itemsTotal) {
			var text = this.emptyText;
			if (itemsTotal < 1) {
                if (this.showArrow) {
                    text = '<div class="x-grid-empty-inner ' + this.arrowCls + '"><div class="x-grid-empty-text">' + (this.emptyTextNoItems || this.emptyText) + '</div></div>';
                } else {
                    text = '<div class="x-grid-empty-inner"><div class="x-grid-empty-text">' + (this.emptyTextNoItems || this.emptyText) + '</div></div>';
                }
			} else {
				text = '<div class="x-grid-empty-inner">' + text + '</div>';
			}
			client.emptyText = '<div class="' + Ext.baseCSSPrefix + 'grid-empty">' + text + '</div>';
			var emptyDiv = client.el.query('.' + Ext.baseCSSPrefix + 'grid-empty');
			if (emptyDiv.length) {
				Ext.fly(emptyDiv[0]).setHTML(text);
                client.refreshSize();
			}
			this.itemsTotal = itemsTotal;
            this.forceRefresh = false;
		}
	},
    
    setEmptyText: function(text, alt) {
        this['emptyText' + (alt ? 'NoItems' : '')] = text;
        this.forceRefresh = true;
    }
});

Ext.define('Ext.ux.form.ToolFieldSet', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.toolfieldset',
    tools: [],

    createLegendCt: function () {
		var me = this,
			legend = me.callParent(arguments);

		if (Ext.isArray(me.tools)) {
            for(var i = 0; i < me.tools.length; i++) {
                legend.items.push(me.createToolCmp(me.tools[i]));
            }
        }
		
		return legend;
    },

    createToolCmp: function(toolCfg) {
        var me = this;
        Ext.apply(toolCfg, {
            xtype:  'tool',
			cls: 'x-tool-extra',
            width:  15,
            height: 15,
            id:     me.id + '-tool-' + toolCfg.type,
            scope:  me
        });
        return Ext.widget(toolCfg);
    }

});

Ext.define('Scalr.CachedRequest', {
    
    defaultTtl: 3600,//seconds
    
    defaultProcessBox: {
        type: 'action',
        msg: 'Loading ...'
    },
    
    constructor: function() {
        this.cache = {};
        this.queue = {};
    },
    
    getCacheId: function(config) {
        var cacheId = [],
            paramNames;
        if (config) {
            cacheId.push(config.url);
            if (Ext.isObject(config.params)) {
                paramNames = Ext.Array.sort(Ext.Object.getKeys(config.params));
                Ext.Array.each(paramNames, function(value){
                    cacheId.push(config.params[value]);
                });
            }
        }
        return cacheId.join('.');
    },
    
    clearCache: function() {
        delete this.queue;
        delete this.cache;
        this.cache = {};
        this.queue = {};
    },
    
    removeCache: function(params) {
        var cacheId = this.getCacheId(params);
        delete this.cache[cacheId];
    },
    
    load: function(params, cb, scope, ttl, processBox) {
        var me = this,
            cacheId = me.getCacheId(params);
        ttl = ttl === undefined ? me.defaultTtl : ttl;
        
        if (me.queue[cacheId] !== undefined) {
            me.queue[cacheId].callbacks.push({fn: cb, scope: scope || me});
        } else if (me.cache[cacheId] !== undefined && !me.isExpired(me.cache[cacheId], ttl)) {
            if (cb !== undefined) cb.call(scope || me, me.cache[cacheId].data, 'exists', cacheId);
        } else {
            delete me.cache[cacheId];
            me.queue[cacheId] = {
                callbacks: [{fn: cb, scope: scope || me}]
            };
            Scalr.Request({
                processBox: processBox!== false ? processBox || me.defaultProcessBox : undefined,
                url:  params.url,
                params: params.params || {},
                scope: me,
                success: function (result, response) {
                    if (me.queue[cacheId] !== undefined) {
                        var callbacks = me.queue[cacheId].callbacks;
                        me.cache[cacheId] = {
                            data: result.data !== undefined ? result.data : result,
                            time: me.getTime()
                        };
                        delete me.queue[cacheId];
                        Ext.Array.each(callbacks, function(callback){
                            if (callback.fn !== undefined && !callback.scope.isDestroyed) callback.fn.call(callback.scope, me.cache[cacheId].data, 'success', cacheId, response);
                        });
                    }
                },
                failure: function (result, response) {
                    if (me.queue[cacheId] !== undefined) {
                        var callbacks = me.queue[cacheId].callbacks;
                        delete me.queue[cacheId];
                        Ext.Array.each(callbacks, function(callback){
                            if (callback.fn !== undefined && !callback.scope.isDestroyed) callback.fn.call(callback.scope, null, false, cacheId, response);
                        });
                    }
                }
            });
        }
        return cacheId;
    },
    
    get: function(params) {
        var cacheId = Ext.isString(params) ? params : this.getCacheId(params);
        return this.cache[cacheId] || undefined;
    },
    
    getTime: function() {
        return Math.floor(new Date().getTime() / 1000);
    },
    
    isExpired: function(data, ttl) {
        return data.expired ? true : (ttl !== 0 ? this.getTime() - data.time > ttl : false);
    },
    
    setExpired: function(params) {
        var cacheId = Ext.isString(params) ? params : this.getCacheId(params);
        if (this.cache[cacheId] !== undefined) {
            this.cache[cacheId].expired = true;
        }
    },
    
    isLoaded: function(params) {
        var cacheId = Ext.isString(params) ? params : this.getCacheId(params);
        return this.cache[cacheId] !== undefined;
    },

    onDestroy: function(){
        this.clearCache();
    }
    
});

Ext.define('Scalr.ui.NotAGridView', {
	extend: 'Ext.container.Container',
	alias : 'widget.notagridview',
    
    layout: {
        type: 'vbox',
        align: 'stretch'
    },
    
    selectable: false,
    
    constructor: function(config) {
        config.cls += ' x-grid-shadow x-not-a-grid' + (config.selectable ? ' x-not-a-grid-selectable' : '');
        this.createItems(config);
        this.callParent(arguments);
    },
    
    initComponent: function(){
        var me = this;
        me.callParent(arguments);
        me.down('#items').on({
            add: function(c, comp) {
                me.updateItemsCls();
                if (me.selectable) {
                    comp.on({
                        click: {
                            fn: function(){
                                me.toggleSelect(this);
                            },
                            element: 'el',
                            scope: comp
                        }
                    });
                }
            },
            remove: function() {
                me.updateItemsCls();
            }
        });
    },
    
    createItems: function(config){
        this.items = [{
            xtype: 'container',
            itemId: 'header',
            layout: 'hbox',
            cls: 'x-not-a-grid-header x-grid-header-ct',
            items: Ext.Array.map(config.columns, function(column, index){
                return column.header !== false ? 
                Ext.apply({
                    xtype: 'component',
                    html: '<div class="x-column-header-inner"><span class="x-column-header-text">' + column.title + '</span></div>',
                    cls: 'x-column-header' + (index === 0 ? ' x-column-header-first' : '')
                }, column.header) : undefined;
            })
        },{
            xtype: 'container',
            cls: 'x-not-a-grid-body-fit',
            flex: 1,
            layout: 'absolute',
            items: [{
                xtype: 'container',
                itemId: 'items',
                maskOnDisable: false,
                cls: 'x-not-a-grid-view x-not-a-grid-view-fit'
            }]
        }];
        if (config.title !== undefined) {
            this.items.unshift({
                xtype: 'component',
                html: config.title,
                cls: 'x-fieldset-subheader ' + config.headerCls
            });
        }
    },
    
    getItems: function(){
        return this.down('#items').items;
    },

    removeItem: function(item){
        return this.down('#items').remove(item);
    },

    removeItems: function(){
        return this.down('#items').removeAll();
    },

    addItems: function(items, append) {
        var me = this,
            c =  me.down('#items');
        c.suspendLayouts();
        if (append === false) {
            this.toggleSelect();
            c.removeAll();
        }
       c.add(Ext.Array.map(items, function(item){
            return item !== undefined ? {
                xtype: 'container',
                layout: 'column',
                cls: 'x-item',
                itemData: item.itemData,
                items: Ext.Array.map(me.columns, function(column) {
                    var c = Ext.clone(column.control);
                    if (c !== undefined) {
                        c['name'] = column.name;
                        c[c.xtype === 'radio' ? 'checked' : 'value'] = item.itemData.settings[column.name] || (Ext.isFunction(column.defaultValue) ? column.defaultValue(item.itemData) : column.defaultValue);
                        if (column.extendInitialConfig) {
                            column.extendInitialConfig(c, item.itemData);
                        }
                    }
                    return c;
                })
            } : undefined;
        }));
        c.resumeLayouts(true);
    },
    
    toggleSelect: function(item) {
        if (item === this.selectedItem) return;
        
        if (this.selectedItem !== undefined) {
            this.selectedItem.removeCls('x-item-selected');
            this.fireEvent('selectionchange', this.selectedItem, false);
            delete this.selectedItem;
        }
        if (item !== undefined) {
            this.selectedItem = item;
            this.selectedItem.addCls('x-item-selected');
            this.fireEvent('selectionchange', item, true);
        }

    },
    
    updateItemsCls: function(){
        this.down('#items').items.each(function(item, index){
            item[index % 2 ? 'addCls' : 'removeCls']('x-item-alt');
        });
    },
    
    setDisabled: function(disabled) {
        var ct = this.down('#items');
        ct.setOverflowXY('hidden', disabled ? 'hidden' : 'auto');
        ct.setDisabled(disabled);
    }
});


//4.2 plugins
Ext.define('Scalr.CachedRequestManager', {
    singleton: true,
    list: {},

    create: function(id, doNotSubscribe) {
        var me = this;
        id = id || 'global';
        if (me.list[id] === undefined) {
            me.list[id] = {
                instance: Ext.create('Scalr.CachedRequest'),
                subscribers: doNotSubscribe ? 0 : 1
            };
        } else if (!doNotSubscribe) {
            me.list[id].subscribers++;
        }
        return me.list[id].instance;
    },

    remove: function(id) {
        var me = this,
            item = me.list[id];
        if (item !== undefined) {
            item.subscribers--;
            if (item.subscribers <= 0) {
                item.instance.destroy();
                delete me.list[id];
            }
        }
    },

    get: function(id) {
        return this.create(id, true);
    }
});

Ext.define('Scalr.ui.LocalCachedRequest', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.localcachedrequest',

    crscope: null,

	init: function(client) {
        Scalr.CachedRequestManager.create(this.crscope);
	},

    destroy: function(){
        Scalr.CachedRequestManager.remove(this.crscope);
    }
});

Ext.define('Scalr.ui.StoreProxyCachedRequest', {
    extend: 'Ext.data.proxy.Proxy',
    alias: 'proxy.cachedrequest',

    crscope: null,
    url: null,

    constructor: function(config) {
        this.params = {};
        this.callParent([config]);
        this.setReader(this.reader);
        //backwards compatibility, will be deprecated in 5.0
        //this.nocache = true;
    },

    updateOperation: function(operation, callback, scope) {
        var i = 0,
            recs = operation.getRecords(),
            len = recs.length;

        for (i; i < len; i++) {
            recs[i].commit();
        }
        operation.setCompleted();
        operation.setSuccessful();

        Ext.callback(callback, scope || this, [operation]);
    },

    create: function() {
        this.updateOperation.apply(this, arguments);
    },

    update: function() {
        this.updateOperation.apply(this, arguments);
    },

    destroy: function() {
        this.updateOperation.apply(this, arguments);
    },

    read: function(operation, callback, scope) {
        var me = this;
        if (me.data) {
            operation.resultSet = me.getReader().read(me.data);

            operation.setCompleted();
            operation.setSuccessful();
            Ext.callback(callback, scope || me, [operation]);
        } else {
            Scalr.CachedRequestManager.get(me.crscope).load(
                {
                    url: me.url,
                    params: me.params
                },
                function(data, status, cacheId, response) {
                    var filterFn,
                        testRe,
                        queryString = operation.params ? operation.params.query : null;

                    if (status) {
                        operation.resultSet = me.getReader().read(me.root !== undefined ? data[me.root] : data);
                        if (operation.resultSet.success) {
                            if (me.filterFn) {
                                operation.resultSet.records = Ext.Array.filter(operation.resultSet.records, me.filterFn, me.filterFnScope || me);
                            }
                            if (queryString) {
                                if (me.filterFields !== undefined) {
                                    testRe = new RegExp(queryString, 'i');
                                    filterFn = function(record){
                                        var res = false;
                                        Ext.Array.each(me.filterFields, function(field){
                                            return !(res = testRe.test(record.get(field)));
                                        });
                                        return res;
                                    }
                                } else if (me.filterFn !== undefined) {
                                    filterFn = function(record){
                                        return me.filterFn(queryString, record);
                                    };
                                }

                                if (filterFn !== undefined) {
                                    operation.resultSet.records = Ext.Array.filter(operation.resultSet.records, filterFn);
                                }
                            }
                            if (me.prependData !== undefined) {
                                Ext.Array.insert(operation.resultSet.records, 0, me.getReader().read(me.prependData).records);
                            }
                        }
                        if (operation.resultSet.success) {
                            operation.commitRecords(operation.resultSet.records);
                            operation.setCompleted();
                            operation.setSuccessful();
                        } else {
                            operation.setException(operation.resultSet.message);
                            me.fireEvent('exception', me, response, operation);
                        }
                    } else {
                        operation.setException(operation, null);
                        me.fireEvent('exception', this, response, operation);
                    }
                    Ext.callback(callback, scope || me, [operation]);
                },
                me,
                me.ttl !== undefined ? me.ttl : 0,
                me.processBox
            );
        }
    },

    updateSettings: function(settings){
        Ext.apply(this, settings);
    }

});

Ext.define('Scalr.ui.PanelScrollFixPlugin', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.panelscrollfix',

    disabled: false,
	client:null,

    scrollTop: 0,
    
	init: function(client) {
		var me = this;
		me.client = client;
		client.on({
			afterrender: function() {
               this.body.el.on('scroll', me.saveScrollPosition, me);
			},
			beforedestroy: function() {
				this.body.el.un('scroll', me.saveScrollPosition, me);
                this.un('afterlayout', me.restoreScrollPosition);
			},
            afterlayout: {
                fn: me.restoreScrollPosition,
                scope: me
            }
		});
	},

    saveScrollPosition: function() {
        if (!this.suspendEvents) {
            this.scrollTop = this.client.body.el.getScroll().top;
        }
    },

    restoreScrollPosition: function() {
        this.suspendEvents = true;
        this.client.body.scrollTo('top', this.scrollTop || 0);
        this.suspendEvents = false;
    },

    resetScrollPosition: function() {
        this.scrollTop = 0;
    }
});


Ext.define('Scalr.ui.ActionsMenu', {
	extend: 'Ext.menu.Menu',
    cls: 'x-options-menu',
	constructor: function () {
		this.callParent(arguments);
        this.linkTplsCache = {};
    },

    onClick: function(e) {
        var me = this,
            item;

        if (me.disabled) {
            e.stopEvent();
            return;
        }

        item = (e.type === 'click') ? me.getItemFromEvent(e) : me.activeItem;
        if (item && item.isMenuItem) {
            if (!item.menu || !me.ignoreParentClicks) {
                item.onClick(e);
            } else {
                e.stopEvent();
            }
            /*Changed*/
            if (Ext.isFunction (item.menuHandler)) {
                item.menuHandler(me.data);
                e.stopEvent();
            } else if (Ext.isObject(item.request)) {
                var r = Scalr.utils.CloneObject(item.request);
                r.params = r.params || {};

                if (Ext.isObject(r.confirmBox))
                    r.confirmBox.msg = new Ext.Template(r.confirmBox.msg).applyTemplate(me.data);

                if (Ext.isFunction(r.dataHandler)) {
                    r.params = Ext.apply(r.params, r.dataHandler(me.data));
                    delete r.dataHandler;
                }
                if (r.success === undefined) {
                    r.success = function () {
                        me.fireEvent('actioncomplete');
                    }
                }
                Scalr.Request(r);
                e.stopEvent();
            }
            /*End*/
        }
        // Click event may be fired without an item, so we need a second check
        if (!item || item.disabled) {
            item = undefined;
        }
        me.fireEvent('click', me, item, e);
    },
    
    setData: function(data) {
        var me = this,
            prevSeparator,
            display;
        me.data = data;

		this.items.each(function (item) {
			display = Ext.isFunction(item.getVisibility) ? item.getVisibility(me.data) : true;
            if (display) {//prevent double separators
                if (item.xtype === 'menuseparator') {
                    display = prevSeparator === undefined;
                    prevSeparator = display ? item : prevSeparator;
                } else {
                    prevSeparator = undefined;
                }
            }
            
			item[display ? 'show' : 'hide']();
			if (display && item.href) {
				// Update item link
				if (! this.linkTplsCache[item.id]) {
					this.linkTplsCache[item.id] = new Ext.Template(item.href).compile();
				}
				var tpl = this.linkTplsCache[item.id];
				if (item.rendered) {
					item.el.down('a').dom.href = tpl.apply(me.data);
                }
			}
		}, this);
    }
});
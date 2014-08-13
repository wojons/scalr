Ext.define('Scalr.ui.PagingToolbar', {
	extend: 'Ext.PagingToolbar',
	alias: 'widget.scalrpagingtoolbar',

	pageSizes: [10, 15, 25, 50, 100],
	pageSizeMessage: '{0} items per page',
	pageSizeStorageName: 'grid-ui-page-size',
	autoRefresh: 0,
	autoRefreshTask: 0,
	//height: 41,
	prependButtons: true,
	beforeItems: [],
	afterItems: [],
    calculatePageSize: true,
    enableParamsCapture: true,

	checkRefreshHandler: function (item, enabled) {
		if (enabled) {
			this.autoRefresh = item.autoRefresh;
			this.gridContainer.autoRefresh = this.autoRefresh;
			this.gridContainer.saveState();
			if (this.autoRefresh) {
				this.setDelayedRefresh();
				this.down('#refresh').setIconCls('x-tbar-autorefresh');
			} else {
				this.clearDelayedRefresh();
				this.down('#refresh').setIconCls('x-tbar-loading');
			}
		}
	},

	getPagingItems: function() {
		var me = this, items = [ '->' ];

		if (this.beforeItems.length) {
            for (var i = 0; i < this.beforeItems.length; i++)
                this.beforeItems[i]['margin'] = '0 9 0 0';

			items = Ext.Array.push(items, this.beforeItems);
		}

		items = Ext.Array.merge(items, [{
			itemId: 'refresh',
			//	tooltip: me.refreshText,
			overflowText: me.refreshText,
			iconCls: Ext.baseCSSPrefix + 'tbar-loading',
			ui: 'paging',
			handler: me.doRefresh,
			scope: me,
            margin: '0 11 0 0'
		}, {
            xtype: 'tbseparator',
            margin: '0 11 0 0'
        }, {
			itemId: 'first',
			//tooltip: me.firstText,
			overflowText: me.firstText,
			iconCls: Ext.baseCSSPrefix + 'tbar-page-first',
			ui: 'paging',
			disabled: true,
			handler: me.moveFirst,
			scope: me,
            margin: '0 9 0 0'
		},{
			itemId: 'prev',
			//tooltip: me.prevText,
			overflowText: me.prevText,
			iconCls: Ext.baseCSSPrefix + 'tbar-page-prev',
			ui: 'paging',
			disabled: true,
			handler: me.movePrevious,
			scope: me,
            margin: '0 9 0 0'
		}, me.beforePageText, {
			xtype: 'textfield',
			itemId: 'inputItem',
			name: 'inputItem',
			cls: Ext.baseCSSPrefix + 'tbar-page-number',
			maskRe: /[0123456789]/,
			minValue: 1,
			enableKeyEvents: true,
			selectOnFocus: true,
			submitValue: false,
			// mark it as not a field so the form will not catch it when getting fields
			isFormField: false,
			width: 40,
			listeners: {
				scope: me,
				keydown: me.onPagingKeyDown,
				blur: me.onPagingBlur
			}
		},{
			xtype: 'tbtext',
			itemId: 'afterTextItem',
			text: Ext.String.format(me.afterPageText, 1)
		}, {
            itemId: 'next',
            //tooltip: me.nextText,
            overflowText: me.nextText,
            iconCls: Ext.baseCSSPrefix + 'tbar-page-next',
            ui: 'paging',
            disabled: true,
            handler: me.moveNext,
            scope: me,
            margin: '0 9 0 0'
        },{
            itemId: 'last',
            //	tooltip: me.lastText,
            overflowText: me.lastText,
            iconCls: Ext.baseCSSPrefix + 'tbar-page-last',
            ui: 'paging',
            disabled: true,
            handler: me.moveLast,
            scope: me,
            margin: '0 11 0 0'
        }]);

		if (this.afterItems.length) {
            for (var i = 0; i < this.afterItems.length; i++)
                this.afterItems[i]['margin'] = '0 9 0 0';

            this.afterItems[this.afterItems.length - 1]['margin'] = '0 12 0 0';
            items.push({
				xtype: 'tbseparator',
                margin: '0 8 0 0'
			});
			items = Ext.Array.push(items, this.afterItems);
		}

		return items;
	},

	evaluatePageSize: function() {
		var grid = this.gridContainer, view = grid.getView();
		if (Ext.isDefined(grid.height) && view.rendered)
			return Math.floor(view.el.getHeight() / 26); // row's height
	},

	getPageSize: function() {
		var pageSize = 0;
		if (Ext.state.Manager.get(this.pageSizeStorageName, 'auto') != 'auto')
			pageSize = Ext.state.Manager.get(this.pageSizeStorageName, 'auto');
		else {
			//var panel = this.up('panel'), view = (panel.getLayout().type == 'card') ? panel.getLayout().getActiveItem().view : panel;
            var grid = this.gridContainer, view = grid.getView();
			if (Ext.isDefined(grid.height) && view && view.rendered)
				pageSize = Math.floor(view.el.getHeight() / 26); // row's height
		}
		return pageSize;
	},

	setPageSizeAndLoad2: function() {
		// TODO check this code, move to gridContainer
		var panel = this.up('panel'), view = (panel.getLayout().type == 'card') ? panel.getLayout().getActiveItem().view : panel;
		if (Ext.isDefined(panel.height) && view && view.rendered) {
			panel.store.pageSize = this.getPageSize();
			if (Ext.isObject(this.data)) {
				panel.store.loadData(this.data.data);
				panel.store.totalCount = this.data.total;
			} else
				panel.store.load();
		}
	},

    setPageSizeAndLoad: function() {
        var grid = this.gridContainer, view = grid.getView();
        if (this.calculatePageSize && Ext.isDefined(grid.height) && view.rendered) {
            grid.store.pageSize = this.getPageSize();
            if (Ext.isObject(this.data)) {
                grid.store.loadData(this.data.data);
                grid.store.totalCount = this.data.total;
            } else
                grid.store.load();
        } else {
            grid.store.load();
        }
    },

    doRefresh : function(){
        var me = this,
            current = me.store.currentPage;

        if (me.fireEvent('beforechange', me, current) !== false) {
            me.store.gridHightlightNew = true;
            me.store.loadPage(current);
        }
    },

    moveNext : function(){
		var me = this,
			total = me.getPageData().pageCount,
			next = me.store.currentPage + 1;

		if (me.store.currentPage == 1 && me.store.pageSize != me.evaluatePageSize() && me.calculatePageSize) {
			// if page has less records, that it could include, load more records per page
			if (me.fireEvent('beforechange', me, next) !== false) {
				me.store.pageSize = me.evaluatePageSize();
				me.store.load();
			}
		} else if (next <= total) {
			if (me.fireEvent('beforechange', me, next) !== false) {
				me.store.nextPage();
			}
		}
	},

	initComponent: function () {
		this.callParent();

		this.on('added', function (comp, container) {
			this.gridContainer = container;

            // TODO: on back to page event, refresh grid WITH gridHightlightNew
			this.refreshHandler = Ext.Function.bind(function () {
                this.store.gridHightlightNew = true;
				this.store.load();
			}, this.gridContainer);

			this.gridContainer.on('activate', function () {
				if (this.store.pageSize != this.getPageSize() || !this.data) {
					this.setPageSizeAndLoad();
                }
				if (this.autoRefresh) {
					this.setDelayedRefresh();
                }
			}, this);

			this.gridContainer.on('deactivate', function () {
				this.clearDelayedRefresh();
			}, this);

			this.gridContainer.store.on('load', function () {
				if (this.autoRefreshTask) {
					this.clearDelayedRefresh();
					if (this.autoRefresh) {
						this.setDelayedRefresh();
                    }
				}
			}, this);

			this.gridContainer.on('staterestore', function(comp) {
				this.autoRefresh = comp.autoRefresh || 0;
				if (this.autoRefresh) {
					this.down('#refresh').setIconCls('x-tbar-autorefresh');
                }
			}, this);
		});
	},
    setDelayedRefresh: function() {
        this.clearDelayedRefresh();
        this.autoRefreshTask = setTimeout(this.refreshHandler, this.autoRefresh * 1000);

    },
    clearDelayedRefresh: function() {
        if (this.autoRefreshTask) {
            clearTimeout(this.autoRefreshTask);
            this.autoRefreshTask = 0;
        }
    },
    onLoad : function(){
        //fix current page
        if (this.store.currentPage > Math.ceil(this.store.getTotalCount() / this.store.pageSize)) {
            this.gridContainer.store.currentPage = 1;
        }
        this.callParent(arguments);
    }
});

Ext.define('Scalr.ui.ToolbarCloudLocation', {
	extend: 'Ext.form.field.ComboBox',
	alias: 'widget.fieldcloudlocation',

	localParamName: 'grid-ui-default-cloud-location',
	fieldLabel: 'Location',
	labelWidth: 53,
	width: 358,
	matchFieldWidth: false,
	listConfig: {
		width: 'auto',
		minWidth: 300
	},
	iconCls: 'no-icon',
	displayField: 'name',
	valueField: 'id',
	editable: false,
	queryMode: 'local',
	setCloudLocation: function () {
        if (this.store.getCount() == 0) {
            Scalr.message.Warning('Location\'s list is empty');
            return;
        }

		if (this.cloudLocation) {
			this.setValue(this.cloudLocation);
		} else {
			var cloudLocation = Ext.state.Manager.get(this.localParamName);
			if (cloudLocation) {
				var ind = this.store.find('id', cloudLocation);
				if (ind != -1)
					this.setValue(cloudLocation);
				else
					this.setValue(this.store.getAt(0).get('id'));
			} else {
				this.setValue(this.store.getAt(0).get('id'));
			}
		}
		this.gridStore.proxy.extraParams.cloudLocation = this.getValue();
	},
	listeners: {
		change: function () {
			if (! this.getValue())
				this.setCloudLocation();
		},
		select: function () {
			Ext.state.Manager.set(this.localParamName, this.getValue());
			this.gridStore.proxy.extraParams.cloudLocation = this.getValue();
			this.gridStore.loadPage(1);
		},
		added: function () {
			this.setCloudLocation();
		}
	}
});

Ext.define('Scalr.ui.GridRadioColumn', {
	extend: 'Ext.grid.column.Column',
	alias: ['widget.radiocolumn'],

	initComponent: function(){
		var me = this;
		me.hasCustomRenderer = true;
		me.callParent(arguments);
	},
	width: 35,

	processEvent: function(type, view, cell, recordIndex, cellIndex, e, record) {
		var me = this;
		if (type == 'click' && e.getTarget('input.x-form-radio')) {
			view.store.each(function(r) {
				r.set(me.dataIndex, false);
			})
			record.set(me.dataIndex, true);
		}
		return this.callParent(arguments);
	},

	defaultRenderer: function(value, meta, record) {
		var result = '<div ';
		if (value)
			result += 'class="x-form-cb-checked" '
		result += 'style="text-align: center" ><input type="button" class="x-form-field x-form-radio" style="border:0" /></div>';

		return result;
	}
});

Ext.define('Scalr.ui.GridOptionsColumn', {
	extend: 'Ext.grid.column.Column',
	alias: 'widget.optionscolumn',

	text: '&nbsp;',
	hideable: false,
	width: 110,
    minWidth: 110,
	fixed: true,
	align: 'center',
	tdCls: 'x-grid-row-options-cell',

	constructor: function () {
		this.callParent(arguments);

		this.sortable = false;
		this.optionsMenu = Ext.create('Ext.menu.Menu', {
            cls: 'x-options-menu',
			items: this.optionsMenu,
			listeners: {
				click: function (menu, item, e) {
					if (item) {
						if (Ext.isFunction (item.menuHandler)) {
							item.menuHandler(item);
							e.preventDefault();
						} else if (Ext.isObject(item.request)) {
							var r = Scalr.utils.CloneObject(item.request);
							r.params = r.params || {};

							if (Ext.isObject(r.confirmBox))
								r.confirmBox.msg = new Ext.Template(r.confirmBox.msg).applyTemplate(item.record.data);

							if (Ext.isFunction(r.dataHandler)) {
								r.params = Ext.apply(r.params, r.dataHandler(item.record));
								delete r.dataHandler;
							}

							Scalr.Request(r);
							e.preventDefault();
						}
					}
				}
			}
		});

		this.optionsMenu.doAutoRender();
        this.optionsMenu.on('hide', function() {
            if (this.currentBtnEl)
                this.currentBtnEl.removeCls('x-grid-row-options-pressed');

            this.currentBtnEl = null;
        }, this);
	},

	showOptionsMenu: function(view, record) {
        var btnEl = Ext.get(view.getNode(record)).down('div.x-grid-row-options');

        var prevSeparator;
		this.optionsMenu.suspendLayouts();
		this.beforeShowOptions(record, this.optionsMenu);
		this.optionsMenu.show();
        
		this.optionsMenu.items.each(function (item) {
			var display = this.getOptionVisibility(item, record);
			item.record = record;
            if (display) {//prevent double separators
                if (item.xtype === 'menuseparator') {
                    display = prevSeparator === undefined;
                    prevSeparator = display ? item : undefined;
                } else {
                    prevSeparator = undefined;
                }
            }
			item[display ? "show" : "hide"]();
			if (display && item.href) {
				// Update item link
				if (! this.linkTplsCache[item.id]) {
					this.linkTplsCache[item.id] = new Ext.Template(item.href).compile();
				}
				var tpl = this.linkTplsCache[item.id];
				if (item.rendered)
					item.el.down('a').dom.href = tpl.apply(record.data);
			}
		}, this);

		this.optionsMenu.resumeLayouts();
		this.optionsMenu.doLayout();

		var xy = btnEl.getXY(), sizeX = xy[1] + btnEl.getHeight() + this.optionsMenu.getHeight();
        btnEl.addCls('x-grid-row-options-pressed');
        this.currentBtnEl = btnEl;
		// menu shouldn't overflow window size
		if (sizeX > Scalr.application.getHeight()) {
			xy[1] -= sizeX - Scalr.application.getHeight();
		}

		this.optionsMenu.setPosition([xy[0] - (this.optionsMenu.getWidth() - btnEl.getWidth()), xy[1] + btnEl.getHeight() + 1]);
	},

	initComponent: function() {
		this.callParent(arguments);

		this.on('boxready', function () {
			this.up('panel').on('itemclick', function (view, record, item, index, e) {
				var btnEl = Ext.get(e.getTarget('div.x-grid-row-options'));
				if (! btnEl)
					return;

				this.showOptionsMenu(view, record);
			}, this);
		});
	},

	renderer: function(value, meta, record, rowIndex, colIndex) {
		if (this.headerCt.getHeaderAtIndex(colIndex).getVisibility(record))
			return '<div class="x-grid-row-options">Actions<div class="x-grid-row-options-trigger"></div></div>';
	},

	linkTplsCache: {},

	getVisibility: function(record) {
		return true;
	},

	getOptionVisibility: function(item, record) {
		return true;
	},

	beforeShowOptions: function(record, menu) {

	}
});

Ext.define('Scalr.ui.GridOptionsColumn2', {
	extend: 'Ext.grid.column.Column',
	alias: 'widget.optionscolumn2',

	text: '&nbsp;',
	hideable: false,
	width: 110,
    minWidth: 110,
	fixed: true,
	align: 'center',
	tdCls: 'x-grid-row-options-cell',

    constructor: function () {
        this.callParent(arguments);

        this.sortable = false;
        if (Ext.isArray(this.menu)) {
            this.menu = {
                xtype: 'actionsmenu',
                items: this.menu
            };
        }
        this.menu = Ext.widget(this.menu);
        this.menu.doAutoRender();
        this.menu.on('hide', function() {
            if (this.currentBtnEl)
                this.currentBtnEl.removeCls('x-grid-row-options-pressed');
        }, this);
    },

    initComponent: function() {
        this.callParent(arguments);

        this.on('boxready', function () {
            this.up('panel').on('itemclick', function (view, record, item, index, e) {
                var btnEl = Ext.get(e.getTarget('div.x-grid-row-options'));
                if (! btnEl) {
                    return;
                }
                if (this.currentBtnEl !== btnEl) {
                    btnEl.addCls('x-grid-row-options-pressed');
                    this.currentBtnEl = btnEl;
                    this.menu.setData(record.getData());
                    this.menu.showBy(btnEl, 'tr-br');
                    return;
                }
                this.menu.hide();
                this.currentBtnEl = null;

            }, this);
        });
    },

	renderer: function(value, meta, record, rowIndex, colIndex) {
		if (this.headerCt.getHeaderAtIndex(colIndex).getVisibility(record))
			return '<div class="x-grid-row-options">Actions<div class="x-grid-row-options-trigger"></div></div>';
	},

	getVisibility: function(record) {
		return true;
	}

});

Ext.define('Scalr.ui.ButtonGroupColumn', {
    extend: 'Ext.grid.column.Column',
    alias: 'widget.buttongroupcolumn',

    stopSelection: true,

    tdCls: Ext.baseCSSPrefix + 'grid-cell-buttongroupcolumn',
    innerCls: Ext.baseCSSPrefix + 'grid-cell-inner-buttongroupcolumn',

    clickTargetName: 'el',

    processEvent: function(type, view, cell, recordIndex, cellIndex, e, record, row) {
        var me = this,
            mousedown = type == 'mousedown',
            newValue;
        if (!me.disabled && mousedown) {
            Ext.Array.each(Ext.fly(cell).query('.x-btn'), function(btn){
                var b = Ext.fly(btn);
                if (e.within(b)) {
                    if (!b.hasCls('x-pressed')) {
                        newValue = b.getAttribute('data-value')
                    }
                }
            });
            if (newValue !== undefined) {
                if (me.toggleHandler !== undefined){
                    me.toggleHandler(view, record, newValue);
                } else {
                    record.set(me.dataIndex, newValue);
                }
            }
            e.stopEvent();
            return false;
        } else {
            return me.callParent(arguments);
        }
    },

    renderer : function(value, meta, record, rowIndex, colIndex, store, grid) {
        var column = grid.panel.columns[colIndex],
            buttons = column.buttons,
            html = [];
        value = column.getValue !== undefined ? column.getValue(record) : value;
        html.push('<div class="x-form-buttongroupfield">');
        Ext.Array.each(buttons, function(btn, index, arr){
            html.push('<a ' + (btn.width ? 'style="width:' + btn.width + 'px"' : '') + ' class="x-btn x-unselectable x-btn-default-small' + (value == btn.value ? ' x-pressed x-btn-pressed x-btn-default-small-pressed' : '') +'" data-value="' + btn.value + '">');
            html.push('<span class="x-btn-wrap"><span class="x-btn-button">');
            html.push('<span class="x-btn-inner x-btn-inner-center" >' + btn.text + '</span>');
            html.push('</span></span></a>');
        });
        html.push('</div>');
        return html.join('');
    }
});

Ext.define('Scalr.ui.MultiCheckboxColumn', {
    extend: 'Ext.grid.column.Column',
    alias: 'widget.multicheckboxcolumn',

    stopSelection: true,

    tdCls: Ext.baseCSSPrefix + 'grid-cell-multicheckboxcolumn',
    innerCls: Ext.baseCSSPrefix + 'grid-cell-inner-multicheckboxcolumn',

    clickTargetName: 'el',
    itemCls: 'x-multicheckbox-item',
    itemCheckedCls: 'x-multicheckbox-item-checked',
    itemDisabledCls: 'x-multicheckbox-item-disabled',
    itemReadOnlyCls: 'x-multicheckbox-item-readonly',

    processEvent: function(type, view, cell, recordIndex, cellIndex, e, record, row) {
        var me = this,
            mousedown = type == 'mousedown',
            changed = false,
            value, name;
        if (!me.disabled && mousedown) {
            value = Ext.clone(record.get(me.dataIndex));
            Ext.Array.each(Ext.fly(cell).query('.' + me.itemCls), function(item){
                var b = Ext.fly(item);
                if (e.within(b) && !b.hasCls(me.itemDisabledCls) && !me.readonly) {
                    name = b.getAttribute('data-value');
                    if (!b.hasCls(me.itemCheckedCls)) {
                        value[name] = 1;
                        changed = true;
                    } else {
                        value[name] = 0;
                        changed = true;
                    }
                    return false;
                }
            });
            if (changed) {
                me.fireEvent('beforechange', me, value, name, record, cell);
                record.set(me.dataIndex, value);
            }
            e.stopEvent();
            return false;
        } else {
            return me.callParent(arguments);
        }
    },

    renderer : function(value, meta, record, rowIndex, colIndex, store, grid) {
        var granted = record.get('granted'),
            column = grid.panel.columns[colIndex],
            html = [];
        if (value) {
            Ext.Object.each(value, function(key, value){
                var cls = column.itemCls;
                if (granted == 1) {
                    if (value == 1) {
                        cls += ' ' + column.itemCheckedCls;
                    }
                } else if (!column.readonly){
                    cls += ' ' + column.itemDisabledCls;
                }

                if (column.readonly){
                    cls += ' ' + column.itemReadOnlyCls;
                }
                html.push('<span class="' + cls + '" data-value="' + key + '"><img src="' + Ext.BLANK_IMAGE_URL + '"/>' + key + '</span>');
            });
        }
        return column.customRenderer ? column.customRenderer(html, record) : html.join('');
    }
});

Ext.define('Scalr.ui.RowPointer', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.rowpointer',

    disabled: false,
	client: null,

	baseCls: 'x-panel-row-pointer',
	addCls: null,
    
    align: 'left',
    
    width: 32,
    height: 28,
	addOffset: 0,
	thresholdOffset: 0,
	hiddenOffset: -100,
    
	init: function(client) {
		this.client = client;
        this.baseCls += ' ' + this.baseCls + '-' + this.align;
        this.initListeners();
	},

    initListeners: function() {
        var me = this;
		me.throttledUpdatePointerPosition = Ext.Function.createThrottled(me.updatePointerPosition, me.throttle, me);

        this.client.on('afterrender', function() {
            this.on('afterlayout', me.throttledUpdatePointerPosition, me);
            this.view.el.on('scroll', me.throttledUpdatePointerPosition, me);

            this.on('beforedestroy',  function() {
                this.un('afterlayout', me.throttledUpdatePointerPosition, me);
                this.view.el.un('scroll', me.throttledUpdatePointerPosition, me);
            });
        });
    },

    getPointerEl: function() {
        if (this.pointerEl === undefined) {
            this.pointerEl = Ext.DomHelper.append(this.client.el.dom, '<div class="' + this.baseCls + (this.addCls ? ' ' + this.addCls  : '') + '"' + (this.tooltip ? ' title="' + this.tooltip + '"'  : '') + '></div>', true);
            this.pointerEl.setWidth(this.width);
            this.pointerEl.setHeight(this.height);
        }
        return this.pointerEl;
    },

    pointTo: function(record) {
		var offset = this.hiddenOffset;
		if (this.client.view && record) {
            var node = this.client.view.getNode(record);
            if (node) {
                offset = Ext.get(node).getOffsetsTo(this.client.el)[1] + this.addOffset;
                offset = offset < this.thresholdOffset ? this.hiddenOffset : offset;
            }
		}
		this.getPointerEl().setStyle('top', offset + 'px');
    },

	updatePointerPosition: function() {
        if (Ext.isFunction(this.getPointerRecord)) {
            this.pointTo(this.getPointerRecord());
        }
	}

});

Ext.define('Scalr.ui.FocusedRowPointer', {
    extend: 'Scalr.ui.RowPointer',
    alias: 'plugin.focusedrowpointer',

    align: 'right',

    width: 10,
	thresholdOffset: 60,
	
	throttle: 100,
    
    initListeners: function() {
        var me = this;
		me.throttledUpdatePointerPosition = Ext.Function.createThrottled(me.updatePointerPosition, me.throttle, me);

        this.client.on('afterrender', function() {
            this.on('afterlayout', me.throttledUpdatePointerPosition, me);
            this.getSelectionModel().on('focuschange', me.throttledUpdatePointerPosition, me);
            this.view.el.on('scroll', me.throttledUpdatePointerPosition, me);

            this.on('beforedestroy',  function() {
                this.un('afterlayout', me.throttledUpdatePointerPosition, me);
                this.getSelectionModel().un('focuschange', me.throttledUpdatePointerPosition, me);
                this.view.el.un('scroll', me.throttledUpdatePointerPosition, me);
            });
        });
    },

    getPointerRecord: function() {
        return this.client.getSelectionModel().lastFocused;
    }

});

Ext.define('Ext.grid.feature.AddButton', {
    extend: 'Ext.grid.feature.Feature',
    alias: 'feature.addbutton',
    cls: Ext.baseCSSPrefix + 'grid-add-button',
    viewCls: Ext.baseCSSPrefix + 'grid-with-add-button',
    disabledCls: Ext.baseCSSPrefix + 'disabled',
    text: 'Add',
    
    init: function(grid) {
        var me = this;

        me.callParent(arguments);
        grid.view.addCls(me.viewCls);

        me.updateButtonPositionBuffered = Ext.Function.createBuffered(me.updateButtonPosition, 0, me);
        grid.view.on('viewready', function() {
                me.updateButtonPosition();
                this.on('resize', me.updateButtonPositionBuffered, me);
                this.on('refresh', me.updateButtonPositionBuffered, me);
                this.on('itemadd', me.updateButtonPositionBuffered, me);
                this.on('itemremove', me.updateButtonPositionBuffered, me);
                this.el.on('scroll', me.updateButtonPositionBuffered, me);
                this.el.on('click', me.onViewClick, me);
            },
            grid.view,
            {single: true}
        );
        grid.view.on('beforedestroy', function() {
                this.un('resize', me.updateButtonPositionBuffered, me);
                this.un('refresh', me.updateButtonPositionBuffered, me);
                this.un('itemadd', me.updateButtonPositionBuffered, me);
                this.un('itemremove', me.updateButtonPositionBuffered, me);
                this.el.un('scroll', me.updateButtonPositionBuffered, me);
                this.el.un('click', me.onViewClick, me);
            },
            grid.view,
            {single: true}
        );

        me.view.addFooterFn(me.renderTFoot);
    },

    renderTFoot: function(values, out) {
        var view = values.view,
            me = view.findFeature('addbutton'),
            colspan = view.headerCt.getVisibleGridColumns().length;

            out.push('<tfoot class="x-grid-add-button-wrap' + (me.disabled ? ' ' + me.disabledCls : '') + '" id="' + view.id + '-add-button"><tr><td colspan="' + colspan + '"><div class="' + me.cls + '">' + me.text + '</div></td></tr></tfoot>');//<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-grid-add-item"/>&nbsp;&nbsp;
    },

    onViewClick: function(e, t) {
        var el = this.view.el.getById(this.view.id + '-add-button');
        if (el && e.within(el) && !this.disabled) {
            this.handler(this.view);
        }
    },

    setDisabled: function(disabled, tooltip) {
        var el = this.view.el.getById(this.view.id + '-add-button');
        if (el) {
            el[disabled ? 'addCls' : 'removeCls'](this.disabledCls);
            el.set({
                'data-qtip': disabled && tooltip ? tooltip : ''
            })
        }
        this.disabled = !!disabled;
    },

    updateButtonPosition: function() {
        if (this.view.isDestroyed) return;
        var btn = this.view.el.getById(this.view.id + '-add-button');
        btn.setStyle('top', (this.view.el.getScroll().top + this.view.el.getHeight() - btn.getHeight()) + 'px');
    }

});

Ext.define('Scalr.ui.GridStatusColumn', {
	extend: 'Ext.grid.column.Column',
	alias: 'widget.statuscolumn',

	text: '&nbsp;',
	hideable: false,
	width: 150,
    minWidth: 150,
	//fixed: true,
	align: 'center',
	tdCls: 'x-grid-row-colored-status-cell',

	renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
        var column = grid.panel.columns[colIndex];
        return Scalr.ui.ColoredStatus.getHtml({
            type: column['statustype'],
            status: record.data.status,
            data: record.data
        }, column.qtipConfig);
	}
});
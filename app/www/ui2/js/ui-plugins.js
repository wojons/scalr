/*
 * Messages system
 */
Ext.ns('Scalr.message');

Scalr.message = {
	queue: [],
	Add: function(message, type) {
		if (Ext.isArray(message)) {
			var s = '';
			for (var i = 0; i < message.length; i++)
				'<li>' + message[i] + '</li>'
			message = '<ul>' + s + '</ul>';
		}

		this.Flush(false, message);

		var tip = new Ext.tip.ToolTip({
			autoShow: true,
			autoHide: false,
			closable: true,
			closeAction: 'destroy',
			header: false,
			layout: {
				type: 'hbox'
			},
			minWidth: 200,
			maxWidth: 900,
			dt: Ext.Date.add(new Date(), Ext.Date.SECOND, 2),
			type: type,
			cls: 'x-tip-message x-tip-message-' + type,
			items: [{
				xtype: 'component',
				flex: 1,
				tpl: '{message}',
				data: {
					message: message
				}
			}, {
				xtype: 'tool',
				type: 'close',
				handler: function () {
					this.up('tooltip').close();
				}
			}],
			onDestroy: function () {
				Ext.Array.remove(Scalr.message.queue, this);
			}
		});

		tip.el.alignTo(Ext.getBody(), 't-t', [0, 15]);
		Scalr.message.queue.push(tip);
	},
	Error: function(message) {
		this.Add(message, 'error');
	},
	Success: function(message) {
		this.Add(message, 'success');
	},
	Warning: function(message) {
		this.Add(message, 'warning');
	},
	Flush: function(force, message) {
		var i = this.queue.length - 1, dt = new Date();

		while (i >= 0) {
			if (force || this.queue[i].dt < dt || this.queue[i].child('component').initialConfig.data.message == message) {
				this.queue[i].destroy();
			}
			i--;
		}
	}
}

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
				result[result.length] = { id: i, name: data[i] }; // format id => name
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
 * Grid plugins
 */
Ext.define('Scalr.ui.GridStorePlugin', {
	extend: 'Ext.AbstractPlugin',
	alias: 'plugin.gridstore',
	loadMask: false,

	init: function (client) {
		client.getView().loadMask = this.loadMask;
		client.store.proxy.view = client.getView(); // :(

		client.store.on({
			scope: client,
			beforeload: function () {
				if (this.getView().rendered)
					this.getView().clearViewEl();
				if (! this.getView().loadMask)
					this.processBox = Scalr.utils.CreateProcessBox({
						type: 'action',
						msg: client.getView().loadingText
					});
			},
			load: function (store, records, success, operation, options) {
				if (! this.getView().loadMask)
					this.processBox.destroy();
			}
		});

		client.store.proxy.on({
			exception: function (proxy, response, operation, options) {
				var message = 'Unable to load data';
				try {
					var result = Ext.decode(response.responseText, true);
					if (result && result.success === false && result.errorMessage)
						message += ' (' + result.errorMessage + ')';
					else
						throw 'Report';
				} catch (e) {
					if (response.status == 200) {
						var report = [ "Ext.JSON.decode(): You're trying to decode an invalid JSON String" ];
						report.push(Scalr.utils.VarDump(response.request.headers));
						report.push(Scalr.utils.VarDump(response.request.options));
						report.push(Scalr.utils.VarDump(response.request.options.params));
						report.push(Scalr.utils.VarDump(response.getAllResponseHeaders()));
						report.push(response.status);
						report.push(response.responseText);

						report = report.join("\n\n");

						Scalr.utils.PostError({
							message: report,
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
			}
		});
	}
});

Ext.define('Scalr.ui.SwitchViewPlugin', {
	extend: 'Ext.AbstractPlugin',
	alias: 'plugin.switchview',
	
	init: function (client) {
		client.on('beforerender', function () {
			var field = this.down('[xtype="tbswitchfield"]');
			if (field) {
				this.activeItem = field.switchValue;

				field.on('statesave', function (c, state) {
					this.getLayout().setActiveItem(state.switchValue);
				}, this);
			}
		}, client);
	}
});

Ext.define('Scalr.ui.PagingToolbar', {
	extend: 'Ext.PagingToolbar',
	alias: 'widget.scalrpagingtoolbar',

	pageSizes: [10, 15, 25, 50, 100],
	pageSizeMessage: '{0} items per page',
	pageSizeStorageName: 'grid-ui-page-size',
	autoRefresh: 0,
	autoRefreshTask: 0,
	height: 33,
	prependButtons: true,
	afterItems: [],

	checkRefreshHandler: function (item, enabled) {
		if (enabled) {
			this.autoRefresh = item.autoRefresh;
			this.gridContainer.autoRefresh = this.autoRefresh;
			this.gridContainer.saveState();
			if (this.autoRefresh) {
				clearInterval(this.autoRefreshTask);
				this.autoRefreshTask = setInterval(this.refreshHandler, this.autoRefresh * 1000);
				this.down('#refresh').setIconCls('x-tbar-autorefresh');
			} else {
				clearInterval(this.autoRefreshTask);
				this.down('#refresh').setIconCls('x-tbar-loading');
			}
		}
	},

	getPagingItems: function() {
		var me = this;

		var items = [ '->', {
			itemId: 'first',
			//tooltip: me.firstText,
			overflowText: me.firstText,
			iconCls: Ext.baseCSSPrefix + 'tbar-page-first',
			ui: 'paging',
			disabled: true,
			handler: me.moveFirst,
			scope: me
		},{
			itemId: 'prev',
			//tooltip: me.prevText,
			overflowText: me.prevText,
			iconCls: Ext.baseCSSPrefix + 'tbar-page-prev',
			ui: 'paging',
			disabled: true,
			handler: me.movePrevious,
			scope: me
		},
			me.beforePageText,
			{
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
			},
			{
				itemId: 'next',
				//tooltip: me.nextText,
				overflowText: me.nextText,
				iconCls: Ext.baseCSSPrefix + 'tbar-page-next',
				ui: 'paging',
				disabled: true,
				handler: me.moveNext,
				scope: me
			},{
				itemId: 'last',
				//	tooltip: me.lastText,
				overflowText: me.lastText,
				iconCls: Ext.baseCSSPrefix + 'tbar-page-last',
				ui: 'paging',
				disabled: true,
				handler: me.moveLast,
				scope: me
			},
			'-',
			{
				itemId: 'refresh',
				//	tooltip: me.refreshText,
				overflowText: me.refreshText,
				iconCls: Ext.baseCSSPrefix + 'tbar-loading',
				ui: 'paging',
				handler: me.doRefresh,
				scope: me
			}];

		if (this.afterItems.length) {
			items = Ext.Array.merge(items, this.afterItems);
		}

		return items;
	},

	getPageSize: function() {
		var pageSize = 0;
		if (Ext.state.Manager.get(this.pageSizeStorageName, 'auto') != 'auto')
			pageSize= Ext.state.Manager.get(this.pageSizeStorageName, 'auto');
		else {
			var panel = this.up('panel'), view = (panel.getLayout().type == 'card') ? panel.getLayout().getActiveItem().view : panel;
			if (Ext.isDefined(panel.height) && view && view.rendered)
				pageSize = Math.floor(view.el.getHeight() / 26); // row's height
		}
		return pageSize;
	},

	setPageSizeAndLoad: function() {
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

	initComponent: function () {
		this.callParent();

		this.on('added', function (comp, container) {
			this.gridContainer = container;

			this.gridContainer.scalrReconfigure = function (loadParams) {
				if (this.scalrReconfigureParams)
					Ext.applyIf(loadParams, this.scalrReconfigureParams);
				Ext.apply(this.store.proxy.extraParams, loadParams);
			};
			this.refreshHandler = Ext.Function.bind(function () {
				this.store.load();
			}, this.gridContainer);

			this.gridContainer.on('activate', function () {
				if (this.store.pageSize != this.getPageSize() || !this.data)
					this.setPageSizeAndLoad();
				if (this.autoRefresh)
					this.autoRefreshTask = setInterval(this.refreshHandler, this.autoRefresh * 1000);
			}, this);

			this.gridContainer.on('deactivate', function () {
				clearInterval(this.autoRefreshTask);
			}, this);

			this.gridContainer.store.on('load', function () {
				if (this.autoRefreshTask) {
					clearInterval(this.autoRefreshTask);
					if (this.autoRefresh)
						this.autoRefreshTask = setInterval(this.refreshHandler, this.autoRefresh * 1000);
				}
			}, this);

			this.gridContainer.on('staterestore', function(comp) {
				this.autoRefresh = comp.autoRefresh || 0;
				if (this.autoRefresh)
					this.down('#refresh').setIconCls('x-tbar-autorefresh');
			}, this);
		});
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
		result += 'style="text-align: center" ><input type="button" class="x-form-field x-form-radio" /></div>';

		return result;
	}
});

Ext.define('Scalr.ui.GridOptionsColumn', {
	extend: 'Ext.grid.column.Column',
	alias: 'widget.optionscolumn',

	text: '&nbsp;',
	hideable: false,
	width: 116,
	fixed: true,
	align: 'center',
	tdCls: 'x-grid-row-options-cell',

	constructor: function () {
		this.callParent(arguments);

		this.sortable = false;
		this.optionsMenu = Ext.create('Ext.menu.Menu', {
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
	},

	showOptionsMenu: function (view, record) {
		this.optionsMenu.suspendLayouts();
		this.beforeShowOptions(record, this.optionsMenu);
		this.optionsMenu.show();

		this.optionsMenu.items.each(function (item) {
			var display = this.getOptionVisibility(item, record);
			item.record = record;
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

		var btnEl = Ext.get(view.getNode(record)).down('div.x-grid-row-options'), xy = btnEl.getXY(), sizeX = xy[1] + btnEl.getHeight() + this.optionsMenu.getHeight();
		// menu shouldn't overflow window size
		if (sizeX > Scalr.application.getHeight()) {
			xy[1] -= sizeX - Scalr.application.getHeight();
		}

		this.optionsMenu.setPosition([xy[0] - (this.optionsMenu.getWidth() - btnEl.getWidth()), xy[1] + btnEl.getHeight() + 1]);
	},

	initComponent: function () {
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

	renderer: function (value, meta, record, rowIndex, colIndex) {
		if (this.headerCt.getHeaderAtIndex(colIndex).getVisibility(record))
			return '<div class="x-grid-row-options">Actions<div class="x-grid-row-options-trigger"></div></div>';
	},

	linkTplsCache: {},

	getVisibility: function (record) {
		return true;
	},

	getOptionVisibility: function (item, record) {
		return true;
	},

	beforeShowOptions: function (record, menu) {

	}
});

Ext.define('Scalr.ui.GridSelectionModel', {
	alias: 'selection.selectedmodel',
	extend: 'Ext.selection.CheckboxModel',

	injectCheckbox: 'last',
	highlightArrow: false,
	checkOnly: true,

	constructor: function () {
		this.callParent(arguments);

		this.selectedMenu = Ext.create('Ext.menu.Menu', {
			items: this.selectedMenu,
			listeners: {
				scope: this,
				click: function (menu, item, e) {
					if (! Ext.isDefined(item))
						return;

					var store = this.store, records = this.selected.items, r = Scalr.utils.CloneObject(Ext.apply({}, item.request));
					r.params = r.params || {};
					r.params = Ext.apply(r.params, r.dataHandler(records));

					if (Ext.isFunction(r.success)) {
						r.success = Ext.Function.createSequence(r.success, function() {
							store.load();
						});
					} else {
						r.success = function () {
							store.load();
						};
					}
					delete r.dataHandler;

					Scalr.Request(r);
				}
			}
		});

		this.selectedMenu.doAutoRender();
	},

	bindComponent: function () {
		this.callParent(arguments);

		this.view.on('refresh', function() {
			this.toggleUiHeader(false);
		}, this);
	},

	getHeaderConfig: function() {
		var c = this.callParent();
		c.width = 55;
		c.minWidth = c.width;
		c.headerId = 'scalrSelectedModelCheckbox';
		c.text = '<div class="arrow"></div>';
		return c;
	},

	getVisibility: function (record) {
		return true;
	},

	renderer: function(value, metaData, record, rowIndex, colIndex, store, view) {
		metaData.tdCls = Ext.baseCSSPrefix + 'grid-cell-special';
		metaData.style = 'margin-left: 5px';

		if (this.getVisibility(record))
			return '<div class="' + Ext.baseCSSPrefix + 'grid-row-checker">&#160;</div>';
	},

	// don't check unavailable items
	selectAll: function(suppressEvent) {
		var me = this,
			selections = [],
			i = 0,
			len,
			start = me.getSelection().length;

		Ext.each(me.store.getRange(), function (record) {
			if (this.getVisibility(record))
				selections.push(record);
		}, this);

		len = selections.length;

		me.bulkChange = true;
		for (; i < len; i++) {
			me.doSelect(selections[i], true, suppressEvent);
		}
		delete me.bulkChange;
		// fire selection change only if the number of selections differs
		me.maybeFireSelectionChange(me.getSelection().length !== start);
	},

	onSelectChange: function() {
		this.callParent(arguments);

		if (! this.highlightArrow) {
			this.highlightArrow = true;

			var view     = this.views[0],
				headerCt = view.headerCt,
				checkHd  = headerCt.child('gridcolumn[isCheckerHd]');

			Ext.create('Ext.fx.Animator', {
				target: checkHd.el.down('div.arrow'),
				duration: 3000,
				iterations: 3,
				keyframes: {
					0: {
						opacity: 1
					},
					10: {
						opacity: 0.3
					},
					20: {
						opacity: 1
					}
				}
			});
		}

		// check to see if all records are selected
		var me = this, selections = [];
		Ext.each(me.store.getRange(), function (record) {
			if (this.getVisibility(record))
				selections.push(record);
		}, this);

		var hdSelectStatus = this.selected.getCount() === selections.length;
		this.toggleUiHeader(hdSelectStatus);
	},

	onHeaderClick: function(headerCt, header, e) {
		if (header.isCheckerHd && !e.getTarget('span.x-column-header-text', 1)) {
			// show menu only if it's not span
			var btnEl = Ext.get(e.getTarget('div.x-column-header-checkbox')), xy = btnEl.getXY();

			if (this.selected.length)
				this.selectedMenu.el.unmask();
			else
				this.selectedMenu.el.mask();

			this.selectedMenu.show();
			this.selectedMenu.setPosition([xy[0] - (this.selectedMenu.getWidth()  - btnEl.getWidth()), xy[1] + btnEl.getHeight() + 1]);
			e.stopEvent();
		} else {
			this.callParent(arguments);
		}
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
	
    onRowMouseDown: function(view, record, item, index, e) {
        view.el.focus();
        var me = this,
            checker = e.getTarget('.' + Ext.baseCSSPrefix + 'grid-row-checker'),
            mode;

        if (!me.allowRightMouseSelection(e)) {
            return;
        }

        // checkOnly set, but we didn't click on a checker.
        if (me.checkOnly && !checker) {
			if (me.checkOnly) {
				me.setLastFocused(record);
				me.lastSelected = record;
			}
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
			
			me.setLastFocused(record);
			me.onLastFocusChanged(record, record);//get focus back to row after click
        } else {
            me.selectWithEvent(record, e);
        }
    },
	
    selectWithEvent: function(record, e, keepExisting) {
        var me = this;

        switch (me.selectionMode) {
            case 'MULTI':
                if (e.ctrlKey && me.isSelected(record)) {
                    me.doDeselect(record, false);
                } else if (e.shiftKey && me.lastFocused) {
                    me.selectRange(me.lastFocused, record, true);
                } else if (e.ctrlKey) {
                    me.doSelect(record, true, false);
                } else if (me.isSelected(record) && !e.shiftKey && !e.ctrlKey && me.selected.getCount() > 1) {
                    me.doSelect(record, keepExisting, false);
                } else {
                    me.doSelect(record, false);
                }
                break;
            case 'SIMPLE':
                if (me.isSelected(record)) {
                    me.doDeselect(record);
                } else {
                    me.doSelect(record, true);
                }
                break;
            case 'SINGLE':
                // if allowDeselect is on and this record isSelected, deselect it
                if (me.allowDeselect && me.isSelected(record)) {
                    me.doDeselect(record);
                // select the record and do NOT maintain existing selections
                } else {
                    me.doSelect(record, false);
                }
                break;
        }
    }	
	
});

Ext.define('Scalr.ui.GridSelection2Model', {
	alias: 'selection.selected2model',
	extend: 'Ext.selection.CheckboxModel',

	injectCheckbox: 'last',
	checkOnly: true,
	showHeaderCheckbox: false,
	cache: {},

/*
 iconCls: 'x-menu-icon-edit',
 text: 'Edit',
 href: '#/roles/{id}/edit',
 itemId: 'option.edit',
 visibility: function (record) {
 if (record.get('origin') == 'CUSTOM') {
 if (! moduleParams['isScalrAdmin'])
 return true;
 else
 return false;
 } else {
 return moduleParams['isScalrAdmin'];
 }
 }

 */

	constructor: function () {
		this.callParent(arguments);

		/*this.selectedMenu = Ext.create('Ext.menu.Menu', {
			items: this.selectedMenu,
			listeners: {
				scope: this,
				click: function (menu, item, e) {
					if (! Ext.isDefined(item))
						return;

					var store = this.store, records = this.selected.items, r = Scalr.utils.CloneObject(Ext.apply({}, item.request));
					r.params = r.params || {};
					r.params = Ext.apply(r.params, r.dataHandler(records));

					if (Ext.isFunction(r.success)) {
						r.success = Ext.Function.createSequence(r.success, function() {
							store.load();
						});
					} else {
						r.success = function () {
							store.load();
						};
					}
					delete r.dataHandler;

					Scalr.Request(r);
				}
			}
		});

		this.selectedMenu.doAutoRender();*/
	},

	bindComponent: function () {
		this.callParent(arguments);

		/*this.view.on('refresh', function () {
			this.toggleUiHeader(false);
		}, this);*/
	},

	getHeaderConfig: function() {
		var c = this.callParent();
		c.width = 140;
		c.minWidth = c.width;
		c.headerId = 'scalrSelectedModelCheckbox';
		c.text = '';
		return c;
	},

	getVisibility: function (record) {
		return true;
	},


	// getVisibility (record) for menu
	// getOptionVisibility

	renderer: function (value, meta, record, rowIndex, colIndex) {
		//if (this.headerCt.getHeaderAtIndex(colIndex).getVisibility(record))
		return '<div class="x-grid-row-options"><div class="x-grid-row-options-checkbox x-grid-row-checker"></div>Actions<div class="x-grid-row-options-trigger"></div></div>';
	},

/*	renderer: function(value, metaData, record, rowIndex, colIndex, store, view) {
		metaData.tdCls = Ext.baseCSSPrefix + 'grid-cell-special';
		metaData.style = 'margin-left: 5px';

		if (this.getVisibility(record))
			return '<div class="' + Ext.baseCSSPrefix + 'grid-row-checker">&#160;</div>';
	},*/

	// don't check unavailable items
	selectAll: function(suppressEvent) {
		var me = this,
			selections = [],
			i = 0,
			len,
			start = me.getSelection().length;

		Ext.each(me.store.getRange(), function (record) {
			if (this.getVisibility(record))
				selections.push(record);
		}, this);

		len = selections.length;

		me.bulkChange = true;
		for (; i < len; i++) {
			me.doSelect(selections[i], true, suppressEvent);
		}
		delete me.bulkChange;
		// fire selection change only if the number of selections differs
		me.maybeFireSelectionChange(me.getSelection().length !== start);
	},

	onSelectChange: function() {
		this.callParent(arguments);

		// check to see if all records are selected
		var me = this, selections = [];
		Ext.each(me.store.getRange(), function (record) {
			if (this.getVisibility(record))
				selections.push(record);
		}, this);

		var hdSelectStatus = this.selected.getCount() === selections.length;
		this.toggleUiHeader(hdSelectStatus);
	},

	/*onHeaderClick: function(headerCt, header, e) {
		if (header.isCheckerHd && !e.getTarget('span.x-column-header-text', 1)) {
			// show menu only if it's not span
			var btnEl = Ext.get(e.getTarget('div.x-column-header-checkbox')), xy = btnEl.getXY();

			if (this.selected.length)
				this.selectedMenu.el.unmask();
			else
				this.selectedMenu.el.mask();

			this.selectedMenu.show();
			this.selectedMenu.setPosition([xy[0] - (this.selectedMenu.getWidth()  - btnEl.getWidth()), xy[1] + btnEl.getHeight() + 1]);
			e.stopEvent();
		} else {
			this.callParent(arguments);
		}
	},*/

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
	}
});

/**
 * @class Ext.ux.RowExpander
 * @extends Ext.AbstractPlugin
 * Plugin (ptype = 'rowexpander') that adds the ability to have a Column in a grid which enables
 * a second row body which expands/contracts.  The expand/contract behavior is configurable to react
 * on clicking of the column, double click of the row, and/or hitting enter while a row is selected.
 *
 * @ptype rowexpander
 */
Ext.define('Ext.ux.RowExpander', {
	extend: 'Ext.AbstractPlugin',

	requires: [
		'Ext.grid.feature.RowBody',
		'Ext.grid.feature.RowWrap'
	],

	alias: 'plugin.rowexpander',

	rowBodyTpl: null,

	/**
	 * @cfg {Boolean} expandOnEnter
	 * <tt>true</tt> to toggle selected row(s) between expanded/collapsed when the enter
	 * key is pressed (defaults to <tt>true</tt>).
	 */
	expandOnEnter: true,

	/**
	 * @cfg {Boolean} expandOnDblClick
	 * <tt>true</tt> to toggle a row between expanded/collapsed when double clicked
	 * (defaults to <tt>true</tt>).
	 */
	expandOnDblClick: false,

	/**
	 * @cfg {Boolean} selectRowOnExpand
	 * <tt>true</tt> to select a row when clicking on the expander icon
	 * (defaults to <tt>false</tt>).
	 */
	selectRowOnExpand: false,

	rowBodyTrSelector: '.x-grid-rowbody-tr',
	rowBodyHiddenCls: 'x-grid-row-body-hidden',
	rowCollapsedCls: 'x-grid-row-collapsed',

	renderer: function(value, metadata, record, rowIdx, colIdx) {
		if (colIdx === 0) {
			metadata.tdCls = 'x-grid-td-expander';
		}
		return '<div class="x-grid-row-expander">&#160;</div>';
	},

	/**
	 * @event expandbody
	 * <b<Fired through the grid's View</b>
	 * @param {HTMLElement} rowNode The &lt;tr> element which owns the expanded row.
	 * @param {Ext.data.Model} record The record providing the data.
	 * @param {HTMLElement} expandRow The &lt;tr> element containing the expanded data.
	 */
	/**
	 * @event collapsebody
	 * <b<Fired through the grid's View.</b>
	 * @param {HTMLElement} rowNode The &lt;tr> element which owns the expanded row.
	 * @param {Ext.data.Model} record The record providing the data.
	 * @param {HTMLElement} expandRow The &lt;tr> element containing the expanded data.
	 */

	constructor: function() {
		this.callParent(arguments);
		var grid = this.getCmp();
		this.recordsExpanded = {};
		// <debug>
		if (!this.rowBodyTpl) {
			Ext.Error.raise("The 'rowBodyTpl' config is required and is not defined.");
		}
		// </debug>
		// TODO: if XTemplate/Template receives a template as an arg, should
		// just return it back!
		var rowBodyTpl = Ext.create('Ext.XTemplate', this.rowBodyTpl),
			features = [{
				ftype: 'rowbody',
				columnId: this.getHeaderId(),
				recordsExpanded: this.recordsExpanded,
				rowBodyHiddenCls: this.rowBodyHiddenCls,
				rowCollapsedCls: this.rowCollapsedCls,
				getAdditionalData: this.getRowBodyFeatureData,
				getRowBodyContents: function(data) {
					return rowBodyTpl.applyTemplate(data);
				}
			},{
				ftype: 'rowwrap'
			}];

		if (grid.features) {
			grid.features = features.concat(grid.features);
		} else {
			grid.features = features;
		}

		// NOTE: features have to be added before init (before Table.initComponent)
	},

	init: function(grid) {
		this.callParent(arguments);
		this.grid = grid;
		// Columns have to be added in init (after columns has been used to create the
		// headerCt). Otherwise, shared column configs get corrupted, e.g., if put in the
		// prototype.
		this.addExpander();
		grid.on('render', this.bindView, this, {single: true});
		grid.on('reconfigure', this.onReconfigure, this);
	},

	onReconfigure: function(){
		this.addExpander();
	},

	addExpander: function(){
		this.grid.headerCt.insert(0, this.getHeaderConfig());
		this.grid.headerCt.items.getAt(1).addCls('x-grid-header-special-after');
	},

	getHeaderId: function() {
		if (!this.headerId) {
			this.headerId = Ext.id();
		}
		return this.headerId;
	},

	getRowBodyFeatureData: function(data, idx, record, orig) {
		var o = Ext.grid.feature.RowBody.prototype.getAdditionalData.apply(this, arguments),
			id = this.columnId;
		o.rowBodyColspan = o.rowBodyColspan - 1;
		o.rowBody = this.getRowBodyContents(data);
		o.rowCls = this.recordsExpanded[record.internalId] ? '' : this.rowCollapsedCls;
		o.rowBodyCls = this.recordsExpanded[record.internalId] ? '' : this.rowBodyHiddenCls;
		o[id + '-tdAttr'] = ' valign="top" rowspan="2" ';
		if (orig[id+'-tdAttr']) {
			o[id+'-tdAttr'] += orig[id+'-tdAttr'];
		}
		return o;
	},

	bindView: function() {
		var view = this.getCmp().getView(),
			viewEl;

		if (!view.rendered) {
			view.on('render', this.bindView, this, {single: true});
		} else {
			viewEl = view.getEl();
			if (this.expandOnEnter) {
				this.keyNav = Ext.create('Ext.KeyNav', viewEl, {
					'enter' : this.onEnter,
					scope: this
				});
			}
			if (this.expandOnDblClick) {
				view.on('itemdblclick', this.onDblClick, this);
			}
			this.view = view;
		}
	},

	onEnter: function(e) {
		var view = this.view,
			ds   = view.store,
			sm   = view.getSelectionModel(),
			sels = sm.getSelection(),
			ln   = sels.length,
			i = 0,
			rowIdx;

		for (; i < ln; i++) {
			rowIdx = ds.indexOf(sels[i]);
			this.toggleRow(rowIdx);
		}
	},

	toggleRow: function(rowIdx) {
		var view = this.view,
			rowNode = view.getNode(rowIdx),
			row = Ext.get(rowNode),
			nextBd = Ext.get(row).down(this.rowBodyTrSelector),
			record = view.getRecord(rowNode),
			grid = this.getCmp();

		if (row.hasCls(this.rowCollapsedCls)) {
			row.removeCls(this.rowCollapsedCls);
			nextBd.removeCls(this.rowBodyHiddenCls);
			this.recordsExpanded[record.internalId] = true;
			view.refreshSize();
			view.fireEvent('expandbody', rowNode, record, nextBd.dom);
		} else {
			row.addCls(this.rowCollapsedCls);
			nextBd.addCls(this.rowBodyHiddenCls);
			this.recordsExpanded[record.internalId] = false;
			view.refreshSize();
			view.fireEvent('collapsebody', rowNode, record, nextBd.dom);
		}
	},

	onDblClick: function(view, cell, rowIdx, cellIndex, e) {
		this.toggleRow(rowIdx);
	},

	getHeaderConfig: function() {
		var me                = this,
			toggleRow         = Ext.Function.bind(me.toggleRow, me),
			selectRowOnExpand = me.selectRowOnExpand;

		return {
			id: this.getHeaderId(),
			width: 35,
			sortable: false,
			resizable: false,
			draggable: false,
			hideable: false,
			menuDisabled: true,
			cls: Ext.baseCSSPrefix + 'grid-header-special',
			renderer: function(value, metadata) {
				metadata.tdCls = Ext.baseCSSPrefix + 'grid-cell-special';

				return '<div class="' + Ext.baseCSSPrefix + 'grid-row-expander">&#160;</div>';
			},
			processEvent: function(type, view, cell, recordIndex, cellIndex, e) {
				if (type == "mousedown" && e.getTarget('.x-grid-row-expander')) {
					var row = e.getTarget('.x-grid-row');
					toggleRow(row);
					return selectRowOnExpand;
				}
			}
		};
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
			destroy: me.onContainerDestroy
		});
	},

	/**
	 * @private Clear up on Container destroy
	 */
	onContainerDestroy: function() {
		if (this.dd) {
			this.dd.unreg();
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
		dd.startAttr = names.left;
		dd.endAttr = names.right;
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
			cmpBox = cmpEl.getPageBox();

			// Last tracked start position
			me.lastPos = cmpBox[this.startAttr];

			// Calculate constraints depending upon orientation
			// Calculate offset from mouse to dragEl position
			containerBox = container.el.getPageBox();
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
			dragBox = Ext.fly(dragEl).getPageBox(),
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
				targetBox = targetEl.getPageBox();
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

/*
 * Toolbar fields
 */
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

Ext.define('Scalr.ui.ToolbarFieldFilter', {
	extend: 'Ext.form.field.Trigger',
	alias: 'widget.tbfilterfield',

	hideLabel: true,
	width: 250,
	cls: 'x-form-trigger-filter',
	trigger1Cls: 'x-form-clear-trigger',
	trigger2Cls: 'x-form-search-trigger',
	triggerWidth: 24,

	hasSearch: false,
	paramName: 'query',
	prevValue: '',
	emptyText: 'Search',

	validationEvent: false,
	validateOnBlur: false,

	initComponent: function () {
		if (this.store.proxy.extraParams['query'] != '')
			this.value = this.store.proxy.extraParams['query'];

		this.callParent(arguments);

		this.on('specialkey', function(f, e) {
			if(e.getKey() == e.ENTER){
				e.stopEvent();
				(this.hasSearch && this.getRawValue() == this.prevValue )? this.onTrigger1Click() : this.onTrigger2Click();
			}
		}, this);
	},

	initTrigger: function () {
		var me = this,
			triggerCell = me.triggerCell;

		me.callParent(arguments);

		triggerCell.elements[0].setVisibilityMode(Ext.core.Element.DISPLAY);
		triggerCell.elements[0].hide();
		me.updateLayout();
	},

	setValue: function (v) {
		this.callParent(arguments);

		if (v && v.length) {
			this.prevValue = v;
			this.store.proxy.extraParams[this.paramName] = v;
			this.hasSearch = true;
			if (this.rendered) {
				this.triggerCell.elements[0].show();
				this.updateLayout();
			}
		} else {
			this.prevValue = '';
		}
	},

	onTrigger1Click: function() {
		if (this.hasSearch) {
			this.setValue();
			this.store.proxy.extraParams[this.paramName] = '';
			this.store.load();
			this.triggerCell.elements[0].hide();
			this.updateLayout();
			this.hasSearch = false;
		}
	},

	onTrigger2Click : function() {
		var v = this.getRawValue();
		if (v.length < 1){
			this.onTrigger1Click();
			return;
		}
		this.prevValue = v;
		this.store.proxy.extraParams[this.paramName] = v;
		this.store.loadPage(1);
		this.hasSearch = true;
		this.triggerCell.elements[0].show();
		this.updateLayout();
	}
});

Ext.define('Scalr.ui.TreepanelFieldFilter', {
	extend: 'Ext.form.field.Trigger',
	alias: 'widget.treefilterfield',

	hideLabel: true,
	width: 250,
	cls: 'x-form-trigger-filter',
	trigger1Cls: 'x-form-clear-trigger',
	trigger2Cls: 'x-form-search-trigger',
	triggerWidth: 24,

	hasSearch: false,
	prevValue: '',
	emptyText: 'Search',

	validationEvent: false,
	validateOnBlur: false,

	initComponent: function () {
		this.callParent(arguments);

		this.on('specialkey', function(f, e) {
			if(e.getKey() == e.ENTER){
				e.stopEvent();
				(this.hasSearch && this.getRawValue() == this.prevValue )? this.onTrigger1Click() : this.onTrigger2Click();
			}
		}, this);
	},

	initTrigger: function () {
		var me = this,
			triggerCell = me.triggerCell;

		me.callParent(arguments);

		triggerCell.elements[0].setVisibilityMode(Ext.core.Element.DISPLAY);
		triggerCell.elements[0].hide();
		me.updateLayout();
	},

	setValue: function (v) {
		this.callParent(arguments);

		if (v && v.length) {
			this.prevValue = v;
			this.store.proxy.extraParams[this.paramName] = v;
			this.hasSearch = true;
			if (this.rendered) {
				this.triggerCell.elements[0].show();
				this.updateLayout();
			}
		} else {
			this.prevValue = '';
		}
	},

	onTrigger1Click: function() {
		if (this.hasSearch) {
			this.setValue();
			var trigger = this;
			var treepanel = trigger.up('panel').down('treepanel');
			Ext.each (treepanel.getRootNode().childNodes, function(farmItem) {
				farmItem.cascadeBy(function(){
					var el = Ext.get(treepanel.getView().getNodeByRecord(this));
					el.setVisibilityMode(Ext.Element.DISPLAY);
					el.setVisible(true);
				});
			});
			this.triggerCell.elements[0].hide();
			this.updateLayout();
			this.hasSearch = false;
		}
	},

	onTrigger2Click : function() {
		var v = this.getRawValue();
		if (v.length < 1){
			this.onTrigger1Click();
			return;
		}
		this.prevValue = v;
		var trigger = this;
		var treepanel = trigger.up('panel').down('treepanel');
		Ext.each (treepanel.getRootNode().childNodes, function(farmItem) {
			farmItem.cascadeBy(function(){
				var el = Ext.get(treepanel.getView().getNodeByRecord(this));
				el.setVisibilityMode(Ext.Element.DISPLAY);
				if(this.get('text').search(trigger.getRawValue()) != -1 || trigger.getRawValue() == '')
					el.setVisible(true);
				else
					el.setVisible(false);
			});
		});
		this.hasSearch = true;
		this.triggerCell.elements[0].show();
		this.updateLayout();
	}
});

Ext.define('Scalr.ui.ToolbarFieldSwitch', {
	extend: 'Ext.toolbar.TextItem',
	alias: 'widget.tbswitchfield',

	cls: 'scalr-ui-btn-icon-viewswitch',
	text: '<div class="grid"></div><div class="view"></div>',
	
	getState: function () {
		return {
			switchValue: this.switchValue
		};
	},
	
	changeSwitch: function (value) {
		this.switchValue = value;
		this.onStateChange();
	},
	
	onRender: function () {
		this.callParent(arguments);
		
		if (this.switchValue == 'view')
			this.addCls('scalr-ui-btn-icon-viewswitch-view');
		else
			this.addCls('scalr-ui-btn-icon-viewswitch-grid');
		
		this.el.down('.grid').on('click', function () {
			this.removeCls('scalr-ui-btn-icon-viewswitch-view');
			this.addCls('scalr-ui-btn-icon-viewswitch-grid');
			this.changeSwitch('grid');
		}, this);
		
		this.el.down('.view').on('click', function () {
			this.removeCls('scalr-ui-btn-icon-viewswitch-grid');
			this.addCls('scalr-ui-btn-icon-viewswitch-view');
			this.changeSwitch('view');
		}, this);
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
	},

	onDestroy: function() {
		var me = this;
		if (me.rendered) {
			Ext.ButtonToggleManager.unregister(me);
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
			farmField.columnWidth = 1/3;;
			farmRoleField.columnWidth = 1/3;
			serverField.columnWidth = 1/3;
		}
	},

	items: [{
		xtype: 'combo',
		hideLabel: true,
		name: 'farmId',
		store: {
			fields: [ 'id', 'name' ],
			proxy: 'object'
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

				if (!this.getValue()) {
					fieldset.down('[name="farmRoleId"]').hide();
					return;
				}

				var successHandler = function(data) {
					var field = fieldset.down('[name="farmRoleId"]');
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
						params: { farmId: me.getValue(), options: fieldset.params['options'].join(',') },
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
			fields: [ 'id', 'name', 'platform', 'role_id' ],
			proxy: 'object'
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
						field.store.load({ data: data['dataServers'] });

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
						params: { farmRoleId: me.getValue(), options: fieldset.params['options'].join(',') },
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
			fields: [ 'id', 'name' ],
			proxy: 'object'
		},
		valueField: 'id',
		displayField: 'name',
		margin: '0 0 0 5',
		editable: false,
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

Ext.define('Scalr.ui.FormFieldTooltip', {
	extend: 'Ext.form.DisplayField',
	alias: 'widget.displayinfofield',
	value: '',
	info: '',
	initComponent: function () {
		// should use value for message
		this.info = this.value || this.info;
		this.value = '<img class="tipHelp" src="/ui2/images/icons/info_icon_16x16.png" style="cursor: help; height: 16px;">';

		this.callParent(arguments);
	},
	listeners: {
		boxready: function () {
			Ext.create('Ext.tip.ToolTip', {
				target: this.el.down('img.tipHelp'),
				dismissDelay: 0,
				html: this.info
			});
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
		var columns = this.up('panel').columns;
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
		var autorefreshFieldset = new Ext.form.FieldSet({
			title: 'Autorefresh',
			items: {
				xtype: 'checkbox',
				boxLabel: 'Enable',
				inputValue: 60,
				checked: this.up('panel').down('scalrpagingtoolbar').autoRefresh,
				name: 'autoRefresh'
			}
		});
		return [columnsFieldset, autorefreshFieldset];
	},

	handler: function () {
		var me = this;
		var columns = me.up('panel').columns;
		Scalr.Confirm({
			title: 'Grid settings',
			form: me.gridSettingsForm(),
			success: function (data) {
				for(var i in columns) {
					if(data[columns[i].text])
						columns[i].show();
					if(!data[columns[i].text] && columns[i].hideable)
						columns[i].hide();
				}
				if(data['autoRefresh'])
					this.up('panel').down('scalrpagingtoolbar').checkRefreshHandler({'autoRefresh': data['autoRefresh']}, true);
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

Ext.define('Scalr.ui.MenuItemTop', {
	extend: 'Ext.menu.Item',
	alias: 'widget.menuitemtop',

	renderTpl: [
		'<div id="{id}-itemEl" class="' + Ext.baseCSSPrefix + 'menu-item-link">',
			'<img id="{id}-iconEl" src="{icon}" class="' + Ext.baseCSSPrefix + 'menu-item-icon {iconCls}" />',
			'<tpl if="links"><div class="x-menu-item-links">',
				'<tpl for="links"><a href="{href}" target="_self" class="{cls}">{text}</a></tpl>',
			'</div></tpl>',
			'<img id="{id}-arrowEl" src="{blank}" class="{arrowCls}" />',
			'<a id="{id}-textEl" class="' + Ext.baseCSSPrefix + 'menu-item-text" <tpl if="arrowCls">style="margin-right: 17px;" </tpl><tpl if="href">href="{href}" </tpl> ><span>{text}</span></a>',
			'<div style="clear: both"></div>',
		'</div>',
	],

	beforeRender: function () {
		var me = this;
		me.callParent();

		if (me.href)
			Ext.applyIf(me.renderData, {
				href: me.href
			});

		if (me.links)
			Ext.applyIf(me.renderData, {
				links: me.links
			});
	},

	onClick: function(e) {
		var me = this;

		//if (!me.href) {
		//	e.stopEvent();
		//}

		if (me.disabled) {
			return;
		}

		if (me.hideOnClick) {
			me.deferHideParentMenusTimer = Ext.defer(me.deferHideParentMenus, me.clickHideDelay, me);
		}

		Ext.callback(me.handler, me.scope || me, [me, e]);
		me.fireEvent('click', me, e);

		if (!me.hideOnClick) {
			me.focus();
		}
	}
});

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

	init: function (client) {
		var me = this;
		client.on('afterrender', function() {
			me.panelContainer = Ext.DomHelper.insertAfter(client.el.down('tbody'), {style: {height: '32px'}}, true);
			var addmask = Ext.DomHelper.append(me.panelContainer,
				'<div style="position: absolute; width: 95%; height: 31px;">' +
					'<div class="x-form-addfield-plus"></div>' +
					'</div>'
				, true);
			addmask.down('div.x-form-addfield-plus').on('click', me.handler, client);
		}, client);
	},
	hide: function () {
		if (this.panelContainer)
			this.panelContainer.remove();
	}
});

Ext.define('Scalr.ui.FormTextCodeMirror', {
	extend: 'Ext.form.field.Base',
	alias: 'widget.codemirror',
	
	readOnly: false,
	addResizeable: false,
	
	fieldSubTpl: '<div id="{id}"></div>',
	enterIsSpecial: false,
	
	setMode: function (cm) {
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

			case 'python':
				cm.setOption('mode', 'python');
				break;

			default:
				cm.setOption('mode', 'text/plain');
				break;
		}
	},
	afterRender: function () {
		this.callParent(arguments);
		this.codeMirror = CodeMirror(this.inputEl, {
			value: this.getRawValue(),
			readOnly: this.readOnly,
			onChange: Ext.Function.bind(function (editor, changes) {
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
			}, this)
		});

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
						this.target.up('.x-panel-body-frame').dom.scrollTop = 99999;
					}
				}
			});
		}
		
	},
	getRawValue: function () {
		var me = this,
			v = (me.codeMirror ? me.codeMirror.getValue() : Ext.value(me.rawValue, ''));
		me.rawValue = v;
		return v;
	},
	setRawValue: function (value) {
		var me = this;
		value = Ext.value(me.transformRawValue(value), '');
		me.rawValue = value;

		return value;
	},
	setValue: function(value) {
		var me = this;
		me.setRawValue(me.valueToRaw(value));

		if (me.codeMirror)
			me.codeMirror.setValue(value);

		return me.mixins.field.setValue.call(me, value);
	}
});

Ext.define('Scalr.ui.FormFieldPassword', {
    extend: 'Ext.form.field.Text',
	alias: 'widget.passwordfield',
	inputType:'password',
	
	allowBlank: false,

	placeholder: '******',
	
	onBeforeRender: function() {
		this.callParent(arguments);
		if (this.originalValue === true) {
			this.setValue(this.placeholder);
		}
	},
	
	onFocus: function() {
		this.callParent(arguments);
		if (this.originalValue === true && this.getValue() == this.placeholder) {
			this.setValue('');
			this.clearInvalid();
		}
	},

	onBlur: function() {
		this.callParent(arguments);
		if (this.originalValue === true && this.getValue() == '') {
			this.setValue(this.placeholder);
		}
	},
	
	initValue: function() {
		this.originalValue = this.value;
		if (this.value === true) {
			this.setValue(this.placeholder);
		}
		
	},
    getSubmitValue: function() {
		if (this.originalValue && this.getValue() == this.placeholder) {
			return null;
		} else {
			return this.callParent(arguments);
		}
    }
});

Ext.define('Scalr.ui.PanelScrollFixPlugin', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.panelscrollfix',
	
    disabled: false,
	client: null,
	
	lastScrollTop: null,
	lastScrollHeight: null,
	
	init: function(client) {
		var me = this;
		me.client = client;
		client.fixScrollTop = function(){
			me.fixScrollTop();
		};
		client.on('render', function(){
			this.items.each(function(i){
				if (i.collapsible) {
					i.on('beforeexpand', function(){
						me.fixScrollTop(true);
					});
					i.on('beforecollapse', function(){
						me.fixScrollTop(true);
					});
				}
			});
		});
	},
	
	fixScrollTop: function(exact) {
		var me = this;
		me.saveScrollPosition();
		me.client.on('afterlayout', function() {
			me.restoreScrollPosition(exact);
		}, me.client, { single: true });
	},
	
	saveScrollPosition: function() {
		this.lastScrollTop = this.client.body.getScroll().top;
		this.lastScrollHeight = this.client.body.getAttribute('scrollHeight');
	},
	
	restoreScrollPosition: function(exact) {
		var scrollHeight = this.client.body.getAttribute('scrollHeight');
		if (this.lastScrollTop !== null) {
			this.client.body.scrollTo( 'top', exact?this.lastScrollTop:(this.lastScrollTop + scrollHeight - this.lastScrollHeight));
		}
	}
});


// show file name in File Field
Ext.override(Ext.form.field.File, {
	onRender: function() {
		var me = this;

		me.callParent(arguments);
		me.anchor = '-30';
		me.browseButtonWrap.applyStyles('padding-left: 30px;');
	},

	buttonText: '',
	setValue: function(value) {
		Ext.form.field.File.superclass.setValue.call(this, value);
	}
});

// submit form on enter on any fields in form
Ext.override(Ext.form.field.Base, {
	initComponent: function() {
		this.callOverridden();

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

//scroll to error fields
Ext.override(Ext.form.Basic, {
	initialize: function() {
		this.callOverridden();
		this.on('actionfailed', function (basicForm) {
			basicForm.getFields().each(function (field) {
				if (field.getActiveError()) {
					field.el.scrollIntoView(basicForm.owner.body);
					return false;
				}
			});
		});
	}
});

Ext.override(Ext.form.action.Action, {
	submitEmptyText: false
});

// save & restore all sort params
Ext.override(Ext.panel.Table, {
	getState: function() {
		var state = this.callParent(arguments), me = this, sorters = me.store.sorters;

		if (sorters) {
			var s = [];
			sorters.each(function (item) {
				s.push({
					direction: item.direction,
					property: item.property,
					root: item.root
				});
			});

			state = me.addPropertyToState(state, 'sort', s);
		}
		state = this.addPropertyToState(state, 'autoRefresh', this.autoRefresh);

		return state;
	},
	applyState: function(state) {
		var sorter = state.sort, me = this, store = me.store;
		if (sorter) {
			store.sort(sorter, null, false);
			delete state.sort;
		}

		this.callParent(arguments);
	}
});

Ext.override(Ext.view.Table, {
	enableTextSelection: true
});

Ext.override(Ext.view.AbstractView, {
	loadingText: 'Loading data...',
	emptyTextPrepare: true,
	//disableSelection: true,
	// TODO: apply and check errors (role/edit for example, selected plugin for grid

	initComponent: function() {
		this.callParent(arguments);
		if (this.emptyTextPrepare)
			this.emptyText = '<div class="x-grid-empty">' + this.emptyText + '</div>';
	}
});

Ext.override(Ext.view.BoundList, {
	afterRender: function() {
		this.callParent(arguments);

		if (this.minWidth)
			this.el.applyStyles('min-width: ' + this.minWidth + 'px');
	}
})

Ext.override(Ext.form.field.ComboBox, {
	matchFieldWidth: false,
	queryReset: false, // set true to refresh store forcibly
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

	onBeforeLoad: function() {
		if (this.queryMode == 'remote')
			this.addCls('x-field-trigger-loading');

		this.removeCls('x-field-trigger-error');
		if (this.rendered)
			this.triggerEl.elements[0].dom.title = '';
	},
	onLoad: function(store, records, successful) {
		if (this.queryMode == 'remote')
			this.removeCls('x-field-trigger-loading');

		if (!successful && store.proxy.reader.rawData && store.proxy.reader.rawData.errorMessage) {
			this.addCls('x-field-trigger-error');
			this.triggerEl.elements[0].dom.title = 'Error loading data: ' + store.proxy.reader.rawData.errorMessage + "\nClick to try once more.";
			this.queryReset = true;
			this.collapse();
		}
	},
	onException: function() {
		if (this.queryMode == 'remote')
			this.removeCls('x-field-trigger-loading');
	},
	// based onTriggerClick
	prefetch: function() {
		var me = this;
		if (!me.readOnly && !me.disabled) {
			if (me.triggerAction === 'all') {
				me.doQuery(me.allQuery, true);
			} else {
				me.doQuery(me.getRawValue(), false, true);
			}
			me.collapse();
		}
	},

	doQuery: function() {
		if (this.queryReset && this.queryCaching)
			this.queryCaching = false;

		var result = this.callParent(arguments);

		if (this.queryReset) {
			this.queryReset = false;
			this.queryCaching = true
		}

		return result;
	},

	defaultListConfig: {
		shadow: false // disable shadow in combobox
	},
	shadow: false,
	pickerOffset: [0, 2]
});

Ext.override(Ext.form.field.Date, {
	pickerOffset: [0, 2]
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

Ext.override(Ext.tip.Tip, {
	shadow: false
});

Ext.override(Ext.panel.Tool, {
	width: 21,
	height: 16
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

// fieldset's title is not legend (simple div)
Ext.override(Ext.form.FieldSet, {
	createLegendCt: function() {
		var me = this,
			items = [],
			legend = {
				xtype: 'container',
				baseCls: me.baseCls + '-header',
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

		// Title
		items.push(me.createTitleCmp());

		return legend;
	}
});

Ext.override(Ext.menu.Menu, {
	childMenuOffset: [2, 0],
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

Ext.override(Ext.menu.Item, {
	renderTpl: [
		'<tpl if="plain">',
			'{text}',
		'<tpl else>',
			'<a id="{id}-itemEl" class="' + Ext.baseCSSPrefix + 'menu-item-link" href="{href}" <tpl if="hrefTarget">target="{hrefTarget}"</tpl> hidefocus="true" unselectable="on">',
				'<img id="{id}-iconEl" src="{icon}" class="' + Ext.baseCSSPrefix + 'menu-item-icon {iconCls}" />',
				'<span id="{id}-textEl" class="' + Ext.baseCSSPrefix + 'menu-item-text" <tpl if="arrowCls">style="margin-right: 17px;"</tpl> >{text}</span>',
				'<img id="{id}-arrowEl" src="{blank}" class="{arrowCls}" />',
				'<div style="clear: both"></div>',
			'</a>',
		'</tpl>'
	]
});

// fix from 4.1.2
// TODO: remove after update
Ext.view.Table.override({
	onUpdate: function(store, record) {
		var index = store.indexOf(record);
		this.callParent(arguments);

		if (this.getSelectionModel().isSelected(record))
			Ext.fly(this.getNodeByRecord(record)).addCls('x-grid-row-selected');

		this.doStripeRows(index, index);
	}
});

Ext.override(Ext.form.FieldSet, {
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
	}
});

// remove strip div
Ext.override(Ext.tab.Bar, {
	afterRender: function() {
		this.callParent(arguments);
		this.strip.applyStyles('height: 0px; display: none;')
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

Ext.Error.handle = function(err) {
	var err = new Ext.Error(err);

	Scalr.utils.PostError({
		message: err.toString(),
		url: document.location.href
	});

	return true;
};

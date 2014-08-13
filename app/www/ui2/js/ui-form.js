Ext.define('Scalr.ui.FormFilterField', {
	extend: 'Ext.form.field.Picker',
	alias: 'widget.filterfield',

	hideTrigger: true,
	separatedParams: [],
	hideFilterIcon: false,
	hideTriggerButton: false,
	hideSearchButton: false,
	cls: 'x-filterfield',
    filterId: 'filterfield',
    width: 240, //fast solution: missing width cause overlapping with the next control of the toolbar

	initComponent: function() {
		var me = this;
		me.callParent(arguments);

		if (!me.form && !me.menu) {
			me.hideTriggerButton = true;
		} else {
			me.on({
				expand: function() {
                    if (me.form) {
                        var picker = this.getPicker(), values = this.getParseValue();
                        picker.getForm().reset();
                        picker.getForm().setValues(values);
                    }
					this.triggerButton.addCls('x-btn-default-small-pressed');
				},
				collapse: function() {
                    if (me.form) {
                        var picker = this.getPicker(), values = this.getParseValue(true);
                        Ext.Object.merge(values, picker.getForm().getValues());
                        this.setParseValue(values);
                    }
					this.triggerButton.removeCls('x-btn-default-small-pressed');
				}
			});
		}

		// in createPicker we re-enter list of params, find better solution
        if (this.form && this.form.items) {
			Ext.each(this.form.items, function(item) {
				if (item.name)
					me.separatedParams.push(item.name);
			});
		}

		if (this.store && (this.store.remoteSort || this.forceRemoteSearch)) {
			this.emptyText = 'Search';

			if (this.store.proxy.extraParams['query'] != '')
				this.value = this.store.proxy.extraParams['query'];
		} else {
			this.emptyText = this.emptyText || 'Filter';
			this.hideSearchButton = true;
			if (! this.hideFilterIcon)
				this.fieldCls = this.fieldCls + ' x-form-field-livesearch';
		}

		if (Ext.isFunction(this.handler)) {
            // TODO: remove or make adjustable
			this.on('change', this.handler, this, { buffer: 300 });
		}
	},

	clearFilter: function() {
		this.collapse();
		this.reset();
        this.applyFilter(this, this.getValue());
		if (! this.hideSearchButton)
			this.storeHandler();
		this.focus();
	},

    clearParams: function (params) {
        var me = this;
        var store = me.store;

        if (params && !store.isLoading()) {
            var storeExtraParams = store.proxy.extraParams;

            Ext.Object.each(params, function (param) {
                delete storeExtraParams[param];
            });
        }
    },

	applyFilter: function(field, value) {
		var me = this;

		value = Ext.String.trim(value);

		if (this.hideSearchButton)
			me.clearButton[value != '' ? 'show' : 'hide' ]();

		if (this.store !== undefined && (me.filterFn || me.filterFields)) {
            var filters = [];
            this.store.filters.each(function(filter){
                if (filter.id !== me.filterId) {
                    filters.push(filter);
                }
            });
            this.store.clearFilter();
			var filterFn = function(record) {
				var result = false,
					r = new RegExp(Ext.String.escapeRegex(value), 'i');
				for (var i = 0, length = me.filterFields.length; i < length; i++) {
					var fieldValue = Ext.isFunction(me.filterFields[i]) ? me.filterFields[i](record) : record.get(me.filterFields[i]);
					result = (fieldValue+'').match(r);
					if (result) {
						break;
					}
				}
				return result;
			};

			if (value != '') {
				filters.push({
                    id: this.filterId,
					filterFn: me.filterFn || filterFn
				});
            }
            this.fireEvent('beforefilter');
            this.store.filter(filters);
			this.fireEvent('afterfilter');
		}
	},

	onRender: function() {
		this.callParent(arguments);

		this.clearButton = this.bodyEl.down('tr').createChild({
			tag: 'td',
			width: 26,
			html: '<div class="x-filterfield-reset"></div>'
		});
        this.clearButton.setVisibilityMode(Ext.dom.AbstractElement.DISPLAY);
		this.clearButton[ this.getValue() != '' ? 'show' : 'hide' ]();
		this.applyFilter(this, this.getValue());
		this.clearButton.on('click', this.clearFilter, this);
		this.on('change', this.applyFilter, this, { buffer: 300 });

		this.on('specialkey', function(f, e) {
			if(e.getKey() == e.ESC){
				e.stopEvent();
				this.clearFilter();
			}
		}, this);

		if (! this.hideTriggerButton) {
			this.triggerButton = this.bodyEl.down('tr').createChild({
				tag: 'td',
				width: 32,
				html: '<div class="x-filterfield-trigger x-btn-default-small"><div class="x-filterfield-trigger-inner"></div></div>'
			}).down('div');
            this.triggerButton.addClsOnOver('x-btn-default-small-over');
			this.triggerButton.on('click', this.onTriggerClick, this);

			if (this.hideSearchButton) {
				this.triggerButton.addCls('x-filterfield-trigger-alone');
			}
		}

		if (! this.hideSearchButton) {
			this.searchButton = this.bodyEl.up('tr').createChild({
				tag: 'td',
				width: this.hideTriggerButton ? 30 : 42,
				html: '<div class="x-filterfield-btn x-btn-default-small"><div class="x-filterfield-btn-inner"></div></div>'
			}).down('div');
            this.searchButton.addClsOnOver('x-btn-default-small-over');
            this.searchButton.addClsOnClick('x-btn-default-small-pressed');
			this.searchButton.on('click', this.storeHandler, this);
			this.on('specialkey', function(f, e) {
				if(e.getKey() == e.ENTER){
					e.stopEvent();
					this.storeHandler();
				}
			}, this);
			this.triggerWrap.applyStyles('border-radius: 3px 0 0 3px');
			if (this.hideTriggerButton) {
				this.searchButton.addCls('x-filterfield-btn-alone');
			}
		}
	},

	createPicker: function() {
        var me = this;
        if (me.form) {
            var formDefaults = {
                    // TODO: move to class, remove bodyCls
                    style: 'margin-top:1px',
                    cls: 'x-panel-shadow',
                    fieldDefaults: {
                        anchor: '100%'
                    },
                    bodyCls: 'x-fieldset-separator-none',
                    focusOnToFront: false,
                    padding: 12,
                    pickerField: me,
                    floating: true,
                    hidden: true,
                    ownerCt: this.ownerCt
                };

            var form = Ext.create('Ext.form.Panel', Ext.apply(formDefaults, this.form));
            form.keepVisibleEls = [];
            me.separatedParams = []; // re-fill because of nested elements in form
            form.getForm().getFields().each(function() {
                if (this.name)
                    me.separatedParams.push(this.name);

                if (this instanceof Ext.form.field.Picker) {
                    this.on('expand', function(c) {
                        this.keepVisibleEls.push(c.getPicker().el);
                    }, form, { single: true });
                } else if (this.xtype == 'textfield') {
                    this.on('specialkey', function(f, e) {
                        if(e.getKey() == e.ENTER){
                            e.stopEvent();
                            me.collapse();
                            me.storeHandler();
                        }
                    });
                }
            });
            return form;
        } else if (me.menu) {
            var menu = Ext.widget(me.menu);
            menu.keepVisibleEls = [];
            menu.on('hide', function(c) {
                me.collapse();
            });
            return menu;
        }
	},

    getParseValue: function () {
        var me = this;

        me.clearParams(me.loadParams);
        me.clearParams(me.parsedParams);

        var filterValue = me.getValue();

        var getStringParams = function (filterValue) {
            var regex = /\(([^)]+)\)/g;
            var usefulParams = [];
            var found;
            var query = filterValue;

            while (found = regex.exec(filterValue)) {
                usefulParams.push(found[1]);

                query = query.replace(found[0], '');
            }

            return { params: usefulParams, query: query.trim().replace(/\s+/g, ' ') };
        };

        var getSeparatorPosition = function (string) {
            return string.indexOf(':');
        };

        var getParsedParams = function (stringParams) {
            var params = {};

            Ext.Array.each(stringParams, function (string) {
                var separator = getSeparatorPosition(string);
                var key = string.substring(0, separator).trim();

                params[key] = string.substring(separator + 1).trim();
            });

            return params;
        };

        var stringParams = getStringParams(filterValue);
        var parsedParams = getParsedParams(stringParams.params);
        parsedParams.query = stringParams.query;

        me.parsedParams = parsedParams;

        return parsedParams;
    },

	setParseValue: function(params) {
		var s = params['query'] || '';
		delete params['query'];
		for (var i in params) {
			if (params[i])
				s += ' (' + i + ':' + params[i] + ')';
		}

		this.setValue(s.trim());
	},

	isElNested: function(e) {
        var flag = false, i;
        for (i = 0; i < this.picker.keepVisibleEls.length; i++)
            flag = flag || e.within(this.picker.keepVisibleEls[i], false, true);

        return flag;
    },

    collapseIf: function(e) {
		var me = this;

		if (!me.isElNested(e) && !me.isDestroyed && !e.within(me.bodyEl, false, true) && !e.within(me.picker.el, false, true) && !me.isEventWithinPickerLoadMask(e)) {
			me.collapse();
		}
	},

    triggerBlur: function(e) {
        var picker = this.picker;

        Ext.form.field.Picker.superclass.triggerBlur.apply(this, arguments);

        if (picker && picker.isVisible() && !this.isElNested(e)) { // exclude nested picker elements
            picker.hide();
        }
    },

	storeHandler: function() {
		this.clearButton[this.getValue() != '' ? 'show' : 'hide' ]();

		for (var i = 0; i < this.separatedParams.length; i++) {
			delete this.store.proxy.extraParams[this.separatedParams[i]];
		}

		Ext.apply(this.store.proxy.extraParams, this.getParseValue());
        if (this.store.buffered) {
            this.store.removeAll();
        }
		this.store.load();
	},

    bindStore: function(store) {
        this.store = store;
        this.applyFilter(this, this.getValue());
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
					/* Changed */
					//me.addClsWithUI(me.pressedCls);
					/* End */
					me.doc.on('mouseup', me.onMouseUp, me);
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
		me.items.each(function(){
			if (me.rendered) {
				this.setDisabled(readOnly);
			}
		});
        me.fireEvent('writeablechange', me, readOnly);
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

Ext.define('Scalr.ui.FormFieldInfoTooltip', {
	extend: 'Ext.form.DisplayField',
	alias: 'widget.displayinfofield',
	initComponent: function () {
		// should use value for message
		var info = this.value || this.info;
		this.value = '<img class="tipHelp" src="/ui2/images/icons/info_icon_16x16.png" data-qtip=\'' + info + '\' style="cursor: help; height: 16px;">';

		this.callParent(arguments);
	},

    setInfo: function (text) {
        var me = this;

        me.setValue('<img class="tipHelp" src="/ui2/images/icons/info_icon_16x16.png" data-qtip=\'' + text + '\' style="cursor: help; height: 16px;">');
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

				if (!this.getValue() || this.getValue() == '0') {
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
	progressBarCls: 'x-form-progress-bar',
	warningPercentage: 60,
	alertPercentage: 80,
	warningCls: 'x-form-progress-bar-warning',
	alertCls: 'x-form-progress-bar-alert',

	valueField: 'value',
	emptyText: '',
	units: '%',

	setRawValue: function(value) {
		var me = this;
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
            'us-central1-a': {region: 'all', x: {common: 30, large: 46}, y: {common:32, large: 48}},
            'us-central1-b': {region: 'all', x: {common: 36, large: 56}, y: {common:30, large: 46}},
            'us-central2-a': {region: 'all', x: {common: 42, large: 66}, y: {common:34, large: 48}},
            'europe-west1-a': {region: 'all', x: {common: 95, large: 150}, y: {common:28, large: 38}},
            'europe-west1-b': {region: 'all', x: {common: 100, large: 160}, y: {common:26, large: 36}},
            'asia-east1-b': {region: 'all', x: {common: 166, large: 254}, y: {common:46, large: 64}},
            'asia-east1-a': {region: 'all', x: {common: 160, large: 246}, y: {common:54, large: 80}}
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
		'<div class="map map-{mapCls}" style="{mapStyle}"><div class="title"></div></div>'
	],
	renderSelectors: {
		titleEl: '.title',
		mapEl: '.map'
	},
    renderData: {},

	constructor: function(config) {
		this.callParent([config]);
		this.locations.rds = this.locations.ec2;
        this.locations.ecs = this.locations.openstack;
        this.locations.ecs_world = this.locations.openstack_world;
		this.settings[this.size].style = 'width:' + this.settings[this.size].size.width + 'px;';
		this.settings[this.size].style += 'height:' + this.settings[this.size].size.height + 'px;';
		this.mapSize = this.settings[this.size].size;
        this.renderData.mapStyle = this.settings[this.size].style;
        this.renderData.mapCls = this.settings[this.size].cls;
	},

	selectLocation: function(platform, selectedLocations, allLocations, map){
        var me = this,
            locationFound = false,
            platformMap = platform !== 'idcf' && platform !== 'ecs' && (me.locations[platform + '_' + map] !== undefined) ? platform + '_' + map : platform;
        me.suspendLayouts();
		allLocations = allLocations || [];
		me.reset();
		if (selectedLocations === 'all') {
			me.mapEl.setStyle('background-position', this.getRegionPosition('all'));
            locationFound = true;
            if (platform === 'gce') {
                Ext.Object.each(me.locations[platformMap], function(key, value) {
                    me.addLocation(platform, key, value, true, true);
                });
            }
		} else if (me.locations[platformMap]) {
            selectedLocations = Ext.isArray(selectedLocations) ? selectedLocations : [selectedLocations];
            var selectedLocation = this.locations[platformMap][selectedLocations[0]];
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
	},

    addLocation: function(platform, name, data, selected, silent) {
        var me = this,
            title = name;
        if (platform !== 'gce' && me.platforms[platform] && me.platforms[platform].locations[name]) {
            title = me.platforms[platform].locations[name];
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
            me.titleEl.setHTML(title);
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
		var loc = this.mapEl.query('.location');
		for (var i=0, len=loc.length; i<len; i++) {
			Ext.removeNode(loc[i]);
		}
        this.titleEl.setHTML('');
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
        this.codeMirror = CodeMirror(this.inputEl, {
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

Ext.define('Scalr.ui.CycleButtonAlt', {
    alias: 'widget.cyclealt',

    extend: 'Ext.button.Split',

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

        checkItem = me.activeItems[0].next(':not([disabled])') || m.items.getAt(0);
        checkItem.setChecked(true);
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
        } else {
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

Ext.define('Scalr.ui.FormFieldColor', {
    extend: 'Ext.Component',
    mixins: {
        labelable: 'Ext.form.Labelable',
        field: 'Ext.form.field.Field'
    },
    alias: 'widget.colorfield',
	cls: 'x-form-colorpicker',
    fieldSubTpl: [],

	allowBlank: false,
	componentLayout: 'field',
	button:null,
	backgroundColor: '#000000',

    initComponent : function() {
        var me = this;
		me.callParent();
        me.initLabelable();
        me.initField();
        if (!me.name) {
            me.name = me.getInputId();
        }
    },

	afterRender: function() {
		var me = this;
		me.button = Ext.create('Ext.Button', {
			renderTo: this.bodyEl,
			pressedCls: '',
			menuActiveCls: '',
			menu: {
				xtype: 'colormenu',
				cls: 'x-form-colorpicker-menu',
				allowReselect: true,
				colors: ['333333', 'DF2200', 'AA00AA', '1A4D99', '3D690C', '006666', '6F8A02', '0C82C0', 'CA4B00', '671F92'],
				listeners: {
					select: function(picker, color){
						me.setValue(color);
					}
				}
			}
		});
		this.fieldReady = true;
	},

    getInputId: function() {
        return this.inputId || (this.inputId = this.id + '-inputEl');
    },

    initRenderTpl: function() {
        var me = this;
        me.renderTpl = me.getTpl('labelableRenderTpl');
        return me.callParent();
    },

    initRenderData: function() {
        return Ext.applyIf(this.callParent(), this.getLabelableRenderData());
    },

	setRawValue: function(value) {
		if (this.fieldReady) {
			var color = this.backgroundColor;
			if (!Ext.isEmpty(value)) {
				color = '#'+value;
			}
			this.button.el.down('.x-btn-inner').setStyle('background', color);
		}
	},

	setValue: function(value) {
		this.value = value;
		this.setRawValue(value);
		this.fireEvent('change', this, this.value);
	},

    getRawValue: function() {
		return this.value || '';
    },

	getValue: function() {
		return this.getRawValue();
	},

    setReadOnly: function(readOnly) {
        var me = this;
        readOnly = !!readOnly;
        me.readOnly = readOnly;
		if (this.fieldReady) {
		}
    }

});

Ext.define('Scalr.ui.VpcIdField', {
	extend: 'Ext.form.field.ComboBox',
	alias: 'widget.vpcidfield',
    
    name: 'vpcId',
    editable: false,
    hideInputOnReadOnly: true,
    queryCaching: false,
    clearDataBeforeQuery: true,
    valueField: 'id',
    displayField: 'name',
    icons: {
        governance: true
    },

    initComponent: function() {
        this.plugins = this.plugins || [];
        this.plugins.push({
            ptype: 'comboaddnew',
            pluginId: 'comboaddnew',
            url: '/tools/aws/vpc/create'
        });

        if (this.store === undefined) {
            this.store = {
                fields: [ 'id', 'name', 'info' ],
                proxy: Ext.applyIf(this.proxyConfig || {}, {
                    type: 'cachedrequest',
                    url: '/platforms/ec2/xGetVpcList',
                    root: 'vpc'
                })
            };
        }
        this.callParent(arguments);
        
        this.on({
            addnew: function(item) {
                Scalr.CachedRequestManager.get().setExpired({
                    url: this.store.proxy.url,
                    params: this.store.proxy.params
                });
            }
        });
    },

    setCloudLocation: function(cloudLocation) {
        var proxy = this.store.proxy,
            disableAddNewPlugin = false,
            vpcLimits = this.vpcLimits;
        this.reset();
        this.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + cloudLocation;
        proxy.params = {cloudLocation: cloudLocation};
        delete proxy.data;
        this.setReadOnly(false, false);
        if (Ext.isObject(vpcLimits)) {
            this.toggleIcon('governance', true);
            if (vpcLimits['value'] == 1) {
                this.allowBlank = false;
                if (vpcLimits['regions'] && vpcLimits['regions'][cloudLocation]) {
                    if (vpcLimits['regions'][cloudLocation]['ids'] && vpcLimits['regions'][cloudLocation]['ids'].length > 0) {
                        var vpcList = Ext.Array.map(vpcLimits['regions'][cloudLocation]['ids'], function(vpcId){
                            return {id: vpcId, name: vpcId};
                        });
                        proxy.data = vpcList;
                        this.store.load();
                        this.setValue(this.store.first());
                        disableAddNewPlugin = true;
                    }
                } else {
                    this.allowBlank = true;
                    this.setReadOnly(true);
                }
            } else {
                this.allowBlank = true;
                this.setReadOnly(true);
            }
        }
        this.getPlugin('comboaddnew').setDisabled(disableAddNewPlugin);
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
    forceSelection: true,
    hideInputOnReadOnly: true,
    queryMode: 'local',

    valueField: 'id',
    displayField: 'name',

    listConfig: {
        cls: 'x-boundlist-alt',
        tpl:
            '<tpl for=".">' +
                '<div class="x-boundlist-item" style="height: auto; width: auto; max-width: 900px;">' +
                    '<div><span style="font-weight: bold">{name}</span>' +
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

    initComponent: function() {
        var me = this;

        me.callParent(arguments);

        me.on('specialkey', function (field, e) {
            if(e.getKey() === e.ESC){
                field.reset();
            }
        });
    },

    onRender: function() {
        var me = this;

        me.callParent(arguments);

        me.inputEl.on('click', function() {
            me.expand();
        });

        me.inputImgEl = me.inputCell.createChild({
            tag: 'img',
            style: 'position: absolute; top: 6px; right: 22px; opacity: 0.8',
            width: 15,
            height: 15
        });
        me.inputImgEl.setVisibilityMode(Ext.Element.DISPLAY);
        me.setInputImgElType(me.getValue());
    },

    setInputImgElType: function(value) {
        var me = this, rec = me.findRecordByValue(value);
        if (rec) {
            this.inputImgEl.dom.src = '/ui2/images/ui/scripts/' + rec.get('os') + '.png';
            this.inputImgEl.show();
        } else {
            this.inputImgEl.hide();
        }
    },

    onChange: function(newValue) {
        var me = this;
        if (me.inputImgEl) {
            me.setInputImgElType(newValue);
        }

        me.callParent(arguments);
    }
});

//constants must be defined before overrides
Scalr.constants = {
    iopsMin: 100,
    iopsMax: 4000,
    ebsMaxStorageSize: 1000,
    ebsMinProIopsStorageSize: 10,
    ebsMaxIopsSizeRatio: 30,
    redisPersistenceTypes: [
        ['aof', 'Append Only File'],
        ['snapshotting', 'Snapshotting'],
        ['nopersistence', 'No persistence']
    ]
};
Scalr.constants.ebsTypes = [
    ['standard', 'Standard EBS (Magnetic)'],
    ['gp2', 'General Purpose (SSD)'],
    ['io1', 'Provisioned IOPS (' + Scalr.constants.iopsMin + ' - ' + Scalr.constants.iopsMax + '):']
];
Scalr.constants.osFamily = [
    ['amazon', [['2012.09', '2012.09'],['2013.03', '2013.03'],['2014.03', '2014.03'],['2014.09', '2014.09']]],
    ['centos', [
        ['5.X', '5', 'Final'],
        ['6.X', '6', 'Final'],
        ['7.X', '7', 'Final']
    ]],
    ['debian', [
        ['6.X', '6', 'Squeeze'],
        ['7.X', '7', 'Wheezy'],
    ]],
    ['gcel', [['12.04', '12.04']]],
    ['oel', [
        ['5.X', '5', 'Tikanga'],
        ['6.X', '6', 'Santiago']
    ]],
    ['redhat', [
        ['5.X', '5', 'Tikanga'],
        ['6.X', '6', 'Santiago'],
        ['7.X', '7', 'Maipo']
    ]],
    ['ubuntu', [
        ['10.04', '10.04', 'Lucid'],['10.10', '10.10', 'Maverick'],['11.04', '11.04', 'Natty'],['11.10', '11.10', 'Oneiric'],
        ['12.04', '12.04', 'Precise'],['12.10', '12.10', 'Quantal'],['13.04', '13.04', 'Raring'],['13.10', '13.10', 'Saucy'],
        ['14.04', '14.04',  'Trusty'],['14.10', '14.10', 'Utopic']
    ]],
    ['windows', [['2003', '2003', 'Server'],['2008', '2008', 'Server'],['2012', '2012', 'Server']]]
];


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
    dismissDelay: 0,
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
        me.bodyEl.applyStyles('padding-right: 28px');
        me.browseButtonWrap.setWidth(0);
	},

	buttonText: '',
    buttonConfig: {
        ui: 'action',
        cls: 'x-form-file-btn x-btn-action-folder',
        margin: '0 0 0 8'
    },
	setValue: function(value) {
		Ext.form.field.File.superclass.setValue.call(this, value);
	}
});

/**
 * 4.2.2
 */
Ext.override(Ext.form.field.Base, {
	allowChangeable: true,
	allowChangeableMsg: 'You can set this value only once',
    tooltipText: null,
	validateOnBlur: false,

    icons: undefined,
    iconsPosition: 'inner',

	initComponent: function() {
        this.initIcons();
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

            if (e.getKey() == e.BACKSPACE && field.readOnly) {
                e.stopEvent();
            }
		});

		if (! this.allowChangeable) {
			this.cls += ' x-form-notchangable-field';
            this.tooltipText = this.allowChangeableMsg;
		}
	},

    initIcons: function() {
        var me = this;
        if (me.icons) {
            var icons = {},
                iconsConfig = {
                    governance: {
                        hidden: true,
                        tooltip: me.getGovernanceTooltip()
                    },
                    globalvars: {
                        tooltip: 'This field supports Global Variable Interpolation.'
                    },
                    question: {
                        hidden: true,
                        tooltip: me.questionTooltip || ''
                    },
                    szrversion: {
                        iconCls: 'warning',
                        tooltip: 'Feature only available in Scalarizr starting from {version}'
                    }
                };
            Ext.Object.each(me.icons, function(name, enabled){
                if (enabled) {
                    icons[name] = Ext.apply({}, iconsConfig[name] || {});
                    if (Ext.isObject(enabled)) {
                        icons[name].tooltip = enabled['tooltip'] || icons[name].tooltip;
                        if (enabled['tooltipData']) {
                            icons[name].tooltip = new Ext.Template(icons[name].tooltip).apply(enabled['tooltipData']);
                        }
                    }
                }
            });
            me.icons = Ext.Object.getSize(icons) ? icons : undefined;
        }
    },

    beforeRender: function() {
        this.callParent(arguments);
        this.renderIcons();
    },

    renderIcons: function() {
        if (!this.icons) return;
        var me = this,
            html = [];
        me.visibleIconsCount = 0;
        Ext.Object.each(this.icons, function(key, value){
            html.push('<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-' + (value['iconCls'] || key) + '" data-qtip="' + value['tooltip'] + '" ' + (value['hidden'] ? 'style="display:none"' : '') + ' />');
            me.visibleIconsCount += value['hidden'] ? 0 : 1;
        });
        this[this.isCheckbox ? 'afterBoxLabelTpl' : 'beforeSubTpl'] = '<span class="x-field-icons">' + html.join('') + '</span>' + (me[this.isCheckbox ? 'afterBoxLabelTpl' : 'beforeSubTpl'] || '');

    },

    refreshIcons: function() {
        if (!this.icons || !this.rendered || this.isCheckbox) return;
        if (this.iconsPosition === 'inner' && this.labelAlign !== 'top') {
            this.bodyEl.setStyle('padding-left', (this.visibleIconsCount*22) + 'px');
        } else {
            var style = {
                left: (0 - this.visibleIconsCount*22) + 'px'
            };
            if (this.labelAlign === 'top') {
               style['top'] = '28px';
            }
            Ext.fly(this.bodyEl.query('.x-field-icons')[0]).setStyle(style);
        }
    },

    hideIcons: function() {
        if (!this.icons) return;
        if (this.rendered) {
            this.visibleIconsCount = 0;
            Ext.each(this.bodyEl.query('.x-field-icons img'), function(iconEl){
                Ext.fly(iconEl).setVisibilityMode(Ext.Element.DISPLAY).setVisible(false);
            });
        } else {
            Ext.Object.each(this.icons, function(key, value){
                value['hidden'] = true;
            });
        }
    },

    toggleIcon: function(icon, show) {
        if (!this.icons || !this.icons[icon]) return;
        if (this.rendered) {
            var iconEl = this.bodyEl.query('.x-field-icons .x-icon-' + (this.icons[icon]['iconCls'] || icon));
            if (iconEl.length) {
                iconEl = Ext.fly(iconEl[0]);
                var isVisible = iconEl.isVisible();
                if (show === undefined) {
                    show = !isVisible;
                    this.visibleIconsCount--;
                } else if (show && !isVisible) {
                    this.visibleIconsCount++;
                } else if (!show && isVisible) {
                    this.visibleIconsCount--;
                }
                iconEl.setVisibilityMode(Ext.Element.DISPLAY).setVisible(!!show);
                this.refreshIcons();
            }
        } else {
            if (this.icons[icon]) {
                if (show === undefined) {
                    show = !!this.icons[icon]['hidden'];
                }
                this.icons[icon]['hidden'] = !show;
            }
        }

    },

    updateIconTooltip: function(icon, tooltip) {
        if (!this.icons || !this.icons[icon]) return;
        if (this.rendered) {
            var iconEl = this.bodyEl.query('.x-field-icons .x-icon-' + (this.icons[icon]['iconCls'] || icon));
            if (iconEl.length) {
                iconEl = Ext.fly(iconEl[0]);
                iconEl.set({'data-qtip': tooltip});
            }
        }
    },

	afterRender: function() {
        if (this.icons) {
            this.bodyEl.setStyle({position: 'relative', display: 'inline-block'});
            this.refreshIcons();
        }
		this.callParent(arguments);

		if (this.tooltipText) {
            this.tooltip = Ext.create('Ext.tip.ToolTip', {
                target: this.inputEl,
                html: this.tooltipText
            });
        }
	},

    setDisabledTooltip: function(disabled) {
        if (this.tooltip)
            this.tooltip.setDisabled(disabled);
    },

    onDestroy: function() {
        this.callParent(arguments);

        if (this.tooltip) {
            this.tooltip.destroy();
        }
    },

	markInvalid: function() {
		this.callParent(arguments);
        this.setDisabledTooltip(true);
	},

	clearInvalid: function() {
		this.callParent(arguments);
        this.setDisabledTooltip(false);
	},

    setValueWithGovernance: function(value, limit) {
        var governanceEnabled = limit !== undefined;
        this.setValue(governanceEnabled ? limit : value);
        this.setReadOnly(governanceEnabled, false);
        this.toggleIcon('governance', governanceEnabled);
    },

    getGovernanceTooltip: function(raw) {
        var message;
        if (!this.governanceTooltip) {
            message = 'The account owner has enforced a specific policy on ';
            if (this.governanceTitle) {
                message += 'the <b>' + this.governanceTitle + '</b>.';
            } else if (this.fieldLabel) {
                message += 'the <b>' + this.fieldLabel + '</b> setting.';
            } else {
                message += 'this setting.';
            }
        } else {
            message = this.governanceTooltip;
        }
        return raw ? message : Ext.String.htmlEncode(message);
    },

    /**
     * Protect from XSS in validator error message
     */
    setActiveErrors: function (errors) {
        var me = this;

        errors = Ext.Array.map(Ext.Array.from(errors), Ext.String.htmlEncode);
        me.callParent([errors]);
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
        me.fireEvent('writeablechange', me, readOnly);
	}
});

Ext.override(Ext.form.field.Trigger, {
	updateEditState: function() {
		var me = this;

		me.callOverridden();
		me[me.readOnly ? 'addCls' : 'removeCls'](me.readOnlyCls);
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
	},

    toggleFields: function (fields, restoreState) {
        var me = this;

        me.suspendLayouts();

        var getField = function (fieldName) {
            return me.down('[name=' + fieldName + ']');
        };

        var toggle = function (field) {
            field.setVisible(!field.isVisible());
        };

        var restore = function (field) {
            var initVisible = field.initialConfig.hidden;

            field.setVisible(!initVisible);
        };

        var doToggle = function (fieldName) {
            var field = getField(fieldName);

            if (field) {
                !restoreState ? toggle(field) : restore(field);
            }
        };

        if (typeof fields === 'object') {
            Ext.each(fields, function (fieldName) {
                doToggle(fieldName);
            });
        } else {
            doToggle(fields);
        }

        me.resumeLayouts(true);
        me.doLayout();
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
        if (me.hideInputOnReadOnly) {
            me.readOnlyCls += ' x-input-readonly';
        }
		me.callParent(arguments);
	}
});

Ext.override(Ext.form.field.ComboBox, {
	matchFieldWidth: false,
	autoSetValue: false,
    autoSetSingleValue: false,
    clearDataBeforeQuery: false,
    restoreValueOnBlur: false,
    enableKeyEvents: true,
    autoSearch: true,

	initComponent: function() {
		var me = this;
		me.callParent(arguments);
		if (!me.value && me.autoSetValue && me.store.getCount() > 0) {
			me.setValue(me.store.first().get(me.valueField));
		} else if (!me.value && me.autoSetSingleValue && me.store.getCount() == 1) {
            me.setValue(me.store.first().get(me.valueField));
        }

        me.enteredValue = '';

        me.clearEnteredValue = Ext.util.TaskManager.newTask({
            run: function () {
                me.enteredValue = '';
            },
            scope: me,
            interval: 1000
        });
	},

    setValue: function(value, doSelect) {
        var me = this;
        if (!value && me.autoSetSingleValue && me.store.getCount() == 1) {
            value = me.store.first().get(me.valueField);
        }

        return me.callParent([value, doSelect]);
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
    },

    selectRecord: function (key, enteredValue) {
        var me = this;

        var getDeltaY = function (recordEl, boundList) {
            var recordY = Ext.get(recordEl).getY();
            var boundListY = boundList.getY();

            return recordY - boundListY;
        };

        var getRecord = function (key, enteredValue) {
            var record = null;

            if (key === me.lastEnteredKey && enteredValue.length <= 2) {
                record = store.findRecord(me.displayField, key, me.lastRecord.index + 1) ||
                         store.findRecord(me.displayField, key);

                me.enteredValue = '';

                return record;
            }

            record = store.findRecord(me.displayField, enteredValue) ||
                     store.findRecord(me.displayField, enteredValue, 0, true);

            return record;
        };

        var boundList = me.getPicker();
        var store = boundList.getStore();
        var record = getRecord(key, enteredValue);
        var recordEl = boundList.getNode(record);

        if (recordEl) {
            var deltaY = getDeltaY(recordEl, boundList);

            me.setValue(record);

            boundList.show();
            boundList.scrollBy(0, deltaY, false);
            boundList.highlightItem(recordEl);
        }

        me.lastRecord = record || me.lastRecord;
        me.lastEnteredKey = key;
    },

    onKeyPress: function (event) {
        var me = this;

        if (me.autoSearch) {
            var key = String.fromCharCode(event.getKey());

            me.clearEnteredValue.restart();

            me.enteredValue = me.enteredValue + key;

            me.selectRecord(key, me.enteredValue);
        }

        me.callParent(arguments);
    },

    onExpand: function () {
        var me = this;

        if (me.autoSearch && !me.lastRecord) {
            var boundList = me.getPicker();
            var firstRecord = boundList.getStore().first();

            me.lastRecord = boundList.getNode(firstRecord);
        }

        me.callParent();
    },

    onBlur: function () {
        var me = this;

        if (me.autoSearch) {
            me.enteredValue = '';
            me.clearEnteredValue.stop();
        }

        me.callParent();
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
	},

    updateBody: function() {
        var me = this,
            years = me.years,
            months = me.months,
            yearNumbers = me.getYears(),
            value = me.getYear(null),
            monthOffset = me.monthOffset,
            year,
            monthItems, m, mr, mLen,
            yearItems, y, yLen, el, maxYear, minYear, maxMonth, minMonth;

        this.callParent(arguments);

        if (me.rendered) {
            if (me.maxDate) {
                maxYear = me.maxDate.getFullYear();
                maxMonth = me.maxDate.getMonth();
            }
            if (me.minDate) {
                minYear = me.minDate.getFullYear();
                minMonth = me.minDate.getMonth();
            }

            monthItems = months.elements;
            mLen      = monthItems.length;
            for (m = 0; m < mLen; m++) {
                el = Ext.fly(monthItems[m]);
                mr = me.resolveOffset(m, monthOffset);
                if (value == maxYear && (maxMonth && mr > maxMonth || minMonth && mr < minMonth)) {
                    el.parent().addCls('x-item-disabled');
                } else {
                    el.parent().removeCls('x-item-disabled');
                }

            }

            yearItems = years.elements;
            yLen      = yearItems.length;

            for (y = 0; y < yLen; y++) {
                el = Ext.fly(yearItems[y]);

                year = yearNumbers[y];
                if (maxYear && year > maxYear || minYear && year < minYear) {
                    el.parent().addCls('x-item-disabled');
                } else {
                    el.parent().removeCls('x-item-disabled');
                }

            }
        }
    },

    onBodyClick: function(e, t) {
        if (!Ext.fly(t.parentNode).hasCls('x-item-disabled')) {
            this.callParent(arguments);
        } else {
            e.stopEvent();
        }
    },

});

Ext.override(Ext.container.Container, {
    relayEventsList: null,

    initComponent: function() {
        var me = this, fields;
        me.callParent(arguments);
        if (me.relayEventsList) {
            me.fieldRelayers = [];
            fields = this.query('[isFormField]');
            for (var i = 0, len = fields.length; i < len; i++) {
                me.fieldRelayers[i] = me.relayEvents(fields[i], me.relayEventsList, 'field');
            }
        }
    },

    beforeDestroy: function() {
        if (this.fieldRelayers) {
            Ext.each(this.fieldRelayers, function(relayer){
                Ext.destroy(relayer);
            });
            delete this.fieldRelayers;
        }
        this.callParent(arguments);
    },

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
    },

    /*preserve scroll position*/
    preserveScrollPosition: false,
    onRender: function () {
        this.callParent(arguments);
        if (this.preserveScrollPosition) {
            this.mon(this.body ? this.body.el : this.el, 'scroll', this.onScroll, this);
        }
    },

    onScroll: function (e ,t, eOpts) {
        this.scrollPosition = (this.body ? this.body.el : this.el).getScroll();
    },

    afterLayout: function () {
        this.callParent(arguments);
        if (this.preserveScrollPosition && this.scrollPosition) {
            var el = this.body ? this.body.el : this.el,
                scrollPosition = this.scrollPosition;
            el.scrollTo('left', scrollPosition.left);
            el.scrollTo('top', scrollPosition.top);
        }
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
    override: 'Ext.tip.QuickTip',
    maxWidth: 600,

    allowTooltipMouseOver: true,
    
    onRender: function() {
        var me = this;
        me.callParent(arguments);
        if (me.anchorEl) {
            me.anchorEl.setVisibilityMode(Ext.Element.DISPLAY);
        }
        if (me.allowTooltipMouseOver) {
            me.mon(me.el, {
                mouseover: me.onTooltipOver,
                mouseout: me.onTargetOut,
                mousemove: me.onMouseMove,
                mousedown: me.onTooltipMouseDown,
                scope: me
            });
        }
    },
    
    onTooltipOver: function() {
        this.clearTimer('hide');
    },

    onTooltipMouseDown: function(e) {
        if (e.getTarget(null, 1, true).is('a')) {
            this.delayHide();
        }
    },

    syncAnchor: function() {
        var me = this,
            anchorPos,
            targetPos,
            offset;
        switch (me.tipAnchor.charAt(0)) {
        case 't':
            anchorPos = 'b';
            targetPos = 'tl';
            offset = [20 + me.anchorOffset, 1];
            break;
        case 'r':
            anchorPos = 'l';
             /** Changed */
            targetPos = 'r';
            offset = [ - 1, 0 + me.anchorOffset];
            /** End */
            break;
        case 'b':
            anchorPos = 't';
            targetPos = 'bl';
            offset = [20 + me.anchorOffset, -1];
            break;
        default:
            anchorPos = 'r';
            /** Changed */
            targetPos = 'l';
            offset = [1, 0 + me.anchorOffset];
            /** End */
            break;
        }
        me.anchorEl.alignTo(me.el, anchorPos + '-' + targetPos, offset);
        me.anchorEl.setStyle('z-index', parseInt(me.el.getZIndex(), 10) || 0 + 1).setVisibilityMode(Ext.Element.DISPLAY);
    },

    onTargetOver : function(e){
        var me = this,
            target = e.getTarget(me.delegate),
            hasShowDelay,
            delay,
            elTarget,
            cfg,
            ns,
            tipConfig,
            autoHide,
            targets, targetEl, value, key;

        if (me.disabled) {
            return;
        }

        me.targetXY = e.getXY();

        if(!target || target.nodeType !== 1 || target == document.documentElement || target == document.body){
            return;
        }

        if (me.activeTarget && ((target == me.activeTarget.el) || Ext.fly(me.activeTarget.el).contains(target))) {

            if (me.targetTextEmpty()) {
                me.onShowVeto();
                delete me.activeTarget;
            } else {
                me.clearTimer('hide');
                me.show();
            }
            return;
        }

        if (target) {
            targets = me.targets;

            for (key in targets) {
                if (targets.hasOwnProperty(key)) {
                    value = targets[key];

                    targetEl = Ext.fly(value.target);
                    if (targetEl && (targetEl.dom === target || targetEl.contains(target))) {
                        elTarget = targetEl.dom;
                        break;
                    }
                }
            }

            if (elTarget) {
                me.activeTarget = me.targets[elTarget.id];
                me.activeTarget.el = target;
                me.anchor = me.activeTarget.anchor;
                if (me.anchor) {
                    me.anchorTarget = target;
                }
                hasShowDelay = parseInt(me.activeTarget.showDelay, 10);
                if (hasShowDelay) {
                    delay = me.showDelay;
                    me.showDelay = hasShowDelay;
                }
                me.delayShow();
                if (hasShowDelay) {
                    me.showDelay = delay;
                }
                return;
            }
        }


        elTarget = Ext.fly(target, '_quicktip-target');
        cfg = me.tagConfig;
        ns = cfg.namespace;
        tipConfig = me.getTipCfg(e);

        if (tipConfig) {
            if (tipConfig.target) {
                target = tipConfig.target;
                elTarget = Ext.fly(target, '_quicktip-target');
            }
            autoHide = elTarget.getAttribute(ns + cfg.hide);

            me.activeTarget = {
                el: target,
                text: tipConfig.text,
                width: +elTarget.getAttribute(ns + cfg.width) || null,
                autoHide: autoHide != "user" && autoHide !== 'false',
                title: elTarget.getAttribute(ns + cfg.title),
                cls: elTarget.getAttribute(ns + cfg.cls),
                align: elTarget.getAttribute(ns + cfg.align),
                showDelay: parseInt(elTarget.getAttribute(ns + cfg.showDelay), 10)
            };
            me.anchor = elTarget.getAttribute(ns + cfg.anchor);
            if (me.anchor) {
                /** Changed */
                me.origAnchor = me.anchor;//when we set anchor - we must set origAnchor as well
                /** End */
                me.anchorTarget = target;
            }
            hasShowDelay = parseInt(me.activeTarget.showDelay, 10);
            if (hasShowDelay) {
                delay = me.showDelay;
                me.showDelay = hasShowDelay;
            }
            me.delayShow();
            if (hasShowDelay) {
                me.showDelay = delay;
            }
        }
    },
    
    showAt : function(xy){
        var me = this,
            target = me.activeTarget,
            header = me.header,
            dismiss, cls;

        if (target) {
            if (!me.rendered) {
                me.render(Ext.getBody());
                me.activeTarget = target;
            }
            me.suspendLayouts();
            if (target.title) {
                me.setTitle(target.title);
                header.show();
            } else if (header) {
                header.hide();
            }
            me.update(target.text);
            me.autoHide = target.autoHide;
            dismiss = target.dismissDelay;

            me.dismissDelay = Ext.isNumber(dismiss) ? dismiss : me.dismissDelay;
            if (target.mouseOffset) {
                xy[0] += target.mouseOffset[0];
                xy[1] += target.mouseOffset[1];
            }

            cls = me.lastCls;
            if (cls) {
                me.removeCls(cls);
                delete me.lastCls;
            }

            cls = target.cls;
            if (cls) {
                me.addCls(cls);
                me.lastCls = cls;
            }

            me.setWidth(target.width);
            /** Changed */
            me.resumeLayouts(true);//bugfix we must to resume layouts before calling me.getAlignToXY
            if (me.anchor && !target.align) {
                me.constrainPosition = false;
            } else if (target.align) {
                var addOffset;
                if (target.align === 'r-l') {
                    addOffset = [-10, 0];
                } else if (target.align === 'l-r') {
                    addOffset = [10, 0];
                }
                xy = me.getAlignToXY(target.el, target.align, addOffset);
                me.constrainPosition = false;
            }else{
                me.constrainPosition = true;
            }
            /** end */
        }
        me.callParent([xy]);
    }
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
    override: 'Ext.data.NodeInterface',

    statics: {
        decorate: function (modelClass) {
            var me = this;

            me.callParent(arguments);

            modelClass.override({
                getMatches: function (filters) {
                    var me = this;

                    var matches = [];

                    Ext.Array.each(filters, function (filter) {
                        var filterFn = filter.filterFn;
                        var property = filter.property;
                        var value = filter.value;
                        var re = new RegExp(value, "ig");

                        me.cascadeBy(function (node) {
                            if (!node.isRoot()) {
                                if (filterFn && filterFn(node)) {
                                    me.filterValue = filterFn(node)[0];
                                    matches.push(node);
                                } else if ((property && value) && (node.get(property).match(re))) {
                                    me.filterValue = value;
                                    matches.push(node);
                                }
                            }
                        });
                    });

                    return matches;
                },

                filter: function (filters) {
                    var me = this;

                    var matches = me.getMatches(filters);

                    var nodesToRemove = [];

                    me.cascadeBy(function (node) {

                        var isLeaf = node.isLeaf();
                        var isMatched = Ext.Array.contains(matches, node);
                        var parentNode = node.parentNode;

                        var hasMatchedNodes = function (node) {
                            return matches.some(function (matchedNode) {
                                return node.contains(matchedNode);
                            });
                        };

                        if ((isLeaf && !isMatched) || (!isLeaf && !isMatched && !hasMatchedNodes(node))) {
                            if (!(parentNode && Ext.Array.contains(matches, parentNode))) {
                                nodesToRemove.push(node);
                            }
                        }
                    });

                    Ext.Array.each(nodesToRemove, function (node) {
                        node.remove();
                    });

                    return me.filterValue;
                }
            });
        }
    }
});

Ext.define(null, {
    override: 'Ext.tree.View',

    highlight: function (node, value, exceptions) {
        var me = this;

        var nodeDomEl = Ext.get(me.getNode(node));
        var nodeText = nodeDomEl.down('.x-tree-node-text');
        nodeText.dom.HTMLsnapshot = nodeText.dom.HTMLsnapshot || nodeText.dom.innerHTML;

        var htmlText = nodeText.dom.HTMLsnapshot;
        var regExpForReplace = new RegExp(value, 'g');

        var isTextInTag = function (textForReplace, position) {
            var previousOpeningTagPosition = htmlText.lastIndexOf('<', position),
                previousClosingTagPosition = htmlText.lastIndexOf('>', position);
            var result = previousOpeningTagPosition - previousClosingTagPosition;
            return [result > 0, previousClosingTagPosition];
        };

        var isException = function (previousClosingTagPosition, exceptions) {
            if (!exceptions || !Ext.isArray(exceptions) || !exceptions.length) {
                return false;
            }

            var text = htmlText.substr(previousClosingTagPosition + 1, 5);

            return exceptions.some(function (exception) {
                return text === exception;
            });
        };

        var doHighlight = function (textForReplace, position) {
            var params = isTextInTag(textForReplace, position),
                textInTag = params[0],
                previousClosingTagPosition = params[1];
            if (!textInTag) {
                if (!isException(previousClosingTagPosition, exceptions)) {
                    return true;
                } else {
                    return position - previousClosingTagPosition >= 6;
                }
            }
            return false;
        };

        var replaceText = function (textForReplace, position) {
            if (doHighlight(textForReplace, position)) {
                return '<span class="scalr-ui-monitoring-filter-highlight">' + textForReplace + '</span>'
            }
            return textForReplace;
        };

        htmlText = htmlText.replace(regExpForReplace, replaceText);
        nodeText.setHTML(htmlText);
    }
});

Ext.define(null, {
    override: 'Ext.tree.Panel',
    showCheckboxesAtRight: false,

    //disable animation due to unpredictable chrome tab crashing since v30
    animate: false,

    hasHighlight: false,
    highlightExceptions: null,

    initEvents: function () {
        var me = this;

        var store = me.getStore();

        store.on('beforefilter', function () {
            me.show();
            me.suspendLayouts();
        });

        store.on('afterfilter', function (value) {
            me.fireEvent('afterfilter');

            me.resumeLayouts(true);
            me.doLayout();

            if (me.hasHighlight && value) {
                me.highlight(value);
            }

            if (!me.getRootNode().hasChildNodes()) {
                me.hide();
            }
        });

        me.callParent(arguments);
    },

    highlight: function (value) {
        var me = this;
        var view = me.getView();

        me.getRootNode().cascadeBy(function (node) {
            if (!node.isRoot()) {
                view.highlight(node, value, me.highlightExceptions);
            }
        });
    },

    afterLayout: function () {
        var me = this;
        var rootNode = me.getRootNode();

        me.callParent(arguments);

        if (me.showCheckboxesAtRight) {
            me.addCls('x-tree-panel-show-checkboxes-at-right');
        }

        if (rootNode) {
            var rootChildNodes = rootNode.childNodes;
            var treeView = me.getView();

            Ext.each(rootChildNodes, function (currentNode) {
                var currentNodeFirstChild = currentNode.firstChild;
                var currentNodeFirstChildDomEl = Ext.get(treeView.getNode(currentNodeFirstChild));

                if (currentNodeFirstChildDomEl) {
                    var currentNodeFirstChildDomElClassName = currentNodeFirstChildDomEl.dom.className;
                    var expandedNodeClassPosition = currentNodeFirstChildDomElClassName.search('x-grid-tree-node-leaf');

                    if (expandedNodeClassPosition === -1) {
                        var iconImage = currentNodeFirstChildDomEl.down('.x-tree-expander');

                        if (iconImage) {
                            iconImage.addCls('x-tree-expander-inception');
                        }
                    }
                }
            });
        }
    }
});

Ext.define(null, {
    override: 'Ext.data.TreeStore',

    hasFilter: false,

    filter: function(filters, value) {
        var me = this;

        me.fireEvent('beforefilter');

        me.clearFilter();

        if (Ext.isString(filters)) {
            filters = {
                property: filters,
                value: value
            };
        }

        var decoded = me.decodeFilters(filters);

        for (var i = 0; i < decoded.length; i++) {
            me.filters.replace(decoded[i]);
        }

        filters = me.filters.items;

        if (me.snapshot) {
            me.setRootNode(me.snapshot);
        }

        me.snapshot = me.getRootNode().copy(null, true);

        if (filters.length) {
            var filterValue = me.getRootNode().filter(filters);

            me.hasFilter = true;
        }

        me.fireEvent('afterfilter', filterValue);
    },

    clearFilter: function() {
        var me = this;

        me.filters.clear();

        me.hasFilter = false;
    },

    isFiltered: function() {
        var me = this;

        return me.hasFilter;
    }
});

Ext.define(null, {
    override: 'Ext.selection.Model',
    
    storeHasSelected: function(record) {
        var store = this.store,
            records,
            len, id, i;

        if (record.hasId() && store.getById(record.getId())) {
            return true;
        } else {
            records = store.data.items;
            /** Changed */
            if (records) {/*this is required for buffered store*/
            /** End */
                len = records.length;
                id = record.internalId;

                for (i = 0; i < len; ++i) {
                    if (id === records[i].internalId) {
                        return true;
                    }
                }
            /** Changed */
            }
            /** End */
        }
        return false;
    }

});


Ext.define(null, {
    override: 'Ext.grid.plugin.BufferedRenderer',

    init: function(grid) {
        var me = this;
        me.callParent(arguments);

        grid.view.on({
            viewready: function() {
                this.store.on('beforeprefetch', me.onStoreBeforePrefetch, me);
                this.store.on('prefetch', me.onStorePrefetch, me);
            },
            single: true
        },{
            begoredestroy: function() {
                this.store.un('beforeprefetch', me.onStoreBeforePrefetch, me);
                this.store.un('prefetch', me.onStorePrefetch, me);
            },
            single: true
        });
        me.view.addFooterFn(Ext.bind(me.renderLoader, me));
    },

    onStoreBeforePrefetch: function(store, records) {
        var view = this.view,
            loader = view.el.down('#' + view.id + '-buffered-loader');
        if (loader) {
            loader.show();
        }
    },

    onStorePrefetch: function(store, records) {
        var view = this.view,
            loader = view.el.down('#' + view.id + '-buffered-loader');
        if (loader) {
            loader.setVisibilityMode(Ext.dom.AbstractElement.DISPLAY);
            loader.hide();
        }
    },

    renderLoader: function(values, out) {
        var view = values.view,
            colspan = view.headerCt.getVisibleGridColumns().length;
        if (view.store.isLoading() || view.store.getCount() < view.store.getTotalCount()) {
            out.push('<tfoot id="' + view.id + '-buffered-loader"><tr><td colspan="' + colspan + '"><div class="x-grid-buffered-loader">Loading...</div></td></tr></tfoot>');
        }
    },

    onViewRefresh: function() {
        var me = this,
            view = me.view,
            oldScrollHeight = me.scrollHeight,
            scrollHeight;


        if (view.all.getCount()) {


            delete me.rowHeight;
        }



        scrollHeight = me.getScrollHeight();

        if (!oldScrollHeight || scrollHeight != oldScrollHeight) {
            /** Changed */
            me.stretchView(view, scrollHeight, true);
            /** End */
        }

        if (me.scrollTop !== view.el.dom.scrollTop) {



            me.onViewScroll();
        } else {
            /** Changed */
            me.setBodyTop(me.bodyTop, undefined, true);
            /** End */

            if (view.all.getCount()) {
                me.viewSize = 0;
                me.onViewResize(view, null, view.getHeight());
            }
        }
    },

    stretchView: function(view, scrollRange/** Changed */, force/** End */) {
        var me = this,
            recordCount = (me.store.buffered ? me.store.getTotalCount() : me.store.getCount());

        if (me.stretcher) {
            /** Changed */
            if (parseInt(me.stretcher.dom.style.marginTop) < scrollRange || force) {
                me.stretcher.dom.style.marginTop = (scrollRange - 1) + 'px';
            }
            /** End */
        } else {
            var el = view.el;



            if (view.refreshCounter) {
                view.fixedNodes++;
            }


            if (recordCount && (me.view.all.endIndex === recordCount - 1)) {
                scrollRange = me.bodyTop + view.body.dom.offsetHeight;
            }
            this.stretcher = el.createChild({
                style: {
                    width: '1px',
                    height: '1px',
                    'marginTop': (scrollRange - 1) + 'px',
                    left: 0,
                    position: 'absolute'
                }
            }, el.dom.firstChild);
        }
    },

    setBodyTop: function(bodyTop, calculatedTop/** Changed */, forceStretchView/** End */) {
        var me = this,
            view = me.view,
            store = me.store,
            body = view.body.dom,
            delta;

        bodyTop = Math.floor(bodyTop);




        if (calculatedTop !== undefined) {
            delta = bodyTop - calculatedTop;
            bodyTop = calculatedTop;
        }
        body.style.position = 'absolute';
        body.style.top = (me.bodyTop = bodyTop) + 'px';



        if (delta) {
            me.scrollTop = me.position = view.el.dom.scrollTop -= delta;
        }

        /** Changed */
        //if (view.all.endIndex === (store.buffered ? store.getTotalCount() : store.getCount()) - 1) {
            me.stretchView(view, me.bodyTop + body.offsetHeight, forceStretchView);
        //}
        /** End */
    },

    getScrollHeight: function() {
        var me = this,
            view   = me.view,
            store  = me.store,
            doCalcHeight = !me.hasOwnProperty('rowHeight'),
            storeCount = me.store.getCount();

        if (!storeCount) {
            return 0;
        }
        if (doCalcHeight) {
            if (view.all.getCount()) {
                me.rowHeight = Math.floor(view.body.getHeight() / view.all.getCount());
            }
        }
        /** Changed */
        return this.scrollHeight = Math.floor((store.buffered && store.getCount() < store.getTotalCount() ? store.getCount() + 20 : store.getCount()) * me.rowHeight);
        /** End */
    }


});

Ext.apply(Ext.form.field.VTypes, {
    numMask: /^[0-9]+$/,
    num: function(v) {
        return this.numMask.test(v);
    },
    daterange: function(val, field) {
        var date = field.parseDate(val);

        if (!field.daterangeCtId || !date) {
            return false;
        }
        if (field.startDateField && (!this.dateRangeMax || (date.getTime() != this.dateRangeMax.getTime()))) {
            var start = field.up('#' + field.daterangeCtId).down('#' + field.startDateField);
            start.setMaxValue(date);
            start.validate();
            this.dateRangeMax = date;
        }
        else if (field.endDateField && (!this.dateRangeMin || (date.getTime() != this.dateRangeMin.getTime()))) {
            var end = field.up('#' + field.daterangeCtId).down('#' + field.endDateField);
            end.setMinValue(date);
            end.validate();
            this.dateRangeMin = date;
        }
        /*
         * Always return true since we're only using this vtype to set the
         * min/max allowed values (these are tested for after the vtype test)
         */
        return true;
    },

    daterangeText: 'Start date must be less than end date',

    rolename: function(value) {
        var r = /^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/;
        return r.test(value);
    },
    rolenameText: 'Name should start and end with letter or number and contain only letters, numbers and dashes.',

    iops: function(value){
        return value*1 >= Scalr.constants.iopsMin && value*1 <= Scalr.constants.iopsMax;
    },
    iopsText: 'Value must be between ' + Scalr.constants.iopsMin + ' and ' + Scalr.constants.iopsMax,
    iopsMask: /[0-9]/,

    password: function(value, field) {
        if (field.otherPassField) {
            var otherPassField = field.up('form').down('#' + field.otherPassField);
            return otherPassField.disabled || value == otherPassField.getValue();
        }
        return true;
    },
    passwordText: 'Passwords do not match'


});

Ext.define(null, {
    override: 'Ext.Component',
    /*Ext.util.Floating.setZIndex*/
    setZIndex: function(index) {
        var me = this;
        /** Changed */
        me.el.setZIndex(index + ((me.zIndexPriority || 0)*10000));
        /** End */
        index += 10;
        if (me.floatingDescendants) {
            index = Math.floor(me.floatingDescendants.setBase(index) / 100) * 100 + 10000;
        }
        return index;
    }
});

/*Chart related overrides*/
Ext.define(null, {
    override: 'Ext.chart.Chart',

    handleClick: function(name, e) {
        var me = this,
            position = me.getEventXY(e),
            seriesItems = me.series.items,
            i, ln, series,
            item;



        for (i = 0, ln = seriesItems.length; i < ln; i++) {
            series = seriesItems[i];
            if (/** Changed */series.skipWithinBoxCheck || /** End */Ext.draw.Draw.withinBox(position[0], position[1], series.bbox)) {
                if (series.getItemForPoint) {
                    item = series.getItemForPoint(position[0], position[1]);
                    if (item) {
                        series.fireEvent(name, item);
                    }
                }
            }
        }
    },

    onMouseMove: function(e) {
        var me = this,
            position = me.getEventXY(e),
            seriesItems = me.series.items,
            i, ln, series,
            item, last, storeItem, storeField;


        if (me.enableMask) {
            me.mixins.mask.onMouseMove.call(me, e);
        }


        for (i = 0, ln = seriesItems.length; i < ln; i++) {
            series = seriesItems[i];
            if (/** Changed */series.skipWithinBoxCheck || /** End */Ext.draw.Draw.withinBox(position[0], position[1], series.bbox)) {
                if (series.getItemForPoint) {
                    item = series.getItemForPoint(position[0], position[1]);
                    last = series._lastItemForPoint;
                    storeItem = series._lastStoreItem;
                    storeField = series._lastStoreField;

                    if (item !== last || item && (item.storeItem != storeItem || item.storeField != storeField)) {
                        if (last) {
                            series.fireEvent('itemmouseout', last);
                            delete series._lastItemForPoint;
                            delete series._lastStoreField;
                            delete series._lastStoreItem;
                        }
                        if (item) {
                            series.fireEvent('itemmouseover', item);
                            series._lastItemForPoint = item;
                            series._lastStoreItem = item.storeItem;
                            series._lastStoreField = item.storeField;
                        }
                    }
                }
            } else {
                last = series._lastItemForPoint;
                if (last) {
                    series.fireEvent('itemmouseout', last);
                    delete series._lastItemForPoint;
                    delete series._lastStoreField;
                    delete series._lastStoreItem;
                }
            }
        }
    },

    getInsets: function() {
        var me = this,
            insetPadding = me.insetPadding;

        return {
            top: /** Changed */me.insetPaddingTop || /** End */insetPadding,
            right: insetPadding,
            bottom: insetPadding,
            left: insetPadding
        };
    },

});

Ext.define(null, {
    override: 'Ext.chart.series.Series',

    getItemForPoint: function(x, y) {

        if (!this.items || !this.items.length || this.seriesIsHidden) {
            return null;
        }
        var me = this,
            items = me.items,
            bbox = me.bbox,
            item, i, ln;

        if (/** Changed */!me.skipWithinBoxCheck && /** End */!Ext.draw.Draw.withinBox(x, y, bbox)) {
            return null;
        }
        
        for (i = 0, ln = items.length; i < ln; i++) {
            if (items[i] && this.isItemInPoint(x, y, items[i], i)) {
                return items[i];
            }
        }

        return null;
    }
});

Ext.define('Ext.chart.series.Events', {
    extend: 'Ext.chart.series.Scatter',
    type: 'events',
    alias: 'series.events',
    
    getPaths: function() {
        var me = this,
            chart = me.chart,
            enableShadows = chart.shadow,
            store = chart.getChartStore(),
            data = store.data.items,
            i, ln, record,
            group = me.group,
            bounds = me.bounds = me.getBounds(),
            bbox = me.bbox,
            xScale = bounds.xScale,
            yScale = bounds.yScale,
            minX = bounds.minX,
            minY = bounds.minY,
            boxX = bbox.x,
            boxY = bbox.y,
            boxHeight = bbox.height,
            items = me.items = [],
            attrs = [],
            reverse = me.reverse,
            x, y, xValue, yValue, sprite;

        for (i = 0, ln = data.length; i < ln; i++) {
            record = data[i];
            xValue = record.get(me.xField);
            yValue = record.get(me.yField);


            if (typeof yValue == 'undefined' || (typeof yValue == 'string' && !yValue)
                || xValue == null || yValue == null) {
                continue;
            }

            if (typeof xValue == 'string' || typeof xValue == 'object' && !Ext.isDate(xValue)) {
                xValue = i;
            }
            if (typeof yValue == 'string' || typeof yValue == 'object' && !Ext.isDate(yValue)) {
                yValue = i;
            }
            if (reverse) {
                x = boxX + bbox.width - ((xValue - minX) * xScale);
            } else {
                x = boxX + (xValue - minX) * xScale;
            }
            /** Changed */
            y = boxY + me.yOffset;
            /** End */
            attrs.push({
                x: x,
                y: y
            });

            me.items.push({
                series: me,
                value: [xValue, yValue],
                point: [x, y],
                storeItem: record
            });


            if (chart.animate && chart.resizing) {
                sprite = group.getAt(i);
                if (sprite) {
                    me.resetPoint(sprite);
                    if (enableShadows) {
                        me.resetShadow(sprite);
                    }
                }
            }
        }
        return attrs;
    },

    isItemInPoint: function(x, y, item) {
        var point,
            tolerance = /** Changed */14/** End */,
            abs = Math.abs;

        function dist(point) {
            var dx = abs(point[0] - x),
                dy = abs(point[1] - y);
            return Math.sqrt(dx * dx + dy * dy);
        }
        point = item.point;
        /** Changed */
        return (point[0] - tolerance <= x && point[0]  >= x &&
            point[1] <= y && point[1] + tolerance >= y);
        /** End */
    }


});

Ext.define(null, {
    override: 'Ext.chart.Shape',
    
    image: function (surface, opts) {
        var o = Ext.apply({
            type: 'image'
        }, opts);
        o.x = 0 - opts.width;
        return surface.add(o);
    }
    
});


Ext.define(null, {
    override: 'Ext.draw.engine.Svg',

    //fixes problem "chart tooltip stays visible after mouseleave"
    onMouseLeave: function(e) {
        /** Changed */
        //if (!this.el.parent().getRegion().contains(e.getPoint())) {
        /** End */
            this.fireEvent('mouseleave', e);
        /** Changed */
        //}
        /** End */
    }

});

Ext.define(null, {
    override: 'Ext.chart.series.Line',

    highlightLine: true,
    highlightItem: function() {
        var me = this,
            line = me.line;
        /** Changed */
        me.callSuper(arguments);
        /** End */
        if (line && !me.highlighted) {
            if (!('__strokeWidth' in line)) {
                line.__strokeWidth = parseFloat(line.attr['stroke-width']) || 0;
            }
            if (line.__anim) {
                line.__anim.paused = true;
            }
            /** Changed */
            if (me.highlightLine) {
                line.__anim = new Ext.fx.Anim({
                    target: line,
                    to: {
                        'stroke-width': line.__strokeWidth + 3
                    }
                });
            }
            /** End */
            me.highlighted = true;
        }
    },
});

Ext.define(null,{
    override: 'Ext.chart.series.Bar',

    maxWidth: 30,
    getBarGirth: function() {
        var me = this,
            store = me.chart.getChartStore(),
            column = me.column,
            ln = store.getCount(),
            gutter = me.gutter / 100,
            padding,
            property;

        property = (column ? 'width' : 'height');

        if (me.style && me.style[property]) {
            me.configuredColumnGirth = true;
            return +me.style[property];
        }

        padding = me.getPadding();
        /** Changed */
        var width = (me.chart.chartBBox[property] - padding[property]) / (ln * (gutter + 1) - gutter);
        if (me.maxWidth && width > me.maxWidth) {
            me.configuredColumnGirth = true;
            return +me.maxWidth;
        }
        return width;
        /** End */
    }
});

Ext.define(null,{
    override: 'Ext.Template',

    pctLabel: function(growth, growthPct, size, fixed, mode){
        var cost, cls, res, growthPctHR;
        mode = mode || 'default';
        cost = Ext.String.htmlEncode(Ext.util.Format.currency(Math.round(mode !== 'default' ? Math.abs(growth) : growth), null, 0));
        cls = 'x-label-pct ' + (growth > 0 ? 'increase' : 'decrease');
        growthPctHR = growthPct;

        if (size === 'large') {
            cls += ' large';
        }
        if (fixed) {
            cls += ' fixed';
        }

        if (growthPctHR >= 1000000) {
            growthPctHR = Math.round(growthPctHR/1000000) + 'M';
        } else if (growthPctHR >= 1000) {
            growthPctHR = Math.round(growthPctHR/1000) + 'K';
        }

        if (mode === 'invert') {
            res = '<span class="' + cls + '" data-qtip="' + (growthPct !== null ? (growth > 0 ? '+' : '-') + growthPctHR + '%' : '') + '"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-icon-' + (growth > 0 ? 'increase' : 'decrease') + '-small" />&nbsp;' + cost + '</span>';
        } else if (mode === 'noqtip') {
            res = '<span class="' + cls + '"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-icon-' + (growth > 0 ? 'increase' : 'decrease') + '-small" />&nbsp;' + (growthPct !== null ? growthPctHR + '% (' + cost + ')' : cost) + '</span>';
        } else {
            cost = (growth > 0 ? '+ ': '') + cost;
            res = '<span class="' + cls + '" data-qtip="Growth: ' + cost + '"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-icon-' + (growth > 0 ? 'increase' : 'decrease') + '-small" />&nbsp;' + (growthPct !== null ? growthPctHR + '%' : '') + '</span>';
        }
        return res;
    },

    currency: function(value, sign, decimals) {
        var val;
        val = decimals ? value : Math.round(value);
        return Ext.util.Format.currency(val, sign || null, decimals || 0);
    },

    itemCost: function(item) {
        var html =
            '<div style="white-space:nowrap">' +
                '<div style="margin-bottom: 4px">' +
                    '<span style="font-weight:bold;font-size:110%;">' + item.name + '</span> ' +
                        (item.type === 'farms' && item.id !== 'everything else' ? ' (id:' + item.id + ')' : '') +
                        (item.label ? '&nbsp;&nbsp;&nbsp;&nbsp;<i>' + item.label + '</i>' : '') +
                '</div>' +
                '<span style="font-weight:bold;font-size:140%">' + this.currency(item.cost)+ '</span> ('+item.costPct+'% of ' + (item.interval ? item.interval+'\'s ' : '') + 'total)' +
            '</div>';
        return html;
    },

    farmInfo: function(data, htmlEncode) {
        var res = '';
        if (data.environment) {
            res = '<b>Farm:</b> ' + data.name + ' (id:' + data.id + ')' +
                  '<br/><b>Environment:</b> ' + data.environment.name + ' (id:' + data.environment.id + ')';
        }
        return htmlEncode ? Ext.String.htmlEncode(res) : res;
    },

    instanceTypeInfo: function(data) {
        var res = [];
        res.push(data['vcpus'] + ' vCPUs');
        res.push(Math.round(data['ram']/1024) + 'GB RAM');
        if (data['disk']) res.push(data['disk'] + 'GB ' + data['type']);
        if (data['note']) res.push(data['note']);
        return res.join(', ');
    }
});

Ext.define(null, {
    override: 'Ext.toolbar.Toolbar',

    enableParamsCapture: false,
    ignoredLoadParams: [],
    forceApplyParams: false,

    getPreparedParams: function (params) {
        var me = this;

        var ignoredParamsNames = me.ignoredLoadParams;
        var filteredParams = Ext.clone(params);
        var ignoredParams = {};

        Ext.Array.each(ignoredParamsNames, function (ignoredParamName) {
            if (filteredParams.hasOwnProperty(ignoredParamName)) {
                ignoredParams[ignoredParamName] = filteredParams[ignoredParamName];
                delete filteredParams[ignoredParamName];
            }
        });

        return { filteredParams: filteredParams, ignoredParams: ignoredParams };
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

        return me;
    },

    getFilters: function (paramKeys) {
        var me = this;

        var filters = {};

        Ext.Array.each(paramKeys, function (param) {
            var filter = me.down('#' + param);

            if (filter) {
                filters[param] = filter;
            }
        });

        return filters;
    },

    fillFilters: function (filters, params) {
        params = Ext.clone(params);

        var me = this;

        var paramKeys = Object.getOwnPropertyNames(params);
        var filterName;

        for (filterName in filters) {
            if (filters.hasOwnProperty(filterName) && filters[filterName].setValue) {
                filters[filterName].setValue(params[filterName] || 0);

                //delete params[filterName];
                //paramKeys.splice(paramKeys.indexOf(filterName), 1);
            }
        }

        return me;
    },

    fillFilterField: function (params) {
        var me = this;

        var filterField = me.down('filterfield');

        if (filterField && !Ext.Object.isEmpty(params)) {
            filterField.setParseValue(params);
            filterField.clearButton.show();
            filterField.loadParams = params;
        }

        return me;
    },

    getUnusedParams: function (params, filters) {
        var unusedParams = Ext.clone(params);
        var paramName;

        for (paramName in unusedParams) {
            if (unusedParams.hasOwnProperty(paramName) && filters.hasOwnProperty(paramName)) {
                delete unusedParams[paramName];
            }
        }

        return unusedParams;
    },

    cutUrl: function (ignoredParams) {
        var url = document.URL;
        var uncutUrl = url.substring(0, url.indexOf('#'));
        var cutUrl = url.substring(url.indexOf('#'), url.length);
        var clearUrl = cutUrl.substring(0, cutUrl.indexOf('?'));

        if (clearUrl) {
            clearUrl = uncutUrl + clearUrl;

            if (!Ext.Object.isEmpty(ignoredParams)) {
                clearUrl = clearUrl + '?' + Ext.Object.toQueryString(ignoredParams);
            }

            history.replaceState(null, null, clearUrl);
        }
    },

    applyParams: function (params) {
        var me = this;

        params = me.getPreparedParams(params);

        var filteredParams = params.filteredParams;
        var ignoredParams = params.ignoredParams;
        var filteredParamsNames = Object.getOwnPropertyNames(filteredParams);

        if (filteredParamsNames.length || !Ext.Object.isEmpty(ignoredParams)) {

            me.clearParams(me.loadParams).clearParams(me.parsedParams);

            Ext.apply(me.store.proxy.extraParams, filteredParams);
            Ext.apply(me.store.proxy.extraParams, ignoredParams);

            var filters = me.getFilters(filteredParamsNames);
            me.fillFilters(filters, filteredParams);

            var unusedParams = me.getUnusedParams(filteredParams, filters);
            me.fillFilterField(unusedParams);

            me.loadParams = filteredParams;

            me.cutUrl(ignoredParams);
        }
    },

    onRender: function() {
        var me = this;

        me.callParent(arguments);

        var panel = me.up('panel');

        if (me.enableParamsCapture && panel) {
            panel.on('applyparams', me.applyParams, me);
        }
    }
});

Ext.define(null, {
    override: 'Ext.grid.column.Column',

    doSort: function() {
        var tablePanel = this.up('tablepanel'),
            store = tablePanel.store;

        // if remoteSort, reset to first page when sort was changed [UI-271]
        if (store.remoteSort) {
            store.currentPage = 1;
        }

        this.callParent(arguments);
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

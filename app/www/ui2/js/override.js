//constants must be defined before overrides
Scalr.constants = {
    iopsMin: 100,
    iopsMax: 20000,
    ebsMinStorageSize: 1,
    ebsMaxStorageSize: 1024,
    ebsGp2MinStorageSize: 1,
    ebsGp2MaxStorageSize: 16384,
    ebsIo1MinStorageSize: 4,
    ebsIo1MaxStorageSize: 16384,
    ebsMaxIopsSizeRatio: 30,
    redisPersistenceTypes: [
        ['aof', 'Append Only File'],
        ['snapshotting', 'Snapshotting'],
        ['nopersistence', 'No persistence']
    ],
    gceDiskTypes: [
        ['pd-standard', 'Standard'],
        ['pd-ssd', 'SSD']
    ]
};
Scalr.constants.ebsTypes = [
    ['standard', 'Standard EBS (Magnetic)'],
    ['gp2', 'General Purpose (SSD)'],
    ['io1', 'Provisioned IOPS (' + Scalr.constants.iopsMin + ' - ' + Scalr.constants.iopsMax + '):']
];

//bugfix v5.1: mousewheel doesn't fire in firefox
Ext.$vendorEventRe = /^(Moz.+|MS.+|DOMMouseScroll)/;

// override default values
Ext.define(null, {
    override: 'Ext.tip.Tip',
    shadow: false
});

Ext.define(null, {
    override: 'Ext.menu.Menu',

    childMenuOffset: [1, 0],
    menuOffset: [0, 1],
    shadow: false
});

Ext.define(null, {
    override: 'Ext.form.Field',
    labelSeparator: ''
});

Ext.define(null, {
    override: 'Ext.form.FieldContainer',
    maskOnDisable: true,
    labelSeparator: ''
});

Ext.define(null, {
    override: 'Ext.form.action.Action',
    submitEmptyText: false
});

Ext.define(null, {
    override: 'Ext.grid.Panel',
    enableColumnMove: false
});

Ext.define(null, {
    override: 'Ext.picker.Date',
    shadow: false
});

Ext.define(null, {
    override: 'Ext.form.field.Picker',
    pickerOffset: [0, 1],

    initComponent: function() {
        this.addCls('x-picker-field');//see ext-theme-scalr/sass/src/form/field/Text.scss
        this.callParent(arguments);
    }
});

Ext.define(null, {
    override: 'Ext.Component',

    statics: {
        //method has been copied from ExtJS 5.1.1, remove after switching to 5.1.1
        //see Ext.grid.NavigationModel override
        fromElement: function (node, topmost) {
            var cmpIdAttr = Ext.Component.componentIdAttribute,
                target = Ext.getDom(node),
                cache = Ext.ComponentManager.all,
                cmpId, cmp;

            if (topmost) {
                topmost = Ext.getDom(topmost);
            } else {
                topmost = document.body.parentNode;
            }

            while (target && target.nodeType === 1 && target !== topmost) {
                cmpId = target.getAttribute(cmpIdAttr) || target.id;
                if (cmpId) {
                    cmp = cache[cmpId];
                    if (cmp) {
                        return cmp;
                    }
                }
                target = target.parentNode;
            }

            return null;
        }
    }
});

Ext.define(null, {
    override: 'Ext.form.action.Submit',
    buildForm: function() {
        var result = this.callParent(arguments),
            formEl = Ext.fly(result.formEl);
        formEl.createChild({
            tag: 'input',
            type: 'hidden',
            name: 'X-Scalr-Scope',
            value: Scalr.scope
        });
        formEl.createChild({
            tag: 'input',
            type: 'hidden',
            name: 'X-Scalr-EnvId',
            value: Scalr.user.envId
        });
        return result;
    }
});


// override code
Ext.define(null, {
    override: 'Ext.tip.ToolTip',
    dismissDelay: 0,

    onRender: function() {
        var me = this;
        me.callParent(arguments);

        //bugfix v5.1: anchor visibility mode
        if (me.anchorEl) {
            me.anchorEl.setVisibilityMode(Ext.Element.DISPLAY);
        }

        //we need clickable tooltips
        me.mon(me.el, {
            mouseover: function () {
                if (me.clickable) { //why here? see data-qclickable
                    this.clearTimer('hide');
                    this.clearTimer('dismiss');
                }
            },
            mouseout: function () {
                if (me.clickable) {
                    this.clearTimer('show');
                    if (this.autoHide !== false) {
                        this.delayHide();
                    }
                }
            },
            mousedown: function(e) {
                if (me.clickable) {
                    if (e.getTarget(null, 1, true).is('a')) {
                        this.delayHide();
                    }
                }
            },
            scope: me
        });
    },

    //better vertical alignment of the left&right anchor
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

    show: function(xy) {
        var me = this;
        this.callParent();
        if (this.hidden === false) {
            /** Changed */
            //bugfix v5.1: tooltips with autowidth - wrong calculation of the width (taken from extjs 4.2)
            me.setPagePosition(-10000, -10000);
            /** End */
            if (me.anchor) {
                me.anchor = me.origAnchor;
            }
            if (!me.calledFromShowAt) {
                me.showAt(xy || me.getTargetXY());
            }
        }
    },

    //handle hideAction === 'destroy' properly
    onDocMouseDown: function(e) {
        if (this.hideAction === 'destroy') {
            this.destroy();
        } else {
            this.callParent(arguments);
        }
    },

    //bugfix v5.1: hideAction=destroy doesn't work when use dismissDelay
    showAt: function(xy) {
        var me = this;
        me.lastActive = new Date();
        me.clearTimers();
        me.calledFromShowAt = true;
        if (!me.isVisible()) {
            this.callParent(arguments);
        }
        if (me.isVisible()) {
            me.setPagePosition(xy[0], xy[1]);
            if (me.constrainPosition || me.constrain) {
                me.doConstrain();
            }
            me.toFront(true);
            me.el.syncUnderlays();
            if (me.dismissDelay && me.autoHide !== false) {
                /*Changed*/
                me.dismissTimer = Ext.defer(me[me.hideAction], me.dismissDelay, me);
                /*End*/
            }
        }
        delete me.calledFromShowAt;
    },


});

Ext.define(null, {
    override: 'Ext.tip.QuickTip',
    maxWidth: 600,

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
            me.resumeLayouts(true);//bugfix v5.1: we must to resume layouts before calling me.getAlignToXY
            if (me.anchor && !target.align) {
                me.constrainPosition = false;
            } else if (target.align) {
                var addOffset;
                //bugfix v5.1: we must leave some space for anchor
                if (target.align === 'r-l') {
                    addOffset = [-13, 0];
                } else if (target.align === 'l-r') {
                    addOffset = [13, 0];
                }
                xy = me.getAlignToXY(target.el, target.align, addOffset);
                me.constrainPosition = false;
            }else{
                me.constrainPosition = true;
            }
            //clickable qtip attribute support
            if (!me.initialConfig.hasOwnProperty('clickable')) {
                me.clickable = target.el.getAttribute('data-qclickable') == 1;
            }
            /** end */
        }
        me.callParent([xy]);
    },

    show: function() {
        if (this.anchor) {
            //bugfix v5.1: without this assignment anchor will not be shown at all(in case of using data-anchor attribute)
            this.origAnchor = this.anchor;
        }
        this.callParent(arguments);
    }
});

Ext.define(null, {
    override: 'Ext.form.FieldSet',

    maskOnDisable: true,

    // fieldset's title is not legend (simple div)
    createLegendCt: function () {
        var me = this,
            items = [],
            legend = {
                xtype: 'container',
                baseCls: me.baseCls + '-header',
                cls: (me.headerCls || '') + ' ' + Ext.dom.Element.unselectableCls,
                // use container layout so we don't get the auto layout innerCt/outerCt
                layout: 'container',
                ui: me.ui,
                id: me.id + '-legend',
                //autoEl: 'legend',
                ariaRole: null,
                ariaLabelledBy: '.' + me.baseCls + '-header-text',
                items: items,
                ownerCt: me,
                shrinkWrap: true,
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
    },

    //fixes collapse, expand, beforecollapse, beforeexpand events double call when clicking on fieldset title with checkbox
    setExpanded: function() {
        if (this.checkboxCmp) this.checkboxCmp.suspendEvents(false);
        this.callParent(arguments);
        if (this.checkboxCmp) this.checkboxCmp.resumeEvents();
    },

    setTitle: function (title, description) {
        return this.callParent([title + (description ? '<span class="x-fieldset-header-description">' + description + '</span>' : '')]);
    }

});

Ext.define(null, {
    override: 'Ext.form.field.ComboBox',
    matchFieldWidth: false,
    autoSetValue: false,
    autoSetSingleValue: false,

    defaultListConfig: {
        loadMask: false,
        shadow: false
    },
    shadow: false,
    expandOnClick: true, //show dropdown when click on ediable combobox

    restoreValueOnBlur: false,//same as forceSelection but allows to set non-existent values
    restoreValueOnBlurValue: null,

    enableKeyEvents: true,
    autoSearch: true,

    initComponent: function() {
        var me = this;

        me.enteredValue = '';

        me.clearEnteredValue = Ext.util.TaskManager.newTask({
            run: function () {
                me.enteredValue = '';
            },
            scope: me,
            interval: 1000
        });

        me.callParent(arguments);

        if (!me.value && me.autoSetValue && me.store.getCount() > 0) {
            me.setValue(me.store.first().get(me.valueField));
        } else if (!me.value && me.autoSetSingleValue && me.store.getCount() == 1) {
            me.setValue(me.store.first().get(me.valueField));
        }

        if (me.expandOnClick) {
            me.on('afterrender', function(){
                me.inputEl.on('click', function(){
                    if (!me.disabled && !me.readOnly && me.editable) {
                        me.onTriggerClick();
                        me.expand();
                    }
                });
            }, me, {priority: 1});
        }

        if (me.restoreValueOnBlur) {
            me.on('specialkey', function(comp, e){
                if(!me.readOnly && e.getKey() === e.ESC && this.restoreValueOnBlurValue !== this.getValue()){
                    if (me.queryFilter) {//if we don't clear filter, record may not be found and raw value will be set
                        me.getStore().getFilters().remove(me.queryFilter);
                    }
                    this.setValue(this.restoreValueOnBlurValue);
                }
            }, me, {priority: 1});
        }
    },

    setValueOnData: function() {
        var me = this;
        if (!me.value && me.autoSetSingleValue && me.store.getCount() == 1) {
            me.setValue(me.store.first().get(me.valueField));
        } else {
            me.callParent(arguments);
        }
    },

    setValue: function(value) {
        var me = this;
        if (!value && me.autoSetSingleValue && me.store.getCount() == 1) {
            value = me.store.first().get(me.valueField);
        }

        return me.callParent([value]);
    },

    onChange: function(newVal, oldVal) {
        if (this.restoreValueOnBlur) {
            if (this.findRecordByValue(newVal) || !this.restoreValueOnBlurHasFocus) {
                this.restoreValueOnBlurValue = newVal;
            }
        }
        this.callParent(arguments);
    },

    onFocus: function() {
        if (this.restoreValueOnBlur) {
            this.restoreValueOnBlurHasFocus = true;
        }
        this.callParent(arguments);
    },

    onBlur: function() {
        var me = this;

        if (me.autoSearch) {
            me.enteredValue = '';
            me.clearEnteredValue.stop();
        }

        if (this.restoreValueOnBlur) {
            if (!this.disabled && !this.readOnly && !this.findRecordByValue(this.getValue()) && this.restoreValueOnBlurValue !== this.getValue()) {
                if (me.queryFilter) {//if we don't clear filter, record may not be found and raw value will be set
                    me.getStore().getFilters().remove(me.queryFilter);
                }
                this.setValue(this.restoreValueOnBlurValue);
            }
            this.restoreValueOnBlurHasFocus = false;
        }

        this.callParent(arguments);
    },

    beforeQuery: function(queryPlan) {
        var me = this;

        // Allow beforequery event to veto by returning false
        if (me.fireEvent('beforequery', queryPlan) === false) {
            queryPlan.cancel = true;
        }

        /* Changed: we dont want to do local raw query if combobox is not editable */
        if (!me.editable && me.queryMode === 'local' && !Ext.isEmpty(queryPlan.query) && Ext.isDefined(queryPlan.rawQuery)) {
            queryPlan.cancel = true;
        }
        /* Changed */

        // Allow beforequery event to veto by returning setting the cancel flag
        else if (!queryPlan.cancel) {

            // If the minChars threshold has not been met, and we're not forcing an "all" query, cancel the query
            if (queryPlan.query.length < me.minChars && !queryPlan.forceAll) {
                queryPlan.cancel = true;
            }
        }
        return queryPlan;
    },

    alignPicker: function() {
        var me = this,
            picker = me.getPicker();

        if (me.isExpanded) {
            if (! me.matchFieldWidth) {
                //picker width shouldn't be smaller then field width when matchFieldWidth==false
                picker.el.applyStyles('min-width: ' + me.bodyEl.getWidth() + 'px');
            }
        }
        this.callParent(arguments);
    },

    onFieldMutation: function(e) {
        var me = this,
            key = e.getKey(),
            isDelete = key === e.BACKSPACE || key === e.DELETE,
            rawValue = me.inputEl.dom.value,
            len = rawValue.length;
        if ((rawValue !== me.lastMutatedValue || isDelete) && key !== e.TAB) {
            me.lastMutatedValue = rawValue;
            me.lastKey = key;
            //don't hide dropdown when removing last character
            if (/*Changed*/ /*len &&*/ /*End*/ (e.type !== 'keyup' || (!e.isSpecialKey() || isDelete))) {
                if (!len) {
                    /* temp fix */
                    me.fireEvent('clearvalue', me);
                    //me.callParent([e]);
                }
                me.doQueryTask.delay(me.queryDelay);
            } else {
                if (!len && (!key || isDelete)) {
                    if (!me.multiSelect) {
                        me.value = null;
                        me.displayTplData = undefined;
                    }
                    me.collapse();
                    if (me.queryFilter) {
                        me.changingFilters = true;
                        me.store.removeFilter(me.queryFilter, true);
                        me.changingFilters = false;
                    }
                }
                me.callParent([
                    e
                ]);
            }
        }
    },

    selectRecord: function (key, enteredValue) {
        var me = this;

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
        var isNodeExists = !Ext.isEmpty(recordEl);

        if (isNodeExists) {
            boundList.getNavigationModel().setPosition(
                parseInt(recordEl.getAttribute('data-recordindex'))
            );
        }

        me.lastRecord = record || me.lastRecord;
        me.lastEnteredKey = key;

        return isNodeExists;
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
    }
});

Ext.define(null, {
    override: 'Ext.grid.column.Column',
    // hide control menu
    menuDisabled: true,
    border: false,

    // extjs doesn't save column parameter, use dataIndex as stateId by default
    getStateId: function () {
        return this.dataIndex || this.stateId || this.headerId;
    },

    // mark sortable columns
    onRender: function() {
        this.callParent();
        if (this.sortable) {
            this.addCls('x-column-header-sortable');
            this.titleEl.createChild({
                tag: 'div',
                cls: 'x-column-header-sort'
            });
        }
    },

    //prevent row focusing when clicking on link or row checker
    processEvent: function(type, view, cell, recordIndex, cellIndex, e) {
        if (type === 'mousedown') {
            if (e.getTarget('a') || (view.hasSelectedRecordPlugin && e.getTarget('.x-grid-row-checker'))) {
                e.preventDefault();
                return false;
            }
        } else if (type === 'click') {
            var target = e.getTarget('a'); //console.log(.getAttribute('href'))
            if (target && Ext.get(target).getAttribute('href') != '#') {
                e.stopPropagation();
                return false;
            } else if (view.hasSelectedRecordPlugin && e.getTarget('.x-grid-row-checker')) {
                e.suppressFocusEvents = true;
            }
        }

        return this.callParent(arguments);
    }

});

Ext.define(null, {
    override: 'Ext.view.Table',
    enableTextSelection: true,
    stripeRows: false,

    selectedRecordFocusCls: 'x-grid-item-selectedrecordfocus',
    // focus (highlight) a whole row, not only cell
    initComponent: function() {
        this.callParent(arguments);
        if (!this.ownerGrid.disableSelection && this.selectedRecordFocusCls) {
            this.on('focuschange', function(comp, old, newd) {
                var me = this;
                if (old) {
                    var el = Ext.fly(me.getRowByRecord(old));
                    if (el) el.parent('table').removeCls(me.selectedRecordFocusCls);
                }

                if (newd) {
                    Ext.each(me.getNodes(), function(node){
                        var el = Ext.fly(node);
                        if (el) el.removeCls(me.selectedRecordFocusCls);
                    });
                    var el = Ext.fly(me.getRowByRecord(newd));
                    if (el) el.parent('table').addCls(me.selectedRecordFocusCls);
                }
            });
        }
    },

    //we dont want to focus first row on view focus
    onFocusEnter: function(e) {
        var me = this;
        if (e.event.getTarget(null, null, true) === me.el) {
            e.event.stopEvent();
            e.event.getTarget().blur();
            return;
        }
        me.callParent(arguments);
    },

    //do not allow to resize columns to the total width smaller than panel width
    autoSizeColumn: function(header) {
        if (Ext.isNumber(header)) {
            header = this.getGridColumns[header];
        }
        if (header) {
            if (header.isGroupHeader) {
                header.autoSize();
                return;
            }
            /*Changed*/
            var currentWidth = header.getWidth(),
                newWidth = this.getMaxContentWidth(header);
            if (newWidth > currentWidth) {
                delete header.flex;
                header.setWidth(newWidth);
            }
            /*End*/
        }
    },

    //bugfix v5.1: don't call refreshScroll until viewReady
    privates: {
        refreshScroll: function () {
            if (this.viewReady && this.body.dom) {
                this.callParent(arguments);
            }
        }
    }


});

Ext.define(null, {
    override: 'Ext.container.Container',

    /*used in scriptfield*/
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

    resetFieldValues: function() {
        Ext.each(this.query('[isFormField]'), function(field){
            field.reset();
        });
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

    ebssize: function(value, field) {
        if (!Ext.isEmpty(value)) {
            var ebsType = field.getEbsType ? field.getEbsType() : 'standard';
            value = value*1;
            switch (ebsType) {
                case 'standard':
                    if (value < Scalr.constants.ebsMinStorageSize || value > Scalr.constants.ebsMaxStorageSize) {
                        this.ebssizeText = 'Value must be between ' + Scalr.constants.ebsMinStorageSize + ' and ' + Scalr.constants.ebsMaxStorageSize;
                        return false;
                    }
                    break;
                case 'io1':
                    if (value < Scalr.constants.ebsIo1MinStorageSize || value > Scalr.constants.ebsIo1MaxStorageSize) {
                        this.ebssizeText = 'Value must be between ' + Scalr.constants.ebsIo1MinStorageSize + ' and ' + Scalr.constants.ebsIo1MaxStorageSize;
                        return false;
                    } else if (field.getEbsIops) {
                        if (Scalr.utils.getMinStorageSizeByIops(field.getEbsIops()) > value) {
                            this.ebssizeText = 'IOPS:GB ratio must be <= 30 ';
                            return false;
                        }
                    }
                    break;
                case 'gp2':
                    if (value < Scalr.constants.ebsGp2MinStorageSize || value > Scalr.constants.ebsGp2MaxStorageSize) {
                        this.ebssizeText = 'Value must be between ' + Scalr.constants.ebsGp2MinStorageSize + ' and ' + Scalr.constants.ebsGp2MaxStorageSize;
                        return false;
                    }
                    break;
            }
        }
        return true;
    },
    ebssizeMask: /[0-9]/,
/*
    validator: function(value){
        var minValue = 1,
            container = this.up('container');
        if (container.down('[name="db.msr.data_storage.ebs.type"]').getValue() === 'io1') {
            minValue = Scalr.utils.getMinStorageSizeByIops(container.down('[name="db.msr.data_storage.ebs.iops"]').getValue());
        }
        if (value*1 > Scalr.constants.ebsMaxStorageSize) {
            return 'Maximum value is ' + Scalr.constants.ebsMaxStorageSize + '.';
        } else if (value*1 < minValue) {
            return 'Minimum value is ' + minValue + '.';
        }
        return true;
    },

 */
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
    override: 'Ext.form.Panel',
    isRecordLoading: 0,
    loadRecord: function(record) {
        this.isRecordLoading++;
        this.suspendLayouts();

        if (this.fireEvent.apply(this, ['beforeloadrecord', record]) === false) {
            this.resumeLayouts(true);
            this.isRecordLoading--;
            return false;
        }
        var ret = this.getForm().loadRecord(record);

        this.fireEvent.apply(this, ['loadrecord', record]);

        this.resumeLayouts(true);
        this.setVisible(true);
        this.isRecordLoading--;
        this.fireEvent.apply(this, ['afterloadrecord', record]);
        return ret;
    },

    resetRecord: function() {
        var record = this.getForm().getRecord();
        this.isRecordLoading++;
        this.fireEvent.apply(this, ['resetrecord', record]);
        this.getForm().reset(true);
        this.setVisible(false);
        this.isRecordLoading--;
    },

    updateRecord: function(record) {
        record = record || this._record;
        var ret = this.callParent(arguments);
        this.fireEvent('updaterecord', record);
        return ret;
    }

});

Ext.define(null, {
    override: 'Ext.button.Button',

    //in some cases(ex: leftmenu) we don't want to add pressedCls on mousedown
    onMouseDown: function(e) {
        var me = this;

        if (Ext.isIE) {
            me.getFocusEl().focus();
        }

        if (!me.disabled && e.button === 0) {
            Ext.button.Manager.onButtonMousedown(me, e);
            /*Changed*/
            if (!me.disableMouseDownPressed) {
                me.addCls(me._pressedCls);
            }
            /*End*/
        }
    }
});

Ext.define(null, {
    override: 'Ext.tree.Panel',

    //disable animation due to unpredictable chrome tab crashing since v30
    animate: false,

    showCheckboxesAtRight: false,

    afterLayout: function () {
        var me = this;

        me.callParent(arguments);
        return;

        if (me.showCheckboxesAtRight) {
            me.addCls('x-tree-panel-show-checkboxes-at-right');
        }

        var rootNode = me.getRootNode();

        if (rootNode) {
            var view = me.getView();

            Ext.each(rootNode.childNodes, function (node) {
                var firstChildNode = Ext.get(view.getNode(node.firstChild));

                if (firstChildNode) {
                    var className = firstChildNode.dom.className;

                    if (className.search('x-grid-tree-node-leaf') === -1) {
                        var iconImage = firstChildNode.down('.x-tree-expander');

                        if (iconImage) {
                            iconImage.addCls('x-tree-expander-inception');
                        }
                    }
                }
            });
        }
    }
});

//some useful template methods
Ext.define(null,{
    override: 'Ext.Template',

    pctLabel: function(growth, growthPct, size, fixed, mode, round){
        var cost, cls, res, growthPctHR;
        mode = mode || 'default';
        round = round === undefined ? true : round;

        cost = Ext.String.htmlEncode(Ext.util.Format.currency(mode !== 'default' ? Math.abs(growth) : growth, null, round ? 0 : 2));
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
            res = '<span class="' + cls + '" data-qtip="' + (growthPct !== null ? (growth > 0 ? '+' : '-') + growthPctHR + '%' : '') + '"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-icon-' + (growth > 0 ? 'increase' : 'decrease') + '" />&nbsp; ' + cost + '</span>';
        } else if (mode === 'noqtip') {
            res = '<span class="' + cls + '"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-icon-' + (growth > 0 ? 'increase' : 'decrease') + '" />&nbsp; ' + (growthPct !== null ? growthPctHR + '% (' + cost + ')' : cost) + '</span>';
        } else {
            cost = (growth > 0 ? '+ ': '') + cost;
            res = '<span '+(growthPct === null ? 'style="text-align:center"' : '')+' class="' + cls + '" data-qtip="Growth: ' + cost + '"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-icon-' + (growth > 0 ? 'increase' : 'decrease') + '" />&nbsp; ' + (growthPct !== null ? growthPctHR + '%' : '') + '</span>';
        }
        return res;
    },

    currency: function(value, sign, decimals) {
        var val;
        val = decimals ? value : Math.round(value);
        return Ext.util.Format.currency(val, sign || null, decimals || 0);
    },

    currency2: function(value, beautify) {
        var result = Ext.util.Format.currency(value);
        return beautify ? '<span class="scalr-ui-analytics-currency">' + result.replace(Ext.util.Format.decimalSeparator, '<span class="small">.') + '</span></span>' : result;
    },

    itemCost: function(item, round) {
        var html;
        round = round === undefined ? true : round;
        html =
            '<div style="white-space:nowrap">' +
                '<div style="margin-bottom: 4px">' +
                    '<span '+(item.cls?'class="'+item.cls+'"':'')+' style="font-size:110%;'+(item.color?'color:#'+item.color+';':'')+'">' + item.name + '</span> ' +
                        (item.type === 'farms' && item.id !== 'everything else' ? ' (id:' + item.id + ')' : '') +
                        (item.label ? '&nbsp;&nbsp;&nbsp;&nbsp;<i>' + item.label + '</i>' : '') +
                '</div>' +
                '<span style="font-size:140%">' + this[round ? 'currency' : 'currency2'](item.cost)+ '</span> ('+item.costPct+'% of ' + (item.interval ? item.interval+'\'s ' : '') + 'total)' +
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
    },

    fitMaxLength: function(s, length) {
        var flen;
        if (s.length > length + 1) {
            flen = Math.ceil(length/2);
            s = s.substr(0, flen) + '...' + s.substr(s.length - flen, flen);
        }
        return s;
    },

    getOsById: function(osId, arch) {
        var os = Scalr.utils.getOsById(osId);
        if (os) {
            arch = arch || '';
            return '<span title="'+os.name+arch+'"><img class="x-icon-osfamily-small x-icon-osfamily-small-'+os.family+'" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;' + os.name + arch + '</span>';
        } else {
            return osId;
        }

    },

    beautifyEngine: function (engineName, engineVersion) {
        var fullEngineText = Scalr.utils.beautifyEngineName(engineName) + '&nbsp;' + engineVersion;

        if (engineName.indexOf('-') !== -1) {
            engineName = engineName.substring(0, engineName.indexOf('-'));
        }

        return '<span data-qtip="' + fullEngineText + '"><img class="x-icon-engine-small x-icon-engine-small-' +
            engineName + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;' +
            fullEngineText + '</span>';
    }

});

Ext.define(null, {
    override: 'Ext.form.Panel',

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

Ext.define(null, {
    override: 'Ext.slider.Single',

    onRender: function() {
        var me = this;
        me.callParent(arguments);

        //display slider value inline
        Ext.DomHelper.append(this.thumbs[0].el, '<div class="x-slider-thumb-inner"></div>', true);
        if (me.showValue) {
            this.sliderValue = Ext.DomHelper.append(this.thumbs[0].el, '<div class="x-slider-value">'+this.getValue()+'</div>', true);
            this.on('change', function(comp, value){
                if (this.sliderValue !== undefined) {
                    this.sliderValue.setHtml(value);
                }
            });
        }
    }
});


Ext.override(Ext.form.field.Base, {
    allowChangeable: true,
    allowChangeableMsg: 'You can set this value only once',
    tooltipText: null,
    validateOnBlur: false,

    initComponent: function() {
        this.callParent(arguments);

        this.on('specialkey', function(field, e) {
            var key = e.getKey();

            if (key === e.ENTER && !field.isExpanded) {
                var form = field.up('form');
                if (form) {
                    var button = form.down('#buttonSubmit');
                    if (button) {
                        button.handler();
                    }
                }
                return;
            }

            if (key === e.BACKSPACE && (field.readOnly || (!field.editable && field.isExpanded === true))) {
                e.stopEvent();
                return;
            }

            if ((key === e.UP || key === e.DOWN) && field.isExpanded === true) {
                e.stopEvent();
                return;
            }
        });

        if (! this.allowChangeable) {
            this.cls += ' x-form-notchangable-field';
            this.tooltipText = this.allowChangeableMsg;
        }
    },

    afterRender: function() {
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

    /**
     * Protect from XSS in validator error message
     */
    setActiveErrors: function (errors) {
        var me = this;

        errors = Ext.Array.map(Ext.Array.from(errors), Ext.String.htmlEncode);
        me.callParent([errors]);
    }
});

Ext.define(null, {
    override: 'Ext.form.field.Text',

    initComponent: function() {
        var me = this;
        if (me.hideInputOnReadOnly) {
            me.readOnlyCls += ' x-input-hide-on-readonly';
        }
        me.callParent(arguments);
    }
});

Ext.define(null, {
    override: 'Ext.form.field.Display',

    initComponent: function() {
        var me = this;
        me.maybeAddFieldIcons();
        me.callParent(arguments);
    },

    //use fieldicons plugin to show icons in x-form-field-(info|warning|governance)
    maybeAddFieldIcons: function() {
        var me = this;
        Ext.each(['info', 'warning', 'governance'], function(icon) {
            if (me.hasCls('x-form-field-' + icon)) {
                me.plugins = Ext.isArray(me.plugins) ? me.plugins : (me.plugins ? [me.plugins] : []);
                me.plugins.push({
                    ptype: 'fieldicons',
                    position: 'over',
                    icons: [icon]
                });
                return false;
            }
        });
    }
});

Ext.define('Ext.data.ModelWithInternalId', {
    extend: 'Ext.data.Model',
    idProperty: 'extInternalId'
});

Ext.define(null, {
    override: 'Ext.grid.plugin.BufferedRenderer',
    init: function() {
        var me = this;
        me.callParent(arguments);
        //buffered grid auto height
        me.grid.on('added', function(comp, ct) {
            ct.on('resize', function() {
                me.grid.setHeight(this.getHeight());
            });
        });
    },

    onStoreClear: function() {
        this.scrollTop = 1;//bugfix v5.1: bufferedrendrer never resets scrollTop when store reloading
        this.callParent(arguments);
    }
});

Ext.define(null, {
    override: 'Ext.view.View',

    //sometimes we don't want to remember lastSelected
    deselectAndClearLastSelected: function() {
        var selModel = this.getSelectionModel();
        selModel.deselectAll();
        selModel.lastSelected = null;
    },

    //bugfix v5.1: arrow keys don't work in widget column with textfield (see governance->ec2->tags grid for example)
    handleEvent: function(e) {
        var me = this,
            isKeyEvent = me.keyEventRe.test(e.type),
            nm = me.getNavigationModel();
        e.view = me;
        if (isKeyEvent) {
            e.item = nm.getItem();
            e.record = nm.getRecord();
        }
        if (!e.item) {
            e.item = e.getTarget(me.itemSelector);
        }
        if (e.item && !e.record) {
            e.record = me.getRecord(e.item);
        }
        if (me.processUIEvent(e) !== false) {
            me.processSpecialEvent(e);
        }
        /*Changed*/
        if (isKeyEvent && ((e.getKey() === e.SPACE || e.isNavKeyPress(true)) && !Ext.fly(e.target).isInputField())) {
        /*End*/
            e.preventDefault();
        }
    }

});


Ext.getDetachedBody();//bugfix v5.1: sometimes Ext.detachedBodyEl not exists when Extjs trying to use it

Ext.define(null, {
    override: 'Ext.grid.column.Widget',

    privates: {
        //bugfix v5.1: record is not a single record but array of records
        onItemRemove: function(record, index, item) {
            var me = this,
                liveWidgets = me.liveWidgets,
                records = Ext.isArray(record) ? record : [record],
                widget;
            Ext.each(records, function(record) {
                if (me.rendered && record && (widget = liveWidgets[record.internalId])) {
                    delete liveWidgets[record.internalId];
                    me.freeWidgetStack.unshift(widget);
                    widget.$widgetRecord = widget.$widgetColumn = null;
                    Ext.detachedBodyEl.dom.appendChild((widget.el || widget.element).dom);
                }
            });
        }
    }
});

Ext.define(null, {
    override: 'Ext.chart.AbstractChart',

    //bugfix v5.1: getRefItems returns axis and series which aren't components and thus don't inherit isFocusable method
    getRefItems: function() {
        return [];
    },

    //bugfix v5.1: series tooltip sometimes stucks, hide them all on chart mouseleave
    initComponent: function() {
        this.callParent(arguments);
        this.on('mouseleave', function(){
            Ext.each(this.getSeries(), function(series) {
                var tooltip = series.getConfig('tooltip', true);
                if (tooltip) {
                    tooltip.hide();
                }
            });
        }, this, {element: 'el'});
    },

    /*For Cost Analytics events series */
    allowSeriesOverflowY: false,
    getItemForPoint: function(x, y) {
        var me = this,
            seriesList = me.getSeries(),
            mainRect = me.getMainRect(),
            ln = seriesList.length,
            i = me.hasFirstLayout ? ln - 1 : -1,
            series, item;
        /*Changed*/
        if (!me.allowSeriesOverflowY && !(mainRect && x >= 0 && x <= mainRect[2] && y >= 0 && y <= mainRect[3])) {
            return null;
        }
        /*End*/
        for (; i >= 0; i--) {
            series = seriesList[i];
            item = series.getItemForPoint(x, y);
            if (item) {
                return item;
            }
        }
        return null;
    }
});

Ext.define(null, {
    override: 'Ext.chart.CartesianChart',

    /*For Cost Analytics events series */
    performLayout: function() {
        this.resizing++;
        this.callParent();
        this.suspendThicknessChanged();
        var me = this,
            chartRect = me.getSurface('chart').getRect(),
            width = chartRect[2],
            height = chartRect[3],
            axes = me.getAxes(),
            axis,
            seriesList = me.getSeries(),
            series, axisSurface, thickness,
            insetPadding = me.getInsetPadding(),
            innerPadding = me.getInnerPadding(),
            surface, gridSurface,
            shrinkBox = Ext.apply({}, insetPadding),
            mainRect, innerWidth, innerHeight, elements, floating, floatingValue, matrix, i, ln,
            isRtl = me.getInherited().rtl,
            flipXY = me.getFlipXY();
        if (width <= 0 || height <= 0) {
            return;
        }
        for (i = 0; i < axes.length; i++) {
            axis = axes[i];
            axisSurface = axis.getSurface();
            floating = axis.getFloating();
            floatingValue = floating ? floating.value : null;
            thickness = axis.getThickness();
            switch (axis.getPosition()) {
                case 'top':
                    axisSurface.setRect([
                        0,
                        shrinkBox.top + 1,
                        width,
                        thickness
                    ]);
                    break;
                case 'bottom':
                    axisSurface.setRect([
                        0,
                        height - (shrinkBox.bottom + thickness),
                        width,
                        thickness
                    ]);
                    break;
                case 'left':
                    axisSurface.setRect([
                        shrinkBox.left,
                        0,
                        thickness,
                        height
                    ]);
                    break;
                case 'right':
                    axisSurface.setRect([
                        width - (shrinkBox.right + thickness),
                        0,
                        thickness,
                        height
                    ]);
                    break;
            }
            if (floatingValue === null) {
                shrinkBox[axis.getPosition()] += thickness;
            }
        }
        width -= shrinkBox.left + shrinkBox.right;
        height -= shrinkBox.top + shrinkBox.bottom;
        mainRect = [
            shrinkBox.left,
            shrinkBox.top,
            width,
            height
        ];
        shrinkBox.left += innerPadding.left;
        shrinkBox.top += innerPadding.top;
        shrinkBox.right += innerPadding.right;
        shrinkBox.bottom += innerPadding.bottom;
        innerWidth = width - innerPadding.left - innerPadding.right;
        innerHeight = height - innerPadding.top - innerPadding.bottom;
        me.setInnerRect([
            shrinkBox.left,
            shrinkBox.top,
            innerWidth,
            innerHeight
        ]);
        if (innerWidth <= 0 || innerHeight <= 0) {
            return;
        }
        me.setMainRect(mainRect);
        me.getSurface().setRect(mainRect);
        for (i = 0 , ln = me.surfaceMap.grid && me.surfaceMap.grid.length; i < ln; i++) {
            gridSurface = me.surfaceMap.grid[i];
            gridSurface.setRect(mainRect);
            gridSurface.matrix.set(1, 0, 0, 1, innerPadding.left, innerPadding.top);
            gridSurface.matrix.inverse(gridSurface.inverseMatrix);
        }
        for (i = 0; i < axes.length; i++) {
            axis = axes[i];
            axisSurface = axis.getSurface();
            matrix = axisSurface.matrix;
            elements = matrix.elements;
            switch (axis.getPosition()) {
                case 'top':
                case 'bottom':
                    elements[4] = shrinkBox.left;
                    axis.setLength(innerWidth);
                    break;
                case 'left':
                case 'right':
                    elements[5] = shrinkBox.top;
                    axis.setLength(innerHeight);
                    break;
            }
            axis.updateTitleSprite();
            matrix.inverse(axisSurface.inverseMatrix);
        }
        /*Changed*/
        var mainRect1 = mainRect.slice(0);
        mainRect1[3] += mainRect1[1];
        mainRect1[1] = 0;
        /*End*/
        for (i = 0 , ln = seriesList.length; i < ln; i++) {
            series = seriesList[i];
            surface = series.getSurface();
            /*Changed*/
            surface.setRect(me.allowSeriesOverflowY ? mainRect1 : mainRect);
            /*End*/
            if (flipXY) {
                if (isRtl) {
                    surface.matrix.set(0, -1, -1, 0, innerPadding.left + innerWidth, innerPadding.top + innerHeight);
                } else {
                    surface.matrix.set(0, -1, 1, 0, innerPadding.left, innerPadding.top + innerHeight);
                }
            } else {
                /*Changed*/
                surface.matrix.set(1, 0, 0, -1, innerPadding.left, me.allowSeriesOverflowY ? mainRect1[3] : innerPadding.top + innerHeight);
                /*End*/
            }
            surface.matrix.inverse(surface.inverseMatrix);
            /*Changed*/
            series.getOverlaySurface().setRect(me.allowSeriesOverflowY ? mainRect1 : mainRect);
            /*End*/
        }
        me.redraw();
        me.onPlaceWatermark(chartRect[2], chartRect[3]);
        this.resizing--;
        this.resumeThicknessChanged();
    },
});

Ext.define(null, {
    override: 'Ext.draw.Surface',

    /*For Cost Analytics events series */
    getEventXY: function(e) {
        var me = this,
            isRtl = me.getInherited().rtl,
            pageXY = e.getXY(),
            container = me.el.up(),
            xy = container.getXY(),
            rect = me.getRect() || me.emptyRect,
            result = [],
            width;
        if (isRtl) {
            width = container.getWidth();
            result[0] = xy[0] - pageXY[0] - rect[0] + width;
        } else {
            result[0] = pageXY[0] - xy[0] - rect[0];
        }
        /*Changed*/
        result[1] = pageXY[1] - xy[1] - (me.ownerCt.allowSeriesOverflowY ? 0 :rect[1]);
        /*End*/
        return result;
    }
});

Ext.define(null, {
    override: 'Ext.chart.axis.Axis',

    //bugfix v5.1: setting majorTickSteps causes incorrect axis labels values calculation
    getRange: function() {
        var majorTickSteps = this.getMajorTickSteps(),
            result;
        this.setMajorTickSteps(0);
        result = this.callParent(arguments);
        this.setMajorTickSteps(majorTickSteps);
        return result;
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

Ext.override(Ext.grid.plugin.CellEditing, {
    //farmDesigner -> network tab -> elasticIP grid
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

Ext.define(null, {
    override: 'Ext.data.ProxyStore',

    preventLoading: false,

    clearProxyParams: function (keys) {
        var me = this;

        var proxyParams = me.getProxy().extraParams;

        Ext.Array.each(keys, function (key) {
            delete proxyParams[key];
        });

        return me;
    },

    clearAllProxyParams: function () {
        var me = this;

        Ext.Object.clear(
            me.getProxy().extraParams
        );

        return me;
    },

    applyProxyParams: function (params) {
        var me = this;

        var preventLoading = me.preventLoading;
        var proxy = me.getProxy();

        Ext.apply(proxy.extraParams, params);

        if (!preventLoading) {
            if (me.isContinuousStore) {
                me.clearAndLoad();
            } else if (proxy.type === 'ajax' || proxy.type === 'cachedrequest') {
                me.removeAll();
                me.load();
            } else {
                me.load();
            }
        }

        return me;
    }
});

Ext.define(null, {
    override: 'Ext.data.ChainedStore',

    clearProxyParams: function (keys) {
        var me = this;

        me.source.clearProxyParams(keys);

        return me;
    },

    clearAllProxyParams: function () {
        var me = this;

        me.source.clearAllProxyParams();

        return me;
    },

    applyProxyParams: function (params) {
        var me = this;

        me.source.applyProxyParams(params);

        return me;
    }
});

Ext.define(null, {
    override: 'Ext.grid.plugin.RowExpander',

    getHeaderConfig: function() {
        var config = this.callParent(arguments);
        config['width'] = 50;

        return config;
    }
});

// hide menu, when user click on href and item has child menu
Ext.define(null, {
    override: 'Ext.menu.Item',

    onClick: function(e) {
        var me = this, clickResult = me.callParent(arguments);

        if (me.href && e.type == 'click') {
            me.deferHideParentMenus();
        }

        return clickResult;
    }
});

Ext.define(null, {
    override: 'Ext.util.SorterCollection',

    //this will allow to add primary sorter to grid, see analytics.js permanentSorter
    addSort: function(property, direction, mode) {
        var me = this,
            sorter;
        if (property && Ext.isString(property)) {
            if ((sorter = me.get(property)) && !direction) {
                direction = sorter.getDirection() === 'ASC' ? 'DESC' : 'ASC';
            }
        }
        this.callParent(arguments);
    }
});

Ext.define(null, {
    override: 'Ext.util.MixedCollection',

    //bugfix v5.1: incorrect scope
    createComparator: function(sorters) {
        return sorters && sorters.length ? function(r1, r2) {
            var result = sorters[0].sort(r1, r2),
                length = sorters.length,
                i = 1;
            // While we have not established a comparison value,
            // loop through subsequent sorters asking for a comparison value
            for (; !result && i < length; i++) {
                /*Changed*/
                result = sorters[i].sort(r1, r2);
                /*End*/
            }
            return result;
        } : function() {
            return 0;
        };
    }
});

Ext.define(null, {
    override: 'Ext.form.field.File',
    clearOnSubmit: false,

    buttonConfig: {
        iconCls: 'x-btn-icon-folder',
        text: '',
        margin: '0 0 0 12'
    },

    setValue: function (value) {
        Ext.form.field.File.superclass.setValue.call(this, value);
    },

    //bugfix v5.1: we shouldn't reset field if clearOnSubmit==false
    extractFileInput: function() {
        var me = this,
            fileInput;
        if (me.rendered) {
            fileInput = me.button.fileInputEl.dom;
            /*Changed*/
            if (me.clearOnSubmit) {
                me.reset();
            } else {
                me.button.reset(false);
                me.fileInputEl = me.button.fileInputEl;
            }
            /*End*/
        } else {
            fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.className = Ext.baseCSSPrefix + 'hidden-display';
            fileInput.name = me.getName();
        }
        return fileInput;
    }

});

Ext.define(null, {
    override: 'Ext.view.TableLayout',

    //bugfix v5.1: incorrect height calculation causes invisible empty text
    measureContentHeight: function(ownerContext) {
        var owner = this.owner,
            bodyDom = owner.body.dom,
            emptyTextDom,
            bodyHeight;
        /*Changed*/
        if (!owner.getViewRange().length) {
        /*End*/
            emptyTextDom = owner.el.down('.' + owner.ownerCt.emptyCls, true);
            bodyDom = emptyTextDom || bodyDom;
        }
        bodyHeight = bodyDom ? bodyDom.offsetHeight : 0;
        if (ownerContext.headerContext.state.boxPlan.tooNarrow) {
            bodyHeight += Ext.getScrollbarSize().height;
        }
        return bodyHeight;
    }
});


/*
    Ext.form.field.Tag

    bugfix v5.1. emptyText breaks the new Ext.form.field.Tag component:

        - The control won't take the focus
        - The keyboard won't be usable until one option is selected with the mouse
        - The empty text itself is misaligned

    bugfix v5.1: expand/collapse behavior
 */
Ext.define(null, {
    override: 'Ext.form.field.Tag',

    updateValue: function () {
        var me = this;

        me.callParent();

        me.applyEmptyText();
    },

    onFocus: function () {
        var me = this;

        me.callParent(arguments);

        me.applyEmptyText();
    },

    onBlur: function () {
        var me = this;

        me.callParent(arguments);

        me.applyEmptyText();
    },

    onExpand: function () {
        var me = this;

        me.callParent();

        if (!Ext.isEmpty(me.emptyText) && Ext.isEmpty(me.value)) {
            me.applyEmptyText(true);
            me.focus();
        }
    },

    afterRender: function () {
        var me = this;

        me.callParent(arguments);

        me.applyEmptyText();
    },

    applyEmptyText : function (hideEmptyText) {
        var me = this,
            emptyText = me.emptyText,
            emptyEl = me.emptyEl,
            inputEl = me.inputEl,
            listWrapper = me.listWrapper,
            emptyCls = me.emptyCls,
            emptyInputCls = me.emptyInputCls,
            isEmpty;

        if (me.rendered && emptyText) {
            isEmpty = Ext.isEmpty(me.value) && !me.hasFocus;
            /* Changed */
            if (isEmpty && !hideEmptyText) {
             /* End */
                inputEl.dom.value = '';
                emptyEl.setHtml(emptyText);
                emptyEl.addCls(emptyCls);
                emptyEl.removeCls(emptyInputCls);
                listWrapper.addCls(emptyCls);
                inputEl.addCls(emptyInputCls);
            } else {
                emptyEl.addCls(emptyInputCls);
                emptyEl.removeCls(emptyCls);
                listWrapper.removeCls(emptyCls);
                inputEl.removeCls(emptyInputCls);
            }
            me.autoSize();
        }
    },

    onTriggerClick: function(preventCollapse) {
        var me = this;
        if (!me.readOnly && !me.disabled) {
            /* Changed */
            if (me.isExpanded && preventCollapse !== true) {
            /* Changed */
                me.collapse();
            } else {
                if (me.triggerAction === 'all') {
                    me.doQuery(me.allQuery, true);
                } else if (me.triggerAction === 'last') {
                    me.doQuery(me.lastQuery, true);
                } else {
                    me.doQuery(me.getRawValue(), false, true);
                }
            }
        }
    },

    onItemListClick: function(e) {
        var me = this,
            selectionModel = me.selectionModel,
            itemEl = e.getTarget(me.tagItemSelector),
            closeEl = itemEl ? e.getTarget(me.tagItemCloseSelector) : false;

        if (me.readOnly || me.disabled) {
            return;
        }

        e.stopPropagation();

        if (itemEl) {
            if (closeEl) {
                me.removeByListItemNode(itemEl);
                if (me.valueStore.getCount() > 0) {
                    me.fireEvent('select', me, me.valueStore.getRange());
                }
            } else {
                me.toggleSelectionByListItemNode(itemEl, e.shiftKey);
            }
            // If not using touch interactions, focus the input
            if (!Ext.supports.TouchEvents) {
                me.inputEl.focus();
            }
        } else {
            if (selectionModel.getCount() > 0) {
                selectionModel.deselectAll();
            }
            me.inputEl.focus();
            if (me.triggerOnClick) {
                /* Changed */
                me.onTriggerClick(true);
                /* Changed */
            }

        }
    },

    //error qtip is not visible fix
    privates: {
        getActionEl: function() {
            return /*this.inputEl || */this.el;
        }
    }
});

// we don't need to load store after applying sorters, so block it (we'll call load later manually)
Ext.define(null, {
    override: 'Ext.panel.Table',

    applyState: function(state) {
        var me = this;

        if (state.storeState) {
            me.store.blockLoadAfterSorters = true;
        }

        me.callParent(arguments);
    }
});

Ext.define(null, {
    override: 'Ext.panel.Table',

    getState: function() {
        var state = this.callParent(arguments);
        state = this.addPropertyToState(state, 'autoRefresh', this.autoRefresh);
        return state;
    }
});


Ext.define(null, {
    override: 'Ext.data.AbstractStore',

    onSorterEndUpdate: function() {
        var me = this;

        if (me.blockLoadAfterSorters) {
            delete me.blockLoadAfterSorters;
            return;
        }

        me.callParent(arguments);
    }
});

/**
 * bugfix v5.1.0 Something removes dom element from container -> remove ext element, so ExtJS creates new one
 */
Ext.define(null, {
    override: 'Ext.grid.plugin.BufferedRenderer',

    stretchView: function() {
        var me = this;

        if (me.stretcher && !me.stretcher.dom) {
            delete me.stretcher;
        }

        me.callParent(arguments);
    }
});

Ext.define(null, {
    override: 'Ext.grid.NavigationModel',

    //avoid row focusing when clicking on row checker(suppressFocusEvents)
    onCellClick: function(view, cell, cellIndex, record, row, recordIndex, clickEvent) {
        if (this.position.isEqual(clickEvent.position)) {
            this.fireNavigateEvent(clickEvent);
        } else {
            /*Changed*/
            this.setPosition(clickEvent.position, null, clickEvent, clickEvent.suppressFocusEvents);
            /*End*/
        }
    },

    //SCALRCORE-1514
    //method has been copied from ExtJS 5.1.1, remove after switching to 5.1.1
    onCellMouseDown: function(view, cell, cellIndex, record, row, recordIndex, mousedownEvent) {
        var parentEvent = mousedownEvent.parentEvent,
            cmp = Ext.Component.fromElement(mousedownEvent.target, cell);

        if (cmp && cmp.isFocusable && cmp.isFocusable()) {
            return;
        }

        if (!parentEvent || parentEvent.type !== 'touchstart') {
            this.setPosition(mousedownEvent.position, null, mousedownEvent);
        }
    },


    //mousedown on rowbody causes losing grid scroll position
    onItemMouseDown: function(view, record, item, index, mousedownEvent) {
        if (!mousedownEvent.getTarget('.x-grid-rowbody') || view.allowRowBodyMouseDown) {
            this.callParent(arguments);
        }
    }
});

/**
 * bugfix v5.1.0 Copy cls like 'x-grid-color-*' to rowBody for correct background
 */
Ext.define(null, {
    override: 'Ext.grid.feature.RowBody',

    getAdditionalData: function(data, idx, record, orig) {
        var rowCls = [];
        Ext.each(orig.rowClasses, function(cls) {
            if (cls.substring(0, 17) == 'x-grid-row-color-')
                rowCls.push(cls);
        });

        return { rowBodyDivCls: rowCls.join(' ') };
    },

    cleanup: function(rows, rowValues) {
        var me = this;
        me.callParent(arguments);
        rowValues.rowBodyDivCls = null;
    }
});

Ext.define(null, {
    override: 'Ext.picker.Month',

    //fix allows to use Ext.picker.Month in monthfield - see analytics.js
    initEvents: function() {
        var me = this;
        me.callParent(arguments);
        if (!me.focusable) {
            me.el.on({
                mousedown: function(e) {e.preventDefault();}
            });
        }
    },

    //allows to disable months and years which exceed min - max date
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

Ext.define(null, {
    override: 'Ext.form.field.Checkbox',

    //prevent to enable disabled checkbox when setting readOnly = false
	setReadOnly: function(readOnly) {
		var me = this,
			inputEl = me.inputEl;
		if (inputEl) {
			// Set the button to disabled when readonly
			inputEl.dom.disabled = readOnly || me.disabled;
		}
        /*Changed*/
		me[readOnly ? 'addCls' : 'removeCls'](me.readOnlyCls);
		me.readOnly = readOnly;
        me.fireEvent('writeablechange', me, readOnly);
        /*End*/
	}
});

/**
 * allow to set sort by 2 or more fields
 * if remoteSort, reset to first page when sort was changed [UI-271]
 */
Ext.define(null, {
    override: 'Ext.grid.column.Column',

    sort: function(direction) {
        var me = this,
            grid = me.up('tablepanel'),
            store = grid.store;

        //debugger;

        // Maintain backward compatibility.
        // If the grid is NOT configured with multi column sorting, then specify "replace".
        // Only if we are doing multi column sorting do we insert it as one of a multi set.
        // Suspend layouts in case multiple views depend upon this grid's store (eg lockable assemblies)
        Ext.suspendLayouts();
        me.sorting = true;

        /** CHANGED */
        // if remoteSort, reset to first page when sort was changed [UI-271]
        if (store.remoteSort) {
            store.currentPage = 1;
        }

        if (Ext.isFunction(me.multiSort)) {
            // direction always null, so reverse it manually
            var sorters = store.getSorters();
            if (sorters.length) {
                if (sorters.first().getDirection() == 'ASC')
                    direction = 'DESC';
            }

            me.multiSort(store, direction);
        } else {
            store.sort(me.getSortParam(), direction, grid.multiColumnSort ? 'multi' : 'replace');
        }
        /** END OF CHANGED */

        delete me.sorting;
        Ext.resumeLayouts(true);
    }
});

Ext.define(null, {
    override: 'Ext.grid.feature.Grouping',
    restoreGroupsState: false,

    disable: function() {
        var me = this;
        if (me.restoreGroupsState) {
            me.__restoreGroupsState = {};
            Ext.Object.each(me.groupCache, function(groupName, group){
                me.__restoreGroupsState[groupName] = group.isCollapsed;
            });
        }
        me.callParent(arguments);
    },

    enable: function() {
        var me = this;
        me.callParent(arguments);
        if (me.restoreGroupsState && Ext.isObject(me.__restoreGroupsState)) {
            Ext.Object.each(me.__restoreGroupsState, function(groupName, collapsed){
                me[collapsed ? 'collapse' : 'expand'](groupName);
            });
        }
    }

});

Ext.define(null, {
    override: 'Ext.grid.plugin.HeaderResizer',

    afterHeaderRender: function() {
        this.callParent(arguments);
        this.ownerGrid.on('resize', this.onGridResize, this);
    },

    onGridResize: function() {
        this.expandFlexColumns();
    },

    destroy: function() {
        this.callParent(arguments);
        this.ownerGrid.un('resize', this.onGridResize, this);
    },

    //resize columns to fit panel width when total columns width is smaller than panel width
    doResize: function() {
        this.callParent(arguments);
        this.expandFlexColumns();
    },

    expandFlexColumns: function() {
        Ext.suspendLayouts();
		var headerCt = this.headerCt,
			grid = headerCt.ownerCt || null,
            currentColumn = this.dragHd,
            columns = [];

		if (!grid) return;

		var columnsWidth = 0,
			panelWidth = grid.view.getWidth();

        Ext.each(this.headerCt.getVisibleGridColumns(), function(col){
            columnsWidth += col.getWidth();
            if (col.initialConfig.flex && col != currentColumn && !col.maxWidth) {
                columns.push(col);
            }
        });

        if (columns.length === 0 && currentColumn) {
            columns.push(currentColumn);
        }

		if (columns.length && panelWidth > columnsWidth) {
            var scrollWidth = grid.getView().el.dom.scrollHeight == grid.getView().el.getHeight() ? 0 : Ext.getScrollbarSize().width,
                deltaWidth = Math.floor((panelWidth - columnsWidth - scrollWidth)/columns.length);
            grid.suspendLayouts();
            for(var i=0, len=columns.length; i<len; i++) {
                var flex = columns[i].flex || null,
                    width = columns[i].width || null;
                columns[i].setWidth(columns[i].getWidth() + deltaWidth);
                if (flex && !width) {
                    columns[i].flex = flex;
                    delete columns[i].width;
                }
            }
            grid.resumeLayouts(true);
		}

        Ext.resumeLayouts(true);
    },

    //bugfix v5.1: unexpected grid reordering after resizing column
    onEnd: function() {
        this.callParent(arguments);
        this.headerCt.tempLock();//check this after upgrade to ExtJS 5.1.1(tempLock is not available there)
    }
});

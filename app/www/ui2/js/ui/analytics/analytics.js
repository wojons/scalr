Scalr.constants.distributionTypes = {
    1: 'compute', //see x-costanalytics-compute in admin/analytics/admin.css
    2: 'storage',
    3: 'bandwidth',
    4: 'other'
};

Ext.define('Scalr.ui.CostAnalyticsPeriod', {
    extend: 'Ext.container.Container',
    alias: 'widget.costanalyticsperiod',

    layout: 'hbox',

    mode: null, //week,month,quarter,year
    startDate: null,
    endDate: null,
    dailyModeEnabled: false,

    preservedValues: {},
    preservedValueId: 'admin',
    stopCalculateDateRange: 0,

    initComponent: function() {
        var me = this;

        me.afterChangeBuffered = Ext.Function.createBuffered(me.afterChange, 500);
        me.callParent(arguments);
        var modeField = me.down('#mode');
        if (me.simple) {
            modeField.remove(modeField.items.getAt(5));
        }
        if (!me.dailyModeEnabled) {
            modeField.remove(modeField.items.getAt(0));
        }
    },

    getValue: function() {
        return this.mode ? {
            mode: this.mode,
            startDate: this.startDate,
            endDate: this.endDate
        } : null;
    },

    setValue: function(mode, startDate, endDate) {
        var prevStartDate = this.startDate,
            prevEndDate = this.endDate,
            modeField = this.down('#mode');
        if (Ext.isObject(mode)) {
            startDate = mode.startDate;
            endDate = mode.endDate;
            mode = mode.mode;
        }
        this.mode = mode;
        if (!startDate) {
            this.calculateDateRange();
        } else {
            this.startDate = startDate;
            this.endDate = endDate;
        }
        if (mode === modeField.getValue() && (prevStartDate - this.startDate !== 0 || prevEndDate - this.endDate)) {
            modeField.setValue(null);
        }
        this.stopCalculateDateRange++;
        modeField.setValue(mode);
        this.stopCalculateDateRange--;
    },

    isCurrentValue: function(mode, startDate, endDate) {
        var result = false,
            range = this.getPeriodDateRange(mode, startDate);
        if (this.mode === mode) {
            if (!startDate) {
                startDate = range.startDate;
            }
            if (!endDate) {
                endDate = range.endDate;
            }

            result = this.startDate - startDate === 0 && this.endDate - endDate === 0;
        }
        return result;
    },

    getPeriodDateRange: function(mode, date) {
        var currentDate = Scalr.utils.Quarters.getDate(date),
            result = {};
        switch (mode) {
            case 'week':
                result.startDate = Ext.Date.subtract(currentDate, Ext.Date.DAY, currentDate.getDay());
                result.endDate = Ext.Date.add(result.startDate, Ext.Date.DAY, 6);
            break;
            case 'month':
                result.startDate = Ext.Date.getFirstDateOfMonth(currentDate);
                result.endDate = Ext.Date.getLastDateOfMonth(currentDate);
            break;
            case 'quarter':
                var dates = Scalr.utils.Quarters.getPeriodForDate(currentDate);
                result.startDate = dates['startDate'];
                result.endDate = dates['endDate'];
            break;
            case 'year':
                result.startDate = Scalr.utils.Quarters.getDate([currentDate.getFullYear(), 0, 1]);
                result.endDate = Scalr.utils.Quarters.getDate([currentDate.getFullYear(), 11, 31]);
            break;
            case 'day':
            case 'custom':
                result.startDate = currentDate;
                result.endDate = currentDate;
            break;
        }
        return result;
    },

    calculateDateRange: function(date) {
        var range = this.getPeriodDateRange(this.mode, date);
        if (this.mode === 'custom') {
            this.startDate = this.startDate || range.startDate;
            this.endDate = this.endDate || range.endDate;
        } else {
            this.startDate = range.startDate;
            this.endDate = range.endDate;
        }
    },

    selectNextPredefined: function(direction) {
        var me = this;
        switch (me.mode) {
            case 'day':
                me.startDate = Ext.Date.add(me.startDate, Ext.Date.DAY, direction*1);
                me.endDate = me.startDate;
            break;
            case 'week':
                me.startDate = Ext.Date.add(me.startDate, Ext.Date.DAY, direction*7);
                me.endDate = Ext.Date.add(me.endDate, Ext.Date.DAY, direction*7);
            break;
            case 'month':
                me.startDate = Ext.Date.add(me.startDate, Ext.Date.MONTH, direction);
                me.endDate = Ext.Date.getLastDateOfMonth(me.startDate);
            break;
            case 'quarter':
                var dates = Scalr.utils.Quarters.getPeriodForDate(me.startDate, direction);
                this.startDate = dates['startDate'];
                this.endDate = dates['endDate'];
            break;
            case 'year':
                me.startDate = Ext.Date.add(me.startDate, Ext.Date.YEAR, direction);
                me.endDate = Ext.Date.getLastDateOfMonth(Ext.Date.add(me.startDate, Ext.Date.MONTH, 11));
            break;
        }
        var inputField = me.down('#' + me.getInputIdForMode(me.mode));
        inputField.suspendEvents(false);
        inputField.setValue(inputField.itemId === 'predefinedInput' ? [me.startDate, me.endDate] : me.startDate);
        inputField.resumeEvents();
        me.onChange(true);
    },
    getInputIdForMode: function(mode) {
        switch (mode) {
            case 'week':
                return 'predefinedInput';
            default:
                return mode + 'Input';
        }
    },
    onSelectMode: function(mode) {
        var me = this;
        me.mode = mode;
        if (!me.stopCalculateDateRange) {
            me.calculateDateRange();
        }
        if (me.mode === 'custom') {
            var today = Scalr.utils.Quarters.getDate();
            me.down('#predefinedCt').hide();
            me.down('#customStartDate').setValue(me.startDate);
            me.down('#customEndDate').setValue(me.endDate > today ? today : me.endDate);
            me.down('#customCt').show();
        } else {
            var inputField = me.down('#' + me.getInputIdForMode(me.mode));
            inputField.suspendEvents(false);
            inputField.setValue(inputField.itemId === 'predefinedInput' ? [me.startDate, me.endDate] : me.startDate);
            inputField.resumeEvents();
            inputField.up().layout.setActiveItem(inputField);
            //if (!me.simple) {
                me.down('#predefinedCt').show();
            //}
            me.down('#customCt').hide();
            me.onChange();

        }
    },
    onChange: function(buffered) {
        this.down('#predefinedNext').setDisabled(this.endDate >= Scalr.utils.Quarters.getDate());
        this.preserveValue();
        this['afterChange' + (buffered ? 'Buffered' : '')]();

    },
    afterChange: function() {
        this.fireEvent('change', this.mode, this.startDate, this.endDate, Scalr.utils.Quarters.getPeriodForDate(this.startDate));
    },
    preserveValue: function() {
        this.preservedValues[this.preservedValueId] = this.getValue();
    },
    getPreservedValue: function() {
        return this.preservedValues[this.preservedValueId];
    },
    restorePreservedValue: function(defaultMode, forceChange) {
        var value = this.getPreservedValue();
        if (!value || this.simple && value.mode === 'custom') {
            value = {mode: defaultMode};
        }
        if (forceChange || !this.isCurrentValue(value.mode, value.startDate, value.endDate)) {
            this.suspendEvents(false);
            this.setValue(value);
            this.resumeEvents();
            this.onChange();
        }
    },
    items: [{
        xtype: 'buttongroupfield',
        itemId: 'mode',
        defaults: {
            flex: 1,
            minWidth: 70
        },
        margin: '0 12 0 0',
        items: [{
            text: 'Day',
            value: 'day'
        },{
            text: 'Week',
            value: 'week'
        },{
            text: 'Month',
            value: 'month'
        },{
            text: 'Quarter',
            value: 'quarter'
        },{
            text: 'Year',
            value: 'year'
        },{
            text: 'Custom',
            value: 'custom'
        }],
        listeners: {
            change: function(comp, value) {
                if (value) {
                    this.up('costanalyticsperiod').onSelectMode(value);
                }
            }
        }
    },{
        xtype: 'container',
        itemId: 'predefinedCt',
        layout: 'hbox',
        hidden: true,
        items: [{
            xtype: 'button',
            itemId: 'predefinedPrev',
            cls: 'x-btn-flag',
            iconCls: 'x-btn-icon-previous',
            margin: '0 6 0 0',
            handler: function() {
                this.up('costanalyticsperiod').selectNextPredefined(-1);
            }
        },{
            xtype: 'container',
            layout: 'card',
            //width: 160,
            items: [{
                xtype: 'textfield',
                itemId: 'predefinedInput',
                readOnly: true,
                width: 160,
                fieldStyle: 'text-align: center;color:#000!important;box-shadow:none',
                valueToRaw: function(value) {
                    if (!value) return value;
                    var rawValue;
                    switch (this.up('costanalyticsperiod').mode) {
                        case 'month':
                            rawValue = Ext.Date.format(value[0], 'F Y');
                        break;
                        /*case 'week':
                            rawValue = Ext.Date.format(value[0], 'M j') + Ext.Date.format(value[1], ' - M j, Y');
                            //rawValue = Ext.Date.format(value, 'Y \\week ' + Ext.Date.getWeekOfYear(value));
                        break;
                        case 'quarter':
                            rawValue = Ext.Date.format(value[0], 'M j') + Ext.Date.format(value[1], ' - M j, Y');
                            //rawValue = Ext.Date.format(value, 'Y \\Q' + (Math.floor(value.getMonth()/3)+1));
                        break;
                        case 'year':
                            rawValue = Ext.Date.format(value[0], 'Y');
                        break;*/
                        default:
                            rawValue = Ext.Date.format(value[0], 'M j') + Ext.Date.format(value[1], ' - M j, Y');
                        break;
                    }
                    return rawValue;
                }
            },{
                xtype: 'datefield',
                itemId: 'dayInput',
                fieldStyle: 'text-align:center',
                editable: false,
                maxValue: Scalr.utils.Quarters.getDate(),
                listeners: {
                    change: function(field, value){
                        var ct = this.up('costanalyticsperiod');
                        ct.calculateDateRange(value);
                        ct.onChange();
                    }
                }
            },{
                xtype: 'monthfield',
                itemId: 'monthInput',
                fieldStyle: 'text-align:center',
                editable: false,
                width: 170,
                maxValue: Scalr.utils.Quarters.getDate(),
                listeners: {
                    change: function(field, value){
                        var ct = this.up('costanalyticsperiod');
                        ct.calculateDateRange(value);
                        ct.onChange();
                    }
                }
            },{
                xtype: 'quarterfield',
                itemId: 'quarterInput',
                fieldStyle: 'text-align:center',
                width: 120,
                editable: false,
                maxValue: Scalr.utils.Quarters.getDate(),
                listeners: {
                    change: function(field, value){
                        var ct = this.up('costanalyticsperiod');
                        ct.calculateDateRange(value);
                        ct.onChange();
                    }
                }
            },{
                xtype: 'yearfield',
                itemId: 'yearInput',
                fieldStyle: 'text-align:center',
                width: 120,
                editable: false,
                maxValue: Scalr.utils.Quarters.getDate(),
                listeners: {
                    change: function(field, value){
                        var ct = this.up('costanalyticsperiod');
                        ct.calculateDateRange(value);
                        ct.onChange();
                    }
                }
            }]
        },{
            xtype: 'button',
            itemId: 'predefinedNext',
            cls: 'x-btn-flag',
            iconCls: 'x-btn-icon-next',
            margin: '0 0 0 6',
            handler: function() {
                this.up('costanalyticsperiod').selectNextPredefined(1);
            }
        }]
    },{
        xtype: 'container',
        itemId: 'customCt',
        hidden: true,
        layout: {
            type: 'hbox',
            align: 'middle'
        },
        items: [{
            xtype: 'datefield',
            itemId: 'customStartDate',
            vtype: 'daterange',
            daterangeCtId: 'customCt',
            endDateField: 'customEndDate',
            format: 'M j, Y',
            maxValue: Scalr.utils.Quarters.getDate(),
            editable: false,
            width: 140
        },{
            xtype: 'label',
            html: '&ndash;',
            margin: '0 6'
        },{
            xtype: 'datefield',
            itemId: 'customEndDate',
            vtype: 'daterange',
            daterangeCtId: 'customCt',
            startDateField: 'customStartDate',
            format: 'M j, Y',
            editable: false,
            maxValue: Scalr.utils.Quarters.getDate(),
            width: 140
        },{
            xtype: 'button',
            itemId: 'customBtn',
            text: 'Apply',
            margin: '0 0 0 12',
            width: 80,
            handler: function(comp) {
                var c = this.up('costanalyticsperiod');
                c.setValue('custom', comp.prev('#customStartDate').getValue(), comp.prev('#customEndDate').getValue());
                c.onChange();
            }
        }]
    },{
        xtype: 'button',
        itemId: 'refresh',
        iconCls: 'x-btn-icon-refresh',
        tooltip: 'Refresh',
        //cls: 'x-btn-paging-toolbar-small',
        handler: function() {
            this.up('costanalyticsperiod').onChange();
        },
        margin: '0 0 0 12'
    }]
});

Ext.define('Ext.picker.Quarter', {
    extend: 'Ext.picker.Month',
    alias: 'widget.quarterpicker',
    cls: 'x-quarterpicker',
    focusable: false,

    beforeRender: function(){
        var me = this,
            i = 0,
            months = ['Q1', 'Q2', 'Q3', 'Q4'],
            margin = me.monthMargin,
            style = '';

        if (me.padding && !me.width) {
            me.cacheWidth();
        }

        me.callParent();

        if (Ext.isDefined(margin)) {
            style = 'margin: 0 ' + margin + 'px;';
        }

        Ext.apply(me.renderData, {
            months: months,
            years: me.getYears(),
            showButtons: me.showButtons,
            monthStyle: style
        });
    },

    setValue: function(value){
        var me = this,
            active = me.activeYear,
            year;

        if (!value) {
            me.value = [null, null];
        } else if (Ext.isDate(value)) {
            var quarter = Scalr.utils.Quarters.getPeriodForDate(value);
            me.value = [quarter['quarter'] - 1, quarter['year']];
        } else {
            me.value = [value[0], value[1]];
        }

        if (me.rendered) {
            year = me.value[1];
            if (year !== null) {
                if ((year < active || year > active + me.yearOffset)) {
                    me.activeYear = year - me.yearOffset + 1;
                }
            }
            me.updateBody();
        }

        return me;
    },

    updateBody: function(){
        var me = this,
            years = me.years,
            months = me.months,
            yearNumbers = me.getYears(),
            cls = me.selectedCls,
            value = me.getYear(null),
            month = me.value[0],
            monthOffset = me.monthOffset,
            year, maxYear, minYear,
            monthItems, m, mr, mLen,
            yearItems, y, yLen, el,
            quarter;

        if (me.rendered) {
            years.removeCls(cls);
            months.removeCls(cls);

            if (me.maxDate) {
                maxYear = me.maxDate.getFullYear();
                quarter = Scalr.utils.Quarters.getPeriodForDate(me.maxDate)['quarter'];
            }
            if (me.minDate) {
                minYear = me.minDate.getFullYear();
            }


            if (quarter !== undefined) {
                monthItems = months.elements;
                mLen      = monthItems.length;
                for (m = 0; m < mLen; m++) {
                    el = Ext.fly(monthItems[m]);
                    if (value == maxYear && m+1 > quarter) {
                        el.parent().addCls('x-item-disabled');
                    } else {
                        el.parent().removeCls('x-item-disabled');
                    }

                }
            }

            yearItems = years.elements;
            yLen      = yearItems.length;

            for (y = 0; y < yLen; y++) {
                el = Ext.fly(yearItems[y]);

                year = yearNumbers[y];
                el.dom.innerHTML = year;
                if (year == value) {
                    el.addCls(cls);
                }

                if (maxYear && year > maxYear || minYear && year < minYear) {
                    el.parent().addCls('x-item-disabled');
                } else {
                    el.parent().removeCls('x-item-disabled');
                }

            }
            if (month !== null) {
                months.item(month).addCls(cls);
            }
        }
    },

    onMonthClick: function(target, isDouble){
        var me = this;
        me.value[0] = me.months.indexOf(target);
        me.updateBody();
        me.fireEvent('month' + (isDouble ? 'dbl' : '') + 'click', me, me.value);
        me.fireEvent('select', me, me.value);
    }


});

Ext.define('Ext.picker.Year', {
    extend: 'Ext.picker.Month',
    alias: 'widget.yearpicker',
    cls: 'x-yearpicker',
    yearOffset: 3,
    totalYears: 12,
    focusable: false,

    beforeRender: function(){
        var me = this,
            i = 0,
            months = [0],
            margin = me.monthMargin,
            style = '';

        if (me.padding && !me.width) {
            me.cacheWidth();
        }

        me.callParent();

        if (Ext.isDefined(margin)) {
            style = 'margin: 0 ' + margin + 'px;';
        }

        Ext.apply(me.renderData, {
            months: months,
            years: me.getYears(),
            showButtons: me.showButtons,
            monthStyle: style
        });
    },

    setValue: function(value){
        var me = this,
            active = me.activeYear,
            year;

        if (!value) {
            me.value = [null, null];
        } else if (Ext.isDate(value)) {
            me.value = [0, value.getFullYear()];
        } else {
            me.value = [value[0], value[1]];
        }

        if (me.rendered) {
            year = me.value[1];
            if (year !== null) {
                if ((year < active || year > active + me.yearOffset)) {
                    me.activeYear = year - me.yearOffset + 1;
                }
            }
            me.updateBody();
        }

        return me;
    },

    updateBody: function(){
        var me = this,
            years = me.years,
            yearNumbers = me.getYears(),
            cls = me.selectedCls,
            value = me.getYear(null),
            year, maxYear, minYear,
            yearItems, y, yLen, el;

        if (me.rendered) {
            years.removeCls(cls);

            if (me.maxDate) {
                maxYear = me.maxDate.getFullYear();
            }
            if (me.minDate) {
                minYear = me.minDate.getFullYear();
            }

            yearItems = years.elements;
            yLen      = yearItems.length;

            for (y = 0; y < yLen; y++) {
                el = Ext.fly(yearItems[y]);

                year = yearNumbers[y];
                el.dom.innerHTML = year;
                if (year == value) {
                    el.addCls(cls);
                }

                if (maxYear && year > maxYear || minYear && year < minYear) {
                    el.parent().addCls('x-item-disabled');
                } else {
                    el.parent().removeCls('x-item-disabled');
                }

            }
        }
    },

    getYears: function(){
        var me = this,
            offset = me.yearOffset,
            start = me.activeYear, // put the "active" year on the left
            end = start + offset,
            i = start,
            cols = Math.round(me.totalYears/me.yearOffset),
            years = [];

        for (; i < end; ++i) {
            for(var j=0;j<cols;j++) {
                years.push(i + offset*j);
            }
        }
        return years;
    },

    resolveOffset: function(index, offset){
        var cols = Math.ceil(this.totalYears/this.yearOffset);
        return Math.floor(index / cols) + (index%cols)*this.yearOffset;
    }

});

Ext.define('Scalr.ui.FormQuarterField', {
    extend: 'Ext.form.field.Date',
    alias: 'widget.quarterfield',

    valueToRaw: function(value) {
        var result = '', quarter;
        if (value) {
            quarter = Scalr.utils.Quarters.getPeriodForDate(value);
            result = quarter['shortTitle'];
        }
        return result;
    },

    rawToValue: function(value) {
        if (value && Ext.isString(value)) {
            var s = value.split(' '),
                quarter = Scalr.utils.Quarters.getPeriodForQuarter(s[0].replace('Q','')*1, s[1]*1);
            return quarter['startDate'];
        } else {
            return value;
        }
    },

    getErrors: function() {
        return [];
    },

    createPicker: function () {
        var me = this,
            format = Ext.String.format;
        return Ext.create('Ext.picker.Quarter', {
            pickerField: me,
            ownerCt: me.ownerCt,
            renderTo: document.body,
            floating: true,
            hidden: true,
            focusOnShow: true,
            minDate: me.minValue,
            maxDate: me.maxValue,
            disabledDatesRE: me.disabledDatesRE,
            disabledDatesText: me.disabledDatesText,
            disabledDays: me.disabledDays,
            disabledDaysText: me.disabledDaysText,
            format: me.format,
            showToday: me.showToday,
            startDay: me.startDay,
            minText: format(me.minText, me.formatDate(me.minValue)),
            maxText: format(me.maxText, me.formatDate(me.maxValue)),
            listeners: {
                select: {scope: me, fn: me.onSelect},
                monthdblclick: {scope: me, fn: me.onOKClick},
                yeardblclick: {scope: me, fn: me.onOKClick},
                OkClick: {scope: me, fn: me.onOKClick},
                CancelClick: {scope: me, fn: me.onCancelClick}
            },
            keyNavConfig: {
                esc: function () {
                    me.collapse();
                }
            }
        });
    },

    onCancelClick: function () {
        var me = this;
        me.selectMonth = null;
        me.collapse();
    },

    onOKClick: function () {
        var me = this;
        if (me.selectMonth) {
            me.setValue(me.selectMonth);
            me.fireEvent('select', me, me.selectMonth);
        }
        me.collapse();
    },

    onSelect: function (m, d) {
        var me = this;
        me.selectMonth = Scalr.utils.Quarters.getPeriodForQuarter(d[0] + 1, d[1])['startDate'];
    }

});

Ext.define('Scalr.ui.FormYearField', {
    extend: 'Ext.form.field.Date',
    alias: 'widget.yearfield',

    valueToRaw: function(value) {
        return value ? value.getFullYear() : '';
    },

    rawToValue: function(value) {
        if (value) {
            return Scalr.utils.Quarters.getDate(value+'-01-01');
        } else {
            return value;
        }
    },

    getErrors: function() {
        return [];
    },

    createPicker: function () {
        var me = this,
            format = Ext.String.format;
        return Ext.create('Ext.picker.Year', {
            pickerField: me,
            ownerCt: me.ownerCt,
            renderTo: document.body,
            floating: true,
            hidden: true,
            focusOnShow: true,
            minDate: me.minValue,
            maxDate: me.maxValue,
            disabledDatesRE: me.disabledDatesRE,
            disabledDatesText: me.disabledDatesText,
            disabledDays: me.disabledDays,
            disabledDaysText: me.disabledDaysText,
            format: me.format,
            showToday: me.showToday,
            startDay: me.startDay,
            minText: format(me.minText, me.formatDate(me.minValue)),
            maxText: format(me.maxText, me.formatDate(me.maxValue)),
            listeners: {
                select: {scope: me, fn: me.onSelect},
                monthdblclick: {scope: me, fn: me.onOKClick},
                yeardblclick: {scope: me, fn: me.onOKClick},
                OkClick: {scope: me, fn: me.onOKClick},
                CancelClick: {scope: me, fn: me.onCancelClick}
            },
            keyNavConfig: {
                esc: function () {
                    me.collapse();
                }
            }
        });
    },

    onCancelClick: function () {
        var me = this;
        me.selectMonth = null;
        me.collapse();
    },

    onOKClick: function () {
        var me = this;
        if (me.selectMonth) {
            me.setValue(me.selectMonth);
            me.fireEvent('select', me, me.selectMonth);
        }
        me.collapse();
    },

    onSelect: function (m, d) {
        var me = this;
        me.selectMonth = Scalr.utils.Quarters.getDate(d[1]+'-01-01');
    }

});

Ext.define('Scalr.ui.FormMonthField', {
    extend: 'Ext.form.field.Date',
    alias: 'widget.monthfield',

    initTime: '01 00:00:00',
    initTimeFormat: 'd H:i:s',
    format: 'F Y',
    createPicker: function () {
        var me = this,
            format = Ext.String.format;
        return Ext.create('Ext.picker.Month', {
            pickerField: me,
            ownerCt: me.ownerCt,
            renderTo: document.body,
            floating: true,
            hidden: true,
            focusOnShow: true,
            minDate: me.minValue,
            maxDate: me.maxValue,
            disabledDatesRE: me.disabledDatesRE,
            disabledDatesText: me.disabledDatesText,
            disabledDays: me.disabledDays,
            disabledDaysText: me.disabledDaysText,
            format: me.format,
            showToday: me.showToday,
            startDay: me.startDay,
            minText: format(me.minText, me.formatDate(me.minValue)),
            maxText: format(me.maxText, me.formatDate(me.maxValue)),
            focusable: false,
            listeners: {
                select: {scope: me, fn: me.onSelect},
                monthdblclick: {scope: me, fn: me.onOKClick},
                yeardblclick: {scope: me, fn: me.onOKClick},
                OkClick: {scope: me, fn: me.onOKClick},
                CancelClick: {scope: me, fn: me.onCancelClick}
            },
            keyNavConfig: {
                esc: function () {
                    me.collapse();
                }
            }
        });
    },

    onCancelClick: function () {
        var me = this;
        me.selectMonth = null;
        me.collapse();
    },

    onOKClick: function () {
        var me = this;
        if (me.selectMonth) {
            me.setValue(me.selectMonth);
            me.fireEvent('select', me, me.selectMonth);
        }
        me.collapse();
    },

    onSelect: function (m, d) {
        var me = this;
        me.selectMonth = Scalr.utils.Quarters.getDate(d[1]+'-0'+(d[0]+1)+'-01');
    }

});

Ext.define('Scalr.ui.CostAnalyticsListView', {
    extend: 'Ext.view.View',
    alias: 'widget.costanalyticslistview',

    deferInitialRefresh: false,
    deferEmptyText: false,
    cls: 'x-dataview',
    itemCls: 'x-dataview-tab',
    selectedItemCls : 'x-dataview-tab-selected',
    overItemCls : 'x-dataview-tab-over',
    itemSelector: '.x-dataview-tab',
    overflowX: 'hidden',
    overflowY: 'auto',
    loadingText: '',
    roundCosts: true,

    initComponent: function() {
        var me = this;

        if (!me.tpl) {
            if (me.subject === 'costcenters' || me.subject === 'projects') {
                me.tpl = new Ext.XTemplate(
                    '<tpl for=".">',
                        '<div class="x-dataview-tab{[values.archived?\' x-dataview-tab-archived\':\'\']}">',
                            '<table style="width:100%">',
                                '<tr>',
                                    (me.subject === 'projects' ?
                                        '<td>'+
                                            '<div class="x-fieldset-subheader" style="margin:0 0 4px -6px;width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" data-qtip="{[this.getItemTooltip(values)]}">'+
                                                '<tpl if="Ext.isNumeric(shared)">'+
                                                    '<img style="vertical-align:middle" src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-icon-share-mode x-costanalytics-icon-share-mode-{shared}" data-qtip="{[this.getShareModeTitle(values)]}"/> '+
                                                '</tpl>'+
                                                '{name} ' +
                                            '</div>'+
                                            '<div style="font-size:90%;color:#8daac5;line-height:14px;margin:-4px 0 0 22px">{ccName}</div>'+
                                        '</td>'+
                                        '<td style="width:24px;text-align:right">'+
                                            '<div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:70px;white-space:nowrap">' + (me.hideBillingCode ? '' : '<span class="x-form-item-label-default" title="{billingCode:htmlEncode}">{billingCode}</span>')+'</div>'+
                                        '</td>'
                                    :
                                        '<td>'+
                                            '<div class="x-fieldset-subheader" style="margin-bottom:4px;width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" data-qtip="{[this.getItemTooltip(values)]}">{name} </div>'+
                                        '</td>'+
                                        '<td style="padding:0 12px 0 0;text-align:right">'+
                                            '<div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:70px;white-space:nowrap">' + (me.hideBillingCode ? '' : '<span class="x-form-item-label-default" title="{billingCode:htmlEncode}">{billingCode}</span>')+'</div>'+
                                        '</td>'
                                    ),
                                '</tr>',
                                '<tr>',
                                    '<td colspan="2" style="text-align:center;padding-bottom:0;padding-top:6px">',
                                        '<span class="x-form-item-label-default">Spent this month</span>',
                                    '</td>',
                                '</tr>',
                                '<tr>',
                                    '<td colspan="2" style="text-align:center;padding-bottom:8px">',
                                        '<span class="x-bold" style="font-size:160%">{[this.currency'+(me.roundCosts?'':'2')+'(values.periodTotal)]}</span> &nbsp;',
                                        '<tpl if="growth!=0">' +
                                            '{[this.pctLabel(values.growth, values.growthPct, false, false, false, '+(me.roundCosts?'true':'false')+')]}' +
                                        '</tpl>'+
                                    '</td>',
                                '</tr>',
                                '<tpl if="archived">',
                                    '<tr><td colspan="2" style="text-align:center;"><div class="x-fieldset-subheader" style="color:#777;margin-bottom:0">Archived</div></td></tr>',
                                '<tpl else>',
                                    '<tpl if="budgetSpentPct!==null">',
                                        '<tr>',
                                            '<td colspan="2" style="text-align:center">',
                                                '<span class="x-form-item-label-default">Budget consumption</span> <span class="x-semibold"style="font-size:110%">{budgetSpentPct}%</span>',
                                            '</td>',
                                        '</tr>',
                                        '<tr>',
                                            '<td colspan="2">',
                                                '<div class="x-form-progress-field" style="margin-top:-3px;height:10px">',
                                                    '<div class="x-form-progress-bar x-costanalytics-bg-{[this.getColorCls(values)]}" style="width:{budgetSpentPct}%;"></div>',
                                                '</div>',
                                            '</td>',
                                        '</tr>',
                                    '</tpl>',
                                '</tpl>',
                            '</table>',
                        '</div>',
                    '</tpl>',
                    {
                        getShareModeTitle: function(values) {
                            var title;
                            if (values.shared == 1) {
                                title = '<b>Global project</b>';
                            } else if (values.shared == 2) {
                                title = '<b>Account project</b><br/>'+values.accountName+' (id:'+values.accountId+')';
                            } else if (values.shared == 3) {
                                title = '<b>Environment project</b><br/>Account - '+values.accountName+' (id:'+values.accountId+')<br/>Environment - '+values.envName+' (id:'+values.envId+')';
                            }
                            return Ext.String.htmlEncode(title);
                        },
                        getColorCls: function(values) {
                            var cls;
                            if (values.budget) {
                                if (values.budgetRemainPct < 5) {
                                    cls = 'red';
                                } else if (values.budgetRemainPct < 25) {
                                    cls = 'orange';
                                } else if (values.budgetSpentPct > 0) {
                                    cls = 'green';
                                }
                            }
                            return cls;
                        },
                        getItemTooltip: function(data){
                            var html = [];
                            html.push('<b>'+data.name+'</b>');
                            html.push(' (<i>' + (data.description || 'No description') + '</i>)<br/>')
                            if (data.archived) {
                                html.push('ARCHIVED<br/>');
                            }
                            if (me.subject === 'costcenters') {
                                html.push('Tracked projects: ' + (data.projectsCount || 0) + '<br/>');
                                html.push('Tracked environments: ' + (data.envCount || 0));
                            } else if (me.subject === 'projects') {
                                html.push('Tracked farms: ' + (data.farmsCount || 0));
                            }
                            return Ext.String.htmlEncode(html.join(''));
                        }
                    }
                );
            }
        } else if (!me.tpl.isTemplate) {
            me.tpl = new Ext.XTemplate(me.tpl);
        }

        me.plugins = {
            ptype: 'dynemptytext',
            emptyText: 'Nothing was found.',
            showArrow: false
        };

        me.callParent();
    }

});

Ext.define('Scalr.ui.CostAnalyticsSpends', {
    extend: 'Ext.container.Container',
    alias: 'widget.costanalyticsspends',

    subject: null,
    type: null,
    roundCosts: true,

    subjects: {
        costcenters: {
            clouds: true,
            projects: true
        },
        projects: {
            clouds: true,
            farms: true
        },
        farms: {
            farmRoles: true
        },
        envs: {
            clouds: true,
            farms: true
        }
    },
    colorMap: null,

    permanentSorter: {
        //"Other farms" must be in the bottom
        id: 'presort',
        sorterFn: function(o1, o2){
            return o1.data.id === 'everything else' ? 1 : (o2.data.id === 'everything else' ? -1 : 0);
        }
    },

    layout: {
        type: 'vbox',
        align: 'stretch'
    },

    applyPermanentSorter: function(store, sorters){
        var sorters = store.getSorters();
        sorters.suspendEvents(false);
        sorters.insert(0, 'presort', this.permanentSorter);
        sorters.resumeEvents();
    },

    initComponent: function() {
        var me = this;
        me.enabledSeries = {};
        Ext.Object.each(me.subjects[me.subject], function(type){
            me.enabledSeries[type] = {};
        });

        me.chartViewTip = {
            trackMouse: true,
            hideDelay: 0,
            showDelay: 0,
            tpl: '{[this.itemCost(values, values.round)]}',
            renderer: me.chartViewTipRenderer ?
            function(record, item) {
                me.chartViewTipRenderer(this, record, item);
            }
            :
            function(record, item) {
                var info;
                info = record.get('extrainfo')[item.field] || {};
                this.update({
                    id: item.field,
                    type: me.type,
                    name: me.type === 'clouds' ? Scalr.utils.getPlatformName(item.field) : me.data[me.type][item.field]['name'],
                    label: record.get('label'),
                    cost: info['cost'],
                    costPct: info['costPct'],
                    interval: me.data['interval'],
                    round: me.roundCosts
                });
            }
        };
        Ext.apply(me.chartViewTip, me.chartViewTipCfg);

        me.chartViewTip.tpl = new Ext.XTemplate(me.chartViewTip.tpl, {
            getColoredName: function(id, name){
                return me.getColoredItemTitle(me.type, id, name, true);
            }
        });


        this.items = [{
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            margin: '0 0 24 0',
            items: [{
                xtype: 'component',
                itemId: 'title',
                cls: 'x-caheader',
                tpl: '{title}'
            },{
                xtype: 'label',
                itemId: 'pieChartInfo',
                flex: 1,
                hidden: true,
                style: 'text-align: center',
                text: 'Click on the grid item to see spend details.'
            },{
                xtype: 'tbfill',
                flex: 0.01
            },{
                xtype: 'buttongroupfield',
                itemId: 'viewSelector',
                value: me.defaultView,
                defaults: {
                    width: 50
                },
                items: [{
                    cls: 'x-costanalytics-icon-line-chart',
                    value: 'line'
                },{
                    cls: 'x-costanalytics-icon-bars-chart',
                    value: 'stacked'
                },{
                    cls: 'x-costanalytics-icon-pie-chart',
                    value: 'pie'
                },{
                    cls: 'x-costanalytics-icon-grid',
                    value: 'grid'
                }],
                listeners: {
                    change: function(comp, value) {
                        if (value) {
                            this.up('costanalyticsspends').onViewSelect(value);
                        }
                    }
                }
            },{
                xtype: 'button',
                iconCls: 'x-btn-icon-download',
                tooltip: 'Download CSV',
                margin: '0 0 0 20',
                listeners: {
                    mouseout: function() {
                        this.setTooltip('Download CSV');
                    }
                },
                handler: function () {
                    this.setTooltip('');//avoid 2 tooltips
                    Scalr.message.InfoTip('Coming soon', this.el, {anchor: 'bottom'});
                    /*var params = Ext.clone(me.requestParams);
                    params['type'] = me.type;
                    Scalr.utils.UserLoadFile(Scalr.utils.getUrlPrefix() + '/analytics/xGetPeriodCsv?' + Ext.urlEncode(params));*/
                }
            }]
        },{
            xtype: 'container',
            itemId: 'viewWrapper',
            layout: 'card',
            flex: 1
        }];
        me.callParent(arguments);
    },

    setType: function(type) {
        var me = this,
            viewSelector,
            view;
        if (me.type !== type) {
            me.type = type;
            if (me.type) {
                viewSelector = me.down('#viewSelector');
                view = viewSelector.getValue() || 'line';
                viewSelector.setValue(null);
                viewSelector.setValue(view);
            }
        }
    },

    loadDataDeferred: function() {
        if (this.rendered) {
            this.loadData.apply(this, arguments);
        } else {
            if (this.loadDataBind !== undefined) {
                this.un('afterrender', this.loadDataBind, this);
            }
            this.loadDataBind = Ext.bind(this.loadData, this, arguments);
            this.on('afterrender', this.loadDataBind, this, {single: true});
        }
    },

    setColorMap: function() {
        var me = this,
            totals = me.data['totals'];
        me.colorMap = me.colorMap || {};
        Ext.Object.each(me.subjects[me.subject], function(type){
            if (totals[type]) {
                me.colorMap[type] = me.colorMap[type] || {};
                if (type === 'clouds') {
                    Ext.each(totals[type], function(item, index){
                        me.colorMap[type][item.id] = Scalr.utils.getColorById.apply(me, [item['id'], 'clouds']);
                    });
                } else {
                    var usedColors = [], startIndex = -1, ids = [];
                    Ext.each(totals[type], function(item, index){
                        if (me.colorMap[type][item.id]) {
                            usedColors.push(me.colorMap[type][item.id]);
                            startIndex = index > startIndex ? index : startIndex;
                        } else {
                            ids.unshift(item.id);
                        }
                    });
                    Ext.each(ids, function(id){
                        startIndex++;
                        me.colorMap[type][id] = Scalr.utils.getColorById.apply(me, [id === 'everything else' ? 29 : startIndex, 'farms']);
                    });
                }
            }
        });
    },

    loadData: function(mode, quarter, startDate, endDate, data) {
        var viewSelector = this.down('#viewSelector'),
            view = viewSelector.getValue() || 'line',
            today = Scalr.utils.Quarters.getDate(),
            realEndDate = endDate > today ? today : endDate,
            title;

        switch (mode) {
            case 'week':
            case 'custom':
                if (startDate < realEndDate) {
                    if (startDate.getFullYear() !== endDate.getFullYear()) {
                        title = Ext.Date.format(startDate, 'M j, Y') + '&nbsp;&ndash;&nbsp;' + Ext.Date.format(endDate, 'M j, Y');
                    } else {
                        title = Ext.Date.format(startDate, 'M j') + '&nbsp;&ndash;&nbsp;' + Ext.Date.format(endDate, 'M j');
                    }
                } else {
                    title = Ext.Date.format(startDate, 'F j');
                }
            break;
            case 'month':
                title = Ext.Date.format(startDate, 'F Y');
            break;
            case 'year':
                title = Ext.Date.format(startDate, 'Y');
            break;
            case 'quarter':
                title = quarter['title'];
            break;
            case 'day':
                title = Ext.Date.format(startDate, 'M j');
            break;
        }

        this.mode = mode;
        this.interval = data['interval'];
        this.startDate = startDate;
        this.endDate = endDate;
        this.realEndDate = endDate > today ? today : endDate;
        this.data = data;
        this.setColorMap();

        this.down('#title').update({title: title});
        var eventsGrid = this.down('#eventsGrid');
        if (eventsGrid) eventsGrid.hide();

        if (this.type) {
            viewSelector.setValue(null);
            viewSelector.setValue(view);
        }
    },

    getColoredItemTitle: function(type, id, name, icon) {
        var html = [];
        html.push('<span style="color:#' + this.getItemColor(id, type)+ '">');
        if (type === 'clouds') {
            html.push((icon ? '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-'+id+'"/>&nbsp;&nbsp;' : '') + Scalr.utils.getPlatformName(id));
        } else {
            html.push(name);
        }
        html.push('</span>');
        return  html.join('');
    },

    getItemColor: function(id, type) {
        var colorMap = this.colorMap[type];
        if (colorMap && colorMap[id] !== undefined) {
            return colorMap[id];
        } else {
            return '000000';
        }
    },

    getItemTitle: function(type, id, name, icon) {
        return (type === 'clouds' ? (icon ? '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-'+id+'"/>&nbsp;&nbsp;' : '') + Scalr.utils.getPlatformName(id) : name);
    },

    getPeriodTitle: function(capitalize) {
        var title = this.mode === 'custom' ? 'period' : this.mode;
        return capitalize ? Ext.String.capitalize(title) : title;
    },

    prepareDataForChartStore: function() {
        var me = this,
            res = [];
        Ext.Array.each(me.data['timeline'], function(item, index){
            //index, datetime, onchart, label, extrainfo, events, series1data, series2data....
            var row = [index, item.datetime, item.onchart || index, item.label, {}, item.events ? 1.1: -1, item.events, item.cost];
            Ext.Object.each(me.data[me.type], function(key, value){
                row[4][key] = value['data'][index];
                row.push(value['data'][index] ? value['data'][index]['cost'] : undefined);
            });
            res.push(row);
        });
        return res;
    },

    getEnabledSeries: function() {
        var me = this,
            res = [],
            series = Ext.Object.getKeys(me.data[me.type]);
        Ext.Array.each(series, function(s){
            var enabled = me.enabledSeries[me.type][s];
            if (enabled === undefined && me.isSeriesEnableByDefault(s) || enabled === true) {
                res.push(s);
            }
        });
        return res;
    },

    isSeriesEnableByDefault: function(id) {
        var res = false;
        //if (id !== 'everything else') {
            Ext.each(this.data['totals']['top'][this.type], function(data){
                if (data['id'] == id) {
                    res = data['cost'] > 0;
                    return false;
                }
            });
        //}
        return res;
    },

    setEnabledSeries: function(type, series) {
        var me = this;
        Ext.Object.each(me.data[me.type], function(key){
            me.enabledSeries[type][key] = false;
        });
        Ext.each(series, function(key){
            me.enabledSeries[type][key] = true;
        });
    },

    onViewSelect: function(view) {
        var me = this,
            viewName = (view === 'line' || view === 'stacked') ? 'chart' : view;
        me.down('#pieChartInfo').setVisible(view === 'pie' && !me.hidePieChartInfo);
        me['select' + Ext.String.capitalize(viewName) + 'View'](view);
    },

    selectChartView: function(view) {
        var me = this,
            enabledSeries = me.getEnabledSeries(),
            viewWrapper = me.down('#viewWrapper'),
            chartWrapper = viewWrapper.getComponent('chartWrapper'),
            chart,
            data = me.data[me.type],
            seriesList = Ext.Object.getKeys(data),
            series = [];
        viewWrapper.suspendLayouts();
        if (!chartWrapper) {
            chartWrapper = viewWrapper.add({
                xtype: 'container',
                itemId: 'chartWrapper',
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                }
            });
        } else {
            chartWrapper.down('#totals').show();
            var details = chartWrapper.down('#details');
            if (details) details.hide();
        }
        if (view === 'line') {
            Ext.Array.each(seriesList, function(value){
                var color = '#'+me.getItemColor(value, me.type);
                series.push({
                    type: 'line',
                    //selectionTolerance: 8,
                    //skipWithinBoxCheck: true,
                    //shadowAttributes: [],
                    //axis: 'left',
                    xField: 'xLabel',
                    yField: value,
                    hidden: !Ext.Array.contains(enabledSeries, value),
                    //showMarkers: false,
                    style: {
                        stroke: color/*,
                        opacity: 0.7,
                        'stroke-width': 1*/
                    },
                    /*highlight: {
                        //radius: 5,
                        fill: color,
                        'stroke-width': 0
                    },*/
                    //highlightLine: false,
                    //smooth: true,
                    marker: {
                        type: 'circle',
                        radius: 3,
                        fill: color,
                        'stroke-width': 0,
                        cursor: 'pointer'
                    },
                    listeners: {
                        itemclick: function(series, item, event) {
                            me.onShowChartViewDetails(
                                item.record.get('index'),
                                item.field,
                                data[item.field]['name'],
                                item.record.get('label'),
                                item.record.get('datetime'),
                                item.record.get('extrainfo')[item.field]
                            );
                        }
                    },
                    tips: me.chartViewTip
                });
            });
        } else if (view === 'stacked') {
            series.push({
                type: 'bar',
                xField: 'xLabel',
                yField: seriesList,
                hidden: Ext.Array.difference(seriesList, enabledSeries),
                stacked: true,
                //xPadding: 0,
                listeners: {
                    itemclick: function(series, item, event) {
                        me.onShowChartViewDetails(
                            item.record.get('index'),
                            item.field,
                            data[item.field]['name'],
                            item.record.get('label'),
                            item.record.get('datetime'),
                            item.record.get('extrainfo')[item.field]
                        );
                    }
                },
                style: {
                    cursor: 'pointer'
                },
                renderer: function(sprite, config, data, index){
                    var color = '#' + me.getItemColor(sprite.getField(), me.type);
                    return {
                        fillStyle: color,
                        strokeStyle: color
                    };
                },
                tips: me.chartViewTip
            });

            if (me.chartVeiwTotalSeries) {
                series.push({
                    type: 'line',
                    selectionTolerance: 4,
                    xField: 'xLabel',
                    axis: 'bottom',
                    yField: 'total',
                    shadowAttributes: [],
                    skipWithinBoxCheck: true,
                    //yOffset: -4,
                    style: {
                        stroke: '#00468c',
                        //opacity: 0.7,
                        'stroke-width': 1
                    },
                    highlight: {
                        radius: 3,
                        fill: '#00468c',
                        'stroke-width': 0
                    },
                    highlightLine: false,
                    //smooth: true,
                    marker: {
                        type: 'circle',
                        radius: 2.5,
                        fill: '#00468c',
                        'stroke-width': 0,
                        cursor: 'pointer'
                    },
                    tips: me.chartViewTip,
                    listeners: {
                        itemclick: function(series, item, event) {
                            me.onShowChartViewDetails(item.record.get('index'), item.field, '', item.record.get('label'), item.record.get('datetime'));
                        }
                    },

                });
            }

        }
        series.push({
            type: 'scatter',
            xField: 'xLabel',
            yField: 'xEvents',
            ignoreYOffset: true,
            renderer: function(sprite, config, data, index){
                var hasEvents = data.store.getData().items[index].get('events');
                return {
                    hidden: !hasEvents
                };
            },
            marker: {
                type: 'image',
                src: '/ui2/images/ui/analytics/event.png',
                rotationRads: Math.PI,
                width: 14,
                height: 12
            },
            listeners: {
                itemclick: function(series, item, event) {
                    me.onShowEvents(item.record.get('label'), item.record.get('datetime'));
                }
            },
            tooltip: {
                trackMouse: true,
                hideDelay: 0,
                showDelay: 0,
                tpl: '<b>{label}</b><br/>{eventsCount} event(s)',
                renderer: function(record) {
                    this.eventsCount = record.get('events');
                    this.update({
                        label: record.get('label'),
                        eventsCount: record.get('events')
                    });
                },
                listeners: {
                    beforeshow: function() {
                        return !!this.eventsCount;
                    }
                }
            }
        });

        chartWrapper.remove(chartWrapper.getComponent('chart'));
        chart = chartWrapper.insert(0, {
            xtype: 'cartesian',
            itemId: 'chart',
            height: 200,
            theme: 'scalr',
            allowSeriesOverflowY: true,
            insetPadding: {top: 20},
            allSeries: seriesList,
            plugins: {
                ptype: 'chartitemevents'
            },
            store: Ext.create('Ext.data.ArrayStore', {
                fields: Ext.Array.merge(['index', {
                   name: 'datetime',
                   type: 'date',
                   convert: function(v, record) {
                       return Scalr.utils.Quarters.getDate(v, true);
                   }
                }, 'xLabel', 'label', 'extrainfo', 'xEvents', {name: 'events', type: 'int'}, 'total'], seriesList),
                data: me.prepareDataForChartStore()
            }),
            toggleSeries: function(value) {
                if (view === 'stacked') {
                    var series = this.getSeries()[0];
                    Ext.each(this.allSeries, function(item, index){
                        series.setHiddenByIndex(index, !Ext.Array.contains(value, item));
                    });
                } else {
                    Ext.each(this.getSeries(), function(series) {
                        if (series.type !== 'scatter') {
                            series.setHidden(!Ext.Array.contains(value, series.getYField()));
                        }
                    });
                }
                this.redraw();
            },
            axes: [{
                type: 'numeric',
                position: 'left',
                fields: Ext.Array.merge(seriesList, me.chartVeiwTotalSeries ? ['total'] : []),
                renderer: function(value, layout){
                    return value > 0 ? Ext.util.Format.currency(value, null, layout.majorTicks.to > 3 ? 0 : 2) : '';
                },
                grid: {
                    fillStyle: '#d7e6f2',
                    strokeStyle: '#b9d2ec'
                },
                majorTickSteps: 4
            },{
                type: 'numeric',
                position: 'right',
                fields: 'xEvents',
                minimum: 0,
                maximum: 1.1,
                hidden: true
            },{
                type: 'category',
                position: 'bottom',
                fields: ['xLabel'],
                renderer: function(label) {
                    return Ext.isString(label) ? label : '';
                }
            }],
            series: series
        });
        this.showChartViewTotals();
        viewWrapper.layout.setActiveItem(chartWrapper);
        viewWrapper.resumeLayouts(true);
    },

    onShowEvents: function(label, datetime) {
        var me = this,
            params = {
                date: Ext.Date.format(datetime, 'Y-m-d H:i'),
                mode: me.mode,
                start: Ext.Date.format(me.startDate, 'Y-m-d'),
                end: Ext.Date.format(me.endDate, 'Y-m-d')
            };
        if (Scalr.scope === 'account') {
            params['envId'] = me.data['envId'];
        } else if (Scalr.scope === 'scalr') {
            params['ccId'] = me.data['ccId'];
        }
        params['projectId'] = me.data['projectId'];

        Scalr.Request({
            processBox: {
                type: 'action',
                msg: 'Loading events...'
            },
            url: Scalr.utils.getUrlPrefix() + '/analytics/xGetTimelineEvents',
            params: params,
            success: function (res) {
                me.showEvents(label, datetime, res['data']);
            }
        });
    },

    showEvents: function(label, datetime, events) {
        var me = this,
            chartWrapper = me.down('#chartWrapper'),
            eventsGrid = chartWrapper.down('#eventsGrid');

        if (!eventsGrid) {
            eventsGrid = chartWrapper.insert(1, {
                xtype: 'grid',
                itemId: 'eventsGrid',
                trackMouseOver: false,
                disableSelection: true,
                margin: '12 0 18',
                store: {
                    fields: [{
                        name: 'dtime',
                        type: 'date',
                        convert: function(v, record) {
                            return Scalr.utils.Quarters.getDate(v, true);
                        }
                    }, 'description', 'type'],
                    sorters: {property: 'dtime'},
                    proxy: 'object'
                },
                maxHeight: 224,
                viewConfig: {
                    emptyText: 'No events found',
                    deferEmptyText: false
                },
                columns: [{
                    xtype: 'templatecolumn',
                    header: 'Events',
                    sortable: false,
                    resizable: false,
                    flex: 1,
                    tpl: new Ext.XTemplate(
                        '{[this.getEventIcon(values)]}&nbsp;&nbsp;<span style="margin-right:40px">{dtime:date(\'M j, Y h:i a\')}</span><span data-qtip="{description:htmlEncode}">{description}</span>',
                        {
                            getEventIcon: function(values) {
                                var title = '', type = '';
                                switch (values['type']) {
                                    case 1:
                                    case 2:
                                        title = 'Project assignement change';
                                        type = 'project';
                                    break;
                                    case 3:
                                    case 4:
                                        title = 'Cost center assignement change';
                                        type = 'cc';
                                    break;
                                    case 5:
                                        title = 'Pricing change';
                                        type = 'pricing';
                                    break;
                                }
                                return '<img src="' + Ext.BLANK_IMAGE_URL + '" title="'+title+'" class="x-costanalytics-icon-event x-costanalytics-icon-event-'+type+'" style="vertical-align:middle" />';
                            }
                        }
                    )
                }],
                dockedItems: [{
                    xtype: 'toolbar',
                    ui: 'inline',
                    dock: 'top',
                    overlay: true,
                    layout: {
                        type: 'hbox',
                        pack: 'end'
                    },
                    margin: 0,
                    padding: '4 8 6 0',
                    style: 'z-index:2',
                    items: {
                        ui: 'other',
                        iconCls: 'x-tool-img x-tool-close',
                        tooltip: 'Hide events',
                        handler: function() {
                            this.up('grid').hide();
                        }
                    }
                }]
            });
        }
        eventsGrid.columns[0].setText('Events (' + label + ')');
        eventsGrid.store.load({data: events});
        eventsGrid.show();
    },

    selectPieView: function() {
        var me = this,
            viewWrapper = me.down('#viewWrapper'),
            pieWrapper = viewWrapper.getComponent('pieWrapper'),
            pie,
            data = me.data['totals'][me.type],
            hideChart = me.data['totals']['cost'] == 0,
            nameColumnTitle = me.type === 'clouds' ? 'Cloud' : (me.type === 'projects' ? 'Project' : 'Farm'),
            grid,
            chart;
        chart = {
            xtype: 'polar',
            insetPadding: 0,
            width: 160,
            height: 160,
            theme: 'scalr',
            hidden: hideChart,
            series: [{
                type: 'pie',
                field: 'cost',
                donut: 24,
                renderer: function(sprite, config, data, index){
                    var record = data.store.getData().items[index];
                    return {
                        fillStyle: '#' + me.getItemColor(record.get('id'), me.type)
                    };
                },
                tooltip: {
                    trackMouse: true,
                    hideDelay: 0,
                    showDelay: 0,
                    tpl: '{[this.itemCost(values, values.round)]}',
                    renderer: function(record, item) {
                        this.update({
                            id: record.get('id'),
                            type: me.type,
                            name: me.type === 'clouds' ? Scalr.utils.getPlatformName(record.get('name')) : record.get('name'),
                            cost: record.get('cost'),
                            costPct: record.get('costPct'),
                            round: me.roundCosts
                        });
                    }
                }
            }]
        };
        viewWrapper.suspendLayouts();
        if (!pieWrapper) {
            pieWrapper = viewWrapper.add({
                xtype: 'container',
                itemId: 'pieWrapper',
                margin: '0 10',
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                }
            });
        }
        pie = pieWrapper.getComponent('pie');
        if (!pie) {
            var store = {
                proxy: 'object',
                fields: [{name: 'id', type: 'string'}, 'name', 'cost', 'costPct', 'prevCost', 'prevCostPct', 'growth', 'growthPct', 'curPrevPctGrowth', 'clouds', 'projects', 'farms', 'instances', 'cloudLocation', 'platform'],
                sorters: [
                    me.permanentSorter
                ,{
                    property: 'cost',
                    direction: 'DESC'
                }],
                listeners: {
                    beforesort: Ext.bind(me.applyPermanentSorter, me)
                },
                data: data
            };
            chart.store = store;
            pie = pieWrapper.add({
                xtype: 'container',
                itemId: 'pie',
                layout: {
                    type: 'hbox',
                    align: 'middle'
                },
                items: [chart,{
                    xtype: 'grid',
                    store: store,
                    flex: 1,
                    maxHeight: 254,
                    margin: '0 0 0 16',
                    features: [{
                        ftype: 'summary',
                        id: 'summary',
                        dock: 'bottom'
                    }],
                    viewConfig: {
                        emptyText: 'No data for selected period',
                        deferEmptyText: false
                    },
                    listeners: {
                        selectionchange: function(grid, selected) {
                            if (selected.length > 0) {
                                var scrollTop = grid.view.el.getScroll().top;//preserveScrollOnRefresh doesn't work for unknown reason
                                me.showPieViewDetails(selected[0].getData());
                                grid.view.el.scrollTo('top', scrollTop);
                            } else {
                                pieWrapper.down('#details').hide();
                            }
                        }
                    },
                    columns: [{
                        header: nameColumnTitle,
                        dataIndex: 'name',
                        sortable: false,
                        width: 220,
                        xtype: 'templatecolumn',
                        tpl: new Ext.XTemplate(
                            '{[this.getColoredName(values.id, values.name)]}',
                        {
                            getColoredName: function(id, name){
                                return me.getColoredItemTitle(me.type, id, name, true);
                            }
                        }),
                        summaryRenderer: function(){
                            return (me.subject === 'projects' ? 'Project' : 'Cost center') + ' total:';
                        }
                    },{
                        header: Ext.String.capitalize(me.getPeriodTitle()) + ' total',
                        dataIndex: 'cost',
                        xtype: 'templatecolumn',
                        flex: 1,
                        maxWidth: 240,
                        tpl: '{[this.currency'+(me.roundCosts?'':'2')+'(values.cost)]} {[values.costPct>0 ? \'(\'+values.costPct+\'%)\' : \'\']}',
                        summaryRenderer: function(){
                            return Ext.util.Format.currency(me.data['totals']['cost'], null, me.roundCosts?0:2);
                        }
                    },{
                        header: 'Previous ' + me.getPeriodTitle(),
                        dataIndex: 'prevCost',
                        xtype: 'templatecolumn',
                        flex: 1,
                        tpl: new Ext.XTemplate('<tpl if="prevCost">{[this.currency'+(me.roundCosts?'':'2')+'(values.prevCost)]} ({prevCostPct}%)</tpl>', {
                            getPoints: function(values){
                                return values.curPrevPctGrowth != 0 ? '<span style="color:#'+(values.curPrevPctGrowth>0 ?'d81911':'46a557')+'">('+(values.curPrevPctGrowth>0?'+':'&ndash;')+Math.abs(values.curPrevPctGrowth)+')</span>' : '';
                            }
                        }),
                        summaryRenderer: function(){
                            return me.data['totals']['cost'] > 0 && me.data['totals']['prevCost'] > 0 ? Ext.util.Format.currency(me.data['totals']['prevCost'], null, me.roundCosts?0:2) : '';
                        }
                    }]
                }]
            });
            grid = pie.down('grid');
        } else {
            var selected = [];
            grid = pie.down('grid');
            grid.getSelectionModel().selected.each(function(rec){
                selected.push(rec.get('id'));
            });
            grid.getSelectionModel().deselectAll();

            pie.suspendLayouts();
            grid.store.loadData(data);
            var oldChart = pie.down('polar');
            pie.remove(oldChart);
            //oldChart.destroy();
            chart.store = grid.store;
            chart = pie.insert(0, chart);

            grid.columns[0].setText(nameColumnTitle);
            grid.columns[1].setText(Ext.String.capitalize(me.getPeriodTitle()) + ' total');
            grid.columns[2].setText('Previous ' + me.getPeriodTitle());

            if (selected.length > 0) {
                var selection = [];
                Ext.Array.each(selected, function(id){
                    var rec = grid.store.getById(id);
                    if (rec) selection.push(rec);
                });
                grid.getSelectionModel().select(selection);
            } else {
                var details = pieWrapper.down('#details');
                if (details) details.hide();
            }
            chart.setVisible(!hideChart);
            pie.resumeLayouts(true);
        }
        grid.view.findFeature('summary').toggleSummaryRow(data && data.length > 0);

        viewWrapper.layout.setActiveItem(pieWrapper);
        viewWrapper.resumeLayouts(true);
    },

    selectGridView: function() {
        var me = this,
            viewWrapper = me.down('#viewWrapper'),
            grid = viewWrapper.down('#dailyGrid'),
            gridCt,
            timeline = me.data['timeline'],
            spendsGridHash = timeline[0]['datetime'] + timeline[timeline.length-1]['datetime'] + timeline.length,
            reconfigureColumns = spendsGridHash !== me.spendsGridHash,
            nameColumnTitle = me.type === 'clouds' ? 'Cloud' : (me.type === 'projects' ? 'Project' : 'Farm'),
            data = {},
            fields = ['type', 'id', {name: 'name', type: 'string'}],
            columns;

        if (reconfigureColumns) {
            columns = [{
                header: nameColumnTitle,
                dataIndex: 'name',
                width: 200,
                locked: true,
                resizable: true,
                sortable: false,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate(
                    '{[this.getItemName(values.id, values.name)]}',
                {
                    getItemName: function(id, name){
                        return me.getItemTitle(me.type, id, name, true);
                    }
                }),
                summaryRenderer: function(value) {
                    return '<span class="x-semibold">Total spend</span>';
                }
            }];
        }
        var todayColumnIndex;
        Ext.Array.each(timeline, function(item, index){
            if(todayColumnIndex === undefined && Ext.Date.parse(item.datetime, 'Y-m-d H:i') > Scalr.utils.Quarters.getDate()) {
                todayColumnIndex = index;
            }
            fields.push('col'+index+'_1', 'col'+index+'_2');
            Ext.Object.each(me.data[me.type], function(key, value){
                data[key] = data[key] || [me.type, key, value['name']];
                if (value['data'][index]) {
                    data[key].push(value['data'][index]['cost'], value['data'][index]['growthPct']);
                } else {
                    data[key].push(0, 0);
                }
            });
            if (reconfigureColumns) {
                columns.push({
                    header: item.label,
                    dataIndex: 'col'+index+'_1',
                    xtype: 'templatecolumn',
                    sortable: false,
                    minWidth: 160,
                    summaryType: 'sum',
                    summaryRenderer: function(value) {
                        return value > 0 ? Ext.String.format('<span class="x-semibold">{0}</span>', Ext.util.Format.currency(value, null, me.roundCosts ? 0 : 2)) : '';
                    },
                    tpl: '<tpl if="col'+index+'_1">{[this.currency'+(me.roundCosts ? '' : '2')+'(values.col'+index+'_1)]} <tpl if="col'+index+'_2&&col'+index+'_2!=0"><span style="color:#999">({[values.col'+index+'_2>0?\'+\':\'\']}{col'+index+'_2:round(2)}%)</span></tpl></tpl>'
                });
            }
        });

        scrollToColumn = function(columnIndex){
            if (columnIndex) {
                if (this.viewReady) {
                    this.scrollBy(this.getGridColumns()[columnIndex-1].getPosition(true)[0], 0, false);
                } else {
                    this.on('viewready', function(){
                        this.scrollBy(this.getGridColumns()[columnIndex-1].getPosition(true)[0], 0, false);
                    }, this, {single: true});
                }
            }
        }
        if (!grid) {
            gridCt = viewWrapper.add({
                xtype: 'container',
                items: {
                    xtype: 'grid',
                    itemId: 'dailyGrid',
                    features: [{
                        ftype: 'summary',
                        id: 'summary'
                    }],
                    listeners: {
                        boxready: function() {
                            scrollToColumn.call(this.view.normalView, todayColumnIndex);
                        },
                        //extjs 5.0 bug fix, remove after switching to 5.1.1
                        beforereconfigure: function(view, store, columns, oldStore) {
                            this.view.normalView.findFeature('summary').summaryRecord = null;
                        }
                        /*reconfigure: function() {
                            scrollToColumn.call(this.view.normalView, this.todayColumnIndex);
                        }*/
                    },
                    /*viewConfig: {
                        preserveScrollOnRefresh: false
                    },*/
                    cls: 'x-grid-locked-shadow x-grid-no-highlighting',
                    store: Ext.create('Ext.data.ArrayStore', {
                        fields: fields,
                        data: Ext.Object.getValues(data),
                        sorters:[
                            me.permanentSorter
                        ,{
                            property: 'name',
                            transform: function(value){
                                return value.toLowerCase();
                            }
                        }],
                    }),
                    columns: columns
                }
            });
            grid = gridCt.down();
        } else {
            gridCt = grid.up();
            grid.reconfigure(Ext.create('Ext.data.ArrayStore', {
                fields: fields,
                data: Ext.Object.getValues(data),
                sorters:[
                    me.permanentSorter
                ,{
                    property: 'name',
                    transform: function(value){
                        return value.toLowerCase();
                    }
                }],
            }), columns);
            grid.columns[0].setText(nameColumnTitle);
        }
        me.spendsGridHash = spendsGridHash;

        viewWrapper.layout.setActiveItem(gridCt);
    },

    onShowInstanceTypeDetails: function(view, index, id, name, label, datetime, data) {
        var me = this,
            params = {
                date: Ext.Date.format(datetime, 'Y-m-d H:i'),
                mode: me.mode,
                start: Ext.Date.format(me.startDate, 'Y-m-d'),
                end: Ext.Date.format(me.endDate, 'Y-m-d')
            };
        if (Scalr.scope === 'environment') {
            if (me.type === 'farms') {
                params.farmId = id;
            } else {
                params.farmId = me.requestParams.farmId;
            }

            if (id !== 'total' && me.type === 'farmRoles') {
                params.farmRoleId = id;
            }
        } else if (Scalr.scope === 'account') {
            if (me.subject === 'projects') {
                params.projectId = me.requestParams.projectId;
                Ext.each(me.data.totals.farms, function(farm) {
                    if (farm.id == id) {
                        if (farm.environment) {
                            params.envId = farm.environment.id;
                        }
                        return false;
                    }
                });
            } else {
                params.envId = me.data['envId'];
            }
            params.farmId = id;
        }
        if (params.farmId === 'everything else') {
            params.farmId = Ext.Object.getKeys(me.data.farms);
            Ext.Array.remove(params.farmId, 'everything else');
            params.farmId = Ext.encode(params.farmId);
        }
        Scalr.Request({
            processBox: {
                type: 'action',
                msg: 'Computing...'
            },
            url: Scalr.utils.getUrlPrefix() + '/analytics/xGetUsageItems',
            params: params,
            success: function (res) {
                me.showInstanceTypeDetails(view, index, id, name, label, datetime, data, res.distributionTypes);
            }
        });

    },

    showInstanceTypeDetails: function(view, index, id, name, label, datetime, data, usageItems) {
        var me = this,
            wrapper = me.down('#' + view + 'Wrapper'),
            details = wrapper.down('#details'),
            titleData, title2Data,
            resources, distributionTypes = [], maxUsageItemCost = 0;

        if (id === 'total') {
            titleData = {label: label};
            title2Data = {
                //id: id,
                name: me.data['name']
            };
            resources = usageItems || me.data['timeline'][index]['distributionTypes'];

        } else {
            resources = usageItems || data['distributionTypes'];
            titleData = {label: label || me.down('#title').data.title};
            title2Data = {
                id: id,
                name: name
            };
            Ext.each(me.data['totals'][me.type], function(item){
                if (item.id == id) {
                    title2Data.location = item.cloudLocation;
                    title2Data.platform = item.platform;
                    return false;
                }
            });
        }

        Ext.each(resources, function(dType){
            var distributionType = {
                id: dType.id,
                name: Scalr.constants.distributionTypes[dType.id] || dType.id,
                cost: dType.cost,
                growth: 0,
                growthPct: dType.growthPct,
                usageItems: []
            };
            Ext.each(dType.usageTypes, function(uType){
                Ext.each(uType.usageItems, function(uItem){
                    var uItemClone = Ext.clone(uItem);
                    uItemClone.usageType = (uType['displayName'] || uType['name']) + ( uType['measure'] ?  ', ' + uType['measure'] : '');
                    distributionType.usageItems.push(uItemClone);
                    maxUsageItemCost = maxUsageItemCost > uItemClone['cost'] ? maxUsageItemCost : uItemClone['cost'];
                });
            });
            distributionTypes[dType.id] = distributionType;
        });

        wrapper.suspendLayouts();

        if (maxUsageItemCost != 0) {
            Ext.Object.each(distributionTypes, function(dTypeId, dType){
                Ext.each(dType.usageItems, function(usageItem) {
                    usageItem['pctOfMax'] = usageItem['cost']/maxUsageItemCost*100;
                });
            });
        }

        if (!details) {
            var distributionItems = [];
            Ext.Object.each(Scalr.constants.distributionTypes, function(dTypeId, dTypeName){
                distributionItems.push.apply(distributionItems, [{
                    xtype: 'component',
                    itemId: 'dType' + dTypeId,
                    data: distributionTypes[dTypeId] || [],
                    hidden: !distributionTypes[dTypeId],
                    tpl:
                        '<div class="x-cabox x-cabox-transparent" style="text-align:left;padding:24px 0 12px">' +
                            '<span class="x-cabox-title x-costanalytics-{name}" style="margin:0 18px 0 0">{name}</span>'+
                            '<span class="title1">{[this.currency2(values.cost, true)]}</span>' +
                            '<tpl if="growth!=0">' +
                                ' &nbsp;{[this.pctLabel(values.growth, values.growthPct, false, false, false, false)]}' +
                            '</tpl>' +
                        '</div>'
                },{
                    xtype: 'grid',
                    flex: 1,
                    margin: 0,
                    itemId: 'dTypeGrid' + dTypeId,
                    cls: 'x-grid-shadow x-grid-no-highlighting',
                    features: [{ftype:'grouping', groupHeaderTpl: '{name}'}],
                    hidden: !distributionTypes[dTypeId],
                    viewConfig: {
                        emptyText: 'Nothing found',
                        deferEmptyText: false
                    },
                    store: {
                        proxy: 'object',
                        groupField: 'usageType',
                        fields: [{name: 'id', type: 'string'}, 'name', 'cost', 'costPct', 'growth', 'growthPct', 'growthPrevPoint', 'growthPrevPointPct', 'min', 'max', 'avg', {name: 'hours', type: 'int'}, 'displayHours', 'pctOfMax', 'usageType'],
                        sorters: {
                            property: 'cost',
                            direction: 'DESC'
                        },
                        data: distributionTypes[dTypeId] ? distributionTypes[dTypeId].usageItems : []
                    },
                    columns: [{
                        header: 'Resource',
                        dataIndex: 'name',
                        flex: 1,
                        xtype: 'templatecolumn',
                        tpl: '<span class="x-costanalytics-compute">{name}</span>'
                    },{
                        header: 'Spend (% of total)',
                        dataIndex: 'cost',
                        xtype: 'templatecolumn',
                        flex: 1.4,
                        tpl: '<div class="bar-inner" style="width:{pctOfMax}%"><span>{[this.currency2(values.cost)]}</span></div>'
                    },{
                        dataIndex: 'costPct',
                        sortable: false,
                        resizable: false,
                        width: 70,
                        xtype: 'templatecolumn',
                        tpl: '{costPct}%'
                    },{
                        header: 'Count (Min/Avg/Max)',
                        dataIndex: 'count',
                        sortable: false,
                        width: 200,
                        xtype: 'templatecolumn',
                        tpl: '{min}/{avg}/{max}'
                    },{
                        header: 'Usage',
                        dataIndex: 'hours',
                        width: 180,
                        xtype: 'templatecolumn',
                        tpl: '<tpl if="displayHours">{displayHours}<tpl else>&mdash;</tpl>'
                    }]
                }]);
            });

            details = wrapper.add({
                xtype: 'container',
                itemId: 'details',
                flex: 1,
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                margin: '48 10 0 0',
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'component',
                        itemId: 'title',
                        cls: 'x-caheader',
                        tpl: '{label}',
                        data: titleData,
                        style: 'white-space:nowrap'
                    },{
                        xtype: 'component',
                        flex: 1,
                        style: 'text-align:center',
                        itemId: 'title2',
                        cls: 'x-caheader',
                        tpl: new Ext.XTemplate(
                            '<b><span style="color:#{[this.getItemColor(values.id)]}"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-{platform}"/> {name}</span></b><tpl if="location"> <span style="white-space:nowrap">({location})</span></tpl>',
                        {
                            getItemColor: function(id){
                                return me.getItemColor(id, me.type);
                            }
                        }),
                        data: title2Data
                    },{
                        xtype: 'button',
                        width: 120,
                        margin: '0 0 0 80',
                        text: 'Back to total',
                        hidden: view === 'pie',
                        handler: function(){
                            me.showChartViewTotals();
                        }
                    }]
                },{
                    xtype: 'container',
                    margin: '0 0 12 0',
                    items: distributionItems
                }]
            });
        } else {
            details.down('#title').update(titleData);
            details.down('#title2').update(title2Data);
            Ext.Object.each(Scalr.constants.distributionTypes, function(dTypeId, dTypeName){
                if (distributionTypes[dTypeId]) {
                    details.down('#dType' + dTypeId).setVisible(true).update(distributionTypes[dTypeId]);
                    details.down('#dTypeGrid' + dTypeId).setVisible(true).store.loadData(distributionTypes[dTypeId].usageItems);
                } else {
                    details.down('#dType' + dTypeId).setVisible(false);
                    details.down('#dTypeGrid' + dTypeId).setVisible(false);
                }
            });
        }
        details.show();
        var totals = wrapper.down('#totals');
        if (totals) totals.hide();
        wrapper.resumeLayouts(true);
    }

});

Ext.define('Scalr.ui.AnalyticsBoxes', {
    extend: 'Ext.container.Container',
    alias: 'widget.analyticsboxes',

    layout: {
        type: 'hbox',
        align: 'stretch'
    },
    loadData: function(mode, quarter, startDate, endDate, data) {
        var me = this;
        var today = Scalr.utils.Quarters.getDate(),
            realEndDate = endDate > today ? today : endDate,
            dateFormat = 'M j';
        me.data = data;
        me.dateFormatPrev = 'M j';

        switch (mode) {
            case 'week':
            case 'custom':
                if (startDate.getFullYear() !== endDate.getFullYear()) {
                    dateFormat = 'M j, Y';
                    me.dateFormatPrev = 'M j\'y';
                }
                me.title = startDate < realEndDate ? (Ext.Date.format(startDate, dateFormat) + '&nbsp;&ndash;&nbsp;' + Ext.Date.format(endDate, dateFormat)) : Ext.Date.format(startDate, dateFormat);
            break;
            case 'month':
                me.title = Ext.Date.format(startDate, 'F Y');
            break;
            case 'year':
                me.title = Ext.Date.format(startDate, 'Y');
            break;
            case 'quarter':
                me.title = quarter['title'];
            break;
            case 'day':
                me.title = Ext.Date.format(startDate, 'M j');
            break;
        }
    }
});

//admin
Ext.define('Scalr.ui.AnalyticsBoxesAdmin', {
    extend: 'Scalr.ui.AnalyticsBoxes',
    alias: 'widget.analyticsboxesadmin',

    loadData: function(mode, quarter, startDate, endDate, data) {
        var me = this,
            totals,
            prevStartDate = Ext.Date.parse(data['previousStartDate'], 'Y-m-d'),
            prevEndDate = Ext.Date.parse(data['previousEndDate'], 'Y-m-d');

        me.callParent(arguments);

        totals = me.data['totals'];
        //total
        this.down('#total').update({
            title: me.title,
            cost: totals['cost'],
            prevCost: totals['prevCost'],
            forecastCost: totals['forecastCost'],
            growth: totals['growth'],
            growthPct: totals['growthPct'],
            period: mode === 'custom' ? 'period' : mode,
            prevPeriod: (prevStartDate - prevEndDate === 0) ? Ext.Date.format(prevStartDate, me.dateFormatPrev) : (Ext.Date.format(prevStartDate, me.dateFormatPrev) + '&nbsp;&ndash;&nbsp;' + Ext.Date.format(prevEndDate, me.dateFormatPrev))
        });

        //trends
        this.down('#trends').update({
            rollingAverageMessage: totals['trends']['rollingAverageMessage'],
            rollingAverage: totals['trends']['rollingAverage'],
            periodHighDate: '<span style="white-space:nowrap">'+ totals['trends']['periodHighDate']+'</span>',
            periodHigh: totals['trends']['periodHigh'],
            periodLowDate: '<span style="white-space:nowrap">'+ totals['trends']['periodLowDate']+'</span>',
            periodLow: totals['trends']['periodLow'],
            interval: data['interval'],
            topspender: this.getTopSpender(),
            subject: this.subject
        });

        //budget
        var budgetCt = this.down('#budget'),
            noBudgetCt = this.down('#noBudget');
        if (totals['budget']['budget'] != 0) {
            budgetCt.show();
            noBudgetCt.hide();
            budgetCt.down('#title').update({
                year: totals['budget']['year'],
                quarter: totals['budget']['quarter'],
                total: totals['budget']['budget'],
                ccId: data['ccId'],
                projectId: data['projectId']
            });
            budgetCt.down('#budgetSpentPct').update({
                value: totals['budget']['budgetSpentPct'],
                currentPeriodSpend: mode === 'month' ? '(' + totals['budget']['budgetSpentThisPeriodPct'] + '% used in ' + Ext.Date.format(startDate, 'F Y') + ')' : ''
            });
            budgetCt.down('polar').store.loadData([[totals['budget']['budgetSpentPct']]]);
            budgetCt.down('#budgetRemain').update(totals['budget']);
            this.down('#budgetAlert').setVisible(!!totals['budget']['budgetAlert']).update({
                ccId: data['ccId'],
                projectId: data['projectId'],
                alert: totals['budget']['budgetAlert']
            });
        } else {
            noBudgetCt.down('#title').update({
                year: totals['budget']['year'],
                quarter: totals['budget']['quarter'],
                text: totals['budget']['closed'] ? 'Budget hasn\'t been set' : 'No budget allocated',
                ccId: data['ccId'],
                projectId: data['projectId']
            });
            noBudgetCt.down('#finalSpent').setVisible(!!totals['budget']['closed']).update({budgetFinalSpent: totals['budget']['budgetFinalSpent']});
            noBudgetCt.down('#button').setVisible(!totals['budget']['closed']);
            budgetCt.hide();
            noBudgetCt.show();
        }
    },
    getTopSpender: function(){
        var top = this.data['totals']['top'][this.subject === 'costcenters' ? 'projects' : 'farms'],
            topspender = null;
        if (top.length) {
            topspender = top[0];
            if ((!top[0].id || top[0].id === 'everything else') && top.length > 1) {
                topspender = top[1];
            }
            if (topspender.cost == 0) {
                topspender = null;
            }
        }
        return topspender;
    },
    defaults: {
        minHeight: 250
    },
    items: [{
        xtype: 'component',
        cls: 'x-cabox',
        itemId: 'total',
        flex: 1,
        minWidth: 160,
        maxWidth: 500,
        tpl: '<div class="x-cabox-title">{title}</div>'+
             '<div style="margin:16px 0 0"><span class="x-form-item-label-default">Spent</span>' +
                '<div>' +
                    '<span class="title1">{[this.currency(values.cost)]}</span>' +
                    '<tpl if="growth!=0">' +
                        ' &nbsp;{[this.pctLabel(values.growth, values.growthPct)]}' +
                    '</tpl>'+
                '</div>' +
             '</div>'+
             '<div style="margin:16px 0 0;padding:0 0 6px 0;min-height:51px">'+
                '<div><span class="x-form-item-label-default">Prev. {period}</span> ({prevPeriod})</div>' +//Same time previous {period}
                '<span class="title2">{[this.currency(values.prevCost)]}</span>&nbsp; ' +
             '</div>'+
             '<tpl if="forecastCost!==null">' +
                '<div style="margin:6px 0 0" class="x-form-item-label-default">{period:capitalize} end estimate</div>' +
                '<span class="title2" style="padding-right:1em">~ {[this.currency(values.forecastCost)]}</span>' +
             '</tpl>'
    },{
        xtype: 'component',
        cls: 'x-cabox',
        itemId: 'trends',
        flex: 1,
        minWidth: 160,
        maxWidth: 500,
        tpl: '<div class="x-cabox-title">Trends</div>' +
             '<div style="margin:16px 0 0"><span class="x-form-item-label-default">{rollingAverageMessage}</span><div style="margin: 4px 0 8px"><span class="title1">{[this.currency(values.rollingAverage)]}</span> per {interval}</div></div>'+
             '<div style="margin:20px 0 0"><span class="x-form-item-label-default">Top spender</span>' +
                '<div style="margin: 4px 0 8px">' +
                    '<tpl if="topspender">' +
                        '<b style="position:relative;top:4px;">'+
                            '<tpl if="subject==\'costcenters\'">' +
                                '<a href="#/admin/analytics/projects?projectId={[values.topspender.id]}">{[values.topspender.name]}</a>' +
                            '<tpl else>' +
                                '<a href="#farms" data-qtip="{[this.farmInfo(values.topspender, true)]}">{[values.topspender.name]}</a>' +
                            '</tpl>' +
                        '</b>' +
                        '<tpl if="topspender.growth!=0">' +
                            ' &nbsp;{[this.pctLabel(values.topspender.growth, values.topspender.growthPct)]}' +
                        '</tpl>'+
                    '<tpl else>' +
                        '&ndash;' +
                    '</tpl>' +
                '</div>' +
             '</div>'+
             '<table>' +
             '<tr><td style="width:50%;vertical-align:top">' +
                     '<div style="margin:0 0 6px 0;padding:6px 0 0;"><span class="x-form-item-label-default">Period high</span><br/>({periodHighDate})</div>' +
                     '<div class="title2">{[this.currency(values.periodHigh)]}</div>' +
             '</td><td style="vertical-align:top">' +
                     '<div style="margin:6px 0 6px 0;"><span class="x-form-item-label-default">Period low</span><br/>({periodLowDate})</div>' +
                     '<div class="title2">{[this.currency(values.periodLow)]}</div>' +
             '</td></tr>' +
             '</table>',
        listeners: {
            afterrender: function() {
                var me = this;
                me.getEl().on('click', function(e) {
                    var el = me.el.query('a');
                    if (el.length) {
                        for (var i=0, len=el.length; i<len; i++) {
                            if (e.within(el[i]) && el[i].getAttribute('href') == '#farms') {
                                me.up('analyticsboxesadmin').fireEvent('farmclick');
                                e.preventDefault();
                                break;
                            }
                        }
                    }
                });
            }
        }
    },{
        xtype: 'container',
        cls: 'x-cabox',
        itemId: 'budget',
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        flex: 1.2,
        items: [{
            xtype: 'component',
            itemId: 'title',
            tpl: '<div class="x-cabox-title"><a style="color:#335071;float:left" href="#/admin/analytics/budgets?ccId={ccId}<tpl if="projectId">&projectId={projectId}</tpl>">{[Ext.isNumeric(values.quarter)?\'Q\':\'\']}{quarter} {year} budget</a><span style="float:right;" class="title2">{[this.currency(values.total)]}</span></div>'
        },{
            xtype: 'container',
            flex: 1,
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'container',
                flex: 1,
                items: [{
                    xtype: 'polar',
                    theme: 'scalr',
                    height: 110,
                    store: Ext.create('Ext.data.ArrayStore', {
                        fields: ['value']
                    }),
                    axes: [{
                        type: 'numeric',
                        position: 'gauge',
                        minimum: 0,
                        maximum: 100,
                        hidden: true
                    }],
                    series: [{
                        type: 'gauge',
                        field: 'value',
                        donut: 70,
                        totalAngle: Math.PI,
                        needleLength: 100,
                        renderer: function(sprite, config, data, index){
                            var record = data.store.getData().items[index];
                            if (record) {
                                var value = record.get('value'),
                                    color;
                                if (value >= 95) {
                                    color = '#de1810';
                                } else if(value >= 75) {
                                    color = '#ffae39';
                                } else {
                                    color = '#319608';
                                }
                            } else {
                                color = '#f0f1f4';
                            }
                            return {
                                fillStyle: color
                            };
                        }
                    }]
                },{
                    xtype: 'component',
                    itemId: 'budgetSpentPct',
                    tpl: '<div><span class="title1" style="font-size:17px">{value}%</span> used</div><p>{currentPeriodSpend}</p>',
                    margin: '-36 0 0 0'
                }]
            },{
                xtype: 'component',
                flex: 1,
                itemId: 'budgetRemain',
                tpl: new Ext.XTemplate(
                     '<tpl if="closed">' +
                        '<div style="margin: 16px 0 0"><span class="x-form-item-label-default">Final spend</span><div class="title1 x-costanalytics-{[this.getColorCls(values)]}" style="margin: 4px 0 8px">{[this.currency(values.budgetFinalSpent)]}</div></div>' +
                        '<div style="padding:10px 0 0"><span class="x-form-item-label-default">Cost variance</span><div class="title2 x-costanalytics-{[values.costVariance>0?\'red\':\'green\']}" data-qtip="{costVariancePct}%">{[values.costVariance>0?\'+\':\'\']}{costVariance:currency}</div></div>'+
                        '<div style="padding:12px 0 0"><span class="x-form-item-label-default">Exceeded on</span><tpl if="estimateDate"><div class="title2 x-costanalytics-red" style="margin: 4px 0 8px">{estimateDate:date(\'M j Y\')}</div><tpl else><div class="title2">&ndash;</div></tpl></div>'+
                     '<tpl else>'+
                        '<div style="margin: 16px 0 0"><span class="x-form-item-label-default">Remaining</span><div class="title1 x-costanalytics-{[this.getColorCls(values)]}" style="margin: 4px 0 8px">{[this.currency(values.budgetRemain)]}</div></div>' +
                        '<div style="padding:10px 0 0"><span class="x-form-item-label-default">Overspend estimate</span><div class="title2<tpl if="estimateOverspend&gt;0"> x-costanalytics-red</tpl>" <tpl if="estimateOverspendPct&gt;0">data-qtip="{estimateOverspendPct}% of budget"</tpl> style="margin: 4px 0 0">~{[this.currency(values.estimateOverspend)]}</div></div>'+
                        '<div style="padding:12px 0 0"><span class="x-form-item-label-default">Exceed{[values.budgetRemain>0?\'\':\'ed\']} on</span><tpl if="estimateDate"><div class="title2 x-costanalytics-red" style="margin: 4px 0 8px">{estimateDate:date(\'M j Y\')}</div><tpl else><div class="title2">&ndash;</div></tpl></div>'+
                     '</tpl>',
                     {
                        getColorCls: function(values) {
                            var cls = 'green';
                            if (values.budget) {
                                if (values.budgetRemainPct < 5) {
                                    cls = 'red';
                                } else if (values.budgetRemainPct < 25) {
                                    cls = 'orange';
                                }
                            }
                            return cls;
                        }
                     }
                )
            }]
        },{
            xtype: 'component',
            itemId: 'budgetAlert',
            margin: '6 0 0',
            hidden: true,
            tpl: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-grid-icon x-grid-icon-warning"/>&nbsp;&nbsp;<a class="x-link-warning" href="#/admin/analytics/budgets?ccId={ccId}<tpl if="projectId">&projectId={projectId}</tpl>">{alert}</a>'
        }]
    },{
        xtype: 'container',
        cls: 'x-cabox',
        itemId: 'noBudget',
        hidden: true,
        layout: 'auto',
        flex: 1.2,
        items: [{
            xtype: 'component',
            itemId: 'title',
            tpl: '<div class="x-cabox-title"><a style="color:#335071;float:left" href="#/admin/analytics/budgets?ccId={ccId}<tpl if="projectId">&projectId={projectId}</tpl>">{[Ext.isNumeric(values.quarter)?\'Q\':\'\']}{quarter} {year} Budget</a><span style="float:right;font-weight:normal"><i>{text}</i></span></div>'
        },{
            xtype: 'component',
            itemId: 'finalSpent',
            margin: '56 0 0 0',
            tpl: '<span class="x-form-item-label-default">Final spend</span><div class="title1" style="margin: 4px 0 8px">{[this.currency(values.budgetFinalSpent)]}</div>'
        },{
            xtype: 'button',
            itemId: 'button',
            margin: '56 0 0 0',
            padding: '0 24',
            cls: 'x-btn-green',
            height: 52,
            text: 'Define a budget',
            handler: function(){
                var data = this.up('analyticsboxesadmin').data;
                Scalr.event.fireEvent('redirect', '#/admin/analytics/budgets', false, {ccId: data['ccId'], projectId: data['projectId']});
            }
        }]
    }]
});

Ext.define('Scalr.ui.CostAnalyticsChartSummary', {
    extend: 'Ext.chart.CartesianChart',
    alias: 'widget.costanalyticssummary',

    theme: 'scalr',
    plugins: {
        ptype: 'chartitemevents'
    },
    interactions: 'itemhighlight',
    fieldsConfig: [],
    insetPadding: '10 0',
    loadData: function(data) {
        /*this.series.each(function(series){
            series.highlight = true;
            series.unHighlightItem();
            series.cleanHighlights();
            series.highlight = false;
        });*/
        this.store.loadData(Ext.Array.map(data, function(item, index){
            return {
                cost: item.cost,
                label: item.label,
                xLabel: item.onchart || index,
                datetime: item.datetime
            };
        }));
        this.fireEvent('afterload');
    },
    axes: [{
        type: 'numeric',
        position: 'left',
        fields: ['cost'],
        renderer: function(value, layout){
            return value > 0 ? Ext.util.Format.currency(value, null, layout.majorTicks.to > 3 ? 0 : 2) : '';
        },
        grid: {
            fillStyle: '#d7e6f2',
            strokeStyle: '#b9d2ec'
        },
        majorTickSteps: 4
    },{
        type: 'category',
        position: 'bottom',
        fields: ['xLabel'],
        renderer: function(label) {
            return Ext.isString(label) ? label : '';
        }

    }],
    series: [{
        type: 'bar',
        axis: 'left',
        xField: 'xLabel',
        yField: ['cost'],
        style: {
            fillStyle: '#b4cede',
            strokeStyle: '#b4cede'
        },
        highlight: {
            fillStyle: '#2581b8',
            strokeStyle: '#2581b8'
        },
        listeners1: {
            itemclick: function(chart, item) {
                /*var series = item.series, items;
                series.highlight = true;
                series.unHighlightItem();
                series.cleanHighlights();
                series.highlightItem(item);
                series.highlight = false;
                this.chart.fireEvent('itemclick', item);*/
            }
        },
        //highlight: false,
        /*highlightCfg: {
            fill: '#2581b8',
            stroke: null
        },*/
        tips: {
            trackMouse: true,
            //anchor: 'top',
            hideDelay: 0,
            showDelay: 0,
            tpl: '<div style="text-align:center">Spent <b>{[this.currency(values.cost)]}</b> on <b>{label}</b></div>',
            renderer: function(record, item) {
                this.update(record.getData());
            }
        }
    }]
});

Ext.define('Scalr.ui.CostAnalyticsListPanel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.costanalyticslistpanel',

    cls: 'x-costanalytics',
    layout: {
        type: 'hbox',
        align: 'stretch'
    },
    refreshStoreOnReconfigure: false,

    applyParams: function(params) {
        var me = this,
            itemId = params.itemId;
        cb = function(){
            var dataview = this.down('costanalyticslistview');
            selectItemById = function() {
                if (itemId) {
                    dataview.getSelectionModel().deselectAll();
                    var record =  dataview.store.getById(itemId);
                    if (record) {
                        dataview.getNode(record, true, false).scrollIntoView(dataview.el);
                        dataview.select(record);
                    }
                } else {
                    var selection = dataview.getSelectionModel().getSelection(),
                        periodField = me.down('costanalyticsperiod');
                    if (selection.length) {
                        periodField.restorePreservedValue();
                    }
                }
            };
            if (me.refreshStoreOnReconfigure) {
                me.refreshStoreOnReconfigure = false;
                dataview.getSelectionModel().deselectAll();
                dataview.store.on('load', selectItemById, this, {single: true});
                dataview.store.reload();
            } else {
                selectItemById();
            }
        };
        if (me.isVisible()) {
            cb.apply(me);
        } else {
            me.on('activate', cb, me, {single: true});
        }

    },

    loadPeriodData: function(params, mode, startDate, endDate, quarter) {
        var me = this;
        me.requestParams = Ext.apply({}, params);
        Ext.apply(me.requestParams, {
            mode: mode,
            startDate: Ext.Date.format(startDate, 'Y-m-d'),
            endDate: Ext.Date.format(endDate, 'Y-m-d')
        });

        Scalr.Request({
            processBox: {
                type: 'action',
                msg: 'Computing...'
            },
            url: '/admin/analytics/'+ me.subject + '/xGetPeriodData',
            params: me.requestParams,
            success: function (data) {
                var summaryTab = me.down('#summary'),
                    spends = me.down('costanalyticsspendsadmin');
                if (data) {
                    Ext.apply(data, params);
                    //calculate top spenders
                    data['totals']['top'] = {};
                    Ext.Array.each(['clouds', me.subject==='projects' ? 'farms' : 'projects'], function(type){
                        var top6 = new Ext.util.MixedCollection();
                        top6.addAll(data['totals'][type]);
                        top6.sort('cost', 'DESC');
                        data['totals']['top'][type] = top6.getRange(0,5);
                    });

                    if (data['totals']['cost'] == 0 && !data['totals']['budget']['closed'] && data['totals']['budget']['budget'] == 0) {
                        me.down('#tabs').setValue('summary');
                    }
                }
                summaryTab.loadDataDeferred(mode, quarter, startDate, endDate, data);
                spends.loadDataDeferred(mode, quarter, startDate, endDate, data);
                spends.requestParams = me.requestParams;
            }
        });
    },

    initComponent: function() {
        var me = this,
            store = me.store,
            sortItems,
            sortGroupName = me.subject + '-sort';
        delete me.store;

        sortItems = [{
            text: 'Order by name',
            group: sortGroupName,
            checked: true,
            sortHandler: function(dir){
                store.sort({
                    property: 'name',
                    direction: dir,
                    transform: function(value){
                        return value.toLowerCase();
                    }
                });
            }
        },{
            text: 'Order by spend',
            checked: false,
            group: sortGroupName,
            defaultDir: 'desc',
            sortHandler: function(dir){
                store.sort({
                    property: 'periodTotal',
                    direction: dir
                });
            }
        },{
            text: 'Order by growth',
            checked: false,
            group: sortGroupName,
            defaultDir: 'desc',
            sortHandler: function(dir){
                store.sort({
                    property: 'growth',
                    direction: dir
                });
            }
        },{
            text: 'Order by budget consumed',
            checked: false,
            group: sortGroupName,
            defaultDir: 'desc',
            sortHandler: function(dir){
                store.sort({
                    sorterFn: function(rec1, rec2){
                        var v1 = rec1.get('budgetSpentPct'),
                            v2 = rec2.get('budgetSpentPct');
                        if (v1 === v2) {
                            if (v1 !== null) {
                                v1 = rec1.get('budgetSpent')*1;
                                v2 = rec2.get('budgetSpent')*1;
                            }
                            if (v1 === v2) {
                                return 0;
                            } else {
                                return v1 > v2 ? 1 : -1;
                            }
                        } else {
                            return v1 > v2 || v2 === null ? 1 : -1;
                        }
                    },
                    direction: dir
                });
            }
        }];

        if (me.subject === 'projects') {
            sortItems.push({
                text: 'Order by parent cost center',
                checked: false,
                group: sortGroupName,
                sortHandler: function(dir){
                    store.sort([{
                        property: 'ccName',
                        direction: dir,
                        transform: function(value){
                            return value.toLowerCase();
                        }
                    },{
                        property: 'name',
                        transform: function(value){
                            return value.toLowerCase();
                        }
                    }]);
                }
            });
        }

        sortItems.push({
            xtype: 'menuseparator'
        },{
            xtype: 'menucheckitem',
            text: 'Show archived ' + (me.subject === 'projects' ? 'Projects' : 'Cost Centers'),
            hideOnClick: true,
            checkHandler: function(comp, checked) {
                var extraParams = store.getProxy().extraParams;
                if (checked) {
                    extraParams['showArchived'] = true;
                } else {
                    delete extraParams['showArchived'];
                }
                store.reload();
            }
        });


        me.items = [
            Ext.create('Ext.panel.Panel', {
                cls: 'x-panel-column-left',
                width: 290,
                items: Ext.create('widget.costanalyticslistview', {
                    subject: me.subject,
                    store: store,
                    listeners: {
                        refresh: function(view){
                            var record = view.getSelectionModel().getLastSelected(),
                                form = view.up('panel').up('panel').down('#form');
                            if (record && record !== form.currentRecord) {
                                form.loadRecord(view.store.getById(record.get(me.subject === 'projects' ? 'projectId' : 'ccId')));
                            }
                        }
                    }
                }),
                layout: 'fit',
                dockedItems: [{
                    xtype: 'toolbar',
                    dock: 'top',
                    ui: 'simple',
                    defaults: {
                        margin: '0 0 0 10'
                    },
                    items: [{
                        xtype: 'filterfield',
                        itemId: 'liveSearch',
                        flex: 1,
                        store: store,
                        margin: 0,
                        matchFieldWidth: false,
                        menu: {
                            xtype: 'menu',
                            //minWidth: 220,
                            defaults: {
                                xtype: 'menuitemsortdir'
                            },
                            items: sortItems
                        }
                    },{
                        itemId: 'add',
                        text: 'New',
                        cls: 'x-btn-green',
                        hidden: !Scalr.utils.isAdmin(),
                        handler: function() {
                            Scalr.event.fireEvent('modal', '#/admin/analytics/' + me.subject + '/edit');
                        }
                    },{
                        itemId: 'refresh',
                        iconCls: 'x-btn-icon-refresh',
                        tooltip: 'Refresh',
                        handler: function() {
                            var dataview = this.up('panel').down('costanalyticslistview');
                            dataview.getSelectionModel().deselectAll();
                            dataview.store.reload();
                        }
                    }]
                }]
            })
        ,{
            xtype: 'container',
            itemId: 'formWrapper',
            flex: 1,
            autoScroll: true,
            layout: 'anchor',
            preserveScrollPosition: true,
            items: [{
                xtype: 'container',
                itemId: 'form',
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                defaults: {
                    anchor: '100%'
                },
                hidden: true,
                cls: 'x-container-fieldset',
                minWidth: 1000,
                maxWidth: 1300,
                listeners: {
                    afterrender: function() {
                        var me = this;
                        me.up('panel').down('costanalyticslistview').on('selectionchange', function(dataview, selection){
                            if (selection.length) {
                                if (me.currentRecord !== selection[0]) {
                                    me.loadRecord(selection[0]);
                                }
                                me.show();
                            } else {
                                me.hide();
                            }
                        });
                    },
                },
                loadRecord: function(record) {
                    var periodField = this.down('costanalyticsperiod');
                    this.currentRecord = record;
                    var idField = me.subject==='costcenters'?'ccId':'projectId';
                    this.down('#itemEdit').modalUrl = '#/admin/analytics/' + me.subject + '/edit?' + idField + '='+record.get(idField);
                    this.down('#itemNotifications').setHref('#/admin/analytics/' + me.subject + '/notifications?' + idField + '='+record.get(idField));
                    periodField.restorePreservedValue('month', !!periodField.getValue());
                },
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    margin: '0 0 12 0',
                    items: [{
                        xtype: 'costanalyticsperiod',
                        listeners: {
                            change: function(mode, startDate, endDate, quarter) {
                                var form = this.up('#form'),
                                    record = form.currentRecord,
                                    tabs = form.down('#tabsWrapper'),
                                    warn;
                                if (record) {
                                    if (me.subject === 'projects') {
                                        this.up('costanalyticslistpanel').loadPeriodData({ccId: record.get('ccId'), projectId: record.get('projectId')}, mode, startDate, endDate, quarter);
                                    } else {
                                        warn = form.down('#emptyCCWarning')
                                        if (record.get('projectsCount') == 0) {
                                            tabs.hide();
                                            warn.down('#title').update({name: record.get('name')});
                                            warn.down('button').modalUrl = '#/admin/analytics/projects/add?ccId='+record.get('ccId');
                                            warn.show();
                                            this.hide();
                                        } else {
                                            warn.hide();
                                            tabs.show();
                                            this.show();
                                            this.up('costanalyticslistpanel').loadPeriodData({ccId: record.get('ccId')}, mode, startDate, endDate, quarter);
                                        }
                                    }
                                }
                            }
                        }
                    },{
                        xtype: 'tbfill'
                    },{
                        xtype: 'button',
                        iconCls: 'x-btn-icon-notifications',
                        itemId: 'itemNotifications',
                        hrefTarget: '_self',
                        href: '#'
                    },{
                        xtype: 'button',
                        iconCls: 'x-btn-icon-settings',
                        itemId: 'itemEdit',
                        margin: '0 0 0 12',
                        handler: function() {
                            Scalr.event.fireEvent('modal', this.modalUrl);
                        }
                    }]
                },{
                    xtype: 'container',
                    itemId: 'emptyCCWarning',
                    hidden: true,
                    flex: 1,
                    layout: {
                        type: 'vbox',
                        align: 'center'
                    },
                    items: [{
                        xtype: 'component',
                        itemId: 'title',
                        cls: 'x-fieldset-subheader',
                        tpl: 'Add your first project to <span style="text-transform:none">&quot;{name}&quot;</span> to begin tracking costs'
                    },{
                        xtype: 'component',
                        anchor: '100%',
                        html: '<a href="https://scalr-wiki.atlassian.net/wiki/x/IwDV" target="_blank">Description of cost centers and projects</a>',
                        margin: '0 0 32 0'
                    },{
                        xtype: 'button',
                        itemId: 'button',
                        margin: '0 0 0 120',
                        padding: '0 24',
                        cls: 'x-btn-green',
                        height: 52,
                        text: 'Create new project',
                        handler: function() {
                            Scalr.event.fireEvent('modal', this.modalUrl);
                        }
                    }]
                },{
                    xtype: 'container',
                    itemId: 'tabsWrapper',
                    items: [{
                        xtype: 'container',
                        layout: {
                            type: 'hbox',
                            pack: 'center'
                        },
                        items: {
                            xtype: 'buttongroupfield',
                            itemId: 'tabs',
                            margin: '18 0 12',
                            defaults: {
                                height: 42,
                                width: 140
                            },
                            value: 'summary',
                            items: [{
                                text: 'Summary',
                                value: 'summary'
                            },{
                                text: 'Cloud spend',
                                value: 'clouds'
                            },{
                                text: (me.subject === 'costcenters' ? 'Project' : 'Farm') + ' spend',
                                value: me.subject === 'costcenters' ? 'projects' : 'farms'
                            }],
                            listeners: {
                                change: function(comp, value) {
                                    me.down('#summary').setVisible(value === 'summary');
                                    me.down('costanalyticsspendsadmin').setVisible(value !== 'summary').setType(value === 'summary' ? null: value);
                                }
                            }
                        }
                    },{
                        xtype: 'container',
                        itemId: 'summary',
                        loadDataDeferred: function() {
                            if (this.isVisible()) {
                                this.loadData.apply(this, arguments);
                            } else {
                                if (this.loadDataBind !== undefined) {
                                    this.un('show', this.loadDataBind, this);
                                }
                                this.loadDataBind = Ext.bind(this.loadData, this, arguments);
                                this.on('show', this.loadDataBind, this, {single: true});
                            }
                        },
                        loadData: function(mode, quarter, startDate, endDate, data) {
                            this.down('analyticsboxesadmin').loadData(mode, quarter, startDate, endDate, data);
                            this.down('#summaryChart').loadData(data['timeline']);
                            this.down('#summaryChartTitle').update(Ext.String.capitalize(data['interval'].replace('day', 'dai')) + 'ly breakdown');
                        },
                        items: [{
                            xtype: 'analyticsboxesadmin',
                            subject: me.subject,
                            listeners: {
                                farmclick: function() {
                                    me.down('#tabs').setValue('farms');
                                }
                            }
                        },{
                            xtype: 'component',
                            cls: 'x-caheader',
                            itemId: 'summaryChartTitle',
                            html: '&nbsp;',
                            margin: '24 20 18 0'
                        },{
                            xtype: 'costanalyticssummary',
                            itemId: 'summaryChart',
                            height: 200,
                            margin: '0 0 20',
                            store: Ext.create('Ext.data.ArrayStore', {
                                fields: [{
                                    name: 'datetime',
                                    type: 'date',
                                    convert: function(v, record) {
                                        return Scalr.utils.Quarters.getDate(v,  true);
                                    }
                                }, 'xLabel', 'label', 'cost', 'rollingAverage', 'rollingAverageMessage', 'budgetUseToDate', 'budgetUseToDatePct', 'quarter', 'year']
                            }),
                            listeners: {
                                afterload: function() {
                                    me.down('#summaryDetails').hide();
                                },
                                itemclick: function(chart, item) {
                                    if (item.record) {
                                        chart.setHighlightItem(null);
                                        me.down('#summaryDetails').loadData(item.record);
                                    }
                                }
                            }
                        },{
                            xtype: 'container',
                            itemId: 'summaryDetails',
                            layout: 'anchor',
                            hidden: true,
                            loadData: function(record){
                                var me = this;
                                cb = function() {
                                    me.suspendLayouts();
                                    me.down('#summaryDetailsTitle').update({label: record.get('label')});
                                    me.down('#summaryDetailsCost').update({cost: record.get('cost')});
                                    me.down('#summaryDetailsRollingAverage').update({
                                        rollingAverage: record.get('rollingAverage'),
                                        rollingAverageMessage: record.get('rollingAverageMessage')
                                    });
                                    me.down('#summaryDetailsBudgetUseToDate').update(record.getData());
                                    me.resumeLayouts(true);
                                    me.show();
                                };
                                if (Ext.isEmpty(record.get('rollingAverage'))) {
                                    var panel = me.up('costanalyticslistpanel');
                                    Scalr.Request({
                                        processBox: {
                                            type: 'action',
                                            msg: 'Computing...'
                                        },
                                        url: '/admin/analytics/' + panel.subject + '/xGetMovingAverageToDate',
                                        params: Ext.apply({
                                            date: Ext.Date.format(record.get('datetime'), 'Y-m-d H:i')
                                        }, panel.requestParams),
                                        success: function (res) {
                                            record.set(res.data);
                                            cb.call(me);
                                        }
                                    });
                                } else {
                                    cb.call(me);
                                }
                            },
                            items: [{
                                xtype: 'component',
                                cls: 'x-caheader',
                                itemId: 'summaryDetailsTitle',
                                tpl: 'On {label}',
                                margin: '0 20 18 0'
                            },{
                                xtype: 'container',
                                layout: 'hbox',
                                anchor: '100%',
                                margin: '0 20 20',
                                cls: 'x-cabox x-cabox-transparent',
                                style: 'border:0',
                                defaults: {
                                    flex: 1
                                },
                                items: [{
                                    xtype: 'component',
                                    itemId: 'summaryDetailsCost',
                                    tpl: '<span class="x-form-item-label-default">Spent</span><div class="title1" style="margin-top:8px">{cost:currency(null, 0)}</div>'
                                },{
                                    xtype: 'component',
                                    itemId: 'summaryDetailsRollingAverage',
                                    tpl: '<span class="x-form-item-label-default">{rollingAverageMessage}</span><div class="title1" style="margin-top:8px">{rollingAverage:currency(null, 0)}</div>'

                                },{
                                    xtype: 'component',
                                    itemId: 'summaryDetailsBudgetUseToDate',
                                    tpl:  '<span class="x-form-item-label-default"><tpl if="quarter">Q{quarter} {year} budget<tpl else>Budget</tpl> use to date</span><div class="title1" style="margin-top:8px">' +
                                          '<tpl if="budgetUseToDatePct">'+
                                              '{budgetUseToDatePct}% ({budgetUseToDate:currency(null, 0)})' +
                                          '<tpl else>'+
                                              '{budgetUseToDate:currency(null, 0)}' +
                                          '</tpl>'+
                                          '</div>'
                                }]
                            }]
                        }]
                    },{
                        xtype: 'costanalyticsspendsadmin',
                        hidden: true,
                        subject: me.subject
                    }]
                }]
            }]
        }];

        me.callParent(arguments);
    }
});

Ext.define('Scalr.ui.CostAnalyticsSpendsAdmin', {
    extend: 'Scalr.ui.CostAnalyticsSpends',
    alias: 'widget.costanalyticsspendsadmin',

    showChartViewTotals: function() {
        var me = this,
            chartWrapper = me.down('#chartWrapper'),
            totals = chartWrapper.down('#totals'),
            details = chartWrapper.down('#details'),
            averageColHeader = Ext.String.capitalize(me.data['interval'].replace('day', 'dai')) + 'ly average',
            forecastColHeader = Ext.String.capitalize(me.mode === 'custom' ? 'period' : me.mode) + ' end estimate',
            titleColHeader = me.type === 'clouds' ? 'Cloud' : (me.type === 'projects' ? 'Project' : 'Farm');

        if (!totals) {
            totals = chartWrapper.add({
                xtype: 'grid',
                itemId: 'totals',
                flex: 1,
                margin: '12 0 0 0',
                cls: 'x-grid-shadow x-grid-no-highlighting',
                features: [{
                    ftype: 'summary',
                    id: 'summary',
                    dock: 'bottom'
                }],
                selModel: {
                    selType: 'selectedmodel',
                    injectCheckbox: 'first',
                    listeners: {
                        selectionchange: function(selModel, selected){
                            var enabledSeries = Ext.Array.map(selected, function(rec){return rec.get('id')});
                            me.down('#chart').toggleSeries(enabledSeries);
                            me.setEnabledSeries(me.type, enabledSeries);
                        }
                    }
                },
                setSelected: function(selected) {
                    this.getSelectionModel().select(selected);
                },
                setSelectedDeferred: function(selected) {
                    if (this.rendered) {
                        this.setSelected(selected);
                    } else {
                        this.on('afterrender', Ext.bind(this.setSelected, this, arguments), this, {single: true});
                    }
                },
                store: {
                    proxy: 'object',
                    fields: [{name: 'id', type: 'string'}, 'name', 'cost', 'costPct', 'prevCost', 'prevCostPct', 'growth', 'growthPct', 'averageCost', 'forecastCost', 'environment'],
                    sorters:[
                        me.permanentSorter
                    ,{
                        property: 'cost',
                        direction: 'DESC'
                    }],
                    data: me.data['totals'][me.type],
                    listeners: {
                        beforesort: Ext.bind(me.applyPermanentSorter, me)
                    }
                },
                viewConfig: {
                    deferEmptyText: false,
                    emptyText: 'No data for selected period'
                },
                columns: [{
                    header: titleColHeader,
                    dataIndex: 'name',
                    sortable: true,
                    flex: 1,
                    maxWidth: 200,
                    xtype: 'templatecolumn',
                    tpl: new Ext.XTemplate(
                            '<tpl if="this.getType()==\'clouds\'">',
                                '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-{id}"/> {[this.getColoredName(values.id, values.name)]}',
                            '<tpl elseif="this.getType()==\'projects\'&&id">',
                                '<a href="#/admin/analytics/projects?projectId={id}">{[this.getColoredName(values.id, values.name)]}</a>',
                            '<tpl else>',
                                '<span data-qtip="{[this.farmInfo(values, true)]}">{[this.getColoredName(values.id, values.name)]}</span>',
                            '</tpl>',
                    {
                        getType: function(){
                            return me.type;
                        },
                        getColoredName: function(id, name){
                            return me.getColoredItemTitle(me.type, id, name);
                        }
                    }),
                    summaryRenderer: function() {
                        return 'Total spend';
                    }
                },{
                    header: 'Total',
                    dataIndex: 'cost',
                    xtype: 'templatecolumn',
                    flex: 1.6,
                    tpl: '{[this.currency(values.cost)]} {[values.costPct > 0 ? \'(\'+values.costPct+\'%)\' : \'\']}',
                    summaryRenderer: function() {
                        return Ext.util.Format.currency(Math.round(me.data['totals']['cost']), null, 0)
                    }
                },{
                    header: 'Previous ' + me.getPeriodTitle(),
                    dataIndex: 'prevCost',
                    xtype: 'templatecolumn',
                    flex: 1.2,
                    tpl: '{[this.currency(values.prevCost)]}',
                    summaryRenderer: function() {
                        return Ext.util.Format.currency(Math.round(me.data['totals']['prevCost']), null, 0)
                    }
                },{
                    header: 'Growth',
                    dataIndex: 'growth',
                    xtype: 'templatecolumn',
                    width: 90,
                    tpl:
                        '<tpl if="growth!=0">' +
                            '{[this.pctLabel(values.growth, values.growthPct, \'small\', \'invert\')]}' +
                        '</tpl>'

                },{
                    header: averageColHeader,
                    dataIndex: 'averageCost',
                    xtype: 'templatecolumn',
                    flex: 1.1,
                    tpl: '<tpl if="averageCost&gt;=.5">{[this.currency(values.averageCost)]}</tpl>'
                },{
                    header: forecastColHeader,
                    dataIndex: 'forecastCost',
                    xtype: 'templatecolumn',
                    flex: 1,
                    tpl: '<tpl if="forecastCost&gt;0">~ {[this.currency(values.forecastCost)]}</tpl>',
                    summaryType: 'sum',
                    summaryRenderer: function(value) {
                        return value > 0 ? '~ ' + Ext.util.Format.currency(value, null, 0) : '';
                    }
                }]
            });
        } else {
            totals.columns[0].setText(titleColHeader);
            totals.columns[2].setText('Previous ' + me.getPeriodTitle());
            totals.columns[4].setText(averageColHeader);
            totals.columns[5].setText(forecastColHeader);
            totals.getSelectionModel().deselectAll(true);
            totals.store.loadData(me.data['totals'][me.type]);
        }
        totals.setSelectedDeferred(Ext.Array.map(me.getEnabledSeries(), function(id){
            return totals.store.getById(id);
        }));
        totals.view.findFeature('summary').toggleSummaryRow(me.data['totals'][me.type] && me.data['totals'][me.type].length > 0);
        totals.show();
        if (details) details.hide();
    },

    showPieViewDetails: function(data) {
        var me = this,
            pieWrapper = me.down('#pieWrapper'),
            details = pieWrapper.down('#details'),
            detailsItemsType = me.type === 'clouds' ? (data['projects'] ? 'projects' : 'farms') : 'clouds',
            detailsItems = data[detailsItemsType],
            headerData, projectCostMax = 0;

        Ext.Array.each(detailsItems, function(item){
            projectCostMax = projectCostMax > item['cost'] ? projectCostMax : item['cost'];
            item.type = detailsItemsType;
        });

        headerData = Ext.applyIf({
            rawName: data['name'],
            name: me.getColoredItemTitle(me.type, data['id'], data['name'], true),
            projectCostMax: projectCostMax
        }, data);

        if (!details) {
            details = pieWrapper.add({
                xtype: 'container',
                itemId: 'details',
                flex: 1,
                margin: '12 0 0',
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                items: [{
                    xtype: 'component',
                    itemId: 'header',
                    cls: 'x-costanalytics-details-header',
                    data: headerData,
                    tpl: new Ext.XTemplate(
                        '<table>' +
                            '<tr class="title">' +
                                '<td style="width:180px;" class="title" rowspan="2" title="{rawName:htmlEncode}">{name}</td>' +
                                '<td>{[this.getPeriodTitle(true)]} spend</td>' +
                                '<td style="width:80px;">&nbsp;</td>' +
                                '<td style="width:150px;">Previous {[this.getPeriodTitle()]}</td>' +
                                '<td style="width:130px;">Growth</td>' +
                            '</tr>' +
                            '<tr class="value">' +
                                '<td>{[this.currency(values.cost)]}</td>' +
                                '<td>&nbsp;</td>' +
                                '<td>{[this.currency(values.prevCost)]}</td>' +
                                '<td>' +
                                    '<tpl if="growth!=0">' +
                                        '{[this.pctLabel(values.growth, values.growthPct, \'small\', \'fixed\')]}' +
                                    '</tpl>'+
                                '</td>' +
                            '</tr>' +
                        '</table>',
                        {
                            getMode: function(){
                                return me.mode;
                            },
                            getPeriodTitle: function(capitalize){
                                return me.getPeriodTitle(capitalize);
                            }
                        }
                    )
                },{
                    xtype: 'dataview',
                    itemId: 'body',
                    flex: 1,
                    cls: 'x-costanalytics-details',
                    itemSelector: '.x-item',
                    autoScroll: true,
                    store: {
                        proxy: 'object',
                        fields: ['id', 'name', 'cost', 'costPct', 'prevCost', 'growth', 'growthPct', 'growthPrevPoint', 'growthPrevPointPct', 'type'],
                        sorters: {
                            property: 'cost',
                            direction: 'DESC'
                        },
                        data: detailsItems
                    },
                    collectData: function(records, startIndex){
                        var data = this.headerData,
                            i = 0,
                            len = records.length,
                            record;
                        data.items = [];
                        for (; i < len; i++) {
                            record = records[i];
                            data.items[i] = this.prepareData(record.data, startIndex + i, record);
                        }
                        return data;
                    },
                    tpl: new Ext.XTemplate(
                        '<table>' +
                            '<tpl if="items.length">' +
                                '<tpl for="items">' +
                                    '<tr class="x-item">' +
                                        '<td style="width:180px" title="{name:htmlEncode}">{[this.getItemTitle(values.type, values.id, values.name)]}</td>' +
                                        '<td><div><div class="bar-inner" style="margin:0;width:{[parent.projectCostMax?values.cost/parent.projectCostMax*100:0]}%"><span>{[this.currency(values.cost)]}</span></div></div></td>' +
                                        '<td style="width:80px">{costPct:round(2)}%</td>' +
                                        '<td style="width:150px">{[this.currency(values.prevCost)]}</td>' +
                                        '<td style="width:130px">' +
                                            '<tpl if="growth!=0">' +
                                                '{[this.pctLabel(values.growth, values.growthPct, \'small\', \'fixed\')]}' +
                                            '</tpl>'+
                                        '</td>' +
                                    '</tr>' +
                                '</tpl>' +
                            '<tpl else>' +
                                '<tr><td class="x-empty">No spend details found</td></tr>' +
                            '</tpl>' +
                        '</table>'
                        ,
                        {
                            getItemTitle: function(type, id, name){
                                var res = me.getItemTitle(type, id, name);
                                if (type === 'projects' && id) {
                                    res = '<a href="#/admin/analytics/projects?projectId='+id+'">' + res + '</a>';
                                }
                                return res;
                            }
                        }
                    ),
                    headerData: headerData
                }]
            });
        } else {
            details.down('#header').update(headerData);
            details.down('dataview').headerData = headerData;
            details.down('dataview').store.loadData(detailsItems);
        }
        details.show();
    },

    onShowChartViewDetails: function(index, id, name, label, datetime, data) {
        var me = this;
        if (me.subject === 'projects' && me.type === 'clouds') {
            Scalr.Request({
                processBox: {
                    type: 'action',
                    msg: 'Computing...'
                },
                url: '/admin/analytics/projects/xGetProjectFarmsTopUsageOnDate',
                params: {
                    ccId: me.data['ccId'],
                    projectId: me.data['projectId'],
                    platform: id,
                    date: Ext.Date.format(datetime, 'Y-m-d H:i'),
                    mode: me.mode,
                    start: Ext.Date.format(me.startDate, 'Y-m-d'),
                    end: Ext.Date.format(me.endDate, 'Y-m-d')
                },
                success: function (res) {
                    var data2 = Ext.apply({}, data);
                    data2['farms'] = res['data'];
                    me.showChartViewDetails(index, id, name, label, datetime, data2);
                }
            });
        } else {
            me.showChartViewDetails.apply(me, arguments);
        }
    },

    showChartViewDetails: function(index, id, name, label, datetime, data) {
        var me = this,
            chartWrapper = me.down('#chartWrapper'),
            details = chartWrapper.down('#details'),
            detailsItemsType = me.type === 'clouds' ? (data['projects'] ? 'projects' : 'farms') : 'clouds',
            detailsItems = data[detailsItemsType],
            titleData, headerData, projectCostMax = 0;
        titleData = {label: label};

        chartWrapper.suspendLayouts();

        Ext.Array.each(detailsItems, function(item){
            projectCostMax = projectCostMax > item['cost'] ? projectCostMax : item['cost'];
            item.type = detailsItemsType;
        });

        headerData = Ext.applyIf({
            rawName: name,
            name: me.getColoredItemTitle(me.type, id, name, true),
            projectCostMax: projectCostMax
        }, data);

        if (!details) {
            details = chartWrapper.add({
                xtype: 'container',
                itemId: 'details',
                flex: 1,
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                margin: '18 10 0 0',
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'component',
                        itemId: 'title',
                        cls: 'x-caheader',
                        tpl: '{label}',
                        data: titleData
                    },{
                        xtype: 'tbfill'
                    },{
                        xtype: 'button',
                        width: 120,
                        text: 'Back to total',
                        handler: function(){
                            me.showChartViewTotals();
                        }
                    }]
                },{
                    xtype: 'component',
                    itemId: 'header',
                    cls: 'x-costanalytics-details-header',
                    data: headerData,
                    tpl: new Ext.XTemplate(
                        '<table>' +
                            '<tr class="title">' +
                                '<td style="width:180px;" class="title" rowspan="2" title="{rawName:htmlEncode}">{name}</td>' +
                                '<td>Total spend</td>' +
                                '<td style="width:80px">&nbsp;</td>' +
                                '<td style="width:200px">Growth from prev {[this.getMode()]}</td>' +
                                '<td style="width:200px">Growth from prev {[this.getIntervalTitle()]}</td>' +
                            '</tr>' +
                            '<tr class="value">' +
                                '<td style="overflow:visible">{[this.currency(values.cost)]} ({[this.currency(values.prevCost)]} previous {[this.getMode()]})</td>' +
                                '<td style="width:80px">&nbsp;</td>' +
                                '<td>' +
                                    '<tpl if="growth!=0">' +
                                        '{[this.pctLabel(values.growth, values.growthPct, \'small\', \'fixed\')]}' +
                                    '</tpl>'+
                                '</td>' +
                                '<td>' +
                                    '<tpl if="growthPrevPoint!=0">' +
                                        '{[this.pctLabel(values.growthPrevPoint, values.growthPrevPointPct, \'small\', \'fixed\')]}' +
                                    '</tpl>'+
                                '</td>' +
                            '</tr>' +
                        '</table>',
                        {
                            getMode: function(){
                                return me.mode;
                            },
                            getIntervalTitle: function(){
                                return me.interval;
                            }
                        }
                    )
                },{
                    xtype: 'dataview',
                    itemId: 'body',
                    flex: 1,
                    cls: 'x-costanalytics-details',
                    itemSelector: '.x-item',
                    autoScroll: true,
                    store: {
                        proxy: 'object',
                        fields: ['id', 'name', 'cost', 'costPct', 'growth', 'growthPct', 'growthPrevPoint', 'growthPrevPointPct', 'type', 'environment'],
                        sorters: {
                            property: 'cost',
                            direction: 'DESC'
                        },
                        data: detailsItems
                    },
                    collectData: function(records, startIndex){
                        var data = this.headerData,
                            i = 0,
                            len = records.length,
                            record;
                        data.items = [];
                        for (; i < len; i++) {
                            record = records[i];
                            data.items[i] = this.prepareData(record.data, startIndex + i, record);
                        }
                        return data;
                    },
                    tpl: new Ext.XTemplate(
                        '<table>' +
                            '<tpl if="items.length">' +
                                '<tpl for="items">' +
                                    '<tr class="x-item">' +
                                        '<td style="width:180px" data-qtip="{[this.farmInfo(values, true)]}">{[this.getItemTitle(values.type, values.id, values.name)]}</td>' +
                                        '<td><div><div class="bar-inner" style="margin:0;width:{[parent.projectCostMax?values.cost/parent.projectCostMax*100:0]}%"><span>{[this.currency(values.cost)]}</span></div></div></td>' +
                                        '<td style="width:80px;text-align:center">{costPct:round(2)}%</td>' +
                                        '<td style="width:200px">' +
                                            '<tpl if="growth!=0">' +
                                                '{[this.pctLabel(values.growth, values.growthPct, \'small\', \'fixed\')]}' +
                                            '</tpl>'+
                                        '</td>' +
                                        '<td style="width:200px">' +
                                            '<tpl if="growthPrevPoint!=0">' +
                                                '{[this.pctLabel(values.growthPrevPoint, values.growthPrevPointPct, \'small\', \'fixed\')]}' +
                                            '</tpl>'+
                                        '</td>' +
                                    '</tr>' +
                                '</tpl>' +
                            '<tpl else>' +
                                '<tr><td class="x-empty">No spend details found</td></tr>' +
                            '</tpl>' +
                        '</table>'
                        ,
                        {
                            getItemTitle: function(type, id, name){
                                var res = me.getItemTitle(type, id, name);
                                if (type === 'projects' && id) {
                                    res = '<a href="#/admin/analytics/projects?projectId='+id+'">' + res + '</a>';
                                }
                                return res;
                            }
                        }
                    ),
                    headerData: headerData
                }]
            });
        } else {
            details.down('#title').update(titleData);
            details.down('#header').update(headerData);
            details.down('dataview').headerData = headerData;
            details.down('dataview').store.loadData(detailsItems);
        }
        details.show();
        chartWrapper.down('#totals').hide();
        chartWrapper.resumeLayouts(true);
    },

});

//environment
Ext.define('Scalr.ui.AnalyticsBoxesEnv', {
    extend: 'Scalr.ui.AnalyticsBoxes',
    alias: 'widget.analyticsboxesenv',

    loadData: function(mode, quarter, startDate, endDate, data) {
        var me = this,
            costDistribution = [],
            totals;

        me.callParent(arguments);

        totals = me.data['totals'];

        //total
        me.down('#total').update({
            title: me.title,
            cost: totals['cost'],
            forecastCost: totals['forecastCost'],
            growth: totals['growth'],
            growthPct: totals['growthPct'],
            trends: totals['trends'],
            interval: data['interval'],
            period: mode === 'custom' ? 'period' : mode
        });

        me.down('#top5farms').loadData(totals['farms']);

        Ext.Object.each(Scalr.constants.distributionTypes, function(id, name){
            var cost = null,
                costPct = null;
            Ext.Array.each(totals['distributionTypes'], function(item){
                if (item.id == id) {
                    cost = item.cost;
                    costPct = item.costPct;
                    return false;
                }
            });
            costDistribution.push({
                id: id,
                name: name,
                cost: cost,
                costPct: costPct
            });
        });
        me.down('#distribution').loadData(costDistribution);
    },
    defaults: {
        minHeight: 240
    },
    items: [{
        xtype: 'component',
        cls: 'x-cabox',
        itemId: 'total',
        flex: 1,
        minWidth: 160,
        maxWidth: 500,
        tpl:
            '<div class="x-cabox-title">{title}</div>'+
            '<div style="margin:16px 0 0"><span class="x-form-item-label-default">Spent</span>' +
                '<div>' +
                    '<span class="title1">{[this.currency2(values.cost, true)]}</span>' +
                    '<tpl if="growth!=0">' +
                        ' &nbsp;{[this.pctLabel(values.growth, values.growthPct, false, false, false, false)]}' +
                    '</tpl>'+
                '</div>' +
            '</div>'+
            '<tpl if="trends">' +
                '<div style="margin:22px 0 0"><span class="x-form-item-label-default">{trends.rollingAverageMessage}</span>' +
                    '<div style="margin: 0 0 8px"><span class="title1">{[this.currency2(values.trends.rollingAverage, true)]}</span> per {interval}</div>' +
                '</div>' +
            '</tpl>' +
            '<tpl if="forecastCost!==null">' +
                '<div style="margin:22px 0 6px" class="x-form-item-label-default">{period:capitalize} end estimate</div>' +
                '<span class="title2" style="padding-right:1em">~ {[this.currency2(values.forecastCost, true)]}</span>' +
            '</tpl>'
    },{
        xtype: 'dataview',
        cls: 'x-cabox',
        itemId: 'distribution',
        flex: 1,
        itemSelector: '.x-top5-item',
        emptyText: 'No data available',
        loadData: function(data) {
            var maxCost,
                rows = [];
            Ext.each(data, function(row){
                row = Ext.clone(row);
                rows.push(row);
                row['pctOfMax'] = 0;
                if (row['cost'] !== null) {
                    maxCost = maxCost > row['cost'] ? maxCost : row['cost'];
                }
            });
            if (maxCost != 0) {
                Ext.each(rows, function(row){
                    row['pctOfMax'] = row['cost'] !== null ? row['cost']/maxCost*100 : 0;
                });
            }
            this.store.loadData(rows);
        },
        store: {
            proxy: 'object',
            sorters: {
                property: 'cost',
                direction: 'DESC'
            },
            fields: ['cost', 'costPct', 'pctOfMax', 'name']
        },
        tpl: new Ext.XTemplate(
            '<div class="x-cabox-title">Cost distribution</div>'+
            '<table class="x-cabox-distribution">' +
                '<tpl for=".">' +
                    '<tr class="x-top5-item">' +
                        '<td>' +
                            '<div style="max-width:250px">{name:capitalize}</div>' +
                            '<div style="margin-top:4px"><div class="bar-inner x-costanalytics-bg-{name}" style="margin:0;width:{pctOfMax}%">'+
                                '<span>{[values.cost === null ? \'N/A\' : this.currency2(values.cost)]}</span>'+
                            '</div></div>' +
                        '</td>'+
                        '<td style="text-align:center;width:75px;"><div style="margin-bottom:4px"><tpl if="xindex==1"><b>% of total</b></tpl>&nbsp;</div>' +
                            '{[values.costPct === null ? \'N/A\' : values.costPct + \'%\']}'+
                        '</td>'+
                    '</tr>' +
                '</tpl>'+
            '</table>'
        )
    },{
        xtype: 'dataview',
        cls: 'x-cabox',
        itemId: 'top5farms',
        flex: 1,
        itemSelector: '.x-top5-item',
        emptyText: '<br/>No data available',
        loadData: function(data) {
            var maxCost,
                rows = [];

            Ext.each(data, function(row){
                if (row.id !== 'everything else') {
                    row = Ext.clone(row);
                    rows.push(row);
                    row['pctOfMax'] = 0;
                    maxCost = maxCost > row['cost'] ? maxCost : row['cost'];
                }
            });
            if (maxCost != 0) {
                Ext.each(rows, function(row){
                    row['pctOfMax'] = row['cost']/maxCost*100;
                });
            }
            this.store.loadData(rows);
        },
        store: {
            proxy: 'object',
            sorters: {
                property: 'cost',
                direction: 'DESC'
            },
            fields: ['id', 'name', 'cost', 'costPct', 'pctOfMax', 'environment']
        },
        tpl: new Ext.XTemplate(
            '<div class="x-cabox-title">Top 5 farms</div>'+
            '<table class="x-cabox-top">' +
                '<tpl for=".">' +
                    '<tpl if="xindex&lt;6">' +
                        '<tr class="x-top5-item">' +
                            '<td>' +
                                '<tpl if="id && this.checkEnvId(environment)">' +
                                    '<a href="#/analytics/farms?farmId={id}">' +
                                '</tpl>' +
                                    '<div style="max-width:250px" class="link">{name}</div>' +
                                    '<div style="margin-top:4px"><div class="bar-inner" style="margin:0;width:{pctOfMax}%"><span>{[this.currency2(values.cost)]}</span></div></div>' +
                                '<tpl if="id && this.checkEnvId(environment)">' +
                                    '</a>' +
                                '</tpl>' +
                            '</td>'+
                        '</tr>' +
                    '</tpl>'+
                '</tpl>'+
            '</table>'
        , {
            checkEnvId: function(environment) {
                return Scalr.user['envId'] == (environment || {}).id;
            }
        })

    }]
});

Ext.define('Scalr.ui.CostAnalyticsSpendsUser', {
    extend: 'Scalr.ui.CostAnalyticsSpends',
    alias: 'widget.costanalyticsspendsuser',

    defaultView: 'stacked',
    roundCosts: false,

    chartViewTipCfg: {
        cls: 'x-tip-light',
        //anchor: 'top',
        tpl:
            '<div class="x-caheader">{label}</div>' +
            '<div style="margin:8px 0 0;padding-right:10px;max-height:224px;overflow-x:hidden;overflow-y:auto;white-space:nowrap">' +
                '<table>' +
                '<tr>' +
                    '<td style="padding-bottom:6px"><b>Total</b></td>' +
                    '<td style="padding-bottom:6px"><b>{[this.currency2(values.total.cost)]}</b></td>' +
                    '<td style="padding-bottom:6px">{[values.total.growthPrevPoint!=0 ? this.pctLabel(values.total.growthPrevPoint, values.total.growthPrevPointPct, \'small\', false, \'invert\', false):\'\']}</td>' +
                '</tr>' +
                '<tpl for="items">' +
                    '<tr>' +
                        '<td>{[this.getColoredName(values.id, values.name)]}&nbsp;&nbsp;&nbsp;&nbsp;</td>' +
                        '<td>{[this.currency2(values.cost)]} ({costPct}%)&nbsp;&nbsp;&nbsp;&nbsp;</td>' +
                        '<td>{[values.growthPrevPoint!=0 ? this.pctLabel(values.growthPrevPoint, values.growthPrevPointPct, \'small\', false, \'invert\', false):\'\']}</td>' +
                    '</tr>' +
                '</tpl>'+
                '</table>' +
            '</div>'
    },
    chartViewTipRenderer: function(tooltip, record, item) {
        var items = [],
            itemsInfo = this.data[this.type],
            sortItems = new Ext.util.MixedCollection();
        Ext.Object.each(record.get('extrainfo'), function(id, item){
            if (item) {
                items.push({
                    id: id,
                    name: itemsInfo[id] ? itemsInfo[id]['name'] : id,
                    cost: item['cost'],
                    costPct: item['costPct'],
                    growthPrevPoint: item['growthPrevPoint'],
                    growthPrevPointPct: item['growthPrevPointPct']
                });
            }
        });
        sortItems.addAll(items);
        sortItems.sort([{
            sorterFn: function(o1, o2){
                return o1.id === 'everything else' ? 1 : (o2.id === 'everything else' ? -1 : 0);
            }
        },{
            property: 'cost',
            direction: 'DESC'
        }]);

        tooltip.update({
            total: this.data['timeline'][record.get('index')],
            label: record.get('label'),
            items: sortItems.getRange()
        });

    },
});

Ext.define('Scalr.ui.CostAnalyticsSpendsFarms', {
    extend: 'Scalr.ui.CostAnalyticsSpendsUser',
    alias: 'widget.costanalyticsspendsfarms',

    subject: 'farms',
    type: 'farmRoles',
    chartVeiwTotalSeries: true,

    showChartViewTotals: function() {
        var me = this,
            chartWrapper = me.down('#chartWrapper'),
            totals = chartWrapper.down('#totals'),
            details = chartWrapper.down('#details'),
            averageColHeader = Ext.String.capitalize(me.data['interval'].replace('day', 'dai')) + 'ly average';
        if (!totals) {
            totals = chartWrapper.add({
                xtype: 'grid',
                itemId: 'totals',
                flex: 1,
                margin: '12 0 0 0',
                cls: 'x-grid-shadow x-grid-no-highlighting',
                features: [{
                    ftype: 'summary',
                    id: 'summary',
                    dock: 'bottom'
                }],
                selModel: {
                    selType: 'selectedmodel',
                    injectCheckbox: 'first',
                    listeners: {
                        selectionchange: function(selModel, selected){
                            var enabledSeries = Ext.Array.map(selected, function(rec){return rec.get('id')});
                            me.down('#chart').toggleSeries(enabledSeries);
                            me.setEnabledSeries(me.type, enabledSeries);
                        }
                    }
                },
                setSelected: function(selected) {
                    this.getSelectionModel().select(selected);
                },
                setSelectedDeferred: function(selected) {
                    if (this.rendered) {
                        this.setSelected(selected);
                    } else {
                        this.on('afterrender', Ext.bind(this.setSelected, this, arguments), this, {single: true});
                    }
                },
                store: {
                    proxy: 'object',
                    fields: [{name: 'id', type: 'string'}, 'name', 'cost', 'costPct', 'prevCost', 'prevCostPct', 'growth', 'growthPct', 'averageCost', 'cloudLocation', 'platform', 'instances'],
                    sorters: {
                        property: 'cost',
                        direction: 'DESC'
                    },
                    data: me.data['totals'][me.type]
                },
                viewConfig: {
                    deferEmptyText: false,
                    emptyText: 'No data for selected period'
                },
                listeners: {
                    itemclick: function (view, record, item, index, e) {
                        if (e.getTarget('.scalr-ui-analytics-link')) {
                            me.onShowInstanceTypeDetails('chart', 0, record.get('id'), record.get('name'), null, null, record.getData());
                            e.preventDefault();
                            return false;
                        }
                    }
                },
                columns: [{
                    header: 'Farm role',
                    dataIndex: 'name',
                    sortable: true,
                    flex: 1,
                    xtype: 'templatecolumn',
                    tpl: new Ext.XTemplate(
                        '<span class="scalr-ui-analytics-link" style="color:#{[this.getItemColor(values.id)]}"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-{platform}"/> {name}</span>',
                    {
                        getItemColor: function(id){
                            return me.getItemColor(id, me.type);
                        }
                    }),
                    summaryRenderer: function() {
                        return 'Total spend';
                    }
                },{
                    header: 'Location',
                    dataIndex: 'cloudLocation',
                    flex: .6
                },{
                    header: 'Spend (% of total)',
                    dataIndex: 'cost',
                    xtype: 'templatecolumn',
                    width: 160,
                    tpl: '{[this.currency2(values.cost)]} {[values.costPct > 0 ? \'(\'+values.costPct+\'%)\' : \'\']}',
                    summaryRenderer: function() {
                        return Ext.util.Format.currency(me.data['totals']['cost'], null, 2);
                    }
                },{
                    header: 'Growth',
                    dataIndex: 'growth',
                    xtype: 'templatecolumn',
                    width: 100,
                    tpl:
                        '<tpl if="growth!=0">' +
                            '{[this.pctLabel(values.growth, values.growthPct, \'small\', \'invert\', false, false)]}' +
                        '</tpl>'

                },{
                    header: averageColHeader,
                    dataIndex: 'averageCost',
                    xtype: 'templatecolumn',
                    flex: .6,
                    tpl: '<tpl if="averageCost&gt;0">{[this.currency2(values.averageCost)]}</tpl>'
                },{
                    xtype: 'templatecolumn',
                    resizable: false,
                    sortable: false,
                    width: 48,
                    tpl: '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-details scalr-ui-analytics-link" style="cursor:pointer" title="View farm role details" />'
                }]
            });
        } else {
            totals.columns[4].setText(averageColHeader);
            totals.getSelectionModel().deselectAll(true);
            totals.store.loadData(me.data['totals'][me.type]);
        }
        totals.setSelectedDeferred(Ext.Array.map(me.getEnabledSeries(), function(id){
            return totals.store.getById(id);
        }));
        totals.view.findFeature('summary').toggleSummaryRow(me.data['totals'][me.type] && me.data['totals'][me.type].length > 0);
        totals.show();
        if (details) details.hide();
    },

    onShowChartViewDetails: function(index, id, name, label, datetime, data) {
        this.onShowInstanceTypeDetails('chart', index, id, name, label, datetime, data);
    },

    showPieViewDetails: function(data) {
        this.onShowInstanceTypeDetails('pie', 0, data.id, data.name, null, null, data);
    }

});

Ext.define('Scalr.ui.CostAnalyticsSpendsEnv', {
    extend: 'Scalr.ui.CostAnalyticsSpendsUser',
    alias: 'widget.costanalyticsspendsenv',

    subject: 'envs',
    type: 'clouds',
    hidePieChartInfo: true,

    showChartViewTotals: function() {
        var me = this,
            chartWrapper = me.down('#chartWrapper'),
            totals = chartWrapper.down('#totals'),
            details = chartWrapper.down('#details'),
            averageColHeader = Ext.String.capitalize(me.data['interval'].replace('day', 'dai')) + 'ly average',
            forecastColHeader = Ext.String.capitalize(me.mode === 'custom' ? 'period' : me.mode) + ' end estimate',
            titleColHeader = me.type === 'clouds' ? 'Cloud' : (me.type === 'projects' ? 'Project' : 'Farm');
        if (!totals) {
            totals = chartWrapper.add({
                xtype: 'grid',
                itemId: 'totals',
                flex: 1,
                margin: '24 0 0 0',
                cls: 'x-grid-no-highlighting',
                features: [{
                    ftype: 'summary',
                    id: 'summary',
                    dock: 'bottom'
                }],
                selModel: {
                    selType: 'selectedmodel',
                    injectCheckbox: 'first',
                    listeners: {
                        selectionchange: function(selModel, selected){
                            var enabledSeries = Ext.Array.map(selected, function(rec){return rec.get('id')});
                            me.down('#chart').toggleSeries(enabledSeries);
                            me.setEnabledSeries(me.type, enabledSeries);
                        }
                    }
                },
                setSelected: function(selected) {
                    this.getSelectionModel().select(selected);
                },
                setSelectedDeferred: function(selected) {
                    if (this.rendered) {
                        this.setSelected(selected);
                    } else {
                        this.on('afterrender', Ext.bind(this.setSelected, this, arguments), this, {single: true});
                    }
                },
                store: {
                    proxy: 'object',
                    fields: [{name: 'id', type: 'string'}, 'name', 'cost', 'costPct', 'prevCost', 'prevCostPct', 'growth', 'growthPct', 'averageCost', 'forecastCost', 'environment', 'projectName', 'email'],
                    sorters: [
                        me.permanentSorter
                    ,{
                        property: 'cost',
                        direction: 'DESC'
                    }],
                    listeners: {
                        beforesort: Ext.bind(me.applyPermanentSorter, me)
                    },
                    data: me.data['totals'][me.type]
                },
                viewConfig: {
                    deferEmptyText: false,
                    emptyText: 'No data for selected period'
                },
                columns: [{
                    header: titleColHeader,
                    dataIndex: 'name',
                    sortable: true,
                    flex: 1,
                    xtype: 'templatecolumn',
                    tpl: new Ext.XTemplate(
                            '<tpl if="this.getType()==\'clouds\'">',
                                '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-{id}"/> {[this.getColoredName(values.id, values.name)]}',
                            '<tpl else>',
                                '<tpl if="id!=\'everything else\' && this.getLevel()==\'environment\'">',
                                    '<a href="#/analytics/farms?farmId={id}" data-qtip="{[this.farmInfo(values, true)]}">{[this.getColoredName(values.id, values.name)]}</a>',
                                '<tpl else>',
                                    '<span data-qtip="{[this.farmInfo(values, true)]}">{[this.getColoredName(values.id, values.name)]}</span>',
                                '</tpl>',
                            '</tpl>',
                    {
                        getType: function(){
                            return me.type;
                        },
                        getLevel: function(){
                            return me.level;
                        },
                        getColoredName: function(id, name){
                            return me.getColoredItemTitle(me.type, id, name);
                        }
                    }),
                    summaryRenderer: function() {
                        return 'Total spend';
                    }
                },{
                    header: 'Project',
                    dataIndex: 'projectName',
                    flex: 1,
                    hidden: true
                },{
                    header: 'Owner',
                    dataIndex: 'email',
                    flex: 1,
                    hidden: true
                },{
                    header: 'Spend (% of total)',
                    dataIndex: 'cost',
                    xtype: 'templatecolumn',
                    flex: 1.6,
                    tpl: '{[this.currency2(values.cost)]} {[values.costPct > 0 ? \'(\'+values.costPct+\'%)\' : \'\']}',
                    summaryRenderer: function() {
                        return Ext.util.Format.currency(me.data['totals']['cost']);
                    }
                },{
                    header: 'Growth',
                    dataIndex: 'growth',
                    xtype: 'templatecolumn',
                    width: 90,
                    tpl:
                        '<tpl if="growth!=0">' +
                            '{[this.pctLabel(values.growth, values.growthPct, \'small\', \'invert\', false, false)]}' +
                        '</tpl>'

                },{
                    header: averageColHeader,
                    dataIndex: 'averageCost',
                    xtype: 'templatecolumn',
                    flex: 1.1,
                    tpl: '<tpl if="averageCost&gt;0">{[this.currency2(values.averageCost)]}</tpl>'
                },{
                    header: forecastColHeader,
                    dataIndex: 'forecastCost',
                    xtype: 'templatecolumn',
                    flex: 1,
                    tpl: '<tpl if="forecastCost&gt;0">~ {[this.currency2(values.forecastCost)]}</tpl>',
                    summaryType: 'sum',
                    summaryRenderer: function(value) {
                        return value > 0 ? '~ ' + Ext.util.Format.currency(value) : '';
                    }
                }]
            });
        } else {
            totals.columns[0].setText(titleColHeader);
            totals.columns[5].setText(averageColHeader);
            totals.columns[6].setText(forecastColHeader);
            totals.getSelectionModel().deselectAll(true);
            totals.store.loadData(me.data['totals'][me.type]);
        }
        totals.columns[1].setVisible(me.type === 'farms' && me.subject === 'envs');
        totals.columns[2].setVisible(me.type === 'farms');
        totals.columns[6].setVisible(me.type !== 'farms');
        totals.setSelectedDeferred(Ext.Array.map(me.getEnabledSeries(), function(id){
            return totals.store.getById(id);
        }));
        totals.view.findFeature('summary').toggleSummaryRow(me.data['totals'][me.type] && me.data['totals'][me.type].length > 0);
        totals.show();
        if (details) details.hide();
    },

    onShowChartViewDetails: function(index, id, name, label, datetime, data) {
        if (this.type === 'farms') {
            this.onShowInstanceTypeDetails('chart', index, id, name, label, datetime, data);
        }
    },

    selectPieView: function() {
        var me = this,
            viewWrapper = me.down('#viewWrapper'),
            pieWrapper = viewWrapper.getComponent('pieWrapper'),
            pie,
            data = me.data['totals'][me.type],
            hideChart = me.data['totals']['cost'] == 0,
            averageColHeader = Ext.String.capitalize(me.data['interval'].replace('day', 'dai')) + 'ly average',
            titleColHeader = me.type === 'clouds' ? 'Cloud' : 'Farm',
            grid,
            chart;

        chart = {
            xtype: 'polar',
            width: 160,
            height: 160,
            theme: 'scalr',
            insetPadding: 0,
            hidden: hideChart,
            series: [{
                type: 'pie',
                xField: 'cost',
                donut: 24,
                renderer: function(sprite, config, data, index){
                    var record = data.store.getData().items[index];
                    return {
                        fillStyle: '#' + me.getItemColor(record.get('id'), me.type)
                    };
                },
                tooltip: {
                    trackMouse: true,
                    hideDelay: 0,
                    showDelay: 0,
                    tpl: '{[this.itemCost(values, false)]}',
                    renderer: function(record, item) {
                        this.update({
                            id: record.get('id'),
                            type: me.type,
                            name: me.type === 'clouds' ? Scalr.utils.getPlatformName(record.get('name')) : record.get('name'),
                            cost: record.get('cost'),
                            costPct: record.get('costPct')
                        });
                    }
                }
            }]
        };
        viewWrapper.suspendLayouts();
        if (!pieWrapper) {
            pieWrapper = viewWrapper.add({
                xtype: 'container',
                itemId: 'pieWrapper',
                margin: '0 10',
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                }
            });
        }

        pie = pieWrapper.getComponent('pie');
        if (!pie) {
            var store = Ext.create('store.store', {
                proxy: 'object',
                fields: [{name: 'id', type: 'string'}, 'name', 'cost', 'costPct', 'prevCost', 'prevCostPct', 'growth', 'growthPct', 'averageCost', 'forecastCost', 'environment'],
                sorters: [
                    me.permanentSorter
                ,{
                    property: 'cost',
                    direction: 'DESC'
                }],
                listeners: {
                    beforesort: Ext.bind(me.applyPermanentSorter, me)
                },
                data: data
            });
            chart.store = store;
            pie = pieWrapper.add({
                xtype: 'container',
                itemId: 'pie',
                layout: {
                    type: 'hbox',
                    align: 'middle'
                },
                items: [chart, {
                    xtype: 'grid',
                    cls: 'x-grid-shadow x-grid-no-highlighting',
                    store: store,
                    flex: 1,
                    maxHeight: 400,
                    margin: '0 0 0 16',
                    features: [{
                        ftype: 'summary',
                        id: 'summary',
                        dock: 'bottom'
                    }],
                    viewConfig: {
                        emptyText: 'No data for selected period',
                        deferEmptyText: false
                    },
                    columns: [{
                        header: titleColHeader,
                        dataIndex: 'name',
                        sortable: true,
                        flex: 1,
                        xtype: 'templatecolumn',
                        tpl: new Ext.XTemplate(
                                '<tpl if="this.getType()==\'clouds\'">',
                                    '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-{id}"/> {[this.getColoredName(values.id, values.name)]}',
                                '<tpl else>',
                                    '<span data-qtip="{[this.farmInfo(values, true)]}">{[this.getColoredName(values.id, values.name)]}</span>',
                                '</tpl>',
                        {
                            getType: function(){
                                return me.type;
                            },
                            getColoredName: function(id, name){
                                return me.getColoredItemTitle(me.type, id, name);
                            }
                        }),
                        summaryRenderer: function() {
                            return 'Total spend';
                        }
                    },{
                        header: 'Spend (% of total)',
                        dataIndex: 'cost',
                        xtype: 'templatecolumn',
                        flex: 1.6,
                        tpl: '{[this.currency2(values.cost)]} {[values.costPct > 0 ? \'(\'+values.costPct+\'%)\' : \'\']}',
                        summaryRenderer: function() {
                            return Ext.util.Format.currency(me.data['totals']['cost'])
                        }
                    },{
                        header: 'Growth',
                        dataIndex: 'growth',
                        xtype: 'templatecolumn',
                        width: 90,
                        tpl:
                            '<tpl if="growth!=0">' +
                                '{[this.pctLabel(values.growth, values.growthPct, \'small\', \'invert\', false, false)]}' +
                            '</tpl>'

                    },{
                        header: averageColHeader,
                        dataIndex: 'averageCost',
                        xtype: 'templatecolumn',
                        flex: 1.1,
                        tpl: '<tpl if="averageCost&gt;=.5">{[this.currency2(values.averageCost)]}</tpl>'
                    }]
                }]
            });
            grid = pie.down('grid');
        } else {
            grid = pie.down('grid');
            pie.suspendLayouts();
            grid.store.loadData(data);
            var oldChart = pie.down('polar');
            pie.remove(oldChart);
            //oldChart.destroy();
            chart.store = grid.store;
            chart = pie.insert(0, chart);
            grid.columns[0].setText(titleColHeader);
            grid.columns[3].setText(averageColHeader);
            chart.setVisible(!hideChart);
            pie.resumeLayouts(true);
        }
        grid.view.findFeature('summary').toggleSummaryRow(data && data.length > 0);

        viewWrapper.layout.setActiveItem(pieWrapper);
        viewWrapper.resumeLayouts(true);
    }

});

Ext.define('Scalr.ui.AnalyticsBoxesFarm', {
    extend: 'Ext.container.Container',
    alias: 'widget.analyticsboxesfarms',

    layout: {
        type: 'hbox',
        align: 'stretch'
    },

    loadData: function(mode, quarter, startDate, endDate, data) {
        var me = this,
            costDistribution = [],
            totals;

        totals = data['totals'];

        me.suspendLayouts();
        me.down('#col1').update({
            cost: totals['cost'],
            growth: totals['growth'],
            growthPct: totals['growthPct']
        });

        Ext.Object.each(Scalr.constants.distributionTypes, function(id, name){
            var cost = 0,
                costPct = 0;
            Ext.Array.each(totals['distributionTypes'], function(item){
                if (item.id == id) {
                    cost = item.cost;
                    costPct = item.costPct;
                    return false;
                }
            });
            costDistribution.push({
                id: id,
                name: name,
                cost: cost,
                costPct: costPct
            });
        });
        me.down('#col2').update(costDistribution);

        me.down('#col3').setVisible(!!totals['trends']).update({
            cost: totals['cost'],
            trends: totals['trends'],
            interval: data['interval']
        });
        me.down('#col4').update({
            forecastCost: totals['forecastCost'],
            period: mode === 'custom' ? 'period' : mode
        });



        me.resumeLayouts(true);
    },
    defaults: {
        height: 100,
        style: 'text-align:left'
    },
    items: [{
        xtype: 'component',
        cls: 'x-cabox x-cabox-transparent',
        itemId: 'col1',
        flex: 1,
        minWidth: 160,
        maxWidth: 280,
        tpl:
            '<div class="x-cabox-title">Farm total spend</div>'+
            '<div style="margin: 8px 0 0">' +
                '<span class="title1">{[this.currency2(values.cost, true)]}</span>' +
                '<tpl if="growth!=0">' +
                    ' &nbsp;{[this.pctLabel(values.growth, values.growthPct, false, false, false, false)]}' +
                '</tpl>'+
            '</div>'
    },{
        xtype: 'component',
        cls: 'x-cabox x-cabox-transparent',
        itemId: 'col2',
        minWidth: 300,
        flex: 1.6,
        tpl:
            '<div class="x-cabox-title">Farm cost distribution</div>' +
            '<div style="max-width:400px">'+
                '<table style="width:90%;border-collapse:collapse;margin:0 0 6px 0"><tr>' +
                    '<tpl for=".">' +
                        '<td class="x-costanalytics-bg-{name}" style="min-width:3px;height:16px;padding:0;width:{[values.costPct]}%;" data-qtip="{[Ext.String.htmlEncode(this.itemCost({name:Ext.String.capitalize(values.name),cost:values.cost,costPct:values.costPct,cls:\'x-costanalytics-\'+values.name}, false))]}" data-qclass="x-tip-light"></td>' +
                    '</tpl>'+
                '</tr></table>' +
                '<table style="width:90%;border-collapse:collapse;margin:0 0 12px 0;white-space:nowrap"><tr>' +
                    '<td style="width:25%"><span><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-bg-compute" style="width:12px;height:12px;border-radius:6px" />&nbsp;Compute</span></td>'+
                    '<td style="width:25%"><span><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-bg-storage" style="width:12px;height:12px;border-radius:6px" />&nbsp;Storage</span></td>'+
                    '<td style="width:30%"><span><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-bg-bandwidth" style="width:12px;height:12px;border-radius:6px" />&nbsp;Bandwidth</span></td>'+
                    '<td style="width:20%"><span><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-costanalytics-bg-other" style="width:12px;height:12px;border-radius:6px" />&nbsp;Other</span></td>'+
                '</tr></table>'+
                '</div>'
    },{
        xtype: 'component',
        cls: 'x-cabox x-cabox-transparent',
        itemId: 'col3',
        flex: 1,
        tpl:
            '<div class="x-cabox-title">{trends.rollingAverageMessage}</div>'+
            '<tpl if="trends">' +
                '<span class="title1">{[this.currency2(values.trends.rollingAverage, true)]}</span> per {interval}' +
            '</tpl>'
    },{
        xtype: 'component',
        cls: 'x-cabox x-cabox-transparent',
        itemId: 'col4',
        flex: 1,
        tpl:
            '<div class="x-cabox-title">{period:capitalize} end estimate</div>'+
            '<tpl if="forecastCost!==null">' +
                '<span class="title1">~ {[this.currency2(values.forecastCost, true)]}</span>' +
            '</tpl>'
    }]
});

Ext.define('Scalr.ui.AnalyticsBoxesEnvProject', {
    extend: 'Scalr.ui.AnalyticsBoxes',
    alias: 'widget.analyticsboxesenvproject',

    loadData: function(mode, quarter, startDate, endDate, data) {
        var me = this,
            totals,
            costDistribution = [];

        me.callParent(arguments);

        totals = me.data['totals'];

        //total
        me.down('#total').update({
            title: me.title,
            cost: totals['cost'],
            forecastCost: totals['forecastCost'],
            growth: totals['growth'],
            growthPct: totals['growthPct'],
            trends: totals['trends'],
            interval: data['interval'],
            period: mode === 'custom' ? 'period' : mode
        });

        Ext.Object.each(Scalr.constants.distributionTypes, function(id, name){
            var cost = null,
                costPct = null;
            Ext.Array.each(totals['distributionTypes'], function(item){
                if (item.id == id) {
                    cost = item.cost;
                    costPct = item.costPct;
                    return false;
                }
            });
            costDistribution.push({
                id: id,
                name: name,
                cost: cost,
                costPct: costPct,
            });
        });
        me.down('#distribution').loadData(costDistribution);

        //budget
        var budgetCt = this.down('#budget'),
            noBudgetCt = this.down('#noBudget');
        if (totals['budget']['budget'] != 0) {
            budgetCt.show();
            noBudgetCt.hide();
            budgetCt.down('#title').update({
                year: totals['budget']['year'],
                quarter: totals['budget']['quarter'],
                total: totals['budget']['budget'],
                ccId: data['ccId'],
                projectId: data['projectId']
            });
            budgetCt.down('#budgetSpentPct').update({
                value: totals['budget']['budgetSpentPct'],
                currentPeriodSpend: mode === 'month' ? '(' + totals['budget']['budgetSpentThisPeriodPct'] + '% used in ' + Ext.Date.format(startDate, 'F Y') + ')' : ''
            });
            budgetCt.down('polar').store.loadData([[totals['budget']['budgetSpentPct']]]);
            budgetCt.down('#budgetRemain').update(totals['budget']);
            this.down('#budgetAlert').setVisible(!!totals['budget']['budgetAlert']).update({
                ccId: data['ccId'],
                projectId: data['projectId'],
                alert: totals['budget']['budgetAlert']
            });
        } else {
            noBudgetCt.down('#title').update({
                year: totals['budget']['year'],
                quarter: totals['budget']['quarter'],
                text: totals['budget']['closed'] ? 'Budget hasn\'t been set' : 'No budget allocated',
                ccId: data['ccId'],
                projectId: data['projectId']
            });
            noBudgetCt.down('#finalSpent').setVisible(!!totals['budget']['closed']).update({budgetFinalSpent: totals['budget']['budgetFinalSpent']});
            var btn = noBudgetCt.down('#button');
            btn.setVisible(!totals['budget']['closed']);
            if (Scalr.isAllowed('ANALYTICS_ACCOUNT', 'allocate-budget')) {
                btn.enable().setTooltip('');
            } else {
                btn.disable().setTooltip('Insufficient permissions to define a budget');
            }
            budgetCt.hide();
            noBudgetCt.show();
        }

    },
    defaults: {
        minHeight: 240
    },
    items: [{
        xtype: 'component',
        cls: 'x-cabox',
        itemId: 'total',
        flex: 1,
        minWidth: 160,
        maxWidth: 500,
        tpl:
            '<div class="x-cabox-title">{title}</div>'+
            '<div style="margin:16px 0 0"><span class="x-form-item-label-default">Spent</span>' +
                '<div>' +
                    '<span class="title1">{[this.currency2(values.cost, true)]}</span>' +
                    '<tpl if="growth!=0">' +
                        ' &nbsp;{[this.pctLabel(values.growth, values.growthPct, false, false, false, false)]}' +
                    '</tpl>'+
                '</div>' +
            '</div>'+
            '<tpl if="trends">' +
                '<div style="margin:20px 0 0"><span class="x-form-item-label-default">{trends.rollingAverageMessage}</span>' +
                    '<div style="margin: 0 0 8px"><span class="title1">{[this.currency2(values.trends.rollingAverage, true)]}</span> per {interval}</div>' +
                '</div>' +
            '</tpl>' +
            '<tpl if="forecastCost!==null">' +
                '<div style="margin:20px 0 0" class="x-form-item-label-default">{period:capitalize} end estimate</div>' +
                '<span class="title2" style="padding-right:1em">~ {[this.currency2(values.forecastCost, true)]}</span>' +
            '</tpl>'
    },{
        xtype: 'dataview',
        cls: 'x-cabox',
        itemId: 'distribution',
        flex: 1,
        itemSelector: '.x-top5-item',
        emptyText: 'No data available',
        loadData: function(data) {
            var maxCost,
                rows = [];
            Ext.each(data, function(row){
                row = Ext.clone(row);
                rows.push(row);
                row['pctOfMax'] = 0;
                if (row['cost'] !== null) {
                    maxCost = maxCost > row['cost'] ? maxCost : row['cost'];
                }
            });
            if (maxCost != 0) {
                Ext.each(rows, function(row){
                    row['pctOfMax'] = row['cost'] !== null ? row['cost']/maxCost*100 : 0;
                });
            }
            this.store.loadData(rows);
        },
        store: {
            proxy: 'object',
            sorters: {
                property: 'cost',
                direction: 'DESC'
            },
            fields: ['cost', 'costPct', 'pctOfMax', 'name']
        },
        tpl: new Ext.XTemplate(
            '<div class="x-cabox-title">Cost distribution</div>'+
            '<table class="x-cabox-distribution">' +
                '<tpl for=".">' +
                    '<tr class="x-top5-item">' +
                        '<td>' +
                            '<div style="max-width:250px">{name:capitalize}</div>' +
                            '<div style="margin-top:4px"><div class="bar-inner x-costanalytics-bg-{name}" style="margin:0;width:{pctOfMax}%">'+
                                '<span>{[values.cost === null ? \'N/A\' : this.currency2(values.cost)]}</span>'+
                            '</div></div>' +
                        '</td>'+
                        '<td style="text-align:center;width:75px;"><div style="margin-bottom:4px"><tpl if="xindex==1"><b>% of total</b></tpl>&nbsp;</div>' +
                            '{[values.costPct === null ? \'N/A\' : values.costPct + \'%\']}'+
                        '</td>'+
                    '</tr>' +
                '</tpl>'+
            '</table>'
        )
    },{
        xtype: 'container',
        cls: 'x-cabox',
        itemId: 'budget',
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        flex: 1.2,
        items: [{
            xtype: 'component',
            itemId: 'title',
            tpl: '<div class="x-cabox-title"><span style="float:left">{[Ext.isNumeric(values.quarter)?\'Q\':\'\']}{quarter} {year} budget</span><span style="float:right;" class="title2">{[this.currency(values.total)]}</span></div>'
        },{
            xtype: 'container',
            flex: 1,
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'container',
                flex: 1,
                items: [{
                    xtype: 'polar',
                    theme: 'scalr',
                    height: 110,
                    store: Ext.create('Ext.data.ArrayStore', {
                        fields: ['value']
                    }),
                    axes: [{
                        type: 'numeric',
                        position: 'gauge',
                        minimum: 0,
                        maximum: 100,
                        hidden: true
                    }],
                    series: [{
                        type: 'gauge',
                        field: 'value',
                        donut: 70,
                        totalAngle: Math.PI,
                        needleLength: 100,
                        renderer: function(sprite, config, data, index){
                            var record = data.store.getData().items[index];
                            if (record) {
                                var value = record.get('value'),
                                    color;
                                if (value >= 95) {
                                    color = '#de1810';
                                } else if(value >= 75) {
                                    color = '#ffae39';
                                } else {
                                    color = '#319608';
                                }
                            } else {
                                color = '#f0f1f4';
                            }
                            return {
                                fillStyle: color
                            };
                        },
                    }]
                },{
                    xtype: 'component',
                    itemId: 'budgetSpentPct',
                    tpl: '<div><span class="title1" style="font-size:17px">{value}%</span> used</div><p>{currentPeriodSpend}</p>',
                    margin: '-36 0 0 0'
                }]
            },{
                xtype: 'component',
                flex: 1,
                itemId: 'budgetRemain',
                tpl: new Ext.XTemplate(
                     '<tpl if="closed">' +
                        '<div style="margin: 16px 0 0"><span class="x-form-item-label-default">Final spend</span><div class="title1 x-costanalytics-{[this.getColorCls(values)]}" style="margin: 4px 0 8px">{[this.currency(values.budgetFinalSpent)]}</div></div>' +
                        '<div style="padding:10px 0 0"><span class="x-form-item-label-default">Cost variance</span><div class="title2 x-costanalytics-{[values.costVariance>0?\'red\':\'green\']}" data-qtip="{costVariancePct}%">{[values.costVariance>0?\'+\':\'\']}{costVariance:currency}</div></div>'+
                        '<div style="padding:12px 0 0"><span class="x-form-item-label-default">Exceeded on</span><tpl if="estimateDate"><div class="title2 x-costanalytics-red" style="margin: 4px 0 8px">{estimateDate:date(\'M j Y\')}</div><tpl else><div class="title2">&ndash;</div></tpl></div>'+
                     '<tpl else>'+
                        '<div style="margin: 16px 0 0"><span class="x-form-item-label-default">Remaining</span><div class="title1 x-costanalytics-{[this.getColorCls(values)]}" style="margin: 4px 0 8px">{[this.currency(values.budgetRemain)]}</div></div>' +
                        '<div style="padding:10px 0 0"><span class="x-form-item-label-default">Overspend estimate</span><div class="title2<tpl if="estimateOverspend&gt;0"> x-costanalytics-red</tpl>" <tpl if="estimateOverspendPct&gt;0">data-qtip="{estimateOverspendPct}% of budget"</tpl> style="margin: 4px 0 0">~{[this.currency(values.estimateOverspend)]}</div></div>'+
                        '<div style="padding:12px 0 0"><span class="x-form-item-label-default">Exceed{[values.budgetRemain>0?\'\':\'ed\']} on</span><tpl if="estimateDate"><div class="title2 x-costanalytics-red" style="margin: 4px 0 8px">{estimateDate:date(\'M j Y\')}</div><tpl else><div class="title2">&ndash;</div></tpl></div>'+
                     '</tpl>',
                     {
                        getColorCls: function(values) {
                            var cls = 'green';
                            if (values.budget) {
                                if (values.budgetRemainPct < 5) {
                                    cls = 'red';
                                } else if (values.budgetRemainPct < 25) {
                                    cls = 'orange';
                                }
                            }
                            return cls;
                        }
                     }
                )
            }]
        },{
            xtype: 'component',
            itemId: 'budgetAlert',
            margin: '6 0 0',
            hidden: true,
            tpl: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-grid-icon x-grid-icon-warning"/>&nbsp;&nbsp;{alert}'
        }]
    },{
        xtype: 'container',
        cls: 'x-cabox',
        itemId: 'noBudget',
        hidden: true,
        layout: 'auto',
        flex: 1.2,
        items: [{
            xtype: 'component',
            itemId: 'title',
            tpl: '<div class="x-cabox-title"><span style="float:left">{[Ext.isNumeric(values.quarter)?\'Q\':\'\']}{quarter} {year} Budget</span><i style="float:right;">{text}</i></div>'
        },{
            xtype: 'component',
            itemId: 'finalSpent',
            margin: '56 0 0 0',
            tpl: '<span class="x-form-item-label-default">Final spend</span><div class="title1" style="margin: 4px 0 8px">{[this.currency(values.budgetFinalSpent)]}</div>'
        },{
            xtype: 'button',
            itemId: 'button',
            margin: '56 0 0 0',
            padding: '0 24',
            cls: 'x-btn-green',
            height: 52,
            text: 'Define a budget',
            disabled: true,
            handler: function(){
                var data = this.up('analyticsboxesenvproject').data;
                Scalr.event.fireEvent('redirect', '#/account/analytics/budgets?ccId=' + data['ccId'] + (data['projectId'] ? '&projectId=' + data['projectId'] : ''));
            }
        }]
    }]
});

Ext.define('Scalr.ui.MenuItemSortDir', {
    extend: 'Ext.menu.CheckItem',
    alias: 'widget.menuitemsortdir',

    defaultDir: 'asc',
    onRender: function () {
        var me = this;
        me.callParent();
        if (me.checked) {
            me.dir = me.defaultDir;
        }
        me.itemEl.createChild({
            tag: 'img',
            src: Ext.BLANK_IMAGE_URL,
            title: me.defaultDir,
            cls: 'x-costanalytics-sort-dir x-costanalytics-sort-dir-' + me.defaultDir
        }, me.arrowEl);
    },

    onClick: function() {
        var me = this,
            dir,
            el = Ext.get(me.itemEl.query('.x-costanalytics-sort-dir')[0]);
        if (me.dir) {
            dir = me.dir === 'asc' ? 'desc' : 'asc';
        } else {
            dir = me.defaultDir;
        }
        if (el) {
            el.set({title: dir});
            el.addCls('x-costanalytics-sort-dir-' + dir);
            el.removeCls('x-costanalytics-sort-dir-' + (dir === 'asc' ? 'desc' : 'asc'));
        }
        me.sortHandler(dir);
        me.dir = dir;
        this.callParent(arguments);
    }

});
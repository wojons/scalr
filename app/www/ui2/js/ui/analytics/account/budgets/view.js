Scalr.regPage('Scalr.ui.analytics.account.budgets.view', function (loadParams, moduleParams) {
    Scalr.utils.Quarters.days = moduleParams['quarters'];

    refreshCurDatePointer = function() {
        //we have to compensate treepanel scrollbar width for headerCurDate
        var padding = 0;
        if (treepanel.view.el.isScrollable()) {
           padding = Ext.getScrollbarSize().width;
        }
        treepanel.down('#header').el.setStyle('padding-right', padding + 'px');
    },
    getDateOffset = function(startDate, endDate, curDate) {
        var oneDay = 24*60*60*1000,
            daysInQuarter = Math.round(Math.abs((endDate.getTime() - startDate.getTime())/(oneDay))),
            dateQuarterDays = Math.round(Math.abs((curDate.getTime() - startDate.getTime())/(oneDay)));

        return dateQuarterDays*100/daysInQuarter;
    };
	var reconfigurePage = function(params) {
        var ccId = params.ccId,
            projectId = params.projectId;

        /*if (moduleParams['quartersConfirmed'] != 1) {
            Scalr.event.fireEvent('redirect', '#/analytics/budgets/quarterCalendar', true);
        }*/

		if (ccId) {
            cb = function() {
                cb1 = function() {
                    var selModel = treepanel.getSelectionModel();
                    selModel.deselectAll();
                    selModel.setLastFocused(null);
                    if (!projectId) {
                        selModel.setLastFocused(store.getRootNode().findChild('id', ccId));
                    } else {
                        var node = store.getRootNode().findChild('id', ccId);
                        if (node) {
                            node.expand();
                            if (node) {
                                selModel.setLastFocused(node.findChild('id', projectId));
                            }
                        }
                    }
                };

                if (store.isLoading()) {
                    store.on('load', cb1, store, {single: true});
                } else {
                    cb1();
                }
            };
            if (panel.isVisible()) {
                cb();
            } else {
                panel.on('activate', cb, panel, {single: true});
            }
		}
	};

	var store = Ext.create('Ext.data.TreeStore', {
        nodeParam: 'ccId',
		fields: [
			'ccId',
            'projectId',
            'name',
            'billingCode',
			'budget',
            'budgetSpent',
            'budgetSpentPct',
            'budgetRemain',
            'budgetRemainPct',
            'budgetOverspend',
            'budgetOverspendPct',
            'children',
            'relationDependentBudget',
            {name: 'id', convert: function(v, record){;return record.data.projectId || record.data.ccId;}}
		],
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}],
		proxy: {
			type: 'ajax',
			url: '/analytics/account/budgets/xList',
            reader: {
                type: 'json',
                root: 'nodes'
            }
		},
        updateParamsAndLoad: function(params, reset) {
            if (reset) {
                this.proxy.extraParams = {};
            }
            var proxyParams = this.proxy.extraParams;
            Ext.Object.each(params, function(name, value) {
                if (value === undefined) {
                    delete proxyParams[name];
                } else {
                    proxyParams[name] = value;
                }
            });
            this.load();
            treepanel.el.mask('Loading...');
        }
	});

    var treepanel = Ext.widget({
        xtype: 'treepanel',
        cls: 'x-panel-column-left x-budgets-grid',
        rootVisible: false,
        store: store,
        minWidth: 600,
        flex: .9,
        hideHeaders: true,
        viewConfig: {
            emptyText: 'No cost centers found'
        },
        plugins: {
            ptype: 'focusedrowpointer',
            thresholdOffset: 66,
            addOffset: 20
        },
        mask: function() {
            this.el.mask('Loading...');
        },
        unmask: function() {
            this.el.unmask();
        },
        listeners: {
            resize: refreshCurDatePointer,
            itemexpand: refreshCurDatePointer,
            itemcollapse: refreshCurDatePointer,
            beforeload: function(store, operation) {
                if (operation.node.isRoot()) {
                    var selModel = this.getSelectionModel();
                    selModel.deselectAll();
                    selModel.setLastFocused(null);
                }
                if (this.el) {
                    this.mask();
                } else {
                    this.on('boxready', this.mask, this, {single: true});
                }
            },
            load: function(store) {
                var data = store.proxy.reader.jsonData;
                if (data['success']) {
                    var startDate = Scalr.utils.Quarters.getDate(data['startDate']),
                        endDate = Scalr.utils.Quarters.getDate(data['endDate']),
                        curDate = Scalr.utils.Quarters.getDate(),
                        dates;

                    if (data['quarter'] === 'year') {
                        dates = ' <span style="font-size:13px">(' + Ext.Date.format(startDate, 'M j, Y') + ' &ndash; ' + Ext.Date.format(endDate, 'M j, Y') + ')</span>';
                    } else {
                        dates = ' <span style="font-size:13px">(' + Ext.Date.format(startDate, 'M j') + ' &ndash; ' + Ext.Date.format(endDate, 'M j') + ')</span>';
                    }
                    this.down('#header').setVisible(!Ext.isEmpty(store.getRootNode().childNodes));
                    this.down('#headerDates').update((data['quarter'] === 'year' ? 'Q1 - Q4' : ('Q' + data['quarter'])) + dates);
                    this.down('#headerCurDate').update(store.proxy.extraParams['quarter'] !== 'year' && Ext.Date.between(curDate, startDate, endDate) ? {
                        date: Ext.Date.format(curDate, 'M j'),
                        offset: getDateOffset(startDate, endDate, curDate)
                    } : '');
                    if (this.el) {
                        this.unmask();
                    } else {
                        this.un('boxready', this.mask);
                    }
                } else {
                    Scalr.message.Error(store.proxy.reader.jsonData['errorMessage']);
                }
            }
        },
        columns: [{
            xtype: 'treecolumn',
            flex: 1,
            maxWidth: 200,
            dataIndex: 'name',
            renderer: function(value, m, record) {
                return '<span style="line-height:17px;font-weight:bold">' + value + '</span><br/><span style="color:#666666;font-size:10px;margin-left:48px;">'+record.get('billingCode') + '</span>'
            }
            
        },{
            xtype: 'templatecolumn',
            flex: 1,
            tpl: new Ext.XTemplate(
                    '<tpl if="budget!=0">'+
                        '<div class="bar-wrapper" style="margin:0 6px 0 12px" data-qtip="{[this.getOverspendTooltip(values)]}">'+
                            '<div class="bar-inner x-costanalytics-bg-{[this.getBarCls(values)]}" style="width:{[100-values.budgetRemainPct]}%">'+
                                '<span>{[this.currency(values.budgetSpent)]}</span>'+
                            '</div>'+
                        '</div>'+
                    '<tpl else>'+
                        '<i>Budget is not set</i>'+
                    '</tpl>'
                ,{
                    getOverspendTooltip: function(values) {
                        if (values.budgetOverspend) {
                            return Ext.String.htmlEncode('Overspend: ' + Ext.util.Format.currency(values.budgetOverspend, null, 0) + ' (' + values.budgetOverspendPct + '%)');
                        } else if (values.budgetRemain) {
                            return Ext.String.htmlEncode('Remaining: ' + Ext.util.Format.currency(values.budgetRemain, null, 0) + ' (' + values.budgetRemainPct + '%)');
                        }
                    },
                    getBarCls: function(values) {
                        var cls = 'green';
                        if (values.budgetRemainPct < 5) {
                            cls = 'red';
                        } else if (values.budgetRemainPct < 25) {
                            cls = 'orange';
                        }
                        return cls;
                    }
                }
            )
        },{
            xtype: 'templatecolumn',
            dataIndex: 'budget',
            width: 22,
            tdCls: 'x-grid-cell-budget-warning',
            tpl:
                '<tpl if="budget!=0">' +
                    '<tpl if="!projectId&&relationDependentBudget&gt;budget">' +
                        '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-warning" data-qtip="You have allocated {[this.currency(values.relationDependentBudget-values.budget)]} more to projects than your total cost center budget"/>' +
                    '</tpl>' +
                '</tpl>'
        },{
            xtype: 'templatecolumn',
            dataIndex: 'budget',
            tpl: '{[values.budget !=0 ? this.currency(values.budget) : \'\']}'
        }],
        dockedItems: [{
            xtype: 'analyticsrecources',
            dock: 'top',
            value: 'budgets',
            maxWidth: 266
        },{
            xtype: 'toolbar',
            dock: 'top',
            layout: 'hbox',
            style: 'box-shadow:none',
            defaults: {
                margin: '0 0 0 20'
            },
            items: [{
                xtype: 'filterfield',
                margin: 0,
                flex: 1,
                maxWidth: 240,
                store: store,
                forceRemoteSearch: true,
                menu: {
                    xtype: 'menu',
                    minWidth: 220,
                    defaults: {
                        xtype: 'menuitemsortdir'
                    },
                    items: [{
                        text: 'Order by name',
                        group: 'budgets-sort',
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
                        text: 'Order by budget consumed',
                        checked: false,
                        group: 'budgets-sort',
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
                    }]
                }

            },{
                xtype: 'button',
                itemId: 'predefinedPrev',
                cls: 'x-costanalytics-icon-arrow x-costanalytics-icon-arrow-left',
                width: 29,
                margin: '0 6 0 8',
                handler: function() {
                    var yearField = this.next();
                    yearField.setValue(yearField.getValue()*1-1);
                }
            },{
                xtype: 'textfield',
                itemId: 'yearInput',
                fieldStyle: 'text-align:center',
                width: 60,
                value: (Scalr.utils.Quarters.getDate()).getFullYear(),
                readOnly: true,
                readOnlyCls: '',
                margin: 0,
                listeners: {
                    change: function(field, value){
                        var curYear = (Scalr.utils.Quarters.getDate()).getFullYear();
                        this.next().setDisabled(curYear+1 == value);
                        this.prev().setDisabled(curYear-4 == value);
                        store.updateParamsAndLoad({year: value});
                    }
                }
            },{
                xtype: 'button',
                itemId: 'predefinedNext',
                cls: 'x-costanalytics-icon-arrow x-costanalytics-icon-arrow-right',
                width: 29,
                margin: '0 0 0 6',
                handler: function() {
                    var yearField = this.prev();
                    yearField.setValue(yearField.getValue()*1+1);
                }
            },{
                xtype: 'buttongroupfield',
                itemId: 'quarter',
                value: Scalr.utils.Quarters.getQuarterForDate(),
                defaults: {
                    width: 40
                },
                items: [{
                    text: 'Q1',
                    value: 1
                },{
                    text: 'Q2',
                    value: 2
                },{
                    text: 'Q3',
                    value: 3
                },{
                    text: 'Q4',
                    value: 4
                },{
                    text: 'Year',
                    value: 'year',
                    width: 54
                }],
                listeners: {
                    change: function(comp, value) {
                        store.updateParamsAndLoad({quarter: value});
                    }
                }
            },{
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                tooltip: 'Refresh',
                handler: function() {
                    this.up('treepanel').store.updateParamsAndLoad();
                },
                margin: '0 0 0 6'
            }]
        },{
            xtype: 'container',
            itemId: 'header',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            dock: 'top',
            padding: '20 0 4',
            items: [{
                xtype: 'component',
                itemId: 'headerDates',
                cls: 'x-caheader',
                html: '&nbsp;',
                flex: 1,
                maxWidth: 200,
                padding: '0 20 0 0',
                style: 'white-space:nowrap'
            },{
                xtype: 'component',
                itemId: 'headerCurDate',
                flex: 1,
                margin: '0 10 0 12',
                height: 28,
                tpl: '<div class="x-budget-date" style="width:{offset}%"><span>{date}</span></div>'
            },{
                xtype: 'component',
                width: 22,
            },{
                xtype: 'component',
                minWidth: 106,
                html: '<div style="font-weight:bold;font-size:13px;line-height:20px">Budget</div>'
            }]
        }]


    });

    var form = Ext.create('Ext.form.Panel', {
        hidden: true,
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        autoScroll: true,
        listeners: {
            afterrender: function() {
                var me = this;
                treepanel.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                    if (newFocused) {
                        if (me.getRecord() !== newFocused) {
                            me.loadBudgetInfo(newFocused);
                        }
                    } else {
                        me.setVisible(false);
                        me.getForm().reset(true);
                    }
                });
            },
            beforedestroy: function() {
                this.abortCurrentRequest();
            }
        },
        abortCurrentRequest: function() {
            if (this.currentRequest) {
                Ext.Ajax.abort(this.currentRequest);
                delete this.currentRequest;
            }
        },
        loadBudgetInfo: function(record) {
            var me = this;
            me.abortCurrentRequest();
            me.up().mask('Loading...');
            me.hide();
            me.getForm()._record = record;
            me.currentRequest = Scalr.Request({
                url: '/analytics/account/budgets/xGetBudgetInfo',
                params: {
                    ccId: record.get('ccId'),
                    projectId: record.get('projectId'),
                    year: treepanel.down('#yearInput').getValue()
                },
                success: function (data) {
                    var rec = me.getRecord();
                    delete me.currentRequest;
                    if (data['ccId'] == rec.get('ccId') && data['projectId'] == rec.get('projectId')) {
                        me.showBudgetInfo(data);
                    }
                },
                failure: function() {
                    me.up().unmask();
                }
            });
        },
        showBudgetInfo: function(data) {
            var record = this.getRecord(),
                budgets = data['budgets'],
                budgetCt = this.down('#budgets'),
                isBudgetEditable = Scalr.utils.isAdmin() || (data.projectId && data.shared == 2),
                allQuartersClosed = true,
                budgetItems = [];
            this.down('#headerName').update(record.get('name'));
            this.down('#headerDate').update(data['year'] + ' Budget' );
            this.getForm().findField('year').setValue(data['year']);
            Ext.each(budgets, function(budget, index){
                var isCurrentQuarter = index+1 === data['quarter'] && !budget['closed'],
                    startDate = Scalr.utils.Quarters.getDate(data['year'] + '-' + budget['startDate']),
                    endDate =  Ext.Date.add(Scalr.utils.Quarters.getDate(data['year'] + '-' + budgets[index < 3 ? index + 1 : 0]['startDate']), Ext.Date.DAY, -1),
                    avgTooltip = '';
                if (budget['closed']) {
                    avgTooltip = Ext.String.htmlEncode(
                        'Monthly average: ' + Ext.util.Format.currency(budget['monthlyAverage'], null, 0) + ' per month<br/>' +
                        'Daily average: ' + Ext.util.Format.currency(budget['dailyAverage'], null, 0) + ' per day'
                    );
                } else {
                    allQuartersClosed = false;
                }
                budgetItems.push({
                    xtype: 'container',
                    maxWidth: 950,
                    margin: index === 3 ? 0 : '0 0 32 0',
                    cls: 'x-cabox2',
                    items: [{
                        xtype: 'container',
                        layout: 'hbox',
                        margin: '0 0 6 0',
                        items: [{
                            xtype: 'component',
                            cls: 'x-caheader',
                            html: 'Q' + (index+1) + ' <span style="font-size:13px">('+Ext.Date.format(startDate, 'M j')+' - ' + Ext.Date.format(endDate, 'M j') + ')</span>'
                        },{xtype: 'tbfill'},{
                            xtype: 'component',
                            hidden: !isCurrentQuarter,
                            margin: '4 0 0',
                            html: 'Current spend: &nbsp;<span style="font-size:13px;font-weight:bold;">' + Ext.util.Format.currency(budget['budgetSpent'], null, 0) + '</span>'
                        }]
                    },{
                        xtype: 'container',
                        cls: 'x-quarter-box' + (isCurrentQuarter ? ' current' : ''),
                        layout: {
                            type: 'hbox',
                            align: 'stretch'
                        },
                        defaults: {
                            flex: 1,
                            padding: '24 2 18',
                            xtype: 'container',
                            cls: 'separator-right',
                            layout: 'anchor',
                            defaults: {
                                anchor: '100%'
                            }
                        },
                        items: [{
                            items: [{
                                xtype: 'component',
                                html: 'Budget'
                            },budget['closed'] || !isBudgetEditable ? {
                                xtype: 'component',
                                cls: 'x-caheader',
                                margin: '10 0 0',
                                html: budget['budget'] !== null ? Ext.util.Format.currency(budget['budget'], null, 0) : '&ndash;'
                            } : {
                                xtype: 'textfield',
                                name: 'budget' + (index + 1),
                                fieldLabel: '$',
                                labelWidth: 12,
                                labelSeparator: '',
                                fieldStyle: 'text-align:center',
                                value: budget['budget'] > 0 ? budget['budget'] : '',
                                maskRe: /[0-9]/,
                                margin: '10 8 0 8'
                            }]
                        },{
                            items: [{
                                xtype: 'component',
                                html: 'Final spend'
                            },{
                                xtype: 'component',
                                cls: 'x-caheader',
                                margin: '10 0 4',
                                html: index+1 > data['quarter'] && !budget['closed'] ? '&ndash;' : '<span data-qtip="' + avgTooltip + '">' + (budget['closed'] ? '' : '~&nbsp;') + Ext.util.Format.currency(budget['closed'] ? budget['budgetFinalSpent'] : budget['projection'], null, 0) + '</span>'
                            },{
                                xtype: 'component',
                                hidden: index+1 > data['quarter'] || budget['closed'],
                                html: '(Estimate)'
                            }]
                        },{
                            items: [{
                                xtype: 'component',
                                html: 'Cost variance'
                            },{
                                xtype: 'component',
                                cls: 'x-caheader',
                                margin: '10 0 4',
                                html: index+1 > data['quarter'] && !budget['closed'] ? '&ndash;' : '<span class="x-costanalytics-'+(budget['costVariance']>0?'red':'green')+'" data-qtip="'+(budget['costVariancePct'] !== null ? budget['costVariancePct']+'%' : '') + '">'+ (budget['closed'] ? '' : '~&nbsp;') + (budget['costVariance']>0?'+':'') + Ext.util.Format.currency(budget['costVariance'], null, 0) + '</span>'
                            },{
                                xtype: 'component',
                                hidden: index+1 > data['quarter'] || budget['closed'],
                                html: '(Estimate)'
                            }]
                        },{
                            padding: 0,
                            cls: '',
                            anchor: 'vbox',
                            flex: 2,
                            items: [{
                                xtype: 'component',
                                cls: 'separator-bottom',
                                style: 'line-height:28px;font-weight:bold;font-size:13px;color:#8A919E',
                                html: 'Q'  + (index+1) + ' ' + (data['year'] - 1)
                            },{
                                xtype: 'container',
                                layout: 'hbox',
                                items: [{
                                    xtype: 'container',
                                    flex: 1,
                                    layout: 'anchor',
                                    items: [{
                                        xtype: 'component',
                                        margin: '10 0 0',
                                        style: 'font-size:13px;color:#56637E',
                                        html: 'Budget'
                                    },{
                                        xtype: 'component',
                                        cls: 'title3',
                                        margin: '6 0 0',
                                        style: 'font-size:13px;color:#8A919E',
                                        html: budget['prev']['budget'] !== null ? Ext.util.Format.currency(budget['prev']['budget'], null, 0) : '&ndash;'
                                    }]
                                },{
                                    xtype: 'container',
                                    flex: 1,
                                    layout: 'anchor',
                                    items: [{
                                        xtype: 'component',
                                        margin: '10 0 0',
                                        style: 'font-size:13px;color:#56637E',
                                        html: 'Final'
                                    },{
                                        xtype: 'component',
                                        cls: 'title3',
                                        margin: '6 0 0',
                                        style: 'font-size:13px;color:#8A919E',
                                        html: budget['prev']['closed'] ? Ext.util.Format.currency(budget['prev']['budgetFinalSpent'], null, 0) : '&ndash;'
                                    }]
                                },{
                                    xtype: 'container',
                                    flex: 1,
                                    layout: 'anchor',
                                    items: [{
                                        xtype: 'component',
                                        margin: '10 0 0',
                                        style: 'font-size:13px;color:#56637E',
                                        html: 'Cost variance'
                                    },{
                                        xtype: 'component',
                                        cls: 'title3',
                                        margin: '6 0 0',
                                        style: 'font-size:13px;opacity:.7',
                                        html: budget['prev']['closed'] ? '<span class="x-costanalytics-'+(budget['prev']['costVariance']>0?'red':'green')+'" data-qtip="'+budget['prev']['costVariancePct']+'%">' + (budget['prev']['costVariance']>0?'+':'') + Ext.util.Format.currency(budget['prev']['costVariance'], null, 0) + '</span>' : '&ndash;'
                                    }]
                                }]
                            }]
                        }]
                    }]
                });
            });
            budgetCt.removeAll();
            budgetCt.add(budgetItems);
            var buttons = this.getDockedComponent('buttons');
            buttons.down('#save').setDisabled(!isBudgetEditable);
            buttons.down('#save').setTooltip(isBudgetEditable ? '' : 'You are not allowed to set budget');
            buttons.setVisible(!allQuartersClosed);
            this.up().unmask();
            this.show();
        },
        items: [{
            xtype: 'container',
            layout: 'hbox',
            cls: 'x-caheader',
            padding: '0 32',
            style: 'line-height:56px;border-bottom:1px solid #e7ebef',
            items: [{
                xtype: 'component',
                itemId: 'headerName'
            },{
                xtype: 'tbfill'
            },{
                xtype: 'component',
                itemId: 'headerDate'
            }]
        },{
            xtype: 'hidden',
            name: 'year'
        },{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            itemId: 'budgets',
            style: 'padding-top:16px'
        }],
        dockedItems: [{
            xtype: 'container',
            maxWidth: 780,
            dock: 'bottom',
            cls: 'x-docked-buttons',
            itemId: 'buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults:{
                //flex: 1,
                //maxWidth: 150
            },
            items: [{
                itemId: 'save',
                xtype: 'button',
                text: 'Save',
                handler: function() {
                    var frm = form.getForm(),
                        record = frm.getRecord(),
                        values = frm.getValues(),
                        quarters = [];
                    for (var i=1; i<5; i++) {
                        if (values['budget' + i] !== undefined) {
                            quarters.push({
                                quarter: i,
                                budget: values['budget' + i]
                            });
                        }
                    }
                    Scalr.Request({
                        processBox: {
                            type: 'save'
                        },
                        params: {
                            quarters: Ext.encode(quarters),
                            ccId: record.get('ccId'),
                            projectId: record.get('projectId'),
                            year: values['year'],
                            selectedQuarter: treepanel.down('#quarter').getValue()
                        },
                        url: '/analytics/account/budgets/xSave/',
                        success: function (data) {
                            Scalr.event.fireEvent('update', '/analytics/account/budgets/edit');
                            record.set(data.data);
                            if (record.get('projectId') && record.parentNode) {
                                record.parentNode.set('relationDependentBudget', data.data['relationDependentBudget']);
                            }
                            form.showBudgetInfo(data.budgetInfo);
                        }
                    });

                }
            },{
                itemId: 'cancel',
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    var selModel = treepanel.getSelectionModel();
                    selModel.deselectAll();
                    selModel.setLastFocused(null);
                }
            }]
        }]
    });


	var panel = Ext.create('Ext.panel.Panel', {
        cls: 'x-costanalytics',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			title: 'Account management &raquo; Cost analytics &raquo; Budgets',
			//reload: false,
			maximize: 'all',
            leftMenu: {
                menuId: 'settings',
                itemId: 'resources',
                showPageTitle: true
            }
		},
        listeners: {
            applyparams: reconfigurePage
        },
		items: [
            treepanel
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            flex: 1,
            layout: 'fit',
            cls: 'x-transparent-mask',
            items: form
		}]
	});

	return panel;
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

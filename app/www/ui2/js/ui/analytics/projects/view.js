Scalr.regPage('Scalr.ui.analytics.projects.view', function (loadParams, moduleParams) {
    Scalr.utils.Quarters.days = moduleParams['quarters'];
    var requestParams, refreshStoreOnReconfigure;
	var reconfigurePage = function(params) {
        var projectId = params.projectId;
        cb = function() {
            selectProjectId = function() {
                if (projectId) {
                    dataview.getSelectionModel().deselectAll();
                    var record =  store.getById(projectId);
                    if (record) {
                        dataview.getNode(record, true, false).scrollIntoView(dataview.el);
                        dataview.select(record);
                    }
                }
            };
            if (refreshStoreOnReconfigure) {
                refreshStoreOnReconfigure = false;
                dataview.getSelectionModel().deselectAll();
                store.on('load', selectProjectId, this, {single: true});
                store.reload();
            } else {
                selectProjectId();
            }
        }
        if (panel.isVisible()) {
            cb();
        } else {
            panel.on('activate', cb, panel, {single: true});

        }

	};

    var loadPeriodData = function(ccId, projectId, mode, startDate, endDate, quarter) {
        requestParams = {
            projectId: projectId,
            mode: mode,
            startDate: Ext.Date.format(startDate, 'Y-m-d'),
            endDate: Ext.Date.format(endDate, 'Y-m-d'),
        };

        Scalr.Request({
            processBox: {
                type: 'action',
                msg: 'Computing...'
            },
            url: '/analytics/projects/xGetPeriodData',
            params: requestParams,
            success: function (data) {
                var summaryTab = panel.down('#summary');
                if (data) {
                    data['ccId'] = ccId;
                    data['projectId'] = projectId;
                    //calculate top spenders
                    data['totals']['top'] = {};
                    Ext.Array.each(['clouds', 'farms'], function(type){
                        var top6 = new Ext.util.MixedCollection();
                        top6.addAll(data['totals'][type]);
                        top6.sort('cost', 'DESC');
                        data['totals']['top'][type] = top6.getRange(0,5);
                    });

                    if (data['totals']['cost'] == 0 && !data['totals']['budget']['closed'] && data['totals']['budget']['budget'] == 0) {
                        panel.down('#tabs').layout.setActiveItem(summaryTab);
                    }
                }
                summaryTab.loadDataDeferred(mode, quarter, startDate, endDate, data);
                panel.down('costanalyticsspends').loadDataDeferred(mode, quarter, startDate, endDate, data);
            }
        });
    };
	var store = Ext.create('store.store', {
        data: moduleParams['projects'],
		fields: [
            'projectId',
			'ccId',
            'ccName',
            'name',
			'billingCode',
            'description',
            'farmsCount',
            'growth',
            'growthPct',
            {name: 'periodTotal', type: 'float'},
            //'forecastedPeriodTotal',
            'budget',
            'budgetRemainPct',
            'budgetSpentPct',
            'budgetSpent',
            'archived',
            {name: 'id', convert: function(v, record){;return record.data.projectId;}}
		],
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}],
		proxy: {
			type: 'ajax',
			url: '/analytics/projects/xList',
            reader: {
                type: 'json',
                root: 'projects'
            }
		}
	});

    var dataview = Ext.create('Ext.view.View', {
        deferInitialRefresh: false,
        store: store,
		listeners: {
            refresh: function(view){
                var record = view.getSelectionModel().getLastSelected(),
                    form = panel.down('#form');
                if (record && record !== form.currentRecord) {
                    form.loadRecord(view.store.getById(record.get('projectId')));
                }
            }
		},
        cls: 'x-dataview',
        itemCls: 'x-dataview-tab',
        selectedItemCls : 'x-dataview-tab-selected',
        overItemCls : 'x-dataview-tab-over',
        itemSelector: '.x-dataview-tab',
        overflowX: 'hidden',
        overflowY: 'auto',
        tpl  : new Ext.XTemplate(
            '<tpl for=".">',
                '<div class="x-dataview-tab{[values.archived?\' x-dataview-tab-archived\':\'\']}">',
                    '<table>',
                        '<tr>',
                            '<td>',
                                '<div class="x-fieldset-subheader" style="margin-bottom:0;width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" data-qtip="{[this.getItemTooltip(values)]}">{name} </div>',
                                '<div style="font-size:90%;color:#999;line-height:14px">{ccName}</div>',
                            '</td>',
                            '<td style="padding:0 12px 0 0;text-align:right">',
                                '<div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:70px;white-space:nowrap"><span class="x-dataview-tab-param-value" title="{billingCode:htmlEncode}">{billingCode}</span></div>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td colspan="2" style="text-align:center;padding-bottom:0">',
                                '<span class="x-dataview-tab-param-value">Spent this month</span>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td colspan="2" style="text-align:center;padding-bottom:8px">',
                                '<span class="x-dataview-tab-param-title" style="font-size:22px">{[this.currency(values.periodTotal)]}</span> &nbsp;',
                                '<tpl if="growth!=0">' +
                                    '{[this.pctLabel(values.growth, values.growthPct)]}' +
                                '</tpl>'+
                            '</td>',
                        '</tr>',
                        '<tpl if="archived">',
                            '<tr><td colspan="2" style="text-align:center;"><div class="x-fieldset-subheader" style="color:#777;margin-bottom:0">Archived</div></td></tr>',
                        '<tpl else>',
                            '<tpl if="budgetSpentPct!==null">',
                                '<tr>',
                                    '<td colspan="2" style="text-align:center">',
                                        '<span class="x-dataview-tab-param-value">Budget consumption <span class="x-dataview-tab-param-title">{budgetSpentPct}%</span></span>',
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
                getColorCls: function(values) {
                    var cls;
                    if (values.budget) {
                        if (values.budgetRemainPct < 5) {
                            cls = 'red';
                        } else if (values.budgetRemainPct < 25) {
                            cls = 'orange';
                        } else if (values.budgetSpentPct > 0) {
                            cls = 'green'
                        }
                    }
                    return cls;
                },
                getItemTooltip: function(data){
                    var html = [];
                    html.push('<b>'+data.name+'</b>');
                    html.push(' (<i>' + (data.description || 'No description for this project') + '</i>)<br/>')
                    if (data.archived) {
                        html.push('ARCHIVED<br/>');
                    }
                    html.push('Tracked farms: ' + data.farmsCount);
                    return Ext.String.htmlEncode(html.join(''));
                }
			}
        ),
		plugins: {
			ptype: 'dynemptytext',
			emptyText: '<div class="title"><br/>No projects were found<br/> to match your search.</div>Try modifying your search criteria <br/>or creating a new one.',
            arrowCls: 'x-grid-empty-arrow2',
			onAddItemClick: function() {
				panel.down('#add').handler();
			}
		},
		loadingText: 'Loading projects ...',
        deferEmptyText: false
    });


	var panel = Ext.create('Ext.panel.Panel', {
        cls: 'x-costanalytics',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			title: 'Projects',
			reload: false,
			maximize: 'all',
            leftMenu: {
                menuId: 'analytics',
                itemId: 'projects'
            }
		},
        listeners: {
            applyparams: reconfigurePage
        },
		items: [
			Ext.create('Ext.panel.Panel', {
				cls: 'x-panel-column-left',
				width: 290,
				items: dataview,
                layout: 'fit',
				dockedItems: [{
					xtype: 'toolbar',
					dock: 'top',
					defaults: {
						margin: '0 0 0 10'
					},
					items: [{
						xtype: 'filterfield',
						itemId: 'liveSearch',
                        flex: 1,
						store: store,
                        forceRemoteSearch: true,
                        margin: 0,
                        menu: {
                            xtype: 'menu',
                            minWidth: 220,
                            items: [{
                                text: 'Order by name',
                                group: 'projects-sort',
                                checked: true,
                                handler: function(){
                                    store.sort({
                                        property: 'name',
                                        transform: function(value){
                                            return value.toLowerCase();
                                        }
                                    });
                                }
                            },{
                                text: 'Order by spend',
                                checked: false,
                                group: 'projects-sort',
                                handler: function(){
                                    store.sort({
                                        property: 'periodTotal',
                                        direction: 'DESC'
                                    });
                                }
                            },{
                                text: 'Order by growth',
                                checked: false,
                                group: 'projects-sort',
                                handler: function(){
                                    store.sort({
                                        property: 'growth',
                                        direction: 'DESC'
                                    });
                                }
                            },{
                                text: 'Order by budget consumed',
                                checked: false,
                                group: 'projects-sort',
                                handler: function(){
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
                                        direction: 'DESC'
                                    });
                                }
                            },{
                                text: 'Order by parent cost center',
                                checked: false,
                                group: 'projects-sort',
                                handler: function(){
                                    store.sort({
                                        property: 'ccName'
                                    },{
                                        property: 'name'
                                    });
                                }
                            }]
                        }
					},{
						itemId: 'add',
                        text: 'New',
                        cls: 'x-btn-green-bg',
                        href: '#/analytics/projects/edit',
                        hrefTarget: '_self',
                        margin: 0
					},{
						itemId: 'refresh',
                        ui: 'paging',
						iconCls: 'x-tbar-loading',
						tooltip: 'Refresh',
						handler: function() {
                            dataview.getSelectionModel().deselectAll();
                            store.reload();
						}
					}]
				}]
			})
		,{
			xtype: 'container',
            itemId: 'formWrapper',
            flex: 1,
            //overflowX: 'auto',
            //overflowY: 'hidden',
            autoScroll: true,
            layout: 'anchor',
            //layout: 'fit',
            preserveScrollPosition: true,
			items: [{
                xtype: 'container',
                itemId: 'form',
                //layout: 'anchor',
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                defaults: {
                    anchor: '100%'
                },
                hidden: true,
                cls: 'x-container-fieldset',
                style: 'padding-top:16px;padding-bottom:0',
    			minWidth: 920,
                //maxWidth: 1200,
                listeners: {
                    afterrender: function() {
                        var me = this;
                        dataview.on('selectionchange', function(dataview, selection){
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
                    this.down('#pEdit').setHref('#/analytics/projects/edit?projectId='+record.get('id'));
                    if (periodField.getValue()) {
                        periodField.onChange();
                    } else {
                        periodField.setValue('month');
                    }
                },
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    margin: '0 0 12 0',
                    items: [{
                        xtype: 'costanalyticsperiod',
                        listeners: {
                            change: function(mode, startDate, endDate, quarter) {
                                var record = this.up('#form').currentRecord;
                                if (record) {
                                    loadPeriodData(record.get('ccId'), record.get('projectId'), mode, startDate, endDate, quarter);
                                }
                            }
                        }
                    },{
                        xtype: 'tbfill'
                    },{
                        xtype: 'button',
                        text: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-configure" />',
                        width: 50,
                        itemId: 'pEdit',
                        hrefTarget: '_self',
                        href: '#'
                    }]
                },{
                    xtype: 'tabpanel',
                    itemId: 'tabs',
                    margin: '22 0 0',
                    cls: 'x-tabs-light',
                    //flex: 1,
                    listeners: {
                        tabchange: function(panel, newtab, oldtab){
                            var comp = panel.down('costanalyticsspends');
                            if (newtab.itemId !== 'summary') {
                                newtab.add(comp);
                                comp.setType(newtab.value);
                            } else {
                                comp.setType(null);
                            }
                        }
                    },
                    items: [{
                        xtype: 'container',
                        tab: true,
                        itemId: 'summary',
                        tabConfig: {
                            title: 'Summary'
                        },
                        layout: 'anchor',
                        loadDataDeferred: function() {
                            if (this.tab.active) {
                                this.loadData.apply(this, arguments);
                            } else {
                                if (this.loadDataBind !== undefined) {
                                    this.un('activate', this.loadDataBind, this);
                                }
                                this.loadDataBind = Ext.bind(this.loadData, this, arguments);
                                this.on('activate', this.loadDataBind, this, {single: true});
                            }
                        },
                        loadData: function(mode, quarter, startDate, endDate, data) {
                            this.down('costcentersboxes').loadData(mode, quarter, startDate, endDate, data);
                            this.down('#summaryChart').loadData(data['timeline']);
                            this.down('#summaryChartTitle').update(Ext.String.capitalize(data['interval'].replace('day', 'dai')) + 'ly breakdown');
                        },
                        items: [{
                            xtype: 'costcentersboxes',
                            subject: 'projects',
                            listeners: {
                                farmclick: function() {
                                    panel.down('#tabs').setActiveTab('farms');
                                }
                            }
                        },{
                            xtype: 'component',
                            cls: 'x-caheader',
                            itemId: 'summaryChartTitle',
                            html: '&nbsp;',
                            margin: '24 20 18 24'
                        },{
                            xtype: 'costanalyticssummary',
                            anchor: '100%',
                            itemId: 'summaryChart',
                            height: 200,
                            margin: '0 20 20',
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
                                    panel.down('#summaryDetails').hide();
                                },
                                itemclick: function(item) {
                                    panel.down('#summaryDetails').loadData(item.storeItem);
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
                                    me.down('#summaryDetailsTitle').update({label: record.get('label')});
                                    me.down('#summaryDetailsCost').update({cost: record.get('cost')});
                                    me.down('#summaryDetailsRollingAverage').update({
                                        rollingAverage: record.get('rollingAverage'),
                                        rollingAverageMessage: record.get('rollingAverageMessage')
                                    });
                                    me.down('#summaryDetailsBudgetUseToDate').update(record.getData());

                                    me.show();
                                };
                                if (record.get('rollingAverage') === '') {
                                    Scalr.Request({
                                        processBox: {
                                            type: 'action',
                                            msg: 'Computing...'
                                        },
                                        url: '/analytics/projects/xGetMovingAverageToDate',
                                        params: Ext.apply({
                                            date: Ext.Date.format(record.get('datetime'), 'Y-m-d H:i')
                                        }, requestParams),
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
                                margin: '0 20 18 24'
                            },{
                                xtype: 'container',
                                layout: 'hbox',
                                anchor: '100%',
                                margin: '0 20 20',
                                cls: 'x-cabox',
                                style: 'border:0',
                                defaults: {
                                    flex: 1
                                },
                                items: [{
                                    xtype: 'component',
                                    itemId: 'summaryDetailsCost',
                                    tpl: 'Spent<div class="title1" style="margin-top:8px">{cost:currency(null, 0)}</div>'
                                },{
                                    xtype: 'component',
                                    itemId: 'summaryDetailsRollingAverage',
                                    tpl: '{rollingAverageMessage}<div class="title1" style="margin-top:8px">{rollingAverage:currency(null, 0)}</div>'

                                },{
                                    xtype: 'component',
                                    itemId: 'summaryDetailsBudgetUseToDate',
                                    tpl:  '<tpl if="quarter">Q{quarter} {year} budget<tpl else>Budget</tpl> use to date<div class="title1" style="margin-top:8px">' +
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
                        xtype: 'container',
                        tab: true,
                        cls: 'x-container-fieldset',
                        tabConfig: {
                            title: 'Cloud spend'
                        },
                        value: 'clouds',
                        minHeight: 200,
                        items: [{
                            xtype: 'costanalyticsspends',
                            subject: 'projects'
                        }]
                    },{
                        xtype: 'container',
                        itemId: 'farms',
                        tab: true,
                        cls: 'x-container-fieldset',
                        value: 'farms',
                        tabConfig: {
                            title: 'Farm spend'
                        },
                        minHeight: 200
                    }]

                }]
            }]
		}]
	});

	Scalr.event.on('update', function (type, project) {
		if (type === '/analytics/projects/edit' || type === '/analytics/projects/add') {
            var record = store.getById(project.projectId);
            if (!record) {
                record = store.add(project)[0];
                dataview.getSelectionModel().select(record);
            } else {
                record.set(project);
                panel.down('#form').loadRecord(record);
            }
        } else if (type === '/analytics/projects/remove') {
            var record = store.getById(project.ccId);
            if (record) {
                if (project.removable || record.get('periodTotal') == 0) {
                    store.remove(record);
                } else {
                    record.set('archived', true);
                }
            }
        } else if (type === '/analytics/budgets/edit') {
            refreshStoreOnReconfigure = true;
        }
    }, panel);

	return panel;
});
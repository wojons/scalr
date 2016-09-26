Scalr.regPage('Scalr.ui.admin.analytics.dashboard.view', function (loadPararams, moduleParams) {
    var panelMinWidth = 1060,
        panelMaxWidth = 1440;
    Scalr.utils.Quarters.days = moduleParams['quarters'];

    var reconfigurePage = function(params) {
        if (!panel.isVisible()) {
            panel.down('costanalyticsperiod').restorePreservedValue('quarter');
        }
    };

    var loadPeriodData = function(mode, startDate, endDate, quarter) {
        Scalr.Request({
            processBox: {
                type: 'action',
                msg: 'Computing...'
            },
            url: '/admin/analytics/dashboard/xGetPeriodData',
            params: {
                mode: mode,
                startDate: Ext.Date.format(startDate, 'Y-m-d'),
                endDate: Ext.Date.format(endDate, 'Y-m-d')
            },
            success: function (data) {
                panel.down('#dashboard').loadData(mode, quarter, startDate, endDate, data);
            }
        });
    };
    var panel = Ext.create('Ext.panel.Panel', {
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Cost analytics',
            menuSubTitle: 'Dashboard',
            menuHref: '#/admin/analytics/dashboard',
            leftMenu: {
                menuId: 'analytics',
                itemId: 'dashboard'
            }
        },
        stateId: 'panel-admin-analytics',
        scrollable: true,
        preserveScrollPosition: true,
        layout: 'auto',
        bodyCls: 'x-panel-column-left-with-tabs x-container-fieldset',
        cls: 'x-costanalytics x-costanalytics-admin-dashboard',
        listeners: {

            boxready: function() {
                this.down('costanalyticsperiod').restorePreservedValue('quarter');
            },
            applyparams: reconfigurePage
        },
        defaults: {
            minWidth: panelMinWidth,
            maxWidth: panelMaxWidth
        },
        items: [{
            xtype: 'container',
            layout: 'hbox',
            items: [{
                xtype: 'costanalyticsperiod',
                simple: true,
                listeners: {
                    change: function(mode, startDate, endDate, quarter) {
                        loadPeriodData(mode, startDate, endDate, quarter);
                    }
                }
            },{xtype: 'tbfill'},{
                xtype: 'component',
                itemId: 'unassignedAlert',
                hidden: true,
                html: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-grid-icon x-grid-icon-warning"/>&nbsp;&nbsp;<a class="x-link-warning" href="#">Scalr has detected some unallocated costs!</a>',
                listeners: {
                    render: function() {
                        var me = this;
                        me.el.on('click', function(e){
                            var el = me.el.query('a');
                            if (el.length && e.within(el[0])) {
                                Scalr.utils.Window({
                                    alignTop: true,
                                    width: 460,
                                    padding: '18 24 0',
                                    layout: {
                                        type: 'vbox',
                                        align: 'stretch'
                                    },
                                    items: [{
                                        xtype: 'component',
                                        cls: 'x-fieldset-subheader',
                                        style: 'text-align:center;line-height:28px;',
                                        html: 'The following Scalr environments have not<br/> been assigned to a cost center. Please contact<br/> your Scalr admin to have them assigned.'
                                    },{
                                        xtype: 'displayfield',
                                        cls: 'x-form-field-info',
                                        anchor: '100%',
                                        value: '<a href="https://scalr-wiki.atlassian.net/wiki/display/docs/Environments" target="_blank">What is a Scalr environment?</a>'
                                    },{
                                        xtype: 'dataview',
                                        itemSelector: '.x-item',
                                        flex: 1,
                                        autoScroll: true,
                                        store: {
                                            fields: ['id', 'name', 'cost'],
                                            proxy: 'object',
                                            data: me.unassignedEnvironments
                                        },
                                        tpl: new Ext.XTemplate(
                                            '<tpl if="length">' +
                                                '<table style="width:100%;border-spacing:5 8px">' +
                                                    '<tr><td><b>ID</b></td><td><b>Environment</b></td><td><b>Spent this period</b></td></tr>' +
                                                    '<tpl for=".">' +
                                                        '<tr>' +
                                                            '<td style="width:100px">{id}</td>'+
                                                            '<td><div style="max-width:240px;overflow:hidden;text-overflow:ellipsis">{name}</div></td>'+
                                                            '<td style="width:150px">{[this.currency(values.cost)]}</td>'+
                                                        '</tr>' +
                                                    '</tpl>'+
                                                '</table>'+
                                            '</tpl>'
                                        )

                                    }],
                                    dockedItems: [{
                                        xtype: 'container',
                                        cls: 'x-docked-buttons',
                                        dock: 'bottom',
                                        layout: {
                                            type: 'hbox',
                                            pack: 'center'
                                        },
                                        items: [{
                                            xtype: 'button',
                                            text: 'Close',
                                            handler: function() {
                                                this.up('#box').close();
                                            }
                                        }]
                                    }]
                                });
                                e.preventDefault();
                            }
                        });
                    }
                }
            }]
        },{
            xtype: 'container',
            itemId: 'dashboard',
            loadData: function(mode, quarter, startDate, endDate, data) {
                this.down('dashboardboxes').loadData(mode, quarter, startDate, endDate, data);
                this.down('dashboardtopboxes').loadData(mode, quarter, startDate, endDate, data);
            },
            defaults: {
                minWidth: panelMinWidth,
                maxWidth: panelMaxWidth
            },
            items: [{
                xtype: 'dashboardboxes',
                margin: '24 0 0',
                minHeight: 290,
                listeners: {
                    afterload: function(unassignedEnvironments) {
                        var el = panel.down('#unassignedAlert');
                        el.setVisible(!!unassignedEnvironments);
                        el.unassignedEnvironments = unassignedEnvironments;
                    }
                }
            },{
                xtype: 'dashboardtopboxes',
                margin: '8 0 0',
                defaults: {
                    minWidth: panelMinWidth,
                    maxWidth: panelMaxWidth
                }
            }]
        }]
    });

    return panel;
});

Ext.define('Scalr.ui.DashboardBoxes', {
    extend: 'Ext.container.Container',
    alias: 'widget.dashboardboxes',

    layout: {
        type: 'hbox',
        align: 'stretch'
    },

    initComponent: function() {
        this.enabledSeries = {};
        this.callParent(arguments);
    },
    loadData: function(mode, quarter, startDate, endDate, data) {
        var me = this,
            totals = data['totals'],
            title, today = Scalr.utils.Quarters.getDate(),
            realEndDate = endDate > today ? today : endDate,
            prevStartDate = Ext.Date.parse(data['previousStartDate'], 'Y-m-d'),
            prevEndDate = Ext.Date.parse(data['previousEndDate'], 'Y-m-d'),
            unassignedResources,
            dateFormat = 'M j',
            dateFormatPrev = 'M j';

        Ext.each(data['costcenters'], function(cc){
            if (!cc['id']) {
                unassignedResources = cc;
                return false;
            }
        });
        if (unassignedResources) {
            Ext.each(data['totals']['clouds'], function(cloud){
                if (unassignedResources['platforms'][cloud['id']]) {
                    cloud['unassignedCost'] = unassignedResources['platforms'][cloud['id']]['cost'];
                }
            });
        }

        switch (mode) {
            case 'week':
            case 'custom':
                if (startDate.getFullYear() !== endDate.getFullYear()) {
                    dateFormat = 'M j, Y';
                    dateFormatPrev = 'M j\'y';
                }
                title = startDate < realEndDate ? (Ext.Date.format(startDate, dateFormat) + '&nbsp;&ndash;&nbsp;' + Ext.Date.format(endDate, dateFormat)) : Ext.Date.format(startDate, dateFormat);
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
        }


        me.down('#title').update({
            label: title
        });
        me.down('#total').update({
            cost: totals['cost'],
            unassignedCost: unassignedResources ? unassignedResources['cost'] : null,
            prevCost: totals['prevCost'],
            forecastCost: totals['forecastCost'],
            growth: totals['growth'],
            growthPct: totals['growthPct'],
            period: mode === 'custom' ? 'period' : mode,
            prevPeriod: (prevStartDate - prevEndDate === 0) ? Ext.Date.format(prevStartDate, dateFormatPrev) : (Ext.Date.format(prevStartDate, dateFormatPrev) + '&nbsp;&ndash;&nbsp;' + Ext.Date.format(prevEndDate, dateFormatPrev))
        });

        me.down('#pie').store.loadData(data['totals']['clouds']);
        me.down('dataview').store.loadData(data['totals']['clouds']);

        /*chart*/
        var enabledClouds = [],
            series = {};

        Ext.each(Ext.Object.getKeys(data['clouds']), function(val){
            if (val === 'total') return;
            var c = data['totals']['clouds'][val] || {},
                enabled = me.enabledSeries[val];
            if (enabled === undefined) {
                Ext.each(data['totals']['clouds'], function(c){
                    if (c['id'] === val) {
                        enabled = c['cost'] > 0;
                        return false;
                    }
                });
            }
            if (enabled) {
                series[val] = {
                    color: Scalr.utils.getColorById(val, me.type === 'farms' ? 'farms' : 'clouds'),
                    name: Scalr.utils.getPlatformName(data['clouds'][val]['name']),
                    enabled: !!enabled
                };
                enabledClouds.push(val);
            }
        });
        me.showChart(data, enabledClouds);
        me.down('#seriesSelector').setSeries('clouds', series);

        me.fireEvent('afterload', data.unassignedEnvironments);
    },
    toggleChartSeries: function(name, enabled) {
        this.enabledSeries[name] = enabled;
        this.down('#chartCt').down('chart').toggleSeries(name, enabled);
    },
    prepareDataForChartStore: function(data) {
        var res = [];
        Ext.Array.each(data['timeline'], function(item, index){
            //datetime, onchart, label, extrainfo, series1data, series2data....
            var row = [item.datetime, item.onchart || index, item.label, {}];
            Ext.Object.each(data['clouds'], function(key, value){
                row[3][key] = value['data'][index];
                row.push(value['data'][index] ? value['data'][index]['cost'] : undefined);
            });
            res.push(row);
        });
        return res;
    },
    showChart: function(data, enabledSeries) {
        var me = this,
            chartCt = me.down('#chartCt'),
            seriesList = Ext.Object.getKeys(data['clouds']),
            series = [];

        series.push({
            type: 'bar',
            xField: 'xLabel',
            yField: enabledSeries,
            //stacked: true,
            insetPadding: '10 0',
            renderer: function(sprite, config, data, index){
                var color = '#' + Scalr.utils.getColorById(sprite.getField(), 'clouds');
                return {
                    fillStyle: color,
                    strokeStyle: color
                };
            },
            tips: {
                trackMouse: true,
                hideDelay: 0,
                showDelay: 0,
                tpl: '{[this.itemCost(values)]}',
                renderer: function(record, item) {
                    var info = record.get('extrainfo')[item.field];
                    if (info) {
                        this.update({
                            name: Scalr.utils.getPlatformName(item.field),
                            label: record.get('label'),
                            cost: info['cost'],
                            costPct: info['costPct']
                        });
                    }
                }
            }
        });

        chartCt.removeAll();
        chartCt.add({
            xtype: 'cartesian',
            itemId: 'chart',
            flex: 1,
            height: 260,
            theme: 'scalr',
            store: Ext.create('Ext.data.ArrayStore', {
                fields: Ext.Array.merge([{
                   name: 'datetime',
                   type: 'date',
                   convert: function(v, record) {
                       return Scalr.utils.Quarters.getDate(v, true);
                   }
                }, 'xLabel', 'label', 'extrainfo'], seriesList),
                data: me.prepareDataForChartStore(data)
            }),
            toggleSeries: function(name, enabled) {
                this.getSeries()[0].setHiddenByIndex(Ext.Array.indexOf(enabledSeries, name), !enabled);
                this.redraw();
            },
            axes: [{
                type: 'numeric',
                position: 'left',
                fields: seriesList,
                renderer: function(value){
                    return value > 0 ? Ext.util.Format.currency(value, null, value >= 5 ? 0 : 2) : '';
                },
                minimum: 0,
                majorTickSteps: 4,
                grid: {
                    fillStyle: '#d7e6f2',
                    strokeStyle: '#b9d2ec'
                }
            },{
                type: 'category',
                position: 'bottom',
                fields: ['xLabel'],
                renderer: function(label) {
                    return Ext.isNumeric(label) ? '' : label;
                }
            }],
            series: series
        });
    },

    items: [{
        xtype: 'container',
        flex: 1,
        maxWidth: 740,
        minWidth: 650,
        items: [{
            xtype: 'component',
            itemId: 'title',
            cls: 'x-caheader',
            margin: '0 0 12',
            tpl: '{label}'
        },{
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'component',
                cls: 'x-cabox',
                itemId: 'total',
                width: 240,
                tpl:
                    '<div class="x-cabox-title">Total spend</div>'+
                    '<div class="title1" style="margin:8px 0 12px" <tpl if="unassignedCost">data-qtip="Includes {[this.currency(values.unassignedCost)]} unaccounted by any cost center"</tpl>>{[this.currency(values.cost)]}</div>' +
                    '<div style="margin:0 0 6px"><span class="x-form-item-label-default">Last period</span> <span style="white-space:nowrap">({prevPeriod})</span></div>' +//Same time previous {period}:
                    '<div class="title2">{[this.currency(values.prevCost)]}</div>' +
                    '<tpl if="growth!=0">' +
                        '<div style="margin:10px 0 0" class="x-form-item-label-default">Growth</div>' +
                        '{[this.pctLabel(values.growth, values.growthPct, \'large\', false, \'invert\')]}' +
                    '</tpl>'
            },{
                xtype: 'container',
                cls: 'x-cabox',
                style: 'padding-bottom:0',
                flex: 1,
                items: [{
                    xtype: 'component',
                    html: '<div class="x-cabox-title">By cloud</div>'
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'stretch'
                    },
                    margin: '0 -20',
                    items: [{
                        xtype: 'polar',
                        itemId: 'pie',
                        margin: '0 6',
                        store: {
                            proxy: 'object',
                            fields: ['id', 'name', 'cost', 'costPct', 'prevCost', 'prevCostPct', 'growth', 'growthPct']
                        },
                        width: 145,
                        height: 145,
                        theme: 'scalr',
                        series: [{
                            type: 'pie',
                            field: 'cost',
                            donut: 46,
                            renderer: function(sprite, config, data, index){
                                var record = data.store.getData().items[index];
                                return {
                                    fillStyle: '#' + Scalr.utils.getColorById(record.get('id'), 'clouds'),
                                };
                            },
                            tips: {
                                trackMouse: true,
                                hideDelay: 0,
                                showDelay: 0,
                                tpl: '{[this.itemCost(values)]}',
                                renderer: function(record, item) {
                                    this.update({
                                        name: Scalr.utils.getPlatformName(record.get('id')),
                                        cost: record.get('cost'),
                                        costPct: record.get('costPct')
                                    });
                                }
                            }
                        }]
                    },{
                        xtype: 'dataview',
                        cls: 'x-dataview',
                        flex: 1,
                        itemSelector: '.x-item',
                        maxHeight: 190,
                        autoScroll: true,
                        store: {
                            proxy: 'object',
                            fields: ['id', 'name', 'cost', 'costPct', 'prevCost', 'prevCostPct', 'growth', 'growthPct', 'unassignedCost'],
                            sorters: {
                                property: 'cost',
                                direction: 'DESC'
                            }
                        },
                        tpl: new Ext.XTemplate(
                            '<tpl for=".">' +
                                '<div class="x-item">' +
                                    '<div class="title3"><span title="Percent of total" style="float:right;color:#{[Scalr.utils.getColorById(values.id, \'clouds\')]}">{[values.costPct?values.costPct+\'%\':\'\']}</span>{[this.getColoredItemTitle(values.id)]}</div>' +
                                    '<div style="margin:0 0 0 26px;"><span class="title4" <tpl if="unassignedCost">data-qtip="Includes {[this.currency(values.unassignedCost)]} unaccounted by any cost center"</tpl>>{[this.currency(values.cost)]}</span>&nbsp; ' +
                                    '<tpl if="growth!=0">' +
                                        '{[this.pctLabel(values.growth, values.growthPct)]}' +
                                    '</tpl></div>'+
                                '</div>' +
                            '</tpl>'
                            ,
                            {
                                getColoredItemTitle: function(id) {
                                    return '<span style="color:#' + Scalr.utils.getColorById(id, 'clouds')+ '"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-'+id+'"/>&nbsp;&nbsp;' + Scalr.utils.getPlatformName(id) + '</span>';
                                }
                            }
                        )
                    }]
                }]
            }]
        }]
    },{
        xtype: 'container',
        flex: 1,
        margin: '0 0 0 32',
        items: [{
            xtype: 'container',
            layout: 'hbox',
            items: [{
                xtype: 'component',
                itemId: 'title',
                cls: 'x-caheader',
                html: 'Cloud spend'
            },{
                xtype: 'tbfill'
            },{
                xtype: 'costanalyticsseriesselector',
                itemId: 'seriesSelector',
                listeners: {
                    change: function(comp, name, pressed) {
                        this.up('dashboardboxes').toggleChartSeries(name, pressed);
                    }
                }
            }]
        },{
            xtype: 'container',
            itemId: 'chartCt',
            layout: 'fit'
        }]
    }]
});

Ext.define('Scalr.ui.DashboardTopBoxes', {
    extend: 'Ext.container.Container',
    alias: 'widget.dashboardtopboxes',

    loadData: function(mode, quarter, startDate, endDate, data) {
        var typeField = this.down('#type');
        this.data = data;

        if (!typeField.getValue()) {
            typeField.setValue('costcenters');
        } else {
            this.setType(typeField.getValue());
        }
    },
    setType: function(type) {
        var me = this;
        me.down('#totalspend').loadData(type, me.data[type]);
        me.down('#changeoverpp').loadData(type, me.data[type]);
        me.down('#budget').loadData(type, me.data['budget'][type]);
    },
    items: [{
        xtype: 'buttongroupfield',
        itemId: 'type',
        margin: '0 0 18 0',
        width: 400,
        listeners: {
            change: function(comp, value) {
                this.up('dashboardtopboxes').setType(value);
            }
        },
        defaults: {
            width: 160,
            height: 32
        },
        items: [{
            text: 'Top 5 cost centers',
            value: 'costcenters'
        },{
            text: 'Top 5 projects',
            value: 'projects'
        }]
    },{
        xtype: 'container',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        defaults: {
            xtype: 'container',
            flex: 1,
            cls: 'x-cabox3',
            minHeight: 100
        },
        items: [{
            items: [{
                xtype: 'component',
                cls: 'x-cabox-title',
                html: 'Total spend'
            },{
                xtype: 'container',
                itemId: 'totalspend',
                flex: 1,
                margin: '16 0 0',
                layout: 'hbox',
                loadData: function(type, data) {
                    var maxCost,
                        rows = [],
                        items1 = [],
                        items2 = [];
                    this.removeAll();
                    if (data.length) {
                        Ext.each(data, function(row){
                            row = Ext.clone(row);
                            rows.push(row);
                            row['pctOfMax'] = 0;
                            maxCost = maxCost > row['cost'] ? maxCost : row['cost'];
                        });
                        if (Math.round(maxCost) != 0) {
                            Ext.each(rows, function(row){
                                row['pctOfMax'] = row['cost']/maxCost*100;
                            });
                        }
                        var top5 = new Ext.util.MixedCollection();
                        top5.addAll(rows);
                        top5.sort('cost', 'DESC');


                        Ext.each(top5.getRange(0,4), function(item, index){
                            items1.push({
                                xtype: 'component',
                                style: 'text-align:left',
                                height: 66,
                                data: item,
                                tpl:
                                    '<tpl if="id">' +
                                        '<a href="#/admin/analytics/' + type + '?' + (type === 'costcenters' ? 'cc' : 'project') + 'Id={id}">' +
                                    '</tpl>' +
                                        '<div style="white-space:nowrap;width:100%;text-overflow:ellipsis;overflow:hidden;" class="link" data-qtip="{name}">{name}</div>' +
                                        '<div style="margin-top:4px"><div class="bar-inner" style="margin:0;width:{pctOfMax}%;text-decoration:none"><span>{[this.currency(values.cost)]}</span></div></div>' +
                                    '<tpl if="id">' +
                                        '</a>' +
                                    '</tpl>'
                            });
                            items2.push({
                                xtype: 'component',
                                height: 66,
                                data: item,
                                tpl: '<div style="margin-bottom:4px">' + (index == 0 ? '<b>% of total</b>' : '&nbsp;') + '</div>{costPct}%'
                            });
                        });
                        this.add([{
                            xtype: 'container',
                            flex: 1,
                            items: items1
                        },{
                            xtype: 'container',
                            width: 75,
                            padding: '0 0 0 6',
                            items: items2
                        }]);
                    } else {
                        this.add({xtype: 'component',  flex: 1, html: 'No data available'});
                    }

                }
            }]
        },{
            items: [{
                xtype: 'container',
                cls: 'x-cabox-title',
                itemId: 'sorters',
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                items: [{
                   xtype: 'component',
                   html: 'Change over previous period'
                },{
                    xtype: 'buttongroupfield',
                    itemId: 'mode',
                    value: '$',
                    cls: 'x-mode-btn',
                    listeners: {
                        change: function(comp, value){
                            this.up().next('#changeoverpp').setMode(value);
                        }
                    },
                    items: [{
                        text: '$',
                        tooltip: 'Order by growth amount',
                        value: '$'
                    },{
                        text: '%',
                        tooltip: 'Order by growth percentage',
                        value: '%'
                    }]

                }]
            },{
                xtype: 'container',
                itemId: 'changeoverpp',
                flex: 1,
                margin: '16 0 0',
                layout: 'hbox',
                loadData: function(type, data) {
                    this.data = new Ext.util.MixedCollection();
                    this.data.addAll(data);
                    this.type = type;
                    this.setMode();

                },
                setMode: function(mode) {
                    var me = this,
                        sorters,
                        data,
                        maxCost,
                        items1 = [],
                        items2 = [];

                    mode = mode || this.up().down('#mode').getValue();
                    me.pctLabelMode = mode === '$' ? 'invert' : 'default';

                    if (mode === '$') {
                        sorters = {
                            sorterFn: function(rec1, rec2) {
                                return Math.abs(rec1.growth) > Math.abs(rec2.growth) ? 1 : -1;
                            },
                            direction: 'DESC'
                        };
                    } else {
                        sorters = [{
                            sorterFn: function(rec1, rec2) {
                                if (rec1.growthPct*1 === rec2.growthPct*1) {
                                    return 0;
                                } else {
                                    return rec1.growthPct*1 > rec2.growthPct*1 ? 1 : -1;
                                }
                            },
                            direction: 'DESC'
                        },{
                            sorterFn: function(rec1, rec2) {
                                return Math.abs(rec1.growth) > Math.abs(rec2.growth) ? 1 : -1;
                            },
                            direction: 'DESC'
                        }];
                    }

                    me.data.sort(sorters);

                    me.removeAll();
                    data = me.data.getRange(0, 5);
                    me.up().down('#sorters').setVisible(data.length > 0);
                    if (data.length > 0) {
                        Ext.each(data, function(row){
                            row['pctOfMax'] = 0;
                            row['prevPctOfMax'] = 0;
                            maxCost = maxCost > row['cost'] ? maxCost : row['cost'];
                            maxCost = maxCost > row['prevCost'] ? maxCost : row['prevCost'];
                        });
                        if (Math.round(maxCost) != 0) {
                            Ext.each(data, function(row){
                                row['pctOfMax'] = row['cost']/maxCost*100;
                                row['prevPctOfMax'] = row['prevCost']/maxCost*100;
                            });
                        }
                        Ext.each(data, function(item, index){
                            items1.push({
                                xtype: 'component',
                                style: 'text-align:left',
                                height: 66,
                                data: item,
                                tpl:
                                    '<tpl if="id">' +
                                        '<a href="#/admin/analytics/' + me.type + '?' + (me.type === 'costcenters' ? 'cc' : 'project') + 'Id={id}">' +
                                    '</tpl>' +
                                        '<div style="white-space:nowrap;width:100%;text-overflow:ellipsis;overflow:hidden;" class="link" data-qtip="{name}">{name}</div>' +
                                        '<div title="Current period" style="margin-top:4px"><div class="bar-inner" style="background: #337cc6;width:{pctOfMax}%"><span>{[this.currency(values.cost)]}</span></div></div>' +
                                        '<div title="Previous period" style="margin:2px 0 0;"><div class="bar-inner" style="background:#a8c5e3;width:{prevPctOfMax}%"><span>{[this.currency(values.prevCost)]}</span></div></div>' +
                                    '<tpl if="id">' +
                                        '</a>' +
                                    '</tpl>'
                            });
                            items2.push({
                                xtype: 'component',
                                height: 66,
                                data: item,
                                padding: '22 0 0 18',
                                tpl:
                                    '<tpl if="growth!=0">' +
                                        '{[this.pctLabel(values.growth, values.growthPct, \'small\', \'fixed\', \''+ me.pctLabelMode + '\')]}' +
                                    '</tpl>'

                            });
                        });
                        this.add([{
                            xtype: 'container',
                            flex: 1,
                            items: items1
                        },{
                            xtype: 'container',
                            padding: '0 0 0 6',
                            items: items2
                        }]);
                    } else {
                        this.add({xtype: 'component',  flex: 1, html: 'No data available'});
                    }

                }
            }]
        },{
            items: [{
                xtype: 'component',
                cls: 'x-cabox-title',
                html: 'Quarter budget'
            },{
                xtype: 'container',
                itemId: 'budget',
                flex: 1,
                layout: 'hbox',
                margin: '16 0 0',
                loadData: function(type, data) {
                    var items1 = [],
                        items2 = [],
                        items3 = [];
                    this.removeAll();
                    if (data.length) {
                        var top5 = new Ext.util.MixedCollection();
                        top5.addAll(data);
                        top5.sort(function(rec1, rec2) {
                            var v1 = rec1['budgetSpentPct'],
                                v2 = rec2['budgetSpentPct'];
                            if (v1 === v2) {
                                if (v1 !== null) {
                                    v1 = rec1['budgetSpent']*1;
                                    v2 = rec2['budgetSpent']*1;
                                }
                                if (v1 === v2) {
                                    return 0;
                                } else {
                                    return v1 > v2 ? 1 : -1;
                                }
                            } else {
                                return v1 > v2 || v2 === null ? 1 : -1;
                            }
                        }, 'DESC');

                        Ext.each(top5.getRange(0,4), function(item, index){
                            items1.push({
                                xtype: 'component',
                                style: 'text-align:left',
                                height: 66,
                                data: item,
                                tpl: new Ext.XTemplate(
                                    '<tpl if="id">' +
                                        '<a href="#/admin/analytics/' + type + '?' + (type === 'costcenters' ? 'cc' : 'project') + 'Id={id}">' +
                                    '</tpl>' +
                                        '<div style="white-space:nowrap;width:100%;text-overflow:ellipsis;overflow:hidden;" class="link" data-qtip="{name}">{name}</div>' +
                                        '<div class="bar-wrapper" style="margin-top:4px"><div class="bar-inner x-costanalytics-bg-{[this.getColorCls(values)]}" style="width:{[values.budget > 0 ? 100-values.budgetRemainPct : 0]}%"><span>{[this.currency(values.budgetSpent)]}</span></div></div>' +
                                    '<tpl if="id">' +
                                        '</a>' +
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
                            });
                            items2.push({
                                xtype: 'component',
                                height: 66,
                                data: item,
                                tpl: '<div style="margin-bottom:4px">' + (index === 0 ? '<b>Budget</b>' : '&nbsp;') + '</div>{[this.currency(values.budget)]}'
                            });
                            items3.push({
                                xtype: 'component',
                                height: 66,
                                data: item,
                                tpl:
                                    '<div style="margin-bottom:4px">' +
                                        (index === 0 ? '<b>Remaining</b>' : '&nbsp;') +
                                    '</div>' +
                                    '<tpl if="budget"><span class="x-costanalytics-{[this.getColorCls(values)]}">{budgetRemainPct}%</span><tpl else>&ndash;</tpl>'
                            });
                        });
                        this.add([{
                            xtype: 'container',
                            flex: 1,
                            items: items1
                        },{
                            xtype: 'container',
                            padding: '0 0 0 20',
                            items: items2
                        },{
                            xtype: 'container',
                            padding: '0 0 0 10',
                            items: items3,
                            width: 90
                        }]);

                    } else {
                        this.add({xtype: 'component',  flex: 1, html: 'No data available'});
                    }
                }
            }]
        }]
    }]
});

Ext.define('Scalr.ui.CostAnalyticsSeriesSelector', {
    extend: 'Ext.container.Container',
    alias: 'widget.costanalyticsseriesselector',

    layout: 'hbox',
    margin: '0 0 0 36',

    defaults: {
        margin: '0 6 0 0'
    },

    setSeries: function(type, series){
        var me = this,
            seriesCount = Ext.Object.getSize(series);
        me.suspendLayouts();
        me.removeAll();
        if (type !== 'clouds' && seriesCount > 3 || type === 'farms') {
            var menuItems = [];
            Ext.Object.each(series, function(key, value){
                menuItems.push({
                    text: '<span style="color:#'+value.color+'">'  + value.name + '</span>',
                    value: key,
                    checked: value.enabled
                });
            });
            me.add({
                xtype: 'cyclealt',
                multiselect: true,
                cls: 'x-btn-compressed',
                width: 200,
                allSelectedText: 'All ' + type + ' selected',
                selectedItemsSeparator: ', ',
                selectedTpl: '{0} of {1} ' + type + ' selected',
                noneSelectedText: 'No ' + type + ' selected',
                changeHandler: function(comp, item) {
                    me.fireEvent('change', me, item.value, !!item.checked);
                },
                getItemText: function(item) {
                    return item.text;
                },
                menu: {
                    cls: 'x-menu-light',
                    minWidth: 200,
                    items: menuItems
                }
            });
        } else {
            Ext.Object.each(series, function(key, value){
                me.add({
                    xtype: 'button',
                    cls: 'x-costanalytics-item-btn x-costanalytics-' + value.color,
                    text: (type === 'clouds' ? '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-'+key+'" />' : value.name),
                    tooltip: !value.enabled ? 'No spends on the <b>' + value.name + '</b>' : value.name,
                    value: key,
                    pressed: value.enabled,
                    disabled: !value.enabled,
                    enableToggle: true,
                    maxWidth: 130,
                    toggleHandler: function(btn, pressed){
                        me.fireEvent('change', me, btn.value, pressed);
                    }
                });
            });
        }
        me.resumeLayouts(true);
    },

    toggleSeries: function(series) {
        if (this.items.first().xtype === 'cyclealt') {
            this.down().menu.items.each(function(item){
                if (series[item.value] !== undefined) {
                    item.setChecked(series[item.value], true);
                }
            });
        } else {
            this.items.each(function(item){
                if (series[item.value] !== undefined) {
                    item.toggle(series[item.value], true);
                }
            });
        }
    }
});

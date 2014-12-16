//Ext.getHead().createChild('<style type="text/css" media="print">.x-panel-cost-report{left:0!important;width:100%!important;}.x-panel-cost-report .x-panel-body-default{border:0;width:100%!important}</style>');
Scalr.regPage('Scalr.ui.public.report', function (loadParams, moduleParams) {
    Scalr.application.disabledDockedToolbars(true, true);
    Scalr.application.addCls('x-panel-white-background');


    var totals = moduleParams['totals'],
        startDate = Scalr.utils.Quarters.getDate(moduleParams['startDate']),
        endDate = Scalr.utils.Quarters.getDate(moduleParams['endDate']),
        title, today = Scalr.utils.Quarters.getDate(),
        realEndDate = endDate > today ? today : endDate,
        prevStartDate = Ext.Date.parse(moduleParams['previousStartDate'], 'Y-m-d'),
        prevEndDate = Ext.Date.parse(moduleParams['previousEndDate'], 'Y-m-d'),
        dateFormat = 'M j',
        dateFormatPrev = 'M j';

    switch (moduleParams['period']) {
        case 'week':
        case 'custom':
            if (startDate.getFullYear() !== endDate.getFullYear()) {
                dateFormat = 'M j, Y';
                dateFormatPrev = 'M j\'y';
            }
            title = startDate < realEndDate ? (Ext.Date.format(startDate, dateFormat) + '&nbsp;&ndash;&nbsp;' + Ext.Date.format(endDate, dateFormat)) : Ext.Date.format(startDate, dateFormat);
            moduleParams['forecastTitle'] = Ext.Date.format(startDate, 'F') + ' forecast';
        break;
        case 'month':
            title = Ext.Date.format(startDate, 'F Y');
            moduleParams['forecastTitle'] = 'Q' + totals['budget']['quarter'] + ' forecast';

        break;
        case 'year':
            title = Ext.Date.format(startDate, 'Y');
        break;
        case 'quarter':
            title = 'Q' + totals['budget']['quarter'] + ' ' + totals['budget']['year'] + ' (' + Ext.Date.format(Scalr.utils.Quarters.getDate(totals['budget']['quarterStartDate']), 'M j') + ' &ndash; ' + Ext.Date.format(Scalr.utils.Quarters.getDate(totals['budget']['quarterEndDate']), 'M j') + ')';
            moduleParams['forecastTitle'] = 'Year forecast';
        break;
    }


	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
            maximize: 'all',
            reload: false
		},
        layout: 'anchor',
        autoScroll: true,
        items: [{
            xtype: 'container',
            cls: 'x-costanalytics x-panel-cost-report',
            layout: 'anchor',
            width: 650,
            items: [{
                xtype: 'component',
                cls: 'x-header',
                itemId: 'header',
                tpl: '<div style="float:left">{name:htmlEncode}</div>' +
                      '<div style="float:right">{title}</div>'
           },{
               xtype: 'component',
               itemId: 'totals',
               cls: 'x-totals',
               margin: '20 32 0',
               tpl:
                   '<table style="width:100%;border-collapse:collapse">'+
                   '<tr>' +
                       '<td style="width:60%">' +
                           '<div class="x-title1">Total spent</div>' +
                           '<span class="x-title2" style="font-size:34px">{[this.currency(values.totals.cost)]}</span>' +
                           '<tpl if="totals.growth!=0">' +
                               ' &nbsp;&nbsp;{[this.pctLabel(values.totals.growth, values.totals.growthPct, \'large\', false, \'noqtip\')]}' +
                           '</tpl>'+
                       '</td>' +
                       '<td>' +
                           '<div class="x-title1">{forecastTitle}</div>' +
                           '<span class="x-title2">~{[values.totals.forecastCost !== null ? this.currency(values.totals.forecastCost): \'n/a\']}</span>' +
                       '</td>' +
                   '</tr>'+
                   '<tr>' +
                       '<td >' +
                           '<br/><div class="x-title1">Prev. {[values.period!=\'custom\'?values.period:\'day\']} ({prevPeriod})</div>' +
                           '<span class="x-title2">{[this.currency(values.totals.prevCost)]}</span>' +
                       '</td>' +
                       '<td>' +
                           '<br/><div class="x-title1">{[values.totals.trends.rollingAverageMessage]}</div>' +
                           '<span class="x-title2">{[this.currency(values.totals.trends.rollingAverage)]} </span>per {interval}' +
                       '</td>' +
                   '</tr>'

           },{
               xtype: 'component',
               cls: 'x-title1',
               itemId: 'summaryChartTitle',
               margin: '32 32 0',
               html: '&nbsp;'
           },{
               xtype: 'chart',
               theme: 'Scalr',
               animate: true,
               height: 120,
               anchor: '100%',
               margin: '10 32 0',
               style: 'background:#f4fafe',
               insetPadding: 3,
               store: Ext.create('Ext.data.ArrayStore', {
                   fields: [{
                       name: 'datetime',
                       type: 'date',
                       convert: function(v, record) {
                           return Scalr.utils.Quarters.getDate(v,  true);
                       }
                   }, 'xLabel', 'label', 'cost'],
                   data: Ext.Array.map(moduleParams['timeline'], function(item){
                       return [item.datetime, item.onchart, item.label, item.cost || 0];
                   })
               }),

                axes: [{
                    type: 'Numeric',
                    position: 'left',
                    fields: ['cost'],
                    minimum: 0,
                    hidden: true
                }],

               series: [{
                   type: 'line',
                   shadowAttributes: [],
                   axis: 'left',
                   xField: 'xLabel',
                   yField: 'cost',
                   style: {
                       'stroke-width': 1,
                       fill: '#98c2ea'
                   },
                   markerConfig: {
                       type: 'circle',
                       radius: 1,
                       fill: '#327ac2'
                   }
               }]
           },{
               xtype: 'component',
               itemId: 'budgetBar',
               margin: '32 32 0',
               hidden: !moduleParams['totals']['budget']['budget'],
               tpl: new Ext.XTemplate(
                   '<span class="x-title1" style="float:right;">Remaining <span class="x-title3">{[this.currency(values.budgetRemain)]}</span></span>'+
                   '<span class="x-title1">Q{quarter} budget <span class="x-title3">{[this.currency(values.budget)]}</span></span>'+
                   '<div class="bar-wrapper" style="margin-top:4px;border-radius:0">'+
                       '<div class="bar-inner x-costanalytics-bg-{[this.getColorCls(values)]}" style="line-height: 30px;width:{[values.budget > 0 ? 100-values.budgetRemainPct : 0]}%">'+
                           '<span>{[this.currency(values.budgetSpent)]}</span>'+
                       '</div>'+
                   '</div>',
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
            },{
                xtype: 'component',
                cls: 'x-footer',
                html: '<a href="https://my.scalr.com#/analytics/dashboard">View detailed statistics</a>'
            }]
        }],
        listeners: {
            boxready: function(){
                var ct = panel.down();
                ct.insert(ct.items.length - 1, table);
            },
            hide: function() {
                Scalr.application.removeCls('x-panel-white-background');
                Scalr.application.disabledDockedToolbars(false);
                this.close();
            }
        }
    });
    panel.down('#header').update({name: moduleParams['name'], title: title});
    moduleParams['prevPeriod'] = (prevStartDate - prevEndDate === 0) ? Ext.Date.format(prevStartDate, dateFormatPrev) : (Ext.Date.format(prevStartDate, dateFormatPrev) + '&nbsp;&ndash;&nbsp;' + Ext.Date.format(prevEndDate, dateFormatPrev));
    panel.down('#totals').update(moduleParams);
    panel.down('#summaryChartTitle').update(Ext.String.capitalize(moduleParams['interval'].replace('day', 'dai')) + 'ly breakdown');
    panel.down('#budgetBar').update(totals['budget']);


    prepareDataForChartStore = function(type, id) {
        var res = [];
        Ext.Array.each(moduleParams['timeline'], function(item, index){
            var row = [item.datetime, item.onchart, item.label];
            if (moduleParams[type][id]) {
                row.push(moduleParams[type][id]['data'][index] ? moduleParams[type][id]['data'][index]['cost'] : 0);
            }
            res.push(row);
        });
        return res;
    };

    var table = {
        xtype: 'container',
        margin: '32 32 0',
        anchor: '100%',
        layout: {
            type: 'table',
            columns: 4,
            tableAttrs: {
                style: {
                    width: '100%'
                }
            },
            tdAttrs: {
                style: {
                    padding: '0 0 12px 0'
                }
            }
        },
        items: []

    };
    Ext.each(['clouds', totals['costcenters'] ? 'costcenters' : (totals['projects'] ? 'projects' : 'farms')], function(type){
        if (Ext.Object.getSize(totals[type])>0) {
            table.items.push({
                xtype: 'component',
                colspan: 4,
                html: '<div class="x-title1" style="margin:12px 0 0">'+(type==='clouds' ? 'Clouds' : 'Top 5 ' + type) +'</div>'
            });
        }
        Ext.each(totals[type], function(item, i){
            table.items.push({
                    xtype: 'component',
                    tpl: type !== 'clouds' ? '{name}' : '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-platform-small x-icon-platform-small-{id}" /> &nbsp;<span style="color:#{[Scalr.utils.getColorById(values.id, \'clouds\')]}">{[Scalr.utils.getPlatformName(values.name)]}</span>',
                    data: item,
                    style: 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis',
                    padding: '0 20 0 0',
                    width: 170,
                    tdAttrs: {
                        width: 170
                    }
                },{
                    xtype: 'component',
                    tpl: '{[this.currency(values.cost)]}',
                    data: item,
                    tdAttrs: {
                        width: '1%'
                    }
                },{
                    xtype: 'component',
                    tpl: 
                        '<tpl if="growth!=0">' +
                            '&nbsp;&nbsp;&nbsp;{[this.pctLabel(values.growth, values.growthPct, \'small\', true, \'noqtip\')]}' +
                        '</tpl>',
                    data: item
                },{
                    xtype: 'chart',
                    theme: 'Scalr',
                    animate: false,
                    tdAttrs: {
                        width: 180
                    },
                    width: 180,
                    height: 32,
                    margin: '-8 0 0',
                    //insetPadding: 3,
                    store: Ext.create('Ext.data.ArrayStore', {
                        fields: [{
                            name: 'datetime',
                            type: 'date',
                            convert: function(v, record) {
                                return Scalr.utils.Quarters.getDate(v,  true);
                            }
                        }, 'xLabel', 'label', 'cost'],
                        data: prepareDataForChartStore(type, item.id)
                    }),

                    series: [{
                        type: 'line',
                        shadowAttributes: [],
                        axis: 'left',
                        xField: 'xLabel',
                        yField: 'cost',
                        smooth: true,
                        style: {
                            'stroke-width': .4,
                            fill: '#327ac2'
                        },
                        markerConfig: {
                            type: 'circle',
                            radius: 0,
                            fill: '#327ac2'
                        }

                    }]
            });
            if (type !== 'clouds' && i === 4){
                return false;
            }
        });
    });
    return panel;
});
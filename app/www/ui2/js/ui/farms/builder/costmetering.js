Ext.define('Scalr.ui.FarmBuilderFarmCostMetering', {
	extend: 'Ext.form.FieldSet',
	alias: 'widget.farmcostmetering',
    
    cls: 'x-fieldset-separator-none',

    initComponent: function() {
        var me = this,
            data = me['analyticsData'],
            farmCostMeteringData,
            seriesList,
            items = [];
        me.callParent();
        if (Scalr.flags['analyticsEnabled']) {
            farmCostMeteringData = data['farmCostMetering'];
            seriesList = farmCostMeteringData ? Ext.Object.getKeys(farmCostMeteringData['farmroles']) : [];
            me.setColorMap();
            
            items.push({
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'combo',
                    store: {
                        fields: [ 'projectId', 'name', 'budgetRemain', {name: 'description', convert: function(v, record) {
                            return record.data.name + '  (' + (record.data.budgetRemain === null ? 'budget is not set' : 'Budget remain ' + Ext.util.Format.currency(record.data.budgetRemain)) + ')';
                        }} ],
                        data: data ? data['projects'] : []
                    },
                    flex: 1,
                    maxWidth: 370,
                    editable: false,
                    autoSetSingleValue: true,
                    valueField: 'projectId',
                    displayField: 'description',
                    fieldLabel: 'Project',
                    labelWidth: 60,
                    name: 'projectId',
                    itemId: 'projectId',
                    plugins: [{
                        ptype: 'comboaddnew',
                        pluginId: 'comboaddnew',
                        url: '/analytics/account/projects/add',
                        disabled: !Scalr.isAllowed('ADMINISTRATION_ANALYTICS', 'manage-projects') || data['costCenterLocked'] == 1
                    }],
                    listConfig: {
                        cls: 'x-boundlist-alt',
                        tpl:
                            '<tpl for=".">' +
                                '<div class="x-boundlist-item" style="height: auto; width: auto; max-width: 900px;">' +
                                    '<div><span style="font-weight: bold">{name}</span>' +
                                        '&nbsp;&nbsp;<span style="color: #666; font-size: 11px;"><tpl if="budgetRemain!==null">Budget remain {[this.currency2(values.budgetRemain)]}<tpl else><i>Budget is not set</i></tpl></span>' +
                                    '</div>' +
                                '</div>' +
                            '</tpl>'
                    },
                },{
                    xtype: 'displayfield',
                    fieldLabel: 'Cost center',
                    value: data ? data['costCenterName'] : null,
                    margin: '0 0 0 24',
                    labelWidth: 90
                }]
            });

            items.push({
                xtype: 'displayfield',
                itemId: 'unsupportedRoles',
                hidden: true,
                anchor: '100%',
                maxWidth: 600,
                margin: '12 0',
                cls: 'x-form-field-warning'
            },{
                xtype: 'container',
                flex: 1,
                layout: 'hbox',
                items: [{
                    xtype: 'container',
                    width: 180,
                    margin: '18 0 0',
                    items: [{
                        xtype: 'component',
                        html: '<label>Current spend rate</label>'
                    },{
                        xtype: 'chart',
                        margin: '10 0 0 0',
                        itemId: 'currentRateChart',
                        width: 180,
                        height: 100,
                        store: Ext.create('Ext.data.ArrayStore', {
                            fields: ['value']
                        }),
                        maxValue: 3,
                        minLabelValue: 1,
                        maxLabelValue: 2,
                        insetPadding: 20,
                        //insetPaddingTop: 20,
                        axes: [{
                            type: 'gaugeminmax',
                            position: 'gauge',
                            margin: 8
                        }],
                        series: [{
                            type: 'gauge',
                            field: 'value',
                            donut: 75,
                            colorSet: ['#F49D10', '#ddd']
                        }]
                    },{
                        xtype: 'component',
                        itemId: 'currentRate',
                        style: 'text-align:center',
                        margin: '-50 0 0',
                        tpl: '<div style="font-weight:bold;font-size:130%" data-qtip="{[this.currency2(values.hourlyRate)]} per hour">{[this.currency2(values.dailyRate, true)]}</div>per day'
                    },{
                        xtype: 'component',
                        itemId: 'minMaxRate',
                        margin: '26 0 0',
                        style: 'text-align:center;color:#444444',
                        tpl: 'min:&nbsp;<span data-qtip="{[this.currency2(values.min.hourlyRate)]} per hour">{[this.currency2(values.min.dailyRate)]}</span>, &nbsp;'+
                             'max:&nbsp;<span data-qtip="{[this.currency2(values.max.hourlyRate)]} per hour">{[this.currency2(values.max.dailyRate)]}</span>'
                    }]
                },{
                    xtype: 'container',
                    flex: 1,
                    margin: '18 0 0 28',
                    maxWidth: 400,
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },
                    items: [{
                        xtype: 'container',
                        layout: 'hbox',
                        items: [{
                            xtype: 'component',
                            html: '<label>Last 7 days</label>'
                        },{
                            xtype: 'component',
                            flex: 1,
                            style: 'text-align:right;font-weight:bold;font-size:130%;margin-right:10px',
                            tpl: '{[values.cost?this.currency2(values.cost, true):\'\']}',
                            data: {cost: farmCostMeteringData ? farmCostMeteringData['totals']['cost'] : ''}
                        }]
                    },farmCostMeteringData ? {
                        xtype: 'chart',
                        itemId: 'chart',
                        height: 140,
                        insetPaddingTop: 18,
                        store: Ext.create('Ext.data.ArrayStore', {
                            fields: Ext.Array.merge([{
                               name: 'datetime',
                               type: 'date',
                               convert: function(v, record) {
                                   return Scalr.utils.Quarters.getDate(v, true);
                               }
                            }, 'xLabel', 'label', 'cost', 'growth', 'growthPct', 'extrainfo'], seriesList),
                            data: me.prepareDataForChartStore()
                        }),
                        axes: [{
                            type: 'Numeric',
                            position: 'left',
                            fields: seriesList,
                            label: {
                                renderer: function(value){return value > 0 ? Ext.util.Format.currency(value, null, value >= 5 ? 0 : 2) : 0}
                            },
                            style : {
                                stroke : 'red'
                            },
                            minimum: 0,
                            majorTickSteps: 3
                        },{
                            type: 'Category',
                            position: 'bottom',
                            dateFormat: 'M d',
                            fields: ['xLabel']
                        }],
                        series: [{
                            type: 'column',
                            shadowAttributes: [],
                            axis: 'bottom',
                            gutter: 80,
                            xField: 'xLabel',
                            yField: seriesList,
                            stacked: true,
                            xPadding: 0,
                            renderer: function(sprite, record, attr, index, store){
                                var yField = sprite.surface.owner.series.getAt(0).yField,
                                    name = yField[index%yField.length];
                                Ext.apply(attr, {fill: '#' + me.getItemColor(name)});
                                return attr;
                            },
                            tips: {
                                cls: 'x-tip-light',
                                trackMouse: true,
                                hideDelay: 0,
                                showDelay: 0,
                                tpl:
                                    '{[this.itemCost(values, false)]}' +
                                    '<div class="scalr-ui-costmetering-hours">' +
                                        '<tpl foreach="hours">' +
                                            '<div class="title">{$}</div>' +
                                            '<table>' +
                                                '<tr><th>Hours</th><th>Min</th><th>Avg</th><th>Max</th></tr>' +
                                                '<tr><td>{[values.hours]}</td><td>{[values.min]}</td><td>{[values.avg]}</td><td>{[values.max]}</td></tr>' +
                                            '</table>' +
                                        '</tpl>' +
                                    '</div>',
                                renderer: function(record, item) {
                                    var info = record.get('extrainfo')[item.yField];
                                    this.update({
                                        id: item.yField,
                                        name: farmCostMeteringData['farmroles'][item.yField]['name'],
                                        label: record.get('xLabel'),
                                        cost: info['cost'],
                                        costPct: info['costPct'],
                                        interval: 'day',
                                        hours: info['hours'],
                                        color: me.getItemColor(item.yField)
                                    });
                                }
                            }

                        },{
                            type: 'line',
                            selectionTolerance: 8,
                            skipWithinBoxCheck: true,
                            shadowAttributes: [],
                            axis: 'left',
                            xField: 'xLabel',
                            yField: 'cost',
                            //showMarkers: false,
                            style: {
                                radius: 4,
                                stroke: '#00468c',
                                opacity: 0.7,
                                'stroke-width': 2
                            },
                            highlight: {
                                radius: 4,
                                fill: '#00468c',
                                'stroke-width': 0
                            },
                            highlightLine: false,
                            //smooth: true,
                            markerConfig: {
                                type: 'circle',
                                radius: 3,
                                fill: '#00468c',
                                'stroke-width': 0,
                                //cursor: 'pointer'
                            },
                            tips: {
                                cls: 'x-tip-light',
                                trackMouse: true,
                                hideDelay: 0,
                                showDelay: 0,
                                tpl:
                                    '<div style="text-align:center"><b>{label}</b></div>' +
                                    '<div class="scalr-ui-costmetering-farmroles">' +
                                        '<table>' +
                                            '<tr>'+
                                                '<th>Total spend</th>' +
                                                '<th>{[this.currency2(values.cost)]}</th>' +
                                                '<th><tpl if="growth!=0">{[this.pctLabel(values.growth, values.growthPct, null, false, \'invert\', false)]}</tpl></th>' +
                                            '</tr>' +
                                            '<tpl foreach="farmroles">' +
                                                '<tr>' + 
                                                    '<td><span style="font-weight:bold;color:#{color}">&nbsp;{name}&nbsp;</span></td>' +
                                                    '<td>{[this.currency2(values.cost)]} {[values.costPct > 0 ? \'(\'+values.costPct+\'%)\' : \'\']}</td>' +
                                                    '<td><tpl if="growth!=0">{[this.pctLabel(values.growth, values.growthPct, null, false, \'invert\', false)]}</tpl></td>'+
                                                '</tr>' +
                                            '</tpl>' +
                                        '</table>' +
                                    '</div>',
                                renderer: function(record, item) {
                                    var farmroles = [];
                                    Ext.Object.each(record.get('extrainfo'), function(key, value){
                                        if (value && value.cost > 0) {
                                            var farmrole = {
                                                id: key,
                                                name: farmCostMeteringData['farmroles'][key]['name'],
                                                color: me.getItemColor(key)
                                            };
                                            farmroles.push(Ext.apply(farmrole, value));
                                        }
                                    });
                                    var farmRolesSorted = new Ext.util.MixedCollection();
                                    farmRolesSorted.addAll(farmroles);
                                    farmRolesSorted.sort('cost', 'DESC');
                                    this.update({
                                        id: item.yField,
                                        label: record.get('xLabel'),
                                        cost: record.get('cost'),
                                        growth: record.get('growth'),
                                        growthPct: record.get('growthPct'),
                                        farmroles: farmRolesSorted.getRange()
                                    });
                                }
                            }
                        }]

                    } : {
                        xtype: 'component',
                        style: 'text-align:center;font-style:italic',
                        margin: '48 0',
                        html: 'No cost data'
                    }]

                }]
            });
        } else {
            
        }
        me.add(items);
    },

    setColorMap: function() {
        var me = this,
            data = me['analyticsData']['farmCostMetering'],
            i = 0;
        me.colorMap = {};
        if (data) {
            Ext.Object.each(data['farmroles'], function(key, value){
                me.colorMap[key] = Scalr.utils.getColorById(i++, 'farms');
            });
        }
    },

    getItemColor: function(id) {
        var colorMap = this.colorMap;
        if (colorMap && colorMap[id] !== undefined) {
            return colorMap[id];
        } else {
            return '000000';
        }
    },
    
    prepareDataForChartStore: function() {
        var me = this,
            data = me['analyticsData']['farmCostMetering'],
            res = [];
        Ext.Array.each(data['timeline'], function(item, index){
            //datetime, onchart, label, cost, extrainfo, events, series1data, series2data....
            var row = [item.datetime, item.onchart, item.label, item.cost, item.growth, item.growthPct, {}];
            Ext.Object.each(data['farmroles'], function(key, value){
                row[6][key] = value['data'][index];
                row.push(value['data'][index] ? value['data'][index]['cost'] : undefined);
            });
            res.push(row);
        });
        return res;
    },
    
    getProjectId: function() {
        var f = this.down('#projectId');
        return f ? f.getValue() : '';
    },
    
    refresh: function() {
        var me = this,
            minInst = {hourlyRate: 0, dailyRate: 0, count: 0},
            maxInst = {hourlyRate: 0, dailyRate: 0, count: 0},
            runInst = {hourlyRate: 0, dailyRate: 0, count: 0},
            unsupportedRolesField = me.down('#unsupportedRoles'),
            unsupportedRoles = [];
        (this.farmRolesStore.snapshot || this.farmRolesStore.data).each(function(role){
            var hourlyRate = role.get('hourly_rate'),
                settings = role.get('settings', true),
                runInstCount = role.get('running_servers'),
                minInstCount = settings['scaling.min_instances'],
                maxInstCount = settings['scaling.max_instances'];
                
            minInst['count'] += minInstCount*1;
            minInst['hourlyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*minInstCount, 2) : 0;
            minInst['dailyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*minInstCount*24, 2) : 0;

            maxInst['count'] += maxInstCount*1;
            maxInst['hourlyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*maxInstCount, 2) : 0;
            maxInst['dailyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*maxInstCount*24, 2) : 0;

            runInst['count'] += runInstCount*1;
            runInst['hourlyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*runInstCount, 2) : 0;
            runInst['dailyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*runInstCount*24, 2) : 0;
            if (Ext.Array.contains(me['analyticsData']['unsupportedClouds'], role.data.platform)) {
                unsupportedRoles.push(role.data.alias);
            }
        });
        var chart = this.down('#currentRateChart');
        chart.maxValue = Math.max(maxInst['dailyRate'], runInst['dailyRate']);
        if (chart.maxValue) {
            chart.minLabelValue = minInst['dailyRate'];
            chart.maxLabelValue = maxInst['dailyRate'];
        } else {
            chart.maxValue = 3
            chart.minLabelValue = 1;
            chart.maxLabelValue = 2;
        }
        chart.store.loadData([[chart.maxValue > 0 ? runInst['dailyRate']*100/chart.maxValue : 0]]);

        this.down('#currentRate').update(runInst);
        this.down('#minMaxRate').update({max: maxInst, min: minInst});
        if (unsupportedRoles.length > 0) {
            unsupportedRolesField.setValue('Cost data is unavailable for the following roles: <b>' + unsupportedRoles.join(', ') + '</b>');
            unsupportedRolesField.show();
        } else {
            unsupportedRolesField.hide();
        }
    }
});

Ext.define('Ext.chart.axis.GaugeMinMax', {
    extend: 'Ext.chart.axis.Gauge',
    alias: 'axis.gaugeminmax',
    steps: 2,
    minimum: 0,
    maximum: 100,

    drawLabel: function() {
        var me = this,
            chart = me.chart,
            surface = chart.surface,
            bbox = chart.chartBBox,
            centerX = bbox.x + (bbox.width / 2),
            centerY = bbox.y + bbox.height,
            margin = me.margin || 10,
            rho = Math.min(bbox.width, 2 * bbox.height) /2 + 2 * margin,
            labelArray = [], label,
            maxValue = chart.maxValue,
            minLabelValue = chart.minLabelValue,
            maxLabelValue = chart.maxLabelValue,
            pi = Math.PI,
            cos = Math.cos,
            sin = Math.sin,
            reverse = me.reverse,
            labels = ['min', 'max'],
            visibleLabels,
            labelCfg = {
                type: 'text',
                'text-anchor': 'middle',
                'stroke-width': 0.2,
                zIndex: 10,
                stroke: '#333'
            };
        if (maxLabelValue / maxValue - minLabelValue / maxValue < .1) {
            visibleLabels = ['max'];
        } else {
            visibleLabels = ['min', 'max'];
        }
        if (!this.labelArray) {
            Ext.each(labels, function(label){
                var labelValue = label === 'min' ? minLabelValue : maxLabelValue;
                label = surface.add(Ext.apply({
                    text: label,
                    x: centerX + rho * cos(labelValue / maxValue * pi - pi),
                    y: centerY + rho * sin(labelValue / maxValue * pi - pi)
                }, labelCfg));
                label.setAttributes({
                    hidden: !Ext.Array.contains(visibleLabels, label)
                }, true);
                labelArray.push(label);
            });
        }
        else {
            labelArray = this.labelArray;
            Ext.each(labels, function(label, i){
                var labelValue = label === 'min' ? minLabelValue : maxLabelValue;
                labelArray[i].setAttributes({
                    hidden: !Ext.Array.contains(visibleLabels, label),
                    text: label,
                    x: centerX + rho * cos(labelValue / maxValue * pi - pi),
                    y: centerY + rho * sin(labelValue / maxValue * pi - pi)
                }, true);
            });
        }
        this.labelArray = labelArray;
    },

    drawAxis: function(init) {
        var chart = this.chart,
            surface = chart.surface,
            bbox = chart.chartBBox,
            centerX = bbox.x + (bbox.width / 2),
            centerY = bbox.y + bbox.height,
            margin = this.margin || 10,
            rho = Math.min(bbox.width, 2 * bbox.height) /2 + margin,
            sprites = [], sprite,
            steps = this.steps,
            i, pi = Math.PI,
            cos = Math.cos,
            sin = Math.sin,
            maxValue = chart.maxValue,
            minLabelValue = chart.minLabelValue,
            maxLabelValue = chart.maxLabelValue;

        if (this.margin >= 0) {
            if (!this.sprites) {
                //draw circles
                Ext.each(['min', 'max'], function(label){
                    var labelValue = label === 'min' ? minLabelValue : maxLabelValue;
                    sprite = surface.add({
                        type: 'path',
                        path: ['M', centerX + (rho - margin) * cos(labelValue / maxValue * pi - pi),
                                    centerY + (rho - margin) * sin(labelValue / maxValue * pi - pi),
                                    'L', centerX + rho * cos(labelValue / maxValue * pi - pi),
                                    centerY + rho * sin(labelValue / maxValue * pi - pi), 'Z'],
                        stroke: '#ccc'
                    });
                    
                    sprite.setAttributes({
                        hidden: false
                    }, true);
                    sprites.push(sprite);
                });
            } else {
                sprites = this.sprites;
                //draw circles
                Ext.each(['min', 'max'], function(label, i){
                    var labelValue = label === 'min' ? minLabelValue : maxLabelValue;
                    sprites[i].setAttributes({
                        path: ['M', centerX + (rho - margin) * cos(labelValue / maxValue * pi - pi),
                                    centerY + (rho - margin) * sin(labelValue / maxValue * pi - pi),
                                    'L', centerX + rho * cos(labelValue / maxValue * pi - pi),
                                    centerY + rho * sin(labelValue / maxValue * pi - pi), 'Z'],
                        stroke: '#ccc'
                    }, true);
                });
            }
        }

        this.sprites = sprites;
        this.drawLabel();
        if (this.title) {
            this.drawTitle();
        }
    }

});

Ext.define('Scalr.ui.FarmBuilderRoleCostMetering', {
	extend: 'Ext.container.Container',
	alias: 'widget.farmrolecostmetering',

    cls: 'x-container-fieldset',

    initComponent: function() {
        var me = this,
            //data = me['analyticsData'],
            //farmCostMeteringData = data['farmCostMetering'],
            items = [];
        me.callParent();
        items.push({
            xtype: 'label',
            text: Scalr.flags['analyticsEnabled'] ? 'Cost metering' : 'Scaling',
            cls: 'x-fieldset-subheader',
            hideOnMinify: true
        },{
            xtype: 'container',
            anchor: '100%',
            layout: 'hbox',
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Min&nbsp;instances',
                labelWidth: 90,
                width: 132,
                margin: '0 18 0 0',
                name: 'min_instances',
                hideOnDisabled: true,
                listeners: {
                    change: function(comp, value) {
                        comp.up('#maintab').onParamChange(comp.name, value);
                        if (Scalr.flags['analyticsEnabled']) {
                            this.up('#costmetering').updateRates();
                        }
                    }
                }
            },{
                xtype: 'textfield',
                fieldLabel: 'Max&nbsp;instances',
                labelWidth: 90,
                width: 132,
                margin: '0 18 0 0',
                name: 'max_instances',
                hideOnDisabled: true,
                listeners: {
                    change: function(comp, value) {
                        comp.up('#maintab').onParamChange(comp.name, value);
                        if (Scalr.flags['analyticsEnabled']) {
                            this.up('#costmetering').updateRates();
                        }

                    }
                }
            },{
                xtype: 'displayfield',
                fieldLabel: 'Running&nbsp;instances',
                labelWidth: 110,
                value: 0,
                name: 'running_servers',
                renderer: function(value) {
                    var html, tip;
                    if (value.suspended_servers > 0) {
                        tip = 'Running servers: <span style="color:#00CC00; cursor:pointer;">' + (value.running_servers || 0) + '</span>' +
                              (value.suspended_servers > 0 ? '<br/>' + (value['base.consider_suspended'] === 'running' ? 'Including' : 'Not including') + ' <span style="color:#4DA6FF;">' + value.suspended_servers + '</span> Suspended server(s)' : '');
                    }
                    html = '<span data-anchor="right" data-qalign="r-l" data-qtip="' + (tip ? Ext.String.htmlEncode(tip) : '') + '" data-qwidth="270">' +
                           '<span style="color:#00CC00; cursor:pointer;">' + (value.running_servers || 0) + '</span>' +
                           (value.suspended_servers > 0 ? ' (<span style="color:#4DA6FF;">' + (value.suspended_servers || 0) + '</span>)' : '')+
                            '</span>';
                    return value.running_servers > 0 ? '<a href="#">' + html + '</a>' : html;
                },
                listeners: {
                    boxready: function() {
                        this.inputEl.on('click', function(e) {
                            var link = document.location.href.split('#'),
                                farmRoleId = this.up('#maintab').currentRole.get('farm_role_id');
                            if (farmRoleId) {
                                window.open(link[0] + '#/servers/view?farmId=' + tabParams['farmId'] + '&farmRoleId=' + farmRoleId);
                            }
                            e.preventDefault();
                        }, this);
                    }
                }
            }]
        });
        if (Scalr.flags['analyticsEnabled']) {
            items.push({
                xtype: 'container',
                anchor: '100%',
                layout: 'hbox',
                hideOnMinify: true,
                items: [{
                    xtype: 'component',
                    itemId: 'minRate',
                    width: 132,
                    margin: '0 18 0 0',
                    hideOnDisabled: true,
                    tpl:
                        '<tpl if="hourlyRate !== null">' +
                            '<div class="daily-rate">{[this.currency2(values.dailyRate)]} <span class="small">per day</span></div><div class="hourly-rate">({[this.currency2(values.hourlyRate)]} per hour)</div>' +
                        '<tpl else><div class="daily-rate"><span class="small">N/A</span></div>'+
                        '</tpl>'
                },{
                    xtype: 'component',
                    itemId: 'maxRate',
                    width: 132,
                    margin: '0 18 0 0',
                    hideOnDisabled: true,
                    tpl:
                        '<tpl if="hourlyRate !== null">' +
                            '<div class="daily-rate">{[this.currency2(values.dailyRate)]} <span class="small">per day</span></div><div class="hourly-rate">({[this.currency2(values.hourlyRate)]} per hour)</div>' +
                        '<tpl else><div class="daily-rate"><span class="small">N/A</span></div>'+
                        '</tpl>'
                },{
                    xtype: 'container',
                    items: [{
                        xtype: 'chart',
                        width: 140,
                        height: 70,
                        animate: true,
                        hidden: true,
                        store: Ext.create('Ext.data.ArrayStore', {
                            fields: ['value']
                        }),
                        insetPadding: 0,
                        axes: [{
                            type: 'gauge',
                            position: 'gauge',
                            minimum: 0,
                            maximum: 100,
                            steps: 1,
                            margin: 7
                        }],
                        series: [{
                            type: 'gauge',
                            field: 'value',
                            donut: 90,
                            colorSet: ['#F49D10', '#ddd']
                        }]
                    },{
                    xtype: 'component',
                    itemId: 'currentRate',
                    //margin: '-70 0 0',
                    width: 140,
                    tpl:
                        '<tpl if="hourlyRate !== null">' +
                            '<div class="daily-rate">{[this.currency2(values.dailyRate)]} <span class="small">per day</span></div><div class="hourly-rate">({[this.currency2(values.hourlyRate)]} per hour)</div>' +
                        '<tpl else><div class="daily-rate"><span class="small">N/A</span></div>'+
                        '</tpl>'
                    }]
                }]
            });
        }
        me.add(items);
    },

    onScalingDisabled: function(disabled) {
        this.suspendLayouts();
        Ext.Array.each(this.query('[hideOnDisabled]'), function(item){
            item.setVisible(!disabled);
            item.forceHidden = disabled;
        });
        this.resumeLayouts(true);
    },
    updateRates: function() {
        var hourlyRate = this.up('#maintab').currentRole.get('hourly_rate'),
            minInstCount = this.down('[name="min_instances"]').getValue(),
            maxInstCount = this.down('[name="max_instances"]').getValue(),
            role = this.up('#maintab').currentRole,
            runInstCount = role.get('running_servers');
        if (Ext.Array.contains(this.up('farmroleedit')['moduleParams']['analytics']['unsupportedClouds'], role.data.platform)) {
            hourlyRate = null;
        }
        this.suspendLayouts();
        runInstCount = runInstCount > maxInstCount ? maxInstCount : runInstCount;
        this.down('#minRate').update({
            hourlyRate: minInstCount > 0 && Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*minInstCount, 2) : null,
            dailyRate: minInstCount > 0 && Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*minInstCount*24, 2) : null
        });
        this.down('#maxRate').update({
            hourlyRate: maxInstCount > 0 && Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*maxInstCount, 2) : null,
            dailyRate: maxInstCount > 0 && Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*maxInstCount*24, 2) : null
        });
        this.down('#currentRate').update({
            hourlyRate: Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*runInstCount, 2) : null,
            dailyRate: Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*runInstCount*24, 2) : null
        });
        this.down('chart').store.loadData([[maxInstCount > 0 ? runInstCount*100/maxInstCount : 0]]);
        this.resumeLayouts(true);
    }

});

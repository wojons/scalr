Ext.define('Scalr.ui.FarmBuilderFarmCostMetering', {
	extend: 'Ext.form.FieldSet',
	alias: 'widget.farmcostmetering',

    cls: 'x-fieldset-separator-none',

    setValue: function(value) {
        var me = this,
            farmCostMeteringData, seriesList, comp;
        me['analyticsData'] = value;
        farmCostMeteringData = me['analyticsData']['farmCostMetering'];
        seriesList = farmCostMeteringData ? Ext.Object.getKeys(farmCostMeteringData['farmroles']) : [];

        me.down('#ccName').setValue(me['analyticsData'] ? me['analyticsData']['costCenterName'] : null);
        me.down('#totalCost').update({cost: farmCostMeteringData ? farmCostMeteringData['totals']['cost'] : ''});
        me.down('#noCostData').setVisible(!farmCostMeteringData);

        comp = me.down('#projectId');
        comp.store.load({data: me['analyticsData']['projects']});
        comp.findPlugin('comboaddnew').setDisabled(!Scalr.isAllowed('ANALYTICS_ACCOUNT', 'manage-projects') || me['analyticsData']['costCenterLocked'] == 1);

        comp = me.down('#chartWrap');
        comp.remove(comp.down('#chart'));
        if (farmCostMeteringData) {
            me.down('#noCostData').hide();
            comp.items.first().show();
            comp.items.insert(comp.items.length - 1, Ext.widget({
                xtype: 'cartesian',
                itemId: 'chart',
                height: 140,
                theme: 'scalr',
                insetPadding: '10 4 10 10',
                store: Ext.create('Ext.data.ArrayStore', {
                    fields: Ext.Array.merge([{
                       name: 'datetime',
                       type: 'date',
                       convert: function(v, record) {
                           return Scalr.utils.Quarters.getDate(v, true);
                       }
                    }, 'xLabel', 'label', 'cost', {name: 'growth', defaultValue: null}, {name: 'growthPct', defaultValue: null}, 'extrainfo'], seriesList),
                    data: me.prepareDataForChartStore()
                }),
                axes: [{
                    type: 'numeric',
                    position: 'left',
                    fields: Ext.Array.merge(seriesList, ['cost']),
                    renderer: function(value, layout){
                        return value > 0 ? Ext.util.Format.currency(value, null, layout.majorTicks.to > 3 ? 0 : 2) : '';
                    },
                    majorTickSteps: 3
                },{
                    type: 'category',
                    position: 'bottom',
                    fields: ['xLabel'],
                    renderer: function(label, layout) {
                        var index = Ext.Array.indexOf(layout.data, label);
                        return index === 0 || index === layout.data.length - 1 ? label : '';
                    }
                }],
                series: [{
                    type: 'bar',
                    axis: 'bottom',
                    xField: 'xLabel',
                    yField: seriesList,
                    stacked: true,
                    renderer: function(sprite, config, rendererData, index){
                        var color = '#' + Scalr.utils.getColorById(sprite.getField());
                        return  {
                            fillStyle: color,
                            strokeStyle: color
                        };
                    },
                    tooltip: {
                        cls: 'x-tip-light',
                        trackMouse: true,
                        hideDelay: 0,
                        showDelay: 0,
                        tpl:
                            '{[this.itemCost(values, false)]}' +
                            '<div class="x-costmetering-hours">' +
                                '<tpl foreach="hours">' +
                                    '<div class="title">{$}</div>' +
                                    '<table>' +
                                        '<tr><th>Hours</th><th>Min</th><th>Avg</th><th>Max</th></tr>' +
                                        '<tr><td>{[values.hours]}</td><td>{[values.min]}</td><td>{[values.avg]}</td><td>{[values.max]}</td></tr>' +
                                    '</table>' +
                                '</tpl>' +
                            '</div>',
                        renderer: function(record, item) {
                            var info = record.get('extrainfo')[item.field];
                            this.update({
                                id: item.yField,
                                name: farmCostMeteringData['farmroles'][item.field]['name'],
                                label: record.get('xLabel'),
                                cost: info['cost'],
                                costPct: info['costPct'],
                                interval: 'day',
                                hours: info['hours'],
                                color: Scalr.utils.getColorById(item.field)
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
                    style: {
                        strokeStyle: '#00468c'
                    },
                    highlight: {
                        fillStyle: '#00468c',
                        strokeStyle: '#00468c'
                    },
                    //smooth: true,
                    marker: {
                        type: 'circle',
                        radius: 3,
                        fill: '#00468c'
                    },
                    tips: {
                        cls: 'x-tip-light',
                        trackMouse: true,
                        hideDelay: 0,
                        showDelay: 0,
                        tpl:
                            '<div style="text-align:center"><b>{label}</b></div>' +
                            '<div class="x-costmetering-farmroles">' +
                                '<table>' +
                                    '<tr>'+
                                        '<th>Total spend</th>' +
                                        '<th>{[this.currency2(values.cost)]}</th>' +
                                        '<th><tpl if="growth">{[this.pctLabel(values.growth, values.growthPct, null, false, \'invert\', false)]}</tpl></th>' +
                                    '</tr>' +
                                    '<tpl foreach="farmroles">' +
                                        '<tr>' +
                                            '<td><span class="x-semibold" style="color:#{color}">&nbsp;{name}&nbsp;</span></td>' +
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
                                        color: Scalr.utils.getColorById(key)
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
            }));
        } else {
            comp.items.first().hide();
            me.down('#noCostData').show();
        }
    },
    initComponent: function() {
        var me = this,
            items = [];
        me.callParent(arguments);
        if (Scalr.flags['analyticsEnabled']) {
            items.push({
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'combo',
                    store: {
                        fields: [ 'projectId', 'name', {name: 'budgetRemain', defaultValue: null}, {name: 'description', convert: function(v, record) {
                            return record.data.name + '  (' + (record.data.budgetRemain === null ? 'budget is not set' : 'Remaining budget ' + Ext.util.Format.currency(record.data.budgetRemain)) + ')';
                        }} ],
                        proxy: 'object',
                        sorters: [{
                            property: 'name',
                            transform: function(value){
                                return value.toLowerCase();
                            }
                        }]
                    },
                    flex: 1,
                    maxWidth: 580,
                    editable: true,
                    selectOnFocus: true,
                    restoreValueOnBlur: true,
                    autoSetSingleValue: true,
                    queryMode: 'local',
                    anyMatch: true,
                    valueField: 'projectId',
                    displayField: 'description',
                    fieldLabel: 'Project',
                    labelWidth: 60,
                    name: 'projectId',
                    itemId: 'projectId',
                    plugins: [{
                        ptype: 'comboaddnew',
                        pluginId: 'comboaddnew',
                        url: '/analytics/projects/add'
                    }],
                    listConfig: {
                        cls: 'x-boundlist-alt',
                        tpl:
                            '<tpl for=".">' +
                                '<div class="x-boundlist-item" style="height: auto; width: auto; max-width: 900px;">' +
                                    '<div><span class="x-semibold">{name}</span>' +
                                        '&nbsp;&nbsp;<span style="font-size: 11px;"><tpl if="budgetRemain!==null">Remaining budget {[this.currency2(values.budgetRemain)]}<tpl else><i>Budget is not set</i></tpl></span>' +
                                    '</div>' +
                                '</div>' +
                            '</tpl>'
                    },
                },{
                    xtype: 'displayfield',
                    itemId: 'ccName',
                    isFormField: false,
                    hidden: true,
                    fieldLabel: 'Cost center',
                    margin: '0 0 0 24',
                    labelWidth: 90
                }]
            });

            items.push({
                xtype: 'displayfield',
                itemId: 'unsupportedRoles',
                hidden: true,
                isFormField: false,
                anchor: '100%',
                maxWidth: 600,
                margin: '12 0',
                cls: 'x-form-field-warning'
            },{
                xtype: 'container',
                flex: 1,
                layout: 'hbox',
                maxWidth: 580,
                items: [{
                    xtype: 'container',
                    width: 180,
                    margin: '22 28 12 0',
                    itemId: 'currentRateWrapper',
                    items: [{
                        xtype: 'label',
                        cls: 'x-form-item-label-default',
                        text: 'Current spend rate'
                    },{
                        xtype: 'component',
                        itemId: 'currentRate',
                        style: 'text-align:center;z-index:7',
                        margin: '-74 0 8',
                        tpl: '<div class="x-semibold" style="font-size:130%" data-qtip="{[this.currency2(values.hourlyRate)]} per hour">{[this.currency2(values.dailyRate, true)]}</div>per day'
                    },{
                        xtype: 'component',
                        itemId: 'minMaxRate',
                        style: 'text-align:center',
                        tpl: 'min:&nbsp;<span data-qtip="{[this.currency2(values.min.hourlyRate)]} per hour">{[this.currency2(values.min.dailyRate)]}</span>, &nbsp;'+
                             'max:&nbsp;<span data-qtip="{[this.currency2(values.max.hourlyRate)]} per hour">{[this.currency2(values.max.dailyRate)]}</span>'
                    }]
                },{
                    xtype: 'container',
                    flex: 1,
                    margin: '18 0 0 0',
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },
                    itemId: 'chartWrap',
                    items: [{
                        xtype: 'container',
                        hidden: true,
                        layout: 'hbox',
                        items: [{
                            xtype: 'label',
                            cls: 'x-form-item-label-default',
                            text: 'Last 7 days'
                        },{
                            xtype: 'component',
                            itemId: 'totalCost',
                            flex: 1,
                            cls: 'x-semibold',
                            style: 'text-align:right;font-size:130%',
                            tpl: '{[values.cost?this.currency2(values.cost, true):\'\']}'
                        }]
                    },{
                        xtype: 'component',
                        itemId: 'noCostData',
                        style: 'text-align:center;font-style:italic',
                        margin: '58 0',
                        html: ''// 'No cost data'
                    }]

                }]
            });
        }
        me.add(items);
    },

    prepareDataForChartStore: function() {
        var me = this,
            data = me['analyticsData']['farmCostMetering'],
            res = [];
        Ext.Array.each(data['timeline'], function(item, index){
            //datetime, onchart, label, cost, extrainfo, events, series1data, series2data....
            var row = [item.datetime, item.onchart || index, item.label, item.cost, item.growth, item.growthPct, {}];
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

    refresh: function(farmRoles) {
        if (!Scalr.flags['analyticsEnabled']) return;
        var me = this,
            minInst = {hourlyRate: 0, dailyRate: 0, count: 0},
            maxInst = {hourlyRate: 0, dailyRate: 0, count: 0},
            runInst = {hourlyRate: 0, dailyRate: 0, count: 0},
            unsupportedRolesField = me.down('#unsupportedRoles'),
            unsupportedRoles = [],
            farmRolesCollection;
        if (farmRoles) {
            farmRolesCollection = new Ext.util.MixedCollection();
            farmRolesCollection.addAll(farmRoles);
        } else {
            farmRolesCollection = this.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.getUnfiltered();
        }
        farmRolesCollection.each(function(role){
            var hourlyRate,
                settings,
                runInstCount,
                minInstCount,
                maxInstCount,
                platform,
                baseConsiderSuspended,
                suspendedInstCount,
                alias;
            if (role.isModel) {
                hourlyRate = role.get('hourly_rate');
                settings = role.get('settings', true);
                baseConsiderSuspended = settings['base.consider_suspended'] || 'running';
                suspendedInstCount = role.get('suspended_servers')*1;
                runInstCount = role.get('running_servers')*1 - (baseConsiderSuspended === 'running' && suspendedInstCount ? suspendedInstCount : 0);
                minInstCount = settings['scaling.enabled'] == 1 ? settings['scaling.min_instances']*1 : 0;
                maxInstCount = settings['scaling.enabled'] == 1 ? settings['scaling.max_instances']*1 : runInstCount;
                platform = role.data.platform;
                alias = role.data.alias;
            } else {
                hourlyRate = role['hourly_rate'];
                baseConsiderSuspended = role['base.consider_suspended'] || 'running';
                suspendedInstCount = role['suspended_servers']*1;
                runInstCount = role['running_servers']*1 - (baseConsiderSuspended === 'running' && suspendedInstCount ? suspendedInstCount : 0);
                minInstCount = role['scaling.enabled'] == 1 ? role['scaling.min_instances']*1 : 0;
                maxInstCount = role['scaling.enabled'] == 1 ? role['scaling.max_instances']*1 : runInstCount;
                platform = role['platform'];
                alias = role['alias'];
            }
            if (suspendedInstCount) {
                minInstCount = Math.min(minInstCount, runInstCount);
            }
            maxInstCount = Math.max(maxInstCount, runInstCount);
            
            minInst['count'] += minInstCount;
            minInst['hourlyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*minInstCount, 2) : 0;
            minInst['dailyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*minInstCount*24, 2) : 0;

            maxInst['count'] += maxInstCount;
            maxInst['hourlyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*maxInstCount, 2) : 0;
            maxInst['dailyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*maxInstCount*24, 2) : 0;

            runInst['count'] += runInstCount;
            runInst['hourlyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*runInstCount, 2) : 0;
            runInst['dailyRate'] += Ext.isNumeric(hourlyRate) ? Ext.util.Format.round(hourlyRate*runInstCount*24, 2) : 0;
            if (Ext.Array.contains(me['analyticsData']['unsupportedClouds'], platform)) {
                unsupportedRoles.push(alias);
            }
        });

        var chartWrapper = this.down('#currentRateWrapper');
        //chartWrapper.setVisible(farmRoles.length > 0);
        var chart = this.down('#currentRateChart');
        if (chart) {
            chartWrapper.remove(chart);
        }
        chart = chartWrapper.insert(1, {
            xtype: 'polar',
            theme: 'scalr',
            itemId: 'currentRateChart',
            height: 140,
            insetPaddding: 0,
            store: Ext.create('Ext.data.ArrayStore', {
                fields: ['value']
            }),
            axes: [{
                type: 'numeric',
                position: 'gauge',
                hidden: true
            }],
            series: [{
                type: 'gauge',
                field: 'value',
                donut: 80,
                totalAngle: Math.PI,
                needleLength: 100,
                colors: ['#F49D10', '#ddd']
            }]
        });
        chart.maxValue = Math.max(maxInst['dailyRate'], runInst['dailyRate']);
        if (chart.maxValue) {
            chart.minLabelValue = minInst['dailyRate'];
            chart.maxLabelValue = maxInst['dailyRate'];
        } else {
            chart.maxValue = 3;
            chart.minLabelValue = 1;
            chart.maxLabelValue = 2;
        }
        Ext.apply(chart.getAxes()[0], {
            customLabels: [{
                value: chart.minLabelValue,
                text: 'min'
            },{
                value: chart.maxLabelValue,
                text: 'max'
            }],
            maxValue: chart.maxValue
        });

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


Ext.define('Scalr.ui.FarmBuilderRoleCostMetering', {
	extend: 'Ext.container.Container',
	alias: 'widget.farmrolecostmetering',

    initComponent: function() {
        var me = this,
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
            defaults: {
                xtype: 'container',
                width: 147,
                margin: '0 18 0 0',
                layout: {
                    type: 'vbox',
                    align: 'middle'
                },
            },
            items: [{
                items: [{
                    xtype: 'label',
                    cls: 'x-form-item-label-default',
                    hideOnMinify: true,
                    anchor: '100%',
                    html: 'Min&nbsp;instances'
                },{
                    xtype: 'fieldcontainer',
                    layout: 'fit',
                    _fieldLabel: 'Min&nbsp;instances',
                    labelWidth: 105,
                    width: 42,
                    showLabelOnMinify: true,
                    items: [{
                        xtype: 'textfield',
                        name: 'min_instances',
                        fieldStyle: 'text-align:center',
                        hideOnDisabled: true,
                        vtype: 'num',
                        listeners: {
                            change: function(comp, value) {
                                comp.up('#maintab').onParamChange(comp.name, value);
                                if (Scalr.flags['analyticsEnabled']) {
                                    this.up('#costmetering').updateRates();
                                }
                            }
                        }
                    },{
                        xtype: 'component',
                        hidden: true,
                        showOnDisabled: true,
                        style: 'text-align:center;margin-top:5px',
                        html: 'N/A'
                    }]
                }]
            },{
                items: [{
                    xtype: 'label',
                    cls: 'x-form-item-label-default',
                    hideOnMinify: true,
                    anchor: '100%',
                    html: 'Max&nbsp;instances'
                },{
                    xtype: 'fieldcontainer',
                    layout: 'fit',
                    _fieldLabel: 'Max&nbsp;instances',
                    labelWidth: 105,
                    width: 42,
                    showLabelOnMinify: true,
                    items: [{
                        xtype: 'textfield',
                        name: 'max_instances',
                        fieldStyle: 'text-align:center',
                        hideOnDisabled: true,
                        vtype: 'num',
                        listeners: {
                            change: function(comp, value) {
                                comp.up('#maintab').onParamChange(comp.name, value);
                                if (Scalr.flags['analyticsEnabled']) {
                                    this.up('#costmetering').updateRates();
                                }
                            }
                        }
                    },{
                        xtype: 'component',
                        hidden: true,
                        showOnDisabled: true,
                        style: 'text-align:center;margin-top:5px',
                        html: 'N/A'
                    }]
                }]
            },{
                margin: 0,
                items: [{
                    xtype: 'label',
                    cls: 'x-form-item-label-default',
                    hideOnMinify: true,
                    anchor: '100%',
                    html: 'Running&nbsp;instances'
                },{
                    xtype: 'displayfield',
                    _fieldLabel: 'Running',
                    labelWidth: 60,
                    value: 0,
                    name: 'running_servers',
                    fieldStyle: 'text-align:center',
                    showLabelOnMinify: true,
                    renderer: function(value) {
                        var html, tip;
                        if (value.suspended_servers > 0) {
                            tip = 'Running servers: <span style="color:#00CC00; cursor:pointer;">' + (value.running_servers || 0) + '</span>' +
                                  (value.suspended_servers > 0 ? '<br/>' + (value['base.consider_suspended'] === 'running' ? 'Including' : 'Not including') + ' <span style="color:#4DA6FF;">' + value.suspended_servers + '</span> Suspended server(s)' : '');
                        }
                        html = '<span data-anchor="right" data-qalign="r-l" data-qtip="' + (tip ? Ext.String.htmlEncode(tip) : '') + '" data-qwidth="290">' +
                               '<span style="color:#00CC00; cursor:pointer;">' + (value.running_servers || 0) + '</span>' +
                               (value.suspended_servers > 0 ? '&nbsp;(<span style="color:#4DA6FF;">' + (value.suspended_servers || 0) + '</span>)' : '')+
                                '</span>';
                        return value.running_servers > 0 ? '<a href="#">' + html + '</a>' : html;
                    },
                    listeners: {
                        boxready: function() {
                            this.inputEl.on('click', function(e) {
                                var link = document.location.href.split('#'),
                                    tabParams = this.up('#farmDesigner').moduleParams.tabParams,
                                    farmRoleId = this.up('#maintab').currentRole.get('farm_role_id');
                                if (tabParams['farmId'] && farmRoleId) {
                                    window.open(link[0] + '#/servers?farmId=' + tabParams['farmId'] + '&farmRoleId=' + farmRoleId);
                                }
                                e.preventDefault();
                            }, this);
                        }
                    }
                }]
            }]
        });
        if (Scalr.flags['analyticsEnabled']) {
            items.push({
                xtype: 'container',
                anchor: '100%',
                layout: 'hbox',
                hideOnMinify: true,
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    width: 320,
                    margin: '0 18 0 0',
                    items: [{
                        xtype: 'component',
                        itemId: 'minRate',
                        flex: 1,
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
                        flex: 1,
                        hideOnDisabled: true,
                        tpl:
                            '<tpl if="hourlyRate !== null">' +
                                '<div class="daily-rate">{[this.currency2(values.dailyRate)]} <span class="small">per day</span></div><div class="hourly-rate">({[this.currency2(values.hourlyRate)]} per hour)</div>' +
                            '<tpl else><div class="daily-rate"><span class="small">N/A</span></div>'+
                            '</tpl>'
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
        Ext.Array.each(this.query('[showOnDisabled]'), function(item){
            item.setVisible(disabled);
        });
        this.resumeLayouts(true);
    },
    updateRates: function() {
        var role = this.up('#maintab').currentRole,
            hourlyRate = role.get('hourly_rate'),
            settings = role.get('settings', true),
            minInstCount = this.down('[name="min_instances"]').getValue()*1,
            maxInstCount = this.down('[name="max_instances"]').getValue()*1,
            runInstCount,
            baseConsiderSuspended = settings['base.consider_suspended'] || 'running';
        runInstCount = role.get('running_servers')*1 - (baseConsiderSuspended === 'running' && role.get('suspended_servers') ? role.get('suspended_servers')*1 : 0);
        if (Ext.Array.contains(this.up('#farmDesigner').moduleParams['analytics']['unsupportedClouds'], role.data.platform)) {
            hourlyRate = null;
        }
        this.suspendLayouts();
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
        this.resumeLayouts(true);
    }

});


/*Ext.define(null, {
    override: 'Ext.chart.axis.sprite.Axis',

    renderTicks: function (surface, ctx, layout, clipRect) {
        var me = this,
            attr = me.attr,
            customLabels = me.getAxis().customLabels,
            maxValue = me.getAxis().maxValue,
            majorTickSize = attr.majorTickSize;
         if (me.attr.position === 'gauge' && customLabels && maxValue) {
            var gaugeAngles = me.getGaugeAngles();
            Ext.each(customLabels, function(lbl) {
                var value = lbl.value * 100 / maxValue,
                    position = (value - attr.min) / (attr.max - attr.min + 1) * attr.totalAngle - attr.totalAngle + gaugeAngles.start;
                ctx.moveTo(attr.centerX + (attr.length) * Math.cos(position), attr.centerY + (attr.length) * Math.sin(position));
                ctx.lineTo(attr.centerX + (attr.length + majorTickSize) * Math.cos(position), attr.centerY + (attr.length + majorTickSize) * Math.sin(position));
            });
        } else {
            me.callParent(arguments);
        }
    },

     renderLabels: function (surface, ctx, layout, clipRect) {
        var me = this,
            attr = me.attr,
            customLabels = me.getAxis().customLabels,
            maxValue = me.getAxis().maxValue,
            label = me.getLabel(),
            lastBBox = null, bbox,
            majorTicks = layout.majorTicks;
        if (majorTicks && label && !label.attr.hidden) {
            if (me.attr.position === 'gauge' && customLabels && maxValue) {
                var gaugeAngles = me.getGaugeAngles();
                label.setAttributes({translationX: 0, translationY: 0}, true, true);
                label.applyTransformations();
                 label.setAttributes({
                    translationY: attr.centerY
                }, true, true);

                Ext.each(customLabels, function(lbl) {
                    var value = lbl.value * 100 / maxValue,
                        angle = (value - attr.min) / (attr.max - attr.min + 1) * attr.totalAngle - attr.totalAngle + gaugeAngles.start;
                    label.setAttributes({
                        text: lbl.text,
                        translationX: attr.centerX + (attr.length + 10) * Math.cos(angle) + 10,
                        translationY: attr.centerY + (attr.length + 10) * Math.sin(angle) - 2
                    }, true, true);
                    label.applyTransformations();
                    bbox = label.attr.matrix.transformBBox(label.getBBox(true));
                    if (lastBBox && !Ext.draw.Draw.isBBoxIntersect(bbox, lastBBox)) {
                        return;
                    }
                    surface.renderSprite(label);
                    lastBBox = bbox;

                });
            } else {
                me.callParent(arguments);
            }
        }
    },
})*/

Scalr.regPage('Scalr.ui.analytics.dashboard.view', function (loadPararams, moduleParams) {
    Scalr.utils.Quarters.days = moduleParams['quarters'];

	var reconfigurePage = function(params) {
        if (!panel.isVisible()) {
            panel.down('costanalyticsperiod').restorePreservedValue('quarter');
        }
	};

    var loadPeriodData = function(mode, startDate, endDate, quarter) {
        var requestParams = {
            mode: mode,
            startDate: Ext.Date.format(startDate, 'Y-m-d'),
            endDate: Ext.Date.format(endDate, 'Y-m-d')
        };
        Scalr.Request({
            processBox: {
                type: 'action',
                msg: 'Computing...'
            },
            url: '/analytics/dashboard/xGetPeriodData',
            params: requestParams,
            success: function (data) {
                var summary = panel.down('analyticsboxesenv'),
                    spends = panel.down('costanalyticsspendsenv');
                if (data) {
                    //Ext.apply(data, params);
                    //calculate top spenders
                    data['totals']['top'] = {};
                    Ext.Array.each(['clouds', 'farms'], function(type){
                        var top6 = new Ext.util.MixedCollection();
                        top6.addAll(data['totals'][type]);
                        top6.sort('cost', 'DESC');
                        data['totals']['top'][type] = top6.getRange(0,5);
                    });

                }
                summary.loadDataDeferred(mode, quarter, startDate, endDate, data);
                spends.loadDataDeferred(mode, quarter, startDate, endDate, data);
                spends.requestParams = requestParams;
            }
        });
    };

	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			menuTitle: 'Cost analytics',
            menuSubTitle: 'Farms',
            menuHref: '#/analytics/dashboard',
            menuFavorite: true,
			reload: false,
			maximize: 'all',
            leftMenu: {
                menuId: 'envanalytics',
                itemId: 'dashboard'
            }
		},
        stateId: 'panel-environment-analytics-dashboard',
        autoScroll: true,
        preserveScrollPosition: true,
        layout: 'anchor',
        bodyCls: 'x-panel-column-left-with-tabs x-container-fieldset',
        cls: 'x-costanalytics',
        listeners: {
            boxready: function() {
                this.down('costanalyticsperiod').restorePreservedValue('quarter');
            },
            applyparams: reconfigurePage
        },
        defaults: {
            maxWidth: 1300,
            minWidth: 1000
        },
		items: [{
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            items: [{
                xtype: 'costanalyticsperiod',
                simple: true,
                dailyModeEnabled: true,
                preservedValueId: 'environment',
                listeners: {
                    change: function(mode, startDate, endDate, quarter) {
                        loadPeriodData(mode, startDate, endDate, quarter);
                    }
                }
            },{
                xtype: 'tbfill'
            },{
                xtype: 'component',
                style: 'text-align:right;white-space:nowrap',
                html: '<div class="x-fieldset-subheader" style="margin-bottom:4px">' + moduleParams['envName'] + '</div><label class="x-label">Cost center:&nbsp;&nbsp;&nbsp;</label>' + moduleParams['ccName']
            }]
        },{
            xtype: 'container',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: {
                xtype: 'buttongroupfield',
                margin: '18 0 12',
                defaults: {
                    height: 42,
                    width: 120
                },
                value: 'clouds',
                items: [{
                    text: 'Cloud spend',
                    value: 'clouds'
                },{
                    text: 'Farm spend',
                    value: 'farms'
                }],
                listeners: {
                    change: function(comp, value) {
                        panel.down('analyticsboxesenv').setVisible(value === 'clouds');
                        panel.down('costanalyticsspendsenv').setType(value);
                    }
                }
            }
        },{
            xtype: 'analyticsboxesenv',
            margin: '0 0 24',
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
            }
        },{
            xtype: 'costanalyticsspendsenv',
            level: 'environment'
        }]
    });

    return panel;
});

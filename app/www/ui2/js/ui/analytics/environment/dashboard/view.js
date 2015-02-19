Scalr.regPage('Scalr.ui.analytics.environment.dashboard.view', function (loadPararams, moduleParams) {
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
            url: '/analytics/environment/dashboard/xGetPeriodData',
            params: requestParams,
            success: function (data) {
                var summaryTab = panel.down('#summary'),
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
                summaryTab.loadDataDeferred(mode, quarter, startDate, endDate, data);
                spends.loadDataDeferred(mode, quarter, startDate, endDate, data);
                spends.requestParams = requestParams;
            }
        });
    };

	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			title: 'Environment cost analytics',
			reload: false,
			maximize: 'all',
            leftMenu: {
                menuId: 'envanalytics',
                itemId: 'dashboard'
            }
		},
        autoScroll: true,
        preserveScrollPosition: true,
        layout: 'anchor',
        bodyCls: 'x-panel-column-left x-container-fieldset',
        cls: 'x-costanalytics',
        listeners: {
            boxready: function() {
                this.down('costanalyticsperiod').restorePreservedValue('quarter');
            },
            applyparams: reconfigurePage
        },
		items: [{
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'middle'
            },
            maxWidth: 1400,
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
            xtype: 'tabpanel',
            itemId: 'tabs',
            margin: '22 0 0',
            cls: 'x-tabs-light',
            maxWidth: 1400,
            listeners: {
                tabchange: function(panel, newtab, oldtab){
                    var comp = panel.down('costanalyticsspendsenv');
                    newtab.add(comp);
                    comp.setType(newtab.value);
                }
            },
            items: [{
                xtype: 'container',
                tab: true,
                itemId: 'summary',
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
                    this.down('analyticsboxesenv').loadData(mode, quarter, startDate, endDate, data);
                },
                tabConfig: {
                    title: 'Cloud spend'
                },
                value: 'clouds',
                layout: 'anchor',
                items: [{
                    xtype: 'analyticsboxesenv'
                },{
                    xtype: 'costanalyticsspendsenv',
                    cls: 'x-container-fieldset',
                    level: 'environment'
                }]
            },{
                xtype: 'container',
                tab: true,
                tabConfig: {
                    title: 'Farm spend'
                },
                layout: 'anchor',
                value: 'farms'
            }]
        }]
    });
    
    return panel;
});

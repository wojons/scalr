Scalr.regPage('Scalr.ui.analytics.account.environments.view', function (loadParams, moduleParams) {
    Scalr.utils.Quarters.days = moduleParams['quarters'];
    var requestParams, refreshStoreOnReconfigure;

	var reconfigurePage = function(params) {
        var envId = params.envId;
        cb = function() {
            selectEnvId = function() {
                if (envId) {
                    dataview.getSelectionModel().deselectAll();
                    var record =  store.getById(envId);
                    if (record) {
                        dataview.getNode(record, true, false).scrollIntoView(dataview.el);
                        dataview.select(record);
                    }
                } else {
                    var selection = dataview.getSelectionModel().getSelection(),
                        periodField = panel.down('costanalyticsperiod');
                    if (selection.length) {
                        periodField.restorePreservedValue();
                    }
                }
            };
            if (refreshStoreOnReconfigure) {
                refreshStoreOnReconfigure = false;
                dataview.getSelectionModel().deselectAll();
                store.on('load', selectEnvId, this, {single: true});
                store.reload();
            } else {
                selectEnvId();
            }
        }
        if (panel.isVisible()) {
            cb();
        } else {
            panel.on('activate', cb, panel, {single: true});

        }

	};

    var loadPeriodData = function(params, mode, startDate, endDate, quarter) {
        requestParams = Ext.apply({}, params);
        Ext.apply(requestParams, {
            mode: mode,
            startDate: Ext.Date.format(startDate, 'Y-m-d'),
            endDate: Ext.Date.format(endDate, 'Y-m-d')
        });

        Scalr.Request({
            processBox: {
                type: 'action',
                msg: 'Computing...'
            },
            url: '/analytics/account/environments/xGetPeriodData',
            params: requestParams,
            success: function (data) {
                var summaryTab = panel.down('#summary'),
                    spends = panel.down('costanalyticsspendsenv');
                Ext.apply(data, params);
                //calculate top spenders
                data['totals']['top'] = {};
                Ext.Array.each(['clouds', 'farms'], function(type){
                    var top6 = new Ext.util.MixedCollection();
                    top6.addAll(data['totals'][type]);
                    top6.sort('cost', 'DESC');
                    data['totals']['top'][type] = top6.getRange(0,5);
                });
                summaryTab.loadDataDeferred(mode, quarter, startDate, endDate, data);
                spends.loadDataDeferred(mode, quarter, startDate, endDate, data);
                spends.requestParams = requestParams;
                panel.fireEvent('afterload');
            }
        });
    };

	var store = Ext.create('store.store', {
        data: moduleParams['environments'],
		fields: [
            'envId',
            'name',
            'description',
            'growth',
            'growthPct',
            'topSpender',
            'ccName',
            {name: 'periodTotal', type: 'float'},
            {name: 'id', convert: function(v, record){;return record.data.envId;}}
		],
		sorters: [{
            property: 'periodTotal',
            direction: 'DESC'
		}],
		proxy: {
			type: 'ajax',
			url: '/analytics/account/environments/xList',
            reader: {
                type: 'json',
                root: 'environments'
            }
		}
	});

    var dataview = Ext.create('widget.costanalyticslistview', {
        store: store,
		listeners: {
            refresh: function(view){
                var record = view.getSelectionModel().getLastSelected(),
                    form = panel.down('#form');
                if (record && record !== form.currentRecord) {
                    form.loadRecord(view.store.getById(record.get('id')));
                }
            },
            beforeitemclick: function(view, record, item, index, e) {
                if (e.getTarget('.topspender-link')) {
                    var f = panel.down('costanalyticsperiod');
                    if (!f.isCurrentValue('month')) {
                        panel.on('afterload', function(){
                            this.down('#tabs').setActiveTab('farms');
                        }, panel, {single: true});
                        f.setValue('month');
                    } else {
                        panel.down('#tabs').setActiveTab('farms');
                    }
                    e.stopEvent();
                    //return false;
                }
            }
		},
        tpl: new Ext.XTemplate(
            '<tpl for=".">',
                '<div class="x-dataview-tab">',
                    '<table>',
                        '<tr>',
                            '<td colspan="2">',
                                '<div class="x-fieldset-subheader" style="margin-bottom:2px;width:245px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" data-qtip="{name:htmlEncode}">{name} </div>',
                                '<div style="font-size:90%;color:#999;line-height:14px;width:245px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{ccName}</div>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td colspan="2" style="text-align:center;padding-bottom:0">',
                                '<span class="x-dataview-tab-param-value">Spent this month</span>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td colspan="2" style="text-align:center;padding-bottom:8px">',
                                '<span class="x-dataview-tab-param-title" style="font-size:22px">{[this.currency2(values.periodTotal, true)]}</span> &nbsp;',
                                '<tpl if="growth!=0">' +
                                    '{[this.pctLabel(values.growth, values.growthPct, null, null, null, false)]}' +
                                '</tpl>'+
                            '</td>',
                        '</tr>',
                        '<tpl if="topSpender!==null">',
                            '<tr>',
                                '<td colspan="2" style="text-align:center">',
                                    '<span class="x-dataview-tab-param-value">Top spender</span><br/>',
                                    '<a class="topspender-link" href="#" title="{topSpender.name:htmlEncode}">{[this.fitMaxLength(values.topSpender.name, 23)]}</a><b> ({[this.currency2(values.topSpender.periodTotal)]})</b>',
                                '</td>',
                            '</tr>',
                        '</tpl>',
                    '</table>',
                '</div>',
            '</tpl>'
        ),
        subject: 'environments',
        roundCosts: false
    });


	var panel = Ext.create('Ext.panel.Panel', {
        cls: 'x-costanalytics',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			title: 'Account management &raquo; Cost analytics &raquo; Environments',
			reload: false,
			maximize: 'all',
            leftMenu: {
                menuId: 'settings',
                itemId: 'resources',
                subpage: 'environments',
                showPageTitle: true
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
                    xtype: 'analyticsrecources',
                    dock: 'top',
                    value: 'environments'
                },{
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
                            defaults: {
                                xtype: 'menuitemsortdir'
                            },
                            items: [{
                                text: 'Order by name',
                                group: 'env-sort',
                                checked: false,
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
                                checked: true,
                                group: 'env-sort',
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
                                group: 'env-sort',
                                defaultDir: 'desc',
                                sortHandler: function(dir){
                                    store.sort({
                                        property: 'growth',
                                        direction: dir
                                    });
                                }
                            }]
                        }
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
            autoScroll: true,
            layout: 'anchor',
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
                    periodField.restorePreservedValue('month', !!periodField.getValue());
                },
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    margin: '0 0 12 0',
                    items: [{
                        xtype: 'costanalyticsperiod',
                        preservedValueId: 'account',
                        dailyModeEnabled: true,
                        listeners: {
                            change: function(mode, startDate, endDate, quarter) {
                                var record = this.up('#form').currentRecord;
                                if (record) {
                                    loadPeriodData({envId: record.get('envId')}, mode, startDate, endDate, quarter);
                                }
                            }
                        }
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
                            level: 'account'
                        }]
                    },{
                        xtype: 'container',
                        itemId: 'farms',
                        tab: true,
                        tabConfig: {
                            title: 'Farm spend'
                        },
                        layout: 'anchor',
                        value: 'farms'
                    }]
                }]
            }]
		}]
	});

	return panel;
});
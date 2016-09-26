Scalr.regPage('Scalr.ui.analytics.farms.view', function (loadParams, moduleParams) {
    Scalr.utils.Quarters.days = moduleParams['quarters'];
    var requestParams, refreshStoreOnReconfigure;

	var reconfigurePage = function(params) {
        var farmId = params.farmId;
        cb = function() {
            selectFarmId = function() {
                if (farmId) {
                    dataview.getSelectionModel().deselectAll();
                    var record =  store.getById(farmId);
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
                store.on('load', selectFarmId, this, {single: true});
                store.reload();
            } else {
                selectFarmId();
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
            url: '/analytics/farms/xGetPeriodData',
            params: requestParams,
            success: function (data) {
                var spends = panel.down('costanalyticsspendsfarms');
                Ext.apply(data, params);
                data['name'] = panel.down('#form').currentRecord.get('name');

                //calculate top spenders
                data['totals']['top'] = {};
                Ext.Array.each(['farmRoles'], function(type){
                    var top6 = new Ext.util.MixedCollection();
                    top6.addAll(data['totals'][type]);
                    top6.sort('cost', 'DESC');
                    data['totals']['top'][type] = top6.getRange(0,5);
                });
                spends.loadData(mode, quarter, startDate, endDate, data);
                spends.requestParams = requestParams;
                panel.down('analyticsboxesfarms').loadData(mode, quarter, startDate, endDate, data);
            }
        });
    };

	var store = Ext.create('store.store', {
        data: moduleParams['farms'],
        model: Ext.define(null, {
            extend: 'Ext.data.Model',
            idProperty: 'farmId',
            fields: [
                'farmId',
                'name',
                'envName',
                'growth',
                'growthPct',
                'periodTotal',
                'topSpender',
                'projectName',
                //{name: 'id', mapping: 'farmId'}
            ]
        }),
        remoteFilter: true,
		sorters: [{
            property: 'periodTotal',
            direction: 'DESC'
		}],
		proxy: {
			type: 'ajax',
			url: '/analytics/farms/xList',
            reader: {
                type: 'json',
                rootProperty: 'farms'
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
                    form.loadRecord(view.store.getById(record.get('farmId')));
                }
            }
		},
        tpl  : new Ext.XTemplate(
            '<tpl for=".">',
                '<div class="x-dataview-tab">',
                    '<table>',
                        '<tr>',
                            '<td colspan="2">',
                                '<div class="x-fieldset-subheader" style="margin-bottom:4px;width:245px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" data-qtip="{name:htmlEncode}">{name} </div>',
                                '<div style="font-size:90%;color:#8daac5;line-height:14px;margin-top:-4px">{projectName}</div>'+
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td colspan="2" style="text-align:center;padding:8px 0 0">',
                                '<span class="x-form-item-label-default">Spent this month</span>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td colspan="2" style="text-align:center;padding-bottom:12px">',
                                '<span class="x-semibold" style="font-size:170%">{[this.currency2(values.periodTotal, true)]}</span> &nbsp;',
                                '<tpl if="growth!=0">' +
                                    '{[this.pctLabel(values.growth, values.growthPct, null, null, null, false)]}' +
                                '</tpl>'+
                            '</td>',
                        '</tr>',
                        '<tpl if="topSpender!==null">',
                            '<tr>',
                                '<td colspan="2" style="text-align:center">',
                                    '<span class="x-form-item-label-default">Top spender</span>',
                                    '<div title="{topSpender.alias:htmlEncode}"><img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-platform-small x-icon-platform-small-{topSpender.platform}"/>&nbsp;{[this.fitMaxLength(values.topSpender.alias, 23)]} <b>({[this.currency2(values.topSpender.periodTotal)]})</b></div>',
                                '</td>',
                            '</tr>',
                        '</tpl>',
                    '</table>',
                '</div>',
            '</tpl>'
        )
    });


	var panel = Ext.create('Ext.panel.Panel', {
        cls: 'x-costanalytics',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			menuTitle: 'Cost analytics',
            menuSubTitle: 'Farms',
            menuHref: '#/analytics/dashboard',
            menuParentStateId: 'panel-environment-analytics-dashboard',
            menuFavorite: true,
			reload: false,
			maximize: 'all',
            leftMenu: {
                menuId: 'envanalytics',
                itemId: 'farms'
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
                    ui: 'simple',
					dock: 'top',
					items: [{
						xtype: 'filterfield',
						itemId: 'liveSearch',
                        flex: 1,
						store: store,
                        //remoteSort: true,
                        //forceSearchButonVisibility: true,
                        margin: '0 10 0 0',
                        menu: {
                            xtype: 'menu',
                            minWidth: 220,
                            defaults: {
                                xtype: 'menuitemsortdir'
                            },
                            items: [{
                                text: 'Order by name',
                                group: 'farms-sort',
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
                                group: 'farms-sort',
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
                                group: 'farms-sort',
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
                        iconCls: 'x-btn-icon-refresh',
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
    			minWidth: 1000,
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
                        preservedValueId: 'environment',
                        dailyModeEnabled: true,
                        listeners: {
                            change: function(mode, startDate, endDate, quarter) {
                                var record = this.up('#form').currentRecord;
                                if (record) {
                                    loadPeriodData({farmId: record.get('farmId')}, mode, startDate, endDate, quarter);
                                }
                            }
                        }
                    }]
                },{
                    xtype: 'analyticsboxesfarms'
                },{
                    xtype: 'costanalyticsspendsfarms',
                    margin: '24 0 0 0',
                    level: 'environment'
                }]
            }]
		}]
	});

	return panel;
});
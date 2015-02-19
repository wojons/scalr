Scalr.regPage('Scalr.ui.analytics.account.projects.view', function (loadParams, moduleParams) {
    Scalr.utils.Quarters.days = moduleParams['quarters'];
    var requestParams, refreshStoreOnReconfigure;

	var reconfigurePage = function(params) {
        var projectId = params.projectId;
        cb = function() {
            selectProjectId = function() {
                if (projectId) {
                    dataview.getSelectionModel().deselectAll();
                    var record = store.getById(projectId);
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
            url: '/analytics/account/projects/xGetPeriodData',
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
            'budget',
            'budgetRemainPct',
            {name: 'budgetSpentPct', defaultValue: null},
            'budgetSpent',
            'archived',
            'shared',
            'accountId',
            'accountName',
            'envId',
            'envName',
            {name: 'id', convert: function(v, record){;return record.data.projectId;}}
		],
		sorters: [{
            property: 'periodTotal',
            direction: 'DESC'
		}],
		proxy: {
			type: 'ajax',
			url: '/analytics/account/projects/xList',
            reader: {
                type: 'json',
                root: 'projects'
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
            }
		},
        subject: 'projects',
        roundCosts: false
    });


	var panel = Ext.create('Ext.panel.Panel', {
        cls: 'x-costanalytics',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			title: 'Account management &raquo; Cost analytics &raquo; Projects',
			reload: false,
			maximize: 'all',
            leftMenu: {
                menuId: 'settings',
                itemId: 'resources',
                subpage: 'projects',
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
                    value: 'projects'
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
                                group: 'projects-sort',
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
                                group: 'projects-sort',
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
                                group: 'projects-sort',
                                defaultDir: 'desc',
                                sortHandler: function(dir){
                                    store.sort({
                                        property: 'growth',
                                        direction: dir
                                    });
                                }
                            },{
                                text: 'Order by budget consumed',
                                checked: false,
                                group: 'projects-sort',
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
						itemId: 'add',
                        text: 'New',
                        cls: 'x-btn-green-bg',
                        href: '#/analytics/account/projects/add?scope=account',
                        hrefTarget: '_self',
                        hidden: !Scalr.isAllowed('ADMINISTRATION_ANALYTICS', 'manage-projects'),
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
                    this.down('#itemEdit').setHref('#/analytics/account/projects/edit?projectId='+record.get('id'));
                    periodField.restorePreservedValue('month', !!periodField.getValue());
                },
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    margin: '0 0 12 0',
                    maxWidth: 1400,
                    items: [{
                        xtype: 'costanalyticsperiod',
                        preservedValueId: 'account',
                        dailyModeEnabled: true,
                        listeners: {
                            change: function(mode, startDate, endDate, quarter) {
                                var form = this.up('#form'),
                                    record = form.currentRecord,
                                    tabs = form.down('#tabs'),
                                    warn;
                                if (record) {
                                    warn = form.down('#sharedProjectWarning')
                                    if (record.get('shared')==1 && Scalr.user.type !== 'FinAdmin' && Scalr.user.type != 'ScalrAdmin') {
                                        tabs.hide();
                                        //warn.down('#title').update({name: record.get('name')});
                                        warn.show();
                                        this.up().hide();
                                    } else {
                                        warn.hide();
                                        tabs.show();
                                        this.up().show();
                                        loadPeriodData({ccId: record.get('ccId'), projectId: record.get('projectId')}, mode, startDate, endDate, quarter);
                                    }
                                }
                            }
                        }
                    },{
                        xtype: 'tbfill'
                    },{
                        xtype: 'button',
                        text: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-configure" />',
                        width: 50,
                        itemId: 'itemEdit',
                        hrefTarget: '_self',
                        margin: '0 0 0 12',
                        hidden: !Scalr.isAllowed('ADMINISTRATION_ANALYTICS', 'manage-projects'),
                        href: '#'
                    }]
                },{
                    xtype: 'container',
                    itemId: 'sharedProjectWarning',
                    hidden: true,
                    flex: 1,
                    layout: {
                        type: 'vbox',
                        align: 'center'
                    },
                    items: [{
                        xtype: 'component',
                        itemId: 'title',
                        cls: 'x-fieldset-subheader',
                        margin: '200 0 0',
                        style: 'text-align:center',
                        html: '<b>Global</b> projects are shared across multiple Scalr accounts.<br/> You must log in to Global Scalr Cost Analytics to view details on these Projects.'
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
                            this.down('analyticsboxesenvproject').loadData(mode, quarter, startDate, endDate, data);
                        },
                        tabConfig: {
                            title: 'Summary'
                        },
                        value: 'clouds',
                        layout: 'anchor',
                        items: [{
                            xtype: 'analyticsboxesenvproject'
                        },{
                            xtype: 'costanalyticsspendsenv',
                            cls: 'x-container-fieldset',
                            level: 'account',
                            subject: 'projects'
                        }]
                    },{
                        xtype: 'container',
                        tab: true,
                        tabConfig: {
                            title: 'Farms'
                        },
                        layout: 'anchor',
                        value: 'farms'
                    }]
                }]
            }]
		}]
	});

	Scalr.event.on('update', function (type, project) {
        var dataview = panel.down('costanalyticslistview');
		if (type === '/analytics/account/projects/edit' || type === '/analytics/account/projects/add') {
            var record = dataview.store.getById(project.projectId);
            if (!record) {
                record = dataview.store.add(project)[0];
                dataview.getSelectionModel().select(record);
            } else {
                record.set(project);
                panel.down('#form').loadRecord(record);
            }
        } else if (type === '/analytics/account/projects/remove') {
            var record = dataview.store.getById(project.projectId);
            if (record) {
                if (project.removable || record.get('periodTotal') == 0) {
                    dataview.store.remove(record);
                } else {
                    record.set('archived', true);
                }
            }
        }
    }, panel);

	return panel;
});
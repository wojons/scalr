Ext.define('Scalr.ui.monitoring.statisticspanel', {
	extend: 'Ext.panel.Panel',
	alias: 'widget.monitoring.statisticspanel',

	defaults: {
		xtype: 'monitoring.statisticswindow'
	},
	params: {
		farm: '',
		role: '',
		panelTitle: ''
	},
	border: false,
	compareMode: false,
    bodyStyle: 'background: #B5C0CE',
	statistics: [{
		name: 'MEMSNMP',
		height: 402,
		title: ' / Memory Usage'
	}, {
		name: 'CPUSNMP',
		height: 368,
		title: ' / CPU Utilization'
	}, {
		name: 'LASNMP',
		height: 334,
		title: ' / Load Averages'
	}, {
		name: 'NETSNMP',
		height: 280,
		title: ' / Network Usage'
	}, {
		name: 'ServersNum',
		height: 266,
		title: ' / Running Servers'
	}],

	renewLayout: function () {
		this.layout = this.compareMode ?
			{ type: 'anchor'} :
		 	{ type: 'table', columns: 2,
		 		tdAttrs: {
		 			style: {'vertical-align': 'top'}
				}
			};
	},

	initComponent: function () {
		this.callParent();
		this.loadPanels();
		this.renewLayout();
	},

	loadPanels: function () {
		var me = this;
		Ext.each(this.statistics, function (item){
			if(item['name']!='ServersNum' || (item['name']=='ServersNum' && me.params && me.params['role'].indexOf('_', 0) == -1))
				me.add({
					height: item.height,
					title: me.params['panelTitle'] + item.title,
					itemId: item.name + me.params['farm'] + me.params['role'],
					watchername: item.name,
					type: 'daily',
					farm: me.params['farm'],
					role: me.params['role']
				});
		});
	}
});

Ext.define('Scalr.ui.monitoring.statisticswindow', {
	extend: 'Ext.panel.Panel',
	alias: 'widget.monitoring.statisticswindow',

	width: 537,
	//bodyPadding: 3,
	margin: '10 0 0 10',

	watchername: '',
	type: 'daily',
	farm: '',
	role: '',
	toolMenu: true,
	typeMenu: true,
		
	html: '<div style="position: relative; top: 48%; text-align: center; width: 100%; height: 50%;"><img src = "/ui2/images/icons/anim/loading_16x16.gif">&nbsp;Loading...</div>',
    style: 'overflow: hidden; border-radius: 3px',
	
	removeDockedItem: true,

	onBoxReady: function (){
		this.fillByStatistics();
		if (this.toolMenu) {
			this.addToolMenu();
		}
		if (this.typeMenu) {
			this.addDockMenu();
		}
	},

	addToDashboard: function () {
		var me = this;
		var data = Ext.encode({
			params: {
				'farmid': me.farm,
				'watchername': me.watchername,
				'graph_type': me.type,
				'role': me.role,
				'width': me.getWidth(),
				'height': me.getHeight(),
				'title': me.title + ' ( ' + me.type + ' )'
			},
			name: 'dashboard.monitoring',
			url: ''
		});

		Scalr.Request({
			processBox: {
				type: 'action',
				msg: 'Adding widget to dashboard...'
			},
			url: '/dashboard/xUpdatePanel',
			params: {'widget': data},
			success: function (data, response, options) {
				Scalr.event.fireEvent('update', '/dashboard', data.panel);
			}
		});
	},

	addToolMenu: function() {
		var me = this;
		if(!me.down('#' + me.watchername + me.farm + me.role + 'tool'))
			me.addTool({
				type: 'dashboard',
				tooltip: 'Add to dashboard',
				itemId: me.watchername + me.farm + me.role + 'tool',
				handler: function (el, tool, p) {
					me.addToDashboard();
				}
			});
	},

	addDockMenu: function() {
		var me = this;
		if(me){
			if (me.removeDockedItem && me.dockedItems.getAt(1))
				me.removeDocked(me.dockedItems.getAt(1), true);
			me.addDocked({
				xtype: 'toolbar',
				dock: 'top',
				items: [{
					xtype: 'buttongroupfield',
					value: 'daily',
					defaults: {
						width: 66
					},
					items: [{
						xtype: 'button',
						text: 'Daily',
						value: 'daily'
					}, {
						xtype: 'button',
						text: 'Weekly',
						value: 'weekly'
					}, {
						xtype: 'button',
						text: 'Monthly',
						value: 'monthly'
					}, {
						xtype: 'button',
						text: 'Yearly',
						value: 'yearly'
					}],
					listeners: {
						change: function (field, value) {
							me.type = value;
							me.fillByStatistics();
						}
					}
				}]
			});
		}
	},

	fillByStatistics: function () {
		if(this) {
			var me = this;
			if(me.body)
				me.body.update('<div style="position: relative; top: 48%; text-align: center; vertical-align: top; width: 100%; height: 50%;"><img src = "/ui2/images/icons/anim/loading_16x16.gif">&nbsp;Loading...</div>');
			me.html = '<div style="position: relative; top: 48%; text-align: center; width: 100%; vertical-align: top; height: 50%;"><img src = "/ui2/images/icons/anim/loading_16x16.gif">&nbsp;Loading...</div>';
			Scalr.Request({
				scope: this,
				url: '/server/statistics_proxy.php?version=2&task=get_stats_image_url&farmid=' + me.farm + '&watchername=' + me.watchername + '&graph_type=' + me.type + '&role=' + me.role,
				success: function (data, response, options) {
					if(me.rendered && !me.isDestroyed) {
						me.body.update('<div style="position: relative; text-align: center; width: 100%; height: 50%;"><img src = "' + data.msg + '"/></div>');
					}
				},
				failure: function(data, response, options) {
					if(me.rendered && !me.isDestroyed) {
						me.body.update('<div style="position: relative; top: 48%; text-align: center; width: 100%; height: 50%;"><font color = "red">' + (data ? data['msg'] : '') + '</font></div>');
					}
				}
			});
		}
	}
});

Ext.define('Scalr.ui.ChartPreview', {
    extend: 'Ext.container.Container',
    alias: 'widget.chartpreview',

    width: 200,
    height: 80,
    cls: 'scalr-ui-chart-preview',

    listeners: {
        boxready: function() {
            var me = this;
            me.el.mask('Loading...');
            me.on('click',
                function(){
                    Scalr.utils.Window({
                        animationTarget: me,
                        xtype: 'monitoring.statisticswindow',
                        title: me.serverName + me.statistics[me.watcher].title,

                        toolMenu: false,
                        typeMenu: true,
                        removeDockedItem: false,

                        watchername: me.watcher,
                        farm: me.farmId,
                        role: me.role,

                        width: 537,
                        height: me.statistics[me.watcher].height,
                        bodyPadding: 0,
                        padding: 0,
                        autoScroll: false,

                        closable: true,
                        cls: null,
                        titleAlign: 'left',
                        tools: [{
                            type: 'refresh',
                            handler: function () {
                                this.up('panel').fillByStatistics();
                            }
                        }]
                    });
                },
                me,
                {element: 'el'}
            );
        }
    },
    role: null,
    farmId: null,
    watcher: null,
    serverName: null,
    src: null,

    statistics: {
        MEMSNMP: {
            height: 418,
            title: ' / Memory Usage'
        },
        CPUSNMP: {
            height: 370,
            title: ' / CPU Utilization'
        },
        LASNMP: {
            height: 337,
            title: ' / Load Averages'
        },
        NETSNMP: {
            height: 282,
            title: ' / Network Usage'
        }
    },


    loadStatistics: function (farmId, watcher, type, farmRoleId, serverInfo, callback) {
        var me = this,
            role = 'INSTANCE_' + farmRoleId + '_' + serverInfo.index;
        me.role = role;
        me.farmId = farmId;
        me.watcher = watcher;
        me.serverName = serverInfo.title;

        if(me.rendered && !me.isDestroyed) {
            me.el.mask('Loading...');
            Scalr.Request({
                scope: this,
                url: '/server/statistics_proxy.php?version=2&task=get_stats_image_url&farmid=' + farmId + '&watchername=' + watcher + '&graph_type=' + type + '&role=' + role,
                success: function (data, response, options) {
                    if(me.rendered && !me.isDestroyed && role == me.role) {
                        me.el.unmask();
                        me.src = data.msg;
                        me.update('<div style="position: relative; text-align: center; width: 100%; height: 50%;"><img width="200" height="80" src = "' + data.msg + '"/></div>');
                        if (Ext.isFunction(callback)) {
                            callback(true);
                        }
                    }
                },
                failure: function(data, response, options) {
                    if (me.rendered && !me.isDestroyed && role == me.role) {
                        me.el.unmask();
                        me.update('<div style="position: relative; top: 2%; text-align: center; width: 100%;"><font color = "red">' + (data ? data['msg'] : '') + '</font></div>');
                        if (Ext.isFunction(callback)) {
                            callback(false);
                        }
                    }
                }
            });
        }
    }
});

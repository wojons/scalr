Scalr.regPage('Scalr.ui.tools.aws.ec2.cloudwatch.view', function (loadParams, moduleParams) {

	var metricsNames = {
		'CPUCreditBalance': 'CPU Credit Balance',
		'CPUCreditUsage': 'CPU Credit Usage',
		'FreeLocalStorage': 'Free Local Storage',
		'NetworkThroughput': 'Network Throughput',
		'DDLThroughput': 'DDL Throughput',
		'InsertLatency': 'Insert Latency',
		'UpdateThroughput': 'Update Throughput',
		'CommitThroughput': 'Commit Throughput',
		'InsertThroughput': 'Insert Throughput',
		'CommitLatency': 'Commit Latency',
		'ActiveTransactions': 'Active Transactions',
		'DDLLatency': 'DDL Latency',
		'UpdateLatency': 'Update Latency',
		'SelectThroughput': 'Select Throughput',
		'DMLThroughput': 'DML Throughput',
		'DeleteLatency': 'Delete Latency',
		'DMLLatency': 'DML Latency',
		'SelectLatency': 'Select Latency',
		'BlockedTransactions': 'Blocked Transactions',
		'LoginFailures': 'Login Failures',
		'DeleteThroughput': 'Delete Throughput',
		'SwapUsage': 'Swap Usage',
		'FreeStorageSpace': 'Free Storage Space',
		'FreeableMemory': 'Freeable Memory',
		'ReadThroughput': 'Read Throughput',
		'ReadLatency': 'Read Latency',
		'WriteIOPS': 'Write IOPS',
		'ReadIOPS': 'Read IOPS',
		'NetworkReceiveThroughput': 'Network Receive Throughput',
		'DiskQueueDepth': 'Disk Queue Depth',
		'WriteLatency': 'Write Latency',
		'DatabaseConnections': 'Database Connections',
		'NetworkTransmitThroughput': 'Network Transmit Throughput',
		'WriteThroughput': 'Write Throughput',
		'CPUUtilization': 'CPU Utilization',
		'BufferCacheHitRatio': 'Buffer Cache Hit Ratio',
		'ResultSetCacheHitRatio': 'Result Set Cache Hit Ratio',
		'BinLogDiskUsage': 'Bin Log Disk Usage'
	};

	var title = '';
	if(loadParams['namespace'] == 'AWS/EC2' || loadParams['namespace'] == 'AWS/RDS') title = loadParams['namespace'].substring(4, 7) + ' instance: ';
	if (loadParams['namespace'] == 'AWS/EBS') title = loadParams['namespace'].substring(4, 7) + ' volume: ';
	function getFormat(range) {
		format = '';
		if(range == 180) format = 'H:i';
		if(range == 3600 || range == 21600) format = 'D d, H:i';
		if(range == 86400) format = 'D, d M';
		if(range == 864000) format = 'd M Y';
		return format;
	}
	function getTime(range) {
		currentTime = new Date();
		startTime = new Date(currentTime);
		if(range == 180) startTime.setHours(startTime.getHours()-1);
		if(range == 3600) startTime.setDate(startTime.getDate()-1);
		if(range == 21600) startTime.setDate(startTime.getDate()-7);
		if(range == 86400) startTime.setMonth(startTime.getMonth()-1);
		if(range == 864000) startTime.setFullYear(startTime.getFullYear()-1);
		return {
			startTime: startTime.toUTCString(),
			endTime: currentTime.toUTCString()
		};
	}
	function createWindows() {
		Ext.each (moduleParams.metric,function(item){
			panel.child('fieldset').add({
				xtype: 'panel',
				title: Ext.isDefined(metricsNames[item.name]) ? metricsNames[item.name] : item.name,
				width: 700,
				height: 400,
				itemId: item.name,
				margin: '0 20 20 0',
				items: [{
		    		xtype: 'chart',
		    		width: 700,
		   			height: 400,
		   			animate: true,
		   			shadow: true,
		            store: {
		            	fields: ['value', 'time'],
		            	proxy: {
							type: 'ajax',
							reader: {
								type: 'json',
								rootProperty: 'data'
							},
							extraParams: {
								metricName: item.name,
								startTime: getTime(180).startTime,
								endTime: getTime(180).endTime,
								type: 'Average',
								period: 180,
								namespace: loadParams['namespace'],
								dType: loadParams['objectId'],
								dValue: loadParams['object'],
								dateFormat: 'H:i',
								Unit: item.unit,
								region: loadParams['region']
							},
							url: '/tools/aws/ec2/cloudwatch/xGetMetric/'
						}
		            },
		         	axes: [{
			            title: item.unit,
			            type: 'numeric',
			            position: 'left',
			            fields: 'value',
			            minimum: 0,
			            grid: {
		                    odd: {
		                      opacity: 0.5,
		                      fill: '#fff'
		                    }
		               }
			        },{
			            title: 'Time',
			            type: 'category',
			            position: 'bottom',
			            fields: 'time'
			        }],
			        series: [{
			            type: 'line',
		                highlight: {
		                	size: 2,
		                    radius: 2
		                },
		                fill: true,
			            xField: 'time',
			            yField: 'value'
			        }],
			        theme: 'Blue',
			        listeners: {
			        	afterrender: function(component, opt){
			        		component.setLoading(true);
			        	},
	        			redraw: function (chart, eOpts ){
	        				var task = new Ext.util.DelayedTask(function(){
    							chart.setLoading(false);
							});
							task.delay(400);
	        			}
			        }
		        }],
		        dockedItems: [{
		        	xtype: 'toolbar',
					dock: 'top',
					items: [{
						xtype: 'combo',
						fieldLabel: 'Time Range',
						labelWidth: 80,
						editable: false,
				        store: [['180', '1 Hour'], ['3600', '1 Day'], ['21600', '1 Week'], ['86400', '1 Month'], ['864000', '1 Year']],
				        value: '180',
				        displayField: 'state',
				        mode: 'local',
				        itemId: 'timeRange' + item.name,
				        listeners:{
					         change: function() {
					         	panel.down('#' + item.name).down('chart').setLoading(true);
				            	Ext.apply(panel.down('#' + item.name).down('chart').store.proxy.extraParams, { startTime: getTime(panel.down(('#timeRange' +item.name)).getValue()).startTime, endTime: getTime(panel.down(('#timeRange' +item.name)).getValue()).endTime, type: panel.down(('#type' + item.name)).getValue(), period: panel.down(('#timeRange' +item.name)).getValue(), dateFormat: getFormat(panel.down(('#timeRange' +item.name)).getValue())});
								panel.down('#' + item.name).down('chart').store.load();
				            }
			           }
				    }, ' ', {
				        xtype: 'combo',
						fieldLabel: 'Type',
						labelWidth: 34,
						editable: false,
					    store: [['Average', 'Average'], ['Minimum', 'Minimum'], ['Maximum', 'Maximum'], ['Sum', 'Sum']],
					    value: 'Average',
				        displayField: 'state',
				        mode: 'local',
				        itemId: 'type' + item.name,
				        listeners:{
					         change: function() {
					         	panel.down('#' + item.name).down('chart').setLoading(true);
				            	Ext.apply(panel.down('#' + item.name).down('chart').store.proxy.extraParams, { startTime: getTime(panel.down(('#timeRange' +item.name)).getValue()).startTime, endTime: getTime(panel.down(('#timeRange' +item.name)).getValue()).endTime, type: panel.down(('#type' + item.name)).getValue(), period: panel.down(('#timeRange' +item.name)).getValue(), dateFormat: getFormat(panel.down(('#timeRange' +item.name)).getValue())});
								panel.down('#' + item.name).down('chart').store.load();
				            }
			           }
				    }, ' ', {
			            text: 'Refresh',
			            handler: function(menuItem) {
			            	panel.down('#' + item.name).down('chart').setLoading(true);
			            	Ext.apply(panel.down('#' + item.name).down('chart').store.proxy.extraParams, { startTime: getTime(panel.down(('#timeRange' +item.name)).getValue()).startTime, endTime: getTime(panel.down(('#timeRange' +item.name)).getValue()).endTime, type: panel.down(('#type' + item.name)).getValue(), period: panel.down(('#timeRange' +item.name)).getValue(), dateFormat: getFormat(panel.down(('#timeRange' +item.name)).getValue())});
							panel.down('#' + item.name).down('chart').store.load();
			            }
				   }]
		        }]
			});
			panel.down('#' + item.name).down('chart').store.load();
		});

	}
	var panel = Ext.create('Ext.panel.Panel', {
        scalrOptions: {
            maximize: 'all',
            menuTitle: 'AWS Cloud Watch'
        },
        stateId: 'grid-tools-aws-ec2-cloudwatch-view',
        autoScroll: true,
        items: [{
            xtype: 'fieldset',
            title: 'Cloud Watch Statistics ('+ title + loadParams['objectId'] + ')',
            layout: {
                type: 'table',
                columns: 2
            }
        }]
	});
	createWindows();
	return panel;
});
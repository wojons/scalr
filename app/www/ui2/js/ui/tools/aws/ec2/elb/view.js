Scalr.regPage('Scalr.ui.tools.aws.ec2.elb.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'name','dtcreated','dnsName','farmId','farmRoleId','farmName','roleName', 'vpcId'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/aws/ec2/elb/xListElasticLoadBalancers/'
		},
		remoteSort: true
	});
	return Ext.create('Ext.grid.Panel', {
		title: 'Tools &raquo; Amazon Web Services &raquo; Elastic Load Balancer',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-tools-aws-ec2-elb-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Elastic Load Balancer',
				href: '#/tools/aws/ec2/elb'
			}
		}],
		viewConfig: {
			emptyText: 'No Elastic Load Balancer found',
			loadingText: 'Loading ELBs ...'
		},
		columns: [
			{ flex: 1, header: "Name", dataIndex: 'name', sortable: true },
			{ flex: 1, header: "Used on", dataIndex: 'farmName', sortable: true, xtype: 'templatecolumn', tpl: new Ext.XTemplate(
				'<tpl if="farmId">' +
					'<a href="#/farms/{farmId}/view" title="Farm {farmName}">{farmName}</a>' +
					'<tpl if="roleName">' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {roleName}">{roleName}</a>' +
					'</tpl>' +
				'</tpl>' +
				'<tpl if="!farmId"><img src="/ui2/images/icons/false.png" /></tpl>'
			)},
			{ flex: 2, header: "DNS name", dataIndex: 'dnsName', sortable: true },
            { header: "Placement", width: 150, dataIndex: 'vpcId', sortable: true, xtype: 'templatecolumn', tpl: '{[values.vpcId || \'EC2\']}' },
			{ header: "Created at", width: 150, dataIndex: 'dtcreated', sortable: true },
			{
				xtype: 'optionscolumn2',
				menu: [{
					text: 'Details',
					iconCls: 'x-menu-icon-info',
					menuHandler:function(data) {
						Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/elb/' + data['name'] + '/details?cloudLocation=' + store.proxy.extraParams.cloudLocation);
					} 
				},{
					xtype: 'menuseparator'
				},{
					text: 'Remove',
					iconCls: 'x-menu-icon-delete',
					request: {
						confirmBox: {
							msg: 'Are you sure want to remove selected Elastic Load Balancer?',
							type: 'delete'
						},
						processBox: {
							type: 'delete',
							msg: 'Removing Elastic Load Balancer ...'
						},
						url: '/tools/aws/ec2/elb/xDelete/',
						dataHandler: function (data) {
							return {
								elbName: data['name'],
								cloudLocation: store.proxy.extraParams.cloudLocation
							};
						},
						success: function(data) {
							store.load();
						}
					}
				}]
			}
		],
		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			beforeItems: [{
                text: 'Add ELB',
                cls: 'x-btn-green-bg',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/elb/create?cloudLocation=' + store.proxy.extraParams.cloudLocation);
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}, ' ', {
				xtype: 'fieldcloudlocation',
				itemId: 'cloudLocation',
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
				gridStore: store
			}]
		}]
	});
});

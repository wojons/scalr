Scalr.regPage('Scalr.ui.tools.aws.ec2.elb.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'name','dtcreated','dnsName', {name: 'farmId', defaultValue: null},'farmRoleId','farmName','roleName', 'vpcId'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/aws/ec2/elb/xListElasticLoadBalancers/'
		},
		remoteSort: true
	});
	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'EC2 ELB',
            menuHref: '#/tools/aws/ec2/elb',
            menuFavorite: true
		},
		store: store,
		stateId: 'grid-tools-aws-ec2-elb-view',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],
		viewConfig: {
			emptyText: 'No Elastic Load Balancer found',
			loadingText: 'Loading ELBs ...'
		},
		columns: [
			{ flex: 1, header: "Elastic Load Balancer", dataIndex: 'name', sortable: true },
			{ flex: 1, header: "Used on", dataIndex: 'farmName', sortable: true, xtype: 'templatecolumn', tpl: new Ext.XTemplate(
				'<tpl if="farmId">' +
					'<a href="#/farms?farmId={farmId}" title="Farm {farmName}">{farmName}</a>' +
					'<tpl if="roleName">' +
						'&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {roleName}">{roleName}</a>' +
					'</tpl>' +
				'</tpl>' +
				'<tpl if="!farmId">&mdash;</tpl>'
			)},
			{ flex: 2, header: "DNS name", dataIndex: 'dnsName', sortable: true },
            { header: "Placement", width: 150, dataIndex: 'vpcId', sortable: true, xtype: 'templatecolumn', tpl: '{[values.vpcId || \'EC2\']}' },
			{ header: "Created at", width: 170, dataIndex: 'dtcreated', sortable: true },
			{
				xtype: 'optionscolumn',
				menu: [{
					text: 'Details',
					iconCls: 'x-menu-icon-information',
                    showAsQuickAction: true,
					menuHandler:function(data) {
						Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/elb/' + data['name'] + '/details?cloudLocation=' + store.proxy.extraParams.cloudLocation);
					}
				},{
					text: 'Remove',
					iconCls: 'x-menu-icon-delete',
                    showAsQuickAction: true,
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
                text: 'New Elastic Load Balancer',
                cls: 'x-btn-green',
				handler: function() {
					Scalr.event.fireEvent('modal', '#/tools/aws/ec2/elb/create?cloudLocation=' + store.proxy.extraParams.cloudLocation);
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}, ' ', {
                xtype: 'cloudlocationfield',
                platforms: ['ec2'],
				gridStore: store
			}]
		}]
	});
});

Scalr.regPage('Scalr.ui.tools.aws.ec2.eips.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'ipaddress','instance_id', 'domain', 'allocation_id', {name: 'farm_id', defaultValue: null}, 'farm_name', 'role_name', {name: 'indb', defaultValue: null}, 'farm_roleid', {name: 'server_id', defaultValue: null}, 'server_index' ],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/aws/ec2/eips/xListEips/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'EC2 EIPs',
            menuHref: '#/tools/aws/ec2/eips',
            menuFavorite: true
		},
		store: store,
		stateId: 'grid-tools-aws-ec2-eips-view',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],

		viewConfig: {
			emptyText: "No elastic IPs found",
			loadingText: 'Loading elastic IPs ...'
		},

		columns: [
			{ header: "Used By", flex: 1, dataIndex: 'farm_name', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="farm_id"><a href="#/farms?farmId={farm_id}" title="Farm {farm_name}">{farm_name}</a>' +
					'<tpl if="role_name">&nbsp;&rarr;&nbsp;<a href="#/farms/{farm_id}/roles/{farm_roleid}/view"' +
						'title="Role {role_name}">{role_name}</a> #{server_index}' +
					'</tpl>' +
				'</tpl>' +
				'<tpl if="! farm_id">&mdash;</tpl>'
			},
			{ header: "IP address", width: 200, dataIndex: 'ipaddress', sortable: false },
			{ header: "Type", width: 80, dataIndex: 'domain', sortable: false },
			{ header: "Allocation ID", width: 200, dataIndex: 'allocation_id', sortable: false },
			{ header: "Auto-assigned", width: 150, dataIndex: 'role_name', sortable: true, xtype: 'templatecolumn', align:'center', tpl:
				'<tpl if="indb"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div></tpl>' +
				'<tpl if="!indb">&mdash;</tpl>'
			},
			{ header: "Server", flex: 1, dataIndex: 'server_id', sortable: true, xtype: 'templatecolumn', tpl:
				'<tpl if="server_id"><a href="#/servers?serverId={server_id}">{server_id}</a></tpl>' +
				'<tpl if="!server_id">{instance_id}</tpl>'
			}
		],

        selModel: {
            selType: 'selectedmodel',
            getVisibility: function(record) {
                return !record.get('server_id');
            }
        },

        listeners: {
            selectionchange: function(selModel, selections) {
                this.down('scalrpagingtoolbar').down('#delete').setDisabled(!selections.length);
            }
        },

        dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			items: [{
                xtype: 'cloudlocationfield',
                platforms: ['ec2'],
                gridStore: store
			}],
            afterItems: [{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more elastic IP(s) to delete them',
                disabled: true,
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected IP(s): %s ?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Deleting elastic IP(s) ...'
                        },
                        url: '/tools/aws/ec2/eips/xDelete/',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('ipaddress'));
                        request.confirmBox.objects.push(records[i].get('ipaddress'));
                    }
                    request.params = { eips: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
                    Scalr.Request(request);
                }
            }]
		}]
	});
});

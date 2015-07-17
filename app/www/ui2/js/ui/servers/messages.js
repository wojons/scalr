Scalr.regPage('Scalr.ui.servers.messages', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'messageid', 'server_id', 'event_server_id', 'status', 'handle_attempts', 'dtlasthandleattempt','message_type','type','isszr'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/servers/xListMessages/'
		},
		remoteSort: true,
        sorters: {
            property: 'dtadded',
            direction: 'DESC'
        }
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'Server messages'
		},

		store: store,
		stateId: 'grid-servers-messages-view',
		stateful: true,
        plugins: [ 'gridstore', {
        	ptype: 'applyparams',
        	filterIgnoreParams: [ 'serverId' ]
        }],

		viewConfig: {
			emptyText: 'No messages found'
		},

		columns:[
			{ header: "Message ID", flex: 1, dataIndex: 'messageid', sortable: true },
			{ header: "Message type", width: 150, dataIndex: 'message_type', xtype: 'templatecolumn', tpl:'{type} / {message_type}', sortable: false },
			{ header: "Server ID", flex: 1, dataIndex: 'server_id', xtype: 'templatecolumn', tpl:'<a href="#/servers/{server_id}/dashboard">{server_id}</a>', sortable: false },
			{ header: "Event server ID", flex: 1, dataIndex: 'event_server_id', xtype: 'templatecolumn', tpl:'<a href="#/servers/{event_server_id}/dashboard">{event_server_id}</a>', sortable: true },
			{ header: "Type", align: 'center', width: 60, dataIndex: 'type', xtype: 'templatecolumn',
                tpl: '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-arrow-{[values.type==\'in\'?\'left\':\'right\']}" '+
                      'data-anchor="right" data-qalign="r-l" data-qtip="This is a message from {[values.type==\'in\'?\'the server addressed to Scalr.\':\'Scalr addressed to the server.\']}" ' +
                     '/>' ,
                sortable: false
            },
            { header: "Status", width: 150, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'servermessage'},
			{ header: "Attempts", width: 100, dataIndex: 'handle_attempts', sortable: true },
			{ header: "Last delivery attempt", width: 200, dataIndex: 'dtlasthandleattempt', sortable: true },
			{
				xtype: 'optionscolumn',
				getVisibility: function (record) {
					return record.get('status') == 2 || record.get('status') == 3;
				},
				menu: [{
					text: 'Re-send message',
					request: {
						processBox: {
							type: 'action',
							msg: 'Re-sending message ...'
						},
						dataHandler: function (data) {
							this.url = '/servers/' + data['server_id'] + '/xResendMessage/';
							return { messageId: data['messageid'] };
						},
						success: function () {
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
            items: [{
                xtype: 'filterfield',
                store: store
            }]
		}]
	});
});

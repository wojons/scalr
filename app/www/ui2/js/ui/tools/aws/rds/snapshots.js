Scalr.regPage('Scalr.ui.tools.aws.rds.snapshots', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'id','name','storage','idtcreated','avail_zone','engine','status','port','dtcreated' ],
		proxy: {
			type: 'scalr.paging',
			extraParams: loadParams,
			url: '/tools/aws/rds/snapshots/xListSnapshots/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB snapshots',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		scalrReconfigureParams: {},
		store: store,
		stateId: 'grid-tools-aws-rds-snapshots-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}],

		viewConfig: {
			emptyText: 'No db snapshots found',
			loadingText: 'Loading db snapshots ...'
		},

		columns: [
			{ header: "Name", flex: 1, dataIndex: 'name', sortable: false },
			{ header: "Storage", width: 100, dataIndex: 'storage', sortable: false },
			{ header: "Created at", width: 150, dataIndex: 'dtcreated', sortable: false },
			{ header: "Instance created at", width: 150, dataIndex: 'idtcreated', sortable: false },
			{ header: "Status", width: 150, dataIndex: 'status', sortable: false },
			{ header: "Port", width: 150, dataIndex: 'port', sortable: false },
			{ header: "Placement", width: 150, dataIndex: 'avail_zone', sortable: false },
			{ header: "Engine", width: 150, dataIndex: 'engine', sortable: false },
			{
				xtype: 'optionscolumn',
				optionsMenu: [{
					text: 'Restore DB instance from this snapshot',
					menuHandler: function (item) {
						document.location.href = '#/tools/aws/rds/instances/restore?snapshot=' + item.record.get('name') + '&cloudLocation=' + store.proxy.extraParams.cloudLocation;
					}
				}]
			}
		],

        multiSelect: true,
        selModel: {
            selType: 'selectedmodel'
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
            beforeItems: [{
                text: 'Add instance',
                cls: 'x-btn-green-bg',
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/rds/instances/create');
                }
            }],
            afterItems: [{
                ui: 'paging',
                itemId: 'delete',
                iconCls: 'x-tbar-delete',
                tooltip: 'Select one or more db snapshot(s) to delete them',
                disabled: true,
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected db snapshot(s): %s ?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Deleting snapshot(s) ...'
                        },
                        url: '/tools/aws/rds/snapshots/xDeleteSnapshots/',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('name'));
                    }
                    request.params = { snapshots: Ext.encode(data), cloudLocation: store.proxy.extraParams.cloudLocation };
                    Scalr.Request(request);
                }
            }],
            items: [{
                xtype: 'fieldcloudlocation',
                itemId: 'cloudLocation',
                store: {
                    fields: [ 'id', 'name' ],
                    data: moduleParams.locations,
                    proxy: 'object'
                },
                gridStore: store,
                cloudLocation: loadParams['cloudLocation'] || ''
            }]
        }]
	});
});

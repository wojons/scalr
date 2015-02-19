Scalr.regPage('Scalr.ui.sshkeys.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'id','type','fingerprint','cloud_location','farm_id','cloud_key_name', 'status', 'farmName' ],
		proxy: {
			type: 'scalr.paging',
			url: '/sshkeys/xListSshKeys/'
		},
		remoteSort: true
	});


	return Ext.create('Ext.grid.Panel', {
		title: 'Tools &raquo; SSH Keys manager',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-sshkeys-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
        
		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'SSH keys',
				href: '#/sshkeys/view'
			}
		}],

		viewConfig: {
			emptyText: 'No matching SSH Key found',
			loadingText: 'Loading SSH keys ...'
		},

		columns: [
			{ text: 'Key ID', width: 100, dataIndex: 'id', sortable: true },
			{ text: 'Name', flex: 1, dataIndex: 'cloud_key_name', sortable: false },
            { header: 'Status', xtype: 'statuscolumn', dataIndex: 'status', statustype: 'sshkey', sortable: true, resizable: false, maxWidth: 90, qtipConfig: {width: 300}},
			{ header: 'Type', width: 200, dataIndex: 'type', sortable: true },
			{ header: "Cloud location", width: 150, dataIndex: 'cloud_location', sortable: true, xtype: 'templatecolumn', tpl: 
			'<tpl if="cloud_location">{cloud_location}<tpl else><img src="/ui2/images/icons/false.png" /></tpl>'
			},
			{ header: 'Farm ID', width: 80, dataIndex: 'farm_id', sortable: false, xtype: 'templatecolumn', tpl:
                '<tpl if="farm_id">' +
                    '<a href="#/farms/view?farmId={farm_id}">{farm_id}</a>'+
                '</tpl>'
            },
			{
				xtype: 'optionscolumn2',
				menu: [{
					text: 'Download SSH Private key',
					iconCls: 'x-menu-icon-downloadprivatekey',
					menuHandler: function (data) {
 						Scalr.utils.UserLoadFile('/sshkeys/' + data['id'] + '/downloadPrivate');
 					}
 				}, {
	 				text: 'Download SSH public key',
					iconCls: 'x-menu-icon-downloadpublickey',
	 				menuHandler: function (data) {
 						Scalr.utils.UserLoadFile('/sshkeys/' + data['id'] + '/downloadPublic');
 					}
 				}, {
					itemId: 'option.delete',
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
					request: {
						confirmBox: {
							type: 'delete',
							msg: 'Remove SSH keypair "{cloud_key_name}"?'
						},
						processBox: {
							type: 'delete',
							msg: 'Removing SSH keypair ...'
						},
						url: '/sshkeys/xRemove',
						dataHandler: function (data) {
							return { sshKeyId: Ext.encode([data['id']])};
						},
						success: function () {
							store.load();
						}
					}
				}]
			}
		],
        
		multiSelect: true,
		selModel: {
            selType: 'selectedmodel',
			getVisibility: function(record) {
				return true;
			}
        },

        
		listeners: {
			selectionchange: function(selModel, selections) {
				var toolbar = this.down('scalrpagingtoolbar');
				toolbar.down('#delete').setDisabled(!selections.length);
			}
		},

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
			afterItems: [{
				ui: 'paging',
				itemId: 'delete',
				iconCls: 'x-tbar-delete',
				tooltip: 'Select one or more SSH keypair to remove them',
				disabled: true,
				handler: function() {
					var request = {
						confirmBox: {
							msg: 'Remove selected SSH keypair(s): %s ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Removing selected SSH keypair(s) ...',
							type: 'delete'
						},
						url: '/sshkeys/xRemove',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), data = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						data.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('cloud_key_name'));
					}
					request.params = { sshKeyId: Ext.encode(data) };
					Scalr.Request(request);
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}, ' ', {
				xtype: 'combo',
				fieldLabel: 'Farm',
				labelWidth: 34,
				width: 250,
                itemId: 'farmId',
				matchFieldWidth: false,
				listConfig: {
					minWidth: 150
				},
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams['farms'],
					proxy: 'object'
				},
				editable: false,
				queryMode: 'local',
				value: 0,
				valueField: 'id',
				displayField: 'name',
				listeners: {
					change: function () {
						this.up('panel').store.proxy.extraParams['farmId'] = this.getValue();
						this.up('panel').store.loadPage(1);
					}
				}
			}]
		}]
	});
});

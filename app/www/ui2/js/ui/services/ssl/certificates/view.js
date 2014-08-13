Scalr.regPage('Scalr.ui.services.ssl.certificates.view', function () {
	var store = Ext.create('store.store', {
		fields: [ 'id', 'name', 'privateKey', 'privateKeyPassword', 'certificate', 'caBundle' ],
		proxy: {
			type: 'scalr.paging',
			url: '/services/ssl/certificates/xListCertificates/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'SSL certificates',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-services-ssl-certificates-view',
		stateful: true,
		plugins: [{
			ptype: 'gridstore'
		}, {
			ptype: 'rowexpander',
			rowBodyTpl: [
				'<p><b>Certificate:</b> <tpl if="certificate">{certificate}<tpl else>empty</tpl></p>',
				'<p><b>Certificate chain:</b> <tpl if="caBundle">{caBundle}<tpl else>empty</tpl></p>'
			]
		}],

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'SSL certificates',
				href: '#/services/ssl/certificates/view'
			}
		}],

		viewConfig: {
			emptyText: "No ssl certificates found",
			loadingText: 'Loading certificates ...'
		},

		columns:[
			{ header: "ID", width: 60, dataIndex: 'id', sortable: true },
			{ header: "Name", flex: 1, dataIndex: 'name', sortable: true },
			{
				header: 'Private key', width: 150, dataIndex: 'privateKey', sortable: false, xtype: 'templatecolumn', align: 'center',
				tpl: '<tpl if="privateKey"><img src="/ui2/images/icons/true.png"><tpl else><img src="/ui2/images/icons/false.png"></tpl>'
			}, {
                header: 'Private key password', width: 150, dataIndex: 'privateKeyPassword', sortable: false, xtype: 'templatecolumn', align: 'center',
                tpl: '<tpl if="privateKeyPassword"><img src="/ui2/images/icons/true.png"><tpl else><img src="/ui2/images/icons/false.png"></tpl>'
            }, {
				header: 'Certificate', width: 150, dataIndex: 'certificate', sortable: false, xtype: 'templatecolumn', align: 'center',
				tpl: '<tpl if="!!certificate"><img src="/ui2/images/icons/true.png"><tpl else><img src="/ui2/images/icons/false.png"></tpl>'
			}, {
				header: 'Certificate chain', width: 150, dataIndex: 'caBundle', sortable: false, xtype: 'templatecolumn', align: 'center',
				tpl: '<tpl if="!!caBundle"><img src="/ui2/images/icons/true.png"><tpl else><img src="/ui2/images/icons/false.png"></tpl>'
			}, {
				xtype: 'optionscolumn2',
				menu: [{
					text: 'Edit',
                    iconCls: 'x-menu-icon-edit',
					href: "#/services/ssl/certificates/{id}/edit"
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
			afterItems: [{
				ui: 'paging',
				itemId: 'delete',
				iconCls: 'x-tbar-delete',
				tooltip: 'Select one or more certificates to delete them',
				disabled: true,
				handler: function() {
					var request = {
						confirmBox: {
							type: 'delete',
							msg: 'Delete selected certificates(s): %s ?'
						},
						processBox: {
							type: 'delete',
							msg: 'Deleting selected certificates ...'
						},
						url: '/services/ssl/certificates/xRemove/',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), ids = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						ids.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('name'));
					}
					request.params = { certs: Ext.encode(ids) };
					Scalr.Request(request);
				}
			}],
			beforeItems: [{
                text: 'Add SSL certificate',
                cls: 'x-btn-green-bg',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/services/ssl/certificates/create');
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}]
		}]
	});
});

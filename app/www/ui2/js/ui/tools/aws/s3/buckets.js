Scalr.regPage('Scalr.ui.tools.aws.s3.buckets', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'name' , 'farmId', 'farmName', 'cfid', 'cfurl', 'cname', 'status', 'enabled'],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/aws/s3/xListBuckets/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		title: 'Tools &raquo; Amazon Web Services &raquo; S3 &raquo; Buckets &amp; Cloudfront',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		store: store,
		stateId: 'grid-tools-aws-s3-buckets',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},
		tools: [{
			xtype: 'gridcolumnstool'
		}],
		viewConfig: {
			emptyText: "No buckets found",
			loadingText: 'Loading buckets ...'
		},
		columns: [
			{ header: "Bucket name", flex: 2, dataIndex: 'name', sortable: false },
			{ header: "Used by", flex: 1, dataIndex: 'farmId', xtype: 'templatecolumn', sortable: true, tpl:
				'<tpl if="farmId"><a href="#/farms/{farmId}/view">{farmName}</a></tpl>' +
				'<tpl if="! farmId"><img src="/ui2/images/icons/false.png"></tpl>'
			},
			{ header: "Cloudfront ID", flex: 2, dataIndex: 'cfid', sortable: false},
			{ header: "Cloudfront URL", flex: 2, dataIndex: 'cfurl', sortable: false},
			{ header: "CNAME", flex: 3, dataIndex: 'cname', sortable: false},
			{ header: "Status", width: 80, dataIndex: 'status', sortable: false},
			{ header: "Enabled", width: 80, dataIndex: 'enabled', xtype: 'templatecolumn', sortable: false, tpl:
				'<tpl if="enabled == \'true\'"><img src="/ui2/images/icons/true.png"></tpl>' +
				'<tpl if="enabled == \'false\' || !enabled"><img src="/ui2/images/icons/false.png"></tpl>'
		}, {
			xtype: 'optionscolumn2',
			width: 120,
			menu: [{
				itemId: "option.create_dist",
				text: 'Create distribution',
				iconCls: 'x-menu-icon-create',
				href: "#/tools/aws/s3/manageDistribution?bucketName={name}",
                getVisibility: function(data) {
                    return !data['cfid'];
                },
			}, {
				itemId: "option.delete_dist",
				iconCls: 'x-menu-icon-delete',
				text: 'Remove distribution',
                getVisibility: function(data) {
                    return data['cfid'] && data['status'] === 'Deployed' && data['enabled'] == 'false';
                },
				menuHandler: function(data) {
					Scalr.Request({
						confirmBox: {
							msg: 'Remove distribution ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Removing distribution ...',
							type: 'delete'
						},
						scope: this,
						url: '/tools/aws/s3/xDeleteDistribution',
						params: {id: data['cfid'], cfurl: data['cfurl'], cname: data['cname']},
						success: function (data, response, options){
							store.load();
						}
					});
				}
			},{ 
                itemId: "option.disable_dist",
                text: 'Disable distribution',
                getVisibility: function(data) {
                    return data['enabled'] == "true" && data['cfid'];
                },
				menuHandler: function(data) {
					Scalr.Request({
						processBox: {
							type: 'action'
						},
						scope: this,
						url: '/tools/aws/s3/xUpdateDistribution',
						params: {id: data['cfid'], enabled: false},
						success: function (data, response, options){
							store.load();
						}
					});
				}
			},{ 
                itemId: "option.enable_dist",
                text: 'Enable distribution',
                getVisibility: function(data) {
                    return data['enabled'] == "false" && data['cfid'];
                },
				menuHandler: function(data) {
					Scalr.Request({
						processBox: {
							type: 'action'
						},
						scope: this,
						url: '/tools/aws/s3/xUpdateDistribution',
						params: {id: data['cfid'], enabled: true},
						success: function (data, response, options){
							store.load();
						}
					});
				}
			},
				new Ext.menu.Separator({itemId: "option.editSep"}),
			{
                itemId: "option.delete_backet",
                iconCls: 'x-menu-icon-delete',
                text: 'Delete bucket',
				menuHandler: function(data) {
					if(data['cfid']) {
						Scalr.message.Warning('Remove distribution before deleting');
					} else {
						Scalr.Request({
							confirmBox: {
								msg: 'Remove selected bucket ?',
								type: 'delete'
							},
							processBox: {
								msg: 'Removing bucket ...',
								type: 'delete'
							},
							scope: this,
							url: '/tools/aws/s3/xDeleteBucket',
							params: { buckets: Ext.encode([ data['name'] ]) },
							success: function (data, response, options){
								store.load();
							}
						});
					}
				}
			}]
		}],

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
                text: 'Add bucket',
                cls: 'x-btn-green-bg',
                handler: function() {
                    Scalr.Request({
                        confirmBox: {
                            title: 'Create new Bucket',
                            width: 480,
                            form: [{
                                xtype: 'fieldset',
                                cls: 'x-fieldset-separator-none',
                                defaults: {
                                    anchor: '100%'
                                },
                                items: [{
                                    xtype: 'combo',
                                    name: 'location',
                                    fieldLabel: 'Select location',
                                    editable: false,
                                    allowBlank: false,
                                    queryMode: 'local',
                                    store: {
                                        fields: [ 'id', 'name' ],
                                        data: moduleParams.locations,
                                        proxy: 'object'
                                    },
                                    valueField: 'id',
                                    displayField: 'name'
                                },{
                                    xtype: 'textfield',
                                    name: 'bucketName',
                                    fieldLabel: 'Bucket Name',
                                    allowBlank: false
                                }]
                            }],
                            formValidate: true,
                            ok: 'Add'
                        },
                        processBox: {
                            msg: 'Creating new Bucket...',
                            type: 'save'
                        },
                        scope: this,
                        url: '/tools/aws/s3/xCreateBucket',
                        success: function (data, response, options){
                            store.load();
                        }
                    });
                }
            }],
			afterItems: [{
                ui: 'paging',
                itemId: 'delete',
                iconCls: 'x-tbar-delete',
                tooltip: 'Select one or more buckets to delete them',
                disabled: true,
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected bucket(s): %s ?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Deleting bucket(s) ...'
                        },
                        url: '/tools/aws/s3/xDeleteBucket',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), objects = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        objects.push(records[i].get('name'));
                        request.confirmBox.objects.push(records[i].get('name'));
                    }
                    request.params = { buckets: Ext.encode(objects) };
                    Scalr.Request(request);
                }
			}],
			items: [{
				xtype: 'filterfield',
				store: store
			}]
		}]
	});
});
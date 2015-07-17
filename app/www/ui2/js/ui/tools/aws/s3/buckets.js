Scalr.regPage('Scalr.ui.tools.aws.s3.buckets', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [ 'name' , {name: 'farmId', defaultValue: null}, 'farmName', 'cfid', 'cfurl', 'cname', 'status', {name: 'enabled', defaultValue: null}],
		proxy: {
			type: 'scalr.paging',
			url: '/tools/aws/s3/xListBuckets/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'AWS Cloudfront'
		},
		store: store,
		stateId: 'grid-tools-aws-s3-buckets',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],
		viewConfig: {
			emptyText: "No buckets found",
			loadingText: 'Loading buckets ...'
		},
		columns: [
			{ header: "Bucket", flex: 2, dataIndex: 'name', sortable: false },
			{ header: "Used by", flex: 1, dataIndex: 'farmId', xtype: 'templatecolumn', sortable: true, tpl:
				'<tpl if="farmId"><a href="#/farms?farmId={farmId}">{farmName}</a></tpl>' +
				'<tpl if="! farmId">&mdash;</tpl>'
			},
			{ header: "Cloudfront ID", flex: 2, dataIndex: 'cfid', sortable: false},
			{ header: "Cloudfront URL", flex: 2, dataIndex: 'cfurl', sortable: false},
			{ header: "CNAME", flex: 3, dataIndex: 'cname', sortable: false},
			{ header: "Status", width: 80, dataIndex: 'status', sortable: false},
			{ header: "Enabled", width: 80, dataIndex: 'enabled', xtype: 'templatecolumn', sortable: false, tpl:
				'<tpl if="enabled == \'true\'"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div></tpl>' +
				'<tpl if="enabled == \'false\' || !enabled">&mdash;</tpl>'
		}, {
			xtype: 'optionscolumn',
			width: 120,
			menu: [{
				text: 'Create distribution',
				iconCls: 'x-menu-icon-create',
                showAsQuickAction: true,
				href: "#/tools/aws/s3/manageDistribution?bucketName={name}",
                getVisibility: function(data) {
                    return !data['cfid'];
                }
			}, {
				iconCls: 'x-menu-icon-delete',
				text: 'Remove distribution',
                showAsQuickAction: true,
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
                text: 'Disable distribution',
                iconCls: 'x-menu-icon-disable',
                showAsQuickAction: true,
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
                text: 'Enable distribution',
                iconCls: 'x-menu-icon-enable',
                showAsQuickAction: true,
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
			}]
		}],

        selModel: 'selectedmodel',
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
                text: 'New bucket',
                cls: 'x-btn-green',
                handler: function() {
                    Scalr.Request({
                        confirmBox: {
                            title: 'Create new Bucket',
                            width: 520,
                            form: [{
                                xtype: 'fieldset',
                                cls: 'x-fieldset-separator-none',
                                defaults: {
                                    anchor: '100%',
                                    labelWidth: 130
                                },
                                items: [{
                                    xtype: 'combo',
                                    name: 'location',
                                    fieldLabel: 'Cloud location',
                                    emptyText: 'Select location',
                                    editable: false,
                                    allowBlank: false,
                                    queryMode: 'local',
                                    plugins: {
                                        ptype: 'fieldinnericoncloud',
                                        platform: 'ec2'
                                    },
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
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
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
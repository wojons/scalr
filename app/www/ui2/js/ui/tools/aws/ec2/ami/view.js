Scalr.regPage('Scalr.ui.tools.aws.ec2.ami.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'id', 'name', 'status', 'imageName', 'imageVirt', 'imageState', 'imageIsPublic'
		],
		proxy: {
            type: 'ajax',
            url: '/tools/aws/ec2/ami/xList/',
            reader: {
                type: 'json',
                rootProperty: 'data'
            }
        },
        autoLoad: true
		//remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'AWS EC2 AMI'
		},
		store: store,
		stateId: 'grid-tools-aws-ec2-ami-view',
		stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],

		viewConfig: {
			emptyText: 'No images found',
			loadingText: 'Loading images ...'
		},

		columns: [
			{ header: "ID", flex: 1, dataIndex: 'id', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="status != &quot;none&quot;"><a href="#/images?id={id}">{id}</a><tpl else>{id}</tpl>'
            },
			{ header: "Name", flex: 1, dataIndex: 'name', sortable: true },
			{ header: "Status", flex: 1, dataIndex: 'status', sortable: true },
			{ header: "Image Name", flex: 1, dataIndex: 'imageName', sortable: true },
			{ header: "Image virt", flex: 1, dataIndex: 'imageVirt', sortable: true },
            { header: "is Public", flex: 1, dataIndex: 'imageIsPublic', sortable: true },
			{ header: "Image state", flex: 1, dataIndex: 'imageState', sortable: true
			}, {
				xtype: 'optionscolumn',
                hidden: true,
				getVisibility: function (record) {
					return record.get('status') !== 'deleting' && record.get('status') !== 'deleted'
				},
				menu: [{
					text: 'CloudWatch statistics',
					iconCls: 'x-menu-icon-statsload',
					menuHandler: function (data) {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/cloudwatch?objectId=' + data['volumeId'] + '&object=VolumeId&namespace=AWS/EBS&region=' + store.proxy.extraParams.cloudLocation);
					}
				},{
					iconCls: 'x-menu-icon-attach',
					text: 'Attach',
					menuHandler: function(data) {
                        Scalr.event.fireEvent('redirect', "#/tools/aws/ec2/ebs/volumes/" + data['volumeId'] + "/attach?cloudLocation=" + store.proxy.extraParams.cloudLocation);
					},
                    getVisibility: function(data) {
                        return !data['mysqMasterVolume'] && !data['instanceId'];
                    }
				},{
					iconCls: 'x-menu-icon-detach',
					text: 'Detach',
                    getVisibility: function(data) {
                        return !data['mysqMasterVolume'] && data['instanceId'];
                    },
					request: {
						confirmBox: {
							type: 'action',
							//TODO: Add form: checkbox: forceDetach
							msg: 'Are you sure want to detach "{volumeId}" volume?'
						},
						processBox: {
							type: 'action',
							msg: 'Detaching EBS volume ...'
						},
						url: '/tools/aws/ec2/ebs/volumes/xDetach/',
						dataHandler: function (data) {
							return { volumeId: data['volumeId'], cloudLocation: store.proxy.extraParams.cloudLocation };
						},
						success: function (data) {
							store.load();
						}
					}
				},{
					xtype: 'menuseparator'
				},{
					text: 'Auto-snapshot settings',
					iconCls: 'x-menu-icon-autosnapshotsettings',
					menuHandler: function(data) {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/autoSnapshotSettings?type=ebs&objectId=' + data['volumeId'] + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
					}
				}, {
					xtype: 'menuseparator'
				}, {
					text: 'Create snapshot',
					iconCls: 'x-menu-icon-create',
					request: {
						confirmBox: {
							type: 'action',
							msg: 'Are you sure want to create snapshot for EBS volume "{volumeId}"?'
						},
						processBox: {
							type: 'action',
							msg: 'Creating EBS snapshot ...'
						},
						url: '/tools/aws/ec2/ebs/snapshots/xCreate/',
						dataHandler: function (data) {
							return { volumeId: data['volumeId'], cloudLocation: store.proxy.extraParams.cloudLocation };
						},
						success: function (data) {
                            Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/ebs/snapshots/' + data.data.snapshotId + '/view?cloudLocation=' + store.proxy.extraParams.cloudLocation);
						}
					}
				}, {
					text: 'View snapshots',
					iconCls: 'x-menu-icon-view',
					menuHandler: function(data) {
                        Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/ebs/snapshots?volumeId=' + data['volumeId'] + '&cloudLocation=' + store.proxy.extraParams.cloudLocation);
					}
				}, {
					xtype: 'menuseparator'
				}, {
					text: 'Delete',
					iconCls: 'x-menu-icon-delete',
					request: {
						confirmBox: {
							type: 'delete',
							msg: 'Are you sure want to delete EBS volume "{volumeId}"?'
						},
						processBox: {
							type: 'delete',
							msg: 'Deleting EBS volume ...'
						},
						url: '/tools/aws/ec2/ebs/volumes/xRemove/',
						dataHandler: function (data) {
							return { volumeId: Ext.encode([data['volumeId']]), cloudLocation: store.proxy.extraParams.cloudLocation };
						},
						success: function () {
							store.load();
						}
					}
				}]
			}
		],

		selModel: {
			selType: 'selectedmodel',
			getVisibility: function(record) {
				return record.get('status') == 'deleting' ? false : true;
			}
		},

		listeners: {
			selectionchange: function(selModel, selections) {
				var toolbar = this.down('scalrpagingtoolbar');
				toolbar.down('#delete').setDisabled(!selections.length);
			}
		},

		dockedItems: [{
			xtype: 'toolbar',
			store: store,
			dock: 'top',
			items: [{
				xtype: 'filterfield',
				store: store,
                filterFields: [ 'id' ]
			}, ' ', {
                xtype: 'cloudlocationfield',
                platforms: ['ec2'],
				gridStore: store
			}, '->', {
                itemId: 'delete',
                disabled: true,
                hidden: true,
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Delete',
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Delete selected EBS volume(s): %s ?'
                        },
                        processBox: {
                            type: 'delete'
                        },
                        url: '/tools/aws/ec2/ebs/volumes/xRemove/',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), volumes = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        volumes.push(records[i].get('volumeId'));
                        request.confirmBox.objects.push(records[i].get('volumeId'))
                    }
                    request.params = { volumeId: Ext.encode(volumes), cloudLocation: store.proxy.extraParams.cloudLocation };
                    Scalr.Request(request);
                }
            }]
		}]
	});
});

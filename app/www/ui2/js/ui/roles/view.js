Scalr.regPage('Scalr.ui.roles.view', function (loadParams, moduleParams) {
    var platformFilterItems = [{
        text: 'All clouds',
        value: null,
        iconCls: 'x-icon-osfamily-small'
    }];

    Ext.Object.each(moduleParams['platforms'], function(key, value){
        platformFilterItems.push({
            text: value.name,
            value: key,
            iconCls: 'x-icon-platform-small x-icon-platform-small-' + key
        });
    });

	var store = Ext.create('store.store', {
		fields: [
			{name: 'id', type: 'int'},
			{name: 'client_id', type: 'int'},
			'name', 'origin', 'client_name', 'behaviors', 'os', 'osFamily', 'platforms','used_servers','status','behaviors_name'
		],
		proxy: {
			type: 'scalr.paging',
			extraParams: loadParams,
			url: '/roles/xListRoles/'
		},
		remoteSort: true
	});

	var confirmationRemovalOptions = {
		xtype: 'fieldset',
		title: 'Removal parameters',
		hidden: moduleParams['isScalrAdmin'],
		items: [{
			xtype: 'checkbox',
			boxLabel: 'Remove image from cloud',
			inputValue: 1,
			checked: false,
			name: 'removeFromCloud'
		}]
	};
	
	var cloneOptions = {
        xtype: 'fieldset',
        cls: 'x-fieldset-separator-none',
        items: [{
            xtype: 'textfield',
            fieldLabel: 'New role name',
            editable: false,
            queryMode: 'local',
            value: '',
            name: 'newRoleName',
            labelWidth: 100,
            anchor: '100%'
        }]
	};

	return Ext.create('Ext.grid.Panel', {
		title: 'Roles &raquo; View',
		scalrOptions: {
			'reload': false,
			'maximize': 'all'
		},
		scalrReconfigureParams: { roleId: '', client_id: '' },
		store: store,
		stateId: 'grid-roles-view',
		stateful: true,
		plugins: {
			ptype: 'gridstore'
		},

		tools: [{
			xtype: 'gridcolumnstool'
		}, {
			xtype: 'favoritetool',
			favorite: {
				text: 'Roles',
				href: '#/roles/view'
			}
		}],

		viewConfig: {
			emptyText: "No roles found"
		},

		columns: [
            { header: "ID", width: 80, dataIndex: 'id', sortable: true },
			{ header: "Role name", flex: 2, dataIndex: 'name', sortable: true },
			{ header: "OS", width: 180, dataIndex: 'os', sortable: true, xtype: 'templatecolumn', tpl: '<img style="margin:0 3px"  class="x-icon-osfamily-small x-icon-osfamily-small-{osFamily}" src="' + Ext.BLANK_IMAGE_URL + '"/> {os}' },
			{ header: "Owner", width: 70, dataIndex: 'client_name', sortable: false},
			{ header: "Automation", flex: 1, dataIndex: 'behaviors_name', sortable: false },
			{ header: "Available on", width: 110, dataIndex: 'platforms', sortable: false, xtype: 'templatecolumn', tpl: 
            '<tpl foreach="platforms">'+
                '<img style="margin:0 3px"  class="x-icon-platform-small x-icon-platform-small-{$}" title="{.}" src="' + Ext.BLANK_IMAGE_URL + '"/>'+
            '</tpl>'
            },
			{ header: "Status", width: 100, dataIndex: 'status', sortable: false, xtype: 'templatecolumn', tpl:
				'{status} ({used_servers})'
			},
			{
				xtype: 'optionscolumn',
				optionsMenu: [
					{ itemId: "option.view", iconCls: 'x-menu-icon-info', text:'View details', href: "#/roles/{id}/info" },
					{ itemId: "option.clone", iconCls: 'x-menu-icon-clone', text: 'Clone', request: {
						confirmBox: {
							type: 'action',
							form: cloneOptions,
							msg: 'Clone "{name}" role" ?'
						},
						processBox: {
							type: 'action',
							msg: 'Cloning role. Please wait ...'
						},
						url: '/roles/xClone/',
						dataHandler: function (record) {
							return { roleId: record.get('id') };
						},
						success: function () {
							Scalr.message.Success("Role successfully cloned");
							store.load();
						}
					}},
					{
						itemId: 'option.migrate',
						iconCls: 'x-menu-icon-clone',
						text: 'Copy to another EC2 region',
						request: {
							processBox: {
								type:'action'
							},
							url: '/roles/xGetMigrateDetails/',
							dataHandler: function (record) {
								return { roleId: record.get('id') };
							},
							success: function (data) {
								Scalr.Request({
									confirmBox: {
										type: 'action',
										msg: 'Copying images allows you to use roles in additional regions',
										formWidth: 600,
										form: [{
											xtype: 'fieldset',
											title: 'Region copy',
                                            defaults: {
                                                anchor: '100%',
                                                labelWidth: 120
                                            },
											items: [{
												xtype: 'displayfield',
												fieldLabel: 'Role name',
												value: data['roleName']	
											},{
												xtype: 'combo',
												fieldLabel: 'Source region',
												store: {
													fields: [ 'cloudLocation', 'name' ],
													proxy: 'object',
													data: data['availableSources']
												},
												autoSetValue: true,
												valueField: 'cloudLocation',
												displayField: 'name',
												editable: false,
												queryMode: 'local',
												name: 'sourceRegion'
											}, {
												xtype: 'combo',
												fieldLabel: 'Destination region',
												store: {
													fields: [ 'cloudLocation', 'name' ],
													proxy: 'object',
													data: data['availableDestinations']
												},
												autoSetValue: true,
												valueField: 'cloudLocation',
												displayField: 'name',
												editable: false,
												queryMode: 'local',
												name: 'destinationRegion'
											}]
										}]
									},
									processBox: {
										type: 'action'
									},
									url: '/roles/xMigrate',
									params: {roleId: data.roleId},
									success: function () {
										store.load();
									}
								});
							}
						}
					},
					{ itemId: "option.edit", iconCls: 'x-menu-icon-edit', text:'Edit', href: "#/roles/{id}/edit" }
				],

				getOptionVisibility: function (item, record) {
					if (item.itemId == 'option.view')
						return true;
                    if (item.itemId == 'option.clone')
                        return Scalr.isAllowed('FARMS_ROLES', 'clone');

					if (item.itemId == 'option.migrate')
						return (record.get('platforms')['ec2'] && record.get('origin') == 'CUSTOM');

					if (record.get('origin') == 'CUSTOM') {
						if (item.itemId == 'option.edit') {
							if (! moduleParams.isScalrAdmin)
								return true;
							else
								return false;
						}
						return true;
					}
					else {
						return moduleParams.isScalrAdmin;
					}
				},

				getVisibility: function (record) {
					return (record.get('status').indexOf('Deleting') == -1);
				}
			}
		],

		multiSelect: true,
		selType: 'selectedmodel',

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
                text: 'Add role',
                cls: 'x-btn-green-bg',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/roles/' + (Scalr.user['type'] === 'ScalrAdmin' ? 'edit' : 'builder'));
				}
			}],
			afterItems: [{
				ui: 'paging',
				itemId: 'delete',
				iconCls: 'x-tbar-delete',
				tooltip: 'Select one or more roles to delete them',
				disabled: true,
				handler: function() {
					var request = {
						confirmBox: {
							msg: 'Remove selected role(s): %s ?',
								type: 'delete',
								form: confirmationRemovalOptions
						},
						processBox: {
							msg: 'Removing selected role(s) ...',
								type: 'delete'
						},
						url: '/roles/xRemove',
						success: function() {
							store.load();
						}
					}, records = this.up('grid').getSelectionModel().getSelection(), roles = [];

					request.confirmBox.objects = [];
					for (var i = 0, len = records.length; i < len; i++) {
						roles.push(records[i].get('id'));
						request.confirmBox.objects.push(records[i].get('name'));
					}
					request.params = { roles: Ext.encode(roles) };
					Scalr.Request(request);
				}
			}],
			items: [{
				xtype: 'filterfield',
				store: store,
				width: 240,
				form: {
					items: [{
						xtype: 'radiogroup',
						name: 'status',
						fieldLabel: 'Status',
						labelAlign: 'top',
						items: [{
							boxLabel: 'All',
							name: 'status',
							inputValue: ''
						}, {
							boxLabel: 'Used',
							name: 'status',
							inputValue: 'Used'
						}, {
							boxLabel: 'Not used',
							name: 'status',
							inputValue: 'Unused'
						}]
					}]
				}
			}, ' ', {
                xtype: 'cyclealt',
                prependText: 'Cloud: ',
                text: 'Cloud: All',
                getItemIconCls: false,
                width: 120,
                hidden: platformFilterItems.length === 2,
                style: 'text-align:center',
                changeHandler: function(comp, item) {
                    store.proxy.extraParams.platform = item.value;
                    delete store.proxy.extraParams.cloudLocation;
                    comp.next('#location').setPlatform(item.value);
                    store.loadPage(1);
                },
                getItemText: function(item) {
                    return item.value ? '<img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '" />' : 'All';
                },
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: platformFilterItems
                }
            }, ' ', {
				xtype: 'combo',
                itemId: 'location',
				matchFieldWidth: false,
				width: 200,
				editable: false,
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
				displayField: 'name',
                emptyText: 'All locations',
				valueField: 'id',
				value: '',
				queryMode: 'local',
				listeners: {
					change: function() {
						store.proxy.extraParams.cloudLocation = this.getValue();
						store.loadPage(1);
					},
                    afterrender: {
                        fn: function() {
                            this.setPlatform();
                        },
                        single: true
                    }
				},
                setPlatform: function(platform) {
                    var locations = {'': 'All locations'};
                    Ext.Object.each(moduleParams['platforms'], function(key, value) {
                        if (!platform || key === platform && Ext.Object.getSize(value.locations)) {
                            Ext.Object.each(value.locations, function(key, value){
                                locations[key] = value;
                            });
                        }
                    });
                    this.store.load({data: locations});
                    this.suspendEvents(false);
                    this.reset();
                    this.resumeEvents();
                }
			}, ' ', 'Owner:', {
				xtype: 'buttongroupfield',
				value: '',
				items: [{
					xtype: 'button',
					text: 'All',
					value: '',
					width: 70
				}, {
					xtype: 'button',
					text: 'Scalr',
					width: 70,
					value: 'Shared'
				}, {
					xtype: 'button',
					text: 'Private',
					width: 70,
					value: 'Custom'
				}],
				listeners: {
					change: function (field, value) {
						store.proxy.extraParams.origin = value;
						store.loadPage(1);
					}
				}
			}]
		}]
	});
});

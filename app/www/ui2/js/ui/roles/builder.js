Scalr.regPage('Scalr.ui.roles.builder', function (loadParams, moduleParams) {
	var platforms = moduleParams.platforms || {},
		rootDeviceTypeFilterEnabled = true,
		chefFieldsetEnabled = true,//Scalr.flags['betaMode'],
		advancedFieldsetEnabled = Scalr.flags['betaMode'],
		result = {},
		behaviors,
		buttons = {
			platforms: [], 
			behaviors: [], 
			addons: []
		};
	
	if (Ext.Object.getSize(platforms) == 0) {
		Scalr.message.Error('The Role Builder does not support your enabled clouds. <br/>Please <a href="#/roles/import">Create a role from Non-Scalr server</a> instead.');
		//Scalr.event.fireEvent('redirect', '#/roles/import', true);
		return false;
	}

	behaviors = [
		{name: 'mysql2', disable: {behavior: ['postgresql', 'redis', 'mongodb', 'percona']}},
		{name: 'postgresql', disable: {platform: ['gce'], behavior: ['redis', 'mongodb', 'percona', 'mysql2']}},
		{name: 'percona', disable: {behavior: ['postgresql', 'redis', 'mongodb', 'mysql2']}},
		{name: 'app', disable: {behavior:['www', 'tomcat']}},
        {name: 'tomcat', disable: {behavior:['app'], os:['oel', {family: 'ubuntu', version: ['10.04']}]}},
        {name: 'haproxy', disable: {behavior:['www']}},
		{name: 'www', disable: {behavior:['app', 'haproxy']}},
		{name: 'memcached'},
		{name: 'redis', disable: {behavior: ['postgresql', 'mongodb', 'percona', 'mysql2']}},
		{name: 'rabbitmq', disable: {os: ['rhel', 'oel']}},
		{name: 'mongodb', disable: {platform: ['gce', 'rackspacengus', 'rackspacenguk'], behavior: ['postgresql', 'redis', 'percona', 'mysql2']}},
		//{name: 'mysqlproxy', addon: true, disable: {os: ['centos', 'oel', 'rhel']}},//{family: 'ubuntu', version: ['10.04']}
		{name: 'chef', addon: true, button: {pressed: true, toggle: Ext.emptyFn}}
	];
	
	//behaviors buttons
	for (var i=0, len=behaviors.length; i<len; i++) {
		var button = {
            iconCls: 'x-icon-behavior-large x-icon-behavior-large-' + behaviors[i].name,
            text: Scalr.utils.beautifyBehavior(behaviors[i].name),
			behavior: behaviors[i].name
		};
		if (behaviors[i].button) {
			Ext.apply(button, behaviors[i].button);
		}
		buttons[behaviors[i].addon ? 'addons' : 'behaviors'].push(button);
	}
	
	//platform buttons
	for (var i in platforms) {
		buttons['platforms'].push({
            text: Scalr.utils.getPlatformName(i, true),
            iconCls: 'x-icon-platform-large x-icon-platform-large-' + i,
			value: i
		});
	}
	
	var toggleBehaviors = function(enable) {
		panel.down('#settings-behaviors').items.each(function(){
			this[enable?'enable':'disable']();
			this.setTooltip(enable?'':'Please select operating system.');
		});
		panel.down('#settings-addons').items.each(function(){
			this[enable?'enable':'disable']();
			this.setTooltip(enable?'':'Please select operating system.');
		});
	}
	
	var onSelectBehavior = function() {
		if (this.pressed) {
			result.behaviors.push(this.behavior);
		} else {
			Ext.Array.remove(result.behaviors, this.behavior);
		}
		panel.refreshBehaviors();
        refreshSoftwareWarning();
	};
	
	var onSelectAddon = function() {
		if (this.pressed) {
			result.addons.push(this.behavior);
		} else {
			Ext.Array.remove(result.addons, this.behavior);
		}
		if (this.behavior == 'chef') {
			panel.down('#chefsettings')[this.pressed?'show':'hide']();
		}
        refreshSoftwareWarning();
	};
    
    var refreshSoftwareWarning = function() {
        panel.down('#softwarewarning').setVisible(result.behaviors.length > 0 || result.addons.length > 1);
    };

	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			maximize: 'all'
		},
        title: 'Roles &raquo; Builder',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
        plugins: {
            ptype: 'localcachedrequest',
            crscope: 'rolebuilder'
        },
		
		items:[{
			xtype: 'container',
            cls: 'x-panel-column-left',
            autoScroll: true,
			width: 494,
			layout: {
				type: 'vbox',
				align: 'stretch'
			},
			items: [{
				xtype: 'fieldset',
				itemId: 'leftcol',
                cls: 'x-fieldset-separator-none',
                title: 'Location and operating system',
				layout: {
					type: 'vbox',
					align: 'stretch'
				},
				items: [{
					xtype: 'label',
					text: 'Location:'
				},{
					xtype: 'container',
					layout: {
						type: 'hbox',
						pack: 'center'
					},
					margin: '0 0 10 0',
					items: {
						xtype: 'cloudlocationmap',
						itemId: 'locationmap',
						platforms: moduleParams['platforms'],
						size: 'large',
						listeners: {
                            beforeselectlocation: function() {
                                return !panel.serverId;
                            },
							selectlocation: function(location){
								panel.down('#cloud_location').setValue(location);
							}
						}
					}
				},{
					xtype: 'combo',
					margin: '0 0 10 0',
					itemId: 'cloud_location',
					editable: false,
					valueField: 'id',
					displayField: 'name',
					queryMode: 'local',
					store: {
						fields: ['id', 'name']
					},
					listeners: {
						change: function(comp, value) {
							panel.fireEvent('selectlocation', value);
							var locations = [];
							this.store.data.each(function(rec){
								locations.push(rec.get('id'));
							});
							panel.down('#locationmap').selectLocation(result.platform, result.cloud_location, locations, 'world');
						}
					}
				},{
					xtype: 'buttongroupfield',
					itemId: 'architecture',
					fieldLabel: 'Architecture',
					cls: 'hideoncustomimage',
					margin: '10 0 0 0',
					labelWidth: 110,
					listeners: {
						change: function(comp, value) {
							panel.fireEvent('selectarchitecture', value);
						}
					},
					defaults: {
						width: 110
					},
					items: [{
						text: '64 bit',
						value: 'x86_64'
					},{
						text: '32 bit',
						value: 'i386'
					}]
				},{
					xtype: 'container',
					itemId: 'rootdevicetypewrap',
					cls: 'hideoncustomimage',
					hidden: true,
					layout: 'hbox',
					margin: '10 0 4 0',
					items: [{
						xtype: 'buttongroupfield',
						itemId: 'root_device_type',
						fieldLabel: 'Root device type',
						labelWidth: 110,
                        width: 348,
						listeners: {
							change: function(comp, value) {
								panel.fireEvent('selectrootdevicetype', value);
							}
						},
						defaults: {
							width: 110
						},
						items: [{
							text: 'EBS',
							value: 'ebs'
						},{
							text: 'Instance store',
							value: 'instance-store'
						}]
					},{
						xtype: 'button',
						itemId: 'hvm',
						enableToggle: true,
						text: 'HVM',
						toggleHandler: function(){
							panel.fireEvent('selecthvm', this.pressed);
						}
					}]
				},{
					xtype: 'label',
					text: 'Operating system:',
					margin: '10 0 0 0',
					cls: 'hideoncustomimage'
				},{
					xtype: 'label',
					text: 'Image ID:',
					margin: '12 0 16 0',
					cls: 'showoncustomimage'
				},{
					xtype: 'textfield',
					itemId: 'imageId',
					cls: 'showoncustomimage',
					allowBlank: false
				}]
			},{
				xtype: 'container',
				itemId: 'images',
				cls: 'hideoncustomimage',
                margin: '0 0 0 18'
			}]
		}, {
			xtype: 'form',
			itemId: 'rightcol',
			layout: 'anchor',
			autoScroll: true,
			flex: 1,
			items: [{
                xtype: 'fieldset',
                title: 'General info and software',
                defaults: {
                    anchor: '100%'
                },
                items: [{
                    xtype: 'textfield',
                    fieldLabel: 'Role name',
                    maxWidth: 536,
                    itemId: 'rolename',
                    submitValue: false,
                    //validateOnChange: false,
                    validator: function (value) {
                        var r = /^[A-z0-9-]+$/, r1 = /^-/, r2 = /-$/;
                        if (r.test(value) && !r1.test(value) && !r2.test(value) && value.length > 2)
                            return true;
                        else
                            return 'Illegal name';
                    }
                },{
                    xtype: 'label',
                    text: 'Software:'
                },{
                    xtype: 'container',
                    margin: '10 0 10 -10',
                    itemId: 'settings-behaviors',
                    defaults: {
                        xtype: 'button',
                        ui: 'simple',
                        disabled: true,
                        enableToggle: true,
                        cls: 'x-btn-simple-large',
                        iconAlign: 'above',
                        margin: '0 0 10 10',
                        tooltip: '',
                        listeners: {
                            toggle: onSelectBehavior
                        }
                    },
                    items: buttons['behaviors']
                },{
                    xtype: 'label',
                    text: 'Addons:'
                }, {
                    xtype: 'container',
                    margin: '10 0 10 -10',
                    itemId: 'settings-addons',
                    defaults: {
                        xtype: 'button',
                        ui: 'simple',
                        enableToggle: true,
                        cls: 'x-btn-simple-large',
                        iconAlign: 'above',
                        margin: '0 0 10 10',
                        listeners: {
                            toggle: onSelectAddon
                        }
                    },
                    items: buttons['addons']
                },{
                    xtype: 'displayfield',
                    itemId: 'softwarewarning',
                    hidden: true,
                    cls: 'x-form-field-info',
                    value: 'Software version is taken from the <b>official OS repositories</b>. Consult your distribution\'s package manager for exact version details.'
                }]
            },{
                xtype: 'fieldset',
                title: 'Install additional software using Chef',
                itemId: 'chefsettings',
                hidden: !chefFieldsetEnabled,
                toggleOnTitleClick: true,
                collapsible: true,
                collapsed: true,
                layout: 'anchor',
                defaults: {
                    maxWidth: 516,
                    anchor: '100%',
                    labelWidth: 120
                },
                listeners: {
                    expand: function(){
                        panel.down('#rightcol').body.scrollTo('top', 3000);
                    }
                },
                items: [{
                    xtype: 'combo',
                    name: 'chef.server',
                    fieldLabel: 'Chef server',
                    valueField: 'id',
                    displayField: 'url',
                    editable: false,
                    value: '',

                    queryCaching: false,
                    clearDataBeforeQuery: true,
                    store: {
                        fields: [ 'url', 'id' ],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'rolebuilder',
                            url: '/services/chef/servers/xListServers/'
                        }
                    },
                    listeners: {
                        change: function(field, value) {
                            if (value) {
                                var f = this.next('[name="chef.environment"]');
                                f.setReadOnly(false, false);

                                // hack, to preselect _default, when none being set
                                if (! f.environmentValue)
                                    f.setValue('_default');

                                f.store.proxy.params['servId'] = value;

                                var f1 = this.next('[name="chef.role"]');
                                f1.setReadOnly(false, false);
                                f1.store.proxy.params['servId'] = value;
                            }
                        }
                    }
                }, {
                    xtype: 'combo',
                    name: 'chef.environment',
                    fieldLabel: 'Chef environment',
                    valueField: 'name',
                    displayField: 'name',
                    editable: false,
                    readOnly: true,
                    value: '',

                    queryCaching: false,
                    clearDataBeforeQuery: true,
                    store: {
                        fields: [ 'name' ],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'rolebuilder',
                            url: '/services/chef/servers/xListEnvironments/'
                        }
                    },
                    listeners: {
                        change: function(field, value) {
                            if (value) {
                                var f = this.next('[name="chef.role"]');
                                f.store.proxy.params['chefEnv'] = value;
                                f.store.proxy.params['servId'] = this.store.proxy.params['servId'];

                                if (f.getValue()) {
                                    f.setValue('');
                                    f.store.load();
                                }
                            }
                        }
                    }
                }, {
                    xtype: 'combo',
                    name: 'chef.role',
                    fieldLabel: 'Chef role',
                    valueField: 'name',
                    displayField: 'name',
                    editable: false,
                    readOnly: true,
                    value: '',

                    queryCaching: false,
                    clearDataBeforeQuery: true,
                    store: {
                        fields: [ 'name', 'chef_type' ],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'rolebuilder',
                            url: '/services/chef/xListRoles'
                        }
                    },
                    listeners: {
                        change: function(field, value) {
                        }
                    }
                },{
                    xtype: 'textarea',
                    fieldLabel: 'Attributes (json)',
                    name: 'chef.attributes',
                    itemId: 'chefattributes',
                    hidden: true,
                    grow: true,
                    growMax: 300,
                    anchor: '100%',
                    maxWidth: null,
                    validateOnChange: false,
                    validator: function(value) {
                        return !Ext.isEmpty(value) && Ext.typeOf(Ext.decode(value, true)) != 'object' ? 'Invalid json' : true;
                    }
                }]
			},{
				xtype: 'fieldset',
				title: 'Advanced options',
				collapsible: true,
				collapsed: true,
                toggleOnTitleClick: true,
				hidden: !advancedFieldsetEnabled,
				layout: 'anchor',
				defaults: {
					maxWidth: 516,
					anchor: '100%',
					labelWidth: 120
				},
				listeners: {
					expand: function(){
						panel.down('#rightcol').body.scrollTo('top', 3000);
					}
				},
				items: [{
					xtype: 'displayfield',
					cls: 'x-form-field-warning',
					value: 'Please use advanced options on your own risk!',
					margin: '8 0 12 0'
				},{
					xtype: 'textfield',
					name: 'advanced.availzone',
					itemId: 'availzone',
					fieldLabel: 'Availability zone'
				},{
					xtype: 'textfield',
					name: 'advanced.servertype',
					itemId: 'servertype',
					fieldLabel: 'Server type'
				},{
					xtype: 'textfield',
					name: 'advanced.scalrbranch',
					itemId: 'scalrbranch',
					fieldLabel: 'Scalarizr branch'
				},{
					xtype: 'textfield',
					name: 'advanced.region',
					itemId: 'region',
					fieldLabel: 'Region'
				},{
					xtype: 'textfield',
					name: 'advanced.overrideImageId',
					itemId: 'overrideImageId',
					hidden: true,
					fieldLabel: 'Image ID'
				},{
					xtype: 'checkbox',
					name: 'advanced.dontterminatefailed',
					itemId: 'dontterminatefailed',
					boxLabel: 'Do not terminate if build failed'
				}]
			}],
			dockedItems:[{
				xtype: 'container',
				dock: 'bottom',
				cls: 'x-docked-buttons',
                style: 'z-index:101;background:transparent',//show toolbar above the mask to make "Cancel" button available
				layout: {
					type: 'hbox',
					pack: 'center'
				},
				items: [{
					xtype: 'button',
					itemId: 'save',
					text: 'Create role',
					disabled: true,
					handler: function() {
						if (chefFieldsetEnabled) {
							if (!panel.down('#chefattributes').isValid()) {
								panel.down('#chefsettings').expand();
								return;
							}
						}
						if (!platforms[result.platform].images.length) {
							if (!panel.down('#imageId').isValid()) {
								panel.down('#imageId').focus();
								return;
							}
						}
						
						if (panel.down('#rolename').isValid()) {
							var r = Scalr.utils.CloneObject(result);
							r.advanced = {};
							r.chef = {};
							
							//role name
							r['roleName'] = panel.down('#rolename').getValue();

							//collect behaviors
							if (! r.behaviors.length) {
								Ext.Array.include(r.behaviors, 'base');
							}
							r['behaviors'] = Ext.encode(Ext.Array.merge(r.behaviors, r.addons));
							delete r.addons;

							//imageId
							if (platforms[r.platform].images.length) {
								r['imageId'] = panel.getImageId();
							} else {
								r['imageId'] = panel.down('#imageId').getValue();
								delete r['architecture'];
							}
							
							//collect fieldsets options
							var extraOptions = panel.down('#rightcol').getValues();
							if (advancedFieldsetEnabled) {//collect advanced options
								Ext.Object.each(extraOptions, function(key, value){
									if (key.match(/^advanced\./ig)) {
										r.advanced[key.replace('advanced.', '')] = value;
									}
								});
								//override imageId
								if (platforms[r.platform].images.length && !Ext.isEmpty(r.advanced['overrideImageId'])) {
									r['imageId'] = r.advanced['overrideImageId'];
								}
								delete r.advanced['overrideImageId'];
							}
							if (chefFieldsetEnabled) {//collect chef options
								Ext.Object.each(extraOptions, function(key, value){
									if (key.match(/^chef./ig)) {
										r.chef[key] = value;
									}
								});
							}
							
							if (loadParams['devScalarizrBranch']) {
								r['devScalarizrBranch'] = loadParams['devScalarizrBranch'];
							}
							
							//backward compatibility
							r['mysqlServerType'] = 'mysql';
							r['location'] = r['cloud_location'];
							
							r.advanced = Ext.encode(r.advanced);
							r.chef = Ext.encode(r.chef);
							
							Scalr.Request({
								processBox: {
									type: 'action'
								},
								url: '/roles/xBuild',
								params: r,
								success: function (data) {
									Scalr.event.fireEvent('redirect', '#/roles/builder?serverId=' + data.serverId);
								}
							});
						} else {
							panel.down('#rightcol').body.scrollTo('top', 0);
							panel.down('#rolename').focus();
						}
					}
				}, {
					xtype: 'button',
					itemId: 'cancel',
					text: 'Cancel',
					handler: function() {
						Scalr.event.fireEvent('close');
					}
				}]
			}]
		},{
            xtype: 'container',
			itemId: 'progresscol',
			layout: 'anchor',
			autoScroll: true,
            hidden: true,
			flex: 1,
            listeners: {
                show: {
                    fn: function() {
                        this.down('#progress').resetProgress();
                        this.down('#log').setBundleTaskId(this.bundleTaskId);
                    },
                    single: true
                }
            },
			items: [{
                xtype: 'fieldset',
                title: 'Role creation progress',
                cls: 'x-fieldset-separator-none',
                itemId: 'progress',
                layout: {
                    type: 'hbox',
                    align: 'stretch'
                },
                defaults: {
                    xtype: 'component',
                    flex: 1,
                    cls: 'scalr-ui-progress-step',
                    height: 26,
                    maxWidth: 300
                },
                resetProgress: function() {
                    this.items.each(function(item, index){
                        if (index === 0) {
                            item.addCls('inprogress');
                            item.removeCls('complete');
                        } else {
                            item.removeCls('inprogress complete');
                        }
                    });
                },
                updateProgress: function(status, state) {
                    var data = {
                        'failed': -1,
                        'pending': 0,
                        'starting-server': 0,
                        'preparing-environment': 1,
                        'installing-software': 1,
                        'creating-role': 3,
                        'success': 5
                    };
                    if (data[status]) {
                        this.items.each(function(item, index){
                            if (index < data[status]) {
                                item.removeCls('inprogress');
                                item.addCls('complete');
                            } else if (index === data[status]) {
                                item.addCls(state || 'inprogress');
                            } else {
                                item.removeCls('inprogress complete');
                            }
                        });
                    }
                },
                items: [{
                    xtype: 'component',
                    html: 'Starting server'
                },{
                    xtype: 'component',
                    html: 'Creating image'
                },{
                    xtype: 'component',
                    html: 'Setting automation'
                },{
                    xtype: 'component',
                    html: 'Building role'
                },{
                    xtype: 'component',
                    html: 'Complete!'
                }]
            },{
                xtype: 'fieldset',
                title: 'Role creation log',
                cls: 'x-fieldset-separator-top-bottom',
                itemId: 'log',
                collapsible: true,
                setBundleTaskId: function(bundleTaskId) {
                    var rightcol = this.up('#progresscol');
                    this.bundleTaskId = bundleTaskId;
                    rightcol.down('#progress').updateProgress('in-progress');
                    this.down('#fullLog').update('<a target="_blank" href="#/bundletasks/'+bundleTaskId+'/logs">View full log in new tab</a>');
                    this.loadBundleTaskData();
                },
                loadBundleTaskData: function() {
                    var me = this;
                    me.stopAutoUpdate();
                    me.request = Scalr.Request({
                        params: {
                            bundleTaskId: this.bundleTaskId
                        },
                        url: '/roles/import/xGetBundleTaskData/',
                        success: function (data) {
                            if (!me.isDestroyed) {
                                var progresscol = me.up('#progresscol');
                                if (data['status'] === 'failed') {
                                    progresscol.down('#progress').updateProgress(data['status']);
                                    panel.onBundleTaskFailed(data['failureReason']);
                                } else {
                                    me.down('grid').store.load({data: data['logs']});
                                    progresscol.down('#progress').updateProgress(data['status']);
                                    if (data['status'] !== 'success') {
                                        me.autoUpdateTask = setTimeout(function(){
                                            me.loadBundleTaskData();
                                        }, 5000);
                                    } else {
                                        panel.onBundleTaskSuccess(data['roleId']);
                                    }
                                }
                            }
                        }
                    });

                },
                autoUpdateTask: null,
                stopAutoUpdate: function(){
                    if (this.request) {
                        Ext.Ajax.abort(this.request);
                    }
                    if (this.autoUpdateTask) {
                        clearTimeout(this.autoUpdateTask);
                        this.autoUpdateTask = null;
                    }
                },
                listeners: {
                    destroy: function() {
                        this.stopAutoUpdate();
                    },
                    hide: function() {
                        this.stopAutoUpdate();
                    }
                },
                items: [{
                    xtype: 'grid',
                    cls: 'x-grid-shadow',
                    plugins: [{
                        ptype: 'gridstore'
                    }, {
                        ptype: 'rowexpander',
                        rowBodyTpl: [
                            '<p><b>Message:</b> {message}</p>'
                        ]
                    }],
                    store: {
                        fields: [
                            {name: 'id', type: 'int'},
                            'dtadded','message'
                        ],
                        proxy: 'object'
                    },
                    viewConfig: {
                        emptyText: 'Log is empty',
                        focusedItemCls: 'x-noselection',
                        selectedItemCls: 'x-noselection',
                        getRowClass: function(record, rowIndex) {
                            return rowIndex === 0 ? 'x-grid-row-new' : '';
                        }
                    },
                    columns: [
                        { header: "Date", width: 165, dataIndex: 'dtadded', sortable: false },
                        { header: "Message", flex: 1, dataIndex: 'message', sortable: false }
                    ]
                },{
                    xtype: 'component',
                    itemId: 'fullLog',
                    margin: '6 0 0'
                }]
            },{
                xtype: 'component',
                itemId: 'success',
                hidden: true,
                cls: 'x-fieldset-subheader',
                style: 'text-align:center',
                margin: '48 0 16',
                html: 'New role has successfully been created'
            },{
                xtype: 'container',
                itemId: 'failed',
                hidden: true,
                margin: '32 0 16',
                items: [{
                    xtype: 'component',
                    cls: 'x-fieldset-subheader',
                    style: 'text-align:center',
                    margin: '0 0 16',
                    html: 'Role creation failed'
                },{
                    xtype: 'component',
                    style: 'text-align:center',
                    itemId: 'failureReason'
                }]
            },{
                xtype: 'container',
                itemId: 'buttons',
                items: [{
                    xtype: 'container',
                    itemId: 'commonButtons',
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        itemId: 'cancel',
                        text: 'Cancel',
                        handler: function() {
                            Scalr.utils.Confirm({
                                form: {
                                    xtype: 'component',
                                    style: 'text-align: center',
                                    margin: '36 0 0',
                                    html: '<span class="x-fieldset-subheader">Are you sure want to cancel role creation?</span>'
                                },
                                success: function() {
                                    Scalr.Request({
                                        processBox: {
                                            type: 'action'
                                        },
                                        url: '/servers/xServerCancelOperation/',
                                        params: { serverId: panel.serverId },
                                        success: function (data) {

                                        }
                                    });
                                    Scalr.event.fireEvent('redirect', '#/roles/view');
                                }
                            });
                        }
                    }]
                },{
                    xtype: 'container',
                    itemId: 'successButtons',
                    hidden: true,
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Edit role',
                        handler: function() {
                            Scalr.event.fireEvent('redirect', '#/roles/'+panel.roleId+'/edit');
                        }
                    },{
                        xtype: 'button',
                        text: 'Build farm',
                        handler: function() {
                            Scalr.event.fireEvent('redirect', '#/farms/build');
                        }
                    }]
                },{
                    xtype: 'container',
                    itemId: 'failedButtons',
                    hidden: true,
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Try again',
                        handler: function() {
                            Scalr.event.fireEvent('redirect', '#/roles/builder');
                        }
                    }]
                }]
            }]
        }],
		dockedItems: [{
			xtype: 'container',
            itemId: 'platforms',
			dock: 'left',
			cls: 'x-docked-tabs',
            width: 112,
			defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'above',
                disableMouseDownPressed: true,
                toggleGroup: 'rolebuilder-tabs',
                toggleHandler: function (comp, state) {
                    if (state) {
                        panel.fireEvent('selectplatform', this.value);
                    }
                }
			},
			items: buttons['platforms']
		}],
		
		suspendState: 0,
		platformsState: {},
		filters: {},
		
		onSelectPlatform: function(platformId) {
			var platform = platforms[platformId],
				images = platform.images,
				compImages = panel.down('#images'),
				added = {},	
				locations = {},	
				compLocationData = [],
				archs = {};
			
            panel.toggleRightColumn(images.length == 0);
			if (result) {
				this.platformsState[result.platform] = Ext.apply({}, result);
			}
			
			if (this.platformsState[platformId]) {
				this.platformsState[platformId].behaviors = result.behaviors;
				this.platformsState[platformId].addons = result.addons;
				this.platformsState[platformId].hvm = 0;
				result = Ext.apply({}, this.platformsState[platformId]);
			} else {
				result = {
					platform: platformId,
					architecture: 'x86_64',
					behaviors: result.behaviors || [], 
					addons: result.addons || ['chef']
				};
			}
			
			this.filters = ['architecture'];
			if (result.platform == 'ec2' && rootDeviceTypeFilterEnabled) {
				this.filters.push('root_device_type');
			}
			
				
			panel.suspendLayouts();
			compImages.removeAll();
			if (images.length) {
				for (var i=0, len=images.length; i<len; i++) {
					var image = images[i],
                        osFamily = Scalr.utils.beautifyOsFamily(image.os_family),
						imageOS = osFamily + ' ' + image.os_version;
					if (!added[imageOS]) {
						compImages.add({
							xtype: 'button',
                            ui: 'simple',
                            cls: 'x-btn-simple-large' + (osFamily.indexOf(' ') !== -1 ? ' x-btn-simple-large-small-text' : ''),
                            iconAlign: 'above',
                            iconCls: 'x-icon-osfamily-large x-icon-osfamily-large-' + image.os_family,
							allowDepress: false,
							toggleGroup: 'scalr-ui-roles-builder-image',
                            text: imageOS,
							osFamily: image.os_family,
							osVersion: image.os_version,
							margin: '0 0 10 10',
							toggleHandler: function () {
								panel.fireEvent('selectname', this.osFamily, this.osVersion, this.pressed);
							}
						});

						added[imageOS] = true;
					}

					if (platform.locations && platform.locations[image.cloud_location]) {
						locations[image.cloud_location] = platform.locations[image.cloud_location];
					}
					archs[image.architecture] = true;

				}
			} else {
				locations = Ext.clone(platform.locations);
			}

			var c = panel.down('#leftcol').query('component[cls~=hideoncustomimage]');
			for (var i=0, len=c.length; i<len; i++) {
				c[i].setVisible(!!images.length);
			}

			var c = panel.down('#leftcol').query('component[cls~=showoncustomimage]');
			for (var i=0, len=c.length; i<len; i++) {
				c[i].setVisible(!images.length);
			}

			//location combobox setup
			var compLocation = panel.down('#cloud_location')
			if (result.platform == 'gce') {
				compLocation.store.loadData([{id: 'all', name: 'GCE roles are automatically available in all regions.'}]);
				compLocation.setValue('all');
				compLocation.setDisabled(true);
			} else {
				Ext.Object.each(locations, function(k, v){
					compLocationData.push({id: k, name: v});
				});
				compLocation.store.loadData(compLocationData);
				compLocation.setDisabled(false);
				compLocation.store.sort('name', 'desc');
				compLocation.setValue(result.cloud_location ? result.cloud_location : (result.platform == 'ec2' && locations['us-east-1'] ? 'us-east-1' : compLocationData[0].id));
			}
			
			panel.down('#rootdevicetypewrap')[result.platform == 'ec2' && rootDeviceTypeFilterEnabled ? 'show' : 'hide']();
			//panel.down('#architecture')[Ext.Object.getSize(archs) > 1 ? 'show' : 'hide']();
			panel.resumeLayouts(true);
			
			
		},
		
		initFilters: function() {
			var me = this;
			for (var i=0, len=this.filters.length; i<len; i++) {
				var comp = panel.down('#'+this.filters[i]),
					isItemFound = false;
				comp.items.each(function(item, index){
					item.enable();
					if (result[me.filters[i]] && result[me.filters[i]] == item.value) {
						comp.setValue(item.value);
						isItemFound = true;
						return false;
					}
				});
				if (!isItemFound) {
					comp.items.each(function(item, index){
						if (!item.disabled) {
							comp.setValue(item.value);
							return false;
						}
					});
				}
			}
		},
		
		getImageId: function() {
			var me = this,
				imageId,
				images = moduleParams.platforms[result.platform].images,
				hvm = panel.down('#hvm').pressed ? 1 : 0;
				
			for (var j=0, len=images.length; j<len; j++) {
				var image = images[j],
					match = result.cloud_location == 'all' || image.cloud_location == result.cloud_location;
				if (match) {
					for (var i=0, len1=me.filters.length; i<len1; i++) {
						match = result[me.filters[i]] ? image[me.filters[i]] == result[me.filters[i]] : true;
						if (match && result.platform == 'ec2' && me.filters[i] == 'root_device_type' && result[me.filters[i]] == 'ebs') {//hvm
							match = hvm == (image.hvm || 0);
						}
						if (!match) break;
					}
				}
				if (match && result.osfamily == image.os_family && result.osversion == image.os_version) {
					imageId = image.image_id;
					break;
				}
			}
			
			return imageId;
	
		},

        findImageById: function(platform, imageId) {
            var images = moduleParams.platforms[platform].images,
                image;
            Ext.Array.each(images, function(item){
                if (item['image_id'] == imageId) {
                    image = item;
                    return false;
                }
            });

            return image;
        },

		getFiltersValues: function() {
			var me = this,
				images = moduleParams.platforms[result.platform].images,
				state = {images: {}, filters: {}},
				hvm = panel.down('#hvm').pressed ? 1 : 0;
			
			for (var j=0, len=images.length; j<len; j++) {
				var image = images[j],
					matchHvm = true,
					match = result.cloud_location == 'all' || image.cloud_location == result.cloud_location;
				if (match) {
					for (var i=0, len1=me.filters.length; i<len1; i++) {
						match = result[me.filters[i]] ? image[me.filters[i]] == result[me.filters[i]] : true;
						
						if (match && result.platform == 'ec2' && me.filters[i] == 'root_device_type' && result[me.filters[i]] == 'ebs') {//hvm
							matchHvm = hvm == (image.hvm || 0);
						}
						
						if (!match || !matchHvm) break;
					}
				}
				if (match ) {
					if (matchHvm) {
						state.images[image.os_family+' '+image.os_version] = true;
					}
					if (result.platform == 'ec2' && result.root_device_type == 'ebs' && image.hvm) {
						state.hvm = 1;
					}
				}
			}
			for (var i=0, len=me.filters.length; i<len; i++) {
				for (var k=0, len1=images.length; k<len1; k++) {
					var image = images[k],
						match = result.cloud_location == 'all' || image.cloud_location == result.cloud_location;
					if (match) {
						for (var j=0, len2=me.filters.length; j<len2; j++) {
							match = result[me.filters[j]] == result[me.filters[i]] || (result[me.filters[j]] ? image[me.filters[j]] == result[me.filters[j]] : true);
							if (!match) break;
						}
					}
					if (match) {
						state.filters[me.filters[i]] = state.filters[me.filters[i]] || {};
						state.filters[me.filters[i]][image[me.filters[i]]] = true;
					}
				}
			}
			return state;
		},

		updateFiltersState: function() {
			var me = this,
				state = this.getFiltersValues(),
				compImages = panel.down('#images');

			if (state.hvm) {
				panel.down('#hvm').enable();
			} else {
				panel.down('#hvm').toggle(false);
				panel.down('#hvm').disable();
			}
	
			compImages.items.each(function() {
				if (state.images[this.osFamily+' '+this.osVersion]) {
					this.enable();
				} else {
					if (this.pressed) {
						this.toggle(false);
					}
					this.disable();
				}
			});
			
			for (var i=0, len=me.filters.length; i<len; i++) {
				if (state.filters[me.filters[i]]) {
					var comp = panel.down('#'+me.filters[i]);
					comp.setReadOnly(false);
					comp.items.each(function(){
						if (!state.filters[me.filters[i]][this.value]) {
							if (this.pressed) {
								this.toggle(false);
								var that = this;
								comp.items.each(function(){
									if (this !== that && !this.disabled) {
										this.toggle(true);
									}
								});
							}
							comp.setReadOnly(true);
						}
					});
				}
			}
		},
		
		refreshBehaviors: function() {
			var params = {platform: result.platform, behavior: result.behaviors},
				image = panel.down('#images').down('[pressed=true]');
			if (image) {
				params.os = {
					family: image.osFamily,
					version: image.osVersion
				}
			}
			for (var i=0, len=behaviors.length; i<len; i++) {
				var item = behaviors[i],
					enabled = true,
					disableInfo;
				if (item.disable) {
					Ext.Object.each(params, function(key, value){
						if (item.disable[key]) {
							if (key == 'os') {
								for (var j=0, len1=item.disable[key].length; j<len1; j++) {
									if (Ext.isString(item.disable[key][j])) {
										if (value.family == item.disable[key][j]) {
											enabled = false;
											break;
										}
									} else {
										if (value.family == item.disable[key][j].family && Ext.Array.contains(item.disable[key][j].version, value.version)) {
											enabled = false;
											break;
										}
									}
								}
							} else {
								enabled = Ext.isArray(value) ? !Ext.Array.intersect(item.disable[key], value).length : !Ext.Array.contains(item.disable[key], value);
							}
						}
						disableInfo = {
							reason: key,
							value: item.disable[key] || null
						};
						return enabled;
					});
				}
                var btn = panel.down('[behavior="'+item.name+'"]');
				if (enabled) {
					btn.enable();
                    btn.setTooltip('');
				}  else {
					btn.toggle(false).disable();
					var message = '';
					if (disableInfo.reason == 'os') {
						message = '<b>' + Scalr.utils.beautifyBehavior(item.name) + '</b> cannot be used together with <b style="white-space:nowrap">' + Scalr.utils.beautifyOsFamily(result.osfamily) + ' ' + result.osversion + '</b>.';
					} else if (disableInfo.reason == 'platform') {
						message = '<b>' + Scalr.utils.beautifyBehavior(item.name) + '</b> is not available on <b style="white-space:nowrap">' + platforms[result.platform].name + '</b>.';
					} else if (disableInfo.reason == 'behavior') {
						message = '<b>' + Scalr.utils.beautifyBehavior(item.name) + '</b> cannot be used together with <b style="white-space:nowrap">' + (Ext.Array.map(Ext.Array.intersect(result.behaviors, disableInfo.value), Scalr.utils.beautifyBehavior)).join(', ') + '</b>.';
						//disableInfo.reason + (disableInfo.reason == 'behavior' ? ': ' + disableInfo.value.join(', ') : '')
					}
					btn.setTooltip(message);
				}
			}
		},
		toggleRightColumn: function(enable) {
            toggleBehaviors(true);
            panel.down('#save').setDisabled(!enable);
            var rightcol = panel.down('#rightcol'), mask;
            rightcol[enable ? 'unmask' : 'mask']();
            mask = rightcol.getEl().child('.x-mask');
            if (mask) {
                mask.set({title: enable ? '' : 'Please select operating system.'});
                mask.setStyle({
                    background: '#ffffff',
                    opacity: .6
                });
            }
            
        },

        loadServer: function(server, image) {
            image = image || {};
            panel.suspendLayouts();
            if (server['platform'] !== 'gce') {
                panel.down('#cloud_location').setValue(image['cloud_location']).disable();
            }
            panel.down('#architecture').setValue(image['architecture']).disable();
            panel.down('#root_device_type').setValue(image['root_device_type']).disable();
            panel.down('#hvm').toggle(image['hvm']==1).disable();
            panel.down('#images').items.each(function(item){
                if (item.osFamily == image['os_family'] && item.osVersion == image['os_version']) {
                    item.toggle(true);
                }
                item.disable();
            });
            panel.down('#imageId').setValue(server['imageId']).disable();

            panel.getDockedComponent('platforms').items.each(function(){
                this.disable();
            });

            panel.down('#rightcol').hide();
            panel.down('#progresscol').bundleTaskId = server.bundleTaskId;
            panel.down('#progresscol').show();
            panel.resumeLayouts(true);
        },

        onBundleTaskSuccess: function(roleId) {
            var progresscol = this.getComponent('progresscol');
            progresscol.down('#successButtons').show();
            progresscol.down('#commonButtons').hide();
            progresscol.down('#log').hide();
            progresscol.down('#success').show();
            this.roleId = roleId;
        },
        onBundleTaskFailed: function(failureReason) {
            var progresscol = this.getComponent('progresscol'),
                failed = progresscol.down('#failed');
            progresscol.down('#failedButtons').show();
            progresscol.down('#commonButtons').hide();
            progresscol.down('#log').hide();
            failed.down('#failureReason').update('Error: '+failureReason);
            failed.show();
        },

		listeners: {
			afterrender: function () {
                var items = panel.down('#platforms').items,
                    defaultPlatform = loadParams['platform'] || 'ec2',
                    defaultItem, server = moduleParams['server'], image;

                if (server) {
                    panel.serverId = server['serverId'];
                    image = this.findImageById(server['platform'], server['imageId']);
                    defaultPlatform = server['platform'];
                }
                items.each(function(){
                    if (this.value == defaultPlatform) {
                        defaultItem = this;
                        return false;
                    }
                });
                defaultItem = defaultItem || items.first();
                defaultItem.toggle(true);

                if (server) {
                    this.loadServer(server, image);
                }
			},
			selectplatform: function(value) {
				this.suspendState++;
				panel.onSelectPlatform(value);
				panel.initFilters();
				this.suspendState--;
				panel.updateFiltersState();
				panel.down('#availzone')[value=='ec2'?'show':'hide']();
				panel.down('#region')[value=='gce'?'show':'hide']();
				panel.down('#overrideImageId')[platforms[value].images.length && Scalr.flags['betaMode']?'show':'hide']();
			},
			selectlocation: function(value) {
				result.cloud_location = value;
				if (!this.suspendState) {
					panel.updateFiltersState();
				}
			},
			selectarchitecture: function(value) {
				result.architecture = value;
				if (!this.suspendState) {
					panel.updateFiltersState();
				}
			},
			selectrootdevicetype: function(value) {
				result.root_device_type = value;
				if (value=='ebs') {
					panel.down('#hvm').enable();
				} else {
					panel.down('#hvm').toggle(false);
					panel.down('#hvm').disable();
				}
				if (!this.suspendState) {
					panel.updateFiltersState();
				}
			},
			selecthvm: function(value) {
				result.hvm = value ? 1 : 0;
				if (!this.suspendState) {
					panel.updateFiltersState();
				}
			},
			selectname: function(osFamily, osVersion, select) {
				result.osfamily = select ? osFamily : null;
				result.osversion = select ? osVersion : null;
                panel.toggleRightColumn(select);
				if (select) {
					panel.refreshBehaviors();
				}
			}
			
		}
	});
	return panel;
});

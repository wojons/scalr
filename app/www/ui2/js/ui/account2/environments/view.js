Scalr.regPage('Scalr.ui.account2.environments.view', function (loadParams, moduleParams) {
	var isAccountOwner = Scalr.user['type'] === 'AccountOwner',
        isAccountSuperAdmin = Scalr.user['type'] === 'AccountSuperAdmin',
		storeTeams = Scalr.data.get('account.teams'),
		storeEnvironments = Scalr.data.get('account.environments'),
        readOnlyAccess = !Scalr.utils.canManageAcl() && !Scalr.isAllowed('ENVADMINISTRATION_ENV_CLOUDS');

	var getTeamNames = function(teams, links) {
		var list = [];
		if (teams) {
            if (Scalr.flags['authMode'] === 'ldap') {
                list = teams;
                var len = list.length, maxLen = 2;
                if (len > maxLen) {
                    list = list.slice(0, maxLen);
                    list.push('and ' + (len - list.length) + ' more');
                }
            } else {
                for (var i=0, len=teams.length; i<len; i++) {
                    var record = storeTeams.getById(teams[i]),
                        name;
                    if (record) {
                        name = record.get('name').replace(/\s/g, '&nbsp;');
                        list.push(links?'<a href="#/account/teams?teamId='+record.get('id')+'">'+name+'</a>':name);
                    }
                }
            }
		}
		return list.join(', ');
	};
	var store = Ext.create('Scalr.ui.ChildStore', {
		parentStore: storeEnvironments,
		filterOnLoad: true,
		sortOnLoad: true,
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}]
	});

	var envTeamsStore = Ext.create('Scalr.ui.ChildStore', {
		parentStore: storeTeams,
		sortOnLoad: true,
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}]
	});

    var firstReconfigure = true;
	var reconfigurePage = function(params) {
        var params = params || {},
            envId = params.envId;
        if (firstReconfigure && !envId) {
            envId = Scalr.user.envId;
        }
		if (envId) {
			dataview.deselect(form.getForm().getRecord());
			if (envId === 'new') {
				if (isAccountOwner || isAccountSuperAdmin) {
					panel.down('#add').handler();
				}
			} else {
				panel.down('#envLiveSearch').reset();
				var record =  store.getById(envId);
				if (record) {
					dataview.select(record);
				}
			}
		}
        firstReconfigure = false;
	};

    var dataview = Ext.create('Ext.view.View', {
        deferInitialRefresh: false,
        store: store,
		listeners: {
            refresh: function(view){
                var record = view.getSelectionModel().getLastSelected();
                if (record) {
                    form.loadRecord(view.store.getById(record.get('id')));
                }
            }
		},
        cls: 'x-dataview',
        itemCls: 'x-dataview-tab',
        selectedItemCls : 'x-dataview-tab-selected',
        overItemCls : 'x-dataview-tab-over',
        itemSelector: '.x-dataview-tab',
        tpl  : new Ext.XTemplate(
            '<tpl for=".">',
                '<div class="x-dataview-tab">',
                    '<table>',
                        '<tr>',
                            '<td colspan="3">',
                                '<div class="x-fieldset-subheader" style="margin-bottom:10px">{name} </div>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td width="50">',
                                '<span class="x-dataview-tab-param-title">ID: </span>',
                            '</td>',
                            '<td width="120">',
                                '<span class="x-dataview-tab-param-value">{id}</span>',
                            '</td>',
                            '<td>',
                                '<div class="x-dataview-tab-param-title x-dataview-tab-status-{[values.status == "Active" ? "active" : "inactive"]}">{[values.status == \'Active\' ? \'Managed\' : \'Suspended\']}</div>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td>',
                                '<span class="x-dataview-tab-param-title">Teams: </span>',
                            '</td>',
                            '<td>',
                                '<tpl if="values.teams && values.teams.length">',
                                    '<div class="x-dataview-tab-param-value">{[this.getTeamNames(values.teams)]}</div>',
                                '</tpl>',
                            '</td>',
                            '<td>',
                                '<span class="x-dataview-tab-param-title" style="float:left">Enabled clouds: </span>',
                                '<span style="float:left;width:100px;">{[this.getPlatformNames(values.platforms)]}</span>',
                            '</td>',
                        '</tr>',
                    '</table>',
                '</div>',
            '</tpl>',
			{
				getPlatformNames: function(platforms){
					var list = [];
					if (platforms && platforms.length) {
						for (var i=0, len=platforms.length; i<len; i++) {
                            list.push(
                                '<div class="x-icon-platform-small x-icon-platform-small-' + platforms[i] + ' "title="'+(Scalr.utils.getPlatformName(platforms[i]))+'"></div>'
                            );
						}
					}
                    return list.length > 0 ? '<div style="margin:-2px 0 0">' + list.join('') + '<div class="x-clear"></div></div>' : '<span class=\"x-dataview-tab-param-value\">&nbsp;none</span>';
				},
				getTeamNames: function(teams){
					return getTeamNames(teams);
				}
			}

        ),
		plugins: {
			ptype: 'dynemptytext',
			emptyText: '<div class="title">No environments were found<br/> to match your search.</div>Try modifying your search criteria'+ (!isAccountOwner && !isAccountSuperAdmin ? '.' : '<br/>or <a class="add-link" href="#">creating a new environment</a>.'),
			onAddItemClick: function() {
				panel.down('#add').handler();
			}
		},
		loadingText: 'Loading environments ...',
		deferEmptyText: true

    });

	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
		fieldDefaults: {
			anchor: '100%'
		},
		layout: 'auto',
        overflowX: 'hidden',
        overflowY: 'auto',
		listeners: {
			hide: function() {
				if (isAccountOwner || isAccountSuperAdmin) {
					dataview.up('panel').down('#add').setDisabled(false);
				}
			},
			afterrender: function() {
				var me = this;
				dataview.on('selectionchange', function(dataview, selection){
					if (selection.length) {
						me.loadRecord(selection[0]);
					} else {
						me.setVisible(false);
					}
				});
			},
			beforeloadrecord: function(record) {
				var frm = this.getForm(),
					isNewRecord = !record.get('id');

				frm.reset(true);
                this.down('#formtitle').setTitle(isNewRecord ? 'New environment' : '');
				var c = this.query('component[cls~=hideoncreate], #delete, #clone');
				for (var i=0, len=c.length; i<len; i++) {
					c[i].setVisible(!isNewRecord);
				}
				if (isAccountOwner || isAccountSuperAdmin) {
					dataview.up('panel').down('#add').setDisabled(isNewRecord);
                    this.down('#delete').setDisabled(storeEnvironments.getCount()>1?false:true);
                }
				if (this.down('#envTeamNames')) {
					this.down('#envTeamNames').setValue(getTeamNames(record.get('teams'), Scalr.utils.canManageAcl()));
				}

                this.down('#teamstitle').setTitle(
                    Scalr.flags['authMode'] == 'ldap' ? 'Accessible by LDAP groups (comma separated)' : 'Team access' + (!isNewRecord && Scalr.utils.canManageAcl() ? ' (<a href="#/account/environments/accessmap?envId=' + record.get('id') + '">view summary</a>)' : '')
                , false);

                var rackspaceBtn = this.down('button[platform="rackspace"]');
                if (rackspaceBtn) {
                    rackspaceBtn.setVisible(Ext.Array.contains(record.get('platforms'), 'rackspace'));
                }

                if (moduleParams['ccs']) {
                    var ccs = moduleParams['ccs'];
                    if (!isNewRecord && moduleParams['unassignedCcs'][record.get('id')]) {
                        ccs = Ext.Array.merge(ccs, [moduleParams['unassignedCcs'][record.get('id')]])
                    }
                    frm.findField('ccId').store.load({data: ccs});
                }
			},
			loadrecord: function(record) {
				envTeamsStore.loadData(storeTeams.getRange());

                var platforms = record.get('platforms') || [],
                    platformCt = form.down('#platforms');
                Ext.Array.each(platformCt.query('[xtype="button"]'), function(btn){
                    var platformEnabled = Ext.Array.contains(platforms, btn.platform);
                    this[(platformEnabled ? 'removeCls' : 'addCls')]('scalr-ui-environment-cloud-disabled');
                });
                platformCt.setDisabled(platforms.length === 0);

				if (!this.isVisible()) {
					this.setVisible(true);
				}
			}
		},
		items: [{
			xtype: 'fieldset',
            itemId: 'formtitle',
            cls: 'x-fieldset-separator-none',
            title: '&nbsp;',
			items: [{
                xtype: 'container',
                defaults: {
                    flex: 1,
                    maxWidth: 370
                },
                layout: 'hbox',
                items: [{
                    xtype: 'textfield',
                    readOnly: !isAccountOwner && !isAccountSuperAdmin,
                    name: 'name',
                    fieldLabel: 'Environment',
                    labelWidth: 80,
                    allowBlank: false
                }, {
                    xtype: 'buttongroupfield',
                    fieldLabel: 'Scalr management',
                    readOnly: !Scalr.utils.canManageAcl(),
                    margin: '0 0 0 40',
                    labelWidth: 120,
                    name: 'status',
                    value: 'Active',
                    layout: 'hbox',
                    defaults: {
                        maxWidth: 100,
                        flex: 1
                    },
                    items: [{
                        text: 'Active',
                        value: 'Active'
                    }, {
                        text: 'Suspended',
                        value: 'Inactive'
                    }]
                }]
            },{
                xtype: 'combo',
                store: {
                    fields: [ 'ccId', 'name' ],
                    proxy: 'object'
                },
                anchor: '50%',
                maxWidth: 370,
                margin: '0 20 0 0',
                editable: false,
                autoSetSingleValue: true,
                hidden: !Scalr.flags['analyticsEnabled'],
                allowBlank: !Scalr.flags['analyticsEnabled'],
                valueField: 'ccId',
                displayField: 'name',
                fieldLabel: 'Cost center',
                labelWidth: 80,
                name: 'ccId',
                readOnly: !isAccountOwner && !isAccountSuperAdmin,
                listeners: {
                    beforeselect: function(field, newRecord) {
                        var text, oldName,
                            isNewEnvironment = !field.up('form').getForm().getRecord().store,
                            oldRecord = field.findRecordByValue(field.getValue());
                        if (!isNewEnvironment && oldRecord && field.getPicker().isVisible()) {
                            oldName = oldRecord ? oldRecord.get('name') : field.getValue()
                            text = 'Switching to <b>' + newRecord.get('name') + '</b> will prevent new Farms in <b>' + field.up('form').down('[name="name"]').getValue() +
                                   '</b> from being associated with any Projects in <b>' + oldName + '</b>. This will not automatically affect any existing farm.<br/>' +
                                   'However, users will need to manually change the Project associated with existing Farms next time any Farm is edited.<br/>' +
                                   'Please ensure that at least 1 Project exists in <b>' + newRecord.get('name') + '</b>, otherwise, users will not be able to save their Farms.'
                            Scalr.message.WarningTip(text, field.inputEl);
                        }
                    }
                }

            }]
		}, {
			xtype: 'container',
			itemId: 'platforms',
			cls: 'x-fieldset-separator-top',
            maxWidth: 1100,
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'fieldset',
                title: 'Public&nbsp;clouds',
                itemId: 'publicPlatforms',
                cls: 'x-fieldset-separator-none x-fieldset-clouds',
            },{
                xtype: 'fieldset',
                title: 'Private&nbsp;clouds',
                itemId: 'privatePlatforms',
                cls: 'x-fieldset-clouds',
                flex: 1,
                listeners: {
                    boxready: {
                        fn: function(){
                            if (this.isVisible() && this.prev().isVisible()) {
                                this.on({
                                    resize: function(){
                                        if (!this.resizeInProgress) {
                                            var leftcol = this.prev(),
                                                container = leftcol.ownerCt,
                                                width = container.getWidth(),
                                                extraWidth = 54,
                                                itemWidth = 110,
                                                colCount = Math.floor(((width > container.maxWidth ? container.maxWidth : width) - extraWidth*2)/110),
                                                rowsCount = Math.ceil((leftcol.items.length + this.items.length)/colCount);
                                            colCount = Math.ceil(leftcol.items.length/rowsCount);
                                            if (colCount > leftcol.items.length) {
                                                colCount = leftcol.items.length;
                                            }
                                            this.resizeInProgress = true;
                                            leftcol.setWidth(colCount*itemWidth + extraWidth);
                                            this.resizeInProgress = false;
                                        }
                                    }
                                });
                            }
                        },
                        sigle: true
                    }
                }
            }],
            listeners: {
                disable: function() {
                    var el = this.getMaskTarget();
                    if (this.credEl) {
                        this.credEl.remove();
                    }
                    if (el) {
                        var envId = this.up('form').getForm().getRecord().get('id');
                        this.credEl = Ext.DomHelper.append(el.dom,
                            '<div class="scalr-add-credentials-wrap">' +
                                (envId ?
                                    '<div class="scalr-add-credentials-info">' +
                                        'Start building cloud infrastructure in this environment, by adding all of your cloud credentials.' +
                                    '</div>' +
                                    '<a href="#/account/environments/' + envId + '/clouds" class="scalr-add-credentials-button">Add cloud credentials</a>'
                                :   '<div class="scalr-add-credentials-info" style="width:450px">' +
                                        'Please save your environment to start adding cloud credentials.' +
                                    '</div>'
                                ) +
                            '</div>',
                        true);
                    }
                },
                enable: function() {
                    if (this.credEl) {
                        this.credEl.remove();
                    }
                }
            }
		},{
			xtype: 'fieldset',
            cls: 'x-fieldset-separator-top',
            itemId: 'teamstitle',
			items: [
                Scalr.utils.canManageAcl() ? Scalr.flags['authMode'] == 'ldap' ? {
                xtype: 'accountauthldapfield',
                name: 'teams'
            } : {
				xtype: 'gridfield',
				name: 'teams',
				flex: 1,
				cls: 'x-grid-shadow x-grid-no-selection',
				maxWidth: 1100,
				listeners: {
					viewready: function(){
						this.reconfigure(envTeamsStore);
					}
				},
				viewConfig: {
					focusedItemCls: '',
					plugins: {
						ptype: 'dynemptytext',
						emptyText: 'No teams were found.'+ (!isAccountOwner && !isAccountSuperAdmin ? '' : ' Click <a href="#/account/teams?teamId=new">here</a> to create new team.')
					}
				},
				columns: [
					{text: 'Team name', flex: 1, dataIndex: 'name', sortable: true, xtype: 'templatecolumn', tpl: '<a href="#/account/teams?teamId={id}">{name}</a>'},
					{text: 'Users', width: 120, dataIndex: 'users', sortable: false, xtype: 'templatecolumn', tpl: '<tpl if="users.length"><a href="#/account/teams?teamId={id}">{users.length}</a></tpl>'},
					{
						text: 'Other environments',
						flex: 1,
						sortable: false,
						xtype: 'templatecolumn',
						tpl: new Ext.XTemplate(
							'{[this.getOtherEnvList(values.id)]}',
						{
							getOtherEnvList: function(teamId){
								var envs = [],
									envId = form.getRecord().get('id');
								storeEnvironments.each(function(){
									var envTeams = this.get('teams');
									if (envTeams && envId != this.get('id')) {
										for (var i=0, len=envTeams.length; i<len; i++) {
											if (teamId == envTeams[i]) {
												envs.push(this.get('name'));
												break;
											}

										}
									}
								});
								return envs.join(', ');
							}
						})
					}
				]
            } : {
				xtype: 'displayfield',
				itemId: 'envTeamNames'
			}]
		}],
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
            maxWidth: 1100,
            hidden: !Scalr.utils.canManageAcl(),
			items: [{
				itemId: 'save',
				xtype: 'button',
				text: 'Save',
				handler: function() {
					var frm = form.getForm();
					if (frm.isValid()) {
						var record = frm.getRecord();
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/account/environments/xSave',
							form: frm,
							params: !record.get('id')?{}:{envId: record.get('id')},
							success: function (data) {
								if (!record.get('id')) {
									record = store.add(data.env)[0];
									dataview.getSelectionModel().select(record);
									Scalr.event.fireEvent('update', '/account/environments/create', data.env);
								} else {
									record.set(data.env);
									form.loadRecord(record);
									Scalr.event.fireEvent('update', '/account/environments/rename', data.env);
								}

                                if (Scalr.flags['authMode'] == 'ldap')
                                    Scalr.data.reload(['account.environments', 'account.teams']);
							}
						});
					}
				}
			}, {
				itemId: 'cancel',
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					dataview.deselect(form.getForm().getRecord());
					form.setVisible(false);
				}
			},{
				itemId: 'clone',
				xtype: 'button',
				text: 'Clone',
				handler: function() {
                    var record = form.getForm().getRecord();
                    Scalr.Confirm({
                        msg: 'Clone "' + record.get('name') + '" environment?',
                        ok: 'Confirm & clone',
                        formWidth: 680,
                        form: {
                            xtype: 'fieldset',
                            title: 'Clone "' + record.get('name') + '" environment',
                            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                            defaults: {
                                anchor: '100%'
                            },
                            items: [{
                                xtype: 'textfield',
                                margin: '12 0 24',
                                fieldLabel: 'New environment name',
                                value: '',
                                name: 'name',
                                labelWidth: 150,
                                allowBlank: false,
                                anchor: '100%'
                            },{
                                xtype: 'displayfield',
                                cls: 'x-form-field-info',
                                value: ' - What will be cloned: Cloud settings, Governance, Environment level global variables, ACLs<br/>' +
                                       ' - What will NOT be cloned: Farms, Roles, Scripts and all other user objects'
                            }]
                        },
                        closeOnSuccess: true,
                        success: function (formValues, form) {
                            var confirmBox = this;
                            if (form.isValid()) {
                                Scalr.Request({
                                    processBox: {
                                        type: 'action',
                                        msg: 'Cloning environment ...'
                                    },
                                    url: '/account/environments/xClone',
                                    params: {
                                        envId: record.get('id'),
                                        name: formValues['name']
                                    },
                                    success: function (data) {
                                        record = store.add(data.env)[0];
                                        dataview.getSelectionModel().select(record);
                                        Scalr.event.fireEvent('update', '/account/environments/create', data.env);

                                        if (Scalr.flags['authMode'] == 'ldap')
                                            Scalr.data.reload(['account.environments', 'account.teams']);

                                        confirmBox.close();
                                    }
                                });
                            }
                        }

                    });
				}
			}, {
				itemId: 'delete',
				xtype: 'button',
				cls: 'x-btn-default-small-red',
				text: 'Delete',
				disabled: !isAccountOwner && !isAccountSuperAdmin,
                tooltip: isAccountOwner || isAccountSuperAdmin ? '' : 'Only <b>Account owner</b> can delete environments',
				handler: function() {
					var record = form.getForm().getRecord();
					Scalr.Request({
						confirmBox: {
							msg: 'Delete environment ' + record.get('name') + ' ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting...',
							type: 'delete'
						},
						scope: this,
						url: '/account/environments/xRemove',
						params: {envId: record.get('id')},
						success: function (data) {
							Scalr.event.fireEvent('update', '/account/environments/delete', {id: record.get('id')});
							record.store.remove(record);
							if (data['flagReload'])
								Scalr.application.updateContext();
						}
					});
				}
            }]
		}]
	});

    var publicPlatformsCt = form.down('#publicPlatforms'),
        privatePlatformsCt = form.down('#privatePlatforms');
	Ext.Object.each(Scalr.platforms, function(key, value) {
		(value.public ? publicPlatformsCt : privatePlatformsCt).add({
            xtype: 'button',
            ui: 'simple',
			cls: 'x-btn-simple-large',
            margin: '10 0 0 10',
            iconAlign: 'above',
            iconCls: 'x-icon-platform-large x-icon-platform-large-' + key,
            text: Scalr.utils.getPlatformName(key, true),
			platform: key,
            disableMouseDownPressed: readOnlyAccess,
			handler: function () {
                if (readOnlyAccess) {
                    Scalr.message.InfoTip('Insufficient permissions to configure cloud.', this.el);
                } else {
                    Scalr.event.fireEvent('redirect', '#/account/environments/' + form.getForm().getRecord().get('id') + '/clouds?platform=' + this.platform, true);
                }
			}
		});
	});
    if (!publicPlatformsCt.items.length) {
        publicPlatformsCt.hide();
        privatePlatformsCt.addCls('x-fieldset-separator-none');
    } else {
        privatePlatformsCt.addCls('x-fieldset-separator-left');
    }
    if (!privatePlatformsCt.items.length) {
        privatePlatformsCt.hide();
        publicPlatformsCt.flex = 1;
    }


	Scalr.event.on('update', function (type, envId, envAutoEnabled, platform, enabled) {
		if (type == '/account/environments/edit') {
			if (form.isVisible()) {
				if (envId == form.getForm().getRecord().get('id')) {
					var b = form.down('#platforms').down('[platform="' + platform + '"]');
					if (b) {
						b[(enabled ? 'removeCls' : 'addCls')]('scalr-ui-environment-cloud-disabled');
					}
				}
			}
			var record = store.getById(envId);
			if (record) {
				var platforms = record.get('platforms') || [];
				if (!enabled){
					Ext.Array.remove(platforms, platform);
				} else if (!Ext.Array.contains(platforms, platform)) {
					platforms.push(platform);
				}
				record.set('platforms', platforms);
                if (envAutoEnabled) {
                    record.set('status', 'Active');
                }
				store.fireEvent('refresh');
			}
		}
	}, form);


	var panel = Ext.create('Ext.panel.Panel', {
		cls: 'scalr-ui-panel-account-env',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			title: 'Environments',
			reload: false,
			maximize: 'all',
			leftMenu: {
				menuId: 'account',
				itemId: 'environments'
			}
		},
        listeners: {
            applyparams: reconfigurePage
        },
		items: [
			Ext.create('Ext.panel.Panel', {
				cls: 'x-panel-column-left',
				width: 440,
				items: dataview,
				autoScroll: true,
				dockedItems: [{
					xtype: 'toolbar',
					dock: 'top',
					defaults: {
						margin: '0 0 0 10'
					},
					items: [{
						xtype: 'filterfield',
						itemId: 'envLiveSearch',
						margin: 0,
                        width: 200,
						filterFields: ['name'],
						store: store
					},{
						xtype: 'tbfill'
					},{
						itemId: 'add',
                        text: 'Add environment',
                        cls: 'x-btn-green-bg',
						tooltip: isAccountOwner || isAccountSuperAdmin ? '' : 'Only <b>Account owner</b> can create environments',
						disabled: !isAccountOwner && !isAccountSuperAdmin,
                        hidden: readOnlyAccess,
						handler: function(){
							dataview.deselect(form.getForm().getRecord());
							form.loadRecord(store.createModel({status: 'Active'}));
						}
					},{
						itemId: 'refresh',
                        ui: 'paging',
						iconCls: 'x-tbar-loading',
						tooltip: 'Refresh',
						handler: function() {
							Scalr.data.reload('account.*');
						}
					}]
				}]
			})
		,{
			xtype: 'container',
            flex: 1,
            layout: 'fit',
			minWidth: 600,
			items: form
		}]
	});
	return panel;
});

Ext.define('Scalr.ui.AccountEnvironmentAuthLdap', {
    extend: 'Ext.form.field.TextArea',
    alias: 'widget.accountauthldapfield',

    setValue: function(value) {
        return this.callParent([ Ext.isArray(value) ? value.join(', ') : value ]);
    },

    getValue: function() {
        var value = this.callParent(arguments);
        return value.split(',');
    },

    getSubmitData: function() {
        var me = this,
            data = null;
        if (!me.disabled && me.submitValue) {
            data = {};
            data[me.getName()] = Ext.encode(me.getValue());
        }
        return data;
    }
});

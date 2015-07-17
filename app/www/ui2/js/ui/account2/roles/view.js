Scalr.regPage('Scalr.ui.account2.roles.view', function (loadParams, moduleParams) {
    var firstReconfigure = true;
	var reconfigurePage = function(params) {
        var params = params || {},
            roleId = params.roleId;
        if (firstReconfigure && !roleId) {
            roleId = 'first';
        }
		if (roleId) {
			dataview.deselectAndClearLastSelected();
			if (roleId === 'new') {
				panel.down('#add').toggle(true);
			} else {
				panel.down('#rolesLiveSearch').reset();
				var record =  store.getById(roleId) || (roleId === 'first' ? store.first() : null);
				if (record) {
					dataview.select(record);
				}
			}
		}
        firstReconfigure = false;
	};

    var storeRoles = Scalr.data.get('account.roles'),
        storeBaseRoles = Scalr.data.get('base.roles');

	var store = Ext.create('Ext.data.ChainedStore', {
		source: storeRoles,
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}]
	});

    var storeRoleResources = Ext.create('Ext.data.Store', {
        fields: ['id', 'name', 'granted', 'group', 'groupOrder', 'permissions', 'locked', 'lockedPermissions'],
        groupField: 'groupOrder'
    });

    // for RESOURCE_FARMS, RESOURCE_TEAM_FARMS, RESOURCE_OWN_FARMS
    var handler = function(store, record) {
        if (record.get('id') == 256) { // RESOURCE_FARMS
            var r;
            r = store.findRecord('id', 264);
            if (r) {
                if (record.get('granted') == 1) {
                    r.set('granted', 1);
                    r.set('locked', 1);
                    r.set('lockedPermissions', Ext.clone(record.get('permissions')));
                } else {
                    r.set('locked', null);
                    r.set('lockedPermissions', null);
                }
            }

            r = store.findRecord('id', 265);
            if (r) {
                if (record.get('granted') == 1) {
                    r.set('granted', 1);
                    r.set('locked', 1);
                    r.set('lockedPermissions', Ext.clone(record.get('permissions')));
                } else {
                    r.set('locked', null);
                    r.set('lockedPermissions', null);
                }
            }
        }
    };

    storeRoleResources.on('refresh', function() {
        var me = this;
        me.suspendEvents();
        me.each(function(record) {
           handler(me, record);
        });
        me.resumeEvents();
    });

    storeRoleResources.on('update', handler);

    var dataview = Ext.create('Ext.view.View', {
		listeners: {
            refresh: function(view){
                var selModel = view.getSelectionModel(),
                    record = selModel.getLastSelected();
                if (record) {
                    dataview.deselectAndClearLastSelected();
                    if (dataview.getNode(record)) {
                        selModel.select(view.store.getById(record.get('id')));
                    }
                }
            }
		},
        deferInitialRefresh: false,
        store: store,
        cls: 'x-dataview',
        itemCls: 'x-dataview-tab',
        selectedItemCls : 'x-dataview-tab-selected',
        overItemCls : 'x-dataview-tab-over',
        itemSelector: '.x-dataview-tab',
        tpl  : new Ext.XTemplate(
            '<tpl for=".">',
                '<div class="x-dataview-tab">',
                    '<div class="x-item-color-corner x-color-{[values.color?values.color:\'333333\']}"></div>',
                    '<table>',
                        '<tr>',
                            '<td>',
                                '<div class="x-fieldset-subheader" style="margin:0 0 8px">{name}</div>',
                                '<table>',
                                '<tr><td class="x-form-item-label-default">New permission default</td><td class="x-dataview-tab-param-value">{[this.getBaseRoleName(values.baseRoleId)]}</td></tr> ',
                                '</table>',
                            '</td>',
                        '</tr>',
                    '</table>',
                '</div>',
            '</tpl>',
			{
				getBaseRoleName: function(baseRoleId){
					var record = storeBaseRoles.getById(baseRoleId);
					return record ? record.get('name') : '';
				}
			}
        ),
		plugins: {
			ptype: 'dynemptytext',
			emptyText: '<div class="x-semibold title">No ACL were found to match your search.</div>Try modifying your search criteria <br/>or creating a new ACL',
			emptyTextNoItems: '<div class="x-semibold title">You have no ACLs under your account.</div>'+
								'Access Control Lists let you define exactly what your co-workers have or don\'t have access to.'
		},
		loadingText: 'Loading ACLs ...',
		deferEmptyText: false
    });

	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        padding: 0,
		listeners: {
			hide: function() {
                panel.down('#add').toggle(false, true);
			},
			afterrender: function() {
				var me = this;
				dataview.on('selectionchange', function(dataview, selection){
					if (selection.length) {
						me.loadRecord(selection[0]);
					} else {
                        me.resetRecord();
					}
				});
			},
			beforeloadrecord: function(record) {
				var frm = this.getForm(),
                    isNewRecord = !record.store;
                this.down('#formtitle').setTitle(isNewRecord ? 'New ACL' : 'Edit ACL');
				this.down('#delete').setVisible(!isNewRecord);
				dataview.up('panel').down('#add').toggle(isNewRecord, true);
                this.down('#roleusage').update(isNewRecord ? '' : '<a href="#/account/roles/usage?accountRoleId=' + record.get('id') + '">Role usage summary</a>');

                var resources = record.get('resources');
                if (!resources) {
                    var baseRole = storeBaseRoles.getById(record.get('baseRoleId'));
                    if (baseRole) {
                        resources = Ext.clone(baseRole.get('resources'));
                    }
                }
                this.down('#resources').loadResources(resources);

                frm.findField('baseRoleId').setReadOnly(!isNewRecord);
            }
		},
        items: [{
            xtype: 'fieldset',
            layout: 'hbox',
            itemId: 'formtitle',
            title: '&nbsp;',
            items: [{
                xtype: 'hiddenfield',
                name: 'id'
            },{
                xtype: 'textfield',
                name: 'name',
                fieldLabel: 'ACL name',
                allowBlank: false,
                labelWidth: 80
            },{
                xtype: 'combo',
                fieldLabel: 'New permission default',
                store: storeBaseRoles,
                allowBlank: false,
                editable: false,
                displayField: 'name',
                valueField: 'id',
                name: 'baseRoleId',
                queryMode: 'local',
                labelWidth: 180,
                margin: '0 0 0 30',
                hideInputOnReadOnly: true,
                listeners: {
                    change: function(comp, value, oldValue){
                        if (form.isRecordLoading) return;
                        var baseRole = storeBaseRoles.getById(value);
                        if (baseRole) {
                            form.down('#resources').loadResources(Ext.clone(baseRole.get('resources')));
                        }
                    }
                }
            },{
                xtype: 'colorfield',
                name: 'color',
                fieldLabel: 'Color',
                allowBlank: false,
                labelWidth: 55,
                margin: '0 0 0 30'
            }]
        },{
            xtype: 'fieldset',
            title: 'Permissions',
            cls: 'x-fieldset-separator-none',
            flex: 1,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'grid',
                itemId: 'resources',
                cls: 'x-grid-with-formfields',
                trackMouseOver: false,
                disableSelection: true,
                maxWidth: 1100,
                flex: 1,
                hideHeaders: true,
                loadResources: function(resources){
                    if (!resources) return;
                    this.getStore().loadData(resources);
                },
                getResources: function(){
                    var data = [];
                    this.store.getUnfiltered().each(function(resource){
                        var r = resource.getData(),
                            permissions = null;
                        if (r.permissions) {
                            permissions = {};
                            Ext.Object.each(r.permissions, function(key, value){
                                permissions[key] = r.granted == 1 ? value : 0;
                            });
                        }
                        data.push({
                            id: r.id,
                            granted: r.granted,
                            permissions: permissions
                        });
                    });
                    return data;
                },
                store: storeRoleResources,
                features: [{
                    id:'grouping',
                    ftype:'grouping',
                    groupHeaderTpl: Ext.create('Ext.XTemplate',
                        '{children:this.getGroupName}',
                        {
                            getGroupName: function(children) {
                                if (children.length > 0) {
                                    var name = children[0].get('group');
                                    return name === 'Account management' || name === 'Environment management' ? '<span class="x-permission-warn">' + name + '</span>&nbsp;&nbsp;<img title="Be careful assigning administrative permissions" src="/ui2/images/icons/warning_icon_16x16.png" style="vertical-align:top">' : name;
                                }
                            }
                        }
                    )
                }],
                viewConfig: {
                    preserveScrollOnRefresh: true,
                    markDirty: true,
                    plugins: {
                        ptype: 'dynemptytext',
                        emptyText: '<div class="x-semibold title">No permissions were found to match your search.</div>Try modifying your search criteria.'
                    },
                    listeners: {
                        viewready: function(){
                            var me = this;
                            Ext.create('Ext.tip.ToolTip', {
                                target: me.el,
                                delegate: 'span.x-multicheckbox-item',
                                trackMouse: true,
                                renderTo: Ext.getBody(),
                                hideDelay: 0,
                                listeners: {
                                    beforeshow: function (tip) {
                                        var trigger = Ext.fly(tip.triggerElement),
                                            record = me.getRecord(trigger.up('tr')),
                                            resource,
                                            permission;
                                        if (record) {
                                            resource = moduleParams['definitions'][record.get('id')];
                                            permission = trigger.getAttribute('data-value');
                                            tip.update(resource && resource[3] && resource[3][permission] ? resource[3][permission] : '');
                                        }
                                    }
                                }
                            });
                        }
                    }
                },
                columns: [{
                    xtype: 'buttongroupcolumn',
                    dataIndex: 'granted',
                    width: 120,
                    buttons: [{
                        text: 'On',
                        value: '1',
                        width: 50
                    },{
                        text: 'Off',
                        value: '0',
                        width: 50
                    }]
                },{
                    xtype: 'multicheckboxcolumn',
                    flex: 1,
                    minWidth: 390,
                    dataIndex: 'permissions',
                    isDisabled: function(record) {
                        return record.get('granted') != 1;
                    },
                    cellRenderer: function(value, record) {
                        var id = record.get('id'),
                            resource = moduleParams['definitions'][id],
                            prefix;
                        prefix = '<div style="float:left;min-width:200px"><div class="x-semibold">' + (resource ? resource[0] : id) + '</div><div style="font-size:85%;color:#999;line-height:1.6em">' + (resource ? resource[1] : '') + '</div></div>';
                        return prefix + value;
                    },
                    listeners: {
                        beforechange: function(column, newValue, name, record, cell) {
                            var id = record.get('id');
                            if (id == 260) { //RESOURCE_FARMS_ROLES
                                if (newValue['create'] == 1) {
                                    if (name === 'create') {
                                        Ext.apply(newValue, {bundletasks: 1, manage: 1});
                                    } else if ((name === 'bundletasks' || name === 'manage') && newValue[name] == 0){
                                        newValue['create'] = 0;
                                    }
                                }
                            }
                            if (id == 256 || id == 264 || id == 265) { //RESOURCE_FARMS (RESOURCE_OWN/TEAM_FARMS)
                                if (newValue[name] == 1 && id == 256) {
                                    // enable permission in child
                                    record.store.getById(264).get('permissions')[name] = 1;
                                    record.store.getById(264).commit();
                                    record.store.getById(265).get('permissions')[name] = 1;
                                    record.store.getById(265).commit();
                                }
                            }
                        }
                    }
                },{
                    xtype: 'templatecolumn',
                    flex: .3,
                    maxWidth: 130,
                    tpl: new Ext.XTemplate(
                        '{[this.getAccess(values)]}',
                        {
                            getAccess: function(values){
                                var access = '<span class="x-no-access x-semibold">No access</span>';
                                if (values.granted == 1) {
                                    access = '<span class="x-full-access x-semibold">Full access</span>';
                                    Ext.Object.each(values.permissions, function(key, value){
                                        if (value == 0) {
                                            access = '<span class="x-limited-access x-semibold">Limited access</span>';
                                            return false;
                                        }
                                    });
                                }
                                return access;
                            }
                        }
                   )
                }],
                dockedItems: [{
                    xtype: 'toolbar',
                    dock: 'top',
                    ui: 'inline',
                    items: [{
                        xtype: 'filterfield',
                        filterFields: ['name', 'group', function(record){
                            var permissions = record.get('permissions');
                            if (permissions) {
                                permissions = Ext.Object.getKeys(permissions).join(' ');
                            }
                            return permissions;
                        }],
                        store: storeRoleResources,
                        submitValue: false,
                        excludeForm: true
                    },{
                        xtype: 'buttongroupfield',
                        cls: 'scalr-ui-panel-account-roles-btngroup',
                        margin: '0 0 0 18',
                        isFormField: false,
                        value: 'all',
                        defaults: {
                            width: 140
                        },
                        items: [{
                            text: 'All permissions',
                            value: 'all'
                        },{
                            text: 'Allowed',
                            cls: 'scalr-ui-panel-account-roles-btn-allowed',
                            value: 1
                        },{
                            text: 'Limited',
                            cls: 'scalr-ui-panel-account-roles-btn-limited',
                            value: 2
                        },{
                            text: 'Forbidden',
                            cls: 'scalr-ui-panel-account-roles-btn-forbidden',
                            value: 0
                        }],
                        listeners: {
                            change: function(comp, value) {
                                var filterId = 'granted',
                                    filters = [];
                                storeRoleResources.removeFilter(filterId);
                                if (value === 2) {
                                    filters.push({
                                        id: filterId,
                                        filterFn: function(record) {
                                            var res = false;
                                            if (record.get('granted') == 1) {
                                                Ext.Object.each(record.get('permissions'), function(key, value){
                                                    if (value == 0) {
                                                        res = true;
                                                        return false;
                                                    }
                                                });
                                            }
                                            return res;
                                        }
                                    });
                                } else if (value !== 'all') {
                                    filters.push({
                                        id: filterId,
                                        exactMatch: true,
                                        property: 'granted',
                                        value: value
                                    });
                                }
                                storeRoleResources.addFilter(filters);
                            }
                        }
                    }]
                }]
            },{
                xtype: 'component',
                itemId: 'roleusage',
                margin: '12 0 0 0'
            }]
        }],
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
            maxWidth: 1100,
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				itemId: 'save',
				text: 'Save',
				handler: function() {
					var frm = form.getForm(),
						record = frm.getRecord();
					if (frm.isValid()) {
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/account/roles/xSave',
                            form: frm,
							params: {
                                resources: Ext.encode(form.down('#resources').getResources())
                            },
							success: function (data) {
								if (!record.store) {
									record = store.add(data.role)[0];
									dataview.getSelectionModel().select(record);
								} else {
									record.set(data.role);
									form.loadRecord(record);
								}
							}
						});
					}

				}
			}, {
				xtype: 'button',
				itemId: 'cancel',
				text: 'Cancel',
				handler: function() {
                    form.hide();
                    dataview.deselectAndClearLastSelected();
				}
			}, {
				xtype: 'button',
				itemId: 'delete',
				cls: 'x-btn-red',
				text: 'Delete',
				handler: function() {
					var record = form.getForm().getRecord();
					Scalr.Request({
						confirmBox: {
							msg: 'Delete ACL ' + record.get('name') + ' ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting...',
							type: 'delete'
						},
						scope: this,
						url: '/account/roles/xRemove',
						params: {
							id: record.get('id')
						},
						success: function (data) {
							record.store.remove(record);
						}
					});
				}
			}]
		}]
	});

	var panel = Ext.create('Ext.panel.Panel', {
		cls: 'scalr-ui-panel-account-roles',
		scalrOptions: {
			menuTitle: 'ACL',
            menuHref: '#/account/roles',
            menuFavorite: true,
			reload: false,
			maximize: 'all',
			leftMenu: {
				menuId: 'account',
				itemId: 'roles'
			}
		},
        stateId: 'grid-account-roles',
        listeners: {
            applyparams: reconfigurePage
        },
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
        items: [{
            xtype: 'panel',
            cls: 'x-panel-column-left',
            width: 340,
            items: dataview,
            autoScroll: true,
            dockedItems: [{
                xtype: 'toolbar',
                dock: 'top',
                ui: 'simple',
                defaults: {
                    margin: '0 0 0 10'
                },
                items: [{
                    xtype: 'filterfield',
                    itemId: 'rolesLiveSearch',
                    margin: 0,
                    filterFields: ['name'],
                    flex: 1,
                    store: store
                },{
                    itemId: 'add',
                    text: 'New ACL',
                    cls: 'x-btn-green',
                    tooltip: 'New ACL',
                    enableToggle: true,
                    toggleHandler: function (button, state) {
                        if (state) {
                            var baseRole = storeBaseRoles.getUnfiltered().first();
                            dataview.deselectAndClearLastSelected();
                            form.loadRecord(storeRoles.createModel({id: 0, baseRoleId: baseRole ? baseRole.get('id') : 0, 'color': '333333'}));
                            form.down('[name=name]').focus();

                            return;
                        }

                        form.hide();
                    }
                },{
                    itemId: 'refresh',
                    iconCls: 'x-btn-icon-refresh',
                    tooltip: 'Refresh',
                    handler: function() {
                        Scalr.data.reload(['account.*', 'base.roles']);
                        form.hide();
                    }
                }]
            }]
        },{
			xtype: 'container',
            flex: 1,
            layout: 'fit',
			minWidth: 800,
			items: form
        }]
	});

	return panel;
});
Scalr.regPage('Scalr.ui.account2.roles.view', function (loadParams, moduleParams) {
    var firstReconfigure = true;
	var reconfigurePage = function(params) {
        var params = params || {},
            roleId = params.roleId;
        if (firstReconfigure && !roleId) {
            roleId = 'first';
        }
		if (roleId) {
			dataview.deselect(form.getForm().getRecord());
			if (roleId === 'new') {
				panel.down('#add').handler();
			} else {
				panel.down('#rolesLiveSearch').reset();
				var record =  store.getById(roleId) || (roleId === 'first' ? (store.snapshot || store.data).first() : null);
				if (record) {
					dataview.select(record);
				}
			}
		}
        firstReconfigure = false;
	};

    var storeRoles = Scalr.data.get('account.roles'),
        storeBaseRoles = Scalr.data.get('base.roles');

	var store = Ext.create('Scalr.ui.ChildStore', {
		parentStore: storeRoles,
		filterOnLoad: true,
		sortOnLoad: true,
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}]
	});

    var storeRoleResources = Ext.create('Ext.data.Store', {
        filterOnLoad: true,
        sortOnLoad: true,
        fields: ['id', 'name', 'granted', 'group', 'groupOrder', 'permissions'],
        groupField: 'groupOrder'
    });

    var dataview = Ext.create('Ext.view.View', {
		listeners: {
            refresh: function(view){
                var record = view.getSelectionModel().getLastSelected();
                if (record) {
                    form.loadRecord(view.store.getById(record.get('id')));
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
                '<div class="x-dataview-tab"><div class="x-item-color-corner x-item-color-corner-{[values.color?values.color:\'333333\']}" ></div>',
                    '<table>',
                        '<tr>',
                            '<td>',
                                '<div class="x-fieldset-subheader" style="margin:0 0 8px">{name}</div>',
                                '<table>',
                                '<tr><td class="x-dataview-tab-param-title">New permission default:</td><td class="x-dataview-tab-param-value">{[this.getBaseRoleName(values.baseRoleId)]}</td></tr> ',
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
            arrowCls: 'x-grid-empty-arrow2',
			emptyText: '<div class="title">No ACL were found<br/> to match your search.</div>Try modifying your search criteria <br/>or <a class="add-link" href="#">creating a new ACL</a>',
			emptyTextNoItems:	'<div class="title">You have no ACLs<br/> under your account.</div>'+
								'Access Control Lists let you<br/> define exactly what your co-workers<br/> have or don\'t have access to.<br/>' +
								'Click "+" button to create one.',
			onAddItemClick: function() {
				panel.down('#add').handler();
			}
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
				dataview.up('panel').down('#add').setDisabled(false);
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
                this.isLoading = true;
				frm.reset(true);

                this.down('#formtitle').setTitle(isNewRecord ? 'New ACL' : '');
				this.down('#delete').setVisible(!isNewRecord);
				dataview.up('panel').down('#add').setDisabled(isNewRecord);
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
			},
			loadrecord: function(record) {
				var me = this;
				me.getForm().clearInvalid();
				if (!me.isVisible()) {
					me.setVisible(true);
				}
                me.isLoading = false;
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
                labelWidth: 150,
                margin: '0 0 0 30',
                hideInputOnReadOnly: true,
                listeners: {
                    change: function(comp, value, oldValue){
                        if (form.isLoading === true) return;
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
                labelWidth: 45,
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
                cls: 'x-grid-shadow x-grid-with-formfields x-grid-no-highlighting',
                maxWidth: 1100,
                flex: 1,
                hideHeaders: true,
                loadResources: function(resources){
                    if (!resources) return;
                    this.getStore().loadData(resources);
                },
                getResources: function(){
                    var data = [];
                    (this.store.snapshot || this.store.data).each(function(){
                        var r = this.getData(),
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
                        emptyText: '<div class="title">No permissions were found to match your search.</div>Try modifying your search criteria.'
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
                    customRenderer: function(html, record) {
                        var id = record.get('id'),
                            resource = moduleParams['definitions'][id],
                            prefix;
                        prefix = '<div style="float:left;min-width:200px"><div style="font-weight:bold">' + (resource ? resource[0] : id) + '</div><div style="font-size:85%;color:#999">' + (resource ? resource[1] : '') + '</div></div>';
                        return prefix + html.join('')
                    },
                    listeners: {
                        beforechange: function(column, newValue, name, record, cell) {
                            if (record.get('id') == 260) {//RESOURCE_FARMS_ROLES
                                if (newValue['create'] == 1) {
                                    if (name === 'create') {
                                        Ext.apply(newValue, {bundletasks: 1, manage: 1});
                                    } else if ((name === 'bundletasks' || name === 'manage') && newValue[name] == 0){
                                        newValue['create'] = 0;
                                    }
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
                                var access = '<span class="x-no-access">No access</span>';
                                if (values.granted == 1) {
                                    access = '<span class="x-full-access">Full access</span>';
                                    Ext.Object.each(values.permissions, function(key, value){
                                        if (value == 0) {
                                            access = '<span class="x-limited-access">Limited access</span>';
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
                    ui: 'simple',
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
                        isFormField: false,
                        listeners: {
                            afterfilter: function(){
                                //workaround of the extjs grouped store/grid bug
                                var grid = panel.down('#resources'),
                                    grouping = grid.getView().getFeature('grouping');
                                if (grid.headerCt.rendered) {
                                    grid.suspendLayouts();
                                    grouping.disable();
                                    grouping.enable();
                                    grid.resumeLayouts(true);
                                }
                            }
                        }
                    },{
                        xtype: 'buttongroupfield',
                        margin: '0 0 0 18',
                        isFormField: false,
                        value: 'all',
                        defaults: {
                            width: 120
                        },
                        items: [{
                           text: 'All permissions',
                           value: 'all'
                        },{
                            text: 'Allowed',
                            cls: 'x-btn-default-small-green',
                            value: 1
                        },{
                            text: 'Limited',
                            cls: 'x-btn-default-small-blue',
                            value: 2
                        },{
                            text: 'Forbidden',
                            cls: 'x-btn-default-small-red2',
                            value: 0
                        }],
                        listeners: {
                            change: function(comp, value) {
                                var filterId = 'granted',
                                    grid = panel.down('#resources'),
                                    grouping = grid.getView().getFeature('grouping'),
                                    filters = [];
                                //workaround of the extjs grouped store/grid bug (4.2.2 bug is still here)
                                grid.suspendLayouts();
                                grouping.disable();
                                storeRoleResources.filters.each(function(filter){
                                    if (filter.id !== filterId) {
                                        filters.push(filter);
                                    }
                                });
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
                                storeRoleResources.clearFilter(false);
                                storeRoleResources.filter(filters);
                                grouping.enable();
                                grid.resumeLayouts(true);
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
						record = frm.getRecord(),
                        role = frm.getValues();
					if (frm.isValid()) {
                        role.resources = Ext.encode(form.down('#resources').getResources());
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/account/roles/xSave',
							params: role,
							success: function (data) {
								if (!record.get('id')) {
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
					dataview.deselect(form.getForm().getRecord());
					form.setVisible(false);
				}
			}, {
				xtype: 'button',
				itemId: 'delete',
				cls: 'x-btn-default-small-red',
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
			title: 'Access control',
			reload: false,
			maximize: 'all',
			leftMenu: {
				menuId: 'account',
				itemId: 'roles'
			}
		},
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
                defaults: {
                    margin: '0 0 0 10'
                },
                items: [{
                    xtype: 'filterfield',
                    itemId: 'rolesLiveSearch',
                    margin: 0,
                    filterFields: ['name'],
                    width: 180,
                    store: store
                },{
                    xtype: 'tbfill'
                },{
                    itemId: 'add',
                    text: 'Add ACL',
                    cls: 'x-btn-green-bg',
                    tooltip: 'Add ACL',
                    handler: function(){
                        var baseRole = (storeBaseRoles.snapshot || storeBaseRoles.data).first();
                        dataview.deselect(form.getForm().getRecord());
                        form.loadRecord(store.createModel({baseRoleId: baseRole ? baseRole.get('id') : 0}));
                    }
                },{
                    itemId: 'refresh',
                    iconCls: 'x-tbar-loading',
                    ui: 'paging',
                    tooltip: 'Refresh',
                    handler: function() {
                        Scalr.data.reload(['account.*', 'base.roles']);
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
Scalr.regPage('Scalr.ui.account2.acl.view', function (loadParams, moduleParams) {
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

    var storeRoles = Scalr.data.get('account.acl'),
        storeBaseRoles = Scalr.data.get('base.acl');

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
        fields: ['id', 'name', 'granted', 'group', 'groupOrder', 'permissions', 'locked', 'lockedPermissions', {name: 'mode', type: 'int'}],
        groupField: 'groupOrder'
    });

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
                this.down('#permissions').setTitle('Permissions ' + (isNewRecord ? '' : '(<a href="#/account/acl/usage?accountRoleId=' + record.get('id') + '">Helper</a>)'));

                var resources = record.get('resources');
                if (!resources) {
                    var baseRole = storeBaseRoles.getById(record.get('baseRoleId'));
                    if (baseRole) {
                        resources = baseRole.get('resources');
                    }
                }
                this.down('#resources').loadResources(Ext.clone(resources));

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
            itemId: 'permissions',
            cls: 'x-fieldset-separator-none',
            flex: 1,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'grid',
                itemId: 'resources',
                trackMouseOver: false,
                disableSelection: true,
                maxWidth: 1100,
                flex: 1,
                hideHeaders: true,
                scrollable: 'y',
                loadResources: function(resources){
                    if (!resources) return;
                    this.getStore().loadData(resources);
                },
                getResources: function(){
                    var data = [];
                    this.store.getUnfiltered().each(function(resource){
                        var r = resource.getData(),
                            item,
                            permissions = null;
                        if (r.permissions) {
                            permissions = {};
                            Ext.Object.each(r.permissions, function(key, value){
                                permissions[key] = r.granted == 1 ? value : 0;
                            });
                        }
                        item = {
                            id: r.id,
                            granted: r.granted,
                            permissions: permissions
                        };
                        if (r.mode) {
                            item.mode = r.mode;
                        }
                        data.push(item);
                    });
                    return data;
                },
                store: storeRoleResources,
                features: [{
                    id:'grouping',
                    ftype:'grouping',
                    restoreGroupsState: true,
                    groupHeaderTpl: Ext.create('Ext.XTemplate',
                        '{children:this.getGroupName}',
                        {
                            getGroupName: function(children) {
                                if (children.length > 0) {
                                    var name = children[0].get('group');
                                    return name === 'Account management' || name === 'Environment management' ? '<span class="x-permission-warn">' + name + '</span>&nbsp;&nbsp;<img data-qtip="Be careful assigning administrative permissions" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-warning" style="margin-top:-2px" >' : name;
                                }
                            }
                        }
                    )
                }],
                viewConfig: {
                    preserveScrollOnRefresh: false,
                    markDirty: false,
                    plugins: {
                        ptype: 'dynemptytext',
                        emptyText: '<div class="x-semibold title">No permissions were found to match your search.</div>Try modifying your search criteria.'
                    }
                },
                columns: [{
                    xtype: 'aclresourcecolumn',
                    flex: 1,
                    definitions: moduleParams['definitions']
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
                        margin: '0 0 0 18',
                        maxWidth: 600,
                        isFormField: false,
                        value: 'all',
                        layout: 'hbox',
                        flex: 1,
                        defaults: {
                            flex: 1
                        },
                        items: [{
                            text: 'All',
                            value: 'all'
                        },{
                            text: 'Full access',
                            cls: 'x-full-access',
                            value: 1
                        },{
                            text: 'Limited',
                            cls: 'x-limited-access',
                            value: 2
                        },{
                            text: 'Read only',
                            cls: 'x-readonly-access',
                            value: 3
                        },{
                            text: 'No access',
                            cls: 'x-no-access',
                            value: 0
                        }],
                        listeners: {
                            change: function(comp, value) {
                                var filterId = 'granted',
                                    filters = [];
                                storeRoleResources.removeFilter(filterId);
                                if (value === 1) {
                                    filters.push({
                                        id: filterId,
                                        filterFn: function(record) {
                                            var res = false;
                                            if (record.get('granted') == 1) {
                                                var permissions = record.get('permissions');
                                                res = true;
                                                if (!Ext.isEmpty(permissions)) {
                                                    Ext.Object.each(permissions, function(key, value){
                                                        if (value == 0) {
                                                            return res = false;
                                                        }
                                                    });
                                                }
                                            }
                                            return res;
                                        }
                                    });
                                } else if (value === 2) {
                                    filters.push({
                                        id: filterId,
                                        filterFn: function(record) {
                                            var res = false;
                                            if (record.get('granted') == 1) {
                                                var permissions = record.get('permissions'),
                                                    hasEnabled, hasDisabled;
                                                if (!Ext.isEmpty(permissions) && (Ext.Object.getSize(permissions) > 1 || permissions['manage'] === undefined)) {
                                                    Ext.Object.each(permissions, function(key, value){
                                                        hasEnabled = hasEnabled || value == 1;
                                                        hasDisabled = hasDisabled || value == 0;
                                                    });
                                                    res = hasEnabled && hasDisabled;
                                                }
                                            }
                                            return res;
                                        }
                                    });
                                } else if (value === 3) {
                                    filters.push({
                                        id: filterId,
                                        filterFn: function(record) {
                                            var res = false;
                                            if (record.get('granted') == 1) {
                                                var permissions = record.get('permissions');
                                                if (!Ext.isEmpty(permissions)) {
                                                    res = true;
                                                    Ext.Object.each(permissions, function(key, value){
                                                        return (res = value == 0);
                                                    });
                                                }
                                            }
                                            return res;
                                        }
                                    });
                                } else if (value === 0) {
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
                            url: '/account/acl/xSave',
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
                        url: '/account/acl/xRemove',
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
            menuHref: '#/account/acl',
            menuFavorite: true,
            reload: false,
            maximize: 'all',
            leftMenu: {
                menuId: 'account',
                itemId: 'roles'
            }
        },
        stateId: 'grid-account-acl',
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
                        Scalr.data.reload(['account.*', 'base.acl']);
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
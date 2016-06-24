Scalr.regPage('Scalr.ui.account2.environments.accessmap', function (loadParams, moduleParams) {
    var storeUsers = Ext.create('Ext.data.Store', {
        fields: ['id', 'name', 'email'],
        data: moduleParams['users'],
        queryMode: 'local'
    });

    var storeUserResources = Ext.create('Ext.data.Store', {
        fields: ['id', 'name', 'granted', 'group', 'groupOrder', 'permissions', 'mode'],
        groupField: 'groupOrder'
    });

    var form = Ext.create('Ext.form.Panel', {
        scalrOptions: {
            'modal': true
        },
        width: 900,
        cls: 'scalr-ui-panel-account-roles',
        bodyCls: 'x-container-fieldset',
        title: 'Environments &raquo; ' + moduleParams['env']['name'] + '&raquo; User permissions summary',
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 130
        },
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        items: [{
            xtype: 'combo',
            emptyText: 'Select user to review permissions summary',
            valueField: 'id',
            displayField: 'name',
            editable: false,
            store: storeUsers,
            listConfig: {
               tpl : '<tpl for=".">'+
                          '<div class="x-boundlist-item"><span class="x-semibold">{name}</span> <span style="color:#999">&lt; {email} &gt;</span></div>'+
                      '</tpl>'
            },
            listeners: {
                change: function(comp, value){
                    Scalr.Request({
                        processBox: {
                            type: 'action'
                        },
                        url: '/account/environments/accessmap/xGet',
                        params: {
                            userId: value,
                            envId: moduleParams['env']['id']
                        },
                        success: function(data){
                            var grantedFilterField = form.down('#grantedfilter');
                            storeUserResources.loadData(data['resources']);
                            grantedFilterField.setValue(grantedFilterField.getValue() || 1);
                            form.down('#resources').show();
                        }
                    });
                }
            }
        },{
            xtype: 'panel',//extra panel added in v5.1.0: collapsing/expanding groups resets scroll position(view.preserveScrollOnRefresh has no effect but our custom container.preserveScrollPosition does work)
            flex: 1,
            preserveScrollPosition: true,
            scrollable: 'y',
            hidden: true,
            itemId: 'resources',
            items: {
                xtype: 'grid',
                cls: 'x-grid-with-formfields',
                trackMouseOver: false,
                disableSelection: true,
                flex: 1,
                hideHeaders: true,
                store: storeUserResources,
                features: [{
                    id:'grouping',
                    ftype:'grouping',
                    groupHeaderTpl: Ext.create('Ext.XTemplate',
                        '{children:this.getGroupName}',
                        {
                            getGroupName: function(children) {
                                if (children.length > 0) {
                                    return children[0].get('group');
                                }
                            }
                        }
                    )
                }],
                viewConfig: {
                    plugins: {
                        ptype: 'dynemptytext',
                        emptyText: '<div class="title">No permissions were found to match your search.</div>Try modifying your search criteria.'
                    }
                },
                columns: [{
                    xtype: 'aclresourcecolumn',
                    flex: 1,
                    readOnly: true,
                    definitions: moduleParams['definitions']
                }],
            },
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
                    store: storeUserResources,
                    submitValue: false,
                    excludeForm: true
                },{
                    xtype: 'buttongroupfield',
                    itemId: 'grantedfilter',
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
                            storeUserResources.removeFilter(filterId);
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
                            storeUserResources.addFilter(filters);
                        }
                    }
                }]
            }]
        }],
        tools: [{
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent('close');
            }
        }]
    });

    return form;
});

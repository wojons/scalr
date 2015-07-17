Scalr.regPage('Scalr.ui.account2.environments.accessmap', function (loadParams, moduleParams) {
    var storeUsers = Ext.create('Ext.data.Store', {
        fields: ['id', 'name', 'email'],
        data: moduleParams['users'],
        queryMode: 'local'
    });

    var storeUserResources = Ext.create('Ext.data.Store', {
        filterOnLoad: true,
        sortOnLoad: true,
        fields: ['id', 'name', 'granted', 'group', 'groupOrder', 'permissions'],
        groupField: 'group'
    });

	var form = Ext.create('Ext.form.Panel', {
		scalrOptions: {
			'modal': true
		},
		width: 900,
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
                            grantedFilterField.setValue(grantedFilterField.getValue() || 'allowed');
                            form.down('#resources').show();
                        }
                    });
                }
            }
        },{
            xtype: 'grid',
            padding: '0 0 12',
            hidden: true,
            margin: '18 0 0',
            itemId: 'resources',
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
                preserveScrollOnRefresh: true,
                plugins: {
                    ptype: 'dynemptytext',
                    emptyText: '<div class="title">No permissions were found to match your search.</div>Try modifying your search criteria.'
                }
            },
            columns: [{
                xtype: 'multicheckboxcolumn',
                flex: 1,
                dataIndex: 'permissions',
                readOnly: true,
                labelRenderer: function(key) {
                    return key === 'ssh-console' ? 'SSH Launcher' : key;
                },
                cellRenderer: function(value, record) {
                    var id = record.get('id'),
                        resource = moduleParams['definitions'][id],
                        prefix;
                    prefix = '<div style="float:left;min-width:200px"><div class="x-semibold">' + (resource ? resource[0] : id) + '</div><div style="font-size:85%;color:#999;line-height:1.6em">' + (resource ? resource[1] : '') + '</div></div>';
                    return prefix + value;
                }
            },{
                xtype: 'templatecolumn',
                width: 130,
                tpl: new Ext.XTemplate(
                    '{[this.getAccess(values)]}',
                    {
                        getAccess: function(values){
                            var access = '<span class="x-semibold" style="color:#c00000">No access</span>';
                            if (values.granted == 1) {
                                access = '<span class="x-semibold" style="color:#008000">Full access</span>';
                                Ext.Object.each(values.permissions, function(key, value){
                                    if (value == 0) {
                                        access = '<span class="x-semibold" style="color:#337dce">Limited access</span>';
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
                ui: 'inline',
                dock: 'top',
                items: [{
                    xtype: 'filterfield',
                    filterFields: ['name', 'group'],
                    store: storeUserResources,
                    submitValue: false,
                    doNotReset: true,
                    listeners: {
                        afterfilter: function(){
                            //workaround of the extjs grouped store/grid bug
                            var grid = form.down('#resources'),
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
                    itemId: 'grantedfilter',
                    doNotReset: true,
                    margin: '0 0 0 18',
                    defaults: {
                        width: 140
                    },
                    items: [{
                       text: 'All permissions',
                       value: 'all'
                    },{
                        text: 'Allowed',
                        cls: 'x-btn-green',
                        value: 'allowed'
                    },{
                        text: 'Limited',
                        cls: 'x-btn-blue',
                        value: 'limited'
                    },{
                        text: 'Forbidden',
                        cls: 'x-btn-pink',
                        value: 'forbidden'
                    }],
                    listeners: {
                        change: function(comp, value) {
                            var filterId = 'granted',
                                filters = [];
                            storeUserResources.removeFilter(filterId);
                            if (value === 'limited') {
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
                                    value: value === 'allowed' ? 1 : 0
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

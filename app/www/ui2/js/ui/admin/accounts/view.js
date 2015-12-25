Scalr.regPage('Scalr.ui.admin.accounts.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            {name: 'id', type: 'int'},
            'name', 'dtadded', 'status', 'servers', 'users', 'envs', 'farms', 'limitEnvs', 'limitFarms', 'limitUsers', 'limitServers', 'ownerEmail', 'dnsZones', 'isTrial'
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/admin/accounts/xListAccounts'
        },
        remoteSort: true
    });

    var loginAsUser = function() {
        Scalr.state.pageSuspend = true;
        Scalr.event.fireEvent('redirect', '#/dashboard');
        Ext.Function.defer(function() {
            Scalr.state.pageSuspend = false;
            delete Scalr.state.changelogData; // clear to get info for new user
            Scalr.application.updateContext(Ext.emptyFn, false, {
                'X-Scalr-Userid': null,
                'X-Scalr-Scope': 'environment'
            });
        }, 50);
    };

    return Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Accounts',
            menuHref: '#/admin/accounts',
            menuFavorite: true
        },
        store: store,
        stateId: 'grid-admin-accounts-view',
        stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],
        viewConfig: {
            emptyText: 'No accounts found',
            loadingText: 'Loading accounts ...'
        },

        columns: [
            { header: "ID", width: 60, dataIndex: 'id', sortable: true },
            { header: "Account", flex:1, dataIndex: 'name', sortable: true },
            { header: Scalr.flags['authMode'] == 'ldap' ? 'LDAP login' : 'Owner email', flex: 1, dataIndex: 'ownerEmail',
                sortable: false, xtype: 'templatecolumn',
                tpl: '<tpl if="ownerLocked"><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-warning" data-qtip="Owner of this account was suspended." style="cursor:default" /></tpl> {ownerEmail}'
            },
            { header: "Added", flex: 1, dataIndex: 'dtadded', sortable: true, xtype: 'templatecolumn',
                tpl: '{[values.dtadded ? values.dtadded : ""]}'
            },
            { text: "Status", width: 100, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
                new Ext.XTemplate('<span style="color: {[this.getClass(values.status)]}">{status} ({isTrial})</span>', {
                    getClass: function (value) {
                        if (value == 'Active')
                            return "green";
                        else if (value != 'Inactive')
                            return "#666633";
                        else
                            return "red";
                    }
                })
            },
            { header: "Environments", width:  100, align:'center', dataIndex: 'envs', sortable: false, xtype: 'templatecolumn',
                tpl: '{envs}/{limitEnvs}'
            },
            { header: "Users", width: 100, dataIndex: 'users', align:'center', sortable: false, xtype: 'templatecolumn',
                tpl: '{users}/{limitUsers}'
            },
            { header: "Servers", width: 100, dataIndex: 'groups', align:'center', sortable: false, xtype: 'templatecolumn',
                tpl: '{servers}/{limitServers}'
            },
            { header: "Farms", width: 100, dataIndex: 'farms', align:'center', sortable: false, xtype: 'templatecolumn',
                tpl: '{farms}/{limitFarms}'
            },
            { header: "DNS Zones", width:  100, align:'center', dataIndex: 'dnsZones', sortable: false, xtype: 'templatecolumn',
                tpl: '{dnsZones}'
            },
            {
                xtype: 'optionscolumn',
                menu: [{
                    iconCls: 'x-menu-icon-edit',
                    text: 'Edit',
                    showAsQuickAction: true,
                    href: "#/admin/accounts/{id}/edit"
                }, {
                    text: 'Unlock owner',
                    request: {
                        processBox: {
                            type: 'action'
                        },
                        url: '/admin/accounts/xUnlockOwner',
                        dataHandler: function (data) {
                            return { accountId: data['id'] };
                        },
                        success: function() {
                            store.load();
                        }
                    },
                    getVisibility: function(data) {
                        return !!data['ownerLocked'];
                    }
                }, {
                    iconCls: 'x-menu-icon-login',
                    text: 'Login as owner',
                    showAsQuickAction: true,
                    request: {
                        processBox: {
                            type: 'action'
                        },
                        url: '/admin/accounts/xLoginAs',
                        dataHandler: function (data) {
                            return { accountId: data['id'] };
                        },
                        success: function() {
                            loginAsUser();
                        }
                    },
                    getVisibility: function(data) {
                        return !data['ownerLocked'];
                    }
                }, {
                    iconCls: 'x-menu-icon-login',
                    text: 'Login as user',
                    request: {
                        processBox: {
                            type: 'action'
                        },
                        url: '/admin/accounts/xGetUsers',
                        dataHandler: function (data) {
                            return { accountId: data['id'] };
                        },
                        success: function (data) {
                            Scalr.Request({
                                confirmBox: {
                                    formValidate: true,
                                    type: 'action',
                                    msg: 'Please select user. You can search by id, email, type.',
                                    formSimple: true,
                                    form: [{
                                        xtype: 'combo',
                                        name: 'userId',
                                        store: {
                                            fields: [ 'id', 'email', 'fullname', 'type', {name: 'display', convert: function(v, record){
                                                return '['+record.data.id+'] '+record.data.email+' ['+record.data.type+']';
                                            }}],
                                            data: data.users,
                                            proxy: 'object'
                                        },
                                        allowBlank: false,
                                        anyMatch: true,
                                        restoreValueOnBlur: true,
                                        editable: true,
                                        valueField: 'id',
                                        displayField: 'display',
                                        queryMode: 'local'
                                    }]
                                },
                                processBox: {
                                    type: 'action'
                                },
                                url: '/admin/accounts/xLoginAs',
                                success: function() {
                                    loginAsUser();
                                }
                            });
                        }
                    }
                },{
                    iconCls: 'x-menu-icon-key',
                    text: 'Change owner password',
                    href: "#/admin/accounts/{id}/changeOwnerPassword",
                    getVisibility: function() {
                        return Scalr.flags['authMode'] != 'ldap';
                    }
                }]
            }
        ],

        selType: 'selectedmodel',
        listeners: {
            selectionchange: function(selModel, selections) {
                var toolbar = this.down('scalrpagingtoolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
            }
        },

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            beforeItems: [{
                text: 'New account',
                cls: 'x-btn-green',
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/admin/accounts/create');
                }
            }],
            afterItems: [{
                itemId: 'delete',
                disabled: true,
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Delete',
                handler: function() {
                    var request = {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Remove selected accounts(s): %s ?'
                        },
                        processBox: {
                            type: 'delete',
                            msg: 'Removing account(s)...'
                        },
                        url: '/admin/accounts/xRemove',
                        success: function() {
                            store.load();
                        }
                    }, records = this.up('grid').getSelectionModel().getSelection(), data = [];

                    request.confirmBox.objects = [];
                    for (var i = 0, len = records.length; i < len; i++) {
                        data.push(records[i].get('id'));
                        request.confirmBox.objects.push(records[i].get('name'))
                    }
                    request.params = { accounts: Ext.encode(data) };
                    Scalr.Request(request);
                }
            }],
            items: [{
                xtype: 'filterfield',
                width: 250,
                form: {
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'ServerId',
                        labelAlign: 'top',
                        name: 'serverId'
                    },{
                        xtype: 'textfield',
                        fieldLabel: 'FarmId',
                        labelAlign: 'top',
                        name: 'farmId'
                    }, {
                        xtype: 'textfield',
                        fieldLabel: 'Owner',
                        labelAlign: 'top',
                        name: 'owner'
                    }, {
                        xtype: 'textfield',
                        fieldLabel: 'User',
                        labelAlign: 'top',
                        name: 'user'
                    } ,{
                        xtype: 'textfield',
                        fieldLabel: 'EnvId',
                        labelAlign: 'top',
                        name: 'envId'
                    }]
                },
                store: store
            }]
        }]
    });
});

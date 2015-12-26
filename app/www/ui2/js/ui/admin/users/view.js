Scalr.regPage('Scalr.ui.admin.users.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'id', 'status', 'email', 'fullname', 'dtcreated', 'dtlastlogin', 'type', 'comments', 'is2FaEnabled'
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/admin/users/xListUsers'
        },
        remoteSort: true
    });

    return Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Admins'
        },
        store: store,
        stateId: 'grid-admin-users-view',
        stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],
        viewConfig: {
            emptyText: 'No users found',
            loadingText: 'Loading users ...'
        },

        columns: [
            { text: 'ID', width: 80, dataIndex: 'id', sortable: true },
            { text: 'Email', flex: 1, dataIndex: 'email', sortable: true },
            { text: 'Status', Width: 50, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
                '<span ' +
                '<tpl if="status == &quot;Active&quot;">style="color: green"</tpl>' +
                '<tpl if="status != &quot;Active&quot;">style="color: red"</tpl>' +
                '>{status}</span>'
            },
            { text: 'Type', width: 140, dataIndex: 'type', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="type == &quot;ScalrAdmin&quot;">Global admin' +
                '<tpl else>Financial admin</tpl>'
            },
            { text: 'Full name', flex: 1, dataIndex: 'fullname', sortable: true },
            { text: '2FA',  width: 70, align: 'center', dataIndex: 'is2FaEnabled', sortable: false, xtype: 'templatecolumn',
                tpl: '<tpl if="is2FaEnabled"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div><tpl else>&mdash;</tpl>'
            },
            { text: 'Created date', width: 180, dataIndex: 'dtcreated', sortable: true },
            { text: 'Last login', width: 180, dataIndex: 'dtlastlogin', sortable: true },
            {
                xtype: 'optionscolumn',
                getVisibility: function(record) {
                    if (record.get('email') == 'admin') {
                        return (Scalr.user.userName == 'admin');
                    } else
                        return true;
                },
                menu: [{
                    text: 'Edit',
                    iconCls: 'x-menu-icon-edit',
                    showAsQuickAction: true,
                    href: '#/admin/users/{id}/edit'
                }, {
                    text: 'Remove',
                    iconCls: 'x-menu-icon-delete',
                    showAsQuickAction: true,
                    getVisibility: function (data) {
                        return data['email'] !== 'admin';
                    },
                    request: {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Are you sure want to remove user "{email}" ?'
                        },
                        processBox: {
                            type: 'delete'
                        },
                        url: '/admin/users/xRemove',
                        dataHandler: function (data) {
                            return { userId: data['id'] };
                        },
                        success: function () {
                            store.load();
                        }
                    }
                }]
            }
        ],

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            beforeItems: [{
                text: 'New user',
                cls: 'x-btn-green',
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/admin/users/create');
                }
            }],
            items: [{
                xtype: 'filterfield',
                store: store
            }]
        }]
    });
});

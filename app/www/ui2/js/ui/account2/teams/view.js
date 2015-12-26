Scalr.regPage('Scalr.ui.account2.teams.view', function (loadParams, moduleParams) {
    var UNASSIGNED_TEAM_ID = 9999999,
        NEW_TEAM_ID = 0,
        isAccountOwner = Scalr.user['type'] === 'AccountOwner',
        readOnlyAccess = !Scalr.utils.canManageAcl(),
        storeTeams = Scalr.data.get('account.teams'),
        storeUsers = Scalr.data.get('account.users'),
        storeRoles = Scalr.data.get('account.acl'),
        storeEnvironments = Scalr.data.get('account.environments');

    var firstReconfigure = true;
    var reconfigurePage = function(params) {
        var params = params || {},
            teamId = params.teamId;
        if (firstReconfigure && !teamId) {
            teamId = 'first';
        }
        if (teamId) {
            dataview.deselectAndClearLastSelected();
            if (teamId === 'new') {
                panel.down('#add').toggle(true);
            } else {
                panel.down('#teamsLiveSearch').reset();
                var record = store.getById(teamId) || (teamId === 'first' ? store.first() : null);
                if (record) {
                    dataview.select(record);
                }
            }
        }
        firstReconfigure = false;
    };

    var store = Ext.create('Ext.data.ChainedStore', {
        source: storeTeams,
        sorters: [{
            property: 'name',
            transform: function(value){
                return value.toLowerCase();
            }
        }]
    });

    var storeDefaultRole = Ext.create('store.store', {
        filterOnLoad: true,
        sortOnLoad: true,
        fields: ['id', 'name', 'color'],
        sorters: [{
            property: 'name',
            transform: function(value){
                return value.toLowerCase();
            }
        }],
        loadRoles: function (data) {
            var roles = [];
            data.each(function(role){
                roles.push({id: role.get('id'), name: role.get('name'), color: role.get('color')});
            });
            this.loadData(roles);
        }
    });
    storeDefaultRole.loadRoles(storeRoles.getUnfiltered());

    var storeTeamUsers = Ext.create('store.store', {
        filterOnLoad: true,
        sortOnLoad: true,
        fields: [
            'id', 'fullname', 'email', 'teamId', 'roles'
        ],
        groupField: 'teamId',
        loadTeam: function(team) {
            var users = [],
                teamUsers = team.get('users');
            if (Ext.isEmpty(teamUsers)) {
                teamUsers = null;
            }
            storeUsers.each(function(userRecord){
                var user = {
                    id: userRecord.get('id'),
                    fullname: userRecord.get('fullname'),
                    email: userRecord.get('email')
                }, flag = false;
                user.teamId = UNASSIGNED_TEAM_ID;
                if (teamUsers) {
                    for (var j=0, len=teamUsers.length; j<len; j++) {
                        if (teamUsers[j].id == user.id) {
                            user.teamId = team.get('id');
                            user.roles = teamUsers[j].roles;
                            flag = true;
                            break;
                        }
                    }
                }

                if (Scalr.flags['authMode'] == 'ldap') {
                    // for LDAP: show only team members
                    if (flag)
                        users.push(user);
                } else {
                    users.push(user);
                }

            });
            this.loadData(users);
        }
    });

    var dataview = Ext.create('Ext.view.View', {
        deferInitialRefresh: false,
        store: store,
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
        cls: 'x-dataview',
        itemCls: 'x-dataview-tab',
        selectedItemCls : 'x-dataview-tab-selected',
        overItemCls : 'x-dataview-tab-over',
        itemSelector: '.x-dataview-tab',
        tpl  : Ext.create('Ext.XTemplate',
            '<tpl for=".">',
                '<div class="x-dataview-tab">',
                    '<table>',
                        '<tr>',
                            '<td colspan="2">',
                                '<div class="x-fieldset-subheader">{name}',
                                '<tpl if="description"> ({description})</tpl>',
                                '</div>',
                            '</td>',
                        '</tr>',
                        '<tr>',
                            '<td width="120">',
                                '<span class="x-form-item-label-default">Users&nbsp; </span><span class="x-dataview-tab-param-value">{[values.users ? values.users.length : 0]}</span>',
                            '</td>',
                            '<td style="padding-left:10px">',
                                '{[this.getTeamEnvironmentsList(values.id)]}',
                            '</td>',
                        '</tr>',
                    '</table>',
                '</div>',
            '</tpl>',
            {
                getTeamEnvironmentsList: function(teamId){
                    var list = [];
                    storeEnvironments.each(function() {
                        if (
                            Scalr.flags['authMode'] == 'scalr' && Ext.Array.contains(this.get('teams') || [], teamId) ||
                            Scalr.flags['authMode'] == 'ldap' && Ext.Array.contains(this.get('teamIds') || [], teamId)
                        ) {
                            list.push(this.get('name'));
                        }
                    });
                    return list.length > 0 ? '<span class="x-form-item-label-default">Access to&nbsp; </span><span class="x-dataview-tab-param-value">'+list.join(', ')+'</span>' : '<i>no access to environments</i>';
                }
            }
        ),
        plugins: {
            ptype: 'dynemptytext',
            showArrow: Scalr.flags['authMode'] == 'ldap',
            emptyText: Scalr.flags['authMode'] == 'ldap' ?
                '<div class="x-semibold title">No teams were found to match your search.</div> Try modifying your search criteria.':
                '<div class="x-semibold title">No teams were found to match your search.</div> Try modifying your search criteria or creating a new team.',

            emptyTextNoItems: Scalr.flags['authMode'] == 'ldap' ?
                '<div class="x-semibold title">You have no teams under your account yet.</div>' +
                'Teams are based on LDAP groups, and let you organize your co-workers\' access to different parts of your infrastructure.<br/>' +
                'Please specify which LDAP groups have access to your Scalr environments on the Environments tab.</div>' :
                '<div class="x-semibold title">You have no teams under your account.</div>'+
                                'Teams let you organize your co-workers\' access<br/> to different parts of your infrastructure',
            onAddItemClick: function() {
                panel.down('#add').toggle(true);
            }
        },
        loadingText: 'Loading teams ...',
        deferEmptyText: false
    });

    var menuUserRoles = Ext.create('Ext.menu.Menu', {
        cls: 'x-menu-light',
        setTeam: function(teamRecord){
            var items = [];
            storeDefaultRole.getUnfiltered().each(function(role){
                items.push({
                    xtype: 'menucheckitem',
                    text: '<span style="color:#'+role.get('color')+'">'+role.get('name')+'</span>',
                    value: role.get('id')
                });
            });
            this.removeAll();
            this.add(items);
        },
        setUser: function(btnEl, userRecord) {
            var userRoles = userRecord.get('roles');
            this.userRecord = userRecord;
            this.items.each(function(item){
                var checked = false;
                for (var i=0, len=userRoles.length; i<len; i++) {
                    if (userRoles[i] == item.value) {
                        checked = true;
                        break;
                    }
                }
                item.setChecked(checked, true);
            });

            var xy = btnEl.getXY(), sizeX = xy[1] + btnEl.getHeight() + this.getHeight();
            if (sizeX > Scalr.application.getHeight()) {
                xy[1] -= sizeX - Scalr.application.getHeight();
            }
            this.show().setPosition([xy[0] - (this.getWidth() - btnEl.getWidth()), xy[1] + btnEl.getHeight() + 1]);
            this.focus();
        },
        defaults: {
            listeners: {
                checkchange: function(menuitem, checked) {
                    var menu = menuitem.parentMenu;
                    var ids = [];
                    menu.items.each(function(item){
                        if (item.checked) {
                            ids.push(item.value);
                        }
                    });
                    menu.userRecord.set('roles', ids);
                }
            }
        }
    });
    menuUserRoles.doAutoRender();

    var gridTeamMembers = Ext.create('Ext.grid.Panel', {
        cls: readOnlyAccess ? 'x-grid-no-highlighting' : '',
        maxWidth: 770,
        store: storeTeamUsers,
        listeners: {
            viewready: function(){
                var onRolesChange = function(){
                    var record = form.getRecord();
                    if (record) {
                        menuUserRoles.setTeam(record);
                        storeTeamUsers.loadTeam(record);
                    }
                    storeDefaultRole.loadRoles(storeRoles.getUnfiltered());
                }

                storeUsers.on({
                    add: function(store, records, index) {
                        for (var i=0, len=records.length; i<len; i++) {
                            storeTeamUsers.add({
                                id: records[i].get('id'),
                                fullname: records[i].get('fullname'),
                                email: records[i].get('email'),
                                teamId: UNASSIGNED_TEAM_ID
                            });
                        }
                    },
                    remove: function(store, records){
                        Ext.each(records, function(record){
                            var teamUser = storeTeamUsers.getById(record.get('id'));
                            if (teamUser) {
                                storeTeamUsers.remove(teamUser);
                            }
                        })
                    },
                    update: function(store, record, operation, fields){
                        if (operation == Ext.data.Model.EDIT) {
                            var teamUser = storeTeamUsers.getById(record.get('id'));
                            if (teamUser) {
                                teamUser.beginEdit();
                                for (var i=0, len=fields.length; i<len; i++) {
                                    teamUser.set(fields[i], record.get(fields[i]));
                                }
                                teamUser.endEdit();
                            }
                        }
                    },
                    refresh: onRolesChange
                });

                storeRoles.on({
                    add: onRolesChange,
                    remove: onRolesChange,
                    update: onRolesChange,
                    refresh: onRolesChange
                });
                onRolesChange();
            }
        },
        features: [{
            id:'grouping',
            ftype:'grouping',
            disabled: readOnlyAccess || Scalr.flags['authMode'] == 'ldap',
            restoreGroupsState: true,
            groupHeaderTpl: '{[(values.name=='+UNASSIGNED_TEAM_ID+'?"Not in team":"In team")+ \' (\' + values.children.length + \' user\' + (values.children.length !== 1 ? \'s\' : \'\') + \')\']}'
        }],
        dockedItems: [{
            xtype: 'toolbar',
            ui: 'inline',
            dock: 'top',
            items: [{
                xtype: 'filterfield',
                filterFields: ['fullname', 'email'],
                store: storeTeamUsers,
                submitValue: false,
                excludeForm: true
            }]
        }],
        viewConfig: {
            focusedItemCls: '',
            plugins: {
                ptype: 'dynemptytext',
                emptyText: '<div class="x-semibold title">No users were found to match your search.</div>Try modifying your search criteria ' + (readOnlyAccess || Scalr.flags['authMode'] == 'ldap' ? '' : 'or <a href="#/account/users?userId=new">creating a new user</a>')
            },
            loadingText: 'Loading users ...',
            deferEmptyText: false,
            listeners: {
                itemclick: function (view, record, item, index, e) {
                    var grid = view.up('panel');
                    if (e.getTarget('img.team-add-remove')) {//user add/remove buttons
                        grid.suspendLayouts();
                        view.getFeature('grouping').disable();//workaround of the extjs grouped store/grid bug: moving record to the collapsed group causes error
                        if (record.get('teamId') != UNASSIGNED_TEAM_ID) {
                            record.set('teamId', UNASSIGNED_TEAM_ID);
                            record.set('roles', null);
                        } else {
                            record.set('teamId', form.getForm().getRecord().get('id'));
                            record.set('roles', []);
                        }
                        view.getFeature('grouping').enable();//workaround of the extjs grouped store/grid bug
                        grid.getSelectionModel().deselect(record);
                        grid.resumeLayouts(true);
                    } else if (e.getTarget('.x-grid-row-options')) {
                        var btnEl = Ext.get(item).down('div.x-grid-row-options');
                        menuUserRoles.setUser(btnEl, record);
                    }
                },
                groupexpand: function(){
                    menuUserRoles.hide();
                },
                groupcollapse: function(){
                    menuUserRoles.hide();
                }
            }
        },
        columns: [{
            text: 'User',
            flex: 2,
            dataIndex: 'fullname',
            sortable: true,
            xtype: 'templatecolumn',
            tpl:
                '<tpl if="values.fullname">{fullname} <span style="color:#999">&lt;{email}&gt;</span><tpl else>{email}</tpl>'
        },{
            text: 'Access control list',
            flex: 1.5,
            sortable: false,
            resizable: false,
            xtype: 'templatecolumn',
            tpl: new Ext.XTemplate(
                '<tpl if="values.teamId!='+UNASSIGNED_TEAM_ID+'">',
                    readOnlyAccess ? '' : '<div class="x-grid-row-options"><div class="x-grid-row-options-icon"></div></div>',
                    '<div data-qclass="x-tip-light x-tip-light-no-underline" data-qtip="{[Ext.htmlEncode(this.getRolesList(values.roles))]}" style="text-overflow:ellipsis;overflow:hidden">{[this.getRolesList(values.roles)]}&nbsp;</div>',
                '</tpl>',
            {
                getRolesList: function(roles){
                    var html = [],
                        defaultRoleField = form.getForm().findField('account_role_id'),
                        defaultRoleRecord = defaultRoleField.findRecordByValue(defaultRoleField.getValue());
                    if (roles) {
                        for (var i=0, len=roles.length; i<len; i++) {
                            var role = storeRoles.getById(roles[i]);
                            if (role) {
                                html.push(readOnlyAccess ? role.get('name') : '<a href="#/account/acl?roleId=' + role.get('id') + '" class="x-semibold user-permission" style="color:#' + role.get('color') + '">' + role.get('name') + '</a>');
                            }
                        }
                    }

                    return html.length > 0 ? html.join(', ') : (defaultRoleRecord && defaultRoleRecord.get('id') ? '<span class="x-semibold" style="color:#'+defaultRoleRecord.get('color')+'">' + defaultRoleRecord.get('name')+'</span> (team\'s default ACL)' : '<span style="color:red">No access</span>');
                }
            })
        },{
            width: 40,
            hidden: readOnlyAccess || Scalr.flags['authMode'] == 'ldap',
            xtype: 'templatecolumn',
            tpl: '<img class="team-add-remove x-grid-icon x-grid-icon-{[values.teamId!='+UNASSIGNED_TEAM_ID+' ? "removegridline" : "addgridline"]}" title="{[values.teamId!='+UNASSIGNED_TEAM_ID+'?"Remove "+(values.fullname || values.email)+" from team":"Add "+(values.fullname || values.email)+" to team"]}" src="'+Ext.BLANK_IMAGE_URL+'"/>',
            resizable: false,
            sortable: false
        }]

    });

    var form = 	Ext.create('Ext.form.Panel', {
        hidden: true,
        fieldDefaults: {
            anchor: '100%'
        },
        layout: {
            type: 'vbox',
            align : 'stretch',
            pack  : 'start'
        },
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
                var isNewRecord = record.get('id') == NEW_TEAM_ID,
                    c = this.query('component[cls~=hideoncreate], #delete');
                for (var i=0, len=c.length; i<len; i++) {
                    c[i].setVisible(!isNewRecord);
                }
                this.down('#formtitle').setTitle(isNewRecord ? 'New team' : 'Edit team');
                dataview.up('panel').down('#add').toggle(isNewRecord, true);
            },
            loadrecord: function(record) {
                storeTeamUsers.loadTeam(record);
                menuUserRoles.setTeam(record);
            }
        },
        items: [{
            xtype: 'hiddenfield',
            name: 'id'
        },{
            xtype: 'fieldset',
            itemId: 'formtitle',
            title: '&nbsp;',
            defaults: {
                flex: 1,
                maxWidth: 370
            },
            layout: 'hbox',
            items: [{
                xtype: 'textfield',
                name: 'name',
                fieldLabel: 'Name',
                labelWidth: 60,
                readOnly: readOnlyAccess,
                allowBlank: false

            },{
                xtype: 'combo',
                fieldLabel: 'Default ACL',
                store: storeDefaultRole,
                editable: false,
                allowBlank: false,
                displayField: 'name',
                valueField: 'id',
                name: 'account_role_id',
                queryMode: 'local',
                labelWidth: 100,
                margin: '0 0 0 30',
                readOnly: readOnlyAccess,
                listeners: {
                    change: function(comp, value) {
                        var form = this.up('form'),
                            record = this.findRecordByValue(value);
                        if (comp.inputEl) {
                            comp.inputEl.setStyle('color', (record ? '#' + record.get('color') : ''))
                        }
                        if (!form.isRecordLoading) {
                            gridTeamMembers.getView().refresh();
                        }
                    }
                },
                listConfig: {
                    getInnerTpl: function(displayField) {
                        return '<span style="color:#{color}">{' + displayField + '}</span>';
                    }
                }
            }]
        },{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            title: 'Members',
            flex: 1,
            layout: 'fit',
            items: gridTeamMembers
        }],
        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            maxWidth: 860,
            hidden: readOnlyAccess,
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                itemId: 'save',
                xtype: 'button',
                text: 'Save',
                handler: function() {
                    var frm = form.getForm(),
                        team = frm.getValues(),
                        users = {};
                    if (frm.isValid()) {
                        storeTeamUsers.queryBy(function(user){
                            if (user.get('teamId') == team['id']) {
                                users[user.get('id')] = user.get('roles');
                            }
                        });
                        Scalr.Request({
                            url: '/account/teams/xSave',
                            processBox: {type: 'save'},
                            form: frm,
                            params: {
                                users: Ext.encode(users)
                            },
                            success: function (data) {
                                var record = frm.getRecord();
                                if (team['id'] != NEW_TEAM_ID) {
                                    record.set(data.team);
                                    form.loadRecord(record);

                                    if (Scalr.flags['authMode'] == 'ldap')
                                        Scalr.data.reload(['account.environments']);

                                } else {
                                    record = store.add(data.team)[0];
                                    dataview.getSelectionModel().select(record);
                                }
                            }
                        });
                    }
                }
            }, {
                itemId: 'cancel',
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    form.hide();
                    dataview.deselectAndClearLastSelected();
                }
            }, {
                itemId: 'delete',
                xtype: 'button',
                cls: 'x-btn-red',
                text: 'Delete',
                handler: function() {
                    var record = form.getForm().getRecord();
                    Scalr.Request({
                        confirmBox: {
                            msg: 'Delete team ' + record.get('name') + ' ?',
                            type: 'delete'
                        },
                        processBox: {
                            msg: 'Deleting...',
                            type: 'delete'
                        },
                        scope: this,
                        url: '/account/teams/xRemove',
                        params: {teamId: record.get('id')},
                        success: function (data) {
                            store.remove(record);

                            if (Scalr.flags['authMode'] == 'ldap')
                                Scalr.data.reload(['account.environments']);
                        }
                    });
                }
            }]
        }]
    });

    var panel = Ext.create('Ext.panel.Panel', {
        cls: 'scalr-ui-panel-account-teams',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        scalrOptions: {
            menuTitle: 'Teams',
            menuHref: '#/account/teams',
            menuFavorite: true,
            reload: false,
            maximize: 'all',
            leftMenu: {
                menuId: 'account',
                itemId: 'teams'
            },
            beforeClose: function() {
                menuUserRoles.hide();
                return true;
            }
        },
        stateId: 'grid-account-teams',
        listeners: {
            applyparams: reconfigurePage
        },
        items: [{
            cls: 'x-panel-column-left',
            width: 440,
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
                    itemId: 'teamsLiveSearch',
                    margin: 0,
                    width: 200,
                    filterFields: ['name'],
                    store: store
                },{
                    xtype: 'tbfill'
                },{
                    itemId: 'add',
                    text: 'New team',
                    cls: 'x-btn-green',
                    hidden: readOnlyAccess,
                    tooltip: 'New team',
                    enableToggle: true,
                    toggleHandler: function (button, state) {
                        if (state) {
                            dataview.deselectAndClearLastSelected();
                            form.loadRecord(storeTeams.createModel({id: NEW_TEAM_ID}));
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
                        Scalr.data.reload('account.*');
                        form.hide();
                    }
                }]
            }]
        },{
            xtype: 'container',
            flex: 1,
            layout: 'fit',
            minWidth: 500,
            items: form
        }]
    });
    return panel;
});

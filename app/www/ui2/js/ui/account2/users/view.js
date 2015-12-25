Scalr.regPage('Scalr.ui.account2.users.view', function (loadParams, moduleParams) {
    var storeTeams = Scalr.data.get('account.teams'),
        storeUsers = Scalr.data.get('account.users'),
        storeRoles = Scalr.data.get('account.acl'),
        isAccountOwner = Scalr.user['type'] == 'AccountOwner';

    var getUserTeamsList = function(userId, links) {
        var teams = [];
        if (userId) {
            storeTeams.each(function(record){
                var teamUsers = record.get('users');
                if (teamUsers) {
                    for (var i=0, len=teamUsers.length; i<len; i++) {
                        if (teamUsers[i].id == userId) {
                            var teamName = record.get('name');
                            teams.push(links ? '<a href="#/account/teams?teamId='+record.get('id')+'">'+teamName+'</a>' : teamName);
                            break;
                        }
                    }
                }
            });
        }
        return teams.join(', ');
    };

    var firstReconfigure = true;
    var reconfigurePage = function(params) {
        if (!firstReconfigure && params.userId) {
            var selModel = grid.getSelectionModel();
            selModel.deselectAll();
            if (params.userId == 'new') {
                panel.down('#add').toggle(true);
            } else {
                panel.down('#usersLiveSearch').reset();
                var record = store.getById(params.userId);
                if (record) {
                    selModel.select(record);
                }
            }
        }
        firstReconfigure = false;
    };

    var store = Ext.create('Ext.data.ChainedStore', {
        source: storeUsers,
        filterOnLoad: true,
        sortOnLoad: true
    });

    var storeUserTeams = Ext.create('store.store', {
        filterOnLoad: true,
        sortOnLoad: true,
        fields: [
            {name: 'id', type: 'string'}, 'name', 'roles', 'readonly', 'checked', 'account_role_id'
        ],
        loadUser: function(user) {
            var userId = user.get('id'),
                teams = [];
            this.removeAll();
            storeTeams.each(function(teamRecord){
                var team = {
                    id: teamRecord.get('id'),
                    name: teamRecord.get('name'),
                    checked: false,
                    roles: [],
                    readonly: false,
                    account_role_id: teamRecord.get('account_role_id')
                },
                teamUsers = teamRecord.get('users');
                if (teamUsers) {
                    for (var i=0, len=teamUsers.length; i<len; i++) {
                        if (teamUsers[i].id == userId) {
                            team.roles = teamUsers[i].roles;
                            team.checked = true;
                        }
                    }
                }
                teams.push(team);
            });
            this.loadData(teams);
        },
        listeners: {
            update: function() {
                var teams = [];
                this.getUnfiltered().each(function(record) {
                    if (record.get('checked')) {
                        teams.push('<a href="#/account/teams?teamId=' + record.get('id') + '">' + record.get('name') + '</a>');
                    }
                });
                form.down('#userTeams').setValue(teams.join(', ') + ' <a href="#" class="x-semibold user-teams-edit">Change</a>');
            }
        }
    });

    var menuUserRoles = Ext.create('Ext.menu.Menu', {
        cls: 'x-menu-light',
        setTeam: function(btnEl, teamRecord){
            var items = [],
                userRoles = teamRecord.get('roles') || [];
            storeRoles.getUnfiltered().each(function(role){
                var checked,
                    roleId = role.get('id');
                Ext.Array.each(userRoles, function(id){
                    return !(checked = roleId == id);
                });
                items.push({
                    xtype: 'menucheckitem',
                    text: '<span style="color:#'+role.get('color')+'">'+role.get('name')+'</span>',
                    value: roleId,
                    checked: checked
                });
            });
            this.teamRecord = teamRecord;
            this.removeAll();
            this.add(items);

            var xy = btnEl.getXY(), sizeX = xy[1] + btnEl.getHeight() + this.getHeight();
            if (sizeX > Scalr.application.getHeight()) {
                xy[1] -= sizeX - Scalr.application.getHeight();
            }
            this.show().setPosition([xy[0] - (this.getWidth() - btnEl.getWidth()), xy[1] + btnEl.getHeight() + 1]);
        },
        defaults: {
            listeners: {
                checkchange: function(menuitem, checked) {
                    var menu = menuitem.parentMenu,
                        scrollTop = form.body.el.getScroll().top,
                        ids = [];
                    menu.items.each(function(item){
                        if (item.checked) {
                            ids.push(item.value);
                        }
                    });
                    menu.teamRecord.set({
                        checked: true,
                        roles: ids
                    });
                    form.body.el.scrollTo('top', scrollTop);
                }
            }
        }
    });
    menuUserRoles.doAutoRender();

    var grid = Ext.create('Ext.grid.Panel', {
        cls: 'x-panel-column-left',
        flex: 1,
        selType: 'selectedmodel',
        store: store,
        stateId: 'grid-account-users-view',
        plugins: ['focusedrowpointer', {ptype: 'selectedrecord', disableSelection: false, clearOnRefresh: true}],
        listeners: {
            viewready: function() {
                reconfigurePage(loadParams);
            },
            selectionchange: function(selModel, selected) {
                this.down('#delete').setDisabled(!selected.length);
                this.down('#activate').setDisabled(!selected.length);
                this.down('#deactivate').setDisabled(!selected.length);
            }
        },
        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            emptyText: '<div class="x-semibold">No users were found to match your search.</div>Try modifying your search criteria or creating a new user',
            loadingText: 'Loading users ...',
            deferEmptyText: false
        },

        columns: [
            {width: 40, dataIndex: 'type', sortable: false, resizable: false,
                xtype: 'templatecolumn',
                tpl:
                    '<tpl if="type==\'AccountOwner\'">'+
                        '<img title="Account owner" src="/ui2/images/ui/account/owner.png" />' +
                    '<tpl elseif="type==\'AccountAdmin\'">' +
                        '<img title="Account admin" src="/ui2/images/ui/account/admin.png" />'+
                    '<tpl elseif="type==\'AccountSuperAdmin\'">' +
                        '<img title="Account admin with access to manage environments" src="/ui2/images/ui/account/super-admin.png" />'+
                    '</tpl>'
            },
            {text: 'User', flex: 1, dataIndex: 'fullname', sortable: true},
            {
                text: Scalr.flags['authMode'] == 'ldap' ? 'LDAP login' : 'Email',
                flex: 1,
                dataIndex: 'email',
                sortable: true
            },
            {text: 'Teams', flex: 1, dataIndex: 'id', sortable: false, xtype: 'templatecolumn', hidden: (Scalr.flags['authMode'] == 'ldap'), tpl:
                new Ext.XTemplate(
                '{[this.getUserTeamsList(values.id)]}',
                {
                    getUserTeamsList: function(userId){
                        return getUserTeamsList(userId);
                    }
                }
            )},
            { text: '2FA',  width: 70, align: 'center', dataIndex: 'is2FaEnabled', sortable: true, xtype: 'templatecolumn',
                tpl: '<tpl if="is2FaEnabled"><div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div><tpl else>&mdash;</tpl>'
            },
            { text: 'Last login',  width: 150, dataIndex: 'dtlastlogin', sortable: true, xtype: 'templatecolumn', tpl: '{dtlastloginhr}' },
            { text: 'Status', width: 90, minWidth: 90, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'user', qtipConfig: {width: 280}}

        ],
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12',
                handler: function() {
                    var action = this.getItemId(),
                        actionMessages = {
                            'delete': ['Delete selected user(s): %s ?', 'Deleting selected users(s) ...'],
                            activate: ['Activate selected user(s): %s ?', 'Activating selected users(s) ...'],
                            deactivate: ['Deactivate selected user(s): %s ?', 'Deactivating selected users(s) ...']
                        },
                        selModel = grid.getSelectionModel(),
                        ids = [],
                        emails = [],
                        request = {};
                    for (var i=0, records = selModel.getSelection(), len=records.length; i<len; i++) {
                        ids.push(records[i].get('id'));
                        emails.push(records[i].get('email'));
                    }

                    request = {
                        confirmBox: {
                            msg: actionMessages[action][0],
                            type: action,
                            objects: emails
                        },
                        processBox: {
                            msg: actionMessages[action][1],
                            type: action
                        },
                        params: {ids: ids, action: action},
                        success: function (data) {
                            if (data.processed && data.processed.length) {
                                switch (action) {
                                    case 'activate':
                                    case 'deactivate':
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            var record = store.getById(data.processed[i]);
                                            record.set('status', action=='deactivate'?'Inactive':'Active');
                                            selModel.deselect(record);
                                        }
                                    break;
                                    case 'delete':
                                        var recordsToDelete = [];
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            recordsToDelete.push(store.getById(data.processed[i]));
                                            selModel.deselect(recordsToDelete[i]);
                                        }
                                        store.remove(recordsToDelete);
                                        grid.view.refresh();
                                    break;
                                }
                            }
                        }
                    };
                    request.url = '/account/users/xGroupActionHandler';
                    request.params.ids = Ext.encode(ids);

                    Scalr.Request(request);
                }
            },
            items: [{
                xtype: 'filterfield',
                itemId: 'usersLiveSearch',
                margin: 0,
                width: 200,
                filterFields: ['fullname', 'email'],
                handler: null,
                store: store
            },{
                xtype: 'tbfill'
            },{
                itemId: 'add',
                text: 'New user',
                cls: 'x-btn-green',
                hidden: Scalr.flags['authMode'] == 'ldap',
                enableToggle: true,
                handler: Ext.emptyFn,
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();
                        form.loadRecord(storeUsers.createModel({id: 0, status: 'Active', password: false}));
                        form.down('[name=fullname]').focus();

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
                    grid.down('#add').toggle(false, true);
                    form.hide();
                }
            },{
                itemId: 'activate',
                iconCls: 'x-btn-icon-activate',
                disabled: true,
                tooltip: 'Activate selected users'
            },{
                itemId: 'deactivate',
                iconCls: 'x-btn-icon-suspend',
                disabled: true,
                tooltip: 'Deactivate selected users'
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Delete selected users'
            }]
        }]
    });

    var form = Ext.create('Ext.form.Panel', {
        hidden: true,
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 135
        },
        autoScroll: true,
        teamsGridCollapsed: false,
        listeners: {
            hide: function () {
                grid.down('#add').toggle(false, true);
            },
            loadrecord: function(record) {
                var form = this.getForm(),
                    isNewRecord = !record.store,
                    userType = record.get('type'),
                    gridTeams = this.down('#userTeamsGrid'),
                    passwordField = this.down('#password');

                var c = this.query('component[cls~=hideoncreate], #delete');
                for (var i=0, len=c.length; i<len; i++) {
                    c[i].setVisible(!isNewRecord);
                }
                this.down('#formtitle').setText(!isNewRecord?record.get(!Ext.isEmpty(record.get('fullname'))?'fullname':'email'):'New user', false);
                grid.down('#add').toggle(isNewRecord, true);

                gridTeams.setVisible(Scalr.flags['authMode'] != 'ldap');
                if (Scalr.flags['authMode'] == 'scalr') {
                    if (isNewRecord || !this.teamsGridCollapsed) {
                        this.on('show', function(){
                            gridTeams.expand();
                        }, gridTeams, {single: true});
                    } else {
                        gridTeams.collapse();
                    }
                } else if (Scalr.flags['authMode'] == 'ldap') {
                    if (isNewRecord)
                        this.down('#userTeams').hide();
                    else
                        this.down('#userTeams').show();
                }

                this.down('[name="status"]').setReadOnly(Scalr.user.userId == record.get('id'));
                this.down('#isAccountAdmin').setVisible(userType != 'AccountOwner');
                form.findField('isAccountAdmin').setValue(userType == 'AccountAdmin' || userType == 'AccountSuperAdmin' ? '1' : '0');
                form.findField('isAccountSuperAdmin').setValue(userType == 'AccountSuperAdmin');
                this.down('#save').setText(isNewRecord ? 'Add user' : 'Save');

                if (record.get('id') == Scalr.user.userId) {
                    if (passwordField.emptyText && passwordField.emptyText != ' ') {
                        passwordField._emptyText = passwordField.emptyText;
                        passwordField.emptyText = ' ';
                    }
                    passwordField.allowBlank = false;
                } else {
                    if (passwordField._emptyText) {
                        passwordField.emptyText = passwordField._emptyText;
                        delete passwordField._emptyText;
                    }
                    passwordField.allowBlank = true;
                }
                passwordField.applyEmptyText();

                this.down('#userTeams').setValue(getUserTeamsList(record.get('id'), true)+ (Scalr.flags['authMode'] == 'scalr' ? ' <a href="#" class="user-teams-edit">Change</a>' : ''));
                storeUserTeams.loadUser(record);

                this.down('#avatar').setSrc();
                if (record.get('gravatarhash')) {
                    this.down('#avatar').setSrc(Scalr.utils.getGravatarUrl(record.get('gravatarhash'), 'large'));
                }
            },
            resetrecord: function(record) {
                var isNewRecord = record ? !record.store : true,
                    gridTeams = this.down('#userTeamsGrid');
                if (!isNewRecord || gridTeams.collapsed) {
                    this.teamsGridCollapsed = gridTeams.collapsed;
                }
            }
        },
        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            items: [{
                itemId: 'formtitle',
                xtype: 'label',
                cls: 'x-fieldset-subheader x-form-title',
                text: '&nbsp;'
            },{
                xtype: 'image',
                cls: 'hideoncreate x-gravatar',
                itemId: 'avatar'
            },{
                xtype: 'displayfield',
                cls: 'hideoncreate',
                fieldLabel: 'ID',
                name: 'id',
                submitValue: true
            },{
                xtype: 'container',
                itemId: 'isAccountAdmin',
                cls: 'scalr-ui-users-account-admin',
                layout: 'hbox',
                margin: '0 0 18',
                items: [{
                    xtype: 'buttongroupfield',
                    name: 'isAccountAdmin',
                    margin: '0 18 0 0',
                    defaults: {
                        width: 50
                    },
                    items: [{
                        text: 'On',
                        value: '1'
                    },{
                        text: 'Off',
                        value: '0'
                    }],
                    listeners: {
                        change: function(comp, value) {
                            var featuresField = comp.up().down('[name="isAccountSuperAdmin"]'),
                                isAccountSuperAdminEnabled = value == '1' && isAccountOwner;
                            featuresField.setVisible(isAccountSuperAdminEnabled).setDisabled(!isAccountSuperAdminEnabled);
                        }
                    }
                },{
                    xtype: 'container',
                    flex: 1,
                    items: [{
                        xtype: 'component',
                        style: 'color:#C00000',
                        html: '<span class="x-bold">Admin access to user management</span><br/>Allow this user to create, modify and remove all account users, teams and ACL\'s.'
                    },{
                        xtype: 'checkbox',
                        name: 'isAccountSuperAdmin',
                        boxLabel: 'Allow to manage environments',
                        style: 'color:#C00000',
                        hidden: true,
                        inputValue: 1,
                        margin: 0
                    }]
                }]
                //todo: ?hidden: Scalr.flags['authMode'] == 'ldap'
            },{
                xtype: 'textfield',
                name: 'fullname',
                fieldLabel: 'Full name'
            },{
                xtype: 'textfield',
                name: 'email',
                fieldLabel: Scalr.flags['authMode'] == 'ldap' ? 'LDAP login' : 'Email',
                allowBlank: false,
                readOnly: Scalr.flags['authMode'] == 'ldap',
                vtype: Scalr.flags['authMode'] == 'ldap' ? '' : 'email'
            }, {
                xtype: 'scalrpasswordfield',
                name: 'password',
                itemId: 'password',
                fieldLabel: 'Password',
                hidden: Scalr.flags['authMode'] == 'ldap',
                emptyText: 'Leave blank to let user specify password',
                vtype: 'password',
                otherPassField: 'cpassword',
                allowBlank: true,
                selectOnFocus: true,
                listeners: {
                    change: function(comp, value) {
                        var hideConfirm = !value || value === '******';
                        if (Scalr.flags['authMode'] !== 'ldap') {
                            comp.next('#cpassword').setVisible(!hideConfirm).setDisabled(hideConfirm)
                        }
                    }
                }
            }, {
                xtype: 'textfield',
                name: 'cpassword',
                itemId: 'cpassword',
                inputType: 'password',
                fieldLabel: 'Password confirm',
                hidden: true,
                disabled: true,
                allowBlank: false,
                selectOnFocus: true,
                submitValue: false,
                vtype: 'password',
                otherPassField: 'password'
            },{
                xtype: 'displayfield',
                itemId: 'userTeams',
                fieldLabel: 'Permissions',
                cls: 'user-teams expanded',
                listeners: {
                    afterrender: {
                        fn: function(){
                            var me = this;
                            this.mon(this.el, 'click', function(e) {
                                var el = me.el.query('a.user-teams-edit');
                                if (el.length && e.within(el[0])) {
                                    form.down('grid').toggleCollapse();
                                    e.preventDefault();
                                }
                            })
                        }, opt: {single: true}
                    }
                }
            },{
                xtype: 'grid',
                cls: 'x-panel-collapsible-mini x-grid-no-selection',
                collapsible: true,
                collapsed: false,
                collapseMode: 'mini',
                animCollapse: false,
                header: false,
                disableSelection: true,
                itemId: 'userTeamsGrid',
                store: storeUserTeams,
                margin: '0 0 12',
                viewConfig: {
                    focusedItemCls: '',
                    plugins: {
                        ptype: 'dynemptytext',
                        emptyText: 'No teams were found.'+ (!isAccountOwner ? '' : ' Click <a href="#/account/teams?teamId=new">here</a> to create new team.')
                    },
                    listeners: {
                        itemclick: function (view, record, item, index, e) {
                            if (record.get('readonly')) return;
                            if (e.getTarget('input.team-member')) {
                                var scrollTop = form.body.el.getScroll().top;
                                record.set({
                                    checked: !record.get('checked'),
                                    roles: []
                                });
                                form.body.el.scrollTo('top', scrollTop);
                            } else if (e.getTarget('.x-grid-row-options')) {
                                var btnEl = Ext.get(item).down('div.x-grid-row-options');
                                menuUserRoles.setTeam(btnEl, record);
                            }
                        }
                    }
                },
                columns: [{
                    text: 'In team',
                    width: 80,
                    xtype: 'templatecolumn',
                    resizable: false,
                    sortable: false,
                    tpl: '<div class="<tpl if="checked">x-form-cb-checked</tpl><tpl if="readonly"> x-item-disabled</tpl>" style="text-align: center">' +
                             '<input type="button" class="x-form-field x-form-checkbox x-form-checkbox-default x-form-cb x-form-cb-default team-member" style="margin:4px 0 0;position:relative" />' +
                         '</div>'
                }, {
                    text: 'Name',
                    flex: 1,
                    resizable: false,
                    sortable: false,
                    dataIndex: 'name'
                }, {
                    text: 'ACL',
                    flex: 2,
                    resizable: false,
                    sortable: false,
                    xtype: 'templatecolumn',
                    tpl: new Ext.XTemplate(
                        '<tpl if="!readonly">',
                            '<div class="x-grid-row-options"><div class="x-grid-row-options-icon"></div></div>',
                        '</tpl>',
                        '<div data-qclass="x-tip-light" data-qtip="{[Ext.htmlEncode(this.getRolesList(values))]}" style="text-overflow:ellipsis;overflow:hidden">{[this.getRolesList(values)]}&nbsp;</div>',
                    {
                        getRolesList: function(team){
                            var html = [];
                            if (team.roles) {
                                for (var i=0, len=team.roles.length; i<len; i++) {
                                    var role = storeRoles.getById(team.roles[i]);
                                    if (role) {
                                        html.push('<a href="#/account/acl?roleId=' + role.get('id') + '" class="x-semibold user-permission" style="color:#' + role.get('color') + '">' + role.get('name') + '</a>');
                                    }
                                }
                            }
                            if (html.length === 0) {
                                if (team.account_role_id) {
                                    var defaultRoleRecord = storeRoles.getById(team.account_role_id);
                                    if (defaultRoleRecord) {
                                        html.push('<span class="x-semibold" style="color:#'+defaultRoleRecord.get('color')+'">' + defaultRoleRecord.get('name')+'</span> (team\'s default ACL)');
                                    }
                                }
                            }
                            return html.join(', ');
                        }
                    })
                }],
                listeners: {
                    viewready: function(){
                        var refreshUserTeams = function(){
                            var record = form.getRecord();
                            if (record) {
                                storeUserTeams.loadUser(record);
                            }
                        }

                        storeRoles.on({
                            add: refreshUserTeams,
                            remove: refreshUserTeams,
                            update: refreshUserTeams,
                            refresh: refreshUserTeams
                        });
                        storeTeams.on({
                            add: refreshUserTeams,
                            remove: refreshUserTeams,
                            update: refreshUserTeams,
                            refresh: refreshUserTeams
                        });

                    },
                    expand: function(){
                        form.down('#userTeams').addCls('expanded');
                    },
                    collapse: function(){
                        form.down('#userTeams').removeCls('expanded');
                    }
                },
                dockedItems: [{
                    xtype: 'toolbar',
                    ui: 'inline',
                    dock: 'top',
                    overlay: true,
                    layout: {
                        type: 'hbox',
                        pack: 'end'
                    },
                    margin: 0,
                    padding: '6 8 6 0',
                    style: 'z-index:2',
                    items: {
                        //style: 'background:transparent',
                        ui: 'other',
                        iconCls: 'x-tool-img x-tool-close',
                        tooltip: 'Close',
                        handler: function() {
                            form.down('grid').collapse();
                        }
                    }
                }]


            },{
                xtype: 'displayfield',
                cls: 'hideoncreate',
                name: 'dtcreated',
                fieldLabel: 'User added'
            },{
                xtype: 'displayfield',
                cls: 'hideoncreate',
                name: 'dtlastlogin',
                fieldLabel: 'Last login'
            },{
                xtype: 'buttongroupfield',
                fieldLabel: 'Status',
                name: 'status',
                value: 'Active',
                plugins: {
                    ptype: 'fieldicons',
                    position: 'outer',
                    icons: [
                        {id: 'question', tooltip: 'You are not allowed to change your own status'}
                    ]
                },
                listeners: {
                    writeablechange: function(comp, readOnly) {
                        this.toggleIcon('question', readOnly);
                    }
                },
                defaults: {
                    width: 100
                },
                items: [{
                    text: 'Active',
                    value: 'Active'
                }, {
                    text: 'Suspended',
                    value: 'Inactive'
                }]
            }, {
                xtype: 'textarea',
                name: 'comments',
                fieldLabel: 'Comments',
                labelAlign: 'top',
                grow: true,
                growMax: 400,
                anchor: '100%'
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
            defaults:{
                flex: 1,
                maxWidth: 150
            },
            items: [{
                itemId: 'save',
                xtype: 'button',
                text: 'Save',
                handler: function () {
                    var frm = form.getForm(),
                        record = frm.getRecord(),
                        isNewRecord = !record.store,
                        confirmBox;
                    sendRequest = function(currentPassword) {
                        var teams = {};
                        storeUserTeams.getUnfiltered().each(function(record){
                            if (record.get('checked')) {
                                teams[record.get('id')] = record.get('roles');
                            }
                        });
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/account/users/xSave',
                            form: frm,
                            params: {
                                teams: Ext.encode(teams),
                                currentPassword: currentPassword
                            },
                            success: function (data) {
                                if (confirmBox) {
                                    confirmBox.close();
                                }
                                if (data.specialToken) {
                                    Scalr.utils.saveSpecialToken(data.specialToken);
                                }

                                if (isNewRecord) {
                                    record = store.add(data.user)[0];
                                } else {
                                    record.set(data.user);
                                }
                                storeTeams.suspendEvents();
                                storeTeams.each(function(teamRecord){
                                    var teamUsers = teamRecord.get('users'),
                                        newTeamUsers = [],
                                        teamId = teamRecord.get('id'),
                                        userId = record.get('id');
                                    if (teamUsers) {
                                        for (var i=0, len=teamUsers.length; i<len; i++) {
                                            if (teamUsers[i].id != userId) {
                                                newTeamUsers.push(teamUsers[i]);
                                            }
                                        }
                                    }
                                    if (data.teams && data.teams[teamId]) {
                                        newTeamUsers.push({
                                            id: userId,
                                            roles: data.teams[teamId].roles
                                        });
                                    }
                                    teamRecord.set('users', newTeamUsers);
                                });
                                storeTeams.resumeEvents();
                                Scalr.data.fireRefresh(['account.users', 'account.teams']);
                                grid.setSelectedRecord(record);
                            },
                            failure: function(data) {
                                if (confirmBox) {
                                    confirmBox.onFailure(data.errors);
                                }
                            }
                        });
                    };
                    if (frm.isValid()) {
                        var passwordField = frm.findField('password');
                        if (!isNewRecord && passwordField.getValue() != '******') {
                            confirmBox = Scalr.utils.ConfirmPassword(sendRequest);
                        } else {
                            sendRequest();
                        }
                    }

                }
            }, {
                itemId: 'cancel',
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    grid.clearSelectedRecord();
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
                            msg: 'Delete user ' + record.get('email') + ' ?',
                            type: 'delete'
                        },
                        processBox: {
                            msg: 'Deleting...',
                            type: 'delete'
                        },
                        scope: this,
                        url: '/account/users/xRemove',
                        params: {userId: record.get('id')},
                        success: function (data) {
                            store.remove(record);
                            grid.view.refresh();
                        }
                    });
                }
            }]
        }]
    });


    var panel = Ext.create('Ext.panel.Panel', {
        cls: 'scalr-ui-panel-account-users',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        scalrOptions: {
            menuTitle: 'Users',
            menuHref: '#/account/users',
            menuFavorite: true,
            reload: false,
            maximize: 'all',
            leftMenu: {
                menuId: 'account',
                itemId: 'users'
            }
        },
        stateId: 'grid-account-users',
        listeners: {
            applyparams: reconfigurePage
        },
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            flex: .6,
            maxWidth: 560,
            minWidth: 400,
            layout: 'fit',
            items: [
                form
            ]
        }]
    });
    return panel;
});

Ext.define('Scalr.ui.RolesLibrary', {
	extend: 'Ext.container.Container',
	alias: 'widget.roleslibrary',

    cls: 'x-roleslibrary',
    padding: 0,
    vpc: null,
    mode: null, //shared|custom
    layout: 'fit',
    initComponent: function() {
        this.callParent(arguments);
        this.on({
            activate: function() {
                var newVpcId, oldVpcId, leftcol, defaultCatId = 'shared';
                oldVpcId = this.vpc ? this.vpc.id : false;

                this.vpc = this.up('#farmDesigner').getVpcSettings();

                newVpcId = this.vpc ? this.vpc.id : false;
                if (this.roleId) {
                    this.down('#leftcol').createSearch(this.roleId);
                    delete this.roleId;
                } else if (!this.mode) {
                    this.getComponent('tabspanel').getDockedComponent('tabs').down('[catId="'+defaultCatId+'"]').toggle(true);
                } else if (newVpcId !== oldVpcId) {
                    leftcol = this.down('#leftcol');
                    leftcol.deselectCurrent();
                    leftcol.refreshStoreFilter();
                }

            }
        });
    },

    items: {
        xtype: 'panel',
        flex: 1,
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        itemId: 'tabspanel',
        dockedItems: [{
            xtype: 'container',
            itemId: 'tabs',
            dock: 'left',
            cls: 'x-docked-tabs x-docked-tabs-light',
            width: 190,
            autoScroll: true,
            listeners: {
                afterrender: function(){
                    var me = this, index = 0;

                    me.suspendLayouts();
                    me.addCategoryBtn({
                        name: 'Quick start',
                        catId: 'shared',
                        cls: 'x-btn-tab-quickstart'
                    });
                    Ext.Object.each(me.up('#farmDesigner').moduleParams.categories, function(key, value) {
                        me.addCategoryBtn({
                            name: value.name,
                            catId: value.id,
                            total: value.total,
                            cls: index ? '' : 'x-btn-tab-first'
                        });
                        index++;
                    });
                    me.resumeLayouts(true);
                },
                add: function(panel, btn) {
                    if (btn.catId === 'search') {
                        var buttons = panel.query('[catId="search"]');
                        if (buttons.length > 3) {
                            Scalr.CachedRequestManager.get('farmDesigner').removeCache({
                                url: '/roles/xGetList',
                                params: {catId: 'search', keyword: buttons[0].keyword}
                            })
                            panel.remove(buttons[0]);
                        }
                    }
                },
                remove: function(panel, btn) {
                    if (btn.pressed) {
                        var last = panel.items.last();
                        if (last) {
                            last.toggle(true);
                        }
                    }
                }
            },
            addCategoryBtn: function(data) {
                var btn = {
                    xtype: 'button',
                    ui: 'tab',
                    textAlign: 'left',
                    allowDepress: false,
                    disableMouseDownPressed: true,
                    text: data.name  + (data.total == 0 ? '<span class="superscript">empty</span>' : ''),
                    cls: (data.total == 0 ? 'x-btn-tab-deprecated ' : '') + (data.cls || ''),
                    catId: data.catId,
                    keyword: data.keyword,
                    roleId: data.roleId,
                    toggleGroup: 'roleslibrary-tabs',
                    toggleHandler: this.toggleHandler,
                    scope: this,
                    style: data.style
                };
                //button remove search
                if (data.catId === 'search') {
                    btn.listeners = {
                        afterrender: function(){
                            var me = this;
                            this.btnEl.on({
                                mouseenter: function(){
                                    me.btnDeleteEl = Ext.DomHelper.append(me.btnEl.dom, '<div class="delete-search" title="Remove search"></div>', true)
                                    me.btnDeleteEl.on('click', function(){
                                        Scalr.CachedRequestManager.get('farmDesigner').removeCache({
                                            url: '/roles/xGetList',
                                            params: {catId: 'search', keyword: me.keyword}
                                        })
                                        me.ownerCt.remove(me);
                                    });
                                },
                                mouseleave: function(){
                                    if (me.btnDeleteEl) {
                                        me.btnDeleteEl.remove();
                                        delete me.btnDeleteEl;
                                    }
                                }
                            });
                        }
                    };

                }
                return this.add(btn);
            },
            toggleHandler: function(btn, pressed) {
                if (pressed) {
                    this.ownerCt.getComponent('leftcol').selectCategory(btn.catId, btn.keyword, btn.roleId);
                }
            }
        }],
        items: [{
            itemId: 'leftcol',
            cls: 'x-panel-column-left x-panel-column-left-with-tabs',
            layout: 'card',
            items:[{
                xtype: 'component',
                itemId: 'blank'
            },{
                xtype: 'component',
                itemId: 'emptytext',
                cls: 'emptytext',
                listeners: {
                    boxready: function() {
                        var me = this;
                        this.on({
                            click: {
                                fn: function(e, el) {
                                    var links = this.query('a.add-link');
                                    if (links.length) {
                                        for (var i=0, len=links.length; i<len; i++) {
                                            if (e.within(links[i])) {
                                                switch (links[i].getAttribute('data-action')) {
                                                    case 'server-search':
                                                        me.up('#leftcol').createSearch();
                                                    break;
                                                }
                                                break;
                                            }
                                        }
                                        e.preventDefault();
                                    }
                                },
                                element: 'el'
                            }
                        });
                    }
                }
            }],
            listeners: {
                boxready: function() {
                    this.fillPlatformFilter();
                }
            },
            createSearch: function(roleId) {
                var tabs = tabs = this.up('#tabspanel').getDockedComponent('tabs');
                if (roleId) {
                    var res = tabs.query('[roleId="'+roleId+'"]');
                    if (res.length === 0) {
                        (tabs.addCategoryBtn({
                            name: 'Role ID: <span class="x-semibold"> ' + roleId + '</span>',
                            catId: 'search',
                            roleId: roleId,
                            cls: 'x-roleslibrary-search-result-btn'
                        })).toggle(true);
                    } else {
                        res[0].toggle(true);
                    }
                } else {
                    var searchField = this.down('#search'),
                        keyword = Ext.String.trim(searchField.getValue() || '');
                    if (!searchField.isValid()) return;
                    if (keyword) {
                        var res = tabs.query('[keyword="'+keyword+'"]');
                        if (res.length === 0) {
                            (tabs.addCategoryBtn({
                                name: 'Search "<span class="x-semibold" title="' + Ext.htmlEncode(keyword) + '" style="text-transform:none">' + Ext.htmlEncode(Ext.String.ellipsis(keyword, 12)) + '</span>"',
                                catId: 'search',
                                keyword: keyword,
                                cls: 'x-roleslibrary-search-result-btn'
                            })).toggle(true);
                        } else {
                            res[0].toggle(true);
                            searchField.setValue(null);
                        }
                    }
                }
            },
            selectCategory: function(catId, keyword, roleId){
                var me = this,
                    mode = catId === 'shared' ? 'shared' : 'custom',
                    searchField = me.down('#search');
                this.catId = catId;
                this.deselectCurrent();
                me.up('roleslibrary').mode = mode;
                if (mode === 'shared') {
                    me.loadRoles({catId: 'shared'}, function(data, status){
                        var view = this.down('#sharedroles');
                        if (!view) {
                            //create fake Chef category - copy of base
                            if (data && status === 'success') {
                                Ext.Array.each(data['software'], function(item){
                                    if (item.name === 'base') {
                                        data['software'].push({
                                            name: 'chef',
                                            ordering: item.ordering + 1,
                                            roles: item.roles
                                        });
                                    }
                                });
                            }
                            view = me.add(me.sharedRolesViewConfig);
                            view.tpl.ownerView = view;
                            view.store.loadData(data ? data['software'] : []);
                        }
                        me.fillOsFilter(view.store);
                        me.refreshStoreFilter();
                        if (view.store.getCount() > 0) {
                            me.getLayout().setActiveItem(view);
                        }
                    });
                } else {
                    var params = {catId: catId};
                    if (catId === 'search') {
                        params.keyword = keyword;
                        params.roleId = roleId;
                    }
                    me.loadRoles(params, function(data, status){
                        var view = this.down('#customroles');
                        if (!view) {
                            view = me.add(me.customRolesViewConfig);
                        }
                        view.store.clearFilter(true);
                        view.store.loadData(data ? data['roles'] : []);
                        me.fillOsFilter(view.store);
                        if (catId === 'search') {
                            searchField.setValue(null);
                        }
                        me.refreshStoreFilter();
                        if (view.store.getCount() > 0) {
                            me.getLayout().setActiveItem(view);
                            if (roleId) {
                                view.getSelectionModel().select(0);
                            }
                            searchField.focus();
                        }
                    });
                }
            },
            deselectCurrent: function() {
                var roleslibrary = this.up('roleslibrary'),
                    view = this.down(roleslibrary.mode === 'shared' ? '#sharedroles' : '#customroles');
                if (view) {
                    view.getSelectionModel().deselectAll();
                }
            },

            loadRoles: function(params, cb) {
                Scalr.CachedRequestManager.get('farmDesigner').load(
                    {
                        url: '/roles/xGetList',
                        params: params
                    },
                    cb,
                    this,
                    0
                );
            },

            getFilterValue: function(name) {
                var filter = this.down('#filters').down('#' + name),
                    value = filter ? filter.getValue() : null;
                value = name === 'search' ? Ext.String.trim(value) : value;
                return value;
            },

            refreshStoreFilter: function() {
                var rl = this.up('roleslibrary'),
                    vpc = rl.vpc,
                    mode = rl.mode,
                    view = this.down(mode === 'shared' ? '#sharedroles' : '#customroles'),
                    store;
                this.deselectCurrent();
                if (view) {
                    store = view.store;
                    store.clearFilter(true);
                    store.filter([{filterFn: Ext.Function.bind(this.storeFilterFn, this, [
                        mode,
                        this.getFilterValue('platform'),
                        this.getFilterValue('os'),
                        this.getFilterValue('search'),
                        vpc !== false ? vpc.region : null
                    ], true)}]);
                    this.refreshEmptyText(view);
                }
            },

            refreshEmptyText: function(view) {
                var emptyText = this.getComponent('emptytext');

                if (view.getStore().getCount() === 0) {
                    var fb = this.up('#farmDesigner'),
                        filterCategory = this.catId,
                        filterPlatform = this.getFilterValue('platform'),
                        category,
                        platform = Scalr.platforms[filterPlatform] ? Scalr.platforms[filterPlatform].name : filterPlatform,
                        filterOs = this.getFilterValue('os'),
                        text;
                    if (filterCategory === 'shared') {
                        category = 'Quick start';
                    } else if (fb.moduleParams.categories[filterCategory]) {
                        category = fb.moduleParams.categories[filterCategory].name;
                    }

                    if (!Ext.isEmpty(this.getFilterValue('search'))) {
                        text = '<div class="title">No roles were found to match your search' + (category ? ' in category <span style="white-space:nowrap">&#8220;' + category + '&#8221;</span>' : '') + '.</div>' +
                               '<a class="add-link" data-action="server-search" href="#">Click here</a> to search across all categories.';
                    } else {
                        if (filterCategory === 'search'){
                            text = '<div class="title">No roles were found to match your search.</div>';
                        } else  if (filterPlatform || filterOs) {
                            text = '<div class="title">' + (category ? '<span style="white-space:nowrap;color:#8a919e">' + category + '</span>' : '') + ' roles ';
                            if (filterOs) {
                                text += ' with <span style="color:#8a919e">' + Scalr.utils.beautifyOsFamily(filterOs) + '</span>';
                            }
                            text += ' are not available'
                            if (filterPlatform) {
                                text += ' for <span style="color:#8a919e;white-space:nowrap">' + platform + '</span>&nbsp;cloud';
                            }
                            text += '.</div>';
                        } else {
                            text = '<div class="title">No roles were found in the selected category.</div>';
                        }

                        text = this.addEmptyTextExtraButtons(text, filterPlatform);
                    }

                    emptyText.getEl().setHtml(text);
                    this.getLayout().setActiveItem(emptyText);
                } else {
                    this.getLayout().setActiveItem(view);
                }
            },

            addEmptyTextExtraButtons: function(text, filterPlatform) {
                var str1 = 'You will have to',
                    str2 = '';
                if (this.isRoleBuilderIconVisible(filterPlatform)) {
                    str1 += ' create new or';
                    str2 += '<div class="x-items-extra"><a class="x-item-extra" href="#/roles/builder' + (filterPlatform ? '?platform=' + filterPlatform : '' ) + '">'+
                            '<span class="x-item-inner">'+
                                '<span class="icon x-icon-behavior-large x-icon-behavior-large-mixed"></span>'+
                                '<span class="title">Create role</span>'+
                            '</span>'+
                            '</a><span class="title x-item-extra-delimiter">or</span>';
                } else {
                    str2 += '<div class="x-items-extra single">';
                }
                str1 += ' build one from an existing server.';
                str2 += '<a class="x-item-extra" href="#/roles/import' + (filterPlatform ? '?platform=' + filterPlatform : '' ) + '">'+
                        '<span class="x-item-inner">'+
                            '<span class="icon x-icon-behavior-large x-icon-behavior-large-wizard"></span>'+
                            '<span class="title">Build from server</span>'+
                        '</span>'+
                        '</a>';
                str2 += '</div>';
                text += '<div style="margin-bottom:12px">' + str1 + '</div>' + str2;
                return text;
            },

            isRoleBuilderIconVisible: function(platform) {
                var result = false;
                if (platform) {
                    result = Ext.Array.contains(['ec2', 'gce', 'rackspacengus', 'rackspacenguk'], platform);
                } else {
                    Ext.Array.each(['ec2', 'gce', 'rackspacengus', 'rackspacenguk'], function(platform){
                        if (Scalr.platforms[platform] !== undefined && Scalr.platforms[platform]['enabled']) {
                            result = true;
                            return false;
                        }
                    });
                }
                return result;
            },


            storeFilterFn: function(record, mode, platform, os, keyword, vpcRegion) {
                var res = false, roles, images;
                if (mode === 'shared') {
                    if (!vpcRegion && record.get('name') === 'vpcrouter') {
                        return false;
                    }
                    if (os || platform || keyword || vpcRegion) {
                        roles = record.get('roles');
                        for (var i=0, len=roles.length; i<len; i++) {
                            if ((!os || (Scalr.utils.getOsById(roles[i].osId, 'family') || 'unknown') === os) && (!keyword || (roles[i].name+'').match(RegExp(Ext.String.escapeRegex(keyword), 'i')))) {
                                images = roles[i].images;
                                if (platform) {
                                    Ext.Object.each(images, function(key) {
                                        if (key === platform) {
                                            res = true;
                                            return false;
                                        }
                                    });
                                } else {
                                    res = true;
                                }
                            }
                            if (res) break;
                        }
                    } else {
                        res = true;
                    }
                    return res;
                } else {
                    if (!vpcRegion && Ext.Array.contains(record.get('behaviors'), 'router')) {
                        return false;
                    }
                    if (os || platform || keyword || vpcRegion) {
                        if ((!os || (Scalr.utils.getOsById(record.get('osId'), 'family') || 'unknown')  === os ) && (!keyword || (record.get('name')+'').match(RegExp(Ext.String.escapeRegex(keyword), 'i')))) {
                            images = record.get('images');
                            if (platform) {
                                res = images[platform] !== undefined;
                            } else {
                                res = true;
                            }
                        }
                    } else {
                        return true;
                    }
                    return res;
                }

            },

            sharedRolesViewConfig: {
                xtype: 'dataview',
                itemId: 'sharedroles',
                cls: 'x-dataview-boxes',
                itemSelector: '.x-item',
                overItemCls : 'x-item-over',
                padding: '0 0 0 12',
                trackOver: true,
                overflowY: 'scroll',
                margin: '0 -' + Ext.getScrollbarSize().width+ ' 0 0',
                store: {
                    fields: ['name', 'roles', {name: 'ordering', type: 'int'}, 'description'],
                    proxy: 'object',
                    sortOnLoad: true,
                    sortOnFilter: true,
                    sorters: [{
                        property: 'ordering'
                    }]
                },
                tpl  : new Ext.XTemplate(
                    '<tpl for=".">',
                        '<div {[this.isItemDisabled(values) ? \'class="x-item x-item-disabled" data-qtip="Category doesn\\\'t contain images in current VPC region"\' : \'class="x-item"\']}>',
                            '<div class="x-item-inner">',
                                '<div class="x-icon x-icon-behavior-large x-icon-behavior-large-{name}"></div>',
                                '<div class="name" style="margin-top:6px">',
                                    '{[Scalr.utils.beautifySoftware(values.name)]}',
                                '</div>',
                            '</div>',
                        '</div>',
                    '</tpl>',
                    {
                        getVpcRegion: function() {
                            var vpc = this.ownerView.up('roleslibrary').vpc;
                            return vpc ? vpc.region : '';
                        },
                        isItemDisabled: function(values) {
                            var vpc = this.ownerView.up('roleslibrary').vpc,
                                images,
                                disabled = false;
                            if (vpc && vpc.region) {
                                for (var i=0, len=values.roles.length; i<len; i++) {
                                    images = values.roles[i].images;
                                    disabled = images['ec2'] && Ext.Object.getSize(images) === 1 && images['ec2'][vpc.region] === undefined;
                                    if (!disabled) {
                                        break;
                                    }
                                }
                            }
                            return disabled;
                        }
                    }
                ),
                listeners: {
                    beforecontainerclick: function(comp, e){//prevent deselect on container click
                        var result = false,
                            el = comp.el.query('a.add-link');
                        if (el.length) {
                            for (var i=0, len=el.length; i<len; i++) {
                                if (e.within(el[i])) {
                                    result = true;
                                    break;
                                }
                            }
                        }
                        return result;
                    },
                    beforeitemclick: function(view, record, item) {
                        return !Ext.get(item).hasCls('x-item-disabled');
                    },
                    selectionchange: function(comp, selection){
                        var roleslibrary = this.up('roleslibrary'),
                            form = roleslibrary.down('form');
                        if (selection.length) {
                            form.currentRole = selection[0];
                            form.loadRecord(roleslibrary.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.createModel({}));
                        } else {
                            form.hide();
                            roleslibrary.fireEvent('hideform');
                        }
                    }
                }
            },
            customRolesViewConfig: {
                xtype: 'grid',
                cls: 'x-roleslibary-list',
                itemId: 'customroles',
                hideHeaders: true,
                padding: '0 12 12',
                plugins: [{
                    ptype: 'focusedrowpointer',
                    thresholdOffset: 0
                }],
                store: {
                    fields: [
                        { name: 'role_id', type: 'int' }, 'cat_id', 'name', 'images', 'behaviors', 'osId', 'roles', 'variables', 'shared', 'description', 'isQuickStart', 'isDeprecated', 'scope'
                    ],
                    proxy: 'object',
                    filterOnLoad: true,
                    sortOnLoad: true,
                    sortOnFilter: true,
                    sorters: [{
                        property: 'name',
                        transform: function(value){
                            return value.toLowerCase();
                        }
                    }]
                },
                viewConfig: {
                    selectedRecordFocusCls: '',
                    getRowClass: function (record, index, rowParams) {
                        var vpc = this.up('roleslibrary').vpc,
                            images = record.get('images'),
                            cls = [];
                        if (vpc && vpc.region) {
                            if (images['ec2'] && Ext.Object.getSize(images) === 1 && images['ec2'][vpc.region] === undefined) {
                                cls.push('x-grid-row-disabled');
                            }
                        }
                        if (cls.length === 0 && record.get('isQuickStart') == 1) {
                            cls.push('x-grid-row-green-text');
                        }
                        return cls.join(',');
                    },
                    listeners: {
                        viewready: function(){
                            var me = this;
                            Ext.create('Ext.tip.ToolTip', {
                                target: me.el,
                                delegate: '.x-grid-row',
                                trackMouse: true,
                                renderTo: Ext.getBody(),
                                hideDelay: 0,
                                listeners: {
                                    beforeshow: function (tip) {
                                        var trigger = Ext.fly(tip.triggerElement),
                                            record = me.getRecord(trigger),
                                            text;
                                        if (record) {
                                            if (trigger.hasCls('x-grid-row-disabled')) {
                                                text = 'Role doesn\'t contain images in current VPC region';
                                            } else if (record.get('isQuickStart') == 1) {
                                                text = 'This Role is being featured as a QuickStart Role';
                                            }

                                        }
                                        if (text) {
                                            tip.update(text);
                                        } else {
                                            return false;
                                        }
                                    }
                                }
                            });
                        },
                        beforeselect: function(view, record) {
                            var el = Ext.get(this.getNode(record));
                            return el ? !el.down('tr').hasCls('x-grid-row-disabled') : true;
                        }

                    }

                },
                columns: [{
                    xtype: 'templatecolumn',
                    width: 22,
                    tpl: new Ext.XTemplate('&nbsp;&nbsp;{[this.getScope(values.scope)]}',
                        {
                            getScope: function(scope){
                                return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qalign="bl-tr" data-qtip="This Role is defined in the '+Ext.String.capitalize(scope)+' Scope"/>';
                            }
                        }
                    )
                },{
                    xtype: 'templatecolumn',
                    width: 32,
                    align: 'center',
                    tpl  : '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-role-small x-icon-role-small-{[Scalr.utils.getRoleCls(values)]}"/>'
                },{
                    xtype: 'templatecolumn',
                    dataIndex: 'name',
                    flex: 1,
                    tpl: new Ext.XTemplate('<span {[this.getNameStyle(values)]}>{name}</span>', {
                        getNameStyle: function(values) {
                            //return 'style="color: green;' + (values.isDeprecated == 1 ? 'text-decoration: line-through;' : '') + '" data-qtip="This Role is being featured as a QuickStart Role."';
                            if (values.isDeprecated == 1) {
                                return 'style="text-decoration: line-through;" data-qtip="This Role is being deprecated, and cannot be added to any Farm."';
                            }
                        }
                    })
                },{
                    xtype: 'templatecolumn',
                    width: 100,
                    align: 'right',
                    tpl  : new Ext.XTemplate('{[this.renderPlatforms(values.images)]}',{
                        renderPlatforms: function(images) {
                            var res = '';
                            Ext.Object.each(Scalr.platforms, function(key){
                                if (images[key] !== undefined) {
                                    res += '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-platform-small x-icon-platform-small-' + key + '"/>';
                                }
                            });
                            return res;
                        }
                    })
                },{
                    xtype: 'templatecolumn',
                    width: 40,
                    align: 'center',
                    tpl  : '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-osfamily-small x-icon-osfamily-small-{[Scalr.utils.getOsById(values.osId, \'family\')]}"/>'
                }],
                listeners: {
                    selectionchange: function(e, selection) {
                        var roleslibrary = this.up('roleslibrary'),
                            form = roleslibrary.down('form');
                        if (selection.length) {
                            form.currentRole = selection[0];
                            form.loadRecord(roleslibrary.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.createModel({}));
                            //this.getView().focus();
                        } else {
                            form.hide();
                            roleslibrary.fireEvent('hideform');
                        }
                    }
                }
            },
            fillPlatformFilter: function() {
                var platformFilter = this.down('#platform'),
                    item;

                item = platformFilter.add({
                    text: '&nbsp;All clouds',
                    value: null,
                    iconCls: 'x-icon-osfamily-small'
                });
                Ext.Object.each(Scalr.platforms, function(key, platform) {
                    if (!platform['enabled']) return;
                    platformFilter.add({
                        text: '&nbsp;' +  platform.name,
                        value: key,
                        iconCls: 'x-icon-platform-small x-icon-platform-small-' + key
                    });
                });
                platformFilter.suspendEvents(false);
                platformFilter.toggleItem(item, true);
                platformFilter.resumeEvents();
            },

            fillOsFilter: function(store) {
                var me = this,
                    mode = this.up('roleslibrary').mode,
                    os = [],
                    osField = me.down('#filters').down('#os'),
                    currentOsItem = osField.getActiveItem(),
                    menuItem;

                store.getUnfiltered().each(function(rec) {
                    if (mode === 'shared') {
                        Ext.Array.each(rec.get('roles'), function(role){
                            os.push(Scalr.utils.getOsById(role.osId, 'family') || 'unknown');
                        });
                    } else {
                        os.push(Scalr.utils.getOsById(rec.get('osId'), 'family') || 'unknown');
                    }
                });

                os = Ext.Array.unique(os);
                os = Ext.Array.sort(os);
                osField.removeAll();
                menuItem = osField.add({
                    text: 'All operating systems',
                    value: null,
                    iconCls: 'x-icon-osfamily-small'
                });
                for (var i=0, len=os.length; i<len; i++) {
                    var tmpMenuItem = osField.add({
                        text: Scalr.utils.beautifyOsFamily(os[i]),
                        value: os[i],
                        iconCls: 'x-icon-osfamily-small x-icon-osfamily-small-' + os[i]
                    });
                    if (currentOsItem !== undefined && tmpMenuItem.value === currentOsItem.value) {
                        menuItem = tmpMenuItem;
                    }
                }
                osField.suspendEvents(false);
                osField.toggleItem(menuItem, true);
                osField.resumeEvents();
            },
            dockedItems: {
                 xtype: 'toolbar',
                 itemId: 'filters',
                 ui: 'simple',
                 dock: 'top',
                 cls: 'x-roleslibrary-filter',
                 items: [{
                     xtype: 'cyclealt',
                     itemId: 'platform',
                     maskOnDisable: true,
                     width: 110,
                     margin: '0 12 0 0',
                     cls: 'x-btn-compressed',
                     changeHandler: function(comp, item) {
                         this.up('#leftcol').refreshStoreFilter();
                     },
                     getItemText: function(item) {
                         return item.value ? 'Cloud: &nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '" />' : item.text;
                     },
                     menu: {
                         cls: 'x-menu-light x-menu-cycle-button-filter',
                         minWidth: 200,
                         items: []
                     }
                 },{
                     xtype: 'cyclealt',
                     itemId: 'os',
                     width: 110,
                     margin: '0 12 0 0',
                     cls: 'x-btn-compressed',
                     text: 'All OS',
                     changeHandler: function(comp, item) {
                         this.up('#leftcol').refreshStoreFilter();
                     },
                     getItemText: function(item) {
                         return item.value ? 'OS: &nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '"/>' : 'All OS';
                     },
                     menu: {
                         cls: 'x-menu-light x-menu-cycle-button-filter',
                         minWidth: 200,
                         items: []
                     }
                 },{
                     xtype: 'tbfill'
                 },{
                    xtype: 'filterfield',
                    itemId: 'search',
                    minWidth: 110,
                    maxWidth: 232,
                    flex: 100,
                    //filterId: 'keyword',
                    emptyText: 'Filter',
                    forceSearchButonVisibility: true,
                    doForceChange: function() {
                        if (this.getValue()) {
                            this.up('#leftcol').createSearch();
                        }
                    },
                    validator: function(value) {
                        if (value) {
                            value = Ext.String.trim(value);
                            if (!(/^[A-Za-z0-9\-\_]+$/).test(value)) {
                                return 'Keyword must contain only letters, numbers and dashes.';
                            }
                        }
                        return true;
                    },
                    listeners: {
                        change: {
                            fn: function(comp, value) {
                                if (comp.isValid()) {
                                    this.up('#leftcol').refreshStoreFilter();
                                }
                            },
                            buffer: 300
                        }
                    }
                 }]
             }
        },{
            xtype: 'container',
            itemId: 'rightcol',
            flex: 1,
            plugins: [{
                ptype: 'adjustwidth'
            }],
            layout: 'fit',
            items: {
                xtype: 'form',
                itemId: 'addrole',
                hidden: true,
                overflowY: 'auto',
                overflowX: 'hidden',
                layout: 'auto',
                state: {},
                plugins: {
                    ptype: 'panelscrollfix',
                    pluginId: 'panelscrollfix'
                },
                items: [{
                    xtype: 'fieldset',
                    itemId: 'main',
                    cls: 'x-fieldset-no-text-transform',
                    headerCls: 'x-fieldset-separator-bottom',
                    items: [{
                        xtype: 'container',
                        itemId: 'imageoptions',
                        maxWidth: 760,
                        layout: {
                            type: 'hbox',
                            align: 'stretch'
                        },
                        defaults: {
                            flex: 1
                        },
                        items: [{
                            xtype: 'container',
                            itemId: 'leftcol',
                            layout: 'anchor',
                            defaults: {
                                anchor: '100%',
                                labelWidth: 120
                            },
                            items: [{
                                xtype: 'displayfield',
                                fieldLabel: 'Cloud',
                                hidden: true,
                                name: 'display_platform'
                            },{
                                xtype: 'buttongroupfield',
                                name: 'platform',
                                baseCls: '',
                                fieldLabel: 'Cloud',
                                labelAlign: 'top',
                                defaults: {
                                    xtype: 'button',
                                    ui: 'simple',
                                    margin: '4 6 6 0'
                                },
                                listeners: {
                                    change: function(comp, value){
                                        this.up('form').fireEvent('selectplatform', value);
                                    }
                                }
                            },{
                                xtype: 'combo',
                                name: 'cloud_location',
                                editable: false,
                                fieldLabel: 'Location',
                                valueField: 'id',
                                displayField: 'name',
                                queryMode: 'local',
                                hideInputOnReadOnly: true,
                                store: {
                                    fields: ['id', 'name', 'disabled'],
                                    sorters: {
                                        property: 'name'
                                    }
                                },
                                listConfig: {
                                    cls: 'x-boundlist-with-icon',
                                    tpl : '<tpl for=".">'+
                                            '<tpl if="disabled">'+
                                                '<div class="x-boundlist-item x-boundlist-item-disabled" title="Location is not available">{name}&nbsp;<span class="warning"></span></div>'+
                                            '<tpl else>'+
                                                '<div class="x-boundlist-item">{name}</div>'+
                                            '</tpl>'+
                                          '</tpl>'
                                },
                                listeners: {
                                    change: function(comp, value) {
                                        this.up('form').fireEvent('selectlocation', value);
                                    },
                                    beforeselect: function(comp, record) {
                                        if (comp.isExpanded) {
                                            return !record.get('disabled');
                                        }
                                    }
                                }
                            },{
                                xtype: 'comboradio',
                                fieldLabel: 'Avail zone',
                                submitValue: false,
                                name: 'availabilityZone',
                                valueField: 'zoneId',
                                displayField: 'name',
                                hidden: true,
                                listConfig: {
                                    cls: 'x-menu-light'
                                },
                                store: {
                                    fields: [ 'zoneId', 'name', 'state', 'disabled', 'items' ],
                                    proxy: 'object'
                                },
                                listeners: {
                                    collapse: function() {
                                        var value = this.getValue();
                                        if (Ext.isObject(value) && value.items.length === 0) {
                                            this.setValue('');
                                        }
                                    }
                                }
                            },{
                                // TODO: fix extjs5 remove multiSelect, use field.Tag
                                xtype: 'combobox',
                                fieldLabel: 'Avail zone',
                                multiSelect: true,
                                name: 'availabilityZoneGce',
                                valueField: 'name',
                                displayField: 'description',
                                allowBlank: false,
                                hidden: true,
                                listConfig: {
                                    cls: 'x-boundlist-with-icon',
                                    tpl : '<tpl for=".">'+
                                            '<tpl if="state != &quot;UP&quot;">'+
                                                '<div class="x-boundlist-item x-boundlist-item-disabled" title="Zone is offline for maintenance"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{description}&nbsp;<span class="warning"></span></div>'+
                                            '<tpl else>'+
                                                '<div class="x-boundlist-item"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{description}</div>'+
                                            '</tpl>'+
                                          '</tpl>'
                                },
                                store: {
                                    fields: [ 'name', {name: 'description', convert: function(v, record){return record.data.description || record.data.name;}}, 'state' ],
                                    proxy: 'object',
                                    sorters: ['name']
                                },
                                editable: false,
                                queryMode: 'local',
                                listeners: {
                                    beforeselect: function(comp, record, index) {
                                        if (comp.isExpanded) {
                                            var result = true;
                                            if (record.get('state') !== 'UP') {
                                                result = false;
                                            }
                                            return result;
                                        }
                                    },
                                    beforedeselect: function(comp, record, index) {
                                        if (comp.isExpanded) {
                                            var result = true;
                                            if (comp.getValue().length < 2) {
                                                Scalr.message.InfoTip('At least one zone must be selected!', comp.inputEl, {anchor: 'bottom'});
                                                result = false;
                                            }
                                            return result;
                                        }
                                    },
                                    change: function(comp, value) {
                                        if (value && value.length) {
                                            var panel = comp.up('form'),
                                                record = panel.getRecord();
                                            Scalr.loadInstanceTypes(record.get('platform'), value[0], Ext.bind(panel.setupInstanceTypeField, panel, [record], true));
                                        }
                                    }
                                }
                            },{
                                xtype: 'textfield',
                                name: 'alias',
                                fieldLabel: 'Alias'
                            }, {
                                xtype: 'combo',
                                name: 'roleid',
                                editable: false,
                                fieldLabel: 'Role',
                                valueField: 'id',
                                displayField: 'name',
                                queryMode: 'local',
                                hidden: true,
                                store: {
                                    fields: ['id', 'name']
                                },
                                plugins: {
                                    ptype: 'fieldicons',
                                    position: 'outer',
                                    icons: {id: 'info', tooltip: 'There are two or more roles that match the current selection.', hidden: false}
                                },
                                listeners: {
                                    change: function(comp, value) {
                                        this.up('form').fireEvent('selectroleid', value);
                                    }
                                }
                            }]
                        },{
                            xtype: 'container',
                            itemId: 'rightcol',
                            margin: '0 0 0 32',
                            layout: 'anchor',
                            defaults: {
                                anchor: '100%',
                                labelWidth: 95
                            },
                            items: [{
                                xtype: 'container',
                                itemId: 'osfilters',
                                layout: 'anchor',
                                defaults: {
                                    anchor: '100%',
                                    labelWidth: 95
                                },
                                items: [{
                                    xtype: 'buttongroupfield',
                                    name: 'osfamily',
                                    fieldLabel: 'Operating system',
                                    labelAlign: 'top',
                                    baseCls: '',
                                    defaults: {
                                        xtype: 'button',
                                        ui: 'simple',
                                        margin: '4 6 6 0'
                                    },
                                    listeners: {
                                        change: function(comp, value){
                                            this.up('form').fireEvent('selectosfamily', value);
                                        }
                                    }
                                },{
                                    xtype: 'combo',
                                    name: 'osname',
                                    editable: false,
                                    valueField: 'id',
                                    displayField: 'name',
                                    queryMode: 'local',
                                    //hideInputOnReadOnly: true,
                                    store: {
                                        fields: ['id', 'name']
                                    },
                                    listeners: {
                                        change: function(comp, value) {
                                            this.up('form').fireEvent('selectosname', value);
                                        }
                                    }
                                },{
                                    xtype: 'fieldcontainer',
                                    layout: 'hbox',
                                    items: [{
                                        xtype: 'displayfield',
                                        name: 'archtext',
                                        fieldLabel: 'Architecture',
                                        labelWidth: 95,
                                        maxWidth: 230,
                                        margin: '0 12 0 0',
                                        hidden: true
                                    },{
                                        xtype: 'buttongroupfield',
                                        name: 'arch',
                                        fieldLabel: 'Architecture',
                                        labelWidth: 95,
                                        maxWidth: 230,
                                        margin: '0 12 0 0',
                                        flex: 1,
                                        layout: 'hbox',
                                        defaults: {
                                            flex: 1,
                                            style: 'padding-left:2px;padding-right:2px'
                                        },
                                        items: [{
                                            value: 'x86_64',
                                            text: '64 bit'
                                        },{
                                            value: 'i386',
                                            text: '32 bit'
                                        }],
                                        listeners: {
                                            change: function(comp, value) {
                                                this.up('form').fireEvent('selectarch', value);
                                            }
                                        }
                                    },{
                                        xtype: 'buttonfield',
                                        name: 'hvm',
                                        hidden: true,
                                        flex: .25,
                                        maxWidth: 70,
                                        style: 'padding-left:2px;padding-right:2px',
                                        text: 'HVM',
                                        enableToggle: true,
                                        toggleHandler: function() {
                                            var form = this.up('form');
                                            if (form.mode === 'shared') {
                                                form.fireEvent('selecthvm', this.pressed ? 1 : 0);
                                            }
                                        }
                                    }]
                                }]
                            },{
                                xtype: 'displayfield',
                                fieldLabel: 'OS',
                                labelWidth: 30,
                                name: 'display_os_name'
                            },{
                                xtype: 'displayfield',
                                fieldLabel: 'Architecture',
                                name: 'display_architecture'
                            },{
                                xtype: 'displayfield',
                                name: 'behaviors',
                                fieldLabel: 'Automation',
                                renderer: function (value) {
                                    var html = [];
                                    Ext.Array.each(value, function (value) {
                                        if (value !== 'base') {
                                            html.push('<img style="float:left;margin:0 8px 8px 0" class="x-icon-role-small x-icon-role-small-' + value + '" src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Ext.htmlEncode(Scalr.utils.beautifyBehavior(value, true)) + '" />');
                                        }
                                    });
                                    return html.length > 0 ? html.join(' ') : '&ndash;';
                                }

                            }]
                        }]
                    }]
                },{
                    xtype: 'container',
                    cls: 'x-container-fieldset x-fieldset-separator-bottom',
                    layout: 'anchor',
                    items: {
                        xtype: 'instancetypefield',
                        name: 'instanceType',
                        labelWidth: 120,
                        maxWidth: 760,
                        margin: 0,
                        anchor: '100%',
                        submitValue: false,
                        allowBlank: false,
                        iconsPosition: 'outer',
                        listeners: {
                            change: function(comp, value){
                                var record = this.findRecordByValue(value);
                                if (record) {
                                    var panel = this.up('form');
                                    panel.updateRecordSettings(panel.getRecord().getInstanceTypeParamName(), value);
                                }
                            }
                        }
                    }
                }],
                dockedItems: [{
                    xtype: 'container',
                    dock: 'bottom',
                    cls: 'x-docked-buttons',
                    maxWidth: 780,
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        itemId: 'save',
                        text: 'Add to farm',
                        handler: function () {
                            var formPanel = this.up('form'),
                                form = formPanel.getForm(),
                                rolesLibrary = this.up('roleslibrary'),
                                values = {settings: {}},
                                record = form.getRecord(),
                                role = formPanel.getCurrentRole(),
                                image,
                                isValid,
                                field,
                                value,
                                cloudLocation;

                            isValid = formPanel.isExtraSettingsValid(record);
                            if (isValid !== true) {
                                if (isValid && isValid.comp.inputEl) {
                                    isValid.comp.inputEl.scrollIntoView(this.up('#addrole').body.el, false, false);
                                    Scalr.message.InfoTip(isValid.message || isValid.comp.getErrors().join(' '), isValid.comp.inputEl, {anchor: 'bottom'});
                                }
                                return;
                            }
                            cloudLocation = form.findField('cloud_location').getValue();

                            values.platform = form.findField('platform').getValue();
                            values.cloud_location = cloudLocation;
                            values.alias = form.findField('alias').getValue();

                            if ((values.platform==='ec2') && formPanel.up('roleslibrary').vpc === false) {
                                value = form.findField('availabilityZone').getValue();
                                if (Ext.isObject(value)) {
                                    if (value.items) {
                                        if (value.items.length === 1) {
                                            value = value.items[0];
                                        } else if (value.items.length > 1) {
                                            value = value.zoneId + '=' + value.items.join(':');
                                        }
                                    }
                                }
                                values.settings['aws.availability_zone'] = value;
                            } else if (Scalr.isOpenstack(values.platform)) {
                                field = form.findField('availabilityZone');
                                if (field.isVisible()) {
                                    value = field.getValue();
                                    if (Ext.isObject(value)) {
                                        if (value.items) {
                                            if (value.items.length === 1) {
                                                value = value.items[0];
                                            } else if (value.items.length > 1) {
                                                value = value.zoneId + '=' + value.items.join(':');
                                            }
                                        }
                                    }
                                    values.settings['openstack.availability_zone'] = value;
                                }
                            } else if (values.platform === 'gce') {
                                field = form.findField('availabilityZoneGce');
                                if (field.validate()) {
                                    var location = field.getValue(),
                                        region = '';

                                    if (location.length === 1) {
                                        location = location[0];
                                        region = location;
                                    } else if (location.length > 1) {
                                        location = 'x-scalr-custom=' + location.join(':');
                                        region = 'x-scalr-custom';
                                    } else {
                                        location = '';
                                    }
                                    values.settings[ 'gce.cloud-location'] = location;
                                    values.settings[ 'gce.region'] = region;
                                } else {
                                    Scalr.message.InfoTip('Availability zone is required', field.inputEl, {anchor: 'bottom'});
                                    return;
                                }
                            }

                            field = form.findField('instanceType');
                            if (field.validate()) {
                                if (values.platform==='ec2') {
                                    var instanceTypeInfo = field.findRecordByValue(field.getValue());
                                    if (instanceTypeInfo && instanceTypeInfo.get('ebsoptimized') === 'default') {
                                        values.settings['aws.ebs_optimized'] = 1;
                                    }
                                }
                                values.settings[record.getInstanceTypeParamName(values.platform)] = field.getValue();
                                values.settings['info.instance_type_name'] = field.findRecordByValue(field.getValue()).get('name');
                            } else {
                                Scalr.message.InfoTip('Instance type is required', field.inputEl, {anchor: 'bottom'});
                                return;
                            }


                            image = role.images[values.platform][values.platform === 'gce' || values.platform === 'azure' ? '' : values.cloud_location];
                            if (formPanel.mode === 'shared') {
                                Ext.apply(values, {
                                    behaviors: role.behaviors,
                                    role_id: role.role_id,
                                    generation: role.generation,
                                    osId: role.osId,
                                    name: role.name,
                                    cat_id: role.cat_id
                                });
                            }

                            Ext.applyIf(values.settings, formPanel.getExtraSettings(record));

                            //some gce region/zone magic
                            if (values.platform === 'gce') {
                                values.cloud_location = values['settings']['gce.region'];
                                values['settings']['gce.region'] = cloudLocation;
                            }

                            record.set(values);

                            if (rolesLibrary.fireEvent('addrole', record)) {
                                rolesLibrary.down('#leftcol').deselectCurrent();
                            }
                        }
                    }, {
                        xtype: 'button',
                        itemId: 'cancel',
                        text: 'Cancel',
                        handler: function() {
                            this.up('roleslibrary').down('#leftcol').deselectCurrent();
                        }
                    }]
                }],
                getAvailablePlatforms: function() {
                    var images,
                        roles,
                        platforms,
                        vpc = this.up('roleslibrary').vpc,
                        removeEc2 = false;//we must remove ec2 if there is no   location === vpc.region

                    if (this.mode === 'custom') {
                        images = this.currentRole.get('images');
                        platforms = Ext.Object.getKeys(images);
                        removeEc2 = vpc !== false && images['ec2'] !== undefined && images['ec2'][vpc.region] === undefined;
                    } else {
                        platforms = [];
                        roles = this.currentRole.get('roles');
                        removeEc2 = vpc !== false;
                        for (var i=0, len=roles.length; i<len; i++) {
                            images = roles[i].images;
                            platforms = Ext.Array.merge(platforms, Ext.Object.getKeys(images));
                            if (removeEc2 && vpc !== false) {
                                if (images['ec2'] !== undefined && images['ec2'][vpc.region] !== undefined)
                                    removeEc2 = false;
                            }
                        }
                    }

                    var platformsSorted = [];
                    Ext.Object.each(Scalr.platforms, function(key, platform){
                        if (Ext.Array.contains(platforms, key) && platform['enabled']) {
                            platformsSorted.push({id: key, disabled: removeEc2 && key === 'ec2' ? true : false});
                        }
                    });
                    return platformsSorted;
                },

                getAvailableImagesCount: function() {
                    var count = 0;
                    if (this.mode === 'custom') {
                        Ext.Object.each(this.currentRole.get('images'), function(key, value){
                            count += Ext.Object.getSize(value);
                        });
                    } else {
                        Ext.Array.each(this.currentRole.get('roles'), function(role){
                            Ext.Object.each(role.images, function(platform, locations){
                                count += Ext.Object.getSize(locations);
                            });
                        });
                    }
                    return count;
                },

                isExtraSettingsValid: function(record) {
                    var res = true;
                    this.items.each(function(item){
                        if (item.isExtraSettings === true && item.isVisible()) {
                            res = item.isValid(record);
                        }
                        return res === true;
                    });
                    return res;
                },

                getExtraSettings: function(record) {
                    var settings = {};
                    this.items.each(function(item){
                        if (item.isExtraSettings === true && item.isVisible()) {
                            Ext.apply(settings, item.getSettings(record));
                        }
                    });
                    return settings;
                },

                getCurrentRole: function() {
                    var me = this,
                        role;
                    if (this.mode === 'custom') {
                        role = me.currentRole.getData();
                    } else if (me.state.roleid) {
                        Ext.Array.each(me.currentRole.get('roles'), function(r){
                            if (r.role_id === me.state.roleid) {
                                role = r;
                                return false;
                            }
                        });
                    }
                    return role;
                },

                updateRecordSettings: function(name, value) {
                    var me = this,
                        record = me.getForm().getRecord(),
                        settings = record.get('settings', true) || {};
                    settings[name] = value;
                    record.set('settings', settings);
                    if (!me.isLoading) {
                        Ext.Array.each(me.extraSettings, function(module){
                            if (module.onSettingsUpdate !== undefined && module.isVisible()) {
                                module.onSettingsUpdate(record, name, value);
                            }
                        });
                    }
                },

                setupInstanceTypeField: function(data, status, record, callback, scope) {
                    var me = this,
                        field = me.getForm().findField('instanceType'),
                        limits = record.getInstanceTypeLimits(),
                        instanceType,
                        instanceTypeList;

                    instanceType = record.getInstanceType(data, me.up('#farmDesigner').getVpcSettings() !== false);
                    instanceTypeList = instanceType['list'] || [];

                    field.store.load({ data: instanceTypeList });
                    field.setValue(instanceType['value']);
                    field.setReadOnly(instanceTypeList.length < 2);
                    field.toggleIcon('governance', !!limits);
                    field.emptyText = instanceType['allowedInstanceTypeCount'] ? ' ' : 'No suitable instance type';
                    field.applyEmptyText();
                    field.updateListEmptyText({cloudLocation:record.get('cloud_location'), limits: !!limits});

                    if(callback) callback.call(scope, record);
                },

                setupAvailabilityZoneField: function(callback, scope) {
                    var me = this,
                        form = me.getForm(),
                        availZoneField;
                    if (me.state.platform === 'ec2') {
                        form.findField('availabilityZoneGce').hide();
                        availZoneField = form.findField('availabilityZone');
                        availZoneField.show();
                        if (me.up('roleslibrary').vpc === false) {
                            Scalr.cachedRequest.load(
                                {
                                    url: '/platforms/'+me.state.platform+'/xGetAvailZones',
                                    params: {cloudLocation: me.state.location}
                                },
                                function(data, status){
                                    var items = [{zoneId: '', name: 'AWS-chosen'}];

                                    if (status) {
                                        items = [{
                                            zoneId: 'x-scalr-diff',
                                            name: 'Distribute equally'
                                        },{
                                            zoneId: '',
                                            name: 'AWS-chosen'
                                        },{
                                            zoneId: 'x-scalr-custom',
                                            name: 'Selected by me',
                                            items: Ext.Array.map(data || [], function(item){ item.disabled = item.state != 'available'; return item;})
                                        }];
                                    }
                                    availZoneField.store.loadData(items);
                                    availZoneField.setValue('');
                                    availZoneField.setDisabled(!status);
                                    if (callback) callback.apply(scope);
                                },
                                this
                            );
                        } else {
                            availZoneField.hide();
                            if (callback) callback.apply(scope);
                        }

                    } else if (Scalr.isOpenstack(me.state.platform)) {
                        form.findField('availabilityZoneGce').hide();
                        availZoneField = form.findField('availabilityZone');
                        availZoneField.show();
                        availZoneField.enable();
                        Scalr.cachedRequest.load(
                            {
                                url: '/platforms/openstack/xGetOpenstackResources',
                                params: {
                                    cloudLocation: me.state.location,
                                    platform: me.state.platform
                                }
                            },
                            function(data, status){
                                if (data && data['availabilityZones']) {
                                    var items = [{zoneId: '', name: 'Cloud-chosen'}];

                                    if (status) {
                                        items = [{
                                            zoneId: 'x-scalr-diff',
                                            name: 'Distribute equally'
                                        },{
                                            zoneId: '',
                                            name: 'Cloud-chosen'
                                        },{
                                            zoneId: 'x-scalr-custom',
                                            name: 'Selected by me',
                                            items: Ext.Array.map(data['availabilityZones'] || [], function(item){ item.disabled = item.state != 'available'; return item;})
                                        }];
                                    }
                                    availZoneField.store.loadData(items);
                                    availZoneField.setValue('');
                                } else {
                                    availZoneField.hide();
                                }
                                if (callback) callback.apply(scope);
                            },
                            this
                        );
                    } else if (me.state.platform === 'gce') {
                        form.findField('availabilityZone').hide();
                        availZoneField = form.findField('availabilityZoneGce');
                        availZoneField.show();
                        Scalr.cachedRequest.load(
                            {
                                url: '/platforms/gce/xGetOptions',
                                params: {}
                            },
                            function(data, status){
                                var zones = [], defaultZone;
                                if (status) {
                                    Ext.each(data['zones'], function(zone){
                                        if (zone['name'].indexOf(me.state.location) === 0) {
                                            zones.push(zone);
                                        }
                                    });
                                }
                                availZoneField.store.loadData(zones);
                                availZoneField.reset();
                                defaultZone = availZoneField.store.first();
                                availZoneField.setValue(defaultZone && defaultZone.get('state') === 'UP' ? defaultZone : '');
                                availZoneField.setDisabled(!status);
                                if (callback) callback.apply(scope);
                            },
                            this
                        );
                    } else {
                        form.findField('availabilityZone').hide();
                        form.findField('availabilityZoneGce').hide();
                        if (callback) callback.apply(scope);
                    }
                },

                toggleExtraSettings: function(record) {
                    var me = this,
                        disableAddToFarmButton = false;
                    me.setupAvailabilityZoneField(function(){
                        me.items.each(function(item){
                            if (item.isExtraSettings === true) {
                                if (item.suspendUpdateEvent !== undefined) {
                                    item.suspendUpdateEvent++;
                                }
                                if (item.isVisibleForRole(record)) {
                                    disableAddToFarmButton = (item.setRole !== undefined ? item.setRole(record) : undefined) === false || disableAddToFarmButton;
                                    item.show();
                                } else {
                                    item.hide();
                                }
                                if (item.suspendUpdateEvent !== undefined) {
                                    item.suspendUpdateEvent--;
                                }

                            }
                        });
                        me.down('#save').setDisabled(disableAddToFarmButton);
                    }, me);
                },

                listeners: {
                    beforerender: function() {
                        var me = this,
                            ext = ['vpc', 'openstack', 'cloudstack', 'azure', 'mongodb', 'dbmsr', 'haproxy', 'proxy', 'chef'];
                        me.extraSettings = [];
                        Ext.Array.each(ext, function(name){
                            me.extraSettings.push(me.add(Scalr.cache['Scalr.ui.farms.builder.addrole.' + name]()));
                        });
                    },
                    selectplatform: function(value) {
                        if (Ext.isEmpty(value)) return;

                        var me = this,
                            form = me.getForm(),
                            locations = [],
                            defaultLocation,
                            fieldLocation = form.findField('cloud_location'),
                            vpc = this.up('roleslibrary').vpc;

                        me.state.platform = value;

                        cb = function(locations, defaultLocation) {
                            fieldLocation.setReadOnly(locations.length < 2, false);

                            fieldLocation.store.loadData(locations);
                            fieldLocation.reset();
                            fieldLocation.setValue(defaultLocation);
                        };

                        if (value === 'gce') {
                            Scalr.cachedRequest.load(
                                {
                                    url: '/platforms/gce/xGetOptions',
                                    params: {}
                                },
                                function(data, status){
                                    if (status && !Ext.isEmpty(data.regions)) {
                                        Ext.each(data.regions, function(region) {
                                            var disabled = true;
                                            Ext.each(data.zones, function(zone){
                                                if (zone['name'].indexOf(region['name']) === 0 && zone['state'] === 'UP') {
                                                    return disabled = false;

                                                }
                                            });
                                            defaultLocation = region.name === 'us-central1' ? region.name : (defaultLocation || region.name);
                                            locations.push({id: region.name, name: region.description || region.name, disabled: disabled});
                                        });
                                    }
                                    cb(locations, defaultLocation || '');
                                }
                            );
                        } else if (value === 'azure') {
                            Scalr.CachedRequestManager.get('farmDesigner').load(
                                {
                                    url: '/platforms/azure/xGetResourceGroups'
                                },
                                function(data, status){
                                    if (!Ext.isEmpty(data.cloudLocations)) {
                                        var cloudLocationGrovernance = Scalr.getGovernance('azure', 'azure.cloud_location');
                                        if (cloudLocationGrovernance && cloudLocationGrovernance['default']) {
                                            defaultLocation = cloudLocationGrovernance['default'];
                                        }
                                        Ext.Object.each(data.cloudLocations, function(key, value) {
                                            if (cloudLocationGrovernance === undefined || Ext.Array.contains(cloudLocationGrovernance.value, key)) {
                                                locations.push({id: key, name: value});
                                            }
                                        });
                                    } else {

                                    }
                                    cb(locations, defaultLocation || 'eastus');
                                }
                            );
                        } else {
                            if (this.mode === 'custom') {
                                Ext.Object.each(me.currentRole.get('images')[value] || {}, function(location){
                                    locations.push({id: location, name: location});
                                    defaultLocation = defaultLocation || location;
                                    if (location === 'us-east-1') {
                                       defaultLocation = location;
                                    }
                                    if (location === me.state.location) {
                                        defaultLocation = me.state.location;
                                    }
                                });
                            } else {
                                var uniqueLocations = [];
                                Ext.Array.each(this.currentRole.get('roles'), function(role){
                                    Ext.Object.each(role.images[value] || {}, function(location){
                                        Ext.Array.include(uniqueLocations, location);
                                        defaultLocation = defaultLocation || location;
                                        if (location === 'us-east-1') {
                                           defaultLocation = location;
                                        }
                                        if (location === me.state.location) {
                                            defaultLocation = me.state.location;
                                        }
                                    });
                                });
                                for (var i=0, len=uniqueLocations.length; i<len; i++) {
                                    locations.push({id: uniqueLocations[i], name: uniqueLocations[i]});
                                }
                            }

                            if (value === 'ec2' && vpc !== false) {
                               defaultLocation = vpc.region;
                               locations = [{id: vpc.region, name: vpc.region}];
                            }
                            cb(locations, defaultLocation);
                        }
                    },

                    selectlocation: function(value) {
                        if (value === null || value === undefined) return;
                        var me = this,
                            form = me.getForm(),
                            locations = [],
                            osFamilyField,
                            osFamilies,
                            defaultOsFamily,
                            imagesLocation;

                        me.suspendLayouts();
                        me.state.location = value;
                        imagesLocation = me.state.platform === 'gce' || me.state.platform === 'azure' ? '' : me.state.location;

                        form.findField('cloud_location').store.data.each(function(record){locations.push(record.get('id'))});

                        if (me.showCloudAndOsButtons) {
                            //fill os families
                            osFamilyField = form.findField('osfamily');
                            osFamilyField.reset();
                            osFamilyField.removeAll();

                            osFamilies = [];
                            if (me.mode === 'shared') {
                                Ext.Array.each(me.currentRole.get('roles'), function(role){
                                    if (role.images[me.state.platform] && role.images[me.state.platform][imagesLocation]) {
                                        var osFamily = Scalr.utils.getOsById(role.osId, 'family');
                                        Ext.Array.include(osFamilies, (osFamily || 'unknown'));
                                        defaultOsFamily = defaultOsFamily || (osFamily || 'unknown');
                                        if ((osFamily || 'unknown') === 'ubuntu') {
                                            defaultOsFamily = 'ubuntu';
                                        }

                                    }
                                });
                                osFamilies = Ext.Array.sort(osFamilies);
                                for (var i=0, len=osFamilies.length; i<len; i++) {
                                    osFamilyField.add({
                                        value: osFamilies[i] || 'unknown',
                                        cls: 'x-btn-simple-medium x-icon-osfamily x-icon-osfamily-' + (osFamilies[i] || 'unknown'),
                                        tooltip: Scalr.utils.beautifyOsFamily(osFamilies[i]) || 'Unknown',
                                        tooltipType: 'title'
                                    });
                                }
                            } else {
                                defaultOsFamily = Scalr.utils.getOsById(me.currentRole.get('osId'), 'family') || 'unknown'
                                osFamilyField.add({
                                    value: defaultOsFamily,
                                    cls: 'x-btn-simple-medium x-icon-osfamily x-icon-osfamily-' + defaultOsFamily,
                                    tooltip: Scalr.utils.beautifyOsFamily(defaultOsFamily) || 'Unknown',
                                    tooltipType: 'title'
                                });

                            }
                            osFamilyField.setValue(defaultOsFamily);
                        } else {
                            me.fireEvent('roleimagechange');
                        }

                        me.resumeLayouts(true);
                    },

                    selectosfamily: function(value) {
                        if (value === null || value === undefined) return;
                        var me = this,
                            form = me.getForm(),
                            osNameField,
                            osNames,
                            selectedRole,
                            imagesLocation = me.state.platform === 'gce' || me.state.platform === 'azure' ? '' : me.state.location;
                        this.suspendLayouts();
                        me.state.osfamily = value;

                        osNameField = form.findField('osname');
                        osNameField.reset();
                        osNames = [];
                        if (me.mode === 'shared') {
                            Ext.Array.each(me.currentRole.get('roles'), function(role){
                                var roleOs = Scalr.utils.getOsById(role.osId) || {};
                                if (role.images[me.state.platform] && role.images[me.state.platform][imagesLocation] && (roleOs.family || 'unknown') === me.state.osfamily) {
                                    Ext.Array.include(osNames, roleOs.name);
                                    if (selectedRole === undefined || (parseFloat(Scalr.utils.getOsById(selectedRole.osId, 'version')) || 0) <  (parseFloat(roleOs.version) || 0)) {
                                        selectedRole = role;
                                    }

                                }
                            });
                        } else {
                            osNames.push(Scalr.utils.getOsById(me.currentRole.get('osId'), 'name'));
                            selectedRole = {osId: me.currentRole.get('osId')};
                        }
                        osNames = Ext.Array.map(osNames, function(osname) {
                            return {id: osname, name: osname};
                        });
                        osNameField.store.loadData(osNames);
                        osNameField.setValue(selectedRole !== undefined ? Scalr.utils.getOsById(selectedRole.osId, 'name') : null);
                        osNameField.setReadOnly(osNames.length < 2, false);
                        this.resumeLayouts(true);
                    },

                    selectosname: function(value) {
                        if (value === null || value === undefined) return;
                        var me = this,
                            form = me.getForm(),
                            archField = form.findField('arch'),
                            archs = {},
                            defaultArch,
                            imagesLocation = me.state.platform === 'gce' || me.state.platform === 'azure' ? '' : me.state.location;
                        this.suspendLayouts();
                        me.state.osname = value;

                        if (me.mode === 'shared') {
                            Ext.Array.each(me.currentRole.get('roles'), function(role){
                                var roleOs = Scalr.utils.getOsById(role.osId);
                                if (role.images[me.state.platform] && role.images[me.state.platform][imagesLocation] &&
                                   (roleOs.family || 'unknown') === me.state.osfamily && roleOs.name === me.state.osname) {
                                    var arch = role.images[me.state.platform][imagesLocation].architecture;
                                    archs[arch] = 1;
                                    defaultArch = defaultArch || arch;
                                    if (arch === 'x86_64') {
                                        defaultArch = 'x86_64';
                                    }
                                }
                            });
                        } else {
                            defaultArch = me.currentRole.get('images')[me.state.platform][me.state.platform === 'gce' || me.state.platform === 'azure' ? '' : me.state.location]['architecture'] || 'x86_64';
                            archs[defaultArch] = 1;
                        }
                        archField.reset();
                        archField.down('[value="i386"]').setDisabled(archs['i386'] === undefined);
                        archField.down('[value="x86_64"]').setDisabled(archs['x86_64'] === undefined);
                        archField.setValue(defaultArch);
                        if (archs['i386'] === undefined || archs['x86_64'] === undefined) {
                            archField.hide();
                            form.findField('archtext').show().setValue((archs['i386'] === undefined ? '64' : '32') + ' bit');
                        } else {
                            archField.show();
                            form.findField('archtext').hide();
                        }
                        this.resumeLayouts(true);
                    },

                    selectarch: function(value) {
                        if (Ext.isEmpty(value)) return;
                        var me = this,
                            form = me.getForm(),
                            hvmField = form.findField('hvm'),
                            hvms = {},
                            imagesLocation = me.state.platform === 'gce' || me.state.platform === 'azure' ? '' : me.state.location;
                        this.suspendLayouts();
                        me.state.arch = value;

                        if (me.mode === 'shared') {
                            Ext.Array.each(me.currentRole.get('roles'), function(role){
                                var roleOs = Scalr.utils.getOsById(role.osId);
                                if (
                                    role.images[me.state.platform] && role.images[me.state.platform][imagesLocation] &&
                                    role.images[me.state.platform][imagesLocation]['architecture'] === me.state.arch &&
                                    (roleOs.family || 'unknown') === me.state.osfamily && roleOs.name === me.state.osname
                                ) {
                                   hvms[(role.images[me.state.platform][imagesLocation].type || '').indexOf('hvm') !== -1 ? 1 : 0] = 1;
                                }
                            });

                            if (hvms[1] === 1 && hvms[0] === 1) {
                                hvmField.enable().toggle(0, false);
                            } else {
                                hvmField.disable().toggle(hvms[1] === 1 ? 1 : 0, false);
                            }
                            me.fireEvent('selecthvm', hvmField.pressed ? 1 : 0);
                            hvmField.setVisible(me.state.platform === 'ec2' && hvms[1] !== undefined);
                        } else {
                            hvmField.hide();
                            this.fireEvent('roleimagechange');
                        }
                        this.resumeLayouts(true);
                    },

                    selecthvm: function(value) {
                        var me = this,
                            form = me.getForm(),
                            imagesLocation = me.state.platform === 'gce' || me.state.platform === 'azure' ? '' : me.state.location;

                        if (form.getRecord().store) return;//buttonfield doesn't work like normal form field - here is workaround

                        var roleField = form.findField('roleid'),
                            roles = [],
                            defaultRole;
                        this.suspendLayouts();
                        me.state.hvm = value;

                        Ext.Array.each(me.currentRole.get('roles'), function(role){
                            var roleOs = Scalr.utils.getOsById(role.osId);
                            if (
                                role.images[me.state.platform] && role.images[me.state.platform][imagesLocation] &&
                                role.images[me.state.platform][imagesLocation]['architecture'] === me.state.arch &&
                                (roleOs.family || 'unknown') === me.state.osfamily && roleOs.name === me.state.osname &&
                                ((role.images[me.state.platform][imagesLocation].type || '').indexOf('hvm') !== -1 ? 1 : 0) == me.state.hvm
                            ) {
                                roles.push({id: role.role_id, name: role.name});
                                defaultRole = defaultRole || role.role_id;
                                if (role.role_id === me.state.roleid) {
                                    defaultRole = me.state.roleid;
                                }

                            }
                        });
                        roleField.reset();
                        roleField.store.loadData(roles);
                        roleField.setValue(defaultRole);

                        roleField.setVisible(roles.length > 1);
                        this.resumeLayouts(true);
                    },

                    selectroleid: function(value) {
                        if (value === null || value === undefined) return;
                        var me = this,
                            imageoptions = me.down('#imageoptions'),
                            role,
                            behaviors = [];
                        this.suspendLayouts();
                        me.state.roleid = value;

                        role = me.getCurrentRole();
                        this.fireEvent('roleimagechange');
                        this.resumeLayouts(true);
                    },

                    roleimagechange: function() {
                        var me = this,
                            form = me.getForm(),
                            record = form.getRecord(),
                            role = me.getCurrentRole(),
                            image = role.images[me.state.platform][me.state.platform === 'gce' || me.state.platform === 'azure' ? '' : me.state.location],
                            values = {
                                platform: me.state.platform,
                                cloud_location: me.state.location,
                                role_id: role.role_id,
                                origin: role.origin,
                                image: Ext.clone(image),
                                settings: {}
                            },
                            imageOptions = me.down('#imageoptions');

                        if (me.showCloudAndOsButtons) {
                            if (me.mode === 'shared') {
                                var farmVariables = me.up('#farmDesigner').getFarmVariables();
                                var filteredRoleVariables = [];

                                Ext.Array.each(role.variables || [], function (roleVariable) {
                                    var roleVariableName = roleVariable.name;
                                    var isVariableExist = farmVariables.some(function (farmVariable) {
                                        return farmVariable.name === roleVariableName;
                                    });

                                    if (!isVariableExist) {
                                        filteredRoleVariables.push(roleVariable);
                                    }
                                });
                                values.variables = Ext.Array.merge(filteredRoleVariables, farmVariables);
                            }

                            values.osId = role.osId;
                            values.behaviors = role.behaviors;
                        }
                        record.set(values);

                        me.suspendLayouts();
                        me.getComponent('main').setTitle(me.mode === 'shared' ? Scalr.utils.beautifySoftware(record.get('name')) : role.name, role.description);

                        //we must get unique alias from farmrolesstore
                        var beforeSetAliasResult = {};
                        me.up('roleslibrary').fireEvent('beforesetalias', role.name, beforeSetAliasResult);

                        var extraValues = {
                            alias: beforeSetAliasResult.alias,
                            behaviors: Ext.clone(role.behaviors)
                        };
                        if (me.mode === 'custom') {
                            var arch = record.get('image', true)['architecture'];
                            extraValues['display_os_name'] = (new Ext.XTemplate('{[this.getOsById(values.osId)]}')).apply({osId: record.get('osId')});
                            extraValues['display_architecture'] = arch ? (arch === 'i386' ? '32' : '64') + 'bit' : '?';
                        }
                        imageOptions.setFieldValues(extraValues);


                        if (me.state.platform !== 'gce') {
                            Scalr.loadInstanceTypes(me.state.platform, me.state.location, Ext.bind(me.setupInstanceTypeField, me, [record, me.toggleExtraSettings, me], true));
                        } else {
                            me.toggleExtraSettings(record);
                        }
                        me.resumeLayouts(true);
                    },

                    beforeloadrecord: function(record) {
                        this.isLoading = true;
                        this.mode = this.up('roleslibrary').mode;
                        var form = this.getForm(),
                            platformField = form.findField('platform'),
                            rolePlatforms = this.getAvailablePlatforms();

                        this.showCloudAndOsButtons = this.mode === 'shared' || rolePlatforms.length > 1;
                        this.imagesCount = this.getAvailableImagesCount();

                        form.reset();
                        this.suspendLayouts();

                        this.down('#osfilters').setVisible(this.showCloudAndOsButtons);
                        form.findField('display_os_name').setVisible(!this.showCloudAndOsButtons);
                        form.findField('display_architecture').setVisible(!this.showCloudAndOsButtons);
                        form.findField('platform').setVisible(this.showCloudAndOsButtons);
                        form.findField('display_platform').setVisible(!this.showCloudAndOsButtons);
                        form.findField('roleid').hide();

                        //fill platforms
                        platformField.removeAll();
                        for (var i=0, len=rolePlatforms.length; i<len; i++) {
                            platformField.add({
                                value: rolePlatforms[i]['id'],
                                cls: 'x-btn-simple-medium x-icon-platform x-icon-platform-' + rolePlatforms[i]['id'],
                                tooltip: rolePlatforms[i]['disabled'] ? 'No images available in current VPC region' : Scalr.platforms[rolePlatforms[i]['id']] ? Scalr.platforms[rolePlatforms[i]['id']].name : rolePlatforms[i]['id'],
                                disabled: rolePlatforms[i]['disabled']
                            });
                        }

                        var farmVariables = this.up('#farmDesigner').getFarmVariables();
                        var roleVariables = Ext.clone(this.currentRole.get('variables'));
                        var filteredRoleVariables = [];

                        Ext.Array.each(roleVariables, function (roleVariable) {
                            var roleVariableName = roleVariable.name;
                            var isVariableExist = farmVariables.some(function (farmVariable) {
                                return farmVariable.name === roleVariableName;
                            });

                            if (!isVariableExist) {
                                filteredRoleVariables.push(roleVariable);
                            }
                        });

                        record.set({
                            cloud_location: null,
                            behaviors: this.currentRole.get('behaviors'),
                            role_id: this.currentRole.get('role_id'),
                            generation: this.currentRole.get('generation'),
                            osId: this.currentRole.get('osId'),
                            name: this.currentRole.get('name'),
                            cat_id: this.currentRole.get('cat_id'),
                            variables: this.currentRole.get('variables')
                        });
                        this.getPlugin('panelscrollfix').resetScrollPosition();
                        this.resumeLayouts(true);
                    },

                    loadrecord: function(record) {
                        var form = this.getForm(),
                            roleslibrary = this.up('roleslibrary'),
                            rolePlatforms = this.getAvailablePlatforms(),
                            platform,
                            leftcol = roleslibrary.down('#leftcol'),
                            platformFilterValue = leftcol.getFilterValue('platform'),
                            osFilterValue = leftcol.getFilterValue('os');

                        if (osFilterValue) {
                            this.state.osfamily = osFilterValue;
                        }

                        if (platformFilterValue) {
                            platform = platformFilterValue;
                        } else if (this.state.platform && Ext.Array.some(rolePlatforms, function(item){return !item.disabled && item.id == this.state.platform;}, this)) {
                            platform = this.state.platform;
                        } else {
                            Ext.Array.each(rolePlatforms, function(item){
                                if (!item.disabled) {
                                    platform = item['id'];
                                    return false;
                                }
                            });
                            if (!platform) {
                                platform = rolePlatforms[0]['id'];
                            }
                        }

                        form.findField('platform').setValue(platform);

                        if (this.mode === 'custom') {
                            this.down('#imageoptions').setFieldValues({
                                'display_platform': '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-platform-small x-icon-platform-small-' + rolePlatforms[0]['id'] + '"/>&nbsp;' + (Scalr.platforms[rolePlatforms[0]['id']] ? Scalr.platforms[rolePlatforms[0]['id']].name : rolePlatforms[0]['id']),
                                'display_location': Ext.Object.getKeys(this.currentRole.get('images')[rolePlatforms[0]['id']]).join(', ')
                            });
                        }

                        if (!this.isVisible()) {
                            this.show();
                            this.ownerCt.updateLayout();//this is required to recalculate form dimensions after window size was changed, while form was hidden
                            roleslibrary.fireEvent('showform');
                        }
                        this.isLoading = false;
                    }
                }
            }
        }]
    }
});

Ext.define('Scalr.ui.RolesLibraryAdjustWidth', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.adjustwidth',

    resizeInProgress: false,

	init: function(client) {
		var me = this;
		me.client = client;
		client.on({
			boxready: function(){
				this.on({
					resize: function(){
                        if (!me.resizeInProgress) {
                            me.adjustWidth();
                        }
					}
				});
			}
		});
	},

	adjustWidth: function(){
		var rightcol = this.client,
			leftcol = rightcol.prev(),
            container = leftcol.ownerCt,
            rightColMinWidth = 640,
            extraWidth = 13,
			rowLength = Math.floor((container.getWidth() - rightColMinWidth - extraWidth - container.getDockedComponent('tabs').getWidth())/122);

        if (rowLength > 6) {
            rowLength = 6;
        } else if (rowLength < 3) {
            rowLength = 3;
        }

        this.resizeInProgress = true;
        leftcol.setWidth(rowLength*122 + extraWidth);
        this.resizeInProgress = false;
	}

});

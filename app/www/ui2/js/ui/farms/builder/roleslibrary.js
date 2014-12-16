Ext.define('Scalr.ui.RolesLibrary', {
	extend: 'Ext.container.Container',
	alias: 'widget.roleslibrary',
    
    cls: 'scalr-ui-roleslibrary',
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
                this.vpc = this.up('#fbcard').down('#farm').getVpcSettings();
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
            cls: 'x-docked-tabs',
            width: 170,
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
                    Ext.Object.each(me.up('roleslibrary').moduleParams.categories, function(key, value) {
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
                            Scalr.CachedRequestManager.get('farmbuilder').removeCache({
                                url: '/roles/xGetList',
                                params: {catId: 'search', keyword: buttons[0].keyword}
                            })
                            panel.remove(buttons[0]);
                        }
                    }
                    panel.refreshButtonsCls();
                },
                remove: function(panel, btn) {
                    panel.refreshButtonsCls();
                    if (btn.pressed) {
                        var last = panel.items.last();
                        if (last) {
                            last.toggle(true);
                        }
                    }
                }
            },
            refreshButtonsCls: function(){
                var last = this.items.getAt(this.items.length-2);
                if (last) {
                    last.removeCls('scalr-ui-last');
                }
                last = this.items.last();
                if (last) {
                    last.addCls('scalr-ui-last');
                }
            },
            addCategoryBtn: function(data) {
                var btn = {
                    xtype: 'button',
                    ui: 'tab',
                    textAlign: 'left',
                    allowDepress: false,
                    disableMouseDownPressed: true,
                    text: '<span class="x-btn-inner-html-wrap">' + data.name  + (data.total == 0 ? '<span class="superscript">empty</span>' : '') + '</span>',
                    cls: (data.total == 0 ? 'x-btn-tab-deprecated ' : '') + (data.cls || ''),
                    catId: data.catId,
                    keyword: data.keyword,
                    roleId: data.roleId,
                    toggleGroup: 'roleslibray-tabs',
                    toggleHandler: this.toggleHandler,
                    scope: this,
                    style: data.style
                };
                //button remove search
                if (data.catId === 'search') {
                    btn.listeners = {
                        boxready: function(){
                            var me = this;
                            this.btnEl.on({
                                mouseenter: function(){
                                    me.btnDeleteEl = Ext.DomHelper.append(me.btnEl.dom, '<div class="delete-search" title="Remove search"></div>', true)
                                    me.btnDeleteEl.on('click', function(){
                                        Scalr.CachedRequestManager.get('farmbuilder').removeCache({
                                            url: '/roles/xGetList',
                                            params: {catId: 'search', keyword: me.keyword}
                                        })
                                        me.ownerCt.remove(me);
                                    });
                                },
                                mouseleave: function(){
                                    me.btnDeleteEl.remove();
                                    delete me.btnDeleteEl;
                                    
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
            cls: 'x-panel-column-left',
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
                    (tabs.addCategoryBtn({
                        name: 'Role ID: <b> ' + roleId + '</b>',
                        catId: 'search',
                        roleId: roleId,
                        cls: 'scalr-ui-roleslibrary-search',
                        style: 'max-width:164px;overflow:hidden;text-overflow:ellipsis'
                    })).toggle(true);

                } else {
                    var searchField = this.down('#search'),
                        keyword = (searchField.getValue() || '').replace(/[\[\]]/img, '');
                    if (keyword || roleId) {
                        var res = tabs.query('[keyword="'+keyword+'"]');
                        if (res.length === 0) {
                            (tabs.addCategoryBtn({
                                name: 'Search "<b title="' + Ext.htmlEncode(keyword) + '">' + Ext.String.ellipsis(keyword, 12) + '</b>"',
                                catId: 'search',
                                keyword: keyword,
                                cls: 'scalr-ui-roleslibrary-search',
                                style: 'max-width:164px;overflow:hidden;text-overflow:ellipsis'
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
                Scalr.CachedRequestManager.get('farmbuilder').load(
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
                    var rl = this.up('roleslibrary'),
                        filterCategory = this.catId,
                        filterPlatform = this.getFilterValue('platform'),
                        category,
                        platform = Scalr.platforms[filterPlatform] ? Scalr.platforms[filterPlatform].name : filterPlatform,
                        filterOs = this.getFilterValue('os'),
                        text;
                    if (filterCategory === 'shared') {
                        category = 'Quick start';
                    } else if (rl.moduleParams.categories[filterCategory]) {
                        category = rl.moduleParams.categories[filterCategory].name;
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
                    
                    emptyText.getEl().setHTML(text);
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
                            if ((!os || (roles[i].os_family || 'unknown') === os) && (!keyword || (roles[i].name+'').match(RegExp(Ext.String.escapeRegex(keyword), 'i')))) {
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
                            if (vpcRegion && res === true) {
                                if (images['ec2'] && (Ext.Object.getSize(images) === 1 || platform === 'ec2') && images['ec2'][vpcRegion] === undefined) {
                                    res = false;
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
                        if ((!os || (record.get('os_family') || 'unknown')  === os ) && (!keyword || (record.get('name')+'').match(RegExp(Ext.String.escapeRegex(keyword), 'i')))) {
                            images = record.get('images');
                            if (platform) {
                                res = images[platform] !== undefined;
                            } else {
                                res = true;
                            }
                            if (vpcRegion && res === true) {
                                if (images['ec2'] && (Ext.Object.getSize(images) === 1 || platform === 'ec2') && images['ec2'][vpcRegion] === undefined) {
                                    res = false;
                                }
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
                cls: 'x-dataview-boxes scalr-ui-dataview-sharedroles',
                itemSelector: '.x-item',
                overItemCls : 'x-item-over',
                padding: '0 0 12 12',
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
                        '<div class="x-item">',
                            '<div class="x-item-inner">',
                                '<div class="x-icon-behavior-large x-icon-behavior-large-{name}"></div>',
                                '<div class="name" style="margin-top:6px">',
                                    '{[Scalr.utils.beautifySoftware(values.name)]}',
                                '</div>',
                            '</div>',
                        '</div>',
                    '</tpl>'			
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
                    selectionchange: function(comp, selection){
                        var roleslibrary = this.up('roleslibrary'),
                            form = roleslibrary.down('form');
                        if (selection.length) {
                            form.currentRole = selection[0];
                            form.loadRecord(Ext.create('Scalr.ui.FarmRoleModel'));
                        } else {
                            form.hide();
                            roleslibrary.fireEvent('hideform');
                        }
                    }
                }
            },
            customRolesViewConfig: {
                xtype: 'grid',
                cls: 'x-grid-shadow scalr-ui-roleslist x-grid-no-selection',
                itemId: 'customroles',
                hideHeaders: true,
                padding: '0 9 9',
                plugins: [{
                    ptype: 'focusedrowpointer',
                    thresholdOffset: 0
                }],
                store: {
                    fields: [
                        { name: 'role_id', type: 'int' }, 'cat_id', 'name', 'images', 'behaviors', 'os_name', 'os_family', 'roles', 'variables', 'shared', 'description', 'os_generation'
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
                columns: [{
                    xtype: 'templatecolumn',
                    width: 20,
                    tpl: new Ext.XTemplate('&nbsp;&nbsp;{[this.getScope(values.shared)]}',
                        {
                            getScope: function(shared){
                                var scope = shared ? 'scalr' : 'environment';
                                return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qtip="This Role is defined in the '+Ext.String.capitalize(scope)+' Scope"/>';
                            }
                        }
                    )
                },{
                    xtype: 'templatecolumn',
                    width: 40,
                    align: 'center',
                    tpl  : '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-role-small x-icon-role-small-{[Scalr.utils.getRoleCls(values)]}"/>'
                },{
                    dataIndex: 'name',
                    flex: 1
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
                    align: 'right',
                    tpl  : '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-osfamily-small x-icon-osfamily-small-{os_family}"/>'
                }],
                listeners: {
                    selectionchange: function(e, selection) {
                        var roleslibrary = this.up('roleslibrary'),
                            form = roleslibrary.down('form');
                        if (selection.length) {
                            form.currentRole = selection[0];
                            form.loadRecord(Ext.create('Scalr.ui.FarmRoleModel'));
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
                    text: 'All clouds',
                    value: null,
                    iconCls: 'x-icon-osfamily-small'
                });
                Ext.Object.each(Scalr.platforms, function(key, platform) {
                    if (!platform['enabled'] || key === 'rds') return;
                    platformFilter.add({
                        text: platform.name,
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

                (store.snapshot || store.data).each(function(rec) {
                    if (mode === 'shared') {
                        Ext.Array.each(rec.get('roles'), function(role){
                            os.push(role.os_family || 'unknown');
                        });
                    } else {
                        os.push(rec.get('os_family') || 'unknown');
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
                 cls: 'scalr-ui-roleslibrary-filter',
                 items: [{
                     xtype: 'cyclealt',
                     itemId: 'platform',
                     maskOnDisable: true,
                     prependText: 'Cloud: ',
                     text: 'Cloud: All',
                     width: 100,
                     margin: '0 12 0 0',
                     cls: 'x-btn-compressed',
                     changeHandler: function(comp, item) {
                         this.up('#leftcol').refreshStoreFilter();
                     },
                     getItemText: function(item) {
                         return item.value ? '<img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '" />' : 'All';
                     },
                     menu: {
                         cls: 'x-menu-light x-menu-cycle-button-filter',
                         minWidth: 200,
                         items: []
                     }
                 },{
                     xtype: 'cyclealt',
                     itemId: 'os',
                     prependText: 'OS: ',
                     text: 'OS: All',
                     width: 100,
                     margin: '0 12 0 0',
                     cls: 'x-btn-compressed',
                     changeHandler: function(comp, item) {
                         this.up('#leftcol').refreshStoreFilter();
                     },
                     getItemText: function(item) {
                         return item.value ? '<img src="' + Ext.BLANK_IMAGE_URL + '" class="' + item.iconCls + '" title="' + item.text + '"/>' : 'All';
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
                    minWidth: 100,
                    maxWidth: 210,
                    flex: 100,
                    filterId: 'keyword',
                    emptyText: 'Filter',
                    hideFilterIcon: true,
                    filterFn: Ext.emptyFn,
                    listeners: {
                        boxready: function() {
                            this.btnSearchAll = this.bodyEl.up('tr').createChild({
                                tag: 'td',
                                style: 'width: 30px',
                                html: '<div class="x-btn-default-small x-filterfield-btn" title="Search across all categories"><div class="x-filterfield-btn-inner"></div></div>'
                            }).down('div');
                            this.triggerWrap.applyStyles('border-radius: 3px 0 0 3px');
                            this.btnSearchAll.addCls('disabled');
                            this.btnSearchAll.on('click', function(){
                                if (!this.btnSearchAll.hasCls('disabled')) {
                                    this.up('#leftcol').createSearch();
                                }
                            }, this);
                        },
                        afterfilter: function () {
                            this.up('#leftcol').refreshStoreFilter();
                        },
                        change: {
                            fn: function(comp, value) {
                                this.btnSearchAll[Ext.String.trim(value) !== '' ? 'removeCls' : 'addCls']('disabled');
                                this.up('#leftcol').refreshStoreFilter();
                            },
                            buffer: 300
                        },
                        specialkey: function(comp, e) {
                            if (e.getKey() === e.ENTER && !this.btnSearchAll.hasCls('disabled')) {
                                this.up('#leftcol').createSearch();
                            }
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
            //overflowY: 'auto',
            //overflowX: 'hidden',
            items: {
                xtype: 'form',
                itemId: 'addrole',
                hidden: true,
                overflowY: 'auto',
                overflowX: 'hidden',
                //autoScroll: true,
                layout: 'auto',
                state: {},
                plugins: {
                    ptype: 'panelscrollfix',
                    pluginId: 'panelscrollfix'
                },
                items: [{
                    xtype: 'fieldset',
                    itemId: 'main',
                    headerCls: 'x-fieldset-separator-bottom',
                    style: 'padding-bottom: 0',
                    items: [{
                        xtype: 'container',
                        itemId: 'imageinfo',
                        maxWidth: 760,
                        margin: 0,
                        hidden: true,
                        layout: {
                            type: 'hbox',
                            align: 'stretch'
                        },
                        defaults: {
                            flex: 1
                        },
                        items: [{
                            xtype: 'container',
                            padding: '22 32 18 0',
                            defaults: {
                                labelWidth: 60
                            },
                            items: [{
                                xtype: 'displayfield',
                                fieldLabel: 'Cloud',
                                name: 'display_platform'
                            },{
                                xtype: 'displayfield',
                                fieldLabel: 'Location',
                                name: 'display_location'
                            }]
                        },{
                            xtype: 'container',
                            cls: 'x-fieldset-separator-left',
                            padding: '22 0 18 32',
                            layout: 'anchor',
                            defaults: {
                                labelWidth:90,
                                anchor: '100%'
                            },
                            items: [{
                                xtype: 'displayfield',
                                fieldLabel: 'OS',
                                name: 'display_os_name'
                            },{
                                xtype: 'displayfield',
                                fieldLabel: 'Automation',
                                name: 'display_behaviors'
                            },{
                                xtype: 'displayfield',
                                fieldLabel: 'Root device type',
                                name: 'display_root_device_type',
                                hidden: true
                            },{
                                xtype: 'textfield',
                                name: 'alias',
                                fieldLabel: 'Alias'
                            }]
                        }]
                    },{
                        xtype: 'container',
                        itemId: 'imageoptions',
                        margin: 0,
                        maxWidth: 760,
                        hidden: true,
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
                                anchor: '100%'
                            },
                            padding: '22 32 18 0',
                            items: [{
                                xtype: 'label',
                                text: 'Cloud:'
                            },{
                                xtype: 'buttongroupfield',
                                name: 'platform',
                                margin: '8 0',
                                baseCls: '',
                                defaults: {
                                    xtype: 'button',
                                    ui: 'simple',
                                    margin: '0 6 6 0'
                                },
                                listeners: {
                                    change: function(comp, value){
                                        this.up('form').fireEvent('selectplatform', value);
                                    }
                                }
                            },{
                                xtype: 'container',
                                layout: {
                                    type: 'hbox',
                                    pack: 'center'
                                },
                                margin: '0 0 10 0',
                                items: {
                                    xtype: 'cloudlocationmap',
                                    itemId: 'locationmap',
                                    listeners: {
                                        selectlocation: function(location, state){
                                            this.up('form').getForm().findField('cloud_location').setValue(location);
                                        }
                                    }
                                }
                            },{
                                xtype: 'combo',
                                name: 'cloud_location',
                                editable: false,
                                fieldLabel: 'Location',
                                labelWidth: 70,
                                anchor: '100%',
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
                            }]
                        },{
                            xtype: 'container',
                            itemId: 'rightcol',
                            padding: '22 0 18 32',
                            cls: 'x-fieldset-separator-left',
                            layout: 'anchor',
                            defaults: {
                                anchor: '100%',
                                labelWidth: 90
                            },
                            items: [{
                                xtype: 'container',
                                itemId: 'osfilters',
                                layout: 'anchor',
                                margin: '0 0 8 0',
                                defaults: {
                                    anchor: '100%',
                                    labelWidth: 80
                                },
                                items: [{
                                    xtype: 'label',
                                    text: 'Operating system:'
                                },{
                                    xtype: 'buttongroupfield',
                                    name: 'osfamily',
                                    margin: '8 0 12',
                                    baseCls: '',
                                    defaults: {
                                        xtype: 'button',
                                        ui: 'simple',
                                        margin: '0 6 6 0'
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
                                    xtype: 'container',
                                    margin: '14 0 0 0',
                                    layout: 'hbox',
                                    items: [{
                                        xtype: 'displayfield',
                                        name: 'archtext',
                                        fieldLabel: 'Architecture',
                                        labelWidth: 90,
                                        maxWidth: 230,
                                        margin: '0 12 0 0',
                                        hidden: true
                                    },{
                                        xtype: 'buttongroupfield',
                                        name: 'arch',
                                        fieldLabel: 'Architecture',
                                        labelWidth: 90,
                                        maxWidth: 230,
                                        margin: '0 12 0 0',
                                        flex: 1,
                                        layout: 'hbox',
                                        defaults: {
                                            flex: 1
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
                                        flex: .3,
                                        maxWidth: 70,
                                        text: 'HVM',
                                        enableToggle: true,
                                        toggleHandler: function() {
                                            var form = this.up('form');
                                            if (form.mode === 'shared') {
                                                form.fireEvent('selecthvm', this.pressed ? 1 : 0);
                                            }
                                        }
                                    }]
                                },{
                                    xtype: 'combo',
                                    name: 'roleid',
                                    editable: false,
                                    fieldLabel: 'Role',
                                    valueField: 'id',
                                    displayField: 'name',
                                    queryMode: 'local',
                                    hidden: true,
                                    margin: '14 0 0 0',
                                    store: {
                                        fields: ['id', 'name']
                                    },
                                    listeners: {
                                        change: function(comp, value) {
                                            this.up('form').fireEvent('selectroleid', value);
                                        }
                                    }
                                }]
                            },{
                                xtype: 'displayfield',
                                fieldLabel: 'OS',
                                name: 'display_os_name'
                            },{
                                xtype: 'displayfield',
                                fieldLabel: 'Automation',
                                name: 'display_behaviors'
                            },{
                                xtype: 'textfield',
                                name: 'alias',
                                fieldLabel: 'Alias'
                            },{
                                xtype: 'displayfield',
                                fieldLabel: 'Root device type',
                                name: 'display_root_device_type',
                                hidden: true
                            }]
                        }]
                    }]
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
                                values = {},
                                record = form.getRecord(),
                                role = formPanel.getCurrentRole(),
                                image,
                                isValid,
                                instTypeName,
                                instTypeField,
                                instTypeRec,
                                cloudLocation;

                            isValid = formPanel.isExtraSettingsValid(record);
                            if (isValid !== true) {
                                if (isValid && isValid.comp.inputEl) {
                                    isValid.comp.inputEl.scrollIntoView(this.up('#addrole').body.el, false, false);
                                    Scalr.message.InfoTip(isValid.message || isValid.comp.getErrors().join('.'), isValid.comp.inputEl, {anchor: 'bottom'});
                                }
                                return;
                            }
                            cloudLocation = form.findField('cloud_location').getValue();

                            values.platform = form.findField('platform').getValue();
                            values.cloud_location = cloudLocation;
                            values.alias = formPanel.down(formPanel.down('#imageoptions').isVisible() ? '#imageoptions' : '#imageinfo').down('[name="alias"]').getValue();
                            if (values.platform === 'ecs' && !values.cloud_location) {
                                Scalr.message.InfoTip('Cloud location is required field', form.findField('cloud_location').inputEl, {anchor: 'bottom'});
                                return;
                            }
                            
                            image = role.images[values.platform][values.platform === 'gce' || values.platform === 'ecs' ? '' : values.cloud_location];
                            if (formPanel.mode === 'shared') {
                                Ext.apply(values, {
                                    behaviors: role.behaviors,
                                    role_id: role.role_id,
                                    generation: role.generation,
                                    os: role.os_name,
                                    os_name: role.os_name,
                                    os_family: role.os_family,
                                    os_generation: role.os_generation,
                                    os_version: role.os_version,
                                    name: role.name,
                                    cat_id: role.cat_id
                                });
                            }

                            Ext.apply(values, {
                                settings: formPanel.getExtraSettings(record) || {}
                            });

                            //some gce region/zone magic
                            if (values.platform === 'gce') {
                                values.cloud_location = values['settings']['gce.region'];
                                values['settings']['gce.region'] = cloudLocation;
                            }
                            record.set(values);

                            //save instance type name
                            instTypeName = record.getInstanceTypeParamName();
                            instTypeField = form.findField(instTypeName);
                            if (instTypeField) {
                                instTypeRec = instTypeField.findRecordByValue(values['settings'][instTypeName]);
                                record.get('settings', true)['info.instance_type_name'] = instTypeRec ? instTypeRec.get('name') : values['settings'][instTypeName];
                            }

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

                    if (removeEc2) {
                        Ext.Array.remove(platforms, 'ec2');
                    }

                    var platformsSorted = [];
                    Ext.Object.each(Scalr.platforms, function(key, platform){
                        if (Ext.Array.contains(platforms, key) && platform['enabled']) {
                            platformsSorted.push(key);
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

                setupInstanceTypeField: function(data, status, container, record, callback) {
                    var me = this,
                        field = container.down('[name="' + container.instanceTypeFieldName + '"]'),
                        limits = me.up('#farmbuilder').getLimits(record.get('platform'), container.instanceTypeFieldName),
                        instanceType = record.getInstanceType(data, limits),
                        instanceTypeList = instanceType['list'] || [];

                    //field.setDisabled(!status);
                    field.store.load({ data: instanceTypeList });
                    field.setValue(instanceType['value']);
                    field.setReadOnly(instanceType['allowedInstanceTypeCount'] < 2);
                    field.toggleIcon('governance', !!limits);
                    field.emptyText = instanceType['allowedInstanceTypeCount'] ? ' ' : 'No suitable instance type';
                    field.applyEmptyText();
                    field.updateListEmptyText({cloudLocation:record.get('cloud_location'), limits: !!limits});

                    if(callback) callback.call(container, record);
                },

                listeners: {
                    beforerender: function() {
                        var me = this,
                            ext = ['ec2', 'euca', 'vpc', 'rackspace', 'openstack', 'cloudstack', 'gce', 'mongodb', 'dbmsr', 'haproxy', 'proxy', 'chef'];
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
                                    if (!Ext.isEmpty(data.regions)) {
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
                        } else if (value === 'ecs') {
                            Scalr.cachedRequest.load(
                                {
                                    url: '/platforms/openstack/xGetCloudLocations',
                                    params: {
                                        platform: value
                                    }
                                },
                                function(data, status){
                                    if (!Ext.isEmpty(data.locations)) {
                                        Ext.Object.each(data.locations, function(key, value) {
                                            defaultLocation = defaultLocation || key;
                                            locations.push({id: key, name: value});
                                        });
                                    } else {
                                        
                                    }
                                    cb(locations, defaultLocation || '');
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
                        this.suspendLayouts();
                        var me = this,
                            form = me.getForm(),
                            locations = [],
                            osFamilyField,
                            osFamilies,
                            defaultOsFamily,
                            imagesLocation;

                        me.state.location = value;
                        imagesLocation = me.state.platform === 'ecs' || me.state.platform === 'gce' ? '' : me.state.location;
                        
                        form.findField('cloud_location').store.data.each(function(){locations.push(this.get('id'))});
                        me.down('#locationmap').selectLocation(me.state.platform, me.state.location, locations, 'world');

                        if (me.mode === 'shared') {
                            //fill os families
                            osFamilyField = form.findField('osfamily');
                            osFamilies = [];
                            Ext.Array.each(me.currentRole.get('roles'), function(role){
                                if (role.images[me.state.platform] && role.images[me.state.platform][imagesLocation]) {
                                    Ext.Array.include(osFamilies, (role.os_family || 'unknown'));
                                    defaultOsFamily = defaultOsFamily || (role.os_family || 'unknown');
                                    if ((role.os_family || 'unknown') === 'ubuntu') {
                                        defaultOsFamily = 'ubuntu';
                                    }

                                }
                            });
                            osFamilyField.reset();
                            osFamilyField.removeAll();
                            osFamilies = Ext.Array.sort(osFamilies);
                            for (var i=0, len=osFamilies.length; i<len; i++) {
                                osFamilyField.add({
                                    value: osFamilies[i] || 'unknown',
                                    cls: 'x-btn-simple-medium x-icon-osfamily x-icon-osfamily-' + (osFamilies[i] || 'unknown'),
                                    tooltip: Scalr.utils.beautifyOsFamily(osFamilies[i]) || 'Unknown',
                                    tooltipType: 'title'
                                });
                            }
                            osFamilyField.setValue(defaultOsFamily);

                        } else {
                            this.fireEvent('roleimagechange');
                        }
                        this.resumeLayouts(true);
                    },

                    selectosfamily: function(value) {
                        if (value === null || value === undefined) return;
                        var me = this,
                            form = me.getForm(),
                            osNameField,
                            osNames,
                            selectedRole,
                            imagesLocation = me.state.platform === 'ecs' || me.state.platform === 'gce' ? '' : me.state.location;
                        this.suspendLayouts();
                        me.state.osfamily = value;

                        osNameField = form.findField('osname');
                        osNames = [];
                        Ext.Array.each(me.currentRole.get('roles'), function(role){
                            if (role.images[me.state.platform] && role.images[me.state.platform][imagesLocation] && (role.os_family || 'unknown') === me.state.osfamily) {
                                Ext.Array.include(osNames, role.os_name);
                                if (selectedRole === undefined || (parseFloat(selectedRole.os_version) || 0) <  (parseFloat(role.os_version) || 0)) {
                                    selectedRole = role;
                                }

                            }
                        });
                        osNames = Ext.Array.map(osNames, function(osname) {
                            return {id: osname, name: osname};
                        });
                        osNameField.reset();
                        osNameField.store.loadData(osNames);
                        osNameField.setValue(selectedRole !== undefined ? selectedRole.os_name : null);
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
                            imagesLocation = me.state.platform === 'ecs' || me.state.platform === 'gce' ? '' : me.state.location;
                        this.suspendLayouts();
                        me.state.osname = value;

                        Ext.Array.each(me.currentRole.get('roles'), function(role){
                            if (role.images[me.state.platform] && role.images[me.state.platform][imagesLocation] &&
                               (role.os_family || 'unknown') === me.state.osfamily && role.os_name === me.state.osname) {
                                var arch = role.images[me.state.platform][imagesLocation].architecture;
                                archs[arch] = 1;
                                defaultArch = defaultArch || arch;
                                if (arch === 'x86_64') {
                                    defaultArch = 'x86_64';
                                }
                            }
                        });
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
                            imagesLocation = me.state.platform === 'ecs' || me.state.platform === 'gce' ? '' : me.state.location;
                        this.suspendLayouts();
                        me.state.arch = value;
                        Ext.Array.each(me.currentRole.get('roles'), function(role){
                            if (    
                                role.images[me.state.platform] && role.images[me.state.platform][imagesLocation] &&
                                role.images[me.state.platform][imagesLocation]['architecture'] === me.state.arch &&
                                (role.os_family || 'unknown') === me.state.osfamily && role.os_name === me.state.osname
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
                        this.resumeLayouts(true);
                    },

                    selecthvm: function(value) {
                        var me = this,
                            form = me.getForm(),
                            imagesLocation = me.state.platform === 'ecs' || me.state.platform === 'gce' ? '' : me.state.location;
                            
                        if (form.getRecord().store !== undefined) return;//buttonfield doesn't work like normal form field - here is workaround
                        
                        var roleField = form.findField('roleid'),
                            roles = [],
                            defaultRole;
                        this.suspendLayouts();
                        me.state.hvm = value;

                        Ext.Array.each(me.currentRole.get('roles'), function(role){
                            if (
                                role.images[me.state.platform] && role.images[me.state.platform][imagesLocation] &&
                                role.images[me.state.platform][imagesLocation]['architecture'] === me.state.arch &&
                                (role.os_family || 'unknown') === me.state.osfamily && role.os_name === me.state.osname &&
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
                        
                        //roleField.setVisible(roles.length > 1);
                        this.resumeLayouts(true);
                    },

                    selectroleid: function(value) {
                        if (value === null || value === undefined) return;
                        var me = this,
                            imageoptions = me.down('#imageoptions'),
                            behaviorsNames = me.up('roleslibrary').moduleParams.behaviors,
                            role,
                            behaviors = [];
                        this.suspendLayouts();
                        me.state.roleid = value;

                        role = me.getCurrentRole();
                        if (role.behaviors) {
                            Ext.Array.each(role.behaviors, function(b) {
                               behaviors.push(behaviorsNames[b] || b); 
                            });
                        }
                        imageoptions.down('[name="display_behaviors"]').setValue(behaviors.join(', '));

                        this.fireEvent('roleimagechange');
                        this.resumeLayouts(true);
                    },

                    roleimagechange: function() {
                        var me = this,
                            form = me.getForm(),
                            record = form.getRecord(),
                            role = me.getCurrentRole(),
                            image = role.images[me.state.platform][me.state.platform === 'ecs' || me.state.platform === 'gce' ? '' : me.state.location],
                            values = {
                                platform: me.state.platform,
                                cloud_location: me.state.location,
                                role_id: role.role_id,
                                origin: role.origin,
                                image: Ext.clone(image),
                                settings: {}
                            },
                            imageOptions = me.down('#imageoptions');
                        
                        imageOptions = imageOptions.isVisible() ? imageOptions : me.down('#imageinfo');

                        if (me.mode === 'shared') {
                            var farmVariables = me.up('#farmbuilder').down('#variables').farmVariables;
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

                            values.os = role.os_name;//bw compatibility
                            values.os_name = role.os_name;
                            values.os_family = role.os_family;
                            values.os_generation = role.os_generation;
                            values.behaviors = role.behaviors;
                        }
                        record.set(values);
                        
                        me.suspendLayouts();
                        me.getComponent('main').setTitle(me.mode === 'shared' ? Scalr.utils.beautifySoftware(record.get('name')) : role.name, role.description);

                        //we must get unique alias from farmrolesstore
                        var beforeSetAliasResult = {};
                        me.up('roleslibrary').fireEvent('beforesetalias', role.name, beforeSetAliasResult);
                        
                        var extraValues = {
                            alias: beforeSetAliasResult.alias
                        };
                        if (me.mode === 'custom') {
                            var arch = record.get('image', true)['architecture'];
                            extraValues['display_os_name'] = '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-osfamily-small x-icon-osfamily-small-' + (record.get('os_family') || 'unknown') + '"/>&nbsp;' + record.get('os_name') + (arch ? '&nbsp;(' + (arch === 'i386' ? '32' : '64') + 'bit)' : '');
                        }
                        imageOptions.setFieldValues(extraValues);

                        //toggle extra settings fieldsets
                        var disableAddToFarmButton = false;
                        this.items.each(function(item){
                            if (item.isExtraSettings === true) {
                                if (item.suspendUpdateEvent !== undefined) {
                                    item.suspendUpdateEvent++;
                                }
                                if (item.isVisibleForRole(record)) {
                                    callback = function(record) {
                                        var result = this.setRole !== undefined ? this.setRole(record) : undefined;
                                        this.show();
                                        return result;
                                    };
                                    if (item.instanceTypeFieldName) {
                                        var platform = record.get('platform'),
                                            cloudLocation = record.get('cloud_location');
                                        if (platform !== 'gce') {
                                            Scalr.loadInstanceTypes(platform, cloudLocation, Ext.bind(me.setupInstanceTypeField, me, [item, record, callback], true));
                                        } else {
                                            callback.call(item, record);
                                        }
                                    } else {
                                        disableAddToFarmButton = callback.call(item, record) === false || disableAddToFarmButton;
                                    }
                                } else {
                                    item.hide();
                                }
                                if (item.suspendUpdateEvent !== undefined) {
                                    item.suspendUpdateEvent--;
                                }

                            }
                        });
                        me.down('#save').setDisabled(disableAddToFarmButton);
                        me.resumeLayouts(true);
                    },

                    beforeloadrecord: function(record) {
                        this.isLoading = true;
                        this.mode = this.up('roleslibrary').mode;
                        var form = this.getForm(),
                            platformField = form.findField('platform'),
                            rolePlatforms = this.getAvailablePlatforms();

                        this.imagesCount = this.getAvailableImagesCount();

                        form.reset();
                        this.suspendLayouts();

                        if (this.imagesCount > 1 || this.mode === 'shared' || Ext.Array.contains(rolePlatforms, 'gce') || Ext.Array.contains(rolePlatforms, 'ecs')) {
                            var imageOptions = this.down('#imageoptions');
                            this.down('#imageinfo').hide();
                            imageOptions.down('#osfilters').setVisible(this.mode === 'shared');
                            imageOptions.down('[name="display_os_name"]').setVisible(this.mode !== 'shared');
                            imageOptions.show();
                        } else {
                            this.down('#imageoptions').hide();
                            this.down('#imageinfo').show();
                        }
                        //fill platforms
                        platformField.removeAll();
                        for (var i=0, len=rolePlatforms.length; i<len; i++) {
                            platformField.add({
                                value: rolePlatforms[i],
                                cls: 'x-btn-simple-medium x-icon-platform x-icon-platform-' + rolePlatforms[i],
                                tooltip: Scalr.platforms[rolePlatforms[i]] ? Scalr.platforms[rolePlatforms[i]].name : rolePlatforms[i],
                                tooltipType: 'title'
                            });
                        }

                        var farmVariables = this.up('#farmbuilder').down('#variables').farmVariables;
                        var roleVariables = Ext.clone(this.currentRole.get('variables'));

                        /*
                        Ext.Array.each(roleVariables, function (roleVariable) {
                            Ext.Array.each(farmVariables, function (farmVariable, index, farmVariablesItSelf) {
                                if (roleVariable.name === farmVariable.name) {
                                    var farmValue = farmVariable.default.value;
                                    var roleLocked = roleVariable.locked;

                                    if (farmValue) {
                                        roleVariable.default = {
                                            name: roleVariable.name,
                                            value: !(roleLocked && parseInt(roleLocked.flagHidden)) ? farmValue : '******',
                                            scope: 'farm'
                                        };

                                        roleVariable.scopes.push('farm');
                                    }

                                    farmVariablesItSelf.splice(index, 1);
                                    return false;
                                }
                            });
                        });
                        */

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
                            os: this.currentRole.get('os_name'),
                            os_name: this.currentRole.get('os_name'),
                            os_family: this.currentRole.get('os_family'),
                            os_generation: this.currentRole.get('os_generation'),
                            os_version: this.currentRole.get('os_version'),
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
                            behaviorsNames = roleslibrary.moduleParams.behaviors,
                            behaviors = [],
                            platform,
                            leftcol = roleslibrary.down('#leftcol'),
                            platformFilterValue = leftcol.getFilterValue('platform'),
                            osFilterValue = leftcol.getFilterValue('os');

                        if (osFilterValue) {
                            this.state.osfamily = osFilterValue;
                        }

                        if (platformFilterValue) {
                            platform = platformFilterValue;
                        } else if (this.state.platform && Ext.Array.contains(rolePlatforms, this.state.platform)) {
                            platform = this.state.platform;
                        } else if (rolePlatforms[0]) {
                            platform = rolePlatforms[0];
                        }

                        form.findField('platform').setValue(platform);

                        Ext.Array.each(record.get('behaviors'), function(b) {
                           behaviors.push(behaviorsNames[b] || b); 
                        });
                        
                        if (this.mode === 'custom') {
                            this.down(this.down('#imageoptions').isVisible() ? '#imageoptions' : '#imageinfo').setFieldValues({
                                'display_behaviors': behaviors.join(', '),
                                'display_platform': '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-platform-small x-icon-platform-small-' + rolePlatforms[0] + '"/>&nbsp;' + (Scalr.platforms[rolePlatforms[0]] ? Scalr.platforms[rolePlatforms[0]].name : rolePlatforms[0]),
                                'display_location': Ext.Object.getKeys(this.currentRole.get('images')[rolePlatforms[0]]).join(', ')
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
		})
	},
	
	adjustWidth: function(){
		var rightcol = this.client,
			leftcol = rightcol.prev(),
            container = leftcol.ownerCt,
            rightColMinWidth = 640,
            extraWidth = 13,
			rowLength = Math.floor((container.getWidth() - rightColMinWidth - extraWidth - container.getDockedComponent('tabs').getWidth())/112);
            
        if (rowLength > 6) {
            rowLength = 6;
        } else if (rowLength < 3) {
            rowLength = 3;
        }
        
        this.resizeInProgress = true;
        leftcol.setWidth(rowLength*112 + extraWidth);
        this.resizeInProgress = false;
	}
	
});

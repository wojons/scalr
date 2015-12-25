Scalr.regPage('Scalr.ui.roles.edit', function (loadParams, moduleParams) {
    var iconCls = 'x-icon-leftmenu',
        tabsConfig = [
            {name: 'overview', title: 'Role overview', cls: 'x-btn-tab-small-dark'},
            {name: 'images', title: 'Images'},
            {name: 'scripting', title: 'Orchestration'},
            {name: 'variables', title: '<span style="position:relative;top:-10px">Global<br/>variables</span>'}
        ];
    tabsConfig.push({name: 'chef', title: 'Chef', cls: 'x-btn-tab-small-dark', hidden: !Ext.Array.contains(moduleParams['role']['behaviors'], 'chef')});
    if (Scalr.scope === 'account') {
        tabsConfig.push({name: 'environments', title: 'Permissions', cls: 'x-btn-tab-small-dark'});
    }

    if (!Ext.isArray(moduleParams['role']['images'])) {
        moduleParams['role']['images'] = [];
    }
    if (!Ext.isArray(moduleParams['role']['scripts'])) {
        moduleParams['role']['scripts'] = [];
    }

    if (loadParams['image']) {
        moduleParams['role']['images'].push({
            hash: loadParams['image']['hash'],
            imageId: loadParams['image']['id'],
            platform: loadParams['image']['platform'],
            cloudLocation: loadParams['image']['cloudLocation'],
            name: loadParams['image']['name'],
            extended: loadParams['image']
        });

        if (moduleParams['role']['osId']) {
            if (loadParams['image']['osId'] != moduleParams['role']['osId']) {
                Scalr.message.Warning('OS versions of image and role are different');
            }
        } else {
            moduleParams['role']['osId'] = loadParams['image']['osId'];
        }
    }

    var panel = Ext.create('Ext.panel.Panel', {
        scalrOptions: {
            maximize: 'all',
            menuParentStateId: 'grid-roles-manager',
            menuHref: '#' + Scalr.utils.getUrlPrefix() + '/roles',
            menuTitle: 'Roles',
            menuSubTitle: 'Role Editor',
            menuFavorite: Scalr.scope === 'environment'
        },
        layout: 'card',
        minWidth: 1200,
        dockedItems: [{
            xtype: 'container',
            itemId: 'tabs',
            dock: 'left',
            cls: 'x-docked-tabs',
            overflowY: 'auto',
            width: 112 + Ext.getScrollbarSize().width,
            defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'top',
                disableMouseDownPressed: true,
                toggleGroup: 'roledesigner-tabs',
                toggleHandler: function(field, state) {
                    if (state) {
                        panel.showTab(this.tabId);
                        panel.getComponent(this.tabId).fireEvent('showtab', moduleParams);
                    } else {
                        panel.getComponent(this.tabId).fireEvent('hidetab', moduleParams);
                    }
                }
            },
            items: Ext.Array.map(tabsConfig, function(tab) {
                return {
                    text: tab.title,
                    tabId: tab.name,
                    cls: tab.cls || '',
                    hidden: !!tab.hidden,
                    iconCls: iconCls + ' ' + iconCls + '-' + tab.name
                };
            })
        },{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons-mini',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'Save',
                handler: function() {
                    var me = this,
                        valid = true,
                        error,
                        roleParams = moduleParams['role'],
                        tabButton;
                    panel.items.each(function(item){
                        if (Ext.isFunction(item.getSubmitValues)) {
                            tabButton = panel.getDockedComponent('tabs').down('[tabId="'+item.itemId+'"]');
                            if (tabButton.isVisible()) {
                                if (Ext.isFunction(item.isValid) && (error = item.isValid(roleParams)) !== true) {
                                    tabButton.toggle(true);
                                    Scalr.message.Flush();
                                    Scalr.message.Error('Please fix errors on "' + tabButton.text + '" tab before saving' + (error ? ': <br>' + error : '.'));
                                    panel.layout.setActiveItem(item);
                                    valid = false;
                                    return false;
                                } else {
                                    Ext.apply(roleParams, item.getSubmitValues());
                                }
                            }
                        }
                    });
                    if (valid) {
                        var params = Ext.apply({}, roleParams);
                        Ext.Object.each(params, function(key, value){
                            if (!Ext.isPrimitive(params[key])) {
                                params[key] = Ext.encode(value);
                            }
                        });
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/roles/xSave/',
                            params: params,
                            success: function (data) {
                                if (params.roleId == 0) {
                                    Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + '/roles?roleId=' + data.role.id);
                                } else {
                                    Scalr.event.fireEvent('update', '/roles/edit', data.role);
                                    Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + '/roles');
                                }
                            }
                        });
                    }
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }],
        items: [{xtype: 'component'}],
        showTab: function(itemId) {
            var tab = this.getComponent(itemId);
            if (!tab) {
                tab = this.add({
                    xtype: 'roleedit' + itemId,
                    itemId: itemId,
                    listeners: {
                        addimage: function() {
                            panel.getDockedComponent('tabs').down('[tabId="images"]').toggle(true);
                            panel.getComponent('images').addImage();
                        },
                        addscript: function() {
                            panel.getDockedComponent('tabs').down('[tabId="scripting"]').toggle(true);
                            //panel.getComponent('images').addImage();
                        },
                        editchef: function() {
                            panel.getDockedComponent('tabs').down('[tabId="chef"]').toggle(true);
                        },
                        behaviorschange: function(behaviors) {
                            var isChefBehaviorEnabled = Ext.Array.contains(behaviors, 'chef');
                            moduleParams['role']['behaviors'] = behaviors;
                            panel.getDockedComponent('tabs').down('[tabId="chef"]').setVisible(isChefBehaviorEnabled);
                            if (!isChefBehaviorEnabled) {
                                delete moduleParams['role']['chef'];
                            }
                            this.down('#chefPanel').refreshChefSettings(moduleParams);
                        },
                        osidchange: function(osId) {
                            moduleParams['role']['osId'] = osId;
                            this.down('#scripts').refreshScripts(moduleParams);
                        }
                    }
                });
            }
            this.layout.setActiveItem(itemId);
        },
        listeners: {
            afterrender: {
                fn: function() {
                    this.getDockedComponent('tabs').items.first().toggle(true);
                },
                single: true
            }
        }
    });

    return panel;
});

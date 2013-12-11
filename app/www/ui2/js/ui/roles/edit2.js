Scalr.regPage('Scalr.ui.roles.edit2', function (loadParams, moduleParams) {
    var iconCls = 'scalr-ui-role-edit-icon',
        tabsConfig = [
            {name: 'overview', title: 'Role overview'},
            {name: 'images', title: 'Images'},
            {name: 'scripting', title: 'Orchestration'}
        ];
    if (Scalr.user['type'] !== 'ScalrAdmin') {
        tabsConfig.push({name: 'variables', title: 'Global variables'});
    }
    tabsConfig.push({name: 'chef', title: 'Chef'});

    if (!Ext.isArray(moduleParams['role']['images'])) {
        moduleParams['role']['images'] = [];
    }
    if (!Ext.isArray(moduleParams['role']['scripts'])) {
        moduleParams['role']['scripts'] = [];
    }

	var panel = Ext.create('Ext.panel.Panel', {
        title: moduleParams['role']['roleId'] ? 'Roles &raquo; Edit &raquo; ' + moduleParams.role.name : 'Roles &raquo; Create',
		scalrOptions: {
			maximize: 'all'
		},
        layout: 'card',
        minWidth: 900,
		dockedItems: [{
            xtype: 'container',
            itemId: 'tabs',
            dock: 'left',
            cls: 'x-docked-tabs',
            width: 112,
            defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'above',
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
                        params = moduleParams['role'],
                        tabButton;
                    panel.items.each(function(item){
                        if (Ext.isFunction(item.isValid) && !item.isValid(params)) {
                            tabButton = panel.getDockedComponent('tabs').down('[tabId="'+item.itemId+'"]');
                            tabButton.toggle(true);
                            Scalr.message.InfoTip('<span style="color:red">Please fix "' + tabButton.text + '" errors before saving.</span>', me.getEl());
                            panel.layout.setActiveItem(item);
                            valid = false;
                            return false;
                        } else {
                            if (Ext.isFunction(item.getSubmitValues)) {
                                Ext.apply(params, item.getSubmitValues());
                            }
                        }
                    });
                    if (valid) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/roles/xSave2/',
                            params: params,
                            success: function () {
                                Scalr.event.fireEvent('close');
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

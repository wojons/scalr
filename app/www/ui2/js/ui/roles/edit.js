Scalr.regPage('Scalr.ui.roles.edit', function (loadParams, moduleParams) {
    var iconCls = 'scalr-ui-role-edit-icon',
        tabsConfig = [
            {name: 'overview', title: 'Role overview'},
            {name: 'images', title: 'Images'},
            {name: 'scripting', title: 'Orchestration'},
            {name: 'variables', title: 'Global variables'}
        ];
    if (Scalr.user['type'] !== 'ScalrAdmin') {
        if (Ext.Array.contains(moduleParams['role']['behaviors'], 'chef')) {
            tabsConfig.push({name: 'chef', title: 'Chef'});
        }
    }

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

                        if (! moduleParams['role']['roleId']) {
                            // TODO: find another solution
                            moduleParams['role']['osFamily'] = panel.getComponent('overview').down('[name="osFamily"]').getValue();
                            moduleParams['role']['osVersion'] = panel.getComponent('overview').down('[name="osVersion"]').getValue();
                        }

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
                        roleParams = moduleParams['role'],
                        tabButton;
                    panel.items.each(function(item){
                        if (Ext.isFunction(item.isValid) && !item.isValid(roleParams)) {
                            tabButton = panel.getDockedComponent('tabs').down('[tabId="'+item.itemId+'"]');
                            tabButton.toggle(true);
                            Scalr.message.Flush();
                            Scalr.message.Error('Please fix errors on "' + tabButton.text + '" tab before saving.');
                            panel.layout.setActiveItem(item);
                            valid = false;
                            return false;
                        } else {
                            if (Ext.isFunction(item.getSubmitValues)) {
                                Ext.apply(roleParams, item.getSubmitValues());
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
                                Scalr.event.fireEvent('update', '/roles/edit', data.role);
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

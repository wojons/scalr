Scalr.regPage('Scalr.ui.account2.orchestration.view', function (loadParams, moduleParams) {
    saveHandler = function(cb) {
        var scripts = panel.down('#scripting').getRoleScripts(),
            scripting = [];

        scripts.each(function(item) {
            var script = item.getData();
            script['event_name'] = script['event'];
            scripting.push(script);
        });

        Scalr.Request({
            processBox: {
                type: 'save'
            },
            url: '/account/orchestration/xSave/',
            params: {
                orchestrationRules: Ext.encode(scripting)
            },
            success: function () {
                Scalr.message.Flush(true);
                cb ? cb() : Scalr.event.fireEvent('refresh');
            }
        });
    }
    
    var pageTitle = 'Account orchestration';
	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			title: pageTitle,
			maximize: 'all',
			leftMenu: {
				menuId: 'settings',
				itemId: 'orchestration',
                beforeClose: function(cb) {
                    if (panel.down('#scripting').hasDirtyRecords()) {
                        Scalr.utils.Window({
                            title: 'Orchestration rule changes',
                            layout: 'fit',
                            width: 560,
                            bodyCls: 'x-container-fieldset',
                            items: [{
                                xtype: 'displayfield',
                                cls: 'x-form-field-warning',
                                value: 'There are some unsaved changes on this page. Do you want to save them before leaving?',
                                margin: '0 0 10 0'
                            }],
                            dockedItems: [{
                                xtype: 'container',
                                cls: 'x-docked-buttons',
                                dock: 'bottom',
                                layout: {
                                    type: 'hbox',
                                    pack: 'center'
                                },
                                items: [{
                                    xtype: 'button',
                                    text: 'Save changes',
                                    handler: function() {
                                        saveHandler(cb);
                                        this.up('#box').close();
                                    }
                                }, {
                                    xtype: 'button',
                                    text: 'Ignore changes',
                                    margin: '0 0 0 10',
                                    handler: function() {
                                        cb();
                                        this.up('#box').close();
                                    }
                                }, {
                                    xtype: 'button',
                                    text: 'Continue edit',
                                    margin: '0 0 0 10',
                                    handler: function() {
                                        this.up('#box').close();
                                    }
                                }]
                            }]
                        });
                    } else {
                        cb();
                    }
                    return false;
                }
			}
		},
        layout: 'fit',
		items: [{
            xtype: 'scriptfield',
            itemId: 'scripting',
            mode: 'account'
		}],
        listeners: {
            boxready: function(){
                var rolescripting = this.down('#scripting');
                rolescripting.loadScripts(moduleParams['scriptData']['scripts'] || []);
                rolescripting.loadEvents(moduleParams['scriptData']['events'] || {});
                rolescripting.loadRoleScripts(moduleParams['orchestrationRules']);
            }
        },
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons-mini',
            weight: 10,
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: saveHandler
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});
    return panel;
});

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
                Ext.isFunction(cb) ? cb() : Scalr.event.fireEvent('refresh');
            }
        });
    }
    
	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
            menuTitle: 'Orchestration',
            menuHref: '#/account/orchestration',
            menuFavorite: true,
			maximize: 'all'
		},
        stateId: 'grid-account-orchestration',
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

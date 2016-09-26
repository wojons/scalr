Scalr.regPage('Scalr.ui.services.configurations.manage', function (loadParams, moduleParams) {
	var isConfigEmpty = Ext.Object.isEmpty(moduleParams['config']);
    
	var form = Ext.create('Ext.form.Panel', {
		width: 900,
		title: 'Services &raquo; Configurations &raquo; Manage',
        layout: 'auto',
        overflowX: 'hidden',
		items: [{
			xtype: 'fieldset',
			title: 'General information',
			labelWidth: 200,
			items:[{
				xtype: 'displayfield',
				name: 'presetName',
				fieldLabel: 'Farm & Role',
				width: 600,
				value: moduleParams['farmName']+" &raquo; "+moduleParams['roleName']
			}, {
				xtype: 'displayfield',
				fieldLabel: 'Automation',
				width: 600,
				value: moduleParams['behaviorName']
			}, {
				xtype: 'displayfield',
				name: 'masterServer',
				hidden: !moduleParams['masterServerId'],
				fieldLabel: 'Master server',
				width: 600,
				value: moduleParams['masterServerId'] ? "<a href='#/servers/"+moduleParams['masterServerId']+"/dashboard'>"+moduleParams['masterServer']['remoteIp']+" ("+moduleParams['masterServerId']+")</a>" : ""
			},{
                xtype: 'displayfield',
                hidden: moduleParams['masterServerId'] || isConfigEmpty,
                anchor: '100%',
                margin: 0,
                cls: 'x-form-field-warning',
                value: 'No running master server found. Any changed in configuration won\'t be tested and will be applied during the next instance launch.'
            }]
		}, {
			xtype: 'container',
            layout: 'anchor',
            cls: 'x-container-fieldset x-fieldset-separator-bottom',
            style: 'padding-bottom:0',
			itemId: 'optionsSet',
			items: [{
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                html: 'Configuration options'
            },{
                xtype: 'displayfield',
                hidden: moduleParams['masterServerId'] || !isConfigEmpty,
                anchor: '100%',
                margin: '0 0 24',
                cls: 'x-form-field-warning',
                value: 'No configuration found. Please launch at least one server to start managing configuration.'
            },{
                xtype: 'displayfield',
                anchor: '100%',
                margin: '0 0 24',
                cls: 'x-form-field-warning',
                value: 'Any changes made to your configuration through this interface will result in ' + moduleParams['behaviorName'] + ' being restarted after your changes are saved.'
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
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					
					var results = [];
					var configFields = form.child('#optionsSet').query('configfield');
					for (var i = 0; i < configFields.length; i++) {
						item = configFields[i];
						
						if (!item.getValue())
							form.child('#optionsSet').remove(item);
						else{
							results[results.length] = item.getValue();
							item.clearStatus();
						}
					}
					
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						form: form.getForm(),
						url: '/services/configurations/xSave/',
						params: {
							'masterServerId': moduleParams['masterServerId'], 
							'farmRoleId': moduleParams['farmRoleId'], 
							'behavior': moduleParams['behavior'], 
							'config': Ext.encode(results) 
						},
						success: function () {
							Scalr.event.fireEvent('refresh');
						}
					});
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});
	
	var optionsSet = form.down("#optionsSet");
	
	for (name in moduleParams['config']) {
		
		var itemId = name.replace(/[^a-zA-Z0-9]+/gi, '');
		
		optionsSet.add({
			xtype: 'panel',
			itemId : itemId,
			flex: 1,
            margin: '12 0 24',
            dockedItems: [{
                dock: 'top',
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                html: name
            }],
            layout: 'anchor',
            defaults: {
                anchor: '100%'
            },
            bodyStyle: 'padding-bottom:32px;',
            plugins: {
                ptype: 'addfield',
                padding: '6px 28px 0 0',
                targetEl: '.x-panel-body',
                handler: function () {
                    var scrollY = form.getScrollY();
                    this.addNewConfigfield();
                    form.setScrollY(scrollY);
                }
            },
            addNewConfigfield: function (){
                this.items.last().down('#remove').enable().show();
                this.add({
                    xtype: 'configfield',
                    configFile: name,
                    showRemoveButton: true
                });
            },
			items: []
		});
		
		for (settingName in moduleParams['config'][name]) {
			form.down('#'+itemId).add({
				showRemoveButton: true,
				notEditable: true,
				configFile: name,
				xtype: 'configfield',
				value: {key: settingName, value: moduleParams['config'][name][settingName]}
			});
		}
		
		form.down('#'+itemId).add({
			xtype: 'configfield',
			configFile: name
		});
	}
	
	//form.down("#optionsSet").

	return form;
});
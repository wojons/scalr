Scalr.regPage('Scalr.ui.account2.environments.clouds', function (loadParams, moduleParams) {
	var platformButtons = [];
    var editPlatform = function(params, data) {
        var platformFamily,
            form;
        if (Scalr.isCloudstack(params['platform'])) {
            platformFamily = 'cloudstack';
        } else if (Scalr.isOpenstack(params['platform'])) {
            platformFamily = 'openstack';
        } else {
            platformFamily = params['platform'];
        }
        form = Scalr.cache['Scalr.ui.account2.environments.clouds.' + platformFamily];
        panel.removeAll();
        panel.add(form ? form(params, data) : {xtype: 'component', cls: 'x-container-fieldset', html: 'Under construction...'});
        panel.down('#delete').setVisible(!!data['params'][params['platform'] + '.is_enabled']);
        panel.down('#buttons').setVisible(!!form);

    };
	var reconfigurePage = function(params) {
        panel.scalrOptions.reload = true;
        if (params['envId'] != loadParams['envId']) {
            Scalr.event.fireEvent('redirect', '#/account/environments/' + params['envId'] + '/clouds');
        } else if (params['platform']) {
            panel.currentPlatform = params['platform'];
            panel.getDockedComponent('tabs').down('button[platform="' + params['platform'] + '"]').toggle(true);
            Scalr.Request({
                processBox: {
                    type: 'action'
                },
                url: '/account/environments/clouds/xGetCloudParams',
                params: params,
                success: function(data){
                    editPlatform(params, data);
                }
            });
        }
	};

	var sendForm  = function(disablePlatform) {
		var frm = panel.down('form').getForm(),
			r = {
				processBox: {
					type: 'save'
				},
				form: frm,
				url: '/account/environments/' + loadParams['envId'] + '/clouds/xSaveCloudParams',
				params: {
                    beta: loadParams['beta'],
                    platform: panel.currentPlatform
                },
				success: function (data) {
					Scalr.event.fireEvent('unlock');
					if (data.demoFarm) {
						Scalr.event.fireEvent('redirect', '#/farms/view?demoFarm=1', true);
					} else {
						var flag = Scalr.flags.needEnvConfig && data.enabled;
						Scalr.event.fireEvent('update', '/account/environments/edit', loadParams['envId'], panel.currentPlatform, data.enabled);
                        Scalr.event.fireEvent('reload');
						//if (! flag)
							//Scalr.event.fireEvent('close');
					}
				}
			};
		if (disablePlatform) {
			frm.findField(panel.currentPlatform + '.is_enabled').setValue(null);
			Ext.apply(r, {
				confirmBox: {
					msg: 'Delete this cloud?',
					type: 'delete',
					ok: 'Delete'
				},
				processBox: {
					msg: 'Deleting...'
				}
			});
		} else {
			frm.findField(panel.currentPlatform + '.is_enabled').setValue('on');
			if (!frm.isValid()) return;
		}

		Scalr.Request(r);
	};


    Ext.Object.each(Scalr.platforms, function(key, value){
        loadParams['platform'] = loadParams['platform'] || key;
        platformButtons.push({
            xtype: 'button',
            cls: 'x-btn-tab-small-light' + (!Ext.Array.contains(moduleParams['enabledPlatforms'], key) ? ' scalr-ui-environment-cloud-disabled' : ''),
            ui: 'tab',
            textAlign: 'left',
            text: value.name,
            iconCls: 'x-icon-platform-small x-icon-platform-small-' + key,
            allowDepress: false,
            toggleGroup: 'environment-platforms',
            href: '#/account/environments/' + moduleParams['env']['id'] + '/clouds?platform=' + key,
            hrefTarget: '_self',
            disableMouseDownPressed: true,
            platform: key,
            listeners: {
                click: function(){panel.scalrOptions.reload = false;}
            }
        });
    });
	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			modal: true,
            reload: true
		},
		scalrReconfigure: function(params){
			reconfigurePage(params);
		},
		width: 960,
        minHeight: 590,
        //cls: 'x-panel-column-left',
		title: 'Environments &raquo; ' + moduleParams['env']['name'] + ' &raquo; Setup clouds',
        layout: 'fit',
		items: [{
            xtype: 'component'
		}],

        dockedItems: [{
            xtype: 'container',
            itemId: 'tabs',
            dock: 'left',
            cls: 'x-docked-tabs',
            width: 240,
            padding: '12 0',
            autoScroll: true,
            items: platformButtons,
            weight: 2
        },{
			xtype: 'container',
            itemId: 'buttons',
			dock: 'bottom',
			cls: 'x-docked-buttons',
            defaults:{
                flex: 1,
                maxWidth: 150
            },
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					sendForm();
				}
			}, {
				xtype: 'button',
				hidden: !!Scalr.flags.needEnvConfig,
				text: 'Close',
				handler: function() {
					Scalr.event.fireEvent('redirect', '#/account/environments?envId=' + moduleParams['env']['id']);
				}
			},{
				xtype: 'button',
                itemId: 'delete',
				cls: 'x-btn-default-small-red',
				hidden: true,
				text: 'Delete',
				handler: function() {
					sendForm(true);
				}
			}, {
				xtype: 'button',
				hidden: !Scalr.flags.needEnvConfig,
				text: 'Do this later',
				handler: function () {
					sessionStorage.setItem('needEnvConfigLater', true);
					Scalr.event.fireEvent('unlock');
					Scalr.event.fireEvent('redirect', '#/dashboard');
				}
			}]
        }],
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('redirect', '#/account/environments?envId=' + moduleParams['env']['id']);
			}
		}],
        listeners: {
			boxready: function(){
				reconfigurePage(loadParams);
			}
        }

	});

	Scalr.event.on('update', function (type, envId, platform, enabled) {console.log('cloud update')
		if (type == '/account/environments/edit') {
            var b = panel.getDockedComponent('tabs').down('[platform="' + platform + '"]');
            if (b) {
                b[(enabled ? 'removeCls' : 'addCls')]('scalr-ui-environment-cloud-disabled');
            }
		}
	}, panel);


	return panel;
});

Scalr.regPage('Scalr.ui.account2.environments.clouds', function (loadParams, moduleParams) {
    var tutorials = {
        ec2: 'https://scalr-wiki.atlassian.net/wiki/display/docs/Amazon+EC2',
        gce: 'http://wiki.scalr.com/display/docs/Google+Compute+Engine#GoogleComputeEngine-Step1:AddYourGoogleComputeEngine(GCE)Credentials',
        cloudstack: 'http://wiki.scalr.com/display/docs/CloudStack#CloudStack-Step1:AddYourCloudStackCredentials',
        idcf: 'http://wiki.scalr.com/display/docs/IDC+Frontier#IDCFrontier-Step1:AddYourIDCFrontier(IDCF)Credentials',
        openstack: 'http://wiki.scalr.com/display/docs/OpenStack#OpenStack-Step2:AddYourOpenStackCredentials',
        rackspacengus: ' http://wiki.scalr.com/display/docs/Rackspace+Open+Cloud#RackspaceOpenCloud-Step1:AddYourRackspaceCredentials',
        rackspacenguk: ' http://wiki.scalr.com/display/docs/Rackspace+Open+Cloud#RackspaceOpenCloud-Step1:AddYourRackspaceCredentials',
        nebula: 'http://wiki.scalr.com/display/docs/OpenStack#OpenStack-Step2:AddYourOpenStackCredentials',
        ocs: 'http://wiki.scalr.com/display/docs/OpenStack#OpenStack-Step2:AddYourOpenStackCredentials',
        ecs: 'http://wiki.scalr.com/display/docs/OpenStack#OpenStack-Step2:AddYourOpenStackCredentials',
        mirantis: 'http://wiki.scalr.com/display/docs/OpenStack#OpenStack-Step2:AddYourOpenStackCredentials',
        vio: 'http://wiki.scalr.com/display/docs/OpenStack#OpenStack-Step2:AddYourOpenStackCredentials',
        verizon: 'http://wiki.scalr.com/display/docs/OpenStack#OpenStack-Step2:AddYourOpenStackCredentials',
        cisco: 'http://wiki.scalr.com/display/docs/OpenStack#OpenStack-Step2:AddYourOpenStackCredentials',
        hpcloud: 'http://wiki.scalr.com/display/docs/OpenStack#OpenStack-Step2:AddYourOpenStackCredentials',
        eucalyptus: 'http://wiki.scalr.com/display/docs/Eucalyptus#Eucalyptus-Step1:AddYourEucalyptusCredentials'
    };

    var editPlatform = function(params, data) {
        var platformFamily,
            formCt = panel.getComponent('formCt'),
            form,
            platformName = Scalr.utils.getPlatformName(params['platform']);
    
        if (Scalr.isCloudstack(params['platform'])) {
            platformFamily = 'cloudstack';
        } else if (Scalr.isOpenstack(params['platform'])) {
            platformFamily = 'openstack';
        } else {
            platformFamily = params['platform'];
        }
        form = Scalr.cache['Scalr.ui.account2.environments.clouds.' + platformFamily];
        formCt.removeAll();
        if (form) {
            formCt.add(form(params, data));
            formCt.down('form').insert(0, {
                xtype: 'displayfield',
                cls: 'x-form-field-info',
                margin: '0 0 20 0',
                value: (Scalr.flags.needEnvConfig ? Scalr.strings['account.need_env_config'].replace('%platform%', platformName) : Scalr.strings['account.cloud_access.info']) + (tutorials[params['platform']] ? '<div style="margin-top:12px;"><a href="' + tutorials[params['platform']] + '" target="_blank" class="x-semibold">Getting started with Scalr and ' + platformName + ' tutorial</a></div>' : '')
            });
        } else {
            formCt.add({xtype: 'component', cls: 'x-container-fieldset', html: 'Under construction...'});
        }
        formCt.down('#delete').setVisible(!!data['params'][params['platform'] + '.is_enabled']);
        panel.down('#buttons').setVisible(!!form);

    };
	var loadPlatform = function(params) {
        panel.currentPlatform = params['platform'];
        panel.getComponent('tabs').down('button[platform="' + params['platform'] + '"]').toggle(true);
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
	};

	var sendForm  = function(disablePlatform) {
		var form = panel.down('form'),
            frm = form.getForm(),
			r = {
				processBox: {
					type: 'save'
				},
				form: frm,
				url: '/account/environments/' + loadParams['envId'] + '/clouds/xSaveCloudParams',
				params: Ext.apply({
                    beta: loadParams['beta'],
                    platform: panel.currentPlatform
                }, form.getExtraParams ? form.getExtraParams(disablePlatform) : {}),
				success: function (data) {
					Scalr.event.fireEvent('unlock');
					if (data.demoFarm) {
						Scalr.event.fireEvent('redirect', '#/farms?demoFarm=1', true);
					} else {
						Scalr.event.fireEvent('update', '/account/environments/edit', loadParams['envId'], data.envAutoEnabled, panel.currentPlatform, data.enabled);
                        editPlatform({envId: loadParams['envId'], platform: panel.currentPlatform}, data);
					}
				},
                failure: function (data, response, options) {
                    if (Ext.isFunction(form.onSaveFailure)) {
                        form.onSaveFailure(data, response, options);
                    }
                }
			};
		if (disablePlatform) {
            if (Ext.isFunction(form.onSavePlatform)) {
                form.onSavePlatform(disablePlatform);
            } else {
                frm.findField(panel.currentPlatform + '.is_enabled').setValue(null);
            }
			Ext.apply(r, {
				confirmBox: {
					msg: 'Delete this cloud credentials from Scalr?',
					type: 'delete',
					ok: 'Delete'
				},
				processBox: {
					msg: 'Deleting...'
				}
			});
		} else {
            if (Ext.isFunction(form.onSavePlatform)) {
                form.onSavePlatform(disablePlatform);
            } else {
    			frm.findField(panel.currentPlatform + '.is_enabled').setValue('on');
            }
			if (!frm.isValid()) return;
		}

		Scalr.Request(r);
	};

    var publicPlatforms = [],
        privatePlatforms = [],
        platformButtons = [];

    Ext.Object.each(Scalr.platforms, function(key, value){
        var platformEnabled = Ext.Array.contains(moduleParams['enabledPlatforms'], key);
        loadParams['platform'] = loadParams['platform'] || key;
        (value.public ? publicPlatforms : privatePlatforms).push({
            xtype: 'button',
            cls: 'x-btn-tab-no-text-transform x-btn-tab-small-light' + (!platformEnabled ? ' scalr-ui-environment-cloud-disabled' : ''),
            ui: 'tab',
            textAlign: 'left',
            text: value.name,
            iconCls: 'x-icon-platform-small x-icon-platform-small-' + key,
            allowDepress: false,
            toggleGroup: 'environment-platforms',
            //href: '#/account/environments/' + moduleParams['env']['id'] + '/clouds?platform=' + key,
            hrefTarget: '_self',
            disableMouseDownPressed: true,
            hidden: !platformEnabled && key === 'rackspace',
            platform: key,
            handler: function() {
                loadPlatform({envId: loadParams['envId'], platform: this.platform});
            }
            /*listeners: {
                click: function(){panel.scalrOptions.reload = false;}
            }*/
        });
    });

    if (publicPlatforms.length > 0) {
        platformButtons.push({
            xtype: 'component',
            html: 'Public clouds',
            cls: 'x-docked-tabs-title'
        });
        platformButtons.push({
            xtype: 'container',
            cls: 'x-docked-tabs',
            width: 'auto',
            items: publicPlatforms
        });
    }
    if (privatePlatforms.length > 0) {
        platformButtons.push({
            xtype: 'component',
            html: 'Private clouds',
            cls: 'x-docked-tabs-title'
        });
        platformButtons.push({
            xtype: 'container',
            cls: 'x-docked-tabs',
            width: 'auto',
            items: privatePlatforms
        });
    }

	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			modal: true,
            reload: true
		},
		width: 1024,
		title: 'Environments &raquo; ' + moduleParams['env']['name'] + ' &raquo; Setup clouds',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
		items: [{
            xtype: 'container',
            itemId: 'tabs',
            dock: 'left',
            width: 240,
            autoScroll: true,
            cls: 'x-docked-tabs',
            items: platformButtons
        },{
            xtype: 'panel',
            itemId: 'formCt',
            flex: 1,
            layout: {
                type: 'fit'
            },
            items: [{
                xtype: 'component'
            }],
            dockedItems: [{
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
                    text: 'Save keys',
                    handler: function() {
                        sendForm();
                    }
                }, {
                    xtype: 'button',
                    text: 'Cancel',
                    handler: function() {
                        Scalr.event.fireEvent('close');
                    }
                },{
                    xtype: 'button',
                    itemId: 'delete',
                    cls: 'x-btn-red',
                    hidden: true,
                    text: 'Delete keys',
                    handler: function() {
                        sendForm(true);
                    }
                }]
            }]
		}],

		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}],
        listeners: {
			boxready: function(){
				loadPlatform(loadParams);
			}
        }
	});

	Scalr.event.on('update', function (type, envId, envAutoEnabled, platform, enabled) {
		if (type == '/account/environments/edit') {
            var b = panel.getComponent('tabs').down('[platform="' + platform + '"]');
            if (b) {
                b[(enabled ? 'removeCls' : 'addCls')]('scalr-ui-environment-cloud-disabled');
            }
		}
	}, panel);


	return panel;
});

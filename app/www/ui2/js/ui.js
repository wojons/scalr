Ext.define('Ext.layout.container.Scalr', {
	extend: 'Ext.layout.container.Absolute',
	alias: [ 'layout.scalr' ],

	activeItem: null,
	stackItems: [],
	zIndex: 101,
	firstRun: true,

	initLayout : function() {
		if (!this.initialized) {
			var me = this;
			this.callParent();

			this.owner.on('resize', function () {
				this.onOwnResize();
			}, this);

			this.owner.items.on('add', function (index, o) {

				//c.style = c.style || {};
				//Ext.apply(c.style, { position: 'absolute' });

				//Ext.apply(c, { hidden: true });
				o.scalrOptions = o.scalrOptions || {};
				Ext.applyIf(o.scalrOptions, {
					reload: true, // close window before show other one
					modal: false, // mask prev window and show new one (false - don't mask, true - mask previous)
					maximize: '' // maximize which sides (all, (max-height - default))
                    // beforeClose: handler() // return true if we can close form or false if we should engage user attention before close
				});

				if (o.scalrOptions.modal)
					o.addCls('x-panel-shadow');

				o.scalrDestroy = function () {
					this.destroy();
					/*Ext.create('Ext.fx.Animator', {
					 target: this,
					 keyframes: {
					 '0%': {
					 opacity: 1
					 },
					 '100%': {
					 opacity: 0
					 }
					 },
					 duration: 1000,
					 listeners: {
					 afteranimate: function () {
					 this.destroy();
					 },
					 scope: this
					 }
					 });*/
				};
				if (o.scalrOptions.leftMenu) {
					var listeners = {
						beforeactivate: function() {
							me.owner.getPlugin('leftmenu').show(o.scalrOptions.leftMenu);
						},
						beforedestroy: function() {
							me.owner.getPlugin('leftmenu').hide();
						},
						hide: function() {
							me.owner.getPlugin('leftmenu').hide();
						}
					};
					o.on(listeners);
				}
		});
		}
	},

	setActiveItem: function (newPage, param) {
		var me = this,
			oldPage = this.activeItem;

		var checkPageBeforeClose = function(oldPage, newPage) {
            if (Ext.isFunction(oldPage.scalrOptions.beforeClose) && oldPage.scalrOptions.reload) {
                var callback = function() {
                    Scalr.event.fireEvent('close');
                };

                var result = oldPage.scalrOptions.beforeClose.call(oldPage, callback);
                if (result === false) {
                    // stop execution
                    if (newPage) {
                        newPage.hide();
                    }
                    return true;
                }
            }

            return false;
        };

        if (this.firstRun) {
			Ext.get('body-container').applyStyles('visibility: visible; opacity: 0.3');

			Ext.create('Ext.fx.Anim', {
				target: Ext.get('body-container'),
				duration: 1200,
				from: {
					opacity: 0.3
				},
				to: {
					opacity: 1
				},
				callback: function () {
				}
			});

			Ext.create('Ext.fx.Anim', {
				target: Ext.get('loading'),
				duration: 900,
				from: {
					opacity: 1
				},
				to: {
					opacity: 0
				},
				callback: function () {
					Ext.get('loading').remove();
				}
			});

			this.firstRun = false;
		}

		if (newPage) {
			if (oldPage != newPage) {
				if (oldPage) {
					if (oldPage.scalrOptions.modal) {
						if (newPage.scalrOptions.modal) {
							if (
								newPage.rendered &&
								(parseInt(oldPage.el.getStyles('z-index')['z-index']) == (parseInt(newPage.el.getStyles('z-index')['z-index']) + 1)))
							{
								this.zIndex--;
								//oldPage.el.unmask();
								oldPage.scalrDestroy();
							} else {
								this.zIndex++;
								oldPage.el.mask();
								oldPage.fireEvent('deactivate');
							}
						} else {
                            // check for locked page before
                            var lockFlag = false;
                            me.owner.items.each(function () {
                                if (this.rendered && this != newPage && checkPageBeforeClose(this, this == newPage ? null : newPage)) {
                                    lockFlag = true;
                                    return false;
                                }
                            });

                            if (lockFlag) {
                                return 'lock';
                            }

                            this.zIndex = 101;
                            oldPage.scalrDestroy();
							// old window - modal, a new one - no, close all windows with reload = true
							// except newPage
							if (! newPage.scalrOptions.modal) {
								me.owner.items.each(function () {
									if (this.rendered && !this.hidden && this != newPage && this.scalrOptions.modal != 'box') {
										if (this.scalrOptions.reload == true) {
											//this.el.unmask();
											this.scalrDestroy();
										} else {
											this.el.unmask();
											this.hide();
											this.fireEvent('deactivate');
										}
									}
								});
							}
						}
					} else {
						if (newPage.scalrOptions.modal) {
							oldPage.el.mask();
							oldPage.fireEvent('deactivate');
						} else {
                            if (checkPageBeforeClose(oldPage, newPage)) {
                                return 'lock';
                            }

                            if (Scalr.state.pageReloadRequired) {
                                // reload page because of changed core js files
                                Scalr.utils.CreateProcessBox({
                                    type: 'action'
                                });
                                Scalr.event.fireEvent('reload');
                                return true;
                            }

							if (oldPage.scalrOptions.reload) {
								//oldPage.el.unmask();
								oldPage.scalrDestroy();
							} else {
								oldPage.hide();
								oldPage.fireEvent('deactivate');
							}
						}
					}
				}
			} else {
				if (oldPage.scalrOptions.reload) {
					//oldPage.unmask();
					oldPage.scalrDestroy();
					this.activeItem = null;
					return false;
				}
			}

			this.activeItem = newPage;
			this.setSize(this.activeItem);

			if (! newPage.scalrOptions.modal) {
				var docTitle = this.activeItem.title || (this.activeItem.scalrOptions ? (this.activeItem.scalrOptions.title || null) : null);
				document.title = Ext.util.Format.stripTags(((docTitle ? (docTitle + ' - ') : '') + 'Scalr CMP').replace(/&raquo;/g, '»'));
			}

            this.activeItem.fireEvent('beforeactivate');
            this.activeItem.fireEvent('applyparams', param);
			this.activeItem.show();
			this.activeItem.el.unmask();
			this.activeItem.fireEvent('activate');

			this.owner.doLayout();

			if (this.activeItem.scalrOptions.modal)
				this.activeItem.el.setStyle({ 'z-index': this.zIndex });
			
			return true;
		}
	},

	setSize: function (comp) {
		var r = this.getTarget().getSize();
		var top = 0, left = 0;

		comp.doAutoRender();

		if (comp.scalrOptions.modal) {
			top = top + 5;
			r.height = r.height - 5 * 2;

			if (comp.scalrOptions.maximize == 'all') {
				left = left + 5;
				r.width = r.width - 5 * 2;
			}
		}

		if (comp.scalrOptions.maximize == 'all') {
			comp.setSize(r);
            comp.removeCls('x-panel-separated');
            comp.removeCls('x-panel-separated-modal');
		} else {
			comp.setAutoScroll(true);
			comp.maxHeight = Math.max(0, r.height - 5*2);
			left = (r.width - comp.getWidth()) / 2;

			if (comp.scalrOptions.modal) {
                comp.addCls('x-panel-separated-modal');
            } else {
                comp.addCls('x-panel-separated');
                comp.maxHeight = comp.maxHeight - 12;
            }
        }

		comp.setPosition(left, top);

		// TODO: investigate in future, while component doesn't have method updateLayout
		if (Ext.isFunction(comp.updateLayout)) {
			comp.updateLayout();
		}
	},

	onOwnResize: function () {
		if (this.activeItem) {
			this.setSize(this.activeItem);
		}
	}
});

Scalr.application = Ext.create('Ext.panel.Panel', {
	layout: 'scalr',
	border: 0,
	autoScroll: false,
	bodyCls: 'x-panel-background',
	//bodyStyle: 'border-radius: 4px',
	plugins: {
		ptype: 'leftmenu',
		pluginId: 'leftmenu'
	},
	dockedItems: [{
		xtype: 'toolbar',
		plugins: Ext.create('Ext.ux.BoxReorderer', {
			listeners: {
				drop: function(pl, cont, cmp, startIdx, endIdx) {
					// first element is menu, sub it from index
					var favorites = Scalr.storage.get('system-favorites') || [], but = favorites[startIdx - 1] || {};

					if (but) {
						Ext.Array.remove(favorites, but);
						Ext.Array.insert(favorites, endIdx - 1, [but]);
					}

					Scalr.storage.set('system-favorites', favorites);
				}
			}
		}),
		dock: 'top',
		itemId: 'top',
		enableOverflow: true,
		height: 45,
		//items: menu,
		cls: 'x-topmenu-menu',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		defaults: {
			border: false
		}
	},{
        itemId: 'globalWarning',
        dock: 'top',
        xtype: 'displayfield',
        hidden: true,
        cls: 'x-form-field-warning x-form-field-warning-fit'

    }],
	disabledDockedToolbars: function (disable, hide) {
		Ext.each(this.getDockedItems(), function (item) {
            if (disable) {
                item.disable();

                if (hide) {
                    item.wasHiddenBefore = item.isHidden();
                    if (!item.wasHiddenBefore) {
                        item.hide();
                    }
                }
            } else {
                if (item.wasHiddenBefore !== undefined) {
                    item.setVisible(!item.wasHiddenBefore);
                    delete item.wasHiddenBefore;
                }
                
                item.enable();
            }
		});
	},
	listeners: {
		add: function (cont, cmp) {
			// hack for grid, dynamic width and columns (afterrender)
			//if (cont.el && cmp.scalrOptions && cmp.scalrOptions.maximize == 'all')
			//	cmp.setWidth(cont.getWidth());
		},
		render: function () {
			this.width = Ext.Element.getViewportWidth();
			this.height = Ext.Element.getViewportHeight();
		},
		boxready: function () {
			Ext.EventManager.onWindowResize(function (width, height) {
				Scalr.application.setSize(width, height);
			});
		}
	}
});

Scalr.application.add({
	xtype: 'component',
	scalrOptions: {
		reload: false,
		maximize: 'all'
	},
	html: '&nbsp;',
	hidden: true,
	title: '',
	itemId: 'blank'
});

Scalr.application.updateContext = function(handler, onlyVars) {
	Scalr.Request({
        processBox: {
            type: 'action',
            msg: 'Loading configuration ...'
        },
		url: '/guest/xGetContext',
		headers: {
			'X-Scalr-EnvId': null, // avoid checking variable on server side,
			'X-Scalr-UserId': null
		},
		success: function(data) {
			var win = Scalr.utils.CreateProcessBox({
				type: 'action',
				msg: 'Applying configuration ...'
			});
            win.show();
			Scalr.application.applyContext(data, onlyVars);
			if (Ext.isFunction(handler))
				handler();
			win.close();
		}
	});
};

Ext.apply(Scalr.application, {
    updateMenuHandler: function (type) {
        if (type == '/account/environments/edit') {
            Scalr.application.updateContext(Ext.emptyFn, true);
        }
    },
    updateSetupHandler: function (type, envId, envAutoEnabled, platform, enabled) {
        if (type == '/account/environments/edit' && envId == Scalr.flags.needEnvConfig) {
            if (enabled) {
                Scalr.event.fireEvent('unlock');
                Scalr.event.un('update', Scalr.application.updateSetupHandler);
                Scalr.event.on('update', Scalr.application.updateMenuHandler);
                Scalr.flags.needEnvConfig = false;
                Scalr.application.updateContext(function() {
                    if (platform == 'ec2') {
                        Scalr.message.Success('Cloud credentials successfully configured. Now you can start to build your first farm. <a target="_blank" href="http://www.youtube.com/watch?v=6u9M-PD-_Ds&t=6s">Learn how to do this by watching video tutorial.</a>');
                        Scalr.event.fireEvent('redirect', '#/farms/build', true);
                    } else if (platform == 'rackspace' || platform == 'gce') {
                        Scalr.message.Success('Cloud credentials successfully configured. You need to create some roles before you will be able to create your first farm.');
                        Scalr.event.fireEvent('redirect', '#/roles/builder', true);
                    } else {
                        Scalr.message.Success('Cloud credentials successfully configured. Please create role from already running server. <a href="https://scalr-wiki.atlassian.net/wiki/x/1w8b" target="_blank">More info here.</a>');
                        Scalr.event.fireEvent('redirect', '#/roles/import', true);
                    }
                }, true);
            }
        }
    }
});

Scalr.application.applyContext = function(context, onlyVars) {
	context = context || {};
	Scalr.user = context['user'] || {};
	Scalr.flags = context['flags'] || {};
    Scalr.acl = context['acl'] || {};
    Scalr.platforms = context['platforms'] || {};
    Scalr.tags = context['tags'] || {};

	if (Ext.isDefined(Scalr.user.userId)) {
		Ext.Ajax.defaultHeaders['X-Scalr-EnvId'] = Scalr.user.envId;
		Ext.Ajax.defaultHeaders['X-Scalr-UserId'] = Scalr.user.userId;
	} else {
		delete Ext.Ajax.defaultHeaders['X-Scalr-EnvId'];
		delete Ext.Ajax.defaultHeaders['X-Scalr-UserId'];
	}

    Scalr.flags['betaMode'] = false;
    if (Scalr.user['envVars']) {
        Scalr.user['envVars'] = Ext.decode(Scalr.user['envVars'], true) || {};
        Scalr.flags['betaMode'] = Scalr.user['envVars']['beta'] == 1;
    }

    Scalr.flags['betaMode'] = Scalr.flags['betaMode'] || document.location.search.indexOf('beta') != -1;
    Scalr.flags['betaMode'] = Scalr.flags['betaMode'] && document.location.search.indexOf('!beta') == -1;

	if (Scalr.flags['betaMode']) {
		Ext.Ajax.defaultHeaders['X-Scalr-Interface-Beta'] = 1;
	} else {
		delete Ext.Ajax.defaultHeaders['X-Scalr-Interface-Beta'];
	}

    Ext.Ajax.extraParams['X-Requested-Token'] = Scalr.flags['specialToken'];

	if (! onlyVars) {
        // clear cache
        Scalr.application.items.each(function() {
            // excludes
            if (this.itemId != 'blank' && this.scalrCache != '/guest/login') {
                this.destroy();
                if (Scalr.application.layout.activeItem == this)
                    Scalr.application.layout.activeItem = null;
            }
        });

        // clear global store
        Scalr.data.reloadDefer('*');
        Scalr.cachedRequest.clearCache();
    }

	if (Scalr.user.userId) {
		Scalr.timeoutHandler.enabled = true;
		// TODO: check
		Scalr.timeoutHandler.params = Scalr.utils.CloneObject(Scalr.user);
		Scalr.timeoutHandler.schedule();
	}

    this.suspendLayouts();
    this.getDockedComponent('globalWarning').hide();
	if (Scalr.user.userId) {
		this.createMenu(context);
        
        if (Scalr.user.envId && Scalr.getPlatformConfigValue('ec2', 'autoDisabled')) {
            this.getDockedComponent('globalWarning').setValue((new Ext.Template(Scalr.strings['aws.revoked_credentials'])).apply({envId: Scalr.user.envId})).show();
        }
	}
    this.resumeLayouts(true);

    if (Ext.isDefined(Scalr.user.userId) && Scalr.flags.needEnvConfig && !sessionStorage.getItem('needEnvConfigLater')) {
		var needConfigEnvId = Scalr.flags.needEnvConfig;

        Scalr.event.un('update', Scalr.application.updateSetupHandler);
		Scalr.flags.needEnvConfig = true;
		Scalr.event.fireEvent('lock');
		Scalr.event.fireEvent('redirect', '#/account/environments/' + needConfigEnvId + '/clouds', true, true);
		Scalr.event.on('update', Scalr.application.updateSetupHandler);
	} else if (! onlyVars) {
        Scalr.event.un('update', Scalr.application.updateMenuHandler);
        Scalr.event.on('update', Scalr.application.updateMenuHandler);
        window.onhashchange(true);
	}
    
    if (Ext.isDefined(Scalr.user.userId) &&  Ext.isDefined(Scalr.user.userName)) {    	
    	_trackingUserEmail = Scalr.user.userName;
    	_trackEvent("000000132973");
    }
};

Scalr.application.createMenu = function(context) {
	var ct = this.down('#top'), menu = [], mainMenu;

	if (Scalr.user.type == 'ScalrAdmin') {
        menu.push({
            cls: 'x-scalr-icon',
            width: 65
        }, {
            text: 'Accounts',
            href: '#/admin/accounts',
            hrefTarget: '_self'
        }, {
            text: 'Admins',
            href: '#/admin/users',
            hrefTarget: '_self'
        }, {
            text: 'Cost analytics',
            href: '#/analytics/dashboard',
            hrefTarget: '_self',
            hidden: !Scalr.flags['analyticsEnabled']
        }, {
            text: 'Logs',
            href: '#/admin/logs/view',
            hrefTarget: '_self'
        }, {
            text: 'Roles',
            menu: [{
                text: 'View all',
                href: '#/roles/manager'
            }, {
                text: 'View shared roles',
                href: '#/roles/manager'
            }, {
                text: 'Add new',
                href: '#/roles/edit'
            }]
        }, {
            text: 'Scripts',
            href: '#/scripts/view',
            hrefTarget: '_self'
        }, {
            text: 'Global variables',
            href: '#/admin/variables',
            hrefTarget: '_self'
        }, {
            text: 'Settings',
            menu: [{
                text: 'Default DNS records',
                href: '#/dnszones/defaultRecords'
            }, {
                text: 'Debug',
                href: '#/admin/utils/debug'
            }]
        });

        menu.push('->');
        menu.push({
            text: Scalr.user['userName'],
            reorderable: false,
            cls: 'x-icon-login',
            menu: {
                cls: 'x-topmenu-dropdown',
                items: [{
                    text: 'Security',
                    href: '#/core/security',
                    iconCls: 'x-topmenu-icon-security'
                }, {
                    xtype: 'menuseparator'
                }, {
                    text: 'Logout',
                    href: '/guest/logout',
                    iconCls: 'x-topmenu-icon-logout'
                }]
            }
        });
    } else if (Scalr.user.type == 'FinAdmin') {
        menu.push({
            cls: 'x-scalr-icon',
            width: 65
        }, {
            text: 'Cost analytics',
            href: '#/analytics/dashboard',
            hrefTarget: '_self'
        });

        menu.push('->');
        menu.push({
            text: Scalr.user['userName'],
            reorderable: false,
            cls: 'x-icon-login',
            menu: {
                cls: 'x-topmenu-dropdown',
                items: [{
                    text: 'Logout',
                    href: '/guest/logout',
                    iconCls: 'x-topmenu-icon-logout'
                }]
            }
        });
    } else {
		var farms = [];
		Ext.each(context['farms'], function (item) {
			farms.push({
				text: item.name,
				href: '#/farms/view?farmId=' + item.id
			});
		});

        mainMenu = [{
            xtype: 'filterfield',
            emptyText: 'Menu filter',
            cls: 'x-menu-item-cmp-search',
            listeners: {
                change: function (field, value) {
                    var items = field.up().items.items, cls = 'x-menu-item-blur';

                    if (value.length < 2)
                        value = '';
                    else
                        value = value.toLowerCase();

                    var search = function (ct) {
                        var flag = false;

                        if (ct.menu) {
                            for (var j = 0; j < ct.menu.items.items.length; j++) {
                                var t = search(ct.menu.items.items[j]);
                                flag = flag || t;
                            }
                        }

                        if (ct.text && value && ct.text.toLowerCase().indexOf(value) != -1) {
                            if (!flag && ct.menu) {
                                // found only root menu item, so highlight all childrens
                                for (var j = 0; j < ct.menu.items.items.length; j++) {
                                    ct.menu.items.items[j].removeCls(cls);
                                }
                            }
                            flag = true;
                        }

                        if (flag || !value)
                            ct.removeCls(cls);
                        else
                            ct.addCls(cls);

                        return flag;
                    };

                    for (var i = 0; i < items.length; i++)
                        search(items[i]);
                }
            }
        }, {
            xtype: 'menuseparator'
        }, {
            text: 'Dashboard',
            iconCls: 'x-topmenu-icon-dashboard',
            href: '#/dashboard'
        }, {
            xtype: 'menuseparator'
        }, {
            xtype: 'menuitemtop',
            text: 'Farms',
            href: '#/farms/view',
            iconCls: 'x-topmenu-icon-farms',
            addLinkHref: '#/farms/build',
            menu: farms,
            hidden: !Scalr.isAllowed('FARMS')
        }, {
            xtype: 'menuitemtop',
            text: 'Roles',
            href: '#/roles/manager',
            iconCls: 'x-topmenu-icon-roles',
            addLinkHref: '#/roles/builder',
            hidden: !Scalr.isAllowed('FARMS_ROLES') || Scalr.flags['betaMode']
        }, {
            xtype: 'menuitemtop',
            text: 'Roles',
            href: '#/roles/manager',
            iconCls: 'x-topmenu-icon-roles',
            hidden: !Scalr.isAllowed('FARMS_ROLES') || !Scalr.flags['betaMode'],
            addLinkHref: '#/roles/create',
            menu: [{
                text: 'Roles Library',
                href: '#/roles/manager',
                iconCls: 'x-topmenu-icon-roles'
            }, {
                text: 'New Role',
                iconCls: 'x-topmenu-icon-new',
                href: '#/roles/edit'
            }, {
                text: 'Role Builder',
                iconCls: 'x-topmenu-icon-builder',
                href: '#/roles/builder'
            }, {
                text: 'Create Role from non-Scalr Server',
                href: '#/roles/import',
                iconCls: 'x-topmenu-icon-import',
                hidden: !Scalr.isAllowed('FARMS_ROLES', 'create')
            }]
        }, {
            xtype: 'menuitemtop',
            text: 'Images',
            href: '#/images/view',
            iconCls: 'x-topmenu-icon-images',
            //hidden: !Scalr.isAllowed('FARMS_ROLES'),
            hidden: !Scalr.flags['betaMode'],
            //addLinkHref: '#//create',
            menu: [{
                text: 'Images Library',
                href: '#/images/view',
                iconCls: 'x-topmenu-icon-library'
            }, {
                text: 'Image Builder',
                iconCls: 'x-topmenu-icon-builder',
                href: '#/roles/builder?image'
            }, {
                text: 'Create Image from non-Scalr Server',
                href: '#/roles/import?image',
                iconCls: 'x-topmenu-icon-import',
                hidden: !Scalr.isAllowed('FARMS_ROLES', 'create')
            }]
        }, {
            text: 'Servers',
            href: '#/servers/view',
            iconCls: 'x-topmenu-icon-servers',
            hidden: !Scalr.isAllowed('FARMS_SERVERS')
        }, {
            xtype: 'menuitemtop',
            text: 'Scripts',
            href: '#/scripts/view',
            iconCls: 'x-topmenu-icon-scripts',
            addLinkHref: '#/scripts/create',
            hidden: !Scalr.isAllowed('ADMINISTRATION_SCRIPTS')
        }, {
            text: 'Logs',
            iconCls: 'x-topmenu-icon-logs',
            hidden: !(Scalr.isAllowed('LOGS_SYSTEM_LOGS') || Scalr.isAllowed('LOGS_API_LOGS') || Scalr.isAllowed('LOGS_SCRIPTING_LOGS')),
            menu: [{
                text: 'System',
                href: '#/logs/system',
                hidden: !Scalr.isAllowed('LOGS_SYSTEM_LOGS')
            }, {
                text: 'Scripting',
                href: '#/logs/scripting',
                hidden: !Scalr.isAllowed('LOGS_SCRIPTING_LOGS')
            }, {
                text: 'API',
                href: '#/logs/api',
                hidden: !Scalr.isAllowed('LOGS_API_LOGS')
            }]
        }, {
            xtype: 'menuseparator'
        }, {
            xtype: 'menuitemtop',
            text: 'DNS zones',
            href: '#/dnszones/view',
            iconCls: 'x-topmenu-icon-dnszones',
            addLinkHref: '#/dnszones/create',
            hidden: !Scalr.isAllowed('DNS_ZONES')
        }, {
            xtype: 'menuitemtop',
            text: 'Apache virtual hosts',
            href: '#/services/apache/vhosts/view',
            iconCls: 'x-topmenu-icon-apachevhosts',
            addLinkHref: '#/services/apache/vhosts/create',
            hidden: !Scalr.isAllowed('SERVICES_APACHE')
        }, {
            text: 'Deployments',
            iconCls: 'x-topmenu-icon-deployments',
            hidden: !(Scalr.isAllowed('DEPLOYMENTS_APPLICATIONS') || Scalr.isAllowed('DEPLOYMENTS_SOURCES') || Scalr.isAllowed('DEPLOYMENTS_TASKS')),
            menu: [{
                text: 'Deployments',
                href: '#/dm/tasks/view',
                hidden: !Scalr.isAllowed('DEPLOYMENTS_TASKS')
            }, {
                text: 'Sources',
                href: '#/dm/sources/view',
                hidden: !Scalr.isAllowed('DEPLOYMENTS_SOURCES')
            }, {
                text: 'Applications',
                href: '#/dm/applications/view',
                hidden: !Scalr.isAllowed('DEPLOYMENTS_APPLICATIONS')
            }]
        }, {
            text: 'Create role from non-Scalr server',
            href: '#/roles/import',
            iconCls: 'x-topmenu-icon-import',
            hidden: !Scalr.isAllowed('FARMS_ROLES', 'create') || Scalr.flags['betaMode']
        }, {
            text: 'DB backups',
            href: '#/db/backups',
            iconCls: 'x-topmenu-icon-dbbackup',
            hidden: !Scalr.isAllowed('DB_BACKUPS')
        }, {
            text: 'Tasks scheduler',
            href: '#/schedulertasks/view',
            iconCls: 'x-topmenu-icon-scheduler',
            hidden: !Scalr.isAllowed('GENERAL_SCHEDULERTASKS')
        }, {
            text: 'SSH keys',
            href: '#/sshkeys/view',
            iconCls: 'x-topmenu-icon-sshkeys',
            hidden: !Scalr.isAllowed('SECURITY_SSH_KEYS')
        }, {
            text: 'Bundle tasks',
            href: '#/bundletasks/view',
            iconCls: 'x-topmenu-icon-bundletasks',
            hidden: !Scalr.isAllowed('FARMS_ROLES', 'bundletasks')
        }, {
            text: 'Server config presets',
            href: '#/services/configurations/presets/view',
            iconCls: 'x-topmenu-icon-presets',
            hidden: !Scalr.isAllowed('DB_SERVICE_CONFIGURATION')
        }, {
            text: 'Custom scaling metrics',
            href: '#/scaling/metrics/view',
            iconCls: 'x-topmenu-icon-metrics',
            hidden: !Scalr.isAllowed('GENERAL_CUSTOM_SCALING_METRICS')
        }, {
            text: 'Custom events',
            href: '#/scripts/events',
            iconCls: 'x-topmenu-icon-events',
            hidden: !Scalr.isAllowed('GENERAL_CUSTOM_EVENTS')
        }, {
            text: 'Environment global variables',
            href: '#/core/variables',
            iconCls: 'x-topmenu-icon-variables',
            hidden: !Scalr.isAllowed('ENVADMINISTRATION_GLOBAL_VARIABLES')
        }, {
            text: 'Governance',
            href: '#/core/governance',
            iconCls: 'x-topmenu-icon-governance',
            hidden: !Scalr.isAllowed('ENVADMINISTRATION_GOVERNANCE')
        }, {
            text: 'SSL certificates',
            href: '#/services/ssl/certificates/view',
            iconCls: 'x-topmenu-icon-sslcertificates',
            hidden: !Scalr.isAllowed('SERVICES_SSL')
        }, {
            text: 'Chef servers',
            href: '#/services/chef/servers/view',
            iconCls: 'x-topmenu-icon-chef',
            hidden: !Scalr.isAllowed('SERVICES_CHEF')
        }, {
            text: 'Webhooks',
            href: '#/webhooks/endpoints',
            iconCls: 'x-topmenu-icon-webhooks',
            hidden: !Scalr.isAllowed('GENERAL_WEBHOOKS')
        }];

        if (Scalr.isPlatformEnabled('ec2')) {
            mainMenu.push({
				xtype: 'menuseparator'
			}, {
				text: 'AWS',
				hideOnClick: false,
				iconCls: 'x-topmenu-icon-aws',
				menu: [{
					text: 'S3 & Cloudfront',
					href: '#/tools/aws/s3/manageBuckets',
					hidden: !Scalr.isAllowed('AWS_S3')
				}, {
					text: 'IAM SSL Certificates',
					href: '#/tools/aws/iam/servercertificates/view',
                    hidden: !Scalr.isAllowed('AWS_IAM')
				}, {
					text: 'Security groups',
					href: '#/security/groups/view?platform=ec2',
                    hidden: !Scalr.isAllowed('SECURITY_SECURITY_GROUPS')
				}, {
					text: 'Elastic IPs',
					href: '#/tools/aws/ec2/eips',
                    hidden: !Scalr.isAllowed('AWS_ELASTIC_IPS')
				}, {
					text: 'Elastic LB',
					href: '#/tools/aws/ec2/elb',
                    hidden: !Scalr.isAllowed('AWS_ELB')
				}, {
					text: 'EBS Volumes',
					href: '#/tools/aws/ec2/ebs/volumes',
                    hidden: !Scalr.isAllowed('AWS_VOLUMES')
				}, {
					text: 'EBS Snapshots',
					href: '#/tools/aws/ec2/ebs/snapshots',
                    hidden: !Scalr.isAllowed('AWS_SNAPSHOTS')
				}, {
                    text: 'Route53',
                    hidden: !Scalr.isAllowed('AWS_ROUTE53'),
                    href: '#/tools/aws/route53'
                }]
            });

            if (Scalr.isAllowed('AWS_RDS')) {
                mainMenu.push({
                    xtype: 'menuseparator'
                }, {
                    text: 'RDS',
                    hideOnClick: false,
                    iconCls: 'x-topmenu-icon-rds',
                    menu: [{
                        text: 'Instances',
                        href: '#/tools/aws/rds/instances'
                    }, {
                        text: 'Security groups',
                        href: '#/tools/aws/rds/sg/view'
                    }, {
                        text: 'Parameter groups',
                        href: '#/tools/aws/rds/pg/view'
                    }, {
                        text: 'DB Snapshots',
                        href: '#/tools/aws/rds/snapshots'
                    }]
                });
            }
        }

        Ext.Array.each(['cloudstack', 'idcf'], function(platform){
            if (Scalr.isPlatformEnabled(platform) && (Scalr.isAllowed('CLOUDSTACK_VOLUMES') || Scalr.isAllowed('CLOUDSTACK_SNAPSHOTS') || Scalr.isAllowed('CLOUDSTACK_PUBLIC_IPS'))) {
                mainMenu.push({
                    xtype: 'menuseparator'
                }, {
                    text: Scalr.utils.getPlatformName(platform),
                    hideOnClick: false,
                    iconCls: 'x-topmenu-icon-' + platform,
                    menu: [{
                        text: 'Volumes',
                        href: '#/tools/cloudstack/volumes?platform=' + platform,
                        hidden: !Scalr.isAllowed('CLOUDSTACK_VOLUMES')
                    }, {
                        text: 'Snapshots',
                        href: '#/tools/cloudstack/snapshots?platform=' + platform,
                        hidden: !Scalr.isAllowed('CLOUDSTACK_SNAPSHOTS')
                    }, {
                        text: 'Public IPs',
                        href: '#/tools/cloudstack/ips?platform=' + platform,
                        hidden: !Scalr.isAllowed('CLOUDSTACK_PUBLIC_IPS')
                    },{
                        text: 'Security groups',
                        href: '#/security/groups/view?platform=' + platform,
                        hidden: !Scalr.isAllowed('SECURITY_SECURITY_GROUPS')
                    }]
                });
            }
        });

        Ext.Array.each(['openstack', 'ecs', 'nebula', 'ocs', 'contrail'], function(platform){
            var menuItems = [];
            if (Scalr.isPlatformEnabled(platform)) {
                if (Scalr.isAllowed('OPENSTACK_VOLUMES')) {
                    menuItems.push({
                        text: 'Volumes',
                        href: '#/tools/openstack/volumes?platform=' + platform,
                    });
                }
                if (Scalr.isAllowed('OPENSTACK_SNAPSHOTS')) {
                    menuItems.push({
                        text: 'Snapshots',
                        href: '#/tools/openstack/snapshots?platform=' + platform,
                    });
                }
                if (Scalr.isAllowed('SECURITY_SECURITY_GROUPS') && Scalr.getPlatformConfigValue(platform, 'ext.securitygroups_enabled') == 1) {
                    menuItems.push({
                        text: 'Security groups',
                        href: '#/security/groups/view?platform=' + platform,
                    });
                }
                if (Scalr.isAllowed('OPENSTACK_ELB') && Scalr.getPlatformConfigValue(platform, 'ext.lbaas_enabled') == 1) {
                    menuItems.push({
                        text: 'Load balancers',
                        menu: [{
                            text: 'Pools',
                            href: '#/tools/openstack/lb/pools?platform=' + platform
                        }, {
                            text: 'Members',
                            href: '#/tools/openstack/lb/members?platform=' + platform
                        }, {
                            text: 'Monitors',
                            href: '#/tools/openstack/lb/monitors?platform=' + platform
                        }]
                    });
                }
                if (platform == 'contrail') {
                    menuItems.push({
                        text: 'Networking',
                        menu: [{
                            text: 'DNS',
                            href: '#/tools/openstack/contrail/dns?platform=' + platform
                        }, {
                            text: 'IPAM',
                            href: '#/tools/openstack/contrail/ipam?platform=' + platform
                        }, {
                            text: 'Policies',
                            href: '#/tools/openstack/contrail/policies?platform=' + platform
                        }, {
                            text: 'Networks',
                            href: '#/tools/openstack/contrail/networks?platform=' + platform
                        }]
                    });
                }
                if (menuItems.length > 0) {
                    mainMenu.push({
                        xtype: 'menuseparator'
                    }, {
                        text: Scalr.utils.getPlatformName(platform),
                        hideOnClick: false,
                        iconCls: 'x-topmenu-icon-' + platform,
                        menu: menuItems
                    });

                }
            }
        });

        if (Scalr.isPlatformEnabled('ucloud')) {
            mainMenu.push({
				xtype: 'menuseparator'
			}, {
				text: 'uCloud',
				hideOnClick: false,
				iconCls: 'x-topmenu-icon-ucloud',
				menu: [{
					text: 'Volumes',
					href: '#/tools/cloudstack/volumes?platform=ucloud'
				}, {
					text: 'Snapshots',
					href: '#/tools/cloudstack/snapshots?platform=ucloud'
				}]
            });
        }

        if (Scalr.isPlatformEnabled('rackspace')) {
            mainMenu.push({
				xtype: 'menuseparator'
			}, {
				text: 'Rackspace',
				hideOnClick: false,
				iconCls: 'x-topmenu-icon-rackspace',
				menu: [{
					text: 'Limits Status',
					href: '#/tools/rackspace/limits'
				}]
            });
        }

        if (Scalr.isPlatformEnabled('gce') && (Scalr.isAllowed('GCE_STATIC_IPS') || Scalr.isAllowed('GCE_PERSISTENT_DISKS') || Scalr.isAllowed('GCE_SNAPSHOTS'))) {
            mainMenu.push({
                xtype: 'menuseparator'
            }, {
                text: Scalr.utils.getPlatformName('gce'),
                hideOnClick: false,
                iconCls: 'x-topmenu-icon-gce',
                menu: [{
                    text: 'Static IPs',
                    href: '#/tools/gce/addresses',
                    hidden: !Scalr.isAllowed('GCE_STATIC_IPS')
                }, {
                    text: 'Persistent disks',
                    href: '#/tools/gce/disks',
                    hidden: !Scalr.isAllowed('GCE_PERSISTENT_DISKS')
                }, {
                    text: 'Snapshots',
                    href: '#/tools/gce/snapshots',
                    hidden: !Scalr.isAllowed('GCE_SNAPSHOTS')
                }]
            });
        }


		menu.push({
			cls: 'x-scalr-icon',
            itemId: 'mainMenu',
			hideOnClick: false,
			width: 77,
			reorderable: false,
			listeners: {
				boxready: function () {
					// fix height of vertical separator
					Ext.override(this.menu, {
						afterComponentLayout: function(width, height, oldWidth, oldHeight) {
							var me = this;
							me.callOverridden();

							if (me.showSeparator && me.items.getAt(1)) {
								var y = me.items.getAt(1).el.getTop(true); // top coordinate for first separator after textfield
								me.iconSepEl.setTop(y);
								me.iconSepEl.setHeight(me.componentLayout.lastComponentSize.contentHeight - y);
							}
						},
                        menuOffset: [ 1, 1 ]
					});
				},
				menushow: function () {
					this.menu.down('textfield').focus(true, true);
				}
			},
            menu: {
                cls: 'x-topmenu-dropdown',
                items: mainMenu,

                setDropdownMenuHeight: function (width, height) {
                    var me = this;

                    if (!me.isDestroyed && me.isVisible()) {
                        me.maxHeight = height;
                        me.updateLayout();
                    }
                },

                listeners: {
                    boxready: function () {
                        var me = this;
                        Ext.EventManager.onWindowResize(me.setDropdownMenuHeight, me);
                    },
                    destroy: function () {
                        var me = this;
                        Ext.EventManager.removeResizeListener(me.setDropdownMenuHeight, me);
                    }
                }
            }
		});

		if (!Scalr.storage.get('system-favorites') && !Scalr.storage.get('system-favorites-created')) {
			Scalr.storage.set('system-favorites', [{
				href: '#/farms/view',
				text: 'Farms'
			}, {
				href: '#/roles/manager',
				text: 'Roles'
			}, {
				href: '#/servers/view',
				text: 'Servers'
			}, {
				href: '#/scripts/view',
				text: 'Scripts'
			}, {
				href: '#/logs/system',
				text: 'System Log'
			}]);
			Scalr.storage.set('system-favorites-created', true);
		}

        var systemFavorites = Scalr.storage.get('system-favorites');
        Ext.each(systemFavorites, function(item) {
            if (item['href'] === '#/roles/view') {
                item['href'] = '#/roles/manager';
                return false;
            }
        });
        Scalr.storage.set('system-favorites', systemFavorites);

		Ext.each(Scalr.storage.get('system-favorites'), function(item) {
			if (item.text) {
				item['hrefTarget'] = '_self';
				item['reorderable'] = true;
				item['cls'] = 'x-btn-favorite';
				//item['overCls'] = 'btn-favorite-over';
				//item['pressedCls'] = 'btn-favorite-pressed';
				menu.push(item);
			}
		}, this);

		menu.push({
			xtype: 'tbfill',
			reorderable: false
		});

		menu.push({
			text: Scalr.user['userName'],
			reorderable: false,
			cls: 'x-icon-avatar',
			itemId: 'gravatar',
			icon: Scalr.utils.getGravatarUrl(Scalr.user['gravatarHash']),
			listeners: {
				boxready: function() {
					Scalr.event.on('update', function (type, hash) {
						Scalr.user['gravatarHash'] = hash;
						if (type == '/account/user/gravatar') {
							this.setIcon(Scalr.utils.getGravatarUrl(hash));
						}
					}, this);
				}
			},
			menu: {
                cls: 'x-topmenu-dropdown',
                items: [{
                    text: 'API access',
                    href: '#/core/api',
                    iconCls: 'x-topmenu-icon-api'
                }, '-', {
                    text: 'Security',
                    href: '#/core/security',
                    iconCls: 'x-topmenu-icon-security'
                }, {
                    text: 'Settings',
                    href: '#/core/settings',
                    iconCls: 'x-topmenu-icon-settings'
                }, '-', {
                    text: 'Logout',
                    href: '/guest/logout',
                    iconCls: 'x-topmenu-icon-logout'
                }]
            }
		});

		var envs = [];
        envs.push({
            href: '#/account/environments',
            iconCls: 'x-topmenu-icon-settings',
            text: 'Manage'
        }, {
            xtype: 'menuseparator'
        });

		Ext.each(context['environments'], function(item) {
			envs.push({
				text: item.name,
				group: 'environment',
				envId: item.id,
				checked: item.id == Scalr.user.envId
			});
		});

		menu.push({
			cls: 'x-icon-environment',
			reorderable: false,
			text: Scalr.user['envName'],
			menu: {
                cls: 'x-topmenu-dropdown',
                items: envs
            },
			tooltip: {
                cls: 'x-tip-light',
                text: 'Environment',
                width: null//required when defining tooltip as config object because of qtip internal bug
            },
			listeners: {
				boxready: function() {
					var handler = function() {
						if (this.envId && Scalr.user['envId'] != this.envId)
							Scalr.Request({
								processBox: {
									type: 'action',
									msg: 'Changing environment ...'
								},
								url: '/core/xChangeEnvironment/',
								params: { envId: this.envId },
								success: function() {
									Scalr.application.updateContext(Ext.emptyFn);
								}
							});
					};

					this.menu.items.each(function(it) {
						it.on('click', handler);
					});

					Scalr.event.on('update', function (type, env) {
						if (type == '/account/environments/create') {
							this.menu.add({
								text: env.name,
								checked: false,
								group: 'environment',
								envId: env.id
							}).on('click', handler);
						} else if (type == '/account/environments/rename') {
							var el = this.menu.child('[envId="' + env.id + '"]');
							if (el) {
								el.setText(env.name);
							}

							if (Scalr.user['envId'] == env.id) {
								this.setText(env.name);
							}
						} else if (type == '/account/environments/delete') {
							var el = this.menu.child('[envId="' + env.id + '"]');
							if (el) {
								this.menu.remove(el);
							}
						}
					}, this);
				}
			}
		});

        var link = null;
        if (Scalr.isAllowed('ADMINISTRATION_ORCHESTRATION')) {
            link = '#/account/orchestration';
        } else if (Scalr.isAllowed('ADMINISTRATION_GLOBAL_VARIABLES')) {
            link = '#/account/variables';
        } else if (Scalr.flags['billingExists'] && Scalr.isAllowed('ADMINISTRATION_BILLING')) {
            link = '#/billing';
        }
		if (link){
			menu.push({
				href: link,
				reorderable: false,
				hrefTarget: '_self',
				cls: 'x-icon-settings',
                tooltip: {
                    cls: 'x-tip-light',
                    text: 'Account management'
                }
			});
        }
        menu.push({
            xtype: 'button',
            height: '100%',
            cls: 'x-icon-change-log',
            tooltip: {
                cls: 'x-tip-light',
                text: 'What\'s new in Scalr'
            },

            addNewsCounter: function () {
                var me = this;

                Ext.DomHelper.append(me.el, {tag: 'div', class: 'scalr-ui-menu-changelog-news-counter'});
            },

            updateNewsCounter: function (newsCount) {
                var me = this;
                var newsCounter = me.el.down('.scalr-ui-menu-changelog-news-counter');

                newsCounter.update(newsCount);
                newsCounter.setVisible(!!newsCount);
            },

            resetNewsCounter: function () {
                var me = this;

                Scalr.Request({
                    url: '/core/xGetChangeLog?resetCounter=true',
                    success: function () {
                        me.updateNewsCounter(0);
                    }
                });
            },

            getChangelog: function () {
                var me = this;

                Scalr.Request({
                    method: 'GET',
                    url: '/core/xGetChangeLog',
                    success: function (data) {
                        me.changelogData = data['data'];

                        var counter = me.el.down('.scalr-ui-menu-changelog-news-counter');

                        if (!counter) {
                            me.addNewsCounter();
                        }

                        me.updateNewsCounter(data['countNew']);
                    }
                });
            },

            showChangelog: function () {
                var me = this;

                Scalr.utils.Window({
                    xtype: 'grid',
                    title: 'What\'s new in Scalr',
                    titleAlign: 'left',
                    width: 800,
                    cls: 'scalr-ui-changelog-grid',
                    closable: true,
                    disableSelection: true,
                    minRecordsCount: 5,

                    store: {
                        fields: ['text', 'url', 'time', 'timestamp', 'new'],
                        proxy: 'object',
                        data: me.changelogData
                    },

                    columns: [{
                        xtype: 'templatecolumn', flex: 1, tpl: [
                            '<tpl for=".">',
                            '<div class="scalr-ui-changelog-div">',
                            '<div class="scalr-ui-changelog-desc">{time}</div>',
                            '<div>' +
                                '<a href="{url}" target="_blank"><span class="scalr-ui-changelog-message-slim">{text}</span></a>' +
                                '<tpl if="new"><span style="margin-left: 5px; cursor: pointer;" class="scalr-ui-changelog-info">New</span></tpl>' +
                                '</div>',
                            '</div>',
                            '</tpl>'
                        ]
                    }],

                    dockedItems: [{
                        xtype: 'displayfield',
                        style: 'background-color: #f0f1f4;box-shadow: 0 1px #dbdfe6 inset;text-align: center',
                        height: 43,
                        value: '<a class="x-fieldset-infobox" href="http://www.scalr.com/scalr-product-blog" target="_blank" style="top: 10px; margin: 0 0 0 12px; padding: 1px 0 1px 22px; cursor: pointer;">Sign up for email notifications from the Product Blog</a>'
                    }],

                    addEmptyRecords: function () {
                        var me = this;

                        var store = me.getStore();
                        var recordsCount = store.getCount();
                        var minRecordsCount = me.minRecordsCount;

                        if (recordsCount < minRecordsCount) {
                            for (var i = 0; i < minRecordsCount - recordsCount; i++) {
                                store.add({});
                            }
                        }
                    },

                    markRecordsAsRead: function () {
                        Ext.each(me.changelogData, function (record) {
                            record['new'] = false;
                        });
                    },

                    listeners: {
                        afterrender: function () {
                            var me = this;
                            me.addEmptyRecords();
                        },

                        destroy: function () {
                            var me = this;
                            me.markRecordsAsRead();
                        },

                        itemclick: function (grid, record, item, index, e) {
                            var target = e.getTarget();

                            if (target.className === 'scalr-ui-changelog-message-slim') {
                                if (typeof _gaq !== 'undefined') {
                                    _gaq.push(['_trackEvent', 'ProductBlog', 'Open', target.innerHTML]);
                                }
                            }
                        }
                    }

                    /*
                     plugins: [{
                         ptype: 'bufferedrenderer',
                         scrollToLoadBuffer: 100,
                         synchronousRender: false
                     }],
                     */
                });
            },

            listeners: {
                beforerender: function () {
                    var me = this;
                    me.getChangelog();
                },
                boxready: function () {
                    var me = this;

                    me.updateChangelog = Ext.util.TaskManager.newTask({
                        run: me.getChangelog,
                        scope: me,
                        interval: 3600000
                    });

                    me.updateChangelog.start();
                },
                destroy: function () {
                    var  me = this;

                    me.updateChangelog.destroy();
                }
            },

            handler: function () {
                var me = this;

                me.showChangelog();
                me.resetNewsCounter();
            }
        });

		menu.push({
			cls: 'x-icon-help',
			tooltip: {
                cls: 'x-tip-light',
                text: 'Help',
                width: null
            },
			reorderable: false,
			menu: {
                cls: 'x-topmenu-dropdown',
                items: [{
                    text: 'Wiki',
                    href: Scalr.flags['wikiUrl'],
                    iconCls: 'x-topmenu-icon-wiki',
                    hrefTarget: '_blank'
                }, {
                    text: 'Support',
                    href: Scalr.flags['supportUrl'],
                    iconCls: 'x-topmenu-icon-support',
                    hrefTarget: '_blank'
                }, {
                    text: 'Full screen',
                    hidden: !Scalr.flags['betaMode'],
                    handler: function() {
                        var fullscreenEnabled =
                            document.fullscreenEnabled ||
                            document.mozFullscreenEnabled ||
                            document.webkitFullscreenEnabled,
                            element = document.documentElement;

                        // chrome always returns true, use escape to cancel ff mode
                        /*if (fullscreenEnabled) {
                            if (document.cancelFullScreen) {
                                document.cancelFullScreen();
                            } else if (document.mozCancelFullScreen) {
                                document.mozCancelFullScreen();
                            } else if (document.webkitCancelFullScreen) {
                                document.webkitCancelFullScreen();
                            }
                        } else {*/

                        if (element.requestFullScreen) {
                            element.requestFullScreen();
                        } else if (element.mozRequestFullScreen) {
                            element.mozRequestFullScreen();
                        } else if (element.webkitRequestFullScreen) {
                            element.webkitRequestFullScreen();
                        }

                        //}
                    }
                }]
            }
		});
	}

	ct.removeAll();
	ct.add(menu);
};

window.onhashchange = function (e) {
	if (Scalr.state.pageSuspendForce) {
		Scalr.state.pageSuspendForce = false;
	} else {
		if (Scalr.state.pageSuspend)
			return;
	}

    var h = window.location.hash.substring(1).split('?'), link = '', param = {}, loaded = false, defaultPage = false;
    if (window.location.hash) {
        // only if hash not null
        if (h[0])
            link = h[0];
        // cut ended /  (/logs/view'/')

        if (h[1])
            param = Ext.urlDecode(h[1]);

        if (link == '' || link == '/') {
            defaultPage = true;
            return; // TODO: check why ?
        }
    } else {
        defaultPage = true;
    }

    if (defaultPage) {
        if (Scalr.user.userId)
            document.location.href = "#/dashboard";
        else
            document.location.href = "#/guest/login";
        return;
    }

    var returnToOldUrl = function(url) {
        var state = !Scalr.state.pageSuspend;

        if (state)
            Scalr.state.pageSuspend = true;

        document.location.href = url;

        if (state) {
            setTimeout(function() {
                Scalr.state.pageSuspend = false;
            }, 10);
        }
    };

    var activeWindow = Ext.WindowManager.getActive();
    if (activeWindow && activeWindow.itemId == 'box' && e) {
        // change link, when open modal window, block change, close window
        returnToOldUrl(e.oldURL);
        activeWindow.close();
        return;
    }

    if (Scalr.state.pageChangeInProgress) {
        Scalr.state.pageChangeInProgressInvalid = true; // User changes link while loading page
        if (Scalr.state.pageChangeInProgressRequest)
            Ext.Ajax.abort(Scalr.state.pageChangeInProgressRequest);
        return;
    }

	Scalr.state.pageChangeInProgress = true;
    Scalr.state.pageRedirectCounter++;
	Scalr.message.Flush();

    var addStatisticGa = function(link) {
        // Google analytics
        if (typeof _gaq != 'undefined') {
            _gaq.push(['_trackPageview', link]);
        }
    };

	var cacheLink = function (link, cache) {
        var cacheOriginal = cache.trim();
		var re = cache.replace(/\/\{[^\}]+\}/g, '/([^\\}\\/]+)').replace(/\//g, '\\/'), fieldsRe = /\/\{([^\}]+)\}/g, fields = [];

		while ((elem = fieldsRe.exec(cache)) != null) {
			fields[fields.length] = elem[1];
		}

		return {
			scalrRegExp: new RegExp('^' + re + '$'),
            scalrCacheStr: cacheOriginal,
			scalrCache: cache,
			scalrParamFields: fields,
			scalrParamGets: function (link) {
				var pars = {}, reg = new RegExp(this.scalrRegExp), params = reg.exec(link);
				if (Ext.isArray(params))
					params.shift(); // delete first element

				for (var i = 0; i < this.scalrParamFields.length; i++)
					pars[this.scalrParamFields[i]] = Ext.isArray(params) ? params.shift() : '';

				return pars;
			}
		};
	};

	// check in cache
	Scalr.application.items.each(function () {
		if (this.scalrRegExp && this.scalrRegExp.test(link)) {

			//TODO: Investigate in Safari
			this.scalrParamGets(link);

			Ext.apply(param, this.scalrParamGets(link));

            addStatisticGa(this.scalrCacheStr);
			loaded = Scalr.application.layout.setActiveItem(this, param);
			return false;
		}
	});

    var finishChange = function () {
        if (Scalr.state.pageChangeInProgressInvalid) {
            Scalr.state.pageChangeInProgressInvalid = false;
            Scalr.state.pageChangeInProgress = false;
            Scalr.state.pageChangeInProgressRequest = false;
            window.onhashchange(true);
        } else {
            Scalr.state.pageChangeInProgress = false;
            Scalr.state.pageChangeInProgressRequest = false;
        }
    };

	if (loaded) {
        if (loaded == 'lock' && e.oldURL) {
            returnToOldUrl(e.oldURL);
        }

        finishChange();
		return;
	}

	Ext.apply(param, Scalr.state.pageRedirectParams);
	Scalr.state.pageRedirectParams = {};

	var r = {
		disableFlushMessages: true,
		disableAutoHideProcessBox: true,
        hideErrorMessage: true,
		url: link,
		params: param,
		success: function (data, response, options) {
            Scalr.state.pageChangeInProgressRequest = false;
			try {
                if (data['moduleUiHash'] && Scalr.state.pageUiHash && data['moduleUiHash'] != Scalr.state.pageUiHash)
                    Scalr.state.pageReloadRequired = true; // reload page, when non-modal window created

				// TODO: replace ui2 -> ui
				var c = 'Scalr.' + data.moduleName.replace('/ui2/js/', '').replace(/-[0-9]+.js/, '').replace(/\//g, '.'), cacheId = response.getResponseHeader('X-Scalr-Cache-Id'), cache = cacheLink(link, cacheId);
				var initComponent = function (c) {
					if (Ext.isObject(c)) {
						Ext.apply(c, cache);
						Scalr.application.add(c);
                        addStatisticGa(c.scalrCacheStr);

						if (Scalr.state.pageChangeInProgressInvalid) {
							if (options.processBox)
								options.processBox.destroy();

							finishChange();
						} else {
                            if (Scalr.application.layout.setActiveItem(c, param) == 'lock' && e.oldURL) {
                                returnToOldUrl(e.oldURL);
                            }

                            if (options.processBox)
								options.processBox.destroy();

                            finishChange();
						}
					} else {
						if (options.processBox)
							options.processBox.destroy();

                        if (Scalr.application.layout.setActiveItem(Scalr.application.getComponent('blank')) == 'lock' && e.oldURL) {
                            returnToOldUrl(e.oldURL);
                        }

						finishChange();
					}
				};
				var loadModuleData = function(c, param, data) {
					if (data.moduleRequiresData) {
						Scalr.data.load(data.moduleRequiresData, function() {
                            if (Ext.isFunction(Scalr.cache[c])) {
                                initComponent(Scalr.cache[c](param, data.moduleParams));
                            } else {
                                Scalr.utils.PostError({
                                    file: 'ui.js (onhashchange)',
                                    message: response.responseText + "\n\n" + Ext.encode(Scalr.cache[c]),
                                    url: document.location.href
                                });
                            }
						});
					} else {
                        if (Ext.isFunction(Scalr.cache[c])) {
                            initComponent(Scalr.cache[c](param, data.moduleParams));
                        } else {
                            Scalr.utils.PostError({
                                file: 'ui.js (onhashchange)',
                                message: response.responseText + "\n\n" + Ext.encode(Scalr.cache[c]),
                                url: document.location.href
                            });
                        }
					}
				};
				
				Ext.apply(param, cache.scalrParamGets(link));

                if (data.moduleRequires)
                    data.moduleRequires.unshift(data.moduleName);
                else
                    data.moduleRequires = [ data.moduleName ];

                // get loaded js files
                var domScripts = document.getElementsByTagName('script'), loadedScripts = [], i, attr;
                for (i = 0; i < domScripts.length; i++) {
                    attr = domScripts[i].getAttribute('src');
                    if (attr)
                        loadedScripts.push(attr);
                }
                data.moduleRequires = Ext.Array.difference(data.moduleRequires, loadedScripts); // also reload all changed files

                // get loaded css files
                var domCss = document.getElementsByTagName('link'), loadedCss = [];
                for (i = 0; i < domCss.length; i++) {
                    attr = domCss[i].getAttribute('href');
                    if (attr)
                        loadedCss.push(attr);
                }
                data.moduleRequiresCss = Ext.Array.difference(data.moduleRequiresCss || [], loadedCss);

                // check for old css to replace them
                if (data.moduleRequiresCss.length) {
                    var requiredCssSim = [];
                    for (i = 0; i < data.moduleRequiresCss.length; i++)
                        requiredCssSim.push(data.moduleRequiresCss[i].replace(/-[0-9]+.css/, '.css'));

                    for (i = 0; i < domCss.length; i++) {
                        if (Ext.Array.contains(requiredCssSim, domCss[i].getAttribute('href').replace(/-[0-9]+.css/, '.css'))) {
                            domCss[i].parentNode.removeChild(domCss[i]);
                        }
                    }
                }

                if (data.moduleRequires.length || data.moduleRequiresCss.length) {
                    var head = Ext.getHead();
                    if (data.moduleRequiresCss.length) {
                        for (i = 0; i < data.moduleRequiresCss.length; i++) {
                            var el = document.createElement('link');
                            el.type = 'text/css';
                            el.rel = 'stylesheet';
                            el.href = data.moduleRequiresCss[i];

                            head.appendChild(el);
                        }
                    }

                    if (data.moduleRequires.length)
                        Ext.Loader.loadScripts(data.moduleRequires, function() {
                            loadModuleData(c, param, data);
                        });
                    else
                        loadModuleData(c, param, data);
                } else {
                    loadModuleData(c, param, data);
				}
			} catch (e) {
				Scalr.utils.PostException(e);
			}
		},
		failure: function (data, response, options) {
            Scalr.state.pageChangeInProgressRequest = false;

            if (options.processBox)
				options.processBox.destroy();

            var message, c = Scalr.application.getComponent('error');

            if (data && data.errorMessage) {
                message = data.errorMessage;
            } else if (response.status == 403) {
                Scalr.state.userNeedLogin = true;
                Scalr.event.fireEvent('redirect', '#/guest/login', true);
            } else if (response.status == 404) {
                message = 'Page not found.';
            } else if (response.timedout == true) {
                message = 'Server didn\'t respond in time. Please try again in a few minutes.';
            } else if (response.aborted == true) {
                message = 'Request was aborted by user.';
            } else {
                if (Scalr.timeoutHandler.enabled) {
                    Scalr.timeoutHandler.undoSchedule();
                    Scalr.timeoutHandler.run();
                }
                message = 'Cannot proceed with your request. Please try again later.';
            }

            if (! response.aborted) {
                if (!c) {
                    c = Scalr.application.add({
                        xtype: 'container',
                        width: 500,
                        scalrOptions: {
                            reload: false,
                            modal: true
                        },
                        cls: 'x-panel-shadow x-panel-confirm',
                        margin: '30 0 0 0',

                        items: [{
                            xtype: 'component',
                            cls: 'x-panel-confirm-message x-panel-confirm-message-multiline',
                            data: {
                                type: 'error',
                                msg: message
                            },
                            tpl: '<div class="icon icon-{type}"></div><div class="message">{msg}</div>'
                        }, {
                            xtype: 'container',
                            layout: {
                                type: 'hbox',
                                pack: 'center'
                            },
                            padding: 16,
                            items: [{
                                xtype: 'button',
                                text: 'Retry',
                                height: 32,
                                width: 150,
                                handler: function() {
                                    Scalr.message.Flush(true);
                                    Scalr.event.fireEvent('refresh');
                                }
                            }, {
                                xtype: 'button',
                                text: 'Cancel',
                                height: 32,
                                width: 150,
                                margin: '0 0 0 12',
                                handler: function() {
                                    Scalr.message.Flush(true);
                                    Scalr.event.fireEvent('close');
                                }
                            }]
                        }],
                        itemId: 'error'
                    });
                } else {
                    c.down('component').update({ type: 'error', msg: message });
                }
            }

            if (Scalr.application.layout.setActiveItem(c) == 'lock' && e.oldURL) {
                returnToOldUrl(e.oldURL);
            }

            finishChange();
		}
	};

	if (e)
		r['processBox'] = {
			type: 'action',
			msg: 'Loading page ...'
		};

	Scalr.state.pageChangeInProgressRequest = Scalr.Request(r);
};

// for test
Scalr.debug = {
    dump: 0,
    pass: 0
};

Scalr.timeoutHandler = {
	defaultTimeout: 60000,
	timeoutRun: 60000,
	timeoutRequest: 5000,
	params: {},
	enabled: false,
	locked: false,
	clearDom: function () {
		if (Ext.get('body-timeout-mask'))
			Ext.get('body-timeout-mask').remove();

		if (Ext.get('body-timeout-container'))
			Ext.get('body-timeout-container').remove();
	},
	schedule: function () {
		this.timeoutId = Ext.Function.defer(this.run, this.timeoutRun, this);
	},
	createTimer: function (cont) {
		clearInterval(this.timerId);
		var f = Ext.Function.bind(function (cont) {
			var el = cont.child('span');
			if (el) {
				var s = parseInt(el.dom.innerHTML);
				s -= 1;
				if (s < 0)
					s = 0;
				el.update(s.toString());
			} else {
				clearInterval(this.timerId);
			}
		}, this, [ cont ]);

		this.timerId = setInterval(f, 1000);
	},
	undoSchedule: function () {
		clearTimeout(this.timeoutId);
		clearInterval(this.timerId);
	},
	restart: function () {
		this.undoSchedule();
		this.run();
	},
	run: function () {
		var hash = Scalr.storage.hash(), time = (new Date()).getTime();
        if (hash != Scalr.storage.get('system-hash')) {
            Scalr.debug.dump++;
            Scalr.storage.set('system-time', time);
            Scalr.storage.set('system-hash', hash);
            this.params['uiStorage'] = Ext.encode({
                dump: Scalr.storage.dump(true),
                time: time
            });
        } else {
            Scalr.debug.pass++;
            delete this.params['uiStorage'];
        }

		Ext.Ajax.request({
			url: '/guest/xPerpetuumMobile',
			params: this.params,
			timeout: this.timeoutRequest,
			scope: this,
			hideErrorMessage: true,
			callback: function (options, success, response) {
				if (success) {
					try {
						response = Ext.decode(response.responseText);

						if (response.success != true) {
							if (response.success == false && response.errorMessage != '') {
								Scalr.message.Error(response.errorMessage);
							} else {
								throw 'False';
							}
						}

						this.clearDom();
						this.timeoutRun = this.defaultTimeout;

						if (! response.isAuthenticated) {
							Scalr.application.layout.setActiveItem(Scalr.application.getComponent('loginForm'));
							this.schedule();
							return;
						} else if (! response.equal) {
							document.location.reload();
							return;
						} else {
							if (this.locked) {
								this.locked = false;
								Scalr.event.fireEvent('unlock');
								// TODO: проверить, нужно ли совместить в unlock
								window.onhashchange(true);
							}

							Scalr.event.fireEvent('update', 'lifeCycle', response);

							this.schedule();
							return;
						}
					} catch (e) {
						this.schedule();
						return;
					}
				}

				if (response.aborted == true) {
					this.schedule();
					return;
				}

				if (response.timedout == true) {
					this.schedule();
					return;
				}

				Scalr.event.fireEvent('lock');
				this.locked = true;

				var mask = Ext.get('body-timeout-mask') || Ext.getBody().createChild({
					id: 'body-timeout-mask',
					tag: 'div',
					style: {
						position: 'absolute',
						top: 0,
						left: 0,
						width: '100%',
						height: '100%',
						background: '#CCC',
						opacity: '0.5',
						'z-index': 300000
					}
				});

				this.timeoutRun += 6000;
				if (this.timeoutRun > 60000)
					this.timeoutRun = 60000;

				if (! Ext.get('body-timeout-container'))
					this.timeoutRun = 5000;

				var cont = Ext.get('body-timeout-container') || Ext.getBody().createChild({
					id: 'body-timeout-container',
					tag: 'div',
					style: {
						position: 'absolute',
						top: '5px',
						left: '5px',
						right: '5px',
						'z-index': 300001,
						background: '#F6CBBA',
						border: '1px solid #BC7D7A',
						'box-shadow': '0 1px #FEECE2 inset',
						font: 'bold 13px arial',
						color: '#420404',
						padding: '10px',
						'text-align': 'center'
					}
				}).applyStyles({ background: '-webkit-gradient(linear, left top, left bottom, from(#FCD9C5), to(#F0BCAC))'
				}).applyStyles({ background: '-moz-linear-gradient(top, #FCD9C5, #F0BCAC)' });

				this.schedule();

				cont.update('Not connected. Connecting in <span>' + this.timeoutRun/1000 + '</span>s. <a href="#">Try now</a> ');
				cont.child('a').on('click', function (e) {
					e.preventDefault();
					cont.update('Not connected. Trying now');
					this.undoSchedule();
					this.run();
				}, this);
				this.createTimer(cont);
			}
		});
	}
};

Scalr.timeoutHandler22 = {
	defaultTimeout: 60000,
	timeoutRun: 60000,
	timeoutRequest: 5000,
	params: {},
	enabled: false,
	forceCheck: false,
	locked: false,
	lockedCheck: true,
	clearDom: function () {
		if (Ext.get('body-timeout-mask'))
			Ext.get('body-timeout-mask').remove();

		if (Ext.get('body-timeout-container'))
			Ext.get('body-timeout-container').remove();
	},
	schedule: function () {
		this.timeoutId = Ext.Function.defer(this.run, this.timeoutRun, this);
	},
	createTimer: function (cont) {
		clearInterval(this.timerId);
		var f = Ext.Function.bind(function (cont) {
			var el = cont.child('span');
			if (el) {
				var s = parseInt(el.dom.innerHTML);
				s -= 1;
				if (s < 0)
					s = 0;
				el.update(s.toString());
			} else {
				clearInterval(this.timerId);
			}
		}, this, [ cont ]);

		this.timerId = setInterval(f, 1000);
	},
	undoSchedule: function () {
		clearTimeout(this.timeoutId);
		clearInterval(this.timerId);
	},
	restart: function () {
		this.undoSchedule();
		this.run();
	},
	run: function () {
		if (!this.locked && !this.forceCheck) {
			var cur = new Date(), tm = Scalr.storage.get('system-pm-updater');
			if (cur < tm) {
				this.schedule();
				return;
			}

			Scalr.storage.set('system-pm-updater', Ext.Date.add(cur, Ext.Date.SECOND, this.timeoutRun/1000));
		}

		Ext.Ajax.request({
			url: this.forceCheck || this.locked && this.lockedCheck ? '/ui/js/connection.js?r=' + new Date().getTime() : '/guest/xPerpetuumMobile',
			params: this.params,
			method: 'GET',
			timeout: this.timeoutRequest,
			scope: this,
			hideErrorMessage: true,
			callback: function (options, success, response) {
				if (success) {
					try {
						if (this.locked && this.lockedCheck) {
							this.lockedCheck = false;
							this.run();
							return;
						} else if (this.forceCheck) {
							this.forceCheck = false;
							this.schedule();
							return;
						} else {
							var response = Ext.decode(response.responseText);
						}

						if (response.success != true)
							throw 'False';

						this.clearDom();
						this.timeoutRun = this.defaultTimeout;

						if (! response.isAuthenticated) {
							Scalr.state.userNeedLogin = true;
							Scalr.event.fireEvent('redirect', '#/guest/login', true);
							this.schedule();
							return;
						} else if (! response.equal) {
							document.location.reload();
							return;
						} else {
							if (this.locked) {
								this.locked = false;
								this.lockedCheck = true;
								Scalr.event.fireEvent('unlock');
								Scalr.storage.set('system-pm-updater-status', this.locked);
								// TODO: проверить, нужно ли совместить в unlock
								window.onhashchange(true);
							}

							this.schedule();
							return;
						}
					} catch (e) {
						this.schedule();
						return;
					}
				}

				if (response.aborted == true) {
					this.schedule();
					return;
				}

				if (response.timedout == true) {
					this.schedule();
					return;
				}

				Scalr.event.fireEvent('lock');
				this.locked = true;
				Scalr.storage.set('system-pm-updater-status', this.locked);

				var mask = Ext.get('body-timeout-mask') || Ext.getBody().createChild({
					id: 'body-timeout-mask',
					tag: 'div',
					style: {
						position: 'absolute',
						top: 0,
						left: 0,
						width: '100%',
						height: '100%',
						background: '#CCC',
						opacity: '0.5',
						'z-index': 300000
					}
				});

				this.timeoutRun += 6000;
				if (this.timeoutRun > 60000)
					this.timeoutRun = 60000;

				if (! Ext.get('body-timeout-container'))
					this.timeoutRun = 5000;

				var cont = Ext.get('body-timeout-container') || Ext.getBody().createChild({
					id: 'body-timeout-container',
					tag: 'div',
					style: {
						position: 'absolute',
						top: '5px',
						left: '5px',
						right: '5px',
						'z-index': 300001,
						background: '#F6CBBA',
						border: '1px solid #BC7D7A',
						'box-shadow': '0 1px #FEECE2 inset',
						font: 'bold 13px arial',
						color: '#420404',
						padding: '10px',
						'text-align': 'center'
					}
				}).applyStyles({ background: '-webkit-gradient(linear, left top, left bottom, from(#FCD9C5), to(#F0BCAC))'
					}).applyStyles({ background: '-moz-linear-gradient(top, #FCD9C5, #F0BCAC)' });

				this.schedule();

				cont.update('Not connected to Scalr. Connecting in <span>' + this.timeoutRun/1000 + '</span>s. <a href="#">Try now</a> ');
				cont.child('a').on('click', function (e) {
					e.preventDefault();
					cont.update('Not connected to Scalr. Trying now');
					this.undoSchedule();
					this.run();
				}, this);
				this.createTimer(cont);
			}
		});
	}
};


Scalr.Init = function (context) {
	Ext.get('loading-div-child').applyStyles('-webkit-animation: pulse 1.5s infinite;');

	new Ext.util.KeyMap(Ext.getBody(), [{
		key: Ext.EventObject.ESC,
		fn: function () {
			if (Scalr.state['pageSuspend'] == false && Scalr.application.layout.activeItem.scalrOptions.modal == true) {
				Scalr.event.fireEvent('close');
			}
		}
	}]);

	window.onunload = function () {
		Scalr.timeoutHandler.enabled = false;
		Scalr.timeoutHandler.undoSchedule();
		Scalr.timeoutHandler.clearDom();

		Ext.getBody().createChild({
			tag: 'div',
			style: {
				opacity: '0.8',
				background: '#EEE',
				'z-index': 400000,
				position: 'absolute',
				top: 0,
				left: 0,
				width: '100%',
				height: '100%'
			}
		});
	};

	/*window.onbeforeunload = function (e) {
		var message = "Where are you gone?";
		e = e || window.event;

		if (e)
			e.returnValue = message;

		return message;
	};*/

	window.onerror = function (message, file, lineno) {
		Scalr.utils.PostError({
			message: 't3 ' + message,
			file: file,
			lineno: lineno,
			url: document.location.href
		});

		return false;
	};

    Scalr.cachedRequest = Scalr.CachedRequestManager.create();
	Scalr.application.render('body-container');
	Scalr.application.applyContext(context);
};

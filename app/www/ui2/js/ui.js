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

            this.toolbarMenu = this.owner.down('#top');

			this.owner.items.on('add', function (index, o) {

				//c.style = c.style || {};
				//Ext.apply(c.style, { position: 'absolute' });

				//Ext.apply(c, { hidden: true });
				o.scalrOptions = o.scalrOptions || {};
				Ext.applyIf(o.scalrOptions, {
					reload: true, // close window before show other one
					modal: false, // mask prev window and show new one (false - don't mask, true - mask previous)
					maximize: '' // maximize which sides (all, (max-height - default))
                    // beforeClose: handler(callback) // return true if we can close form or false if we should engage user attention before close.
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
                            if (o.scalrOptions.leftMenu.showPageTitle && o.scalrOptions.title) {
                                var title = me.owner.getDockedComponent('globalTitle');
                                title.update(o.scalrOptions.title);
                                title.show();
                            }
						},
						beforedestroy: function() {
							me.owner.getPlugin('leftmenu').hide();
                            me.owner.getDockedComponent('globalTitle').hide();
						},
						hide: function() {
							me.owner.getPlugin('leftmenu').hide();
                            me.owner.getDockedComponent('globalTitle').hide();
						}
					};
					o.on(listeners);
				}
		    }, this);
		}
	},

	setActiveItem: function (newPage, param) {
		var me = this,
			oldPage = this.activeItem;

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
								(parseInt(oldPage.el.getStyle('z-index')) == (parseInt(newPage.el.getStyle('z-index')) + 1)))
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
            this.activeItem.doAutoRender();////todo: check & remove in extjs5

            this.activeItem.fireEvent('beforeactivate');
            this.activeItem.fireEvent('applyparams', param);
            this.setSize(this.activeItem);

            if (! newPage.scalrOptions.modal) {
				var docTitle = this.activeItem.title || (this.activeItem.scalrOptions ?
                        (this.activeItem.scalrOptions.menuTitle || '') + (this.activeItem.scalrOptions.menuSubTitle ? ' » ' + this.activeItem.scalrOptions.menuSubTitle : '')
                        : '');
				document.title = Ext.util.Format.stripTags(((docTitle ? (docTitle + ' - ') : '') + 'Scalr CMP').replace(/&raquo;/g, '»'));

                this.toolbarMenu.items.each(function(item) {
                    item.removeCls('x-btn-selected');
                    if (item.tmpObject)
                        this.toolbarMenu.remove(item);
                }, this);

                if (newPage.scalrOptions.maximize == 'all' && newPage.itemId != 'blank') {
                    var btn, stateId = newPage.scalrOptions.menuParentStateId || newPage.stateId;

                    if (stateId) {
                        btn = this.toolbarMenu.child('button[parentStateId="' + stateId + '"]');
                    }

                    if (! btn) {
                        btn = this.toolbarMenu.insert(this.toolbarMenu.items.indexOf(this.toolbarMenu.child('tbfill')), {
                            hrefTarget: '_self',
                            href: newPage.scalrOptions.menuHref,
                            parentStateId: stateId,
                            text: newPage.scalrOptions.menuTitle,
                            tmpObject: true,
                            cls: newPage.scalrOptions.menuFavorite ? 'x-btn-favorite-add' : '',
                            listeners: {
                                afterrender: function() {
                                    var me = this;
                                    me.favoriteEl = this.el.createChild({
                                        tag: 'div',
                                        cls: 'x-btn-favorite-div',
                                        'data-qtip': 'Add <b>' + newPage.scalrOptions.menuTitle + '</b> to bookmarks bar'
                                    });
                                    me.favoriteEl.createChild({
                                        tag: 'div'
                                    });
                                    me.favoriteEl.on('click', function(e) {
                                        e.preventDefault();
                                        if (me.favoriteElClicked)
                                            return;

                                        me.favoriteEl.addCls('x-btn-favorite-div-enabled');
                                        me.favoriteElClicked = true;
                                        me.tmpObject = false;

                                        var lst = Scalr.utils.getFavorites(Scalr.scope);
                                        lst.push({
                                            text: me.text,
                                            href: me.href,
                                            stateId: me.parentStateId
                                        });
                                        Scalr.storage.set('system-favorites-' + Scalr.scope, lst);
                                        setTimeout(function() {
                                            me.favoriteEl.hide();
                                            me.removeCls('x-btn-favorite-add');
                                            if (!Ext.state.Manager.get('system-favorites-suppress-add-message')) {
                                                Scalr.message.Success('<a href="'+me.href+'">' + newPage.scalrOptions.menuTitle + '</a> was added to your Bookmarks bar. <br/>Access <a href="#'+Scalr.utils.getUrlPrefix()+'/core/settings">User Settings</a> to reorder or change your Bookmarks.');
                                            }
                                        }, 100);
                                    });
                                }
                            }
                        });
                    }

                    btn.addCls('x-btn-selected');
                    if (newPage.title)
                        newPage.addCls('x-panel-global-title-' + Scalr.scope);
                }
			}

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
		} else {
            console.log('Debug: Component doesn\'t have updateLayout method', comp);
        }
	},

	onOwnResize: function () {
		if (this.activeItem) {
            this.activeItem.doAutoRender();//todo: check & remove in extjs5
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
		dock: 'top',
		itemId: 'top',
		enableOverflow: true,
        overflowHandler: 'scroller',
        hidden: true,
		height: 46,
		cls: 'x-topmenu-menu',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		defaults: {
			border: false
		}
    },{
        xtype: 'component',
        itemId: 'globalTitle',
        dock: 'top',
        hidden: true,
        padding: '13 6 10 12',
        cls: 'x-panel-header-default x-panel-header-text-container-default'
	},{
        itemId: 'globalWarning',
        dock: 'top',
        xtype: 'displayfield',
        hidden: true,
        cls: 'x-form-field-warning x-form-field-warning-fit'

    }],
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
			Ext.on('resize', function (width, height) {
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

Scalr.application.updateContext = function(handler, onlyVars, headers) {
	Scalr.Request({
        processBox: {
            type: 'action',
            msg: 'Loading configuration ...'
        },
		url: '/guest/xGetContext',
		headers: Ext.applyIf(headers || {}, {
            'X-Scalr-Scope': Scalr.scope
        }),
        params: {
            uiStorageTime: Scalr.storage.get('system-time')
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

Scalr.event.on('update', function (type) {
    if (type == '/account/environments/edit') {
        // update environments names in menu, todo: refactor
        Scalr.application.updateContext(Ext.emptyFn, true);
    }
});

Scalr.application.applyContext = function(context, onlyVars) {
	context = context || {};
	Scalr.user = context['user'] || {};
	Scalr.flags = context['flags'] || {};
    Scalr.acl = context['acl'] || {};
    Scalr.platforms = context['platforms'] || {};
    Scalr.os = context['os'] || {};
    Scalr.tags = context['tags'] || {};
    Scalr.farms = context['farms'] || {};
    Scalr.environments = context['environments'] || {};
    Scalr.scope = context['scope'] || '';
    Scalr.governance = context['governance'] || {};

    Scalr.application.applyHeaders();

	if (! onlyVars) {
        Scalr.application.clearCache();
    }

    if (Scalr.user.uiStorage) {
        console.log('apply new storage data');
        Scalr.storage.apply(Ext.decode(Scalr.user.uiStorage));
        delete Scalr.user.uiStorage;
    }

    this.suspendLayouts();
    this.getDockedComponent('globalWarning').hide();
	if (Scalr.user.userId) {
		this.createMenu(context['scope']);

        if (Scalr.user.envId && Scalr.getPlatformConfigValue('ec2', 'autoDisabled')) {
            // TODO: rewrite, add component by call
            this.getDockedComponent('globalWarning').setValue((new Ext.Template(Scalr.strings['aws.revoked_credentials'])).apply({envId: Scalr.user.envId})).show();
        }
	}
    this.resumeLayouts(true);

	if (! onlyVars) {
        window.onhashchange(true);
	}

    if (Ext.isDefined(Scalr.user.userId) && Ext.isDefined(Scalr.user.userName)) {
    	_trackingUserEmail = Scalr.user.userName;
    	_trackEvent("000000132973");
    }
};

Scalr.application.changeScope = function(scope, envId) {
    var headers = {};

    if (scope == 'environment') {
        envId = envId || Scalr.storage.get('system-environment-id', true);
        headers['X-Scalr-EnvId'] = envId;
        Scalr.storage.set('system-environment-id', envId, true);
        Scalr.storage.set('system-environment-id', envId); // also duplicate in localStorage
    }

    Scalr.scope = scope;
    Scalr.application.updateContext(null, false, headers);
};

Scalr.application.clearCache = function() {
    // clear cache
    Scalr.application.items.each(function(c) {
        // excludes
        if (c.itemId != 'blank') {
            if (Scalr.application.layout.activeItem == c) {
                c.fireEvent('deactivate'); // close page properly
                Scalr.application.layout.activeItem = null;
            }
            c.destroy();
        }
    });

    // clear global store
    Scalr.data.reloadDefer('*');
    Scalr.cachedRequest.clearCache();
};

Scalr.application.applyHeaders = function() {
    var headers = Ext.Ajax.getDefaultHeaders();

    if (Ext.isDefined(Scalr.user.userId)) {
        headers['X-Scalr-UserId'] = Scalr.user.userId;
        headers['X-Scalr-Scope'] = Scalr.scope;

        if (Scalr.scope == 'environment')
            headers['X-Scalr-EnvId'] = Scalr.user.envId;
        else
            delete headers['X-Scalr-EnvId'];

    } else {
        delete headers['X-Scalr-EnvId'];
        delete headers['X-Scalr-UserId'];
        delete headers['X-Scalr-Scope'];
    }

    Scalr.flags['betaMode'] = false;
    if (Scalr.user['envVars']) {
        Scalr.user['envVars'] = Ext.decode(Scalr.user['envVars'], true) || {};
        Scalr.flags['betaMode'] = Scalr.user['envVars']['beta'] == 1;
    }

    Scalr.flags['betaMode'] = Scalr.flags['betaMode'] || document.location.search.indexOf('beta') != -1;
    Scalr.flags['betaMode'] = Scalr.flags['betaMode'] && document.location.search.indexOf('!beta') == -1;

    if (Scalr.flags['betaMode']) {
        headers['X-Scalr-Interface-Beta'] = 1;
    } else {
        delete headers['X-Scalr-Interface-Beta'];
    }
    Ext.Ajax.setDefaultHeaders(headers);
    Ext.Ajax.setExtraParams(Ext.apply(Ext.Ajax.getExtraParams(), {'X-Requested-Token' : Scalr.flags['specialToken']}));
};

Scalr.application.getExtraParams = function() {
    return {
        'X-Requested-Token' : Scalr.flags['specialToken'],
        'X-Scalr-Scope': Scalr.scope,
        'X-Scalr-EnvId': Scalr.user.envId
    };
}

Scalr.application.createMenu = function(scope) {
	var ct = this.down('#top'), menu = [], mainMenu, definitions, farms = [], finAdminHide = Scalr.user.type == 'FinAdmin';

    var convertDefinition = function(items, scope) {
        var i, menu, parent, output = [], item;
        for (i = 0; i < items.length; i++) {
            item = items[i];
            if (! Ext.isArray(item)) {
                if (! (item == '-' && output[output.length - 1] == '-')) {
                    output.push(item);
                }
                continue;
            }

            if (item[3].indexOf(scope) == -1)
                continue;

            // hidden
            if (item[4])
                continue;

            menu = {
                text: item[0],
                iconCls: item[1] ? 'x-topmenu-icon-' + item[1] : ''
            };

            if (item[2]) {
                menu['href'] = item[2];
            }

            menu = Ext.apply(menu, item[5]);
            if (menu['addLinkHref']) {
                menu['xtype'] = 'menuitemtop';
            }

            if (item[6]) {
                parent = convertDefinition(item[6], scope);
                if (! Ext.isEmpty(parent)) {
                    menu['menu'] = {
                        plugins: ['bgiframe'],
                        //hideOnClick: !!menu['href'],
                        hideOnClick: true,
                        cls: (menu['menuCls'] || '') + ' x-topmenu-dropdown x-menu-' + scope,
                        items: parent
                    };
                }
            }

            output.push(menu);
        }

        return output;
    };

    if (scope == 'environment') {
        Ext.each(Scalr.farms, function (item) {
            farms.push([ item.name, '', '#/farms/view?farmId=' + item.id, ['environment']]);
        });
    }

    definitions = [
        // text, icon, href, scopes, hidden, additional properties, child menu
        [ 'Dashboard', 'dashboard', '#/dashboard', ['environment'] ],
        [ 'Account Dashboard', 'dashboard', '#/account/dashboard', ['account'] ],
        [ 'Admin Dashboard', 'dashboard', '#/admin/dashboard', ['scalr'] ],
        '-',
        [ 'Billing', 'billing', '#/account/billing', ['account'], !(Scalr.flags['billingExists'] && Scalr.isAllowed('ADMINISTRATION_BILLING'))],
        '-',
        [ 'Accounts', 'accounts', '#/admin/accounts', ['scalr']],
        [ 'Admins', 'members', '#/admin/users', ['scalr']],
        [ 'Environments', 'environments', '#/account/environments', ['account'], !(Scalr.utils.canManageAcl() || Scalr.isAllowed('ENVADMINISTRATION_ENV_CLOUDS'))],
        [ 'Teams', 'accounts', '#/account/teams', ['account'], !Scalr.utils.canManageAcl()],
        [ 'Users', 'members', '#/account/users', ['account'], !Scalr.utils.canManageAcl()],
        [ 'ACL', 'acl', '#/account/roles', ['account'], !Scalr.utils.canManageAcl()],
        '-',
        [ 'Farms', 'farms', '#/farms', ['environment'], !(Scalr.isAllowed('FARMS') || Scalr.isAllowed('OWN_FARMS') || Scalr.isAllowed('TEAM_FARMS')), { addLinkHref: '#/farms/designer', menuCls: 'x-topmenu-farms' }, farms ],
        [ 'Roles', 'roles', '#/roles', ['scalr', 'environment'], !Scalr.isAllowed('FARMS_ROLES'), { addLinkHref: '#/roles/create' }, [
            [ 'Roles Library', 'library', '#/roles', ['scalr', 'environment']],
            [ 'New Role', 'new', '#/roles/edit', ['scalr', 'environment']],
            [ 'Role Builder', 'manage', '#/roles/builder', ['environment']],
            [ 'Create Role from non-Scalr Server', 'import', '#/roles/import', ['environment'], !Scalr.isAllowed('FARMS_ROLES', 'create')]
        ]],
        [ 'Images', 'images', '#/admin/images', ['scalr'], false, { addLinkHref: '#/admin/images/create' }],
        [ 'Images', 'images', '#/images', ['environment'], !Scalr.isAllowed('FARMS_IMAGES'), { addLinkHref: '#/images/create' }, [
            [ 'Images Library', 'library', '#/images', ['scalr', 'environment']],
            [ 'Register existing Image', 'new', '#/images/create?register', ['scalr', 'environment'], !Scalr.isAllowed('FARMS_IMAGES', 'create')],
            [ 'Image Builder', 'manage', '#/roles/builder?image', ['environment'], !Scalr.isAllowed('FARMS_IMAGES', 'create')],
            [ 'Create Image from non-Scalr Server', 'import', '#/roles/import?image', ['environment'], !Scalr.isAllowed('FARMS_IMAGES', 'create')],
            [ 'Bundle Tasks', 'bundletasks', '#/bundletasks', ['environment'], !Scalr.isAllowed('FARMS_ROLES', 'bundletasks')]
        ]],
        [ 'Servers', 'servers', '#/servers', ['environment'], !(Scalr.isAllowed('FARMS', 'servers') || Scalr.isAllowed('OWN_FARMS', 'servers') || Scalr.isAllowed('TEAM_FARMS', 'servers') || Scalr.isAllowed('FARMS_ROLES', 'create'))],
        [ 'Scripts', 'scripts', '#/scripts', ['environment'], !Scalr.isAllowed('ADMINISTRATION_SCRIPTS'), { addLinkHref: '#/scripts?new=true' }],
        [ 'Scripts', 'scripts', '#/admin/scripts', ['scalr'], false, { addLinkHref: '#/admin/scripts?new=true' }],
        [ 'Scripts', 'scripts', '#/account/scripts', ['account'], !Scalr.isAllowed('ADMINISTRATION_SCRIPTS'), { addLinkHref: '#/account/scripts?new=true' }],
        [ 'Logs', 'logs', '', ['environment'], !(Scalr.isAllowed('LOGS_EVENT_LOGS') || Scalr.isAllowed('LOGS_SYSTEM_LOGS') || Scalr.isAllowed('LOGS_API_LOGS') || Scalr.isAllowed('LOGS_SCRIPTING_LOGS')), {}, [
            [ 'Event log', 'events', '#/logs/events', ['environment'], !Scalr.isAllowed('LOGS_EVENT_LOGS')],
            [ 'System log', 'logs', '#/logs/system', ['environment'], !Scalr.isAllowed('LOGS_SYSTEM_LOGS')],
            [ 'Scripting log', 'logs', '#/logs/scripting', ['environment'], !Scalr.isAllowed('LOGS_SCRIPTING_LOGS')],
            [ 'API Log', 'logs', '#/logs/api', ['environment'], !Scalr.isAllowed('LOGS_API_LOGS')]
        ]],
        [ 'SSH Keys', 'sshkeys', '#/sshkeys', ['environment'], !Scalr.isAllowed('SECURITY_SSH_KEYS')],
        '-',
        [ 'DNS Zones', 'dnszones', '#/dnszones', ['environment'], !Scalr.isAllowed('DNS_ZONES'), { addLinkHref: '#/dnszones/create' }],
        [ 'Default DNS Records', 'dnszones', '#/dnszones/defaultRecords', ['scalr']],
        [ 'Governance', 'governance', '#/core/governance', ['environment'], !Scalr.isAllowed('ENVADMINISTRATION_GOVERNANCE')],
        [ 'Cost Analytics', 'analytics', '#/analytics/dashboard', ['environment'], !Scalr.flags['analyticsEnabled'] || !Scalr.isAllowed('ENVADMINISTRATION_ANALYTICS')],
        [ 'Cost Analytics', 'analytics', '#/account/analytics/environments', ['account'], !Scalr.flags['analyticsEnabled'] || !Scalr.isAllowed('ADMINISTRATION_ANALYTICS')],
        [ 'Cost Analytics', 'analytics', '#/admin/analytics/dashboard', ['scalr'], !Scalr.flags['analyticsEnabled']],
        [ 'Global Variables', 'variables', '#/core/variables', ['environment'], !Scalr.isAllowed('ENVADMINISTRATION_GLOBAL_VARIABLES')],
        [ 'Global Variables', 'variables', '#/account/variables', ['account'], !Scalr.isAllowed('ADMINISTRATION_GLOBAL_VARIABLES')],
        [ 'Global Variables', 'variables', '#/admin/variables', ['scalr']],
        [ 'Tasks Scheduler', 'scheduler', '#/schedulertasks', ['environment'], !Scalr.isAllowed('GENERAL_SCHEDULERTASKS')],
        [ 'Webhooks', 'webhooks', '#/webhooks/endpoints', ['environment'], !Scalr.isAllowed('ENVADMINISTRATION_WEBHOOKS')],
        [ 'Webhooks', 'webhooks', '#/admin/webhooks/endpoints', ['scalr']],
        [ 'Webhooks', 'webhooks', '#/account/webhooks/endpoints', ['account'], !Scalr.isAllowed('ADMINISTRATION_WEBHOOKS')],
        [ 'Custom Events', 'events', '#/admin/events', ['scalr'], !Scalr.isAllowed('GENERAL_CUSTOM_EVENTS')],
        [ 'Custom Events', 'events', '#/scripts/events', ['environment'], !Scalr.isAllowed('GENERAL_CUSTOM_EVENTS')],
        [ 'Custom Events', 'events', '#/account/events', ['account'], !Scalr.isAllowed('GENERAL_CUSTOM_EVENTS')],
        [ 'Custom Scaling Metrics', 'metrics', '#/scaling/metrics', ['environment'], !Scalr.isAllowed('GENERAL_CUSTOM_SCALING_METRICS')],
        [ 'Operating Systems', 'os', '#/admin/os', ['scalr']],
        '-',
        [ 'Chef servers', 'chef', '#/admin/services/chef/servers', ['scalr'] ],
        [ 'Chef servers', 'chef', '#/services/chef/servers', ['environment'], !Scalr.isAllowed('SERVICES_ENVADMINISTRATION_CHEF') ],
        [ 'Chef servers', 'chef', '#/account/services/chef/servers', ['account'], !Scalr.isAllowed('SERVICES_ADMINISTRATION_CHEF') ],
        '-'
    ];

    if (Scalr.isPlatformEnabled('ec2')) {
        definitions.push(
            '-',
            [ 'AWS', 'aws', '', ['environment'], false, {}, [
                [ 'S3 & Cloudfront', 's3', '#/tools/aws/s3/manageBuckets', ['environment'], !Scalr.isAllowed('AWS_S3') ],
                [ 'IAM SSL Certificates', 'sslcertificates', '#/tools/aws/iam/servercertificates/view', ['environment'], !Scalr.isAllowed('AWS_IAM') ],
                [ 'Security groups', 'security', '#/security/groups/view?platform=ec2', ['environment'], !Scalr.isAllowed('SECURITY_SECURITY_GROUPS') ],
                [ 'EC2 EIPs', 'eips', '#/tools/aws/ec2/eips', ['environment'], !Scalr.isAllowed('AWS_ELASTIC_IPS') ],
                [ 'EC2 ELB', 'elb', '#/tools/aws/ec2/elb', ['environment'], !Scalr.isAllowed('AWS_ELB') ],
                [ 'EBS Volumes', 'volumes', '#/tools/aws/ec2/ebs/volumes', ['environment'], !Scalr.isAllowed('AWS_VOLUMES') ],
                [ 'EBS Snapshots', 'snapshots', '#/tools/aws/ec2/ebs/snapshots', ['environment'], !Scalr.isAllowed('AWS_SNAPSHOTS') ],
                [ 'Route53', 'route53', '#/tools/aws/route53', ['environment'], !Scalr.isAllowed('AWS_ROUTE53') ]
            ]]
        );

        if (Scalr.isAllowed('AWS_RDS')) {
            definitions.push(
                '-',
                [ 'RDS', 'rds', '', ['environment'], false, {}, [
                    [ 'DB Instances', 'instances', '#/tools/aws/rds/instances', ['environment']],
                    [ 'Security groups', 'security', '#/tools/aws/rds/sg/view', ['environment']],
                    [ 'Parameter groups', 'settings', '#/tools/aws/rds/pg/view', ['environment']],
                    [ 'DB Snapshots', 'snapshots', '#/tools/aws/rds/snapshots', ['environment']]
                ]]
            );
        }
    }

    Ext.Array.each(['cloudstack', 'idcf'], function(platform){
        if (Scalr.isPlatformEnabled(platform) && (Scalr.isAllowed('CLOUDSTACK_VOLUMES') || Scalr.isAllowed('CLOUDSTACK_SNAPSHOTS') || Scalr.isAllowed('CLOUDSTACK_PUBLIC_IPS'))) {
            definitions.push(
                '-',
                [ Scalr.utils.getPlatformName(platform), platform, '', ['environment'], false, {}, [
                    [ 'Volumes', 'volumes', '#/tools/cloudstack/volumes?platform=' + platform, ['environment'], !Scalr.isAllowed('CLOUDSTACK_VOLUMES')],
                    [ 'Snapshots', 'snapshots', '#/tools/cloudstack/snapshots?platform=' + platform, ['environment'], !Scalr.isAllowed('CLOUDSTACK_SNAPSHOTS')],
                    [ 'Public IPs', 'ips', '#/tools/cloudstack/ips?platform=' + platform, ['environment'], !Scalr.isAllowed('CLOUDSTACK_PUBLIC_IPS')],
                    [ 'Security groups', 'security', '#/security/groups/view?platform=' + platform, ['environment'], !Scalr.isAllowed('SECURITY_SECURITY_GROUPS')]
                ]]
            );
        }
    });

    Ext.Array.each(['openstack', 'ecs', 'nebula', 'ocs', 'mirantis', 'vio', 'verizon', 'cisco', 'hpcloud'], function(platform){
        if (Scalr.isPlatformEnabled(platform)) {
            definitions.push(
                '-',
                [ Scalr.utils.getPlatformName(platform), platform, '', ['environment'], false, {}, [
                    [ 'Volumes', 'volumes', '#/tools/openstack/volumes?platform=' + platform, ['environment'], !Scalr.isAllowed('OPENSTACK_VOLUMES')],
                    [ 'Snapshots', 'snapshots', '#/tools/openstack/snapshots?platform=' + platform, ['environment'], !Scalr.isAllowed('OPENSTACK_SNAPSHOTS')]
                    [ 'Security groups', 'security', '#/security/groups/view?platform=' + platform, ['environment'], !Scalr.isAllowed('SECURITY_SECURITY_GROUPS') && Scalr.getPlatformConfigValue(platform, 'ext.securitygroups_enabled') == 1],
                    /*[ 'Load balancers', 'pools', '', ['environment'], !Scalr.isAllowed('OPENSTACK_ELB') && Scalr.getPlatformConfigValue(platform, 'ext.lbaas_enabled') == 1, {}, [
                        [ 'Pools', 'pools', '#/tools/openstack/lb/pools?platform=' + platform, ['environment']],
                        [ 'Members', 'members', '#/tools/openstack/lb/members?platform=' + platform, ['environment']],
                        [ 'Monitors', 'monitors', '#/tools/openstack/lb/monitors?platform=' + platform, ['environment']]
                    ]],*/
                    [ 'Details', 'info', '#/tools/openstack/details?platform=' + platform, ['environment']]
                ]]
            );
        }
    });

    if (Scalr.isPlatformEnabled('rackspace')) {
        definitions.push(
            '-',
            [ 'Rackspace', 'rackspace', '', ['environment'], false, {}, [
                [ 'Limits status', 'limits', '#/tools/rackspace/limits', ['environment'] ]
            ]]
        );
    }

    if (Scalr.isPlatformEnabled('gce') && (Scalr.isAllowed('GCE_STATIC_IPS') || Scalr.isAllowed('GCE_PERSISTENT_DISKS') || Scalr.isAllowed('GCE_SNAPSHOTS'))) {
        definitions.push(
            '-',
            [ Scalr.utils.getPlatformName('gce'), 'gce', '', ['environment'], false, {}, [
                [ 'Static IPs', 'ips', '#/tools/gce/addresses', ['environment'], !Scalr.isAllowed('GCE_STATIC_IPS')],
                [ 'Persistent disks', 'persistentdisks', '#/tools/gce/disks', ['environment'], !Scalr.isAllowed('GCE_PERSISTENT_DISKS')],
                [ 'Snapshots', 'snapshots', '#/tools/gce/snapshots', ['environment'], !Scalr.isAllowed('GCE_SNAPSHOTS')]
            ]]
        );
    }

    definitions.push(
        '-',
        [ 'Orchestration', 'scripts', '#/account/orchestration', ['account'], !Scalr.isAllowed('ADMINISTRATION_ORCHESTRATION') ],
        '-',
        [ 'SSL Certificates', 'sslcertificates', '#/services/ssl/certificates', ['environment'], !Scalr.isAllowed('SERVICES_SSL')],
        [ 'Apache Virtual Hosts', 'apachevhosts', '#/services/apache/vhosts', ['environment'], !Scalr.isAllowed('SERVICES_APACHE'), { addLinkHref: '#/services/apache/vhosts/create' }],
        [ 'DB Backups', 'dbbackups', '#/db/backups', ['environment'], !Scalr.isAllowed('DB_BACKUPS')]
    );

    if (Scalr.flags['showDeprecatedFeatures']) {
        definitions.push(
            '-',
            [ 'Deployments', 'deployments', '', ['environment'], !(Scalr.isAllowed('DEPLOYMENTS_APPLICATIONS') || Scalr.isAllowed('DEPLOYMENTS_SOURCES') || Scalr.isAllowed('DEPLOYMENTS_TASKS')), {}, [
                [ 'Deployments', 'deployments', '#/dm/tasks/view', ['environment'], !Scalr.isAllowed('DEPLOYMENTS_TASKS')],
                [ 'Sources', 'dmsources', '#/dm/sources/view', ['environment'], !Scalr.isAllowed('DEPLOYMENTS_SOURCES')],
                [ 'Applications', 'dmapplications', '#/dm/applications/view', ['environment'], !Scalr.isAllowed('DEPLOYMENTS_APPLICATIONS')]
            ]],
            [ 'Server Config Presets', 'presets', '#/services/configurations/presets', ['environment'], !Scalr.isAllowed('DB_SERVICE_CONFIGURATION')]
        );
    }

    mainMenu = convertDefinition(definitions, scope);
    mainMenu.unshift({
        xtype: 'filterfield',
        emptyText: 'Menu filter',
        cls: 'x-menu-item-cmp-search x-form-filterfield',
        listeners: {
            change: {
                fn: function (field, value) {
                    var menuCt = field.up(), items = menuCt.items.items, j;

                    if (value.length < 2)
                        value = '';
                    else
                        value = value.toLowerCase();

                    var search = function (ct) {
                        var flag = false;

                        if (ct.menu) {
                            for (j = 0; j < ct.menu.items.items.length; j++) {
                                var t = search(ct.menu.items.items[j]);
                                flag = flag || t;
                            }
                        }

                        if (ct.xtype == 'filterfield')
                            flag = true;

                        if (ct.text && value && ct.text.toLowerCase().indexOf(value) != -1) {
                            if (!flag && ct.menu) {
                                // found only root menu item, so highlight all childrens
                                for (j = 0; j < ct.menu.items.items.length; j++) {
                                    ct.menu.items.items[j].show();
                                }
                            }
                            flag = true;
                        }

                        if (flag || !value)
                            ct.show();
                        else
                            ct.hide();

                        return flag;
                    };

                    menuCt.suspendLayouts();

                    for (var i = 0; i < items.length; i++)
                        search(items[i]);

                    menuCt.resumeLayouts(true);
                },
                buffer: 200
            },
            specialkey: function(field, e) {
                if (e.getKey() == e.ENTER) {
                    var visibleEls = field.up().query('{isVisible()}');
                    if (visibleEls.length == 2 && visibleEls[1].isMenuItem) {
                        visibleEls[1].onClick(e);
                    }
                }
            }
        }
    });

    menu.push({
        iconCls: 'x-scalr-icon',
        itemId: 'mainMenu',
        hideOnClick: false,
        cls: 'x-btn-scalr x-btn-favorite', // vertical separator
        listeners: {
            boxready: function () {
                // fix height of vertical separator
                if (this.menu) {
                    Ext.override(this.menu, {
                        afterComponentLayout: function(width, height, oldWidth, oldHeight) {
                            var me = this;
                            me.callOverridden();

                            if (me.showSeparator && me.items.getAt(1)) {
                                var y = me.items.getAt(1).el.getTop(true); // top coordinate for first separator after textfield
                                me.iconSepEl.setTop(y);
                                me.iconSepEl.setHeight(me.componentLayout.lastComponentSize.contentHeight - y);
                            }
                        }
                    });
                }
            }
        },
        menuAlign: 'tl-bl',
        menu: finAdminHide ? null : {
            cls: 'x-topmenu-dropdown x-menu-' + scope,
            items: mainMenu,
            plugins: ['bgiframe'],
            listeners: {
                beforeshow: function() {
                    var me = this;
                    me.maxHeight = Scalr.application.getSize()['height'] - 50; // topmenu's height
                },
                show: function() {
                    // save width on first layout to prevent shrinking page when we process search
                    if (! this.minWidthSetBy) {
                        var minWidth = this.minWidth = this.getWidth();
                        this.minWidthSetBy = true;
                        this.items.each(function(item) {
                            item.minWidth = minWidth;
                        });
                    }

                    this.down('textfield').focus(true, true);
                }
            }
        }
    });

    Ext.each(Scalr.utils.getFavorites(scope), function(item) {
        menu.push({
            hrefTarget: '_self',
            href: item['href'],
            parentStateId: item['stateId'],
            text: item['text'],
            cls: 'x-btn-favorite'
        });
    }, this);

    menu.push({
        xtype: 'tbfill'
    });

    if (scope == 'environment' || scope == 'account') {
        var envs = [];
        envs.push({
            iconCls: 'x-topmenu-icon-manage',
            text: 'Manage account',
            href: '#/account/dashboard'
        }, {
            xtype: 'menuseparator'
        });

        Ext.each(Scalr.environments, function(item) {
            envs.push({
                text: item.name,
                href: item.id == Scalr.user.envId ? null : '#?environmentId=' + item.id + '/dashboard',
                checked: item.id == Scalr.user.envId,
                group: 'environment',
                envId: item.id
            });
        });

        menu.push({
            iconCls: 'x-icon-environment',
            cls: 'x-btn-environment',
            text: Scalr.user['envName'],
            menu: {
                plugins: ['bgiframe'],
                cls: 'x-topmenu-dropdown x-menu-' + scope,
                items: envs
            },
            listeners: {
                boxready: function() {
                    Scalr.event.on('update', function (type, env) {
                        if (type == '/account/environments/create') {
                            this.menu.add({
                                text: env.name,
                                href: '#?environmentId=' + env.id + '/dashboard',
                                checked: false,
                                group: 'environment',
                                envId: env.id
                            });
                        } else if (type == '/account/environments/rename') {
                            var el = this.menu.child('[envId="' + env.id + '"]');
                            if (el) {
                                el.setText(env.name);
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
    }

    menu.push({
        xtype: 'button',
        iconCls: 'x-icon-changelog',

        listeners: {
            afterrender: function() {
                var me = this;
                me.newsCounter = Ext.DomHelper.append(me.el, {tag: 'div', cls: 'x-menu-changelog-news-counter', hidden: true}, true);
                me.getChangelog();
            }
        },

        handler: function () {
            var me = this;

            me.showChangelog();
            me.resetNewsCounter();
        },

        updateNewsCounter: function(newsCount) {
            var me = this;
            me.newsCounter.update(newsCount);
            me.newsCounter.setVisible(!! newsCount);
        },

        resetNewsCounter: function () {
            var me = this;

            Scalr.Request({
                url: '/core/xGetChangeLog?resetCounter=true',
                success: function () {
                    Scalr.state.changelogData['countNew'] = 0;
                    me.updateNewsCounter(0);
                }
            });
        },

        getChangelog: function () {
            var me = this;

            if (Scalr.state.changelogData && ((Date.now() - Scalr.state.changelogData['tm']) > 3600)) {
                me.updateNewsCounter(Scalr.state.changelogData['countNew']);
            } else {
                Scalr.Request({
                    method: 'GET',
                    url: '/core/xGetChangeLog',
                    success: function (data) {
                        // fill required min 5 values
                        for (var i = 0, len = 5 - data['data'].length; i < len; i++) {
                            data['data'].push({});
                        }

                        Scalr.state.changelogData = data;
                        me.updateNewsCounter(data['countNew']);
                    }
                });
            }
        },

        showChangelog: function () {
            var me = this;

            Scalr.utils.Window({
                title: 'What\'s new in Scalr',
                width: 800,
                closable: true,
                plugins: ['bgiframe'],
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },

                items: [{
                    html:
                        '<div class="x-menu-changelog-div" style="padding-top: 15px">' +
                            '<img style="margin-right: 6px" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-info" />' +
                            '<a href="http://www.scalr.com/scalr-product-blog" target="_blank">Sign up</a>' +
                            '<span> for email notifications from the Product Blog</span>' +
                        '</div>'
                }, {
                    xtype: 'dataview',
                    scrollable: 'vertical',
                    cls: 'x-menu-changelog-grid',
                    flex: 1,
                    disableSelection: true,
                    store: {
                        fields: ['text', 'url', 'time', 'timestamp', 'new'],
                        proxy: 'object',
                        data: Scalr.state.changelogData['data']
                    },
                    itemSelector: 'x-menu-changelog-div',
                    tpl: [
                        '<tpl for=".">',
                            '<div class="x-menu-changelog-div">',
                                '<div class="x-menu-changelog-desc">{time}</div>',
                                '<div>' +
                                    '<a href="{url}" target="_blank"><span class="x-menu-changelog-message-slim">{text}</span></a>' +
                                    '<tpl if="new"><span style="margin-left: 5px; cursor: pointer;" class="x-menu-changelog-info">New</span></tpl>' +
                                '</div>',
                            '</div>',
                        '</tpl>'
                    ],
                    listeners: {
                        itemclick: function (view, record, item, index, e) {
                            var target = e.getTarget();

                            if (target.className === 'x-menu-changelog-message-slim') {
                                if (typeof _gaq !== 'undefined') {
                                    _gaq.push(['_trackEvent', 'ProductBlog', 'Open', target.innerHTML]);
                                }
                            }
                        }
                    }
                }],

                listeners: {
                    destroy: function () {
                        Ext.each(Scalr.state.changelogData['data'], function (record) {
                            record['new'] = false;
                        });
                    }
                }
            });
        }
    });

    if (! finAdminHide) {
        menu.push({
            iconCls: 'x-icon-help',
            menu: {
                plugins: ['bgiframe'],
                cls: 'x-topmenu-dropdown x-menu-' + scope,
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
                    text: 'About Scalr',
                    href: '#/core/about',
                    iconCls: 'x-topmenu-icon-info'
                }]
            }
        });
    }

    var userDefinitions = [
        [ 'API access', 'api', '#/core/api', ['account', 'environment']],
        [ 'APIv2 access', 'api', '#/core/api2', ['account', 'environment'], !Scalr.flags['apiEnabled'] || !Scalr.flags['betaMode']],
        [ 'Security', 'security', '#/core/security', ['scalr', 'account', 'environment'], finAdminHide],
        [ 'Settings', 'settings', '#/core/settings', ['environment']],
        [ 'Settings', 'settings', '#/account/core/settings', ['account']],
        [ 'Debug', 'settings', '#/admin/utils/debug', ['scalr'], finAdminHide],
        '-',
        [ 'Logout', 'logout', '/guest/logout', ['scalr', 'account', 'environment']],
    ], userMenu = convertDefinition(userDefinitions, scope);

    userMenu.unshift({
        xtype: 'component',
        style: 'margin: 0',
        cls: 'x-topmenu-user-menu',
        data: Scalr.user,
        indent: false,
        tpl: '<div style="font: 14px OpenSansSemiBold; color: white; padding: 18px 25px">{userName}</div>' +
             '<tpl if="accountOwnerName"><div style="font: 14px OpenSansRegular; color: white; padding: 4px 25px 18px 25px ">This account is managed by {accountOwnerName}</div></tpl>'
    });

    menu.push({
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
            plugins: ['bgiframe'],
            cls: 'x-topmenu-dropdown x-menu-' + scope,
            items: userMenu
        }
    });

    /*} else if (Scalr.user.type == 'FinAdmin') {
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
    } else {*/

	ct.removeAll();
    ct.removeCls('x-topmenu-menu-scalr x-topmenu-menu-account x-topmenu-menu-environment');
    ct.addCls('x-topmenu-menu-' + scope);
	ct.add(menu);
    ct.show();
};

window.onhashchange = function (e) {
	if (Scalr.state.pageSuspendForce) {
		Scalr.state.pageSuspendForce = false;
	} else {
		if (Scalr.state.pageSuspend)
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

    var h = window.location.hash.substring(1), link = '', param = {}, loaded = false, defaultPage = false, environmentId = '';
    // exclude first symbol #

    if (!Scalr.user.userId && h.substring(0, 8) != '/public/') {
        if (Scalr.application.layout.firstRun) {
            Scalr.application.layout.setActiveItem(Scalr.application.getComponent('blank'));
        }
        Scalr.utils.authWindow.show();
        return;
    }

    if (h.substring(0, 15) == '?environmentId=') {
        var ind = h.indexOf('/', 15);
        if (ind != -1) {
            environmentId = h.substring(15, ind);
            h = h.substring(ind);

            history.replaceState(
                null, null, '#' + h
            );
        }
    }

    h = h.split('?');

    if (Scalr.state.pageOpenModalWindow) {
        h = Scalr.state.pageOpenModalWindow.substring(1).split('?');
        link = h[0];
        param = h[1] ? Ext.urlDecode(h[1]) : param;

    } else {
        if (h[0])
            link = h[0];
        // cut ended /  (/logs/view'/')

        if (h[1])
            param = Ext.urlDecode(h[1]);

        if (link == '' || link == '/' || link == '/guest/login') {
            document.location.href = Scalr.flags.needEnvConfig ? '#/account/dashboard' : '#/dashboard';
            return;
        }
    }

    var activeWindow = Ext.WindowManager.getActive();
    if (activeWindow && activeWindow.itemId == 'box' && e && !Scalr.state.pageOpenModalWindow) {
        // change link, then open modal window, return page in browser => block change, close modal window, return correct link
        // !Scalr.state.pageOpenModalWindow -> allow to open modal window over modal window
        activeWindow.close();

        if (!activeWindow.loadingErrorWindow) {
            if (e && e.oldURL) {
                returnToOldUrl(e.oldURL);
            }

            return;
        }
    }

    if (Scalr.state.pageChangeInProgress) {
        Scalr.state.pageChangeInProgressInvalid = true; // User changes link while loading page
        if (Scalr.state.pageChangeInProgressRequest)
            Ext.Ajax.abort(Scalr.state.pageChangeInProgressRequest);
        return;
    }

    // check beforeClose
    if (!Scalr.state.pageOpenModalWindow && Scalr.application.layout.activeItem && Scalr.application.layout.activeItem.scalrOptions && Ext.isFunction(Scalr.application.layout.activeItem.scalrOptions.beforeClose)) {
        var comp = Scalr.application.layout.activeItem, callback = function() {
            Scalr.event.fireEvent('close');
        };

        if (!comp.scalrOptions.beforeClose.call(comp, callback)) {
            if (e && e.oldURL) {
                returnToOldUrl(e.oldURL);
            }

            return;
        }
    }

    // we are ready to move, let's check scope before
    var linkScopeAccount = link.substring(0, 9) == '/account/';
    if (Scalr.scope == 'environment' && linkScopeAccount) {
        Scalr.application.changeScope('account');
        return;
    } else if (Scalr.scope == 'account' && !linkScopeAccount || environmentId) {
        Scalr.application.changeScope('environment', environmentId);
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

    Ext.apply(param, Scalr.state.pageRedirectParams);
    Scalr.state.pageRedirectParams = {};

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
        finishChange();
		return;
	}

    var showErrorWindowPopup = function(message) {
        Scalr.utils.Window({
            width: 500,
            cls: 'x-panel-shadow x-panel-confirm',
            loadingErrorWindow: true,
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
                    width: 130,
                    handler: function() {
                        this.up('panel').close();
                        Scalr.message.Flush(true);
                        Scalr.event.fireEvent('refresh');
                    }
                }, {
                    xtype: 'button',
                    text: 'Go to dashboard',
                    height: 32,
                    margin: '0 0 0 12',
                    width: 150,
                    handler: function() {
                        this.up('panel').close();
                        Scalr.message.Flush(true);
                        Scalr.event.fireEvent('redirect', '#' + Scalr.utils.getUrlPrefix() + '/dashboard');
                    }
                }, {
                    xtype: 'button',
                    text: 'Back',
                    height: 32,
                    width: 130,
                    margin: '0 0 0 12',
                    handler: function() {
                        Scalr.message.Flush(true);
                        this.up('panel').close();

                        if (Scalr.state.pageOpenModalWindow)
                            Scalr.state.pageOpenModalWindow = '';
                        else
                            Scalr.event.fireEvent('close');
                    }
                }]
            }]
        });
    };

	var r = {
		disableFlushMessages: true,
		disableAutoHideProcessBox: true,
        hideErrorMessage: true,
		url: link,
		params: param,
		success: function (data, response, options) {
            Scalr.state.pageChangeInProgressRequest = false;
            Scalr.state.pageOpenModalWindow = '';

            try {
                if (data['moduleUiHash'] && Scalr.state.pageUiHash && data['moduleUiHash'] != Scalr.state.pageUiHash)
                    Scalr.state.pageReloadRequired = true; // reload page, when non-modal window created

				var c = 'Scalr.' + data.moduleName.replace('/ui2/js/', '').replace(/-[0-9]+.js/, '').replace(/\//g, '.'), cacheId = response.getResponseHeader('X-Scalr-Cache-Id'), cache = cacheLink(link, cacheId);
                var initComponent = function (name, params, moduleParams) {
                    var c;

                    try {
                        c = Scalr.cache[name](params, moduleParams);
                    } catch (e) {
                        if (e && e.name == 'Error' && e.message.match(/^\[Ext\.create].*$/)) {
                            // we've got issues with init of some classes
                            if (Scalr.state.pageReloadRequired) {
                                // may be that classes haven't been loaded yet
                                Scalr.utils.CreateProcessBox({
                                    type: 'action'
                                });
                                Scalr.event.fireEvent('reload');
                            } else {
                                showErrorWindowPopup(e);
                            }
                        }
                    }

                    if (Ext.isObject(c)) {
						Ext.apply(c, cache);
                        if (c.scalrOptions && c.scalrOptions.modalWindow) {
                            if (options.processBox)
                                options.processBox.destroy();

                            finishChange();

                        } else {
                            Scalr.application.add(c);
                            addStatisticGa(c.scalrCacheStr);

                            if (Scalr.state.pageChangeInProgressInvalid) {
                                if (options.processBox)
                                    options.processBox.destroy();

                                finishChange();
                            } else {
                                Scalr.application.layout.setActiveItem(c, param);

                                if (options.processBox)
                                    options.processBox.destroy();

                                finishChange();
                            }
                        }
					} else {
						if (options.processBox)
							options.processBox.destroy();

                        Scalr.application.layout.setActiveItem(Scalr.application.getComponent('blank'));
						finishChange();
					}
				};
				var loadModuleData = function(c, param, data) {
					if (data.moduleRequiresData) {
						Scalr.data.load(data.moduleRequiresData, function() {
                            initComponent(c, param, data.moduleParams);
						});
					} else {
                        initComponent(c, param, data.moduleParams);
                    }
				};

				Ext.apply(param, cache.scalrParamGets(link));
                data.moduleRequiresMain = data.moduleRequiresMain || [];
                data.moduleRequires = data.moduleRequires || [];

                // get loaded js files
                var domScripts = document.getElementsByTagName('script'), loadedScripts = [], i, attr;
                for (i = 0; i < domScripts.length; i++) {
                    attr = domScripts[i].getAttribute('src');
                    if (attr)
                        loadedScripts.push(attr);
                }
                data.moduleRequiresMain = Ext.Array.difference(data.moduleRequiresMain, loadedScripts); // also reload all changed files
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

                if (data.moduleRequiresMain.length || data.moduleRequires.length || data.moduleRequiresCss.length) {
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

                    if (data.moduleRequiresMain.length) {
                        Ext.Loader.loadScalrScriptList(data.moduleRequiresMain, function() {
                            if (data.moduleRequires.length) {
                                Ext.Loader.loadScalrScriptList(data.moduleRequires, function() {
                                    loadModuleData(c, param, data);
                                });
                            } else {
                                loadModuleData(c, param, data);
                            }
                        });
                    } else {
                        loadModuleData(c, param, data);
                    }
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

            var message;

            if (data && data.errorMessage) {
                message = data.errorMessage;
            } else if (response.status == 403) {
                Scalr.state.userNeedLogin = true;
                Scalr.state.userNeedRefreshPageAfter = true;
                Scalr.utils.authWindow.show();
            } else if (response.status == 404) {
                message = 'Page not found.';
            } else if (response.timedout == true) {
                message = 'Server didn\'t respond in time. Please try again in a few minutes.';
            } else if (response.aborted == true) {
                message = 'Request was aborted by user.';
            } else {
                Scalr.utils.timeoutHandler.schedule(true);
                Scalr.state.userNeedRefreshPageAfter = true;
                message = 'Cannot proceed with your request. Please try again later.';
            }

            if (Scalr.application.layout.firstRun) {
                Scalr.application.layout.setActiveItem(Scalr.application.getComponent('blank'));
            }

            if (!response.aborted && !Scalr.state.userNeedRefreshPageAfter) {
                showErrorWindowPopup(message);
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

Scalr.Init = function (context) {
	Ext.get('loading-div-child').applyStyles('-webkit-animation: pulse 1.5s infinite;');

	new Ext.util.KeyMap(Ext.getBody(), [{
		key: Ext.event.Event.ESC,
		fn: function () {
			if (Scalr.state['pageSuspend'] == false && Scalr.application.layout.activeItem.scalrOptions.modal == true) {
                // ESC event cancel XMLHTTPRequest, but we don't want to cancel ESC event, so just make some timeout before close page
                Ext.Function.defer(function() {
                    Scalr.event.fireEvent('close');
                }, 50);
            }
		}
	}]);

	window.onunload = function () {
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

    Scalr.utils.authWindow = Scalr.utils.authWindow(context['flags']);
    Scalr.utils.timeoutHandler.schedule();
    Scalr.utils.fillFavorites();
    Scalr.cachedRequest = Scalr.CachedRequestManager.create();
	Scalr.application.render('body-container');
	Scalr.application.applyContext(context);
};

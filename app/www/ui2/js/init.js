// catch server error page (404, 403, timeOut and other)
Ext.Ajax.on('requestexception', function(conn, response, options) {
	if (options.hideErrorMessage == true)
		return;

	// this messages are used in window.onhashchange at ui.js
    if (response.status == 403) {
		Scalr.state.userNeedLogin = true;
		Scalr.event.fireEvent('redirect', '#/guest/login', true);
	} else if (response.status == 404) {
		Scalr.message.Error('Page not found.');
	} else if (response.timedout == true) {
		Scalr.message.Error('Server didn\'t respond in time. Please try again in a few minutes.');
	} else if (response.aborted == true) {
		//Scalr.message.Error('Request was aborted by user.');
	} else {
		if (Scalr.timeoutHandler.enabled) {
			Scalr.timeoutHandler.undoSchedule();
			Scalr.timeoutHandler.run();

			//Scalr.timeoutHandler.forceCheck = true;
			//Scalr.timeoutHandler.restart();
		}
		Scalr.message.Error('Cannot proceed with your request. Please try again later.');
	}
});

(function() {
    var handler = function(conn, response) {
        if (Scalr.flags['betaMode']) {
            try {
                var h = response.getResponseHeader('X-Scalr-Debug');
                if (h) {
                    console.debug(Ext.decode(h, true));
                }
            } catch (e) {}
        }
    }

    Ext.Ajax.on('requestcomplete', handler);
    Ext.Ajax.on('requestexception', handler);
})();

Scalr.storage = {
	prefix: 'scalr-',
	getStorage: function (session) {
		session = session || false;
		if (session)
			return sessionStorage;
		else
			return localStorage;
	},
	getName: function (name) {
		return this.prefix + name;
	},
	listeners: {},
	encodeValue: (new Ext.state.Provider()).encodeValue,
	decodeValue: (new Ext.state.Provider()).decodeValue,

	get: function (name, session) {
		var storage = this.getStorage(session);
		return storage ? this.decodeValue(storage.getItem(this.getName(name))) : '';
	},
	set: function (name, value, session) {
		var storage = this.getStorage(session);
		try {
			if (storage) {
                storage.setItem(this.getName(name), this.encodeValue(value));
            }
		} catch (e) {
			if (e == QUOTA_EXCEEDED_ERR) {
				Scalr.message.Error('LocalStorage overcrowded');
			}
		}
	},
	clear: function (name, session) {
		var storage = this.getStorage(session);
		if (storage) {
            storage.removeItem(this.getName(name));
        }
	},
    // encoded = true | false | 'decode'
	dump: function(encoded, ignoreHash) {
		var storage = Scalr.storage.getStorage(), data = {}, decoded = false;
        if (encoded == 'decoded') {
            decoded = true;
            encoded = false;
        }

		for (var i = 0, len = storage.length; i < len; i++) {
			var key = storage.key(i);
            if (ignoreHash && (key == 'scalr-system-time' || key == 'scalr-system-hash')) {
                continue;
            }

            if (key.substring(0, 6) == 'scalr-') {
                data[key] = storage.getItem(key);
                if (decoded) {
                    data[key] = this.decodeValue(data[key]);
                }
            }
		}

		return encoded ? Ext.encode(data) : data;
	},
	hash: function() {
		return CryptoJS.SHA1(this.dump(true, true)).toString();
	}
};

Ext.state.Manager.setProvider(new Ext.state.LocalStorageProvider({ prefix: 'scalr-' }));
Ext.Ajax.extraParams = Ext.Ajax.extraParams || {};

// this event triggers only when it was fired from another tabs
window.addEventListener('storage', function (e) {
	if (e && e.key) {
		var name = e.key.replace(Scalr.storage.prefix, '');
		if (Scalr.storage.listeners[name]) {
			Scalr.storage.listeners[name](Scalr.storage.get(name));
		}
	}
}, false);

Ext.tip.QuickTipManager.init();

Ext.getBody().setStyle('overflow', 'hidden');

Scalr.event = new Ext.util.Observable();
/*
 * update - any content on page was changed (notify): function (type, arguments ...)
 * close - close current page and go back
 * redirect - redirect to link: function (href, force, params)
 * reload - browser page
 * refresh - current application
 * lock - lock to switch current application (override only throw redirect with force = true)
 * unlock - unlock ...
 * clear - clear application from cache (close and reload)
 */
Scalr.event.addEvents('update', 'close', 'redirect', 'reload', 'refresh', 'resize', 'lock', 'unlock', 'maximize', 'clear');

Scalr.event.on = Ext.Function.createSequence(Scalr.event.on, function (event, handler, scope) {
	if (event == 'update' && scope)
		scope.on('destroy', function () {
			this.un('update', handler, scope);
		}, this);
});

Scalr.cache = {};
Scalr.regPage = function (type, fn) {
	Scalr.cache[type] = fn;
};

Scalr.user = {};
Scalr.flags = {};
Scalr.state = {
	pageSuspend: false,
	pageSuspendForce: false,
	pageRedirectParams: {},
    pageRedirectCounter: 0,
	userNeedLogin: false
};

Scalr.version = function (checkVersion) {
	try {
		var version = Scalr.InitParams.ui.version;
	} catch (e) {}
	return ( !version || version == checkVersion) ? true : false;
};

Scalr.data = {
	stores: {},
	add: function(stores){
		if (!Ext.isArray(stores)) {
			stores = [stores];
		}
		for (var i=0, len=stores.length; i<len; i++) {
			if (!this.stores[stores[i].name]) {
				this.stores[stores[i].name] = new Ext.data.Store(stores[i]);
			}
		}
	},
	get: function(name) {
		return this.stores[name];
	},
	query: function(names) {
		var stores = [];
		if (!Ext.isArray(names)) {
			names = [names];
		}
		for (var i=0, len=names.length; i<len; i++) {
			if (names[i].indexOf('*') != -1) {
				var q = names[i].replace('*', '');
				Ext.Object.each(this.stores, function(name, store){
					if (name.indexOf(q) !== -1) {
						stores.push(store);
					}
				});
			} else if (this.stores[names[i]]) {
				stores.push(this.stores[names[i]]);
			}
		}
		return stores;
	},
	fireRefresh: function(names){
		var stores = this.query(names);
		for (var i=0, len=stores.length; i<len; i++) {
			stores[i].fireEvent('refresh');
		}
	},
	load: function(names, callback, reload, lock) {
		var me = this,
			stores = this.query(names),
			requests = [], requestsMap = {};
		for (var i=0, len=stores.length; i<len; i++) {
			if ((reload && stores[i].dataLoaded) || !reload && !stores[i].dataLoaded) {
				if (requestsMap[stores[i].dataUrl] === undefined) {
					requests.push({
						url: stores[i].dataUrl,
						stores: []
					})
					requestsMap[stores[i].dataUrl] = requests.length - 1;
				}
				requests[requestsMap[stores[i].dataUrl]].stores.push(stores[i].name);
			}
		}

		var resumeEventsList = [],
			firstRun = true,
			runRequest = function() {
				if (requests.length) {
					var request = requests.shift();
					var r = {
						url: request.url,
						params: {
							stores: Ext.encode(request.stores)
						},
						success: function (data, response, options) {
							Ext.Object.each(data.stores, function(name, data){
								me.stores[name].suspendEvents(true);
								resumeEventsList.push(name);
								me.stores[name].loadData(data);
								me.stores[name].dataLoaded = true;
							});
							firstRun = false;
							runRequest();
						}
					};
					if (firstRun && lock) {
						r.processBox = {type: 'action'};
					} else {
						r.disableFlushMessages = true;
						r.disableAutoHideProcessBox =true;
					}
					Scalr.Request(r);
				} else {
					for (var i=0, len=resumeEventsList.length; i<len; i++) {
						me.stores[resumeEventsList[i]].resumeEvents();
					}
					callback ? callback() : null;
				}
			}
		runRequest();
	},
	reload: function(names, lock, callback) {
		lock = lock === undefined ? true : lock;
		this.load(names, callback, true, lock);
	},
	reloadDefer: function(names) {
		var stores = this.query(names);
		for (var i=0, len=stores.length; i<len; i++) {
			stores[i].dataLoaded = false;
		}
	}
};

Scalr.event.on('close', function(force) {
	Scalr.state.pageSuspendForce = Ext.isBoolean(force) ? force : false;

	if (history.length > 1)
		history.back();
	else
		document.location.href = "#/dashboard";
});

Scalr.event.on('redirect', function(href, force, params) {
	Scalr.state.pageSuspendForce = Ext.isBoolean(force) ? force : false;
	Scalr.state.pageRedirectParams = params || {};
	document.location.href = href;
});

Scalr.event.on('lock', function(hide) {
	Scalr.state.pageSuspend = true;
	Scalr.application.disabledDockedToolbars(true, hide);
});

Scalr.event.on('unlock', function() {
	Scalr.state.pageSuspend = false;
	Scalr.application.disabledDockedToolbars(false);
});

Scalr.event.on('reload', function () {
	document.location.reload();
});

Scalr.event.on('refresh', function (forceReload) {
	// @TODO: forceReload
	window.onhashchange(true);
});

Scalr.event.on('resize', function () {
	Scalr.application.getLayout().onOwnResize();
});

Scalr.event.on('maximize', function () {
	var item = Scalr.application.getLayout().activeItem;
	if (item.scalrOptions.maximize == '') {
		if (item.width)
			item.savedWidth = item.width;
		item.scalrOptions.maximize = 'all';
	} else {
		if (item.savedWidth)
			item.width = item.savedWidth;
        delete item.savedWidth;
        delete item.height;
		item.scalrOptions.maximize = '';
	}

	Scalr.application.getLayout().onOwnResize();
});

Scalr.event.on('clear', function (url) {
	var hashchange = false;

	Scalr.application.items.each(function () {
		if (this.scalrRegExp && this.scalrRegExp.test(url)) {
			if (Scalr.application.getLayout().activeItem == this)
				hashchange = true;

			this.close();
			return false;
		}
	});

	if (hashchange)
		window.onhashchange(true);
});

/*
 * Messages system
 */
Ext.ns('Scalr.message');

Scalr.message = {
    queue: [],
    Add: function(message, type) {
        if (Ext.isArray(message)) {
            var s = '';
            for (var i = 0; i < message.length; i++)
                '<li>' + message[i] + '</li>'
            message = '<ul>' + s + '</ul>';
        }

        this.Flush(false, message);

        var tip = new Ext.tip.ToolTip({
            zIndexPriority: 5,
            autoShow: true,
            autoHide: false,
            closable: true,
            closeAction: 'destroy',
            header: false,
            layout: {
                type: 'hbox'
            },
            minWidth: 200,
            maxWidth: 700,
            maxHeight: Scalr.application.getHeight() - 30,
            dt: Ext.Date.add(new Date(), Ext.Date.SECOND, 2),
            autoScroll: true,
            type: type,
            cls: 'x-tip-message x-tip-message-' + type,
            items: [{
                xtype: 'component',
                flex: 1,
                tpl: '{message}',
                data: {
                    message: message
                }
            }, {
                xtype: 'tool',
                type: 'close',
                handler: function () {
                    this.up('tooltip').close();
                }
            }],
            onDestroy: function () {
                Ext.Array.remove(Scalr.message.queue, this);
            }
        });

        tip.el.alignTo(Ext.getBody(), 't-t', [0, 15]);
        Scalr.message.queue.push(tip);
    },
    Error: function(message) {
        this.Add(message, 'error');
    },
    Success: function(message) {
        this.Add(message, 'success');
    },
    Warning: function(message) {
        this.Add(message, 'warning');
    },
    InfoTip: function(message, el, params) {
        var config = {
            target: el,
            anchorToTarget: true,
            anchor: 'top',
            html: message,
            autoShow: true,
            dismissDelay: 10000,
            listeners: {
                hide: function() {
                    this.destroy();
                }
            }
        };
        if (params !== undefined) {
            Ext.apply(config, params);
        }
        new Ext.tip.ToolTip(config);
    },
    ErrorTip: function(message, el, params) {
        this.InfoTip(message, el, Ext.apply({cls: 'x-tip-message x-tip-message-error'}, params));
    },
    WarningTip: function(message, el, params) {
        this.InfoTip(message, el, Ext.apply({cls: 'x-tip-message x-tip-message-warning'}, params));
    },
    Flush: function(force, message) {
        var i = this.queue.length - 1, dt = new Date();

        while (i >= 0) {
            if (force || this.queue[i].dt < dt || this.queue[i].child('component').initialConfig.data.message == message) {
                this.queue[i].destroy();
            }
            i--;
        }
    }
};

Ext.Ajax.timeout = 60000;

// catch server error page (404, 403, timeOut and other)
Ext.Ajax.on('requestexception', function(conn, response, options) {
	if (options.doNotShowError == true)
		return;

	if (response.status == 403) {
		Scalr.state.userNeedLogin = true;
		Scalr.event.fireEvent('redirect', '#/guest/login', true);
	} else if (response.status == 404) {
		Scalr.message.Error('Page not found.');
	} else if (response.timedout == true) {
		Scalr.message.Error('Server didn\'t respond in time. Please try again in a few minutes.');
	} else if (response.aborted == true) {
		Scalr.message.Error('Request was aborted by user.');
	} else {
		if (Scalr.timeoutHandler.enabled) {
			Scalr.timeoutHandler.undoSchedule();
			Scalr.timeoutHandler.run();

			//Scalr.timeoutHandler.forceCheck = true;
			//Scalr.timeoutHandler.restart();
		}
		Scalr.message.Error('Cannot proceed your request at the moment. Please try again later.');
	}
});

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
		if (storage)
			storage.setItem(this.getName(name), this.encodeValue(value));
	},
	clear: function (name, session) {
		var storage = this.getStorage(session);
		if (storage)
			storage.removeItem(this.getName(name));
	}
};

window.addEventListener('storage', function (e) {
	if (e && e.key) {
		var name = e.key.replace(Scalr.storage.prefix, '');
		if (Scalr.storage.listeners[name]) {
			Scalr.storage.listeners[name](Scalr.storage.get(name));
		}
	}
}, false);


Scalr.event = new Ext.util.Observable();
/*
 * update - any content on page was changed (notify): function (type, arguments ...)
 * close - close current page and go back
 * redirect - redirect to link: function (href, keepMessages, force)
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
	userNeedLogin: false
};

Scalr.version = function (checkVersion) {
	try {
		var version = Scalr.InitParams.ui.version;
	} catch (e) {}
	return ( !version || version == checkVersion) ? true : false;
};

Ext.getBody().setStyle('overflow', 'hidden');
Ext.tip.QuickTipManager.init();

Ext.state.Manager.setProvider(new Ext.state.LocalStorageProvider({ prefix: 'scalr-' }));

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
	var options = Scalr.application.getLayout().activeItem.scalrOptions;
	options.maximize = options.maximize == 'all' ? '' : 'all';
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

Ext.Ajax.timeout = 60000;

(function preload(){
	var url = [
		'/ui2/images/icons/loading.gif',
		'/ui2/js/extjs-4.1/theme/images/topmenu/scalr_logo_icon_36x27.png'
	];

	for (var i = 0; i < url.length; i++) {
		var image = new Image();
		image.src = url[i];
	}
})();

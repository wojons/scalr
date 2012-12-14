Ext.ns('Scalr.utils');

Scalr.utils.CreateProcessBox = function (config) {
	var messages = {
		delete: 'Deleting ...',
		reboot: 'Rebooting ...',
		terminate: 'Terminating ...',
		launch: 'Launching ...',
		save: 'Saving ...'
	};
	config = config || {};
	config['msg'] = config['msg'] || messages[config['type']] || 'Processing ...';

	var c = Ext.create('Ext.panel.Panel', {
		itemId: 'box',
		floating: true,
		modal: true,
		data: config,
		width: 310,
		shadow: false,
		cls: 'x-panel-confirm x-panel-confirm-plain',
		title: config['msg'],
		titleAlign: 'center',
		bodyStyle: 'text-align: center; padding-bottom: 19px; padding-top: 10px;',
		tpl: '<img class="progress" src="/ui2/images/icons/loading.gif">',

		onFloatShow: function () {
			var me = this, xy, size = me.getSize();

			xy = me.el.getAlignToXY(me.container, 'c-c', [ 0, - size['height'] / 2 ]);
			me.setPagePosition(xy);
			//me.el.applyStyles('opacity: 1');
		},
		listeners: {
			/*beforedestroy: function () {
				this.el.applyStyles('-webkit-transition: all 1s ease');

				//if (! this.scalrAnimDestroy) {
				//}

			},*/
			//afterrender: function () {
			//	this.el.applyStyles('opacity: 0; -webkit-transition: opacity 0.5s ease');
			//}
		}

		/*scalrDestroy: function () {
			var me = this;
			setTimeout(function () {
				me.el.applyStyles('-webkit-transition: all 1s');
				me.el.dom.addEventListener('webkitTransitionEnd', function () {
					me.destroy();
				});
				me.el.applyStyles('opacity: 0');
			}, 1000);
		}*/
	});

	c.show();

	return c;
};

Scalr.utils.CloneObject = function (o) {
	if (o == null || typeof(o) != 'object')
		return o;

	if(o.constructor == Array)
		return [].concat(o);

	var t = {};
	for (var i in o)
		t[i] = Scalr.utils.CloneObject(o[i]);

	return t;
};

Scalr.utils.Confirm = function (config) {
	var a = '';
	switch (config['type']) {
		case 'delete':
			a = 'Delete'; break;
		case 'reboot':
			a = 'Reboot'; break;
		case 'terminate':
			a = 'Terminate'; break;
		case 'launch':
			a = 'Launch'; break;
	};

	if (config.objects) {
		config.objects.sort();
		var r = '<span style="font-weight: 700;">' + config.objects.shift() + '</span>';
		if (config.objects.length)
			r = r + ' and <span title="' + config.objects.join("\n") + '" style="font-weight: 700; border-bottom: 1px dashed #000080;">' + config.objects.length + ' others</span>';

		config.msg = config.msg.replace('%s', r);
	}

	config['ok'] = config['ok'] || a;
	config['closeOnSuccess'] = config['closeOnSuccess'] || false;
	var items = [];

	if (Ext.isDefined(config.type)) {
		items.push({
			xtype: 'component',
			data: config,
			tpl: '<div class="icon icon-{type}"></div><div class="message">{msg}</div>'
		});
	}

	if (Ext.isDefined(config.form)) {
		var form = {
			margin: Ext.isDefined(config.type) ? '0 5 0 5' : '5 5 0 5',
			layout: 'anchor',
			itemId: 'form',
			xtype: 'form',
			border: false,
			defaults: {
				anchor: '100%'
			},
			items: config.form
		};

		if (Ext.isDefined(config.formValidate)) {
			form.listeners = {
				validitychange: function (form, valid) {
					if (valid)
						this.up('#box').down('#buttonOk').enable();
					else
						this.up('#box').down('#buttonOk').disable();
				},
				boxready: function () {
					if (this.form.hasInvalidField())
						this.up('#box').down('#buttonOk').disable();
				}
			};
		}

		items.push(form);
	}

	var c = Ext.create('Ext.panel.Panel', {
		itemId: 'box',
		floating: true,
		modal: true,
		shadow: false,
		cls: 'scalr-mb-container',
		border: false,
		cls: 'x-panel-confirm' + (Ext.isDefined(config.form) ? '' : ' x-panel-confirm-plain'),
		width: config.formWidth || 400,
		title: config.title || null,
		maxHeight: Scalr.application.getHeight() - 10, // padding from top and bottom
		autoScroll: true,
		titleAlign: 'center',
		items: items,
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: config['ok'] || 'OK',
				width: 150,
				cls: 'x-btn-plain',
				itemId: 'buttonOk',
				disabled: config['disabled'] || false,
				handler: function () {
					var values = this.up('#box').down('#form') ? this.up('#box').down('#form').getValues() : {};

					if (! config.closeOnSuccess)
						this.up('#box').close();

					if (config.success.call(config.scope || this.up('#box'), values, this.up('#box') ? this.up('#box').down('#form') : this) && config.closeOnSuccess) {
						this.up('#box').close();
					}
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				width: 150,
				cls: 'x-btn-plain',
				margin: '0 0 0 16',
				handler: function () {
					this.up('#box').close();
				}
			}]
		}]
	});

	c.keyMap = new Ext.util.KeyMap(Ext.getBody(), [{
		key: Ext.EventObject.ESC,
		fn: function () {
			this.close();
		},
		scope: c
	}]);

	if (! Ext.isDefined(config.form)) {
		c.keyMap.addBinding({
			key: Ext.EventObject.ENTER,
			fn: function () {
				var btn = this.down('#buttonOk');
				btn.handler.call(btn);
			},
			scope: c
		});
	}

	c.on('destroy', function () {
		this.keyMap.destroy();
	});

	c.show();
	c.center();
	c.toFront();

	return c;
};

Scalr.utils.Request = function (config) {
	var currentUrl = document.location.href;

	config = Ext.apply(config, {
		callback: function (options, success, response) {
			if (!options.disableAutoHideProcessBox && options.processBox)
				options.processBox.destroy();

			if (success == true && response.responseText && (Ext.isDefined(response.status) ? response.status == 200 : true)) {
				// only for HTTP Code = 200 (for fake ajax upload files doesn't exist response status)
				//try {
					var result = Ext.decode(response.responseText);

					if (result && result.success == true) {
						if (result.successMessage)
							Scalr.message.Success(result.successMessage);

						if (result.warningMessage)
							Scalr.message.Warning(result.warningMessage);

						options.successF.call(this, result, response, options);
						/*try {
							options.successF.call(this, result, response, options);
						} catch (e) {
							Scalr.message.Error('Success handler error:' + e);
						}*/
						return true;
					} else {
						if (result && result.errorMessage)
							Scalr.message.Error(result.errorMessage);

						options.failureF.call(this, result, response, options);
						/*try {
							options.failureF.call(this, result, response, options);
						} catch (e) {
							Scalr.message.Error('Failure handler error:' + e);
						}*/
						return;
					}
				/*} catch (e) {
					Scalr.message.Error('Received incorrect response from server (' + e + ')');
					//Scalr.utils.PostReport(response, options, e);
				}*/
			}
			if (!response.responseText && Ext.isDefined(response.status) ? response.status == 200 : true)
				Scalr.utils.PostError({
					message: 'responseText is null in ajax request\nRequest:\n' + Scalr.utils.VarDump(response.request.options.params || {}) + '\nresponse headers: \n' + Scalr.utils.VarDump(response.getAllResponseHeaders()),
					url: document.location.href
				});

			// else nothing, global error handler used (if status code != 200)
			options.failureF.call(this, null, response, options);
		}
	});

	//config.disableFlushMessages = !!config.disableFlushMessages;
	//if (! config.disableFlushMessages)
	//	Scalr.message.Flush();

	config.disableAutoHideProcessBox = !!config.disableAutoHideProcessBox;

	config.successF = config.success || function () {};
	config.failureF = config.failure || function () {};
	config.scope = config.scope || config;
	config.params = config.params || {};

	delete config.success;
	delete config.failure;

	var pf = function (config) {
		if (config.processBox) {
			config.processBox = Scalr.utils.CreateProcessBox(config.processBox);
		}

		if (config.form) {
			config['success'] = function (form, action) {
				action.callback.call(this, action, true, action.response);
			};

			config['failure'] = function (form, action) {
				// investigate later, in extjs 4
				action.callback.call(this, action, /*(action.response.status == 200) ? true : false*/ true, action.response);
			};
			config['clientValidation'] = false;

			if (config.form.hasUpload()) {
				config.params['X-Requested-With'] = 'XMLHttpRequest';
			}

			config.form.submit(config);
		} else {
			return Ext.Ajax.request(config);
		}
	};

	if (Ext.isObject(config.confirmBox)) {
		config.confirmBox['success'] = function (params) {
			delete config.confirmBox;

			if (Ext.isDefined(params))
				Ext.applyIf(config.params, params);

			pf(config);
		};

		Scalr.Confirm(config.confirmBox);
	} else {
		return pf(config);
	}
};

Scalr.utils.UserLoadFile = function (path) {
	Ext.Function.defer(
		Ext.getBody().createChild({
			tag: 'iframe',
			src: path,
			width: 0,
			height: 0,
			frameborder: 0
		}).remove, 1000
	);
};

Scalr.utils.VarDump = function (c) {
	var d = [];
	for (var s in c) {
		if (Ext.isString(c[s]))
			d.push(s + ': ' + c[s]);
	}

	d = d.join("\n");

	return d;
};

Scalr.utils.ThrowDebug = function (c) {
	var d = [];
	for (var s in c) {
		if (Ext.isString(c[s]))
			d.push(s + ': ' + c[s]);
	}

	d = d.join("\n");
	throw d;
};

Scalr.utils.PostException = function(e) {
	Scalr.utils.PostError({
		message: e.message + "\nstack: " + e.stack + "\ntype: " + e.type + "\nname: " + e.name,
		url: document.location.href
	});
};

Scalr.utils.PostError = function(params) {
	Scalr.Request({
		url: '/guest/xPostError',
		doNotShowError: true,
		params: params
	});

	Scalr.message.Warning("Whoops! Something went wrong, and we have been notified. Try reloading the page if things don't work.");
};

Scalr.utils.IsEqualValues = function (obj1, obj2) {
	for (var i in obj1) {
		if (! Ext.isDefined(obj2[i]) && obj1[i] == obj2[i])
			return false;
	}

	return true;
}

// shorter name
Scalr.Confirm = Scalr.utils.Confirm;
Scalr.Request = Scalr.utils.Request;

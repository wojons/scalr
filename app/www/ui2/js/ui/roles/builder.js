Scalr.regPage('Scalr.ui.roles.builder', function (loadParams, moduleParams) {
	var result = { behaviors: [], addons: [ 'chef' ] };

	if (! Ext.isObject(moduleParams.platforms)) {
		Scalr.message.Error('Roles builder supports only EC2 and Rackspace platforms');
		Scalr.event.fireEvent('redirect', moduleParams['environment'], true);
		return false;
	}

	var platforms = [];
	for (var i in moduleParams.platforms)
		platforms[platforms.length] = {
			xtype: 'custombutton',
			width: 109,
			height: 109,
			allowDepress: false,
			toggleGroup: 'scalr-ui-roles-builder-os',

			renderTpl:
				'<div class="x-btn-custom" id="{id}-btnEl">' +
					'<div class="{prefix}-btn-icon"><img src="{icon}"></div>' +
					'<div class="{prefix}-btn-name">{name}</div>' +
				'</div>',
			renderData: {
				prefix: 'scalr-ui-roles-builder',
				name: moduleParams.platforms[i],
				icon: '/ui2/images/icons/platform/' + i + '_89x64.png'
			},
			platform: i,
			margin: 10,
			toggleHandler: function () {
				if (this.pressed) {
					result['platform'] = this.platform;
					form.down("#step1").setTitle('Step 1 - Choose platform [' + this.renderData.name + ']');
					form.stepNext();
				} else {
					form.stepChanged();
				}
			}
		};

	var checkboxBehaviorListener = function() {
		if (this.behavior == 'mysql') {
			if (this.pressed) {
				form.down('#softwareSet').show();
			} else
				form.down('#softwareSet').hide();
		}

		if (this.behavior == 'app') {
			if (this.pressed)
				form.down('[behavior="www"]').disable();
			else
				form.down('[behavior="www"]').enable();
		}

		//moduleParams['images'][result['platform']][result['imageId']]['name'] == 'Ubuntu 12.04'
		var isUbuntu12 = moduleParams['images'][result['platform']][result['imageId']]['name'] == 'Ubuntu 12.04';
		var isCentOS6 = moduleParams['images'][result['platform']][result['imageId']]['name'] == 'CentOS 6.3';
		
		var dbBehaviors = ['mysql', 'postgresql', 'redis', 'mongodb', 'percona', 'mysql2'];
		if (Ext.Array.contains(dbBehaviors, this.behavior)) {
			
			Ext.Array.each(dbBehaviors, function(value){
				if (this.behavior != value) {
					if (this.pressed) {
						form.down('[behavior="'+value+'"]').disable();
					} else {
						if (value == 'percona') {
							if (isUbuntu12 || isCentOS6)
							//if (isCentOS6)
								form.down('[behavior="'+value+'"]').enable();
						} else if (value == 'mysql2') {
							if (isUbuntu12)
								form.down('[behavior="'+value+'"]').enable();
						}
						else if (value == 'mysql') {
							if (!isUbuntu12)
								form.down('[behavior="'+value+'"]').enable();
						}
						else
							form.down('[behavior="'+value+'"]').enable();
					}
				}
			}, this);
		}
		
		if (this.behavior == 'www') {
			if (this.pressed)
				form.down('[behavior="app"]').disable();
			else
				form.down('[behavior="app"]').enable();
		}

		if (this.pressed) {
			result.behaviors.push(this.behavior);
		} else {
			Ext.Array.remove(result.behaviors, this.behavior);
		}

		if (result.behaviors.length > 1) {
			form.down('#settings-group').setValue('Mixed images');
		} else if (result.behaviors.length) {
			var ar = {
				'app': 'Application servers',
				'mysql': 'Database servers',
				'mysql2': 'Database servers',
				'percona': 'Database servers',
				'postgresql': 'Database servers',
				'www': 'Load balancers',
				'memcached': 'Caching servers',
				'redis': 'Database servers',
				'mongodb': 'Database servers',
				'rabbitmq': 'MQ servers'
			};

			form.down('#settings-group').setValue(ar[result.behaviors[0]]);
		} else {
			form.down('#settings-group').setValue('Base images');
		}
	};

	var checkboxAddonsListener = function() {
		if (this.pressed) {
			result.addons.push(this.behavior);
		} else {
			Ext.Array.remove(result.addons, this.behavior);
		}
	}

	var form = Ext.create('Ext.panel.Panel', {
		title: 'Role builder',
		width: 1000,
		layout: {
			type: 'accordion',
			hideCollapseTool: true
		},

		defaults: {
			border: false,
			bodyCls: 'x-panel-body-plain-frame',
			bodyPadding: 0,
			toggleCollapse: function (animate) {
				if (this.collapsed) {
					this.ownerCt.layout.expandedItem = this.ownerCt.items.indexOf(this);
					this.expand(animate);
				}
				return this;
			}
		},
		stepChanged: function () {
			var i = this.layout.expandedItem;
			for (var len = this.items.length, i = i + 1; i < len; i++)
				this.items.get(i).disable();
		},
		stepNext: function () {
			var i = this.layout.expandedItem + 1;
			if (i < this.items.length) {
				this.items.get(i).enable();
				this.items.get(i).expand();
				this.layout.expandedItem = i;
			}
		},

		items: [{
			title: 'Step 1 - Choose platform',
			layout: 'column',
			hideCollapseTool: true,
			itemId: 'step1',
			items: platforms
		}, {
			title: 'Step 2 - Choose OS',
			disabled: true,
			hideCollapseTool: true,
			itemId: 'step2',
			layout: 'anchor',
			autoScroll: true,
			items: [{
				xtype: 'fieldset',
				style: 'margin: 10px',
				title: 'Choose location and architecture',
				layout: 'column',
				items: [{
					xtype: 'combo',
					allowBlank: false,
					editable: false,
					queryMode: 'local',

					fieldLabel: 'Location',
					columnWidth: .50,
					itemId: 'location',
					valueField: 'name',
					displayField: 'description',
					store: {
						fields: [ 'name', 'description' ],
						proxy: 'object'
					},
					listeners: {
						change: function () {
							var r = this.findRecord('name', this.getValue());
							result['location'] = this.getValue();
							result['location_description'] = r.get('description');
							form.down('#step2').applyFilter();
						}
					}
				}, {
					xtype: 'container',
					columnWidth: .15,
					html: '&nbsp;'
				}, {
					xtype: 'radiogroup',
					itemId: 'architecture',
					columnWidth: .20,
					layout: 'anchor',
					items: [{
						boxLabel: 'i386',
						name: 'architecture',
						inputValue: 'i386'
					}, {
						boxLabel: 'x86_64',
						name: 'architecture',
						inputValue: 'x86_64'
					}],
					listeners: {
						change: function () {
							if (Ext.isString(this.getValue().architecture)) {
								result['architecture'] = this.getValue().architecture;
								form.down('#step2').applyFilter();
							}
						}
					}
				}]
			}, {
				xtype: 'fieldset',
				style: 'margin: 10px',
				title: 'Select OS',
				itemId: 'images'
			}, {
				xtype: 'fieldset',
				style: 'margin: 10px',
				hidden:!(loadParams['beta'] == 1),
				title: 'OR Custom image',
				items: [{
					xtype: 'fieldcontainer',
					fieldLabel: 'ImageID',
					layout: 'hbox',
					items: [{
						xtype: 'textfield',
						name: 'imageId',
						value: ''
					} , {
						xtype: 'button',
						margin: '0 0 0 3',
						text: 'Set this image as prototype',
						handler: function(){
							result['imageId'] = this.prev('[name="imageId"]').getValue();
							result['isCustomImage'] = true;
							
							form.stepNext();
							form.down('#step2').setTitle('Step 2 - Choose OS [ImageID: ' + result['imageId'] + ']');
						}
					}]
				}]
			}],
			applyFilter: function () {
				var architecture = result['architecture'], location = result['location'], r = moduleParams.images[result['platform']];

				form.down('#images').items.each(function () {
					var d = true;
					for (i in r) {
						if (r[i].name == this.renderData.name && r[i].location == location && r[i].architecture == architecture) {
							d = false;
							this.imageId = i;
							break;
						}
					}

					if (this.pressed) {
						result['imageId'] = this.imageId;
						form.down('#step2').setTitle('Step 2 - Choose OS [' + this.renderData.name + ' (' + result['architecture'] + ') at ' + result['location_description'] + ']');
					}

					if (d) {
						if (this.pressed)
							this.toggle(false);

						this.disable(); // deactivate, if selected
					} else
						this.enable();
				});
			},
			listeners: {
				enable: function () {
					var r = moduleParams.images[result['platform']], d = [], l = {}, k = [], cont = form.down('#images');

					cont.removeAll();
					form.down('#step2').setTitle('Step 2 - Choose OS');
					var added = {};
					for (i in r) {
						if (! added[r[i].name])
							
							cont.add({
								xtype: 'custombutton',
								width: 109,
								height: 109,
								allowDepress: false,
								toggleGroup: 'scalr-ui-roles-builder-image',
								style: 'display: inline-table',

								renderTpl:
									'<div class="x-btn-custom" id="{id}-btnEl">' +
										'<div class="{prefix}-btn-icon"><img src="{icon}"></div>' +
										'<div class="{prefix}-btn-name">{name}</div>' +
									'</div>',
								renderData: {
									prefix: 'scalr-ui-roles-builder',
									name: r[i].name,
									icon: '/ui2/images/icons/os/' + r[i]['os_dist'] + '_89x64.png',
									iconDisabled: '/ui2/images/icons/os/' + r[i]['os_dist'] + '_disabled_89x64.png',
									osDist: r[i]['os_dist']
								},
								imageId: i,
								margin: 10,
								toggleHandler: function () {
									if (this.pressed) {
										result['imageId'] = this.imageId;
										form.down('[name="imageId"]').setValue('');
										form.stepNext();
										form.down('#step2').setTitle('Step 2 - Choose OS [' + this.renderData.name + ' (' + result['architecture'] + ') at ' + result['location_description'] + ']');

										if (this.renderData.osDist == 'centos')
											form.down('[behavior="mysqlproxy"]').toggle(false).disable();
										else
											form.down('[behavior="mysqlproxy"]').enable();
										
										if (this.renderData.name == 'Ubuntu 12.04') {
											form.down('[behavior="mysql"]').toggle(false).disable();
											//form.down('[behavior="percona"]').toggle(false).disable();
											
											form.down('[behavior="mysql2"]').enable();
											form.down('[behavior="percona"]').enable();
										} else if (this.renderData.name == 'CentOS 6.3') {
											form.down('[behavior="mysql2"]').toggle(false).disable();
											
											form.down('[behavior="mysql"]').enable();
											form.down('[behavior="percona"]').enable();
										} else {
											form.down('[behavior="mysql2"]').toggle(false).disable();
											form.down('[behavior="percona"]').toggle(false).disable();
											
											form.down('[behavior="mysql"]').enable();
										}
										
									} else {
										form.stepChanged();
										form.down('#step2').setTitle('Step 2 - Choose OS');
									}
								},
								listeners: {
									click: function () {
										if (form.layout.expandedItem == 1)
											form.stepNext();
									}
								}
							});

						added[r[i].name] = true;

						l[r[i]['location']] = r[i]['location_description'];
					}

					for (var i in l)
						k.push({ name: i, description: l[i]});

					var c = form.down('#location');
					c.store.loadData(k);
					c.store.sort('description', 'desc');
					c.setValue(result['platform'] == 'ec2' ? 'us-east-1' : k[0]['name']);

					var r = c.findRecord('name', c.getValue());
					result['location'] = c.getValue();
					result['location_description'] = r.get('description');

					form.down('#architecture').setValue({ architecture: ['x86_64'] });
					result['architecture'] = 'x86_64';

					form.down('#step2').applyFilter();
				}
			}
		}, {
			title: 'Step 3 - Set settings',
			itemId: 'step3',
			hideCollapseTool: true,
			disabled: true,
			autoScroll: true,
			items: [{
				xtype: 'fieldset',
				margin: 10,
				title: 'General',
				labelWidth: 80,
				items: [{
					xtype: 'textfield',
					fieldLabel: 'Role name',
					itemId: 'settings-rolename',
					width: 600,
					validator: function (value) {
						var r = /^[A-z0-9-]+$/, r1 = /^-/, r2 = /-$/;
						if (r.test(value) && !r1.test(value) && !r2.test(value) && value.length > 2)
							return true;
						else
							return 'Illegal name';
					}
				}, {
					xtype: 'displayfield',
					fieldLabel: 'Group',
					value: 'Base images',
					width: 600,
					itemId: 'settings-group'
				}]
			}, {
				xtype: 'fieldset',
				margin: 10,
				title: 'Behaviors',
				itemId: 'settings-behaviors',
				defaults: {
					xtype: 'custombutton',
					width: 109,
					height: 109,
					enableToggle: true,
					style: 'display: inline-table',

					renderTpl:
						'<div class="x-btn-custom" style="width: 109px; height: 109px" id="{id}-btnEl">' +
							'<div class="{prefix}-btn-icon"><img src="{icon}"></div>' +
							'<div class="{prefix}-btn-name">{name}</div>' +
						'</div>',
					margin: 10
				},
				items: [{
					behavior: 'mysql',
					renderData: {
						name: 'MySQL',
						icon: '/ui2/images/icons/behaviors/database_mysql.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}, {
					behavior: 'mysql2',
					renderData: {
						name: 'MySQL 5.5',
						icon: '/ui2/images/icons/behaviors/database_mysql.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}, {
					behavior: 'postgresql',
					renderData: {
						name: 'PostgreSQL',
						icon: '/ui2/images/icons/behaviors/database_postgresql.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}, {
					behavior: 'percona',
					renderData: {
						name: 'Percona 5.5',
						icon: '/ui2/images/icons/behaviors/database_percona.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}, {
					behavior: 'app',
					renderData: {
						name: 'Apache',
						icon: '/ui2/images/icons/behaviors/app_app.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}, {
					behavior: 'www',
					renderData: {
						name: 'Nginx',
						icon: '/ui2/images/icons/behaviors/lb_www.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}, {
					behavior: 'memcached',
					renderData: {
						name: 'Memcached',
						icon: '/ui2/images/icons/behaviors/cache_memcached.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}, {
					behavior: 'redis',
					renderData: {
						name: 'Redis',
						icon: '/ui2/images/icons/behaviors/database_redis.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}, {
					behavior: 'rabbitmq',
					renderData: {
						name: 'RabbitMQ',
						icon: '/ui2/images/icons/behaviors/mq_rabbitmq.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}, {
					behavior: 'mongodb',
					renderData: {
						name: 'MongoDB',
						icon: '/ui2/images/icons/behaviors/database_mongodb.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxBehaviorListener
				}]
			}, {
				xtype: 'fieldset',
				margin: 10,
				title:  'Addons',
				itemId: 'settings-addons',
				defaults: {
					xtype: 'custombutton',
					width: 109,
					height: 109,
					enableToggle: true,
					style: 'display: inline-table',

					renderTpl:
						'<div class="x-btn-custom" style="width: 109px; height: 109px" id="{id}-btnEl">' +
							'<div class="{prefix}-btn-icon"><img src="{icon}"></div>' +
							'<div class="{prefix}-btn-name">{name}</div>' +
						'</div>',
					margin: 10
				},
				items: [{
					behavior: 'mysqlproxy',
					renderData: {
						name: 'MySQL Proxy',
						icon: '/ui2/images/icons/behaviors/utils_mysqlproxy.png',
						prefix: 'scalr-ui-roles-builder'
					},
					handler: checkboxAddonsListener
				}, {
					behavior: 'chef',
					renderData: {
						name: 'Chef',
						icon: '/ui2/images/icons/behaviors/utils_chef.png',
						prefix: 'scalr-ui-roles-builder'
					},
					pressed: true,
					toggle: Ext.emptyFn
				}]
			}, {
				xtype: 'fieldset',
				style: 'margin: 10px',
				title: 'Software',
				itemId: 'softwareSet',
				hidden: true,
				labelWidth: 80,
				items: [{
					fieldLabel: 'MySQL',
					xtype: 'combo',
					allowBlank: false,
					editable: false,
					store: [['mysql', 'MySQL 5.1'], ['percona', 'Percona Server 5.1']],
					value: 'mysql',
					name: 'mysqlServerType',
					typeAhead: false,
					queryMode: 'local',
					width: 300
				}]
			}],

			dockedItems: [{
				xtype: 'container',
				dock: 'bottom',
				cls: 'x-docked-bottom-frame',
				layout: {
					type: 'hbox',
					pack: 'center'
				},
				items: [{
					xtype: 'button',
					text: 'Create',
					handler: function () {
						if (form.down('#settings-rolename').isValid()) {
							var r = Scalr.utils.CloneObject(result);

							if (! r.behaviors.length)
								Ext.Array.include(r.behaviors, 'base');

							// hack for mysql2 (replace mysql with mysql2 for image with Ubuntu 12.04)
							/*
							if (moduleParams['images'][result['platform']][result['imageId']]['name'] == 'Ubuntu 12.04' && Ext.Array.contains(r.behaviors, 'mysql')) {
								Ext.Array.remove(r.behaviors, 'mysql');
								Ext.Array.include(r.behaviors, 'mysql2');
							}
							*/

							r['behaviors'] = Ext.encode(Ext.Array.merge(r.behaviors, r.addons));
							delete r.addons;
							r['roleName'] = form.down('#settings-rolename').getValue();
							r['mysqlServerType'] = form.down('[name="mysqlServerType"]').getValue();
							
							if (loadParams['devScalarizrBranch'])
								r['devScalarizrBranch'] = loadParams['devScalarizrBranch'];

							Scalr.Request({
								processBox: {
									type: 'action'
								},
								url: '/roles/xBuild',
								params: r,
								success: function (data) {
									Scalr.event.fireEvent('redirect', '#/bundletasks/' + data.bundleTaskId + '/view');
								}
							});
						}
					}
				}]
			}]
		}],
		listeners: {
			show: function () {
				if (platforms.length == 1) {
					form.down('#step1').child('custombutton').toggle(true);
					form.down('#step1').disable();
				}
			}
		}
	});

	return form;
});

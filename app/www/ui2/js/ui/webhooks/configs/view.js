Scalr.regPage('Scalr.ui.webhooks.configs.view', function (loadParams, moduleParams) {
    var scalrOptions;
    if (moduleParams['levelMap'][moduleParams['level']] == 'account') {
		scalrOptions = {
			title: 'Account management &raquo; Webhooks &raquo; Webhooks',
			maximize: 'all',
			leftMenu: {
				menuId: 'settings',
				itemId: 'webhooks',
                showPageTitle: true
			}
		};
    } else {
		scalrOptions = {
			title: Ext.String.capitalize(moduleParams['levelMap'][moduleParams['level']]) + ' webhooks &raquo; Webhooks',
			maximize: 'all',
			leftMenu: {
				menuId: 'webhooks',
				itemId: 'configs',
                showPageTitle: true
			}
		};
    }
    
    var store = Ext.create('store.store', {
        fields: [
            {name: 'webhookId', type: 'string'},
            'level',
            'name',
            'postData',
            'skipPrivateGv',
            'endpoints',
            'events',
            'farms',
            {name: 'timeout', type: 'int', defaultValue: 3},
            {name: 'attempts', type: 'int', defaultValue: 3},
            {name: 'id', convert: function(v, record){return record.data.webhookId;}}
        ],
        data: moduleParams['configs'],
		proxy: {
			type: 'ajax',
			url: '/webhooks/configs/xList/',
            extraParams: {
                level: moduleParams['level']
            },
            reader: {
                type: 'json',
                root: 'configs',
                successProperty: 'success'
            }
		},
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}]
	});

	var reconfigurePage = function(params) {
        if (params.webhookId) {
            cb = function() {
                var selModel = grid.getSelectionModel();
                selModel.deselectAll();
                if (params.webhookId === 'new') {
                    panel.down('#add').handler();
                } else {
                    panel.down('#liveSearch').reset();
                    var record = store.getById(params.webhookId);
                    if (record) {
                        selModel.select(record);
                    }
                }
            };
            if (grid.view.viewReady) {
                cb();
            } else {
                grid.view.on('viewready', cb, grid.view, {single: true});
            }
        }
    };
	
    var grid = Ext.create('Scalr.ui.WebhooksGrid', {
        cls: 'x-grid-shadow x-panel-column-left',
        store: store,
        type: 'config',
        filterFields: ['name'],
        columns: [
            {
                header: '<img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qclass="x-tip-light" data-qtip="' +
                    Ext.String.htmlEncode('<div>Scopes:</div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr">&nbsp;&nbsp;Scalr</div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-account">&nbsp;&nbsp;Account</div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-environment">&nbsp;&nbsp;Environment</div>') +
                    '" />&nbsp;Name',
                text: 'Name',
                flex: 1,
                dataIndex: 'name',
                resizable: false,
                sortable: true,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getLevel(values.level)]}&nbsp;&nbsp;{name}',
                   {
                       getLevel: function(level){
                           var scope = moduleParams['levelMap'][level];
                           return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qtip="'+Ext.String.capitalize(scope)+' scope"/>';
                       }
                   }
               )
            }
        ],
        level: moduleParams['level'],
        listeners: {
            btnnewclick: function(newRecord) {
                form.loadRecord(newRecord);
            },
            btnrefreshclick: function() {
                store.load();
            }
        }
    });

	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
		fieldDefaults: {
			anchor: '100%'
		},
		layout: 'auto',
        overflowX: 'hidden',
        overflowY: 'auto',
		listeners: {
			hide: function() {
    			grid.down('#add').setDisabled(false);
			},
            afterrender: function() {
                var me = this;
                grid.getSelectionModel().on('focuschange', function(gridSelModel){
                    var warning = me.up('#rightcol').down('#warning');
                    if (gridSelModel.lastFocused) {
                        if (moduleParams['level'] == gridSelModel.lastFocused.get('level')) {
                            warning.hide();
                            me.loadRecord(gridSelModel.lastFocused);
                        } else {
                            me.hide();
                            warning.down('displayfield').setValue('<b>'+gridSelModel.lastFocused.get('name')+'</b><br/>'+Ext.String.capitalize(''+moduleParams['levelMap'][gridSelModel.lastFocused.get('level')]) + ' webhook info can\'t be viewed in the current scope.');
                            warning.show();
                        }
                    } else {
                        me.hide();
                        me.up('#rightcol').down('#warning').hide();
                    }
                });
                form.down('#events').store.load({data: moduleParams['events']});
                form.down('#farms').store.load({data: moduleParams['farms']});
            },
			beforeloadrecord: function(record) {
				var frm = this.getForm(),
					isNewRecord = !record.get('webhookId');

				frm.reset(true);
                this.down('#formtitle').setTitle(isNewRecord ? 'New webhook' : 'Edit webhook');
				var c = this.query('component[cls~=hideoncreate], #delete');
				for (var i=0, len=c.length; i<len; i++) {
					c[i].setVisible(!isNewRecord);
				}
                grid.down('#add').setDisabled(isNewRecord);
			},
            loadrecord: function() {
                if (!this.isVisible()) {
                    this.show();
                }
            }
		},
		items: [{
			xtype: 'fieldset',
            itemId: 'formtitle',
            cls: 'x-fieldset-separator-none',
            title: '&nbsp;',
            defaults: {
                labelWidth: 140
            },
			items: [{
                xtype: 'hidden',
                name: 'webhookId'
            },{
				xtype: 'textfield',
				name: 'name',
				fieldLabel: 'Name',
				allowBlank: false,
                validateOnChange: false
            },{
                xtype: 'comboboxselect',
                itemId: 'endpoints',
                name: 'endpoints',
                store: {
                    fields: ['id', 'url', 'isValid'],
                    data: moduleParams['endpoints'],
                    proxy: 'object'
                },
                flex: 1,
                valueField: 'id',
                displayField: 'url',
                fieldLabel: 'Endpoints',
                queryMode: 'local',
                columnWidth: 1,
                allowBlank: false,
                emptyText: 'Please select endpoints',
                listeners: {
                    beforeselect: function(field, record) {
                        return record.get('isValid') == 1;
                    }
                },
                listConfig: {
                    tpl: '<tpl for="."><div class="x-boundlist-item">{[values.isValid!=1?\'<span style="color:#999">\'+values.url+\' (inactive)</span>\':values.url]}</div></tpl>'
                },
                submitValue: false
            },{
                xtype: 'comboboxselect',
                store: {
                    fields: [
                        'name',
                        'description',
                        'scope',
                        {
                            name: 'id',
                            convert: function (v, record) {return record.data.name;}
                        },
                        {
                            name: 'title',
                            convert: function (v, record) {
                                return record.data.name === '*' ? 'All Events' : record.data.name;
                            }
                        }
                    ],
                    proxy: 'object'
                },
                fieldLabel: 'Events',
                valueField: 'id',
                displayField: 'title',
                queryMode: 'local',
                allowBlank: false,
                //validateOnChange: false,
                itemId: 'events',
                name: 'events',
                emptyText: 'Please select events',
                listConfig: {
                    cls: 'x-boundlist-role-scripting-events',
                    style: 'white-space:nowrap',
                    getInnerTpl: function(displayField) {
                        return '&nbsp;<img src="'+Ext.BLANK_IMAGE_URL+'" class="scalr-scope-{scope}" data-qtip="{scope:capitalize} scope"/>&nbsp; '+
                               '<tpl if=\'id == \"*\"\'>All Events<tpl else>{id} <span style="color:#999">({description})</span></tpl>';
                    }
                },
                submitValue: false
            },{
                xtype: 'comboboxselect',
                store: {
                    fields: [
                        {name: 'id', type: 'int'},
                        'name'
                    ],
                    proxy: 'object'
                },
                fieldLabel: 'Farms',
                valueField: 'id',
                displayField: 'name',
                queryMode: 'local',
                //validateOnChange: false,
                itemId: 'farms',
                name: 'farms',
                emptyText: 'All farms',
                submitValue: false
            },{
                xtype: 'fieldcontainer',
                fieldLabel: 'Timeout (sec)',
                layout: 'hbox',
                items: [{
                    xtype: 'combo',
                    store: [1,2,3,4,5,6,7,8,9,10],
                    editable: false,
                    name: 'timeout',
                    width: 60
                },{
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    info: 
                        '<b>Timeout</b><br/>'+ 
                        'When delivering a Webhook Notification, Scalr will expect to start<br/>' +
                        'receiving your Endpoint&#39;s response within this duration. If your<br/>' +
                        'Endpoint fails to respond in time, Scalr may mark the request as<br/>' +
                        'failed or retry it at a later time, depending on your configuration<br/>' +
                        'below (maximum delivery attempts).'
                }]
            },{
                xtype: 'fieldcontainer',
                fieldLabel: 'Max. delivery attempts',
                layout: 'hbox',
                items: [{
                    xtype: 'combo',
                    store: [1,2,3,4,5],
                    editable: false,
                    name: 'attempts',
                    width: 60
                },{
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    info:
                        '<b>Maximum delivery attempts</b><br/>'+
                        'If delivery of a Webhook Notification fails, Scalr will retry the<br/>' +
                        'request until it succeeds, or until the maximum number of delivery<br/>' +
                        'attempts configured here has been exceeded. <br/>' + 
                        'Visit the <a href="https://scalr-wiki.atlassian.net/wiki/x/AgDO" target="_blank">Scalr Wiki</a> for information regarding what Scalr considers<br/>'+
                        'to be delivery failures and the retry schedule.'
                }]
            },{
                xtype: 'checkbox',
                name: 'skipPrivateGv',
                boxLabel: 'Do not expose private GlobalVariables in webhook payload'
			}, {
				xtype: 'textarea',
				name: 'postData',
				fieldLabel: 'User data',
                labelAlign: 'top',
                icons: {
                    globalvars: true
                },
                iconsPosition: 'outer',
                validateOnChange: false,
                height: 120
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
            maxWidth: 1100,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
			items: [{
				itemId: 'save',
				xtype: 'button',
				text: 'Save',
				handler: function() {
					var frm = form.getForm(),
                        params,
                        record = frm.getRecord();
					if (frm.isValid()) {
                        params = {
                            endpoints: Ext.encode(frm.findField('endpoints').getValue()),
                            events: Ext.encode(frm.findField('events').getValue()),
                            farms: Ext.encode(frm.findField('farms').getValue()),
                            level: moduleParams['level'],
                        };
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/webhooks/configs/xSave',
                            form: frm,
                            params: params,
							success: function (data) {
                                var isNewRecord = !record.get('webhookId');
                                grid.getSelectionModel().setLastFocused(null);
                                form.setVisible(false);
								if (isNewRecord) {
									record = store.add(data.webhook)[0];
									grid.getSelectionModel().select(record);
								} else {
									record.set(data.webhook);
									form.loadRecord(record);
								}
                                if (isNewRecord) {
                                    grid.getSelectionModel().select(record);
                                } else {
                                    grid.getSelectionModel().setLastFocused(record);
                                }

							}
						});
					}
				}
			}, {
				itemId: 'cancel',
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
                    grid.getSelectionModel().setLastFocused(null);
                    form.setVisible(false);
				}
			}, {
				itemId: 'delete',
				xtype: 'button',
				cls: 'x-btn-default-small-red',
				text: 'Delete',
				handler: function() {
					var record = form.getForm().getRecord();
					Scalr.Request({
						confirmBox: {
							msg: 'Delete webhook?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting...',
							type: 'delete'
						},
						scope: this,
						url: '/webhooks/configs/xRemove',
						params: {webhookId: record.get('webhookId'), level: moduleParams['level']},
						success: function (data) {
							record.store.remove(record);
						}
					});
				}
			}]
		}]
	});
	
	var panel = Ext.create('Ext.panel.Panel', {
		cls: 'scalr-ui-panel-webhooks-ebndpoints',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: scalrOptions,
        listeners: {
            applyparams: reconfigurePage
        },
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            flex: 1,
            maxWidth: 640,
            minWidth: 400,
            layout: 'fit',
            items: [form, {
                xtype: 'container',
                cls: 'x-container-fieldset',
                itemId: 'warning',
                layout: 'anchor',
                hidden: true,
                items: {
                    xtype: 'displayfield',
                    anchor: '100%',
                    cls: 'x-form-field-info'
                }
            }]
        }]
	});

	return panel;
});
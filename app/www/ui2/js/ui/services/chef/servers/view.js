Scalr.regPage('Scalr.ui.services.chef.servers.view', function (loadParams, moduleParams) {
    var scalrOptions;
    if (moduleParams['levelMap'][moduleParams['level']] == 'account') {
		scalrOptions = {
			title: 'Account management &raquo; Chef servers',
			maximize: 'all',
			leftMenu: {
				menuId: 'settings',
				itemId: 'chef',
                showPageTitle: true
			}
		};
    } else {
		scalrOptions = {
			maximize: 'all'
		};
    }

    var store = Ext.create('store.store', {
        fields: [
            'id',
            'url',
            'username',
            'authKey',
            'vUsername',
            'vAuthKey',
            'level',
            'status'
        ],
        data: moduleParams['servers'],
		proxy: {
			type: 'ajax',
			url: '/services/chef/servers/xList/',
            extraParams: {
                level: moduleParams['level']
            },
            reader: {
                type: 'json',
                root: 'servers',
                successProperty: 'success'
            }
		},
		sorters: [{
			property: 'id'
		}]
	});

    var grid = Ext.create('Ext.grid.Panel', {
        cls: 'x-grid-shadow x-panel-column-left',
        store: store,
        flex: 1,
        selModel: {
            selType: 'selectedmodel',
            getVisibility: function(record) {
                return moduleParams['level'] == record.get('level');
            }
        },
        plugins: ['focusedrowpointer'],
        columns: [{
            header: '<img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qclass="x-tip-light" data-qtip="' +
                Ext.String.htmlEncode('<div>Scopes:</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr">&nbsp;&nbsp;Scalr</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-account">&nbsp;&nbsp;Account</div>' +
                '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-environment">&nbsp;&nbsp;Environment</div>') +
                '" />&nbsp;URL',
            text: 'URL',
            flex: 1,
            dataIndex: 'url',
            resizable: false,
            sortable: true,
            xtype: 'templatecolumn',
            tpl: new Ext.XTemplate('{[this.getLevel(values.level)]}&nbsp;&nbsp;{url}',
               {
                   getLevel: function(level){
                       var scope = moduleParams['levelMap'][level];
                       return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qtip="'+Ext.String.capitalize(scope)+' scope"/>';
                   }
               }
            )
        },{
            header: 'User name',
            dataIndex: 'username',
            width: 200
        },{
            header: 'Status',
            minWidth: 90,
            width: 90,
            dataIndex: 'status',
            sortable: true,
            resizable: false,
            xtype: 'statuscolumn',
            statustype: 'chefserver',
            params: {
                level: moduleParams['levelMap'][moduleParams['level']]
            }
        }],
        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No chef servers found.',
                emptyTextNoItems: 'You have no chef servers added yet.'
            },
            loadingText: 'Loading chef servers ...',
            deferEmptyText: false,
            listeners: {
                refresh: function(view){
                    view.getSelectionModel().setLastFocused(null);
                    view.getSelectionModel().deselectAll();
                }
            }
        },
        listeners: {
            selectionchange: function(selModel, selected) {
                this.down('#delete').setDisabled(!selected.length);
            }
        },
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            defaults: {
                margin: '0 0 0 12',
                handler: function() {
                    var action = this.getItemId(),
                        actionMessages = {
                            'delete': ['Delete selected chef server(s)', 'Deleting chef server(s) ...']
                        },
                        selModel = grid.getSelectionModel(),
                        ids = [],
                        request = {};

                    for (var i=0, records = selModel.getSelection(), len=records.length; i<len; i++) {
                        ids.push(records[i].get('id'));
                    }

                    request = {
                        confirmBox: {
                            msg: actionMessages[action][0],
                            type: action
                        },
                        processBox: {
                            msg: actionMessages[action][1],
                            type: action
                        },
                        params: {action: action, level: moduleParams['level']},
                        success: function (data) {
                            if (data.processed && data.processed.length) {
                                switch (action) {
                                    case 'delete':
                                        var recordsToDelete = [];
                                        for (var i=0,len=data.processed.length; i<len; i++) {
                                            recordsToDelete.push(grid.store.getById(data.processed[i]));
                                            selModel.deselect(recordsToDelete[i]);
                                        }
                                        grid.store.remove(recordsToDelete);
                                    break;
                                }
                            }
                            selModel.refreshLastFocused();
                        }
                    };
                    request.url = '/services/chef/servers/xGroupActionHandler';
                    request.params['serverIds'] = Ext.encode(ids);

                    Scalr.Request(request);
                }
            },
            items: [{
                xtype: 'filterfield',
                itemId: 'liveSearch',
                margin: 0,
                minWidth: 60,
                maxWidth: 200,
                flex: 1,
                filterFields: ['url'],
                handler: null,
                store: store
            },{
                xtype: 'tbfill',
                flex: .1,
                margin: 0
            },{
                xtype: 'tbfill',
                flex: .1,
                margin: 0
            },{
                itemId: 'add',
                text: 'New chef server',
                cls: 'x-btn-green-bg',
                handler: function() {
                    grid.getSelectionModel().setLastFocused(null);
                    form.loadRecord(grid.store.createModel({level: moduleParams['level']}));
                }
            },{
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                tooltip: 'Refresh',
                handler: function() {
                    store.load();
                }
            },{
                itemId: 'delete',
                ui: 'paging',
                iconCls: 'x-tbar-delete',
                disabled: true,
                tooltip: 'Deletechef server '
            }]
        }]
    });

	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
		layout: {
            type: 'vbox',
            align: 'stretch'
        },
		listeners: {
			hide: function() {
    			grid.down('#add').setDisabled(false);
			},
            afterrender: function() {
                var me = this;
                grid.getSelectionModel().on('focuschange', function(gridSelModel){
                    if (gridSelModel.lastFocused) {
                        me.loadRecord(gridSelModel.lastFocused);
                    } else {
                        me.hide();
                    }
                });
            },
			beforeloadrecord: function(record) {
				var frm = this.getForm(),
					isNewRecord = !record.get('id'),
                    warning = this.down('#warning');

				frm.reset(true);
                if (moduleParams['level'] == record.get('level')) {
                    warning.hide();
                    this.down('#clientAuth').show();
                    this.down('#clientVAuth').show();
                    this.getDockedComponent('buttons').show();
                    frm.findField('url').setReadOnly(false);
                    this.down('#formtitle').setTitle(isNewRecord ? 'New chef server' : 'Edit chef server');
                } else {
                    warning.down('displayfield').setValue('You don\'t have permission to view authorization settings for a <b>Chef Server</b> defined in the ' + Ext.String.capitalize(''+moduleParams['levelMap'][record.get('level')]) + ' Scope.');
                    this.down('#clientAuth').hide();
                    this.down('#clientVAuth').hide();
                    this.getDockedComponent('buttons').hide();
                    frm.findField('url').setReadOnly(true);
                    warning.show();
                    this.down('#formtitle').setTitle('View chef server');
                }


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
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 70,
            allowBlank: false,
            validateOnChange: false
		},
		items: [{
			xtype: 'fieldset',
            itemId: 'formtitle',
            title: '&nbsp;',
			items: [{
                xtype: 'displayfield',
                submitValue: true,
                fieldLabel: 'ID',
                cls: 'hideoncreate',
                name: 'id'
            },{
				xtype: 'textfield',
				name: 'url',
				fieldLabel: 'URL',
                hideInputOnReadOnly: true
            },{
                xtype: 'displayfield',
                cls: 'hideoncreate',
                fieldLabel: 'Used by',
                name: 'status',
                renderer: function(value) {
                    var record = this.up('form').getForm().getRecord(),
                        status,
                        text;
                    if (record) {
                        status = record.get('status');
                        if (status) {
                            text = ['This <b>Chef Server</b> is currently used by '];
                            if (status['rolesCount'] > 0) {
                                text.push(moduleParams['levelMap'][moduleParams['level']] == 'environment' ? '<a href="#/roles/manager?chefServerId='+record.get('id')+'">'+status['rolesCount']+'&nbsp;Role(s)</a>' : status['rolesCount']+'&nbsp;Role(s)');
                            }
                            if (status['farmsCount'] > 0) {
                                text.push((status['rolesCount']>0 ? ' and ' : '') + (moduleParams['levelMap'][moduleParams['level']] == 'environment' ? '<a href="#/farms/view?chefServerId='+record.get('id')+'">'+status['farmsCount']+'&nbsp;Farm(s)</a>' : status['farmsCount']+'&nbsp;Farm(s)'));
                            }
                            text = text.join('');
                        } else {
                            status = 'Not used';
                            text = 'This <b>Chef Server</b> is currently not used by any <b>Role</b> and <b>Farm Role</b>.';
                        }

                    }
                    return text;
                }
            }]
        },{
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
        },{
			xtype: 'fieldset',
			title: 'Client authorization',
            itemId: 'clientAuth',
			defaults: {
				allowBlank: false
			},
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            flex: 1,
			items: [{
				xtype: 'textfield',
				name: 'username',
				fieldLabel: 'Username'
			},{
				xtype: 'textarea',
				flex: 1,
				name: 'authKey',
				fieldLabel: 'Key'

			}]
		},{
			xtype: 'fieldset',
			title: 'Client validator authorization',
            cls: 'x-fieldset-separator-none',
            itemId: 'clientVAuth',
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            flex: 1,
			items: [{
				xtype: 'textfield',
				name: 'vUsername',
				fieldLabel: 'Username'
			},{
				xtype: 'textarea',
				flex: 1,
				name: 'vAuthKey',
				fieldLabel: 'Key'

			}]
		}],
		dockedItems: [{
			xtype: 'container',
            itemId: 'buttons',
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
                            level: moduleParams['level']
                        };
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/services/chef/servers/xSave',
                            form: frm,
                            params: params,
							success: function (data) {
                                var isNewRecord = !record.get('id');
                                grid.getSelectionModel().setLastFocused(null);
                                form.setVisible(false);
								if (isNewRecord) {
									record = store.add(data.server)[0];
									grid.getSelectionModel().select(record);
								} else {
									record.set(data.server);
									form.loadRecord(record);
								}
                                if (isNewRecord) {
                                    grid.getSelectionModel().select(record);
                                } else {
                                    grid.getSelectionModel().setLastFocused(record);
                                }
                                Scalr.CachedRequestManager.get().setExpired({url: '/services/chef/servers/xListServers/'});
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
							msg: 'Delete chef server?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting...',
							type: 'delete'
						},
						scope: this,
						url: '/services/chef/servers/xRemove',
						params: {id: record.get('id'), level: moduleParams['level']},
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
        title: moduleParams['levelMap'][moduleParams['level']] == 'account' ? '' : Ext.String.capitalize(moduleParams['levelMap'][moduleParams['level']]) + ' Chef servers',
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            flex: .8,
            maxWidth: 640,
            minWidth: 400,
            layout: 'fit',
            items: form
        }]
	});

	return panel;

});
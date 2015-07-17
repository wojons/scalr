Scalr.regPage('Scalr.ui.services.chef.servers.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [
            'id',
            'url',
            'username',
            'authKey',
            'vUsername',
            'vAuthKey',
            'scope',
            'status'
        ],
        data: moduleParams['servers'],
		proxy: {
			type: 'ajax',
			url: '/services/chef/servers/xList/',
            reader: {
                type: 'json',
                rootProperty: 'servers',
                successProperty: 'success'
            }
		},
		sorters: [{
			property: 'id'
		}]
	});

	var reconfigurePage = function(params) {
        if (params.chefServerId) {
            cb = function() {
                if (params.chefServerId === 'new') {
                    panel.down('#add').handler();
                } else {
                    panel.down('#liveSearch').reset();
                    var record = store.getById(params.chefServerId);
                    if (record) {
                        grid.setSelectedRecord(record);
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

    var grid = Ext.create('Ext.grid.Panel', {
        cls: 'x-panel-column-left x-panel-column-left-with-tabs',
        store: store,
        flex: 1,
        selModel: {
            selType: 'selectedmodel',
            getVisibility: function(record) {
                return Scalr.scope == record.get('scope');
            }
        },
        plugins: ['focusedrowpointer', {ptype: 'selectedrecord', disableSelection: false, clearOnRefresh: true}],
        columns: [{
            text: 'URL',
            flex: 1,
            dataIndex: 'url',
            resizable: false,
            sortable: true,
            xtype: 'templatecolumn',
            tpl: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-{scope}" data-qclass="x-tip-light" data-qtip="{[Scalr.utils.getScopeLegend(\'chefserver\')]}"/>&nbsp;&nbsp;{url}'
        },{
            text: 'User name',
            dataIndex: 'username',
            width: 200
        },{
            text: 'Status',
            minWidth: 90,
            width: 90,
            dataIndex: 'status',
            sortable: true,
            resizable: false,
            xtype: 'statuscolumn',
            statustype: 'chefserver',
            params: {
                currentScope: Scalr.scope
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
            deferEmptyText: false
        },
        listeners: {
            selectionchange: function(selModel, selected) {
                this.down('#delete').setDisabled(!selected.length);
            }
        },
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12'
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
                cls: 'x-btn-green',
                enableToggle: true,
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();
                        form.loadRecord(store.createModel({id: 0, scope: Scalr.scope}));
                        form.down('[name=url]').focus();

                        return;
                    }

                    form.hide();
                }
            },{
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function() {
                    store.load();
                    grid.down('#add').toggle(false, true);
                }
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Delete chef server',
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
                        params: {action: action},
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
                        }
                    };
                    request.url = '/services/chef/servers/xGroupActionHandler';
                    request.params['serverIds'] = Ext.encode(ids);

                    Scalr.Request(request);
                }
            }]
        }]
    });

	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
		layout: {
            type: 'vbox',
            align: 'stretch'
        },

        disableButtons: function (disabled, scope) {
            var me = this;

            Ext.Array.each(
                me.getDockedComponent('buttons').query('#save, #delete'),
                function (button) {
                    button
                        .setTooltip(disabled && !Ext.isEmpty(scope)
                            ? Scalr.utils.getForbiddenActionTip('chef server', scope)
                            : ''
                        )
                        .setDisabled(disabled);
                }
            );

            return me;
        },

        toggleScopeInfo: function(record) {
            var me = this,
                scopeInfoField = me.down('#scopeInfo');
            if (Scalr.scope != record.get('scope')) {
                scopeInfoField.setValue(Scalr.utils.getScopeInfo('chef server', record.get('scope'), record.get('id')));
                scopeInfoField.show();
            } else {
                scopeInfoField.hide();
            }
            return me;
        },
		listeners: {
			hide: function() {
    			//grid.down('#add').setDisabled(false);
			},
			beforeloadrecord: function(record) {
				var frm = this.getForm(),
					isNewRecord = !record.store;

                if (Scalr.scope == record.get('scope')) {
                    this.down('#clientAuth').show();
                    this.down('#clientVAuth').show();
                    this.disableButtons(false);
                    frm.findField('url').setReadOnly(false);
                    this.down('#formtitle').setTitle(isNewRecord ? 'New chef server' : 'Edit chef server');
                    this.down('#save').setText(isNewRecord ? 'Create' : 'Save');
                } else {
                    //warning.down('displayfield').setValue('You don\'t have permission to view authorization settings for a <b>Chef Server</b> defined in the ' + Ext.String.capitalize(record.get('scope')) + ' Scope.');
                    this.down('#clientAuth').hide();
                    this.down('#clientVAuth').hide();
                    this.disableButtons(true, record.get('scope'));
                    frm.findField('url').setReadOnly(true);
                    this.down('#formtitle').setTitle('Edit chef server');
                    this.down('#save').setText('Save');
                }


				var c = this.query('component[cls~=hideoncreate], #delete');
				for (var i=0, len=c.length; i<len; i++) {
					c[i].setVisible(!isNewRecord);
				}
                grid.down('#add').toggle(isNewRecord, true);
                this.toggleScopeInfo(record);
            }
		},
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 70,
            allowBlank: false
		},
		items: [{
            xtype: 'displayfield',
            itemId: 'scopeInfo',
            cls: 'x-form-field-info x-form-field-info-fit',
            width: '100%',
            hidden: true
        },{
			xtype: 'fieldset',
            itemId: 'formtitle',
            margin: '0 0 24',//fieldset layout bug fix
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
                                text.push(Scalr.scope == 'environment' ? '<a href="#/roles?chefServerId='+record.get('id')+'">'+status['rolesCount']+'&nbsp;Role(s)</a>' : status['rolesCount']+'&nbsp;Role(s)');
                            }
                            if (status['farmsCount'] > 0) {
                                text.push((status['rolesCount']>0 ? ' and ' : '') + (Scalr.scope == 'environment' ? '<a href="#/farms?chefServerId='+record.get('id')+'">'+status['farmsCount']+'&nbsp;Farm(s)</a>' : status['farmsCount']+'&nbsp;Farm(s)'));
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
			xtype: 'fieldset',
			title: 'Client authorization',
            itemId: 'clientAuth',
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
                        record = frm.getRecord();
					if (frm.isValid()) {
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/services/chef/servers/xSave',
                            form: frm,
							success: function (data) {
								if (!record.store) {
									record = store.add(data.server)[0];
								} else {
									record.set(data.server);
								}
                                grid.clearSelectedRecord();
                                grid.setSelectedRecord(record);
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
                    grid.clearSelectedRecord();
                    grid.down('#add').toggle(false, true);
				}
			}, {
				itemId: 'delete',
				xtype: 'button',
				cls: 'x-btn-red',
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
						params: {id: record.get('id')},
						success: function (data) {
							record.store.remove(record);
						}
					});
				}
			}]
		}]
	});


	var panel = Ext.create('Ext.panel.Panel', {
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			maximize: 'all',
            menuTitle: 'Chef servers',
            menuHref: '#' + Scalr.utils.getUrlPrefix() + '/services/chef/servers',
            menuFavorite: Ext.Array.contains(['account', 'environment'], Scalr.scope),
		},
        stateId: 'grid-chef-servers',
        listeners: {
            applyparams: reconfigurePage
        },
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
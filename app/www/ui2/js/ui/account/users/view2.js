Scalr.regPage('Scalr.ui.account.users.view2', function (loadParams, moduleParams) {
	
	Ext.define('Scalr.ui.models.User', {
		extend: 'Ext.data.Model',
		fields: [
			'id', 'status', 'email', 'fullname', 'dtcreated', 'dtlastlogin', 'type', 'comments', 'teams', 'type', 'is2FaEnabled'
		]
	});

	var store = Ext.create('store.store', {
		model:'Scalr.ui.models.User',
		data: moduleParams['usersList'],
		proxy: {
			type: 'object'
		}
	});
	
	var form = 	Ext.create('Ext.form.Panel', {
		hidden: true,
		fieldDefaults: {
			anchor: '100%'
		},
		activeRecord: null, 
		
		setActiveRecord: function(record){
			var form = this.getForm();
			form.reset();
			this.setMode(record?'edit':'create');
			this.activeRecord = record || new Scalr.ui.models.User();
			form.loadRecord(this.activeRecord);
			this.setVisible(true);
		},
		setMode: function(mode) {
			var form = this.getForm()
				,visible = mode=='edit'?true:false;
			form.findField('id').setVisible(visible);
			form.findField('dtcreated').setVisible(visible);
			form.findField('dtlastlogin').setVisible(visible);
			form.findField('teams').setVisible(visible);
			this.down('#delete').setVisible(visible);
			this.down('#settings').setTitle((mode=='edit'?'Edit':'Add') + ' user');
		},
		items: [{
			itemId: 'settings',
			xtype: 'fieldset',
			title: 'Settings',
			items: [{
				xtype: 'displayfield',
				fieldLabel: 'ID'
			},{
				xtype: 'textfield',
				name: 'fullname',
				fieldLabel: 'Full name'
			},{
				xtype: 'textfield',
				name: 'email',
				fieldLabel: 'Email',
				allowBlank: false,
				vtype: 'email'
			}, {
				xtype: 'textfield',
				name: 'password',
				inputType: 'password',
				fieldLabel: 'Password',
				emptyText: 'Leave blank to let user specify password by himself',
				value: '',
				allowBlank: true
			},{
				xtype: 'displayfield',
				name: 'teams',
				fieldLabel: 'Teams',
				valueToRaw: function(value) {
					var teams = [];
					if (value) {
						Ext.each(value, function(team) {
							teams.push(team.name);
						});
					}
					return teams.join(', ');
				}
			}/*,{
				xtype: 'buttongroup',
				fieldLabel: 'Status',
				allowBlank: false,
				items: [{
					name: 'status',
					text: 'Active',
					value: 'Active',
					pressed: true
				}, {
					name: 'status',
					text: 'Inactive',
					value: 'Inactive'
				}]
			},{
				xtype: 'radiogroup',
				fieldLabel: 'Status',
				allowBlank: false,

				items: [{
					name: 'status',
					inputValue: 'Active',
					boxLabel: 'Active',
					checked: true
				}, {
					name: 'status',
					inputValue: 'Inactive',
					boxLabel: 'Inactive'
				}]
			}*/,{
				xtype: 'displayfield',
				name: 'dtcreated',
				fieldLabel: 'User added'
			},{
				xtype: 'displayfield',
				name: 'dtlastlogin',
				fieldLabel: 'Last login'
			}, {
				xtype: 'textarea',
				name: 'comments',
				fieldLabel: 'Comments',
				labelAlign: 'top',
				grow: true,
				growMax: 400,
				anchor: '100%'
			}, {
				xtype: 'hidden',
				name: 'id'
			}]
		}],
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-toolbar',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			defaults: {
				margin: '0 5 0 5'
			},
			items: [{
				itemId: 'save',
				xtype: 'button',
				text: 'Save',
				handler: function () {
					var form = this.up('form').getForm();
					if (form.isValid()) {
						console.log(form);
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/account/users/xSave',
							form: form,
							success: function (data) {
								form.updateRecord();
							}
						});
					}
				}
			}, {
				itemId: 'cancel',
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					this.up('form').setVisible(false);
				}
			}, {
				itemId: 'delete',
				xtype: 'button',
				cls: 'x-btn-default-small-red',
				text: 'Delete',
				handler: function() {
					var record = this.up('form').activeRecord;
					Scalr.Request({
						confirmBox: {
							msg: 'Delete user #' + record.get('id') + ' ?',
							type: 'delete'
						},
						processBox: {
							msg: 'Deleting...',
							type: 'delete'
						},
						scope: this,
						url: '/account/users/xRemove',
						params: {userId: record.get('id')},
						success: function (data) {
							store.remove(record);
						}
					});
				}
			}]
		}]
	});

	var grid = Ext.create('Ext.grid.Panel', {
		cls: 'scalr-ui-panel-columned-grid',
		flex: 1,
		//scalrReconfigureParams: {teamId : '', userId: '', groupPermissionId: ''},
		multiSelect: true,
		updatePointerPosition: function() {
			var record = this.getSelectionModel().lastFocused
				,offset;
			if (record) {
				offset = Ext.get(this.view.getNode(record)).getOffsetsTo(this.el)[1];
				offset = offset<50?-20:offset;
			} else {
				offset = -20;
			}
			this.el.setStyle('background-position','100% '+offset+'px');
		},
		selModel: {
			selType: 'selectedmodel',
			listeners: {
				focuschange: function(selModel) {
					if (selModel.lastFocused) {
						if (form.activeRecord != selModel.lastFocused) {
							form.setActiveRecord(selModel.lastFocused);
							grid.updatePointerPosition();
						}
					} else {
						form.setVisible(false);
						grid.updatePointerPosition();
					}
				}
			}
		},
		store: store,
		stateId: 'grid-account-users-view',
		//stateful: true,
		//forceFit: true,
		plugins: {
			ptype: 'gridstore'
		},
		listeners: {
			afterrender: function(){
				this.view.el.dom.onscroll = function(){grid.updatePointerPosition();};
			},
			selectionchange: function(selModel, selected) {
				this.down('#delete').setDisabled(!selected.length);
			}
		},
		viewConfig: {
			emptyText: 'No users found',
			loadingText: 'Loading users ...',
			deferEmptyText: false
		},

		columns: [
			{text: 'Name', flex: 1, dataIndex: 'fullname', sortable: true},
			{text: 'Email', flex: 1, dataIndex: 'email', sortable: true},
			{ text: 'Status', width: 80, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
				'<span ' +
				'<tpl if="status == &quot;Active&quot;">style="color: green"</tpl>' +
				'<tpl if="status != &quot;Active&quot;">style="color: red"</tpl>' +
				'>{status}</span>'
			},
			{text: 'Last login', width: 170, dataIndex: 'dtlastlogin', sortable: true}
		],
		dockedItems: [{
			dock: 'top',
			cls: 'x-toolbar',
			layout: {
				type: 'hbox',
				pack: 'end'
			},
			items: [{
				xtype: 'textfield',
				name: 'searchField',
				hideLabel: true,
				width: 200,
				listeners: {
					change: {
						fn: function(me, value){
							store.filterBy(function(record, id){
								value= Ext.String.trim(value);
								return value=='' ? true : record.get('fullname').match(new RegExp(Ext.String.escapeRegex(value), 'i'))
							})							 
						},
						scope: this,
						buffer: 100
					}
				}
			},{ 
				xtype: 'tbfill' 
			},{
				itemId: 'delete',
				xtype: 'button',
				margin: '0 10 0 0',
				disabled: true,
				cls: 'x-btn-default-small-red',
				text: 'Delete',
				handler: function(){
				}
			},{
				itemId: 'add',
				xtype: 'button',
				text: 'Add',
				handler: function(){
					grid.getSelectionModel().setLastFocused(null);
					grid.updatePointerPosition();
					form.setActiveRecord(null);
				}
			}]
		}]
	});

	var panel = Ext.create('Ext.panel.Panel', {
		cls: 'scalr-ui-panel-columned',
		leftMenu: {
			menuId: 'account',
			itemId: 'users'
		},
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: {
			'reload': true,
			'maximize': 'all'
		},
		items: [
			grid
		,{
			cls: 'scalr-ui-panel-columned-form-wrapper',
			flex: 1,
			maxWidth: 580,
			items: [
				form
			]
		}]	
	});
	return panel;
});

Ext.define('Scalr.ui.DnsRecordsField',{
	extend: 'Ext.grid.Panel',	
	alias: 'widget.dnsrecords',
	stores: {own: null, system: null},
	zone: {
		domainName: ''
	},
	selType: 'selectedmodel',
	plugins: {
		ptype: 'dnsrowediting',
		pluginId: 'rowediting'
	},
	columns: [{
		text: 'Domain', 
		flex: 1, 
		dataIndex: 'name', 
		sortable: true, 
		editor: {
			xtype: 'textfield',
			emptyText: 'Domain',
            itemId: 'name'
		}
	},{
		text: 'TTL',
		width: 70,
		dataIndex: 'ttl', 
		sortable: true, 
		resizable: false,
		editor: {
			xtype: 'textfield',
			emptyText: 'TTL'
		}
	},{
		text: 'Type', 
		dataIndex: 'type', 
		sortable: true, 
		resizable: false,
		width: 90,
		editor: {
			xtype: 'combo',
			store: [ 'A', 'CNAME', 'MX', 'TXT', 'NS', 'SRV'],
			editable: false,
			flex: 1.3,
			emptyText: 'Type',
			listeners: {
				change: function () {
                    this.up('panel').down('dnsvaluefield').setType(this.getValue());
				}
			}
		}
	},{
		text: 'Value', 
		flex: 1,
		minWidth: 280, 
		dataIndex: 'value', 
		sortable: true, 
		resizable: false,
		xtype: 'templatecolumn', 
		tpl: '<tpl if="type == \'SRV\'">'+
				'{value} (priority: {priority}, weight: {weight}, port: {port})'+
			 '<tpl elseif="type == \'MX\'">'+
				'{value} (priority: {priority})'+
			 '<tpl else>'+
				'{value}'+
			 '</tpl>'
			,
		editor: 'dnsvaluefield'
	}],
	
	dockedItems: [{
        xtype: 'toolbar',
        ui: 'simple',
		dock: 'top',
		items: [{
			xtype: 'filterfield',
			itemId: 'livesearch',
			margin: 0,
			submitValue: false,
			filterFields: ['name', 'value']
		},{
			xtype: 'buttongroupfield',
			itemId: 'type',
			value: 'own',
			hidden: true,
			submitValue: false,
			margin: '0 0 0 24',
			defaults: {
				width: 90
			},
			items: [{
				text: 'Custom',
				value: 'own'
			},{
				text: 'System',
				value: 'system'
			}],
			listeners: {
				change: function(comp, value){
					this.up('grid').fireEvent('changetype', value);
				}
			}
		},{
			xtype: 'tbfill' 
		},{
			itemId: 'add',
            margin: '0 10 0 0',
            text: 'Add DNS record',
            cls: 'x-btn-green-bg',
			tooltip: 'Add DNS record',
			handler: function() {
				var grid = this.up('grid'),
					store = grid.getStore(),
					rowEditing = grid.getPlugin('rowediting');
				rowEditing.cancelEdit();
				store.insert(0, store.createModel({name: null, type: null, ttl: null, value: null, isnew: true}));
				rowEditing.startEdit(0, 0);
				rowEditing.getEditor().getForm().clearInvalid(0);
			}
		},{
			itemId: 'delete',
            ui: 'paging',
            iconCls: 'x-tbar-delete',
			disabled: true,
			tooltip: 'Delete selected records',
			handler: function() {
				var grid = this.up('grid'),
					selection = grid.getSelectionModel().getSelection();
				//are we going to ask for a confirmation here?
				/*Scalr.Confirm({
					type: 'delete',
					msg: 'Delete selected ' + selection.length + ' DNS record(s)?',
					success: function (data) {*/
						grid.suspendLayouts();
						grid.getStore().remove(selection);
						grid.resumeLayouts(true);
					/*}
				});*/
			}
		}]
	}],	
	viewConfig: {
		//focusedItemCls: 'x-grid-row-over',
		//overItemCls: '',
		plugins: {
			ptype: 'dynemptytext',
			emptyText: '<b style="line-height:20px">No DNS records were found to match your search.</b><br/> Try modifying your search criteria',
			emptyTextNoItems:	'<b style="line-height:20px">No DNS records.</b><br/>'+
								'Click "<b>Add DNS record</b>" button to create one.'
		},
		loadingText: 'Loading users ...',
		deferEmptyText: false,
		cls: 'x-grid-view-dns-records'
	},
	setType: function(type) {
		this.down('#type').setValue(type);
	},
	listeners: {
		viewready: function() {
			this.down('#livesearch').store = this.getStore();
			this.down('#type')[this.stores.system && this.stores.system.data.length?'show':'hide']();
		},
		selectionchange: function(selModel, selected) {
			this.down('#delete').setDisabled(!selected.length);
		},
		changetype: function(type) {
			var selModel = this.getSelectionModel(),
				liveSearch = this.down('#livesearch'),
				rowEditing = this.getPlugin('rowediting');
			if (type == 'system') {
				rowEditing.cancelEdit();
				rowEditing.disable();
				this.down('#add').disable();
				selModel.deselectAll();
				selModel.setLocked(true);
			} else {
				rowEditing.enable();
				this.down('#add').enable();
				selModel.setLocked(false);
			}
			this.reconfigure(this.stores[type]);
			liveSearch.clearFilter();
			liveSearch.store = this.stores[type];
			
		},
		closeeditor: function() {
			var rowEditing = this.getPlugin('rowediting');
			if (rowEditing.editing) {
				var dnsRecordForm = rowEditing.getEditor().getForm();
				if (dnsRecordForm.getRecord().get('isnew') && 
					Ext.isEmpty(dnsRecordForm.findField('name').getValue()) &&
					Ext.isEmpty(dnsRecordForm.findField('value').getValue()) ) {
					rowEditing.cancelEdit();
				} else {
					rowEditing.continuousAdd = false;
					rowEditing.completeEdit();
					rowEditing.continuousAdd = true;
					if (rowEditing.editing) {
						Scalr.message.Error('Please correct errors in DNS records first.');
						return false;
					}
				}
			}
			return true;
		}
	}
	
});

Ext.define('Scalr.ui.GridDnsRowEditing', {
	extend: 'Ext.grid.plugin.RowEditing',
	alias: 'plugin.dnsrowediting',
	clicksToMoveEditor: 1,
	clicksToEdit: 1,
	autoCancel: true,
	errorSummary: false,
	continuousAdd: true,

    init: function(grid) {
		this.mon(Ext.getDoc(), {
			mousewheel: this.onDocClick,
			mousedown: this.onDocClick,
			scope: this
		});
        this.callParent(arguments);
    },

	destroy: function() {
		var doc = doc = Ext.getDoc();
		doc.un('mousewheel', this.onDocClick, this);
		doc.un('mousedown', this.onDocClick, this);
		this.callParent(arguments);
	},

	listeners: {
        beforeedit: function() {
            return this.disabled !== true;
        },
		startedit: function(editor, o) {
            var frm = this.getEditor().getForm();
			if (o.record.get('isnew')) {
				frm.findField('type').setValue('A');
				frm.findField('ttl').setValue(14400);
			} else {
				frm.findField('type').fireEvent('change');
			}
            var field = this.getEditor().down('dnsvaluefield');
            field.down('#priority').setValue(o.record.get('priority'));
            field.down('#port').setValue(o.record.get('port'));
            field.down('#weight').setValue(o.record.get('weight'));
            field.down('#value').setValue(o.record.get('value'));
			this.grid.getSelectionModel().deselect(o.record);
            frm.findField('name').inputEl.focus(true);
		},
		canceledit: function(editor, o) {
			if (o.record.get('isnew')) {
				this.grid.getStore().remove(o.record);
			} else {
				var selModel = this.grid.getSelectionModel();
				selModel.deselect(o.record);
				selModel.refreshLastFocused();
			}
		},
		edit: function(editor, o) {
			var selModel = this.grid.getSelectionModel(),
				store = this.grid.getStore(),
                field = this.getEditor().down('dnsvaluefield');
			selModel.deselect(o.record);
            o.record.set({
                value: field.down('#value').getValue(),
                port: field.down('#port').getValue(),
                weight: field.down('#weight').getValue(),
                priority: field.down('#priority').getValue()
            });

			o.record.set('ttl', o.record.get('ttl') || 0);
			if (o.record.get('isnew')) {
				o.record.set('isnew', false);
				if (this.continuousAdd) {
					store.insert(0, store.createModel({name: null, type: null, ttl: null, value: null, isnew: true}));
					this.startEdit(0, 0);
					this.getEditor().getForm().clearInvalid(0);
				}
			}
			selModel.refreshLastFocused();
		},
		validateedit: function(editor, o){
			var me = this,
				form = me.getEditor().getForm(),
				valid = true,
				name = o.newValues.name == '@' || o.newValues.name == '' ? me.grid.zone['domainName'] + '.' : o.newValues.name;

			o.store.data.each(function(){
				if (o.record !== this) {
					var rname = this.get('name');
					rname = rname == '@' || rname == '' ? me.grid.zone['domainName'] + '.' : rname;
					if ((o.newValues.type == 'CNAME' || this.get('type') == 'CNAME') && this.get('name') == name) {
						form.findField('name').markInvalid('Conflict name ' + name);
						valid = false;
						return false;
					}
				}
			});

			return valid;
		}

	},
	onDocClick: function(e) {
		if (!this.isDestroyed) {
			var cancelEdit = false,
                editor = this.getEditor();
			cancelEdit = !e.within(this.grid.view.el, false, true) && !e.within(editor.el, false, true);
			if (cancelEdit) {
				editor.getForm().getFields().each(function(){
					if(this.picker) {
						cancelEdit = !e.within(this.picker.el, false, true);
					}
					return cancelEdit;
				});
			}
			if (cancelEdit) {
				this.cancelEditIf();
			}
		}
	},
	cancelEditIf: function() {
		if (this.editing) {
			this.completeEdit();
			if (this.editing) {
				if (!this.editor.getForm().getRecord().get('isnew')) {
					return false;
				} else {
					this.cancelEdit();
				}
			}
		}
		return true;
	},
    onCellClick: function(view, cell, colIdx, record, row, rowIdx, e) {
		/* Changed */
		if (!this.cancelEditIf()) {
			return;
		}
		if (Ext.fly(e.getTarget()).hasCls('x-grid-row-checker')) {//skip row select checkbox click
			return;
		}
		/* End */
        if(!view.expanderSelector || !e.getTarget(view.expanderSelector)) {
            this.startEdit(record, view.ownerCt.columnManager.getHeaderAtIndex(colIdx));
        }
    },
    startEdit: function(record, columnHeader) {
        var res = this.callParent(arguments);
        if (res) {
            this.fireEvent('startedit', this, this.context);
        }
        return res;
    },
    onEnterKey: function(e) {
        var me = this,
            grid = me.grid,
            selModel = grid.getSelectionModel(),
            record,
            columnHeader;
            
        if (selModel.lastFocused) {
            record = selModel.lastFocused;
            columnHeader = grid.columnManager.getHeaderAtIndex(0);
            me.startEdit(record, columnHeader);
        } else {
            this.callParent(arguments);
        }
    }
});

Ext.define('Scalr.ui.FormFieldDnsValue', {
	extend: 'Ext.container.Container',
	alias: 'widget.dnsvaluefield',

	mixins: {
		field: 'Ext.form.field.Field'
	},

	allowBlank: false,
    layout: 'hbox',
	items: [{
		xtype: 'textfield',
		itemId: 'priority',
		name: 'priority',
		emptyText: 'priority',
		hidden: true,
		flex: 1,
		maxWidth: 60,
		margin: '0 0 0 5'
	}, {
		xtype: 'textfield' ,
		itemId: 'weight',
		name: 'weight',
		emptyText: 'weight',
		hidden: true,
		flex: 1,
		maxWidth: 60,
		margin: '0 0 0 5'
	}, {
		xtype: 'textfield',
		itemId: 'port',
		name: 'port',
		emptyText: 'port',
		hidden: true,
		flex: 1,
		maxWidth: 60,
		margin: '0 0 0 5'
	},{
		xtype: 'textfield',
		itemId: 'value',
		name: 'value',
		emptyText: 'value',
		flex: 3,
		margin: '0 0 0 5'
	}],

    setType: function(type) {
        this.getComponent('port').hide();
        this.getComponent('weight').hide();
        this.getComponent('priority').hide();

        if (type == 'MX' || type == 'SRV') {
            this.getComponent('priority').show();
        }

        if (type == 'SRV') {
            this.getComponent('weight').show();
            this.getComponent('port').show();
        }

    }
});
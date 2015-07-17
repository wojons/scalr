Ext.define('Scalr.ui.DnsRecordsField',{
    extend: 'Ext.grid.Panel',
    alias: 'widget.dnsrecords',
    stores: {own: null, system: null},
    selType: 'selectedmodel',
    plugins: {
        ptype: 'dnsrowediting',
        pluginId: 'rowediting'
    },
    initComponent: function() {
        //defaultRecords toolbar ui
        if (this.dockedToolbarUi) {
            this.dockedItems[0].ui = this.dockedToolbarUi;
        }
        this.callParent(arguments);
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
        },
        renderer: function (value) {
            return Ext.String.htmlEncode(value);
        }
    },{
        text: 'TTL',
        width: 80,
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
        width: 110,
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
                '{value:htmlEncode} (priority: {priority:htmlEncode}, weight: {weight:htmlEncode}, port: {port:htmlEncode})'+
             '<tpl elseif="type == \'MX\'">'+
                '{value} (priority: {priority:htmlEncode})'+
             '<tpl else>'+
                '{value:htmlEncode}'+
             '</tpl>'
            ,
        editor: 'dnsvaluefield'
    }],

    dockedItems: [{
        xtype: 'toolbar',
        ui: 'inline',
        dock: 'top',
        items: [{
            xtype: 'filterfield',
            itemId: 'livesearch',
            margin: 0,
            submitValue: false,
            filterFields: ['name', 'value'],
            isRecordMatched: function (record) {
                var me = this;

                var value = me.getValue().toLowerCase();
                if (record.get('editing')) return true;
                return me.getFilterFields().some(function (field) {
                    var fieldValue = !Ext.isFunction(field)
                        ? record.get(field)
                        : field(record);

                    return !Ext.isEmpty(fieldValue)
                        && fieldValue.toLowerCase().indexOf(value) !== -1;
                });
            },
            listeners: {
                change: function() {
                    this.store.getUnfiltered().each(function(record){
                        record.set('editing', false);
                    });
                }
            }
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
            cls: 'x-btn-green',
            tooltip: 'Add DNS record',
            handler: function() {
                var grid = this.up('grid'),
                    store = grid.getStore(),
                    rowEditing = grid.getPlugin('rowediting'),
                    record;
                rowEditing.cancelEdit();
                record = store.createModel({isnew: true, editing: true});
                store.insert(0, record);
                grid.view.focusRow(record);
                rowEditing.startEdit(record);
                rowEditing.getEditor().getForm().clearInvalid(0);
            }
        },{
            itemId: 'delete',
            iconCls: 'x-btn-icon-delete',
            cls: 'x-btn-red',
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
        plugins: {
            ptype: 'dynemptytext',
            emptyText: 'No DNS records were found to match your search. Try modifying your search criteria',
            emptyTextNoItems: 'No DNS records.'
        },
        loadingText: 'Loading ...',
        deferEmptyText: false,
        markDirty: false
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
            liveSearch.resetFilter();
            liveSearch.store = this.stores[type];
            this.stores[type].fireEvent('refresh');//refresh dynemptytext

        },
        closeeditor: function() {
            var rowEditing = this.getPlugin('rowediting');
            if (rowEditing.editing) {
                var editor = rowEditing.getEditor(),
                    frm = editor.getForm();
                if (frm.getRecord().get('isnew') &&
                    Ext.isEmpty(frm.findField('name').getValue()) &&
                    Ext.isEmpty(frm.findField('value').getValue()) ) {
                    rowEditing.cancelEdit();
                } else {
                    rowEditing.suspendContinuousAdd++;
                    rowEditing.completeEdit();
                    rowEditing.suspendContinuousAdd--;
                    if (rowEditing.editing) {
                        this.view.focusRow(frm.getRecord());
                        Scalr.message.Error('Please fix errors before saving.');
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
    suspendContinuousAdd: 0,

    init: function(grid) {
        this.mon(Ext.getDoc(), {
            mousewheel: this.onDocClick,
            mousedown: this.onDocClick,
            scope: this
        });
        this.callParent(arguments);
    },

    destroy: function() {
        this.mun(Ext.getDoc(), {
            mousewheel: this.onDocClick,
            mousedown: this.onDocClick,
            scope: this
        });
        this.callParent(arguments);
    },

    listeners: {
        beforeedit: function() {
            return this.disabled !== true;
        },
        startedit: function(editor, o) {
            var editor = this.getEditor(),
                frm = editor.getForm();
            frm.findField('type').fireEvent('change');

            editor.down('dnsvaluefield').setValues({
                priority: o.record.get('priority'),
                port: o.record.get('port'),
                weight: o.record.get('weight'),
                value: o.record.get('value')
            });
            o.record.set('editing', true);
            this.grid.getSelectionModel().deselect(o.record);
        },
        canceledit: function(editor, o) {
            if (o.record.get('isnew')) {
                this.grid.getStore().remove(o.record);
            }
        },
        edit: function(editor, o) {
            var store = this.grid.getStore(),
                field = this.getEditor().down('dnsvaluefield'),
                record;
            o.record.set({
                value: field.down('#value').getValue(),
                port: field.down('#port').getValue(),
                weight: field.down('#weight').getValue(),
                priority: field.down('#priority').getValue()
            });

            o.record.set('ttl', o.record.get('ttl') || 0);
            if (o.record.get('isnew')) {
                o.record.set('isnew', false);
                if (!this.suspendContinuousAdd) {
                    record = store.createModel({isnew: true, editing: true});
                    store.insert(0, record);
                    this.grid.view.focusRow(record);
                    this.startEdit(record);
                    this.getEditor().getForm().clearInvalid(0);
                }
            }
        },
        validateedit: function(editor, o){
            var me = this,
                form = me.getEditor().getForm(),
                valid = true,
                name = (o.newValues.name == '@' || o.newValues.name == '') && me.grid.zone ? me.grid.zone['domainName'] + '.' : o.newValues.name;

            o.store.getUnfiltered().each(function(record){
                if (o.record !== record) {
                    var rname = record.get('name');
                    rname = (rname == '@' || rname == '') && me.grid.zone ? me.grid.zone['domainName'] + '.' : rname;
                    if ((o.newValues.type == 'CNAME' || record.get('type') == 'CNAME') && record.get('name') == name) {
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
            this.suspendContinuousAdd++;
            this.completeEdit();
            this.suspendContinuousAdd--;
            if (this.editing) return false;
        }
        return true;
    },

    onCellClick: function(view, cell, colIdx, record, row, rowIdx, e) {
        var el = Ext.fly(e.getTarget());
		if (el.hasCls('x-grid-row-checker') || el.query('.x-grid-row-checker').length) {
            //skip row select checkbox click
            return;
        }

        if (!this.cancelEditIf()) {
            view.focusRow(this.getEditor().getRecord());
            return;
        }
        this.callParent(arguments);
    },

    startEdit: function() {
        var res = this.callParent(arguments);
        if (res) {
            this.fireEvent('startedit', this, this.context);
        }
        return res;
    }
});


Ext.define('Scalr.ui.FormFieldDnsValue', {
    extend: 'Ext.form.FieldContainer',
    alias: 'widget.dnsvaluefield',

    allowBlank: false,
    layout: 'hbox',
    items: [{
        xtype: 'textfield',
        itemId: 'priority',
        name: 'priority',
        emptyText: 'priority',
        hidden: true,
        flex: 1,
        maxWidth: 75
    }, {
        xtype: 'textfield' ,
        itemId: 'weight',
        name: 'weight',
        emptyText: 'weight',
        hidden: true,
        flex: 1,
        maxWidth: 70
    }, {
        xtype: 'textfield',
        itemId: 'port',
        name: 'port',
        emptyText: 'port',
        hidden: true,
        flex: 1,
        maxWidth: 70
    },{
        xtype: 'textfield',
        itemId: 'value',
        name: 'value',
        emptyText: 'value',
        flex: 3
    }],

    setType: function(type) {
        this.getComponent('port').hide();
        this.getComponent('weight').hide();
        this.getComponent('priority').hide();

        if (type === 'MX' || type === 'SRV') {
            this.getComponent('priority').show();
        }

        if (type === 'SRV') {
            this.getComponent('weight').show();
            this.getComponent('port').show();
        }
    },
    setValues: function(values) {
        var me = this;
        Ext.Object.each(values, function(name, value) {
            me.getComponent(name).setValue(value);
        });
    },
    isFocusable: function() {
        return this.isVisible(true);
    },
    focus: function() {
        this.getComponent('value').focus();
    }
});
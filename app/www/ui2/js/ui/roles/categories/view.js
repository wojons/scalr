Scalr.regPage('Scalr.ui.roles.categories.view', function (loadParams, moduleParams) {
    var isReadOnly = Scalr.scope == 'account' && !Scalr.isAllowed('ROLES_ACCOUNT', 'manage') ||
                     Scalr.scope == 'environment' && !Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage');
    var store = Ext.create('store.store', {
        fields: [
            'id',
            'name',
            'used',
            'status',
            'scope'
        ],
        data: moduleParams['categories'],
        proxy: {
            type: 'ajax',
            url: '/roles/categories/xList/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                successProperty: 'success'
            }
        },
        sorters: [{
            property: 'name'
        }],
        listeners: {
            beforeload: function () {
                grid.down('#add').toggle(false, true);
            },
            filterchange: function () {
                grid.down('#add').toggle(false, true);
            }
        }
    });

	var reconfigurePage = function(params) {
        if (params.catId) {
            cb = function() {
                if (params.catId === 'new') {
                    panel.down('#add').toggle(true);
                } else {
                    panel.down('#liveSearch').reset();
                    var record = store.getById(params.catId);
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
        cls: 'x-panel-column-left',
        store: store,
        flex: 1,
        selModel: !isReadOnly? 
        {
            selType: 'selectedmodel',
            getVisibility: function(record) {
                return Scalr.scope == record.get('scope') && !record.get('used');
            }
        } : null,
        plugins: [ 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true
        }],
        columns: [{
            text: 'Category',
            flex: 1,
            dataIndex: 'name',
            resizable: false,
            sortable: true,
            xtype: 'templatecolumn',
            tpl: new Ext.XTemplate('{[this.getScope(values.scope)]}&nbsp;&nbsp;{name}',
                {
                    getScope: function(scope){
                        return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('rolecategory') + '"/>';
                    }
                }
            )
        }, {
            header: 'Status',
            minWidth: 90,
            width: 90,
            dataIndex: 'status',
            sortable: true,
            resizable: false,
            xtype: 'statuscolumn',
            statustype: 'rolecategory'
        }],
        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No categories found.',
                emptyTextNoItems: 'You have no categories added yet.'
            },
            loadingText: 'Loading categories ...',
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
                filterFields: ['name'],
                handler: null,
                store: store
            },{
                xtype: 'cyclealt',
                name: 'scope',
                getItemIconCls: false,
                hidden: Scalr.user.type === 'ScalrAdmin',
                width: 130,
                changeHandler: function (me, menuItem) {
                    store.applyProxyParams({
                        scope: menuItem.value
                    });
                },
                getItemText: function (item) {
                    return item.value
                        ? 'Scope: &nbsp;<img src="'
                            + Ext.BLANK_IMAGE_URL
                            + '" class="' + item.iconCls
                            + '" title="' + item.text + '" />'
                        : item.text;
                },
                menu: {
                    cls: 'x-menu-light x-menu-cycle-button-filter',
                    minWidth: 200,
                    items: [{
                        text: 'All scopes',
                        value: null
                    }, {
                        text: 'Scalr scope',
                        value: 'scalr',
                        iconCls: 'scalr-scope-scalr'
                    }, {
                        text: 'Account scope',
                        value: 'account',
                        iconCls: 'scalr-scope-account'
                    }, {
                        text: 'Environment scope',
                        value: 'environment',
                        iconCls: 'scalr-scope-environment',
                        hidden: Scalr.scope !== 'environment',
                        disabled: Scalr.scope !== 'environment'
                    }]
                }
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
                text: 'New category',
                cls: 'x-btn-green',
                enableToggle: true,
                hidden: isReadOnly,
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();
                        form.loadRecord(grid.store.createModel({scope: Scalr.scope}));
                        form.down('[name=name]').focus();

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
                }
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Delete category ',
                hidden: isReadOnly,
                handler: function() {
                    var action = this.getItemId(),
                        actionMessages = {
                            'delete': ['Delete selected categories?', 'Deleting categories ...']
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
                        params: {action: action, scope: Scalr.scope},
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
                    request.url = '/roles/categories/xGroupActionHandler';
                    request.params['ids'] = Ext.encode(ids);

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

        disableButtons: function (disabled, scope, isCategoryUsed) {
            var me = this;

            var tooltip = !disabled
                ? ''
                : Scalr.utils.getForbiddenActionTip('category', scope);

            Ext.Array.each(
                me.getDockedComponent('buttons').query('#save, #delete'),
                function (button) {
                    button.
                        setTooltip(tooltip).
                        setDisabled(
                            button.getItemId() !== 'delete'
                                ? disabled
                                : disabled || isCategoryUsed
                    );
                }
            );

            return me;
        },

        toggleScopeInfo: function(record) {
            var me = this,
                scopeInfoField = me.down('#scopeInfo');
            if (Scalr.scope != record.get('scope')) {
                scopeInfoField.setValue(Scalr.utils.getScopeInfo('role category', record.get('scope'), record.get('id')));
                scopeInfoField.show();
            } else {
                scopeInfoField.hide();
            }
            return me;
        },

        listeners: {
            beforeloadrecord: function(record) {
                var frm = this.getForm(),
                    isNewRecord = !record.store,
                    scope = record.get('scope'),
                    isCategoryUsed = !!record.get('used');

                this.down('#save').setText(isNewRecord ? 'Create' : 'Save');

                if (Scalr.scope === scope) {
                    this.disableButtons(isReadOnly, scope, isCategoryUsed);
                    this.down('#formtitle').setTitle((isNewRecord ? 'New' : 'Edit') + ' category');
                } else {
                    this.disableButtons(true, scope, isCategoryUsed);
                    this.down('#formtitle').setTitle('Edit category');
                }
                
                frm.findField('name').setReadOnly(scope !== Scalr.scope || isReadOnly);
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
            labelWidth: 90,
            allowBlank: false
        },
        items: [{
            xtype: 'displayfield',
            itemId: 'scopeInfo',
            cls: 'x-form-field-info x-form-field-info-fit',
            anchor: '100%',
            hidden: true
        },{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            itemId: 'formtitle',
            title: '&nbsp;',
            items: [{
                xtype: 'textfield',
                name: 'name',
                allowBlank: false,
                regex: /^[a-z0-9][a-z0-9- ]*[a-z0-9]$/i,
                regexText: 'Name should start and end with letter or number and contain only letters, numbers, spaces and dashes.',
                fieldLabel: 'Name'
            },{
                xtype: 'displayfield',
                cls: 'hideoncreate',
                name: 'status',
                renderer: function(value) {
                    var record = this.up('form').getForm().getRecord(),
                        used,
                        text = '';
                    if (record) {
                        used = record.get('used');
                        if (used) {
                            text = 'This <b>Category</b> is currently used by ' + used+'&nbsp;Role(s)';
                        } else {
                            text = 'This <b>Category</b> is currently not used by any <b>Role</b>.';
                        }

                    }
                    return text;
                }
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
                        name = frm.findField('name').getValue(),
                        record = frm.getRecord();
                    if (frm.isValid()) {
                        params = {
                            id: record.store ? record.get('id') : null
                        };

                        var fn = function(replace) {
                            Scalr.Request({
                                processBox: {
                                    type: 'save'
                                },
                                url: '/roles/categories/xSave',
                                form: frm,
                                params: params,
                                success: function (data) {
                                    if (!record.store) {
                                        record = store.add(data.category)[0];
                                    } else {
                                        record.set(data.category);
                                    }
                                    grid.clearSelectedRecord();
                                    grid.setSelectedRecord(record);
                                    Scalr.CachedRequestManager.get().setExpired({url: '/roles/categories/xList'});
                                }
                            });
                        };

                        fn();
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
                            msg: 'Delete category?',
                            type: 'delete'
                        },
                        processBox: {
                            msg: 'Deleting...',
                            type: 'delete'
                        },
                        scope: this,
                        url: '/roles/categories/xGroupActionHandler',
                        params: { ids: Ext.encode([record.get('id')]), action: 'delete' },
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
            menuTitle: 'Role Categories',
            menuHref: '#' + Scalr.utils.getUrlPrefix() + '/roles/categories',
            menuFavorite: Ext.Array.contains(['account', 'environment'], Scalr.scope),
        },
        stateId: 'grid-roles-categories-view',
        listeners: {
            applyparams: reconfigurePage
        },
        items: [
            grid
            ,{
                xtype: 'container',
                itemId: 'rightcol',
                flex: .8,
                maxWidth: 900,
                minWidth: 640,
                layout: 'fit',
                items: form
            }]
    });

    return panel;
});

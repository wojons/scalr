Ext.define('Scalr.ui.RoleScriptingGrid', {
	extend: 'Ext.grid.Panel',
	alias: 'widget.scriptinggrid',
    hideHeaders: true,
    groupingStartCollapsed: false,
    groupingShowTotal: false,
    hideDeleteButton: false,
    addButtonHandler: null,
    features: [{
        ftype: 'rowbody',
        getAdditionalData: function(data, rowIndex, record, orig) {
            var name = '',
                target = record.get('target');
            switch (target) {
                case '':
                    name = '<span>No target (no execution)</span>';
                break;
                case 'farm':
                    name = 'on <span style="color:#2BAF23">all instances in the farm</span>';
                break;
                case 'role':
                    name = 'on <span style="color:#9e5ac7">all instances of this role</span>';
                break;
                case 'instance':
                    name = 'on <span style="color:#1582EE">triggering instance only</span>';
                break;
                case 'roles':
                    var roleIds = target == 'role' ? [this.grid.up('scriptfield2').farmRoleId] : (record.get('target_roles') || []),
                        roles = [],
                        rolesStore = this.grid.form.getForm().findField('target_roles').getStore();

                    for (var i=0, len=roleIds.length; i<len; i++) {
                        var res = rolesStore.query('farm_role_id', roleIds[i]);
                        if (res.length){
                            var rec = res.first();
                            roles.push('<span style="color:#' + Scalr.utils.getColorById(rec.get('farm_role_id'))+'">' + rec.get('alias') + '</span>');
                        }
                    }
                    name = roles.length ? 'on <span><i>' + roles.join(', ') + '</i></span>' : '&nbsp;';
                break;
                case 'behaviors':
                    var bahaviorIds = record.get('target_behaviors') || [],
                        behaviors = [],
                        behaviorsStore = this.grid.form.getForm().findField('target_behaviors').getStore();

                    for (var i=0, len=bahaviorIds.length; i<len; i++) {
                        var res = behaviorsStore.query('id', bahaviorIds[i]);
                        if (res.length){
                            var rec = res.first();
                            behaviors.push(rec.get('name'));
                        }
                    }
                    name = behaviors.length ? 'on all <span><i>' + behaviors.join(', ') + '</i></span> roles' : '&nbsp;';
                break;
            }

            return {
                rowBody: '<span title="Execution mode" style="float:left;width:52px;margin-right:7px;text-align:center;font-size:90%;line-height:95%;word-wrap:break-word">' + (record.get('issync') == 1 ? 'blocking' : '<span style="color:green;position:relative;top:-5px;">non blocking</span>') + '</span><div style="margin:0 57px 5px">'+name+'</div>',
                rowBodyColspan: this.view.headerCt.getColumnCount(),
                rowBodyCls: record.get('system') ? 'x-grid-row-system' : ''
            };
        }
    },{
        ftype: 'rowwrap'
    }],
    store: {
        fields: [ 'script_id', 'script', 'event', /*'event_order',*/ 'target', 'target_roles', 'target_behaviors', 'issync', 'timeout', 'version', 'params', {name: 'order_index', type: 'int'}, 'system', 'role_script_id', 'hash', 'script_path', 'run_as' ],
        filterOnLoad: true,
        sortOnLoad: true,
        sorters: ['order_index'],
        proxy: 'object',
        groupField: 'event'
    },
    columns: [{
        flex: 1,
        dataIndex: 'order_index',
        renderer: function(val, meta, record, rowIndex, colIndex, store) {
            var script = record.get('script') || record.get('script_path');
            if (record.dirty) {
                meta.tdCls += ' x-grid-dirty-cell';
            }
            return '<span style="float:left;width:46px;font-size:90%;">#'+record.get('order_index')+'</span> <b>'+script+'</b>';
        }
    }],
    initComponent: function() {
        var me = this;
        this.features = Ext.clone(this.features);
        this.features.push({
            id:'grouping',
            ftype:'grouping',
            startCollapsed: this.groupingStartCollapsed,
            groupHeaderTpl: [
                '{children:this.getGroupName}',
                {
                    getGroupName: function(children) {
                        if (children.length > 0) {
                            var name = children[0].get('event');
                            return '<span style="font-weight:normal">On <span style="font-weight:bold">' + (name === "*" ? "All events" : name) + '</span> perform: ' + (me.groupingShowTotal ? '&nbsp;' + children.length + ' script' + (children.length > 1? 's' : '') : '') + '</span>';
                        }
                    }
                }
            ]
        });
        if (this.addButtonHandler) {
            this.features.push({
                ftype: 'addbutton',
                text: 'Add orchestration rule',
                handler: this.addButtonHandler
            });
        }
        if (!this.hideDeleteButton) {
            this.columns = Ext.clone(this.columns);
            this.columns.push({
                xtype: 'templatecolumn',
                tpl: '<tpl if="!system"><img style="cursor:pointer" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Delete rule" src="'+Ext.BLANK_IMAGE_URL+'"/></tpl>',
                width: 42,
                sortable: false,
                dataIndex: 'id',
                align:'left'
            });
        }
        this.callParent(arguments);
    }
});

Ext.define('Scalr.ui.RoleScriptingPanel', {
	extend: 'Ext.container.Container',
	alias: 'widget.scriptfield2',

    mode: 'farmrole',
	layout: {
		type: 'hbox',
		align: 'stretch'
	},
    beforeRender: function() {
        if (this.mode === 'role') {
            this.down('#targetRolesWrap').hide();
            this.down('#targetBehaviorsWrap').hide();
            this.down('#targetRole').show();
        }
        this.callParent(arguments);
    },
	items: [{
        xtype: 'scriptinggrid',
        cls: 'x-panel-column-left x-grid-shadow x-grid-role-scripting',
        maxWidth: 500,
        minWidth: 350,
        flex: .6,
        bodyStyle: 'box-shadow:none',
		plugins: [{
			ptype: 'focusedrowpointer',
			addOffset: 12
		}],
		listeners: {
			viewready: function() {
				var me = this;
                me.form = me.up('scriptfield2').down('form');
				me.down('#scriptsLiveSearch').store = me.store;
				me.getSelectionModel().on('focuschange', function(gridSelModel){
					if (!me.disableOnFocusChange) {
						if (gridSelModel.lastFocused) {
							if (gridSelModel.lastFocused != me.form.getRecord()) {
								me.form.loadRecord(gridSelModel.lastFocused);
							}
						} else {
							me.form.deselectRecord();
						}
					}
				});
			},
            itemclick: function (view, record, item, index, e) {
                if (e.getTarget('img.x-icon-action-delete')) {
                    var selModel = view.getSelectionModel();
                    if (record === selModel.getLastFocused()) {
                        selModel.deselectAll();
                        selModel.setLastFocused(null);
                    }
                    view.store.remove(record);
                    return false;
                }
            },
            rowbodyclick: function(view, node) {
                var selModel = view.getSelectionModel();
                selModel.deselectAll();
                selModel.setLastFocused(view.getRecord(Ext.fly(node).prev()));
            }
		},
		viewConfig: {
			plugins: {
				ptype: 'dynemptytext',
				emptyText: '<div class="title">No rules were found to match your search.</div> Try modifying your search criteria or <a class="add-link" href="#">creating a new orchestration&nbsp;rule</a>.',
				emptyTextNoItems: 'Click on the button above to create your first orchestration&nbsp;rule',
				onAddItemClick: function() {
					this.client.ownerCt.down('#add').handler();
				}
			},
			loadingText: 'Loading scripts ...',
			deferEmptyText: false,
			overflowY: 'auto',
			overflowX: 'hidden',
			getRowClass: function(record){
				var cls = [];
				if (record.get('system')) {
					cls.push('x-grid-row-system');
				}
				return cls.join(' ');
            }
		},

		dockedItems: [{
			xtype: 'toolbar',
            ui: 'simple',
			dock: 'top',
            style: 'padding-right:0',
			items: [{
				xtype: 'filterfield',
				itemId: 'scriptsLiveSearch',
				margin: 0,
                width: 180,
				filterFields: ['script', 'script_path'],
				listeners: {
					afterfilter: function(){
                        var selModel = this.up('grid').getSelectionModel();
                        selModel.deselectAll();
                        selModel.setLastFocused(null);
					}
				}
			},{
				xtype: 'tbfill'
			},{
				itemId: 'add',
                text: 'Add rule',
                cls: 'x-btn-green-bg',
				handler: function() {
					var grid = this.up('grid'),
                        selModel = grid.getSelectionModel();
                    selModel.deselectAll();
					selModel.setLastFocused(null);
					grid.form.loadRecord(grid.getStore().createModel({issync: '1', order_index: 10}));
				}
			}]
		}]
	}, {
        xtype: 'container',
        layout: 'fit',
        flex: 1,
        margin: 0,
        items: {
            xtype: 'form',
            hidden: true,
            overflowY: 'auto',
            items: [{
                xtype: 'fieldset',
                title: 'Trigger event<img src="/ui2/images/icons/info_icon_16x16.png" style="position:relative;top: 2px;left:8px">',
                defaults: {
                    anchor: '100%',
                    maxWidth: 700,
                    labelWidth: 120
                },
                items: [{
                    xtype: 'combo',
                    store: {
                        fields: [
                            'id',
                            {
                                name: 'title',
                                convert: function(v, record){
                                    return record.get('id')=='*' ? 'All Events' : record.get('id');
                                }
                            },
                            'name'
                        ],
                        proxy: 'object'
                    },
                    valueField: 'id',
                    displayField: 'title',
                    queryMode: 'local',
                    allowBlank: false,
                    validateOnChange: false,
                    editable: false,
                    itemId: 'event',
                    name: 'event',
                    emptyText: 'Please select trigger',
                    listConfig: {
                        cls: 'x-boundlist-role-scripting-events',
                        style: 'white-space:nowrap',
                        getInnerTpl: function(displayField) {
                            return '<tpl if=\'id == \"*\"\'>All Events<tpl else>{id} <span style="color:#999">({name})</span></tpl>';
                        }
                    },
                    listeners: {
                        change: function(comp, value) {
                            var formPanel = this.up('form'),
                                form = formPanel.getForm(),
                                record = formPanel.getRecord(),
                                scriptRecord = comp.findRecordByValue(value),
                                disableFields = function(fields) {
                                    for (var i=0, len=fields.length; i<len; i++) {
                                        var field = formPanel.down(fields[i]);
                                        if (field.getValue()) {
                                            formPanel.down('#targetDoNotExecute').setValue(true);
                                        }
                                        field.disable();
                                    }
                                };
                            formPanel.suspendLayouts();
                            if (value) {
                                var c = formPanel.query('component[hideOn~=x-empty-trigger-hide]');
                                for (var i=0, len=c.length; i<len; i++) {
                                    c[i].setVisible(true);
                                }
                                if (record.store === undefined) {
                                    form.findField('order_index').setValue(comp.up('scriptfield2').getNextOrderIndexForEvent(value));
                                }
                            }
                            formPanel.savedScrollTop = formPanel.body.getScroll().top;
                            formPanel.updateRecordSuspended++;
                            switch (value) {
                                case 'HostDown':
                                    disableFields(['#targetInstance']);
                                break;
                                default:
                                    formPanel.down('#targetRoles').enable();
                                    formPanel.down('#targetInstance').enable();
                                break;
                            }
                            comp.next().update(scriptRecord ? scriptRecord.get('name') : '');
                            formPanel.body.scrollTo('top', formPanel.savedScrollTop);

                            formPanel.updateRecordSuspended--;
                            formPanel.updateRecord(['event', 'target', 'target_roles']);
                            formPanel.resumeLayouts(true);
                        }
                    }
                }, {
                    xtype: 'container',
                    itemId: 'eventDescription',
                    style: 'color:#666',
                    margin: '12 0 0'
                }]
            },{
                xtype: 'fieldset',
                title: 'Action<img src="/ui2/images/icons/info_icon_16x16.png" style="position:relative;top: 2px;left:8px">',
                hideOn: 'x-empty-trigger-hide',
                defaults: {
                    anchor: '100%',
                    maxWidth: 700,
                    labelWidth: 120
                },
                items: [{
                    xtype: 'tabpanel',
                    itemId: 'tabs',
                    cls: 'x-tabs-dark',
                    margin: '0 0 18 0',
                    height: 100,
                    defaults: {
                        listeners: {
                            activate: function(tab) {
                                var scriptParamsWrapper = this.up('form').down('#scriptParamsWrapper');
                                scriptParamsWrapper.setVisible(tab.itemId === 'scalrscript' ? !!scriptParamsWrapper.scriptHasParams : false);
                                var fields = tab.query('[isFormField]');
                                for (var i = 0, len = fields.length; i < len; i++) {
                                    fields[i].setDisabled(false);
                                }
                            },
                            deactivate: function(tab) {
                                var fields = tab.query('[isFormField]');
                                for (var i = 0, len = fields.length; i < len; i++) {
                                    fields[i].setDisabled(true);
                                }
                            }
                        }
                    },
                    items: [{
                        xtype: 'container',
                        itemId: 'scalrscript',
                        tabConfig: {
                            title: 'Scalr script'
                        },
                        items: [{
                            xtype: 'container',
                            cls: 'x-container-fieldset',
                            layout: {
                                type: 'hbox',
                                align: 'stretch'
                            },
                            items: [{
                                xtype: 'combo',
                                fieldLabel: 'Script',
                                disabled: true,
                                store: {
                                    fields: [ 'id', 'name', 'description', 'issync', 'timeout', 'revisions' ],
                                    proxy: 'object'
                                },
                                valueField: 'id',
                                displayField: 'name',
                                queryMode: 'local',
                                editable: true,
                                allowBlank: false,
                                validateOnChange: false,
                                forceSelection: true,
                                itemId: 'script',
                                name: 'script_id',
                                flex: 1,
                                labelWidth: 50,
                                emptyText: 'Please select script',
                                anyMatch: true,
                                listeners: {
                                    change: function(comp, value) {
                                        var scriptRecord = comp.findRecordByValue(value),
                                            formPanel = comp.up('form'),
                                            form = formPanel.getForm(),
                                            versionField = form.findField('version');
                                        if (!scriptRecord) return;
                                        formPanel.updateRecordSuspended++;
                                        formPanel.suspendLayouts();
                                        versionField.getStore().removeAll();
                                        versionField.reset();
                                        if (value) {
                                            var c = formPanel.query('component[hideOn~=x-empty-action-hide]');
                                            for (var i=0, len=c.length; i<len; i++) {
                                                c[i].setVisible(true);
                                            }
                                        }

                                        if (scriptRecord) {
                                            form.findField('script').setValue(scriptRecord.get('name'));
                                            var	revisions = Scalr.utils.CloneObject(scriptRecord.get('revisions'));

                                            //load script revisions
                                            for (var i in revisions) {
                                                revisions[i]['revisionName'] = revisions[i]['revision'];
                                            }
                                            var latestRev = Ext.Array.max(Object.keys(revisions), function (a, b) {
                                                return parseInt(a) > parseInt(b) ? 1 : -1;
                                            });

                                            revisions.splice(0, 0, { revision: -1, revisionName: 'Latest', fields: revisions[latestRev]['fields'] });
                                            versionField.getStore().load({data: revisions});

                                            versionField.setValue('-1');

                                            form.findField('target').setValue(scriptRecord.get('target'));
                                            form.findField('issync').setValue(scriptRecord.get('issync') || '0');
                                            form.findField('timeout').setValue(scriptRecord.get('timeout'));

                                            var order_index = form.findField('order_index').getValue();
                                            form.findField('order_index').setValue(order_index > 0 ? order_index : comp.up('scriptfield2').getNextOrderIndexForEvent(form.findField('event').getValue()));
                                        }
                                        formPanel.updateRecordSuspended--;
                                        formPanel.updateRecord(['script_id', 'script', 'version', 'target', 'issync', 'timeout', 'order_index']);
                                        formPanel.resumeLayouts(true);
                                    }
                                }
                            }, {
                                xtype: 'hiddenfield',
                                name: 'script'
                            }, {
                                xtype: 'combo',
                                fieldLabel: 'Version',
                                disabled: true,
                                store: {
                                    fields: [{ name: 'revision', type: 'string' }, 'revisionName', 'fields' ],
                                    proxy: 'object'
                                },
                                valueField: 'revision',
                                displayField: 'revisionName',
                                forceSelection: true,
                                queryMode: 'local',
                                editable: false,
                                name: 'version',
                                width: 140,
                                labelWidth: 50,
                                margin: '0 0 0 20',
                                listeners: {
                                    change: function (comp, value) {
                                        var formPanel = this.up('form'),
                                            scriptParamsWrapper = formPanel.down('#scriptParamsWrapper'),
                                            scriptParams = scriptParamsWrapper.down('#scripting_edit_parameters'),
                                            getParamValues = function(){
                                                var res = {};
                                                scriptParams.items.each(function(){
                                                    res[this.paramName] = this.getValue();
                                                })
                                                return res;
                                            };

                                        formPanel.updateRecordSuspended++;
                                        formPanel.savedScrollTop = formPanel.body.getScroll().top;
                                        scriptParamsWrapper.hide();
                                        scriptParamsWrapper.scriptHasParams = false;
                                        formPanel.suspendLayouts();
                                        if (value) {
                                            var revisionRecord = this.findRecord('revision', value),
                                                fields = revisionRecord ? revisionRecord.get('fields') : null;
                                            if (Ext.isObject(fields)) {
                                                var record = formPanel.getForm().getRecord(),
                                                    values = formPanel.isLoading && record ? record.get('params') : getParamValues();

                                                scriptParams.removeAll();
                                                formPanel.removeScriptParams();
                                                for (var i in fields) {
                                                    formPanel.updateScriptParam(i, values[i] || '');
                                                    scriptParams.add({
                                                        xtype: 'textfield',
                                                        fieldLabel: fields[i],
                                                        isScriptParamField: true,
                                                        paramName: i,
                                                        value: values[i] || '',
                                                        submitValue: false,
                                                        listeners: {
                                                            change: function(comp, value) {
                                                                formPanel.updateScriptParam(comp.paramName, value);
                                                            }
                                                        }
                                                    });
                                                }
                                                scriptParamsWrapper.scriptHasParams = true;
                                                scriptParamsWrapper.show();
                                            } else {
                                                scriptParams.removeAll();
                                                formPanel.removeScriptParams();
                                            }
                                            formPanel.getForm().findField('script_path').reset();
                                        } else {
                                            formPanel.removeScriptParams();
                                            scriptParams.removeAll();
                                        }
                                        formPanel.resumeLayouts(true);
                                        formPanel.body.scrollTo('top', formPanel.savedScrollTop);
                                        formPanel.updateRecordSuspended--;
                                        formPanel.updateRecord(['version']);

                                    }
                                }
                            }]
                        }]
                    },{
                        xtype: 'container',
                        itemId: 'localscript',
                        cls: 'x-container-fieldset',
                        layout: 'anchor',
                        defaults: {
                            anchor: '100%'
                        },
                        tabConfig: {
                            title: 'Local script'
                        },
                        items: [{
                            xtype: 'textfield',
                            name: 'script_path',
                            disabled: true,
                            fieldLabel: 'Path',
                            allowBlank: false,
                            emptyText: '/path/to/the/script',
                            labelWidth: 50,
                            listeners: {
                                change: function(comp, value) {
                                    var formPanel = this.up('form');
                                    if (value) {
                                        var c = formPanel.query('component[hideOn~=x-empty-action-hide]');
                                        for (var i=0, len=c.length; i<len; i++) {
                                            c[i].setVisible(true);
                                        }
                                        formPanel.getForm().findField('script_id').reset();
                                    }
                                    formPanel.updateRecord(['script_path']);
                                }
                            }
                        }]
                    },{
                        xtype: 'container',
                        itemId: 'chef',
                        cls: 'x-container-fieldset',
                        layout: 'anchor',
                        defaults: {
                            anchor: '100%'
                        },
                        tabConfig: {
                            title: 'Chef runlist'
                        },
                        items: [{
                            xtype: 'label',
                            text: 'Coming soon...'
                        }]
                    }]
                },{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'buttongroupfield',
                        fieldLabel: 'Execution mode',
                        editable: false,
                        name: 'issync',
                        labelWidth: 110,
                        width: 380,
                        defaults: {
                            width: 110
                        },
                        items: [{
                            text: 'Blocking',
                            value: '1'
                        },{
                            text: 'Non-blocking',
                            value: '0'
                        }],
                        listeners: {
                            change: function (comp, value) {
                                var formPanel = comp.up('form');
                                formPanel.updateRecord(['issync']);
                            }
                        }
                    },{
                        xtype: 'textfield',
                        fieldLabel: 'Run as',
                        labelWidth: 50,
                        name: 'run_as',
                        emptyText: 'root',
                        hidden: true,
                        flex: 1,
                        listeners: {
                            change: function (comp, value) {
                                var formPanel = comp.up('form');
                                formPanel.updateRecord(['run_as']);
                            }
                        }
                    }]
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'middle'
                    },
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Timeout',
                        name: 'timeout',
                        allowBlank: false,
                        validateOnChange: false,
                        regex: /^[0-9]+$/,
                        width: 160,
                        labelWidth: 110,
                        margin: '0 5 0 0',
                        listeners: {
                            change: function (comp, value) {
                                var formPanel = comp.up('form');
                                formPanel.updateRecord(['timeout']);
                            }
                        }
                    },{
                        xtype: 'label',
                        html: 'sec'
                    }]
                },{
                    xtype: 'textfield',
                    hideOn: 'x-empty-trigger-hide',
                    fieldLabel: 'Order',
                    name: 'order_index',
                    allowBlank: false,
                    validateOnChange: false,
                    regex: /^[0-9]+$/,
                    maxWidth: 160,
                    labelWidth: 110,
                    listeners: {
                        change: function (comp, value) {
                            var formPanel = comp.up('form');
                            formPanel.updateRecord(['order_index']);
                        }
                    }
                }]
            },{
                xtype: 'fieldset',
                title: 'Target<img src="/ui2/images/icons/info_icon_16x16.png" style="position:relative;top: 2px;left:8px">',
                hideOn: 'x-empty-action-hide',
                defaults: {
                    anchor: '100%',
                    maxWidth: 700,
                    labelWidth: 120
                },
                items: [{
                    xtype: 'fieldcontainer',
                    defaults: {
                        listeners: {
                            change: function(comp, checked) {
                                if (checked) {
                                    var formPanel = comp.up('form');
                                    formPanel.down('#targetRolesList').setDisabled(true);
                                    formPanel.down('#targetBehaviorsList').setDisabled(true);
                                    formPanel.updateRecord([{name: 'target', value: comp.inputValue}, 'target_roles']);
                                }
                            }
                        }
                    },
                    items: [{
                        xtype: 'radio',
                        name: 'target',
                        itemId: 'targetDoNotExecute',
                        inputValue: '',
                        boxLabel: 'No target (no execution)'
                    },{
                        xtype: 'radio',
                        name: 'target',
                        itemId: 'targetInstance',
                        inputValue: 'instance',
                        boxLabel: 'Triggering instance only'
                    },{
                        xtype: 'container',
                        itemId: 'targetRolesWrap',
                        layout: {
                            type: 'column'
                        },
                        items: [{
                            xtype: 'radio',
                            name: 'target',
                            itemId: 'targetRoles',
                            inputValue: 'roles',
                            boxLabel: 'Selected roles:',
                            width: 168,
                            listeners: {
                                change: function(comp, checked) {
                                    if (checked) {
                                        var formPanel = comp.up('form');
                                        formPanel.down('#targetRolesList').setDisabled(false);
                                        formPanel.down('#targetBehaviorsList').setDisabled(true);
                                        formPanel.updateRecord([{name: 'target', value: comp.inputValue}, 'target_roles']);
                                    }
                                }
                            }

                        },{
                            xtype: 'comboboxselect',
                            itemId: 'targetRolesList',
                            name: 'target_roles',
                            displayField: 'alias',
                            valueField: 'farm_role_id',
                            columnWidth: 1,
                            queryMode: 'local',
                            store: {
                                fields: ['farm_role_id', 'platform', 'cloud_location', 'role_id',  'name', 'alias'],
                                proxy: 'object'
                            },
                            flex: 1,
                            grow: false,
                            labelTpl: new Ext.XTemplate(
                                '{[this.getLabel(values)]}',
                                {
                                    getLabel: function(values) {
                                        return '<span style=\'color:#' + Scalr.utils.getColorById(values.farm_role_id)+'\'>' + values.alias + '</span> (' + values.cloud_location +')'
                                    }
                                }
                            ),
                            listConfig: {
                                tpl: new Ext.XTemplate(
                                    '<tpl for="."><div class="x-boundlist-item">{[this.getLabel(values)]}</div></tpl>',
                                    {
                                        getLabel: function(values) {
                                            return '<span style=\'color:#' + Scalr.utils.getColorById(values.farm_role_id)+'\'>' + values.alias + '</span> (' + values.cloud_location +')'
                                        }
                                    }
                                )
                            },
                            listeners: {
                                change: function(){
                                    this.up('form').updateRecord(['target', 'target_roles']);

                                }
                            }

                        }]
                    },{
                        xtype: 'container',
                        itemId: 'targetBehaviorsWrap',
                        margin: '6 0 0',
                        layout: {
                            type: 'column'
                        },
                        items: [{
                            xtype: 'radio',
                            name: 'target',
                            itemId: 'targetBehaviors',
                            inputValue: 'behaviors',
                            boxLabel: 'Roles with automation:',
                            width: 168,
                            listeners: {
                                change: function(comp, checked) {
                                    if (checked) {
                                        var formPanel = comp.up('form');
                                        formPanel.down('#targetRolesList').setDisabled(true);
                                        formPanel.down('#targetBehaviorsList').setDisabled(false);
                                        formPanel.updateRecord([{name: 'target', value: comp.inputValue}, 'target_behaviors']);
                                    }
                                }
                            }

                        },{
                            xtype: 'comboboxselect',
                            itemId: 'targetBehaviorsList',
                            name: 'target_behaviors',
                            displayField: 'name',
                            valueField: 'id',
                            columnWidth: 1,
                            queryMode: 'local',
                            grow: false,
                            store: {
                                fields: ['id', 'name'],
                                proxy: 'object'
                            },
                            flex: 1,
                            listeners: {
                                change: function(){
                                    this.up('form').updateRecord(['target', 'target_behaviors']);
                                }
                            }

                        }]
                    },{
                        xtype: 'radio',
                        name: 'target',
                        inputValue: 'role',
                        itemId: 'targetRole',
                        hidden: true,
                        boxLabel: 'All instances of this role',
                        margin: '6 0 0'
                    },{
                        xtype: 'radio',
                        name: 'target',
                        inputValue: 'farm',
                        itemId: 'targetFarm',
                        boxLabel: 'All instances in the farm',
                        margin: '6 0 0'
                    }]
                }]
            },{
                xtype: 'fieldset',
                title: 'Script parameters<img src="/ui2/images/icons/info_icon_16x16.png" style="position:relative;top: 2px;left:8px">',
                cls: 'x-fieldset-separator-none',
                itemId: 'scriptParamsWrapper',
                hidden: true,
                items: [{
                    xtype: 'container',
                    maxWidth: 700,
                    itemId: 'scripting_edit_parameters',
                    layout: 'anchor',
                    defaults: {
                        labelWidth: 120,
                        anchor: '100%'
                    }
                }]
            }],
            listeners: {
                boxready: function() {
                    this.grid = this.up('scriptfield2').down('grid');
                },
                beforeloadrecord: function(record) {
                    var form = this.getForm(), mode = this.up('scriptfield2').mode;
                    this.isLoading = true;
                    this.down('#scripting_edit_parameters').removeAll();
                    form.reset(true);
                    if (record.get('version') == 'latest') {
                        record.set('version', '-1');
                    }
                    
                    if (record.get('timeout') === '') {
                        record.set('timeout', '1200');
                    }

                    if (record.get('target') == 'role' && mode !== 'role') {
                        record.set('target', 'roles');
                        record.set('target_roles', [this.up('scriptfield2').farmRoleId]);
                    }
                },
                loadrecord: function(record) {
                    var isNewRecord = !record.store,
                        form = this.getForm(),
                        tabs = this.down('#tabs'),
                        readOnly = !!record.get('system');
                    tabs.setActiveTab(isNewRecord || record.get('script_id') ? 'scalrscript' : (record.get('script_path') ? 'localscript' : ''));
                    tabs.setDisabled(readOnly);
                    form.getFields().each(function(){
                        if (!this.isScriptParamField) {
                            this.setReadOnly(readOnly, this.editable);
                        }
                    });
                    if (isNewRecord || record.get('target') != 'roles') {
                        form.findField('target_roles').setValue([this.up('scriptfield2').farmRoleId]);
                    }
                    form.clearInvalid();
                    if (!this.isVisible()) {
                        this.setVisible(true);
                        this.ownerCt.updateLayout();//recalculate form dimensions after container size was changed, while form was hidden
                    }

                    var c = this.query('component[hideOn~=x-empty-trigger-hide], component[hideOn~=x-empty-action-hide]');
                    for (var i=0, len=c.length; i<len; i++) {
                        c[i].setVisible(!isNewRecord);
                    }

                    this.isLoading = false;
                }
            },

            updateRecordSuspended: 0,

            deselectRecord: function() {
                var form = this.getForm();
                this.setVisible(false);
                this.isLoading = true;
                this.down('#scripting_edit_parameters').removeAll();
                form.reset(true);
                this.isLoading = false;

            },

            removeScriptParams: function(){
                var record = this.getRecord();
                if (!this.isLoading && record) {
                    record.set('params', {});
                }
            },

            updateScriptParam: function(name, value) {
                var form = this.getForm(),
                    record = this.getRecord();
                if (this.isLoading) {// || this.updateRecordSuspended
                    return;
                }
                if (record) {
                    var versionField = form.findField('version'),
                        revisionRecord = versionField.findRecord('revision', versionField.getValue()),
                        fields = revisionRecord ? revisionRecord.get('fields') : null;
                    if (fields && fields[name]) {
                        var params = record.get('params');
                        params = Ext.isEmpty(params) ? {} : params;
                        if (!Ext.isEmpty(fields[name])) {
                            params[name] = value;
                        } else if (params[name]){
                            delete params[name];
                        }
                        record.set('params', params);
                    }

                }
            },

            updateRecord: function(fields) {
                var form = this.getForm(),
                    record = this.getRecord(),
                    values = {};

                if (this.isLoading || this.updateRecordSuspended || (record && record.get('system'))) {
                    return;
                }
                if (record && record.store === undefined) {
                    if (!form.hasInvalidField()) {
                        values = form.getValues();
                    }
                } else {
                    for (var i=0, len=fields.length; i<len; i++) {
                        if (Ext.typeOf(fields[i]) == 'object') {
                            values[fields[i].name] = fields[i].value;
                        } else {
                            var field = form.findField(fields[i]);
                            if (field.isValid()) {
                                values[fields[i]] = field[fields[i]=='target' ? 'getGroupValue' : 'getValue']();
                            }
                        }
                    }
                }
                
                if (values.target && values.target_roles) {
                    if (values.target == 'roles' && values.target_roles.length == 0) {
                        values.target = '';
                    }
                }
                if (values['script_path']) {
                    values['script_id'] = null;
                    values['script'] = null;
                    values['version'] = -1;
                    values['params'] = {};
                } else if (values['script_id']) {
                    values['script_path'] = null;
                }
                
                if (Ext.Object.getSize(values) && record) {
                    this.grid.disableOnFocusChange = true;
                    record.set(values);
                    if (record.store === undefined) {
                        this.grid.getStore().add(record);
                        this.grid.getSelectionModel().setLastFocused(record, true);
                    } else {
                        this.grid.store.sort('order_index', 'ASC');
                    }
                    this.grid.disableOnFocusChange = false;
                }

            }
        }
	}],

	setCurrentRole: function(role) {
		this.farmRoleId = role.get('farm_role_id');
		this.farmRoleId = Ext.isEmpty(this.farmRoleId) ? '*self*' : this.farmRoleId;
        this.down('form').down('[name="run_as"]').setVisible(role.get('os_family') !== 'windows');
	},

	loadRoleScripts: function(data) {
		this.down('grid').getStore().load({data: this.mode === 'role' ? Ext.Array.map(data, function(item){
            item['event'] = item['event_name'];
            item['script'] = item['script_name'];
            item['version'] = item['version'] + '';
            return item;
        }) : data});
	},

	clearRoleScripts: function(data) {
        var grid = this.down('grid');
        grid.getView().getSelectionModel().setLastFocused(null, true);
		grid.getStore().removeAll();
		this.down('form').deselectRecord();
		this.down('#scriptsLiveSearch').reset();
	},

	getRoleScripts: function(data) {
		var store = this.down('grid').getStore();
		return store.snapshot || store.data;
	},

	loadScripts: function(data) {
		this.down('#script').getStore().load({data: data});
	},

	loadEvents: function(data) {
		var events = {'*': 'All events'};
		Ext.apply(events, data);
		this.down('#event').getStore().load({data: events});
	},

	loadBehaviors: function(data) {
		this.down('#targetBehaviorsList').getStore().load({data: data});
	},

	loadRoles: function(data) {
		var roles = [];
		for (var i=0, len=data.length; i<len; i++) {
			if (!Ext.isEmpty(data[i].farm_role_id) || data[i].current) {
				data[i].farm_role_id = Ext.isEmpty(data[i].farm_role_id) ? '*self*' : data[i].farm_role_id;
				roles.push(data[i]);
			}
		}

		this.down('#targetRolesList').getStore().load({data: roles});
	},

	getNextOrderIndexForEvent: function(eventName) {
		var index = 10;
		this.getRoleScripts().each(function(){
			var curIndex = this.get('order_index');
			if (this.get('event') == eventName && curIndex >= index) {
				index = Math.floor(curIndex/10)*10 + 10;
			}
		});
		return index;
	}


});

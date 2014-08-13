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
                target = record.get('target'),
                system = record.get('system');
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
                    var roleIds = target == 'role' ? [this.grid.up('scriptfield').farmRoleId] : (record.get('target_roles') || []),
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
                rowBody: '<div style="cursor:pointer"><span title="Execution mode" style="float:left;width:52px;margin-right:7px;text-align:center;font-size:90%;line-height:95%;word-wrap:break-word">' + (record.get('isSync') == 1 ? 'blocking' : '<span style="color:green;position:relative;top:-5px;">non blocking</span>') + '</span><div '+(system ? 'title="'+system+' level script"' : '')+' style="margin:0 57px 5px">'+name+'</div></div>',
                rowBodyColspan: this.view.headerCt.getColumnCount(),
                rowBodyCls: record.get('system') ? 'x-grid-row-system' : ''
            };
        }
    },{
        ftype: 'rowwrap'
    }],
    store: {
        fields: ['script_type', 'script_id', 'script', 'os', 'event', 'target', 'target_roles', 'target_behaviors', 'isSync', 'timeout', 'version', 'params', {name: 'order_index', type: 'int'}, 'system', 'role_script_id', 'rule_id', 'hash', 'script_path', 'run_as', {name: 'groupField', convert: function(v, record) {return v || record.data.event;} } ],
        filterOnLoad: true,
        sortOnLoad: true,
        sorters: ['order_index'],
        proxy: 'object',
        groupField: 'groupField',
        loadEvents: function(events) {
            var me = this,
                index = 1;
            me.scriptingEvents = {};
            Ext.Object.each(events, function(key, value) {
                me.scriptingEvents[key] = index;
                index++;
            });
        },
        listeners: {
            add: function(store, records) {
                this.refreshGroupField(records);
            },
            update: function(store, record, operation, fields) {
                if (Ext.Array.contains(fields, 'event')) {
                    this.refreshGroupField([record]);
                }
            },
            beforeload: function(srore, operation) {
                var data = operation.data,
                    events = this.scriptingEvents;
                if (events && data) {
                    Ext.Array.each(data, function(v) {
                        v['groupField'] = events[v['event']] !== undefined ? events[v['event']] : v['event'];
                    });
                }
            }
        },
        refreshGroupField: function(records) {
            var events = this.scriptingEvents;
            if (events) {
                Ext.Array.each(records, function(record) {
                    if (events[record.get('event')] !== undefined) {
                        record.set('groupField', events[record.get('event')]);
                    }
                });
            }
        }
    },
    columns: [{
        flex: 1,
        dataIndex: 'order_index',
        renderer: function(val, meta, record, rowIndex, colIndex, store) {
            var script,
                system = record.get('system'),
                scriptType = record.get('script_type'),
                scope = '';
            switch (scriptType) {
                case 'scalr':
                    script = record.get('script') + '&nbsp;&nbsp;<img style="opacity:.6;position:relative;top:-2px" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-osfamily-small x-icon-osfamily-small-'+(record.get('os')=='windows'?'windows':'oel')+'" />';
                break;
                case 'local':
                    script = Ext.String.htmlEncode(record.get('script_path'));
                break;
                case 'chef':
                    script = 'Chef runlist';
                break;
            }
            if (record.dirty) {
                meta.tdCls += ' x-grid-dirty-cell';
            }
            if (system) {
                meta.tdAttr += ' title="'+system+' level script"';
                scope = '<img src="'+Ext.BLANK_IMAGE_URL+'" class="scalr-ui-variablefield-scope-'+system+'" style="width:10px;height:10px;vertical-align:top;position:relative;top:4px"/>&nbsp;';
            }
            return '<span style="float:left;width:46px;font-size:90%;">#'+record.get('order_index')+'</span> '+scope+'<b>'+script+'</b>';
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
    alias: 'widget.scriptfield',

    mode: 'farmrole',
    layout: {
        type: 'hbox',
        align: 'stretch'
    },
    initComponent: function() {
        this.callParent(arguments);
        this.down('#chef').add(Scalr.flags['betaMode'] ? {
            xtype: 'cheforchestration',
            itemId: 'chefSettings',
            relayEventsList: ['change'],
            disableItems: true,
            listeners: {
                fieldchange: function(field, newValue, oldValue) {
                    if (this.isValid()) {
                        var formPanel = this.up('form');
                        formPanel.updateRecord(['chef']);
                    }
                }
            }
        }:{
            xtype: 'label',
            text: 'Coming soon...'
        });
    },
    beforeRender: function() {
        if (this.mode === 'role') {
            this.down('#targetRolesWrap').hide();
            this.down('#targetBehaviorsWrap').hide();
            this.down('#targetRole').show();
        } else if (this.mode === 'account') {
            this.down('#targetRolesWrap').hide();
            this.down('#targetBehaviorsWrap').hide();
            this.down('#targetFarm').hide();
            this.down('#chef').tab.hide();
            //this.down('[name="run_as"]').show();
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
                me.form = me.up('scriptfield').down('form');
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
                    grid.form.loadRecord(grid.getStore().createModel({isSync: '1', order_index: 10}));
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
                title: 'Trigger event<img src="/ui2/images/icons/info_icon_16x16.png" style="position:relative;top: 2px;left:8px" data-qtip="A server lifetime event that will trigger automation processes.">',
                defaults: {
                    anchor: '100%',
                    maxWidth: 700,
                    labelWidth: 120
                },
                items: [
                    {
                        xtype: 'combo',
                        store: {
                            fields: [
                                'id',
                                {
                                    name: 'title',
                                    convert: function (v, record) {
                                        return record.get('id') == '*' ? 'All Events' : record.get('id');
                                    }
                                },
                                'name'
                            ],
                            proxy: 'object'
                        },
                        valueField: 'id',
                        displayField: 'title',
                        queryMode: 'local',
                        triggerAction: 'last',
                        lastQuery: '',
                        allowBlank: false,
                        validateOnChange: false,
                        itemId: 'event',
                        name: 'event',
                        emptyText: 'Please select trigger',
                        editable: true,
                        anyMatch: true,
                        autoSearch: false,
                        forceSelection: true,
                        hideInputOnReadOnly: true,
                        listConfig: {
                            cls: 'x-boundlist-role-scripting-events',
                            style: 'white-space:nowrap',
                            getInnerTpl: function (displayField) {
                                return '<tpl if=\'id == \"*\"\'>All Events<tpl else>{id} ' +
                                    '<tpl if="name">' +
                                    '<span style="color:#999">({name})</span>' +
                                    '</tpl>' +
                                    '</tpl>';
                            }
                        },
                        listeners: {
                            specialkey: function (field, e) {
                                if (e.getKey() === e.ESC) {
                                    field.reset();
                                }
                            },
                            afterrender: function (field) {
                                field.inputEl.on('click', function () {
                                    field.expand();
                                });
                            },
                            beforeselect: function(comp, rec, index) {
                                var formPanel = this.up('form'),
                                    record = formPanel.getRecord(),
                                    eventId = rec.get('id'),
                                    tabs;
                                if (Scalr.flags['betaMode']) {
                                    var isEventForbiddenForChef = Ext.Array.contains(['HostInit', 'HostDown', 'BeforeInstanceLaunch'], eventId);
                                    tabs = formPanel.down('#tabs');
                                    if (isEventForbiddenForChef && tabs.getComponent('chef').tab.isVisible()) {
                                        Scalr.message.InfoTip('Please note that Chef runlist can\'t be used with '+eventId+' event', comp.inputEl, {anchor: 'bottom'});
                                    }
                                    if (record.store === undefined) {
                                        if (tabs.activeTab.itemId !== 'chef') {
                                            tabs.getComponent('chef').setDisabled(isEventForbiddenForChef);
                                        } else if (isEventForbiddenForChef) {
                                            return false;
                                        }
                                    } else if (record.get('script_type') === 'chef' && isEventForbiddenForChef) {
                                        return false;
                                    }
                                }
                            },
                            change: function (comp, value) {
                                var formPanel = this.up('form'),
                                    form = formPanel.getForm(),
                                    record = formPanel.getRecord(),
                                    scriptRecord = comp.findRecordByValue(value),
                                    disableFields = function (fields) {
                                        for (var i = 0, len = fields.length; i < len; i++) {
                                            var field = formPanel.down(fields[i]);
                                            if (field.getValue()) {
                                                formPanel.down('#targetDoNotExecute').setValue(true);
                                            }
                                            field.disable();
                                        }
                                    };
                                formPanel.suspendLayouts();
                                if (scriptRecord) {
                                    if (value) {
                                        var c = formPanel.query('component[hideOn~=x-empty-trigger-hide]');
                                        for (var i = 0, len = c.length; i < len; i++) {
                                            c[i].setVisible(true);
                                        }
                                        if (record.store === undefined) {
                                            form.findField('order_index').setValue(comp.up('scriptfield').getNextOrderIndexForEvent(value));
                                        }
                                    }
                                    formPanel.savedScrollTop = formPanel.body.getScroll().top;
                                    formPanel.updateRecordSuspended++;
                                    switch (value) {
                                        case 'HostDown':
                                        case 'BeforeInstanceLaunch':
                                            disableFields(['#targetInstance']);
                                            break;
                                        default:
                                            formPanel.down('#targetRoles').enable();
                                            formPanel.down('#targetInstance').enable();
                                            break;
                                    }
                                    formPanel.body.scrollTo('top', formPanel.savedScrollTop);

                                    formPanel.updateRecordSuspended--;
                                    formPanel.updateRecord(['event', 'target', 'target_roles']);
                                    
                                }
                                comp.next().update(scriptRecord ? scriptRecord.get('name') : '');
                                formPanel.resumeLayouts(true);
                            }
                        }
                    }, {
                    xtype: 'container',
                    itemId: 'eventDescription',
                    style: 'color:#666;font-style:italic',
                    margin: '12 0 0'
                }]
            },{
                xtype: 'fieldset',
                title: 'Action<img src="/ui2/images/icons/info_icon_16x16.png" style="position:relative;top: 2px;left:8px" data-qtip="The specific automation that should be executed when the Trigger event is fired.  (For a Scalr or local script, ensure that the path to the interpreter has been defined in the script.)">',
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
                    minHeight: 100,
                    refreshTabsVisibility: function(isNewRecord) {
                        var me = this;
                        me.items.each(function(){
                            this.tab.setDisabled(!isNewRecord && me.activeTab !== this);
                        });
                    },
                    restoreTabsVisibility: function() {
                        var me = this;
                        me.items.each(function(){
                            this.tab.setDisabled(false);
                        });
                    },
                    defaults: {
                        listeners: {
                            activate: function(tab) {
                                var formPanel = this.up('form'),
                                    record = formPanel.getRecord(),
                                    field;
                                field = formPanel.down('#scriptParamsWrapper');
                                field.setVisible(tab.itemId === 'scalrscript' ? !!field.scriptHasParams : false);

                                var fields = tab.query('[isFormField]');
                                for (var i = 0, len = fields.length; i < len; i++) {
                                    fields[i].setDisabled(false);
                                }
                                if (Scalr.flags['betaMode'] && !record.get('system')) {
                                    field = formPanel.getForm().findField('isSync');
                                    if (tab.itemId === 'chef') {
                                        field.setReadOnly(true);
                                        field.setValue('1');
                                        if (record.store === undefined && tab.down('#chefSettings').isValid()) {
                                            formPanel.updateRecord(['chef']);
                                        }
                                    } else {
                                        field.setReadOnly(false);
                                    }

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
                                xtype: 'scriptselectfield',
                                itemId: 'script',
                                name: 'script_id',
                                flex: 1,
                                labelWidth: 50,
                                allowBlank: false,
                                validateOnChange: false,
                                store: {
                                    fields: [ 'id', 'name', 'description', 'os', 'isSync', 'timeout', 'versions', 'accountId', 'createdByEmail'  ],
                                    proxy: 'object'
                                },
                                triggerAction: 'last',
                                lastQuery: '',
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
                                            form.findField('os').setValue(scriptRecord.get('os'));
                                            var	versions = Scalr.utils.CloneObject(scriptRecord.get('versions'));

                                            versions.splice(0, 0, { version: -1, versionName: 'Latest', variables: versions[versions.length - 1]['variables'] });
                                            versionField.getStore().load({data: versions});

                                            versionField.setValue(-1);

                                            form.findField('target').setValue(scriptRecord.get('target'));
                                            form.findField('isSync').setValue(scriptRecord.get('isSync') || 0);
                                            form.findField('timeout').setValue(scriptRecord.get('timeout'));

                                            var order_index = form.findField('order_index').getValue();
                                            form.findField('order_index').setValue(order_index > 0 ? order_index : comp.up('scriptfield').getNextOrderIndexForEvent(form.findField('event').getValue()));
                                        }
                                        formPanel.updateRecordSuspended--;
                                        formPanel.updateRecord(['script_id', 'script', 'os', 'version', 'target', 'isSync', 'timeout', 'order_index']);
                                        formPanel.resumeLayouts(true);
                                    }
                                }
                            }, {
                                xtype: 'hiddenfield',
                                name: 'script'
                            }, {
                                xtype: 'hiddenfield',
                                name: 'os'
                            }, {
                                xtype: 'combo',
                                fieldLabel: 'Version',
                                disabled: true,
                                store: {
                                    fields: ['version', 'versionName', 'variables' ],
                                    proxy: 'object'
                                },
                                valueField: 'version',
                                displayField: 'versionName',
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
                                            var revisionRecord = this.findRecord('version', value),
                                                fields = revisionRecord ? revisionRecord.get('variables') : null;
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
                            icons: {
                                globalvars: true
                            },
                            emptyText: '/path/to/the/script',
                            labelWidth: 50,
                            margin: 0,
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
                        }
                    }]
                },{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'buttongroupfield',
                        fieldLabel: 'Execution mode',
                        editable: false,
                        name: 'isSync',
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
                                formPanel.updateRecord(['isSync']);
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
                itemId: 'targetsWrapper',
                title: 'Target<img src="/ui2/images/icons/info_icon_16x16.png" style="position:relative;top: 2px;left:8px" data-qtip="The exact servers upon which the above automation will be executed.">',
                hideOn: 'x-empty-action-hide',
                defaults: {
                    anchor: '100%',
                    maxWidth: 700,
                    labelWidth: 120
                },
                refreshTargets: function() {
                    var mode = this.up('scriptfield').mode,
                        scriptType = this.up('form').getRecord().get('script_type');
                    if (mode === 'farmrole') {
                        this.down('#targetRolesWrap').setVisible(scriptType !== 'chef');
                        this.down('#targetBehaviorsWrap').setVisible(scriptType !== 'chef');
                    }
                    if (mode !== 'account') {
                        this.down('#targetFarm').setVisible(scriptType !== 'chef');
                    }
                    if (mode === 'role') {
                        this.down('#targetRole').setVisible(scriptType !== 'chef');
                    }
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
                title: 'Script parameters<img src="/ui2/images/icons/info_icon_16x16.png" style="position:relative;top: 2px;left:8px" data-qwidth="300" data-qtip="This script requires certain parameters to be defined before it can be executed. Please enter in the specific values.">',
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
                    this.grid = this.up('scriptfield').down('grid');
                },
                beforeloadrecord: function(record) {
                    var form = this.getForm(), 
                        scriptField = this.up('scriptfield'),
                        os = scriptField.roleOs,
                        mode = scriptField.mode,
                        store;
                    this.isLoading = true;
                    this.down('#scripting_edit_parameters').removeAll();
                    form.findField('version').store.removeAll();
                    form.reset(true);

                    store = form.findField('script_id').store;

                    if (os) {
                        store.removeFilter('osFilter');
                        store.filter([{id: 'osFilter', property: 'os', value: os === 'windows' ? 'windows' : 'linux'}]);
                    } else {
                        store.removeFilter('osFilter');
                    }
                    
                    if (record.get('timeout') === '') {
                        record.set('timeout', '1200');
                    }

                    if (mode !== 'role' && mode !== 'account') {
                        this.down('#targetRole').setVisible(record.get('target') == 'role');
                    }
                },
                loadrecord: function(record) {
                    var isNewRecord = !record.store,
                        form = this.getForm(),
                        tabs = this.down('#tabs'),
                        system = record.get('system'),
                        readOnly = !!system,
                        scriptField = this.up('scriptfield'),
                        scriptType = record.get('script_type'),
                        chefSettingsField;
                    tabs.suspendLayouts();
                    tabs.enable();
                    tabs.restoreTabsVisibility();
                    tabs.setActiveTab(isNewRecord || scriptType === 'scalr' ? 'scalrscript' : (scriptType === 'local' ? 'localscript' : 'chef'));
                    tabs.refreshTabsVisibility(isNewRecord);
                    tabs.setDisabled(readOnly);
                    tabs.resumeLayouts(true);
                    form.getFields().each(function(){
                        if (scriptType === 'chef' && this.name === 'isSync' && !readOnly) {return};
                        if (!this.isScriptParamField) {
                            this.setReadOnly(readOnly, this.editable);
                        } else {
                            this.setReadOnly(readOnly ? readOnly && system === 'account' : readOnly, this.editable);
                        }
                    });
                    if (isNewRecord || record.get('target') != 'roles') {
                        form.findField('target_roles').setValue([this.up('scriptfield').farmRoleId]);
                    }
                    if (Scalr.flags['betaMode']) {
                        chefSettingsField = this.down('#chefSettings');
                        chefSettingsField.readOnly = readOnly;
                        chefSettingsField.chefSettings = scriptField.chefSettings || {};
                        chefSettingsField.setValue(isNewRecord ? scriptField.chefSettings : (record.get('params') || {}));
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
                    this.down('#targetsWrapper').refreshTargets();
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
                        revisionRecord = versionField.findRecord('version', versionField.getValue()),
                        fields = revisionRecord ? revisionRecord.get('variables') : null;
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
                    isNew = record && record.store === undefined,
                    errorFound,
                    values = {};

                if (this.isLoading || this.updateRecordSuspended || (record && record.get('system'))) {
                    return;
                }
                if (isNew) {
                    fields = ['script_path', 'script_id', 'version', 'script', 'os', 'run_as', 'order_index', 'event', 'isSync', 'timeout']
                    if (Scalr.flags['betaMode']) {
                        fields.push('chef');
                    }
                }
                for (var i=0, len=fields.length; i<len; i++) {
                    if (Ext.typeOf(fields[i]) == 'object') {
                        values[fields[i].name] = fields[i].value;
                    } else {
                        var field = fields[i] !== 'chef' ? form.findField(fields[i]) : this.down('#chefSettings');
                        if (field.isValid()) {
                            values[fields[i]] = field[fields[i]=='target' ? 'getGroupValue' : 'getValue']();
                        } else {
                            errorFound = true;
                        }
                    }
                }

                if (isNew && errorFound) {
                    return;
                }

                if (values.target && values.target_roles) {
                    if (values.target == 'roles' && values.target_roles.length == 0) {
                        values.target = '';
                    }
                }
                if (values['script_path']) {
                    values['script_type'] = 'local';
                    values['script_id'] = null;
                    values['script'] = null;
                    values['os'] = null;
                    values['version'] = -1;
                    values['params'] = {};
                } else if (values['script_id']) {
                    values['script_type'] = 'scalr';
                    values['script_path'] = null;
                    values['params'] = {};
                } else if (values['chef']) {
                    values['script_type'] = 'chef';
                    values['script_id'] = null;
                    values['script'] = null;
                    values['os'] = null;
                    values['version'] = -1;
                    values['script_path'] = null;
                    values['params'] = values['chef'];
                    values['isSync'] = '1';
                    delete values['chef'];
                }
                var isEventForbiddenForChef = Ext.Array.contains(['HostInit', 'HostDown', 'BeforeInstanceLaunch'], values['event']);
                if (values['script_type'] === 'chef' && isEventForbiddenForChef) {
                    Scalr.message.InfoTip(values['event'] + ' event is not available for Chef runlist', form.findField('event').inputEl, {anchor: 'bottom'});
                    return;
                }

                var refreshTargets = values['script_type'] != record.get('script_type');

                if (Ext.Object.getSize(values) && record) {
                    this.grid.disableOnFocusChange = true;
                    record.set(values);
                    if (record.store === undefined) {
                        this.grid.getStore().add(record);
                        this.grid.getSelectionModel().setLastFocused(record, true);
                        this.down('#tabs').refreshTabsVisibility(false);
                        this.down('#targetsWrapper').show();
                    } else {
                        this.grid.store.sort('order_index', 'ASC');
                    }
                    this.grid.disableOnFocusChange = false;
                }
                if (refreshTargets) {
                    this.down('#targetsWrapper').refreshTargets();
                }

            }
        }
    }],

    setCurrentRoleOptions: function(options) {
        options = options || {};
        if (this.mode === 'farmrole') {
            this.farmRoleId = Ext.isEmpty(options.farmRoleId) ? '*self*' : options.farmRoleId;
        }
        if (this.mode !== 'account') {
            this.roleOs = options.osFamily;
            this.down('form').down('[name="run_as"]').setVisible(options.osFamily !== 'windows');
            this.down('#chef').tab.setVisible(options.chefAvailable);
        }
    },

    loadRoleScripts: function(data) {
        this.down('grid').getStore().load({data: this.mode === 'role' || this.mode === 'account' ? Ext.Array.map(data, function(item){
            item['event'] = item['event_name'];
            item['script'] = item['script_name'];
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

    hasDirtyRecords: function() {
        var store = this.down('grid').getStore(),
            isDirty = store.getRemovedRecords().length > 0;
        if (!isDirty) {
            (store.snapshot || store.data).each(function(record){
                isDirty = record.dirty;
                return !isDirty;
            });
        }
        return isDirty;
    },

    loadScripts: function(data) {
        this.down('#script').getStore().load({data: data});
    },

    loadEvents: function(data) {
        var events = {'*': 'All events'};
        Ext.apply(events, data);
        this.down('#event').getStore().load({data: events});
        this.down('grid').getStore().loadEvents(events);
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

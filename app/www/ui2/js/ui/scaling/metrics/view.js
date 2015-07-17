Scalr.regPage('Scalr.ui.scaling.metrics.view', function () {

	var store = Ext.create('store.store', {

		fields: [
            'id',
            'envId',
            'clientId',
            'name',
            'filePath',
            'retrieveMethod',
            'calcFunction'
        ],

        proxy: {
            type: 'ajax',
            url: '/scaling/metrics/xListMetrics/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                successProperty: 'success'
            }
        },

        removeByMetricId: function (ids) {
            var me = this;

            me.remove(Ext.Array.map(
                ids, function (id) {
                    return me.getById(id);
                }
            ));

            if (me.getCount() === 0) {
                grid.getView().refresh();
            }

            return me;
        }
	});

	var grid = Ext.create('Ext.grid.Panel', {

        cls: 'x-panel-column-left',
        flex: 1,
        scrollable: true,

		store: store,

        plugins: [ 'applyparams', 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }],

        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No metrics found.',
                emptyTextNoItems: 'You have no metrics added yet.'
            },
            loadingText: 'Loading metrics ...',
            deferEmptyText: false
        },

        selModel: {
            selType: 'selectedmodel',
            getVisibility: function (record) {
                var envId = record.get('envId');
                return envId !== null;
            }
        },

        listeners: {
            selectionchange: function(selModel, selections) {
                this.down('toolbar').down('#delete').setDisabled(!selections.length);
            }
        },

        applyMetric: function (metric) {
            var me = this;

            var record = me.getSelectedRecord();
            var store = me.getStore();

            if (Ext.isEmpty(record)) {
                record = store.add(metric)[0];
            } else {
                record.set(metric);
                me.clearSelectedRecord();
            }

            me.setSelectedRecord(record);

            return me;
        },

        deleteMetric: function (id, name) {

            var isDeleteMultiple = Ext.typeOf(id) === 'array';

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Delete metric <b>' + name + '</b> ?'
                        : 'Delete selected metric(s): %s ?',
                    objects: isDeleteMultiple ? name : null
                },
                processBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Deleting <b>' + name + '</b> ...'
                        : 'Deleting selected metric(s) ...'
                },
                url: '/scaling/metrics/xRemove/',
                params: {
                    metrics: Ext.encode(
                        !isDeleteMultiple ? [id] : id
                    )
                },
                success: function (response) {
                    var deletedMetricsIds = response.processed;

                    if (!Ext.isEmpty(deletedMetricsIds)) {
                        store.removeByMetricId(deletedMetricsIds);
                    }
                }
            });
        },

        deleteSelectedMetric: function () {
            var me = this;

            var record = me.getSelectedRecord();

            me.deleteMetric(
                record.get('id'),
                record.get('name')
            );

            return me;
        },

        deleteSelectedMetrics: function () {
            var me = this;

            var ids = [];
            var names = [];

            Ext.Array.each(
                me.getSelectionModel().getSelection(),

                function (record) {
                    ids.push(record.get('id'));
                    names.push(record.get('name'));
                }
            );

            me.deleteMetric(ids, names);

            return me;
        },

		columns: [
			{ text: "ID", width: 60, dataIndex: 'id', sortable: true },
			{ text: 'Metric',

                flex: 1,
                dataIndex: 'name',
                sortable: true,
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getScope(values.envId)]}&nbsp;&nbsp;{name}', {
                        getScope: function (envId) {
                            var scope = envId !== null
                                ? 'environment'
                                : 'scalr';
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('metric') + '"/>';
                        }
                })
            },
			{ text: "File path", flex: 1, dataIndex: 'filePath', sortable: true },
			{ text: "Retrieve method", flex: 1, dataIndex: 'retrieveMethod', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="retrieveMethod == \'read\'">File-Read</tpl>' +
				'<tpl if="retrieveMethod == \'execute\'">File-Execute</tpl>'
			},
			{ text: "Calculation function", flex: 1, dataIndex: 'calcFunction', sortable: false, xtype: 'templatecolumn', tpl:
				'<tpl if="calcFunction == \'avg\'">Average</tpl>' +
				'<tpl if="calcFunction == \'sum\'">Sum</tpl>' +
				'<tpl if="calcFunction == \'max\'">Maximum</tpl>'
			}
		],

		dockedItems: [{
			xtype: 'toolbar',
			store: store,
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                store: store,
                filterFields: ['name'],
                margin: 0,
                listeners: {
                    afterfilter: function () {
                        grid.getView().refresh();
                    }
                }
            },/*

            TODO: Require refactoring model according SCALRCORE-1138 to add support for scalr, account scopes.

            {
                xtype: 'cyclealt',
                name: 'scope',
                getItemIconCls: false,
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
                        hidden: Scalr.scope !== 'environment'
                    }]
                }
            },*/{
                xtype: 'tbfill'
            },{
                text: 'New metric',
                itemId: 'add',
                cls: 'x-btn-green',
                enableToggle: true,
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();

                        form.down('#save').setText('Create');
                        form.down('#delete').hide();

                        form
                            .setHeader('New Metric')
                            .hideScopeInfo()
                            .setFieldsReadOnly(false)
                            .show()
                            .down('[name=name]').focus();

                        return;
                    }

                    form.hide();
                }
            },{
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.load();
                    grid.down('#add').toggle(false, true);
                }
            },{
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Select one or more metrics to delete them',
                handler: function () {
                    grid.deleteSelectedMetrics();
                }
            }]
		}]
	});

    var form = Ext.create('Ext.form.Panel', {
        hidden: true,
        autoScroll: true,

        fieldDefaults: {
            anchor: '100%'
        },

        scope: Scalr.scope,

        getScope: function () {
            return this.scope;
        },

        envIdToScope: function (envId) {
            return envId === null
                ? 'scalr'
                : 'environment';
        },

        setFieldsReadOnly: function (readOnly, scope) {
            var me = this;

            me.getForm().getFields().each(function (field) {
                field.setReadOnly(readOnly);
            });

            Ext.Array.each(
                me.getDockedComponent('buttons').query('#save, #delete'),
                function (button) {
                    button
                        .setTooltip(readOnly ? Scalr.utils.getForbiddenActionTip('metric', scope) : '')
                        .setDisabled(readOnly);
                }
            );

            return me;
        },

        saveMetric: function () {
            var me = this;

            var baseForm = me.getForm();
            var record = me.getRecord();

            if (baseForm.isValid()) {
                Scalr.Request({
                    processBox: {
                        type: 'save'
                    },
                    url: '/scaling/metrics/xSave',
                    form: baseForm,
                    params: record ? {
                        metricId: record.get('id')
                    } : null,
                    success: function (response) {

                        var metric = response.metric;

                        if (!Ext.isEmpty(metric)) {
                            grid.applyMetric(metric);
                            return true;
                        }

                        store.load();
                        grid.down('#add').toggle(false, true);
                    }
                });
            }

            return me;
        },

        setHeader: function (header) {
            var me = this;

            me.down('fieldset').setTitle(header);

            return me;
        },

        hideScopeInfo: function () {
            var me = this;

            me.down('#scopeInfo').hide();

            return me;
        },

        toggleScopeInfo: function (metricId, metricScope) {
            var me = this;

            var scopeInfoField = me.down('#scopeInfo');

            if (metricScope !== me.getScope()) {
                scopeInfoField.setValue(
                    Scalr.utils.getScopeInfo('metric', metricScope, metricId)
                );
                scopeInfoField.show();
                return me;
            }

            scopeInfoField.hide();
            return me;
        },

        listeners: {
            afterloadrecord: function (record) {
                var me = this;

                var scope = me.envIdToScope(record.get('envId'));
                var readOnly = scope !== me.getScope();

                grid.down('#add').toggle(false, true);

                me.down('#save').setText('Save');
                me.down('#delete').show();

                me
                    .setHeader('Edit Metric')
                    .toggleScopeInfo(
                        record.get('id'),
                        scope
                    )
                    .setFieldsReadOnly(readOnly, scope);
            }
        },

        items: [{
            xtype: 'displayfield',
            itemId: 'scopeInfo',
            cls: 'x-form-field-info x-form-field-info-fit',
            anchor: '100%',
            hidden: true
        }, {
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            title: 'Metric details',
            labelWidth: 120,
            defaults: {
                labelAlign: 'top'
            },
            items:[{
                xtype: 'textfield',
                name: 'name',
                fieldLabel: 'Name',
                vtype: 'alphanum',
                minLength: 5,
                allowBlank: false
            }, {
                xtype: 'textfield',
                name: 'filePath',
                fieldLabel: 'File path'
            }, {
                xtype: 'combo',
                name: 'retrieveMethod',
                fieldLabel: 'Retrieve method',
                editable: false,
                value: 'read',
                queryMode: 'local',
                store: [
                    ['read','File-Read'],
                    ['execute','File-Execute']
                ]
            }, {
                xtype: 'combo',
                name: 'calcFunction',
                fieldLabel: 'Calculation function',
                editable: false,
                value: 'avg',
                queryMode: 'local',
                store: [
                    ['avg','Average'],
                    ['sum','Sum'],
                    ['max','Maximum']
                ]
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
            maxWidth: 1000,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [{
                xtype: 'button',
                itemId: 'save',
                text: 'Save',
                handler: function () {
                    form.saveMetric();
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    grid.clearSelectedRecord();
                    grid.down('#add').toggle(false, true);
                }
            }, {
                xtype: 'button',
                itemId: 'delete',
                cls: 'x-btn-red',
                text: 'Delete',
                handler: function() {
                    grid.deleteSelectedMetric();
                }
            }]
        }]
    });

    return Ext.create('Ext.panel.Panel', {

        stateful: true,
        stateId: 'grid-scaling-metrics-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Custom scaling metrics',
            menuHref: '#/scaling/metrics',
            menuFavorite: true
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: .6,
            maxWidth: 800,
            minWidth: 400,
            layout: 'fit',
            items: [ form ]
        }]
    });
});

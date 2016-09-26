Ext.define('Scalr.ui.FarmRoleEditorTab.Scaling', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Scaling',
    itemId: 'scaling',

    layout: {
        type: 'hbox',
        align: 'stretch'
    },

    settings: {
        'scaling': undefined,
        'scaling.min_instances': 1,
        'scaling.max_instances': 2,
        'scaling.polling_interval': 1,
        'scaling.keep_oldest': 0,
        'scaling.ignore_full_hour': 0,
        'scaling.safe_shutdown': 0,
        'scaling.exclude_dbmsr_master': 0,
        'scaling.one_by_one': 0,
        'scaling.enabled': function(record) {
            return record.hasBehavior('rabbitmq') ? 0 : Scalr.getDefaultValue('AUTO_SCALING');
        } ,
        'scaling.upscale.timeout_enabled': undefined,
        'scaling.upscale.timeout': undefined,
        'scaling.downscale.timeout_enabled': undefined,
        'scaling.downscale.timeout': undefined,
        'scaling.downscale_only_if_all_metrics_true': undefined,
        'base.resume_strategy': undefined,
        'base.terminate_strategy': undefined
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && !record.get('behaviors').match('mongodb');
    },

    onRoleUpdate: function(record, name, value, oldValue) {
        if (this.suspendOnRoleUpdate > 0 || !this.isVisible()) {
            return;
        }

        var fullname = name.join('.'),
            comp;
        if (fullname === 'settings.scaling.min_instances') {
            comp = this.down('[name="scaling.min_instances"]');
            this.down('#scalinggrid').refreshEmptyText(value);
        } else if (fullname === 'settings.scaling.max_instances') {
            comp = this.down('[name="scaling.max_instances"]');
        }

        if (comp) {
            comp.suspendEvents(false);
            comp.setValue(value);
            comp.resumeEvents();
        }
    },

    isTabReadonly: function(record) {
        var behaviors = record.get('behaviors').split(','),
            isCfRole = Ext.Array.contains(behaviors, 'cf_cloud_controller') || Ext.Array.contains(behaviors, 'cf_health_manager'),
            isRabbitMqRole = Ext.Array.contains(behaviors, 'rabbitmq');

        return isCfRole || isRabbitMqRole;
    },
    showTab: function (record) {
        var me = this,
            settings = record.get('settings'),
            scaling = record.get('scaling'),
            metrics = me.up('#farmDesigner').moduleParams.tabParams['metrics'],
            readonly = me.isTabReadonly(record),
            platform = record.get('platform'),
            errors = record.get('errors', true) || {},
            grid,
            field, disableStrategy,
            isScalarized = record.get('isScalarized') == 1;

        me.suspendLayouts();

        if (record.get('behaviors').match("rabbitmq")) {
            settings['scaling.enabled'] = 0;
        }
        me.down('[name="scaling.enabled"]').setValue(settings['scaling.enabled'] == 1 ? '1' : '0').setReadOnly(readonly);
        me.down('#scalinggrid').setReadOnly(readonly);

        var isCfRole = (record.get('behaviors').match("cf_cloud_controller") || record.get('behaviors').match("cf_health_manager"));
        Ext.each(me.query('field'), function(item){
            item.setDisabled(readonly && (item.name != 'scaling.min_instances' || isCfRole || !record.get('new')));
        });

        me.down('[name="scaling.ignore_full_hour"]').setVisible(platform === 'ec2');

        disableStrategy = platform !== 'ec2' && platform !== 'gce' && platform !== 'cloudstack'  && !Scalr.isOpenstack(platform);
        field = me.down('[name="base.terminate_strategy"]');
        field.reset();
        field.setValue(disableStrategy ? 'terminate' : settings['base.terminate_strategy'] || 'terminate');
        field.updateIconTooltip('question', 'This setting is not supported by ' + Scalr.utils.getPlatformName(platform)+ ' cloud');
        field.setDisabled(disableStrategy);

        field = me.down('[name="base.consider_suspended"]');
        field.setValue(disableStrategy ? 'terminated' : settings['base.consider_suspended'] || 'running');
        field.updateIconTooltip('question', 'This setting is not supported by ' + Scalr.utils.getPlatformName(platform)+ ' cloud');
        field.toggleIcon('question', disableStrategy);
        if (disableStrategy) {
            field.setDisabled(disableStrategy);
        }

        //set values
        me.setFieldValues({
            'scaling.min_instances': settings['scaling.min_instances'] || '',
            'scaling.max_instances': settings['scaling.max_instances'] || '',
            'scaling.polling_interval': settings['scaling.polling_interval'] || '',
            'scaling.keep_oldest': settings['scaling.keep_oldest'] == 1,
            'scaling.ignore_full_hour': settings['scaling.ignore_full_hour'] == 1,
            'scaling.safe_shutdown': settings['scaling.safe_shutdown'] == 1,
            'scaling.exclude_dbmsr_master': settings['scaling.exclude_dbmsr_master'],
            'scaling.one_by_one': settings['scaling.one_by_one'] == 1,
            'scaling.upscale.timeout_enabled': settings['scaling.upscale.timeout_enabled'] == 1,
            'scaling.upscale.timeout': Ext.isEmpty(settings['scaling.upscale.timeout'], true) ? 10 : settings['scaling.upscale.timeout'],
            'scaling.downscale.timeout_enabled': settings['scaling.downscale.timeout_enabled'] == 1,
            'scaling.downscale.timeout': Ext.isEmpty(settings['scaling.downscale.timeout'], true) ? 10 : settings['scaling.downscale.timeout'],
            'scaling.downscale_only_if_all_metrics_true': settings['scaling.downscale_only_if_all_metrics_true'] == 1
        });
        me.down('[name="scaling.upscale.timeout"]').setDisabled(settings['scaling.upscale.timeout_enabled'] != 1);
        me.down('[name="scaling.downscale.timeout"]').setDisabled(settings['scaling.downscale.timeout_enabled'] != 1);

        me.down('[name="scaling.exclude_dbmsr_master"]').setVisible(record.isDbMsr(true));

        field = me.down('[name="scaling_algo"]');
        field.store.load({ data: metrics });
        field.setReadOnly(!isScalarized);

        me.down('[name="scaling.safe_shutdown"]').setReadOnly(!isScalarized);


        //load grid, select invalid record if any
        var dataToLoad = [], failedId = false, dateTimeMetricIsUsed = false;
        errors = errors['scaling'] || {};
        Ext.Object.each(scaling, function(id, settings){
            var metric = metrics[id],
                error = errors[id] || null;

            if (error && !failedId) {
                failedId = id;
            }

            dateTimeMetricIsUsed = dateTimeMetricIsUsed || id == 5;//DateTime metric

            dataToLoad.push({
                id: id,
                settings: settings,
                name: metric.name,
                alias: metric.alias,
                isInvert: metric.isInvert,
                validationErrors: error
            });
        });
        grid = me.down('grid');
        grid.store.loadData(dataToLoad);

        if (platform === 'ec2') {
            this.refreshIgnoreFullHour(dateTimeMetricIsUsed);
        }
        if (failedId) {
            grid.setSelectedRecord(grid.store.findRecord('id', failedId, 0, false, true, true));
        }

        me.down('#timezone').setText('Time zone: <span style="color:#666">' + me.up('#farmDesigner').down('#farmSettings #timezone').getValue() +
            '</span> <a href="#">Change</a>', false);

        me.resumeLayouts(true);
    },

    onScalingUpdate: function() {
        var record = this.currentRole,
            store = this.down('grid').getStore(),
            scaling = {};
        store.getUnfiltered().each(function(item){
            scaling[item.get('id')] = item.get('settings');
        });
        this.suspendOnRoleUpdate++;
        record.set('scaling', scaling);
        this.suspendOnRoleUpdate--;
        if (record.get('platform') === 'ec2') {
            this.refreshIgnoreFullHour(scaling[5]!==undefined);//DateTime metric
        }
    },

    refreshIgnoreFullHour: function(dateTimeMetricIsUsed) {
        var field = this.down('[name="scaling.ignore_full_hour"]');
        if (dateTimeMetricIsUsed) {
            if (!field.readOnly) {
                field.setValue(true);
                field.setReadOnly(true);
            }
        } else {
            field.setReadOnly(false);
        }
    },
    hideTab: function (record) {
        var settings = record.get('settings'),
            scaling = {},
            grid = this.down('grid'),
            store = grid.getStore(),
            customCleaned = 0,
            needToClean = function (alias, errors) {
                return errors && 'time' !== alias && (!customCleaned || 'custom' !== alias);
            },
            toCleanSelectors = [];

        grid.clearSelectedRecord();
        store.getUnfiltered().each(function(item){
            var alias = item.get('alias'),
                errors = item.get('validationErrors');

            if (needToClean(alias, errors)) {
                if ('custom' === alias) {
                    customCleaned++;
                }
                alias = '#' + alias + ' [name=';
                toCleanSelectors.push(alias + Ext.Object.getKeys(errors).join('],' + alias) + ']');
            }

            scaling[item.get('id')] = item.get('settings');
        });

        settings['scaling.enabled'] = this.down('[name="scaling.enabled"]').getValue();

        settings['base.terminate_strategy'] = this.down('[name="base.terminate_strategy"]').getValue();
        settings['base.consider_suspended'] = this.down('[name="base.consider_suspended"]').getValue();

        settings['scaling.min_instances'] = this.down('[name="scaling.min_instances"]').getValue();
        settings['scaling.max_instances'] = this.down('[name="scaling.max_instances"]').getValue();
        settings['scaling.polling_interval'] = this.down('[name="scaling.polling_interval"]').getValue();
        settings['scaling.keep_oldest'] = this.down('[name="scaling.keep_oldest"]').getValue() == true ? 1 : 0;
        settings['scaling.ignore_full_hour'] = this.down('[name="scaling.ignore_full_hour"]').getValue() == true ? 1 : 0;
        settings['scaling.safe_shutdown'] = this.down('[name="scaling.safe_shutdown"]').getValue() == true ? 1 : 0;
        settings['scaling.exclude_dbmsr_master'] = this.down('[name="scaling.exclude_dbmsr_master"]').getValue() == true ? 1 : 0;
        settings['scaling.one_by_one'] = this.down('[name="scaling.one_by_one"]').getValue() == true ? 1 : 0;
        settings['scaling.downscale_only_if_all_metrics_true'] = this.down('[name="scaling.downscale_only_if_all_metrics_true"]').getValue() == true ? 1 : 0;

        if (this.down('[name="scaling.upscale.timeout_enabled"]').getValue()) {
            settings['scaling.upscale.timeout_enabled'] = 1;
            settings['scaling.upscale.timeout'] = this.down('[name="scaling.upscale.timeout"]').getValue();
        } else {
            settings['scaling.upscale.timeout_enabled'] = 0;
            delete settings['scaling.upscale.timeout'];
        }

        if (this.down('[name="scaling.downscale.timeout_enabled"]').getValue()) {
            settings['scaling.downscale.timeout_enabled'] = 1;
            settings['scaling.downscale.timeout'] = this.down('[name="scaling.downscale.timeout"]').getValue();
        } else {
            settings['scaling.downscale.timeout_enabled'] = 0;
            delete settings['scaling.downscale.timeout'];
        }
        this.down('[name="scaling.enabled"]').reset();
        record.set({
            settings: settings,
            scaling: scaling
        });

        if (toCleanSelectors.length) {
            this.suspendEvents(false);
            Ext.Array.forEach(this.down('#scalingform').query(toCleanSelectors.join(',')), function(field) {
                field.isFormField && field.clearInvalid();
            });
            this.resumeEvents(true);
        }
    },

    //<staged 2016-02-05 &lt;s.honcharov@scalr.com&gt; for future refactoring>
    //clearErrors: function (settingName, metricId, fieldName) {
    //    var me = this, errors;
    //
    //    if (!me.currentRole || !(errors = me.currentRole.get('errors', true))) {
    //        return;
    //    }
    //
    //    var tabSettings = me.getSettingsList();
    //    if (tabSettings !== undefined) {
    //        Ext.Object.each(errors, function(name, error){
    //            if (!(name in tabSettings) || (settingName && settingName != name)) {
    //                return;
    //            }
    //
    //            if (name === 'scaling') {
    //                if (metricId && error[metricId]) {
    //                    if (fieldName) {
    //                        delete error[metricId][fieldName];
    //                        if (Ext.Object.isEmpty(error[metricId])) {
    //                            delete error[metricId];
    //                        }
    //                    } else {
    //                        delete error[metricId];
    //                    }
    //
    //                    if (Ext.Object.getSize(error) === 1) { // field `message`
    //                        delete errors[name];
    //                    }
    //                }
    //            } else {
    //                delete errors[name];
    //            }
    //        });
    //    }
    //    if (Ext.Object.getSize(errors) === 0) {
    //        me.currentRole.set('errors', null);
    //    }
    //},
    //</staged>

    getErrorTipConfig: function (metric, error) {
        if (error) {
            var size = Ext.Object.getSize(error);

            return {
                title: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-error"> '+ size +' validation error'+ (size > 1 ? 's' : '') +' in <em>'+ metric.name + '</em> metric:',
                msg: '- ' + Ext.Object.getValues(error).join('<br />- ') + '<br />'
            }
        }

        return null;
    },

    __items: [{
        xtype: 'container',
        maxWidth: 640,
        minWidth: 550,
        cls: 'x-panel-column-left-with-tabs',
        flex: .7,
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        items: [{
            xtype: 'toolbar',
            ui: 'simple',
            items: [{
                xtype: 'buttongroupfield',
                name: 'scaling.enabled',
                width: 210,
                defaults: {
                    width: 105
                },
                items: [{
                    text: 'Manual',
                    value: '0'
                },{
                    text: 'Automatic',
                    value: '1'
                }],
                listeners: {
                    change: function(comp, value) {
                        var tab = comp.up('#scaling'),
                            record = tab.currentRole,
                            settings = record.get('settings');

                        var leftcol = tab.down('#leftcol');
                        leftcol.down('grid').clearSelectedRecord();
                        leftcol.setVisible(value === '1');
                        tab.down('[name="scaling.min_instances"]').setVisible(value === '1');
                        tab.down('[name="scaling.max_instances"]').setVisible(value === '1');
                        tab.down('#scalingform').hide();
                        tab.down('#rightcol')[value === '1' ? 'removeCls' : 'addCls']('x-panel-column-left-with-tabs');

                        if (settings[comp.name] != value) {
                            settings[comp.name] = value;
                            tab.suspendOnRoleUpdate++;
                            record.set('settings', settings);
                            tab.suspendOnRoleUpdate--;
                        }

                    }
                }
            },{
                xtype: 'tbfill'
            },{
                xtype: 'textfield',
                fieldLabel: 'Min instances',
                labelWidth: 105,
                name: 'scaling.min_instances',
                width: 142,
                margin: 0,
                vtype: 'num',
                listeners: {
                    change: function(comp, value) {
                        var tab = comp.up('#scaling'),
                            record = tab.currentRole,
                            settings = record.get('settings');
                        settings[comp.name] = value;
                        tab.suspendOnRoleUpdate++;
                        record.set('settings', settings);
                        tab.suspendOnRoleUpdate--;
                        tab.down('#scalinggrid').refreshEmptyText(value);
                    }
                }
            },{
                xtype: 'textfield',
                fieldLabel: 'Max instances',
                labelWidth: 105,
                name: 'scaling.max_instances',
                width: 142,
                margin: '0 0 0 12',
                vtype: 'num',
                listeners: {
                    change: function(comp, value) {
                        var tab = comp.up('#scaling'),
                            record = tab.currentRole,
                            settings = record.get('settings');
                        settings[comp.name] = value;
                        tab.suspendOnRoleUpdate++;
                        record.set('settings', settings);
                        tab.suspendOnRoleUpdate--;
                    }
                }
            }]
        },{
            xtype: 'container',
            itemId: 'leftcol',
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            flex: 1,
            items: [{
                xtype: 'grid',
                itemId: 'scalinggrid',
                cls: 'x-fieldset-separator-bottom',
                multiSelect: true,
                enableColumnResize: false,
                padding: '0 12 12',
                features: {
                    ftype: 'addbutton',
                    text: 'Add scaling rule',
                    handler: function(view) {
                        var grid = view.up();
                        grid.clearSelectedRecord();
                        grid.form.loadRecord(grid.getStore().createModel({}));
                    }
                },
                plugins: [{
                    ptype: 'focusedrowpointer',
                    thresholdOffset: 26,
                    addOffset: 5
                },{
                    ptype: 'selectedrecord',
                    getForm: function() {
                        return this.grid.up('#scaling').down('form');
                    }
                },{
                    ptype: 'rowtooltip',
                    pluginId: 'rowtooltip',
                    cls: 'x-tip-form-invalid',
                    anchor: 'top',
                    minWidth: 330,
                    beforeShow: function (tooltip) {
                        var record = tooltip.owner.view.getRecord(tooltip.triggerElement), errors, cfg;

                        if (record && (errors = record.get('validationErrors'))) {
                            cfg = tooltip.owner.up('#scaling').getErrorTipConfig({name: record.get('name')}, errors);
                            tooltip.setTitle(cfg.title);
                            tooltip.update(cfg.msg);

                            return true;
                        }

                        return false;
                    }
                }],
                store: {
                    model: Scalr.getModel({fields: [{name: 'id', type: 'int'}, 'name', 'alias', 'min', 'max', 'settings', 'isInvert', 'validationErrors']})
                },
                columns: [{
                    text: 'Scale based on',
                    sortable: false,
                    dataIndex: 'name',
                    flex: 1.6
                },{
                    text: 'Scale up',
                    sortable: false,
                    dataIndex: 'max',
                    flex: 1,
                    xtype: 'templatecolumn',
                    tpl: [
                        '<tpl if="isInvert">',
                            '<tpl if="!(settings.min == null || settings.min === \'\')">',
                                '< {settings.min:htmlEncode}',
                            '</tpl>',
                        '<tpl elseif="!(settings.max == null || settings.max === \'\')">',
                            '> {settings.max:htmlEncode}',
                        '</tpl>'
                    ]
                },{
                    text: 'Scale down',
                    sortable: false,
                    dataIndex: 'min',
                    flex: 1,
                    xtype: 'templatecolumn',
                    tpl: [
                        '<tpl if="isInvert">',
                            '<tpl if="!(settings.max == null || settings.max === \'\')">',
                                '> {settings.max:htmlEncode}',
                            '</tpl>',
                        '<tpl elseif="!(settings.min == null || settings.min === \'\')">',
                            '< {settings.min:htmlEncode}',
                        '</tpl>'
                    ]
                }, {
                    xtype: 'templatecolumn',
                    tpl: '<img class="x-grid-icon x-grid-icon-delete" title="Delete scaling rule" src="'+Ext.BLANK_IMAGE_URL+'"/>',
                    width: 42,
                    sortable: false,
                    dataIndex: 'id',
                    align:'left'
                }],
                viewConfig: {
                    overflowY: 'auto',
                    overflowX: 'hidden',
                    getRowClass: function (record) {
                        return record.get('validationErrors') ? 'x-grid-row-color-red' : '';
                    }
                },
                refreshEmptyText: function(minInstances) {
                    var view = this.getView();
                    minInstances = Ext.isEmpty(minInstances) || !Ext.isNumeric(minInstances) ? '?' : minInstances;
                    view.emptyText = '<div class="' + this.emptyCls + '">No auto-scaling rules defined. Scalr will maintain ' + minInstances + ' running instance(s).</div>';
                    if (this.store.getCount() === 0) {
                        view.refresh();
                    }
                },
                listeners: {
                    viewready: function() {
                        var me = this,
                            tab = me.up('#scaling');
                        me.form = me.up('panel').up('container').down('form');
                        me.store.on({
                            add: {fn: tab.onScalingUpdate, scope: tab},
                            update: {fn: tab.onScalingUpdate, scope: tab},
                            remove: {fn: tab.onScalingUpdate, scope: tab}
                        });

                        me.store.on({
                            refresh: me.refreshAddButton,
                            update: me.refreshAddButton,
                            add: me.refreshAddButton,
                            remove: me.refreshAddButton,
                            scope: me
                        });
                        me.refreshAddButton();
                        me.refreshEmptyText(tab.currentRole.get('settings', true)['scaling.min_instances']);
                    },
                    itemclick: function (view, record, item, index, e) {
                        if (e.getTarget('img.x-grid-icon-delete')) {
                            view.store.remove(record);
                            return false;
                        }
                    }
                },
                refreshAddButton: function() {
                    var disableAddButton = false;
                    this.store.getUnfiltered().each(function(record){
                        disableAddButton = record.get('alias') === 'time';
                    });
                    this.view.findFeature('addbutton').setDisabled(disableAddButton, disableAddButton ? 'DateAndTime metric cannot be used with others' : '');
                },
                setReadOnly: function(readonly) {
                    this.getView().findFeature('addbutton').setDisabled(!!readonly);
                },

                clearFailed: function (id, name) {
                    var record;

                    if (id) {
                        record = this.store.findRecord('id', id, 0, false, true, true);
                        if (!record) {
                            return;
                        }

                        //<staged 2016-02-05 &lt;s.honcharov@scalr.com&gt; for future refactoring>
                        //var tab = this.up('#scaling');
                        //tab.clearErrors.call(tab, 'scaling', id, name);
                        //</staged>
                    }

                    function clearRecordError(record, name){
                        var errors = null;
                        if (name && (errors = record.get('validationErrors'))) {
                            delete errors[name];
                            if (Ext.Object.isEmpty(errors)) {
                                errors = null;
                            }
                        }
                        record.set('validationErrors', errors);
                    }

                    this.suspendLayouts();
                    if (record) {
                        clearRecordError(record, name);
                    } else {
                        this.store.getUnfiltered().each(function(record){
                            clearRecordError(record, name);
                        });
                    }
                    this.resumeLayouts();
                }
            }, {
                xtype: 'container',
                itemId: 'scalingsettings',
                flex: 1,
                overflowY: 'auto',
                preserveScrollPosition: true,
                items: [{
                    xtype: 'fieldset',
                    title: 'Scaling decision settings',
                    defaults: {
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        }
                    },
                    items: [{
                        items: [{
                            xtype: 'label',
                            text: 'Make scaling decisions every'
                        }, {
                            xtype: 'textfield',
                            name: 'scaling.polling_interval',
                            margin: '0 8',
                            vtype: 'num',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'minute(s)'
                        }]
                    }, {
                        items: [{
                            xtype: 'checkbox',
                            boxLabel: 'Limit scale up decisions to one per',
                            name: 'scaling.upscale.timeout_enabled',
                            handler: function (checkbox, checked) {
                                if (checked)
                                    this.next('[name="scaling.upscale.timeout"]').enable();
                                else
                                    this.next('[name="scaling.upscale.timeout"]').disable();
                            }
                        }, {
                            xtype: 'textfield',
                            name: 'scaling.upscale.timeout',
                            vtype: 'num',
                            margin: '0 8',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'minute(s)'
                        }]
                    }, {
                        items: [{
                            xtype: 'checkbox',
                            boxLabel: 'Limit scale down decisions to one per',
                            name: 'scaling.downscale.timeout_enabled',
                            handler: function (checkbox, checked) {
                                if (checked)
                                    this.next('[name="scaling.downscale.timeout"]').enable();
                                else
                                    this.next('[name="scaling.downscale.timeout"]').disable();
                            }
                        }, {
                            xtype: 'textfield',
                            name: 'scaling.downscale.timeout',
                            vtype: 'num',
                            margin: '0 8',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'minute(s)'
                        }]
                    }, {
                        xtype: 'checkbox',
                        name: 'scaling.one_by_one',
                        boxLabel: 'Wait until running state is reached before next decision to scale up'
                    }, {
                        xtype: 'checkbox',
                        name: 'scaling.exclude_dbmsr_master',
                        boxLabel: 'Exclude database master from scaling metric calculations'
                    },{
                        xtype: 'checkbox',
                        name: 'scaling.downscale_only_if_all_metrics_true',
                        boxLabel: 'Scale down only if all metrics return true'
                    }]
                },{
                    xtype: 'fieldset',
                    title: 'Termination preferences',
                    cls: 'x-fieldset-separator-none',
                    items: [{
                        xtype: 'combo',
                        store: [['terminate', 'Launch / Terminate'], ['suspend', 'Resume / Suspend']],
                        valueField: 'name',
                        displayField: 'description',
                        fieldLabel: 'Scaling behavior',
                        editable: false,
                        labelWidth: 210,
                        width: 390,
                        queryMode: 'local',
                        name: 'base.terminate_strategy',
                        plugins: {
                            ptype: 'fieldicons',
                            align: 'right',
                            position: 'outer',
                            icons: ['question']
                        },
                        listeners: {
                            disable: function() {
                                this.toggleIcon('question', true);
                            },
                            enable: function() {
                                this.toggleIcon('question', false);
                            },
                            change: function(comp, value) {
                                var comp2 = comp.next('[name="base.consider_suspended"]');
                                if (value === 'suspend') {
                                    comp2.setValue('terminated');
                                    comp2.setDisabled(true);
                                } else {
                                    comp2.setDisabled(false);
                                }
                            }
                        }
                    }, {
                        xtype: 'combo',
                        store: [['running', 'Running'], ['terminated', 'Terminated']],
                        valueField: 'name',
                        displayField: 'description',
                        fieldLabel: 'Consider suspended servers',
                        labelWidth: 210,
                        width: 390,
                        editable: false,
                        queryMode: 'local',
                        name: 'base.consider_suspended',
                        plugins: {
                            ptype: 'fieldicons',
                            align: 'right',
                            position: 'outer',
                            icons: ['question']
                        }
                    }, {
                        xtype: 'checkbox',
                        name: 'scaling.keep_oldest',
                        boxLabel: 'Scale down by shutting down newest servers first'
                    },{
                        xtype: 'checkbox',
                        name: 'scaling.ignore_full_hour',
                        boxLabel: 'Skip waiting full billing period when scaling down',
                        plugins: [{
                            ptype: 'fieldicons',
                            position: 'outer',
                            icons: [{id: 'question', tooltip: 'This setting is forced with DateTime scaling metric'}]
                        }],
                        listeners: {
                            writeablechange: function(comp, readOnly) {
                                this.toggleIcon('question', readOnly);
                            }
                        }
                    },{
                        xtype: 'checkbox',
                        name: 'scaling.safe_shutdown',
                        boxLabel: 'Enable safe shutdown when scaling down',
                        plugins: {
                            ptype: 'fieldicons',
                            icons: [{
                                id: 'info',
                                tooltip: 'Scalr will terminate an instance ONLY IF the script &#39;/usr/local/scalarizr/hooks/auth-shutdown&#39; returns 1. ' +
                                         'If this script is not found or returns any other value, Scalr WILL NOT terminate that server.'
                            }, {
                                id: 'question',
                                hidden: true,
                                tooltip: 'Safe shutdown is not available for agentless roles'
                            }]
                        },
                        listeners: {
                            writeablechange: function(comp, readOnly) {
                                this.toggleIcon('question', readOnly);
                                this.toggleIcon('info', !readOnly);
                            }
                        }
                    }]
                }]
            }]
        }]
    },{
        xtype: 'container',
        itemId: 'rightcol',
        flex: 1,
        layout: 'fit',
        items: {
            xtype: 'form',
            itemId: 'scalingform',
            hidden: true,
            overflowY: 'auto',
            items: [{
                xtype: 'fieldset',
                title: 'Scaling metric',
                items: [{
                    xtype: 'combo',
                    name: 'scaling_algo',
                    anchor: '100%',
                    maxWidth: 600,
                    editable: false,
                    emptyText: 'Please select scaling metric',
                    queryMode: 'local',
                    store: {
                        fields: [ {name: 'id', type: 'int'}, 'name', 'alias', 'scope', 'isInvert' ],
                        proxy: 'object'
                    },
                    valueField: 'id',
                    displayField: 'name',
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        icons: [{id: 'question', tooltip: 'Scalarizr automation is required to use other Scaling metrics'}]
                    }],
                    listConfig: {
                        getInnerTpl: function () {
                            return '<img src="' + Ext.BLANK_IMAGE_URL +
                                '" class="scalr-scope-{scope}" /><span style="padding-left: 6px; height: 26px">{name}</span>';
                        }
                    },
                    listeners: {
                        change: function(comp, value, oldValue) {
                            var formPanel = this.up('form'),
                                record = formPanel.getRecord(),
                                algos = formPanel.down('#algos'),
                                error, store;

                            if (value) {
                                var fieldRecord = comp.findRecordByValue(value),
                                    alias = fieldRecord.get('alias'),
                                    isInverted = fieldRecord.get('isInvert');

                                if (!formPanel.isRecordLoading && formPanel.grid) {
                                    store = formPanel.grid.store;
                                    error = false;

                                    if (store.findExact('id', value) !== -1) {
                                        error = 'This scaling metric already added.';
                                    } else if (
                                        !record.store && (store.findExact('alias', 'time') !== -1 || alias === 'time' && store.getCount() > 0) ||
                                            record.store && alias === 'time' && store.getCount() > 1
                                        ){
                                        error = 'DateAndTime metric cannot be used with others.';
                                    }

                                    if (error) {
                                        Scalr.message.InfoTip(error, comp.getEl());
                                        this.suspendEvents(false);
                                        this.setValue(oldValue);
                                        this.resumeEvents(false);

                                        return;
                                    }
                                }

                                if (alias === 'custom') {
                                    algos.down('#custom').swapFields(isInverted);
                                }

                                formPanel.updateRecordSuspended++;
                                algos.layout.setActiveItem(alias);
                                formPanel.showStat(alias);
                                formPanel.updateRecordSuspended--;
                                formPanel.updateRecord(null, null, isInverted);
                            } else if (algos.layout.activeItem) {
                                algos.layout.setActiveItem('blank');
                                formPanel.hideStat();
                            }
                        }
                    }
                }]
            },{
                xtype: 'container',
                layout: 'card',
                itemId: 'algos',
                activeItem: 'blank',

                validateHelper: {
                        max: {pair: '[name=min]', isMax: true},
                        min: {pair: '[name=max]'},
                        msgTpl: 'Scale up value must be {0} than Scale down value'
                },
                validateBounds: function(field) {
                    var helper = this.validateHelper,
                        hlp = helper[field.name],
                        isMax = !!hlp['isMax'],
                        pairField = field.up('fieldset').down(hlp['pair']),
                        value = field.isValid() && +field.getValue(),
                        pairValue = pairField.isValid() && +pairField.getValue();

                    if (value !== false && pairValue !== false) {
                        var record = this.up('form').getRecord(),
                            inverted = !!record.get('isInvert'),
                            err = record.get('validationErrors') || {},
                            msg;

                        if (value == pairValue || isMax == (value < pairValue)) {
                            msg = Ext.String.format(helper.msgTpl, inverted ? 'less' : 'greater');
                            err[inverted ? 'min' : 'max'] = msg;
                            (isMax !== inverted ? field : pairField).markInvalid(msg);

                        } else {
                            delete err[inverted ? 'min' : 'max'];
                            if (Ext.Object.isEmpty(err)) {
                                err = null;
                            }
                            (isMax !== inverted ? field : pairField).clearInvalid();
                        }
                        record.set('validationErrors', err);
                    }
                },
                defaults: {
                    listeners: {
                        beforeactivate: function() {
                            var me = this;
                            //default field values
                            if (me.defaultValues) {
                                Ext.Object.each(me.defaultValues, function(name, value){
                                    var field = me.down('[name="' + name + '"]'),
                                        fieldValue = field.getValue();
                                    if (Ext.isEmpty(fieldValue) || !fieldValue) {
                                        field.setValue(value);
                                    }
                                });
                            }
                        },
                        afterrender: function() {
                            var me = this;
                            if (me.defaultValues) {
                                var helper = me.up().validateHelper,
                                    onFieldChange = function(comp, value){
                                        me.up('form').updateRecord(comp.name, value);
                                    },
                                    onFieldBlur = me.up().validateBounds;

                                Ext.Object.each(me.defaultValues, function(name){
                                    var field = me.down('[name="' + name + '"]'),
                                        hlp;
                                    field.on('change', onFieldChange, field);

                                    if (!me.bypassBoundsValidation && (hlp = helper[name]) && hlp['pair']) {
                                        field.on('blur', onFieldBlur, me.up());
                                    }
                                });
                            }

                        }
                    }
                },
                items: [{
                    xtype: 'component',
                    itemId: 'blank'
                },{
                    xtype: 'fieldset',
                    itemId: 'la',
                    title: 'Downscaling and upscaling thresholds',
                    defaultValues: {
                        period: '15',
                        min: '2',
                        max: '5'
                    },
                    defaults: {
                        maxWidth: 330
                    },
                    items: [{
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            text: 'Use'
                        }, {
                            xtype: 'combo',
                            hideLabel: true,
                            store: ['1','5','15'],
                            allowBlank: false,
                            editable: false,
                            name: 'period',
                            queryMode: 'local',
                            margin: '0 8',
                            width: 60
                        }, {
                            xtype: 'label',
                            text: 'minute(s) load averages for scaling',
                            flex: 1
                        }]
                    }, {
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale up when LA goes over'
                        }, {
                            xtype: 'textfield',
                            name: 'max',
                            vtype: 'float',
                            allowBlank: false,
                            margin: '0 0 0 8',
                            width: 60
                        }]
                    }, {
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale down when LA goes under'
                        }, {
                            xtype: 'textfield',
                            name: 'min',
                            vtype: 'float',
                            allowBlank: false,
                            margin: '0 0 0 8',
                            width: 60
                        }]
                    }]
                },{
                    xtype: 'fieldset',
                    itemId: 'ram',
                    title: 'Downscaling and upscaling thresholds',
                    defaultValues: {
                        use_cached: false,
                        min: '',
                        max: ''
                    },
                    defaults: {
                        maxWidth: 380
                    },
                    items: [{
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale up when free RAM goes below'
                        }, {
                            xtype: 'textfield',
                            name: 'min',
                            vtype: 'float',
                            allowBlank: false,
                            margin: '0 8',
                            width: 60
                        }, {
                            xtype: 'label',
                            text: 'MB'
                        }]
                    }, {
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale down when free RAM goes above'
                        }, {
                            xtype: 'textfield',
                            name: 'max',
                            vtype: 'float',
                            allowBlank: false,
                            margin: '0 8',
                            width: 60
                        }, {
                            xtype: 'label',
                            text: 'MB'
                        }]
                    }, {
                        xtype: 'checkbox',
                        boxLabel: 'Use free+cached ram as scaling metric',
                        name: 'use_cached',
                        inputValue: '1'
                    }]
                },{
                    xtype: 'fieldset',
                    itemId: 'bw',
                    title: 'Downscaling and upscaling thresholds',
                    defaultValues: {
                        type: 'outbound',
                        min: '10',
                        max: '40'
                    },
                    defaults: {
                        maxWidth: 540
                    },
                    items: [{
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            text: 'Use'
                        }, {
                            xtype: 'combo',
                            hideLabel: true,
                            store: [ 'inbound', 'outbound' ],
                            allowBlank: false,
                            editable: false,
                            name: 'type',
                            queryMode: 'local',
                            margin: '0 8',
                            width: 120
                        }, {
                            xtype: 'label',
                            text: ' bandwidth usage value for scaling',
                            flex: 1
                        }]
                    }, {
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale up when average bandwidth usage on role is more than'
                        }, {
                            xtype: 'textfield',
                            name: 'max',
                            vtype: 'float',
                            allowBlank: false,
                            margin: '0 8',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'Mbit/s'
                        }]
                    }, {
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale down when average bandwidth usage on role is less than'
                        }, {
                            xtype: 'textfield',
                            name: 'min',
                            vtype: 'float',
                            allowBlank: false,
                            margin: '0 8',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'Mbit/s'
                        }]
                    }]
                },{
                    xtype: 'fieldset',
                    itemId: 'sqs',
                    title: 'Downscaling and upscaling thresholds',
                    bypassBoundsValidation: true,
                    defaultValues: {
                        queue_name: '',
                        min: '',
                        max: ''
                    },
                    defaults: {
                        maxWidth: 380,
                        anchor: '100%'
                    },
                    items: [{
                        fieldLabel: 'Queue name',
                        xtype: 'textfield',
                        name: 'queue_name',
                        allowBlank: false,
                        labelWidth: 100
                    }, {
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale up when queue size goes over'
                        }, {
                            xtype: 'textfield',
                            name: 'max',
                            vtype: 'num',
                            allowBlank: false,
                            margin:'0 8',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'items'
                        }]
                    }, {
                        xtype: 'container',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale down when queue size goes under'
                        }, {
                            xtype: 'textfield',
                            name: 'min',
                            vtype: 'num',
                            allowBlank: false,
                            margin: '0 8',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'items'
                        }]
                    }]
                },{
                    xtype: 'fieldset',
                    itemId: 'http',
                    title: 'Downscaling and upscaling thresholds',
                    defaultValues: {
                        url: '',
                        min: '1',
                        max: '5'
                    },
                    defaults: {
                        maxWidth: 430,
                        anchor: '100%'
                    },
                    items: [{
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale up when URL response time more than'
                        }, {
                            xtype: 'textfield',
                            name: 'max',
                            vtype: 'num',
                            allowBlank: false,
                            margin: '0 8',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'seconds'
                        }]
                    }, {
                        xtype: 'fieldcontainer',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            flex: 1,
                            text: 'Scale down when URL response time less than'
                        }, {
                            xtype: 'textfield',
                            name: 'min',
                            vtype: 'num',
                            allowBlank: false,
                            margin: '0 8',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'seconds'
                        }]
                    }, {
                        xtype: 'textfield',
                        fieldLabel: 'URL (with http(s)://)',
                        name: 'url',
                        maxWidth: 600,
                        labelWidth: 140,
                        vtype: 'url',
                        allowBlank: false
                    }]
                },{
                    xtype: 'fieldset',
                    itemId: 'custom',
                    title: 'Downscaling and upscaling thresholds',
                    defaultValues: {
                        min: '',
                        max: ''
                    },
                    defaults: {
                        maxWidth: 340
                    },

                    /**
                     * Current container's state
                     * false: `max` field is upper, label: `scale up when metric goes over`
                     *        `min` field is lower, label: `scale down when metric goes under`
                     * true:  `min` field is upper, label: `scale up when metric goes under`
                     *        `max` field is lower, label: `scale down when metric goes over`
                     */
                    inverted: false,
                    swapHelper: {
                        labelX: {0: 'over', 1: 'under'},
                        nameS: {min: 'max', max: 'min'}
                    },

                    /**
                     * Swap names between `max` and `min` fields, change their labels
                     * when custom metric is loading into form
                     *
                     * @param inverted {bool} loading metric is inverted
                     */
                    swapFields: function (inverted) {
                        if (inverted != this.inverted) {
                            var helper = this.swapHelper,
                                form = this.up('form');

                            form.suspendLayouts();
                            Ext.Array.forEach(this.query('#scaleUp, #scaleDown'), function (fieldset) {
                                var label = fieldset.down('label'),
                                    labelId = label.itemId,
                                    text = fieldset.down('textfield'),
                                    name = text.name;

                                label.setData({x: helper.labelX[ (labelId == 'maxLabel') == inverted ? 1 : 0 ]});
                                text.name = helper.nameS[name];
                            });
                            form.updateLayout();

                            this.inverted = inverted;
                        }

                        return this;
                    },

                    items: [{
                        xtype: 'fieldcontainer',
                        itemId: 'scaleUp',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            itemId: 'maxLabel',
                            flex: 1,
                            tpl: 'Scale up when metric value goes {x}',
                            data: {x: 'over'}
                        }, {
                            xtype: 'textfield',
                            name: 'max',
                            maskRe: /[0-9.]/,
                            margin: '0 0 0 8',
                            width: 40
                        }]
                    }, {
                        xtype: 'fieldcontainer',
                        itemId: 'scaleDown',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            itemId: 'minLabel',
                            flex: 1,
                            tpl: 'Scale down when metric value goes {x}',
                            data: {x: 'under'}
                        }, {
                            xtype: 'textfield',
                            name: 'min',
                            maskRe: /[0-9.]/,
                            margin: '0 0 0 8',
                            width: 40
                        }]
                    }]
                },{
                    xtype: 'fieldset',
                    itemId: 'time',
                    title: 'Schedule rules',
                    listeners: {
                        hide: function() {
                            this.down('grid').store.removeAll();
                        }
                    },
                    items: {
                        xtype: 'grid',
                        hideHeaders: true,
                        maxWidth: 600,
                        store: {
                            fields: [ 'start_time', 'end_time', 'week_days', 'instances_count', 'id' ],
                            proxy: 'object'
                        },
                        cls: 'x-grid-scaling-schedule-rules',
                        disableSelection: true,
                        trackMouseOver: false,
                        features: [{
                            ftype: 'addbutton',
                            text: 'Add schedule rule',
                            handler: function(view) {
                                Scalr.Confirm({
                                    form: {
                                        xtype: 'container',
                                        cls: 'x-container-fieldset',
                                        layout: 'anchor',
                                        defaults: {
                                            labelWidth: 130
                                        },
                                        items: [{
                                            xtype: 'timefield',
                                            fieldLabel: 'Start time',
                                            name: 'ts_s_time',
                                            anchor: '100%',
                                            minValue: '0:00am',
                                            maxValue: '23:55pm',
                                            allowBlank: false
                                        }, {
                                            xtype: 'timefield',
                                            fieldLabel: 'End time',
                                            name: 'ts_e_time',
                                            anchor: '100%',
                                            minValue: '0:00am',
                                            maxValue: '23:55pm',
                                            allowBlank: false
                                        }, {
                                            xtype: 'checkboxgroup',
                                            fieldLabel: 'Days of week',
                                            columns: 3,
                                            items: [
                                                { boxLabel: 'Sun', name: 'ts_dw_Sun', width: 50 },
                                                { boxLabel: 'Mon', name: 'ts_dw_Mon' },
                                                { boxLabel: 'Tue', name: 'ts_dw_Tue' },
                                                { boxLabel: 'Wed', name: 'ts_dw_Wed' },
                                                { boxLabel: 'Thu', name: 'ts_dw_Thu' },
                                                { boxLabel: 'Fri', name: 'ts_dw_Fri' },
                                                { boxLabel: 'Sat', name: 'ts_dw_Sat' }
                                            ]
                                        }, {
                                            xtype: 'numberfield',
                                            fieldLabel: 'Instances count',
                                            name: 'ts_instances_count',
                                            anchor: '100%',
                                            allowDecimals: false,
                                            minValue: 0,
                                            maxValue: 1000,
                                            allowBlank: false
                                        }]
                                    },
                                    ok: 'Add',
                                    title: 'Add schedule rule',
                                    formValidate: true,
                                    closeOnSuccess: true,
                                    scope: view,
                                    success: function (formValues) {
                                        var store = view.up('grid').store,
                                            week_days_list = '',
                                            i = 0, k;

                                        for (k in formValues) {
                                            if (k.indexOf('ts_dw_') != -1 && formValues[k] == 'on') {
                                                week_days_list += k.replace('ts_dw_','')+', ';
                                                i++;
                                            }
                                        }

                                        if (i == 0) {
                                            Scalr.message.Error('You should select at least one week day');
                                            return false;
                                        }
                                        else
                                            week_days_list = week_days_list.substr(0, week_days_list.length-2);

                                        var int_s_time = parseInt(formValues.ts_s_time.replace(/\D/g,''));
                                        var int_e_time = parseInt(formValues.ts_e_time.replace(/\D/g,''));

                                        if (formValues.ts_s_time.indexOf('AM') && int_s_time >= 1200)
                                            int_s_time = int_s_time-1200;

                                        if (formValues.ts_e_time.indexOf('AM') && int_e_time >= 1200)
                                            int_e_time = int_e_time-1200;

                                        if (formValues.ts_s_time.indexOf('PM') != -1)
                                            int_s_time = int_s_time+1200;

                                        if (formValues.ts_e_time.indexOf('PM') != -1)
                                            int_e_time = int_e_time+1200;

                                        if (int_e_time <= int_s_time) {
                                            Scalr.message.Error('End time value must be greater than Start time value');
                                            return false;
                                        }

                                        var record_id = int_s_time+':'+int_e_time+':'+week_days_list+':'+formValues.ts_instances_count;

                                        var recordData = {
                                            start_time: formValues.ts_s_time,
                                            end_time: formValues.ts_e_time,
                                            instances_count: formValues.ts_instances_count,
                                            week_days: week_days_list,
                                            id: record_id
                                        };

                                        var list_exists = false;
                                        var list_exists_overlap = false;
                                        var week_days_list_array = week_days_list.split(", ");

                                        store.each(function (item, index, length) {
                                            if (item.data.id == recordData.id) {
                                                Scalr.message.Error('Same record already exists');
                                                list_exists = true;
                                                return false;
                                            }

                                            var chunks = item.data.id.split(':');
                                            var s_time = chunks[0];
                                            var e_time = chunks[1];
                                            if (
                                                    (int_s_time >= s_time && int_s_time <= e_time) ||
                                                    (int_e_time >= s_time && int_e_time <= e_time) ||
                                                    (s_time >= int_s_time && s_time <= int_e_time) ||
                                                    (e_time >= int_s_time && e_time <= int_e_time)
                                                )
                                            {
                                                var week_days_list_array_item = (chunks[2]).split(", ");
                                                for (var ii = 0; ii < week_days_list_array_item.length; ii++)
                                                {
                                                    for (var kk = 0; kk < week_days_list_array.length; kk++)
                                                    {
                                                        if (week_days_list_array[kk] == week_days_list_array_item[ii] && week_days_list_array[kk] != '')
                                                        {
                                                            list_exists_overlap = "Period "+week_days_list+" "+formValues.ts_s_time+" - "+formValues.ts_e_time+" overlaps with period "+chunks[2]+" "+item.data.start_time+" - "+item.data.end_time;
                                                            return true;
                                                        }
                                                    }
                                                }
                                            }
                                        }, this);

                                        if (!list_exists && !list_exists_overlap) {
                                            store.add(recordData);
                                            return true;
                                        } else {
                                            Scalr.message.Error((!list_exists_overlap) ? 'Same record already exists' : list_exists_overlap);
                                            return false;
                                        }
                                    }
                                });
                            }
                        },{
                            ftype: 'rowbody',
                            getAdditionalData: function(data, rowIndex, record, orig) {
                                return {
                                    rowBody: '<div style="margin-top:-6px;width:100%;overflow:hidden;text-overflow:ellipsis"><span style="font-size:90%;margin-right:10px;width:90px;text-align:center;float:left">instance(s)</span><span style="white-space:nowrap">on <span class="x-semibold" data-qtip="'+record.get('week_days')+'">'+record.get('week_days')+'</span></span></div>',
                                    rowBodyColspan: this.view.headerCt.getColumnCount()
                                };

                            }
                        }],
                        viewConfig: {
                            //emptyText: 'No schedule rules defined',
                            //deferEmptyText: false,
                            focusedItemCls: '',
                            overItemCls: ''
                        },
                        columns: [{
                            xtype: 'templatecolumn',
                            flex: 1,
                            tpl: '<span class="x-semibold" style="text-align:center;width:90px;float:left;;margin-right:10px">{instances_count}</span> <span class="x-semibold">{start_time}</span> - <span class="x-semibold">{end_time}</span>'
                        }, {
                            xtype: 'templatecolumn',
                            tpl: '<img class="x-grid-icon x-grid-icon-delete" title="Delete schedule rule" src="'+Ext.BLANK_IMAGE_URL+'"/>',
                            width: 42,
                            sortable: false,
                            dataIndex: 'id',
                            align:'left'
                        }],
                        onDataChange: function() {
                            var me = this,
                                form = me.up('form'),
                                data = [], records = me.store.getRange();
                            for (var i = 0; i < records.length; i++)
                                data[data.length] = records[i].data;
                            form.updateRecord('settings', data);
                        },
                        listeners: {
                            viewready: function() {
                                var me = this;
                                me.store.on({
                                    add: {fn: me.onDataChange, scope: me},
                                    update: {fn: me.onDataChange, scope: me},
                                    remove: {fn: me.onDataChange, scope: me}
                                });
                            },
                            itemclick: function (view, record, item, index, e) {
                                if (e.getTarget('img.x-grid-icon-delete')) {
                                    view.store.remove(record);
                                    return false;
                                }
                            }
                        },
                        dockedItems: [{
                            xtype: 'toolbar',
                            ui: 'inline',
                            padding: '0 0 8',
                            dock: 'top',
                            items: [{
                                xtype: 'label',
                                itemId: 'timezone',
                                flex: 1,
                                listeners: {
                                    render: function() {
                                        var me = this;
                                        me.el.on('click', function(e){
                                            var el = me.el.query('a');
                                            if (el.length && e.within(el[0])) {
                                                me.up('#farmDesigner').showFarmSettings('general');
                                                e.preventDefault();
                                            }
                                        });
                                    }
                                }
                            }]
                        }]
                    }
                }]
            }, {
                xtype: 'fieldset',
                itemId: 'statpanel',
                title: 'Statistics',
                cls: 'x-fieldset-separator-none',
                hidden: true,
                items: [{
                    xtype: 'chartpreview',
                    itemId: 'chartPreview',
                    height: 250,
                    width: 442
                }]
                /*
                items: [{
                    xtype: 'label',
                    itemId: 'statstatus',
                    style: 'color:#666'
                },{
                    xtype: 'image',
                    itemId: 'stat',
                    farm: moduleTabParams['farmId'],
                    style: 'max-width:537px;cursor:pointer',
                    width: '100%',
                    listeners: {
                        afterrender: function(){
                            var me = this;
                            me.on('click',
                                function() {
                                    this.up('form').showStatPopup(this, this.farm, this.role, this.watcher)
                                },
                                me,
                                {element: 'el'}
                            );
                        }
                    }
                }]*/
            }],
            listeners: {
                afterrender: function() {
                    this.grid = this.up('#scaling').down('#leftcol grid');
                },
                beforeloadrecord: function(record) {
                    this.down('#algos #time grid').store.loadData({});
                },

                loadrecord: function(record) {
                    var form = this.getForm(),
                        id = record.get('id'),
                        alias = record.get('alias'),
                        settings = record.get('settings') || {},
                        errors = record.get('validationErrors') || {};

                    form.clearInvalid();
                    if (record.store) {
                        form.findField('scaling_algo').setValue(id);
                    }

                    if (alias) {
                        if (alias === 'time') {
                            this.down('#algos #'+alias+' grid').store.loadData(settings);
                        } else {
                            this.down('#algos #'+alias).setFieldValues(settings);

                            Ext.Object.each(errors, function (name, message) {
                                var cmp = this.down('#'+ alias + ' [name='+ name +']');
                                if (cmp && cmp.isFormField) {
                                    cmp.markInvalid(message);

                                    cmp.on('blur', function () {
                                        this.grid.clearFailed(id, name);
                                    }, this, {single: true});
                                }
                            }, this);

                        }
                    }
                    if (!this.isVisible()) {
                        this.setVisible(true);
                        this.ownerCt.updateLayout();//recalculate form dimensions after container size was changed, while form was hidden
                    }
                },

                afterloadrecord: function(record) {
                    var isScalarized = this.up('#scaling').currentRole.get('isScalarized') == 1,
                        field = this.getForm().findField('scaling_algo');
                    if (!record.store) {
                        if (!isScalarized) field.setValue(5);//DateTime metric
                    }
                    field.toggleIcon('question', !isScalarized);
                }
            },

            updateRecordSuspended: 0,

            updateRecord: function (fieldName, fieldValue, isInverted) {
                var record = this.getRecord();

                if (this.isRecordLoading || this.updateRecordSuspended || !record) {
                    return;
                }

                var data = {
                        settings: record.get('settings') || {}
                    };

                if (fieldName) {
                    if (fieldName == 'settings') {
                        data['settings'] = fieldValue;
                    } else {
                        data['settings'][fieldName] = fieldValue;
                    }
                } else {
                    var algoId = this.getForm().findField('scaling_algo').getValue(),
                        fieldsContainer = this.down('#algos').layout.getActiveItem(),
                        algoData = this.up('#farmDesigner').moduleParams.tabParams['metrics'][algoId];

                    data['id'] = algoId;
                    data['name'] = algoData.name;
                    data['alias'] = algoData.alias;
                    data['settings'] = fieldsContainer.getFieldValues();
                    data['isInvert'] = isInverted || false;
                }
                if (fieldName !== 'settings') {
                    data.min = data['settings'].min || undefined;
                    data.max = data['settings'].max || undefined;
                }

                this.grid.suspendLayouts();
                record.set(data);
                if (record.store === undefined) {
                    this.grid.getStore().add(record);
                    this.grid.setSelectedRecord(record);
                }
                this.grid.resumeLayouts(true);

            },

            hideStat: function() {
                this.down('#statpanel').hide();
            },

            showStat: function(metric) {
                //fixme extjs5

                var me = this;
                var roleRecord = me.up('#scaling').currentRole;
                var isRoleNew = roleRecord.get('new');
                var statPanel = me.down('#statpanel');

                var isMetricCorrect = function (metric) {
                    var metrics = ['mem', 'cpu', 'la', 'net', 'snum'];
                    return metrics.some(function (currentMetric) {
                        return currentMetric === metric;
                    });
                };

                if (!isRoleNew && isMetricCorrect(metric)) {
                    var tabParams = this.up('#farmDesigner').moduleParams.tabParams;
                    var hostUrl = tabParams['monitoringHostUrl'];
                    var farmId = tabParams['farmId'];
                    var farmRoleId = roleRecord.get('farm_role_id');
                    var farmHash = tabParams['farmHash'];
                    var period = 'daily';
                    var params = {farmId: farmId, farmRoleId: farmRoleId, hash: farmHash, period: period, metrics: metric};
                    var size = {height: 250, width: 442};
                    var chartPreview = me.down('#chartPreview');

                    var callback = function () {
                        //me.lcdDelayed = Ext.Function.defer(me.showStat, 6000, me);
                    };

                    statPanel.show();
                    chartPreview.loadStatistics(hostUrl, params, callback, size);
                } else {
                    statPanel.hide();
                }
            }
        }
    }]
});

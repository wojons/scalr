Scalr.regPage('Scalr.ui.farms.builder.tabs.scaling', function (moduleTabParams) {
    return Ext.create('Scalr.ui.FarmsBuilderTab', {
        tabTitle: 'Scaling',
        itemId: 'scaling',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        cls: 'scalr-ui-farmbuilder-roleedit-tab',

        settings: {
            'scaling.min_instances': 1,
            'scaling.max_instances': 2,
            'scaling.polling_interval': 1,
            'scaling.keep_oldest': 0,
            'scaling.ignore_full_hour': 0,
            'scaling.safe_shutdown': 0,
            'scaling.exclude_dbmsr_master': 0,
            'scaling.one_by_one': 0,
            'scaling.enabled': function(record) {return record.hasBehavior('rabbitmq') ? 0 : 1} ,
            'scaling.upscale.timeout_enabled': undefined,
            'scaling.upscale.timeout': undefined,
            'scaling.downscale.timeout_enabled': undefined,
            'scaling.downscale.timeout': undefined,
            'base.resume_strategy': undefined,
            'base.terminate_strategy': undefined
        },

        isEnabled: function (record) {
            return  record.get('platform') != 'rds' && !record.get('behaviors').match('mongodb');
        },

        onRoleUpdate: function(record, name, value, oldValue) {
            if (this.suspendOnRoleUpdate > 0 || !this.isVisible()) {
                return;
            }

            var fullname = name.join('.'),
                comp;
            if (fullname === 'settings.scaling.min_instances') {
                comp = this.down('[name="scaling.min_instances"]');
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
            var settings = record.get('settings'),
                scaling = record.get('scaling'),
                metrics = moduleTabParams['metrics'],
                readonly = this.isTabReadonly(record),
                platform = record.get('platform'),
                field, disableStrategy;
            this.suspendLayouts();

            if (record.get('behaviors').match("rabbitmq")) {
                settings['scaling.enabled'] = 0;
            }
            this.down('[name="scaling.enabled"]').setValue(settings['scaling.enabled'] == 1 ? '1' : '0').setReadOnly(readonly);
            this.down('#scalinggrid').setReadOnly(readonly);

            var isCfRole = (record.get('behaviors').match("cf_cloud_controller") || record.get('behaviors').match("cf_health_manager"));
            Ext.each(this.query('field'), function(item){
                item.setDisabled(readonly && (item.name != 'scaling.min_instances' || isCfRole || !record.get('new')));
            });

            this.down('#scaling_safe_shutdown_compositefield').setVisible(true);
            this.down('[name="scaling.ignore_full_hour"]').setVisible(platform === 'ec2');

            disableStrategy = platform !== 'ec2' && !Scalr.isOpenstack(platform);
            field = this.down('[name="base.terminate_strategy"]');
            field.setValue(disableStrategy ? 'terminate' : settings['base.terminate_strategy'] || 'terminate');
            field.updateIconTooltip('question', 'This setting is not supported by ' + Scalr.utils.getPlatformName(platform)+ ' cloud');
            field.setDisabled(disableStrategy);

			field = this.down('[name="base.consider_suspended"]');
            field.setValue(disableStrategy ? 'terminated' : settings['base.consider_suspended'] || 'running');
            field.updateIconTooltip('question', 'This setting is not supported by ' + Scalr.utils.getPlatformName(platform)+ ' cloud');
            field.toggleIcon('question', disableStrategy);
            field.setDisabled(disableStrategy);
            
            //set values
            this.setFieldValues({
                'scaling.min_instances': settings['scaling.min_instances'] || 1,
                'scaling.max_instances': settings['scaling.max_instances'] || 2,
                'scaling.polling_interval': settings['scaling.polling_interval'] || 1,
                'scaling.keep_oldest': settings['scaling.keep_oldest'] == 1,
                'scaling.ignore_full_hour': settings['scaling.ignore_full_hour'] == 1,
                'scaling.safe_shutdown': settings['scaling.safe_shutdown'] == 1,
                'scaling.exclude_dbmsr_master': settings['scaling.exclude_dbmsr_master'],
                'scaling.one_by_one': settings['scaling.one_by_one'] == 1,
                'scaling.upscale.timeout_enabled': settings['scaling.upscale.timeout_enabled'] == 1,
                'scaling.upscale.timeout': settings['scaling.upscale.timeout'] || 10,
                'scaling.downscale.timeout_enabled': settings['scaling.downscale.timeout_enabled'] == 1,
                'scaling.downscale.timeout': settings['scaling.downscale.timeout'] || 10
            });
            this.down('[name="scaling.upscale.timeout"]').setDisabled(settings['scaling.upscale.timeout_enabled'] != 1);
            this.down('[name="scaling.downscale.timeout"]').setDisabled(settings['scaling.downscale.timeout_enabled'] != 1);

            this.down('[name="scaling.exclude_dbmsr_master"]').setVisible(record.isDbMsr(true));

            this.down('[name="scaling_algo"]').store.load({ data: metrics });

            var dataToLoad = [];
            Ext.Object.each(scaling, function(id, settings){
                dataToLoad.push({
                    id: id,
                    settings: settings,
                    name: metrics[id].name,
                    alias: metrics[id].alias
                });
            })
            this.down('grid').store.loadData(dataToLoad);

            this.down('#timezone').setText('Time zone: <span style="color:#666">' + this.up('#fbcard').down('#farm').down('#timezone').getValue() +
                '</span> <a href="#">Change</a>', false);

            this.resumeLayouts(true);
        },

        onScalingUpdate: function() {
            var record = this.currentRole,
                store = this.down('grid').getStore(),
                scaling = {};
            (store.snapshot || store.data).each(function(item){
                scaling[item.get('id')] = item.get('settings');
            });
            this.suspendOnRoleUpdate++;
            record.set('scaling', scaling);
            this.suspendOnRoleUpdate--;
        },

        hideTab: function (record) {
            var settings = record.get('settings'),
                scaling = {},
                grid = this.down('grid'),
                store = grid.getStore(),
                selModel = grid.getSelectionModel();

            selModel.setLastFocused(null);
            selModel.deselectAll();
            (store.snapshot || store.data).each(function(item){
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
            record.set('settings', settings);
            record.set('scaling', scaling);
        },

        items: [{
            xtype: 'container',
            maxWidth: 600,
            minWidth: 490,
            cls: 'x-panel-column-left',
            flex: .7,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'container',
                margin: 12,
                layout: {
                    type: 'hbox',
                    align: 'stretch'
                },
                items: [{
                    xtype: 'buttongroupfield',
                    name: 'scaling.enabled',
                    width: 190,
                    defaults: {
                        width: 90
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
                            leftcol.down('grid').getSelectionModel().deselectAll();
                            leftcol.setVisible(value === '1');
                            tab.down('[name="scaling.min_instances"]').setVisible(value === '1');
                            tab.down('[name="scaling.max_instances"]').setVisible(value === '1');
                            tab.down('#scalingform').hide();
                            tab[value === '1' ? 'removeCls' : 'addCls']('x-panel-column-left');

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
                    labelWidth: 90,
                    name: 'scaling.min_instances',
                    width: 132,
                    margin: 0,
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
                },{
                    xtype: 'textfield',
                    fieldLabel: 'Max instances',
                    labelWidth: 90,
                    name: 'scaling.max_instances',
                    width: 132,
                    margin: '0 0 0 12',
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
                    cls: 'x-grid-shadow x-grid-role-scaling-rules x-fieldset-separator-bottom',
                    multiSelect: true,
                    enableColumnResize: false,
                    padding: '0 12 12',
                    features: {
                        ftype: 'addbutton',
                        text: 'Add scaling rule',
                        handler: function(view) {
                            var grid = view.up(),
                                selModel = grid.getSelectionModel();
                            selModel.setLastFocused(null);
                            selModel.deselectAll();
                            grid.form.loadRecord(grid.getStore().createModel({}));
                        }
                    },
                    plugins: [{
                        ptype: 'focusedrowpointer',
                        thresholdOffset: 26,
                        addOffset: 5
                    }],
                    store: {
                        fields: ['id', 'name', 'alias', 'min', 'max', 'settings'],
                        proxy: 'object'
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
                            '<tpl if="alias===\'ram\'">',
                                '<tpl if="settings.min">',
                                    '< {settings.min:htmlEncode}',
                                '</tpl>',
                            '<tpl else>',
                                '<tpl if="settings.max">',
                                    '> {settings.max:htmlEncode}',
                                '</tpl>',
                            '</tpl>'
                        ]
                    },{
                        text: 'Scale down',
                        sortable: false,
                        dataIndex: 'min',
                        flex: 1,
                        xtype: 'templatecolumn',
                        tpl: [
                            '<tpl if="alias===\'ram\'">',
                                '<tpl if="settings.max">',
                                    '> {settings.max:htmlEncode}',
                                '</tpl>',
                            '<tpl else>',
                                '<tpl if="settings.min">',
                                    '< {settings.min:htmlEncode}',
                                '</tpl>',
                            '</tpl>'
                        ]
                    }, {
                        xtype: 'templatecolumn',
                        tpl: '<img style="cursor:pointer" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Delete scaling rule" src="'+Ext.BLANK_IMAGE_URL+'"/>',
                        width: 42,
                        sortable: false,
                        dataIndex: 'id',
                        align:'left'
                    }],
                    viewConfig: {
                        overItemCls: '',
                        overflowY: 'auto',
                        overflowX: 'hidden'
                    },
                    listeners: {
                        viewready: function() {
                            var me = this,
                                tab = me.up('#scaling');
                            me.form = me.up('panel').up('container').down('form');
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
                            me.store.on({
                                add: {fn: tab.onScalingUpdate, scope: tab},
                                update: {fn: tab.onScalingUpdate, scope: tab},
                                remove: {fn: tab.onScalingUpdate, scope: tab}
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
                        }
                    },
                    setReadOnly: function(readonly) {
                        this.getView().findFeature('addbutton').setDisabled(!!readonly);
                    }
                }, {
                    xtype: 'fieldset',
                    itemId: 'scalingsettings',
                    flex: 1,
                    cls: 'x-fieldset-scaling-settings x-fieldset-separator-none',
                    overflowY: 'auto',
                    items: [{
                        xtype: 'component',
                        cls: 'x-fieldset-subheader',
                        html: 'Scaling decision frequency'
                    },{
                        xtype: 'container',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
                        items: [{
                            xtype: 'label',
                            text: 'Make scaling decisions every'
                        }, {
                            xtype: 'textfield',
                            name: 'scaling.polling_interval',
                            margin: '0 5',
                            width: 40
                        }, {
                            xtype: 'label',
                            text: 'minute(s)'
                        }]
                    }, {
                        xtype: 'container',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
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
                            margin: '0 5',
                            width: 40
                        }, {
                            xtype: 'label',
                            flex: 1,
                            text: 'minute(s)'
                        }]
                    }, {
                        xtype: 'container',
                        layout: {
                            type: 'hbox',
                            align: 'middle'
                        },
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
                            margin: '0 5',
                            width: 40
                        }, {
                            xtype: 'label',
                            flex: 1,
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
                        xtype: 'component',
                        cls: 'x-fieldset-subheader x-fieldset-separator-top',
                        html: 'Termination preferences'
                    }, {
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
                        icons: {
                            question: true
                        },
                        iconsPosition: 'outer',
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
                        icons: {
                            question: true
                        },
                        iconsPosition: 'outer'
        			}, {
                        xtype: 'checkbox',
                        name: 'scaling.keep_oldest',
                        boxLabel: 'Scale down by shutting down newest servers first'
                    },{
                        xtype: 'checkbox',
                        name: 'scaling.ignore_full_hour',
                        boxLabel: 'Skip waiting full billing period when scaling down'
                    },{
                        xtype: 'container',
                        layout: 'hbox',
                        itemId: 'scaling_safe_shutdown_compositefield',
                        items: [{
                            xtype: 'checkbox',
                            name: 'scaling.safe_shutdown',
                            boxLabel: 'Enable safe shutdown when scaling down'
                        }, {
                            xtype: 'displayinfofield',
                            margin: '0 0 0 5',
                            info:   'Scalr will terminate an instance ONLY IF the script &#39;/usr/local/scalarizr/hooks/auth-shutdown&#39; returns 1. ' +
                                'If this script is not found or returns any other value, Scalr WILL NOT terminate that server.'
                        }]
                    }]
                }]
            }]
        },{
            xtype: 'container',
            flex: 1,
            layout: 'fit',
            items: {
                xtype: 'form',
                itemId: 'scalingform',
                hidden: true,
                overflowY: 'auto',
                items: [{
                    xtype: 'fieldset',
                    title: 'Scaling metric<img src="/ui2/images/icons/info_icon_16x16.png" style="position:relative;top: 2px;left:8px">',
                    items: [{
                        xtype: 'combo',
                        name: 'scaling_algo',
                        anchor: '100%',
                        maxWidth: 600,
                        editable: false,
                        emptyText: 'Please select scaling metric',
                        queryMode: 'local',
                        store: {
                            fields: [ 'id', 'name', 'alias' ],
                            proxy: 'object'
                        },
                        valueField: 'id',
                        displayField: 'name',
                        listeners: {
                            change: function(comp, value, oldValue) {
                                var formPanel = this.up('form'),
                                    record = formPanel.getForm().getRecord(),
                                    algos = formPanel.down('#algos'),
                                    error;
                                if (value) {
                                    var alias = comp.findRecordByValue(value).get('alias');
                                    if (!formPanel.isLoading && formPanel.grid) {
                                        var forbidChange = false;
                                        if (formPanel.grid.store.find('id', value) !== -1) {
                                            error = 'This scaling metric already added.';
                                            forbidChange = true;
                                        } else if (
                                            !record.store && (formPanel.grid.store.find('alias', 'time') !== -1 || alias === 'time' && formPanel.grid.store.getCount() > 0) ||
                                                record.store && alias === 'time' && formPanel.grid.store.getCount() > 1
                                            ){
                                            error = 'DateAndTime metric cannot be used with others';
                                            forbidChange = true;
                                        }
                                        if (forbidChange) {
                                            Scalr.message.InfoTip(error, comp.getEl());
                                            this.suspendEvents(false);
                                            this.setValue(oldValue);
                                            this.resumeEvents(false);
                                            return;
                                        }
                                    }
                                    formPanel.updateRecordSuspended++;
                                    algos.layout.setActiveItem(alias);
                                    formPanel.showStat(alias);
                                    formPanel.updateRecordSuspended--;
                                    formPanel.updateRecord();
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
                                var me = this,
                                    formPanel = me.up('form'),
                                    onFieldChange = function(comp, value){
                                        formPanel.updateRecord(comp.name, value);
                                    };
                                if (me.defaultValues) {
                                    Ext.Object.each(me.defaultValues, function(name, value){
                                        var field = me.down('[name="' + name + '"]');
                                        field.on('change', onFieldChange, field);
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
                            maxWidth: 350
                        },
                        items: [{
                            xtype: 'container',
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
                                margin: '0 5',
                                width: 60
                            }, {
                                xtype: 'label',
                                text: 'minute(s) load averages for scaling'
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
                                text: 'Scale down when LA goes under'
                            }, {
                                xtype: 'textfield',
                                name: 'min',
                                maskRe: /[0-9.]/,
                                margin: '0 0 0 5',
                                width: 40
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
                                text: 'Scale up when LA goes over'
                            }, {
                                xtype: 'textfield',
                                name: 'max',
                                maskRe: /[0-9.]/,
                                margin: '0 0 0 5',
                                width: 40
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
                            maxWidth: 420
                        },
                        items: [{
                            xtype: 'container',
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
                                maskRe: /[0-9.]/,
                                margin: '0 5',
                                width: 40
                            }, {
                                xtype: 'label',
                                text: 'MB'
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
                                text: 'Scale down when free RAM goes above'
                            }, {
                                xtype: 'textfield',
                                name: 'max',
                                maskRe: /[0-9.]/,
                                margin: '0 5',
                                width: 40
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
                            maxWidth: 580
                        },
                        items: [{
                            xtype: 'container',
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
                                margin: '0 5',
                                width: 100
                            }, {
                                xtype: 'label',
                                text: ' bandwidth usage value for scaling'
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
                                text: 'Scale up when average bandwidth usage on role is less than'
                            }, {
                                xtype: 'textfield',
                                name: 'min',
                                maskRe: /[0-9.]/,
                                margin: '0 5',
                                width: 40
                            }, {
                                xtype: 'label',
                                text: 'Mbit/s'
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
                                text: 'Scale down when average bandwidth usage on role is more than'
                            }, {
                                xtype: 'textfield',
                                name: 'max',
                                maskRe: /[0-9.]/,
                                margin: '0 5',
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
                        defaultValues: {
                            queue_name: '',
                            min: '',
                            max: ''
                        },
                        defaults: {
                            maxWidth: 440
                        },
                        items: [{
                            fieldLabel: 'Queue name',
                            xtype: 'textfield',
                            name: 'queue_name',
                            labelWidth: 80,
                            width: 300
                        }, {
                            xtype: 'container',
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
                                maskRe: /[0-9.]/,
                                margin:'0 5',
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
                                maskRe: /[0-9.]/,
                                margin: '0 5',
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
                            maxWidth: 500,
                            anchor: '100%'
                        },
                        items: [{
                            xtype: 'container',
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
                                maskRe: /[0-9.]/,
                                margin: '0 5',
                                width: 40
                            }, {
                                xtype: 'label',
                                text: 'seconds'
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
                                text: 'Scale down when URL response time less than'
                            }, {
                                xtype: 'textfield',
                                name: 'min',
                                maskRe: /[0-9.]/,
                                margin: '0 5',
                                width: 40
                            }, {
                                xtype: 'label',
                                text: 'seconds'
                            }]
                        }, {
                            xtype: 'textfield',
                            fieldLabel: 'URL (with http(s)://)',
                            name: 'url',
                            labelWidth: 125
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
                            maxWidth: 410
                        },
                        items: [{
                            xtype: 'container',
                            layout: {
                                type: 'hbox',
                                align: 'middle'
                            },
                            items: [{
                                xtype: 'label',
                                flex: 1,
                                text: 'Scale up when metric value goes over'
                            }, {
                                xtype: 'textfield',
                                name: 'max',
                                maskRe: /[0-9.]/,
                                margin: '0 0 0 5',
                                width: 40
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
                                text: 'Scale down when metric value goes under'
                            }, {
                                xtype: 'textfield',
                                name: 'min',
                                maskRe: /[0-9.]/,
                                margin: '0 0 0 5',
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
                            cls: 'x-grid-shadow x-grid-scaling-schedule-rules x-grid-no-highlighting',
                            features: {
                                ftype: 'addbutton',
                                text: 'Add schedule rule',
                                handler: function(view) {
                                    Scalr.Confirm({
                                        form: {
                                            xtype: 'container',
                                            cls: 'x-container-fieldset',
                                            layout: 'anchor',
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
                                                        (int_e_time >= s_time && int_e_time <= e_time)
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
                            },
                            viewConfig: {
                                //emptyText: 'No schedule rules defined',
                                //deferEmptyText: false,
                                focusedItemCls: '',
                                overItemCls: ''
                            },
                            columns: [{
                                xtype: 'templatecolumn',
                                flex:1,
                                tpl: '<b>{instances_count}</b> instance(s), between <b>{start_time}</b> and <b>{end_time}</b> on <b>{week_days}</b>'
                            }, {
                                xtype: 'templatecolumn',
                                tpl: '<img style="cursor:pointer" width="15" height="15" class="x-icon-action x-icon-action-delete" title="Delete schedule rule" src="'+Ext.BLANK_IMAGE_URL+'"/>',
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
                                    if (e.getTarget('img.x-icon-action-delete')) {
                                        view.store.remove(record);
                                        return false;
                                    }
                                }
                            },
                            dockedItems: [{
                                xtype: 'toolbar',
                                ui: 'simple',
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
                                                    var builder = me.up('#fbcard');
                                                    builder.prev().deselectAll();
                                                    builder.layout.setActiveItem('farm');

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
                        this.grid = this.up('#scaling').down('#leftcol').down('grid');
                    },
                    beforeloadrecord: function(record) {
                        var form = this.getForm();
                        this.isLoading = true;
                        form.reset(true);
                        this.down('#algos').down('#time').down('grid').store.loadData({});
                    },

                    loadrecord: function(record) {
                        var form = this.getForm(),
                            alias = record.get('alias');

                        form.clearInvalid();
                        form.findField('scaling_algo').setValue(record.get('id'));

                        if (alias) {
                            if (alias === 'time') {
                                this.down('#algos').down('#'+record.get('alias')).down('grid').store.loadData(record.get('settings') || {});
                            } else {
                                this.down('#algos').down('#'+record.get('alias')).setFieldValues(record.get('settings') || {});
                            }
                        }
                        if (!this.isVisible()) {
                            this.setVisible(true);
                            this.ownerCt.updateLayout();//recalculate form dimensions after container size was changed, while form was hidden
                        }

                        this.isLoading = false;
                    }
                },

                updateRecordSuspended: 0,

                updateRecord: function(fieldName, fieldValue) {
                    var record = this.getRecord();

                    if (this.isLoading || this.updateRecordSuspended || !record) {
                        return;
                    }

                    var form = this.getForm(),
                        data = {
                            settings: record.get('settings') || {}
                        },
                        algoId = form.findField('scaling_algo').getValue(),
                        fieldsContainer = this.down('#algos').layout.getActiveItem();

                    if (fieldName) {
                        if (fieldName == 'settings') {
                            data['settings'] = fieldValue;
                        } else {
                            data['settings'][fieldName] = fieldValue;
                        }
                    } else {
                        var algoData = moduleTabParams['metrics'][algoId];
                        data['id'] = algoId;
                        data['name'] = algoData.name;
                        data['alias'] = algoData.alias;
                        data['settings'] = fieldsContainer.getFieldValues();
                    }
                    if (fieldName !== 'settings') {
                        data.min = data['settings'].min || undefined;
                        data.max = data['settings'].max || undefined;
                    }

                    this.grid.suspendLayouts();
                    record.set(data);
                    if (record.store === undefined) {
                        this.grid.getStore().add(record);
                        this.grid.getSelectionModel().setLastFocused(record);
                    }
                    this.grid.resumeLayouts(true);

                },

                deselectRecord: function() {
                    var form = this.getForm();
                    this.setVisible(false);
                    this.isLoading = true;
                    form.reset(true);
                    delete form._record;
                    this.isLoading = false;

                },

                hideStat: function() {
                    this.down('#statpanel').hide();
                },

                showStat: function(metric) {
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
                        var hostUrl = moduleTabParams['monitoringHostUrl'];
                        var farmId = moduleTabParams['farmId'];
                        var farmRoleId = roleRecord.get('farm_role_id');
                        var farmHash = moduleTabParams['farmHash'];
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
});

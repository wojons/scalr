Scalr.regPage('Scalr.ui.schedulertasks.view', function (loadParams, moduleParams) {

    var store = Ext.create('Scalr.ui.ContinuousStore', {

        fields: [
            'id',
            'name',
            'type',
            'comments',
            'targetName',
            'targetType',
            'startTime',
            'config',
            'lastStartTime',
            'timezone',
            'restartEvery',
            'status',
            'targetFarmId',
            'targetFarmName',
            'targetRoleId',
            'targetRoleName',
            'targetId'
        ],

        proxy: {
            type: 'ajax',
            url: '/schedulertasks/xList/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                successProperty: 'success'
            }
        },

        listeners: {
            beforeload: function () {
                grid.down('#add').toggle(false, true);
            },
            filterchange: function () {
                grid.down('#add').toggle(false, true);
            }
        },

        removeByTaskId: function (ids) {
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

        plugins: [ 'applyparams', 'focusedrowpointer', 'continuousrenderer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true
        }],

        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No tasks found.',
                emptyTextNoItems: 'You have no tasks added yet.'
            },
            loadingText: 'Loading tasks ...',
            deferEmptyText: false
        },

        selModel: Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage') ? 'selectedmodel' : null,

        listeners: {
            selectionchange: function(selModel, selections) {
                var toolbar = this.down('toolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
                toolbar.down('#activate').setDisabled(!selections.length);
                toolbar.down('#suspend').setDisabled(!selections.length);
                toolbar.down('#execute').setDisabled(!selections.length);
            }
        },

        applyTask: function (task) {
            var me = this;

            var record = me.getSelectedRecord();
            var store = me.getStore();

            if (Ext.isEmpty(record)) {
                record = store.add(task)[0];
            } else {
                record.set(task);
                me.clearSelectedRecord();
            }

            me.setSelectedRecord(record);

            return me;
        },

        deleteTask: function (id, name) {
            var isDeleteMultiple = Ext.typeOf(id) === 'array';

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Delete task <b>' + name + '</b> ?'
                        : 'Delete selected task(s): %s ?',
                    objects: isDeleteMultiple ? name : null
                },
                processBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Deleting <b>' + name + '</b> ...'
                        : 'Deleting selected task(s) ...'
                },
                url: '/schedulertasks/xDelete',
                params: {
                    tasks: Ext.encode(
                        !isDeleteMultiple ? [id] : id
                    )
                },
                success: function (response) {
                    var deletedTasksIds = response.processed;

                    if (Ext.isArray(deletedTasksIds)) {
                        store.removeByTaskId(deletedTasksIds);
                    }
                }
            });
        },

        deleteSelectedTask: function () {
            var me = this;

            var record = me.getSelectedRecord();

            me.deleteTask(
                record.get('id'),
                record.get('name')
            );

            return me;
        },

        getSelectedTasksParams: function () {
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

            return {
                ids: ids,
                names: names
            };
        },

        deleteSelectedTasks: function () {
            var me = this;

            var params = me.getSelectedTasksParams();

            me.deleteTask(params.ids, params.names);

            return me;
        },

        afterActionComplete: function (tasksIds, status) {
            var me = this;

            var store = me.getStore();
            var records = [];

            if (Ext.isString(status)) {
                Ext.Array.each(tasksIds, function (taskId) {
                    var record = store.getById(taskId);
                    record.set('status', status);

                    records.push(record);
                });
            } else {
                records = Ext.Array.map(tasksIds, function (taskId) {
                    return store.getById(taskId);
                });
            }

            me.getSelectionModel().deselect(records);

            return me;
        },

        activateSelectedTasks: function () {
            var me = this;

            var params = me.getSelectedTasksParams();

            Scalr.Request({
                confirmBox: {
                    type: 'action',
                    msg: 'Activate selected task(s): %s ?',
                    ok: 'Activate',
                    objects: params.names
                },
                processBox: {
                    type: 'action'
                },
                params: {
                    tasksIds: Ext.encode(params.ids)
                },
                url: '/schedulertasks/xActivate/',
                success: function (response) {
                    if (Ext.isArray(response.processed)) {
                        me.afterActionComplete(response.processed, 'Active');
                    }
                }
            });

            return me;
        },

        suspendSelectedTasks: function () {
            var me = this;

            var params = me.getSelectedTasksParams();

            Scalr.Request({
                confirmBox: {
                    type: 'action',
                    msg: 'Suspend selected task(s): %s ?',
                    ok: 'Suspend',
                    objects: params.names
                },
                processBox: {
                    type: 'action'
                },
                params: {
                    tasksIds: Ext.encode(params.ids)
                },
                url: '/schedulertasks/xSuspend/',
                success: function (response) {
                    if (Ext.isArray(response.processed)) {
                        me.afterActionComplete(response.processed, 'Suspended');
                    }
                }
            });

            return me;
        },

        executeSelectedTasks: function () {
            var me = this;

            var params = me.getSelectedTasksParams();

            Scalr.Request({
                confirmBox: {
                    type: 'action',
                    msg: 'Execute selected task(s): %s ?',
                    ok: 'Execute',
                    objects: params.names
                },
                processBox: {
                    type: 'action'
                },
                params: {
                    tasksIds: Ext.encode(params.ids)
                },
                url: '/schedulertasks/xExecute/',
                success: function (response) {
                    if (Ext.isArray(response.processed)) {
                        me.afterActionComplete(response.processed);
                    }
                }
            });

            return me;
        },

        columns: [
            { text: 'ID', width: 80, dataIndex: 'id', sortable: true },
            { text: 'Task', flex: 1, dataIndex: 'name', sortable: true },
            { text: 'Type', width: 70, dataIndex: 'type', sortable: true, xtype: 'templatecolumn', align: 'center', tpl: [
                '<img ',
                    'style="cursor: default" class="x-grid-icon x-grid-icon-',
                        '<tpl if="type == &quot;script_exec&quot;">executescript</tpl>',
                        '<tpl if="type == &quot;fire_event&quot;">fireevent</tpl>',
                        '<tpl if="type == &quot;launch_farm&quot;">launchfarm</tpl>',
                        '<tpl if="type == &quot;terminate_farm&quot;">terminatefarm</tpl>',
                    '" ',
                    'data-qtip=\'',
                        '<tpl if="type == &quot;script_exec&quot;">Execute script: <a href="#/scripts/{config.scriptId}/view?version={config.scriptVersion}">{config.scriptName}</a> (<tpl if="config.scriptVersion == -1">latest<tpl else>{config.scriptVersion}</tpl>)</tpl>',
                        '<tpl if="type == &quot;fire_event&quot;">Fire event: {config.eventName}</tpl>',
                        '<tpl if="type == &quot;launch_farm&quot;">Launch farm</tpl>',
                        '<tpl if="type == &quot;terminate_farm&quot;">Terminate farm</tpl>',
                    '\' ',
                    'src="' + Ext.BLANK_IMAGE_URL +
                '"/>'
            ]},
            { text: 'Start date', flex: 0.5, maxWidth: 165, dataIndex: 'startTime', sortable: true },
            { text: 'Restart every', width: 120, dataIndex: 'restartEvery', sortable: false, xtype: 'templatecolumn', tpl: new Ext.XTemplate(
                '<tpl if="restartEvery == 0">Never</tpl>' +
                '<tpl if="restartEvery != 0">{[this.convertTime(values.restartEvery)]}</tpl>', {
                    convertTime: function (time) {
                        if (time > 60) {
                            var d1 = Math.ceil(time/60), d2 = Math.floor(time/60);
                            if (d1 == d2) {
                                time = time/60;
                                if (time > 24) {
                                    d1 = Math.ceil(time/24), d2 = Math.floor(time/24);
                                    if (d1 == d2) {
                                        time = time/24;
                                        return time + " days";
                                    }
                                } else {
                                    return time + " hours";
                                }
                                time = time * 60;
                            }
                        }
                        return time + " minutes";
                    }
                })
            },
            { xtype: 'templatecolumn', text: 'Last time executed', flex: 0.5, maxWidth: 165, dataIndex: 'lastStartTime', sortable: true, tpl:
                '<tpl if="!lastStartTime"><div style="width: 13px; margin: 0 auto">&mdash;</div><tpl else>{lastStartTime}</tpl>'
            },
            { text: 'Timezone', flex: 0.5, maxWidth: 180, dataIndex: 'timezone', sortable: true },
            { text: 'Status', minWidth: 110, width: 110, dataIndex: 'status', sortable: true, xtype: 'statuscolumn', statustype: 'schedulertask'}
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
            }, {
                xtype: 'tbfill'
            }, {
                text: 'New task',
                itemId: 'add',
                cls: 'x-btn-green',
                enableToggle: true,
                hidden: !Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage'),
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();

                        form.down('[name=startTimeDate]').setMinValue(new Date());
                        form.down('[name=scriptVersion]').scriptOptionsValue = {};
                        form.down('#scriptOptions').removeAll();

                        form.
                            applyFarmWidget(moduleParams['farmWidget'], true).
                            hideDeleteButton(true).
                            setSaveButtonText('Create').
                            setHeader('New Task').
                            show().
                            down('[name=name]').focus();

                        return;
                    }

                    form.hide();
                }
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.load();
                }
            }, {
                itemId: 'activate',
                disabled: true,
                iconCls: 'x-btn-icon-activate',
                tooltip: 'Activate',
                hidden: !Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage'),
                handler: function () {
                    grid.activateSelectedTasks();
                }
            }, {
                itemId: 'suspend',
                disabled: true,
                iconCls: 'x-btn-icon-suspend',
                tooltip: 'Suspend',
                hidden: !Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage'),
                handler: function () {
                    grid.suspendSelectedTasks();
                }
            }, {
                itemId: 'execute',
                disabled: true,
                iconCls: 'x-btn-icon-launch',
                tooltip: 'Execute',
                hidden: !Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage'),
                handler: function() {
                    grid.executeSelectedTasks();
                }
            }, {
                itemId: 'delete',
                disabled: true,
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Delete',
                hidden: !Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage'),
                handler: function () {
                    grid.deleteSelectedTasks();
                }
            }]
        }]
    });

    var form = Ext.create('Ext.form.Panel', {

        autoScroll: true,
        hidden: true,

        fieldDefaults: {
            anchor: '100%'
        },

        applyStartTime: function (value) {
            var me = this;

            var isTimeDefined = value !== 'Now';

            me.down('#startTimeType').setValue(
                !isTimeDefined ? 'Now' : 'Specified time'
            );

            if (isTimeDefined) {
                var timeIndex = value.length - 8;
                var startDate = new Date(value.substring(0, timeIndex - 1));
                var dateField = me.down('[name=startTimeDate]');

                dateField.setMinValue();
                dateField.setValue(startDate);

                me.down('[name=startTime]').setValue(
                    value.substring(timeIndex, timeIndex + 5)
                );
            }

            return me;
        },

        applyInterval: function (interval) {
            var me = this;

            if (!Ext.isEmpty(interval)) {
                var measure = 'minutes';

                if (!(interval % 60)) {
                    interval = interval / 60;
                    measure = 'hours';
                }

                if (!(interval % 24)) {
                    interval = interval / 24;
                    measure = 'days';
                }

                me.getForm().setValues({
                    restartEvery: interval,
                    restartEveryMeasure: measure
                });
            }

            return me;
        },

        hideScriptOptions: function (hidden) {
            var me = this;

            var fieldSet = me.down('#scriptOptions');

            fieldSet.
                setVisible(!hidden && fieldSet.items.getCount()).
                setDisabled(hidden);

            return me;
        },

        hideTerminationOptions: function (hidden) {
            var me = this;

            me.down('#terminationOptions').
                setVisible(!hidden).
                setDisabled(hidden);

            return me;
        },

        hideEventOptions: function (hidden) {
            var me = this;

            me.down('#eventOptions').
                setVisible(!hidden).
                setDisabled(hidden);

            return me;
        },

        hideExecutionOptions: function (hidden) {
            var me = this;

            me.down('#executionOptions').
                setVisible(!hidden).
                setDisabled(hidden);

            return me;
        },

        hideFieldSets: function (taskType) {
            var me = this;

            var scriptExecute = taskType === 'script_exec';
            var fireEvent = taskType === 'fire_event';
            var terminateFarm = taskType === 'terminate_farm';
            var launchFarm = taskType === 'launch_farm';

            me.
                hideScriptOptions(!scriptExecute).
                hideExecutionOptions(!scriptExecute).
                hideEventOptions(!fireEvent).
                hideTerminationOptions(!terminateFarm).
                down('#farmRoles').
                    setTitle(fireEvent ? 'Event context' : 'Target').
                    optionChange(terminateFarm || launchFarm ? 'add' : 'remove', 'disabledFarmRole').
                    optionChange(fireEvent ? 'remove' : 'add', 'isScalarizedOnly').
                    show();

            return me;
        },

        applyFarmWidget: function (params, hidden) {
            var me = this;

            me.remove(me.down('#farmRoles'));

            me.insert(2, {
                xtype: 'farmroles',
                itemId: 'farmRoles',
                title: 'Target',
                hidden: !!hidden,
                params: params
            });

            return me;
        },

        applyScriptConfig: function (config) {
            var me = this;

            config.scriptVersion = parseInt(config.scriptVersion);

            Ext.Object.each(config['scriptOptions'], function (option, value) {
                config['scriptOptions[' + option + ']'] = value;
            });

            me.down('[name=scriptVersion]').scriptOptionsValue = config['scriptOptions'];

            me.getForm().setValues(config);

            return me;
        },

        applyEventConfig: function (config) {
            var me = this;

            me.down('#eventParams').setValue(
                Ext.decode(config.eventParams)
            );

            me.getForm().setValues({
                eventName: config.eventName
            });

            return me;
        },

        applyFarmConfig: function (config) {
            var me = this;

            me.getForm().setValues({
                deleteCloudObjects: config.deleteCloudObjects,
                deleteDNSZones: config.deleteDNSZones
            });

            return me;
        },

        applyTaskData: function (farmWidget, config, type) {
            var me = this;

            me.
                applyFarmWidget(farmWidget).
                hideFieldSets(type);

            me.setFieldsReadOnly(!Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage'));

            if (config.scriptId !== 0) {
                me.applyScriptConfig(config);
                return me;
            }

            if (!Ext.isEmpty(config.eventName)) {
                me.applyEventConfig(config);
                return me;
            }

            if (Ext.isDefined(config.deleteDNSZones)) {
                me.applyFarmConfig(config);
            }

            return me;
        },

        requestTaskData: function (id) {
            var me = this;

            Scalr.Request({
                processBox: {
                    type: 'load'
                },
                url: '/schedulertasks/xGet',
                params: {
                    schedulerTaskId: id
                },
                success: function (response) {
                    me
                        .applyScripts(response.scripts)
                        .applyTaskData(
                            response['farmWidget'],
                            response['task']['config'],
                            response['task']['type']
                        );
                }
            });

            return me;
        },

        applyScripts: function (scripts) {
            var me = this;

            var scriptsStore = me.down('scriptselectfield').getStore();
            scriptsStore.removeAll();
            scriptsStore.loadData(scripts);

            return me;
        },

        setActualDate: function () {
            var me = this;

            var startDateField = me.down('[name=startTimeDate]');
            var startDate = startDateField.getValue();
            var startTimeField = me.down('[name=startTime]');
            var startTimeDate = startTimeField.getValue();

            if (!Ext.isEmpty(startDate) && !Ext.isEmpty(startTimeDate)) {
                startDate = Ext.Date.add(startDate, Ext.Date.HOUR, Ext.Date.format(startTimeDate, 'G'));
                startDate = Ext.Date.add(startDate, Ext.Date.MINUTE, Ext.Date.format(startTimeDate, 'i'));

                var userTimezoneOffset = Ext.Date.format(new Date(), 'Z');
                var selectedTimezoneOffset = me.down('[name=timezone]').getOffset();
                var timestamp = Ext.Date.format(startDate, 'timestamp') - parseInt(userTimezoneOffset) + parseInt(selectedTimezoneOffset);
                var date = Ext.Date.parse(timestamp, 'timestamp');

                startDateField.setValue(date);
                startTimeField.setValue(date);
            }

            return me;
        },

        saveTask: function () {
            var me = this;

            var baseForm = me.getForm();

            var params = {
                eventParams: me.down('[name=type]').getValue() === 'fire_event'
                    ? Ext.encode(me.down('#eventParams').getValue())
                    : null
            };

            if (baseForm.isValid()) {
                Scalr.Request({
                    processBox: {
                        type: 'save'
                    },
                    form: baseForm,
                    url: '/schedulertasks/xSave/',
                    params: params,
                    success: function (response) {
                        var task = response.task;

                        if (!Ext.isEmpty(task)) {
                            grid.applyTask(task);
                            return true;
                        }

                        store.load();
                        grid.down('#add').toggle(false, true);
                    }
                });

                return me;
            }
        },

        hideDeleteButton: function (hidden) {
            var me = this;

            me.down('#delete').setVisible(!hidden);

            return me;
        },

        setSaveButtonText: function (text) {
            var me = this;

            me.down('#save').setText(text);

            return me;
        },

        setCurrentTime: function () {
            var me = this;

            var date = new Date();

            me.down('[name=startTimeDate]').setValue(date);

            me.down('[name=startTime]').setValue(date);

            return me;
        },

        setHeader: function (header) {
            var me = this;

            me.down('fieldset').setTitle(header);

            return me;
        },

        setFieldsReadOnly: function (readOnly) {
            var me = this;

            me.getForm().getFields().each(function (field) {
                field.setReadOnly(readOnly);
            });
            me.down('#eventParams').setReadOnly(readOnly);

            return me;
        },

        listeners: {
            afterloadrecord: function (record) {
                var me = this;

                me.down('[name=scriptVersion]').scriptOptionsValue = {};
                me.down('#scriptOptions').removeAll();

                me.
                    requestTaskData(record.get('id')).
                    applyStartTime(record.get('startTime')).
                    applyInterval(record.get('restartEvery')).
                    hideDeleteButton(false).
                    setSaveButtonText('Save').
                    setHeader((Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage') ? 'Edit' : 'View') + ' Task');

                grid.down('#add').toggle(false, true);
            }
        },

        items: [{
            xtype: 'fieldset',
            title: 'Task details',
            defaults: {
                labelWidth: 120
            },
            items: [{
                xtype: 'hidden',
                name: 'id'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Name',
                name: 'name',
                allowBlank: false
            }, {
                xtype: 'combo',
                name: 'type',
                fieldLabel: 'Type',
                emptyText: 'Select task type',
                store: [
                    ['script_exec', 'Execute script'],
                    ['terminate_farm', 'Terminate farm'],
                    ['launch_farm', 'Launch farm'],
                    ['fire_event', 'Fire event']
                ],
                editable: false,
                allowBlank: false,
                listeners: {
                    change: function (field, value) {
                        form.hideFieldSets(value);
                    },
                    beforeselect: function(comp, record) {
                        var field = this.up('form').down('[name="farmRoleId"]');
                        if (record.get('field1') == 'script_exec' && comp.getPicker().isVisible() && field.isVisible()) {
                            var rec = field.findRecordByValue(field.getValue());
                            if (rec && rec.get('isScalarized') == '0') {
                                Scalr.message.InfoTip('Script execution is not available for agentless roles.', comp.inputEl, {anchor: 'bottom'});
                                return false;
                            }
                        }
                        
                    }
                }
            }, {
                xtype: 'textarea',
                fieldLabel: 'Description',
                name: 'comments'
            }]
        }, {
            xtype: 'fieldset',
            title: 'Schedule',
            collapsible: true,
            defaults: {
                labelWidth: 120
            },
            items: [{
                xtype: 'combo',
                itemId: 'startTimeType',
                fieldLabel: 'Start from',
                store: [ 'Now', 'Specified time' ],
                value: 'Now',
                submitValue: false,
                editable: false,
                maxWidth: 350,
                listeners: {
                    change: function (field, value) {
                        var isTimeDefined = value === 'Specified time';

                        field.next().
                            setVisible(isTimeDefined).
                            setDisabled(!isTimeDefined);

                        if (isTimeDefined) {
                            form
                                .setCurrentTime()
                                .setActualDate();
                        }
                    }
                }
            }, {
                xtype: 'fieldcontainer',
                fieldLabel: ' ',
                hidden: true,
                disabled: true,
                layout: 'hbox',
                maxWidth: 350,
                items: [{
                    xtype: 'datefield',
                    name: 'startTimeDate',
                    format: 'Y-m-d',
                    flex: 1,
                    value: new Date(),
                    minValue: new Date()
                }, {
                    xtype: 'timefield',
                    name: 'startTime',
                    format: 'H:i',
                    value: '00:00',
                    flex: 0.6,
                    margin: '0 0 0 12',
                    listeners: {
                        change: function (timeField, value) {
                            if (!Ext.isEmpty(value)) {
                                var minutes = parseInt(Ext.Date.format(value, 'i'));
                                var modulo =  minutes % 15;

                                if (modulo !== 0) {
                                    var hours = parseInt(Ext.Date.format(value, 'H'));

                                    minutes = minutes - modulo + 15;

                                    timeField.setValue(
                                        minutes !== 60
                                            ? hours + ':' + minutes
                                            : (hours + 1) + ':00'
                                    );
                                }
                            }
                        }
                    }
                }]
            }, {
                xtype: 'fieldcontainer',
                fieldLabel: 'Run every',
                layout: 'hbox',
                maxWidth: 350,
                items: [{
                    xtype: 'numberfield',
                    name: 'restartEvery',
                    allowBlank: false,
                    value: 30,
                    minValue: 1,
                    flex: 1,
                    getSubmitValue: function () {
                        var me = this;

                        var value = me.getValue();
                        var measure = me.next().getValue();

                        return measure !== 'minutes'
                            ? value * (measure === 'hours' ? 60 : 60 * 24)
                            : value;
                    }
                }, {
                    xtype: 'combo',
                    name: 'restartEveryMeasure',
                    editable: false,
                    submitValue: false,
                    queryMode: 'local',
                    store: [ 'minutes', 'hours', 'days' ],
                    value: 'minutes',
                    width: 100,
                    margin: '0 0 0 12'
                }]
            }, {
                xtype: 'combo',
                name: 'timezone',
                fieldLabel: 'Timezone',
                allowBlank: false,
                forceSelection: true,
                queryMode: 'local',
                editable: false,
                store: {
                    fields: ['id', 'name'],
                    proxy: 'object',
                    data: moduleParams['timezones']
                },
                valueField: 'id',
                displayField: 'id',
                value: moduleParams['defaultTimezone'] || '',
                maxWidth: 350,
                getOffset: function () {
                    var me = this, record = me.findRecord('id', me.getValue());
                    return record ? record.get('name') : Ext.Date.format(new Date(), 'Z');
                }
            }]
        }, {
            xtype: 'farmroles',
            itemId: 'farmRoles',
            title: 'Target',
            hidden: true,
            params: moduleParams['farmWidget']
        }, {
            xtype: 'fieldset',
            title: 'Execution options',
            itemId: 'executionOptions',
            collapsible: true,
            hidden: true,
            items: [{
                xtype: 'scriptselectfield',
                name: 'scriptId',
                allowBlank: false,
                store: {
                    proxy: 'object',
                    fields: [
                        'id',
                        'name',
                        'description',
                        'os',
                        'isSync',
                        'timeout',
                        'versions',
                        'accountId',
                        'scope',
                        'createdByEmail'
                    ],
                    data: moduleParams['scripts']
                },
                listeners: {
                    change: function (field, value) {
                        var cont = field.up(), r = field.findRecord('id', value), fR = cont.down('[name="scriptVersion"]');

                        if (!r)
                            return;

                        fR.setValue();
                        fR.store.loadData(r.get('versions'));
                        fR.store.insert(0, { version: -1, versionName: 'Latest', variables: fR.store.last().get('variables') });
                        fR.setValue(fR.store.first().get('version'));

                        cont.down('[name="scriptTimeout"]').setValue(r.get('timeout'));
                        cont.down('[name="scriptIsSync"]').setValue(r.get('isSync'));
                    }
                }
            }, {
                xtype: 'buttongroupfield',
                fieldLabel: 'Execution mode',
                name: 'scriptIsSync',
                defaults: {
                    width: 130
                },
                items: [{
                    text: 'Blocking',
                    value: 1
                },{
                    text: 'Non-blocking',
                    value: 0
                }],
                value: 1
            }, {
                xtype: 'textfield',
                fieldLabel: 'Timeout',
                name: 'scriptTimeout',
                vtype: 'num',
                validator: function (value) {
                    return value > 0 ? true : 'Timeout should be greater than 0';
                },
                allowBlank: false
            },{
                xtype: 'combo',
                name: 'scriptVersion',
                store: {
                    proxy: 'object',
                    fields: [
                        'version',
                        'versionName',
                        'variables'
                    ]
                },
                valueField: 'version',
                displayField: 'versionName',
                editable: false,
                queryMode: 'local',
                fieldLabel: 'Version',
                scriptOptionsValue: {},
                listeners: {
                    change: function (field, value) {
                        var record = field.findRecordByValue(value);
                        var fieldSet = form.down('#scriptOptions');

                        if (record) {
                            var fields = record.get('variables');
                            var scriptOptionsValue = field.scriptOptionsValue;

                            Ext.each(fieldSet.items.getRange(), function(item) {
                                scriptOptionsValue[item.name] = item.getValue();
                            });

                            fieldSet.removeAll();

                            if (Ext.isObject(fields)) {
                                for (var i in fields) {
                                    fieldSet.add({
                                        xtype: 'textfield',
                                        fieldLabel: fields[i],
                                        name: 'scriptOptions[' + i + ']',
                                        readOnly: !Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage'),
                                        value: scriptOptionsValue['scriptOptions[' + i + ']'] ? scriptOptionsValue['scriptOptions[' + i + ']'] : '',
                                        width: '100%'
                                    });
                                }
                                fieldSet.show();
                            } else {
                                fieldSet.hide();
                            }
                        } else {
                            fieldSet.hide();
                        }
                    }
                }
            }]
        }, {
            xtype: 'fieldset',
            title: 'Script options',
            itemId: 'scriptOptions',
            labelWidth: 100,
            hidden: true
        }, {
            xtype: 'fieldset',
            title: 'Termination options',
            itemId: 'terminationOptions',
            hidden: true,
            items: [{
                xtype: 'checkbox',
                name: 'deleteDNSZones',
                boxLabel: 'Delete DNS zone from nameservers. It will be recreated when the farm is launched.',
                inputValue: 1
            }, {
                xtype: 'checkbox',
                name: 'deleteCloudObjects',
                boxLabel: 'Delete cloud objects (EBS, Elastic IPs, etc)',
                inputValue: 1
            }]
        }, {
            xtype: 'fieldset',
            title: 'Event options',
            itemId: 'eventOptions',
            hidden: true,
            items: [{
                xtype: 'combo',
                name: 'eventName',
                emptyText: 'Select an event',
                store: {
                    proxy: 'object',
                    fields: [
                        'name',
                        'description',
                        'scope'
                    ],
                    data: moduleParams['events']
                },
                plugins: {
                    ptype: 'fieldinnericonscope',
                    tooltipScopeType: 'event'
                },
                matchFieldWidth: true,
                listConfig: Scalr.configs.eventsListConfig,
                displayField: 'name',
                queryMode: 'local',
                valueField: 'name',
                editable: true,
                anyMatch: true,
                autoSearch: false,
                selectOnFocus: true,
                restoreValueOnBlur: true,
                allowBlank: false,
                fieldLabel: 'Event',
                margin: 0,
                listeners: {
                    change: function (field, value) {
                        var record = field.findRecordByValue(value);

                        field.next().setValue(
                            record ? record.get('description') || 'No description for this event' : '&nbsp;'
                        );
                    }
                }
            },{
                xtype: 'displayfield',
                itemId: 'eventDescription',
                fieldLabel: ' ',
                renderer: function (value) {
                    return '<i>' + value + '</i>';
                }
            },{
                xtype: 'label',
                cls: 'x-form-item-label-default',
                text: 'Scripting parameters',
                style: 'display:block',
                margin: '0 0 6 0'
            },{
                xtype: 'namevaluelistfield',
                itemId: 'eventParams',
                itemName: 'parameter',
                boxready: function (field) {
                    if (!field.store.getCount()) {
                        field.store.add({});
                    }
                }

            }]
        }],

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            hidden: !Scalr.isAllowed('GENERAL_SCHEDULERTASKS', 'manage'),
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults: {
                xtype: 'button',
                flex: 1,
                maxWidth: 140
            },
            items: [{
                text: 'Save',
                itemId: 'save',
                handler: function () {
                    form.saveTask();
                }
            }, {
                text: 'Cancel',
                handler: function () {
                    grid.clearSelectedRecord();
                    grid.down('#add').toggle(false, true);
                }
            }, {
                xtype: 'button',
                itemId: 'delete',
                cls: 'x-btn-red',
                text: 'Delete',
                handler: function () {
                    grid.deleteSelectedTask();
                }
            }]
        }]
    });

    return Ext.create('Ext.panel.Panel', {

        stateful: true,
        stateId: 'grid-schedulertasks-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Tasks Scheduler',
            menuHref: '#/schedulertasks',
            menuFavorite: true
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: .4,
            maxWidth: 600,
            minWidth: 420,
            layout: 'fit',
            items: [ form ]
        }]
    });
});

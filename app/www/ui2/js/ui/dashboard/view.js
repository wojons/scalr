Scalr.regPage('Scalr.ui.dashboard.view', function (loadParams, moduleParams) {
    Scalr.storage.set('dashboard', Ext.Date.now());
    var dashBoardUp = Scalr.storage.get('dashboard');
    var addWidgetForm = function () {// function for add Widget panel
        var widgetForm = new Ext.form.FieldSet({
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            items: [{
                xtype: 'checkboxgroup',
                allowBlank: false,
                vertical: true,
                columns: 1,
                msgTarget: 'none'
            }]
        });
        var widgets = Scalr.scope !== 'scalr' ? [
            {name: 'dashboard.announcement', title: 'Announcements', desc: 'Displays announcements from The Official Scalr blog,<br/> from Changelog and User defined ones.'},
            {name: 'dashboard.newuser', title: 'New user checklist', desc: ''}
        ] : [];

        if (Scalr.isAllowed('BILLING_ACCOUNT') && moduleParams.flags['billingEnabled'])
            widgets.push({name: 'dashboard.billing', title: 'Billing', desc: 'Displays your current billing parameters'});

        if (Scalr.scope === 'scalr') {
            widgets.push({name: 'dashboard.gettingstarted', title: 'Getting started', desc: 'Getting started tutorials.'});
            widgets.push({name: 'dashboard.scalrhealth', title: 'Scalr Health', desc: 'Scalr health monitoring.'});
        }

        if (Scalr.scope == 'account') {
            widgets.push({name: 'dashboard.environments', title: 'Environments in this account', desc: 'Displays environments in this account'});
        }

        if (Scalr.scope == 'environment') {
            widgets.push({name: 'dashboard.status', title: 'AWS health status', desc: 'Displays most up-to-the-minute information on service availability of Amazon Web Services'});

            if (Scalr.isAllowed('LOGS_SYSTEM_LOGS')) {
                widgets.push({name: 'dashboard.lasterrors', title: 'Last errors', desc: 'Displays last 10 errors from system logs'});
            }

            if (Scalr.isAllowed('FARMS', 'create') || Scalr.isAllowed('TEAM_FARMS', 'create') || Scalr.isAllowed('OWN_FARMS', 'create')) {
                widgets.push({name: 'dashboard.addfarm', title: 'Create new Farm', desc: ''});
            }

            if (Scalr.flags['analyticsEnabled'] && Scalr.isAllowed('ANALYTICS_ENVIRONMENT')) {
                widgets.push({name: 'dashboard.costanalytics', title: 'Cost Analytics', desc: ''});
            }

            if (moduleParams.flags['cloudynEnabled'] && Scalr.isPlatformEnabled('ec2'))
                widgets.push({
                    name: 'dashboard.cloudyn',
                    title: 'Cloudyn',
                    desc: 'Integration with Cloudyn'
                });
        }

        for (var i = 0; i < widgets.length; i++) {   //all default widgets
            var widget = widgets[i];
            var widgetName = widget['name'];
            var widgetDescription = widget['desc'];

            if (moduleParams['panel']['widgets'].indexOf(widgetName) == -1) {
                widgetForm.down('checkboxgroup').add({
                    xtype: 'checkbox',
                    boxLabel: widget['title'],
                    name: 'widgets',
                    inputValue: widgetName,
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        position: 'outer',
                        icons: {
                            id: 'info',
                            tooltip: widgetDescription,
                            hidden: Ext.isEmpty(widgetDescription)
                        }
                    }]
                });
            }
        }
        if (!widgetForm.down('checkboxgroup').items.length) {
            widgetForm.down('checkboxgroup').hide();
            widgetForm.add({xtype: 'displayfield', anchor: '100%', fieldStyle: 'text-align:center', value: 'All available widgets are already in use'});
        }
        widgetForm.updateLayout();
        return widgetForm;
    };
    var updateHandler = {
        id: 0,
        timeout: 65000,
        running: false,
        schedule: function (run) {
            this.running = Ext.isDefined(run) ? run : this.running;

            clearTimeout(this.id);
            if (this.running)
                this.id = Ext.Function.defer(this.start, this.timeout, this);
        },
        start: function (force) {
            var list = [];
            var widgets = {};
            var widgetCount = 0,
                time = (new Date()).getTime();
            panel.items.each(function (column) {  /*get all widgets in columns*/
                column.items.each(function (widget) {
                    if (widget.widgetType == 'local') {
                        if (!force && widget.updateTimeout && widget.lastUpdateTime + widget.updateTimeout > time) return true;
                        widgetCount++;
                        widgets[widget.id] = {
                            name: widget.xtype,
                            params: widget.params || {}
                        };
                    }
                });
            }); /*end*/
            if (widgetCount)
                Scalr.Request({
                    url: '/dashboard/xAutoUpdateDash/',
                    params: {
                        updateDashboard: Ext.encode(widgets)
                    },
                    headers: {
                        'Scalr-Autoload-Request': 1
                    },
                    success: function (data) {
                        for (var i in data['updateDashboard']) {
                            if (panel.down('#' + i)) {
                                if (data.updateDashboard[i]['widgetContent'])
                                    panel.down('#' + i).widgetUpdate(data.updateDashboard[i]['widgetContent']);
                                else
                                    panel.down('#' + i).widgetError(data.updateDashboard[i]['widgetError'] || 'Some error has occurred');
                            }
                        }
                        this.schedule();
                    },
                    failure: function () {
                        this.schedule();
                    },
                    scope: this
                });
        }
    };
    var panel = Ext.create('Scalr.ui.dashboard.Panel',{
        defaultType: 'dashboard.column',
        scalrOptions: {
            maximize: 'all',
            reload: false,
            menuTitle: Scalr.scope == 'scalr' ? 'Admin Dashboard' : Scalr.scope == 'account' ? 'Account Dashboard' : 'Dashboard'
        },
        style: {
            overflowY: 'visible',
            overflowX: 'hidden',
            minHeight: '100%'
        },
        stateId: Scalr.scope === 'scalr' ? 'panel-admin-dashboard' : Scalr.scope == 'account' ? 'panel-account-dashboard' : 'panel-dashboard',

        bodyStyle: 'background-color: transparent;', // should be padding: 12px, but extjs goes into recursion in 4.2.2

        isSaving: false,
        savingPanel: 0,

        fillDash: function () { // function for big panel
            this.suspendLayouts();
            this.removeAll();
            var configuration = moduleParams['panel']['configuration'];
            for (var i = 0; i < configuration.length; i++) {  // all columns in panel
                panel.newCol(i);
                if (configuration[i]) {
                    for (var j = 0; j < configuration[i].length; j++) { // all widgets in column
                        if (! configuration[i][j])
                            continue;

                        if (configuration[i][j]['name'] == 'dashboard.billing' && (!moduleParams.flags['billingEnabled'] || !Scalr.isAllowed('BILLING_ACCOUNT')))
                            continue;

                        try {
                            var widget = this.items.getAt(i).add(
                                panel.newWidget(
                                    configuration[i][j]['name'],
                                    configuration[i][j]['params'],
                                    moduleParams['params']
                                )
                            );
                            if (widget.widgetType == 'local') {
                                if (configuration[i][j]['widgetContent'])
                                    widget.widgetUpdate(configuration[i][j]['widgetContent']);
                                else
                                    widget.widgetError(configuration[i][j]['widgetError'] || 'Some error has occurred');
                            }
                        } catch (e) {}
                    }
                }
            }
            panel.updateColWidth();
            this.resumeLayouts(true);
        },

        savePanel: function (refill, cb) {
            if (!this.isSaving) { /*if saving not in process*/
                this.isSaving = true;
                var me = this;
                var configuration = moduleParams['panel']['configuration']; //cols
                var i = 0;
                if (panel.items) {
                    configuration = [];
                    panel.items.each(function(column){
                        var col = [];
                        column.items.each(function(item){
                            col.push({ params: item.params, name: item.xtype });
                        });
                        configuration.push(col);
                    });
                    moduleParams['panel']['configuration'] = configuration;
                }

                if (this.savingPanel)
                    this.savingPanel.show();

                Scalr.Request({
                    url: '/dashboard/xSavePanel',
                    params: {
                        panel: Ext.encode(moduleParams['panel'])
                    },
                    success: function(data) {
                        moduleParams['panel'] = data['panel'];

                        Scalr.storage.set('dashboard', Ext.Date.now());
                        dashBoardUp = Scalr.storage.get('dashboard');
                        if (me.savingPanel)
                            me.savingPanel.hide();
                        me.isSaving = false;
                        if (Ext.isFunction(cb)) {
                            cb();
                        }
                    },
                    failure: function () {
                        me.isSaving = false;
                    }
                });
                if (refill)
                    updateHandler.start(true);
            }
        },
        listeners: {
            activate: function () {
                updateHandler.schedule(true);
            },
            deactivate: function () {
                updateHandler.schedule(false);
            },
            resize: function (e, x, y) {
                if (panel.items) {
                    for (var i = 0; i < panel.items.length; i++) {
                        panel.items.getAt(i).setHeight(y);  //maximize column height
                    }
                }
            },
            afterrender: function() {
                var panelContainer = Ext.DomHelper.insertFirst(panel.el, {id:'editpanel-div'}, true);   /*create panel for indicate Saving*/
                this.savingPanel = Ext.DomHelper.append (panelContainer,
                    '<div class="x-mask-msg" style="z-index: 999; left: 48%; position: absolute; top: 2px;">' +
                        '<div class="x-mask-loading x-mask-msg-inner ">' +
                            '<div class="x-mask-msg-text"></div>' +
                        '</div>' +
                    '</div>', true);
                this.savingPanel.hide();													/*end*/
                panel.body.on('click', function(e, el, obj) {
                    if (e.getTarget('div.remove')) {
                        if (panel.items.length === 1) {
                            Scalr.Confirm ({
                                msg: 'After removing last column default dashboard configuration will be restored. Are you sure you want to continue?',
                                type: 'delete',
                                scope: panel.down('[id='+e.getTarget('.scalr-ui-dashboard-container').id+']'),
                                success: function(data) {
                                    if (!this.items.length) {
                                        panel.remove(this);
                                        panel.savePanel(0, function(){Scalr.event.fireEvent('refresh', true);});
                                    }
                                }
                            });
                        } else {
                            Scalr.Confirm ({
                                msg: 'Are you sure you want to remove this column from dashboard?',
                                type: 'delete',
                                scope: panel.down('[id='+e.getTarget('.scalr-ui-dashboard-container').id+']'),
                                success: function(data) {
                                    if (!this.items.length) {
                                        panel.remove(this);
                                        panel.savePanel(0);
                                        panel.updateColWidth();
                                        panel.updateLayout();

                                        var divs = panel.el.query('div.add')
                                        for (var i = 0; i < divs.length; i++) {
                                            divs[i].setAttribute('index', i);
                                        }
                                    }
                                }
                            });
                        }
                    }
                    if (e.getTarget('div.add')) {
                        var index = e.getTarget('div.add').getAttribute('index'); // in which column to add
                        Scalr.Confirm ({
                            title: 'Select widgets to add',
                            form: addWidgetForm(),
                            ok: 'Add',
                            formValidate: true,
                            scope: this,
                            success: function(formValues) {
                                if(formValues.widgets){
                                    if (!panel.items.length)
                                        panel.newCol(0);
                                    if (Ext.isArray(formValues.widgets)) {
                                        for(var i = 0; i < formValues.widgets.length; i++) {
                                            panel.items.getAt(index).add(panel.newWidget(formValues.widgets[i]));
                                        }
                                    } else
                                        panel.items.getAt(index).add(panel.newWidget(formValues.widgets));
                                    panel.savePanel(1);
                                }
                            }
                        });
                    }
                });
            }
        }
    });

    panel.fillDash();

    var updateDash = function (type, data) {
        if (type == '/dashboard') {
            moduleParams['panel'] = data;
            panel.fillDash();
        }
    };

    Scalr.event.on('update',updateDash);
    panel.on('destroy', function() {
        Scalr.event.un('update', updateDash);
        delete Scalr.storage.listeners['dashboard'];
    });

    Scalr.storage.listeners['dashboard'] = function (value){
        if (value != dashBoardUp)
            Scalr.event.fireEvent('refresh');
    };
    return panel;
});
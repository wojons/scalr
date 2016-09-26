Ext.define('Scalr.ui.dashboard.Column', {
    extend: 'Ext.container.Container',
    alias: 'widget.dashboard.column',

    cls: 'scalr-ui-dashboard-container',
    index: 0,
    initComponent: function () {
        this.callParent();
        this.html =
            '<div class = "editpanel">' +
                '<div class="add" index=' + this.index + '>Add widget</div>' +
                '<div class="remove">Delete column</div>' +
            '</div>';
        this.on('afterrender', function(){
            var me = this;
            me.el.on({
                mouseenter: function(){
                    me.refreshOverCls();
                },
                mouseleave: function(){
                    this.removeCls('scalr-ui-dashboard-container-over scalr-ui-dashboard-container-over-empty');
                }
            });
        });
    },
    refreshOverCls: function() {
        this.removeCls('scalr-ui-dashboard-container-over scalr-ui-dashboard-container-over-empty');
        this.addCls(this.items.length ? 'scalr-ui-dashboard-container-over' : 'scalr-ui-dashboard-container-over-empty');
    }

});
Ext.define('Scalr.ui.dashboard.Panel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashpanel',

    cls: 'scalr-ui-dashboard-panel',
    defaultType: 'dashboard.column',
    autoScroll: true,
    border: false,

    layout: {
        type : 'column',
        reserveScrollbar: true
    },

    initComponent : function() {
        this.callParent();

        this.on('drop',
            function (dropObject, e) {
                dropObject.panel.setPosition(0, 0);
                //this.savePanel();
                this.doLayout();
            },
        this);
    },

    // private
    initEvents : function(){
        this.callParent();
        this.dd = Ext.create('Scalr.ui.dashboard.DropZone', this, this.dropConfig);
    },

    // private
    beforeDestroy : function() {
        if (this.dd) {
            this.dd.unreg();
        }
        Scalr.ui.dashboard.Panel.superclass.beforeDestroy.call(this);
    },

    updateColWidth: function () {
        var items = this.layout.getLayoutItems(),
            len = items.length,
            i = 0,
            j = 0,
            total = 0,
            item;
        if (items[0] && items[0].up()) {
            for (; i < len; i++) {  ///columns
                item = items[i];
                item.columnWidth = parseFloat((1 / len).toFixed(2));
                total += item.columnWidth;
            }
            if (items[i - 1]) {
                items[i - 1].margin = 0;
                if (total < 1)
                    items[i - 1].columnWidth += 1 - total;
            }
        }
    },

    newCol: function (index) {
        this.add({
            layout: 'anchor',
            index: index || 0,
            padding: '12 0 0 12'
        });
    },

    newWidget: function(type, params, moduleParams) {
        return {
            xtype: type,
            collapsible: true,
            draggable: {
                endDrag: function(){
                    this.panelProxy.hide();
                    this.panel.setStyle({
                        left: 0,
                        top: 0
                    });
                }
            },
            addTools: this.setTools,
            layout: 'fit', // TODO: remove on refactor
            anchor: '100%',
            params: params,
            moduleParams: moduleParams,
            margin: '0 0 12 0'
        };
    },
    setTools: function() { //function for all moduls
        var me = this.up('dashpanel');
        if (this.showSettingsWindow)
            this.tools.push({
                xtype: 'tool',
                type: 'settings',
                handler: function () {
                    this.up().up().showSettingsWindow();
                }
            });
        this.tools.push({
            xtype: 'tool',
            type: 'close',
            handler: function(e, toolEl, closePanel) {
                Scalr.Confirm({
                    msg: 'Are you sure you want to remove this widget from dashboard?',
                    type: 'action',
                    success: function() {
                        var p = closePanel.up();
                        p.el.animate({
                            opacity: 0,
                            callback: function(){
                                var ct = p.up();
                                p.fireEvent('close', p);
                                p[this.closeAction]();
                                me.savePanel();
                                if (ct.el) {
                                    Ext.defer(function(){
                                        ct.refreshOverCls();
                                    }, 200);
                                }
                            },
                            scope: p
                        });
                    }
                });
            }
        });
    }
});
Ext.define('Scalr.ui.dashboard.DropZone', {
    extend: 'Ext.dd.DropTarget',

    constructor: function(dash, cfg) {
        this.dash = dash;
        Ext.dd.ScrollManager.register(dash.body);
        Scalr.ui.dashboard.DropZone.superclass.constructor.call(this, dash.body, cfg);
        dash.body.ddScrollConfig = this.ddScrollConfig;
    },

    ddScrollConfig: {
        vthresh: 50,
        hthresh: -1,
        animate: true,
        increment: 200
    },

    createEvent: function(dd, e, data, col, c, pos) {
        return {
            dash: this.dash,
            panel: data.panel,
            columnIndex: col,
            column: c,
            position: pos,
            data: data,
            source: dd,
            rawEvent: e,
            status: this.dropAllowed
        };
    },

    notifyOver: function(dd, e, data) {
        var xy = e.getXY(),
            dash = this.dash,
            proxy = dd.proxy;

        // case column widths
        if (!this.grid) {
            this.grid = this.getGrid();
        }
        // handle case scroll where scrollbars appear during drag
        var cw = dash.body.dom.clientWidth;
        if (!this.lastCW) {
            // set initial client width
            this.lastCW = cw;
        } else if (this.lastCW != cw) {
            // client width has changed, so refresh layout & grid calcs
            this.lastCW = cw;
            //dash.doLayout();
            this.grid = this.getGrid();
        }

        // determine column
        var colIndex = 0,
            colRight = 0,
            cols = this.grid.columnX,
            len = cols.length,
            cmatch = false;

        for (len; colIndex < len; colIndex++) {
            colRight = cols[colIndex].x + cols[colIndex].w;
            if (xy[0] < colRight) {
                cmatch = true;
                break;
            }
        }
        // no match, fix last index
        if (!cmatch) {
            colIndex--;
        }

        // find insert position
        var overWidget, pos = 0,
            h = 0,
            match = false,
            overColumn = dash.items.getAt(colIndex),
            widgets = overColumn.items.items,
            overSelf = false;
        if (this.lastPos && this.lastPos.c) {
            this.lastPos.c.removeCls('scalr-ui-dashboard-container-dd');
        }
        overColumn.addCls('scalr-ui-dashboard-container-dd');

        len = widgets.length;

        for (len; pos < len; pos++) {
            overWidget = widgets[pos];
            h = overWidget.el.getHeight();
            if (h === 0) {
                overSelf = true;
            } else if ((overWidget.el.getY() + (h / 2)) > xy[1]) {
                match = true;
                break;
            }
        }

        pos = (match && overWidget ? pos : overColumn.items.getCount()) + (overSelf ? -1 : 0);
        var overEvent = this.createEvent(dd, e, data, colIndex, overColumn, pos);

        if (dash.fireEvent('validatedrop', overEvent) !== false && dash.fireEvent('beforedragover', overEvent) !== false) {

            // make sure proxy width is fluid in different width columns
            proxy.getProxy().setWidth('auto');

            if (overWidget) {
                dd.panelProxy.moveProxy(overWidget.el.dom.parentNode, match ? overWidget.el.dom : null);
            } else {
                dd.panelProxy.moveProxy(overColumn.el.dom, null);
            }

            this.lastPos = {
                c: overColumn,
                col: colIndex,
                p: overSelf || (match && overWidget) ? pos : false
            };
            this.scrollPos = dash.body.getScroll();

            dash.fireEvent('dragover', overEvent);
            return overEvent.status;
        } else {
            return overEvent.status;
        }
    },

    notifyOut: function(dd) {
        if (this.lastPos && this.lastPos.c) {
            this.lastPos.c.removeCls('scalr-ui-dashboard-container-dd');
        }
        delete this.grid;
    },

    notifyDrop: function(dd, e, data) {
        delete this.grid;
        if (!this.lastPos) {
            return;
        }
        var c = this.lastPos.c,
            col = this.lastPos.col,
            pos = this.lastPos.p,
            panel = dd.panel,
            dropEvent = this.createEvent(dd, e, data, col, c, pos !== false ? pos : c.items.getCount());

        if (this.dash.fireEvent('validatedrop', dropEvent) !== false &&
            this.dash.fireEvent('beforedrop', dropEvent) !== false) {

            Ext.suspendLayouts();

            // make sure panel is visible prior to inserting so that the layout doesn't ignore it
            panel.el.dom.style.display = '';
            dd.proxy.hide();
            dd.panelProxy.hide();
            var parentCol = panel.up();
            if (pos !== false) {
                c.insert(pos, panel);
            } else {
                c.add(panel);
            }

            Ext.resumeLayouts(true);
            this.dash.fireEvent('drop', dropEvent);

            // scroll position is lost on drop, fix it
            var st = this.scrollPos.top;
            if (st) {
                var d = this.dash.body.dom;
                setTimeout(function() {
                        d.scrollTop = st;
                    },
                    10);
            }
        }
        this.lastPos.c.removeCls('scalr-ui-dashboard-container-dd');
        delete this.lastPos;
        if (parentCol != c)
            panel.up('dashpanel').savePanel(0);

        panel.up('container').refreshOverCls();
        return true;
    },

    // internal cache of body and column coords
    getGrid: function() {
        var box = this.dash.body.getBox();
        box.columnX = [];
        this.dash.items.each(function(c) {
            box.columnX.push({
                x: c.el.getX(),
                w: c.el.getWidth()
            });
        });
        return box;
    },

    // unregister the dropzone from ScrollManager
    unreg: function() {
        Ext.dd.ScrollManager.unregister(this.dash.body);
        Scalr.ui.dashboard.DropZone.superclass.unreg.call(this);
    }
});

Ext.define('Scalr.ui.dashboard.Farm', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.farm',

    title: 'Farm servers',
    layout: 'fit',
    items: [{
        xtype: 'dataview',
        store: {
            fields: [ 'behaviors', 'group', 'servCount', 'farmRoleId', 'farmId', 'roleId', 'farmRoleAlias'],
            proxy: 'object'
        },
        border: true,
        deferEmptyText: false,
        loadMask: false,
        itemSelector: 'div.scalr-ui-dashboard-farms-servers',
        tpl: new Ext.XTemplate(
            '<tpl if="values.length">',
                '<ul class="scalr-ui-dashboard-farms" style="text-align:center">' +
                    '<tpl for=".">' +
                    '<li>' +
                    '<a href="#/farms/{farmId}/roles/{farmRoleId}/view" data-anchor="top"  data-qtip="{farmRoleAlias:htmlEncode}" class="icon"><div class="x-icon-role-small x-icon-role-small-{[Scalr.utils.getRoleCls(values)]}" /></div></a>' +
                    '<a href="#/servers?farmId={farmId}&farmRoleId={farmRoleId}" class="count">{servCount}</a>' +
                    '</li>' +
                    '</tpl>' +
                '</ul>',
            '</tpl>')
    }],
    widgetType: 'local',
    widgetUpdate: function (content) {
        this.down('dataview').emptyText = '<div class="x-grid-empty">No servers running</div>';
        this.down('dataview').store.load({
            data: content['servers']
        });
        this.title = 'Farm <span style="font: 14px OpenSansRegular; text-transform: none;">(' + content['name'] + ')</span>';
    },
    widgetError: function (msg) {
        this.down('dataview').emptyText = '<div class="x-grid-empty x-error">' + msg + '</div>';
    }
});

Ext.define('Scalr.ui.dashboard.Monitoring', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.monitoring',
    widgetType: 'nonlocal',
    maxHeight: 300 + 42,

    loadContent: function () {
        var me = this;
        var hostUrl = me.moduleParams['monitoringUrl'];
        var params = me.params;
        var template = new Ext.XTemplate(
            '<div class="scalr-ui-dashboard-monitoring-image-container" style="background-color: white; text-align: center; line-height: 300px; height: 100%; width: 100%;">',
            '<tpl if="imageSource">',
                '<img class="scalr-ui-dashboard-monitoring-image" style="height: 100%; max-height: 300px; vertical-align: top;" src="{imageSource}" /></div>',
            '</tpl>',
            '<tpl if="message">{message}</tpl>',
            '<tpl if="error">',
                '<div style="position: absolute; height: 30px; width: 400px; line-height: 30px;top: 50%; margin-top: -15px; left: 50%; margin-left: -200px; color: #F04A46;">{error}</div>',
            '</tpl>',
            '</div>'
        );

        if (me.rendered) {
            me.body.mask('');
        }

        if (params['widgetError']) {
            me.update(template.apply({ error: params['widgetError'] }));
            return;
        }

        Scalr.Request({
            method: 'GET',
            url: hostUrl + '/load_statistics',
            scope: this,
            params: params,
            success: function (data) {
                if (!me.isDestroyed) {
                    var metric = params.metrics;
                    var metricData = data['metric'][metric];
                    if (metricData.success) {
                        var imageSource = metricData.img;
                        if (typeof imageSource !== 'string') {
                            var disk = params.disk;
                            var graph = params.graph;

                            imageSource = imageSource[disk][graph];
                        }
                        me.update(template.apply({ imageSource: imageSource }));
                    } else {
                        me.update(template.apply({ message: metricData.msg }));
                    }
                    me.fireEvent('resize');
                }
            },

            failure: function (data) {
                if (!me.isDestroyed) {
                    me.update(template.apply({ error: data && data.msg ? data.msg: 'Connection error' }));
                }
            }
        });
    },

    setImageTopMargin: function () {
        var me = this;
        var imageContainer = me.el.down('.scalr-ui-dashboard-monitoring-image-container');
        var image = me.el.down('.scalr-ui-dashboard-monitoring-image');

        if (imageContainer && image) {
            var imageContainerHeight = imageContainer.getHeight();
            var imageTopMargin = 0;

            if (imageContainerHeight > 300) {
                imageTopMargin = (imageContainerHeight - 300) / 2;
            }

            image.setStyle('margin-top', imageTopMargin + 'px');
        }
    },

    listeners: {
        boxready: function() {
            var me = this;

            var currentWidth = me.getWidth();
            me.initWidth = me.getWidth();
            var height = currentWidth / 16 * 9;
            me.setHeight(height <= me.maxHeight ? height : me.maxHeight);

            me.params = me.params || {};

            me.setTitle(me.params['title'] || 'Monitoring');
            if (! me.collapsed) {
                me.loadContent();
            }

            //me.setImageTopMargin();
        },
        resize: function () {
            var me = this;
            var currentHeight = me.getHeight();
            var currentWidth = me.getWidth();
            var deltaWidth = currentWidth - me.initWidth;
            var deltaHeight = deltaWidth / 16 * 9;

            me.initWidth = currentWidth;
            var height = currentHeight + deltaHeight;
            me.setHeight(height <= me.maxHeight ? height : me.maxHeight);
            //me.setImageTopMargin();
        },
        beforeexpand: function() {
            if (this.rendered)
                this.body.mask();
        },
        expand: function () {
            this.loadContent();
        }
    }
});

Ext.define('Scalr.ui.dashboard.Announcement', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.announcement',

    title: 'Announcements',
    items: {
        xtype: 'announcementsview',
        client: 'dashboard',
        maxHeight: 600,
        store: Ext.create('Ext.data.ChainedStore', {
            source: Scalr.utils.announcement.store,
            newsCount: 8,
            recCount: 8,
            listeners: {
                datachanged: function () {
                    this.recCount = this.newsCount;
                }
            }
        })
    },
    widgetType: 'local',

    checkParams: function (newsCount, force) {
        if (Ext.isEmpty(this.params)) {
            this.params = {newsCount: 8};
            this.down('announcementsview').store.newsCount = 8;
        } else if (newsCount && (newsCount !== this.params['newsCount'] || force)) {
            this.params['newsCount'] = newsCount;
            this.down('announcementsview').store.newsCount = newsCount;
        }
    },
    widgetUpdate: Ext.emptyFn,
    widgetError: Ext.emptyFn,
    showSettingsWindow: function () {
        this.checkParams();

        Scalr.Confirm({
            formSimple: true,
            form: [{
                xtype: 'combo',
                store: [1, 4, 8, 10],
                fieldLabel: 'Number of announcements:',
                labelWidth: 200,
                editable: false,
                value: this.params['newsCount'],
                queryMode: 'local',
                name: 'newsCount',
                anchor: '100%'
            }],
            title: 'Settings',
            success: function (data) {
                var newsCount = data['newsCount'],
                    store = this.down('announcementsview').store;

                if (newsCount) {
                    this.checkParams(newsCount);
                    this.up('dashpanel').savePanel(0);

                    store.fireEvent('datachanged', store);
                    store.source.loadData(store.source.data.items);
                }
            },
            scope: this
        });
    },

    listeners: {
        render: function () {
            var store = this.down('announcementsview').store;

            this.checkParams(this.params && this.params['newsCount'] || null, true);
            store.fireEvent('datachanged', store);
            if (!store.getFilters().length) {
                store.setFilters([function () {
                    return 0 < store.recCount--;
                }]);
            } else {
                store.source.loadData(store.source.data.items);
            }
        }
    }
});

Ext.define('Scalr.ui.dashboard.LastErrors', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.lasterrors',

    title: 'Last errors',
    layout: 'fit',
    items: {
        xtype: 'dataview',
        store: {
            fields: [ 'message', 'time', 'server_id', 'cnt' ],
            proxy: 'object'
        },
        deferEmptyText: false,
        emptyText: '<div class="x-grid-empty">No errors</div>',
        loadMask: false,
        itemSelector: 'div.scalr-ui-dashboard-widgets-div',
        tpl: new Ext.XTemplate(
            '<tpl for=".">',
            '<div title="{message}" class="scalr-ui-dashboard-widgets-div">',
            '<div class="scalr-ui-dashboard-widgets-desc"><tpl if="server_id"><a href="#/servers/{server_id}/dashboard">{time}</a><tpl else>{time}</tpl><tpl if="cnt &gt; 1"> (repeated {cnt} times)</tpl></div>',
            '<div style="max-height: 60px; overflow: hidden;"><span class="scalr-ui-dashboard-widgets-message-slim">{message}</span></div>',
            '</div>',
            '</tpl>'
        )
    },
    widgetType: 'local',
    widgetUpdate: function (content) {
        if (!this.params || !this.params['errorCount'])
            this.params = {'errorCount': 10};
        this.down('dataview').emptyText = '<div class="x-grid-empty">No errors</div>';
        this.down('dataview').store.load({
            data: content
        });
    },
    widgetError: function (msg) {
        this.down('dataview').emptyText = '<div class="x-grid-empty x-error">' + msg + '</div>';
    },
    showSettingsWindow: function () {
        if (!this.params || !this.params['errorCount'])
            this.params = {errorCount: 10};
        Scalr.Confirm({
            formSimple: true,
            form: [{
                xtype: 'combo',
                //margin: 5,
                store: [5, 10, 15, 20, 50, 100],
                fieldLabel: 'Number of errors:',
                labelWidth: 120,
                editable: false,
                value: this.params['errorCount'],
                queryMode: 'local',
                name: 'errorCount',
                anchor: '100%'
            }],
            title: 'Settings',
            success: function (data) {
                if (data['errorCount']) {
                    this.params['errorCount'] = data['errorCount'];
                    this.up('dashpanel').savePanel(1);
                }
            },
            scope: this
        });
    }
});

Ext.define('Scalr.ui.dashboard.Billing', {
    extend: 'Ext.form.Panel',
    alias: 'widget.dashboard.billing',
    cls: 'scalr-ui-dashboard-widgets-billing',
    bodyStyle: 'background-color: whiteSmoke; padding: 21px 12px 16px 12px;',

    title: 'Billing',
    items: [{
        xtype: 'container',
        defaults: {
            labelWidth: 130,
            anchor: '100%'
        },
        items: [{
            xtype: 'displayfield',
            name: 'plan',
            fieldLabel: 'Plan'
        }, {
            xtype: 'displayfield',
            fieldLabel: 'Status',
            name: 'status'

        }, {
            xtype: 'displayfield',
            fieldLabel: 'Next charge',
            name: 'nextCharge'

        }, {
            xtype: 'displayfield',
            fieldLabel: '<a href="http://scalr.net/emergency_support/" target="_blank">Emergency support</a>',
            name: 'support',
            listeners: {
                boxready: function() {
                    this.inputEl.on('click', function(e, el) {
                        if (e.getTarget('a.dashed')) {
                            var action = el.getAttribute('type');
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    msg: (action == 'subscribe') ? 'Are you sure want to subscribe to Emergency Support for $300 / month?' : 'Are you sure want to unsubscribe from Emergency Support?'
                                },
                                processBox: {
                                    type: 'action'
                                },
                                params: { action: action },
                                scope: this,
                                url: '/account/billing/xSetEmergSupport/',
                                success: function () {
                                    Scalr.message.Success((action == 'subscribe') ? "You've successfully subscribed to Emergency support" : "You've successfully unsubscribed from emergency support");
                                    this.up('form').loadContent();
                                }
                            });
                        }
                    }, this);
                }
            }
        }]
    }],
    widgetType: 'nonlocal',
    updateForm: function(data) {
        var values = {},
            frm = this.getForm();
        this.data = data;
        values['plan'] = data['productName'] + ' ( ' + data['productPrice'] + ' / month ) [<a href = "#/account/billing/changePlan">Change Plan</a>]';

        switch (data['state']) {
            case 'Subscribed':
                values['status'] = '<span style="color:green;font-weight:bold;">Subscribed</span>'; break;
            case 'Trial':
                values['status'] = '<span style="color:green;font-weight:bold;">Trial</span> (<b>' + data['trialDaysLeft'] + '</b> days left)'; break;
            case 'Unsubscribed':
                values['status'] = '<span style="color:red;font-weight:bold;">Unsubscribed</span> [<a href="#/account/billing/reactivate">Re-activate</a>]'; break;
            case 'Behind on payment':
                values['status'] = '<span style="color:red;font-weight:bold;">Behind on payment</span>'; break;
            default:
                values['status'] = data['state']; break;
        }

        if (data['ccType'])
            values['nextCharge'] = '$' + data['nextAmount'] + ' on ' + data['nextAssessmentAt'] + ' on ' + data['ccType'] + ' ' + data['ccNumber'] + ' [<a href="#/account/billing/updateCreditCard">Change card</a>]';
        else
            values['nextCharge'] = '$' + data['nextAmount'] + ' on ' + (data['nextAssessmentAt'] ? data['nextAssessmentAt'] : 'unknown') + ' [<a href="#/account/billing/updateCreditCard" class="dashed">Set credit card</a>]';


        if (data['emergSupport'] == 'included')
            values['support'] = '<span style="color:green;">Subscribed as part of ' + data['productName'] + ' package</span><a type="" style="display:none;"></a> '+ data['emergPhone'];
        else if (data['emergSupport'] == "enabled")
            values['support'] = '<span style="color:green;">Subscribed</span> ($300 / month) [<a type="unsubscribe" class="dashed">Unsubscribe</a>] ' + data['emergPhone'];
        else
            values['support'] = 'Not subscribed [<a type="subscribe" class="dashed">Subscribe for $300 / month</a>]';

        frm.findField('support').setVisible(data['emergSupport'] == 'included' || data['emergSupport'] == "enabled");
        frm.setValues(values);
    },
    listeners: {
        boxready: function() {
            if (!this.collapsed)
                this.loadContent();
        },
        beforeexpand: function() {
            if (this.rendered)
                this.body.mask();
        },
        expand: function() {
            this.loadContent();
        }
    },
    loadContent: function () {
        if (this.rendered)
            this.body.mask('');

        Scalr.Request({
            url: '/dashboard/widget/billing/xGetContent',
            scope: this,
            success: function (content) {
                if (this.rendered)
                    this.body.unmask();
                this.updateForm(content);
            },
            failure: function () {
                if (this.rendered)
                    this.body.unmask();
            }
        });
    }
});

Ext.define('Scalr.ui.dashboard.Status', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.status',

    title: 'AWS health status',
    params: {},

    items: [{
        xtype: 'gridpanel',
        store: {
            fields: ['img', 'status', 'name', 'message', 'locations', 'EC2', 'RDS', "S3"],
            proxy: 'object'
        },
        columns: [{
            text: 'Location',
            flex: 2,
            dataIndex: 'locations',
            xtype: 'templatecolumn',
            resizable: false,
            tpl: '{locations}'
        }, {
            text: 'EC2',
            xtype: 'templatecolumn',
            sortable: false,
            resizable: false,
            tpl: '<img class="x-grid-icon x-grid-icon-{[values && values.EC2.img==\'normal.png\'?\'ok\':\'notok\']}" src="'+Ext.BLANK_IMAGE_URL+'" data-qtip="{EC2.status}">',
            flex: 1
        }, {
            text: 'RDS',
            xtype: 'templatecolumn',
            sortable: false,
            resizable: false,
            tpl: '<img class="x-grid-icon x-grid-icon-{[values && values.RDS.img==\'normal.png\'?\'ok\':\'notok\']}" src="'+Ext.BLANK_IMAGE_URL+'" data-qtip="{RDS.status}">',
            flex: 1
        }, {
            text: 'S3',
            xtype: 'templatecolumn',
            sortable: false,
            resizable: false,
            tpl: '<img class="x-grid-icon x-grid-icon-{[values && values.S3.img==\'normal.png\'?\'ok\':\'notok\']}" src="'+Ext.BLANK_IMAGE_URL+'" data-qtip="{S3.status}">',
            flex: 1
        }],
        disableSelection: true,
        viewConfig: {
            emptyText: 'No info found',
            deferEmptyText: false,
            getRowClass: function(rec, rowIdx) {
                return rowIdx % 2 == 1 ? 'scalr-ui-dashboard-grid-row' : 'scalr-ui-dashboard-grid-row-alt';
            }
        },
        plugins: {
            ptype: 'gridstore'
        }
    }],
    widgetType: 'nonlocal',
    loadContent: function () {
        var me = this;

        if (this.rendered) {
            this.minHeight = 120;
            this.updateLayout();
            this.body.mask('');
        }

        Scalr.Request({
            url: '/dashboard/widget/status/xGetContent',
            scope: this,
            params: { locations: this.params['locations'] },
            success: function (content) {
                var items = [];
                if (this.isDestroyed)
                    return;

                Ext.each(content['data'], function(item){
                    Ext.Object.each(item, function(prop){
                        // status formatting
                        if (item[prop].status) {
                            var status = item[prop].status.trim();
                            // server tags must be encoded twice, because once they will be decoded by qtip
                            status = Ext.util.Format.htmlEncode(status);
                            // wrap first line in <b> tag in multiline status
                            var statusLines = status.match(/.*\n/g);
                            if (statusLines) {
                                status = status.replace(statusLines[0].trim(), '<b>' + statusLines[0] + '</b>\n');
                            }

                            // remove 'more' word
                            status = status.replace(/\n\s*more\s*\n/g, '\n');

                            // replace new lines with <br> tag
                            status = status.replace(/\n\s*/g, '<br>');

                            // time formatting
                            // if dot + time add <br>
                            var timeWithDot = status.match(/\.\s\d{1,2}:\d{2}\s\w*/g);
                            Ext.each(timeWithDot, function (item) {
                                var formattedItem = item.replace('.', '.<br>');
                                status = status.replace(item, formattedItem);
                            });

                            // wrap time in <b> tag
                            var timeArray = status.match(/\d{1,2}:\d{2}\s\w*/g);
                            Ext.each(timeArray, function (item) {
                                var formattedItem = '<b>' + item + '</b>'
                                status = status.replace(item, formattedItem);
                            });

                            // encode string for qtip
                            status = Ext.util.Format.htmlEncode(status);
                            item[prop].status = status;
                        }
                    });

                    if (item) items.push(item);
                });
                this.child('grid').store.load({
                    data: items
                });

                if (content.locations)
                    me.params['locations'] = content.locations;

                if (this.rendered) {
                    this.minHeight = 0;
                    this.updateLayout();
                    this.body.unmask();
                }

            },
            failure: function () {
                if (this.rendered) {
                    this.minHeight = 0;
                    this.updateLayout();
                    this.body.unmask();
                }
            }
        });
    },

    listeners: {
        boxready: function() {
            this.params = this.params || {};
            if (! this.collapsed)
                this.loadContent();
        },
        beforeexpand: function() {
            if (this.rendered)
                this.body.mask();
        },
        expand: function () {
            this.loadContent();
        }
    },

    addSettingsForm: function () {
        var settingsForm = new Ext.form.FieldSet({
            title: 'Choose location(s) to show',
            items: {
                xtype: 'checkboxgroup',
                columns: 3,
                vertical: true
            }
        });

        var locations = this.params['locations'];
        for (var i in this.locations) {
            settingsForm.down('checkboxgroup').add({
                xtype: 'checkbox',
                boxLabel: i,
                name: 'locations',
                inputValue: i,
                checked: locations.indexOf(i)!=-1 ? true: false,
                listeners: {
                    change: function (checkbox, value) {
                        var group = checkbox.up('checkboxgroup');
                        group.up('#box').down('#buttonOk').setDisabled(
                            Ext.isEmpty(group.getValue().locations)
                        );
                    }
                }
            });
        }
        return settingsForm;
    },

    showSettingsWindow: function () {
        Scalr.Request({
            url: '/dashboard/widget/status/xGetLocations',
            scope: this,
            success: function (locationData) {
                if (locationData['locations']) {
                    this.locations = locationData['locations'];
                    Scalr.Confirm({
                        form: this.locations ? this.addSettingsForm() : {xtype: 'displayfield', value: 'No locations to select'},
                        formWidth: 450,
                        title: 'Settings',
                        padding: 5,
                        success: function (formValues) {
                            if(formValues.locations){
                                var locations = [];
                                if (Ext.isArray(formValues.locations)) {
                                    for(var i = 0; i < formValues.locations.length; i++) {
                                        locations.push(formValues.locations[i]);
                                    }
                                } else
                                    locations.push(formValues.locations);
                                this.params = {'locations': Ext.encode(locations)};
                                this.up('dashpanel').savePanel(0);
                                if (!this.collapsed)
                                    this.loadContent();
                            }
                        },
                        scope: this
                    });
                }
                else {
                    Scalr.Confirm({
                        title: 'No locations',
                        msg: 'No locations to select',
                        type: 'action'
                    });
                }
            },
            failure: function() {
                Scalr.Confirm({
                    title: 'No locations',
                    msg: 'No locations to select',
                    type: 'action'
                });
            }
        });
    }
});
Ext.define('Scalr.ui.dashboard.tutorFarm', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.tutorfarm',

    title: 'Farms',
    items: [{
        xtype: 'panel',
        border: false,
        html:
            '<div style="float: left; width: 55%; padding: 30px 0px 25px 25px; height: 150px;">' +
                '<span class="scalr-ui-dashboard-tutor-message" style="margin-left: 17px;">New to Scalr?</span>' +
                '<br/><br/><span class="scalr-ui-dashboard-tutor-message-big">Create a farm</span>' +
                '</div>' +
                '<a href="#/farms/designer"><div style="float: left; width: 40%; margin-top: 10px; height: 115px; background: url(\'/ui2/images/ui/dashboard/create_farm.png\') no-repeat;" align="center">' +
                '</div></a>' +
                '<div style="width: 5%; float: left; height: 100%; padding-left: 5px;">' +
                '<div class="x-menu-icon-help" style="cursor: pointer; position: absolute; top: 115px;" align="right"></div>' +
                '</div>'
    }, {
        xtype: 'panel',
        margin: '10 0 0 0',
        itemId: 'tutorFarmInfo',
        hidden: true,
        autoScroll: true,
        border: false,
        height: 230,
        html:
            '<div class="scalr-ui-dashboard-tutor-desc"><span class="scalr-ui-dashboard-tutor-title">Farms</span><br/>' +
                '<br/>To create a farm, simply click on this widget or go to <a href="#/farms/designer"> Server Farms > Build New</a>.<br/><br/>' +
                'In Scalr, farms are logical unit that allow you to group a set of configurati on and behavior according to which your servers should behave. With Scalr\'s terminology, farms are simply set of roles.' +
                '<br/><br/><span class="scalr-ui-dashboard-tutor-title">Roles</span><br/>' +
                'Roles are core concepts in Scalr and fundamental components of your architecture.They are images that define the behavior of your servers. As in object-oriented programming, a role is used as a blueprint to create instances of itself.' +
                '<br/><br/><a href="#/farms/designer"><span class="scalr-ui-dashboard-tutor-title">Farm Builder</span></a><br/>' +
                'Start by naming your farm and click on the Role tab. Here, you will be asked to add roles. If you are getting started with Scalr, you should still have a list of pre-made roles ready to be added to your farm. Let us take the example of a classic three-tier web stack. In Scalr, each tier corresponds to a separate role. First comes the load balancing tier that can be added to a farm by clicking the *Add* button on the NGINX load-balancer role. Then comes the application tier. Simply add an Apache+Ubuntu 64bit role to the farm. The same can be done for the database tier by adding a MySQL on Ubuntu 64bit role. In this example a role comprises the operating system and the software that will give the role its specific behavior.' +
                '<br/><br/>Once you’ve added all your roles you will need to configure them. To do so, simply click on the role icon. For more information on all the configurations, please visit our wiki.' +
                '<br/><br/>You might wonder: what exactly does adding these roles to the farm do? Well it does not actually do anything. It simply creates the blueprint from which your farm will be launched. To launch it, simply hit Save at the bottom of the page and Launch in the drop down Options menu.' +
                '</div>'
    }],
    onBoxReady: function () {
        var tutorpanel = this;
        this.body.on('click', function(e, el, obj) {
            if (e.getTarget('div.x-menu-icon-help'))
            {
                if (tutorpanel.down('#tutorFarmInfo').hidden) {
                    tutorpanel.down('#tutorFarmInfo').el.slideIn('t');
                    tutorpanel.down('#tutorFarmInfo').show();
                    tutorpanel.down('#tutorFarmInfo').setHeight(380);
                } else {
                    tutorpanel.down('#tutorFarmInfo').el.slideOut('t', {easing: 'easeOut'});
                    tutorpanel.down('#tutorFarmInfo').hide();
                }
                tutorpanel.up('dashpanel').doLayout();
            }
        });
        this.doLayout();
        this.callParent();
    }
});
Ext.define('Scalr.ui.dashboard.tutorApp', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.tutorapp',

    title: 'Applications',
    items: [{
        xtype: 'panel',
        border: false,
        html:
            '<div style="float: left; width: 58%; padding: 30px 0px 25px 25px; height: 150px;">' +
                '<span class="scalr-ui-dashboard-tutor-message" style="margin-left: 17px;">No app running?</span>' +
                '<br/><br/><span class="scalr-ui-dashboard-tutor-message-big">Deploy your code</span>' +
            '</div>' +
            '<a href="#/dm/applications/view"><div style=" float: left; width: 37%; margin-top: 10px; height: 115px; background: url(\'/ui2/images/ui/dashboard/deploy_code.png\') no-repeat;" align="center">' +
            '</div></a>' +
            '<div style="width: 5%; float: left; height: 100%; padding-left: 5px;">' +
                '<div class="x-menu-icon-help" style="cursor: pointer; position: absolute; top: 115px;" align="right"></div>' +
                '</div>'
    }],
    onBoxReady: function () {
        var tutorpanel = this;
        this.body.on('click', function(e, el, obj) {
            if (e.getTarget('div.x-menu-icon-help'))
            {
                if (tutorpanel.down('#tutorAppInfo').hidden) {
                    tutorpanel.down('#tutorAppInfo').show();
                    tutorpanel.down('#tutorAppInfo').el.slideIn('t');
                    tutorpanel.doLayout();
                    tutorpanel.down('#tutorAppInfo').setHeight(380);
                } else {
                    tutorpanel.down('#tutorAppInfo').el.slideOut('t', {easing: 'easeOut'});
                    tutorpanel.down('#tutorAppInfo').hide();
                    tutorpanel.doLayout();
                }
            }
        });
        this.doLayout();
        this.callParent();
    }
});

Ext.define('Scalr.ui.dashboard.tutorDnsZones', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.tutordns',

    title: 'DNS Zones',
    items: [{
        xtype: 'panel',
        border: false,
        html:
            '<div style="float: left; width: 55%; padding: 30px 0px 25px 25px; height: 150px;">' +
                '<span class="scalr-ui-dashboard-tutor-message">Let us manage your</span>' +
                '<br/><br/><span class="scalr-ui-dashboard-tutor-message-big" style="margin-left: 30px;">DNS zones</span>' +
            '</div>' +
            '<a href="#/dnszones/view"><div style="float: left; width: 40%; margin-top: 10px; height: 115px; background: url(\'/ui2/images/ui/dashboard/dns_zone.png\') no-repeat;" align="center">' +
            '</div></a>'+
            '<div style="width: 5%; float: left; height: 100%; padding-left: 5px;">' +
                '<div class="x-menu-icon-help" style="cursor: pointer; position: absolute; top: 115px;" align="right"></div>' +
                '</div>'
    }, {
        xtype: 'panel',
        margin: '10 0 0 0',
        itemId: 'tutorDnsInfo',
        hidden: true,
        autoScroll: true,
        border: false,
        html:
            '<div class="scalr-ui-dashboard-tutor-desc"><span class="scalr-ui-dashboard-tutor-title">DNS Management</span><br/>' +
                '<br/>Scalr provides an out-of-the-box DNS Management tool. To use it, you\'ll need to log in to your registrar and point your domain to Scalr\'s name servers.' +
                '<br/><br/>Create \'IN NS\' records on nameservers authoritative for your root domain:' +
                '<br/>- beta.yourdomain.com. IN NS ns1.scalr.net.' +
                '<br/>- beta.yourdomain.com. IN NS ns2.scalr.net.' +
                '<br/>- beta.yourdomain.com. IN NS ns3.scalr.net.' +
                '<br/>- beta.yourdomain.com. IN NS ns4.scalr.net.' +
                '<br/>Create \'beta.yourdomain.com\' DNS zone in Scalr and point it to desired farm/role.' +
                '<br/>Wait for DNS cache TTL to expire' +
                '<br/><br/>DNS zones are automatically updated by Scalr to reflect the instances you are currently running.' +
                '</div>'
    }],
    onBoxReady: function () {
        var tutorpanel = this;
        this.body.on('click', function(e, el, obj) {
            if (e.getTarget('div.x-menu-icon-help'))
            {
                if (tutorpanel.down('#tutorDnsInfo').hidden) {
                    tutorpanel.down('#tutorDnsInfo').show();
                    tutorpanel.down('#tutorDnsInfo').el.slideIn('t');
                    tutorpanel.down('#tutorDnsInfo').setHeight(420);
                } else {
                    tutorpanel.down('#tutorDnsInfo').el.slideOut('t', {easing: 'easeOut'});
                    tutorpanel.down('#tutorDnsInfo').hide();
                }
                tutorpanel.up('dashpanel').doLayout();
            }
        });
        this.doLayout();
        this.callParent();
    }
});

Ext.define('Scalr.ui.dashboard.Cloudyn', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.cloudyn',

    itemId: 'cloudyn',
    title: 'Cloud cost efficiency',
    widgetType: 'nonlocal',
    cls: 'scalr-ui-dashboard-widgets-cloudyn',
    items: [{
        xtype: 'panel',
        itemId: 'setup',
        layout: 'anchor',
        bodyStyle: 'padding: 10px',
        style: 'background-color: #F2F7EB; background-image: url(/ui2/images/icons/new.png); background-position: top right; background-repeat: no-repeat;',
        defaults: {
            anchor: '100%'
        },
        items: [{
            xtype: 'component',
            style: 'margin-bottom: 10px',
            html:
                '<div style="text-align: center; height: 44px; width: 100%; margin-bottom: 10px; margin-top: 8px"><img src="/ui2/images/ui/dashboard/cloudyn_logo.png" width=116 height=44 /></div>' +
                '<span style="line-height: 25px;font-family:OpenSansSemiBold">Optimize your cloud spend with actionable reports, and more &mdash; all from within Scalr.</span> <a href="https://www.cloudyn.com" target="_blank">Learn more about Cloudyn.</a>'
        }, {
            xtype: 'checkbox',
            name: 'owner',
            boxLabel: '&nbsp;I agree to Cloudyn\'s <a href="https://www.cloudyn.com/terms-of-use/" target="_blank">terms of service</a>, and for Scalr to share read-only access on my behalf.',
            style: 'margin-bottom: 20px',
            listeners: {
                change: function(field, value) {
                    if (value)
                        this.next('fieldcontainer').child('button').enable();
                    else
                        this.next('fieldcontainer').child('button').disable();
                }
            }
        }, {
            xtype: 'fieldcontainer',
            name: 'owner',
            layout: {
                type: 'vbox',
                align: 'center'
            },
            style: 'margin-bottom: 20px',
            items: [{
                xtype: 'button',
                disabled: true,
                flex: 1,
                width: 220,
                text: 'Start saving now *',
                handler: function() {
                    Scalr.Request({
                        processBox: {
                            type: 'action'
                        },
                        url: '/dashboard/widget/cloudyn/xSetup',
                        scope: this.up('#cloudyn'),
                        success: function(data) {
                            this.updateForm(data);
                        }
                    });
                }
            }]
        }, {
            xtype: 'displayfield',
            name: 'owner',
            anchor: '100%',
            value: '<div style="text-align: center; width: 100%;">*AWS read-only API Credentials will be shared with Cloudyn.</div>'
        }]
    }, {
        xtype: 'grid',
        itemId: 'info',
        hidden: true,
        store: {
            fields: [ 'Metric', 'MetricName', 'DataIsReady', 'CompletionDateTz', 'estimate', 'SpaceBeforeUnit', 'IsPrefixUnit', 'UnitOfMeasurement' ],
            proxy: 'object'
        },
        columns: [{
            header: 'Metric name',
            flex: 1,
            hideable: false,
            sortable: false,
            xtype: 'templatecolumn',
            dataIndex: 'MetricName',
            tpl: '{MetricName}'
        }, {
            header: 'Data',
            flex: 1,
            hideable: false,
            sortable: false,
            dataIndex: 'Metric',
            renderer: function(value, metaData, record) {
                if (record.get('DataIsReady') == 'true') {
                    var s = '<span style="font-weight: bold">';
                    if (record.get('IsPrefixUnit')) {
                        s += record.get('UnitOfMeasurement') + record.get('Metric') + '</span>';
                    } else {
                        s += record.get('Metric') + '</span>' + (record.get('SpaceBeforeUnit') == 1 ? ' ' : '') + record.get('UnitOfMeasurement');
                    }
                    return s;
                } else {
                    metaData.tdCls = 'border';
                    if (record.get('estimate'))
                        return 'Available ' + record.get('estimate');
                    else
                        return 'Data is not ready';
                }
            }
        }],
        disableSelection: true,
        viewConfig: {
            emptyText: 'No metrics found',
            deferEmptyText: false
        },
        dockedItems: [{
            xtype: 'component',
            dock: 'bottom',
            style: 'line-height: 40px; text-align: center; background-color: white;',
            height: 40,
            itemId: 'icon',
            html: '&nbsp;'
        }]
    }],
    updateForm: function(content) {
        var setup = this.down('#setup'), info = this.down('#info');
        if (content.enabled) {
            setup.hide();
            info.show();
            if (content.owner) {
                info.down('#icon').update('<img src="/ui2/images/ui/dashboard/cloudyn_icon.png" style="float:center; vertical-align: middle; padding-right: 10px;"><a target="_blank" style="font-weight: bold;" href="' + content['consoleUrl'] + '">See more details ...</a>');
                info.down('#icon').show();
            } else {
                info.down('#icon').hide();
            }
            info.store.loadData(content.metrics);
        } else {
            info.hide();
            setup.show();
            Ext.each(setup.query('[name="owner"]'), function() {
                if (content.owner)
                    this.show();
                else
                    this.hide();
            });
        }
    },
    listeners: {
        boxready: function() {
            if (!this.collapsed)
                this.loadContent();
        },
        beforeexpand: function() {
            if (this.rendered)
                this.body.mask();
        },
        expand: function() {
            this.loadContent();
        }
    },
    loadContent: function () {
        if (this.rendered)
            this.body.mask('');

        Scalr.Request({
            url: '/dashboard/widget/cloudyn/xGetContent',
            scope: this,
            success: function (content) {
                if (this.rendered)
                    this.body.unmask();
                this.updateForm(content);
            },
            failure: function () {
                if (this.rendered)
                    this.body.unmask();
            }
        });
    }
});

Ext.define('Scalr.ui.dashboard.Addfarm', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.addfarm',

    title: 'Create new Farm',
    widgetType: 'local',
    cls: 'scalr-ui-dashboard-widgets-addfarm',
    items: [{
        xtype: 'form',
        layout: 'anchor',
        defaults: {
            anchor: '100%',
            labelWidth: 70
        },
        bodyCls: 'x-container-fieldset',
        items: [{
            xtype: 'textfield',
            name: 'name',
            fieldLabel: 'Name',
            allowBlank: false,
            selectOnFocus: true,
            refreshFarmName: function() {
                var farmIndex = 1;
                if (Scalr.farms.length) {
                    farmIndex = Scalr.farms.length + 1;
                }
                this.setValue('My Farm #' + farmIndex);
            }
        },{
            xtype: 'combo',
            store: {
                fields: [ 'projectId', 'name', {name: 'budgetRemain', defaultValue: null}, {name: 'description', convert: function(v, record) {
                    return record.data.name + '  (' + (Ext.isEmpty(record.data.budgetRemain) ? 'budget is not set' : 'Remaining budget ' + Ext.util.Format.currency(record.data.budgetRemain)) + ')';
                }} ],
                proxy: 'object',
                sorters: [{
                    property: 'name',
                    transform: function(value){
                        return value.toLowerCase();
                    }
                }]
            },
            flex: 1,
            editable: true,
            selectOnFocus: true,
            restoreValueOnBlur: true,
            queryMode: 'local',
            anyMatch: true,
            autoSetSingleValue: true,
            valueField: 'projectId',
            displayField: 'description',
            fieldLabel: 'Project',
            name: 'projectId',
            allowBlank: false,
            hidden: !Scalr.flags['analyticsEnabled'],
            disabled: !Scalr.flags['analyticsEnabled'],
            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/analytics/projects/add'
            }],
            listConfig: {
                cls: 'x-boundlist-alt',
                tpl:
                    '<tpl for=".">' +
                        '<div class="x-boundlist-item" style="height: auto; width: auto; max-width: 900px;">' +
                            '<div><span class="x-semibold">{name}</span>' +
                                '&nbsp;&nbsp;<span style=" font-size: 11px;"><tpl if="budgetRemain!==null">Remaining budget {[this.currency2(values.budgetRemain)]}<tpl else><i>Budget is not set</i></tpl></span>' +
                            '</div>' +
                        '</div>' +
                    '</tpl>'
            }
        },{
            xtype: 'container',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            margin: '18 0 0',
            items: [{
                xtype: 'button',
                text: 'Create new Farm',
                handler: function() {
                    var form = this.up('form').getForm();
                    if (form.isValid()) {
                        Scalr.event.fireEvent('redirect', '#/farms/designer', false, Ext.apply({roleId: 'new'}, {farm: form.getValues()}));
                        form.reset();
                        form.findField('name').refreshFarmName();
                    }
                }
            }],
        }]
    },{
        xtype: 'component',
        cls: 'x-grid-empty x-error',
        itemId: 'errorMsg',
        hidden: true
    }],
    widgetUpdate: function(content) {
        var form = this.down('form').getForm(),
            projectId = form.findField('projectId');
        if (Scalr.flags['analyticsEnabled']) {
            projectId.getStore().load({data: content.projects});
            projectId.findPlugin('comboaddnew').setDisabled(!Scalr.isAllowed('ANALYTICS_PROJECTS_ACCOUNT', 'create') || content.costCenterLocked == 1);
            form.reset();
        }
        form.findField('name').refreshFarmName();
        this.down('form').show();
        this.down('#errorMsg').hide();
    },
    widgetError: function (msg) {console.log(msg)
        this.down('form').hide();
        this.down('#errorMsg').show().update(msg);
    }
});

Ext.define('Scalr.ui.dashboard.NewUser', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.newuser',

    cls: 'x-form-dashboard-checklist',
    title: 'New user checklist',
    widgetType: 'local',
    widgetUpdate: function (content) {
        this.removeAll();

        var i, j, items = [], ch, c, cn;
        for (i = 0; i < content.length; i++) {
            ch = [];
            for (j = 0; j < content[i]['items'].length; j++) {
                c = content[i]['items'][j];
                cn = {
                    xtype: 'displayfield',
                    value:
                        (('status' in c) ? '<div style="margin-right: 10px" class="x-grid-icon x-grid-icon-simple x-grid-icon-' + (c['status'] ? 'ok' : 'gray-ok') + '"></div>  ' : '') +
                            (c['href'] ? '<a href="' + c['href'] + '">' : '') + c['text'] + (c['href'] ? '</a>' : '')
                };

                if (c['info']) {
                    cn['plugins'] = [{
                        ptype: 'fieldicons',
                        align: 'right',
                        icons: {
                            id: 'info',
                            tooltip: c['info']
                        }
                    }];
                }

                ch.push(cn);
            }
            items.push({
                xtype: 'fieldset',
                title: '<img src="'+Ext.BLANK_IMAGE_URL+'" class="scalr-ui-dashboard-checklist-icon scalr-ui-dashboard-checklist-icon-'+i+'"/><span style="margin-left:48px">' + content[i]['title'] + '</span>',
                defaults: {
                    margin: '0 0 0 48'
                },
                items: ch
            });
        }

        this.add({
            xtype: 'container',
            items: items
        });
    },

    widgetError: function(message) {
        this.removeAll();

        this.add({
            xtype: 'component',
            html: '<div class="x-grid-empty x-error">' + message + '</div>'
        });
    }
});

Ext.define('Scalr.ui.dashboard.CostAnalytics', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.costanalytics',

    title: 'Cost Analytics',
    cls: 'scalr-ui-dashboard-widgets-costanalytics',
    autoScroll: true,
    minHeight: 120,
    updateTimeout: 3600000,//1 hour
    items: {
        xtype: 'gridpanel',
        border: false,
        store: {
            fields: ['id',  'name', 'cost', 'growth', 'growthPct'],
            proxy: 'object',
            sorters: {
                property: 'cost',
                direction: 'DESC'
            }
        },
        columns: [{
            text: 'Farm',
            xtype: 'templatecolumn',
            dataIndex: 'name',
            flex: 1,
            tpl: '<a href="#/analytics/farms?farmId={id}">{name}</a>'
        },{
            text: 'This month',
            dataIndex: 'cost',
            xtype: 'templatecolumn',
            flex: .8,
            tpl: '{[this.currency2(values.cost)]}'
        },{
            text: 'Growth',
            dataIndex: 'costPct',
            sortable: false,
            resizable: false,
            width: 110,
            align: 'center',
            xtype: 'templatecolumn',
            tpl: '<tpl if="growth!=0">' +
                    ' &nbsp;{[this.pctLabel(values.growth, values.growthPct, \'small\', \'fixed\')]}' +
                 '<tpl else>&mdash;' +
                 '</tpl>'
        }],
        disableSelection: true,
        viewConfig: {
            emptyText: 'No cost data',
            deferEmptyText: false
        },
        plugins: {
            ptype: 'gridstore'
        }
    },
    onBoxReady: function () {
        if (!this.params || !this.params['farmCount'])
            this.params = {'farmCount': 5};
        this.callParent();
    },
    widgetType: 'local',
    widgetUpdate: function (content) {
        var grid = this.down('grid');
        grid.view.emptyText = '<div class="x-grid-empty">No cost data</div>';
        grid.store.load({
            data: content['farms']
        });
        grid.getView().headerCt.setHeight(content['farms'].length ? null : 0);
        this.lastUpdateTime = (new Date()).getTime();
    },
    widgetError: function (msg) {
        var grid = this.down('grid');
        grid.getView().headerCt.setHeight(0);
        grid.view.emptyText = '<div class="x-grid-empty x-error">' + msg + '</div>';
    },
    showSettingsWindow: function () {
        if (!this.params || !this.params['farmCount'])
            this.params = {farmCount: 5};
        Scalr.Confirm({
            formSimple: true,
            form: [{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                fieldLabel: 'Show top',
                labelWidth: 70,
                items: [{
                    xtype: 'combo',
                    margin: '0 6',
                    width: 60,
                    store: [1, 2, 5, 10, 15, 20],
                    editable: false,
                    value: this.params['farmCount'],
                    name: 'farmCount'
                },{
                    xtype: 'label',
                    cls: 'x-form-item-label-default',
                    text: 'farms'

                }]
            }],
            title: 'Settings',
            success: function (data) {
                if (data['farmCount']) {
                    this.params['farmCount'] = data['farmCount'];
                    this.up('dashpanel').savePanel(1);
                }
            },
            scope: this
        });
    }
});


Ext.define('Scalr.ui.dashboard.Environments', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.environments',

    title: 'Environments in this account',
    cls: 'scalr-ui-dashboard-widgets-environments',
    autoScroll: true,
    items: {
        xtype: 'gridpanel',
        border: false,
        store: {
            fields: [
                'id',
                'name',
                {name: 'farmsCount', type: 'int'},
                {name: 'serversCount', type: 'int'}
            ],
            proxy: 'object',
            sorters: {
                property: 'name',
                direction: 'ASC'
            }
        },
        columns: [{
            text: 'Environment',
            xtype: 'templatecolumn',
            dataIndex: 'name',
            flex: 1,
            tpl: '<a href="#?environmentId={id}/dashboard" data-qtip="Switch to {name:htmlEncode}">{name}</a>'
        },{
            text: 'Farms',
            dataIndex: 'farmsCount',
            tdCls: 'x-grid-big-href',
            align: 'center',
            width: 90
        },{
            text: 'Servers',
            dataIndex: 'serversCount',
            tdCls: 'x-grid-big-href',
            align: 'center',
            width: 90
        },{
            xtype: 'templatecolumn',
            sortable: false,
            resizeable: false,
            align: 'center',
            width: 50,
            tpl: '<a href="#?environmentId={id}/dashboard" data-qtip="Switch to {name:htmlEncode}" ><img src="'+Ext.BLANK_IMAGE_URL+'" class="x-grid-icon x-grid-icon-login" /></a>'
        }],
        disableSelection: true,
        viewConfig: {
            emptyText: 'No environments in this account',
            deferEmptyText: false
        },
        plugins: {
            ptype: 'gridstore'
        }
    },
    onBoxReady: function () {
        if (!this.params || !this.params['farmCount'])
            this.params = {'farmCount': 5};
        this.callParent();
    },
    widgetType: 'local',
    widgetUpdate: function (content) {
        var grid = this.down('grid');
        grid.view.emptyText = '<div class="x-grid-empty">No environments in this account</div>';
        grid.store.load({
            data: content['environments']
        });
        grid.getView().headerCt.setHeight(content['environments'].length ? null : 0);
        this.lastUpdateTime = (new Date()).getTime();
    },
    widgetError: function (msg) {
        var grid = this.down('grid');
        grid.getView().headerCt.setHeight(0);
        grid.view.emptyText = '<div class="x-grid-empty x-error">' + msg + '</div>';
    }
});

Ext.define('Scalr.ui.dashboard.GettingStarted', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.gettingstarted',

    title: 'First Steps',

    widgetType: 'local',
    widgetUpdate: Ext.emptyFn,
    widgetError: Ext.emptyFn,

    items: {
        xtype: 'container',
        bodyStyle: 'font-size: 11px; body-padding: 4px; overflow-y: auto;',
        items: [{
            xtype: 'dataview',
            store: {
                fields: [ 'href', 'name' ],
                proxy: 'object',
                data: [
                    {href: 'https://scalr-wiki.atlassian.net/wiki/x/igAeAQ', name: 'First Steps - Login as an administrator'},
                    {href: 'https://scalr-wiki.atlassian.net/wiki/x/iAAeAQ', name: 'First Steps - Create a new user'},
                    {href: 'https://scalr-wiki.atlassian.net/wiki/x/kgAeAQ', name: 'First Steps - Add Cloud Credentials'},
                    {href: 'https://scalr-wiki.atlassian.net/wiki/x/ngAeAQ', name: 'First Steps - Add Images and Roles'}
                ]
            },
            loadMask: false,
            itemSelector: 'div.scalr-ui-dashboard-widgets-div',
            tpl: [
                '<h2 style="text-align: center">Did you just deploy your new Scalr install?<br/>Follow these instructions to get started.</h2><p><ul>',
                '<tpl for=".">',
                '<li><h3><a href="{href}" target="_blank">{name}</a></h3></li>',
                '</tpl>',
                '</p></ul>'
            ]
        }]
    }
});

Ext.define('Scalr.ui.dashboard.ScalrHealth', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.dashboard.scalrhealth',

    title: 'SCALR HEALTH',

    widgetType: 'local',
    widgetUpdate: function (content) {
        var errPanel = this.down('#error');

        if (errPanel.isVisible()) {
            errPanel.hide();
        }

        this.query('#hosts, #services').forEach(function (grid) {
            var data = content[grid.itemId] || [];

            grid.store.load({data: data});
            grid.getView().headerCt.setHeight(data.length ? null : 0);
            if (!grid.isVisible()) {
                grid.show();
            }

            // hide optionscolumn if only one host in table
            if (grid.itemId === 'hosts') {
                var optionscolumn = grid.down('optionscolumn');

                if (data.length <= 1) {
                    optionscolumn.hide();
                } else {
                    optionscolumn.show();
                }
            }
        });
        this.lastUpdateTime = (new Date()).getTime();
    },
    widgetError: function (msg) {
        this.query('#hosts, #services').forEach(function (grid) {
            grid.hide();
        });
        var errPanel = this.down('#error');
        errPanel.update('<div class="x-grid-empty x-error">' + msg + '</div>');
        errPanel.show();
    },

    items: {
        xtype: 'container',
        defaults: {
            xtype: 'grid',
            header: {style: 'background-color:#f1f5fa'},
            disableSelection: true,
            enableColumnHide: false,
            enableColumnMove: false
        },
        items: [{
            xtype: 'panel',
            itemId: 'error',
            header: false,
            hidden: true
        }, {
            title: 'HOSTS',
            itemId: 'hosts',

            store: {
                fields: ['host', 'version', 'edition', 'revision', 'revDate'],
                proxy: 'object'
            },
            columns: [{
                text: 'Host Name',
                dataIndex: 'host',
                sortable: true,
                flex: 2
            }, {
                text: 'Version',
                xtype: 'templatecolumn',
                dataIndex: 'id',
                tpl: [
                    '{version} ({edition})',
                    '<tpl if="revision">',
                        ' Rev: {revision}',
                    '</tpl>',
                    '<tpl if="revDate">',
                        ' ({revDate})',
                    '</tpl>'
                ],
                sortable: false,
                flex: 4
            }, {
                xtype: 'optionscolumn',
                menu: [{
                    iconCls: 'x-menu-icon-delete',
                    text: 'Remove host from widget',
                    showAsQuickAction: true,
                    request: {
                        confirmBox: {
                            type: 'delete',
                            msg: 'Are you sure want to remove host "{host}" ?'
                        },
                        processBox: {
                            type: 'delete'
                        },
                        url: '/dashboard/widget/scalrhealth/xRemove',
                        dataHandler: function (data) {
                            return {hostName: data['host']};
                        },
                        success: function (data) {
                            var store = this.up('grid').getStore();
                            var record = store.findRecord('host', data.host);

                            store.remove(record);

                            if (store.count() <= 1) {
                                this.hide();
                            }
                        }
                    }
                }]
            }]
        }, {
            title: 'SERVICES',
            itemId: 'services',

            store: {
                fields: [
                    'name',
                    {name: 'numWorkers', type: 'int'},
                    {name: 'numTasks', type: 'int'},
                    'lastStart',
                    'timeSpent',
                    {
                        name: 'state', convert: function (val) {
                        return val || 'unknown';
                    }
                    }
                ],
                proxy: 'object'
            },
            columns: [{
                text: 'Service',
                dataIndex: 'name',
                sortable: true,
                width: 220
            }, {
                text: 'Tasks / Workers',
                dataIndex: 'numTasks',
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate(
                    '<tpl if="values.numWorkers || values.numTasks">',
                    '<span data-anchor="left" data-qalign="l-r" data-qtip="{[this.getTooltipHtml(values)]}" data-qwidth="360">',
                        '<span style="color:#1e90ff">{[values.numTasks]}</span>',
                        '/<span style="color:#ffa500">{[values.numWorkers]}</span>',
                    '</span>',
                    '</tpl>',
                    {
                        disableFormats: true,
                        getTooltipHtml: function (values) {
                            return Ext.String.htmlEncode(
                                '<span style="color:#1e90ff;">' + values.numTasks + '</span> &ndash; Tasks in the queue<br/>' +
                                '<span style="color:#ffa500;">' + values.numWorkers + '</span> &ndash; Workers have been used into working cycle'
                            );
                        }
                    }
                ),
                align: 'center',
                sortable: false,
                width: 120
            }, {
                text: 'Last run',
                dataIndex: 'lastStart',
                sortable: false,
                flex: 1
            }, {
                text: 'Time spent',
                dataIndex: 'timeSpent',
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate(
                    '<tpl if="timeSpent != null">{[this.getInterval(values.timeSpent)]}</tpl>',
                    {
                        disableFormats: true,
                        /**
                         * Convert interval in seconds if necessary to minutes, hours or days.
                         * Truncate result and add appropriate suffix.
                         * Convert to
                         *  - days if &gt;= 86400, suffix ' d'
                         *  - hours if &gt;= 3600, suffix ' h'
                         *  - minutes if &gt;= 60, suffix ' min'
                         *  - seconds if &lt; 60, suffix ' sec'
                         *  If is impossible to convert input parameter into integer, or converted value
                         *  less than 0 - return empty string.
                         *  If input parameter converts to 0 - return `1 sec`.
                         *
                         * @param sec {string|number} interval in seconds
                         * @return {string} converted value with appropriate suffix
                         */
                        getInterval: function (sec) {
                            var suffix = ' sec', d;

                            sec = parseInt(sec, 10);
                            if (isNaN(sec) || !isFinite(sec) || sec < 0) {
                                return '';
                            }

                            if (sec >= 86400) {
                                d = 86400;
                                suffix = ' d';
                            } else if (sec >= 3600) {
                                d = 3600;
                                suffix = ' h';
                            } else if (sec >= 60) {
                                d = 60;
                                suffix = ' min';
                            } else if (sec == 0) {
                                sec = 1;
                            }

                            return (d ? parseInt(sec / d) : sec) + suffix;
                        }
                    }
                ),
                sortable: false,
                width: 90
            }, {
                text: 'State',
                dataIndex: 'state',
                xtype: 'statuscolumn',
                statustype: 'service',
                sortable: false,
                width: 100,
                minWidth: 100

            }]
        }]
    }
});

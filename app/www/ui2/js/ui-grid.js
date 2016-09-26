Ext.define('Scalr.ui.data.proxy.Paging', {
    extend: 'Ext.data.proxy.Ajax',
    alias: 'proxy.scalr.paging',

    reader: {
        type: 'json',
        rootProperty: 'data',
        totalProperty: 'total',
        successProperty: 'success'
    }
});

//extjs5 ready
Ext.define('Scalr.ui.StoreReaderObject', {
    extend: 'Ext.data.reader.Json',
    alias: 'reader.object',

    // For Object Reader, methods in the base which use these properties must not see the defaults
    config: {
        totalProperty: undefined,
        successProperty: undefined,
        idFieldFromIndex: false,
        transform: function(data) {
            var me = this, i,
                result = [];
            for (i in data) {
                if (Ext.isString(data[i]) || Ext.isNumber(data[i])) {
                    result[result.length] = {id: i, name: data[i]}; // format id => name
                } else {
                    result[result.length] = data[i];
                    if (me.getIdFieldFromIndex() && data[i]['id'] === undefined) {
                        data[i]['id'] = i;
                    }
                }
            }
            return result;
        }
    }
});

//extjs5 ready
Ext.define('Scalr.ui.StoreProxyObject', {
    extend: 'Ext.data.proxy.Memory',
    alias: 'proxy.object',

    config: {
        reader: {
            type: 'object'
        }
    },

    read: function(operation) {
        if (Ext.isDefined(operation.config.data)) {
            this.setData(operation.config.data);
        }
        this.callParent(arguments);
    }
});

Ext.define('Scalr.ui.PagingToolbar', {
    extend: 'Ext.PagingToolbar',
    alias: 'widget.scalrpagingtoolbar',

    pageSizes: [10, 15, 25, 50, 100],
    pageSizeMessage: '{0} items per page',
    pageSizeStorageName: 'grid-ui-page-size',
    autoRefresh: 0,
    autoRefreshTask: 0,
    //height: 41,
    prependButtons: true,
    beforeItems: [],
    afterItems: [],
    calculatePageSize: true,
    enableParamsCapture: true,

    checkRefreshHandler: function (item, enabled) {
        if (enabled) {
            this.autoRefresh = item.autoRefresh;
            this.gridContainer.autoRefresh = this.autoRefresh;
            this.gridContainer.saveState();
            if (this.autoRefresh) {
                this.setDelayedRefresh();
                this.down('#refresh').setIconCls('x-btn-icon-autorefresh');
            } else {
                this.clearDelayedRefresh();
                this.down('#refresh').setIconCls('x-btn-icon-refresh');
            }
        }
    },

    getPagingItems: function() {
        var me = this, items = [ '->' ];

        if (this.beforeItems.length) {
            for (var i = 0; i < this.beforeItems.length; i++)
                this.beforeItems[i]['margin'] = '0 12 0 0';

            items = Ext.Array.push(items, this.beforeItems);
        }

        items = Ext.Array.merge(items, [{
            itemId: 'refresh',
            //	tooltip: me.refreshText,
            overflowText: me.refreshText,
            iconCls: 'x-btn-icon-refresh',
            //ui: 'paging',
            handler: me.doRefresh,
            scope: me,
            margin: '0 12 0 0'
        }, {
            itemId: 'settings',
            //	tooltip: me.refreshText,
            overflowText: 'Settings',
            iconCls: 'x-btn-icon-settings',
            //ui: 'paging',
            handler: me.showSettings,
            scope: me,
            margin: '0 3 0 0'
        }, {
            itemId: 'first',
            //tooltip: me.firstText,
            overflowText: me.firstText,
            iconCls: Ext.baseCSSPrefix + 'tbar-page-first',
            ui: 'paging',
            disabled: true,
            handler: me.moveFirst,
            scope: me,
            margin: '0 6 0 0'
        },{
            itemId: 'prev',
            //tooltip: me.prevText,
            overflowText: me.prevText,
            iconCls: Ext.baseCSSPrefix + 'tbar-page-prev',
            ui: 'paging',
            disabled: true,
            handler: me.movePrevious,
            scope: me,
            margin: '0 6 0 0'
        }, me.beforePageText, {
            xtype: 'textfield',
            itemId: 'inputItem',
            name: 'inputItem',
            cls: Ext.baseCSSPrefix + 'tbar-page-number',
            maskRe: /[0123456789]/,
            minValue: 1,
            enableKeyEvents: true,
            selectOnFocus: true,
            submitValue: false,
            // mark it as not a field so the form will not catch it when getting fields
            isFormField: false,
            width: 40,
            margin: '0 4',
            listeners: {
                scope: me,
                keydown: me.onPagingKeyDown,
                blur: me.onPagingBlur
            }
        },{
            xtype: 'tbtext',
            itemId: 'afterTextItem',
            text: Ext.String.format(me.afterPageText, 1),
            margin: '0 5 0 0'
        }, {
            itemId: 'next',
            //tooltip: me.nextText,
            overflowText: me.nextText,
            iconCls: Ext.baseCSSPrefix + 'tbar-page-next',
            ui: 'paging',
            disabled: true,
            handler: me.moveNext,
            scope: me,
            margin: '0 5 0 0'
        },{
            itemId: 'last',
            //	tooltip: me.lastText,
            overflowText: me.lastText,
            iconCls: Ext.baseCSSPrefix + 'tbar-page-last',
            ui: 'paging',
            disabled: true,
            handler: me.moveLast,
            scope: me,
            margin: '0 5 0 0'
        }]);

        if (this.afterItems.length) {
            for (var i = 0; i < this.afterItems.length; i++)
                this.afterItems[i]['margin'] = '0 12 0 0';

            this.afterItems[0]['margin'] = '0 12 0 4';
            this.afterItems[this.afterItems.length - 1]['margin'] = '0 0 0 0';
            items = Ext.Array.push(items, this.afterItems);
        }

        return items;
    },

    evaluatePageSize: function() {
        return Math.floor((Scalr.application.getHeight()-100) / 31);
    },

    getPageSize: function() {
        var pageSize = 0;
        if (Ext.state.Manager.get(this.pageSizeStorageName, 'auto') != 'auto') {
            pageSize = Ext.state.Manager.get(this.pageSizeStorageName, 'auto');
        } else {
            pageSize = this.evaluatePageSize();
        }
        return pageSize;
    },

    setPageSizeAndLoad: function () {
        var me = this;

        var grid = me.gridContainer;

        if (me.calculatePageSize) {
            var store = grid.getStore();
            store.setPageSize(me.getPageSize());

            var data = me.data;

            if (Ext.isObject(data)) {
                // debug message: is it still used ?
                Scalr.utils.PostError({
                    file: 'scalrpagingtoolbar (using data)',
                    message: Ext.encode(data),
                    url: document.location.href
                });

                store.loadData(data.data);
                store.totalCount = data.total;
            }
        }

        return me;
    },

    doRefresh : function(){
        var me = this,
            current = me.store.currentPage;

        if (me.fireEvent('beforechange', me, current) !== false) {
            me.store.gridHightlightNew = true;
            me.store.loadPage(current);
        }
    },

    showSettings: function() {
        var columnsFieldset = new Ext.form.FieldSet({
            title: 'Grid columns to show'
        });
        var checkboxGroup = columnsFieldset.add({
            xtype: 'checkboxgroup',
            columns: 2,
            vertical: true
        });

        var grid = this.gridContainer,
            columns = grid.columns;

        for (var i in columns) {
            if (columns[i].hideable) {
                checkboxGroup.add({
                    xtype: 'checkbox',
                    boxLabel: columns[i].text,
                    checked: !columns[i].hidden,
                    name: Ext.util.Format.stripTags(columns[i].text),
                    inputValue: 1
                });
            }
        }

        var settingsFieldset = new Ext.form.FieldSet({
            layout: 'hbox',
            items: [{
                xtype: 'button',
                flex: 1,
                text: '<span style="font-weight:normal">Reset columns width</span>',
                handler: function(){
                    grid.suspendLayouts();
                    for (var i=0, len=columns.length; i<len; i++) {
                        if (columns[i].initialConfig.flex) {
                            columns[i].flex = columns[i].initialConfig.flex;
                            if (columns[i].width) {
                                delete columns[i].width;
                            }
                        } else if (columns[i].initialConfig.width) {
                            columns[i].width = columns[i].initialConfig.width;
                        }
                    }
                    grid.resumeLayouts(true);
                }
            }, {
                xtype: 'buttonfield',
                flex: 1,
                margin: '0 0 0 10',
                text: 'Autorefresh',
                name: 'autoRefresh',
                enableToggle: true,
                value: !!this.autoRefresh
            }]
        });

        Scalr.Confirm({
            formWidth: 450,
            form: [columnsFieldset, settingsFieldset],
            success: function (data) {
                for (var i in columns) {
                    if (data[columns[i].text])
                        columns[i].show();
                    if (!data[columns[i].text] && columns[i].hideable)
                        columns[i].hide();
                }
                grid.fireEvent('resize');

                if (data['autoRefresh'])
                    this.checkRefreshHandler({autoRefresh: 60 }, true);
                else
                    this.checkRefreshHandler({autoRefresh: 0}, true);
            },
            scope: this
        });
    },

    moveNext : function(){
        var me = this,
            total = me.getPageData().pageCount,
            next = me.store.currentPage + 1;

        if (me.store.currentPage == 1 && me.store.pageSize != me.getPageSize() && me.calculatePageSize) {
            // if page has less records, that it could include, load more records per page
            if (me.fireEvent('beforechange', me, next) !== false) {
                me.store.pageSize = me.evaluatePageSize();
                me.store.load();
            }
        } else if (next <= total) {
            if (me.fireEvent('beforechange', me, next) !== false) {
                me.store.nextPage();
            }
        }
    },

    initComponent: function () {
        this.callParent();

        this.on('added', function (comp, container) {
            this.gridContainer = container;

            // TODO: on back to page event, refresh grid WITH gridHightlightNew
            this.refreshHandler = Ext.Function.bind(function () {
                this.store.gridHightlightNew = true;
                this.store.load();
            }, this.gridContainer);

            this.gridContainer.on('beforeactivate', function () {
                if (this.store.pageSize != this.getPageSize() || !this.data) {
                    this.setPageSizeAndLoad();
                }
                if (this.autoRefresh) {
                    this.setDelayedRefresh();
                }
            }, this);

            this.gridContainer.on('deactivate', function () {
                this.clearDelayedRefresh();
            }, this);

            this.gridContainer.store.on('load', function () {
                if (this.autoRefreshTask) {
                    this.clearDelayedRefresh();
                    if (this.autoRefresh) {
                        this.setDelayedRefresh();
                    }
                }
            }, this);

            this.gridContainer.on('staterestore', function(comp) {
                this.autoRefresh = comp.autoRefresh || 0;
                if (this.autoRefresh) {
                    this.down('#refresh').setIconCls('x-btn-icon-autorefresh');
                }
            }, this);
        });
    },
    setDelayedRefresh: function() {
        this.clearDelayedRefresh();
        this.autoRefreshTask = setTimeout(this.refreshHandler, this.autoRefresh * 1000);

    },
    clearDelayedRefresh: function() {
        if (this.autoRefreshTask) {
            clearTimeout(this.autoRefreshTask);
            this.autoRefreshTask = 0;
        }
    },
    onLoad : function(){
        //fix current page
        if (this.store.currentPage > Math.ceil(this.store.getTotalCount() / this.store.pageSize)) {
            this.gridContainer.store.currentPage = 1;
        }
        this.callParent(arguments);
    }
});

Ext.define('Scalr.ui.ToolbarCloudLocation', {
    extend: 'Ext.form.field.ComboBox',
    alias: 'widget.fieldcloudlocation',

    localParamName: 'grid-ui-default-cloud-location',
    fieldLabel: 'Location',
    labelWidth: 65,
    width: 358,
    matchFieldWidth: false,
    listConfig: {
        width: 'auto',
        minWidth: 300
    },
    iconCls: 'no-icon',
    displayField: 'name',
    valueField: 'id',
    editable: false,
    queryMode: 'local',
    setCloudLocation: function () {
        if (this.store.getCount() == 0) {
            Scalr.message.ErrorTip('Location\'s list is empty', this);
            this.disable();
            return;
        }

        if (this.cloudLocation) {
            this.setValue(this.cloudLocation);
        } else {
            var cloudLocation = Ext.state.Manager.get(this.localParamName);
            if (cloudLocation) {
                var ind = this.store.find('id', cloudLocation);
                if (ind != -1)
                    this.setValue(cloudLocation);
                else
                    this.setValue(this.store.getAt(0).get('id'));
            } else {
                this.setValue(this.store.getAt(0).get('id'));
            }
        }
        this.gridStore.proxy.extraParams.cloudLocation = this.getValue();
    },
    listeners: {
        change: function () {
            if (! this.getValue())
                this.setCloudLocation();
        },
        select: function () {
            Ext.state.Manager.set(this.localParamName, this.getValue());
            this.gridStore.proxy.extraParams.cloudLocation = this.getValue();
            this.gridStore.loadPage(1);
        },
        added: function () {
            this.setCloudLocation();
        }
    }
});

Ext.define('Scalr.ui.GridRadioColumn', {
    extend: 'Ext.grid.column.Column',
    alias: ['widget.radiocolumn'],

    initComponent: function(){
        var me = this;
        me.hasCustomRenderer = true;
        me.callParent(arguments);
    },
    width: 35,

    processEvent: function(type, view, cell, recordIndex, cellIndex, e, record) {
        var me = this;
        if (type == 'click' && e.getTarget('input.x-form-radio')) {
            view.store.each(function(r) {
                r.set(me.dataIndex, false);
            })
            record.set(me.dataIndex, true);
        }
        return this.callParent(arguments);
    },

    defaultRenderer: function(value, meta, record) {
        var result = '<div ';
        if (value)
            result += 'class="x-form-cb-checked" '
        result += 'style="text-align: center" ><input type="button" class="x-form-field x-form-radio" style="border:0" /></div>';

        return result;
    }
});

Ext.define('Scalr.ui.ActionsMenu', {
    extend: 'Ext.menu.Menu',
    alias: 'widget.actionsmenu',
    cls: 'x-options-menu',
    fillEmptyIcons: false, // when icon is hidden, show empty space
    constructor: function () {
        this.callParent(arguments);
        this.linkTplsCache = {};
    },

    onClick: function(e) {
        var me = this,
            type = e.type,
            item,
            clickResult,
            iskeyEvent = type === 'keydown';

        if (me.disabled) {
            e.stopEvent();
            return;
        }

        item = me.getItemFromEvent(e);
        if (item && item.isMenuItem) {
            Scalr.ui.ActionsMenu.processEvent(me.up('optionscolumn') || me, item, me.data, e);
        }

        me.callParent(arguments);
    },

    setData: function(data) {
        var me = this,
            prevSeparator,
            display;
        me.data = data;
        me.visibleItemsCount = 0;

        this.items.each(function (item) {
            display = Ext.isFunction(item.getVisibility) ? item.getVisibility(me.data) : true;
            if (display) {//prevent double separators
                if (item.xtype === 'menuseparator') {
                    display = prevSeparator === undefined;
                    prevSeparator = display ? item : prevSeparator;
                } else {
                    prevSeparator = undefined;
                    me.visibleItemsCount++;
                }
            }

            item[display ? 'show' : 'hide']();
            if (display && item.href) {
                // Update item link
                if (! this.linkTplsCache[item.id]) {
                    this.linkTplsCache[item.id] = new Ext.Template(item.href).compile();
                }
                var tpl = this.linkTplsCache[item.id];
                if (item.rendered) {
                    item.el.down('a').dom.href = tpl.apply(me.data);
                }
            }
        }, this);
    },

    statics: {
        // custom method, is used by actionsmenu and optionscolumn
        processEvent: function(me, item, data, e) {
            if (Ext.isFunction (item.menuHandler)) {
                item.menuHandler(data);
                e && e.stopEvent();
            } else if (Ext.isObject(item.request)) {
                var r = Scalr.utils.CloneObject(item.request);
                r.params = r.params || {};
                r.scope = r.scope || me;

                if (Ext.isObject(r.confirmBox)) {
                    r.confirmBox.msg = new Ext.Template(r.confirmBox.msg).applyTemplate(data);
                }

                if (Ext.isFunction(r.dataHandler)) {
                    r.params = Ext.apply(r.params, r.dataHandler(data));
                    delete r.dataHandler;
                }
                if (r.success === undefined) {
                    r.success = function () {
                        me.fireEvent('actioncomplete');
                    }
                }
                Scalr.Request(r);
                e && e.stopEvent();
            }
        }
    }
});

Ext.define('Scalr.ui.GridOptionsColumn', {
    extend: 'Ext.grid.column.Column',
    alias: 'widget.optionscolumn',

    text: 'Actions',
    hideable: false,
    minWidth: 140,
    fixed: true,
    align: 'left',
    tdCls: 'x-grid-row-options-cell',
    calculatedMargin: 8,
    calculatedSize: 30,
    quickActionsLimit: 3,

    initComponent: function() {
        var me = this;

        me.sortable = false;
        me.linkTplsCache = {};
        if (Ext.isArray(me.menu)) {
            var i, flag = true;
            for (i = 0; i < me.menu.length; i++) {
                if (Ext.isEmpty(me.menu[i].showAsQuickAction))
                    flag = false;
            }

            if (flag && me.menu.length <= me.quickActionsLimit) {
                // we don't need menu, we can show all elements
                me.quickItems = new Ext.util.MixedCollection();
                i = 1;
                Ext.each(me.menu, function(m) {
                    me.quickItems.add('id-' + i, m);
                    i++;
                }, me);

                me.width = me.calculatedMargin + (me.calculatedSize) * me.menu.length;
                me.minWidth = 70;
                me.align = 'center';
                delete me.menu;
            } else {
                me.menu = {
                    xtype: 'actionsmenu',
                    items: me.menu
                };
            }
        }

        if (me.menu) {
            me.menu = Ext.widget(me.menu);
            me.menu.doAutoRender();
            me.menu.ownerCmp = me;
            me.menu.on('hide', function() {
                if (me.currentBtnEl) {
                    me.currentBtnEl.removeCls('x-grid-row-options-pressed');
                    me.currentBtnEl = null;
                }
            }, me);
        }

        me.callParent(arguments);
        me.on('boxready', function () {
            var panel = this.up('panel'), widget = this;
            Ext.override(panel.getView(), {
                onFocusLeave: function(e) {
                    if (widget.menu && !(e.toComponent && e.toComponent.xtype == 'menuitem')) {
                        widget.menu.hide();
                        widget.currentBtnEl = null;
                    }

                    this.callParent(arguments);
                }
            });
        });

        me.addCls(Ext.baseCSSPrefix + 'column-header-align-center');
    },

    renderer: function(value, meta, record, rowIndex, colIndex) {
        var cmp = this.headerCt.getHeaderAtIndex(colIndex);
        if (cmp.getVisibility(record)) {
            var cnt = 0, ret = '', items = cmp.menu ? cmp.menu.items : cmp.quickItems, innerTpl = '', visibleItemsCount = 0, quickActions = [], visibleQuickActionsCount = 0;

            items.eachKey(function(key, item) {
                var visibility = Ext.isFunction(item.getVisibility) ? item.getVisibility(record.getData()) : true;

                if (visibility && item.xtype !== 'menuseparator') visibleItemsCount++;

                if (!Ext.isEmpty(item.showAsQuickAction)) {
                    quickActions.push({
                        key: key,
                        item: item,
                        visibility: visibility,
                        showAsQuickAction: item.showAsQuickAction
                    });
                }
            });

            if (quickActions.length > 0) {
                quickActions = Ext.Array.sort(quickActions, function(item1, item2){
                    return Ext.Array.numericSortFn(item1.showAsQuickAction, item2.showAsQuickAction);
                });
                Ext.each(quickActions, function(quickAction){
                    // if we don't have menu, fill empty cells to keep center align
                    if ( (cnt < cmp.quickActionsLimit) && (quickAction.visibility || !cmp.menu || cmp.fillEmptyIcons)) {
                        if (quickAction.visibility) {
                            innerTpl = '<div class="x-grid-row-options-quick-action x-grid-icon ' + quickAction.item.iconCls.replace('x-menu-icon-', 'x-grid-icon-') + '" data-itemid="' + quickAction.key + '" data-qtip="' + quickAction.item.text + '"></div>';

                            if (quickAction.item.href) {
                                if (! cmp.linkTplsCache[quickAction.key]) {
                                    cmp.linkTplsCache[quickAction.key] = new Ext.Template(quickAction.item.href).compile();
                                }

                                var tpl = cmp.linkTplsCache[quickAction.key];
                                innerTpl = '<a href="' + tpl.apply(record.getData()) + '">' + innerTpl + '</a>';
                            }
                            visibleQuickActionsCount++;
                        } else {
                            innerTpl = '<div class="x-grid-row-options-quick-action-hidden x-grid-icon"></div>';
                        }
                        cnt++;
                        ret += innerTpl;
                    }
                });
            }

            if (cmp.menu && visibleItemsCount > visibleQuickActionsCount) {
                if (! ret)
                    ret = '<div style="height: 29px">&nbsp</div>';
                ret += '<div class="x-grid-row-options"><div class="x-grid-row-options-trigger"></div></div>';
                meta.tdCls += ' x-grid-row-options-cell-trigger';
            }
            return ret;
        }

    },

    getVisibility: function(record) {
        return true;
    },

    processEvent: function(type, view, cell, recordIndex, cellIndex, e, record, row) {
        //prevent row focusing
        if (type === 'mousedown' && (e.getTarget('div.x-grid-row-options') || e.getTarget('div.x-grid-row-options-quick-action'))) {
            e.preventDefault();
            return false;
        }

        if (type === 'click') {
            var btnEl = Ext.get(e.getTarget('div.x-grid-row-options'));
            if (! btnEl) {
                var quickEl = Ext.get(e.getTarget('div.x-grid-row-options-quick-action')), items = this.menu ? this.menu.items : this.quickItems;
                if (quickEl) {
                    if (! quickEl.parent('a')) {
                        Scalr.ui.ActionsMenu.processEvent(this, items.get(quickEl.getAttribute('data-itemid')), record.getData());
                    }
                }
            } else if (this.menu) {
                if (this.currentBtnEl !== btnEl) {
                    if (this.currentBtnEl)
                        this.currentBtnEl.removeCls('x-grid-row-options-pressed');
                    btnEl.addCls('x-grid-row-options-pressed');
                    this.currentBtnEl = btnEl;
                    this.menu.setData(record.getData());
                    this.menu.showBy(btnEl, 'tr-br?', [ 0, 1 ]);
                    this.menu.focus();
                    e.stopPropagation();
                    return false;
                }
            }

            if (this.menu) {
                this.menu.hide();
                this.currentBtnEl = null;
            }


            e.stopPropagation();
            return false;
        }
    }


});

Ext.define('Scalr.ui.RowPointer', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.rowpointer',

    disabled: false,
    client: null,

    baseCls: 'x-panel-row-pointer',
    addCls: null,

    align: 'left',

    width: 32,
    height: 32,
    addOffset: 0,
    thresholdOffset: 0,
    hiddenOffset: -100,

    init: function(client) {
        this.client = client;
        this.baseCls += ' ' + this.baseCls + '-' + this.align;
        this.initListeners();
    },

    initListeners: function() {
        var me = this;
        me.throttledUpdatePointerPosition = Ext.Function.createThrottled(me.updatePointerPosition, me.throttle, me);

        this.client.on('afterrender', function() {
            this.on('afterlayout', me.throttledUpdatePointerPosition, me);
            this.view.el.on('scroll', me.throttledUpdatePointerPosition, me);

            this.on('beforedestroy',  function() {
                this.un('afterlayout', me.throttledUpdatePointerPosition, me);
                this.view.el.un('scroll', me.throttledUpdatePointerPosition, me);
            });
        });
    },

    getPointerEl: function() {
        if (this.pointerEl === undefined) {
            this.pointerEl = Ext.DomHelper.append(this.client.el.dom, '<div class="' + this.baseCls + (this.addCls ? ' ' + this.addCls  : '') + '"' + (this.tooltip ? ' title="' + this.tooltip + '"'  : '') + '></div>', true);
            this.pointerEl.setWidth(this.width);
            this.pointerEl.setHeight(this.height);
        }
        return this.pointerEl;
    },

    pointTo: function(record) {
        var offset = this.hiddenOffset;
        if (this.client.view && record) {
            var row = this.client.view.getRow(record);
            if (row) {
                offset = Ext.get(row).getOffsetsTo(this.client.el)[1] + this.addOffset;
                offset = offset < this.thresholdOffset ? this.hiddenOffset : offset;
            }
        }
        this.getPointerEl().setStyle('top', offset + 'px');
    },

    updatePointerPosition: function() {
        if (Ext.isFunction(this.getPointerRecord)) {
            this.pointTo(this.getPointerRecord());
        }
    }

});

Ext.define('Scalr.ui.FocusedRowPointer', {
    extend: 'Scalr.ui.RowPointer',
    alias: 'plugin.focusedrowpointer',

    align: 'right',

    width: 0,
    height: 0,
    thresholdOffset: 70,
    addOffset: 5,

    throttle: 100,

    mode: 'selectedRecord', //selModel


    init: function(client) {
        if (!client.findPlugin('selectedrecord')) {
           this.mode = 'selModel';
        }
        this.callParent(arguments);
    },

    initListeners: function() {
        var me = this;
        me.throttledUpdatePointerPosition = Ext.Function.createThrottled(me.updatePointerPosition, me.throttle, me);

        this.client.on('afterrender', function() {
            this.on('afterlayout', me.throttledUpdatePointerPosition, me);
            this.view.on('refresh', me.throttledUpdatePointerPosition, me);
            this.view.el.on('scroll', me.throttledUpdatePointerPosition, me);

            if (me.mode === 'selectedRecord') {
                this.on('selectedrecordchange', me.throttledUpdatePointerPosition, me);
            } else {
                this.view.on('select', me.throttledUpdatePointerPosition, me);
            }

            this.on('beforedestroy',  function() {
                this.un('afterlayout', me.throttledUpdatePointerPosition, me);
                this.view.un('refresh', me.throttledUpdatePointerPosition, me);
                this.view.el.un('scroll', me.throttledUpdatePointerPosition, me);
                if (me.mode === 'selectedRecord') {
                    this.un('selectedrecordchange', me.throttledUpdatePointerPosition, me);
                } else {
                    this.view.un('select', me.throttledUpdatePointerPosition, me);
                }
            });
        });
    },

    getPointerRecord: function() {
        var pointerRecord;
        if (this.client.isDestroyed) return;
        if (this.mode === 'selectedRecord') {
            pointerRecord = this.client.getSelectedRecord();
        } else {
            var selection = this.client.getSelectionModel().getSelection();
            if (selection.length){
                pointerRecord = selection[0];
            }
        }
        return pointerRecord;
    }

});

Ext.define('Ext.grid.feature.AddButton', {
    extend: 'Ext.grid.feature.Feature',
    alias: 'feature.addbutton',
    cls: Ext.baseCSSPrefix + 'grid-add-button',
    viewCls: Ext.baseCSSPrefix + 'grid-with-add-button',
    disabledCls: Ext.baseCSSPrefix + 'disabled',
    disabledTooltip: null,
    hidden: false,
    config: {
        text: 'Add'
    },

    init: function(grid) {
        var me = this;
        me.callParent(arguments);
        grid.view.addCls(me.viewCls);
        if (me.text) {
            me.setText(me.text);
        }
        grid.view.on('viewready', function() {
                me.renderAddButton();
                this.on('refresh', me.renderAddButton, me);
                this.on('itemadd', me.renderAddButton, me);

                this.on('resize', me.updateButtonPosition, me);
                this.on('refresh', me.updateButtonPosition, me);
                this.on('itemadd', me.updateButtonPosition, me);
                this.on('itemremove', me.updateButtonPosition, me);
                this.el.on('scroll', me.updateButtonPosition, me);
                this.el.on('click', me.onViewClick, me);
            },
            grid.view,
            {single: true}
        );
        grid.view.on('beforedestroy', function() {
                this.un('refresh', me.renderAddButton, me);
                this.un('itemadd', me.renderAddButton, me);

                this.un('resize', me.updateButtonPosition, me);
                this.un('refresh', me.updateButtonPosition, me);
                this.un('itemadd', me.updateButtonPosition, me);
                this.un('itemremove', me.updateButtonPosition, me);
                if (this.el) {
                    this.el.un('scroll', me.updateButtonPosition, me);
                    this.el.un('click', me.onViewClick, me);
                }
                delete me.buttonEl;
            },
            grid.view,
            {single: true}
        );
    },

    updateText: function(text) {
        if (this.buttonEl) {
            Ext.fly(this.buttonEl).setHtml(text);
        }
    },

    renderAddButton: function() {
        var me = this;
        if (!me.buttonEl) {
            me.buttonEl = Ext.core.DomHelper.insertHtml('beforeEnd', me.view.body.el.dom, '<div class="' + me.cls + '' + (me.disabled ? ' ' + me.disabledCls : '') + '" id="' + me.view.id + '-add-button" '+(me.hidden?' style="display:none"':'')+(me.disabledTooltip?' data-qtip="'+Ext.String.htmlEncode(me.disabledTooltip)+'"':'')+'>' + me.getText() + '</div>');
            Ext.get(me.buttonEl).setVisibilityMode(Ext.dom.Element.DISPLAY);
        } else {
            me.view.body.el.append(me.buttonEl);
        }
        me.view.refreshSize();
    },

    onViewClick: function(e, t) {
        if (this.buttonEl && e.within(this.buttonEl) && !this.disabled) {
            this.handler(this.view);
        }
    },

    setDisabled: function(disabled, tooltip) {
        if (this.view.isDestroyed) return;

        if (this.buttonEl) {
            var el = Ext.fly(this.buttonEl);
            el[disabled ? 'addCls' : 'removeCls'](this.disabledCls);
            el.set({
                'data-qtip': disabled && tooltip ? tooltip : ''
            });
        }
        if (tooltip !== undefined) {
            this.disabledTooltip = tooltip;
        }
        this.disabled = !!disabled;
    },

    setVisible: function(visible) {
        if (this.view.isDestroyed) return;

        if (this.buttonEl) {
            var el = Ext.fly(this.buttonEl);
            el[visible ? 'show' : 'hide']();
        }
        this.hidden = !visible;
    },

    updateButtonPosition: function() {
        var view = this.view,
            height,
            scrollHeight,
            scrollTop;
        if (view.isDestroyed || !view.el || !this.buttonEl) return;

        height = view.getHeight();
        scrollHeight = view.el.dom.scrollHeight;
        scrollTop = view.el.getScroll().top;
        Ext.fly(this.buttonEl).setStyle('top', scrollHeight > height ? (height - scrollHeight + scrollTop) + 'px' : '');
    }

});

Ext.define('Scalr.ui.GridStatusColumn', {
    extend: 'Ext.grid.column.Column',
    alias: 'widget.statuscolumn',

    text: '&nbsp;',
    hideable: false,
    width: 150,
    minWidth: 150,
    //fixed: true,
    align: 'center',
    tdCls: 'x-grid-row-colored-status-cell',

    renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
        var column = grid.panel.columns[colIndex];
        return Scalr.ui.ColoredStatus.getHtml({
            type: column['statustype'],
            params: column['params'],
            status: record.data.status || record.data[column.dataIndex],
            data: record.data
        }, column.qtipConfig);
    }
});

Ext.define('Scalr.ui.SelectedRecord', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.selectedrecord',

    form: null,
    grid: null,
    selectedRecord: null,
    selectedRecordCls: 'x-grid-item-selectedrecord',

    clearOnRefresh: false,
    selectSingleRecord: false,
    suspendSelectedRecordChangeEvent: 0,
    disableSelection: true,
    suspendClearOnRemove: 0,

    init: function(client) {
        var me = this;
        this.grid = client;

        if (!this.form) {
            client.on('render', function () {
                this.form = this.getForm ? this.getForm() : client.up().down('form');
            }, this);
        }

        this.grid.getSelectedRecord = this.getSelectedRecord.bind(this);
        this.grid.setSelectedRecord = this.setSelectedRecord.bind(this);
        this.grid.clearSelectedRecord = this.clearSelectedRecord.bind(this);

        this.grid.getStore().on({
            remove: function(store, records) {
                if (!me.suspendClearOnRemove) {
                    for (var i = 0; i < records.length; i++) {
                        if (records[i] == this.selectedRecord) {
                            this.clearSelectedRecord();
                        }
                    }
                }
            },
            scope: this
        });

        client.getSelectionModel().on('focuschange', function (comp, oldFocused, newFocused) {
            if (newFocused) {
                if (newFocused != this.selectedRecord) {
                    this.setSelectedRecord(newFocused);
                }
            }
        }, this);

        client.getView().hasSelectedRecordPlugin = true;
        var getRowClass = client.getView().getRowClass;
        client.getView().getRowClass = function(record) {
            var cls = [];
            if (getRowClass) {
                cls.push(getRowClass.apply(this, arguments));
            }

            if (record === me.selectedRecord) {
                this.rowValues.itemClasses.push(me.selectedRecordCls);
            }

            return cls.join(' ');
        };

        client.getSelectionModel().setLocked(this.disableSelection);

        if (this.clearOnRefresh) {
            this.grid.getView().on('refresh', function() {
                this.clearSelectedRecord();
            }, this);
        }

        if (me.selectSingleRecord) {
            me.grid.getView().on('refresh', function (view) {
                var nodes = view.getNodes();

                if (nodes.length === 1) {
                    me.setSelectedRecord(
                        view.getRecord(nodes[0])
                    );
                }
            });
        }
    },

    setSelectedRecord: function(record) {
        if (record === this.selectedRecord) return;

        var selectedRecord;
        this.suspendSelectedRecordChangeEvent++;
        selectedRecord = this.clearSelectedRecord();
        this.suspendSelectedRecordChangeEvent--;

        if (this.grid.fireEvent('beforeselectedrecordchange', record) && this.form) {
            if (this.form.loadRecord(record)) {
                this.selectedRecord = record;
                this.grid.getView().addItemCls(this.selectedRecord, this.selectedRecordCls);
                this.grid.fireEvent('selectedrecordchange', record, selectedRecord);
            } else if (selectedRecord) {
                this.grid.fireEvent('selectedrecordchange', null, selectedRecord);
            }
        } else {
            this.selectedRecord = record;
            this.grid.getView().addItemCls(this.selectedRecord, this.selectedRecordCls);
            this.grid.fireEvent('selectedrecordchange', record, selectedRecord);
        }
    },

    getSelectedRecord: function(record) {
        return this.selectedRecord || null;
    },

    clearSelectedRecord: function() {
        var selectedRecord = this.selectedRecord,
            navModel;
        if (this.form) {
            this.form.resetRecord();
        }
        if (selectedRecord) {
            this.selectedRecord = null;
            this.grid.getView().removeItemCls(selectedRecord, this.selectedRecordCls);
            navModel = this.grid.getView().getNavigationModel();
            if (navModel.record === selectedRecord) {
                navModel.setPosition(null, null);
            }
            if (!this.suspendSelectedRecordChangeEvent) {
                this.grid.fireEvent('selectedrecordchange', null, selectedRecord);
            }
        }
        return selectedRecord;
    }
});

// extjs5
Ext.define('Scalr.ui.GridSelectionModel', {
    alias: 'selection.selectedmodel',
    extend: 'Ext.selection.CheckboxModel',

    injectCheckbox: 'last',
    headerWidth: 50,
    mode: 'SIMPLE',

    bindComponent: function(view) {
        var me = this;
        me.callParent(arguments);

        if (view) {
            view.on('render', function() {
                this.el.on('mousedown', function(e) {
                    if (e.getTarget('table.x-grid-item') && e.shiftKey) {
                        // prevent text selection on row selections
                        e.preventDefault();
                    }
                });
            });
        }

        if (me.store) {
            // deselect records, which were hidden by local filter
            me.store.on('filterchange', function() {
                var me = this,
                    oldSelections = me.getSelection(),
                    store = me.store,
                    i = 0,
                    deselected = [];

                for (; i < oldSelections.length; i++) {
                    if (store.indexOf(oldSelections[i]) == -1)
                        deselected.push(oldSelections[i]);
                }

                if (deselected.length)
                    me.deselect(deselected);
            }, me);
        }
    },

    // required in all cases
    getVisibility: function (record) {
        return true;
    },

    renderer: function(value, metaData, record, rowIndex, colIndex, store, view) {
        metaData.tdCls = Ext.baseCSSPrefix + 'grid-cell-special';
        metaData.style = 'margin-left: 3px';

        if (this.getVisibility(record))
            return '<div class="' + Ext.baseCSSPrefix + 'grid-row-checker">&#160;</div>';
    },

    onNavigate: function(e) {
        // Enforce the ignoreRightMouseSelection setting.
        // Enforce presence of a record.
        // Enforce selection upon click, not mousedown.
        if (!e.record || this.vetoSelection(e.keyEvent)) {
            return;
        }

        this.onBeforeNavigate(e);

        var me = this,
            keyEvent = e.keyEvent,
            // ctrlKey may be set on the event if we want to treat it like a ctrlKey so
            // we don't mutate the original event object
            ctrlKey = keyEvent.ctrlKey || e.ctrlKey,
            recIdx = e.recordIndex,
            record = e.record,
            lastFocused = e.previousRecord,
            isSelected = me.isSelected(record),
            from = (me.selectionStart && me.isSelected(e.previousRecord)) ? me.selectionStart : (me.selectionStart = e.previousRecord),
            fromIdx = e.previousRecordIndex,
            key = keyEvent.getCharCode(),
            isSpace = key === keyEvent.SPACE,
            direction = key === keyEvent.UP || key === keyEvent.PAGE_UP ? 'up' : (key === keyEvent.DOWN || key === keyEvent.DOWN ? 'down' : null);

        if (key === keyEvent.A && ctrlKey) {
            // Listening to endUpdate on the Collection will be more efficient
            me.selected.beginUpdate();
            me.selectRange(0, me.store.getCount() - 1);
            me.selected.endUpdate();
        }
        else if (isSpace) {
            // SHIFT+SPACE, select range
            if (keyEvent.shiftKey) {
                me.selectRange(from, record, ctrlKey);
            } else {
                // SPACE pessed on a selected item: deselect.
                if (isSelected) {
                    if (me.allowDeselect) {
                        me.doDeselect(record);
                    }
                }
                // SPACE on an unselected item: select it
                // keyEvent.ctrlKey means "keep existing"
                else {
                    me.doSelect(record, ctrlKey);
                }
            }
        }

        // SHIFT-navigate selects intervening rows from the last selected (or last focused) item and target item
        else if (keyEvent.shiftKey && from) {
            // If we are heading back TOWARDS the start rec - deselect skipped range...
            if (direction === 'up' && fromIdx <= recIdx) {
                me.deselectRange(lastFocused, recIdx + 1);
            }
            else if (direction === 'down' && fromIdx >= recIdx) {
                me.deselectRange(lastFocused, recIdx - 1);
            }

            // If we are heading AWAY from start point, or no CTRL key, so just select the range and let the CTRL control "keepExisting"...
            else if (from !== record) {
                /** Changed */
                me.selectRange(from, record, ctrlKey || true);
                /** End */
            }
            me.lastSelected = record;

        } else {
            /* CHANGED */
            if (e.keyEvent.type === 'click' && e.keyEvent.getTarget(me.checkSelector))
                me.selectWithEvent(record, keyEvent);
        }
        /* END */

        // selectionStart is a start point for shift/mousedown to create a range from.
        // If the mousedowned record was not already selected, then it becomes the
        // start of any range created from now on.
        // If we drop to no records selected, then there is no range start any more.
        if (!keyEvent.shiftKey) {
            if (me.isSelected(record)) {
                me.selectionStart = record;
            }
        }
    },

    doSelect: function(records, keepExisting, suppressEvent) {
        var me = this,
            record, i, result = [];

        if (me.locked) {
            return;
        }
        if (typeof records === "number") {
            record = me.store.getAt(records);
            // No matching record, jump out
            if (!record) {
                return;
            }
            records = [record];
        }

        records = !Ext.isArray(records) ? [records] : records;

        for (i = 0; i < records.length; i++) {
            if (me.getVisibility(records[i]))
                result.push(records[i]);
        }

        if (result.length)
            me.callParent([result, keepExisting, suppressEvent]);
    },

    updateHeaderState: function() {
        // check to see if all records are selected
        var me = this,
            store = me.store,
            storeCount = store.getCount(),
            views = me.views,
            hdSelectStatus = false,
            selectedCount = 0,
            selected, len, i;

        if (!store.isBufferedStore) {
            /* CHANGED */
            storeCount = 0;
            store.each(function(record) {
                if (me.getVisibility(record))
                    storeCount++;
            });
            /* End of changed */

            if (storeCount > 0) {
                selected = me.selected;
                hdSelectStatus = true;
                for (i = 0, len = selected.getCount(); i < len; ++i) {
                    if (store.indexOfId(selected.getAt(i).id) === -1) {
                        break;
                    }
                    ++selectedCount;
                }
                hdSelectStatus = storeCount === selectedCount;
            }
        }

        if (views && views.length) {
            me.toggleUiHeader(hdSelectStatus);
        }
    }
});

Ext.define('Scalr.ui.ContinuousRenderer', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.continuousrenderer',

    isContinuousRenderer: true,
    rowHeight: 30,
    loadNextPageOffset: 10,
    highlightNew: false,

    init: function(grid) {
        var me = this,
            view = grid.view,
            viewListeners = {
                scroll: me.onViewScroll,
                //resize: me.onViewResize,
                refresh: me.onViewRefresh,
                //boxready: me.onViewBoxReady,
                scope: me,
                destroyable: true
            };

        me.grid = grid;
        me.view = view;
        grid.bufferedRenderer = false;
        view.continuousRenderer = me;
        view.preserveScrollOnRefresh = false;
        view.animate = false;
        view.loadMask = false;

        me.bindStore(view.dataSource);

        me.viewListeners = view.on(viewListeners);

        me.view.addFooterFn(Ext.bind(me.renderLoader, me));

        if (me.highlightNew) {
            var getRowClass = view.getRowClass;

            view.getRowClass = function (record) {
                var cls;

                if (Ext.isFunction(getRowClass)) {
                    cls = getRowClass.apply(view, arguments);
                }

                if (Ext.isEmpty(cls) && record.get('gridHighlightItem')) {
                    cls = 'x-grid-row-color-new';
                }

                return cls;
            };

            grid.getStore().on({
                clearandload: function (store) {
                    var data = store.getData();

                    if (data.count() > 0) {
                        store.gridHightlight = [];

                        data.each(function (record) {
                            Ext.Array.push(store.gridHightlight, record.getId());
                        });
                    }
                },
                load: function (store) {
                    var ids = store.gridHightlight;

                    if (Ext.isDefined(ids)) {
                        store.each(function (record) {
                            if (!Ext.Array.contains(ids, record.getId())) {
                                record.set('gridHighlightItem', true);
                            }
                        });

                        delete store.gridHightlight;
                    }
                }
            });
        }
    },

    onViewRefresh: function(view, records) {
        view.getOverflowEl().setScrollTop(0);
        view.body.translate();//fixes chrome bug on retina(slow rendering on scroll)
    },

    onViewScroll: function() {
        var me = this,
            store = me.store,
            totalCount = me.store.getTotalCount();
        if (!(me.disabled || totalCount <= store.getPageSize() || me.store.loading)) {
            if (totalCount > me.store.getCount() && store.getCount() < this.getLastVisibleRowIndex() + me.loadNextPageOffset) {
                me.store.loadPage(me.store.currentPage+1, {addRecords: true});
            }
        }
    },

    bindStore: function (store) {
        var me = this,
            view = me.view;

        if (me.store) {
            me.unbindStore();
        }
        me.storeListeners = store.on({
            scope: me,
            beforeload: me.onStoreBeforePrefetch,
            load: me.onStorePrefetch,
            remove: me.onStoreRemove,
            destroyable: true
        });

        this.storeProxyListeners = store.proxy.on({
            scope: me,
            exception: me.onStoreProxyException,
            destroyable: true
        });

        me.store = store;
    },

    unbindStore: function() {
        this.storeListeners.destroy();
        this.storeProxyListeners.destroy();
        this.store = null;
    },

    getFirstVisibleRowIndex: function() {
        return Math.floor(this.view.getScrollY() / this.rowHeight);
    },

    getLastVisibleRowIndex: function() {
        return this.getFirstVisibleRowIndex() + Math.ceil(this.view.el.dom.clientHeight / this.rowHeight);
    },

    renderLoader: function(values, out) {
        var view = values.view;
        if (!view.el.down('#' + view.id + '-buffered-loader')) {
            out.push('<div id="' + view.id + '-buffered-loader" class="x-grid-buffered-loader" style="display:none"><div>Loading...</div></div>');
        }
        if (!view.el.down('#' + view.id + '-buffered-error')) {
            out.push('<div id="' + view.id + '-buffered-error" class="x-grid-error" style="display:none"></div>');
        }
    },

    onStoreBeforePrefetch: function(store, records) {
        var view = this.view,
            loader = view.el.down('#' + view.id + '-buffered-loader'),
            error = view.el.down('#' + view.id + '-buffered-error'),
            emptyText = view.el.down('.x-grid-empty');
        if (loader) {
            loader.show();
        }
        if (emptyText) {
            emptyText.hide();
        }
        if (error) {
            error.setVisibilityMode(Ext.dom.Element.DISPLAY);
            error.hide();
        }
    },

    onStorePrefetch: function(store, records) {
        var view = this.view,
            loader = view.el.down('#' + view.id + '-buffered-loader'),
            emptyText = view.el.down('.x-grid-empty');
        if (loader) {
            loader.setVisibilityMode(Ext.dom.Element.DISPLAY);
            loader.hide();
        }
        if (emptyText) {
            emptyText.show();
        }
    },

    onStoreRemove: function(store, records) {
        store.totalCount -= records.length;
        if (!store.updatingCollection && store.getCount() < store.getTotalCount()) {
            store.loadPage(store.currentPage, {
                start: store.currentPage * store.getPageSize() - records.length,
                limit: records.length,
                addRecords: true
            });
        }
    },

    onStoreProxyException: function(proxy, response, operation, options) {
        var me = this,
            message = 'Unable to load data';
        try {
            var result = Ext.decode(response.responseText, true);
            if (result && result.success === false && result.errorMessage)
                message += ' (' + result.errorMessage + ')';
            else
                throw 'Report';
        } catch (e) {
            if (response.status == 200 && Ext.isFunction(response.getAllResponseHeaders) && response.getAllResponseHeaders() && response.responseText) {
                var report = [ "Ext.JSON.decode(): You're trying to decode an invalid JSON String" ];
                report.push(Scalr.utils.VarDump(response.request.headers));
                report.push(Scalr.utils.VarDump(response.request.options));
                report.push(Scalr.utils.VarDump(response.request.options.params));
                report.push(Scalr.utils.VarDump(response.getAllResponseHeaders()));
                report.push(response.status);
                report.push(response.responseText);

                report = report.join("\n\n");

                Scalr.utils.PostError({
                    message: 't2 ' + report,
                    url: document.location.href
                });
            }
        }
        if (me.store.getCount()) {
            Scalr.message.Error(message);
        } else {
            var error = me.view.el.down('#' + me.view.id + '-buffered-error');
            if (error) {
                error.update(message);
                error.show();
                var emptyText = me.view.el.down('.x-grid-empty');
                if (emptyText) {
                    emptyText.hide();
                }
            }
            //me.view.refreshSize();
        }
    },

    destroy: function() {
        var me = this;
        Ext.destroy(me.viewListeners, me.storeListeners, me.storeProxyListeners);
    }
});

Ext.define('Scalr.ui.ContinuousStore', {
    extend: 'Ext.data.Store',

    alias: 'store.continuous',

    isContinuousStore: true,

    updatingCollection: 0,

    config: {
        data: 0,
        pageSize: 50,
        remoteSort: true,
        remoteFilter: true,
        sortOnLoad: false
    },

    sort: function(field, direction, mode) {
        if (arguments.length === 0) {
            this.clearAndLoad();
        } else {
            this.getSorters().addSort(field, direction, mode);
        }
    },

    onSorterEndUpdate: function() {
        var me = this,
            sorters = me.getSorters().getRange();

        if (me.blockLoadAfterSorters) {
            delete me.blockLoadAfterSorters;
            return;
        }

        if (sorters.length) {
            me.clearAndLoad({
                callback: function() {
                    me.fireEvent('sort', me, sorters);
                }
            }, true);
        } else {
            me.fireEvent('sort', me, sorters);
        }
    },

    clearAndLoad: function(options, suppressEvent) {
        var me = this;

        if (suppressEvent !== true) {
            me.fireEvent('clearandload', me);
        }

        me.removeAll();
        me.loadPage(1, options);
    },

    loadRecords: function(records, options) {
        var me     = this,
            length = records.length,
            data   = me.getData(),
            addRecords, i, skipSort;

        if (options) {
            addRecords = options.addRecords;
        }

        if (!addRecords) {
            me.clearData(true);
        }

        me.loading = false;
        me.updatingCollection++;
        if (!addRecords) me.ignoreCollectionAdd = true;
        me.callObservers('BeforePopulate');
        data.add(Ext.Array.filter(records, function(record){
            return data.getByKey(record.getId()) === undefined;
        }));
        if (!addRecords) me.ignoreCollectionAdd = false;

        for (i = 0; i < length; i++) {
            records[i].join(me);
        }

        ++me.loadCount;
        me.updatingCollection--;
        me.complete = true;
        me.fireEvent('datachanged', me);
        if (!addRecords) me.fireEvent('refresh', me);
        me.callObservers('AfterPopulate');
    },

    //we always add new records to the top in continuous store
    add: function(arg) {
        return this.insert(0, arguments.length === 1 ? arg : arguments);
    }
});

Ext.define('Scalr.ui.AclResourceColumn', {
    extend: 'Ext.grid.column.Column',
    alias: 'widget.aclresourcecolumn',

    tdCls: Ext.baseCSSPrefix + 'grid-cell-aclresourcecolumn',
    innerCls: Ext.baseCSSPrefix + 'grid-cell-inner-aclresourcecolumn',

    cbCls: Ext.baseCSSPrefix + 'permission-cb',
    cbModeCls: Ext.baseCSSPrefix + 'mode-cb',
    cbCheckedCls: Ext.baseCSSPrefix + 'cb-checked',
    cbDisabledCls: Ext.baseCSSPrefix + 'cb-disabled',

    config: {
        readOnly: false
    },

    statics: {
        hideMenus: function() {
            if (this.accessButtonMenu) this.accessButtonMenu.hide();
            if (this.resourceModeMenu) this.resourceModeMenu.hide();
        },
        getAccessButtonMenu: function() {
            if (!this.accessButtonMenu) {
                this.accessButtonMenu = Ext.create('Ext.menu.Menu', {
                    cls: 'x-menu-light x-menu-light-no-icon',
                    width: 140,
                    setRecord: function(btnEl, record, column){
                        var items = [],
                            permissions = record.get('permissions'),
                            value = btnEl.getAttribute('data-value');
                        items.push({
                            xtype: 'menuitem',
                            text: '<span style="color:#008000">Full access</span>',
                            value: 'full'
                        });
                        if (value === 'limited') {
                        items.push({
                            xtype: 'menuitem',
                                text: 'Limited',
                                value: 'limited'
                            });
                        }
                        if (!Ext.isEmpty(permissions)) {
                            items.push({
                                xtype: 'menuitem',
                                text: '<span style="color:#B277FD">Read only</span>',
                                value: 'readonly'
                            });
                        }
                        items.push({
                            xtype: 'menuitem',
                            text: '<span style="color:#c00000">No access</span>',
                            value: 'no'
                        });

                        Ext.each(items, function(item){
                            if (item.value === value) {
                                item.cls = 'x-menu-item-current';
                                return false;
                            }
                        });

                        this.hide();
                        this.removeAll();
                        this.add(items);

                        this.resourceRecord = record;
                        this.parentColumn = column;

                        var xy = btnEl.getXY(), sizeX = xy[1] + btnEl.getHeight() + this.getHeight();
                        if (sizeX > Scalr.application.getHeight()) {
                            xy[1] -= sizeX - Scalr.application.getHeight();
                        }
                        this.showBy(btnEl, 'tl-bl');//.setPosition([xy[0] - (this.getWidth() - btnEl.getWidth()), xy[1] + btnEl.getHeight() + 1]);
                        this.focus();
                    },
                    defaults: {
                        handler: function(menuitem) {
                            var menu = menuitem.parentMenu,
                                record = menu.resourceRecord,
                                permissions = record.get('permissions'),
                                autoFilter,
                                settings = {};
                            if (menuitem.value === 'limited') return;

                            //full, no, readonly
                            settings.granted = menuitem.value === 'no' ? 0 : 1;
                            if (!Ext.isEmpty(permissions)) {
                                settings.permissions = {};
                                Ext.Object.each(permissions, function(name, enabled){
                                    settings.permissions[name] = menuitem.value === 'full' ? 1 : 0;
                                });
                            }
                            autoFilter = record.store.getAutoFilter();
                            record.store.setAutoFilter(false);
                            record.set(settings);
                            menu.parentColumn.afterChangeResource(settings, record);
                            record.store.setAutoFilter(autoFilter);
                        }
                    }
                });
                this.accessButtonMenu.doAutoRender();
            }
            return this.accessButtonMenu;
        },
        getResourceModeMenu: function() {
            if (!this.resourceModeMenu) {
                this.resourceModeMenu = Ext.create('Ext.menu.Menu', {
                    cls: 'x-menu-light x-menu-light-no-icon',
                    width: 210,
                    setRecord: function(btnEl, record, column){
                        var items = [],
                            id = record.get('id'),
                            mode = record.get('mode');
                        Ext.each(column.definitions[id][4], function(item){
                            if (item['key'] != 1) {/*MODE_ALL*/
                        items.push({
                            xtype: 'menuitem',
                                    text: item['name'],
                                    cls: mode == item['key'] ? 'x-menu-item-current' : '',
                                    value: item['key']
                        });
                            }

                        });

                        this.hide();
                        this.removeAll();
                        this.add(items);

                        this.resourceRecord = record;
                        this.parentColumn = column;

                        var xy = btnEl.getXY(), sizeX = xy[1] + btnEl.getHeight() + this.getHeight();
                        if (sizeX > Scalr.application.getHeight()) {
                            xy[1] -= sizeX - Scalr.application.getHeight();
                        }

                        this.showBy(btnEl, 'tl-bl');//.setPosition([xy[0] - (this.getWidth() - btnEl.getWidth()), xy[1] + btnEl.getHeight() + 1]);
                        this.focus();
                    },
                    defaults: {
                        handler: function(menuitem) {
                            var menu = menuitem.parentMenu,
                                record = menu.resourceRecord,
                                autoFilter;
                            autoFilter = record.store.getAutoFilter();
                            record.store.setAutoFilter(false);
                            record.set('mode', menuitem.value);
                            record.store.setAutoFilter(autoFilter);
                        }
                    }
                });
                this.resourceModeMenu.doAutoRender();
            }
            return this.resourceModeMenu;
        }
    },

    initComponent: function() {
        this.callParent(arguments);
        this.on('afterrender', function() {
            this.getView().on('afterrender', function() {
                this.getView().el.on('scroll', this.self.hideMenus, this.self);
                this.getView().on({
                    groupcollapse: {
                        fn: this.self.hideMenus,
                        scope: this.self
                    },
                    groupexpand: {
                        fn: this.self.hideMenus,
                        scope: this.self
                    }
                });
            }, this, {single: true});
        }, this, {single: true});
    },

    isDisabled: function(record) {
        return false;
    },

    processEvent: function(type, view, cell, recordIndex, cellIndex, e, record, row) {
        var me = this,
            mousedown = type == 'mousedown',
            changed = false,
            id = record.get('id'),
            autoFilter = view.store.getAutoFilter(),
            mode,
            target,
            value, name;

        if (type == 'click') {
            target = e.getTarget('.x-btn', null, true);
            if (target) {
                if (!target.hasCls('x-btn-disabled')) {
                    //resource access button
                    var menu = me.self.getAccessButtonMenu();
                    if (menu.isVisible() && menu.resourceRecord === record) {
                        menu.hide();
                    } else {
                        menu.setRecord(target, record, me);
                    }
                }
            } else {
                target = e.getTarget('.x-picker-field', null, true);
                if (target) {
                    if (!target.hasCls('x-form-readonly')) {
                        //resource mode combobox
                        var menu = me.self.getResourceModeMenu();
                        if (menu.isVisible() && menu.resourceRecord === record) {
                            menu.hide();
                        } else {
                            menu.setRecord(target, record, me);
                        }
                    }
                } else {
                    view.store.setAutoFilter(false);
                    target = e.getTarget('.' + me.cbCls, null, true);
                    if (target) {
                        if (!target.hasCls(me.cbDisabledCls)) {
                            //permission checkbox
                            name = target.getAttribute('data-value');
                            value = Ext.clone(record.get('permissions'));
                            value[name] = target.hasCls(me.cbCheckedCls) ? 0 : 1;
                            me.beforeChangePermission(name, value, record);
                            record.set('permissions', value);
                            me.afterChangeResource({permissions: value}, record);
                        }
                    } else {
                        target = e.getTarget('.' + me.cbModeCls, null, true);
                        if (target && !target.hasCls(me.cbDisabledCls)) {
                            //mode checkbox
                            if (target.hasCls(me.cbCheckedCls)) {
                                value = 1;/*MODE_ALL*/
                            } else {
                                if (me.definitions[id] && me.definitions[id][4]) {
                                    Ext.each(me.definitions[id][4], function(item){
                                        if (item['key'] != 1) {/*MODE_ALL*/
                                            value = item['key'];
                                            return false;
                                        }
                                    });

                                }
                            }
                            record.set('mode', value);
                        }
                    }

                    view.store.setAutoFilter(autoFilter);
                }
            }

        }
        return me.callParent(arguments);
    },

    renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
        var column = grid.panel.columns[colIndex],
            html = [];
        html.push(column.getResourceHtml(record));
        html.push(column.getPermissionsHtml(record));
        return column.cellRenderer ? column.cellRenderer(html.join(''), record) : html.join('');
    },

    getResourceHtml: function(record) {
        var html = [];
        html.push('<div class="x-resource">');
        html.push(this.getAccessButtonHtml(record));
        html.push(this.getResourceNameHtml(record));
        html.push(this.getResourceModeHtml(record));
        html.push('</div>');
        return html.join('');
    },
    getAccessButtonHtml: function(record) {
        var html,
            cls = [],
            permissions,
            text,
            value,
            mode,
            isBtnDisabled,
            id = record.get('id');

        if (this.getReadOnly()) {
            cls.push('x-btn-not-btn');
            isBtnDisabled = true;
        } else if (id == 264 || id == 265) {
            var parentRecord = record.store.getById(256);//RESOURCE_FARMS (RESOURCE_OWN/TEAM_FARMS)
            if (parentRecord.get('granted') == 1) {
                isBtnDisabled = true;
            }
        }

        if (record.get('granted') != 1) {
            cls.push('x-btn-red');
            if (isBtnDisabled) {
                cls.push('x-no-access');
            }
            text = 'No access';
            value = 'no';
        } else {
            permissions = Ext.Object.getValues(record.get('permissions'));
            if ((Ext.isEmpty(permissions) || !Ext.Array.contains(permissions, 0))) {
                cls.push('x-btn-green');
                if (isBtnDisabled) {
                    cls.push('x-full-access');
                }
                text = 'Full access';
                value = 'full';
            } else if (!Ext.Array.contains(permissions, 1)) {
                cls.push('x-btn-purple');
                if (isBtnDisabled) {
                    cls.push('x-readonly-access');
                }
                text = 'Read only';
                value = 'readonly';
            } else {
                if (isBtnDisabled) {
                    cls.push('x-limited-access');
                }
                text = 'Limited';
                value = 'limited';
            }
        }

        if (isBtnDisabled) {
            cls.push('x-btn-disabled');
        }

        html =
            '<a class="x-btn ' + cls.join(' ') +' x-unselectable x-btn-default-small" data-value="'+value+'">' +
            '<span style="table-layout:fixed;" class="x-btn-wrap x-btn-wrap-default-small x-btn-split x-btn-split-right">' +
            '<span class="x-btn-button x-btn-button-default-small x-btn-text x-btn-button-center">' +
            '<span class="x-btn-icon-el x-btn-icon-el-default-small"></span>' +
            '<span class="x-btn-inner x-btn-inner-default-small">' + text + '</span>' +
            '</span>' +
            '</span>' +
            '</a>';

        return html;
    },

    getResourceNameHtml: function(record) {
        return '<div class="x-resource-name">' + record.get('name') + '</div>';
    },

    getResourceModeHtml: function(record) {
        var me = this,
            html = [],
            cls = me.cbModeCls,
            id = record.get('id'),
            mode = record.get('mode');

        if (me.definitions[id] && me.definitions[id][4] && record.get('granted')) {
            //resource with modes
            var currentMode,
                modeAll,
                modes = [];
            Ext.each(me.definitions[id][4], function(item){
                if (item['key'] == 1) {/*MODE_ALL*/
                    modeAll = item;
                } else {
                    modes.push(item);
                }

                if (mode == item['key'] || !mode && item['default']) {
                    currentMode = item;
                }

            });

            if (modeAll) {
                if (me.getReadOnly()){
                    cls += ' ' + me.cbDisabledCls;
                }
                if (modeAll !== currentMode) {
                    cls += ' ' + me.cbCheckedCls;
                }
                html.push('<span class="' + cls + ' x-unselectable"><img src="' + Ext.BLANK_IMAGE_URL + '"/>Limit user access to volumes from</span>');
            }
            html.push(
                '<a style="display:inline-block;vertical-align:top;margin:0 0 0 12px;text-decoration:none" class="x-field x-picker-field x-form-item x-form-item-default x-form-type-text '+(modeAll === currentMode || me.getReadOnly() ? 'x-form-readonly' : '')+'" >' +
                    '<div class="x-form-item-body x-form-item-body-default x-form-text-field-body x-form-text-field-body-default" style="max-width:210px;min-width:100px">' +
                        '<div class="x-form-trigger-wrap x-form-trigger-wrap-default">' +
                            '<div class="x-form-text-wrap x-form-text-wrap-default">' +
                                '<span type="text" class="x-form-field x-form-required-field x-form-text x-form-text-default " autocomplete="off" readonly="readonly" value="">'+(currentMode && modeAll !== currentMode ? currentMode['name'] : (modes.length ? modes[0]['name'] : ''))+'</span>' +
                            '</div>' +
                            '<div class="x-form-trigger x-form-trigger-default x-form-arrow-trigger x-form-arrow-trigger-default "></div>' +
                        '</div>' +
                    '</div>' +
                '</a>'
            );

        }
        return html.join('');
    },

    getPermissionsHtml: function(record) {
        var me = this,
            html = [],
            permissions = record.get('permissions'),
            id = record.get('id');
        if (!Ext.isEmpty(permissions) && record.get('granted') && (Ext.Object.getSize(permissions) > 1 || permissions['manage'] === undefined)){
            Ext.Object.each(permissions, function(name, enabled){
                var cls = me.cbCls,
                    resource = me.definitions[record.get('id')],
                    description = resource && resource[3] && resource[3][name] ? resource[3][name] : '';

                if (enabled == 1) {
                    cls += ' ' + me.cbCheckedCls;
                }

                if (me.getReadOnly()){
                    cls += ' ' + me.cbDisabledCls;
                } else {
                    if (id == 264 || id == 265) {
                        var parentRecord = record.store.getById(256);//RESOURCE_FARMS (RESOURCE_OWN/TEAM_FARMS)
                        if (parentRecord.get('granted') == 1 && parentRecord.get('permissions')[name] == 1) {
                            cls += ' ' + me.cbDisabledCls;
                        }
                    }
                }

                html.push('<div class="' + cls + ' x-unselectable" data-value="' + name + '">');
                html.push('<img src="' + Ext.BLANK_IMAGE_URL + '"/>');
                html.push('<span class="x-permission-name">' + me.permissionLabelRenderer(name) + '</span>&nbsp;&nbsp;<span style="font-size:85%;color:#ccc;line-height:1.6em">' + description + '</span>');
                html.push('</div>');
            });
        }
        return html.join('');
    },

    permissionLabelRenderer: function(label) {
        var label = label === 'ssh-console' ? 'SSH Launcher' : label;
        label = Scalr.utils.capitalizeFirstLetter(label.replace('-', ' '));
        return label;
    },

    beforeChangePermission: function(name, value, record) {
        var id = record.get('id');
        if (id == 260) { //RESOURCE_ROLES_ENVIRONMENT
            if (value['create'] == 1) {
                if (name === 'create') {
                    Ext.apply(value, {bundletasks: 1, manage: 1});
                } else if ((name === 'bundletasks' || name === 'manage') && value[name] == 0){
                    value['create'] = 0;
                }
            }
        }
    },

    afterChangeResource: function(settings, record) {
        var id = record.get('id');
        if (id == 256) { //RESOURCE_FARMS (RESOURCE_OWN/TEAM_FARMS)
            // enable permission in child
            var childPermissions = {},
                childRecord;

            Ext.Object.each(settings.permissions, function(name, enabled){
                if (enabled == 1) {
                    childPermissions[name] = 1;
                }
            });

            childRecord = record.store.getById(264);
            childRecord.set('permissions', Ext.apply(childRecord.get('permissions'), childPermissions));
            if (settings.granted === 1) {
                childRecord.set('granted', 1);
            }
            //ExtJS v5.1.0 bug workaround: commiting record, which is not visible(filtered) at the moment, causes error
            var view = this.getView();
            if (view) childRecord.commit(!!Ext.isEmpty(view.getNode(childRecord)));

            childRecord = record.store.getById(265);
            childRecord.set('permissions', Ext.apply(childRecord.get('permissions'), childPermissions));
            if (settings.granted === 1) {
                childRecord.set('granted', 1);
            }

            // if full access - also enable create permission
            if (!Ext.Array.contains(Ext.Object.getValues(settings.permissions), 0)) {
                childRecord.set('permissions', Ext.apply(childRecord.get('permissions'), { create: 1}));
            }

            //ExtJS v5.1.0 bug workaround: commiting record, which is not visible(filtered) at the moment, causes error
            if (view) childRecord.commit(!!Ext.isEmpty(this.ownerCt.view.getNode(childRecord)));
        }
    }
});

Ext.define('Scalr.ui.RoleEnvironmentsColumn', {
    extend: 'Ext.grid.column.Column',
    alias: 'widget.roleenvironmentscolumn',

    tdCls: Ext.baseCSSPrefix + 'grid-cell-aclresourcecolumn',
    innerCls: Ext.baseCSSPrefix + 'grid-cell-inner-aclresourcecolumn',

    cbCls: Ext.baseCSSPrefix + 'permission-cb',
    cbModeCls: Ext.baseCSSPrefix + 'mode-cb',
    cbCheckedCls: Ext.baseCSSPrefix + 'cb-checked',
    cbDisabledCls: Ext.baseCSSPrefix + 'cb-disabled',

    config: {
        readOnly: false
    },

    statics: {
        hideMenus: function() {
            if (this.accessButtonMenu) this.accessButtonMenu.hide();
        },
        getAccessButtonMenu: function() {
            if (!this.accessButtonMenu) {
                this.accessButtonMenu = Ext.create('Ext.menu.Menu', {
                    cls: 'x-menu-light x-menu-light-no-icon',
                    width: 140,
                    setRecord: function(btnEl, record, column){
                        var items = [],
                            value = btnEl.getAttribute('data-value');
                        items.push({
                            xtype: 'menuitem',
                            text: '<span style="color:#008000">Available</span>',
                            value: 1
                        },{
                            xtype: 'menuitem',
                            text: '<span style="color:#c00000">Restricted</span>',
                            value: 0
                        });

                        Ext.each(items, function(item){
                            if (item.value == value) {
                                item.cls = 'x-menu-item-current';
                                return false;
                            }
                        });

                        this.hide();
                        this.removeAll();
                        this.add(items);

                        this.envRecord = record;
                        this.parentColumn = column;

                        var xy = btnEl.getXY(), sizeX = xy[1] + btnEl.getHeight() + this.getHeight();
                        if (sizeX > Scalr.application.getHeight()) {
                            xy[1] -= sizeX - Scalr.application.getHeight();
                        }
                        this.showBy(btnEl, 'tl-bl');
                        this.focus();
                    },
                    defaults: {
                        handler: function(menuitem) {
                            var menu = menuitem.parentMenu,
                                record = menu.envRecord,
                                autoFilter,
                                settings = {};

                            autoFilter = record.store.getAutoFilter();
                            record.store.setAutoFilter(false);
                            record.set({enabled: menuitem.value});
                            record.store.setAutoFilter(autoFilter);
                        }
                    }
                });
                this.accessButtonMenu.doAutoRender();
            }
            return this.accessButtonMenu;
        },
    },

    initComponent: function() {
        this.callParent(arguments);
        this.on('afterrender', function() {
            this.getView().on('afterrender', function() {
                this.getView().el.on('scroll', this.self.hideMenus, this.self);
                this.getView().on({
                    groupcollapse: {
                        fn: this.self.hideMenus,
                        scope: this.self
                    },
                    groupexpand: {
                        fn: this.self.hideMenus,
                        scope: this.self
                    }
                });
            }, this, {single: true});
        }, this, {single: true});
    },

    isDisabled: function(record) {
        return false;
    },

    processEvent: function(type, view, cell, recordIndex, cellIndex, e, record, row) {
        var me = this,
            target;

        if (type == 'click') {
            target = e.getTarget('.x-btn', null, true);
            if (target) {
                if (!target.hasCls('x-btn-disabled')) {
                    //access button
                    var menu = me.self.getAccessButtonMenu();
                    if (menu.isVisible() && menu.envRecord === record) {
                        menu.hide();
                    } else {
                        menu.setRecord(target, record, me);
                    }
                }
            }
        }
        return me.callParent(arguments);
    },

    renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
        var column = grid.panel.columns[colIndex],
            html = [];
        html.push(column.getAccessHtml(record));
        return column.cellRenderer ? column.cellRenderer(html.join(''), record) : html.join('');
    },

    getAccessHtml: function(record) {
        var html = [];
        html.push('<div class="x-resource">');
        html.push(this.getAccessButtonHtml(record));
        html.push(this.getResourceNameHtml(record));
        html.push('</div>');
        return html.join('');
    },
    getAccessButtonHtml: function(record) {
        var html,
            cls,
            text,
            value,
            isBtnDisabled

        if (this.getReadOnly()) {
            isBtnDisabled = true;
        }

        if (record.get('enabled') != 1) {
            cls = 'x-btn-red';
            text = 'Restricted';
            value = 0;
        } else {
            cls = 'x-btn-green';
            text = 'Available';
            value = 1;
        }

        if (isBtnDisabled) {
            cls += ' x-btn-disabled';
        }

        html =
            '<a class="x-btn ' + cls +' x-unselectable x-btn-default-small" data-value="'+value+'">' +
            '<span style="table-layout:fixed;" class="x-btn-wrap x-btn-wrap-default-small x-btn-split x-btn-split-right">' +
            '<span class="x-btn-button x-btn-button-default-small x-btn-text x-btn-button-center">' +
            '<span class="x-btn-icon-el x-btn-icon-el-default-small"></span>' +
            '<span class="x-btn-inner x-btn-inner-default-small">' + text + '</span>' +
            '</span>' +
            '</span>' +
            '</a>';

        return html;
    },

    getResourceNameHtml: function(record) {
        return '<div class="x-resource-name">' + record.get('name') + '</div>';
    }

});

/**
 * Config has one specific parameter: `client`. Acceptable values: 'popup', 'dashboard'.
 * @DependsOn Scalr.utils.announcementHelper
 * @DependsOn Scalr.utils.announcement
 */
Ext.define('Scalr.ui.AnnouncementsView', {
    extend: 'Ext.view.View',
    alias: 'widget.announcementsview',

    maxHeight: 720,
    overflowY: 'auto',
    preserveScrollOnRefresh: true,
    itemSelector: '.x-announcements-div',
    collectData: function () {
        var data = this.callParent(arguments);
        data.clientCls = this.client;

        return data;
    },
    tpl: [
        '<div class="x-announcements-client-{clientCls}">',
            '<tpl for="."><div class="x-announcements-div">',
                '<img class="x-announcements-type x-announcements-icon x-announcements-icon-{type}" data-qtip="{[this.getTooltipHtml(values)]}" src="' + Ext.BLANK_IMAGE_URL + '" />',
                '<tpl if="isNew">',
                    '<span class="x-announcements-info x-announcements-icon x-announcements-icon-info">New</span>',
                '</tpl>',
                '<span class="x-announcements-desc">{time}</span>',
                '<div class="x-announcements-data">',
                    '<tpl if="type === \'message\'">',
                        '<span class="x-announcements-title x-announcements-title-msg">{[this.title(values.title)]}</span>',
                        '<div class="x-announcements-message-msg">{[this.msgText(values.msg)]}</div>',
                    '<tpl else>',
                        '<a href="{url}" target="_blank"><span class="x-announcements-title">{title}</span></a>',
                    '</tpl>',
                '</div>',
            '</div></tpl>',
        '</div>',
        {
            disableFormats: true,
            title: function (title) {
                return Scalr.utils.announcementHelper.title(title);
            },
            msgText: function (text) {
                return Scalr.utils.announcementHelper.formatMsg(text);
            },
            getTooltipHtml: function (values) {
                var type;
                switch (values.type) {
                    case 'scalrblog': type = 'Blog'; break;
                    case 'changelog': type = 'Changelog'; break;
                    case 'message':   type = 'Announcement'; break;
                    default: type = 'unknown';
                }

                return type;
            }
        }
    ],

    store: Scalr.utils.announcement.store,

    listeners: {
        itemclick: function (view, record, item, index, e) {
            var target = e.getTarget();

            if (target.className === 'x-announcements-title' && 'scalrblog' === record.get('type')) {
                if (typeof _gaq !== 'undefined') {
                    _gaq.push(['_trackEvent', 'ProductBlog', 'Open', target.innerHTML]);
                }
            }
        }
    }
});

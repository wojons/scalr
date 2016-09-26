 /*
 * Form plugins
 */
Ext.define('Scalr.ui.ScalrTagFieldPlugin', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.addnewtag',

    init: function (component) {
        var me = this;

        component.on('render', function (component) {
            var picker = component.getPicker();
            picker.addCls('x-boundlist-hideemptygrid');

            picker.on({
                render: function (pickerItSelf) {
                    if (!me.disabled) {
                        picker.el.addCls('x-boundlist-with-addnew');
                    }

                    me.el = picker.el.createChild({
                        tag: 'div',
                        cls: 'x-boundlist-addnewtag'
                    }).on('click', function(e) {
                        e.stopEvent(); // we don't want to reset current combobox value
                    }).setVisible(!me.disabled);
                },

                focuschange: function (pickerItSelf, oldFocused, newFocused) {
                    picker.focusedNode = newFocused;

                    if (!Ext.isEmpty(picker.el)) {
                        me.setText();
                    }
                },

                itemmouseenter: function (pickerItSelf, record, item) {
                    //picker.getNavigationModel().record = record;
                    picker.fireEvent('focuschange', picker, picker.focusedNode, record);
                    me.setFocusedNode(item);
                },

                itemmouseleave: function (pickerItSelf, record, item) {
                    picker.getNavigationModel().record = null;
                    picker.fireEvent('focuschange', picker, picker.focusedNode, null);
                },

                select: function () {
                    me.fieldNewValue = '';
                },

                deselect: function (pickerItSelf, record) {
                    me.fieldNewValue = '';

                    me.setFocusedNode(
                        picker.getNode(record)
                    );
                },

                itemmousedown: function (pickerItSelf, record) {
                    me.setFocusedNode(
                        picker.getNode(record)
                    );
                },

                selectionchange: function () {
                    me.setText();
                },

                scope: picker
            });

            component.on('beforequery', function (queryPlan) {
                var pickerEl = picker.el;

                component.expand(); // when store is empty picker doesn't appear

                if (pickerEl) {
                    var enteredValue = Ext.String.htmlEncode(queryPlan.query);
                    var record = picker.getStore().findRecord('tag', enteredValue, 0, false, false, true);
                    var isRecordExists = !Ext.isEmpty(record);

                    me.fieldNewValue = !isRecordExists ? enteredValue : '';
                    component.suspendCollapse = enteredValue ? true : false;
                    me.setDisabled(!enteredValue);

                    me.setText();

                    return;
                }

                me.setDisabled(true);
            });
        });
    },

    // fixed bug with a loss of focus (selected record)
    setFocusedNode: function (node) {
        var me = this;

        var picker = me.getCmp().getPicker();

        if (!Ext.isEmpty(picker.el)) {
            Ext.Array.each(
                picker.el.query('.x-boundlist-item-over'),
                function (focusedNode) {
                    Ext.get(focusedNode).removeCls('x-boundlist-item-over');
                }
            );

            if (!Ext.isEmpty(node)) {
                Ext.get(node).addCls('x-boundlist-item-over');
                //picker.focusedNode = node;
            }
        }

        return me;
    },

    getOnTabText: function (tagName, isSelected) {
        var me = this;

        if (Ext.isEmpty(tagName)) {
            return '';
        }

        return '<span class="x-boundlist-addnewtag-focused-text">' +
            (!Ext.isEmpty(me.fieldNewValue) ? ' or ' : 'Press ') +
            '<b>Tab</b> to ' + (isSelected ? 'de' : '') +
            'select<span class="x-boundlist-addnewtag-text-tagname">"' +
            tagName +
            '"</span>';
    },

    getOnEnterText: function (tagName) {
        if (Ext.isEmpty(tagName)) {
            return '';
        }
        return '<span>Press <b>Enter</b> to add</span>' +
            '<span class="x-boundlist-addnewtag-text-tagname">' +
            '"' + tagName + '"' +
            '</span>';
    },

    setText: function () {
        var me = this;

        var picker = me.getCmp().getPicker();
        var pickerEl = picker.el;
        var text = '';

        if (!Ext.isEmpty(pickerEl)) {
            var focusedNode = picker.focusedNode;
            var showTabText = !Ext.isEmpty(focusedNode) && picker.getStore().getCount() > 0;

            text = me.getOnEnterText(me.fieldNewValue) +
                me.getOnTabText(
                    showTabText ? focusedNode.get('tag') : '',
                    showTabText ? picker.getSelectionModel().isSelected(focusedNode) : false
                );

            pickerEl.down('.x-boundlist-addnewtag').update(
                '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-info style="padding-left:6px" />&nbsp;&nbsp;' +
                text
            );
        }

        me.setDisabled(
            Ext.isEmpty(text)
        );

        return me;
    },

    setDisabled: function (disabled) {
        var me = this;

        var element = me.el;

        if (Ext.isDefined(element)) {
            var parent = element.parent();

            if (!Ext.isEmpty(parent)) {
                parent[disabled ? 'removeCls' : 'addCls']('x-boundlist-with-addnew');
            }

            element.setVisible(!disabled);
        }

        me.disabled = disabled;
    }
});

Ext.define('Scalr.ui.ComboAddNewPlugin', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.comboaddnew',
    url: '',
    postUrl: '',
    disabled: false,
    isRedirect: false,
    applyNewValue: true,

    init: function(comp) {
        var me = this;

        // to preserve offset for add button
        Ext.override(comp, {
            alignPicker: function() {
                this.callParent();

                var me = this,
                    picker = me.getPicker(),
                    pickerEl = picker.getEl(),
                    itemContainerEl = pickerEl.down('.x-boundlist-list-ct'),
                    itemContainerElHeight = itemContainerEl.getHeight();

                if (pickerEl.down('.x-boundlist-addnew').isVisible() && itemContainerElHeight >= pickerEl.getHeight()) {
                    itemContainerEl.setHeight(itemContainerElHeight - 32);
                }
            }
        });

        comp.on('render', function() {
            var picker = this.getPicker();
            picker.addCls('x-boundlist-hideemptygrid');
            picker.on('render', function() {
                if (!me.disabled) {
                    this.el.addCls('x-boundlist-with-addnew');
                }
                me.el = this.el.createChild({
                    tag: 'div',
                    cls: 'x-boundlist-addnew',
                    title: 'Add new'
                }).on('click', function(e) {
                    comp.collapse();
                    me.handler();
                    e.stopEvent();//we don't want to reset current combobox value
                }).setVisible(!me.disabled);
            });
        });

        Scalr.event.on('update', function(type, element) {
            if (type == me.url) {
                if (me.applyNewValue) {
                    this.store.add(element);
                    this.setValue(element[this.valueField]);
                }
                this.fireEvent('addnew', element);
            }
        }, comp);
    },

    setDisabled: function(disabled) {
        if (this.el !== undefined) {
            var parent = this.el.parent();
            if (parent) {
                parent[disabled ? 'removeCls' : 'addCls']('x-boundlist-with-addnew');
            }
            this.el.setVisible(!disabled);
        }
        this.disabled = disabled;
    },

    handler: function() {
        Scalr.event.fireEvent(this.isRedirect ? 'redirect' : 'modal', '#' + this.url + this.postUrl, false, this.redirectParams);
    }
});

/*
 * Grid plugins
 */

Ext.define('Scalr.ui.ApplyParamsPlugin', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.applyparams',

    firstLoad: true,

    updateLinkOnStoreDataChanged: true,

    loadStoreOnReturn: true,

    expectingFilterResponse: false,

    initHiddenParams: function () {
        var me = this;

        var hiddenParams = me.hiddenParams;

        me.hiddenParams = Ext.isArray(hiddenParams)
            ? hiddenParams : [];

        Ext.Array.push(me.hiddenParams, 'new');

        return me;
    },

    initForbiddenParams: function () {
        var me = this;

        var forbiddenParams = me.storeForbiddenParams;

        me.storeForbiddenParams = Ext.isArray(forbiddenParams)
            ? forbiddenParams : [];

        Ext.Array.push(me.storeForbiddenParams, 'new');

        return me;
    },

    initFilterIgnoreParams: function () {
        var me = this;

        var filterIgnoreParams = me.filterIgnoreParams;

        me.filterIgnoreParams = Ext.isArray(filterIgnoreParams)
            ? filterIgnoreParams : [];

        return me;
    },

    initForbiddenFilters: function () {
        var me = this;

        var forbiddenFilters = me.forbiddenFilters;

        me.forbiddenFilters = Ext.isArray(forbiddenFilters)
            ? forbiddenFilters : [];

        return me;
    },

    init: function (grid) {
        var me = this;

        me.grid = grid;

        me.store = grid.getStore();
        me.store.preventLoading = true;

        me.toolbar = grid.child('toolbar');

        me.
            initHiddenParams().
            initForbiddenParams().
            initFilterIgnoreParams().
            initForbiddenFilters().
            initSubscriber();

        me._cachedParams = {};

        if (me.updateLinkOnStoreDataChanged) {
            me.subscribeToStore();
        }
    },

    isDisabled: function () {
        return this.disabled;
    },

    hasScalrOptions: function (component) {
        return Ext.isDefined(component.scalrOptions);
    },

    subscribeApplyParamsEvent: function (subscriber) {
        var me = this;

        me.subscriber = subscriber;

        subscriber.on({
            applyparams: me.applyParams,
            activate: me.enable,
            deactivate: me.disable,
            reloadstore: me.reloadStore,
            scope: me
        });

        return me;
    },

    initSubscriber: function () {
        var me = this;

        var grid = me.grid;

        grid.addButtonTrigger = me._addButtonTrigger;

        if (me.hasScalrOptions(grid)) {
            me.subscribeApplyParamsEvent(grid);

            return me;
        }

        grid.on('beforerender', function () {
            me.subscribeApplyParamsEvent(
                grid.up('[scalrOptions]')
            );
        });

        return me;
    },

    toQueryString: function (object) {
        Ext.Object.each(object, function (key, value) {
            if (!value || value === '0') {
                delete object[key];
            }
        });

        return Ext.Object.toQueryString(object);
    },

    getPureLink: function () {
        var url = document.URL;
        var separatorIndex = url.indexOf('#');
        var pagePath = url.substring(separatorIndex, url.length);
        var paramsIndex = pagePath.indexOf('?');

        return url.substring(0, separatorIndex) +
            (paramsIndex !== -1 ? pagePath.substring(0, paramsIndex) : pagePath);
    },

    setLink: function (params) {
        var me = this;

        var pureLink = me.getPureLink();
        var stringParams = me.toQueryString(
            me.excludeProperties(params, me.hiddenParams)
        );

        history.replaceState(
            null, null, !stringParams
                ? pureLink
                : pureLink + '?' + stringParams
        );

        return me;
    },

    subscribeToStore: function () {
        var me = this;

        var store = me.store;
        store = !store.getProxy()
            ? store.source
            : store;

        store.on('datachanged', function () {
            me.setLink(
                store.getProxy().extraParams
            );
        }, me);

        return me;
    },

    applyToStore: function (params) {
        var me = this;

        var store = me.store;
        store.preventLoading = false;
        store.applyProxyParams(params);

        return me;
    },

    getFilters: function () {
        var me = this;

        return me.toolbar.query(
            '[name][isFormField=true][xtype!=filterfield]'

            + Ext.Array.map(me.forbiddenFilters, function (filterName) {
                return '[name!=' + filterName + ']';
            }).join('')
        );
    },

    formatFilterValue: function (value) {
        if (value === 'true') {
            value = true;
        } else if (value === 'false') {
            value = false;
        }

        return value;
    },

    isExpectingFilters: function () {
        return this.expectingFilterResponse;
    },

    applyTo: function (filter, value) {
        var me = this;

        if (!Ext.isEmpty(value)) {
            filter.setValue(
                me.formatFilterValue(value)
            );

            return true;
        }

        if (!me.firstLoad) {

            if (filter.allowBlank === false) {
                me.requiredValues[filter.name] = filter.getValue();
                return false;
            }

            if (Ext.isDefined(filter.originalValue)) {
                filter.reset();
            } else {
                filter.setValue(null);
            }

            return false;
        }

        me.defaultValues[filter.name] = filter.getValue();

        return false;
    },

    applyToSingleFilters: function (params) {
        var me = this;

        var appliedParamKeys = [];

        Ext.Array.each(me.getFilters(), function (filter) {
            var filterName = filter.name;

            if (me.applyTo(filter, params[filterName])) {
                appliedParamKeys.push(filterName);
            }
        });

        return appliedParamKeys;
    },

    excludeProperties: function (object, properties) {
        var result = {};

        Ext.Object.each(object, function (key, value) {
            if (properties.indexOf(key) === -1) {
                result[key]  = value;
            }
        });

        return result;
    },

    applyToFilterField: function (params, appliedParamKeys) {
        var me = this;

        var filterField = me.toolbar.down('filterfield');

        if (filterField && filterField.remoteSort) {
            filterField.resetFilter();

            var filterFieldParams = me.excludeProperties(
                params, Ext.Array.merge(
                    appliedParamKeys,
                    me.filterIgnoreParams,
                    me.storeForbiddenParams
                )
            );

            if (filterField && !Ext.Object.isEmpty(filterFieldParams)) {
                var compiledValue = filterField.compileValue(filterFieldParams);

                filterField.setValue(compiledValue);
                filterField.lastValue = compiledValue;
                filterField.appliedParamKeys = Ext.Object.getKeys(filterFieldParams);

                filterField.getTrigger('cancelButton').
                    show();
            }
        }

        return me;
    },

    applyToFilters: function (params) {
        var me = this;

        me.defaultValues = {};
        me.requiredValues = {};

        var appliedParams = me.applyToSingleFilters(params);

        me.applyToFilterField(params, appliedParams);

        return me.firstLoad ? me.defaultValues : me.requiredValues;
    },

    _addButtonTrigger: function () {
        var me = this;

        var button = me.down('#add');

        if (!Ext.isEmpty(button) && button.enableToggle) {
            button.toggle(true);

            var setFormForceVisibility = function (form) {
                if (button.pressed) {
                    form.show();
                }
            };

            var form = me.up('panel').down('form');

            form.on('hide', setFormForceVisibility);

            button.on('toggle', function (button, state) {
                if (!state) {
                    form.un('hide', setFormForceVisibility)
                }
            });
        }

        return me;
    },

    deleteEmptyKeys: function (object) {
        Ext.Object.each(object, function (key, value) {
            if (Ext.isEmpty(value)) {
                delete object[key];
            }
        });

        return object;
    },

    wereParamsChanged: function (params) {
        var me = this;

        var proxyParams = me.store.getProxy().extraParams;

        return !(
            ( Ext.Object.isEmpty(params) && !Ext.Object.isEmpty(proxyParams) )
            || ( Ext.Object.equals(params, me.deleteEmptyKeys(proxyParams)) )
        );
    },

    reloadStore: function() {
        this.applyToStore();
    },

    //todo: refact this

    applyParams: function (params) {
        var me = this;

        var field = me.toolbar.down('[beforeApplyParams]');

        if (me.firstLoad && !me.isExpectingFilters() && field !== null) {
            me.expectingFilterResponse = true;

            field.beforeApplyParams(function () {
                me.applyParams(params);
            });

            return me;
        }

        me.expectingFilterResponse = false;

        var store = me.store;

        if (me.isDisabled() && !me.wereParamsChanged(params)) {
            me.setLink(
                store.getProxy().extraParams
            );

            if (me.loadStoreOnReturn) {
                me.applyToStore(params);
            }

            return me;
        }

        store.
            clearAllProxyParams().
            preventLoading = true;

        if (params.new) {
            me.
                setLink(params).
                applyToStore(null).
                grid.
                    addButtonTrigger();

            return me;
        }

        var resetOn = me.subscriber.resetOn;

        if (params[resetOn]) {
            var newParams = {};
            newParams[resetOn] = params[resetOn];
            params = newParams;

            Ext.Array.each(me.getFilters(), function (filter) {
                filter.setValue(null);
            });
        }

        var filtersValues = me.applyToFilters(params);

        me.applyToStore(
            Ext.Object.merge(params, filtersValues)
        );

        me.firstLoad = false;

        return me;
    }
});

Ext.define('Scalr.ui.GridStorePlugin', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.gridstore',
    loadMask: false,
    highlightNew: false,

    init: function (client) {
        var me = this;
        client.getView().loadMask = this.loadMask;
        client.store.proxy.view = client.getView(); // :(

        if (me.highlightNew) {
            var view = client.getView(), prevFn = view.getRowClass;

            view.getRowClass = function(record) {
                var cls = prevFn ? (prevFn.apply(view, arguments) || '') : '';
                if (record.get('gridHighlightItem'))
                    cls = cls + ' x-grid-row-color-new';

                return cls;
            };
        }

        client.store.on({
            scope: client,
            beforeload: function (store) {
                if (me.highlightNew && store.gridHightlightNew) {
                    store.gridHightlight = store.data.clone();
                    delete store.gridHightlightNew;
                }

                if (this.getView().rendered) {
                    this.getView().clearViewEl();
                    // TODO: make it better, ExtJS leaves div, so remove it by ourselves
                    var ge = this.getView().el.child('.x-grid-error')
                    if (ge)
                        ge.remove();
                }

                if (! this.getView().loadMask && !this.processBox)
                    this.processBox = Scalr.utils.CreateProcessBox({
                        type: 'action',
                        msg: client.getView().loadingText
                    });
            },
            load: function (store) {
                if (store.gridHightlight) {
                    store.each(function(record) {
                        if (! store.gridHightlight.getByKey(record.getId()))
                            record.set('gridHighlightItem', 1);
                    });

                    delete store.gridHightlight;
                }

                if (this.isDestroyed || !this.getView().loadMask) {
                    if (this.processBox) {
                        this.processBox.destroy();
                        delete this.processBox;
                    }
                }
            }
        });

        client.store.proxy.on({
            exception: function (proxy, response, operation, options) {
                if (client.store.gridHightlight)
                    delete client.store.gridHightlight;

                if (response.status == 403 || response.status == 0) {
                    Scalr.state.userNeedRefreshStoreAfter = true;
                }

                var message = 'Unable to load data';
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

                message += '. <a href="#">Refresh</a>';

                proxy.view.update('<div class="x-grid-error">' + message + '</div>');
                proxy.view.el.down('a').on('click', function (e) {
                    e.preventDefault();
                    client.store.load();
                });
                proxy.view.refreshSize();
            }
        });
    }
});

Ext.define('Scalr.ui.GridRowTooltip', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.rowtooltip',

    cls: '',

    anchor: '',

    minWidth: 300,
    maxWidth: 500,

    beforeShow: Ext.emptyFn,

    init: function (client) {
        var me = this;

        client.on('afterrender', function () {
            var view = client.getView();

            client.rowtooltip = Ext.create('Ext.tip.ToolTip', {
                cls: me.cls,
                anchor: me.anchor,
                target: client.getId(),
                delegate: view.itemSelector,
                //trackMouse: true,
                owner: client,
                minWidth: me.minWidth,
                maxWidth: me.maxWidth,
                listeners: {
                    beforeshow: me.beforeShow
                }
            });
        });
        client.on('destroy', function(){
            Ext.destroy(client.rowtooltip);
            delete client.rowtooltip;
        });
    }
});

// (5.0)
Ext.define('Ext.ux.BoxReorderer', {
    requires: [
        'Ext.dd.DD'
    ],

    mixins: {
        observable: 'Ext.util.Observable'
    },

    /**
     * @cfg {String} itemSelector
     * A {@link Ext.DomQuery DomQuery} selector which identifies the encapsulating elements of child
     * Components which participate in reordering.
     */
    itemSelector: '.x-box-item',

    /**
     * @cfg {Mixed} animate
     * If truthy, child reordering is animated so that moved boxes slide smoothly into position.
     * If this option is numeric, it is used as the animation duration in milliseconds.
     */
    animate: 100,

    /**
     * @event StartDrag
     * Fires when dragging of a child Component begins.
     * @param {Ext.ux.BoxReorderer} this
     * @param {Ext.container.Container} container The owning Container
     * @param {Ext.Component} dragCmp The Component being dragged
     * @param {Number} idx The start index of the Component being dragged.
     */

    /**
     * @event Drag
     * Fires during dragging of a child Component.
     * @param {Ext.ux.BoxReorderer} this
     * @param {Ext.container.Container} container The owning Container
     * @param {Ext.Component} dragCmp The Component being dragged
     * @param {Number} startIdx The index position from which the Component was initially dragged.
     * @param {Number} idx The current closest index to which the Component would drop.
     */

    /**
     * @event ChangeIndex
     * Fires when dragging of a child Component causes its drop index to change.
     * @param {Ext.ux.BoxReorderer} this
     * @param {Ext.container.Container} container The owning Container
     * @param {Ext.Component} dragCmp The Component being dragged
     * @param {Number} startIdx The index position from which the Component was initially dragged.
     * @param {Number} idx The current closest index to which the Component would drop.
     */

    /**
     * @event Drop
     * Fires when a child Component is dropped at a new index position.
     * @param {Ext.ux.BoxReorderer} this
     * @param {Ext.container.Container} container The owning Container
     * @param {Ext.Component} dragCmp The Component being dropped
     * @param {Number} startIdx The index position from which the Component was initially dragged.
     * @param {Number} idx The index at which the Component is being dropped.
     */

    constructor: function() {
        this.mixins.observable.constructor.apply(this, arguments);
    },

    init: function(container) {
        var me = this;

        me.container = container;

        // Set our animatePolicy to animate the start position (ie x for HBox, y for VBox)
        me.animatePolicy = {};
        me.animatePolicy[container.getLayout().names.x] = true;



        // Initialize the DD on first layout, when the innerCt has been created.
        me.container.on({
            scope: me,
            boxready: me.onBoxReady,
            beforedestroy: me.onContainerDestroy
        });
    },

    /**
     * @private Clear up on Container destroy
     */
    onContainerDestroy: function() {
        var dd = this.dd;
        if (dd) {
            dd.unreg();
            this.dd = null;
        }
    },

    onBoxReady: function() {
        var me = this,
            layout = me.container.getLayout(),
            names = layout.names,
            dd;

        // Create a DD instance. Poke the handlers in.
        // TODO: Ext5's DD classes should apply config to themselves.
        // TODO: Ext5's DD classes should not use init internally because it collides with use as a plugin
        // TODO: Ext5's DD classes should be Observable.
        // TODO: When all the above are trus, this plugin should extend the DD class.
        dd = me.dd = new Ext.dd.DD(layout.innerCt, me.container.id + '-reorderer');
        Ext.apply(dd, {
            animate: me.animate,
            reorderer: me,
            container: me.container,
            getDragCmp: me.getDragCmp,
            clickValidator: Ext.Function.createInterceptor(dd.clickValidator, me.clickValidator, me, false),
            onMouseDown: me.onMouseDown,
            startDrag: me.startDrag,
            onDrag: me.onDrag,
            endDrag: me.endDrag,
            getNewIndex: me.getNewIndex,
            doSwap: me.doSwap,
            findReorderable: me.findReorderable
        });

        // Decide which dimension we are measuring, and which measurement metric defines
        // the *start* of the box depending upon orientation.
        dd.dim = names.width;
        dd.startAttr = names.beforeX;
        dd.endAttr = names.afterX;
    },

    getDragCmp: function(e) {
        return this.container.getChildByElement(e.getTarget(this.itemSelector, 10));
    },

    // check if the clicked component is reorderable
    clickValidator: function(e) {
        var cmp = this.getDragCmp(e);

        // If cmp is null, this expression MUST be coerced to boolean so that createInterceptor is able to test it against false
        return !!(cmp && cmp.reorderable !== false);
    },

    onMouseDown: function(e) {
        var me = this,
            container = me.container,
            containerBox,
            cmpEl,
            cmpBox;

        // Ascertain which child Component is being mousedowned
        me.dragCmp = me.getDragCmp(e);
        if (me.dragCmp) {
            cmpEl = me.dragCmp.getEl();
            me.startIndex = me.curIndex = container.items.indexOf(me.dragCmp);

            // Start position of dragged Component
            cmpBox = cmpEl.getBox();

            // Last tracked start position
            me.lastPos = cmpBox[me.startAttr];

            // Calculate constraints depending upon orientation
            // Calculate offset from mouse to dragEl position
            containerBox = container.el.getBox();
            if (me.dim === 'width') {
                me.minX = containerBox.left;
                me.maxX = containerBox.right - cmpBox.width;
                me.minY = me.maxY = cmpBox.top;
                me.deltaX = e.getX() - cmpBox.left;
            } else {
                me.minY = containerBox.top;
                me.maxY = containerBox.bottom - cmpBox.height;
                me.minX = me.maxX = cmpBox.left;
                me.deltaY = e.getY() - cmpBox.top;
            }
            me.constrainY = me.constrainX = true;
        }
    },

    startDrag: function() {
        var me = this,
            dragCmp = me.dragCmp;

        if (dragCmp) {
            // For the entire duration of dragging the *Element*, defeat any positioning and animation of the dragged *Component*
            dragCmp.setPosition = Ext.emptyFn;
            dragCmp.animate = false;

            // Animate the BoxLayout just for the duration of the drag operation.
            if (me.animate) {
                me.container.getLayout().animatePolicy = me.reorderer.animatePolicy;
            }
            // We drag the Component element
            me.dragElId = dragCmp.getEl().id;
            me.reorderer.fireEvent('StartDrag', me, me.container, dragCmp, me.curIndex);
            // Suspend events, and set the disabled flag so that the mousedown and mouseup events
            // that are going to take place do not cause any other UI interaction.
            dragCmp.suspendEvents();
            dragCmp.disabled = true;
            dragCmp.el.setStyle('zIndex', 100);
        } else {
            me.dragElId = null;
        }
    },

    /**
     * @private
     * Find next or previous reorderable component index.
     * @param {Number} newIndex The initial drop index.
     * @return {Number} The index of the reorderable component.
     */
    findReorderable: function(newIndex) {
        var me = this,
            items = me.container.items,
            newItem;

        if (items.getAt(newIndex).reorderable === false) {
            newItem = items.getAt(newIndex);
            if (newIndex > me.startIndex) {
                while(newItem && newItem.reorderable === false) {
                    newIndex++;
                    newItem = items.getAt(newIndex);
                }
            } else {
                while(newItem && newItem.reorderable === false) {
                    newIndex--;
                    newItem = items.getAt(newIndex);
                }
            }
        }

        newIndex = Math.min(Math.max(newIndex, 0), items.getCount() - 1);

        if (items.getAt(newIndex).reorderable === false) {
            return -1;
        }
        return newIndex;
    },

    /**
     * @private
     * Swap 2 components.
     * @param {Number} newIndex The initial drop index.
     */
    doSwap: function(newIndex) {
        var me = this,
            items = me.container.items,
            container = me.container,
            wasRoot = me.container._isLayoutRoot,
            orig, dest, tmpIndex;

        newIndex = me.findReorderable(newIndex);

        if (newIndex === -1) {
            return;
        }

        me.reorderer.fireEvent('ChangeIndex', me, container, me.dragCmp, me.startIndex, newIndex);
        orig = items.getAt(me.curIndex);
        dest = items.getAt(newIndex);
        items.remove(orig);
        tmpIndex = Math.min(Math.max(newIndex, 0), items.getCount() - 1);
        items.insert(tmpIndex, orig);
        items.remove(dest);
        items.insert(me.curIndex, dest);

        // Make the Box Container the topmost layout participant during the layout.
        container._isLayoutRoot = true;
        container.updateLayout();
        container._isLayoutRoot = wasRoot;
        me.curIndex = newIndex;
    },

    onDrag: function(e) {
        var me = this,
            newIndex;

        newIndex = me.getNewIndex(e.getPoint());
        if ((newIndex !== undefined)) {
            me.reorderer.fireEvent('Drag', me, me.container, me.dragCmp, me.startIndex, me.curIndex);
            me.doSwap(newIndex);
        }

    },

    endDrag: function(e) {
        if (e) {
            e.stopEvent();
        }
        var me = this,
            layout = me.container.getLayout(),
            temp;

        if (me.dragCmp) {
            delete me.dragElId;

            // Reinstate the Component's positioning method after mouseup, and allow the layout system to animate it.
            delete me.dragCmp.setPosition;
            me.dragCmp.animate = true;

            // Ensure the lastBox is correct for the animation system to restore to when it creates the "from" animation frame
            me.dragCmp.lastBox[layout.names.x] = me.dragCmp.getPosition(true)[layout.names.widthIndex];

            // Make the Box Container the topmost layout participant during the layout.
            me.container._isLayoutRoot = true;
            me.container.updateLayout();
            me.container._isLayoutRoot = undefined;

            // Attempt to hook into the afteranimate event of the drag Component to call the cleanup
            temp = Ext.fx.Manager.getFxQueue(me.dragCmp.el.id)[0];
            if (temp) {
                temp.on({
                    afteranimate: me.reorderer.afterBoxReflow,
                    scope: me
                });
            }
            // If not animated, clean up after the mouseup has happened so that we don't click the thing being dragged
            else {
                Ext.Function.defer(me.reorderer.afterBoxReflow, 1, me);
            }

            if (me.animate) {
                delete layout.animatePolicy;
            }
            me.reorderer.fireEvent('drop', me, me.container, me.dragCmp, me.startIndex, me.curIndex);
        }
    },

    /**
     * @private
     * Called after the boxes have been reflowed after the drop.
     * Re-enabled the dragged Component.
     */
    afterBoxReflow: function() {
        var me = this;
        me.dragCmp.el.setStyle('zIndex', '');
        me.dragCmp.disabled = false;
        me.dragCmp.resumeEvents();
    },

    /**
     * @private
     * Calculate drop index based upon the dragEl's position.
     */
    getNewIndex: function(pointerPos) {
        var me = this,
            dragEl = me.getDragEl(),
            dragBox = Ext.fly(dragEl).getBox(),
            targetEl,
            targetBox,
            targetMidpoint,
            i = 0,
            it = me.container.items.items,
            ln = it.length,
            lastPos = me.lastPos;

        me.lastPos = dragBox[me.startAttr];

        for (; i < ln; i++) {
            targetEl = it[i].getEl();

            // Only look for a drop point if this found item is an item according to our selector
            if (targetEl.is(me.reorderer.itemSelector)) {
                targetBox = targetEl.getBox();
                targetMidpoint = targetBox[me.startAttr] + (targetBox[me.dim] >> 1);
                if (i < me.curIndex) {
                    if ((dragBox[me.startAttr] < lastPos) && (dragBox[me.startAttr] < (targetMidpoint - 5))) {
                        return i;
                    }
                } else if (i > me.curIndex) {
                    if ((dragBox[me.startAttr] > lastPos) && (dragBox[me.endAttr] > (targetMidpoint + 5))) {
                        return i;
                    }
                }
            }
        }
    }
});

Ext.define('Scalr.ui.CustomButton', {
    alias: 'widget.custombutton',
    extend: 'Ext.Component',

    hidden: false,
    disabled: false,
    pressed: false,
    enableToggle: false,
    maskOnDisable: false,

    childEls: [ 'btnEl' ],

    overCls: 'x-btn-custom-over',
    pressedCls: 'x-btn-custom-pressed',
    disabledCls: 'x-btn-custom-disabled',
    tooltipType: 'qtip',

    initComponent: function() {
        var me = this;
        me.callParent(arguments);

        if (Ext.isString(me.toggleGroup)) {
            me.enableToggle = true;
        }

        me.renderData['disabled'] = me.disabled;
    },

    onRender: function () {
        var me = this;

        me.callParent(arguments);

        me.mon(me.el, {
            click: me.onClick,
            scope: me
        });

        if (me.pressed)
            me.addCls(me.pressedCls);

        Ext.ButtonToggleManager.register(me);

        if (me.tooltip) {
            me.setTooltip(me.tooltip, true);
        }

    },

    onDestroy: function() {
        var me = this;
        if (me.rendered) {
            Ext.ButtonToggleManager.unregister(me);
            me.clearTip();
        }
        me.callParent();
    },

    toggle: function(state, suppressEvent) {
        var me = this;
        state = state === undefined ? !me.pressed : !!state;
        if (state !== me.pressed) {
            if (me.rendered) {
                me[state ? 'addCls': 'removeCls'](me.pressedCls);
            }
            me.pressed = state;
            if (!suppressEvent) {
                me.fireEvent('toggle', me, state);
                Ext.callback(me.toggleHandler, me.scope || me, [me, state]);
            }
        }
        return me;
    },

    onClick: function(e) {
        var me = this;
        if (! me.disabled) {
            me.doToggle();
            me.fireHandler(e);
        }
    },

    fireHandler: function(e){
        var me = this,
        handler = me.handler;

        me.fireEvent('click', me, e);
        if (handler) {
            handler.call(me.scope || me, me, e);
        }
    },

    doToggle: function(){
        var me = this;
        if (me.enableToggle && (me.allowDepress !== false || !me.pressed)) {
            me.toggle();
        }
    },

    setTooltip: function(tooltip, initial) {
        var me = this;

        if (me.rendered) {
            if (!initial) {
                me.clearTip();
            }
            if (Ext.isObject(tooltip)) {
                Ext.tip.QuickTipManager.register(Ext.apply({
                    target: me.btnEl.id
                },
                tooltip));
                me.tooltip = tooltip;
            } else {
                me.btnEl.dom.setAttribute(me.getTipAttr(), tooltip);
            }
        } else {
            me.tooltip = tooltip;
        }
        return me;
    },

    getTipAttr: function(){
        return this.tooltipType == 'qtip' ? 'data-qtip' : 'title';
    },

    clearTip: function() {
        if (Ext.isObject(this.tooltip)) {
            Ext.tip.QuickTipManager.unregister(this.btnEl);
        }
    }
});

/*
 remove, move code to ui.js

Ext.define('Scalr.ui.PanelTool', {

    extend: 'Ext.panel.Tool',
    alias: 'widget.favoritetool',

     * Example:
     *
     favorite: {
        text: 'Farms',
        href: '#/farms/view'
     }

    favorite: {},

    initComponent: function () {
        this.type = 'favorite';
        this.favorite.hrefTarget = '_self';
        var favorites = Scalr.storage.get('system-favorites');

        Ext.each(favorites, function (item) {
            if (item.href == this.favorite['href']) {
                this.type = 'favorite-checked';
                return false;
            }
        }, this);

        this.callParent();
    },

    handler: function () {
        var favorites = Scalr.storage.get('system-favorites') || [], enabled = this.type == 'favorite-checked', href = this.favorite.href, menu = Scalr.application.getDockedComponent('top');

        if (enabled) {
            var index = menu.items.findIndex('href', this.favorite.href);
            menu.remove(menu.items.getAt(index));

            Ext.Array.each(favorites, function(item) {
                if (item.href == href) {
                    Ext.Array.remove(favorites, item);
                    return false;
                }
            });
            this.setType('favorite');
        } else {
            var index = menu.items.findIndex('xtype', 'tbfill'), fav = Scalr.utils.CloneObject(this.favorite);
            Ext.apply(fav, {
                hrefTarget: '_self',
                reorderable: true,
                cls: 'x-btn-favorite',
                overCls: 'btn-favorite-over',
                pressedCls: 'btn-favorite-pressed'
            });
            menu.insert(index, fav);
            favorites.push(this.favorite);
            this.setType('favorite-checked');
        }

        Scalr.storage.set('system-favorites', favorites);
    }
});*/

// 5.0
Ext.define('Scalr.ui.MenuItemTop', {
    extend: 'Ext.menu.Item',
    alias: 'widget.menuitemtop',
    cls: 'x-menu-item-addlink',
    addLinkHrefDisabled: false,

    onRender: function () {
        var me = this;
        me.callParent();

        if (me.addLinkHrefDisabled)
            return;

        me.itemEl.createChild({
            tag: 'a',
            cls: 'addlink',
            href: me.addLinkHref,
            html: 'ADD NEW'
        }, me.arrowEl);
    }
});

// DEPRECATED, remove with old farm builder
Ext.define('Scalr.ui.FormComboButton', {
    extend: 'Ext.container.Container',
    alias: 'widget.combobutton',

    cls: 'x-form-combobutton',
    handler: Ext.emptyFn,
    privateHandler: function (btn) {
        this.handler(btn.value, btn);
    },

    initComponent: function () {
        var me = this, groupName = this.getId() + '-button-group';

        for (var i = 0; i < me.items.length; i++) {
            Ext.apply(me.items[i], {
                enableToggle: true,
                toggleGroup: groupName,
                allowDepress: false,
                handler: me.privateHandler,
                scope: me
            });
        }

        me.callParent();
    },

    afterRender: function () {
        this.callParent(arguments);

        this.items.first().addCls('x-btn-default-small-combo-first');
        this.items.last().addCls('x-btn-default-small-combo-last');
    },

    getValue: function () {
        var b = Ext.ButtonToggleManager.getPressed(this.getId() + '-button-group');
        if (b)
            return b.value;
    }
});

Ext.define('Scalr.ui.AddFieldPlugin', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.addfield',
    pluginId: 'addfield',
    width: '100%',
    targetEl: 'tbody',
    cls: '',

    init: function (client) {
        var me = this;
        me.client = client;
        client.on('afterrender', function() {
            me.panelContainer = Ext.DomHelper.insertAfter(client.el.down(me.targetEl), {style: {height: '30px'}}, true);
            var addmask = Ext.DomHelper.append(me.panelContainer,
                '<div style="position: absolute; width: ' + me.width + '; height: 30px;'+(me.padding ? 'padding:' + me.padding + ';' : '')+'">' +
                    '<div class="scalr-ui-addfield ' + me.cls + '"></div>' +
                    '</div>'
                , true);
            addmask.down('div.scalr-ui-addfield').on('click', me.handler, client);
        }, client);
    },
    run: function() {
        this.handler.call(this.client);
    },
    isVisible: function() {
        return this.panelContainer && this.panelContainer.isVisible();
    },
    setWidth: function(width) {
        this.width = width;
        if (this.panelContainer)
            this.panelContainer.down('div').setWidth(width);
    },
    hide: function() {
        if (this.panelContainer) {
            this.panelContainer.remove();
            this.panelContainer = null;
        }
    }
});

Ext.define('Scalr.ui.LeftMenu', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.leftmenu',

    disabled: false,
    client:null,
    menu: null,
    menuVisible: false,

    currentMenuId: null,
    currentItemId: null,
    currentOptions: null,
    itemIdPrefix: 'leftmenu-',
    defaultMenuWidth: 110 + Ext.getScrollbarSize().width,

    itemIconClsPrefix: 'x-icon-leftmenu',

    getMenuConfig: function(menuId){
        var config = {
            items: [],
            cls: menuId
        };
        switch (menuId) {
            case 'account':
                config.items.push({
                    itemId:'teams',
                    href: '#/account/teams',
                    text: 'Teams'
                });

                if (Scalr.utils.canManageAcl()) {
                    config.items.push({
                        itemId:'users',
                        href: '#/account/users',
                        text: 'Users'
                    });

                    config.items.push({
                        itemId:'roles',
                        href: '#/account/acl',
                        text: 'ACL'
                    });
                }
                break;
            case 'webhooks':
                config.items.push({
                    itemId:'endpoints',
                    href: '#' + Scalr.utils.getUrlPrefix() + '/webhooks/endpoints',
                    text: 'Endpoints'
                });

                config.items.push({
                    itemCls: 'webhooks',
                    itemId:'configs',
                    href: '#' + Scalr.utils.getUrlPrefix() + '/webhooks/configs',
                    text: 'Webhooks'
                });

                config.items.push({
                    itemId:'history',
                    href: '#' + Scalr.utils.getUrlPrefix() + '/webhooks/history',
                    text: 'History'
                });
                break;

            case 'analytics':
                config.items.push({
                    itemId: 'dashboard',
                    cls: 'x-btn-tab-small-dark',
                    href: '#/admin/analytics/dashboard',
                    text: 'Dashboard'
                });
                config.items.push({
                    itemId: 'costcenters',
                    href: '#/admin/analytics/costcenters',
                    text: 'Cost centers'
                },{
                    itemId: 'projects',
                    href: '#/admin/analytics/projects',
                    text: 'Projects'
                },{
                    itemId: 'budgets',
                    href: '#/admin/analytics/budgets',
                    text: 'Budgets'
                },{
                    itemId: 'pricing',
                    href: '#/admin/analytics/pricing',
                    text: 'Pricing list'
                });
                config.items.push({
                    itemId: 'notifications',
                    href: '#/admin/analytics/notifications',
                    text: 'Notifications',
                });
                break;
            case 'envanalytics':
                config.cls = 'analytics';
                config.items.push({
                    cls: 'x-btn-tab-small-dark',
                    itemId: 'dashboard',
                    itemCls: 'environments',
                    href: '#/analytics/dashboard',
                    text: 'Environment'
                },{
                    itemId: 'farms',
                    itemCls: 'farms',
                    href: '#/analytics/farms',
                    text: 'Farms'
                });
                break;
            case 'accountanalytics':
                config.cls = 'analytics';
                config.items.push({
                    itemId: 'environments',
                    href: '#/account/analytics/environments',
                    text: 'Environments'
                });
                if (Scalr.isAllowed('ANALYTICS_PROJECTS_ACCOUNT')) {
                    config.items.push({
                        itemId: 'projects',
                        href: '#/account/analytics/projects',
                        text: 'Projects'
                    });
                }
                if (Scalr.isAllowed('ANALYTICS_PROJECTS_ACCOUNT', 'allocate-budget')) {
                    config.items.push({
                        itemId: 'budgets',
                        href: '#/account/analytics/budgets',
                        text: 'Budgets'
                    });
                }
                if (Scalr.isAllowed('ANALYTICS_PROJECTS_ACCOUNT')) {
                    config.items.push({
                        itemId: 'notifications',
                        href: '#/account/analytics/notifications',
                        text: 'Notifications'
                    });
                }
                break;

        }
        return config;
    },


    init: function(client) {
        var me = this;
        me.client = client;
    },

    create: function() {
        var me = this;
        me.menu = Ext.create('Ext.container.Container', {
            hidden: true,
            dock: 'left',
            cls: 'x-docked-tabs',
            width: me.defaultMenuWidth,
            scrollable: 'y',
            defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'top',
                disableMouseDownPressed: true,
                enebleToggle: true,
                hrefTarget: null,
                listeners: {
                    click: Ext.bind(me.onButtonClick, me)
                }
            }
        });
        me.client.addDocked(this.menu);
    },

    set: function(options) {
        var me = this, prevBtn, newBtn, menuConfig;
        me.currentOptions = options;
        if (options.menuId !== this.currentMenuId || this.currentScope != Scalr.scope) {
            this.currentScope = Scalr.scope;
            menuConfig = this.getMenuConfig(options.menuId);
            this.menu.removeAll();
            var iconClsPrefix = me.itemIconClsPrefix;
            this.menu.setWidth(options.width || me.defaultMenuWidth);
            this.menu.add(Ext.Array.map(menuConfig.items, function(item){
                var iconClsPrefixLocal = item.menuCls ? me.itemIconClsPrefix + item.menuCls : iconClsPrefix;
                if (options.icons === false) {
                    item.textAlign = 'left';
                } else {
                    item.iconCls = iconClsPrefixLocal + ' ' + iconClsPrefixLocal + '-' + (item.itemCls || item.itemId);
                }
                item.itemId = me.itemIdPrefix + item.itemId;
                return item;
            }));
            this.currentMenuId = options.menuId;
            this.currentItemId = null;
        }
        newBtn = this.menu.getComponent(me.itemIdPrefix + options.itemId);
        if (options.itemId !== this.currentItemId) {
            if (this.currentItemId) {
                prevBtn = this.menu.getComponent(me.itemIdPrefix + this.currentItemId);
                if (prevBtn) prevBtn.toggle(false, true);
            }
            if (newBtn) newBtn.toggle(true);
            this.currentItemId = options.itemId;
        }
        if (newBtn && options.subpage) {
            var newHref = newBtn.href.replace(/[^/]+\/{0,1}$/, options.subpage);
            if (newBtn.rendered) {
                newBtn.setHref(newHref);
            } else {
                newBtn.href = newHref;
            }
        }
    },

    show: function(options) {
        if (this.menu === null) {
            this.create();
        }
        this.set(options);
        this.menuVisible = true;
        this.menu.show();
    },

    hide: function() {
        this.menuVisible = false;
        if (this.menu) {
            this.menu.hide();
        }
    },

    onButtonClick: function(btn, e) {
        continueCallback = function() {
            Scalr.event.fireEvent('redirect', btn.href);
        };
        if (Ext.isFunction(this.currentOptions.beforeClose) && this.currentOptions.beforeClose(continueCallback) === false) {
            e.stopEvent();
            return false;
        }
    }

});

Ext.define('Scalr.ui.GridField', {
    extend: 'Ext.grid.Panel',
    mixins: {
        field: 'Ext.form.field.Field'
    },
    alias: 'widget.gridfield',

    selModel: {
        selType: 'selectedmodel',
        injectCheckbox: 'first'
    },
    fieldReady: false,

    allowBlank: true,

    initComponent : function() {
        var me = this;
        me.callParent();
        this.initField();
        if (!me.name) {
            me.name = me.getInputId();
        }

        this.on('viewready', function(){
            this.fieldReady = true;
            this.setRawValue(this.value);
        });
        this.on('selectionchange', function(selModel, selected){
            this.checkChange();
        });
        this.getStore().on('refresh', me.onStoreRefresh, me);
    },

    setValue: function(value) {
        this.setRawValue(value);
        return this.mixins.field.setValue.call(this, value);
    },

    setRawValue: function(value) {
        if (this.fieldReady) {
            var store = this.getStore(),
                records = [];

            value = value || [];
            for (var i=0, len=value.length; i<len; i++) {
                var record = store.getById(value[i]);
                if (record) {
                    records.push(record);
                }
            }
            if (records.length) {
                this.getSelectionModel().select(records, false, true);
            } else {
                this.getSelectionModel().deselectAll();
            }
        }
    },

    getInputId: function() {
        return this.inputId || (this.inputId = this.id + '-inputEl');
    },

    getRawValue: function() {
        var ids = [];
        this.getSelectionModel().selected.each(function(record){
            ids.push(record.get('id'));
        });
        return ids;
    },

    getValue: function() {
        return this.getRawValue();
    },

    getActiveError : function() {
        return this.activeError || '';
    },

    getSubmitData: function() {
        var me = this,
            data = null;
        if (!me.disabled && me.submitValue) {
            data = {};
            data[me.getName()] = Ext.encode(me.getValue());
        }
        return data;
    },

    onStoreRefresh: function(){
        this.setRawValue(this.value);
    },

    beforeDestroy: function() {
        this.getStore().un('refresh', this.onStoreRefresh, this);
        this.callParent(arguments);
    }


});

Ext.define('Scalr.ui.FormPicker', {
    extend:'Ext.form.field.Picker',
    alias: 'widget.formpicker',

    onBoxReady: function() {
        this.callParent(arguments);
        this.on({
            expand: function() {
                //this.parseSearchString();
            }
        });
    },

    createPicker: function() {
        var me = this,
            formDefaults = {
                style: 'background:#F0F1F4;border-radius:4px;box-shadow: 0 1px 3px #7B8BA1;margin-top:1px',
                fieldDefaults: {
                    anchor: '100%'
                },
                focusOnToFront: false,
                padding: 12,
                pickerField: me,
                floating: true,
                hidden: true,
                ownerCt: this.ownerCt
            };

        if (!this.form.dockedItems) {
            this.form.dockedItems = {
                xtype: 'container',
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                dock: 'bottom',
                items: [{
                    xtype: 'button',
                    text: 'Search',
                    handler: function() {me.focus();
                        me.collapse();
                        //me.fireEvent('search');
                    }
                }]
            }
        }
        if (this.form.items) {
            this.form.items.unshift({
                xtype: 'textfield',
                name: 'keywords',
                fieldLabel: 'Has words',
                labelAlign: 'top'
            });
        }
        var form = Ext.create('Ext.form.Panel', Ext.apply(formDefaults, this.form));
        form.getForm().getFields().each(function(){
            if (this.xtype == 'combo') {
                this.on('expand', function(){
                    this.picker.el.on('mousedown', function(e){
                        me.keepVisible = true;
                    });
                }, this, {single: true})
            }
        })
        return form;
    },

    collapseIf: function(e) {
        var me = this;
        if (!me.keepVisible && !me.isDestroyed && !e.within(me.bodyEl, false, true) && !e.within(me.picker.el, false, true) && !me.isEventWithinPickerLoadMask(e)) {
            me.collapse();
        }
        me.keepVisible = false;
    }

});

Ext.define('Scalr.ui.data.View.DynEmptyText', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.dynemptytext',

    disabled: false,
    client: null,

    onAddItemClick: Ext.emptyFn,
    itemsTotal: null,
    emptyText: 'Nothing were found to match your search.<br/>Try modifying your search criteria.',
    emptyTextNoItems: null,
    forceRefresh: false,

    init: function(client) {
        var me = this;
        me.client = client;
        client.emptyText = '<div class="' + Ext.baseCSSPrefix + 'grid-empty">' + me.emptyText + '</div>';
        client.deferEmptyText = false;
        client.on({
            containerclick: {
                fn: me.onContainerClick,
                scope: me
            },
            afterrender: function() {
                client.store.on({
                    refresh: {
                        fn: me.updateEmptyText,
                        scope: me
                    },
                    add: {
                        fn: me.updateEmptyText,
                        scope: me
                    },
                    remove: {
                        fn: me.updateEmptyText,
                        scope: me
                    }
                });
                me.updateEmptyText();
            },
            beforedestroy: function() {
                this.un('containerclick', me.onContainerClick, me);
                this.store.un('refresh', me.updateEmptyText, me);
                this.store.un('add', me.updateEmptyText, me);
                this.store.un('remove', me.updateEmptyText, me);
            }
        });
    },

    onContainerClick: function(comp, e){
        var el = comp.el.query('a.add-link');
        if (el.length) {
            for (var i=0, len=el.length; i<len; i++) {
                if (e.within(el[i])) {
                    this.onAddItemClick(el[i]);
                    break;
                }
            }
            e.preventDefault();
        }
    },

    updateEmptyText: function() {
        var client = this.client,
            store = client.store,
            visibleCount = store.getCount(),
            itemsTotal = 0;
        if (store.getUnfiltered) {//store
            itemsTotal = store.getUnfiltered().length;
        } else {
            store = store.getSource();
            if (store.getUnfiltered) {//chained store
                itemsTotal = store.getUnfiltered().length;
            } else if (store.length) {//collection
                itemsTotal = store.length;
            }
        }

        if (!visibleCount && (this.forceRefresh || itemsTotal !== this.itemsTotal)) {
            var text = itemsTotal < 1 ? this.emptyTextNoItems || this.emptyText : this.emptyText;
            client.emptyText = '<div class="' + Ext.baseCSSPrefix + 'grid-empty">' + text + '</div>';
            var emptyDiv = client.el.query('.' + Ext.baseCSSPrefix + 'grid-empty');
            if (emptyDiv.length) {
                Ext.fly(emptyDiv[0]).setHtml(text);
            }
            client.refreshSize();
            this.itemsTotal = itemsTotal;
            this.forceRefresh = false;
        } else if (visibleCount) {
            //extjs table view bug? empty text is visible after adding new record
            client.clearEmptyEl();
        }
    },

    setEmptyText: function(text, alt) {
        this['emptyText' + (alt ? 'NoItems' : '')] = text;
        this.forceRefresh = true;
    }
});

Ext.define('Ext.ux.form.ToolFieldSet', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.toolfieldset',
    tools: [],

    createLegendCt: function () {
        var me = this,
            legend = me.callParent(arguments);

        if (Ext.isArray(me.tools)) {
            for(var i = 0; i < me.tools.length; i++) {
                legend.items.push(me.createToolCmp(me.tools[i]));
            }
        }

        return legend;
    },

    createToolCmp: function(toolCfg) {
        var me = this;
        Ext.apply(toolCfg, {
            xtype:  'tool',
            cls: 'x-tool-extra',
            width:  15,
            height: 15,
            id:     me.id + '-tool-' + toolCfg.type,
            scope:  me
        });
        return Ext.widget(toolCfg);
    }

});

Ext.define('Scalr.CachedRequest', {

    defaultTtl: 3600,//seconds

    defaultProcessBox: {
        type: 'action',
        msg: 'Loading ...'
    },

    constructor: function() {
        this.cache = {};
        this.queue = {};
    },

    getCacheId: function(config) {
        var cacheId = [],
            paramNames;
        if (config) {
            cacheId.push(config.url);
            if (Ext.isObject(config.params)) {
                paramNames = Ext.Array.sort(Ext.Object.getKeys(config.params));
                Ext.Array.each(paramNames, function(value){
                    cacheId.push(config.params[value]);
                });
            }
        }
        return cacheId.join('.');
    },

    clearCache: function() {
        delete this.queue;
        delete this.cache;
        this.cache = {};
        //todo abort requests before unset
        this.queue = {};
    },

    removeCache: function(params) {
        var cacheId = this.getCacheId(params);
        delete this.cache[cacheId];
    },

    load: function(params, cb, scope, ttl, processBox) {
        var me = this,
            cacheId = me.getCacheId(params);
        ttl = ttl === undefined ? me.defaultTtl : ttl;

        if (me.queue[cacheId] !== undefined) {
            me.queue[cacheId].callbacks.push({fn: cb, scope: scope || me});
        } else if (me.cache[cacheId] !== undefined && !me.isExpired(me.cache[cacheId], ttl)) {
            if (cb !== undefined) cb.call(scope || me, me.cache[cacheId].data, 'exists', cacheId);
        } else {
            delete me.cache[cacheId];
            me.queue[cacheId] = {
                callbacks: [{fn: cb, scope: scope || me}]
            };
            me.queue[cacheId].request = Scalr.Request({
                processBox: processBox!== false ? processBox || me.defaultProcessBox : undefined,
                url:  params.url,
                params: params.params || {},
                scope: me,
                success: function (result, response) {
                    if (me.queue[cacheId] !== undefined) {
                        var callbacks = me.queue[cacheId].callbacks;
                        me.cache[cacheId] = {
                            data: result.data !== undefined ? result.data : result,
                            time: me.getTime()
                        };
                        delete me.queue[cacheId];
                        Ext.Array.each(callbacks, function(callback){
                            if (callback.fn !== undefined && !callback.scope.isDestroyed) callback.fn.call(callback.scope, me.cache[cacheId].data, 'success', cacheId, response);
                        });
                    }
                },
                failure: function (result, response) {
                    if (me.queue[cacheId] !== undefined) {
                        var callbacks = me.queue[cacheId].callbacks;
                        delete me.queue[cacheId];
                        Ext.Array.each(callbacks, function(callback){
                            if (callback.fn !== undefined && !callback.scope.isDestroyed) callback.fn.call(callback.scope, null, false, cacheId, response);
                        });
                    }
                }
            });
        }
        return cacheId;
    },

    abort: function(cacheId) {
        var me = this;
        if (me.queue[cacheId] && me.queue[cacheId].request) {
            Ext.Ajax.abort(me.queue[cacheId].request);
            delete me.queue[cacheId].request;
        }
    },

    get: function(params) {
        var cacheId = Ext.isString(params) ? params : this.getCacheId(params);
        return this.cache[cacheId] || undefined;
    },

    getTime: function() {
        return Math.floor(new Date().getTime() / 1000);
    },

    isExpired: function(data, ttl) {
        return data.expired ? true : (ttl !== 0 ? this.getTime() - data.time > ttl : false);
    },

    setExpired: function(params) {
        var cacheId = Ext.isString(params) ? params : this.getCacheId(params);
        if (this.cache[cacheId] !== undefined) {
            this.cache[cacheId].expired = true;
        }
    },

    setExpiredByMask: function (mask) {
        var me = this;

        Ext.Object.each(me.cache, function (id, cache) {
            if (Ext.String.startsWith(id, mask)) {
                cache.expired = true;
            }
        });
    },

    isLoaded: function(params) {
        var cacheId = Ext.isString(params) ? params : this.getCacheId(params);
        return this.cache[cacheId] !== undefined;
    },

    onDestroy: function(){
        this.clearCache();
    }

});

//4.2 plugins
Ext.define('Scalr.CachedRequestManager', {
    singleton: true,
    list: {},

    create: function(id, doNotSubscribe) {
        var me = this;
        id = id || 'global';
        if (me.list[id] === undefined) {
            me.list[id] = {
                instance: Ext.create('Scalr.CachedRequest'),
                subscribers: doNotSubscribe ? 0 : 1
            };
        } else if (!doNotSubscribe) {
            me.list[id].subscribers++;
        }
        return me.list[id].instance;
    },

    remove: function(id) {
        var me = this,
            item = me.list[id];
        if (item !== undefined) {
            item.subscribers--;
            if (item.subscribers <= 0) {
                item.instance.destroy();
                delete me.list[id];
            }
        }
    },

    clear: function(id) {
        var me = this,
            item = me.list[id];
        if (item !== undefined) {
            item.instance.clearCache();
        }
    },

    get: function(id) {
        return this.create(id, true);
    }
});

Ext.define('Scalr.ui.LocalCachedRequest', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.localcachedrequest',

    crscope: null,

    init: function(client) {
        Scalr.CachedRequestManager.create(this.crscope);
    },

    destroy: function(){
        Scalr.CachedRequestManager.remove(this.crscope);
    }
});

Ext.define('Scalr.ui.StoreProxyCachedRequest', {
    extend: 'Ext.data.proxy.Proxy',
    alias: 'proxy.cachedrequest',

    config: {
        crscope: null,
        url: null,
        data: null,
        filterFields: null,
        root: null,
        filterFn: null,
        filterFnScope: null,
        prependData: null,
        ttl: null,
        processBox: null
    },

    constructor: function(config) {
        this.params = {};
        this.extraParams = {};
        this.callParent(arguments);
    },


    finishOperation: function(operation) {
        var i = 0,
            recs = operation.getRecords(),
            len = recs.length;

        for (i; i < len; i++) {
            recs[i].commit();
        }
        operation.setSuccessful(true);
    },

    create: function(operation) {
        this.finishOperation(operation);
    },

    update: function(operation) {
        this.finishOperation(operation);
    },

    erase: function(operation) {
        this.finishOperation(operation);
    },

    clear: Ext.emptyFn,

    read: function(operation) {
        var me = this,
            config = me.config,
            resultSet,
            records,
            root = me.getRoot(),
            data = me.getData(),
            requestParams;
        if (data) {
            resultSet = me.getReader().read(me.getData());
            if (operation.process(resultSet, null, null, false) !== false) {
                if (me.getFilterFn()) {
                    resultSet.setRecords(records = Ext.Array.filter(resultSet.getRecords(), me.getFilterFn(), me.getFilterFnScope() || me));
                    resultSet.setTotal(records.length);
                }
                operation.setCompleted();
            }
        } else {
            requestParams = {
                url: me.getUrl(),
                params: Ext.Object.getSize(me.params) ? me.params : me.extraParams
            };
            if (operation.config.clearCache) {
                Scalr.CachedRequestManager.get(me.getCrscope()).setExpired(requestParams);
            }
            Scalr.CachedRequestManager.get(me.getCrscope()).load(
                requestParams,
                function(data, status, cacheId, response) {
                    var filterFn,
                        testRe,
                        queryString = operation.config.params ? operation.config.params.query : null;

                    if (status) {
                        resultSet = me.getReader().read(root ? data[root] : data);
                        if (operation.process(resultSet, null, null, false) !== false) {
                            if (me.getFilterFn()) {
                                resultSet.setRecords(records = Ext.Array.filter(resultSet.getRecords(), me.getFilterFn(), me.getFilterFnScope() || me));
                                resultSet.setTotal(records.length)
                            }
                            if (queryString) {
                                if (me.getFilterFields()) {
                                    testRe = new RegExp(Ext.String.escapeRegex(queryString), 'i');
                                    filterFn = function(record){
                                        var res = false;
                                        Ext.Array.each(me.getFilterFields(), function(field){
                                            return !(res = testRe.test(record.get(field)));
                                        });
                                        return res;
                                    };
                                }

                                if (filterFn !== undefined) {
                                    resultSet.setRecords(records = Ext.Array.filter(resultSet.getRecords(), filterFn));
                                    resultSet.setTotal(records.length);
                                }
                            }
                            if (me.getPrependData()) {
                                Ext.Array.insert(operation.getRecords(), 0, me.getReader().read(me.getPrependData()).getRecords());
                            }

                            operation.setCompleted();
                        }
                    }
                },
                me,
                me.getTtl() || 0,
                me.getProcessBox()
            );
        }
    }

});

Ext.define('Scalr.ui.PanelScrollFixPlugin', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.panelscrollfix',

    disabled: false,
    client:null,

    scrollTop: 0,

    init: function(client) {
        var me = this;
        me.client = client;
        client.on({
            afterrender: function() {
               this.body.el.on('scroll', me.saveScrollPosition, me);
            },
            beforedestroy: function() {
                this.body.el.un('scroll', me.saveScrollPosition, me);
                this.un('afterlayout', me.restoreScrollPosition);
            },
            afterlayout: {
                fn: me.restoreScrollPosition,
                scope: me
            }
        });
    },

    saveScrollPosition: function() {
        if (!this.suspendEvents) {
            this.scrollTop = this.client.body.el.getScroll().top;
        }
    },

    restoreScrollPosition: function() {
        this.suspendEvents = true;
        this.client.body.scrollTo('top', this.scrollTop || 0);
        this.suspendEvents = false;
    },

    resetScrollPosition: function() {
        this.scrollTop = 0;
    }
});

Ext.define('Scalr.ui.ColoredStatus', {
    singleton: true,
    config: {
        server: {
            'Pending launch': {
                cls: 'yellow',
                text:  'Scalr is preparing to launch this server. No API call to your Cloud Platform has been made yet.'
            },
            'Pending': {
                cls: 'yellow',
                iconCls: 'running',
                text: 'Scalr has made an API call to your Cloud Platform to request this server. Scalr is waiting for the server to finish booting.'
            },
            'Initializing': {
                cls: 'yellow',
                iconCls: 'running',
                text:
                    function(data){
                        return 'The server has finished booting.' + (data.isScalarized == 1 ? ' Scalarizr has fired the HostInit event and is now configuring the server.' : '');
                    }
            },
            'Running': {
                cls: 'green',
                text:
                    function(data){
                        return 'The server has finished initializing.' + (data.isScalarized == 1 ? ' Scalarizr has fired the HostUp event, and will continue to process further orchestration events.' : '');
                    }
            },
            'Pending terminate': {
                text: 'Scalr has scheduled this server for termination. No API call to your Cloud Platform has been made yet.'
            },
            'Terminated': {
                text: 'Scalr has terminated this server. It cannot be resumed, but may be replaced. You are no longer paying instance hours for it.'
            },
            'Pending suspend': {
                cls: 'blue',
                text: 'Scalr has scheduled this server for suspension. No API call to your Cloud Platform has been made yet.'
            },
            'Suspended': {
                cls: 'blue',
                text: 'Scalr has suspended this server to disk. It may be resumed. You are no longer accumulating instance hours for this server.'
            },
            'Resuming': {
                iconCls: 'running'
            },
            'Importing': {
                cls: 'green',
                text: 'Scalr is importing this server to create a new Role. Scalr <b>will not</b> automatically terminate this server once it is done.'
            },
            'Temporary': {
                cls: 'green',
                text: 'Scalr has launched this temporary server. Scalr <b>will</b> automatically terminate this server once task is done.'
            },
            'Failed': {
                cls: 'red',
                iconCls: 'failed',
                text: 'An error occurred as this <b>server</b> was launching.'
            },
            'Rebooting': {
                text: 'A reboot operation was requested on this server. It is now rebooting and reinitializing.'
            }
        },
        role: {
            'In use': {
                cls: 'green',
                text: 'This <b>role</b> is currently used by one or more <b>farms</b> to launch instances. '
            },
            'Not used': {
                text: 'This <b>role</b> is currently not used by any <b>farm</b>.'
            }
        },
        chefserver: {
            'In use': {
                cls: 'green'
            },
            'Not used': {}
        },
        farm: {
            'Running': {
                cls: 'green',
                text: 'Scalr is actively managing and monitoring this farm. Resources are being provisioned from your Cloud Platform to operate this Farm.'
            },
            'Terminated': {
                text: 'Scalr has terminated all the resources for this Farm.'
            }
        },
        script: {
            'Non-blocking': {
                cls: 'green',
                text: 'Scalarizr <b>will not wait</b> for your script to finish executing before firing and processing further events.'
            },
            'Blocking': {
                text: 'Scalarizr <b>will wait</b> for your script to finish executing before firing and processing further events. Useful to avoid race conditions in time-sensitive workflows.'
            }
        },
        schedulertaskscript: {
            'Non-blocking': {
                cls: 'green',
                text: 'Scalarizr <b>will not wait</b> for your script to finish executing before firing and processing further events.'
            },
            'Blocking': {
                text: 'Scalarizr <b>will wait</b> for your script to finish executing before firing and processing further events. Useful to avoid race conditions in time-sensitive workflows.'
            }
        },
        bundletask: {
            'starting-server': {
                cls: 'yellow',
                title: 'Starting temporary server',
                text: 'Scalr is launching a temporary server for this bundle task, and is currently waiting for the server to finish booting.'
            },
            'preparing-environment': {
                cls: 'yellow',
                title: 'Uploading import tools',
                text: 'The temporary server for this bundle task has finished booting, and Scalr is now uploading import tools to the server.'
            },
            'installing-software': {
                cls: 'yellow',
                title: 'Installing import tools',
                text: 'Scalr has finished uploading import tools to the temporary server for this bundle task, and is now installing them.'
            },
            'awaiting-user-action': {
                cls: 'yellow',
                title: 'Pending user action',
                text: 'Scalr is ready to start the import process, and is now waiting for user input.'
            },
            'establishing-communication': {
                cls: 'yellow',
                title: 'Establishing communication',
                text: 'Scalr is starting the import process, and is currently establishing two-way communication with the Scalarizr agent installed on the server.'
            },
            'pending': {
                cls: 'yellow',
                title: 'Pending',
                text: 'Scalr has established two-way communication with the Scalarizr agent installed on the server, and is now initiating the role creation process.'
            },
            'preparing': {
                cls: 'yellow',
                title: 'Preparing System',
                text: 'Scalr is making modifications to system configuration files to make the server suitable for image creation.'
            },
            'in-progress': {
                cls: 'yellow',
                title: '(Image creation) In progress',
                text: 'Scalr has made an API call to your cloud to create an image from the server, and is now waiting for the process to complete.'
            },
            'creating-role': {
                cls: 'yellow',
                title: 'Creating role',
                text: 'Scalr has completed the image creation process, and is now creating a new Scalr role and associating the image with it. '
            },
            'replacing-servers': {
                cls: 'yellow',
                title: 'Replacing servers',
                text: 'Scalr has finished creating this Scalr role, and is now replacing existing servers with the updated role.'
            },
            'success': {
                cls: 'green',
                title: 'Completed',
                text: 'Scalr has successfully completed this bundle task.'
            },
            'failed': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Failed',
                text: 'Scalr failed to complete this bundle task.'
            },
            'cancelled': {
                title: 'Cancelled',
                text: 'A Scalr user aborted this bundle task.'
            }
        },
        dnszone: {
            'Active': {
                cls: 'green',
                text: 'Records for this DNS zone are automatically created and managed by Scalr, and are available on Scalr\'s nameservers.'
            },
            'Inactive': {
                text: 'This DNS zone is not managed by Scalr, and is not available on Scalr\'s nameservers.'
            },
            'Pending create': {
                cls: 'yellow',
                text: 'This DNS zone was recently created in Scalr, but the changes haven\'t been propagated to Scalr\'s nameservers yet.'
            },
            'Pending update': {
                cls: 'yellow',
                text: 'This DNS zone was recently changed in Scalr, but the changes haven\'t been propagated to Scalr\'s nameservers yet.'
            },
            'Pending delete': {
                cls: 'yellow',
                text: 'This DNS zone was recently deleted in Scalr, but the deletion hasn\'t been propagated to Scalr\'s nameservers yet.'
            }
        },
        schedulertask: {
            'Active': {
                cls: 'green',
                text: 'The Task Scheduler is currently automating this task, it will be executed according to the schedule you specified.'
            },
            'Suspended': {
                text: 'The Task Scheduler is not automating this task, it will not be executed.'
            },
            'Finished': {
                text: 'The Task Scheduler has finished this task.'
            }
        },
        user: {
            'Active': {
                cls: 'green',
                text: 'This user can access Scalr.'
            },
            'Inactive': {
                title: 'Suspended',
                text: 'This user account has been suspended, and this user can no longer access Scalr.'
            }
        },
        servermessage: {
            'Delivered': {
                cls: 'green',
                text: 'Scalr successfully delivered this message to the server.'
            },
            'Processed': {
                cls: 'green',
                text: 'Scalr successfully processed this message from the server.'
            },
            'Pending delivery': {
                cls: 'yellow',
                text: 'Scalr is preparing to deliver this message to the server. The message hasn\'t been delivered yet.'
            },
            'Pending processing': {
                cls: 'yellow',
                text: 'Scalr has received this message from the server. The message hasn\'t been processed yet.'
            },
            'Delivery failed': {
                cls: 'red',
                text: 'Scalr failed to deliver this message to the server. The error may be due to a firewall or misconfiguration issue.'
            },
            'Processing failed': {
                cls: 'red',
                text: 'Scalr failed to process this message from the server due to an internal error. Check your logs for more details, or contact support.'
            }
        },
        policy: {
            'Enforced': {
                cls: 'green',
                text: 'This policy is currently active, and controls Scalr usage in this environment.'
            },
            'Disabled': {
                cls: 'grey',
                text: 'This policy is currently inactive, no restriction on Scalr usage is imposed in this environment.'
            }
        },
        webhookendpoint: {
            'Validated': {
                cls: 'green',
                text: 'This endpoint has yet to be validated. Until it is validated, it can\'t be added to a Webhook.'
            },
            'Inactive': {
                title: 'Inactive',
                text: 'This endpoint has been validated. If this endpoint is added to a webhook, Scalr will deliver webhook messages to it.'
            }
        },
        webhookhistory: {
            'Complete': {
                cls: 'green',
                text: 'Scalr successfully delivered this Webhook Notification to your Webhook Endpoint, and your Webhook Endpoint responded with a HTTP status code indicating success.'
            },
            'Pending': {
                text: 'This Webhook Notification has been scheduled. Either Scalr hasn\'t attempted delivering this Webhook Notification yet, or the last delivery attempt has failed, and Scalr will retry delivery at a later time.'
            },
            'Failed': {
                cls: 'red',
                text: 'Scalr attempted delivering this Webhook Notification, but failed permanently. Either your Webhook Endpoint responded with a HTTP status code indicating a bad request (4XX) and caused Scalr to abort, or the maximum number of attempts was exceeded for this Webhook Notification.'
            }
        },
        costanalyticsnotification: {
            'Enabled': {
                cls: 'green',
                text: 'Notification will be sent to recipients'
            },
            'Disabled': {
                text: 'Notification will not be sent to recipients'
            }
        },
        sshkey: {
            'In use': {
                cls: 'green'
            },
            'Not used': {}
        },
        customevent: {
            'In use': {
                cls: 'green',
                text: 'This <b>Custom Event</b> is currently used.'
            },
            'Not used': {
                text: 'This <b>Custom Event</b> is currently not used.'
            }
        },
        rdsdbinstance: {
            'creating': {
                cls: 'yellow',
                iconCls: 'running',
                title: 'Creating',
                text: 'The instance is being created. The instance is inaccessible while it is being created.'
            },
            'backing-up': {
                cls: 'yellow',
                iconCls: 'running',
                title: 'Backing up',
                text: 'The instance is currently being backed up.'
            },
            'available': {
                cls: 'green',
                title: 'Available',
                text: 'The instance is healthy and available.'
            },
            'pending': {
                cls: 'yellow',
                iconCls: 'running',
                title: 'Pending'
            },
            'rebooting': {
                cls: 'yellow',
                iconCls: 'running',
                title: 'Rebooting',
                text: 'The instance is being rebooted because of a customer request or an Amazon RDS ' +
                    'process that requires the rebooting of the instance.'
            },
            'maintenance': {
                cls: 'yellow',
                iconCls: 'running',
                title: 'Maintenance',
                text: 'Amazon RDS is applying a maintenance update to the DB instance.'
            },
            'modifying': {
                cls: 'yellow',
                iconCls: 'running',
                title: 'Modifying',
                text: 'The instance is being modified because of a customer request to modify the instance.'
            },
            'renaming': {
                cls: 'yellow',
                iconCls: 'running',
                title: 'Renaming',
                text: 'The instance is being renamed because of a customer request to rename it.'
            },
            'resetting-master-credentials': {
                cls: 'yellow',
                iconCls: 'running',
                title: 'Resetting master credentials',
                text: 'The master credentials for the instance are being reset ' +
                    'because of a customer request to reset them.'
            },
            'upgrading': {
                cls: 'yellow',
                iconCls: 'running',
                title: 'Upgrading',
                text: 'The database engine version is being upgraded.'
            },
            'deleting': {
                iconCls: 'running',
                title: 'Deleting',
                text: 'The instance is being deleted.'
            },
            'deleted': {
                title: 'Deleted',
                text: 'The instance is completely deleted.'
            },
            'failed': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Failed',
                text: 'The instance has failed and Amazon RDS was unable to recover it. ' +
                    'Perform a point-in-time restore to the latest restorable time of the instance to recover the data.'
            },
            'inaccessible-encryption-credentials': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Inaccessible encryption credentials',
                text: 'The KMS key used to encrypt or decrypt the DB instance could not be accessed.'
            },
            'incompatible-credentials': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible credentials',
                text: 'The supplied CloudHSM username or password is incorrect. Please update the CloudHSM credentials for the DB instance.'
            },
            'incompatible-network': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible network',
                text: 'Amazon RDS is attempting to perform a recovery action on an instance but is unable to do ' +
                    'so because the VPC is in a state that is preventing the action from being completed. ' +
                    'This status can occur if, for example, all available IP addresses in a subnet were in use and ' +
                    'Amazon RDS was unable to get an IP address for the DB instance.'
            },
            'incompatible-option-group': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible option group',
                text: 'Amazon RDS attempted to apply an option group change but was unable to do so, ' +
                    'and Amazon RDS was unable to roll back to the previous option group state. ' +
                    'Consult the Recent Events list for the DB instance for more information. ' +
                    'This status can occur if, for example, the option group contains an option such as TDE ' +
                    'and the DB instance does not contain encrypted information.'
            },
            'incompatible-parameters': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible parameters',
                text: 'Amazon RDS was unable to start up the DB instance because the parameters specified ' +
                    'in the instance\'s DB parameter group were not compatible. ' +
                    'Revert the parameter changes or make them compatible with the instance to regain access ' +
                    'to your instance. Consult the Recent Events list for the DB instance for more information ' +
                    'about the incompatible parameters.'
            },
            'incompatible-restore': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible restore',
                text: 'Amazon RDS is unable to do a point-in-time restore. ' +
                    'Common causes for this status include using temp tables or using MyISAM tables.'
            },
            'restore-error': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Restore error',
                text: 'The DB instance encountered an error attempting to restore to a point-in-time or from a snapshot.'
            },
            'storage-full': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Storage full',
                text: 'The instance has reached its storage capacity allocation. ' +
                    'This is a critical status and should be remedied immediately; ' +
                    'you should scale up your storage by modifying the DB instance. Set CloudWatch alarms to ' +
                    'warn you when storage space is getting low so you don\'t run into this situation.'
            }
        },
        rdsdbcluster: {
            'creating': {
                cls: 'yellow',
                title: 'Creating',
                text: 'The cluster is being created. The cluster is inaccessible while it is being created.'
            },
            'backing-up': {
                cls: 'yellow',
                title: 'Backing up',
                text: 'The cluster is currently being backed up.'
            },
            'available': {
                cls: 'green',
                title: 'Available',
                text: 'The cluster is healthy and available.'
            },
            'pending': {
                cls: 'yellow',
                title: 'Pending'
            },
            'rebooting': {
                cls: 'yellow',
                title: 'Rebooting',
                text: 'The cluster is being rebooted because of a customer request or an Amazon RDS ' +
                    'process that requires the rebooting of the cluster.'
            },
            'maintenance': {
                cls: 'yellow',
                title: 'Maintenance',
                text: 'Amazon RDS is applying a maintenance update to the DB cluster.'
            },
            'modifying': {
                cls: 'yellow',
                title: 'Modifying',
                text: 'The cluster is being modified because of a customer request to modify the cluster.'
            },
            'renaming': {
                cls: 'yellow',
                title: 'Renaming',
                text: 'The cluster is being renamed because of a customer request to rename it.'
            },
            'resetting-master-credentials': {
                cls: 'yellow',
                title: 'Resetting master credentials',
                text: 'The master credentials for the cluster are being reset ' +
                    'because of a customer request to reset them.'
            },
            'upgrading': {
                cls: 'yellow',
                title: 'Upgrading',
                text: 'The database engine version is being upgraded.'
            },
            'deleting': {
                title: 'Deleting',
                text: 'The cluster is being deleted.'
            },
            'deleted': {
                title: 'Deleted',
                text: 'The cluster is completely deleted.'
            },
            'failed': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Failed',
                text: 'The cluster has failed and Amazon RDS was unable to recover it. ' +
                    'Perform a point-in-time restore to the latest restorable time of the cluster to recover the data.'
            },
            'inaccessible-encryption-credentials': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Inaccessible encryption credentials',
                text: 'The KMS key used to encrypt or decrypt the DB cluster could not be accessed.'
            },
            'incompatible-credentials': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible credentials',
                text: 'The supplied CloudHSM username or password is incorrect. Please update the CloudHSM credentials for the DB cluster.'
            },
            'incompatible-network': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible network',
                text: 'Amazon RDS is attempting to perform a recovery action on an cluster but is unable to do ' +
                    'so because the VPC is in a state that is preventing the action from being completed. ' +
                    'This status can occur if, for example, all available IP addresses in a subnet were in use and ' +
                    'Amazon RDS was unable to get an IP address for the DB cluster.'
            },
            'incompatible-option-group': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible option group',
                text: 'Amazon RDS attempted to apply an option group change but was unable to do so, ' +
                    'and Amazon RDS was unable to roll back to the previous option group state. ' +
                    'Consult the Recent Events list for the DB cluster for more information. ' +
                    'This status can occur if, for example, the option group contains an option such as TDE ' +
                    'and the DB cluster does not contain encrypted information.'
            },
            'incompatible-parameters': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible parameters',
                text: 'Amazon RDS was unable to start up the DB cluster because the parameters specified ' +
                    'in the cluster\'s DB parameter group were not compatible. ' +
                    'Revert the parameter changes or make them compatible with the cluster to regain access ' +
                    'to your cluster. Consult the Recent Events list for the DB cluster for more information ' +
                    'about the incompatible parameters.'
            },
            'incompatible-restore': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Incompatible restore',
                text: 'Amazon RDS is unable to do a point-in-time restore. ' +
                    'Common causes for this status include using temp tables or using MyISAM tables.'
            },
            'restore-error': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Restore error',
                text: 'The DB cluster encountered an error attempting to restore to a point-in-time or from a snapshot.'
            },
            'storage-full': {
                cls: 'red',
                iconCls: 'failed',
                title: 'Storage full',
                text: 'The cluster has reached its storage capacity allocation. ' +
                    'This is a critical status and should be remedied immediately; ' +
                    'you should scale up your storage by modifying the DB cluster. Set CloudWatch alarms to ' +
                    'warn you when storage space is getting low so you don\'t run into this situation.'
            }
        },
        os: {
            'active': {
                title: 'Enabled',
                cls: 'green',
                text: 'This operating system is available.'
            },
            'inactive': {
                title: 'Disabled',
                text: 'This operating system is not available'
            }
        },
        apikey: {
            'active': {
                title: 'Active',
                cls: 'green',
                text: 'This API key is active.'
            },
            'inactive': {
                title: 'Disabled',
                text: 'This API key is disabled'
            }
        },
        rolecategory: {
            'In use': {
                cls: 'green',
                text: 'This <b>Role Category</b> is currently used.'
            },
            'Not used': {
                text: 'This <b>Role Category</b> is currently not used.'
            }
        },
        instancehealth: {
            'InService': {
                title: 'InService',
                cls: 'green'
            },
            'OutOfService': {
                title: 'OutOfService'
            },
            'Unknown': {
                title: 'Unknown'
            }
        },
        service: {
            'unknown': {cls: 'transparent', title: '&nbsp;'},
            'running': {cls: 'green', title: 'Running'},
            'scheduled': {cls: 'green', title: 'Scheduled'},
            'idle': {cls: 'blue', title: 'Idle'},
            'failed': {cls: 'red', title: 'Failed'},
            'disabled': {cls: '', title: 'Disabled'}
        }
    },

    getHtml: function(config, qtipConfig) {
        var status = config.status || 'Unknown',
            renderData,
            html,
            statusConfig,
            tooltip = {
                align: 'r-l',
                anchor: 'right',
                width: 340
            };
        Ext.apply(tooltip, qtipConfig);
        if (Ext.isFunction(this.handlers[config.type])) {
            renderData = this.handlers[config.type].call(this, config.data, config.params);
        } else {
            renderData = {status: status};
        }

        renderData['title'] = renderData['title'] || renderData['status'];
        statusConfig = this.config[config.type] ? this.config[config.type][renderData['status']] : undefined;
        if (statusConfig) {
            renderData['cls'] = statusConfig['cls'];
            renderData['iconCls'] = renderData['iconCls'] || statusConfig['iconCls'];
            if (statusConfig['text'] && !renderData['text']) {
                renderData['text'] = Ext.isFunction(statusConfig['text']) ? statusConfig['text'](config.data) : statusConfig['text'];
            }
            if (statusConfig['title']) {
                renderData['title'] = statusConfig['title'];
            }
        }
        html =
            '<'+ (renderData['link'] ? 'a href="' + renderData['link'] + '" ' : 'div ') +
                'class="x-colored-status ' + (renderData['cls'] || '') + '" ' +
                'data-qclickable="1" ' +
                'data-anchor="' + tooltip.anchor + '" ' +
                'data-qalign="' + tooltip.align + '" ' +
                'data-qtitle="' + Ext.String.htmlEncode(renderData['tooltipTitle'] || renderData['title']) + '" ' +
                (renderData['text'] || renderData['link'] ? 'data-qtip="' + Ext.String.htmlEncode((renderData['text']||'') + (renderData['link'] ? ' <a '+(renderData['inlineLink'] ? '' : 'class="bottom-link"')+' href="' + renderData['link'] + '">' + renderData['linkText'] + '</a>' : '') + (renderData['appendText'] || '')) + '" ' : '') +
                'data-qwidth="' + tooltip.width + '">' +
                (renderData['iconCls'] ? '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-colored-status-'+renderData['iconCls']+'" />' : '' ) +
                renderData['title'] +
            '</' + (renderData['link'] ? 'a' : 'div') + '>';

        return html;//
    },

    handlers: {
        server: function(data) {
            var result = {status: data['status']},
                linkOperationStatus = '#/operations/details?serverId=' + data['server_id'] + '&operation=Initialization',
                troubleshootingLinks = {
                    'Pending launch': 'https://scalr-wiki.atlassian.net/wiki/x/CYG0',
                    'Pending': 'https://scalr-wiki.atlassian.net/wiki/x/DYG0',
                    'Initializing': 'https://scalr-wiki.atlassian.net/wiki/x/E4G0'
                };

            if (Ext.Array.contains(['Pending terminate', 'Terminated', 'Pending suspend', 'Suspended'], data['status'])) {
                if (data['termination_error']) {
                    result['tooltipTitle'] = 'Info:';
                    result['text'] = Ext.String.htmlEncode(Ext.String.htmlEncode(data['termination_error']));
                }
            } else {
                if (data['isInitFailed']) {
                    result['link'] = linkOperationStatus;
                    result['linkText'] = 'Get error details';
                    result['status'] = 'Failed';
                } else {
                    if (result['status'] === 'Pending' || result['status'] === 'Initializing') {
                        result['link'] = linkOperationStatus;
                        result['linkText'] = 'View progress';
                    }
                    if (data['launch_error'] == 1) {
                        result['iconCls'] = 'failed';
                        result['link'] = linkOperationStatus;
                        result['linkText'] = 'Get error details';
                    }
                }
                if (data['launch_error'] != 1 && troubleshootingLinks[result['status']]) {
                    result['appendText'] = '<div style="border-top:1px solid #fff;padding-top:10px;margin-top:12px;">Does this Server seem stuck in ' + result['status'] + ' State? If it does, consider reviewing the <a href="' + troubleshootingLinks[result['status']] + '" target="blank">troubleshooting documentation</a>.</div>'
                    result['inlineLink'] = true;
                }

                if (data['status'] === 'Importing') {
                    result['link'] = '#/roles/import?serverId=' + data['server_id'];
                    result['linkText'] = 'View progress';
                }
            }

            return result;
        },
        farm: function(data) {
            var titles = {
                    1: 'Running',
                    0: 'Terminated',
                    2: 'Terminating',
                    3: 'Synchronizing'
                };
            return {status: titles[data['status']] || 'Unknown'};
        },
        image: function(data) {
            var result = {}, text;

            if (data['status'] == 'failed') {
                result['status'] = 'Failed';
                result['text'] = 'Image deletion failed with error: ' + data['statusError'];
                result['cls'] = 'red';
            } else if (data['status'] == 'pending_delete') {
                result['status'] = 'Deleting';
                result['cls'] = 'yellow';
                result['text'] = 'Scalr is currently deleting this <b>image</b>.';
            } else if (data['used']) {
                text = ['This <b>Image</b> is currently used by '];
                if (data['used']['rolesCount'] > 0) {
                    text.push('<a href="#/roles/manager?imageId='+data['id']+'">'+data['used']['rolesCount']+'&nbsp;Role(s)</a>');
                }
                if (data['used']['serversCount'] > 0) {
                    text.push((data['used']['rolesCount']>0 ? ' and ' : '') + '<a href="#/servers/view?imageId='+data['id']+'">'+data['used']['serversCount']+'&nbsp;Server(s)</a>');
                }
                result['status'] = 'In use';
                result['cls'] = 'green';
                result['text'] = text.join('');
            } else {
                result['status'] = 'Not used';
                result['text'] = 'This <b>Image</b> is currently not used by any <b>Role</b> and <b>Server</b>.';
            }

            return result;
        },
        script: function(data) {
            return {status: data['isSync'] == 1 ? 'Blocking' : 'Non-blocking'};
        },
        schedulertaskscript: function(data) {
            return {status: data['config']['scriptIsSync'] == 1 ? 'Blocking' : 'Non-blocking'};
        },
        servermessage: function(data) {
            var result = {};

            if (data['status'] == 1) {
                result['status'] = data['type'] == 'out' ? 'Delivered' : 'Processed';
            } else if (data['status'] == 0) {
                result['status'] = data['type'] == 'out' ? 'Pending delivery' : 'Pending processing';
            } else if (data['status'] == 2 || data['status'] == 3) {
                result['status'] = data['type'] == 'out' ? 'Delivery failed' : 'Processing failed';
            }
            return result;
        },
        policy: function(data) {
            return {status: data['settings']['enabled'] == 1 ? 'Enforced' : 'Disabled'};
        },
        webhookendpoint: function(data) {
            return {status: data['isValid'] == 1 ? 'Validated' : 'Inactive'};
        },
        webhookhistory: function(data) {
            var result = {status: 'Pending'};
            if (data['status'] == 1) {
                result['status'] = 'Complete';
            } else if (data['status'] == 2) {
                result['status'] = 'Failed';
            }
            return result;
        },
        chefserver: function(data, params) {
            var text,
                status,
                scope = params['currentScope'];
            if (data['status']) {
                status = 'In use';
                text = ['This <b>Chef Server</b> is currently used by '];
                if (data['status']['rolesCount'] > 0) {
                    text.push(scope == 'environment' ? '<a href="#/roles/manager?chefServerId='+data['id']+'">'+data['status']['rolesCount']+'&nbsp;Role(s)</a>' : data['status']['rolesCount']+'&nbsp;Role(s)');
                }
                if (data['status']['farmsCount'] > 0) {
                    text.push((data['status']['rolesCount']>0 ? ' and ' : '') + (scope == 'environment' ? '<a href="#/farms/view?chefServerId='+data['id']+'">'+data['status']['farmsCount']+'&nbsp;Farm(s)</a>' : data['status']['farmsCount']+'&nbsp;Farm(s)'));
                }
                text = text.join('');
            } else {
                status = 'Not used';
                text = 'This <b>Chef Server</b> is currently not used by any <b>Role</b> and <b>Farm Role</b>.';
            }
            return {
                status: status,
                text: text
            };
        },
        costanalyticsnotification: function(data) {
            return {status: data['status'] == 1 ? 'Enabled' : 'Disabled'};
        },

        sshkey: function(data, params) {
            var text;
            if (data['status'] === 'In use') {
                text = 'Farm <a href="#/farms/view?farmId='+data['farmId']+'">'+data['farmName']+'</a> is using this key';
            } else {
                text = 'This key is no longer used by Scalr, and can safely be deleted';
            }
            return {
                status: data['status'],
                text: text
            };
        },
        osusage: function(data) {
            var result = {}, text;

            if (data['used']) {
                text = ['This <b>Operating System</b> is currently used by '];
                if (data['used']['imagesCount'] > 0) {
                    text.push(data['used']['imagesCount']+'&nbsp;<b>Image</b>(s)');
                }
                if (data['used']['rolesCount'] > 0) {
                    text.push((data['used']['imagesCount']>0 ? ' and ' : '') + data['used']['rolesCount']+'&nbsp;<b>Role</b>(s)');
                }
                result['status'] = 'In use';
                result['cls'] = 'green';
                result['text'] = text.join('');
            } else {
                result['status'] = 'Not used';
                result['text'] = 'This <b>Operating System</b> is currently not used by any <b>Image</b> and <b>Role</b>.';
            }

            return result;
        },

        apikey: function(data, params) {
            return {
                status: data['active'] == 0 ? 'inactive' : 'active'
            };
        }

    }
});

Ext.define('Ext.chart.theme.Scalr', {
    extend: 'Ext.chart.theme.Base',
    alias: 'chart.theme.scalr',
    config: {
        chart: {
            defaults: {
                animation: false,
                background: 'none'
            }
        },
        series: {
            defaults: {
                style: {
                    strokeStyle: 'none'
                }
            },
            bar: {
                style: {
                    minBarWidth: 10,
                    maxBarWidth: 30,
                    minGapWidth: 10
                }
            }
        },
        axis: {
            defaults: {
                label: {
                    fillStyle: '#2c496b',
                    fontSize: 14,
                    fontFamily: 'OpenSansRegular'
                },
                style: {
                    strokeStyle: '#b9d2ec'
                }
            },
            left: {
                label: {
                    textAlign: 'right'
                }
            }
        }
    }
});

//extjs5 ready
Ext.define('Ext.form.field.plugin.Icons', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.fieldicons',
    pluginId: 'fieldicons',

    position: 'inner',
    align: 'left',
    childElName: 'iconsEl',
    cls: 'x-field-icons',
    labelCls: 'x-label-with-fieldicon',

    init: function(field) {
        var me = this,
            destination,
            html = [],
            icons = {},
            iconsDefaults = {
                governance: {
                    hidden: true,
                    tooltip: 'The account owner has enforced a specific policy on {[values.fieldLabel?\'the <b>\'+values.fieldLabel+\'</b>\': \'this setting.\']}',
                    tooltipData: {}
                },
                globalvars: {
                    tooltip: 'This field supports Global Variable Interpolation.'
                },
                question: {
                    hidden: true
                },
                szrversion: {
                    iconCls: 'warning',
                    tooltip: 'Feature only available in Scalarizr starting from {version}'
                }
            };

        me.extendField();

        //todo inject me.childElName to field.childEls instead of "this.iconsEl = this.bodyEl.down('.'+me.cls)"
        /*if (Ext.isArray(field.childEls)) {
            field.getChildEls().push(me.childElName);
        } else {
            field.getChildEls[field.childElName] = {name: me.childElName, itemId: me.childElName};
        }*/

        if (me.position === 'label') {
            destination = 'afterLabelTextTpl';
        } else {
            if (field.isCheckbox) {
                destination = 'afterBoxLabelTextTpl';
            } else {
                destination = me.align === 'left' ? 'beforeSubTpl' : 'afterSubTpl';
            }
        }

        me.visibleIconsCount = 0;
        html.push('<span id="' + field.id+'-' + me.childElName + '" class="' + me.cls + ' ' + me.cls + '-' + me.align + '">');
        Ext.each(me.icons, function(icon, i){
            var iconId;
            if (Ext.isString(icon)) {
                iconId = icon;
                icons[iconId] = {};
            } else {
                iconId = icon.id;
                icons[iconId] = Ext.apply({}, icon);
            }
            Ext.applyIf(icons[iconId], iconsDefaults[iconId]);

            if (icons[iconId].tooltipData) {
                if (iconId === 'governance' && !icons[iconId].tooltipData['fieldLabel']) {
                    icons[iconId].tooltipData['fieldLabel'] = field.getFieldLabel();
                }
                icons[iconId].tooltip = new Ext.XTemplate(icons[iconId].tooltip).apply(icons[iconId]['tooltipData']);
            }
            me.icons = icons;
            html.push('<img src="' + Ext.BLANK_IMAGE_URL + '" data-iconid="' + iconId + '" class="x-icon-' + (icons[iconId]['iconCls'] || iconId) + '" data-qclickable="1" data-qtip="' + Ext.String.htmlEncode(icons[iconId]['tooltip'] || '') + '" ' + (icons[iconId]['hidden'] ? 'style="display:none"' : '') + ' />');
            me.visibleIconsCount += icons[iconId]['hidden'] ? 0 : 1;
        });
        me.initialVisibleIconsCount = me.visibleIconsCount;
        html.push('</span>');

        field[destination] = html.join('') + (field[destination] || '');

        field.on('afterrender', function() {
            this.bodyEl.setStyle({position: 'relative'});
            this.iconsEl = this.el.down('.'+me.cls);
            me.refreshIconsStyle();
        }, field, {priority: 1});

        if (me.position === 'label') {
            field.on('beforefieldlabelchange', function(field, opts) {
                me.visibleIconsCount = me.initialVisibleIconsCount;
                opts.label += this.afterLabelTextTpl;
            });
            field.on('fieldlabelchange', function (field) {
                this.iconsEl = this.el.down('.' + me.cls);
                me.refreshIconsStyle();
            })
        }
    },

    extendField: function() {
        var me = this,
            field = me.getCmp();
        Ext.each(['hideIcons', 'toggleIcon', 'updateIconTooltip', 'setValueWithGovernance'], function(method) {
            if (!field[method]) {
                field[method] = Ext.bind(me[method], me);
            } else {
                Ext.log.warn('plugins.fieldicons: method ' + method + ' is in use');
            }
        });
    },

    refreshIconsStyle: function() {
        var field = this.getCmp(),
            style = {},
            iconsWidth;
        if (!field.rendered || field.isCheckbox) return;

        iconsWidth = this.visibleIconsCount * 24;

        switch (this.position) {
            case 'label':
                if (field.labelEl) {
                    field.labelEl[this.visibleIconsCount ? 'addCls' : 'removeCls'](this.labelCls);
                }
                break;
            case 'over':
                break;
            default:
                if (this.position === 'inner' && field.labelAlign !== 'top') {
                    field.bodyEl.setStyle('padding-' + this.align, iconsWidth + 'px');
                } else {
                    style[this.align] = (0 - iconsWidth) + 'px';
                    if (this.align === 'top') {
                       style['top'] = '28px';
                    }
                    field.iconsEl.setStyle(style);
                }
                break;
        }
    },

    hideIcons: function() {
        var field = this.getCmp();
        if (field.rendered) {
            this.visibleIconsCount = 0;
            Ext.each(field.iconsEl.query('img'), function(iconEl){
                Ext.fly(iconEl).setVisibilityMode(Ext.Element.DISPLAY).setVisible(false);
            });
            this.refreshIconsStyle();
        } else {
            Ext.Object.each(this.icons, function(key, value){
                value['hidden'] = true;
            });
        }
    },

    toggleIcon: function(icon, show) {
        var me = this,
            field = me.getCmp();
        if (!me.icons || !me.icons[icon]) return me;
        fn = function() {
            var iconEl = field.iconsEl.query('[data-iconid="'+icon+'"]');
            if (iconEl.length) {
                iconEl = Ext.fly(iconEl[0]);
                var isVisible = iconEl.isVisible();
                if (show === undefined) {
                    show = !isVisible;
                    me.visibleIconsCount--;
                } else if (show && !isVisible) {
                    me.visibleIconsCount++;
                } else if (!show && isVisible) {
                    me.visibleIconsCount--;
                }
                iconEl.setVisibilityMode(Ext.Element.DISPLAY).setVisible(!!show);
                me.refreshIconsStyle();
            }
        }
        if (field.rendered) {
            fn();
        } else {
            field.on('afterrender', fn, field, {single: true});
        }

        return me;
    },

    updateIconTooltip: function(icon, tooltip) {
        var me = this,
            field = me.getCmp();
        if (!me.icons || !me.icons[icon]) return me;
        fn = function() {
            var iconEl = field.iconsEl.query('[data-iconid="'+icon+'"]');
            if (iconEl.length) {
                iconEl = Ext.fly(iconEl[0]);
                iconEl.set({'data-qtip': tooltip});
            }
        }
        if (field.rendered) {
            fn();
        } else {
            field.on('afterrender', fn, field, {single: true});
        }

        return me;
    },

    setValueWithGovernance: function(value, limit) {
        var governanceEnabled = limit !== undefined,
            field = this.getCmp();
        field.setValue(governanceEnabled ? limit : value);
        field.setReadOnly(governanceEnabled, false);
        this.toggleIcon('governance', governanceEnabled);
    }

});

Ext.define('Ext.form.field.plugin.InnerIcon', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.fieldinnericon',

    iconClsPrefix: '',
    field: null,//required
    tooltipField: null,
    tooltipSuffix: '',
    innerIconStyle: 'top:6px;left:6px;',
    inputPadding: 30,

    init: function(field) {
        var me = this,
            tpl = field.tpl;

        field.listConfig = field.listConfig || {};

        if (field.listConfig.tpl) {
            tpl = field.listConfig.tpl;
        }

        if (tpl) {
            if (!tpl.isTemplate) {
                tpl = new Ext.XTemplate(tpl);
            }
            tpl.getInnerIcon = Ext.bind(me.getInnerIcon, me);

            field.listConfig.tpl = tpl;
        }
        var img = me.getInnerIconTpl();
        if (field.listConfig.getInnerTpl) {
            var getInnerTpl = field.listConfig.getInnerTpl;
            field.listConfig.getInnerTpl = function(displayField) {
                return getInnerTpl.apply(this, [displayField, img]);
            };
        } else {
            field.listConfig.getInnerTpl = function(displayField) {
                return img + '{' + displayField + '}';
            };
        }

        field.on({
            afterrender: function () {
                this.bodyEl.setStyle('position', 'relative');
                me.innerIconEl = this.inputCell.createChild({
                    tag: 'img',
                    src: Ext.BLANK_IMAGE_URL,
                    style: 'position:absolute;' + me.innerIconStyle
                });
                me.innerIconEl.setVisibilityMode(Ext.Element.DISPLAY);
                me.updateFieldIcon(this.getValue());
            },
            change: function(comp, newValue, oldValue) {
                if (me.innerIconEl) {
                    me.updateFieldIcon(newValue);
                }
            }
        });
    },

    updateFieldIcon: function(newValue) {
        var me = this,
            field = me.getCmp(),
            rec = field.findRecordByValue(newValue);
        if (me._currentCls) {
            me.innerIconEl.removeCls(me._currentCls);
            delete me._currentCls;
        }
        if (rec && rec.get(me.field)) {
            me._currentCls = me.iconClsPrefix + rec.get(me.field);
            me.innerIconEl.addCls(me._currentCls);
            if (me.tooltipField) {
                me.innerIconEl.set({'data-qtip': Ext.String.capitalize(rec.get(me.tooltipField)) + me.tooltipSuffix});
            } else if (me.tooltipScopeType) {
                me.innerIconEl.set({'data-qtip': Scalr.utils.getScopeLegend(me.tooltipScopeType, true), 'data-qclass': 'x-tip-light' });
            }
            me.innerIconEl.show();
            field.inputEl.setStyle('padding-left', me.inputPadding + 'px');
        } else {
            field.inputEl.setStyle('padding-left', '7px');
            me.innerIconEl.hide();
        }
    },

    getInnerIcon: function(values) {
        var tooltip = '';
        if (this.tooltipField) {
            tooltip = Ext.String.capitalize(values[this.tooltipField]) + this.tooltipSuffix;
        } else if (this.tooltipScopeType) {
            tooltip = Scalr.utils.getScopeLegend(this.tooltipScopeType);
        }
        return '<img src="'+Ext.BLANK_IMAGE_URL+'" class="' + this.iconClsPrefix + values[this.field] + '" data-qclass="x-tip-light" data-qtip="' + tooltip + '"/>';
    },

    getInnerIconTpl: function() {
        var tooltip = '';
        if (this.tooltipField) {
            tooltip = '{' + this.tooltipField + ':capitalize}' + this.tooltipSuffix;
        } else if (this.tooltipScopeType) {
            tooltip = Scalr.utils.getScopeLegend(this.tooltipScopeType);
        }
        return '<img src="'+Ext.BLANK_IMAGE_URL+'" class="'+this.iconClsPrefix+'{'+this.field+'}" data-qclass="x-tip-light" data-qtip="' + tooltip + '"/>&nbsp;&nbsp;';
    }
});


Ext.define('Ext.form.field.plugin.InnerIconScope', {
    extend: 'Ext.form.field.plugin.InnerIcon',
    alias: 'plugin.fieldinnericonscope',
    pluginId: 'fieldinnericonscope',

    iconClsPrefix: 'scalr-scope-',
    field: 'scope',
    tooltipScopeType: null,
    innerIconStyle: 'top:9px;left:6px;',
    inputPadding: 28
});

Ext.define('Ext.form.field.plugin.InnerIconRdsInstanceEngine', {
    extend: 'Ext.form.field.plugin.InnerIcon',
    alias: 'plugin.fieldinnericonrds',
    pluginId: 'fieldinnericonrds',

    iconClsPrefix: 'x-icon-engine-small x-icon-engine-small-',
    field: 'id',
    innerIconStyle: 'top: 5px; left: 7px;',
    inputPadding: 34
});

Ext.define('Ext.form.field.plugin.InnerIconCloud', {
    extend: 'Ext.form.field.plugin.InnerIcon',
    alias: 'plugin.fieldinnericoncloud',
    pluginId: 'fieldinnericoncloud',

    iconClsPrefix: 'x-icon-platform-small x-icon-platform-small-',
    innerIconStyle: 'top:6px;left:7px;',
    inputPadding: 34,

    platform: '',

    setPlatform: function (platform) {
        var me = this;

        me.platform = platform;

        var field = me.getCmp();

        field.listConfig.getInnerTpl = function (displayField) {
            return me.getInnerIconTpl() + '{' + displayField + '}';
        };

        field.picker = null;

        me.updateFieldIcon();

        return true;
    },

    updateFieldIcon: function() {
        var me = this,
            field = me.getCmp(),
            platform = me.platform;

        if (!Ext.isEmpty(me._currentCls)) {
            me.innerIconEl.removeCls(me._currentCls);
            delete me._currentCls;
        }

        if (!Ext.isEmpty(platform)) {
            me._currentCls = me.iconClsPrefix + platform;
            me.innerIconEl.addCls(me._currentCls);
            me.innerIconEl.set({'data-qtip': Scalr.utils.getPlatformName(platform) + me.tooltipSuffix});
            me.innerIconEl.show();
            field.inputEl.setStyle('padding-left', me.inputPadding + 'px');
        } else {
            field.inputEl.setStyle('padding-left', '7px');
            me.innerIconEl.hide();
        }
    },

    getInnerIcon: function () {
        var me = this;

        return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="' +
            me.iconClsPrefix + me.platform +
            '" data-qtip="' + Scalr.utils.getPlatformName(me.platform) + '"/>';
    },

    getInnerIconTpl: function () {
        return this.getInnerIcon() + '&nbsp;&nbsp;';
    }
});


Ext.define('Scalr.ui.PanelBgiFrame', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.bgiframe',

    init: function(client) {
        this.client = client;
        this.showBgiFrameDelayed = Ext.Function.createDelayed(this.showBgiFrame, 200);
        client.on({
            afterlayout: this.showBgiFrameDelayed,
            show: this.showBgiFrameDelayed,
            hide: this.hideBgiFrame,
            scope: this
        });
    },

    destroy: function() {
        this.client.un({
            afterlayout: this.showBgiFrameDelayed,
            show: this.showBgiFrameDelayed,
            hide: this.hideBgiFrame,
            scope: this
        });
        if (this.iframeEl) {
            this.iframeEl.remove();
            delete this.iframeEl;
        }
    },

    showBgiFrame: function() {
        if (!this.client.isVisible()) return;
        if (!this.iframeEl) {
            var iframe = document.createElement('iframe');
            var iframeStyle = iframe.style;
            iframeStyle.setProperty('display', 'none');
            iframeStyle.setProperty('position', 'absolute', 'important');
            iframeStyle.setProperty('border', '0px', 'important');
            iframeStyle.setProperty('zIndex', '5', 'important');
            document.body.appendChild(iframe);
            this.iframeEl = Ext.get(iframe);
        }

        if (this.client.el) {
            this.iframeEl.setBox(this.client.el.getBox());
            this.iframeEl.show();
        }
    },
    hideBgiFrame: function() {
        if (this.iframeEl) {
            this.iframeEl.hide();
        }
    }
});

Ext.define('Scalr.RepeatingRequest', {
    extend: 'Ext.Evented',

    requestCount: 0,

    requestLimit: 3,

    timeout: 0,

    step: 5000,

    doNotHideErrorMessages: false,

    setTimeout: function (timeout) {
        var me = this;

        me.timeout = timeout;

        return me;
    },

    getTimeout: function () {
        return this.timeout;
    },

    getRequestConfig: function () {
        return Ext.clone(this.requestConfig);
    },

    doStep: function () {
        var me = this;

        me.setTimeout(me.getTimeout() + me.step);

        me.requestConfig.hideErrorMessage = !me.doNotHideErrorMessages
            ? me.requestLimit - me.requestCount !== 1
            : false;

        return me;
    },

    onSuccess: function (responseData) {
        var me = this;

        me.fireEvent('success', responseData);
        me.destroy();

        return me;
    },

    onFailure: function (responseData) {
        var me = this;

        me.requestCount++;

        if (me.requestCount >= me.requestLimit) {
            me.fireEvent('failure', responseData);
            me.destroy();
            return me;
        }

        me.doStep();

        Ext.Function.defer(
            me.doRequest,
            me.getTimeout(),
            me
        );

        return me;
    },

    doRequest: function () {
        var me = this;

        if (!me.isDestroyed) {
            var request = Scalr.Request(me.getRequestConfig());
            me.fireEvent('request', request);
        }

        return me;
    },

    request: function (config) {
        var me = this;

        me.requestConfig = Ext.apply(config, {
            hideErrorMessage: !me.doNotHideErrorMessages,
            success: me.onSuccess,
            failure: me.onFailure,
            scope: me
        });

        if (me.timeout > 0) {
            Ext.Function.defer(
                me.doRequest,
                me.getTimeout(),
                me
            );
            return me;
        }

        me.doRequest();

        return me;
    }
});

Ext.define('Scalr.ui.RepeatableTask', {

    interval: 5000,

    getArgs: Ext.emptyFn,

    stopIf: Ext.emptyFn,

    handleRequest: false,

    maybeAbortRequest: function () {
        var me = this;

        var currentRequest = me.request;

        if (!Ext.isEmpty(currentRequest) && Ext.Ajax.isLoading(currentRequest)) {
            Ext.Ajax.abort(currentRequest);
        }

        return true;
    },

    createRequestHandler: function (taskRunFn) {
        var me = this;

        return function () {
            me.maybeAbortRequest();

            me.request = taskRunFn.apply(me.scope, arguments);

            return true;
        };
    },

    initTask: function (config) {
        var me = this;

        Ext.apply(me, config);

        me.runner = new Ext.util.TaskRunner();

        me.task = me.runner.newTask({
            run: !me.handleRequest ? me.run : me.createRequestHandler(me.run),
            interval: me.interval,
            scope: me.scope
        });

        if (!Ext.isEmpty(me.subscribers)) {
            me.initSubscribers();
        }
    },

    constructor: function (config) {
        var me = this;

        me.initTask(config);
    },

    initSubscribers: function () {
        var me = this;

        var subscribers = me.subscribers;

        Ext.Object.each(subscribers, function (action, config) {
            subscribers[action] = me.formatSubscriberConfig(action, config);

            if (action !== 'restart') {
                me.subscribeOn(action);
            }
        });

        return subscribers;
    },

    formatSubscriberConfig: function (action, config) {
        var me = this;

        config = Ext.isArray(config) ? config : [ config ];

        return Ext.Array.map(config, function (configItem) {
            if (Ext.isString(configItem)) {
                configItem = {
                    event: configItem,
                    scope: me.scope
                };
            }

            return configItem;
        });
    },

    subscribeOn: function (action) {
        var me = this;

        Ext.Array.each(me.subscribers[action], function (config) {
            var component = config.scope;
            var options = action === 'start' ? { single: true } : null;

            component.on(config.event, me[action], me, options);
        });

        return me;
    },

    unsubscribeFrom: function (action) {
        var me = this;

        Ext.Array.each(me.subscribers[action], function (config) {
            var component = config.scope;

            component.un(config.event, me[action], me);
        });

        return me;
    },

    applyArgs: function () {
        var me = this;

        me.task.args = arguments;

        return me;
    },

    start: function (forceStart) {
        var me = this;

        var task = me.task;

        if (!me.stopIf.apply(me, arguments) || !!forceStart) {
            me.applyArgs.apply(
                me,
                me.getArgs !== Ext.emptyFn
                    ? me.getArgs.apply(me, arguments)
                    : arguments
            );
            me.task.start();
            me.subscribeOn('restart');
        } else {
            me.stop();
        }

        return me;
    },

    stop: function () {
        var me = this;

        me.maybeAbortRequest();
        me.task.stop();
        me.subscribeOn('start');

        return me;
    },

    isStopped: function () {
        return this.task.stopped;
    },

    restart: function () {
        var me = this;

        if (!me.stopIf.apply(me, arguments)) {
            me.applyArgs.apply(
                me,
                me.getArgs !== Ext.emptyFn
                    ? me.getArgs.apply(me, arguments)
                    : arguments
            );
            me.task.restart();
        } else {
            me.unsubscribeFrom('restart');
            me.stop();
        }

        return me;
    },

    destroy: function () {
        var me = this;

        me.task.destroy();

        me.callParent();
    }
});

Ext.define('Scalr.ui.RepeatableFormTask', {
    extend: 'Scalr.ui.RepeatableTask',

    interval: 15000,

    handleRequest: true,

    initTask: function (config) {
        var me = this;

        var form = config.form;
        var panel = form.up('panel');
        var store = panel.down('grid').getStore();

        Ext.apply(config, {
            scope: form,
            subscribers: {
                start: 'afterloadrecord',
                restart: 'afterloadrecord',
                stop: [{
                    event: 'refresh',
                    scope: store
                }, {
                    event: 'deactivate',
                    scope: panel
                }],
                destroy: {
                    event: 'beforedestroy',
                    scope: panel
                },
                maybeAbortRequest: 'beforeloadrecord'
            }
        });

        me.callParent(arguments);
    }
});

Ext.define('Scalr.ui.FormRepeatableTaskPlugin', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.formrepeatabletask',

    start: Ext.emptyFn,

    restart: Ext.emptyFn,

    stop: Ext.emptyFn,

    stopIf: Ext.emptyFn,

    getArgs: Ext.emptyFn,

    init: function (client) {
        var me = this;

        client.on('boxready', function () {
            var task = Ext.create('Scalr.ui.RepeatableFormTask', {
                run: client[me.pluginId],
                handleRequest: Ext.isBoolean(me.handleRequest) ? me.handleRequest : true,
                interval: !Ext.isEmpty(me.interval) ? me.interval : 15000,
                form: client,
                stopIf: me.stopIf,
                getArgs: !Ext.isEmpty(me.args)
                    ? function (record) {
                        return Ext.Array.map(me.args, function (arg) {
                            return record.get(arg);
                        });
                    }
                    : me.getArgs
            });

            Ext.apply(me, {
                start: function () {
                    task.start.apply(task, arguments);
                },
                restart: function () {
                    task.restart.apply(task, arguments);
                },
                stop: function () {
                    task.stop.call(task, arguments);
                }
            });
        });
    }
});

Ext.define('Scalr.ui.expandEditorPlugin', {
        extend: 'Ext.plugin.Abstract',
        alias: 'plugin.expandeditor',

        init: function (cmp) {
            this.setCmp(cmp);

            var expandEditor = {
                layout: 'fit',
                height: '80%',
                width: '80%',
                scrollable: false,

                editorConfig: {
                    value: '',
                    readOnly: false
                },

                listeners: {
                    beforeclose: function (panel) {
                        var value = panel.down(cmp.xtype).getValue();
                        cmp.focus();
                        cmp.setValue(value);
                    }
                },

                items: [{
                    xtype: cmp.xtype,
                    hideLabel: true,
                    validator: function (value) {
                        if (Ext.isFunction(cmp.validator)) {
                            return cmp.validator(value);
                        } else {
                            return true;
                        }
                    },
                    listeners: {
                        boxready: function (field) {
                            var config = field.up().editorConfig;
                            var editable = cmp.editable !== false;

                            field.emptyText = cmp.emptyText;
                            field.setValue(config.value);
                            field.setReadOnly(!editable || config.readOnly);
                            field.focus();
                        }
                    }
                }],

                dockedItems: [{
                    xtype: 'container',
                    dock: 'bottom',
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Close',
                        maxWidth: 140,
                        handler: function (button) {
                            button.up('panel').close();
                        }
                    }]
                }]
            };

            cmp.on({
                afterrender: function () {
                    var parentEl = cmp.xtype === 'codemirror' ? cmp.inputEl : cmp.inputWrap;
                    var expandBtn = parentEl.appendChild({
                        tag: 'button',
                        cls: 'x-grid-icon x-grid-icon-expand',
                        style: {
                            backgroundColor: 'transparent',
                            position: 'absolute',
                            border: 'none',
                            height: '20px',
                            width: '20px',
                            top: '5px',
                            right: '5px',
                            zIndex: '9999',
                            padding: '0'
                        }
                    });

                    parentEl.setStyle('position', 'relative');

                    expandBtn.on('click', function () {
                        Ext.apply(expandEditor.editorConfig, {
                            value: cmp.getValue(),
                            readOnly: cmp.readOnly
                        });

                        Scalr.utils.Window(expandEditor);
                    });
                }
            });
        }

});

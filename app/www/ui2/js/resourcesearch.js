/**
 * Abstract Entity data Model
 */
Ext.define('Scalr.resourcesearch.data.model.Entity', {
    extend: 'Ext.data.Model',

    fields: [
        'entityName',
        'matchField',
        'matchValue',
        'envId',
        'envName',
        'data'
    ],

    getUrl: function () {
        var me = this;

        var environment = Scalr.scope === 'account' ? '?environmentId=' + me.get('envId') : '';

        if (me.get('entityName') === 'server') {
            url = Ext.String.format('#{0}/servers/{1}/dashboard', environment, me.get('data').serverId);
        } else {
            url = Ext.String.format('#{0}/farms?farmId={1}', environment, me.get('data').id);
        }

        return url;
    },

    getIdentifier: function () {
        var me = this;

        var data = me.get('data');

        return me.get('entityName') === 'server' ? data.serverId : data.name;
    }
});

/**
 * Farm data Model
 */
Ext.define('Scalr.resourcesearch.data.model.Farm', {
    extend: 'Ext.data.Model',

    fields: [
        'id',
        'name',
        'status',
        'added',
        'createdByEmail',
        'teamName', {
            name: 'environment',
            convert: function (value, record) {
                return {
                    id: record.get('envId'),
                    name: record.get('envName')
                };
            }
        }, {
            name: 'farm',
            convert: function (value, record) {
                return {
                    id: record.get('id'),
                    name: record.get('name'),
                    envId: record.get('envId')
                };
            }
        }
    ]
});

/**
 * Server data Model
 */
Ext.define('Scalr.resourcesearch.data.model.Server', {
    extend: 'Ext.data.Model',

    fields: [
        'serverId',
        'status',
        'farmId',
        'farmName',
        'farmRoleId',
        'farmRoleName',
        'roleId',
        'platform',
        'instanceTypeName',
        'cloudLocation',
        'hostname',
        'remoteIp',
        'localIp', {
            name: 'environment',
            convert: function (value, record) {
                return {
                    id: record.get('envId'),
                    name: record.get('envName')
                };
            }
        }, {
            name: 'server',
            convert: function (value, record) {
                return {
                    serverId: record.get('serverId'),
                    envId: record.get('envId')
                };
            }
        }, {
            name: 'location',
            convert: function (value, record) {
                return {
                    platform: record.get('platform'),
                    cloudLocation: record.get('cloudLocation')
                };
            }
        }, {
            name: 'farm',
            convert: function (value, record) {
                return {
                    id: record.get('farmId'),
                    name: record.get('farmName'),
                    envId: record.get('envId')
                };
            }
        }, {
            name: 'farmRole',
            convert: function (value, record) {
                return {
                    id: record.get('roleId'),
                    name: record.get('farmRoleName'),
                    envId: record.get('envId')
                };
            }
        }
    ]
});

/**
 * Search field's picker's grid
 */
Ext.define('Scalr.resourcesearch.grid.Grid', {
    extend: 'Ext.grid.Panel',

    alias: 'widget.resourcesearchgrid',

    cls: Ext.baseCSSPrefix + 'resourcesearchgrid',

    config: {
        hideHeaders: true,
        features: [{
            ftype: 'grouping',
            id: 'grouping',
            hideGroupedHeader: true,
            groupHeaderTpl: '{name}s ({rows.length})'
        }]
    },

    viewConfig: {
        loadingText: null,
        preserveScrollOnRefresh: true
    },

    initConfig: function (config) {
        var me = this;

        Ext.apply(config, {
            store: {
                model: 'Scalr.resourcesearch.data.model.Entity',
                groupField: 'entityName',
                proxy: {
                    type: 'ajax',
                    url: '/core/xSearchResources',
                    reader: {
                        type: 'json',
                        rootProperty: 'data',
                        successProperty: 'success'
                        /*transform: function (data) {
                            if (data.success) {
                                var entities = data.data;

                                var maxEnvironmentNameLength = Ext.Array.max(entities.map(function (entity) {
                                    return entity.envName;
                                })).length;

                                // textFontWidth / 2 * length + iconWidth + iconMargin
                                var environmentBlockWidth = 13 / 2 * maxEnvironmentNameLength + 20 + 8;

                                Ext.Array.each(entities, function (entity) {
                                    entity.environmentBlockWidth = environmentBlockWidth;
                                });
                            }

                            return data;
                        }*/
                    }
                }
            }
        });

        return me.callParent(arguments);
    },

    columns: [{
        xtype: 'templatecolumn',
        flex: 1,
        tpl: [
            '<div class="{[this.baseCSSPrefix]}result">',
                '<img src="{[this.blankImageUrl]}" class="{[this.baseCSSPrefix]}icon {[this.baseCSSPrefix]}icon-{entityName}" />',
                '<span class="{[this.baseCSSPrefix]}fieldname">{matchField}:</span> {matchValue}',
            '</div>',

            '<div class="{[this.baseCSSPrefix]}environment">',
                '<img src="{[this.blankImageUrl]}" class="{[this.baseCSSPrefix]}icon {[this.baseCSSPrefix]}icon-environment" />',
                '<span>{envName}</span>',
            '</div>', {
                blankImageUrl: Ext.BLANK_IMAGE_URL,
                baseCSSPrefix: Ext.baseCSSPrefix
            }
        ]
    }],

    initEvents: function () {
        var me = this;

        me.on({
            rowkeydown: me.onRowKeyDown,
            beforeselect: me.onBeforeSelect,
            scope: me
        });

        return me.callParent();
    },

    onBeforeSelect: function () {
        var me = this;

        var scroller = me.getView().getScrollable();

        if (!Ext.isEmpty(scroller)) {
            var position = scroller.getPosition();

            me.on('select', function () {
                scroller.scrollTo(position);
            }, me, {single: true});
        }

        return true;
    },

    onRowKeyDown: function (grid, record, tr, rowIndex, e) {
        var me = this;

        var key = e.getKey();

        if (key === e.DOWN) {
            return me.up('panel').pickerField.onDownArrow();
        } else if (key === e.UP) {
            return me.up('panel').pickerField.onUpArrow();
        } else if (key === e.ENTER) {
            Scalr.event.fireEvent('redirect', record.getUrl());
        }

        return false;
    }
});

/**
 * Search field's picker's base form
 */
Ext.define('Scalr.resourcesearch.form.Panel', {
    extend: 'Ext.form.Panel',

    alias: 'widget.resourcesearchform',

    cls: Ext.baseCSSPrefix + 'resourcesearchform',

    config: {
        scrollable: 'vertical',
        defaults: {
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            defaults: {
                xtype: 'displayfield'
            },
            fieldDefaults: {
                labelWidth: 125
            }
        }
    },

    onBoxReady: function () {
        var me = this;

        me.down('[name=environment]').setVisible(Scalr.scope === 'account');

        return me.callParent();
    }
});

/**
 * Search field's picker's Farm form
 */
Ext.define('Scalr.resourcesearch.form.FarmPanel', {
    extend: 'Scalr.resourcesearch.form.Panel',

    alias: 'widget.resourcesearchfarmform',

    config: {
        items: [{
            items: [{
                name: 'id',
                fieldLabel: 'ID'
            }, {
                name: 'farm',
                fieldLabel: 'Name',
                valueText: '<a href="#{0}/farms?farmId={1}">{2}</a>',
                renderer: function (value) {
                    var environment = Scalr.scope === 'account' ? '?environmentId=' + value.envId : '';

                    return Ext.String.format(this.valueText, environment, value.id, value.name);
                }
            }, {
                name: 'environment',
                fieldLabel: 'Environment',
                valueText: '<a href="#?environmentId={0}/dashboard">{1}</a>',
                renderer: function (value) {
                    return Ext.String.format(this.valueText, value.id, value.name);
                }
            }, {
                name: 'status',
                fieldLabel: 'Status',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? Scalr.utils.getStatusHtml('farm', value) : '&mdash;';
                }
            }, {
                name: 'added',
                fieldLabel: 'Added on'
            }, {
                name: 'createdByEmail',
                fieldLabel: 'Created by'
            }, {
                name: 'teamName',
                fieldLabel: 'Team',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }]
        }]
    }
});

/**
 * Search field's picker's Server form
 */
Ext.define('Scalr.resourcesearch.form.ServerPanel', {
    extend: 'Scalr.resourcesearch.form.Panel',

    alias: 'widget.resourcesearchserverform',

    config: {
        items: [{
            items: [{
                name: 'server',
                fieldLabel: 'Server ID',
                valueText: '<a href="#{0}/servers/{1}/dashboard">{1}</a>',
                renderer: function (value) {
                    var environment = Scalr.scope === 'account' ? '?environmentId=' + value.envId : '';

                    return Ext.String.format(this.valueText, environment, value.serverId);
                }
            }, {
                name: 'environment',
                fieldLabel: 'Environment',
                valueText: '<a href="#?environmentId={0}/dashboard">{1}</a>',
                renderer: function (value) {
                    return Ext.String.format(this.valueText, value.id, value.name);
                }
            }, {
                name: 'location',
                fieldLabel: 'Location',
                valueText: '<img src="{0}" class="x-icon-platform-small x-icon-platform-small-{1}" style="margin-right: 6px;" data-qtip="{2}"/><span>{3}</span>',
                renderer: function (value) {
                    return !Ext.isObject(value) ? '&mdash;' : Ext.String.format(
                        this.valueText,
                        Ext.BLANK_IMAGE_URL,
                        value.platform,
                        Scalr.utils.getPlatformName(value.platform),
                        value.cloudLocation
                    );
                }
            }, {
                name: 'farm',
                fieldLabel: 'Farm',
                valueText: '<a href="#{0}/farms?farmId={1}">{2}</a>',
                renderer: function (value) {
                    var environment = Scalr.scope === 'account' ? '?environmentId=' + value.envId : '';

                    return Ext.String.format(this.valueText, environment, value.id, value.name);
                }
            }, {
                name: 'farmRole',
                fieldLabel: 'Role name',
                valueText: '<a href="#{0}/roles?roleId={1}">{2}</a>',
                renderer: function (value) {
                    var environment = Scalr.scope === 'account' ? '?environmentId=' + value.envId : '';

                    return Ext.String.format(this.valueText, environment, value.id, value.name);
                }
            }, {
                name: 'status',
                fieldLabel: 'Status',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? Scalr.utils.getStatusHtml('server', value) : '&mdash;';
                }
            }, {
                name: 'instanceTypeName',
                fieldLabel: 'Instance type'
            }, {
                name: 'hostname',
                fieldLabel: 'Hostname'
            }, {
                name: 'publicIp',
                fieldLabel: 'Public IP'
            }, {
                name: 'privateIp',
                fieldLabel: 'Private IP'
            }, {
                name: 'added',
                fieldLabel: 'Added on'
            }]
        }]
    }
});

/**
 * Search field's picker
 */
Ext.define('Scalr.resourcesearch.panel.Panel', {
    extend: 'Ext.panel.Panel',

    cls: Ext.baseCSSPrefix + 'resourcesearchpicker',

    config: {
        floating: true,
        focusable: true,
        tabIndex: 0,
        hidden: true,
        shadow: false,
        layout: {
            type: 'hbox',
            align: 'stretch'
        }
    },

    initConfig: function (config) {
        var me = this;

        Ext.apply(config, {
            items: [{
                xtype: 'resourcesearchgrid',
                scope: config.scope,
                flex: 0.56
            }, {
                xtype: 'container',
                itemId: 'formContainer',
                cls: Ext.baseCSSPrefix + 'resourcesearchform-container',
                flex: 0.44,
                layout: 'card',
                items: [{
                    xtype: 'resourcesearchfarmform',
                    itemId: 'farm'
                }, {
                    xtype: 'resourcesearchserverform',
                    itemId: 'server'
                }]
            }]
        });

        return me.callParent(arguments);
    },

    initComponent: function () {
        var me = this;

        me.callParent();

        me.grid = me.down('resourcesearchgrid');
        me.store = me.grid.getStore();

        return true;
    },

    initEvents: function () {
        var me = this;

        Ext.on('resize', me.onWindowResize, me);

        me.getStore().on('load', me.doResize, me);

        me.on('boxready', me.doResize, me);

        me.grid.on({
            beforeselect: me.beforeSelect,
            select: me.onSelect,
            scope: me
        });

        return me.callParent();
    },

    doResize: function () {
        return this.onWindowResize(null, Ext.getBody().getHeight());
    },

    onWindowResize: function (width, height) {
        var me = this;

        var y = me.getY();

        if (y < 0) {
            var searchField = me.pickerField;
            y = searchField.getY() + searchField.getHeight();
        }

        var maxHeight = height - y - me.bottomMargin;

        me.setMaxHeight(maxHeight);

        var minHeight = me.getMinHeight() >= maxHeight ? maxHeight : me.getInitialConfig('minHeight');

        me.setMinHeight(minHeight);

        return true;
    },

    beforeDestroy: function () {
        var me = this;

        Ext.un('resize', me.onWindowResize, me);

        return me.callParent();
    },

    beforeSelect: function (rowModel, record) {
        return this.setForm(record.get('entityName'));
    },

    onSelect: function (rowModel, record) {
        var me = this;

        var model = Ext.create(
            'Scalr.resourcesearch.data.model.' + Ext.String.capitalize(record.get('entityName')),
            record.get('data')
        );

        return me.down('#formContainer').getLayout().getActiveItem().loadRecord(model);
    },

    setForm: function (itemId) {
        var me = this;

        if (me.currentFormId !== itemId) {
            me.currentFormId = itemId;

            var formContainer = me.down('#formContainer');
            var form = formContainer.down('#' + itemId);

            formContainer.getLayout().setActiveItem(form);
        }

        return true;
    },

    getGrid: function () {
        return this.grid;
    },

    getStore: function () {
        return this.store;
    },

    moveTo: function (position) {
        var me = this;

        var grid = me.getGrid();
        var store = grid.getStore();
        var selectedRecordIndex = store.indexOf(grid.getSelection()[0]);
        var record = store.getAt(selectedRecordIndex + (position === 'next' ? 1 : -1));

        if (!Ext.isEmpty(record)) {
            grid.setSelection(record);

            return record;
        }

        return null;
    }
});

/**
 * Search field
 */
Ext.define('Scalr.resourcesearch.form.field.Search', {
    extend: 'Ext.form.field.Picker',

    alias: [
        'widget.resourcesearchfield',
        'widget.resourcesearch'
    ],

    config: {
        checkChangeEvents: [ 'keyup' ],
        pickerOffset: [ 0, 0 ],
        pickerAlign: 'bl',
        context: {
            scope: 'account',
            search: [ 'farms', 'servers' ]
        },
        triggers: {
            picker: {
                disabled: true,
                hidden: true
            },
            loader: {
                weight: 0,
                hidden: true,
                cls: Ext.baseCSSPrefix + 'resourcesearchfield-loader'
            },
            searchSummary: {
                weight: 1,
                cls: Ext.baseCSSPrefix + 'resourcesearchfield-searchsummary',
                extraCls: Ext.browser.is('firefox') ? Ext.baseCSSPrefix + 'firefox' : null,
                setText: function (text, type) {
                    return this.getEl().setHtml(text);
                },
                scope: 'this'
            },
            clear: {
                weight: 2,
                hidden: true,
                cls: Ext.baseCSSPrefix + 'resourcesearchfield-clear',
                handler: 'clearSearch'
            },
            search: {
                weight: 3,
                cls: Ext.baseCSSPrefix + 'resourcesearchfield-search',
                handler: 'search'
            }
        }
    },

    cls: Ext.baseCSSPrefix + 'resourcesearchfield',

    emptyText: 'Search Farms and Servers',

    defaultSearchText: '<span>Press <span class="x-semibold">Esc</span> to close</span>',
    searchSummaryText: '<span><span class="x-semibold">{0}</span> {1}</span>',

    createPickerOnReady: true,

    collapseIf: Ext.emptyFn,
    onFocusLeave: Ext.emptyFn,

    //private
    redirectOnEnter: false,

    getContext: function () {
        return this.context;
    },

    setContext: function (context, value) {
        var me = this;

        var contextConfig = me.getContext();
        contextConfig = Ext.isObject(contextConfig) ? contextConfig : {};

        if (Ext.isString(context)) {
            contextConfig[context] = value;
        } else {
            Ext.apply(contextConfig, context);
        }

        me.context = contextConfig;

        return me.context;
    },

    initTriggers: function (triggers) {
        var me = this;

        triggers.search.scope = triggers.clear.scope = me;

        return me.callParent(arguments);
    },

    initEvents: function () {
        var me = this;

        me.callParent();

        me.on('focuschange', me.onFocusChange, me);

        me.up('panel').on('boxready', me.onBoxReady, me);

        me.keyNav = new Ext.util.KeyNav(me.inputEl, {
            up: me.onUpArrow,
            esc: me.onEsc,
            enter: me.onEnter,
            scope: me
        });

        me.getPicker().getStore().on({
            load: me.onPickerStoreLoad,
            clear: me.onPickerStoreClear,
            scope: me
        });

        me.globalKeyMap = new Ext.util.KeyMap({
            target: Ext.getBody(),
            key: Ext.event.Event.ESC,
            fn: function (keyIndex, event) {
                me.onEsc(event);
            },
            scope: me
        });

        Ext.getBody().on('click', me.onBodyClick, me);

        return true;
    },

    onBodyClick: function (event, node) {
        var me = this;

        if (Ext.get(node).hasCls('x-mask') && me.rendered) {
            me.up('#box').close();
        }

        return true;
    },

    onFocusChange: function (focusOn) {
        var me = this;

        var redirectOnEnter = focusOn === 'picker';

        if (redirectOnEnter !== me.redirectOnEnter) {
            me.redirectOnEnter = redirectOnEnter;
        }

        return true;
    },

    onDownArrow: function () {
        var me = this;

        if (!me.isExpanded) {
            return false;
        }

        return me.moveTo('next');
    },

    onUpArrow: function () {
        var me = this;

        if (!me.isExpanded) {
            return false;
        }

        var previousRecord = me.moveTo('previous');

        if (previousRecord === null) {
            me.focus();
        }

        return true;
    },

    moveTo: function (position) {
        var me = this;

        me.fireEvent('focuschange', 'picker');

        return me.isExpanded ? me.getPicker().moveTo(position) : null;
    },

    onEsc: function (event) {
        var me = this;

        if (!me.getTrigger('clear').hidden && !Ext.isEmpty(event)) {
            event.stopEvent();
        }

        return me.clearSearch();
    },

    onEnter: function () {
        var me = this;

        if (!me.redirectOnEnter || !me.isExpanded) {
            me.search();
        } else {
            Scalr.event.fireEvent('redirect',
                me.getPicker().getGrid().getSelection()[0].getUrl()
            );
        }

        return true;
    },

    onFieldMutation: function (event) {
        var me = this;

        var key = event.getKey();

        if (me.redirectOnEnter && key !== event.DOWN && key !== event.UP && key !== event.ENTER) {
            me.fireEvent('focuschange', 'field');

            if (me.isExpanded) {
                //me.getTrigger('searchSummary').setText(me.searchSummary);
            }
        }

        return true;
    },

    onMouseDown: function () {
        var me = this;

        me.fireEvent('focuschange', 'field');

        return me.callParent();
    },

    clearSearch: function () {
        var me = this;

        me.reset();
        me.clearStore();
        me.collapse();
        me.focus();

        return true;
    },

    onPickerStoreLoad: function (store, records, successful) {
        var me = this;

        me.setPending(false);

        var empty = Ext.isEmpty(records);

        var searchSummaryText = 'No results';

        if (!empty) {
            me.fireEvent('focuschange', 'picker');

            var searchSummary = {}; // wtf with store.getGroups().countByGroup()

            store.getGroups().eachKey(function (key, item) {
                searchSummary[key] = item.count();
            });

            me.searchSummary = searchSummaryText = me.searchSummaryToString(searchSummary);

            me.expand();
        }

        me.getTrigger('searchSummary').setText(searchSummaryText);

        me.getTrigger('clear').show();

        if (empty) {
            me.collapse();
        }

        return successful;
    },

    //private
    searchSummaryToString: function (searchSummary) {
        var me = this;

        var mask = me.searchSummaryText;
        var strings = [];

        Ext.Object.each(searchSummary, function (entity, count) {
            var entityNameText = Ext.String.capitalize(entity) + (count > 1 ? 's' : '');
            strings.push(Ext.String.format(mask, count, entityNameText));
        });

        return 'Found ' + strings.join(', ');
    },

    onPickerStoreClear: function (store) {
        var me = this;

        me.getTrigger('searchSummary').setText(me.defaultSearchText);
        me.getTrigger('clear').hide();

        //me.collapse();

        return store;
    },

    onBoxReady: function () {
        var me = this;

        if (me.createPickerOnReady) {
            me.createPicker();
        }

        me.getTrigger('searchSummary').setText(me.defaultSearchText);

        return me.callParent();
    },

    clearStore: function () {
        var me = this;

        var store = me.getPicker().getStore();

        if (store.count() > 0) {
            store.removeAll();
        } else {
            me.onPickerStoreClear(store);
        }

        return me;
    },

    createPicker: function () {
        var me = this;

        return Ext.create('Scalr.resourcesearch.panel.Panel', {
            minHeight: Scalr.scope === 'account' ? 445 : 400,
            bottomMargin: 15,
            pickerField: me,
            scope: me.getContext().scope
        });
    },

    onExpand: function () {
        var me = this;

        var grid = me.getPicker().getGrid();
        grid.setSelection(grid.getStore().first());

        me.focus();

        return me.callParent();
    },

    searchBy: function (queryString) {
        var me = this;

        me.getPicker().getStore().applyProxyParams({
            query: queryString
        });

        return true;
    },

    search: function () {
        var me = this;

        me.collapse();

        var value = me.getValue();

        if (!Ext.isEmpty(value)) {
            me.setPending(true);
            return me.searchBy(value);
        }

        me.clearStore();

        return false;
    },

    setPending: function (pending) {
        var me = this;

        if (pending) {
            me.getTrigger('searchSummary').setText(me.defaultSearchText);
            me.getTrigger('clear').hide();
        }

        me.getTrigger('loader').setVisible(pending);

        return me;
    },

    beforeDestroy : function () {
        var me = this;

        me.globalKeyMap.destroy();

        Ext.getBody().un('click', me.onBodyClick, me);

        return me.callParent();
    }
});

Scalr.regPage('Scalr.ui.monitoring.view', function (loadParams, moduleParams) {

    var store = Ext.create('store.tree', {
        root: {
            children: moduleParams['children']
        },
        proxy: 'object'
    });

    var panel = Ext.create('Ext.panel.Panel', {
        bodyStyle: 'background: #B5C0CE;',
        scalrOptions: {
            'maximize': 'all'
        },
        layout: 'fit',
        tools: [
            {
                xtype: 'monitoringgraphstool'
            }
        ],

        stateful: true,
        stateId: 'monitoring-view',

        getState: function () {
            var me = this,
                state = null;
            state = me.addPropertyToState(state, 'watchernames');
            return state;
        },

        watchernames: ['mem', 'cpu', 'la', 'net', 'snum', 'io'],

        getHostUrl: function() {
            return moduleParams.hostUrl;
        },

        dockedItems: [
            {
                dock: 'left',
                xtype: 'panel',
                layout: 'vbox',
                bodyStyle: 'border-right: 1px solid #B5C0CE;',
                height: '100%',
                width: 324,
                items: [
                    {
                        xtype: 'buttongroupfield',
                        itemId: 'statisticsTypeSwitcher',
                        value: 'daily',
                        margin: '12 0 0 12',
                        defaults: {
                            width: 75
                        },
                        items: [
                            {
                                xtype: 'button',
                                text: 'Daily',
                                value: 'daily'
                            },
                            {
                                xtype: 'button',
                                text: 'Weekly',
                                value: 'weekly'
                            },
                            {
                                xtype: 'button',
                                text: 'Monthly',
                                value: 'monthly'
                            },
                            {
                                xtype: 'button',
                                text: 'Yearly',
                                value: 'yearly'
                            }
                        ],
                        listeners: {
                            change: function (buttons, value) {
                                panel.updateStatisticsView();
                            }
                        }
                    },
                    {
                        xtype: 'container',
                        itemId: 'treeFilterFieldAndCompareModeSwitcherContainer',
                        layout: 'hbox',
                        margin: '12 0 0 12',
                        items: [
                            {
                                xtype: 'filterfield',
                                itemId: 'treeFilterField',
                                store: store,
                                filterFields: ['text'],
                                width: 144,
                                handler: null

                                /*
                                handler: function (field, value) {
                                    var treePanel = panel.down('#farmsMonitoringTreePanel');
                                    treePanel.getStore().filter('text', value);
                                }
                                */
                            },
                            {
                                xtype: 'button',
                                enableToggle: true,
                                text: 'Compare Mode',
                                margin: '0 0 0 12',
                                width: 144,
                                toggleHandler: function (button, toggle) {
                                    var treePanel = panel.down('#farmsMonitoringTreePanel');
                                    treePanel.setCompareMode(toggle);

                                    if (toggle) {
                                        var selectedNode = treePanel.getSelectionModel().getLastSelected(),
                                            CompareModeOnParams = treePanel.prepareDataForMonitoring([selectedNode]);

                                        selectedNode.set('checked', true);
                                        treePanel.getSelectionModel().deselect(selectedNode);
                                        panel.updateStatisticsView(CompareModeOnParams);
                                    } else {
                                        var checkedNodes = treePanel.getChecked(),
                                            checkedNode;
                                        if (checkedNodes.length === 1) {
                                            checkedNode = checkedNodes[0];
                                        } else {
                                            checkedNode = treePanel.monitoredItem;
                                        }
                                        var compareModeOffParams = treePanel.prepareDataForMonitoring([checkedNode]);
                                        treePanel.getSelectionModel().select(checkedNode);
                                        panel.updateStatisticsView(compareModeOffParams);
                                    }

                                    treePanel.setCheckedNodeStyle();
                                    treePanel.showCheckboxes();
                                    treePanel.uncheckNodes();
                                }
                            }
                        ]
                    },
                    {
                        xtype: 'displayfield',
                        itemId: 'filterMessage',
                        value: 'No matches found',
                        fieldCls: 'scalr-ui-monitoring-filter-no-matches-found-message',
                        padding: '50 0 0 110',
                        hidden: true

                    },
                    {
                        xtype: 'treepanel',
                        itemId: 'farmsMonitoringTreePanel',
                        cls: 'scalr-ui-monitoring-tree-panel',

                        selType: 'monitoring',

                        rootVisible: false,
                        compareMode: false,
                        showCheckboxesAtRight: true,
                        margin: 12,
                        width: 300,
                        scroll: 'vertical',

                        hasHighlight: true,
                        highlightExceptions: ['Farm:', 'role:'],

                        store: store,

                        viewConfig: {
                            preserveScrollOnRefresh: true
                        },

                        setCompareMode: function (compareMode) {
                            var me = this;
                            me.compareMode = compareMode;
                        },

                        showCheckboxes: function () {
                            var me = this,
                                checkboxes = me.el.query('.x-tree-checkbox');

                                Ext.each(checkboxes, function (checkbox) {
                                    Ext.get(checkbox).setVisible(me.compareMode);
                                });
                        },

                        setCheckedNodeStyle: function () {
                            var me = this,
                                checkedNodes = me.getChecked(),
                                treeView = me.getView();
                            Ext.each(checkedNodes, function (checkedNode) {
                                var domElCorrespondingToTheNode = Ext.get(treeView.getNode(checkedNode));
                                if (me.compareMode && domElCorrespondingToTheNode) {
                                    domElCorrespondingToTheNode.addCls('scalr-ui-monitoring-checked-node');
                                } else if (!me.compareMode && domElCorrespondingToTheNode) {
                                    domElCorrespondingToTheNode.removeCls('scalr-ui-monitoring-checked-node');
                                }
                            });
                        },

                        hideTreeIcons: function () {
                            var me = this,
                                treeIcons = me.el.query('.x-tree-icon');
                            Ext.each(treeIcons, function (icon) {
                                Ext.get(icon).destroy();
                            });
                        },

                        uncheckNodes: function () {
                            var me = this,
                                checkedNodes = me.getChecked();
                            if (!me.compareMode) {
                                Ext.each(checkedNodes, function (checkedNode) {
                                    checkedNode.set('checked', false);
                                });
                            }
                        },

                        prepareDataForMonitoring: function (checkedNodes) {
                            var me = this,
                                params = [];
                            Ext.each(checkedNodes, function (currentNode) {
                                var currentNodesParams = currentNode.raw,
                                    researchableNode = currentNode,
                                    title = '';
                                for (var i = 0; i < currentNode.data.depth - 1; i++) {
                                    var parentsParams = researchableNode.parentNode.raw;
                                    title = parentsParams.value + ' \u2192 ' + title;
                                    researchableNode = researchableNode.parentNode;
                                }
                                title += currentNodesParams.value;

                                var isInstance = currentNode.data.depth === 3;

                                params.push({params: currentNode.raw.params, title: title, isInstance: isInstance});
                            });
                            me.cachedParams = params;
                            panel.setTitleBasedOnSelectedNode();
                            return params;
                        },

                        setNodesTextStyle: function (farmNodes) {
                            var me = this;
                            var view = me.getView();

                            Ext.Array.each(farmNodes, function (farmNode) {
                                var domFarmNode = view.getNode(farmNode);
                                var styledTitle = '<span style="font-weight: bold">Farm: ' + '<span style="color: #008000;">' + farmNode.raw.value + '</span></span>';

                                Ext.get(domFarmNode).update(domFarmNode.innerHTML.replace(farmNode.raw.text, styledTitle));

                                Ext.Array.each(farmNode.childNodes, function (roleNode) {
                                    var domRoleNode = view.getNode(roleNode);
                                    var roleText = roleNode.raw.text;

                                    Ext.get(domRoleNode).update(domRoleNode.innerHTML.replace(roleText, 'role: ' + roleText));
                                });
                            });


                        },

                        listeners: {
                            boxready: function () {
                                var me = this;
                                var farmNodes = me.getRootNode().childNodes;

                                me.setNodesTextStyle(farmNodes);
                            },
                            afterfilter: function () {
                                var me = this;
                                var farmNodes = me.getRootNode().childNodes;

                                me.setNodesTextStyle(farmNodes);
                            },
                            afterrender: function () {
                                var me = this;

                                var getMonitoredItem = function () {
                                    var farmId = loadParams.farmId;
                                    var farmRoleId = loadParams.farmRoleId;
                                    var index = loadParams.index;
                                    var farmNodes = me.getRootNode().childNodes;

                                    var getNode = function(nodeId, nodes, type) {
                                        for (var i = 0; i < nodes.length; i++) {
                                            var currentNodeId = nodes[i].raw.params[type];
                                            if (currentNodeId === nodeId) {
                                                return nodes[i];
                                            }
                                        }
                                        return nodes[0];
                                    };

                                    var farmNode = getNode(farmId, farmNodes, 'farmId');

                                    if (!farmRoleId) {
                                        return farmNode;
                                    }

                                    var farmRoleNode = getNode(farmRoleId, farmNode.childNodes, 'farmRoleId');

                                    if (!index) {
                                        return farmRoleNode;
                                    }

                                    return getNode(index,farmRoleNode.childNodes, 'index');
                                };

                                me.monitoredItem = getMonitoredItem();
                                me.getSelectionModel().select(me.monitoredItem);
                            },
                            afterlayout: function () {
                                var me = this;
                                me.showCheckboxes();
                                me.setCheckedNodeStyle();
                                me.hideTreeIcons();
                                //me.restoreFilteredNodesView();
                            },
                            select: function (context, record) {
                                var me = this;

                                if (!me.compareMode) {
                                    var view = me.getView();
                                    view.saveScrollState();

                                    var params = me.prepareDataForMonitoring([record]);
                                    panel.updateStatisticsView(params);

                                    view.restoreScrollState();
                                }
                            },
                            checkchange: function () {
                                var me = this;

                                var checkedNodes = me.getChecked();
                                var checkNodesNumber = checkedNodes.length;
                                var params = me.prepareDataForMonitoring(checkedNodes);

                                if (checkNodesNumber) {
                                    me.getView().saveScrollState();

                                    panel.updateStatisticsView(params, checkNodesNumber);
                                }
                            }
                        }
                    }
                ]
            }
        ],

        items: [
            {
                xtype: 'monitoring.statistics',
                itemId: 'monitoringStatistics'
            }
        ],

        setTreePanelHeight: function () {
            var me = this,
                panelTitle = me.el.down('.x-panel-header'),
                statisticsTypeSwitcher = me.down('#statisticsTypeSwitcher'),
                filterAndModeSwitcher = me.down('#treeFilterFieldAndCompareModeSwitcherContainer'),
                treePanel = me.down('#farmsMonitoringTreePanel'),
                panelHeight = me.getHeight(),
                panelTitleHeight = panelTitle.getHeight(),
                statisticsTypeSwitcherHeight = statisticsTypeSwitcher.getHeight(),
                filterAndModeSwitcherHeight = filterAndModeSwitcher.getHeight(),
                allVerticalMargins = 12 * 4;

            treePanel.maxHeight = panelHeight - (panelTitleHeight + statisticsTypeSwitcherHeight + filterAndModeSwitcherHeight + allVerticalMargins);
        },

        setTitleBasedOnSelectedNode: function () {
            var me = this,
                treePanel = me.down('#farmsMonitoringTreePanel'),
                compareMode = treePanel.compareMode;

            if (!compareMode) {
                var title = treePanel.cachedParams[0].title,
                    parsedTitle = title.split(' ');

                parsedTitle[0] = '<span style="color: #008000;">' + parsedTitle[0] + '</span>';
                title = parsedTitle.join(' ');
                me.setTitle('Farms &raquo; Monitoring &raquo; ' + title);
            } else {
                me.setTitle('Farms &raquo; Monitoring');
            }
        },

        updateStatisticsView: function (params, checkedNodesCount) {
            var me = this,
                treePanel = panel.down('#farmsMonitoringTreePanel'),
                statisticsView = me.down('#monitoringStatistics'),
                statisticsTypeSwitcher = me.down('#statisticsTypeSwitcher'),
                statisticsType = statisticsTypeSwitcher.value,
                compareMode = treePanel.compareMode;

            params = params || treePanel.cachedParams;
            statisticsView.updateTpl(params, statisticsType, compareMode, checkedNodesCount);
        },

        listeners: {
            resize: function () {
                var me = this;
                me.setTreePanelHeight();
            },
            beforerender: function () {
                var me = this;
                me.cachedModuleParams = moduleParams;

                var statisticsView = me.down('#monitoringStatistics');
                statisticsView.watchernames = me.watchernames;
            },
            statesave: function () {
                var me = this,
                    statisticsView = me.down('#monitoringStatistics');
                statisticsView.watchernames = me.watchernames;
            }
        }
    });

    return panel;
});

Ext.define('Scalr.ui.MonitoringTreeModel', {
    extend: 'Ext.selection.TreeModel',
    alias: 'selection.monitoring',

    onRowClick: function (view, record, item, index, e) {
        var me = this;
        var treePanel = view.panel;
        var compareMode = treePanel.compareMode;

        if (compareMode) {
            var checked = record.get('checked');

            record.set('checked', !checked);
            treePanel.fireEvent('checkchange');

            return;
        }

        me.callParent(arguments);
    }
});

Ext.define('Scalr.ui.MonitoringGraphsTool', {
    extend: 'Ext.panel.Tool',
    alias: 'widget.monitoringgraphstool',

    initComponent: function () {
        this.type = 'settings';
        this.callParent();
    },

    setCheckboxStatus: function (checkboxName) {
        var me = this,
            panel = me.up('panel'),
            statisticsView = panel.down('#monitoringStatistics');

        return statisticsView.watchernames.some(function (currentWatchername) {
            return currentWatchername === checkboxName;
        });
    },

    graphsSettingsForm: function () {
        var graphsFieldset = new Ext.form.FieldSet({
            title: 'Graphs to show'
        });
        var checkboxGroup = graphsFieldset.add({
            xtype: 'checkboxgroup',
            columns: 2,
            vertical: true,
            items: [
                {
                    xtype: 'checkbox',
                    boxLabel: 'Memory usage',
                    name: 'mem',
                    checked: this.setCheckboxStatus('mem')
                },
                {
                    xtype: 'checkbox',
                    boxLabel: 'CPU utilization',
                    name: 'cpu',
                    checked: this.setCheckboxStatus('cpu')
                },
                {
                    xtype: 'checkbox',
                    boxLabel: 'Load averages',
                    name: 'la',
                    checked: this.setCheckboxStatus('la')
                },
                {
                    xtype: 'checkbox',
                    boxLabel: 'Network usage',
                    name: 'net',
                    checked: this.setCheckboxStatus('net')
                },
                {
                    xtype: 'checkbox',
                    boxLabel: 'Servers count',
                    name: 'snum',
                    checked: this.setCheckboxStatus('snum')
                },
                {
                    xtype: 'checkbox',
                    boxLabel: 'Disk I/O',
                    name: 'io',
                    checked: this.setCheckboxStatus('io')
                }
            ]
        });
        return graphsFieldset;
    },

    handler: function () {
        var me = this;
        Scalr.Confirm({
            form: me.graphsSettingsForm(),
            success: function (data) {
                var panel = me.up('panel'),
                    graphsToView = [];
                for (var watchername in data) {
                    graphsToView.push(watchername);
                }
                panel.watchernames = graphsToView;
                panel.saveState();
                panel.updateStatisticsView();
            },
            scope: this
        });
    }
});
Scalr.regPage('Scalr.ui.monitoring.view', function (loadParams, moduleParams) {

    var store = Ext.create('store.tree', {
        root: {
            children: moduleParams['children']
        },
        proxy: 'object'
    });

    var panel = Ext.create('Ext.panel.Panel', {
        bodyCls: 'scalr-ui-monitoring-main-panel',

        scalrOptions: {
            maximize: 'all',
            menuTitle: 'Monitoring'
        },

        layout: 'fit',

        /*
        tools: [{
            xtype: 'monitoringgraphstool'
        }],
        */

        stateful: true,
        stateId: 'panel-monitoring-view',

        watchernames: ['mem', 'cpu', 'la', 'net', 'snum', 'io'],

        getState: function () {
            var me = this,
                state = null;
            state = me.addPropertyToState(state, 'watchernames');
            return state;
        },

        getHostUrl: function () {
            return moduleParams.hostUrl;
        },

        items: [{
            xtype: 'container',
            name: 'window',
            autoScroll: true,
            layout: {
                align: 'stretch'
            },
            items: [{
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'buttongroupfield',
                    itemId: 'statisticsTypeSwitcher',
                    value: 'daily',
                    margin: '15 0 0 15',
                    defaults: {
                        width: 100
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Daily',
                        value: 'daily'
                    }, {
                        xtype: 'button',
                        text: 'Weekly',
                        value: 'weekly'
                    }, {
                        xtype: 'button',
                        text: 'Monthly',
                        value: 'monthly'
                    }, {
                        xtype: 'button',
                        text: 'Yearly',
                        value: 'yearly'
                    }],
                    listeners: {
                        change: function (buttons, value) {
                            panel.updateStatisticsView();
                        }
                    }
                }, {
                    xtype: 'button',
                    overflowText: 'Settings',
                    iconCls: 'x-btn-icon-settings',
                    margin: '15 0 0 12',
                    height: 30,

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
                            items: [{
                                xtype: 'checkbox',
                                boxLabel: 'Memory usage',
                                name: 'mem',
                                checked: this.setCheckboxStatus('mem')
                            }, {
                                xtype: 'checkbox',
                                boxLabel: 'CPU utilization',
                                name: 'cpu',
                                checked: this.setCheckboxStatus('cpu')
                            }, {
                                xtype: 'checkbox',
                                boxLabel: 'Load averages',
                                name: 'la',
                                checked: this.setCheckboxStatus('la')
                            }, {
                                xtype: 'checkbox',
                                boxLabel: 'Network usage',
                                name: 'net',
                                checked: this.setCheckboxStatus('net')
                            }, {
                                xtype: 'checkbox',
                                boxLabel: 'Servers count',
                                name: 'snum',
                                checked: this.setCheckboxStatus('snum')
                            }, {
                                xtype: 'checkbox',
                                boxLabel: 'Disk I/O',
                                name: 'io',
                                checked: this.setCheckboxStatus('io')
                            }]
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
                }]
            }, {
                xtype: 'loadstatistics',
                itemId: 'monitoringStatistics',
                getMaskTarget: function() {
                    return panel.child('container').getEl().next();
                }
            }]
        }],

        dockedItems: [{
            dock: 'left',
            xtype: 'panel',
            bodyCls: 'scalr-ui-monitoring-left-panel',
            //layout: 'vbox',
            height: '100%',
            width: 350,
            items: [{
                xtype: 'container',
                itemId: 'treeFilterFieldAndCompareModeSwitcherContainer',
                layout: 'hbox',
                width: '100%',
                padding: 15,
                items: [{
                    xtype: 'filterfield',
                    store: store,
                    filterFields: ['text'],
                    flex: 1,
                    listeners: {
                        afterfilter: function() {
                            this.up().next('#filterMessage')[this.getStore().count() ? 'hide' : 'show']();
                        }
                    }
                }, {
                    xtype: 'button',
                    enableToggle: true,
                    text: 'Compare Mode',
                    margin: '0 0 0 18',
                    flex: 1,
                    toggleHandler: function (button, toggle) {
                        var treePanel = panel.down('#farmsMonitoringTreePanel');
                        treePanel.setCompareMode(toggle);

                        var selectionModel = treePanel.getSelectionModel();

                        if (toggle) {
                            var selectedNode = selectionModel.getLastSelected() || treePanel.monitoredItem,
                                compareModeParams = treePanel.prepareDataForMonitoring([selectedNode]);

                            selectedNode.set('checked', true);
                            selectionModel.deselect(selectedNode);
                            panel.updateStatisticsView(compareModeParams);
                        } else {
                            var checkedNodes = treePanel.getChecked(),
                                checkedNode;

                            if (checkedNodes.length === 1) {
                                checkedNode = checkedNodes[0];
                            } else {
                                checkedNode = treePanel.monitoredItem;
                            }

                            var compareModeOffParams = treePanel.prepareDataForMonitoring([checkedNode]);
                            selectionModel.select(checkedNode);
                            panel.updateStatisticsView(compareModeOffParams);
                        }

                        treePanel.setNodesStyle();
                        treePanel.uncheckNodes();
                        treePanel.showCheckboxes();
                    }
                }]
            }, {
                xtype: 'displayfield',
                itemId: 'filterMessage',
                value: 'No matches found',
                fieldCls: 'scalr-ui-monitoring-filter-no-matches-found-message',
                padding: '50 0 0 110',
                hidden: true

            }, {
                xtype: 'treepanel',
                itemId: 'farmsMonitoringTreePanel',
                cls: 'scalr-ui-monitoring-tree-panel',

                selType: 'monitoring',
                store: store,

                rowLines: true,
                rootVisible: false,
                hideHeaders: true,
                compareMode: false,
                width: '100%',

                viewConfig: {
                    preserveScrollOnRefresh: true,
                    selectedRecordFocusCls: null
                },

                columns : [{
                    xtype : 'treecolumn',
                    dataIndex: 'text',
                    flex: 1,
                    renderer : function (value, metaData, record) {
                        var params = record.get('params'),
                            isScalarized = record.get('isScalarized'),
                            warning = '';

                        if (params.index) {
                            return value;
                        }

                        if (isScalarized !== undefined && isScalarized == 0) {
                            warning = '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-warning" style="vertical-align:middle;margin:0 2px 0 12px" data-qtip="Statistics is not available for agentless servers" />&nbsp;';
                        }

                        var farmRoleId = params.farmRoleId;

                        metaData.tdCls = farmRoleId
                            ? 'scalr-ui-monitoring-tree-node-farmrole'
                            : 'scalr-ui-monitoring-tree-node-farm';

                        return farmRoleId
                            ? '<span>' + warning + 'role: </span><span>' + value + '</span>'
                            : '<span>Farm: </span><span>' + value + '</span>';
                    }
                }],

                setCompareMode: function (compareMode) {
                    var me = this;

                    me.compareMode = compareMode;

                    if (compareMode) {
                        me.addCls('scalr-ui-monitoring-compare-mode');
                        return me;
                    }

                    me.removeCls('scalr-ui-monitoring-compare-mode');
                },

                showCheckboxes: function () {
                    var me = this,
                        checkboxes = me.el.query('.x-tree-checkbox');

                    Ext.each(checkboxes, function (checkbox, i) {
                        var record = me.view.getRecord(Ext.get(checkbox).up('.x-grid-row')),
                            isScalarized = record ? record.get('isScalarized') : undefined;
                        //console.log(i, me.compareMode, checkbox);
                        Ext.get(checkbox).setVisible(me.compareMode && (isScalarized === undefined || isScalarized == 1));
                    });
                },

                setNodesStyle: function () {
                    var me = this,
                        compareMode = me.compareMode,
                        treeView = me.getView();

                    Ext.Array.each(me.getChecked(), function (record) {
                        var node = Ext.get(treeView.getNode(record));

                        if (compareMode && node) {
                            node.addCls('scalr-ui-monitoring-checked-node');
                        } else if (!compareMode && node) {
                            node.removeCls('scalr-ui-monitoring-checked-node');
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
                            title = parentsParams.value + ' &rarr; ' + title;
                            researchableNode = researchableNode.parentNode;
                        }
                        title += currentNodesParams.value;

                        var isInstance = currentNode.data.depth === 3;

                        params.push({params: currentNode.raw.params, title: title, isInstance: isInstance});
                    });
                    me.cachedParams = params;

                    return params;
                },

                setNodesTextStyle: function (farmNodes) {
                    return;
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
                        //me.hideTreeIcons();
                        me.setNodesStyle();
                        /*
                        me.setNodesTextStyle(
                            me.getRootNode().childNodes
                        );
                        */
                    },
                    beforeselect: function(view, record) {
                        var isScalarized = record.get('isScalarized');
                        if (isScalarized !== undefined && isScalarized == 0) {
                            return false;
                        }
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

                        me.setNodesStyle();

                        var checkedNodes = me.getChecked();
                        var checkNodesNumber = checkedNodes.length;
                        var params = me.prepareDataForMonitoring(checkedNodes);

                        if (checkNodesNumber) {
                            me.getView().saveScrollState();

                            panel.updateStatisticsView(params, checkNodesNumber);
                        }
                    }
                }
            }]
        }],

        setTreePanelHeight: function () {
            var me = this;

            me.down('#farmsMonitoringTreePanel').maxHeight = me.getHeight()
                - me.down('#treeFilterFieldAndCompareModeSwitcherContainer').getHeight();
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
            boxready: function (me) {
                //me.down('#monitoringStatistics').setLoading(me.getEl());
            },
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
            items: [{
                xtype: 'checkbox',
                boxLabel: 'Memory usage',
                name: 'mem',
                checked: this.setCheckboxStatus('mem')
            }, {
                xtype: 'checkbox',
                boxLabel: 'CPU utilization',
                name: 'cpu',
                checked: this.setCheckboxStatus('cpu')
            }, {
                xtype: 'checkbox',
                boxLabel: 'Load averages',
                name: 'la',
                checked: this.setCheckboxStatus('la')
            }, {
                xtype: 'checkbox',
                boxLabel: 'Network usage',
                name: 'net',
                checked: this.setCheckboxStatus('net')
            }, {
                xtype: 'checkbox',
                boxLabel: 'Servers count',
                name: 'snum',
                checked: this.setCheckboxStatus('snum')
            }, {
                xtype: 'checkbox',
                boxLabel: 'Disk I/O',
                name: 'io',
                checked: this.setCheckboxStatus('io')
            }]
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
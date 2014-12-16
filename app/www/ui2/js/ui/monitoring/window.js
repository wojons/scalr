Ext.define('Scalr.ui.monitoring.statistics', {
    extend: 'Ext.Component',
    alias: 'widget.monitoring.statistics',

    autoScroll: true,

    cacheLifetime: 60000,
    autoRefreshTime: 60000,

    initComponent: function () {
        var me = this;

        me.callParent(arguments);

        me.data = [];
        me.dataForAutorefresh = {};
        me.cachedStatistics = {creationTime: {}};

        me.monitoringRefreshTask = Ext.util.TaskManager.newTask({
            run: me.refreshStatistics,
            scope: me,
            interval: me.autoRefreshTime
        });
    },

    metricsTitles: {
        mem: 'Memory usage',
        cpu: 'CPU utilization',
        la: 'Load averages',
        net: 'Network usage',
        snum: 'Servers count',
        io: 'Disk I/O'
    },

    tpl: [
        '<div class="scalr-ui-monitoring-loading-message">',
        '<div>Loading...</div>',
        '<div class="x-panel-confirm-loading"></div>',
        '</div>',

        '<div class="scalr-ui-monitoring-main-container">',

        '<tpl if="this.compareMode">',
        '<tpl for=".">',
        '<div class="scalr-ui-monitoring-container-for-statistics" data-index="{itemIndex}">',
        '<div class="scalr-ui-monitoring-title">{title}</div>',
        '<tpl for="statistics">',
        '<tpl if="path">',
        '<div class="scalr-ui-monitoring-statistics-container">',
        '<div class="scalr-ui-monitoring-image-container scalr-ui-monitoring-refresh-this" data-metric="{metric}" data-disk="{disk}" data-graph="{graph}">',
        '<img src="{path}" alt="{watchername}">',
        '</div>',
        '<div class="scalr-ui-monitoring-add-to-dashboard-tool-container">',
        '<div class="scalr-ui-monitoring-add-to-dashboard-tool" data-farmid="{farmId}" data-roleid="{farmRoleId}" data-index="{index}" data-title="{title}" data-metric="{metric}" data-hash="{hash}" data-disk="{disk}" data-graph="{graph}" title="Add to dashboard"></div>',
        '</div>',

        '<tpl if="disk">',
        '<div class="scalr-ui-monitoring-disk-name-container scalr-ui-monitoring-{disk}" data-disk="{disk}">',
        '<div class="scalr-ui-monitoring-disk-name">{disk}</div>',
        '</div>',
        '</tpl>',

        '</div>',
        '<tpl else>',
        '<div class="scalr-ui-monitoring-statistics-container">',
        '<div class="scalr-ui-monitoring-image-container scalr-ui-monitoring-refresh-this" data-metric="{metric}" data-disk="{disk}" data-graph="{graph}">{message}</div>',
        '<div class="scalr-ui-monitoring-add-to-dashboard-tool-container">',
        '<div class="scalr-ui-monitoring-add-to-dashboard-tool" data-farmid="{farmId}" data-roleid="{farmRoleId}" data-index="{index}" data-title="{title}" data-metric="{metric}" data-hash="{hash}" data-disk="{disk}" data-graph="{graph}" title="Add to dashboard"></div>',
        '</div>',
        '</div>',
        '</tpl>',
        '</tpl>',
        '</div>',
        '</tpl>',

        '<tpl else>',
        '<tpl for=".">',
        '<tpl if="path">',
        '<div class="scalr-ui-monitoring-statistics-container-compare-mode-off">',
        '<div class="scalr-ui-monitoring-image-container-compare-mode-off scalr-ui-monitoring-refresh-this" data-metric="{metric}" data-disk="{disk}" data-graph="{graph}">',
        '<img class="scalr-ui-monitoring-statistics-image-compare-mode-off" src="{path}">',
        '</div>',
        '<div class="scalr-ui-monitoring-add-to-dashboard-tool-container">',
        '<div class="scalr-ui-monitoring-add-to-dashboard-tool" data-farmid="{farmId}" data-roleid="{farmRoleId}" data-index="{index}" data-title="{title}" data-metric="{metric}" data-hash="{hash}" data-disk="{disk}" data-graph="{graph}" title="Add to dashboard"></div>',
        '</div>',

        '<tpl if="disk">',
        '<div class="scalr-ui-monitoring-disk-name-container scalr-ui-monitoring-{disk}" data-disk="{disk}">',
        '<div class="scalr-ui-monitoring-disk-name">{disk}</div>',
        '</div>',
        '</tpl>',

        '</div>',
        '<tpl else>',
        '<div class="scalr-ui-monitoring-statistics-container-compare-mode-off">',
        '<div class="scalr-ui-monitoring-image-container-compare-mode-off scalr-ui-monitoring-refresh-this" data-metric="{metric}" data-disk="{disk}" data-graph="{graph}"><div class="scalr-ui-monitoring-message">{message}</div></div>',
        '<div class="scalr-ui-monitoring-add-to-dashboard-tool-container">',
        '<div class="scalr-ui-monitoring-add-to-dashboard-tool" data-farmid="{farmId}" data-roleid="{farmRoleId}" data-index="{index}" data-title="{title}" data-metric="{metric}" data-hash="{hash}" data-disk="{disk}" data-graph="{graph}" title="Add to dashboard"></div>',
        '</div>',
        '</div>',
        '</tpl>',
        '</tpl>',
        '</tpl>',
        '</div>'
    ],

    updateTpl: function (paramsForStatistics, statisticsType, compareMode, checkedNodesCount) {
        var me = this;
        var currentUpdateTime = new Date().getTime();
        var statisticsNumber = paramsForStatistics.length;
        var loadedStatisticsCounter = 0;

        me.lastUpdateTime = currentUpdateTime;
        me.showLoadingMessage();
        me.data = [];
        me.cachedStatisticsLinksForRefreshing = {};
        me.statisticsType = statisticsType;
        me.compareMode = compareMode;

        me.monitoringRefreshTask.stop();

        if (checkedNodesCount) {
            me.checkedNodesCount = checkedNodesCount;
        }

        var updateTplIfAllStatisticsObtained = function () {
            if (++loadedStatisticsCounter === statisticsNumber) {
                me.tpl.compareMode = me.compareMode;
                me.update(me.data);
                me.hideScrolls(false);
                me.addTools();
                me.doDiskLabelsHover();

                if (compareMode && checkedNodesCount < me.checkedNodesCount) {
                    me.showLoadingMessage();
                }

                me.setStatisticsWidth();

                me.monitoringRefreshTask.restart();

                me.up('panel').down('treepanel').getView().restoreScrollState();
            }
        };

        Ext.each(paramsForStatistics, function (currentItemsParams, itemIndex) {
            var getCachedStatisticIndex = function (itemParams) {
                var index = '';
                for (var key in itemParams) {
                    if (itemParams.hasOwnProperty(key) && key !== 'metrics') {
                        index = index.concat(itemParams[key]);
                    }
                }
                return index;
            };

            var cacheCheck = function (metrics) {
                var metricsNumber = metrics.split(',').length;
                return cachedStatistic && cachedStatistic.length >= metricsNumber && me.cacheLifetime > currentTime - cachedStatisticCreationTime;
            };

            var params = currentItemsParams.params;
            var metricException = currentItemsParams.isInstance ? 'snum' : 'io';

            params.period = me.statisticsType;
            params.metrics = me.watchernames.join(',').replace(',' + metricException, '');

            var title = currentItemsParams.title;
            var itemIndexInCache = getCachedStatisticIndex(params);
            var cachedStatistic = me.cachedStatistics[itemIndexInCache];
            var cachedStatisticCreationTime = me.cachedStatistics.creationTime[itemIndexInCache];
            var currentTime = new Date().getTime();

            if (cacheCheck(params.metrics)) {
                if (compareMode) {
                    me.data[itemIndex] = {title: title, statistics: cachedStatistic, itemIndex: itemIndexInCache};
                } else {
                    me.data = cachedStatistic;
                }
                updateTplIfAllStatisticsObtained();
            } else {
                if (compareMode) {
                    me.data[itemIndex] = {title: title, statistics: []};
                }
                me.cachedStatistics[itemIndexInCache] = [];
                me.getStatistics(params, title, currentUpdateTime, itemIndex, itemIndexInCache, updateTplIfAllStatisticsObtained);
            }
        });
    },

    getStatistics: function (params, title, currentUpdateTime, itemIndex, itemIndexInCache, callback) {
        var me = this;
        Scalr.Request({
            method: 'GET',
            url: me.hostUrl + '/load_statistics',
            params: params,
            success: function (data) {
                var hash = params.hash;
                var metrics = data['metric'];
                var metricsList = params.metrics.split(',');

                var fillData = function (metrics, metricsList, compareMode, itemIndex) {
                    Ext.each(metricsList, function (metric) {
                        if (metrics.hasOwnProperty(metric)) {
                            if (metrics[metric].success) {
                                var image = metrics[metric].img;
                                if (typeof(image) === 'string') {
                                    if (!compareMode) {
                                        //me.data.push(params);
                                        //me.data[itemIndex].path = image;
                                        //me.data[itemIndex].metric = metric;
                                        //me.data[itemIndex].title = title
                                        me.data.push({path: image, metric: metric, farmId: params.farmId, farmRoleId: params.farmRoleId, index: params.index, title: title, hash: hash});
                                    } else {
                                        me.data[itemIndex].statistics.push({path: image, metric: metric, farmId: params.farmId, farmRoleId: params.farmRoleId, index: params.index, title: title, hash: hash});
                                        me.data[itemIndex].itemIndex = itemIndexInCache;
                                    }
                                } else {
                                    for (var disk in image) {
                                        for (var graph in image[disk]) {
                                            if (!compareMode) {
                                                me.data.push({path: image[disk][graph], metric: metric, disk: disk, graph: graph, farmId: params.farmId, farmRoleId: params.farmRoleId, index: params.index, title: title, hash: hash});
                                            } else {
                                                me.data[itemIndex].statistics.push({path: image[disk][graph], metric: metric, disk: disk, graph: graph, farmId: params.farmId, farmRoleId: params.farmRoleId, index: params.index, title: title, hash: hash});
                                                me.data[itemIndex].itemIndex = itemIndexInCache;
                                            }
                                        }
                                    }
                                }
                            } else {
                                var message = metrics[metric].msg;
                                if (!compareMode) {
                                    me.data.push({message: message, metric: metric});
                                } else {
                                    me.data[itemIndex].statistics.push({message: message, metric: metric});
                                    me.data[itemIndex].itemIndex = itemIndexInCache;
                                }
                            }
                        }
                    });
                };

                if (!me.isDestroyed && (currentUpdateTime === me.lastUpdateTime) && me.data) {
                    fillData(metrics, metricsList, me.compareMode, itemIndex);
                    callback();

                    var dataForCaching = me.compareMode ? me.data[itemIndex] : me.data;
                    me.cacheStatistics(dataForCaching, itemIndexInCache);
                }
            },
            failure: function (data) {
                if (!me.isDestroyed && (currentUpdateTime === me.lastUpdateTime) && me.data) {

                    var message = data && data.msg ? data.msg : 'Connection error';

                    if (!me.compareMode) {
                        me.data.push({message: message});
                    } else {
                        me.data[itemIndex].statistics.push({message: message});
                    }

                    callback();
                }
            }
        });

        me.itemIndexInCache = itemIndexInCache;
        me.dataForAutorefresh[itemIndexInCache] = params;
    },

    cacheStatistics: function (statistic, index) {
        var me = this;
        me.cachedStatistics[index] = statistic;
        me.cachedStatistics.creationTime[index] = new Date().getTime();
    },

    addTools: function () {
        var me = this;
        var addToDashboardButtons = me.el.query('.scalr-ui-monitoring-add-to-dashboard-tool');

        Ext.each(addToDashboardButtons, function (button) {
            var farmId = button.getAttribute('data-farmid');
            var farmRoleId = button.getAttribute('data-roleid');
            var index = button.getAttribute('data-index');
            var hash = button.getAttribute('data-hash');
            var metric = button.getAttribute('data-metric');
            var period = me.statisticsType;
            var title = button.getAttribute('data-title');

            var params = {
                farmId: farmId,
                hash: hash,
                metrics: metric,
                period: period
            };

            if (farmRoleId) {
                params.farmRoleId = farmRoleId;
            }

            if (index) {
                params.index = index;
            }

            if (metric === 'io') {
                var disk = button.getAttribute('data-disk');
                var graph = button.getAttribute('data-graph');

                params.disk = disk;
                params.graph = graph;
                title += ' / ' + me.metricsTitles[metric] + ' \u2192 ' + disk + ' (' + period + ')';
            } else {
                title += ' / ' + me.metricsTitles[metric] + ' (' + period + ')';
            }

            params.title = title;

            var dataForRequest = Ext.encode({
                params: params,
                name: 'dashboard.monitoring',
                url: ''
            });

            Ext.get(button).on('click', function () {
                Scalr.Request({
                    processBox: {
                        type: 'action',
                        msg: 'Adding widget to dashboard...'
                    },
                    url: '/dashboard/xUpdatePanel',
                    params: {'widget': dataForRequest},
                    success: function (data) {
                        Scalr.event.fireEvent('update', '/dashboard', data.panel);
                    }
                });
            });
        });
    },

    doDiskLabelsHover: function () {
        var me = this;
        var diskLabels = me.el.query('.scalr-ui-monitoring-disk-name-container');

        Ext.each(diskLabels, function (diskLabel) {
            diskLabel = Ext.get(diskLabel);

            var diskName = diskLabel.getAttribute('data-disk');
            var currentDiskLabels = me.el.query('.scalr-ui-monitoring-' + diskName);

            diskLabel.on('mouseenter', function () {
                Ext.each(currentDiskLabels, function (label) {
                    Ext.get(label).setStyle('opacity', 1);
                });
            });

            diskLabel.on('mouseleave', function () {
                Ext.each(currentDiskLabels, function (label) {
                    Ext.get(label).setStyle('opacity', 0.7);
                });
            });
        });
    },

    setStatisticsWidth: function () {
        var me = this,
            mainContainer = me.el.down('.scalr-ui-monitoring-main-container');
        if (me.compareMode) {
            var containersForStatistics = me.el.query('.scalr-ui-monitoring-container-for-statistics'),
                containersForStatisticsNumber = containersForStatistics.length,
                statisticsWidth = 537,
                statisticsMargin = 13,
                mainContainerWidth = (statisticsWidth + statisticsMargin) * containersForStatisticsNumber + statisticsMargin;

            mainContainer.setWidth(mainContainerWidth);
        }
    },

    refreshStatistics: function () {
        var me = this;
        var refreshCounter = 0;
        var itemContainers = me.el.query('.scalr-ui-monitoring-container-for-statistics');

        me.monitoringRefreshTask.stop();

        if (!itemContainers.length) {
            itemContainers = me.el.query('.scalr-ui-monitoring-main-container');
        }

        var callback = function () {
            if (++refreshCounter === itemContainers.length) {
                me.monitoringRefreshTask.restart();
            }
        };

        Ext.each(itemContainers, function (container) {
            var itemIndexInCache = container.getAttribute('data-index') || me.itemIndexInCache;
            var params = me.dataForAutorefresh[itemIndexInCache];

            Scalr.Request({
                method: 'GET',
                url: me.hostUrl + '/load_statistics',
                params: params,
                success: function (data) {
                    var metrics = data['metric'];

                    if (!me.isDestroyed) {
                        var imageContainers = Ext.get(container).query('.scalr-ui-monitoring-refresh-this');
                        Ext.each(imageContainers, function (imageContainer) {
                            var metric = imageContainer.getAttribute('data-metric');
                            var image = '';

                            if (metrics.hasOwnProperty(metric) && metrics[metric].success) {
                                if (metric !== 'io') {
                                    image = metrics[metric].img;
                                } else {
                                    var disk = imageContainer.getAttribute('data-disk');
                                    var graph = imageContainer.getAttribute('data-graph');
                                    image = metrics[metric].img[disk][graph];
                                }
                            }

                            Ext.get(imageContainer).update();
                            Ext.DomHelper.append(imageContainer, {tag: 'img', src: image});
                            callback();
                        });
                    }
                },
                failure: function () {
                    if (!me.isDestroyed) {
                        callback();
                    }
                }
            });
        });
    },

    setLoadingMessagePosition: function () {
        var me = this;
        var windowHeight = me.getHeight();
        var windowWidth = me.getWidth();

        var loadingMessage = me.el.down('.scalr-ui-monitoring-loading-message'),
            loadingMessageHeight = 110,
            loadingMessageWidth = 313;

        var xPosition = windowWidth / 2 - loadingMessageWidth / 2;
        var yPosition = windowHeight / 2 - loadingMessageHeight / 2;

        loadingMessage.position('absolute', 100, xPosition, yPosition);
    },

    showLoadingMessage: function () {
        var me = this;
        if (me.el) {
            me.hideScrolls(true);
            var loadingMessage = me.el.down('.scalr-ui-monitoring-loading-message'),
                mainContainer = me.el.down('.scalr-ui-monitoring-main-container');
            if (!loadingMessage.isVisible()) {
                mainContainer.setOpacity(0.5);
                me.setLoadingMessagePosition();
                loadingMessage.show();
                me.hideAddToDashboardTools();
            }
        }
    },

    hideAddToDashboardTools: function () {
        var me = this,
            addToDashboardTools = me.el.query('.scalr-ui-monitoring-add-to-dashboard-tool-container');
        Ext.each(addToDashboardTools, function (tool) {
            Ext.get(tool).hide();
        });
    },

    hideScrolls: function (hidden) {
        var me = this,
            style = hidden ? 'hidden' : 'auto';
        me.el.setStyle('overflow', style);
    },

    beforeRender: function () {
        var me = this;

        me.callParent();

        me.hostUrl = me.up('panel').getHostUrl();
    },

    onDestroy: function () {
        var me = this;

        me.callParent();

        me.monitoringRefreshTask.destroy();
    }
});

Ext.define('Scalr.ui.ChartPreview', {
    extend: 'Ext.Component',
    alias: 'widget.chartpreview',

    tpl: [
        '<tpl for=".">',
            '<div class="scalr-ui-chart-preview">',
                '<div class="scalr-ui-chart-preview-title">{title}:</div>',
                '<tpl if="success">',
                    '<img src="{image}" data-metric="{metric}" height="{height}" width="{width}" />',
                '<tpl else>',
                    '<div style="color: #F04A46">{message}</div>',
                '</tpl>',
            '</div>',
        '</tpl>'
    ],

    listeners: {
        boxready: function () {
            var me = this;
            me.el.mask('Loading...');
        }
    },

    statisticsTitles: {
        'mem': 'Memory usage',
        'cpu': 'CPU utilization',
        'la': 'Load averages',
        'net': 'Network usage'
    },

    loadStatistics: function (hostUrl, paramsForRequest, callback, size) {
        var me = this;

        if (!size) {
            size = {height: 80, width: 141};
        }

        if (me.rendered && !me.isDestroyed) {
            //me.el.mask('Loading...');

            me.paramsForRequest = paramsForRequest;

            Scalr.Request({
                method: 'GET',
                url: hostUrl + '/load_statistics',
                params: paramsForRequest,
                success: function (data) {
                    if (me.rendered && !me.isDestroyed) {
                        me.data = [];

                        var metrics = data['metric'];
                        var metricsList = paramsForRequest.metrics.split(',');

                        Ext.each(metricsList, function (metric) {
                            if (metrics.hasOwnProperty(metric)) {
                                var title = me.statisticsTitles[metric];
                                var image = metrics[metric].img;

                                me.data.push({success: true, title: title, image: image, metric: metric, height: size.height, width: size.width});
                            }
                        });

                        me.update(me.data);

                        var images = me.el.query('img');

                        Ext.each(images, function (image) {
                            image = Ext.get(image);

                            image.on('click', function () {
                                var imageSource = this.getAttribute('src');
                                var metric = this.getAttribute('data-metric');
                                var title = me.statisticsTitles[metric];

                                Scalr.utils.Window({
                                    xtype: 'panel',
                                    cls: 'x-panel x-panel-shadow',
                                    title: title,
                                    titleAlign: 'left',
                                    height: 425,
                                    width: 570,
                                    autoScroll: false,
                                    closable: true,
                                    items: [
                                        {
                                            xtype: 'buttongroupfield',
                                            margin: '20 20 0 80',
                                            value: 'daily',
                                            defaults: {
                                                width: 100
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
                                                boxready: function () {
                                                    var panel = this.up('panel');
                                                    var image = panel.down('#chartPreviewMaximizedImage');
                                                    var period = paramsForRequest.period;

                                                    image.cache[period] = data['metric'][metric].img;
                                                },
                                                change: function (field, value) {
                                                    var buttonGroup = this;
                                                    var panel = buttonGroup.up('panel');
                                                    var image = panel.down('#chartPreviewMaximizedImage');

                                                    if (!image.cache[value]) {
                                                        image.el.mask('Loading...');

                                                        me.paramsForRequest.metrics = metric;
                                                        me.paramsForRequest.period = value;

                                                        Scalr.Request({
                                                            method: 'GET',
                                                            url: hostUrl + '/load_statistics',
                                                            params: me.paramsForRequest,
                                                            success: function (data) {
                                                                if (me.rendered && !me.isDestroyed) {
                                                                    var imageSource = data['metric'][metric].img;

                                                                    image.update('<img src="' + imageSource + '" />');
                                                                    image.cache[value] = imageSource;

                                                                }
                                                            }
                                                        });
                                                    } else {
                                                        image.update('<img src="' + image.cache[value] + '" />');
                                                    }
                                                }
                                            }
                                        },
                                        {
                                            xtype: 'component',
                                            itemId: 'chartPreviewMaximizedImage',
                                            margin: 20,
                                            html: '<img src="' + imageSource + '" />',
                                            cache: {}
                                        }
                                    ]
                                });
                            });
                        });

                        if (callback) {
                            callback();
                        }
                    }
                },
                failure: function (data) {
                    var metricsList = paramsForRequest.metrics.split(',');

                    me.data = [];

                    Ext.each(metricsList, function (metric) {
                        var title = me.statisticsTitles[metric];
                        var message = data && data.msg ? data.msg : 'Connection error';

                        me.data.push({success: false, title: title, message: message, metric: metric, height: size.height, width: size.width});
                    });

                    me.update(me.data);

                    if (callback) {
                        callback();
                    }
                }

            });
        }
    }

});
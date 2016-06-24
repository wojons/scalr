Scalr.regPage('Scalr.ui.admin.analytics.pricing.view', function (loadParams, moduleParams) {

    var platformsWithoutEndpoints = [ 'ec2', 'gce' ];

    var hasEndpoints = function (platform) {
        return platformsWithoutEndpoints.indexOf(platform) === -1;
    };

    var isEditable = Scalr.user.type === 'ScalrAdmin';

    var endpointsStore = Ext.create('store.store', {
        fields: ['envId', 'url'],
        proxy: {
            type: 'ajax',
            url: '/admin/analytics/pricing/xGetPlatformEndpoints',
            reader: {
                type: 'json',
                rootProperty: 'data'
            }
        },
        autoLoad: false,
        listeners: {
            beforeload: function (me) {
                me.processBox = Scalr.utils.CreateProcessBox({
                    type: 'action',
                    msg: 'Gathering records...'
                });
            },
            load: function (me, records, successful) {
                me.processBox.destroy();

                var success = successful && records.length;

                if (success) {
                    pricingPanel.setEndpoint(me.getRecord()).
                        cacheEndpoints(records);
                }

                pricingPanel.setDescription(success).toggleControls(success);
            },
            clear: function () {
                pricingPanel.resetEndpoints();
            }
        },

        getRecord: function () {
            var me = this;

            var loadUrl = loadParams.url;

            if (loadUrl) {
                Ext.Object.clear(loadParams);
                var record = me.findRecord('url', loadUrl);

                if (record) {
                    return record;
                }

                pricingPanel.showEnvironmentWarning(loadUrl);
            }

            return me.getAt(0);
        }
    });

    var locationsStore = Ext.create('store.store', {
        fields: ['cloudLocation', 'url', 'denyOverride', 'prices'],
        proxy: {
            type: 'ajax',
            url: '/admin/analytics/pricing/xGetPlatformLocations',
            timeout: 60000,
            reader: {
                type: 'json',
                rootProperty: 'data'
            }
        },
        autoLoad: false,
        sorters: [{
            property: 'cloudLocation'
        }],
        listeners: {
            beforeload: function (me) {
                me.processBox = Scalr.utils.CreateProcessBox({
                    type: 'action',
                    msg: 'Gathering records...'
                });
            },
            load: function (me, records, successful) {
                me.processBox.destroy();

                pricingPanel.setLocation(me.getAt(0));

                var success = successful && records.length;

                if (success) {
                    pricingPanel.cacheLocations(records);
                }

                if (!hasEndpoints(pricingPanel.platform)) {
                    pricingPanel.setDescription(success).toggleControls(success);
                }
            },
            clear: function () {
                pricingPanel.resetLocations();
            }
        }
    });

    var pricesStore = Ext.create('store.store', {
        fields: ['type', 'name', 'priceLinux', 'priceWindows'],
        proxy: {
            type: 'ajax',
            url: '/admin/analytics/pricing/xGetPlatformInstanceTypes',
            timeout: 60000,
            reader: {
                type: 'json',
                rootProperty: 'data.prices'
            }
        },
        autoLoad: false,
        sortOnLoad: true,
        sorters: { property: 'name' },
        listeners: {
            beforeload: function (me) {
                me.processBox = Scalr.utils.CreateProcessBox({
                    type: 'action',
                    msg: 'Gathering records...'
                });
            },
            load: function (me, records, successful, operation) {
                me.processBox.destroy();

                if (successful && records.length) {
                    me.formatPrices(records);
                    pricingPanel.toggleGrid(true).cachePrices(records);
                }
            },
            clear: function () {
                pricingPanel.toggleGrid(false).
                    toggleSaveButton(true).toggleCancelButton(true);
            }
        },

        isPricesChanged: function () {
            var me = this;
            return me.getModifiedRecords().length && !pricingPanel.down('[name=save]').isDisabled();
        },

        cancelChanges: function () {
            var me = this;

            Ext.Array.each(me.data.items, function (record) {
                record.reject();
            });

            return me;
        },

        getRecordsData: function () {
            var me = this;

            var data = [];

            Ext.Array.each(me.data.items, function (record) {
                data.push(record.data);
            });

            return data;
        },

        getMaxNumberLength: function (prices) {
            var length = 0;

            var getLength = function (number) {
                return Ext.isDefined(number)
                    ? number.toString().length
                    : null;
            };

            Ext.Array.each(prices, function (price) {
                var linux = getLength(price.get('priceLinux'));
                var windows = getLength(price.get('priceWindows'));

                length = linux > length ? linux : length;
                length = windows > length ? windows : length;
            });

            return length;
        },

        formatPrice: function (price, maxLength) {
            price = Ext.Number.from(price, 0).toString();

            for (var i = maxLength - price.length; i > 0; i--) {
                if (price.indexOf('.') === -1 && i > 1) {
                    price = price + '.';
                } else if (price.indexOf('.') !== -1) {
                    price = price + '0';
                }
            }

            return price;
        },

        formatPrices: function (prices) {
            var me = this;
            var maxLength = me.getMaxNumberLength(prices);

            Ext.Array.each(prices, function (price) {
                price.set('priceLinux',
                    me.formatPrice(price.get('priceLinux'), maxLength)
                );

                price.set('priceWindows',
                    me.formatPrice(price.get('priceWindows'), maxLength)
                );
            });

            me.commitChanges();

            return me;
        }
    });

    var pricingPanel = Ext.create('Ext.panel.Panel', {

        name: 'pricingPanel',
        cls: 'x-panel-column-left x-panel-column-left-with-tabs',
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Cost analytics',
            menuSubTitle: 'Pricing',
            menuHref: '#/admin/analytics/dashboard',
            menuParentStateId: 'panel-admin-analytics',
            leftMenu: {
                menuId: 'analytics',
                itemId: 'pricing'
            },
            beforeClose: function (callback) {
                var me = this;

                if (pricesStore.isPricesChanged()) {
                    pricingPanel.savePrices(
                        { platform: me.platform },
                        callback
                    );
                    return true;
                }

                return false;
            }
        },

        layout: {
            type: 'vbox',
            align: 'stretch'
        },

        cache: {
            endpoints: {},
            locations: {},
            prices: {}
        },

        wasReturnedOnPage: false,

        getPlatformNames: function (platforms) {
            var names = [];

            Ext.Array.each(platforms, function (platform) {
                names.push(Scalr.utils.getPlatformName(platform));
            });

            return names;
        },

        clearEndpoints: function () {
            var me = this;

            if (endpointsStore.getCount()) {
                endpointsStore.removeAll();
            }

            return me;
        },

        clearLocations: function () {
            var me = this;

            if (locationsStore.getCount()) {
                locationsStore.removeAll();
            }

            return me;
        },

        clearPrices: function () {
            var me = this;

            if (pricesStore.getCount()) {
                pricesStore.removeAll();
            }

            return me;
        },

        getUrl: function () {
            var me = this;
            return hasEndpoints(me.platform) ? (me.down('[name=endpoints]').getRawValue() || '') : '';
        },

        getEnvId: function () {
            var me = this;
            return hasEndpoints(me.platform) ? (me.down('[name=endpoints]').getValue() || 0) : 0;
        },

        getLocation: function () {
            var me = this;
            return me.down('[name=locations]').getValue();
        },

        getDate: function (date) {
            var me = this;
            return Ext.Date.format(date || me.down('[name=date]').getValue(), 'Y-m-d');
        },

        cacheEndpoints: function (data) {
            var me = this;

            me.cache.endpoints[me.platform] = data;

            return me;
        },

        cacheLocations: function (data) {
            var me = this;

            var platform = me.platform;

            if (!me.cache.locations[platform]) {
                me.cache.locations[platform] = {};
            }

            me.cache.locations[platform][me.getEnvId()] = data;

            return me;
        },

        cachePrices: function (data) {
            data = data || pricesStore.data.items;

            var me = this;

            var platform = me.platform;
            var envId = me.getEnvId();
            var location = me.getLocation();
            var date = me.getDate();

            if (!me.cache.prices[platform]) {
                me.cache.prices[platform] = {};
            }

            if (!me.cache.prices[platform][envId]) {
                me.cache.prices[platform][envId] = {};
            }

            if (!me.cache.prices[platform][envId][location]) {
                me.cache.prices[platform][envId][location] = {};
            }

            me.cache.prices[platform][envId][location][date] = data;

            return me;
        },

        isEndpointsCached: function (platform) {
            var me = this;
            return me.cache.endpoints[platform || me.platform];
        },

        isLocationsCached: function (envId) {
            var me = this;
            var platform = me.cache.locations[me.platform];

            return platform && platform[envId || me.getEnvId()];
        },

        isPricesCached: function (location) {
            var me = this;

            var platformPrices = me.cache.prices[me.platform];

            if (!platformPrices) {
                return false;
            }

            var environmentPrices = platformPrices[me.getEnvId()];

            if (!environmentPrices) {
                return false;
            }

            var locationPrices = environmentPrices[location || me.getLocation()];

            if (!locationPrices) {
                return false;
            }

            return locationPrices[me.getDate()];
        },

        applyCachedEndpoints: function (platform) {
            var me = this;

            endpointsStore.loadData(me.cache.endpoints[platform || me.platform]);

            me.setEndpoint(endpointsStore.getAt(0)).
                setDescription(true).toggleControls(true);

            return me;
        },

        applyCachedLocations: function (envId) {
            var me = this;
            var platform = me.platform;

            locationsStore.loadData(me.cache.locations[platform][envId || me.getEnvId()]);

            me.setLocation(locationsStore.getAt(0));

            if (!hasEndpoints(platform)) {
                me.setDescription(true).toggleControls(true);
            }

            return me;
        },

        applyCachedPrices: function (location) {
            var me = this;

            pricesStore.loadData(
                me.cache.prices[me.platform][me.getEnvId()][location || me.getLocation()][me.getDate()]
            );

            me.toggleGrid(true);

            return me;
        },

        clearPricesCache: function () {
            var me = this;

            me.cache.prices[me.platform][me.getEnvId()][me.getLocation()][me.getDate()] = null;

            return me;
        },

        loadEndpoints: function (platform) {
            var me = this;

            me.clearEndpoints().clearLocations().clearPrices();

            Ext.apply(endpointsStore.proxy.extraParams, {
                platform: platform || me.platform
            });

            if (me.isEndpointsCached(platform)) {
                me.applyCachedEndpoints(platform);
                return me;
            }

            endpointsStore.load();

            return me;
        },

        loadLocations: function (envId, url) {
            var me = pricingPanel;

            me.clearLocations().clearPrices();

            Ext.apply(locationsStore.proxy.extraParams, {
                platform: me.platform,
                envId: envId || me.getEnvId(),
                url: url || me.getUrl()
            });

            if (me.isLocationsCached(envId)) {
                me.applyCachedLocations(envId);
                return me;
            }

            locationsStore.load();

            return me;
        },

        loadWithoutEndpoints: function () {
            var me = this;

            me.loadLocations(0, '').
                down('[name=endpoints]').
                setRawValue(Scalr.utils.getPlatformName(me.platform));

            return me;
        },

        loadPrices: function (location) {
            var me = pricingPanel;

            me.clearPrices();

            Ext.apply(pricesStore.proxy.extraParams, {
                platform: me.platform,
                envId: me.getEnvId(),
                cloudLocation: location || me.getLocation(),
                effectiveDate: me.getDate()
            });

            if (me.isPricesCached(location)) {
                me.applyCachedPrices(location);
                return me;
            }

            pricesStore.load();

            return me;
        },

        resetEndpoints: function () {
            var me = this;

            me.down('[name=endpoints]').disable().clearValue();

            return me;
        },

        toggleHistoryButton: function (isDisabled) {
            var me = this;

            var button = me.down('[name=pricingHistory]');
            button.setDisabled(isDisabled !== undefined ? isDisabled : !button.isDisabled());

            return me;
        },

        resetLocations: function () {
            var me = this;

            me.down('[name=locations]').disable().clearValue();
            me.down('[name=date]').disable();//.setValue(new Date());
            me.toggleHistoryButton(true);

            return me;
        },

        setEndpoint: function (record) {
            var me = this;

            me.down('[name=endpoints]').enable().setValue(record);

            return me;
        },

        setLocation: function (record) {
            var me = this;

            var locationField = me.down('[name=locations]');

            if (record) {
                locationField.enable().setValue(record);
                me.down('[name=date]').enable();
                me.toggleHistoryButton(false);

                return me;
            }

            locationField.setValue('Unable to load locations');

            return me;
        },

        setDescription: function (success) {
            var me = this;

            var platform = Scalr.utils.getPlatformName(me.platform, false);

            me.el.down('.scalr-analytics-pricing-header-description').update(
                !success ? '<span style="color: #F04A46;">There are no configured environments using ' + platform + ' cloud.</span>' :
                'Define the price for instances in your ' + platform + ' cloud.'
            );

            return me;
        },

        toggleControls: function (isVisible) {
            var me = this;

            var controls = me.down('[name=pricingControls]');
            controls.setVisible(isVisible !== undefined ? isVisible : !controls.isVisible());

            return me;
        },

        toggleGrid: function (isVisible) {
            var me = this;

            var grid = me.down('[name=pricingGrid]');
            grid.setVisible(isVisible !== undefined ? isVisible : !grid.isVisible());

            return me;
        },

        toggleSaveButton: function (isDisabled) {
            var me = this;

            var button = me.down('[name=save]');
            button.setDisabled(isDisabled !== undefined ? isDisabled : !button.isDisabled());

            return me;
        },

        toggleCancelButton: function (isDisabled) {
            var me = this;

            var button = me.down('[name=cancel]');
            button.setDisabled(isDisabled !== undefined ? isDisabled : !button.isDisabled());

            return me;
        },

        toggleDeleteButton: function (isDisabled) {
            var me = this;

            var button = me.down('[name=delete]');
            button.setDisabled(isDisabled !== undefined ? isDisabled : !button.isDisabled());

            return me;
        },

        toggleAutomaticUpdate: function (isVisible) {
            var me = this;

            var checkbox = me.down('[name=automaticUpdate]');
            checkbox.setVisible(isVisible !== undefined ? isVisible : !checkbox.isVisible());

            return me;
        },

        savePrices: function (params, callback, withoutConfirm) {
            var me = this;

            var doCallback = function () {
                if (Ext.isFunction(callback)) {
                    callback();
                }
            };

            Scalr.Request({
                url: '/admin/analytics/pricing/xSavePrice',
                confirmBox: withoutConfirm ? null : {
                    type: 'save',
                    msg: 'Save ' + pricesStore.getModifiedRecords().length +
                    ' changed price(s) ?',
                    listeners: {
                        boxready: function () {
                            var me = this;
                            me.down('button').next().
                                on('click', function () {
                                    pricesStore.cancelChanges();
                                    doCallback();
                                });
                        }
                    }
                },
                processBox: {
                    type: 'save'
                },
                params: {
                    platform: params.platform || me.platform,
                    url: me.getUrl(),
                    cloudLocation: params.location || me.getLocation(),
                    effectiveDate: params.date || me.getDate(),
                    forbidAutomaticUpdate: !me.getAutomaticUpdateState(),
                    prices: Ext.encode(pricesStore.getRecordsData())
                },
                success: function () {
                    pricesStore.commitChanges();
                    me.toggleHistoryButton(false);
                    doCallback();
                },
                failure: function () {
                    pricesStore.cancelChanges();
                    doCallback();
                }
            });

            return me;
        },

        deletePrices: function () {
            var me = this;

            var date = me.getDate();

            Scalr.Request({
                url: '/admin/analytics/pricing/xDelete',
                confirmBox: {
                    type: 'delete',
                    msg: 'Delete prices for ' + Ext.Date.format(new Date(date), "F j, Y") + ' ?'
                },
                processBox: {
                    type: 'delete'
                },
                params: {
                    platform: me.platform,
                    url: me.getUrl(),
                    cloudLocation: me.getLocation(),
                    effectiveDate: date
                },
                success: function () {
                    me.clearPricesCache().loadPrices();
                }
            });

            return me;
        },

        showHistory: function () {
            Scalr.utils.Window(pricingHistoryPanel);
        },

        showHistoryWarning: function () {
            Scalr.message.Warning('Pricing history is missing');
        },

        getHistoryTitle: function (params) {
            var titleParams = [];

            Ext.Object.each(params, function (key, value) {
                if (value) {
                    titleParams.push(value);
                }
            });

            return 'Pricing history for ' + titleParams.join(' / ');
        },

        loadHistory: function (callback) {
            var me = this;

            var params = {
                platform: me.platform,
                url: me.getUrl(),
                cloudLocation: me.getLocation()
            };

            Scalr.Request({
                url: '/admin/analytics/pricing/xGetPlatformPricingHistory',
                processBox: {
                    type: 'action'
                },
                params: params,
                success: function (data) {
                    var history = data.history;
                    var instances = data.types;

                    if (data.success && instances.length && !Ext.Object.isEmpty(history)) {
                        pricingHistoryPanel.prepareHistoryData(
                            history, instances, me.getHistoryTitle(params)
                        );
                        callback();
                        return;
                    }

                    me.showHistoryWarning();
                    me.toggleHistoryButton(true);
                },
                failure: function () {
                    me.showHistoryWarning();
                    me.toggleHistoryButton(true);
                }
            });

            return me;
        },

        showEnvironmentWarning: function (url) {
            var me = this;

            Scalr.message.Warning('There aren\'t any environment with platform ' + '<span style="font-style: italic;">' +
            me.platform + '</span> and specified url ' + '<span style="font-style: italic;">' +  url + '</span>.');

            return me;
        },

        setPlatform: function (platform) {
            var me = pricingPanel;

            me.platform = platform || me.platform;
            platform = me.platform;

            me.clearEndpoints().clearLocations().clearPrices().
                toggleAutomaticUpdate(platform === 'ec2');

            if (hasEndpoints(platform)) {
                me.loadEndpoints();
                return;
            }

            me.loadWithoutEndpoints();

            return me;
        },

        setDate: function (date) {
            var me = this;

            me.down('[name=date]').setValue(date);

            return me;
        },

        setAutomaticUpdateState: function (value) {
            var me = this;

            me.down('[name=automaticUpdate]').setValue(value);

            return me;
        },

        getAutomaticUpdateState: function () {
            var me = this;
            return me.down('[name=automaticUpdate]').getValue();
        },

        formatPriceValue: function (value) {
            value = Ext.Number.from(value, 0);

            if (value > 999.999999) {
                value = 999.999999;
            } else if (value.toString().length > 10) {
                value = value.toFixed(6);
            }

            return value;
        },

        listeners: {
            selectplatform: function (me, platform) {
                if (!pricesStore.isPricesChanged()) {
                    me.setPlatform(platform);
                    return;
                }

                me.savePrices({ platform: me.platform }, me.setPlatform);
                me.platform = platform;
            },

            boxready: function (me) {
                var platforms = me.down('[name=platforms]');

                platforms.fill(moduleParams.platforms).
                    selectPlatform(loadParams.platform || platforms.child().value);
            },

            activate: function () {
                var me = this;

                if (me.wasReturnedOnPage) {
                    me.setPlatform(me.platform);
                }

                me.wasReturnedOnPage = true;
            }
        },

        dockedItems: [
            {
                xtype: 'container',
                name: 'platforms',
                dock: 'left',
                cls: 'x-docked-tabs x-docked-tabs-light',
                width: 230,
                overflowY: 'auto',
                overflowX: 'hidden',

                defaults: {
                    xtype: 'button',
                    ui: 'tab',
                    toggleGroup: 'rolebuilder-tabs',
                    textAlign: 'left',
                    allowDepress: false,
                    disableMouseDownPressed: true,
                    pressed: false,
                    cls: 'x-btn-tab-no-text-transform',
                    toggleHandler: function (me, state) {
                        if (state) {
                            pricingPanel.fireEvent('selectplatform', pricingPanel, me.value);
                        }
                    }
                },

                fill: function (platforms) {
                    var me = this;

                    Ext.Array.each(platforms, function (platform) {
                        me.add({
                            iconCls: 'x-icon-platform-small x-icon-platform-small-' + platform,
                            text: Scalr.utils.getPlatformName(platform),
                            value: platform
                        });
                    });

                    return me;
                },

                selectPlatform: function (platform) {
                    var me = this;

                    if (moduleParams.platforms.indexOf(platform) === -1) {
                        platform = me.child().value;
                    }

                    me.down('[value=' + platform + ']').toggle(true);

                    return me;
                }
            },
            {
                xtype: 'panel',
                cls: 'x-docked-buttons',
                dock: 'bottom',
                weight: 10,
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                defaults: {
                    xtype: 'button',
                    disabled: true,
                    width: 140
                },
                items: [{
                    name: 'save',
                    text: 'Save',
                    handler: function () {
                        pricingPanel.savePrices({}, null, true).
                            toggleSaveButton(true).toggleCancelButton(true);
                    }
                }, {
                    name: 'cancel',
                    text: 'Cancel',
                    handler: function () {
                        //pricesStore.cancelChanges();
                        pricesStore.reload();
                        pricingPanel.toggleSaveButton(true).toggleCancelButton(true);
                    }
                }, {
                    name: 'delete',
                    text: 'Delete',
                    handler: function () {
                        pricingPanel.deletePrices();
                    }
                }]
            }
        ],

        items: [{
            html: '<div class="x-fieldset-subheader scalr-analytics-pricing-header">' +
            'Pricing for instance type and services</div>' +
            '<div class="x-fieldset-header-description scalr-analytics-pricing-header-description">' +
            'Define the price for instances in your cloud.</div>'
        }, {
            xtype: 'container',
            name: 'pricingControls',
            layout: 'hbox',
            margin: '10 0 0 32',
            items: [{
                xtype: 'combo',
                name: 'endpoints',
                maxWidth: 280,
                flex: 1,
                editable: false,
                store: endpointsStore,
                queryMode: 'local',
                displayField: 'url',
                valueField: 'envId',
                disabled: true,
                listeners: {
                    change: function (me, value) {
                        if (value && pricingPanel.getPlatformNames(platformsWithoutEndpoints).indexOf(value) === -1) {

                            if (!pricesStore.isPricesChanged()) {
                                pricingPanel.loadLocations(value, me.getRawValue());
                                return;
                            }

                            pricingPanel.savePrices({}, pricingPanel.loadLocations);
                        }
                    }
                }
            }, {
                xtype: 'combo',
                name: 'locations',
                margin: '0 0 0 12',
                maxWidth: 250,
                flex: .5,
                editable: false,
                store: locationsStore,
                queryMode: 'local',
                displayField: 'cloudLocation',
                valueField: 'cloudLocation',
                disabled: true,
                listeners: {
                    change: function (me, value, oldValue) {
                        if (value && value !== 'Unable to load locations') {

                            if (!pricesStore.isPricesChanged()) {
                                pricingPanel.loadPrices(value).toggleHistoryButton(false);
                                return;
                            }

                            pricingPanel.savePrices({ location: oldValue }, pricingPanel.loadPrices).
                                toggleHistoryButton(false);
                        }
                    }
                }
            }, {
                xtype: 'datefield',
                name: 'date',
                margin: '0 0 0 12',
                width: 125,
                editable: false,
                minValue: new Date(),
                maxValue: Ext.Date.add(new Date(), Ext.Date.YEAR, 1),
                value: new Date(),
                disabled: true,
                listeners: {
                    change: function (me, value, oldValue) {
                        if (!pricesStore.isPricesChanged()) {
                            pricingPanel.loadPrices().toggleDeleteButton(
                                !(new Date() < value)
                            );
                            return;
                        }

                        pricingPanel.savePrices({ date: pricingPanel.getDate(oldValue) }, pricingPanel.loadPrices).
                            toggleDeleteButton( !(new Date() < value) );
                    }
                }
            }, {
                xtype: 'button',
                name: 'pricingHistory',
                margin: '0 0 0 12',
                width: 130,
                text: 'Pricing history',
                disabled: true,
                handler: function () {
                    pricingPanel.loadHistory(pricingPanel.showHistory);
                }
            }, {
                xtype: 'tbfill',
                flex: .1
            }, {
                xtype: 'checkboxfield',
                name: 'automaticUpdate',
                boxLabel: 'Use official AWS pricing',
                margin: '0 32 0 0',
                value: !moduleParams.forbidAutomaticUpdate.ec2,
                listeners: {
                    change: function () {
                        pricingPanel.toggleSaveButton(false).
                            toggleCancelButton(false);
                    }
                }
            }]
        }, {
            xtype: 'grid',
            name: 'pricingGrid',
            cls: 'x-grid-shadow scalr-ui-analytics-pricing-grid-cell',
            flex: 1,
            padding: '12 32',
            hidden: true,

            disableSelection: true,

            store: pricesStore,

            viewConfig: {
                loadMask: false
            },

            plugins: {
                ptype: 'cellediting',
                clicksToEdit: 1,
                listeners: {
                    beforeedit: function () {
                        return isEditable;
                    },
                    validateedit: function (editor, context) {
                        var value = context.value;

                        return Ext.isNumeric(value) && value !== context.originalValue;
                    },
                    edit: function (editor, context) {
                        context.record.set(context.field, pricingPanel.formatPriceValue(context.value));

                        pricingPanel
                            .setAutomaticUpdateState(false)
                            .toggleSaveButton(false)
                            .toggleCancelButton(false);
                    }
                }
            },

            columns: [{
                text: 'Instance type',
                dataIndex: 'name',
                flex: 1
            }, {
                text: 'Linux price ($ / hour)',
                flex: 1,
                dataIndex: 'priceLinux',
                cls: 'x-grid-item-focused',
                editor: {
                    xtype: 'textfield',
                    emptyText: '0'
                }
            }, {
                text: 'Windows price ($ / hour)',
                flex: 1,
                dataIndex: 'priceWindows',
                editor: {
                    xtype: 'textfield',
                    emptyText: '0'
                }
            }]
        }]
    });

    var pricingHistoryPanel = {

        xtype: 'panel',
        name: 'pricingHistoryPanel',
        cls: 'x-panel x-panel-shadow scalr-ui-analytics-pricing-grid-cell',
        title: 'Pricing history',
        titleAlign: 'left',
        maxHeight: 600,
        width: 1050,
        padding: '0 0 15 0',
        closable: true,

        layout: {
            type: 'vbox',
            align: 'stretch'
        },

        getInstancesNames: function (instances) {
            var names = [];

            Ext.Array.each(instances, function (instance) {
                names.push(instance.name);
            });

            return names.sort();
        },

        getInstances: function (instances) {
            var me = this;

            var names = [];

            Ext.Array.each(me.getInstancesNames(instances), function (instance) {
                names.push(
                    { html: instance }
                );
            });

            return names;
        },

        getHistoryTemplateModel: function (records) {
            Ext.Array.each(records, function (record) {
                record.priceLinux = '&mdash;';
                record.priceWindows = record.priceLinux;
            });

            return records;
        },

        findRecord: function (model, key, value) {
            var found = null;

            Ext.Array.each(model, function (record) {
                if (record[key] === value) {
                    found = record;
                    return false;
                }
            });

            return found;
        },

        getNumber: function (input) {
            return typeof input !== 'string' ? input : 0;
        },

        getPricesDiff: function (minuend, subtrahend) {
            var me = this;

            minuend = me.getNumber(minuend);
            subtrahend = me.getNumber(subtrahend);
            var diff = Ext.Number.correctFloat(minuend - subtrahend);

            return diff ? Ext.Number.toFixed(diff, 3) : diff;
        },

        getHistoryModels: function (history, instances) {
            // todo: comb this code
            var me = this;

            var templateModel = me.getHistoryTemplateModel(instances);
            var previousModel = null;
            var models = {};

            Ext.Array.each(me.dates, function (date) {
                var model = Ext.clone(templateModel);

                Ext.Array.each(history[date], function (record) {
                    var modelRecord = me.findRecord(model, 'type', record.type);

                    if (modelRecord) {
                        modelRecord.priceLinux = record.priceLinux || modelRecord.priceLinux;
                        modelRecord.priceWindows = record.priceWindows || modelRecord.priceWindows;

                        if (previousModel) {
                            var previousModelRecord = me.findRecord(previousModel, 'type', record.type);
                            modelRecord.diffLinux = me.getPricesDiff(modelRecord.priceLinux, previousModelRecord.priceLinux);
                            modelRecord.diffWindows = me.getPricesDiff(modelRecord.priceWindows, previousModelRecord.priceWindows);
                        }
                    }
                });

                previousModel = Ext.clone(model);
                models[date] = model;
            });

            return models;
        },

        getSortedDates: function (dates) {
            var compareByTime = function (firstDate, secondDate) {
                return new Date(firstDate) - new Date(secondDate);
            };

            return dates.sort(compareByTime);
        },

        getDateIndex: function (date) {
            var me = this;
            return me.dates.indexOf(date);
        },

        getNextDateIndex: function (currentDate) {
            var me = this;

            var index = -1;

            Ext.Array.each(me.dates, function (date, i) {
                if (date > currentDate) {
                    index = i;
                    return false;
                }
            });

            return index;
        },

        getPreviousDateIndex: function (currentDate) {
            var me = this;

            var dates = Ext.Array.clone(me.dates).reverse();
            var index = -1;

            Ext.Array.each(dates, function (date) {
                if (date < currentDate) {
                    index = me.dates.indexOf(date);
                    return false;
                }
            });

            return index;
        },

        getDate: function (index) {
            var me = this;
            return me.dates[index];
        },

        getFirstDate: function () {
            var me = this;
            return me.firstDate;
        },

        getSecondDate: function () {
            var me = this;
            return me.secondDate;
        },

        isNextDateAvailable: function () {
            var me = this;
            return me.getNextDateIndex(me.getSecondDate()) !== -1;
        },

        isPreviousDateAvailable: function () {
            var me = this;
            return me.getPreviousDateIndex(me.getFirstDate()) !== -1;
        },

        toggleControls: function () {
            var me = this;

            me.down('[name=nextDate]').setDisabled(
                !me.isNextDateAvailable()
            );
            me.down('[name=previousDate]').setDisabled(
                !me.isPreviousDateAvailable()
            );

            return me;
        },

        setFirstDate: function (date) {
            var me = this;

            me.firstDate = date;
            me.toggleControls().
                down('[name=firstDate]').setDate(date);

            return me;
        },

        setSecondDate: function (date) {
            var me = this;

            me.secondDate = date;
            me.toggleControls().
                down('[name=secondDate]').setDate(date);

            return me;
        },

        getEffectiveDateIndex: function (date) {
            var me = this;

            var dateIndex = me.getDateIndex(date);
            dateIndex = dateIndex !== -1 ? dateIndex : me.getNextDateIndex(date);
            dateIndex = dateIndex !== -1 ? dateIndex : me.getPreviousDateIndex(date);

            return dateIndex;
        },

        setNextDate: function () {
            var me = this;

            var secondDate = me.getSecondDate();
            var nextDateIndex = me.getNextDateIndex(secondDate);

            me.setFirstDate(secondDate).
                setSecondDate(me.getDate(nextDateIndex));
        },

        setPreviousDate: function () {
            var me = this;

            var firstDate = me.getFirstDate();
            var previousDateIndex = me.getPreviousDateIndex(firstDate);

            me.setSecondDate(firstDate).
                setFirstDate(me.getDate(previousDateIndex));
        },

        setDates: function (date) {
            var me = this;

            var dates = me.dates;
            var datesCount = dates.length;
            var dateIndex = me.getEffectiveDateIndex(date);

            if (datesCount > dateIndex + 1) {
                me.setFirstDate(dates[dateIndex]);
                me.setSecondDate(dates[dateIndex + 1]);
                return;
            }

            me.setFirstDate(dates[dateIndex - 1]);
            me.setSecondDate(dates[dateIndex]);
        },

        prepareHistoryData: function (history, instances, title) {
            var me = this;

            me.title = title;
            me.dates = me.getSortedDates(Ext.Object.getKeys(history));
            me.instances = me.getInstances(instances);
            me.history = me.getHistoryModels(history, instances);

            return me;
        },

        setPricingPanelDate: function (date) {
            var me = this;

            pricingPanel.setDate(date);

            return me;
        },

        listeners: {
            beforerender: function () {
                var me = this;

                me.setDates(pricingPanel.getDate());
                me.down('[name=instancesNames]').add(me.instances);
            }
        },

        items: [
            {
                xtype: 'container',
                name: 'controls',
                layout: {
                    align: 'middle',
                    pack: 'center',
                    type: 'hbox'
                },
                margin: '10 0 0 0',
                items: [{
                    xtype: 'component',
                    margin: '0 0 0 10',
                    listeners: {
                        boxready: function (me) {
                            me.setWidth(
                                me.up('panel').down('[name=instancesNames]').getWidth()
                            );
                        }
                    }
                }, {
                    xtype: 'button',
                    name: 'previousDate',
                    cls: 'x-btn-flag',
                    iconCls: 'x-btn-icon-previous',
                    handler: function (me) {
                        me.up('panel').setPreviousDate();
                    }
                },
                    {
                        xtype: 'button',
                        name: 'nextDate',
                        cls: 'x-btn-flag',
                        iconCls: 'x-btn-icon-next',
                        margin: '0 0 0 6',
                        handler: function (me) {
                            me.up('panel').setNextDate();
                        }
                    }]
            },
            {
                xtype: 'container',
                margin: '15 0 0 0',
                flex: 1,
                layout: 'hbox',
                overflowY: 'auto',
                overflowX: 'hidden',
                items: [{
                    xtype: 'container',
                    name: 'instancesNames',
                    margin: '62 15 0 10',
                    defaults: {
                        height: 37,
                        baseCls: 'scalr-ui-analytics-pricing-history-instances'
                    }
                }, {
                    xtype: 'container',
                    name: 'grids',
                    flex: 1,
                    layout: 'hbox',

                    items: [{
                        xtype: 'pricinghistorygrid',
                        name: 'firstDate',
                        flex: 1
                    }, {
                        xtype: 'pricinghistorygrid',
                        name: 'secondDate',
                        flex: 1
                    }]
                }]
            }]
    };

    return pricingPanel;
});

Ext.define('Scalr.ui.PricingHistoryGrid', {
    extend: 'Ext.container.Container',
    alias: 'widget.pricinghistorygrid',

    margin: '0 10 0 0',
    overflowY: 'auto',
    hideMode: 'display',

    setDate: function (date) {
        var me = this;

        me.down('[name=date]').fireEvent('datechange', date);

        return me;
    },

    items: [{
        name: 'date',

        getDate: function () {
            var me = this;
            return me.date;
        },

        isPriceChangeAvailable: function (date) {
            return new Date() < new Date(date);
        },

        listeners: {
            datechange: function (date) {
                var me = this;

                me.date = date;

                var title = '<span style="font-weight: bold">' +
                    Ext.Date.format(new Date(date), "F j, Y") +
                    '</span>';

                if (me.isPriceChangeAvailable(date)) {
                    title = '<a class="scalr-ui-analytics-pricing-history-date-title" title="Change prices">' +
                    title + '</a>';
                }

                me.update(title);
                me.next().updateHistory(date);
            },

            afterlayout: function (me) {
                var title = me.getEl().down('.scalr-ui-analytics-pricing-history-date-title');

                if (title) {
                    title.on('click', function () {
                        this.fireEvent('click', me);
                    }, me);
                }
            },

            click: function (me) {
                var historyPanel = me.up('[name=pricingHistoryPanel]');
                historyPanel.close();
                historyPanel.setPricingPanelDate(me.getDate());

            }
        }
    }, {
        xtype: 'grid',
        margin: '5 0 0 0',
        cls: 'x-grid-shadow',
        store: {
            fields: ['priceLinux', 'priceWindows', 'diffLinux', 'diffWindows', 'name'],
            sortOnLoad: true,
            sorters: { property: 'name' }
        },
        selModel: {
            locked: true
        },
        columns: [
            { text: 'Linux price ($ / hour)', flex: 1, xtype: 'templatecolumn', dataIndex: 'priceLinux', sortable: false,
                tpl: [
                    '<tpl if="diffLinux">',
                    '{[this.formatPrice(values.priceLinux)]} {[this.formatDiff(values.diffLinux)]}',
                    '<tpl else>',
                    '{[this.formatPrice(values.priceLinux)]}',
                    '</tpl>',
                    {
                        formatPrice: function (price) {
                            var image = '&mdash;';
                            return price !== image ? Ext.Number.from(price, 0).toFixed(3) : image;
                        },
                        formatDiff: function (diff) {
                            var color = diff > 0 ? '#F04A46;' : '#319608;';
                            diff = diff > 0 ? '+' + diff : diff;
                            return '<span style="color:' + color + '">(' + diff + ')</span>';
                        }
                    }
                ]},

            { text: 'Windows price ($ / hour)', flex: 1, xtype: 'templatecolumn', dataIndex: 'priceWindows', sortable: false,
                tpl: [
                    '<tpl if="diffWindows">',
                    '{[this.formatPrice(values.priceWindows)]}  {[this.formatDiff(values.diffWindows)]}',
                    '<tpl else>',
                    '{[this.formatPrice(values.priceWindows)]}',
                    '</tpl>',
                    {
                        formatPrice: function (price) {
                            var image = '&mdash;';
                            return price !== image ? Ext.Number.from(price, 0).toFixed(3) : image;
                        },
                        formatDiff: function (diff) {
                            var color = diff > 0 ? '#F04A46;' : '#319608;';
                            diff = diff > 0 ? '+' + diff : diff;
                            return '<span style="color:' + color + '">(' + diff + ')</span>';
                        }
                    }
                ]}
        ],

        updateHistory: function (date) {
            var me = this;

            if (date) {
                me.getStore().loadData(
                    me.up('[name=pricingHistoryPanel]').history[date]
                );

                return me;
            }

            me.up('container').hide();

            return me;
        }
    }]
});

Scalr.regPage('Scalr.ui.tools.aws.route53.view', function (loadParams, moduleParams) {

    var recordsTooltips = {
        name: 'The domain name for this record set. Each label can be up to 63 characters long, and the total length of the domain name can be up to 255 characters.',
        type: 'The record set type. For weighted and latency record sets, only A, AAAA, CNAME, and TXT are valid types. For alias record sets, only A and AAAA are valid types. Additional help for each type appears below the Value field.',
        alias: 'Specify whether you want this record set to be an alias for an AWS resource. An alias record set is similar in some ways to a CNAME record set; one of the differences is that you can create an alias for the zone apex. Alias record sets are supported only for DNS record types A and AAAA.',
        ttl: 'The resource record cache time to live (TTL), in seconds. This value determines how long a record set is cached by DNS resolvers and by web browsers. The TTL must match for all weighted and latency record sets that have the same name and type.',
        resourceRecord: {
            'A': 'IPv4 address. Enter multiple addresses on separate lines.<p>Example:<br>192.0.2.235<br>198.51.100.234',
            'CNAME': 'The domain name that you want to resolve to instead of the value in the Name field.<p>Example:<br>www.example.com',
            'MX': 'A priority and a domain name that specifies a mail server. Enter multiple values on separate lines.<p>Format:<br>[priority] [mail server host name]<p>Example:<br>10 mailserver.example.com.<br>20 mailserver2.example.com.',
            'AAAA': 'IPv6 address. Enter multiple addresses on separate lines.<p>Example:<br>2001:0db8:85a3:0:0:8a2e:0370:7334<br>fe80:0:0:0:202:b3ff:fe1e:8329',
            'TXT': 'A text record. Enter multiple values on separate lines. Enclose text in quotation marks.<p>Example:<br>"Sample Text Entries"<br>"Enclose entries in quotation marks"',
            'PTR': 'The domain name that you want to return.<p>Example:<br>www.example.com',
            'SRV': 'An SRV record. For information about SRV record format, refer to the applicable documentation. Enter multiple values on separate lines.<p>Format:<br>[priority] [weight] [port] [server host name]<p>Example:<br>1 10 5269 xmpp-server.example.com.<br>2 12 5060 sip-server.example.com.',
            'SPF': 'An SPF record. For information about SPF record format, refer to the applicable documentation. Enter multiple values on separate lines. Enclose values in quotation marks.<p>Example:<br>"v=spf1 ip4:192.168.0.1/16-all"',
            'NS': 'The domain name of a name server. Enter multiple name servers on separate lines.<p>Example:<br>ns1.amazon.com<br>ns2.amazon.org<br>ns3.amazon.net<br>ns4.amazon.co.uk',
            'SOA': 'Start of authority record. Enter all time values in seconds.<p>Format:<br>[authority-domain] [domain-of-zone-admin] [zone-serial-number] [refresh-time] [retry-time] [expire-time] [minimum TTL]<p>Example:<br>ns.example.net. hostmaster.example.com. 1 7200 900 1209600 86400'
        },
        aliasTarget: 'Enter the fully qualified domain name of an Elastic load balancer, an S3 website endpoint, or the domain name of a record set in this hosted zone. To display a list of AWS resources associated with the current account, click in the field. To filter the list, begin entering the name of an AWS resource.',
        evaluateTargetHealth: 'Specify whether you want Route 53 to check the health of the record set (the alias target) that this alias record set points to. The alias target must be health checked, or this setting has no effect',
        policy: 'The method that you want Route 53 to use when routing queries for this record set. Click each option for more information.',
        weight: 'Determines the probability that one record set will be selected from a group of weighted record sets. Valid values: 0 to 255. To disable routing to a resource, set Weight to 0. If you set the Weight to 0 for all of the record sets in a group, traffic is routed to all resources with equal probability. Example: two record sets have weights of 1 and 3 (sum = 4). On average, Route 53 selects the first record 1/4th of the time and the other record set 3/4ths of the time.',
        region: 'The Amazon EC2 region where the resource that is specified in this resource record set resides.',
        failover: 'Determines whether this record set is the active (primary) or passive (secondary) record set in an active-passive failover configuration',
        setId: {
            weight: 'Weighted record sets only. Enter a unique description that differentiates this record set from other weighted record sets that have the same name and type. Maximum of 128 characters. Example: Seattle data center, rack 2, position 4.',
            region: 'Latency record sets only. Enter a unique description that differentiates this record set from other latency record sets that have the same name and type. Maximum of 128 characters. Example: US West (Oregon) load balancer.',
            failover: 'Enter a unique description that differentiates this record set from other failover record sets that have the same name and type. Maximum of 128 characters.'
        }
    };

    var healthTooltips = {
        failureThreshold: 'The number of consecutive health checks that an endpoint must pass or fail for Route 53 to change the current status of the endpoint from unhealthy to healthy or vice versa.',
        stringMatching: 'Choose whether you want Route 53 to search the response body for the string that you specify in Search String. If you choose Yes, Route 53 considers the endpoint healthy only if the value appears entirely within the first 5120 bytes of the response body.'
    };

    var zonesStore = Ext.create('store.store', {
        fields: ['zoneId', 'name', 'recordSetCount', 'comment'],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/aws/route53/hostedzones/xList'
        },
        autoLoad: true,
        remoteSort: true,
        listeners: {
            load: function () {
                recordsGrid.hide();
            }
        }
    });

    var recordsStore = Ext.create('store.store', {
        fields: ['name', 'type', 'resourceRecord', 'evaluateTargetHealth', 'healthId', 'ttl', 'region', 'weight', 'setIdentifier', 'dnsName', 'aliasZoneId', 'policy', 'alias', 'failover'],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/aws/route53/recordsets/xList'
        },
        remoteSort: true,

        listeners: {
            load: function (store, records, successful) {
                var me = this;

                if (successful) {
                    var zoneId = me.getProxy().extraParams.zoneId;

                    recordsGrid.recordsCache[zoneId] = records;
                }

                recordsGrid.down('[name=saveZoneButton]').disable();
            },

            datachanged: function () {
                recordsGrid.getSelectionModel().clearSelections();
                recordsGrid.down('[name=saveZoneButton]').enable();
            },

            update: function (store, record, operation, modifiedFieldNames) {
                if (modifiedFieldNames.length === 1 && modifiedFieldNames[0] === 'snapshot') {
                    return;
                }

                recordsGrid.getSelectionModel().clearSelections();
                recordsGrid.down('[name=saveZoneButton]').enable();
            }
        }
    });

    var healthChecksStore = Ext.create('store.store', {
        fields: ['hostName', 'ipAddress', 'protocol', 'port', 'requestInterval', 'failureThreshold', 'searchString', 'healthId', 'resourcePath', 'stringMatching', {
            name: 'url',
            convert: function (value, record) {
                var protocol = record.get('protocol');
                var ipAddress = record.get('ipAddress');
                var port = record.get('port');
                var path = record.get('resourcePath');
                var url = '';

                if (ipAddress) {
                    url = protocol + '://' + ipAddress;
                    url = port ? url + ':' + port : url;
                    url = path ? url + '/' + path : url;

                    return url.toLowerCase();
                }

                return value;
            }
        }],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/aws/route53/healthchecks/xList'
        },
        remoteSort: true,
        listeners: {
            load: function () {
                healthChecksForm.hide();
                healthChecksForm.getForm().reset(true);
            }
        }
    });

    var zonesGrid = Ext.create('Ext.grid.Panel', {
        flex: 1.2,
        cls: 'x-grid-shadow x-grid-shadow-buffered x-panel-column-left',
        store: zonesStore,
        padding: '12 0 12 0',
        minWidth: 540,

        multiSelect: true,
        selModel: {
            selType: 'selectedmodel'
        },

        plugins: [
            'gridstore',
            'focusedrowpointer',
            {
                ptype: 'bufferedrenderer',
                scrollToLoadBuffer: 100,
                synchronousRender: false
            }
        ],

        viewConfig: {
            emptyText: 'No hosted zones found',
            loadingText: 'Loading hosted zones...'
        },

        listeners: {
            selectionchange: function (selModel, selections) {
                var me = this;

                var toolbar = me.down('toolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
            }
        },

        getSelectedZonesIds: function () {
            var me = this;

            var records = me.getSelectionModel().getSelection();
            var hostedZonesIds = [];

            for (var i = 0; i < records.length; i++) {
                hostedZonesIds.push(records[i].get('zoneId'));
            }

            return hostedZonesIds;
        },

        removeHostedZones: function (zonesIds) {
            var me = this;

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: 'Delete selected hosted zone(s): %s ?',
                    objects: zonesIds
                },
                processBox: {
                    type: 'delete'
                },
                url: '/tools/aws/route53/hostedzones/xDelete',
                params: {
                    cloudLocation: me.down('#cloudLocation').getValue(),
                    zoneId: Ext.encode(zonesIds)
                },
                success: function () {
                    zonesStore.load();
                }
            });
        },

        saveHostedZone: function (name, comment) {
            var me = this;

            Scalr.Request({
                processBox: {
                    type: 'save'
                },
                url: '/tools/aws/route53/hostedzones/xCreate',
                params: {
                    cloudLocation: me.down('#cloudLocation').getValue(),
                    domainName: name,
                    description: comment
                },
                success: function (data) {
                    var modifiedRecords = recordsStore.getModifiedRecords();

                    if (modifiedRecords.length) {
                        Ext.apply(recordsStore.getProxy().extraParams, {
                            zoneId: data.data.zoneId
                        });

                        Ext.Array.each(modifiedRecords, function (record) {
                            recordsGrid.saveRecordSet(Ext.clone(record.data));
                        });
                    }

                    zonesStore.load();
                }
            });
        },

        columns: [
            { header: "Domain name", flex: 1, dataIndex: 'name', sortable: true },
            { header: "Comment", flex: 1, dataIndex: 'comment', sortable: true }
        ],

        dockedItems: [{
            xtype: 'toolbar',
            style: 'box-shadow: none;',
            dock: 'top',
            padding: '0 0 12 0',
            layout: 'hbox',
            width: 160,
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                store: zonesStore,
                width: 110,
                margin: 0
            }, {
                xtype: 'fieldcloudlocation',
                itemId: 'cloudLocation',
                fieldLabel: null,
                width: 220,
                margin: '0 0 0 12',
                store: {
                    fields: [ 'id', 'name' ],
                    data: moduleParams.locations,
                    proxy: 'object'
                },
                gridStore: zonesStore
            }, {
                xtype: 'tbfill',
                flex: .01
            }, {
                text: 'Add zone',
                name: 'addZoneButton',
                cls: 'x-btn-green-bg',
                margin: '0 12 0 0',
                handler: function() {
                    zonesGrid.getSelectionModel().clearSelections();

                    recordsGrid.down('#refresh').disable();

                    var domainName = recordsGrid.down('[name=zoneName]');
                    domainName.setValue('').enable();

                    var comment = recordsGrid.down('[name=zoneComment]');
                    comment.setValue('').enable();

                    recordsGrid.down('[name=zoneId]').setValue('').hide();

                    recordsGrid.down('[name=addRecordButton]').disable();

                    recordsStore.removeAll();

                    //recordsGrid.getPlugin('recordsBufferedRenderer').cancelLoad();

                    recordsGrid.down('[name=saveZoneButton]').disable();

                    recordsGrid.show();
                }
            }, {
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                margin: '0 12 0 0',
                tooltip: 'Refresh',
                handler: function() {
                    zonesStore.load();
                }
            }, {
                ui: 'paging',
                itemId: 'delete',
                iconCls: 'x-tbar-delete',
                margin: '0 12 0 0',
                tooltip: 'Select one or more hosted zone(s) to delete them',
                disabled: true,
                handler: function () {
                    var params = zonesGrid.getSelectedZonesIds();
                    zonesGrid.removeHostedZones(params);
                }
            }]
        }]
    });

    var recordsGrid = Ext.create('Ext.grid.Panel', {
        //flex: 0.8,
        //cls: 'x-grid-shadow x-grid-shadow-buffered x-fieldset-separator-right',
        cls: 'x-grid-shadow x-grid-shadow-buffered',
        store: recordsStore,
        padding: '12 32 12 32',

        hidden: true,

        multiSelect: true,
        selModel: {
            selType: 'selectedmodel'
        },

        plugins: [
            'gridstore',
            {
                ptype: 'focusedrowpointer',
                addCls: 'x-panel-row-pointer-light'
            }
            //TODO
            /*{
                ptype: 'bufferedrenderer',
                pluginId: 'recordsBufferedRenderer',
                scrollToLoadBuffer: 100,
                synchronousRender: false
            }*/
        ],

        viewConfig: {
            emptyText: 'No record sets',
            loadingText: 'Loading record sets...'
        },

        recordsCache: {},
        recordsSnapshot: {},

        isRecordSetRemovable: function (recordSetType, recordSetName) {
            var me = this;

            return !(recordSetType === 'SOA' || (recordSetType === 'NS' && recordSetName === me.down('[name=zoneName]').getValue()));
        },

        listeners: {
            afterrender: function () {
                var me = this;

                zonesGrid.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                    if (newFocused) {
                        var zoneData = newFocused.data;

                        var domainName = me.down('[name=zoneName]');
                        domainName.setValue(zoneData.name).disable();

                        var comment = me.down('[name=zoneComment]');
                        comment.setValue(zoneData['comment']).disable();

                        me.down('[name=zoneId]').setValue(zoneData.zoneId).show();

                        me.down('#refresh').enable();
                        me.down('[name=addRecordButton]').enable();

                        var zoneId = zoneData.zoneId;
                        var cache = me.recordsCache[zoneId];

                        recordsStore.getProxy().extraParams = {
                            cloudLocation: zonesGrid.down('#cloudLocation').getValue(),
                            zoneId: zoneId
                        };

                        if (!cache) {
                            recordsStore.load();
                        } else {
                            recordsStore.loadData(cache);
                        }

                        me.show();
                    } else {
                        me.hide();
                        me.getSelectionModel().clearSelections();
                    }
                });
            },

            selectionchange: function (selModel, selections) {
                var me = this;

                var toolbar = me.down('toolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
            },

            beforeselect: function (grid, record) {
                var me = this;

                return me.isRecordSetRemovable(record.get('type'), record.get('name'));
            }
        },

        getRecordSetParams: function (recordSetData) {
            var recordSetParams = {
                name: recordSetData['name'],
                type: recordSetData['type'],
                setIdentifier: recordSetData['setIdentifier'],
                policy: recordSetData['policy'],
                weight: recordSetData['weight'],
                region: recordSetData['region'],
                failover: recordSetData['failover'],
                healthId: recordSetData['healthId']
            };

            if (recordSetData['dnsName']) {
                recordSetParams.dnsName = recordSetData['dnsName'];
                recordSetParams.evaluateTargetHealth = recordSetData['evaluateTargetHealth'].toString();
                recordSetParams.aliasZoneId = recordSetData['aliasZoneId'];
            } else {
                recordSetParams.ttl = recordSetData['ttl'];
                recordSetParams.resourceRecord = recordSetData['resourceRecord'];
            }

            return recordSetParams;
        },

        getRecordsParams: function (records) {
            var me = this;

            var recordSetsParams = [];

            Ext.each(records, function (record) {
                recordSetsParams.push(me.getRecordSetParams(record['data']));
            });

            return recordSetsParams;
        },

        doRecreate: function (oldParams, newParams) {
            if (oldParams) {
                return newParams.name !== oldParams.name ||
                    newParams.type !== oldParams.type ||
                    newParams.setIdentifier !== oldParams.setIdentifier;
            }

            return false;
        },

        saveRecordSet: function (recordParams) {
            var me = this;

            recordParams.resourceRecord = Ext.encode(recordParams.resourceRecord);

            var oldRecordSet = recordParams.snapshot;
            var oldRecordSetParams = oldRecordSet ? Ext.encode(me.getRecordSetParams(oldRecordSet)) : null;

            var request = {
                processBox: {
                    type: 'save'
                },
                scope: this,
                params: {
                    cloudLocation: zonesGrid.down('#cloudLocation').getValue(),
                    zoneId: recordsStore.getProxy().extraParams.zoneId,
                    action: !oldRecordSet ? 'CREATE' : 'UPSERT',
                    oldRecordSet: oldRecordSetParams
                },
                url: '/tools/aws/route53/recordsets/xSave',
                success: function () {
                    recordsStore.load();
                    zonesGrid.getSelectionModel().clearSelections();

                    recordsGrid.recordsSnapshot = {};
                }
            };

            Ext.Object.merge(request.params, recordParams);

            Scalr.Request(request);
        },

        removeRecordSets: function (recordSetsParams) {
            Scalr.Request({
                /*
                confirmBox: {
                    type: 'delete',
                    msg: 'Delete selected record set(s): %s ?',
                    objects: recordSetsParams.map(function (recordSetParams) {
                        return recordSetParams['name'] + '/' + recordSetParams['type'];
                    })
                },
                */
                processBox: {
                    type: 'delete'
                },
                url: '/tools/aws/route53/recordsets/xDelete',
                params: {
                    cloudLocation: zonesGrid.down('#cloudLocation').getValue(),
                    zoneId: recordsStore.getProxy().extraParams.zoneId,
                    recordSets: Ext.encode(recordSetsParams)
                },
                success: function () {
                    var modifiedRecords = recordsStore.getModifiedRecords();

                    if (modifiedRecords.length) {
                        Ext.Array.each(modifiedRecords, function (record) {
                            recordsGrid.saveRecordSet(Ext.clone(record.data));
                        });
                    } else {
                        zonesGrid.getSelectionModel().clearSelections();
                    }
                }
            });
        },

        columns: [
            { header: "Name", flex: 1, dataIndex: 'name', sortable: true },
            { header: "Type", width: 75, dataIndex: 'type', sortable: true },
            { header: "Alias", width: 75, align: 'center', xtype: 'templatecolumn',
                tpl: [
                    '<tpl if="alias">',
                        '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-ok" />',
                    '<tpl else>',
                        '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-minus" />',
                    '</tpl>'
                ]
            }
        ],

        dockedItems: [{
            xtype: 'component',
            cls: 'x-fieldset-header scalr-analytics-pricing-header',
            style: 'padding: 0;',
            margin: '0 0 12 0',
            html: '<div class="x-fieldset-header-text" style="float:none">' +
                'Hosted zone details</div>'
        }, {
            xtype: 'container',
            dock: 'top',

            layout: 'vbox',
            width: '100%',
            defaults: {
                width: '100%'
            },
            items: [{
                xtype: 'displayfield',
                fieldLabel: 'Zone ID',
                name: 'zoneId'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Domain name',
                allowBlank: false,
                regex: /^(?!:\/\/)([a-zA-Z0-9]+\.)?[a-zA-Z0-9][a-zA-Z0-9-]+\.[a-zA-Z]{2,6}?$/i,
                name: 'zoneName',
                listeners: {
                    validitychange: function (field, isValid) {
                        var addRecordButton = recordsGrid.down('[name=addRecordButton]');
                        var saveZoneButton = recordsGrid.down('[name=saveZoneButton]').enable();

                        if (!isValid) {
                            addRecordButton.disable();
                            saveZoneButton.disable();
                            return;
                        }

                        addRecordButton.enable();
                        saveZoneButton.enable();
                    }
                }
            }, {
                xtype: 'textfield',
                fieldLabel: 'Comment',
                name: 'zoneComment'
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Record sets'
            }, {
                xtype: 'toolbar',
                style: 'box-shadow: none;',
                padding: '0 0 12 0',
                layout: 'hbox',
                width: '100%',
                defaults: {
                    margin: '0 0 0 12'
                },
                items: [{
                    xtype: 'filterfield',
                    store: recordsStore,
                    width: 220,
                    margin: 0
                }, {
                    xtype: 'tbfill',
                    flex: .01
                }, {
                    text: 'Add record',
                    name: 'addRecordButton',
                    cls: 'x-btn-green-bg',
                    margin: '0 12 0 0',
                    handler: function() {
                        var zoneNameField = recordsGrid.down('[name=zoneName]');

                        recordsGrid.getSelectionModel().clearSelections();

                        recordsForm.getForm().reset(true);
                        recordsForm.disableFields(false);
                        recordsForm.down('[name=hostedZoneName]').setValue(zoneNameField.getValue());
                        recordsForm.down('[name=recordsFormSaveButton]').setText('Add');
                        recordsForm.show();

                        if (!zoneNameField.isDisabled()) {
                            zoneNameField.disable();

                            recordsForm.down('[name=aliasContainer]').hide();
                        }
                    }
                }, {
                    itemId: 'refresh',
                    ui: 'paging',
                    iconCls: 'x-tbar-loading',
                    margin: '0 12 0 0',
                    tooltip: 'Refresh',
                    handler: function() {
                        recordsGrid.getSelectionModel().clearSelections();

                        recordsForm.hide();
                        recordsForm.getForm().reset(true);

                        recordsStore.load();
                    }
                }, {
                    ui: 'paging',
                    itemId: 'delete',
                    iconCls: 'x-tbar-delete',
                    margin: '0 12 0 0',
                    tooltip: 'Select one or more record set(s) to delete them',
                    disabled: true,
                    handler: function () {
                        recordsForm.hide();
                        recordsForm.getForm().reset(true);

                        var selectedRecordSets = recordsGrid.getSelectionModel().getSelection();
                        recordsStore.remove(selectedRecordSets);
                    }
                }]
            }]
        }, {
            xtype: 'panel',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            //weight: 10,
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [
                {
                    xtype: 'button',
                    name: 'saveZoneButton',
                    text: 'Save',
                    handler: function () {
                        var zoneCommentField = recordsGrid.down('[name=zoneComment]');

                        if (!zoneCommentField.isDisabled()) {
                            var zoneNameField = recordsGrid.down('[name=zoneName]');

                            if (zoneNameField.isValid()) {
                                zonesGrid.saveHostedZone(zoneNameField.getValue(), zoneCommentField.getValue());

                            }
                            return;
                        }

                        var removedRecords = recordsStore.getRemovedRecords();

                        if (removedRecords.length) {
                            var removedRecordsParams = recordsGrid.getRecordsParams(removedRecords);
                            recordsGrid.removeRecordSets(removedRecordsParams);
                        } else {
                            var modifiedRecords = recordsStore.getModifiedRecords();

                            Ext.Array.each(modifiedRecords, function (record) {
                                recordsGrid.saveRecordSet(Ext.clone(record.data));
                            });
                        }
                    }
                },
                {
                    xtype: 'button',
                    text: 'Cancel',
                    handler: function () {
                        zonesGrid.getSelectionModel().clearSelections();

                        if (recordsGrid.down('#refresh').isDisabled()) {
                            recordsGrid.hide();
                        }
                    }
                }
            ]
        }]
    });

    var recordsGridContainer = {
        xtype: 'container',
        flex: .8,
        minWidth: 475,
        maxWidth: 900,
        layout: 'fit',
        cls: 'x-transparent-mask',
        items: recordsGrid
    };

    var healthChecksGrid = Ext.create('Ext.grid.Panel', {
        flex: 1.2,
        cls: 'x-grid-shadow x-grid-shadow-buffered x-panel-column-left',
        store: healthChecksStore,
        padding: '12 0 12 0',
        forceFit: true,

        multiSelect: true,
        selModel: {
            selType: 'selectedmodel',
            allowDeselect: true
        },

        plugins: [
            'gridstore',
            'focusedrowpointer',
            {
                ptype: 'bufferedrenderer',
                scrollToLoadBuffer: 100,
                synchronousRender: false
            }
        ],

        viewConfig: {
            emptyText: 'No health checks found',
            deferEmptyText: false,
            loadMask: false
        },

        listeners: {
            selectionchange: function (selModel, selections) {
                var me = this;

                var toolbar = me.down('toolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
            }
        },

        getUrl: function (protocol, ipAddress, port, path) {
            var url = '';

            if (ipAddress) {
                url = protocol + '://' + ipAddress;
                url = port ? url + ':' + port : url;
                url = path ? url + '/' + path : url;
            }

            return url.toLowerCase();
        },

        getSelectedHealthParams: function () {
            var me = this;

            var records = me.getSelectionModel().getSelection();
            var healthId = [];
            var healthUrl = [];

            for (var i = 0; i < records.length; i++) {
                var currentRecord = records[i];

                healthId.push(currentRecord.get('healthId'));

                healthUrl.push(me.getUrl(
                    currentRecord.get('protocol'),
                    currentRecord.get('ipAddress'),
                    currentRecord.get('port'),
                    currentRecord.get('resourcePath')
                ));
            }

            return {healthId: healthId, healthUrl: healthUrl};
        },

        removeHealthChecks: function (params) {
            var me = this;

            var healthId = params.healthId;
            var healthUrl = params.healthUrl;

            if (typeof healthId === 'string') {
                healthId = [healthId];
            }

            if (typeof healthUrl === 'string') {
                healthUrl = [healthUrl];
            }

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: 'Delete selected health check(s): %s ?',
                    objects: healthUrl
                },
                processBox: {
                    type: 'delete'
                },
                url: '/tools/aws/route53/healthchecks/xDelete',
                params: {
                    cloudLocation: me.down('#cloudLocation').getValue(),
                    healthId: Ext.encode(healthId)
                },
                success: function () {
                    healthChecksStore.load();
                }
            });
        },

        columns: [
            { header: "Url", flex: 1, itemId: 'healthUrl', xtype: 'templatecolumn',
                tpl: [
                    '<tpl>',
                    '{[this.getUrl(values.protocol, values.ipAddress, values.port, values.resourcePath)]}',
                    '</tpl>',
                    {
                        getUrl: function (protocol, ipAddress, port, path) {
                            var url = '';

                            if (ipAddress) {
                                url = protocol + '://' + ipAddress;
                                url = port ? url + ':' + port : url;
                                url = path ? url + '/' + path : url;
                            }

                            return url.toLowerCase();
                        }
                    }
                ]
            },
            { header: "Host name", flex: 1, xtype: 'templatecolumn',
                tpl: [
                    '<tpl if="hostName">',
                    '{hostName}',
                    '<tpl else>',
                    '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-minus" />',
                    '</tpl>'
                ]
            },
            { header: "ID", flex: 1, dataIndex: 'healthId', sortable: true }
        ],

        dockedItems: [{
            xtype: 'toolbar',
            style: 'box-shadow: none;',
            dock: 'top',
            padding: '0 0 12 0',
            layout: 'hbox',
            width: 160,
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                store: healthChecksStore,
                width: 220,
                margin: 0
            }, {
                xtype: 'fieldcloudlocation',
                itemId: 'cloudLocation',
                fieldLabel: null,
                width: 220,
                margin: '0 0 0 12',
                store: {
                    fields: [ 'id', 'name' ],
                    data: moduleParams.locations,
                    proxy: 'object'
                },
                gridStore: healthChecksStore
            }, {
                xtype: 'tbfill',
                flex: .01
            }, {
                text: 'Add health check',
                cls: 'x-btn-green-bg',
                margin: '0 12 0 0',
                handler: function() {
                    healthChecksGrid.getSelectionModel().clearSelections();

                    healthChecksForm.getForm().reset(true);
                    healthChecksForm.enable();
                    healthChecksForm.show();
                }
            }, {
                itemId: 'refresh',
                ui: 'paging',
                iconCls: 'x-tbar-loading',
                margin: '0 12 0 0',
                tooltip: 'Refresh',
                handler: function() {
                    healthChecksGrid.getSelectionModel().clearSelections();

                    healthChecksForm.hide();
                    healthChecksForm.getForm().reset(true);

                    healthChecksStore.load();
                }
            }, {
                ui: 'paging',
                itemId: 'delete',
                iconCls: 'x-tbar-delete',
                margin: '0 12 0 0',
                tooltip: 'Select one or more health check(s) to delete them',
                disabled: true,
                handler: function () {
                    healthChecksForm.hide();
                    healthChecksForm.getForm().reset(true);

                    var params = healthChecksGrid.getSelectedHealthParams();
                    healthChecksGrid.removeHealthChecks(params);
                }
            }]
        }]
    });

    var healthChecksForm = Ext.create('Ext.form.Panel', {
        fieldDefaults: {
            anchor: '100%'
        },

        autoScroll: true,
        hidden: true,

        layout: {
            type: 'vbox',
            align: 'stretch'
        },

        updateUrl: function () {
            var me = this;

            var url = '';
            var protocol = me.down('[name=protocol]').getValue();
            var address = me.down('[name=ipAddress]').getValue();
            var port = me.down('[name=port]').getValue();
            var pathField = me.down('[name=resourcePath]');
            var path = pathField.getValue();

            if (address) {
                url = protocol + '://' + address;
                url = port ? url + ':' + port : url;
                url = path && pathField.isVisible() ? url + '/' + path : url;
            }

            me.down('[name=url]').setValue(url);
        },

        updateType: function () {
            var me = this;

            var type = 'basic &nbsp';
            var requestInterval = me.down('[name=requestInterval]').getValue();
            var stringMatchingEnabled = me.down('[name=searchStringContainer]').isVisible();

            var getOptions = function (requestInterval, stringMatchingEnabled) {
                var options = [];

                if (requestInterval === 'Fast') {
                    options.push('fast interval');
                }

                if (stringMatchingEnabled) {
                    options.push('string matching')
                }

                return options.join(', ');
            };

            var options = getOptions(requestInterval, stringMatchingEnabled);
            type = options ? type + '+&nbsp additional options: ' + options : type + '‒&nbsp no additional options selected';
            type = type + ' (' + '<a href="http://aws.amazon.com/route53/pricing/#HealthChecks">view pricing</a>' + ')';

            me.down('[name=healthCheckType]').setValue(type);
        },

        saveHealthCheck: function () {
            var me = this;

            var form = me.getForm();

            if (form.isValid()) {
                Scalr.Request({
                    processBox: {
                        type: 'save'
                    },
                    url: '/tools/aws/route53/healthchecks/xCreate',
                    form: form,
                    params: {
                        cloudLocation: healthChecksGrid.down('#cloudLocation').getValue()
                    },
                    success: function () {
                        me.hide();
                        me.getForm().reset(true);

                        healthChecksStore.load();
                    }
                });
            }
        },

        listeners: {
            boxready: function () {
                var me = this;
                me.updateUrl();
            },

            afterrender: function () {
                var me = this;

                healthChecksGrid.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                    if (newFocused) {
                        if (me.getRecord() !== newFocused) {
                            me.loadRecord(newFocused);
                            me.disable();
                            me.show();
                        }
                    } else {
                        me.hide();
                        me.getForm().reset(true);
                    }
                });
            },

            beforedestroy: function () {
                //TODO
                //this.abortCurrentRequest();
            }
        },

        items: [{
            xtype: 'fieldset',
            title: 'Health check details',
            defaults: {
                labelWidth: 115
            },

            items: [{
                fieldLabel: 'Protocol',
                xtype: 'buttongroupfield',
                value: 'http',
                defaults: {
                    width: 100 / 3 + '%'
                },
                items: [
                    {
                        xtype: 'button',
                        text: 'HTTP',
                        value: 'http'
                    },
                    {
                        xtype: 'button',
                        text: 'HTTPS',
                        value: 'https'
                    },
                    {
                        xtype: 'button',
                        text: 'TCP',
                        value: 'tcp'
                    }
                ],
                listeners: {
                    change: function (buttongroup, value) {
                        var fields = ['stringMatchingSet', 'hostName', 'resourcePath'];

                        healthChecksForm.toggleFields(fields, value !== 'tcp');

                        healthChecksForm.updateUrl();
                    }
                },
                name: 'protocol'
            }, {
                fieldLabel: 'IP address',
                xtype: 'textfield',
                allowBlank: false,
                regex: /(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])/,
                name: 'ipAddress',
                listeners: {
                    change: function () {
                        healthChecksForm.updateUrl();
                    }
                }
            }, {
                fieldLabel: 'Port',
                xtype: 'numberfield',
                value: 80,
                allowBlank: false,
                regex: /^\d+$/,
                minValue: 1,
                maxValue: 65535,
                name: 'port',
                listeners: {
                    change: function () {
                        healthChecksForm.updateUrl();
                    }
                }
            }, {
                fieldLabel: 'Host name',
                xtype: 'textfield',
                name: 'hostName'
            }, {
                fieldLabel: 'Path',
                xtype: 'textfield',
                name: 'resourcePath',
                listeners: {
                    change: function () {
                        healthChecksForm.updateUrl();
                    }
                },
                getSubmitValue: function () {
                    var me = this;

                    return '/' + me.getValue();
                }
            }]
        }, {
            xtype: 'fieldset',
            defaults: {
                labelWidth: 115
            },

            items: [{
                fieldLabel: 'Request interval',
                xtype: 'combo',
                store: {
                    fields: ['type', 'interval', {
                        name: 'displayedValue', convert: function (value, model) {
                            return model.get('type') + ' (' + model.get('interval') + ' seconds)';
                        }
                    }],
                    data: [
                        {type: 'Standard', interval: 30},
                        {type: 'Fast', interval: 10}
                    ]
                },
                displayField: 'displayedValue',
                valueField: 'interval',
                value: 30,
                editable: false,
                listeners: {
                    change: function () {
                        healthChecksForm.updateType();
                    }
                },
                name: 'requestInterval'
            }, {
                fieldLabel: 'Failure threshold',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                items: [{
                    xtype: 'numberfield',
                    flex: 1,
                    value: 3,
                    minValue: 1,
                    maxValue: 10,
                    editable: false,
                    name: 'failureThreshold'
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: healthTooltips.failureThreshold
                }]
            }]
        }, {
            xtype: 'fieldset',
            defaults: {
                labelWidth: 275
            },
            name: 'stringMatchingSet',
            items: [{
                fieldLabel: 'Enable string matching',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                items: [{
                    xtype: 'buttongroupfield',
                    flex: 1,
                    value: false,
                    defaults: {
                        width: '50%'
                    },
                    items: [
                        {
                            xtype: 'button',
                            text: 'Yes',
                            value: true
                        },
                        {
                            xtype: 'button',
                            text: 'No',
                            value: false
                        }
                    ],
                    listeners: {
                        change: function (field, value) {
                            healthChecksForm.toggleFields('searchStringContainer');
                            healthChecksForm.updateType();

                            Ext.apply(healthChecksForm.down('[name=searchString]'), {allowBlank: !value});
                        }
                    },
                    name: 'stringMatching'
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: healthTooltips.failureThreshold
                }]
            }, {
                xtype: 'container',
                hidden: true,
                name: 'searchStringContainer',
                items: [{
                    fieldLabel: 'Search string',
                    xtype: 'displayfield'
                }, {
                    xtype: 'textareafield',
                    minLength: 1,
                    maxLength: 255,
                    width: '100%',
                    name: 'searchString'
                }]
            }]
        }, {
            xtype: 'fieldset',
            defaults: {
                xtype: 'displayfield',
                labelWidth: 120
            },

            items: [{
                fieldLabel: 'URL',
                name: 'url'
            }, {
                fieldLabel: 'Health check type',
                value: 'basic &nbsp‒&nbsp no additional options selected (' + '<a href="http://aws.amazon.com/route53/pricing/#HealthChecks" target="_blank">view pricing</a>' + ')',
                name: 'healthCheckType'
            }]
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
                text: 'Save',
                handler: function() {
                    healthChecksForm.saveHealthCheck();
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]
    });

    var healthFormContainer = {
        xtype: 'container',
        flex: .6,
        minWidth: 450,
        maxWidth: 800,
        layout: 'fit',
        cls: 'x-transparent-mask',
        items: healthChecksForm
    };

    var aliasTargetsStore = Ext.create('store.store', {
        fields: ['title', 'domainName', 'aliasZoneId', {
            name: 'displayValue',
            convert: function (value, record) {
                return record.get('title') + ' / ' + record.get('domainName')
            }
        }],
        proxy: {
            type: 'object'
        }
    });

    var recordsForm = Ext.create('Ext.form.Panel', {
        bodyStyle: 'background-color: #f9fafb;',
        fieldDefaults: {
            anchor: '100%'
        },

        autoScroll: true,

        hidden: true,

        isRecordSetRemovable: true,

        disableFields: function (disable) {
            var me = this;

            var nameField = me.down('[name=name]');
            nameField.setDisabled(disable);

            me.down('[name=hostedZoneName]').setVisible(!disable);
            me.down('[name=type]').setDisabled(disable);
            me.down('[name=aliasContainer]').setVisible(!disable);
        },

        setName: function (name, hostedZoneName) {
            var me = this;

            if (me.isRecordSetRemovable) {
                me.down('[name=name]').setValue(
                    name !== hostedZoneName ? name.slice(0, name.indexOf(hostedZoneName) - 1) : ''
                );
            }
        },

        setResourceRecord: function (value) {
            var me = this;

            if (value) {
                me.down('[name=resourceRecord]').setValue(value.join('\n'));
            }
        },

        setSetId: function (setId) {
            var me = this;

            me.down('[name=setIdentifier]').setValue(setId);
        },

        formatForm: function (recordData, zoneName) {
            var me = this;

            var alias = recordData['alias'];
            //recordData['alias'] = false;

            me.setName(recordData['name'], zoneName);
            me.setResourceRecord(recordData['resourceRecord']);
            me.setSetId(recordData['setIdentifier']);

            //me.down('[name=alias]').setValue(alias);
        },

        changeSetId: function (isFailover) {
            var me = this;

            var setId = '';

            if (isFailover) {
                var name = me.down('[name=name]').getValue();
                var recordType = me.down('[name=failover]').getValue();

                setId = name ? name + '-' + recordType : recordType;
            }

            me.down('[name=setIdentifier]').setValue(setId);
        },

        changeValueTooltip: function (type) {
            var me = this;

            me.suspendLayouts();

            me.down('[name=resourceRecordDescription]').setValue(recordsTooltips.resourceRecord[type]);

            me.resumeLayouts(true);
            me.doLayout();
        },

        changeAliasZoneId: function (domainName) {
            var me = this;

            var aliasZoneIdField = me.down('[name=aliasZoneId]');
            var record = aliasTargetsStore.findRecord('domainName', domainName);

            if (record) {
                var aliasZoneId = record.get('aliasZoneId');

                me.suspendLayouts();

                aliasZoneIdField.setValue(aliasZoneId);
                aliasZoneIdField.show();

                me.resumeLayouts(true);
                me.doLayout();
            }
        },

        updateNamePreview: function (name) {
            var me = this;

            var hostedZoneNameField = me.down('[name=hostedZoneName]');
            var hostedZoneName = recordsGrid.down('[name=zoneName]').getValue();

            if (name) {
                hostedZoneNameField.setValue(name + '.' + hostedZoneName);
            } else {
                hostedZoneNameField.setValue(hostedZoneName);
            }
        },

        updateSetId: function (name) {
            var me = this;

            if (me.down('[name=failoverContainer]').isVisible()) {
                var failoverType = me.down('[name=failover]').getValue();

                me.down('[name=setIdentifier]').setValue(
                    name ? name + '-' + failoverType : failoverType
                );
            }
        },

        getAliasTargets: function () {
            var me = this;

            Scalr.Request({
                url: '/tools/aws/route53/recordsets/xGetAliasTargets',
                processBox: {
                    type: 'action',
                    msg: 'Loading alias targets...'
                },
                params: {
                    cloudLocation: zonesGrid.down('#cloudLocation').getValue(),
                    zoneId: recordsStore.getProxy().extraParams.zoneId,
                    name: me.down('[name=name]').getSubmitValue()
                },
                success: function (data) {
                    var aliasTargets = data['data'];
                    var dnsNameCombo = me.down('[name=dnsName]');

                    if (aliasTargets && aliasTargets.length) {
                        dnsNameCombo.enable();
                        dnsNameCombo.setValue('Select alias target');
                        aliasTargetsStore.loadData(aliasTargets);
                    } else {
                        dnsNameCombo.disable();
                        dnsNameCombo.setValue('No alias targets available');
                    }
                }
            });
        },

        removeS3Targets: function () {
            aliasTargetsStore.remove(aliasTargetsStore.queryBy(function (record) {
                return record.get('type') === 'S3 website endpoints';
            }));
        },

        getS3Targets: function (recordSetName) {
            var me = this;

            Scalr.Request({
                url: '/tools/aws/route53/recordsets/xGetS3Targets',
                params: {
                    cloudLocation: zonesGrid.down('#cloudLocation').getValue(),
                    zoneId: recordsStore.getProxy().extraParams.zoneId,
                    name: recordSetName
                },
                success: function (data) {
                    if (data.data.length) {
                        me.removeS3Targets();

                        aliasTargetsStore.add(data);

                        me.down('[name=dnsName]').enable();
                    }
                }
            });
        },

        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-light-separator-bottom',
            title: 'Record set details',
            defaults: {
                labelWidth: 120
            },

            items: [{
                fieldLabel: 'Name',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                items: [{
                    xtype: 'textfield',
                    flex: 1,
                    name: 'name',
                    listeners: {
                        change: function (field, value) {
                            value = Ext.String.htmlEncode(value);
                            recordsForm.updateNamePreview(value);
                            recordsForm.updateSetId(value);
                        }
                    },
                    getSubmitValue: function () {
                        var me = this;

                        return recordsForm.isRecordSetRemovable ? recordsForm.down('[name=hostedZoneName]').getValue() : me.getValue();
                    }
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: recordsTooltips.name
                }]
            }, {
                xtype: 'displayfield',
                margin: '0 26 6 126',
                fieldStyle: 'text-align: right',
                //value: recordsGrid.down('[name=zoneName]').getValue(),
                name: 'hostedZoneName'
            }, {
                fieldLabel: 'Type',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                items: [{
                    xtype: 'combo',
                    flex: 1,
                    store: {
                        fields: ['type', 'description', {
                            name: 'displayedValue', convert: function (value, model) {
                                return model.get('type') + ' ‒ ' + model.get('description');
                            }
                        }],
                        data: [
                            {type: 'A', description: 'IPv4 address'},
                            {type: 'CNAME', description: 'Canonical name'},
                            {type: 'MX', description: 'IMail exchange'},
                            {type: 'AAAA', description: 'IPv6 address'},
                            {type: 'TXT', description: 'Text'},
                            {type: 'PTR', description: 'Pointer'},
                            {type: 'SRV', description: 'Service locator'},
                            {type: 'SPF', description: 'Sender Policy Framework'},
                            {type: 'NS', description: 'Name server'}
                            //{type: 'SOA', description: 'Start of authority'}
                        ]
                    },
                    displayField: 'displayedValue',
                    valueField: 'type',
                    value: 'A',
                    editable: false,
                    name: 'type',
                    listeners: {
                        change: function (combo, value) {
                            recordsForm.changeValueTooltip(value);
                        }
                    }
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: recordsTooltips.type
                }]
            }]
        }, {
            xtype: 'fieldset',
            cls: 'x-fieldset-light-separator-bottom',
            defaults: {
                labelWidth: 120
            },

            items: [{
                fieldLabel: 'Alias',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                name: 'aliasContainer',
                items: [{
                    xtype: 'buttongroupfield',
                    flex: 1,
                    value: false,
                    name: 'alias',
                    defaults: {
                        width: '25%'
                    },
                    items: [
                        {
                            xtype: 'button',
                            text: 'Yes',
                            value: true
                        },
                        {
                            xtype: 'button',
                            text: 'No',
                            value: false
                        }
                    ],
                    listeners: {
                        change: function (field, value) {
                            var fields = ['ttlContainer', 'resourceRecord', 'resourceRecordDescription', 'dnsNameContainer', 'dnsName', 'evaluateTargetHealth'];

                            recordsForm.toggleFields(fields);

                            Ext.apply(recordsForm.down('[name=resourceRecord]'), {allowBlank: value});
                            Ext.apply(recordsForm.down('[name=dnsName]'), {allowBlank: !value});
                        }
                    }
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: recordsTooltips.alias
                }]
            }, {
                fieldLabel: 'TTL (seconds)',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                name: 'ttlContainer',
                items: [{
                    xtype: 'numberfield',
                    flex: 1,
                    step: 60,
                    value: 300,
                    name: 'ttl'
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: recordsTooltips.ttl
                }]
            }, {
                fieldLabel: 'Value',
                xtype: 'textareafield',
                allowBlank: false,
                name: 'resourceRecord',
                getSubmitValue: function () {
                    var me = this;

                    return me.getValue().split('\n');

                    /*
                    return Ext.encode(
                        me.getValue().split('\n')
                    );
                    */
                }
            }, {
                xtype: 'displayfield',
                margin: '0 0 0 126',
                name: 'resourceRecordDescription',
                value: recordsTooltips.resourceRecord['A']
            }, {
                fieldLabel: 'Alias target',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                hidden: true,
                name: 'dnsNameContainer',
                items: [{
                    xtype: 'displayfield',
                    flex: 1
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: recordsTooltips.aliasTarget
                }]
            }, {
                xtype: 'combo',
                flex: 1,
                hidden: true,
                editable: false,
                store: aliasTargetsStore,
                displayField: 'displayValue',
                valueField: 'domainName',
                emptyText: 'Select alias target',
                name: 'dnsName',
                queryMode: 'local',
                listeners: {
                    focus: function () {
                        if (!aliasTargetsStore.getCount()) {
                            recordsForm.getAliasTargets();
                        }
                    },
                    change: function (combo, value) {
                        if (value) {
                            recordsForm.changeAliasZoneId(value);
                        }
                    },
                    afterrender: function () {
                        var me = this;
                        var nameField = recordsForm.down('[name=name]');

                        nameField.on('blur', function () {
                            var recordName = nameField.getSubmitValue();

                            if (me.isVisible() && me.recordName !== recordName) {
                                recordsForm.getS3Targets(recordName)
                            }

                            me.recordName = recordName;
                        });
                    }
                },
                getSubmitValue: function () {
                    var me = this;

                    return recordsForm.down('[name=alias]').getValue() ? me.getValue() : null;
                }
            }, {
                fieldLabel: 'Alias hosted zone ID',
                labelWidth: 130,
                xtype: 'displayfield',
                hidden: true,
                submitValue: true,
                name: 'aliasZoneId'
            }, {
                boxLabel: 'Evaluate target health',
                xtype: 'checkbox',
                checked: false,
                hidden: true,
                name: 'evaluateTargetHealth',
                getSubmitValue: function () {
                    var me = this;

                    return me.getValue().toString();
                }
            }]
        }, {
            xtype: 'fieldset',
            cls: 'x-fieldset-light-separator-bottom',
            defaults: {
                labelWidth: 120
            },

            items: [{
                fieldLabel: 'Routing policy',
                xtype: 'buttongroupfield',
                value: 'simple',
                defaults: {
                    width: '25%'
                },
                items: [
                    {
                        xtype: 'button',
                        text: 'Simple',
                        value: 'simple'
                    },
                    {
                        xtype: 'button',
                        text: 'Weighted',
                        value: 'weight'
                    },
                    {
                        xtype: 'button',
                        text: 'Latency',
                        value: 'region'
                    },
                    {
                        xtype: 'button',
                        text: 'Failover',
                        value: 'failover'
                    }
                ],
                listeners: {
                    change: function (field, value) {
                        recordsForm.toggleFields(['weightContainer', 'regionContainer', 'failoverContainer', 'setIdContainer', 'healthId', 'healthIdLabel'], true);

                        if (value !== 'simple') {
                            recordsForm.toggleFields([value + 'Container', 'healthId', 'healthIdLabel', 'setIdContainer']);
                        }

                        recordsForm.down('[name=setIdTooltip]').setInfo(recordsTooltips.setId[value]);

                        Ext.apply(recordsForm.down('[name=weight]'), {allowBlank: true});
                        Ext.apply(recordsForm.down('[name=region]'), {allowBlank: true});

                        Ext.apply(recordsForm.down('[name=' + value + ']'), {allowBlank: false});
                        Ext.apply(recordsForm.down('[name=setIdentifier]'), {allowBlank: value === 'simple'});
                    }
                },
                name: 'policy'
            }, {
                fieldLabel: 'Weight',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                hidden: true,
                name: 'weightContainer',
                items: [{
                    xtype: 'numberfield',
                    flex: 1,
                    minValue: 0,
                    maxValue: 255,
                    name: 'weight'
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: recordsTooltips.weight
                }]
            }, {
                fieldLabel: 'Region',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                hidden: true,
                name: 'regionContainer',
                items: [{
                    xtype: 'combo',
                    flex: 1,
                    store: moduleParams['regions'],
                    editable: false,
                    emptyText: 'Select region',
                    name: 'region'
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: recordsTooltips.region
                }]
            }, {
                fieldLabel: 'Failover record type',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                hidden: true,
                name: 'failoverContainer',
                items:[{
                    xtype: 'buttongroupfield',
                    flex: 1,
                    value: 'primary',
                    defaults: {
                        width: 115
                    },
                    items: [
                        {
                            xtype: 'button',
                            text: 'Primary',
                            value: 'primary'
                        },
                        {
                            xtype: 'button',
                            text: 'Secondary',
                            value: 'secondary'
                        }
                    ],
                    name: 'failover',
                    listeners: {
                        change: function (field, value) {
                            recordsForm.changeSetId(value);
                        }
                    }
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    info: recordsTooltips.failover
                }],
                listeners: {
                    show: function () {
                        recordsForm.changeSetId(true);
                    },
                    hide: function () {
                        recordsForm.changeSetId(false);
                    }
                }
            }, {
                fieldLabel: 'Set ID',
                xtype: 'fieldcontainer',
                layout: 'hbox',
                hidden: true,
                name: 'setIdContainer',
                items: [{
                    xtype: 'textfield',
                    maxLength: 128,
                    flex: 1,
                    name: 'setIdentifier'
                }, {
                    xtype: 'displayinfofield',
                    margin: '0 0 0 6',
                    width: 20,
                    name: 'setIdTooltip'
                }]
            }, {
                fieldLabel: 'Health check to associate',
                labelWidth: 300,
                xtype: 'displayfield',
                hidden: true,
                name: 'healthIdLabel'
            }, {
                xtype: 'combo',
                store: healthChecksStore,
                /*
                store: {
                    fields: ['healthId', 'protocol', 'ipAddress', 'port', 'resourcePath', {
                        name: 'url',
                        convert: function (value, record) {
                            var protocol = record.get('protocol');
                            var ipAddress = record.get('ipAddress');
                            var port = record.get('port');
                            var path = record.get('resourcePath');
                            var url = '';

                            if (ipAddress) {
                                url = protocol + '://' + ipAddress;
                                url = port ? url + ':' + port : url;
                                url = path ? url + '/' + path : url;

                                return url.toLowerCase();
                            }

                            return value;
                        }
                    }],
                    data: moduleParams['healthChecks'],
                    proxy: 'object'
                },
                */
                displayField: 'url',
                valueField: 'healthId',
                editable: false,
                emptyText: 'Select health check to associate',
                //value: moduleParams['healthChecks'] && moduleParams['healthChecks'].length ? 'Do not associate with health check' : 'No health checks available',
                //disabled: !(moduleParams['healthChecks'] && moduleParams['healthChecks'].length),
                hidden: true,
                name: 'healthId'
                /*
                getSubmitValue: function () {
                    var me = this;

                    var value = me.getValue();

                    return value !== 'Do not associate with health check' ? value : null;
                }
                */
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            style: 'background-color: #f9fafb;',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                margin: '0 0 12 0',
                text: 'Edit',
                name: 'recordsFormSaveButton',
                handler: function() {
                    var fields = recordsForm.getValues();
                    fields.alias = fields.alias === 'true';

                    if (recordsForm.getForm().isValid()) {
                        var record = recordsForm.getRecord();

                        if (record) {
                            //var index = fields.name + fields.type;
                            //recordsGrid.recordsSnapshot[index] = recordsGrid.recordsSnapshot[index] || Ext.clone(record.data);

                            Ext.Object.each(fields, function (field, value) {
                                record.set(field, value);
                            });
                        } else {
                            recordsStore.add(fields);
                        }
                    }
                }
            }]
        }],

        listeners: {
            afterrender: function () {
                var me = this;

                var clearForm = function () {
                    me.hide();
                    me.getForm().reset(true);
                };

                recordsGrid.getSelectionModel().on('focuschange', function (gridSelModel, oldFocused, newFocused) {
                    if (newFocused) {
                        if (me.getRecord() !== newFocused) {
                            var recordSnapshot = newFocused.get('snapshot');
                            var isZoneExist = recordsGrid.down('[name=zoneComment]').isDisabled();

                            if (!recordSnapshot && isZoneExist) {
                                newFocused.set('snapshot', Ext.clone(newFocused.data));
                            }

                            me.loadRecord(newFocused);

                            var name = newFocused.get('name');
                            var type = newFocused.get('type');

                            var isRemovable = function (recordSetType, recordSetName, hostedZoneName) {
                                return !(recordSetType === 'SOA' || (recordSetType === 'NS' && recordSetName === hostedZoneName));
                            };

                            var zoneName = recordsGrid.down('[name=zoneName]').getValue();

                            me.isRecordSetRemovable = isRemovable(type, name, zoneName);

                            me.disableFields(!me.isRecordSetRemovable);

                            me.formatForm(newFocused.data, zoneName);

                            me.down('[name=recordsFormSaveButton]').setText('Edit');

                            me.down('[name=dnsName]').enable();

                            aliasTargetsStore.clearData();

                            if (!isZoneExist) {
                                recordsForm.down('[name=aliasContainer]').hide();
                            }

                            me.show();
                        }
                    } else {
                        clearForm();
                    }
                });

                recordsGrid.on('hide', function () {
                    clearForm();
                });

                recordsStore.on('load', function () {
                    clearForm();
                });
            }
        }
    });

    var recordsFormContainer = {
        xtype: 'container',
        flex: .6,
        layout: 'fit',
        cls: 'x-transparent-mask',
        style: 'background-color: #f9fafb;',
        minWidth: 500,
        maxWidth: 700,
        autoScroll: true,
        items: recordsForm
    };

    var panel = Ext.create('Ext.panel.Panel', {
        title: 'Tools &raquo; Amazon Web Services &raquo; Route53',

        autoScroll: true,

        //cls: 'scalr-ui-roles-manager',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        scalrOptions: {
            reload: false,
            maximize: 'all'
        },
        tools: [{
            xtype: 'favoritetool',
            favorite: {
                text: 'Route53',
                href: '#/tools/aws/route53'
            }
        }],

        items: [
            zonesGrid,
            recordsGridContainer,
            recordsFormContainer
        ],

        dockedItems: [
            {
                xtype: 'container',
                itemId: 'tabs',
                weight: 1,
                dock: 'left',
                cls: 'x-docked-tabs',
                width: 112,
                autoScroll: true,
                defaults: {
                    xtype: 'button',
                    ui: 'tab',
                    allowDepress: false,
                    iconAlign: 'above',
                    disableMouseDownPressed: true,
                    toggleGroup: 'route53-tabs',
                    toggleHandler: function (button, state) {
                        if (state) {
                            panel.fireEvent('itemselect', button.value);
                        }
                    }
                },
                items: [{
                    iconCls: 'x-icon-leftmenu-tools x-icon-leftmenu-tools-zones',
                    text: 'Hosted zones',
                    value: 'hostedZones',
                    pressed: true
                }, {
                    iconCls: 'x-icon-leftmenu-tools x-icon-leftmenu-tools-health-checks',
                    text: 'Health checks',
                    value: 'healthChecks'
                }]
            }
        ],

        listeners: {
            itemselect: function (item) {
                var me = this;

                me.removeAll(false);

                if (item === 'hostedZones') {
                    me.add([zonesGrid, recordsGridContainer, recordsFormContainer]);
                }

                if (item === 'healthChecks') {
                    if (!healthChecksStore.getCount()) {
                        healthChecksStore.load();
                    }
                    me.add([healthChecksGrid, healthFormContainer]);
                }
            }
        }
    });

    return panel;
});
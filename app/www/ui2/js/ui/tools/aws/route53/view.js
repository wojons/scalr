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
        remoteSort: true,
        autoLoad: true,
        listeners: {
            load: function () {
                zonesForm.hide();
                recordsGrid.hide();
                recordsForm.hide();
                recordsGridContainer.getDockedItems('container')[0].hide();
            }
        }
    });

    var recordsStore = Ext.create('store.store', {
        model: Scalr.getModel({
            fields: [
                'name',
                'type',
                'resourceRecord',
                'evaluateTargetHealth',
                'healthId',
                'ttl',
                'region',
                'weight',
                'setIdentifier',
                'dnsName',
                'aliasZoneId',
                'policy',
                'alias',
                'failover'
            ]
        }),

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

                recordsGridContainer.down('[name=saveZoneButton]').disable();
            },

            datachanged: function () {
                recordsGrid.getSelectionModel().deselectAll();
                recordsGridContainer.down('[name=saveZoneButton]').enable();
            },

            update: function (store, record, operation, modifiedFieldNames) {
                if (modifiedFieldNames.length === 1 && modifiedFieldNames[0] === 'snapshot') {
                    return;
                }

                recordsGrid.getSelectionModel().deselectAll();
                recordsGridContainer.down('[name=saveZoneButton]').enable();
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
        autoLoad: true,
        listeners: {
            load: function () {
                healthChecksForm.hide();
                healthChecksForm.getForm().reset(true);
            }
        }
    });

    var zonesGrid = Ext.create('Ext.grid.Panel', {
        flex: 1.2,
        cls: 'x-panel-column-left',
        store: zonesStore,
        minWidth: 565,

        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'focusedrowpointer'
        }, {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true
        }],

        viewConfig: {
            emptyText: 'No hosted zones found',
            loadingText: 'Loading hosted zones...'
        },

        selModel: Scalr.isAllowed('AWS_ROUTE53', 'manage') ? 'selectedmodel' : null,

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
            { header: "Hosted zone", flex: 1, dataIndex: 'name', sortable: true },
            { header: "Comment", flex: 1, dataIndex: 'comment', sortable: true }
        ],

        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                store: zonesStore,
                flex: 1,
                maxWidth: 220,
                margin: 0
            }, {
                xtype: 'tbfill',
                flex: 0.01
            }, {
                text: 'New zone',
                name: 'addZoneButton',
                cls: 'x-btn-green',
                hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
                handler: function() {
                    zonesGrid.clearSelectedRecord();

                    zonesForm.enable().show();

                    recordsGrid.down('#refresh').disable();
                    recordsGrid.down('filterfield').disable();

                    zonesForm.down('[name=zoneId]').disable().hide();
                    zonesForm.down('[name=name]').enable();
                    zonesForm.down('[name=comment]').enable();

                    recordsGrid.down('[name=addRecordButton]').disable();

                    recordsGrid.disableColumnHeaders();

                    recordsStore.removeAll();

                    recordsGridContainer.down('[name=saveZoneButton]').disable();

                    recordsGrid.show();
                    recordsGridContainer.getDockedItems('container')[0].show();
                }
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function() {
                    zonesStore.load();
                }
            }, {
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more hosted zone(s) to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
                handler: function () {
                    var params = zonesGrid.getSelectedZonesIds();
                    zonesGrid.removeHostedZones(params);
                }
            }]
        }]
    });

    var zonesForm = Ext.create('Ext.form.Panel', {
        listeners: {
            afterloadrecord: function (record) {
                var me = this;

                me.disable();
                me.down('[name=zoneId]').enable().show();

                recordsGrid.down('#refresh').enable();
                recordsGrid.down('filterfield').enable();
                recordsGrid.down('[name=addRecordButton]').enable();
                recordsGrid.enableColumnHeaders();

                var zoneId = record.get('zoneId');
                var cache = recordsGrid.recordsCache[zoneId];

                recordsStore.getProxy().extraParams = {
                    zoneId: record.get('zoneId')
                };

                if (!cache) {
                    recordsStore.load();
                } else {
                    recordsStore.loadData(cache);
                }

                recordsGrid.show();
                recordsGridContainer.getDockedItems('container')[0].show();
            }
        },

        items: [{
            xtype: 'fieldset',
            title: 'Hosted zone details',
            cls: 'x-fieldset-separator-none',
            defaults: {
                anchor: '100%',
                labelWidth: 120
            },
            items: [{
                xtype: 'hiddenfield',
                name: 'id',
                submitValue: false
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Zone ID',
                name: 'zoneId'
            }, {
                xtype: 'textfield',
                fieldLabel: 'Domain name',
                allowBlank: false,
                regex: /^(?!:\/\/)([a-zA-Z0-9]+\.)?[a-zA-Z0-9][a-zA-Z0-9-]+\.[a-zA-Z]{2,6}?$/i,
                name: 'name',
                listeners: {
                    validitychange: function (field, isValid) {
                        var addRecordButton = recordsGrid.down('[name=addRecordButton]');
                        var saveZoneButton = recordsGridContainer.down('[name=saveZoneButton]');
                        saveZoneButton.enable();

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
                name: 'comment'
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Record sets'
            }]
        }]
    });

    var recordsGrid = Ext.create('Ext.grid.Panel', {
        store: recordsStore,
        padding: '0 24',

        height: 500,
        scrollable: true,

        hidden: true,

        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'focusedrowpointer',
            addCls: 'x-panel-row-pointer-light'
        }, {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            getForm: function () {
                return recordsForm;
            }
        }],

        viewConfig: {
            preserveScrollOnRefresh: true,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No Record Sets found.',
                emptyTextNoItems: 'You have no Record Sets added yet.'
            },
            loadingText: 'Loading Record Sets...',
            deferEmptyText: false
        },

        selModel:
            Scalr.isAllowed('AWS_ROUTE53', 'manage') ?
            {
                selType: 'selectedmodel',

                getVisibility: function (record) {
                    return recordsGrid.isRecordSetRemovable(record.get('type'), record.get('name'));
                }
            } : null,

        recordsCache: {},
        recordsSnapshot: {},

        isRecordSetRemovable: function (recordSetType, recordSetName) {
            return !(
                recordSetType === 'SOA'
                || (recordSetType === 'NS' && recordSetName === zonesForm.down('[name=name]').getValue())
            );
        },

        listeners: {
            afterrender: function () {
                var me = this;

                zonesGrid.getSelectionModel().on('focuschange', function (gridSelModel, oldFocused, newFocused) {
                    return;//qwerty
                    if (newFocused) {
                        var zoneData = newFocused.data;

                        var domainName = me.down('[name=zoneName]');
                        domainName.setValue(zoneData.name).disable();

                        var comment = me.down('[name=comment]');
                        comment.setValue(zoneData['comment']).disable();

                        me.down('[name=zoneId]').setValue(zoneData.zoneId).show();

                        me.down('#refresh').enable();
                        me.down('[name=addRecordButton]').enable();

                        var zoneId = zoneData.zoneId;
                        var cache = me.recordsCache[zoneId];

                        recordsStore.getProxy().extraParams = {
                            zoneId: zoneId
                        };

                        if (!cache) {
                            recordsStore.load();
                        } else {
                            recordsStore.loadData(cache);
                        }

                        me.show();
                    } /*else {
                        me.hide();
                        me.getSelectionModel().clearSelections();
                    }*/
                });
            },

            selectionchange: function (selModel, selections) {
                var me = this;

                var toolbar = me.down('toolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
            }

            /*
            beforeselect: function (grid, record) {
                var me = this;

                return me.isRecordSetRemovable(record.get('type'), record.get('name'));
            }
            */
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

            var action = function () {
                var action = '';

                if (recordParams.name === zonesForm.down('[name=name]').getValue()) {
                    action = 'UPSERT';
                } else {
                    action = !oldRecordSet ? 'CREATE' : 'UPSERT';
                }

                return action;
            }();

            var request = {
                processBox: {
                    type: 'save'
                },
                scope: this,
                params: {
                    dnsName: null,
                    zoneId: recordsStore.getProxy().extraParams.zoneId,
                    action: action,
                    oldRecordSet: oldRecordSetParams
                },
                url: '/tools/aws/route53/recordsets/xSave',
                success: function () {
                    recordsStore.load();

                    zonesGrid.clearSelectedRecord();
                    recordsGrid.hide();
                    recordsForm.hide();
                    recordsGridContainer.getDockedItems('container')[0].hide();

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
                        recordsStore.load();

                        zonesGrid.clearSelectedRecord();
                        recordsGrid.hide();
                        recordsForm.hide();
                        recordsGridContainer.getDockedItems('container')[0].hide();
                    }
                }
            });
        },

        columns: [
            { header: "Record set", flex: 1, dataIndex: 'name', sortable: true },
            { header: "Type", width: 75, dataIndex: 'type', sortable: true },
            { header: "Alias", width: 75, dataIndex: 'alias', align: 'center', xtype: 'templatecolumn',
                tpl: [
                    '<tpl if="alias">',
                        '<div class="x-grid-icon x-grid-icon-simple x-grid-icon-ok"></div>',
                    '<tpl else>',
                        '&mdash;',
                    '</tpl>'
                ]
            }
        ],

        dockedItems: [{
            xtype: 'container',
            dock: 'top',
            autoScroll: true,

            layout: 'vbox',
            width: '100%',
            defaults: {
                width: '100%'
            },
            items: [{
                xtype: 'toolbar',
                ui: 'simple',
                padding: '0 0 12 0',
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
                    cls: 'x-btn-green',
                    hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
                    handler: function() {
                        var zoneNameField = zonesForm.down('[name=name]');

                        recordsGrid.clearSelectedRecord();

                        //recordsForm.getForm().reset(true);
                        //recordsForm.disableFields(false);
                        recordsForm.down('[name=hostedZoneName]').setValue(zoneNameField.getValue());
                        recordsForm.down('[name=recordsFormSaveButton]').setText('Add');
                        recordsForm.show();

                        if (!zoneNameField.isDisabled()) {
                            zoneNameField.disable();
                        }

                        recordsForm.down('[name=name]').enable();
                        recordsForm.down('[name=type]').enable();
                        recordsForm.down('[name=alias]').setVisible(zoneNameField.isDisabled());
                    }
                }, {
                    itemId: 'refresh',
                    iconCls: 'x-btn-icon-refresh',
                    tooltip: 'Refresh',
                    handler: function() {
                        recordsGrid.getSelectionModel().clearSelections();

                        recordsForm.hide();
                        recordsForm.getForm().reset(true);


                        recordsStore.load();
                    }
                }, {
                    itemId: 'delete',
                    iconCls: 'x-btn-icon-delete',
                    cls: 'x-btn-red',
                    tooltip: 'Select one or more record set(s) to delete them',
                    disabled: true,
                    hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
                    handler: function () {
                        recordsForm.hide();
                        recordsForm.getForm().reset(true);

                        var selectedRecordSets = recordsGrid.getSelectionModel().getSelection();
                        recordsStore.remove(selectedRecordSets);
                    }
                }]
            }]
        }]
    });

    var recordsGridContainer = Ext.create('Ext.panel.Panel', {
        flex: .8,
        minWidth: 500,
        maxWidth: 900,
        cls: 'x-transparent-mask',
        items: [ zonesForm, recordsGrid ],
        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            hidden: true,
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [
                {
                    xtype: 'button',
                    name: 'saveZoneButton',
                    text: 'Save',
                    hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
                    handler: function () {
                        var zoneCommentField = zonesForm.down('[name=comment]');

                        if (!zoneCommentField.isDisabled()) {
                            var zoneNameField = zonesForm.down('[name=name]');

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
                    hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
                    handler: function () {
                        zonesGrid.clearSelectedRecord();
                        recordsGrid.hide();
                        recordsForm.hide();
                        recordsGridContainer.getDockedItems('container')[0].hide();
                    }
                }
            ]
        }]
    });

    var healthChecksGrid = Ext.create('Ext.grid.Panel', {
        flex: 1.2,
        cls: 'x-panel-column-left',
        store: healthChecksStore,
        padding: '0 0 12 0',

        selModel: Scalr.isAllowed('AWS_ROUTE53', 'manage') ? 'selectedmodel' : null,

        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'focusedrowpointer'
        }, {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true
        }],

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
                    healthId: Ext.encode(healthId)
                },
                success: function () {
                    healthChecksStore.load();
                }
            });
        },

        columns: [
            { header: "Url", flex: 1, itemId: 'healthUrl', xtype: 'templatecolumn', sortable: false,
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
            { header: "Host name", flex: 1, xtype: 'templatecolumn', dataIndex: 'hostName', sortable: true,
                tpl: [
                    '<tpl if="hostName">',
                        '{hostName}',
                    '<tpl else>',
                        '&mdash;',
                    '</tpl>'
                ]
            },
            { header: "ID", flex: 1, dataIndex: 'healthId', sortable: false }
        ],

        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                store: healthChecksStore,
                flex: 1,
                maxWidth: 220,
                margin: 0
            }, {
                xtype: 'tbfill',
                flex: 0.01
            }, {
                text: 'New health check',
                hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
                cls: 'x-btn-green',
                handler: function() {
                    healthChecksGrid.clearSelectedRecord();

                    healthChecksForm
                        .setReadOnly(false)
                        .show();
                }
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function() {
                    healthChecksStore.load();
                }
            }, {
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more health check(s) to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
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

        setReadOnly: function (readOnly) {
            var me = this;

            me.getForm().getFields().each(function (field) {
                field.setReadOnly(readOnly);
            });

            me.down('#save').setDisabled(readOnly);

            return me;
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

            afterloadrecord: function () {
                this.setReadOnly(true);
            }
        },

        items: [{
            xtype: 'fieldset',
            title: 'Health check details',
            defaults: {
                labelWidth: 120,
                margin: '0 20 8 0'
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
                labelWidth: 120,
                margin: '0 20 8 0'
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
                xtype: 'numberfield',
                fieldLabel: 'Failure threshold',
                flex: 1,
                value: 3,
                minValue: 1,
                maxValue: 10,
                editable: false,
                name: 'failureThreshold',
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: healthTooltips.failureThreshold
                    }
                }]
            }]
        }, {
            xtype: 'fieldset',
            defaults: {
                labelWidth: 275,
                margin: '0 20 8 0'
            },
            name: 'stringMatchingSet',
            items: [{
                xtype: 'buttongroupfield',
                fieldLabel: 'Enable string matching',
                flex: 1,
                value: false,
                defaults: {
                    width: '50%'
                },
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: healthTooltips.failureThreshold
                    }
                }],
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
                labelWidth: 120,
                margin: '0 20 8 0'
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
            hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [{
                xtype: 'button',
                itemId: 'save',
                text: 'Save',
                handler: function() {
                    healthChecksForm.saveHealthCheck();
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    healthChecksGrid.clearSelectedRecord();
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
        bodyStyle: 'background-color: white',
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
            me.down('[name=alias]').setVisible(!disable);
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
            var hostedZoneName = zonesForm.down('[name=name]').getValue();

            if (name) {
                hostedZoneNameField.setValue(name + '.' + hostedZoneName);
            } else {
                hostedZoneNameField.setValue(hostedZoneName);
            }
        },

        updateSetId: function (name) {
            var me = this;

            var failoverField = me.down('[name=failover]');

            if (failoverField.isVisible()) {
                var failoverType = failoverField.getValue();

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
                xtype: 'hiddenfield',
                name: 'id',
                submitValue: false
            }, {
                xtype: 'textfield',
                fieldLabel: 'Name',
                cls: 'x-grid-editor',
                flex: 1,
                name: 'name',
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: recordsTooltips.name
                    }
                }],
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
                xtype: 'displayfield',
                margin: '0 26 6 126',
                fieldStyle: 'text-align: right',
                name: 'hostedZoneName'
            }, {
                xtype: 'combo',
                cls: 'x-grid-editor',
                fieldLabel: 'Type',
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
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: recordsTooltips.type
                    }
                }],
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
            }]
        }, {
            xtype: 'fieldset',
            cls: 'x-fieldset-light-separator-bottom',
            defaults: {
                labelWidth: 120
            },

            items: [{
                xtype: 'buttongroupfield',
                fieldLabel: 'Alias',
                flex: 1,
                value: false,
                name: 'alias',
                defaults: {
                    width: '25%'
                },
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: recordsTooltips.alias
                    }
                }],
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
                        var fields = ['ttl', 'resourceRecord', 'resourceRecordDescription', 'aliasTarget', 'dnsName', 'evaluateTargetHealth'];

                        recordsForm.toggleFields(fields);

                        Ext.apply(recordsForm.down('[name=resourceRecord]'), {allowBlank: value});
                        Ext.apply(recordsForm.down('[name=dnsName]'), {allowBlank: !value});
                    }
                }
            }, {
                xtype: 'numberfield',
                fieldLabel: 'TTL (seconds)',
                cls: 'x-grid-editor',
                flex: 1,
                step: 60,
                value: 300,
                name: 'ttl',
                readOnly: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: recordsTooltips.ttl
                    }
                }]
            }, {
                fieldLabel: 'Value',
                xtype: 'textareafield',
                cls: 'x-grid-editor',
                allowBlank: false,
                name: 'resourceRecord',
                readOnly: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
                getSubmitValue: function () {
                    var me = this;

                    return me.getValue().split('\n');
                }
            }, {
                xtype: 'displayfield',
                margin: '0 0 0 126',
                name: 'resourceRecordDescription',
                value: recordsTooltips.resourceRecord['A']
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Alias target',
                hidden: true,
                name: 'aliasTarget',
                flex: 1,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: recordsTooltips.aliasTarget
                    }
                }]
            }, {
                xtype: 'combo',
                cls: 'x-grid-editor',
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
                cls: 'x-grid-editor',
                labelWidth: 145,
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
                readOnly: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
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
                        recordsForm.toggleFields(['weight', 'region', 'failover', 'setIdentifier', 'healthId', 'healthIdLabel'], true);

                        if (value !== 'simple') {
                            recordsForm.toggleFields([value, 'healthId', 'healthIdLabel', 'setIdentifier']);
                        }

                        var setIdField = recordsForm.down('[name=setIdentifier]');
                        setIdField.getPlugin('fieldicons').
                            updateIconTooltip('info', recordsTooltips.setId[value]);

                        Ext.apply(recordsForm.down('[name=weight]'), {allowBlank: true});
                        Ext.apply(recordsForm.down('[name=region]'), {allowBlank: true});

                        Ext.apply(recordsForm.down('[name=' + value + ']'), {allowBlank: false});
                        Ext.apply(setIdField, {allowBlank: value === 'simple'});
                    }
                },
                name: 'policy'
            }, {
                xtype: 'numberfield',
                cls: 'x-grid-editor',
                fieldLabel: 'Weight',
                flex: 1,
                minValue: 0,
                maxValue: 255,
                name: 'weight',
                hidden: true,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: recordsTooltips.weight
                    }
                }]
            }, {
                xtype: 'combo',
                cls: 'x-grid-editor',
                fieldLabel: 'Region',
                flex: 1,
                store: moduleParams['regions'],
                editable: false,
                emptyText: 'Select region',
                name: 'region',
                hidden: true,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: recordsTooltips.region
                    }
                }]
            }, {
                xtype: 'buttongroupfield',
                fieldLabel: 'Failover record type',
                flex: 1,
                value: 'primary',
                hidden: true,
                defaults: {
                    width: 115
                },
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: recordsTooltips.failover
                    }
                }],
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
                    show: function () {
                        recordsForm.changeSetId(true);
                    },
                    hide: function () {
                        recordsForm.changeSetId(false);
                    },
                    change: function (field, value) {
                        recordsForm.changeSetId(value);
                    }
                }
            }, {
                xtype: 'textfield',
                cls: 'x-grid-editor',
                fieldLabel: 'Set ID',
                maxLength: 128,
                flex: 1,
                name: 'setIdentifier',
                hidden: true,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: ''
                    }
                }]
            }, {
                fieldLabel: 'Health check to associate',
                labelWidth: 300,
                xtype: 'displayfield',
                hidden: true,
                name: 'healthIdLabel'
            }, {
                xtype: 'combo',
                cls: 'x-grid-editor',
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
            //style: 'background-color: #f9fafb;',
            style: 'background-color: white',
            hidden: !Scalr.isAllowed('AWS_ROUTE53', 'manage'),
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults: {
                flex: 1,
                maxWidth: 140
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

                        recordsForm.hide();
                    }
                }
            }]
        }],

        listeners: {
            afterloadrecord: function (record) {
                var me = this;

                var isDefault = !recordsGrid.isRecordSetRemovable(
                    record.get('type'),
                    record.get('name')
                );

                me.down('[name=name]').setDisabled(isDefault);
                me.down('[name=type]').setDisabled(isDefault);
                me.down('[name=alias]').setVisible(!isDefault);
                me.down('[name=recordsFormSaveButton]').setText('Edit');
            },

            afterrender: function () {
                return;
                var me = this;

                var clearForm = function () {
                    me.hide();
                    me.getForm().reset(true);
                };

                recordsGrid.getSelectionModel().on('selectionchange', function (selectionModel, selected) {
                    var record = selected[0];

                    if (record) {
                        if (me.getRecord() !== record) {
                            var recordSnapshot = record.get('snapshot');
                            var isZoneExist = zonesForm.down('[name=comment]').isDisabled();

                            if (!recordSnapshot && isZoneExist) {
                                record.set('snapshot', Ext.clone(record.data));
                            }

                            me.loadRecord(record);

                            var name = record.get('name');
                            var type = record.get('type');

                            var isRemovable = function (recordSetType, recordSetName, hostedZoneName) {
                                return !(recordSetType === 'SOA' || (recordSetType === 'NS' && recordSetName === hostedZoneName));
                            };

                            var zoneName = zonesForm.down('[name=name]').getValue();

                            me.isRecordSetRemovable = isRemovable(type, name, zoneName);

                            me.disableFields(!me.isRecordSetRemovable);

                            me.formatForm(record.data, zoneName);

                            me.down('[name=recordsFormSaveButton]').setText('Edit');

                            me.down('[name=dnsName]').enable();

                            aliasTargetsStore.clearData();

                            if (!isZoneExist) {
                                recordsForm.down('[name=alias]').hide();
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
        style: 'background-color: white',
        minWidth: 540,
        maxWidth: 700,
        autoScroll: true,
        items: recordsForm
    };

    var panel = Ext.create('Ext.panel.Panel', {
        scrollable: 'x',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'AWS Route 53',
            menuHref: '#/tools/aws/route53',
            menuFavorite: true
        },

        stateful: true,
        stateId: 'grid-tools-aws-route53-view',

        items: [
            zonesGrid,
            recordsGridContainer,
            recordsFormContainer
        ],

        dockedItems: [{
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
                iconAlign: 'top',
                disableMouseDownPressed: true,
                toggleGroup: 'route53-tabs',
                toggleHandler: function (button, state) {
                    if (state) {
                        panel.fireEvent('itemselect', button.value);
                    }
                }
            },
            items: [{
                iconCls: 'x-icon-leftmenu x-icon-leftmenu-hostedzones',
                text: 'Hosted zones',
                value: 'hostedZones',
                pressed: true
            }, {
                iconCls: 'x-icon-leftmenu x-icon-leftmenu-healthchecks',
                text: 'Health checks',
                value: 'healthChecks'
            }]

        }],

        listeners: {
            itemselect: function (item) {
                var me = this;

                me.removeAll(false);

                me.add(item === 'hostedZones'
                    ? [ zonesGrid, recordsGridContainer, recordsFormContainer ]
                    : [ healthChecksGrid, healthFormContainer ]
                );

                return true;
            }
        }
    });

    return panel;
});
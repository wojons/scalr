Scalr.regPage('Scalr.ui.discoverymanager.servers.view', function (loadParams, moduleParams) {
    var platformsTabs = [], allowToImport = false;
    loadParams['platform'] = loadParams['platform'] || moduleParams['allowedPlatforms'][0];

    Ext.each(moduleParams['allowedPlatforms'], function (platformId) {
        platformsTabs.push({
            text: Scalr.utils.getPlatformName(platformId, true),
            iconCls: 'x-icon-platform-large x-icon-platform-large-' + platformId,
            value: platformId,
            pressed: platformId == loadParams['platform']
        });
    });

    Ext.each(['FARMS', 'OWN_FARMS', 'TEAM_FARMS'], function (resource) {
        allowToImport = allowToImport || Scalr.isAllowed(resource, 'update') && Scalr.isAllowed(resource, 'servers');
    });

    var store = Ext.create('Scalr.ui.ContinuousStore', {
        proxy: {
            type: 'ajax',
            url: '/discoverymanager/servers/xList',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },

        fields: [{
                name: 'cloudServerId',
                type: 'string'
            }, {
                name: 'privateIp',
                type: 'string'
            }, {
                name: 'publicIp',
                type: 'string'
            }, {
                name: 'imageId',
                type: 'string'
            }, {
                name: 'status',
                type: 'string'
            }, {
                name: 'subnetId',
                type: 'string'
            }, {
                name: 'keyPairName',
                type: 'string'
            }, {
                name: 'vpcId',
                type: 'string'
            },

            'launchTime',
            'securityGroups',
            'tags',
        ]
    });

    var grid = Ext.create('Ext.grid.Panel', {
        flex: 1,
        cls: 'x-panel-column-left',

        store: store,

        plugins: [{
                ptype: 'applyparams',
                filterIgnoreParams: [ 'platform' ],
                loadStoreOnReturn: false
            }, {
                ptype: 'selectedrecord',
                disableSelection: false,
                clearOnRefresh: true,
                selectSingleRecord: true
            },
            'focusedrowpointer',
            'continuousrenderer'
        ],
        selModel: {
            selType: 'selectedmodel',
            getVisibility: function (record) {
                return record.get('status') == 'running';
            }
        },

        viewConfig: {
            emptyText: 'No orphaned servers found.'
        },

        listeners: {
            selectionchange: function(selModel, selected) {
                var errorMsg = '',
                    required = {imageId: [], vpcId: [], subnetId: []},
                    names = {imageId: 'AMI', vpcId: 'VPC', subnetId: 'VPC subnet'},
                    optional = {instanceType: []},
                    errors = [], i, value = [];

                if (selected.length) {
                    Ext.each(selected, function (record) {
                        if (record.get('status') != 'running') {
                            errorMsg = 'Non-running server(s) are not allowed to import';
                        }
                    });

                    if (!errorMsg) {
                        Ext.each(selected, function (record) {
                            for (i in required) {
                                required[i].push(record.get(i));
                            }

                            for (i in optional) {
                                optional[i].push(record.get(i));
                            }
                        });

                        for (i in required) {
                            required[i] = Ext.Array.unique(required[i]);
                            if (required[i].length > 1) {
                                errors.push(i);
                            }
                        }

                        for (i in optional) {
                            optional[i] = Ext.Array.unique(optional[i]);
                        }

                        if (errors.length) {
                            errorMsg = 'Instances must have the same ' + errors.join(', ');
                        }
                    }
                }

                this.down('#import').setDisabled(!selected.length || errorMsg).setTooltip(errorMsg);
            }
        },

        columns: [{
            header: 'Cloud Server ID',
            dataIndex: 'cloudServerId',
            sortable: true,
            flex: 3
        }, {
            header: 'Cloud Image ID',
            dataIndex: 'imageId',
            sortable: true,
            flex: 2
        }, {
            header: 'VPC',
            xtype: 'templatecolumn',
            sortable: false,
            flex: 2,
            tpl: [
                '<tpl if="!Ext.isEmpty(vpcId)">',
                '{vpcId}',
                '<tpl else>',
                '&mdash;',
                '</tpl>'
            ]
        }, {
            header: 'Subnet',
            xtype: 'templatecolumn',
            sortable: false,
            flex: 2,
            tpl: [
                '<tpl if="!Ext.isEmpty(subnetId)">',
                '{subnetId}',
                '<tpl else>',
                '&mdash;',
                '</tpl>'
            ]
        }, {
            header: 'Public IP',
            xtype: 'templatecolumn',
            sortable: false,
            width: 120,
            tpl: [
                '<tpl if="!Ext.isEmpty(publicIp)">',
                    '{publicIp}',
                '<tpl else>',
                    '&mdash;',
                '</tpl>'
            ]
        }, {
            header: 'Private IP',
            xtype: 'templatecolumn',
            sortable: false,
            width: 120,
            tpl: [
                '<tpl if="!Ext.isEmpty(privateIp)">',
                    '{privateIp}',
                '<tpl else>',
                    '&mdash;',
                '</tpl>'
            ]
        }],

        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
            store: store,
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                store: store,
                margin: 0,
                form: {
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'Cloud image id',
                        labelAlign: 'top',
                        name: 'imageId'
                    }, {
                        xtype: 'textfield',
                        fieldLabel: 'VPC ID',
                        labelAlign: 'top',
                        name: 'vpcId'
                    }, {
                        xtype: 'textfield',
                        fieldLabel: 'VPC subnet',
                        labelAlign: 'top',
                        name: 'subnetId'
                    }]
                }
            }, {
                xtype: 'cloudlocationfield',
                platforms: [ 'ec2' ],
                gridStore: store
            }, {
                xtype: 'tbfill',
                flex: 1
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.clearAndLoad();
                }
            }, {
                itemId: 'import',
                text: 'Import',
                disabled: true,
                hidden: !Scalr.isAllowed('DISCOVERY_SERVERS', 'import') || !allowToImport,
                cls: 'x-btn-green',
                handler: function () {
                    Scalr.event.fireEvent('redirect', '#/servers/import?' + Ext.Object.toQueryString({
                        platform: loadParams['platform'],
                        cloudLocation: loadParams['cloudLocation'],
                        ids: Ext.encode(Ext.Array.map(grid.getSelectionModel().getSelection(), function(rec) {
                            return rec.get('cloudServerId');
                        }))
                    }));
                }
            }]
        }],

        getPlatform: function () {
            var me = this;

            return me.down('#platform')
                .getValue();
        },

        getCloudLocation: function () {
            var me = this;

            return me.down('#cloudLocation')
                .getValue();
        }
    });

    var form = Ext.create('Ext.form.Panel', {

        hidden: true,
        scrollable: true,

        layout: {
            type: 'vbox',
            align: 'stretch'
        },

        fieldDefaults: {
            anchor: '100%'
        },

        listeners: {
            afterloadrecord: function (record) {
                var me = this;

                me
                    .setHeader(record.get('cloudServerId'))
                    .setAmiId(
                        record.get('imageId'),
                        record.get('imageHash'),
                        record.get('imageName')
                    )
                    .setTags(record.get('tags'))
                    .disableButtons(
                        record.get('status') !== 'running'
                    );

                return true;
            }
        },

        getSelectedServersId: function () {
            return this.getRecord()
                .get('cloudServerId');
        },

        setHeader: function (header) {
            var me = this;

            me.down('fieldset')
                .setTitle(header);

            return me;
        },

        setTags: function (tags) {
            var me = this;

            var tagsStore = me.down('#tagsGrid').getStore();
            tagsStore.removeAll();
            tagsStore.loadData(tags);

            return me;
        },

        setAmiId: function (imageId, imageHash, imageName) {
            var me = this;

            me.down('[name=imageId]')
                .setValue(
                    Ext.isEmpty(imageHash)
                        ? imageId
                        : '<a href="#/images?hash=' + imageHash + '">' + imageName + '</a>'
                );

            return me;
        },

        disableButtons: function (disabled) {
            var me = this;

            Ext.Array.each(
                me.down('#buttons').query('button'),
                function (button) {
                    button.setDisabled(disabled);
                }
            );

            return me;
        },

        redirectToImport: function (createImage) {
            var me = this;

            Scalr.event.fireEvent('redirect',
                '#/roles/import?' + (!createImage ? '' : 'image&') + Ext.Object.toQueryString({
                    platform: grid.getPlatform(),
                    cloudLocation: grid.getCloudLocation(),
                    cloudServerId: me.getSelectedServersId()
                })
            );

            return me;
        },

        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            headerCls: 'x-fieldset-separator-bottom',
            fieldDefaults: {
                anchor: '100%'
            },
            defaults: {
                xtype: 'displayfield',
                labelWidth: 130
            },
            items: [{
                fieldLabel: 'Status',
                name: 'status',
                getStatusName: function (status) {
                    return {
                        'pending': 'Pending',
                        'running': 'Running',
                        'shutting-down': 'Shutting down',
                        'terminated': 'Terminated',
                        'stopping': 'Stopping',
                        'stopped': 'Stopped'
                    }[status];
                },
                getStatusColor: function (status) {
                    return {
                        'pending': '#e87a18',
                        'running': '#008000',
                        'shutting-down': '#999',
                        'terminated': '#999',
                        'stopping': '#999',
                        'stopped': '#999'
                    }[status];
                },
                renderer: function (value) {
                    var me = this;

                    var statusName = me.getStatusName(value);

                    return Ext.isDefined(statusName)
                        ? '<b style="color:' + me.getStatusColor(value) + '">' + statusName + '</b>'
                        : value;
                }
            }, {
                fieldLabel: 'Launch time',
                name: 'launchTime'
            }, {
                fieldLabel: 'AMI ID',
                name: 'imageId'
            }, {
                fieldLabel: 'Public IP',
                name: 'publicIp',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }, {
                fieldLabel: 'Private IP',
                name: 'privateIp',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }, {
                fieldLabel: 'VPC ID',
                name: 'vpcId',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }, {
                fieldLabel: 'Subnet ID',
                name: 'subnetId',
                renderer: function (value) {
                    return !Ext.isEmpty(value) ? value : '&mdash;';
                }
            }, {
                fieldLabel: 'Key pair name',
                name: 'keyPairName'
            }, {
                fieldLabel: 'Security groups',
                name: 'securityGroups',
                renderer: function (value) {
                    if (!Ext.isEmpty(value)) {
                        var cloudLocation = grid.getCloudLocation();

                        return Ext.Array.map(value, function (securityGroup) {
                            return '<a class="scalr-ui-rds-tagfield-sg-name" data-id="'
                                + securityGroup.groupId + '">' + securityGroup.groupName + '</a>';
                        }).join(', ');
                    }

                    return '&mdash;';
                },
                showSecurityGroupInfo: function (securityGroupInfo, accountId, remoteAddress) {
                    Scalr.Confirm({
                        formWidth: 950,
                        formLayout: 'fit',
                        alignTop: true,
                        winConfig: {
                            autoScroll: false,
                            layout: 'fit'
                        },
                        form: [{
                            xtype: 'sgeditor',
                            vpcIdReadOnly: true,
                            accountId: accountId,
                            remoteAddress: remoteAddress,
                            listeners: {
                                afterrender: function () {
                                    this.setValues(securityGroupInfo);
                                }
                            }
                        }],
                        ok: 'Save',
                        closeOnSuccess: true,
                        success: function (formValues, form) {
                            var formPanel = form.up('#box');
                            var values = formPanel.down('sgeditor')
                                    .getValues();

                            if (values !== false) {
                                values.returnData = true;
                                Scalr.Request({
                                    processBox: {
                                        type: 'save'
                                    },
                                    url: '/security/groups/xSave',
                                    params: values,
                                    success: function (data) {
                                        formPanel.destroy();
                                    }
                                });
                            }
                        }
                    });
                },
                listeners: {
                    afterrender: {
                        fn: function (field) {
                            field.getEl().on('click', function(event, target) {
                                target = Ext.get(target);

                                if (target.hasCls('scalr-ui-rds-tagfield-sg-name')) {
                                    Scalr.Request({
                                        processBox: {
                                            type: 'load'
                                        },
                                        url: '/security/groups/xGetGroupInfo',
                                        params: {
                                            platform: 'ec2',
                                            cloudLocation: grid.getCloudLocation(),
                                            securityGroupId: target.getAttribute('data-id')
                                        },
                                        success: function (response) {
                                            field.showSecurityGroupInfo(
                                                response,
                                                moduleParams.accountId,
                                                moduleParams.remoteAddress
                                            );
                                        }
                                    });
                                }
                            });
                        },

                        single: true
                    }
                }
            }, {
                fieldLabel: 'Tags'
            }]
        }, {
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            style: 'margin-top: -10px',
            flex: 1,
            minHeight: 150,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'grid',
                itemId: 'tagsGrid',
                trackMouseOver: false,
                disableSelection: true,
                flex: 1,
                store: {
                    proxy: 'object',
                    fields: [{
                        name: 'key',
                        type: 'string'
                    }, {
                        name: 'value',
                        type: 'string'
                    }]
                },
                viewConfig: {
                    emptyText: 'No tags found.',
                    deferEmptyText: false
                },
                columns: [{
                    header: 'Key',
                    dataIndex: 'key',
                    flex: 0.6
                }, {
                    header: 'Value',
                    dataIndex: 'value',
                    flex: 1
                }],
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            itemId: 'buttons',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            maxWidth: 1000,
            defaults: {
                flex: 1,
                maxWidth: 140
            },
            items: [{
                xtype: 'button',
                text: 'Create Image',
                handler: function () {
                    form.redirectToImport(true);
                }
            }, {
                xtype: 'button',
                text: 'Create Role',
                handler: function() {
                    form.redirectToImport();
                }
            }]
        }]
    });

    return Ext.create('Ext.panel.Panel', {
        stateful: true,
        stateId: 'grid-discoverymanager-servers-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Discovery manager',
            menuHref: '#/discoverymanager/servers',
            menuFavorite: true
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: 0.5,
            maxWidth: 600,
            minWidth: 400,
            layout: 'fit',
            items: [ form ]
        }],

        dockedItems: [{
            xtype: 'container',
            itemId: 'platforms',
            dock: 'left',
            cls: 'x-docked-tabs',
            width: 110 + Ext.getScrollbarSize().width,
            overflowY: 'auto',
            defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'top',
                disableMouseDownPressed: true,
                disabled: true,
                toggleGroup: 'disoverymanager-tabs',
                cls: 'x-btn-tab-no-text-transform',
                toggleHandler: function (comp, state) {
                    if (state) {
                        panel.fireEvent('selectplatform', this.value);
                    }
                }
            },
            items: platformsTabs
        }]
    });
});

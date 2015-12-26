Scalr.regPage('Scalr.ui.servers.orphaned.view', function (loadParams, moduleParams) {

    var store = Ext.create('Scalr.ui.ContinuousStore', {

        proxy: {
            type: 'ajax',
            url: '/servers/orphaned/xList',
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

        viewConfig: {
            emptyText: 'No orphaned servers found.'
        },

        columns: [{
            header: 'Cloud Server ID',
            dataIndex: 'cloudServerId',
            sortable: true,
            flex: 1
        }, {
            header: 'Public IP',
            xtype: 'templatecolumn',
            sortable: false,
            width: 150,
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
            width: 150,
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
                margin: 0
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
        stateId: 'grid-servers-orphaned-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'Orphaned servers',
            menuHref: '#/servers/orphaned',
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
        }]
    });
});

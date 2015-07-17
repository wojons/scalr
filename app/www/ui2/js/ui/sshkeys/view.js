Scalr.regPage('Scalr.ui.sshkeys.view', function (loadParams, moduleParams) {

    var platforms = [];

    Ext.Object.each(Scalr.platforms, function(key, value) {
        if (value.enabled || (moduleParams.platforms.indexOf(key) !== -1)) {
            platforms.push(key);
        }
    });

    var sshKeyStore = Ext.create('Scalr.ui.ContinuousStore', {
        fields: [
            'id',
            'type',
            'cloudLocation',
            'farmId',
            'cloudKeyName',
            'status',
            'farmName', {
                name: 'platform',
                convert: function (value) {
                    if (value === 'eucalyptus') {
                        return 'ecs';
                    }

                    return value;
                }
            }
        ],
        proxy: {
            type: 'ajax',
            url: '/sshkeys/xList/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        }
    });

    var grid = Ext.create('Ext.grid.Panel', {
        cls: 'x-panel-column-left',
        flex: 1,
        scrollable: true,

        store: sshKeyStore,

        plugins: ['applyparams', 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }, {
            ptype: 'continuousrenderer'
        }],

        viewConfig: {
            emptyText: 'No SSH Keys defined'
        },

        selModel: 'selectedmodel',

        listeners: {
            selectionchange: function (selModel, selections) {
                this.down('toolbar').down('#delete').setDisabled(!selections.length);
            }
        },

        deleteKeypair: function (id, name) {

            var isDeleteMultiple = Ext.typeOf(id) === 'array';

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Delete keypair <b>' + name + '</b> ?'
                        : 'Delete selected SSH keypair(s): %s ?',
                    objects: isDeleteMultiple ? name : null
                },
                processBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Deleting <b>' + name + '</b> ...'
                        : 'Deleting selected SSH keypair(s) ...'
                },
                url: '/sshkeys/xRemove/',
                params: {
                    sshKeyId: Ext.encode(
                        !isDeleteMultiple ? [id] : id
                    )
                },
                success: function (response) {
                    grid.setSelection();
                    sshKeyStore.load();
                }
            });
        },

        deleteSelectedKeypair: function () {
            var me = this;

            var record = me.getSelectedRecord();

            me.deleteKeypair(
                record.get('id'),
                record.get('cloudKeyName')
            );

            return me;
        },

        deleteSelectedKeypairs: function () {
            var me = this;

            var ids = [];
            var names = [];

            Ext.Array.each(
                me.getSelectionModel().getSelection(),

                function (record) {
                    ids.push(record.get('id'));
                    names.push(record.get('cloudKeyName'));
                }
            );

            me.deleteKeypair(ids, names);

            return me;
        },

        columns: [{
            text: 'SSH Key',
            flex: 1.2,
            dataIndex: 'cloudKeyName',
            sortable: true
        }, {
            header: 'Location',
            minWidth: 110,
            flex: 1,
            dataIndex: 'platform',
            sortable: true,
            renderer: function (value, meta, record) {
                var location = record.get('cloudLocation');
                return '<img class="x-icon-platform-small x-icon-platform-small-' + value +
                '" data-qtip="' + Scalr.utils.getPlatformName(value) + '" src="' + Ext.BLANK_IMAGE_URL +
                '"/>&nbsp;<span style="line-height: 18px;">' + (location ? location : 'All locations') + '</span>';
            }
        }, {
            header: 'Farm',
            flex: 1,
            dataIndex: 'farmId',
            sortable: false,
            renderer: function (value, meta, record) {
                var farmName = record.get('farmName');

                if (!Ext.isEmpty(farmName)) {
                    return '<a href="#/farms?farmId=' + value + '">' + farmName + '</a>';
                }

                return '&mdash;';
            }
        }, {
            header: 'Status',
            xtype: 'statuscolumn',
            dataIndex: 'status',
            statustype: 'sshkey',
            sortable: false,
            resizable: false,
            maxWidth: 90,
            qtipConfig: {
                width: 300
            }
        }, {
            xtype: 'optionscolumn',
            menu: [{
                text: 'Download SSH Private key',
                iconCls: 'x-menu-icon-downloadprivatekey',
                showAsQuickAction: true,
                menuHandler: function (data) {
                    Scalr.utils.UserLoadFile('/sshkeys/' + data.id + '/downloadPrivate?' + Ext.Object.toQueryString({
                        formatPpk: Ext.os.name === 'Windows'
                    }));
                }
            }, {
                text: 'Download SSH Public key',
                iconCls: 'x-menu-icon-downloadpublickey',
                showAsQuickAction: true,
                menuHandler: function (data) {
                    Scalr.utils.UserLoadFile('/sshkeys/' + data.id + '/downloadPublic');
                }
            }]
        }],

        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12'
            },
            items: [{
                xtype: 'filterfield',
                store: sshKeyStore,
                filterFields: ['cloudKeyName'],
                margin: 0,
                listeners: {
                    afterfilter: function () {
                        grid.getView().refresh();
                    }
                }
            }, {
                xtype: 'cloudlocationfield',
                cls: 'x-btn-compressed',
                platforms: platforms,
                forceAllLocations: true,
                listeners: {
                    change: function (me, value) {
                        sshKeyStore.applyProxyParams(value);
                    }
                }
            }, {
                xtype: 'tbfill'
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    sshKeyStore.clearAndLoad();
                }
            }, {
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                disabled: true,
                tooltip: 'Select one or more shh keys to delete them',
                handler: function () {
                    grid.deleteSelectedKeypairs();
                }
            }]
        }]
    });

    var form = Ext.create('Ext.form.Panel', {

        hidden: true,
        autoScroll: true,

        fieldDefaults: {
            anchor: '100%'
        },

        setHeader: function (sshKeypairName) {
            var me = this;

            me.down('fieldset').setTitle(sshKeypairName);

            return me;
        },

        listeners: {
            afterloadrecord: function (record) {
                var me = this;

                me.setHeader(record.get('cloudKeyName'));
            }
        },

        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none',
            headerCls: 'x-fieldset-separator-bottom',
            defaults: {
                xtype: 'displayfield',
                labelWidth: 140
            },
            items: [{
                fieldLabel: 'Key ID',
                name: 'id'
            }, {
                name: 'cloudLocation',
                fieldLabel: 'Location',
                renderer: function (value) {
                    var record = form.getRecord();
                    var cloudLocation = !Ext.isEmpty(value) ? value : 'All locations';

                    if (!Ext.isEmpty(record)) {
                        var platform = record.get('platform');
                        var platformName = Scalr.utils.getPlatformName(platform);

                        return '<img class="x-icon-platform-small x-icon-platform-small-' + platform +
                            '" data-qtip="' + platformName + '" src="' + Ext.BLANK_IMAGE_URL +
                            '"/> ' + cloudLocation;
                    }

                    return cloudLocation;
                }
            }, {
                fieldLabel: 'Farm',
                name: 'farmId',
                renderer: function (value) {
                    var record = form.getRecord();

                    if (!Ext.isEmpty(record)) {
                        var farmName = record.get('farmName');

                        if (!Ext.isEmpty(farmName)) {
                            return '<a href="#/farms?farmId=' + value + '">' + farmName + '</a>';
                        }
                    }

                    return '&mdash;';
                }
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            name: 'buttons',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults: {
                flex: 1,
                maxWidth: 160
            },
            items: [{
                xtype: 'button',
                text: 'Private key',
                cls: 'x-btn-with-icon-and-text',
                iconCls: 'x-btn-icon-downloadprivatekey',
                menu: {
                    xtype: 'actionsmenu',
                    items: [{
                        text: 'SSH Private key in PEM format',
                        iconCls: 'x-menu-icon-downloadprivatekey',
                        handler: function () {
                            Scalr.utils.UserLoadFile('/sshkeys/' + form.getRecord().get('id') + '/downloadPrivate');
                        }
                    }, {
                        text: 'SSH Private key in PPK format',
                        iconCls: 'x-menu-icon-downloadprivatekey',
                        handler: function () {
                            Scalr.utils.UserLoadFile('/sshkeys/' + form.getRecord().get('id') + '/downloadPrivate?' + Ext.Object.toQueryString({
                                formatPpk: true
                            }));
                        }
                    }]
                }
            }, {
                xtype: 'button',
                text: 'Public key',
                cls: 'x-btn-with-icon-and-text',
                iconCls: 'x-btn-icon-downloadpublickey',
                handler: function () {
                    Scalr.utils.UserLoadFile('/sshkeys/' + form.getRecord().get('id') + '/downloadPublic');
                }
            }, {
                xtype: 'button',
                itemId: 'delete',
                cls: 'x-btn-red',
                text: 'Delete',
                handler: function () {
                    grid.deleteSelectedKeypair();
                }
            }]
        }]
    });

    return Ext.create('Ext.panel.Panel', {
        stateful: true,
        stateId: 'grid-sshkeys-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'SSH Keys',
            menuHref: '#/sshkeys',
            menuFavorite: true
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: .5,
            maxWidth: 700,
            minWidth: 550,
            layout: 'fit',
            items: [ form ]
        }]
    });
});

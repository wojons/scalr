Scalr.regPage('Scalr.ui.services.ssl.certificates.view', function () {

	var store = Ext.create('store.store', {

		fields: [
            'id',
            'name',
            'privateKey',
            'privateKeyPassword',
            'certificate',
            'caBundle'
        ],

        proxy: {
            type: 'ajax',
            url: '/services/ssl/certificates/xListCertificates/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                successProperty: 'success'
            }
        },

        listeners: {
            beforeload: function () {
                grid.down('#add').toggle(false, true);
            },
            filterchange: function () {
                grid.down('#add').toggle(false, true);
            }
        },

        removeByCertificateId: function (ids) {
            var me = this;

            me.remove(Ext.Array.map(
                ids, function (id) {
                    return me.getById(id);
                }
            ));

            if (me.getCount() === 0) {
                grid.getView().refresh();
            }

            return me;
        }
	});

	var grid = Ext.create('Ext.grid.Panel', {

        cls: 'x-panel-column-left',
        flex: 1,
        scrollable: true,

		store: store,

        plugins: [ 'applyparams', 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }],

        viewConfig: {
            preserveScrollOnRefresh: true,
            markDirty: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No ssl certificates found.',
                emptyTextNoItems: 'You have no ssl certificates added yet.'
            },
            loadingText: 'Loading certificates ...',
            deferEmptyText: false
        },

        selModel: Scalr.isAllowed('SERVICES_SSL', 'manage') ? 'selectedmodel' : null,

        listeners: {
            selectionchange: function (selModel, selections) {
                this.down('toolbar').down('#delete').setDisabled(!selections.length);
            }
        },

        applyCertificate: function (certificate) {
            var me = this;

            var record = me.getSelectedRecord();
            var store = me.getStore();

            if (Ext.isEmpty(record)) {
                record = store.add(certificate)[0];
            } else {
                record.set(certificate);
                me.clearSelectedRecord();
            }

            me.setSelectedRecord(record);

            return me;
        },

        deleteCertificates: function (ids, names) {

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: 'Delete selected certificate(s): %s ?',
                    objects: names
                },
                processBox: {
                    type: 'delete',
                    msg: 'Deleting selected certificates ...'
                },
                url: '/services/ssl/certificates/xRemove/',
                params: {
                    certs: Ext.encode(ids)
                },
                success: function (response) {
                    var deletedCertificatesIds = response.processed;

                    if (!Ext.isEmpty(deletedCertificatesIds)) {

                        store.removeByCertificateId(
                            deletedCertificatesIds
                        );
                    }
                }
            });
        },

        deleteSelectedCertificates: function () {
            var me = this;

            var ids = [];
            var names = [];

            Ext.Array.each(
                me.getSelectionModel().getSelection(),

                function (record) {
                    ids.push(record.get('id'));
                    names.push(record.get('name'));
                }
            );

            me.deleteCertificates(ids, names);

            return me;
        },

        columns: [{
            header: "ID",
            width: 60,
            dataIndex: 'id',
            sortable: true
        }, {
            header: "SSL Certificate",
            flex: 1,
            dataIndex: 'name',
            sortable: true
        }, {
            header: 'Certificate',
            width: 100,
            sortable: false,
            xtype: 'templatecolumn',
            align: 'center',
            tpl: [
                '<tpl if="!Ext.isEmpty(values.certificate)">',
                    '<div class="x-grid-icon x-grid-icon-ok"></div>',
                '<tpl else>',
                    '&mdash;',
                '</tpl>'
            ]
        }, {
            header: 'Certificate chain',
            width: 140,
            sortable: false,
            xtype: 'templatecolumn',
            align: 'center',
            tpl: [
                '<tpl if="!Ext.isEmpty(values.caBundle)">',
                    '<div class="x-grid-icon x-grid-icon-ok"></div>',
                '<tpl else>',
                    '&mdash;',
                '</tpl>'
            ]
        }, {
            header: 'Private key',
            width: 100,
            sortable: false,
            xtype: 'templatecolumn',
            align: 'center',
            tpl: [
                '<tpl if="!Ext.isEmpty(values.privateKey)">',
                    '<div class="x-grid-icon x-grid-icon-ok"></div>',
                '<tpl else>',
                    '&mdash;',
                '</tpl>'
            ]
        }],

		dockedItems: [{
			xtype: 'toolbar',
			store: store,
			dock: 'top',
            ui: 'simple',
            defaults: {
                margin: '0 0 0 12'
            },
			items: [{
				xtype: 'filterfield',
				store: store,
                filterFields: ['name'],
                margin: 0,
                listeners: {
                    afterfilter: function () {
                        grid.getView().refresh();
                    }
                }
            }, {
                xtype: 'tbfill'
            }, {
                text: 'New SSL certificate',
                itemId: 'add',
                cls: 'x-btn-green',
                enableToggle: true,
                hidden: !Scalr.isAllowed('SERVICES_SSL', 'manage'),
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();

                        form.down('#save').setText('Create');
                        form.down('#delete').hide();

                        form.
                            setHeader('New SSL Certificate').
                            disableButtons().
                            show().
                            down('[name=name]').focus();

                        return;
                    }

                    form.hide();
                }
            }, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.load();
                }
            }, {
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more certificates to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('SERVICES_SSL', 'manage'),
                handler: function () {
                    grid.deleteSelectedCertificates();
                }
            }]
		}]
	});

    var form = Ext.create('Scalr.ui.CertificateForm', {

        hidden: true,

        onCertificateSave: function (form, certificate) {
            if (certificate !== null) {
                grid.applyCertificate(certificate);
                return true;
            }

            store.load();
            grid.down('#add').toggle(false, true);
        },

        onCertificateRemove: function (form, id) {
            store.removeByCertificateId(id);
        },

        onCancel: function () {
            grid.clearSelectedRecord();
            grid.down('#add').toggle(false, true);
        },

        listeners: {
            afterloadrecord: function (record) {
                var me = this;

                var isManageAllowed = Scalr.isAllowed('SERVICES_SSL', 'manage');

                me
                    .setHeader((isManageAllowed ? 'Edit' : 'View') + ' SSL Certificate')
                    .disableButtons(
                        record.get('certificate'),
                        record.get('caBundle'),
                        record.get('privateKey')
                    );

                me.down('#save').setText('Save');
                me.down('#delete').show();
                me.setReadOnly(!isManageAllowed);

                grid.down('#add').toggle(false, true);
            }
        }
    });

    return Ext.create('Ext.panel.Panel', {

        stateful: true,
        stateId: 'grid-services-ssl-certificates-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'SSL certificates',
            menuHref: '#/services/ssl/certificates',
            menuFavorite: true
        },

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: .6,
            maxWidth: 900,
            minWidth: 600,
            layout: 'fit',
            items: [ form ]
        }]
    });
});

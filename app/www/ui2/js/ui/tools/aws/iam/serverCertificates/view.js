Scalr.regPage('Scalr.ui.tools.aws.iam.serverCertificates.view', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [ 'name','path','arn','id','upload_date' ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/aws/iam/serverCertificates/xListCertificates/'
        },
        remoteSort: true
    });

    return Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'AWS Server Certificates',
            menuHref: '#/tools/aws/iam/servercertificates',
            menuFavorite: true
        },
        store: store,
        stateId: 'grid-tools-aws-iam-serverCertificates-view',
        stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],
        viewConfig: {
            emptyText: 'No server certificates found',
            loadingText: 'Loading certificates ...'
        },

        columns: [
            { header: "ID", width: 250, dataIndex: 'id', sortable: false },
            { header: "Certificate", flex: 1, dataIndex: 'name', sortable: false },
            { header: "Path", flex: 1, dataIndex: 'path', sortable: false },
            { header: "Arn", flex: 1, dataIndex: 'arn', sortable: false },
            { header: "Upload date", width: 200, dataIndex: 'upload_date', sortable: false }
        ],

        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            beforeItems: [{
                text: 'New certificate',
                cls: 'x-btn-green',
                hidden: !Scalr.isAllowed('AWS_IAM', 'manage'),
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/tools/aws/iam/servercertificates/create');
                }
            }]
        }]
    });
});

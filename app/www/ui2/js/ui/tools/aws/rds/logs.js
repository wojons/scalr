Scalr.regPage('Scalr.ui.tools.aws.rds.logs', function (loadParams, moduleParams) {
    var store = Ext.create('store.store', {
        fields: [ "Date", "SourceIdentifier", "Message", "SourceType" ],
        proxy: {
            type: 'scalr.paging',
            url: '/tools/aws/rds/xListLogs/'
        },
        remoteSort: true
    });
    return Ext.create('Ext.grid.Panel', {
        scalrOptions: {
            reload: false,
            maximize: 'all',
            menuTitle: 'RDS Event logs'
        },
        store: store,
        stateId: 'grid-tools-aws-rds-logs',
        stateful: true,
        plugins: [{
            ptype: 'gridstore'
        }, {
            ptype: 'applyparams'
        }],
        viewConfig: {
            deferEmptyText: false,
            emptyText: 'No logs found',
            loadingText: 'Loading logs ...'
        },
        columns: [
            {flex: 3, text: "Time", dataIndex: 'Date', sortable: true },
            {flex: 1, text: "Message", dataIndex: 'Message', sortable: true },
            {flex: 1, text: "Caller", dataIndex: 'SourceIdentifier', sortable: true },
            {flex: 1, text: "Type", dataIndex: 'SourceType', sortable: true }
        ],
        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top',
            items: [{
                xtype: 'filterfield',
                store: store
            }]
        }]
    });
});

Scalr.regPage('Scalr.ui.core.governance.lease.history', function () {
    var store = Ext.create('store.store', {
        fields: [
            'id', 'farm_id', 'farm_name', 'request_days', 'request_time', 'request_comment', 'request_user_email', 'answer_comment',
            'answer_user_email', 'status'
        ],
        proxy: {
            type: 'scalr.paging',
            url: '/core/governance/lease/xHistoryRequests'
        },
        remoteSort: true
    });

    return Ext.create('Ext.grid.Panel', {
        title: 'Governance &raquo; Lease management &raquo; History of non-standard requests',
        store: store,
        scalrOptions: {
            'reload': false,
            'maximize': 'all'
        },
        stateId: 'grid-core-governance-lease-history',
        stateful: true,
        plugins: [ 'gridstore', 'applyparams' ],

        viewConfig: {
            emptyText: 'No requests found',
            loadingText: 'Loading requests ...'
        },
        columns: [
            { header: 'Farm name', flex: 1, dataIndex: 'farm_id', sortable: true, xtype: 'templatecolumn', tpl: '{farm_name}' },
            { header: 'Days', width: 80, dataIndex: 'request_days', sortable: true, align: 'center', xtype: 'templatecolumn', tpl:
                '<tpl if="request_days == 0">Forever<tpl else>{request_days}</tpl>'
            },
            { header: 'Time', width: 160, dataIndex: 'request_time', sortable: true },
            { header: 'Requesting user', flex: 1, sortable: false, dataIndex: 'request_user_email' },
            { header: 'Comment with request', flex: 2, sortable: false, dataIndex: 'request_comment' },
            { header: 'Responding user', flex: 1, sortable: false, dataIndex: 'answer_user_email' },
            { header: 'Comment with response', flex: 2, sortable: false, dataIndex: 'answer_comment' },
            { header: 'Status', width: 100, dataIndex: 'status', sortable: true, xtype: 'templatecolumn', tpl:
                '<tpl if="status == &quot;pending&quot;">Pending' +
                '<tpl elseif="status == &quot;decline&quot;">Declined' +
                '<tpl elseif="status == &quot;approve&quot;">Approved' +
                '<tpl elseif="status == &quot;cancel&quot;">Cancel</tpl>'
            }
        ],
        dockedItems: [{
            xtype: 'scalrpagingtoolbar',
            store: store,
            dock: 'top'
        }]
    });
});
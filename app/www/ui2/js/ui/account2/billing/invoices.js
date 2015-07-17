Scalr.regPage('Scalr.ui.account2.billing.invoices', function (loadParams, moduleParams) {
	
	var form = Ext.create('Ext.form.Panel', {
		width: 500,
		layout: 'card',
		title: 'Billing &raquo; Invoices',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			'modal': true
		},
        bodyCls: 'x-container-fieldset',
		items: [{
			xtype: 'grid',
			itemId: 'view',
			store: {
				proxy: 'object',
				fields: ['createdAt', 'id', 'text']
			},
            plugins: [{
                ptype: 'gridstore'
            }],

			viewConfig: {
				emptyText: 'No invoices available for your subscription',
				loadingText: 'Loading invoices ...',
				deferEmptyText: false
			},

			columns: [
				{ header: '', flex: 200, sortable: true, dataIndex: '', xtype: 'templatecolumn',
					tpl: '<a href="/account/billing/{id}/showInvoice?X-Requested-Token=' + Scalr.flags['specialToken'] + '" target="_blank">{createdAt}</a>'
				}
			]				
		}],
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}]
	});

	form.down('#view').store.load({ data: moduleParams.invoices });

	return form;
});

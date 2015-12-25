Scalr.regPage('Scalr.ui.account2.environments.clouds.azure', function (loadParams, moduleParams) {
    var params = moduleParams['params'];

    if (!Ext.isEmpty(params['errorMessage'])) {
        Scalr.message.Error(params['errorMessage']);
    }

    return Ext.create('Ext.form.Panel', {
        bodyCls: 'x-container-fieldset',
        saveBtnText: params['step'] >= 2 ? null : 'Continue',
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 150
        },
        autoScroll: true,
        beforeSaveSuccess: function(data) {
            if (data.authLocation) {
                document.location.href = data.authLocation;
                Scalr.utils.CreateProcessBox({msg: 'Redirecting...'});
                return false;
            }
        },
        onCloseForm: function(params) {
            Scalr.event.fireEvent('redirect', '#/account/environments?envId='+ params['envId'], true);
        },
        items: [{
            xtype: 'hidden',
            name: 'azure.is_enabled',
            value: 'on'
        }, {
            xtype: 'fieldset',
            title: 'Step 1. Tenant',
            cls: 'x-fieldset-separator-none',
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Tenant name',
                name: 'tenant_name',
                value: params['tenant_name']
            }]
        },{
            xtype: 'fieldset',
            title: 'Step 2. Subscription',
            cls: 'x-fieldset-separator-none',
            hidden: params['step'] < 2,
            items: [{
                xtype: 'combo',
                fieldLabel: 'Subscription',
                name: 'subscription_id',
                valueField: 'subscriptionId',
                displayField: 'displayName',
                value: params['subscription_id'],
                store: {
                    fields: ['subscriptionId', 'displayName'],
                    proxy: 'object',
                    data: params['subscriptions']
                },
                editable: false,
                emptyText: 'Please select subscription'
            }]
        }]
    });
});

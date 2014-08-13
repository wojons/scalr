Scalr.regPage('Scalr.ui.farms.builder.addrole.mongodb', function () {
    return {
        xtype: 'fieldset',
        isExtraSettings: true,
        hidden: true,
        
        title: 'Mongo over SSL',
        name: 'mongodb.ssl.enabled',
        toggleOnTitleClick: true,
        checkboxToggle: true,
        collapsed: true,
        collapsible: true,
        layout: 'anchor',
        
        isVisibleForRole: function(record) {
            return Ext.Array.contains(record.get('behaviors'), 'mongodb');
        },

        isValid: function() {
            var res = true, field;
            if (!this.collapsed) {
                field = this.down('[name="mongodb.ssl.cert_id"]');
                res = field.validate() || {comp: field};
            }
            return res;
        },

        getSettings: function() {
            var settings = {},
                sslCertId = this.down('[name="mongodb.ssl.cert_id"]').getValue();
            if (!this.collapsed && sslCertId) {
                settings['mongodb.ssl.enabled'] = 1;
                settings['mongodb.ssl.cert_id'] = sslCertId;
            } else {
                settings['mongodb.ssl.enabled'] = 0;
                settings['mongodb.ssl.cert_id'] = '';
            }
            return settings;
        },

        items: [{
            xtype: 'combo',
            name: 'mongodb.ssl.cert_id',
            fieldLabel: 'SSL certificate',
            maxWidth: 470,
            anchor: '100%',
            emptyText: 'Choose certificate',
            valueField: 'id',
            displayField: 'name',
            allowBlank: false,

            forceSelection: true,
            queryCaching: false,
            minChars: 0,
            queryDelay: 10,
            store: {
                fields: [ 'id', 'name' ],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'farmbuilder',
                    url: '/services/ssl/certificates/xListCertificates',
                    filterFields: ['name']
                }
            },
            plugins: [{
                ptype: 'comboaddnew',
                url: '/services/ssl/certificates/create',
                disabled: !Scalr.isAllowed('SERVICES_SSL')
            }],
            listeners: {
                addnew: function(item) {
                    Scalr.CachedRequestManager.get('farmbuilder').setExpired({url: '/services/ssl/certificates/xListCertificates'});
                }
            }
        }]
    }
});

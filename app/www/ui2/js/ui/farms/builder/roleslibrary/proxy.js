Scalr.regPage('Scalr.ui.farms.builder.addrole.proxy', function () {
    return {
        xtype: 'container',
        itemId: 'proxy',
        isExtraSettings: true,
        hidden: true,

        isVisibleForRole: function(record) {
            return Ext.Array.contains(record.get('behaviors', true), 'www');
        },

        onSelectImage: function(record) {
            if (this.isVisibleForRole(record)) {
                this.setRole(record);
                this.show();
            } else {
                this.hide();
            }
        },

        setRole: function(record) {
            var me = this,
                p = this.down('proxysettings');
            p.proxyDefaults = this.up('roleslibrary').moduleParams.tabParams['nginx'];
            me.roles = [];
			this.up('roleslibrary').moduleParams.tabParams.farmRolesStore.each(function(r){
                var location = r.get('cloud_location');
    			me.roles.push({id: r.get('farm_role_id'), name: r.get('alias') + (location ? ' (' + location + ')' : '')});
			});
            p.setValue({
                'nginx.proxies': []
            });
        },

        isValid: function() {
            return this.down('proxysettings').down('#form').validateRecord();
        },

        getSettings: function() {
            var p = this.down('proxysettings'),
                settings = p.getValue();
            settings['nginx.proxies'] = Ext.encode(settings['nginx.proxies']);
            return settings;
        },

        items: [{
            xtype: 'proxysettings',
            mode: 'add'
        }]
    }
});
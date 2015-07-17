Scalr.regPage('Scalr.ui.farms.builder.addrole.haproxy', function () {
    return {
        xtype: 'container',
        itemId: 'haproxy',
        isExtraSettings: true,
        hidden: true,
        
        layout: 'anchor',

        isVisibleForRole: function(record) {
            return Ext.Array.contains(record.get('behaviors', true), 'haproxy');
        },

        setRole: function(record){
            var me = this,
                hp = me.down('haproxysettings');
            me.roles = [];
			this.up('#farmDesigner').moduleParams.tabParams.farmRolesStore.each(function(r){
                var location = r.get('cloud_location');
    			me.roles.push({id: r.get('farm_role_id'), alias: r.get('alias'), name: r.get('alias') + (location ? ' (' + location + ')' : '')});
			});
            
            hp.setValue({
                'haproxy.proxies': []
            });
        },

        isValid: function() {
            return this.down('haproxysettings').down('#form').validateRecord();
        },

        getSettings: function() {
            var hp = this.down('haproxysettings'),
                settings = hp.getValue();
            settings['haproxy.proxies'] = Ext.encode(settings['haproxy.proxies']);
            return settings;
        },

        items: [{
            xtype: 'haproxysettings',
            mode: 'add'
        }]
    }
});

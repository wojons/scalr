Scalr.regPage('Scalr.ui.farms.builder.addrole.chef', function () {
    return {
        xtype: 'container',
        itemId: 'chef',
        isExtraSettings: true,
        hidden: true,

        isVisibleForRole: function(record) {
            //we are going to show chef settings only for shared roles under Chef group
            return this.up('form').currentRole['data']['name'] === 'chef';
        },

        onSelectImage: function(record) {
            if (this.isVisibleForRole(record)) {
                this.setRole(record);
                this.show();
            } else {
                this.hide();
            }
        },

        onSettingsUpdate: function(record, name, value) {
        },

        setRole: function(record) {
            var field = this.down('chefsettings');
            this.currentRole = record;
            field.setValue({'chef.bootstrap': 1});
            field.clearInvalid();
        },

        isValid: function() {
            return this.down('chefsettings').isValid(true);
        },

        getSettings: function() {
            return this.down('chefsettings').getValue();
        },

        items: [{
            xtype: 'chefsettings'
        }]
    };
});

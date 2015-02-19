Ext.define('Scalr.ui.aws.RdsHelper', {
    singleton: true,

    getInstanceTypes: function (cloudLocation, engine, engineVersion, callback) {
        Scalr.Request({
            processBox: {
                type: 'load'
            },
            url: '/tools/aws/rds/instances/xGetInstanceTypes',
            params: {
                cloudLocation: cloudLocation,
                engine: engine,
                engineVersion: engineVersion,
            },
            success: function (response) {
                if (callback && Ext.isFunction(callback)) {
                    callback(response);
                }
            }
        });
    },

    getEngineVersions: function (cloudLocation, engine, callback) {
        Scalr.Request({
            processBox: {
                type: 'load'
            },
            url: '/tools/aws/rds/instances/xGetEngineVersions',
            params: {
                cloudLocation: cloudLocation,
                engine: engine
            },
            success: function (response) {
                if (callback && Ext.isFunction(callback)) {
                    callback(response);
                }
            }
        });
    },

    getParameterGroups: function (cloudLocation, engine, engineVersion, callback) {
        Scalr.Request({
            processBox: {
                type: 'load'
            },
            url: '/tools/aws/rds/instances/xGetParameterGroup',
            params: {
                cloudLocation: cloudLocation,
                engine: engine,
                engineVersion: engineVersion
            },
            success: function (response) {
                if (callback && Ext.isFunction(callback)) {
                    callback(response);
                }
            }
        });
    },

    getOptionGroups: function (cloudLocation, engine, engineVersion, callback) {
        Scalr.Request({
            processBox: {
                type: 'load'
            },
            url: '/tools/aws/rds/instances/xGetOptionGroups',
            params: {
                cloudLocation: cloudLocation,
                engine: engine,
                engineVersion: engineVersion
            },
            success: function (response) {
                if (callback && Ext.isFunction(callback)) {
                    callback(response);
                }
            }
        });
    }

});

Ext.define('Scalr.ui.RdsSecurityGroupMultiSelect', {
    extend: 'Scalr.ui.SecurityGroupMultiSelect',
    alias: 'widget.rdssgmultiselect',

    selectOnLoad: function (store) {
        var me = this;

        var selectedRecords = [];

        Ext.Array.each(me.selection, function (item) {
            var selectedRecord = null;

            store.findBy(function (record) {
                if (record.get('id') === item || record.get('name') === item) {
                    selectedRecord = record;
                    return false;
                }
            });

            if (selectedRecord) {
                selectedRecords.push(selectedRecord);
            }
        });

        me.selection = selectedRecords;
        me.down('grid').getView().getSelectionModel().select(selectedRecords);

        return me;
    },

    updateButtonState: function (count) {
        var me = this;

        me.callParent(arguments);

        var button = me.up('#box').down('#buttonOk');
        button.setDisabled(!count);
        button.setText('Select ' + count + ' group(s)');
    }
});
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

Ext.define('Scalr.ui.rds.SecurityGroupMultiSelect', {
    extend: 'Scalr.ui.SecurityGroupMultiSelect',
    alias: 'widget.rdssgmultiselect',

    isRdsSecurityGroupMultiSelect: true,

    governanceWarning: null,

    initComponent: function () {
        var me = this;

        me.callParent(arguments);

        var store = me.down('grid').getStore();

        me.selection = Ext.Array.map(me.selection, function (item) {
            if (Ext.isObject(item)) {
                return store.createModel(item);
            }

            return store.createModel({
                name: item.trim()
            });
        });

        store.on('load', function () {
            me.updateButtonState(me.selection.length);
        }, me, { single: true });

        var title = 'Add Security Groups to DB Instance';

        me.setTitle(!Ext.isString(me.governanceWarning)
            ? title
            : title + '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL +
                         '" class="x-icon-governance" data-qtip="' +
                        me.governanceWarning +
                        '" />'
        );

        me.defaultVpcGroups = me.defaultVpcGroups || [];
    },

    initEvents: function () {
        var me = this;

        me.callParent(arguments);

        if (me.storeExtraParams.platform === 'ec2') {
            me.down('grid').
                on('beforedeselect', function (rowModel, record) {
                    return !Ext.Array.contains(me.defaultVpcGroups, record.get('name'));
                });
        }
    },

    /*selectOnLoad: function (store) {
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

        //me.selection = selectedRecords;
        me.down('grid').getView().getSelectionModel().select(selectedRecords);

        return me;
    },*/

    selectOnLoad: function (store) {
        var me = this;

        var records = [];

        Ext.Array.each(me.selection, function (item) {
            var record = store.findRecord('name', item.get('name'));

            if (!Ext.isEmpty(record)) {
                records.push(record);
            }
        });

        me.down('grid').getView().getSelectionModel().select(records);
    },

    updateButtonState: function (count) {
        var me = this;

        me.callParent(arguments);

        var button = me.up('#box').down('#buttonOk');
        button.setDisabled(!count);
        button.setText('Select ' + count + ' group(s)');
    }
});

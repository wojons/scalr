Scalr.regPage('Scalr.ui.db.backups.view', function (loadParams, moduleParams) {
	//TODO back-end part

    var store = Ext.create('store.store', {
        fields: [ 'id', 'name' ],
        data: moduleParams['farms'],
        proxy: 'object'
    });

	var panel = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			maximize: 'all',
            menuTitle: 'DB Backups',
            menuHref: '#/db/backups',
            menuFavorite: true
			//reload: false
		},
		dataStore: {},
        stateId: 'grid-db-backups-view',

        layout: 'fit',
		items: {
            autoScroll: true,
			xtype: 'db.backup.calendar',
			itemId: 'dbbackupsScalrCalendar',
            backups: moduleParams['backups']
		},

		dockedItems: [{
			xtype: 'toolbar',
			dock: 'top',
            enableParamsCapture: true,
            store: store,
			items: [{
                xtype: 'button',
                cls: 'x-btn-flag',
                iconCls: 'x-btn-icon-previous',
                style: 'min-width: 36px',
                handler: function() {
                    var monthField = panel.down('#dateSelector'),
                        date = new Date(monthField.getValue()),
                        farmIdCombobox = panel.down('#farmId'),
                        calendar = panel.down('#dbbackupsScalrCalendar');

                    date = Ext.Date.add(date, Ext.Date.MONTH, -1);
                    monthField.setValue(date);
                    calendar.checkCacheThenRefreshCalendar(date, farmIdCombobox.getValue());
                }
            }, {
                xtype: 'datefield',
                itemId: 'dateSelector',
                margin: '0 0 0 8',
                width: 170,
                format: 'F Y',
                value: new Date(),
                editable: false,
                listeners: {
                    boxready: function (field) {
                        var picker = field.getPicker();

                        Ext.apply(picker, {
                            disableAnim: true,

                            onOkClick: function(picker, value) {
                                var me = this,
                                    month = value[0],
                                    year = value[1],
                                    date = new Date(year, month, me.getActive().getDate());

                                if (date.getMonth() !== month) {
                                    // 'fix' the JS rolling date conversion if needed
                                    date = Ext.Date.getLastDateOfMonth(new Date(year, month, 1));
                                }

                                me.setValue(date);
                                me.hideMonthPicker();
                                me.fireEvent('select', me, me.value);
                                me.onSelect();
                            },

                            onCancelClick: function() {
                                var me = this;

                                me.selectedUpdate(me.activeDate);
                                me.hideMonthPicker();

                                field.collapse();
                            }
                        });


                    },
                    expand: function (field) {
                        field.getPicker().showMonthPicker();
                    },
                    select: function (field, value) {
                        var farmIdCombobox = panel.down('#farmId'),
                            calendar = panel.down('#dbbackupsScalrCalendar');

                        calendar.checkCacheThenRefreshCalendar(value, farmIdCombobox.getValue());
                    }
                }
            }, {
                xtype: 'button',
                cls: 'x-btn-flag',
                iconCls: 'x-btn-icon-next',
                style: 'min-width: 36px',
                margin: '0 0 0 8',
                handler: function() {
                    var monthField = panel.down('#dateSelector'),
                        date = new Date(monthField.getValue()),
                        farmIdCombobox = panel.down('#farmId'),
                        calendar = panel.down('#dbbackupsScalrCalendar');

                    date = Ext.Date.add(date, Ext.Date.MONTH, +1);
                    monthField.setValue(date);
                    calendar.checkCacheThenRefreshCalendar(date, farmIdCombobox.getValue());
                }
            }, {
				xtype: 'combo',
				fieldLabel: 'Farm',
				labelWidth: 34,
                margin: '0 0 0 20',
				width: 250,
				matchFieldWidth: false,
				listConfig: {
					minWidth: 150
				},
				store: store,
				editable: false,
				queryMode: 'local',
				itemId: 'farmId',
				value: loadParams['farmId'] || 0,
				valueField: 'id',
				displayField: 'name',
				listeners: {
					change: function() {
                        var me = this,
                            monthField = panel.down('#dateSelector'),
                            date = new Date(monthField.getValue()),
                            calendar = panel.down('#dbbackupsScalrCalendar');
                        calendar.checkCacheThenRefreshCalendar(date, me.getValue());
					}
				}
			}, {
                xtype: 'button',
                iconCls: 'x-btn-icon-refresh',
                margin: '0 0 0 20',
                handler: function() {
                    var monthField = panel.down('#dateSelector'),
                        date = new Date(monthField.getValue()),
                        farmIdCombobox = panel.down('#farmId'),
                        calendar = panel.down('#dbbackupsScalrCalendar');
                    calendar.getBackupsThenRefreshCalendar(date, farmIdCombobox.getValue());
                }
            }]
		}]
	});
	return panel;
});

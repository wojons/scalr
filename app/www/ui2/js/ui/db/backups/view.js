Scalr.regPage('Scalr.ui.db.backups.view', function (loadParams, moduleParams) {
	//TODO back-end part

    var store = Ext.create('store.store', {
        fields: [ 'id', 'name' ],
        data: moduleParams['farms'],
        proxy: 'object'
    });

	var panel = Ext.create('Ext.panel.Panel', {
		title: 'DB backups',
		scalrOptions: {
			maximize: 'all'
			//reload: false
		},
		dataStore: {},

        layout: 'fit',
		items: {
            autoScroll: true,
			xtype: 'db.backup.calendar',
			itemId: 'dbbackupsScalrCalendar',
            backups: moduleParams['backups']
		},

		tools: [{
			xtype: 'favoritetool',
			favorite: {
				text: 'DB backups',
				href: '#/db/backups'
			}
		}],

		dockedItems: [{
			xtype: 'toolbar',
			dock: 'top',
            enableParamsCapture: true,
            store: store,
			items: [{
                xtype: 'button',
                iconCls: 'scalr-ui-dbbackups-button-prev',
                width: 29,
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
                editable: false,
                selectMonth: null,
                createPicker: function () {
                    var me = this,
                        format = Ext.String.format;
                    return Ext.create('Ext.picker.Month', {
                        pickerField: me,
                        ownerCt: me.ownerCt,
                        renderTo: document.body,
                        floating: true,
                        hidden: true,
                        focusOnShow: true,
                        minDate: me.minValue,
                        maxDate: me.maxValue,
                        disabledDatesRE: me.disabledDatesRE,
                        disabledDatesText: me.disabledDatesText,
                        disabledDays: me.disabledDays,
                        disabledDaysText: me.disabledDaysText,
                        format: me.format,
                        showToday: me.showToday,
                        startDay: me.startDay,
                        minText: format(me.minText, me.formatDate(me.minValue)),
                        maxText: format(me.maxText, me.formatDate(me.maxValue)),
                        listeners: {
                            select: { scope: me, fn: me.onSelect     },
                            monthdblclick: { scope: me, fn: me.onOKClick     },
                            yeardblclick: { scope: me, fn: me.onOKClick     },
                            OkClick: { scope: me, fn: me.onOKClick     },
                            CancelClick: { scope: me, fn: me.onCancelClick }
                        },
                        keyNavConfig: {
                            esc: function () {
                                me.collapse();
                            }
                        }
                    });
                },
                onCancelClick: function () {
                    var me = this;
                    me.selectMonth = null;
                    me.collapse();
                },
                onOKClick: function () {
                    var me = this;
                    if (me.selectMonth) {
                        me.setValue(me.selectMonth);
                        me.fireEvent('select', me, me.selectMonth);
                    }
                    me.collapse();
                },
                onSelect: function (m, d) {
                    var me = this;
                    me.selectMonth = new Date(( d[0] + 1 ) + '/1/' + d[1]);
                },
                format: 'F Y',
                value: new Date(),
                itemId: 'dateSelector',
                margin: '0 0 0 6',
                width: 170,
                listeners: {
                    select: function() {
                        var me = this,
                            date = new Date(me.getValue()),
                            farmIdCombobox = panel.down('#farmId'),
                            calendar = panel.down('#dbbackupsScalrCalendar');
                        calendar.checkCacheThenRefreshCalendar(date, farmIdCombobox.getValue());
                    }
                }
            }, {
                xtype: 'button',
                iconCls: 'scalr-ui-dbbackups-button-next',
                margin: '0 0 0 6',
                width: 29,
                handler: function() {
                    var monthField = panel.down('#dateSelector'),
                        date = new Date(monthField.getValue()),
                        farmIdCombobox = panel.down('#farmId'),
                        calendar = panel.down('#dbbackupsScalrCalendar');

                    date = Ext.Date.add(date, Ext.Date.MONTH, +1);
                    monthField.setValue(date);
                    calendar.checkCacheThenRefreshCalendar(date, farmIdCombobox.getValue());
                }
            },  {
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
			},  {
                xtype: 'button',
                text: 'Refresh',
                margin: '0 0 0 20',
                width: 120,
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
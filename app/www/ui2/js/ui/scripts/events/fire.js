Scalr.regPage('Scalr.ui.scripts.events.fire', function (loadParams, moduleParams) {
    var form = Ext.create('Ext.form.Panel', {
        width: 900,
        title: 'Fire event',
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },

        items: [{
            xtype: 'fieldset',
            title: 'Event',
            items: [{
                xtype: 'combobox',
                emptyText: 'Select an event',
				store: {
					fields: [ 'name', 'description', 'scope'],
					data: moduleParams['events'],
					proxy: 'object'
				},
                plugins: {ptype: 'fieldinnericonscope', tooltipScopeType: 'event'},
                matchFieldWidth: true,
                listConfig: Scalr.configs.eventsListConfig,
                displayField: 'name',
                queryMode: 'local',
                valueField: 'name',
                editable: true,
                anyMatch: true,
                autoSearch: false,
                selectOnFocus: true,
                restoreValueOnBlur: true,
                allowBlank: false,
                readOnly: !!moduleParams['eventName'],
                name: 'eventName',
                listeners: {
                    afterrender: function(comp) {
                        comp.inputEl.on('click', function(){
                            if (!comp.readOnly) {
                                comp.expand();
                            }
                        });
                    },
                    change: function(comp, value) {
                        var rec = comp.findRecordByValue(value);
                        comp.next().update(rec ? rec.get('description') || 'No description for this event' : '&nbsp;');
                    }
                }
            },{
                xtype: 'component',
                itemId: 'eventDescription',
                style: 'font-style:italic',
                html: '&nbsp;',
                margin: '12 0 0'
            }]
        },{
            xtype: 'farmroles',
            title: 'Event context',
            itemId: 'executionTarget',
            params: moduleParams['farmWidget']
        },{
            xtype: 'fieldset',
            title: 'Scripting parameters',
            cls: 'x-fieldset-separator-none',
            items: {
                xtype: 'namevaluelistfield',
                itemId: 'eventParams',
                itemName: 'parameter',
                listeners: {
                    boxready: function() {
                       this.store.add({});
                    }
                }
            }
        }],

        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'Fire event',
                handler: function () {
                    Scalr.message.Flush(true);
                    if (form.getForm().isValid())
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            url: '/scripts/events/xFire/',
                            params: Ext.apply({
                                eventParams: Ext.encode(form.down('#eventParams').getValue())
                            },loadParams),
                            form: form.getForm(),
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]
    });

    if (moduleParams)
        form.getForm().setValues(moduleParams);

    form.getForm().clearInvalid();
    return form;
});
Scalr.regPage('Scalr.ui.farms.extendedinfo', function (loadParams, moduleParams) {
    if (Scalr.flags['analyticsEnabled']) {
        Ext.Array.insert(moduleParams['info'], 1, [{
            xtype: 'farmcostmetering',
            itemId: 'costMetering',
            title: 'Cost metering',
            cls: 'x-fieldset-separator-bottom'
        }]);
    }
	var form = Ext.create('Ext.form.Panel', {
		title: 'Farm "' + moduleParams['name'] + '" extended information',
        preserveScrollPosition: true,
		scalrOptions: {
			'modal': true
		},
        layout: 'auto',//default 'anchor' layout causes [E]Layout run failed
        bodyStyle: 'overflow-x: hidden!important', //required for layout auto
		width: 860,
		items: moduleParams['info'],
		tools: [{
			type: 'refresh',
			handler: function () {
				Scalr.event.fireEvent('refresh');
			}
		}, {
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}]
	});

    if (Scalr.flags['analyticsEnabled']) {
        form.down('#costMetering').setValue(moduleParams['analytics']);
        form.down('#projectId').setValue(moduleParams['projectId']).setReadOnly(true);
        form.down('#projectId').labelWidth = 130;
        form.down('#costMetering').refresh(moduleParams['roles'])
    }

    if (form.down('#lease')) {
        var fs = form.down('#lease'), dt = new Date(fs.params['terminateDate']), stDt = Ext.Date.add(dt, Ext.Date.DAY, fs.params['standardLifePeriod']);
        var saveHandler = function() {
            var params = this.up('#lease').getFieldValues(true);
            params['farmId'] = moduleParams['id'];
            params['extend'] = this.up('#lease').down('#extend').getActiveTab().tabConfig.value;

            Scalr.Request({
                confirmBox: {
                    type: 'action',
                    msg: 'Please confirm that you want to extend the farm termination date?'
                },
                processBox: {
                    type: 'action'
                },
                url: '/farms/xLeaseExtend',
                params: params,
                success: function() {
                    Scalr.event.fireEvent('refresh');
                }
            });
        };

        var items = [{
            xtype: 'displayfield',
            fieldLabel: 'Current farm termination date',
            value: fs['params']['localeTerminateDate']
        }, {
            xtype: 'displayfield',
            fieldLabel: 'Standard extensions remain',
            hidden: !!fs.params['nonStandardExtendInProgress'],
            value: fs.params['standardExtendRemain']
        }];
        if (fs.params['farmLaunchPermission']) {
            items.push.apply(items, [{
                xtype: 'container',
                hidden: !fs.params['nonStandardExtendInProgress'],
                layout: 'hbox',
                items: [{
                    xtype: 'displayfield',
                    value: fs.params['nonStandardExtendInProgress']
                }, {
                    xtype: 'button',
                    margin: '0 0 0 12',
                    width: 80,
                    text: 'Cancel',
                    handler: function () {
                        var params = {
                            farmId: moduleParams['id'],
                            extend: 'cancel'
                        };

                        Scalr.Request({
                            confirmBox: {
                                type: 'action',
                                msg: 'Are you sure want to cancel the non-standard request ?'
                            },
                            processBox: {
                                type: 'action'
                            },
                            url: '/farms/xLeaseExtend',
                            params: params,
                            success: function() {
                                Scalr.event.fireEvent('refresh');
                            }
                        });
                    }
                }]
            }, {
                xtype: 'displayfield',
                value: fs.params['nonStandardExtendLastError'],
                hidden: !fs.params['nonStandardExtendLastError'],
                cls: 'x-form-field-warning'
            }, {
                xtype: 'tabpanel',
                itemId: 'extend',
                cls: 'x-tabs-dark',
                margin: '18 0 0 0',
                hidden: !fs.params['standardExtend'] && !fs.params['nonStandardExtend'],
                activeTab: fs.params['standardExtend'] ? 'standard' : 'nonStandard',
                items: [{
                    xtype: 'container',
                    tabConfig: {
                        title: 'Standard extenstion',
                        value: 'standard'
                    },
                    cls: 'x-container-fieldset',
                    itemId: 'standard',
                    disabled: !fs.params['standardExtend'],
                    items: [{
                        xtype: 'displayfield',
                        fieldLabel: 'Standard extension term length',
                        labelWidth: 230,
                        value: fs.params['standardLifePeriod'] + ' Days'
                    }, {
                        xtype: 'displayfield',
                        fieldLabel: 'New termination date',
                        labelWidth: 230,
                        value: Ext.Date.format(stDt, 'M j, Y')
                    }, {
                        xtype: 'button',
                        text: 'Request extension',
                        handler: saveHandler
                    }]
                }, {
                    xtype: 'container',
                    tabConfig: {
                        title: 'Non-standard extension',
                        value: 'non-standard'
                    },
                    cls: 'x-container-fieldset',
                    itemId: 'nonStandard',
                    disabled: !fs.params['nonStandardExtend'],
                    items: [{
                        xtype: 'container',
                        layout: 'hbox',
                        margin: '0 0 6 0',
                        items: [{
                            xtype: 'buttongroupfield',
                            labelWidth: 80,
                            fieldLabel: 'Extend by',
                            name: 'by',
                            value: 'days',
                            width: 320,
                            items: [{
                                text: 'days',
                                allowBlank: false,
                                value: 'days',
                                width: 70
                            }, {
                                text: 'date',
                                value: 'date',
                                width: 70
                            }, {
                                text: 'forever',
                                value: 'forever',
                                width: 90
                            }],
                            listeners: {
                                boxready: function() {
                                    this.next('[name="byDays"]').fireEvent('change');
                                },
                                change: function(field, value) {
                                    this.next('[name="byDays"]')[value == 'days' ? 'show' : 'hide']();
                                    this.next('[name="byDate"]')[value == 'date' ? 'show' : 'hide']();
                                    this.up('#nonStandard').down('#nonStandardTermDate')[value == 'forever' ? 'hide' : 'show']();

                                    if (value == 'days')
                                        this.next('[name="byDays"]').fireEvent('change');
                                    else if (value == 'date')
                                        this.next('[name="byDate"]').fireEvent('change');
                                }
                            }
                        }, {
                            xtype: 'textfield',
                            name: 'byDays',
                            allowBlank: false,
                            margin: '0 0 0 5',
                            value: 20,
                            width: 125,
                            listeners: {
                                change: function() {
                                    if (this.prev('[name="by"]').getValue() == 'days' && this.getValue()) {
                                        var stDt = Ext.Date.add(dt, Ext.Date.DAY, this.getValue());
                                        this.up('#nonStandard').down('#nonStandardTermDate').setValue(Ext.Date.format(stDt, 'M j, Y'));
                                    }
                                }
                            }
                        }, {
                            xtype: 'datefield',
                            margin: '0 0 0 5',
                            width: 125,
                            minValue: fs.params['terminateDate'],
                            format: 'Y-m-d',
                            name: 'byDate',
                            hidden: true,
                            listeners: {
                                change: function() {
                                    if (this.prev('[name="by"]').getValue() == 'date' && this.getValue()) {
                                        var stDt = new Date(this.getValue());
                                        this.up('#nonStandard').down('#nonStandardTermDate').setValue(Ext.Date.format(stDt, 'M j, Y'));
                                    }
                                }
                            }
                        }]
                    }, {
                        xtype: 'textarea',
                        fieldLabel: 'Comment',
                        labelWidth: 80,
                        width: 450,
                        name: 'comment'
                    }, {
                        xtype: 'displayfield',
                        fieldLabel: 'New termination date',
                        labelWidth: 160,
                        itemId: 'nonStandardTermDate'
                    }, {
                        xtype: 'button',
                        text: 'Request extension',
                        handler: saveHandler
                    }]
                }]
            }]);
        }
        fs.add(items);
    }

	return form;
});

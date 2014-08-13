Scalr.regPage('Scalr.ui.farms.extendedinfo', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		title: 'Farm "' + moduleParams['name'] + '" extended information',
		scalrOptions: {
			'modal': true
		},
		width: 800,
		fieldDefaults: {
			labelWidth: 160
		},
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
	
	if (form.down('#repo')) {
		form.down('#repo').store.load({
			data: moduleParams['scalarizr_repos']
		});
	}
	
	if (form.down('#updSettingsSave')) {
		form.down('#updSettingsSave').on('click', function(){
			
			var params = form.getForm().getValues(),
                isValid = true,
                validators = {
                    hh: /^(\*|(1{0,1}\d|2[0-3])(-(1{0,1}\d|2[0-3])){0,1}(,(1{0,1}\d|2[0-3])(-(1{0,1}\d|2[0-3])){0,1})*)(\/(1{0,1}\d|2[0-3])){0,1}$/,
                    dd: /^(\*|([12]{0,1}\d|3[01])(-([12]{0,1}\d|3[01])){0,1}(,([12]{0,1}\d|3[01])(-([12]{0,1}\d|3[01])){0,1})*)(\/([12]{0,1}\d|3[01])){0,1}$/,
                    dw: /^(\*|[0-6](-([0-6])){0,1}(,([0-6])(-([0-6])){0,1})*)(\/([0-6])){0,1}$/i
                };
			params['farmId'] = loadParams['farmId'];
			Ext.Object.each(validators, function(name, regexp){
                var field = form.getForm().findField(name);
                isValid = regexp.test(field.getValue());
                isValid || field.markInvalid('Invalid format');
                return isValid;
            });
            if (isValid) {
                Scalr.Request({
                    processBox: {
                        type: 'action',
                        //msg: 'Saving auto-update configuration. This operation can take a few minutes, please wait...'
                        msg:  'Saving configuration ...'
                    },
                    url: '/farms/xSaveSzrUpdSettings/',
                    params: params,
                    success: function(){
                        //Scalr.event.fireEvent('refresh');
                    }
                });
            }
		});
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
                    msg: 'Are you sure want to extend farm expiration date ?'
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

        fs.add([{
            xtype: 'displayfield',
            fieldLabel: 'Current farm termination date',
            labelWidth: 190,
            value: fs['params']['localeTerminateDate']
        }, {
            xtype: 'displayfield',
            fieldLabel: 'Standard extensions remain',
            labelWidth: 190,
            hidden: !!fs.params['nonStandardExtendInProgress'],
            value: fs.params['standardExtendRemain']
        }, {
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
                    labelWidth: 190,
                    value: fs.params['standardLifePeriod'] + ' Days'
                }, {
                    xtype: 'displayfield',
                    fieldLabel: 'New termination date',
                    labelWidth: 190,
                    value: Ext.Date.format(stDt, 'M j, Y')
                }, {
                    xtype: 'button',
                    text: 'Request extension',
                    handler: saveHandler,
                    height: 32,
                    width: 155
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
                        width: 300,
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
                            width: 70
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
                    width: 430,
                    name: 'comment'
                }, {
                    xtype: 'displayfield',
                    fieldLabel: 'New termination date',
                    labelWidth: 140,
                    itemId: 'nonStandardTermDate'
                }, {
                    xtype: 'button',
                    text: 'Request extension',
                    handler: saveHandler,
                    height: 32,
                    width: 155
                }]
            }]
        }]);
    }

	return form;
});

Scalr.regPage('Scalr.ui.account2.billing.updateCreditCard', function (loadParams, moduleParams) {

	var form = Ext.create('Ext.form.Panel', {
		width: 600,
		title: 'Billing &raquo; Update CreditCard Information',
		fieldDefaults: {
			anchor: '100%',
            labelWidth: 110
		},
		scalrOptions: {
			'modal': true
		},
        bodyCls: 'x-container-fieldset',
        bodyStyle: 'padding-bottom:0',
		items: [{
			xtype: 'displayfield',
			cls: 'x-form-field-info',
			value: 'Your card will be pre-authorized for $1. <a href="http://en.wikipedia.org/wiki/Authorization_hold">What does this mean?</a>'
		},{
			xtype: 'fieldcontainer',
			fieldLabel: 'Card number',
			heigth: 24,
			layout: {
                type: 'hbox',
                align: 'middle'
            },
			items: [{
				xtype: 'textfield',
				name: 'ccNumber',
                flex: 1,
                validator: function (value) {
                    if (!/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3,4}$/.test(value)) {
                        return 'Valid credit card number format is XXXX-XXXX-XXXX-XXX(X)';
                    }
                    return true;
                },
				emptyText: moduleParams['billing']['ccNumber'],
				value: ''
			},
			{ xtype: 'component', height: 23, width: 37, margin: '0 0 0 5', html: '<img src="/ui2/images/ui/billing/cc_visa.png" />'},
			{ xtype: 'component', height: 23, width: 37, margin: '0 0 0 5', html: '<img src="/ui2/images/ui/billing/cc_mc.png" />'},
			{ xtype: 'component', height: 23, width: 37, margin: '0 0 0 5', html: '<img src="/ui2/images/ui/billing/cc_amex.png" />'},
			{ xtype: 'component', height: 23, width: 37, margin: '0 0 0 5', html: '<img src="/ui2/images/ui/billing/cc_discover.png" />'}
			]
		}, {
			xtype: 'fieldcontainer',
			fieldLabel: 'CVV code',
			layout: 'hbox',
			items: [{
				xtype: 'textfield',
				name: 'ccCvv',
				width: 50,
                allowBlank: false,
				value: ''
			},
			{ xtype: 'displayfield', value:'Exp. date', margin: '0 0 0 20' },
			{
				xtype: 'combo',
				name: 'ccExpMonth',
				margin: '0 0 0 5',
				hideLabel: true,
				editable: false,
				value:'01',
                flex: 1,
				store: {
					fields: [ 'name', 'description' ],
					proxy: 'object',
					data:[
						{name:'01', description:'01 - January'},
						{name:'02', description:'02 - February'},
						{name:'03', description:'03 - March'},
						{name:'04', description:'04 - April'},
						{name:'05', description:'05 - May'},
						{name:'06', description:'06 - June'},
						{name:'07', description:'07 - July'},
						{name:'08', description:'08 - August'},
						{name:'09', description:'09 - September'},
						{name:'10', description:'10 - October'},
						{name:'11', description:'11 - November'},
						{name:'12', description:'12 - December'}
					]
				},
				valueField: 'name',
				displayField: 'description',
				queryMode: 'local'
			}, {
				xtype: 'combo',
				name: 'ccExpYear',
				margin: '0 0 0 5',
				width: 80,
				value: '2015',
				hideLabel: true,
				editable: false,
				store: {
					fields: [ 'name', 'description' ],
					proxy: 'object',
					data:[
						{name:'2011', description:'2011'},
						{name:'2012', description:'2012'},
						{name:'2013', description:'2013'},
						{name:'2014', description:'2014'},
						{name:'2015', description:'2015'},
						{name:'2016', description:'2016'},
						{name:'2017', description:'2017'},
						{name:'2018', description:'2018'},
						{name:'2019', description:'2019'},
						{name:'2020', description:'2020'},
						{name:'2021', description:'2021'},
						{name:'2022', description:'2022'}
					]
				},
				valueField: 'name',
				displayField: 'description',
				queryMode: 'local'
			}
			]
		}, {
			xtype: 'textfield',
			name:'firstName',
			fieldLabel: 'First name',
            allowBlank: false,
			value: moduleParams['firstName']
		}, {
			xtype: 'textfield',
			name:'lastName',
			fieldLabel: 'Last name',
            allowBlank: false,
			value: moduleParams['lastName']
		}, {
			xtype: 'textfield',
			name:'postalCode',
			fieldLabel: 'Postal code',
            allowBlank: false,
			value: ''
		}],
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}],

		dockedItems: [{
			xtype: 'container',
			cls: 'x-docked-buttons',
			dock: 'bottom',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Update',
				handler: function() {
                    if (this.up('form').isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/account/billing/xUpdateCreditCard/',
                            form: this.up('form').getForm(),
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
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

	return form;
});

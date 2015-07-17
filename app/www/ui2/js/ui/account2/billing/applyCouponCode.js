Scalr.regPage('Scalr.ui.account2.billing.applyCouponCode', function (loadParams, moduleParams) {
	
	var form = Ext.create('Ext.form.Panel', {
		width: 500,
		title: 'Billing &raquo; Apply coupon code',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			'modal': true
		},
        bodyCls: 'x-container-fieldset',
        bodyStyle: 'padding-bottom:0',
		items: [{
			xtype: 'textfield',
			name:'couponCode',
			fieldLabel: 'Coupon code',
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
				text: 'Apply',
				handler: function() {
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						url: '/account/billing/xApplyCouponCode/',
						form: this.up('form').getForm(),
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

	return form;
});

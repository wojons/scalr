Scalr.regPage('Scalr.ui.services.rabbitmq.status', function (loadParams, moduleParams) {
    var buttons = [],
        farmRoleId,
        rabbitmqData;
    Ext.each(moduleParams['list'], function(farmRole){
        var button = {
            tooltip: farmRole.alias,
            text: Ext.String.ellipsis(farmRole.alias, 26),
            href: '#/services/rabbitmq/status?farmId=' + loadParams.farmId + '&farmRoleId=' + farmRole.id
        };
        if (farmRole.data !== undefined) {
            farmRoleId = farmRole.id;
            rabbitmqData = farmRole.data;
            button.pressed = true;
        }
        buttons.push(button);
    });
	var panel = Ext.create('Ext.form.Panel', {
		width: 800,
        minHeight: 400,
        scalrOptions: {
            'modal': true
        },
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 130
		},
		items: [{
			xtype: 'fieldset',
			title: 'RabbitMQ access credentials',
            cls: 'x-fieldset-separator-none',
			items: [{
				xtype: 'displayfield',
				fieldLabel: 'Login',
				hidden: !rabbitmqData['password'],
				value: 'scalr'
			}, {
				xtype: 'displayfield',
				fieldLabel: 'Password',
				hidden: !rabbitmqData['password'],
				value: rabbitmqData['password']
			}, {
				xtype: 'fieldcontainer',
				fieldLabel: 'Control panel',
				hideLabel: !rabbitmqData['showStatusLabel'],
				layout: {
					type: 'hbox'
				},
				items: [{
					xtype: 'displayfield',
					hidden: rabbitmqData['url'] ? false : true,
					margin: '0 3 0 0',
					value: '<a href="' + rabbitmqData['url'] + '" target="_blank">' + rabbitmqData['url'] + '</a>'
				}, {
					xtype: 'button',
					hidden: !rabbitmqData['showSetup'],
					margin: '0 3 0 0',
					name: 'setupCP',
					text: 'Setup Control panel',
					handler: function(){
						Scalr.Request({
							processBox: {
								type: 'action'
							},
							url: '/services/rabbitmq/xSetupCp/',
							params: {
								farmRoleId: farmRoleId
							},
							success: function(data) {
								panel.down('[name="status"]').show().setValue(data.status);
								panel.down('[name="setupCP"]').hide();
							}
						});
					}
				}]
            }, {
                xtype: 'displayfield',
                name: 'status',
                hidden: rabbitmqData['status'] ? false : true,
                value: rabbitmqData['status']
			}]
		}],
        dockedItems:[{
            xtype: 'container',
            itemId: 'tabs',
            dock: 'left',
            width: 240,
            overflowY: 'auto',
            overflowX: 'hidden',
            weight: 2,
            cls: 'x-docked-tabs x-docked-tabs-light',
            hidden: (moduleParams['list'] ||[]).length < 2,
            defaults: {
                xtype: 'button',
                ui: 'tab',
                toggleGroup: 'rabbitmq-status-tabs',
                textAlign: 'left',
                allowDepress: false,
                hrefTarget: '_self',
                disableMouseDownPressed: true

            },
            items: buttons
        },{
            xtype: 'container',
            itemId: 'toolbar',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'close',
                handler: function() {
                    Scalr.event.fireEvent('redirect', '#/farms');
                }
            }]
        }]
	});

	if (rabbitmqData['overview']) {
		var overview = rabbitmqData['overview'] || {},
            queueTotals = overview['queue_totals'] || {};
		panel.add({
			xtype: 'fieldset',
			title: 'Overview',
            cls: 'x-fieldset-separator-top',
			defaults: {
				labelWidth: 160
			},
			items: [{
				xtype: 'displayfield',
				fieldLabel: 'Management version',
				value: overview['management_version']
			}, {
				xtype: 'displayfield',
				fieldLabel: 'Ready queued messages',
				value: queueTotals['messages_ready']
			}, {
				xtype: 'displayfield',
				fieldLabel: 'Unacknowledged queued messages',
				value: queueTotals['messages_unacknowledged']
			}, {
				xtype: 'displayfield',
				fieldLabel: 'Total queued messages',
				value: queueTotals['messages']
			}]
		});
	}

	return panel;
});

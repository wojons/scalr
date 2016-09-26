Scalr.regPage('Scalr.ui.tools.aws.vpc.createSubnet', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
		title: 'Create VPC subnet',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modalWindow: true
		},
		width: 600,
        defaults: {
            labelWidth: 130
        },
        bodyCls: 'x-container-fieldset x-fieldset-no-bottom-padding',
		items: [{
            xtype: 'textfield',
            name: 'vpcId',
            fieldLabel: 'VPC ID',
            readOnly: true,
            hideInputOnReadOnly: true,
            value: loadParams['vpcId']
        },{
            xtype: 'textfield',
            name: 'name',
            fieldLabel: 'Name'
        },{
            xtype: 'combo',
            name: 'availZone',
            fieldLabel: 'Availability zone',
            valueField: 'zoneId',
            displayField: 'name',

            editable: false,
            queryCaching: false,
            minChars: 0,
            queryDelay: 10,
            value: '',
            emptyText: 'AWS-chosen',
            store: {
                fields: [ 'id', 'name', {name: 'zoneId', mapping: 'id' }],
                proxy: {
                    type: 'cachedrequest',
                    url: '/platforms/ec2/xGetAvailZones',
                    params: {cloudLocation: loadParams['cloudLocation']},
                    prependData: [{id: '', name: 'AWS-chosen'}]
                }
            }
        },{
            xtype: 'textfield',
            name: 'subnet',
            fieldLabel: 'CIDR block',
            emptyText: 'eg. 10.0.0.0/24',
            allowBlank: false,
            value: moduleParams['subnet']
        },{
            xtype: 'combo',
            name: 'routeTableId',
            fieldLabel: 'Routing table',
            valueField: 'id',
            displayField: 'id',
            emptyText: 'VPC default',

            queryCaching: false,
            minChars: 0,
            forceSelection: true,
            store: {
                fields: [ 'id', 'name', 'main', 'type' ],
                proxy: {
                    type: 'cachedrequest',
                    url: '/tools/aws/vpc/xListRoutingTables',
                    params: {
                        cloudLocation: loadParams['cloudLocation'],
                        vpcId: loadParams['vpcId']
                    },
                    filterFields: ['id', 'name']
                }
            },
            listConfig: {
                style: 'white-space:nowrap',
                cls: 'x-boundlist-alt',
                tpl:
                    '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto;line-height:20px">' +
                        '<div>{[values.name || \'<i>No name</i>\' ]} - <b>{id}</b> <span style="font-style: italic;font-size:90%">{[values.main ? \'(VPC default)\' : \'\']}</span></div>' +
                        '<div>Type: <b>{type:capitalize}</b></div>' +
                    '</div></tpl>'
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
				text: 'Create',
				handler: function() {
					if (form.getForm().isValid()) {
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							params: {
                                cloudLocation: loadParams['cloudLocation']
                            },
							form: form.getForm(),
							url: '/tools/aws/vpc/xCreateSubnet',
							success: function (data) {
								if (data['subnet']) {
									Scalr.event.fireEvent('update', '/tools/aws/vpc/createSubnet', data['subnet']);
								}
								form.close();
							}
						});
					}
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					form.close();
				}
			}]
		}]
	});

	return form;
});

Scalr.regPage('Scalr.ui.tools.aws.vpc.createSubnet', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		title: 'Create VPC subnet',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modal: true
		},
		width: 600,
        defaults: {
            labelWidth: 120
        },
        bodyCls: 'x-container-fieldset x-fieldset-no-bottom-padding',
		items: [{
            xtype: 'combo',
            name: 'vpcId',
            emptyText: 'Please select a VPC ID',
            editable: false,
            fieldLabel: 'VPC ID',
            hideInputOnReadOnly: true,
            queryCaching: false,
            clearDataBeforeQuery: true,
            store: {
                fields: [ 'id', 'name', 'info' ],
                proxy: {
                    type: 'cachedrequest',
                    url: '/platforms/ec2/xGetVpcList',
                    root: 'vpc',
                    ttl: 1,
                    params: {cloudLocation: loadParams['cloudLocation']}
                }
            },
            valueField: 'id',
            displayField: 'name',
            allowBlank: false,
            listeners: {
                change: function(field, value) {
                    this.next('[name="routeTableId"]').store.proxy.params['vpcId'] = value;
                }
            }
        },{
            xtype: 'textfield',
            name: 'name',
            fieldLabel: 'Name'
        },{
            xtype: 'combo',
            name: 'availZone',
            fieldLabel: 'Availability zone',
            valueField: 'id',
            displayField: 'name',

            editable: false,
            queryCaching: false,
            minChars: 0,
            queryDelay: 10,
            value: '',
            emptyText: 'AWS-chosen',
            store: {
                fields: [ 'id', 'name' ],
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
            allowBlank: false
        },{
            xtype: 'combo',
            name: 'routeTableId',
            fieldLabel: 'Routing table',
            valueField: 'id',
            displayField: 'id',
            emptyText: 'VPC default',

            //editable: false,
            queryCaching: false,
            minChars: 0,
            queryDelay: 10,
            clearDataBeforeQuery: true,
            restoreValueOnBlur: true,
            store: {
                fields: [ 'id', 'name', 'main', 'type' ],
                proxy: {
                    type: 'cachedrequest',
                    url: '/tools/aws/vpc/xListRoutingTables',
                    params: {cloudLocation: loadParams['cloudLocation']},
                    filterFields: ['id', 'name']
                }
            },
            listeners: {
                beforequery: function() {
                    var vpcId = this.prev('[name="vpcId"]');
                    if (!vpcId.getValue()) {
                        this.collapse();
                        Scalr.message.InfoTip('Select VPC ID first.', vpcId.inputEl);
                        return false;
                    }
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

	form.getForm().setValues({
        vpcId: loadParams['vpcId'],
		subnet: moduleParams['subnet']
	});

    if (loadParams['vpcId']) {
        form.getForm().findField('vpcId').setReadOnly(true);
    }

	return form;
});

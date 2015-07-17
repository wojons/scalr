Scalr.regPage('Scalr.ui.farms.roles.replaceRole', function (loadParams, moduleParams) {
	return Scalr.utils.Window({
        xtype: 'form',
		scalrOptions: {
			modalWindow: true
		},
		width: 800,
        layout: 'fit',
        bodyCls: 'x-container-fieldset',
		items: [{
            xtype: 'grid',
            margin: '0 0 0 -20',
            padding: '0 0 0 20',
            plugins: [{
                pluginId: 'rowpointer',
                ptype: 'rowpointer',
                align: 'left',
                addCls: 'x-panel-row-pointer-star',
                tooltip: 'Current role',
                getPointerRecord: function() {
                    return this.client.store.getById(moduleParams['roleId']);
                }
            }, 'selectedrecord'],
            viewConfig: {
                emptyText: 'No suitable roles found',
                deferEmptyText: false
            },
            store: {
                fields: ['id', 'name', 'osId', 'shared', 'image', 'behaviors'],
                proxy: 'object',
                data: moduleParams['roles'],
                sorters: [{
                    property: 'osId',
                    direction: 'ASC'
                }]
            },
            columns: [{
                xtype: 'templatecolumn',
                text: 'Role',
                dataIndex: 'name',
                flex: 1,
                tpl: new Ext.XTemplate('{[this.getScope(values.shared)]}&nbsp;&nbsp;{name}',
                    {
                        getScope: function(shared){
                            var scope = shared ? 'scalr' : 'environment';
                            return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qclass="x-tip-light" data-qtip="' + Scalr.utils.getScopeLegend('role') + '"/>';
                        }
                    }
                )

            },{
                xtype: 'templatecolumn',
                text: 'OS',
                dataIndex: 'osId',
                flex: .6,
                tpl:
                    '{[this.getOsById(values.osId)]} '+
                    '{[values.image.architecture==\'i386\'?32:64]}bit'

            },{
                xtype: 'templatecolumn',
                align: 'right',
                sortable: false,
                resizeable: false,
                tpl  : new Ext.XTemplate('{[this.renderBehaviors(values.behaviors)]}',{
                    renderBehaviors: function(behaviors) {
                        var res = [];
                        Ext.Array.each(behaviors, function(behavior){
                            res.push('<img style="margin:0 3px 0 0" title="' + Scalr.utils.beautifyBehavior(behavior) + '" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-role-small x-icon-role-small-' + behavior + '"/>');
                        });
                        return res.join('&nbsp;');
                    }
                })
            }],
            listeners: {
                viewready: function(){
                    var grid = this,
                        record = this.store.getById(moduleParams['roleId']);
                    grid.setSelectedRecord(record);
                    grid.on('selectedrecordchange', function(record){
                        if (record) {
                            grid.up('form').down('#replace').setDisabled(record.get('id') == moduleParams['roleId']);
                        }
                    });
                }
            }
        }],
        dockedItems:[{
            xtype: 'container',
            dock: 'top',
            cls: 'x-container-fieldset x-fieldset-no-bottom-padding',
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                html: 'Select replacement role'
            },{
                xtype: 'displayfield',
                cls: 'x-form-field-warning',
                value: 'Replacing this role may lead to broken functionality! Currently running instances will NOT be replaced, only NEW instances will use this replaced role.',
                style: 'width: 100%',
                margin: 0
            }]
        },{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                itemId: 'replace',
                text: 'Replace',
                disabled: true,
                handler: function() {
                    var me = this,
                        record = me.up('form').down('grid').getSelectedRecord();
                    if (record) {
                        Scalr.Request({
							confirmBox: {
								type: 'action',
								msg: 'Are you sure you want to replace this role?<br>' +
								'This change will be saved immediately!'
							},
                            processBox: {
                                type: 'save'
                            },
                            url: '/farms/' + loadParams['farmId'] + '/roles/' + loadParams['farmRoleId'] + '/xReplaceRole',
                            params: {
                                roleId: record.get('id')
                            },
                            success: function (data) {
                                Scalr.event.fireEvent('update', '/farms/roles/replaceRole', {
                                    farmId: loadParams['farmId'],
                                    farmRoleId: loadParams['farmRoleId'],
                                    role: data.role
                                });
                                me.up('form').close();
                            }
                        });
                    }
                }
            }, {
                xtype: 'button',
                itemId: 'cancel',
                text: 'Cancel',
                margin: '0 0 0 24',
                handler: function() {
                    this.up('form').close();
                }
            }]
        }]

	});
});

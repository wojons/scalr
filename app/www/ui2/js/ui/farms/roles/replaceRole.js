Scalr.regPage('Scalr.ui.farms.roles.replaceRole', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		scalrOptions: {
			modal: true
		},
		width: 700,
        layout: 'fit',
        bodyCls: 'x-container-fieldset',
		items: [{
            xtype: 'grid',
            hideHeaders: true,
            margin: '0 0 0 -32',
            padding: '0 0 0 32',
            cls: 'x-grid-shadow x-grid-no-selection',
            plugins: [{
                pluginId: 'rowpointer',
                ptype: 'rowpointer',
                align: 'left',
                addCls: 'x-panel-row-pointer-star',
                tooltip: 'Current role',
                getPointerRecord: function() {
                    return this.client.store.getById(moduleParams['roleId']);
                }
            }],
            store: {
                fields: ['id', 'name', 'os_name', 'os_family', 'os_generation', 'os_version', 'shared', 'image', 'behaviors'],
                proxy: 'object',
                data: moduleParams['roles'],
                sortOnLoad: true,
                sorters: [{
                    property: 'os_version',
                    direction: 'ASC'
                }]
            },
            columns: [{
                xtype: 'templatecolumn',
                flex: 1,
                tpl: 
                    '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-osfamily-small x-icon-osfamily-small-{os_family}"/>&nbsp;&nbsp;'+
                    '{os_name} {[values.image.architecture==\'i386\'?32:64]}bit'+
                    '&nbsp;&nbsp;(<tpl if="shared"><span style="color:#46A657" title="Quick start role.">{name}</span><tpl else>{name}</tpl>)'
                    
            },{
                xtype: 'templatecolumn',
                align: 'right',
                tpl  : new Ext.XTemplate('{[this.renderBehaviors(values.behaviors)]}',{
                    renderBehaviors: function(behaviors) {
                        var res = [];
                        Ext.Array.each(behaviors, function(behavior){
                            res.push('<img title="' + Scalr.utils.beautifyBehavior(behavior) + '" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-role-small x-icon-role-small-' + behavior + '"/>');
                        });
                        return res.join('&nbsp;');
                    }
                })
            }],
            viewConfig: {
                listeners: {
                    viewready: function(){
                        var grid = this,
                            selModel = this.getSelectionModel(),
                            record = this.store.getById(moduleParams['roleId']);
                        selModel.setLastFocused(record);
                        selModel.on('focuschange', function(selModel, oldFocused, newFocused){
                            if (newFocused) {
                                grid.up('form').down('#replace').setDisabled(newFocused.get('id') == moduleParams['roleId']);
                            }
                        })
                    }
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
                    var record = this.up('form').down('grid').getSelectionModel().getLastFocused();
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
                                Scalr.event.fireEvent('close');
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
                    Scalr.event.fireEvent('close');
                }
            }]
        }]

	});
});

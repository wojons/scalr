Scalr.regPage('Scalr.ui.core.settings', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
        scrollable: true,
        //minWidth: 1060,
		scalrOptions: {
			maximize: 'all',
            menuTitle: 'Settings',
            menuHref: '#/core/settings'
		},
        fieldDefaults: {
            labelWidth: 80
        },
		items: [{
			xtype: 'container',
            cls: 'x-fieldset-separator-bottom',
			layout: 'hbox',
            defaults: {
                minWidth: 300,
                maxWidth: 460,
                minHeight: 150,
                flex:1
            },
			items: [{
				xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
				title: 'Profile information',
				items: [{
					xtype: 'displayfield',
					name: 'user_email',
					fieldLabel: 'Email',
					readOnly: true
				},{
					xtype: 'textfield',
					name: 'user_fullname',
					fieldLabel: 'Full name',
					anchor: '100%'
                }]
			},{
				xtype: 'fieldset',
                title: 'Avatar settings',
                cls: 'x-fieldset-separator-left',
				items: [{
                    xtype: 'image',
                    style: 'position:absolute;right:24px;top:16px;border-radius:4px',
                    width: 46,
                    height: 46,
                    src: Scalr.utils.getGravatarUrl(moduleParams['gravatar_hash'], 'large')
                }, {
                    xtype: 'displayfield',
                    value: '<a href="http://gravatar.com/" target="blank">Change your avatar at Gravatar.com</a>'
                }, {
                    xtype: 'textfield',
					name: 'gravatar_email',
					fieldLabel: 'Gravatar email',
					vtype: 'email',
                    labelWidth: 110,
					anchor: '100%'
				}]
			},{
                xtype: 'fieldset',
                title: 'User interface',
                cls: 'x-fieldset-separator-left',
                defaults: {
                    labelWidth: 160,
                    anchor: '100%'
                },
                items: [{
					xtype: 'buttongroupfield',
					fieldLabel: 'Dashboard columns',
					name: 'dashboard_columns',
					value: moduleParams['dashboard_columns'],
                    defaults: {
                        flex: 1
                    },
                    layout: 'hbox',
					items: [{
						text: '1',
						value: '1'
					}, {
						text: '2',
						value: '2'
					}, {
						text: '3',
						value: '3'
					}, {
						text: '4',
						value: '4'
					}, {
						text: '5',
						value: '5'
					}]
                },{
                    xtype: 'combo',
                    fieldLabel: 'Timezone',
                    store: moduleParams['timezones_list'],
                    allowBlank: false,
                    forceSelection: true,
                    editable: false,
                    name: 'timezone',
                    queryMode: 'local',
                    anyMatch: true
                }]

            }]
		}, {
			xtype: 'container',
			layout: 'hbox',
            cls: 'x-fieldset-separator-bottom',
            hidden: true,
			items: [{
				xtype: 'fieldset',
				title: 'Default table length',
				flex: 1,
                cls: 'x-fieldset-separator-left',
                items: [{
                    xtype: 'buttongroupfield',
                    fieldLabel: 'Items per page',
                    labelWidth: 110,
                    value: Ext.state.Manager.get('grid-ui-page-size', 'auto'),
                    items: [{
                        text: 'Auto',
                        value: 'auto',
                        width: 65
                    }, {
                        text: '10',
                        value: 10,
                        width: 45
                    }, {
                        text: '25',
                        value: 25,
                        width: 45
                    }, {
                        text: '50',
                        value: 50,
                        width: 45
                    }, {
                        text: '100',
                        value: 100,
                        width: 55
                    }],
                    submitValue: false,
                    listeners: {
                        change: function(component, newValue) {
                            Ext.state.Manager.set('grid-ui-page-size', newValue);
                        }
                    }
				}]
			}]
        },{
            xtype: 'fieldset',
            title: 'Bookmarks bar&nbsp;&nbsp;<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qtip="Drag and drop Bookmarks to reorder them, or click X to remove a Bookmark." />',
            items: [{
                xtype: 'dataview',
                itemId: 'bookmarks',
                cls: 'x-dataview-list',
                plugins: [{
                    ptype: 'scalrviewdragdrop',
                    pluginId: 'scalrviewdragdrop'
                }],
                store: {
                    proxy: 'object',
                    fields: ['stateId', 'text', 'ordering'],
                    sorters: [{
                        property: 'ordering'
                    }],
                    data: Ext.Array.map(Scalr.utils.getFavorites(Scalr.scope), function(item, index){
                        return {
                            stateId: item.stateId,
                            text: item.text,
                            ordering: index
                        }
                    }),
                    listeners: {
                        remove: function() {
                            this.resetOrdering();
                        }
                    },
                    updateOrdering: function(record, ordering) {
                        var currentOrdering = record.get('ordering');
                        this.suspendEvents(true);
                        this.getUnfiltered().each(function(rec) {
                            var recOrdering = rec.get('ordering');
                            if (recOrdering >= ordering) {
                                rec.set('ordering', recOrdering + 1);
                            }
                        });
                        record.set('ordering', ordering);

                        this.getUnfiltered().each(function(rec) {
                            var recOrdering = rec.get('ordering');
                            if (recOrdering > currentOrdering) {
                                rec.set('ordering', recOrdering - 1);
                            }
                        });
                        //this.sort(farmRolesSorters);
                        this.resumeEvents();
                    },
                    resetOrdering: function() {
                        var data = this.queryBy(function(){return true;}),
                            index = 0;
                        data.sort([{property:'ordering'}]);
                        data.each(function(record) {
                            record.set('ordering', index++);
                        });
                        //this.sort(farmRolesSorters);
                    },

                },
                itemSelector: '.x-item',
                tpl  : new Ext.XTemplate(
                    '<tpl for=".">',
                        '<div class="x-item x-tagfield-item" style="padding-left:12px!important;margin: 0 12px 12px 0;background:#D7E4F2">' +
                            '<tpl if="stateId!=\'panel-admin-dashboard\' && stateId!=\'panel-account-dashboard\' && stateId!=\'panel-dashboard\'">'+
                                '<div class="x-tagfield-item-text" style="text-transform:uppercase;font:13px/28px OpenSansBold;">{text}</div>' +
                                '<div class="x-delete x-tagfield-item-close" style="top:10px"></div>'+
                            '<tpl else>'+
                                '<div class="x-tagfield-item-text" style="text-transform:uppercase;font:13px/28px OpenSansBold;padding-right:12px">{text}</div>' +
                            '</tpl>'+
                        '</div>',
                    '</tpl>'),
                listeners: {
                    beforeitemclick: function (view, record, item, index, e) {
                        if (e.getTarget('.x-delete', 10, true)) {
                            view.store.remove(record);
                            view.refresh();
                            return false;
                        }
                    },
                    drop: function(node, data, record, position) {//console.log(record.getData());return;
                        if (data.records[0]) {
                            var newIndex = record.get('ordering') + (position=='after' ? 1 : 0);
                            data.records[0].store.updateOrdering(data.records[0], newIndex);
                            this.refresh();
                        }
                    },

                }
            },{
                xtype: 'checkbox',
                submitValue: false,
                name: 'donNotShowFavoritesAddMessage',
                value: Ext.state.Manager.get('system-favorites-suppress-add-message', false),
                boxLabel: 'Do not show message after adding new link to Bookmarks bar',
                margin: 0
            }]
		},{
			xtype: 'fieldset',
			title: 'SSH Launcher settings&nbsp;&nbsp;<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-globalvars" data-qtip="All text fields in SSH applet settings support Global Variable Interpolation" />',
            collapsed: loadParams['ssh'] === undefined,
            collapsible: true,
            layout: 'hbox',
            items: [{
                xtype: 'container',
                flex: 2/3,
                maxWidth: 870,
                minWidth: 600,
                layout: 'anchor',
                defaults: {
                    anchor: '100%',
                    labelWidth: 140,
                    emptyText: 'Use default'
                },
                items: [{
                    xtype: 'buttongroupfield',
                    fieldLabel: 'SSH Launcher',
                    name: 'ssh.console.launcher',
                    hidden: !Scalr.flags['betaMode'],
                    defaults: {
                        width: 110
                    },
                    items: [{
                        text: 'Applet',
                        value: 'applet'
                    },{
                        text: 'Application',
                        value: 'application'
                    }]
                },{
                    xtype: 'textfield',
                    name: 'ssh.console.username',
                    fieldLabel: 'User name',
                    emptyText: 'root (scalr on GCE)'
                },{
                    xtype: 'combo',
                    name: 'ssh.console.ip',
                    fieldLabel: 'IP address',
                    editable: false,
                    emptyText: 'System default',
                    store: [['auto', 'System default'], ['public', 'Public'], ['private', 'Private']]
                },{
                    xtype: 'textfield',
                    name: 'ssh.console.port',
                    fieldLabel: 'Port',
                    emptyText: '22'
                },{
                    xtype: 'buttongroupfield',
                    hidden: !Scalr.isAllowed('SECURITY_SSH_KEYS'),
                    name: 'ssh.console.disable_key_auth',
                    fieldLabel: 'SSH Key Auth',
                    value: '0',
                    defaults: {
                        width: 110
                    },
                    items: [{
                        text: 'Disabled',
                        value: '1'
                    },{
                        text: 'Enabled',
                        value: '0'
                    }]
                },{
                    xtype: 'textfield',
                    name: 'ssh.console.key_name',
                    flex: 1,
                    fieldLabel: 'SSH key name',
                    emptyText: 'FARM-{SCALR_FARM_ID}-{SCALR_CLOUD_LOCATION}-' + moduleParams['scalr.id'],
                    hidden: !Scalr.isAllowed('SECURITY_SSH_KEYS'),
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        position: 'outer',
                        icons: {
                            id: 'info',
                            tooltip: 'Scalr will automatically provide the SSH keys it generates to use with your hosts to the SSH Launcher Applet. '+
                            'If you\'re using Scalr keys, we suggest keeping this default unchanged. <br/>However, if you\'d like to use a custom SSH Key '+
                            '(perhaps because you have configured SSH Key Governance), then you can simply add the key in ~/.ssh/scalr-ssh-keys. ' +
                            'Scalr will not override it. <br/>View <a href="http:/scalr-wiki.atlassian.net" target="_blank">this Wiki page for important information</a>.'
                        }
                    }]
                },{
                    xtype: 'buttongroupfield',
                    name: 'ssh.console.enable_agent_forwarding',
                    fieldLabel: 'Agent Forwarding',
                    value: '0',
                    defaults: {
                        width: 110
                    },
                    items: [{
                        text: 'Disabled',
                        value: '0'
                    },{
                        text: 'Enabled',
                        value: '1'
                    }]
                },{
                    xtype: 'combobox',
                    flex: 1,
                    store: [
                        ['', 'Auto detect'],
                        ['com.scalr.ssh.provider.mac.', 'Mac AppleScript + OpenSSH'],
                        ['com.scalr.ssh.provider.linux.LinuxGnomeTerminalSSHProvider', 'Linux + Gnome Terminal + OpenSSH'],
                        ['com.scalr.ssh.provider.linux.LinuxXTermSSHProvider', 'Linux + XTerm + OpenSSH'],
                        ['com.scalr.ssh.provider.mac.MacAppleScriptSSHProvider', 'Mac OS + AppleScript + OpenSSH'],
                        ['com.scalr.ssh.provider.mac.MacNativeSSHProvider', 'Mac OS + Terminal Configuration + OpenSSH'],
                        ['com.scalr.ssh.provider.mac.MacSSHProvider', 'Mac OS + Terminal bash Script + OpenSSH'],
                        ['com.scalr.ssh.provider.windows.WindowsPuTTYProvider', 'Windows + PuTTY'],
                        ['com.scalr.ssh.provider.windows.WindowsOpenSSHProvider', 'Windows + OpenSSH']
                    ],
                    emptyText: 'Auto detect',
                    name: 'ssh.console.preferred_provider',
                    editable: false,
                    fieldLabel: 'Preferred provider',
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        position: 'outer',
                        icons: {
                            id: 'info',
                            tooltip: Ext.String.htmlEncode('The applet automatically tries all providers available for your '+
                            'platform, you should not have to override this parameter. Only change this ' +
                            'parameter if you understand precisely what you are doing.')
                        }
                    }]
                },{
                    xtype: 'combobox',
                    store: ['ALL', 'SEVERE', 'WARNING', 'INFO', 'CONFIG', 'FINE', 'FINER', 'FINEST', 'OFF'],
                    emptyText: 'CONFIG',
                    name: 'ssh.console.log_level',
                    editable: false,
                    fieldLabel: 'Log level'
                }]
            },{
                xtype: 'component',
                flex: 1/3
            }]
		}],

		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons-mini',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					if (form.getForm().isValid()) {
                        var currentFavorites = Scalr.utils.getFavorites(Scalr.scope),
                            newFavorites = [], uniqIds = {};
                        form.down('#bookmarks').store.getUnfiltered().each(function(rec){
                            var stateId = rec.get('stateId');
                            Ext.each(currentFavorites, function(bookmark){
                                if (bookmark.stateId == stateId && uniqIds[stateId] === undefined) {
                                    uniqIds[stateId] = true;
                                    newFavorites.push(bookmark);
                                }
                            });
                        });
                        Ext.state.Manager.set('system-favorites-suppress-add-message', form.getForm().findField('donNotShowFavoritesAddMessage').getValue());
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							url: '/core/xSaveSettings/',
							form: this.up('form').getForm(),
							scope: this,
							success: function (data, response, options) {
								if (this.up('form').down('[name="dashboard_columns"]') != moduleParams['dashboard_columns']) {
									Scalr.event.fireEvent('update', '/dashboard', data.panel);
								}
                                Scalr.storage.set('system-favorites-' + Scalr.scope, newFavorites);
                                Scalr.application.updateContext(function(){
                                    Scalr.event.fireEvent('update', '/account/user/gravatar', data['gravatarHash'] ||'');
                                    Scalr.event.fireEvent('close');
                                }, true);
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

    moduleParams['ssh.console.launcher'] = moduleParams['ssh.console.launcher'] || 'applet';
    moduleParams['ssh.console.disable_key_auth'] = moduleParams['ssh.console.disable_key_auth'] || '0';
    moduleParams['ssh.console.enable_agent_forwarding'] = moduleParams['ssh.console.enable_agent_forwarding'] || '0';
	form.getForm().setValues(moduleParams);
	return form;
});

Ext.define('Scalr.ui.ScalrDataViewDragZone', {
    extend: 'Ext.view.DragZone',
    onInitDrag: function(x, y) {
        var me = this,
            data = me.dragData,
            view = data.view,
            selectionModel = view.getSelectionModel(),
            record = view.getRecord(data.item);
        // Update the selection to match what would have been selected if the user had
        // done a full click on the target node rather than starting a drag from it
        /* Changed */
        /*if (!selectionModel.isSelected(record)) {
            selectionModel.selectWithEvent(record, me.DDMInstance.mousedownEvent);
        }*/
        data.records = [record];//selectionModel.getSelection();
        /* End */
        Ext.fly(me.ddel).setHtml('Move <b>'+record.get('text')+'</b> to the new position');
        me.proxy.update(me.ddel);
        me.onStartDrag(x, y);
        return true;
    },
    afterRepair: function() {
        this.dragging = false;
    }
});

Ext.define('Scalr.ui.ScalrDataViewDropZone', {
    extend: 'Ext.view.DropZone',

	handleNodeDrop : function() {
        this.getIndicator().hide();
    },

    getIndicator: function() {
        var me = this;

        if (me.indicator && me.indicator.el && me.indicator.el.isDestroyed) {
            Ext.destroy(me.indicator);
            delete me.indicator;
        }
        if (!me.indicator) {
            me.indicator = new Ext.Component({
                ariaRole: 'presentation',
                html: me.indicatorHtml,
                cls: me.indicatorCls,
                renderTo: me.view.el,
                autoRender: true,
                //floating: true,
                shadow: false
            });
        }
        return me.indicator;
    },

    positionIndicator: function(node, data, e) {
        var me = this,
            view = me.view,
            pos = me.getPosition(e, node),
            overRecord = view.getRecord(node),
            draggingRecords = data.records,
            indicatorY;

        if (node && !Ext.Array.contains(draggingRecords, overRecord) && (
            pos === 'before' ||// && !me.containsRecordAtOffset(draggingRecords, overRecord, -1)
            pos === 'after'// && !me.containsRecordAtOffset(draggingRecords, overRecord, 1)
        )) {
            me.valid = true;
            me.getIndicator().el['insert' + Ext.String.capitalize(pos)](node);
            if (me.overRecord !== overRecord || me.currentPosition !== pos) {
                me.overRecord = overRecord;
                me.currentPosition = pos;
            }
            me.getIndicator().show();
        } else {
            //me.invalidateDrop();
        }
    },

    getPosition: function(e, node) {
        var x      = e.getXY()[0],
            y      = e.getXY()[1],
            region = Ext.fly(node).getRegion(),
            pos;

        if ((region.right - x) >= (region.right - region.left) / 2) {
            pos = "before";
        } else {
            pos = "after";
        }
        return pos;
    },


    getTargetFromEvent : function(e) {
        var node = e.getTarget(this.view.getItemSelector()),
            mouseY, nodeList, testNode, i, len, box;

        if (!node) {
            if (this.overRecord) {
                return this.view.getNode(this.overRecord)
            }
        }
        return node;
    },

    invalidateDrop: Ext.emptyFn

});

Ext.define('Scalr.ui.ScalrDataViewDragDrop', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.scalrviewdragdrop',

    uses: [
        'Ext.view.ScalrDataViewDragZone',
        'Ext.view.ScalrDataViewDropZone'
    ],

    dragText : 'move item to the new position',
    ddGroup : "ViewDD",
    enableDrop: true,

    enableDrag: true,
	offsetY: 0,
	handleNodeDrop: Ext.emptyFn,

    init : function(view) {
        view.on('render', this.onViewRender, this, {single: true});
    },

    destroy: function() {
        Ext.destroy(this.dragZone, this.dropZone);
    },

    enable: function() {
        var me = this;
        if (me.dragZone) {
            me.dragZone.unlock();
        }
        if (me.dropZone) {
            me.dropZone.unlock();
        }
        me.callParent();
    },

    disable: function() {
        var me = this;
        if (me.dragZone) {
            me.dragZone.lock();
        }
        if (me.dropZone) {
            me.dropZone.lock();
        }
        me.callParent();
    },

    onViewRender : function(view) {
        var me = this;

        if (me.enableDrag) {
            me.dragZone = new Scalr.ui.ScalrDataViewDragZone({
                view: view,
                ddGroup: me.dragGroup || me.ddGroup,
                dragText: me.dragText
            });
        }

        if (me.enableDrop) {
            me.dropZone = new Scalr.ui.ScalrDataViewDropZone({
                view: view,
                ddGroup: me.dropGroup || me.ddGroup,
				offsetY: me.offsetY
            });
        }
    }
});

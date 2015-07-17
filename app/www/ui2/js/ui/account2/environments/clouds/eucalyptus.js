Scalr.regPage('Scalr.ui.account2.environments.clouds.eucalyptus', function (loadParams, moduleParams) {
	var params = moduleParams['params'],
        locationIndex = 1,
        newLocationName = 'New location';

	var form = Ext.create('Ext.form.Panel', {
        bodyCls: 'x-container-fieldset',
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 140
		},
        onSavePlatform: function() {
            var tabs = this.down('#tabs');
            tabs.items.each(function(){
                if (!this.location) {
                    tabs.setActiveTab(this);
                }
            });
        },
        onSaveFailure: function(data, response, options) {
            var me = this;
            if (Ext.isObject(data.errors)) {
                Ext.Object.each(data.errors, function(key, value){
                    var field = me.getForm().findField(key);
                    if (field) {
                        field.up('#tabs').setActiveTab(field.up('container[tab]'));
                        return false;
                    }
                });
            }
        },
        getExtraParams: function(disablePlatform) {
            var locations = [];
            if (!disablePlatform) {
                form.down('#tabs').items.each(function(item){
                    if (item.location) {
                        locations.push(item.location);
                    }
                });
            }
            return {locations: Ext.encode(locations)};
        },
        getLocationFields: function(locationName, values) {
            values = values || {};
            return [{
                xtype: 'textfield',
                fieldLabel: 'Account ID',
                name: 'eucalyptus.account_id.' + locationName,
                value: values['eucalyptus.account_id'] || ''
            }, {
                xtype: 'textfield',
                fieldLabel: 'Access key',
                name: 'eucalyptus.access_key.' + locationName,
                value: values['eucalyptus.access_key'] || ''
            }, {
                xtype: 'textfield',
                fieldLabel: 'Secret key',
                name: 'eucalyptus.secret_key.' + locationName,
                value: values['eucalyptus.secret_key'] || ''
            }, {
                xtype: 'textfield',
                fieldLabel: 'EC2 URL',
                name: 'eucalyptus.ec2_url.' + locationName,
                value: values['eucalyptus.ec2_url'] || '',
                flex: 1,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: 'ex: http://192.168.1.1:8773/services/Eucalyptus'
                    }
                }]
            }, {
                xtype: 'textfield',
                fieldLabel: 'S3 URL',
                name: 'eucalyptus.s3_url.' + locationName,
                value: values['eucalyptus.s3_url'] || '',
                flex: 1,
                plugins: [{
                    ptype: 'fieldicons',
                    align: 'right',
                    position: 'outer',
                    icons: {
                        id: 'info',
                        tooltip: 'ex: http://192.168.1.1:8773/services/Walrus'
                    }
                }]
            }, {
                xtype: 'filefield',
                fieldLabel: 'X.509 certificate file',
                name: 'eucalyptus.certificate.' + locationName,
                value: values['eucalyptus.certificate'] || ''
            }, {
                xtype: 'filefield',
                fieldLabel: 'X.509 private key file',
                name: 'eucalyptus.private_key.' + locationName,
                value: values['eucalyptus.private_key'] || ''
            }, {
                xtype: 'filefield',
                fieldLabel: 'X.509 cloud certificate file',
                name: 'eucalyptus.cloud_certificate.' + locationName,
                value: values['eucalyptus.cloud_certificate'] || ''
            }];
        },
        addLocation: function(location, values) {
            var me = this,
                tabs = me.down('#tabs'),
                tab,
                items;

            items = [{
                xtype: 'textfield',
                fieldLabel: 'Cloud location name',
                itemId: 'location',
                readOnly: location !== newLocationName,
                allowBlank: false,
                value: location !== newLocationName ? location : ''
            }];

            if (location === newLocationName) {
                items.push({
                    xtype: 'container',
                    autoScroll: true,
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: {
                        xtype: 'button',
                        text: 'Continue',
                        margin: '12 0 0',
                        width: 100,
                        handler: function() {
                            var newLocationCt = this.up('container[tab]'),
                                locationField = newLocationCt.down('#location'),
                                location = locationField.getValue();
                            if (locationField.isValid()) {
                                newLocationCt.location = location;
                                newLocationCt.tab.setText(location);
                                newLocationCt.add(newLocationCt.up('form').getLocationFields(location));
                                this.up('container').hide();
                                locationField.setReadOnly(true);
                            } else {
                                newLocationCt.down('#location').focus(true, 100);
                            }
                        }
                    }
                });
            } else {
                items.push.apply(items, me.getLocationFields(location, values));
            }
        
            tab = tabs.insert(tabs.items.length, {
                xtype: 'container',
                tab: true,
                cls: 'x-container-fieldset',
                closable: true,
                autoScroll: true,
                location: location !== newLocationName ? location : '',
                tabConfig: {
                    title: location,
                    maxWidth: 180,
                    closeText: 'Remove location'
                },
                layout: 'anchor',
				defaults: {
					anchor: '100%',
					labelWidth: 190
				},
                items: items
            });
            locationIndex++;
            return tab;
        },
		items: {
            xtype: 'tabpanel',
            itemId: 'tabs',
            cls: 'x-tabs-dark',
            flex: 1,
            //margin: '0 0 18 0',
            listeners: {
                beforeremove: function() {
                    if (this.items.length === 1) {
                        this.setActiveTab(form.addLocation(newLocationName));
                    }
                },
                tabchange: function(panel, tab) {
                    tab.down('#location').focus(true, 100);
                }
            },
            tabBar: {
                items: [{
                    xtype: 'tbfill'
                },{
                    xtype: 'button',
                    text: 'Add location',
                    cls: 'x-btn-green',
                    margin: '2 0 6 12',
                    handler: function() {
                        var tabs = this.up('#tabs'),
                            tab;
                        tabs.items.each(function(){
                            if (!this.location) {
                                tab = this;
                                return false;
                            }
                        });
                        tabs.setActiveTab(tab || form.addLocation(newLocationName));
                    }
                }]
            }
        }
    });

    var defaultTab;
    if (Ext.Object.getSize(params.locations) > 0) {
        Ext.Object.each(params.locations, function(key, value){
            var tab = form.addLocation(key, value);
            defaultTab = defaultTab || tab;
        });
    } else {
        defaultTab = form.addLocation(newLocationName);
    }
    form.down('#tabs').setActiveTab(defaultTab);
    return form;
});

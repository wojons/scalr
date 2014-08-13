Ext.define('Scalr.ui.RoleDesignerTabOverview', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditoverview',
    cls: 'x-panel-column-left',
    layout: {
        type: 'vbox',
        align: 'stretch'
    },
    autoScroll: true,
    items: [{
        xtype: 'form',
        itemId: 'roleSettings',
        cls: 'x-panel-column-left',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        defaults: {
            flex: 1,
            maxWidth: 450
        },
        items: [{
            xtype: 'container',
            cls: 'x-container-fieldset x-fieldset-separator-right',
            layout: 'anchor',
            maxWidth: 540,
            defaults: {
                anchor: '100%',
                labelWidth: 80
            },
            items: [{
                xtype: 'component',
                itemId: 'nameReadonly',
                cls: 'x-fieldset-subheader',
                hidden: true
            },{
                xtype: 'textfield',
                name: 'name',
                itemId: 'name',
                fieldLabel: 'Name',
                hideInputOnReadOnly: true,
                allowBlank: false
            },{
                xtype: 'fieldcontainer',
                itemId: 'os',
                layout: 'hbox',
                fieldLabel: 'OS',
                items: [{
                    xtype: 'combo',
                    name: 'osFamily',
                    width: 130,
                    displayField: 'title',
                    valueField: 'osFamily',
                    editable: false,
                    store: Ext.create('Ext.data.ArrayStore', {
                        fields: ['osFamily', 'versions', {name: 'title', convert: function(v, record){return Scalr.utils.beautifyOsFamily(record.data.osFamily);}}],
                        data: [
                            ['amazon', [['2012.09', '2012.09'],['2013.03', '2013.03'],['2014.03', '2014.03']]],
                            ['centos', [
                                ['5.3', '5', 'Final'],['5.4', '5', 'Final'],['5.5', '5', 'Final'],['5.6', '5', 'Final'],['5.7', '5', 'Final'],['5.8', '5', 'Final'],['5.9', '5', 'Final'],['5.10', '5', 'Final'],
                                ['6.1', '6', 'Final'],['6.2', '6', 'Final'],['6.3', '6', 'Final'],['6.4', '6', 'Final'],['6.5', '6', 'Final']
                            ]],
                            ['debian', [
                                ['6.0.4', '6', 'Squeeze'],['6.0.5', '6', 'Squeeze'],['6.0.7', '6', 'Squeeze'],
                                ['7.0', '7', 'Wheezy'],['7.1', '7', 'Wheezy'],['7.5', '7', 'Wheezy']
                            ]],
                            ['gcel', [['12.04', '12.04']]],
                            ['oel', [
                                ['5.5', '5', 'Tikanga'],['5.7', '5', 'Tikanga'],['5.9', '5', 'Tikanga'],
                                ['6.1', '6', 'Santiago']
                            ]],
                            ['redhat', [
                                ['5.4', '5', 'Tikanga'],['5.5', '5', 'Tikanga'],['5.6', '5', 'Tikanga'],['5.7', '5', 'Tikanga'],['5.8', '5', 'Tikanga'],['5.9', '5', 'Tikanga'],['5.10', '5', 'Tikanga'],
                                ['6.1', '6', 'Santiago'],['6.3', '6', 'Santiago'],['6.4', '6', 'Santiago'],['6.5', '6', 'Santiago']
                            ]],
                            ['ubuntu', [
                                ['10.04', '10.04', 'Lucid'],['10.10', '10.10', 'Maverick'],['11.04', '11.04', 'Natty'],['11.10', '11.10', 'Oneiric'],
                                ['12.04', '12.04', 'Precise'],['12.10', '12.10', 'Quantal'],['13.04', '13.04', 'Raring'],['13.10', '13.10', 'Saucy'],
                                ['14.04', '14.04',  'Trusty Tahr']
                            ]],
                            ['windows', [['2003', '2003', 'Server'],['2008', '2008', 'Server'],['2012', '2012', 'Server']]]
                        ]
                    }),
                    listeners: {
                        change: function(comp, value) {
                            var record = comp.findRecordByValue(value);
                            if (record) {
                                var version = comp.next(),
                                    store = version.store;
                                store.getProxy().data = record.get('versions');
                                store.load();
                                version.setValue(store.last());
                            }
                        }
                    }
                },{
                    xtype: 'combo',
                    name: 'osVersion',
                    displayField: 'title',
                    valueField: 'osVersion',
                    editable: false,
                    flex: 1,
                    store: Ext.create('Ext.data.ArrayStore', {
                        fields: ['osVersion', 'osGeneration', 'suffix', {name: 'title', convert: function(v, record){return record.data.osVersion + ' ' + record.data.suffix;}}]
                    }),
                    margin: '0 0 0 12'
                }]
            },{
                xtype: 'combo',
                name: 'catId',
                valueField: 'id',
                displayField: 'name',
                editable: false,
                hideInputOnReadOnly: true,
                store: {
                    fields: ['id', 'name'],
                    proxy: 'object'
                },
                fieldLabel: 'Category',
                allowBlank: false
            },{
                xtype: 'displayfield',
                name: 'created',
                fieldLabel: 'Created by'
            },{
                xtype: 'textarea',
                name: 'description',
                fieldLabel: 'Description',
                height: 70
            },{
				xtype: 'checkboxgroup',
				maxWidth: 320,
				fieldLabel: 'Flags',
				itemId: 'tags',
                submitValue: false,
                margin: 0,
				items: [{
					boxLabel: 'ec2.ebs',
					inputValue: 'ec2.ebs',
					name: 'tags'
				}, {
					boxLabel: 'ec2.hvm',
					inputValue: 'ec2.hvm',
					name: 'tags'
				}]

            },{
                xtype: 'displayfield',
                fieldLabel: 'Flags',
                itemId: 'tagsReadonly',
                hidden: true
            }]
        },{
            xtype: 'container',
            cls: 'x-container-fieldset x-fieldset-separator-right',
            items: [{
                xtype: 'container',
                layout: 'anchor',
                defaults: {
                    anchor: '100%',
                    labelWidth: 120
                },
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'component',
                        itemId: 'topRightcol',
                        //hidden: true,
                        cls: 'x-fieldset-subheader',
                        html: 'Software and automation'
                    },{
                        xtype: 'button',
                        itemId: 'configureAutomation',
                        text: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-configure" />&nbsp;Configure',
                        maxWidth: 120,
                        flex: 1,
                        margin: '0 0 0 10',
                        handler: function() {
                            var behaviors = [
                                    'chef', 'mysql','mariadb','mysql2','percona','postgresql'/*,'cassandra'*/,'redis','mongodb',
                                    'app','www','haproxy','memcached','rabbitmq',
                                    'cf_router','cf_cloud_controller','cf_health_manager','cf_dea','cf_service'
                                ],
                                store = this.up('roleeditoverview').down('#automation').store;
                            Scalr.Confirm({
                                formWidth: 850,
                                form: [{
                                    xtype: 'fieldset',
                                    title: 'Configure automation',
                                    cls: 'x-fieldset-separator-none',
                                    height: 420,
                                    defaults: {
                                        xtype: 'button',
                                        ui: 'simple',
                                        enableToggle: true,
                                        cls: 'x-btn-simple-large',
                                        iconAlign: 'above',
                                        margin: '10 10 0 0'
                                    },
                                    items: Ext.Array.map(behaviors, function(behavior){
                                        return {
                                            iconCls: 'x-icon-behavior-large x-icon-behavior-large-' + behavior,
                                            text: Scalr.utils.beautifyBehavior(behavior),
                                            behavior: behavior,
                                            pressed: store.query('name', behavior).length > 0
                                        };
                                    })
                                }],
                                closeOnSuccess: true,
                                scope: this,
                                success: function (formValues, form) {
                                    var behaviors = [];
                                    Ext.Array.each(form.query('[xtype="button"]'), function(btn){
                                        if (btn.pressed) {
                                            behaviors.push([btn.behavior]);
                                        }
                                    });
                                    if (behaviors.length === 1 && behaviors[0] === 'chef') {
                                        behaviors.push('base');
                                    }
                                    store.loadData(behaviors);
                                    return true;
                                }
                            });
                        }
                    }]
                },{
                    xtype: 'displayfield',
                    itemId: 'osReadonly',
                    fieldLabel: 'Operating system',
                    hidden: true
                },{
                    xtype: 'displayfield',
                    name: 'software',
                    fieldLabel: 'Software version'
                },{
                    xtype: 'fieldcontainer',
                    fieldLabel: 'Scalr automation',
                    items: {
                        xtype: 'dataview',
                        itemId: 'automation',
                        itemSelector: '.x-item',
                        store: Ext.create('Ext.data.ArrayStore', {
                            fields: ['name']
                        }),
                        tpl  : new Ext.XTemplate(
                            '<tpl for=".">',
                                '<tpl if="' + (Scalr.user.type === 'ScalrAdmin' ? '' : 'values.name!=\'chef\' && ') + 'values.name!=\'base\'">',
                                    '<span style="white-space:nowrap;color:#666;line-height:26px;" data-qtip="{[Scalr.utils.beautifyBehavior(values.name, true)]}">',
                                        '<img style="margin:4px 0 0 0;" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-role-small x-icon-role-small-{name}"/>&nbsp;{[Scalr.utils.beautifyBehavior(values.name)]}',
                                    '</span>&nbsp;&nbsp; ',
                                '</tpl>',
                            '</tpl>'
                        )
                    }
                }]
            }]
        },{
            xtype: 'container',
            cls: 'x-container-fieldset',
            layout: 'anchor',
            defaults: {
                anchor: '100%'
            },
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                html: 'Role usage'
            },{
                xtype: 'cloudlocationmap',
                itemId: 'locationmap',
                size: 'large',
                autoSelect: true,
                //hidden: true,
                listeners: {
                    selectlocation: function(location){
                        var grid = this.next(),
                            res = grid.store.query('location', location, false, false, true),
                            selModel;
                        if (res.length) {
                            selModel = grid.view.getSelectionModel();
                            selModel.deselectAll();
                            selModel.setLastFocused(res.first());
                        }
                    }
                }
            },{
                xtype: 'displayfield',
                name: 'usage',
                fieldStyle: 'text-align: center',
                margin: '10 0 0 0'
                //fieldLabel: 'Role usage'

            }]
        }]
    },{
        xtype: 'container',
        flex: 1,
        cls: 'x-fieldset-separator-top',
        minHeight: 240,
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        defaults: {
            flex: 1
        },
        items: [{
            xtype: 'fieldset',
            title: 'Images',
            cls: 'x-fieldset-separator-right',
            maxWidth: 760,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'grid',
                itemId: 'images',
                cls: 'x-grid-shadow x-grid-no-highlighting',
                flex: 1,
                store: {
                    fields: [ 'platform', 'location', 'image_id', 'architecture', 'errors', 'isEmpty'],
                    proxy: 'object'
                },
                features: {
                    ftype: 'addbutton',
                    text: 'Add image',
                    handler: function(view) {
                        view.up('roleeditoverview').fireEvent('addimage');
                    }
                },
                plugins: [{
                    ptype: 'focusedrowpointer',
                    thresholdOffset: 26
                }],

                viewConfig: {
                    getRowClass: function(record) {
                        var cls = '';
                        if (record.get('errors')) {
                            cls += ' x-grid-row-red';
                        } else if (!record.get('image_id')) {
                            cls += ' x-grid-row-disabled';
                        }
                        return cls;
                    }
                },
                columns: [
                    { header: "Cloud", flex: .6, dataIndex: 'platform', sortable: true, xtype: 'templatecolumn', tpl:
                        '<img class="x-icon-platform-small x-icon-platform-small-{platform}" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;{[Scalr.utils.getPlatformName(values.platform)]}'
                    },
                    { header: "Location", flex: 1, dataIndex: 'location', sortable: true, renderer:
                        function(value, meta, record) {
                            var platform = record.get('platform'),
                                location = record.get('location'),
                                res = '';
                            if (record.get('image_id')) {
                                if (platform === 'gce' || platform === 'ecs') {
                                    res += 'All regions';
                                } else if (location) {
                                    if (Scalr.platforms[platform] && Scalr.platforms[platform]['locations'] && Scalr.platforms[platform]['locations'][location]) {
                                        res += Scalr.platforms[platform]['locations'][location];
                                    } else {
                                        res += location;
                                    }
                                }
                            } else if (!record.get('errors')) {
                                res = '<i>No image has been added for this cloud</i>';
                            }
                            return res;
                        }
                    }
                ],
                listeners: {
                    viewready: {
                        fn: function() {
                            var me = this;
                            this.store.on('load', function(){
                                var store = this,
                                    map = me.prev(),
                                    locations = [];
                                //map.reset();

                                (store.snapshot || store.data).each(function(record){
                                    locations.push({
                                        platform: record.get('platform'),
                                        location: record.get('location')
                                    });
                                });
                                //map.addLocations(locations);
                            });

                            me.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                                if (newFocused) {
                                    //me.prev().setLocation(newFocused.get('location'));
                                }
                            });

                        },
                        single: true
                    }
                }
            }]
        },{
            xtype: 'container',
            autoScroll: true,
            items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                flex: 1,
                title: 'Orchestration',
                //cls: 'x-fieldset-separator-none',
                minHeight: 130,
                items: [{
                    xtype: 'scriptinggrid',
                    itemId: 'scripts',
                    maxWidth: 600,
                    groupingStartCollapsed: true,
                    groupingShowTotal: true,
                    cls: 'x-grid-shadow x-grid-role-scripting x-grid-no-highlighting',
                    hideDeleteButton: true,
                    addButtonHandler: function(view) {
                        view.up('roleeditoverview').fireEvent('addscript');
                    }
                }]
            },{
                xtype: 'container',
                cls: 'x-container-fieldset x-fieldset-separator-top',
                itemId: 'chefPanel',
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'component',
                        cls: 'x-fieldset-subheader',
                        html: 'Bootstrap role with Chef'
                    },{
                        xtype: 'button',
                        maxWidth: 120,
                        flex: 1,
                        margin: '0 0 0 10',
                        text: '<img style="vertical-align:top;" width="16" height="16" src="/ui2/images/ui/roles/edit/chef_config.png" />&nbsp;Configure',
                        handler: function() {
                            this.up('roleeditoverview').fireEvent('editchef');
                        }
                    }]
                },{
                    xtype: 'displayfield',
                    itemId: 'nochef',
                    value: '<i>Chef is currently not used by this role.</i>'
                },{
                    xtype: 'container',
                    layout: 'anchor',
                    itemId: 'chef',
                    hidden: true,
                    defaults: {
                        labelWidth: 110,
                        anchor: '100%',
                        maxWidth: 600
                    },
                    items: [{
                        xtype: 'displayfield',
                        hideOn: 'solo',
                        name: 'chef.server_id',
                        fieldLabel: 'Chef server',
                        renderer: function(value, comp) {
                            if (value) {
                                Scalr.cachedRequest.load({url: '/services/chef/servers/xListServers/'},function(data, status) {
                                    if (comp.rendered && !comp.isDestroyed && status) {
                                        Ext.Object.each(data, function(key, chefServer){
                                            if (chefServer.id == value) {
                                                value = chefServer.url;
                                                comp.inputEl.dom.innerHTML = chefServer.url;
                                                comp.updateLayout();
                                                return false;
                                            }
                                        });
                                    }
                                });
                            }
                            return value;
                        }
                    },{
                        xtype: 'displayfield',
                        name: 'chef.environment',
                        hideOn: 'solo',
                        fieldLabel: 'Chef environment'
                    },{
                        xtype: 'displayfield',
                        name: 'chef.role_name',
                        hideOn: 'solo',
                        fieldLabel: 'Chef role'
                    },{
                        xtype: 'displayfield',
                        name: 'chef.cookbook_url',
                        hideOn: 'server',
                        fieldLabel: 'Cookbook URL'
                    },{
                        xtype: 'textarea',
                        name: 'chef.runlist',
                        readOnly: true,
                        submitValue: false,
                        labelAlign: 'top',
                        fieldLabel: 'Runlist'
                    },{
                        xtype: 'textarea',
                        name: 'chef.attributes',
                        readOnly: true,
                        submitValue: false,
                        labelAlign: 'top',
                        fieldLabel: 'Attributes'
                    }]
                }]
            }]
        }]
    }],
    initComponent: function() {
        this.callParent(arguments);
        this.addListener({
            showtab: {
                fn: function(params){
                    var software = [],
                        isNewRole = !params['role']['roleId'];
                    Ext.Object.each(params['role']['software'], function(key, value){
                        if (value.name) {
                            software.push(Ext.String.capitalize(value.name) + ' ' + value.version);
                        }
                    });
                    this.down('[name="catId"]').store.load({data: params.categories});
                    if (!isNewRole) {
                        this.down('#nameReadonly').show().update(params['role']['name']);
                        //this.down('#topRightcol').show();
                        this.down('#name').hide();
                        this.down('#osReadonly').show().setValue('<img src="'+Ext.BLANK_IMAGE_URL+'" title="" class="x-icon-osfamily-small x-icon-osfamily-small-'+params['role']['osFamily']+'" />&nbsp;' + params['role']['os']);
                        this.down('#os').hide();
                        this.setFieldValues({
                            name: params['role']['name'],
                            catId: params['role']['catId'],
                            osFamily: params['role']['osFamily'],
                            osVersion: params['role']['osVersion'],
                            description: params['role']['description'],
                            created: params['role']['addedByEmail'] ? '<i>' + params['role']['addedByEmail'] + '</i>' + (params['role']['dtadded'] ? ' on <i>' + params['role']['dtadded'] + '</i>' : '') : '-',
                            usage: '<span style="color:green;font-size:140%">' + params['roleUsage']['farms'] + '</span> farm(s) with ' + '<span style="color:green;font-size:140%">' + params['roleUsage']['instances'] + '</span> running instance(s) of this role',
                            software: software.length ? software.join(', ') : '-'
                        });
                        var tags = this.down('#tagsReadonly');
                        this.down('#tags').hide();
                        tags.show();
                        tags.setValue(Ext.Object.getKeys(params['role']['tags']).join(', ') || '-');
                    } else {
                        this.down('[name="osFamily"]').setValue('ubuntu');
                        this.down('[name="created"]').hide();
                        this.down('[name="usage"]').hide();
                        this.down('[name="software"]').hide();
                        var tags = this.down('#tags');
                        tags.setValue({tags: Ext.Object.getKeys(params['role']['tags'])});
                    }

                    var fields = this.down('#roleSettings').query('[isFormField]');
                    Ext.Array.each(fields, function(item){
                        if (item.name !== 'description') {
                            item.setReadOnly(!isNewRole, false);
                        }
                        if (item.name === 'catId') {
                            item.allowBlank = !isNewRole;
                        }
                    });

                    this.down('#configureAutomation').setVisible(isNewRole);
                    this.down('#automation').store.loadData(isNewRole ? [['base']] : Ext.Array.map(params['role']['behaviors'], function(item){return [item];}));
                    this.down('#scripts').getStore().loadEvents(params['scriptData']['events']);
                },
                single: true
            },
            hidetab: function(params) {
                var automationStore = this.down('#automation').store;
                params['role']['behaviors'] = [];
                (automationStore.snapshot || automationStore.data).each(function(record){
                    params['role']['behaviors'].push(record.get('name'));
                });

            }
        });
        this.addListener({
            showtab: {
                fn: function(params){
                    var images = [],
                        platformsAdded = {},
                        chefEnabled = Ext.Array.contains(params['role']['behaviors'], 'chef') && Scalr.user.type !== 'ScalrAdmin';
                    Ext.Array.each(params['role']['images'], function(value) {
                        images.push(value);
                        platformsAdded[value['platform']] = true;
                    });
                    if (Scalr.user.type !== 'ScalrAdmin') {
                        Ext.Object.each(Scalr.platforms, function(key, value) {
                            if (platformsAdded[key] === undefined && value.enabled) {
                                images.push({platform: key});
                            }
                        });
                    }
                    this.down('#images').store.load({data: images});

                    this.down('#scripts').getStore().load({data: Ext.Array.map(params['role']['scripts'], function(item){
                        item['event'] = item['event'] || item['event_name'];
                        item['script'] = item['script'] || item['script_name'];
                        return item;
                    })});

                    ;
                    if (chefEnabled) {
                        var chef, chefCt = this.down('#chef'), noChefCt = this.down('#nochef');
                        if (Ext.isObject(params['role']['chef'])) {
                            chef = params['role']['chef'];
                            if (chef['chef.bootstrap'] == 1) {
                                chefCt.setFieldValues(chef);
                                Ext.Array.each(chefCt.query('[hideOn="solo"]'), function(item){
                                    item.setVisible(chef['chef.cookbook_url'] === undefined);
                                });
                                Ext.Array.each(chefCt.query('[hideOn="server"]'), function(item){
                                    item.setVisible(chef['chef.cookbook_url'] !== undefined);
                                });
                                chefCt.down('[name="chef.runlist"]').setVisible(chef['chef.role_name'] === undefined);
                                chefCt.down('[name="chef.role_name"]').setVisible(chef['chef.role_name'] !== undefined);
                                chefCt.show();
                                noChefCt.hide();
                            } else {
                                chefCt.hide();
                                noChefCt.show();
                            }
                        } else {
                            chefCt.hide();
                            noChefCt.show();
                        }
                    }
                    this.down('#chefPanel').setVisible(chefEnabled);
                }
            }
        });
    },
    getSubmitValues: function() {
        var values = this.getFieldValues(true),
            osField = this.down('[name="osVersion"]'),
            automationStore = this.down('#automation').store,
            os;
        values['tags'] = this.down('#tags').getValue().tags;
        if (!Ext.isArray(values['tags']) && values['tags']) {
            values['tags'] = [values['tags']];
        }
        os = osField.findRecordByValue(values['osVersion']);
        if (os) {
            os = os.getData();
            values.osGeneration = os.osGeneration;
            values.os = Scalr.utils.beautifyOsFamily(values.osFamily) + ' ' + os.title;
        }

        values['behaviors'] = [];
        (automationStore.snapshot || automationStore.data).each(function(record){
            values['behaviors'].push(record.get('name'));
        });
        return values;
    },

    isValid: function(params) {
        var valid = true;
        if (!params['id']) {
            valid = this.down('#roleSettings').isValid();
        }
        return valid;
    }

});

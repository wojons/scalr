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
        layout: 'column',
        defaults: {
            columnWidth: .5
        },
        items: [{
            xtype: 'container',
            cls: 'x-container-fieldset',
            layout: 'anchor',
            maxWidth: 760,
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
                xtype: 'displayfield',
                itemId: 'osReadonly',
                fieldLabel: 'OS',
                hidden: true
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
                            ['amazon', [['2012.09', '2012.09'],['2013.03', '2013.03']]],
                            ['centos', [
                                ['5.3', '5', 'Final'],['5.4', '5', 'Final'],['5.5', '5', 'Final'],['5.6', '5', 'Final'],['5.7', '5', 'Final'],['5.8', '5', 'Final'],['5.9', '5', 'Final'],
                                ['6.1', '6', 'Final'],['6.2', '6', 'Final'],['6.3', '6', 'Final'],['6.4', '6', 'Final']
                            ]],
                            ['debian', [
                                ['6.0.4', '6', 'Squeeze'],['6.0.5', '6', 'Squeeze'],['6.0.7', '6', 'Squeeze'],
                                ['7.0', '7', 'Wheezy'],['7.1', '7', 'Wheezy']
                            ]],
                            ['gcel', [['12.04', '12.04']]],
                            ['oel', [
                                ['5.5', '5', 'Tikanga'],['5.7', '5', 'Tikanga'],['5.9', '5', 'Tikanga'],
                                ['6.1', '6', 'Santiago']
                            ]],
                            ['redhat', [
                                ['5.4', '5', 'Tikanga'],['5.5', '5', 'Tikanga'],['5.6', '5', 'Tikanga'],['5.7', '5', 'Tikanga'],['5.8', '5', 'Tikanga'],['5.9', '5', 'Tikanga'],
                                ['6.1', '6', 'Santiago'],['6.3', '6', 'Santiago'],['6.4', '6', 'Santiago']
                            ]],
                            ['ubuntu', [
                                ['10.04', '10.04', 'Lucid'],['10.10', '10.10', 'Maverick'],['11.04', '11.04', 'Natty'],['11.10', '11.10', 'Oneiric'],
                                ['12.04', '12.04', 'Precise'],['12.10', '12.10', 'Quantal'],['13.04', '13.04', 'Raring'],['13.10', '13.10', 'Saucy']
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
				xtype: 'checkboxgroup',
				maxWidth: 320,
				fieldLabel: 'Properties',
				itemId: 'tags',
                submitValue: false,
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
                xtype: 'textarea',
                name: 'description',
                fieldLabel: 'Description',
                height: 50

            }]
        },{
            xtype: 'container',
            itemId: 'topRightcol',
            hidden: true,
            cls: 'x-container-fieldset',
            layout: 'anchor',
            defaults: {
                anchor: '100%',
                labelWidth: 120
            },
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                html: '&nbsp;'
            },{
                xtype: 'displayfield',
                name: 'usage',
                fieldLabel: 'Role usage'
            },{
                xtype: 'displayfield',
                name: 'software',
                fieldLabel: 'Software version'
            },{
                xtype: 'displayfield',
                name: 'created',
                fieldLabel: 'Created by'
            }]
        }]
    },{
        xtype: 'container',
        flex: 1,
        cls: 'x-fieldset-separator-top',
        minHeight: 400,
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
                xtype: 'cloudlocationmap',
                itemId: 'locationmap',
                size: 'large',
                autoSelect: true,
                margin: '0 0 20 0',
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
                xtype: 'grid',
                itemId: 'images',
                cls: 'x-grid-shadow',
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
                        return record.get('errors') ? 'x-grid-row-red' : '';
                    }
                },
                columns: [
                    { header: "Cloud location", flex: 1, dataIndex: 'platform', sortable: true, renderer:
                        function(value, meta, record) {
                            var platform = record.get('platform'),
                                location = record.get('location'),
                                res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" title="' + Scalr.utils.getPlatformName(platform) + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                            if (platform === 'gce') {
                                res += 'All regions';
                            }
                            if (location) {
                                if (this.platforms[platform] && this.platforms[platform]['locations'][location]) {
                                    res += this.platforms[platform]['locations'][location];
                                } else {
                                    res += location;
                                }
                            }
                            return res;
                        }
                    },
                    { header: "Image ID", flex: 1.1, dataIndex: 'image_id', sortable: true }
                ],
                listeners: {
                    viewready: {
                        fn: function() {
                            var me = this;
                            this.store.on('load', function(){
                                var store = this,
                                    map = me.prev(),
                                    locations = [];
                                map.reset();

                                (store.snapshot || store.data).each(function(record){
                                    locations.push({
                                        platform: record.get('platform'),
                                        location: record.get('location')
                                    });
                                });
                                map.addLocations(locations);
                            });

                            me.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                                if (newFocused) {
                                    me.prev().setLocation(newFocused.get('location'));
                                }
                            });

                        },
                        single: true
                    }
                }
            }]
        },{
            xtype: 'container',
            overflowY: 'auto',
            overflowX: 'hidden',
            items: [{
                xtype: 'container',
                cls: 'x-container-fieldset x-fieldset-separator-bottom',
                autoScroll: true,
                items: [{
                    xtype: 'container',
                    layout: 'column',
                    items: [{
                        xtype: 'component',
                        cls: 'x-fieldset-subheader',
                        html: 'Scalr automation'
                    },{
                        xtype: 'button',
                        itemId: 'configureAutomation',
                        text: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-configure" />&nbsp;Configure',
                        width: 120,
                        margin: '-2 0 0 20',
                        handler: function() {
                            var behaviors = ['base','chef', 'mysql','mariadb','mysql2','percona','postgresql'/*,'cassandra'*/,'redis','mongodb','app','www','haproxy','memcached','rabbitmq','cf_router','cf_cloud_controller','cf_health_manager','cf_dea','cf_service'],
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
                                    store.loadData(behaviors);
                                    return true;
                                }
                            });
                        }
                    }]
                },{
                    xtype: 'dataview',
                    itemId: 'automation',
                    itemSelector: '.x-item',
                    store: Ext.create('Ext.data.ArrayStore', {
                        fields: ['name']
                    }),
                    tpl  : new Ext.XTemplate(
                        '<tpl for=".">',
                            '<div class="x-item" style="line-height:20px;margin:0 16px 8px 0;color:#666;float:left">',
                                '<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-role-small x-icon-role-small-{name}"/>&nbsp;{[Scalr.utils.beautifyBehavior(values.name)]}',
                            '</div>',
                        '</tpl>'
                    )
                }]
            },{
                xtype: 'container',
                cls: 'x-container-fieldset x-fieldset-separator-bottom',
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'component',
                        cls: 'x-fieldset-subheader',
                        html: 'Bootstrap role with Chef'
                    },{
                        xtype: 'button',
                        width: 120,
                        margin: '0 0 0 20',
                        text: '<img style="vertical-align:top;" width="16" height="16" src="/ui2/images/ui/roles/edit/chef_config.png" />&nbsp;Configure',
                        handler: function() {
                            this.up('roleeditoverview').fireEvent('editchef');
                        }
                    }]
                },{
                    xtype: 'component',
                    itemId: 'chefStatus',
                    style: 'color:#666'
                }]
            },{
                xtype: 'fieldset',
                flex: 1,
                title: 'Orchestration',
                cls: 'x-fieldset-separator-none',
                //layout: 'hbox',
                items: [{
                    xtype: 'scriptinggrid',
                    itemId: 'scripts',
                    groupingStartCollapsed: true,
                    groupingShowTotal: true,
                    cls: 'x-grid-shadow x-grid-role-scripting x-grid-no-highlighting',
                    maxWidth: 600,
                    flex: 1,
                    hideDeleteButton: true,
                    addButtonHandler: function(view) {
                        view.up('roleeditoverview').fireEvent('addscript');
                    }
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
                        software.push(Ext.String.capitalize(value.name) + ' ' + value.version);
                    });
                    this.down('[name="catId"]').store.load({data: params.categories});
                    if (!isNewRole) {
                        this.down('#nameReadonly').show().update(params['role']['name']);
                        this.down('#topRightcol').show();
                        this.down('#name').hide();
                        this.down('#osReadonly').show().setValue('<img src="'+Ext.BLANK_IMAGE_URL+'" title="" class="x-icon-osfamily-small x-icon-osfamily-small-'+params['role']['osFamily']+'" />&nbsp;' + params['role']['os']);
                        this.down('#os').hide();
                        this.setFieldValues({
                            name: params['role']['name'],
                            catId: params['role']['catId'],
                            osFamily: params['role']['osFamily'],
                            osVersion: params['role']['osVersion'],
                            description: params['role']['description'],
                            created: params['role']['addedByEmail'] ? '<span style="color:#0054cc">' + params['role']['addedByEmail'] + '</span>' + (params['role']['dtadded'] ? ' on <span style="color:#0054cc">' + params['role']['dtadded'] + '</span>' : '') : '-',
                            usage: isNewRole ? '-' : ('<span style="color:#0054cc">' + (params['role']['farmsCount'] || 0) + '</span> farms with ' + '<span style="color:#0054cc">' + (params['role']['rolesCount'] || 0) + '</span> running instances of this role'),
                            software: software.length ? software.join(', ') : '-'
                        });
                    } else {
                        this.down('[name="osFamily"]').setValue('ubuntu');
                        this.setFieldValues({
                            created: '-',
                            usage: '-',
                            software: '-'
                        });
                    }

                    this.down('#images').platforms = params.platforms;
                    this.down('#locationmap').platforms = params['platforms'];

                    var tags = this.down('#tags');
                    tags.setValue({tags: Ext.Object.getKeys(params['role']['tags'])});

                    var fields = this.down('#roleSettings').query('[isFormField]');
                    Ext.Array.each(fields, function(item){
                        if (item.name !== 'description') {
                            item.setReadOnly(!isNewRole, false);
                        }
                    });

                    this.down('#configureAutomation').setVisible(isNewRole);
                    this.down('#automation').store.loadData(isNewRole ? [['base']] : Ext.Array.map(params['role']['behaviors'], function(item){return [item];}));
                },
                single: true
            }
        });
        this.addListener({
            showtab: {
                fn: function(params){
                    this.down('#images').store.load({data: params['role']['images']});
                    this.down('#scripts').getStore().load({data: Ext.Array.map(params['role']['scripts'], function(item){
                        item['event'] = item['event'] || item['event_name'];
                        item['script'] = item['script'] || item['script_name'];
                        return item;
                    })});
                    var chef, chefStatus;
                    if (Ext.isObject(params['role']['chef'])) {
                        chef = params['role']['chef'];
                        if (chef['chef.bootstrap'] == 1) {
                            if (chef['chef.cookbook_url']) {
                                chefStatus = 'Cookbook URL: ' + chef['chef.cookbook_url'];
                            } else if (chef['chef.cookbook_url']) {
                                chefStatus = 'Server: ' + chef['chef.server_id'];
                            }
                        }
                    }
                    this.down('#chefStatus').update(chefStatus || 'not configured');
                }
            }
        });
    },
    getSubmitValues: function() {
        var values = this.getFieldValues(true),
            osField = this.down('[name="osVersion"]'),
            automationStore = this.down('#automation').store,
            os;
        values['tags[]'] = this.down('#tags').getValue().tags;
        os = osField.findRecordByValue(values['osVersion']).getData();
        if (os) {
            values.osGeneration = os.osGeneration;
            values.os = Scalr.utils.beautifyOsFamily(values.osFamily) + ' ' + os.title;
        }

        values['behaviors[]'] = [];
        (automationStore.snapshot || automationStore.data).each(function(record){
            values['behaviors[]'].push(record.get('name'));
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

Scalr.regPage('Scalr.ui.roles.create', function (loadParams, moduleParams) {

    var isImportAllowed = Scalr.isAllowed('IMAGES_ENVIRONMENT', 'import');
    var isBuildAllowed = Scalr.isAllowed('IMAGES_ENVIRONMENT', 'build');

    return Ext.create('Ext.panel.Panel', {
        title: 'Choose a role creation method',
        width: isImportAllowed && isBuildAllowed ? 410 : 280,
        layout: 'hbox',

        scalrOptions: {
            modal: true
        },

        tools: [{
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent('close');
            }
        }],

        defaults: {
            xtype: 'button',
            ui: 'simple',
            cls: 'x-btn-simple-large',
            margin: isImportAllowed || isBuildAllowed ? '20 0 20 20' : '20 0 20 85',
            iconAlign: 'top'
        },

        items: [{
            xtype: 'button',
            text: 'New role',
            href: '#/roles/edit',
            hrefTarget: '_self',
            iconCls: 'x-icon-behavior-large x-icon-behavior-large-mixed',
            tooltip: 'Create a new empty Role, and manually add Images, Orchestration, and Global Variables.'
        }, {
            xtype: 'button',
            text: '<span class="small">Role from <br/>non-Scalr server</span>',
            href: '#/roles/import',
            hrefTarget: '_self',
            iconCls: 'x-icon-behavior-large x-icon-behavior-large-wizard',
            tooltip: 'Snapshot an existing Server that is not currently managed by Scalr, and use the snapshot as an Image for your new Role.',
            hidden: !isImportAllowed,
            listeners: {
                boxready: function() {
                    this.btnInnerEl.applyStyles('margin-top: -6px;');
                }
            }
        }, {
            xtype: 'button',
            text: 'Role builder',
            href: '#/roles/builder',
            hrefTarget: '_self',
            hidden: !isBuildAllowed,
            iconCls: 'x-icon-behavior-large x-icon-behavior-large-rolebuilder',
            tooltip: 'Use the Role Builder wizard to bundle supported software into an Image for your new Role.'
        }]
    });
});

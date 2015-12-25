Scalr.regPage('Scalr.ui.images.create', function (loadParams, moduleParams) {
    var platforms = {};
    Ext.Object.each(Scalr.platforms, function(key, value){
        if (value.enabled) {
            platforms[key] = Scalr.utils.getPlatformName(key);
        }
    });

    var isManageAllowed = Scalr.isAllowed('IMAGES_ENVIRONMENT', 'manage');
    var isImportAllowed = Scalr.isAllowed('IMAGES_ENVIRONMENT', 'import');
    var isBuildAllowed =  Scalr.isAllowed('IMAGES_ENVIRONMENT', 'build');
    var allowedActionsCount = Ext.Array.filter([isManageAllowed, isImportAllowed, isBuildAllowed], function (action) {
        return action;
    }).length;

	return Ext.create('Ext.form.Panel', {
        title: 'Choose a image creation method',
        width: allowedActionsCount > 2 ? 410 : 280,
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

        items: [{
            xtype: 'container',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            defaults: {
                xtype: 'button',
                ui: 'simple',
                cls: 'x-btn-simple-large',
                margin: allowedActionsCount > 1 ? '20 0 20 20' : '20 0 20 85',
                iconAlign: 'top'
            },
            items: [{
                xtype: 'button',
                text: "Register\n" +
                    "existing image",
                itemId: 'newImage',
                href: '#/images/register',
                hrefTarget: '_self',
                iconCls: 'x-icon-behavior-large x-icon-behavior-large-mixed',
                tooltip: 'Register existing Image manually.',
                hidden: !isManageAllowed,
                listeners: {
                    boxready: function() {
                        this.btnInnerEl.applyStyles('white-space: pre; padding: 0px;');
                    }
                }
            }, {
                xtype: 'button',
                text: "Image from\n" +
                    "non-Scalr server",
                href: '#/roles/import?image',
                hrefTarget: '_self',
                hidden: !isImportAllowed,
                iconCls: 'x-icon-behavior-large x-icon-behavior-large-wizard',
                tooltip: 'Snapshot an existing Server that is not currently managed by Scalr, and use the snapshot as an Image.',
                listeners: {
                    boxready: function() {
                        this.btnInnerEl.applyStyles('white-space: pre; padding: 0px;');
                    }
                }
            }, {
                xtype: 'button',
                text: 'Image builder',
                href: '#/roles/builder?image',
                hrefTarget: '_self',
                hidden: !isBuildAllowed,
                iconCls: 'x-icon-behavior-large x-icon-behavior-large-rolebuilder',
                tooltip: 'Use the Role Builder wizard to bundle supported software into an Image.'
            }]
        }]
    });
});

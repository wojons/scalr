Scalr.regPage('Scalr.ui.core.variables', function (loadParams, moduleParams) {
    return Ext.create('Ext.form.Panel', {
        scalrOptions: {
            maximize: 'all',
            menuTitle: 'Global Variables',
            menuHref: '#/core/variables',
            menuFavorite: true
        },
        stateId: 'grid-variables-view',
        fieldDefaults: {
            labelWidth: 110
        },
        layout: 'fit',
        items: {
            xtype: 'variablefield',
            name: 'variables',
            currentScope: 'environment',
            cls: 'x-panel-column-left',
            readOnly: !Scalr.isAllowed('GLOBAL_VARIABLES_ENVIRONMENT', 'manage'),
            value: moduleParams.variables
        },
        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons-mini',
            hidden: !Scalr.isAllowed('GLOBAL_VARIABLES_ENVIRONMENT', 'manage'),
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'Save',
                handler: function() {
                    var me = this;

                    var form = me.up('form').getForm();

                    if (form.isValid())
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/core/xSaveVariables/',
                            form: form,
                            success: function (data) {
                                Scalr.defaults = !Ext.isEmpty(data.defaults) ? data.defaults : {};
                                Scalr.event.fireEvent('refresh');
                            }
                        });
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
});

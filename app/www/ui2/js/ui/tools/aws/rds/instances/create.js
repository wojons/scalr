Scalr.regPage('Scalr.ui.tools.aws.rds.instances.create', function (loadParams, moduleParams) {

    return Ext.create('Scalr.rds.instance.form.Panel', {
        locations: moduleParams.locations,
        farms: moduleParams.farms,
        accountId: moduleParams.accountId,
        remoteAddress: moduleParams.remoteAddress,
        scalrOptions: {
            modal: true
        }
    });

});

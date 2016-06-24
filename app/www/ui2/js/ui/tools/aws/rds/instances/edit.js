Scalr.regPage('Scalr.ui.tools.aws.rds.instances.edit', function (loadParams, moduleParams) {

    return Ext.create('Scalr.rds.instance.form.Panel', {
        locations: moduleParams.locations,
        farms: moduleParams.farms,
        cloudLocation: loadParams.cloudLocation,
        instanceData: moduleParams.instance,
        accountId: moduleParams.accountId,
        remoteAddress: moduleParams.remoteAddress,
        scalrOptions: {
            modal: true
        }
    });

});

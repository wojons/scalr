Scalr.regPage('Scalr.ui.public.azure', function (loadParams, moduleParams) {
    if (loadParams['code']) {
        if (Scalr.user.userId) {
            Scalr.event.fireEvent('redirect', '/public/xAzureToken?code=' + loadParams['code']);
        } else {
            Scalr.utils.authWindow.show();
        }
    } else {
        Scalr.message.Error(Ext.htmlEncode(loadParams['error']) || 'Unknown error');
    }
});

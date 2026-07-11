$(function () {
    if (!window.piwik.userLogin || document.querySelector('#loginPage')) {
      return; // do nothing if not a dashboard request
    }
    if (window.msTeamsShouldShowWebhookNotification) {
        var UI = require('piwik/UI');
        var notification = new UI.Notification();
        var translationKey = 'MicrosoftTeams_MicrosoftTeamsWebhookUrlDeprecatedNoticeText';
        if (window.msTeamsAlertModule && window.msTeamsAlertModule === 'CustomAlerts') {
            translationKey = 'MicrosoftTeams_MicrosoftTeamsWebhookUrlDeprecatedNoticeTextCustomAlerts';
        }
        var message = _pk_translate(translationKey, ['<a href="https://matomo.org/faq/reports/how-to-get-microsoft-teams-webhook-url/" target="_blank" rel="noreferrer noopener">', '</a>'])
        notification.show(message,{
          context: 'warning',
          id: 'msTeamsDeprecatedNotification'
        });
    }
});
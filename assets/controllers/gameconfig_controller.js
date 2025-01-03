import { Controller } from 'stimulus';
import { successNotification, errorNotification } from '../helpers/notification';

export default class extends Controller {

    connect()
    {
        for (var i = 1; i < 99999; i++) {
            // no need for any auto-reloading anywhere on the gameconfig page
            window.clearInterval(i);
        }
    }

    async configDownload(event)
    {
        const downloadURL = `/manager/gameconfig/${event.currentTarget.dataset.config}/download`;
        const response = await fetch(downloadURL);
        if (response.status != 200) {
            errorNotification('Could not download configuration file.');
            return;
        }
        // choosing not to read and use the response blob, requires a JS duplication of PHP code
        window.location = downloadURL;
    }

    async configArchive(event)
    {
        let configId = event.currentTarget.dataset.config;
        if (!confirm(
            `This will permanently archive your configuration file. `+
            'It will subsequently no longer be usable. '+
            'However, it will still be downloadable.'+
            'Are you sure?'
        )) {
            return;
        }
        event.currentTarget.disabled = true;
        const response = await fetch(`/manager/gameconfig/${configId}/archive`);
        if (response.status != 204) {
            errorNotification('Configuration archival failed.');
            return;
        }
        window.dispatchEvent(new CustomEvent("modal-closing"));
        successNotification('Configuration file successfully archived.');
        document.querySelector('turbo-frame#configsTable').reload();
    }

}
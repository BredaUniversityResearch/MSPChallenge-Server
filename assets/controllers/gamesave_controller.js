import { Controller } from 'stimulus';
import { successNotification, errorNotification } from '../helpers/notification';

export default class extends Controller {


    connect()
    {
        for (var i = 1; i < 99999; i++) {
            // no need for any auto-reloading anywhere on the gamesave page
            window.clearInterval(i);
        }
    }

    async saveDownload(event)
    {
        const downloadURL = `/manager/saves/${event.currentTarget.dataset.save}/download`;
        const response = await fetch(downloadURL);
        if (response.status != 200) {
            errorNotification('Could not download save file. It might still be in the process of being created.');
            return;
        }
        // choosing not to read and use the response blob, because our save files can become huge
        window.location = downloadURL;
    }

    async saveArchive(event)
    {
        let saveId = event.currentTarget.dataset.save;
        if (!confirm(
            `This will permanently archive save #${saveId}. `+
            'It will subsequently no longer be reloadable. '+
            'However, it will still be downloadable.'+
            'Are you sure?'
        )) {
            return;
        }
        event.currentTarget.disabled = true;
        const response = await fetch(`/manager/saves/${saveId}/archive`);
        if (response.status != 204) {
            errorNotification('Save archival failed.');
            return;
        }
        window.dispatchEvent(new CustomEvent("modal-closing"));
        successNotification('Save successfully archived.');
        document.querySelector('turbo-frame#savesTable').reload();
    }    

}

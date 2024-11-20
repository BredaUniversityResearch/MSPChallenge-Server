import { Controller } from 'stimulus';
import { errorNotification } from '../helpers/notification';

export default class extends Controller {

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
    
}
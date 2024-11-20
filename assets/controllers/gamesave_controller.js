import { Controller } from 'stimulus';
import { success, error } from 'tata-js';

export default class extends Controller {

    async saveDownload(event)
    {
        const downloadURL = `/manager/saves/${event.currentTarget.dataset.save}/download`;
        const response = await fetch(downloadURL);
        if (response.status != 200) {
            error('Error', 'Could not download save file.', { position: 'mm', duration: 10000 });
            return;
        }
        // choosing not to read and use the response blob, because our save files can become huge
        window.location = downloadURL;
    }
    
}
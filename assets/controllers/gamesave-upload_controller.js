import { Controller } from 'stimulus';
import { successNotification } from '../helpers/notification';

// Deliberately well under any post_max_size you'd set (see 10-app.ini) so a single chunk
// request never needs a bump to PHP's ini limits, now or as save files keep growing.
const CHUNK_SIZE = 8 * 1024 * 1024; // 8MB

export default class extends Controller {

    static targets = [
        'fileInput', 'errors', 'progressWrapper', 'progressBar', 'submitButton', 'csrfToken'
    ];

    static values = {
        initUrl: String,
        chunkUrl: String,
        completeUrl: String,
    };

    async upload()
    {
        const file = this.fileInputTarget.files[0];
        this.clearErrors();

        if (!file) {
            this.showErrors(['Please choose a ZIP file to upload.']);
            return;
        }
        if (!file.name.toLowerCase().endsWith('.zip')) {
            this.showErrors(['Please upload a valid ZIP archive.']);
            return;
        }

        this.setBusy(true);

        try {
            const token = await this.initUpload(file);
            await this.uploadChunks(file, token);
            await this.completeUpload(token);

            successNotification('Successfully uploaded your save file. Ready for use.');
            window.dispatchEvent(new CustomEvent('modal-closing'));
            document.querySelector('turbo-frame#savesTable').reload();
        } catch (error) {
            // Network drop, tab closed mid-upload, etc: this is the "basic chunking" tier,
            // so we don't try to resume - just surface the error and let the user retry.
            this.showErrors([error.message || 'Upload failed. Please try again.']);
            this.setBusy(false);
        }
    }

    // The app wraps every JsonResponse as {success, message, payload}; unwrap defensively so
    // this still works if an endpoint ever returns something unwrapped.
    unwrapPayload(data)
    {
        return (data && typeof data === 'object' && 'payload' in data) ? data.payload : data;
    }

    firstErrorMessage(data, fallback)
    {
        const payload = this.unwrapPayload(data);
        if (payload && Array.isArray(payload.errors) && payload.errors.length) {
            return payload.errors[0];
        }
        return (data && data.message) || fallback;
    }

    async initUpload(file)
    {
        const response = await fetch(this.initUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfTokenTarget.value,
            },
            body: JSON.stringify({ filename: file.name, totalSize: file.size }),
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(this.firstErrorMessage(data, 'Could not start upload.'));
        }
        return this.unwrapPayload(data).token;
    }

    async uploadChunks(file, token)
    {
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

        for (let index = 0; index < totalChunks; index++) {
            const start = index * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);

            const response = await fetch(this.chunkUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/octet-stream',
                    'X-Upload-Token': token,
                    'X-Chunk-Index': String(index),
                },
                body: chunk,
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(this.firstErrorMessage(data, 'Upload failed while sending the file.'));
            }

            this.updateProgress(Math.round(((index + 1) / totalChunks) * 100));
        }
    }

    async completeUpload(token)
    {
        const response = await fetch(this.completeUrlValue, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': this.csrfTokenTarget.value,
                'X-Upload-Token': token,
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(this.firstErrorMessage(data, 'Could not finalize upload.'));
        }
    }

    setBusy(busy)
    {
        this.submitButtonTarget.disabled = busy;
        this.fileInputTarget.disabled = busy;
        this.progressWrapperTarget.style.display = busy ? 'block' : 'none';
        if (!busy) {
            this.updateProgress(0);
        }
    }

    updateProgress(percent)
    {
        this.progressBarTarget.style.width = `${percent}%`;
        this.progressBarTarget.textContent = `${percent}%`;
    }

    showErrors(errors)
    {
        this.errorsTarget.innerHTML = errors.map((message) => `<div>${message}</div>`).join('');
    }

    clearErrors()
    {
        this.errorsTarget.innerHTML = '';
    }
}

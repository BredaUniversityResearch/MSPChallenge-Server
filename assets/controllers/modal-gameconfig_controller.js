import { Controller } from 'stimulus';
import { submitFormGeneric } from '../helpers/form';
import Modal from '../helpers/modal';

export default class extends Controller {

    static targets = ['modalGameConfigVersionUploadForm', 'modalGameConfigFilename', 'modalGameConfigFileDescription'];

    modalHelper;

    connect()
    {
        this.modalHelper = new Modal;
    }

    openConfigDetailsModal(event)
    {
        this.modalHelper.setModalDefaultTitle('Configuration File Version Details');
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/gameconfig/${event.currentTarget.dataset.config}/details`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    openNewConfigModal(event)
    {
        this.modalHelper.setModalDefaultTitle('Upload New Configuration File');
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/gameconfig/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    async submitGameConfigVersionUploadModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalGameConfigVersionUploadFormTarget,
            'Successfully uploaded your configuration file. Ready for use.',
            function (result) { 
                window.dispatchEvent(new CustomEvent("modal-closing"));
                document.querySelector('turbo-frame#configsTable').reload();
            }
        )
    }

    async onConfigFileSelection(event)
    {
        if (event.currentTarget.value) {
            const request = await fetch(`/manager/gameconfig/${event.currentTarget.value}/file`);
            const jsonResponse = JSON.parse(await request.text());
            this.modalGameConfigFilenameTarget.value = jsonResponse.filename;
            this.modalGameConfigFilenameTarget.setAttribute('disabled', true);
            this.modalGameConfigFileDescriptionTarget.value = jsonResponse.description;
            this.modalGameConfigFileDescriptionTarget.setAttribute('disabled', true);
        } else {
            this.modalGameConfigFilenameTarget.value = '';
            this.modalGameConfigFilenameTarget.removeAttribute('disabled');
            this.modalGameConfigFileDescriptionTarget.value = '';
            this.modalGameConfigFileDescriptionTarget.removeAttribute('disabled');
        }
    }
}
import { Controller } from 'stimulus';
import { submitFormGeneric } from '../helpers/form';
import Modal from '../helpers/modal';

export default class extends Controller {

    static targets = ['modalSaveLoadForm', 'modalSaveEditForm'];

    modalHelper;

    connect()
    {
        this.modalHelper = new Modal;
    }
    
    setupSaveModal(title)
    {
        document.getElementById('modalDefaultTitle').innerHTML = title;
    }

    openSaveLoadModal(event)
    {
        this.setupSaveModal('Create New Session');
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/saves/${event.currentTarget.dataset.save}/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    openSaveDetailsModal(event)
    {
        this.setupSaveModal('Save Details');
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/saves/${event.currentTarget.dataset.save}/details`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    async submitSaveLoadModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalSaveLoadFormTarget,
            'Successfully reloaded the session. Please wait for it to be finalised...',
            function (sessionId) {
                if (sessionId) {
                    document.getElementById('logToast').setAttribute('data-session', sessionId);
                    window.dispatchEvent(new CustomEvent("session-changing"));
                }
            }
        );
    }

    async submitSaveEditModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalSaveEditFormTarget,
            'Successfully saved your notes.',
            function (result) { 
                window.dispatchEvent(new CustomEvent("modal-closing"));
            }
        )
    }
}

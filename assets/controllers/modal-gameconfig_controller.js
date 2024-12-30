import { Controller } from 'stimulus';
import { submitFormGeneric } from '../helpers/form';
import Modal from '../helpers/modal';

export default class extends Controller {

    static targets = [];

    modalHelper;

    connect()
    {
        this.modalHelper = new Modal;
    }

    setupConfigModal(title)
    {
        document.getElementById('modalDefaultTitle').innerHTML = title;
    }

    openConfigDetailsModal(event)
    {
        this.setupConfigModal('Configuration File Version Details');
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/gameconfig/${event.currentTarget.dataset.config}/details`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }
}
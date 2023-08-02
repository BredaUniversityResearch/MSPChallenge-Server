import { Controller } from 'stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static targets = ['modal'];

    openModal(event)
    {
        this.modal.show();
    }

    closeModal(event)
    {
        this.modal.hide();
    }

    get modal()
    {
        return Modal.getOrCreateInstance(this.modalTarget);
    }
}
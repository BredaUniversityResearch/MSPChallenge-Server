import { Controller } from 'stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {

    static targets = ['modalDefault'];

    openModalDefault(event)
    {
        Modal.getOrCreateInstance(this.modalDefaultTarget).show();
    }

    closeModalDefault()
    {
        Modal.getOrCreateInstance(this.modalDefaultTarget).hide();
    }
}

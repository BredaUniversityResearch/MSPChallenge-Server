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
}

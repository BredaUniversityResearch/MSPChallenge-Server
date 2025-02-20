import { Controller } from 'stimulus';
import { submitFormGeneric } from '../helpers/form';
import Modal from '../helpers/modal';

export default class extends Controller {

    static targets = ['modalNewSessionForm', 'modalUserAccessForm'];

    modalHelper;

    connect()
    {
        this.modalHelper = new Modal;
    }

    setupSessionModal(title)
    {
        this.modalHelper.setModalDefaultTitle(title);
        this.modalHelper.stopReloadModalDefaultBody();
        document.getElementById('sessionInfoLog').innerHTML = '<turbo-frame id="gameLogComplete" src=""></turbo-frame>';
        document.getElementById('sessionInfoLog').style.display = 'none';
    }

    openNewSessionModal()
    {
        this.setupSessionModal('Create New Session');
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = '/manager/gamelist/0/form';
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    openSessionDetailsModal(event)
    {
        this.setupSessionModal(`Session Details #${event.currentTarget.dataset.session}`);
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/gamelist/${event.currentTarget.dataset.session}/details`;
        this.modalHelper.autoReloadModalDefaultBody();
        let frame2 = this.modalHelper.prepAndGetTurboFrame('gameLogComplete');
        frame2.src = `/manager/gamelist/${event.currentTarget.dataset.session}/log/complete`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    openSessionAccessModal(event)
    {
        this.setupSessionModal(`User Access Session #${event.currentTarget.dataset.session}`);
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/gamelist/${event.currentTarget.dataset.session}/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    async submitUserAccessModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalUserAccessFormTarget,
            'User access successfully saved.',
            function (result) { 
                window.dispatchEvent(new CustomEvent("modal-closing"));
            }
        );
    }

    async submitNewSessionModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalNewSessionFormTarget,
            'Successfully added a new session. Please wait for it to be finalised...',
            function (sessionId) {
                if (sessionId) {
                    document.getElementById('logToast').setAttribute('data-session', sessionId);
                    window.dispatchEvent(new CustomEvent("session-changing"));
                }
            }
        );
    }
}

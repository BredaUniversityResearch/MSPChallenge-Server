import { Controller } from 'stimulus';
import { Modal } from 'bootstrap';
import $ from 'jquery';
import { success, error } from 'tata-js';

export default class extends Controller {
    static targets = ['modal', 'modalBody'];

    openModal(event)
    {
        let frame = document.querySelector('turbo-frame#' + this.element.dataset.turboframe);
        frame.innerHTML = '<div class="modal-body"><h3>Loading...</h3></div>';
        frame.reload();
        this.modal.show();
    }

    openDetailsModal({ params: { sessionid } })
    {
        let frame = document.querySelector('turbo-frame#' + this.element.dataset.turboframe);
        frame.innerHTML = '<div class="modal-body"><h3>Loading...</h3></div>';
        frame.src = '/manager/game/details/' + sessionid;
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

    async submitFormNewSession(event)
    {
        let successMessage = 'Successfully added a new session. Please wait for it to be finalised...';
        await this.submitFormGeneric(
            event,
            successMessage,
            function (sessionId) {
                if (sessionId) {
                    $('#logToast').attr('data-session', sessionId);
                    const event = new CustomEvent("session-changing");
                    window.dispatchEvent(event);
                }
            }
        );
        this.modal.hide();
    }

    async submitFormEditSession(event)
    {
        let successMessage = 'Successfully changed the session name.';
        await this.submitFormGeneric(
            event,
            successMessage
        );
    }
    
    async submitFormGeneric(event, successMessage, successCallback = null)
    {
        event.preventDefault();
        const $form = $(this.modalBodyTarget).find('form');
        let button = $form.find('button[type=submit]');
        if (button) {
            var oldHtml = button.html();
            button.html('<i class="fa fa-refresh fa-spin"></i>');
            button.prop('disabled', true);
        }
        try {
            await $.ajax({
                url: $form.prop('action'),
                method: $form.prop('method'),
                data: $form.serialize(),
                dataType: 'json',
                success: successCallback
            });
            success('Success', successMessage, { position: 'mm', duration: 10000 });
        } catch (e) {
            this.modalBodyTarget.innerHTML = e.responseText;
        }
        if (button) {
            button.html(oldHtml);
            button.prop('disabled', false);
        }
    }
}
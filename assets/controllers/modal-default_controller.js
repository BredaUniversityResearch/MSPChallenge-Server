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
        await this.submitFormGeneric(event, successMessage);
    }
    async submitFormGeneric(event, successMessage)
    {
        event.preventDefault();
        let button = $('button[type=submit]');
        if (button) {
            button.html('<i class="fa fa-refresh fa-spin"></i>');
            button.prop('disabled', true);
        }
        const $form = $(this.modalBodyTarget).find('form');
        try {
            await $.ajax({
                url: $form.prop('action'),
                method: $form.prop('method'),
                data: $form.serialize(),
                dataType: 'json',
                success: function (result) {
                    if (result) {
                        $('#logToast').attr('data-session', result);
                        const event = new CustomEvent("session-changing");
                        window.dispatchEvent(event);
                    }
                }
            });
            success(
                'Success',
                successMessage,
                { position: 'mm', duration: 10000 }
            );
            this.modal.hide();
        } catch (e) {
            this.modalBodyTarget.innerHTML = e.responseText;
        }
    }
}
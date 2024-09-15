import { Controller } from 'stimulus';
import { Modal } from 'bootstrap';
import $ from 'jquery';
import { success } from 'tata-js';

export default class extends Controller {
    static targets = ['modalNewSession', 'modalNewSessionBody', 'modalSessionDetails', 'modalSessionAccess'];

    prepAndGetTurboFrame(modalTurboFrame)
    {
        let frame = document.querySelector('turbo-frame#' + modalTurboFrame);
        frame.innerHTML = '<div class="modal-body"><h3>Loading...</h3></div>';
        return frame;
    }

    openNewSessionModal(event)
    {
        let frame = this.prepAndGetTurboFrame('gameForm');
        frame.reload();
        Modal.getOrCreateInstance(this.modalNewSessionTarget).show();
    }

    openSessionDetailsModal(event)
    {
        let frame = this.prepAndGetTurboFrame('gameDetails');
        frame.src = `/manager/game/${event.currentTarget.dataset.session}/details`;
        $('#sessionInfoLog').hide();
        let frame2 = this.prepAndGetTurboFrame('gameLogComplete');
        frame2.src = `/manager/game/${event.currentTarget.dataset.session}/log/complete`;
        Modal.getOrCreateInstance(this.modalSessionDetailsTarget).show();
    }

    openSessionAccessModal(event)
    {
        let frame = this.prepAndGetTurboFrame('gameAccess');
        frame.src = '/manager/game/access/' + event.currentTarget.dataset.session;
        Modal.getOrCreateInstance(this.modalSessionAccessTarget).show();
    }

    closeNewSessionModal(event)
    {
        Modal.getOrCreateInstance(this.modalNewSessionTarget).hide();
    }

    closeSessionDetailsModal(event)
    {
        Modal.getOrCreateInstance(this.modalSessionDetailsTarget).hide();
    }

    closeSessionAccessModal(event)
    {
        Modal.getOrCreateInstance(this.modalSessionAccessTarget).hide();
    }

    async submitFormNewSession(event)
    {
        await this.submitFormGeneric(
            event,
            this.modalNewSessionBodyTarget,
            'Successfully added a new session. Please wait for it to be finalised...',
            function (sessionId) {
                if (sessionId) {
                    $('#logToast').attr('data-session', sessionId);
                    const event = new CustomEvent("session-changing");
                    window.dispatchEvent(event);
                }
            }
        );
    }
    
    async submitFormGeneric(event, target, successMessage, successCallback = null)
    {
        event.preventDefault();
        const $form = $(target).find('form');
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
            target.innerHTML = e.responseText;
        }
        if (button) {
            button.html(oldHtml);
            button.prop('disabled', false);
        }
    }
}
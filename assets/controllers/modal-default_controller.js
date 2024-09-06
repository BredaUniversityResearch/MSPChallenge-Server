import { Controller } from 'stimulus';
import { Modal } from 'bootstrap';
import $ from 'jquery';
import { success } from 'tata-js';

export default class extends Controller {
    static targets = ['modalNewSession', 'modalNewSessionBody', 'modalSessionDetails', 'modalSessionAccess'];

    static gameDetailsFrameReloader;

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
        frame.src = '/manager/game/details/' + event.currentTarget.dataset.session;
        Modal.getOrCreateInstance(this.modalSessionDetailsTarget).show();
        this.startDetailsModalAutoReload();
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
        this.stopDetailsModalAutoReload();
    }

    closeSessionAccessModal(event)
    {
        Modal.getOrCreateInstance(this.modalSessionAccessTarget).hide();
    }

    startDetailsModalAutoReload()
    {
        this.gameDetailsFrameReloader = setInterval(function () {
            document.querySelector('turbo-frame#gameDetails').reload();
        }, 5000);
    }

    stopDetailsModalAutoReload()
    {
        clearInterval(this.gameDetailsFrameReloader);
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
        this.closeNewSessionModal();
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
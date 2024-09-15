import { Controller } from 'stimulus';
import { Modal } from 'bootstrap';
import $ from 'jquery';
import { success } from 'tata-js';

export default class extends Controller {

    static targets = ['modalDefault', 'modalNewSessionForm'];
    static modalDefaultBodyReloader;

    autoReloadModalDefaultBody()
    {
        if (this.modalDefaultBodyReloader === undefined) {
            this.modalDefaultBodyReloader = setInterval(function () {
                document.querySelector('turbo-frame#modalDefaultBody').reload();
            }, 10000);
        }
    }

    stopReloadModalDefaultBody()
    {
        clearInterval(this.modalDefaultBodyReloader);
        this.modalDefaultBodyReloader = undefined;
    }

    resetModalAndReturnBodyFrame(title)
    {
        $('#modalDefaultTitle').html(title);
        $('#sessionInfoLog').hide();
        this.stopReloadModalDefaultBody();
        return this.prepAndGetTurboFrame('modalDefaultBody');
    }

    prepAndGetTurboFrame(frameName)
    {
        let frame = document.querySelector(`turbo-frame#${frameName}`);
        frame.innerHTML = '<div class="modal-body"><h3>Loading...</h3></div>';
        return frame;
    }

    openNewSessionModal()
    {
        let frame = this.resetModalAndReturnBodyFrame('Create New Session');
        frame.src = '/manager/game/form';
        Modal.getOrCreateInstance(this.modalDefaultTarget).show();
    }

    openSessionDetailsModal(event)
    {
        let frame = this.resetModalAndReturnBodyFrame(`Session Details #${event.currentTarget.dataset.session}`);
        frame.src = `/manager/game/${event.currentTarget.dataset.session}/details`;
        this.autoReloadModalDefaultBody();
        let frame2 = this.prepAndGetTurboFrame('gameLogComplete');
        frame2.src = `/manager/game/${event.currentTarget.dataset.session}/log/complete`;
        Modal.getOrCreateInstance(this.modalDefaultTarget).show();
    }

    openSessionAccessModal(event)
    {
        let frame = this.resetModalAndReturnBodyFrame(`User Access Session #${event.currentTarget.dataset.session}`);
        frame.src = `/manager/game/access/${event.currentTarget.dataset.session}`;
        Modal.getOrCreateInstance(this.modalDefaultTarget).show();
    }

    closeModalDefault()
    {
        Modal.getOrCreateInstance(this.modalDefaultTarget).hide();
    }

    async submitFormNewSession(event)
    {
        await this.submitFormGeneric(
            event,
            this.modalNewSessionFormTarget,
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
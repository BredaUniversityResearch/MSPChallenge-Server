import { Controller } from 'stimulus';
import $ from 'jquery';
import { success } from 'tata-js';

export default class extends Controller {

    static targets = ['modalNewSessionForm'];
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
        $('#sessionInfoLog').html('<turbo-frame id="gameLogComplete" src=""></turbo-frame>');
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
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    openSessionDetailsModal(event)
    {
        let frame = this.resetModalAndReturnBodyFrame(`Session Details #${event.currentTarget.dataset.session}`);
        frame.src = `/manager/game/${event.currentTarget.dataset.session}/details`;
        this.autoReloadModalDefaultBody();
        let frame2 = this.prepAndGetTurboFrame('gameLogComplete');
        frame2.src = `/manager/game/${event.currentTarget.dataset.session}/log/complete`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    openSessionAccessModal(event)
    {
        let frame = this.resetModalAndReturnBodyFrame(`User Access Session #${event.currentTarget.dataset.session}`);
        frame.src = `/manager/game/access/${event.currentTarget.dataset.session}`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    async submitNewSessionModalForm(event)
    {
        event.preventDefault();
        const $form = $(this.modalNewSessionFormTarget).find('form');
        let button = $form.find('button[type=submit]');
        var oldButtonHtml = button.html();
        button.html('<i class="fa fa-refresh fa-spin"></i>');
        button.prop('disabled', true);
        try {
            await $.ajax({
                url: $form.prop('action'),
                method: $form.prop('method'),
                data: $form.serialize(),
                dataType: 'json',
                success: function (sessionId) {
                    if (sessionId) {
                        $('#logToast').attr('data-session', sessionId);
                        window.dispatchEvent(new CustomEvent("session-changing"));
                    }
                }
            });
            success(
                'Success',
                'Successfully added a new session. Please wait for it to be finalised...',
                { position: 'mm', duration: 10000 }
            );
        } catch (e) {
            this.modalNewSessionFormTarget.innerHTML = e.responseText;
        }
        button.html(oldButtonHtml);
        button.prop('disabled', false);
    }
}
import { Controller } from 'stimulus';
import $ from 'jquery';
import { successNotification } from '../helpers/notification';

export default class extends Controller {

    static targets = ['modalNewSessionForm', 'modalUserAccessForm'];
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
        document.getElementById('modalDefaultTitle').innerHTML = title;
        document.getElementById('sessionInfoLog').innerHTML = '<turbo-frame id="gameLogComplete" src=""></turbo-frame>';
        document.getElementById('sessionInfoLog').style.display = 'none';
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
        frame.src = '/manager/game/0/form';
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
        frame.src = `/manager/game/${event.currentTarget.dataset.session}/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    async submitUserAccessModalForm(event)
    {
        await this.submitFormGeneric(
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
        await this.submitFormGeneric(
            event,
            this.modalNewSessionFormTarget,
            'Successfully added a new session. Please wait for it to be finalised...',
            function (sessionId) {
                if (sessionId) {
                    $('#logToast').attr('data-session', sessionId);
                    window.dispatchEvent(new CustomEvent("session-changing"));
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
        const response = await fetch($form.prop('action'), {
            method: $form.prop('method'),
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: $form.serialize()
        });
        const responseText = await response.text();
        if (response.status != 200) {
            target.innerHTML = responseText;
        } else {
            successNotification(successMessage);
            successCallback(responseText);
        }
        if (button) {
            button.html(oldHtml);
            button.prop('disabled', false);
        }
    }
}
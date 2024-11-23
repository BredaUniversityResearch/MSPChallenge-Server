import { Controller } from 'stimulus';
import $ from 'jquery';
import { successNotification } from '../helpers/notification';

export default class extends Controller {

    static targets = ['modalSaveLoadForm', 'modalSaveEditForm'];
    
    stopReloadModalDefaultBody()
    {
        clearInterval(this.modalDefaultBodyReloader);
        this.modalDefaultBodyReloader = undefined;
    }
    
    resetModalAndReturnBodyFrame(title)
    {
        document.getElementById('modalDefaultTitle').innerHTML = title;
        return this.prepAndGetTurboFrame('modalDefaultBody');
    }

    prepAndGetTurboFrame(frameName)
    {
        let frame = document.querySelector(`turbo-frame#${frameName}`);
        frame.innerHTML = '<div class="modal-body"><h3>Loading...</h3></div>';
        return frame;
    }

    openSaveLoadModal(event)
    {
        let frame = this.resetModalAndReturnBodyFrame('Create New Session');
        frame.src = `/manager/saves/${event.currentTarget.dataset.save}/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    async submitSaveLoadModalForm(event)
    {
        await this.submitFormGeneric(
            event,
            this.modalSaveLoadFormTarget,
            'Successfully reloaded the session. Please wait for it to be finalised...',
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
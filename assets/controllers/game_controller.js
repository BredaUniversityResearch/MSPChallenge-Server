import { Controller } from 'stimulus';
import { success, error } from 'tata-js';
import $ from "jquery";

export default class extends Controller {
    static values = {
        id: Number,
        name: String
    }

    toggleSessionInfoLog()
    {
        if ($('#sessionInfoLog').is(':visible')) {
            $('#sessionInfoLog').hide();
            const event = new CustomEvent("infolog-hiding");
            window.dispatchEvent(event);
        } else {
            $('#sessionInfoLog').show();
            const event = new CustomEvent("infolog-showing");
            window.dispatchEvent(event);
        }
    }

    reloadGameDetailsFrame()
    {
        document.querySelector('turbo-frame#gameDetails').reload();
    }

    disableButton(target)
    {
        target.disabled = true;
    }

    async editNameThroughPrompt()
    {
        let name = prompt('Session name: ', this.nameValue);
        try {
            await $.ajax({
                url: '/manager/game/name/' + this.idValue,
                method: 'post',
                data: { name: name },
                dataType: 'json'
            });
            success('Success', 'Session name successfully changed.', { position: 'mm', duration: 10000 });
        } catch (e) {
            error('Error', 'Session name change failed.', { position: 'mm', duration: 10000 });
        }
        this.reloadGameDetailsFrame();
    }

    async sessionState(event)
    {
        let state = event.currentTarget.dataset.state;
        this.disableButton(event.currentTarget);
        try {    
            await $.ajax({
                url: `/manager/game/${this.idValue}/state/${state}`,
                method: 'GET'
            });
            success('Success',`Changed state to ${state}`, { position: 'mm', duration: 10000 });
        } catch (e) {
            error('Error', 'Session state change failed. ', { position: 'mm', duration: 10000 });
        }
        // no reload, let autoreload handle it
    }

    async sessionRecreate(event)
    {
        this.disableButton(event.currentTarget);
        try {
            await $.ajax({
                url: `/manager/game/${this.idValue}/recreate`,
                method: 'GET',
                success: function (result) {
                    if (result) {
                        $('#logToast').attr('data-session', result);
                        const event = new CustomEvent("session-changing");
                        window.dispatchEvent(event);
                        const event2 = new CustomEvent("modal-closing");
                        window.dispatchEvent(event2);
                    }
                }
            });
            success('Success', 'Recreating session, please be patient...', { position: 'mm', duration: 10000 });
        } catch (e) {
            error('Error', 'Session recreation failed.', { position: 'mm', duration: 10000 });
        }
    }

    async sessionArchive(event)
    {
        this.disableButton(event.currentTarget);
        try {
            await $.ajax({
                url: `/manager/game/${this.idValue}/archive`,
                method: 'GET',
                success: function (results) {
                    const event = new CustomEvent("modal-closing");
                    window.dispatchEvent(event);
                }
            });
            success('Success', 'Archiving session, please be patient.', { position: 'mm', duration: 10000 });
        } catch (e) {
            error('Error', 'Session archival failed.', { position: 'mm', duration: 10000 });
        }
        this.reloadGameDetailsFrame();
    }

    async sessionSave(event)
    {
        let type = event.currentTarget.dataset.type;
        try {
            await $.ajax({
                url: `/manager/game/${this.idValue}/save/${type}`,
                method: 'GET',
            });
            success('Success', 'Saving session, please be patient. Check the Saves page to find it.',{ position: 'mm', duration: 10000 });
        } catch (e) {
            error('Error', 'Session save failed.', { position: 'mm', duration: 10000 });
        }
    }

    sessionExport()
    {
        window.location = `/manager/game/${this.idValue}/export`;
    }

    async sessionDemo(event)
    {
        if (event.currentTarget.dataset.currentdemosetting == 0) {
            if (!confirm(
                'By enabling demo mode, the simulations will start, '+
                'they will continue until the end (even if you select '+
                'pause along the way), and subsequently the session will '+
                'be recreated. After recreation, the whole process '+
                'continues until you disable demo mode again. '+
                'Are you sure you want to do that?'
            )) {
                return;
            }
        }
        this.disableButton(event.currentTarget);
        try {
            await $.ajax({
                url: `/manager/game/${this.idValue}/demo`,
                method: 'GET',
            });
            success('Success', 'Switched demo mode successfully.', { position: 'mm', duration: 10000 });
        } catch (e) {
            error('Error', 'Demo mode switch failed.', { position: 'mm', duration: 10000 });
        }
        this.reloadGameDetailsFrame();
    }

}
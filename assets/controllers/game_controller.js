import { Controller } from 'stimulus';
import { success, error } from 'tata-js';
import $ from "jquery";

export default class extends Controller {
    static values = {
        id: Number,
        name: String
    }

    startAutoReload()
    {
        if (typeof gameDetailsFrameReloader === 'undefined') {
            var gameDetailsFrameReloader = setInterval(function () {
                this.reloadGameDetailsFrame()
            }, 5000);
            alert('reloader defined');
        }
    }

    reloadGameDetailsFrame()
    {
        document.querySelector('turbo-frame#gameDetails').reload();
    }

    async editNameThroughPrompt()
    {
        let name = prompt('Session name: ', this.nameValue);
        console.log(name);
        try {
            await $.ajax({
                url: '/manager/game/name/' + this.idValue,
                method: 'post',
                data: { name: name },
                dataType: 'json'
            });
            success('Success', 'Session name successfully changed.', { position: 'mm', duration: 10000 });
            this.reloadGameDetailsFrame();
        } catch (e) {
            error('Error', e.responseText, { position: 'mm', duration: 10000 });
        }
    }

    async sessionState(event)
    {
        this.element.disabled = true;
        let state = event.currentTarget.dataset.state;
        try {    
            await $.ajax({
                url: `/manager/game/${this.idValue}/state/${state}`,
                method: 'GET'
            });
            success('Success',`Changed state to ${state}`, { position: 'mm', duration: 10000 });
        } catch (e) {
            error(
                'Error',
                'Session state change failed. ' + e.responseText,
                { position: 'mm', duration: 10000 }
            );
        }
    }

    async sessionRecreate()
    {
        try {
            this.element.disabled = true;
            await $.ajax({
                url: `/manager/game/${this.idValue}/recreate`,
                method: 'GET',
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
                'Recreating session, please be patient...',
                {position: 'mm', duration: 10000}
            );
            this.reloadGameDetailsFrame();
        } catch (e) {
            error(
                'Error',
                'Session recreation failed. ' + e.responseText,
                { position: 'mm', duration: 10000 }
            );
            this.element.disabled = false;
        }
    }

    async sessionArchive()
    {
        try {
            this.element.disabled = true;
            await $.ajax({
                url: `/manager/game/${this.idValue}/archive`,
                method: 'GET',
            });
            success(
                'Success',
                'Archiving session, please be patient.',
                { position: 'mm', duration: 10000 }
            );
            this.reloadGameDetailsFrame();
        } catch (e) {
            error(
                'Error',
                'Session archival failed. ' + e.responseText,
                { position: 'mm', duration: 10000 }
            );
            this.element.disabled = false;
        }
    }

    async sessionSave(event)
    {
        try {
            await $.ajax({
                url: `/manager/game/${this.idValue}/save/${event.currentTarget.dataset.type}`,
                method: 'GET',
            });
            success(
                'Success',
                'Saving session, please be patient. Check the Saves page to find it.',
                { position: 'mm', duration: 10000 }
            );
        } catch (e) {
            error(
                'Error',
                'Session save failed. ' + e.responseText,
                { position: 'mm', duration: 10000 }
            );
        }
    }

    sessionExport()
    {
        window.location = `/manager/game/${this.idValue}/export`;
    }

    async sessionDemo()
    {
        try {
            this.element.disabled = true;
            await $.ajax({
                url: `/manager/game/${this.idValue}/demo`,
                method: 'GET',
            });
            success(
                'Success',
                'Switched demo mode successfully.',
                { position: 'mm', duration: 10000 }
            );
            this.reloadGameDetailsFrame();
        } catch (e) {
            error(
                'Error',
                'Demo mode switch failed. ' + e.responseText,
                { position: 'mm', duration: 10000 }
            );
            this.element.disabled = false;
        }
    }

}
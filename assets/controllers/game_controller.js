import { Controller } from 'stimulus';
import { success, error } from 'tata-js';
import $ from "jquery";

export default class extends Controller {
    static values = {
        id: Number,
        state: String,
        type: String
    }

    async sessionState()
    {
        try {
            this.element.disabled = true;
            await $.ajax({
                url: `/manager/game/${this.idValue}/state/${this.stateValue}`,
                method: 'GET',
            });
            success('Success',`Changed state to ${this.stateValue}`, { position: 'mm', duration: 10000 });
            document.querySelector('turbo-frame#gameDetails').reload();
        } catch (e) {
            error(
                'Error',
                'Session state change failed. ' + e.responseText,
                { position: 'mm', duration: 10000 }
            );
            this.element.disabled = false;
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
            document.querySelector('turbo-frame#gameDetails').reload();
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
            document.querySelector('turbo-frame#gameDetails').reload();
        } catch (e) {
            error(
                'Error',
                'Session archival failed. ' + e.responseText,
                { position: 'mm', duration: 10000 }
            );
            this.element.disabled = false;
        }
    }

    async sessionSave()
    {
        try {
            await $.ajax({
                url: `/manager/game/${this.idValue}/save/${this.typeValue}`,
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
            document.querySelector('turbo-frame#gameDetails').reload();
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
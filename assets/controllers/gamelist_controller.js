import { Controller } from 'stimulus';
import { success, error } from 'tata-js';
import $ from "jquery";

export default class extends Controller {

    connect()
    {
        let frame = document.querySelector('turbo-frame#sessionsTable');
        setInterval(function () {
            frame.reload();
        }, 10000);
    }
    
    toggleSessionInfoLog()
    {
        if ($('#sessionInfoLog').is(':visible')) {
            $('#sessionInfoLog').hide();
        } else {
            $('#sessionInfoLog').show();
        }
    }

    reloadGameDetailsFrame()
    {
        document.querySelector('turbo-frame#modalDefaultBody').reload();
    }

    disableButton(target)
    {
        target.disabled = true;
    }

    async sessionEditName(event)
    {
        let sessionId = event.currentTarget.dataset.session;
        let newName = prompt('Session name: ', event.currentTarget.dataset.name);
        if (newName == null) {
            return;
        }
        try {
            await $.ajax({
                url: `/manager/game/${sessionId}/name`,
                method: 'post',
                data: { name: newName },
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
        let sessionId = event.currentTarget.dataset.session;
        let state = event.currentTarget.dataset.state;
        this.disableButton(event.currentTarget);
        try {    
            await $.ajax({
                url: `/manager/game/${sessionId}/state/${state}`,
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
        let sessionId = event.currentTarget.dataset.session;
        if (!confirm(
            `This will delete and recreate session ID #${sessionId}. ` +
            'All existing data will be lost. Are you sure?'
        )) {
            return;
        }
        this.disableButton(event.currentTarget);
        try {
            await $.ajax({
                url: `/manager/game/${sessionId}/recreate`,
                method: 'GET',
                success: function (sessionId) {
                    if (sessionId) {
                        $('#logToast').attr('data-session', sessionId);
                        window.dispatchEvent(new CustomEvent("session-changing"));
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
        let sessionId = event.currentTarget.dataset.session;
        if (!confirm(
            `This will permanently archive session #${sessionId}. `+
            'It will subsequently no longer be usable by end users. '+
            'If you want a backup, then *first* save this session, *before* continuing! '+
            'Are you sure?'
        )) {
            return;
        }
        this.disableButton(event.currentTarget);
        try {
            await $.ajax({
                url: `/manager/game/${sessionId}/archive`,
                method: 'GET',
                success: function (results) {
                    window.dispatchEvent(new CustomEvent("modal-closing"));
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
        let sessionId = event.currentTarget.dataset.session;
        try {
            await $.ajax({
                url: `/manager/game/${sessionId}/save/${type}`,
                method: 'GET',
            });
            success('Success', 'Saving session, please be patient. Check the Saves page to find it.',{ position: 'mm', duration: 10000 });
        } catch (e) {
            error('Error', 'Session save failed.', { position: 'mm', duration: 10000 });
        }
    }

    sessionExport(event)
    {
        window.location = `/manager/game/${event.currentTarget.dataset.session}/export`;
    }

    async sessionDemo(event)
    {
        let currentDemoSetting = event.currentTarget.dataset.currentdemosetting;
        let sessionId = event.currentTarget.dataset.session;
        if (currentDemoSetting == 0) {
            if (!confirm(
                `By enabling demo mode on session #${sessionId}, the simulations will start, `+
                'they will continue until the end (even if you select '+
                'pause along the way), and subsequently the session will '+
                'be recreated. After recreation, the whole process '+
                'continues until you disable demo mode again. '+
                'Are you sure?'
            )) {
                return;
            }
        }
        this.disableButton(event.currentTarget);
        try {
            await $.ajax({
                url: `/manager/game/${sessionId}/demo`,
                method: 'GET',
            });
            success('Success', 'Switched demo mode successfully.', { position: 'mm', duration: 10000 });
        } catch (e) {
            error('Error', 'Demo mode switch failed.', { position: 'mm', duration: 10000 });
        }
        this.reloadGameDetailsFrame();
    }

}
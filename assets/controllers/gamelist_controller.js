import { Controller } from 'stimulus';
import { successNotification, errorNotification } from '../helpers/notification';

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
        if (document.getElementById('sessionInfoLog').style.display == 'none') {
            document.getElementById('sessionInfoLog').style.display = 'initial';
        } else {
            document.getElementById('sessionInfoLog').style.display = 'none';
        }
    }

    reloadGameDetailsFrame()
    {
        document.querySelector('turbo-frame#modalDefaultBody').reload();
    }

    async sessionEditName(event)
    {
        let sessionId = event.currentTarget.dataset.session;
        let newName = prompt('Session name: ', event.currentTarget.dataset.name);
        let formData = new FormData;
        formData.append('name', newName);
        const response = await fetch(`/manager/gamelist/${sessionId}/name`, {
            method: 'POST',
            body: formData
        });
        if (response.status != 204) {
            errorNotification('Session name change failed. Please make sure it is not empty.');
            return;
        }
        successNotification('Session name successfully changed.');
        this.reloadGameDetailsFrame();
    }

    async sessionState(event)
    {
        let sessionId = event.currentTarget.dataset.session;
        let state = event.currentTarget.dataset.state;
        event.currentTarget.disabled = true;
        const response = await fetch(`/manager/gamelist/${sessionId}/state/${state}`);
        if (response.status != 204) {
            errorNotification('Session state change failed.');
            return;
        }
        successNotification(`Changed state to ${state}.`);
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
        event.currentTarget.disabled = true;
        const response = await fetch(`/manager/gamelist/${sessionId}/recreate`);
        if (response.status != 200) {
            errorNotification('Session recreation failed.');
            return;
        }
        const responseText = await response.text();
        if (responseText) {
            document.querySelector('#logToast').setAttribute('data-session', responseText);
            window.dispatchEvent(new CustomEvent("session-changing"));
        }
        successNotification('Recreating session, please be patient...');
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
        event.currentTarget.disabled = true;
        const response = await fetch(`/manager/gamelist/${sessionId}/archive`);
        if (response.status != 204) {
            errorNotification('Session archival failed.');
            return;
        }
        window.dispatchEvent(new CustomEvent("modal-closing"));
        successNotification('Archiving session, please be patient.');
    }

    async sessionSave(event)
    {
        let type = event.currentTarget.dataset.type;
        let sessionId = event.currentTarget.dataset.session;
        const response = await fetch (`/manager/gamelist/${sessionId}/save/${type}`);
        if (response.status != 204) {
            errorNotification('Session save failed.');
            return;
        }
        successNotification('Saving session, please be patient. Check the Saves page to find it.');
    }

    async sessionExport(event)
    {
        const downloadURL = `/manager/gamelist/${event.currentTarget.dataset.session}/export`;;
        const response = await fetch(downloadURL);
        if (response.status != 200) {
            errorNotification('Could not download exported config file.');
            return;
        }
        // choosing not to read and use the response blob, because our config files can become a little big
        window.location = downloadURL;
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
        event.currentTarget.disabled = true;
        const response = await fetch (`/manager/gamelist/${sessionId}/demo`);
        if (response.status != 204) {
            errorNotification('Demo mode switch failed.');
            return;
        }
        successNotification('Switched demo mode successfully.');
        this.reloadGameDetailsFrame();
    }

}
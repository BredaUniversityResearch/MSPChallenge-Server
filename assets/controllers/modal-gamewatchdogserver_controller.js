import { Controller } from 'stimulus';
import { submitFormGeneric } from '../helpers/form';
import Modal from '../helpers/modal';
import { errorNotification } from '../helpers/notification';

export default class extends Controller {

    static targets = ['modalGameWatchdogServerForm'];

    modalHelper;

    connect()
    {
        this.modalHelper = new Modal;
    }

    openGameWatchdogServerModal(event)
    {
        this.modalHelper.setModalDefaultTitle('Watchdog servers');
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/gamewatchdogserver/list`;
        let frame2 = this.modalHelper.prepAndGetTurboFrame('settingForm');
        frame2.src = `/manager/gamewatchdogserver/0/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    editGameWatchdogServerInModal(event)
    {
        let frame = this.modalHelper.prepAndGetTurboFrame('settingForm');
        frame.src = `/manager/gamewatchdogserver/${event.currentTarget.dataset.geoserver}/form`;
    }
    
    async submitGameWatchdogServerModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalGameWatchdogServerFormTarget,
            'Successfully added or updated Watchdog server.',
            function (result) {
                document.querySelector('turbo-frame#modalDefaultBody').reload();
                document.querySelector('turbo-frame#settingForm').src = `/manager/gamewatchdogserver/0/form`;
                document.querySelector('turbo-frame#settingsTable').reload();
            }
        );
    }

    async toggleGameWatchdogServerAvailability(event)
    {
        event.currentTarget.innerHTML = '<i class="fa fa-refresh fa-spin"></i>';
        event.currentTarget.setAttribute('disabled', true);
        const response = await fetch(`/manager/gamewatchdogserver/${event.currentTarget.dataset.geoserver}/availability`);
        if (response.status != 204) {
            errorNotification('WatchdogServer availability change failed.');
            return;
        }
        document.querySelector('turbo-frame#modalDefaultBody').reload();
        document.querySelector('turbo-frame#settingsTable').reload();
    }
}

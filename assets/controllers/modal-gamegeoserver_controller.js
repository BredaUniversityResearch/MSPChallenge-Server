import { Controller } from 'stimulus';
import { submitFormGeneric } from '../helpers/form';
import Modal from '../helpers/modal';
import { successNotification, errorNotification } from '../helpers/notification';

export default class extends Controller {

    static targets = ['modalGameGeoServerForm'];

    modalHelper;

    connect()
    {
        this.modalHelper = new Modal;
    }

    openGameGeoServerModal(event)
    {
        this.modalHelper.setModalDefaultTitle('GeoServers');
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/gamegeoserver/list`;
        let frame2 = this.modalHelper.prepAndGetTurboFrame('settingForm');
        frame2.src = `/manager/gamegeoserver/0/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    editGameGeoServerInModal(event)
    {
        let frame = this.modalHelper.prepAndGetTurboFrame('settingForm');
        frame.src = `/manager/gamegeoserver/${event.currentTarget.dataset.geoserver}/form`;
    }
    
    async submitGameGeoServerModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalGameGeoServerFormTarget,
            'Successfully added or updated GeoServer.',
            function (result) {
                document.querySelector('turbo-frame#modalDefaultBody').reload();
                document.querySelector('turbo-frame#settingForm').src = `/manager/gamegeoserver/0/form`;
            }
        );
    }

    async toggleGameGeoServerAvailability(event)
    {
        const response = await fetch(`/manager/gamegeoserver/${event.currentTarget.dataset.geoserver}/availability`);
        if (response.status != 204) {
            errorNotification('GameGeoServer availability change failed.');
            return;
        }
        document.querySelector('turbo-frame#modalDefaultBody').reload();
    }
}

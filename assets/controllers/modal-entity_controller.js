import { Controller } from 'stimulus';
import { submitFormGeneric } from '../helpers/form';
import Modal from '../helpers/modal';
import { errorNotification } from '../helpers/notification';

export default class extends Controller {

    static targets = ['modalEntityForm'];

    modalHelper;

    connect()
    {
        this.modalHelper = new Modal;
    }

    openEntityModal(event)
    {
        this.modalHelper.setModalDefaultTitle(event.params.entityDesc);
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/entity/${event.params.entityName}/list`;
        let frame2 = this.modalHelper.prepAndGetTurboFrame('settingForm');
        frame2.src = `/manager/entity/${event.params.entityName}/0/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    editEntityInModal(event)
    {
        let frame = this.modalHelper.prepAndGetTurboFrame('settingForm');
        frame.src = `/manager/entity/${event.params.entityName}/${event.params.entityId}/form`;
    }
    
    async submitEntityModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalEntityFormTarget,
            `Successfully added or updated ${event.params.entityDesc}.`,
            function (result) {
                document.querySelector('turbo-frame#modalDefaultBody').reload();
                document.querySelector('turbo-frame#settingForm').src = `/manager/entity/${event.params.entityName}/0/form`;
                document.querySelector('turbo-frame#settingsTable').reload();
            }
        );
    }

    async toggleEntityProperty(event)
    {
        event.currentTarget.innerHTML = '<i class="fa fa-refresh fa-spin"></i>';
        event.currentTarget.setAttribute('disabled', true);
        const response = await fetch(`/manager/entity/${event.params.entityName}/${event.params.entityId}/toggle/${event.params.propertyName}`);
        if (response.status != 204) {
            errorNotification(`${event.params.entityDesc} availability change failed.`);
            return;
        }
        document.querySelector('turbo-frame#modalDefaultBody').reload();
        document.querySelector('turbo-frame#settingsTable').reload();
    }
}

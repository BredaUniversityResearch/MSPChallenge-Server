import { Controller } from 'stimulus';
import { submitFormGeneric } from '../helpers/form';
import { successNotification, errorNotification } from '../helpers/notification';
import Modal from '../helpers/modal';

export default class extends Controller {

    static targets = ['modalSettingDescriptionForm', 'modalSettingUsersForm'];

    modalHelper;

    connect()
    {
        this.modalHelper = new Modal;
    }

    openSettingDescriptionModal(event)
    {
        this.modalHelper.setModalDefaultTitle('Edit Server Description');
        document.querySelector(`turbo-frame#settingForm`).innerHTML = '';
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/setting/${event.currentTarget.dataset.setting}/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    openSettingUsersModal(event)
    {
        this.modalHelper.setModalDefaultTitle('Server Users');
        let frame = this.modalHelper.prepAndGetTurboFrame();
        frame.src = `/manager/setting/users/list`;
        let frame2 = this.modalHelper.prepAndGetTurboFrame('settingForm');
        frame2.src = `/manager/setting/users/form`;
        window.dispatchEvent(new CustomEvent("modal-opening"));
    }

    async deleteSettingUser(event)
    {
        let formData = new FormData;
        formData.append('delurl', event.currentTarget.dataset.delurl);
        const response = await fetch(`/manager/setting/users/delete`, {
            method: 'POST',
            body: formData
        });
        if (response.status != 204) {
            const responseText = await response.text();
            errorNotification(responseText);
            return;
        }
        successNotification('User successfully removed.');
        document.querySelector('turbo-frame#modalDefaultBody').reload();
    }

    async submitSettingUsersModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalSettingUsersFormTarget,
            'Successfully added new user.',
            function (result) {
                document.querySelector('turbo-frame#modalDefaultBody').reload();
                document.querySelector('turbo-frame#settingForm').reload();
            }
        );
    }

    async submitSettingDescriptionModalForm(event)
    {
        await submitFormGeneric(
            event,
            this.modalSettingDescriptionFormTarget,
            'Successfully saved server description.',
            function (result) {
                document.querySelector('turbo-frame#settingsTable').reload();
                window.dispatchEvent(new CustomEvent("modal-closing"));
            }
        );
    }
}

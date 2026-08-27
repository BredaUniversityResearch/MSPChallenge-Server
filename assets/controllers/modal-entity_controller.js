import { Controller } from 'stimulus';
import { submitFormGeneric } from '../helpers/form';
import Modal from '../helpers/modal';
import { successNotification, errorNotification } from '../helpers/notification';

export default class extends Controller {

    static targets = ['modalEntityForm'];

    modalHelper;
    boundOnFrameLoad;

    connect()
    {
        this.modalHelper = new Modal;
        this.boundOnFrameLoad = this.onFrameLoad.bind(this);
        document.addEventListener('turbo:frame-load', this.boundOnFrameLoad);
        this.updateConditionalFields();
    }

    disconnect()
    {
        document.removeEventListener('turbo:frame-load', this.boundOnFrameLoad);
    }

    onFrameLoad(event)
    {
        if (event.target && event.target.id === 'settingForm') {
            this.updateConditionalFields();
        }
    }

    updateConditionalFields(event = null)
    {
        if (!this.hasModalEntityFormTarget) {
            return;
        }

        // Only react to relevant field interactions when called as an event handler.
        // Keep full recalculation for connect()/frame-load calls where no event is passed.
        if (event && event.target) {
            const target = event.target;
            const isRelevant =
                target.dataset?.conditionalController === 'true' ||
                typeof target.dataset?.conditionalShowWhen !== 'undefined';
            if (!isRelevant) {
                return;
            }
        }

        const root = this.modalEntityFormTarget;
        const form = root.querySelector('form');
        if (!form) {
            return;
        }

        const conditionalFields = root.querySelectorAll('[data-conditional-show-when]');
        conditionalFields.forEach((fieldEl) => {
            const rule = fieldEl.dataset.conditionalShowWhen || '';
            const parts = rule.split('=');
            if (parts.length !== 2) {
                return;
            }

            const triggerName = parts[0].trim();
            const expectedValue = parts[1].trim();
            const trigger = form.querySelector(`[name$="[${triggerName}]"]`);
            if (!trigger) {
                return;
            }

            const isVisible = String(trigger.value) === expectedValue;
            fieldEl.style.display = isVisible ? '' : 'none';

            // Prevent hidden credential fields from being validated/submitted unexpectedly.
            fieldEl.querySelectorAll('input, select, textarea').forEach((inputEl) => {
                inputEl.disabled = !isVisible;
            });
        });

        this.updateTestConnectionButtonState(form);
    }

    updateTestConnectionButtonState(form)
    {
        const testButton = this.modalEntityFormTarget.querySelector('[data-action*="modal-entity#testConnection"]');
        if (!testButton) {
            return;
        }

        const accessTypeInput = form.querySelector('[name$="[accessType]"]');
        const usernameInput = form.querySelector('[name$="[username]"]');
        const passwordInput = form.querySelector('[name$="[password]"]');

        const isCredentialsMode = !accessTypeInput || accessTypeInput.value === 'credentials';

        // When editing an existing entity (entityId > 0) the credential fields render blank
        // because PasswordType never pre-fills for security. The backend will use the stored
        // DB values in that case, so we only require filled fields for brand-new entities.
        const entityId = parseInt(testButton.dataset.modalEntityEntityIdParam || '0', 10);
        const isNewEntity = entityId === 0;

        const hasUsername = !isNewEntity || !!(usernameInput && usernameInput.value.trim());
        const hasPassword = !isNewEntity || !!(passwordInput && passwordInput.value.trim());

        const shouldDisable = isCredentialsMode && (!hasUsername || !hasPassword);
        testButton.disabled = shouldDisable;
        testButton.title = shouldDisable
            ? 'Set both username and password before testing credentialed access.'
            : '';
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

    async testConnection(event)
    {
        const button = event.currentTarget;
        if (button.disabled) {
            return;
        }

        const entityName = event.params.entityName;
        const entityId = event.params.entityId ?? 0;
        const formEl = this.modalEntityFormTarget.querySelector('form');

        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fa fa-refresh fa-spin"></i> Testing...';
        button.setAttribute('disabled', true);

        try {
            const formData = new FormData(formEl);
            const response = await fetch(
                `/manager/entity/${entityName}/${entityId}/test-connection`,
                { method: 'POST', body: formData }
            );
            let result = {};
            try {
                result = await response.json();
            } catch (_) {
                // Non-JSON body (e.g. Symfony error page) — treat as failure.
            }

            const message = result.message || (response.ok
                ? 'Connection successful.'
                : 'Connection test failed.');

            const advertisedLayerCount = result.payload && typeof result.payload.advertisedLayerCount === 'number'
                ? result.payload.advertisedLayerCount
                : null;
            if (advertisedLayerCount !== null) {
                console.log(`GeoServer test: ${advertisedLayerCount} advertised WMS layer(s) reported by GetCapabilities.`);
            }

            // Use HTTP status as primary signal: success only on 2xx with explicit success flag.
            if (response.ok && result.success === true) {
                successNotification(message);
            } else {
                errorNotification(message);
            }
        } catch (e) {
            errorNotification('Connection test failed: ' + e.message);
        } finally {
            button.innerHTML = originalHTML;
            button.removeAttribute('disabled');
            this.updateConditionalFields();
        }
    }
}

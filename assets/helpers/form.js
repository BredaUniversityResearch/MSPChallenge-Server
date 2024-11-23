import $ from 'jquery';
import { successNotification } from './notification';

export async function submitFormGeneric(event, target, successMessage, successCallback = null)
{
    event.preventDefault();
    const form = $(target).find('form');
    let button = form.find('button[type=submit]');
    if (button) {
        var oldHtml = button.html();
        button.html('<i class="fa fa-refresh fa-spin"></i>');
        button.prop('disabled', true);
    }
    const response = await fetch(form.prop('action'), {
        method: form.prop('method'),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.serialize()
    });
    const responseText = await response.text();
    if (response.status != 200) {
        target.innerHTML = responseText;
    } else {
        successNotification(successMessage);
        if (successCallback) {
            successCallback(responseText);
        }
    }
    if (button) {
        button.html(oldHtml);
        button.prop('disabled', false);
    }
}
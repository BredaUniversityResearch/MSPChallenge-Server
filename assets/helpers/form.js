import { successNotification } from './notification';

export async function submitFormGeneric(event, target, successMessage, successCallback = null)
{
    event.preventDefault();
    var form = document.querySelector('form');
    var data = new FormData(form);

    let button = document.querySelector('button[type=submit]');
    if (button) {
        var oldHtml = button.innerHTML;
        button.innerHTML = '<i class="fa fa-refresh fa-spin"></i>';
        button.setAttribute('disabled', true);
    }
    
    const response = await fetch(form.getAttribute('action'), {
        method: form.getAttribute('method'),
        headers: {},
        body: data
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
        button.innerHTML = oldHtml;
        button.removeAttribute('disabled');
    }
}

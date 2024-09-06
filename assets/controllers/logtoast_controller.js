import {Controller} from "stimulus";
import $ from 'jquery';

export default class extends Controller {

    static timeout;

    showToast(event)
    {
        $('#logToastTitle').html('Log of session #' + event.currentTarget.dataset.session);
        document.querySelector('turbo-frame#logToastBodyTurboFrame').src = '/manager/game/' + event.currentTarget.dataset.session +'/log';
        $('#logToast').show();
        this.timeout = setInterval(function () {
            document.querySelector('turbo-frame#logToastBodyTurboFrame').reload();
        }, 2000);
    }

    hideToast()
    {
        $('#logToast').hide();
        clearInterval(this.timeout);
    }
}

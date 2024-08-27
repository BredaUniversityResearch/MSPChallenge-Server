import {Controller} from "stimulus";
import $ from 'jquery';

export default class extends Controller {

    static timeout;

    showToast()
    {
        $('#logToastTitle').html('Log of session #' + this.element.dataset.session);
        $('#logToastBodyTurboFrame').attr('src', '/manager/game/' + this.element.dataset.session +'/log');
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

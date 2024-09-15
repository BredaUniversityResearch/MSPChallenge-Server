import {Controller} from "stimulus";
import $ from 'jquery';

export default class extends Controller {

    static timeout;

    showToast(event)
    {
        let sessionId = this.element.dataset.session;
        $('#logToastTitle').html('Log of session #' + sessionId);
        document.querySelector('turbo-frame#gameLogExcerpt').src = '/manager/game/' + sessionId +'/log/excerpt';
        $('#logToast').show();
        this.timeout = setInterval(function () {
            document.querySelector('turbo-frame#gameLogExcerpt').reload();
        }, 2000);
        window.dispatchEvent(new CustomEvent("modal-closing"));
    }

    hideToast()
    {
        $('#logToast').hide();
        clearInterval(this.timeout);
    }
}

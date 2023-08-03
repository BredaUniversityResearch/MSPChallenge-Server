import {Controller} from "stimulus";
import $ from 'jquery';

export default class extends Controller {

    showToast()
    {
        $('#logToastBodyTurboFrame').attr('src', '/manager/game/' + this.element.dataset.session +'/log');
        $('#logToastTitle').html('Creating session ' + this.element.dataset.session);
        $('#logToast').show();
        setInterval(function () {
            document.querySelector('turbo-frame#logToastBodyTurboFrame').reload();
        }, 2000);
    }

    hideToast()
    {
        $('#logToast').hide();
    }

}
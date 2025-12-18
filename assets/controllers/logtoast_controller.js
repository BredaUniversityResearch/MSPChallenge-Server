import {Controller} from "stimulus";

export default class extends Controller {

    static timeout;

    showToast(event)
    {
        let sessionId = this.element.dataset.session;
        document.getElementById('logToastTitle').innerHTML = `Log of session #${sessionId}`;
        document.querySelector('turbo-frame#gameLogExcerpt').src = `/manager/gamelist/${sessionId}/log/excerpt`;
        document.getElementById('logToast').style.display = 'block';
        this.timeout = setInterval(function () {
            document.querySelector('turbo-frame#gameLogExcerpt').reload();
        }, 3000);
        window.dispatchEvent(new CustomEvent("modal-closing"));
    }

    hideToast()
    {
        document.getElementById('logToast').style.display = 'none';
        clearInterval(this.timeout);
    }
}

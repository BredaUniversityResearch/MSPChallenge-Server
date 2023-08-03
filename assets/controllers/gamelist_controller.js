import { Controller } from 'stimulus';


export default class extends Controller {

    connect()
    {
        setInterval(function () {
            let frame = document.querySelector('turbo-frame#sessionsTable');
            frame.reload();
        }, 10000);
    }

}
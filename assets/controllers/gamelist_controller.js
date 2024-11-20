import { Controller } from 'stimulus';


export default class extends Controller {

    connect()
    {
        let frame = document.querySelector('turbo-frame#sessionsTable');
        setInterval(function () {
            frame.reload();
        }, 10000);
    }

}
import { Controller } from 'stimulus';


export default class extends Controller {

    connect()
    {
        let frame = this.getFrame();
        setInterval(function () {
            frame.reload();
        }, 10000);
    }

    applyFilter(event)
    {
        let frame = this.getFrame();
        frame.src = event.currentTarget.dataset.href;
        frame.reload();
    }

    getFrame()
    {
        return document.querySelector('turbo-frame#sessionsTable');
    }

}
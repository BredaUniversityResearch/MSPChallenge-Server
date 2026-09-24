import { Controller } from 'stimulus';


export default class extends Controller {

    applyFilter(event)
    {
        const frame = document.querySelector(event.currentTarget.dataset.frame);
        if (!frame) {
            return;
        }
        frame.src = event.currentTarget.dataset.href;
        frame.reload();
    }

}

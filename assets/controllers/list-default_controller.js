import { Controller } from 'stimulus';


export default class extends Controller {

    applyFilter(event)
    {
        let frame = document.querySelector(event.currentTarget.dataset.frame);
        frame.src = event.currentTarget.dataset.href;
        frame.reload();
    }

}
import { Controller } from 'stimulus';


export default class extends Controller {

    applyFilter()
    {
        let frame = document.querySelector('turbo-frame#sessionsTable');
        frame.src = this.element.dataset.href;
        frame.reload();
    }

}
import { Controller } from 'stimulus';
import { success, error } from 'tata-js';

export default class extends Controller {

    setState()
    {
        let newState = this.element.dataset.state;
        // jquery to the right Symfony controller, with the proper POST data
        success('Success', newState, {
            position: 'mm',
            duration: 10000
        });
    }

}
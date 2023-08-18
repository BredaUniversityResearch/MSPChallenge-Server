import { Controller } from 'stimulus';
import { success, error } from 'tata-js';

export default class extends Controller {
    static values = {
        state: String
    }
    setState()
    {
        // jquery to the right Symfony controller, with the proper POST data
        success('Success', this.stateValue, {
            position: 'mm',
            duration: 10000
        });
    }

}
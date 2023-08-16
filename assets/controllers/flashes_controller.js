import { Controller } from 'stimulus';
import { success, error, info } from 'tata-js';

export default class extends Controller {
    static values = {
        success: String,
        error: String,
        notice: String
    }

    connect()
    {
        if (this.successValue) {
            success('Success', this.successValue, {
                position: 'mm',
                duration: 10000
            });
        }
        if (this.errorValue) {
            error('Error', this.errorValue, {
                position: 'mm',
                duration: 10000
            });
        }
        if (this.noticeValue) {
            info('Notice', this.noticeValue, {
                position: 'mm',
                duration: 30000
            });
        }
    }
}
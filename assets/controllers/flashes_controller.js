import { Controller } from 'stimulus';
import { successNotification, errorNotification, noticeNotification } from '../helpers/notification';

export default class extends Controller {
    static values = {
        success: String,
        error: String,
        notice: String
    }

    connect()
    {
        if (this.successValue) {
            successNotification(this.successValue);
        }
        if (this.errorValue) {
            errorNotification(this.errorValue);
        }
        if (this.noticeValue) {
            noticeNotification(this.noticeValue);
        }
    }
}
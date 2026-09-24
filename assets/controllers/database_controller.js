import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        interval: { type: Number, default: 5000 }
    };

    connect()
    {
        this.startRefresh();
    }

    disconnect()
    {
        this.stopRefresh();
    }

    startRefresh()
    {
        this.refreshTimer = setInterval(() => this.refresh(), this.intervalValue);
    }

    stopRefresh()
    {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
    }

    refresh()
    {
        const frame = this.element;
        if (frame.src) {
            frame.reload();
        }
    }
}


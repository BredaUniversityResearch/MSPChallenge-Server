import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["logs"];

    connect()
    {
        this.eventSource = new EventSource('/manager/error/logs/stream');
        this.eventSource.addEventListener('log', this.handleLog.bind(this));
        this.eventSource.onerror = this.handleError.bind(this);
    }

    disconnect()
    {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }

    handleLog(event)
    {
        let source = '', time = '', message = '', stackTrace = '';
        try {
            const logObj = JSON.parse(event.data);
            source = logObj.source || '';
            time = logObj.time || '';
            message = logObj.log || '';
            stackTrace = logObj.stack_trace || '';
        } catch (e) {
            message = event.data;
        }
        const row = document.createElement('tr');
        if (stackTrace) {
            const uniqueId = this.generateUUID();
            try {
                stackTrace = this.formatJsonAsTable(JSON.parse(stackTrace));
            } catch (e) {
                // do nothing if not valid json
            }
            stackTrace = `
                <p class="d-inline-flex gap-1">
                  <a data-bs-toggle="collapse" href="#collapse-${uniqueId}" role="button" aria-expanded="false" aria-controls="collapse-${uniqueId}">
                     <h6><span class="badge text-bg-secondary">More info</span></h6>
                  </a>
                </p>
                <div class="collapse" id="collapse-${uniqueId}">
                    <div class="card card-body">
                    ${stackTrace}
                    </div>
                </div>
            `;
        }
        row.innerHTML = `
            <td data-time="${time}">${this.timeAgo(time)}</td>
            <td>${source}</td>
            <td data-bs-content="test"
                data-bs-toggle="popover"
                data-bs-trigger="hover focus"
                data-bs-placement="top">
                ${message}${stackTrace}
            </td>
        `;
        this.logsTarget.prepend(row);
        this.sortLogRowsByTimeDesc();
    }

    handleError()
    {
        const row = document.createElement('tr');
        row.innerHTML = `<td colspan="6" style="color:red;">[Connection lost to log stream]</td>`;
        this.logsTarget.prepend(row);
    }

    timeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffMs = now - date;

        const seconds = Math.floor(diffMs / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours   = Math.floor(minutes / 60);
        const days    = Math.floor(hours / 24);
        const months  = Math.floor(days / 30);
        const years   = Math.floor(days / 365);

        if (years > 0)   return `${years} year${years > 1 ? 's' : ''} ago`;
        if (months > 0)  return `${months} month${months > 1 ? 's' : ''} ago`;
        if (days > 0)    return `${days} day${days > 1 ? 's' : ''} ago`;
        if (hours > 0)   return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        return `${seconds} second${seconds !== 1 ? 's' : ''} ago`;
    }

    generateUUID() {
        return 'row-' + ([1e7]+-1e3+-4e3+-8e3+-1e11)
            .replace(/[018]/g, c =>
                (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
            );
    }

    formatJsonAsTable(jsonObj) {
        let table = '<table class="table table-borderless"><tbody>';
        for (const [key, value] of Object.entries(jsonObj)) {
            table += `<tr><td>${key}:</td><td>${value}</td></tr>`;
        }
        table += '</tbody></table>';
        return table;
    }

    sortLogRowsByTimeDesc() {
        const rows = Array.from(this.logsTarget.querySelectorAll('table#if-logs-table tr'));
        rows.sort((a, b) => {
            const timeA = new Date(a.cells[0]?.getAttribute('data-time'));
            const timeB = new Date(b.cells[0]?.getAttribute('data-time'));
            return timeB - timeA;
        });
        rows.forEach(row => this.logsTarget.appendChild(row));
    }
}
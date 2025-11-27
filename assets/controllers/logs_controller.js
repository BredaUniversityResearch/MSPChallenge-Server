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
        let source = '', time = '', channel = '', severity = '', message = '', stackTrace = '';
        try {
            const logObj = JSON.parse(event.data);
            source = logObj.tag || '';
            time = logObj.time || '';
            channel = logObj.channel || '';
            severity = logObj.severity || '';
            message = logObj.message || '';
            stackTrace = logObj.stack_trace || '';
        } catch (e) {
            message = event.data;
        }
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${time}</td>
            <td>${source}</td>
            <td>${channel}</td>
            <td>${severity}</td>
            <td>${message}</td>
            <td><pre style="white-space: pre-wrap;">${stackTrace}</pre></td>
        `;
        this.logsTarget.prepend(row);
    }

    handleError()
    {
        const row = document.createElement('tr');
        row.innerHTML = `<td colspan="6" style="color:red;">[Connection lost to log stream]</td>`;
        this.logsTarget.prepend(row);
    }
}

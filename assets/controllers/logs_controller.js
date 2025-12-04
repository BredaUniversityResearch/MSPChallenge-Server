import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["logs"];
    offset = 0;
    errorsHidden = false;
    warningsHidden = false;

    connect()
    {
        this.poll();
    }

    poll()
    {
        fetch(`/manager/error/logs?offset=${this.offset}`)
            .then(response => response.json())
            .then(data => {
                data.payload.lines.reduce(
                    (p, line) => p.then(() => this.handleLog(line)),
                    Promise.resolve()
                );
                this.offset = data.payload.lastLine;
                setTimeout(() => this.poll(), 20000);
            });
    }

    async handleLog(logObj)
    {
        let channel = logObj.channel || '';
        let time = logObj.datetime || '';
        let message = logObj.message || '';
        let extra = logObj.extra || '';
        let levelName = logObj.level_name || '';
        if (levelName === 'CRITICAL') {
            levelName = 'ERROR';
        }
        if ((Array.isArray(extra) && extra.length === 0) || (typeof extra === 'object' && Object.keys(extra).length === 0)) {
            extra = '';
        }
        const messageHash = await this.sha256(message);

        // Try to find an existing row with the same message hash
        const existingRow = this.logsTarget.querySelector(`tr[data-message-hash="${messageHash}"]`);
        if (existingRow) {
            // Increment count
            const countCell = existingRow.querySelector('td[data-count]');
            let count = parseInt(countCell.textContent, 10) || 1;
            countCell.textContent = count + 1;
            // Update time
            const timeCell = existingRow.querySelector('td[data-time]');
            if (new Date(timeCell.getAttribute('data-time')) < new Date(time)) {
                timeCell.textContent = this.timeAgo(time);
                timeCell.setAttribute('data-time', time);
            }
            this.sortLogRowsByTimeDesc();
            return;
        }

        const row = document.createElement('tr');
        row.classList.add(levelName === 'ERROR' ? 'table-danger': 'table-warning');
        row.setAttribute('data-message-hash', messageHash);
        if (extra) {
            const uniqueId = this.generateUUID();
            try {
                extra = this.formatAsTable(extra);
            } catch (e) {
                // do nothing if not valid json
            }
            extra = `
                <p class="d-inline-flex gap-1">
                  <a data-bs-toggle="collapse" href="#collapse-${uniqueId}" role="button" aria-expanded="false" aria-controls="collapse-${uniqueId}">
                     <h6><span class="badge text-bg-secondary">More info</span></h6>
                  </a>
                </p>
                <div class="collapse" id="collapse-${uniqueId}">
                    <div class="card card-body">
                        <div style="display:block;width:0;min-width:100%;overflow-x:auto;">
                            ${extra}
                        </div>
                    </div>
                </div>
            `;
        }
        row.innerHTML = `
            <td data-count class="w-auto text-nowrap">1</td>
            <td data-time="${time}" class="w-auto text-nowrap">${this.timeAgo(time)}</td>
            <td class="w-auto text-nowrap">${channel}</td>
            <td>${message}${extra}</td>
        `;
        this.logsTarget.prepend(row);
        this.sortLogRowsByTimeDesc();
    }

    timeAgo(dateString)
    {
        const now = new Date();
        const date = new Date(dateString);
        const diffMs = now - date;

        const seconds = Math.floor(diffMs / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours   = Math.floor(minutes / 60);
        const days    = Math.floor(hours / 24);
        const months  = Math.floor(days / 30);
        const years   = Math.floor(days / 365);

        if (years > 0) {
            return `${years} year${years > 1 ? 's' : ''} ago`;
        }
        if (months > 0) {
            return `${months} month${months > 1 ? 's' : ''} ago`;
        }
        if (days > 0) {
            return `${days} day${days > 1 ? 's' : ''} ago`;
        }
        if (hours > 0) {
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        }
        if (minutes > 0) {
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        }
        return `${seconds} second${seconds !== 1 ? 's' : ''} ago`;
    }

    generateUUID()
    {
        return 'row-' + ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(
            /[018]/g,
            c=> (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
        );
    }

    formatAsTable(obj)
    {
        let table = '<table class="table table-borderless"><tbody>';
        for (const [key, value] of Object.entries(obj)) {
            let displayValue;
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                displayValue = this.formatAsTable(value);
            } else {
                displayValue = Array.isArray(value) ? JSON.stringify(value) : value;
            }
            table += `<tr><td class="pt-0">${key}:</td><td class="pt-0">${displayValue}</td></tr>`;
        }
        table += '</tbody></table>';
        return table;
    }

    sortLogRowsByTimeDesc()
    {
        const rows = Array.from(this.logsTarget.querySelectorAll(':scope > tr'));
        rows.sort((a, b) => {
            const timeA = new Date(a.cells[1]?.getAttribute('data-time'));
            const timeB = new Date(b.cells[1]?.getAttribute('data-time'));
            return timeB - timeA;
        });
        rows.forEach(row => this.logsTarget.appendChild(row));
    }

    async sha256(str)
    {
        const encoder = new TextEncoder();
        const data = encoder.encode(str);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    toggleErrors()
    {
        this.errorsHidden = !this.errorsHidden;
        const errorRows = this.logsTarget.querySelectorAll(".table-danger");
        errorRows.forEach(row => {
            row.style.display = this.errorsHidden ? "none" : "";
        });
        const btn = document.getElementById("toggle-errors");
        if (this.errorsHidden) {
            btn.classList.remove("text-bg-danger");
            btn.classList.add("text-bg-secondary");
        } else {
            btn.classList.remove("text-bg-secondary");
            btn.classList.add("text-bg-danger");
        }
    }

    toggleWarnings()
    {
        this.warningsHidden = !this.warningsHidden;
        const warningRows = this.logsTarget.querySelectorAll(".table-warning");
        warningRows.forEach(row => {
            row.style.display = this.warningsHidden ? "none" : "";
        });
        const btn = document.getElementById("toggle-warnings");
        if (this.warningsHidden) {
            btn.classList.remove("text-bg-warning");
            btn.classList.add("text-bg-secondary");
        } else {
            btn.classList.remove("text-bg-secondary");
            btn.classList.add("text-bg-warning");
        }
    }
}
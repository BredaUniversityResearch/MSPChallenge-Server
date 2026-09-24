/*
 * Converts server-rendered (UTC) log timestamps to the visitor's local timezone.
 * The server renders a best-effort UTC time/day-header as a fallback (e.g. for
 * no-JS or before this script runs); this script rewrites those in place using
 * the browser's own timezone, and regroups the "day header" rows so that day
 * boundaries reflect the viewer's local calendar day rather than the server's.
 */

const timeFormatter = new Intl.DateTimeFormat(undefined, {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
});

const dayFormatter = new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric'
});

const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
});

function localDayKey(date)
{
    // Locale-independent key to detect when the local calendar day changes.
    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

function localizeLogList(list)
{
    // Remove any server-rendered day headers; they're based on server time and
    // will be re-inserted below based on the viewer's local day.
    list.querySelectorAll(':scope > li.log-day-header').forEach((header) => header.remove());

    let lastDayKey = null;
    list.querySelectorAll(':scope > li.log-entry[data-datetime]').forEach((entry) => {
        const date = new Date(entry.dataset.datetime);
        if (isNaN(date.getTime())) {
            return;
        }

        const dayKey = localDayKey(date);
        if (dayKey !== lastDayKey) {
            const header = document.createElement('li');
            header.className = 'list-group-item list-group-item-secondary text-center fw-bold log-day-header';
            header.textContent = dayFormatter.format(date);
            list.insertBefore(header, entry);
            lastDayKey = dayKey;
        }

        const badge = entry.querySelector(':scope > .log-time');
        if (badge) {
            badge.textContent = timeFormatter.format(date);
        }
    });
}

function localizeLogTimestamps(root = document)
{
    root.querySelectorAll('.log-entry-list').forEach(localizeLogList);
    localizeUnixTimestamps(root);
}

function localizeUnixTimestamps(root = document)
{
    // Simple standalone timestamps (e.g. "Setup Time" / "Time Until End"),
    // rendered server-side as a unix (seconds) timestamp with a UTC fallback text.
    root.querySelectorAll('.local-time[data-unix]').forEach((el) => {
        const seconds = Number(el.dataset.unix);
        if (!Number.isFinite(seconds)) {
            return;
        }
        el.textContent = dateTimeFormatter.format(new Date(seconds * 1000));
    });
}

document.addEventListener('DOMContentLoaded', () => localizeLogTimestamps());
document.addEventListener('turbo:load', () => localizeLogTimestamps());
document.addEventListener('turbo:frame-load', (event) => localizeLogTimestamps(event.target));

module.exports = { localizeLogTimestamps };

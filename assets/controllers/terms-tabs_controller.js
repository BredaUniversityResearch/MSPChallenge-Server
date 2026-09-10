import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tabbar', 'content'];
    static values = { currentPath: String };
    tabs = [];
    activeIndex = 0;

    connect() {
        const label = this.extractTitle(this.contentTarget.innerHTML) ?? 'Terms of Service';
        this.tabs = [{ path: this.currentPathValue, label, html: this.contentTarget.innerHTML }];
        this.activeIndex = 0;
        this.renderTabbar();
    }

    async openTab(event) {
        event.preventDefault();
        const path = event.params.path;

        const existingIndex = this.tabs.findIndex((tab) => tab.path === path);
        if (existingIndex !== -1) {
            this.activate(existingIndex);
            return;
        }

        const response = await fetch(`${window.location.pathname}/document?path=${encodeURIComponent(path)}`);
        const html = await response.text();
        const label = this.extractTitle(html) ?? path.split('/').pop();

        this.tabs.push({ path, label, html });
        this.activate(this.tabs.length - 1);
    }

    extractTitle(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const heading = doc.querySelector('h1, h2');
        return heading ? heading.textContent.trim() : null;
    }

    switchTab(event) {
        this.activate(event.params.index);
    }

    activate(index) {
        this.activeIndex = index;
        this.contentTarget.innerHTML = this.tabs[index].html;
        this.contentTarget.scrollTop = 0;
        this.renderTabbar();
    }

    renderTabbar() {
        this.tabbarTarget.innerHTML = this.tabs
            .map((tab, i) => {
                const activeClass = i === this.activeIndex ? ' active' : '';
                return `<button class="tos-tab${activeClass}" data-action="terms-tabs#switchTab" data-terms-tabs-index-param="${i}">${tab.label}</button>`;
            })
            .join('');
    }
}

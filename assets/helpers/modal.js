export default class Modal {

    static modalDefaultBodyReloader;

    autoReloadModalDefaultBody()
    {
        if (this.modalDefaultBodyReloader === undefined) {
            this.modalDefaultBodyReloader = setInterval(function () {
                document.querySelector('turbo-frame#modalDefaultBody').reload();
            }, 10000);
        }
    }

    stopReloadModalDefaultBody()
    {
        clearInterval(this.modalDefaultBodyReloader);
        this.modalDefaultBodyReloader = undefined;
    }

    prepAndGetTurboFrame(frameName = 'modalDefaultBody')
    {
        let frame = document.querySelector(`turbo-frame#${frameName}`);
        frame.innerHTML = '<div class="modal-body"><h3>Loading...</h3></div>';
        return frame;
    }

    emptyTurboFrame(frameName = 'modalDefaultBody')
    {
        document.querySelector(`turbo-frame#${frameName}`).innerHTML = '';
    }

    setModalDefaultTitle(title)
    {
        document.getElementById('modalDefaultTitle').innerHTML = title;
    }
}

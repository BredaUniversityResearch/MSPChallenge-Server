import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */
// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';

/*
 * https://symfony.com/doc/current/frontend/encore/bootstrap.html#importing-bootstrap-javascript
 * https://symfony.com/doc/current/frontend/encore/legacy-applications.html
 * Importing Bootstrap JavaScript
 * Require bootstrap from any of your JavaScript files:
 */
const $ = require('jquery');
// create global $ and jQuery variables
// global.$ = global.jQuery = $;
// this "modifies" the jquery module: adding behavior to it
// the bootstrap module doesn't export/return anything
require('bootstrap');
// or you can include specific pieces
const TooltipModule = require('bootstrap/js/dist/tooltip');
const Tooltip = TooltipModule.default || TooltipModule;

require('tata-js');
require('./helpers/notification.js');
require('./helpers/form.js');
require('./helpers/modal.js');

/*
 * https://symfony.com/doc/current/frontend/encore/bootstrap.html#using-bootstrap-with-turbo
 * Using Bootstrap with Turbo
 * If you are using bootstrap with Turbo Drive, to allow your JavaScript to load on each page change, wrap the initialization in a turbo:load event listener:
 *
 */
// this waits for Turbo Drive to load
let delegatedTooltip = null;
let delegatedRoot = null;

function enableBootstrapOverlays()
{
    const root = document.body;
    if (delegatedRoot !== root) {
        delegatedTooltip?.dispose();
        delegatedTooltip = null;
        delegatedRoot = root;
    }

    if (!delegatedTooltip) {
        // Delegate tooltips from <body> so elements injected by Turbo Frames also work.
        delegatedTooltip = new Tooltip(root, {
            selector: '[data-bs-toggle="tooltip"]',
            container: 'body',
            delay: { show: 0, hide: 0 }
        });
    }
}

function closeBootstrapOverlays(root = document)
{
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        Tooltip.getInstance(el)?.hide();
    });
}

document.addEventListener('DOMContentLoaded', enableBootstrapOverlays);
document.addEventListener('turbo:load', enableBootstrapOverlays);
document.addEventListener('turbo:frame-load', enableBootstrapOverlays);
document.addEventListener('turbo:before-frame-render', (event) => closeBootstrapOverlays(event.target));
document.addEventListener('turbo:before-render', closeBootstrapOverlays);
document.addEventListener('turbo:before-cache', closeBootstrapOverlays);

/*
 * https://symfony.com/doc/current/frontend/encore/bootstrap.html#using-other-bootstrap-jquery-plugins
 * Using other Bootstrap / jQuery Plugins
 *   If you need to use jQuery plugins that work well with jQuery, you may need to use Encore's autoProvidejQuery()
 *   method so that these plugins know where to find jQuery. Then, you can include the needed JavaScript and CSS like
 *   normal:
 */
 // ...
//// require the JavaScript
//require('bootstrap-star-rating');
//// require 2 CSS files needed
//require('bootstrap-star-rating/css/star-rating.css');
//require('bootstrap-star-rating/themes/krajee-svg/theme.css');

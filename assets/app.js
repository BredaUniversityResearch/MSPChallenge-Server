import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */
// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';
// start the Stimulus application
import './bootstrap';

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
// require('bootstrap/js/dist/tooltip');
// require('bootstrap/js/dist/popover');
$(document).ready(function() {
    $('[data-toggle="popover"]').popover();
});

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
//document.addEventListener('turbo:load', function (e) {
//    // this enables bootstrap tooltips globally
//    let tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
//    let tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
//        return new Tooltip(tooltipTriggerEl)
//    });
//});

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

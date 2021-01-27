<style>
  /* #page-wrapper {
        padding-top: 30px !important;
    } */
  @font-face {
    font-family: 'robotoregular';
    src: url('<?php echo $url_app_root; ?>css/fonts/Roboto-Regular-webfont.eot');
    src: url('<?php echo $url_app_root; ?>css/fonts/Roboto-Regular-webfont.eot?#iefix') format('embedded-opentype'),
      url('<?php echo $url_app_root; ?>css/fonts/Roboto-Regular-webfont.woff') format('woff'),
      url('<?php echo $url_app_root; ?>css/fonts/Roboto-Regular-webfont.ttf') format('truetype'),
      url('<?php echo $url_app_root; ?>css/fonts/Roboto-Regular-webfont.svg#robotoregular') format('svg');
    font-weight: normal;
    font-style: normal;
  }

  h1,
  h2 {
    text-transform: uppercase;
    padding-top: 10px;
  }

  #header-wrapper {
    background-color: #555555;
    border-bottom: 1px solid #aaa;
    height: 59px;
  }


  .bg-dark {
    background-color: #555555 !important;
  }

 

  


  nav a,
  nav a:visited {
    color: #e3e3e3;
  }

  nav a:hover {
    color: #ffffff;
  }


  a,
  a:visited {
    color: #555555;
    text-decoration: none;
  }

  a:hover {
    color: #555555;
    text-decoration: underline;
  }

  

  

  /* Fixes dropdown menus placed on the right side */
  .ml-auto .dropdown-menu {
    left: auto !important;
    right: 0px;
  }

  .dropdown .nav-link {
    color: #fff;
    text-decoration: none;
  }

  .dropdown .dropdown-menu a {
    color: #033C73;
  }

  .dropdown-menu {
    min-width: 200px;
  }

  .container .dropdown .dropdown-menu a:hover {
    color: #fff;
    background-color: #b91773;
    border-color: #fff;

  }

  .container {
    /* width: 1060px; */
    padding: 0;
    top: auto;
    bottom: 0;
    left: 0;
    right: 0;
    margin: auto;
  }

  #page-wrapper {
    background: rgba(255, 255, 255, 0.3);
    padding: 10px;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    margin: auto;
    color: #000000;
  }

  /* #page-wrapper .container {
    width: inherit;
  } */

  .jumbotron {
    background: none;
  }



  /* this deals with giving font-awesome icons badges since they were deprecated in BS4 */
  *.icon-blue {
    color: #0088cc;
  }

  *.icon-white {
    color: white;
    padding-right: 2rem;
  }

  i {
    text-align: left;
    /* /* vertical-align:middle; */
    position: relative;
  }

  .badge:after {
    content: attr(data-count);
    /* position: absolute; */

    /* background: rgba(0,0,255,1); */
    /* height:1rem; */
    /* top:1rem;
    right:1.5rem;
    width:2rem;
    text-align: center;
    line-height: 2rem;; */
    font-size: 1rem;
    /* border-radius: 50%; */
    color: white;
    /* border:1px solid blue; */
  }

  .col-sm-12 {
    width: 80%;
    flex: none;
    margin: auto;
  }

  html {
    min-height: 100%;
    /* make sure it is at least as tall as the viewport */
    position: relative;
  }

  body {
    font-family: 'robotoregular';
    font-size: 14px;
    margin: 0;
    padding: 0;
    background-image: url(<?php echo $url_app_root . 'images/Main_HEADER_003.jpg'; ?> );
    background-repeat: no-repeat;
    background-attachment: fixed;
    background-size: cover;
  }


  #sidebar {
    overflow: hidden;
    z-index: 3;
    max-width: 180px;
  }

  #sidebar .list-group {
    min-width: 250px;
    background-color: #023c73;
    min-height: 100vh;
  }

  #sidebar i {
    margin-right: 6px;
  }

  #sidebar .list-group-item {
    border-radius: 0;
    background-color: #04519b;
    color: rgba(255, 255, 255, 0.8);
    border-left: 0;
    border-right: 0;
    white-space: nowrap;
  }

  #sidebar .list-group-item:hover {
    color: #fff;
  }

  /* highlight active menu */
  #sidebar .list-group-item:not(.collapsed) {
    background-color: #222;
  }

  /* closed state */
  #sidebar .list-group .list-group-item[aria-expanded="false"]::after {
    content: " \f0d7";
    font-family: FontAwesome;
    display: inline;
    text-align: right;
    padding-left: 5px;
  }

  /* open state */
  #sidebar .list-group .list-group-item[aria-expanded="true"] {
    background-color: #033C73;
  }

  #sidebar .list-group .list-group-item[aria-expanded="true"]::after {
    content: " \f0da";
    font-family: FontAwesome;
    display: inline;
    text-align: right;
    padding-left: 5px;
  }

  /* level 1*/
  #sidebar .list-group .collapse .list-group-item,
  #sidebar .list-group .collapsing .list-group-item {
    padding-left: 20px;
    background-color: #fff;
    color: #033C73;
  }

  #sidebar .list-group .collapse .list-group-item:hover,
  #sidebar .list-group .collapsing .list-group-item:hover {
    background-color: #2FA4E7;
  }

  /* level 2*/
  #sidebar .list-group .collapse>.collapse .list-group-item,
  #sidebar .list-group .collapse>.collapsing .list-group-item {
    padding-left: 30px;
  }

  /* level 3*/
  #sidebar .list-group .collapse>.collapse>.collapse .list-group-item {
    padding-left: 40px;
  }

  @media (max-width:767px) {

    #sidebar {
      min-width: 35px;
      max-width: 40px;
      overflow-y: auto;
      overflow-x: visible;
      transition: all 0.25s ease;
      transform: translateX(-45px);
      position: fixed;
    }

    #sidebar.show {
      transform: translateX(0);
    }

    #sidebar::-webkit-scrollbar {
      width: 0px;
    }

    #sidebar,
    #sidebar .list-group {
      min-width: 35px;
      overflow: visible;
      position: relative;
    }

    /* overlay sub levels on small screens */
    #sidebar .list-group .collapse.show,
    #sidebar .list-group .collapsing {
      position: relative;
      z-index: 1;
      width: 190px;
      top: 0;
    }

    #sidebar .list-group>.list-group-item {
      text-align: center;
      padding: .75rem .5rem;
    }

    /* hide caret icons of top level when collapsed */
    #sidebar .list-group>.list-group-item[aria-expanded="true"]::after,
    #sidebar .list-group>.list-group-item[aria-expanded="false"]::after {
      display: none;
    }
  }

  .collapse.show {
    visibility: visible;
  }

  .collapsing {
    visibility: visible;
    height: 0;
    -webkit-transition-property: height, visibility;
    transition-property: height, visibility;
    -webkit-transition-timing-function: ease-out;
    transition-timing-function: ease-out;
  }

  .collapsing.width {
    -webkit-transition-property: width, visibility;
    transition-property: width, visibility;
    width: 0;
    height: 100%;
    -webkit-transition-timing-function: ease-out;
    transition-timing-function: ease-out;
  }


  #sidebarLeft {
    overflow: hidden;
    z-index: 3;
    max-width: 180px;
  }

  #sidebarLeft .list-group {
    min-width: 250px;
    background-color: #023c73;
    min-height: 100vh;
  }

  #sidebarLeft i {
    margin-right: 6px;
  }

  #sidebarLeft .list-group-item {
    border-radius: 0;
    background-color: #04519b;
    color: rgba(255, 255, 255, 0.8);
    border-left: 0;
    border-right: 0;
    white-space: nowrap;
    text-align: left;
  }

  #sidebarLeft .list-group-item:hover {
    color: #fff;
  }

  /* highlight active menu */
  #sidebarLeft .list-group-item:not(.collapsed) {
    background-color: #222;
  }

  /* closed state */
  #sidebarLeft .list-group .list-group-item[aria-expanded="false"]::after {
    content: " \f0d7";
    font-family: FontAwesome;
    display: inline;
    text-align: right;
    padding-left: 5px;
  }

  /* open state */
  #sidebarLeft .list-group .list-group-item[aria-expanded="true"] {
    background-color: #033C73;
  }

  #sidebarLeft .list-group .list-group-item[aria-expanded="true"]::after {
    content: " \f0da";
    font-family: FontAwesome;
    display: inline;
    text-align: right;
    padding-left: 5px;
  }

  /* level 1*/
  #sidebarLeft .list-group .collapse .list-group-item,
  #sidebarLeft .list-group .collapsing .list-group-item {
    padding-left: 20px;
    background-color: #fff;
    color: #033C73;
  }

  #sidebarLeft .list-group .collapse .list-group-item:hover,
  #sidebarLeft .list-group .collapsing .list-group-item:hover {
    background-color: #2FA4E7;
  }

  /* level 2*/
  #sidebarLeft .list-group .collapse>.collapse .list-group-item,
  #sidebarLeft .list-group .collapse>.collapsing .list-group-item {
    padding-left: 30px;
  }

  /* level 3*/
  #sidebarLeft .list-group .collapse>.collapse>.collapse .list-group-item {
    padding-left: 40px;
  }

  @media (max-width:767px) {

    #sidebarLeft {
      min-width: 35px;
      max-width: 40px;
      overflow-y: auto;
      overflow-x: visible;
      transition: all 0.25s ease;
      transform: translateX(45px);
      position: fixed;
    }

    #sidebarLeft.show {
      transform: translateX(0);
    }

    #sidebarLeft::-webkit-scrollbar {
      width: 0px;
    }

    #sidebarLeft,
    #sidebarLeft .list-group {
      min-width: 35px;
      overflow: visible;
      position: relative;
    }

    /* overlay sub levels on small screens */
    #sidebarLeft .list-group .collapse.show,
    #sidebarLeft .list-group .collapsing {
      position: relative;
      z-index: 1;
      width: 190px;
      left: -150px;
      top: 0;
    }

    #sidebarLeft .list-group>.list-group-item {
      text-align: center;
      padding: .75rem .5rem;
    }

    /* hide caret icons of top level when collapsed */
    #sidebarLeft .list-group>.list-group-item[aria-expanded="true"]::after,
    #sidebarLeft .list-group>.list-group-item[aria-expanded="false"]::after {
      display: none;
    }
  }

  .btn-label {
    position: relative;
    left: -9px;
    display: inline-block;
    padding: 6px 12px;
    background: rgba(0, 0, 0, 0.15);
    border-radius: 3px 0 0 3px;
  }

  .btn-labeled {
    padding-top: 0;
    padding-bottom: 0;
    -left: 2px
  }
  
  [data-toggle="collapse"] .fa:before {   
    content: "\f139";
  }

  [data-toggle="collapse"].collapsed .fa:before {
    content: "\f13a";
  }
  
  .stripe {
    background-color: rgba(0,0,0,.05);
  }
  
  .removeborder {
    border-top: hidden;
  }


</style>

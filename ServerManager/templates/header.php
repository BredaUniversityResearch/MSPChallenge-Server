<?php

$pageTitle = 'MSP Challenge Server Manager';

$url_app_root = ServerManager::getInstance()->GetServerManagerFolder();
require_once ServerManager::getInstance()->GetServerManagerRoot().'templates/security_headers.php';

// from here on HTML will be included

require_once('header1_must_include.php'); ?>

<?php /* ALL CSS FIRST! */ ?>

<!-- AKA Primary CSS -->
<link href="<?=$url_app_root;?>css/color_schemes/bootstrap.min.css" rel="stylesheet">

<?php require_once(ServerManager::getInstance()->GetServerManagerRoot() . 'templates/style.php'); ?>

<!-- Template CSS -->
<!-- AKA Secondary CSS -->

<!-- Table Sorting and Such -->
<link href="<?=$url_app_root;?>css/datatables.css" rel="stylesheet">

<!-- Your Custom CSS Goes Here and will override everything above this!-->
<link href="<?=$url_app_root;?>css/custom.css" rel="stylesheet">

<!-- Custom Fonts/Animation/Styling-->

<link href="<?=$url_app_root;?>css/tablesorter-theme.default.css" rel="stylesheet">

<link href="<?=$url_app_root;?>css/manager.css" rel="stylesheet">

<link href="<?=$url_app_root;?>css/font-awesome.min.css" rel="stylesheet">

<link rel="stylesheet" href="<?=$url_app_root;?>css/jquery-ui.min.css">

<link rel="stylesheet" href="<?=$url_app_root;?>css/timepicker.css">

<?php /* END OF ALL CSS! */ ?>



<script src="<?=$url_app_root;?>js/jquery-3.1.1.min.js"></script>
<!-- jQuery Fallback -->
<script type="text/javascript">
if (typeof jQuery == 'undefined') {
	document.write(unescape("%3Cscript src='<?=$url_app_root;?>js/jquery-3.1.1.min.js' type='text/javascript'%3E%3C/script%3E"));
}
</script>

<script src="<?=$url_app_root;?>js/toasts/tata.js"></script>

<script src="<?=$url_app_root;?>js/jquery.tablesorter-2.31.1.min.js"></script>

<script src="<?=$url_app_root;?>js/jquery-ui.min.js"></script>

<script src="<?=$url_app_root;?>js/jquery.blockUI.min.js"></script>

<script src="<?=$url_app_root;?>js/validator-0.11.9.min.js"></script>

<script src="<?=$url_app_root;?>js/timepicker.js"></script>

<script src="<?=$url_app_root;?>js/bootstrap.min.js"></script>

<!-- ServerManager's js, with cache busting -->
<script src="<?=$url_app_root;?>js/manager.base.js?v=<?=time();?>"></script>
<script src="<?=$url_app_root;?>js/manager.gamesession.js?v=<?=time();?>"></script>
<script src="<?=$url_app_root;?>js/manager.gamesave.js?v=<?=time();?>"></script>
<script src="<?=$url_app_root;?>js/manager.gameconfig.js?v=<?=time();?>"></script>
<script src="<?=$url_app_root;?>js/manager.settings.js?v=<?=time();?>"></script>

</head>
<body>
  <?php //require_once('css/style.php'); ?>
  <?php
  require_once ServerManager::getInstance()->GetServerManagerRoot().'templates/navigation.php';
  ?>

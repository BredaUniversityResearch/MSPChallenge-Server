
<?php
$url_app_root = ServerManager::getInstance()->GetServerManagerFolder();
?>

<div id="header-wrapper" >
    <nav class="navbar">
      <a class="title" href="<?php echo $url_app_root;?>index.php" style="background-image: url('<?=$url_app_root?>images/logo.png');"> MSP Challenge Server Manager</a>
      <div id="header-menu-wrapper">
        <ul id="header-menu">
          
          
          <li id="main-menu-item">
            <div id="main-menu-icon"></div> </a>
            <ul id="main-menu">
              <li><a href="https://www.mspchallenge.info" title="www.mspchallenge.info">Main MSP Challenge website</a></li>
              <li><a href="https://community.mspchallenge.info/" title="community.mspchallenge.info">MSP Challenge Community Wiki</a></li>
              <li><a href="https://knowledge.mspchallenge.info/" title="knowledge.mspchallenge.info">MSP Knowledge Base</a></li>
              
            </ul>
          </li>
          <?php if($user->isLoggedIn()){ //anyone is logged in ?>
          <li id="user-menu-item">
            <div id="user-menu-icon"></div> </a>
            <ul id="user-menu">
              <li><a href="https://auth.mspchallenge.info/users/account.php">My user details</a></li>
              <li><a href="https://community.mspchallenge.info/wiki/Special:Preferences">Community Wiki preferences</a></li>
              <li><a href="<?=$url_app_root?>logout.php">Logout</a></li>
            </ul>
          </li>
          <?php } else { ?>
          <li id="user-menu-item">
            <div id="user-menu-icon"></div> </a>
            <ul id="user-menu">
              <li><a href="<?=$url_app_root?>login.php">Log in</a></li>
              <li><a href="https://auth.mspchallenge.info/users/forgot_password.php">Reset password</a></li>
              <li><a href="https://auth.mspchallenge.info/users/join.php">Create an account</a></li>
            </ul>
          </li>
          <?php } ?>
        </ul>
      </div>
    </nav>

</div>

<?php

    if(isset($_GET['err'])){
      err("<font color='red'>".$err."</font>");
    }

    if(isset($_GET['msg'])){
      err($msg);
    }
?>

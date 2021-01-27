
<div id="header-wrapper" >
    <nav class="navbar">

      <a class="title" href="<?php echo $url_app_root;?>index.php" style="background-image: url('<?=$url_app_root?>images/logo.png');"> MSP Challenge Server Manager</a>
      <div id="navbarsExample03">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item">Current address: &nbsp; 
            <?php 
            $servermanager = ServerManager::getInstance();
            echo $servermanager->GetTranslatedServerURL();
            ?>
          </li>
          <?php if($user->isLoggedIn()){ //anyone is logged in ?>
          <li class="nav-item"><a href="<?=$url_app_root?>"><span class="fa fa-fw fa-home"></span> Home</a></li>
          <li class="nav-item"><a href="<?=$url_app_root?>server_manager.php"><span class="fa fa-fw fa-wrench"></span> Settings</a> </li>

          <li class="dropdown nav-item">
            <a class="dropdown-toggle nav-links" href="" data-toggle="dropdown" aria-haspopup="true" role="button" aria-expanded="false"><span class="fa fa-fw fa-cogs"></span> </a>
            <div class="dropdown-menu">
              <a class="dropdown-item" href="https://auth.mspchallenge.info/users/account.php"><span class="fa fa-fw fa-user"></span> Account</a>
              <a class="dropdown-item" href="https://community.mspchallenge.info/"><span class="fa fa-fw fa-wrench"></span> Support</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?=$url_app_root?>logout.php"><span class="fa fa-fw fa-sign-out"></span> Logout</a>
            </div>
          </li>
          <?php } else { ?>
            <li class="nav-item"><a href="<?=$url_app_root?>login.php"><span class="fa fa-fw fa-sign-in"></span> Log In</a></li>
            <li class="nav-item"><a href="https://auth.mspchallenge.info/users/join.php"><span class="fa fa-fw fa-plus-square"></span> Register</a></li>

            <li class="dropdown nav-item">
              <a class="dropdown-toggle nav-links" href="" data-toggle="dropdown" aria-haspopup="true" role="button" aria-expanded="false"><span class="fa fa-fw fa-life-ring"></span> Help </a>
              <div class="dropdown-menu">
                <a class="dropdown-item" href="https://auth.mspchallenge.info/users/forgot_password.php"><span class="fa fa-fw fa-wrench"></span> Forgot Password</a>
                <a class="dropdown-item" href="https://community.mspchallenge.info/"><span class="fa fa-fw fa-wrench"></span> Support</a>
              </div>
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

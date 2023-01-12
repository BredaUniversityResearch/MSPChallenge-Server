<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong

use App\Domain\API\v1\Config;
use ServerManager\ServerManager;
use function ServerManager\err;

$url_app_root = ServerManager::getInstance()->GetServerManagerFolder();

$userLoggedIn = isset($user) && $user->isLoggedIn();
$authBaseUrl = Config::GetInstance()->getMSPAuthBaseURL();
$html =<<<"HTML"
<div id="header-wrapper" >

    <nav class="navbar">
      <a class="title" href="${url_app_root}index.php"> MSP Challenge Server Manager</a>
      <div id="header-menu-wrapper">
        <ul id="header-menu">
          <li id="main-menu-item">
            mspchallenge.info
            <ul id="main-menu">
              <li><a href="https://www.mspchallenge.info" title="www.mspchallenge.info">Home</a></li>
              <li><a href="https://community.mspchallenge.info/" title="community.mspchallenge.info">Community</a></li>
              <li><a href="https://knowledge.mspchallenge.info/" title="knowledge.mspchallenge.info">Knowledge Base</a></li>
            </ul>
          </li>
          <li id="user-menu-item">
            <a href="https://auth2.mspchallenge.info">Account</a>
          </li>
        </ul>
      </div>
    </nav>

</div>
HTML;

echo $html;

if (isset($_GET['err'])) {
    err("<font color='red'>".$_GET['err']."</font>");
}

if (isset($_GET['msg'])) {
    err($_GET['msg']);
}


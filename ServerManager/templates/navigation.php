<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong

use App\Domain\API\v1\Config;
use ServerManager\ServerManager;
use function ServerManager\err;

$url_app_root = ServerManager::getInstance()->getAbsolutePathBase();

$userLoggedIn = isset($user) && $user->isLoggedIn();
$authBaseUrl = Config::GetInstance()->getMSPAuthBaseURL();
$html =<<<"HTML"
<div id="header-wrapper" >

    <nav class="navbar">
      <div id="logo">
        <img src="/ServerManager/images/MSP_Challenge_Icon-037c7c.png">
      </div>
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
            <a href="{$authBaseUrl}">Account</a>
          </li>
          <li id="user-menu-item">
            <a href="/ServerManager/logout_php">Log out</a>
          </li>
          <li id="user-menu-item">
            <a href="/manager">Try the new Server Manager!</a>
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


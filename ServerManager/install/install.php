<?php
/*
based on...

UserSpice 5
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use App\Domain\API\v1\Config;

use ServerManager\Base;
use ServerManager\DB;
use ServerManager\ServerManager;
use ServerManager\Session;
use ServerManager\User;
use Symfony\Component\Uid\Uuid;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\Setting;

ob_start();
?>

<?php
require __DIR__ . '/../init.php';
$user = new User();
$servermanager = ServerManager::getInstance();

//install the database tables and content

if (!$user->isLoggedIn()) {
    die();
}
if (!$servermanager->freshinstall()) {
    die();
}

$db = DB::getInstance();
require_once __DIR__ . '/../templates/header.php';

if ($servermanager->install($user)) {
    $em = SymfonyToLegacyHelper::getInstance()->getEntityManager();
    $params = ["server" =>
        [
            "uuid" => $em->getRepository(Setting::class)->findOneBy(['name' => 'server_uuid'])
                ->getValue(),
            "serverID" => $em->getRepository(Setting::class)->findOneBy(['name' => 'server_id'])
                ->getValue(),
            "password" => $em->getRepository(Setting::class)->findOneBy(['name' => 'server_password'])
                ->getValue(),
            "serverName" => $em->getRepository(Setting::class)->findOneBy(['name' => 'server_name'])
                ->getValue()
        ],
        "user" => "/api/users/".$user->data()->account_id
    ];
    Base::postCallAuthoriser("server_users", $params);
    // @codingStandardsIgnoreStart
    //echo 'settings sent <br/>';
    ?>
      <div id="page-wrapper">
        <div class="container">
            <div id="infobox"></div>
          <h1>New installation</h1>
          <p>This is a new installation of the Server Manager application.</p>
          <p>You, <strong><?=$user->data()->username;?></strong>, are now the primary user of this Server Manager. This means that you can not only use this application,
           but you can also add other users to it through Settings - User Access. You don't have to do this
           right now of course, or at all for that matter.</p>
          <p>You can go ahead and <a href="<?php echo ServerManager::getInstance()->getAbsolutePathBase();?>manager.php">set up your first MSP Challenge server</a>.</p>
          <p>We also recommend you enter your computer's proper IP address or full-qualified domain name under Settings.</p>
        </div>
      </div>
    <?php
    // @codingStandardsIgnoreEnd
    // @codingStandardsIgnoreStart
}
?>
<!-- footers -->
<?php
// the final html footer copyright row + the external js calls
require_once ServerManager::getInstance()->getServerManagerRoot().'templates/footer.php';

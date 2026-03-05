<?php
namespace AISC\Core;

use AISC\Admin\Menu;
use AISC\Admin\Dashboard;
use AISC\Admin\SettingsPage;
use AISC\Rest\RestController;

if (!defined('ABSPATH')) exit;

class Plugin {

    public function init(): void {
        // Admin UI
        if (is_admin()) {
            (new Menu())->register();
            (new Dashboard())->register();
            (new SettingsPage())->register();
        }

        // REST
        (new RestController())->register();
    }
}
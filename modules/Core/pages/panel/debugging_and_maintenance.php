<?php
/**
 * Staff panel debugging + maintenance page
 *
 * @author Samerton
 * @license MIT
 * @version 2.2.0
 *
 * @var Cache $cache
 * @var FakeSmarty $smarty
 * @var Language $language
 * @var Navigation $cc_nav
 * @var Navigation $navigation
 * @var Navigation $staffcp_nav
 * @var Pages $pages
 * @var TemplateBase $template
 * @var User $user
 * @var Widgets $widgets
 */

if (!$user->handlePanelPageLoad('admincp.core.debugging')) {
    require_once ROOT_PATH . '/403.php';
    die();
}

const PAGE = 'panel';
const PARENT_PAGE = 'core_configuration';
const PANEL_PAGE = 'debugging_and_maintenance';
$page_title = $language->get('admin', 'debugging_and_maintenance');
require_once ROOT_PATH . '/core/templates/backend_init.php';

// Input
if (Input::exists()) {
    $errors = [];

    if (Token::check()) {
        // Valid token
        // Validate message
        $validation = Validate::check($_POST, [
            'message' => [
                Validate::MAX => 1024
            ]
        ])->message($language->get('admin', 'maintenance_message_max_1024'));

        if ($validation->passed()) {
            // Update database
            // Is debug mode enabled or not?
            Settings::set('error_reporting', (isset($_POST['enable_debugging']) && $_POST['enable_debugging']) ? '1' : '0');

            // Maintenance mode
            Settings::set('maintenance', (isset($_POST['enable_maintenance']) && $_POST['enable_maintenance']) ? '1' : '0');
            Settings::set('maintenance_message', (isset($_POST['message']) && !empty($_POST['message'])) ? $_POST['message'] : 'Maintenance mode is enabled.');

            // Log::getInstance()->log(Log::Action('admin/core/maintenance/update'));

            // Page load timer
            Settings::set('page_loading', isset($_POST['enable_page_load_timer']) && $_POST['enable_page_load_timer'] == 1 ? '1' : '0');

            // Reload to update debugging
            Session::flash('debugging_success', $language->get('admin', 'debugging_settings_updated_successfully'));
            Redirect::to(URL::build('/panel/core/debugging_and_maintenance'));
        }

        $errors = $validation->errors();
    } else {
        // Invalid token
        $errors[] = $language->get('general', 'invalid_token');
    }
}

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

if (Session::exists('debugging_success')) {
    $template->getEngine()->addVariables([
        'SUCCESS' => Session::flash('debugging_success'),
        'SUCCESS_TITLE' => $language->get('general', 'success'),
    ]);
}

if (isset($errors) && count($errors)) {
    $template->getEngine()->addVariables([
        'ERRORS' => $errors,
        'ERRORS_TITLE' => $language->get('general', 'error'),
    ]);
}

if ($user->hasPermission('admincp.errors')) {
    $template->getEngine()->addVariables([
        'ERROR_LOGS' => $language->get('admin', 'error_logs'),
        'ERROR_LOGS_LINK' => URL::build('/panel/core/errors'),
    ]);
}

$template->getEngine()->addVariables([
    'PARENT_PAGE' => PARENT_PAGE,
    'DASHBOARD' => $language->get('admin', 'dashboard'),
    'CONFIGURATION' => $language->get('admin', 'configuration'),
    'DEBUGGING_AND_MAINTENANCE' => $language->get('admin', 'debugging_and_maintenance'),
    'PAGE' => PANEL_PAGE,
    'TOKEN' => Token::get(),
    'SUBMIT' => $language->get('general', 'submit'),
    'ENABLE_DEBUG_MODE' => $language->get('admin', 'enable_debug_mode'),
    'ENABLE_DEBUG_MODE_VALUE' => (defined('DEBUGGING') ? DEBUGGING : 0),
    'ENABLE_MAINTENANCE_MODE' => $language->get('admin', 'enable_maintenance_mode'),
    'ENABLE_MAINTENANCE_MODE_VALUE' => Settings::get('maintenance'),
    'ENABLE_PAGE_LOAD_TIMER' => $language->get('admin', 'enable_page_load_timer'),
    'ENABLE_PAGE_LOAD_TIMER_VALUE' => Settings::get('page_loading'),
    'MAINTENANCE_MODE_MESSAGE' => $language->get('admin', 'maintenance_mode_message'),
    'MAINTENANCE_MODE_MESSAGE_VALUE' => Output::getPurified(Settings::get('maintenance_message')),
    'CANCEL' => $language->get('general', 'cancel'),
    'DEBUG_LINK' => $language->get('admin', 'debug_link'),
    'DEBUG_LINK_INFO' => $language->get('admin', 'debug_link_info'),
    'DEBUG_LINK_URL' => URL::build('/queries/debug_link'),
    'COPIED' => $language->get('general', 'copied'),
]);

$template->onPageLoad();

require ROOT_PATH . '/core/templates/panel_navbar.php';

// Display template
$template->displayTemplate('core/debugging_and_maintenance');

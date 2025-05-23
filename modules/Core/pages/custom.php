<?php
/**
 * Custom page
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
 * @var string $route
 * @var TemplateBase $template
 * @var User $user
 * @var Widgets $widgets
 */

// Check whenever route is homepage or not
$page_route = empty($route) ? Settings::get('home_type') : rtrim($route, '/');

// Get page info from URL
$custom_page = DB::getInstance()->get('custom_pages', ['url', $page_route]);
if (!$custom_page->count()) {
    require ROOT_PATH . '/404.php';
    die();
}

$custom_page = $custom_page->first();

// Check permissions
$perms = DB::getInstance()->get('custom_pages_permissions', ['page_id', $custom_page->id])->results();
if ($user->isLoggedIn()) {
    $groups = $user->getAllGroupIds();
    foreach ($groups as $group) {
        foreach ($perms as $perm) {
            if ($perm->group_id == $group) {
                if ($perm->view == 1) {
                    $can_view = 1;
                    break 2;
                }

                break;
            }
        }
    }
} else {
    foreach ($perms as $perm) {
        if ($perm->group_id == 0) {
            if ($perm->view == 1) {
                $can_view = 1;
            }

            break;
        }
    }
}

if (!isset($can_view)) {
    require ROOT_PATH . '/403.php';
    die();
}

if ($custom_page->redirect) {
    header('X-Robots-Tag: noindex, nofollow');
    Redirect::to($custom_page->link);
}

// Always define page name
define('PAGE', $custom_page->id);
define('CUSTOM_PAGE', $custom_page->title);
$page_title = Output::getClean($custom_page->title);
require_once ROOT_PATH . '/core/templates/frontend_init.php';

$template->assets()->include([
    DARK_MODE
        ? AssetTree::PRISM_DARK
        : AssetTree::PRISM_LIGHT,
    AssetTree::TINYMCE_SPOILER,
]);

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

$content = EventHandler::executeEvent('renderCustomPage', [
    'content' => $custom_page->content,
    'skip_purify' => $custom_page->all_html ?? false
])['content'];

$template->getEngine()->addVariables([
    'WIDGETS_LEFT' => $widgets->getWidgets('left'),
    'WIDGETS_RIGHT' => $widgets->getWidgets('right'),
    'CONTENT' => $content,
]);

$template->onPageLoad();

require ROOT_PATH . '/core/templates/navbar.php';
require ROOT_PATH . '/core/templates/footer.php';

if ($custom_page->basic) {
    $template->displayTemplate('custom_basic');
} else {
    $template->displayTemplate('custom');
}

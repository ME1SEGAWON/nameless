<?php
/**
 * Staff panel forum settings page
 *
 * @author Partydragen
 * @license MIT
 * @version 2.2.0
 *
 * @var Cache $cache
 * @var FakeSmarty $smarty
 * @var Language $forum_language
 * @var Language $language
 * @var Navigation $cc_nav
 * @var Navigation $navigation
 * @var Navigation $staffcp_nav
 * @var Pages $pages
 * @var TemplateBase $template
 * @var User $user
 * @var Widgets $widgets
 */

// Can the user view the panel?
if (!$user->handlePanelPageLoad('admincp.forums')) {
    require_once ROOT_PATH . '/403.php';
    die();
}

const PAGE = 'panel';
const PARENT_PAGE = 'forum';
const PANEL_PAGE = 'forum_settings';
$page_title = $forum_language->get('forum', 'forums');
require_once ROOT_PATH . '/core/templates/backend_init.php';

if (Input::exists()) {
    if (Token::check()) {
        $validation = Validate::check($_POST, [
            'news_items' => [
                Validate::REQUIRED => true,
                Validate::NUMERIC => true,
                Validate::AT_LEAST => 0,
                Validate::AT_MOST => 20,
            ],
            'spam_timer' => [
                Validate::REQUIRED => true,
                Validate::NUMERIC => true,
                Validate::AT_LEAST => 1,
            ],
        ])->messages([
            'news_items' => [
                Validate::REQUIRED => $forum_language->get('forum', 'news_items_required'),
                Validate::NUMERIC => $forum_language->get('forum', 'news_items_numeric'),
                Validate::AT_LEAST => static fn($meta) => $forum_language->get('forum', 'news_items_min', $meta),
                Validate::AT_MOST => static fn($meta) => $forum_language->get('forum', 'news_items_max', $meta),
            ],
            'spam_timer' => [
                Validate::REQUIRED => $forum_language->get('forum', 'spam_timer_required'),
                Validate::NUMERIC => $forum_language->get('forum', 'spam_timer_numeric'),
                Validate::AT_LEAST => static fn($meta) => $forum_language->get('forum', 'spam_timer_min', $meta),
            ],
        ]);

        if ($validation->passed()) {
            // Update link location
            if (isset($_POST['link_location'])) {
                switch ($_POST['link_location']) {
                    case 1:
                    case 2:
                    case 3:
                    case 4:
                        $location = $_POST['link_location'];
                        break;
                    default:
                        $location = 1;
                }
            } else {
                $location = 1;
            }

            // Update Link location cache
            $cache->setCache('nav_location');
            $cache->store('forum_location', $location);

            Settings::set('forum_reactions', (isset($_POST['use_reactions']) && $_POST['use_reactions'] == 'on') ? '1' : 0);
            Settings::set('news_items_front_page', $_POST['news_items'], 'forum');
            Settings::set('spam_timer', $_POST['spam_timer'], 'forum');
            Settings::set('banned_terms', $_POST['banned_terms'], 'forum');

            Session::flash('admin_forums_settings', $forum_language->get('forum', 'settings_updated_successfully'));
        } else {
            Session::put('admin_forums_settings_errors', $validation->errors());
        }
    } else {
        // Invalid token
        Session::put('admin_forums_settings_errors', [$language->get('general', 'invalid_token')]);
    }
    Redirect::to(URL::build('/panel/forums/settings'));
}

// Retrieve Link Location from cache
$cache->setCache('nav_location');
$link_location = $cache->retrieve('forum_location');

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

if (Session::exists('admin_forums_settings')) {
    $success = Session::flash('admin_forums_settings');
}

if (Session::exists('admin_forums_settings_errors')) {
    $errors = Session::flash('admin_forums_settings_errors');
}

if (isset($success)) {
    $template->getEngine()->addVariables([
        'SUCCESS' => $success,
        'SUCCESS_TITLE' => $language->get('general', 'success'),
    ]);
}

if (isset($errors) && count($errors)) {
    $template->getEngine()->addVariables([
        'ERRORS' => $errors,
        'ERRORS_TITLE' => $language->get('general', 'error'),
    ]);
}

$template->getEngine()->addVariables([
    'PARENT_PAGE' => PARENT_PAGE,
    'DASHBOARD' => $language->get('admin', 'dashboard'),
    'FORUM' => $forum_language->get('forum', 'forum'),
    'SETTINGS' => $language->get('admin', 'settings'),
    'LINK_LOCATION' => $language->get('admin', 'page_link_location'),
    'LINK_LOCATION_VALUE' => $link_location,
    'LINK_NAVBAR' => $language->get('admin', 'page_link_navbar'),
    'LINK_MORE' => $language->get('admin', 'page_link_more'),
    'LINK_FOOTER' => $language->get('admin', 'page_link_footer'),
    'LINK_NONE' => $language->get('admin', 'page_link_none'),
    'USE_REACTIONS' => $forum_language->get('forum', 'use_reactions'),
    'USE_REACTIONS_VALUE' => Settings::get('forum_reactions') === '1',
    'NEWS_ITEMS_ON_FRONT_PAGE' => $forum_language->get('forum', 'news_items_front_page_limit'),
    'NEWS_ITEMS_ON_FRONT_PAGE_VALUE' => Settings::get('news_items_front_page', 5, 'forum'),
    'SPAM_TIMER' => $forum_language->get('forum', 'spam_timer'),
    'SPAM_TIMER_INFO' => $forum_language->get('forum', 'spam_timer_info'),
    'SPAM_TIMER_VALUE' => Settings::get('spam_timer', 30, 'forum'),
    'BANNED_TERMS' => $forum_language->get('forum', 'banned_terms'),
    'BANNED_TERMS_INFO' => $forum_language->get('forum', 'banned_terms_info'),
    'BANNED_TERMS_VALUE' => Output::getClean(Settings::get('banned_terms', '', 'forum')),
    'INFO' => $language->get('general', 'info'),
    'PAGE' => PANEL_PAGE,
    'TOKEN' => Token::get(),
    'SUBMIT' => $language->get('general', 'submit'),
]);

$template->onPageLoad();

require ROOT_PATH . '/core/templates/panel_navbar.php';

// Display template
$template->displayTemplate('forum/forums_settings');

<?php
/**
 * Staff panel general settings page
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

if (!$user->handlePanelPageLoad('admincp.core.general')) {
    require_once ROOT_PATH . '/403.php';
    die();
}

const PAGE = 'panel';
const PARENT_PAGE = 'core_configuration';
const PANEL_PAGE = 'general_settings';
$page_title = $language->get('admin', 'general_settings');
require_once ROOT_PATH . '/core/templates/backend_init.php';

// Handle input
if (isset($_GET['do'])) {
    if ($_GET['do'] == 'installLanguage') {
        // Install new language
        $languages = glob('modules' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . '*');
        foreach ($languages as $item) {
            // cursed
            $short_code = explode('.', explode(DIRECTORY_SEPARATOR, $item)[3])[0];

            // Is it already in the database?
            $exists = DB::getInstance()->get('languages', ['short_code', $short_code])->results();
            if (!count($exists)) {
                // No, add it now
                DB::getInstance()->insert('languages', [
                    // If they try and install a language which is not "official", default to the short code for the name
                    'name' => Language::LANGUAGES[$short_code]['name'] ?? $short_code,
                    'short_code' => $short_code
                ]);
            }
        }

        Session::flash('general_language', $language->get('admin', 'installed_languages'));
    } else {
        if ($_GET['do'] == 'updateLanguages') {
            $active_language = DB::getInstance()->get('languages', ['is_default', true])->results();
            if (count($active_language)) {
                DB::getInstance()->query('UPDATE nl2_users SET language_id = ?', [$active_language[0]->id]);
                $language = new Language('core', $active_language[0]->short_code);
            }

            Session::flash('general_language', $language->get('admin', 'updated_user_languages'));
        }
    }

    Redirect::to(URL::build('/panel/core/general_settings'));
}

// Deal with input
if (Input::exists()) {
    if (Token::check()) {
        // Validate input
        $validation = Validate::check($_POST, [
            'sitename' => [
                Validate::REQUIRED => true,
                Validate::MIN => 2,
                Validate::MAX => 64
            ],
            'contact_email' => [
                Validate::REQUIRED => true,
                Validate::MIN => 3,
                Validate::MAX => 255
            ]
        ])->messages([
            'sitename' => $language->get('admin', 'missing_sitename'),
            'contact_email' => $language->get('admin', 'missing_contact_address')
        ]);

        if ($validation->passed()) {
            // Update settings
            // Sitename
            Settings::set('sitename', Input::get('sitename'));

            // Email address
            Settings::set('incoming_email', Input::get('contact_email'));

            // Language
            // Get current default language
            DB::getInstance()->update('languages', ['is_default', true], [
                'is_default' => false,
            ]);

            $language_id = DB::getInstance()->get('languages', ['id', Input::get('language')])->results();
            $language_short_code = Output::getClean($language_id[0]->short_code);
            $language_id = $language_id[0]->id;

            DB::getInstance()->update('languages', $language_id, [
                'is_default' => true,
            ]);

            // Update cache
            $cache->setCache('languagecache');
            $cache->store('language', $language_short_code);

            // Timezone
            try {
                Settings::set('timezone', $_POST['timezone']);
            } catch (Exception $e) {
                $errors = [$e->getMessage()];
            }

            // Default Homepage
            Settings::set('home_type', $_POST['homepage']);

            // Private profile
            Settings::set('private_profile', $_POST['privateProfile'] ? '1' : '0');

            // Registration displaynames
            Settings::set('displaynames', (isset($_POST['displaynames']) && $_POST['displaynames'] == 'true') ? '1' : '0');

            // Emoji style
            Settings::set('emoji_style', $_POST['emoji_style']);

            // Friendly URLs
            $friendly = Input::get('friendlyURL') == 'true';

            // Force HTTPS?
            if (Input::get('forceHTTPS') == 'true') {
                $https = true;
            } else {
                $https = false;
            }

            // Force WWW?
            if (Input::get('forceWWW') == 'true') {
                $www = true;
            } else {
                $www = false;
            }

            // Update config
            if (is_writable(ROOT_PATH . '/' . implode(DIRECTORY_SEPARATOR, ['core', 'config.php']))) {
                Config::setMultiple([
                    'core.friendly' => $friendly,
                    'core.force_https' => $https,
                    'core.force_www' => $www
                ]);
            } else {
                $errors = [$language->get('admin', 'config_not_writable')];
            }

            // Login method
            Settings::set('login_method', $_POST['login_method']);

            // Auto language
            Settings::set('auto_language_detection', $_POST['auto_language'] === 'true' ? 1 : 0);

            // StaffCP two-factor auth
            Settings::set('require_staffcp_tfa', $_POST['require_staffcp_tfa'] === 'true' ? 1 : 0);

            Log::getInstance()->log(Log::Action('admin/core/general'));

            Session::flash('general_language', $language->get('admin', 'settings_updated_successfully'));

            // Redirect in case URL type has changed
            if (!isset($errors)) {
                if ($friendly === true) {
                    $redirect = URL::build('/panel/core/general_settings', '', 'friendly');
                } else {
                    $redirect = URL::build('/panel/core/general_settings', '', 'non-friendly');
                }
                Redirect::to($redirect);
            }
        } else {
            $errors = $validation->errors();
        }
    } else {
        // Invalid token
        $errors = [$language->get('general', 'invalid_token')];
    }
}

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

if (Session::exists('general_language')) {
    $success = Session::flash('general_language');
}

if (isset($success)) {
    $template->getEngine()->addVariables([
        'SUCCESS_TITLE' => $language->get('general', 'success'),
        'SUCCESS' => $success,
    ]);
}

if (isset($errors) && count($errors)) {
    $template->getEngine()->addVariables([
        'ERRORS_TITLE' => $language->get('general', 'error'),
        'ERRORS' => $errors,
    ]);
}

// Get form values
$contact_email = Output::getClean(Settings::get('incoming_email'));

$languages = DB::getInstance()->get('languages', ['id', '<>', 0])->results();
$count = count($languages);
for ($i = 0; $i < $count; $i++) {
    $language_path = implode(DIRECTORY_SEPARATOR, [ROOT_PATH, 'modules', 'Core', 'language', $languages[$i]->short_code . '.json']);
    if (!file_exists($language_path)) {
        unset($languages[$i]);
    }
}

$timezone = Settings::get('timezone');
$private_profile = Settings::get('private_profile');
$displaynames = Settings::get('displaynames');
$method = Settings::get('login_method');

$homepage_pages = [[
    'value' => 'news',
    'name' => $language->get('admin', 'homepage_news'),
    'module' => 'Core'
], [
    'value' => 'portal',
    'name' => $language->get('admin', 'portal'),
    'module' => 'Core'
], [
    'value' => 'custom',
    'name' => $language->get('admin', 'custom_content'),
    'module' => 'Core'
]];

foreach ($pages->returnPages() as $key => $page) {
    if (str_contains($key, '/panel/') || str_contains($key, '/queries/') || str_contains($key, '/user/')) {
        continue;
    }

    $homepage_pages[] = [
        'value' => Output::getPurified($key),
        'name' => Output::getPurified($key),
        'module' => $page['module']
    ];
}

$template->getEngine()->addVariables([
    'PARENT_PAGE' => PARENT_PAGE,
    'DASHBOARD' => $language->get('admin', 'dashboard'),
    'CONFIGURATION' => $language->get('admin', 'configuration'),
    'GENERAL_SETTINGS' => $language->get('admin', 'general_settings'),
    'PAGE' => PANEL_PAGE,
    'TOKEN' => Token::get(),
    'SUBMIT' => $language->get('general', 'submit'),
    'SITE_NAME_LABEL' => $language->get('admin', 'sitename'),
    'CONTACT_EMAIL_ADDRESS' => $language->get('admin', 'contact_email_address'),
    'CONTACT_EMAIL_ADDRESS_VALUE' => $contact_email,
    'INFO' => $language->get('general', 'info'),
    'DEFAULT_LANGUAGE' => $language->get('admin', 'default_language'),
    'DEFAULT_LANGUAGE_HELP' => $language->get('admin', 'default_language_help', [
        'docLinkStart' => "<a href='https://docs.namelessmc.com/home#translations' target='_blank'>",
        'docLinkEnd' => '</a>'
    ]),
    'DEFAULT_LANGUAGE_VALUES' => $languages,
    'INSTALL_LANGUAGE_LINK' => URL::build('/panel/core/general_settings/', 'do=installLanguage'),
    'INSTALL_LANGUAGE' => $language->get('admin', 'install_language'),
    'UPDATE_USER_LANGUAGES_LINK' => URL::build('/panel/core/general_settings/', 'do=updateLanguages'),
    'UPDATE_USER_LANGUAGES' => $language->get('admin', 'update_user_languages'),
    'UPDATE_USER_LANGUAGES_INFO' => $language->get('admin', 'update_user_languages_warning'),
    'YES' => $language->get('general', 'yes'),
    'NO' => $language->get('general', 'no'),
    'ARE_YOU_SURE' => $language->get('general', 'are_you_sure'),
    'DEFAULT_TIMEZONE' => $language->get('admin', 'default_timezone'),
    'DEFAULT_TIMEZONE_LIST' => Util::listTimezones(),
    'DEFAULT_TIMEZONE_VALUE' => $timezone,
    'HOMEPAGE_TYPE' => $language->get('admin', 'default_homepage'),
    'HOMEPAGE_PAGES' => $homepage_pages,
    'HOMEPAGE_VALUE' => Settings::get('home_type'),
    'USE_FRIENDLY_URLS' => $language->get('admin', 'use_friendly_urls'),
    'USE_FRIENDLY_URLS_VALUE' => Config::get('core.friendly'),
    'USE_FRIENDLY_URLS_HELP' => $language->get('admin', 'use_friendly_urls_help', [
        'docLinkStart' => "<a href='https://docs.namelessmc.com/friendly-urls' target='_blank'>",
        'docLinkEnd' => '</a>'
    ]),
    'ENABLED' => $language->get('admin', 'enabled'),
    'DISABLED' => $language->get('admin', 'disabled'),
    'PRIVATE_PROFILES' => $language->get('admin', 'private_profiles'),
    'PRIVATE_PROFILES_VALUE' => $private_profile,
    'FORCE_HTTPS' => $language->get('admin', 'force_https'),
    'FORCE_HTTPS_VALUE' => (defined('FORCE_SSL')),
    'FORCE_HTTPS_HELP' => $language->get('admin', 'force_https_help'),
    'FORCE_WWW' => $language->get('admin', 'force_www'),
    'FORCE_WWW_VALUE' => (defined('FORCE_WWW')),
    'ENABLE_NICKNAMES' => $language->get('admin', 'enable_nicknames_on_registration'),
    'ENABLE_NICKNAMES_VALUE' => $displaynames,
    'LOGIN_METHOD' => $language->get('admin', 'login_method'),
    'LOGIN_METHOD_VALUE' => $method,
    'EMAIL' => $language->get('user', 'email'),
    'EMAIL_OR_USERNAME' => $language->get('user', 'email_or_username'),
    'USERNAME' => $language->get('user', 'username'),
    'EMOJI_STYLE' => $language->get('admin', 'emoji_style'),
    'EMOJI_STYLE_HELP' => $language->get('admin', 'emoji_style_help', [
        'nativeExample' => Text::renderEmojis('😀', 'native'),
        'twemojiExample' => Text::renderEmojis('😀', 'twemoji'),
        'joypixelsExample' => Text::renderEmojis('😀', 'joypixels'),
    ]),
    'EMOJI_STYLE_VALUE' => Settings::get('emoji_style', 'twemoji'),
    'NATIVE' => $language->get('admin', 'emoji_native'),
    'TWEMOJI' => $language->get('admin', 'emoji_twemoji'),
    'JOYPIXELS' => $language->get('admin', 'emoji_joypixels'),
    'AUTO_LANGUAGE_VALUE' => Settings::get('auto_language_detection'),
    'ENABLE_AUTO_LANGUAGE' => $language->get('admin', 'enable_auto_language'),
    'AUTO_LANGUAGE_HELP' => $language->get('admin', 'auto_language_help'),
    'REQUIRE_STAFFCP_TFA' => $language->get('admin', 'require_two_factor_for_staffcp'),
    'REQUIRE_STAFFCP_TFA_VALUE' => Settings::get('require_staffcp_tfa')
]);

$template->onPageLoad();

require ROOT_PATH . '/core/templates/panel_navbar.php';

// Display template
$template->displayTemplate('core/general_settings');

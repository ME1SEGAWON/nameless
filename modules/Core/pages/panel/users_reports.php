<?php
/**
 * Staff panel user reports page
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

if (!$user->handlePanelPageLoad('modcp.reports')) {
    require_once ROOT_PATH . '/403.php';
    die();
}

const PAGE = 'panel';
const PARENT_PAGE = 'users';
const PANEL_PAGE = 'reports';
$page_title = $language->get('moderator', 'reports');
require_once ROOT_PATH . '/core/templates/backend_init.php';

$timeago = new TimeAgo(TIMEZONE);

if (!isset($_GET['id'])) {
    // Get all reports
    $reports = [];

    if (!isset($_GET['view'])) {
        // Get open reports
        if (!isset($_GET['uid'])) {
            $report_query = DB::getInstance()->query('SELECT * FROM nl2_reports WHERE status = 0 ORDER BY date_updated DESC')->results();
            $url = URL::build('/panel/users/reports/');
            $change_view_link = URL::build('/panel/users/reports/', 'view=closed');
        } else {
            $report_query = DB::getInstance()->query('SELECT * FROM nl2_reports WHERE status = 0 AND reported_id = ? ORDER BY date_updated DESC', [(int)$_GET['uid']])->results();
            $url = URL::build('/panel/users/reports/', 'uid=' . urlencode((int) $_GET['uid']) . '&');
            $change_view_link = URL::build('/panel/users/reports/', 'view=closed&uid=' . urlencode((int) $_GET['uid']));
        }

        $template->getEngine()->addVariables([
            'CHANGE_VIEW' => $language->get('moderator', 'view_closed'),
            'CHANGE_VIEW_LINK' => $change_view_link,
        ]);
    } else {
        // Get closed reports
        if (!isset($_GET['uid'])) {
            $report_query = DB::getInstance()->query('SELECT * FROM nl2_reports WHERE status = 1 ORDER BY date_updated DESC')->results();
            $url = URL::build('/panel/users/reports/', 'view=closed&');
            $change_view_link = URL::build('/panel/users/reports');
        } else {
            $report_query = DB::getInstance()->query('SELECT * FROM nl2_reports WHERE status = 1 AND reported_id = ? ORDER BY date_updated DESC', [(int)$_GET['uid']])->results();
            $url = URL::build('/panel/users/reports/', 'view=closed&uid=' . urlencode((int) $_GET['uid']) . '&');
            $change_view_link = URL::build('/panel/users/reports/', 'uid=' . urlencode((int) $_GET['uid']));
        }

        $template->getEngine()->addVariables([
            'CHANGE_VIEW' => $language->get('moderator', 'view_open'),
            'CHANGE_VIEW_LINK' => $change_view_link,
        ]);
    }

    if (count($report_query)) {
        // Get page
        if (isset($_GET['p'])) {
            if (!is_numeric($_GET['p'])) {
                Redirect::to($url);
            }

            if ($_GET['p'] == 1) {
                // Avoid bug in pagination class
                Redirect::to($url);
            }
            $p = $_GET['p'];
        } else {
            $p = 1;
        }

        $paginator = new Paginator(
            $template_pagination ?? null,
            $template_pagination_left ?? null,
            $template_pagination_right ?? null
        );
        $results = $paginator->getLimited($report_query, 10, $p, count($report_query));
        $pagination = $paginator->generate(7, $url);

        foreach ($results->data as $report) {
            // Get comments count
            $comments = DB::getInstance()->get('reports_comments', ['report_id', $report->id])->results();
            $comments = count($comments);

            if (!$report->reported_id) {
                $integration = Integrations::getInstance()->getIntegration('Minecraft');
                if ($integration != null) {
                    $reported_user = new IntegrationUser($integration, $report->reported_uuid, 'identifier');
                    if ($reported_user->exists()) {
                        $reported_user = $reported_user->getUser();

                        $reported_user_profile = URL::build('/panel/user/' . urlencode($reported_user->data()->id . '-' . $reported_user->data()->username));
                        $reported_user_style = $reported_user->getGroupStyle();
                        $reported_user_avatar = $reported_user->getAvatar();
                    } else {
                        $reported_user_profile = '#';
                        $reported_user_style = '';
                        $reported_user_avatar = AvatarSource::getAvatarFromUUID(Output::getClean($report->reported_uuid));
                    }
                } else {
                    $reported_user_profile = '#';
                    $reported_user_style = '';
                    $reported_user_avatar = AvatarSource::getAvatarFromUUID(Output::getClean($report->reported_uuid));
                }

                $reported_user_name = Output::getClean($report->reported_mcname);
            } else {
                $reported_user = new User($report->reported_id);

                $reported_user_name = $reported_user->getDisplayname();
                $reported_user_profile = URL::build('/panel/user/' . urlencode($report->reported_id . '-' . $reported_user->data()->username));
                $reported_user_style = $reported_user->getGroupStyle();
                $reported_user_avatar = $reported_user->getAvatar();
            }

            $updated_by_user = new User($report->updated_by);

            $server = null;
            if ($report->server_id) {
                $server = Output::getClean(DB::getInstance()->get('mc_servers', ['id', $report->server_id])->first()->name);
            }

            $reports[] = [
                'id' => $report->id,
                'type' => $report->type,
                'server' => $server,
                'user_reported' => $reported_user_name,
                'user_profile' => $reported_user_profile,
                'user_reported_style' => $reported_user_style,
                'user_reported_avatar' => $reported_user_avatar,
                'reported_at' => ($report->reported ? $timeago->inWords($report->reported, $language) : $timeago->inWords($report->date_reported, $language)),
                'reported_at_full' => ($report->reported ? date(DATE_FORMAT, $report->reported) : date(DATE_FORMAT, strtotime($report->date_reported))),
                'link' => URL::build('/panel/users/reports/', 'id=' . urlencode($report->id)),
                'updated_by' => $updated_by_user->getDisplayname(),
                'updated_by_profile' => URL::build('/panel/user/' . urlencode($report->updated_by . '-' . $updated_by_user->data()->username)),
                'updated_by_style' => $updated_by_user->getGroupStyle(),
                'updated_by_avatar' => $updated_by_user->getAvatar(),
                'updated_at' => ($report->updated ? $timeago->inWords($report->updated, $language) : $timeago->inWords($report->date_updated, $language)),
                'updated_at_full' => ($report->updated ? date(DATE_FORMAT, $report->updated) : date(DATE_FORMAT, strtotime($report->date_updated))),
                'comments' => $comments,
            ];
        }

        $template->getEngine()->addVariable('PAGINATION', $pagination);
    } else {
        if (!isset($_GET['view'])) {
            $template->getEngine()->addVariable('NO_REPORTS', $language->get('moderator', 'no_open_reports'));
        } else {
            $template->getEngine()->addVariable('NO_REPORTS', $language->get('moderator', 'no_closed_reports'));
        }
    }

    if (isset($_GET['uid'])) {
        $template->getEngine()->addVariable('VIEWING_USER', Output::getClean($user->idToNickname((int)$_GET['uid'])));
    }

    // Template variables
    $template->getEngine()->addVariables([
        'ALL_REPORTS' => $reports,
        'VIEW' => $language->get('general', 'view'),
        'USER_REPORTED' => $language->get('moderator', 'user_reported'),
        'COMMENTS' => $language->get('moderator', 'comments'),
        'UPDATED_BY' => $language->get('moderator', 'updated_by'),
        'ACTIONS' => $language->get('moderator', 'actions'),
        'ORIGIN' => $language->get('general', 'report_origin'),
        'WEBSITE' => $language->get('general', 'origin_website'),
        'API' => $language->get('general', 'origin_api'),
    ]);

    $template_file = 'core/users_reports';
} else {
    // Get report by ID
    if (!isset($_GET['action'])) {
        $report = DB::getInstance()->get('reports', ['id', $_GET['id']])->results();
        if (!count($report)) {
            Redirect::to(URL::build('/panel/users/reports'));
        }
        $report = $report[0];

        // Check input
        if (Input::exists()) {
            $errors = [];

            // Check token
            if (Token::check()) {
                // Valid token
                $validation = Validate::check($_POST, [
                    'content' => [
                        Validate::REQUIRED => true,
                        Validate::MIN => 1,
                        Validate::MAX => 10000
                    ]
                ])->message($language->get('moderator', 'report_comment_invalid'));

                if ($validation->passed()) {
                    DB::getInstance()->insert('reports_comments', [
                        'report_id' => $report->id,
                        'commenter_id' => $user->data()->id,
                        'comment_date' => date('Y-m-d H:i:s'),
                        'comment_content' => Input::get('content'),
                        'date' => date('U')
                    ]);

                    DB::getInstance()->update('reports', $report->id, [
                        'updated_by' => $user->data()->id,
                        'updated' => date('U'),
                        'date_updated' => date('Y-m-d H:i:s')
                    ]);

                    $success = $language->get('moderator', 'comment_created');
                } else {
                    // Display error
                    $errors = $validation->errors();
                }
            } else {
                // Invalid token
                $errors[] = $language->get('general', 'invalid_token');
            }
        }

        // Get comments
        $comments = DB::getInstance()->get('reports_comments', ['report_id', $report->id])->results();
        $template_comments = [];
        foreach ($comments as $comment) {
            $comment_user = new User($comment->commenter_id);

            $template_comments[] = [
                'username' => $comment_user->getDisplayname(),
                'profile' => URL::build('/panel/user/' . urlencode($comment->commenter_id . '-' . $comment_user->data()->username)),
                'style' => $comment_user->getGroupStyle(),
                'avatar' => $comment_user->getAvatar(),
                'content' => Output::getPurified($comment->comment_content),
                'date' => ($comment->date ? date(DATE_FORMAT, $comment->date) : date(DATE_FORMAT, strtotime($comment->comment_date))),
                'date_friendly' => ($comment->date ? $timeago->inWords($comment->date, $language) : $timeago->inWords($comment->comment_date, $language))
            ];
        }

        if (!$report->reported_id) {
            $integration = Integrations::getInstance()->getIntegration('Minecraft');
            if ($integration != null) {
                $reported_user = new IntegrationUser($integration, $report->reported_uuid, 'identifier');
                if ($reported_user->exists()) {
                    $reported_user = $reported_user->getUser();

                    $reported_user_profile = URL::build('/panel/user/' . urlencode($reported_user->data()->id . '-' . $reported_user->data()->username));
                    $reported_user_style = $reported_user->getGroupStyle();
                    $reported_user_avatar = $reported_user->getAvatar();
                } else {
                    $reported_user_profile = '#';
                    $reported_user_style = '';
                    $reported_user_avatar = AvatarSource::getAvatarFromUUID(Output::getClean($report->reported_uuid));
                }
            } else {
                $reported_user_profile = '#';
                $reported_user_style = '';
                $reported_user_avatar = AvatarSource::getAvatarFromUUID(Output::getClean($report->reported_uuid));
            }

            $reported_user_name = Output::getClean($report->reported_mcname);
        } else {
            $reported_user = new User($report->reported_id);

            $reported_user_name = $reported_user->getDisplayname();
            $reported_user_profile = URL::build('/panel/user/' . urlencode($report->reported_id . '-' . $reported_user->data()->username));
            $reported_user_style = $reported_user->getGroupStyle();
            $reported_user_avatar = $reported_user->getAvatar();
        }

        $reporter_user = new User($report->reporter_id);

        if ($report->server_id) {
            $server = DB::getInstance()->get('mc_servers', ['id', $report->server_id])->first()->name;
            $smarty->assign('REPORT_SERVER', Output::getClean($server));
        }

        // Template variables
        $template->getEngine()->addVariables([
            'REPORTS_LINK' => URL::build('/panel/users/reports'),
            'VIEWING_REPORT' => $language->get('moderator', 'viewing_report'),
            'BACK' => $language->get('general', 'back'),
            'REPORTED_USER' => $reported_user_name,
            'REPORTED_USER_PROFILE' => $reported_user_profile,
            'REPORTED_USER_STYLE' => $reported_user_style,
            'REPORTED_USER_AVATAR' => $reported_user_avatar,
            'REPORT_DATE' => ($report->reported ? date(DATE_FORMAT, $report->reported) : date(DATE_FORMAT, strtotime($report->date_reported))),
            'REPORT_DATE_FRIENDLY' => ($report->reported ? $timeago->inWords($report->reported, $language) : $timeago->inWords($report->date_reported, $language)),
            'CONTENT_LINK' => $report->link,
            'VIEW_CONTENT' => $language->get('moderator', 'view_content'),
            'REPORT_CONTENT' => Output::getPurified($report->report_reason),
            'REPORTER_USER' => $reporter_user->getDisplayname(),
            'REPORTER_USER_PROFILE' => URL::build('/panel/user/' . urlencode($report->reporter_id . '-' . $reporter_user->data()->username)),
            'REPORTER_USER_STYLE' => $reporter_user->getGroupStyle(),
            'REPORTER_USER_AVATAR' => $reporter_user->getAvatar(),
            'COMMENTS' => $template_comments,
            'COMMENTS_TEXT' => $language->get('moderator', 'comments'),
            'NO_COMMENTS' => $language->get('moderator', 'no_comments'),
            'NEW_COMMENT' => $language->get('moderator', 'new_comment'),
            'TYPE' => $report->type,
            'ORIGIN' => $language->get('general', 'report_origin'),
            'WEBSITE' => $language->get('general', 'origin_website'),
            'API' => $language->get('general', 'origin_api'),
        ]);

        // Close/reopen link
        if ($report->status == 0) {
            $template->getEngine()->addVariables([
                'CLOSE_LINK' => URL::build('/panel/users/reports/', 'action=close&id=' . urlencode($report->id)),
                'CLOSE_REPORT' => $language->get('moderator', 'close_report'),
            ]);
        } else {
            $template->getEngine()->addVariables([
                'REOPEN_LINK' => URL::build('/panel/users/reports/', 'action=open&id=' . urlencode($report->id)),
                'REOPEN_REPORT' => $language->get('moderator', 'reopen_report'),
            ]);
        }

        $template_file = 'core/users_reports_view';
    } else {
        if ($_GET['action'] == 'close') {
            // Close report
            if (is_numeric($_GET['id'])) {
                // Get report
                $report = DB::getInstance()->get('reports', ['id', $_GET['id']]);
                if ($report->count()) {
                    $report = $report->first();

                    if (!Token::check()) {
                        Session::flash('report_error', $language->get('general', 'invalid_token'));
                        die();
                    }

                    DB::getInstance()->update('reports', $report->id, [
                        'status' => 1,
                        'date_updated' => date('Y-m-d H:i:s'),
                        'updated' => date('U'),
                        'updated_by' => $user->data()->id
                    ]);

                    DB::getInstance()->insert('reports_comments', [
                        'report_id' => $report->id,
                        'commenter_id' => $user->data()->id,
                        'comment_date' => date('Y-m-d H:i:s'),
                        'date' => date('U'),
                        'comment_content' => $language->get('moderator', 'x_closed_report', ['user' => $user->getDisplayname()])
                    ]);
                }

                Session::flash('report_success', $language->get('moderator', 'report_closed'));
                Redirect::to(URL::build('/panel/users/reports/', 'id=' . urlencode($report->id)));
            }

            Redirect::to(URL::build('/panel/users/reports'));
        }

        if ($_GET['action'] == 'open') {
            // Reopen report
            if (is_numeric($_GET['id'])) {
                // Get report
                $report = DB::getInstance()->get('reports', ['id', $_GET['id']]);
                if ($report->count()) {
                    $report = $report->first();

                    if (!Token::check()) {
                        Session::flash('report_error', $language->get('general', 'invalid_token'));
                        die();
                    }

                    DB::getInstance()->update('reports', $report->id, [
                        'status' => false,
                        'date_updated' => date('Y-m-d H:i:s'),
                        'updated' => date('U'),
                        'updated_by' => $user->data()->id
                    ]);

                    DB::getInstance()->insert('reports_comments', [
                        'report_id' => $report->id,
                        'commenter_id' => $user->data()->id,
                        'comment_date' => date('Y-m-d H:i:s'),
                        'date' => date('U'),
                        'comment_content' => $language->get('moderator', 'x_reopened_report', ['user' => $user->getDisplayname()])
                    ]);
                }

                Session::flash('report_success', $language->get('moderator', 'report_reopened'));
                Redirect::to(URL::build('/panel/users/reports/', 'id=' . urlencode($report->id)));
            }

            Redirect::to(URL::build('/panel/users/reports'));
        }

        Redirect::to(URL::build('/panel/users/reports'));
    }
}

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

if (Session::exists('report_success')) {
    $success = Session::flash('report_success');
}

if (Session::exists('report_error')) {
    $errors = [Session::flash('report_error')];
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
    'USER_MANAGEMENT' => $language->get('admin', 'user_management'),
    'REPORTS' => $language->get('moderator', 'reports'),
    'PAGE' => PANEL_PAGE,
    'TOKEN' => Token::get(),
    'SUBMIT' => $language->get('general', 'submit'),
]);

$template->onPageLoad();

require ROOT_PATH . '/core/templates/panel_navbar.php';

// Display template
$template->displayTemplate($template_file);

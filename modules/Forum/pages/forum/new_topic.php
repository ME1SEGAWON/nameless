<?php
/**
 * Forum new topic page
 *
 * @author Samerton
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

// Always define page name
const PAGE = 'forum';
$page_title = $forum_language->get('forum', 'new_topic');
require_once ROOT_PATH . '/core/templates/frontend_init.php';

// User must be logged in to proceed
if (!$user->isLoggedIn()) {
    Redirect::to(URL::build('/forum'));
}

$forum = new Forum();

if (!isset($_GET['fid']) || !is_numeric($_GET['fid'])) {
    Redirect::to(URL::build('/forum/error/', 'error=not_exist'));
}

$fid = (int)$_GET['fid'];

// Get user group ID
$user_groups = $user->getAllGroupIds();

// Does the forum exist, and can the user view it?
$list = $forum->forumExist($fid, $user_groups);
if (!$list) {
    Redirect::to(URL::build('/forum/error/', 'error=not_exist'));
}

// Can the user post a topic in this forum?
$can_reply = $forum->canPostTopic($fid, $user_groups);
if (!$can_reply) {
    Redirect::to(URL::build('/forum/view/' . urlencode($fid)));
}

$current_forum = DB::getInstance()->query('SELECT * FROM nl2_forums WHERE id = ?', [$fid])->first();
$forum_title = Output::getClean($current_forum->forum_title);

// Topic labels
$template->getEngine()->addVariable('LABELS_TEXT', $forum_language->get('forum', 'label'));
$labels = [];

$default_labels = $current_forum->default_labels ? explode(',', $current_forum->default_labels) : [];
$selected_labels = ((isset($_POST['topic_label']) && is_array($_POST['topic_label'])) ? Input::get('topic_label') : $default_labels);

$forum_labels = DB::getInstance()->get('forums_topic_labels', ['id', '<>', 0])->results();
if (count($forum_labels)) {
    foreach ($forum_labels as $label) {
        $forum_ids = explode(',', $label->fids);

        if (in_array($fid, $forum_ids)) {
            // Check permissions
            $lgroups = explode(',', $label->gids);

            $hasperm = false;
            foreach ($user_groups as $group_id) {
                if (in_array($group_id, $lgroups)) {
                    $hasperm = true;
                    break;
                }
            }

            if (!$hasperm) {
                continue;
            }

            // Get label HTML
            $label_html = DB::getInstance()->get('forums_labels', ['id', $label->label])->results();
            if (!count($label_html)) {
                continue;
            }

            $label_html = str_replace('{x}', Output::getClean($label->name), Output::getPurified($label_html[0]->html));

            $labels[] = [
                'id' => $label->id,
                'html' => $label_html,
                'checked' => in_array($label->id, $selected_labels)
            ];
        }
    }
}

// Deal with any inputted data
if (Input::exists()) {
    if (Token::check()) {
        // Check post limits
        $spamTimer = Settings::get('spam_timer', 30, 'forum');
        $lastPost = DB::getInstance()->query(
            'SELECT `created` FROM nl2_posts WHERE post_creator = ? ORDER BY `created` DESC LIMIT 1',
            [$user->data()->id]
        );

        if ($lastPost->count()) {
            if ($lastPost->first()->created > strtotime("-$spamTimer seconds")) {
                $spamCheck = true;
            }
        }

        if (!isset($spamCheck)) {
            // Spam check passed
            $validate = Validate::check($_POST, [
                'title' => [
                    Validate::REQUIRED => true,
                    Validate::MIN => 2,
                    Validate::MAX => 64
                ],
                'content' => [
                    Validate::REQUIRED => true,
                    Validate::MIN => 2,
                    Validate::MAX => 50000,
                    Validate::NOT_CONTAIN => Forum::getBannedTerms(),
                ]
            ])->messages([
                'title' => [
                    Validate::REQUIRED => $forum_language->get('forum', 'title_required'),
                    Validate::MIN => $forum_language->get('forum', 'title_min_2'),
                    Validate::MAX => $forum_language->get('forum', 'title_max_64')
                ],
                'content' => [
                    Validate::REQUIRED => $forum_language->get('forum', 'content_required'),
                    Validate::MIN => $forum_language->get('forum', 'content_min_2'),
                    Validate::MAX => $forum_language->get('forum', 'content_max_50000'),
                    Validate::NOT_CONTAIN => $forum_language->get('forum', 'content_contains_banned_term'),
                ]
            ]);

            if ($validate->passed()) {
                $post_labels = [];

                if (isset($_POST['topic_label']) && !empty($_POST['topic_label']) && is_array($_POST['topic_label'])) {
                    foreach ($_POST['topic_label'] as $topic_label) {
                        $label = DB::getInstance()->get('forums_topic_labels', ['id', $topic_label])->results();
                        if (count($label)) {
                            $lgroups = explode(',', $label[0]->gids);

                            $hasperm = false;
                            foreach ($user_groups as $group_id) {
                                if (in_array($group_id, $lgroups)) {
                                    $hasperm = true;
                                    break;
                                }
                            }

                            if ($hasperm) {
                                $post_labels[] = $label[0]->id;
                            }
                        }
                    }
                } else {
                    if (count($default_labels)) {
                        $post_labels = $default_labels;
                    }
                }

                DB::getInstance()->insert('topics', [
                    'forum_id' => $fid,
                    'topic_title' => Input::get('title'),
                    'topic_creator' => $user->data()->id,
                    'topic_last_user' => $user->data()->id,
                    'topic_date' => date('U'),
                    'topic_reply_date' => date('U'),
                    'labels' => implode(',', $post_labels)
                ]);
                $topic_id = DB::getInstance()->lastId();

                $content = Input::get('content');

                DB::getInstance()->insert('posts', [
                    'forum_id' => $fid,
                    'topic_id' => $topic_id,
                    'post_creator' => $user->data()->id,
                    'post_content' => $content,
                    'post_date' => date('Y-m-d H:i:s'),
                    'created' => date('U')
                ]);

                // Get last post ID
                $last_post_id = DB::getInstance()->lastId();
                $content = EventHandler::executeEvent('preTopicCreate', [
                    'alert_full' => ['path' => ROOT_PATH . '/modules/Forum/language', 'file' => 'forum', 'term' => 'user_tag_info', 'replace' => '{{author}}', 'replace_with' => $user->getDisplayname()],
                    'alert_short' => ['path' => ROOT_PATH . '/modules/Forum/language', 'file' => 'forum', 'term' => 'user_tag'],
                    'alert_url' => URL::build('/forum/topic/' . urlencode($topic_id), 'pid=' . urlencode($last_post_id)),
                    'content' => $content,
                    'user' => $user,
                ])['content'];

                DB::getInstance()->update('posts', $last_post_id, [
                    'post_content' => $content
                ]);

                DB::getInstance()->update('forums', $fid, [
                    'last_post_date' => date('U'),
                    'last_user_posted' => $user->data()->id,
                    'last_topic_posted' => $topic_id
                ]);

                Log::getInstance()->log(Log::Action('forums/topic/create'), Output::getClean(Input::get('title')));

                // Execute hooks and pass $available_hooks
                $available_hooks = DB::getInstance()->get('forums', ['id', $fid])->first();
                $available_hooks = json_decode($available_hooks->hooks) ?? [];
                EventHandler::executeEvent(new TopicCreatedEvent(
                    $user,
                    $forum_title,
                    Input::get('title'),
                    Input::get('content'),
                    $topic_id,
                    $available_hooks,
                ));

                Session::flash('success_post', $forum_language->get('forum', 'post_successful'));

                Redirect::to(URL::build('/forum/topic/' . urlencode($topic_id) . '-' . $forum->titleToURL(Input::get('title'))));
            } else {
                $error = $validate->errors();
            }
        } else {
            $error = [$forum_language->get('forum', 'spam_wait', ['count' => ($lastPost->first()->created - strtotime("-$spamTimer seconds"))])];
        }
    } else {
        $error = [$language->get('general', 'invalid_token')];
    }
}

// Generate a token
$token = Token::get();

// Generate content for template
if (isset($error)) {
    $template->getEngine()->addVariable('ERROR', $error);
}

$creating_topic_in = $forum_language->get('forum', 'creating_topic_in_x', ['forum' => $forum_title]);
$template->getEngine()->addVariable('CREATING_TOPIC_IN', $creating_topic_in);

// Get info about forum
$forum_query = DB::getInstance()->get('forums', ['id', $fid])->results();
$forum_query = $forum_query[0];

// Placeholder?
if ($forum_query->topic_placeholder) {
    $placeholder = Output::getPurified($forum_query->topic_placeholder);
}

// Template variables
$template->getEngine()->addVariables([
    'LABELS' => $labels,
    'TOPIC_TITLE' => $forum_language->get('forum', 'topic_title'),
    'TOPIC_VALUE' => ((isset($_POST['title']) && $_POST['title']) ? Output::getClean(Input::get('title')) : ''),
    'LABEL' => $forum_language->get('forum', 'label'),
    'SUBMIT' => $language->get('general', 'submit'),
    'CANCEL' => $language->get('general', 'cancel'),
    'CLOSE' => $language->get('general', 'close'),
    'CONFIRM_CANCEL' => $language->get('general', 'confirm_cancel'),
    'YES' => $language->get('general', 'yes'),
    'NO' => $language->get('general', 'no'),
    'TOKEN' => '<input type="hidden" name="token" value="' . $token . '">',
    'FORUM_LINK' => URL::build('/forum'),
    'CONTENT_LABEL' => $language->get('general', 'content'),
    'FORUM_TITLE' => Output::getClean($forum_title),
    'FORUM_DESCRIPTION' => Output::getPurified($forum_query->forum_description),
    'NEWS_FORUM' => $forum_query->news
]);

$content = $_POST['content'] ?? $forum_query->topic_placeholder ?? null;
if ($content) {
    // Purify post content
    $content = EventHandler::executeEvent('renderPostEdit', ['content' => $content])['content'];
}

$template->assets()->include([
    AssetTree::TINYMCE,
]);

$template->addJSScript(Input::createTinyEditor($language, 'reply', $content, true));

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

$template->onPageLoad();

require ROOT_PATH . '/core/templates/navbar.php';
require ROOT_PATH . '/core/templates/footer.php';

// Display template
$template->displayTemplate('forum/new_topic');

<?php
/*
 *  Made by Samerton
 *  https://github.com/NamelessMC/Nameless/
 *  NamelessMC version 2.2.0
 *
 *  Licence: MIT
 *
 *  Recent posts dashboard collection item
 */

class RecentPostsItem extends CollectionItemBase {

    private TemplateEngine $_engine;
    private Language $_language;
    private int $_posts;

    public function __construct(TemplateEngine $engine, Language $language, Cache $cache, int $posts) {
        $cache->setCache('dashboard_stats_collection');
        if ($cache->isCached('recent_posts')) {
            $from_cache = $cache->retrieve('recent_posts');
            $order = $from_cache['order'] ?? 4;

            $enabled = $from_cache['enabled'] ?? 1;
        } else {
            $order = 4;
            $enabled = 1;
        }

        parent::__construct($order, $enabled);

        $this->_engine = $engine;
        $this->_language = $language;
        $this->_posts = $posts;
    }

    public function getContent(): string {
        $this->_engine->addVariables([
            'TITLE' => $this->_language->get('forum', 'recent_posts'),
            'VALUE' => $this->_posts
        ]);

        return $this->_engine->fetch('collections/dashboard_stats/recent_posts');
    }
}

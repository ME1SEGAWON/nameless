<?php
/*
 *  Made by Samerton
 *  https://github.com/NamelessMC/Nameless/
 *  NamelessMC version 2.2.0
 *
 *  Licence: MIT
 *
 *  Total users dashboard collection item
 */

class TotalUsersItem extends CollectionItemBase {

    private TemplateEngine $_engine;
    private Language $_language;

    public function __construct(TemplateEngine $engine, Language $language, Cache $cache) {
        $cache->setCache('dashboard_stats_collection');
        if ($cache->isCached('total_users')) {
            $from_cache = $cache->retrieve('total_users');
            $order = $from_cache['order'] ?? 1;

            $enabled = $from_cache['enabled'] ?? 1;
        } else {
            $order = 1;
            $enabled = 1;
        }

        parent::__construct($order, $enabled);

        $this->_engine = $engine;
        $this->_language = $language;
    }

    public function getContent(): string {
        // Get the number of total users
        $users_query = DB::getInstance()->query('SELECT COUNT(*) AS c FROM nl2_users')->first()->c;

        $this->_engine->addVariables([
            'TITLE' => $this->_language->get('admin', 'total_users'),
            'VALUE' => $users_query
        ]);

        return $this->_engine->fetch('collections/dashboard_stats/total_users');
    }
}

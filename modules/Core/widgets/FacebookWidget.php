<?php

/*
 *  Made by Samerton
 *  https://github.com/NamelessMC/Nameless/
 *  NamelessMC version 2.2.0
 *
 *  License: MIT
 *
 *  Facebook Widget
 */

class FacebookWidget extends WidgetBase {

    private string $_fb_url;

    public function __construct(TemplateEngine $engine, ?string $fb_url = '') {
        $this->_engine = $engine;

        // Set widget variables
        $this->_module = 'Core';
        $this->_name = 'Facebook';
        $this->_description = 'Display a feed from your Facebook page on your site. Make sure you have entered your Facebook URL in the StaffCP -> Core -> Social Media tab first!';

        $this->_fb_url = $fb_url;
    }

    public function initialise(): void {
        $this->_content = '
            <div id="fb-root"></div>
            <script>
                (function(d, s, id) {
                    var js, fjs = d.getElementsByTagName(s)[0];
                    if (d.getElementById(id)) return;
                    js = d.createElement(s); js.id = id;
                    js.src = "//connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v2.10";
                    fjs.parentNode.insertBefore(js, fjs);
                }(document, \'script\', \'facebook-jssdk\'));
            </script>

            <div class="fb-page" data-href="' . Output::getClean($this->_fb_url) . '" data-tabs="timeline" data-small-header="false" data-adapt-container-width="true" data-hide-cover="false" data-show-facepile="true">
                <blockquote cite="' . Output::getClean($this->_fb_url) . '" class="fb-xfbml-parse-ignore">
                    <a href="' . Output::getClean($this->_fb_url) . '">' . Output::getClean(SITE_NAME) . '</a>
                </blockquote>
            </div>';
    }
}

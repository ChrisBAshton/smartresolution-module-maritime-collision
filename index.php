<?php

declare_module(array(
    'key'         => 'maritime_collision',
    'title'       => 'Maritime Collision',
    'description' => 'Custom dispute type: Maritime Collision'
), function () {
    // top-level routes
    top_level_route('/maritime-collision-about', 'MaritimeCollision->about');
    top_level_route('/maritime-collision-search', 'MaritimeCollision->search');

    // dispute-level routes
    route('/maritime-collision', 'MaritimeCollision->route');
    route('/initiate-maritime-collision', 'MaritimeCollision->markAsInitiated');

    // hooked events
    on('dispute_dashboard', 'MaritimeCollision->showMaritimeCollisionOption', 'high');
});

class MaritimeCollision {

    private $maritimeStarted = false;

    function __construct() {
        if (get_dispute_property('initiated') === 'true') {
            $this->maritimeStarted = true;
        }
    }

    public function showMaritimeCollisionOption() {
        dashboard_add_items(array(
            array(
                'title' => 'Maritime Collision',
                'image' => get_module_url() . '/assets/anchor.png',
                'href'  => get_dispute_url() . '/maritime-collision'
            ),
            array(
                'title' => 'Find similar disputes',
                'image' => get_module_url() . '/assets/magnifying_glass.png',
                'href'  => '/maritime-collision-search'
            ),
            array(
                'title' => 'About Maritime Collision',
                'image' => get_module_url() . '/assets/about.png',
                'href'  => '/maritime-collision-about'
            )
        ));
    }

    public function route() {
        if (!$this->maritimeStarted) {
            render(
                get_module_url() . '/views/get_started.html',
                array(
                    'disputeUrl' => get_dispute_url()
                )
            );
        }
        else {
            render(get_module_url() . '/views/started.html');
        }
    }

    public function about() {
        render_markdown(get_module_url() . '/views/about.md');
    }

    public function markAsInitiated() {
        set_dispute_property('initiated', 'true');
        header('Location: ' . get_dispute_url() . '/maritime-collision');
    }

    public function search() {
        render(
            get_module_url() . '/views/search.html',
            array(
                'time' => date('d/m/Y, h:i:s', time())
            )
        );
    }

}
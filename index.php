<?php

declare_module(array(
    'key'         => 'maritime_collision',
    'title'       => 'Maritime Collision',
    'description' => 'Custom dispute type: Maritime Collision'
), function () {

    top_level_route('/maritime-collision-about', 'MaritimeCollision->about');
    top_level_route('/maritime-collision-search', 'MaritimeCollision->search');
    route('/maritime-collision', 'MaritimeCollision->index');

    on('dispute_dashboard', 'MaritimeCollision->showMaritimeCollisionOption', 'high');

});

class MaritimeCollision {

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

    public function index() {
        render(
            get_module_url() . '/views/index.html'
        );
    }

    public function about() {
        render_markdown(get_module_url() . '/views/about.md');
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
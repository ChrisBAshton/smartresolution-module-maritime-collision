<?php

declare_module(array(
    'key'         => 'maritime_collision',
    'title'       => 'Maritime Collision',
    'description' => 'Custom dispute type: Maritime Collision'
), function () {

    // tables
    declare_tables(array(
        'setup' => array(
            'id_of_first_agent'  => 'INTEGER DEFAULT 0',
            'id_of_second_agent' => 'INTEGER DEFAULT 0'
        ),
        'answers' => array(
            'question' => 'INTEGER',
            'agent_id' => 'INTEGER',
            'answer'   => 'TEXT'
        )
    ));

    // top-level routes
    top_level_route('/about-maritime-collision', 'MaritimeCollision->about');
    top_level_route('/maritime-collision-search', 'MaritimeCollision->search');

    // dispute-level routes
    route('/maritime-collision', 'MaritimeCollision->maritimeCollisionIndex');
    route('/initiate-maritime-collision', 'MaritimeCollision->initiateMaritimeCollision');

    // hooked events
    on('dispute_dashboard', 'MaritimeCollision->showMaritimeCollisionOption', 'high');
});

class MaritimeCollision {

    private $firstAgent  = 0;
    private $secondAgent = 0;

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
                'href'  => '/about-maritime-collision'
            )
        ));
    }

    /**
     * checkMaritimeStatus() should be called before rendering Maritime Collision dispute pages.
     */
    private function checkMaritimeStatus() {
        if (!get('setup.id_of_first_agent')) {
            // record was not found - this is the first time the module has been loaded in the dispute.
            // We need to create a record. Setting one property is enough to create the entire row.
            // The id of the second agent will be set to default value
            set('setup.id_of_first_agent', '0');
        }
        else {
           $this->firstAgent  = (int) get('setup.id_of_first_agent');
           $this->secondAgent = (int) get('setup.id_of_second_agent');
        }
    }

    public function maritimeCollisionIndex() {
        $this->checkMaritimeStatus();

        if ($this->firstAgent > 0 && $this->secondAgent > 0) {
            render(get_module_url() . '/views/started.html');
        }
        else if (
            $this->firstAgent  === get_login_id() ||
            $this->secondAgent === get_login_id()
        ) {
            render(get_module_url() . '/views/waiting.html');
        }
        else {
            render(
                get_module_url() . '/views/get_started.html',
                array(
                    'disputeUrl' => get_dispute_url()
                )
            );
        }
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

    public function initiateMaritimeCollision() {
        $loggedInAgent = get_login_id();

        // if first agent has not been set yet
        if ((int) get('setup.id_of_first_agent') === 0) {
            set('setup.id_of_first_agent', $loggedInAgent);
        }
        else if (
            // if second agent has not been set yet, and we are not the first agent
            (int) get('setup.id_of_second_agent') === 0 &&
            $loggedInAgent !== (int) get('setup.id_of_first_agent')
        ) {
            set('setup.id_of_second_agent', $loggedInAgent);
        }

        header('Location: ' . get_dispute_url() . '/maritime-collision');
    }

}
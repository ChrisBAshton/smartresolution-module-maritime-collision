<?php

require 'MaritimeCollision.php';

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
    route('/answer-maritime-collision-question', 'MaritimeCollision->answerQuestion');

    // hooked events
    on('homescreen_dashboard', 'MaritimeCollision->showMaritimeCollisionMetaOptions', 'low');
    on('dispute_dashboard', 'MaritimeCollision->showMaritimeCollisionOption', 'high');
});
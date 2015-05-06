<?php

/**
 * Defines the methods which handle maritime-collision dispute HTTP methods.
 */
class MaritimeCollision {

    private $firstAgent   = 0;
    private $secondAgent  = 0;
    private $resultsReady = false;

    /**
     * Handles the HTTP GET '/maritime-collision', rendering either a disclaimer page, questions form
     * or results page depending on the history of the dispute.
     */
    public function maritimeCollisionIndex() {
        $this->checkMaritimeStatus();

        if ($this->bothAgentsInitiated()) {
            if ($this->resultsReady) {
                new ResultsCalculator();
            }
            else {
                $this->renderQuestions();
            }
        }
        else if (
            $this->firstAgent  === get_login_id() ||
            $this->secondAgent === get_login_id()
        ) {
            render(get_module_url() . '/views/waiting.html', array(
                'waitingFor' => 'the other agent to initiate the maritime collision module.'
            ));
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

    /**
     * Checks if both agents have agreed to the disclaimer and also checks if results are ready, setting
     * the MaritimeCollision class private attributes.
     * This function should be called before rendering Maritime Collision dispute pages.
     * It was tempting to put this in a constructor but that causes problems when calling dispute-independent
     * pages, such as "About Maritime Collision".
     */
    private function checkMaritimeStatus() {
        if (get('setup.id_of_first_agent') === false) {
            // record was not found - this is the first time the module has been loaded in the dispute.
            // We need to create a record. No need to manually specify any property values, since we've
            // defined some defaults in the table setup.
            createRow('setup');
        }

        // these don't necessarily correspond to Agent A or Agent B in the SmartResolution core software,
        // we just need to identify the two agents and keep them separate. The ordering doesn't matter.
        $this->firstAgent  = (int) get('setup.id_of_first_agent');
        $this->secondAgent = (int) get('setup.id_of_second_agent');
        $this->checkIfResultsAreReady();
    }

    /**
     * Denotes whether or not the module has calculated the results of the dispute or if it still needs to ask either agent any more questions.
     * @return boolean True if results are ready, false if not.
     */
    private function checkIfResultsAreReady() {
        $this->resultsReady = (
            $this->bothAgentsInitiated() &&
            $this->agentHasAnsweredAllQuestions($this->firstAgent) &&
            $this->agentHasAnsweredAllQuestions($this->secondAgent)
        );
    }

    /**
     * Denotes whether or not both agents have initiated the module (accepted the disclaimer).
     * @return boolean True if both agents have initiated, otherwise false.
     */
    private function bothAgentsInitiated() {
        return $this->firstAgent > 0 && $this->secondAgent > 0;
    }

    /**
     * Denotes whether or not the given agent has answered all of the relevant questions or if there are more questions to answer.
     * @param  int $agentID Login ID of the agent.
     * @return boolean      True if agent has answered all questions, otherwise false.
     */
    private function agentHasAnsweredAllQuestions($agentID) {
        return (0 === count($this->getQuestionsForAgent($agentID)));
    }

    /**
     * Returns an array of unanswered questions for the given agent. If the agent has already answered all questions, returns an empty array.
     * @param  int $agentID Login ID of the agent.
     * @return array        Array of questions, each element represented by an associative array.
     */
    private function getQuestionsForAgent($agentID) {
        $string       = file_get_contents(__DIR__ . '/questions.json');
        $allQuestions = json_decode($string, true);
        $questions    = array();

        foreach($allQuestions as $question) {
            $alreadyAnswered = get('answers.answer', array(
                'agent_id' => $agentID,
                'question' => $question['id']
            ));

            if (!$alreadyAnswered && $this->satisfiesPrerequisites($question, $agentID)) {
                array_push($questions, $question);
            }
        }

        return $questions;
    }

    /**
     * Denotes whether or not the given question has all of its prerequisites satisfied. For example, for question 2 to be displayed it may require that question 1 was answered "no". The prerequisites are defined in the JSON in the following format:
     *
     *  "prerequisites": [
     *      {
     *          "question_id":     "question_1",
     *          "required_answer": "no"
     *      }
     *  ]
     *
     *  The Agent ID is required because the function checks the answers of that agent to see if the prerequisites are satisfied.
     *
     * @param  array $question  Associative array representing the question.
     * @param  int   $agentID   ID of the agent we want to check has fulfilled the prerequisites.
     * @return boolean          True if satisfied, false if not.
     */
    private function satisfiesPrerequisites($question, $agentID) {
        if (isset($question['prerequisites'])) {
            foreach($question['prerequisites'] as $prereq) {
                $prereqAnswer = get('answers.answer', array(
                    'agent_id' => $agentID,
                    'question' => $prereq['question_id'],
                    'answer'   => $prereq['required_answer']
                ));
                if (!$prereqAnswer) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Renders the questions form.
     */
    public function renderQuestions() {
        $questions = $this->getQuestionsForAgent(get_login_id());

        if (count($questions) > 0) {
            render(
                get_module_url() . '/views/question.html',
                array(
                    'disputeUrl' => get_dispute_url(),
                    'questions'  => $questions
                )
            );
        }
        else {
            render(get_module_url() . '/views/waiting.html', array(
                'waitingFor' => 'the other agent to answer some questions.'
            ));
        }
    }

    /**
     * Adds the maritime collision option to the dispute dashboard.
     */
    public function showMaritimeCollisionOption() {
        dashboard_add_item(array(
            'title' => 'Maritime Collision',
            'image' => get_module_url() . '/assets/anchor.png',
            'href'  => get_dispute_url() . '/maritime-collision'
        ), true);
    }

    /**
     * Adds various maritime collision options to the homescreen dashboard, including an 'About' and a 'Search' page.
     */
    public function showMaritimeCollisionMetaOptions() {
        dashboard_add_items(array(
            array(
                'title' => 'Search historical maritime disputes',
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
     * POST method; the logged in agent accepts the disclaimer, initiating the module.
     */
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

        notify(
            other_agent_to($loggedInAgent),
            'The other agent has just agreed to the maritime collision disclaimer.',
            get_dispute_url() . '/maritime-collision'
        );

        header('Location: ' . get_dispute_url() . '/maritime-collision');
    }

    /**
     * POST method; submits an agent's answer or answers to the database. Then redirects to maritime collision landing page.
     * @param  F3 $f3 The global F3 object containing the POSTed answers.
     */
    public function answerQuestion($f3) {
        foreach($f3->get('POST') as $questionID => $value) {
            createRow('answers', array(
                'agent_id' => get_login_id(),
                'question' => $questionID,
                'answer'   => $value
            ));
        }

        notify(
            other_agent_to(get_login_id()),
            'The other agent has just answered a maritime collision question.',
            get_dispute_url() . '/maritime-collision'
        );

        header('Location: ' . get_dispute_url() . '/maritime-collision');
    }
}
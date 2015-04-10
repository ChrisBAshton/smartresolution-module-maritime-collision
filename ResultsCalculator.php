<?php

class ResultsCalculator {

    private $questions    = array();
    private $yourAnswers  = array();
    private $theirAnswers = array();

    function __construct() {
        $this->setUp();

        $results = array();
        foreach($this->questions as $question) {
            $result = array(
                'question_id'  => $question['id'],
                'question'     => $question['text'],
                'your_answer'  => $this->getAnswerToQuestion($question['id'], $this->yourAnswers),
                'their_answer' => $this->getAnswerToQuestion($question['id'], $this->theirAnswers)
            );
            $this->checkIfAnswersTally($result);

            if ($result['your_answer'] === 'N/A' && $result['their_answer'] === 'N/A') {
                // the question was not applicable for either agent, and therefore is not relevant
                // for the purposes of our results and should not be added to the array.
            }
            else {
                array_push($results, $result);
            }
        }

        render(get_module_url() . '/views/results.html', array(
            'results' => $results,
            'summary' => $this->tryToDeduceSummary($results)
        ));
    }

    private function setUp() {
        $this->questions = json_decode(file_get_contents(__DIR__ . '/questions.json'), true);
        $answers = get_multiple('answers.*');

        foreach($answers as $answer) {
            if (get_login_id() === (int) $answer['agent_id']) {
                array_push($this->yourAnswers, $answer);
            }
            else {
                array_push($this->theirAnswers, $answer);
            }
        }
    }

    private function getAnswerToQuestion($questionID, $answers) {
        foreach($answers as $answer) {
            if ($questionID === $answer['question']) {
                return $answer['answer'];
            }
        }
        return 'N/A';
    }

    private function checkIfAnswersTally(&$result) {
        if ($this->answersTally($result)) {
            $result['tally'] = 'YES';
        }
        else {
            $result['tally'] = 'NO';
        }
    }

    private function answersTally($result) {
        switch($result['question_id']) {
            case 'article_1':
                return $this->answerIsOneOf($result, array(
                    array('mine', 'other'),
                    array('both', 'both')
                ));
            case 'article_2':
                return $this->answerIsOneOf($result, array(
                    array('yes', 'yes'),
                    array('no', 'no')
                ));
            case 'article_3':
                return $this->answerIsOneOf($result, array(
                    array('mine', 'other'),
                    array('both', 'both')
                ));
            default:
                return false;
        }
    }

    private function answerIsOneOf($result, $possibleAnswers) {
        foreach($possibleAnswers as $answer) {
            if ($this->answerIs($result, $answer)) {
                return true;
            }
        }
        return false;
    }

    private function answerIs($result, $answer) {
        return (
            ($result['your_answer'] === $answer[0] && $result['their_answer'] === $answer[1]) ||
            ($result['your_answer'] === $answer[1] && $result['their_answer'] === $answer[0])
        );
    }

    private function tryToDeduceSummary($results) {
        $canDeduceSummary = true;

        foreach ($results as $result) {
            if ($result['tally'] === 'NO') {
                $canDeduceSummary = false;
            }
        }

        if ($canDeduceSummary) {
            $this->deduceSummary($results);
        }
        else {
            return "Unfortunately, your version of events conflicts with the other agent's version of events. In court, they would now examine your evidence to try and establish which version of events is correct. The SmartResolution Maritime Collision module is currently incapable of doing this.";
        }
    }

    private function deduceSummary($results) {
        // @TODO
    }

}
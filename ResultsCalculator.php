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
            'results'   => $results,
            'summaries' => $this->tryToDeduceSummary($results)
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

    private function answerIsOneOf($result, $possibleAnswers) {
        foreach($possibleAnswers as $answer) {
            if ($this->answerIs($result, $answer)) {
                return true;
            }
        }
        return false;
    }

    private function answersMatch($result) {
        return $result['your_answer'] === $result['their_answer'];
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
            $summary = $this->deduceSummary($results);
        }
        else {
            $summary = "Unfortunately, your version of events conflicts with the other agent's version of events. In court, they would now examine your evidence to try and establish which version of events is correct. The SmartResolution Maritime Collision module is currently incapable of doing this.";
        }

        return $summary;
    }

    private function getResultsForQuestion($questionID, $results) {
        foreach($results as $result) {
            if ($questionID === $result['question_id']) {
                return $result;
            }
        }
        throw new Exception('Could not find the question.');
    }

    // ************************************************************************ the functions below are tightly coupled with the specific questions in questions.json. Everything above this line is more generic and could be reused in other modules.

    private function answersTally($result) {
        switch($result['question_id']) {
            case 'article_11':
                return $this->answersMatch($result);
            case 'article_1':
                return $this->answerIsOneOf($result, array(
                    array('mine', 'other'),
                    array('both', 'both')
                ));
            case 'article_2':
                return $this->answersMatch($result);
            case 'article_3':
                return $this->answerIsOneOf($result, array(
                    array('mine', 'other'),
                    array('both', 'both')
                ));
            case 'article_7a':
                return $this->answersMatch($result);
            case 'article_7b':
                return $this->answersMatch($result);
            default:
                return false;
        }
    }

    private function deduceSummary($results) {
        $summary = array();
        $complexSummary = false;

        $conventionInvalid = $this->getResultsForQuestion('article_11', $results)['your_answer'];

        if ($conventionInvalid === 'yes') {
            $summary[] = "The Convention does not apply to ships of war or to Government ships appropriated exclusively to a public service, according to Article 11.";
        }
        else {
            $accidentalCollision = $this->getResultsForQuestion('article_2', $results)['your_answer'];

            if ($accidentalCollision === 'yes') {

                $vesselsDamaged = $this->getResultsForQuestion('article_1', $results)['your_answer'];

                switch($vesselsDamaged) {
                    case 'mine':
                        $summary[] = "Your client's vessel was damaged in an accidental collision. According to Article 2, they must suffer the damages themselves.";
                        break;
                    case 'other':
                        $summary[] = "The other party's vessel was damaged in an accidental collision. According to Article 2, your client must suffer the damages themselves.";
                        break;
                    case 'both':
                        $summary[] = "Both parties' vessels were damaged in an accidental collision. According to Article 2, both parties must suffer their own damages themselves, not the other party.";
                        break;
                }
            }
            else {

                $happenedLessThanTwoYearsAgo = $this->getResultsForQuestion('article_7a', $results)['your_answer'];

                if ($happenedLessThanTwoYearsAgo === 'no') {

                    $couldHaveArrestedDefendant = $this->getResultsForQuestion('article_7b', $results)['your_answer'];

                    if ($couldHaveArrestedDefendant === 'no') {
                        $summary[] = "According to Article 7, actions for the recovery of damages are barred after an interval of two years from the date of the casualty. Neither party can recover damages from the other.";
                    }
                    else {
                        $summary[] = "Although in most cases the recovery of damages are barred after an interval of two years from the date of the casualty, said periods can be extended in cases such as yours, where it has not been possible to arrest the defendant vessel in the territorial waters of the State in which the plaintiff has his domicile or principal place of business (Article 7).";
                        $complexSummary = true;
                    }
                }
                else {
                    $complexSummary = true;
                }
            }
        }

        if ($complexSummary) {
            $this->deduceDamages($results, $summary);
        }

        if (count($summary) === 0) {
            $summary[] = 'Could not deduce summary.';
        }

        return $summary;
    }

    private function deduceDamages($results, &$summary) {
        $vesselResponsible = $this->getResultsForQuestion('article_3', $results)['your_answer'];

        switch($vesselResponsible) {
            case 'mine':
                $summary[] = "According to Article 3, if the collision is caused by the fault of one of the vessels, liability to make good the damages attaches to the one which has committed the fault. Therefore, your client is responsible for the damages.";
                break;
            case 'other':
                $summary[] = "According to Article 3, if the collision is caused by the fault of one of the vessels, liability to make good the damages attaches to the one which has committed the fault. Therefore, the other client is responsible for the damages.";
                break;
            case 'both':
                $summary[] = "Article 4 states: the liability of each vessel is in proportion to the degree of the faults respectively committed. Provided that if, having regard to the circumstances, it is not possible to establish the degree of the respective faults, or if it appears that the faults are equal, the liability is apportioned equally.";

                $summary[] = "The damages caused, either to the vessels or to their cargoes or to the effects or other property of the crews, passengers, or other persons on board, are borne by the vessels in fault in the above proportions, and even to third parties a vessel is not liable for more than such proportion of such damages.";

                $summary[] = "In respect of damages caused by death or personal injuries, the vessels in fault are jointly as well as severally liable to third parties. It is left to the law of each country to determine, as regards such right to obtain contribution, the meaning and effect of any contract or provision of law which limits the liability of the owners of a vessel towards persons on board.";
                break;
        }
    }
}
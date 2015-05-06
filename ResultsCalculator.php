<?php

/**
 * Responsible for calculating the maritime collision law result according to the agents' answers.
 */
class ResultsCalculator {

    private $questions    = array();
    private $yourAnswers  = array(); // answers of the logged in agent
    private $theirAnswers = array(); // answers of the other agent in the dispute

    /**
     * Constructor: gathers the necessary data, calculates a result and renders the result in HTML.
     */
    function __construct() {
        $this->setUp();
        $answers   = $this->getAnswers();
        $summaries = $this->tryToDeduceSummary($answers);

        render(get_module_url() . '/views/results.html', array(
            'results'   => $answers,
            'summaries' => $summaries
        ));
    }

    /**
     * Retrieves the questions from the JSON and the agents' answers from the database, storing the results in private attributes to make available to the rest of the class.
     */
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

    /**
     * Retrieves an array containing questions, answers and whether or not the answers tally.
     * @return array
     */
    private function getAnswers() {
        $answers = array();
        foreach($this->questions as $question) {
            $answer = array(
                'question_id'  => $question['id'],
                'question'     => $question['text'],
                'your_answer'  => $this->getAnswerToQuestion($question['id'], $this->yourAnswers),
                'their_answer' => $this->getAnswerToQuestion($question['id'], $this->theirAnswers)
            );

            if ($this->answersTally($answer)) {
                $answer['tally'] = 'YES';
            }
            else {
                $answer['tally'] = 'NO';
            }

            if ($answer['your_answer'] === 'N/A' && $answer['their_answer'] === 'N/A') {
                // the question was not applicable for either agent, and therefore is not relevant
                // for the purposes of our results and should not be added to the array.
            }
            else {
                array_push($answers, $answer);
            }
        }
        return $answers;
    }

    /**
     * Retrieves from the collection of answers the answer which corresponds to the given question ID.
     * @param  string $questionID Unique ID of the question.
     * @param  array  $answers    Array of answers.
     * @return string             Answer to the question.
     */
    private function getAnswerToQuestion($questionID, $answers) {
        foreach($answers as $answer) {
            if ($questionID === $answer['question']) {
                return $answer['answer'];
            }
        }
        return 'N/A';
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
            $summary = array("Unfortunately, your version of events conflicts with the other agent's version of events. In court, they would now examine your evidence to try and establish which version of events is correct. The SmartResolution Maritime Collision module is currently incapable of doing this.");
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

    /**
     * Deduces whether or not the agents' answers tally. This isn't just a case of checking if the answers are the same: some answers need to be different for the overall picture to match, e.g. "Which vessel was damaged?" => ("mine" and "theirs") or ("theirs" and "mine") rather than ("mine" and "mine") or ("theirs" and "theirs")
     * @param  array $answers Array containing agents' answers to a certain question.
     * @return boolean        True if the answers tally, false if not.
     */
    private function answersTally($answers) {
        switch($answers['question_id']) {
            case 'article_11':
                return $this->answersMatch($answers);
            case 'article_1':
                return $this->answerIsOneOf($answers, array(
                    array('mine', 'other'),
                    array('both', 'both')
                ));
            case 'article_2':
                return $this->answersMatch($answers);
            case 'article_3':
                return $this->answerIsOneOf($answers, array(
                    array('mine', 'other'),
                    array('both', 'both')
                ));
            case 'article_7a':
                return $this->answersMatch($answers);
            case 'article_7b':
                return $this->answersMatch($answers);
            default:
                return false;
        }
    }

    /**
     * Are both agents' answers the same?
     * @param  array $answers Array containing both agents' answers.
     * @return boolean        True if answers match, otherwise false.
     */
    private function answersMatch($answers) {
        return $answers['your_answer'] === $answers['their_answer'];
    }

    /**
     * Checks if the agents' answers match one of the possible answer sets.
     * @param  array $answer          Agents' answers
     * @param  array $possibleAnswers Possible answer sets.
     * @return boolean                True if answer is one of the possible answer sets, otherwise false.
     */
    private function answerIsOneOf($answers, $possibleAnswers) {
        foreach($possibleAnswers as $possibleAnswer) {
            if ($this->answerIs($answers, $possibleAnswer)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compares agents' answers with an answer set, returning true if there is a match.
     * @param  array $result  Agents' answers.
     * @param  array $answer  Possible answer set.
     * @return boolean        True if the answers match the answer set, otherwise false.
     */
    private function answerIs($result, $answer) {
        return (
            ($result['your_answer'] === $answer[0] && $result['their_answer'] === $answer[1]) ||
            ($result['your_answer'] === $answer[1] && $result['their_answer'] === $answer[0])
        );
    }

    /**
     * Deduces the summary for the maritime collision, based on the agents' answers.
     * @param  array $results Array of questions, answers and whether or not they tally.
     * @return array<string>  Array of summaries. Some summaries are complicated and split into multiple elements in an array, which should be mapped to new paragraphs. Simple summaries will only be an array of one element.
     */
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

                    if ($couldHaveArrestedDefendant === 'yes') {
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

    /**
     * In cases where the collision was not accidental, the calculation of awarded damages is quite complicated. This is encapsulated in its own function, and directly alters the $summary parameter.
     * @param  array         $results    Array of questions, answers and whether or not they tally.
     * @param  array<string> &$summary  Array of summaries.
     */
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
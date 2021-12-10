<?php
declare(strict_types = 1);

/* Copyright (c) 1998-2019 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Survey\Execution;

use ILIAS\Survey\InternalRepoService;
use ILIAS\Survey\InternalDataService;
use ILIAS\Survey\InternalDomainService;
use ILIAS\Survey\Code\CodeManager;

/**
 * Survey Runs
 * @author killing@leifos.de
 */
class SessionManager
{
    /**
     * @var AnonymousSessionRepo
     */
    protected $session_repo;

    /**
     * @var int
     */
    protected $user_id;

    /**
     * @var \ilObjSurvey
     */
    protected $survey;

    /**
     * @var RunManager
     */
    protected $run_manager;

    /**
     * @var CodeManager
     */
    protected $code_manager;

    /**
     * Constructor
     */
    public function __construct(
        AnonymousSessionRepo $session_repo,
        \ilObjSurvey $survey,
        int $user_id,
        InternalDomainService $domain_service
    ) {
        $this->user_id = $user_id;
        $this->session_repo = $session_repo;
        $this->survey = $survey;
        $this->run_manager = $domain_service->execution()->run($survey, $user_id);
        $this->code_manager = $domain_service->code($survey, $user_id);
    }

    public function initSession(
        string $requested_code = ""
    ) {
        $user_id = $this->user_id;
        $survey = $this->survey;
        $session_repo = $this->session_repo;
        // validate incoming
        $code_input = false;
        // ->requested_code
        $anonymous_code = $requested_code;
        if ($anonymous_code != "") {
            $code_input = true;
            if (!$this->run_manager->isCodeOfCurrentUnfinishedRun($anonymous_code)) { // #15031 - valid as long survey is not finished
                $anonymous_code = "";
            } else {
                // #15860
                // a user has used a valid code, we store this in table
                // svy_anonymous
                $this->code_manager->bindUser($anonymous_code, $user_id);
                $session_repo->setCode($survey->getId(), $anonymous_code);
            }
        }
        // now we try to get the code from the session
        if (!$anonymous_code) {
            $anonymous_code = $session_repo->getCode($survey->getId());
            if ($anonymous_code) {
                $code_input = true;     // ??
            }
        }

        // if the survey is anonymous, codes are stored for logged
        // in users in svy_finished. Here we get this code, if already stored
        if ($survey->getAnonymize() && !$anonymous_code) {
            $anonymous_code = $survey->findCodeForUser($user_id);
        }

        // get existing runs for current user, might generate code
        $execution_status = $survey->getUserSurveyExecutionStatus($anonymous_code);
        if ($execution_status) {
            $anonymous_code = (string) $execution_status["code"];
            $execution_status = $execution_status["runs"];
        }

        // (final) check for proper anonymous code
        if (!$survey->isAccessibleWithoutCode() &&
//          !$is_appraisee &&
            $code_input && // #11346
            (!$anonymous_code || !$this->code_manager->exists($anonymous_code))) {
            $anonymous_code = "";
            throw new \ilWrongSurveyCodeException("Wrong Survey Code used.");
        }
        $this->session_repo->setCode($survey->getId(), $anonymous_code);
    }

    /**
     * Get current valid code
     */
    public function getCode() : string
    {
        return $this->session_repo->getCode($this->survey->getId());
    }
}

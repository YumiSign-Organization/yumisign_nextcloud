<?php

/**
 *
 * @copyright Copyright (c) 2024, RCDevs (info@rcdevs.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\YumiSignNxtC\Service;

use CURLFile;
use CurlHandle;
use CURLStringFile;
use DateInterval;
use DateTime;
use Exception;
use FileInfo;
use GuzzleHttp\Handler\CurlHandler;
use nusoap_client;

use OCA\YumiSignNxtC\AppInfo\Application as YumiSignApp;
use OCA\YumiSignNxtC\Commands\GetsFile;
use OCA\YumiSignNxtC\Db\SignSession;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Utility\LogYumiSign;
use OCA\YumiSignNxtC\Utility\Utility;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OC\Files\Filesystem;
use OC\Files\Mount\Manager;
use OC\Files\Node\Folder;
use OC\Files\Mount\MountPoint;
use OCP\IDateTimeFormatter;
use OCP\L10N\IFactory;

use function OCP\Log\logger;
use OCA\YumiSignNxtC\Service\Constante;
use OCA\YumiSignNxtC\Service\Cst;
use OCA\YumiSignNxtC\Service\Entity;
use OCP\Files\File;
use OCP\IUserSession;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class SignService
{
    // use GetsFile;

    const CNX_TIME_OUT = 3;

    // Settings
    private $apiKey;
    private $asyncTimeout;
    private $defaultDomain;
    private $description;
    private $enableDemoMode;
    private $proxyHost;
    private $proxyPassword;
    private $proxyPort;
    private $proxyUsername;
    private $serverUrl;
    private $signedFile;
    private $signScope;
    private $syncTimeout;
    // private $temporaryFolder;
    private $useProxy;
    private $userSettings;
    private $watermarkText;
    private int $workspaceId;
    private $workspaceName;
    /**
     * This priority status array is used to prevent network delays to override most recent status (workflow logic)
     * e.g. "signed" is after "approved" but if "Approved message" is received after "Signed message", the "approved" status will erase the final "signed" status
     */
    // private $statusPriority;

    public function __construct(
        private IAccountManager $accountManager,
        private IUserSession $userSession,
        private IConfig $config,
        private IL10N $l,
        private IRootFolder $rootFolder,
        private IUserManager $userManager,
        private SignSessionMapper $mapper,
        private IFactory $l10nFactory,
        private IConfig $systemConfig,
        private IL10N $l10n,
        private IDateTimeFormatter $formatter,
        private LogYumiSign $logYumiSign,
    ) {

        Utility::$l = $l;

        $this->apiKey           = $config->getAppValue(YumiSignApp::APP_ID, 'api_key');
        $this->asyncTimeout     = (int) $config->getAppValue(YumiSignApp::APP_ID, 'async_timeout'); // in days
        $this->defaultDomain    = $config->getAppValue(YumiSignApp::APP_ID, 'default_domain');
        $this->description      = $config->getAppValue(YumiSignApp::APP_ID, 'description');
        $this->enableDemoMode   = $config->getAppValue(YumiSignApp::APP_ID, 'enable_demo_mode');
        $this->proxyHost        = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_host')     : false);
        $this->proxyPassword    = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_password') : false);
        $this->proxyPort        = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_port')     : false);
        $this->proxyUsername    = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_username') : false);
        $this->serverUrl        = $config->getAppValue(YumiSignApp::APP_ID, 'server_url');
        $this->signedFile       = $config->getAppValue(YumiSignApp::APP_ID, 'signed_file');
        $this->signScope        = $config->getAppValue(YumiSignApp::APP_ID, 'sign_scope', 'qualified');
        $this->syncTimeout      = (int) $config->getAppValue(YumiSignApp::APP_ID, 'sync_timeout') * 60;
        // $this->temporaryFolder  = rtrim($config->getSystemValue('tempdirectory', rtrim(sys_get_temp_dir(), '/') . '/nextcloudtemp', '/')) . '/';
        $this->useProxy         = $config->getAppValue(YumiSignApp::APP_ID, 'use_proxy');
        $this->userSettings     = $config->getAppValue(YumiSignApp::APP_ID, 'user_settings');
        $this->watermarkText    = $config->getAppValue(YumiSignApp::APP_ID, 'watermark_text');
        $this->workspaceId      = intval($config->getAppValue(YumiSignApp::APP_ID, 'workspace_id'));
        $this->workspaceName    = $config->getAppValue(YumiSignApp::APP_ID, 'workspace_name');
    }

    private function addRecipients(string $serverUrl, string $apiKey, int $workspaceId, int $workflowId, string $recipientsJson): CurlResponse
    {
        $curlResponse = new CurlResponse();
        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows/{$workflowId}/roles", "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $recipientsJson);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
        }

        return $curlResponse;
    }

    private function addSteps(string $serverUrl, string $apiKey, int $workspaceId, int $workflowId, string $rolesJson): CurlResponse
    {
        $curlResponse = new CurlResponse();
        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows/{$workflowId}/steps", "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rolesJson);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
        }

        return $curlResponse;
    }

    private function addWorkflowPreferences(string $serverUrl, string $apiKey, int $workspaceId, int $workflowId, $preferencesJson): CurlResponse
    {
        $curlResponse = new CurlResponse();
        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows/{$workflowId}/preferences", "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $preferencesJson);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
        }

        return $curlResponse;
    }

    // Management of the YumiSign responses
    private function analyseYumiSignResponse($response, bool $subArray = false): array
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Analyse YumiSign response");
        $this->logYumiSign->debug((is_array($response) ? json_encode($response) : $response), __FUNCTION__);

        $return = [];

        // Input response may be json entity or array or entities
        if (!is_array($response)) {
            $response = json_decode($response, true);
        }

        // Check if this array response is simple or multidimensional
        switch (true) {
            case $subArray && !(array_key_exists(Constante::get(Cst::YMS_IDENTIFIER), $response) &&
                array_key_exists(Constante::get(Cst::YMS_RESULT), $response) &&
                array_key_exists(Constante::get(Cst::YMS_RESPONSE), $response) &&
                array_key_exists(Constante::get(Cst::ERROR), $response)
            ):
                // Throw an exception because the YumiSign response structure is abnormal
                throw new Exception($this->l->t("YumiSign response is invalid; process: \"{$processPrefix}\""), false);
                break;

            case !$subArray && !(array_key_exists(Constante::get(Cst::YMS_IDENTIFIER), $response) &&
                array_key_exists(Constante::get(Cst::YMS_RESULT), $response) &&
                array_key_exists(Constante::get(Cst::YMS_RESPONSE), $response) &&
                array_key_exists(Constante::get(Cst::ERROR), $response)
            ):
                // Multidimensional array
                foreach ($response as $key => $item) {
                    $return = $this->analyseYumiSignResponse($item, true);
                }
                break;

            case (array_key_exists(Constante::get(Cst::YMS_IDENTIFIER), $response) &&
                array_key_exists(Constante::get(Cst::YMS_RESULT), $response) &&
                array_key_exists(Constante::get(Cst::YMS_RESPONSE), $response) &&
                array_key_exists(Constante::get(Cst::ERROR), $response)):
                // Simple array
                // YumiSign returns error or bad result
                // WARNING: this is not an exception but a Business Logic valid response
                if ($response[Constante::get(Cst::ERROR)] == true) {
                    $return[Constante::get(Cst::CODE)]    = $response[Constante::get(Cst::ERROR)][Constante::get(Cst::CODE)];
                    $return['message'] = $response[Constante::get(Cst::ERROR)]['message'];
                    break;
                }
                // Just in case error intel not filled and result is wrong...
                if ($response[Constante::get(Cst::YMS_RESULT)] == false) {
                    $return[Constante::get(Cst::CODE)]    = false;
                    $return['message'] = "Error occurred during process";
                }
                // Here, the response is OK
                $return[Constante::get(Cst::CODE)] = true;
                $return['message'] = "OK";
                break;

            default:
                throw new Exception($this->l->t("Function not implemented: you should contact RCDevs at netadm@rcdevs.com; process: \"{$processPrefix}\""), false);
                break;
        }

        return $return;
    }

    public function asyncExternalMobileSignPrepare($path, $email, $userId, $appUrl, $signatureType, $fileId)
    {
        $resp = [];

        logger('yumisign_nextcloud')->debug('fileId : ' . json_encode($fileId));

        // $user = $this->userManager->get($userId);
        $user = $this->userSession->getUser();

        // Get current document full path (filesystem)
        $userFolder = $this->rootFolder->getUserFolder($user->getUID());

        $fileNode = $userFolder->getById($fileId)[0];
        $filePath = $fileNode->getInternalPath();

        logger('yumisign_nextcloud')->debug('$filePath : ' . $filePath);

        // Create a workflow
        $expiryDate = strtotime("+{$this->asyncTimeout} days");
        $curlWorkflow = $this->createWorkflow($this->serverUrl, $this->apiKey, $this->workspaceId, $userId, $fileNode, strtolower($signatureType), $expiryDate);
        $workflow = json_decode($curlWorkflow->body);

        // Initialize workflow preferences
        $uniquePreference = ["name" => "WorkflowNotificationCallbackUrlPreference", "value" => "{$appUrl}"];
        $preferences["preferences"][] = $uniquePreference;

        // Add workflow preferences
        $curlWorkflowPreferences = $this->addWorkflowPreferences($this->serverUrl, $this->apiKey, $this->workspaceId, $workflow->id, json_encode($preferences));

        // Retrieve the secret from YumiSign
        $secret = "";
        $bodyWorkflowPreferences = json_decode($curlWorkflowPreferences->body);
        foreach ($bodyWorkflowPreferences as $key => $subArray) {
            if ($subArray->name && strcasecmp($subArray->name, Constante::get(Cst::YMS_WF_SECRET))  === 0) {
                $secret = $subArray->value;
                break;
            }
        }
        if ($secret === "") throw new Exception($this->l->t("No secret parameter found"), 1);

        $webhook = json_decode($curlWorkflowPreferences->body);

        // Add workflow recipients roles
        // Read recipients field given by user and split data into several recipients addr
        $emails = explode(";", str_replace(",", ";", $email));

        // Define roles for the recipients
        foreach ($emails as $key => $uniqueEmail) {
            $role = [];
            $role[] = ["type" => "sign", "color" => "#" . $this->randomColorPart() . $this->randomColorPart() . $this->randomColorPart()];
            $uniqueRecipient = [
                "name" => $uniqueEmail,
                "email" => $uniqueEmail,
                "roles" => $role,
            ];

            $recipients["recipients"][] = $uniqueRecipient;
        }

        // Add recipients and retrieve roles
        $curlRecipients = $this->addRecipients($this->serverUrl, $this->apiKey, $this->workspaceId, $workflow->id, json_encode($recipients));

        $roles = json_decode($curlRecipients->body);

        // Retrieve Workflow values and insert data in DB
        $curlDebriefWorkflow = $this->debriefWorkflow($this->serverUrl, $this->apiKey, $this->workspaceId, $workflow->id);
        $debriefWorkflow = json_decode($curlDebriefWorkflow->body);

        // Save data in DB for each recipient
        foreach ($debriefWorkflow->recipients as $dbwRecipients) {
            // Insert row in DB
            $signSession = new SignSession();
            $signSession->setApplicantId($userId);
            $signSession->setFilePath($filePath);
            $signSession->setWorkspaceId($this->workspaceId);
            $signSession->setWorkflowId($workflow->id);
            $signSession->setWorkflowName($workflow->name);
            $signSession->setEnvelopeId($workflow->envelopeId);
            $signSession->setSecret($secret);
            $signSession->setExpiryDate($debriefWorkflow->expiryDate);
            $signSession->setStatus($debriefWorkflow->status);
            $signSession->setCreated($debriefWorkflow->createDate);
            $signSession->setChangeStatus($debriefWorkflow->createDate);
            $signSession->setRecipient($dbwRecipients->email);
            $signSession->setGlobalStatus($debriefWorkflow->status);
            $signSession->setMsgDate($debriefWorkflow->createDate);
            $signSession->setFileId($fileId);

            $this->mapper->insert($signSession);
        }

        // Initialize steps
        foreach ($roles as $key => $signRole) {
            $roleSteps[] = $signRole->roles[0]->id;
        }
        $input["steps"][] = ["sign" => true, "roles" => $roleSteps];

        // Add steps
        $curlSteps = $this->addSteps($this->serverUrl, $this->apiKey, $this->workspaceId, $workflow->id, json_encode($input));

        $steps = json_decode($curlSteps->body);

        // Add fields
        $curlSession = $this->getSession($this->serverUrl, $this->apiKey, $this->workspaceId, $workflow->id);

        $session = json_decode($curlSession->body);

        // Add code field
        if (!empty($session->session) && !empty($session->designerUrl)) {
            $resp = json_decode(json_encode($session), true);
            $resp[Constante::get(Cst::CODE)] = '2';
            $resp["error"] = "";
            $resp["message"] = "OpenDesigner";
            $resp["workspaceId"] = $this->workspaceId;
            $resp["workflowId"] = $workflow->id;
            $resp["envelopeId"] = $workflow->envelopeId;
        } else {
            $resp["code"] = 0;
            $resp["error"] = "Server error";
            $resp["message"] = "Unable to run Sign process with YumiSign";
        }

        return $resp;
    }

    public function asyncExternalMobileSignSubmit($workspaceId, $workflowId, $envelopeId)
    {
        $resp = [];

        // Start the workflow
        $curlWorkflow = $this->startWorkflow($this->serverUrl, $this->apiKey, $workspaceId, $workflowId);

        $workflow = json_decode($curlWorkflow->body);
        if (!isset($workflow[0]->error) || !is_null($workflow[0]->error)) {
            $resp["code"] = (isset($workflow[0]->error->code) ? $workflow[0]->error->code : "");
            $resp["message"] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : "");
            // The following are filled with "fake" data (this is just to be compliant with conditions in Vue file)
            $resp["session"] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : "OK");
            $resp["designerUrl"] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : false);
            $resp["workspaceId"] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : $workspaceId);
            $resp["workflowId"] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : $workflowId);
        }

        if ($workflow[0]->error === null) {
            // Update status in DB
            $yumisignSessions = $this->mapper->findTransactions($envelopeId);
            foreach ($yumisignSessions as $yumisignSession) {
                $yumisignSession->setStatus(Constante::status(Status::STARTED));
                $yumisignSession->setChangeStatus(time());
                $this->mapper->update($yumisignSession);
            }
        }

        return $resp;
    }

    public function cancelSignRequest($envelopeId, $userId, $forceDeletion = false, $recipient = '')
    {
        $resp = [];

        if (!$forceDeletion) {
            try {
                $signSession = $this->mapper->findActiveTransaction($envelopeId, $recipient);
            } catch (DoesNotExistException $e) {
                $resp[Constante::get(Cst::CODE)] = 0;
                $resp['message'] = $this->l->t("YumiSign transaction not started or timedout");
                return $resp;
            }
        } else {
            try {
                $signSession = $this->mapper->findTransaction($envelopeId, $recipient);
            } catch (DoesNotExistException $e) {
                $resp[Constante::get(Cst::CODE)] = 0;
                $resp['message'] = $this->l->t("YumiSign transaction not found");
                return $resp;
            }
        }

        if ($signSession->getApplicantId() !== $userId) {
            $resp[Constante::get(Cst::CODE)] = 403;
            $resp['message'] = $this->l->t("YumiSign transaction not found for this user");
            return $resp;
        }

        try {
            // Check on YumiSign only if not "Forcing process"
            // First, check the real state of this YumiSign transaction
            $check = json_decode($this->debriefWorkflow($this->serverUrl, $this->apiKey, $signSession->getWorkspaceId(), $signSession->getWorkflowId())->body, true);
            if (strcasecmp($check[Constante::get(Cst::YMS_STATUS)], Constante::status(Status::CANCELED)) !== 0) {
                $resp = $this->cancelWorkflow($this->serverUrl, $this->apiKey, $signSession->getWorkspaceId(), $signSession->getWorkflowId());
            } else {
                // Transaction already cancelled on YUmiSign server, not on DB (weird...)
                $resp[Constante::get(Cst::CODE)] = true;
                $resp['message'] = Constante::get(Cst::YMS_ALREADY_CANCELLED);
                // Update DB status to match with YUmiSign server
                $this->updateAllStatus($envelopeId, Constante::status(Status::CANCELED), time());
            }
            // Delete DB Issue Request if "Forcing process"
            if ($forceDeletion) {
                $this->mapper->deleteTransactions($envelopeId, $recipient);
                // Delete YumiSign transaction on server if no DB row left or if no recipient defined (means all rows for this Envelope ID)
                if ($this->mapper->countIssuesByEnvelopeId($envelopeId) == 0 || strcasecmp($recipient, '') === 0) {
                    $workflows = [];
                    $workflows['workflows'][] = ['id' => $signSession->getWorkflowId(), 'workspaceId' => $signSession->getWorkspaceId()];
                    $this->deleteWorkflow($this->serverUrl, $this->apiKey, json_encode($workflows));
                }

                $resp[Constante::get(Cst::CODE)] = true;
                $resp['message'] = Constante::get(Cst::YMS_DELETED);
            }
        } catch (\Throwable $th) {
            $resp[Constante::get(Cst::CODE)]    = $th->getCode();
            $resp['message'] = $th->getMessage();
            return $resp;
        }

        return $resp;
    }

    private function cancelWorkflow(string $serverUrl, string $apiKey, int $workspaceId, int $workflowId): array
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Creating YumiSign workflow");

        $return = [];

        $curlResponse = new CurlResponse();

        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows/{$workflowId}/cancel", "PUT", false);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);

            $this->logYumiSign->debug(json_encode($curlResponse), __FUNCTION__);
            // Check result validity
            $return = $this->checkResultValidity($curlResponse, __FUNCTION__);
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
            throw $th;
        }

        return $return;
    }

    public function checkAsyncSignature()
    {
        $this->logYumiSign->info("checkAsyncSignature : starting process", __FUNCTION__);

        $curlResponse = new CurlResponse();
        try {
            // Get all transactions from YumiSign server
            $ch = $this->setCurlEnv($this->apiKey, "{$this->serverUrl}/workspaces/{$this->workspaceId}/workflows", "GET", false);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);

            // Prepare a comparative array which will contain Envelopes IDs fron YumiSign server.
            $envelopesYumiSign = [];

            // Check retrieved data
            if (!empty(Utility::getArrayData(json_decode($curlResponse->body, true), 'items', false))) {
                foreach (json_decode($curlResponse->body, true)['items'] as $keyItem => $item) {
                    $envelopesYumiSign[$item['envelopeId']] = $item['envelopeId'];
                    // Browse each recipient
                    if (
                        !empty(Utility::getArrayData($item, 'recipients', false)) &&
                        !empty(Utility::getArrayData($item, 'envelopeId', false)) &&
                        !empty(Utility::getArrayData($item, 'status', false))
                    ) {
                        // Ignore transactions which have to be archived
                        if (strcasecmp($item['status'], Constante::status(Status::TO_BE_ARCHIVED)) !== 0) {
                            // If database does not contain the envelope ID, delete YumiSign transaction on server
                            if ($this->mapper->countTransactionsByEnvelopeId($item['envelopeId']) == 0) {
                                $this->logYumiSign->info(sprintf("This transaction %s (%s) is missing in DB; should delete it. Status is %s", $item['envelopeId'], $item['name'], $item['status']), __FUNCTION__);
                                $workflows = [];
                                $workflows['workflows'][] = ['id' => $item['id'], 'workspaceId' => $this->workspaceId];

                                // Check if status is cancelled; if not, cancel YumiSign transaction before deletion
                                if (strcasecmp($item['status'], Constante::status(Status::CANCELED)) !== 0) {
                                    $this->cancelWorkflow($this->serverUrl, $this->apiKey, $this->workspaceId, $item['id']);
                                }
                                $this->deleteWorkflow($this->serverUrl, $this->apiKey, json_encode($workflows));
                            } else {
                                foreach ($item['recipients'] as $recipient) {
                                    // Get recipient status
                                    $recipientStatus = $item['status'];
                                    // Modify it if more intel
                                    if (!empty(Utility::getArrayData($item, 'pendingActions', false))) {
                                        foreach ($item['pendingActions'] as $pendingAction) {
                                            if (strcasecmp($pendingAction['recipientEmail'], $recipient['email']) === 0) {
                                                $recipientStatus = $pendingAction['status'];
                                                break;
                                            }
                                        }
                                    }
                                    // Update status
                                    $this->updateStatus($item['envelopeId'], $recipient['email'], $item['status'], $recipientStatus);
                                }
                            }
                        }
                    }
                }
            }

            // Now let's clean DB thanks to filled comparative array
            try {
                $yumisignSessions = $this->mapper->findTransactions();
                foreach ($yumisignSessions as $yumisignSession) {
                    if (!array_key_exists($yumisignSession->getEnvelopeId(), $envelopesYumiSign)) {
                        $this->logYumiSign->info("Delete DB {$yumisignSession->getEnvelopeId()}", __FUNCTION__);
                        $this->mapper->deleteTransactions($yumisignSession->getEnvelopeId());
                    }
                }
            } catch (DoesNotExistException $e) {
                // YumiSign transaction table is empty
                // Not needed to bother admins...
            }
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
            $this->logYumiSign->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__);
        }
        $this->logYumiSign->info("checkAsyncSignature : well done", __FUNCTION__);

        return $curlResponse;
    }

    public function checkCurlBody(CurlResponse $curlResponse, string $functionName)
    {
        if (null === json_decode($curlResponse->body)) {
            $message = sprintf("cURL returned empty body in process \"%s\".", $functionName);
            $this->logYumiSign->debug($message, __FUNCTION__, true);
        }
    }

    public function checkCurlCode(CurlResponse $curlResponse, string $functionName)
    {
        if (($curlResponse->code !== 200) && ($curlResponse->code !== 302)) {
            $message = sprintf("cURL returned unwanted code (%s). This process is skipped for function %s.", $curlResponse->code, $functionName);
            $this->logYumiSign->debug($message, __FUNCTION__, true);
        }
    }

    // Check result validity
    public function checkResultValidity(CurlResponse $curlResponse, string $functionName): array
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Check YumiSign response");

        if (($curlResponse->code !== 200) && ($curlResponse->code !== 302)) {
            $message = sprintf("cURL returned unwanted code (%s). This process is skipped for function %s.", $curlResponse->code, $functionName);
            $this->logYumiSign->debug($message, __FUNCTION__);
            $return[Constante::get(Cst::CODE)]    = false;
            $return['message'] = $message;
            return $return;
        }

        if (null === json_decode($curlResponse->body)) throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\""), 1);
        return $this->analyseYumiSignResponse($curlResponse->body);
    }

    // Get the YumiSign server url status (OK/KO) for the Settings page
    public function checkSettings()
    {
        $return = [];

        try {
            // Retrieve connection intel from database
            $apiKey      = $this->config->getAppValue('yumisign_nextcloud', 'api_key');
            $serverUrl   = $this->config->getAppValue('yumisign_nextcloud', 'server_url');
            $workspaceId = $this->config->getAppValue('yumisign_nextcloud', 'workspace_id');

            // Call the server with the stored parameters
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}", "GET", false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);
            curl_close($ch);

            // Check response validity
            $this->checkCurlCode($curlResponse, __FUNCTION__);
            $this->checkCurlBody($curlResponse, __FUNCTION__);

            // At this point, this response is OK
            $return[Constante::get(Cst::YMS_ID)] = $workspaceId;
            $return[Constante::get(Cst::CODE)] = $return[Constante::get(Cst::YMS_STATUS)] = true;
            $return[Constante::get(Cst::YMS_NAME)] = $return[Constante::get(Cst::YMS_MESSAGE)] = $this->l->t("Connected to YumiSign");
        } catch (\Throwable $th) {
            $this->logYumiSign->error($th->getMessage(), __FUNCTION__);
            $this->logYumiSign->error(json_encode($curlResponse), __FUNCTION__);
            $return = [];
            $return[Constante::get(Cst::YMS_ID)] = 0;
            $return[Constante::get(Cst::CODE)] = $return[Constante::get(Cst::YMS_STATUS)]  = false;
            $return[Constante::get(Cst::YMS_MESSAGE)] = $th->getMessage();
        }

        return $return;
    }

    // Get the YumiSign server url status (OK/KO) for the Settings page
    public function checkServerUrl(IRequest $request)
    {
        $return = [];

        try {
            // Retrieve connection intel from request parameters
            $apiKey = $request->getParam('api_key') ?? '';
            $serverUrl = $request->getParam('server_url') ?? '';
            $workspaceId = $request->getParam('workspace_id') ?? '';

            // Get all transactions from YumiSign server: this is the best way to check the 3 mandatory parameters at the same time
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows", "GET", false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);
            curl_close($ch);

            // Check response validity
            $this->checkCurlCode($curlResponse, __FUNCTION__);
            $this->checkCurlBody($curlResponse, __FUNCTION__);

            // At this point, this response is OK
            $return[Constante::get(Cst::YMS_ID)] = $workspaceId;
            $return[Constante::get(Cst::CODE)] = $return[Constante::get(Cst::YMS_STATUS)] = true;
            $return[Constante::get(Cst::YMS_NAME)] = $return[Constante::get(Cst::YMS_MESSAGE)] = $this->l->t("Connected to YumiSign");
        } catch (\Throwable $th) {
            $this->logYumiSign->error($th->getMessage(), __FUNCTION__);
            $this->logYumiSign->error(json_encode($curlResponse), __FUNCTION__);
            $return = [];
            $return[Constante::get(Cst::YMS_ID)] = 0;
            $return[Constante::get(Cst::CODE)] = $return[Constante::get(Cst::YMS_STATUS)]  = false;
            $return[Constante::get(Cst::YMS_MESSAGE)] = $th->getMessage();
        }

        return $return;
    }

    public function checkWspCommon(array $workspace, string $wksName, string $wksId)
    {
        if (strcasecmp($wksId, '') === 0) {
            return (strcasecmp(Utility::getArrayData($workspace, Constante::get(Cst::YMS_NAME), true, true), $wksName) === 0);
        } else {
            return (strcasecmp(Utility::getArrayData($workspace, Constante::get(Cst::YMS_NAME), true, true), $wksName) === 0 &&
                Utility::getArrayData($workspace, Constante::get(Cst::YMS_ID), true, true) === intval($wksId));
        }
    }

    public function checkWorkspace(IRequest $request)
    {
        $return = [];
        $return[Constante::get(Cst::YMS_LIST_ID)] = [];
        $return[Constante::get(Cst::YMS_ID)] = '';

        try {
            // Get workspace name from parameters
            $wksName = $request->getParam('workspace_name') ?? '';
            $wksId   = $request->getParam('workspace_id') ?? '';

            // Set cURL options
            $ch = $this->setCurlEnv($this->apiKey, "{$this->serverUrl}/workspaces", "GET");

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);
            curl_close($ch);

            // Check response validity
            $this->checkCurlCode($curlResponse, __FUNCTION__);
            $this->checkCurlBody($curlResponse, __FUNCTION__);

            $found = false;
            $workspaces = json_decode($curlResponse->body, true);
            // Verify received intel from YumiSign server
            foreach ($workspaces as $workspace) {
                if (
                    $this->checkWspCommon($workspace, $wksName, $wksId)
                ) {
                    // Common behaviour based on Name value
                    $found = true;
                    $return[Constante::get(Cst::CODE)] = $return[Constante::get(Cst::YMS_STATUS)] = true;
                    $return[Constante::get(Cst::YMS_NAME)] = $return[Constante::get(Cst::YMS_MESSAGE)] = Utility::getArrayData($workspace, Constante::get(Cst::YMS_NAME), true, true);

                    // According to the ID value
                    if (strcasecmp($wksId, '') !== 0) {
                        // Name & ID are filled => found this pair
                        $return[Constante::get(Cst::YMS_ID)]   = Utility::getArrayData($workspace, Constante::get(Cst::YMS_ID), true, true);
                        break;
                    } else {
                        // Just a valid Name but maybe several exist => add to the IDs list
                        $return[Constante::get(Cst::YMS_LIST_ID)][] = Utility::getArrayData($workspace, Constante::get(Cst::YMS_ID), true, true);
                    }
                }
            }

            // If only one ID is found, convert IDs list to unique ID
            if (sizeof($return[Constante::get(Cst::YMS_LIST_ID)]) === 1) {
                $return[Constante::get(Cst::YMS_ID)] = $return[Constante::get(Cst::YMS_LIST_ID)][0];
            }

            if (!$found) $this->logYumiSign->info(sprintf($this->l->t("The workspace named \"%s\" was not found on YumiSign server"), $wksName), __FUNCTION__);
        } catch (\Throwable $th) {
            $this->logYumiSign->error($th->getMessage(), __FUNCTION__);
            $this->logYumiSign->error(json_encode($curlResponse), __FUNCTION__);
            $return = [];
            $return[Constante::get(Cst::YMS_ID)] = '';
            $return[Constante::get(Cst::YMS_LIST_ID)] = [];
            $return[Constante::get(Cst::CODE)] = $return[Constante::get(Cst::YMS_STATUS)]  = false;
            $return[Constante::get(Cst::YMS_MESSAGE)] = $th->getMessage();
        }

        return $return;
    }

    private function createWorkflow(string $serverUrl, string $apiKey, int $workspaceId, string $userId, File $file, string $signType, int $expiryDate): CurlResponse
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Creating YumiSign workflow");

        $curlResponse = new CurlResponse();

        try {
            $wfName = $file->getName() . ' (' . date('Y-m-d_H:i:s') . ')';

            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows", "POST", false);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $post = array(
                'name' => $wfName,
                'document' => new CURLFILE(
                    'data://application/octet-stream;base64,' . base64_encode($file->getContent()),
                    mime_content_type($file->getMimeType()),
                    $file->getName()
                ),
                'type' => $signType,
                'expiryDate' => $expiryDate,
                'senderName' => $userId,
            );
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            // Add name if workflow has been created
            if (isset(json_decode($curlResponse->body)->id)) {
                $tmpResponse = json_decode($curlResponse->body);
                $tmpResponse->name = $wfName;

                $curlResponse->body = json_encode($tmpResponse);
            }

            curl_close($ch);

            // Check result validity
            if (null === json_decode($curlResponse->body))          throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\""), 1);
            if (isset(json_decode($curlResponse->body)->error))     throw new Exception($this->l->t("Error occurred during process: \"{$processPrefix}\" / code: \"" . json_decode($curlResponse->body)->error->code . "\""), 1);

            // Manage missing fields
            $workflow = json_decode($curlResponse->body);
            if (!isset($workflow->id))            throw new Exception($this->l->t("Cannot retrieve Workflow ID; process: \"{$processPrefix}\" / code: \"" . json_decode($curlResponse->body)->error->code . "\""), 1);
            if (!isset($workflow->envelopeId))    throw new Exception($this->l->t("Cannot retrieve Envelope ID; process: \"{$processPrefix}\" / code: \"" . json_decode($curlResponse->body)->error->code . "\""), 1);
            if (!isset($workflow->documents))     throw new Exception($this->l->t("No documents in this transaction; process: \"{$processPrefix}\" / code: \"" . json_decode($curlResponse->body)->error->code . "\""), 1);
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
            throw $th;
        }

        return $curlResponse;
    }

    private function createWorkspace(string $serverUrl, string $apiKey, $wsName, $wsDescription): CurlResponse
    {
        $curlResponse = new CurlResponse();

        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces", "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{"name": "' . $wsName . '", "description": "' . $wsDescription . '"}');

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
        }

        return $curlResponse;
    }

    private function debriefWorkflow(string $serverUrl, string $apiKey, int $workspaceId, int $workflowId): CurlResponse
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Retrieve YumiSign workflow intel");

        $curlResponse = new CurlResponse();

        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows/{$workflowId}", "GET");

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $curlResponse;
    }

    private function deleteWorkflow(string $serverUrl, string $apiKey, string $workflowsJson): array
    {
        $return = [];

        $curlResponse = new CurlResponse();

        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workflows", "DELETE");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $workflowsJson);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);

            // Check result validity
            $return = $this->checkResultValidity($curlResponse, __FUNCTION__);
        } catch (\Throwable $th) {
            $this->logYumiSign->error($th->getMessage(), __FUNCTION__);
            $this->logYumiSign->error(sprintf("Variables : %s / %s / %s", $serverUrl, $apiKey, $workflowsJson), __FUNCTION__);
            $this->logYumiSign->error(json_encode($curlResponse), __FUNCTION__);
            $curlResponse = new CurlResponse();
            throw $th;
        }

        return $return;
    }

    private function retrieveEnvelopesIds(string $serverUrl, string $apiKey, array $envelopesIds): CurlResponse
    {
        $curlResponse = new CurlResponse();

        try {
            // Create array parameters
            $arrayParameters = '';
            foreach ($envelopesIds as $key => $envelopeIdArray) {
                $envelopeId = $envelopeIdArray[Constante::get(Cst::YMS_ENVELOPEID)];
                $arrayParameters .= "&ids[]=$envelopeId";
            }

            $curlUrl = "{$serverUrl}/envelopes?{$arrayParameters}";
            $ch = $this->setCurlEnv($apiKey, $curlUrl, "GET", false);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $curlResponse;
    }

    // Common get response
    public function getCurlResponse($ch): CurlResponse
    {
        $curlResponse = new CurlResponse();

        $curlResponse->response = curl_exec($ch);
        $curlResponse->code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $curlResponse->header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlResponse->header = substr($curlResponse->response, 0, $curlResponse->header_size);
        $curlResponse->body   = substr($curlResponse->response,    $curlResponse->header_size);

        return $curlResponse;
    }

    private function getSession(string $serverUrl, string $apiKey, int $workspaceId, int $workflowId): CurlResponse
    {
        $curlResponse = new CurlResponse();
        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows/{$workflowId}/session", "GET");

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
        }
        return $curlResponse;
    }

    // Retrieve Nextcloud workspace
    // name : Nextcloud
    // description : YumiSign for Nextcloud
    private function getWorkspace(string $serverUrl, string $apiKey, bool $createWsp = false): array
    {
        $workspaceReturned = [];
        // Init failed values just in case (maybe a forgotten UC...)
        $workspaceReturned[Constante::get(Cst::CODE)]    = false;
        $workspaceReturned[Constante::get(Cst::YMS_MESSAGE)] = $this->l->t("Workspace not defined.");

        try {
            if (!empty($this->workspaceId))   throw new Exception($this->l->t("Workspace ID is already defined."), 1);
            if (empty($this->workspaceName)) throw new Exception($this->l->t("Workspace name is empty."), 1);

            // YMS Workspace identity
            $wsName = $this->workspaceName;
            $wsDescription = $this->description;

            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces", "GET");

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);
            $this->logYumiSign->debug(json_encode($curlResponse), __FUNCTION__);

            curl_close($ch);

            $found = false;
            foreach (json_decode($curlResponse->body) as $workspace) {
                if ($workspace->name === $wsName && $workspace->description === $wsDescription) {
                    $found = true;
                    $workspaceReturned[Constante::get(Cst::CODE)] = true;
                    $workspaceReturned[Constante::get(Cst::YMS_ID)]   = $workspace->id;
                    $workspaceReturned[Constante::get(Cst::YMS_NAME)] = $wsName;
                    break;
                }
            }

            // if WS not found, create it
            if (!$found && $createWsp) {
                $curlResponse = $this->createWorkspace($serverUrl, $apiKey, $wsName, $wsDescription);
                if (is_null($curlResponse) || !isset(json_decode($curlResponse->body)->id)) throw new Exception($this->l->t("Cannot retrieve Workspace ID."), 1);
                $response = json_decode($curlResponse->body, true);
                $workspaceReturned[Constante::get(Cst::CODE)] = true;
                $workspaceReturned[Constante::get(Cst::YMS_ID)]   = $response[Constante::get(Cst::YMS_ID)];
                $workspaceReturned[Constante::get(Cst::YMS_NAME)] = $response[Constante::get(Cst::YMS_NAME)];
                $found = true;
            }
        } catch (\Throwable $th) {
            $this->logYumiSign->error(json_encode($curlResponse), __FUNCTION__);
            $workspaceReturned = [];
            $workspaceReturned[Constante::get(Cst::CODE)]    = false;
            $workspaceReturned[Constante::get(Cst::YMS_MESSAGE)] = $th->getMessage();
        }

        return $workspaceReturned;
    }

    public function lastJobRun()
    {
        $return = [];
        // $processError = true;

        try {
            $ymsJob = $this->mapper->findJob();
            $reservedAt = Utility::getArrayData($ymsJob, 'reserved_at', false, 'No "Reservation" column found in query result');
            $lastRun = Utility::getArrayData($ymsJob, 'last_run', false, 'No "Last Run" column found in query result');

            switch (true) {
                case $reservedAt === 0 && $lastRun === 0:
                    $return[Constante::get(Cst::CODE)] = true;
                    $return[Constante::get(Cst::YMS_STATUS)]  = Constante::get(Cst::YMS_SUCCESS);
                    $return[Constante::get(Cst::YMS_MESSAGE)] = $this->l->t('The cron job is activated');
                    break;

                case $reservedAt === 0 && $lastRun !== 0:
                    // $lastRun = date('Y-m-d_H:i:s', $lastRun);
                    $return[Constante::get(Cst::CODE)] = true;
                    $return[Constante::get(Cst::YMS_STATUS)]  = Constante::get(Cst::YMS_SUCCESS);
                    $return[Constante::get(Cst::YMS_MESSAGE)] = $this->l->t('The cron job is activated; the last time the job ran was at %s', [date('Y-m-d_H:i:s', $lastRun)]);
                    break;

                default:
                    $return[Constante::get(Cst::CODE)] = false;
                    $return[Constante::get(Cst::YMS_STATUS)]  = Constante::get(Cst::ERROR);
                    $return[Constante::get(Cst::YMS_MESSAGE)] = $this->l->t('The cron job was disabled at %s', [date('Y-m-d_H:i:s', $reservedAt)]);
                    break;
            }
        } catch (\Throwable $th) {
            $this->logYumiSign->error($this->l->t('Checking process failed at %s', [date('Y-m-d_H:i:s')]), __FUNCTION__, true);
            $return[Constante::get(Cst::CODE)] = false;
            $return[Constante::get(Cst::YMS_STATUS)]  = Constante::get(Cst::ERROR);
            $return[Constante::get(Cst::YMS_MESSAGE)] = $th->getMessage();
        }

        return $return;
    }

    private function randomColorPart()
    {
        return str_pad(dechex(mt_rand(128, 230)), 2, '0', STR_PAD_LEFT);
    }

    public function resetJob()
    {
        $return = [];

        try {
            $this->mapper->resetJob();
            // No exception => query is OK (does not mean data is updated)
            $return[Constante::get(Cst::CODE)] = true;
            $return[Constante::get(Cst::YMS_STATUS)]  = Constante::get(Cst::YMS_SUCCESS);
            $return[Constante::get(Cst::YMS_MESSAGE)] = $this->l->t('The cron job has been activated at %s', [date('Y-m-d_H:i:s')]);
        } catch (\Throwable $th) {
            $return[Constante::get(Cst::CODE)] = false;
            $return[Constante::get(Cst::YMS_STATUS)]  = Constante::get(Cst::ERROR);
            $return[Constante::get(Cst::YMS_MESSAGE)] = $th->getMessage();
        }

        return $return;
    }

    public function saveTransactionFiles(array $requestBody, string $userId)
    {
        try {
            $warningMsg = '';
            $created = null;
            $envelopeId = '';

            // Get current document full path (filesystem)
            $user = $this->userManager->get($userId);
            $userFolder = $this->rootFolder->getUserFolder($user->getUID());

            if (array_key_exists('documents', $requestBody)) {
                foreach ($requestBody['documents'] as $key => $document) {
                    if (array_key_exists('file', $document) && array_key_exists('file', $document['file'])) {
                        $envelopeId = Utility::getArrayData($requestBody, 'id', true, 'YumiSign transaction ID field is missing');
                        try {
                            $yumisignSession = $this->mapper->findTransaction($envelopeId);
                        } catch (DoesNotExistException $e) {
                            $warningMsg = "YumiSign transaction {$envelopeId} not found";
                            $this->logYumiSign->info($warningMsg, __FUNCTION__);
                            return Utility::warning($warningMsg);
                        }

                        // Only one session in the result
                        if ($yumisignSession->getApplicantId()) $applicantId    = $yumisignSession->getApplicantId();
                        if ($yumisignSession->getFilePath())    $filePath       = $yumisignSession->getFilePath();
                        // if ($yumisignSession->getWorkflowId())  $workflowId  = $yumisignSession->getWorkflowId();
                        if ($yumisignSession->getFileId())      $fileId         = $yumisignSession->getFileId();

                        $url = str_replace('\\', '', $document['file']['file']);
                        $originalFilename            = trim($document['file']['name'], DIRECTORY_SEPARATOR);
                        $originalFilenameNoExtension = pathinfo($originalFilename, PATHINFO_FILENAME);
                        $originalExtension           = pathinfo($originalFilename, PATHINFO_EXTENSION);

                        $ymsUrlArchive = $this->serverUrl . Constante::get(Cst::YMS_URL_ARCHIVE);

                        $this->logYumiSign->debug($document['file']['file'], __FUNCTION__);
                        $this->logYumiSign->debug($url, __FUNCTION__);
                        $this->logYumiSign->debug($ymsUrlArchive, __FUNCTION__);

                        $this->logYumiSign->debug(substr(parse_url($url, PHP_URL_PATH), 0, strlen(parse_url($ymsUrlArchive, PHP_URL_PATH))), __FUNCTION__);
                        $this->logYumiSign->debug(parse_url($ymsUrlArchive, PHP_URL_PATH), __FUNCTION__);

                        if (
                            strcasecmp(
                                substr(parse_url($url, PHP_URL_PATH), 0, strlen(parse_url($ymsUrlArchive, PHP_URL_PATH))),
                                parse_url($ymsUrlArchive, PHP_URL_PATH)
                            ) !== 0
                        ) {
                            $this->logYumiSign->warning(
                                'EnvelopeID mismatch : ' .
                                    parse_url(substr($url, 0, strlen($ymsUrlArchive))) .
                                    ' versus ' .
                                    parse_url($ymsUrlArchive),
                                __FUNCTION__
                            );
                            $this->logYumiSign->warning($warningMsg, __FUNCTION__);
                            throw new Exception("This url does not match to the Archives", 1);
                        }

                        $ch = $this->setCurlEnv($this->apiKey, $url, "GET", false);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        $temporaryFile = curl_exec($ch);
                        curl_close($ch);

                        // Move the temporary file as the final timestamped file in user's folder (near the original file)
                        $timestamp = $this->getUserLocalesTimestamp($applicantId, new DateTime());

                        if (isset($fileId)) {
                            $fileNode = $userFolder->getById($fileId)[0];
                        } else {
                            throw new Exception("File Id is not set for this envelopeId: {$envelopeId}", 404);
                        }

                        $folder = $fileNode->getParent();
                        $fileName = sprintf(
                            '%s_YumiSigned_%s.%s',
                            $originalFilenameNoExtension,
                            $timestamp,
                            $originalExtension
                        );

                        // Have to add this try catch to prevent exception on External storages
                        try {
                            $this->logYumiSign->info("Saving file for user \"{$userId}\" [{$fileName}]");
                            $created = $folder->newFile(
                                $fileName,
                                $temporaryFile
                            );
                        } catch (\Throwable $th) {
                            $created = $folder->search($fileName)[0];
                            //throw $th;
                        }

                        return [
                            'fileNode' => $created,
                            'error' => 0,
                        ];
                    }
                }
            }
        } catch (\Throwable $th) {
            $envelopeId = $envelopeId === '' ? 'undefined' : $envelopeId;
            $this->logYumiSign->error("Issue on envelopeId {$envelopeId}: {$th->getMessage()}", __FUNCTION__);
            return [
                'fileNode' => $created,
                'error' => $th->getCode(),
            ];
        }
    }

    // Common function for cURL
    public function setCurlEnv(string $apiKey, string $url, string $request, bool $contentTypeJson = true): CurlHandle
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request);
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Proxy configuration
        if ($this->useProxy === "1") {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);

            // Set the proxy IP
            curl_setopt($ch, CURLOPT_PROXY, $this->proxyHost);

            // Set the port
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxyPort);

            // Set the username and password
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$this->proxyUsername}:{$this->proxyPassword}");
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-YumiSign-Api-Key:' . $apiKey,
            ($contentTypeJson ? 'Content-Type:application/json' : ''),
        ]);

        return $ch;
    }

    private function startWorkflow(string $serverUrl, string $apiKey, int $workspaceId, int $workflowId): CurlResponse
    {
        $curlResponse = new CurlResponse();
        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows/{$workflowId}/start", "PUT", false);

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
        }

        return $curlResponse;
    }

    // Get the YumiSign workspace name status (OK/KO) for the Settings page
    public function statusWorkspaceName(IRequest $request)
    {
        $returnValue = false;

        return $returnValue;
    }

    public function updateAllStatus(string $envelopeId, string $status, int $msgDate)
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Update all YumiSign workflow status");

        try {
            $yumisignSessions = $this->mapper->findTransactions($envelopeId);
            foreach ($yumisignSessions as $yumisignSession) {
                // Check if update is needed according to sent message date
                if ($msgDate > $yumisignSession->getMsgDate()) {
                    $yumisignSession->setGlobalStatus($status);
                    $yumisignSession->setStatus($status);
                    $yumisignSession->setChangeStatus(time());
                    $yumisignSession->setMsgDate($msgDate);

                    // Update
                    $this->mapper->update($yumisignSession);
                }
            }
        } catch (\Throwable $th) {
            throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\" / message: \"" . $th->getMessage() . "\""), 1);
        }
    }

    public function updateStatus(string $envelopeId, string $recipient, string $globalStatus, string $status, int $msgDate = 0)
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Update YumiSign workflow status");

        try {
            if ($msgDate == 0) $msgDate = time();
            try {
                $yumisignSession = $this->mapper->findRecipientTransaction($envelopeId, $recipient);
            } catch (DoesNotExistException $e) {
                $warningMsg = "YumiSign transaction {$envelopeId} not found for recipient {$recipient}";
                $this->logYumiSign->info($warningMsg, __FUNCTION__);
                return Utility::warning($warningMsg);
            }

            // Check if update is needed according to sent message date
            if ($msgDate > $yumisignSession->getMsgDate()) {
                $yumisignSession->setGlobalStatus($globalStatus);
                $yumisignSession->setStatus($status);
                $yumisignSession->setChangeStatus(time());
                $yumisignSession->setMsgDate($msgDate);

                // Update
                $this->mapper->update($yumisignSession);
            }
        } catch (\Throwable $th) {
            $exceptionMsg = $this->l->t("Critical error during process : \"{$processPrefix}\" / message: \"" . $th->getMessage() . "\" / {$envelopeId} / {$recipient} / {$globalStatus} / {$status}");
            $this->logYumiSign->error($exceptionMsg, __FUNCTION__, true);
            throw new Exception($exceptionMsg, 1);
        }
    }

    public function webhook($headerYumiSign, $requestBody)
    {
        $processStatus = [];
        $applicantId = "";

        try {
            // Get header data
            if (empty($headerYumiSign)) {
                $warningMsg = $this->l->t('YumiSign signature is missing');
                $this->logYumiSign->warning($warningMsg, __FUNCTION__);
                throw new Exception($warningMsg, 1);
            }

            $headerdata = explode(',', str_replace('=', ',', $headerYumiSign));

            if (array_key_exists('_route', $requestBody)) unset($requestBody['_route']);

            $envelopeId = Utility::getArrayData($requestBody, 'id',         true, 'YumiSign transaction ID field is missing');
            $status     = Utility::getArrayData($requestBody, 'status',     true, 'YumiSign transaction status field is missing');

            $secret = "";

            try {
                $yumisignSession = $this->mapper->findTransaction($envelopeId);
            } catch (DoesNotExistException $e) {
                $warningMsg = "YumiSign transaction {$envelopeId} not found";
                $this->logYumiSign->warning($warningMsg, __FUNCTION__);
                throw new Exception($warningMsg, 1);
            }

            if ($yumisignSession->getApplicantId()) $applicantId = $yumisignSession->getApplicantId();
            if ($yumisignSession->getSecret()) $secret = $yumisignSession->getSecret();
            else {
                $warningMsg = "YumiSign transaction secret not found";
                $this->logYumiSign->warning($warningMsg, __FUNCTION__);
                throw new Exception($warningMsg, 1);
            }

            $payload = $headerdata[1] . "." . json_encode($requestBody);

            // Check if Keys match
            $match = (strcmp(hash_hmac('sha256', $payload, $secret), $headerdata[3]) === 0);

            // KO if not match
            if (!$match) {
                $warningMsg = "YumiSign transaction bad key";
                $this->logYumiSign->warning($warningMsg, __FUNCTION__);
                throw new Exception($warningMsg, 1);
            }

            // Valid transaction, do what you need to do...
            switch ($status) {
                case Constante::status(Status::NOT_STARTED):
                case Constante::status(Status::APPROVED):
                case Constante::status(Status::CANCELED):
                case Constante::status(Status::DECLINED):
                case Constante::status(Status::EXPIRED):
                case Constante::status(Status::TO_BE_ARCHIVED):
                case Constante::status(Status::STARTED):
                    // Update each recipient status
                    $steps = Utility::getArrayData($requestBody, 'steps',       true, 'YumiSign transaction steps fields are missing');
                    foreach ($steps as $step) {
                        foreach ($step['actions'] as $action) {
                            $resp = $this->updateStatus($envelopeId, $action['recipientEmail'], $status, $action['status'], $headerdata[1]);
                        }
                    }
                    break;
                case Constante::get(Cst::YMS_SIGNED):
                    /**
                     * Only request status is available; the recipients status are not written in this Signed request
                     * So all status are updated as Signed (recipients + global)
                     */
                    $resp = $this->updateAllStatus($envelopeId, $status, $headerdata[1]);
                    // Save the files of this transaction (all recipients have signed)
                    $resp = $this->saveTransactionFiles($requestBody, $yumisignSession->getApplicantId());
                    break;
                default:
                    #code...
                    break;
            }

            $processStatus[Constante::get(Cst::CODE)] = true;
            $processStatus['message'] = "YumiSign transaction {status}";
            $processStatus['status'] = $status;
        } catch (\Throwable $th) {
            $warningMsg = "{$th->getMessage()} / Envelope ID : {$envelopeId}";
            $this->logYumiSign->error($warningMsg, __FUNCTION__);
            $processStatus = Utility::warning($warningMsg);
        }

        try {
            // Send notification to applicant whatever the YumiSign transaction status EXCEPT for status == NULL (This is the WebHook initialization)
            // However, if there was an exception, keep display it
            $manager = \OC::$server->get(IManager::class);
            $notification = $manager->createNotification();

            if (!empty($applicantId)) {
                $notification->setApp(YumiSignApp::APP_ID)
                    ->setUser($applicantId)
                    ->setDateTime(new \DateTime())
                    ->setObject('envelopeId', $envelopeId)
                    ->setSubject('YumiSign', $processStatus);

                $manager->notify($notification);
            }
        } catch (\Throwable $th) {
            $this->logYumiSign->error($th->getMessage(), __FUNCTION__);
            return Utility::warning($th->getMessage());
        }
    }

    public function checkAsyncSignatureTask(string $applicantId = null)
    {
        try {
            $this->logYumiSign->debug("########################################################################", __FUNCTION__);

            $envelopesIds = [];
            $transactionsToUpdate = [];
            $rightNow = intval(time());

            // Update expired transactions status and global status
            $this->mapper->updateTransactionsStatusExpired();

            // Count actives transactions
            $countTransactions = $this->mapper->countTransactions($rightNow, $applicantId);
            if ($countTransactions[Constante::get(Cst::CODE)] != 1) {
                throw new Exception($countTransactions[Constante::get(Cst::ERROR)], 1);
            }
            // Just to have a clearer code ...
            $countTransactions = $countTransactions[Constante::get(Cst::DATA)];

            $realTransactionProcessed = 0;

            $this->logYumiSign->debug("Transactions : {$countTransactions}", __FUNCTION__);

            $nbPages = intdiv($countTransactions, $this->mapper->maxItems) + ($countTransactions % $this->mapper->maxItems > 0    ? 1 : 0);
            $this->logYumiSign->debug("Pages : {$nbPages}", __FUNCTION__);

            $startCheckProcess = new DateTime();
            $this->logYumiSign->debug(sprintf("Start check process at %s", date_format($startCheckProcess, "Y/m/d H:i:s")), __FUNCTION__);

            for ($cptPagesTransactions = 0; $cptPagesTransactions < $nbPages; $cptPagesTransactions++) {

                // fet transactionsfor this page
                $transactionsPage = $this->mapper->findAllEnvelopesIds($rightNow, $applicantId, $cptPagesTransactions, $this->mapper->maxItems);

                $envelopesIds = [];
                // Get all current page envelopeIds in only one curl call
                foreach ($transactionsPage as $unitRecord) {
                    $envelopesIds[$unitRecord->getEnvelopeId()] = [
                        Constante::get(Cst::YMS_ENVELOPEID)    => $unitRecord->getEnvelopeId(),
                        Constante::get(Cst::YMS_APPLICANTID)   => $unitRecord->getApplicantId(),
                    ];
                }

                // Retrieve all the transactions corresponding to the current page envelopesIds
                $curlSession = $this->retrieveEnvelopesIds($this->serverUrl, $this->apiKey, $envelopesIds);
                $requestBody = json_decode($curlSession->body, true);

                // Check if return request is without error
                if (array_key_exists(Constante::yumisign(Yumisign::ERROR), $requestBody) && !is_null($requestBody[Constante::yumisign(Yumisign::ERROR)])) {
                    $apiKeyRateLimitReached = ($requestBody[Constante::yumisign(Yumisign::ERROR)][Constante::yumisign(Yumisign::CODE)] === 'API_KEY_RATE_LIMIT_REACHED');
                    $this->logYumiSign->error(sprintf("Something happened during YumiSign server calls; server sent this message: %s", $requestBody[Constante::yumisign(Yumisign::ERROR)][Constante::yumisign(Yumisign::MESSAGE)]), __FUNCTION__);
                } else {
                    // Prepare to update transations from retrieved data
                    foreach ($requestBody as $actualTransaction) {

                        switch (true) {
                            case array_key_exists(Constante::yumisign(Yumisign::ERROR), $actualTransaction) && !is_null($actualTransaction[Constante::yumisign(Yumisign::ERROR)]):
                                switch ($actualTransaction[Constante::yumisign(Yumisign::ERROR)][Constante::yumisign(Yumisign::CODE)]) {
                                    case Constante::error(Error::YMS_ERR_ENVELOPE_NOT_FOUND):
                                        $transactionsToUpdate[] = [
                                            Constante::entity(Entity::ENVELOPE_ID)      => $actualTransaction[Constante::yumisign(Yumisign::IDENTIFIER)],
                                            Constante::entity(Entity::APPLICANT_ID)     => $applicantId,
                                            Constante::entity(Entity::STATUS)           => Constante::status(Status::NOT_FOUND),
                                            Constante::entity(Entity::GLOBAL_STATUS)    => Constante::status(Status::NOT_FOUND),
                                        ];
                                        break;

                                    default: // Set generic status
                                        $transactionsToUpdate[] = [
                                            Constante::entity(Entity::ENVELOPE_ID)      => $actualTransaction[Constante::yumisign(Yumisign::IDENTIFIER)],
                                            Constante::entity(Entity::APPLICANT_ID)     => $applicantId,
                                            Constante::entity(Entity::STATUS)           => Constante::status(Status::NOT_APPLICABLE),
                                            Constante::entity(Entity::GLOBAL_STATUS)    => Constante::status(Status::NOT_APPLICABLE),
                                        ];
                                        break;
                                }
                                break;
                            case array_key_exists(Constante::yumisign(Yumisign::ERROR), $actualTransaction) && is_null($actualTransaction[Constante::yumisign(Yumisign::ERROR)]):
                                // Flag which indicated if we insert the array $currentTransaction after foreach loops
                                $isCurrentTransactionInserted = false;
                                $currentTransaction = [
                                    Constante::entity(Entity::ENVELOPE_ID)      => $actualTransaction[Constante::yumisign(Yumisign::IDENTIFIER)],
                                    Constante::entity(Entity::APPLICANT_ID)     => $applicantId,
                                    Constante::entity(Entity::GLOBAL_STATUS)    => $actualTransaction[Constante::yumisign(Yumisign::RESPONSE)][Constante::yumisign(Yumisign::STATUS)],
                                ];

                                // if glpbal status is signed, save signed documents
                                if ($currentTransaction[Constante::entity(Entity::GLOBAL_STATUS)] === Constante::status(Status::SIGNED)) {
                                    $this->saveTransactionFiles(
                                        $actualTransaction['response'],
                                        // $userId,
                                        $envelopesIds[$actualTransaction['response']['id']][Constante::get(Cst::YMS_APPLICANTID)],
                                    );
                                }

                                // Check status for all recipients (will run if not signed)
                                foreach ($actualTransaction[Constante::yumisign(Yumisign::RESPONSE)][Constante::yumisign(Yumisign::STEPS)] as $key => $step) {
                                    foreach ($step[Constante::yumisign(Yumisign::ACTIONS)] as $key => $action) {

                                        $currentTransaction[Constante::entity(Entity::RECIPIENT)]   = $action[Constante::yumisign(Yumisign::RECIPIENTEMAIL)];
                                        $currentTransaction[Constante::entity(Entity::STATUS)]      = $action[Constante::yumisign(Yumisign::STATUS)];
                                        $transactionsToUpdate[] = $currentTransaction; // We insert directly the current transaction inside the global array
                                        $isCurrentTransactionInserted = true;
                                    }
                                }
                                if (!$isCurrentTransactionInserted) {
                                    $transactionsToUpdate[] = $currentTransaction;
                                }
                                break;
                            default:
                                $this->logYumiSign->warning(sprintf("This case is not implemented; please report a bug with the following data [%s]", json_encode($actualTransaction)), __FUNCTION__);
                                break;
                        }

                        $realTransactionProcessed++;
                    }
                }

                // Leave the loop before planned if YMS server is flooded
                if ($apiKeyRateLimitReached) {
                    break;
                }
            }

            $endCheckProcess = new DateTime();

            $sinceStart = $startCheckProcess->diff($endCheckProcess);
            $this->logYumiSign->debug(sprintf("End check process at %s", date_format($endCheckProcess, "Y/m/d H:i:s")), __FUNCTION__);
            $this->logYumiSign->info(sprintf("Data processed: %d records treated in %d d %d H %d m %d s", $realTransactionProcessed, $sinceStart->d, $sinceStart->h, $sinceStart->i, $sinceStart->s), __FUNCTION__);

            // Update data in local DB
            $startUpdatedata = new DateTime();
            $this->logYumiSign->debug(sprintf("Start update process at %s", date_format($startUpdatedata, "Y/m/d H:i:s")), __FUNCTION__);

            $realTransactionUpdated = $this->mapper->updateTransactionsStatus($transactionsToUpdate);
            $endUpdatedata = new DateTime();

            $sinceStart = $startUpdatedata->diff($endUpdatedata);

            $this->logYumiSign->debug(sprintf("End update process at %s", date_format($endUpdatedata, "Y/m/d H:i:s")), __FUNCTION__);
            $this->logYumiSign->info(sprintf("Database updated: %d records treated in %d d %d H %d m %d s", $realTransactionUpdated, $sinceStart->d, $sinceStart->h, $sinceStart->i, $sinceStart->s), __FUNCTION__);

            return;
        } catch (\Throwable $th) {
            $this->logYumiSign->error($th->getMessage(), __FUNCTION__);
            return Utility::warning($th->getMessage());
        }
    }

    public function getUserLocalesTimestamp(string $userId, \DateTime $date)
    {
        $owner = $this->userManager->get($userId);
        $lang = 'en';
        $timeZone = $this->systemConfig->getUserValue($owner->getUID(), 'core', 'timezone', null);
        $timeZone = isset($timeZone) ? new \DateTimeZone($timeZone) : new \DateTimeZone('UTC');

        if ($lang) {
            $l10n = $this->l10nFactory->get(YumiSignApp::APP_ID, $lang);
            if (!$l10n) {
                $l10n = $this->l10n;
            }
        } else {
            $l10n = $this->l10n;
        }
        $date->setTimezone($timeZone);
        return $date->format('Y-m-d H:i:s');
    }
}

<?php

namespace OCA\YumiSignNxtC\Service;

use CURLFile;
use CurlHandle;
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

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class SignService
{
    use GetsFile;

    const CNX_TIME_OUT = 3;

    /** @var IL10N */
    private $l;

    private SignSessionMapper $mapper;
    private $storage;
    private $accountManager;
    private $userManager;

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
    private $temporaryFolder;
    private $useProxy;
    private $userSettings;
    private $watermarkText;
    private int $workspaceId;
    private $workspaceName;
    /**
     * This priority status array is used to prevent network delays to override most recent status (workflow logic)
     * e.g. "signed" is after "approved" but if "Approved message" is received after "Signed message", the "approved" status will erase the final "signed" status
     */
    private $statusPriority = [
        YMS_STATUS_NOT_STARTED      => 0,
        YMS_STATUS_STARTED          => 1,
        YMS_STATUS_APPROVED         => 2,
        YMS_STATUS_DECLINED         => 3,
        YMS_STATUS_CANCELED         => 4,
        YMS_STATUS_EXPIRED          => 5,
        YMS_STATUS_TO_BE_ARCHIVED   => 6,
        YMS_STATUS_SIGNED           => 7,
    ];

    public function __construct(
        IRootFolder $storage,
        IConfig $config,
        IUserManager $userManager,
        IAccountManager $accountManager,
        SignSessionMapper $mapper,
        IL10N $l
    ) {
        $this->mapper = $mapper;
        $this->storage = $storage;
        $this->accountManager = $accountManager;
        $this->userManager = $userManager;
        $this->l = $l;
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
        $this->temporaryFolder  = rtrim($config->getSystemValue('tempdirectory', rtrim(sys_get_temp_dir(), '/') . '/nextcloudtemp', '/')) . '/';
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
        LogYumiSign::write((is_array($response) ? json_encode($response) : $response), __FUNCTION__);

        $return = [];

        // Input response may be json entity or array or entities
        if (!is_array($response)) {
            $response = json_decode($response, true);
        }

        // Check if this array response is simple or multidimensional
        switch (true) {
            case $subArray && !(array_key_exists(YMS_IDENTIFIER, $response) &&
                array_key_exists(YMS_RESULT, $response) &&
                array_key_exists(YMS_RESPONSE, $response) &&
                array_key_exists(YMS_ERROR, $response)
            ):
                // Throw an exception because the YumiSign response structure is abnormal
                throw new Exception($this->l->t("YumiSign response is invalid; process: \"{$processPrefix}\""), false);
                break;

            case !$subArray && !(array_key_exists(YMS_IDENTIFIER, $response) &&
                array_key_exists(YMS_RESULT, $response) &&
                array_key_exists(YMS_RESPONSE, $response) &&
                array_key_exists(YMS_ERROR, $response)
            ):
                // Multidimensional array
                foreach ($response as $key => $item) {
                    $return = $this->analyseYumiSignResponse($item, true);
                }
                break;

            case (array_key_exists(YMS_IDENTIFIER, $response) &&
                array_key_exists(YMS_RESULT, $response) &&
                array_key_exists(YMS_RESPONSE, $response) &&
                array_key_exists(YMS_ERROR, $response)):
                // Simple array
                // YumiSign returns error or bad result
                // WARNING: this is not an exception but a Business Logic valid response
                if ($response[YMS_ERROR] == true) {
                    $return['code']    = $response[YMS_ERROR]['code'];
                    $return['message'] = $response[YMS_ERROR]['message'];
                    break;
                }
                // Just in case error intel not filled and result is wrong...
                if ($response[YMS_RESULT] == false) {
                    $return['code']    = false;
                    $return['message'] = "Error occurred during process";
                }
                // Here, the response is OK
                $return['code'] = true;
                $return['message'] = "OK";
                break;

            default:
                throw new Exception($this->l->t("Function not implemented: you should contact RCDevs at netadm@rcdevs.com; process: \"{$processPrefix}\""), false);
                break;
        }

        return $return;
    }

    public function asyncExternalMobileSignPrepare($path, $email, $userId, $appUrl, $signatureType)
    {
        $resp = [];

        // list($fileContent, $fileName, $fileSize, $lastModified) = $this->getFile($path, $userId);

        $user = $this->userManager->get($userId);
        $account = $this->accountManager->getAccount($user);
        $sender = $account->getProperty(IAccountManager::PROPERTY_DISPLAYNAME)->getValue();

        // Get current document full path (filesystem)
        $currentUserFolder = $this->storage->getUserFolder($userId);
        $config = new \OC\Config('config/');
        $base_path = $config->getValue('datadirectory');

        // Create a workflow
        $expiryDate = strtotime("+{$this->asyncTimeout} days");
        $curlWorkflow = $this->createWorkflow($this->serverUrl, $this->apiKey, $this->workspaceId, $userId, $base_path . $currentUserFolder->getPath() . DIRECTORY_SEPARATOR, $path, strtolower($signatureType), $expiryDate);
        $workflow = json_decode($curlWorkflow->body);

        // Initialize workflow preferences
        $uniquePreference = ["name" => "WorkflowNotificationCallbackUrlPreference", "value" => "{$appUrl}/webhook"];
        $preferences["preferences"][] = $uniquePreference;

        // Add workflow preferences
        $curlWorkflowPreferences = $this->addWorkflowPreferences($this->serverUrl, $this->apiKey, $this->workspaceId, $workflow->id, json_encode($preferences));

        // Retrieve the secret from YumiSign
        $secret = "";
        $bodyWorkflowPreferences = json_decode($curlWorkflowPreferences->body);
        foreach ($bodyWorkflowPreferences as $key => $subArray) {
            if ($subArray->name && strcasecmp($subArray->name, YMS_WF_SECRET)  === 0) {
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
            $signSession->setFilePath($path);
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
            $resp['code'] = '2';
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
                $yumisignSession->setStatus(YMS_STARTED);
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
                $resp['code'] = 0;
                $resp['message'] = $this->l->t("YumiSign transaction not started or timedout");
                return $resp;
            }
        } else {
            try {
                $signSession = $this->mapper->findTransaction($envelopeId, $recipient);
            } catch (DoesNotExistException $e) {
                $resp['code'] = 0;
                $resp['message'] = $this->l->t("YumiSign transaction not found");
                return $resp;
            }
        }

        if ($signSession->getApplicantId() !== $userId) {
            $resp['code'] = 403;
            $resp['message'] = $this->l->t("YumiSign transaction not found for this user");
            return $resp;
        }

        try {
            // Check on YumiSign only if not "Forcing process"
            // First, check the real state of this YumiSign transaction
            $check = json_decode($this->debriefWorkflow($this->serverUrl, $this->apiKey, $signSession->getWorkspaceId(), $signSession->getWorkflowId())->body, true);
            if (strcasecmp($check[YMS_STATUS], YMS_STATUS_CANCELED) !== 0) {
                $resp = $this->cancelWorkflow($this->serverUrl, $this->apiKey, $signSession->getWorkspaceId(), $signSession->getWorkflowId());
            } else {
                // Transaction already cancelled on YUmiSign server, not on DB (weird...)
                $resp['code'] = true;
                $resp['message'] = YMS_ALREADY_CANCELLED;
                // Update DB status to match with YUmiSign server
                $this->updateAllStatus($envelopeId, YMS_STATUS_CANCELED, time());
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

                $resp['code'] = true;
                $resp['message'] = YMS_DELETED;
            }
        } catch (\Throwable $th) {
            $resp['code']    = $th->getCode();
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

            LogYumiSign::write(json_encode($curlResponse), __FUNCTION__);
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
        LogYumiSign::write("checkAsyncSignature : starting process", __FUNCTION__);

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
                        if (strcasecmp($item['status'], YMS_STATUS_TO_BE_ARCHIVED) !== 0) {
                            // If database does not contain the envelope ID, delete YumiSign transaction on server
                            if ($this->mapper->countTransactionsByEnvelopeId($item['envelopeId']) == 0) {
                                LogYumiSign::write(sprintf("This transaction %s (%s) is missing in DB; should delete it. Status is %s", $item['envelopeId'], $item['name'], $item['status']), __FUNCTION__);
                                $workflows = [];
                                $workflows['workflows'][] = ['id' => $item['id'], 'workspaceId' => $this->workspaceId];

                                // Check if status is cancelled; if not, cancel YumiSign transaction before deletion
                                if (strcasecmp($item['status'], YMS_STATUS_CANCELED) !== 0) {
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
                        LogYumiSign::write("Delete DB {$yumisignSession->getEnvelopeId()}", __FUNCTION__);
                        $this->mapper->deleteTransactions($yumisignSession->getEnvelopeId());
                    }
                }
            } catch (DoesNotExistException $e) {
                // YumiSign transaction table is empty
                // Not needed to bother admins...
            }
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
            LogYumiSign::write(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__);
        }
        LogYumiSign::write("checkAsyncSignature : well done", __FUNCTION__);

        return $curlResponse;
    }

    private function checkCurlBody(CurlResponse $curlResponse, string $functionName)
    {
        if (null === json_decode($curlResponse->body)) {
            $message = sprintf("cURL returned empty body in process \"%s\".", $functionName);
            LogYumiSign::write($message, __FUNCTION__, true);
        }
    }

    private function checkCurlCode(CurlResponse $curlResponse, string $functionName)
    {
        if (($curlResponse->code !== 200) && ($curlResponse->code !== 302)) {
            $message = sprintf("cURL returned unwanted code (%s). This process is skipped for function %s.", $curlResponse->code, $functionName);
            LogYumiSign::write($message, __FUNCTION__, true);
        }
    }

    // Check result validity
    public function checkResultValidity(CurlResponse $curlResponse, string $functionName): array
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Check YumiSign response");

        if (($curlResponse->code !== 200) && ($curlResponse->code !== 302)) {
            $message = sprintf("cURL returned unwanted code (%s). This process is skipped for function %s.", $curlResponse->code, $functionName);
            LogYumiSign::write($message, __FUNCTION__);
            $return['code']    = false;
            $return['message'] = $message;
            return $return;
        }

        if (null === json_decode($curlResponse->body)) throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\""), 1);
        return $this->analyseYumiSignResponse($curlResponse->body);
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
            $return[YMS_ID] = $workspaceId;
            $return[YMS_CODE] = $return[YMS_STATUS] = true;
            $return[YMS_NAME] = $return[YMS_MESSAGE] = $this->l->t("Connected to YumiSign");
        } catch (\Throwable $th) {
            LogYumiSign::write($th->getMessage(), __FUNCTION__);
            LogYumiSign::write(json_encode($curlResponse), __FUNCTION__);
            $return = [];
            $return[YMS_ID] = 0;
            $return[YMS_CODE] = $return[YMS_STATUS]  = false;
            $return[YMS_MESSAGE] = $th->getMessage();
        }

        return $return;
    }

    public function checkWspCommon(array $workspace, string $wksName, string $wksId)
    {
        if (strcasecmp($wksId, '') === 0) {
            return (strcasecmp(Utility::getArrayData($workspace, YMS_NAME, true, true), $wksName) === 0);
        } else {
            return (strcasecmp(Utility::getArrayData($workspace, YMS_NAME, true, true), $wksName) === 0 &&
                Utility::getArrayData($workspace, YMS_ID, true, true) === intval($wksId));
        }
    }

    public function checkWorkspace(IRequest $request)
    {
        $return = [];
        $return[YMS_LIST_ID] = [];
        $return[YMS_ID] = '';

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
                    $return[YMS_CODE] = $return[YMS_STATUS] = true;
                    $return[YMS_NAME] = $return[YMS_MESSAGE] = Utility::getArrayData($workspace, YMS_NAME, true, true);

                    // According to the ID value
                    if (strcasecmp($wksId, '') !== 0) {
                        // Name & ID are filled => found this pair
                        $return[YMS_ID]   = Utility::getArrayData($workspace, YMS_ID, true, true);
                        break;
                    } else {
                        // Just a valid Name but maybe several exist => add to the IDs list
                        $return[YMS_LIST_ID][] = Utility::getArrayData($workspace, YMS_ID, true, true);
                    }
                }
            }

            // If only one ID is found, convert IDs list to unique ID
            if (sizeof($return[YMS_LIST_ID]) === 1) {
                $return[YMS_ID] = $return[YMS_LIST_ID][0];
            }

            if (!$found) LogYumiSign::write(sprintf($this->l->t("The workspace named \"%s\" was not found on YumiSign server"), $wksName), __FUNCTION__);
        } catch (\Throwable $th) {
            LogYumiSign::write($th->getMessage(), __FUNCTION__);
            LogYumiSign::write(json_encode($curlResponse), __FUNCTION__);
            $return = [];
            $return[YMS_ID] = '';
            $return[YMS_LIST_ID] = [];
            $return[YMS_CODE] = $return[YMS_STATUS]  = false;
            $return[YMS_MESSAGE] = $th->getMessage();
        }

        return $return;
    }

    private function createWorkflow(string $serverUrl, string $apiKey, int $workspaceId, string $userId, string $userpath, string $filepath, string $signType, int $expiryDate): CurlResponse
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Creating YumiSign workflow");

        $curlResponse = new CurlResponse();

        try {
            $wfName = pathinfo($userpath . $filepath, PATHINFO_BASENAME) . ' (' . date('Y-m-d_H:i:s') . ')';

            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows", "POST", false);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $post = array(
                'name' => $wfName,
                'document' => new CURLFILE($userpath . $filepath, mime_content_type($userpath . $filepath)),
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

            // Check result validity
            // if (null === json_decode($curlResponse->body)) throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\""), 1);
            // if (isset(json_decode($curlResponse->body)->error))     throw new Exception($this->l->t("Cannot retrieve Workflow ID; process: \"{$processPrefix}\" / code: \"" . json_decode($curlResponse->body)->error->code . "\""), 1);
            // $return = $this->checkResultValidity($curlResponse);
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
            LogYumiSign::write($th->getMessage(), __FUNCTION__);
            LogYumiSign::write(sprintf("Variables : %s / %s / %s", $serverUrl, $apiKey, $workflowsJson), __FUNCTION__);
            LogYumiSign::write(json_encode($curlResponse), __FUNCTION__);
            $curlResponse = new CurlResponse();
            throw $th;
        }

        return $return;
    }

    // Common get response
    private function getCurlResponse($ch): CurlResponse
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
        $workspaceReturned[YMS_CODE]    = false;
        $workspaceReturned[YMS_MESSAGE] = $this->l->t("Workspace not defined.");

        try {
            if (!empty($this->workspaceId))   throw new Exception($this->l->t("Workspace ID is already defined."), 1);
            if (empty($this->workspaceName)) throw new Exception($this->l->t("Workspace name is empty."), 1);

            // YMS Workspace identity
            $wsName = $this->workspaceName;
            $wsDescription = $this->description;

            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces", "GET");

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);
            LogYumiSign::write(json_encode($curlResponse), __FUNCTION__);

            curl_close($ch);

            $found = false;
            foreach (json_decode($curlResponse->body) as $workspace) {
                if ($workspace->name === $wsName && $workspace->description === $wsDescription) {
                    $found = true;
                    $workspaceReturned[YMS_CODE] = true;
                    $workspaceReturned[YMS_ID]   = $workspace->id;
                    $workspaceReturned[YMS_NAME] = $wsName;
                    break;
                }
            }

            // if WS not found, create it
            if (!$found && $createWsp) {
                $curlResponse = $this->createWorkspace($serverUrl, $apiKey, $wsName, $wsDescription);
                if (is_null($curlResponse) || !isset(json_decode($curlResponse->body)->id)) throw new Exception($this->l->t("Cannot retrieve Workspace ID."), 1);
                $response = json_decode($curlResponse->body, true);
                $workspaceReturned[YMS_CODE] = true;
                $workspaceReturned[YMS_ID]   = $response[YMS_ID];
                $workspaceReturned[YMS_NAME] = $response[YMS_NAME];
                $found = true;
            }
        } catch (\Throwable $th) {
            LogYumiSign::write(json_encode($curlResponse), __FUNCTION__);
            $workspaceReturned = [];
            $workspaceReturned[YMS_CODE]    = false;
            $workspaceReturned[YMS_MESSAGE] = $th->getMessage();
        }

        return $workspaceReturned;
    }

    private function isTakingPriorityOver(string $newStatus, string $oldStatus): bool
    {
        return $this->statusPriority[$newStatus] > $this->statusPriority[$oldStatus];
    }

    public function lastJobRun()
    {
        $return = [];
        $wrong = true;

        try {
            $ymsJob = $this->mapper->findJob();
            $reservedAt = Utility::getArrayData($ymsJob, 'reserved_at', false, 'Not Reservation column found in query result');
            $lastRun = Utility::getArrayData($ymsJob, 'last_run', false, 'Not Last Run column found in query result');

            if ($reservedAt !== '' && $reservedAt === 0 && $lastRun !== '') {
                $return[YMS_CODE] = true;
                $return[YMS_STATUS]  = YMS_SUCCESS;
                if ($lastRun !== 0) {
                    $lastRun = date('Y-m-d_H:i:s', $lastRun);
                    $return[YMS_MESSAGE] = $this->l->t('The cron job is activated; the last time the job ran was at %s', [$lastRun]);
                } else {
                    $return[YMS_MESSAGE] = $this->l->t('The cron job is activated');
                }
                // Set the flag
                $wrong = false;
            } elseif ($reservedAt !== '' && $reservedAt !== 0 && $lastRun !== '') {
                $return[YMS_CODE] = false;
                $return[YMS_STATUS]  = YMS_ERROR;
                $reservedAt = date('Y-m-d_H:i:s', $reservedAt);
                $return[YMS_MESSAGE] = $this->l->t('The cron job was disabled at %s', [$reservedAt]);
                // Set the flag
                $wrong = false;
            }

            // In case of error...
            if ($wrong) {
                $now = date('Y-m-d_H:i:s');
                LogYumiSign::write($this->l->t('Checking process failed at %s', [$now]), __FUNCTION__, true);
            }
        } catch (\Throwable $th) {
            $return[YMS_CODE] = false;
            $return[YMS_STATUS]  = YMS_ERROR;
            $return[YMS_MESSAGE] = $th->getMessage();
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
            $return[YMS_CODE] = true;
            $return[YMS_STATUS]  = YMS_SUCCESS;
            $return[YMS_MESSAGE] = $this->l->t('The cron job has been activated at %s', [date('Y-m-d_H:i:s')]);
        } catch (\Throwable $th) {
            $return[YMS_CODE] = false;
            $return[YMS_STATUS]  = YMS_ERROR;
            $return[YMS_MESSAGE] = $th->getMessage();
        }

        return $return;
    }

    public function saveTransactionFiles(array $requestBody)
    {
        try {
            foreach ($requestBody['documents'] as $key => $document) {
                if (array_key_exists('file', $document) && array_key_exists('file', $document['file'])) {
                    $envelopeId = Utility::getArrayData($requestBody, 'id', true, 'YumiSign transaction ID field is missing');
                    try {
                        $yumisignSession = $this->mapper->findTransaction($envelopeId);
                    } catch (DoesNotExistException $e) {
                        $warningMsg = "YumiSign transaction {$envelopeId} not found";
                        LogYumiSign::write($warningMsg, __FUNCTION__);
                        return Utility::warning($warningMsg);
                    }

                    // Only one session in the result
                    if ($yumisignSession->getApplicantId()) $applicantId = $yumisignSession->getApplicantId();
                    if ($yumisignSession->getFilePath())    $filePath    = $yumisignSession->getFilePath();
                    if ($yumisignSession->getWorkflowId())  $workflowId  = $yumisignSession->getWorkflowId();

                    $url = str_replace('\\', '', $document['file']['file']);
                    $originalDirectory           = DIRECTORY_SEPARATOR . trim(pathinfo($filePath, PATHINFO_DIRNAME), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                    $originalFilename            = trim($document['file']['name'], DIRECTORY_SEPARATOR);
                    $originalFilenameNoExtension = pathinfo($originalFilename, PATHINFO_FILENAME);
                    $originalExtension           = pathinfo($originalFilename, PATHINFO_EXTENSION);

                    $ymsUrlArchive = $this->serverUrl . YMS_URL_ARCHIVE;
                    if (
                        strcasecmp(
                            substr($url, 0, strlen($ymsUrlArchive)),
                            $ymsUrlArchive
                        ) !== 0
                    ) throw new Exception("This url does not match to the Archives", 1);

                    // Remove url header ($ymsUrlArchive) and keep workflow ID and Archive file ID
                    $urlEnding = explode('/', substr($url, strlen($ymsUrlArchive)));

                    // Check if workflow IDs match
                    if ($yumisignSession->getWorkflowId()) $workflowId  = $yumisignSession->getWorkflowId();

                    // Create temporary folder if needed
                    if (!is_dir($this->temporaryFolder)) {
                        mkdir($this->temporaryFolder);
                    }

                    // Download file and save it in temporary folder
                    $temporaryFile = "{$this->temporaryFolder}{$urlEnding[2]}_{$applicantId}_{$workflowId}";
                    $fh = fopen($temporaryFile, "w");
                    $ch = $this->setCurlEnv($this->apiKey, $url, "GET", false);
                    curl_setopt($ch, CURLOPT_FILE, $fh);

                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fh);

                    // If file not saved throw exception
                    if (!file_exists($temporaryFile) || filesize($temporaryFile) === 0) throw new Exception("File has not been saved", 1);

                    // Move the temporary file as the final timestamped file in user's folder (near the original file)
                    $config = new \OC\Config('config/');
                    $base_path           = rtrim($config->getValue('datadirectory'), DIRECTORY_SEPARATOR);
                    $currentUserFolder   = $this->storage->getUserFolder($applicantId);
                    $documentFolder      = trim($currentUserFolder->getPath(), DIRECTORY_SEPARATOR);
                    $destinationFullPath = sprintf(
                        '%s%s_YumiSigned_%s.%s',
                        $originalDirectory,
                        $originalFilenameNoExtension,
                        date('Y-m-d_H.i.s'),
                        $originalExtension
                    );

                    // First check if destination folder exists
                    if (!is_dir($base_path . DIRECTORY_SEPARATOR . $documentFolder . $originalDirectory)) throw new Exception("Destination folder does not exist", 1);

                    // Move the downloaded file
                    rename($temporaryFile, $base_path . DIRECTORY_SEPARATOR . $documentFolder . $destinationFullPath);

                    $userFolder = $this->storage->getUserFolder($applicantId);
                    $userFolder->touch($destinationFullPath);
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    // Common function for cURL
    private function setCurlEnv(string $apiKey, string $url, string $request, bool $contentTypeJson = true): CurlHandle
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

        // $timeout = 10;

        // $ch = $this->setCurlEnv($this->apiKey, rtrim($request->getParam('server_url'), '/') . "/workspaces", "GET", false);

        // curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        // // Call YMS server
        // $curlResponse = $this->getCurlResponse($ch);

        // if (($curlResponse->code == "200") || ($curlResponse->code == "302")) {
        //     $returnValue['status'] = "true";
        //     $returnValue['message'] = json_decode($curlResponse->body)[0]->description;
        // } else {
        //     $returnValue = false;
        // }
        // curl_close($ch);

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
                LogYumiSign::write($warningMsg, __FUNCTION__);
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
            LogYumiSign::write($exceptionMsg, __FUNCTION__, true);
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
                LogYumiSign::write($warningMsg, __FUNCTION__);
                throw new Exception($warningMsg, 1);
            }

            $headerdata = explode(',', str_replace('=', ',', $headerYumiSign));

            if (array_key_exists('_route', $requestBody)) unset($requestBody['_route']);

            $envelopeId = Utility::getArrayData($requestBody, 'id',         true, 'YumiSign transaction ID field is missing');
            $status     = Utility::getArrayData($requestBody, 'status',     true, 'YumiSign transaction status field is missing');
            // $createDate = Utility::getArrayData($requestBody, 'createDate', true, 'YumiSign transaction creation date field is missing');
            // $expiryDate = Utility::getArrayData($requestBody, 'expiryDate', true, 'YumiSign transaction expiration date field is missing');
            // $documents  = Utility::getArrayData($requestBody, 'documents',  true, 'YumiSign transaction documents fields are missing');
            // $name       = Utility::getArrayData($requestBody, 'name',       true, 'YumiSign transaction name field is missing');

            $secret = "";

            try {
                $yumisignSession = $this->mapper->findTransaction($envelopeId);
            } catch (DoesNotExistException $e) {
                $warningMsg = "YumiSign transaction {$envelopeId} not found";
                LogYumiSign::write($warningMsg, __FUNCTION__);
                throw new Exception($warningMsg, 1);
            }

            if ($yumisignSession->getApplicantId()) $applicantId = $yumisignSession->getApplicantId();
            if ($yumisignSession->getSecret()) $secret = $yumisignSession->getSecret();
            else {
                $warningMsg = "YumiSign transaction secret not found";
                LogYumiSign::write($warningMsg, __FUNCTION__);
                throw new Exception($warningMsg, 1);
            }

            $payload = $headerdata[1] . "." . json_encode($requestBody);

            // Check if Keys match
            $match = (strcmp(hash_hmac('sha256', $payload, $secret), $headerdata[3]) === 0);

            // KO if not match
            if (!$match) {
                $warningMsg = "YumiSign transaction bad key";
                LogYumiSign::write($warningMsg, __FUNCTION__);
                throw new Exception($warningMsg, 1);
            }

            // Valid transaction, do what you need to do...
            switch ($status) {
                case YMS_STATUS_NOT_STARTED:
                case YMS_STATUS_APPROVED:
                case YMS_STATUS_CANCELED:
                case YMS_STATUS_DECLINED:
                case YMS_STATUS_EXPIRED:
                case YMS_STATUS_TO_BE_ARCHIVED:
                case YMS_STATUS_STARTED:
                    // Update each recipient status
                    $steps = Utility::getArrayData($requestBody, 'steps',       true, 'YumiSign transaction steps fields are missing');
                    foreach ($steps as $step) {
                        foreach ($step['actions'] as $action) {
                            $resp = $this->updateStatus($envelopeId, $action['recipientEmail'], $status, $action['status'], $headerdata[1]);
                        }
                    }
                    break;
                case YMS_STATUS_SIGNED:
                    /**
                     * Only request status is available; the recipients status are not written in this Signed request
                     * So all status are updated as Signed (recipients + global)
                     */
                    $resp = $this->updateAllStatus($envelopeId, $status, $headerdata[1]);
                    // Save the files of this transaction (all recipients have signed)
                    $resp = $this->saveTransactionFiles($requestBody);
                    break;
                default:
                    #code...
                    break;
            }

            $processStatus['code'] = true;
            $processStatus['message'] = "YumiSign transaction {status}";
            $processStatus['status'] = $status;
        } catch (\Throwable $th) {
            $warningMsg = "{$th->getMessage()} / Envelope ID : {$envelopeId}";
            LogYumiSign::write($warningMsg, __FUNCTION__);
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
            LogYumiSign::write($th->getMessage(), __FUNCTION__);
            return Utility::warning($th->getMessage());
        }
    }
}

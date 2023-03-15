<?php

namespace OCA\YumiSignNxtC\Service;

use CURLFile;
use CurlHandle;
use DateInterval;
use DateTime;
use Exception;
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
    private $serverUrls;
    private $apiKey;
    private $defaultDomain;
    private $userSettings;
    private $useProxy;
    private $proxyHost;
    private $proxyPort;
    private $proxyUsername;
    private $proxyPassword;
    private $signScope;
    private $signedFile;
    private $syncTimeout;
    private $asyncTimeout;
    private $enableDemoMode;
    private $watermarkText;
    private $temporaryFolder;
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

        $this->serverUrls       = json_decode($config->getAppValue(YumiSignApp::APP_ID, 'server_urls', '[]'));
        $this->apiKey           = $config->getAppValue(YumiSignApp::APP_ID, 'api_key');
        $this->defaultDomain    = $config->getAppValue(YumiSignApp::APP_ID, 'default_domain');
        $this->userSettings     = $config->getAppValue(YumiSignApp::APP_ID, 'user_settings');
        $this->useProxy         = $config->getAppValue(YumiSignApp::APP_ID, 'use_proxy');
        $this->proxyHost        = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_host')     : false);
        $this->proxyPort        = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_port')     : false);
        $this->proxyUsername    = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_username') : false);
        $this->proxyPassword    = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_password') : false);
        $this->signScope        = $config->getAppValue(YumiSignApp::APP_ID, 'sign_scope', 'qualified');
        $this->signedFile       = $config->getAppValue(YumiSignApp::APP_ID, 'signed_file');
        $this->syncTimeout      = (int) $config->getAppValue(YumiSignApp::APP_ID, 'sync_timeout') * 60;
        $this->asyncTimeout     = (int) $config->getAppValue(YumiSignApp::APP_ID, 'async_timeout'); // in days
        $this->enableDemoMode   = $config->getAppValue(YumiSignApp::APP_ID, 'enable_demo_mode');
        $this->watermarkText    = $config->getAppValue(YumiSignApp::APP_ID, 'watermark_text');
        $this->temporaryFolder  = rtrim($config->getSystemValue('tempdirectory', rtrim(sys_get_temp_dir(), '/') . '/nextcloudtemp', '/')) . '/';
    }

    public function asyncExternalMobileSignPrepare($path, $email, $userId, $appUrl, $signatureType)
    {
        $resp = [];

        $isPdf = str_ends_with(strtolower($path), ".pdf");

        if ($this->enableDemoMode && !$isPdf) {
            $resp['code'] = 0;
            $resp['message'] = $this->l->t("Demo mode enabled. It is only possible to sign PDF files.");
            return $resp;
        }

        list($fileContent, $fileName, $fileSize, $lastModified) = $this->getFile($path, $userId);

        $user = $this->userManager->get($userId);
        $account = $this->accountManager->getAccount($user);
        $sender = $account->getProperty(IAccountManager::PROPERTY_DISPLAYNAME)->getValue();

        $nbServers = count($this->serverUrls);
        for ($i = 0; $i < $nbServers; ++$i) {
            $client = new nusoap_client($this->serverUrls[$i], false, $this->proxyHost, $this->proxyPort, $this->proxyUsername, $this->proxyPassword, self::CNX_TIME_OUT);
            $client->setDebugLevel(0);
            $client->soap_defencoding = 'UTF-8';
            $client->decode_utf8 = FALSE;

            // Get Nextcloud workspace
            $curlWorkspace = $this->getWorkspace($this->serverUrls[$i], $this->apiKey);

            $workspace = json_decode($curlWorkspace->body)[0];
            if (!isset($workspace->id)) {
                throw new Exception($this->l->t("Cannot retrieve Workspace ID."), 1);
            }

            // Get current document full path (filesystem)
            $currentUserFolder = $this->storage->getUserFolder($userId);
            $config = new \OC\Config('config/');
            $base_path = $config->getValue('datadirectory');

            // Create a workflow
            $expiryDate = strtotime("+{$this->asyncTimeout} days");
            $curlWorkflow = $this->createWorkflow($this->serverUrls[$i], $this->apiKey, $workspace->id, $userId, $base_path . $currentUserFolder->getPath() . DIRECTORY_SEPARATOR, $path, strtolower($signatureType), $expiryDate);
            $workflow = json_decode($curlWorkflow->body);

            // Initialize workflow preferences
            $uniquePreference = ["name" => "WorkflowNotificationCallbackUrlPreference", "value" => "{$appUrl}/webhook"];
            $preferences["preferences"][] = $uniquePreference;

            // Add workflow preferences
            $curlWorkflowPreferences = $this->addWorkflowPreferences($this->serverUrls[$i], $this->apiKey, $workspace->id, $workflow->id, json_encode($preferences));

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
            $curlRecipients = $this->addRecipients($this->serverUrls[$i], $this->apiKey, $workspace->id, $workflow->id, json_encode($recipients));

            $roles = json_decode($curlRecipients->body);

            // Retrieve Workflow values and insert data in DB
            $curlDebriefWorkflow = $this->debriefWorkflow($this->serverUrls[$i], $this->apiKey, $workspace->id, $workflow->id);
            $debriefWorkflow = json_decode($curlDebriefWorkflow->body);

            // Save data in DB for each recipient
            foreach ($debriefWorkflow->recipients as $dbwRecipients) {
                // Insert row in DB
                $signSession = new SignSession();
                $signSession->setApplicantId($userId);
                $signSession->setFilePath($path);
                $signSession->setWorkspaceId($workspace->id);
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
            // $roleSteps[] = $roles[0]->roles[0]->id;
            $input["steps"][] = ["sign" => true, "roles" => $roleSteps];

            // Add steps
            $curlSteps = $this->addSteps($this->serverUrls[$i], $this->apiKey, $workspace->id, $workflow->id, json_encode($input));

            $steps = json_decode($curlSteps->body);

            // Add fields
            $curlSession = $this->getSession($this->serverUrls[$i], $this->apiKey, $workspace->id, $workflow->id);

            $session = json_decode($curlSession->body);

            // Add code field
            if (!empty($session->session) && !empty($session->designerUrl)) {
                $resp = json_decode(json_encode($session), true);
                $resp['code'] = '2';
                $resp["error"] = "";
                $resp["message"] = "OpenDesigner";
                $resp["workspaceId"] = $workspace->id;
                $resp["workflowId"] = $workflow->id;
                $resp["envelopeId"] = $workflow->envelopeId;
            } else {
                $resp["code"] = 0;
                $resp["error"] = "Server error";
                $resp["message"] = "Unable to run Sign process with YumiSign";
            }
        }

        return $resp;
    }

    public function asyncExternalMobileSignSubmit($workspaceId, $workflowId, $envelopeId)
    {
        $resp = [];

        $nbServers = count($this->serverUrls);
        for ($i = 0; $i < $nbServers; ++$i) {
            $client = new nusoap_client($this->serverUrls[$i], false, $this->proxyHost, $this->proxyPort, $this->proxyUsername, $this->proxyPassword, self::CNX_TIME_OUT);
            $client->setDebugLevel(0);
            $client->soap_defencoding = 'UTF-8';
            $client->decode_utf8 = FALSE;

            // Start the workflow
            $curlWorkflow = $this->startWorkflow($this->serverUrls[$i], $this->apiKey, $workspaceId, $workflowId);

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
        }

        return $resp;
    }

    public function cancelSignRequest($envelopeId, $userId, $forceDeletion = false, $recipient = '')
    {
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

        // TODO : Remove nbServers because just one...
        $nbServers = count($this->serverUrls);
        for ($i = 0; $i < $nbServers; ++$i) {
            $client = new nusoap_client($this->serverUrls[$i], false, $this->proxyHost, $this->proxyPort, $this->proxyUsername, $this->proxyPassword, self::CNX_TIME_OUT, $this->syncTimeout);
            $client->setDebugLevel(0);
            $client->soap_defencoding = 'UTF-8';
            $client->decode_utf8 = FALSE;

            try {
                // Check on YumiSign only if not "Forcing process"
                if (!$forceDeletion) {
                    // First, check the real state of this YumiSign transaction
                    $check = json_decode($this->debriefWorkflow($this->serverUrls[$i], $this->apiKey, $signSession->getWorkspaceId(), $signSession->getWorkflowId())->body, true);
                    // if (strcasecmp($check[YMS_STATUS], YMS_STATUS_CANCELED) === 0) {
                    //     // Workflow already cancelled so force deletion, just in case...
                    //     $forceDeletion = true;
                    // } else {
                    //     $resp = $this->cancelWorkflow($this->serverUrls[$i], $this->apiKey, $signSession->getWorkspaceId(), $signSession->getWorkflowId());
                    // }
                    if (strcasecmp($check[YMS_STATUS], YMS_STATUS_CANCELED) !== 0) {
                        $resp = $this->cancelWorkflow($this->serverUrls[$i], $this->apiKey, $signSession->getWorkspaceId(), $signSession->getWorkflowId());
                    }
                }

                // Delete DB Issue Request if "Forcing process"
                // if ((isset($resp) && array_key_exists('code', $resp) && $resp['code'] === true) && $forceDeletion) {
                if ($forceDeletion) {
                    $this->mapper->deleteTransactions($envelopeId, $recipient);
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
    }

    public function checkAsyncSignature()
    {
        $nbServers = count($this->serverUrls);
        for ($i = 0; $i < $nbServers; ++$i) {
            $curlResponse = new CurlResponse();
            try {
                // Retrieve Workspace ID
                $curlWorkspace = $this->getWorkspace($this->serverUrls[$i], $this->apiKey);

                $workspace = json_decode($curlWorkspace->body)[0];

                // Get all transactions from YumiSign server
                $ch = $this->setCurlEnv($this->apiKey, "{$this->serverUrls[$i]}/workspaces/{$workspace->id}/workflows", "GET", false);

                // Call YMS server
                $curlResponse = $this->getCurlResponse($ch);

                curl_close($ch);

                // Check retrieved data
                foreach (json_decode($curlResponse->body, true)['items'] as $keyItem => $item) {
                    // Browse each recipient
                    foreach ($item['recipients'] as $keyRecipient => $recipient) {
                        // // Find transcation in DB
                        // try {
                        //     $yumisignSession = $this->mapper->findTransaction($item['envelopeId'], $recipient);
                        // } catch (DoesNotExistException $e) {
                        //     $warningMsg = "YumiSign transaction {$item['envelopeId']} not found for {$recipient}";
                        //     LogYumiSign::write($warningMsg, __FUNCTION__);
                        //     return Utility::warning($warningMsg);
                        // }

                        // Get recipient status
                        $recipientStatus = $item['status'];
                        // Modify it if more intel
                        foreach ($item['pendingActions'] as $keyPendingAction => $pendingAction) {
                            if (strcasecmp($pendingAction['recipientEmail'], $recipient['email']) === 0) {
                                $recipientStatus = $pendingAction['status'];
                                break;
                            }
                        }
                        // Update status
                        $this->updateStatus($item['envelopeId'], $recipient['email'], $item['status'], $recipientStatus);
                    }
                }
            } catch (\Throwable $th) {
                $curlResponse = new CurlResponse();
            }
            return $curlResponse;
        }
    }

    // Get the YumiSign server status (OK/KO) for the Settings page
    public function yumisignStatus(IRequest $request)
    {
        $returnValue = false;

        $timeout = 10;

        $ch = $this->setCurlEnv($this->apiKey, rtrim($request->getParam('server_urls'), '/') . "/workspaces", "GET", false);

        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        // Call YMS server
        $curlResponse = $this->getCurlResponse($ch);

        if (($curlResponse->code == "200") || ($curlResponse->code == "302")) {
            $returnValue['status'] = "true";
            $returnValue['message'] = json_decode($curlResponse->body)[0]->description;
        } else {
            $returnValue = false;
        }
        curl_close($ch);

        return $returnValue;
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

                    if (
                        strcasecmp(
                            substr($url, 0, strlen(YMS_URL_ARCHIVE)),
                            YMS_URL_ARCHIVE
                        ) !== 0
                    ) throw new Exception("This url does not match to the Archives", 1);

                    // Remove url header (YMS_URL_ARCHIVE) and keep workflow ID and Archive file ID
                    $urlEnding = explode('/', substr($url, strlen(YMS_URL_ARCHIVE)));

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

    private function isTakingPriorityOver(string $newStatus, string $oldStatus): bool
    {
        return $this->statusPriority[$newStatus] > $this->statusPriority[$oldStatus];
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

    // Retrieve Nextcloud workspace
    // name : Nextcloud
    // description : YumiSign for Nextcloud
    private function getWorkspace(string $serverUrl, string $apiKey): CurlResponse
    {
        $curlResponse = new CurlResponse();

        // YMS Workspace identity
        $wsName = "Nextcloud";
        $wsDescription = "YumiSign for Nextcloud";

        try {
            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces", "GET");

            // Call YMS server
            $curlResponse = $this->getCurlResponse($ch);

            curl_close($ch);

            $found = false;
            foreach (json_decode($curlResponse->body) as $workspace) {
                if ($workspace->name === $wsName && $workspace->description === $wsDescription) {
                    $found = true;
                    break;
                }
            }

            // if WS not found, create it
            if (!$found) {
                $curlResponse = $this->createWorkspace($serverUrl, $apiKey, $wsName, $wsDescription);
                // $workspace = json_decode($workspaceJSON);
                if (is_null($curlResponse) || !isset(json_decode($curlResponse->body)->id)) {
                    throw new Exception($this->l->t("Cannot retrieve Workspace ID."), 1);
                }
            }
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
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

    private function createWorkflow(string $serverUrl, string $apiKey, int $workspaceId, string $userId, string $userpath, string $filepath, string $signType, int $expiryDate): CurlResponse
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Creating YumiSign workflow");

        $curlResponse = new CurlResponse();

        try {
            $wfName = date('Y-m-d_H:i:s') . '_' . uniqid("", true);

            $ch = $this->setCurlEnv($apiKey, "{$serverUrl}/workspaces/{$workspaceId}/workflows", "POST", false);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $post = array(
                'name' => $wfName,
                'document' => new CURLFILE($userpath . $filepath),
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

    function addWorkflowPreferences(string $serverUrl, string $apiKey, int $workspaceId, int $workflowId, $preferencesJson): CurlResponse
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

    public function testing()
    {
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

            // Check result validity
            $return = $this->checkResultValidity($curlResponse);
        } catch (\Throwable $th) {
            $curlResponse = new CurlResponse();
            throw $th;
        }

        return $return;
    }

    // Check result validity
    public function checkResultValidity(CurlResponse $curlResponse): array
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Check YumiSign response");

        if (null === json_decode($curlResponse->body)) throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\""), 1);
        return $this->analyseYumiSignResponse($curlResponse->body);
    }

    // Management of the YumiSign responses
    public function analyseYumiSignResponse($response, bool $subArray = false): array
    {
        $processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Analyse YumiSign response");

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
                // TODO : Check this
                throw new Exception($this->l->t("Function not implemented: you should contact RCDevs <a href=\"mailto:netadm@rcdevs.com\">here</a>; process: \"{$processPrefix}\""), false);
                break;
        }

        return $return;
    }

    private function randomColorPart()
    {
        return str_pad(dechex(mt_rand(128, 255)), 2, '0', STR_PAD_LEFT);
    }
}

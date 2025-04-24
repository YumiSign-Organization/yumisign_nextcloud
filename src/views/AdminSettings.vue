<!--
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
-->

<template>
	<rcdevsMain>
		<rcdevsSettingsContainer id="rcdevsAppNameYMS">
			<rcdevsSettingsHeader>
				<rcdevsSettingsTitle>{{ getT('YumiSign for Nextcloud Settings') }}</rcdevsSettingsTitle>
				<rcdevsSettingsItem>{{ getT('Installed version') }} : {{ installedVersion }}</rcdevsSettingsItem>
				<rcdevsSettingsItem>{{ getT('Enter your YumiSign server settings in the fields below.') }}</rcdevsSettingsItem>
				<rcdevsSettingsItem>{{ getT('After each settings modification, please save your settings.') }}</rcdevsSettingsItem>
			</rcdevsSettingsHeader>

			<rcdevsSettingsPartsContainer>
				<!-- API key -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('YumiSign API key') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="api_key" ref="apiKey" v-model="apiKey" type="text" name="api_key" maxlength="256" :placeholder="`${placeHolderApiKey}`" />
						<deleteIcon @click="resetValueAndCo('apiKey')">x</deleteIcon>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>

				<!-- Client ID -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('Client ID') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="client_id" ref="clientId" v-model="clientId" type="text" name="client_id" maxlength="256" :placeholder="`${placeHolderclientId}`" />
						<deleteIcon @click="resetValueAndCo('clientId')">x</deleteIcon>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>

				<!-- Client Secret -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('Client secret') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="client_secret" ref="clientSecret" v-model="clientSecret" type="text" name="client_secret" maxlength="256" :placeholder="`${placeHolderclientSecret}`" />
						<deleteIcon @click="resetValueAndCo('clientSecret')">x</deleteIcon>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>

				<!-- Workspace name -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('Workspace name') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="workspace_name" ref="workspaceName" v-model="workspaceName" type="text" name="workspace_name" maxlength="256" :placeholder="`${placeHolderWorkspaceName}`" />
						<deleteIcon @click="resetValueAndCo('workspaceName')">x</deleteIcon>
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsTransition">
						<transition name="fade">
							<img v-if="!reqWspName.enable" class="rcdevsClickable rcdevsStatusTransaction" :src="disableImg" />
							<img v-if="reqWspName.enable && reqWspName.request" class="rcdevsClickable rcdevsStatusTransaction rcdevsStatusLoader" :src="requestImg" />
							<img v-if="reqWspName.enable && !reqWspName.request && reqWspName.status" class="rcdevsClickable rcdevsStatusTransaction" :src="successImg" />
							<img v-if="reqWspName.enable && !reqWspName.request && !reqWspName.status" class="rcdevsClickable rcdevsStatusTransaction" :src="failureImg" />
						</transition>
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsButton">
						<button v-if="workspaceName && !workspaceId" @click="checkWorkspaceName()">
							{{ getT('Verify name') }}
						</button>
						<button v-if="workspaceName && workspaceId" @click="checkNameId()">
							{{ getT('Check Name/ID') }}
						</button>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>

				<!-- Workspace ID -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('Workspace ID') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="workspace_id" ref="workspaceId" v-model="workspaceId" type="text" name="workspace_id" maxlength="256" :placeholder="`${placeHolderWorkspaceName}`" />
						<deleteIcon @click="resetValueAndCo('workspaceId')">x</deleteIcon>
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsTransition">
						<transition name="fade">
							<img v-if="!reqWspId.enable" class="rcdevsClickable rcdevsStatusTransaction" :src="disableImg" />
							<img v-if="reqWspId.enable && reqWspId.request" class="rcdevsClickable rcdevsStatusTransaction rcdevsStatusLoader" :src="requestImg" />
							<img v-if="reqWspId.enable && !reqWspId.request && reqWspId.status" class="rcdevsClickable rcdevsStatusTransaction" :src="successImg" />
							<img v-if="reqWspId.enable && !reqWspId.request && !reqWspId.status" class="rcdevsClickable rcdevsStatusTransaction" :src="failureImg" />
						</transition>
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsButton">
						<button v-if="!workspaceId" @click="retrieveId()">
							{{ getT('Retrieve ID') }}
						</button>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
			</rcdevsSettingsPartsContainer>

			<rcdevsSettingsPartsContainer>
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						<button @click="testConnection()">
							{{ getT('Test connection') }}
						</button>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
			</rcdevsSettingsPartsContainer>
		</rcdevsSettingsContainer>

		<!-- PROXY SETTINGS -->
		<rcdevsSettingsContainer id="rcdevsProxyYMS">
			<rcdevsSettingsPartsContainer>
				<rcdevsSettingsRow>
					<NcCheckboxRadioSwitch class="rcdevsSettingsChkBox" :checked.sync="useProxy" type="switch">{{ getT('Use a proxy') }}</NcCheckboxRadioSwitch>
				</rcdevsSettingsRow>

				<!-- Proxy host -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('Proxy host') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="proxyHost" ref="proxyHost" v-model="proxyHost" type="text" name="proxyHost" maxlength="255" />
						<deleteIcon @click="resetValueAndCo('proxyHost')">x</deleteIcon>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>

				<!-- Proxy Port -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('Proxy port') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="proxy_port" ref="proxyPort" v-model="proxyPort" type="number" name="proxy_port" min="1" max="65535" />
						<deleteIcon @click="resetValueAndCo('proxyPort')">x</deleteIcon>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>

				<!-- Proxy User -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('Proxy username') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="proxy_username" ref="proxyUsername" v-model="proxyUsername" type="text" name="proxy_username" maxlength="255" />
						<deleteIcon @click="resetValueAndCo('proxyUsername')">x</deleteIcon>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>

				<!-- Proxy password -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('Proxy password') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="proxy_password" ref="proxyPassword" v-model="proxyPassword" type="text" name="proxy_password" maxlength="255" />
						<deleteIcon @click="resetValueAndCo('proxyPassword')">x</deleteIcon>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
			</rcdevsSettingsPartsContainer>
		</rcdevsSettingsContainer>

		<!-- SIGNATURES PARAMETERS -->
		<rcdevsSettingsContainer id="rcdevsSignParametersYMS">
			<rcdevsSettingsHeader>
				<rcdevsSettingsTitle>{{ getT('Signatures Parameters') }}</rcdevsSettingsTitle>
			</rcdevsSettingsHeader>

			<rcdevsSettingsPartsContainer>
				<!-- Enable Sign -->
				<rcdevsSettingsRow>
					<NcCheckboxRadioSwitch class="rcdevsSettingsChkBox" :checked.sync="enableSign" type="switch">{{ getT('Enable YumiSign signature') }}</NcCheckboxRadioSwitch>
				</rcdevsSettingsRow>

				<!-- Enable Sign Standard -->
				<rcdevsSettingsRow>
					<NcCheckboxRadioSwitch class="rcdevsSettingsChkBox" :checked.sync="signTypeStandard" type="switch">{{ getT('Enable Simple signature') }}</NcCheckboxRadioSwitch>
				</rcdevsSettingsRow>

				<!-- Enable Sign Advanced -->
				<rcdevsSettingsRow>
					<NcCheckboxRadioSwitch class="rcdevsSettingsChkBox" :checked.sync="signTypeAdvanced" type="switch">{{ getT('Enable Advanced signature') }}</NcCheckboxRadioSwitch>
				</rcdevsSettingsRow>

				<!-- Enable Sign Qualified -->
				<rcdevsSettingsRow>
					<NcCheckboxRadioSwitch class="rcdevsSettingsChkBox" :checked.sync="signTypeQualified" type="switch">{{ getT('Enable Qualified signature') }}</NcCheckboxRadioSwitch>
				</rcdevsSettingsRow>

				<rcdevsSettingsRow>
					<NcCheckboxRadioSwitch class="rcdevsSettingsChkBox" :checked.sync="overwrite" type="switch">{{ getT('Overwrite the original PDF file with its signed copy (default: time-stamped copy)') }}</NcCheckboxRadioSwitch>
				</rcdevsSettingsRow>

				<!-- Textual Complements : Signed -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						{{ getT('Textual complement of signed file') }}
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="textualComplementSignYMS" ref="textualComplementSign" v-model="textualComplementSign" type="text" :name="textualComplementSignYMS" maxlength="66" :placeholder="`${placeHolderTextualComplementSign}`" />
						<deleteIcon @click="resetValueAndCo('textualComplementSign')">x</deleteIcon>
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="templateFilename">
						<label>{{ getTemplateFilenameSign }}</label>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
			</rcdevsSettingsPartsContainer>
		</rcdevsSettingsContainer>

		<rcdevsSettingsContainer id="rcdevsCronYMS">
			<rcdevsSettingsHeader>
				<rcdevsSettingsTitle>{{ getT('Cron Parameters') }}</rcdevsSettingsTitle>
			</rcdevsSettingsHeader>

			<rcdevsSettingsPartsContainer>
				<rcdevsSettingsRow>
					{{ getT('To check asynchronous signature requests, you need to define the execution frequency of the background task that checks the status of these signatures.') }}
				</rcdevsSettingsRow>
				<rcdevsSettingsRow class="rcdevsRedNote">
					{{ getT("Please note that for this periodicity to be honored, it is necessary to configure Nextcloud background jobs setting with 'Cron' value and to define the crontab periodicity accordingly.") }}
				</rcdevsSettingsRow>
				<rcdevsSettingsRow class="rcdevsReadMore">
					&#10132; <a href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/background_jobs_configuration.html#cron" target="_blank">{{ getT('More information here') }}</a>
				</rcdevsSettingsRow>
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel"> {{ getT('Periodicity') }} (mns) </rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<input id="cron_interval" ref="cronInterval" v-model="cronInterval" type="number" name="cron_interval" :min="MIN_CRON_INTERVAL" :max="MAX_CRON_INTERVAL" />
						<deleteIcon @click="resetValueAndCo('cronInterval')">x</deleteIcon>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel"> {{ getT('Checking Cron') }}</rcdevsSettingsItem>
					<rcdevsSettingsItem id="cron_check" name="cron_check" class="rcdevsSettingsInput" :class="[reqCron.status]">
						{{ reqCron.message }}
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
				<rcdevsSettingsRow class="rcdevsSettingsButton">
					<button v-if="reqCron.code === 0" @click="reset_job()">
						{{ getT('Re-enable cron') }}
					</button>
				</rcdevsSettingsRow>
			</rcdevsSettingsPartsContainer>

			<rcdevsSettingsFooter>
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsLabel">
						<button @click="saveSettings">
							{{ getT('Save') }}
						</button>
					</rcdevsSettingsItem>
					<rcdevsSettingsItem class="rcdevsSettingsInput">
						<transition name="fade">
							<p v-if="!saved" class="save_warning">
								{{ getT('Do not forget to save your settings!') }}
							</p>
							<p v-if="success" id="save_success">
								{{ getT('Your settings have been saved succesfully') }}
							</p>
							<p v-if="failure" id="save_failure">
								{{ getT('There was an error saving settings') }}
							</p>
						</transition>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
			</rcdevsSettingsFooter>
		</rcdevsSettingsContainer>

		<!-- Modal form for Workspaces IDs -->
		<WorkspaceListIds ref="WorkspaceListIds" @closed="closedEvent" />
	</rcdevsMain>
</template>

<script>
import {appName} from '../javascript/config.js';
import {generateFilePath, generateOcsUrl} from '@nextcloud/router';
import {getBasename, getOcsUrl, getT, isEmail, isEnabled, isValidResponse, log} from '../javascript/utility';
import {loadState} from '@nextcloud/initial-state';
import axios from '@nextcloud/axios';
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js';
import WorkspaceListIds from '../components/WorkspaceListIds.vue';

const reqCron = {
	code: 0,
	enable: false,
	message: '',
	request: false,
	status: 'chk_0',
};
const reqServerUrl = {
	code: 0,
	enable: true,
	message: '',
	request: false,
	status: 'chk_0',
};
const reqWspId = {
	code: 0,
	enable: false,
	id: 0,
	message: '',
	request: false,
	status: 'chk_0',
};
const reqWspName = {
	code: 0,
	enable: false,
	id: 0,
	message: '',
	request: false,
	status: 'chk_0',
};

export default {
	name: 'AdminSettings',
	components: {
		NcCheckboxRadioSwitch,
		WorkspaceListIds,
	},

	data() {
		this.apis = [];
		this.apis.settingsCheck = '/settings/check';
		this.apis.settingsCheckCron = '/settings/check/cron';
		this.apis.settingsCheckWorkspaceId = '/settings/check/workspace/id';
		this.apis.settingsCheckWorkspaceName = '/settings/check/workspace/name';
		this.apis.settingsJobReset = '/settings/job/reset';
		this.apis.settingsSave = '/settings/save';

		return {
			getT: getT,

			axiosChecking: {
				abortCtrl: null,
				inProgress: false,
				success: false,
				error: false,
				message: null,
			},

			reqCron,
			reqServerUrl,
			reqWspId,
			reqWspName,

			// From DB table Settings `oc_appconfig`
			apiKey: this.$parent.apiKey,
			asyncTimeout: this.$parent.asyncTimeout,
			clientId: this.$parent.clientId,
			clientSecret: this.$parent.clientSecret,
			cronInterval: this.$parent.cronInterval,
			description: this.$parent.description,
			enableSign: this.$parent.enableSign,
			installedVersion: this.$parent.installedVersion,
			overwrite: this.$parent.overwrite,
			proxyHost: this.$parent.proxyHost,
			proxyPassword: this.$parent.proxyPassword,
			proxyPort: this.$parent.proxyPort,
			proxyUsername: this.$parent.proxyUsername,
			signTypeAdvanced: this.$parent.signTypeAdvanced,
			signTypeQualified: this.$parent.signTypeQualified,
			signTypeStandard: this.$parent.signTypeStandard,
			textualComplementSign: this.$parent.textualComplementSign,
			useProxy: this.$parent.useProxy,
			workspaceId: this.$parent.workspaceId,
			workspaceName: this.$parent.workspaceName,

			success: false,
			failure: false,
			saved: false,
			MIN_TIMEOUT: 1,
			MAX_SYNC_TIMEOUT: 5,
			MAX_ASYNC_TIMEOUT: 30,
			MIN_CRON_INTERVAL: 1,
			MAX_CRON_INTERVAL: 15,

			templateFilenameSign: '',
		};
	},

	computed: {
		getTemplateFilenameSign: function () {
			return this.changeTemplateFilename(this.textualComplementSign);
		},
	},

	beforeMount() {
		const initialSettings = loadState(appName, 'initialSettings');

		console.log(`initialSettings:[${JSON.stringify(initialSettings)}]`);

		this.apiKey = initialSettings.apiKey;
		this.asyncTimeout = initialSettings.asyncTimeout;
		this.clientId = initialSettings.clientId;
		this.clientSecret = initialSettings.clientSecret;
		this.cronInterval = initialSettings.cronInterval;
		this.description = initialSettings.description;
		this.enableSign = initialSettings.enableSign;
		this.installedVersion = initialSettings.installedVersion;
		this.overwrite = initialSettings.overwrite;
		this.proxyHost = initialSettings.proxyHost;
		this.proxyPassword = initialSettings.proxyPassword;
		this.proxyPort = initialSettings.proxyPort;
		this.proxyUsername = initialSettings.proxyUsername;
		this.signTypeAdvanced = initialSettings.signTypeAdvanced;
		this.signTypeQualified = initialSettings.signTypeQualified;
		this.signTypeStandard = initialSettings.signTypeStandard;
		this.textualComplementSign = initialSettings.textualComplementSign;
		this.useProxy = initialSettings.useProxy;
		this.workspaceId = initialSettings.workspaceId;
		this.workspaceName = initialSettings.workspaceName;
	},

	mounted() {
		this.loadingImg = generateFilePath(appName, '', 'img/') + 'YumiSign.svg';
		this.requestImg = generateFilePath(appName, '', 'img/') + 'YumiSign_gray.svg';
		this.successImg = generateFilePath(appName, '', 'img/') + 'YumiSign_green.svg';
		this.failureImg = generateFilePath(appName, '', 'img/') + 'YumiSign_red.svg';
		this.disableImg = generateFilePath(appName, '', 'img/') + 'YumiSign_disabled.svg';

		this.reqCron = {
			checked: false,
			code: 0,
			enable: false,
			message: '',
			request: false,
			status: 'chk_0',
		};
		this.reqServerUrl = {
			checked: false,
			code: 0,
			enable: true,
			message: '',
			request: false,
			status: 'chk_0',
		};
		this.reqWspId = {
			checked: false,
			code: 0,
			enable: false,
			id: 0,
			message: '',
			request: false,
			status: 'chk_0',
		};
		this.reqWspName = {
			checked: false,
			code: 0,
			enable: false,
			id: 0,
			message: '',
			request: false,
			status: 'chk_0',
		};

		this.placeHolderApiKey = this.getT('Get API Key from RCDevs');
		this.placeHolderclientId = this.getT('Optional, get from RCDevs');
		this.placeHolderclientSecret = this.getT('Optional, get from RCDevs');
		this.placeHolderWorkspaceName = this.getT('Set a Workspace name');

		// Add Event Listener on all inputs
		const inputs = document.querySelectorAll('input');
		inputs.forEach((input) => {
			input.addEventListener('change', this.inputNotSaved);
		});

		// Add Event listener on NcCheckboxRadioSwitch (FYI, focus on main generated span tag to check if radio is checked or not: the radio does not throw an event)
		const attrObserver = new MutationObserver((mutations) => {
			mutations.forEach((mu) => {
				if (mu.type === 'attributes' && mu.attributeName === 'class') {
					this.inputNotSaved();
				}
			});
		});

		const ELS_test = document.querySelectorAll('.rcdevsSettingsChkBox');
		ELS_test.forEach((el) => attrObserver.observe(el, {attributes: true}));

		document.querySelectorAll('.rcdevsSettingsChkBox').forEach((btn) => {
			btn.addEventListener('click', () => ELS_test.forEach((el) => el.classList.toggle(btn.dataset.class)));
		});

		this.saved = true;

		// Call server check
		this.testConnection();

		// Check if Cron is enabled or disabled
		this.retrieveCronStatus();
	},

	methods: {
		axiosSettingsCheck: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to check settings (connection status)`);

				axios
					.get(getOcsUrl(this.apis.settingsCheck), {
						signal: this.axiosChecking.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results
						if (!isValidResponse(response)) {
							throw new Error('Connection check failed');
						}
						this.axiosChecking.success = true;
						// Apply values
						this.reqServerUrl.code = response.data.code;
						this.reqServerUrl.enable = response.data.status;
						this.reqServerUrl.message = response.data.message;
						this.reqServerUrl.status = `chk_${response.data.code}`;

						this.reqWspId.code = response.data.code;
						this.reqWspId.enable = response.data.status;
						this.reqWspId.id = response.data.id;
						this.reqWspId.status = response.data.status;

						this.reqWspName.code = response.data.code;
						this.reqWspName.enable = response.data.status;
						this.reqWspName.id = response.data.id;
						this.reqWspName.status = response.data.status;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = true;
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;

						this.reqServerUrl.request = false;
						this.reqWspId.request = false;
						this.reqWspName.request = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSettingsCheckCron: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to check settings (cron)`);

				axios
					.get(getOcsUrl(this.apis.settingsCheckCron), {
						signal: this.axiosChecking.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results
						if (!isValidResponse(response)) {
							throw new Error('Cron check failed');
						}
						this.axiosChecking.success = true;
						// Apply values
						this.reqCron.enable = true;
						this.reqCron.code = response.data.code;
						this.reqCron.message = response.data.message;
						this.reqCron.status = `chk_${response.data.code}`;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = true;
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;

						this.reqCron.request = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSettingsCheckWorkspaceId: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to check settings (workspace ID)`);

				axios
					.get(getOcsUrl(this.apis.settingsCheckWorkspaceId), {
						signal: this.axiosChecking.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results
						if (!response.data.hasOwnProperty('id')) {
							throw new Error('YumiSign workspace ID is missing');
						}
						this.axiosChecking.success = true;
						// Apply values
						this.reqWspId.enable = true;
						this.reqWspId.status = response.data.status;
						this.reqWspName.code = response.data.code;
						this.reqWspName.enable = true;
						this.reqWspName.id = response.data.id;
						this.reqWspName.message = response.data.message;
						this.reqWspName.status = response.data.status;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = true;
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;

						this.reqWspId.request = false;
						this.reqWspName.request = false;
						this.reqWspName.checked = true;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSettingsCheckWorkspaceName: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to check settings (workspace name)`);

				axios
					.get(getOcsUrl(this.apis.settingsCheckWorkspaceName), {
						signal: this.axiosChecking.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results
						if (!response.data.hasOwnProperty('id')) {
							throw new Error('YumiSign workspace ID is missing');
						}
						this.axiosChecking.success = true;
						// Apply values
						this.reqWspName.enable = true;
						this.reqWspName.code = response.data.code;
						this.reqWspName.id = response.data.id;
						this.reqWspName.message = response.data.message;
						this.reqWspName.status = response.data.status;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = true;
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;
						this.reqWspName.request = false;

						this.reqWspName.checked = true;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSettingsRetrieveWorkspaceId: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to retrieve settings (workspace ID)`);

				axios
					.get(getOcsUrl(this.apis.settingsCheckWorkspaceId), {
						signal: this.axiosChecking.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results
						if (!response.data.hasOwnProperty('id')) {
							throw new Error('YumiSign workspace ID is missing');
						}
						this.axiosChecking.success = true;
						// Apply values
						this.reqWspId.code = response.data.code;
						this.reqWspId.id = response.data.id;
						this.reqWspId.listId = response.data.listId;

						// Show modal if needed.
						if (this.reqWspId.listId.length > 1) {
							this.showWorkspaceListIds(this.reqWspId.listId);
						} else if (this.reqWspId.listId.length === 1) {
							this.reqWspName.status = response.data.status;
							this.reqWspId.enable = true;
						}

						this.reqWspId.message = response.data.message;
						this.reqWspId.status = response.data.status;

						this.workspaceId = response.data.id;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = true;
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;

						this.reqWspId.request = false;
						this.reqWspName.request = false;
						this.reqWspName.checked = true;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSettingsJobReset: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to reset job`);

				axios
					.get(getOcsUrl(this.apis.settingsJobReset), {
						signal: this.axiosChecking.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results
						if (!isValidResponse(response)) {
							throw new Error('Job reset failed');
						}
						this.axiosChecking.success = true;
						// Apply values
						this.reqCron.enable = true;
						this.reqCron.code = response.data.code;
						this.reqCron.message = response.data.message;
						this.reqCron.status = `chk_${response.data.code}`;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = true;
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;

						this.reqCron.request = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSettingsSave: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to save settings`);

				axios
					.post(getOcsUrl(this.apis.settingsSave), {
						api_key: this.apiKey,
						async_timeout: this.asyncTimeout,
						client_id: this.clientId,
						client_secret: this.clientSecret,
						cron_interval: this.cronInterval,
						description: this.description,
						enable_sign: this.enableSign,
						overwrite: this.overwrite,
						proxy_host: this.proxyHost,
						proxy_password: this.proxyPassword,
						proxy_port: this.proxyPort,
						proxy_username: this.proxyUsername,
						sign_type_advanced: this.signTypeAdvanced,
						sign_type_qualified: this.signTypeQualified,
						sign_type_standard: this.signTypeStandard,
						textual_complement_sign: this.textualComplementSign,
						use_proxy: this.useProxy,
						workspace_id: this.workspaceId,
						workspace_name: this.workspaceName,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results
						if (!isValidResponse(response)) {
							throw new Error('Saving failed');
						}
						this.axiosChecking.success = true;
						// Apply values
						this.success = true;
						this.saved = true;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = true;
						// Apply values
						this.failure = true;
						this.saved = false;
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		changeTemplateFilename(textualComplement) {
			let rightNow = Math.floor(Date.now() / 1000);
			const contract = getT('Contract');

			if (textualComplement === '') {
				return `${contract}_${this.formatMe(new Date(rightNow * 1000))}.pdf`;
			} else {
				return `${contract}_${textualComplement}_${this.formatMe(new Date(rightNow * 1000))}.pdf`;
			}
		},

		checkNameId() {
			this.reqWspName.enable = true;
			this.reqWspName.request = true;
			this.reqWspId.enable = true;
			this.reqWspId.request = true;

			this.axiosSettingsCheckWorkspaceId();
		},

		checkWorkspaceName() {
			this.reqWspName.enable = true;
			this.reqWspName.request = true;

			this.axiosSettingsCheckWorkspaceName();
		},

		clearIcons() {
			this.reqWspName.enable = false;
			this.reqWspId.enable = false;
			this.reqServerUrl.enable = false;
		},

		dateHelperFactory() {
			const padZero = (val, len = 2) => `${val}`.padStart(len, `0`);
			const setValues = (date) => {
				let vals = {
					yyyy: date.getFullYear(),
					m: date.getMonth() + 1,
					d: date.getDate(),
					h: date.getHours(),
					mi: date.getMinutes(),
					s: date.getSeconds(),
				};
				Object.keys(vals)
					.filter((k) => k !== `yyyy`)
					.forEach((k) => (vals[k[0] + k] = padZero(vals[k], (k === `ms` && 3) || 2)));
				return vals;
			};

			return (date) => ({
				values: setValues(date),
				toArr(...items) {
					return items.map((i) => this.values[i]);
				},
			});
		},

		formatMe(date) {
			const dateHelper = this.dateHelperFactory();
			const vals = `yyyy,mm,dd,hh,mmi,ss`.split(`,`);
			const myDate = dateHelper(date).toArr(...vals);
			return `${myDate.slice(0, 3).join(`-`)} ${myDate.slice(3, 6).join(`:`)}.${myDate.slice(-1)[0]}`;
		},

		getFunctionName: function () {
			const error = new Error();
			const stackLines = error.stack.split('\n');
			// The stack trace format can vary; you may need to adjust the index
			const callerLine = stackLines[2].trim();
			const functionName = callerLine.split(' ')[1];
			return functionName;
		},

		initAxios: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				let currentAxios = {};
				currentAxios.abortCtrl = new AbortController();
				currentAxios.inProgress = true;
				currentAxios.error = false;
				currentAxios.message = null;

				return currentAxios;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		inputNotSaved(event) {
			this.saved = false;
		},

		reset_job() {
			this.reqCron.enable = true;
			this.reqCron.request = true;

			this.axiosSettingsJobReset();
		},

		resetValueAndCo(refData) {
			this[refData] = '';
			this.$refs[refData].focus();

			this.saved = false;
		},

		retrieveCronStatus() {
			this.reqCron.enable = true;
			this.reqCron.request = true;

			this.axiosSettingsCheckCron();
		},

		retrieveId() {
			this.reqWspId.enable = true;
			this.reqWspId.request = true;

			this.axiosSettingsRetrieveWorkspaceId();
		},

		saveSettings() {
			this.success = false;
			this.failure = false;

			if (this.asyncTimeout < this.MIN_TIMEOUT || this.asyncTimeout > this.MAX_ASYNC_TIMEOUT || this.cronInterval < this.MIN_CRON_INTERVAL || this.cronInterval > this.MAX_CRON_INTERVAL) {
				this.failure = true;
				return;
			}

			this.axiosSettingsSave();
		},

		async showWorkspaceListIds(listId) {
			const ok = await this.$refs.WorkspaceListIds.show({
				title: this.getT('Choose workspace ID'),
				message: t(appName, 'The given workspace name corresponds to several Ids. Please click on the correct ID.'),
				items: listId,
				cancelButton: this.getT('Close'),
				okButton: '',
				updateId: this.updateId,
			});

			if (ok) {
				// eslint-disable-next-line
				console.log('OK');
			}
		},

		testConnection() {
			this.reqServerUrl.enable = true;
			this.reqServerUrl.request = true;
			this.reqWspId.enable = true;
			this.reqWspId.request = true;
			this.reqWspName.enable = true;
			this.reqWspName.request = true;

			this.axiosSettingsCheck();
		},

		updateId(wspId) {
			this.workspaceId = wspId;
		},
	},
};
</script>

<style>
@import '../styles/yumisignRoot.css';
@import '../styles/rcdevsSettings.css';
@import '../styles/rcdevsStyle.css';
@import '../styles/rcdevsUtility.css';
</style>

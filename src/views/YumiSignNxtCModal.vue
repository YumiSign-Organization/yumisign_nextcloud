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
	<NcModal class="rcdevsModalYMS" :show.sync="modal" @close="closeModal" :outTransition="true" :aria-label="ncModalAriaLabel">
		<rcdevsModalContent ref="rcdevsModalFormYMS">
			<rcdevsLogo ref="rcdevsLogoYMS">
				<svg id="YumiSignLogo" :data-src="ui.pictures.applicationLogo"></svg>
			</rcdevsLogo>

			<rcdevsModalMainContainer>
				<rcdevsModalHeader>
					<rcdevsRow class="chosenFile">
						<rcdevsItem class="filenameLabel">
							{{ file.message }}
						</rcdevsItem>
					</rcdevsRow>

					<rcdevsRow class="chosenFile">
						<rcdevsItem class="filenameValue">
							{{ file.basename }}
						</rcdevsItem>
					</rcdevsRow>
				</rcdevsModalHeader>

				<rcdevsSettingsKO v-if="actionSign && disabledSign && enabledApp.checked" class="alert alertDanger disabledAction">
					{{ ui.messages.warning.disabled.sign }}
				</rcdevsSettingsKO>

				<rcdevsWaitingOcs v-if="!rcdevsSettings.checked || !enabledApp.checked || !signTypes.checked">
					<img :src="ui.pictures.loadingImg" />
				</rcdevsWaitingOcs>

				<rcdevsSignProcess v-if="enabledSign && actionSign">
					<rcdevsRecipientsChoices v-if="!axiosSrvRequest.inProgress && actionSign && !file.signed">
						<recipientsSignleChoice v-on:click="changeNcSelectvalue(constantes.self.label)">
							<NcCheckboxRadioSwitch v-if="!selfDisabled" :checked.sync="recipientType" :value="constantes.self.label" :disabled="selfDisabled" name="rcdevsRecipientRadioYMS" type="radio">
								{{ constantes.self.value }}
							</NcCheckboxRadioSwitch>
							<DisplaySelfEmail>
								<input type="text" name="" :value="currentUserFullData" disabled />
							</DisplaySelfEmail>
						</recipientsSignleChoice>

						<recipientsSignleChoice v-on:click="changeNcSelectvalue(constantes.nextcloud)">
							<NcCheckboxRadioSwitch :checked.sync="recipientType" :value="constantes.nextcloud" name="rcdevsRecipientRadioYMS" type="radio">
								{{ ui.messages.filenameMessage.nextcloudUser }}
							</NcCheckboxRadioSwitch>
							<SelectNextcloudUsers>
								<NcSelect id="rcdevsUsersFiltered" v-bind="usersListProps" v-model="usersListProps.value" @search="search" :no-wrap="true" />
								<!-- <SearchResults id="rcdevsSearchResultsYMS" v-if="user !== ''" :search-text="user" :search-results="userResults" :entries-loading="usersLoading" :no-results="noUserResults" :scrollable="true" :selectable="true" @click="addUser" /> -->
							</SelectNextcloudUsers>
						</recipientsSignleChoice>

						<recipientsSignleChoice v-on:click="changeNcSelectvalue(constantes.email)">
							<NcCheckboxRadioSwitch :checked.sync="recipientType" :value="constantes.email" name="rcdevsRecipientRadioYMS" type="radio">
								{{ ui.messages.filenameMessage.email }}
							</NcCheckboxRadioSwitch>
							<InputUsersEmails>
								<input id="rcdevsEmailsList" ref="emailsList" v-model="emailsList" type="text" :name="rcdevsEmailsList" :placeholder="`${ui.messages.placeHolderEmailsList}`" />
							</InputUsersEmails>
						</recipientsSignleChoice>
					</rcdevsRecipientsChoices>

					<rcdevsRow id="rcdevsMessageBanner">
						<rcdevsInProgress v-if="axiosSrvRequest.inProgress">
							<img :src="ui.pictures.loadingImg" />
						</rcdevsInProgress>

						<rcdevsSuccess v-if="axiosSrvRequest.success">
							<rcdevsMessageBanner type="success">
								<rcdevsIconMessage type="success" />
								<rcdevsCuteMessage>
									{{ axiosSrvRequest.message }}
								</rcdevsCuteMessage>
							</rcdevsMessageBanner>
							<rcdevsSuccessTick v-if="axiosSrvRequest.success && file.signed" class="rcdevsSuccessTick">
								<img id="rcdevsSuccessTick" :src="ui.pictures.successTick" />
							</rcdevsSuccessTick>
						</rcdevsSuccess>

						<rcdevsError v-if="axiosSrvRequest.error">
							<rcdevsMessageBanner type="error">
								<rcdevsIconMessage type="error" />
								<rcdevsCuteMessage>
									{{ axiosSrvRequest.message }}
								</rcdevsCuteMessage>
							</rcdevsMessageBanner>
						</rcdevsError>
					</rcdevsRow>
				</rcdevsSignProcess>

				<rcdevsModalFooter class="rcdevsModalFooterYMS">
					<rcdevsRow>
						<hr class="rcdevsSeparator" />
					</rcdevsRow>
					<rcdevsRow v-if="enabledSign && !file.signed">
						<rcdevsSignaturesTypes>
							<!-- <rcdevsSignType v-if="enabledSign && signTypes.standard.enabled" :disabled="axiosSrvRequest.inProgress"> -->
							<rcdevsSignType v-bind:class="disabledSign || !signTypes.standard.enabled || signatureType !== constantes.signType.standard.value ? 'signDisabled' : ''">
								<NcCheckboxRadioSwitch :checked.sync="signatureType" :disabled="axiosSrvRequest.inProgress || disabledSign || !signTypes.standard.enabled" :value="constantes.signType.standard.value" name="rcdevsSignatureTypeRadioYMS" type="radio">
									<rcdevsItemsGrp>
										<rcdevsItem class="rcdevsLabel">{{ signTypes.standard.label }}</rcdevsItem>
										<rcdevsItem class="rcdevsAdditional">{{ signTypes.standard.additional }}</rcdevsItem>
									</rcdevsItemsGrp>
								</NcCheckboxRadioSwitch>
							</rcdevsSignType>

							<!-- <rcdevsSignType v-if="enabledSign && signTypes.advanced.enabled" :disabled="axiosSrvRequest.inProgress"> -->
							<rcdevsSignType v-bind:class="disabledSign || !signTypes.advanced.enabled || signatureType !== constantes.signType.advanced.value ? 'signDisabled' : ''">
								<NcCheckboxRadioSwitch :checked.sync="signatureType" :disabled="axiosSrvRequest.inProgress || disabledSign || !signTypes.advanced.enabled" :value="constantes.signType.advanced.value" name="rcdevsSignatureTypeRadioYMS" type="radio">
									<rcdevsItemsGrp>
										<rcdevsItem class="rcdevsLabel">{{ signTypes.advanced.label }}</rcdevsItem>
										<rcdevsItem class="rcdevsAdditional">{{ signTypes.advanced.additional }}</rcdevsItem>
									</rcdevsItemsGrp>
								</NcCheckboxRadioSwitch>
							</rcdevsSignType>

							<!-- <rcdevsSignType v-if="enabledSign && signTypes.qualified.enabled" :disabled="axiosSrvRequest.inProgress"> -->
							<rcdevsSignType v-bind:class="disabledSign || !signTypes.qualified.enabled || signatureType !== constantes.signType.qualified.value ? 'signDisabled' : ''">
								<NcCheckboxRadioSwitch :checked.sync="signatureType" :disabled="axiosSrvRequest.inProgress || disabledSign || !signTypes.qualified.enabled" :value="constantes.signType.qualified.value" name="rcdevsSignatureTypeRadioYMS" type="radio">
									<rcdevsItemsGrp>
										<rcdevsItem class="rcdevsLabel">{{ signTypes.qualified.label }}</rcdevsItem>
										<rcdevsItem class="rcdevsAdditional">{{ signTypes.qualified.additional }}</rcdevsItem>
									</rcdevsItemsGrp>
								</NcCheckboxRadioSwitch>
							</rcdevsSignType>
						</rcdevsSignaturesTypes>
					</rcdevsRow>
					<rcdevsRow class="rcdevsButtonsYMS">
						<button type="button" @click="closeModal" class="closeModal">
							{{ ui.button.close }}
						</button>

						<button type="button" @click="runTransactionSignature()" class="actionModal">
							{{ ui.button.send }}
						</button>
					</rcdevsRow>
				</rcdevsModalFooter>
			</rcdevsModalMainContainer>
		</rcdevsModalContent>
	</NcModal>
</template>

<style>
@import '../styles/yumisignRoot.css';
@import '../styles/rcdevsStyle.css';
@import '../styles/rcdevsUtility.css';
@import '../styles/yumisignStyle.css';
</style>

<script>
import {appName, modalName, signAction, signatureLabel, signatureLabelFull, uiTitleSign} from '../javascript/config.js';
import {emit} from '@nextcloud/event-bus';
import {File, Permission} from '@nextcloud/files';
import {generateFilePath, generateRemoteUrl, generateUrl} from '@nextcloud/router';
import {getBasename, getOcsUrl, getT, isEmail, isEnabled, isIssueResponse, isValidResponse, log} from '../javascript/utility';
import {getCurrentUser} from '@nextcloud/auth';
import axios from '@nextcloud/axios';
import debounce from 'debounce';
import ListItemIcon from '@nextcloud/vue/dist/Components/NcListItemIcon.js';
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js';
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js';
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js';
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js';
import SearchResults from '../components/Search/SearchResults.vue';

export default {
	name: modalName,
	components: {
		ListItemIcon,
		NcCheckboxRadioSwitch,
		NcModal,
		NcSelect,
		NcTextField,
		SearchResults,
	},

	data() {
		this.apis = [];
		this.apis.settingsCheck = '/settings/check';
		this.apis.settingsCheckTypes = '/settings/check/types';
		this.apis.settingsCheckApp = '/settings/check/app';
		this.apis.signLocalAsync = '/sign/local/async';
		this.apis.userEmail = '/user/email';
		this.apis.userId = '/user/id';
		this.apis.usersAll = '/users/all';

		this.constantes = [];
		this.constantes.self = [];
		this.constantes.self.label = 'self';
		this.constantes.self.value = getT('Self-signed');
		this.constantes.nextcloud = 'nextcloud';
		this.constantes.email = 'email';

		this.constantes.signType = [];
		// Advanced
		this.constantes.signType.advanced = [];
		this.constantes.signType.advanced.additional = getT('For internal and B2B documents');
		this.constantes.signType.advanced.label = getT('Advanced signature');
		this.constantes.signType.advanced.value = 'advanced';
		// Qualified
		this.constantes.signType.qualified = [];
		this.constantes.signType.qualified.additional = getT('For legal documents');
		this.constantes.signType.qualified.label = getT('Qualified signature');
		this.constantes.signType.qualified.value = 'qualified';
		// Standard
		this.constantes.signType.standard = [];
		this.constantes.signType.standard.additional = getT('For most documents');
		this.constantes.signType.standard.label = getT('Standard signature');
		this.constantes.signType.standard.value = 'standard';

		this.ui = [];

		this.ui.axios = [
			{
				requestCancelled: getT('Transaction canceled'),
			},
		];

		this.ui.button = [];
		this.ui.button.close = getT('Close');
		this.ui.button.send = getT('Send');

		this.ui.messages = [];
		this.ui.messages.filenameMessage = [];
		this.ui.messages.filenameMessage.email = getT('Signature by email');
		this.ui.messages.filenameMessage.nextcloudUser = getT('Signature by a Nextcloud user');
		this.ui.messages.filenameMessage.searchUsers = getT('Search users');
		this.ui.messages.filenameMessage.sign = getT(signatureLabelFull);
		this.ui.messages.placeHolderEmailsList = getT('Emails separated by commas');
		this.ui.messages.warning = [];
		this.ui.messages.warning.disabled = [];
		this.ui.messages.warning.disabled.sign = getT('All signature modes are disabled; you have to enable minimum one mode in this application settings prior to sign any document');

		this.ui.pictures = [];
		this.ui.pictures.mobileSigningImg = generateFilePath(appName, '', 'img/') + 'mobileSigning.png';
		this.ui.pictures.loadingImg = generateFilePath('core', '', 'img/') + 'loading.gif';
		this.ui.pictures.applicationLogo = generateFilePath(appName, '', 'img/') + 'applicationLogo.svg';
		this.ui.pictures.separator = generateFilePath(appName, '', 'img/') + 'separator.svg';
		this.ui.pictures.successTick = generateFilePath(appName, '', 'img/') + 'successTick.svg';

		this.ui.scripts = [];
		this.ui.scripts.svgLoader = generateFilePath(appName, '', 'javascript/') + 'svg-loader.min.js';

		this.ui.title = [
			{
				chosen: '',
				sign: uiTitleSign,
			},
		];

		this.currentUser = {};
		this.currentUserFullData = '';
		this.selfDisabled = false;

		return {
			fileList: [],
			ncModalAriaLabel: '',

			noUserResults: false,
			usersLoading: false,
			userResults: [],
			user: '',

			actionSign: false,
			disabledSign: true,
			enabledSign: false,
			file: [
				{
					message: '',
					signed: false,
					basename: getBasename(this.chosenFile),
				},
			],
			filenameMessage: '',

			enabledApp: {
				checked: false,
				sign: false,
			},
			rcdevsSettings: {
				checked: false,
				validated: false,
			},
			signTypes: [],

			settingKO: true,

			axiosChecking: {
				abortCtrl: null,
				inProgress: false,
				success: false,
				error: false,
				message: null,
			},

			axiosSrvRequest: {
				abortCtrl: null,
				inProgress: false,
				success: false,
				error: false,
				message: null,
			},

			axiosUser: {
				abortCtrl: null,
				inProgress: false,
				success: false,
				error: false,
				message: null,
			},

			chosenFile: null,
			action: null,
			modal: false,
			checkingSettings: true,
			errorMessage: '',
			recipientType: '',
			signatureType: '',
			selfEmail: '',
			localUser: [],
			formattedOptions: [],
			designerUrl: '',

			signatureTypeSelected: 'simple', // Setting initial value
			usersListProps: {
				inputLabel: '',
				userSelect: true,
				multiple: true,
				options: [],
				appendToBody: false,
				closeOnSelect: false,
				uid: {
					type: [String, Number],
					default: () => uniqueId(),
				},
			},
		};
	},

	beforeCreate() {
		try {
			log.info(`[beforeCreate] Running...`);
			this.noUserResults = false;
			this.usersLoading = false;
			this.userResults = {};

			this.$root.$watch('chosenFile', async (newValue) => {
				this.chosenFile = newValue;
				this.commonWatch(newValue);
			});

			this.$root.$watch('action', async (newValue) => {
				this.action = newValue;
				this.commonWatch(newValue);
			});
		} catch (exception) {
			log.error(`[beforeCreate] ${exception}`);
		}
	},

	mounted() {
		let scriptSvgLoader = document.createElement('script');
		scriptSvgLoader.setAttribute('src', this.ui.scripts.svgLoader);
		document.head.appendChild(scriptSvgLoader);
	},

	methods: {
		abortUserSearch: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		addUser: function (item) {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.user = this.localUser.applicantId = item.value.shareWith;
				this.localUser.email = item.shareWithDisplayNameUnique;
				this.userResults = {};
				this.noUserResults = false;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSettingsCheck: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to check settings (Global)`);

				axios
					.get(getOcsUrl(this.apis.settingsCheck), {
						signal: this.axiosChecking.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						if (isIssueResponse(response)) {
							throw new Error(`No property named "code" in Axios response`);
						}

						// log.debug(`isEnabled(response.data.code):[${isEnabled(response.data.code)}]`);
						log.debug(`isValidResponse(response):[${isValidResponse(response)}]`);

						this.axiosChecking.success = true;
						// this.rcdevsSettings.validated = isEnabled(response.data.code);
						this.rcdevsSettings.validated = isValidResponse(response);
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = !(this.rcdevsSettings.validated = false);
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;
						this.rcdevsSettings.checked = true;
						this.refreshUiVariables();
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSettingsCheckApp: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to check settings`);

				axios
					.get(getOcsUrl(this.apis.settingsCheckApp), {
						signal: this.axiosChecking.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results Sign
						if (!response.data.hasOwnProperty('enableSign')) {
							throw new Error('Internal server error: this application cannot be enabled due to lack of information');
						}
						this.axiosChecking.success = true;
						this.enabledApp.sign = isEnabled(response.data.enableSign) && this.isActionSign();
						log.debug(`
this.enabledApp.sign:[${this.enabledApp.sign}] /
response.data.enableSign:[${response.data.enableSign}] /
isEnabled(response.data.enableSign):[${isEnabled(response.data.enableSign)}] /
this.isActionSign():[${this.isActionSign()}] /
`);
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = !(this.enabledApp.sign = false);
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;
						this.enabledApp.checked = true;
						this.refreshUiVariables();
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSettingsCheckTypes: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosChecking = this.initAxios();

				log.info(`Contact server to check settings (Signature types)`);

				axios
					.get(getOcsUrl(this.apis.settingsCheckTypes), {
						signal: this.axiosChecking.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results Advanced
						if (!response.data.hasOwnProperty('signTypeAdvanced')) {
							throw new Error('Sign type advanced is missing');
						}

						// Check results Qualified
						if (!response.data.hasOwnProperty('signTypeQualified')) {
							throw new Error('Sign type qualified is missing');
						}

						// Check results Standard
						if (!response.data.hasOwnProperty('signTypeStandard')) {
							throw new Error('Sign type standard is missing');
						}

						this.axiosChecking.success = true;

						// Use temporary vars (issue on response which cannot be changed !?!)
						let responseDataSigntypeadvanced = response.data.signTypeAdvanced;
						let responseDataSigntypequalified = response.data.signTypeQualified;
						let responseDataSigntypestandard = response.data.signTypeStandard;

						// According to file to sign extension, disable the standard signature (if not a PDF)
						if (!this.chosenFile.path.toLowerCase().endsWith('.pdf')) {
							responseDataSigntypestandard = '0';
						}

						log.info(`The signTypes are standard : [${responseDataSigntypestandard}], advanced : [${responseDataSigntypeadvanced}] and qualified : [${responseDataSigntypeadvanced}]`);

						this.signTypes.advanced.enabled = isEnabled(responseDataSigntypeadvanced);
						this.signTypes.qualified.enabled = isEnabled(responseDataSigntypequalified);
						this.signTypes.standard.enabled = isEnabled(responseDataSigntypestandard);

						let cptEnabled = 0;
						cptEnabled += +this.signTypes.advanced.enabled;
						cptEnabled += +this.signTypes.qualified.enabled;
						cptEnabled += +this.signTypes.standard.enabled;
						log.debug(`this.signTypes.advanced.enabled:[${this.signTypes.advanced.enabled}] / this.signTypes.qualified.enabled:[${this.signTypes.qualified.enabled}] / this.signTypes.standard.enabled:[${this.signTypes.standard.enabled}] / cptEnabled:[${cptEnabled}]`);

						switch (cptEnabled) {
							case 0:
								throw new Error('Minimum one signature type is needed to sign the document');
								break;
							case 1:
								this.signTypes.advanced.label = this.signTypes.qualified.label = this.signTypes.standard.label = getT('Signature');
								break;

							default:
								this.signTypes.advanced.label = this.constantes.signType.advanced.label;
								this.signTypes.qualified.label = this.constantes.signType.qualified.label;
								this.signTypes.standard.label = this.constantes.signType.standard.label;
								break;
						}
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosChecking.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosChecking.message = exception.message;
						}

						this.axiosChecking.error = !(this.signTypes.advanced.enabled = this.signTypes.qualified.enabled = this.signTypes.standard.enabled = false);
					})
					.finally(() => {
						this.axiosChecking.inProgress = false;
						this.signTypes.checked = true;
						this.refreshUiVariables();
						log.debug(`this.signTypes.advanced.enabled:[${this.signTypes.advanced.enabled}] / this.signTypes.qualified.enabled:[${this.signTypes.qualified.enabled}] / this.signTypes.standard.enabled:[${this.signTypes.standard.enabled}]`);
						log.debug(`this.enabledSign:[${this.enabledSign}]`);
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosSignLocalAsync: function (apiUrlSignature, recipientId, recipientEmail, recipientType) {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosSrvRequest = this.initAxios();

				log.info(`chosenFile : ${JSON.stringify(this.chosenFile)}`);
				log.info(`fileId : ${this.chosenFile._attributes.fileid}`);

				axios
					.post(
						getOcsUrl(apiUrlSignature),
						{
							path: this.chosenFile.path,
							fileId: this.chosenFile._attributes.fileid,
							recipientId: recipientId,
							recipientEmail: recipientEmail,
							recipientType: recipientType,
						},
						{
							signal: this.axiosSrvRequest.abortCtrl.signal,
						}
					)
					.then((response) => {
						log.debug(`Asynchronized signature response : [${JSON.stringify(response.data)}]`);

						if (isIssueResponse(response)) {
							throw new Error(getT('Error: ') + getT(response.data.message));
						}
						this.axiosSrvRequest.error = !(this.file.signed = this.axiosSrvRequest.success = true);
						this.axiosSrvRequest.message = response.data.message;

						const yumisignCallback = `${location.protocol}//${location.host}` + generateUrl(`/apps/${appName}/sign/mobile/async/external/submit`) + '?' + `&workspaceId=${response.data.workspaceId}` + `&workflowId=${response.data.workflowId}` + `&envelopeId=${response.data.envelopeId}` + `&url=${window.location.href}`;

						if (response.data.designerUrl) {
							this.designerUrl = response.data.designerUrl + '?callback=' + encodeURIComponent(yumisignCallback);
							if (this.signatureTypeSelected.toLowerCase() !== this.constantes.signType.qualified.value) {
								window.location.replace(this.designerUrl);
							}
						}
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosSrvRequest.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosSrvRequest.message = exception.message;
						}

						this.axiosSrvRequest.error = !(this.file.signed = false);
					})
					.finally(() => {
						this.axiosSrvRequest.inProgress = false;
						this.refreshUiVariables();
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosUserEmail: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosUser = this.initAxios();

				log.info(`Contact server to retrieve User's email`);

				axios
					.get(getOcsUrl(this.apis.userEmail), {
						signal: this.axiosUser.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						this.currentUser.email = response.data;
						this.getCurrentUser();

						this.axiosUser.success = true;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosUser.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosUser.message = exception.message;
						}

						this.axiosUser.error = true;
						this.currentUser.email = null;
					})
					.finally(() => {
						this.axiosUser.inProgress = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosUserId: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosUser = this.initAxios();

				log.info(`Contact server to retrieve User's id`);

				axios
					.get(getOcsUrl(this.apis.userId), {
						signal: this.axiosUser.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						this.currentUser.id = response.data;
						this.getCurrentUser();

						this.axiosUser.success = true;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosUser.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosUser.message = exception.message;
						}

						this.axiosUser.error = true;
						this.currentUser.id = null;
					})
					.finally(() => {
						this.axiosUser.inProgress = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosUsersList: function (query) {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosUser = this.initAxios();

				log.info(`Contact server to retrieve Users list`);

				return axios
					.post(
						getOcsUrl(this.apis.usersAll),
						{
							search: query,
							type: 'user',
						},
						{
							signal: this.axiosUser.abortCtrl.signal,
						}
					)
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);
						this.axiosUser.success = true;

						const respData = response.data;
						const exact = respData.exact?.users || [];
						const users = respData.users || [];
						log.debug(`[${this.getFunctionName()}] respData:[${respData}] / exact:[${exact}] / users:[${users}]`);

						this.usersListProps.options = [];

						exact.forEach((singleUser) => {
							log.debug(`[${this.getFunctionName()}] singleUser:[${JSON.stringify(singleUser)}]`);
							this.usersListProps.options.push({
								id: singleUser.value.shareWith,
								displayName: singleUser.label,
								subname: singleUser.shareWithDisplayNameUnique,
							});
						});

						users.forEach((singleUser) => {
							log.debug(`[${this.getFunctionName()}] singleUser:[${JSON.stringify(singleUser)}]`);
							this.usersListProps.options.push({
								id: singleUser.value.shareWith,
								displayName: singleUser.label,
								subname: singleUser.shareWithDisplayNameUnique,
							});
						});

						log.debug(`[${this.getFunctionName()}] this.usersListProps.options:[${JSON.stringify(this.usersListProps.options)}]`);
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosUser.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosUser.message = exception.message;
						}

						this.axiosUser.error = true;
						this.currentUser.id = null;
					})
					.finally(() => {
						this.axiosUser.inProgress = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		cancelSearchLabel: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				return getT('Cancel search');
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		changeNcSelectvalue: function (radiovalue) {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				if (!(this.selfDisabled && radiovalue === this.constantes.self.label)) {
					this.recipientType = radiovalue;
				}
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		closeModal: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				if (this.axiosSrvRequest.abortCtrl && this.axiosSrvRequest.abortCtrl.signal) {
					this.axiosSrvRequest.abortCtrl.abort('Operation canceled by the user');
					log.info('Operation canceled by the user');
				}

				this.resetInputs();
				this.$root.$emit('dialog:closed');

				// Reset inputs
				this.usersListProps.options = [];
				this.emailsList = '';

			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		commonSyncSign: function (action, response) {
			log.debug(`Refresh ${action} file : [${response.data.data.fileId}]`);

			const davUrl = generateRemoteUrl('dav');
			const currentUserId = getCurrentUser()?.uid;

			log.debug(`DAV : [${davUrl}/files/${currentUserId}/${response.data.data.name}]`);

			const file = new File({
				source: `${davUrl}/files/${currentUserId}/${response.data.data.name}`,
				id: response.data.data.fileId,
				size: response.data.data.size,
				mtime: new Date(),
				mime: 'application/pdf',
				owner: getCurrentUser()?.uid || null,
				permissions: Permission.ALL,
				root: `/files/${currentUserId}`,
			});

			if (response.data.data.overwrite) {
				emit('files:node:updated', file);
			} else {
				emit('files:node:created', file);
			}

			this.axiosSrvRequest.error = !(this.file.signed = this.axiosSrvRequest.success = true);
		},
		
		commonWatch: function (newValue) {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				if (newValue) {
					this.modal = true;

					this.resetInputs();

					// Verify App settings
					this.axiosSettingsCheck();

					// Check Signature Types
					this.axiosSettingsCheckTypes();
					this.axiosSettingsCheckApp();

					// Get current user Id
					this.axiosUserId();
					this.axiosUserEmail();
				} else {
					this.modal = false;
				}
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		debounceSearchUsers: debounce(function (query, loading) {
			this.searchUsers(query, loading);
		}, 250),

		findUsersFiltered: function () {
			log.info(this.usersListProps.value);
		},

		getCurrentUser: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.currentUserFullData = '';

				switch (true) {
					case isEmail(this.currentUser.id):
						this.currentUserFullData = this.currentUser.id;
						break;
					default:
						this.currentUserFullData = this.currentUser.id;
						break;
				}
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
				this.currentUserFullData = '';
			}
		},

		getFilenameMessage: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				let localReturn = this.isActionSign() ? this.ui.messages.filenameMessage.sign : '';
				log.debug(`${this.getFunctionName()} : [${localReturn}]`);

				return localReturn;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		getFunctionName: function () {
			const error = new Error();
			const stackLines = error.stack.split('\n');
			// The stack trace format can vary; you may need to adjust the index
			const callerLine = stackLines[2].trim();
			const functionName = callerLine.split(' ')[1];
			return functionName;
		},

		getModalTitle: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				let localReturn = this.isActionSign() ? this.ui.title.sign : '';
				log.debug(`${this.getFunctionName()} : [${localReturn}]`);

				return localReturn;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		getNcModalAriaLabel: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				if (this.isActionSign()) {
					getT(signatureLabel);
				}
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		handleUserInput: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosSrvRequest.error = false;
				this.noUserResults = false;
				this.usersLoading = true;
				this.userResults = {};
				this.debounceSearchUsers();
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
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

		isActionSign: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				let localReturn = this.action === signAction ? true : false;
				log.debug(`${this.getFunctionName()} : [${localReturn}]`);

				return localReturn;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		isDisabledSign: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				// Using rcdevsSettings.checked because if not checked, impossible to say if Settings are OK or KO
				let localReturn = this.rcdevsSettings.checked && this.enabledApp.checked && this.signTypes.checked && (!this.enabledApp.sign || this.settingKO || (!this.signTypes.advanced.enabled && !this.signTypes.qualified.enabled && !this.signTypes.standard.enabled));
				log.debug(`${this.getFunctionName()} : [${localReturn}]`);

				return localReturn;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		isEnabledSign: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				// Using rcdevsSettings.checked because if not checked, impossible to say if Settings are OK or KO
				let localReturn = this.rcdevsSettings.checked && this.enabledApp.checked && this.signTypes.checked && this.enabledApp.sign && this.settingOK && (this.signTypes.advanced.enabled || this.signTypes.qualified.enabled || this.signTypes.standard.enabled);
				log.debug(`
rcdevsSettings.checked:[${this.rcdevsSettings.checked}] /
enabledApp.checked:[${this.enabledApp.checked}] /
signTypes.checked:[${this.signTypes.checked}] /
enabledApp.sign:[${this.enabledApp.sign}] /
settingOK:[${this.settingOK}] /
signTypes.advanced.enabled:[${this.signTypes.advanced.enabled}] /
signTypes.qualified.enabled:[${this.signTypes.qualified.enabled}] /
signTypes.standard.enabled:[${this.signTypes.standard.enabled}] /
				`);
				log.debug(`${this.getFunctionName()} : [${localReturn}]`);

				return localReturn;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		isSearchingUser: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				return this.user !== '';
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		isSettingKO: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				// Using rcdevsSettings.checked because if not checked, impossible to say if Settings are OK or KO
				let localReturn = this.rcdevsSettings.checked && !this.rcdevsSettings.validated;
				log.debug(`${this.getFunctionName()} : [${localReturn}]`);

				return localReturn;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		isSettingOK: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				// Using rcdevsSettings.checked because if not checked, impossible to say if Settings are OK or KO
				let localReturn = this.rcdevsSettings.checked && this.rcdevsSettings.validated;
				log.debug(`
rcdevsSettings.checked:[${this.rcdevsSettings.checked}] /
rcdevsSettings.validated:[${this.rcdevsSettings.validated}] /
				`);
				log.debug(`${this.getFunctionName()} : [${localReturn}]`);

				return localReturn;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		isSrvRequestInProgress: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				let localReturn = this.axiosSrvRequest.inProgress;
				log.debug(`${this.getFunctionName()} : [${localReturn}]`);

				return localReturn;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		isSrvRequestSuccess: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				let localReturn = !this.axiosSrvRequest.inProgress && this.axiosSrvRequest.success;
				log.debug(`${this.getFunctionName()} : [${localReturn}]`);

				return localReturn;
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		refreshUiVariables: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				log.debug('Refresh UI Vars');
				this.actionSign = this.isActionSign();
				this.disabledSign = this.isDisabledSign();
				this.enabledSign = this.isEnabledSign();
				this.file.message = this.getFilenameMessage();
				this.ncModalAriaLabel = this.getNcModalAriaLabel();
				this.settingKO = this.isSettingKO();
				this.settingOK = this.isSettingOK();
				this.axiosSrvRequest.success = this.isSrvRequestSuccess();
				this.axiosSrvRequest.inProgress = this.isSrvRequestInProgress();
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		resetInputs: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.ui.title.chosen = this.getModalTitle();

				this.axiosChecking = {
					abortCtrl: null,
					inProgress: false,
					success: false,
					error: false,
					message: null,
				};

				this.axiosSrvRequest = {
					abortCtrl: null,
					inProgress: false,
					success: false,
					error: false,
					message: null,
				};
				log.info(`Initialize axiosSrvRequest : [${JSON.stringify(this.axiosSrvRequest)}]`);

				this.axiosUser = {
					abortCtrl: null,
					inProgress: false,
					success: false,
					error: false,
					message: null,
				};

				this.userResults = {};
				this.noUserResults = false;
				this.usersLoading = false;
				this.recipientType = this.constantes.self.label;
				this.localUser = [];
				this.selfDisabled = false;

				this.enabledApp = {
					checked: false,
					sign: false,
				};

				this.file = {
					message: '',
					signed: false,
					basename: getBasename(this.chosenFile),
				};

				this.rcdevsSettings = {
					checked: false,
					validated: false,
				};

				this.signTypes = {
					checked: false,
					advanced: {
						additional: this.constantes.signType.advanced.additional,
						enabled: false,
						label: '',
					},
					qualified: {
						additional: this.constantes.signType.qualified.additional,
						enabled: false,
						label: '',
					},
					standard: {
						additional: this.constantes.signType.standard.additional,
						enabled: false,
						label: '',
					},
				};

				this.refreshUiVariables();
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		runTransactionSignature: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				log.debug(`this.signatureType : [${this.signatureType}]`);

				let apiUrlSignature = `${this.apis.signLocalAsync}/${this.signatureType}`;
				let recipientId = '';
				let recipientEmail = '';

				switch (this.recipientType) {
					case this.constantes.self.label:
						recipientId = this.currentUser.id;
						recipientEmail = '';
						break;
					case this.constantes.nextcloud:
						this.usersListProps.value.forEach((unitUser) => {
							if (recipientId.length > 0) {
								recipientId += ',';
							}
							recipientId += unitUser.id;
						});
						recipientEmail = '';
						break;
					case this.constantes.email:
						recipientId = '';
						recipientEmail = this.emailsList;
						break;

					default:
						throw new Error('Invalid recipient type');
						break;
				}

				this.axiosSignLocalAsync(apiUrlSignature, recipientId, recipientEmail, this.recipientType);
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		search: function (query, loading) {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				log.debug(`[${this.getFunctionName()}] query : [${query}]`);

				if (query.length >= 3) {
					this.axiosUsersList(query);
				}
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
				showError(getT('An error occurred while performing the search'));
			}
		},

		// async searchUsers(query, loading) {
		searchUsers: async function (query, loading) {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				log.debug(`[${this.getFunctionName()}] query : [${query}]`);

				if (query.length >= 3) {
					this.usersListProps.loading = true;
					const search = async (search, type) => {
						return await axios
							.post(getOcsUrl(this.apis.usersAll), {
								search: query,
								type: 'user',
							})
							.finally(() => {
								this.usersListProps.loading = false;
							});
					};

					const exact = search.exact?.users || [];
					const users = search.users || [];
					log.debug(`[${this.getFunctionName()}] search:[${search}] / exact:[${exact}] / users:[${users}]`);

					this.usersListProps.options = [];

					exact.forEach((singleUser) => {
						this.usersListProps.options.push({
							id: singleUser.value.shareWith,
							displayName: singleUser.label,
							subname: singleUser.shareWithDisplayNameUnique,
						});
					});

					users.forEach((singleUser) => {
						this.usersListProps.options.push({
							id: singleUser.value.shareWith,
							displayName: singleUser.label,
							subname: singleUser.shareWithDisplayNameUnique,
						});
					});
				}
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
				showError(getT('An error occurred while performing the search'));
			}
		},
	}, // END methods
};
</script>

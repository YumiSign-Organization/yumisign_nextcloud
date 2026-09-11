<!--
 *
 * @copyright Copyright (c) 2025, RCDevs (info@rcdevs.com)
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
		<rcdevsMessageBanner>
			<rcdevsIconMessage></rcdevsIconMessage>
			<rcdevsCuteMessage></rcdevsCuteMessage>
		</rcdevsMessageBanner>

		<SignedFolderSettings :endpoint="signedFoldersEndpoint" :translate="translateSignedFolders" />

		<rcdevsSettingsContainer id="rcdevsPortalConnectionYMS">
			<rcdevsSettingsHeader>
				<rcdevsSettingsTitle>{{ ui.messages.app.title }}</rcdevsSettingsTitle>
				<rcdevsSettingsItem>{{ ui.messages.app.tagline }}</rcdevsSettingsItem>
			</rcdevsSettingsHeader>

			<rcdevsSettingsPartsContainer>
				<!-- Connection button -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsButton">
						<button @click="connection()" :disabled="tokenOk">
							{{ ui.button.connect }}
						</button>
					</rcdevsSettingsItem>
					<rcdevsSettingsItem v-if="displayMessageCheck" :class="{rcdevsValid: accessTokenRegistered, rcdevsInvalid: !accessTokenRegistered}">
						{{ axiosSettings.message }}
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsButton">
						<button @click="axiosRefreshToken()" :disabled="!tokenOk">
							{{ ui.button.refresh }}
						</button>
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsButton">
						<button @click="axiosDeleteToken()" :disabled="!tokenOk">
							{{ ui.button.delete }}
						</button>
					</rcdevsSettingsItem>
					<rcdevsSettingsItem v-if="displayMessageDelete" :class="{rcdevsValid: accessTokenDeleted, rcdevsInvalid: !accessTokenDeleted}">
						{{ axiosSettings.messageDelete }}
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
			</rcdevsSettingsPartsContainer>
		</rcdevsSettingsContainer>
	</rcdevsMain>
</template>

<script>
import SignedFolderSettings from '../../rcdevsBundle/src/components/SignedFolderSettings.vue';
import {CDEM, getBasename, getAppUrl, getFunctionName as getUtilityFunctionName, getOcsUrl, getT, isEmail, isEmptyString, isEnabled, isFilledString, isNotEmail, isValidResponse, log} from '../javascript/utility';
import {loadState} from '@nextcloud/initial-state';
import $ from 'jquery';
import axios from '@nextcloud/axios';

export default {
	name: 'PersonalSettings',
	components: { SignedFolderSettings },

	data() {
		this.ui = {
			button: {},
			messages: {
				app: {},
				banner: {},
			},
			pictures: {},
			placeHolders: {},
		};
		this.apis = {
			oauthTokenCheck: '/oauth/token/check',
			oauthTokenDelete: '/oauth/token/delete',
			oauthTokenRefresh: '/oauth/token/refresh',
		};
		//#region UI texts
		this.ui.messages.app.emailAddress = getT('Email address');
		this.ui.messages.app.failureSave = getT('There was an error saving settings');
		this.ui.messages.app.forgetSave = getT('Do not forget to save your settings!');
		this.ui.messages.app.password = getT('Password');
		this.ui.messages.app.save = getT('Save');
		this.ui.messages.app.tagline = getT('Login to YumiSign to send signature requests from your own account');
		this.ui.messages.app.title = getT('YumiSign for Nextcloud Personal Settings');

		this.ui.messages.banner.error = 'error';
		this.ui.messages.banner.success = 'success';
		this.ui.messages.banner.warning = 'warning';
		this.ui.messages.banner.info = 'info';

		this.ui.button.connect = getT('Connection');
		this.ui.button.delete = getT('Delete token');
		this.ui.button.refresh = getT('Refresh token');

		this.ui.placeHolders.emailAddress = getT('Email used to connect to YumiSign');
		this.ui.placeHolders.password = getT('Associated password');
		//#endregion

		return {
			signedFoldersEndpoint: getAppUrl('/settings/signed-folders'),
			translateSignedFolders: getT,
			//#region Returned values
			accessTokenDeleted: null,
			accessTokenRegistered: null,
			code: null,
			displayMessageCheck: true,
			displayMessageDelete: false,
			redirectUri: null,
			state: null,
			tokenOk: false,

			axiosSettings: {
				abortCtrl: null,
				inProgress: false,
				success: false,
				error: false,
				message: null,
				messageDelete: null,
			},
			//#endregion
		};
	},

	created() {
		this.axiosAccessTokenCheck();
	},

	beforeMount() {
		//#region Initial Settings
		const initialSettings = loadState(appName, 'initialSettings');

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
		this.sign_type_advanced = initialSettings.sign_type_advanced;
		this.sign_type_qualified = initialSettings.sign_type_qualified;
		this.sign_type_standard = initialSettings.sign_type_standard;
		this.state = initialSettings.state;
		this.textualComplementSign = initialSettings.textualComplementSign;
		this.useProxy = initialSettings.useProxy;
		this.workspaceId = initialSettings.workspaceId;
		this.workspaceName = initialSettings.workspaceName;
		// Specific
		this.ymsApiAuthorize = initialSettings.ymsApiAuthorize;

		log.debug(`initialSettings:[${JSON.stringify(initialSettings)}]`);
		//#endregion
	},

	methods: {
		axiosAccessTokenCheck: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosSettings = this.initAxios();

				// log.info(`Contact server to check if access token is registered`);

				// Hide/Show messages
				this.displayMessageCheck = true;
				this.displayMessageDelete = false;

				axios
					.get(getAppUrl(this.apis.oauthTokenCheck), {
						signal: this.axiosSettings.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);
						const cdem = CDEM(response.data);

						// Check results
						if (cdem.code !== 0) {
							throw new Error('Checking failed');
						}
						this.axiosSettings.success = true;
						// Apply values
						this.accessTokenRegistered = cdem.data.tokenRegistered;
						this.tokenOk = cdem.data.tokenRegistered;
						this.axiosSettings.message = getT(cdem.message);
						this.axiosSettings.messageDelete = '';
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosSettings.message = getT(this.ui.axios.requestCancelled);
						} else {
							this.axiosSettings.message = getT(exception.message);
						}

						this.axiosSettings.error = true;
						// Apply values
					})
					.finally(() => {
						this.axiosSettings.inProgress = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosDeleteToken: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosSettings = this.initAxios();

				// Hide/Show messages
				this.displayMessageCheck = false;
				this.displayMessageDelete = true;

				axios
					.get(getAppUrl(this.apis.oauthTokenDelete), {
						signal: this.axiosSettings.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);
						const cdem = CDEM(response.data);

						// Check results
						if (cdem.code !== 0) {
							throw new Error('Deleting failed');
						}
						this.axiosSettings.success = true;
						// Apply values
						this.accessTokenDeleted = cdem.data.tokenDeleted;
						this.tokenOk = cdem.data.tokenRegistered;
						this.axiosSettings.messageDelete = getT(cdem.message);
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosSettings.messageDelete = getT(this.ui.axios.requestCancelled);
						} else {
							this.axiosSettings.messageDelete = getT(exception.message);
						}

						this.axiosSettings.error = true;
						// Apply values
					})
					.finally(() => {
						this.axiosSettings.inProgress = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosRefreshToken: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosSettings = this.initAxios();

				// Hide/Show messages
				this.displayMessageCheck = true;
				this.displayMessageDelete = false;

				axios
					.get(getAppUrl(this.apis.oauthTokenRefresh), {
						signal: this.axiosSettings.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);
						const cdem = CDEM(response.data);

						// Check results
						if (cdem.code !== 0) {
							throw new Error('Checking failed');
						}
						this.axiosSettings.success = true;
						// Apply values
						this.accessTokenRegistered = cdem.data.tokenRegistered;
						this.tokenOk = cdem.data.tokenRegistered;
						this.axiosSettings.message = getT(cdem.message);
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosSettings.message = getT(this.ui.axios.requestCancelled);
						} else {
							this.axiosSettings.message = getT(exception.message);
						}

						this.axiosSettings.error = true;
						// Apply values
					})
					.finally(() => {
						this.axiosSettings.inProgress = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		connection: function () {
			try {
				const connectUrl = getAppUrl(this.ymsApiAuthorize);
				log.debug(`Connect to ${connectUrl}`);
				window.location.replace(connectUrl);
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
				writeMessageBanner(getT(exception.message), ui.messages.banner.error);
			}
		},

			getFunctionName: function () {
				return getUtilityFunctionName();
			},

		hideQuietly: function (element, speed = 1000) {
			setTimeout(function () {
				$(element).fadeOut('fast', function () {
					$(element).removeClass('show');
				});
			}, speed);
		},

		hideNotSoQuietly: function (element) {
			hideQuietly(element, 250);
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

		resetValueAndCo(refData) {
			this[refData] = '';
			this.$refs[refData].focus();
		},

		saveSettings: function () {},

		writeMessageBanner: function (messageToDisplay, messageType = 'Info') {
			$('rcdevsCuteMessage').text(messageToDisplay);
			$('messageBanner').attr('type', messageType);
			$('messageBanner').css('display', 'flex');

			if (messageType === this.ui.messages.banner.success) {
				this.hideQuietly($('messageBanner'));
			}
		},
	},

	mounted() {
		$('body').on('click', 'rcdevsMessageBanner', function () {
			this.hideNotSoQuietly($(this));
		});
	},

	beforeDestroy() {
		$('body').off('click', 'rcdevsMessageBanner', function () {
			this.hideNotSoQuietly($(this));
		});
	},
};
</script>

<style>
@import '../styles/yumisignRoot.css';
@import '../styles/rcdevsSettings.css';
@import '../styles/rcdevsStyle.css';
@import '../styles/rcdevsUtility.css';
</style>

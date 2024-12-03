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
		<rcdevsMessageBanner>
			<rcdevsIconMessage></rcdevsIconMessage>
			<rcdevsCuteMessage></rcdevsCuteMessage>
		</rcdevsMessageBanner>

		<rcdevsSettingsContainer id="rcdevsPortalConnectionYMS">
			<rcdevsSettingsHeader>
				<rcdevsSettingsTitle>{{ ui.messages.app.title }}</rcdevsSettingsTitle>
				<rcdevsSettingsItem>{{ ui.messages.app.tagline }}</rcdevsSettingsItem>
			</rcdevsSettingsHeader>

			<rcdevsSettingsPartsContainer>
				<!-- Connection button -->
				<rcdevsSettingsRow>
					<rcdevsSettingsItem class="rcdevsSettingsButton">
						<button @click="connection()">
							{{ ui.button.connect }}
						</button>
					</rcdevsSettingsItem>
					<rcdevsSettingsItem :class="{rcdevsValid: accessTokenRegistered, rcdevsInvalid: !accessTokenRegistered}">
						{{ axiosSettings.message }}
					</rcdevsSettingsItem>
				</rcdevsSettingsRow>
			</rcdevsSettingsPartsContainer>
		</rcdevsSettingsContainer>
	</rcdevsMain>
</template>

<script>
import {getBasename, getOcsUrl, getT, isEmail, isEmptyString, isEnabled, isFilledString, isNotEmail, isValidResponse, log} from '../javascript/utility';
import {loadState} from '@nextcloud/initial-state';
import $ from 'jquery';
import axios from '@nextcloud/axios';

export default {
	name: 'PersonalSettings',

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
			settingsAccessTokenCheck: '/settings/personal/token/check',
			settingsAccessTokenRefreshToken: '/settings/personal/token',
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

		this.ui.placeHolders.emailAddress = getT('Email used to connect to YumiSign');
		this.ui.placeHolders.password = getT('Associated password');
		//#endregion

		return {
			//#region Returned values
			accessTokenRegistered: null,
			code: null,
			redirectUri: null,
			state: null,

			axiosSettings: {
				abortCtrl: null,
				inProgress: false,
				success: false,
				error: false,
				message: null,
			},
			//#endregion
		};
	},

	created() {
		const {protocol, host, pathname} = window.location;
		this.redirectUri = `${protocol}//${host}${pathname}`;

		const urlParams = new URLSearchParams(window.location.search);
		const params = {};
		for (const [key, value] of urlParams) {
			if (key === 'code') {
				this.code = value;
			}
			if (key === 'state') {
				this.state = value;
			}
			params[key] = value;
		}
		log.debug(`Parameters in URL ${JSON.stringify(params)}`);

		// If code && state are not null, call YumiSign server to retrieve access_token and refresh_token
		if (this.code == null || this.state == null) {
			/**
			 * First step on Nextcloud server, the user opens the Personal Settings page
			 * Check if Token is saved in NxcDB
			 *
			 * @step: Sending users to authorize
			 * @reference: https://docs.yumisign.com/authorization/oauth.html#step-1-sending-users-to-authorize
			 */
			this.axiosAccessTokenCheck();
		}
		// Check if a token is already registered
		else {
			// The code AND the state are not null
			/**
			 * Second step on Nextcloud server, returned from YumiSign login page (Users are redirected to your server with a verification code, https://docs.yumisign.com/authorization/oauth.html#step-2-users-are-redirected-to-your-server-with-a-verification-code)
			 * Call the YumiSign server through Nextcloud server
			 * 
			 * @step: Exchanging a verification code for an access token
			 * @reference: https://docs.yumisign.com/authorization/oauth.html#step-3-exchanging-a-verification-code-for-an-access-token
			 */
			this.axiosAccessTokenRefreshToken();
		}
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
		this.signTypeAdvanced = initialSettings.signTypeAdvanced;
		this.signTypeQualified = initialSettings.signTypeQualified;
		this.signTypeStandard = initialSettings.signTypeStandard;
		this.state = initialSettings.state;
		this.textualComplementSign = initialSettings.textualComplementSign;
		this.useProxy = initialSettings.useProxy;
		this.workspaceId = initialSettings.workspaceId;
		this.workspaceName = initialSettings.workspaceName;
		// Specific
		this.ymsApiAuthorize = initialSettings.ymsApiAuthorize;

		console.log(`initialSettings:[${JSON.stringify(initialSettings)}]`);
		//#endregion
	},

	methods: {
		axiosAccessTokenRefreshToken: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosSettings = this.initAxios();

				// log.info(`Contact server to access APIs with Token`);

				axios
					.post(getOcsUrl(this.apis.settingsAccessTokenRefreshToken), {
						redirect_uri: this.redirectUri,
						code: this.code,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results
						if (!isValidResponse(response)) {
							throw new Error('Token initialization failed');
						}
						this.axiosSettings.success = true;
						// Apply values
						this.accessTokenRegistered = isEnabled(response.data.code);
						this.axiosSettings.message = response.data.message;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosSettings.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosSettings.message = exception.message;
						}

						this.axiosSettings.error = true;
						this.writeMessageBanner(this.axiosSettings.message, this.ui.messages.banner.error);
						// Apply values
						this.accessTokenRegistered = false;
						this.axiosSettings.message = this.axiosSettings.message;
					})
					.finally(() => {
						this.axiosSettings.inProgress = false;
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosAccessTokenCheck: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosSettings = this.initAxios();

				// log.info(`Contact server to check if token is registered`);
				axios
					.get(getOcsUrl(this.apis.settingsAccessTokenCheck), {
						signal: this.axiosSettings.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`Response for ${JSON.stringify(response.data)}`);

						// Check results
						if (!isValidResponse(response)) {
							throw new Error('Checking failed');
						}
						this.axiosSettings.success = true;
						// Apply values
						this.accessTokenRegistered = response.data.data.tokenRegistered;
						this.axiosSettings.message = response.data.message;
						this.state = response.data.data.state;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosSettings.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosSettings.message = exception.message;
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
				window.location.replace(`${this.ymsApiAuthorize}?&client_id=${this.clientId}&redirect_uri=${this.redirectUri}&response_type=code&state=${this.state}`);
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
				writeMessageBanner(getT(exception.message), ui.messages.banner.error);
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

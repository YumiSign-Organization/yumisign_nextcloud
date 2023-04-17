<template>
	<!--
	*
	* @copyright Copyright (c) 2023, RCDevs (info@rcdevs.com)
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
	-->
	<div id="yumisign_nextcloud_main">
		<h2>{{ $t('yumisign_nextcloud', 'YumiSign for Nextcloud Settings') }}</h2>
		<div id="yumisign_nextcloud" class="ymsSettings">
			<div class="ymsSettingsHeader">
				{{ $t('yumisign_nextcloud', 'Enter your YumiSign server settings in the fields below.') }}
			</div>
			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label :for="yumisign_server_url">{{ $t('yumisign_nextcloud', 'YumiSign server URL') }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<span class="deleteicon">
						<input :id="yumisign_server_url"
							ref="serverUrl"
							v-model="serverUrl"
							type="text"
							:name="yumisign_server_url"
							maxlength="300"
							:placeholder="`${placeHolderServerUrl}`">
						<!-- <span @click="serverUrl = ''; clearIcons(); $refs.serverUrl.focus();">x</span> -->
						<span @click="resetValueAndCo('serverUrl');">x</span>
					</span>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage" />
				<div class="ymsSettingsPart ymsSettingsButton" />
			</div>
			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label for="api_key">{{ $t('yumisign_nextcloud', 'YumiSign API Key') }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<span class="deleteicon">
						<input id="api_key"
							ref="apiKey"
							v-model="apiKey"
							type="text"
							name="api_key"
							maxlength="256"
							:placeholder="`${placeHolderApiKey}`">
						<span @click="resetValueAndCo('apiKey');">x</span>
					</span>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage" />
				<div class="ymsSettingsPart ymsSettingsButton" />
			</div>
			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label for="workspace_name">{{ $t('yumisign_nextcloud', 'Workspace name') }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<span class="deleteicon">
						<input id="workspace_name"
							ref="workspaceName"
							v-model="workspaceName"
							type="text"
							class="deletable"
							name="workspace_name"
							maxlength="256"
							:placeholder="`${placeHolderWorkspaceName}`">
						<span @click="resetValueAndCo('workspaceName');">x</span>
					</span>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage">
					<transition name="fade">
						<img v-if="!reqWspName.enable" class="status_loader" :src="disableImg">
						<img v-if="reqWspName.enable && reqWspName.request" class="status_loader status_request" :src="requestImg">
						<img v-if="reqWspName.enable && !reqWspName.request && reqWspName.status" class="status_loader" :src="successImg">
						<img v-if="reqWspName.enable && !reqWspName.request && !reqWspName.status" class="status_loader" :src="failureImg">
					</transition>
				</div>
				<div class="ymsSettingsPart ymsSettingsButton">
					<button v-if="workspaceName && !workspaceId" @click="checkWorkspaceName()">
						{{ $t('yumisign_nextcloud', 'Verify name') }}
					</button>
					<button v-if="workspaceName && workspaceId" @click="checkNameId()">
						{{ $t('yumisign_nextcloud', 'Check Name/ID') }}
					</button>
				</div>
			</div>
			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label for="workspace_id">{{ $t('yumisign_nextcloud', 'Workspace ID') }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<span class="deleteicon">
						<input id="workspace_id"
							ref="workspaceId"
							v-model="workspaceId"
							type="number"
							class="deletable"
							name="workspace_id"
							:placeholder="`${placeHolderWorkspaceId}`">
						<span @click="resetValueAndCo('workspaceId');">x</span>
					</span>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage">
					<transition name="fade">
						<img v-if="!reqWspId.enable" class="status_loader" :src="disableImg">
						<img v-if="reqWspId.enable && reqWspId.request" class="status_loader status_request" :src="requestImg">
						<img v-if="reqWspId.enable && !reqWspId.request && reqWspId.status" class="status_loader" :src="successImg">
						<img v-if="reqWspId.enable && !reqWspId.request && !reqWspId.status" class="status_loader" :src="failureImg">
					</transition>
				</div>
				<div class="ymsSettingsPart ymsSettingsButton">
					<button v-if="!workspaceId" @click="retrieveId()">
						{{ $t('yumisign_nextcloud', 'Retrieve ID') }}
					</button>
				</div>
			</div>
			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel" />
				<div class="ymsSettingsPart ymsSettingsInput" />
				<div class="ymsSettingsPart ymsSettingsImage">
					<transition name="fade">
						<img v-if="!reqServerUrl.enable" class="status_loader" :src="disableImg">
						<img v-if="reqServerUrl.request" class="status_loader status_request" :src="requestImg">
						<img v-if="!reqServerUrl.request && reqServerUrl.status" class="status_loader" :src="successImg">
						<img v-if="!reqServerUrl.request && !reqServerUrl.status" class="status_loader" :src="failureImg">
					</transition>
				</div>
				<div class="ymsSettingsPart ymsSettingsButton">
					<button @click="testConnection()">
						{{ $t('yumisign_nextcloud', 'Test connection') }}
					</button>
				</div>
			</div>
			<div class="ymsSettingsFooter">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<button @click="saveSettings">
						{{ $t('yumisign_nextcloud', 'Save') }}
					</button>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<transition name="fade">
						<p v-if="!saved" class="save_warning">
							{{ $t('yumisign_nextcloud', 'Do not forget to save your settings!') }}
						</p>
						<p v-if="success" id="save_success">
							{{ $t('yumisign_nextcloud', 'Your settings have been saved succesfully') }}
						</p>
						<p v-if="failure" id="save_failure">
							{{ $t('yumisign_nextcloud', 'There was an error saving settings') }}
						</p>
					</transition>
				</div>
			</div>
		</div>

		<div id="proxy" class="ymsSettings">
			<div class="ymsSettingsHeader">
				{{ $t('yumisign_nextcloud', 'Enter your Proxy server settings in the fields below.') }}
			</div>

			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<CheckboxRadioSwitch :checked.sync="useProxy">
						{{ $t('yumisign_nextcloud', 'Use a proxy') }}
					</CheckboxRadioSwitch>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput" />
				<div class="ymsSettingsPart ymsSettingsImage" />
				<div class="ymsSettingsPart ymsSettingsButton" />
			</div>

			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label for="proxy_host">{{ $t('yumisign_nextcloud', 'Proxy Host') }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<span class="deleteicon">
						<input id="proxy_host"
							ref="proxyHost"
							v-model="proxyHost"
							type="text"
							name="proxy_host"
							maxlength="255"
							:disabled="!useProxy">
						<span @click="resetValueAndCo('proxyHost');">x</span>
					</span>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage" />
				<div class="ymsSettingsPart ymsSettingsButton" />
			</div>

			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label for="proxy_port">{{ $t('yumisign_nextcloud', 'Proxy Port') }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<span class="deleteicon">
						<input id="proxy_port"
							ref="proxyPort"
							v-model="proxyPort"
							type="number"
							name="proxy_port"
							min="1"
							max="65535"
							:disabled="!useProxy">
						<span @click="resetValueAndCo('proxyPort');">x</span>
					</span>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage" />
				<div class="ymsSettingsPart ymsSettingsButton" />
			</div>

			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label for="proxy_username">{{ $t('yumisign_nextcloud', 'Proxy Username') }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<span class="deleteicon">
						<input id="proxy_username"
							ref="proxyUsername"
							v-model="proxyUsername"
							type="text"
							name="proxy_username"
							maxlength="255"
							:disabled="!useProxy">
						<span @click="resetValueAndCo('proxyUsername');">x</span>
					</span>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage" />
				<div class="ymsSettingsPart ymsSettingsButton" />
			</div>

			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label for="proxy_password">{{ $t('yumisign_nextcloud', 'Proxy Password') }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<span class="deleteicon">
						<input id="proxy_password"
							ref="proxyPassword"
							v-model="proxyPassword"
							type="text"
							name="proxy_password"
							maxlength="255"
							:disabled="!useProxy">
						<span @click="resetValueAndCo('proxyPassword');">x</span>
					</span>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage" />
				<div class="ymsSettingsPart ymsSettingsButton" />
			</div>

			<div class="ymsSettingsFooter">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<button @click="saveSettings">
						{{ $t('yumisign_nextcloud', 'Save') }}
					</button>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<transition name="fade">
						<p v-if="!saved" class="save_warning">
							{{ $t('yumisign_nextcloud', 'Do not forget to save your settings!') }}
						</p>
						<p v-if="success" id="save_success">
							{{ $t('yumisign_nextcloud', 'Your settings have been saved succesfully') }}
						</p>
						<p v-if="failure" id="save_failure">
							{{ $t('yumisign_nextcloud', 'There was an error saving settings') }}
						</p>
					</transition>
				</div>
			</div>
		</div>

		<div id="crontab" class="ymsSettings">
			<div class="ymsSettingsHeader">
				{{ $t('yumisign_nextcloud', 'Completion check of pending asynchronous signatures') }}
			</div>

			<div class="ymsSettingsHeader">
				{{ $t('yumisign_nextcloud', 'Define the execution periodicity of the background job that checks for completed signature requests.\n'
					+ 'Please note that for this periodicity to be honored, it is necessary to configure NextCloud background jobs setting with \'Cron\' value and to define the crontab periodicity accordingly.') }}
			</div>

			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label for="cron_interval">{{ $t('yumisign_nextcloud', 'Periodicity ({min} - {max} mns)', {min: MIN_CRON_INTERVAL, max: MAX_CRON_INTERVAL}) }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<span class="deleteicon">
						<input id="cron_interval"
							ref="cronInterval"
							v-model="cronInterval"
							type="number"
							name="cron_interval"
							:min="MIN_CRON_INTERVAL"
							:max="MAX_CRON_INTERVAL">
						<span @click="resetValueAndCo('cronInterval');">x</span>
					</span>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage" />
				<div class="ymsSettingsPart ymsSettingsButton" />
			</div>

			<div class="ymsSettingsPartsContainer">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<label for="cron_check">{{ $t('yumisign_nextcloud', 'Checking Cron') }}</label>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<p id="cron_check" name="cron_check" :class="[reqCron.status]">
						{{ reqCron.message }}
					</p>
				</div>
				<div class="ymsSettingsPart ymsSettingsImage" />
				<div class="ymsSettingsPart ymsSettingsButton">
					<button v-if="!reqCron.code" @click="reset_job()">
						{{ $t('yumisign_nextcloud', 'Reset cron') }}
					</button>
				</div>
			</div>

			<div class="ymsSettingsFooter">
				<div class="ymsSettingsPart ymsSettingsLabel">
					<button @click="saveSettings">
						{{ $t('yumisign_nextcloud', 'Save') }}
					</button>
				</div>
				<div class="ymsSettingsPart ymsSettingsInput">
					<transition name="fade">
						<p v-if="!saved" class="save_warning">
							{{ $t('yumisign_nextcloud', 'Do not forget to save your settings!') }}
						</p>
						<p v-if="success" id="save_success">
							{{ $t('yumisign_nextcloud', 'Your settings have been saved succesfully') }}
						</p>
						<p v-if="failure" id="save_failure">
							{{ $t('yumisign_nextcloud', 'There was an error saving settings') }}
						</p>
					</transition>
				</div>
			</div>
		</div>

		<!-- Modal form for Workspaces IDs -->
		<WspListIds ref="WspListIds" @closed="closedEvent" />
	</div>
</template>
<script>
import axios from '@nextcloud/axios'
import { generateUrl, generateFilePath } from '@nextcloud/router'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import WspListIds from './components/WorkspaceListIds.vue'

// const reqYumiSign = { enable: false, request: false, status: false, message: '', id: 0, code: false }
const reqServerUrl = { enable: true, request: false, status: false, message: '', code: false }
const reqWspName = { enable: false, request: false, status: false, message: '', id: 0, code: false }
const reqWspId = { enable: false, request: false, status: false, message: '', id: 0, code: false }
const reqCron = { enable: false, request: false, status: false, message: '', code: false }

export default {
	name: 'AppAdmin',
	components: {
		CheckboxRadioSwitch,
		WspListIds,
	},
	data() {
		const serverUrl = this.$parent.serverUrl
		const baseUrl = generateUrl('/apps/yumisign_nextcloud') + '/'

		return {
			serverUrl,
			baseUrl,
			reqYumiSign: [],
			reqServerUrl,
			reqWspName,
			reqWspId,
			reqCron,
			apiKey: this.$parent.apiKey,
			workspaceId: this.$parent.workspaceId,
			workspaceName: this.$parent.workspaceName,
			defaultDomain: this.$parent.defaultDomain,
			userSettings: this.$parent.userSettings,
			useProxy: !!this.$parent.useProxy,
			proxyHost: this.$parent.proxyHost,
			proxyPort: this.$parent.proxyPort,
			proxyUsername: this.$parent.proxyUsername,
			proxyPassword: this.$parent.proxyPassword,
			signScope: this.$parent.signScope,
			signedFile: this.$parent.signedFile,
			syncTimeout: this.$parent.syncTimeout,
			asyncTimeout: this.$parent.asyncTimeout,
			cronInterval: this.$parent.cronInterval,
			enableDemoMode: !!this.$parent.enableDemoMode,
			watermarkText: this.$parent.watermarkText,
			success: false,
			failure: false,
			saved: false,
			MIN_TIMEOUT: 1,
			MAX_SYNC_TIMEOUT: 5,
			MAX_ASYNC_TIMEOUT: 30,
			MIN_CRON_INTERVAL: 1,
			MAX_CRON_INTERVAL: 15,
		}
	},
	mounted() {
		this.loadingImg = generateFilePath('yumisign_nextcloud', '', 'img/') + 'YumiSign.png'
		this.requestImg = generateFilePath('yumisign_nextcloud', '', 'img/') + 'YumiSign_gray.svg'
		this.successImg = generateFilePath('yumisign_nextcloud', '', 'img/') + 'YumiSign_green.png'
		this.failureImg = generateFilePath('yumisign_nextcloud', '', 'img/') + 'YumiSign_red.png'
		this.disableImg = generateFilePath('yumisign_nextcloud', '', 'img/') + 'YumiSign_disabled.png'

		// this.reqYumiSign = { enable: false, request: false, status: false, message: '', id: 0, code: false }
		this.reqServerUrl = { enable: true, request: false, status: false, message: '', code: false }
		this.reqWspName = { enable: false, request: false, status: false, message: '', id: 0, code: false }
		this.reqWspId = { enable: false, request: false, status: false, message: '', id: 0, code: false }
		this.reqCron = { enable: false, request: false, status: false, message: '', code: false }

		this.placeHolderServerUrl = t('yumisign_nextcloud', 'Write YumiSign url here')
		this.placeHolderApiKey = t('yumisign_nextcloud', 'Get API Key from YumiSign UI')
		this.placeHolderWorkspaceName = t('yumisign_nextcloud', 'Set a Workspace name')
		this.placeHolderWorkspaceId = t('yumisign_nextcloud', 'Set the Workspace ID from name')

		// Add Event Listener on all inputs
		const inputs = document.querySelectorAll('input')
		inputs.forEach(input => {
			input.addEventListener('change', this.inputNotSaved)
		})
		this.saved = true

		// Call server check
		this.testConnection()

		// Check if Cron is enabled or disabled
		this.retrieveCronStatus()
	},
	methods: {
		commonServerPost(urlPost) {
			return axios.post(this.baseUrl + urlPost, {
				xsrfCookieName: 'XSRF-TOKEN',
				xsrfHeaderName: 'X-XSRF-TOKEN',
				server_url: this.serverUrl,
				api_key: this.apiKey,
				workspace_id: this.workspaceId,
				workspace_name: this.workspaceName,
				description: this.description,
				use_proxy: this.useProxy,
				proxy_host: this.proxyHost,
				proxy_port: this.proxyPort,
				proxy_username: this.proxyUsername,
				proxy_password: this.proxyPassword,
			})
				.then(response => {
					return response.data
				})
				.catch(error => {
					return error.data
				})
		},

		checkNameId() {
			this.reqWspName.enable = true
			this.reqWspName.request = true
			this.reqWspId.enable = true
			this.reqWspId.request = true

			this.commonServerPost('check_wspname_id').then(response => {
				this.reqWspName.enable = true
				this.reqWspName.request = false
				this.reqWspName.code = response.code
				this.reqWspName.id = response.id
				this.reqWspName.message = response.message
				this.reqWspName.status = response.status
				this.reqWspId.enable = true
				this.reqWspId.request = false
				this.reqWspId.status = response.status
			})
		},

		checkWorkspaceName() {
			this.reqWspName.enable = true
			this.reqWspName.request = true

			this.commonServerPost('check_wspname_id').then(response => {
				this.reqWspName.enable = true
				this.reqWspName.request = false
				this.reqWspName.code = response.code
				this.reqWspName.id = response.id
				this.reqWspName.message = response.message
				this.reqWspName.status = response.status
			})
		},

		clearIcons() {
			this.reqWspName.enable = false
			this.reqWspId.enable = false
			this.reqServerUrl.enable = false
		},

		inputNotSaved(event) {
			this.saved = false
		},

		reset_job() {
			this.reqCron.enable = true
			this.reqCron.request = true

			this.commonServerPost('reset_job').then(response => {
				this.reqCron.enable = true
				this.reqCron.request = false
				this.reqCron.code = response.code

				this.reqCron.message = response.message
				this.reqCron.status = 'chk_' + response.status
			})

		},

		resetValueAndCo(refData) {
			this[refData] = ''
			this.clearIcons()
			this.$refs[refData].focus()
			this.saved = false
		},

		retrieveCronStatus() {
			this.reqCron.enable = true
			this.reqCron.request = true

			this.commonServerPost('check_cron_status').then(response => {
				this.reqCron.enable = true
				this.reqCron.request = false
				this.reqCron.code = response.code

				this.reqCron.message = response.message
				this.reqCron.status = 'chk_' + response.status
			})
		},

		retrieveId() {
			this.reqWspId.enable = true
			this.reqWspId.request = true

			this.commonServerPost('check_wspname_id').then(response => {
				this.reqWspId.enable = true
				this.reqWspId.request = false
				this.reqWspId.code = response.code
				this.reqWspId.id = response.id
				this.reqWspId.listId = response.listId

				// Show modal if needed.
				if (this.reqWspId.listId.length > 1) {
					this.showWspListIds(this.reqWspId.listId)
				} else if (this.reqWspId.listId.length === 1) {
					this.reqWspName.status = response.status
					this.reqWspId.enable = true
				}

				this.reqWspId.message = response.message
				this.reqWspId.status = response.status

				this.workspaceId = response.id
			})
		},

		saveSettings() {
			this.success = false
			this.failure = false

			if (this.syncTimeout < this.MIN_TIMEOUT
				|| this.syncTimeout > this.MAX_SYNC_TIMEOUT
				|| this.asyncTimeout < this.MIN_TIMEOUT
				|| this.asyncTimeout > this.MAX_ASYNC_TIMEOUT
				|| this.cronInterval < this.MIN_CRON_INTERVAL
				|| this.cronInterval > this.MAX_CRON_INTERVAL) {
				this.failure = true
				return
			}

			const baseUrl = generateUrl('/apps/yumisign_nextcloud')

			axios.post(baseUrl + '/settings', {
				api_key: this.apiKey,
				async_timeout: this.asyncTimeout,
				cron_interval: this.cronInterval,
				default_domain: this.defaultDomain,
				description: this.description,
				enable_demo_mode: this.enableDemoMode,
				proxy_host: this.proxyHost,
				proxy_password: this.proxyPassword,
				proxy_port: this.proxyPort,
				proxy_username: this.proxyUsername,
				server_url: this.serverUrl,
				sign_scope: this.signScope,
				signed_file: this.signedFile,
				sync_timeout: this.syncTimeout,
				use_proxy: this.useProxy,
				user_settings: this.userSettings,
				watermark_text: this.watermarkText,
				workspace_id: this.workspaceId,
				workspace_name: this.workspaceName,
			})
				.then(response => {
					this.success = true
					this.saved = true
				})
				.catch(error => {
					this.failure = true
					this.saved = false
					// eslint-disable-next-line
					console.log(error)
				})
		},

		async showWspListIds(listId) {
			const ok = await this.$refs.WspListIds.show({
				title: t('yumisign_nextcloud', 'Choose workspace ID'),
				message: t('yumisign_nextcloud', 'The given workspace name corresponds to several Ids. Please click on the correct ID.'),
				items: listId,
				cancelButton: t('yumisign_nextcloud', 'Close'),
				okButton: '',
				updateId: this.updateId,
			})

			if (ok) {
				// eslint-disable-next-line
				console.log('OK')
			}
		},

		testConnection() {
			this.reqServerUrl.enable = true
			this.reqServerUrl.request = true

			this.commonServerPost('check_server_url').then(response => {
				this.reqServerUrl.enable = true
				this.reqServerUrl.request = false
				this.reqServerUrl.code = response.code
				this.reqServerUrl.message = response.message
				this.reqServerUrl.status = response.status
			})
		},

		updateId(wspId) {
			this.workspaceId = wspId
		},
	},
}

</script>

<style>
	@import '../css/settings.css';
</style>

<style scoped>
#yumisign_nextcloud_main {
	padding-left: 10px;
	padding-top: 10px;
}

/* label,
input {
	display: inline-block;
	width: 320px;
}

label {
	width: 230px;
} */

input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
	-webkit-appearance: none;
}

.message_status {
	padding: 6px 15px;
	border-radius: 3px;
}

.error {
	background: var(--color-error);
}

.success {
	background: var(--color-success);
}

.server_message {
	border: solid var(--color-success) 1px;
	display: inline-block;
	padding: 5px;
}

#save_success, .chk_success {
	color: #47c647;
}

#save_failure, .chk_error {
	color: #c64747;
}

.save_warning {
	color: #c69347;
}

.status_loader {
	margin-bottom: -6px;
	margin-left: -4px;
	width: 24px;
	height: 24px;
}

#yumisign_server_url {
	margin-top: 30px;
}

span.deleteicon {
	position: relative;
	display: inline-flex;
	align-items: center;
}

span.deleteicon span {
	position: absolute;
	display: block;
	right: 10px;
	width: 15px;
	height: 15px;
	border-radius: 50%;
	color: var(--color-text-maxcontrast);
	background-color: var(--color-background-dark);
	font: 13px monospace;
	text-align: center;
	line-height: 1em;
	cursor: pointer;
}

span.deleteicon input {
	padding-right: 18px;
	box-sizing: border-box;
}

@keyframes fadeIn {
	from {
		opacity: 0;
	}

	to {
		opacity: 0.5;
	}
}

.status_request {
	animation: fadeIn 1s ease-in infinite alternate;
}
</style>

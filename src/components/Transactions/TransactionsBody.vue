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
	<rcdevsTransactionsListingContainer>
		<rcdevsTransactionsListing>
			<rcdevsTransactionsListingHeader>
				<rcdevsTransactionsListingRow :status="status">
					<rcdevsTransactionsListingCell class="rcdevsListingDocumentName">{{ getT('Document name') }}</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell class="rcdevsListingSignature">{{ getT('Signature') }}</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell class="rcdevsListingRecipient">{{ getT('Recipient') }}</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell class="rcdevsListingCreationDate">{{ getT('Creation date') }}</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell class="rcdevsListingExpirationDate" v-if="status !== 'completed'">{{ getT('Expiration date') }}</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell class="">&nbsp;</rcdevsTransactionsListingCell>
				</rcdevsTransactionsListingRow>
			</rcdevsTransactionsListingHeader>
			<rcdevsTransactionsListingBody>
				<rcdevsTransactionsListingRow v-for="(transaction, index) in transactions" :key="index" :class="['yms_' + transaction.transactions[0].envelope_id]" :status="status">
					<rcdevsTransactionsListingCell class="listingDocument">{{ transaction.transactions[0].file_path }}</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell class="listingSignature">
						<!-- <svg :data-src="`ui.pictures.${transaction.transactions[0].signature_type}`"></svg> -->
						<img v-if="transaction.transactions[0].signature_type === 'advanced'" id="signatureType" :src="ui.pictures.advanced" />
						<img v-if="transaction.transactions[0].signature_type === 'qualified'" id="signatureType" :src="ui.pictures.qualified" />
						<img v-if="transaction.transactions[0].signature_type === 'standard'" id="signatureType" :src="ui.pictures.standard" />
					</rcdevsTransactionsListingCell>

					<rcdevsTransactionsListingCell class="listingRecipients">
						<rcdevsRecipients v-for="(transactionTr, indexTr) in transaction.transactions" :key="indexTr" :class="['multipleRows yms_' + transaction.transactions[0].envelope_id + '_' + (transaction.transactions.length === 1 ? 'all' : indexTr)]">
							<rcdevsUnitRecipient>{{ transactionTr.recipient }}</rcdevsUnitRecipient>
						</rcdevsRecipients>
					</rcdevsTransactionsListingCell>

					<rcdevsTransactionsListingCell class="listingCreation">{{ transaction.transactions[0].created | moment }}</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell class="listingExpiration" v-if="status !== 'completed'">{{ transaction.transactions[0].expiry_date | moment }}</rcdevsTransactionsListingCell>

					<rcdevsTransactionsListingCell v-if="status === 'pending'" class="listingActions cancelButton">
						<div v-if="canceling[index]" class="loaderWrap">
							<div class="icon-loading-small loaderIcon" />
							<span class="statusText">{{ ui.messages.canceling }}</span>
						</div>

						<button v-else @click="cancelTransaction(transaction.transactions[0].envelope_id)">
							{{ getT('Cancel') }}
						</button>
					</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell v-else class="listingActions cancelButton">
						<div v-if="deleting[index]" class="loaderWrap">
							<div class="icon-loading-small loaderIcon" />
							<span class="statusText">{{ ui.messages.deleting }}</span>
						</div>

						<button v-else @click="forceCancellation(transaction.transactions[0].envelope_id)">
							{{ getT('Delete') }}
						</button>
					</rcdevsTransactionsListingCell>
				</rcdevsTransactionsListingRow>

				<!-- Empty row if array empty -->
				<rcdevsTransactionsListingRow v-if="!transactions.length">
					<rcdevsTransactionsListingCell class="listingDocument">&nbsp;</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell class="listingSignature">&nbsp;</rcdevsTransactionsListingCell>

					<rcdevsTransactionsListingCell class="listingRecipients">&nbsp;</rcdevsTransactionsListingCell>

					<rcdevsTransactionsListingCell class="listingCreation">&nbsp;</rcdevsTransactionsListingCell>
					<rcdevsTransactionsListingCell class="listingExpiration" v-if="status !== 'completed'">&nbsp;</rcdevsTransactionsListingCell>

					<rcdevsTransactionsListingCell class="listingActions">&nbsp;</rcdevsTransactionsListingCell>
				</rcdevsTransactionsListingRow>
			</rcdevsTransactionsListingBody>
		</rcdevsTransactionsListing>

		<rcdevsTransactionsListingPagination if="transactions.length">
			<Paginate v-show="pageCount > 1 && transactions.length" :page-count="pageCount" :page-range="3" :margin-pages="2" :click-handler="changePage" :prev-text="getT('Previous')" :next-text="getT('Next')" :container-class="'paginationContainer'" :page-class="'paginationPage'" :prev-class="'paginationPage'" :next-class="'paginationPage'" />
		</rcdevsTransactionsListingPagination>

		<ConfirmDialogue ref="ConfirmDialogue" />
	</rcdevsTransactionsListingContainer>
</template>

<script>
import '../../styles/rcdevsListing.css';
import {appName, baseUrl, nbItemsPerPage, timestamp} from '../../javascript/config.js';
import {generateFilePath, generateOcsUrl} from '@nextcloud/router';
import {getOcsUrl, getT, isIssueResponse, isValidResponse, log} from '../../javascript/utility.js';
import axios from '@nextcloud/axios';
import ConfirmDialogue from './CancelTransactionForce.vue';
import moment from 'moment';
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js';
import Paginate from 'vuejs-paginate';

export default {
	name: 'TransactionsBody',
	props: {
		status: String,
		// transactions: Array,
	},

	components: {
		NcEmptyContent,
		Paginate,
		ConfirmDialogue,
	},

	filters: {
		moment(date) {
			return moment(date * 1000).format(`${timestamp}`);
		},
	},

	data() {
		this.apis = [];
		this.apis.uiItemsPage = '/ui/items/page';
		this.apis.transactions = '/transactions';

		this.ui = [];
		this.ui.axios = [
			{
				requestCancelled: getT('Transaction canceled'),
			},
		];

		this.ui.messages = [];
		this.ui.messages.canceling = getT('Canceling...');
		this.ui.messages.deleting = getT('Deleting...');
		this.ui.messages.retrieving = getT('Retrieving...');

		this.ui.pictures = [];
		this.ui.pictures.advanced = generateFilePath(appName, '', 'img/') + 'signTypeAdvanced.svg';
		this.ui.pictures.qualified = generateFilePath(appName, '', 'img/') + 'signTypeQualified.svg';
		this.ui.pictures.standard = generateFilePath(appName, '', 'img/') + 'signTypeStandard.svg';

		return {
			getT: getT,
			pageCount: null,
			requesting: false,
			transactions: [],
			canceling: [],
			deleting: [],

			axiosSrvRequest: {
				abortCtrl: null,
				inProgress: false,
				success: false,
				error: false,
				message: null,
			},

			rcdevsItemsPerPage: {
				retrieved: false,
				validated: false,
			},

			rcdevsTransactions: {
				retrieved: false,
				validated: false,
			},
		};
	},
	created() {
		const CancelToken = axios.CancelToken;
		this.source = CancelToken.source();
	},
	mounted() {
		this.NB_ITEMS_PER_PAGE = nbItemsPerPage; // default value if API call fails
		this.loadingImg = generateFilePath('core', '', 'img/') + 'loading.gif';
		this.requesting = true;

		this.axiosItemsPerPage();
	},

	methods: {
		axiosItemsPerPage: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosSrvRequest = this.initAxios();

				log.info(`Contact server to get nb items per page (${this.status})`);

				axios
					.get(getOcsUrl(this.apis.uiItemsPage), {
						signal: this.axiosSrvRequest.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`[${this.getFunctionName()}], Response for ${JSON.stringify(response.data)}`);

						if (isIssueResponse(response)) {
							throw new Error(`No property named "code" in Axios response`);
						}

						// Check results ItemsPerPage
						if (!response.data.hasOwnProperty('itemsPerPage')) {
							throw new Error('Number of items per page is missing');
						}

						log.debug(`isValidResponse(response):[${isValidResponse(response)}]`);

						this.axiosSrvRequest.error = !(this.axiosSrvRequest.success = true);
						this.axiosSrvRequest.message = response.data.message;

						this.rcdevsItemsPerPage.validated = isValidResponse(response);

						this.NB_ITEMS_PER_PAGE = response.data.itemsPerPage;
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosSrvRequest.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosSrvRequest.message = exception.message;
						}

						this.axiosSrvRequest.error = !(this.rcdevsItemsPerPage.validated = false);
					})
					.finally(() => {
						this.axiosSrvRequest.inProgress = false;
						this.rcdevsItemsPerPage.retrieved = true;

						log.debug(`this.rcdevsItemsPerPage.validated:[${this.rcdevsItemsPerPage.validated}]`);
						this.refreshUiVariables();

						log.debug(`this.rcdevsItemsPerPage.validated:[${this.rcdevsItemsPerPage.validated}]`);
						if (this.rcdevsItemsPerPage.validated) {
							log.info(`Retrieve transactions with status ${this.status}`);
							this.axiosTransactions();
						} else {
							log.info(`Nothing to do with status ${this.status}`);
						}
					});
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},

		axiosTransactions: function () {
			try {
				log.debug(`[${this.getFunctionName()}] Running...`);
				this.axiosSrvRequest = this.initAxios();

				log.info(`Contact server to get Transactions (${this.status})`);

				axios
					.get(getOcsUrl(`${this.apis.transactions}/${this.status}?nbItems=${this.NB_ITEMS_PER_PAGE}`), {
						signal: this.axiosSrvRequest.abortCtrl.signal,
					})
					.then((response) => {
						log.debug(`[${this.getFunctionName()}], Response for ${JSON.stringify(response.data)}`);

						if (isIssueResponse(response)) {
							throw new Error(`No property named "code" in Axios response`);
						}
						log.debug(`isValidResponse(response):[${isValidResponse(response)}]`);

						this.axiosSrvRequest.error = !(this.axiosSrvRequest.success = true);
						this.axiosSrvRequest.message = response.data.message;

						this.rcdevsTransactions.validated = isValidResponse(response);

						this.pageCount = Math.ceil(response.data.data.count / this.NB_ITEMS_PER_PAGE);
						log.debug(`this.pageCount:[${this.pageCount}]`);

						this.canceling.splice(response.data.data.transactions.length);
						this.deleting.splice(response.data.data.transactions.length);
						this.transactions = this.getTransactions(response.data.data.transactions);
					})
					.catch((exception) => {
						if (axios.isCancel(exception)) {
							this.axiosSrvRequest.message = this.ui.axios.requestCancelled;
						} else {
							this.axiosSrvRequest.message = exception.message;
						}

						this.axiosSrvRequest.error = !(this.rcdevsTransactions.validated = false);
					})
					.finally(() => {
						this.axiosSrvRequest.inProgress = false;
						this.rcdevsTransactions.retrieved = true;
						this.refreshUiVariables();
					});
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

		async cancelTransaction(envelopeId) {
			const ok = await this.$refs.ConfirmDialogue.show({
				title: this.getT('Cancel pending signature'),
				message: t(appName, 'Are you sure you want to cancel this signature request ?'),
				cancelButton: this.getT('Go back'),
				okButton: this.getT('Cancel'),
			});
			if (ok) {
				let urlTransaction = generateOcsUrl(baseUrl + '/api/v1/transaction/cancel');
				axios
					.put(urlTransaction, {
						envelopeId,
					})
					.then((response) => {
						log.debug(`response.data.code:[${response.data.code}]`);

						switch (response.data.code) {
							case 'WORKFLOW_NOT_FOUND':
								// Ask to force deletion in database
								this.forceCancellation(envelopeId);
								break;
							case 'already_cancelled':
								this.deleteTableRows(this.transactions, envelopeId);
								break;
							case '1':
							case 1:
								this.deleteTableRows(this.transactions, envelopeId);
								break;
							case true:
								this.deleteTableRows(this.transactions, envelopeId);
								break;
							default:
								alert(response.data.message);
								break;
						}
					})
					.catch((error) => {
						alert('Cancellation process failed.\n' + error);
					});
			}
		},

		changePage(pageNum) {
			this.requesting = true;
			this.transactions = [];

			let urlTransaction = generateOcsUrl(baseUrl + '/api/v1/transactions/' + this.status);

			axios({
				url: `${urlTransaction}?&page=${pageNum - 1}&nbItems=${this.NB_ITEMS_PER_PAGE}`,
				method: 'GET',
				// timeout: 10000,
			})
				.then((response) => {
					this.requesting = false;
					this.pageCount = Math.ceil(response.data.data.count / this.NB_ITEMS_PER_PAGE);
					this.canceling.splice(response.data.data.transactions.length);
					this.deleting.splice(response.data.data.transactions.length);
					this.transactions = this.getTransactions(response.data.data.transactions);
				})
				.catch((error) => {
					this.requesting = false;
					console.log(error);
				});
		},

		deleteTableRows(transactions, envelopeId) {
			for (let cpt = transactions.length - 1; cpt >= 0; cpt--) {
				if (transactions[cpt].transactions[0].envelope_id.toLowerCase() === envelopeId.toLowerCase()) {
					this.transactions.splice(cpt, 1);
				}
			}
		},

		async forceCancellation(envelopeId) {
			const ok = await this.$refs.ConfirmDialogue.show({
				title: this.getT('Force cancellation'),
				message: t(appName, 'This transaction is not found on YumiSign.\nDo you want to force deletion on Nextcloud database ?'),
				cancelButton: this.getT('Go back'),
				okButton: this.getT('Force deletion'),
			});

			if (ok) {
				let urlTransaction = generateOcsUrl(baseUrl + '/api/v1/transaction/deletion');
				axios
					.put(urlTransaction, {
						envelopeId,
					})
					.then((response) => {
						if (response.data.code === '1') {
							this.deleteTableRows(this.transactions, envelopeId);
						} else {
							alert('Forcing cancellation failed.\n' + response.data.message + '\ncode:' + response.data.code);
						}
					})
					.catch((error) => {
						alert('Forcing cancellation process failed.\n' + error);
					});
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

		getTransactions(responseTransactions) {
			// Group recipients for the same transaction (only one line per transaction)
			const tmpTransactions = {};
			const returnTransactions = [];

			responseTransactions.forEach((ymsTransaction) => {
				if (!tmpTransactions[ymsTransaction.envelope_id]) {
					// envelope_id not found
					const transactionArray = [ymsTransaction];
					tmpTransactions[ymsTransaction.envelope_id] = {
						transactions: transactionArray,
					};
				} else {
					// envelope_id found, append
					tmpTransactions[ymsTransaction.envelope_id].transactions.push(ymsTransaction);
				}
			});

			// Convert object into an array
			Object.keys(tmpTransactions).forEach((key) => {
				returnTransactions.push(tmpTransactions[key]);
			});

			return returnTransactions;
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
				this.axiosSrvRequest.success = this.isSrvRequestSuccess();
				this.axiosSrvRequest.inProgress = this.isSrvRequestInProgress();
			} catch (exception) {
				log.error(`[${this.getFunctionName()}] ${exception}`);
			}
		},
	},
};
</script>

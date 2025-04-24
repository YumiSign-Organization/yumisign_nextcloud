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
	<rcdevsTransactionsContainer>
		<rcdevsRequestLoadeer v-if="requesting">
			<img :src="loadingImg" />
		</rcdevsRequestLoadeer>

		<TransactionsBody v-else :status="status" :transactions="transactions" />
	</rcdevsTransactionsContainer>
</template>

<script>
import '../../styles/rcdevsListing.css';
import {appName, baseUrl, timestamp} from '../../javascript/config.js';
import {generateOcsUrl} from '@nextcloud/router';
import {getOcsUrl, getT} from '../../javascript/utility.js';
import axios from '@nextcloud/axios';
import ConfirmDialogue from './CancelTransactionForce.vue';
import moment from 'moment';
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js';
import Paginate from 'vuejs-paginate';
import TransactionsBody from './TransactionsBody.vue';

export default {
	name: 'Transactions',
	props: {
		status: String,
	},

	components: {
		ConfirmDialogue,
		NcEmptyContent,
		Paginate,
		TransactionsBody,
	},
	filters: {
		moment(date) {
			return moment(date * 1000).format(`${timestamp}`);
		},
	},
	data() {
		this.apis = [];
		this.apis.uiItemsPage = '/ui/items/page';
		return {
			getT: getT,
			pageCount: null,
			requesting: false,
			transactions: [],
			canceling: [],
		};
	},
	created() {
		const CancelToken = axios.CancelToken;
		this.source = CancelToken.source();
	},
	methods: {
		runAPI: function (urlApi) {
			try {
				return axios
					.get(
						getOcsUrl(urlApi),
						{},
						{
							cancelToken: this.source.token,
						}
					)
					.catch((error) => {
						this.error = true;
						console.log(error);
					});
			} catch (error) {
				console.error(error.message);
			}
		},

		async cancelTransaction(index, envelopeId) {
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
						switch (response.data.code) {
							case 'WORKFLOW_NOT_FOUND':
								// Ask to force deletion in database
								this.forceCancellation(envelopeId);
								break;
							case 'already_cancelled':
								this.deleteTableRows(this.transactions, envelopeId);
								break;
							case '1':
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

			let urlTransaction = generateOcsUrl(baseUrl + '/api/v1/transactions/pendings');

			// axios
			// .get(
			// `${urlTransaction}?&page=${pageNum - 1}&nbItems=${this.NB_ITEMS_PER_PAGE}`
			// )
			axios({
				url: `${urlTransaction}?&page=${pageNum - 1}&nbItems=${this.NB_ITEMS_PER_PAGE}`,
				method: 'POST',
				// timeout: 10000,
			})
				.then((response) => {
					this.requesting = false;
					this.pageCount = Math.ceil(response.data.count / this.NB_ITEMS_PER_PAGE);
					this.canceling.splice(response.data.transactions.length);
					this.transactions = this.getTransactions(response.data.transactions);
				})
				.catch((error) => {
					this.requesting = false;
					// eslint-disable-next-line
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
	},
};
</script>

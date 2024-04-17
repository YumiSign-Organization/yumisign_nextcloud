<template>
	<div class="component-container">
		<div v-if="requesting">
			<img :src="loadingImg">
		</div>
		<EmptyContent v-else-if="!requests.length" icon="icon-comment">
			{{ $t('yumisign_nextcloud', 'No signature requests issues') }}
			<template #desc>
				{{ $t('yumisign_nextcloud', 'There are currently no signature requests issues') }}
			</template>
		</EmptyContent>
		<table v-else
			class="listings">
			<thead>
				<tr>
					<th>
						{{ $t('yumisign_nextcloud', 'Creation date') }}
					</th>
					<th>
						{{ $t('yumisign_nextcloud', 'Expiration date') }}
					</th>
					<th>
						{{ $t('yumisign_nextcloud', 'Transaction status') }}
					</th>
					<th>
						{{ $t('yumisign_nextcloud', 'File') }}
					</th>
					<th>
						{{ $t('yumisign_nextcloud', 'Recipient') }}
					</th>
					<th>
						{{ $t('yumisign_nextcloud', 'Recipient status') }}
					</th>
					<th>&nbsp;</th>
					<!-- <th>&nbsp;</th> -->
				</tr>
			</thead>
			<tbody>
				<tr v-for="(request, index) in requests" :key="index" :class="['yms_' + request.transactions[0].envelope_id]">
					<td>{{ request.transactions[0].created | moment }}</td>
					<td>{{ request.transactions[0].expiry_date | moment }}</td>
					<td>{{ $t('yumisign_nextcloud', request.transactions[0].global_status) }}</td>
					<td>{{ request.transactions[0].file_path }}</td>
					<td>
						<div v-for="(transaction, indexTr) in request.transactions" :key="indexTr" :class="['multipleRows yms_' + request.transactions[0].envelope_id + '_' + (request.transactions.length === 1 ? 'all' : indexTr)]">
							<span>{{ transaction.recipient }}</span>
						</div>
					</td>
					<td>
						<div v-for="(transaction, indexTr) in request.transactions" :key="indexTr" :class="['multipleRows yms_' + request.transactions[0].envelope_id + '_' + (request.transactions.length === 1 ? 'all' : indexTr)]">
							<span>{{ $t('yumisign_nextcloud', transaction.status) }}</span>
							<img v-if="deleting[index]" :src="loadingImg">
							<span v-else class="recipient-delete" @click="deleteRequest(index, request.transactions[0].envelope_id, transaction.recipient, 'yms_' + request.transactions[0].envelope_id + '_' + (request.transactions.length === 1 ? 'all' : indexTr))" />
						</div>
					</td>
					<td>
						<img v-if="deleting[index]" :src="loadingImg">
						<button v-else @click="deleteRequest(index, request.transactions[0].envelope_id, false, false)">
							{{ $t('yumisign_nextcloud', 'Delete YumiSign request') }}
						</button>
					</td>
				</tr>
			</tbody>
		</table>
		<Paginate v-show="pageCount > 1 && requests.length"
			:page-count="pageCount"
			:page-range="3"
			:margin-pages="2"
			:click-handler="changePage"
			:prev-text="$t('yumisign_nextcloud', 'Previous')"
			:next-text="$t('yumisign_nextcloud', 'Next')"
			:container-class="'pagination'" />
		<ConfirmDialogue ref="ConfirmDialogue" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl, generateFilePath } from '@nextcloud/router'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import Paginate from 'vuejs-paginate'
import moment from 'moment'
import ConfirmDialogue from './components/CancelRequestForce.vue'

export default {
	name: 'Issues',
	components: {
		EmptyContent,
		Paginate,
		ConfirmDialogue,
	},
	filters: {
		moment(date) {
			return moment(date * 1000).format('YYYY-MM-DD HH:mm:ss')
		},
	},
	data() {
		return {
			pageCount: null,
			requesting: false,
			requests: [],
			deleting: [],
		}
	},
	created() {
		this.NB_ITEMS_PER_PAGE = 20
	},
	mounted() {
		this.loadingImg = generateFilePath('core', '', 'img/') + 'loading.gif'
		this.requesting = true

		const baseUrl = generateUrl('/apps/yumisign_nextcloud')
		axios.get(baseUrl + '/issues_requests?nbItems=' + this.NB_ITEMS_PER_PAGE)
			.then(response => {
				this.requesting = false
				this.pageCount = Math.ceil(response.data.count / this.NB_ITEMS_PER_PAGE)
				this.deleting.splice(response.data.requests.length)
				this.requests = this.getRequests(response.data.requests)
			})
			.catch(error => {
				this.requesting = false
				// eslint-disable-next-line
				console.log(error)
			})
	},
	methods: {
		changePage(pageNum) {
			this.requesting = true
			this.requests = []

			const baseUrl = generateUrl('/apps/yumisign_nextcloud')

			axios.get(baseUrl + '/issues_requests?page=' + (pageNum - 1) + '&nbItems=' + this.NB_ITEMS_PER_PAGE)
				.then(response => {
					this.requesting = false
					this.pageCount = Math.ceil(response.data.count / this.NB_ITEMS_PER_PAGE)
					this.deleting.splice(response.data.requests.length)
					this.requests = this.getRequests(response.data.requests)
				})
				.catch(error => {
					this.requesting = false
					// eslint-disable-next-line
					console.log(error)
				})
		},

		async deleteRequest(index, envelopeId, recipient, tagToDelete) {
			const ok = await this.$refs.ConfirmDialogue.show({
				title: t('yumisign_nextcloud', 'Deleting signature issue'),
				message: t('yumisign_nextcloud', 'Are you sure you want to delete this signature issue ?'),
				cancelButton: t('yumisign_nextcloud', 'Go back'),
				okButton: t('yumisign_nextcloud', 'Delete issue'),
			})
			if (recipient === false) { recipient = '' }
			if (ok) {
				const baseUrl = generateUrl('/apps/yumisign_nextcloud')
				axios.put(baseUrl + '/force_deletion_request', {
					envelopeId,
					recipient,
				})
					.then(response => {
						switch (response.data.code) {
						case '1':
							this.deleteTableRows(this.requests, envelopeId, recipient, tagToDelete)
							break
						case true:
							this.deleteTableRows(this.requests, envelopeId, recipient, tagToDelete)
							break
						default:
							alert(response.data.message)
							break
						}
					})
					.catch(error => {
						alert(t('yumisign_nextcloud', 'Deletion process failed') + '\n' + error)
					})
			}
		},

		deleteTableRows(requests, envelopeId, recipient, tagToDelete) {
			if (!tagToDelete) {
				// Remove the entire row (means the Transaction with all recipients)
				document.querySelectorAll('.yms_' + envelopeId).forEach(el => el.remove())
			} else {
				// Remove only the recipient; Special: if only one recipient (tag ends with '_all'), remove the entire row
				if (tagToDelete.substr(tagToDelete.length - 4).toLowerCase() === '_all') {
					document.querySelectorAll('.yms_' + envelopeId).forEach(el => el.remove())
				} else {
					document.querySelectorAll('.' + tagToDelete).forEach(el => el.remove())
				}
			}
		},

		getRequests(responseRequests) {
			// Group recipients for the same transaction (only one line per transaction)
			const tmpRequests = {}
			const returnRequests = []

			responseRequests.forEach(ymsTransaction => {
				if (!tmpRequests[ymsTransaction.envelope_id]) {
					// envelope_id not found
					const transactionArray = [ymsTransaction]
					tmpRequests[ymsTransaction.envelope_id] = { transactions: transactionArray }
				} else {
					// envelope_id found, append
					tmpRequests[ymsTransaction.envelope_id].transactions.push(ymsTransaction)
				}
			})

			// Convert object into an array
			Object.keys(tmpRequests).forEach(key => {
				returnRequests.push(tmpRequests[key])
			})

			return returnRequests
		},
	},
}
</script>

<template>
	<div class="component-container">
		<h1>{{ $t('yumisign_nextcloud', 'Signature requests issues') }}</h1>
		<div v-if="requesting">
			<img :src="loadingImg">
		</div>
		<EmptyContent v-else-if="!requests.length" icon="icon-comment">
			{{ $t('yumisign_nextcloud', 'No signature requests issues') }}
			<template #desc>
				{{ $t('yumisign_nextcloud', 'There are currently no signature requests issues') }}
			</template>
		</EmptyContent>
		<table v-else>
			<thead>
				<tr>
					<th>{{ $t('yumisign_nextcloud', 'Creation date') }}</th>
					<th>{{ $t('yumisign_nextcloud', 'Expiration date') }}</th>
					<th>{{ $t('yumisign_nextcloud', 'Transaction status') }}</th>
					<th>{{ $t('yumisign_nextcloud', 'Recipient') }}</th>
					<th>{{ $t('yumisign_nextcloud', 'Recipient status') }}</th>
					<th style="width: 100%">
						{{ $t('yumisign_nextcloud', 'File') }}
					</th>
					<th>&nbsp;</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(request, index) in requests" :key="index" :class="[request.envelope_id]">
					<td>{{ request.created | moment }}</td>
					<td>{{ request.expiry_date | moment }}</td>
					<td>{{ request.global_status }}</td>
					<td>{{ request.recipient }}</td>
					<td>{{ request.status }}</td>
					<td>{{ request.file_path }}</td>
					<td>
						<img v-if="deleting[index]" :src="loadingImg">
						<button v-else @click="deleteRequest(index, request.envelope_id, request.recipient)">
							{{ $t('yumisign_nextcloud', 'Delete recipient request') }}
						</button>
					</td>
					<td>
						<img v-if="deleting[index]" :src="loadingImg">
						<button v-else @click="deleteRequest(index, request.envelope_id, false)">
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
				this.requests = response.data.requests
			})
			.catch(error => {
				this.requesting = false
				// eslint-disable-next-line
				console.log(error)
			})
	},
	methods: {
		deleteTableRows(requests, envelopeId, recipient) {
			for (let cpt = this.requests.length - 1; cpt >= 0; cpt--) {
				let recipientOK = false
				if (recipient.toLowerCase() === '') {
					recipientOK = true
				} else {
					recipientOK = (requests[cpt].recipient.toLowerCase() === recipient.toLowerCase())
				}
				if (requests[cpt].envelope_id.toLowerCase() === envelopeId.toLowerCase() && recipientOK) {
					requests.splice(cpt, 1)
				}
			}
		},
		async deleteRequest(index, envelopeId, recipient) {
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
							this.deleteTableRows(this.requests, envelopeId, recipient)
							break
						case true:
							this.deleteTableRows(this.requests, envelopeId, recipient)
							break
						default:
							alert(response.data.message)
							break
						}
					})
					.catch(error => {
						alert('Deletion process failed.\n' + error)
					})
			}
		},
		changePage(pageNum) {
			this.requesting = true
			this.requests = []

			const baseUrl = generateUrl('/apps/yumisign_nextcloud')

			axios.get(baseUrl + '/issues_requests?page=' + (pageNum - 1) + '&nbItems=' + this.NB_ITEMS_PER_PAGE)
				.then(response => {
					this.requesting = false
					this.pageCount = Math.ceil(response.data.count / this.NB_ITEMS_PER_PAGE)
					this.deleting.splice(response.data.requests.length)
					this.requests = response.data.requests
				})
				.catch(error => {
					this.requesting = false
					// eslint-disable-next-line
					console.log(error)
				})
		},
	},
}
</script>

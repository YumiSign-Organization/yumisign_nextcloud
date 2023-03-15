<template>
	<div class="component-container">
		<h1>{{ $t('yumisign_nextcloud', 'Pending signature requests') }}</h1>
		<div v-if="requesting">
			<img :src="loadingImg">
		</div>
		<EmptyContent v-else-if="!requests.length" icon="icon-comment">
			{{ $t('yumisign_nextcloud', 'No pending signature requests') }}
			<template #desc>
				{{ $t('yumisign_nextcloud', 'There are currently no pending signature requests') }}
			</template>
		</EmptyContent>
		<table v-else>
			<thead>
				<tr>
					<th>{{ $t('yumisign_nextcloud', 'Creation date') }}</th>
					<th>{{ $t('yumisign_nextcloud', 'Expiration date') }}</th>
					<th>{{ $t('yumisign_nextcloud', 'Recipient') }}</th>
					<th style="width: 100%">
						{{ $t('yumisign_nextcloud', 'File') }}
					</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(request, index) in requests" :key="index" :class="[request.envelope_id]">
					<td>{{ request.created | moment }}</td>
					<td>{{ request.expiry_date | moment }}</td>
					<td>{{ request.recipient }}</td>
					<td>{{ request.file_path }}</td>
					<td>
						<img v-if="canceling[index]" :src="loadingImg">
						<button v-else @click="cancelRequest(index, request.envelope_id)">
							{{ $t('yumisign_nextcloud', 'Cancel request') }}
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
	name: 'Pending',
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
			canceling: [],
		}
	},
	created() {
		this.NB_ITEMS_PER_PAGE = 20
	},
	mounted() {
		this.loadingImg = generateFilePath('core', '', 'img/') + 'loading.gif'
		this.requesting = true

		const baseUrl = generateUrl('/apps/yumisign_nextcloud')
		axios.get(baseUrl + '/pending_requests?nbItems=' + this.NB_ITEMS_PER_PAGE)
			.then(response => {
				this.requesting = false
				this.pageCount = Math.ceil(response.data.count / this.NB_ITEMS_PER_PAGE)
				this.canceling.splice(response.data.requests.length)
				this.requests = response.data.requests
			})
			.catch(error => {
				this.requesting = false
				// eslint-disable-next-line
				console.log(error)
			})
	},
	methods: {
		deleteTableRows(requests, envelopeId) {
			for (let cpt = this.requests.length - 1; cpt >= 0; cpt--) {
				if (requests[cpt].envelope_id.toLowerCase() === envelopeId.toLowerCase()) {
					requests.splice(cpt, 1)
				}
			}
		},
		async forceCancellation(envelopeId) {
			const ok = await this.$refs.ConfirmDialogue.show({
				title: t('yumisign_nextcloud', 'Force cancellation'),
				message: t('yumisign_nextcloud', 'This pending signature is not found on YumiSign.\nDo you want to force deletion on Nextcloud database ?'),
				cancelButton: t('yumisign_nextcloud', 'Go back'),
				okButton: t('yumisign_nextcloud', 'Force deletion'),
			})

			if (ok) {
				const baseUrl = generateUrl('/apps/yumisign_nextcloud')
				axios.put(baseUrl + '/force_deletion_request', {
					envelopeId,
				})
					.then(response => {
						if (response.data.code === '1') {
							this.deleteTableRows(this.requests, envelopeId)
						} else {
							alert('Forcing cancellation failed.\n' + response.data.message + '\ncode:' + response.data.code)
						}
					})
					.catch(error => {
						alert('Forcing cancellation process failed.\n' + error)
					})
			}
		},
		async cancelRequest(index, envelopeId) {
			const ok = await this.$refs.ConfirmDialogue.show({
				title: t('yumisign_nextcloud', 'Cancel pending signature'),
				message: t('yumisign_nextcloud', 'Are you sure you want to cancel this signature request ?'),
				cancelButton: t('yumisign_nextcloud', 'Go back'),
				okButton: t('yumisign_nextcloud', 'Cancel pending'),
			})
			if (ok) {
				const baseUrl = generateUrl('/apps/yumisign_nextcloud')
				axios.put(baseUrl + '/cancel_sign_request', {
					envelopeId,
				})
					.then(response => {
						switch (response.data.code) {
						case 'WORKFLOW_NOT_FOUND':
							// Ask to force deletion in database
							this.forceCancellation(envelopeId)
							break
						case '1':
							this.deleteTableRows(this.requests, envelopeId)
							break
						case true:
							this.deleteTableRows(this.requests, envelopeId)
							break
						default:
							alert(response.data.message)
							break
						}
					})
					.catch(error => {
						alert('Cancellation process failed.\n' + error)
					})
			}
		},
		changePage(pageNum) {
			this.requesting = true
			this.requests = []

			const baseUrl = generateUrl('/apps/yumisign_nextcloud')

			axios.get(baseUrl + '/pending_requests?page=' + (pageNum - 1) + '&nbItems=' + this.NB_ITEMS_PER_PAGE)
				.then(response => {
					this.requesting = false
					this.pageCount = Math.ceil(response.data.count / this.NB_ITEMS_PER_PAGE)
					this.canceling.splice(response.data.requests.length)
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

<template>
	<section class="signed-folder-settings" :aria-busy="loading || saving">
		<h2>{{ translate('Signed document folders') }}</h2>
		<p>{{ translate('Paths are relative to your Files root. Folders are created only when saving a successfully signed document.') }}</p>
		<form @submit.prevent="save">
			<label>
				{{ translate('As applicant') }}
				<input v-model="values.applicant"
					type="text"
					required
					:disabled="loading || saving"
					autocomplete="off">
			</label>
			<label>
				{{ translate('As recipient') }}
				<input v-model="values.recipient"
					type="text"
					required
					:disabled="loading || saving"
					autocomplete="off">
			</label>
			<p v-if="error" role="alert" class="signed-folder-settings__error">
				{{ translate('Invalid signed folder settings block signing. Correct the error to continue.') }} {{ error }}
			</p>
			<p v-if="saved" role="status">
				{{ translate('Settings saved') }}
			</p>
			<button type="submit" :disabled="loading || saving">
				{{ translate(saving ? 'Saving…' : 'Save') }}
			</button>
		</form>
	</section>
</template>

<script>
import axios from '@nextcloud/axios'

/** Shared personal settings UI: the application supplies its route and translations. */
export default {
	name: 'SignedFolderSettings',
	props: {
		endpoint: { type: String, required: true },
		translate: { type: Function, required: true },
	},
	data() {
		return { values: { applicant: '', recipient: '' }, loading: true, saving: false, error: '', saved: false }
	},
	async mounted() {
		try {
			const { data } = await axios.get(this.endpoint)
			this.values = data.values
			this.error = data.error || ''
		} catch (e) {
			this.error = e.response?.data?.message || e.message
		} finally {
			this.loading = false
		}
	},
	methods: {
		async save() {
			if (this.loading || this.saving) {
				return
			}
			this.saving = true
			this.saved = false
			this.error = ''
			try {
				const { data } = await axios.post(this.endpoint, { ...this.values })
				this.values = data.values
				this.saved = true
			} catch (e) {
				this.error = e.response?.data?.message || e.message
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.signed-folder-settings {
	margin: 30px;
	max-width: 720px;
}
.signed-folder-settings label {
	display: flex;
	flex-direction: column;
	margin: 16px 0;
}
.signed-folder-settings input {
	width: 100%;
}
.signed-folder-settings__error {
	color: var(--color-error);
}
</style>

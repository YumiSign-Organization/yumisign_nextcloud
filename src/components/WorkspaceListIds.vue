<template>
	<PopupModal ref="popup" class="ymsSettingsModal">
		<h2 style="margin-top: 0">
			{{ title }}
		</h2>
		<div class="ymsSettingsHeader">
			{{ message }}
		</div>
		<div class="ymsSettingsPartsContainer">
			<div v-for="item in items" :key="item" class="ymsSettingsList">
				<div @click="updateId(item)">
					<label>
						<input
							v-model="itemId"
							type="radio"
							name="itemId"
							autocomplete="off"
							:value="item">{{ item }}
					</label>
				</div>
			</div>
		</div>
		<div class="ymsSettingsFooter">
			<div v-if="itemId" class="">
				{{ $t('yumisign_nextcloud', 'You have selected:') }} {{ itemId }}
			</div>
			<div class="btns">
				<button @click="_cancel">
					{{ cancelButton }}
				</button>
				<button v-if="okButton !== ''" @click="_confirm">
					{{ okButton }}
				</button>
			</div>
		</div>
	</PopupModal>
</template>

<script>
import PopupModal from './PopupModal.vue'

export default {
	name: 'WorkspaceListIds',

	components: { PopupModal },

	data: () => ({
		// Parameters that change depending on the type of dialogue
		title: undefined,
		message: undefined, // Main text content
		okButton: undefined, // Text for confirm button; leave it empty because we don't know what we're using it for
		cancelButton: 'Cancel', // text for cancel button

		// Private variables
		resolvePromise: undefined,
		rejectPromise: undefined,
		itemId: undefined,
	}),

	methods: {
		show(opts = {}) {
			this.title = opts.title
			this.message = opts.message
			this.items = opts.items
			this.okButton = opts.okButton
			this.updateId = opts.updateId
			this.itemId = undefined

			if (opts.cancelButton) {
				this.cancelButton = opts.cancelButton
			}
			// Once we set our config, we tell the popup modal to open
			this.$refs.popup.open()
			// Return promise so the caller can get results
			return new Promise((resolve, reject) => {
				this.resolvePromise = resolve
				this.rejectPromise = reject
			})
		},

		updateId(item) {
			this.opts.updateId(item)
		},

		_confirm() {
			this.$refs.popup.close()
			this.resolvePromise(true)
		},

		_cancel() {
			this.$refs.popup.close()
			this.resolvePromise(false)
			// Or you can throw an error
			// this.rejectPromise(new Error('User canceled the dialogue'))
		},
	},
}
</script>

<style scoped>
.btns {
	display: flex;
	flex-direction: row;
	justify-content: space-between;
}

.ok-btn {
	/* color: red; */
	/* text-decoration: underline; */
	/* line-height: 2.5rem; */
	cursor: pointer;
}

.cancel-btn {
	padding: 0.5em 1em;
	/* background-color: #d5eae7; */
	/* color: #35907f; */
	/* border: 2px solid #0ec5a4; */
	/* border-radius: 5px; */
	/* font-weight: bold; */
	/* font-size: 16px; */
	/* text-transform: uppercase; */
	cursor: pointer;
}

.chooseId {
	border-bottom-style: solid;
	border-bottom-width: 1px;
	margin-bottom: 10px;
	padding-bottom: 5px;
}

.chooseId:last-child {
	border-bottom-style: none;
	border-bottom-width: 0px;
	padding-bottom: 5px;
}

input[type='radio']{
	vertical-align: baseline;
	height: auto;
	min-height: 1px;
}

</style>

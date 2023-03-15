import Vue from 'vue'
import { translate } from '@nextcloud/l10n'
import EventBus from './EventBus'
import YumiSignNxtCModal from './YumiSignNxtCModal'

Vue.prototype.$t = translate

const signModalHolderId = 'yumisignsignModalHolder'
const signModalHolder = document.createElement('div')
signModalHolder.id = signModalHolderId
document.body.append(signModalHolder)

// eslint-disable-next-line
new Vue({
	el: signModalHolder,
	render: h => {
		return h(YumiSignNxtCModal)
	},
})

OCA.Files.fileActions.registerAction({
	mime: 'file',
	name: 'YumiSignNxtC',
	permissions: OC.PERMISSION_READ,
	iconClass: 'icon-yumisign',
	actionHandler: (filename, context) => {
		EventBus.$emit('yumisign-sign-click', { filename })
	},
	displayName: t('yumisign_nextcloud', 'Sign with YumiSign'),
	order: -100,
})

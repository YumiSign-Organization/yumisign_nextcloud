import Vue from 'vue'
import { translate } from '@nextcloud/l10n'
import AppAdmin from './AppAdmin'

Vue.prototype.$t = translate

const adminRootElement = document.getElementById('yumisign_nextcloud-admin-root')
// eslint-disable-next-line
new Vue({
	el: '#yumisign_nextcloud-admin-root',
	data: () => Object.assign({}, adminRootElement.dataset),
	render: h => h(AppAdmin),
})

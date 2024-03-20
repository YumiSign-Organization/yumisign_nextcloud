/**
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import VueObserveVisibility from 'vue-observe-visibility'
import { Tooltip } from '@nextcloud/vue'
import { FileAction, Permission, registerFileAction } from '@nextcloud/files'

import '@nextcloud/dialogs/style.css'

import YumiSignNxtCModal from './views/YumiSignNxtCModal.vue'
import Logo from '../img/app-dark.svg?raw'
import './styles/loader.scss'

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA
Vue.prototype.OCP = OCP

Vue.directive('tooltip', Tooltip)

Vue.use(VueObserveVisibility)

const el = document.createElement('div')
document.body.appendChild(el)

const app = new Vue({
	el,
	data: {
		chosenFile: null,
	},
	render: h => h(YumiSignNxtCModal),
})

app.$on('dialog:open', (model) => {
	app.$data.chosenFile = model
})

app.$on('dialog:closed', () => {
	app.$data.chosenFile = null
})

registerFileAction(new FileAction({
	id: 'yumisign_nextcloud-sign',
	displayName: () => t('yumisign_nextcloud', 'Sign with YumiSign'),
	iconSvgInline: () => Logo,
	enabled: (files, view) => {
		return (files.length === 1
				&& files[0].mime === 'application/pdf'
				&& (files[0].permissions & (Permission.READ | Permission.WRITE)) === (Permission.READ | Permission.WRITE))
	},
	exec: (file, view, dir) => {
		app.$emit('dialog:open', file)
	},
}))

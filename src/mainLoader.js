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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
// import VueObserveVisibility from 'vue-observe-visibility'
import { Tooltip } from '@nextcloud/vue'
import { FileAction, Permission, registerFileAction } from '@nextcloud/files'

import '@nextcloud/dialogs/style.css'

import YumiSignNxtCModal from './views/YumiSignNxtCModal.vue'
import Logo from '../img/YumiSign.svg?raw'
import './styles/yumisignLoader.css'
import {getT} from './javascript/utility.js';
import {signAction} from './javascript/config.js';

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA
Vue.prototype.OCP = OCP

Vue.directive('tooltip', Tooltip)

// Vue.use(VueObserveVisibility)

const el = document.createElement('div')
document.body.appendChild(el)

// Sign menu
const appSign = new Vue({
	el,
	data: {
		action: null,
		chosenFile: null
	},
	render: h => h(YumiSignNxtCModal),
})

appSign.$on('dialog:open', (model) => {
	appSign.$data.action = signAction,
	appSign.$data.chosenFile = model
})

appSign.$on('dialog:closed', () => {
	appSign.$data.action = null,
	appSign.$data.chosenFile = null
})

registerFileAction(new FileAction({
	id: `${appName}_sign`,
	displayName: () => getT('Sign with YumiSign'),
	iconSvgInline: () => Logo,
	enabled: (files, view) => {
		return (files.length === 1
			&& (
				files[0].mime		=== 'application/pdf'															// pdf
				|| files[0].mime	=== 'application/msword'														// doc
				|| files[0].mime	=== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'	// docX
				|| files[0].mime	=== 'application/vnd.ms-excel'													// xls
				|| files[0].mime	=== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'			// xlsX
				|| files[0].mime	=== 'image/jpeg'																// jpeg & jpg
				|| files[0].mime	=== 'image/png'																	// png
				|| files[0].mime	=== 'image/gif'																	// gif
			)
			&& (files[0].permissions & (Permission.READ | Permission.WRITE)) === (Permission.READ | Permission.WRITE))
	},
	exec: (file, view, dir) => {
		appSign.$emit('dialog:open', file)
	},
}))

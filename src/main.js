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
		// EventBus.$emit('yumisign-sign-click', { filename })
		EventBus.$emit('yumisign-sign-click', { context })
	},
	displayName: t('yumisign_nextcloud', 'Sign with YumiSign'),
	order: -100,
})

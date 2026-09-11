import axios from '@nextcloud/axios'
import { CDEM, getOcsUrl } from './utility.js'
import { startUiRefreshWatcher as startSharedWatcher } from '../../rcdevsBundle/src/javascript/uiRefreshWatcher.js'

export const startUiRefreshWatcher = () => startSharedWatcher({
	applicationPath: '/apps/yumisign_nextcloud',
	transactionsEvent: 'yms:refresh:transactions',
	readSignal: async (lastToken) => {
		const response = await axios.get(getOcsUrl('/ui/refresh'), { params: { lastToken } })
		return CDEM(response.data).data
	},
})

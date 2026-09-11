/**
 * Poll per-user signals and refresh Files or transactions without reloading the page.
 */

import { emit, subscribe } from '@nextcloud/event-bus'

let pollTimer = null
let lastRefreshToken = ''
let lastFilesToken = ''
let initialized = false
let inFlight = false
let currentFolder = null
let filesRefreshPending = false

const REFRESH_POLL_MS = 10000
const REFRESH_SCOPE_ALL = 'all'
const REFRESH_SCOPE_FILES = 'files'
const REFRESH_SCOPE_TRANSACTIONS = 'transactions'
let transactionsEvent = ''
let applicationPath = ''
let readSignal = null

const normalizeScope = (scope) => {
	const candidate = String(scope || '').trim().toLowerCase()

	if (candidate === REFRESH_SCOPE_FILES
		|| candidate === REFRESH_SCOPE_TRANSACTIONS
		|| candidate === REFRESH_SCOPE_ALL) {
		return candidate
	}

	return REFRESH_SCOPE_ALL
}

const shouldReloadOnCurrentPage = (scope) => {
	if (scope === REFRESH_SCOPE_ALL) {
		return true
	}

	const pathname = String(window.location.pathname || '').toLowerCase()

	if (pathname.includes(applicationPath)) {
		return scope === REFRESH_SCOPE_TRANSACTIONS
	}

	if (pathname.includes('/apps/files')) {
		return scope === REFRESH_SCOPE_FILES
	}

	return true
}

const dispatchSoftRefreshEvent = (eventName, scope, token) => {
	window.dispatchEvent(new CustomEvent(eventName, {
		detail: {
			scope,
			token,
			timestamp: Date.now(),
		},
	}))
}

const refreshFilesListingSoft = () => {
	if (!currentFolder) {
		filesRefreshPending = true
		return
	}

	// Nextcloud 33 reloads the current listing when its Folder is updated.
	filesRefreshPending = false
	emit('files:node:updated', currentFolder)
}

const triggerSoftRefresh = (scope, token) => {
	if (scope === REFRESH_SCOPE_TRANSACTIONS || scope === REFRESH_SCOPE_ALL) {
		dispatchSoftRefreshEvent(transactionsEvent, scope, token)
	}

	if (scope === REFRESH_SCOPE_FILES || scope === REFRESH_SCOPE_ALL) {
		refreshFilesListingSoft()
	}
}

const pollRefreshSignal = async () => {
	if (document.visibilityState === 'hidden' || inFlight) {
		return
	}

	inFlight = true
	try {
		const payload = await readSignal(lastRefreshToken)
		if (!payload || typeof payload.token !== 'string') {
			return
		}
		const token = payload.token
		const filesToken = String(payload.filesToken || '')
		const scope = normalizeScope(payload.scope)
		const filesChanged = filesToken !== '' && filesToken !== lastFilesToken
		const changed = (token !== '' && token !== lastRefreshToken) || payload.shouldRefresh === true

		if (initialized) {
			if (filesChanged && shouldReloadOnCurrentPage(REFRESH_SCOPE_FILES)) {
				refreshFilesListingSoft()
			}
			if (changed && shouldReloadOnCurrentPage(scope)) {
				// Avoid two Files reloads for the same poll.
				if (filesChanged && scope === REFRESH_SCOPE_ALL) {
					dispatchSoftRefreshEvent(transactionsEvent, scope, token)
				} else if (!filesChanged || scope !== REFRESH_SCOPE_FILES) {
					triggerSoftRefresh(scope, token)
				}
			}
		}
		lastRefreshToken = token
		lastFilesToken = filesToken
		initialized = true
	} finally {
		inFlight = false
	}
}

export const startUiRefreshWatcher = (options) => {
	if (pollTimer !== null) {
		return
	}

	readSignal = options.readSignal
	applicationPath = options.applicationPath
	transactionsEvent = options.transactionsEvent

	subscribe('files:list:updated', ({ folder }) => {
		currentFolder = folder || null
		if (filesRefreshPending && currentFolder) {
			refreshFilesListingSoft()
		}
	})
	document.addEventListener('visibilitychange', () => {
		pollRefreshSignal().catch(() => {})
	})
	pollRefreshSignal().catch(() => {})
	pollTimer = window.setInterval(() => {
		pollRefreshSignal().catch(() => {})
	}, REFRESH_POLL_MS)
}

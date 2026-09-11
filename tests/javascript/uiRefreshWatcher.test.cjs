const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')

function setup(pathname = '/apps/files/files') {
	const events = []
	const listeners = {}
	const requests = []
	const timers = []
	const document = { visibilityState: 'visible', addEventListener: (name, fn) => { listeners[name] = fn } }
	const context = {
		document, Date,
		window: { location: { pathname }, setInterval: fn => { timers.push(fn); return 1 }, dispatchEvent: event => events.push([event.type, event.detail]) },
		CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail } },
		emit: (name, payload) => events.push([name, payload]),
		subscribe: (name, fn) => { listeners[name] = fn },
		axios: { get: () => new Promise((resolve, reject) => requests.push({ resolve, reject })) },
		CDEM: value => value,
		getOcsUrl: path => path,
	}
	const source = fs.readFileSync(`${__dirname}/../../rcdevsBundle/src/javascript/uiRefreshWatcher.js`, 'utf8')
		.replace(/^import .*\n/gm, '').replace('export const startUiRefreshWatcher', 'const startUiRefreshWatcher')
	vm.runInNewContext(source + `\nconst options = { applicationPath: '/apps/yumisign_nextcloud', transactionsEvent: 'yms:refresh:transactions', readSignal: async () => (await axios.get()).data.data }; startUiRefreshWatcher(options); startUiRefreshWatcher(options);`, context)
	const reply = async payload => {
		requests.shift().resolve({ data: { data: payload } })
		await new Promise(resolve => setImmediate(resolve))
	}
	return { events, listeners, requests, timers, document, reply }
}

test('first signed file after an empty baseline refreshes the actual Files folder once', async () => {
	const s = setup()
	assert.equal(s.timers.length, 1)
	const folder = { fileid: 42, path: '/Shared' }
	s.listeners['files:list:updated']({ folder })
	await s.reply({ token: '', filesToken: '' })
	s.timers[0]()
	await s.reply({ token: 'status', scope: 'all', filesToken: 'signed' })
	assert.equal(s.events.filter(([name]) => name === 'files:node:updated').length, 1)
	assert.equal(s.events[0][1], folder)
	s.timers[0]()
	await s.reply({ token: 'status', scope: 'all', filesToken: 'signed' })
	assert.equal(s.events.filter(([name]) => name === 'files:node:updated').length, 1)
})

test('Files token survives a later transactions-only status change', async () => {
	const s = setup()
	await s.reply({ token: '', filesToken: '' })
	const folder = { fileid: 9 }
	s.listeners['files:list:updated']({ folder })
	s.timers[0]()
	await s.reply({ token: 'new-status', scope: 'transactions', filesToken: 'signed' })
	assert.deepEqual(s.events, [['files:node:updated', folder]])
})

test('refresh waits for Files initialization and follows folder navigation without looping', async () => {
	const s = setup()
	await s.reply({ token: '', filesToken: '' })
	s.timers[0]()
	await s.reply({ token: '', filesToken: 'signed' })
	assert.equal(s.events.length, 0)
	const folder = { fileid: 10 }
	s.listeners['files:list:updated']({ folder })
	s.listeners['files:list:updated']({ folder })
	assert.equal(s.events.length, 1)
	const nextFolder = { fileid: 11 }
	s.listeners['files:list:updated']({ folder: nextFolder })
	s.timers[0]()
	await s.reply({ token: '', filesToken: 'signed-again' })
	assert.equal(s.events[1][1], nextFolder)
})

test('polls do not overlap, recover after errors, and resume when tab becomes visible', async () => {
	const s = setup()
	s.timers[0]()
	assert.equal(s.requests.length, 1)
	s.requests.shift().reject(new Error('offline'))
	await new Promise(resolve => setImmediate(resolve))
	s.timers[0]()
	await s.reply({ token: '', filesToken: '' })
	s.document.visibilityState = 'hidden'
	s.timers[0]()
	assert.equal(s.requests.length, 0)
	s.document.visibilityState = 'visible'
	s.listeners.visibilitychange()
	assert.equal(s.requests.length, 1)
	await s.reply({ token: '', filesToken: 'signed' })
})

test('transactions page retains its refresh and never emits Files updates for files-only changes', async () => {
	const s = setup('/apps/yumisign_nextcloud')
	await s.reply({ token: '', filesToken: '' })
	s.timers[0]()
	await s.reply({ token: '', filesToken: 'signed' })
	assert.equal(s.events.length, 0)
	s.timers[0]()
	await s.reply({ token: 'status', scope: 'transactions', filesToken: 'signed' })
	assert.equal(s.events[0][0], 'yms:refresh:transactions')
})

const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const Vue = require('vue')

const source = fs.readFileSync(`${__dirname}/../../src/views/AdminSettings.vue`, 'utf8')
const script = source.split('<script>')[1].split('</script>')[0]
	.replace(/^import .*;\s*$/gm, '')
	.replace('export default', 'module.exports =')

const utilitySource = fs.readFileSync(`${__dirname}/../../src/javascript/utility.js`, 'utf8')
const validatorSource = utilitySource.slice(utilitySource.indexOf('export const isValidResponse ='), utilitySource.indexOf('export const isValidJSON ='))
const isValidResponse = vm.runInNewContext(validatorSource.replace('export const isValidResponse =', 'const isValidResponse =') + '\nisValidResponse')

function setup({ initialize = true } = {}) {
	const notifications = []
	const errors = []
	const requests = []
	const timers = new Map()
	let timerId = 0
	const context = {
		module: { exports: {} },
		NcCheckboxRadioSwitch: {}, WorkspaceListIds: {},
		getT: text => text,
		showSuccess: message => notifications.push(message),
		showError: message => errors.push(message),
		AbortController,
		getUtilityFunctionName: () => "test",
		appName: "yumisign_nextcloud",
		loadState: () => ({ asyncTimeout: 10, cronInterval: 5, workspaceName: "initial" }),
		log: { debug() {} },
		getOcsUrl: path => path,
		isValidResponse,
		setTimeout: fn => { timers.set(++timerId, fn); return timerId },
		clearTimeout: id => timers.delete(id),
		axios: { post: (url, payload) => new Promise((resolve, reject) => requests.push({ payload, resolve, reject })) },
	}
	vm.runInNewContext(script, context)
	const component = context.module.exports
	// Keep actual Vue reactivity and production methods, without DOM/server-check hooks.
	const instance = new Vue({ ...component, parent: new Vue(), beforeMount: [], mounted: [], beforeDestroy: [] })
	if (initialize) {
		component.beforeMount.call(instance)
		instance.initializeAutosave()
	}
	return { instance, requests, timers, component, notifications, errors, context }
}

const settle = async () => { await Promise.resolve(); await Vue.nextTick() }

test('initial load does not save; typing is grouped and switches save automatically', async () => {
	const { instance, requests, timers, notifications } = setup()
	await settle()
	assert.equal(requests.length, 0)
	assert.equal(timers.size, 0)
	assert.equal(notifications.length, 0)
	instance.workspaceName = 'dev'
	await settle()
	instance.workspaceName = 'dev_nextcloud_33'
	instance.enableSign = true
	await settle()
	assert.equal(timers.size, 1)
	const saving = [...timers.values()][0]()
	assert.equal(requests.length, 1)
	assert.equal(requests[0].payload.workspace_name, 'dev_nextcloud_33')
	assert.equal(requests[0].payload.enable_sign, true)
	requests[0].resolve({ data: { code: 0 } })
	await saving
	assert.deepEqual(notifications, ['Settings saved'])
	instance.$destroy()
})

test('changes during a request are saved afterwards using the latest values', async () => {
	const { instance, requests, notifications } = setup()
	instance.workspaceName = 'first'
	await settle()
	const saving = instance.saveSettings()
	instance.workspaceName = 'latest'
	await settle()
	await instance.saveSettings()
	assert.equal(requests.length, 1)
	requests[0].resolve({ data: { code: 0 } })
	await saving
	assert.equal(requests.length, 2)
	assert.equal(notifications.length, 0)
	assert.equal(requests[1].payload.workspace_name, 'latest')
	requests[1].resolve({ data: { code: 0 } })
	await settle()
	assert.equal(instance.saveInProgress, false)
	assert.deepEqual(notifications, ['Settings saved'])
	instance.$destroy()
})

test('invalid numbers are not saved and failures remain visible until the next successful save', async () => {
	const { instance, requests, notifications } = setup()
	instance.cronInterval = ''
	await settle()
	await instance.saveSettings()
	assert.equal(requests.length, 0)
	assert.equal(instance.failure, true)
	instance.cronInterval = '5'
	await settle()
	let saving = instance.saveSettings()
	requests[0].reject(new Error('Network unavailable'))
	await saving
	assert.equal(notifications.length, 0)
	assert.equal(instance.failure, true)
	instance.workspaceName = 'retry'
	await settle()
	saving = instance.saveSettings()
	requests[1].resolve({ data: { code: 0 } })
	await saving
	assert.equal(instance.failure, false)
	instance.$destroy()
})


test('empty fields populated by initial state never trigger autosave or a toast', async () => {
	const { instance, requests, timers, component, notifications } = setup({ initialize: false })
	instance.scheduleSave()
	await instance.saveSettings()
	assert.equal(timers.size, 0)
	component.beforeMount.call(instance)
	await settle()
	instance.initializeAutosave()
	await settle()
	assert.equal(instance.workspaceName, 'initial')
	assert.equal(timers.size, 0)
	assert.equal(requests.length, 0)
	assert.equal(notifications.length, 0)
	instance.$destroy()
})

test('editing then restoring the saved value does not save or notify', async () => {
	const { instance, requests, notifications } = setup()
	instance.workspaceName = 'temporary'
	await settle()
	instance.workspaceName = 'initial'
	await settle()
	await instance.saveSettings()
	assert.equal(requests.length, 0)
	assert.equal(notifications.length, 0)
	instance.$destroy()
})


test('an unsuccessful server response never displays a success confirmation', async () => {
	const { instance, requests, notifications } = setup()
	instance.workspaceName = 'changed'
	await settle()
	const saving = instance.saveSettings()
	requests[0].resolve({ data: { code: 1 } })
	await saving
	assert.equal(instance.failure, true)
	assert.equal(notifications.length, 0)
	instance.$destroy()
})


test('retrieve ID uses current workspace name and saves a unique ID as text', async () => {
	const { instance, requests, context } = setup()
	instance.workspaceName = 'new workspace'
	context.axios.get = async (url, options) => {
		assert.equal(options.params.workspace_name, 'new workspace')
		assert.equal(options.params.workspace_id, '')
		return { data: { code: 0, status: true, listId: [42], id: 42 } }
	}
	await instance.axiosSettingsRetrieveWorkspaceId()
	assert.equal(instance.workspaceId, '42')
	await settle()
	const saving = instance.saveSettings()
	assert.equal(requests[0].payload.workspace_id, '42')
	requests[0].resolve({ data: { code: 0 } })
	await saving
	instance.$destroy()
})

test('retrieve ID preserves the field until a choice is made in the popup', async () => {
	const { instance, context } = setup()
	instance.workspaceId = ''
	context.axios.get = async () => ({ data: { code: 1, status: true, listId: [42, 43], id: '' } })
	let items
	instance.showWorkspaceListIds = async choices => { items = choices; instance.updateId(43) }
	await instance.axiosSettingsRetrieveWorkspaceId()
	assert.deepEqual(items, [42, 43])
	assert.equal(instance.workspaceId, '43')
	instance.$destroy()
})

test('retrieve ID displays the API error without changing the stored selection', async () => {
	const { instance, errors, context } = setup()
	instance.workspaceId = '42'
	context.axios.get = async () => ({ data: { status: false, listId: [], message: 'No current valid subscription found' } })
	await instance.axiosSettingsRetrieveWorkspaceId()
	assert.deepEqual(errors, ['No current valid subscription found'])
	assert.equal(instance.workspaceId, '42')
	assert.equal(instance.reqWspId.request, false)
	instance.$destroy()
})

test('workspace popup selects an ID through its callback and can be reopened', async () => {
	const popupSource = fs.readFileSync(`${__dirname}/../../src/components/WorkspaceListIds.vue`, 'utf8')
	const popupScript = popupSource.split('<script>')[1].split('</script>')[0]
		.replace(/^import .*;\s*$/gm, '').replace('export default', 'module.exports =')
	const context = { module: { exports: {} }, PopupModal: {} }
	vm.runInNewContext(popupScript, context)
	const popup = new Vue(context.module.exports)
	popup.$refs.popup = { open() {}, close() {} }
	let selected
	let closed = popup.show({ items: [42, 43], updateId: id => { selected = id } })
	popup.updateId(43)
	assert.equal(popup.itemId, 43)
	assert.equal(selected, 43)
	popup._cancel()
	await closed
	closed = popup.show({ items: [44, 45], updateId: id => { selected = id } })
	popup.updateId(44)
	assert.equal(selected, 44)
	popup._cancel()
	await closed
	popup.$destroy()
})

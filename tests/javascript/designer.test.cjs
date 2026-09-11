const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')

const source = fs.readFileSync(`${__dirname}/../../src/views/YumiSignNxtCModal.vue`, 'utf8')
const script = source.split('<script>')[1].split('</script>')[0].replace(/^import .*;\s*$/gm, '').replace('export default', 'module.exports =')

test('Files v4 public file ID is submitted and Designer receives callback identifiers', async () => {
	let sent, redirect
	const context = {
		debounce: callback => callback,
		module: { exports: {} }, modalName: 'modal', appName: 'yumisign_nextcloud',
		ListItemIcon: {}, NcCheckboxRadioSwitch: {}, NcModal: {}, NcSelect: {}, NcTextField: {}, SearchResults: {},
		log: { debug() {}, info() {}, error() {} },
		CDEM: response => response, getOcsUrl: url => url, getT: text => text,
		generateUrl: path => `/nextcloud${path}`, URL, URLSearchParams,
		location: { protocol: 'https:', host: 'cloud.example' },
		window: { location: { origin: 'https://cloud.example', href: 'https://cloud.example/nextcloud/apps/files', replace: url => { redirect = url } } },
		axios: { post: async (url, payload) => {
			sent = payload
			return { data: { code: 0, data: { designerUrl: 'https://app.yumisign.com/designer?session=test', workspaceId: 1, workflowId: 2, envelopeId: 3 }, error: null, message: 'OpenDesigner' } }
		} },
	}
	vm.runInNewContext(script, context)
	const instance = {
		chosenFile: { fileid: 42, path: '/file.pdf' },
		initAxios: () => ({ abortCtrl: { signal: undefined } }),
		getFunctionName: () => 'test', refreshUiVariables() {},
		file: {}, signatureTypeSelected: 'standard', constantes: { signType: { qualified: { value: 'qualified' } } },
	}
	await context.module.exports.methods.axiosSignLocalAsync.call(instance, '/sign/local/async', '', 'user@example.com', 'email')
	assert.equal(sent.fileId, 42)
	assert.ok(redirect)
	const url = new URL(redirect)
	assert.equal(url.searchParams.get('session'), 'test')
	const callback = new URL(url.searchParams.get('callback'))
	assert.equal(callback.searchParams.get('workspaceId'), '1')
	assert.equal(callback.searchParams.get('workflowId'), '2')
	assert.equal(callback.searchParams.get('envelopeId'), '3')
	assert.equal(instance.axiosSrvRequest.error, false)
})

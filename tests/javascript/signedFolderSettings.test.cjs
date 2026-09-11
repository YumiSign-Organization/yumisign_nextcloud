const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const Vue = require('vue')

function setup() {
	const requests = []
	const context = { module: { exports: {} }, axios: {
		get: url => new Promise((resolve, reject) => requests.push({ url, resolve, reject })),
		post: (url, payload) => new Promise((resolve, reject) => requests.push({ url, payload, resolve, reject })),
	} }
	const source = fs.readFileSync(`${__dirname}/../../rcdevsBundle/src/components/SignedFolderSettings.vue`, 'utf8')
		.split('<script>')[1].split('</script>')[0].replace(/^import .*\n/gm, '').replace('export default', 'module.exports =')
	vm.runInNewContext(source, context)
	const component = context.module.exports
	const instance = new Vue({ ...component, propsData: { endpoint: '/app/settings/signed-folders', translate: text => text } })
	return { instance, requests, component }
}

test('loading values and saving independent paths does not send a user identity', async () => {
	const { instance, requests, component } = setup()
	const load = component.mounted.call(instance)
	assert.equal(instance.loading, true)
	requests.shift().resolve({ data: { values: { applicant: 'Default/A', recipient: 'Default/R' }, error: null } })
	await load
	assert.equal(requests.length, 0)
	instance.values.applicant = 'Signed by coworkers'
	const save = instance.save()
	assert.equal(requests[0].payload.recipient, 'Default/R')
	assert.equal(requests[0].payload.applicant, 'Signed by coworkers')
	assert.deepEqual(Object.keys(requests[0].payload).sort(), ['applicant', 'recipient'])
	requests.shift().resolve({ data: { values: { ...instance.values } } })
	await save
	assert.equal(instance.saved, true)
})

test('invalid existing settings remain editable and server errors are displayed', async () => {
	const { instance, requests, component } = setup()
	const load = component.mounted.call(instance)
	requests.shift().resolve({ data: { values: { applicant: '', recipient: 'R' }, error: 'Applicant path is empty' } })
	await load
	assert.equal(instance.error, 'Applicant path is empty')
	assert.equal(instance.loading, false)
	const save = instance.save()
	instance.save()
	assert.equal(requests.length, 1)
	requests.shift().reject({ response: { data: { message: 'Invalid signed folder' } } })
	await save
	assert.equal(instance.saved, false)
	assert.equal(instance.saving, false)
	assert.equal(instance.error, 'Invalid signed folder')
})

import Vue from 'vue'
import Router from 'vue-router'
import Pending from './Pending'
import Issues from './Issues'

Vue.use(Router)

export default new Router({
	mode: 'hash',
	base: process.env.BASE_URL,
	linkExactActiveClass: 'active',
	routes: [
		{
			path: '/',
			name: 'pending',
			component: Pending,
		},
		{
			path: '/issues',
			name: 'issues',
			component: Issues,
		},
	],
})

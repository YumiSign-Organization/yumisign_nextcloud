<!--
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
-->
<template>
	<NcContent :class="{'icon-loading': loading}" app-name="yumisign_nextcloud">
		<NcAppContent>
			<rcdevsAppContainer id="rcdevsAppNameYMS">
				<rcdevsAppHeader>
					<rcdevsAppTitle>{{ ui.messages.app.title }}</rcdevsAppTitle>
					<rcdevsAppTagline>{{ ui.messages.app.tagline }}</rcdevsAppTagline>
				</rcdevsAppHeader>

				<rcdevsTabsContainer>
					<rcdevsTabsHeaders>
						<rcdevsTabHeader class="activ" status="pending">
							<rcdevsTabHeaderLabel>
								{{ ui.messages.tab.pending.title }}
								<rcdevsTabHeaderLabelBorder />
							</rcdevsTabHeaderLabel>
						</rcdevsTabHeader>
						<rcdevsTabHeader class="notActiv" status="completed">
							<rcdevsTabHeaderLabel>
								{{ ui.messages.tab.completed.title }}
								<rcdevsTabHeaderLabelBorder />
							</rcdevsTabHeaderLabel>
						</rcdevsTabHeader>
						<rcdevsTabHeader class="notActiv" status="declined">
							<rcdevsTabHeaderLabel>
								{{ ui.messages.tab.declined.title }}
								<rcdevsTabHeaderLabelBorder />
							</rcdevsTabHeaderLabel>
						</rcdevsTabHeader>
						<rcdevsTabHeader class="notActiv" status="expired">
							<rcdevsTabHeaderLabel>
								{{ ui.messages.tab.expired.title }}
								<rcdevsTabHeaderLabelBorder />
							</rcdevsTabHeaderLabel>
						</rcdevsTabHeader>
						<rcdevsTabHeader class="notActiv" status="failed">
							<rcdevsTabHeaderLabel>
								{{ ui.messages.tab.failed.title }}
								<rcdevsTabHeaderLabelBorder />
							</rcdevsTabHeaderLabel>
						</rcdevsTabHeader>
					</rcdevsTabsHeaders>
					<rcdevsTabsBody>
						<rcdevsTabsBodyItem status="pending"><Transactions status="pending" /></rcdevsTabsBodyItem>
						<rcdevsTabsBodyItem status="completed"><Transactions status="completed" /></rcdevsTabsBodyItem>
						<rcdevsTabsBodyItem status="declined"><Transactions status="declined" /></rcdevsTabsBodyItem>
						<rcdevsTabsBodyItem status="expired"><Transactions status="expired" /></rcdevsTabsBodyItem>
						<rcdevsTabsBodyItem status="failed"><Transactions status="failed" /></rcdevsTabsBodyItem>
					</rcdevsTabsBody>
				</rcdevsTabsContainer>
			</rcdevsAppContainer>
		</NcAppContent>
	</NcContent>
</template>

<script>
import './styles/yumisignStyle.css';
import './styles/yumisignRoot.css';
import {getT} from './javascript/utility.js';
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js';
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js';
import Transactions from './components/Transactions/Transactions.vue';

export default {
	name: 'App',

	components: {
		NcAppContent,
		NcContent,
		// subComponents
		Transactions,
	},

	data() {
		this.ui = {
			button: {},
			messages: {
				app: {},
				tab: {
					pending: {},
					completed: {},
					declined: {},
					expired: {},
					failed: {},
				},
			},
			pictures: {},
		};

		this.ui.messages.app.title = getT('My Projects');
		this.ui.messages.app.tagline = getT('Here you can manage all your requests');

		this.ui.messages.tab.pending.title = getT('Pending');
		this.ui.messages.tab.completed.title = getT('Completed');
		this.ui.messages.tab.declined.title = getT('Declined');
		this.ui.messages.tab.expired.title = getT('Expired');
		this.ui.messages.tab.failed.title = getT('Failed');

		return {
			loading: false,
		};
	},
};

$(document).ready(function () {
	$('rcdevsTabHeader').hover(function () {
		$('rcdevsTabHeader').removeClass('activ').addClass('notActiv');
		$(this).addClass('activ');
		$(this).removeClass('notActiv');
		$('rcdevsTabsBody').children().css('display', 'none');
		$('rcdevsTabsBody')
			.children('[status="' + $(this).attr('status') + '"]')
			.each(function () {
				$(this).css('display', 'flex');
			});
	});
});
</script>
<style lang="scss">
table th,
table td {
	padding: 4px 8px;
}
</style>

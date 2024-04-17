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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
-->

<template>
	<div class="app-container">
		<div id="app-navigation" role="navigation">
			<ul>
				<li>
					<router-link to="/">
						{{ $t('yumisign_nextcloud', 'Pending signature requests') }}
					</router-link>
				</li>
				<li>
					<router-link to="/issues">
						{{ $t('yumisign_nextcloud', 'Signature requests issues') }}
					</router-link>
				</li>
			</ul>
			<div id="app-settings">
				<div id="app-settings-header">
					<button class="settings-button" data-apps-slide-toggle="#app-settings-content" />
				</div>
				<div id="app-settings-content">
					<!-- Your settings in here -->
				</div>
			</div>
		</div>
		<div id="app-content" tabindex="0" touch-action="pan-y">
			<div id="app-content-wrapper">
				<router-view />
			</div>
		</div>
	</div>
</template>

<style>
	@import '../css/listing.css';
</style>

<style scoped>
	.flex-container {
		display: flex;
	}

	.flex-child {
		flex: 1;
		border: 2px solid yellow;
	}

	.flex-child:first-child {
		margin-right: 20px;
	}

	.app-container {
		margin-top: 0px;
		display: flex;
		width: 100%;
	}

	#app-content >>> h1 {
		font-size: 2em;
		text-align: center;
	}

	/* #app-content >>> table {
		border-collapse: collapse;
		margin: 25px 0;
		font-size: 0.9em;
		font-family: sans-serif;
		width: 100%;
		box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
	}

	#app-content >>> thead tr {
		background-color: var(--color-primary-element-light);
		color: #ffffff;
		text-align: left;
	}

	#app-content >>> th, #app-content >>> td {
		padding: 12px 15px;
	}

	#app-content >>> tr {
		border-bottom: 1px solid #dddddd;
	}

	#app-content >>> tr:last-of-type {
		border-bottom: 2px solid var(--color-primary-element-light);
	} */

	#app-content >>> img {
		display: block;
		margin: 25px auto;
	}

	#app-content >>> td img {
		margin: 0 auto;
	}

	#app-content >>> .pagination {
		list-style: none;
		padding: 0;
	}

	#app-content >>> .pagination li {
		float: left;
	}

	#app-content >>> .pagination a {
		display: block;
		border: solid #dddddd 1px;
		padding: 6px 12px;
		margin-right: -1px;
		color: var(--color-primary-element-light);
	}

	#app-content >>> .pagination .active a {
		color: var(--color-main-background);
		background-color: var(--color-primary-element-light);
		cursor: default;
	}

	#app-content >>> .pagination .disabled a {
		color: #dddddd;
		cursor: not-allowed;
	}

	#app-content >>> .pagination li:not(.active):not(.disabled) a:hover {
		background-color: #dddddd;
	}
</style>

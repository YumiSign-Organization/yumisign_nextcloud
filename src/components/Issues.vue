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
    <div class="component-container">
        <IssueTitle>
            {{ getT("Requests issues") }}
        </IssueTitle>

        <div v-if="requesting">
            <img :src="loadingImg" />
        </div>
        <NcEmptyContent
            v-else-if="!requests.length"
            icon="icon-comment"
            :description="getT('No signature requests issues')"
        >
            <template #desc>
                {{ getT("There are currently no signature requests issues") }}
            </template>
        </NcEmptyContent>
        <table v-else class="listings">
            <thead>
                <tr>
                    <th>
                        {{ getT("Creation date") }}
                    </th>
                    <th>
                        {{ getT("Expiration date") }}
                    </th>
                    <th>
                        {{ getT("Transaction status") }}
                    </th>
                    <th>
                        {{ getT("File") }}
                    </th>
                    <th>
                        {{ getT("Recipient") }}
                    </th>
                    <th>
                        {{ getT("Recipient status") }}
                    </th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(request, index) in requests"
                    :key="index"
                    :class="['yms_' + request.transactions[0].envelope_id]"
                >
                    <td>{{ request.transactions[0].created | moment }}</td>
                    <td>{{ request.transactions[0].expiry_date | moment }}</td>
                    <td>
                        {{ getT(request.transactions[0].global_status) }}
                    </td>
                    <td>{{ request.transactions[0].file_path }}</td>
                    <td>
                        <div
                            v-for="(transaction, indexTr) in request.transactions"
                            :key="indexTr"
                            :class="[
                                'multipleRows yms_' +
                                    request.transactions[0].envelope_id +
                                    '_' +
                                    (request.transactions.length === 1 ? 'all' : indexTr),
                            ]"
                        >
                            <span>{{ transaction.recipient }}</span>
                        </div>
                    </td>
                    <td>
                        <div
                            v-for="(transaction, indexTr) in request.transactions"
                            :key="indexTr"
                            :class="[
                                'multipleRows yms_' +
                                    request.transactions[0].envelope_id +
                                    '_' +
                                    (request.transactions.length === 1 ? 'all' : indexTr),
                            ]"
                        >
                            <span>{{ getT(transaction.status) }}</span>
                            <img v-if="deleting[index]" :src="loadingImg" />
                            <span
                                v-else
                                class="recipient-delete"
                                @click="
                                    deleteRequest(
                                        index,
                                        request.transactions[0].envelope_id,
                                        transaction.recipient,
                                        'yms_' +
                                            request.transactions[0].envelope_id +
                                            '_' +
                                            (request.transactions.length === 1
                                                ? 'all'
                                                : indexTr)
                                    )
                                "
                            />
                        </div>
                    </td>
                    <td>
                        <img v-if="deleting[index]" :src="loadingImg" />
                        <button
                            v-else
                            @click="
                                deleteRequest(
                                    index,
                                    request.transactions[0].envelope_id,
                                    false,
                                    false
                                )
                            "
                        >
                            {{ getT("Delete YumiSign request") }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <paginationFlexContainer>
            <Paginate
                v-show="pageCount > 1 && requests.length"
                :page-count="pageCount"
                :page-range="3"
                :margin-pages="2"
                :click-handler="changePage"
                :prev-text="getT('Previous')"
                :next-text="getT('Next')"
                :container-class="'paginationContainer'"
                :page-class="'paginationPage'"
                :prev-class="'paginationPage'"
                :next-class="'paginationPage'"
            />
        </paginationFlexContainer>
        <ConfirmDialogue ref="ConfirmDialogue" />
    </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl, generateFilePath, generateOcsUrl } from "@nextcloud/router";
import NcEmptyContent from "@nextcloud/vue/dist/Components/NcEmptyContent.js";
import Paginate from "vuejs-paginate";
import moment from "moment";
import ConfirmDialogue from "./CancelRequestForce.vue";
import { appName, baseUrl } from "../config.js";
import { getT } from "../utility.js";
import "../styles/listing.css";

export default {
    name: "Issues",
    components: {
        NcEmptyContent,
        Paginate,
        ConfirmDialogue,
    },
    filters: {
        moment(date) {
            return moment(date * 1000).format("YYYY-MM-DD HH:mm:ss");
        },
    },
    data() {
        return {
            getT: getT,
            pageCount: null,
            requesting: false,
            requests: [],
            deleting: [],
        };
    },
    created() {
        const CancelToken = axios.CancelToken;
        this.source = CancelToken.source();
    },
    mounted() {
        this.NB_ITEMS_PER_PAGE = 20; // default value if API call fails
        this.loadingImg = generateFilePath("core", "", "img/") + "loading.gif";
        this.requesting = true;

        this.runAPI("ui/items/page")
            .then((response) => {
                this.NB_ITEMS_PER_PAGE = response.data.itemsPerPage;
            })
            .finally(() => {
                this.runAPI(`requests/issues?nbItems=${this.NB_ITEMS_PER_PAGE}`).then((response) => {
                    this.requesting = false;
                    this.pageCount = Math.ceil(
                        response.data.count / this.NB_ITEMS_PER_PAGE
                    );
                    this.deleting.splice(response.data.requests.length);
                    this.requests = this.getRequests(response.data.requests);
                });
            });
    },
    methods: {
        runAPI: function (urlApi) {
            try {
                return axios
                    .get(
                        generateOcsUrl(`apps/${appName}/api/v1/${urlApi}`),
                        {},
                        {
                            cancelToken: this.source.token,
                        }
                    )
                    .catch((error) => {
                        this.error = true;
                        console.log(error);
                    });
            } catch (error) {
                console.error(error.message);
            }
        },

        changePage(pageNum) {
            this.requesting = true;
            this.requests = [];

            let urlRequest = generateOcsUrl(baseUrl + "/api/v1/requests/issues");
            axios
                .get(
                    `${urlRequest}?&page=${pageNum - 1}&nbItems=${this.NB_ITEMS_PER_PAGE}`
                )

                .then((response) => {
                    this.requesting = false;
                    this.pageCount = Math.ceil(
                        response.data.count / this.NB_ITEMS_PER_PAGE
                    );
                    this.deleting.splice(response.data.requests.length);
                    this.requests = this.getRequests(response.data.requests);
                })
                .catch((error) => {
                    this.requesting = false;
                    // eslint-disable-next-line
                    console.log(error);
                });
        },

        async deleteRequest(index, envelopeId, recipient, tagToDelete) {
            const ok = await this.$refs.ConfirmDialogue.show({
                title: this.getT("Deleting signature issue"),
                message: t(
                    appName,
                    "Are you sure you want to delete this signature issue ?"
                ),
                cancelButton: this.getT("Go back"),
                okButton: this.getT("Delete issue"),
            });
            if (recipient === false) {
                recipient = "";
            }
            if (ok) {
                let urlRequest = generateOcsUrl(baseUrl + "/api/v1/sign/deletion");
                axios
                    .put(urlRequest, {
                        envelopeId,
                        recipient,
                    })
                    .then((response) => {
                        switch (response.data.code) {
                            case "1":
                                this.deleteTableRows(
                                    this.requests,
                                    envelopeId,
                                    recipient,
                                    tagToDelete
                                );
                                break;
                            case true:
                                this.deleteTableRows(
                                    this.requests,
                                    envelopeId,
                                    recipient,
                                    tagToDelete
                                );
                                break;
                            default:
                                alert(response.data.message);
                                break;
                        }
                    })
                    .catch((error) => {
                        alert(this.getT("Deletion process failed") + "\n" + error);
                    });
            }
        },

        deleteTableRows(requests, envelopeId, recipient, tagToDelete) {
            if (!tagToDelete) {
                // Remove the entire row (means the Transaction with all recipients)
                document
                    .querySelectorAll(".yms_" + envelopeId)
                    .forEach((el) => el.remove());
            } else {
                // Remove only the recipient; Special: if only one recipient (tag ends with '_all'), remove the entire row
                if (tagToDelete.substr(tagToDelete.length - 4).toLowerCase() === "_all") {
                    document
                        .querySelectorAll(".yms_" + envelopeId)
                        .forEach((el) => el.remove());
                } else {
                    document
                        .querySelectorAll("." + tagToDelete)
                        .forEach((el) => el.remove());
                }
            }
        },

        getRequests(responseRequests) {
            // Group recipients for the same transaction (only one line per transaction)
            const tmpRequests = {};
            const returnRequests = [];

            responseRequests.forEach((ymsTransaction) => {
                if (!tmpRequests[ymsTransaction.envelope_id]) {
                    // envelope_id not found
                    const transactionArray = [ymsTransaction];
                    tmpRequests[ymsTransaction.envelope_id] = {
                        transactions: transactionArray,
                    };
                } else {
                    // envelope_id found, append
                    tmpRequests[ymsTransaction.envelope_id].transactions.push(
                        ymsTransaction
                    );
                }
            });

            // Convert object into an array
            Object.keys(tmpRequests).forEach((key) => {
                returnRequests.push(tmpRequests[key]);
            });

            return returnRequests;
        },
    },
};
</script>

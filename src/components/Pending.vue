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
        <PendingTitle>
            {{ getT("Pending requests") }}
        </PendingTitle>

        <div v-if="requesting">
            <img :src="loadingImg" />
        </div>
        <NcEmptyContent
            v-else-if="!requests.length"
            icon="icon-comment"
            :description="getT('No pending signature requests')"
        >
            <template #desc>
                {{ getT("There are currently no pending signature requests") }}
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
                        {{ getT("Recipient") }}
                    </th>
                    <th>
                        {{ getT("File") }}
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
                    <td>{{ request.transactions[0].file_path }}</td>
                    <td class="cancelButton">
                        <img v-if="canceling[index]" :src="loadingImg" />
                        <button
                            v-else
                            @click="
                                cancelRequest(index, request.transactions[0].envelope_id)
                            "
                        >
                            {{ getT("Cancel request") }}
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
import { generateFilePath, generateOcsUrl } from "@nextcloud/router";
import NcEmptyContent from "@nextcloud/vue/dist/Components/NcEmptyContent.js";
import Paginate from "vuejs-paginate";
import moment from "moment";
import ConfirmDialogue from "./CancelRequestForce.vue";
import { appName, baseUrl } from "../config.js";
import { getT } from "../utility.js";
import "../styles/listing.css";

export default {
    name: "Pending",

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
            canceling: [],
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
                this.runAPI(`requests/pending?nbItems=${this.NB_ITEMS_PER_PAGE}`).then(
                    (response) => {
                        this.requesting = false;
                        this.pageCount = Math.ceil(
                            response.data.count / this.NB_ITEMS_PER_PAGE
                        );
                        this.canceling.splice(response.data.requests.length);
                        this.requests = this.getRequests(response.data.requests);
                    }
                );
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

        async cancelRequest(index, envelopeId) {
            const ok = await this.$refs.ConfirmDialogue.show({
                title: this.getT("Cancel pending signature"),
                message: t(
                    appName,
                    "Are you sure you want to cancel this signature request ?"
                ),
                cancelButton: this.getT("Go back"),
                okButton: this.getT("Cancel pending"),
            });
            if (ok) {
                let urlRequest = generateOcsUrl(baseUrl + "/api/v1/sign/cancel");
                axios
                    .put(urlRequest, {
                        envelopeId,
                    })
                    .then((response) => {
                        switch (response.data.code) {
                            case "WORKFLOW_NOT_FOUND":
                                // Ask to force deletion in database
                                this.forceCancellation(envelopeId);
                                break;
                            case "already_cancelled":
                                this.deleteTableRows(this.requests, envelopeId);
                                break;
                            case "1":
                                this.deleteTableRows(this.requests, envelopeId);
                                break;
                            case true:
                                this.deleteTableRows(this.requests, envelopeId);
                                break;
                            default:
                                alert(response.data.message);
                                break;
                        }
                    })
                    .catch((error) => {
                        alert("Cancellation process failed.\n" + error);
                    });
            }
        },

        changePage(pageNum) {
            this.requesting = true;
            this.requests = [];

            let urlRequest = generateOcsUrl(baseUrl + "/api/v1/requests/pending");

            // axios
            //     .get(
            //         `${urlRequest}?&page=${pageNum - 1}&nbItems=${this.NB_ITEMS_PER_PAGE}`
            //     )
            axios({
                url: `${urlRequest}?&page=${pageNum - 1}&nbItems=${
                    this.NB_ITEMS_PER_PAGE
                }`,
                method: "POST",
                // timeout: 10000,
            })
                .then((response) => {
                    this.requesting = false;
                    this.pageCount = Math.ceil(
                        response.data.count / this.NB_ITEMS_PER_PAGE
                    );
                    this.canceling.splice(response.data.requests.length);
                    this.requests = this.getRequests(response.data.requests);
                })
                .catch((error) => {
                    this.requesting = false;
                    // eslint-disable-next-line
                    console.log(error);
                });
        },

        deleteTableRows(requests, envelopeId) {
            for (let cpt = requests.length - 1; cpt >= 0; cpt--) {
                if (
                    requests[cpt].transactions[0].envelope_id.toLowerCase() ===
                    envelopeId.toLowerCase()
                ) {
                    this.requests.splice(cpt, 1);
                }
            }
        },

        async forceCancellation(envelopeId) {
            const ok = await this.$refs.ConfirmDialogue.show({
                title: this.getT("Force cancellation"),
                message: t(
                    appName,
                    "This pending signature is not found on YumiSign.\nDo you want to force deletion on Nextcloud database ?"
                ),
                cancelButton: this.getT("Go back"),
                okButton: this.getT("Force deletion"),
            });

            if (ok) {
                let urlRequest = generateOcsUrl(baseUrl + "/api/v1/sign/deletion");
                axios
                    .put(urlRequest, {
                        envelopeId,
                    })
                    .then((response) => {
                        if (response.data.code === "1") {
                            this.deleteTableRows(this.requests, envelopeId);
                        } else {
                            alert(
                                "Forcing cancellation failed.\n" +
                                    response.data.message +
                                    "\ncode:" +
                                    response.data.code
                            );
                        }
                    })
                    .catch((error) => {
                        alert("Forcing cancellation process failed.\n" + error);
                    });
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

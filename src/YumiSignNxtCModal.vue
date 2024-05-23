<template>
    <!--
	*
	* @copyright Copyright (c) 2023, RCDevs (info@rcdevs.com)
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
    <div>
        <Modal class="rcdevsYmsModal" v-if="modal" size="large" @close="closeModal">
            <div class="modal__content">
                <h1>{{ $t("yumisign_nextcloud", "YumiSign for Nextcloud") }}</h1>
                <img v-if="checkingSettings" :src="loadingImg" />
                <p v-else-if="!settingsOk" id="error_settings" class="alert alert-danger">
                    <span>
                        {{ warningServer }}
                    </span>
                </p>
                <div v-else>
                    <img
                        v-if="!success"
                        :src="mobileSigningImg"
                        style="max-height: 200px"
                    />
                    <!-- BEGIN -->
                    <p v-else>
                        <span v-if="signatureTypeSelected != 'simple'" id="green-tick">
                            &#10003;
                        </span>
                    </p>
                    <!-- END -->
                    <p>
                        {{ filenameMessage }} <strong>{{ filename }}</strong>
                    </p>
                    <p v-if="error" class="error">
                        {{ errorMessage }}
                    </p>
                    <br />
                    <div v-if="!requesting && !success">
                        <CheckboxRadioSwitch
                                v-on:click="changeNcSelectvalue('self')"
                            :checked.sync="recipientType"
                            value="self"
                            name="recipient_radio"
                            type="radio"
                        >
                            {{ $t("yumisign_nextcloud", "Self-signature") }}
                        </CheckboxRadioSwitch>
                        <div class="flex-container">
                            <CheckboxRadioSwitch
                                v-on:click="changeNcSelectvalue('nextcloud')"
                                :checked.sync="recipientType"
                                value="nextcloud"
                                name="recipient_radio"
                                type="radio"
                            >
                                {{
                                    $t(
                                        "yumisign_nextcloud",
                                        "Signature by a Nextcloud user:"
                                    )
                                }}
                            </CheckboxRadioSwitch>
                            <Multiselect
                                v-model="localUser"
                                :options="formattedOptions"
                                label="displayName"
                                track-by="uid"
                                :user-select="true"
                                style="width: 400px"
                                :placeholder="`${placeHolderUser}`"
                                @select="checkNextcloudRadio"
                                @search-change="localUserSearchChanged"
                            >
                                <template #singleLabel="{ option }">
                                    <ListItemIcon
                                        v-bind="option"
                                        :title="option.displayName"
                                        :avatar-size="24"
                                        :no-margin="true"
                                        style="width: 380px"
                                    />
                                </template>
                            </Multiselect>
                        </div>
                        <div class="flex-container">
                            <CheckboxRadioSwitch
                                v-on:click="changeNcSelectvalue('external')"
                                :checked.sync="recipientType"
                                value="external"
                                name="recipient_radio"
                                type="radio"
                            >
                                {{ $t("yumisign_nextcloud", "Signature by email:") }}
                            </CheckboxRadioSwitch>
                            <input
                                v-model="externalUserEmail"
                                type="text"
                                :placeholder="`${placeHolderEmail}`"
                                @input="checkExternalRadio"
                            />
                        </div>
                        <button type="button" @click="mobileSignature">
                            {{ $t("yumisign_nextcloud", "Digital signature") }}
                        </button>
                    </div>
                    <div v-if="requesting">
                        <img :src="loadingImg" />
                    </div>
                    <div v-if="success && signatureTypeSelected != 'simple'">
                        <button type="button" class="primary" @click="closeModal">
                            {{ $t("yumisign_nextcloud", "Close") }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
<script>
import Modal from "@nextcloud/vue/dist/Components/Modal";
import CheckboxRadioSwitch from "@nextcloud/vue/dist/Components/CheckboxRadioSwitch";
import Multiselect from "@nextcloud/vue/dist/Components/Multiselect";
import ListItemIcon from "@nextcloud/vue/dist/Components/ListItemIcon";
import EventBus from "./EventBus";
import queryString from "query-string";
import axios from "@nextcloud/axios";
import { generateUrl, generateFilePath } from "@nextcloud/router";

export default {
    name: "YumiSignNxtCModal",
    components: {
        Modal,
        CheckboxRadioSwitch,
        Multiselect,
        ListItemIcon,
    },
    data() {
        return {
            modal: false,
            checkingSettings: true,
            requesting: false,
            success: false,
            error: false,
            errorMessage: "",
            source: null,
            settingsOk: false,
            recipientType: "self",
            localUser: "",
            externalUserEmail: "",
            formattedOptions: [],
            designerUrl: "",
            optionsSigType: [
                { label: t("yumisign_nextcloud", "Simple"), value: "simple" },
                // { label: t('yumisign_nextcloud', 'Advanced'), value: 'advanced' },
                { label: t("yumisign_nextcloud", "Qualified"), value: "qualified" },
            ],
            signatureTypeSelected: "simple", // Setting initial value
        };
    },
    mounted() {
        this.mobileSigningImg =
            generateFilePath("yumisign_nextcloud", "", "img/") + "mobile-signing.png";
        this.loadingImg = generateFilePath("core", "", "img/") + "loading.gif";

        EventBus.$on("yumisign-sign-click", (payload) => {
            this.showModal();
            // this.filename = payload.filename
            this.fileInfoModel = payload.context.fileInfoModel;
            this.filename = payload.context.fileInfoModel.attributes.name;
            this.fileId = payload.context.fileInfoModel.id;
            this.filenameMessage = t("yumisign_nextcloud", "Digital signature of file");
            this.placeHolderUser = t("yumisign_nextcloud", "Write a user Id");
            this.placeHolderEmail = t(
                "yumisign_nextcloud",
                "Write one or several emails separated by semicolons"
            );
            this.warningServer = t(
                "yumisign_nextcloud",
                "You have to enter the YumiSign server URL in the YumiSign for Nextcloud settings prior to sign any document"
            );
            console.log("File ID is " + this.fileId);
        });
    },
    methods: {
        showModal() {
            this.modal = true;
            this.requesting = false;
            this.success = false;
            this.error = false;
            this.errorMessage = "";

            const baseUrl = generateUrl("/apps/yumisign_nextcloud");

            const CancelToken = axios.CancelToken;
            this.source = CancelToken.source();

            axios
                .get(
                    baseUrl + "/check_settings",
                    {},
                    {
                        cancelToken: this.source.token,
                    }
                )
                .then((response) => {
                    this.checkingSettings = false;
                    this.settingsOk = response.data;
                })
                .catch((error) => {
                    // eslint-disable-next-line
                    console.log(error);
                });
        },
        checkNextcloudRadio() {
            this.recipientType = "nextcloud";
        },
        localUserSearchChanged(searchQuery, id) {
            if (searchQuery.length >= 3) {
                const baseUrl = generateUrl("/apps/yumisign_nextcloud");
                const CancelToken = axios.CancelToken;
                this.source = CancelToken.source();
                axios
                    .get(
                        // baseUrl + "/get_local_users?searchQuery=" + searchQuery,
                        baseUrl + `/get_local_users?&search=${searchQuery}&type=user`,
                        {},
                        {
                            cancelToken: this.source.token,
                        }
                    )
                    .then((response) => {
                        this.formattedOptions = response.data.map((item) => {
                            return {
                                uid: item.uid,
                                displayName: item.display_name,
                                subtitle: item.email,
                                icon: "icon-user",
                                isNoUser: false,
                            };
                        });
                    })
                    .catch((error) => {
                        // eslint-disable-next-line
                        console.log(error);
                    });
            } else {
                this.formattedOptions = [];
            }
        },
        checkExternalRadio() {
            if (this.externalUserEmail.length > 0) {
                this.recipientType = "external";
            }
        },
        closeModal() {
            if (this.source !== null) {
                this.source.cancel("Operation canceled by the user.");
                this.source = null;
            }

            if (this.success) {
                FileList.reload();
            }

            this.modal = false;
        },
        mobileSignature() {
            if (this.recipientType === "self") {
                this.syncMobileSignature();
            } else if (this.recipientType === "nextcloud") {
                this.asyncLocalMobileSignature();
            } else {
                this.asyncExternalMobileSignature();
            }
        },
        syncMobileSignature() {
            this._commonCallSignature("/apps/yumisign_nextcloud", "/mobile_sign");
        },
        asyncLocalMobileSignature() {
            if (this.recipientType === "nextcloud" && !this.localUser) {
                return;
            }

            this._commonCallSignature(
                "/apps/yumisign_nextcloud",
                "/async_local_mobile_sign",
                this.localUser.subtitle,
                this.localUser.uid
            );
        },
        asyncExternalMobileSignature() {
            if (
                this.recipientType === "external" &&
                (!this.externalUserEmail || !this.signatureTypeSelected)
            ) {
                return;
            }

            this._commonCallSignature(
                "/apps/yumisign_nextcloud",
                "/async_external_mobile_sign",
                this.externalUserEmail
            );
        },

        _commonCallSignature(url, axiosPostUrl, signerEmail = "", nxcUsername = "") {
            this.error = false;
            this.requesting = true;
            const baseUrl = generateUrl(url);

            const CancelToken = axios.CancelToken;
            this.source = CancelToken.source();
            axios
                .post(
                    baseUrl + axiosPostUrl,
                    {
                        path: this.getFilePath(),
                        appUrl:
                            location.protocol + "//" + location.host + generateUrl(url),
                        signatureType: this.signatureTypeSelected,
                        email: signerEmail,
                        username: nxcUsername,
                        fileId: this.fileId,
                    },
                    {
                        cancelToken: this.source.token,
                    }
                )
                .then((response) => {
                    this.requesting = false;
                    if (response.data.session !== null && response.data.session !== "") {
                        this.success = true;
                        const yumisignCallback = encodeURIComponent(
                            location.protocol +
                                "//" +
                                location.host +
                                generateUrl(url) +
                                "/async_external_mobile_sign_submit?workspaceId=" +
                                response.data.workspaceId +
                                "&workflowId=" +
                                response.data.workflowId +
                                "&envelopeId=" +
                                response.data.envelopeId +
                                "&url=" +
                                window.location.href
                        );
                        this.designerUrl =
                            response.data.designerUrl + "?callback=" + yumisignCallback;
                        if (this.signatureTypeSelected.toLowerCase() === "simple") {
                            window.location.replace(this.designerUrl);
                        }
                    } else {
                        this.error = true;
                        this.errorMessage = "Error: " + response.data.message;
                    }
                })
                .catch((error) => {
                    this.requesting = false;
                    this.error = true;
                    this.errorMessage = error;
                });
        },

        getFilePath() {
            const parsed = queryString.parse(window.location.search);
            if (parsed.dir === "/") {
                return this.filename;
            } else {
                return parsed.dir + "/" + this.filename;
            }
        },

        changeNcSelectvalue: function (radiovalue) {
			try {
				if (!(this.selfDisabled && radiovalue === 'self')) {
					this.recipientType = radiovalue;
				}
			} catch (error) {
				console.error(error.message);
			}
		},

    },
};
</script>
<style scoped>
.modal__content {
    margin: 50px;
    text-align: center;
}

h1 {
    font-size: 2em;
    font-weight: bold;
    margin-bottom: 10px;
}

#green-tick {
    display: inline-block;
    font-size: 150px;
    color: green;
    margin-top: 100px;
    margin-bottom: 100px;
}

.error {
    color: red;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.alert {
    display: block;
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
}

#error_settings {
    margin-top: 25px;
}

.flex-container {
    display: flex;
    align-items: center;
    gap: 8px;
}

.flex-container:last-of-type {
    margin-bottom: 32px;
}

input {
    flex: 1 1 auto;
}
</style>

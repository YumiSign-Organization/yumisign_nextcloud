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
    <div class="ymsModal">
        <NcModal
            :show.sync="modal"
            @close="closeModal"
            :outTransition="true"
            :aria-label="t(appName, 'Sign with YumiSign')"
        >
            <div ref="ymsModalForm" class="ymsModalContent">
                <div class="rcdevsLogo">
                    <img src="../../img/rcdevsLogo.png" />
                </div>
                <h1 class="ymsModalTitle">
                    {{ t(appName, "YumiSign for Nextcloud") }}
                </h1>

                <div v-if="isSettingKO()" id="errorSettings" class="alert alertDanger">
                    {{ ui.messages.warningServer }}
                </div>

                <ymsModalMainContainer v-if="isSettingOK()">
                    <div v-if="!success">
                        <img
                            :src="ui.pictures.mobileSigningImg"
                            style="max-height: 200px"
                        />
                    </div>

                    <greenTick v-if="isSuccesNotSimpleTypeSignature()">
                        <span> &#10003; </span>
                    </greenTick>

                    <div class="chosenFile">
                        <span>
                            {{ ui.messages.filenameMessage }}
                        </span>
                        <span class="filename">
                            {{ getBasename() }}
                        </span>
                    </div>

                    <p v-if="error" class="error">
                        {{ errorMessage }}
                    </p>

                    <br />

                    <recipientsChoices v-if="!isTransactionInProgress()">
                        <recipientsSignleChoice
                            v-on:click="changeNcSelectvalue(constantes.self)"
                        >
                            <NcCheckboxRadioSwitch
                                v-if="!selfDisabled"
                                :checked.sync="recipientType"
                                :value="constantes.self"
                                :disabled="selfDisabled"
                                name="ymsRecipientRadio"
                                type="radio"
                            >
                                {{ t(appName, "Self-signature") }}
                            </NcCheckboxRadioSwitch>
                            <DisplaySelfEmail>
                                <span>{{ currentUserFullData }}</span>
                            </DisplaySelfEmail>
                        </recipientsSignleChoice>

                        <recipientsSignleChoice
                            v-on:click="changeNcSelectvalue(constantes.nextcloud)"
                        >
                            <NcCheckboxRadioSwitch
                                :checked.sync="recipientType"
                                :value="constantes.nextcloud"
                                name="ymsRecipientRadio"
                                type="radio"
                            >
                                {{ t(appName, "Signature by a Nextcloud user") }}
                            </NcCheckboxRadioSwitch>
                            <SelectNextcloudUsers>
                                <NcTextField
                                    ref="userField"
                                    v-observe-visibility="userVisibilityChanged"
                                    :disabled="shareLoading"
                                    :value.sync="user"
                                    type="text"
                                    :placeholder="t(appName, 'Search users')"
                                    trailing-button-icon="close"
                                    :trailing-button-label="cancelSearchLabel"
                                    :show-trailing-button="isSearchingUser"
                                    @trailing-button-click="abortUserSearch"
                                    @input="handleUserInput"
                                >
                                    <Magnify :size="16" />
                                </NcTextField>
                                <SearchResults
                                    v-if="user !== ''"
                                    :search-text="user"
                                    :search-results="userResults"
                                    :entries-loading="usersLoading"
                                    :no-results="noUserResults"
                                    :scrollable="true"
                                    :selectable="true"
                                    @click="addUser"
                                />
                            </SelectNextcloudUsers>
                        </recipientsSignleChoice>

                        <recipientsSignleChoice
                            v-on:click="changeNcSelectvalue(constantes.external)"
                        >
                            <NcCheckboxRadioSwitch
                                :checked.sync="recipientType"
                                :value="constantes.external"
                                name="ymsRecipientRadio"
                                type="radio"
                            >
                                {{ t(appName, "Signature by email") }}
                            </NcCheckboxRadioSwitch>
                            <InputUsersEmails>
                                <input
                                    v-model="externalUserEmail"
                                    type="text"
                                    :placeholder="`${ui.messages.placeHolderEmail}`"
                                />
                            </InputUsersEmails>
                        </recipientsSignleChoice>
                    </recipientsChoices>

                    <div v-if="isRequesting()">
                        <img :src="ui.pictures.loadingImg" />
                    </div>
                </ymsModalMainContainer>

                <ymsModalFooter>
                    <button
                        v-if="isSettingKO()"
                        type="button"
                        @click="closeModal"
                        class="closeModal"
                    >
                        {{ t(appName, "Close") }}
                    </button>
                    <button
                        v-if="isSettingOK()"
                        type="button"
                        @click="submitSignature"
                        class="submitSignature"
                    >
                        {{ t(appName, "Digital signature") }}
                    </button>
                </ymsModalFooter>
            </div>
        </NcModal>
    </div>
</template>

<script>
import { generateFilePath, generateOcsUrl, generateUrl } from "@nextcloud/router";
import axios from "@nextcloud/axios";
import ListItemIcon from "@nextcloud/vue/dist/Components/NcListItemIcon.js";
import NcCheckboxRadioSwitch from "@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js";
import NcModal from "@nextcloud/vue/dist/Components/NcModal.js";
import NcSelect from "@nextcloud/vue/dist/Components/NcSelect.js";
import "../styles/ymsStyle.css";
import { appName, baseUrl } from "../config.js";
import debounce from "debounce";
import NcTextField from "@nextcloud/vue/dist/Components/NcTextField.js";
import SearchResults from "../components/SearchResults.vue";

export default {
    name: "YumiSignNxtCModal",
    components: {
        NcModal,
        NcCheckboxRadioSwitch,
        NcSelect,
        ListItemIcon,
        NcTextField,
        SearchResults,
    },

    data() {
        this.constantes = [];
        this.constantes.self = "self";
        this.constantes.nextcloud = "nextcloud";
        this.constantes.external = "external";

        this.ui = [];
        this.ui.messages = [];
        this.ui.pictures = [];

        this.ui.messages.filenameMessage = t(appName, "Digital signature of file");
        this.ui.messages.placeHolderUser = t(appName, "Write a user Id");
        this.ui.messages.placeHolderEmail = t(
            appName,
            "Write one or several emails separated by semicolons"
        );
        this.ui.messages.warningServer = t(
            appName,
            "You have to enter the YumiSign server URL in the YumiSign for Nextcloud settings prior to sign any document"
        );

        this.ui.pictures.mobileSigningImg =
            generateFilePath(appName, "", "img/") + "mobile-signing.png";
        this.ui.pictures.loadingImg =
            generateFilePath("core", "", "img/") + "loading.gif";

        this.selfDisabled = false;
        this.currentUserFullData = "";
        this.currentUser = {};

        return {
            noUserResults: false,
            usersLoading: false,
            userResults: {},
            user: "",

            appName,
            chosenFile: null,
            modal: false,
            checkingSettings: true,
            requesting: false,
            success: false,
            error: false,
            errorMessage: "",
            source: null,
            ymsSettingsChecked: false,
            ymsSettings: false,
            recipientType: "",
            selfEmail: "",
            localUser: [],
            externalUserEmail: "",
            formattedOptions: [],
            designerUrl: "",
            optionsSigType: [
                { label: t(appName, "Simple"), value: "simple" },
                { label: t(appName, "Qualified"), value: "qualified" },
            ],
            signatureTypeSelected: "simple", // Setting initial value
            selectArray: [
                {
                    title: "User select",
                    props: {
                        inputId: Math.floor(Math.random() * 100),
                        userSelect: true,
                        options: [
                            {
                            },
                        ],
                    },
                },
            ],
        };
    },

    created() {
        try {
            this.noUserResults = false;
            this.usersLoading = false;
            this.userResults = {};
            this.user = "";

            this.$root.$watch("chosenFile", async (newValue) => {
                this.chosenFile = newValue;

                if (newValue) {
                    this.modal = true;
                    this.requesting = false;
                    this.success = false;
                    this.error = false;
                    this.errorMessage = "";

                    const CancelToken = axios.CancelToken;
                    this.source = CancelToken.source();

                    // Verify App settings
                    this.runAPI(
                        "settings/check",
                        this,
                        "ymsSettings",
                        "ymsSettingsChecked"
                    ).then((response) => {
                        this.ymsSettings = response.data;

                        this.ymsSettingsChecked = true;
                    });

                    // Get current user Id
                    this.runAPI("user/id", this, "currentUser.id").then((response) => {
                        this.currentUser.id = response.data;
                        this.getCurrentUser();
                    });
                    this.runAPI("user/email", this, "currentUser.email").then(
                        (response) => {
                            this.currentUser.email = response.data;
                            this.getCurrentUser();
                        }
                    );

                    // Fill Nextcloud users list
                    // this.getUsersList();
                } else {
                    this.modal = false;
                }
            });
        } catch (error) {
            console.error(error.message);
        }
    },

    mounted() {
        this.resetInputs();
    },

    methods: {
        resetInputs: function () {
            this.user = "";
            this.userResults = {};
            this.noUserResults = false;
            this.usersLoading = false;
            this.recipientType = this.constantes.self;
            this.externalUserEmail = "";
            this.localUser = [];
            this.selfDisabled = false;
        },
        addUser(item) {
            this.user = this.localUser.uid = item.value.shareWith;
            this.localUser.subtitle = item.label;
            this.userResults = {};
            this.noUserResults = false;
        },
        cancelSearchLabel() {
            return t(appName, "Cancel search");
        },
        isSearchingUser() {
            return this.user !== "";
        },
        abortUserSearch() {
            this.noUserResults = false;
            this.usersLoading = false;
            this.userResults = {};
            this.user = "";
        },

        handleUserInput() {
            this.error = false;
            this.noUserResults = false;
            this.usersLoading = true;
            this.userResults = {};
            this.debounceSearchUsers();
        },

        abortUserSearch() {
            this.noUserResults = false;
            this.usersLoading = false;
            this.userResults = {};
            this.user = "";
        },

        debounceSearchUsers: debounce(function () {
            this.searchUsers();
        }, 250),

        async searchUsers() {
            try {
                const search = async (search, type) => {
                    return await axios.post(
                        generateOcsUrl(`apps/${appName}/api/v1/users/all`),
                        {
                            search,
                            type,
                        }
                    );
                };

                if (this.user.length >= 3) {
                    const response = await search(this.user, "user");
                    // this.userResults = response?.data?.ocs?.data || {};
                    this.userResults = response?.data || {};
                    if (Array.isArray(this.userResults)) {
                        this.userResults = {};
                    }
                    this.usersLoading = false;
                    const users = this.userResults.users || [];
                    const exact = this.userResults.exact?.users || [];
                    if (!users.length && !exact.length) {
                        this.noUserResults = true;
                    }
                }
            } catch (exception) {
                console.error(exception);
                showError(t(appName, "An error occurred while performing the search"));
            }
        },

        commonSignatureServerPost({
            urlPost,
            signerEmail = "",
            nxcUsername = "",
            appUrl = "",
        } = {}) {
            try {
                this.error = false;
                this.requesting = true;
                let urlRequest = generateOcsUrl(baseUrl + urlPost);

                axios({
                    url: urlRequest,
                    method: "POST",
                    // timeout: 10000,
                    data: {
                        path: this.chosenFile.path,
                        appUrl: generateUrl(baseUrl + appUrl),
                        signatureType: this.signatureTypeSelected,
                        email: signerEmail,
                        username: nxcUsername,
                        fileId: this.chosenFile.fileid,
                    },
                })
                    .then((response) => {
                        this.requesting = false;
                        if (
                            response.data.session !== null &&
                            response.data.session !== ""
                        ) {
                            this.success = true;
                            const yumisignCallback =
                                `${location.protocol}//${location.host}` +
                                // generateUrl(`/apps/${appName}`) +
                                generateUrl(
                                    `/apps/${appName}/sign/mobile/async/external/submit`
                                ) +
                                "?" +
                                `&workspaceId=${response.data.workspaceId}` +
                                `&workflowId=${response.data.workflowId}` +
                                `&envelopeId=${response.data.envelopeId}` +
                                `&url=${window.location.href}`;

                            this.designerUrl =
                                response.data.designerUrl +
                                "?callback=" +
                                encodeURIComponent(yumisignCallback);
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
            } catch (error) {
                console.error(error.message);
            }
        },
        changeNcSelectvalue: function (radiovalue) {
            try {
                if (!(this.selfDisabled && radiovalue === this.constantes.self)) {
                    this.recipientType = radiovalue;
                }
            } catch (error) {
                console.error(error.message);
            }
        },
        getBasename: function () {
            try {
                return this.chosenFile ? this.chosenFile._attributes.basename : "";
            } catch (error) {
                console.error(error.message);
            }
        },
        getCurrentUser: function () {
            this.currentUserFullData = "";
            try {

                switch (true) {
                    case this.isEmail(this.currentUser.id):
                        this.currentUserFullData = this.currentUser.id;
                        break;
                    case this.currentUser.email === "" || this.currentUser.email === null:
                        this.recipientType = this.constantes.nextcloud;
                        this.selfDisabled = true;
                        this.currentUserFullData = t(
                            appName,
                            "Your email is not defined; self signature is disabled"
                        );

                        break;
                    default:
                        this.currentUserFullData =
                            this.currentUser.id + "<" + this.currentUser.email + ">";
                        break;
                }
            } catch (error) {
                console.error(error.message);
                this.currentUserFullData = "";
            }
        },
        isSettingOK: function () {
            try {
                return this.ymsSettings;
            } catch (error) {
                console.error(error.message);
            }
        },
        isSettingKO: function () {
            try {
                return !this.isSettingOK() && this.ymsSettingsChecked;
            } catch (error) {
                console.error(error.message);
            }
        },
        isTransactionInProgress: function () {
            try {
                return this.requesting || this.success;
            } catch (error) {
                console.error(error.message);
            }
        },
        isRequesting: function () {
            try {
                return this.requesting;
            } catch (error) {
                console.error(error.message);
            }
        },
        isSuccesNotSimpleTypeSignature: function () {
            try {
                return !this.signatureTypeSelected && this.success;
            } catch (error) {
                console.error(error.message);
            }
        },
        isEmailList: function (listToCheck) {
            try {
                // let formattedString = listToCheck.replace(/,/g, ";");
                let formattedString = listToCheck.replaceAll(new RegExp(/,/g), ";");
                // let formattedString = listToCheck.replaceAll(",", ";");
                let emailsToCheck = formattedString.split(";");

                let validEmails = 0;
                let scope = this;
                emailsToCheck.forEach(function (item, index) {
                    if (!scope.isEmail(item)) {
                        return false;
                    } else {
                        validEmails++;
                    }
                    return true;
                });

                if (emailsToCheck.length !== validEmails) {
                    throw new Error("Invalid mail format");
                } else {
                    return true;
                }
            } catch (error) {
                console.error(error.message);
                this.errorMessage = error.message;
                this.error = true;
            }
        },
        isEmail: function (emailToCheck) {
            try {
                var pattern = new RegExp(
                    /^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i
                );
                return pattern.test(emailToCheck);
            } catch (error) {
                console.error(error.message);
            }
        },
        submitSignature: function () {
            try {
                switch (true) {
                    case this.recipientType === "self":
                        console.info("#1 is chosen");
                        this.commonSignatureServerPost({
                            urlPost: "/api/v1/sign/mobile",
                            appUrl: "/webhook",
                        });
                        break;

                    case this.recipientType === "nextcloud" && this.localUser !== null:
                        if (this.isEmail(this.localUser.subtitle)) {
                            console.info("#2 is chosen");
                            this.commonSignatureServerPost({
                                urlPost: "/api/v1/sign/mobile/async/local",
                                signerEmail: this.localUser.subtitle,
                                nxcUsername: this.localUser.uid,
                                appUrl: "/webhook",
                            });
                        } else {
                            this.errorMessage = t(appName, "Invalid email");
                        }
                        break;
                    case this.recipientType === "external" &&
                        this.externalUserEmail !== "" &&
                        this.signatureTypeSelected !== "":
                        if (this.isEmailList(this.externalUserEmail)) {
                            console.info("#3 is chosen");
                            this.commonSignatureServerPost({
                                urlPost: "/api/v1/sign/mobile/async/external",
                                signerEmail: this.externalUserEmail,
                                appUrl: "/webhook",
                            });
                        } else {
                            this.errorMessage = t(appName, "Invalid email");
                        }
                        break;

                    default:
                        console.log(
                            `No chosen option : recipientType=${
                                this.recipientType
                            } / localUser=${JSON.stringify(
                                this.localUser
                            )} / externalUserEmail=${
                                this.externalUserEmail
                            } / signatureTypeSelected=${this.signatureTypeSelected}`
                        );
                        break;
                }
            } catch (error) {
                console.error(error.message);
            }
        },

        runAPI: function (urlApi, mainThis, variableToFill, flag = "") {
            try {
                return (
                    axios
                        .get(
                            generateOcsUrl(`apps/${appName}/api/v1/${urlApi}`),
                            {},
                            {
                                cancelToken: this.source.token,
                            }
                        )
                        .catch((error) => {
                            mainThis[variableToFill] = null;
                            this.error = true;
                            console.log(error);
                        })
                );
            } catch (error) {
                console.error(error.message);
            }
        },
        closeModal: function () {
            try {
                this.resetInputs();
                this.$root.$emit("dialog:closed");
            } catch (error) {
                console.error(error.message);
            }
        },
    },
};
</script>

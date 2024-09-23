import { Controller } from 'stimulus';
import $ from 'jquery';
import { success } from 'tata-js';

export default class extends Controller {

    passwordAdmin;
    passwordRegion;
    passwordPlayer;

    connect(event)
    {
        this.passwordAdmin = JSON.parse($('#game_list_user_access_form_passwordAdmin').val()).admin;
        this.passwordRegion = JSON.parse($('#game_list_user_access_form_passwordAdmin').val()).region;
        this.passwordPlayer = JSON.parse($('#game_list_user_access_form_passwordPlayer').val());
        this.setupInitialFormState(event);
    }

    setupInitialFormState(event)
    {
        $('#game_list_user_access_form_providerAdmin').val(this.passwordAdmin.provider);
        if (this.passwordAdmin.provider == 'local') {
            $('#adminUserFields').hide();
            $('#game_list_user_access_form_passwordAdminRaw').val(this.passwordAdmin.value);
        } else {
            $('#adminPasswordFields').hide();
            this.addInitialUserInput(event, this.passwordAdmin.value, 'usersAdmin');
        }
        $('#game_list_user_access_form_providerRegion').val(this.passwordRegion.provider);
        if (this.passwordRegion.provider == 'local') {
            $('#regionUserFields').hide();
            $('#game_list_user_access_form_passwordRegionRaw').val(this.passwordRegion.value);
        } else {
            $('#regionPasswordFields').hide();
            this.addInitialUserInput(event, this.passwordRegion.value, 'usersRegion');
        }
        $('#game_list_user_access_form_providerPlayer').val(this.passwordPlayer.provider);
        if (this.passwordPlayer.provider == 'local') {
            $('#playerUserFields').hide();
            this.addInitialPlayerPasswordInput();
        } else {
            $('#playerPasswordFields').hide();
            this.addInitialPlayerUserInput(event);
        }
    }

    translatePasswordAdmin()
    {
        this.passwordAdmin.provider = $('#game_list_user_access_form_providerAdmin').val();
        if (this.passwordAdmin.provider == 'local') {
            this.passwordAdmin.value = $('#game_list_user_access_form_passwordAdminRaw').val();
        } else {
            let newUsersAdmin = '';
            $(`input[name^="game_list_user_access_form[usersAdmin]"]`).each(
                function() { if ($(this).val()) { newUsersAdmin += $(this).val() + '|'; } }
            );
            this.passwordAdmin.value = newUsersAdmin.substring(0, newUsersAdmin.length - 1);
        }

        this.passwordRegion.provider = $('#game_list_user_access_form_providerRegion').val();
        if (this.passwordRegion.provider == 'local') {
            this.passwordRegion.value = $('#game_list_user_access_form_passwordRegionRaw').val();
        } else {
            let newUsersRegion = '';;
            $(`input[name^="game_list_user_access_form[usersRegion]"]`).each(
                function() { if ($(this).val()) { newUsersRegion += $(this).val() + '|'; } }
            );
            this.passwordAdmin.value = newUsersRegion.substring(0, newUsersRegion.length - 1);
        }

        $('#game_list_user_access_form_passwordAdmin').val(
            JSON.stringify({
                admin: this.passwordAdmin,
                region: this.passwordRegion
            })
        );
    }

    translatePasswordPlayer()
    {
        this.passwordPlayer.provider = $('#game_list_user_access_form_providerPlayer').val();
        if (this.passwordPlayer.provider == 'local') {
            if ($('#game_list_user_access_form_passwordPlayerall').val()) {
                for (const team in this.passwordPlayer.value) {
                    this.passwordPlayer.value[team] = $('#game_list_user_access_form_passwordPlayerall').val();
                }
            } else {
                for (const team in this.passwordPlayer.value) {
                    this.passwordPlayer.value[team] = $(`#game_list_user_access_form_passwordPlayerCountry${team}`).val();
                }                
            }
        } else {
            let newUsersPlayer = '';;
            $(`input[name^="game_list_user_access_form[usersPlayerall]"]`).each(
                function() { if ($(this).val()) { newUsersPlayer += $(this).val() + '|'; } }
            );
            if (newUsersPlayer) {
                for (const team in this.passwordPlayer.value) {
                    this.passwordPlayer.value[team] = newUsersPlayer;
                }
            } else {
                let newUsersPlayerTeams = {};
                for (const team in this.passwordPlayer.value) {
                    newUsersPlayerTeams[team] = '';
                    $(`input[name^="game_list_user_access_form[usersPlayerCountry${team}]"]`).each(
                        function() { if ($(this).val()) { newUsersPlayerTeams[team] += $(this).val() + '|'; } }
                    );
                    if (newUsersPlayerTeams[team]) {
                        this.passwordPlayer.value[team] = newUsersPlayerTeams[team].substring(0, newUsersPlayerTeams[team].length - 1);
                    } else {
                        this.passwordPlayer.value[team] = '';
                    }
                }
            }
        }

        $('#game_list_user_access_form_passwordPlayer').val(JSON.stringify(this.passwordPlayer));
    }

    translateFormStateAndSubmit(event)
    {
        event.preventDefault();
        this.translatePasswordAdmin();
        this.translatePasswordPlayer();
        window.dispatchEvent(new CustomEvent("user-access-saving"));
    }

    toggleProvider(event)
    {
        let passwordFields = `${event.currentTarget.dataset.provider}PasswordFields`;
        let userFields = `${event.currentTarget.dataset.provider}UserFields`;
        if (event.currentTarget.selectedOptions[0].value == 'local') {
            $(`#${passwordFields}`).show();
            $(`#${userFields}`).hide();
        } else {
            $(`#${passwordFields}`).hide();
            $(`#${userFields}`).show();
        }
    }

    addInitialPlayerUserInput(event)
    {
        if (this.oneCommonCountryValue() && Object.values(this.passwordPlayer.value)[0]) {
            this.addInitialUserInput(event, Object.values(this.passwordPlayer.value)[0], 'usersPlayerall');
        } else {
            for (const team in this.passwordPlayer.value) {
                if (this.passwordPlayer.value[team]) {
                    this.addInitialUserInput(event, this.passwordPlayer.value[team], `usersPlayerCountry${team}`);
                }
            }
        }
    }

    addInitialPlayerPasswordInput()
    {
        if (this.oneCommonCountryValue()) {
            $('#game_list_user_access_form_passwordPlayerall').val(Object.values(this.passwordPlayer.value)[0]);
        } else {
            for (const team in this.passwordPlayer.value) {
                $(`#game_list_user_access_form_passwordPlayerCountry${team}`).val(this.passwordPlayer.value[team]);
            }
        }
    }

    oneCommonCountryValue()
    {
        let previousTeamPassword = null;
        for (const team in this.passwordPlayer.value) {
            if (previousTeamPassword !== null && this.passwordPlayer.value[team] != previousTeamPassword) {
                return false;
            }
            previousTeamPassword = this.passwordPlayer.value[team];
        }
        return true;
    }

    addInitialUserInput(event, passwordTypeValue, userType)
    {
        let passwordTypeValueArray = passwordTypeValue.split('|');
        for (const user of passwordTypeValueArray) {
            this.addUserInput(event, userType, user);
        }
    }

    addUserInput(event, targetOverride = null, content = '')
    {
        var targetName = targetOverride ?? event.currentTarget.dataset.collection;
        var target = $(`#${targetName}`);
        let inputToAdd = target.data('prototype').replace(/__name__/g, $(`input[name^="game_list_user_access_form[${targetName}]"]`).length);
        inputToAdd = inputToAdd.replace(/type="text"/g, `type="text" value="${content}"`);
        target.before(inputToAdd);
    }
    
}
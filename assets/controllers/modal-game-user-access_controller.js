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
        this.setupInitialState(event);
    }

    setupInitialState(event)
    {
        if (this.passwordAdmin.provider == 'local') {
            $('#adminUserFields').hide();
            $('#game_list_user_access_form_password_admin').val(this.passwordAdmin.value);
        } else {
            $('#adminPasswordFields').hide();
            $('#game_list_user_access_form_provider_admin').val(this.passwordAdmin.provider);
            this.addInitialUserInput(event, this.passwordAdmin.value, 'usersAdmin');
        }

        if (this.passwordRegion.provider == 'local') {
            $('#regionUserFields').hide();
            $('#game_list_user_access_form_password_region').val(this.passwordRegion.value);
        } else {
            $('#regionPasswordFields').hide();
            $('#game_list_user_access_form_provider_region').val(this.passwordRegion.provider);
            this.addInitialUserInput(event, this.passwordRegion.value, 'usersRegion');
        }

        if (this.passwordPlayer.provider == 'local') {
            $('#playerUserFields').hide();
        } else {
            $('#playerPasswordFields').hide();
        }
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

    addInitialUserInput(event, passwordTypeValue, userType)
    {
        let passwordTypeValueArray = passwordTypeValue.split('|');
        for (const user of passwordTypeValueArray) {
            this.addUserInput(event, userType, user);
        }
    }

    addUserInput(event, targetOverride = '', content = '')
    {
        if (targetOverride) {
            var target = $(`#${targetOverride}`);
        } else {
            var target = $(`#${event.currentTarget.dataset.collection}`);
        }
        let inputToAdd = target.data('prototype').replace(/__name__/g, 0);
        inputToAdd = inputToAdd.replace(/type="text"/g, `type="text" value="${content}"`);
        target.before(inputToAdd);
    }
    
}
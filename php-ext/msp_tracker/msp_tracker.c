#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "ext/standard/info.h"
#include "php_msp_tracker.h"
#include "Zend/zend_exceptions.h"
#include <stdlib.h>
#include <string.h>

static void (*msp_tracker_original_execute_internal)(zend_execute_data *execute_data, zval *return_value) = NULL;

ZEND_BEGIN_MODULE_GLOBALS(msp_tracker)
    zval callback;
    zval pending_events;
    zend_bool has_callback;
    zend_bool callback_enabled;
    zend_bool in_callback;
ZEND_END_MODULE_GLOBALS(msp_tracker)

ZEND_DECLARE_MODULE_GLOBALS(msp_tracker)

#define MSP_TRACKER_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(msp_tracker, v)

static void msp_tracker_dispatch_payload(zval *payload);

static void php_msp_tracker_init_globals(zend_msp_tracker_globals *globals)
{
    ZVAL_UNDEF(&globals->callback);
    ZVAL_UNDEF(&globals->pending_events);
    globals->has_callback = 0;
    globals->callback_enabled = 1;
    globals->in_callback = 0;
}

#define MSP_TRACKER_MAX_PENDING_EVENTS 512

static void msp_tracker_queue_payload(zval *payload)
{
    zval payload_copy;

    if (Z_TYPE(MSP_TRACKER_G(pending_events)) != IS_ARRAY) {
        array_init(&MSP_TRACKER_G(pending_events));
    }

    if (zend_hash_num_elements(Z_ARRVAL(MSP_TRACKER_G(pending_events))) >= MSP_TRACKER_MAX_PENDING_EVENTS) {
        return;
    }

    ZVAL_COPY(&payload_copy, payload);
    add_next_index_zval(&MSP_TRACKER_G(pending_events), &payload_copy);
}

static void msp_tracker_flush_pending_payloads(void)
{
    zval *payload;

    if (!MSP_TRACKER_G(callback_enabled) || !MSP_TRACKER_G(has_callback)) {
        return;
    }

    if (Z_TYPE(MSP_TRACKER_G(pending_events)) != IS_ARRAY) {
        return;
    }

    ZEND_HASH_FOREACH_VAL(Z_ARRVAL(MSP_TRACKER_G(pending_events)), payload) {
        msp_tracker_dispatch_payload(payload);
    } ZEND_HASH_FOREACH_END();

    zend_hash_clean(Z_ARRVAL(MSP_TRACKER_G(pending_events)));
}

static void msp_tracker_dispatch_payload(zval *payload)
{
    zval retval;
    zval params[1];

    if (!MSP_TRACKER_G(callback_enabled) || MSP_TRACKER_G(in_callback)) {
        return;
    }

    if (!MSP_TRACKER_G(has_callback)) {
        msp_tracker_queue_payload(payload);
        return;
    }

    MSP_TRACKER_G(in_callback) = 1;
    ZVAL_COPY(&params[0], payload);

    if (call_user_function(EG(function_table), NULL, &MSP_TRACKER_G(callback), &retval, 1, params) == FAILURE) {
        php_error_docref(NULL, E_WARNING, "Failed to invoke registered callback");
        zval_ptr_dtor(&params[0]);
        MSP_TRACKER_G(in_callback) = 0;
        return;
    }

    zval_ptr_dtor(&retval);
    zval_ptr_dtor(&params[0]);
    MSP_TRACKER_G(in_callback) = 0;
}

static void msp_tracker_add_dsn_part(zval *payload, const char *dsn, const char *needle, const char *field)
{
    const char *start;
    const char *end;
    size_t length;

    if (dsn == NULL || needle == NULL || field == NULL) {
        return;
    }

    start = strstr(dsn, needle);
    if (start == NULL) {
        return;
    }

    start += strlen(needle);
    end = strchr(start, ';');
    length = (size_t) (end ? (end - start) : strlen(start));
    if (length == 0) {
        return;
    }

    add_assoc_stringl(payload, field, start, length);
}


static zend_long msp_tracker_query_pdo_connection_id_from_object(zval *pdo_object)
{
    zval func_name;
    zval query_arg;
    zval query_retval;
    zval fetch_func;
    zval fetch_retval;
    zend_long conn_id = 0;

    if (pdo_object == NULL || Z_TYPE_P(pdo_object) != IS_OBJECT) {
        return 0;
    }

    ZVAL_STRING(&func_name, "query");
    ZVAL_STRING(&query_arg, "SELECT CONNECTION_ID()");
    ZVAL_UNDEF(&query_retval);

    if (call_user_function(NULL, pdo_object, &func_name, &query_retval, 1, &query_arg) == FAILURE
        || EG(exception) != NULL
        || Z_TYPE(query_retval) != IS_OBJECT) {
        if (EG(exception) != NULL) {
            zend_clear_exception();
        }
        zval_ptr_dtor(&func_name);
        zval_ptr_dtor(&query_arg);
        if (!Z_ISUNDEF(query_retval)) {
            zval_ptr_dtor(&query_retval);
        }
        return 0;
    }

    zval_ptr_dtor(&func_name);
    zval_ptr_dtor(&query_arg);

    ZVAL_STRING(&fetch_func, "fetchColumn");
    ZVAL_UNDEF(&fetch_retval);

    if (call_user_function(NULL, &query_retval, &fetch_func, &fetch_retval, 0, NULL) == SUCCESS
        && EG(exception) == NULL) {
        if (Z_TYPE(fetch_retval) == IS_STRING) {
            conn_id = (zend_long) atol(Z_STRVAL(fetch_retval));
        } else if (Z_TYPE(fetch_retval) == IS_LONG) {
            conn_id = Z_LVAL(fetch_retval);
        }
    }

    if (EG(exception) != NULL) {
        zend_clear_exception();
    }

    zval_ptr_dtor(&fetch_func);
    if (!Z_ISUNDEF(fetch_retval)) {
        zval_ptr_dtor(&fetch_retval);
    }
    zval_ptr_dtor(&query_retval);

    return conn_id;
}

static void msp_tracker_try_emit_pdo_connect_event(zend_execute_data *execute_data, zval *return_value)
{
    zend_function *func;
    zval payload;
    zval *dsn_arg;
    zval *pdo_object = NULL;
    zend_string *dsn_string = NULL;
    const char *dsn_value = NULL;
    const char *colon;
    const char *source = NULL;

    func = execute_data->func;
    if (func == NULL || func->common.scope == NULL || func->common.function_name == NULL) {
        return;
    }

    if (strcmp(ZSTR_VAL(func->common.scope->name), "PDO") != 0) {
        return;
    }

    if (strcmp(ZSTR_VAL(func->common.function_name), "__construct") == 0) {
        if (Z_TYPE(execute_data->This) != IS_OBJECT) {
            return;
        }
        pdo_object = &execute_data->This;
        source = "pdo::__construct";
    } else if (strcmp(ZSTR_VAL(func->common.function_name), "connect") == 0) {
        if (return_value == NULL || Z_TYPE_P(return_value) != IS_OBJECT) {
            return;
        }
        pdo_object = return_value;
        source = "pdo::connect";
    } else {
        return;
    }

    if (ZEND_CALL_NUM_ARGS(execute_data) < 1) {
        return;
    }

    dsn_arg = ZEND_CALL_ARG(execute_data, 1);
    if (dsn_arg == NULL) {
        return;
    }

    if (Z_TYPE_P(dsn_arg) == IS_STRING) {
        dsn_string = Z_STR_P(dsn_arg);
    } else {
        dsn_string = zval_get_string(dsn_arg);
    }

    if (dsn_string == NULL || ZSTR_LEN(dsn_string) == 0) {
        if (Z_TYPE_P(dsn_arg) != IS_STRING && dsn_string != NULL) {
            zend_string_release(dsn_string);
        }
        return;
    }

    dsn_value = ZSTR_VAL(dsn_string);

    array_init(&payload);
    add_assoc_string(&payload, "event", "pdo_connect_opened");
    add_assoc_string(&payload, "source", (char *) source);
    add_assoc_string(&payload, "driver", "pdo");
    add_assoc_string(&payload, "dsn", (char *) dsn_value);

    colon = strchr(dsn_value, ':');
    if (colon != NULL && colon > dsn_value) {
        add_assoc_stringl(&payload, "dsn_driver", dsn_value, (size_t) (colon - dsn_value));
    }

    msp_tracker_add_dsn_part(&payload, dsn_value, "host=", "db_host");
    msp_tracker_add_dsn_part(&payload, dsn_value, "port=", "db_port");
    msp_tracker_add_dsn_part(&payload, dsn_value, "dbname=", "db_name");

    /* Query the actual MySQL connection_id from this PDO connection so the
     * app-side handler can upsert it into msp_tracker.connection correctly. */
    {
        zend_long conn_id = msp_tracker_query_pdo_connection_id_from_object(pdo_object);
        if (conn_id > 0) {
            add_assoc_long(&payload, "connection_id", conn_id);
        }
    }

    msp_tracker_dispatch_payload(&payload);
    zval_ptr_dtor(&payload);

    if (Z_TYPE_P(dsn_arg) != IS_STRING) {
        zend_string_release(dsn_string);
    }
}

static void msp_tracker_execute_internal(zend_execute_data *execute_data, zval *return_value)
{
    if (msp_tracker_original_execute_internal != NULL) {
        msp_tracker_original_execute_internal(execute_data, return_value);
    } else {
        execute_internal(execute_data, return_value);
    }

    if (EG(exception) != NULL) {
        return;
    }

    msp_tracker_try_emit_pdo_connect_event(execute_data, return_value);
}

PHP_FUNCTION(msp_tracker_register_connection_callback)
{
    zval *callback;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ZVAL(callback)
    ZEND_PARSE_PARAMETERS_END();

    if (!zend_is_callable(callback, 0, NULL)) {
        php_error_docref(NULL, E_WARNING, "Argument must be callable");
        RETURN_FALSE;
    }

    if (MSP_TRACKER_G(has_callback)) {
        zval_ptr_dtor(&MSP_TRACKER_G(callback));
        MSP_TRACKER_G(has_callback) = 0;
    }

    ZVAL_COPY(&MSP_TRACKER_G(callback), callback);
    MSP_TRACKER_G(has_callback) = 1;
    msp_tracker_flush_pending_payloads();

    RETURN_TRUE;
}

PHP_FUNCTION(msp_tracker_emit_connection_event)
{
    zval *payload;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY(payload)
    ZEND_PARSE_PARAMETERS_END();

    if (!MSP_TRACKER_G(callback_enabled) || !MSP_TRACKER_G(has_callback) || MSP_TRACKER_G(in_callback)) {
        RETURN_FALSE;
    }

    msp_tracker_dispatch_payload(payload);
    RETURN_TRUE;
}

PHP_FUNCTION(msp_tracker_set_enabled)
{
    bool enabled;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_BOOL(enabled)
    ZEND_PARSE_PARAMETERS_END();

    MSP_TRACKER_G(callback_enabled) = enabled ? 1 : 0;
}

PHP_FUNCTION(msp_tracker_is_enabled)
{
    RETURN_BOOL(MSP_TRACKER_G(callback_enabled));
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_msp_tracker_register_connection_callback, 0, 1, _IS_BOOL, 0)
    ZEND_ARG_CALLABLE_INFO(0, callback, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_msp_tracker_emit_connection_event, 0, 1, _IS_BOOL, 0)
    ZEND_ARG_ARRAY_INFO(0, payload, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_msp_tracker_set_enabled, 0, 1, IS_VOID, 0)
    ZEND_ARG_TYPE_INFO(0, enabled, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_msp_tracker_is_enabled, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry msp_tracker_functions[] = {
    PHP_FE(msp_tracker_register_connection_callback, arginfo_msp_tracker_register_connection_callback)
    PHP_FE(msp_tracker_emit_connection_event, arginfo_msp_tracker_emit_connection_event)
    PHP_FE(msp_tracker_set_enabled, arginfo_msp_tracker_set_enabled)
    PHP_FE(msp_tracker_is_enabled, arginfo_msp_tracker_is_enabled)
    PHP_FE_END
};

PHP_MINIT_FUNCTION(msp_tracker)
{
    ZEND_INIT_MODULE_GLOBALS(msp_tracker, php_msp_tracker_init_globals, NULL);
    msp_tracker_original_execute_internal = zend_execute_internal;
    zend_execute_internal = msp_tracker_execute_internal;
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(msp_tracker)
{
    /* Only restore the process-level execute hook here.
     * Do NOT touch module globals (thread-local storage) in MSHUTDOWN:
     * in ZTS/FrankenPHP worker mode the thread context is already torn down
     * by the time MSHUTDOWN runs, which causes a segfault. */
    zend_execute_internal = msp_tracker_original_execute_internal;
    return SUCCESS;
}

PHP_RINIT_FUNCTION(msp_tracker)
{
    /* In ZTS builds (required by FrankenPHP worker mode) we must refresh the
     * TSRM cache pointer at the start of every request so that MSP_TRACKER_G()
     * macro resolves to the correct per-thread storage. */
#if defined(COMPILE_DL_MSP_TRACKER) && defined(ZTS)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif
    if (Z_TYPE(MSP_TRACKER_G(pending_events)) != IS_ARRAY) {
        array_init(&MSP_TRACKER_G(pending_events));
    }
    return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(msp_tracker)
{
    /* Release the per-thread callback zval here while thread context is valid. */
    if (MSP_TRACKER_G(has_callback)) {
        zval_ptr_dtor(&MSP_TRACKER_G(callback));
        ZVAL_UNDEF(&MSP_TRACKER_G(callback));
        MSP_TRACKER_G(has_callback) = 0;
    }

    if (Z_TYPE(MSP_TRACKER_G(pending_events)) != IS_UNDEF) {
        zval_ptr_dtor(&MSP_TRACKER_G(pending_events));
        ZVAL_UNDEF(&MSP_TRACKER_G(pending_events));
    }

    return SUCCESS;
}

PHP_MINFO_FUNCTION(msp_tracker)
{
    php_info_print_table_start();
    php_info_print_table_header(2, "msp_tracker support", "enabled");
    php_info_print_table_row(2, "version", PHP_MSP_TRACKER_VERSION);
    php_info_print_table_end();
}

zend_module_entry msp_tracker_module_entry = {
    STANDARD_MODULE_HEADER,
    "msp_tracker",
    msp_tracker_functions,
    PHP_MINIT(msp_tracker),
    PHP_MSHUTDOWN(msp_tracker),
    PHP_RINIT(msp_tracker),
    PHP_RSHUTDOWN(msp_tracker),
    PHP_MINFO(msp_tracker),
    PHP_MSP_TRACKER_VERSION,
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_MSP_TRACKER
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(msp_tracker)
#endif



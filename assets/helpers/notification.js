import { success, error, info } from 'tata-js';

export function successNotification(successMessage)
{
    success('Success', successMessage, { position: 'mm', duration: 10000 });
}

export function errorNotification(errorMessage)
{
    error('Error', errorMessage, { position: 'mm', duration: 10000 });
}

export function noticeNotification(noticeMessage)
{
    info('Notice', noticeMessage, { position: 'mm', duration: 30000 });
}

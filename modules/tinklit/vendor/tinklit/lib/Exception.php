<?php
namespace Tinklit;

class Exception
{
    public static function formatError($error)
    {
        $reason= '';
        $message = '';

        if (isset($error['reason']))
            $reason = $error['reason'];

        if (isset($error['error']))
            $message = $error['error'];
        
        if (isset($error['message']))
            $message = $error['message'];
        
        return $message;
    }

    public static function throwException($http_status, $error)
    {
        $reason = $error['error'];

        switch ($http_status) {
            case 400:
                switch ($reason) {
                    case 'CredentialsMissing': throw new CredentialsMissing(self::formatError($error));
                    case 'BadEnvironment': throw new BadEnvironment(self::formatError($error));
                    default: throw new BadRequest(self::formatError($error));
                }
            case 401:
                switch ($reason) {
                    case 'BadCredentials': throw new BadCredentials(self::formatError($error));
                    default: throw new Unauthorized(self::formatError($error));
                }
            case 404:
                switch ($reason) {
                    case 'PageNotFound': throw new PageNotFound(self::formatError($error));
                    case 'RecordNotFound': throw new RecordNotFound(self::formatError($error));
                    case 'InvoiceNotFound': throw new InvoiceNotFound(self::formatError($error));
                    default: throw new NotFound(self::formatError($error));
                }
	   case 406:
                switch ($reason) {
                    case 'PageNotAcceptable': throw new PageNotAcceptable(self::formatError($error));
                    case 'RecordNotAcceptable': throw new RecordNotAcceptable(self::formatError($error));
                    case 'InvoiceNotAcceptable': throw new InvoiceNotAcceptable(self::formatError($error));
                    default: throw new NotAcceptable(self::formatError($error));
                }	
            case 422:
                switch ($reason) {
                    case 'InvoiceIsNotValid': throw new InvoiceIsNotValid(self::formatError($error));
                    default: throw new UnprocessableEntity(self::formatError($error));
                }
            case 429:
                switch ($reason) {
                    default: throw new RateLimitException(self::formatError($error));
                }
            case 500:
                switch ($reason) {
                    default: throw new InternalServerError(self::formatError($error));
                }
            default: throw new APIError(self::formatError($error));
        }
    }
}

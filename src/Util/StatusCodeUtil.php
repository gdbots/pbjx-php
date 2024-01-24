<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Util;

use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Enum\HttpCode;

/**
 * Simple conversions from our "Code" aka vendor codes
 * to http status codes and back.
 */
final class StatusCodeUtil
{
    public static function vendorToHttp(Code $code = Code::OK): HttpCode
    {
        if (Code::OK === $code) {
            return HttpCode::HTTP_OK;
        }

        switch ($code) {
            case Code::CANCELLED:
                return HttpCode::HTTP_CLIENT_CLOSED_REQUEST;

            case Code::UNKNOWN:
            case Code::DATA_LOSS:
            case Code::INTERNAL:
                return HttpCode::HTTP_INTERNAL_SERVER_ERROR;

            case Code::INVALID_ARGUMENT:
            case Code::OUT_OF_RANGE:
                return HttpCode::HTTP_BAD_REQUEST;

            case Code::DEADLINE_EXCEEDED:
                return HttpCode::HTTP_GATEWAY_TIMEOUT;

            case Code::NOT_FOUND:
                return HttpCode::HTTP_NOT_FOUND;

            case Code::ABORTED:
            case Code::ALREADY_EXISTS:
                return HttpCode::HTTP_CONFLICT;

            case Code::PERMISSION_DENIED:
                return HttpCode::HTTP_FORBIDDEN;

            case Code::UNAUTHENTICATED:
                return HttpCode::HTTP_UNAUTHORIZED;

            case Code::RESOURCE_EXHAUSTED:
                return HttpCode::HTTP_TOO_MANY_REQUESTS;

            // questionable... may not always be etag related.
            case Code::FAILED_PRECONDITION:
                return HttpCode::HTTP_PRECONDITION_FAILED;

            case Code::UNIMPLEMENTED:
                return HttpCode::HTTP_NOT_IMPLEMENTED;

            case Code::UNAVAILABLE:
                return HttpCode::HTTP_SERVICE_UNAVAILABLE;

            default:
                return HttpCode::HTTP_UNPROCESSABLE_ENTITY;
        }
    }

    public static function httpToVendor(HttpCode $httpCode = HttpCode::HTTP_OK): Code
    {
        if ($httpCode->value < 400) {
            return Code::OK;
        }

        switch ($httpCode) {
            case HttpCode::HTTP_CLIENT_CLOSED_REQUEST:
                return Code::CANCELLED;

            case HttpCode::HTTP_INTERNAL_SERVER_ERROR:
                return Code::INTERNAL;

            case HttpCode::HTTP_GATEWAY_TIMEOUT:
                return Code::DEADLINE_EXCEEDED;

            case HttpCode::HTTP_NOT_FOUND:
                return Code::NOT_FOUND;

            case HttpCode::HTTP_CONFLICT:
                return Code::ALREADY_EXISTS;

            case HttpCode::HTTP_FORBIDDEN:
                return Code::PERMISSION_DENIED;

            case HttpCode::HTTP_UNAUTHORIZED:
                return Code::UNAUTHENTICATED;

            case HttpCode::HTTP_TOO_MANY_REQUESTS:
                return Code::RESOURCE_EXHAUSTED;

            case HttpCode::HTTP_PRECONDITION_FAILED:
                return Code::FAILED_PRECONDITION;

            case HttpCode::HTTP_NOT_IMPLEMENTED:
                return Code::UNIMPLEMENTED;

            case HttpCode::HTTP_SERVICE_UNAVAILABLE:
                return Code::UNAVAILABLE;

            default:
                if ($httpCode->value >= 500) {
                    return Code::INTERNAL;
                }

                if ($httpCode->value > 400) {
                    return Code::INVALID_ARGUMENT;
                }

                return Code::OK;
        }
    }
}

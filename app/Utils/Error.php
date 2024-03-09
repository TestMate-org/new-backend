<?php declare (strict_types = 1);

namespace TestMate\Utils;

/**
 * @author TestMate <dev@testmate.org>
 */
class Error
{
    /**
     * Get error logging
     * @param \Exception $e
     * @return array
     */
    public static function get(\Exception $e)
    {
        return [
            'code' => $e->getCode(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'message' => $e->getMessage(),
        ];
    }
}

<?php

/**
 * PHPStan Bootstrap Stub
 * Defines global functions for static analysis without executing application code
 */

if (!function_exists('getGateway')) {
    /**
     * Get a gateway instance from the container
     *
     * @param class-string $class
     * @return mixed
     */
    function getGateway(string $class)
    {
        return new $class();
    }
}

if (!function_exists('getService')) {
    /**
     * Get a service instance from the container
     *
     * @param class-string $class
     * @return mixed
     */
    function getService(string $class)
    {
        return new $class();
    }
}

if (!function_exists('getContainer')) {
    /**
     * Get the DI container
     *
     * @return \League\Container\Container
     */
    function getContainer()
    {
        return new \League\Container\Container();
    }
}

if (!function_exists('getLogger')) {
    /**
     * Get the logger instance
     *
     * @return \Psr\Log\LoggerInterface
     */
    function getLogger()
    {
        return new \Monolog\Logger('app');
    }
}

if (!function_exists('getUserPermissionsForNavigation')) {
    /**
     * Get user permissions for navigation
     *
     * @param int $staffID
     * @return array<array>
     */
    function getUserPermissionsForNavigation(int $staffID): array
    {
        return [];
    }
}

if (!function_exists('hasPermission')) {
    /**
     * Check if user has permission
     *
     * @param int $staffID
     * @param string $module
     * @param string $action
     * @return bool
     */
    function hasPermission(int $staffID, string $module, string $action): bool
    {
        return true;
    }
}

if (!function_exists('getUserEditableTabsForStudents')) {
    /**
     * Get editable tabs for students
     *
     * @param int $staffID
     * @return array<string>
     */
    function getUserEditableTabsForStudents(int $staffID): array
    {
        return [];
    }
}

if (!function_exists('canUserEditTab')) {
    /**
     * Check if user can edit tab
     *
     * @param int $staffID
     * @param string $tab
     * @return bool
     */
    function canUserEditTab(int $staffID, string $tab): bool
    {
        return true;
    }
}

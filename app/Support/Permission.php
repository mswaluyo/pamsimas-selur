<?php

namespace App\Support;

/**
 * Kontrol akses sederhana per-role (menggantikan app\Core\Permission sistem asli).
 */
class Permission
{
    public const MATRIX = [
        'Administrator' => ['*'],
        'Operator' => ['dashboard', 'devices', 'monitoring', 'logs', 'templates', 'settings', 'meter', 'customers'],
        'Kasir' => ['dashboard', 'meter', 'payment', 'customers'],
        'Viewer' => ['dashboard', 'monitoring'],
    ];

    public static function can(string $role, string $module, string $action = 'read'): bool
    {
        $allowed = self::MATRIX[$role] ?? [];
        if (in_array('*', $allowed)) return true;
        if (!in_array($module, $allowed)) return false;
        // Viewer hanya read
        if ($role === 'Viewer') return $action === 'read';
        return true;
    }

    public static function abortUnlessCan(string $role, string $module, string $action = 'read'): void
    {
        if (!self::can($role, $module, $action)) {
            abort(403, 'Anda tidak memiliki akses ke modul ini.');
        }
    }
}

<?php
/**
 * Shared server-side validation helpers used by register.php, reset-password.php,
 * and api/profile.php's change-password action.
 */

declare(strict_types=1);

/**
 * @return string[] List of unmet requirements; empty array means the password is strong enough.
 */
function validate_password_strength(string $password): array
{
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = 'be at least 8 characters long';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'include at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'include at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'include at least one number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'include at least one symbol';
    }
    return $errors;
}

function validate_phone(string $phone): bool
{
    return $phone === '' || (bool) preg_match('/^[0-9+\-\s()]{7,20}$/', $phone);
}

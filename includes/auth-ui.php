<?php
/**
 * SnapVenture Admin - shared helpers for the auth pages
 * (login, register, forgot/reset password).
 *
 * Field errors are keyed by the input's id, e.g. $errors["email"].
 */

const AUTH_MIN_PASSWORD_LENGTH = 6;

/**
 * Banner shown at the top of the auth card.
 * $type is "success" or "error".
 */
function auth_alert(string $type, string $title, string $text = ""): string
{
    $icon = $type === "success"
        ? '<circle cx="12" cy="12" r="11" /><path class="auth-alert-check" d="M7 12.5l3.2 3.2L17 9" />'
        : '<circle cx="12" cy="12" r="11" /><path d="M12 7v6" /><path d="M12 16.8v.01" />';

    // Errors interrupt screen readers; success messages are announced politely
    $role = $type === "error" ? "alert" : "status";

    return '<div class="auth-alert ' . $type . '" role="' . $role . '">'
        . '<svg class="auth-alert-icon" viewBox="0 0 24 24" aria-hidden="true">' . $icon . '</svg>'
        . '<div><strong>' . htmlspecialchars($title) . '</strong>'
        . ($text !== "" ? '<span>' . htmlspecialchars($text) . '</span>' : "")
        . '</div></div>';
}

/**
 * aria attributes for an input: marks it invalid and links it to its
 * error (or hint) text so screen readers read it with the field.
 */
function auth_field_attrs(array $errors, string $field, bool $hasHint = false): string
{
    if (isset($errors[$field])) {
        return 'aria-invalid="true" aria-describedby="' . $field . '-error"';
    }

    return $hasHint ? 'aria-describedby="' . $field . '-hint"' : "";
}

/**
 * Text under an input: the field's error if it has one, otherwise its hint.
 */
function auth_field_message(array $errors, string $field, string $hint = ""): string
{
    if (isset($errors[$field])) {
        return '<p class="field-error" id="' . $field . '-error">'
            . htmlspecialchars($errors[$field]) . '</p>';
    }

    if ($hint !== "") {
        return '<p class="field-hint" id="' . $field . '-hint">'
            . htmlspecialchars($hint) . '</p>';
    }

    return "";
}

/**
 * Validates a new password + confirmation (register and reset password).
 * Returns field errors keyed "password" / "confirm_password".
 */
function auth_validate_new_password(string $password, string $confirmPassword): array
{
    $errors = [];

    if ($password === "") {
        $errors["password"] = "Please enter a password.";
    } elseif (strlen($password) < AUTH_MIN_PASSWORD_LENGTH) {
        $errors["password"] = "Password must be at least " . AUTH_MIN_PASSWORD_LENGTH . " characters.";
    }

    if ($confirmPassword === "") {
        $errors["confirm_password"] = "Please confirm your password.";
    } elseif (!isset($errors["password"]) && $password !== $confirmPassword) {
        $errors["confirm_password"] = "Passwords do not match.";
    }

    return $errors;
}

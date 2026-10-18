<?php
// Globale Helfer für Templates und Controller.
use App\Session;

/** HTML-Escaping. */
function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int $cents): string
{
    return number_format($cents / 100, 2, ',', '.') . ' €';
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Session::csrf()) . '">';
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function fmt_date(?string $d): string
{
    return $d ? date('d.m.Y', strtotime($d . ' UTC')) : '–';
}

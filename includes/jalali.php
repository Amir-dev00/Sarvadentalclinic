<?php

declare(strict_types=1);

/**
 * Gregorian ↔ Jalali helpers (Asia/Tehran display).
 * Storage remains Gregorian/MySQL DATETIME.
 */

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4)
        + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30 + 186));
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0)) ? 29 : 28,
        31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    for ($gm = 1; $gm <= 12 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return [$gy, $gm, $gd];
}

function to_jalali(?string $datetime, string $format = 'Y/m/d H:i'): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }
    [$jy, $jm, $jd] = gregorian_to_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
    $map = [
        'Y' => sprintf('%04d', $jy),
        'm' => sprintf('%02d', $jm),
        'd' => sprintf('%02d', $jd),
        'H' => date('H', $ts),
        'i' => date('i', $ts),
    ];
    return strtr($format, $map);
}
